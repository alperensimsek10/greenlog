<?php
/**
 * GreenLog - Sera Yönetim Paneli (v5.0 - QR Kod Entegrasyonlu)
 */

// Güvenlik Kontrolü
if (!isset($_SESSION['Personel_ID'])) { exit("Erişim Engellendi"); }

require_once 'db-connect.php'; 

$mesaj = "";
$mesaj_tur = "";
$rol = $_SESSION['Rol'];

// --- 1. VERİ KAYIT İŞLEMİ ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sera_ekle'])) {
    $seraAdi = trim($_POST['sera_adi']);
    $aciklama = trim($_POST['aciklama']);
    $parsel   = trim($_POST['parsel_bilgisi']);
    $enlem    = !empty($_POST['enlem']) ? trim($_POST['enlem']) : null; 
    $boylam   = !empty($_POST['boylam']) ? trim($_POST['boylam']) : null;
    
    if (!empty($seraAdi) && !empty($parsel)) {
        try {
            $pdo->exec("SET NAMES 'utf8mb4'");
            $pdo->beginTransaction();

            $sql_sera = "INSERT INTO sera (Sera_Adi, Aciklama, Aktif_Mi) VALUES (:sera_adi, :aciklama, 1)";
            $stmt_sera = $pdo->prepare($sql_sera);
            $stmt_sera->execute([':sera_adi' => $seraAdi, ':aciklama' => $aciklama]);
            $yeniSeraID = $pdo->lastInsertId();

            $sql_lok = "INSERT INTO lokasyon (Sera_ID, Parsel_Bilgisi, Enlem, Boylam, Sira_No) 
                        VALUES (:sid, :parsel, :en, :boy, :sira)";
            $stmt_lok = $pdo->prepare($sql_lok);
            $stmt_lok->execute([
                ':sid'    => $yeniSeraID,
                ':parsel' => $parsel,
                ':en'      => $enlem,
                ':boy'    => $boylam,
                ':sira'   => 'Sıra-1'
            ]);
            
            $log_detay = "Yeni Sera Eklendi: " . $seraAdi;
            $log_sql = "INSERT INTO sistem_loglari (Personel_ID, Islem_Detay, Islem_Tarihi, Tablo_Adi) 
                        VALUES (:pid, :detay, :tarih, :tablo)";
            $pdo->prepare($log_sql)->execute([
                ':pid'   => $_SESSION['Personel_ID'],
                ':detay' => $log_detay,
                ':tarih' => date('Y-m-d H:i:s'),
                ':tablo' => 'sera'
            ]);

            $pdo->commit();
            $mesaj = "Sera ve konum bilgileri başarıyla kaydedildi.";
            $mesaj_tur = "success";
        } catch (Exception $e) {
            $pdo->rollBack();
            $mesaj = "Hata: " . $e->getMessage();
            $mesaj_tur = "error";
        }
    }
}

// --- 2. VERİ ÇEKME ---
$pdo->exec("SET NAMES 'utf8mb4'");
$sorgu = $pdo->query("SELECT * FROM sera ORDER BY Sera_Adi ASC");
$seralar = $sorgu->fetchAll(PDO::FETCH_ASSOC);

$toplam_sera = count($seralar);
$aktif_sera = 0;
$pasif_sera = 0;
foreach($seralar as $s) {
    if((int)$s['Aktif_Mi'] === 1) $aktif_sera++;
    else $pasif_sera++;
}
?>

<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />

<style>
    .sera-container { animation: fadeIn 0.5s ease; font-family: 'Plus Jakarta Sans', sans-serif; padding: 15px; box-sizing: border-box; }
    .page-header { margin-bottom: 30px; }
    .page-header h2 { font-weight: 800; color: #0f172a; margin: 0; font-size: 1.8rem; display: flex; align-items: center; gap: 12px; }
    
    /* Geliştirilmiş Grid Düzeni ve Mobil Uyumluluk */
    .grid-layout { 
        display: grid; 
        grid-template-columns: <?php echo ($rol === 'Admin') ? '380px 1fr' : '1fr'; ?>; 
        gap: 30px; 
    }

    @media (max-width: 992px) {
        .grid-layout { grid-template-columns: 1fr; }
    }

    .kpi-row { display: flex; gap: 20px; margin-bottom: 25px; flex-wrap: wrap; }
    .kpi-card { background: #fff; padding: 22px; border-radius: 16px; display: flex; align-items: center; gap: 18px; flex: 1; min-width: 220px; border: 1px solid #f1f5f9; box-shadow: 0 10px 30px rgba(15, 23, 42, 0.03); transition: transform 0.3s ease; }
    .kpi-card:hover { transform: translateY(-3px); }
    .kpi-card i { width: 52px; height: 52px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; }
    
    .icon-total { background: #eff6ff; color: #3b82f6; }
    .icon-active { background: #ecfdf5; color: #10b981; }
    .icon-passive { background: #fef2f2; color: #ef4444; }
    
    .kpi-info h4 { margin: 0; font-size: 1.8rem; font-weight: 800; color: #1e293b; }
    .kpi-info p { margin: 0; font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }

    .glass-box { background: #fff; border-radius: 16px; padding: 25px; border: 1px solid #e2e8f0; box-shadow: 0 10px 30px rgba(15, 23, 42, 0.04); height: fit-content; }
    
    /* Form İç Alan Düzenlemeleri (row-inputs eklendi) */
    .form-group { margin-bottom: 18px; }
    .form-group label { display: block; font-weight: 600; margin-bottom: 8px; color: #334155; font-size: 0.85rem; }
    .form-group input, .form-group textarea { width: 100%; padding: 11px 14px; border: 1px solid #cbd5e1; border-radius: 10px; font-size: 0.9rem; box-sizing: border-box; transition: all 0.3s ease; color: #334155; font-family: inherit; }
    .form-group input:focus, .form-group textarea:focus { border-color: #10b981; outline: none; box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15); }
    
    .row-inputs { display: flex; gap: 15px; }
    .row-inputs .form-group { flex: 1; min-width: 0; }
    
    .btn-submit { width: 100%; background: #10b981; color: white; padding: 14px; border: none; border-radius: 10px; font-weight: 700; cursor: pointer; transition: all 0.3s ease; margin-top: 10px; letter-spacing: 0.5px; }
    .btn-submit:hover { background: #059669; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(16, 185, 129, 0.3); }

    /* Liste Tasarımı */
    .list-item-wrapper { display: flex; align-items: center; background: #f8fafc; border-radius: 12px; margin-bottom: 12px; border: 1px solid #f1f5f9; transition: all 0.3s ease; padding-right: 15px; gap: 10px; }
    .list-item-wrapper:hover { border-color: #10b981; background: #fff; box-shadow: 0 8px 20px rgba(0,0,0,0.04); }

    .list-item { flex-grow: 1; display: flex; justify-content: space-between; align-items: center; padding: 16px; text-decoration: none; color: inherit; min-width: 0; }
    
    .status-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; margin-right: 10px; flex-shrink: 0; }
    .dot-active { background: #10b981; box-shadow: 0 0 8px rgba(16,185,129,0.5); }
    .dot-passive { background: #ef4444; box-shadow: 0 0 8px rgba(239,68,68,0.5); }
    
    .qr-btn { background: #fff; color: #475569; border: 1px solid #cbd5e1; width: 38px; height: 38px; border-radius: 8px; cursor: pointer; transition: all 0.2s ease; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .qr-btn:hover { background: #10b981; color: white; border-color: #10b981; transform: scale(1.05); }

    /* Modern Modal Tasarımı */
    #qrModal { display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.4); backdrop-filter: blur(8px); }
    .modal-content { background: white; margin: 12% auto; padding: 30px; border-radius: 20px; width: 320px; text-align: center; position: relative; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); border: 1px solid #f1f5f9; }
    .close-modal { position: absolute; right: 20px; top: 15px; font-size: 24px; cursor: pointer; color: #94a3b8; transition: color 0.2s; }
    .close-modal:hover { color: #475569; }
    #qrImage { width: 200px; height: 200px; margin: 20px 0; border: 1px solid #e2e8f0; padding: 10px; border-radius: 12px; background: #fff; }
    .btn-download { display: inline-flex; align-items: center; justify-content: center; gap: 8px; background: #2563eb; color: white; padding: 12px 20px; border-radius: 10px; text-decoration: none; font-weight: 700; font-size: 0.9rem; margin-top: 5px; width: 100%; box-sizing: border-box; transition: all 0.2s; }
    .btn-download:hover { background: #1d4ed8; }

    #toast { position: fixed; top: 25px; right: 25px; z-index: 9999; padding: 16px 28px; border-radius: 12px; color: white; font-weight: 600; box-shadow: 0 20px 40px rgba(0,0,0,0.15); display: flex; align-items: center; gap: 10px; }

    @keyframes fadeIn { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
</style>

<div class="sera-container">
    <div class="page-header">
        <h2><i class="fas fa-seedling" style="color:#10b981;"></i> Sera Yönetim Merkezi</h2>
    </div>

    <div id="qrModal">
        <div class="modal-content animate__animated animate__zoomIn animate__fast">
            <span class="close-modal" onclick="closeQR()">&times;</span>
            <h3 id="modalSeraAdi" style="margin:0; color:#0f172a; font-weight: 700;">Sera QR Kodu</h3>
            <img id="qrImage" src="" alt="QR Code">
            <br>
            <a id="qrDownloadLink" href="#" class="btn-download" onclick="downloadQR(event)">
                <i class="fas fa-download"></i> QR KODU İNDİR
            </a>
            <p style="font-size: 0.75rem; color: #64748b; margin-top: 15px; font-weight: 500;">QR kod okutulduğunda sistem otomatik olarak sera detayına yönlendirir.</p>
        </div>
    </div>

    <?php if ($mesaj): ?>
        <div id="toast" class="animate__animated animate__slideInRight" style="background: <?= $mesaj_tur == 'success' ? '#10b981' : '#ef4444' ?>;">
            <i class="fas <?= $mesaj_tur == 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle' ?>"></i> <?= htmlspecialchars($mesaj) ?>
        </div>
        <script>
            setTimeout(() => { 
                const toast = document.getElementById('toast');
                if(toast) {
                    toast.classList.replace('animate__slideInRight', 'animate__slideOutRight'); 
                    setTimeout(() => { toast.remove(); }, 500); 
                }
            }, 3000);
        </script>
    <?php endif; ?>

    <div class="kpi-row">
        <div class="kpi-card"><i class="fas fa-layer-group icon-total"></i><div class="kpi-info"><p>Toplam</p><h4><?= $toplam_sera ?></h4></div></div>
        <div class="kpi-card"><i class="fas fa-check-circle icon-active"></i><div class="kpi-info"><p>Aktif</p><h4><?= $aktif_sera ?></h4></div></div>
        <div class="kpi-card"><i class="fas fa-times-circle icon-passive"></i><div class="kpi-info"><p>Pasif</p><h4><?= $pasif_sera ?></h4></div></div>
    </div>

    <div class="grid-layout">
        <?php if ($rol === 'Admin'): ?>
        <div class="glass-box">
            <h4 style="margin-top:0; margin-bottom: 20px; color:#0f172a; font-weight: 700;"><i class="fas fa-plus-circle" style="color:#10b981"></i> Yeni Sera Kaydı</h4>
            <form method="POST">
                <div class="form-group"><label>Sera Adı</label><input type="text" name="sera_adi" required autocomplete="off"></div>
                
                <div class="row-inputs">
                    <div class="form-group"><label>Parsel Bilgisi</label><input type="text" name="parsel_bilgisi" required autocomplete="off"></div>
                    <div class="form-group"><label>Sıra No</label><input type="text" value="Sıra-1" readonly style="background:#f1f5f9; color: #64748b; border-color: #e2e8f0;"></div>
                </div>
                
                <div class="row-inputs">
                    <div class="form-group"><label>Enlem</label><input type="text" name="enlem" placeholder="Örn: 39.93"></div>
                    <div class="form-group"><label>Boylam</label><input type="text" name="boylam" placeholder="Örn: 32.85"></div>
                </div>
                
                <div class="form-group"><label>Açıklama</label><textarea name="aciklama" rows="3" placeholder="Sera hakkında kısa notlar..."></textarea></div>
                <button type="submit" name="sera_ekle" class="btn-submit">SERAYI SİSTEME EKLE</button>
            </form>
        </div>
        <?php endif; ?>

        <div class="glass-box">
            <h4 style="margin-top:0; margin-bottom: 20px; color:#0f172a; font-weight: 700;"><i class="fas fa-list-ul" style="color:#10b981"></i> Mevcut Seralar</h4>
            <div style="max-height: 620px; overflow-y: auto; padding-right: 5px;">
                <?php if(empty($seralar)): ?>
                    <p style="text-align: center; color: #94a3b8; padding: 20px;">Sistemde kayıtlı sera bulunamadı.</p>
                <?php else: ?>
                    <?php foreach($seralar as $sera): ?>
                    <div class="list-item-wrapper">
                        <a href="panel.php?sayfa=sera_detay&id=<?= urlencode($sera['Sera_ID']) ?>" class="list-item">
                            <div style="min-width: 0;">
                                <div style="display:flex; align-items:center;">
                                    <span class="status-dot <?= (int)$sera['Aktif_Mi'] === 1 ? 'dot-active' : 'dot-passive' ?>"></span>
                                    <strong style="font-size:1.05rem; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($sera['Sera_Adi']) ?></strong>
                                </div>
                                <div style="font-size:0.85rem; color:#64748b; margin-top:5px; padding-left:20px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    <?= htmlspecialchars($sera['Aciklama'] ?: 'Detay belirtilmemiş.') ?>
                                </div>
                            </div>
                            <div style="display:flex; align-items:center; gap:10px; flex-shrink: 0; margin-left: 15px;">
                                <span style="font-size: 0.75rem; color: #94a3b8; font-weight: 700; letter-spacing: 0.5px;">DETAYLAR</span>
                                <i class="fas fa-chevron-right" style="color:#10b981; font-size:0.85rem;"></i>
                            </div>
                        </a>
                        <button class="qr-btn" title="QR Kod Oluştur" 
                                data-id="<?= htmlspecialchars($sera['Sera_ID']) ?>" 
                                data-name="<?= htmlspecialchars($sera['Sera_Adi']) ?>"
                                onclick="openQR(this)">
                            <i class="fas fa-qrcode"></i>
                        </button>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
let currentQrUrl = "";

function openQR(button) {
    const id = button.dataset.id;
    const name = button.dataset.name;
    
    const baseUrl = "https://greenlog.42web.io/panel.php?sayfa=sera_detay&id=" + id;
    currentQrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${encodeURIComponent(baseUrl)}`;
    
    document.getElementById('modalSeraAdi').innerText = name;
    document.getElementById('qrImage').src = currentQrUrl;
    document.getElementById('qrModal').style.display = 'block';
}

function closeQR() {
    document.getElementById('qrModal').style.display = 'none';
}

// Güvenli ve Karşı Karşıya Kalınan CORS Engelini Aşan İndirme Fonksiyonu
async function downloadQR(event) {
    event.preventDefault();
    if (!currentQrUrl) return;

    try {
        const response = await fetch(currentQrUrl);
        const blob = await response.blob();
        const blobUrl = URL.createObjectURL(blob);
        
        const tempLink = document.createElement('a');
        tempLink.href = blobUrl;
        tempLink.download = `${document.getElementById('modalSeraAdi').innerText.replace(/\s+/g, '-').toLowerCase()}-qr.png`;
        document.body.appendChild(tempLink);
        tempLink.click();
        document.body.removeChild(tempLink);
        URL.revokeObjectURL(blobUrl);
    } catch (error) {
        // Yedek plan: İndirme başarısız olursa resmi yeni sekmede aç
        window.open(currentQrUrl, '_blank');
    }
}

window.onclick = function(event) {
    const modal = document.getElementById('qrModal');
    if (event.target === modal) closeQR();
}
</script>