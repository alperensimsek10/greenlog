<?php
/**
 * GreenLog - Yeni Personel ve Kart Kaydı (v3.8 - Gizli RFID E-posta Şablonlu)
 * Tasarım: Modern Form UX, PHPMailer Entegrasyonu, Animasyonlu Geri Bildirim
 */

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// PHPMailer Bağımlılıkları
require 'PHPMailer/PHPMailer/src/Exception.php';
require 'PHPMailer/PHPMailer/src/PHPMailer.php';
require 'PHPMailer/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * T.C. Kimlik Numarası Matematiksel Doğrulama Fonksiyonu
 */
function tcNoDogrula($tcno) {
    $tcno = trim($tcno);
    
    // Sadece rakamlardan oluşmalı ve 11 hane olmalı
    if (!preg_match('/^[0-9]{11}$/', $tcno)) return false;
    
    // İlk hane 0 olamaz
    if ($tcno[0] == 0) return false;
    
    $haneler = str_split($tcno);
    
    // 1, 3, 5, 7 ve 9. hanelerin toplamı
    $tekler = $haneler[0] + $haneler[2] + $haneler[4] + $haneler[6] + $haneler[8];
    
    // 2, 4, 6 ve 8. hanelerin toplamı
    $ciftler = $haneler[1] + $haneler[3] + $haneler[5] + $haneler[7];
    
    // (Tekler * 7 - Ciftler) % 10 bize 10. haneyi vermeli
    $onuncuHane = (($tekler * 7) - $ciftler) % 10;
    if ($onuncuHane < 0) $onuncuHane += 10;
    
    if ($onuncuHane != $haneler[9]) return false;
    
    // İlk 10 hanenin toplamının mod 10'u bize 11. haneyi vermeli
    $toplam = 0;
    for ($i = 0; $i < 10; $i++) {
        $toplam += $haneler[$i];
    }
    
    if ($toplam % 10 != $haneler[10]) return false;
    
    return true;
}

// Veritabanı bağlantısının ($pdo) yukarda tanımlandığı varsayılmaktadır.

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['personel_kaydet'])) {
    try {
        $tc_no = trim($_POST['tc_no']);

        // 0. T.C. Kimlik Algoritma Kontrolü
        if (!tcNoDogrula($tc_no)) {
            throw new Exception("Girdiğiniz T.C. Kimlik Numarası geçersizdir. Lütfen kontrol edip tekrar deneyiniz.");
        }

        $pdo->beginTransaction(); 

        // 1. Rastgele Kart Numarası Üretimi
        $yeni_kart_no = strtoupper(substr(md5(uniqid()), 0, 8));
        $verilis_tarihi = date('Y-m-d');

        $kart_sql = "INSERT INTO kart (Kart_No, Verilis_Tarihi) VALUES (?, ?)";
        $kart_stmt = $pdo->prepare($kart_sql);
        $kart_stmt->execute([$yeni_kart_no, $verilis_tarihi]);
        
        $son_kart_id = $pdo->lastInsertId();

        // 2. Personel Verileri
        $sifre_input = !empty($_POST['sifre']) ? $_POST['sifre'] : '123456';
        $email_adresi = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        $secilen_rol = $_POST['rol']; 

        $sql = "INSERT INTO personel (Kart_ID, TC_No, Ad_Soyad, Email, Cinsiyet, Dogum_Tarihi, Telefon, Unvan, Sifre, Rol, Aktif_Mi) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $son_kart_id,
            $tc_no,
            $_POST['ad_soyad'],
            $email_adresi,
            $_POST['cinsiyet'],
            $_POST['dogum_tarihi'],
            $_POST['telefon'],
            $_POST['unvan'],
            $sifre_input, 
            $secilen_rol,
            $_POST['aktiflik']
        ]);

        // 3. PHPMailer Bildirimi (RFID Kart No Alanı E-Postadan Kaldırıldı)
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
            $mail->addAddress($email_adresi, $_POST['ad_soyad']);
            
            $mail->isHTML(true);
            $mail->Subject = 'GreenLog - Yeni Personel Kaydı Bilgilendirmesi';
            
            $mail->Body    = "
                <div style='font-family: sans-serif; border: 1px solid #e2e8f0; padding: 30px; border-radius: 20px; max-width: 600px; margin: 0 auto; background-color: #ffffff;'>
                    <h2 style='color: #10b981; margin-top: 0;'>Hoş Geldin, {$_POST['ad_soyad']}!</h2>
                    <p style='color: #475569;'>GreenLog akıllı sera yönetim sistemine kaydınız başarıyla oluşturulmuştur.</p>
                    
                    <div style='background: #f8fafc; padding: 20px; border-radius: 12px; margin: 20px 0; border: 1px solid #e2e8f0;'>
                        <p style='margin: 5px 0;'><b>Yetki Rolü:</b> $secilen_rol</p>
                        <p style='margin: 5px 0;'><b>E-posta:</b> $email_adresi</p>
                        <p style='margin: 5px 0;'><b>Geçici Şifre:</b> <code style='background:#e2e8f0; padding:2px 5px;'>$sifre_input</code></p>
                    </div>
                    
                    <p style='font-size: 13px; color: #64748b;'>Güvenliğiniz için sisteme ilk girişte şifrenizi güncellemenizi öneririz.</p>
                </div>";
            
            $mail->send();
            $mail_mesaj = " ve giriş bilgileri e-posta ile gönderildi.";
        } catch (Exception $e) {
            $mail_mesaj = " ancak mail gönderilemedi.";
        }

        $pdo->commit(); 
        echo "<script>alert('Personel başarıyla kaydedildi$mail_mesaj'); window.location.href='panel.php?sayfa=personel';</script>";
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        $hata = $e->getMessage();
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
        --gl-text: #0f172a;
        --gl-text-muted: #64748b;
        --gl-border: #e2e8f0;
        --font-main: 'Plus Jakarta Sans', sans-serif;
        --gl-radius-lg: 24px;
        --gl-radius-md: 14px;
    }

    .form-wrapper { font-family: var(--font-main); max-width: 1000px; margin: 0 auto; padding: 25px; color: var(--gl-text); }

    .back-link { 
        display: inline-flex; align-items: center; gap: 8px; color: var(--gl-text-muted); 
        text-decoration: none; font-weight: 700; font-size: 14px; margin-bottom: 25px; 
        transition: all 0.3s ease; padding: 12px 20px; border-radius: var(--gl-radius-md); background: white; 
        border: 1px solid var(--gl-border);
    }
    .back-link:hover { color: var(--gl-green); border-color: var(--gl-green); transform: translateX(-4px); }

    .register-container { 
        background: white; border-radius: var(--gl-radius-lg); border: 1px solid var(--gl-border); 
        box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.06); overflow: hidden; 
    }

    .register-header { 
        background: linear-gradient(135deg, #10b981 0%, #059669 100%); 
        padding: 45px; color: white; display: flex; align-items: center; gap: 25px; 
    }
    .reg-icon { 
        background: rgba(255,255,255,0.2); width: 68px; height: 68px; border-radius: 20px; 
        display: flex; align-items: center; justify-content: center; font-size: 26px; 
        backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,0.3);
    }
    .reg-title h2 { margin: 0; font-size: 26px; font-weight: 800; }
    .reg-title p { margin: 6px 0 0; opacity: 0.9; font-size: 14px; }

    .register-body { padding: 45px; }
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
    .form-group { display: flex; flex-direction: column; gap: 8px; }
    
    label { 
        font-size: 12px; font-weight: 700; color: var(--gl-text-muted); 
        text-transform: uppercase; letter-spacing: 0.5px; padding-left: 4px; 
        display: flex; align-items: center; gap: 8px; 
    }
    label i { color: #94a3b8; font-size: 13px; }
    .form-group:focus-within label i { color: var(--gl-green); }

    input, select { 
        padding: 14px 16px; border-radius: var(--gl-radius-md); border: 2px solid #f1f5f9; 
        outline: none; transition: all 0.3s ease; font-size: 14px; font-weight: 600;
        background: #f8fafc; color: var(--gl-text); box-sizing: border-box;
    }
    input:focus, select:focus { border-color: var(--gl-green); background: #fff; box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.08); }

    .info-box { 
        background: var(--gl-green-light); border: 1px solid #dcfce7; padding: 18px; 
        border-radius: var(--gl-radius-md); display: flex; align-items: center; gap: 15px; 
        margin-bottom: 35px; border-left: 5px solid var(--gl-green);
    }
    .info-box i { color: var(--gl-green); font-size: 20px; }
    .info-box p { margin: 0; font-size: 14px; color: #166534; font-weight: 600; }

    .btn-save { 
        background: var(--gl-green); color: white; padding: 18px; 
        border-radius: var(--gl-radius-md); border: none; font-weight: 800; font-size: 15px; 
        cursor: pointer; transition: all 0.3s ease; margin-top: 25px; width: 100%; 
        display: flex; justify-content: center; align-items: center; gap: 10px;
    }
    .btn-save:hover { background: var(--gl-green-dark); transform: translateY(-2px); }

    .error-card { background: #fef2f2; color: #b91c1c; padding: 16px; border-radius: var(--gl-radius-md); margin-bottom: 30px; border: 1px solid #fecaca; font-weight: 700; display: flex; align-items: center; gap: 10px; font-size: 14px; }

    @media (max-width: 768px) { .form-grid { grid-template-columns: 1fr; } }
</style>

<div class="form-wrapper animate__animated animate__fadeIn">
    <a href="panel.php?sayfa=personel" class="back-link">
        <i class="fa-solid fa-arrow-left"></i> Personel Listesine Dön
    </a>

    <div class="register-container">
        <div class="register-header">
            <div class="reg-icon"><i class="fa-solid fa-user-plus"></i></div>
            <div class="reg-title">
                <h2>Personel Kayıt</h2>
                <p>Sisteme yeni bir personel tanımlayın ve erişim yetkilerini belirleyin.</p>
            </div>
        </div>

        <div class="register-body">
            <?php if (isset($hata)): ?>
                <div class="error-card animate__animated animate__headShake">
                    <i class="fa-solid fa-triangle-exclamation"></i> <?php echo htmlspecialchars($hata); ?>
                </div>
            <?php endif; ?>

            <div class="info-box">
                <i class="fa-solid fa-magic"></i>
                <p><b>Akıllı Kayıt Sistemi:</b> Kayıt sonrası personel için 8 haneli benzersiz RFID kart kodu üretilecek ve giriş bilgileri anlık olarak e-posta ile iletilecektir.</p>
            </div>

            <form action="" method="POST" autocomplete="off" id="regForm">
                <div class="form-grid">
                    
                    <div class="form-group">
                        <label><i class="fa-solid fa-id-card"></i> T.C. Kimlik No</label>
                        <input type="text" name="tc_no" maxlength="11" pattern="[0-9]{11}" placeholder="11 Haneli TC Giriniz" required>
                    </div>

                    <div class="form-group">
                        <label><i class="fa-solid fa-user"></i> Ad Soyad</label>
                        <input type="text" name="ad_soyad" placeholder="Örn: Ahmet Yılmaz" required>
                    </div>

                    <div class="form-group">
                        <label><i class="fa-solid fa-at"></i> E-Posta Adresi</label>
                        <input type="email" name="email" placeholder="personel@greenlog.com" required>
                    </div>

                    <div class="form-group">
                        <label><i class="fa-solid fa-phone"></i> Telefon Numarası</label>
                        <input type="tel" name="telefon" maxlength="11" placeholder="Örn: 05XXXXXXXXX" required>
                    </div>

                    <div class="form-group">
                        <label><i class="fa-solid fa-lock"></i> Sistem Şifresi</label>
                        <input type="password" name="sifre" placeholder="Boş bırakılırsa: 123456">
                    </div>

                    <div class="form-group">
                        <label><i class="fa-solid fa-briefcase"></i> Görev / Ünvan</label>
                        <input type="text" name="unvan" placeholder="Örn: Ziraat Mühendisi" required>
                    </div>

                    <div class="form-group">
                        <label><i class="fa-solid fa-calendar-days"></i> Doğum Tarihi</label>
                        <input type="date" name="dogum_tarihi" required>
                    </div>

                    <div class="form-group">
                        <label><i class="fa-solid fa-venus-mars"></i> Cinsiyet</label>
                        <select name="cinsiyet" required>
                            <option value="" disabled selected>Seçiniz</option>
                            <option value="Erkek">Erkek</option>
                            <option value="Kadın">Kadın</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label><i class="fa-solid fa-shield-alt"></i> Yetki Grubu</label>
                        <select name="rol" required>
                            <option value="" disabled selected>Seçiniz</option>
                            <option value="Personel">Saha Personeli (Standart)</option>
                            <option value="Güvenlik">Güvenlik Personeli (Kontrol)</option> 
                            <option value="Admin">Sistem Yöneticisi (Admin)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label><i class="fa-solid fa-toggle-on"></i> Başlangıç Durumu</label>
                        <select name="aktiflik">
                            <option value="1">Hemen Aktif Et</option>
                            <option value="0">Pasif Olarak Kaydet</option>
                        </select>
                    </div>
                </div>

                <button type="submit" name="personel_kaydet" class="btn-save" id="submitBtn">
                    <i class="fa-solid fa-check-double"></i> Kaydı Tamamla ve Bilgileri Gönder
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const regForm = document.getElementById('regForm');
        const submitBtn = document.getElementById('submitBtn');
        
        // TC No ve Telefon Kısıtlamaları (Sadece rakam, Max 11 Karakter, Yapıştırma korumalı)
        ['tc_no', 'telefon'].forEach(name => {
            const inputField = document.querySelector(`input[name="${name}"]`);
            if(inputField) {
                inputField.addEventListener('input', function() {
                    this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11);
                });
            }
        });

        // Form gönderim yükleniyor animasyonu
        regForm.addEventListener('submit', function() {
            submitBtn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> İşlem Yapılıyor, Lütfen Bekleyin...';
            submitBtn.style.opacity = '0.7';
            submitBtn.style.pointerEvents = 'none';
        });
    });
</script>