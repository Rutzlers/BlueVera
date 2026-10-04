<?php
require dirname(__DIR__).'/app/bootstrap.php';
header('Content-Type: text/plain; charset=utf-8');
echo "User-agent: *\n";
if(!seoBase()||setting('sales_enabled')!=='1')echo "Disallow: /\n";
else echo "Allow: /\nSitemap: ".seoBase()."/sitemap.php\n";
