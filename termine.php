<?php
declare(strict_types=1);

/**
 * Zwischenspeicher für die Termin-Feeds (Google-Tabellen über Apps Script).
 *
 * Die Kalender auf tvmartists.com und michaelsanderling.de holen die Termine von hier statt direkt
 * von Google (das ist langsam). Der Feed wird höchstens alle 5 Minuten neu von Google geholt und
 * in news-data/termine/ gespeichert (Ordner liegt nur auf dem Server, nicht im Repo).
 *
 *   /termine.php?a=sanderling   Feed von Michael Sanderling
 *   /termine.php?a=bettac       Feed von Johannes Pierre Bettac
 *
 * Verhalten:
 * - Es wird immer sofort die gespeicherte Fassung ausgeliefert, auch wenn sie älter als 5 Minuten ist;
 *   danach (nach dem Senden der Antwort) wird sie im Hintergrund erneuert. Beim allerersten Aufruf
 *   wird einmalig direkt bei Google geholt.
 * - Ein fehlerhafter Abruf (Fehler, leere Antwort, kein "###EVENT###") überschreibt die gute Fassung nie.
 * - Optional per Cron alle 5 Minuten, dann ist der Speicher auch ohne Besucher aktuell:
 *     php /pfad/zur/termine.php refresh
 */

const TTL_SECONDS = 300;

$FEEDS = [
    'sanderling' => 'https://script.google.com/macros/s/AKfycbwIsVT1iJGDACNdpbtpeK2yUP7W1rugP6uVmX6uinXRyuiIlMFXvsh03JPiE2qdjnq7/exec',
    'bettac'     => 'https://script.google.com/macros/s/AKfycbx-l_QkTurXbeyaGjmR1K7asVBsYnvUhJXUZzq73KiQ7-QEWJOrJr-UQb-MbYkWDaO-/exec',
];
$ALLOWED_ORIGINS = ['https://michaelsanderling.de', 'https://www.michaelsanderling.de', 'https://tvmartists.com', 'https://www.tvmartists.com'];

$dir = __DIR__ . '/news-data/termine';

function cache_file(string $dir, string $key): string { return $dir . '/' . $key . '.txt'; }

/** Holt den Feed bei Google und speichert ihn, falls er brauchbar ist. true = Speicher erneuert. */
function refresh_feed(string $dir, string $key, string $url): bool
{
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) return false;
    // Nur ein Abruf gleichzeitig pro Feed (kein Ansturm auf Google, wenn viele Besucher zugleich kommen)
    $lock = @fopen($dir . '/' . $key . '.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) return false;
    try {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,   // Apps Script leitet weiter
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_USERAGENT      => 'TVM-termine-cache',
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if (!is_string($body) || $code !== 200 || strpos($body, '###EVENT###') === false) return false;
        $tmp = cache_file($dir, $key) . '.tmp' . getmypid();
        if (file_put_contents($tmp, $body) === false) return false;
        return rename($tmp, cache_file($dir, $key));
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

// Aufruf per Cron: alle Feeds erneuern
if (PHP_SAPI === 'cli') {
    if (($argv[1] ?? '') !== 'refresh') { fwrite(STDERR, "Aufruf: php termine.php refresh\n"); exit(1); }
    $bad = 0;
    foreach ($FEEDS as $key => $url) {
        $ok = refresh_feed($dir, $key, $url);
        echo $key . ': ' . ($ok ? 'ok' : 'FEHLER (alte Fassung bleibt)') . "\n";
        if (!$ok) $bad++;
    }
    exit($bad ? 1 : 0);
}

$key = (string)($_GET['a'] ?? '');
if (!isset($FEEDS[$key])) { http_response_code(404); header('Content-Type: text/plain; charset=utf-8'); echo 'Unknown feed'; exit; }

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $ALLOWED_ORIGINS, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
}

$file = cache_file($dir, $key);
$have = is_file($file);
if (!$have) {
    // allererster Aufruf: einmalig direkt holen
    refresh_feed($dir, $key, $FEEDS[$key]);
    $have = is_file($file);
    if (!$have) { http_response_code(502); header('Content-Type: text/plain; charset=utf-8'); echo 'Feed not available'; exit; }
}

$stale = (time() - (int)filemtime($file)) > TTL_SECONDS;
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: public, max-age=60');   // Browser/CDN dürfen kurz zwischenspeichern
$body = (string)file_get_contents($file);
header('Content-Length: ' . strlen($body));
echo $body;

if ($stale) {
    // Antwort ist raus; jetzt im Hintergrund erneuern (der Besucher wartet nicht darauf)
    if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
    else { @ob_end_flush(); flush(); }
    ignore_user_abort(true);
    refresh_feed($dir, $key, $FEEDS[$key]);
}
