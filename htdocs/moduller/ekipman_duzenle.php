<?php
/**
 * GreenLog - Ekipman Düzenleme Paneli (v3.2 - Fixed Absolute Redirect)
 */

// 1. Güvenlik Filtresi ve Ekipman ID Kontrolü
$Ekipman_ID = 0;
if (isset($_GET['id'])) {
    $Ekipman_ID = (int)$_GET['id'];
}

if ($Ekipman_ID <= 0) {
    echo '<div style="padding:60px 20px; text-align:center; color:#ef4444; font-family:\'Plus Jakarta Sans\',sans-serif; background:white; border-radius:24px; margin:20px; box-shadow: 0 20px 40px rgba(0,0,0,0.05); border:1px solid #f1f5f9;">
            <i class="fas fa-exclamation-triangle fa-4x" style="margin-bottom:20px; color:#f59e0b;"></i><br>
            <h2 style="margin:0; color:#0f172a; font-weight:800;">Geçersiz Ekipman ID</h2>
            <p style="color:#64748b; margin-top:10px; margin-bottom:20px;">Lütfen geçerli bir ekipman seçerek tekrar deneyin.</p>
            <a href="panel.php?sayfa=ekipmanlar" style="color:white; background:#10b981; padding:12px 24px; border-radius:12px; font-weight:700; text-decoration:none; display:inline-block;">← Listeye Dön</a>
          </div>';
    return; 
}

// URL'den kökeni ve eğer varsa taşınan Sera ID'sini alıyoruz
$origin = 'ekipmanlar';
$target_sera_id = 0;

if (isset($_GET['from']) && $_GET['from'] === 'sera_detay') {
    $origin = 'sera_detay';
    if (isset($_GET['sera_id'])) {
        $target_sera_id = (int)$_GET['sera_id'];
    }
}

try {
    // 2. Mevcut Ekipman Verisini Çekme
    $sorgu = $pdo->prepare("SELECT * FROM ekipman WHERE Ekipman_ID = ?");
    $sorgu->execute([$Ekipman_ID]);
    $ekipman = $sorgu->fetch(PDO::FETCH_ASSOC);

    if (!$ekipman) {
        echo '<div style="padding:50px; text-align:center; color:#ef4444; font-family:sans-serif;"><h3>Ekipman bulunamadı veya silinmiş.</h3></div>';
        return;
    }

    // Eğer linkten sera_id gelmediyse veritabanından yedek olarak oku
    if ($target_sera_id <= 0 && isset($ekipman['Sera_ID'])) {
        $target_sera_id = (int)$ekipman['Sera_ID'];
    }

    // İptal butonunun linki
    $iptal_url = ($origin === 'sera_detay' && $target_sera_id > 0) ? "panel.php?sayfa=sera_detay&id=" . $target_sera_id : "panel.php?sayfa=ekipmanlar";

    // 3. --- VERİ GÜNCELLEME İŞLEMİ (POST) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ekipman_guncelle'])) {
        $ekipman_adi = trim($_POST['ekipman_adi']);
        $seri_no = trim($_POST['seri_no']);
        $durum = $_POST['durum'] === 'Aktif' ? 'Aktif' : 'Pasif';
        
        $post_origin = isset($_POST['form_origin']) ? $_POST['form_origin'] : 'ekipmanlar';
        $post_sera_id = isset($_POST['form_sera_id']) ? (int)$_POST['form_sera_id'] : 0;

        if (!empty($ekipman_adi)) {
            $guncelle = $pdo->prepare("UPDATE ekipman SET Ekipman_Adi = ?, Seri_No = ?, Durum = ? WHERE Ekipman_ID = ?");
            $guncelle->execute([$ekipman_adi, $seri_no, $durum, $Ekipman_ID]);
            
            // Yönlendirme rotasını belirle
            if ($post_origin === 'sera_detay' && $post_sera_id > 0) {
                $yonlendirilecek_url = "panel.php?sayfa=sera_detay&id=" . $post_sera_id;
            } else {
                $yonlendirilecek_url = "panel.php?sayfa=ekipmanlar";
            }

            // JavaScript ile yönlendirme
            echo '<script>window.location.href = "' . $yonlendirilecek_url . '";</script>';
            exit;
        } else {
            $hata = "Ekipman adı boş bırakılamaz!";
        }
    }

    // Sera listesini çekme (Seçenek kutusu için)
    $seralar_sorgu = $pdo->query("SELECT Sera_ID, Sera_Adi FROM sera ORDER BY Sera_Adi ASC");
    $seralar = $seralar_sorgu->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    exit("Veritabanı hatası: " . htmlspecialchars($e->getMessage()));
}
?>

<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />

<style>
    :root {
        --gl-green: #10b981;
        --gl-blue: #3b82f6;
        --gl-text: #0f172a;
        --gl-text-muted: #64748b;
        --gl-border: #e2e8f0;
        --gl-shadow: 0 10px 30px rgba(15, 23, 42, 0.04);
    }
    .ed-wrapper { font-family: 'Plus Jakarta Sans', sans-serif; color: var(--gl-text); max-width: 600px; margin: 30px auto; padding: 15px; }
    .ed-card { background: white; border-radius: 24px; padding: 35px; box-shadow: var(--gl-shadow); border: 1px solid var(--gl-border); }
    .ed-title { font-size: 1.4rem; font-weight: 800; margin-top: 0; margin-bottom: 25px; display: flex; align-items: center; gap: 12px; color: #0f172a; }
    .form-group { margin-bottom: 20px; }
    .form-group label { display: block; font-weight: 700; font-size: 0.88rem; color: #334155; margin-bottom: 8px; }
    .form-control { width: 100%; padding: 12px 16px; border-radius: 12px; border: 1px solid var(--gl-border); font-family: inherit; font-size: 0.95rem; box-sizing: border-box; transition: all 0.2s; color: var(--gl-text); }
    .form-control:focus { outline: none; border-color: var(--gl-blue); box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1); }
    .btn-group { display: flex; gap: 15px; margin-top: 30px; }
    .btn-submit { background: var(--gl-blue); color: white; border: none; padding: 14px 24px; border-radius: 12px; font-weight: 700; font-size: 0.95rem; cursor: pointer; flex: 2; transition: all 0.2s; display: inline-flex; align-items: center; justify-content: center; gap: 8px; }
    .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(59, 130, 246, 0.2); }
    .btn-cancel { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; padding: 14px 24px; border-radius: 12px; font-weight: 700; font-size: 0.95rem; text-decoration: none; text-align: center; flex: 1; transition: all 0.2s; }
    .btn-cancel:hover { background: #e2e8f0; }
    .alert-danger { background: #fef2f2; border: 1px solid rgba(239, 68, 68, 0.2); color: #ef4444; padding: 14px; border-radius: 12px; font-weight: 600; font-size: 0.9rem; margin-bottom: 20px; }
</style>

<div class="ed-wrapper animate__animated animate__fadeInUp animate__fast">
    <div class="ed-card">
        <h2 class="ed-title">
            <i class="fas fa-microchip" style="color: var(--gl-blue);"></i>
            Ekipman Düzenle
        </h2>

        <?php if (isset($hata)): ?>
            <div class="alert-danger"><i class="fas fa-exclamation-circle"></i> <?= $hata ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="form_origin" value="<?= htmlspecialchars($origin) ?>">
            <input type="hidden" name="form_sera_id" value="<?= $target_sera_id ?>">
            
            <div class="form-group">
                <label>Ekipman Adı</label>
                <input type="text" name="ekipman_adi" class="form-control" value="<?= htmlspecialchars($ekipman['Ekipman_Adi']) ?>" required autocomplete="off">
            </div>

            <div class="form-group">
                <label>Seri Numarası (S/N)</label>
                <input type="text" name="seri_no" class="form-control" value="<?= htmlspecialchars($ekipman['Seri_No']) ?>" autocomplete="off">
            </div>

            <div class="form-group">
                <label>Bağlı Olduğu Sera</label>
                <select class="form-control" disabled style="background-color: #f8fafc; cursor: not-allowed;">
                    <?php foreach ($seralar as $s): ?>
                        <option value="<?= $s['Sera_ID'] ?>" <?= $s['Sera_ID'] == $ekipman['Sera_ID'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['Sera_Adi']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small style="color: var(--gl-text-muted); font-size: 0.75rem; margin-top: 4px; display: block;">Ekipmanların sera lokasyonu altyapı güvenliği nedeniyle değiştirilemez.</small>
            </div>

            <div class="form-group">
                <label>Çalışma Durumu</label>
                <select name="durum" class="form-control">
                    <option value="Aktif" <?= $ekipman['Durum'] === 'Aktif' ? 'selected' : '' ?>>Aktif (Çalışıyor)</option>
                    <option value="Pasif" <?= $ekipman['Durum'] === 'Pasif' ? 'selected' : '' ?>>Pasif (Durduruldu)</option>
                </select>
            </div>

            <div class="btn-group">
                <a href="<?= $iptal_url ?>" class="btn-cancel">İptal</a>
                <button type="submit" name="ekipman_guncelle" class="btn-submit">
                    <i class="fas fa-save"></i> Değişiklikleri Kaydet
                </button>
            </div>
        </form>
    </div>
</div>