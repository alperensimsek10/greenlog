<?php
// .env dosyasının yolunu belirliyoruz
$env_dosyasi = __DIR__ . '/.env';

// .env dosyasını oku ve diziye çevir
if (file_exists($env_dosyasi)) {
    $env = parse_ini_file($env_dosyasi);
} else {
    die(".env dosyasi bulunamadi! Lutfen ayarlari yapilandirin.");
}

// Bilgileri .env'den çekiyoruz
$host = $env['DB_HOST'];
$dbname = $env['DB_NAME'];
$username = $env['DB_USER'];
$password = $env['DB_PASS'];

try {
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    $pdo = new PDO($dsn, $username, $password, $options);

} catch (PDOException $e) {
    die("Veritabanı bağlantı hatası: Lütfen sistem yöneticisine başvurun.");
}
?>