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
    // Zusätze aus dem Repository (news-translations/<id>.json):
    //  - deutsche Fassung (title_de/text_de) für Artikel ohne eigene deutsche Fassung im Admin (eine im Admin gespeicherte hat Vorrang)
    //  - en_replace: [{from, to}] ersetzt im ENGLISCHEN Text genau diese Stelle, sofern sie noch so im Text steht
    //    (steht sie nicht mehr da, z. B. weil der Text im Admin schon geändert wurde, passiert nichts)
    foreach ($out as &$o) {
        $f = __DIR__ . '/news-translations/' . $o['id'] . '.json';
        $t = is_file($f) ? json_decode((string)file_get_contents($f), true) : null;
        if (!is_array($t)) { continue; }
        // "base": "de" = der im Admin gespeicherte Titel/Text ist deutsch (Originalsprache); dann ist Deutsch die Standardsprache der Seite
        // gilt nur, solange im Admin noch der deutsche Originaltitel steht (wurde dort auf Englisch umgestellt, greift die normale deutsche Fassung aus der Datei)
        if (($t['base'] ?? '') === 'de' && trim((string)($o['title'] ?? '')) === trim((string)($t['title_de'] ?? ''))) {
            $o['base'] = 'de';
            $o['title_de'] = (string)($o['title'] ?? '');
            $o['text_de']  = (string)($o['text'] ?? '');
        } elseif (!tvm_has_de($o) && trim((string)($t['title_de'] ?? '')) !== '' && trim((string)($t['text_de'] ?? '')) !== '') {
            $o['title_de'] = (string)$t['title_de'];
            $o['text_de']  = (string)$t['text_de'];
        }
        // weitere Sprachen aus der Datei: title_en/text_en, title_fr/text_fr
        foreach (['en', 'fr'] as $l) {
            if (trim((string)($t['title_' . $l] ?? '')) !== '' && trim((string)($t['text_' . $l] ?? '')) !== '' && !isset($o['title_' . $l])) {
                $o['title_' . $l] = (string)$t['title_' . $l];
                $o['text_' . $l]  = (string)$t['text_' . $l];
            }
        }
        foreach (is_array($t['en_replace'] ?? null) ? $t['en_replace'] : [] as $r) {
            $from = (string)($r['from'] ?? ''); $to = (string)($r['to'] ?? '');
            if ($from !== '' && isset($o['text']) && strpos((string)$o['text'], $from) !== false) { $o['text'] = str_replace($from, $to, (string)$o['text']); }
        }
    }
    unset($o);
    usort($out, fn($a, $b) => strcmp((string)($b['ts'] ?? ''), (string)($a['ts'] ?? '')));
    return $out;
}

/** Adresse eines Artikels; mit $lang = 'de' die deutsche Fassung (englisch ist der Standard ohne Zusatz) */
function tvm_article_url(string $id, string $lang = ''): string
{
    return TVM_BASE . '/news-article.php?id=' . $id . ($lang !== '' ? '&lang=' . $lang : '');
}

/** Originalsprache der Seite: 'en' (Standard) oder 'de' (wenn der gespeicherte Text deutsch ist) */
function tvm_base_lang(array $it): string
{
    return ($it['base'] ?? '') === 'de' ? 'de' : 'en';
}

/** Verfügbare Sprachfassungen in der Reihenfolge EN, DE, FR: Sprache => [Titel, Text] */
function tvm_langs(array $it): array
{
    $base = tvm_base_lang($it);
    $out = [];
    foreach (['en', 'de', 'fr'] as $l) {
        if ($l === $base) {
            $t = trim((string)($it['title'] ?? '')); $x = (string)($it['text'] ?? '');
        } else {
            $t = trim((string)($it['title_' . $l] ?? '')); $x = (string)($it['text_' . $l] ?? '');
        }
        if ($t !== '' && trim($x) !== '') { $out[$l] = [$t, $x]; }
    }
    return $out;
}

/** Adresse der Fassung in der Sprache $lang; die Originalsprache hat die Adresse ohne Zusatz */
function tvm_article_url_for(array $it, string $lang): string
{
    return tvm_article_url((string)$it['id'], $lang === tvm_base_lang($it) ? '' : $lang);
}

/** Hat der Artikel eine zusätzliche deutsche Fassung (Standardsprache Englisch, Titel und Text auf Deutsch eingetragen)? */
function tvm_has_de(array $it): bool
{
    return tvm_base_lang($it) === 'en' && trim((string)($it['title_de'] ?? '')) !== '' && trim((string)($it['text_de'] ?? '')) !== '';
}

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
    if (count($paras) > 1 && preg_match('/^(by|von|par)\s/iu', trim($paras[0])) && mb_strlen(trim($paras[0])) <= 160) {
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

/**
 * Hochformat-Bilder: unten 40 % abschneiden (dort steht oft eingebrannte Schrift) -> es bleiben die oberen 60 %.
 * Gibt das style-Attribut für das <img> zurück (leer bei Querformat oder unbekannter Größe).
 */
function tvm_image_crop_style(array $it): string
{
    $w = (int)($it['w'] ?? 0); $h = (int)($it['h'] ?? 0);
    if ($w <= 0 || $h <= $w) { return ''; }
    return ' style="aspect-ratio:' . $w . ' / ' . (int)round($h * 0.6) . ';object-fit:cover;object-position:50% 0"';
}

/** Neueste Text-News, die auf der Hauptseite erscheinen soll (Kategorie "home") */
function tvm_latest_home_article(): ?array
{
    foreach (tvm_articles() as $a) {
        if (in_array('home', (array)($a['cats'] ?? []), true)) { return $a; }
    }
    return null;
}

/** Namenszeile am Anfang des Textes ("By Name, Funktion, Datum:"), sonst leer */
function tvm_byline(string $text): string
{
    $paras = preg_split('/\n{2,}/u', trim(str_replace(["\r\n", "\r"], "\n", $text))) ?: [];
    $first = trim((string)($paras[0] ?? ''));
    if (count($paras) > 1 && preg_match('/^(by|von|par)\s/iu', $first) && mb_strlen($first) <= 160) {
        return tvm_strip_byline_date((string)preg_replace('/\s+/u', ' ', $first));
    }
    return '';
}

/** Datum am Ende der Namenszeile weglassen: "By …, 14 November 2026:" -> "By …:" (das Datum steht separat auf der Seite) */
function tvm_strip_byline_date(string $line): string
{
    $months = 'January|February|March|April|May|June|July|August|September|October|November|December|Januar|Februar|M\x{00E4}rz|Mai|Juni|Juli|Oktober|Dezember';
    return (string)preg_replace('/,?\s*\d{1,2}\.?\s+(?:' . $months . ')\s+\d{4}(?=\s*:?\s*$)/iu', '', $line);
}

/** Block "Latest News" für die Startseite (serverseitig); leer, wenn es keine passende News gibt */
function tvm_latest_news_html(): string
{
    $a = tvm_latest_home_article();
    if (!$a || empty($a['image'])) { return ''; }
    $id    = (string)$a['id'];
    $url   = 'news-article.php?id=' . rawurlencode($id);
    $title = trim((string)($a['title'] ?? ''));
    $text  = tvm_description((string)($a['text'] ?? ''), 420);
    $by    = tvm_byline((string)($a['text'] ?? ''));
    $alt   = (string)($a['alt'] ?? $title);
    $size  = (!empty($a['w']) && !empty($a['h'])) ? ' width="' . (int)$a['w'] . '" height="' . (int)$a['h'] . '"' : '';
    $crop  = tvm_image_crop_style($a);
    $pic   = '<img src="' . tvm_e((string)$a['image']) . '" alt="' . tvm_e($alt) . '"' . $size . $crop . ' loading="lazy">';
    if (!empty($a['webp'])) {
        $pic = '<picture><source type="image/webp" srcset="' . tvm_e((string)$a['webp']) . '">' . $pic . '</picture>';
    }
    return '<section class="latest-news" aria-labelledby="latest-news-h">'
        . '<h2 class="section-label" id="latest-news-h">Latest News</h2>'
        . '<article class="ln-card">'
        . '<h3 class="ln-title"><a href="' . tvm_e($url) . '">' . tvm_e($title) . '</a></h3>'
        . '<a class="ln-img" href="' . tvm_e($url) . '">' . $pic . '</a>'
        . ($by !== '' ? '<p class="ln-by">' . tvm_e($by) . '</p>' : '')
        . '<p class="ln-text">' . tvm_e($text) . '</p>'
        . '<a class="ln-more" href="' . tvm_e($url) . '">Read more</a>'
        . implode('', array_map(function (string $l) use ($a, $url) {
            $label = ['de' => 'Auf Deutsch lesen', 'fr' => 'Lire en français', 'en' => 'Read in English'][$l] ?? '';
            return $label === '' ? '' : ' <span aria-hidden="true">&nbsp;&middot;&nbsp;</span> <a class="ln-more" lang="' . $l . '" hreflang="' . $l . '" href="' . tvm_e($url . '&lang=' . $l) . '">' . $label . '</a>';
        }, array_values(array_diff(array_keys(tvm_langs($a)), [tvm_base_lang($a)]))))
        . '</article></section>';
}

