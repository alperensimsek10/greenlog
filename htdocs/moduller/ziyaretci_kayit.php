<?php
/**
 * GreenLog - Ziyaretçi Kayıt Sistemi (v4.1 - Sadece Bugünün Etkinlikleri Gösterilir)
 */

// 1. SAAT DİLİMİ AYARI
date_default_timezone_set('Europe/Istanbul');
if (isset($pdo)) {
    $pdo->exec("SET time_zone = '+03:00'");
}

$hata_mesaji = "";

// T.C. Kimlik Doğrulama Fonksiyonu (PHP Arka Plan Kontrolü)
function tcKimlikDogrula($tc) {
    $tc = trim((string)$tc);
    if (strlen($tc) != 11 || !preg_match('/^[0-9]{11}$/', $tc)) return false;
    if ($tc[0] == '0') return false;
    
    $tekler = $tc[0] + $tc[2] + $tc[4] + $tc[6] + $tc[8];
    $ciftler = $tc[1] + $tc[3] + $tc[5] + $tc[7];
    
    $hane10 = (($tekler * 7) - $ciftler) % 10;
    $hane11 = ($tekler + $ciftler + $tc[9]) % 10;
    
    if ($hane10 != $tc[9] || $hane11 != $tc[10]) return false;
    return true;
}

// 2. ÇIKIŞ İŞLEMİ YAKALAMA
if (isset($_GET['cikis_id'])) {
    $cikis_id = intval($_GET['cikis_id']);
    $su_an = date('Y-m-d H:i:s');
    
    $update = $pdo->prepare("UPDATE ziyaretciler SET Durum = 'Ayrıldı', Cikis_Zamani = ? WHERE Ziyaretci_ID = ?");
    $update->execute([$su_an, $cikis_id]);
    
    echo "<script>window.location.href='?sayfa=ziyaretci_kayit';</script>";
    exit;
}

// 3. YENİ KAYIT EKLEME
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['yeni_kayit'])) {
    $ad = htmlspecialchars(trim($_POST['ad_soyad']), ENT_QUOTES, 'UTF-8');
    $tc = trim($_POST['tc_kimlik']);
    
    // Açılır kutudan veya 'diğer' seçeneğiyle elle girilen metni yakala
    $neden = isset($_POST['ziyaret_nedeni']) ? trim($_POST['ziyaret_nedeni']) : '';
    if ($neden === 'Diger' && isset($_POST['ozel_neden'])) {
        $neden = trim($_POST['ozel_neden']);
    }
    $neden = htmlspecialchars($neden, ENT_QUOTES, 'UTF-8');
    
    $personel_id = isset($_SESSION['Personel_ID']) ? $_SESSION['Personel_ID'] : 0;
    $giris_zamani = date('Y-m-d H:i:s');

    // Kontrol 1: PHP Tarafında Algoritmik TC Kontrolü
    if (!tcKimlikDogrula($tc)) {
        $hata_mesaji = "Geçersiz T.C. Kimlik Numarası girdiniz! Lütfen kontrol edin.";
    } else {
        // Kontrol 2: Bu TC Numarası Veritabanında Başka Bir İsimle Var mı?
        $check_stmt = $pdo->prepare("SELECT Ad_Soyad FROM ziyaretciler WHERE TC_Kimlik = ? ORDER BY Ziyaretci_ID DESC LIMIT 1");
        $check_stmt->execute([$tc]);
        $mevcut_kayit = $check_stmt->fetch(PDO::FETCH_ASSOC);

        if ($mevcut_kayit) {
            $mevcut_isim = mb_strtolower(trim($mevcut_kayit['Ad_Soyad']), 'UTF-8');
            $yeni_isim = mb_strtolower($ad, 'UTF-8');

            if ($mevcut_isim !== $yeni_isim) {
                $hata_mesaji = "Güvenlik Engeli: Bu T.C. Kimlik Numarası sistemde zaten başka bir isimle (<strong>" . htmlspecialchars($mevcut_kayit['Ad_Soyad'], ENT_QUOTES, 'UTF-8') . "</strong>) kayıtlıdır! Lütfen bilgileri doğrulayın.";
            }
        }

        // Eğer hiçbir hata mesajı oluşmadıysa kaydet
        if (empty($hata_mesaji)) {
            $insert = $pdo->prepare("INSERT INTO ziyaretciler (Ad_Soyad, TC_Kimlik, Ziyaret_Nedeni, Kaydeden_Personel_ID, Giris_Zamani, Durum) VALUES (?, ?, ?, ?, ?, 'İçeride')");
            $insert->execute([$ad, $tc, $neden, $personel_id, $giris_zamani]);
            
            echo "<script>window.location.href='?sayfa=ziyaretci_kayit';</script>";
            exit;
        }
    }
}

// 4. 🎯 SADECE BUGÜNÜN AKTİF ETKİNLİKLERİNİ ÇEKME (ZAMANSAL FİLTRE)
try {
    $pdo->exec("SET NAMES 'utf8mb4'");
    // Etkinlik tarihi tam olarak bugüne eşit olanları getirir
    $aktif_etkinlikler_sorgu = $pdo->query("SELECT * FROM etkinlikler WHERE Etkinlik_Tarihi = CURRENT_DATE() ORDER BY Etkinlik_ID DESC");
    $aktif_etkinlikler = $aktif_etkinlikler_sorgu->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $aktif_etkinlikler = [];
}
?>

<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght=400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />

<style>
    :root { 
        --gl-green: #10b981; 
        --gl-green-dark: #059669; 
        --gl-green-light: #ecfdf5;
        --gl-danger: #ef4444;
        --gl-danger-light: #fef2f2;
        --gl-text: #0f172a;
        --gl-text-muted: #64748b;
        --gl-border: #e2e8f0;
        --font-main: 'Plus Jakarta Sans', sans-serif;
    }

    .form-wrapper { font-family: var(--font-main); max-width: 1100px; margin: 0 auto; padding: 20px; color: var(--gl-text); }
    .register-container { background: white; border-radius: 24px; border: 1px solid var(--gl-border); box-shadow: 0 20px 40px -15px rgba(0,0,0,0.05); overflow: hidden; margin-bottom: 40px; }
    .register-header { background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 35px 45px; color: white; display: flex; align-items: center; gap: 20px; }
    .reg-icon { background: rgba(255,255,255,0.18); width: 60px; height: 60px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 24px; backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,0.2); }
    .reg-title h2 { margin: 0; font-size: 24px; font-weight: 800; letter-spacing: -0.5px; }
    .register-body { padding: 45px; }
    
    .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; align-items: flex-end; }
    .form-group { display: flex; flex-direction: column; gap: 8px; }
    
    label { font-size: 11px; font-weight: 800; color: var(--gl-text-muted); text-transform: uppercase; letter-spacing: 0.7px; padding-left: 4px; display: flex; align-items: center; gap: 6px; }
    label i { color: var(--gl-green); }

    input, select { 
        padding: 14px 16px; border-radius: 12px; border: 2px solid #e2e8f0; 
        outline: none; transition: all 0.3s ease; font-size: 15px; font-weight: 600;
        background: #f8fafc; color: var(--gl-text); font-family: inherit;
        box-sizing: border-box; width: 100%;
    }
    input:focus, select:focus { border-color: var(--gl-green); background: #fff; box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.12); }

    .alert-box { background: var(--gl-danger-light); border: 1px solid #fca5a5; padding: 15px 20px; border-radius: 14px; display: flex; align-items: center; gap: 12px; margin-bottom: 25px; border-left: 5px solid var(--gl-danger); color: #991b1b; font-weight: 600; font-size: 14px; }
    .btn-save { background: var(--gl-green); color: white; padding: 14px 20px; border-radius: 12px; border: none; font-weight: 800; font-size: 15px; cursor: pointer; transition: all 0.3s ease; width: 100%; display: flex; justify-content: center; align-items: center; gap: 10px; box-shadow: 0 8px 16px rgba(16, 185, 129, 0.15); }
    .btn-save:hover { background: var(--gl-green-dark); transform: translateY(-2px); box-shadow: 0 12px 20px rgba(16, 185, 129, 0.25); }
    .btn-exit { background: #fff1f2; color: #e11d48; padding: 8px 16px; border-radius: 10px; text-decoration: none; font-weight: 700; font-size: 13px; border: 1px solid #ffe4e6; transition: all 0.2s; display: inline-flex; align-items: center; gap: 6px; }
    .btn-exit:hover { background: #e11d48; color: white; transform: translateY(-1px); }

    .table-section h3 { font-weight: 800; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; font-size: 18px; color: var(--gl-text); }
    .table-card { background: white; border-radius: 20px; border: 1px solid var(--gl-border); overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.02); }
    .custom-table { width: 100%; border-collapse: collapse; }
    .custom-table thead { background: #f8fafc; border-bottom: 1px solid var(--gl-border); }
    .custom-table th { padding: 16px 20px; text-align: left; font-size: 11px; text-transform: uppercase; color: var(--gl-text-muted); letter-spacing: 0.8px; font-weight: 800; }
    .custom-table td { padding: 18px 20px; border-top: 1px solid #f1f5f9; font-size: 14px; font-weight: 600; }
    .custom-table tr:hover { background: #f8fafc; }

    .time-in { color: var(--gl-green); font-weight: 700; display: flex; align-items: center; gap: 5px; }
    .status-badge { background: var(--gl-green-light); color: var(--gl-green-dark); padding: 4px 8px; border-radius: 6px; font-size: 10px; font-weight: 800; display: inline-block; margin-top: 4px; }
    
    .event-badge {
        background: #eff6ff; color: #2563eb; padding: 3px 8px; border-radius: 6px;
        font-size: 11px; font-weight: 700; display: inline-block; border: 1px solid #bfdbfe; margin-top: 4px;
    }

    @media (max-width: 768px) { .form-grid { grid-template-columns: 1fr; } }
</style>

<div class="form-wrapper animate__animated animate__fadeIn">
    
    <?php if (!empty($hata_mesaji)): ?>
        <div class="alert-box animate__animated animate__shakeX">
            <i class="fas fa-exclamation-triangle" style="font-size: 20px;"></i>
            <p style="margin:0; padding-left: 5px; line-height: 1.4;"><?= $hata_mesaji ?></p>
        </div>
    <?php endif; ?>

    <div class="register-container">
        <div class="register-header">
            <div class="reg-icon"><i class="fas fa-id-card"></i></div>
            <div class="reg-title"><h2>Ziyaretçi Giriş Kaydı</h2></div>
        </div>

        <div class="register-body">
            <form method="POST" autocomplete="off" id="visitorForm">
                <div class="form-grid">
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Ziyaretçi Ad Soyad</label>
                        <input type="text" name="ad_soyad" required placeholder="Örn. Ahmet Yılmaz">
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-fingerprint"></i> T.C. Kimlik No</label>
                        <input type="text" name="tc_kimlik" id="tc_kimlik" maxlength="11" minlength="11" required placeholder="11 Haneli TC No">
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-comment-alt"></i> Ziyaret Nedeni / Etkinlik</label>
                        <select id="ziyaret_nedeni" name="ziyaret_nedeni" onchange="ziyaretNedeniKontrol(this)" required>
                            <option value="" disabled selected>-- Neden Seçiniz --</option>
                            
                            <?php if(!empty($aktif_etkinlikler)): ?>
                                <optgroup label="Bugün Tanımlı Etkinlikler">
                                    <?php foreach($aktif_etkinlikler as $etk): ?>
                                        <option value="<?= htmlspecialchars($etk['Etkinlik_Adi']) ?>">
                                            📅 Etkinlik: <?= htmlspecialchars($etk['Etkinlik_Adi']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endif; ?>
                            
                            <optgroup label="Standart Nedenler">
                                <option value="Toplantı / Görüşme">Toplantı / Görüşme</option>
                                <option value="Bakım / Onarım / Destek">Bakım / Onarım / Destek</option>
                                <option value="Kargo / Evrak Teslimat">Kargo / Evrak Teslimat</option>
                                <option value="Diger">Diğer (Kendim Yazmak İstiyorum)...</option>
                            </optgroup>
                        </select>
                    </div>

                    <div class="form-group">
                        <button type="submit" name="yeni_kayit" class="btn-save">
                            <i class="fas fa-sign-in-alt"></i> Giriş Kaydı Aç
                        </button>
                    </div>
                </div>

                <div class="form-group" id="ozel_neden_alani" style="display: none; margin-top: 20px; max-width: 400px;">
                    <label><i class="fas fa-edit"></i> Özel Ziyaret Nedenini Yazınız</label>
                    <input type="text" id="ozel_neden" placeholder="Örn: Kalibrasyon Hizmeti">
                </div>
            </form>
        </div>
    </div>

    <div class="table-section">
        <h3><i class="fas fa-users" style="color: var(--gl-green);"></i> İçerideki Ziyaretçiler</h3>
        <div class="table-card">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>Ziyaretçi</th>
                        <th>Kimlik No</th>
                        <th>Giriş Zamanı</th>
                        <th>Ziyaret Nedeni</th>
                        <th style="text-align: right;">İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sorgu = $pdo->query("SELECT * FROM ziyaretciler WHERE Durum = 'İçeride' ORDER BY Giris_Zamani DESC");
                    if ($sorgu && $sorgu->rowCount() > 0) {
                        while ($row = $sorgu->fetch(PDO::FETCH_ASSOC)) {
                            $masked_tc = (strlen($row['TC_Kimlik']) == 11) ? substr($row['TC_Kimlik'], 0, 3) . '*****' . substr($row['TC_Kimlik'], -3) : $row['TC_Kimlik'];
                            $safe_ad = htmlspecialchars($row['Ad_Soyad'], ENT_QUOTES, 'UTF-8');
                            $safe_neden = htmlspecialchars($row['Ziyaret_Nedeni'], ENT_QUOTES, 'UTF-8');
                            
                            // İçerideki ziyaretçinin nedeni veritabanında etkinlikler tablosunda var mı?
                            // (Eski veya yeni fark etmeksizin rozet doğru görünsün diye genel bir check)
                            $is_event = false;
                            try {
                                $check_ev = $pdo->prepare("SELECT COUNT(*) FROM etkinlikler WHERE Etkinlik_Adi = ?");
                                $check_ev->execute([$row['Ziyaret_Nedeni']]);
                                if($check_ev->fetchColumn() > 0) { $is_event = true; }
                            } catch(PDOException $err) {}
                            ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 700; color: var(--gl-text);"><?= $safe_ad ?></div>
                                    <span class='status-badge'><i class="fas fa-door-open"></i> İÇERİDE</span>
                                </td>
                                <td><code style='color:var(--gl-text-muted); font-size: 14px; font-weight: 600; font-family: monospace;'><?= $masked_tc ?></code></td>
                                <td>
                                    <div class='time-in'><i class='far fa-clock'></i> <?= date('H:i', strtotime($row['Giris_Zamani'])) ?></div>
                                    <small style='color:#94a3b8; font-weight:500;'><?= date('d.m.Y', strtotime($row['Giris_Zamani'])) ?></small>
                                </td>
                                <td>
                                    <?php if($is_event): ?>
                                        <span class="event-badge"><i class="fas fa-calendar-alt"></i> <?= $safe_neden ?></span>
                                    <?php else: ?>
                                        <span style="color: var(--gl-text-muted);"><?= $safe_neden ?></span>
                                    <?php endif; ?>
                                </td>
                                <td style='text-align: right;'>
                                    <a href='?sayfa=ziyaretci_kayit&cikis_id=<?= $row['Ziyaretci_ID'] ?>' class='btn-exit' onclick='return confirm("Ziyaretçi çıkış yapsın mı?")'>
                                        <i class='fas fa-sign-out-alt'></i> Çıkış Yap
                                    </a>
                                </td>
                            </tr>
                            <?php
                        }
                    } else {
                        echo "<tr><td colspan='5' style='padding: 60px; text-align: center; color: var(--gl-text-muted); font-weight:500;'>Şu anda içeride aktif ziyaretçi bulunmuyor.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    function ziyaretNedeniKontrol(selectElement) {
        var ozelNedenAlani = document.getElementById('ozel_neden_alani');
        var ozelNedenInput = document.getElementById('ozel_neden');
        
        if (selectElement.value === 'Diger') {
            ozelNedenAlani.style.display = 'flex';
            ozelNedenInput.required = true;
            ozelNedenInput.setAttribute('name', 'ozel_neden');
        } else {
            ozelNedenAlani.style.display = 'none';
            ozelNedenInput.required = false;
            ozelNedenInput.removeAttribute('name');
        }
    }

    document.getElementById('tc_kimlik').addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
    });

    document.getElementById('visitorForm').addEventListener('submit', function(e) {
        const tc = document.getElementById('tc_kimlik').value.trim();
        
        if (tc.length !== 11 || tc[0] === '0') {
            alert('Geçersiz T.C. Kimlik Numarası! (11 haneli olmalı ve 0 ile başlayamaz)');
            e.preventDefault();
            return false;
        }

        let tekler = 0;
        let ciftler = 0;
        let tumu = 0;

        for (let i = 0; i < 9; i++) {
            const rakam = parseInt(tc[i]);
            tumu += rakam;
            if (i % 2 === 0) {
                tekler += rakam;
            } else {
                ciftler += rakam;
            }
        }

        tumu += parseInt(tc[9]);

        const hane10 = ((tekler * 7) - ciftler) % 10;
        const hane11 = tumu % 10;

        if (hane10 !== parseInt(tc[9]) || hane11 !== parseInt(tc[10])) {
            alert('Girdiğiniz T.C. Kimlik Numarası Hatalı. Lütfen kontrol edin.');
            e.preventDefault();
            return false;
        }
    });
</script>