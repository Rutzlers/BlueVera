<?php
function themeDefaults(): array {return ['ink'=>'#183e40','teal'=>'#087e86','sea'=>'#27a6a5','cream'=>'#f7f5eb','paper'=>'#fffefa','line'=>'#e3e8e2','muted'=>'#788682','mint'=>'#e4efdf'];}
function shopName(): string {return setting('shop_name','BlueVera')?:'BlueVera';}
function shopLogo(): string {return setting('shop_logo','assets/favicon.svg')?:'assets/favicon.svg';}
function uploadedImage(string $field,string $fallback): string {
 if(!isset($_FILES[$field])||$_FILES[$field]['error']===UPLOAD_ERR_NO_FILE)return $fallback;
 $f=$_FILES[$field];if($f['error']!==UPLOAD_ERR_OK||$f['size']>5*1024*1024)throw new RuntimeException('Görsel en fazla 5 MB olabilir.');
 $type=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);$ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$type]??null;
 if(!$ext||!getimagesize($f['tmp_name']))throw new RuntimeException('JPG, PNG veya WebP görsel seçin.');
 $path='uploads/'.bin2hex(random_bytes(16)).'.'.$ext;if(!move_uploaded_file($f['tmp_name'],PUBLIC_DIR.'/'.$path))throw new RuntimeException('Görsel yüklenemedi.');return $path;
}
function returnPolicy(): string {return <<<'POLICY'
İADE VE DEĞİŞİM POLİTİKASI

1. Cayma hakkı
6502 sayılı Kanun ve Mesafeli Sözleşmeler Yönetmeliği kapsamındaki tüketici alışverişlerinde teslimden itibaren 14 gün içinde gerekçe veya ceza olmadan cayabilirsiniz. Teslimden önce de cayma mümkündür. Bildiriminizi aşağıdaki e-posta adresine veya yazılı olarak iletebilirsiniz; mağazanın ön onayı şart değildir.

2. Geri gönderim ve ücret iadesi
Bildirimin ardından 14 gün içinde ürünü gönderin; satıcı ürünü kendisi almayı teklif etmişse gönderim gerekmez. Ön bilgilendirmede belirtilen taşıyıcıyla iadede kargo bedeli size yüklenmez. Taşıyıcı belirtilmemişse de iade masrafı size ait değildir. Belirtilen taşıyıcının bulunduğunuz yerde şubesi yoksa ücretsiz alım sağlanır.
Geri ödeme, belirtilen taşıyıcıya teslimden itibaren 14 gün içinde, başka taşıyıcı kullanılmışsa ürünün satıcıya ulaşmasından itibaren 14 gün içinde yapılır. Teslim öncesi caymada süre bildirimle başlar. Teslimat bedeli dahil ödemeler, kullanılan ödeme aracına uygun biçimde, masrafsız ve tek seferde geri verilir.

3. İstisnalar
Kişiye özel üretilmiş ürünler ile koruyucu unsurları açılmış ve sağlık/hijyen nedeniyle iadeye elverişsiz ürünlerde mevzuattaki istisnalar geçerlidir. Her açılmış kutu veya her kulaklık otomatik olarak istisna sayılmaz. Telefon, bilgisayar, tablet ve akıllı saatler sırf ürün türü nedeniyle cayma dışında değildir. Olağan inceleme ve kullanım caymayı ortadan kaldırmaz.

4. Ayıplı ürünler
Ayıplı malda kanuni koşullarla sözleşmeden dönme, bedel indirimi, ücretsiz onarım veya ayıpsız misliyle değişim haklarınız vardır. Bu haklar 14 günlük cayma süresinden bağımsızdır. Kural olarak teslimden itibaren iki yıllık zamanaşımı uygulanır; daha uzun sorumluluk süreleri ve ağır kusur/hile hükümleri saklıdır. Hakların kullanılması nedeniyle doğan masraflar sorumlu tarafa aittir. Kargo tutanağı olmaması tek başına kanuni hakları kaldırmaz.

5. Değişim ve gönderim hazırlığı
Ayıplı olmayan üründe farklı model/renk değişimi stok ve karşılıklı mutabakatla değerlendirilir. Anlaşma sağlanamazsa kanuni cayma hakkınız korunur. Gönderiye sipariş numaranızı ekleyin; aksesuarları birlikte ve taşıma sırasında zarar görmeyecek şekilde paketleyin. Cihazdaki kişisel verilerinizi yedekleyip silin ve kişisel hesap bağlantılarını kaldırın. Bu hazırlık önerileri kanuni hakları ortadan kaldıran ek koşullar değildir.

6. Bildirim örneği
Ad soyad:
Sipariş numarası ve ürün:
Sipariş / teslim tarihi:
“Belirttiğim ürüne ilişkin sözleşmeden cayma hakkımı kullanıyorum.”
İletişim bilgisi ve bildirim tarihi:
Bu örneği kullanmak zorunlu değildir. Talebinizin ve gönderiminizin kaydını saklayın.

7. Başvuru yolları
Uyuşmazlıklarda ilgili yılın parasal sınırına göre Tüketici Hakem Heyetine veya kanuni dava şartları çerçevesinde tüketici mahkemesine başvurabilirsiniz. Politika, tüketicinin emredici mevzuattan doğan haklarını daraltmaz.
POLICY;
}
// Additive, versioned migration: existing products, users, orders and custom texts stay intact.
if(setting('customization_version')!=='1')transaction(function(){
 if(setting('customization_version')==='1')return;
 db()->exec('CREATE TABLE IF NOT EXISTS categories(id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT NOT NULL UNIQUE)');
 query('INSERT OR IGNORE INTO categories(name) SELECT DISTINCT category FROM products WHERE category<>?', ['']);
 $old=setting('legal_İade ve Değişim');if($old===''||str_starts_with($old,'Bu sayfanın içeriği'))setsetting('legal_İade ve Değişim',returnPolicy());
 setsetting('customization_version','1');
});
