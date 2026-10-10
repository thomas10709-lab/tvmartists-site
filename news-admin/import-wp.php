<?php
declare(strict_types=1);

/**
 * Einmaliger Import der News aus WordPress in die eigene Liste (news-data/news.json).
 *
 *   Probelauf (ändert nichts):   php news-admin/import-wp.php
 *   Echter Import:               php news-admin/import-wp.php --run
 *
 * Optionen: --base=https://tvmartists.com   WordPress-Adresse
 *           --docroot=/pfad                 Ordner mit wp-content/uploads (Standard: Webordner)
 *           --limit=10                      Beiträge pro WP-Kategorie
 *
 * Liest WordPress nur (öffentliche Schnittstelle). Bereits importierte Beiträge werden übersprungen.
 */

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require __DIR__ . '/lib.php';

@ini_set('memory_limit', '512M');
date_default_timezone_set('Europe/Berlin');

// WP-Kategorie-ID -> Seite
$MAP = [
    7  => 'home',
    14 => 'mats-thiersch',
    12 => 'johannes-pierre-bettac',
    15 => 'jose-luis-gutierrez',
    11 => 'michael-sanderling',
];
// Kategorien, die über den Namen gesucht werden (ID unbekannt)
$BY_NAME = ['Gerald Karni' => 'gerald-karni'];

// ---------------------------------------------------------------- Argumente
$opt = ['run' => false, 'base' => 'https://tvmartists.com', 'docroot' => dirname(__DIR__), 'limit' => 10];
foreach (array_slice($argv, 1) as $a) {
    if ($a === '--run') { $opt['run'] = true; }
    elseif (preg_match('/^--base=(.+)$/', $a, $m)) { $opt['base'] = rtrim($m[1], '/'); }
    elseif (preg_match('/^--docroot=(.+)$/', $a, $m)) { $opt['docroot'] = rtrim($m[1], '/'); }
    elseif (preg_match('/^--limit=(\d+)$/', $a, $m)) { $opt['limit'] = max(1, (int)$m[1]); }
    else { fwrite(STDERR, "Unbekannte Option: $a\n"); exit(2); }
}
$API  = $opt['base'] . '/wp-json/wp/v2';
$DATA = dirname(__DIR__) . '/news-data';
$IMG  = $DATA . '/img';
$JSON = $DATA . '/news.json';
$PAGES = news_pages();

function out(string $s = ''): void { echo $s . "\n"; }

function http_get(string $url, int $maxBytes = 30 * 1024 * 1024): string
{
    $ctx = stream_context_create(['http' => ['timeout' => 30, 'follow_location' => 1, 'user_agent' => 'tvm-news-import/1.0'],
                                  'ssl'  => ['verify_peer' => true]]);
    $body = @file_get_contents($url, false, $ctx, 0, $maxBytes + 1);
    if ($body === false) { throw new RuntimeException('Abruf fehlgeschlagen: ' . $url); }
    if (strlen($body) > $maxBytes) { throw new RuntimeException('Datei zu groß: ' . $url); }
    return $body;
}

function api(string $url): array
{
    $d = json_decode(http_get($url, 5 * 1024 * 1024), true);
    if (!is_array($d)) { throw new RuntimeException('Keine gültige Antwort von WordPress: ' . $url); }
    return $d;
}

function norm(string $s): string
{
    $s = mb_strtolower(trim(html_entity_decode(strip_tags($s), ENT_QUOTES, 'UTF-8')));
    $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    return $t !== false ? $t : $s;
}

// ---------------------------------------------------------------- Kategorien bestimmen
$catKeys = $MAP;
foreach ($BY_NAME as $name => $key) {
    $needle = explode(' ', $name);
    $found = null;
    foreach (api($API . '/categories?per_page=100&search=' . rawurlencode(end($needle))) as $c) {
        if (norm((string)($c['name'] ?? '')) === norm($name)) { $found = (int)$c['id']; break; }
    }
    if ($found) { $catKeys[$found] = $key; } else { out("Hinweis: Kategorie „$name“ nicht gefunden, wird übersprungen."); }
}

// ---------------------------------------------------------------- Beiträge sammeln
$posts = [];   // wp_id => ['post'=>..., 'cats'=>[...]]
foreach ($catKeys as $catId => $key) {
    $list = api($API . '/posts?categories=' . $catId . '&per_page=' . $opt['limit'] . '&_embed');
    foreach ($list as $p) {
        if (!in_array($catId, (array)($p['categories'] ?? []), true)) { continue; }
        $id = (int)$p['id'];
        $posts[$id]['post'] = $p;
        $posts[$id]['cats'][$key] = $key;
    }
}

$existing = [];
foreach (load_news($JSON) as $it) { if (!empty($it['wp_id'])) { $existing[(int)$it['wp_id']] = true; } }

out(($opt['run'] ? 'ECHTER IMPORT' : 'PROBELAUF (es wird nichts verändert; mit --run wirklich importieren)') . ' – ' . count($posts) . ' Beiträge in WordPress gefunden');
out(str_repeat('-', 78));

ensure_dirs($DATA, $IMG);
$imported = 0; $skipped = 0; $failed = 0;

foreach ($posts as $wpId => $row) {
    $p = $row['post'];
    $title = trim(html_entity_decode(strip_tags((string)($p['title']['rendered'] ?? '')), ENT_QUOTES, 'UTF-8'));
    $cats = array_values($row['cats']);
    $label = implode(', ', array_map(fn($c) => $PAGES[$c] ?? $c, $cats));
    $line = sprintf('#%d  %s  [%s]', $wpId, $title !== '' ? $title : '(ohne Titel)', $label);

    if (isset($existing[$wpId])) { out("überspringe (schon importiert): $line"); $skipped++; continue; }

    $src = (string)($p['_embedded']['wp:featuredmedia'][0]['source_url'] ?? '');
    if ($src === '') { out("überspringe (kein Titelbild): $line"); $skipped++; continue; }

    $gmt = (string)($p['date_gmt'] ?? $p['date'] ?? '');
    try { $ts = (new DateTimeImmutable($gmt, new DateTimeZone('UTC')))->format('c'); } catch (Throwable $e) { $ts = gmdate('c'); }

    // Bild: bevorzugt direkt von der Festplatte (WordPress-Uploads), sonst per Download
    $local = '';
    if (preg_match('#/wp-content/uploads/(.+)$#', (string)parse_url($src, PHP_URL_PATH), $m)) {
        $cand = $opt['docroot'] . '/wp-content/uploads/' . $m[1];
        if (is_file($cand)) { $local = $cand; }
    }
    $how = $local !== '' ? 'Datei auf dem Server' : 'Download';
    out("importiere: $line\n    " . substr($ts, 0, 16) . ' · ' . basename((string)parse_url($src, PHP_URL_PATH)) . " ($how)");
    if (!$opt['run']) { continue; }

    $tmp = '';
    try {
        if ($local === '') {
            $tmp = tempnam(sys_get_temp_dir(), 'tvmimp');
            file_put_contents($tmp, http_get($src, MAX_FILE_BYTES));
            $local = $tmp;
        }
        $id = gmdate('Ymd-His') . '-' . bin2hex(random_bytes(3));
        $res = process_image($local, $IMG . '/' . $id);
        if (!is_array($res)) { throw new RuntimeException($res); }
        $item = [
            'id' => $id, 'ts' => $ts,
            'alt' => $title !== '' ? mb_substr($title, 0, 200) : 'News – ' . $label,
            'link' => '', 'image' => 'news-data/img/' . $id . '.jpg',
            'webp' => $res['webp'] ? 'news-data/img/' . $id . '.webp' : '',
            'w' => $res['w'], 'h' => $res['h'], 'cats' => $cats, 'wp_id' => $wpId,
        ];
        with_lock($DATA, function () use ($JSON, $item) {
            $all = load_news($JSON);
            $all[] = $item;
            save_news($JSON, $all);
        });
        $imported++;
    } catch (Throwable $e) {
        out('    FEHLER: ' . $e->getMessage());
        $failed++;
    } finally {
        if ($tmp !== '' && is_file($tmp)) { @unlink($tmp); }
    }
}

out(str_repeat('-', 78));
if ($opt['run']) {
    out("Fertig: $imported importiert, $skipped übersprungen, $failed fehlgeschlagen.");
    out('Prüfen: https://tvmartists.com/news-admin/');
} else {
    out('Probelauf beendet. Wenn die Liste stimmt: php news-admin/import-wp.php --run');
}
exit($failed > 0 ? 1 : 0);
