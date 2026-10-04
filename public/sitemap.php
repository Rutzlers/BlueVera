<?php
require dirname(__DIR__).'/app/bootstrap.php';
header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
if(seoBase()&&setting('sales_enabled')==='1'){
 $links=[url(),url('catalog')];
 foreach(query('SELECT name FROM categories ORDER BY name')->fetchAll() as $c)$links[]=url('catalog',['category'=>$c['name']]);
 foreach(query('SELECT id FROM products WHERE active=1 AND demo=0 ORDER BY id')->fetchAll() as $p)$links[]=url('product',['id'=>$p['id']]);
 foreach(['Gizlilik Politikası','KVKK','Mesafeli Satış','İade ve Değişim','Çerez Politikası'] as $t)if(setting('legal_'.$t))$links[]=url('info',['title'=>$t]);
 foreach($links as $link)echo '<url><loc>'.htmlspecialchars(seoAbsolute($link),ENT_XML1|ENT_QUOTES,'UTF-8').'</loc></url>';
}
echo '</urlset>';
