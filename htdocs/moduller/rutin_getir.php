<?php
session_start();
include 'db_baglantisi.php';

$personel_id = $_SESSION['Personel_ID'];
// Rutinleri veritabanından çekip grupluyoruz
$query = $pdo->prepare("SELECT Rutin_Adi, GROUP_CONCAT(Ekipman_ID) as idler 
                        FROM gorev_ekipmanlari 
                        WHERE Is_Rutin = 1 AND Personel_ID = ? 
                        GROUP BY Rutin_Adi");
$query->execute([$personel_id]);
$sonuc = $query->fetchAll(PDO::FETCH_ASSOC);

$rutin_dizi = [];
foreach ($sonuc as $satir) {
    $rutin_dizi[$satir['Rutin_Adi']] = explode(',', $satir['idler']);
}

echo json_encode($rutin_dizi);
?>