<?php
/**
 * GreenLog - Gelişmiş Bitki Kayıt Paneli (v3.2 - Stil ve Hizalama Düzeltilmiş)
 */

// 1. Parametre Yakalama
$Sera_ID = 0;
if (isset($_GET['sera_id'])) { $Sera_ID = (int)$_GET['sera_id']; }
elseif (isset($_GET['Sera_ID'])) { $Sera_ID = (int)$_GET['Sera_ID']; }
elseif (isset($_GET['id'])) { $Sera_ID = (int)$_GET['id']; }
elseif (isset($_POST['Sera_ID'])) { $Sera_ID = (int)$_POST['Sera_ID']; }

// Geri Dönüş Linki Belirleme (Akıllı Hafıza Sistemi)
if (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'yeni_bitki') === false) {
    $_SESSION['orijinal_geri_link'] = $_SERVER['HTTP_REFERER'];
}
$geri_link = $_SESSION['orijinal_geri_link'] ?? "panel.php?sayfa=bitkiler";

// Seçili Sera Bilgilerini Getir
$secili_sera = null;
if ($Sera_ID > 0) {
    $s_sorgu = $pdo->prepare("SELECT * FROM sera WHERE Sera_ID = ?");
    $s_sorgu->execute([$Sera_ID]);
    $secili_sera = $s_sorgu->fetch();
}

// 2. İşlemler
$mesaj = ""; $hata = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $pdo->exec("SET NAMES 'utf8'");
    
    // DURUM A: Hızlı Tür Ekleme
    if (isset($_POST['hizli_takson_ekle'])) {
        try {
            $pdo->beginTransaction();
            $t_sorgu = $pdo->prepare("INSERT INTO takson (Bitki_Adi, Takson_Adi, Tur_Grubu) VALUES (?, ?, ?)");
            $t_sorgu->execute([$_POST['new_bitki_adi'], $_POST['new_takson_adi'], $_POST['new_tur_grubu']]);
            
            $log_detay = "Sisteme yeni tür eklendi: " . $_POST['new_bitki_adi'];
            $log_sorgu = $pdo->prepare("INSERT INTO sistem_loglari (Personel_ID, Islem_Detay, Islem_Tarihi, Tablo_Adi) VALUES (?, ?, ?, ?)");
            $log_sorgu->execute([$_SESSION['Personel_ID'], $log_detay, date('Y-m-d H:i:s'), 'takson']);

            $pdo->commit();
            $mesaj = "Yeni tür başarıyla sisteme eklendi!";
        } catch (PDOException $e) { $pdo->rollBack(); $hata = "Tür eklenemedi: " . $e->getMessage(); }
    }

    // DURUM B: Ana Bitki Kaydı
    if (isset($_POST['bitki_kaydet'])) {
        try {
            $pdo->beginTransaction();

            $t_bilgi = $pdo->prepare("SELECT Takson_Adi FROM takson WHERE Takson_ID = ?");
            $t_bilgi->execute([$_POST['Takson_ID']]);
            $bitki_adi = $t_bilgi->fetchColumn() ?: "Bilinmeyen Bitki";

            $sorgu = $pdo->prepare("INSERT INTO bitki (Takson_ID, Lokasyon_ID, Sera_ID, Sorumlu_Pers_ID, Eken_Pers_ID, Ekim_Tarihi, Aktiflik, enlem, boylam) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            // Boş bırakılırsa veritabanına NULL (Boş) olarak kaydet
            $enlem_val = !empty(trim($_POST['enlem'])) ? trim($_POST['enlem']) : NULL;
            $boylam_val = !empty(trim($_POST['boylam'])) ? trim($_POST['boylam']) : NULL;

            $sorgu->execute([
                $_POST['Takson_ID'], 
                $_POST['Lokasyon_ID'], 
                $Sera_ID, 
                $_POST['Sorumlu_Pers_ID'], 
                $_POST['Eken_Pers_ID'], 
                date('Y-m-d H:i:s'), 
                1,
                $enlem_val,
                $boylam_val
            ]);

            $log_detay = "Yeni Bitki Eklendi: " . $bitki_adi;
            $log_sorgu = $pdo->prepare("INSERT INTO sistem_loglari (Personel_ID, Islem_Detay, Islem_Tarihi, Tablo_Adi) VALUES (?, ?, ?, ?)");
            $log_sorgu->execute([$_SESSION['Personel_ID'], $log_detay, date('Y-m-d H:i:s'), 'bitki']);

            $pdo->commit();
            
            // YENİ: Akıllı Yönlendirme (Kullanıcı nereden geldiyse oraya durum=yeni_bitki_ok sinyali gönderir)
            $yonlendir = $geri_link;
            $yonlendir .= (strpos($yonlendir, '?') !== false) ? '&durum=yeni_bitki_ok' : '?durum=yeni_bitki_ok';
            echo "<script>window.location.href='$yonlendir';</script>";
            exit;
        } catch (PDOException $e) { $pdo->rollBack(); $hata = "Kayıt hatası: " . $e->getMessage(); }
    }
}

// 3. Veri Çekme
$taksonlar = $pdo->query("SELECT * FROM takson ORDER BY Bitki_Adi ASC")->fetchAll();
$seralar = $pdo->query("SELECT * FROM sera WHERE Aktif_Mi = 1")->fetchAll(); 
$lokasyonlar = ($Sera_ID > 0) ? $pdo->query("SELECT * FROM lokasyon WHERE Sera_ID = $Sera_ID")->fetchAll() : [];
$personeller = $pdo->query("SELECT Personel_ID, Ad_Soyad FROM personel WHERE Rol = 'Personel' ORDER BY Ad_Soyad ASC")->fetchAll();
?>

<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />

<link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    :root {
        --gl-green: #10b981;
        --gl-green-dark: #059669;
        --gl-green-light: #ecfdf5;
        --gl-bg: #f8fafc;
        --gl-text: #0f172a;
        --gl-text-muted: #64748b;
        --gl-border: rgba(226, 232, 240, 0.8);
        --font-main: 'Plus Jakarta Sans', sans-serif;
    }

    .page-wrapper { padding: 40px 20px; font-family: var(--font-main); color: var(--gl-text); }
    .main-card { background: white; max-width: 650px; margin: auto; padding: 45px; border-radius: 32px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.05); border: 1px solid var(--gl-border); animation: fadeIn 0.6s ease; }
    
    /* Etiket Yapıları ve Flex Düzeni */
    .form-row-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
    .form-label { display: block; font-weight: 700; margin-bottom: 0; color: var(--gl-text-muted); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; }
    
    /* Girdi Elementleri (Tüm input ve selectler aynı görünecek) */
    .input-style { width: 100%; height: 56px; border-radius: 16px; border: 2px solid #f1f5f9; background-color: #f8fafc; padding: 0 20px; font-size: 15px; margin-bottom: 24px; box-sizing: border-box; transition: all 0.3s; color: var(--gl-text); font-family: inherit; font-weight: 500; }
    .input-style:focus { border-color: var(--gl-green); outline: none; background: #fff; box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.15); }
    
    /* Tom Select Özelleştirmesi (Açılır Kutular) */
    .ts-wrapper.input-style { padding: 0 !important; height: auto !important; min-height: 56px; margin-bottom: 24px; border-radius: 16px !important; border: 2px solid #f1f5f9 !important; background-color: #f8fafc !important; transition: all 0.3s; }
    .ts-wrapper .ts-control { border: none !important; background: transparent !important; padding: 14px 20px !important; font-size: 15px !important; font-weight: 500 !important; font-family: var(--font-main) !important; color: var(--gl-text) !important; cursor: pointer; }
    .ts-wrapper.focus .ts-control { background: #fff !important; border-radius: 16px !important; }
    .ts-wrapper.focus { border-color: var(--gl-green) !important; box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.15) !important; background: #fff !important; }
    .ts-dropdown { border-radius: 16px !important; border: 1px solid #e2e8f0 !important; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1) !important; font-family: var(--font-main) !important; padding: 8px !important; z-index: 1000; }
    .ts-dropdown .option { padding: 12px 15px !important; border-radius: 10px; margin-bottom: 2px; transition: 0.1s; color: var(--gl-text); font-weight: 500; cursor: pointer; }
    .ts-dropdown .option:hover, .ts-dropdown .active { background-color: var(--gl-green) !important; color: white !important; font-weight: 600; }
    
    /* Buton Yapıları */
    .action-buttons { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 10px; }
    .btn-main { height: 60px; background: var(--gl-green); color: white; border: none; border-radius: 18px; font-weight: 800; font-size: 16px; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; justify-content: center; gap: 12px; box-shadow: 0 10px 20px -5px rgba(16, 185, 129, 0.3); text-decoration: none; }
    .btn-main:hover { background: var(--gl-green-dark); transform: translateY(-2px); }
    .btn-secondary { height: 60px; background: #f1f5f9; color: #475569; border: none; border-radius: 18px; font-weight: 700; font-size: 16px; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; justify-content: center; gap: 12px; text-decoration: none; box-sizing: border-box; }
    .btn-secondary:hover { background: #e2e8f0; color: #1e293b; transform: translateY(-2px); }

    /* Yeni Tür Badge Buton */
    .badge-add { background: var(--gl-green-light); padding: 8px 14px; border-radius: 12px; font-size: 11px; cursor: pointer; font-weight: 800; color: var(--gl-green-dark); border: 1px solid rgba(16, 185, 129, 0.2); transition: all 0.2s; display: inline-flex; align-items: center; gap: 5px; }
    .badge-add:hover { background: var(--gl-green); color: white; }
    
    .alert-box { padding: 18px; border-radius: 16px; margin-bottom: 25px; display: flex; align-items: center; gap: 12px; font-size: 14px; font-weight: 600; }
    
    .sera-info-card { 
        background: #f1f5f9; border-radius: 20px; padding: 20px; margin-bottom: 25px;
        border-left: 5px solid var(--gl-green); display: flex; flex-direction: column; gap: 5px;
    }
    .sera-info-title { font-weight: 800; color: #1e293b; font-size: 14px; }
    .sera-info-coords { font-family: monospace; color: #64748b; font-size: 13px; }
    .coord-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }

    /* Modal (Pop-up) Tasarımı */
    .gl-modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(8px); display: flex; align-items: center; justify-content: center; z-index: 9999; opacity: 0; visibility: hidden; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
    .gl-modal-overlay.active { opacity: 1; visibility: visible; }
    .gl-modal-card { background: white; width: 100%; max-width: 500px; padding: 35px; border-radius: 28px; box-shadow: 0 30px 60px -15px rgba(0, 0, 0, 0.2); transform: scale(0.9); transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); border: 1px solid rgba(255,255,255,0.8); position: relative; margin: 0 20px; }
    .gl-modal-overlay.active .gl-modal-card { transform: scale(1); }
    .gl-modal-close { position: absolute; top: 25px; right: 25px; width: 36px; height: 36px; background: #f1f5f9; color: #64748b; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; }
    .gl-modal-close:hover { background: #e2e8f0; color: #0f172a; }
    
    /* SweetAlert2 Toast'larını ve Modallarını Her Şeyin En Üstüne Alır */
    .swal2-container { z-index: 99999 !important; }
</style>

<div class="page-wrapper">
    <div class="main-card">
        
        <div style="text-align: center; margin-bottom: 40px;">
            <div style="width: 75px; height: 75px; background: var(--gl-green-light); color: var(--gl-green); border-radius: 24px; display: flex; align-items: center; justify-content: center; font-size: 32px; margin: 0 auto 20px; transform: rotate(-5deg);"><i class="fas fa-seedling"></i></div>
            <h2 style="margin:0; font-size: 28px; font-weight: 800; letter-spacing: -1px;">Yeni Üretim Kaydı</h2>
            <p style="color: var(--gl-text-muted); font-size: 15px; margin-top: 5px;">Bitki konumlandırma ve üretim girişi.</p>
        </div>

        <form method="POST" onsubmit="return formDogrulaBitki(event, this)">
            <div class="form-row-header">
                <label class="form-label">1. Üretim Serası</label>
            </div>
            <select name="Sera_ID" class="input-style js-search-select" onchange="window.location.href='panel.php?sayfa=yeni_bitki&sera_id=' + this.value">
                <option value="">Sera seçin...</option>
                <?php foreach($seralar as $s): ?>
                    <option value="<?php echo $s['Sera_ID']; ?>" <?php echo ($Sera_ID == $s['Sera_ID']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($s['Sera_Adi']); ?></option>
                <?php endforeach; ?>
            </select>

            <?php if ($Sera_ID > 0 && $secili_sera): ?>
                
                <div class="form-row-header">
                    <label class="form-label">2. Bitki Türü</label>
                    <span class="badge-add" onclick="openGlModal()"><i class="fas fa-plus"></i> YENİ TÜR</span>
                </div>
                <select name="Takson_ID" class="input-style js-search-select">
                    <option value="">Bir bitki türü yazın veya seçin...</option>
                    <?php foreach($taksonlar as $t): ?>
                        <option value="<?php echo $t['Takson_ID']; ?>"><?php echo htmlspecialchars($t['Bitki_Adi']); ?> (<?php echo htmlspecialchars($t['Takson_Adi']); ?>)</option>
                    <?php endforeach; ?>
                </select>

                <div class="form-row-header">
                    <label class="form-label">3. Dikim Alanı (Lokasyon)</label>
                </div>
                <select name="Lokasyon_ID" class="input-style js-search-select">
                    <option value="">Seçiniz...</option>
                    <?php foreach($lokasyonlar as $l): ?>
                        <option value="<?php echo $l['Lokasyon_ID']; ?>"><?php echo htmlspecialchars($l['Parsel_Bilgisi']); ?> — Sıra: <?php echo $l['Sira_No']; ?></option>
                    <?php endforeach; ?>
                </select>

                <div class="form-row-header">
                    <label class="form-label">4. Sorumlu Personel</label>
                </div>
                <select name="Sorumlu_Pers_ID" class="input-style js-search-select">
                    <option value="">Sorumlu Personeli Seçiniz...</option>
                    <?php foreach($personeller as $p): ?>
                        <option value="<?php echo $p['Personel_ID']; ?>"><?php echo htmlspecialchars($p['Ad_Soyad']); ?></option>
                    <?php endforeach; ?>
                </select>

                <div class="form-row-header">
                    <label class="form-label">5. Eken Personel</label>
                </div>
                <select name="Eken_Pers_ID" class="input-style js-search-select">
                    <option value="">Eken Personeli Seçiniz...</option>
                    <?php foreach($personeller as $p): ?>
                        <option value="<?php echo $p['Personel_ID']; ?>"><?php echo htmlspecialchars($p['Ad_Soyad']); ?></option>
                    <?php endforeach; ?>
                </select>

                <div class="form-row-header">
                    <label class="form-label">6. Bitki Koordinatları</label>
                </div>
                <div class="coord-grid">
                    <div>
                        <label class="form-label" style="font-size:0.65rem; margin-bottom:5px;">Enlem</label>
                        <input type="text" name="enlem" class="input-style" placeholder="Örn: 41.123" value="">
                    </div>
                    <div>
                        <label class="form-label" style="font-size:0.65rem; margin-bottom:5px;">Boylam</label>
                        <input type="text" name="boylam" class="input-style" placeholder="Örn: 42.456" value="">
                    </div>
                </div>

                <div class="action-buttons">
                    <a href="<?php echo $geri_link; ?>" class="btn-secondary"><i class="fas fa-arrow-left"></i> GERİ DÖN</a>
                    <button type="submit" name="bitki_kaydet" class="btn-main"><i class="fas fa-rocket"></i> KAYDI TAMAMLA</button>
                </div>

            <?php else: ?>
                <div style="text-align:center; padding:40px; border:2px dashed #e2e8f0; border-radius:24px; background:#fafafa; margin-bottom: 25px;">
                    <p style="color:var(--gl-text-muted); font-weight:600;">Lütfen işlem yapmak için yukarıdan bir sera seçin.</p>
                </div>
                
                <div>
                    <a href="<?php echo $geri_link; ?>" class="btn-secondary" style="width: 100%;"><i class="fas fa-arrow-left"></i> GERİ DÖN</a>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<div id="glTaxonModal" class="gl-modal-overlay" onclick="closeGlModalOnClickOutside(event)">
    <div class="gl-modal-card">
        <div class="gl-modal-close" onclick="closeGlModal()"><i class="fas fa-times"></i></div>
        
        <div style="margin-bottom:25px;">
            <h3 style="margin:0 0 5px 0; color:var(--gl-text); font-weight:800; font-size:22px; letter-spacing: -0.5px;">Yeni Tür Tanımla</h3>
            <p style="margin:0; color:var(--gl-text-muted); font-size:14px;">Sisteme kaydedilecek yeni takson bilgilerini yazın.</p>
        </div>
        
        <form method="POST" onsubmit="return formDogrulaTakson(event, this)">
            <input type="hidden" name="Sera_ID" value="<?php echo $Sera_ID; ?>">
            
            <div class="form-row-header" style="margin-bottom:8px;">
                <label class="form-label">Bitki Yaygın Adı</label>
            </div>
            <input type="text" name="new_bitki_adi" class="input-style" placeholder="Örn: Domates, Lavanta">
            
            <div class="form-row-header" style="margin-bottom:8px;">
                <label class="form-label">Bilimsel Ad (Takson)</label>
            </div>
            <input type="text" name="new_takson_adi" class="input-style" placeholder="Örn: Solanum lycopersicum">
            
            <div class="form-row-header" style="margin-bottom:8px;">
                <label class="form-label">Tür Grubu</label>
            </div>
            <select name="new_tur_grubu" class="input-style js-search-select">
                <option value="Süs Bitkisi">Süs Bitkisi</option>
                <option value="Meyve/Sebze" selected>Meyve/Sebze</option>
                <option value="Tıbbi/Aromatik">Tıbbi/Aromatik</option>
                <option value="Diğer">Diğer</option>
            </select>
            
            <button type="submit" name="hizli_takson_ekle" class="btn-main" style="width:100%; height:54px; font-size:15px; margin-top:10px;">TÜRÜ EKLE</button>
        </form>
    </div>
</div>

<script>
// 1. TOM SELECT BAŞLATMA (Sayfa yüklenince çalışır)
document.addEventListener("DOMContentLoaded", function() {
    document.querySelectorAll('.js-search-select').forEach(function(el) {
        new TomSelect(el, {
            create: false,
            sortField: {
                field: "text",
                direction: "asc"
            },
            placeholder: el.options[0].text,
            noResultsText: "Sonuç bulunamadı"
        });
    });
});

// 2. MODAL (POP-UP) KONTROLLERİ
function openGlModal() {
    const modal = document.getElementById("glTaxonModal");
    modal.classList.add("active");
    document.body.style.overflow = "hidden";
}

function closeGlModal() {
    const modal = document.getElementById("glTaxonModal");
    modal.classList.remove("active");
    document.body.style.overflow = "auto";
}

function closeGlModalOnClickOutside(event) {
    const modalOverlay = document.getElementById("glTaxonModal");
    if (event.target === modalOverlay) {
        closeGlModal();
    }
}

// 3. SWEETALERT2 VE FORM DOĞRULAMA (GLOBAL ALANDA OLMALI)
const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    showCloseButton: true,
    timer: 3000,
    timerProgressBar: true,
    background: '#ffffff',
    didOpen: (toast) => {
        toast.addEventListener('mouseenter', Swal.stopTimer)
        toast.addEventListener('mouseleave', Swal.resumeTimer)
    }
});

// PHP'den gelen Başarı/Hata mesajlarını sağ üstten zarifçe göster
<?php if(!empty($mesaj)): ?>
    Toast.fire({ icon: 'success', iconColor: '#10b981', title: '<?php echo htmlspecialchars($mesaj); ?>' });
<?php endif; ?>
<?php if(!empty($hata)): ?>
    Toast.fire({ icon: 'error', iconColor: '#ef4444', title: '<?php echo htmlspecialchars($hata); ?>' });
<?php endif; ?>

/// ANA BİTKİ FORMU KONTROLÜ (Dinamik ve Akıllı Odaklamalı)
function formDogrulaBitki(event, form) {
    event.preventDefault(); // Formun anında gitmesini durdur
    
    let sera = form.querySelector('[name="Sera_ID"]');
    let takson = form.querySelector('[name="Takson_ID"]');
    let lokasyon = form.querySelector('[name="Lokasyon_ID"]');
    let sorumlu = form.querySelector('[name="Sorumlu_Pers_ID"]');
    let eken = form.querySelector('[name="Eken_Pers_ID"]');
    let enlem = form.querySelector('[name="enlem"]');
    let boylam = form.querySelector('[name="boylam"]');

    // YENİ: Tom Select ve Normal Inputlar için Ortak Akıllı Odaklayıcı
    const akilliFocus = (element) => {
        if (element && element.tomselect) {
            element.tomselect.focus(); // Tom Select kutusuysa onu aç ve odaklan
        } else if (element) {
            element.focus(); // Normal metin kutusuysa (enlem, boylam) ona odaklan
        }
    };

    // Adım Adım Kontrol, Özel Mesaj ve Akıllı Odaklanma (Focus)
    if (!sera || !sera.value) {
        Toast.fire({ icon: 'error', title: 'Lütfen "Üretim Serası" seçin!' });
        akilliFocus(sera);
        return false;
    }
    if (takson && !takson.value) {
        Toast.fire({ icon: 'error', title: 'Lütfen "Bitki Türü" seçin!' });
        akilliFocus(takson);
        return false;
    }
    if (lokasyon && !lokasyon.value) {
        Toast.fire({ icon: 'error', title: 'Lütfen "Dikim Alanı (Lokasyon)" seçin!' });
        akilliFocus(lokasyon);
        return false;
    }
    if (sorumlu && !sorumlu.value) {
        Toast.fire({ icon: 'error', title: 'Lütfen "Sorumlu Personeli" seçin!' });
        akilliFocus(sorumlu);
        return false;
    }
    if (eken && !eken.value) {
        Toast.fire({ icon: 'error', title: 'Lütfen "Eken Personeli" seçin!' });
        akilliFocus(eken);
        return false;
    }

    // Her şey tamsa Onay Penceresi
    Swal.fire({
        title: 'Kaydı Tamamla',
        text: "Yeni bitki üretim kaydını sisteme eklemek istediğinize emin misiniz?",
        icon: 'question',
        iconColor: '#10b981',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: '<i class="fas fa-rocket"></i> Evet, Kaydet',
        cancelButtonText: 'İptal'
    }).then((result) => { 
        if (result.isConfirmed) { 
            let hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden'; hiddenInput.name = 'bitki_kaydet'; hiddenInput.value = '1';
            form.appendChild(hiddenInput);
            form.submit(); 
        } 
    });
}

// YENİ TÜR (MODAL) FORMU KONTROLÜ (Dinamik ve Odaklamalı)
function formDogrulaTakson(event, form) {
    event.preventDefault(); // Formun anında gitmesini durdur
    
    let ad = form.querySelector('[name="new_bitki_adi"]');
    let taksonAd = form.querySelector('[name="new_takson_adi"]');
    let turGrubu = form.querySelector('[name="new_tur_grubu"]');
    
    // Adım Adım Kontrol, Özel Mesaj ve Odaklanma (Focus)
    if (!ad.value.trim() || ad.value.length < 2) {
        Toast.fire({ icon: 'error', title: 'Lütfen geçerli bir "Bitki Yaygın Adı" yazın!' });
        ad.focus();
        return false;
    }
    if (!taksonAd.value.trim() || taksonAd.value.length < 2) {
        Toast.fire({ icon: 'error', title: 'Lütfen geçerli bir "Bilimsel Ad (Takson)" yazın!' });
        taksonAd.focus();
        return false;
    }
    if (!turGrubu.value) {
        Toast.fire({ icon: 'error', title: 'Lütfen "Tür Grubu" seçin!' });
        return false;
    }

    // Her şey tamsa Onay Penceresi
    Swal.fire({
        title: 'Yeni Tür Ekle',
        text: "Bu türü sistem kütüphanesine eklemek istediğinize emin misiniz?",
        icon: 'info',
        iconColor: '#3b82f6',
        showCancelButton: true,
        confirmButtonColor: '#3b82f6',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: '<i class="fas fa-plus"></i> Evet, Ekle',
        cancelButtonText: 'İptal'
    }).then((result) => { 
        if (result.isConfirmed) { 
            let hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden'; hiddenInput.name = 'hizli_takson_ekle'; hiddenInput.value = '1';
            form.appendChild(hiddenInput);
            form.submit(); 
        } 
    });
}
</script>