<?php
declare(strict_types=1);

/**
 * Eigene Seite je Text-News, serverseitig erzeugt, damit Google Titel, Text, Bild und Datum
 * sofort im HTML sieht (title, description, Open Graph, NewsArticle-Daten).
 */
require __DIR__ . '/news-seo.php';

$ARTISTS = [
    'mats-thiersch'          => ['Mats Thiersch', 'mats-thiersch.html'],
    'gerald-karni'           => ['Gerald Karni', 'gerald-karni.html'],
    'francesco-cagnasso'     => ['Francesco Cagnasso', 'francesco-cagnasso.html'],
    'johannes-pierre-bettac' => ['Johannes Pierre Bettac', 'johannes-pierre-bettac.html'],
    'jose-luis-gutierrez'    => ['José Luis Gutiérrez', 'jose-luis-gutierrez.html'],
    'michael-sanderling'     => ['Michael Sanderling', 'michael-sanderling.html'],
];

date_default_timezone_set('Europe/Berlin');
$id = (string)($_GET['id'] ?? '');
$it = null;
foreach (tvm_articles() as $a) { if ($a['id'] === $id) { $it = $a; break; } }

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-cache');
if (!$it) { http_response_code(404); header('X-Robots-Tag: noindex'); }

$title = $it ? trim((string)($it['title'] ?? '')) : '';
$text  = $it ? (string)($it['text'] ?? '') : '';
$pageTitle = $title !== '' ? $title . ' | TVM artists' : 'News | TVM artists';
$desc  = $it ? tvm_description($text) : 'News from TVM artists.';
$url   = $it ? tvm_article_url($id) : TVM_BASE . '/news.html';
$img   = $it && !empty($it['image']) ? tvm_abs((string)$it['image']) : '';
$ts    = $it ? tvm_iso((string)($it['ts'] ?? '')) : '';
$cats  = $it && is_array($it['cats'] ?? null) ? $it['cats'] : [];
$artist = '';
foreach ($cats as $c) { if (isset($ARTISTS[$c])) { $artist = $c; break; } }

$ld = null;
if ($it) {
    $ld = [
        '@context' => 'https://schema.org',
        '@type' => 'NewsArticle',
        'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $url],
        'headline' => mb_substr($title, 0, 110),
        'datePublished' => $ts,
        'dateModified' => $ts,
        'author' => ['@type' => 'Organization', 'name' => 'TVM artists', 'url' => TVM_BASE . '/'],
        'publisher' => ['@type' => 'Organization', 'name' => 'TVM artists', 'url' => TVM_BASE . '/',
                        'logo' => ['@type' => 'ImageObject', 'url' => TVM_BASE . '/favicon-512.png']],
        'description' => $desc,
    ];
    if ($img !== '') { $ld['image'] = [$img]; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= tvm_e($pageTitle) ?></title>
    <meta name="description" content="<?= tvm_e($desc) ?>">
<?php if ($it): ?>
    <link rel="canonical" href="<?= tvm_e($url) ?>">
    <meta property="og:type" content="article">
    <meta property="og:site_name" content="TVM artists">
    <meta property="og:title" content="<?= tvm_e($title) ?>">
    <meta property="og:description" content="<?= tvm_e($desc) ?>">
    <meta property="og:url" content="<?= tvm_e($url) ?>">
<?php if ($img !== ''): ?>
    <meta property="og:image" content="<?= tvm_e($img) ?>">
    <meta name="twitter:card" content="summary_large_image">
<?php endif; ?>
    <meta property="article:published_time" content="<?= tvm_e($ts) ?>">
    <script type="application/ld+json"><?= json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php endif; ?>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32.png">
    <link rel="icon" type="image/png" sizes="192x192" href="/favicon-192.png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Raleway:wght@300;400;500;600&display=swap');
        *, *::before, *::after { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body { background-color: #1F4270; color: #ffffff; font-family: 'Raleway', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; -webkit-font-smoothing: antialiased; overflow-x: hidden; }
        .article-page { max-width: 760px; margin: 0 auto; padding: 56px 20px 30px; }
        .back-link { display: inline-block; color: #cfe3f5; font-size: 14px; letter-spacing: 0.03em; text-decoration: none; margin-bottom: 26px; }
        .back-link:hover { text-decoration: underline; }
        .article-img { display: block; width: 100%; height: auto; border-radius: 8px; margin: 0 0 18px; }
        .article-date { font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase; color: #cfe3f5; margin: 0 0 22px; }
        h1 { font-weight: 500; font-size: clamp(26px, 4.6vw, 38px); line-height: 1.25; margin: 0 0 24px; }
        .article-text { font-size: 17px; font-weight: 300; line-height: 1.75; letter-spacing: 0.01em; }
        .article-text p { margin: 0 0 1.1em; white-space: pre-line; }
        .empty { text-align: center; padding: 40px 0; color: #cfe3f5; font-weight: 300; }
        .site-footer { width: 100%; text-align: center; padding: 30px 20px 40px; box-sizing: border-box; }
        .footer-rule { width: min(220px, 60vw); height: 1px; margin: 0 auto 40px; background: rgba(255,255,255,0.85); }
        .footer-insta { display: inline-block; line-height: 0; margin-bottom: 28px; transition: opacity 0.25s ease; }
        .footer-insta:hover { opacity: 0.7; }
        .footer-insta img { width: 34px; height: 34px; display: block; }
        .footer-logo-link { display: block; width: 120px; margin: 0 auto 30px; line-height: 0; transition: opacity 0.25s ease; }
        .footer-logo-link:hover { opacity: 0.7; }
        .footer-logo { display: block; width: 100%; height: auto; }
        .footer-copy { font-family: 'Raleway', sans-serif; font-weight: 300; font-size: 13px; letter-spacing: 0.04em; color: rgba(255,255,255,0.9); margin: 0; }
        .footer-copy a { color: rgba(255,255,255,0.9); text-decoration: none; transition: opacity 0.25s ease; }
        .footer-copy a:hover { opacity: 0.7; text-decoration: underline; }
    </style>
    <script src="bg-sync.js?v=2"></script>
    <style>
        /* Hintergrund wandert wie auf den Künstlerseiten durch die Farbtöne (synchron über bg-sync.js) */
        body { position: relative; min-height: 100vh; }
        body::before {
            content: "";
            position: absolute;
            top: 0; left: 0;
            width: 100%; height: 100%;
            z-index: -1;
            pointer-events: none;
            background-color: #17355E;
            animation: bg-breathe 60s ease-in-out infinite;
            animation-delay: var(--bg-delay, 0s);
        }
        @keyframes bg-breathe {
            0%, 100% { background-color: #17355E; }
            25%      { background-color: #2B6C9C; }
            50%      { background-color: #6E78AC; }
            75%      { background-color: #43285A; }
        }
        /* Hinter dem Seitenrand (Überscrollen am oberen/unteren Rand, besonders auf dem Handy): dunkles Brombeer aus dem Farbverlauf statt Blau */
        html { background-color: #43285A; }
        body { background-color: transparent; }
        @media (prefers-reduced-motion: reduce) {
            body::before { animation: none; }
        }
    </style>
</head>
<body>
    <main class="article-page">
<?php if ($artist !== ''): ?>
        <a class="back-link" href="news.html?artist=<?= tvm_e(rawurlencode($artist)) ?>">&larr; <?= tvm_e($ARTISTS[$artist][0]) ?> &ndash; News</a>
<?php else: ?>
        <a class="back-link" href="index.html">&larr; TVM artists</a>
<?php endif; ?>
<?php if (!$it): ?>
        <p class="empty">This news item could not be found.</p>
<?php else: ?>
        <article>
            <h1><?= tvm_e($title) ?></h1>
<?php if ($img !== ''): ?>
            <?php if (!empty($it['webp'])): ?><picture><source type="image/webp" srcset="<?= tvm_e((string)$it['webp']) ?>"><?php endif; ?>
            <img class="article-img" src="<?= tvm_e((string)$it['image']) ?>" alt="<?= tvm_e((string)($it['alt'] ?? $title)) ?>"<?= !empty($it['w']) && !empty($it['h']) ? ' width="' . (int)$it['w'] . '" height="' . (int)$it['h'] . '"' : '' ?><?= tvm_image_crop_style($it) ?>>
            <?php if (!empty($it['webp'])): ?></picture><?php endif; ?>

<?php endif; ?>
            <p class="article-date"><time datetime="<?= tvm_e($ts) ?>"><?= tvm_e((new DateTimeImmutable($ts))->format('j F Y')) ?></time></p>
            <div class="article-text">
<?php $bylineShown = tvm_byline($text); foreach (preg_split('/\n{2,}/', $text) ?: [] as $i => $par): if (trim($par) === '') { continue; } ?>
                <p><?= tvm_linkify($i === 0 && $bylineShown !== '' ? $bylineShown : trim($par)) ?></p>
<?php endforeach; ?>
            </div>
        </article>
<?php endif; ?>
    </main>
    <footer class="site-footer">
        <div class="footer-rule"></div>
        <a href="https://www.instagram.com/tvm.artists" class="footer-insta" target="_blank" rel="noopener" aria-label="TVM artists on Instagram">
            <img src="images/insta-white.png" alt="Instagram">
        </a>
        <a href="https://tvmartists.com/" class="footer-logo-link" aria-label="TVM artists"><img src="images/footer-logo.png" alt="TVM artists" class="footer-logo" loading="lazy"></a>
        <p class="footer-copy">&copy; <?= date('Y') ?> TVM artists &ndash; <a href="imprint.html">imprint</a> &ndash; <a href="imprint.html#cookies">cookie policy</a></p>
    </footer>
</body>
</html>
