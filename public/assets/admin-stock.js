const variantBox=document.querySelector('textarea[name="variants"]');
const stockBox=document.querySelector('input[name="stock"]');
const stockExplanation=document.getElementById('stock-explanation');
if(variantBox&&stockBox&&stockExplanation){
 let simpleStock=stockBox.value;let wasVariant=false;
 function updateStock(){const lines=variantBox.value.split(/\r?\n/).map(x=>x.trim()).filter(Boolean);const active=lines.length>0;
 if(active&&!wasVariant)simpleStock=stockBox.value;
 stockBox.readOnly=active;let total=0,valid=true;
 for(const line of lines){const parts=line.includes('|')?line.split('|').map(x=>x.trim()):line.match(/^(.+?)\s+(\d+)$/)?.slice(1);if(!parts||parts.length!==2||!parts[0]||!/^\d+$/.test(parts[1])||Number(parts[1])>100000){valid=false;break;}total+=Number(parts[1]);}
 if(active){stockExplanation.textContent=valid?'Toplam stok: '+total+'. Değiştirmek için her seçeneğin yanındaki adedi düzenleyin.':'Seçeneklerin stoklarını tamamlayın. Örnek: Mavi | 5';if(valid)stockBox.value=String(total);}
 else{if(wasVariant)stockBox.value=simpleStock;stockExplanation.textContent='Seçeneksiz ürün: Stok adedi alanını doğrudan düzenleyebilirsiniz.';}
 wasVariant=active;}
 variantBox.addEventListener('input',updateStock);updateStock();
}
