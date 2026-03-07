<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Bağlantı dosyamızı dahil ediyoruz (İçinden $pdo nesnesi gelecek)
require_once "db-connect.php";

if (isset($_POST['username']) && isset($_POST['password'])) {

    // Güvenlik temizliği
    function validate($data){
        return htmlspecialchars(stripslashes(trim($data)));
    }

    // Formdaki 'username' inputunu biz TC_No olarak kabul edeceğiz
    $tc_no = validate($_POST['username']);
    $password = validate($_POST['password']);

    if (empty($tc_no)) {
        header("Location: index.php?error=TC Kimlik Numarası gerekli");
        exit();
    } else if (empty($password)) {
        header("Location: index.php?error=Şifre gerekli");
        exit();
    } else {
        // Bizim veritabanına (PERSONEL tablosuna) göre SQL sorgusu
        $sql = "SELECT Personel_ID, TC_No, Ad_Soyad, Unvan, Sifre FROM PERSONEL WHERE TC_No = :tc_no";

        try {
            $stmt = $pdo->prepare($sql);
            // PDO ile parametreyi güvenli bir şekilde bağlıyoruz
            $stmt->execute([':tc_no' => $tc_no]);

            // Eğer böyle bir TC_No varsa kullanıcı verilerini çek
            $user = $stmt->fetch();

            if ($user) {
                // Şifre Kontrolü
                // (NOT: İleride şifreleri password_hash() ile şifrelediğinde burayı password_verify() ile değiştirmelisin)
                if ($password === $user['Sifre']) {

                    // Giriş başarılı! Session (Oturum) değişkenlerini bizim tabloya göre ayarlıyoruz
                    $_SESSION['Personel_ID'] = $user['Personel_ID'];
                    $_SESSION['TC_No'] = $user['TC_No'];
                    $_SESSION['Ad_Soyad'] = $user['Ad_Soyad'];
                    $_SESSION['Unvan'] = $user['Unvan']; // Rol yönetimi için unvanı da aldık

                    // Başarılı giriş sonrası panele yönlendir
                    header("Location: panel.php");
                    exit();

                } else {
                    header("Location: index.php?error=TC Kimlik No veya şifre hatalı");
                    exit();
                }

            } else {
                header("Location: index.php?error=TC Kimlik No veya şifre hatalı");
                exit();
            }

        } catch (PDOException $e) {
            die("Sorgu hatası: " . $e->getMessage());
        }
    }

} else {
    header("Location: index.php");
    exit();
}
?>