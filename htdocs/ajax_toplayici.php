<?php
// ajax_toplayici.php - Sadece numara hesaplayıp döner, HTML içermez!
require_once 'db-connect.php';

if (isset($_GET['toplayici_adi'])) {
    $t_adi = trim($_GET['toplayici_adi']);
    
    // YENİ DÜZENLEME: Sadece SİLİNMEMİŞ (Aktif_Mi = 1 olan) kayıtlar arasındaki en büyük numarayı buluyoruz.
    $stmt = $pdo->prepare("SELECT MAX(CAST(Toplayici_No AS UNSIGNED)) as max_no FROM arazi_defteri WHERE Toplayici = ? AND Aktif_Mi = 1");
    $stmt->execute([$t_adi]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 1 ekleyip sadece sayıyı ekrana bas
    $next_no = ($row['max_no'] ?? 0) + 1;
    echo $next_no;
}
?>