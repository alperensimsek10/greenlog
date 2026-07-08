<?php
/**
 * GreenLog - Personel Yönetimi (v3.5 - Emerald Premium Edition)
 */

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// --- 1. DURUM GÜNCELLEME MANTIĞI ---
if (isset($_GET['islem']) && $_GET['islem'] == 'durum_degistir') {
    $id = intval($_GET['id']);
    $mevcut_durum = intval($_GET['mevcut']);
    $yeni_durum = ($mevcut_durum == 1) ? 0 : 1;

    $sorgu = $pdo->prepare("UPDATE personel SET Aktif_Mi = ? WHERE Personel_ID = ?");
    $sorgu->execute([$yeni_durum, $id]);

    echo "<script>window.location.href='panel.php?sayfa=personel';</script>";
    exit;
}

// --- 2. VERİLERİ ÇEKME ---
try {
    $toplam_personel = $pdo->query("SELECT COUNT(*) FROM personel")->fetchColumn();
    $aktif_personel = $pdo->query("SELECT COUNT(*) FROM personel WHERE Aktif_Mi = 1")->fetchColumn();
    $pasif_personel = $pdo->query("SELECT COUNT(*) FROM personel WHERE Aktif_Mi = 0")->fetchColumn();
    
    $personeller = $pdo->query("
        SELECT * FROM personel 
        ORDER BY 
            (CASE WHEN Rol = 'Admin' THEN 0 ELSE 1 END) ASC, 
            Ad_Soyad ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("<div style='padding:20px; color:red; font-family:sans-serif;'>Veritabanı Hatası: " . htmlspecialchars($e->getMessage()) . "</div>");
}
?>

<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />

<style>
    :root { 
        --gl-green: #10b981; 
        --gl-green-dark: #059669; 
        --gl-green-light: #f0fdf4;
        --gl-blue: #3b82f6;
        --gl-blue-light: #eff6ff;
        --gl-red: #ef4444;
        --gl-red-light: #fef2f2;
        --gl-bg: #f8fafc;
        --gl-text: #0f172a;
        --gl-text-muted: #64748b;
        --gl-border: #e2e8f0;
        --gl-radius-lg: 16px;
        --gl-radius-md: 12px;
        --gl-shadow: 0 10px 15px -3px rgba(15, 23, 42, 0.04), 0 4px 6px -4px rgba(15, 23, 42, 0.04);
        --gl-shadow-hover: 0 20px 25px -5px rgba(15, 23, 42, 0.08), 0 8px 10px -6px rgba(15, 23, 42, 0.08);
    }

    .gl-container { font-family: 'Plus Jakarta Sans', sans-serif; max-width: 1400px; margin: 0 auto; padding: 25px; background: var(--gl-bg); }

    /* --- KPI Grid --- */
    .kpi-grid { 
        display: grid; 
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); 
        gap: 24px; margin-bottom: 35px; 
    }
    .kpi-card { 
        background: #fff; padding: 24px; border-radius: var(--gl-radius-lg); 
        display: flex; align-items: center; gap: 20px;
        box-shadow: var(--gl-shadow); border: 1px solid var(--gl-border);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .kpi-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--gl-shadow-hover);
    }
    .kpi-icon { width: 56px; height: 56px; border-radius: var(--gl-radius-md); display: flex; align-items: center; justify-content: center; font-size: 1.4rem; transition: 0.3s; }
    
    .kpi-total { border-left: 5px solid var(--gl-blue); }
    .kpi-total .kpi-icon { background: var(--gl-blue-light); color: var(--gl-blue); }
    
    .kpi-active { border-left: 5px solid var(--gl-green); }
    .kpi-active .kpi-icon { background: var(--gl-green-light); color: var(--gl-green); }

    .kpi-passive { border-left: 5px solid var(--gl-red); }
    .kpi-passive .kpi-icon { background: var(--gl-red-light); color: var(--gl-red); }

    /* --- Başlık Alanı --- */
    .list-header { 
        display: flex; justify-content: space-between; align-items: center; 
        margin-bottom: 25px; flex-wrap: wrap; gap: 15px;
    }

    .btn-add { 
        background: var(--gl-green); color: white !important; padding: 12px 24px; 
        border-radius: var(--gl-radius-md); text-decoration: none; font-weight: 700; font-size: 0.875rem;
        display: inline-flex; align-items: center; gap: 10px; transition: all 0.3s ease;
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
    }
    .btn-add:hover { background: var(--gl-green-dark); transform: translateY(-1px); box-shadow: 0 6px 16px rgba(16, 185, 129, 0.3); }

    /* --- Tablo Kutusu --- */
    .panel-box { background: #fff; border-radius: var(--gl-radius-lg); padding: 10px 24px 24px 24px; border: 1px solid var(--gl-border); box-shadow: var(--gl-shadow); }
    
    .gl-table { width: 100%; border-collapse: separate; border-spacing: 0 10px; }
    .gl-table th { text-align: left; padding: 16px 20px; color: var(--gl-text-muted); font-size: 0.75rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; }
    .gl-table td { padding: 16px 20px; background: #fff; border-top: 1px solid var(--gl-border); border-bottom: 1px solid var(--gl-border); transition: all 0.2s ease; }
    
    .gl-table td:first-child { border-left: 1px solid var(--gl-border); border-radius: var(--gl-radius-md) 0 0 var(--gl-radius-md); }
    .gl-table td:last-child { border-right: 1px solid var(--gl-border); border-radius: 0 var(--gl-radius-md) var(--gl-radius-md) 0; }

    .gl-table tbody tr { transition: all 0.2s ease; }
    .gl-table tbody tr:hover td { background: #f8fafc; border-top-color: #cbd5e1; border-bottom-color: #cbd5e1; }
    .gl-table tbody tr:hover td:first-child { border-left-color: #cbd5e1; }
    .gl-table tbody tr:hover td:last-child { border-right-color: #cbd5e1; }

    /* Pasif Satır Stili */
    .gl-table tr.row-passive { opacity: 0.55; }
    .gl-table tr.row-passive td { background: #fdfefe; }

    /* Rol Rozetleri */
    .badge-role { padding: 6px 12px; border-radius: 8px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; display: inline-flex; align-items: center; gap: 6px; }
    .role-admin { background: var(--gl-blue-light); color: #1e40af; border: 1px solid #dbeafe; }
    .role-staff { background: var(--gl-green-light); color: var(--gl-green-dark); border: 1px solid #d1fae5; }

    /* Tıklanabilir Durum Switch Yapısı */
    .switch-container { display: inline-block; position: relative; width: 44px; height: 24px; }
    .switch-container input { opacity: 0; width: 0; height: 0; }
    .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .3s; border-radius: 24px; }
    .slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 4px; bottom: 4px; background-color: white; transition: .3s; border-radius: 50%; box-shadow: 0 1px 3px rgba(0,0,0,0.15); }
    input:checked + .slider { background-color: var(--gl-green); }
    input:checked + .slider:before { transform: translateX(20px); }

    /* Butonlar */
    .edit-btn { width: 38px; height: 38px; display: inline-flex; align-items: center; justify-content: center; color: var(--gl-text-muted); background: #f1f5f9; border-radius: 10px; text-decoration: none; transition: all 0.2s ease; border: 1px solid transparent; }
    .edit-btn:hover { color: var(--gl-blue); background: var(--gl-blue-light); border-color: #bfdbfe; transform: scale(1.05); }

    /* MOBİL RESPONSIVE TASARIM */
    @media (max-width: 768px) {
        .gl-container { padding: 15px; }
        .list-header h3 { width: 100%; }
        .btn-add { width: 100%; justify-content: center; padding: 14px; }
        .panel-box { padding: 10px 15px 15px 15px; }
        
        .gl-table thead { display: none; }
        .gl-table, .gl-table tbody, .gl-table tr, .gl-table td { display: block; width: 100%; }
        .gl-table tr { margin-bottom: 20px; border: 1px solid var(--gl-border); border-radius: var(--gl-radius-lg); overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.02); background: #fff; }
        .gl-table td { border: none !important; padding: 12px 20px; position: relative; text-align: right; border-radius: 0 !important; box-sizing: border-box; }
        .gl-table td:not(:last-child) { border-bottom: 1px dashed #f1f5f9 !important; }
        
        .gl-table td::before {
            content: attr(data-label);
            position: absolute; left: 20px; top: 50%; transform: translateY(-50%);
            font-size: 0.75rem; font-weight: 700; color: var(--gl-text-muted); text-transform: uppercase;
        }
        
        .gl-table td:first-child { text-align: left; background: #f8fafc; padding-top: 16px; padding-bottom: 16px; border-bottom: 1px solid var(--gl-border) !important; }
        .gl-table td:first-child::before { display: none; }
        .gl-table tbody tr:hover td { background: #fff; }
        .gl-table tbody tr:hover td:first-child { background: #f8fafc; }
    }
</style>

<div class="gl-container animate__animated animate__fadeIn">
    
    <div class="kpi-grid">
        <div class="kpi-card kpi-total">
            <div class="kpi-icon"><i class="fa-solid fa-users"></i></div>
            <div class="kpi-info">
                <h3 style="margin:0; font-size:1.6rem; font-weight:800; color:var(--gl-text);"><?php echo $toplam_personel; ?></h3>
                <p style="margin:4px 0 0 0; font-size:0.75rem; color:var(--gl-text-muted); font-weight:700; text-transform:uppercase; letter-spacing: 0.5px;">Toplam Personel</p>
            </div>
        </div>

        <div class="kpi-card kpi-active">
            <div class="kpi-icon"><i class="fa-solid fa-user-check"></i></div>
            <div class="kpi-info">
                <h3 style="margin:0; font-size:1.6rem; font-weight:800; color:var(--gl-text);"><?php echo $aktif_personel; ?></h3>
                <p style="margin:4px 0 0 0; font-size:0.75rem; color:var(--gl-text-muted); font-weight:700; text-transform:uppercase; letter-spacing: 0.5px;">Aktif Erişim</p>
            </div>
        </div>

        <div class="kpi-card kpi-passive">
            <div class="kpi-icon"><i class="fa-solid fa-user-slash"></i></div>
            <div class="kpi-info">
                <h3 style="margin:0; font-size:1.6rem; font-weight:800; color:var(--gl-text);"><?php echo $pasif_personel; ?></h3>
                <p style="margin:4px 0 0 0; font-size:0.75rem; color:var(--gl-text-muted); font-weight:700; text-transform:uppercase; letter-spacing: 0.5px;">Pasif / Kısıtlı</p>
            </div>
        </div>
    </div>

    <div class="list-header">
        <h3 style="margin:0; font-weight: 800; color: var(--gl-text); font-size: 1.35rem; display: flex; align-items:center; gap:12px;">
            <span style="background: var(--gl-green); width:6px; height:26px; border-radius:3px; display:inline-block;"></span>
            Personel Listesi
        </h3>
        <a href="panel.php?sayfa=personel_ekle" class="btn-add">
            <i class="fa-solid fa-plus"></i> Yeni Personel Ekle
        </a>
    </div>

    <div class="panel-box">
        <table class="gl-table">
            <thead>
                <tr>
                    <th>Personel</th>
                    <th>Ünvan</th>
                    <th>Yetki Seviyesi</th>
                    <th>Durum</th>
                    <th style="text-align: right;">İşlemler</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($personeller as $p): 
                    $isAdmin = ($p['Rol'] == 'Admin');
                    $isAktif = ($p['Aktif_Mi'] == 1);
                ?>
                <tr class="<?php echo !$isAktif ? 'row-passive' : ''; ?>">
                    <td>
                        <div style="display: flex; align-items: center; gap: 14px;">
                            <div style="width: 42px; height: 42px; background: var(--gl-green-light); border: 1px solid #d1fae5; border-radius: var(--gl-radius-md); display: flex; align-items: center; justify-content: center; font-weight: 800; color: var(--gl-green-dark); font-size: 1rem;">
                                <?php echo mb_substr($p['Ad_Soyad'], 0, 1, 'UTF-8'); ?>
                            </div>
                            <div>
                                <div style="font-weight: 700; color: var(--gl-text); font-size: 0.95rem;"><?php echo htmlspecialchars($p['Ad_Soyad']); ?></div>
                                <div style="font-size: 0.7rem; color: var(--gl-text-muted); font-weight: 600; margin-top: 2px;">TC: <?php echo htmlspecialchars($p['TC_No']); ?></div>
                            </div>
                        </div>
                    </td>
                    
                    <td data-label="Ünvan">
                        <span style="font-weight: 600; color: #475569; font-size: 0.9rem;"><?php echo htmlspecialchars($p['Unvan']); ?></span>
                    </td>
                    
                    <td data-label="Yetki">
                        <span class="badge-role <?php echo $isAdmin ? 'role-admin' : 'role-staff'; ?>">
                            <i class="<?php echo $isAdmin ? 'fa-solid fa-shield-halved' : 'fa-solid fa-user'; ?>"></i>
                            <?php echo htmlspecialchars($p['Rol']); ?>
                        </span>
                    </td>
                    
                    <td data-label="Durum">
                        <label class="switch-container" onclick="DurumDegistir(event, <?php echo $p['Personel_ID']; ?>, <?php echo $p['Aktif_Mi']; ?>)">
                            <input type="checkbox" <?php echo $isAktif ? 'checked' : ''; ?> readonly>
                            <span class="slider"></span>
                        </label>
                    </td>
                    
                    <td style="text-align: right;">
                        <a href="panel.php?sayfa=personel_duzenle&id=<?php echo $p['Personel_ID']; ?>" class="edit-btn" title="Düzenle">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function DurumDegistir(event, id, mevcutDurum) {
    event.preventDefault(); // Label çift tıklama hatasını önler
    
    const mesaj = mevcutDurum == 1 
        ? "Bu personelin erişim yetkisini askıya almak istediğinize emin misiniz?" 
        : "Bu personele tekrar erişim yetkisi vermek istediğinize emin misiniz?";
        
    if(confirm(mesaj)) {
        window.location.href = 'panel.php?sayfa=personel&islem=durum_degistir&id=' + id + '&mevcut=' + mevcutDurum;
    }
}
</script>