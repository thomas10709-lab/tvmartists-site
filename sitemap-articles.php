<?php
declare(strict_types=1);

/** Sitemap aller Text-News. Wird in der Search Console eingereicht und aktualisiert sich selbst. */
require __DIR__ . '/news-seo.php';
header('Content-Type: application/xml; charset=UTF-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
<?php foreach (tvm_articles() as $a): $ls = tvm_langs($a); ?>
<?php foreach ($ls as $l => $_): ?>
  <url><loc><?= tvm_e(tvm_article_url_for($a, $l)) ?></loc><lastmod><?= tvm_e(tvm_iso((string)($a['ts'] ?? ''))) ?></lastmod>
<?php if (count($ls) > 1): foreach ($ls as $l2 => $_2): ?>
    <xhtml:link rel="alternate" hreflang="<?= tvm_e($l2) ?>" href="<?= tvm_e(tvm_article_url_for($a, $l2)) ?>"/>
<?php endforeach; endif; ?>
  </url>
<?php endforeach; ?>
<?php if (!$ls): ?>
  <url><loc><?= tvm_e(tvm_article_url($a['id'])) ?></loc><lastmod><?= tvm_e(tvm_iso((string)($a['ts'] ?? ''))) ?></lastmod></url>
<?php endif; ?>
<?php endforeach; ?>
</urlset>
