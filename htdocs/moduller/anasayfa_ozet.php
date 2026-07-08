<?php
/**
 * GreenLog - Gelişmiş Dashboard (v5.0 - Dinamik Analitik & Rol Bazlı Log/Görev)
 */
if (!isset($_SESSION['Personel_ID'])) { exit("Erişim Engellendi"); }

$kullanici_id = $_SESSION['Personel_ID'];
$rol = $_SESSION['Rol'];
$bugun = date('Y-m-d');

try {
    $pdo->exec("SET NAMES 'utf8mb4'"); 

    // 1. Tür Grubu Dağılımı Verileri
    $tur_dagilim_sorgu = $pdo->query("SELECT Tur_Grubu, COUNT(*) as adet FROM takson GROUP BY Tur_Grubu");
    $tur_labels = [];
    $tur_data = [];
    while($row = $tur_dagilim_sorgu->fetch()) {
        $tur_labels[] = $row['Tur_Grubu'] ?: 'Belirtilmemiş';
        $tur_data[] = (int)$row['adet'];
    }

    // 2. Sera Bazlı Bitki Sayısı
    $sera_bitki_sorgu = $pdo->query("SELECT s.Sera_Adi, COUNT(b.Bitki_ID) as adet 
                                     FROM sera s 
                                     LEFT JOIN bitki b ON s.Sera_ID = b.Sera_ID 
                                     GROUP BY s.Sera_ID");
    $sera_labels = [];
    $sera_data = [];
    while($row = $sera_bitki_sorgu->fetch()) {
        $sera_labels[] = $row['Sera_Adi'];
        $sera_data[] = (int)$row['adet'];
    }

    // 3. Genel Sayaçlar
    $toplam_bitki_sayisi = $pdo->query("SELECT COUNT(*) FROM bitki")->fetchColumn() ?: 0;
    $toplam_ekipman_sayisi = $pdo->query("SELECT COUNT(*) FROM ekipman")->fetchColumn() ?: 0;

    // 4. Log Kayıtları (Rol Bazlı)
    if ($rol === 'Admin') {
        // Admin tüm logları görür
        $loglar = $pdo->query("SELECT l.Islem_Detay, l.Islem_Tarihi, p.Ad_Soyad 
                               FROM sistem_loglari l 
                               LEFT JOIN personel p ON l.Personel_ID = p.Personel_ID 
                               ORDER BY l.Islem_Tarihi DESC LIMIT 25");
                               
        // Filtre için aktif personelleri alfabetik olarak çekiyoruz
        $personel_listesi = $pdo->query("SELECT Ad_Soyad FROM personel WHERE Aktif_Mi = 1 ORDER BY Ad_Soyad ASC")->fetchAll(PDO::FETCH_COLUMN);
    } else {
        // Personel sadece kendi loglarını görür
        $loglar = $pdo->prepare("SELECT l.Islem_Detay, l.Islem_Tarihi, p.Ad_Soyad 
                                 FROM sistem_loglari l 
                                 LEFT JOIN personel p ON l.Personel_ID = p.Personel_ID 
                                 WHERE l.Personel_ID = ? 
                                 ORDER BY l.Islem_Tarihi DESC LIMIT 10");
        $loglar->execute([$kullanici_id]);
    }

    // 5. Aktif Görevler (GÜNCELLENDİ: Admin için Personel ismi de çekiliyor)
    if ($rol === 'Admin') {
        // Admin tüm şirketteki aktif görevleri ve atanan personeli görsün
        $gorev_sorgu = $pdo->query("SELECT g.Gorev_ID, g.Baslik, g.Son_Tarih, g.Durum, p.Ad_Soyad as Personel_Ad 
                                    FROM gorevler g
                                    LEFT JOIN personel p ON g.Personel_ID = p.Personel_ID
                                    WHERE g.Durum IN ('Bekliyor', 'Devam Ediyor') 
                                    ORDER BY g.Son_Tarih ASC LIMIT 7");
        $aktif_gorevler = $gorev_sorgu->fetchAll();
    } else {
        // Personel sadece kendi üzerine atananları görsün
        $gorev_sorgu = $pdo->prepare("SELECT Gorev_ID, Baslik, Son_Tarih, Durum 
                                      FROM gorevler 
                                      WHERE Personel_ID = ? AND Durum IN ('Bekliyor', 'Devam Ediyor') 
                                      ORDER BY Son_Tarih ASC LIMIT 7");
        $gorev_sorgu->execute([$kullanici_id]);
        $aktif_gorevler = $gorev_sorgu->fetchAll();
    }

} catch (PDOException $e) { 
    echo "Veri çekme hatası: " . $e->getMessage(); 
}

function logMesajiDuzenle($mesaj) {
    $temiz = preg_replace('/\s?\(?ID:\s?\d+\)?/ui', '', $mesaj);
    $temiz = str_replace(['olu?turuldu', 'ba?lat?ld?', 'Kay?t'], ['oluşturuldu', 'başlatıldı', 'Kayıt'], $temiz);
    return trim($temiz);
}
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<style>
    :root {
        --bg-white: #ffffff;
        --border-color: #f1f5f9;
        --text-main: #1e293b;
        --text-muted: #64748b;
    }

    .dashboard-container { 
        display: flex; 
        flex-direction: column; 
        gap: 20px; 
        font-family: 'Plus Jakarta Sans', sans-serif;
        padding: 10px;
    }
    
    .stat-row { 
        display: grid; 
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); 
        gap: 20px; 
    }

    .stat-card { 
        background: var(--bg-white); 
        padding: 20px; 
        border-radius: 15px; 
        border: 1px solid var(--border-color); 
        display: flex; 
        align-items: center; 
        gap: 15px;
        transition: transform 0.2s;
    }
    .stat-card:hover { transform: translateY(-3px); }

    .stat-icon { 
        width: 55px; 
        height: 55px; 
        border-radius: 12px; 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        font-size: 1.4rem; 
        flex-shrink: 0;
    }
    
    .chart-grid, .log-grid { 
        display: grid; 
        grid-template-columns: repeat(2, 1fr); 
        gap: 20px; 
    }
    .log-grid { grid-template-columns: 1.5fr 1fr; }
    
    .content-card { 
        background: var(--bg-white); 
        border-radius: 20px; 
        padding: 20px; 
        border: 1px solid var(--border-color); 
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        min-width: 0; 
        display: flex;
        flex-direction: column;
    }

    .card-title { 
        font-weight: 700; 
        color: var(--text-main); 
        display: flex; 
        align-items: center; 
        gap: 10px;
        font-size: 1.1rem;
    }
    
    .custom-scroll { 
        overflow-y: auto; 
        padding-right: 5px;
        flex: 1;
    }
    .custom-scroll::-webkit-scrollbar { width: 5px; }
    .custom-scroll::-webkit-scrollbar-track { background: transparent; }
    .custom-scroll::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
    .custom-scroll::-webkit-scrollbar-thumb:hover { background: #cbd5e1; }

    /* Log Tasarımı */
    .log-item { 
        padding: 15px 10px; 
        border-bottom: 1px dashed #e2e8f0; 
        font-size: 0.9rem; 
        line-height: 1.4;
    }
    .log-item:last-child { border-bottom: none; }
    .log-date { 
        font-size: 0.75rem; 
        color: #94a3b8; 
        display: block; 
        margin-top: 5px;
    }

    /* Görevler Tasarımı */
    .task-item { padding: 15px; border: 1px solid #e2e8f0; border-radius: 12px; margin-bottom: 12px; background: #f8fafc; transition: 0.2s; cursor: pointer; }
    .task-item:hover { border-color: #cbd5e1; background: #fff; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
    .task-item.t-bekliyor { border-left: 4px solid #f59e0b; }
    .task-item.t-devam { border-left: 4px solid #3b82f6; }
    .task-item.t-gecikti { border-left: 4px solid #ef4444; }
    
    .search-input {
        padding: 8px 12px 8px 35px;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        font-size: 0.85rem;
        outline: none;
        width: 200px;
        font-family: inherit;
        transition: 0.2s;
    }
    .search-input:focus { border-color: #3b82f6; }

    @media (max-width: 1024px) { .log-grid { grid-template-columns: 1fr; } }
    @media (max-width: 768px) { 
        .chart-grid { grid-template-columns: 1fr; } 
        .content-card { padding: 15px; } 
        .stat-row { grid-template-columns: 1fr; } 
    }
</style>

<div class="dashboard-container">
    
    <div class="stat-row">
        <div class="stat-card">
            <div class="stat-icon" style="background: #ecfdf5; color: #10b981;"><i class="fas fa-leaf"></i></div>
            <div>
                <div style="font-size: 0.85rem; color: var(--text-muted);">Toplam Bitki</div>
                <div style="font-size: 1.6rem; font-weight: 800; color: var(--text-main);"><?php echo $toplam_bitki_sayisi; ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #eff6ff; color: #3b82f6;"><i class="fas fa-tools"></i></div>
            <div>
                <div style="font-size: 0.85rem; color: var(--text-muted);">Kayıtlı Cihaz</div>
                <div style="font-size: 1.6rem; font-weight: 800; color: var(--text-main);"><?php echo $toplam_ekipman_sayisi; ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #fff7ed; color: #f59e0b;"><i class="fas fa-microscope"></i></div>
            <div>
                <div style="font-size: 0.85rem; color: var(--text-muted);">Tür Çeşitliliği</div>
                <div style="font-size: 1.6rem; font-weight: 800; color: var(--text-main);"><?php echo count($tur_data); ?></div>
            </div>
        </div>
    </div>

    <div class="chart-grid">
        <div class="content-card">
            <div class="card-title" style="margin-bottom:20px;"><i class="fas fa-chart-pie" style="color:#10b981"></i> Tür Grubu Dağılımı</div>
            <div style="position: relative; height: 300px; width: 100%;"><canvas id="turChart"></canvas></div>
        </div>
        <div class="content-card">
            <div class="card-title" style="margin-bottom:20px;"><i class="fas fa-chart-bar" style="color:#3b82f6"></i> Sera Bitki Yoğunluğu</div>
            <div style="position: relative; height: 300px; width: 100%;"><canvas id="seraChart"></canvas></div>
        </div>
    </div>

    <div class="log-grid">
        
        <div class="content-card" style="height: 440px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; flex-wrap:wrap; gap:10px;">
                <div class="card-title" style="margin:0;"><i class="fas fa-history" style="color:#64748b"></i> Son Sistem Hareketleri</div>
                
                <?php if($rol === 'Admin'): ?>
                    <div style="display:flex; gap:10px; align-items:center;">
                        
                        <div style="position:relative;">
                            <i class="fas fa-users" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#3b82f6;"></i>
                            <select id="logPersonelFilter" class="search-input" style="padding-left: 35px; padding-right: 25px; cursor:pointer; width:160px; appearance:none; background-color:#fff;">
                                <option value="">Tüm Personel</option>
                                <?php foreach($personel_listesi as $p_ad): ?>
                                    <option value="<?= htmlspecialchars($p_ad) ?>"><?= htmlspecialchars($p_ad) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <i class="fas fa-chevron-down" style="position:absolute; right:10px; top:50%; transform:translateY(-50%); color:#94a3b8; pointer-events:none; font-size:10px;"></i>
                        </div>

                        <div style="position:relative;">
                            <i class="fas fa-search" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#94a3b8;"></i>
                            <input type="text" id="logSearch" class="search-input" placeholder="Loglarda ara...">
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="custom-scroll" id="logList">
                <?php 
                if($loglar->rowCount() > 0):
                    while($log = $loglar->fetch()): 
                        $islem = logMesajiDuzenle($log['Islem_Detay']);
                        $kim_yapti = ($rol === 'Admin') ? htmlspecialchars($log['Ad_Soyad'] ?? 'Sistem') . ': ' : '';
                ?>
                    <div class="log-item">
                        <span style="font-weight: 700; color: #10b981;" class="log-person"><?php echo $kim_yapti; ?></span> 
                        <span class="log-text"><?php echo htmlspecialchars($islem); ?></span>
                        <span class="log-date"><i class="far fa-clock"></i> <?php echo date('H:i - d.m.Y', strtotime($log['Islem_Tarihi'])); ?></span>
                    </div>
                <?php 
                    endwhile; 
                else: 
                ?>
                    <div style="text-align:center; padding:30px; color:#94a3b8;"><i class="fas fa-inbox fa-2x" style="opacity:0.5; margin-bottom:10px; display:block;"></i> Henüz bir hareket kaydı yok.</div>
                <?php endif; ?>
                
                <div id="noLogMessage" style="display:none; text-align:center; padding:40px 20px; color:#94a3b8;">
                    <div style="width:60px; height:60px; background:#f1f5f9; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 15px auto;">
                        <i class="fas fa-search-minus fa-2x" style="color:#cbd5e1;"></i>
                    </div>
                    <p id="noLogText" style="margin:0; font-size:0.95rem; line-height:1.5;">Kayıt bulunamadı.</p>
                </div>

            </div>
        </div>

        <div class="content-card" style="height: 440px;">
            <div class="card-title" style="margin-bottom:15px; flex-shrink:0;">
                <i class="fas fa-clipboard-check" style="color:#f59e0b"></i> 
                <?php echo ($rol === 'Admin') ? 'Personelin Aktif Görevleri' : 'Aktif Görevlerim'; ?>
            </div>
            
            <div class="custom-scroll">
                <?php if(count($aktif_gorevler) > 0): ?>
                    <?php foreach($aktif_gorevler as $g): 
                        // ZAMAN/DURUM KONTROLÜ
                        $is_gecikti = ($g['Son_Tarih'] < $bugun);
                        if ($is_gecikti) {
                            $t_class = 't-gecikti'; $b_color = '#ef4444'; $bg_color = '#fef2f2'; $durum_text = '⚠️ Gecikti';
                        } elseif ($g['Durum'] === 'Bekliyor') {
                            $t_class = 't-bekliyor'; $b_color = '#f59e0b'; $bg_color = '#fffbeb'; $durum_text = 'Bekliyor';
                        } else {
                            $t_class = 't-devam'; $b_color = '#3b82f6'; $bg_color = '#eff6ff'; $durum_text = 'Devam Ediyor';
                        }
                    ?>
                        <div class="task-item <?= $t_class ?>" onclick="window.location.href='panel.php?sayfa=is_takip&focus_id=<?= $g['Gorev_ID'] ?>'">
                            <div style="font-weight:700; color:var(--text-main); font-size:0.95rem; margin-bottom:8px;">
                                <?= htmlspecialchars($g['Baslik']) ?>
                            </div>
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <div style="display:flex; gap:12px; align-items:center;">
                                    <span style="font-size:0.8rem; color:<?= $is_gecikti ? '#ef4444' : '#64748b' ?>; <?= $is_gecikti ? 'font-weight:700;' : '' ?>">
                                        <i class="far fa-calendar"></i> <?= date('d.m.Y', strtotime($g['Son_Tarih'])) ?>
                                    </span>
                                    
                                    <?php if($rol === 'Admin'): ?>
                                        <span style="font-size:0.8rem; color:#475569; font-weight:600; background:#f1f5f9; padding:2px 8px; border-radius:6px; display:inline-flex; align-items:center; gap:5px;">
                                            <i class="far fa-user" style="color:#3b82f6; font-size:0.75rem;"></i> <?= htmlspecialchars($g['Personel_Ad'] ?? 'Atanmamış') ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <span style="font-size:0.75rem; font-weight:700; padding:4px 10px; border-radius:6px; color:<?= $b_color ?>; background:<?= $bg_color ?>;">
                                    <?= $durum_text ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="height:100%; display:flex; flex-direction:column; align-items:center; justify-content:center; color:#94a3b8; text-align:center;">
                        <div style="width:60px; height:60px; background:#f1f5f9; border-radius:50%; display:flex; align-items:center; justify-content:center; margin-bottom:15px;">
                            <i class="fas fa-check-double fa-2x" style="color:#cbd5e1;"></i>
                        </div>
                        <p style="margin:0; font-weight:600; font-size:1.1rem; color:#475569;">Harika!</p>
                        <p style="margin:5px 0 0 0; font-size:0.9rem;">Şu an bekleyen hiçbir görev yok.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if(count($aktif_gorevler) > 0): ?>
                <div style="flex-shrink:0; margin-top:auto; padding-top:10px; border-top:1px solid var(--border-color);">
                    <a href="panel.php?sayfa=is_takip" style="display:block; text-align:center; padding:10px; color:#3b82f6; text-decoration:none; font-weight:700; font-size:0.9rem; border-radius:8px; transition:0.2s;" onmouseover="this.style.background='#eff6ff'" onmouseout="this.style.background='transparent'">
                        Tüm İş Takibine Git <i class="fas fa-arrow-right" style="margin-left:5px;"></i>
                    </a>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<script>
// --- GRAFİK KODLARI ---
const colors = ['#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#ec4899'];

new Chart(document.getElementById('turChart'), {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode($tur_labels); ?>,
        datasets: [{
            data: <?php echo json_encode($tur_data); ?>,
            backgroundColor: colors,
            borderWidth: 0,
            hoverOffset: 10
        }]
    },
    options: { 
        responsive: true, 
        maintainAspectRatio: false,
        plugins: { 
            legend: { 
                position: 'bottom', 
                labels: { usePointStyle: true, padding: 25, font: { family: 'Plus Jakarta Sans', size: 12 } } 
            } 
        },
        cutout: '65%'
    }
});

new Chart(document.getElementById('seraChart'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($sera_labels); ?>,
        datasets: [{
            label: 'Bitki Sayısı',
            data: <?php echo json_encode($sera_data); ?>,
            backgroundColor: '#3b82f6',
            borderRadius: 8,
            barThickness: 25
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { font: { family: 'Plus Jakarta Sans' } } },
            x: { grid: { display: false }, ticks: { font: { family: 'Plus Jakarta Sans' } } }
        },
        plugins: { legend: { display: false } }
    }
});

// --- LOG ARAMA VE PERSONEL FİLTRELEME SİSTEMİ ---
const searchInput = document.getElementById('logSearch');
const personelFilter = document.getElementById('logPersonelFilter');
const noLogMessage = document.getElementById('noLogMessage');
const noLogText = document.getElementById('noLogText');

function filterLogs() {
    let searchText = searchInput ? searchInput.value.toLowerCase() : '';
    let personText = personelFilter ? personelFilter.value.toLowerCase() : '';
    
    let personName = (personelFilter && personelFilter.selectedIndex > 0) ? personelFilter.options[personelFilter.selectedIndex].text : '';
    
    let items = document.querySelectorAll('.log-item');
    let visibleCount = 0;
    
    items.forEach(item => {
        let itemText = item.textContent || item.innerText;
        let personSpan = item.querySelector('.log-person');
        let itemPerson = personSpan ? (personSpan.textContent || personSpan.innerText).toLowerCase() : '';
        
        let matchesSearch = itemText.toLowerCase().indexOf(searchText) > -1;
        let matchesPerson = personText === '' || itemPerson.indexOf(personText) > -1;
        
        if (matchesSearch && matchesPerson) {
            item.style.display = "";
            visibleCount++;
        } else {
            item.style.display = "none";
        }
    });

    if (noLogMessage) {
        if (visibleCount === 0 && items.length > 0) {
            noLogMessage.style.display = "block";
            
            if (personName && searchText) {
                noLogText.innerHTML = `<strong style="color:var(--text-main)">${personName}</strong> için "<strong style="color:var(--text-main)">${searchInput.value}</strong>" aramasında sonuç bulunamadı.`;
            } else if (personName) {
                noLogText.innerHTML = `<strong style="color:var(--text-main)">${personName}</strong> adlı personele ait sistem hareketi bulunamadı.`;
            } else if (searchText) {
                noLogText.innerHTML = `"<strong style="color:var(--text-main)">${searchInput.value}</strong>" kelimesini içeren kayıt bulunamadı.`;
            } else {
                noLogText.innerHTML = "Aradığınız kritere uygun kayıt bulunamadı.";
            }
        } else {
            noLogMessage.style.display = "none";
        }
    }
}

if (searchInput) searchInput.addEventListener('keyup', filterLogs);
if (personelFilter) personelFilter.addEventListener('change', filterLogs);
</script>