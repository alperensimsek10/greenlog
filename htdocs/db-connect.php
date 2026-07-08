<?php
    date_default_timezone_set('Europe/Istanbul');
    
// Hata raporlama (Geliştirme aşamasında açık kalmalı)
ini_set('display_errors', 1);
error_reporting(E_ALL);

$host = "";
$db   = "";
$user = "";
$pass = "";

try {
    // Panel.php için PDO bağlantısı (UTF-8 Ayarlı)
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET time_zone = '+03:00';");

    $pdo->exec("SET NAMES 'utf8mb4'");
    $pdo->exec("SET CHARACTER SET utf8mb4");
    $pdo->exec("SET COLLATION_CONNECTION = 'utf8mb4_unicode_ci'");
    
    // Login.php için MySQLi bağlantısı
    $con = new mysqli($host, $user, $pass, $db);
    $con->set_charset("utf8mb4");

    if ($con->connect_error) {
        die("MySQLi Bağlantı hatası: " . $con->connect_error);
    }
} catch (PDOException $e) {
    die("PDO Veritabanı bağlantı hatası: " . $e->getMessage());
}
?>