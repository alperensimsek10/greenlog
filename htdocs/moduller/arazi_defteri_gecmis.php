<?php
/**
 * GreenLog - Arazi Defteri Geçmiş Kayıtlar (İzole Edilmiş Yetki ve PDF Destekli)
 */
require_once 'db-connect.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Güvenlik: Giriş yapılmamışsa engelle
if (!isset($_SESSION['Personel_ID'])) { exit("Erişim Engellendi"); }

// Admin Yetkisi ve Kullanıcı Bilgisini Çek
$isAdmin = (isset($_SESSION['Rol']) && trim(strtolower($_SESSION['Rol'])) === 'admin');
$kullanici_id = $_SESSION['Personel_ID'];

$stmt_kisi = $pdo->prepare("SELECT Ad_Soyad FROM personel WHERE Personel_ID = ?");
$stmt_kisi->execute([$kullanici_id]);
$kullanici_ad_soyad = $stmt_kisi->fetchColumn();

// --- SİLME (SOFT DELETE) İŞLEMİ ---
if (isset($_POST['kayit_sil']) && $isAdmin) {
    $sil_id = $_POST['sil_id'];
    
    try {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE arazi_defteri SET Aktif_Mi = 0 WHERE Kayit_ID = ?")->execute([$sil_id]);
        $pdo->prepare("INSERT INTO sistem_loglari (Personel_ID, Islem_Detay, Islem_Tarihi, Tablo_Adi) VALUES (?, ?, NOW(), 'arazi_defteri')")
            ->execute([$_SESSION['Personel_ID'], "Arazi Kaydını Sildi: #$sil_id"]);
        
        $pdo->commit();
        // Sayfayı yenile ve Toast tetikle
        echo "<script>
                localStorage.setItem('goster_toast', '1');
                window.location.href = '?sayfa=arazi_defteri_gecmis';
              </script>";
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo "<script>alert('Hata!');</script>";
    }
}

// Kayıtları Veritabanından Çek
$kayitlar = [];
try {
    $params = [];
    $sql = "SELECT ad.*, t.Takson_Adi, t.Bitki_Adi 
            FROM arazi_defteri ad
            LEFT JOIN takson t ON ad.Takson_ID = t.Takson_ID
            WHERE ad.Aktif_Mi = 1 ";
            
    // KURAL: Eğer Admin değilse, sadece kendi adının olduğu verileri görebilir!
    if (!$isAdmin) {
        $sql .= " AND ad.Toplayici = ? ";
        $params[] = $kullanici_ad_soyad;
    }
            
    $sql .= " ORDER BY ad.Toplama_Tarihi DESC, ad.Kayit_ID DESC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $kayitlar = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $hata = "Veriler çekilirken hata oluştu: " . $e->getMessage();
}
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    .t-container { background: #fff; border-radius: 20px; padding: 25px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); width: 100%; max-width: 100%; box-sizing: border-box; overflow-x: hidden; }
    .page-title { margin:0 0 25px 0; color:#1e293b; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;}
    
    .btn-back { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: 0.2s; text-decoration: none;}
    .btn-back:hover { background: #e2e8f0; color: #1e293b; }
    
    .btn-pdf { background: #ef4444; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: 0.2s; font-family: inherit;}
    .btn-pdf:hover { background: #dc2626; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);}

    .btn-edit { background: #3b82f6; color: white; padding: 8px 15px; border-radius: 8px; text-decoration: none; font-size: 13px; font-weight: 600; transition: 0.2s; display: inline-block; white-space: nowrap;}
    .btn-edit:hover { background: #2563eb; }
    
    .btn-delete { background: #ef4444; color: white; padding: 8px 12px; border-radius: 8px; border: none; font-size: 13px; font-weight: 600; transition: 0.2s; cursor: pointer; margin-left: 5px; display: inline-block;}
    .btn-delete:hover { background: #dc2626; }

    .table-responsive { width: 100%; overflow-x: auto; background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; }
    .data-table { width: 100%; border-collapse: collapse; font-size: 14px; text-align: left; min-width: 900px; }
    .data-table th { background: #f8fafc; color: #475569; padding: 15px; font-weight: 600; border-bottom: 2px solid #e2e8f0; white-space: nowrap; }
    .data-table td { padding: 15px; color: #334155; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
    .data-table tr:last-child td { border-bottom: none; }
    .data-table tr:hover { background-color: #f8fafc; }
    .badge-info { background: #e0f2fe; color: #0284c7; padding: 5px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; }
    .empty-state { text-align: center; padding: 40px; color: #64748b; }

    @media print {
        .no-print { display: none !important; }
        body * { visibility: hidden; }
        #exportArea, #exportArea * { visibility: visible; }
        #exportArea { position: absolute; left: 0; top: 0; width: 100%; }
        .data-table th, .data-table td { border: 1px solid #cbd5e1 !important; }
    }
</style>

<div class="t-container">
    <div class="page-title">
        <h2 style="margin:0;"><i class="fas fa-history" style="color:#3b82f6; margin-right:10px;"></i> Geçmiş Arazi Kayıtları</h2>
        
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <button onclick="downloadPDF()" class="btn-pdf no-print">
                <i class="fas fa-file-pdf"></i> PDF İndir
            </button>
            <a href="?sayfa=arazi_defteri" class="btn-back no-print">
                <i class="fas fa-plus"></i> Yeni Kayıt Ekle
            </a>
        </div>
    </div>

    <?php if(isset($hata)): ?>
        <div style="background: #fee2e2; color: #b91c1c; padding: 15px; border-radius: 8px; margin-bottom: 20px;" class="no-print">
            <i class="fas fa-exclamation-triangle"></i> <?= $hata ?>
        </div>
    <?php endif; ?>

    <div class="table-responsive" id="exportArea">
        <h3 style="display:none; text-align:center; color:#1e293b; margin-bottom:20px;" id="pdfTitle">GREENLOG ARAZİ DEFTERİ RAPORU</h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Takson Adı</th>
                    <th>Toplayıcı</th>
                    <th>Toplama Tarihi</th>
                    <th>Lokasyon</th>
                    <th>Koordinat</th>
                    <th>Habitat / Vej. / Rakım</th>
                    <th style="text-align: center;" class="no-print">İşlem</th>
                </tr>
            </thead>
            <tbody>
                <?php if(count($kayitlar) > 0): ?>
                    <?php foreach($kayitlar as $kayit): ?>
                        <tr ondblclick="window.location='?sayfa=arazi_defteri&id=<?= $kayit['Kayit_ID'] ?? 0 ?>'" style="cursor: pointer;" title="Düzenlemek için çift tıklayabilirsiniz">
                            <td>
                                <strong><?= htmlspecialchars($kayit['Takson_Adi'] ?? 'Bilinmiyor') ?></strong>
                                <?php if(!empty($kayit['Bitki_Adi'])): ?>
                                    <br><span style="font-size:12px; color:#64748b;">(<?= htmlspecialchars($kayit['Bitki_Adi']) ?>)</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($kayit['Toplayici'] ?? '-') ?>
                                <?php if(!empty($kayit['Toplayici_No'])): ?>
                                    <span class="badge-info"><?= htmlspecialchars($kayit['Toplayici_No']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                    if(!empty($kayit['Toplama_Tarihi'])) {
                                        echo date('d.m.Y', strtotime($kayit['Toplama_Tarihi']));
                                    } else {
                                        echo '-';
                                    }
                                ?>
                            </td>
                            <td>
                                <?php 
                                    $yer = [];
                                    if(!empty($kayit['Ilce'])) $yer[] = htmlspecialchars($kayit['Ilce']);
                                    if(!empty($kayit['Sehir'])) $yer[] = htmlspecialchars($kayit['Sehir']);
                                    echo !empty($yer) ? implode(', ', $yer) : '-';
                                ?>
                            </td>
                            <td>
                                <?php if(!empty($kayit['Enlem']) && !empty($kayit['Boylam'])): ?>
                                    <a href="https://maps.google.com/?q=<?= htmlspecialchars($kayit['Enlem']) ?>,<?= htmlspecialchars($kayit['Boylam']) ?>" target="_blank" style="color: #10b981; text-decoration: none; font-weight:600;">
                                        <i class="fas fa-map-marker-alt"></i> Harita
                                    </a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($kayit['Habitat'] ?? '-') ?> 
                                <?php if(!empty($kayit['Vejetasyon'])): ?>
                                    <br><span style="font-size:12px; color:#64748b;">Vej: <?= htmlspecialchars($kayit['Vejetasyon']) ?></span>
                                <?php endif; ?>
                                <?php if(!empty($kayit['Yukseklik'])): ?>
                                    <br><span style="font-size:12px; color:#64748b;">Rakım: <?= htmlspecialchars($kayit['Yukseklik']) ?>m</span>
                                <?php endif; ?>
                            </td>
                            
                            <td style="text-align: center; white-space: nowrap;" class="no-print">
                                <a href="?sayfa=arazi_defteri&id=<?= htmlspecialchars($kayit['Kayit_ID'] ?? 0) ?>" class="btn-edit">
                                    <i class="fas fa-edit"></i> İncele
                                </a>
                                
                                <?php if($isAdmin): ?>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Bu kaydı sistemden kaldırmak istediğinize emin misiniz?');">
                                        <input type="hidden" name="sil_id" value="<?= htmlspecialchars($kayit['Kayit_ID']) ?>">
                                        <button type="button" 
                                                class="btn-delete" 
                                                onclick="araKayitSilConfirm(<?= htmlspecialchars($kayit['Kayit_ID']) ?>)" 
                                                title="Sil">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7">
                            <div class="empty-state">
                                <i class="fas fa-folder-open" style="font-size: 40px; margin-bottom: 15px; opacity: 0.3;"></i>
                                <p>Sistemde henüz kaydedilmiş aktif bir arazi defteri kaydı bulunmuyor.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    function downloadPDF() {
        const element = document.getElementById('exportArea');
        const title = document.getElementById('pdfTitle');
        title.style.display = 'block';

        const opt = {
            margin:       0.3,
            filename:     'Arazi_Defteri_Raporu.pdf',
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { scale: 2 },
            jsPDF:        { unit: 'in', format: 'a4', orientation: 'landscape' }
        };
        
        html2pdf().set(opt).from(element).save().then(() => {
            title.style.display = 'none';
        });
    }
    
    // 1. Toast Yapılandırması (Başarı mesajları için)
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        showCloseButton: true,
        timer: 2000,
        background: '#ffffff',
        iconColor: '#10b981'
    });

    // 2. Sayfa yüklendiğinde silme sonrası mesajı göster
    $(document).ready(function() {
        if(localStorage.getItem('goster_toast') === '1') {
            Toast.fire({ icon: 'success', title: 'Başarılı!', text: 'Kayıt başarıyla silindi.' });
            localStorage.removeItem('goster_toast');
        }
    });

    // 3. SİLME ONAYI (Premium Popup)
    function araKayitSilConfirm(id) {
        Swal.fire({
            title: 'Kayıt Silinecek',
            text: "Bu kaydı sistemden kaldırmak istediğinize emin misiniz?",
            icon: 'warning',
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#94a3b8',
            showCancelButton: true,
            confirmButtonText: 'Evet, Sil',
            cancelButtonText: 'İptal'
        }).then((result) => {
            if (result.isConfirmed) {
                // Formu arka planda oluştur ve gönder
                let form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="kayit_sil" value="1">
                    <input type="hidden" name="sil_id" value="${id}">
                `;
                document.body.appendChild(form);
                
                // Başarı mesajı için bayrak bırak
                localStorage.setItem('goster_toast', '1');
                form.submit();
            }
        });
    }

    // 4. PDF İndirme Fonksiyonu
    function downloadPDF() {
        const element = document.getElementById('exportArea');
        const title = document.getElementById('pdfTitle');
        title.style.display = 'block';

        const opt = {
            margin: 0.3,
            filename: 'Arazi_Defteri_Raporu.pdf',
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2 },
            jsPDF: { unit: 'in', format: 'a4', orientation: 'landscape' }
        };
        
        html2pdf().set(opt).from(element).save().then(() => {
            title.style.display = 'none';
        });
    }
</script>