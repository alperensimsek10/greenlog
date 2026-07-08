<?php
/**
 * GreenLog - Yeni Ekipman Kaydı (Sera Seçimli & Dinamik Geri Dönüşlü)
 */
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Mevcut seraları çek (Select menüsü için)
$seralar = $pdo->query("SELECT Sera_ID, Sera_Adi FROM sera ORDER BY Sera_Adi ASC")->fetchAll();

// URL'den kökeni ve eğer sera detayından gelindiyse ilgili Sera ID'sini alıyoruz
$origin = 'ekipmanlar';
$selected_sera_id = 0;

if (isset($_GET['from']) && $_GET['from'] === 'sera_detay') {
    $origin = 'sera_detay';
    if (isset($_GET['sera_id'])) {
        $selected_sera_id = (int)$_GET['sera_id'];
    }
}

if (isset($_POST['kaydet'])) {
    $ad = $_POST['ekipman_adi'];
    $sn = $_POST['seri_no'];
    $kat = $_POST['kategori'];
    $durum = $_POST['durum'];
    $sera_id = $_POST['sera_id']; 

    $post_origin = isset($_POST['form_origin']) ? $_POST['form_origin'] : 'ekipmanlar';

    $stmt = $pdo->prepare("INSERT INTO ekipman (Ekipman_Adi, Seri_No, Kategori, Durum, Sera_ID) VALUES (?, ?, ?, ?, ?)");
    if ($stmt->execute([$ad, $sn, $kat, $durum, $sera_id])) {
        
        // Kayıt başarılı olduğunda yönlendirilecek hedef rotayı belirliyoruz
        if ($post_origin === 'sera_detay' && $sera_id > 0) {
            $yonlendirilecek_url = "panel.php?sayfa=sera_detay&id=" . $sera_id;
        } else {
            $yonlendirilecek_url = "panel.php?sayfa=ekipmanlar";
        }

        echo "<script>alert('Ekipman başarıyla eklendi.'); window.location='" . $yonlendirilecek_url . "';</script>";
        exit;
    }
}
?>

<style>
    .form-card { background: #ffffff; border-radius: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); border: 1px solid #f1f5f9; padding: 40px; max-width: 800px; margin: 30px auto 0 auto; }
    .input-group { margin-bottom: 20px; }
    .input-label { display: block; font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 700; font-size: 0.9rem; color: #1e293b; margin-bottom: 8px; }
    .form-control-custom { width: 100%; padding: 14px 16px; border-radius: 12px; border: 2px solid #f1f5f9; background: #f8fafc; font-family: 'Inter', sans-serif; font-size: 1rem; transition: all 0.3s ease; outline: none; box-sizing: border-box; }
    .form-control-custom:focus { border-color: #10b981; background: #ffffff; box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1); }
    .btn-submit { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; border: none; padding: 16px 32px; border-radius: 14px; font-weight: 800; width: 100%; cursor: pointer; transition: 0.3s; display: flex; align-items: center; justify-content: center; gap: 10px; font-size: 1rem; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2); }
    .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(16, 185, 129, 0.3); filter: brightness(1.1); }
    .form-icon { width: 45px; height: 45px; background: rgba(16, 185, 129, 0.1); color: #10b981; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; margin-bottom: 20px; }
</style>

<div class="form-card animate__animated animate__fadeInUp">
    <div class="form-icon"><i class="fas fa-plus"></i></div>
    <h2 style="margin:0 0 10px 0; color:#0f172a; font-weight: 800;">Yeni Ekipman Kaydı</h2>
    <p style="color:#64748b; margin-bottom:30px;">Sisteme yeni bir teknik donanım ekleyerek takibe başlayın.</p>

    <form method="POST">
        <input type="hidden" name="form_origin" value="<?= htmlspecialchars($origin) ?>">

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:25px;">
            
            <div class="input-group" style="grid-column: span 2;">
                <label class="input-label">Ekipman Adı</label>
                <input type="text" name="ekipman_adi" class="form-control-custom" placeholder="Örn: Otomatik Sulama Kontrol Ünitesi" required autocomplete="off">
            </div>

            <div class="input-group">
                <label class="input-label">Bağlı Olduğu Sera</label>
                <?php if ($selected_sera_id > 0): ?>
                    <select class="form-control-custom" style="background-color: #f1f5f9; cursor: not-allowed;" disabled>
                        <?php foreach($seralar as $s): ?>
                            <option value="<?= $s['Sera_ID'] ?>" <?= $s['Sera_ID'] == $selected_sera_id ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['Sera_Adi']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="sera_id" value="<?= $selected_sera_id ?>">
                <?php else: ?>
                    <select name="sera_id" class="form-control-custom" required>
                        <option value="">-- Sera Seçin --</option>
                        <?php foreach($seralar as $s): ?>
                            <option value="<?= $s['Sera_ID'] ?>"><?= htmlspecialchars($s['Sera_Adi']) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>

            <div class="input-group">
                <label class="input-label">Seri Numarası</label>
                <input type="text" name="seri_no" class="form-control-custom" placeholder="SN-100200300" required autocomplete="off">
            </div>

            <div class="input-group">
                <label class="input-label">Kategori</label>
                <select name="kategori" class="form-control-custom">
                    <option value="Sensör">Sensör</option>
                    <option value="Pompa">Pompa</option>
                    <option value="Aydınlatma">Aydınlatma</option>
                    <option value="İklimlendirme">İklimlendirme</option>
                    <option value="Diğer">Diğer</option>
                </select>
            </div>

            <div class="input-group">
                <label class="input-label">Başlangıç Durumu</label>
                <select name="durum" class="form-control-custom">
                    <option value="Aktif">Aktif (Hemen Kullanıma Başla)</option>
                    <option value="Arızalı">Arızalı (Onarım Gerekli)</option>
                    <option value="Bakımda">Bakımda (Kontrol Aşamasında)</option>
                </select>
            </div>

        </div>

        <div style="margin-top: 20px;">
            <button type="submit" name="kaydet" class="btn-submit">
                <i class="fas fa-save"></i> Envantere Kaydet
            </button>
        </div>
    </form>
</div>