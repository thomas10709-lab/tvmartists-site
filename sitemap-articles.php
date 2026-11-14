<?php
declare(strict_types=1);

/** Sitemap aller Text-News. Wird in der Search Console eingereicht und aktualisiert sich selbst. */
require __DIR__ . '/news-seo.php';
header('Content-Type: application/xml; charset=UTF-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach (tvm_articles() as $a): ?>
  <url><loc><?= tvm_e(tvm_article_url($a['id'])) ?></loc><lastmod><?= tvm_e(tvm_iso((string)($a['ts'] ?? ''))) ?></lastmod></url>
<?php endforeach; ?>
</urlset>
