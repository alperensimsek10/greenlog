# 🌿 GreenLog: Bütünleşik Botanik Bahçesi Envanter Yönetimi ve İzleme Sistemi

GreenLog, botanik bahçelerindeki karmaşık idari, akademik ve operasyonel süreçleri tek bir çatı altında toplayan, uluslararası standartlara uygun, web tabanlı ve akıllı bir yönetim ekosistemidir. 

Bu proje, **Artvin Çoruh Üniversitesi Mühendislik Fakültesi Bilgisayar Mühendisliği Bölümü** bünyesinde Lisans Bitirme Projesi olarak geliştirilmiştir.

---

## 👥 Geliştiriciler ve Akademik Kadro
*   **Geliştiriciler:** 
    *   Alperen ŞİMŞEK 
    *   Mehmet Emin METİ 
*   **Danışman:** Dr. Öğr. Üyesi Serap YAĞMUR
*   **Dönem:** Haziran 2026 / ARTVİN

---

## 🎯 Projenin Amacı ve Çözdüğü Problemler
Geleneksel botanik kayıt mekanizmaları (Excel, matbu defterler vb.) veri tutarsızlığına, taksonomik isimlendirme hatalarına ve mekânsal konumların kaybedilmesine neden olmaktadır. GreenLog bu problemleri şu çözümlerle ortadan kaldırır:
*   **Akademik Standardizasyon:** Serbest metin girişlerinin getirdiği hataları önlemek adına uluslararası **Darwin Core (DwC)** protokollerini temel alır.
*   **Milimetrik Mekânsal Takip:** Bitki konumlarını metinsel adreslerden kurtararak **Coğrafi Bilgi Sistemleri (CBS)** entegrasyonu ile harita üzerine taşır.
*   **Operasyonel Denetlenebilirlik:** Sahada yapılan tüm tarımsal müdahaleleri, iş akışlarını ve envanter hareketlerini yönetici onay mekanizması (**Durum Makinesi**) ve işlem günlükleri (**Audit Logs**) ile korur.

---

## 🛠️ Teknolojik Yığın (Tech Stack)

### Backend & Veritabanı
*   **Programlama Dili:** PHP 8.x (Nesne Yönelimli Programlama - OOP esaslarına uygun)
*   **Veritabanı:** MySQL 8.0 (InnoDB depolama motoru)
*   **Veri Mimarisi:** 3. Normal Form (3NF) standartlarında normalize edilmiş ilişkisel şema.

### Frontend
*   **Harita Entegrasyonu:** Leaflet.js & OpenStreetMap (OSM)
*   **Tasarım & UI kütüphaneleri:** Plus Jakarta Sans font ailesi, Tom Select (Dinamik arama/filtreleme).

### Siber Güvenlik Duvarı
*   **WhiteShark Security:** Uygulama katmanında SQL Injection, XSS ve CSRF gibi kritik OWASP tehditlerini veritabanına ulaşmadan engelleyen özel middleware katmanı.

---

## 📐 Sistem Mimarisi ve Veri Modeli
Sistem mimarisi, istemci ile sunucu arasındaki veri trafiğini optimize eden üç katmanlı (3-tier) bir tasarıma sahiptir. Veritabanı omurgası birbirine Yabancı Anahtarlarla (Foreign Key) bağlı 7 temel tablonun mantıksal ilişkisiyle yürütülür:
1.  **Taksonomi İlişkisi (`takson` ➔ `bitki`):** Uluslararası standartlardaki bilimsel hiyerarşi ile sahaya dikilen canlı materyaller arasında 1-N ilişkisel bağ kurar.
2.  **Mekânsal Hiyerarşi (`sera` ➔ `lokasyon` ➔ `bitki`):** Bitkinin sadece hangi serada olduğunu değil; seranın içindeki hangi parsel ve sırada yer aldığını double hassasiyetli koordinatlarla izler.
3.  **İdari Takip (`personel` ➔ `ekipman` ➔ `sistem_loglari`):** Tüm CRUD (Ekle, Oku, Güncelle, Sil) hareketlerini, işlemi gerçekleştiren personelin ID bilgisiyle asenkron olarak loglar.

---

## 🔒 İşlem Güvenliği (ACID) ve Kod Örneği
Çok kullanıcılı sistemde yarış durumlarını (race conditions) ve veri kayıplarını engellemek amacıyla veritabanı seviyesinde veri güvenliği (`beginTransaction` ve `commit`/`rollBack`) garanti altına alınmıştır.

```php
// yeni_bitki.php modülünün güvenli transaction yapısı
$pdo->beginTransaction();
try {
    // Form verilerini bitki tablosuna ekle
    $sorgu = $pdo->prepare("INSERT INTO bitki (...) VALUES (...)");
    $sorgu->execute([...]);

    // Audit Log tetikle
    $log_sorgu = $pdo->prepare("INSERT INTO sistem_loglari (...) VALUES (...)");
    $log_sorgu->execute([...]);

    $pdo->commit(); // İşlemleri doğrula
} catch (PDOException $e) {
    $pdo->rollBack(); // Hata durumunda işlemleri geri al
}
