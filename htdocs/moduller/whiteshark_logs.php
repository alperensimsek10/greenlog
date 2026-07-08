<?php
// Doğrudan bu dosyaya erişimi engellemek için Admin Güvenlik Kontrolü
if (!isset($_SESSION['Personel_ID']) || $_SESSION['Rol'] !== 'Admin') {
    die("Erişim Yetkiniz Yok!");
}

// ========================================================
// İŞLEM: BAN KALDIRMA TALEBİ GELDİYSE
// ========================================================
$mesaj = "";
if (isset($_GET['action']) && $_GET['action'] === 'unban' && isset($_GET['id'])) {
    $ban_id = (int)$_GET['id'];
    try {
        $unban_sql = "UPDATE ip_blacklist SET aktif_mi = 0 WHERE id = :id";
        $unban_stmt = $pdo->prepare($unban_sql);
        $unban_stmt->execute([':id' => $ban_id]);
        $mesaj = "<div class='alert alert-success mb-3 small py-2'>IP engeli başarıyla kaldırıldı!</div>";
    } catch (PDOException $e) {
        $mesaj = "<div class='alert alert-danger mb-3 small py-2'>Hata oluştu: " . $e->getMessage() . "</div>";
    }
}

// ========================================================
// VERİ ÇEKME: GRAFİKLER VE ÖZET KARTLARI İÇİN İSTATİSTİKLER
// ========================================================
try {
    $toplam_istek = $pdo->query("SELECT COUNT(*) FROM security_logs")->fetchColumn();
    $basarili_giris = $pdo->query("SELECT COUNT(*) FROM security_logs WHERE durum LIKE 'BAŞARILI%'")->fetchColumn();
    $hatali_giris = $pdo->query("SELECT COUNT(*) FROM security_logs WHERE durum LIKE 'BAŞARISIZ%'")->fetchColumn();
    $aktif_ban_sayisi = $pdo->query("SELECT COUNT(*) FROM ip_blacklist WHERE aktif_mi = 1 AND (bitis_zamani IS NULL OR bitis_zamani > NOW())")->fetchColumn();

    // Renklerin etiketlerle tam eşleşmesi için sıralı çekiyoruz (Örn: Önce BAŞARILI'lar, sonra BAŞARISIZ'lar gelsin diye)
    $nedenler_query = $pdo->query("SELECT durum, COUNT(*) as adet FROM security_logs GROUP BY durum ORDER BY durum DESC");
    $nedenler_data = $nedenler_query->fetchAll(PDO::FETCH_ASSOC);

    $grafik_etiketler = [];
    $grafik_adetler = [];
    foreach ($nedenler_data as $row) {
        $grafik_etiketler[] = $row['durum'];
        $grafik_adetler[] = (int)$row['adet'];
    }

    $ban_query = $pdo->query("SELECT * FROM ip_blacklist WHERE aktif_mi = 1 AND (bitis_zamani IS NULL OR bitis_zamani > NOW()) ORDER BY id DESC");
    $banli_listesi = $ban_query->fetchAll(PDO::FETCH_ASSOC);

    $log_query = $pdo->query("SELECT * FROM security_logs ORDER BY id DESC LIMIT 100");
    $log_listesi = $log_query->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Veritabanı istatistik hatası: " . $e->getMessage());
}
?>

<style>
    :root {
        --bg-main: #f8fafc;
        --border-color: #e2e8f0;
        --text-main: #0f172a;
        --text-muted: #64748b;
        --primary: #3b82f6;
        --primary-hover: #2563eb;
        --danger: #ef4444;
        --danger-light: #fef2f2;
        --success: #10b981;
        --warning: #f59e0b;
    }

    /* --- GİRİŞ VE YÜKLENME ANİMASYONLARI --- */
    @keyframes pageFadeIn {
        from { opacity: 0; transform: translateY(15px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .animated-page-wrapper {
        animation: pageFadeIn 0.5s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        padding: 5px;
        width: 100%;
        box-sizing: border-box;
    }

    /* Sayaç Grid Yapısı */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 20px;
        margin-bottom: 25px;
    }

    .stat-card {
        background: white;
        border-radius: 16px;
        border: 1px solid var(--border-color);
        padding: 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
    }

    .stat-card .info h3 { margin: 0; font-size: 28px; font-weight: 700; color: var(--text-main); }
    .stat-card .info p { margin: 4px 0 0 0; font-size: 14px; color: var(--text-muted); font-weight: 500; }
    
    .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
    }

    /* Orta Bölüm Düzeni: Grafik ve IP Tablosu (Birebir Eşit Yükseklik) */
    .shark-main-grid {
        display: grid !important;
        grid-template-columns: 380px 1fr !important;
        gap: 24px !important;
        margin-bottom: 24px !important;
        align-items: stretch;
    }
    @media (max-width: 992px) {
        .shark-main-grid { grid-template-columns: 1fr !important; }
    }

    /* Modern Kart Yapısı */
    .gl-card {
        background: white;
        border-radius: 16px;
        border: 1px solid var(--border-color);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.04);
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }

    .gl-card-header {
        padding: 18px 24px;
        background: #ffffff;
        border-bottom: 1px solid var(--border-color);
        font-weight: 700;
        font-size: 15px;
        color: var(--text-main);
        display: flex;
        align-items: center;
        gap: 10px;
        flex-shrink: 0;
    }

    /* İçerik Gövdesi */
    .gl-card-body {
        padding: 24px;
        flex-grow: 1;
        position: relative;
        display: flex;
        flex-direction: column;
    }

    /* Kaydırma Özellikli Ban Listesi Tablo Alanı */
    .table-responsive-shark { 
        height: 325px; 
        overflow-y: auto; 
        width: 100%; 
    }

    /* Boş durum uyarı yazısını kart gövdesinde dikey ve yatay TAM ORTALAMA */
    .empty-ban-container {
        display: flex;
        align-items: center;
        justify-content: center;
        flex-grow: 1;
        width: 100%;
        color: var(--text-muted);
        font-style: italic;
        text-align: center;
    }
    
    .table-shark-custom { width: 100%; border-collapse: collapse; text-align: left; font-size: 14px; }
    .table-shark-custom th { background: #f8fafc; padding: 14px 24px; font-size: 12px; font-weight: 600; text-transform: uppercase; color: var(--text-muted); border-bottom: 1px solid var(--border-color); position: sticky; top: 0; z-index: 5; }
    .table-shark-custom td { padding: 16px 24px; border-bottom: 1px solid #f1f5f9; color: var(--text-main); vertical-align: middle; }
    .table-shark-custom tbody tr:hover { background: #f8fafc; }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="animated-page-wrapper">
    
    <?php echo $mesaj; ?>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="info">
                <h3><?php echo $toplam_istek; ?></h3>
                <p>Toplam İstek</p>
            </div>
            <div class="stat-icon" style="background: #eff6ff; color: var(--primary);"><i class="fas fa-shield-alt"></i></div>
        </div>
        <div class="stat-card">
            <div class="info">
                <h3 style="color: var(--success);"><?php echo $basarili_giris; ?></h3>
                <p>Başarılı Girişler</p>
            </div>
            <div class="stat-icon" style="background: #ecfdf5; color: var(--success);"><i class="fas fa-check-circle"></i></div>
        </div>
        <div class="stat-card">
            <div class="info">
                <h3 style="color: var(--danger);"><?php echo $hatali_giris; ?></h3>
                <p>Hatalı Denemeler</p>
            </div>
            <div class="stat-icon" style="background: #fef2f2; color: var(--danger);"><i class="fas fa-exclamation-triangle"></i></div>
        </div>
        <div class="stat-card">
            <div class="info">
                <h3 style="color: var(--warning);"><?php echo $aktif_ban_sayisi; ?></h3>
                <p>Aktif Banlı IP</p>
            </div>
            <div class="stat-icon" style="background: #fffbeb; color: var(--warning);"><i class="fas fa-ban"></i></div>
        </div>
    </div>

    <div class="shark-main-grid">
        <div class="gl-card">
            <div class="gl-card-header">
                <i class="fas fa-chart-pie" style="color: var(--primary);"></i> Giriş Durumları Analizi
            </div>
            <div class="gl-card-body" style="height: 325px; display: flex; align-items: center; justify-content: center;">
                <canvas id="sharkChart"></canvas>
            </div>
        </div>

        <div class="gl-card" style="border-color: #fee2e2;">
            <div class="gl-card-header" style="background: #fff5f5; color: #991b1b; border-bottom: 1px solid #fee2e2;">
                <i class="fas fa-user-slash"></i> Aktif Engellenen IP Adresleri
            </div>
            <div class="gl-card-body" style="padding: 0;">
                <?php if (count($banli_listesi) === 0): ?>
                    <div class="empty-ban-container">
                        <span><i class="fas fa-info-circle me-1"></i> Şu an aktif engellenen bir IP bulunmuyor.</span>
                    </div>
                <?php else: ?>
                    <div class="table-responsive-shark">
                        <table class="table-shark-custom">
                            <thead>
                                <tr>
                                    <th>IP Adresi</th>
                                    <th>Sebep</th>
                                    <th>Bitiş Süresi</th>
                                    <th style="text-align: right;">İşlem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($banli_listesi as $ban): ?>
                                    <tr>
                                        <td><code><?php echo htmlspecialchars($ban['ip_adresi']); ?></code></td>
                                        <td><span class="text-danger" style="font-weight: 500;"><?php echo htmlspecialchars($ban['sebep']); ?></span></td>
                                        <td>
                                            <?php if($ban['bitis_zamani']): ?>
                                                <span style="font-weight: 500;"><i class="far fa-clock" style="color: #64748b;"></i> <?php echo date('d.m.Y H:i:s', strtotime($ban['bitis_zamani'])); ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-danger px-2 py-1" style="font-size: 11px; border-radius: 6px;">Kalıcı</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align: right;">
                                            <a href="?sayfa=whiteshark_logs&action=unban&id=<?php echo $ban['id']; ?>" 
                                               class="btn btn-sm btn-link text-success p-0 text-decoration-none fw-bold" 
                                               style="font-size: 13px;"
                                               onclick="return confirm('IP engelini kaldırmak istediğinize emin misiniz?');">
                                                <i class="fas fa-unlock"></i> Engeli Kaldır
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="gl-card">
        <div class="gl-card-header">
            <i class="fas fa-list-alt" style="color: var(--text-muted);"></i> Detaylı Log Kayıtları (Son 100)
        </div>
        <div class="table-responsive-shark" style="max-height: 400px; height: auto;">
            <table class="table-shark-custom" style="font-size: 13px;">
                <thead style="position: sticky; top: 0; z-index: 10;">
                    <tr>
                        <th>Zaman</th>
                        <th>IP Adresi</th>
                        <th>Maskeli Kimlik</th>
                        <th>Durum Açıklaması</th>
                        <th>Cihaz Bilgisi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($log_listesi) === 0): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">Kayıtlı güvenlik logu bulunamadı.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($log_listesi as $log): ?>
                            <tr>
                                <td style="white-space: nowrap;"><strong><?php echo date('d.m.Y H:i:s', strtotime($log['zaman'])); ?></strong></td>
                                <td><code><?php echo htmlspecialchars($log['ip_adresi']); ?></code></td>
                                <td><?php echo htmlspecialchars($log['tc_no']); ?></td>
                                <td>
                                    <?php if (strpos($log['durum'], 'BAŞARILI') !== false): ?>
                                        <span class="badge px-2 py-1" style="border-radius: 6px; font-weight: 600; font-size: 11px; background-color: #ecfdf5; color: #10b981; border: 1px solid #a7f3d0;"><?php echo htmlspecialchars($log['durum']); ?></span>
                                    <?php else: ?>
                                        <span class="badge px-2 py-1" style="border-radius: 6px; font-weight: 600; font-size: 11px; background-color: #fef2f2; color: #ef4444; border: 1px solid #fca5a5;"><?php echo htmlspecialchars($log['durum']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted text-truncate" style="max-width: 250px;" title="<?php echo htmlspecialchars($log['cihaz']); ?>">
                                    <?php echo htmlspecialchars($log['cihaz']); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    if(window.sharkChartInstance){ window.sharkChartInstance.destroy(); }
    const ctx = document.getElementById('sharkChart').getContext('2d');
    
    // PHP'den gelen etiket dizisini alıyoruz
    const labels = <?php echo json_encode($grafik_etiketler); ?>;
    
    // Her bir etiketin içeriğine göre dinamik renk eşleştirmesi yapıyoruz
    const backgroundColors = labels.map(label => {
        if (label.includes('BAŞARILI - Düz metin')) {
            return 'rgba(16, 185, 129, 0.85)';  // Canlı Yeşil
        } else if (label.includes('BAŞARILI')) {
            return 'rgba(59, 130, 246, 0.85)';   // Güvenli Mavi
        } else if (label.includes('Captcha')) {
            return 'rgba(245, 158, 11, 0.85)';   // Uyarı Turuncusu (Kırmızı Tonu Grubu)
        } else {
            return 'rgba(239, 68, 68, 0.85)';    // Kritik Hata Kırmızısı
        }
    });

    window.sharkChartInstance = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: <?php echo json_encode($grafik_adetler); ?>,
                backgroundColor: backgroundColors,
                borderColor: '#ffffff',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { 
                        boxWidth: 10, 
                        padding: 16, 
                        font: { size: 11, weight: '500' },
                        color: '#64748b'
                    }
                }
            },
            cutout: '72%'
        }
    });
</script>