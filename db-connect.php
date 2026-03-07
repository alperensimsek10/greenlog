<?php
// Tüm hataları ekranda göstermesi için (Sadece geliştirme aşamasında kullanılır)
error_reporting(E_ALL);
ini_set('display_errors', 1);

$env_dosyasi = __DIR__ . '/.env';

// .env dosyası var mı kontrol et
if (!file_exists($env_dosyasi)) {
    die("HATA: .env dosyası bulunamadı! Dosya yolunu kontrol edin: " . $env_dosyasi);
}

// parse_ini_file bazı sunucularda kapalı olabilir, o yüzden @ koymuyoruz ki hata varsa görelim
$env = parse_ini_file($env_dosyasi);

if (!$env) {
    die("HATA: .env dosyası okunamadı! İçindeki formatın doğru olduğundan emin olun.");
}

$host = $env['DB_HOST'];
$dbname = $env['DB_NAME'];
$username = $env['DB_USER'];
$password = $env['DB_PASS'];

// Global olarak $pdo değişkenini tanımlıyoruz
global $pdo;

try {
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    $pdo = new PDO($dsn, $username, $password, $options);

} catch (PDOException $e) {
    die("Veritabanı bağlantı hatası: " . $e->getMessage());
}
?>
