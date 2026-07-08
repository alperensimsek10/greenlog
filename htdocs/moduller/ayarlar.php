<?php
/**
 * GreenLog - Kullanıcı Ayarları (v3.3 - Smart Security Edition)
 * Özellikler: 
 * 1. Ad-Soyad kilitli (Readonly)
 * 2. Mevcut şifre ile kimlik doğrulaması zorunlu
 * 3. Sadece değişen alanların mail bildirilmesi
 */

// PHPMailer Bağımlılıkları (Dosya yollarının doğruluğundan emin olun)
require 'PHPMailer/PHPMailer/src/Exception.php';
require 'PHPMailer/PHPMailer/src/PHPMailer.php';
require 'PHPMailer/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Güvenlik: Oturum kontrolü
if (!isset($_SESSION['Personel_ID'])) {
    die("Oturum bulunamadı. Lütfen giriş yapın.");
}
$user_id = $_SESSION['Personel_ID']; 

// --- 1. MEVCUT BİLGİLERİ ÇEKME ---
$sorgu = $pdo->prepare("SELECT * FROM personel WHERE Personel_ID = ?");
$sorgu->execute([$user_id]);
$user = $sorgu->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("<div style='padding:20px; color:red; font-family:sans-serif;'>Hata: Kullanıcı kaydı bulunamadı.</div>");
}

// --- 2. GÜNCELLEME İŞLEMİ ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ayarlari_kaydet'])) {
    $mevcut_sifre_input = $_POST['mevcut_sifre'];
    $yeni_tel   = $_POST['telefon'];
    $yeni_email = $_POST['email'];
    $yeni_sifre = $_POST['sifre'];
    
    // Şifre Doğrulama (Veritabanındaki şifre hashli ise password_verify kullanılır)
    if (!password_verify($mevcut_sifre_input, $user['Sifre']) && $user['Sifre'] !== $mevcut_sifre_input) {
        $hata = "Mevcut şifreniz hatalı! Güvenliğiniz için işlem reddedildi.";
    } else {
        $degisenler = [];
        
        // Değişiklik Kontrolleri
        if ($yeni_email !== $user['Email']) $degisenler[] = "E-posta Adresi";
        if ($yeni_tel !== $user['Telefon'])   $degisenler[] = "Telefon Numarası";
        if (!empty($yeni_sifre))              $degisenler[] = "Şifre";

        if (empty($degisenler)) {
            echo "<script>alert('Herhangi bir değişiklik algılanmadı.'); window.location.href='panel.php?sayfa=ayarlar';</script>";
            exit;
        }

        try {
            // SQL Hazırlığı
            if (!empty($yeni_sifre)) {
                $hashli_yeni_sifre = password_hash($yeni_sifre, PASSWORD_DEFAULT);
                $sql = "UPDATE personel SET Telefon = ?, Email = ?, Sifre = ? WHERE Personel_ID = ?";
                $params = [$yeni_tel, $yeni_email, $hashli_yeni_sifre, $user_id];
            } else {
                $sql = "UPDATE personel SET Telefon = ?, Email = ? WHERE Personel_ID = ?";
                $params = [$yeni_tel, $yeni_email, $user_id];
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            // --- MAİL BİLDİRİMİ ---
            $degisim_listesi = implode(", ", $degisenler);
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
                $mail->addAddress($yeni_email, $user['Ad_Soyad']);
                $mail->isHTML(true);
                $mail->Subject = 'GreenLog - Bilgileriniz Güncellendi';
                $mail->Body = "
                    <div style='font-family:sans-serif; border:1px solid #edf2f7; padding:25px; border-radius:15px; max-width:500px;'>
                        <h2 style='color:#10b981;'>Merhaba {$user['Ad_Soyad']},</h2>
                        <p style='color:#4a5568;'>Hesap bilgilerinizde yapılan değişiklikler aşağıdadır:</p>
                        <div style='background:#f0fdf4; border:1px solid #dcfce7; padding:15px; border-radius:10px; color:#166534; font-weight:bold;'>
                             Güncellenen Alanlar: $degisim_listesi
                        </div>
                        <p style='font-size:12px; color:#a0aec0; margin-top:20px;'>Bu işlem sizin tarafınızdan yapılmadıysa lütfen hemen sistem yöneticisi ile iletişime geçin.</p>
                    </div>";
                $mail->send();
                $onay_notu = "ve mail ile bildirildi.";
            } catch (Exception $e) {
                $onay_notu = "ancak mail gönderilemedi.";
            }

            echo "<script>alert('Bilgileriniz başarıyla güncellendi $onay_notu'); window.location.href='panel.php?sayfa=ayarlar';</script>";
            exit;
        } catch (PDOException $e) {
            $hata = "Hata: " . $e->getMessage();
        }
    }
}
?>

<!-- UI (Görsel Arayüz) -->
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<style>
    :root { 
        --gl-green: #10b981; --gl-green-dark: #059669; --gl-bg: #f8fafc;
        --gl-text: #1e293b; --gl-text-muted: #64748b; --gl-border: #e2e8f0;
    }

    .settings-wrapper { font-family: 'Plus Jakarta Sans', sans-serif; max-width: 800px; margin: 2rem auto; padding: 0 20px; }
    .animate-fade-in { animation: fadeInSlide 0.8s ease-out; }
    @keyframes fadeInSlide { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

    .settings-card { background: white; border-radius: 32px; border: 1px solid var(--gl-border); box-shadow: 0 20px 50px rgba(0,0,0,0.04); overflow: hidden; }

    .card-header { 
        background: linear-gradient(135deg, #10b981 0%, #059669 100%); 
        padding: 50px 40px; color: white; display: flex; align-items: center; gap: 25px; position: relative;
    }
    .card-header::after { content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 40px; background: white; clip-path: ellipse(50% 100% at 50% 100%); }

    .header-icon-box { background: rgba(255,255,255,0.25); width: 70px; height: 70px; border-radius: 24px; display: flex; align-items: center; justify-content: center; font-size: 30px; backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.3); }

    .card-body { padding: 40px 50px 50px; }
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .full-width { grid-column: span 2; }

    label { font-size: 11px; font-weight: 800; color: var(--gl-text-muted); text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 8px; display: flex; align-items: center; gap: 6px; }

    input { width: 100%; padding: 14px 18px; border-radius: 14px; border: 2px solid #f1f5f9; font-size: 15px; font-weight: 600; color: var(--gl-text); transition: all 0.3s ease; box-sizing: border-box; background: #f8fafc; }
    input:focus:not([readonly]) { border-color: var(--gl-green); outline: none; box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1); background: white; }

    input[readonly] { background: #f1f5f9; color: #94a3b8; cursor: not-allowed; border-style: dashed; }
    
    .verify-box { background: #fffbeb; border: 1px solid #fef3c7; padding: 20px; border-radius: 20px; margin-bottom: 10px; }
    .verify-input { border-color: #fde68a !important; background: #ffffff !important; }

    .btn-update { background: var(--gl-green); color: white; border: none; padding: 18px; border-radius: 18px; font-weight: 800; font-size: 16px; cursor: pointer; width: 100%; margin-top: 25px; transition: all 0.3s ease; display: flex; justify-content: center; align-items: center; gap: 12px; box-shadow: 0 10px 20px rgba(16, 185, 129, 0.2); }
    .btn-update:hover { transform: translateY(-2px); background: var(--gl-green-dark); box-shadow: 0 12px 25px rgba(16, 185, 129, 0.3); }

    .error-card { background:#fef2f2; color:#b91c1c; padding:18px; border-radius:16px; margin-bottom:25px; border:1px solid #fecaca; display: flex; align-items: center; gap: 10px; font-weight: 700; }
</style>

<div class="settings-wrapper animate-fade-in">
    <div class="settings-card">
        <div class="card-header">
            <div class="header-icon-box"><i class="fas fa-shield-alt"></i></div>
            <div class="header-text">
                <h3 style="margin:0; font-size:24px;">Profil Ayarları</h3>
                <p style="margin:5px 0 0; opacity:0.9;">Bilgilerinizi güvenli bir şekilde güncelleyin.</p>
            </div>
        </div>

        <div class="card-body">
            <?php if (isset($hata)): ?>
                <div class="error-card"><i class="fas fa-exclamation-triangle"></i> <?php echo $hata; ?></div>
            <?php endif; ?>

            <form method="POST" id="settingsForm">
                <div class="form-grid">
                    
                    <div class="form-group full-width">
                        <label><i class="fas fa-lock"></i> Adı Soyadı (Sabit)</label>
                        <input type="text" value="<?php echo htmlspecialchars($user['Ad_Soyad']); ?>" readonly>
                    </div>

                    <div class="form-group full-width verify-box">
                        <label style="color: #92400e;"><i class="fas fa-key"></i> KİMLİK DOĞRULAMA</label>
                        <input type="password" name="mevcut_sifre" class="verify-input" placeholder="Onay için mevcut şifrenizi girin..." required>
                        <small style="color: #b45309; display:block; margin-top:8px; font-size:11px;">* Herhangi bir güncellleme için bu alan zorunludur.</small>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> E-posta Adresi</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($user['Email']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-phone"></i> Telefon</label>
                        <input type="tel" name="telefon" value="<?php echo htmlspecialchars($user['Telefon']); ?>" required>
                    </div>

                    <div class="form-group full-width">
                        <label><i class="fas fa-fingerprint"></i> Yeni Şifre (Boş bırakılabilir)</label>
                        <input type="password" name="sifre" placeholder="Değiştirmek istemiyorsanız boş bırakın...">
                    </div>
                </div>

                <button type="submit" name="ayarlari_kaydet" class="btn-update" id="submitBtn">
                    <i class="fas fa-check-double"></i> Değişiklikleri Kaydet
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    document.getElementById('settingsForm').addEventListener('submit', function() {
        const btn = document.getElementById('submitBtn');
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> İşleniyor...';
        btn.style.opacity = '0.7';
        btn.style.pointerEvents = 'none';
    });
</script>