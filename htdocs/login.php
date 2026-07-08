<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Bağlantı dosyasını dahil ediyoruz
require_once "db-connect.php";

/**
 * WHITE SHARK GÜVENLİK, LOG VE BRUTEFORCE KATMANI
 */
class WhiteShark {
    
    // IP Engelli mi Kontrol Et
    public static function isIpBanned($pdo) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Bilinmiyor';
        $zaman = date('Y-m-d H:i:s');

        $sql = "SELECT id FROM ip_blacklist 
                WHERE ip_adresi = :ip AND aktif_mi = 1 
                AND (bitis_zamani IS NULL OR bitis_zamani > :zaman) 
                LIMIT 1";
        
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':ip' => $ip, ':zaman' => $zaman]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ? true : false;
        } catch (PDOException $e) {
            return false;
        }
    }

    // YENİ EKLEME: Son 5 dakikadaki hatalı giriş denemesi sayısını getirir
    public static function getFailedAttemptsCount($pdo) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Bilinmiyor';
        $bes_dakika_once = date('Y-m-d H:i:s', strtotime('-5 minutes'));

        $sql = "SELECT COUNT(*) FROM security_logs 
                WHERE ip_adresi = :ip 
                AND durum LIKE 'BAŞARISIZ%' 
                AND zaman >= :bes_dakika_once";
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':ip' => $ip, ':bes_dakika_once' => $bes_dakika_once]);
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            return 0;
        }
    }

    // Son 1 dakikadaki hatalı girişleri say ve gerekirse BANLA
    public static function checkAndBan($pdo) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Bilinmiyor';
        $bir_dakika_once = date('Y-m-d H:i:s', strtotime('-1 minute'));

        $sql = "SELECT COUNT(*) FROM security_logs 
                WHERE ip_adresi = :ip 
                AND durum LIKE 'BAŞARISIZ%' 
                AND zaman >= :bir_dakika_once";
        
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':ip' => $ip, ':bir_dakika_once' => $bir_dakika_once]);
            $hatali_sayisi = $stmt->fetchColumn();

            if ($hatali_sayisi >= 10) {
                $bitis_zamani = date('Y-m-d H:i:s', strtotime('+1 hour'));
                self::banIp($pdo, $ip, "1 dakika içinde 10+ hatalı giriş denemesi (Spam/Bruteforce)", $bitis_zamani);
                return true;
            }
        } catch (PDOException $e) {
            // Hata durumunda sessizce devam et
        }
        return false;
    }

    // IP'yi Veritabanına Yasaklı Olarak Ekle
    private static function banIp($pdo, $ip, $sebep, $bitis_zamani) {
        $zaman = date('Y-m-d H:i:s');
        
        try {
            $check_sql = "SELECT id FROM ip_blacklist WHERE ip_adresi = :ip AND aktif_mi = 1";
            $stmt = $pdo->prepare($check_sql);
            $stmt->execute([':ip' => $ip]);
            if ($stmt->fetch()) return;

            $sql = "INSERT INTO ip_blacklist (ip_adresi, sebep, baslangic_zamani, bitis_zamani, aktif_mi) 
                    VALUES (:ip, :sebep, :baslangic, :bitis, 1)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':ip'        => $ip,
                ':sebep'     => $sebep,
                ':baslangic' => $zaman,
                ':bitis'     => $bitis_zamani
            ]);

            $log_mesaji = "[$zaman] !!! KRİTİK GÜVENLİK !!! IP ENGELLEME UYGULANDI: $ip | Sebep: $sebep" . PHP_EOL;
            file_put_contents('whiteshark_security.log', $log_mesaji, FILE_APPEND);
        } catch (PDOException $e) {
            $hata_mesaji = "[$zaman] BAN TABLOSU YAZMA HATASI: " . $e->getMessage() . PHP_EOL;
            file_put_contents('whiteshark_security.log', $hata_mesaji, FILE_APPEND);
        }
    }

    // Mevcut loglama metodu
    public static function logIsteği($pdo, $tc_no, $durum) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Bilinmiyor';
        $tarayici = $_SERVER['HTTP_USER_AGENT'] ?? 'Bilinmiyor';
        $zaman = date('Y-m-d H:i:s');
        
        if (strlen($tc_no) === 11) {
            $maskeli_tc = substr($tc_no, 0, 3) . "*****" . substr($tc_no, -3);
        } else {
            $maskeli_tc = substr($tc_no, 0, 2) . "..." . substr($tc_no, -2);
        }
        
        $log_mesaji = "[$zaman] IP: $ip | TC: $maskeli_tc | Durum: $durum | Cihaz: $tarayici" . PHP_EOL;
        file_put_contents('whiteshark_security.log', $log_mesaji, FILE_APPEND);

        try {
            $log_sql = "INSERT INTO security_logs (tc_no, durum, ip_adresi, cihaz, zaman) 
                        VALUES (:tc_no, :durum, :ip_adresi, :cihaz, :zaman)";
            $log_stmt = $pdo->prepare($log_sql);
            $log_stmt->execute([
                ':tc_no'     => $maskeli_tc,
                ':durum'     => $durum,
                ':ip_adresi' => $ip,
                ':cihaz'     => $tarayici,
                ':zaman'     => $zaman
            ]);
        } catch (PDOException $e) {
            $hata_mesaji = "[$zaman] LOG VERİTABANI HATASI: " . $e->getMessage() . PHP_EOL;
            file_put_contents('whiteshark_security.log', $hata_mesaji, FILE_APPEND);
        }
    }
}

// KRİTİK ADIM: GİRİŞ DENETİMİ BAŞLAMADAN ÖNCE IP BANLI MI KONTROLÜ
if (WhiteShark::isIpBanned($pdo)) {
    header("Location: index.php?error=" . urlencode("Çok fazla hatalı giriş denemesi nedeniyle erişiminiz kısıtlandı!"));
    exit();
}

// POST verilerinde gerekli temel alanların gelip gelmediğini kontrol ediyoruz
if (isset($_POST['tc_no']) && isset($_POST['password'])) {

    function validate($data){
        return htmlspecialchars(stripslashes(trim($data)), ENT_QUOTES, 'UTF-8');
    }

    $tc_no = validate($_POST['tc_no']);
    $password = $_POST['password'];

    // BENI HATIRLA ÇEREZ MEKANİZMASI
    if (isset($_POST['beni_hatirla'])) {
        setcookie("remember_tc", $tc_no, time() + (86400 * 30), "/", "", false, true);
    } else {
        if (isset($_COOKIE['remember_tc'])) {
            setcookie("remember_tc", "", time() - 3600, "/");
        }
    }

    // BOŞLUK KONTROLÜ
    if (empty($tc_no) || empty($password)) {
        WhiteShark::logIsteği($pdo, $tc_no, "BAŞARISIZ - Boş Form Gönderimi");
        WhiteShark::checkAndBan($pdo);
        header("Location: index.php?error=" . urlencode("TC Kimlik No ve Şifre boş bırakılamaz"));
        exit();
    }

    // CAPTCHA KONTROLÜ (Yalnızca hatalı giriş sayısı >= 3 ise tetiklenir)
    $hatali_giriş_sayisi = WhiteShark::getFailedAttemptsCount($pdo);
    if ($hatali_giriş_sayisi >= 3) {
        if (!isset($_POST['captcha_girilen']) || trim($_POST['captcha_girilen']) !== ($_SESSION['captcha'] ?? '')) {
            WhiteShark::logIsteği($pdo, $tc_no, "BAŞARISIZ - Hatalı veya Geçersiz Captcha Kodu");
            WhiteShark::checkAndBan($pdo);
            header("Location: index.php?error=" . urlencode("Güvenlik kodu hatalı (Büyük/Küçük harfe dikkat edin)!"));
            exit();
        }
    }

    // Aktif personeli getir
    $sql = "SELECT Personel_ID, TC_No, Ad_Soyad, Unvan, Rol, Sifre FROM personel WHERE TC_No = :tc_no AND Aktif_Mi = 1";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':tc_no' => $tc_no]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Şifre kontrolü (Hash'li veya Düz Metin)
            if (password_verify($password, $user['Sifre']) || $password === $user['Sifre']) {
                
                $_SESSION['Personel_ID'] = $user['Personel_ID'];
                $_SESSION['TC_No']       = $user['TC_No'];
                $_SESSION['Ad_Soyad']    = $user['Ad_Soyad'];
                $_SESSION['Unvan']       = $user['Unvan'];
                $_SESSION['Rol']         = $user['Rol'];

                unset($_SESSION['captcha']);

                $durum_mesaji = password_verify($password, $user['Sifre']) ? "BAŞARILI - Kullanıcı sisteme giriş yaptı" : "BAŞARILI - Düz metin şifre ile giriş yapıldı";
                WhiteShark::logIsteği($pdo, $tc_no, $durum_mesaji);

                header("Location: panel.php");
                exit();
            } else {
                WhiteShark::logIsteği($pdo, $tc_no, "BAŞARISIZ - Hatalı Şifre Denemesi");
                WhiteShark::checkAndBan($pdo);
                header("Location: index.php?error=" . urlencode("Hatalı şifre girdiniz"));
                exit();
            }
        } else {
            WhiteShark::logIsteği($pdo, $tc_no, "BAŞARISIZ - Kullanıcı bulunamadı veya pasif hesap");
            WhiteShark::checkAndBan($pdo);
            header("Location: index.php?error=" . urlencode("Kullanıcı bulunamadı veya hesabınız pasif"));
            exit();
        }

    } catch (PDOException $e) {
        WhiteShark::logIsteği($pdo, $tc_no, "SİSTEM HATASI - " . $e->getMessage());
        die("Sorgu hatası: " . $e->getMessage());
    }
} else {
    header("Location: index.php");
    exit();
}
?>