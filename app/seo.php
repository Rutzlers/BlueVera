<?php
function seoBase(): string {
 $base=rtrim(setting('site_url'),'/');$parts=parse_url($base);
 return $parts&&($parts['scheme']??'')==='https'&&!empty($parts['host'])&&!isset($parts['query'])&&!isset($parts['fragment'])&&!isset($parts['user'])?$base:'';
}
function seoSlug(string $text): string {
 $text=strtr(mb_strtolower($text),['ı'=>'i','ğ'=>'g','ü'=>'u','ş'=>'s','ö'=>'o','ç'=>'c']);
 return trim(preg_replace('/[^a-z0-9]+/','-',$text),'-')?:'urun';
}
function seoAbsolute(string $path): string {
 if(preg_match('~^https://~i',$path))return $path;
 return seoBase()?seoBase().'/'.ltrim($path,'/'):'';
}
function seoData(string $page): array {
 $name=shopName();$title=$name.' — Hayatına iyi gelen teknoloji';$description=$name.' mağazasında kulaklıkları, akıllı saatleri ve günlük hayatına eşlik eden teknoloji ürünlerini keşfet.';
 $params=[];$image=shopLogo();$schema=[];$index=in_array($page,['','catalog','product','info'],true);$product=null;
 if($page==='catalog'){
  $category=trim((string)($_GET['category']??''));$title=($category?:'Tüm ürünler').' | '.$name;$description=($category?:'Teknoloji ürünleri').' modellerini, fiyatlarını ve özelliklerini '.$name.' mağazasında incele.';
  if($category!==''){$params['category']=$category;if(!query('SELECT id FROM categories WHERE name=?',[$category])->fetchColumn())$index=false;}
  if(!empty($_GET['sale'])){$params['sale']=1;$title='Fırsatlar | '.$name;}
  if(!empty($_GET['q'])||!empty($_GET['sort']))$index=false;
 }
 if($page==='product'){
  $product=query('SELECT * FROM products WHERE id=? AND active=1',[(int)($_GET['id']??0)])->fetch();
  if($product){$params=['id'=>$product['id']];$title=$product['name'].' | '.$name;$description=mb_substr(trim(preg_replace('/\s+/u',' ',strip_tags($product['description']))),0,160);$image=$product['image'];if($product['demo'])$index=false;}
  else{$title='Ürün bulunamadı | '.$name;$index=false;http_response_code(404);}
 }
 if($page==='info'){
  $t=(string)($_GET['title']??'');$params=['title'=>$t];$title=$t.' | '.$name;$description=mb_substr(trim(preg_replace('/\s+/u',' ',strip_tags(setting('legal_'.$t)))),0,160);
  if(!in_array($t,['Gizlilik Politikası','KVKK','Mesafeli Satış','İade ve Değişim','Çerez Politikası'],true)){$index=false;http_response_code(404);}
 }
 if(!$index&&!in_array($page,['catalog','product','info'],true))$title=(str_starts_with($page,'admin')?'Yönetim':'Hesap ve sipariş işlemleri').' | '.$name;
 $canonical=$index?seoAbsolute(url($page,$params)):'';
 if(!seoBase()||setting('sales_enabled')!=='1')$index=false;
 if($index&&$product){
  $schema=['@context'=>'https://schema.org','@type'=>'Product','name'=>$product['name'],'description'=>$description,'image'=>[seoAbsolute($image)],'sku'=>(string)$product['id'],'url'=>$canonical,'offers'=>['@type'=>'Offer','url'=>$canonical,'priceCurrency'=>'TRY','price'=>number_format($product['price']/100,2,'.',''),'availability'=>'https://schema.org/'.($product['stock']>0?'InStock':'OutOfStock'),'itemCondition'=>'https://schema.org/NewCondition']];
 }
 if($index&&$page==='')$schema=['@context'=>'https://schema.org','@type'=>'Organization','name'=>$name,'url'=>$canonical,'logo'=>seoAbsolute(shopLogo())];
 return compact('title','description','canonical','index','image','schema');
}
function seoHead(string $page): void {
 $s=seoData($page);
 echo '<title>'.e($s['title']).'</title><meta name="description" content="'.e($s['description']).'"><meta name="robots" content="'.($s['index']?'index,follow,max-image-preview:large':'noindex,follow').'">';
 if($s['canonical'])echo '<link rel="canonical" href="'.e($s['canonical']).'">';
 if($s['index']){
 foreach(['og:title'=>$s['title'],'og:description'=>$s['description'],'og:url'=>$s['canonical'],'og:image'=>seoAbsolute($s['image']),'og:site_name'=>shopName(),'og:locale'=>'tr_TR','og:type'=>$page==='product'?'product':'website'] as $key=>$value)echo '<meta property="'.$key.'" content="'.e($value).'">';
 echo '<meta name="twitter:card" content="summary_large_image">';
 }
 if($s['schema'])echo '<script type="application/ld+json">'.json_encode($s['schema'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).'</script>';
}
