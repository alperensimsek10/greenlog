<?php
/**
 * GreenLog - Personel Bilgileri Düzenleme (v3.5 - Emerald Premium Dashboard)
 */

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 1. Mevcut Veriyi Çekme
if (isset($_GET['id'])) {
    $personel_id = intval($_GET['id']);
    
    // Personeli ve bağlı kart numarasını getir
    $sorgu = $pdo->prepare("
        SELECT p.*, k.Kart_No 
        FROM personel p 
        LEFT JOIN kart k ON p.Kart_ID = k.Kart_ID 
        WHERE p.Personel_ID = ?
    ");
    $sorgu->execute([$personel_id]);
    $p = $sorgu->fetch(PDO::FETCH_ASSOC);

    if (!$p) {
        die("<div style='padding:20px; color:red; font-family:sans-serif; font-weight:bold;'>Hata: Personel bulunamadı.</div>");
    }
} else {
    die("<div style='padding:20px; color:red; font-family:sans-serif; font-weight:bold;'>Hata: Geçersiz ID.</div>");
}

// 2. Güncelleme İşlemi
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['personel_guncelle'])) {
    try {
        $sql = "UPDATE personel SET 
                TC_No = ?, 
                Ad_Soyad = ?, 
                Cinsiyet = ?, 
                Dogum_Tarihi = ?, 
                Telefon = ?, 
                Unvan = ?, 
                Rol = ?, 
                Aktif_Mi = ? 
                WHERE Personel_ID = ?";
        
        $stmt = $pdo->prepare($sql);
        $sonuc = $stmt->execute([
            $_POST['tc_no'],
            $_POST['ad_soyad'],
            $_POST['cinsiyet'],
            $_POST['dogum_tarihi'],
            $_POST['telefon'],
            $_POST['unvan'],
            $_POST['rol'],
            $_POST['aktiflik'],
            $personel_id
        ]);

        if ($sonuc) {
            echo "<script>window.location.href='panel.php?sayfa=personel&mesaj=guncellendi';</script>";
            exit;
        }
    } catch (PDOException $e) {
        $hata = "Güncelleme hatası: " . $e->getMessage();
    }
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
        --font-main: 'Plus Jakarta Sans', sans-serif;
        --gl-radius-lg: 24px;
        --gl-radius-md: 14px;
        --gl-shadow: 0 10px 15px -3px rgba(15, 23, 42, 0.04), 0 4px 6px -4px rgba(15, 23, 42, 0.04);
    }

    .form-wrapper { font-family: var(--font-main); max-width: 900px; margin: 0 auto; padding: 25px; color: var(--gl-text); }

    /* Navigasyon */
    .back-nav { 
        display: inline-flex; align-items: center; gap: 8px; color: var(--gl-text-muted); 
        text-decoration: none; font-weight: 700; font-size: 14px; margin-bottom: 25px; 
        transition: all 0.3s ease; padding: 12px 20px; border-radius: var(--gl-radius-md); background: white; 
        border: 1px solid var(--gl-border); box-shadow: var(--gl-shadow);
    }
    .back-nav:hover { color: var(--gl-green); border-color: var(--gl-green); transform: translateX(-4px); }

    /* Ana Panel */
    .form-container { 
        background: white; border-radius: var(--gl-radius-lg); border: 1px solid var(--gl-border); 
        box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.06); overflow: hidden; 
    }

    /* Üst Bölüm (Banner) */
    .profile-banner { 
        background: linear-gradient(135deg, #10b981 0%, #059669 100%); 
        padding: 40px; color: white; display: flex; align-items: center; gap: 25px; 
    }
    .profile-avatar { 
        width: 74px; height: 74px; background: rgba(255,255,255,0.2); 
        border: 2px solid rgba(255,255,255,0.4); border-radius: 20px; 
        display: flex; align-items: center; justify-content: center; 
        font-size: 28px; font-weight: 800; backdrop-filter: blur(10px);
    }
    .profile-info h2 { margin: 0; font-size: 24px; font-weight: 800; letter-spacing: -0.5px; }
    .profile-info p { margin: 6px 0 0; opacity: 0.9; font-size: 14px; font-weight: 500; }

    /* Kart ID Rozeti */
    .card-id-box { 
        margin: -25px 40px 30px; background: white; padding: 16px 24px; 
        border-radius: var(--gl-radius-md); border: 1px solid var(--gl-border); 
        box-shadow: 0 10px 25px rgba(15, 23, 42, 0.05);
        display: flex; justify-content: space-between; align-items: center;
    }
    .card-id-label { font-size: 12px; font-weight: 800; color: var(--gl-text-muted); text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 8px; }
    .card-id-label i { color: var(--gl-green); font-size: 14px; }
    .card-id-value { font-family: 'Monaco', 'Consolas', monospace; color: var(--gl-green-dark); font-weight: 700; font-size: 15px; background: var(--gl-green-light); padding: 6px 14px; border-radius: 10px; border: 1px solid #d1fae5; }
    .badge-card-none { color: var(--gl-red); background: var(--gl-red-light); border: 1px solid #fecaca; font-family: var(--font-main); font-size: 12px; font-weight: 700; padding: 6px 14px; border-radius: 10px; }

    /* Form İçerik */
    .form-body { padding: 0 40px 40px; }
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
    .form-group { display: flex; flex-direction: column; gap: 8px; }
    
    label { 
        font-size: 12px; font-weight: 700; color: var(--gl-text-muted); 
        text-transform: uppercase; letter-spacing: 0.5px; padding-left: 4px;
        display: flex; align-items: center; gap: 8px;
    }
    label i { color: #94a3b8; font-size: 13px; transition: 0.3s; }
    .form-group:focus-within label i { color: var(--gl-green); }

    input, select { 
        padding: 14px 16px; border-radius: var(--gl-radius-md); border: 2px solid #f1f5f9; 
        outline: none; transition: all 0.3s ease; font-size: 14px; font-weight: 600;
        background: #f8fafc; color: var(--gl-text); font-family: inherit; box-sizing: border-box;
    }
    
    input:focus, select:focus { 
        border-color: var(--gl-green); background: #fff; 
        box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.08); 
    }

    /* Güncelle Butonu */
    .btn-submit { 
        background: var(--gl-green); color: white; padding: 16px; 
        border-radius: var(--gl-radius-md); border: none; font-weight: 800; font-size: 15px; 
        cursor: pointer; transition: all 0.3s ease; margin-top: 35px; width: 100%; 
        display: flex; justify-content: center; align-items: center; gap: 10px;
        box-shadow: 0 6px 20px rgba(16, 185, 129, 0.25);
    }
    .btn-submit:hover { background: var(--gl-green-dark); transform: translateY(-2px); box-shadow: 0 10px 25px rgba(16, 185, 129, 0.35); }

    .error-alert { background: var(--gl-red-light); color: #b91c1c; padding: 16px; border-radius: var(--gl-radius-md); margin-bottom: 25px; border: 1px solid #fecaca; font-weight: 600; display: flex; align-items: center; gap: 10px; font-size: 14px; }

    /* Durum Seçimi Özel Renkler */
    .status-select-passive { border-color: #fca5a5 !important; color: #b91c1c !important; background: #fff5f5 !important; }
    .status-select-passive:focus { box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.1) !important; }

    @media (max-width: 768px) { 
        .form-grid { grid-template-columns: 1fr; gap: 20px; } 
        .card-id-box { flex-direction: column; gap: 12px; text-align: center; margin: -25px 20px 25px; padding: 15px; } 
        .form-body { padding: 0 20px 20px; }
        .profile-banner { padding: 30px 20px; flex-direction: column; text-align: center; gap: 15px; }
    }
</style>

<div class="form-wrapper animate__animated animate__fadeIn">
    <a href="panel.php?sayfa=personel" class="back-nav">
        <i class="fa-solid fa-arrow-left"></i> Personel Listesine Dön
    </a>

    <div class="form-container">
        <div class="profile-banner">
            <div class="profile-avatar">
                <?php echo mb_substr($p['Ad_Soyad'], 0, 1, 'UTF-8'); ?>
            </div>
            <div class="profile-info">
                <h2>Profil Ayarlarını Düzenle</h2>
                <p><strong><?php echo htmlspecialchars($p['Ad_Soyad']); ?></strong> adındaki personelin sistem verilerini güncelliyorsunuz.</p>
            </div>
        </div>

        <div class="card-id-box">
            <span class="card-id-label"><i class="fa-solid fa-id-card"></i> Tanımlı Donanım Kimliği (RFID)</span>
            <?php if ($p['Kart_No']): ?>
                <span class="card-id-value"><?php echo htmlspecialchars($p['Kart_No']); ?></span>
            <?php else: ?>
                <span class="badge-card-none"><i class="fa-solid fa-triangle-exclamation"></i> SİSTEMDE KART TANIMLI DEĞİL</span>
            <?php id_card_icon: endif; ?>
        </div>

        <div class="form-body">
            <?php if (isset($hata)): ?>
                <div class="error-alert animate__animated animate__headShake">
                    <i class="fa-solid fa-circle-exclamation"></i> <?php echo htmlspecialchars($hata); ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST" id="updateForm">
                <div class="form-grid">
                    
                    <div class="form-group">
                        <label><i class="fa-solid fa-fingerprint"></i> T.C. Kimlik Numarası</label>
                        <input type="text" name="tc_no" value="<?php echo htmlspecialchars($p['TC_No']); ?>" maxlength="11" placeholder="11 Haneli TC Kimlik No" required>
                    </div>

                    <div class="form-group">
                        <label><i class="fa-solid fa-user-pen"></i> Ad Soyad</label>
                        <input type="text" name="ad_soyad" value="<?php echo htmlspecialchars($p['Ad_Soyad']); ?>" placeholder="Ad ve Soyadı giriniz" required>
                    </div>

                    <div class="form-group">
                        <label><i class="fa-solid fa-venus-mars"></i> Cinsiyet</label>
                        <select name="cinsiyet" required>
                            <option value="Erkek" <?php echo $p['Cinsiyet'] == 'Erkek' ? 'selected' : ''; ?>>Erkek</option>
                            <option value="Kadın" <?php echo $p['Cinsiyet'] == 'Kadın' ? 'selected' : ''; ?>>Kadın</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label><i class="fa-solid fa-calendar-days"></i> Doğum Tarihi</label>
                        <input type="date" name="dogum_tarihi" value="<?php echo htmlspecialchars($p['Dogum_Tarihi']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label><i class="fa-solid fa-phone"></i> Telefon Numarası</label>
                        <input type="tel" name="telefon" value="<?php echo htmlspecialchars($p['Telefon']); ?>" placeholder="05xx xxx xx xx" required>
                    </div>

                    <div class="form-group">
                        <label><i class="fa-solid fa-briefcase"></i> Şirket Ünvanı</label>
                        <input type="text" name="unvan" value="<?php echo htmlspecialchars($p['Unvan']); ?>" placeholder="Örn: Bilgi İşlem Sorumlusu" required>
                    </div>

                    <div class="form-group">
                        <label><i class="fa-solid fa-user-shield"></i> Yetki Rolü</label>
                        <select name="rol" required>
                            <option value="Personel" <?php echo $p['Rol'] == 'Personel' ? 'selected' : ''; ?>>Saha Personeli</option>
                            <option value="Admin" <?php echo $p['Rol'] == 'Admin' ? 'selected' : ''; ?>>Sistem Yöneticisi</option>
                            <option value="Güvenlik" <?php echo $p['Rol'] == 'Güvenlik' ? 'selected' : ''; ?>>Güvenlik / Resepsiyon</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label><i class="fa-solid fa-circle-bolt"></i> Hesap Durumu</label>
                        <select name="aktiflik" id="statusSelect" class="<?php echo $p['Aktif_Mi'] == 0 ? 'status-select-passive' : ''; ?>">
                            <option value="1" <?php echo $p['Aktif_Mi'] == 1 ? 'selected' : ''; ?>>🟢 Hesabı Aktif Tut</option>
                            <option value="0" <?php echo $p['Aktif_Mi'] == 0 ? 'selected' : ''; ?>>🔴 Erişimi Askıya Al (Pasif)</option>
                        </select>
                    </div>
                </div>

                <button type="submit" name="personel_guncelle" class="btn-submit" id="saveBtn">
                    <i class="fa-solid fa-floppy-disk"></i> Değişiklikleri Uygula
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const updateForm = document.getElementById('updateForm');
        const saveBtn = document.getElementById('saveBtn');
        const statusSelect = document.getElementById('statusSelect');

        // Durum seçimine göre anlık renk yönetimi
        statusSelect.addEventListener('change', function() {
            if(this.value == "0") {
                this.classList.add('status-select-passive');
            } else {
                this.classList.remove('status-select-passive');
            }
        });

        // T.C. Kimlik girdisini sadece sayıyla kısıtlama
        const tcInput = document.querySelector('input[name="tc_no"]');
        if(tcInput) {
            tcInput.addEventListener('input', function() {
                this.value = this.value.replace(/[^0-9]/g, '');
            });
        }

        // Form gönderim animasyon durum yönetimi (Çift basmayı engeller)
        updateForm.addEventListener('submit', function() {
            saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Ayarlar Güncelleniyor...';
            saveBtn.style.opacity = '0.7';
            saveBtn.style.pointerEvents = 'none';
        });
    });
</script>