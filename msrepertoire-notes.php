<?php
declare(strict_types=1);

/**
 * Speicher für die Notizen der Seite michael-sanderling/msrepetroire.html (Notenmaterial pro Werk).
 *
 *   GET   /msrepertoire-notes.php            -> { notes: { <Schluessel>: { t: Text, u: Zeitpunkt } }, editable: bool }
 *   POST  /msrepertoire-notes.php            -> { key, text, password }  speichert (leerer Text loescht die Notiz)
 *
 * Lesen darf jeder, der die Seite kennt. Speichern geht nur mit dem Bearbeiten-Passwort.
 * Einmalig auf dem Server setzen (Passwort steht nur als Hash in news-data/, nicht im Repo):
 *     php msrepertoire-notes.php setpassword 'MeinPasswort'
 * Ohne gesetztes Passwort bleibt die Seite schreibgeschuetzt.
 * Die Daten liegen in news-data/msrepertoire/ (nur auf dem Server, nicht im Repo; ein Deploy ueberschreibt sie nicht).
 */

$DIR = __DIR__ . '/news-data/msrepertoire';
$NOTES = $DIR . '/notes.json';
$HASH = $DIR . '/password.hash';
$FAILS = $DIR . '/fails.json';
$ALLOWED_ORIGINS = ['https://michaelsanderling.de', 'https://www.michaelsanderling.de', 'https://tvmartists.com', 'https://www.tvmartists.com'];
const MAX_TEXT = 2000;
const MAX_FAILS = 10;      // falsche Passwoerter je IP ...
const FAIL_WINDOW = 900;   // ... innerhalb von 15 Minuten

// Ordner anlegen und fuer direkte Aufrufe sperren (Notizen und Passwort-Hash gehen nur ueber dieses Skript)
function ensure_dir(string $dir): bool
{
    $ok = is_dir($dir) || @mkdir($dir, 0755, true) || is_dir($dir);
    if ($ok && !is_file($dir . '/.htaccess')) {
        @file_put_contents($dir . '/.htaccess', "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Order deny,allow\n  Deny from all\n</IfModule>\n");
    }
    return $ok;
}

// ---- Kommandozeile: Passwort setzen ----
if (PHP_SAPI === 'cli') {
    if (($argv[1] ?? '') === 'setpassword' && isset($argv[2]) && strlen($argv[2]) >= 6) {
        if (!ensure_dir($DIR)) { fwrite(STDERR, "Ordner $DIR kann nicht angelegt werden.\n"); exit(1); }
        file_put_contents($HASH, password_hash($argv[2], PASSWORD_DEFAULT));
        @chmod($HASH, 0600);
        echo "Passwort gesetzt.\n";
        exit(0);
    }
    fwrite(STDERR, "Aufruf: php msrepertoire-notes.php setpassword 'Passwort (mind. 6 Zeichen)'\n");
    exit(2);
}

function out(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && in_array($origin, $ALLOWED_ORIGINS, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(204); exit; }

function read_notes(string $file): array
{
    $raw = @file_get_contents($file);
    $j = $raw ? json_decode($raw, true) : null;
    return is_array($j) ? $j : [];
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET') {
    out(['notes' => (object)read_notes($NOTES), 'editable' => is_file($HASH)]);
}
if ($method !== 'POST') out(['error' => 'Method not allowed.'], 405);

// ---- Speichern ----
if (!is_file($HASH)) out(['error' => 'Editing is not set up on the server yet.'], 403);
if (!ensure_dir($DIR)) out(['error' => 'Storage folder is not writable.'], 500);

$in = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($in)) out(['error' => 'Invalid request.'], 400);
$key = (string)($in['key'] ?? '');
$text = trim((string)($in['text'] ?? ''));
$pw = (string)($in['password'] ?? '');
$meta = is_array($in['meta'] ?? null) ? $in['meta'] : [];
if (!preg_match('/^[a-z0-9]{6,40}$/', $key)) out(['error' => 'Invalid work key.'], 400);
if (mb_strlen($text) > MAX_TEXT) out(['error' => 'The comment is too long (max. ' . MAX_TEXT . ' characters).'], 400);

// Passwort pruefen, mit Sperre nach zu vielen Fehlversuchen je IP
$ip = $_SERVER['REMOTE_ADDR'] ?? '?';
$now = time();
$fails = [];
$fraw = @file_get_contents($FAILS);
if ($fraw) { $fj = json_decode($fraw, true); if (is_array($fj)) $fails = $fj; }
foreach ($fails as $k => $list) { $fails[$k] = array_values(array_filter((array)$list, fn($t) => $t > $now - FAIL_WINDOW)); if (!$fails[$k]) unset($fails[$k]); }
if (count($fails[$ip] ?? []) >= MAX_FAILS) out(['error' => 'Too many wrong passwords. Please try again in 15 minutes.'], 429);
if (!password_verify($pw, trim((string)@file_get_contents($HASH)))) {
    $fails[$ip][] = $now;
    @file_put_contents($FAILS, json_encode($fails), LOCK_EX);
    out(['error' => 'Wrong password.'], 403);
}

if (!empty($in['check'])) out(['ok' => true]);   // nur Passwort pruefen (Entsperren auf der Seite), nichts aendern

// Eine Notiz aendern: unter Sperre lesen, aendern, atomar schreiben (zwei gleichzeitige Speichervorgaenge ueberschreiben sich nicht)
$lock = fopen($DIR . '/notes.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX)) out(['error' => 'Could not lock the storage.'], 500);
$notes = read_notes($NOTES);
if ($text === '') unset($notes[$key]);
else $notes[$key] = [
    't' => $text,
    'u' => date('c'),
    'c' => mb_substr((string)($meta['c'] ?? ''), 0, 120),   // Komponist und Titel nur zur Lesbarkeit der Datei
    'w' => mb_substr((string)($meta['w'] ?? ''), 0, 200),
];
$tmp = $NOTES . '.tmp';
if (@file_put_contents($tmp, json_encode($notes, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) === false || !@rename($tmp, $NOTES)) {
    flock($lock, LOCK_UN); fclose($lock);
    out(['error' => 'Could not save.'], 500);
}
flock($lock, LOCK_UN); fclose($lock);
out(['ok' => true, 'u' => $notes[$key]['u'] ?? null]);
