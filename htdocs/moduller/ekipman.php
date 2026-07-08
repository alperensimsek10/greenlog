<?php
/**
 * GreenLog - Ekipman & Arıza Yönetim Merkezi (Kullanım Geçmişi Modallı Versiyon)
 */
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// --- ONARIM İŞLEMİ ---
if (isset($_POST['onarim_tamamla'])) {
    $log_id = $_POST['log_id'];
    $e_id = $_POST['ekipman_id'];
    try {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE ekipman SET Durum = 'Aktif' WHERE Ekipman_ID = ?")->execute([$e_id]);
        $pdo->prepare("UPDATE ekipman_log SET Teslim_Tarihi = NOW(), Gun_Sonu_Raporu = CONCAT(Gun_Sonu_Raporu, ' - [ONARILDI]') WHERE Log_ID = ?")->execute([$log_id]);
        $pdo->commit();
        echo "<script>window.location='panel.php?sayfa=ekipmanlar';</script>";
    } catch(Exception $e) { $pdo->rollBack(); }
}

// --- VERİ TABANI SORGULARI ---
$toplam = $pdo->query("SELECT COUNT(*) FROM ekipman")->fetchColumn();
$arizali = $pdo->query("SELECT COUNT(*) FROM ekipman WHERE Durum = 'Arızalı'")->fetchColumn();
$aktif = $pdo->query("SELECT COUNT(*) FROM ekipman WHERE Durum = 'Aktif'")->fetchColumn();

// Seralara göre ekipman dağılımı
$sera_sorgu = $pdo->query("SELECT COALESCE(s.Sera_Adi, 'Atanmamış') as sera_adi, COUNT(e.Ekipman_ID) as adet 
                           FROM ekipman e 
                           LEFT JOIN sera s ON e.Sera_ID = s.Sera_ID 
                           GROUP BY e.Sera_ID")->fetchAll();

$sera_adlari = [];
$sera_adetleri = [];
foreach($sera_sorgu as $satir) {
    $sera_adlari[] = $satir['sera_adi'];
    $sera_adetleri[] = (int)$satir['adet'];
}

// --- ADMIN ÖZEL: Personel Ekipman Kullanım İstatistiği ---
$is_admin = (isset($_SESSION['Rol']) && $_SESSION['Rol'] === 'Admin') || (isset($_SESSION['rol']) && $_SESSION['rol'] === 'Admin');

$personel_adlari = [];
$personel_ekipman_adetleri = [];

if ($is_admin) {
    $personel_sorgu = $pdo->query("SELECT p.Ad_Soyad, COUNT(el.Log_ID) as adet 
                                   FROM ekipman_log el 
                                   JOIN personel p ON el.Personel_ID = p.Personel_ID 
                                   GROUP BY el.Personel_ID 
                                   ORDER BY adet DESC")->fetchAll();
    
    foreach($personel_sorgu as $satir) {
        $personel_adlari[] = $satir['Ad_Soyad'];
        $personel_ekipman_adetleri[] = (int)$satir['adet'];
    }
}
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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
    }

    /* Sayaç Grid Yapısı */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
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

    /* Düzen Mimari */
    .equipment-grid {
        display: grid;
        grid-template-columns: 2.5fr 1fr;
        gap: 24px;
        align-items: start;
    }

    .main-content-flow {
        display: flex;
        flex-direction: column;
        gap: 24px;
    }

    .equipment-aside {
        display: flex;
        flex-direction: column;
        gap: 24px;
    }

    .header-actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        flex-wrap: wrap;
        gap: 16px;
    }

    .header-actions h2 { font-size: 24px; font-weight: 700; color: var(--text-main); margin: 0; }
    .btn-group { display: flex; gap: 12px; }

    /* Butonlar */
    .gl-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 18px;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.2s ease;
        border: none;
        cursor: pointer;
    }
    .gl-btn-primary { background: var(--primary); color: white; }
    .gl-btn-primary:hover { background: var(--primary-hover); transform: translateY(-1px); }
    .gl-btn-danger { background: var(--danger-light); color: var(--danger); border: 1px solid #fee2e2; }
    .gl-btn-danger:hover { background: var(--danger); color: white; transform: translateY(-1px); }
    .gl-btn-success { background: var(--success); color: white; padding: 6px 14px; font-size: 12px; border-radius: 8px; }

    /* Kart Yapıları */
    .gl-card {
        background: white;
        border-radius: 16px;
        border: 1px solid var(--border-color);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.04);
        overflow: hidden;
    }

    .gl-card-header {
        padding: 18px 24px;
        background: #ffffff;
        border-bottom: 1px solid var(--border-color);
        font-weight: 700;
        font-size: 16px;
        color: var(--text-main);
        display: flex;
        align-items: center;
        gap: 10px;
    }

    /* Tablo Modeli */
    .responsive-table { width: 100%; overflow-x: auto; }
    .gl-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 14px; }
    .gl-table th { background: #f8fafc; padding: 14px 24px; font-size: 12px; font-weight: 600; text-transform: uppercase; color: var(--text-muted); border-bottom: 1px solid var(--border-color); }
    .gl-table td { padding: 16px 24px; border-bottom: 1px solid #f1f5f9; color: var(--text-main); vertical-align: middle; }
    .gl-table tbody tr:hover { background: #f8fafc; }

    /* İsim Tıklama Stili */
    .clickable-equipment {
        cursor: pointer;
        color: var(--text-main);
        transition: color 0.2s;
    }
    .clickable-equipment:hover {
        color: var(--primary);
    }

    /* Rozetler */
    .gl-badge { display: inline-flex; align-items: center; gap: 5px; padding: 6px 12px; border-radius: 30px; font-size: 12px; font-weight: 600; }
    .badge-aktif { background: #ecfdf5; color: #065f46; }
    .badge-arizali { background: #fef2f2; color: #991b1b; }
    .badge-bakimda { background: #fffbeb; color: #92400e; }
    .badge-category { background: #f1f5f9; color: #475569; padding: 4px 8px; border-radius: 6px; font-size: 12px; }

    /* Satır İçi Aksiyon Butonları */
    .table-actions { display: flex; align-items: center; justify-content: flex-end; gap: 8px; }
    .action-btn { display: inline-flex; align-items: center; gap: 4px; padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; text-decoration: none; transition: all 0.2s; }
    .action-btn-edit { background: #f1f5f9; color: #475569; }
    .action-btn-edit:hover { background: #e2e8f0; color: var(--text-main); }
    .action-btn-report { background: #fff5f5; color: var(--danger); border: 1px solid #fee2e2; }
    .action-btn-report:hover { background: var(--danger); color: white; }

    /* Yan Panel Sağ Taraf */
    .aside-card-container { max-height: 400px; overflow-y: auto; padding: 16px; background: #fafafa; }
    .maintenance-item { background: white; border: 1px solid var(--border-color); padding: 16px; border-radius: 12px; margin-bottom: 12px; transition: all 0.2s; }
    .maintenance-item:hover { border-color: #fca5a5; transform: translateY(-2px); }

    /* MODAL (AÇILIR PENCERE) CSS */
    .gl-modal {
        display: none; 
        position: fixed; 
        z-index: 9999; 
        left: 0; top: 0; 
        width: 100%; height: 100%; 
        background-color: rgba(15, 23, 42, 0.6); 
        backdrop-filter: blur(4px);
        align-items: center; justify-content: center;
    }
    .gl-modal-content {
        background-color: #fff;
        border-radius: 16px;
        width: 90%; max-width: 650px;
        box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);
        animation: modalFadeIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        overflow: hidden;
    }
    .gl-modal-header {
        padding: 16px 24px;
        border-bottom: 1px solid var(--border-color);
        display: flex; justify-content: space-between; align-items: center;
        background: #f8fafc;
    }
    .gl-modal-body { padding: 24px; max-height: 450px; overflow-y: auto; }
    .close-modal { font-size: 20px; color: var(--text-muted); cursor: pointer; transition: color 0.2s; }
    .close-modal:hover { color: var(--danger); }

    /* Geçmiş Listesi Tasarımı */
    .history-timeline { list-style: none; padding: 0; margin: 0; position: relative; }
    .history-timeline::before { content: ''; position: absolute; left: 19px; top: 0; bottom: 0; width: 2px; background: var(--border-color); }
    .history-item { position: relative; padding-left: 45px; margin-bottom: 20px; }
    .history-item:last-child { margin-bottom: 0; }
    .history-icon { position: absolute; left: 10px; top: 2px; width: 20px; height: 20px; border-radius: 50%; background: white; border: 3px solid var(--primary); }
    .history-item.active-use .history-icon { border-color: var(--warning); background: var(--warning); }
    
    @keyframes modalFadeIn {
        from { transform: translateY(25px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }

    @media (max-width: 1100px) { .equipment-grid { grid-template-columns: 1fr; } .equipment-aside { order: -1; } }
    @media (max-width: 768px) { .header-actions { flex-direction: column; align-items: flex-start; } .btn-group { width: 100%; } .gl-btn { flex: 1; justify-content: center; } }
</style>

<div class="detail-container animated-page-wrapper">
    
    <div class="header-actions">
        <h2>Envanter & Teknik Servis</h2>
        <div class="btn-group">
            <a href="?sayfa=ariza_bildir" class="gl-btn gl-btn-danger">
                <i class="fas fa-tools"></i> Arıza Bildir
            </a>
            <a href="?sayfa=yeni_ekipman" class="gl-btn gl-btn-primary">
                <i class="fas fa-plus"></i> Yeni Ekipman
            </a>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="info">
                <h3><?= $toplam ?></h3>
                <p>Toplam Ekipman</p>
            </div>
            <div class="stat-icon" style="background: #eff6ff; color: var(--primary);"><i class="fas fa-boxes"></i></div>
        </div>
        <div class="stat-card">
            <div class="info">
                <h3><?= $aktif ?></h3>
                <p>Aktif Çalışan</p>
            </div>
            <div class="stat-icon" style="background: #ecfdf5; color: var(--success);"><i class="fas fa-check-circle"></i></div>
        </div>
        <div class="stat-card">
            <div class="info"><h3 style="color: var(--danger);"><?= $arizali ?></h3><p>Arızalı / Serviste</p></div>
            <div class="stat-icon" style="background: #fef2f2; color: var(--danger);"><i class="fas fa-exclamation-triangle"></i></div>
        </div>
    </div>

    <div class="equipment-grid">
        
        <div class="main-content-flow">
            
            <div class="gl-card">
                <div class="gl-card-header"><i class="fas fa-chart-bar" style="color: var(--primary);"></i> Seradaki Ekipman Yoğunluğu Dağılımı</div>
                <div style="padding: 20px; position: relative; height: 260px; display:flex; justify-content:center;">
                    <canvas id="seraGrafik" style="max-width: 100%; height: 100%;"></canvas>
                </div>
            </div>

            <div class="gl-card">
                <div class="gl-card-header"><i class="fas fa-list" style="color: var(--primary);"></i> Envanter Listesi (Geçmiş için isme tıklayın)</div>
                <div class="responsive-table">
                    <table class="gl-table">
                        <thead>
                            <tr>
                                <th>Ekipman Bilgisi</th>
                                <th>Konum / Sera</th>
                                <th>Kategori</th>
                                <th>Durum</th>
                                <th style="text-align: right;">İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $ekipmanlar = $pdo->query("SELECT e.*, s.Sera_Adi FROM ekipman e LEFT JOIN sera s ON e.Sera_ID = s.Sera_ID ORDER BY e.Ekipman_ID DESC")->fetchAll();
                            foreach($ekipmanlar as $e): 
                                $status_class = 'badge-bakimda';
                                if ($e['Durum'] == 'Aktif') $status_class = 'badge-aktif';
                                if ($e['Durum'] == 'Arızalı') $status_class = 'badge-arizali';
                            ?>
                            <tr>
                                <td>
                                    <div class="clickable-equipment" onclick="openHistoryModal(<?= $e['Ekipman_ID'] ?>, '<?= htmlspecialchars($e['Ekipman_Adi']) ?>')">
                                        <strong style="font-size: 15px; text-decoration: underline; text-underline-offset: 3px;"><?= htmlspecialchars($e['Ekipman_Adi'] ?? 'İsimsiz') ?></strong>
                                    </div>
                                    <div style="color: var(--text-muted); font-size: 12px; margin-top: 4px;"><i class="fas fa-barcode"></i> <?= $e['Seri_No'] ?? '-' ?></div>
                                </td>
                                <td>
                                    <?php if($e['Sera_Adi']): ?>
                                        <span style="font-weight: 500;"><i class="fas fa-warehouse" style="color: #64748b;"></i> <?= htmlspecialchars($e['Sera_Adi']) ?></span>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted); font-style: italic; font-size: 13px;">Atanmamış</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge-category"><?= htmlspecialchars($e['Kategori'] ?? 'Genel') ?></span></td>
                                <td><span class="gl-badge <?= $status_class ?>"><i class="fas fa-circle" style="font-size: 7px;"></i> <?= $e['Durum'] ?? 'Belirsiz' ?></span></td>
                                <td style="text-align: right;">
                                    <div class="table-actions">
                                        <?php if($e['Durum'] !== 'Arızalı'): ?>
                                            <a href="?sayfa=ariza_bildir&ekipman_id=<?= $e['Ekipman_ID'] ?>" class="action-btn action-btn-report" title="Arıza Bildir"><i class="fas fa-tools"></i> Bildir</a>
                                        <?php endif; ?>
                                        <a href="?sayfa=ekipman_duzenle&id=<?= $e['Ekipman_ID'] ?>" class="action-btn action-btn-edit" title="Düzenle"><i class="fas fa-cog"></i></a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <aside class="equipment-aside">
            
            <?php if ($is_admin): ?>
            <div class="gl-card">
                <div class="gl-card-header"><i class="fas fa-users" style="color: var(--warning);"></i> Personel / Ekipman Kullanımı</div>
                <div style="padding: 20px; position: relative; height: 260px; display:flex; justify-content:center;">
                    <canvas id="personelGrafik" style="max-width: 100%; height: 100%;"></canvas>
                </div>
            </div>
            <?php endif; ?>

            <div class="gl-card" style="border-color: #fee2e2;">
                <div class="gl-card-header" style="background: #fff5f5; color: #991b1b; border-bottom: 1px solid #fee2e2;">
                    <i class="fas fa-bell"></i> Bekleyen Onarımlar
                    <span style="background: #7f1d1d; color: white; font-size: 11px; padding: 1px 7px; border-radius: 20px; font-weight: 600; margin-left: auto;"><?= $arizali ?></span>
                </div>
                <div class="aside-card-container">
                    <?php 
                    $arizalar = $pdo->query("SELECT l.*, e.Ekipman_Adi, s.Sera_Adi FROM ekipman_log l JOIN ekipman e ON l.Ekipman_ID = e.Ekipman_ID LEFT JOIN sera s ON e.Sera_ID = s.Sera_ID WHERE e.Durum = 'Arızalı' AND l.Teslim_Tarihi IS NULL ORDER BY l.Log_ID DESC")->fetchAll();
                    if($arizalar):
                        foreach($arizalar as $a): ?>
                        <div class="maintenance-item">
                            <div style="display: flex; justify-content: space-between; align-items: start;">
                                <div style="font-weight: 600; font-size: 14px; color: var(--text-main);"><?= htmlspecialchars($a['Ekipman_Adi']) ?></div>
                                <?php if($a['Sera_Adi']): ?>
                                    <span style="background: #eff6ff; color: var(--primary); font-size: 11px; padding: 2px 6px; border-radius: 4px; font-weight: 600;"><?= htmlspecialchars($a['Sera_Adi']) ?></span>
                                <?php endif; ?>
                            </div>
                            <div style="color: var(--danger); font-size: 13px; margin: 8px 0; font-weight: 500;">
                                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars(str_replace("ARIZA BİLDİRİLDİ: ", "", $a['Gun_Sonu_Raporu'] ?? '')) ?>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 12px; border-top: 1px dashed var(--border-color); padding-top: 10px;">
                                <span style="color: var(--text-muted); font-size: 12px;"><i class="far fa-clock"></i> <?= date('H:i', strtotime($a['Alis_Tarihi'])) ?></span>
                                <form method="POST" style="margin:0;">
                                    <input type="hidden" name="log_id" value="<?= $a['Log_ID'] ?>">
                                    <input type="hidden" name="ekipman_id" value="<?= $a['Ekipman_ID'] ?>">
                                    <button type="submit" name="onarim_tamamla" class="gl-btn gl-btn-success">Onarıldı</button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; 
                    else: ?>
                        <div style="text-align: center; color: var(--text-muted); padding: 40px 20px;"><i class="fas fa-check-circle" style="color: var(--success); font-size: 32px; margin-bottom: 10px; display: block;"></i>Aktif arıza kaydı bulunmuyor.</div>
                    <?php endif; ?>
                </div>
            </div>
        </aside>

    </div>
</div>

<div id="historyModal" class="gl-modal">
    <div class="gl-modal-content">
        <div class="gl-modal-header">
            <h3 id="modalTitle" style="margin:0; font-size:16px; font-weight:700; color:var(--text-main);">Ekipman Geçmişi</h3>
            <span class="close-modal" onclick="closeHistoryModal()">&times;</span>
        </div>
        <div class="gl-modal-body" id="modalBody">
            <p style="text-align:center; color:var(--text-muted);">Yükleniyor...</p>
        </div>
    </div>
</div>

<script>
// CHARTJS AYARI
document.addEventListener("DOMContentLoaded", function() {
    // 1. Grafik: Sera Yoğunluğu
    const ctx = document.getElementById('seraGrafik').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($sera_adlari) ?>,
            datasets: [{
                label: 'Ekipman Sayısı ',
                data: <?= json_encode($sera_adetleri) ?>,
                backgroundColor: 'rgba(59, 130, 246, 0.75)',
                borderColor: 'rgba(59, 130, 246, 1)',
                borderWidth: 1,
                borderRadius: 6,
                barThickness: 25
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1, color: '#64748b' }, grid: { color: '#f1f5f9' } },
                x: { ticks: { color: '#64748b' }, grid: { display: false } }
            }
        }
    });

    // 2. Grafik: Admin Özel Personel Kullanım Grafiği (Doughnut / Simit Grafik)
    <?php if ($is_admin): ?>
    const ctxPersonel = document.getElementById('personelGrafik').getContext('2d');
    new Chart(ctxPersonel, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($personel_adlari) ?>,
            datasets: [{
                data: <?= json_encode($personel_ekipman_adetleri) ?>,
                backgroundColor: [
                    'rgba(245, 158, 11, 0.8)',
                    'rgba(16, 185, 129, 0.8)',
                    'rgba(59, 130, 246, 0.8)',
                    'rgba(139, 92, 246, 0.8)',
                    'rgba(239, 68, 68, 0.8)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            layout: {
                padding: { top: 10, bottom: 10 }
            },
            plugins: { 
                legend: { 
                    position: 'right',
                    labels: { boxWidth: 12, font: { size: 11 }, color: '#64748b' }
                } 
            }
        }
    });
    <?php endif; ?>
});

// MODAL AKSİYONLARI (FETCH API)
function openHistoryModal(id, ad) {
    const modal = document.getElementById('historyModal');
    const title = document.getElementById('modalTitle');
    const body = document.getElementById('modalBody');
    
    title.innerText = ad + " - Kullanım & Arıza Geçmişi";
    body.innerHTML = '<div style="text-align:center; padding:20px; color:var(--text-muted);"><i class="fas fa-spinner fa-spin"></i> Geçmiş kayıtlar çekiliyor...</div>';
    modal.style.display = "flex";

    fetch('ekipman_gecmis.php?id=' + id)
        .then(response => response.text())
        .then(data => {
            body.innerHTML = data;
        })
        .catch(err => {
            body.innerHTML = '<div style="color:var(--danger); text-align:center;">Kayıtlar yüklenirken bir hata oluştu.</div>';
        });
}

function closeHistoryModal() {
    document.getElementById('historyModal').style.display = "none";
}

window.onclick = function(event) {
    const modal = document.getElementById('historyModal');
    if (event.target == modal) {
        modal.style.display = "none";
    }
}
</script>