<?php
/**
 * GreenLog - Tohum Evi Modülü (Modernize Edilmiş ve Otomatik Aksesyonlu)
 */
require_once 'db-connect.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Güvenlik: Giriş yapılmamışsa engelle
if (!isset($_SESSION['Personel_ID'])) { exit("Erişim Engellendi"); }

// --- 1. OTOMATİK AKSESYON NUMARASI ÜRETİCİ (Matematiksel Zeka Eklendi) ---
function getNextAksesyonNo($pdo) {
    $currentYear = date('Y');
    
    $stmt = $pdo->prepare("
        SELECT Aksesyon_No 
        FROM tohum_evi 
        WHERE Aksesyon_No LIKE ? 
        ORDER BY CAST(SUBSTRING_INDEX(Aksesyon_No, '-', -1) AS UNSIGNED) DESC 
        LIMIT 1
    ");
    $stmt->execute(["$currentYear-%"]);
    $lastAksesyon = $stmt->fetchColumn();

    if ($lastAksesyon) {
        $parts = explode('-', $lastAksesyon);
        $nextNumber = intval(end($parts)) + 1;
    } else {
        $nextNumber = 1;
    }

    return $currentYear . '-' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
}

// Yeni eklenecek tohum için sıradaki numarayı hazırda tutalım
$yeniAksesyonNo = getNextAksesyonNo($pdo);

// --- 2. YENİ TOHUM KAYT İŞLEMİ ---
if (isset($_POST['yeni_tohum_kaydet'])) {
    $aksesyon = trim($_POST['aksesyon_no']); 
    $takson_id = trim($_POST['takson_id']);  
    $toplayici = trim($_POST['toplayici']);
    $hasat = !empty($_POST['hasat_tarihi']) ? $_POST['hasat_tarihi'] : null;
    $koken = trim($_POST['koken']);
    $miktar = trim($_POST['miktar']);

    try {
        $pdo->beginTransaction();
        
        $sql = "INSERT INTO tohum_evi (Aksesyon_No, Takson_ID, Toplayici, Hasat_Tarihi, Koken, Miktar) VALUES (?, ?, ?, ?, ?, ?)";
        $pdo->prepare($sql)->execute([$aksesyon, $takson_id, $toplayici, $hasat, $koken, $miktar]);
        
        // LOG KAYDI
        $takson_adi = $pdo->query("SELECT Takson_Adi FROM takson WHERE Takson_ID = " . intval($takson_id))->fetchColumn() ?: 'Bilinmeyen Takson';
        $log_mesaj = "Tohum Evine Yeni Kayıt Girdi ($aksesyon): $takson_adi ($miktar)";
        
        $log_sql = "INSERT INTO sistem_loglari (Personel_ID, Islem_Detay, Islem_Tarihi, Tablo_Adi) VALUES (?, ?, NOW(), 'tohum_evi')";
        $pdo->prepare($log_sql)->execute([$_SESSION['Personel_ID'], $log_mesaj]);

        $pdo->commit();
        echo "<script>alert('✅ Tohum başarıyla kaydedildi! Aksesyon No: $aksesyon'); window.location=window.location.href;</script>";
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo "<script>alert('Kayıt Başarısız! Aynı Aksesyon Numarası zaten kayıtlı olabilir veya veritabanı hatası oluştu.');</script>";
    }
}

// --- 3. GEREKLİ VERİLERİ ÇEKME (Personel ve Taksonlar) ---
$personeller = [];
$taksonlar = [];
try {
    $stmt_personel = $pdo->query("SELECT Ad_Soyad FROM personel WHERE Rol = 'Personel' ORDER BY Ad_Soyad ASC");
    $personeller = $stmt_personel->fetchAll(PDO::FETCH_ASSOC);

    $stmt_takson = $pdo->query("SELECT Takson_ID, Takson_Adi, Bitki_Adi FROM takson ORDER BY Takson_Adi ASC");
    $taksonlar = $stmt_takson->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// --- 4. VERİTABANINDAN TOHUM LİSTESİNİ ÇEKME ---
$tohumlar = [];
try {
    $sql = "SELECT te.*, t.Takson_Adi, t.Bitki_Adi 
            FROM tohum_evi te
            LEFT JOIN takson t ON te.Takson_ID = t.Takson_ID
            ORDER BY te.Tohum_ID DESC";
    $stmt = $pdo->query($sql);
    $tohumlar = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "<div style='padding:20px; color:red;'>Veritabanı bağlantı hatası veya tablolar bulunamadı.</div>";
}

// --- VERİTABANINDAN ÜRETİM TALEPLERİNİ ÇEKME ---
$uretim_planlari = [];
try {
    $stmt_uretim = $pdo->query("SELECT * FROM uretim_plani ORDER BY Uretim_ID DESC");
    $tum_uretimler = $stmt_uretim->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($tum_uretimler as $uretim) {
        $aksesyonNo = $uretim['Aksesyon_No'];
        if (!isset($uretim_planlari[$aksesyonNo])) {
            $uretim_planlari[$aksesyonNo] = [];
        }
        $uretim_planlari[$aksesyonNo][] = $uretim;
    }
} catch (PDOException $e) {}
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    .t-container { background: #fff; border-radius: 20px; padding: 25px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); min-height: 80vh; position: relative;}
    
    .t-header-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; border-bottom: 1px solid #f1f5f9; padding-bottom: 20px; }
    .t-search-group { display: flex; gap: 10px; align-items: center; }
    .t-search-input { padding: 8px 15px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; width: 250px; outline: none; }
    
    .dropdown { position: relative; display: inline-block; padding-bottom: 5px; } 
    .dropdown-btn { background: #1e3a8a; color: white; padding: 10px 20px; border-radius: 8px; border: none; cursor: pointer; font-size: 13px; display: flex; align-items: center; gap: 8px; transition:0.2s;}
    .dropdown-btn:hover { background: #1e40af; }
    .dropdown-content { display: none; position: absolute; right: 0; top: 100%; background-color: #fff; min-width: 200px; box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.1); border-radius: 8px; z-index: 10; overflow: hidden; border: 1px solid #e2e8f0; }
    .dropdown-content a { color: #334155; padding: 12px 16px; text-decoration: none; display: block; font-size: 13px; border-bottom: 1px solid #f1f5f9; cursor: pointer;}
    .dropdown-content a:hover { background-color: #f8fafc; color:#1e3a8a; font-weight:600;}
    .dropdown:hover .dropdown-content { display: block; }

    .t-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    .t-table th { text-align: left; padding: 15px; background: #f8fafc; color: #64748b; font-size: 13px; border-bottom: 2px solid #edf2f7; }
    .t-table td { padding: 15px; border-bottom: 1px solid #f1f5f9; font-size: 13px; color: #475569; cursor: pointer; transition: 0.2s;}
    .t-table tbody tr:hover { background: #f8fafc; }
    .text-muted { color: #94a3b8; font-style: italic; }

    .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px); }
    .modal-content { background: #fff; width: 650px; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); overflow: hidden; animation: slideDown 0.3s ease-out; }
    .modal-header { padding: 20px 25px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; }
    .modal-header h3 { margin: 0; color: #1e3a8a; font-size: 18px; }
    .close-btn { background: none; border: none; font-size: 24px; color: #94a3b8; cursor: pointer; transition: 0.2s; line-height: 1;}
    .close-btn:hover { color: #ef4444; }

    .modal-tabs { display: flex; border-bottom: 1px solid #e2e8f0; background: #f8fafc; }
    .tab-link { flex: 1; text-align: center; padding: 15px 10px; font-size: 13px; color: #64748b; text-decoration: none; font-weight: 600; border-bottom: 2px solid transparent; transition: 0.2s; }
    .tab-link:hover { color: #1e3a8a; }
    .tab-link.active { color: #1e3a8a; border-bottom: 2px solid #1e3a8a; background: #fff; }
    .modal-body { padding: 25px; min-height: 250px; max-height: 70vh; overflow-y: auto;}
    .tab-content { display: none; }
    .tab-content.active { display: block; animation: fadeIn 0.3s; }

    .upload-area { border: 2px dashed #cbd5e1; border-radius: 12px; padding: 40px; text-align: center; background: #f8fafc; transition: 0.3s; cursor: pointer; display:flex; flex-direction:column; align-items:center; justify-content:center; height: 150px; }
    .upload-area:hover { border-color: #3b82f6; background: #eff6ff; }
    .t-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
    .t-input, .t-select { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; box-sizing: border-box; }
    .t-label { display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 5px; }
    .btn-submit { background: #10b981; color: white; border: none; padding: 12px; border-radius: 8px; width: 100%; font-weight: 600; cursor: pointer; margin-top: 15px; }
    .btn-submit:hover { background: #059669; }
    .input-locked { background-color: #f1f5f9 !important; color: #3b82f6; cursor: not-allowed; font-weight: bold; font-family: monospace; font-size: 15px; text-align: center;}
    
    .detail-list { list-style: none; padding: 0; margin: 0; }
    .detail-list li { padding: 12px 0; border-bottom: 1px solid #f1f5f9; font-size: 14px; color: #334155; display: flex; justify-content: space-between;}
    .detail-list li strong { color: #64748b; font-weight: 600; }

    /* Aktif Filtre Rozeti Tasarımı */
    .active-filter-badge { display: none; align-items: center; gap: 6px; background: #eff6ff; color: #2563eb; padding: 6px 12px; border-radius: 8px; font-size: 12px; font-weight: 600; border: 1px solid #bfdbfe; }

    @keyframes slideDown { from { transform: translateY(-20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

    @media print {
        body * { visibility: hidden; }
        .t-container, .t-container * { visibility: visible; }
        .t-container { position: absolute; left: 0; top: 0; width: 100%; box-shadow: none; padding: 0; margin: 0; }
        .t-header-bar, .dropdown, #searchInput, .active-filter-badge { display: none !important; }
        .t-table th, .t-table td { border: 1px solid #cbd5e1 !important; padding: 8px !important; }
    }
</style>

<div class="t-container">
    <h2 style="margin:0 0 20px 0; color:#1e293b;">Tohum Evi</h2>
    
    <div class="t-header-bar">
        <div class="t-search-group">
            <input type="text" id="searchInput" class="t-search-input" placeholder="Aksesyon veya Takson ara...">
            <div id="activeFilterDisplay" class="active-filter-badge">
                <span id="activeFilterText"></span>
                <i class="fas fa-times-circle" onclick="clearFilterSystem()" style="cursor:pointer; color:#ef4444; font-size:14px; margin-left:4px;" title="Filtreyi Temizle"></i>
            </div>
        </div>
        
        <div class="dropdown">
            <button class="dropdown-btn">İşlemler <i class="fas fa-caret-down"></i></button>
            <div class="dropdown-content">
                <a onclick="openAddModal()"><i class="fas fa-plus-circle" style="color:#10b981; width:20px;"></i> Yeni Tohum Kabulü</a>
                <a onclick="openFilterSystem()"><i class="fas fa-filter" style="color:#3b82f6; width:20px;"></i> Veriyi Süz</a>
                <a onclick="window.print()"><i class="fas fa-print" style="color:#475569; width:20px;"></i> Listeyi Yazdır (PDF)</a>
                <a onclick="downloadPDF()"><i class="fas fa-file-pdf" style="color:#ef4444; width:20px;"></i> PDF Olarak İndir</a>
            </div>
        </div>
    </div>

    <div style="overflow-x:auto;" id="exportArea">
        <table class="t-table" id="seedTable">
            <thead>
                <tr>
                    <th>Aksesyon No</th>
                    <th>Takson Adı</th>
                    <th>Toplayıcı</th>
                    <th>Hasat Tarihi</th>
                    <th>Köken</th>
                    <th>Miktar</th>
                </tr>
            </thead>
            <tbody>
                <?php if(count($tohumlar) > 0): ?>
                    <?php foreach($tohumlar as $tohum): ?>
                    <tr class="clickable-row" 
                        data-aksesyon="<?= htmlspecialchars(strtolower($tohum['Aksesyon_No'])) ?>"
                        data-takson="<?= htmlspecialchars(strtolower(($tohum['Takson_Adi'] ?? '') . ' ' . ($tohum['Bitki_Adi'] ?? ''))) ?>"
                        data-toplayici="<?= htmlspecialchars(strtolower($tohum['Toplayici'] ?: 'bilgi yok')) ?>"
                        data-hasat="<?= empty($tohum['Hasat_Tarihi']) ? 'Bilgi yok' : date('d.m.Y', strtotime($tohum['Hasat_Tarihi'])) ?>"
                        data-koken="<?= htmlspecialchars($tohum['Koken'] ?: 'Belirtilmedi') ?>"
                        data-miktar="<?= htmlspecialchars($tohum['Miktar']) ?>">
                        
                        <td style="color:#3b82f6; font-weight:700; font-family:monospace; font-size: 15px;"><?= htmlspecialchars($tohum['Aksesyon_No']) ?></td>
                        <td>
                            <strong><?= htmlspecialchars($tohum['Takson_Adi'] ?? 'Bilinmiyor') ?></strong>
                            <?php if(!empty($tohum['Bitki_Adi'])): ?>
                                <br><small style="color:#64748b;">(<?= htmlspecialchars($tohum['Bitki_Adi']) ?>)</small>
                            <?php endif; ?>
                        </td>
                        <td class="<?= empty($tohum['Toplayici']) ? 'text-muted' : '' ?>"><?= empty($tohum['Toplayici']) ? 'Bilgi yok' : htmlspecialchars($tohum['Toplayici']) ?></td>
                        
                        <td class="<?= empty($tohum['Hasat_Tarihi']) ? 'text-muted' : '' ?>">
                            <?= empty($tohum['Hasat_Tarihi']) ? 'Bilgi yok' : date('d.m.Y', strtotime($tohum['Hasat_Tarihi'])) ?>
                        </td>
                        
                        <td><small><?= htmlspecialchars($tohum['Koken'] ?: 'Belirtilmedi') ?></small></td>
                        <td><span style="background:#f1f5f9; padding:4px 8px; border-radius:4px; font-weight:600;"><?= htmlspecialchars($tohum['Miktar']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align:center; padding: 50px; color: #94a3b8;">
                            <i class="fas fa-seedling" style="font-size:30px; margin-bottom:10px; opacity:0.5;"></i><br>
                            Henüz tohum kaydı bulunmuyor. Sağ üstten yeni bir kayıt ekleyin.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <div style="text-align:center; padding: 20px; color:#94a3b8; font-size:12px;">
        Toplam <strong id="rowCountDisplay"><?= count($tohumlar) ?></strong> kayıt listeleniyor.
    </div>
</div>

<div id="addModal" class="modal-overlay">
    <div class="modal-content" style="width: 500px;">
        <div class="modal-header" style="background:#f8fafc;">
            <h3 style="color:#10b981;"><i class="fas fa-seedling"></i> Yeni Tohum Kabulü</h3>
            <button class="close-btn" onclick="closeAddModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST">
                <div class="t-form-grid">
                    <div>
                        <label class="t-label">Aksesyon No *</label>
                        <input type="text" name="aksesyon_no" class="t-input input-locked" value="<?= $yeniAksesyonNo ?>" readonly required>
                    </div>
                    <div>
                        <label class="t-label">Miktar / Birim *</label>
                        <input type="text" name="miktar" class="t-input" placeholder="Örn: 100 Gram veya 50 Adet" required>
                    </div>
                    <div style="grid-column: span 2;">
                        <label class="t-label">Takson Türü *</label>
                        <select name="takson_id" class="t-select" required>
                            <option value="">Botanik Tür Seçin...</option>
                            <?php foreach($taksonlar as $t): ?>
                                <option value="<?= $t['Takson_ID'] ?>">
                                    <?= htmlspecialchars($t['Takson_Adi']) ?> (<?= htmlspecialchars($t['Bitki_Adi']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="t-label">Toplayıcı Kişi</label>
                        <select name="toplayici" class="t-input">
                            <option value="">Seçiniz (Opsiyonel)</option>
                            <?php foreach($personeller as $kisi): ?>
                                <option value="<?= htmlspecialchars($kisi['Ad_Soyad']) ?>">
                                    <?= htmlspecialchars($kisi['Ad_Soyad']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="t-label">Hasat Tarihi</label>
                        <input type="date" name="hasat_tarihi" class="t-input">
                    </div>
                    <div style="grid-column: span 2;">
                        <label class="t-label">Köken Bilgisi</label>
                        <select name="koken" class="t-select">
                            <option value="">Seçin (Opsiyonel)</option>
                            <option value="Doğal (Yabani)">Doğal (Yabani)</option>
                            <option value="Kültür (Yetiştirme)">Kültür (Yetiştirme)</option>
                            <option value="Bağış / Takas">Bağış / Takas</option>
                            <option value="Satın Alma">Satın Alma</option>
                        </select>
                    </div>
                </div>
                <button type="submit" name="yeni_tohum_kaydet" class="btn-submit">
                    <i class="fas fa-save"></i> Sisteme Kaydet
                </button>
            </form>
        </div>
    </div>
</div>

<div id="seedModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle" style="color:#334155; font-family: monospace;">Detaylar</h3>
            <button class="close-btn" onclick="closeDetailModal()">&times;</button>
        </div>
        
        <div class="modal-tabs">
            <a href="#" class="tab-link active" data-target="tab-kayit">Kayıt Bilgileri</a>
            <a href="#" class="tab-link" data-target="tab-stok">Stok Hareketleri</a>
            <a href="#" class="tab-link" data-target="tab-uretim">Üretim Talepleri</a>
            <a href="#" class="tab-link" data-target="tab-gonderim">Gönderim Talepleri</a>
            <a href="#" class="tab-link" data-target="tab-foto">Fotoğraflar</a>
        </div>
        
        <div class="modal-body">
            <div id="tab-kayit" class="tab-content active">
                <ul class="detail-list">
                    <li><strong>Takson Adı:</strong> <span id="detay-takson">-</span></li>
                    <li><strong>Miktar:</strong> <span id="detay-miktar" style="background:#f1f5f9; padding:2px 8px; border-radius:4px; font-weight:600;">-</span></li>
                    <li><strong>Toplayıcı:</strong> <span id="detay-toplayici">-</span></li>
                    <li><strong>Hasat Tarihi:</strong> <span id="detay-hasat">-</span></li>
                    <li><strong>Köken:</strong> <span id="detay-koken">-</span></li>
                </ul>
            </div>
            
            <div id="tab-stok" class="tab-content">
                <div style="text-align:center; color:#94a3b8; padding:30px;">Henüz stok hareketi bulunmuyor.</div>
            </div>
            
            <div id="tab-uretim" class="tab-content">
                <div style="text-align:center; color:#94a3b8; padding:30px;">Bu bitkiye ait üretim talebi yok.</div>
            </div>
            
            <div id="tab-gonderim" class="tab-content">
                <div style="text-align:center; color:#94a3b8; padding:30px;">Gönderim talebi kaydı yok.</div>
            </div>
            
            <div id="tab-foto" class="tab-content">
                <div class="upload-area">
                    <i class="fas fa-cloud-upload-alt" style="font-size: 30px; color: #94a3b8; margin-bottom: 10px;"></i>
                    <p style="margin: 0; color: #64748b; font-size: 14px;">Dosyaları sürükleyip buraya bırakın.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const tumUretimVerileri = <?= json_encode($uretim_planlari) ?>;

function openAddModal() { document.getElementById('addModal').style.display = 'flex'; }
function closeAddModal() { document.getElementById('addModal').style.display = 'none'; }
function closeDetailModal() { document.getElementById('seedModal').style.display = 'none'; }

function downloadPDF() {
    const element = document.getElementById('exportArea');
    const opt = {
        margin:       0.5,
        filename:     'Tohum_Listesi.pdf',
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 2 },
        jsPDF:        { unit: 'in', format: 'a4', orientation: 'landscape' }
    };
    html2pdf().set(opt).from(element).save();
}

// 🎯 VERİYİ SÜZ / GELİŞMİŞ FİLTRELEME SİSTEMİ (Global Değişkenler)
let activeFilterType = null;
let activeFilterValue = '';

function openFilterSystem() {
    Swal.fire({
        title: 'Veriyi Hangi Kriterle Süzmek İstersiniz?',
        icon: 'question',
        input: 'select',
        inputOptions: {
            'aksesyon': 'Aksesyon Numarasına Göre',
            'takson': 'Takson / Bitki Adına Göre',
            'toplayici': 'Toplayıcı Kişiye Göre'
        },
        inputPlaceholder: 'Bir süzme kriteri seçin...',
        showCancelButton: true,
        confirmButtonText: 'İlerle <i class="fas fa-arrow-right"></i>',
        cancelButtonText: 'Vazgeç',
        confirmButtonColor: '#1e3a8a',
        cancelButtonColor: '#64748b',
        preConfirm: (value) => {
            if (!value) {
                Swal.showValidationMessage('Lütfen bir kriter seçiniz!');
            }
            return value;
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const criteria = result.value;
            let placeholderText = "";
            
            if(criteria === 'aksesyon') placeholderText = "Örn: 2026-00001";
            else if(criteria === 'takson') placeholderText = "Örn: Quercus veya Meşe";
            else if(criteria === 'toplayici') placeholderText = "Toplayıcı adını yazın...";

            Swal.fire({
                title: 'Filtre Değerini Girin',
                text: 'Yazdığınız kelimeyi içeren tüm kayıtlar getirilicektir.',
                input: 'text',
                inputPlaceholder: placeholderText,
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-filter"></i> Süzgeci Uygula',
                cancelButtonText: 'Geri Dön',
                confirmButtonColor: '#10b981',
                preConfirm: (textValue) => {
                    if (!textValue || textValue.trim() === '') {
                        Swal.showValidationMessage('Arama ifadesi boş bırakılamaz!');
                    }
                    return textValue.trim().toLowerCase();
                }
            }).then((filterResult) => {
                if (filterResult.isConfirmed) {
                    activeFilterType = criteria;
                    activeFilterValue = filterResult.value;
                    applyFilters();
                }
            });
        }
    });
}

function applyFilters() {
    let rows = document.querySelectorAll('#seedTable tbody tr');
    let globalSearch = document.getElementById('searchInput').value.toLowerCase();
    let visibleCount = 0;

    rows.forEach(row => {
        // Satırda tıklama event'i yoksa veya boş uyarısı satırıysa atla
        if (!row.classList.contains('clickable-row')) return;

        let matchFilter = true;
        let matchGlobal = true;

        // 1. Gelişmiş "Veriyi Süz" Kontrolü
        if (activeFilterType && activeFilterValue !== '') {
            let dataAttrValue = row.getAttribute('data-' + activeFilterType) || '';
            if (!dataAttrValue.includes(activeFilterValue)) {
                matchFilter = false;
            }
        }

        // 2. Klasik Arama Çubuğu Kontrolü
        if (globalSearch !== '') {
            let textToSearch = row.cells[0].innerText + " " + row.cells[1].innerText + " " + row.cells[2].innerText;
            if (!textToSearch.toLowerCase().includes(globalSearch)) {
                matchGlobal = false;
            }
        }

        // Şartlar sağlanıyorsa satırı göster
        if (matchFilter && matchGlobal) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    // Toplam sayıyı dinamik güncelle
    document.getElementById('rowCountDisplay').innerText = visibleCount;

    // Arama barının yanındaki süzgeç rozetini göster/gizle
    const filterDisplay = document.getElementById('activeFilterDisplay');
    const filterText = document.getElementById('activeFilterText');
    
    if (activeFilterType && activeFilterValue !== '') {
        let typeLabel = activeFilterType === 'aksesyon' ? 'Aksesyon' : (activeFilterType === 'takson' ? 'Takson' : 'Toplayıcı');
        filterText.innerHTML = `<i class="fas fa-filter"></i> Süzgeç: <u>${typeLabel}</u> / "<strong>${activeFilterValue}</strong>"`;
        filterDisplay.style.display = 'inline-flex';
    } else {
        filterDisplay.style.display = 'none';
    }
}

function clearFilterSystem() {
    activeFilterType = null;
    activeFilterValue = '';
    applyFilters();
}

document.addEventListener("DOMContentLoaded", function() {
    
    const rows = document.querySelectorAll('.clickable-row');
    rows.forEach(row => {
        row.addEventListener('click', function(e) {
            // Süzgeç temizleme çarpısına tıklandığında detay modalı açılmasını engelle
            if(e.target.classList.contains('fa-times-circle') || e.target.id === 'activeFilterDisplay') {
                return;
            }

            let aksesyonNo = this.getAttribute('data-aksesyon').toUpperCase();
            let takson = this.getAttribute('data-takson');
            let bitki = this.getAttribute('data-bitki');
            let toplayici = this.getAttribute('data-toplayici');
            let hasat = this.getAttribute('data-hasat');
            let koken = this.getAttribute('data-koken');
            let miktar = this.getAttribute('data-miktar');
            
            // İlk harfleri büyütmek için görsel düzeltme
            let tamTakson = this.cells[1].innerHTML;

            document.getElementById('modalTitle').innerText = aksesyonNo;
            document.getElementById('detay-takson').innerHTML = tamTakson;
            document.getElementById('detay-toplayici').innerText = this.cells[2].innerText;
            document.getElementById('detay-hasat').innerText = hasat;
            document.getElementById('detay-koken').innerText = koken;
            document.getElementById('detay-miktar').innerText = miktar;
            
            let tabUretim = document.getElementById('tab-uretim');
            let uretimler = tumUretimVerileri[aksesyonNo];
            
            if (uretimler && uretimler.length > 0) {
                let html = '<ul class="detail-list">';
                uretimler.forEach(talep => {
                    let tarih = talep.Kayit_Tarihi || 'Tarih Yok';
                    
                    if(tarih !== 'Tarih Yok') {
                        let d = new Date(tarih);
                        if(!isNaN(d)) {
                            tarih = ("0" + d.getDate()).slice(-2) + "." + ("0"+(d.getMonth()+1)).slice(-2) + "." + d.getFullYear();
                        }
                    }
                    
                    let uretimMiktar = talep.Miktar || 'Belirtilmedi';
                    let durum = talep.Durum || 'Bekliyor';
                    
                    html += `<li>
                                <div>
                                    <strong style="color:#1e3a8a;">📅 ${tarih}</strong><br>
                                    <small style="color:#64748b;">Durum: <span style="font-weight:600; color:#10b981;">${durum}</span></small>
                                </div>
                                <div>
                                    <span style="background:#f1f5f9; padding:4px 8px; border-radius:4px; font-weight:600; font-size:12px;">Hedef: ${uretimMiktar}</span>
                                </div>
                             </li>`;
                });
                html += '</ul>';
                tabUretim.innerHTML = html;
            } else {
                tabUretim.innerHTML = '<div style="text-align:center; color:#94a3b8; padding:30px;">Bu bitkiye ait üretim talebi yok.</div>';
            }

            document.getElementById('seedModal').style.display = 'flex';
            
            document.querySelectorAll('.tab-link').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            document.querySelector('.tab-link[data-target="tab-kayit"]').classList.add('active');
            document.getElementById('tab-kayit').classList.add('active');
        });
    });

    const tabs = document.querySelectorAll('.tab-link');
    tabs.forEach(tab => {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            let targetID = this.getAttribute('data-target');
            
            document.querySelectorAll('.tab-link').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            
            this.classList.add('active');
            document.getElementById(targetID).classList.add('active');
        });
    });

    const overlays = document.querySelectorAll('.modal-overlay');
    overlays.forEach(overlay => {
        overlay.addEventListener('click', function(e) {
            if (e.target === this) {
                this.style.display = 'none';
            }
        });
    });

    // Standart arama çubuğu entegrasyonu (Filtrelerle senkronize çalışır)
    const searchInput = document.getElementById('searchInput');
    if(searchInput) {
        searchInput.addEventListener('keyup', applyFilters);
    }
}); 
</script>