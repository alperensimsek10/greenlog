<?php
/**
 * GreenLog - Ziyaretçi Geçmişi (Dinamik Filtreleme, Arama, Kusursuz PDF & Başlıklı Excel)
 */
if (!isset($_SESSION['Personel_ID'])) { exit("Erişim Engellendi"); }

// Türkiye Saat Dilimi
date_default_timezone_set('Europe/Istanbul');

// Filtre ve Arama Parametrelerini Al
$period = isset($_GET['period']) ? $_GET['period'] : 'gunluk'; 
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

try {
    // 1. ÜST KARTLAR İÇİN SAYAÇLAR
    $gunluk_sayi = $pdo->query("SELECT COUNT(*) FROM ziyaretciler WHERE DATE(Giris_Zamani) = CURDATE()")->fetchColumn() ?: 0;
    $aylik_sayi  = $pdo->query("SELECT COUNT(*) FROM ziyaretciler WHERE MONTH(Giris_Zamani) = MONTH(CURRENT_DATE()) AND YEAR(Giris_Zamani) = YEAR(CURRENT_DATE())")->fetchColumn() ?: 0;
    $yillik_sayi = $pdo->query("SELECT COUNT(*) FROM ziyaretciler WHERE YEAR(Giris_Zamani) = YEAR(CURRENT_DATE())")->fetchColumn() ?: 0;

    // 2. SORGUNU OLUŞTUR
    $sql = "SELECT * FROM ziyaretciler WHERE Durum = 'Ayrıldı'";
    $params = [];

    // Dönem Filtresi
    if ($period == 'gunluk') {
        $sql .= " AND DATE(Giris_Zamani) = CURDATE()";
    } elseif ($period == 'aylik') {
        $sql .= " AND MONTH(Giris_Zamani) = MONTH(CURRENT_DATE()) AND YEAR(Giris_Zamani) = YEAR(CURRENT_DATE())";
    } elseif ($period == 'yillik') {
        $sql .= " AND YEAR(Giris_Zamani) = YEAR(CURRENT_DATE())";
    }

    // İsim Araması
    if (!empty($search)) {
        $sql .= " AND Ad_Soyad LIKE :search";
        $params['search'] = "%$search%";
    }

    $sql .= " ORDER BY Cikis_Zamani DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $liste = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) { 
    echo "<div class='alert-box'><i class='fas fa-exclamation-circle'></i> Veritabanı Hatası: " . $e->getMessage() . "</div>"; 
    $liste = [];
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
        --gl-text: #0f172a;
        --gl-text-muted: #64748b;
        --gl-border: #e2e8f0;
        --font-main: 'Plus Jakarta Sans', sans-serif;
    }

    .history-container { display: flex; flex-direction: column; gap: 25px; font-family: var(--font-main); color: var(--gl-text); padding: 5px; }
    
    /* Üst Kartlar */
    .stat-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; }
    .stat-card { 
        background: #fff; padding: 24px; border-radius: 20px; border: 1px solid var(--gl-border); 
        display: flex; align-items: center; gap: 20px; transition: all 0.3s ease;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);
    }
    .stat-card:hover { transform: translateY(-3px); box-shadow: 0 12px 20px -5px rgba(0,0,0,0.05); }
    .stat-icon { width: 54px; height: 54px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; }
    .stat-info small { font-size: 12px; font-weight: 700; color: var(--gl-text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
    .stat-info strong { font-size: 24px; font-weight: 800; color: var(--gl-text); display: block; margin-top: 2px; }

    /* Filtreleme ve Raporlama Alanı */
    .controls-wrapper {
        display: flex; flex-direction: column; gap: 15px; background: #fff; 
        padding: 20px; border-radius: 20px; border: 1px solid var(--gl-border);
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);
    }
    .filter-bar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }

    .btn-group { display: flex; gap: 8px; background: #f1f5f9; padding: 4px; border-radius: 12px; }
    .btn-filter { 
        padding: 8px 18px; border-radius: 10px; border: none; background: transparent; 
        color: var(--gl-text-muted); cursor: pointer; font-weight: 700; text-decoration: none; font-size: 13px; transition: all 0.2s;
    }
    .btn-filter.active { background: white; color: var(--gl-green-dark); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.08); }
    .btn-filter:hover:not(.active) { color: var(--gl-text); }

    .search-group { display: flex; align-items: center; gap: 8px; }
    .search-input { 
        padding: 10px 16px; border-radius: 10px; border: 2px solid #e2e8f0; outline: none; 
        width: 240px; font-size: 14px; font-weight: 600; background: #f8fafc; transition: all 0.3s;
    }
    .search-input:focus { border-color: var(--gl-green); background: white; }
    
    .btn-action { 
        padding: 10px 16px; border-radius: 10px; border: none; font-weight: 700; font-size: 13px;
        cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s; text-decoration: none;
    }
    .btn-search { background: var(--gl-text); color: white; }
    .btn-search:hover { background: #020617; }

    /* Dışa Aktarma Butonları */
    .export-group { display: flex; gap: 10px; border-top: 1px solid #f1f5f9; padding-top: 15px; justify-content: flex-end; }
    .btn-excel { background: #10b981; color: white; }
    .btn-excel:hover { background: #059669; }
    .btn-pdf { background: #ef4444; color: white; }
    .btn-pdf:hover { background: #dc2626; }

    /* Web Ekranı Tablo Ayarları */
    .main-table-card { background: #fff; border-radius: 20px; border: 1px solid var(--gl-border); overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); }
    .table-responsive { overflow-x: auto; }
    .custom-table { width: 100%; border-collapse: collapse; min-width: 900px; }
    .custom-table th { background: #f8fafc; text-align: left; padding: 16px 20px; color: var(--gl-text-muted); border-bottom: 2px solid #e2e8f0; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 800; }
    .custom-table td { padding: 16px 20px; border-bottom: 1px solid #f1f5f9; font-size: 14px; font-weight: 600; color: var(--gl-text); }
    .custom-table tr:hover td { background-color: #f8fafc; }
    
    .time-badge { font-weight: 700; padding: 4px 8px; border-radius: 6px; font-size: 12px; display: inline-flex; align-items: center; gap: 4px; }
    .badge-in { background: #ecfdf5; color: #059669; }
    .badge-out { background: #fef2f2; color: #dc2626; }

    /* 🖨️ KUSURSUZ PDF / YAZICI STİLLERİ (Scroll ve URL Gizleme Çözümü) */
    @media print {
        @page { size: auto; margin: 20mm 15mm 20mm 15mm; }
        
        /* Ekrandaki gereksiz elemanları tamamen uçur */
        body * { visibility: hidden; background: transparent !important; box-shadow: none !important; }
        
        /* Sadece rapor gövdesini görünür kıl ve kağıda yay */
        #printArea, #printArea * { visibility: visible; }
        #printArea { position: absolute; left: 0; top: 0; width: 100%; border: none !important; }
        
        /* Scroll Bar'ları yok eden sihirli kurallar */
        .table-responsive { overflow: visible !important; overflow-x: visible !important; }
        .custom-table { min-width: 100% !important; width: 100% !important; table-layout: fixed; }
        
        /* Kolon Genişliklerini A4 Sayfasına Göre Sabitle */
        .custom-table th:nth-child(1), .custom-table td:nth-child(1) { width: 25%; }
        .custom-table th:nth-child(2), .custom-table td:nth-child(2) { width: 18%; }
        .custom-table th:nth-child(3), .custom-table td:nth-child(3) { width: 22%; }
        .custom-table th:nth-child(4), .custom-table td:nth-child(4) { width: 17%; }
        .custom-table th:nth-child(5), .custom-table td:nth-child(5) { width: 17%; }

        /* Matbaa/Baskı Uyumlu Tasarım */
        .custom-table th { background: #f1f5f9 !important; color: #0f172a !important; border-bottom: 2px solid #cbd5e1 !important; padding: 12px 10px !important;}
        .custom-table td { padding: 12px 10px !important; font-size: 12px !important; border-bottom: 1px solid #e2e8f0 !important; }
        .time-badge { background: transparent !important; padding: 0 !important; font-size: 12px !important; }
        .badge-in { color: #059669 !important; }
        .badge-out { color: #dc2626 !important; }
        .time-badge i { display: none !important; }
    }
</style>

<div class="history-container animate__animated animate__fadeIn">
    
    <div class="stat-row">
        <div class="stat-card">
            <div class="stat-icon" style="background: #ecfdf5; color: #10b981;"><i class="fas fa-calendar-day"></i></div>
            <div class="stat-info"><small>Günlük Toplam</small><strong><?= $gunluk_sayi; ?></strong></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #eff6ff; color: #3b82f6;"><i class="fas fa-calendar-alt"></i></div>
            <div class="stat-info"><small>Aylık Toplam</small><strong><?= $aylik_sayi; ?></strong></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #fff7ed; color: #f59e0b;"><i class="fas fa-chart-line"></i></div>
            <div class="stat-info"><small>Yıllık Toplam</small><strong><?= $yillik_sayi; ?></strong></div>
        </div>
    </div>

    <div class="controls-wrapper">
        <div class="filter-bar">
            <div class="btn-group">
                <a href="?sayfa=ziyaretci_gecmisi&period=gunluk&search=<?= urlencode($search); ?>" class="btn-filter <?= $period == 'gunluk' ? 'active' : ''; ?>">Bugün</a>
                <a href="?sayfa=ziyaretci_gecmisi&period=aylik&search=<?= urlencode($search); ?>" class="btn-filter <?= $period == 'aylik' ? 'active' : ''; ?>">Bu Ay</a>
                <a href="?sayfa=ziyaretci_gecmisi&period=yillik&search=<?= urlencode($search); ?>" class="btn-filter <?= $period == 'yillik' ? 'active' : ''; ?>">Bu Yıl</a>
                <a href="?sayfa=ziyaretci_gecmisi&period=hepsi&search=<?= urlencode($search); ?>" class="btn-filter <?= $period == 'hepsi' ? 'active' : ''; ?>">Tümü</a>
            </div>

            <form method="GET" action="" class="search-group">
                <input type="hidden" name="sayfa" value="ziyaretci_gecmisi">
                <input type="hidden" name="period" value="<?= htmlspecialchars($period); ?>">
                <input type="text" name="search" class="search-input" placeholder="Ziyaretçi adı ara..." value="<?= htmlspecialchars($search); ?>">
                <button type="submit" class="btn-action btn-search"><i class="fas fa-search"></i> Filtrele</button>
            </form>
        </div>
        
        <div class="export-group">
            <button onclick="exportToExcel()" class="btn-action btn-excel"><i class="fas fa-file-excel"></i> Excel İndir (.xls)</button>
            <button onclick="exportToPDF()" class="btn-action btn-pdf"><i class="fas fa-file-pdf"></i> PDF Çıktı Al</button>
        </div>
    </div>

    <div class="main-table-card" id="printArea">
        <div style="display:none; padding: 10px 0 25px 0; border-bottom: 2px solid #0f172a; margin-bottom: 20px;" class="show-on-print">
            <h2 style="margin:0; font-family:'Plus Jakarta Sans', sans-serif; font-weight:800; color:#0f172a; font-size: 26px; letter-spacing: -0.5px;">GreenLog Ziyaretçi Raporu</h2>
            <p style="margin:6px 0 0 0; font-family:'Plus Jakarta Sans', sans-serif; color:#64748b; font-size:13px; font-weight: 600;">
                Filtreleme Dönemi: <span style="color:#0f172a;"><?= strtoupper($period) ?></span> | 
                Raporlama Tarihi: <span style="color:#0f172a;"><?= date('d.m.Y H:i') ?></span>
            </p>
        </div>
        
        <div class="table-responsive">
            <table class="custom-table" id="historyTable">
                <thead>
                    <tr>
                        <th>Ziyaretçi</th>
                        <th>TC Kimlik No</th>
                        <th>Ziyaret Nedeni</th>
                        <th>Giriş Tarihi / Saat</th>
                        <th>Çıkış Tarihi / Saat</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if(count($liste) > 0) {
                        foreach($liste as $r) {
                            $g_tarih = date('d.m.Y', strtotime($r['Giris_Zamani']));
                            $g_saat = date('H:i', strtotime($r['Giris_Zamani']));
                            
                            $c_tarih = ($r['Cikis_Zamani'] && $r['Cikis_Zamani'] != '0000-00-00 00:00:00') ? date('d.m.Y', strtotime($r['Cikis_Zamani'])) : '-';
                            $c_saat = ($r['Cikis_Zamani'] && $r['Cikis_Zamani'] != '0000-00-00 00:00:00') ? date('H:i', strtotime($r['Cikis_Zamani'])) : '';
                            
                            // Maskelenmiş TC
                            $masked_tc = (strlen($r['TC_Kimlik']) == 11) ? substr($r['TC_Kimlik'], 0, 3) . '*****' . substr($r['TC_Kimlik'], -3) : $r['TC_Kimlik'];
                            ?>
                            <tr>
                                <td><span style="font-weight: 700; color: var(--gl-text);"><?= htmlspecialchars($r['Ad_Soyad'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td><code style="color:var(--gl-text-muted); font-size: 13px; font-weight:600; font-family: monospace;"><?= $masked_tc ?></code></td>
                                <td style="color: var(--gl-text-muted); word-break: break-word;"><?= htmlspecialchars($r['Ziyaret_Nedeni'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= $g_tarih ?> <span class='time-badge badge-in'><i class='far fa-clock'></i> <?= $g_saat ?></span></td>
                                <td><?= $c_tarih ?> <?= !empty($c_saat) ? "<span class='time-badge badge-out'><i class='far fa-clock'></i> {$c_saat}</span>" : "" ?></td>
                            </tr>
                            <?php
                        }
                    } else {
                        ?>
                        <tr>
                            <td colspan="5" style="text-align:center; padding:60px; color: var(--gl-text-muted);">
                                <i class='fas fa-folder-open' style='font-size:2.5rem; display:block; margin-bottom:15px; color:#cbd5e1;'></i>
                                Seçilen kriterlere uygun arşivlenmiş ziyaretçi kaydı bulunamadı.
                            </td>
                        </tr>
                        <?php
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    const aktifPeriod = '<?= $period ?>';
    const periodTr = aktifPeriod === 'gunluk' ? 'Bugun' : (aktifPeriod === 'aylik' ? 'Bu_Ay' : (aktifPeriod === 'yillik' ? 'Bu_Yil' : 'Tumu'));
    const dosyaAdi = 'GreenLog_Ziyaretci_Gecmisi_' + periodTr + '_' + new Date().toISOString().slice(0,10);

    // 1. Üst Alanında Dönem Bilgisi Olan Gelişmiş Excel Aktarım Motoru
    function exportToExcel() {
        const originalTable = document.getElementById("historyTable");
        const clonedTable = originalTable.cloneNode(true);

        const donemMetni = aktifPeriod === 'gunluk' ? 'BUGÜN' : (aktifPeriod === 'aylik' ? 'BU AY' : (aktifPeriod === 'yillik' ? 'BU YIL' : 'TÜMÜ'));
        const raporTarihi = '<?= date("d.m.Y H:i") ?>';

        // Excel dosyasının en tepesine eklenecek birleştirilmiş başlık alanları
        const headerRows = `
            <tr>
                <th colspan="5" style="font-size: 16px; font-weight: bold; text-align: center; height: 35px; background-color: #0f172a; color: #ffffff;">
                    GreenLog Ziyaretçi Geçmişi Raporu
                </th>
            </tr>
            <tr>
                <th colspan="5" style="font-size: 11px; text-align: center; height: 25px; background-color: #f1f5f9; color: #64748b; font-weight: bold;">
                    Filtre Dönemi: ${donemMetni}  |  Raporlama Tarihi: ${raporTarihi}
                </th>
            </tr>
            <tr><td colspan="5" style="height:10px; background-color:#ffffff; border:none;"></td></tr>
        `;

        clonedTable.insertAdjacentHTML('afterbegin', headerRows);
        let html = clonedTable.outerHTML;

        const template = `<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
        <head><meta charset="UTF-8"></head>
        <body>${html}</body></html>`;

        const blob = new Blob([template], { type: "application/vnd.ms-excel" });
        const url = URL.createObjectURL(blob);
        
        const a = document.createElement("a");
        a.href = url;
        a.download = dosyaAdi + ".xls";
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }

    // 2. Akıllı Yazıcı / PDF Tetikleyici
    function exportToPDF() {
        const printHeader = document.querySelector('.show-on-print');
        if(printHeader) printHeader.style.display = 'block';
        
        window.print();
        
        if(printHeader) printHeader.style.display = 'none';
    }
</script>