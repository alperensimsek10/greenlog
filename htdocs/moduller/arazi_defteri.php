<?php
/**
 * GreenLog - Arazi Defteri Modülü (İzole Yetki ve Tam Korumalı)
 */
require_once 'db-connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['Personel_ID'])) { 
    exit("Erişim Engellendi"); 
}

// Admin Yetkisi ve Kullanıcı Bilgisini Çek
$rol = $_SESSION['Rol']; // Rolü burada tanımlıyoruz ki aşağıda kullanalım
$isAdmin = (isset($rol) && trim(strtolower($rol)) === 'admin');
$kullanici_id = $_SESSION['Personel_ID'];
$bugun = date('Y-m-d'); // <-- Artık her yerde çalışır

$stmt_kisi = $pdo->prepare("SELECT Ad_Soyad FROM personel WHERE Personel_ID = ?");
$stmt_kisi->execute([$kullanici_id]);
$kullanici_ad_soyad = $stmt_kisi->fetchColumn();


// --- 1. DÜZENLEME MODU KONTROLÜ ---
$edit_data = null;
$is_edit = false;
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $is_edit = true;
    $stmt_edit = $pdo->prepare("SELECT * FROM arazi_defteri WHERE Kayit_ID = ?");
    $stmt_edit->execute([$_GET['id']]);
    $edit_data = $stmt_edit->fetch(PDO::FETCH_ASSOC);

    // GÜVENLİK KURALI: Admin değilse ve kayıt kendine ait değilse sayfadan kovar!
    if (!$isAdmin && $edit_data['Toplayici'] !== $kullanici_ad_soyad) {
        echo "<script>alert('❌ Yetki Hatası: Başka bir personele ait kaydı görüntüleyemez veya düzenleyemezsiniz!'); window.location='?sayfa=arazi_defteri_gecmis';</script>";
        exit;
    }
}

// --- 2. VERİTABANINA KAYIT/GÜNCELLEME İŞLEMİ ---
if (isset($_POST['arazi_kaydet'])) {
    $kayit_id = $_POST['kayit_id'] ?? '';
    $toplayici = trim($_POST['toplayici'] ?? '');
    $toplayici_no = trim($_POST['toplayici_no'] ?? '');
    $takson_id = !empty($_POST['takson_id']) ? $_POST['takson_id'] : null;
    $toplama_tarihi = !empty($_POST['toplama_tarihi']) ? $_POST['toplama_tarihi'] : null;
    
    $yardimci_toplayici = trim($_POST['yardimci_toplayici'] ?? '');
    $ulke = trim($_POST['ulke'] ?? 'Türkiye');
    $sehir = trim($_POST['sehir'] ?? '');
    $ilce = trim($_POST['ilce'] ?? '');
    $lokasyon = trim($_POST['lokasyon'] ?? '');
    $enlem = trim($_POST['enlem'] ?? '');
    $boylam = trim($_POST['boylam'] ?? '');
    $habitat = trim($_POST['habitat'] ?? '');
    $vejetasyon = trim($_POST['vejetasyon'] ?? '');
    $yukseklik = trim($_POST['yukseklik'] ?? '');
    $baki = trim($_POST['baki'] ?? '');
    $toplayici_notu = trim($_POST['toplayici_notu'] ?? '');

    // BACKEND GÜVENLİĞİ 1: Toplayıcı ve Yardımcı aynı olamaz
    if ($toplayici !== '' && $toplayici === $yardimci_toplayici) {
        echo "<script>alert('❌ Hata: Toplayıcı ile Yardımcı Toplayıcı aynı kişi olamaz!'); window.history.back();</script>";
        exit;
    }

    // BACKEND GÜVENLİĞİ 2: Rakım Eksi Olamaz!
    if ($yukseklik !== '' && $yukseklik < 0) {
        echo "<script>alert('❌ Hata: Rakım değeri negatif (sıfırdan küçük) olamaz!'); window.history.back();</script>";
        exit;
    }

    try {
        $pdo->beginTransaction();

        if (!empty($kayit_id)) {
            $sql = "UPDATE arazi_defteri SET 
                    Toplayici=?, Toplayici_No=?, Takson_ID=?, Toplama_Tarihi=?, Yardimci_Toplayici=?, 
                    Ulke=?, Sehir=?, Ilce=?, Lokasyon=?, Enlem=?, Boylam=?, Habitat=?, Vejetasyon=?, Yukseklik=?, Baki=?, Toplayici_Notu=? 
                    WHERE Kayit_ID=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$toplayici, $toplayici_no, $takson_id, $toplama_tarihi, $yardimci_toplayici, $ulke, $sehir, $ilce, $lokasyon, $enlem, $boylam, $habitat, $vejetasyon, $yukseklik, $baki, $toplayici_notu, $kayit_id]);
            
            $log_mesaj = "Arazi Kaydı Güncellendi (Kayıt ID: #$kayit_id)";
            $log_sql = "INSERT INTO sistem_loglari (Personel_ID, Islem_Detay, Islem_Tarihi, Tablo_Adi) VALUES (?, ?, NOW(), 'arazi_defteri')";
            $pdo->prepare($log_sql)->execute([$_SESSION['Personel_ID'], $log_mesaj]);

            $pdo->commit();
            
            echo "<script>
                    localStorage.setItem('goster_toast', '1');
                    window.location.href = '?sayfa=arazi_defteri_gecmis';
                  </script>";
        } else {
            $sql = "INSERT INTO arazi_defteri 
                    (Toplayici, Toplayici_No, Takson_ID, Toplama_Tarihi, Yardimci_Toplayici, Ulke, Sehir, Ilce, Lokasyon, Enlem, Boylam, Habitat, Vejetasyon, Yukseklik, Baki, Toplayici_Notu) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$toplayici, $toplayici_no, $takson_id, $toplama_tarihi, $yardimci_toplayici, $ulke, $sehir, $ilce, $lokasyon, $enlem, $boylam, $habitat, $vejetasyon, $yukseklik, $baki, $toplayici_notu]);
            
            $log_mesaj = "Yeni Arazi Kaydı Oluşturuldu (Toplayıcı No: $toplayici_no)";
            $log_sql = "INSERT INTO sistem_loglari (Personel_ID, Islem_Detay, Islem_Tarihi, Tablo_Adi) VALUES (?, ?, NOW(), 'arazi_defteri')";
            $pdo->prepare($log_sql)->execute([$_SESSION['Personel_ID'], $log_mesaj]);
            
            $pdo->commit();
            echo "<script>
                    localStorage.setItem('goster_toast', '1');
                    // Formu temizle ve kullanıcıyı aynı sayfada tut
                    window.location.href = '?sayfa=arazi_defteri'; 
                  </script>";
        }
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo "<script>alert('Hata Oluştu: " . addslashes($e->getMessage()) . "');</script>";
    }
}

// --- 3. GEREKLİ VERİLERİ ÇEK ---
$personeller = [];
$taksonlar = [];
try {
    $stmt_personel = $pdo->query("SELECT Personel_ID, Ad_Soyad FROM personel WHERE Rol = 'Personel' ORDER BY Ad_Soyad ASC");
    $personeller = $stmt_personel->fetchAll(PDO::FETCH_ASSOC);

    $stmt_takson = $pdo->query("SELECT Takson_ID, Takson_Adi, Bitki_Adi FROM takson ORDER BY Takson_Adi ASC");
    $taksonlar = $stmt_takson->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}
?>

<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    .t-container { background: #fff; border-radius: 20px; padding: 25px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); width: 100%; box-sizing: border-box; }
    .page-title { margin:0 0 25px 0; color:#1e293b; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;}
    .flex-row { display: flex; flex-wrap: wrap; gap: 15px; margin-bottom: 15px; width: 100%; align-items: flex-end; }
    .flex-col { flex: 1 1 200px; display: flex; flex-direction: column; min-width: 0; }
    .flex-col-full { flex: 1 1 100%; display: flex; flex-direction: column; }
    .form-section { background: #f8fafc; border: 1px solid #e2e8f0; padding: 20px; border-radius: 12px; margin-bottom: 20px; }
    .t-label { font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
    .t-input, .t-select, .t-textarea { width: 100%; padding: 12px 15px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; color: #334155; outline: none; transition: 0.2s; background: #fff; height: 46px; box-sizing: border-box; }
    .t-textarea { height: auto; min-height: 80px; resize: vertical; }
    .input-locked { background-color: #f1f5f9 !important; color: #64748b; cursor: not-allowed; font-weight: bold; }
    
    .btn-primary { background: #1e3a8a; color: white; border: none; padding: 12px 25px; border-radius: 8px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; text-decoration: none;}
    .btn-success { background: #10b981; } 
    .btn-pdf { background: #3b82f6; } 
    .btn-pdf:hover { background: #2563eb; }
    
    .select2-container { width: 100% !important; min-width: 0; }
    .select2-container--default .select2-selection--single { height: 46px; border: 1px solid #cbd5e1; border-radius: 8px; padding: 8px; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 44px; }

    @media print {
        .no-print { display: none !important; }
        .form-section { border: 1px solid #cbd5e1 !important; background: #fff !important; }
    }
    
    .pdf-mode { padding: 0 !important; box-shadow: none !important; background: #fff !important; }
    .pdf-mode .form-section { padding: 12px 15px !important; margin-bottom: 10px !important; border: 1px solid #94a3b8 !important; background: #fff !important;}
    .pdf-mode .t-input, .pdf-mode .t-select, .pdf-mode .select2-container--default .select2-selection--single { 
        height: 30px !important; padding: 4px 10px !important; font-size: 12px !important; border-color: #cbd5e1 !important; background: #f8fafc !important;
    }
    .pdf-mode .t-label { font-size: 10px !important; margin-bottom: 3px !important; color: #1e293b !important; }
    .pdf-mode .t-textarea { min-height: 40px !important; padding: 6px 10px !important; }
    .pdf-mode .select2-selection__arrow, .pdf-mode .select2-selection__clear { display: none !important; }
    
    /* Tarih kutusu şıklığı ve elle müdahale engeli */
    .custom-area { width: 100%; border: 2px solid var(--border); border-radius: 12px; padding: 15px; box-sizing: border-box; font-family: inherit; font-size: 14px; transition: 0.2s; cursor: pointer; }
    .custom-area:focus { border-color: var(--p); outline: none; background: #fff; }
    
    /* Toast mesajları için tepeden boşluk */
    .swal2-toast {
        margin-top: 10px !important;
    }
    
    /* LED Efekti ve Hata Sınıfı */
    @keyframes border-pulse {
        0% { border-color: #ef4444; box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
        70% { border-color: #ef4444; box-shadow: 0 0 0 6px rgba(239, 68, 68, 0); }
        100% { border-color: #ef4444; box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
    }

    .input-error-animate {
        animation: border-pulse 1.5s ease-in-out forwards; /* 'forwards' yerine süreyi uzattık */
    }

    /* Select2 kutusunu da yakalamak için */
    .select2-container--default.input-error-animate .select2-selection--single {
        animation: border-pulse 1.5s ease-in-out forwards;
    }
    
    /* Tarih kutusunu standart kutularla aynı formata getir */
    input[type="date"].custom-area {
        background-color: #ffffff !important;
        border: 1px solid #cbd5e1 !important; /* Diğer kutularınla aynı border */
        border-radius: 8px !important;       /* Diğer kutularınla aynı radius */
        height: 46px !important;             /* Diğer inputlarınla aynı yükseklik */
        padding: 12px 15px !important;
        color: #334155 !important;
        font-family: inherit !important;
        appearance: none; /* Tarayıcının kendi stilini baskıla */
    }

    /* Tarih kutusuna tıklandığında odaklanma efekti */
    input[type="date"].custom-area:focus {
        border-color: #3b82f6 !important;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1) !important;
    }

    /* Takvim ikonunun rengini kutu stiline uydur */
    input[type="date"]::-webkit-calendar-picker-indicator {
        cursor: pointer;
        filter: invert(0.5); /* İkonu daha belirgin ve uyumlu yap */
    }
    
</style>

<div class="t-container" id="pdfFormArea">
    
    <div id="pdfTitleSingle" style="display:none; margin-bottom: 20px; border-bottom: 2px solid #10b981; padding-bottom:15px; align-items: center; justify-content: space-between;">
        <div style="flex: 1; text-align: left;">
            <?php if($is_edit): ?>
                <?php $kayit_linki = "http://greenlog.42web.io/panel.php?sayfa=arazi_defteri&id=" . $edit_data['Kayit_ID']; ?>
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=80x80&data=<?= urlencode($kayit_linki) ?>" style="width: 70px; height: 70px; border-radius: 8px; border: 1px solid #cbd5e1; padding: 3px;" alt="QR Kod">
            <?php endif; ?>
        </div>
        <div style="flex: 3; text-align: center;">
            <h2 style="margin:0; color:#1e293b;">GREENLOG BİTKİ BİLGİ FORMU</h2>
            <small style="color:#64748b; font-weight:600;">Sistem Kayıt ID: <?= $edit_data['Kayit_ID'] ?? 'YENİ KAYIT' ?> | Çıktı Tarihi: <?= date('d.m.Y') ?></small>
        </div>
        <div style="flex: 1; text-align: right;">
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=80x80&data=GreenLog" style="width: 70px; height: 70px; opacity: 0;" alt="Denge">
        </div>
    </div>

    <div class="page-title no-print">
        <h2 style="margin:0;">
            <i class="fas <?= $is_edit ? 'fa-edit' : 'fa-book-open' ?>" style="color:#10b981; margin-right:10px;"></i> 
            <?= $is_edit ? 'Kayıt Düzenle' : 'Arazi Defteri' ?>
        </h2>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <?php if($is_edit): ?>
                <button type="button" onclick="downloadSinglePDF()" class="btn-primary btn-pdf">
                    <i class="fas fa-file-pdf"></i> PDF İndir
                </button>
                <a href="?sayfa=arazi_defteri_gecmis" class="btn-primary" style="background: #ef4444; padding: 10px 20px;"><i class="fas fa-times"></i> İptal</a>
            <?php else: ?>
                <a href="?sayfa=arazi_defteri_gecmis" class="btn-primary" style="background: #64748b;"><i class="fas fa-list"></i> Geçmiş Kayıtlar</a>
            <?php endif; ?>

            <button type="button" class="btn-primary <?= $is_edit ? 'btn-success' : '' ?>" onclick="formKontrol()">
                <i class="fas fa-save"></i> <?= $is_edit ? 'Değişiklikleri Güncelle' : 'Kaydet' ?>
            </button>
        </div>
    </div>

    <form id="araziForm" method="POST" onsubmit="return false;">
        <input type="hidden" name="arazi_kaydet" value="1">
        <input type="hidden" name="kayit_id" value="<?= htmlspecialchars($edit_data['Kayit_ID'] ?? '') ?>"> 

        <div class="form-section flex-row">
            <div class="flex-col">
                <label class="t-label">Toplayıcı *</label>
                <?php if($isAdmin): ?>
                    <select name="toplayici" id="toplayici_select" class="t-select" required onchange="otomatikNoGetir()">
                        <option value="">Seçin</option>
                        <?php foreach($personeller as $kisi): ?>
                            <option value="<?= htmlspecialchars($kisi['Ad_Soyad']) ?>" 
                                <?= (isset($edit_data['Toplayici']) && $edit_data['Toplayici'] == $kisi['Ad_Soyad']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($kisi['Ad_Soyad']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <input type="hidden" name="toplayici" id="toplayici_select" value="<?= htmlspecialchars($kullanici_ad_soyad) ?>">
                    <input type="text" class="t-input input-locked" value="<?= htmlspecialchars($kullanici_ad_soyad) ?>" readonly>
                <?php endif; ?>
            </div>

            <div class="flex-col">
                <label class="t-label">Toplayıcı No *</label>
                <input type="text" name="toplayici_no" id="toplayici_no_input" class="t-input input-locked" placeholder="Otomatik..." readonly required value="<?= htmlspecialchars($edit_data['Toplayici_No'] ?? '') ?>">
            </div>

            <div class="flex-col">
                <label class="t-label">Takson Türü *</label>
                <select name="takson_id" id="takson_select" class="t-select" required>
                    <option value="">Botanik Tür Seçin...</option>
                    <?php foreach($taksonlar as $t): ?>
                        <option value="<?= $t['Takson_ID'] ?>" <?= (isset($edit_data['Takson_ID']) && $edit_data['Takson_ID'] == $t['Takson_ID']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($t['Takson_Adi']) ?> (<?= htmlspecialchars($t['Bitki_Adi']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-section">
            <div class="flex-row">
                <div class="flex-col" style="flex: 0 0 250px;">
                    <label class="t-label">Toplama Tarihi *</label>
                    <input type="date" name="toplama_tarihi" class="custom-area" 
                           value="<?= $edit_data['Toplama_Tarihi'] ?? $bugun ?>" 
                           min="2000-01-01" max="2030-12-31" 
                           onkeypress="return false;" onpaste="return false;" 
                           style="cursor: pointer;" required>
                </div>

                <div class="flex-col">
                    <label class="t-label">Yardımcı Toplayıcı</label>
                    <select name="yardimci_toplayici" id="yardimci_toplayici_select" class="t-select">
                        <option value="">Yoksa Boş Bırakın</option>
                        <?php foreach($personeller as $kisi): ?>
                            <option value="<?= htmlspecialchars($kisi['Ad_Soyad']) ?>" <?= (isset($edit_data['Yardimci_Toplayici']) && $edit_data['Yardimci_Toplayici'] == $kisi['Ad_Soyad']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($kisi['Ad_Soyad']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="flex-row" id="konum_alani">
                <div class="flex-col">
                    <label class="t-label">Ülke *</label>
                    <select name="ulke" id="ulke" class="t-select" required onchange="ulkeKontrol()">
                        <option value="Türkiye" <?= (isset($edit_data['Ulke']) && $edit_data['Ulke'] == 'Türkiye' || !isset($edit_data['Ulke'])) ? 'selected' : '' ?>>Türkiye</option>
                        <option value="Yurtdışı" <?= (isset($edit_data['Ulke']) && $edit_data['Ulke'] == 'Yurtdışı') ? 'selected' : '' ?>>Diğer (Yurtdışı)</option>
                    </select>
                </div>
                <div class="flex-col" id="sehir_sarmalayici">
                    <label class="t-label">Şehir *</label>
                    <select name="sehir" id="sehir" class="t-select" required onchange="ilceDoldur()">
                        <option value="">İl Yükleniyor...</option>
                    </select>
                </div>
                <div class="flex-col" id="ilce_sarmalayici">
                    <label class="t-label">İlçe *</label>
                    <select name="ilce" id="ilce" class="t-select" required>
                        <option value="">Önce İl Seçin</option>
                    </select>
                </div>
            </div>

            <div class="flex-row">
                <div class="flex-col-full">
                    <label class="t-label">Lokasyon Tarifi *</label>
                    <textarea name="lokasyon" class="t-textarea" required placeholder="Açık adres veya tarif girin..."><?= htmlspecialchars($edit_data['Lokasyon'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="flex-row">
                <div class="flex-col"><label class="t-label">Enlem (Opsiyonel)</label><input type="text" name="enlem" class="t-input" placeholder="" value="<?= htmlspecialchars($edit_data['Enlem'] ?? '') ?>"></div>
                <div class="flex-col"><label class="t-label">Boylam (Opsiyonel)</label><input type="text" name="boylam" class="t-input" placeholder="" value="<?= htmlspecialchars($edit_data['Boylam'] ?? '') ?>"></div>
                <div class="flex-col">
                    <label class="t-label">Habitat (Fiziki Ortam) *</label>
                    <select name="habitat" class="t-select" required>
                        <option value="">Seçin</option>
                        <option <?= (isset($edit_data['Habitat']) && $edit_data['Habitat'] == 'Orman İçi / Altı') ? 'selected' : '' ?>>Orman İçi / Altı</option>
                        <option <?= (isset($edit_data['Habitat']) && $edit_data['Habitat'] == 'Dere / Su Kenarı') ? 'selected' : '' ?>>Dere / Su Kenarı</option>
                        <option <?= (isset($edit_data['Habitat']) && $edit_data['Habitat'] == 'Sarp Kayalık / Uçurum') ? 'selected' : '' ?>>Sarp Kayalık / Uçurum</option>
                        <option <?= (isset($edit_data['Habitat']) && $edit_data['Habitat'] == 'Çayır / Mera / Açıklık') ? 'selected' : '' ?>>Çayır / Mera / Açıklık</option>
                        <option <?= (isset($edit_data['Habitat']) && $edit_data['Habitat'] == 'Taşlık / Çakıllık') ? 'selected' : '' ?>>Taşlık / Çakıllık</option>
                        <option <?= (isset($edit_data['Habitat']) && $edit_data['Habitat'] == 'Yol Kenarı / Tahrip Edilmiş') ? 'selected' : '' ?>>Yol Kenarı / Tahrip Edilmiş</option>
                        <option <?= (isset($edit_data['Habitat']) && $edit_data['Habitat'] == 'Bataklık / Turbalık') ? 'selected' : '' ?>>Bataklık / Turbalık</option>
                        <option <?= (isset($edit_data['Habitat']) && $edit_data['Habitat'] == 'Kumlu / Sahil') ? 'selected' : '' ?>>Kumlu / Sahil</option>
                        <option <?= (isset($edit_data['Habitat']) && $edit_data['Habitat'] == 'Diğer') ? 'selected' : '' ?>>Diğer</option>
                    </select>
                </div>
            </div>

            <div class="flex-row">
                <div class="flex-col">
                    <label class="t-label">Vejetasyon (Bitki Örtüsü) *</label>
                    <select name="vejetasyon" class="t-select" required>
                        <option value="">Seçin</option>
                        <option <?= (isset($edit_data['Vejetasyon']) && $edit_data['Vejetasyon'] == 'Orman (İğne/Yapraklı)') ? 'selected' : '' ?>>Orman (İğne/Yapraklı)</option>
                        <option <?= (isset($edit_data['Vejetasyon']) && $edit_data['Vejetasyon'] == 'Maki / Çalılık') ? 'selected' : '' ?>>Maki / Çalılık</option>
                        <option <?= (isset($edit_data['Vejetasyon']) && $edit_data['Vejetasyon'] == 'Bozkır (Step)') ? 'selected' : '' ?>>Bozkır (Step)</option>
                        <option <?= (isset($edit_data['Vejetasyon']) && $edit_data['Vejetasyon'] == 'Alpin Çayır (Yüksek Yayla)') ? 'selected' : '' ?>>Alpin Çayır (Yüksek Yayla)</option>
                        <option <?= (isset($edit_data['Vejetasyon']) && $edit_data['Vejetasyon'] == 'Sulak Alan (Sazlık vb.)') ? 'selected' : '' ?>>Sulak Alan (Sazlık vb.)</option>
                        <option <?= (isset($edit_data['Vejetasyon']) && $edit_data['Vejetasyon'] == 'Kültür / Tarım Alanı') ? 'selected' : '' ?>>Kültür / Tarım Alanı</option>
                        <option <?= (isset($edit_data['Vejetasyon']) && $edit_data['Vejetasyon'] == 'Diğer') ? 'selected' : '' ?>>Diğer</option>
                    </select>
                </div>
                <div class="flex-col">
                    <label class="t-label">Rakım (m) *</label>
                    <input type="number" name="yukseklik" class="t-input" placeholder="Örn: 1200" required min="0" value="<?= htmlspecialchars($edit_data['Yukseklik'] ?? '') ?>">
                </div>
                <div class="flex-col">
                    <label class="t-label">Bakı *</label>
                    <select name="baki" class="t-select" required>
                        <option value="">Seçin</option>
                        <option <?= (isset($edit_data['Baki']) && $edit_data['Baki'] == 'Kuzey') ? 'selected' : '' ?>>Kuzey</option>
                        <option <?= (isset($edit_data['Baki']) && $edit_data['Baki'] == 'Güney') ? 'selected' : '' ?>>Güney</option>
                        <option <?= (isset($edit_data['Baki']) && $edit_data['Baki'] == 'Doğu') ? 'selected' : '' ?>>Doğu</option>
                        <option <?= (isset($edit_data['Baki']) && $edit_data['Baki'] == 'Batı') ? 'selected' : '' ?>>Batı</option>
                    </select>
                </div>
            </div>

            <div class="flex-row">
                <div class="flex-col-full">
                    <label class="t-label">Notlar (Opsiyonel)</label>
                    <textarea name="toplayici_notu" class="t-textarea" placeholder="Belirtmek istediğiniz diğer notlar..."><?= htmlspecialchars($edit_data['Toplayici_Notu'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
    // 1. GLOBAL DEĞİŞKENLER
    let turkiyeVerisi = null;
    var kayitliSehir = "<?= htmlspecialchars($edit_data['Sehir'] ?? 'Artvin') ?>"; 
    var kayitliIlce = "<?= htmlspecialchars($edit_data['Ilce'] ?? '') ?>";
    let Toast;

    $(document).ready(function () {
        // --- TOAST YAPILANDIRMASI (Genel Ayarlar) ---
        Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            showCloseButton: true,
            timer: 2000,
            background: '#ffffff'
        });

        // Sayfa açılışında başarı mesajı varsa göster
        if(localStorage.getItem('goster_toast') === '1') {
            showSuccessToast('Arazi defteri kaydı başarıyla oluşturuldu.');
            localStorage.removeItem('goster_toast');
        }

        $('.js-ara').select2();
        $('#takson_select').select2({ placeholder: 'Tür seçin...', allowClear: true, width: '100%' });
        
        ulkeKontrol();
        <?php if(!$isAdmin && !$is_edit): ?> otomatikNoGetir(); <?php endif; ?>
    });

    // 2. YENİ BAŞARI FONKSİYONU (YEŞİL)
    function showSuccessToast(mesaj) {
        Toast.fire({
            icon: 'success',
            title: 'Başarılı!',
            text: mesaj,
            iconColor: '#10b981' // Yeşil
        });
    }

    // 3. YENİ HATA FONKSİYONU (KIRMIZI)
    function showErrorToast(mesaj) {
        Toast.fire({
            icon: 'error',
            title: 'Eksik Bilgi!',
            text: mesaj,
            iconColor: '#ef4444' // Kırmızı
        });
    }

    // 4. FORM KONTROLÜ
    function formKontrol() {
        const form = document.getElementById("araziForm");
        const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
        let hataVar = false;

        form.querySelectorAll('.input-error-animate').forEach(el => el.classList.remove('input-error-animate'));
        form.querySelectorAll('.select2-container').forEach(el => el.classList.remove('input-error-animate'));

        for (let input of inputs) {
            if (!input.value.trim()) {
                let label = form.querySelector(`label[for="${input.name}"]`) || input.previousElementSibling;
                let fieldName = label ? label.innerText.replace('*', '').trim() : "İlgili alan";

                showErrorToast(`${fieldName} alanını doldurunuz.`);
                
                let hedef = $(input).hasClass('select2-hidden-accessible') ? $(input).next('.select2-container') : $(input);
                hedef.addClass('input-error-animate');

                setTimeout(() => { hedef.removeClass('input-error-animate'); }, 2000);

                input.focus();
                hataVar = true;
                break;
            }
        }

        if (hataVar) return false;

        var toplayici = document.getElementById("toplayici_select")?.value;
        var yardimci = document.getElementById("yardimci_toplayici_select")?.value;
        if (toplayici !== "" && toplayici === yardimci) {
            showErrorToast('Toplayıcı ile Yardımcı aynı olamaz!');
            return false;
        }
        form.submit();
    }

    // 4. SİLME ONAYI (Premium Popup)
    function gorevSilConfirm(id, event) {
        event.stopPropagation();
        event.preventDefault();
        Swal.fire({
            title: 'Kayıt Silinecek',
            text: "Bu kaydı silmek istediğinize emin misiniz?",
            icon: 'warning',
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#94a3b8',
            showCancelButton: true,
            confirmButtonText: 'Evet, Sil',
            cancelButtonText: 'İptal'
        }).then((result) => {
            if (result.isConfirmed) { window.location.href = 'panel.php?sayfa=arazi_defteri&sil_id=' + id; }
        });
    }

    // --- YARDIMCI FONKSİYONLAR ---
    function otomatikNoGetir() {
        var toplayici_adi = document.getElementById("toplayici_select").value;
        var input = document.getElementById("toplayici_no_input");
        if (toplayici_adi !== "") {
            input.value = "Hesaplanıyor...";
            fetch('ajax_toplayici.php?toplayici_adi=' + encodeURIComponent(toplayici_adi))
                .then(response => response.text())
                .then(data => { input.value = data.trim(); });
        }
    }
    
    function downloadSinglePDF() {
        const element = document.getElementById('pdfFormArea');
        const title = document.getElementById('pdfTitleSingle');
        const buttonsAndTitles = document.querySelectorAll('.no-print');
        
        title.style.display = 'flex'; 
        element.classList.add('pdf-mode');
        buttonsAndTitles.forEach(el => el.style.display = 'none');

        const opt = {
            margin: 0.2, 
            filename: 'Bitki_Formu_No_<?= $edit_data["Kayit_ID"] ?? "Yeni" ?>.pdf',
            image: { type: 'jpeg', quality: 1 },
            html2canvas: { scale: 2, useCORS: true }, 
            jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' } 
        };
        
        html2pdf().set(opt).from(element).save().then(() => {
            title.style.display = 'none';
            element.classList.remove('pdf-mode');
            buttonsAndTitles.forEach(el => el.style.display = ''); 
        });
    }

    $(document).ready(function() {
        $('#takson_select').select2({
            placeholder: 'Tür veya bitki adı yazarak arayın...',
            allowClear: true,
            width: '100%', 
            language: {
                noResults: function() { return "Kayıt bulunamadı"; }
            }
        });
    });

    function ulkeKontrol() {
        const ulke = document.getElementById('ulke').value;
        const sehirSarmalayici = document.getElementById('sehir_sarmalayici');
        const ilceSarmalayici = document.getElementById('ilce_sarmalayici');

        if(ulke === 'Türkiye') {
            sehirSarmalayici.innerHTML = `<label class="t-label">Şehir *</label><select name="sehir" id="sehir" class="t-select" required onchange="ilceDoldur()"><option value="">İl Yükleniyor...</option></select>`;
            ilceSarmalayici.innerHTML = `<label class="t-label">İlçe *</label><select name="ilce" id="ilce" class="t-select" required><option value="">Önce İl Seçin</option></select>`;
            
            if(!turkiyeVerisi) {
                // 1. ADIM: GitHub yerine direkt güncel ve sertifikası temiz bir kaynak
                fetch('https://raw.githubusercontent.com/denizturkmen/turkiye-il-ilce/main/il-ilce.json', { mode: 'cors' })
                    .then(res => {
                        if(!res.ok) throw new Error("API Erişimi Başarısız");
                        return res.json();
                    })
                    .then(data => { 
                        // Veri yapısı farklıysa (il/ilçe/vb) dönüştürücü burada devreye girer
                        veriyiFormatlaVeDoldur(data); 
                    })
                    .catch(err1 => {
                        console.warn("API Erişimi Sağlanamadı, yedekleme başlatılıyor...", err1);

                        // 2. ADIM: Yedekleme - TurkiyeAPI (https destekli)
                        fetch('https://turkiyeapi.dev/api/v1/provinces', { mode: 'cors' })
                            .then(res => res.json())
                            .then(data => { veriyiFormatlaVeDoldur(data.data); })
                            .catch(err2 => {
                                console.error("Tüm API kanalları kapalı:", err2);
                                // HATA DURUMUNDA GÜVENLİK: Kullanıcıya elle girme şansı ver
                                sehirSarmalayici.innerHTML = `<label class="t-label">Şehir *</label><input type="text" name="sehir" class="t-input" value="${kayitliSehir}" required>`;
                                ilceSarmalayici.innerHTML = `<label class="t-label">İlçe *</label><input type="text" name="ilce" class="t-input" value="${kayitliIlce}" required>`;
                            });
                    });
            } else {
                sehirDoldur(); 
            }
        } else {
            sehirSarmalayici.innerHTML = `<label class="t-label">Şehir (Yurtdışı) *</label><input type="text" name="sehir" class="t-input" placeholder="Şehir yazın..." value="${kayitliSehir !== 'Artvin' ? kayitliSehir : ''}" required>`;
            ilceSarmalayici.innerHTML = `<label class="t-label">Bölge/Eyalet *</label><input type="text" name="ilce" class="t-input" placeholder="Bölge yazın..." value="${kayitliIlce}" required>`;
        }
    }

    // YENİ: Hangi API'den gelirse gelsin, veriyi bizim sisteme uyduran "Akıllı Dönüştürücü"
    function veriyiFormatlaVeDoldur(hamVeri) {
        turkiyeVerisi = hamVeri.map(il => ({
            name: il.name || il.il_adi || il.il_isim,
            districts: (il.districts || il.ilceler || []).map(ilce => ({ 
                name: ilce.name || ilce.ilce_adi || ilce.ilce_isim 
            }))
        })); 
        sehirDoldur();
    }

    function sehirDoldur() {
        const sehirSelect = document.getElementById('sehir');
        if(!sehirSelect) return;
        sehirSelect.innerHTML = '<option value="">İl Seçin</option>';
        turkiyeVerisi.sort((a, b) => a.name.localeCompare(b.name, 'tr-TR'));
        turkiyeVerisi.forEach(il => {
            const option = document.createElement('option');
            option.value = il.name;
            option.textContent = il.name;
            sehirSelect.appendChild(option);
        });
        sehirSelect.value = kayitliSehir;
        ilceDoldur();
    }

    function ilceDoldur() {
        const sehirSelect = document.getElementById('sehir');
        const ilceSelect = document.getElementById('ilce');
        if(!sehirSelect || !ilceSelect) return;
        const secilenIlAdi = sehirSelect.value;
        ilceSelect.innerHTML = '<option value="">İlçe Seçin</option>';
        if(!secilenIlAdi || !turkiyeVerisi) return;
        const secilenIl = turkiyeVerisi.find(il => il.name === secilenIlAdi);
        if(secilenIl && secilenIl.districts) {
            const ilceler = secilenIl.districts.sort((a, b) => a.name.localeCompare(b.name, 'tr-TR'));
            ilceler.forEach(ilce => {
                const option = document.createElement('option');
                option.value = ilce.name;
                option.textContent = ilce.name;
                ilceSelect.appendChild(option);
            });
            if(kayitliIlce && ilceSelect.querySelector(`option[value="${kayitliIlce}"]`)) {
                ilceSelect.value = kayitliIlce;
            }
        }
    }

    function formDogrulaBasla(event, form) {
        event.preventDefault();
        Swal.fire({
            title: 'İşlem Onayı', text: "Devam etmek istiyor musunuz?", icon: 'info',
            showCancelButton: true, confirmButtonColor: '#3b82f6', cancelButtonColor: '#94a3b8'
        }).then((result) => { if (result.isConfirmed) { form.submit(); } });
    }
</script>