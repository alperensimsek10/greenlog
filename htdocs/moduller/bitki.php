<?php
/**
 * GreenLog - Modern Bitki Envanter Paneli (v5.1 - Akıllı Gruplanmış Özet Panel - Son Eken Kaldırıldı)
 */

try {
    // 1. Sayaç Verileri
    $toplam_kayit = $pdo->query("SELECT COUNT(*) FROM bitki")->fetchColumn();
    $aktif_bitki = $pdo->query("SELECT COUNT(*) FROM bitki WHERE Aktiflik = 1")->fetchColumn();
    $pasif_bitki = $pdo->query("SELECT COUNT(*) FROM bitki WHERE Aktiflik = 0")->fetchColumn();

    // 2. Grafik Verileri
    $grafik_sorgu = $pdo->query("
        SELECT s.Sera_Adi, COUNT(b.Bitki_ID) as Adet 
        FROM sera s 
        JOIN bitki b ON s.Sera_ID = b.Sera_ID 
        WHERE b.Aktiflik = 1
        GROUP BY s.Sera_ID
    ")->fetchAll(PDO::FETCH_ASSOC);

    $sera_adlari = json_encode(array_column($grafik_sorgu, 'Sera_Adi'));
    $sera_sayilari = json_encode(array_column($grafik_sorgu, 'Adet'));

    // 3. Akıllı Veritabanı Sorgusu (Yoğunluğu Engellemek İçin Takson_ID bazlı gruplandı)
    $bitkiler = $pdo->query("
        SELECT 
            t.Takson_ID, 
            t.Bitki_Adi as Takson_Tur_Adi, 
            t.Takson_Adi as Bilimsel_Ad,
            COUNT(b.Bitki_ID) as Toplam_Adet,
            GROUP_CONCAT(DISTINCT s.Sera_Adi SEPARATOR ', ') as Seralar,
            MAX(b.Ekim_Tarihi) as Son_Ekim_Tarihi,
            (SELECT p.Ad_Soyad FROM bitki b2 
             LEFT JOIN personel p ON b2.Eken_Pers_ID = p.Personel_ID 
             WHERE b2.Takson_ID = t.Takson_ID AND b2.Aktiflik = 1 
             ORDER BY b2.Ekim_Tarihi DESC LIMIT 1) as Son_Eken_Personel,
            (SELECT p.Ad_Soyad FROM bitki b3 
             LEFT JOIN personel p ON b3.Sorumlu_Pers_ID = p.Personel_ID 
             WHERE b3.Takson_ID = t.Takson_ID AND b3.Aktiflik = 1 
             ORDER BY b3.Ekim_Tarihi DESC LIMIT 1) as Son_Sorumlu_Personel
        FROM takson t
        JOIN bitki b ON t.Takson_ID = b.Takson_ID
        LEFT JOIN sera s ON b.Sera_ID = s.Sera_ID
        WHERE b.Aktiflik = 1
        GROUP BY t.Takson_ID
        ORDER BY t.Bitki_Adi ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "<div style='padding:50px; text-align:center; color:#ef4444;'>Hata: " . $e->getMessage() . "</div>";
    return;
}
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<style>
    /* --- WEB PANEL --- */
    :root { --accent-green: #10b981; --dark-slate: #1e293b; --soft-gray: #f8fafc; }
    .gl-container { font-family: 'Plus Jakarta Sans', sans-serif; padding: 15px; color: #0f172a; }
    
    .gl-stats-row { display: grid; grid-template-columns: 1fr 1.8fr; gap: 20px; margin-bottom: 25px; }
    .gl-card { background: white; border-radius: 24px; padding: 25px; border: 1px solid #e2e8f0; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05); position: relative; }
    
    .stat-main { text-align: center; }
    .stat-label { font-weight: 800; color: #64748b; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; }
    .stat-value { font-size: 4rem; font-weight: 800; color: var(--dark-slate); line-height: 1; margin: 10px 0; }
    .stat-badges { display: flex; gap: 10px; justify-content: center; margin-top: 15px; flex-wrap: wrap; }
    .badge-item { padding: 6px 12px; border-radius: 12px; font-size: 0.85rem; font-weight: 700; white-space: nowrap; }
    .b-aktif { background: #d1fae5; color: #065f46; }
    .b-pasif { background: #fee2e2; color: #991b1b; }

    .search-wrapper { position: relative; flex: 1; min-width: 250px; }
    .search-input { width: 100%; padding: 12px 20px; border-radius: 14px; border: 1px solid #e2e8f0; background: var(--soft-gray); font-weight: 600; outline: none; transition: 0.3s; box-sizing: border-box; }
    .search-input:focus { border-color: var(--accent-green); background: white; box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1); }

    .gl-table { width: 100%; border-collapse: separate; border-spacing: 0 10px; min-width: 600px; }
    .gl-table-responsive { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
    
    .gl-table th { padding: 12px; color: #64748b; font-size: 0.8rem; text-transform: uppercase; text-align: left; }
    .gl-table tbody tr { background: var(--soft-gray); transition: 0.2s; }
    .gl-table tbody tr:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.05); background: white; }
    .gl-table td { padding: 18px 12px; border-top: 1px solid #f1f5f9; border-bottom: 1px solid #f1f5f9; }
    .gl-table td:first-child { border-left: 1px solid #f1f5f9; border-radius: 15px 0 0 15px; }
    .gl-table td:last-child { border-right: 1px solid #f1f5f9; border-radius: 0 15px 15px 0; }
    
    .btn-group { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
    .btn-primary { background: var(--dark-slate); color: white; border: none; padding: 12px 22px; border-radius: 14px; cursor: pointer; font-weight: 700; transition: 0.3s; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-size: 0.9rem; white-space: nowrap; }
    .btn-primary:hover { transform: translateY(-2px); opacity: 0.9; }
    .btn-add { background: var(--accent-green); }

    .count-badge { background: #e2e8f0; color: #475569; padding: 4px 10px; border-radius: 8px; font-size: 0.8rem; font-weight: 700; margin-left: 8px; }

    /* --- MOBILE BREAKPOINTS --- */
    @media (max-width: 992px) {
        .gl-stats-row { grid-template-columns: 1fr; }
        .stat-value { font-size: 3rem; }
    }
    @media (max-width: 768px) {
        .gl-header-actions { flex-direction: column; align-items: flex-start !important; }
        .search-wrapper { max-width: 100%; width: 100%; }
        .btn-group { width: 100%; }
        .btn-group .btn-primary { flex: 1; justify-content: center; }
        .gl-card { padding: 15px; }
    }

    /* --- A4 YAZDIRMA --- */
    #printArea { display: none; }
    @media print {
        @page { size: A4; margin: 0; }
        body * { visibility: hidden; }
        #printArea, #printArea * { visibility: visible; }
        #printArea { display: grid !important; grid-template-columns: 1fr 1fr; grid-template-rows: repeat(3, 1fr); width: 210mm; height: 297mm; position: absolute; left: 0; top: 0; background: #fff; }
        .label-box { width: 105mm; height: 99mm; border: 0.5pt solid #000; box-sizing: border-box; display: flex; flex-direction: column; overflow: hidden; }
        .l-header { height: 22mm; border-bottom: 2pt solid #000; display: flex; align-items: center; justify-content: center; text-align: center; }
        .l-header h1 { margin: 0; font-size: 20pt; font-weight: 900; text-decoration: underline; }
        .l-header p { margin: 3pt 0 0 0; font-size: 11pt; font-weight: bold; }
        .l-row { display: flex; border-bottom: 1.5pt solid #000; min-height: 16mm; }
        .l-tag { width: 25mm; background: #ececec !important; border-right: 1.5pt solid #000; font-size: 10pt; font-weight: 900; padding: 6pt; display: flex; align-items: center; -webkit-print-color-adjust: exact; }
        .l-content { flex: 1; padding: 6pt 10pt; display: flex; align-items: center; font-size: 14pt; font-weight: bold; }
        .sci-name { font-style: italic; font-size: 18pt; line-height: 1.2; }
        .l-footer { display: flex; flex: 1; }
        .l-f-info { flex: 1; display: flex; flex-direction: column; border-right: 1.5pt solid #000; overflow: hidden; }
        .l-f-qr { width: 35mm; height: 100%; display: flex; align-items: center; justify-content: center; padding: 2mm; box-sizing: border-box; }
        .l-f-qr img { max-width: 100%; height: auto; }
        .sub-row { display: flex; flex: 1; border-bottom: 1.5pt solid #000; align-items: center; overflow: hidden; }
        .sub-row:last-child { border-bottom: none; }
        .kunye-container { display: flex; flex-direction: column; justify-content: center; padding-left: 8pt; line-height: 1.2; }
        .kunye-item { font-size: 9pt; font-weight: bold; white-space: nowrap; }
    }
</style>

<div class="gl-container">
    <div class="gl-stats-row">
        <div class="gl-card stat-main">
            <span class="stat-label">Toplam Envanter</span>
            <div class="stat-value"><?= $toplam_kayit ?></div>
            <div class="stat-badges">
                <span class="badge-item b-aktif">● <?= $aktif_bitki ?> Aktif</span>
                <span class="badge-item b-pasif">○ <?= $pasif_bitki ?> Pasif</span>
            </div>
        </div>

        <div class="gl-card">
            <div style="font-weight: 800; font-size: 0.8rem; color: #64748b; margin-bottom: 15px;">LOKASYON DAĞILIMI</div>
            <div style="height: 200px; position: relative;">
                <canvas id="seraChart"></canvas>
            </div>
        </div>
    </div>

    <div class="gl-card">
        <div class="gl-header-actions" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;">
            <div style="display: flex; align-items: center; gap: 20px; flex: 1; flex-wrap: wrap;">
                <h3 style="margin:0; font-weight: 800; font-size: 1.5rem; white-space: nowrap;">Tür Envanter Özeti</h3>
                <div class="search-wrapper">
                    <input type="text" id="bitkiSearch" class="search-input" placeholder="Bitki veya konum ara...">
                </div>
            </div>
            
            <div class="btn-group">
                <a href="panel.php?sayfa=yeni_bitki" class="btn-primary btn-add">Bitki Ekle</a>
                <button onclick="yazdirEtiketler()" class="btn-primary">Yazdır</button>
            </div>
        </div>

        <div class="gl-table-responsive">
            <table class="gl-table" id="bitkiTable">
                <thead>
                    <tr>
                        <th style="width: 40px;"><input type="checkbox" id="checkAll"></th>
                        <th>Bitki Türü / İsmi</th>
                        <th>Bulunduğu Seralar</th>
                        <th>Son İşlem Tarihi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($bitkiler as $b): ?>
                    <tr class="bitki-row" 
                        data-id="<?= $b['Takson_ID'] ?>"
                        data-nom="<?= htmlspecialchars($b['Bilimsel_Ad']) ?>"
                        data-sorumlu="<?= htmlspecialchars($b['Son_Sorumlu_Personel'] ?? '-') ?>"
                        data-eken="<?= htmlspecialchars($b['Son_Eken_Personel'] ?? '-') ?>"
                        data-konum="<?= htmlspecialchars($b['Seralar']) ?>"
                        data-ekim="<?= date('d.m.Y', strtotime($b['Son_Ekim_Tarihi'])) ?>">
                        
                        <td><input type="checkbox" class="bitki-check"></td>
                        <td class="search-target">
                            <a href="panel.php?sayfa=bitki_detay&takson_id=<?= $b['Takson_ID'] ?>" style="color: inherit; text-decoration: none;">
                                <div style="font-weight: 800; font-style: italic; display: inline-block;"><?= $b['Bilimsel_Ad'] ?></div>
                                <span class="count-badge"><?= $b['Toplam_Adet'] ?> Adet</span>
                                <br><small style="color:#64748b"><?= $b['Takson_Tur_Adi'] ?></small>
                            </a>
                        </td>
                        <td class="search-target" style="max-width: 250px; color:#334155;"><strong><?= $b['Seralar'] ?></strong></td>
                        <td><?= date('d.m.Y', strtotime($b['Son_Ekim_Tarihi'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="printArea"></div>

<script>
// Arama Fonksiyonu
document.getElementById('bitkiSearch').addEventListener('keyup', function() {
    let filter = this.value.toLocaleLowerCase('tr');
    let rows = document.querySelectorAll('#bitkiTable tbody tr');
    rows.forEach(row => {
        let text = row.innerText.toLocaleLowerCase('tr');
        row.style.display = text.includes(filter) ? '' : 'none';
    });
});

// Grafik Yapılandırması
const ctx = document.getElementById('seraChart').getContext('2d');
new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: <?= $sera_adlari ?>,
        datasets: [{
            data: <?= $sera_sayilari ?>,
            backgroundColor: ['#10b981', '#3b82f6', '#f59e0b', '#8b5cf6', '#ef4444'],
            borderWidth: 0,
            hoverOffset: 15
        }]
    },
    options: {
        maintainAspectRatio: false,
        cutout: '75%',
        plugins: { 
            legend: { 
                position: window.innerWidth < 768 ? 'bottom' : 'right',
                labels: { usePointStyle: true, font: { weight: '600', size: 11 } } 
            } 
        }
    }
});

document.getElementById('checkAll').onclick = function() {
    document.querySelectorAll('.bitki-check').forEach(c => c.checked = this.checked);
};

function yazdirEtiketler() {
    const selected = document.querySelectorAll('.bitki-check:checked');
    if(selected.length === 0) { alert('Lütfen bitki seçiniz!'); return; }

    const printArea = document.getElementById('printArea');
    printArea.innerHTML = '';
    const basimTarihi = new Date().toLocaleDateString('tr-TR');

    selected.forEach((chk, i) => {
        const d = chk.closest('tr').dataset;
        const qrId = "qr_" + i;

        const labelHtml = `
            <div class="label-box">
                <div class="l-header">
                    <div><h1>ARTVIN (ARTH)</h1><p>Artvin Çoruh Üniversitesi</p></div>
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
                            <div class="l-content" style="font-size:9pt;">${d.konum}</div>
                        </div>
                        <div class="sub-row">
                            <div class="l-tag">Künye</div>
                            <div class="kunye-container">
                                <div class="kunye-item">Ekim T: ${d.ekim}</div>
                                <div class="kunye-item">Basım T: ${basimTarihi}</div>
                                <div class="kunye-item">No: ARTH-${d.id.padStart(4, '0')}</div>
                            </div>
                        </div>
                    </div>
                    <div class="l-f-qr" id="${qrId}"></div>
                </div>
            </div>
        `;
        printArea.insertAdjacentHTML('beforeend', labelHtml);

        new QRCode(document.getElementById(qrId), {
            text: "https://greenlog.42web.io/panel.php?sayfa=bitki_detay&takson_id=" + d.id,
            width: 100,
            height: 100,
            correctLevel : QRCode.CorrectLevel.H
        });
    });

    setTimeout(() => { window.print(); }, 1200);
}
</script>