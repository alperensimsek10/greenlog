<?php
session_start();
include 'db_baglantisi.php'; // Bağlantı dosyan

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $personel_id = $_SESSION['Personel_ID'];
    $rutin_adi = $_POST['rutin_adi'];
    $ekipman_idler = $_POST['ekipman_idler']; // JS'ten dizi olarak gelir

    try {
        $pdo->beginTransaction();
        foreach ($ekipman_idler as $e_id) {
            // Gorev_ID NULL çünkü bu bir rutin, görev değil!
            $sql = "INSERT INTO gorev_ekipmanlari (Ekipman_ID, Is_Rutin, Rutin_Adi, Personel_ID) VALUES (?, 1, ?, ?)";
            $pdo->prepare($sql)->execute([$e_id, $rutin_adi, $personel_id]);
        }
        $pdo->commit();
        echo json_encode(['status' => 'success']);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error']);
    }
}