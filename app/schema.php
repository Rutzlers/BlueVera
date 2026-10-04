<?php
function initialize(): void {
db()->exec("CREATE TABLE settings(name TEXT PRIMARY KEY,value TEXT NOT NULL);
CREATE TABLE products(id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT NOT NULL,category TEXT NOT NULL,description TEXT NOT NULL,price INTEGER NOT NULL CHECK(price>=0),old_price INTEGER NOT NULL DEFAULT 0,stock INTEGER NOT NULL DEFAULT 0 CHECK(stock>=0),image TEXT NOT NULL,active INTEGER NOT NULL DEFAULT 1,demo INTEGER NOT NULL DEFAULT 1);
CREATE TABLE users(id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT NOT NULL,email TEXT UNIQUE NOT NULL,password TEXT NOT NULL);
CREATE TABLE orders(id TEXT PRIMARY KEY,secret TEXT NOT NULL,user_id INTEGER,name TEXT NOT NULL,email TEXT NOT NULL,phone TEXT NOT NULL,address TEXT NOT NULL,items TEXT NOT NULL,total INTEGER NOT NULL,shipping INTEGER NOT NULL,method TEXT NOT NULL,status TEXT NOT NULL DEFAULT 'pending',tracking TEXT NOT NULL DEFAULT '',created TEXT NOT NULL,expires INTEGER NOT NULL,released INTEGER NOT NULL DEFAULT 0,test_mode INTEGER NOT NULL DEFAULT 0,request_key TEXT UNIQUE NOT NULL);
CREATE TABLE attempts(id TEXT PRIMARY KEY,hits INTEGER NOT NULL,expires INTEGER NOT NULL);");
foreach(['shop_name'=>'BlueVera','shipping'=>'7990','free_shipping'=>'200000','sales_enabled'=>'0','paytr_test'=>'1','bank_enabled'=>'0','cod_enabled'=>'0','card_enabled'=>'0','support_email'=>'','phone'=>'','company'=>'','address'=>'','iban'=>'','bank_name'=>'','bank_owner'=>'','site_url'=>'','legal_ready'=>'0'] as $k=>$v)setsetting($k,$v);
$products=[
['Studio Wireless','Kulaklıklar','Müziğe yer aç. Günlük kullanım için kablosuz, kulak üstü tasarım. Bu kayıt örnek üründür; satış öncesi gerçek ürün bilgileriyle değiştirin.',249900,299900,'headphones.jpg'],
['Air Mini Drone','Drone','Yeni açılardan keşfet. Kompakt drone koleksiyonu için örnek ürün kaydı.',344900,399900,'drone.jpg'],
['Vera Watch','Akıllı Saatler','Günün ritmine eşlik eden sade tasarım. Örnek ürün; teknik özellikleri yönetim panelinden ekleyin.',189900,229900,'watch.jpg'],
['Focus Mirrorless','Kameralar','Anı biriktirenler için. Kamera koleksiyonu örnek ürünü.',849900,0,'camera.jpg'],
['Sound Pocket','Kulaklıklar','Her an yanında. Taşınabilir ses koleksiyonu örnek ürünü.',124900,149900,'earbuds.jpg'],
['Everyday Charge','Şarj Aletleri','Masanızda daha az karmaşa. Şarj aksesuarları için örnek ürün.',69900,89900,'charger.jpg']];
foreach($products as $p)query('INSERT INTO products(name,category,description,price,old_price,stock,image) VALUES(?,?,?,?,?,12,?)',[$p[0],$p[1],$p[2],$p[3],$p[4],'assets/'.$p[5]]);
foreach(['Gizlilik Politikası','KVKK','Mesafeli Satış','İade ve Değişim','Çerez Politikası'] as $t)setsetting('legal_'.$t,'Bu sayfanın içeriği mağaza sahibi tarafından henüz hazırlanmadı. Satışa açılmadan önce işletmenize uygun metni yönetim panelinden ekleyin.');
}
