<?php
/**
 * GreenLog - Sera Detay ve Yönetim Paneli (v3.1 - Full Responsive & Secure)
 */

// 1. ID Kontrolü ve Güvenlik Filtresi
$Sera_ID = 0;
if (isset($_GET['id'])) {
    $Sera_ID = (int)$_GET['id'];
} elseif (isset($_GET['sera_id'])) {
    $Sera_ID = (int)$_GET['sera_id'];
}

if ($Sera_ID <= 0) {
    echo '<div style="padding:60px 20px; text-align:center; color:#ef4444; font-family:\'Plus Jakarta Sans\',sans-serif; background:white; border-radius:24px; margin:20px; box-shadow: 0 20px 40px rgba(0,0,0,0.05); border:1px solid #f1f5f9;">
            <i class="fas fa-exclamation-triangle fa-4x" style="margin-bottom:20px; color:#f59e0b;"></i><br>
            <h2 style="margin:0; color:#0f172a; font-weight:800;">Geçersiz Sera Erişimi</h2>
            <p style="color:#64748b; margin-top:10px; margin-bottom:20px;">Lütfen geçerli bir sera seçerek tekrar deneyin.</p>
            <a href="panel.php?sayfa=seralar" style="color:white; background:#10b981; padding:12px 24px; border-radius:12px; font-weight:700; text-decoration:none; display:inline-block; transition:0.2s;">← Listeye Dön</a>
          </div>';
    return; 
}

// --- DURUM GÜNCELLEME İŞLEMİ (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['durum_degistir'])) {
    $yeni_durum = (int)$_POST['yeni_durum'] === 1 ? 1 : 0;
    $guncelle = $pdo->prepare("UPDATE sera SET Aktif_Mi = ? WHERE Sera_ID = ?");
    $guncelle->execute([$yeni_durum, $Sera_ID]);
    
    header("Location: panel.php?sayfa=sera_detay&id=" . $Sera_ID);
    exit;
}

// 2. Veri Çekme İşlemleri (Güvenli ve Optimize)
try {
    $sorgu = $pdo->prepare("SELECT * FROM sera WHERE Sera_ID = ?");
    $sorgu->execute([$Sera_ID]);
    $sera = $sorgu->fetch(PDO::FETCH_ASSOC);

    if (!$sera) { 
        echo '<div style="padding:50px; text-align:center; color:#ef4444; font-family:sans-serif;"><h3>Sera bulunamadı veya silinmiş.</h3></div>';
        return;
    }

    $ekipman_sorgu = $pdo->prepare("SELECT * FROM ekipman WHERE Sera_ID = ? ORDER BY Ekipman_Adi ASC");
    $ekipman_sorgu->execute([$Sera_ID]);
    $ekipmanlar = $ekipman_sorgu->fetchAll(PDO::FETCH_ASSOC);

    $ozet_sorgu = $pdo->prepare("
        SELECT t.Takson_Adi, COUNT(b.Bitki_ID) as adet 
        FROM bitki b 
        LEFT JOIN takson t ON b.Takson_ID = t.Takson_ID 
        WHERE b.Sera_ID = ? AND b.Aktiflik = 1 
        GROUP BY b.Takson_ID, t.Takson_Adi
        ORDER BY adet DESC
    ");
    $ozet_sorgu->execute([$Sera_ID]);
    $ozetler = $ozet_sorgu->fetchAll(PDO::FETCH_ASSOC);

    $liste_sorgu = $pdo->prepare("
        SELECT b.*, t.Takson_Adi 
        FROM bitki b 
        LEFT JOIN takson t ON b.Takson_ID = t.Takson_ID 
        WHERE b.Sera_ID = ? 
        ORDER BY b.Ekim_Tarihi DESC
    ");
    $liste_sorgu->execute([$Sera_ID]);
    $bitkiler = $liste_sorgu->fetchAll(PDO::FETCH_ASSOC);

    $toplam_bitki = 0;
    foreach ($ozetler as $o) { $toplam_bitki += (int)$o['adet']; }
    $kapasite = 100; 
    $doluluk = ($kapasite > 0) ? ($toplam_bitki / $kapasite) * 100 : 0;
    if ($doluluk > 100) $doluluk = 100;

    $toplam_tur = count($ozetler);

} catch (PDOException $e) {
    exit("Veritabanı hatası: " . htmlspecialchars($e->getMessage()));
}
?>

<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />

<style>
    :root {
        --gl-green: #10b981;
        --gl-green-dark: #059669;
        --gl-green-light: #ecfdf5;
        --gl-blue: #3b82f6;
        --gl-blue-light: #eff6ff;
        --gl-red: #ef4444;
        --gl-red-light: #fef2f2;
        --gl-text: #0f172a;
        --gl-text-muted: #64748b;
        --gl-border: #e2e8f0;
        --gl-shadow: 0 10px 30px rgba(15, 23, 42, 0.04);
    }

    .sd-wrapper { font-family: 'Plus Jakarta Sans', sans-serif; color: var(--gl-text); max-width: 1400px; margin: 0 auto; padding: 15px; box-sizing: border-box; }
    .sd-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; background: white; padding: 25px; border-radius: 20px; box-shadow: var(--gl-shadow); border: 1px solid var(--gl-border); flex-wrap: wrap; gap: 20px; }
    .header-info { flex: 1; min-width: 250px; }
    .sd-header h2 { margin: 0; font-size: 1.6rem; font-weight: 800; display: flex; align-items: center; flex-wrap: wrap; gap: 12px; color: #0f172a; }
    
    .status-badge { font-size: 0.7rem; padding: 5px 12px; border-radius: 50px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; }
    .status-active { background: var(--gl-green-light); color: var(--gl-green); border: 1px solid rgba(16, 185, 129, 0.3); }
    .status-passive { background: var(--gl-red-light); color: var(--gl-red); border: 1px solid rgba(239, 68, 68, 0.3); }

    .action-group { display: flex; gap: 12px; flex-wrap: wrap; width: 100%; }
    @media (min-width: 768px) { .action-group { width: auto; } }

    .sd-btn-add { background: var(--gl-green); color: white; padding: 12px 20px; border-radius: 12px; text-decoration: none; font-weight: 700; font-size: 0.88rem; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s ease; border: none; cursor: pointer; flex: 1; justify-content: center; white-space: nowrap; }
    .sd-btn-add:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(16, 185, 129, 0.2); }
    .sd-btn-blue { background: var(--gl-blue); }
    .sd-btn-blue:hover { box-shadow: 0 5px 15px rgba(59, 130, 246, 0.2); }
    
    .sd-btn-status { background: white; padding: 12px 20px; border-radius: 12px; font-weight: 700; font-size: 0.88rem; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s ease; cursor: pointer; border: 1px solid var(--gl-border); flex: 1; justify-content: center; }
    .sd-btn-status:hover { background: #f8fafc; transform: translateY(-2px); }
    
    .btn-deactivate { color: var(--gl-red); border-color: rgba(239, 68, 68, 0.3); }
    .btn-deactivate:hover { background: var(--gl-red-light); }
    .btn-activate { color: var(--gl-green); border-color: rgba(16, 185, 129, 0.3); }
    .btn-activate:hover { background: var(--gl-green-light); }

    .sd-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 25px; }
    .sd-stat-card { background: white; padding: 22px; border-radius: 16px; box-shadow: var(--gl-shadow); display: flex; align-items: center; gap: 18px; border: 1px solid var(--gl-border); }
    .sd-stat-icon { width: 52px; height: 52px; border-radius: 14px; background: var(--gl-green-light); color: var(--gl-green); display: flex; align-items: center; justify-content: center; font-size: 1.4rem; flex-shrink: 0; }

    .sd-main-grid { display: grid; grid-template-columns: 1fr; gap: 25px; align-items: start; }
    @media (min-width: 1200px) { .sd-main-grid { grid-template-columns: 440px 1fr; } }

    .sd-glass-card { background: white; border-radius: 20px; padding: 22px; box-shadow: var(--gl-shadow); border: 1px solid var(--gl-border); }
    .sd-card-title { font-size: 1.05rem; font-weight: 800; margin-top: 0; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; color: #0f172a; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px; }

    .sd-table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; margin: 0 -5px; padding-bottom: 5px; }
    .sd-table { width: 100%; border-collapse: separate; border-spacing: 0 10px; min-width: 550px; }
    .sd-table th { text-align: left; padding: 10px 16px; color: var(--gl-text-muted); font-size: 0.78rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; }
    .sd-table td { padding: 16px; background: #f8fafc; border-top: 1px solid #f1f5f9; border-bottom: 1px solid #f1f5f9; font-size: 0.92rem; transition: all 0.2s; }
    .sd-table tr:hover td { background: #f1f5f9; }
    .sd-table td:first-child { border-left: 1px solid #f1f5f9; border-radius: 12px 0 0 12px; }
    .sd-table td:last-child { border-right: 1px solid #f1f5f9; border-radius: 0 12px 12px 0; }

    .sd-list-item { display: flex; justify-content: space-between; align-items: center; background: #f8fafc; padding: 14px 16px; border-radius: 12px; margin-bottom: 12px; border: 1px solid #f1f5f9; transition: all 0.2s; gap: 10px; }
    .sd-list-item:hover { border-color: #cbd5e1; background: #fff; transform: translateX(3px); }
    .sd-list-info { flex-grow: 1; }
    .sd-list-actions { display: flex; align-items: center; gap: 6px; flex-shrink: 0; }

    .sd-btn-edit-sm { color: var(--gl-blue); background: white; border: 1px solid var(--gl-border); width: 32px; height: 32px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; transition: all 0.2s; text-decoration: none; }
    .sd-btn-edit-sm:hover { background: var(--gl-blue-light); border-color: rgba(59, 130, 246, 0.3); transform: scale(1.05); }
    
    /* Yeni Kırmızı Arıza Butonu Stili */
    .sd-btn-warn-sm { color: var(--gl-red); background: white; border: 1px solid var(--gl-border); width: 32px; height: 32px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; transition: all 0.2s; text-decoration: none; }
    .sd-btn-warn-sm:hover { background: var(--gl-red-light); border-color: rgba(239, 68, 68, 0.3); transform: scale(1.05); }
</style>

<div class="sd-wrapper animate__animated animate__fadeIn">

    <div class="sd-header animate__animated animate__fadeInDown animate__fast">
        <div class="header-info">
            <h2>
                <i class="fas fa-warehouse" style="color:var(--gl-blue);"></i> 
                <?= htmlspecialchars($sera['Sera_Adi']) ?>
                <?= $sera['Aktif_Mi'] ? '<span class="status-badge status-active">Aktif</span>' : '<span class="status-badge status-passive">Pasif</span>'; ?>
            </h2>
            <p style="margin-top: 8px; font-size: 0.9rem; color: var(--gl-text-muted); display: flex; align-items: center; gap: 6px;">
                <i class="fas fa-align-left" style="color: #94a3b8;"></i> <?= htmlspecialchars($sera['Aciklama'] ?: 'Açıklama belirtilmemiş.') ?>
            </p>
        </div>

        <div class="action-group">
            <a href="panel.php?sayfa=yeni_ekipman&from=sera_detay&sera_id=<?= $Sera_ID ?>" class="sd-btn-add sd-btn-blue">
                <i class="fas fa-microchip"></i> Yeni Ekipman
            </a>
            <a href="panel.php?sayfa=yeni_bitki&sera_id=<?= urlencode($Sera_ID) ?>" class="sd-btn-add">
                <i class="fas fa-seedling"></i> Yeni Bitki
            </a>
            <form method="POST" onsubmit="return confirm('Sera işletim durumunu değiştirmek istediğinize emin misiniz?');" style="flex:1; display:flex;">
                <input type="hidden" name="yeni_durum" value="<?= $sera['Aktif_Mi'] ? '0' : '1'; ?>">
                <button type="submit" name="durum_degistir" class="sd-btn-status <?= $sera['Aktif_Mi'] ? 'btn-deactivate' : 'btn-activate'; ?>">
                    <i class="fas <?= $sera['Aktif_Mi'] ? 'fa-power-off' : 'fa-play'; ?>"></i> 
                    Durum
                </button>
            </form>
        </div>
    </div>

    <div class="sd-stats-grid">
        <div class="sd-stat-card">
            <div class="sd-stat-icon"><i class="fas fa-seedling"></i></div>
            <div>
                <small style="color: var(--gl-text-muted); font-weight: 700; text-transform: uppercase; font-size:0.7rem; letter-spacing: 0.5px;">Aktif Bitki</small>
                <div style="font-size: 1.6rem; font-weight: 800; color: #1e293b;"><?= $toplam_bitki ?></div>
            </div>
        </div>

        <div class="sd-stat-card">
            <div class="sd-stat-icon" style="background:#f5f3ff; color:#7c3aed;"><i class="fas fa-leaf"></i></div>
            <div>
                <small style="color: var(--gl-text-muted); font-weight: 700; text-transform: uppercase; font-size:0.7rem; letter-spacing: 0.5px;">Toplam Tür</small>
                <div style="font-size: 1.6rem; font-weight: 800; color: #1e293b;"><?= $toplam_tur ?></div>
            </div>
        </div>
        
        <div class="sd-stat-card">
            <div class="sd-stat-icon" style="background:var(--gl-blue-light); color:var(--gl-blue);"><i class="fas fa-tools"></i></div>
            <div>
                <small style="color: var(--gl-text-muted); font-weight: 700; text-transform: uppercase; font-size:0.7rem; letter-spacing: 0.5px;">Toplam Ekipman</small>
                <div style="font-size: 1.6rem; font-weight: 800; color: #1e293b;"><?= count($ekipmanlar) ?></div>
            </div>
        </div>

        <div class="sd-stat-card">
            <div class="sd-stat-icon" style="background:#fff7ed; color:#f59e0b;"><i class="fas fa-chart-pie"></i></div>
            <div style="flex-grow:1;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <small style="font-weight: 700; text-transform: uppercase; font-size:0.7rem; color: var(--gl-text-muted); letter-spacing: 0.5px;">Sera Doluluk</small>
                    <span style="font-weight: 800; color: #f59e0b; font-size:0.95rem;">%<?= round($doluluk, 1) ?></span>
                </div>
                <div style="width: 100%; height: 7px; background: #f1f5f9; border-radius: 10px; margin-top: 8px; overflow: hidden; border: 1px solid #e2e8f0;">
                    <div style="width: <?= $doluluk ?>%; height: 100%; background: #f59e0b; border-radius:10px;"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="sd-main-grid">
        <div class="animate__animated animate__fadeInLeft animate__fast">
            
            <div class="sd-glass-card" style="margin-bottom: 25px;">
                <div class="sd-card-title"><i class="fas fa-server" style="color: var(--gl-blue);"></i> Donanım ve Ekipmanlar</div>
                <?php if(empty($ekipmanlar)): ?>
                    <p style="color:var(--gl-text-muted); text-align:center; font-size:0.85rem; padding: 10px 0;"><i class="fas fa-folder-open"></i> Bağlı ekipman bulunamadı.</p>
                <?php else: ?>
                    <?php foreach($ekipmanlar as $ekip): ?>
                        <div class="sd-list-item">
                            <div class="sd-list-info">
                                <div style="font-weight: 700; font-size: 0.9rem; color: #1e293b;"><?= htmlspecialchars($ekip['Ekipman_Adi']) ?></div>
                                <small style="color:var(--gl-text-muted); font-size: 0.75rem;">S/N: <?= htmlspecialchars($ekip['Seri_No']) ?></small>
                            </div>
                            <div class="sd-list-actions">
                                <span class="status-badge <?= ($ekip['Durum'] === 'Aktif') ? 'status-active' : 'status-passive'; ?>" style="font-size:0.65rem; margin-right:4px;">
                                    <?= htmlspecialchars($ekip['Durum']) ?>
                                </span>
                                
                                <?php if($ekip['Durum'] !== 'Arızalı' && $ekip['Durum'] !== 'Hurda'): ?>
                                    <a href="panel.php?sayfa=ariza_bildir&ekipman_id=<?= urlencode($ekip['Ekipman_ID']) ?>&from=sera_detay&sera_id=<?= $Sera_ID ?>" class="sd-btn-warn-sm" title="Arıza Bildir">
                                        <i class="fas fa-exclamation-triangle" style="font-size:0.75rem;"></i>
                                    </a>
                                <?php endif; ?>

                                <a href="panel.php?sayfa=ekipman_duzenle&id=<?= urlencode($ekip['Ekipman_ID']) ?>&from=sera_detay&sera_id=<?= $Sera_ID ?>" class="sd-btn-edit-sm" title="Ekipmanı Düzenle">
                                    <i class="fas fa-edit" style="font-size:0.8rem;"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="sd-glass-card">
                <div class="sd-card-title"><i class="fas fa-chart-pie" style="color: #6366f1;"></i> Botanik Tür Dağılımı</div>
                <?php if(empty($ozetler)): ?>
                    <p style="color:var(--gl-text-muted); text-align:center; font-size:0.85rem; padding: 10px 0;"><i class="fas fa-leaf"></i> Botanik veri girişi yok.</p>
                <?php else: ?>
                    <?php foreach($ozetler as $ozet): ?>
                        <div class="sd-list-item">
                            <span style="font-weight: 600; font-size:0.9rem; color:#334155;"><?= htmlspecialchars($ozet['Takson_Adi'] ?: 'Bilinmeyen Tür') ?></span>
                            <span style="background:var(--gl-green); color:white; padding:3px 12px; border-radius:8px; font-weight:800; font-size:0.8rem;"><?= (int)$ozet['adet'] ?> Adet</span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="sd-glass-card animate__animated animate__fadeInRight animate__fast">
            <div class="sd-card-title"><i class="fas fa-th-list" style="color: var(--gl-green);"></i> Detaylı Envanter Listesi</div>
            <div class="sd-table-wrap">
                <?php if(empty($bitkiler)): ?>
                    <div style="text-align:center; padding:40px 20px; color: var(--gl-text-muted);">
                        <i class="fas fa-seedling fa-3x" style="margin-bottom:15px; color:#cbd5e1;"></i>
                        <p style="margin:0; font-size:0.95rem;">Bu seraya henüz hiç bitki ekilmemiş.</p>
                    </div>
                <?php else: ?>
                    <table class="sd-table">
                        <thead>
                            <tr>
                                <th>Botanik Tür / ID</th>
                                <th>Ekim Tarihi</th>
                                <th>Canlılık Durumu</th>
                                <th style="width: 50px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($bitkiler as $bitki): ?>
                            <tr>
                                <td>
                                    <strong style="color: #0f172a; font-size:0.95rem;"><?= htmlspecialchars($bitki['Takson_Adi'] ?: 'Belirtilmemiş') ?></strong>
                                    <br><small style="color:#94a3b8; font-weight: 600;">#<?= (int)$bitki['Bitki_ID'] ?></small>
                                </td>
                                <td style="color: #475569; font-weight: 500;">
                                    <?= date('d.m.Y', strtotime($bitki['Ekim_Tarihi'])) ?>
                                </td>
                                <td>
                                    <span style="font-size:0.75rem; font-weight:800; display: inline-flex; align-items: center; gap: 5px; color:<?= $bitki['Aktiflik'] ? 'var(--gl-green)' : 'var(--gl-red)'; ?>">
                                        <i class="fas <?= $bitki['Aktiflik'] ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                                        <?= $bitki['Aktiflik'] ? 'AKTİF' : 'PASİF'; ?>
                                    </span>
                                </td>
                                <td style="text-align:right;">
                                    <a href="panel.php?sayfa=duzenle&id=<?= urlencode($bitki['Bitki_ID']) ?>" style="color:var(--gl-green); background: white; border: 1px solid var(--gl-border); width: 36px; height: 36px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; transition: all 0.2s; text-decoration: none;" title="Bitki Düzenle">
                                        <i class="fas fa-edit" style="font-size:0.85rem;"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>