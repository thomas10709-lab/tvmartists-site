<?php
declare(strict_types=1);

/** Gemeinsame Funktionen der News-Verwaltung (Webseite und Import-Skript) */

const MAX_FILE_BYTES = 25 * 1024 * 1024;   // 25 MB pro Bild
const MAX_PIXELS     = 36000000;           // 36 Megapixel
const MAX_WIDTH      = 1200;               // Breite der gespeicherten Bilder
const MAX_SHOWN      = 10;                 // so viele Karten zeigt ein Karussell

function news_pages(): array
{
    return [
    'home'                   => 'Hauptseite',
    'mats-thiersch'          => 'Mats Thiersch',
    'gerald-karni'           => 'Gerald Karni',
    'johannes-pierre-bettac' => 'Johannes Pierre Bettac',
    'jose-luis-gutierrez'    => 'José Luis Gutiérrez',
    'michael-sanderling'     => 'Michael Sanderling',

    ];
}

function ensure_dirs(string $data, string $img): void
{
    foreach ([$data, $img] as $d) {
        if (!is_dir($d) && !@mkdir($d, 0755, true) && !is_dir($d)) {
            throw new RuntimeException('Ordner konnte nicht angelegt werden: ' . basename($d));
        }
    }
    $ht = $data . '/.htaccess';
    if (!is_file($ht)) {
        file_put_contents($ht, <<<HT
Options -Indexes
<FilesMatch "\.(php|phtml|phar)$">
  Require all denied
</FilesMatch>
<IfModule mod_expires.c>
  ExpiresActive On
  ExpiresDefault "access plus 1 year"
  ExpiresByType application/json "access plus 0 seconds"
</IfModule>
<IfModule mod_headers.c>
  <FilesMatch "^news\.json$">
    Header set Cache-Control "no-cache"
  </FilesMatch>
</IfModule>

HT);
    }
}

/** Alle Änderungen an news.json unter einer Sperre ausführen */
function with_lock(string $data, callable $fn)
{
    $fh = fopen($data . '/.lock', 'c');
    if (!$fh) { throw new RuntimeException('Sperrdatei nicht möglich.'); }
    flock($fh, LOCK_EX);
    try { return $fn(); } finally { flock($fh, LOCK_UN); fclose($fh); }
}

function load_news(string $file): array
{
    if (!is_file($file)) { return []; }
    $d = json_decode((string)file_get_contents($file), true);
    return is_array($d) ? $d : [];
}

function save_news(string $file, array $items): void
{
    usort($items, fn($a, $b) => strcmp((string)($b['ts'] ?? ''), (string)($a['ts'] ?? '')));
    $tmp = $file . '.tmp' . bin2hex(random_bytes(3));
    $json = json_encode(array_values($items), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if (file_put_contents($tmp, $json) === false || !rename($tmp, $file)) {
        @unlink($tmp);
        throw new RuntimeException('news.json konnte nicht gespeichert werden.');
    }
}

/** Bild lesen, drehen, verkleinern, als JPG (+ WebP) speichern */
function process_image(string $tmp, string $outBase)
{
    $info = @getimagesize($tmp);
    if (!$info) { return 'Das ist keine gültige Bilddatei.'; }
    [$w, $h, $type] = $info;
    if ($w < 1 || $h < 1 || $w * $h > MAX_PIXELS) { return 'Das Bild ist zu groß (mehr als 36 Megapixel).'; }

    switch ($type) {
        case IMAGETYPE_JPEG: $im = @imagecreatefromjpeg($tmp); break;
        case IMAGETYPE_PNG:  $im = @imagecreatefrompng($tmp);  break;
        case IMAGETYPE_WEBP: $im = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmp) : false; break;
        default: return 'Nur JPG, PNG oder WebP sind erlaubt.';
    }
    if (!$im) { return 'Das Bild konnte nicht gelesen werden.'; }

    if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $ex = @exif_read_data($tmp);
        $o = (int)($ex['Orientation'] ?? 1);
        $angle = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
        if ($angle) {
            $rot = imagerotate($im, $angle, 0);
            if ($rot) { $im = $rot; }
        }
    }

    $w = imagesx($im); $h = imagesy($im);
    $nw = min($w, MAX_WIDTH);
    $nh = (int)round($h * $nw / $w);
    $dst = imagecreatetruecolor($nw, $nh);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imageinterlace($dst, true);

    if (!imagejpeg($dst, $outBase . '.jpg', 82)) { return 'Das Bild konnte nicht gespeichert werden.'; }
    $webp = false;
    if (function_exists('imagewebp')) { $webp = @imagewebp($dst, $outBase . '.webp', 80); }
    if (!$webp) { @unlink($outBase . '.webp'); }
    return ['w' => $nw, 'h' => $nh, 'webp' => (bool)$webp];
}

function clean_text(string $s, int $max): string
{
    $s = trim(strip_tags($s));
    $s = preg_replace('/\s+/u', ' ', $s) ?? '';
    return mb_substr($s, 0, $max);
}


/* ------------------------------------------------------------------------
 * Link-Karten: Titel, Auszug, Bild und Quelle einer fremden Seite holen
 * ---------------------------------------------------------------------- */

/** Host darf nur auf öffentliche Adressen zeigen (Schutz vor Zugriffen auf interne Dienste) */
function public_host_ok(string $host): bool
{
    $host = strtolower(trim($host, '[]'));
    if ($host === '' || $host === 'localhost') { return false; }
    $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (array)@gethostbynamel($host);
    if (!$ips) { return false; }
    foreach ($ips as $ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) { return false; }
    }
    return true;
}

/** @return array{0:string,1:string}|string  [Inhalt, Content-Type] oder Fehlertext */
function http_get(string $url, int $maxBytes = 2000000)
{
    for ($hop = 0; $hop < 4; $hop++) {
        $p = parse_url($url);
        $scheme = strtolower((string)($p['scheme'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true) || empty($p['host'])) { return 'Ungültige Adresse.'; }
        if (!public_host_ok((string)$p['host'])) { return 'Diese Adresse ist nicht erlaubt.'; }
        if (!function_exists('curl_init')) { return 'Auf dem Server fehlt curl.'; }

        $body = '';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT        => 12,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; TVMartistsLinkPreview/1.0)',
            CURLOPT_HTTPHEADER     => ['Accept: text/html,image/*;q=0.8,*/*;q=0.5', 'Accept-Language: en,de;q=0.8,fr;q=0.6'],
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_HEADER         => false,
            CURLOPT_WRITEFUNCTION  => function ($c, $chunk) use (&$body, $maxBytes) {
                $body .= $chunk;
                return strlen($body) > $maxBytes ? 0 : strlen($chunk);
            },
        ]);
        $loc = '';
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($c, $h) use (&$loc) {
            if (stripos($h, 'location:') === 0) { $loc = trim(substr($h, 9)); }
            return strlen($h);
        });
        $ok = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $type = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if ($code >= 300 && $code < 400 && $loc !== '') {
            // relative Weiterleitung auflösen
            if (!preg_match('~^https?://~i', $loc)) {
                $base = $scheme . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
                $loc = $loc[0] === '/' ? $base . $loc : $base . '/' . ltrim($loc, '/');
            }
            $url = $loc;
            continue;
        }
        if ($code < 200 || $code >= 300) { return 'Die Seite antwortet nicht (Status ' . $code . ').'; }
        if ($ok === false && strlen($body) === 0) { return 'Die Seite konnte nicht geladen werden.'; }
        return [$body, $type];
    }
    return 'Zu viele Weiterleitungen.';
}

/** Meta-Angaben (Open Graph u. a.) aus HTML lesen */
function parse_link_meta(string $html, string $contentType, string $pageUrl): array
{
    $enc = 'UTF-8';
    if (preg_match('/charset=([\w-]+)/i', $contentType, $m)) { $enc = $m[1]; }
    elseif (preg_match('/<meta[^>]+charset=["\']?([\w-]+)/i', $html, $m)) { $enc = $m[1]; }
    if (strtoupper($enc) !== 'UTF-8') {
        $conv = @mb_convert_encoding($html, 'UTF-8', $enc);
        if (is_string($conv) && $conv !== '') { $html = $conv; }
    }
    $prev = libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);

    $meta = [];
    foreach ($doc->getElementsByTagName('meta') as $mt) {
        $k = strtolower($mt->getAttribute('property') ?: $mt->getAttribute('name'));
        $v = $mt->getAttribute('content');
        if ($k !== '' && $v !== '' && !isset($meta[$k])) { $meta[$k] = $v; }
    }
    $title = $meta['og:title'] ?? $meta['twitter:title'] ?? '';
    if ($title === '') {
        $t = $doc->getElementsByTagName('title')->item(0);
        $title = $t ? $t->textContent : '';
    }
    $desc = $meta['og:description'] ?? $meta['twitter:description'] ?? $meta['description'] ?? '';
    $img  = $meta['og:image'] ?? $meta['twitter:image'] ?? '';
    $site = $meta['og:site_name'] ?? '';
    if ($site === '') { $site = (string)preg_replace('/^www\./i', '', (string)parse_url($pageUrl, PHP_URL_HOST)); }
    if ($img !== '' && !preg_match('~^https?://~i', $img)) {
        $p = parse_url($pageUrl);
        $base = $p['scheme'] . '://' . $p['host'];
        $img = $img[0] === '/' ? $base . $img : $base . '/' . ltrim($img, '/');
    }
    return [
        'title'  => clean_text(html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8'), 200),
        'text'   => clean_text(html_entity_decode($desc, ENT_QUOTES | ENT_HTML5, 'UTF-8'), 320),
        'source' => clean_text(html_entity_decode($site, ENT_QUOTES | ENT_HTML5, 'UTF-8'), 80),
        'image'  => $img,
    ];
}
