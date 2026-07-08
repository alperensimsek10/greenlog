<?php
/**
 * GreenLog - İş & Ekipman Yönetimi (v29.3 - Admin Grafik Üst Bölüm Entegrasyonu)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/PHPMailer/src/Exception.php';
require 'PHPMailer/PHPMailer/src/PHPMailer.php';
require 'PHPMailer/PHPMailer/src/SMTP.php';

if (!isset($_SESSION['Personel_ID'])) { exit("Erişim Engellendi"); }

$rol = $_SESSION['Rol'];
$kullanici_id = $_SESSION['Personel_ID'];
$bugun = date('Y-m-d');

// --- MAİL FONKSİYONU ---
function greenLogMailGonder($alici_mail, $alici_ad, $konu, $icerik) {
    $mail = new PHPMailer(true);
    try {
        $mail->CharSet = 'UTF-8';
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'greenlog5255@gmail.com';
        $mail->Password = 'crvn kobr yzfj lvay';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->setFrom('greenlog5255@gmail.com', 'GreenLog');
        $mail->addAddress($alici_mail, $alici_ad);
        $mail->isHTML(true);
        $mail->Subject = $konu;
        $mail->Body = "<div style='font-family:sans-serif; border:1px solid #e2e8f0; padding:25px; border-radius:15px; max-width:600px; margin:auto;'>
                <div style='text-align:center; margin-bottom:20px;'><h2 style='color:#3b82f6; margin:0;'>GreenLog Bildirim</h2></div>
                $icerik
                <hr style='border:0; border-top:1px solid #eee; margin:20px 0;'>
                <p style='font-size:12px; color:#94a3b8; text-align:center;'>Bu bir sistem mesajıdır.</p>
            </div>";
        $mail->send();
        return true;
    } catch (Exception $e) { return false; }
}

// --- 0. OTOMATİK: TARİHİ GEÇEN GÖREVLERE UYARI MAİLİ ---
$gecmis_gorevler = $pdo->query("
    SELECT g.Gorev_ID, g.Baslik, g.Son_Tarih, g.Personel_ID, p.Email, p.Ad_Soyad 
    FROM gorevler g 
    JOIN personel p ON g.Personel_ID = p.Personel_ID
    WHERE g.Durum != 'Tamamlandi' 
      AND g.Son_Tarih < CURDATE() 
      AND g.Gecikme_Mail_Gonderildi = 0
")->fetchAll();

foreach ($gecmis_gorevler as $gv) {
    $gosterim_tarih = date('d.m.Y', strtotime($gv['Son_Tarih']));
    $msg = "
        <h3 style='color:#ef4444;'>⚠️ Gecikmiş Görev Uyarısı</h3>
        <p>Merhaba <b>{$gv['Ad_Soyad']}</b>,</p>
        <p>Aşağıdaki görevin son teslim tarihi geçmiş ve henüz tamamlanmamıştır:</p>
        <div style='background:#fef2f2; border-left:4px solid #ef4444; padding:15px; border-radius:8px; margin:15px 0;'>
            <b style='font-size:16px;'>{$gv['Baslik']}</b><br>
            <span style='color:#ef4444;'>Son Tarih: $gosterim_tarih</span>
        </div>
        <p>Lütfen görevi en kısa sürede tamamlayın.</p>
    ";
    $mailOk = greenLogMailGonder($gv['Email'], $gv['Ad_Soyad'], '⚠️ Gecikmiş Görev: ' . $gv['Baslik'], $msg);
    if ($mailOk) {
        $pdo->prepare("UPDATE gorevler SET Gecikme_Mail_Gonderildi = 1 WHERE Gorev_ID = ?")
            ->execute([$gv['Gorev_ID']]);
        $pdo->prepare("INSERT INTO sistem_loglari (Personel_ID, Islem_Detay, Islem_Tarihi) VALUES (?, ?, NOW())")
            ->execute([$gv['Personel_ID'], "Gecikme Uyarı Maili Gönderildi: {$gv['Baslik']}"]);
    }
}

// --- 1. ADMIN: GÖREV SİLME ---
if ($rol === 'Admin' && isset($_GET['sil_id'])) {
    $silinen_baslik = $pdo->query("SELECT Baslik FROM gorevler WHERE Gorev_ID = " . intval($_GET['sil_id']))->fetchColumn();
    $pdo->prepare("DELETE FROM gorevler WHERE Gorev_ID = ?")->execute([$_GET['sil_id']]);
    if ($silinen_baslik) {
        $pdo->prepare("INSERT INTO sistem_loglari (Personel_ID, Islem_Detay, Islem_Tarihi) VALUES (?, ?, NOW())")->execute([$kullanici_id, "Görev Sildi: $silinen_baslik"]);
    }
    echo "<script>window.location='panel.php?sayfa=is_takip';</script>";
}

// --- 2. ADMIN: GÖREV ATA ---
if ($rol === 'Admin' && isset($_POST['gorev_ata'])) {
    $p_id = $_POST['personel_id'];
    $bas = trim($_POST['baslik']);
    $tarih = $_POST['son_tarih'];
    $pdo->prepare("INSERT INTO gorevler (Personel_ID, Baslik, Son_Tarih, Durum, Uyari_Gonderildi, Gecikme_Mail_Gonderildi) VALUES (?, ?, ?, 'Bekliyor', 0, 0)")->execute([$p_id, $bas, $tarih]);

    $p_data = $pdo->query("SELECT Email, Ad_Soyad FROM personel WHERE Personel_ID = $p_id")->fetch();

    $kime = $p_data['Ad_Soyad'] ?? 'Bilinmeyen Personel';
    $pdo->prepare("INSERT INTO sistem_loglari (Personel_ID, Islem_Detay, Islem_Tarihi) VALUES (?, ?, NOW())")->execute([$kullanici_id, "Yeni Görev Atadı ($kime): $bas"]);

    if ($p_data) {
        $gosterim_tarih = date('d.m.Y', strtotime($tarih));
        $msg = "<h3>Yeni Görev!</h3><p>Merhaba {$p_data['Ad_Soyad']}, size yeni iş atandı:<br><b>$bas</b><br>Son Tarih: $gosterim_tarih</p>";
        greenLogMailGonder($p_data['Email'], $p_data['Ad_Soyad'], 'Yeni Görev', $msg);
    }
    echo "<script>window.location='panel.php?sayfa=is_takip&islem=atandi';</script>";
}

// --- 3. PERSONEL: İŞE BAŞLA ---
if (isset($_POST['is_baslat'])) {
    $g_id = $_POST['gorev_id'];
    $ekipmanlar = $_POST['ekipman_id'] ?? [];

    $pdo->prepare("UPDATE gorevler SET Durum = 'Devam Ediyor' WHERE Gorev_ID = ?")->execute([$g_id]);

    if (!empty($ekipmanlar)) {
        foreach ($ekipmanlar as $e_id) {
            $pdo->prepare("INSERT INTO gorev_ekipmanlari (Gorev_ID, Ekipman_ID) VALUES (?, ?)")->execute([$g_id, $e_id]);
           $pdo->prepare("INSERT INTO ekipman_log (Gorev_ID, Ekipman_ID, Personel_ID, Alis_Tarihi) VALUES (?, ?, ?, NOW())")->execute([$g_id, $e_id, $kullanici_id]);
        }
    }

    $baslik = $pdo->query("SELECT Baslik FROM gorevler WHERE Gorev_ID = $g_id")->fetchColumn();
    $pdo->prepare("INSERT INTO sistem_loglari (Personel_ID, Islem_Detay, Islem_Tarihi) VALUES (?, ?, NOW())")->execute([$kullanici_id, "Göreve Başladı: $baslik"]);

    echo "<script>window.location='panel.php?sayfa=is_takip&islem=basladi';</script>";
}

// --- 4. PERSONEL: EKİPMAN DÜZENLE ---
if (isset($_POST['ekipman_guncelle'])) {
    $g_id = $_POST['gorev_id'];
    $yeni_ekipmanlar = $_POST['yeni_ekipman_id'] ?? [];

    $pdo->prepare("DELETE FROM gorev_ekipmanlari WHERE Gorev_ID = ?")->execute([$g_id]);
    $pdo->prepare("DELETE FROM ekipman_log WHERE Personel_ID = ? AND Gorev_ID = ? AND Teslim_Tarihi IS NULL")->execute([$kullanici_id, $g_id]);

    if (!empty($yeni_ekipmanlar)) {
        foreach ($yeni_ekipmanlar as $e_id) {
            $pdo->prepare("INSERT INTO gorev_ekipmanlari (Gorev_ID, Ekipman_ID) VALUES (?, ?)")->execute([$g_id, $e_id]);
            $pdo->prepare("INSERT INTO ekipman_log WHERE Personel_ID = ? AND Gorev_ID = ? AND Teslim_Tarihi IS NULL")->execute([$kullanici_id, $g_id]);
        }
    }

    $baslik = $pdo->query("SELECT Baslik FROM gorevler WHERE Gorev_ID = $g_id")->fetchColumn();
    $pdo->prepare("INSERT INTO sistem_loglari (Personel_ID, Islem_Detay, Islem_Tarihi) VALUES (?, ?, NOW())")->execute([$kullanici_id, "Ekipman Güncelledi: $baslik"]);

    echo "<script>window.location='panel.php?sayfa=is_takip&islem=guncellendi';</script>";
}

// --- 5. PERSONEL: İŞI BİTİR ---
if (isset($_POST['is_bitir'])) {
    $g_id = $_POST['gorev_id'];
    $rapor = trim($_POST['rapor']);

    try {
        $pdo->beginTransaction();
        
        $pdo->prepare("UPDATE gorevler SET Durum = 'Tamamlandi', Gun_Sonu_Raporu = ? WHERE Gorev_ID = ?")->execute([$rapor, $g_id]);
        $pdo->prepare("UPDATE ekipman_log SET Teslim_Tarihi = NOW() WHERE Personel_ID = ? AND Gorev_ID = ? AND Teslim_Tarihi IS NULL")->execute([$kullanici_id, $g_id]);

        $baslik = $pdo->query("SELECT Baslik FROM gorevler WHERE Gorev_ID = $g_id")->fetchColumn();
        $pdo->prepare("INSERT INTO sistem_loglari (Personel_ID, Islem_Detay, Islem_Tarihi) VALUES (?, ?, NOW())")->execute([$kullanici_id, "Görev Tamamlandı: $baslik"]);

        $pdo->commit();
        echo "<script>window.location='panel.php?sayfa=is_takip&islem=bitti';</script>";
    } catch (Exception $e) { $pdo->rollBack(); }
}

$admin_sql = "SELECT g.*, p.Ad_Soyad, 
    (SELECT GROUP_CONCAT(e.Ekipman_Adi SEPARATOR ', ') FROM gorev_ekipmanlari ge JOIN ekipman e ON ge.Ekipman_ID = e.Ekipman_ID WHERE ge.Gorev_ID = g.Gorev_ID) as Ekipmanlar,
    g.Gun_Sonu_Raporu as Rapor
    FROM gorevler g JOIN personel p ON g.Personel_ID=p.Personel_ID";

// --- ADMIN: GRAFİK VERİLERİNİN HAZIRLANMASI ---
if ($rol === 'Admin') {
    // 1. Personel Bazlı Toplam Görev Dağılımı (Hangi personel ne kadar iş yapmış)
    $grafik_personel_is = $pdo->query("
        SELECT p.Ad_Soyad, COUNT(g.Gorev_ID) as Toplam
        FROM personel p
        LEFT JOIN gorevler g ON p.Personel_ID = g.Personel_ID
        WHERE p.Rol = 'Personel' AND p.Aktif_Mi = 1
        GROUP BY p.Personel_ID
        ORDER BY Toplam DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // 2. Performans Analizi (Genel Durum Dağılımı)
    $istatistik_durumlar = $pdo->query("
        SELECT 
            SUM(CASE WHEN Durum = 'Tamamlandi' THEN 1 ELSE 0 END) as Biten,
            SUM(CASE WHEN Durum != 'Tamamlandi' AND Son_Tarih < '$bugun' THEN 1 ELSE 0 END) as Geciken,
            SUM(CASE WHEN Durum = 'Devam Ediyor' AND Son_Tarih >= '$bugun' THEN 1 ELSE 0 END) as DevamEden,
            SUM(CASE WHEN Durum = 'Bekliyor' AND Son_Tarih >= '$bugun' THEN 1 ELSE 0 END) as Bekleyen
        FROM gorevler
    ")->fetch(PDO::FETCH_ASSOC);
}
?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales/tr.js'></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- GRAFİKLER İÇİN CHART.JS ENTEGRASYONU -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    .main-grid {
        --p: #3b82f6; --s: #10b981; --d: #ef4444; --w: #f59e0b;
        --card-bg: #ffffff; --text-main: #1e293b; --border: #e2e8f0;

        display: grid;
        grid-template-columns: minmax(0, 1fr) 420px;
        gap: 30px;
        padding: 25px;
        max-width: 1600px;
        margin: 0 auto;
        align-items: start;
    }

    .main-grid .left-panel  { display: flex; flex-direction: column; gap: 25px; min-width: 0; }
    .main-grid .right-panel { display: flex; flex-direction: column; gap: 18px; min-width: 0; }

    .main-grid .card { background: var(--card-bg); border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.03); padding: 20px; border: 1px solid var(--border); display: flex; flex-direction: column; }

    .main-grid .card-title { font-size: 1.25rem; font-weight: 700; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; color: var(--text-main); }

    /* ADMIN GRAFİK GRID TASARIMI (TAKVİM ÜSTÜ İÇİN DURUMU ENTEGRE EDİLDİ) */
    .admin-dashboard-charts {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
        margin-bottom: 5px; /* Takvimle arasındaki mesafe */
    }
    .chart-container {
        position: relative;
        margin: auto;
        height: 240px;
        width: 100%;
    }

    /* LİSTELER */
    .main-grid .inbox-list         { overflow-y: auto; padding-right: 8px; }
    .main-grid .admin-list-aktif   { max-height: 400px; }
    .main-grid .admin-list-biten   { max-height: 450px; }
    .main-grid .personel-list-aktif { max-height: 500px; }
    .main-grid .personel-list-biten { max-height: 400px; }

    .main-grid .inbox-list::-webkit-scrollbar       { width: 6px; }
    .main-grid .inbox-list::-webkit-scrollbar-track  { background: #f1f5f9; border-radius: 10px; }
    .main-grid .inbox-list::-webkit-scrollbar-thumb  { background: #cbd5e1; border-radius: 10px; }
    .main-grid .inbox-list::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

    /* KUTU TEMEL AYARI VE RENKLER */
    .main-grid .inbox-item {
        background: #fff;
        border: 1px solid var(--border);
        border-left: 5px solid var(--border);
        border-radius: 12px;
        padding: 15px;
        margin-bottom: 12px;
        margin-top: 5px;
        cursor: pointer;
        transition: all 0.2s ease;
        position: relative;
    }

    .main-grid .inbox-item.s-bekliyor { border-left-color: var(--w); }
    .main-grid .inbox-item.s-devam    { border-left-color: var(--p); }
    .main-grid .inbox-item.s-bitti    { border-left-color: var(--s); opacity: 0.7; }

    /* GECİKMİŞ GÖREV - KIRMIZI TEMA */
    .main-grid .inbox-item.s-gecikti {
        border-left-color: var(--d);
        background: #fff5f5;
    }
    .main-grid .inbox-item.s-gecikti:hover,
    .main-grid .inbox-item.s-gecikti.active-select {
        border-color: var(--d);
    }
    .main-grid .b-gecikti {
        background: #fee2e2;
        color: #b91c1c;
        animation: pulseRed 1.5s infinite;
    }
    @keyframes pulseRed {
        0%, 100% { opacity: 1; }
        50%       { opacity: 0.6; }
    }
    .main-grid .action-box.a-gecikti {
        border-top: 5px solid var(--d);
        box-shadow: 0 10px 25px rgba(239,68,68,0.12);
        background: #fffafa;
    }

    .main-grid .inbox-item:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.05); }

    .main-grid .inbox-item.s-bekliyor:hover, .main-grid .inbox-item.s-bekliyor.active-select { border-color: var(--w); }
    .main-grid .inbox-item.s-devam:hover,    .main-grid .inbox-item.s-devam.active-select    { border-color: var(--p); }
    .main-grid .inbox-item.s-bitti:hover,    .main-grid .inbox-item.s-bitti.active-select    { border-color: var(--s); }
    .main-grid .inbox-item.active-select { background: #f8fafc; }

    .main-grid .inbox-title { font-weight: 700; font-size: 14px; color: var(--text-main); margin-bottom: 5px; display: block; }
    .main-grid .inbox-meta  { display: flex; justify-content: space-between; align-items: center; font-size: 12px; color: #64748b; }
    .main-grid .badge { padding: 4px 10px; border-radius: 8px; font-weight: 600; font-size: 11px; }
    .main-grid .b-bekliyor { background: #fef3c7; color: #b45309; }
    .main-grid .b-devam    { background: #dbeafe; color: #1d4ed8; }
    .main-grid .b-bitti    { background: #d1fae5; color: #047857; }

    .main-grid #islem-merkezi, .main-grid #admin-islem-merkezi { display: none; animation: fadeIn 0.4s ease; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

    /* DİNAMİK ACTION-BOX */
    .main-grid .action-box { background: #fff; border: 1px solid #cbd5e1; border-radius: 16px; padding: 25px; word-wrap: break-word; overflow-wrap: anywhere; word-break: break-word; }
    .main-grid .action-box.a-bekliyor { border-top: 5px solid var(--w); box-shadow: 0 10px 25px rgba(245, 158, 11, 0.08); }
    .main-grid .action-box.a-devam    { border-top: 5px solid var(--p); box-shadow: 0 10px 25px rgba(59, 130, 246, 0.08); }
    .main-grid .action-box.a-bitti    { border-top: 5px solid var(--s); box-shadow: 0 10px 25px rgba(16, 185, 129, 0.08); }
    .main-grid .action-header  { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid var(--border); }
    .main-grid .action-title   { font-size: 1.2rem; font-weight: 700; color: var(--text-main); margin: 0; }

    .main-grid .a-bekliyor .ms-btn:hover   { border-color: var(--w); }
    .main-grid .a-bekliyor .ms-item:hover  { color: var(--w); }
    .main-grid .a-bekliyor .ms-item input[type="checkbox"] { accent-color: var(--w); }

    .main-grid .equip-area   { background: #f8fafc; border-radius: 12px; padding: 20px; border: 1px dashed #cbd5e1; margin-bottom: 20px; }
    .main-grid .equip-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
    .main-grid .equip-label  { font-weight: 700; font-size: 13px; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; }

    .main-grid .btn-main { width: 100%; padding: 14px; border-radius: 12px; border: none; font-weight: 600; font-size: 15px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: 0.2s; margin-top: 15px; }
    .main-grid .btn-main:hover { transform: translateY(-1px); filter: brightness(1.05); }
    .main-grid .btn-blue    { background: var(--p); color: white; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3); }
    .main-grid .btn-green   { background: var(--s); color: white; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3); }
    .main-grid .btn-warning { background: var(--w); color: white; box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3); }

    .main-grid .btn-outline-warning { background: white; color: var(--w); border: 1px solid var(--w); padding: 8px 15px; border-radius: 8px; font-size: 12px; font-weight: 600; cursor: pointer; transition: 0.2s; }
    .main-grid .btn-outline-warning:hover { background: #fffbeb; }
    .main-grid .text-warning { color: var(--w) !important; }
    .main-grid .text-primary { color: var(--p) !important; }

    .main-grid .btn-outline { background: white; color: var(--p); border: 1px solid var(--p); padding: 8px 15px; border-radius: 8px; font-size: 12px; font-weight: 600; cursor: pointer; transition: 0.2s; }
    .main-grid .btn-outline:hover { background: #eff6ff; }
    .main-grid .custom-area { width: 100%; border: 2px solid var(--border); border-radius: 12px; padding: 15px; box-sizing: border-box; font-family: inherit; font-size: 14px; transition: 0.2s; }
    .main-grid .custom-area:focus { border-color: var(--p); outline: none; background: #fff; }

    /* ÖZEL DROPBOX */
    .main-grid .ms-container { position: relative; width: 100%; font-family: inherit; }
    .main-grid .ms-btn       { background: #fff; border: 2px solid var(--border); border-radius: 12px; padding: 14px 15px; display: flex; justify-content: space-between; align-items: center; cursor: pointer; color: var(--text-main); font-size: 14px; font-weight: 600; transition: 0.2s; }
    .main-grid .ms-btn:hover { border-color: var(--p); }
    .main-grid .ms-dropdown  { position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #cbd5e1; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); margin-top: 8px; z-index: 999; overflow: hidden; display: none; }
    .main-grid .ms-search    { padding: 15px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 10px; background: #f8fafc; }
    .main-grid .ms-search input { border: none; background: transparent; width: 100%; outline: none; font-size: 14px; color: var(--text-main); }
    .main-grid .ms-list      { max-height: 220px; overflow-y: auto; padding: 5px 0; }
    .main-grid .ms-item      { display: flex; align-items: center; gap: 12px; padding: 10px 20px; cursor: pointer; transition: 0.2s; font-size: 14px; color: #334155; }
    .main-grid .ms-item span { white-space: normal; word-break: break-word; }
    .main-grid .ms-item:hover { background: #f1f5f9; color: var(--p); font-weight: 600; }
    .main-grid .ms-item input[type="checkbox"] { width: 18px; height: 18px; accent-color: var(--p); cursor: pointer; }
    .main-grid .ms-footer    { padding: 15px; border-top: 1px solid var(--border); background: #f8fafc; }

    .main-grid .select2-container { width: 100% !important; }
    
    /* TAKVİM PREMİUM GÖRÜNÜM */
    .fc .fc-toolbar { margin-bottom: 25px !important; align-items: center; }
    .fc .fc-toolbar-title { font-size: 1.25rem !important; font-weight: 800; color: var(--text-main); text-transform: uppercase; letter-spacing: 0.5px; }
    
    .fc .fc-button-group { display: flex; gap: 8px; }
    .fc .fc-button-group > .fc-button { margin: 0 !important; border-radius: 8px !important; }
    
    .fc .fc-button-primary { 
        background-color: #fff !important; 
        color: #475569 !important; 
        border: 1px solid #cbd5e1 !important; 
        box-shadow: none !important; 
        text-transform: capitalize; 
        font-weight: 600 !important; 
        font-size: 13px !important; 
        transition: 0.2s; 
        padding: 8px 16px !important; 
    }
    .fc .fc-button-primary:hover { background-color: #f8fafc !important; color: var(--p) !important; border-color: #94a3b8 !important; }
    .fc .fc-button-primary:disabled { opacity: 0.5; }
    
    .fc .fc-today-button { 
        background-color: #3b82f6 !important; 
        color: #fff !important; 
        border-color: #93c5fd !important; 
    }
    .fc .fc-today-button:disabled { background-color: #93c5fd !important; color: #fff !important; opacity: 0.7; }
    
    .fc-theme-standard th { padding: 10px 0 !important; font-weight: 700; color: #64748b; font-size: 12px; background: #f8fafc; border-color: var(--border); }
    .fc-theme-standard td, .fc-theme-standard th { border-color: var(--border); }
    .fc-daygrid-day-number { font-weight: 600; color: #475569; font-size: 13px; padding: 8px !important; }
    .fc-event { border-radius: 6px !important; font-size: 11px !important; font-weight: 600; padding: 3px 5px !important; border: none !important; cursor: pointer; transition: 0.2s; margin-bottom: 3px !important; }
    .fc-event:hover { filter: brightness(0.9); transform: scale(1.02); }
    
    /* SELECT2 GÖRSEL UYUM SİSTEMİ */
    .select2-container--default .select2-selection--single { 
        background-color: #fff; 
        border: 2px solid var(--border); 
        border-radius: 12px; 
        height: 52px; 
        padding: 12px 15px; 
        transition: all 0.2s ease; 
        outline: none; 
        display: flex;
        align-items: center;
    }
    .select2-container--default.select2-container--open .select2-selection--single, 
    .select2-container--default .select2-selection--single:focus { 
        border-color: var(--p); background-color: #fff; 
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered { 
        color: var(--text-main); font-family: inherit; font-size: 14px; font-weight: 600; padding-left: 0; line-height: normal; width: 100%;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow { 
        height: 100%; right: 15px; top: 0; display: flex; align-items: center;
    }
    
    @media (max-width: 1100px) { .main-grid { grid-template-columns: 1fr; } }
</style>

<div class="main-grid">

    <div class="left-panel">

        <!-- GRAFİKLER ARTIK TAKVİMİN ÜSTÜNDE YER ALIYOR -->
        <?php if ($rol === 'Admin'): ?>
            <div class="admin-dashboard-charts">
                <div class="card">
                    <h4 class="card-title"><i style="color:var(--p)"></i> Personel Görev Takibi</h4>
                    <div class="chart-container">
                        <canvas id="chartPersonelIs"></canvas>
                    </div>
                </div>
                <div class="card">
                    <h4 class="card-title"><i style="color:var(--s)"></i> Genel Görev ve Performans Durumu</h4>
                    <div class="chart-container">
                        <canvas id="chartPerformans"></canvas>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- TAKVİM BÖLÜMÜ -->
        <div class="card" style="flex: none; margin-bottom: 0;">
            <div id='calendar'></div>
        </div>

        <?php if ($rol === 'Admin'): ?>
            <div id="admin-islem-merkezi">
                <div class="action-box">
                    <div class="action-header">
                        <h3 class="action-title" id="admin-detay-baslik"></h3>
                        <span class="badge" id="admin-detay-durum-badge"></span>
                    </div>

                    <div style="display:flex; gap:15px; margin-bottom:20px; flex-wrap:wrap;">
                        <div style="flex:1; min-width:150px; background:#f8fafc; padding:15px; border-radius:12px; border:1px solid #e2e8f0;">
                            <span style="font-size:11px; color:#64748b; font-weight:bold; text-transform:uppercase;"><i class="fas fa-user"></i> Görevli Personel</span>
                            <div style="font-weight:600; color:#1e293b; margin-top:5px; font-size:15px;" id="admin-detay-personel"></div>
                        </div>
                        <div style="flex:1; min-width:150px; background:#f8fafc; padding:15px; border-radius:12px; border:1px solid #e2e8f0;">
                            <span style="font-size:11px; color:#64748b; font-weight:bold; text-transform:uppercase;"><i class="fas fa-calendar-check"></i> Son Tarih</span>
                            <div style="font-weight:600; color:#1e293b; margin-top:5px; font-size:15px;" id="admin-detay-tarih"></div>
                        </div>
                    </div>

                    <div id="admin-detay-ekipman-kutu" style="display:none; margin-bottom:20px;">
                        <label class="equip-label"><i class="fas fa-toolbox"></i> Kullanılan Ekipmanlar</label>
                        <div style="background:#fff; border:1px dashed #cbd5e1; padding:15px; border-radius:12px; font-size:14px; color:#3b82f6; font-weight:600; margin-top:10px; word-wrap: break-word; white-space: normal;" id="admin-detay-ekipmanlar"></div>
                    </div>

                    <div id="admin-detay-rapor-kutu" style="display:none;">
                        <label class="equip-label"><i class="fas fa-file-signature"></i> Gün Sonu Raporu</label>
                        <div style="background:#f0fdf4; border:1px solid #a7f3d0; padding:15px; border-radius:12px; font-size:14px; color:#065f46; margin-top:10px; line-height:1.6; word-wrap: break-word; white-space: normal;" id="admin-detay-rapor"></div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div id="islem-merkezi">
                <?php
                $stmt = $pdo->prepare("SELECT * FROM gorevler WHERE Personel_ID = ? AND Durum != 'Tamamlandi'");
                $stmt->execute([$kullanici_id]);
                $tum_ekipmanlar = $pdo->query("SELECT Ekipman_ID, Ekipman_Adi FROM ekipman WHERE Durum = 'Aktif'")->fetchAll();

                while ($is = $stmt->fetch()):
                    $secili_stmt = $pdo->prepare("SELECT Ekipman_ID FROM gorev_ekipmanlari WHERE Gorev_ID = ?");
                    $secili_stmt->execute([$is['Gorev_ID']]);
                    $secili_ekipman_dizisi = $secili_stmt->fetchAll(PDO::FETCH_COLUMN);
                    $is_gecikti = ($is['Son_Tarih'] < $bugun);
                ?>
                    <div class="action-box gorev-formu <?= $is_gecikti ? 'a-gecikti' : ($is['Durum'] == 'Bekliyor' ? 'a-bekliyor' : 'a-devam') ?>" id="form_<?= $is['Gorev_ID'] ?>" style="display:none;">

                        <div class="action-header">
                            <h3 class="action-title">
                                <i class="fas fa-<?= $is_gecikti ? 'exclamation-triangle' : 'clipboard-check' ?> <?= $is_gecikti ? '' : ($is['Durum'] == 'Bekliyor' ? 'text-warning' : 'text-primary') ?>" style="margin-right:8px; <?= $is_gecikti ? 'color:var(--d)' : '' ?>"></i>
                                <?= htmlspecialchars($is['Baslik']) ?>
                            </h3>
                            <?php if ($is_gecikti): ?>
                                <span class="badge b-gecikti">⚠️ Gecikti</span>
                            <?php else: ?>
                                <span class="badge <?= $is['Durum'] == 'Bekliyor' ? 'b-bekliyor' : 'b-devam' ?>"><?= $is['Durum'] ?></span>
                            <?php endif; ?>
                        </div>

                        <?php if ($is_gecikti): ?>
                            <div style="background:#fef2f2; border:1px solid #fecaca; border-radius:10px; padding:12px 15px; margin-bottom:18px; font-size:13px; color:#b91c1c; font-weight:600;">
                                <i class="fas fa-exclamation-circle"></i>
                                Bu görevin son tarihi <b><?= date('d.m.Y', strtotime($is['Son_Tarih'])) ?></b> olup geçmiştir. Lütfen en kısa sürede tamamlayın.
                            </div>
                        <?php endif; ?>

                        <?php if ($is['Durum'] == 'Bekliyor'): ?>
                            <form method="POST" onsubmit="return formDogrulaBasla(event, this)">
                                <input type="hidden" name="gorev_id" value="<?= $is['Gorev_ID'] ?>">

                                <div class="equip-area">
                                    <div class="equip-header">
                                        <label class="equip-label"><i class="fas fa-toolbox"></i> Kullanılacak Ekipmanlar</label>
                                        <button type="button" class="btn-outline-warning" onclick="rutinModalAc('<?= $is['Gorev_ID'] ?>')"><i class="fas fa-bolt"></i> Rutin Yükle</button>
                                    </div>

                                    <div class="ms-container">
                                        <div class="ms-btn" onclick="toggleDropdown('ms-drop-<?= $is['Gorev_ID'] ?>')">
                                            <span id="ms-text-<?= $is['Gorev_ID'] ?>">Ekipman Seçmek İçin Tıklayın...</span>
                                            <i class="fas fa-chevron-down"></i>
                                        </div>
                                        <div class="ms-dropdown" id="ms-drop-<?= $is['Gorev_ID'] ?>">
                                            <div class="ms-search">
                                                <i class="fas fa-search"></i>
                                                <input type="text" placeholder="Malzeme adı aratın..." onkeyup="filterEkipman(this, '<?= $is['Gorev_ID'] ?>')">
                                            </div>
                                            <div class="ms-list" id="ms-list-<?= $is['Gorev_ID'] ?>">
                                                <?php foreach ($tum_ekipmanlar as $c): ?>
                                                    <label class="ms-item">
                                                        <input type="checkbox" name="ekipman_id[]" value="<?= $c['Ekipman_ID'] ?>" 
                                                           class="ekipman-cb-<?= $is['Gorev_ID'] ?>" 
                                                           onchange="updateBtnText('<?= $is['Gorev_ID'] ?>')">
                                                        <span><?= $c['Ekipman_Adi'] ?></span>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                            <div class="ms-footer">
                                                <button type="button" class="btn-main btn-warning" style="margin-top:0; padding:10px;" onclick="toggleDropdown('ms-drop-<?= $is['Gorev_ID'] ?>')"><i class="fas fa-check"></i> Seçimi Tamamla</button>
                                            </div>
                                        </div>
                                    </div>
                                    <div style="text-align: right; margin-top: 15px;">
                                        <a href="javascript:void(0)" onclick="rutinKaydet('<?= $is['Gorev_ID'] ?>')" class="text-warning" style="font-size:13px; font-weight:600; text-decoration:none;"><i class="fas fa-plus-circle"></i> Bu Seçimi Rutin Olarak Kaydet</a>
                                    </div>
                                </div>
                                <button type="submit" name="is_baslat" class="btn-main <?= $is_gecikti ? 'btn-blue' : 'btn-warning' ?>"><i class="fas fa-play"></i> İşe Başla</button>
                            </form>

                        <?php else: ?>
                            <div class="equip-area" style="background: #fff; border-color: var(--p);">
                                <div class="equip-header">
                                    <label class="equip-label"><i class="fas fa-tools"></i> Ekipman Güncelleme</label>
                                    <div style="display:flex; gap:8px;">
                                        <button type="button" class="btn-outline" onclick="rutinModalAc('edit_<?= $is['Gorev_ID'] ?>')"><i class="fas fa-bolt"></i> Rutin Yükle</button>
                                        <button type="button" class="btn-outline" onclick="$('#edit_<?= $is['Gorev_ID'] ?>').slideToggle()"><i class="fas fa-pen"></i> Seçimi Düzenle</button>
                                    </div>
                                </div>

                                <div id="edit_<?= $is['Gorev_ID'] ?>" style="display:none; padding-top:15px; border-top: 1px dashed #cbd5e1;">
                                    <form method="POST" onsubmit="return formDogrulaGuncelle(event, this)">
                                        <input type="hidden" name="gorev_id" value="<?= $is['Gorev_ID'] ?>">

                                        <div class="ms-container">
                                            <div class="ms-btn" onclick="toggleDropdown('ms-drop-edit_<?= $is['Gorev_ID'] ?>')">
                                                <span id="ms-text-edit_<?= $is['Gorev_ID'] ?>">
                                                    <?= count($secili_ekipman_dizisi) > 0 ? "<span style='color:var(--p)'><i class='fas fa-check-double'></i> " . count($secili_ekipman_dizisi) . " Ekipman Seçili</span>" : "Ekipman Seçiniz..." ?>
                                                </span>
                                                <i class="fas fa-chevron-down"></i>
                                            </div>
                                            <div class="ms-dropdown" id="ms-drop-edit_<?= $is['Gorev_ID'] ?>">
                                                <div class="ms-search">
                                                    <i class="fas fa-search"></i>
                                                    <input type="text" placeholder="Malzeme adı aratın..." onkeyup="filterEkipman(this, 'edit_<?= $is['Gorev_ID'] ?>')">
                                                </div>
                                                <div class="ms-list" id="ms-list-edit_<?= $is['Gorev_ID'] ?>">
                                                    <?php foreach ($tum_ekipmanlar as $c):
                                                        $isChecked = in_array($c['Ekipman_ID'], $secili_ekipman_dizisi) ? 'checked' : '';
                                                    ?>
                                                        <label class="ms-item">
                                                            <input type="checkbox" name="yeni_ekipman_id[]" value="<?= $c['Ekipman_ID'] ?>" 
                                                                   class="ekipman-cb-edit_<?= $is['Gorev_ID'] ?>" 
                                                                   id="cb_edit_<?= $is['Gorev_ID'] ?>_<?= $c['Ekipman_ID'] ?>" 
                                                                   <?= $isChecked ?> 
                                                                   onchange="updateBtnText('edit_<?= $is['Gorev_ID'] ?>')">
                                                            <span><?= $c['Ekipman_Adi'] ?></span>
                                                        </label>
                                                    <?php endforeach; ?>
                                                </div>
                                                <div class="ms-footer">
                                                    <button type="button" class="btn-main btn-blue" style="margin-top:0; padding:10px;" onclick="toggleDropdown('ms-drop-edit_<?= $is['Gorev_ID'] ?>')"><i class="fas fa-check"></i> Seçimi Tamamla</button>
                                                </div>
                                            </div>
                                        </div>
                                        <div style="text-align: right; margin-top: 15px;">
                                            <a href="javascript:void(0)" onclick="rutinKaydet('edit_<?= $is['Gorev_ID'] ?>')" class="text-primary" style="font-size:13px; font-weight:600; text-decoration:none;"><i class="fas fa-plus-circle"></i> Bu Seçimi Rutin Olarak Kaydet</a>
                                        </div>

                                        <button type="submit" name="ekipman_guncelle" class="btn-outline" style="margin-top:15px; width:100%;"><i class="fas fa-sync-alt"></i> Listeyi Kaydet</button>
                                    </form>
                                </div>
                            </div>

                            <form method="POST" onsubmit="return formDogrulaBitir(event, this)">
                                <input type="hidden" name="gorev_id" value="<?= $is['Gorev_ID'] ?>">
                                <label class="equip-label" style="margin-bottom:8px; display:block;"><i class="fas fa-file-signature"></i> Gün Sonu Raporu</label>
                                <textarea name="rapor" class="custom-area" style="min-height:100px;" placeholder="Yapılan işlemleri detaylıca buraya yazın..."></textarea>
                                <button type="submit" name="is_bitir" class="btn-main btn-blue"><i class="fas fa-check-circle"></i> Raporu Teslim Et ve İşi Bitir</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="right-panel">

        <?php if ($rol === 'Admin'): ?>
            <div class="card" style="flex: none;">
                <h4 class="card-title">Görev Atama</h4>
                <form method="POST" onsubmit="return formDogrulaAta(event)">
                    <select name="personel_id" class="js-ara" style="width:100%;">
                        <option value="">Personel Seçin</option>
                        <?php foreach ($pdo->query("SELECT Personel_ID, Ad_Soyad FROM personel WHERE Aktif_Mi=1 AND Rol='Personel'") as $p) echo "<option value='{$p['Personel_ID']}'>{$p['Ad_Soyad']}</option>"; ?>
                    </select>
                    <input type="text" name="baslik" class="custom-area" placeholder="Görev konusu..." style="margin-top:10px;">
                    <input type="date" name="son_tarih" class="custom-area" value="<?= $bugun ?>" min="<?= $bugun ?>" style="margin-top: 10px; cursor: pointer;" onkeydown="return false;" onpaste="return false;">
                    <button type="submit" name="gorev_ata" class="btn-main btn-blue">Görevlendir</button>
                </form>
            </div>
        <?php endif; ?>

        <div class="card">
            <h4 class="card-title"><i class="fas fa-<?= $rol === 'Admin' ? 'tasks' : 'inbox' ?>" style="color:var(--p)"></i> Aktif İş Takibi</h4>
            <div class="inbox-list <?= $rol === 'Admin' ? 'admin-list-aktif' : 'personel-list-aktif' ?>">
                <?php
                $aktif_var = false;
                $sql_aktif = ($rol === 'Admin')
                    ? "SELECT g.*, p.Ad_Soyad FROM gorevler g JOIN personel p ON g.Personel_ID = p.Personel_ID WHERE g.Durum != 'Tamamlandi' ORDER BY g.Son_Tarih ASC"
                    : "SELECT g.*, 'Görevim' as Ad_Soyad FROM gorevler g WHERE g.Personel_ID = $kullanici_id AND g.Durum != 'Tamamlandi' ORDER BY g.Son_Tarih ASC";

                foreach ($pdo->query($sql_aktif) as $i):
                    $aktif_var = true;
                    $gecikti = ($i['Son_Tarih'] < $bugun);

                    if ($gecikti) {
                        $bg       = 's-gecikti';
                        $bdg      = 'b-gecikti';
                        $bdg_text = '⚠️ Gecikti';
                    } elseif ($i['Durum'] == 'Bekliyor') {
                        $bg       = 's-bekliyor';
                        $bdg      = 'b-bekliyor';
                        $bdg_text = 'Bekliyor';
                    } else {
                        $bg       = 's-devam';
                        $bdg      = 'b-devam';
                        $bdg_text = 'Devam Ediyor';
                    }
                ?>
                    <div class="inbox-item item-btn <?= $bg ?>" id="list_btn_<?= $i['Gorev_ID'] ?>"
                         onclick="<?= $rol === 'Admin' ? 'adminIslemPaneliAc' : 'islemPaneliAc' ?>(<?= $i['Gorev_ID'] ?>)">
                        <div style="display:flex; justify-content:space-between; align-items:start;">
                            <span class="inbox-title"><?= htmlspecialchars($i['Baslik']) ?></span>
                            <?php if ($rol === 'Admin'): ?>
                                <a href="javascript:void(0);"
                                   onclick="gorevSilConfirm(<?= $i['Gorev_ID'] ?>, event)"
                                   style="color:var(--d)"><i class="fas fa-trash"></i></a>
                            <?php endif; ?>
                        </div>
                        <div class="inbox-meta">
                            <?php if ($rol === 'Admin'): ?>
                                <span><i class="fas fa-user"></i> <?= $i['Ad_Soyad'] ?></span>
                            <?php else: ?>
                                <span style="<?= $gecikti ? 'color:var(--d);font-weight:600;' : '' ?>">
                                    <i class="far fa-calendar"></i> <?= date('d.m.Y', strtotime($i['Son_Tarih'])) ?>
                                    <?= $gecikti ? ' <i class="fas fa-exclamation-circle"></i>' : '' ?>
                                </span>
                            <?php endif; ?>
                            <span class="badge <?= $bdg ?>"><?= $bdg_text ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (!$aktif_var): ?>
                    <div style="text-align:center; padding:20px; color:#94a3b8;"><p>Bekleyen iş yok.</p></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <h4 class="card-title" style="color:#64748b;"><i class="fas fa-history"></i> Biten Görevler & Raporlar</h4>
            <div class="inbox-list <?= $rol === 'Admin' ? 'admin-list-biten' : 'personel-list-biten' ?>">
                <?php
                $sql_biten = ($rol === 'Admin')
                    ? "SELECT g.*, p.Ad_Soyad FROM gorevler g JOIN personel p ON g.Personel_ID = p.Personel_ID WHERE g.Durum = 'Tamamlandi' ORDER BY g.Son_Tarih DESC LIMIT 15"
                    : "SELECT g.*, 'Görevim' as Ad_Soyad FROM gorevler g WHERE g.Personel_ID = $kullanici_id AND g.Durum = 'Tamamlandi' ORDER BY g.Son_Tarih DESC LIMIT 10";

                foreach ($pdo->query($sql_biten) as $i): ?>
                    <div class="inbox-item item-btn s-bitti" id="list_btn_<?= $i['Gorev_ID'] ?>" <?php if ($rol === 'Admin') echo "onclick='adminIslemPaneliAc({$i['Gorev_ID']})'"; ?>>
                        <span class="inbox-title" style="text-decoration: line-through; color:#94a3b8;"><?= htmlspecialchars($i['Baslik']) ?></span>
                        <div class="inbox-meta">
                            <?php if ($rol === 'Admin'): ?>
                                <span><i class="fas fa-user"></i> <?= $i['Ad_Soyad'] ?></span>
                            <?php else: ?>
                                <span><?= date('d.m.Y', strtotime($i['Son_Tarih'])) ?></span>
                            <?php endif; ?>
                            <span><i class="fas fa-check"></i> Tamamlandı</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
// ==========================================
// 1. GLOBAL DEĞİŞKENLER VE AYARLAR
// ==========================================

const adminTasks = {};
<?php if ($rol === 'Admin'):
    $tasks = $pdo->query($admin_sql)->fetchAll();
    foreach ($tasks as $t): ?>
    adminTasks[<?= $t['Gorev_ID'] ?>] = {
        baslik:    <?= json_encode($t['Baslik']) ?>,
        personel:  <?= json_encode($t['Ad_Soyad']) ?>,
        durum:     <?= json_encode($t['Durum']) ?>,
        tarih:     <?= json_encode(date('d.m.Y', strtotime($t['Son_Tarih']))) ?>,
        gecikti:   <?= ($t['Durum'] !== 'Tamamlandi' && $t['Son_Tarih'] < $bugun) ? 'true' : 'false' ?>,
        ekipmanlar: <?= json_encode($t['Ekipmanlar'] ?? '') ?>,
        rapor:     <?= json_encode($t['Rapor'] ?? '') ?>
    };
<?php endforeach; endif; ?>

const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    showCloseButton: true, 
    timer: 2500, 
    timerProgressBar: true,
    background: '#ffffff',
    iconColor: '#ef4444',
    didOpen: (toast) => {
        toast.addEventListener('mouseenter', Swal.stopTimer)
        toast.addEventListener('mouseleave', Swal.resumeTimer)
    }
});

// ==========================================
// 2. FONKSİYONLAR
// ==========================================

function adminIslemPaneliAc(id) {
    $('.item-btn').removeClass('active-select');
    $('#list_btn_' + id).addClass('active-select');

    let task = adminTasks[id];
    if (task) {
        $('#admin-detay-baslik').text(task.baslik);
        $('#admin-detay-personel').text(task.personel);

        if (task.gecikti === 'true' || task.gecikti === true) {
            $('#admin-detay-tarih').html('<span style="color:#ef4444; font-weight:700;"><i class="fas fa-exclamation-triangle"></i> ' + task.tarih + ' (GECİKTİ)</span>');
        } else {
            $('#admin-detay-tarih').text(task.tarih);
        }

        let bdgClass, aBoxClass, bdgText;
        if (task.gecikti === 'true' || task.gecikti === true) {
            bdgClass = 'b-gecikti'; aBoxClass = 'a-gecikti'; bdgText = '⚠️ Gecikti';
        } else if (task.durum === 'Bekliyor') {
            bdgClass = 'b-bekliyor'; aBoxClass = 'a-bekliyor'; bdgText = 'Bekliyor';
        } else if (task.durum === 'Tamamlandi') {
            bdgClass = 'b-bitti'; aBoxClass = 'a-bitti'; bdgText = 'Tamamlandı';
        } else {
            bdgClass = 'b-devam'; aBoxClass = 'a-devam'; bdgText = 'Devam Ediyor';
        }

        $('#admin-detay-durum-badge').text(bdgText).removeClass('b-bekliyor b-devam b-bitti b-gecikti').addClass(bdgClass);
        $('#admin-islem-merkezi .action-box').removeClass('a-bekliyor a-devam a-bitti a-gecikti').addClass(aBoxClass);

        if (task.durum !== 'Bekliyor' && task.ekipmanlar) {
            $('#admin-detay-ekipmanlar').text(task.ekipmanlar);
            $('#admin-detay-ekipman-kutu').show();
        } else {
            $('#admin-detay-ekipman-kutu').hide();
        }

        if (task.durum === 'Tamamlandi' && task.rapor) {
            $('#admin-detay-rapor').text(task.rapor);
            $('#admin-detay-rapor-kutu').show();
        } else {
            $('#admin-detay-rapor-kutu').hide();
        }

        $('#admin-islem-merkezi').fadeIn(300, function () {
            let container = $('.content-area').length ? $('.content-area') : $('html, body');
            let target = $(this);
            if (target.offset()) {
                let topPos = target.offset().top - (container.is('html, body') ? 0 : container.offset().top) + container.scrollTop() - 20;
                container.animate({ scrollTop: topPos }, 400);
            }
        });
    }
}

function islemPaneliAc(gorev_id) {
    $('.item-btn').removeClass('active-select');
    $('#list_btn_' + gorev_id).addClass('active-select');
    $('#islem-merkezi').show();
    $('.gorev-formu').hide();

    $('#form_' + gorev_id).fadeIn(300, function () {
        let container = $('.content-area').length ? $('.content-area') : $('html, body');
        let target    = $(this);
        if (target.offset()) {
            let topPos = target.offset().top - (container.is('html, body') ? 0 : container.offset().top) + container.scrollTop() - 20;
            container.animate({ scrollTop: topPos }, 400);
        }
    });
}

function toggleDropdown(dropdownId) {
    $('.ms-dropdown').not('#' + dropdownId).fadeOut(150);
    $('#' + dropdownId).fadeToggle(150);
}

document.addEventListener('click', function (event) {
    if (!event.target.closest('.ms-container')) { $('.ms-dropdown').fadeOut(150); }
});

function updateBtnText(id, isGecikti = false) {
    let checked = $('.ekipman-cb-' + id + ':checked').length;
    let btnText = document.getElementById('ms-text-' + id);
    if (!btnText) return;
    let iconColor = isGecikti ? 'var(--d)' : 'var(--p)';
    if (checked === 0) { btnText.innerText = 'Ekipman Seçmek İçin Tıklayın...'; } 
    else if (checked === 1) { btnText.innerHTML = `<span style="color:${iconColor}"><i class="fas fa-check-circle"></i> 1 Ekipman Seçildi</span>`; } 
    else { btnText.innerHTML = `<span style="color:${iconColor}"><i class="fas fa-check-double"></i> ${checked} Ekipman Seçildi</span>`; }
}

function filterEkipman(input, id) {
    let filter = input.value.toLowerCase();
    let items = document.querySelectorAll('#ms-list-' + id + ' .ms-item');
    items.forEach(item => {
        let text = item.textContent || item.innerText;
        item.style.display = text.toLowerCase().indexOf(filter) > -1 ? "flex" : "none";
    });
}

function gorevSilConfirm(id, event) {
    event.stopPropagation();
    event.preventDefault();
    Swal.fire({
        title: 'Görevi Sil',
        text: "Bu görevi kalıcı olarak silmek istediğinize emin misiniz?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444', 
        cancelButtonColor: '#94a3b8',
        confirmButtonText: '<i class="fas fa-trash"></i> Evet, Sil',
        cancelButtonText: 'İptal'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'panel.php?sayfa=is_takip&sil_id=' + id;
        }
    });
}

function formDogrulaAta(event) {
    let form = event.target;
    let personel = form.querySelector('[name="personel_id"]').value;
    let baslik = form.querySelector('[name="baslik"]').value.trim();

    if (!personel) {
        event.preventDefault();
        Toast.fire({ icon: 'error', title: 'Lütfen görev atanacak personeli seçin!' });
        return false;
    }

    if (!baslik || baslik.length < 3) {
        event.preventDefault();
        Toast.fire({ icon: 'error', title: 'Lütfen geçerli bir görev konusu yazın!' });
        return false;
    }
    return true;
}

function formDogrulaBasla(event, form) {
    event.preventDefault();
    let gorevId = form.querySelector('[name="gorev_id"]').value;
    let checkedCount = form.querySelectorAll('.ekipman-cb-' + gorevId + ':checked').length;
    
    if (checkedCount === 0) {
        Toast.fire({ icon: 'error', title: 'Lütfen ekipman seçimi yapınız!' });
        return false;
    }

    Swal.fire({
        title: 'İşe Başla',
        text: "Görevi başlatmak istediğinize emin misiniz?",
        icon: 'info',
        iconColor: '#3b82f6',
        showCancelButton: true,
        confirmButtonColor: '#3b82f6',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: '<i class="fas fa-play"></i> Evet, Başlat',
        cancelButtonText: 'İptal'
    }).then((result) => { 
        if (result.isConfirmed) { 
            let hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden'; hiddenInput.name = 'is_baslat'; hiddenInput.value = '1';
            form.appendChild(hiddenInput);
            form.submit(); 
        } 
    });
}

function formDogrulaGuncelle(event, form) {
    event.preventDefault();
    let gorevId = form.querySelector('[name="gorev_id"]').value;
    let checkedCount = form.querySelectorAll('.ekipman-cb-edit_' + gorevId + ':checked').length;
    
    if (checkedCount === 0) {
        Toast.fire({ icon: 'error', title: 'Lütfen en az bir ekipman seçimi yapınız!' });
        return false;
    }

    Swal.fire({
        title: 'Ekipmanları Güncelle',
        text: "Ekipman listesini güncellemek istediğinize emin misiniz?",
        icon: 'warning',
        iconColor: '#f59e0b',
        showCancelButton: true,
        confirmButtonColor: '#f59e0b',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: '<i class="fas fa-sync-alt"></i> Evet, Güncelle',
        cancelButtonText: 'İptal'
    }).then((result) => { 
        if (result.isConfirmed) { 
            let hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden'; hiddenInput.name = 'ekipman_guncelle'; hiddenInput.value = '1';
            form.appendChild(hiddenInput);
            form.submit(); 
        } 
    });
}

function formDogrulaBitir(event, form) {
    event.preventDefault();
    let rapor = form.querySelector('[name="rapor"]').value.trim();
    if (!rapor || rapor.length < 5) {
        Toast.fire({ icon: 'error', title: 'Lütfen detaylı bir gün sonu raporu yazın!' });
        return false;
    }
    
    Swal.fire({
        title: 'Görevi Tamamla',
        text: "Görevi bitirmek ve raporu teslim etmek istediğinize emin misiniz?",
        icon: 'question',
        iconColor: '#10b981',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: '<i class="fas fa-check-circle"></i> Evet, İşi Bitir',
        cancelButtonText: 'İptal'
    }).then((result) => { 
        if (result.isConfirmed) { 
            let hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden'; hiddenInput.name = 'is_bitir'; hiddenInput.value = '1';
            form.appendChild(hiddenInput);
            form.submit(); 
        } 
    });
}

function rutinKaydet(gorevId) {
    let secilenler = [];
    $('.ekipman-cb-' + gorevId + ':checked').each(function () { secilenler.push($(this).val()); });

    if (secilenler.length === 0) {
        Toast.fire({ icon: 'error', title: 'Önce malzemeleri seçmelisiniz!' });
        return;
    }

    Swal.fire({
        title: 'Rutine İsim Verin', input: 'text', inputPlaceholder: 'Örn: Sera İlaçlama Seti',
        showCancelButton: true, confirmButtonText: '<i class="fas fa-save"></i> Kaydet', cancelButtonText: 'İptal', confirmButtonColor: '#10b981'
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            let rutinler = JSON.parse(localStorage.getItem('greenlog_rutinler')) || {};
            rutinler[result.value] = secilenler;
            localStorage.setItem('greenlog_rutinler', JSON.stringify(rutinler));
            Toast.fire({ icon: 'success', iconColor: '#10b981', title: 'Kayıt Başarılı!' });
        }
    });
}

function rutinModalAc(gorevId, isGecikti = false) {
    let rutinler = JSON.parse(localStorage.getItem('greenlog_rutinler')) || {};
    let isimler = Object.keys(rutinler);
    
    if (isimler.length === 0) { 
        Toast.fire({ icon: 'info', title: 'Henüz kayıtlı bir şablonunuz yok!' }); 
        return; 
    }

    let options = '<option value="" disabled selected>Bir şablon seçin...</option>';
    isimler.forEach(isim => { options += `<option value="${isim}">${isim}</option>`; });

    Swal.fire({
        title: '<span style="font-size: 1.5rem; font-weight: 800; color: #1e293b;">Rutin Yükle</span>',
        html: `
            <div style="text-align: left; padding: 10px 5px;">
                <label style="display:block; font-size: 12px; font-weight: 700; color: #64748b; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 1px;">
                    <i class="fas fa-layer-group" style="color: #3b82f6; margin-right: 5px;"></i> Kayıtlı Şablonlar
                </label>
                <select id="swal-rutin-sec" style="width: 100%; height: 55px; padding: 0 15px; font-size: 15px; font-weight: 600; color: #1e293b; border-radius: 14px; appearance: none; background: #f8fafc url('data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2224%22 height=%2224%22 viewBox=%220 0 24 24%22 fill=%22none%22 stroke=%22%2364748b%22 stroke-width=%222%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22><polyline points=%226 9 12 15 18 9%22></polyline></svg>') no-repeat right 15px center; background-size: 18px; cursor: pointer; border: 2px solid #e2e8f0; outline: none; transition: all 0.2s ease; box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);" onfocus="this.style.borderColor='#3b82f6'; this.style.background='#fff';" onblur="this.style.borderColor='#e2e8f0'; this.style.background='#f8fafc';">
                    ${options}
                </select>
            </div>
        `,
        showCloseButton: true,
        showCancelButton: false,
        showDenyButton: true, 
        confirmButtonText: '<i class="fas fa-check-circle"></i> Uygula', 
        denyButtonText: '<i class="fas fa-trash-alt"></i> Sil',
        confirmButtonColor: '#3b82f6', 
        denyButtonColor: '#ef4444'
    }).then((result) => {
        let secilenRutinAd = document.getElementById('swal-rutin-sec').value;

        if (result.isConfirmed) {
            if (!secilenRutinAd) {
                Toast.fire({ icon: 'error', title: 'Lütfen yüklemek için bir şablon seçin!' });
                return;
            }
            let secilecekIdler = rutinler[secilenRutinAd];
            
            $('.ekipman-cb-' + gorevId).prop('checked', false);
            
            secilecekIdler.forEach(id => { 
                $('.ekipman-cb-' + gorevId + '[value="' + id + '"]').prop('checked', true); 
            });
            
            updateBtnText(gorevId, isGecikti);
            Toast.fire({ icon: 'success', iconColor: '#10b981', title: 'Rutin başarıyla yüklendi!' });
        } 
        else if (result.isDenied) {
            if (!secilenRutinAd) {
                Toast.fire({ icon: 'error', title: 'Lütfen silmek için bir şablon seçin!' });
                return;
            }
            
            Swal.fire({
                title: 'Şablonu Sil',
                html: `<strong>${secilenRutinAd}</strong> şablonunu kalıcı olarak silmek istediğinize emin misiniz?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#94a3b8',
                confirmButtonText: '<i class="fas fa-trash"></i> Evet, Sil',
                cancelButtonText: 'İptal'
            }).then((delResult) => {
                if (delResult.isConfirmed) {
                    delete rutinler[secilenRutinAd]; 
                    localStorage.setItem('greenlog_rutinler', JSON.stringify(rutinler)); 
                    Toast.fire({ icon: 'success', iconColor: '#10b981', title: 'Şablon silindi!' });
                }
            });
        }
    });
}

// ==========================================
// 3. SAYFA YÜKLENDİĞİNDE ÇALIŞACAK KISIM
// ==========================================
$(document).ready(function () {
    
    const urlParams = new URLSearchParams(window.location.search);
    const islem = urlParams.get('islem');
    
    if (islem === 'basladi') {
        Toast.fire({ icon: 'success', iconColor: '#10b981', title: 'İş başarıyla başlatıldı!' });
        window.history.replaceState(null, null, window.location.pathname + '?sayfa=is_takip');
    } else if (islem === 'bitti') {
        Toast.fire({ icon: 'success', iconColor: '#10b981', title: 'Rapor teslim edildi ve görev tamamlandı!' });
        window.history.replaceState(null, null, window.location.pathname + '?sayfa=is_takip');
    } else if (islem === 'atandi') {
        Toast.fire({ icon: 'success', iconColor: '#10b981', title: 'Görev başarıyla personele atandı!' });
        window.history.replaceState(null, null, window.location.pathname + '?sayfa=is_takip');
    } else if (islem === 'guncellendi') {
        Toast.fire({ icon: 'success', iconColor: '#10b981', title: 'Ekipman listesi güncellendi!' });
        window.history.replaceState(null, null, window.location.pathname + '?sayfa=is_takip');
    }

    <?php if ($rol === 'Admin'): ?>
        $('#admin-islem-merkezi').hide();

        // --- ADMIN: DİNAMİK CHART.JS GRAFİK KURULUMLARI ---
        // 1. Personel İş Yükü Çubuk Grafiği (Hangi personel ne kadar iş yapmış)
        const ctxPersonel = document.getElementById('chartPersonelIs').getContext('2d');
        new Chart(ctxPersonel, {
            type: 'bar',
            data: {
                labels: [<?php foreach($grafik_personel_is as $g_p) { echo json_encode($g_p['Ad_Soyad']) . ","; } ?>],
                datasets: [{
                    label: 'Toplam Atanan Görev',
                    data: [<?php foreach($grafik_personel_is as $g_p) { echo intval($g_p['Toplam']) . ","; } ?>],
                    backgroundColor: 'rgba(59, 130, 246, 0.75)',
                    borderColor: '#3b82f6',
                    borderWidth: 1.5,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1, color: '#64748b' }, grid: { color: '#f1f5f9' } },
                    x: { ticks: { color: '#64748b' }, grid: { display: false } }
                }
            }
        });

        // 2. Zamanında Tamamlama & Genel Performans Dağılımı (Doughnut Chart)
        const ctxPerformans = document.getElementById('chartPerformans').getContext('2d');
        new Chart(ctxPerformans, {
            type: 'doughnut',
            data: {
                labels: ['Tamamlanan', 'Süresi Geçen (Gecikmiş)', 'Devam Eden', 'Bekleyen'],
                datasets: [{
                    data: [
                        <?= intval($istatistik_durumlar['Biten'] ?? 0) ?>,
                        <?= intval($istatistik_durumlar['Geciken'] ?? 0) ?>,
                        <?= intval($istatistik_durumlar['DevamEden'] ?? 0) ?>,
                        <?= intval($istatistik_durumlar['Bekleyen'] ?? 0) ?>
                    ],
                    backgroundColor: ['#10b981', '#ef4444', '#3b82f6', '#f59e0b'],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'right', labels: { boxWidth: 12, font: { size: 12 }, color: '#475569' } }
                },
                cutout: '65%'
            }
        });
    <?php endif; ?>
    
    $('.js-ara').select2();

    var calendarEl = document.getElementById('calendar');
    if(calendarEl) {
        var calendar = new FullCalendar.Calendar(calendarEl, {
            locale: 'tr', 
            initialView: 'dayGridMonth', 
            contentHeight: "auto",
            headerToolbar: { left: '', center: 'title', right: 'prev,today,next' },
            buttonText: { today: 'Bugün' },
            eventClick: function (info) {
                let props = info.event.extendedProps;
                <?php if ($rol !== 'Admin'): ?>
                    if (props.durum !== 'Tamamlandi') { islemPaneliAc(props.id); }
                <?php else: ?>
                    adminIslemPaneliAc(props.id);
                <?php endif; ?>
            },
            events: [<?php
                $sql = ($rol === 'Admin') ? "SELECT g.*, p.Ad_Soyad FROM gorevler g JOIN personel p ON g.Personel_ID=p.Personel_ID" : "SELECT * FROM gorevler WHERE Personel_ID = $kullanici_id";
                foreach ($pdo->query($sql)->fetchAll() as $ev):
                    $ev_gecikti = ($ev['Durum'] !== 'Tamamlandi' && $ev['Son_Tarih'] < $bugun);
                    $color = $ev_gecikti ? '#ef4444' : (($ev['Durum'] == 'Tamamlandi') ? '#10b981' : (($ev['Durum'] == 'Devam Ediyor') ? '#3b82f6' : '#f59e0b'));
                    $titleText = ($rol === 'Admin') ? ($ev['Ad_Soyad'] . ": " . $ev['Baslik']) : $ev['Baslik'];
                    $props = json_encode(['durum' => $ev['Durum'], 'id' => $ev['Gorev_ID']]);
                ?>
                { title: <?= json_encode($titleText) ?>, start: <?= json_encode($ev['Son_Tarih']) ?>, backgroundColor: '<?= $color ?>', borderColor: 'transparent', extendedProps: <?= $props ?> },
            <?php endforeach; ?>]
        });
        calendar.render();

        $('.fc-toolbar-chunk:first-child').html('<div style="display:flex; align-items:center; gap:8px; font-size:1.25rem; font-weight:700; color:var(--text-main);"><i class="fas fa-calendar-alt" style="color:var(--p);"></i> İş Planı</div>');
        $('.fc-toolbar').css({'margin-bottom': '25px', 'align-items': 'center'});
    }

    <?php if ($rol !== 'Admin'): ?>
        let ilkAktifGorevBtn = $('.item-btn').first();
        if (ilkAktifGorevBtn.length > 0 && ilkAktifGorevBtn.attr('id')) {
            let ilkGorevId = ilkAktifGorevBtn.attr('id').replace('list_btn_', '');
            islemPaneliAc(ilkGorevId);
        }
    <?php endif; ?>

    const focusId = urlParams.get('focus_id');
    if (focusId) {
        setTimeout(function() {
            <?php if ($rol === 'Admin'): ?> adminIslemPaneliAc(focusId); <?php else: ?> islemPaneliAc(focusId); <?php endif; ?>
        }, 300);
    }
});
</script>