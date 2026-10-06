# PHP Flat-File Alışveriş Scripti

Veritabanı gerektirmeyen, çok basit PHP alışveriş sitesi (veriler `data/*.json` içinde tutulur).

- Sınırsız ürün: ad, görsel, açıklama, fiyat, ödeme IBAN'ı
- Sepet, müşteri bilgileri (ad-soyad, e-posta, telefon, adres) ile sipariş
- Sipariş sonrası yöneticiye ve müşteriye e-posta (SMTP veya PHP `mail()`)
- Site ve SMTP ayarları yönetim panelinden

Kurulum: dosyaları PHP 7.4+ sunucuya yükleyin, `data/` ve `uploads/` yazılabilir olsun.
Yönetim: `/admin/` — ilk giriş `admin` / `admin123` (Site Ayarları'ndan değiştirin).
Test: `php -S localhost:8000`
