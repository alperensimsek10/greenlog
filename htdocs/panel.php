<?php
ob_start(); // Çıktı tamponlaması başlatıldı (Headers already sent hatasını çözer)
session_start();
require_once "db-connect.php";

// --- AKILLI YÖNLENDİRME SİSTEMİ ---
if (!isset($_SESSION['Personel_ID'])) {
    if (isset($_SERVER['REQUEST_URI'])) {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    }
    header("Location: index.php?error=Lütfen önce giriş yapın");
    exit();
}

if (isset($_SESSION['redirect_url'])) {
    $target = $_SESSION['redirect_url'];
    unset($_SESSION['redirect_url']);
    if (strpos($target, 'panel.php') !== false) {
        header("Location: " . $target);
        exit();
    }
}
// ----------------------------------

$ad_soyad = $_SESSION['Ad_Soyad'];
$rol = $_SESSION['Rol'];
$sayfa = isset($_GET['sayfa']) ? $_GET['sayfa'] : 'anasayfa';

if ($rol === 'Güvenlik' && $sayfa === 'anasayfa') {
    header("Location: ?sayfa=ziyaretci_kayit");
    exit();
}

function active($p, $current) {
    return ($p === $current) ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GreenLog - Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    <link href="style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        /* --- 1. APP SHELL (SABİT İSKELET, SADECE İÇERİK KAYAR) --- */
        body {
            height: 100vh;
            margin: 0;
            overflow: hidden !important; /* Tüm sayfanın kaymasını KESİNLİKLE yasaklar! */
        }

        .main-content {
            height: 100vh;
            display: flex;
            flex-direction: column; /* İçeriği alt alta dizer (Topbar üstte, İçerik altta) */
            overflow: hidden; /* Taşan hiçbir şeye izin vermez */
        }

        .topbar {
            flex-shrink: 0; /* Üst menünün (header) daralmasını veya kaymasını engeller, hep sabit kalır */
            display: flex; 
            align-items: center; 
            padding: 15px 20px;
        }

        .content-area {
            flex: 1; /* Kalan tüm boşluğu (Topbar'ın altını) doldurur */
            overflow-y: auto; /* SADECE BURADA KAYDIRMA ÇUBUĞU ÇIKAR */
            scrollbar-gutter: stable; /* Scrollbar çıktığında içeriği sola itip daraltmasını engeller */
        }
        
        /* --- 2. SİDEBAR SCROLL AYARI (Sol Menü Taşmalarını Önler) --- */
        .sidebar {
            display: flex;
            flex-direction: column;
            height: 100vh; /* Tam ekran yüksekliği */
        }
        .sidebar-menu {
            flex: 1; /* Kalan boşluğu doldurur */
            overflow-y: auto; /* Sadece menü taşarsa scroll çıkar */
            padding-bottom: 20px; /* En alttaki butona nefes aldırır */
        }
        
        /* Sidebar'a özel karanlık temaya uygun zarif kaydırma çubuğu */
        .sidebar-menu::-webkit-scrollbar { width: 5px; }
        .sidebar-menu::-webkit-scrollbar-track { background: transparent; }
        .sidebar-menu::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.2); border-radius: 10px; }
        .sidebar-menu::-webkit-scrollbar-thumb:hover { background: rgba(255, 255, 255, 0.4); }


        /* --- 3. MOBİL MENÜ AYARLARI --- */
        @media (max-width: 992px) {
            .sidebar {
                position: fixed;
                left: -280px; /* Başlangıçta gizli */
                top: 0;
                bottom: 0;
                z-index: 9999;
                transition: 0.3s all ease;
                width: 280px;
                box-shadow: 10px 0 30px rgba(0,0,0,0.1);
            }

            .sidebar.active {
                left: 0; /* Butona basınca görünür yap */
            }

            .main-content {
                margin-left: 0 !important;
                width: 100% !important;
            }

            .menu-toggle {
                display: flex !important; /* Mobilde butonu göster */
            }

            /* Karartma katmanı (Sidebar açıkken arka planı koyulaştırır) */
            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 0; left: 0; right: 0; bottom: 0;
                background: rgba(0,0,0,0.5);
                z-index: 9998;
                backdrop-filter: blur(3px);
            }
            .sidebar-overlay.active { display: block; }
        }

        /* Menü Butonu Stili */
        .menu-toggle {
            display: none; /* Masaüstünde gizli */
            background: #fff;
            border: 1px solid #e2e8f0;
            padding: 10px;
            border-radius: 10px;
            cursor: pointer;
            color: #10b981;
            font-size: 1.2rem;
            margin-right: 15px;
            align-items: center;
            justify-content: center;
            transition: 0.2s;
        }
        .menu-toggle:hover { background: #f8fafc; }
        
        .user-info { flex-grow: 1; }
    </style>
</head>
<body>

    <div class="sidebar-overlay" id="overlay" onclick="toggleSidebar()"></div>

    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-leaf"></i> <span>GreenLog</span>
            <i class="fas fa-times" class="close-sidebar-mobile" onclick="toggleSidebar()" style="display:none; cursor:pointer; font-size: 1.2rem;"></i>
        </div>
        <ul class="sidebar-menu">
            <?php if ($rol !== 'Güvenlik'): ?>
                <li><a href="?sayfa=anasayfa" class="<?= active('anasayfa', $sayfa) ?>"><i class="fas fa-home"></i> <span>Ana Sayfa</span></a></li>
                <li><a href="?sayfa=is_takip" class="<?= active('is_takip', $sayfa) ?>"><i class="fas fa-tasks"></i> <span>İş Takibi</span></a></li>
                <li><a href="?sayfa=arazi_defteri" class="<?= active('arazi_defteri', $sayfa) ?>"><i class="fas fa-book-open"></i> <span>Arazi Defteri</span></a></li>
                <li><a href="?sayfa=uretim_plani" class="<?= active('uretim_plani', $sayfa) ?>"><i class="fas fa-calendar-check"></i> <span>Üretim Planı</span></a></li>
                <li><a href="?sayfa=tohumevi" class="<?= active('tohumevi', $sayfa) ?>"><i class="fas fa-box-open"></i> <span>Tohum Evi</span></a></li>
                <li><a href="?sayfa=seralar" class="<?= active('seralar', $sayfa) ?>"><i class="fas fa-warehouse"></i> <span>Seralar</span></a></li>
                <li><a href="?sayfa=bitkiler" class="<?= active('bitkiler', $sayfa) ?>"><i class="fas fa-seedling"></i> <span>Bitkiler</span></a></li>
                <li><a href="?sayfa=harita" class="<?= active('harita', $sayfa) ?>"><i class="fas fa-map-marked-alt"></i> <span>Harita</span></a></li>
                <li><a href="?sayfa=ekipmanlar" class="<?= active('ekipmanlar', $sayfa) ?>"><i class="fas fa-tools"></i> <span>Ekipmanlar</span></a></li>
            <?php endif; ?>
            
            <?php if ($rol === 'Admin'): ?>
                <li><a href="?sayfa=personel" class="<?= active('personel', $sayfa) ?>"><i class="fas fa-users-cog"></i> <span>Personel</span></a></li>
            <?php endif; ?>

            <?php if ($rol === 'Admin' || $rol === 'Güvenlik'): ?>
                <li><a href="?sayfa=etkinlik" class="<?= active('etkinlik', $sayfa) ?>"><i class="fas fa-calendar-alt"></i> <span>Etkinlik</span></a></li>
                <li><a href="?sayfa=ziyaretci_kayit" class="<?= active('ziyaretci_kayit', $sayfa) ?>"><i class="fas fa-id-badge"></i> <span>Ziyaretçi Kayıt</span></a></li>
                <li><a href="?sayfa=ziyaretci_gecmisi" class="<?= active('ziyaretci_gecmisi', $sayfa) ?>"><i class="fas fa-history"></i> <span>Ziyaretçi Geçmişi</span></a></li>
            <?php endif; ?>
            
            <?php if ($rol !== 'Güvenlik'): ?>
                <li><a href="?sayfa=ayarlar" class="<?= active('ayarlar', $sayfa) ?>"><i class="fas fa-cog"></i> <span>Profil Ayarları</span></a></li>
            <?php endif; ?>

            <?php if ($rol === 'Admin'): ?>
                    <a href="?sayfa=whiteshark_logs" class="<?= active('whiteshark_logs', $sayfa) ?>">
                        <i class="fas fa-shield-alt"></i> <span>Güvenlik & Log</span>
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </aside>

    <div class="main-content">
        <header class="topbar">
            <button class="menu-toggle" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>

            <div class="user-info">
                Merhaba, <strong><?= htmlspecialchars($ad_soyad) ?></strong>
                <span class="badge <?= ($rol === 'Admin') ? 'badge-admin' : '' ?>">
                    <?= htmlspecialchars($rol) ?>
                </span>
            </div>
            <a href="logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Çıkış</a>
        </header>

        <main class="content-area">
            <?php
            $allowed_pages = [
                'anasayfa'             => 'moduller/anasayfa_ozet.php',
                'is_takip'             => 'moduller/is_takip.php',
                'arazi_defteri'        => 'moduller/arazi_defteri.php',
                'arazi_defteri_gecmis' => 'moduller/arazi_defteri_gecmis.php',
                'uretim_plani'         => 'moduller/uretim_plani.php',
                'ariza_bildir'         => 'moduller/ariza_bildir.php', 
                'tohumevi'             => 'moduller/tohumevi.php',
                'seralar'              => 'moduller/sera.php',
                'bitkiler'             => 'moduller/bitki.php',
                'bitki_detay'          => 'moduller/bitki_detay.php',
                'harita'               => 'moduller/harita.php',
                'sera_detay'           => 'moduller/sera_detay.php',
                'yeni_bitki'           => 'moduller/yeni_bitki.php',
                'duzenle'              => 'moduller/duzenle.php',
                'ayarlar'              => 'moduller/ayarlar.php',
                'ekipmanlar'           => 'moduller/ekipman.php',
                'yeni_ekipman'         => 'moduller/yeni_ekipman.php',
                'ekipman_duzenle'      => 'moduller/ekipman_duzenle.php'
            ];

            if ($rol === 'Admin' || $rol === 'Güvenlik') {
                $allowed_pages['ziyaretci_kayit'] = 'moduller/ziyaretci_kayit.php';
                $allowed_pages['ziyaretci_gecmisi'] = 'moduller/ziyaretci_gecmisi.php';
                $allowed_pages['etkinlik'] = 'moduller/etkinlik.php'; 
            }

            if ($rol === 'Admin') {
                $allowed_pages['personel'] = 'moduller/personel_yonetimi.php';
                $allowed_pages['personel_ekle'] = 'moduller/personel_ekle.php';
                $allowed_pages['personel_duzenle'] = 'moduller/personel_duzenle.php';
                
                // White Shark log modül dosya yolunu listeye tanımlıyoruz
                $allowed_pages['whiteshark_logs'] = 'moduller/whiteshark_logs.php';
            }

            if (array_key_exists($sayfa, $allowed_pages)) {
                $dosya = $allowed_pages[$sayfa];
                if (file_exists($dosya)) {
                    include $dosya;
                } else {
                    echo "<div class='placeholder-card'><i class='fas fa-tools'></i><h2>Modül Hazırlanıyor</h2><p><b>$dosya</b> dosyası bulunamadı.</p></div>";
                }
            } else {
                echo "<div class='placeholder-card'><i class='fas fa-exclamation-triangle' style='color:#ef4444'></i><h2>404</h2><p>Sayfa bulunamadı.</p></div>";
            }
            ?>
        </main>
    </div>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('overlay').classList.toggle('active');
        }
        
        // URL'den gelen durum sinyallerini dinle
        document.addEventListener("DOMContentLoaded", function() {
            const urlParams = new URLSearchParams(window.location.search);

            // Eğer URL'de durum=yeni_bitki_ok varsa
            if (urlParams.get('durum') === 'yeni_bitki_ok') {
                Swal.mixin({
                    toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true
                }).fire({
                    icon: 'success', iconColor: '#10b981', title: 'Bitki başarıyla sisteme kaydedildi!'
                });

                // Sayfa yenilenirse mesaj tekrar çıkmasın diye URL'yi temizle
                let yeniUrl = window.location.href.replace(/&?durum=yeni_bitki_ok/, '');
                window.history.replaceState(null, null, yeniUrl);
            }
        });
    </script>
</body>
</html>