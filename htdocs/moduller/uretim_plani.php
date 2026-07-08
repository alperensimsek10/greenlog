<?php
/**
 * GreenLog - Profesyonel Üretim Planı Modülü (Özel Tarih Aralıklı & Logolu PDF Destekli v6.3)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['Personel_ID'])) { exit("Erişim Engellendi"); }

$rol = $_SESSION['Rol'];
$kullanici_id = $_SESSION['Personel_ID'];

// Rol kontrolündeki boşluklar temizleniyor
$isAdmin = (trim(strtolower($rol)) === 'admin');

// --- 1. AJAX / ARKA PLAN PDF VERİ TALEBİ ---
if (isset($_GET['get_pdf_data'])) {
    if (ob_get_length()) ob_clean(); 
    
    header('Content-Type: application/json; charset=utf-8');
    
    $p = $_GET['periyot'] ?? 'aylik';
    $m = (int)($_GET['ay'] ?? date('m'));
    $y = date('Y');
    
    if ($p === 'gunluk') {
        $b_tar = date('Y-m-d');
        $e_tar = date('Y-m-d');
    } elseif ($p === 'yillik') {
        $b_tar = "$y-01-01";
        $e_tar = "$y-12-31";
    } elseif ($p === 'ozel') {
        $b_tar = $_GET['baslangic'] ?? date('Y-m-d');
        $e_tar = $_GET['bitis'] ?? date('Y-m-d');
    } else {
        $b_tar = "$y-" . str_pad($m, 2, "0", STR_PAD_LEFT) . "-01";
        $e_tar = date("Y-m-t", strtotime($b_tar));
    }
    
    try {
        $stmt = $pdo->prepare("SELECT u.*, p.Ad_Soyad FROM uretim_plani u LEFT JOIN personel p ON u.Personel_ID = p.Personel_ID WHERE u.Hedef_Tarih BETWEEN ? AND ? ORDER BY u.Hedef_Tarih ASC");
        $stmt->execute([$b_tar, $e_tar]);
        $sonuclar = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($sonuclar, JSON_UNESCAPED_UNICODE);
    } catch (PDOException $e) {
        echo json_encode([]);
    }
    exit;
}

// --- 2. DURUM GÜNCELLEME İŞLEMİ ---
if (isset($_POST['durum_guncelle']) || isset($_POST['yeni_durum'])) {
    $u_id = $_POST['u_id'];
    $yeni_durum = trim($_POST['yeni_durum']); 
    
    $check_auth = $pdo->prepare("SELECT Personel_ID, Durum, Takson_Adi FROM uretim_plani WHERE Uretim_ID = ?");
    $check_auth->execute([$u_id]);
    $is_bilgisi = $check_auth->fetch();

    if ($is_bilgisi) {
        $yetkili = true;
        
        if (!$isAdmin) {
            $yetkili = false;
            echo "<script>alert('Bu işlemi yapmaya yetkiniz yok! Sadece yöneticiler onay vermebilir.');</script>";
        }

        if ($yetkili) {
            $update = $pdo->prepare("UPDATE uretim_plani SET Durum = ? WHERE Uretim_ID = ?");
            $update->execute([$yeni_durum, $u_id]);
            
            // LOG KAYDI
            $plan_adi = $is_bilgisi['Takson_Adi'];
            $log_mesaj = "Üretim Planı Durumunu Güncelledi ($yeni_durum): $plan_adi";
            $log_sql = "INSERT INTO sistem_loglari (Personel_ID, Islem_Detay, Islem_Tarihi, Tablo_Adi) VALUES (?, ?, NOW(), 'uretim_plani')";
            $pdo->prepare($log_sql)->execute([$kullanici_id, $log_mesaj]);
        }
    }
    
    $ref_ay = $_GET['ay'] ?? date('m');
    echo "<script>window.location='?sayfa=uretim_plani&ay=".$ref_ay."';</script>";
    exit;
}

// --- 3. YENİ ÜRETİM PLANI / İSTEĞİ EKLE ---
if (isset($_POST['plan_kaydet'])) {
    $aksesyon = $_POST['aksesyon_no'];
    $takson = $_POST['takson_adi'];
    $miktar = $_POST['miktar'];
    $hedef = $_POST['hedef_tarih'];
    $notlar = $_POST['notlar'] ?? '';

    if ($isAdmin) {
        $p_id = $_POST['personel_id'];
        $durum = 'Onaylandı'; 
        $kime = $pdo->query("SELECT Ad_Soyad FROM personel WHERE Personel_ID = $p_id")->fetchColumn() ?: 'Bilinmiyor';
        $log_mesaj = "Yeni Üretim Planı Atadı ($kime): $takson ($miktar Adet)";
    } else {
        $p_id = $kullanici_id;
        $durum = 'Onay Bekliyor'; 
        $log_mesaj = "Yeni Üretim İsteği Gönderdi: $takson ($miktar Adet)";
    }

    $sql = "INSERT INTO uretim_plani (Aksesyon_No, Takson_Adi, Miktar, Personel_ID, Hedef_Tarih, Notlar, Durum) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $pdo->prepare($sql)->execute([$aksesyon, $takson, $miktar, $p_id, $hedef, $notlar, $durum]);
    
    $log_sql = "INSERT INTO sistem_loglari (Personel_ID, Islem_Detay, Islem_Tarihi, Tablo_Adi) VALUES (?, ?, NOW(), 'uretim_plani')";
    $pdo->prepare($log_sql)->execute([$kullanici_id, $log_mesaj]);
    
    $ay = date('m', strtotime($hedef));
    echo "<script>
            localStorage.setItem('goster_toast', '1');
            window.location.href = '?sayfa=uretim_plani&ay=$ay';
          </script>";
    exit;
}

// --- TOHUM EVİ VERİLERİ ---
$tohumlar = [];
try {
    $tohum_sql = "SELECT te.Aksesyon_No, t.Takson_ID, t.Takson_Adi, t.Bitki_Adi FROM tohum_evi te LEFT JOIN takson t ON te.Takson_ID = t.Takson_ID ORDER BY te.Aksesyon_No DESC";
    $tohumlar = $pdo->query($tohum_sql)->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {}

$secili_ay = isset($_GET['ay']) ? (int)$_GET['ay'] : (int)date('m');
$yil = date('Y');
$aylar = [1=>"Ocak", 2=>"Şubat", 3=>"Mart", 4=>"Nisan", 5=>"Mayıs", 6=>"Haziran", 7=>"Temmuz", 8=>"Ağustos", 9=>"Eylül", 10=>"Ekim", 11=>"Kasım", 12=>"Aralık"];
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<style>
    .u-container { background: #fff; border-radius: 20px; padding: 25px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); font-family: 'Inter', sans-serif; }
    .u-tabs { display: flex; position: relative; width: 100%; background: #f8fafc; border-radius: 12px; padding: 6px; box-shadow: inset 0 2px 4px rgba(0,0,0,0.02); border: 1px solid #e2e8f0; margin-bottom: 30px; box-sizing: border-box; z-index: 1; }
    .u-tab-link { flex: 1; text-align: center; padding: 12px 5px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 13px; color: #64748b; transition: all 0.2s ease; white-space: nowrap; cursor: pointer; }
    .u-tab-link:hover { color: #1e293b; background: #e2e8f0; }
    .u-tab-link.active { background: #10b981; color: white;}
    .u-tab-link.active:hover { background: #059669; color: white; }
    
    .u-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    .u-table th { text-align: left; padding: 15px; background: #f8fafc; color: #64748b; font-size: 13px; border-bottom: 2px solid #edf2f7; }
    .u-table td { padding: 15px; border-bottom: 1px solid #f1f5f9; font-size: 14px; color: #334155; }
    
    .u-badge { padding: 6px 12px; border-radius: 8px; font-size: 11px; font-weight: 800; border: none; display: inline-block; text-align: center; text-decoration: none; }
    .btn-badge-action { cursor: pointer; transition: 0.2s; outline: none; }
    .btn-badge-action:hover { opacity: 0.85; transform: scale(1.04); }
    
    .status-OnayBekliyor { background: #fef08a; color: #854d0e; }
    .status-Onaylandi { background: #10b981 !important; color: #ffffff !important; } 
    .status-Iptal { background: #ef4444 !important; color: #ffffff !important; } 
    
    .status-DevamEdiyor { background: #e0f2fe; color: #075985; }
    .status-Tamamlandi { background: #dcfce7; color: #166534; }
    
    .btn-action { background: #1e293b; color: white; padding: 10px 20px; border-radius: 10px; border: none; cursor: pointer; font-weight: 600; transition: 0.2s; display: inline-flex; align-items: center; gap: 8px;}
    .btn-action:hover { background: #0f172a; }

    .pdf-dropdown { position: relative; display: inline-block; }
    .btn-pdf { background: #1e293b; color: white; padding: 10px 15px; border-radius: 10px; border: none; cursor: pointer; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; }
    .btn-pdf:hover { background: #0f172a; }
    .dropdown-menu { display: none; position: absolute; right: 0; top: 110%; background-color: #ffffff; min-width: 210px; box-shadow: 0 10px 25px rgba(0,0,0,0.15); border-radius: 10px; z-index: 100; border: 1px solid #e2e8f0; overflow: hidden; padding: 5px 0; }
    .dropdown-menu a { color: #334155; padding: 10px 15px; text-decoration: none; display: block; font-size: 13px; font-weight: 600; transition: 0.2s; cursor: pointer; border-bottom: 1px solid #f1f5f9; }
    .dropdown-menu a:last-child { border-bottom: none; }
    .dropdown-menu a:hover { background-color: #f1f5f9; color: #1e293b; }
    .dropdown-menu i { margin-right: 8px; width: 16px; text-align: center; color: #64748b; }
    .dropdown-menu a:hover i { color: #1e293b; }
    .show { display: block !important; animation: slideDown 0.2s ease-out; }

    .admin-alert-box { background: #fffbeb; border: 1px solid #fde047; padding: 20px; border-radius: 15px; margin-bottom: 30px; }
    .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px); }
    .modal-content { background: #fff; width: 600px; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); overflow: hidden; animation: slideDown 0.3s ease-out; }
    .modal-header { padding: 20px 25px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; background: #f8fafc;}
    .modal-header h3 { margin: 0; color: #1e293b; font-size: 18px; }
    .close-btn { background: none; border: none; font-size: 24px; color: #94a3b8; cursor: pointer; transition: 0.2s; line-height: 1;}
    .close-btn:hover { color: #ef4444; }
    .modal-body { padding: 25px; max-height: 80vh; overflow-y: auto;}
    .u-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
    .u-input, .u-select { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; box-sizing: border-box; background: #fff;}
    .u-label { display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 5px; }
    .input-locked { background-color: #f1f5f9 !important; color: #64748b; cursor: not-allowed; font-weight: 600; }
    
    @keyframes slideDown { from { transform: translateY(-10px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
</style>

<div class="u-container">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:25px;">
        <div>
            <h2 style="margin:0; color:#1e293b;">Üretim Planı</h2>
            <small style="color:#94a3b8;"><?= $yil ?> Yılı Operasyonel Takvimi</small>
        </div>
        
        <div style="display:flex; gap:10px; align-items: center;">
            <div class="pdf-dropdown">
                <button type="button" onclick="togglePdfMenu(event)" class="btn-pdf">
                    <i class="fas fa-file-pdf"></i> PDF Rapor İndir <i class="fas fa-chevron-down" style="font-size:10px; margin-left:2px;"></i>
                </button>
                <div id="pdfDropdownMenu" class="dropdown-menu">
                    <a onclick="buildAndDownloadPDF('gunluk')"><i class="fas fa-calendar-day"></i> Günlük Planı İndir</a>
                    <a onclick="buildAndDownloadPDF('aylik')"><i class="fas fa-calendar-alt"></i> Bu Ayın Planını İndir</a>
                    <a onclick="buildAndDownloadPDF('yillik')"><i class="fas fa-calendar-check"></i> Bu Yılın Planını İndir</a>
                    <a onclick="promptOzelTarihSecici()" style="color: #0f172a;"><i class="fas fa-history" style="color: #1e293b;"></i> Özel Tarih Aralığı...</a>
                </div>
            </div>

            <button type="button" onclick="openAddModal()" class="btn-action">
                <i class="fas fa-plus-circle"></i> Yeni Plan Ekle
            </button>
        </div>
    </div>

    <?php if ($isAdmin): ?>
        <?php 
        $bekleyenler = $pdo->query("SELECT u.*, p.Ad_Soyad FROM uretim_plani u LEFT JOIN personel p ON u.Personel_ID = p.Personel_ID WHERE TRIM(u.Durum) = 'Onay Bekliyor' OR u.Durum IS NULL OR TRIM(u.Durum) = '' ORDER BY u.Uretim_ID DESC")->fetchAll(PDO::FETCH_ASSOC);
        if (count($bekleyenler) > 0):
        ?>
        <div class="admin-alert-box">
            <h4 style="margin: 0 0 15px 0; color: #b45309;"><i class="fas fa-exclamation-circle"></i> Onay Bekleyen Talepler</h4>
            <table class="u-table" style="background: transparent;">
                <thead>
                    <tr><th>Aksesyon No</th><th>Takson</th><th>Miktar</th><th>Talep Eden</th><th>İşlem</th></tr>
                </thead>
                <tbody>
                    <?php foreach($bekleyenler as $b): ?>
                    <tr>
                        <td style="color:#1e3a8a; font-weight:600; font-family:monospace;"><?= htmlspecialchars($b['Aksesyon_No'] ?: '-') ?></td>
                        <td><strong><?= htmlspecialchars($b['Takson_Adi']) ?></strong></td>
                        <td><?= $b['Miktar'] ?> Adet</td>
                        <td><?= $b['Ad_Soyad'] ?></td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="u_id" value="<?= $b['Uretim_ID'] ?>">
                                <input type="hidden" name="yeni_durum" value="Onaylandı">
                                <button type="submit" name="durum_guncelle" class="u-badge status-Onaylandi btn-badge-action">Onayla</button>
                            </form>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="u_id" value="<?= $b['Uretim_ID'] ?>">
                                <input type="hidden" name="yeni_durum" value="Iptal">
                                <button type="submit" name="durum_guncelle" class="u-badge status-Iptal btn-badge-action">Reddet</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="u-tabs" id="ayTabs">
        <?php foreach($aylar as $no => $ad): ?>
            <a href="?sayfa=uretim_plani&ay=<?= $no ?>" class="u-tab-link <?= ($secili_ay == $no) ? 'active' : '' ?>">
                <?= $ad ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div style="overflow-x: auto;">
        <table class="u-table">
            <thead>
                <tr><th>Aksesyon No</th><th>Takson Adı</th><th>Miktar</th><th>Sorumlu</th><th>Hedef Tarih</th><th>Durum</th></tr>
            </thead>
            <tbody>
                <?php
                $bas = "$yil-".str_pad($secili_ay, 2, "0", STR_PAD_LEFT)."-01";
                $bit = date("Y-m-t", strtotime($bas));
                
                if ($isAdmin) {
                    $sql_listele = "SELECT u.*, p.Ad_Soyad FROM uretim_plani u LEFT JOIN personel p ON u.Personel_ID = p.Personel_ID WHERE u.Hedef_Tarih BETWEEN ? AND ? AND TRIM(u.Durum) != 'Onay Bekliyor' AND u.Durum IS NOT NULL AND u.Durum != '' ORDER BY u.Hedef_Tarih ASC";
                } else {
                    $sql_listele = "SELECT u.*, p.Ad_Soyad FROM uretim_plani u LEFT JOIN personel p ON u.Personel_ID = p.Personel_ID WHERE u.Hedef_Tarih BETWEEN ? AND ? ORDER BY u.Hedef_Tarih ASC";
                }
                
                $stmt = $pdo->prepare($sql_listele);
                $stmt->execute([$bas, $bit]);

                $ekran_kayit_var = false;
                while($row = $stmt->fetch()): 
                    $ekran_kayit_var = true;
                    $cleanStatus = trim($row['Durum']);
                    
                    // Karakter temizleme
                    $stClass = str_replace([' ', 'ı', 'ğ', 'ü', 'ş', 'ö', 'ç'], ['', 'i', 'g', 'u', 's', 'o', 'c'], strtolower($cleanStatus));
                    $displayStatus = $cleanStatus ?: 'Onay Bekliyor';
                    if($cleanStatus == '') { $stClass = 'onaybekliyor'; }
                ?>
                    <tr>
                        <td style="color:#3b82f6; font-weight:700; font-family:monospace; font-size: 15px;"><?= htmlspecialchars($row['Aksesyon_No'] ?: '-') ?></td>
                        <td><strong><?= htmlspecialchars($row['Takson_Adi']) ?></strong></td>
                        <td><b><?= $row['Miktar'] ?> Adet</b></td>
                        <td><?= $row['Ad_Soyad'] ?: 'Atanmamış' ?></td>
                        <td><?= date('d.m.Y', strtotime($row['Hedef_Tarih'])) ?></td>
                        <td>
                            <?php if($stClass == 'onaybekliyor'): ?>
                                <span class="u-badge status-OnayBekliyor">Onay Bekliyor</span>
                            <?php elseif($stClass == 'onaylandi' || $stClass == 'onaylanmis'): ?>
                                <span class="u-badge status-Onaylandi">Onaylandı</span>
                            <?php elseif($stClass == 'iptal' || $stClass == 'reddedildi' || $stClass == 'red'): ?>
                                <span class="u-badge status-Iptal">Reddedildi</span>
                            <?php elseif($stClass == 'devamediyor'): ?>
                                <span class="u-badge status-DevamEdiyor">Devam Ediyor</span>
                            <?php elseif($stClass == 'tamamlandi'): ?>
                                <span class="u-badge status-Tamamlandi">Tamamlandı</span>
                            <?php else: ?>
                                <span class="u-badge status-OnayBekliyor"><?= htmlspecialchars($displayStatus) ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; if(!$ekran_kayit_var): ?>
                    <tr><td colspan="6" style="text-align:center; padding:30px; color:#94a3b8;">Bu aya ait kayıt bulunamadı.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="hidden-pdf-template" style="display: none; background: #ffffff; padding: 20px; font-family: 'Inter', sans-serif;">
    <div style="border-bottom: 3px solid #1e293b; padding-bottom: 15px; margin-bottom: 20px;">
        <table style="width: 100%;">
            <tr>
                <td style="vertical-align: middle;">
                    <img src="images/logo3.png" alt="GreenLog Logo" style="height: 55px; max-width: 250px; display: block;">
                </td>
                <td style="text-align: right; vertical-align: middle;">
                    <h3 id="pdf-dynamic-title" style="margin: 0; color: #1e293b; font-size: 20px;">Üretim Raporu</h3>
                    <small style="color: #94a3b8; font-size: 11px;">Oluşturulma Tarihi: <?= date('d.m.Y H:i') ?></small>
                </td>
            </tr>
        </table>
    </div>
    <table class="u-table" id="pdf-data-table">
        <thead>
            <tr><th>Aksesyon No</th><th>Takson Adı</th><th>Miktar</th><th>Sorumlu</th><th>Hedef Tarih</th><th>Durum</th></tr>
        </thead>
        <tbody id="pdf-data-body"></tbody>
    </table>
</div>

<div id="addModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-calendar-plus" style="color:#10b981;"></i> <?= $isAdmin ? 'Yeni Üretim Planı' : 'Yeni Üretim İsteği' ?></h3>
            <button class="close-btn" onclick="closeAddModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" onsubmit="return modalFormKontrol(event)" novalidate>
                <div class="u-grid">
                    <div>
                        <label class="u-label">Aksesyon No (Tohum Evi) *</label>
                        <select name="aksesyon_no" id="aksesyon_select" class="u-select" required onchange="taksonDoldur()">
                            <option value="">Tohum Seçin...</option>
                            <?php foreach($tohumlar as $t): ?>
                                <option value="<?= htmlspecialchars($t['Aksesyon_No']) ?>" data-takson="<?= htmlspecialchars($t['Takson_Adi'] . ($t['Bitki_Adi'] ? ' ('.$t['Bitki_Adi'].')' : '')) ?>">
                                    <?= htmlspecialchars($t['Aksesyon_No']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="u-label">Takson / Bitki Adı *</label>
                        <input type="text" name="takson_adi" id="takson_input" class="u-input input-locked" placeholder="Aksesyon seçince dolacak..." readonly required>
                    </div>
                    <div>
                        <label class="u-label">Üretim Miktarı (Hedef) *</label>
                        <input type="number" name="miktar" class="u-input" placeholder="Örn: 500" required min="1" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                    </div>
                    <?php if($isAdmin): ?>
                    <div>
                        <label class="u-label">Sorumlu Personel *</label>
                        <select name="personel_id" class="u-select" required>
                            <option value="">Personel Ata...</option>
                            <?php foreach($pdo->query("SELECT Personel_ID, Ad_Soyad FROM personel WHERE Aktif_Mi=1") as $p) echo "<option value='{$p['Personel_ID']}'>{$p['Ad_Soyad']}</option>"; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div style="<?= $isAdmin ? 'grid-column: span 2;' : 'grid-column: span 1;' ?>">
                        <label class="u-label">Hedef Tarih *</label>
                        <input type="date" name="hedef_tarih" id="modal_hedef_tarih" class="u-input" onkeydown="return false" style="cursor: pointer;" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div style="grid-column: 1 / -1;">
                        <label class="u-label">Özel Talimatlar / Notlar</label>
                        <input type="text" name="notlar" class="u-input" placeholder="Opsiyonel">
                    </div>
                </div>
                <button type="submit" name="plan_kaydet" class="btn-action" style="width:100%; margin-top:20px; background:#10b981; padding:12px; font-size:15px;">
                    <i class="fas fa-check-circle"></i> <?= $isAdmin ? 'Üretim Planı Oluştur' : 'Üretim İsteğini Onaya Gönder' ?>
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000
    });

    function togglePdfMenu(event) {
        event.stopPropagation();
        document.getElementById("pdfDropdownMenu").classList.toggle("show");
    }

    window.onclick = function(event) {
        if (!event.target.matches('.btn-pdf') && !event.target.matches('.btn-pdf *')) {
            var dropdowns = document.getElementsByClassName("dropdown-menu");
            for (var i = 0; i < dropdowns.length; i++) {
                if (dropdowns[i].classList.contains('show')) dropdowns[i].classList.remove('show');
            }
        }
    }

    function promptOzelTarihSecici() {
        const currentYear = new Date().getFullYear();
        const maxDateStr = currentYear + "-12-31";
        const todayStr = new Date().toISOString().split('T')[0];

        Swal.fire({
            title: 'Özel Tarih Aralığı Seçin',
            html: `
                <div class="swal-date-container" style="display: flex; flex-direction: column; gap: 12px; text-align: left; margin-top: 15px;">
                    <div style="display: flex; flex-direction: column; gap: 4px;">
                        <label style="font-size:13px; font-weight:700; color:#475569;">Başlangıç Tarihi</label>
                        <input type="date" id="swal-start-date" class="u-input" value="${currentYear}-01-01" min="2020-01-01" max="${maxDateStr}" onkeydown="return false" style="cursor: pointer;">
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 4px;">
                        <label style="font-size:13px; font-weight:700; color:#475569;">Bitiş Tarihi</label>
                        <input type="date" id="swal-end-date" class="u-input" value="${todayStr}" min="2020-01-01" max="${maxDateStr}" onkeydown="return false" style="cursor: pointer;">
                    </div>
                </div>
            `,
            focusConfirm: false,
            showCancelButton: true,
            confirmButtonText: 'Raporu Üret',
            cancelButtonText: 'İptal',
            confirmButtonColor: '#1e293b',
            preConfirm: () => {
                const baslangic = document.getElementById('swal-start-date').value;
                const bitis = document.getElementById('swal-end-date').value;
                if (!baslangic || !bitis) {
                    Swal.showValidationMessage('Lütfen her iki tarihi de doldurun!');
                    return false;
                }
                
                const sDate = new Date(baslangic);
                const eDate = new Date(bitis);
                
                if (isNaN(sDate.getTime()) || isNaN(eDate.getTime())) {
                    Swal.showValidationMessage('Geçersiz bir tarih seçildi!');
                    return false;
                }
                
                if (sDate.getFullYear() < 2020 || sDate.getFullYear() > currentYear || eDate.getFullYear() < 2020 || eDate.getFullYear() > currentYear) {
                    Swal.showValidationMessage(`Lütfen geçerli bir yıl seçiniz (2020-${currentYear})!`);
                    return false;
                }

                if (sDate > eDate) {
                    Swal.showValidationMessage('Başlangıç tarihi bitiş tarihinden büyük olamaz!');
                    return false;
                }
                return { baslangic: baslangic, bitis: bitis };
            }
        }).then((result) => {
            if (result.isConfirmed) {
                buildAndDownloadPDF('ozel', result.value.baslangic, result.value.bitis);
            }
        });
    }

    function buildAndDownloadPDF(periyot, ozelBas = '', ozelBit = '') {
        const seciliAy = '<?= $secili_ay ?>';
        const seciliAyAdi = '<?= $aylar[$secili_ay] ?>';
        const seciliYil = '<?= $yil ?>';
        
        let baslik = "";
        let ajaxData = { get_pdf_data: 1, periyot: periyot, ay: seciliAy };

        if(periyot === 'gunluk') {
            baslik = "Günlük Üretim Planı Raporu";
        } else if(periyot === 'yillik') {
            baslik = seciliYil + " Yıllık Üretim Planı Raporu";
        } else if(periyot === 'ozel') {
            let bPart = ozelBas.split('-');
            let ePart = ozelBit.split('-');
            baslik = `${bPart[2]}.${bPart[1]}.${bPart[0]} - ${ePart[2]}.${ePart[1]}.${ePart[0]} Arası Özel Üretim Raporu`;
            
            ajaxData.baslangic = ozelBas;
            ajaxData.bitis = ozelBit;
        } else {
            baslik = seciliAyAdi + " " + seciliYil + " Dönemi Aylık Üretim Planı Raporu";
        }

        Swal.fire({
            title: 'Rapor Hazırlanıyor...',
            text: 'Veritabanı kayıtları derleniyor.',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        const tamUrl = window.location.origin + window.location.pathname + window.location.search;

        $.ajax({
            url: tamUrl,
            type: 'GET',
            data: ajaxData,
            dataType: 'json',
            success: function(data) {
                document.getElementById('pdf-dynamic-title').innerText = baslik;
                const tbody = document.getElementById('pdf-data-body');
                tbody.innerHTML = "";

                if(!data || data.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:20px; color:#94a3b8;">Belirtilen tarih kriterlerinde herhangi bir üretim kaydı bulunamadı.</td></tr>`;
                } else {
                    data.forEach(item => {
                        let tarihParts = item.Hedef_Tarih.split('-');
                        let fTarih = tarihParts[2] + '.' + tarihParts[1] + '.' + tarihParts[0];
                        let sorumlu = item.Ad_Soyad ? item.Ad_Soyad : 'Atanmamış';
                        
                        let tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td style="color:#3b82f6; font-weight:700; font-family:monospace;">${item.Aksesyon_No || '-'}</td>
                            <td><strong>${item.Takson_Adi}</strong></td>
                            <td><b>${item.Miktar} Adet</b></td>
                            <td>${sorumlu}</td>
                            <td>${fTarih}</td>
                            <td><span style="font-weight:bold; color:#1e293b;">${item.Durum || 'Onay Bekliyor'}</span></td>
                        `;
                        tbody.appendChild(tr);
                    });
                }

                const container = document.getElementById('hidden-pdf-template');
                container.style.display = "block";

                const opt = {
                    margin:       [15, 15, 15, 15],
                    filename:     'GreenLog_' + periyot + '_uretim_raporu.pdf',
                    image:        { type: 'jpeg', quality: 0.98 },
                    html2canvas:  { scale: 2, useCORS: true },
                    jsPDF:        { unit: 'mm', format: 'a4', orientation: 'landscape' }
                };

                html2pdf().set(opt).from(container).save().then(() => {
                    container.style.display = "none";
                    Swal.close();
                    Toast.fire({ icon: 'success', title: 'Rapor İndirildi!', iconColor: '#10b981' });
                });
            },
            error: function(xhr) {
                Swal.close();
                console.error("Gelen Hatalı Ham Veri:", xhr.responseText);
                Swal.fire('Hata!', 'Veri çözümlenemedi. Tarayıcı konsolunu (F12) inceleyin.', 'error');
            }
        });
    }

    function openAddModal() { document.getElementById('addModal').style.display = 'flex'; }
    function closeAddModal() { document.getElementById('addModal').style.display = 'none'; }
    
    function modalFormKontrol(event) {
        const form = event.target;
        const inputs = form.querySelectorAll('input[required], select[required]');
        let hataVar = false;

        for (let input of inputs) {
            let val = input.tagName === 'SELECT' ? input.value : input.value.trim();
            if (input.name === 'miktar' && parseInt(val) < 1) {
                Toast.fire({ icon: 'error', title: 'Hata!', text: 'Miktar 1\'den küçük olamaz!', iconColor: '#ef4444' });
                input.focus(); hataVar = true; break;
            }
            if (!val) {
                let label = input.previousElementSibling ? input.previousElementSibling.innerText : "İlgili alan";
                Toast.fire({ icon: 'error', title: 'Hata!', text: `${label.replace('*', '').trim()} boş bırakılamaz.`, iconColor: '#ef4444' });
                input.focus(); hataVar = true; break;
            }
        }
        if (hataVar) { event.preventDefault(); return false; }
        return true; 
    }

    function taksonDoldur() {
        var select = document.getElementById("aksesyon_select");
        var input = document.getElementById("takson_input");
        input.value = select.options[select.selectedIndex].getAttribute("data-takson") || "";
    }
    
    $(document).ready(function () {
        if(localStorage.getItem('goster_toast') === '1') {
            Toast.fire({ icon: 'success', title: 'Başarılı!', text: 'İşlem başarıyla tamamlandı.', iconColor: '#10b981' });
            localStorage.removeItem('goster_toast');
        }

        const bugun = new Date();
        const currentYear = bugun.getFullYear();
        
        // Minimum sınır: 30 gün öncesi
        const birAyOnce = new Date();
        birAyOnce.setDate(bugun.getDate() - 30); 
        const formatliMinTarih = birAyOnce.toISOString().split('T')[0];

        // Maksimum sınır: İçinde bulunulan yılın son günü (31 Aralık)
        const formatliMaxTarih = currentYear + "-12-31";

        const tarihInput = document.getElementById('modal_hedef_tarih');
        if(tarihInput) {
            tarihInput.setAttribute('min', formatliMinTarih);
            tarihInput.setAttribute('max', formatliMaxTarih);
        }
    });
</script>