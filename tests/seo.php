<?php
$folder=dirname(__DIR__).'/test-output/seo-'.bin2hex(random_bytes(5));putenv('BLUEVERA_STORAGE='.$folder);
require dirname(__DIR__).'/app/bootstrap.php';
$count=0;function verify($ok,$message){global $count;if(!$ok)throw new RuntimeException($message);$count++;}
setsetting('site_url','https://shop.example.test/magaza');setsetting('sales_enabled','1');
query('INSERT INTO products(name,category,description,price,stock,image,active,demo) VALUES(?,?,?,?,?,?,1,0)',['Türkçe Kulaklık </script>','Kulaklıklar','Net ses ve rahat kullanım.',12345,3,'assets/headphones.jpg']);$id=db()->lastInsertId();
$_GET=['id'=>$id];$s=seoData('product');verify($s['index'],'Real product indexable');verify(str_contains($s['title'],'Türkçe Kulaklık'),'Product title');verify(str_contains($s['canonical'],'urun=turkce-kulaklik-script'),'Readable URL');verify(str_starts_with($s['canonical'],'https://shop.example.test/magaza/'),'Subfolder canonical');verify($s['schema']['offers']['price']==='123.45','Accurate price');verify(str_ends_with($s['schema']['offers']['availability'],'InStock'),'Stock');
ob_start();seoHead('product');$head=ob_get_clean();verify(substr_count($head,'</script>')===1,'Safe JSON markup');
query('UPDATE products SET stock=0 WHERE id=?',[$id]);verify(str_ends_with(seoData('product')['schema']['offers']['availability'],'OutOfStock'),'Out of stock');
foreach(['account','cart','checkout','track','order','pay','admin','admin/orders'] as $page){$s=seoData($page);verify(!$s['index']&&!$s['canonical']&&!$s['schema'],'Private page '.$page);}
$_GET=['q'=>'kulaklık'];verify(!seoData('catalog')['index'],'Search noindex');
$_GET=['id'=>$id];query('UPDATE products SET demo=1 WHERE id=?',[$id]);verify(!seoData('product')['index']&&!seoData('product')['schema'],'Demo excluded');query('UPDATE products SET demo=0 WHERE id=?',[$id]);
query('UPDATE products SET active=0 WHERE id=?',[$id]);verify(!seoData('product')['index'],'Hidden product excluded');query('UPDATE products SET active=1 WHERE id=?',[$id]);
setsetting('site_url','');verify(!seoData('product')['index']&&!seoData('product')['canonical'],'No invented domain');setsetting('site_url','https://shop.example.test/magaza');setsetting('sales_enabled','0');verify(!seoData('product')['index']&&!seoData('product')['schema'],'Preview noindex');setsetting('sales_enabled','1');
file_put_contents(dirname(__DIR__).'/test-output/seo-store-path.txt',$folder);
echo "PASS: $count SEO checks\n";
