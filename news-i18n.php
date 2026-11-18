<?php
declare(strict_types=1);

/**
 * Titel und Textanfang aller Sprachfassungen je Text-News: {"de": {"<id>": {"t": Titel, "x": Text}}, "fr": {...}, "en": {...}}.
 * Die Listen (News-Seite, Karussell, Latest News) wählen daraus die Browser-Sprache. Auch von michaelsanderling.de abrufbar.
 */
require __DIR__ . '/news-seo.php';
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache');
header('Access-Control-Allow-Origin: *');
$out = [];
foreach (tvm_articles() as $a) {
    foreach (tvm_langs($a) as $l => [$t, $x]) {
        $out[$l][(string)$a['id']] = ['t' => $t, 'x' => mb_substr($x, 0, 900)];
    }
}
echo json_encode((object)$out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
