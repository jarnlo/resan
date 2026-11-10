<?php
// Bilder per resdag. Ligger bredvid index.html på Inleed.
//   GET  api.php?action=list                             → {"days": {"3": [{"file", "url", "by": "Anna", "mine": true}]}}
//   POST api.php?action=upload  (code, day, photo, name) → {"ok": true, "file": "..."}
//   POST api.php?action=delete  (code, file)             → {"ok": true}  (bara egna bilder)
// Varje telefon skickar en slumpad nyckel i X-Resan-Owner. Servern sparar bara dess hash per bild,
// och bara samma nyckel får ta bort bilden. Namnet är bara en etikett, inget bevis.
//   POST api.php?action=check   (code)             → {"ok": true}
// Koden ligger som hash i config.php (se config.sample.php).

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

const TOTAL_DAYS = 41;
const MAX_SIDE = 1600;
const MAX_BYTES = 15 * 1024 * 1024;
$dir = __DIR__ . '/uploads';

function out(int $status, array $body): never {
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function codeOk(): bool {
    $cfg = __DIR__ . '/config.php';
    if (!is_file($cfg)) return false;
    $c = require $cfg;
    $code = (string)($_POST['code'] ?? '');
    return $code !== '' && isset($c['code_hash']) && password_verify($code, $c['code_hash']);
}

// Vem som laddat upp vad: meta.php {"fil": {"by": "namn", "owner": "<sha256>"}}.
// Filen börjar med en PHP-rad som avslutar direkt, så den visar ingenting om någon hämtar den via webben.
const META_GUARD = "<?php exit; ?>\n";
function metaFile(): string { return __DIR__ . '/meta.php'; }
function ownerHash(): string {
    $t = (string)($_SERVER['HTTP_X_RESAN_OWNER'] ?? '');
    return preg_match('/^[a-f0-9]{32,64}$/', $t) ? hash('sha256', $t) : '';
}
function meta(string $dir): array {
    $f = metaFile();
    if (!is_file($f)) return [];
    return json_decode(substr((string)file_get_contents($f), strlen(META_GUARD)), true) ?: [];
}
function setMeta(string $dir, string $file, ?array $entry): void {
    $fh = fopen(metaFile(), 'c+');
    if (!$fh) return;
    flock($fh, LOCK_EX);
    $all = json_decode(substr(stream_get_contents($fh) ?: '', strlen(META_GUARD)), true) ?: [];
    if ($entry === null) unset($all[$file]); else $all[$file] = $entry;
    ftruncate($fh, 0); rewind($fh);
    fwrite($fh, META_GUARD . json_encode($all, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    flock($fh, LOCK_UN); fclose($fh);
}

// Filnamn: day-NN-<tid><slump>.jpg – inget från användaren hamnar i sökvägen.
function listDays(string $dir): array {
    $days = [];
    $meta = meta($dir);
    $me = ownerHash();
    foreach (glob($dir . '/day-*.jpg') ?: [] as $path) {
        $f = basename($path);
        if (!preg_match('/^day-(\d{2})-\d+[a-f0-9]*\.jpg$/', $f, $m)) continue;
        $e = $meta[$f] ?? [];
        $days[(string)(int)$m[1]][] = [
            'file' => $f, 'url' => 'uploads/' . $f, 'by' => $e['by'] ?? '',
            'mine' => $me !== '' && hash_equals($e['owner'] ?? '', $me),
        ];
    }
    foreach ($days as &$list) usort($list, fn($a, $b) => strcmp($a['file'], $b['file']));
    return $days;
}

$action = $_GET['action'] ?? '';

if ($action === 'list') {
    out(200, ['days' => (object)listDays($dir)]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(405, ['error' => 'Fel metod.']);

// Långsamma gissningar på koden: liten fördröjning på varje felaktigt försök.
if (!codeOk()) { usleep(800000); out(403, ['error' => 'Fel kod.']); }

if ($action === 'check') out(200, ['ok' => true]);

if ($action === 'upload') {
    if (ownerHash() === '') out(400, ['error' => 'Telefonen saknar nyckel. Ladda om sidan.']);
    $day = (int)($_POST['day'] ?? 0);
    if ($day < 1 || $day > TOTAL_DAYS) out(400, ['error' => 'Ogiltig dag.']);
    $up = $_FILES['photo'] ?? null;
    if (!$up || $up['error'] !== UPLOAD_ERR_OK) out(400, ['error' => 'Ingen bild kom fram.']);
    if ($up['size'] > MAX_BYTES) out(413, ['error' => 'Bilden är för stor.']);

    $info = @getimagesize($up['tmp_name']);
    $type = $info[2] ?? 0;
    $src = match ($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($up['tmp_name']),
        IMAGETYPE_PNG  => @imagecreatefrompng($up['tmp_name']),
        IMAGETYPE_WEBP => @imagecreatefromwebp($up['tmp_name']),
        default        => false,
    };
    if (!$src) out(415, ['error' => 'Det där verkar inte vara en bild (JPEG, PNG eller WebP).']);

    // Vrid rätt efter EXIF om telefonen inte redan gjort det.
    if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $o = (int)(@exif_read_data($up['tmp_name'])['Orientation'] ?? 1);
        if ($o === 3) $src = imagerotate($src, 180, 0);
        if ($o === 6) $src = imagerotate($src, -90, 0);
        if ($o === 8) $src = imagerotate($src, 90, 0);
    }

    // Skala ner och spara om som JPEG: all metadata (GPS m.m.) försvinner.
    $w = imagesx($src); $h = imagesy($src);
    $k = min(1, MAX_SIDE / max($w, $h));
    $nw = (int)round($w * $k); $nh = (int)round($h * $k);
    $dst = imagecreatetruecolor($nw, $nh);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

    if (!is_dir($dir) && !mkdir($dir, 0755, true)) out(500, ['error' => 'Kunde inte skapa mappen för bilder.']);
    $name = sprintf('day-%02d-%d%s.jpg', $day, time(), bin2hex(random_bytes(3)));
    if (!imagejpeg($dst, $dir . '/' . $name, 82)) out(500, ['error' => 'Kunde inte spara bilden.']);
    $by = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', (string)($_POST['name'] ?? '')) ?? '');
    setMeta($dir, $name, ['by' => mb_substr($by, 0, 40), 'owner' => ownerHash()]);
    out(200, ['ok' => true, 'file' => $name, 'url' => 'uploads/' . $name]);
}

if ($action === 'delete') {
    $f = basename((string)($_POST['file'] ?? ''));
    if (!preg_match('/^day-\d{2}-\d+[a-f0-9]*\.jpg$/', $f)) out(400, ['error' => 'Ogiltigt filnamn.']);
    $path = $dir . '/' . $f;
    if (!is_file($path)) out(404, ['error' => 'Bilden finns inte.']);
    $owner = meta($dir)[$f]['owner'] ?? '';
    if (ownerHash() === '' || !hash_equals($owner, ownerHash())) out(403, ['error' => 'Du kan bara ta bort bilder du själv har laddat upp.']);
    // Inget raderas på riktigt: flyttas till uploads/borttagna/ (går att återställa via filhanteraren).
    if (!is_dir($dir . '/borttagna')) mkdir($dir . '/borttagna', 0755, true);
    rename($path, $dir . '/borttagna/' . $f);
    setMeta($dir, $f, null);
    out(200, ['ok' => true]);
}

out(400, ['error' => 'Okänd åtgärd.']);
