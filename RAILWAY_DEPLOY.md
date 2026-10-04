# BlueVera - Railway Deploy

Bu paket BlueVera'yı Railway üzerinde PHP 8.3 + Apache + SQLite ile çalıştırmak için hazırlanmıştır.

## Eklenecek dosyalar

Bu klasördeki dosyaları repository köküne kopyalayın:

- `Dockerfile`
- `docker-entrypoint-bluevera.sh`
- `.dockerignore`
- `public/.htaccess`

Mevcut dosyaların üzerine yalnızca aynı isim varsa dikkatli şekilde yazın.

## Railway adımları

1. Railway'de yeni proje oluşturun.
2. GitHub repository olarak `Rutzlers/BlueVera` seçin.
3. Servise bir **Volume** ekleyin.
4. Volume mount path değerini `/data` yapın.
5. Variables bölümünde:
   - `BLUEVERA_STORAGE=/data`
6. Deploy edin.
7. Railway'den public domain oluşturun.
8. Site açıldıktan sonra yönetim panelinde `site_url` değerini Railway HTTPS adresiniz yapın.

## Kalıcı veriler

Aşağıdakiler `/data` altında tutulur ve yeniden deploy sırasında silinmez:

- SQLite veritabanı: `/data/store.sqlite`
- PHP session dosyaları: `/data/sessions`
- Yüklenen ürün/logolar: `/data/uploads`

`public/uploads` klasörü container açılırken `/data/uploads` konumuna symlink edilir.

## PHP gereksinimleri

Docker image şunları sağlar:

- PHP 8.3
- Apache
- PDO SQLite
- SQLite
- mbstring
- cURL (PHP image içinde mevcut)
- fileinfo

## PayTR

Kart ödemesi için mağaza ayarlarından şu değerler girilmelidir:

- merchant_id
- merchant_key
- merchant_salt
- site_url

`site_url` mutlaka HTTPS olmalıdır.

## Önemli

İlk çalıştırmada BlueVera veritabanını otomatik oluşturur. Ayrı SQL import işlemi gerekmez.
