<?php
// Oturum kontrolü (Güvenlik için önemli)
session_start();
if (!isset($_SESSION['Personel_ID'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Yetkisiz erişim']);
    exit;
}

// Veritabanı bağlantısını dahil et
require_once 'db-connect.php'; 

// Hata raporlamayı JSON çıktısını bozmaması için kapatalım veya sadece kritik hataları alalım
error_reporting(0); 

$period = isset($_GET['period']) ? $_GET['period'] : 'gunluk';
header('Content-Type: application/json; charset=utf-8');

try {
    // Temel sorgu (Sadece ayrılanlar)
    $sql = "SELECT Ad_Soyad, TC_Kimlik, Giris_Zamani, Cikis_Zamani, Ziyaret_Nedeni 
            FROM ziyaretciler 
            WHERE Durum = 'Ayrıldı' ";

    // Periyoda göre filtre ekle
    if ($period == 'gunluk') {
        $sql .= " AND DATE(Giris_Zamani) = CURDATE()";
    } elseif ($period == 'aylik') {
        $sql .= " AND MONTH(Giris_Zamani) = MONTH(CURRENT_DATE()) 
                  AND YEAR(Giris_Zamani) = YEAR(CURRENT_DATE())";
    } elseif ($period == 'yillik') {
        $sql .= " AND YEAR(Giris_Zamani) = YEAR(CURRENT_DATE())";
    }

    $sql .= " ORDER BY Cikis_Zamani DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Tarih formatlarını güzelleştirelim
    foreach ($results as &$row) {
        $row['Giris_Zamani'] = date('d.m.Y H:i', strtotime($row['Giris_Zamani']));
        $row['Cikis_Zamani'] = date('d.m.Y H:i', strtotime($row['Cikis_Zamani']));
    }

    echo json_encode($results);

} catch (PDOException $e) {
    echo json_encode(['error' => 'Sorgu hatası: ' . $e->getMessage()]);
}
?>