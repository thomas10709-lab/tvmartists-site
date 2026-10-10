<?php
declare(strict_types=1);

/**
 * News-Verwaltung für tvmartists.com
 * - Bilder hochladen, Kategorien (Hauptseite / Künstlerseiten) wählen, ändern, löschen
 * - Speichert nach ../news-data/ (news.json + img/). Dieser Ordner liegt NICHT im Repo.
 * - Zugang: Passwort (Hash in ../news-data/config.php, siehe Hinweis unten)
 */

const MAX_FILE_BYTES = 25 * 1024 * 1024;   // 25 MB pro Bild
const MAX_PIXELS     = 36000000;           // 36 Megapixel
const MAX_WIDTH      = 1200;               // Breite der gespeicherten Bilder
const MAX_FAILS      = 8;                  // Fehlversuche ...
const FAIL_WINDOW    = 900;                // ... innerhalb von 15 Minuten

$PAGES = [
    'home'                   => 'Hauptseite',
    'mats-thiersch'          => 'Mats Thiersch',
    'gerald-karni'           => 'Gerald Karni',
    'johannes-pierre-bettac' => 'Johannes Pierre Bettac',
    'jose-luis-gutierrez'    => 'José Luis Gutiérrez',
    'michael-sanderling'     => 'Michael Sanderling',
];

$ROOT = dirname(__DIR__);
$DATA = $ROOT . '/news-data';
$IMG  = $DATA . '/img';
$JSON = $DATA . '/news.json';

@ini_set('memory_limit', '512M');

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");

$https = !empty($_SERVER['HTTPS']) || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_name('tvm_news');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Strict']);
session_start();
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function flash(string $type, string $msg): void { $_SESSION['flash'][] = [$type, $msg]; }

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

function client_key(): string
{
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    return hash('sha256', $ip);
}

function fails(string $data): array
{
    $f = $data . '/attempts.json';
    $all = is_file($f) ? (json_decode((string)file_get_contents($f), true) ?: []) : [];
    $now = time();
    foreach ($all as $k => $times) {
        $all[$k] = array_values(array_filter($times, fn($t) => $now - (int)$t < FAIL_WINDOW));
        if (!$all[$k]) { unset($all[$k]); }
    }
    return $all;
}

function register_fail(string $data): void
{
    $all = fails($data);
    $all[client_key()][] = time();
    file_put_contents($data . '/attempts.json', json_encode($all), LOCK_EX);
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

function valid_cats($in, array $pages): array
{
    $out = [];
    foreach ((array)$in as $c) { if (is_string($c) && isset($pages[$c])) { $out[$c] = $c; } }
    return array_values($out);
}

function clean_text(string $s, int $max): string
{
    $s = trim(strip_tags($s));
    $s = preg_replace('/\s+/u', ' ', $s) ?? '';
    return mb_substr($s, 0, $max);
}

function clean_link(string $s): string
{
    $s = trim($s);
    if ($s === '') { return ''; }
    if (!filter_var($s, FILTER_VALIDATE_URL)) { return ''; }
    $scheme = strtolower((string)parse_url($s, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true) ? $s : '';
}

// ---------------------------------------------------------------- Setup prüfen
try { ensure_dirs($DATA, $IMG); } catch (Throwable $ex) { $setupError = $ex->getMessage(); }
$cfgFile = $DATA . '/config.php';
$cfg = is_file($cfgFile) ? (require $cfgFile) : null;
$hasPassword = is_array($cfg) && !empty($cfg['hash']);

$authed = !empty($_SESSION['authed']);
$postTooBig = $_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0;

// ---------------------------------------------------------------- Aktionen
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($postTooBig) {
        flash('err', 'Die Dateien sind zusammen zu groß für den Server (Limit ' . e((string)ini_get('post_max_size')) . '). Bitte weniger Bilder auf einmal hochladen.');
    } elseif (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
        flash('err', 'Die Sitzung ist abgelaufen. Bitte noch einmal versuchen.');
    } else {
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'login') {
            $k = client_key();
            $recent = fails($DATA)[$k] ?? [];
            if (count($recent) >= MAX_FAILS) {
                flash('err', 'Zu viele Fehlversuche. Bitte in 15 Minuten noch einmal probieren.');
            } elseif ($hasPassword && password_verify((string)($_POST['password'] ?? ''), (string)$cfg['hash'])) {
                session_regenerate_id(true);
                $_SESSION['authed'] = true;
                $_SESSION['csrf'] = bin2hex(random_bytes(16));
                $authed = true;
            } else {
                register_fail($DATA);
                sleep(1);
                flash('err', 'Das Passwort stimmt nicht.');
            }
        } elseif ($authed) {
            try {
                if ($action === 'logout') {
                    $_SESSION = [];
                    session_destroy();
                    header('Location: ./');
                    exit;
                }

                if ($action === 'upload') {
                    $cats = valid_cats($_POST['cats'] ?? [], $PAGES);
                    $alt  = clean_text((string)($_POST['alt'] ?? ''), 200);
                    $link = clean_link((string)($_POST['link'] ?? ''));
                    $files = $_FILES['images'] ?? null;
                    if (!$cats) {
                        flash('err', 'Bitte mindestens eine Seite auswählen (zum Beispiel Hauptseite).');
                    } elseif (!$files || !is_array($files['name']) || (count($files['name']) === 1 && $files['name'][0] === '')) {
                        flash('err', 'Bitte mindestens ein Bild auswählen.');
                    } else {
                        $ok = 0;
                        for ($i = 0; $i < count($files['name']); $i++) {
                            $name = (string)$files['name'][$i];
                            $err  = (int)$files['error'][$i];
                            if ($err !== UPLOAD_ERR_OK) {
                                flash('err', $name . ': Upload fehlgeschlagen (Fehler ' . $err . ', evtl. zu groß).');
                                continue;
                            }
                            if ((int)$files['size'][$i] > MAX_FILE_BYTES) {
                                flash('err', $name . ': Datei ist größer als 25 MB.');
                                continue;
                            }
                            $id = gmdate('Ymd-His') . '-' . bin2hex(random_bytes(3));
                            $res = process_image((string)$files['tmp_name'][$i], $IMG . '/' . $id);
                            if (!is_array($res)) {
                                flash('err', $name . ': ' . $res);
                                continue;
                            }
                            $labels = implode(', ', array_map(fn($c) => $PAGES[$c], $cats));
                            $item = [
                                'id'    => $id,
                                'ts'    => gmdate('c'),
                                'alt'   => $alt !== '' ? $alt : 'News – ' . $labels,
                                'link'  => $link,
                                'image' => 'news-data/img/' . $id . '.jpg',
                                'webp'  => $res['webp'] ? 'news-data/img/' . $id . '.webp' : '',
                                'w'     => $res['w'],
                                'h'     => $res['h'],
                                'cats'  => $cats,
                            ];
                            with_lock($DATA, function () use ($JSON, $item) {
                                $all = load_news($JSON);
                                $all[] = $item;
                                save_news($JSON, $all);
                            });
                            $ok++;
                        }
                        if ($ok > 0) { flash('ok', $ok . ($ok === 1 ? ' Bild wurde' : ' Bilder wurden') . ' veröffentlicht.'); }
                    }
                }

                if ($action === 'update' || $action === 'delete') {
                    $id = (string)($_POST['id'] ?? '');
                    if (!preg_match('/^\d{8}-\d{6}-[a-f0-9]{6}$/', $id)) {
                        flash('err', 'Ungültiger Eintrag.');
                    } elseif ($action === 'update') {
                        $cats = valid_cats($_POST['cats'] ?? [], $PAGES);
                        $alt  = clean_text((string)($_POST['alt'] ?? ''), 200);
                        $link = clean_link((string)($_POST['link'] ?? ''));
                        if (!$cats) {
                            flash('err', 'Es muss mindestens eine Seite ausgewählt bleiben. Zum Entfernen bitte „Löschen" nutzen.');
                        } else {
                            with_lock($DATA, function () use ($JSON, $id, $cats, $alt, $link) {
                                $all = load_news($JSON);
                                foreach ($all as &$it) {
                                    if (($it['id'] ?? '') === $id) { $it['cats'] = $cats; $it['link'] = $link; if ($alt !== '') { $it['alt'] = $alt; } }
                                }
                                unset($it);
                                save_news($JSON, $all);
                            });
                            flash('ok', 'Änderung gespeichert.');
                        }
                    } else {
                        if (empty($_POST['confirm'])) {
                            flash('err', 'Zum Löschen bitte das Häkchen „Wirklich löschen" setzen.');
                        } else {
                            with_lock($DATA, function () use ($JSON, $id, $IMG) {
                                $all = array_values(array_filter(load_news($JSON), fn($it) => ($it['id'] ?? '') !== $id));
                                save_news($JSON, $all);
                                @unlink($IMG . '/' . $id . '.jpg');
                                @unlink($IMG . '/' . $id . '.webp');
                            });
                            flash('ok', 'Bild wurde gelöscht.');
                        }
                    }
                }
            } catch (Throwable $ex) {
                flash('err', 'Fehler: ' . $ex->getMessage());
            }
        }
    }
    header('Location: ./');
    exit;
}

$flashes = $_SESSION['flash'] ?? [];
unset($_SESSION['flash']);
$news = $authed ? load_news($JSON) : [];
$csrf = $_SESSION['csrf'];
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>News verwalten</title>
<style>
  :root { --bg:#1F4270; --card:#ffffff; --ink:#1b2a3a; --blue:#12396B; --soft:#cfe3f5; --err:#b3261e; --ok:#1e7a3c; }
  * { box-sizing: border-box; }
  body { margin:0; background:var(--bg); color:#fff; font:16px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif; }
  main { max-width: 860px; margin: 0 auto; padding: 24px 16px 60px; }
  h1 { font-size: 24px; margin: 0 0 4px; font-weight: 600; }
  h2 { font-size: 18px; margin: 0 0 12px; color: var(--blue); }
  .sub { color: var(--soft); margin: 0 0 20px; font-size: 14px; }
  .card { background: var(--card); color: var(--ink); border-radius: 10px; padding: 18px; margin-bottom: 18px; }
  label { display:block; font-weight:600; margin: 12px 0 4px; font-size: 14px; }
  input[type=text], input[type=url], input[type=password], input[type=file] { width:100%; padding:10px; border:1px solid #9bb2cc; border-radius:8px; font-size:16px; background:#fff; color:var(--ink); }
  .cats { display:flex; flex-wrap:wrap; gap:8px 18px; margin-top:6px; }
  .cats label { display:flex; align-items:center; gap:8px; font-weight:400; margin:0; padding:6px 10px; border:1px solid #c5d4e6; border-radius:8px; cursor:pointer; }
  .cats input { width:18px; height:18px; }
  button { background:var(--blue); color:#fff; border:0; border-radius:8px; padding:11px 20px; font-size:16px; cursor:pointer; }
  button:hover { background:#0d2c54; }
  button.danger { background:var(--err); }
  button.light { background:#e7eef7; color:var(--blue); }
  .msg { border-radius:8px; padding:10px 14px; margin-bottom:12px; font-size:15px; }
  .msg.ok { background:#dff3e5; color:var(--ok); }
  .msg.err { background:#fbe3e1; color:var(--err); }
  .hint { font-size:13px; color:#51647a; margin-top:6px; }
  .item { display:grid; grid-template-columns: 110px 1fr; gap:14px; padding:14px 0; border-top:1px solid #e1e8f0; }
  .item:first-of-type { border-top:0; }
  .item img { width:110px; height:138px; object-fit:cover; border-radius:6px; background:#eef2f7; }
  .meta { font-size:13px; color:#51647a; margin-bottom:6px; }
  .row { display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-top:10px; }
  .top { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; }
  .del label { display:inline-flex; align-items:center; gap:6px; font-weight:400; margin:0; }
  @media (max-width:560px) { .item { grid-template-columns: 80px 1fr; } .item img { width:80px; height:100px; } }
</style>
</head>
<body>
<main>
  <div class="top">
    <div>
      <h1>News verwalten</h1>
      <p class="sub">tvmartists.com &ndash; Bilder für die News-Karussells</p>
    </div>
    <?php if ($authed): ?>
    <form method="post"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="logout"><button class="light" type="submit">Abmelden</button></form>
    <?php endif; ?>
  </div>

  <?php foreach ($flashes as [$type, $msg]): ?>
    <div class="msg <?= $type === 'ok' ? 'ok' : 'err' ?>"><?= e($msg) ?></div>
  <?php endforeach; ?>
  <?php if (!empty($setupError)): ?><div class="msg err"><?= e($setupError) ?></div><?php endif; ?>

<?php if (!$hasPassword): ?>
  <div class="card">
    <h2>Passwort noch nicht eingerichtet</h2>
    <p>Bitte einmal im SSH-Fenster des Servers ausführen (das Passwort wird beim Tippen nicht angezeigt):</p>
    <pre style="white-space:pre-wrap;background:#eef2f7;padding:12px;border-radius:8px;font-size:13px">read -s -p "Neues Passwort: " P; echo
H=$(php -r 'echo password_hash($argv[1], PASSWORD_DEFAULT);' "$P")
printf "&lt;?php return ['hash' =&gt; '%s'];\n" "$H" &gt; <?= e($DATA) ?>/config.php
unset P H</pre>
    <p class="hint">Danach diese Seite neu laden.</p>
  </div>

<?php elseif (!$authed): ?>
  <div class="card">
    <h2>Anmelden</h2>
    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <input type="hidden" name="action" value="login">
      <label for="pw">Passwort</label>
      <input id="pw" type="password" name="password" required autofocus>
      <div class="row"><button type="submit">Anmelden</button></div>
    </form>
  </div>

<?php else: ?>
  <div class="card">
    <h2>Neue Bilder hochladen</h2>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <input type="hidden" name="action" value="upload">

      <label for="images">Bilder (JPG, PNG oder WebP; mehrere möglich)</label>
      <input id="images" type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple required>
      <div class="hint">Jedes Bild wird automatisch verkleinert (max. <?= MAX_WIDTH ?> px Breite) und als JPG und WebP gespeichert. Am besten im Hochformat 4:5.</div>

      <label>Auf welchen Seiten soll es erscheinen?</label>
      <div class="cats">
        <?php foreach ($PAGES as $key => $label): ?>
          <label><input type="checkbox" name="cats[]" value="<?= e($key) ?>"> <?= e($label) ?></label>
        <?php endforeach; ?>
      </div>

      <label for="alt">Beschreibung (optional, für Suchmaschinen und Screenreader)</label>
      <input id="alt" type="text" name="alt" maxlength="200" placeholder="z. B. Konzertplakat Lucerne Symphony Orchestra">

      <label for="link">Link (optional, öffnet beim Klick auf das Bild)</label>
      <input id="link" type="url" name="link" placeholder="https://…">

      <div class="row"><button type="submit">Hochladen und veröffentlichen</button></div>
    </form>
  </div>

  <div class="card">
    <h2>Vorhandene News (<?= count($news) ?>)</h2>
    <?php if (!$news): ?><p class="hint">Noch keine eigenen News. Solange hier nichts steht, zeigen die Seiten die alten News aus WordPress.</p><?php endif; ?>
    <?php foreach ($news as $it): $id = (string)($it['id'] ?? ''); $cats = (array)($it['cats'] ?? []); ?>
      <div class="item">
        <img src="../<?= e((string)($it['image'] ?? '')) ?>" alt="">
        <div>
          <div class="meta"><?= e(date('d.m.Y H:i', (int)strtotime((string)($it['ts'] ?? 'now')))) ?> &middot; <?= e(implode(', ', array_map(fn($c) => $PAGES[$c] ?? $c, $cats))) ?></div>
          <form method="post">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="id" value="<?= e($id) ?>">
            <div class="cats">
              <?php foreach ($PAGES as $key => $label): ?>
                <label><input type="checkbox" name="cats[]" value="<?= e($key) ?>" <?= in_array($key, $cats, true) ? 'checked' : '' ?>> <?= e($label) ?></label>
              <?php endforeach; ?>
            </div>
            <label>Beschreibung</label>
            <input type="text" name="alt" maxlength="200" value="<?= e((string)($it['alt'] ?? '')) ?>">
            <label>Link</label>
            <input type="url" name="link" value="<?= e((string)($it['link'] ?? '')) ?>" placeholder="https://…">
            <div class="row">
              <button type="submit" name="action" value="update">Speichern</button>
            </div>
          </form>
          <form method="post" class="del">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="id" value="<?= e($id) ?>">
            <div class="row">
              <label><input type="checkbox" name="confirm" value="1"> Wirklich löschen</label>
              <button class="danger" type="submit" name="action" value="delete">Löschen</button>
            </div>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
</main>
</body>
</html>
