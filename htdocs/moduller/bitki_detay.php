<?php
/**
 * GreenLog - Ultra Detay Modülü (v4.1 - Satır Bazlı Dinamik Etiket Yazdırma Aktif)
 */

$takson_id = isset($_GET['takson_id']) ? intval($_GET['takson_id']) : 0;
if ($takson_id <= 0) { 
    echo "<div style='font-family:sans-serif; padding:20px; text-align:center;'><h2>Hata!</h2><p>Geçersiz bitki kimliği.</p></div>";
    exit; 
}

try {
    // 1. Bitki Genel Bilgileri
    $takson_sorgu = $pdo->prepare("SELECT * FROM takson WHERE Takson_ID = ?");
    $takson_sorgu->execute([$takson_id]);
    $takson = $takson_sorgu->fetch(PDO::FETCH_ASSOC);

    if (!$takson) { 
        echo "Bitki bulunamadı.";
        return; 
    }

    // 2. Stok Durumu (Üst Kartlar İçin)
    $stok_sorgu = $pdo->prepare("
        SELECT 
            COUNT(*) as toplam_adet,
            SUM(CASE WHEN Aktiflik = 1 THEN 1 ELSE 0 END) as aktif_adet,
            SUM(CASE WHEN Aktiflik = 0 THEN 1 ELSE 0 END) as pasif_adet
        FROM bitki WHERE Takson_ID = ?
    ");
    $stok_sorgu->execute([$takson_id]);
    $stok = $stok_sorgu->fetch(PDO::FETCH_ASSOC);

    // 3. Bu Türe Ait Tüm Ekim Kayıtları
    $envanter_sorgu = $pdo->prepare("
        SELECT 
            b.Bitki_ID, b.Ekim_Tarihi, b.enlem, b.boylam, b.Aktiflik,
            s.Sera_Adi, l.Parsel_Bilgisi,
            p1.Ad_Soyad as Sorumlu_Personel,
            p2.Ad_Soyad as Eken_Personel
        FROM bitki b
        LEFT JOIN sera s ON b.Sera_ID = s.Sera_ID
        LEFT JOIN lokasyon l ON b.Lokasyon_ID = l.Lokasyon_ID
        LEFT JOIN personel p1 ON b.Sorumlu_Pers_ID = p1.Personel_ID
        LEFT JOIN personel p2 ON b.Eken_Pers_ID = p2.Personel_ID
        WHERE b.Takson_ID = ?
        ORDER BY b.Ekim_Tarihi DESC
    ");
    $envanter_sorgu->execute([$takson_id]);
    $envanter_listesi = $envanter_sorgu->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) { 
    echo "Veritabanı hatası: " . $e->getMessage();
    return; 
}

$detay_link = "https://greenlog.42web.io/panel.php?sayfa=bitki_detay&takson_id=" . $takson_id;
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<style>
    :root { --accent-green: #10b981; --soft-gray: #f8fafc; --danger-red: #ef4444; --dark-slate: #1e293b; }
    .detail-wrapper { animation: fadeIn 0.6s ease-out; font-family: 'Plus Jakarta Sans', sans-serif; padding-bottom: 50px; }
    
    /* Üst Kahraman Alanı */
    .hero-section { background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); padding: 40px; border-radius: 35px; color: white; margin-bottom: 30px; }
    .plant-title { font-size: 3rem; font-weight: 800; margin: 0; }
    .scientific-name { display: inline-block; background: rgba(16, 185, 129, 0.2); color: #34d399; padding: 6px 15px; border-radius: 12px; font-style: italic; font-weight: 600; margin-top: 10px; }
    
    /* İstatistik Kartları */
    .smart-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; transform: translateY(-50px); padding: 0 30px; max-width: 1200px; margin: 0 auto; }
    .smart-card { background: white; padding: 25px; border-radius: 25px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); text-align: center; }

    /* Bilgi Blokları ve Tablolar */
    .panel-box { background: white; border-radius: 30px; padding: 30px; margin-bottom: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.03); max-width: 1200px; margin-left: auto; margin-right: auto; }
    
    .gl-table { width: 100%; border-collapse: separate; border-spacing: 0 8px; font-size: 0.95rem; }
    .gl-table th { padding: 12px; color: #64748b; font-size: 0.8rem; text-transform: uppercase; text-align: left; font-weight: 700; }
    .gl-table tbody tr { background: var(--soft-gray); transition: 0.2s; }
    .gl-table tbody tr:hover { background: #f1f5f9; }
    .gl-table td { padding: 15px 12px; border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; color: #334155; }
    .gl-table td:first-child { border-left: 1px solid #e2e8f0; border-radius: 12px 0 0 12px; }
    .gl-table td:last-child { border-right: 1px solid #e2e8f0; border-radius: 0 12px 12px 0; }
    
    .status-badge { padding: 4px 10px; border-radius: 8px; font-size: 0.75rem; font-weight: 700; color: white; display: inline-block; }
    .status-active { background: #10b981; }
    .status-passive { background: #64748b; }

    .geo-link { color: #2563eb; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; }
    .geo-link:hover { text-decoration: underline; }

    .btn-print-row { background: var(--dark-slate); color: white; border: none; padding: 6px 12px; border-radius: 8px; font-weight: 700; font-size: 0.8rem; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; gap: 4px; }
    .btn-print-row:hover { background: var(--accent-green); transform: translateY(-1px); }

    /* --- YAZDIRMA ETİKET TASARIMI (MİLLİMETRİK) --- */
    #printArea { display: none; }
    @media print {
        @page { size: auto; margin: 0; }
        body * { visibility: hidden; }
        #printArea, #printArea * { visibility: visible; }
        #printArea { display: block !important; position: absolute; left: 5mm; top: 5mm; width: 105mm; }
        .label-box { width: 105mm; border: 1.5pt solid #000; box-sizing: border-box; display: flex; flex-direction: column; background: #fff; }
        .l-header { height: 22mm; border-bottom: 2pt solid #000; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; }
        .l-header h1 { margin: 0; font-size: 20pt; font-weight: 900; text-decoration: underline; color: #000; }
        .l-header p { margin: 2pt 0 0 0; font-size: 11pt; font-weight: bold; color: #000; }
        .l-row { display: flex; border-bottom: 1.5pt solid #000; min-height: 15mm; }
        .l-tag { width: 25mm; background: #ececec !important; border-right: 1.5pt solid #000; font-size: 10pt; font-weight: 900; padding: 6pt; display: flex; align-items: center; color: #000; -webkit-print-color-adjust: exact; }
        .l-content { flex: 1; padding: 6pt 10pt; display: flex; align-items: center; font-size: 14pt; font-weight: bold; color: #000; }
        .sci-name { font-style: italic; font-size: 17pt; line-height: 1.1; }
        .l-footer { display: flex; flex: 1; }
        .l-f-info { flex: 1; display: flex; flex-direction: column; border-right: 1.5pt solid #000; }
        .l-f-qr { width: 38mm; display: flex; align-items: center; justify-content: center; padding: 2mm; }
        .sub-row { display: flex; flex: 1; border-bottom: 1.5pt solid #000; align-items: center; }
        .sub-row:last-child { border-bottom: none; }
        .kunye-container { display: flex; flex-direction: column; justify-content: center; padding-left: 8pt; line-height: 1.2; }
        .kunye-item { font-size: 9.5pt; font-weight: bold; color: #000; white-space: nowrap; }
    }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>

<div class="detail-wrapper">
    <div class="hero-section">
        <div style="max-width: 1200px; margin: 0 auto;">
            <h1 class="plant-title"><?= htmlspecialchars($takson['Bitki_Adi']) ?></h1>
            <div class="scientific-name"><?= htmlspecialchars($takson['Takson_Adi']) ?></div>
        </div>
    </div>

    <div class="smart-stats">
        <div class="smart-card">
            <div style="font-size: 2.2rem; font-weight: 800;"><?= $stok['toplam_adet'] ?></div>
            <div style="color: #94a3b8; font-weight: 700; font-size: 0.75rem;">TOPLAM DİKİM KAYDI</div>
        </div>
        <div class="smart-card" style="border-top: 5px solid var(--accent-green);">
            <div style="font-size: 2.2rem; font-weight: 800; color: var(--accent-green);"><?= $stok['aktif_adet'] ?></div>
            <div style="color: #94a3b8; font-weight: 700; font-size: 0.75rem;">AKTİF YAŞAYAN STOK</div>
        </div>
        <div class="smart-card" style="border-top: 5px solid var(--danger-red);">
            <div style="font-size: 2.2rem; font-weight: 800; color: var(--danger-red);"><?= $stok['pasif_adet'] ?></div>
            <div style="color: #94a3b8; font-weight: 700; font-size: 0.75rem;">HASAT EDİLEN / PASİF</div>
        </div>
    </div>

    <div class="panel-box">
        <h2 style="font-weight:800; margin-top:0; margin-bottom:20px; color: var(--dark-slate);">
            <i class="fas fa-leaf" style="color:var(--accent-green); margin-right: 8px;"></i> 
            Tüm Dikim Geçmişi ve Lokasyon Detayları
        </h2>
        
        <?php if(!empty($envanter_listesi)): ?>
            <div style="overflow-x: auto;">
                <table class="gl-table">
                    <thead>
                        <tr>
                            <th>ID / No</th>
                            <th>Sera / Konum Bilgisi</th>
                            <th>Eken Personel</th>
                            <th>Sorumlu Personel</th>
                            <th>Ekim Zamanı</th>
                            <th>Koordinatlar</th>
                            <th>Durum</th>
                            <th style="text-align: right;">Aksiyon</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($envanter_listesi as $kayit): ?>
                            <tr class="bitki-row"
                                data-id="<?= str_pad($kayit['Bitki_ID'], 4, "0", STR_PAD_LEFT) ?>"
                                data-nom="<?= htmlspecialchars($takson['Takson_Adi']) ?>"
                                data-sorumlu="<?= htmlspecialchars($kayit['Sorumlu_Personel'] ?? '-') ?>"
                                data-eken="<?= htmlspecialchars($kayit['Eken_Personel'] ?? '-') ?>"
                                data-konum="<?= htmlspecialchars(($kayit['Sera_Adi'] ?? 'Genel') . ' - ' . ($kayit['Parsel_Bilgisi'] ?? 'Parsel Yok')) ?>"
                                data-ekim="<?= date('d.m.Y', strtotime($kayit['Ekim_Tarihi'])) ?>">
                                
                                <td><strong>ARTH-<?= str_pad($kayit['Bitki_ID'], 4, "0", STR_PAD_LEFT) ?></strong></td>
                                <td>
                                    <strong><?= htmlspecialchars($kayit['Sera_Adi'] ?? '-') ?></strong>
                                    <br><small style="color:#64748b;"><?= htmlspecialchars($kayit['Parsel_Bilgisi'] ?? 'Genel Bölge') ?></small>
                                </td>
                                <td><?= htmlspecialchars($kayit['Eken_Personel'] ?? 'Belirtilmedi') ?></td>
                                <td><?= htmlspecialchars($kayit['Sorumlu_Personel'] ?? 'Belirtilmedi') ?></td>
                                <td><?= date('d.m.Y H:i', strtotime($kayit['Ekim_Tarihi'])) ?></td>
                                <td>
                                    <?php if(!empty($kayit['enlem']) && !empty($kayit['boylam'])): ?>
                                        <a href="http://maps.google.com/?q=<?= $kayit['enlem'] ?>,<?= $kayit['boylam'] ?>" target="_blank" class="geo-link">
                                            <i class="fas fa-map-marker-alt" style="color:#ef4444;"></i>
                                            <?= $kayit['enlem'] ?>, <?= $kayit['boylam'] ?>
                                        </a>
                                    <?php else: ?>
                                        <span style="color:#94a3b8; font-style: italic;">Girilmemiş</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($kayit['Aktiflik'] == 1): ?>
                                        <span class="status-badge status-active">Aktif Stok</span>
                                    <?php else: ?>
                                        <span class="status-badge status-passive">Pasif/Hasat</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right;">
                                    <button type="button" class="btn-print-row" onclick="satirEtiketiYazdir(this)">
                                        <i class="fas fa-print"></i> Etiket Yazdır
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p style="color:#94a3b8; text-align: center; padding: 20px;">Bu türe ait kaydedilmiş herhangi bir envanter geçmişi bulunmuyor.</p>
        <?php endif; ?>
    </div>
</div>

<div id="printArea"></div>

<script>
function satirEtiketiYazdir(btn) {
    const row = btn.closest('tr');
    const d = row.dataset;
    const printArea = document.getElementById('printArea');
    
    const basimTarihi = new Date().toLocaleDateString('tr-TR');
    
    // Şablonu dinamik olarak sadece bu satırın verileriyle dolduruyoruz
    printArea.innerHTML = `
        <div class="label-box">
            <div class="l-header">
                <h1>ARTVIN (ARTH)</h1>
                <p>Artvin Çoruh Üniversitesi</p>
            </div>
            <div class="l-row" style="min-height:22mm;">
                <div class="l-tag">Grup/Nom.</div>
                <div class="l-content"><div class="sci-name">${d.nom}</div></div>
            </div>
            <div class="l-row">
                <div class="l-tag">Sorumlu</div>
                <div class="l-content">${d.sorumlu}</div>
            </div>
            <div class="l-row">
                <div class="l-tag">Eken</div>
                <div class="l-content">${d.eken}</div>
            </div>
            <div class="l-footer">
                <div class="l-f-info">
                    <div class="sub-row">
                        <div class="l-tag">Konum</div>
                        <div class="l-content" style="font-size:9.5pt;">${d.konum}</div>
                    </div>
                    <div class="sub-row">
                        <div class="l-tag">Künye</div>
                        <div class="kunye-container">
                            <div class="kunye-item">Ekim T: ${d.ekim}</div>
                            <div class="kunye-item">Basım T: ${basimTarihi}</div>
                            <div class="kunye-item">No: ARTH-${d.id}</div>
                        </div>
                    </div>
                </div>
                <div class="l-f-qr" id="dynamic_qr"></div>
            </div>
        </div>
    `;

    // QR kodunu oluştur
    new QRCode(document.getElementById("dynamic_qr"), {
        text: "<?= $detay_link ?>",
        width: 110,
        height: 110,
        colorDark : "#000000",
        colorLight : "#ffffff",
        correctLevel : QRCode.CorrectLevel.H
    });

    // QR elementinin renderlanması için küçük bir bekleme verip yazdırıyoruz
    setTimeout(() => { 
        window.print(); 
    }, 400);
}
</script>