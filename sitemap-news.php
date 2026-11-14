<?php
declare(strict_types=1);

/** Google-News-Sitemap: nur Text-News der letzten 2 Tage (Vorgabe von Google). */
require __DIR__ . '/news-seo.php';
header('Content-Type: application/xml; charset=UTF-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$limit = time() - 2 * 86400;
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">
<?php foreach (tvm_articles() as $a): $t = strtotime((string)($a['ts'] ?? '')); if (!$t || $t < $limit || $t > time() + 3600) { continue; } ?>
  <url>
    <loc><?= tvm_e(tvm_article_url($a['id'])) ?></loc>
    <news:news>
      <news:publication><news:name>TVM artists</news:name><news:language>en</news:language></news:publication>
      <news:publication_date><?= tvm_e(tvm_iso((string)$a['ts'])) ?></news:publication_date>
      <news:title><?= tvm_e((string)($a['title'] ?? '')) ?></news:title>
    </news:news>
  </url>
<?php endforeach; ?>
</urlset>
