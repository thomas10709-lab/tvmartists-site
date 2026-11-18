<?php
declare(strict_types=1);

/** Zusätzliche Sprachfassungen je Artikel (ohne die Originalsprache der Seite), z. B. {"<id>":["de","fr"]} – für die Links in der News-Liste. */
require __DIR__ . '/news-seo.php';
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache');
$out = [];
foreach (tvm_articles() as $a) {
    $extra = array_values(array_diff(array_keys(tvm_langs($a)), [tvm_base_lang($a)]));
    if ($extra) { $out[(string)$a['id']] = $extra; }
}
echo json_encode((object)$out);
