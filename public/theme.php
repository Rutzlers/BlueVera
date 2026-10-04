<?php
require dirname(__DIR__).'/app/bootstrap.php';
require ROOT.'/app/contrast.php';
header('Content-Type: text/css; charset=utf-8');
echo ':root{';$palette=[];foreach(themeDefaults() as $key=>$default){$color=setting('color_'.$key,$default);if(!preg_match('/^#[0-9a-fA-F]{6}$/',$color))$color=$default;$palette[$key]=$color;echo '--'.$key.':'.$color.';';}echo '}';
foreach(['body,.panel,.metrics>div,.table-wrap,input,textarea,select,.floating-label'=>'paper','.hero,.auth-panel,.summary,.sidebar,.editorial,footer'=>'cream','.search,.product-photo,.detail-image,.categories a,.admin-layout'=>'mint'] as $selector=>$bg){echo $selector.'{--text-strong:'.readableColor($palette['ink'],$palette[$bg]).';--text-muted:'.readableColor($palette['muted'],$palette[$bg]).';--text-accent:'.readableColor($palette['teal'],$palette[$bg]).';color:var(--text-strong);}';}
echo '.button.dark,.side-link.selected,.topbar{color:'.readableColor('#ffffff',$palette['ink']).';}.button.dark:hover,.side-link.selected:hover{color:'.readableColor('#ffffff',$palette['teal']).';}';
?>
.hero,.auth-panel,.summary,.sidebar,.editorial,footer{background:var(--cream)}
.search,.product-photo,.detail-image,.categories a,.admin-layout{background:var(--mint)}
.panel,.metrics>div,.table-wrap,input,textarea,select,.floating-label{background:var(--paper)}
.hero p,.description,.footer-grid p,.footer-grid>div>a:not(.brand),.hero-foot{color:var(--muted)}
.brand img{object-fit:contain}.brand>span{overflow-wrap:anywhere;max-width:250px}
.color-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:20px}
.color-grid input[type=color]{height:48px;padding:5px;cursor:pointer}.brand-preview{max-width:170px;max-height:100px;object-fit:contain}
/* Readability overrides apply after the base stylesheet, including mobile rules. */
body{font-size:16px;line-height:1.7}h1,h2,h3{font-weight:650;letter-spacing:-.7px}
label,input,textarea,select,.button,.side-link,.filters select{font-size:15px;line-height:1.55}
small,.eyebrow,.pill,.hero-foot,.underlink,.text-button,.breadcrumb,.badge,.status,.demo-note,.footer-tag,.footer-bottom,.product-copy .eyebrow,.section-heading .eyebrow{font-size:12px;line-height:1.65}
.eyebrow{letter-spacing:1px}.brand small{font-size:10px;letter-spacing:1px}.header-links span,.nav .wrap,.search input,.categories strong,.benefits strong,.footer-grid h4{font-size:14px}
.hero p,.description,.footer-grid p,.footer-grid>div>a:not(.brand),.hero-foot,.benefits small,.muted,.summary small,.page-heading p,.auth-panel>p,.setup-steps small,.product-copy .eyebrow,.demo-note,.footer-bottom{color:var(--text-muted)}
.eyebrow,.underlink,.text-button,.stock,.nav a:last-child,.button:not(.dark){color:var(--text-accent)}
.hero p,.description,.legal p,.detail-info,.panel>p,.order-admin p,.auth-panel>p{font-size:16px;line-height:1.8}
.footer-grid>div>a:not(.brand),.footer-grid p,.benefits small,.summary p,.summary small,.floating-label small,.quantity .text-button{font-size:14px}
.product-copy h3{font-size:18px;line-height:1.4}.price-row strong,.cart-item>strong{font-size:17px}.price-row del{font-size:13px;color:var(--text-muted)}
.summary .total{font-size:19px}.product-copy{padding-top:18px}.nav .wrap{gap:22px;overflow-x:auto;justify-content:flex-start}.header{gap:24px}.hero-foot{flex-wrap:wrap}.benefits{gap:18px}.categories a{flex-wrap:wrap}.hero h1 em{color:var(--text-accent)}
.tracking-code{font-family:ui-monospace,Consolas,monospace;font-size:19px;letter-spacing:.6px;overflow-wrap:anywhere;margin:8px 0 16px}
.delivery-steps{display:grid;grid-template-columns:repeat(5,1fr);list-style:none;padding:0;margin:30px 0;gap:12px}.delivery-steps li{border-top:3px solid var(--line);padding-top:16px;display:flex;align-items:center;gap:10px;font-size:14px}.delivery-steps li>span{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border:1px solid currentColor;border-radius:50%;flex-shrink:0}.delivery-steps .complete{border-color:var(--text-accent);color:var(--text-accent)}.delivery-steps [aria-current]{font-weight:700}.order-history summary{cursor:pointer;font-weight:600}.order-history li{margin-top:12px}.order-history time{font-variant-numeric:tabular-nums}.order-history ol{padding-left:23px}
@media(max-width:760px){body{font-size:16px}.header-links span{display:none}.hero h1{font-size:40px}.hero p{font-size:16px}.hero-foot{font-size:12px}.section-heading h2{font-size:24px}.products{gap:20px 14px}.product-copy h3{font-size:16px}.price-row strong{font-size:15px}.price-row del{font-size:13px}.benefits{grid-template-columns:1fr}.benefits strong{font-size:15px}.benefits small{font-size:14px}.footer-grid{grid-template-columns:1fr 1fr}.side-link{font-size:14px}.delivery-steps{grid-template-columns:1fr;gap:10px}.delivery-steps li{border-top:0;border-left:3px solid var(--line);padding:8px 0 8px 14px}.delivery-steps .complete{border-left-color:var(--text-accent)}.tracking-code{font-size:17px}.cart-item{flex-wrap:wrap}.quantity{gap:12px}.quantity label{font-size:14px}.floating-label{left:14px}.section-heading{flex-wrap:wrap}.section-heading>.underlink{margin-top:0}}
