<section class="panel" aria-label="İade talebi"><h2>İade / iptal talebi</h2>
<?php $requests=query('SELECT * FROM return_requests WHERE order_id=? ORDER BY id DESC',[$o['id']])->fetchAll();$open=false;foreach($requests as $r):if(in_array($r['status'],['pending','approved'],true))$open=true;?>
<article class="notice"><strong><?=e(returnLabel($r['status']))?></strong><p>Talep tarihi: <?=e($r['created'])?></p><?php if($r['reason']):?><p><?=nl2br(e($r['reason']))?></p><?php endif;if($r['reply']):?><p><strong>Mağazanın yanıtı:</strong><br><?=nl2br(e($r['reply']))?></p><?php endif?></article>
<?php endforeach;if(!$open&&canRequestReturn($o)):?>
<p>Siparişinin tamamı için iade veya teslimat öncesi iptal talebi gönderebilirsin. Talebin mağaza tarafından incelenir; sonucu burada görebilirsin.</p>
<?php startform('return_request','form-grid');?><input type="hidden" name="order_id" value="<?=e($o['id'])?>"><input type="hidden" name="order_key" value="<?=e($o['secret'])?>"><label>Açıklama (isteğe bağlı)<textarea name="reason" maxlength="2000" placeholder="İstersen talebinle ilgili ayrıntıları yazabilirsin."></textarea></label><button class="button dark">İade talebini gönder</button></form>
<?php elseif(!$open&&!$requests):?><p>Bu sipariş için yeni talep açılamıyor. Yardım için mağazayla iletişime geçebilirsin.</p><?php endif?>
<p><small>Talep veya admin onayı otomatik para iadesi yapmaz. İade koşulları ve iletişim: <a href="<?=url('info',['title'=>'İade ve Değişim'])?>">İade ve Değişim</a>.</small></p></section>
