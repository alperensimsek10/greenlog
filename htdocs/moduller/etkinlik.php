<?php
/**
 * GreenLog - Etkinlik Yönetimi, Katılımcı Paneli, Eşit Boyutlu Butonlar ve Yönetim Modülü
 */
if (!isset($_SESSION['Personel_ID'])) { exit("Erişim Engellendi"); }

date_default_timezone_set('Europe/Istanbul');
$bugun = date('Y-m-d');
$islem_hatasi = "";
$aktif_personel_id = (int)$_SESSION['Personel_ID'];

// --- 1. SİLME İŞLEMİ (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['etkinlik_sil'])) {
    try {
        $sil_id = (int)$_POST['sil_id'];
        if ($sil_id > 0) {
            $sil_sorgu = $pdo->prepare("DELETE FROM etkinlikler WHERE Etkinlik_ID = ?");
            $sil_sorgu->execute([$sil_id]);
            echo "<script>alert('Etkinlik başarıyla silindi!'); window.location.href = '?sayfa=etkinlik';</script>";
            exit;
        }
    } catch (PDOException $e) {
        $islem_hatasi = "Silme Hatası: " . $e->getMessage();
    }
}

// --- 2. DÜZENLEME (GÜNCELLEME) İŞLEMİ (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['etkinlik_guncelle'])) {
    try {
        $guncelle_id = (int)$_POST['guncelle_id'];
        $etkinlik_adi = trim($_POST['etkinlik_adi']);
        $etkinlik_tarihi = $_POST['etkinlik_tarihi'];
        $aciklama = trim($_POST['aciklama']);

        if ($guncelle_id > 0 && !empty($etkinlik_adi) && !empty($etkinlik_tarihi)) {
            $guncel_sorgu = $pdo->prepare("UPDATE etkinlikler SET Etkinlik_Adi = ?, Etkinlik_Tarihi = ?, Aciklama = ? WHERE Etkinlik_ID = ?");
            $guncel_sorgu->execute([$etkinlik_adi, $etkinlik_tarihi, $aciklama, $guncelle_id]);
            
            $yonlendir = isset($_GET['detay_id']) ? "?sayfa=etkinlik&detay_id=".$guncelle_id : "?sayfa=etkinlik";
            echo "<script>alert('Etkinlik başarıyla güncellendi!'); window.location.href = '".$yonlendir."';</script>";
            exit;
        }
    } catch (PDOException $e) {
        $islem_hatasi = "Güncelleme Hatası: " . $e->getMessage();
    }
}

// --- 3. YENİ ETKİNLİK OLUŞTURMA İŞLEMİ (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['etkinlik_olustur'])) {
    try {
        $etkinlik_adi = trim($_POST['etkinlik_adi']);
        $etkinlik_tarihi = $_POST['etkinlik_tarihi'];
        $aciklama = trim($_POST['aciklama']);

        if (!empty($etkinlik_adi) && !empty($etkinlik_tarihi)) {
            // Olusturan_Personel_ID sütunu sorguya ve execute dizisine eklendi
            $ekle_sorgu = $pdo->prepare("INSERT INTO `etkinlikler` (`Etkinlik_Adi`, `Etkinlik_Tarihi`, `Aciklama`, `Olusturan_Personel_ID`) VALUES (?, ?, ?, ?)");
            $ekle_sorgu->execute([$etkinlik_adi, $etkinlik_tarihi, $aciklama, $aktif_personel_id]);
            echo "<script>alert('Etkinlik başarıyla oluşturuldu!'); window.location.href = '?sayfa=etkinlik';</script>";
            exit;
        }
    } catch (PDOException $e) {
        $islem_hatasi = "Ekleme Hatası: " . $e->getMessage();
    }
}

// --- 4. DETAY VE SAYAÇ PARAMETRELERİ ---
$detay_modu = false;
$secilen_etkinlik = null;
$katilimcilar = [];
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$detay_id = isset($_GET['detay_id']) ? (int)$_GET['detay_id'] : 0;

if ($detay_id > 0) {
    // Detay görünümünde de oluşturan personelin adını çekebilmek için LEFT JOIN ekledik
    $etk_sorgu = $pdo->prepare("SELECT e.*, p.Ad_Soyad as Olusturan_Personel FROM etkinlikler e LEFT JOIN personel p ON e.Olusturan_Personel_ID = p.Personel_ID WHERE e.Etkinlik_ID = ?");
    $etk_sorgu->execute([$detay_id]);
    $secilen_etkinlik = $etk_sorgu->fetch(PDO::FETCH_ASSOC);

    if ($secilen_etkinlik) {
        $detay_modu = true;
        $etkinlik_adi_param = '%' . $secilen_etkinlik['Etkinlik_Adi'] . '%';

        $stmt1 = $pdo->prepare("SELECT COUNT(*) FROM ziyaretciler WHERE Ziyaret_Nedeni LIKE ?");
        $stmt1->execute([$etkinlik_adi_param]);
        $kart_toplam_katilimci = $stmt1->fetchColumn() ?: 0;

        $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM ziyaretciler WHERE Ziyaret_Nedeni LIKE ? AND (Durum = 'İçeride' OR Durum = 'Iceride')");
        $stmt2->execute([$etkinlik_adi_param]);
        $kart_aktif_iceride = $stmt2->fetchColumn() ?: 0;

        $stmt3 = $pdo->prepare("SELECT COUNT(*) FROM ziyaretciler WHERE Ziyaret_Nedeni LIKE ? AND (Durum != 'İçeride' AND Durum != 'Iceride')");
        $stmt3->execute([$etkinlik_adi_param]);
        $kart_ayrilanlar = $stmt3->fetchColumn() ?: 0;
        
        try {
            $sql_katilimci = "SELECT * FROM ziyaretciler WHERE (Ziyaret_Nedeni LIKE :etkinlik_adi)";
            $params_katilimci = ['etkinlik_adi' => $etkinlik_adi_param];
            if (!empty($search)) {
                $sql_katilimci .= " AND Ad_Soyad LIKE :search";
                $params_katilimci['search'] = "%$search%";
            }
            $sql_katilimci .= " ORDER BY Giris_Zamani DESC";
            $katilimci_sorgu = $pdo->prepare($sql_katilimci);
            $katilimci_sorgu->execute($params_katilimci);
            $katilimcilar = $katilimci_sorgu->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {}
    }
} 

if (!$detay_modu) {
    try {
        $toplam_etkinlik = $pdo->query("SELECT COUNT(*) FROM etkinlikler")->fetchColumn() ?: 0;
        $bu_ayki_etkinlik = $pdo->query("SELECT COUNT(*) FROM etkinlikler WHERE MONTH(Etkinlik_Tarihi) = MONTH(CURRENT_DATE()) AND YEAR(Etkinlik_Tarihi) = YEAR(CURRENT_DATE())")->fetchColumn() ?: 0;
        $toplam_katilimci = $pdo->query("SELECT COUNT(*) FROM ziyaretciler WHERE Ziyaret_Nedeni LIKE '%Gezisi%' OR Ziyaret_Nedeni LIKE '%Etkinlik%'")->fetchColumn() ?: 0;
    } catch (PDOException $e) {
        $toplam_etkinlik = $bu_ayki_etkinlik = $toplam_katilimci = 0;
    }
}

try {
    $pdo->exec("SET NAMES 'utf8mb4'");
    // Etkinlikleri çekerken oluşturan personelin adını almak için LEFT JOIN yapıyoruz
    $etkinlikler_sorgu = $pdo->query("SELECT e.*, p.Ad_Soyad as Olusturan_Personel FROM etkinlikler e LEFT JOIN personel p ON e.Olusturan_Personel_ID = p.Personel_ID ORDER BY e.Etkinlik_Tarihi DESC");
    $etkinlikler = $etkinlikler_sorgu->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $etkinlikler = []; }
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<style>
    :root { 
        --bg-white: #ffffff;
        --border-color: #f1f5f9;
        --text-main: #1e293b;
        --text-muted: #64748b;
        --gl-green: #0ea5e9; 
        --gl-brand: #10b981; 
        --gl-brand-dark: #059669;
        --gl-brand-light: #f0fdf4;
        --gl-border: #e2e8f0;
        --gl-bg-main: #f8fafc;
    }

    .history-container { 
        display: flex; 
        flex-direction: column; 
        gap: 20px; 
        font-family: 'Plus Jakarta Sans', sans-serif;
        padding: 10px;
        color: var(--text-main);
    }

    .stat-row { 
        display: grid; 
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); 
        gap: 20px; 
    }
    .stat-card { 
        background: var(--bg-white); 
        padding: 20px; 
        border-radius: 15px; 
        border: 1px solid var(--border-color); 
        display: flex; 
        align-items: center; 
        gap: 15px;
        box-shadow: 0 4px 15px rgba(15, 23, 42, 0.03);
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 25px rgba(15, 23, 42, 0.06);
    }
    .stat-icon { 
        width: 55px; 
        height: 55px; 
        border-radius: 12px; 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        font-size: 1.4rem; 
        flex-shrink: 0;
    }
    .stat-info small { 
        font-size: 0.85rem; 
        color: var(--text-muted); 
        display: block;
        margin-bottom: 4px;
    }
    .stat-info strong { 
        font-size: 1.6rem; 
        font-weight: 800; 
        color: var(--text-main); 
        display: block; 
    }

    .content-card { 
        background: var(--bg-white); 
        border-radius: 20px; 
        padding: 20px; 
        border: 1px solid var(--border-color); 
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        min-width: 0; 
        display: flex;
        flex-direction: column;
    }
    .card-title { 
        font-weight: 700; 
        color: var(--text-main); 
        display: flex; 
        align-items: center; 
        gap: 10px;
        font-size: 1.1rem;
        margin-bottom: 20px;
        margin-top: 0;
    }

    .info-card { 
        background: #fff; 
        border-radius: 20px; 
        padding: 20px; 
        border: 1px solid var(--border-color); 
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
        flex-wrap: wrap;
        gap: 20px; 
    }
    .info-badge-row { display: flex; flex-wrap: wrap; gap: 15px; align-items: center; margin-top: 10px; }
    .badge-date {
        background: #f1f5f9;
        color: #475569;
        padding: 6px 12px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .badge-personel {
        background: #e0f2fe;
        color: #0369a1;
        padding: 6px 12px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .etkinlik-grid { display: grid; grid-template-columns: 1fr 1.4fr; gap: 20px; align-items: start; }
    @media (max-width: 992px) { .etkinlik-grid { grid-template-columns: 1fr; } }

    .controls-wrapper { 
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
        flex-wrap: wrap; 
        gap: 20px; 
        background: #fff; 
        padding: 20px 24px; 
        border-radius: 16px; 
        border: 1px solid var(--gl-border); 
        box-shadow: 0 4px 15px rgba(15, 23, 42, 0.03);
    }
    .search-group { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
    .search-input { 
        padding: 10px 16px; 
        border-radius: 10px; 
        border: 1px solid var(--gl-border); 
        outline: none; 
        width: 260px; 
        font-size: 14px; 
        font-weight: 500; 
        background: #f8fafc; 
        transition: all 0.2s;
    }
    .search-input:focus { border-color: #3b82f6; background: #fff; }
    
    .btn-action { 
        height: 42px; 
        padding: 0 20px; 
        border-radius: 10px; 
        border: none; 
        font-weight: 600; 
        font-size: 13px; 
        cursor: pointer; 
        display: inline-flex; 
        align-items: center; 
        justify-content: center; 
        gap: 8px; 
        transition: all 0.2s ease; 
        text-decoration: none; 
        box-sizing: border-box;
    }
    .btn-action:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
    .btn-action:active { transform: translateY(0); }

    .btn-search { background: var(--text-main); color: white; }
    .btn-search:hover { background: #0f172a; }
    
    .btn-edit { background: #f59e0b; color: white; }
    .btn-edit:hover { background: #d97706; box-shadow: 0 4px 12px rgba(245, 158, 11, 0.2); }
    .btn-delete-trigger { background: #ef4444; color: white; }
    .btn-delete-trigger:hover { background: #dc2626; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2); }
    .btn-back { background: #64748b; color: white; }
    .btn-back:hover { background: #475569; }
    
    .export-group { display: flex; gap: 10px; align-items: center; }
    .btn-excel { background: #10b981; color: white; }
    .btn-excel:hover { background: #059669; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2); }
    .btn-pdf { background: #6366f1; color: white; }
    .btn-pdf:hover { background: #4f46e5; box-shadow: 0 4px 12px rgba(99, 102, 241, 0.2); }

    .form-control { 
        width: 100%; 
        padding: 12px 14px; 
        border: 1px solid var(--gl-border); 
        border-radius: 10px; 
        font-size: 0.9rem; 
        outline: none; 
        box-sizing: border-box; 
        color: var(--text-main); 
        background: #f8fafc;
        transition: all 0.2s;
    }
    .form-control:focus { border-color: #3b82f6; background: #fff; }
    .form-group { margin-bottom: 20px; display: flex; flex-direction: column; gap: 8px; }
    .form-group label { font-size: 0.85rem; font-weight: 600; color: #475569; }

    .etkinlik-item { 
        padding: 20px; 
        border: 1px solid var(--gl-border); 
        border-radius: 12px; 
        margin-bottom: 16px; 
        background: #f8fafc; 
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1); 
        position: relative; 
    }
    .etkinlik-item:hover { 
        border-color: var(--gl-brand); 
        background: #fff; 
        box-shadow: 0 10px 20px rgba(16, 185, 129, 0.05);
        transform: scale(1.01);
    }
    .etkinlik-main-click { text-decoration: none; color: inherit; display: block; }
    .etkinlik-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; margin-bottom: 10px; }
    .etkinlik-name { font-weight: 700; font-size: 1.05rem; color: var(--text-main); }
    .etkinlik-sub-info { display: flex; gap: 10px; margin-top: 8px; align-items: center; }
    .etkinlik-actions { display: flex; gap: 8px; justify-content: flex-end; margin-top: 14px; border-top: 1px dashed #e2e8f0; padding-top: 12px; }

    .main-table-card { 
        background: #fff; 
        border-radius: 16px; 
        border: 1px solid var(--gl-border); 
        overflow: hidden; 
        box-shadow: 0 4px 15px rgba(15, 23, 42, 0.03);
    }
    .custom-table { width: 100%; border-collapse: collapse; min-width: 800px; }
    .custom-table th { 
        background: #f8fafc; 
        text-align: left; 
        padding: 18px 20px; 
        color: var(--text-muted); 
        border-bottom: 2px solid #e2e8f0; 
        font-size: 11px; 
        text-transform: uppercase; 
        font-weight: 700; 
        letter-spacing: 0.5px;
    }
    .custom-table td { padding: 18px 20px; border-bottom: 1px solid #f1f5f9; font-size: 14px; font-weight: 500; color: #334155; }
    .custom-table tr:hover td { background-color: #f8fafc; }
    
    .time-badge { font-weight: 600; padding: 6px 10px; border-radius: 6px; font-size: 12px; display: inline-block; }
    .badge-in { background: #e0f2fe; color: #0369a1; }
    .badge-out { background: #f1f5f9; color: #475569; }
    
    .badge-durum { padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-block; }
    .durum-icerde { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
    .durum-ayrildi { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

    .gl-modal { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(15, 23, 42, 0.4); align-items: center; justify-content: center; backdrop-filter: blur(8px); }
    .gl-modal-content { background-color: #fff; padding: 35px; border-radius: 20px; width: 100%; max-width: 520px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15); position: relative; animation: modalFade 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
    .gl-modal-close { position: absolute; top: 25px; right: 25px; font-size: 22px; cursor: pointer; color: var(--text-muted); transition: color 0.2s; }
    .gl-modal-close:hover { color: var(--text-main); }
    @keyframes modalFade { from { transform: translateY(15px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

    @media print {
        @page { size: portrait; margin: 12mm; }
        body { background: #fff !important; font-family: Arial, sans-serif !important; width: 100% !important; }
        .sidebar, .topbar, .controls-wrapper, .stat-row, .etkinlik-grid, .btn-action, .etkinlik-actions, .info-card, form { display: none !important; }
        .history-container { padding: 0 !important; background: transparent !important; }
        .main-table-card { border: none !important; box-shadow: none !important; padding: 0 !important; margin: 0 !important; width: 100% !important; min-width: unset !important; overflow: visible !important; }
        .show-on-print { display: block !important; visibility: visible !important; margin-bottom: 25px !important; width: 100% !important; }
        .table-responsive { overflow: visible !important; width: 100% !important; }
        .custom-table { width: 100% !important; min-width: unset !important; table-layout: fixed !important; border-collapse: collapse !important; border: 1px solid #cbd5e1 !important; }
        .hide-on-pdf { display: none !important; width: 0px !important; padding: 0 !important; margin: 0 !important; visibility: hidden !important; }
        .custom-table th:nth-child(1), .custom-table td:nth-child(1) { width: 15% !important; text-align: center !important; }
        .custom-table th:nth-child(2), .custom-table td:nth-child(2) { width: 50% !important; text-align: left !important; }
        .custom-table th:nth-child(3), .custom-table td:nth-child(3) { width: 35% !important; text-align: left !important; }
        .custom-table th { background: #f1f5f9 !important; color: #0f172a !important; padding: 12px 10px !important; font-size: 12px !important; font-weight: bold !important; border: 1px solid #cbd5e1 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .custom-table td { padding: 12px 10px !important; font-size: 12px !important; border: 1px solid #cbd5e1 !important; word-wrap: break-word !important; white-space: normal !important; color: #334155 !important; background: transparent !important; }
        .custom-table td code { font-size: 12px !important; font-family: monospace !important; background: transparent !important; padding: 0 !important; }
    }
    .show-on-print { display: none; }
</style>

<div id="editModal" class="gl-modal">
    <div class="gl-modal-content">
        <span class="gl-modal-close" onclick="closeEditModal()">&times;</span>
        <h3 style="margin-top:0; margin-bottom:25px; font-weight:800; font-size: 1.3rem; display:flex; align-items:center; gap:10px;">
            <i class="fas fa-edit" style="color:#f59e0b;"></i> Etkinliği Düzenle
        </h3>
        <form action="" method="POST">
            <input type="hidden" id="edit_id" name="guncelle_id">
            <div class="form-group">
                <label for="edit_adi">Etkinlik Adı</label>
                <input type="text" id="edit_adi" name="etkinlik_adi" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="edit_tarihi">Etkinlik Tarihi</label>
                <input type="date" id="edit_tarihi" name="etkinlik_tarihi" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="edit_aciklama">Açıklama / Notlar</label>
                <textarea id="edit_aciklama" name="aciklama" class="form-control" rows="4"></textarea>
            </div>
            <button type="submit" name="etkinlik_guncelle" class="btn-action btn-edit" style="width:100%; height:46px; margin-top: 10px; border-radius: 12px;">
                <i class="fas fa-save"></i> Değişiklikleri Kaydet
            </button>
        </form>
    </div>
</div>

<div class="history-container">
    
    <?php if ($detay_modu): ?>
        <div class="info-card">
            <div>
                <div class="screen-only-label" style="font-size: 0.75rem; font-weight: 700; color: var(--gl-brand-dark); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 4px;">Aktif Etkinlik Raporu</div>
                <h2>
                    <i class="fas fa-calendar-day" style="color: #0ea5e9; margin-right: 6px;"></i> <?= htmlspecialchars($secilen_etkinlik['Etkinlik_Adi']) ?>
                </h2>
                <div class="info-badge-row">
                    <span class="badge-date"><i class="far fa-calendar-alt" style="color: var(--gl-brand)"></i> <?= date('d.m.Y', strtotime($secilen_etkinlik['Etkinlik_Tarihi'])) ?></span>
                    <?php if(!empty($secilen_etkinlik['Olusturan_Personel'])): ?>
                        <span class="badge-personel"><i class="far fa-user"></i> Oluşturan: <?= htmlspecialchars($secilen_etkinlik['Olusturan_Personel']) ?></span>
                    <?php endif; ?>
                    <?php if(!empty($secilen_etkinlik['Aciklama'])): ?>
                        <span style="font-size: 0.9rem; color: var(--text-muted); font-weight: 500;"><b>Açıklama:</b> <?= htmlspecialchars($secilen_etkinlik['Aciklama']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div style="display:flex; gap:10px; align-items:center;">
                <button type="button" class="btn-action btn-edit" onclick="openEditModal(<?= $secilen_etkinlik['Etkinlik_ID'] ?>, '<?= htmlspecialchars($secilen_etkinlik['Etkinlik_Adi'], ENT_QUOTES) ?>', '<?= $secilen_etkinlik['Etkinlik_Tarihi'] ?>', '<?= htmlspecialchars($secilen_etkinlik['Aciklama'], ENT_QUOTES) ?>')">
                    <i class="fas fa-edit"></i> Düzenle
                </button>
                <form action="" method="POST" style="display:inline-flex;" onsubmit="return confirm('Bu etkinliği silmek istediğinize emin misiniz?');">
                    <input type="hidden" name="sil_id" value="<?= $secilen_etkinlik['Etkinlik_ID'] ?>">
                    <button type="submit" name="etkinlik_sil" class="btn-action btn-delete-trigger"><i class="fas fa-trash"></i> Sil</button>
                </form>
                <a href="?sayfa=etkinlik" class="btn-action btn-back"><i class="fas fa-arrow-left"></i> Geri Dön</a>
            </div>
        </div>

        <div class="stat-row">
            <div class="stat-card">
                <div class="stat-icon" style="background: #eff6ff; color: #3b82f6;"><i class="fas fa-users"></i></div>
                <div class="stat-info"><small>Toplam Katılımcı</small><strong><?= $kart_toplam_katilimci; ?></strong></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: #ecfdf5; color: #10b981;"><i class="fas fa-door-open"></i></div>
                <div class="stat-info"><small>Şu An İçeride Olanlar</small><strong><?= $kart_aktif_iceride; ?></strong></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: #fef2f2; color: #ef4444;"><i class="fas fa-door-closed"></i></div>
                <div class="stat-info"><small>Ayrılan Katılımcılar</small><strong><?= $kart_ayrilanlar; ?></strong></div>
            </div>
        </div>

        <div class="controls-wrapper">
            <form method="GET" action="" class="search-group">
                <input type="hidden" name="sayfa" value="etkinlik">
                <input type="hidden" name="detay_id" value="<?= $detay_id ?>">
                <input type="text" name="search" class="search-input" placeholder="Katılımcı ismiyle ara..." value="<?= htmlspecialchars($search); ?>">
                <button type="submit" class="btn-action btn-search"><i class="fas fa-search"></i> Filtrele</button>
            </form>
            <div class="export-group">
                <button onclick="exportToExcel()" class="btn-action btn-excel"><i class="fas fa-file-excel"></i> Excel Verisi</button>
                <button onclick="exportToPDF()" class="btn-action btn-pdf"><i class="fas fa-file-pdf"></i> PDF Yazdır</button>
            </div>
        </div>

        <div class="main-table-card" id="printArea">
            <div class="show-on-print" style="padding-bottom: 15px; border-bottom: 2px solid #1e293b; font-family: Arial, sans-serif;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <img src="images/logo3.png" alt="GreenLog Logo" style="height: 45px; max-width: 180px; object-fit: contain;">
                    <div style="text-align: right; font-size: 11px; color: #64748b; font-weight: bold;">ETKİNLİK KATILIMCI LİSTESİ</div>
                </div>
                <table style="width: 100%; font-size: 11px; font-weight: 600; color: #1e293b;">
                    <tr>
                        <td style="padding: 2px 0;"><b>Etkinlik Adı:</b> <?= htmlspecialchars($secilen_etkinlik['Etkinlik_Adi']) ?></td>
                        <td style="text-align: right; padding: 2px 0;"><b>Rapor Tarihi:</b> <?= date('d.m.Y H:i') ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 2px 0;"><b>Etkinlik Tarihi:</b> <?= date('d.m.Y', strtotime($secilen_etkinlik['Etkinlik_Tarihi'])) ?></td>
                        <td style="text-align: right; padding: 2px 0;"><b>Toplam Kayıt:</b> <?= count($katilimcilar) ?> Kişi</td>
                    </tr>
                </table>
            </div>

            <div class="table-responsive">
                <table class="custom-table" id="historyTable">
                    <thead>
                        <tr>
                            <th style="text-align: center;">Sıra</th>
                            <th>Ad Soyad</th>
                            <th>TC Kimlik No</th>
                            <th class="hide-on-pdf">Giriş Saati</th>
                            <th class="hide-on-pdf">Çıkış Saati</th>
                            <th style="text-align: center;" class="hide-on-pdf">Durum</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($katilimcilar)): ?>
                            <?php $sira_no = 1; foreach ($katilimcilar as $k): 
                                $g_saat = date('d.m.Y H:i', strtotime($k['Giris_Zamani']));
                                $c_saat = ($k['Cikis_Zamani'] && $k['Cikis_Zamani'] != '0000-00-00 00:00:00') ? date('d.m.Y H:i', strtotime($k['Cikis_Zamani'])) : '-';
                                $masked_tc = (strlen($k['TC_Kimlik']) == 11) ? substr($k['TC_Kimlik'], 0, 3) . '*****' . substr($k['TC_Kimlik'], -3) : $k['TC_Kimlik'];
                            ?>
                                <tr>
                                    <td style="text-align: center; font-weight: 700;"><?= $sira_no++ ?></td>
                                    <td><span style="font-weight: 600;"><?= htmlspecialchars($k['Ad_Soyad']) ?></span></td>
                                    <td><code style="font-family: monospace; font-size: 13px;"><?= $masked_tc ?></code></td>
                                    <td class="hide-on-pdf"><span class='time-badge badge-in'><?= $g_saat ?></span></td>
                                    <td class="hide-on-pdf"><span class='time-badge badge-out'><?= $c_saat ?></span></td>
                                    <td style="text-align: center;" class="hide-on-pdf">
                                        <span class="badge-durum <?= (($k['Durum'] ?? '') == 'İçeride' || ($k['Durum'] ?? '') == 'Iceride') ? 'durum-icerde' : 'durum-ayrildi' ?>">
                                            <?= (($k['Durum'] ?? '') == 'İçeride' || ($k['Durum'] ?? '') == 'Iceride') ? 'İçeride' : 'Ayrıldı' ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" style="text-align: center; padding: 60px; color: var(--text-muted); font-weight: 500;">Etkinliğe ait katılımcı kaydı bulunamadı.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php else: ?>
        <div class="stat-row">
            <div class="stat-card">
                <div class="stat-icon" style="background: #eff6ff; color: #3b82f6;"><i class="fas fa-layer-group"></i></div>
                <div class="stat-info"><small>Toplam Etkinlik</small><strong><?= $toplam_etkinlik; ?></strong></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: #ecfdf5; color: #10b981;"><i class="fas fa-calendar-check"></i></div>
                <div class="stat-info"><small>Bu Ayki Etkinlikler</small><strong><?= $bu_ayki_etkinlik; ?></strong></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: #fff7ed; color: #f59e0b;"><i class="fas fa-users"></i></div>
                <div class="stat-info"><small>Sistem Genel Katılımcı</small><strong><?= $toplam_katilimci; ?></strong></div>
            </div>
        </div>

        <div class="etkinlik-grid">
            <div class="content-card">
                <div class="card-title"><i class="fas fa-calendar-plus" style="color: #10b981;"></i> Yeni Etkinlik Tanımla</div>
                <form action="" method="POST">
                    <div class="form-group">
                        <label for="etkinlik_adi">Etkinlik Adı</label>
                        <input type="text" id="etkinlik_adi" name="etkinlik_adi" class="form-control" placeholder="Örn: X Üniversitesi Teknik Gezisi" required>
                    </div>
                    <div class="form-group">
                        <label for="etkinlik_tarihi">Etkinlik Tarihi</label>
                        <input type="date" id="etkinlik_tarihi" name="etkinlik_tarihi" class="form-control" value="<?= $bugun; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="aciklama">Açıklama / Notlar</label>
                        <textarea id="aciklama" name="aciklama" class="form-control" placeholder="Etkinliğe dair eklemek istediğiniz detaylar..." rows="4"></textarea>
                    </div>
                    <button type="submit" name="etkinlik_olustur" class="btn-action btn-search" style="width:100%; justify-content:center; padding:12px; height: 46px; border-radius: 12px; background: #10b981;">
                        <i class="fas fa-plus-circle"></i> Etkinliği Oluştur
                    </button>
                </form>
            </div>

            <div class="content-card">
                <div class="card-title"><i class="fas fa-calendar-alt" style="color: #3b82f6;"></i> Sistemde Kayıtlı Etkinlikler</div>
                <div style="max-height: 520px; overflow-y: auto; padding-right: 5px;" class="custom-scroll">
                    <?php if (!empty($etkinlikler)): ?>
                        <?php foreach($etkinlikler as $e): ?>
                            <div class="etkinlik-item">
                                <a href="?sayfa=etkinlik&detay_id=<?= $e['Etkinlik_ID'] ?>" class="etkinlik-main-click">
                                    <div class="etkinlik-header">
                                        <div class="etkinlik-name">
                                            <i class="far fa-folder" style="color:#0ea5e9; margin-right:6px;"></i> 
                                            <?= htmlspecialchars($e['Etkinlik_Adi']) ?>
                                        </div>
                                        <span class="badge-date"><i class="far fa-clock"></i> <?= date('d.m.Y', strtotime($e['Etkinlik_Tarihi'])) ?></span>
                                    </div>
                                    <div style="font-size: 0.88rem; color: var(--text-muted); line-height: 1.6; text-align:left; padding-left: 22px;">
                                        <?= !empty($e['Aciklama']) ? nl2br(htmlspecialchars($e['Aciklama'])) : '<i>Herhangi bir açıklama girilmemiş.</i>' ?>
                                    </div>
                                    <div class="etkinlik-sub-info" style="padding-left: 22px;">
                                        <span style="font-size: 0.78rem; color:#475569; font-weight: 600; background:#f1f5f9; padding: 3px 8px; border-radius: 5px;">
                                            <i class="fas fa-user-edit" style="color: #64748b; font-size: 11px;"></i> Oluşturan: <?= !empty($e['Olusturan_Personel']) ? htmlspecialchars($e['Olusturan_Personel']) : 'Bilinmiyor' ?>
                                        </span>
                                    </div>
                                </a>
                                
                                <div class="etkinlik-actions">
                                    <button type="button" class="btn-action btn-edit" style="height:34px; padding: 0 14px; font-size:12px;" onclick="openEditModal(<?= $e['Etkinlik_ID'] ?>, '<?= htmlspecialchars($e['Etkinlik_Adi'], ENT_QUOTES) ?>', '<?= $e['Etkinlik_Tarihi'] ?>', '<?= htmlspecialchars($e['Aciklama'], ENT_QUOTES) ?>')">
                                        <i class="fas fa-edit"></i> Düzenle
                                    </button>
                                    <form action="" method="POST" style="display:inline-flex;" onsubmit="return confirm('Bu etkinliği silmek istediğinize emin misiniz?');">
                                        <input type="hidden" name="sil_id" value="<?= $e['Etkinlik_ID'] ?>">
                                        <button type="submit" name="etkinlik_sil" class="btn-action btn-delete-trigger" style="height:34px; padding: 0 14px; font-size:12px;"><i class="fas fa-trash"></i> Sil</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="padding:60px; text-align:center; color: var(--text-muted); font-weight: 500;">
                            <i class="fas fa-calendar-times" style="font-size: 2rem; display:block; margin-bottom:10px; color:#cbd5e1;"></i>
                            Henüz sistemde tanımlı bir etkinlik yok.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
    function openEditModal(id, adi, tarihi, aciklama) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_adi').value = adi;
        document.getElementById('edit_tarihi').value = tarihi;
        document.getElementById('edit_aciklama').value = aciklama;
        document.getElementById('editModal').style.display = 'flex';
    }

    function cancelEvent(e) {
        if (e.target === document.getElementById('editModal')) {
            closeEditModal();
        }
    }

    function closeEditModal() {
    }

    window.addEventListener('click', cancelEvent);

    function exportToExcel() {
        const table = document.getElementById("historyTable");
        if(!table) return;
        const html = table.outerHTML;
        const template = `<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40"><head><meta charset="UTF-8"></head><body>${html}</body></html>`;
        const blob = new Blob([template], { type: "application/vnd.ms-excel" });
        const a = document.createElement("a");
        a.href = URL.createObjectURL(blob);
        a.download = "Etkinlik_Katilimci_Listesi_" + new Date().toISOString().slice(0,10) + ".xls";
        a.click();
    }

    function exportToPDF() {
        window.print();
    }
</script>