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
    $s = trim((string)preg_replace('/\s+/u', ' ', implode(' ', $paras)));
    if (mb_strlen($s) <= $max) { return $s; }
    $cut = mb_substr($s, 0, $max - 1);
    $space = mb_strrpos($cut, ' ');
    if ($space !== false && $space > (int)($max * 0.6)) { $cut = mb_substr($cut, 0, $space); }
    return rtrim($cut, " ,.;:-") . '…';
}
