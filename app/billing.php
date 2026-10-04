<?php
if(setting('billing_version')!=='1')transaction(function(){
 if(setting('billing_version')==='1')return;
 db()->exec('ALTER TABLE products ADD COLUMN vat_rate INTEGER');
 db()->exec("ALTER TABLE orders ADD COLUMN invoice_json TEXT NOT NULL DEFAULT '{}'");
 setsetting('billing_version','1');
});
function invoiceInput(): array {
 $type=field('invoice_type',20)?:'personal';$company=field('invoice_company',150);$tax=field('tax_number',11);$office=field('tax_office',100);
 if(!in_array($type,['personal','company'],true))throw new RuntimeException('Fatura türü geçersiz.');
 if($type==='company'&&(!$company||!$office||!preg_match('/^\d{10,11}$/',$tax)))throw new RuntimeException('Kurumsal fatura için unvan, vergi dairesi ve vergi numarası gerekli.');
 if($type==='personal'&&$tax!==''&&!preg_match('/^[1-9]\d{10}$/',$tax))throw new RuntimeException('Kimlik numarası 11 haneli olmalıdır; gerekli değilse boş bırakın.');
 return ['type'=>$type,'company'=>$company,'tax_number'=>$tax,'tax_office'=>$office];
}
function vatAmount(array $item): int {return (int)round(($item['price']*$item['qty'])*$item['vat_rate']/(100+$item['vat_rate']));}
