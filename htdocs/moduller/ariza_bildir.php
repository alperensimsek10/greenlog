<?php
/**
 * GreenLog - Gelişmiş Arıza Bildirim Sayfası (v3.1 - Dynamic Intelligent Redirect)
 */
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Güvenlik: Oturum kontrolü
if (!isset($_SESSION['Personel_ID'])) { exit("Erişim Engellendi"); }
$kullanici_id = $_SESSION['Personel_ID'];

// URL Köken Kontrolleri
$origin = 'ekipmanlar';
$target_sera_id = 0;
$preselected_ekipman_id = 0;

if (isset($_GET['from']) && $_GET['from'] === 'sera_detay') {
    $origin = 'sera_detay';
    if (isset($_GET['sera_id'])) {
        $target_sera_id = (int)$_GET['sera_id'];
    }
}

if (isset($_GET['ekipman_id'])) {
    $preselected_ekipman_id = (int)$_GET['ekipman_id'];
}

// Geri Dönüş ve İptal Link Rotaları
$iptal_url = ($origin === 'sera_detay' && $target_sera_id > 0) ? "panel.php?sayfa=sera_detay&id=" . $target_sera_id : "panel.php?sayfa=ekipmanlar";

// --- VERİ KAYIT İŞLEMİ ---
if (isset($_POST['ariza_kaydet'])) {
    $e_id = $_POST['ekipman_id'];
    $not = trim($_POST['ariza_notu']);
    $post_origin = isset($_POST['form_origin']) ? $_POST['form_origin'] : 'ekipmanlar';
    $post_sera_id = isset($_POST['form_sera_id']) ? (int)$_POST['form_sera_id'] : 0;
    
    try {
        $pdo->beginTransaction();
        
        // 1. Ekipman durumunu 'Arızalı' yap
        $pdo->prepare("UPDATE ekipman SET Durum = 'Arızalı' WHERE Ekipman_ID = ?")->execute([$e_id]);
        
        // 2. Log kaydı oluştur
        $pdo->prepare("INSERT INTO ekipman_log (Ekipman_ID, Personel_ID, Alis_Tarihi, Teslim_Tarihi, Gun_Sonu_Raporu) 
                       VALUES (?, ?, NOW(), NULL, ?)")
            ->execute([$e_id, $kullanici_id, "ARIZA BİLDİRİLDİ: " . $not]);
            
        $pdo->commit();

        // Rota belirleme
        if ($post_origin === 'sera_detay' && $post_sera_id > 0) {
            $yonlendirilecek_url = "panel.php?sayfa=sera_detay&id=" . $post_sera_id;
        } else {
            $yonlendirilecek_url = "panel.php?sayfa=ekipmanlar";
        }

        echo "<script>alert('Arıza kaydı açıldı ve cihaz takibe alındı.'); window.location='" . $yonlendirilecek_url . "';</script>";
        exit;
    } catch(Exception $e) {
        $pdo->rollBack();
        echo "<div style='padding:20px; background:#fee2e2; color:#b91c1c;'>Hata: " . $e->getMessage() . "</div>";
    }
}
?>

<style>
    .ariza-card {
        max-width: 650px;
        margin: 40px auto;
        background: #ffffff;
        border-radius: 24px;
        box-shadow: 0 20px 40px rgba(0,0,0,0.08);
        border: 1px solid #f1f5f9;
        overflow: hidden;
        animation: fadeInUp 0.5s ease;
    }
    .ariza-header {
        background: #fef2f2;
        padding: 30px;
        border-bottom: 1px solid #fee2e2;
        text-align: center;
    }
    .ariza-body {
        padding: 40px;
    }
    .form-label {
        display: block;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 10px;
        font-size: 0.95rem;
    }
    .input-style {
        width: 100%;
        padding: 15px;
        border-radius: 12px;
        border: 2px solid #f1f5f9;
        background: #f8fafc;
        font-size: 1rem;
        transition: all 0.3s ease;
        outline: none;
        box-sizing: border-box;
    }
    .input-style:focus {
        border-color: #ef4444;
        background: #fff;
        box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.1);
    }
    .btn-row {
        display: flex;
        gap: 15px;
        margin-top: 35px;
    }
    .btn-cancel {
        flex: 1;
        padding: 16px;
        background: #f1f5f9;
        color: #64748b;
        text-align: center;
        text-decoration: none;
        border-radius: 14px;
        font-weight: 700;
        transition: 0.3s;
    }
    .btn-cancel:hover { background: #e2e8f0; color: #1e293b; }
    
    .btn-save {
        flex: 2;
        padding: 16px;
        background: #ef4444;
        color: white;
        border: none;
        border-radius: 14px;
        font-weight: 800;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        transition: 0.3s;
    }
    .btn-save:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(239, 68, 68, 0.3);
        filter: brightness(1.1);
    }
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>

<div class="detail-container">
    <div class="ariza-card">
        <div class="ariza-header">
            <div style="width: 60px; height: 60px; background: #ef4444; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; font-size: 24px; box-shadow: 0 8px 16px rgba(239, 68, 68, 0.2);">
                <i class="fas fa-tools"></i>
            </div>
            <h2 style="margin: 0; color: #991b1b; font-weight: 800; letter-spacing: -0.5px;">Yeni Arıza Bildirimi</h2>
            <p style="color: #ef4444; margin: 5px 0 0; font-weight: 600; font-size: 0.9rem;">Cihaz otomatik olarak arızalı konumuna alınacaktır.</p>
        </div>

        <div class="ariza-body">
            <form method="POST">
                <input type="hidden" name="form_origin" value="<?= htmlspecialchars($origin) ?>">
                <input type="hidden" name="form_sera_id" value="<?= $target_sera_id ?>">

                <div style="margin-bottom: 25px;">
                    <label class="form-label">Arızalı Ekipman</label>
                    
                    <?php if ($preselected_ekipman_id > 0): ?>
                        <select class="form-control-custom input-style" style="background-color: #e2e8f0; cursor: not-allowed;" disabled>
                            <?php 
                            $secili_sorgu = $pdo->prepare("SELECT * FROM ekipman WHERE Ekipman_ID = ?");
                            $secili_sorgu->execute([$preselected_ekipman_id]);
                            $secili_cihaz = $secili_sorgu->fetch(PDO::FETCH_ASSOC);
                            if ($secili_cihaz): ?>
                                <option value="<?= $secili_cihaz['Ekipman_ID'] ?>" selected>
                                    <?= htmlspecialchars($secili_cihaz['Ekipman_Adi']) ?> (<?= htmlspecialchars($secili_cihaz['Seri_No']) ?>)
                                </option>
                            <?php endif; ?>
                        </select>
                        <input type="hidden" name="ekipman_id" value="<?= $preselected_ekipman_id ?>">
                    <?php else: ?>
                        <select name="ekipman_id" class="js-ara input-style" required>
                            <option value="">Cihaz listesini açın...</option>
                            <?php 
                            foreach($pdo->query("SELECT * FROM ekipman WHERE Durum != 'Hurda' AND Durum != 'Arızalı' ORDER BY Ekipman_Adi ASC") as $e): ?>
                                <option value="<?= $e['Ekipman_ID'] ?>">
                                    <?= htmlspecialchars($e['Ekipman_Adi']) ?> (<?= htmlspecialchars($e['Seri_No']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                </div>

                <div>
                    <label class="form-label">Sorun Detayı</label>
                    <textarea name="ariza_notu" class="input-style" style="min-height: 140px; resize: vertical;" placeholder="Örn: Su pompası sesli çalışıyor ancak su basmıyor..." required></textarea>
                </div>

                <div class="btn-row">
                    <a href="<?= $iptal_url ?>" class="btn-cancel">İptal Et</a>
                    <button type="submit" name="ariza_kaydet" class="btn-save">
                        <i class="fas fa-check-circle"></i> Bildirimi Tamamla
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    if ($.isFunction($.fn.select2)) {
        $('.js-ara').select2({
            dropdownParent: $('.ariza-body')
        });
    }
});
</script>