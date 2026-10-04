# BlueVera — kurulum ve kullanım

## Sipariş takibi ve okunaklılık güncellemesi

- Yeni ve mevcut her siparişe benzersiz, kısa `482731` gibi yalnızca rakamlardan oluşan takip kodu atanır. Harf veya tire gerekmez. Eski BV biçimindeki kodlar da kabul edilir. Önceden oluşturulmuş uzun kodlar da geçerli kalır; mevcut siparişlerde artık kısa kod gösterilir. Kod sipariş detayında ve admin sipariş kartında görünür. Müşteri takip kodu **ve e-postasıyla** sorgular; eski sipariş numarası da çalışır.
- Admin → Siparişler ekranında onaylı siparişleri **Hazırlanıyor → Kargoya verildi → Yolda → Teslim edildi** olarak ilerletin. Kargoya verme aşamasında kargo firması ve firmanın takip numarası gereklidir. Mağazanın takip kodu bu harici kargo numarasından ayrıdır.
- Ödeme onayı olmadan kart siparişi sevke ilerletilemez; kapıda ödeme siparişi onaylı olarak başlar. Kargo firmasıyla otomatik bağlantı yoktur: durumları admin günceller, müşteri sayfayı yenilediğinde görür. E-posta/SMS gönderilmez.
- Her durum değişikliği tarih/saat ile geçmişe kaydedilir. Eski siparişlerde önceki hareketler uydurulmaz; geçiş anındaki mevcut durum kaydedilir.
- Ana metinler ve küçük etiketler büyütüldü. Tema renklerini koruyarak ana yüzeylerde metin rengi en az 4,5:1 kontrast sağlayacak şekilde otomatik seçilir. Bu, sitenin tamamı için erişilebilirlik sertifikası değildir.

## Güncelleme: kategori, yönetici girişi ve görünüm

Mevcut kurulum için `BlueVera-Guncelleme.zip` kullanın. Önce mağaza dosyaları ve veritabanını yedekleyin; ardından `app` dosyalarını mevcut `app` içine, `public` içindekileri mevcut web köküne (örneğin `public_html`) yükleyin. `storage` veya `uploads` klasörünü silmeyin/değiştirmeyin. Bu güncelleme ZIP'inde veritabanı, yüklediğiniz resimler ve kurulum anahtarı bulunmaz. Kategoriler ilk istekte mevcut ürünlerden otomatik aktarılır; üyeler, yönetici hesabı ve siparişler korunur.

- **Kategoriler:** yeni kategori ekleyin; adını değiştirince bağlı ürünler de güncellenir. Ürün formunda kategoriyi açılır listeden seçin.
- **Hesabım:** ilk kurulumda belirlediğiniz yönetici e-postası ve şifresi admin panelini açar. Normal müşteri hesapları yönetici olmaz. Yönetici olarak giriş yaptıysanız Hesabım doğrudan paneli açar.
- **Logo, isim ve renkler:** mağaza adı, logo ve sekiz tema rengini değiştirin. PNG/JPG/WebP logo yüklenebilir; eski logoya geri dönülebilir. İsim ve logo üst/alt bölüm ve yönetim panelinde uygulanır.
- **İade ve Değişim:** boş örnek metnin yerine 30 Eylül 2026 itibarıyla Ticaret Bakanlığının bilgilendirmeleri esas alınarak metin eklendi. Daha önce yazdığınız özel bir metin varsa korunur. **Mağaza ve ödeme** bölümünden ticari unvan, destek e-postası, telefon, iade adresi ve iade taşıyıcısını tamamlayın. Diğer hukuk sayfaları bu güncellemede doldurulmamıştır. Metni ürünlerinizin niteliği ve işletmenizin gerçek süreçleriyle birlikte hukuk uzmanına kontrol ettirin.

Bu paket PHP ile çalışan, ürün, stok ve sipariş yönetimi olan bir mağazadır. Kurulum için Composer, Node veya MySQL veritabanı oluşturmanız gerekmez. Veriler SQLite dosyasında saklanır. Vitrin için örnek ürünler ve fotoğraflar eklenmiştir; bu kayıtlar satın alınamaz.

## Bilgisayarınızda açma

1. `BASLAT.bat` dosyasına çift tıklayın. XAMPP `C:\xampp` konumunda olmalıdır.
2. Tarayıcıda `http://127.0.0.1:8097` adresini açın. Önizleme zaten açıksa ikinci kez başlatmayın.
3. İlk yönetici hesabını oluşturmak için `http://127.0.0.1:8097/install.php` adresine gidin.
4. `storage/install-token.txt` dosyasını bilgisayarınızda açın. İçindeki anahtarı kurulum ekranına girin, kendi e-posta ve şifrenizi belirleyin. Varsayılan/ortak şifre yoktur.
5. Yönetim: `http://127.0.0.1:8097/index.php?page=admin/login`

## Hostinge yükleme — önerilen düzen

Gereksinimler: PHP 8.2 veya üzeri, PDO SQLite, mbstring, fileinfo, OpenSSL, cURL ve HTTPS. Kod yerel XAMPP PHP 8.0.30 ile de kontrol edilmiştir; canlıda güncel desteklenen bir PHP sürümü kullanın. SQLite etkin değilse hosting desteğinden `pdo_sqlite` eklentisini açmasını isteyin. Bu sürüm MySQL kullanmaz.

Hosting dosya yöneticisinde:

```text
/home/hesabiniz/
  app/                  ← paketteki app klasörü
  storage/              ← paketteki storage klasörü (webden erişilmemeli)
  public_html/          ← paketteki public klasörünün İÇİNDEKİ dosyalar
    index.php
    install.php
    callback.php
    .htaccess
    assets/
    uploads/
```

1. ZIP'i bilgisayarınızda çıkarın. `app` ve `storage` klasörlerini `public_html` ile aynı üst klasöre yükleyin. Aynı adlı mevcut uygulama klasörleriniz varsa onların üzerine yazmayın; ayrı hosting alanı veya ayrı üst klasör kullanın.
2. `public` klasörünün **içindekileri** `public_html` içine yükleyin. Gizli `.htaccess` dosyalarını dahil edin.
3. PHP'nin `storage` ve `public_html/uploads` klasörlerine yazabildiğinden emin olun. Genellikle klasörler 0755, dosyalar 0644 yeterlidir; herkese yazma izni (0777) vermeyin.
4. SSL'yi etkinleştirin ve hosting panelinden HTTPS yönlendirmesini açın.
5. `https://alanadiniz.com/install.php` üzerinden kendinize yönetici hesabı oluşturun. ZIP'teki `storage/install-token.txt` anahtarını kullanın.
6. Kurulumdan sonra `install.php` ve `storage/install-token.txt` dosyalarını kaldırabilirsiniz. Hesap oluşturulduktan sonra kurulum zaten kilitlenir.
7. `https://alanadiniz.com/index.php?page=admin/login` adresinden giriş yapın.

Hostinginiz yalnızca tek klasöre yüklemeye izin veriyorsa paketin tamamını bir klasöre koyup `public/` adresinden çalıştırabilirsiniz; bu durumda Apache ve `.htaccess` kurallarının çalışması zorunludur. `storage/store.sqlite` ve `storage/install-token.txt` adreslerinin dışarıdan 403/404 döndürdüğünü doğrulayın. Nginx üzerinde `public` klasörünü document root yapın. Gizli dosyaları public klasörüne taşımayın.

## Ürün ve mağaza yönetimi

- **Ürünler → Yeni ürün:** ad, kategori, açıklama, fiyat, stok ve görsel ekleyin. Kategori ürünlerden otomatik oluşur. JPG, PNG, WebP; en fazla 5 MB.
- **Vitrinden kaldır:** ürün kaydını ve eski siparişleri koruyarak yayından alır. Düzenle ekranından tekrar yayınlanabilir.
- Örnek ürünleri gizleyin veya tüm bilgilerini gerçek ürüne göre düzenleyip “Örnek ürün” işaretini kaldırın. Fotoğraflar temsili olduğundan gerçek ürün fotoğraflarınızı yükleyin.
- **Mağaza ve ödeme:** işletme unvanı, adres, telefon, destek e-postası, site adresi, kargo ücreti ve ücretsiz kargo sınırını doldurun. Vitrin markası bu teslimatta BlueVera olarak tasarlanmıştır.
- **Bilgi sayfaları:** gizlilik, KVKK, mesafeli satış, iade ve çerez metinlerini işletmenize uygun olarak ekleyin. Paket içindeki içerikler tamamlanmış hukuki metin değildir.
- Son olarak **Mağazada sipariş alımını aç** kutusunu işaretleyin. İşletme bilgileri, metin onayı ve en az bir ödeme yöntemi tamamlanmadan açılamaz.

## Ödeme seçenekleri

### Havale / EFT
Banka adı, IBAN ve hesap sahibini girip etkinleştirin. Sipariş oluşturulunca banka bilgileri ve açıklamaya yazılacak sipariş numarası gösterilir. Bankadan tahsilatı kontrol ettikten sonra siparişi panelde “Ödeme alındı” durumuna geçirin. Sistem banka hesabınızı otomatik izlemez. Ödenmeyen havale rezervasyonları 48 saat sonra bir sonraki site isteğinde sona erer ve stok geri eklenir.

### Kapıda ödeme
Panelden açılabilir. Kargo firmanızla kapıda tahsilat anlaşmasını kendiniz yapmalısınız. Sipariş sistemde kaydolur; otomatik kargo etiketi veya kargo firması API bağlantısı yoktur. Kargo takip bilgisi panelden girilir.

### Kart / PayTR
Başlangıçta kapalıdır. PayTR işyeri anlaşması ve size verilen `merchant_id`, `merchant_key`, `merchant_salt` gerekir. Gizli bilgileri sohbet yerine doğrudan kendi yönetim panelinize girin.

1. Site adresini `https://alanadiniz.com` şeklinde ayarlayın; sonda `index.php` olmasın. Alt klasördeyse `https://alanadiniz.com/magaza/public` gibi gerçek kökü girin.
2. PayTR panelinde bildirim adresini `https://alanadiniz.com/callback.php` olarak tanımlayın (alt klasör varsa ekleyin).
3. Önce test modu açıkken gerçek olmayan test ödemesi yapın. Başarılı yönlendirme tek başına siparişi onaylamaz; imzası doğrulanmış sunucu bildirimi gerekir. Tutarlar sunucuda hesaplanır. Tek çekim kullanılır; taksit kapalıdır.
4. Test siparişi “Test ödemesi — sevk etmeyin” durumuna geçmelidir. Test satışları ciroya dahil edilmez; stok iade edilir.
5. PayTR onayı ve uçtan uca test tamamlanınca test modunu kapatıp kart ödemesini açın.

Canlı tahsilat bu teslimatta denenmemiştir: mağaza hesabı ve anahtarları verilmemiştir. PayTR, localhost'taki bildirim adresine ulaşamaz; canlı HTTPS alanında test edin. Resmi belgeler: https://dev.paytr.com/iframe-api/iframe-api-1-adim ve https://dev.paytr.com/iframe-api/iframe-api-2-adim

Kart rezervasyonu 60 dakika sürer; ödeme formu 30 dakikalık süre ile açılır. Rezervasyon sona erdikten sonra ödeme bildirimi gelirse sipariş “Manuel inceleme” olur. Bu siparişleri otomatik sevk etmeyin; tahsilat/stok durumunu kontrol edin. İadeler otomatik yapılmaz, ödeme kuruluşu panelinden yürütülür.

## Siparişler ve yedekleme

Üyelik zorunlu değildir. Müşteri sipariş numarası + e-postasıyla takip yapabilir. Üye oturumunda verilen siparişler hesap sayfasında görünür. E-posta/SMS bildirimi, şifre sıfırlama e-postası ve otomatik kargo bağlantısı bu sürüme dahil değildir.

Yedeklemeden önce satış alımını kapatın; `storage/store.sqlite` ve `public/uploads` dosyalarını birlikte yedekleyin. Yedekleri webden erişilebilir klasörlere koymayın. Sipariş verileri ve ödeme anahtarları içerir. Sunucu hesabını ve yönetici şifrenizi koruyun. Yönetici oturumu 1 saat hareketsizlikte sona erer.

Paket temiz veritabanıyla kurulur. Önizleme/test verileri ZIP'e dahil edilmez. Mevcut mağazayı güncellerken `storage` ve `uploads` içeriğinin üzerine temiz paketle yazmayın.


## SEO ve Google hazırlığı

- Ürün adı ve açıklamasından otomatik, sayfaya özel başlık/açıklama üretilir. Ürün bağlantılarında Türkçe karakterleri sadeleştirilmiş ürün adı bulunur; eski bağlantılar çalışmaya devam eder. Kanonik bağlantı aynı ürünün farklı bağlantılarının tercih edilen adresini belirtir.
- Mağaza ve ödeme → Site adresi alanına gerçek HTTPS adresini (alt klasör varsa onunla birlikte) yazın. Site adresi boşken veya sipariş alımı kapalıyken sayfalar noindex olur ve site haritası boş kalır. Mağaza satışa açıldığında aktif, örnek olmayan ürünler otomatik dahil edilir.
- Hesap, sepet, ödeme, takip ve yönetim sayfaları ile arama/sıralama sonuçları noindex olur. Bu bir erişim güvenliği mekanizması değildir; mevcut kimlik doğrulama korunur.
- Gerçek ürünlerde fiyat (TRY), stok, görsel ve ürün adı Product/Offer verisi olarak sunulur. Puan veya müşteri yorumu uydurulmaz. Paylaşım başlığı ve görsel bilgileri de oluşturulur.
- Site haritası: SITE_ADRESI/sitemap.php. Apache/LiteSpeed yönlendirmesi açıkken sitemap.xml de çalışır. robots.txt aynı şekilde robots.php üzerinden üretilir. Dosyaları yüklerken gizli .htaccess dosyasını da aktarın.
- Yayında HTTPS adresini, robots.txt ve sitemap.php çıktısını kontrol edin; alan adını Google Search Console üzerinden doğrulayıp sitemap.php adresini gönderin. Henüz Google hesabına bağlanılmadı veya sitemap gönderilmedi. Arama sıralaması garantisi yoktur.
- Google kaynakları: https://developers.google.com/search/docs/specialty/ecommerce/designing-a-url-structure-for-ecommerce-sites ve https://developers.google.com/search/docs/appearance/structured-data/product-snippet

## Bu sürümde eklenenler

- Yasal sayfalar (Gizlilik, KVKK, Mesafeli Satış, İade, Çerez) örnek metin olarak kaldığı sürece sipariş alımı kapalı kalır; ayarlarda satışı açmak da engellenir.
- Yeni siparişte müşteriye (sözleşme ve iade metni kopyasıyla) ve size e-posta gider; kargoya verilince müşteriye bildirim gider. Gönderim, Destek e-postası ayarı ve hosting'in PHP mail() desteğiyle çalışır. Hosting mail() kapatmışsa e-posta gitmez, panel yine çalışır.
- Yönetici şifresi Ayarlar sayfasından değiştirilebilir.
- Siparişlerde arama ve 25'lik sayfalama var. Ödenmiş / hazırlanan / kargodaki siparişler "İade edildi" yapılabilir (hazırlanmamış siparişte stok geri döner). PayTR iadesini yine PayTR panelinden yapmalısınız.
- Süresi dolan havale siparişi ve "Manuel inceleme" (canlı) siparişi, stok yeterliyse "Ödeme alındı" olarak kurtarılabilir.
- PayTR ödeme çerçevesi artık iframeResizer ile otomatik boyutlanır (CSP yalnızca ödeme sayfasında paytr.com betiğine izin verir).
- Oturum ve sepet 7 gün saklanır; eski oturum dosyaları otomatik temizlenir.
- Cloudflare vb. arkasındaysanız Ayarlar > Sunucu kutusunu işaretleyin; aksi halde işaretlemeyin (IP sahteciliğini önlemek için varsayılan kapalıdır).
- Statik dosyalar (görsel/CSS) tarayıcıda önbelleğe alınır, sayfalar sıkıştırılır (Apache).
- Ürün görseli değiştirildiğinde eski dosya silinir; siparişlerde artık yalnızca ürün adı/fiyat/adet saklanır.

- Alt kısımda küçük bir çerez bilgilendirme bandı var (yalnızca zorunlu çerez kullanıldığını belirtir, "Anladım" ile kapanır). Ödeme düğmesi "Siparişi onayla ve öde" olarak değişti; son metni avukatınızla teyit edin.

Bilerek eklenmeyenler: ürün başına farklı fiyat/SKU (seçenekler aynı fiyatı paylaşır), kupon, ürün yorumları. Mevcut kayıtlı eski siparişler olduğu gibi çalışır.

## Backend eklemeleri (şifre, yedek, bot koruması)

- **Şifremi unuttum:** Müşteri giriş sayfasındaki bağlantıdan e-postasını yazar, 1 saat geçerli tek kullanımlık bağlantı alır. Bağlantı yalnızca Ayarlar'daki "Site adresi" doluysa ve hosting mail() izin veriyorsa gönderilir. Kayıtlı olmayan e-posta için de aynı cevap verilir.
- **E-posta doğrulama:** Yeni üyeye doğrulama bağlantısı gider (7 gün). Zorunlu değildir; doğrulanmamış üye alışveriş yapabilir, Hesabım'da yeniden gönder düğmesi görür.
- **Veritabanı yedeği:** Yönetim > Ayarlar > "Yedeği indir". Tek .sqlite dosyası iner (siparişler, müşteriler, PayTR anahtarları dahil). Geri yüklemek için siteyi kapatıp `storage/store.sqlite` dosyasını bununla değiştirin. Yüklenen ürün görselleri (`public/uploads`) ayrıca yedeklenmelidir.
- **Ürün silme:** Ürünler listesinde "Sil". Siparişlerde geçen ürünler silinemez, onları "Vitrinden kaldır" ile gizleyin.
- **Bot koruması:** Formlara görünmez bir tuzak alan ve imzalı zaman damgası eklendi (CAPTCHA yok, görünüm aynı). Ayrıca aynı e-posta/telefonla 3'ten fazla ödemesi bekleyen havale/kart siparişi açılamaz; böylece stok kilitleme engellenir.
- **Yönetici şifresini unuttuysanız** (SSH/terminal gerekir): `php app/reset-admin.php "yeni-sifre-en-az-12-karakter"`. Paneldeyken değiştirmek için Ayarlar > Yönetici şifresi.
- Güncelleme sonrası açık duran eski sayfalardan gönderilen formlar bir kez "Formu yenileyin" uyarısı verebilir.

## Ürün seçenekleri, ek görseller ve adres alanları

- **Seçenekli ürün (renk/beden/model):** Ürün düzenleme sayfasında "Seçenek türü" (örn. Renk) ve "Seçenekler ve stok" kutusuna her satıra `Ad | stok` yazın (örn. `Mavi | 5`). Her seçeneğin stoğu ayrı tutulur, toplam stok otomatik hesaplanır. Fiyat tüm seçeneklerde aynıdır. Sepete eklemeden önce müşteri seçenek seçmek zorundadır; seçenek siparişte ürün adının yanında görünür. Kutuyu boşaltırsanız ürün normal stoklu ürüne döner. Seçenekli ürünlerde katalogdaki "+" düğmesi ürün sayfasına götürür.
- **Ek görseller:** Ürün başına ana görsele ek en fazla 8 görsel yüklenebilir. Ürün sayfasında küçük önizlemeler çıkar, tıklayınca ana görsel değişir. Silmek için görselin yanındaki kutuyu işaretleyip kaydedin; kullanılmayan dosyalar diskten silinir.
- **Adres:** Sipariş formunda açık adres, il (81 il listesi), ilçe ve isteğe bağlı 5 haneli posta kodu ayrı alanlardır; yönetim panelinde ve e-postalarda tek adres metni olarak görünür, il/ilçe/posta kodu ayrıca veritabanında saklanır. Eski siparişler aynen kalır.
- Güncelleme ilk açılışta veritabanını otomatik genişletir (yeni tablo ve sütunlar). Geri alınabilmesi için güncellemeden önce Ayarlar > Yedeği indir ile yedek alın.
