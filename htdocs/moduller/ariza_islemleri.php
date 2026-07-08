<?php
// Bu fonksiyonu her yere kolayca dahil edebilirsin
function arızaKaydet($pdo, $ekipman_id, $rapor) {
    try {
        $pdo->beginTransaction();
        
        // 1. Ekipmanı Arızalı yap
        $pdo->prepare("UPDATE ekipman SET Durum = 'Arızalı' WHERE Ekipman_ID = ?")->execute([$ekipman_id]);
        
        // 2. Log'u kapat (Eğer o an bir görevdeyse)
        $pdo->prepare("UPDATE ekipman_log SET Teslim_Tarihi = NOW(), Gun_Sonu_Raporu = CONCAT(IFNULL(Gun_Sonu_Raporu,''), ?) 
                       WHERE Ekipman_ID = ? AND Teslim_Tarihi IS NULL")
            ->execute([" - [ARIZALI: $rapor]", $ekipman_id]);
            
        $pdo->commit();
        return true;
    } catch(Exception $e) {
        $pdo->rollBack();
        return false;
    }
}
?>