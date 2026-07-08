<?php
/**
 * GreenLog - Bitki Düzenleme Paneli (v3.3 - Stable Infinity Edition)
 */

// URL'den gelen Bitki ID'sini al ve tam sayıya zorla
$bitki_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($bitki_id <= 0) {
    echo '<div style="padding:100px 20px; text-align:center; color:#ef4444; font-family:\'Plus Jakarta Sans\',sans-serif; background:white; border-radius:24px; box-shadow:0 20px 40px rgba(0,0,0,0.03); max-width:500px; margin:50px auto;">
            <i class="fas fa-exclamation-triangle fa-4x" style="margin-bottom:20px; color:#f59e0b;"></i><br>
            <h2 style="margin:0; color:#0f172a; font-weight:800;">Geçersiz İşlem</h2>
            <p style="color:#64748b; margin-top:10px;">Düzenlemek istediğiniz bitki kimlik numarası (ID) bulunamadı.</p>
            <a href="panel.php?sayfa=seralar" style="color:white; background:#10b981; padding:12px 24px; border-radius:12px; font-weight:700; text-decoration:none; margin-top:20px; display:inline-block;">← Panele Dön</a>
          </div>';
    return;
}

// 1. Mevcut Bitki Bilgilerini Güvenli Şekilde Çek
try {
    $sorgu = $pdo->prepare("SELECT * FROM bitki WHERE Bitki_ID = ?");
    $sorgu->execute([$bitki_id]);
    $bitki = $sorgu->fetch(PDO::FETCH_ASSOC);

    if (!$bitki) {
        echo '<div style="padding:100px 20px; text-align:center; color:#ef4444; font-family:\'Plus Jakarta Sans\',sans-serif; max-width:500px; margin:50px auto;">
                <i class="fas fa-search-minus fa-4x" style="color:#cbd5e1; margin-bottom:20px;"></i><br>
                <h3 style="color:#0f172a;">Kayıt Bulunamadı</h3>
                <p style="color:#64748b;">Belirtilen bitki kaydı sistemde mevcut değil veya silinmiş.</p>
              </div>';
        return;
    }

    // 2. Form Gönderildiğinde Güncelleme İşlemi (POST)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bitki_guncelle'])) {
        $takson_id   = (int)$_POST['takson_id'];
        $lokasyon_id = (int)$_POST['lokasyon_id'];
        $sorumlu_id  = (int)$_POST['sorumlu_id'];
        $aktiflik    = (int)$_POST['aktiflik'] === 1 ? 1 : 0;

        $guncelle = $pdo->prepare("UPDATE bitki SET Takson_ID = ?, Lokasyon_ID = ?, Sorumlu_Pers_ID = ?, Aktiflik = ? WHERE Bitki_ID = ?");
        $guncelle->execute([$takson_id, $lokasyon_id, $sorumlu_id, $aktiflik, $bitki_id]);
        
        $s_id = (int)$bitki['Sera_ID'];
        
        // Output ve Header sorunlarını aşmak için JavaScript tabanlı güvenli yönlendirme
        echo "<script>window.location.href='panel.php?sayfa=sera_detay&id=" . $s_id . "&durum=guncellendi';</script>";
        exit;
    }

    // 3. Seçim Listeleri İçin Verileri Hazırla
    $taksonlar = $pdo->query("SELECT * FROM takson ORDER BY Bitki_Adi ASC")->fetchAll(PDO::FETCH_ASSOC);
    
    $lokasyonlar = $pdo->prepare("SELECT * FROM lokasyon WHERE Sera_ID = ? ORDER BY Parsel_Bilgisi ASC, Sira_No ASC");
    $lokasyonlar->execute([(int)$bitki['Sera_ID']]);
    $lokasyon_listesi = $lokasyonlar->fetchAll(PDO::FETCH_ASSOC);

    // Tablonuzdaki gerçek sütun adına (Ad_Soyad) göre verileri listele
    $personeller = $pdo->query("SELECT Personel_ID, Ad_Soyad, Unvan FROM personel WHERE Aktif_Mi = 1 ORDER BY Ad_Soyad ASC")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $hata = "Veritabanı operasyon hatası: " . $e->getMessage();
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
        --gl-bg: #f8fafc;
        --gl-text: #0f172a;
        --gl-text-muted: #64748b;
        --gl-border: #e2e8f0;
        --font-main: 'Plus Jakarta Sans', sans-serif;
    }

    .gl-edit-wrapper {
        padding: 60px 20px;
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 80vh;
        font-family: var(--font-main);
        background: #fafafa;
    }

    .gl-edit-card {
        background: #ffffff;
        width: 100%;
        max-width: 640px;
        border-radius: 24px;
        padding: 40px;
        box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.06);
        border: 1px solid var(--gl-border);
    }

    .gl-header {
        text-align: center;
        margin-bottom: 35px;
    }

    .gl-header .icon-circle {
        width: 74px;
        height: 74px;
        background: var(--gl-green-light);
        color: var(--gl-green);
        border-radius: 20px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
        margin-bottom: 15px;
        transition: 0.3s;
    }

    .gl-header h3 {
        margin: 0;
        color: var(--gl-text);
        font-size: 24px;
        font-weight: 800;
        letter-spacing: -0.5px;
    }

    .gl-header p {
        color: var(--gl-text-muted);
        font-size: 14px;
        margin-top: 6px;
    }

    .gl-form-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 20px;
    }
    @media (min-width: 500px) {
        .gl-form-grid { grid-template-columns: repeat(2, 1fr); }
        .full-width { grid-column: span 2; }
    }

    .gl-form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 700;
        color: #334155;
        font-size: 0.78rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .gl-input {
        width: 100%;
        height: 52px;
        padding: 0 16px;
        border-radius: 12px;
        border: 1.5px solid #e2e8f0;
        background-color: #f8fafc;
        font-size: 14px;
        color: var(--gl-text);
        transition: all 0.2s ease-in-out;
        box-sizing: border-box;
        font-family: inherit;
        font-weight: 500;
    }

    .gl-input:focus {
        border-color: var(--gl-green);
        background-color: #ffffff;
        outline: none;
        box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.08);
    }

    .gl-btn-row {
        display: flex;
        flex-direction: column;
        gap: 12px;
        margin-top: 35px;
    }

    .gl-btn-save {
        width: 100%;
        background: var(--gl-green);
        color: #ffffff;
        border: none;
        height: 54px;
        border-radius: 14px;
        font-weight: 700;
        font-size: 15px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        transition: all 0.2s;
        box-shadow: 0 10px 20px -5px rgba(16, 185, 129, 0.25);
    }

    .gl-btn-save:hover {
        background-color: var(--gl-green-dark);
        transform: translateY(-1px);
    }

    .gl-btn-cancel {
        width: 100%;
        background-color: #f1f5f9;
        color: var(--gl-text-muted);
        border: none;
        height: 50px;
        border-radius: 12px;
        font-weight: 600;
        font-size: 14px;
        text-decoration: none;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: 0.2s;
    }

    .gl-btn-cancel:hover {
        background-color: #e2e8f0;
        color: var(--gl-text);
    }

    .alert-error {
        background: #fef2f2;
        color: #dc2626;
        padding: 16px;
        border-radius: 12px;
        margin-bottom: 25px;
        font-size: 14px;
        border: 1px solid #fee2e2;
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 500;
    }
</style>

<div class="gl-edit-wrapper animate__animated animate__fadeIn">
    <div class="gl-edit-card animate__animated animate__zoomIn animate__fast">
        
        <div class="gl-header">
            <div class="icon-circle">
                <i class="fas fa-seedling"></i>
            </div>
            <h3>Envanter Düzenleme</h3>
            <p>Sistem Takip Numarası: <span style="font-weight: 700; color: var(--gl-text);">#<?= (int)$bitki_id ?></span></p>
        </div>

        <?php if (isset($hata)): ?>
            <div class="alert-error animate__animated animate__shakeX">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($hata) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="gl-form-grid">
                
                <div class="gl-form-group full-width">
                    <label>Bitki Türü (Takson)</label>
                    <select name="takson_id" required class="gl-input">
                        <?php foreach ($taksonlar as $t): ?>
                            <option value="<?= (int)$t['Takson_ID'] ?>" <?= ($bitki['Takson_ID'] == $t['Takson_ID']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($t['Bitki_Adi']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="gl-form-group full-width">
                    <label>Konum (Parsel / Sıra)</label>
                    <select name="lokasyon_id" required class="gl-input">
                        <?php foreach ($lokasyon_listesi as $l): ?>
                            <option value="<?= (int)$l['Lokasyon_ID'] ?>" <?= ($bitki['Lokasyon_ID'] == $l['Lokasyon_ID']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($l['Parsel_Bilgisi']) ?> — Sıra: <?= htmlspecialchars($l['Sira_No']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="gl-form-group">
                    <label>Sorumlu Personel</label>
                    <select name="sorumlu_id" required class="gl-input">
                        <option value="">Sorumlu Seçiniz</option>
                        <?php foreach ($personeller as $p): ?>
                            <option value="<?= (int)$p['Personel_ID'] ?>" <?= ($bitki['Sorumlu_Pers_ID'] == $p['Personel_ID']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['Ad_Soyad'] . ' — ' . $p['Unvan']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="gl-form-group">
                    <label>Üretim Durumu</label>
                    <select name="aktiflik" class="gl-input" style="font-weight: 600;">
                        <option value="1" <?= ($bitki['Aktiflik'] == 1) ? 'selected' : '' ?> style="color: #10b981;">● Sistemde Aktif</option>
                        <option value="0" <?= ($bitki['Aktiflik'] == 0) ? 'selected' : '' ?> style="color: #64748b;">○ Hasat Edildi / Pasif</option>
                    </select>
                </div>

            </div>

            <div class="gl-btn-row">
                <button type="submit" name="bitki_guncelle" class="gl-btn-save">
                    <i class="fas fa-check-circle"></i> Değişiklikleri Uygula
                </button>
                <a href="panel.php?sayfa=sera_detay&id=<?= (int)$bitki['Sera_ID'] ?>" class="gl-btn-cancel">
                    Vazgeç ve Detaylara Dön
                </a>
            </div>
        </form>

    </div>
</div>