<?php
declare(strict_types=1);

/** Gemeinsame Hilfen für news-article.php und die Sitemaps (gibt selbst nichts aus). */

const TVM_BASE = 'https://tvmartists.com';

function tvm_e(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

/** Alle Text-News (type=article), neueste zuerst */
function tvm_articles(): array
{
    $file = __DIR__ . '/news-data/news.json';
    $d = is_file($file) ? json_decode((string)file_get_contents($file), true) : [];
    $out = [];
    foreach (is_array($d) ? $d : [] as $it) {
        if (is_array($it) && ($it['type'] ?? '') === 'article'
            && preg_match('/^\d{8}-\d{6}-[a-f0-9]{6}$/', (string)($it['id'] ?? ''))) {
            $out[] = $it;
        }
    }
    usort($out, fn($a, $b) => strcmp((string)($b['ts'] ?? ''), (string)($a['ts'] ?? '')));
    return $out;
}

function tvm_article_url(string $id): string { return TVM_BASE . '/news-article.php?id=' . $id; }

function tvm_abs(string $path): string
{
    return preg_match('#^https?://#i', $path) ? $path : TVM_BASE . '/' . ltrim($path, '/');
}

function tvm_iso(string $ts): string
{
    try { return (new DateTimeImmutable($ts))->format('c'); } catch (Throwable $e) { return gmdate('c'); }
}

/**
 * Beschreibung für Google (Suchergebnis, Open Graph): beginnt mit dem Inhalt der News.
 * Eine kurze Namenszeile am Anfang ("By Name, Funktion, Datum:") wird übersprungen und bleibt nur im Artikeltext.
 * Google zeigt etwa 155 Zeichen; es wird an einer Wortgrenze gekürzt.
 */
function tvm_description(string $text, int $max = 155): string
{
    $paras = preg_split('/\n{2,}/u', trim(str_replace(["\r\n", "\r"], "\n", $text))) ?: [];
    if (count($paras) > 1 && preg_match('/^by\s/iu', trim($paras[0])) && mb_strlen(trim($paras[0])) <= 160) {
        array_shift($paras);
    }
    $s = trim((string)preg_replace('/\s+/u', ' ', tvm_strip_links(implode(' ', $paras))));
    if (mb_strlen($s) <= $max) { return $s; }
    $cut = mb_substr($s, 0, $max - 1);
    $space = mb_strrpos($cut, ' ');
    if ($space !== false && $space > (int)($max * 0.6)) { $cut = mb_substr($cut, 0, $space); }
    return rtrim($cut, " ,.;:-") . '…';
}

/**
 * Text für die Anzeige: HTML wird maskiert, Links werden klickbar.
 * Schreibweise im Text: [Wort](https://…) macht das Wort zum Link; eine nackte Adresse https://… wird ebenfalls zum Link.
 * Nur http/https; Links öffnen in neuem Tab.
 */
function tvm_linkify(string $plain): string
{
    $e = tvm_e($plain);
    $e = preg_replace_callback('/\[([^\]\n]{1,200})\]\((https?:\/\/[^\s)]+)\)/u', function ($m) {
        return '<a href="' . $m[2] . '" target="_blank" rel="noopener noreferrer">' . $m[1] . '</a>';
    }, $e) ?? $e;
    $e = preg_replace_callback('/(?<![">])\bhttps?:\/\/[^\s<]+/u', function ($m) {
        $u = $m[0]; $trail = '';
        while ($u !== '' && preg_match('/[.,;:!?)]$/u', $u)) { $trail = substr($u, -1) . $trail; $u = substr($u, 0, -1); }
        return '<a href="' . $u . '" target="_blank" rel="noopener noreferrer">' . $u . '</a>' . $trail;
    }, $e) ?? $e;
    return $e;
}

/** Link-Schreibweise [Wort](https://…) für Beschreibungen/Auszüge auf das Wort kürzen */
function tvm_strip_links(string $text): string
{
    return (string)preg_replace('/\[([^\]\n]{1,200})\]\(https?:\/\/[^\s)]+\)/u', '$1', $text);
}

