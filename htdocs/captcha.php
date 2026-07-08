<?php
session_start();

// --- SESLİ OKUMA TALEBİ ---
if (isset($_GET['ses']) && $_GET['ses'] == 1) {
    header('Content-Type: application/json; charset=utf-8');
    
    $aktif_kod = isset($_SESSION['captcha']) ? $_SESSION['captcha'] : '';
    $harfler = str_split($aktif_kod);
    $okunacak_dizi = [];
    foreach ($harfler as $harf) {
        if (is_numeric($harf)) {
            $okunacak_dizi[] = $harf;
        } else if ($harf === strtoupper($harf)) {
            $okunacak_dizi[] = "Büyük " . $harf;
        } else {
            $okunacak_dizi[] = "Küçük " . $harf;
        }
    }
    
    $sesli_metin = implode(', ', $okunacak_dizi);
    echo json_encode(['metin' => $sesli_metin]);
    exit();
}
// --------------------------

// --- YENİ RESİM ÜRETME SÜRECİ ---
// Okunurluğu zorlaştıran benzer karakterler (0, 1, I, l, o, O) elendi
$karakterler = '23456789abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
$captcha_kodu = '';
for ($i = 0; $i < 4; $i++) {
    $captcha_kodu .= $karakterler[rand(0, strlen($karakterler) - 1)];
}

$_SESSION['captcha'] = $captcha_kodu;

// Boyutları büyütüyoruz (Okunurluk için ideal ölçek)
$genislik = 180;
$yukseklik = 65;
$resim = imagecreatetruecolor($genislik, $yukseklik);

// Renk Tanımlamaları
$arka_plan = imagecolorallocate($resim, 245, 247, 250); // Hafif kırık beyaz / gri
imagefill($resim, 0, 0, $arka_plan);

// Parazit Çizgileri ve Noktaları (image_f3ae03.png stili)
for ($i = 0; $i < 4; $i++) {
    $cizgi_rengi = imagecolorallocate($resim, rand(150, 200), rand(150, 200), rand(200, 240));
    imagesetthickness($resim, rand(1, 2));
    imageline($resim, rand(0, $genislik), rand(0, $yukseklik), rand(0, $genislik), rand(0, $yukseklik), $cizgi_rengi);
}
for ($i = 0; $i < 200; $i++) {
    $nokta_rengi = imagecolorallocate($resim, rand(140, 200), rand(140, 200), rand(140, 200));
    imagesetpixel($resim, rand(0, $genislik), rand(0, $yukseklik), $nokta_rengi);
}

// Yazı Tipi Ayarları (Gelişmiş TTF Font Desteği)
$font = __DIR__ . '/arial.ttf'; // Klasörünüzdeki herhangi bir .ttf font dosyasını adlandırın
$yazi_rengi = imagecolorallocate($resim, 46, 125, 50); // GreenLog Kurumsal Yeşili

$harf_dizisi = str_split($captcha_kodu);
$x_ekseni = 20;

foreach ($harf_dizisi as $harf) {
    $aci = rand(-15, 15); // Harfleri hafifçe döndürerek estetik katıyoruz
    $boyut = rand(22, 26); // Harf boyutları ciddi oranda büyütüldü
    $y_ekseni = rand(42, 48);
    
    if (file_exists($font)) {
        imagettftext($resim, $boyut, $aci, $x_ekseni, $y_ekseni, $y_rengi, $font, $harf);
    } else {
        // Eğer sunucuda TTF font bulunamazsa düşülecek güvenli modern fallback alternatifi
        $yazi_rengi_yedek = imagecolorallocate($resim, rand(20, 50), rand(80, 120), rand(20, 50));
        imagestring($resim, 5, $x_ekseni, 22, $harf, $yazi_rengi_yedek);
    }
    $x_ekseni += 40;
}

header('Content-Type: image/png');
imagepng($resim);
imagedestroy($resim);
?>