<?php
function luminance(string $hex): float {$values=[];foreach([1,3,5] as $pos){$v=hexdec(substr($hex,$pos,2))/255;$values[]=$v<=0.04045?$v/12.92:pow(($v+0.055)/1.055,2.4);}return .2126*$values[0]+.7152*$values[1]+.0722*$values[2];}
function contrastRatio(string $a,string $b): float {$x=luminance($a);$y=luminance($b);return (max($x,$y)+.05)/(min($x,$y)+.05);}
function readableColor(string $preferred,string $background): string {if(contrastRatio($preferred,$background)>=4.5)return $preferred;return contrastRatio('#183e40',$background)>=4.5?'#183e40':(contrastRatio('#ffffff',$background)>=4.5?'#ffffff':'#111111');}
