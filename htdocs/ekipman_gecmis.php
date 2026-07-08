<?php
/**
 * GreenLog - Ekipman Geçmişi Veri Sağlayıcı (AJAX - Görev Bağlantılı Premium Sürüm)
 */
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Orijinal veritabanı bağlantısı
require_once "db-connect.php"; 

if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo '<p style="color:#ef4444; text-align:center;">Geçersiz ekipman parametresi.</p>';
    exit;
}

$ekipman_id = (int)$_GET['id'];

try {
    // YENİ: gorevler tablosunu LEFT JOIN ile bağlıyor, Baslik ve Rapor'u çekiyoruz!
    $sorgu = $pdo->prepare("SELECT l.*, p.Ad_Soyad, g.Baslik as Gorev_Adi, g.Gun_Sonu_Raporu as Gorev_Raporu 
                            FROM ekipman_log l 
                            LEFT JOIN personel p ON l.Personel_ID = p.Personel_ID 
                            LEFT JOIN gorevler g ON l.Gorev_ID = g.Gorev_ID 
                            WHERE l.Ekipman_ID = ? 
                            ORDER BY l.Log_ID DESC 
                            LIMIT 10");
    $sorgu->execute([$ekipman_id]);
    $kayitlar = $sorgu->fetchAll();
} catch (Exception $e) {
    echo '<p style="color:#ef4444; text-align:center;">Veritabanı hatası: ' . htmlspecialchars($e->getMessage()) . '</p>';
    exit;
}

if (!$kayitlar) {
    echo '<div style="text-align:center; color:#64748b; padding:40px 0;">
            <i class="fas fa-history" style="font-size:28px; margin-bottom:10px; display:block; color:#cbd5e1;"></i>
            Bu cihaza ait henüz bir kullanım veya arıza geçmişi kaydı bulunmuyor.
          </div>';
    exit;
}
?>

<div class="history-timeline">
    <?php foreach($kayitlar as $k): 
        $ariza_notu = $k['Gun_Sonu_Raporu'] ?? ''; // Kendi tablosundaki not (Genelde arızalar buraya düşer)
        $gorev_raporu = $k['Gorev_Raporu'] ?? ''; // Görevler tablosundaki merkezi rapor
        
        // 1. Durum Tespiti: Kayıt bir arıza bildirimi mi içeriyor?
        $is_ariza = (strpos($ariza_notu, 'ARIZA') !== false);
        
        // 2. Akıllı Rapor Seçimi: Arıza varsa onu göster, yoksa Merkezi Görev Raporunu göster
        if ($is_ariza) {
            $rapor_metni = $ariza_notu;
        } else {
            $rapor_metni = !empty($gorev_raporu) ? $gorev_raporu : (!empty($ariza_notu) ? $ariza_notu : 'Rapor bırakılmamış.');
        }
        
        // 3. Durum Tespiti: Cihaz şu an hala işlemde / teslim edilmedi mi?
        $durum_aktif = ($k['Teslim_Tarihi'] === NULL || $k['Teslim_Tarihi'] == '0000-00-00 00:00:00'); 

        // Renk ve Stil Dinamik Karar Mekanizması
        if ($is_ariza) {
            // Arıza Kayıtları -> KIRMIZI
            $dot_color = '#ef4444'; 
            $badge_text = 'Arıza Durumu';
            $badge_style = 'background:#fef2f2; color:#991b1b;';
        } elseif ($durum_aktif) {
            // Şu an Aktif Kullanımda -> MAVİ
            $dot_color = '#3b82f6'; 
            $badge_text = 'Şu An Kullanımda';
            $badge_style = 'background:#eff6ff; color:#1e40af;';
        } else {
            // Geçmiş, Sorunsuz Kapatılmış Teslimat -> GRİ
            $dot_color = '#94a3b8'; 
            $badge_text = 'Teslim Edildi';
            $badge_style = 'background:#f1f5f9; color:#475569;';
        }
    ?>
        <div class="history-item" style="position: relative; padding-left: 35px; margin-bottom: 20px;">
            <div class="history-icon" style="position: absolute; left: 0; top: 4px; width: 14px; height: 14px; border-radius: 50%; background: <?= $dot_color ?>; border: 3px solid #fff; box-shadow: 0 0 0 2px <?= $dot_color ?>;"></div>
            
            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:14px 16px;">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                    <span style="font-weight:700; font-size:14px; color:#0f172a;">
                        <i class="fas fa-user" style="color:#64748b; font-size:12px; margin-right:4px;"></i> 
                        <?= htmlspecialchars($k['Ad_Soyad'] ?? 'Bilinmeyen Personel') ?>
                    </span>
                    
                    <span style="<?= $badge_style ?> font-size:11px; font-weight:700; padding:3px 8px; border-radius:6px;">
                        <?= $badge_text ?>
                    </span>
                </div>

                <?php if(!empty($k['Gorev_Adi'])): ?>
                    <div style="font-size:13px; color:#3b82f6; margin-top:10px; font-weight:700; display:flex; align-items:center; gap:5px;">
                        <i class="fas fa-clipboard-check"></i> <?= htmlspecialchars($k['Gorev_Adi']) ?>
                    </div>
                <?php endif; ?>

                <div style="font-size:13px; color:#475569; margin:<?= !empty($k['Gorev_Adi']) ? '5px' : '10px' ?> 0 10px 0; line-height:1.5;">
                    <strong>İşlem Notu:</strong> <?= htmlspecialchars($rapor_metni) ?>
                </div>

                <div style="display:flex; gap:15px; font-size:11px; color:#94a3b8; border-top:1px dashed #e2e8f0; padding-top:8px; margin-top:4px; flex-wrap:wrap;">
                    <span><i class="far fa-calendar-alt"></i> <strong>Alış / Bildirim:</strong> <?= date('d.m.Y H:i', strtotime($k['Alis_Tarihi'])) ?></span>
                    <?php if(!$durum_aktif): ?>
                        <span><i class="far fa-calendar-check"></i> <strong>Teslim / Onarım:</strong> <?= date('d.m.Y H:i', strtotime($k['Teslim_Tarihi'])) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>