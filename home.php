<?php
declare(strict_types=1);

/**
 * Startseite mit serverseitig eingesetztem "Latest News"-Block.
 * Liest index.html und ersetzt den Platzhalter <!--LATEST_NEWS--> durch die neueste Text-News der Hauptseite.
 * Wird über die .htaccess für die Adresse "/" aufgerufen (siehe server/htaccess-snippet.txt).
 * Bei jedem Fehler wird die Startseite einfach ohne den Block ausgeliefert.
 */
require __DIR__ . '/news-seo.php';

$html = @file_get_contents(__DIR__ . '/index.html');
if ($html === false) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Startseite nicht gefunden.');
}
try { $block = tvm_latest_news_html(); } catch (Throwable $e) { $block = ''; }

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-cache');
echo str_replace('<!--LATEST_NEWS-->', $block, $html);
