(function(){var n=document.getElementById('cookie-note'),b=document.getElementById('cookie-ok');if(!n||!b)return;var k='blueveraCookieNote';var seen=false;try{seen=localStorage.getItem(k)==='1';}catch(e){}
if(!seen)n.hidden=false;
b.addEventListener('click',function(){try{localStorage.setItem(k,'1');}catch(e){}n.hidden=true;});})();
