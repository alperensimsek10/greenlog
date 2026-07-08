<?php
session_start();
// Hatalı giriş sayısını sorgulamak için veritabanı bağlantısı ve login.php'deki class mantığı gerekiyor.
// login.php'deki class'ı temiz kullanabilmek için require ediyoruz veya burada da tanımlayabiliriz.
require_once "db-connect.php";

// index.php içinde de WhiteShark log sayma fonksiyonuna ihtiyacımız var:
function getIndexFailedCount($pdo) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Bilinmiyor';
    $bes_dakika_once = date('Y-m-d H:i:s', strtotime('-5 minutes'));
    $sql = "SELECT COUNT(*) FROM security_logs WHERE ip_adresi = :ip AND durum LIKE 'BAŞARISIZ%' AND zaman >= :bes_dakika_once";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':ip' => $ip, ':bes_dakika_once' => $bes_dakika_once]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}
$goster_captcha = (getIndexFailedCount($pdo) >= 3);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <link rel="shortcut icon" href="images/logo1.png" type="image/x-icon">
    <link rel="stylesheet" href="style_login.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GreenLog</title>
    <style>
        /* --- ODAKLANMA VE GÜVENLİK BİLEŞENLERİ --- */
        .input-box input:focus, .captcha-giris-kutusu input:focus {
            border-color: #2e7d32 !important;
            box-shadow: 0 0 10px rgba(46, 125, 50, 0.4);
            transition: all 0.3s ease;
        }

        .input-box input:-webkit-autofill,
        .input-box input:autofill {
            -webkit-text-fill-color: #fff !important;
            transition: background-color 5000s ease-in-out 0s;
        }
        
        .input-box input:-webkit-autofill::placeholder,
        .input-box input:autofill::placeholder {
            color: transparent !important;
        }

        .input-box {
            position: relative;
        }
        .input-box .sifre-durum {
            position: absolute;
            right: 45px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 20px;
            color: #fff;
            cursor: pointer;
            opacity: 0.7;
            transition: opacity 0.2s;
            z-index: 5;
        }
        .input-box .sifre-durum:hover {
            opacity: 1;
        }

        .caps-uyari {
            color: #ffcc00;
            font-size: 12px;
            text-align: left;
            margin-top: -10px;
            margin-bottom: 10px;
            display: none;
            font-weight: 500;
        }

        .beni-hatirla-alanı {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            margin: -5px 0 15px 5px;
            color: #fff;
            font-size: 14px;
        }
        .beni-hatirla-alanı input[type="checkbox"] {
            accent-color: #2e7d32;
            margin-right: 8px;
            cursor: pointer;
            width: 16px;
            height: 16px;
        }
        .beni-hatirla-alanı label {
            cursor: pointer;
            user-select: none;
        }

        .login-footer {
            margin-top: 25px;
            text-align: center;
            color: rgba(255, 255, 255, 0.7);
            font-size: 12px;
            line-height: 1.6;
        }
        .login-footer a {
            color: #fff;
            text-decoration: underline;
            font-weight: 500;
        }
        .login-footer a:hover {
            color: #a3e635;
        }

        /* --- CAPTCHA ALANI --- */
        .captcha-ana-kapsayici {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin: 25px 0;
            width: 100%;
        }

        .captcha-gorsel-sirasi {
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
        }

        .captcha-gorsel-alani {
            flex: 1;
            height: 55px;
            background: #ffffff; 
            border-radius: 12px;
            overflow: hidden;
            border: 2px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 4px 6px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .captcha-gorsel-alani img {
            width: 100% !important;
            height: 100% !important;
            display: block !important;
            object-fit: fill !important;
            position: static !important;
            left: auto !important;
        }

        .captcha-aksiyon-butonlari {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .captcha-buton {
            background: rgba(255, 255, 255, 0.9);
            border: none;
            border-radius: 8px;
            width: 36px;
            height: 25px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #2e7d32;
            transition: all 0.2s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .captcha-buton.ses-butonu {
            color: #1d4ed8;
        }

        .captcha-buton:hover {
            background: #ffffff;
            transform: scale(1.05);
        }

        .captcha-giris-kutusu {
            width: 100%;
            height: 50px;
        }

        .captcha-giris-kutusu input {
            width: 100%;
            height: 100%;
            background: transparent;
            outline: none;
            border: 2px solid rgba(255, 255, 255, .2);
            border-radius: 40px;
            font-size: 16px;
            color: #fff;
            text-align: center;
            box-sizing: border-box;
        }

        .captcha-giris-kutusu input::placeholder {
            color: rgba(255, 255, 255, 0.75) !important;
        }
    </style>
</head>
<body>
    <div class="kutu">
        <img src="images/logo2.png" alt="Logo">
        
        <?php if (isset($_GET['error'])) { ?>
            <p style="color: #ff4d4d; background: #ffe6e6; padding: 10px; border-radius: 5px; text-align: center; font-size: 14px; margin-bottom: 15px; font-weight: 500;">
                <?php echo htmlspecialchars($_GET['error']); ?>
            </p>
        <?php } ?>

        <form action="login.php" method="POST">
            <h1>Giriş Ekranı</h1>
            
            <div class="input-box">
                <input id="tck" name="tc_no" type="text" placeholder="TC Kimlik No" maxlength="11" value="<?php echo isset($_COOKIE['remember_tc']) ? htmlspecialchars($_COOKIE['remember_tc']) : ''; ?>" required>
                <i class='bx bxs-id-card'></i> 
            </div>
            
            <div class="input-box">
                <input id="passwordk" name="password" type="password" placeholder="Şifreniz" required>
                <i class='bx bx-hide sifre-durum' id="sifre-goz" onclick="sifreGosterGizle()"></i>
                <i class='bx bxs-lock-alt'></i>
            </div>

            <div class="caps-uyari" id="caps-durum">
                <i class='bx bx-error-circle'></i> Uyarı: Caps Lock (Büyük Harf) Açık!
            </div>

            <div class="beni-hatirla-alanı">
                <input type="checkbox" id="beni_hatirla" name="beni_hatirla" <?php echo isset($_COOKIE['remember_tc']) ? 'checked' : ''; ?>>
                <label for="beni_hatirla">Beni Hatırla</label>
            </div>
            
            <?php if ($goster_captcha) { ?>
            <div class="captcha-ana-kapsayici">
                <div class="captcha-gorsel-sirasi">
                    <div class="captcha-gorsel-alani">
                        <img src="captcha.php" alt="Captcha" id="captcha-img">
                    </div>
                    <div class="captcha-aksiyon-butonlari">
                        <button type="button" class="captcha-buton ses-butonu" onclick="captchaSeslendir();" title="Kodu Sesli Dinle">
                            <i class='bx bxs-volume-full' style='font-size: 16px;'></i>
                        </button>
                        <button type="button" class="captcha-buton" onclick="document.getElementById('captcha-img').src='captcha.php?'+Math.random();" title="Kodu Yenile">
                            <i class='bx bx-refresh' style='font-size: 18px; font-weight: bold;'></i>
                        </button>
                    </div>
                </div>
                <div class="captcha-giris-kutusu">
                    <input type="text" name="captcha_girilen" placeholder="Güvenlik Kodunu Buraya Giriniz" maxlength="4" required autocomplete="off">
                </div>
            </div>
            <?php } ?>
            
            <button type="submit" class="button">Giriş Yap</button>
        </form> 

        <div class="login-footer">
            <p>Giriş yaparak <a href="kvkk.php" target="_blank">KVKK Aydınlatma Metni</a>'ni kabul etmiş olursunuz.</p>
            <p>© 2026 GreenLog. Tüm Hakları Saklıdır.</p>
        </div>
    </div>

    <script>
        function sifreGosterGizle() {
            var sifreInput = document.getElementById("passwordk");
            var gozIkonu = document.getElementById("sifre-goz");
            
            if (sifreInput.type === "password") {
                sifreInput.type = "text";
                gozIkonu.classList.remove("bx-hide");
                gozIkonu.classList.add("bx-show");
            } else {
                sifreInput.type = "password";
                gozIkonu.classList.remove("bx-show");
                gozIkonu.classList.add("bx-hide");
            }
        }

        var sifreInput = document.getElementById("passwordk");
        var capsUyari = document.getElementById("caps-durum");

        function checkCapsLock(event) {
            if (event && typeof event.getModifierState === "function") {
                if (event.getModifierState("CapsLock")) {
                    capsUyari.style.display = "block";
                } else {
                    capsUyari.style.display = "none";
                }
            }
        }

        sifreInput.addEventListener("keyup", checkCapsLock);
        sifreInput.addEventListener("keydown", checkCapsLock);

        function captchaSeslendir() {
            fetch('captcha.php?ses=1')
            .then(response => response.json())
            .then(data => {
                if (data.metin) {
                    var u = new SpeechSynthesisUtterance();
                    u.text = data.metin;
                    u.lang = 'tr-TR';
                    u.rate = 0.8;
                    window.speechSynthesis.cancel();
                    window.speechSynthesis.speak(u);
                }
            })
            .catch(error => console.error('Ses yüklenirken hata oluştu:', error));
        }
    </script>
</body>
</html>