<?php
declare(strict_types=1);

/** Liste der Artikel-IDs, die eine deutsche Fassung haben (für die News-Liste: Link "Auf Deutsch"). */
require __DIR__ . '/news-seo.php';
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache');
$ids = [];
foreach (tvm_articles() as $a) { if (tvm_has_de($a)) { $ids[] = (string)$a['id']; } }
echo json_encode($ids);
