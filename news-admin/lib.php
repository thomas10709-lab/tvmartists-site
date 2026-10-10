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

