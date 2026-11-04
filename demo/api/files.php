<?php
/**
 * The endpoint of the demo's file manager (GridKit\FileManager, 1.125.0). It STORES NOTHING: this is a
 * public site, and an endpoint that keeps what it is handed is a file drop for the whole internet.
 *
 *   GET  ?picture=<n>   one of the demo's sample pictures, drawn here (an SVG without script, served with a
 *                       Content-Security-Policy that would stop one anyway)
 *   POST files[]        an upload: checked by FileManager::receive() with the manager's rules() — the type by
 *                       its content, the name, the size, the dimensions — and answered as js/file-manager.js
 *                       expects: {"ok":true,"file":{…}} or {"ok":false,"message":"…"}. A picture comes back
 *                       as a data: URL so the listing can show it; nothing is moved anywhere, and PHP removes
 *                       its temporary file when this request ends.
 *   POST action=…       delete, move, rename, folder: answered as if done, and nothing is changed.
 *
 * Without the script the forms post here as plain forms, and the answer is a short page that says what the
 * server would have done, with a link back.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../autoload.php';

use GridKit\{FileManager, Lang};

$lang = $_GET['lang'] ?? $_COOKIE['gk_lang'] ?? 'en';
Lang::set(in_array($lang, ['en', 'de'], true) ? $lang : 'en');

// ── A sample picture ────────────────────────────────────────────────────────
if (isset($_GET['picture'])) {
    $n = max(0, min(99, (int) $_GET['picture']));
    $hue = ($n * 47) % 360;
    $hue2 = ($hue + 38) % 360;
    header('Content-Type: image/svg+xml; charset=utf-8');
    header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; sandbox");
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: public, max-age=86400');
    printf('<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="800" viewBox="0 0 1200 800">'
        . '<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="hsl(%d 58%% 66%%)"/>'
        . '<stop offset="1" stop-color="hsl(%d 54%% 40%%)"/></linearGradient></defs>'
        . '<rect width="1200" height="800" fill="url(#g)"/><circle cx="860" cy="230" r="170" fill="#fff" opacity=".18"/>'
        . '<path d="M0 640 L300 420 L520 600 L760 380 L1200 700 L1200 800 L0 800Z" fill="#000" opacity=".16"/></svg>', $hue, $hue2);
    exit;
}

$ajax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
$e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

/** The answer: JSON for the script, a short page for a plain form. */
$answer = static function (int $status, array $body) use ($ajax, $e): never {
    http_response_code($status);
    header('Cache-Control: no-store');
    if ($ajax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
    header('Content-Type: text/html; charset=utf-8');
    $lines = $body['lines'] ?? [(string) ($body['message'] ?? '')];
    echo '<!DOCTYPE html><html lang="' . $e(Lang::locale()) . '"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<meta name="robots" content="noindex"><title>File manager — GridKit Demo</title>'
        . '<style>body{font:15px/1.5 system-ui,sans-serif;max-width:720px;margin:32px auto;padding:0 16px}li{margin:4px 0}</style></head><body>'
        . '<p><a href="../#files">← Back to the demo</a></p><h1>' . $e($body['ok'] ?? false ? 'Checked' : 'Not taken') . '</h1><ul>';
    foreach ($lines as $line) echo '<li>' . $e((string) $line) . '</li>';
    echo '</ul><p>The demo stores nothing. In your application this is where the file is moved (out of the web root) and recorded.</p></body></html>';
    exit;
};

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    $answer(405, ['ok' => false, 'message' => 'This address takes uploads and actions as POST.']);
}
// Larger than post_max_size: PHP dropped the whole request, the token too.
if ($_POST === [] && $_FILES === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    $answer(413, ['ok' => false, 'message' => 'The upload is larger than this server takes.']);
}
// The token the page gave csrf(). A real application compares it with the one in its session (hash_equals).
if (!hash_equals('demo-token', (string) ($_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) {
    $answer(403, ['ok' => false, 'message' => 'The page is out of date. Reload it and try again.']);
}

// ── An upload ───────────────────────────────────────────────────────────────
if (isset($_FILES['files'])) {
    // The same rules the browser was told: the manager of the page, built the same way.
    $rules = ['accept' => ['image/*', 'application/pdf', 'text/csv', '.csv', '.txt'], 'maxSize' => 5 * 1024 * 1024, 'maxFiles' => 10];
    $results = FileManager::receive($_FILES['files'], $rules);   // no target directory: nothing is moved anywhere
    if ($results === []) {
        $answer(400, ['ok' => false, 'message' => Lang::t('files.err_no_file')]);
    }
    if (!$ajax) {
        $answer(200, ['ok' => true, 'lines' => array_map(static fn (array $r): string => (string) $r['message'], $results)]);
    }
    $r = $results[0];
    if (!$r['ok']) {
        $answer(422, ['ok' => false, 'message' => $r['message']]);
    }
    $file = [
        'id'       => 'up-' . substr((string) $r['sha256'], 0, 12),
        'name'     => $r['name'],
        'size'     => $r['size'],
        'mime'     => $r['mime'],
        'modified' => time(),
        'folder'   => is_string($_POST['folder'] ?? null) && $_POST['folder'] !== '' ? $_POST['folder'] : null,
        'width'    => $r['width'],
        'height'   => $r['height'],
    ];
    // A small raster picture travels back as a data: URL, so the listing shows it — nothing is kept here.
    if (in_array($r['mime'], ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true) && $r['size'] <= 400 * 1024) {
        $file['url'] = 'data:' . $r['mime'] . ';base64,' . base64_encode((string) file_get_contents((string) $r['tmp']));
    }
    $answer(200, ['ok' => true, 'file' => $file]);
}

// ── An action ───────────────────────────────────────────────────────────────
$action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
$ids = array_values(array_filter((array) ($_POST['ids'] ?? []), 'is_string'));
$name = is_string($_POST['name'] ?? null) ? trim($_POST['name']) : '';
switch ($action) {
    case 'delete':
    case 'move':
        if ($ids === []) $answer(400, ['ok' => false, 'message' => Lang::t('files.none_selected')]);
        if ($action === 'delete' && in_array('f-contract', $ids, true)) {
            // Refused on purpose, so the way a refusal looks can be seen.
            $answer(409, ['ok' => false, 'message' => 'The contract is kept for ten years and cannot be deleted. Nothing was deleted.']);
        }
        $answer(200, ['ok' => true, 'lines' => [count($ids) . ' files would be ' . ($action === 'delete' ? 'deleted.' : 'moved.')]]);
    case 'rename':
        if (count($ids) !== 1 || $name === '' || mb_strlen($name) > 255 || preg_match('~[/\\\\\x00-\x1f]~', $name)) {
            $answer(422, ['ok' => false, 'message' => 'That name cannot be used.']);
        }
        $answer(200, ['ok' => true, 'file' => ['id' => $ids[0], 'name' => $name], 'lines' => ["Renamed to $name."]]);
    case 'folder':
        if ($name === '' || mb_strlen($name) > 120 || preg_match('~[/\\\\\x00-\x1f]~', $name)) {
            $answer(422, ['ok' => false, 'message' => 'That name cannot be used for a folder.']);
        }
        $answer(200, ['ok' => true, 'folder' => ['id' => 'new-' . bin2hex(random_bytes(4)), 'name' => $name], 'lines' => ["Folder $name would be created."]]);
    case 'download':
        $answer(200, ['ok' => true, 'lines' => [count($ids) . ' files would be sent as a ZIP archive.']]);
}
$answer(400, ['ok' => false, 'message' => 'Unknown action.']);
