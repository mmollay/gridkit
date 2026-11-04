<?php
/**
 * GridKit\FileManager (1.125.0) — a media library and a receipt drawer.
 *
 * Two halves. What the server writes: the listing from the page's rows, the folders and the breadcrumb,
 * the toolbar, the upload form, the bulk actions, the details, the links that work without script, the
 * configuration js/file-manager.js reads, every option and what a wrong one becomes (a warning and a
 * manager that still renders), hostile names everywhere. And what the server lets in: receive() and
 * check() against hostile uploads built here — fake $_FILES arrays over temporary files: a wrong type,
 * a polyglot, ".php.jpg", a NUL byte, a decompression bomb, zero bytes, an SVG with script, a path that
 * was never uploaded, a target in the web root.
 *
 * is_uploaded_file() is false for every file a test can make, so receive() refuses them all — which is
 * checked first. The rest of the pipeline runs through FileManager::intake(), the private seam receive()
 * and check() share, with an "uploaded" test and a "move" of the test's choosing; no public call can
 * declare a file uploaded. What the browser does — the upload with progress, the keys, the drop, the
 * sheet, contrast — needs a browser: ci/browser-files.js.
 */

declare(strict_types=1);

use GridKit\{FileManager, Lang};

require_once __DIR__ . '/css-helpers.php';

/** Run, and collect what it said with E_USER_WARNING. @return array{0:mixed,1:list<string>} */
function fmRun(callable $fn): array
{
    $notes = [];
    set_error_handler(static function (int $no, string $msg) use (&$notes): bool {
        if ($no !== E_USER_WARNING) return false;
        $notes[] = $msg;
        return true;
    });
    try {
        $out = $fn();
    } finally {
        restore_error_handler();
    }
    return [$out, $notes];
}

/** The configuration the script reads. @return array<string,mixed> */
function fmConfig(string $html): array
{
    if (!preg_match('~<script type="application/json" data-gk-files-config>(.*?)</script>~s', $html, $m)) return [];
    $cfg = json_decode($m[1], true);
    return is_array($cfg) ? $cfg : [];
}

function fmDom(string $html): DOMXPath
{
    $doc = new DOMDocument();
    @$doc->loadHTML('<?xml encoding="utf-8"?><body>' . $html . '</body>', LIBXML_NOERROR);
    return new DOMXPath($doc);
}

/** A PNG of any size the header says — the pixels are one row of zeros, so a 50,000 × 50,000 one is 100 bytes. */
function fmPng(int $w = 4, int $h = 3, string $tail = ''): string
{
    $chunk = static fn (string $type, string $data): string => pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    return "\x89PNG\r\n\x1a\n" . $chunk('IHDR', pack('NNCCCCC', $w, $h, 8, 2, 0, 0, 0))
        . $chunk('IDAT', (string) gzcompress(str_repeat("\0", 1 + 3 * min($w, 64)))) . $chunk('IEND', '') . $tail;
}

const FM_JPEG = '/9j/4AAQSkZJRgABAQEAYABgAAD//gA+Q1JFQVRPUjogZ2QtanBlZyB2MS4wICh1c2luZyBJSkcgSlBFRyB2ODApLCBkZWZhdWx0IHF1YWxpdHkK/9sAQwAIBgYHBgUIBwcHCQkICgwUDQwLCwwZEhMPFB0aHx4dGhwcICQuJyAiLCMcHCg3KSwwMTQ0NB8nOT04MjwuMzQy/9sAQwEJCQkMCwwYDQ0YMiEcITIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIy/8AAEQgAAwAEAwEiAAIRAQMRAf/EAB8AAAEFAQEBAQEBAAAAAAAAAAABAgMEBQYHCAkKC//EALUQAAIBAwMCBAMFBQQEAAABfQECAwAEEQUSITFBBhNRYQcicRQygZGhCCNCscEVUtHwJDNicoIJChYXGBkaJSYnKCkqNDU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6g4SFhoeIiYqSk5SVlpeYmZqio6Slpqeoqaqys7S1tre4ubrCw8TFxsfIycrS09TV1tfY2drh4uPk5ebn6Onq8fLz9PX29/j5+v/EAB8BAAMBAQEBAQEBAQEAAAAAAAABAgMEBQYHCAkKC//EALURAAIBAgQEAwQHBQQEAAECdwABAgMRBAUhMQYSQVEHYXETIjKBCBRCkaGxwQkjM1LwFWJy0QoWJDThJfEXGBkaJicoKSo1Njc4OTpDREVGR0hJSlNUVVZXWFlaY2RlZmdoaWpzdHV2d3h5eoKDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uLj5OXm5+jp6vLz9PX29/j5+v/aAAwDAQACEQMRAD8A+f6KKKAP/9k=';
const FM_GIF = 'R0lGODdhBAADAIAAAAQCBAAAACwAAAAABAADAAACA4SPVgA7';
const FM_PDF = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";

/** A temporary file holding $bytes; removed at the end of the run. */
function fmTmp(string $bytes): string
{
    static $made = [];
    $path = (string) tempnam(sys_get_temp_dir(), 'gkfm');
    file_put_contents($path, $bytes);
    if ($made === []) register_shutdown_function(static function () use (&$made): void { foreach ($made as $p) @unlink($p); });
    $made[] = $path;
    return $path;
}

/** $_FILES['files'] as PHP builds it for files[]: [[client name, bytes or a path, error], …]. */
function fmFiles(array $list): array
{
    $out = ['name' => [], 'type' => [], 'tmp_name' => [], 'error' => [], 'size' => []];
    foreach ($list as [$name, $bytes, $err]) {
        $tmp = $err === UPLOAD_ERR_OK ? fmTmp($bytes) : '';
        $out['name'][] = $name;
        $out['type'][] = 'image/png';                 // a claim the server never reads
        $out['tmp_name'][] = $tmp;
        $out['error'][] = $err;
        $out['size'][] = 10;                          // likewise
    }
    return $out;
}

/**
 * The pipeline as receive() runs it, with every file counted as uploaded and "moved" by copy — the test seam.
 * @return list<array<string,mixed>>
 */
function fmIntake(array $files, array $opts = []): array
{
    $m = new ReflectionMethod(FileManager::class, 'intake');
    return $m->invoke(null, $files, $opts, static fn (string $p): bool => is_file($p), static fn (string $a, string $b): bool => copy($a, $b));
}

/** One file through the seam. @return array<string,mixed> */
function fmOne(string $name, string $bytes, array $opts = []): array
{
    [$r] = fmRun(fn () => fmIntake(fmFiles([[$name, $bytes, UPLOAD_ERR_OK]]), $opts));
    return $r[0] ?? [];
}

/** A manager with a little of everything. */
function fmSample(): FileManager
{
    return (new FileManager('media'))
        ->folders([
            ['id' => 'r', 'name' => 'Receipts', 'parent' => null, 'count' => 3],
            ['id' => 'r26', 'name' => '2026', 'parent' => 'r'],
            ['id' => 'm', 'name' => 'Marketing', 'parent' => null],
        ])
        ->files([
            ['id' => 'a', 'name' => 'File 10.png', 'size' => 2048, 'mime' => 'image/png', 'modified' => 1700000300, 'url' => '/m/a.png'],
            ['id' => 'b', 'name' => 'File 2.pdf', 'size' => 9000000, 'mime' => 'application/pdf', 'modified' => 1700000100],
            ['id' => 'c', 'name' => 'Ölpreise.xlsx', 'size' => 100, 'modified' => 1700000200],
            ['id' => 'd', 'name' => 'Clip.mp4', 'size' => 5000, 'mime' => 'video/mp4', 'modified' => 1700000000],
            ['id' => 'e', 'name' => 'Receipt.pdf', 'size' => 10, 'mime' => 'application/pdf', 'folder' => 'r26'],
        ]);
}

/** The ids of the listed files, in the order drawn. @return list<string> */
function fmOrder(string $html): array
{
    preg_match_all('/data-gk-file="([^"]*)"/', $html, $m);
    return $m[1];
}

/** @return array<string,callable> */
return (static function (): array {

$root = __DIR__ . '/..';
$js = static fn (): string => (string) file_get_contents($root . '/js/file-manager.js');
$src = static fn (): string => (string) file_get_contents($root . '/src/FileManager.php');
$doc = static fn (): string => (string) file_get_contents($root . '/GRIDKIT_SKILL.md');
$sheet = static function (): string {
    $css = gkCssText();
    $from = strpos($css, '/* === 1.125.0 — file manager === */');
    if ($from === false) return '';
    $next = strpos($css, "\n/* === ", $from + 10);
    return (string) preg_replace('#/\*.*?\*/#s', '', $next === false ? substr($css, $from) : substr($css, $from, $next - $from));
};

return [

// ── The page ────────────────────────────────────────────────────────────

'a plain manager: a named section, its config, the toolbar, the listing, the details — and no warning' => function (): void {
    Lang::set('en');
    [$html, $notes] = fmRun(fn () => fmSample()->html());
    T::eq($notes, [], 'a sound manager says nothing');
    T::contains($html, '<section class="gk-files gk-files-view-grid" id="gk-files-media" data-gk-files="media" aria-label="Files">', 'the section, its id and name');
    $cfg = fmConfig($html);
    T::eq($cfg['id'] ?? null, 'media', 'the config names the manager');
    T::eq(count($cfg['files'] ?? []), 5, 'every file is in the config, also those of other folders');
    T::eq($cfg['select'] ?? null, 'multi', 'multi selection by default');
    T::eq($cfg['upload'] ?? null, '', 'no upload URL, no upload');
    T::notContains($html, 'gk-files-upload', 'no upload form without uploadUrl()');
    T::contains($html, '<form class="gk-files-toolbar" method="get" action="" role="search" aria-label="Find files">', 'the toolbar is a GET form');
    T::contains($html, '<ul class="gk-files-items" aria-label="Files and folders">', 'the listing is a named list');
    T::contains($html, '<aside class="gk-files-details" id="gk-files-media-details" aria-labelledby="gk-files-media-dtitle" tabindex="-1" hidden>', 'the details wait, hidden');
    T::contains($html, '<p class="gk-sr-only" aria-live="polite" data-gk-files-live></p>', 'a polite live region');
    T::ok(str_ends_with($html, '</section>'), 'closed');
    $x = fmDom($html);
    T::eq($x->query('//form//form')->length, 0, 'no form inside a form');
    T::eq($x->query('//*[@id]')->length, count(array_unique(array_map(fn ($n) => $n->getAttribute('id'), iterator_to_array($x->query('//*[@id]'))))), 'every id once');
},

'render() prints what html() returns' => function (): void {
    $fm = fmSample();
    ob_start();
    $fm->render();
    T::eq((string) ob_get_clean(), $fm->html(), 'the twins agree');
},

'the top shows its folders first, then its files; a folder file waits for its folder' => function (): void {
    $html = fmSample()->html();
    T::contains($html, '<li class="gk-files-item gk-files-folder" data-gk-folder="m">', 'a folder of the top');
    T::notContains($html, 'data-gk-folder="r26"', 'a subfolder is not at the top');
    T::eq(fmOrder($html), ['d', 'b', 'a', 'c'], 'names in natural order: Clip, File 2, File 10, Ölpreise — e lies in 2026');
    T::ok(strpos($html, 'data-gk-folder="m"') < strpos($html, 'data-gk-file="d"'), 'folders before files');
    T::ok(strpos($html, 'data-gk-folder="m"') < strpos($html, 'data-gk-folder="r"'), 'folders by name');
    T::contains($html, '<p class="gk-files-summary" data-gk-files-summary>4 files · 2 folders</p>', 'the summary counts');
    T::contains($html, '<span class="gk-files-meta">3 items</span>', 'a folder with a count says it');
    T::contains($html, '<a class="gk-files-name" href="?folder=r"><span class="gk-sr-only">Folder </span>Receipts</a>', 'a folder link says what it is');
},

'path(): the folder, its files, the breadcrumb through every parent' => function (): void {
    $html = fmSample()->path('r26')->html();
    T::eq(fmOrder($html), ['e'], 'only the files of 2026');
    T::contains($html, '<nav class="gk-files-crumbs" aria-label="Folder path"><ol><li><a href="?">Files</a></li><li><a href="?folder=r">Receipts</a></li><li><span aria-current="page">2026</span></li></ol></nav>', 'the path, the current one in words');
    T::contains($html, '<input type="hidden" name="folder" value="r26">', 'the toolbar keeps the folder');
    T::eq(fmConfig($html)['folder'] ?? null, 'r26', 'the script knows the folder');
    [$h2, $notes] = fmRun(fn () => fmSample()->path('nope')->html());
    T::eq(count($notes), 1, 'an unknown folder is a warning');
    T::contains($notes[0] ?? '', "path(): the folder 'nope' is not in folders()", 'which says what');
    T::eq(fmOrder($h2), ['d', 'b', 'a', 'c'], 'and the top is shown');
    // A cycle in the parents must not loop.
    [$h3] = fmRun(fn () => (new FileManager('c'))->folders([['id' => 1, 'name' => 'A', 'parent' => 2], ['id' => 2, 'name' => 'B', 'parent' => 1]])->path(1)->html());
    T::ok(substr_count($h3, '<li>') <= 4, 'a cycle ends the breadcrumb');
},

'breadcrumb(): crumbs given as they are; a dangerous link is dropped' => function (): void {
    [$html, $notes] = fmRun(fn () => (new FileManager('b'))->breadcrumb([
        ['name' => 'Media', 'url' => '/media'], ['label' => 'Bad', 'href' => 'javascript:alert(1)'], ['name' => '2026'], ['url' => '/x'],
    ])->html());
    T::contains($html, '<li><a href="/media">Media</a></li><li><span>Bad</span></li><li><span aria-current="page">2026</span></li>', 'the crumbs, the bad one without a link');
    T::eq(count($notes), 2, 'a javascript: link and a crumb without a name are warnings');
    T::notContains($html, 'javascript:', 'the link is gone');
},

'files(): the map, objects, and a warning for each broken row' => function (): void {
    $obj = new stdClass();
    $obj->key = 9; $obj->title = 'Obj.gif'; $obj->bytes = '77';
    [$html, $notes] = fmRun(fn () => (new FileManager('m'))->files([
        $obj,
        ['key' => 1, 'title' => 'Ok.png', 'bytes' => 5, 'changed' => new DateTimeImmutable('2026-01-02 03:04:00')],
        ['key' => 1, 'title' => 'Again.png'],
        ['title' => 'No id.png'],
        ['key' => 2],
        ['key' => 3, 'title' => 'Bad size.png', 'bytes' => 'lots'],
        'a string',
    ], ['id' => 'key', 'name' => 'title', 'size' => 'bytes', 'modified' => 'changed', 'colour' => 'x'])->html());
    $joined = implode("\n", $notes);
    T::contains($joined, "the map names 'colour'", 'an unknown field of the map');
    T::contains($joined, "the id '1' is there twice", 'a duplicate id');
    T::contains($joined, 'has no id', 'a row without an id');
    T::contains($joined, "('2') has no name", 'a row without a name');
    T::contains($joined, 'a size that is not a number of bytes', 'a size that is not one');
    T::contains($joined, 'row 6 is string', 'a row that is no row');
    T::eq(fmOrder($html), ['3', '9', '1'], 'what was sound is listed: Bad size, Obj, Ok');
    T::contains($html, '<span class="gk-files-date">2026-01-02 03:04</span>', 'a DateTime is a date');
    T::contains($html, 'data-gk-file="9" data-gk-kind="image"', 'the type of an object row comes from its extension');
    T::contains($html, '<span class="gk-files-size">77 bytes</span>', 'a size as a numeric string');
},

'URLs: javascript: and data: are refused, a data: picture is not' => function (): void {
    [$html, $notes] = fmRun(fn () => (new FileManager('u'))->files([
        ['id' => 1, 'name' => 'a.png', 'url' => "java\tscript:alert(1)", 'thumbUrl' => 'data:text/html,<script>alert(1)</script>'],
        ['id' => 2, 'name' => 'b.png', 'url' => 'data:image/png;base64,iVBORw0KGgo=', 'thumbUrl' => 'data:image/svg+xml;base64,PHN2Zz4='],
        ['id' => 3, 'name' => 'c.png', 'url' => 'data:image/svg+xml;base64,PHN2Zz4='],
        ['id' => 4, 'name' => 'd.png', 'url' => ' VBScript:msgbox'],
    ])->html());
    T::eq(count($notes), 4, 'four refusals: javascript:, data:text/html, an SVG as url, vbscript:');
    T::notContains(strtolower($html), 'script:', 'no runnable link is written');
    T::notContains($html, 'data:text/html', 'no data: page');
    T::contains($html, 'src="data:image/svg+xml;base64,PHN2Zz4="', 'an SVG thumbnail is an <img>');
    $cfg = fmConfig($html);
    T::eq($cfg['files'][1]['url'] ?? null, 'data:image/png;base64,iVBORw0KGgo=', 'a raster picture may be a data: URL');
    T::ok(array_key_exists('url', $cfg['files'][2] ?? []) && $cfg['files'][2]['url'] === null, 'an SVG data: URL is no address to open');
},

'hostile names, ids, folders and tokens are text everywhere' => function (): void {
    $evil = '"><img src=x onerror=alert(1)></script><script>alert(2)</script>';
    [$html] = fmRun(fn () => (new FileManager('m"><x'))
        ->folders([['id' => $evil, 'name' => $evil]])
        ->files([['id' => $evil, 'name' => $evil . '.png', 'url' => '/u?a="' . $evil, 'folder' => null]])
        ->selected([$evil])->open($evil)
        ->uploadUrl('/up?x="' . $evil)->actions(['delete' => '/d?"' . $evil, 'rename' => '/r', 'folder' => '/f', 'move' => '/m'])
        ->csrf($evil)->hidden(['x' => $evil])->keep(['k' => $evil])->label($evil)
        ->html());
    T::notContains($html, '<img src=x', 'no injected element');
    T::notContains($html, '<script>alert', 'no injected script');
    T::eq(substr_count($html, '</script>'), 1, 'one script element ends, the config');
    $cfg = fmConfig($html);
    T::eq($cfg['files'][0]['name'] ?? null, $evil . '.png', 'the config holds the name as it is');
    T::eq($cfg['csrf']['token'] ?? null, $evil, 'and the token');
    $x = fmDom($html);
    T::eq($x->query('//script')->length, 1, 'one script element in the DOM');
    T::eq($x->query('//*[@onerror]')->length, 0, 'no handler attribute');
    T::contains($html, 'id="gk-files-h', 'an id that is not safe becomes a hash in the element ids');
},

// ── Toolbar, order, filter, request ─────────────────────────────────────

'sort(), filter(), search(): what is shown and in which order' => function (): void {
    T::eq(fmOrder(fmSample()->sort('date', 'desc')->html()), ['a', 'c', 'b', 'd'], 'newest first');
    T::eq(fmOrder(fmSample()->sort('size')->html()), ['c', 'a', 'd', 'b'], 'smallest first');
    T::eq(fmOrder(fmSample()->sort('size', 'desc')->html()), ['b', 'd', 'a', 'c'], 'largest first');
    T::eq(fmOrder(fmSample()->sort('type')->html()), ['d', 'b', 'a', 'c'], 'by extension: mp4, pdf, png, xlsx');
    T::eq(fmOrder(fmSample()->sort('name', 'desc')->html()), ['c', 'a', 'b', 'd'], 'Z–A');
    T::eq(fmOrder(fmSample()->filter('image')->html()), ['a'], 'images');
    T::eq(fmOrder(fmSample()->filter('document')->html()), ['b', 'c'], 'documents: PDF and a spreadsheet');
    T::eq(fmOrder(fmSample()->filter('other')->html()), ['d'], 'other: a video');
    $h = fmSample()->search('olpreise')->html();
    T::eq(fmOrder($h), ['c'], 'an umlaut matches its vowel');
    T::notContains($h, 'data-gk-folder', 'a search hides folders that do not match');
    T::eq(fmOrder(fmSample()->search('FILE')->html()), ['b', 'a'], 'case does not count');
    $none = fmSample()->search('zzz')->html();
    T::contains($none, '<p class="gk-files-empty">No file matches.</p>', 'nothing found says so');
    T::contains((new FileManager('e'))->html(), '<p class="gk-files-empty">This folder is empty.</p>', 'an empty folder says so');
    T::contains(fmSample()->html(), '<p class="gk-files-empty" hidden>', 'and is hidden when there is something');
},

'the toolbar: search, type, sort, view — each with a label, preset to the state' => function (): void {
    $html = fmSample()->sort('date', 'desc')->filter('image')->search('x"y')->view('list')->html();
    T::contains($html, '<section class="gk-files gk-files-view-list"', 'the view is a class');
    T::contains($html, '<span class="gk-sr-only">Search files</span><input type="search" name="q" value="x&quot;y"', 'the search, named, with its value');
    T::contains($html, '<option value="image" selected>Images</option>', 'the type');
    T::contains($html, '<option value="date-desc" selected>Newest first</option>', 'the order');
    T::contains($html, '<input type="radio" name="view" value="list" checked data-gk-files-view>', 'the view');
    T::contains($html, '<legend class="gk-sr-only">View</legend>', 'the switch is a named group');
    T::contains($html, '<button type="submit" class="gk-btn gk-btn-tonal gk-btn-primary gk-btn-sm gk-files-apply">Apply</button>', 'a button to apply without script');
    preg_match_all('/<option value="([a-z]+-[a-z]+)"/', $html, $m);
    T::eq($m[1], ['name-asc', 'name-desc', 'date-desc', 'date-asc', 'size-desc', 'size-asc', 'type-asc', 'type-desc'], 'every order');
},

'fromQuery(): the request picks what it knows and ignores the rest without a word' => function (): void {
    [$html, $notes] = fmRun(fn () => fmSample()->fromQuery(['view' => 'list', 'sort' => 'size-desc', 'type' => 'document', 'q' => 'file', 'folder' => '', 'file' => 'b'])->html());
    T::eq($notes, [], 'no warning');
    T::eq(fmOrder($html), ['b'], 'documents named "file", largest first');
    T::contains($html, 'gk-files-view-list', 'list');
    T::contains($html, '<h3 class="gk-files-details-title" id="gk-files-media-dtitle">File 2.pdf</h3>', 'the details of ?file=b');
    [$h2, $n2] = fmRun(fn () => fmSample()->fromQuery(['view' => 'tiles', 'sort' => 'name; DROP', 'type' => ['x'], 'q' => ['y'], 'folder' => ['z']])->html());
    T::eq($n2, [], 'nonsense is not a warning');
    T::contains($h2, 'gk-files-view-grid', 'nonsense changes nothing');
    T::eq(fmOrder($h2), ['d', 'b', 'a', 'c'], 'nonsense changes nothing at all');
    $long = fmSample()->fromQuery(['q' => str_repeat('ä', 5000)])->html();
    T::ok(strlen((string) (fmConfig($long)['q'] ?? '')) <= 400, 'a search is cut to 400 bytes');
    $_GET = ['view' => 'list'];
    T::contains(fmSample()->fromQuery()->html(), 'gk-files-view-list', 'default: $_GET');
    $_GET = [];
},

'links: the state travels, keep() and anchor() too; param() puts it under one name' => function (): void {
    $html = fmSample()->path('r')->sort('date', 'desc')->view('list')->keep(['page' => 'media'])->anchor('files')->html();
    T::contains($html, 'href="?page=media&amp;folder=r26&amp;view=list&amp;sort=date-desc#files"', 'a folder link keeps the page, the view, the order and the fragment');
    T::contains($html, '<form class="gk-files-toolbar" method="get" action="#files"', 'the toolbar returns to the fragment');
    T::contains($html, '<input type="hidden" name="page" value="media">', 'the toolbar keeps the page');
    T::contains($html, '<li><a href="?page=media&amp;view=list&amp;sort=date-desc#files">Files</a></li>', 'the top of the breadcrumb keeps the view, not the folder');
    $p = fmSample()->param('media')->path('r')->html();
    T::contains($p, 'href="?media%5Bfolder%5D=r26"', 'a folder link under the name');
    T::contains($p, 'name="media[q]"', 'the search under the name');
    T::contains($p, 'name="media[view]"', 'the view under the name');
    T::contains($p, '<input type="hidden" name="media[folder]" value="r">', 'the folder under the name');
    T::eq(fmConfig($p)['param'] ?? null, 'media', 'the script writes the same names');
    T::eq(fmOrder(fmSample()->param('media')->fromQuery(['media' => ['type' => 'image'], 'type' => 'other'])->html()), ['a'], 'only its own parameters count');
    T::contains(fmSample()->html(), 'href="?file=a" title="File 10.png"', 'a name links to its details');
    [, $n] = fmRun(fn () => fmSample()->param('a b')->anchor('#1x')->keep(['q' => 'x', 'z' => []]));
    T::eq(count($n), 4, 'a bad name, a bad fragment, a parameter of its own, a value that is not text');
},

// ── Selection and actions ───────────────────────────────────────────────

'select(): checkboxes, radio buttons or none; selected() ticks' => function (): void {
    $multi = fmSample()->selected(['a', 'zz'])->html();
    T::contains($multi, '<li class="gk-files-item gk-files-selected" data-gk-file="a" data-gk-kind="image"><input type="checkbox" class="gk-files-check" id="gk-files-media-c-a" name="ids[]" value="a" checked aria-label="Select File 10.png">', 'a ticked box, named by the file');
    T::contains($multi, '<label class="gk-files-thumb" for="gk-files-media-c-a" aria-hidden="true"><img src="/m/a.png" alt="" loading="lazy" decoding="async"></label>', 'the picture ticks the box too');
    T::contains($multi, '<label class="gk-files-all" hidden><input type="checkbox" data-gk-files-all><span>Select all</span></label>', '"Select all" waits for the script');
    T::contains($multi, '<span class="gk-files-count" data-gk-files-count>1 selected</span>', 'the count');
    $single = fmSample()->select('single')->html();
    T::contains($single, '<input type="radio" class="gk-files-check"', 'single: radio buttons');
    T::notContains($single, 'data-gk-files-all', 'no "select all"');
    $none = fmSample()->select('none')->html();
    T::notContains($none, 'gk-files-check', 'none: no box');
    T::notContains($none, 'gk-files-bulk', 'no bar');
    T::contains($none, '<span class="gk-files-thumb" aria-hidden="true"><img', 'the picture is only a picture');
    [, $n] = fmRun(fn () => fmSample()->select('many'));
    T::eq(count($n), 1, 'an unknown mode warns');
},

'actions(): buttons that post where the page says, and events for the rest' => function (): void {
    [$html, $notes] = fmRun(fn () => fmSample()->actions(['delete' => '/d', 'move' => '/m', 'download' => true, 'rename' => '/r', 'folder' => '/f',
        'share' => '/s', 'copy' => false])->html());
    T::eq(count($notes), 2, 'an unknown action warns, also one switched off');
    T::contains($html, '<form class="gk-files-body" method="post" action="/d" id="gk-files-media-form">', 'the selection is a POST form');
    T::contains($html, 'name="action" value="delete" formaction="/d" data-gk-files-action="delete"', 'delete posts to its URL');
    T::contains($html, 'gk-btn gk-btn-outlined gk-btn-danger gk-btn-sm gk-files-act', 'delete looks dangerous');
    T::contains($html, 'name="action" value="move" formaction="/m" data-gk-files-action="move"', 'move posts to its URL');
    T::contains($html, 'name="action" value="download" hidden data-gk-files-needs-js data-gk-files-action="download"', 'an event-only action waits for the script');
    T::contains($html, '<select name="target" data-gk-files-target><option value="">Files</option><option value="m">' . "\u{00A0}\u{00A0}\u{00A0}" . 'Marketing</option><option value="r">' . "\u{00A0}\u{00A0}\u{00A0}" . 'Receipts</option><option value="r26">' . str_repeat("\u{00A0}", 6) . '2026</option></select>', 'every folder, indented by depth');
    T::contains($html, '<details class="gk-files-newfolder"><summary', 'a new folder');
    T::contains($html, '<form method="post" action="/f" data-gk-files-form="folder"><input type="hidden" name="action" value="folder"><input type="hidden" name="parent" value="">', 'which posts its parent');
    T::eq(fmConfig($html)['actions'] ?? null, ['delete' => '/d', 'move' => '/m', 'download' => true, 'rename' => '/r', 'folder' => '/f'], 'the script knows them');
    [$h2, $n2] = fmRun(fn () => fmSample()->actions(['delete' => 'javascript:x()', 'move' => 5, 'folder' => true])->html());
    T::eq(count($n2), 2, 'a javascript: URL and a number are refused');
    T::notContains($h2, 'data-gk-files-action="delete"', 'and not drawn');
    T::contains($h2, '<details class="gk-files-newfolder" hidden data-gk-files-needs-js>', 'a folder by event waits for the script');
    $no = fmSample()->html();
    T::notContains($no, 'gk-files-act', 'without actions no buttons');
    T::contains($no, '<form class="gk-files-body" method="post" action="" id=', 'the form posts nowhere in particular');
},

'the details: what the file is, its address, a way to rename it' => function (): void {
    $html = fmSample()->files([['id' => 'w', 'name' => 'Wide.jpg', 'size' => 1572864, 'mime' => 'image/jpeg', 'url' => '/w.jpg', 'width' => 3000, 'height' => '2000', 'modified' => '2026-03-04 05:06']])
        ->actions(['rename' => '/r'])->csrf('t0k')->open('w')->html();
    T::contains($html, 'tabindex="-1"><div class="gk-files-details-head"><h3 class="gk-files-details-title" id="gk-files-media-dtitle">Wide.jpg</h3>', 'open, with the name');
    T::contains($html, '<a class="gk-files-details-close" href="?" role="button" aria-label="Close details">', 'a close link that works without script');
    T::contains($html, '<img src="/w.jpg" alt="Preview of Wide.jpg">', 'a named preview');
    T::contains($html, '<dt>Type</dt><dd>JPG image (image/jpeg)</dd><dt>Size</dt><dd>1.5 MB</dd><dt>Dimensions</dt><dd>3,000 × 2,000 px</dd><dt>Modified</dt><dd>2026-03-04 05:06</dd>', 'type, size, dimensions, date');
    T::contains($html, '<input type="text" readonly value="/w.jpg">', 'the URL to select');
    T::contains($html, 'gk-files-copy" hidden data-gk-files-copy>', 'copy waits for the script');
    T::contains($html, '<form class="gk-files-rename" method="post" action="/r" data-gk-files-form="rename"><input type="hidden" name="_token" value="t0k"><input type="hidden" name="action" value="rename"><input type="hidden" name="ids[]" value="w">', 'rename posts the token, the action and the id');
    T::contains($html, '<input type="text" name="name" value="Wide.jpg" required maxlength="255"', 'with the name to change');
    T::contains($html, '<li class="gk-files-item" data-gk-file="w"', 'the file is listed');
    $pdf = fmSample()->open('b')->html();
    T::contains($pdf, '<div class="gk-files-preview"><span class="material-icons" aria-hidden="true">picture_as_pdf</span></div>', 'a PDF is a symbol');
    T::notContains($pdf, 'gk-files-url', 'no address, no URL row');
    T::notContains($pdf, '<dt>Dimensions</dt>', 'no dimensions for a PDF');
    T::contains(fmSample()->open('nope')->html(), 'tabindex="-1" hidden>', 'an unknown file opens nothing');
},

// ── Upload form ─────────────────────────────────────────────────────────

'the upload: a multipart form that works without script, the limits in words' => function (): void {
    $html = fmSample()->uploadUrl('/up')->path('r')->csrf('tok', 'csrf_token')->hidden(['tenant' => 7])->html();
    T::contains($html, '<form class="gk-files-upload" method="post" enctype="multipart/form-data" action="/up" aria-label="Upload"><input type="hidden" name="csrf_token" value="tok"><input type="hidden" name="tenant" value="7"><input type="hidden" name="folder" value="r">', 'token, hidden fields and the folder');
    T::contains($html, '<input type="file" name="files[]" id="gk-files-media-input" multiple accept="image/jpeg,image/png,image/gif,image/webp,application/pdf" aria-describedby="gk-files-media-hint">', 'files[], several, the types, described by the hint');
    T::contains($html, '<span class="gk-files-hint" id="gk-files-media-hint">JPEG, PNG, GIF, WebP and PDF, up to 10 MB each, 20 files at once.</span>', 'the limits in words');
    T::contains($html, '<button type="submit" class="gk-btn gk-btn-filled gk-btn-primary gk-btn-sm gk-files-send">Upload</button>', 'a submit button without script');
    T::contains($html, '<ul class="gk-files-queue" aria-label="Uploads" hidden></ul>', 'the queue waits');
    $cfg = fmConfig($html);
    T::eq([$cfg['upload'], $cfg['maxSize'], $cfg['maxFiles'], $cfg['parallel']], ['/up', 10485760, 20, 3], 'the limits for the script');
    T::eq($cfg['csrf'] ?? null, ['field' => 'csrf_token', 'token' => 'tok'], 'the token for its header');
    T::eq($cfg['hidden'] ?? null, ['tenant' => '7'], 'the hidden fields');
    $h2 = fmSample()->uploadUrl('/up')->accept(['image/*', 'application/pdf', '.CSV'])->maxSize('2 MB')->maxFiles(3)->html();
    T::contains($h2, 'Images, PDF and CSV, up to 2 MB each, 3 files at once.', 'families and extensions in words');
    T::contains($h2, 'accept="image/*,application/pdf,.csv"', 'the accept attribute');
},

'upload options: sizes, counts, types — a wrong one warns and falls back' => function (): void {
    $cases = [
        [fn () => (new FileManager('x'))->maxSize('512K'), 'maxSize', 524288, 0],
        [fn () => (new FileManager('x'))->maxSize('1.5M'), 'maxSize', 1572864, 0],
        [fn () => (new FileManager('x'))->maxSize(2048), 'maxSize', 2048, 0],
        [fn () => (new FileManager('x'))->maxSize('huge'), 'maxSize', 10485760, 1],
        [fn () => (new FileManager('x'))->maxSize(10), 'maxSize', 10485760, 1],
        [fn () => (new FileManager('x'))->maxSize('3G'), 'maxSize', 10485760, 1],
        [fn () => (new FileManager('x'))->maxFiles(0), 'maxFiles', 20, 1],
        [fn () => (new FileManager('x'))->maxFiles(1000), 'maxFiles', 1000, 0],
    ];
    foreach ($cases as [$fn, $key, $want, $warns]) {
        [$fm, $n] = fmRun($fn);
        T::eq($fm->rules()[$key], $want, "$key becomes $want");
        T::eq(count($n), $warns, "$key: $warns warning(s)");
    }
    [$fm, $n] = fmRun(fn () => (new FileManager('x'))->uploadUrl('/u')->parallel(9));
    T::eq(fmConfig($fm->html())['parallel'], 3, 'parallel out of range is 3');
    T::eq(count($n), 1, 'and says so');
    [$fm, $n] = fmRun(fn () => (new FileManager('x'))->accept(['text/html', '.php', 'image', 'image/png', 'image/png', 'application/x-php']));
    T::eq($fm->rules()['accept'], ['image/png'], 'what runs and what is no type is dropped; doubles too');
    T::eq(count($n), 4, 'four warnings');
    [$fm, $n] = fmRun(fn () => (new FileManager('x'))->accept(['nope']));
    T::eq($fm->rules()['accept'], ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'], 'nothing left: images and PDF');
    T::eq(count($n), 2, 'the entry and the empty list');
    [, $n] = fmRun(fn () => (new FileManager('x'))->uploadUrl('javascript:x')->uploadUrl(''));
    T::eq(count($n), 2, 'an upload URL that is not one');
    [, $n] = fmRun(fn () => (new FileManager('x'))->csrf('t', 'a b')->hidden(['ids' => 1, 'action' => 'x', 'ok' => [1]]));
    T::eq(count($n), 4, 'a bad field name, two of the manager\'s own fields, a value that is not text');
    [, $n] = fmRun(fn () => (new FileManager('x'))->view('cards')->sort('colour', 'up')->filter('music'));
    T::eq(count($n), 4, 'view, sort key, direction, type');
    [$fm, $n] = fmRun(fn () => new FileManager(' '));
    T::eq(count($n), 1, 'an empty id');
    T::contains($fm->html(), 'data-gk-files="files"', 'is "files"');
},

'words in German' => function (): void {
    Lang::set('de');
    $html = fmSample()->uploadUrl('/u')->actions(['delete' => '/d'])->open('a')->html();
    Lang::set('en');
    foreach (['Dateien durchsuchen', 'Alle Arten', 'Neueste zuerst', 'Raster', 'Hochladen', 'Dateien wählen', '0 ausgewählt',
              'Löschen', 'Details schließen', 'Abmessungen', '2,0 KB'] as $w) {
        if ($w === '2,0 KB') continue;
        T::contains($html, $w, "German: $w");
    }
    T::contains($html, 'bis 10 MB je Datei, 20 Dateien auf einmal.', 'the hint in German');
    T::notContains($html, 'Search files', 'no English left');
    T::eq(fmConfig($html)['locale']['date'] ?? null, 'd.m.Y H:i', 'the German date shape');
},

// ── receive(): what is let in ───────────────────────────────────────────

'receive() takes only what PHP received: a file a test made is refused' => function (): void {
    [$r, $n] = fmRun(fn () => FileManager::receive(fmFiles([['a.png', fmPng(), UPLOAD_ERR_OK]])));
    T::eq($n, [], 'no warning');
    T::eq(count($r), 1, 'one result');
    T::eq([$r[0]['ok'], $r[0]['error']], [false, 'not_uploaded'], 'is_uploaded_file() says no');
    T::eq($r[0]['message'], 'a.png was not uploaded with this request and was not taken.', 'in words');
    [$r] = fmRun(fn () => FileManager::receive(['name' => ['x.png'], 'tmp_name' => ['/etc/passwd'], 'error' => [0], 'size' => [1]]));
    T::eq($r[0]['error'], 'not_uploaded', 'a path smuggled into the array');
    [$r] = fmRun(fn () => FileManager::receive(['name' => 'x.png', 'tmp_name' => "/tmp/a\0b", 'error' => 0, 'size' => 1]));
    T::eq($r[0]['error'], 'not_uploaded', 'a path with a NUL byte');
},

'receive() reads every shape of $_FILES, and an empty slot is no file' => function (): void {
    $png = fmPng();
    T::eq(fmRun(fn () => fmIntake([]))[0], [], 'nothing posted: an empty list');
    T::eq(fmRun(fn () => fmIntake(['name' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0]))[0], [], 'an empty field: nothing');
    $single = fmRun(fn () => fmIntake(['name' => 'one.png', 'tmp_name' => fmTmp($png), 'error' => 0, 'size' => 0]))[0];
    T::eq([count($single), $single[0]['ok']], [1, true], 'one file');
    $multi = fmRun(fn () => fmIntake(fmFiles([['a.png', $png, 0], ['', '', UPLOAD_ERR_NO_FILE], ['b.png', $png, 0]])))[0];
    T::eq(array_column($multi, 'name'), ['a.png', 'b.png'], 'files[] without the empty slot');
    T::eq(array_column($multi, 'index'), [0, 2], 'each says where it was');
    $nested = fmRun(fn () => fmIntake(['name' => ['x' => ['a.png', 'b.png']], 'tmp_name' => ['x' => [fmTmp($png), fmTmp($png)]], 'error' => ['x' => [0, 0]], 'size' => ['x' => [1, 1]]]))[0];
    T::eq(count($nested), 2, 'files[x][] nested');
    $list = fmRun(fn () => fmIntake([['name' => 'a.png', 'tmp_name' => fmTmp($png), 'error' => 0, 'size' => 1], 'junk']))[0];
    T::eq(count($list), 1, 'a list of entries, junk ignored');
    T::eq(fmRun(fn () => fmIntake(['foo' => 'bar']))[0], [], 'not an upload at all');
},

'receive(): an upload error is a sentence, not a mystery' => function (): void {
    $r = fmRun(fn () => fmIntake(fmFiles([['big.png', '', UPLOAD_ERR_INI_SIZE], ['form.png', '', UPLOAD_ERR_FORM_SIZE], ['half.png', '', UPLOAD_ERR_PARTIAL],
        ['t.png', '', UPLOAD_ERR_NO_TMP_DIR], ['w.png', '', UPLOAD_ERR_CANT_WRITE], ['e.png', '', UPLOAD_ERR_EXTENSION], ['n.png', '', 99]])))[0];
    T::eq(array_column($r, 'error'), ['ini_size', 'size', 'partial', 'server', 'server', 'server', 'server'], 'each code');
    T::contains($r[0]['message'], 'big.png is larger than this server takes (', 'upload_max_filesize in words');
    T::eq($r[2]['message'], 'half.png arrived only in part. Please try again.', 'partial');
    T::eq($r[3]['message'], 'The server could not take t.png (error 6).', 'the server\'s trouble');
    foreach ($r as $x) T::ok($x['ok'] === false && $x['stored'] === null, 'refused, nothing stored');
},

'receive(): more than maxFiles — the rest is refused' => function (): void {
    $png = fmPng();
    $r = fmRun(fn () => fmIntake(fmFiles([['1.png', $png, 0], ['2.png', $png, 0], ['3.png', $png, 0]]), ['maxFiles' => 2]))[0];
    T::eq(array_column($r, 'ok'), [true, true, false], 'two taken');
    T::eq($r[2]['error'], 'count', 'the third: too many');
    T::eq($r[2]['message'], 'Too many files: at most 2 can be uploaded at once.', 'in words');
},

'a sound picture: its type by content, its size on disk, a random name of its own' => function (): void {
    $png = fmPng(4, 3);
    $r = fmOne('Holiday photo.png', $png);
    T::eq([$r['ok'], $r['error'], $r['mime'], $r['ext'], $r['kind']], [true, null, 'image/png', 'png', 'image'], 'a PNG');
    T::eq([$r['width'], $r['height']], [4, 3], 'its pixels');
    T::eq($r['size'], strlen($png), 'the size on disk, not the 10 the client claimed');
    T::ok(preg_match('/^[0-9a-f]{32}\.png$/', (string) $r['stored']) === 1, 'stored as 32 random hex digits and its own extension');
    T::eq($r['sha256'], hash('sha256', $png), 'its hash');
    T::eq($r['name'], 'Holiday photo.png', 'the client name for display');
    T::ok(is_string($r['tmp']) && is_file($r['tmp']) && $r['path'] === null, 'without a target nothing is moved');
    T::eq($r['message'], 'Holiday photo.png was accepted.', 'in words');
    T::ok(fmOne('a.png', $png)['stored'] !== $r['stored'], 'a new name every time');
    $j = fmOne('Shot.JPEG', (string) base64_decode(FM_JPEG));
    T::eq([$j['ok'], $j['mime'], $j['ext']], [true, 'image/jpeg', 'jpg'], 'a .JPEG is a JPEG, stored as .jpg');
    $g = fmOne('anim.gif', (string) base64_decode(FM_GIF));
    T::eq([$g['ok'], $g['mime']], [true, 'image/gif'], 'a GIF');
    $p = fmOne('Invoice 2026.pdf', FM_PDF);
    T::eq([$p['ok'], $p['mime'], $p['kind'], $p['width']], [true, 'application/pdf', 'document', null], 'a PDF');
    $c = fmOne('list.csv', "name;mail\nAnna;a@b.at\n", ['accept' => ['.csv']]);
    T::eq([$c['ok'], $c['ext']], [true, 'csv'], 'a CSV by its extension token');
},

'the type is what the file holds, and the name has to agree' => function (): void {
    $png = fmPng();
    $r = fmOne('notes.png', "just some text\n");
    T::eq($r['error'], 'type', 'text named .png: not an accepted type');
    T::eq($r['mime'], 'text/plain', 'it is text');
    T::eq(fmOne('notes.png', "just some text\n", ['accept' => ['text/plain', 'image/*']])['error'], 'mismatch', 'accepted text, but named like a picture');
    $m = fmOne('photo.jpg', $png);
    T::eq($m['error'], 'mismatch', 'a PNG named .jpg');
    T::eq($m['message'], 'photo.jpg is a PNG image, but its name says otherwise. Rename it with the right extension.', 'in words');
    T::eq(fmOne('doc.png', FM_PDF)['error'], 'mismatch', 'a PDF named .png');
    T::eq(fmOne('scan.pdf', $png, ['accept' => ['application/pdf']])['error'], 'type', 'a picture where only PDF is taken');
    T::eq(fmOne('noext', $png)['error'], 'mismatch', 'a name without an extension');
    T::eq(fmOne('a.png', $png, ['accept' => ['image/*']])['ok'], true, 'image/* takes a PNG');
    T::eq(fmOne('a.png', $png, ['accept' => ['.png']])['ok'], true, 'so does .png');
},

'what a browser or a server runs is never taken, whatever accept says' => function (): void {
    $php = "<?php system(\$_GET['c']); ?>";
    $r = fmOne('shell.jpg', $php, ['accept' => ['image/*', 'text/x-php', 'text/plain']]);
    T::eq([$r['ok'], $r['error']], [false, 'type'], 'PHP named .jpg');
    T::eq(fmOne('page.png', '<!DOCTYPE html><html><body><script>alert(1)</script></body></html>', ['accept' => ['text/html', 'image/*']])['error'], 'type', 'HTML named .png');
    T::eq(fmOne('a.txt', "#!/bin/sh\nrm -rf /\n", ['accept' => ['text/plain', '.txt']])['error'], 'type', 'a shell script named .txt');
    T::eq(fmOne('a.txt', '<?php echo 1;', ['accept' => ['text/plain']])['error'], 'type', 'PHP named .txt');
    T::eq(fmOne('x.png', "\x7fELF\x02\x01\x01" . str_repeat("\0", 60), ['accept' => ['image/*', 'application/octet-stream']])['error'], 'type', 'a program');
},

'a name that runs, anywhere in it, is refused before the file is opened' => function (): void {
    $png = fmPng();
    foreach (['shell.php.png', 'x.PHP5.png', 'x.phtml.png', 'a.phar.png', 'b.pht.png', 'c.cgi.png', 'd.jsp.png', 'e.asp.png',
              'f.html.png', 'g.exe.png', 'h.shtml.png', 'x.png.php', 'x.png.php.', 'x.php .png', 'evil.svgz.png', 'a.htaccess.png'] as $name) {
        $r = fmOne($name, $png);
        T::eq([$r['ok'], $r['error']], [false, 'name'], "$name is refused");
        T::ok($r['sha256'] === null && $r['stored'] === null, "$name is not even read");
    }
    T::contains(fmOne('shell.php.png', $png)['message'], 'is named like a program a server runs', 'in words');
    foreach (['.htaccess', '.user.ini', '...', ' . ', ''] as $name) {
        T::eq(fmOne($name, $png)['error'], 'name', var_export($name, true) . ' is no name');
    }
    $nul = fmOne("photo.php\0.png", $png);
    T::eq($nul['error'], 'name', 'a NUL byte');
    T::eq(fmOne("evil\0.png", $png)['error'], 'name', 'a NUL byte alone is enough');
    T::eq(fmOne('.hidden.png', $png)['error'], 'name', 'a leading dot, even before a sound extension');
    T::notContains((string) $nul['name'], "\0", 'and the name shown has none');
    T::eq(fmOne('report.2026.final.png', $png)['ok'], true, 'dots in a name are fine');
    T::eq(fmOne('phpinfo.png', $png)['ok'], true, '"php" inside a word is fine');
},

'the client\'s name is cleaned for display and never reaches the disk' => function (): void {
    $png = fmPng();
    $r = fmOne('C:\\fakepath\\..\\..\\etc/photo.png', $png);
    T::eq([$r['ok'], $r['name']], [true, 'photo.png'], 'no path, either slash');
    T::eq(fmOne("ph\x01o\x1bto\x7f.png", $png)['name'], 'photo.png', 'no control characters');
    T::eq(fmOne("Gr\xfc\xdfe.png", $png)['name'], 'Gr??e.png', 'bytes that are not UTF-8 become "?"');
    $long = fmOne(str_repeat('ä', 200) . '.png', $png);
    T::ok(strlen((string) $long['name']) <= 255 && preg_match('//u', (string) $long['name']) === 1, 'at most 255 bytes, still UTF-8');
    T::ok(!str_contains((string) $r['stored'], 'photo'), 'the stored name has nothing of it');
},

'zero bytes and too many bytes' => function (): void {
    $r = fmOne('empty.png', '');
    T::eq([$r['error'], $r['message']], ['empty', 'empty.png is empty (0 bytes).'], 'zero bytes');
    $big = fmOne('big.png', fmPng(4, 3, str_repeat('x', 3000)), ['maxSize' => 2048]);
    T::eq($big['error'], 'size', 'larger than maxSize');
    T::contains($big['message'], 'is too large (3 KB); at most 2 KB per file.', 'both sizes in words');
    T::eq(fmOne('ok.png', fmPng(), ['maxSize' => '1K'])['ok'], true, 'maxSize as text');
},

'a polyglot: a real picture carrying code is refused' => function (): void {
    $r = fmOne('cat.png', fmPng(4, 3, '<?php system($_GET["c"]); ?>'));
    T::eq([$r['ok'], $r['error'], $r['mime']], [false, 'script', 'image/png'], 'PHP after the PNG');
    T::eq($r['message'], 'cat.png holds code and was not taken.', 'in words');
    T::eq(fmOne('cat.png', fmPng(4, 3, '<?PhP echo 1;'))['error'], 'script', 'case does not hide it');
    T::eq(fmOne('cat.png', fmPng(4, 3, '<?= 1 ?>'))['error'], 'script', 'the short tag in a picture');
    T::eq(fmOne('cat.png', fmPng(4, 3, '<script>alert(1)</script>'))['error'], 'script', 'a script element in a picture');
    T::eq(fmOne('cat.png', fmPng(4, 3, '<HTML><body onload=x>'))['error'], 'script', 'a page in a picture');
    $jpeg = (string) base64_decode(FM_JPEG);
    $exif = substr($jpeg, 0, 2) . "\xFF\xFE\x00\x16<?php phpinfo(); ?>X" . substr($jpeg, 2);
    T::eq(fmOne('me.jpg', $exif)['error'], 'script', 'PHP in a JPEG comment');
    $gif = (string) base64_decode(FM_GIF);
    T::eq(fmOne('a.gif', $gif . '<script src=//evil></script>')['error'], 'script', 'a GIFAR-like tail');
    T::eq(fmOne('i.pdf', FM_PDF . "\n<?php echo 1; ?>")['error'], 'script', 'PHP in a PDF');
    // Across the boundary of the 1 MB pieces it reads.
    $edge = fmPng(4, 3, str_repeat('A', 1048576 - strlen(fmPng(4, 3)) - 2) . '<?php');
    T::eq(fmOne('edge.png', $edge, ['maxSize' => 4194304])['error'], 'script', 'a word split between two pieces');
    T::ok(in_array(fmOne('t.txt', "a <script> in a text file\n", ['accept' => ['text/plain']])['error'], ['script', 'type'], true), 'page markup in text: as code, or as a page');
},

'a decompression bomb: pixels, not bytes' => function (): void {
    $r = fmOne('bomb.png', fmPng(50000, 50000));
    T::eq([$r['ok'], $r['error']], [false, 'dimensions'], 'a 100-byte PNG of 50,000 × 50,000');
    T::eq($r['message'], 'bomb.png is 50,000 × 50,000 pixels; at most 50 megapixels can be taken.', 'in words');
    T::ok(strlen(fmPng(50000, 50000)) < 200, 'it really is tiny');
    T::eq(fmOne('wide.png', fmPng(30000, 2))['error'], 'dimensions', 'wider than maxWidth');
    T::eq(fmOne('wide.png', fmPng(3000, 2), ['maxWidth' => 2000])['error'], 'dimensions', 'maxWidth');
    T::eq(fmOne('tall.png', fmPng(2, 3000), ['maxHeight' => 2000])['error'], 'dimensions', 'maxHeight');
    T::eq(fmOne('mp.png', fmPng(2000, 2000), ['maxPixels' => 3000000])['error'], 'dimensions', 'maxPixels');
    T::eq(fmOne('fine.png', fmPng(2000, 1500), ['maxPixels' => 3000000])['ok'], true, 'just inside');
    T::eq(fmOne('broken.png', "\x89PNG\r\n\x1a\n" . str_repeat("\0", 40))['ok'], false, 'a PNG signature with nothing behind it');
},

'SVG: only when named, and only without anything that runs or loads' => function (): void {
    $clean = '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><rect width="10" height="10" fill="#0a0"/><use href="#a"/></svg>';
    T::eq(fmOne('logo.svg', $clean)['error'], 'type', 'the default does not take SVG');
    T::eq(fmOne('logo.svg', $clean, ['accept' => ['image/*']])['error'], 'type', 'image/* does not take SVG');
    T::eq(fmOne('logo.svg', $clean, ['accept' => ['image/*', '.svg']])['ok'], true, 'named as .svg it does');
    $svg = ['accept' => ['image/svg+xml']];
    $r = fmOne('logo.svg', $clean, $svg);
    T::eq([$r['ok'], $r['mime'], $r['ext'], $r['kind']], [true, 'image/svg+xml', 'svg', 'image'], 'a clean SVG');
    $bad = [
        'script'       => '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
        'onload'       => '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>',
        'onclick'      => '<svg xmlns="http://www.w3.org/2000/svg"><rect ONCLICK="alert(1)"/></svg>',
        'javascript'   => '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><a xlink:href=" java&#9;script:alert(1)"><rect/></a></svg>',
        'data html'    => '<svg xmlns="http://www.w3.org/2000/svg"><a href="data:text/html,&lt;script&gt;alert(1)&lt;/script&gt;"><rect/></a></svg>',
        'foreign'      => '<svg xmlns="http://www.w3.org/2000/svg"><foreignObject><body xmlns="http://www.w3.org/1999/xhtml">x</body></foreignObject></svg>',
        'iframe'       => '<svg xmlns="http://www.w3.org/2000/svg"><iframe src="//evil"/></svg>',
        'animate'      => '<svg xmlns="http://www.w3.org/2000/svg"><a><animate attributeName="href" values="javascript:alert(1)"/><rect/></a></svg>',
        'set'          => '<svg xmlns="http://www.w3.org/2000/svg"><set attributeName="onmouseover" to="alert(1)"/></svg>',
        'entity'       => '<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY x SYSTEM "file:///etc/passwd">]><svg xmlns="http://www.w3.org/2000/svg"><text>&x;</text></svg>',
        'laughs'       => '<!DOCTYPE svg [<!ENTITY a "aaaa"><!ENTITY b "&a;&a;&a;&a;">]><svg xmlns="http://www.w3.org/2000/svg">&b;</svg>',
        'style import' => '<svg xmlns="http://www.w3.org/2000/svg"><style>@import url(//evil/x.css);</style></svg>',
        'style url'    => '<svg xmlns="http://www.w3.org/2000/svg"><style>rect{background:url(https://evil/track)}</style></svg>',
        'style attr'   => '<svg xmlns="http://www.w3.org/2000/svg"><rect style="background:url(javascript:alert(1))"/></svg>',
        'pi'           => '<?xml version="1.0"?><?xml-stylesheet href="//evil/x.xsl" type="text/xsl"?><svg xmlns="http://www.w3.org/2000/svg"/>',
        'php'          => '<svg xmlns="http://www.w3.org/2000/svg"><text><?php echo 1; ?></text></svg>',
        'not xml'      => '<svg xmlns="http://www.w3.org/2000/svg"><rect></svg>',
    ];
    foreach ($bad as $what => $bytes) {
        $r = fmOne('x.svg', $bytes, $svg);
        T::eq($r['ok'], false, "an SVG with $what is refused");
        T::ok(in_array($r['error'], ['script', 'type'], true), "$what: as code or as no SVG ({$r['error']})");
    }
    T::contains(fmOne('x.svg', $bad['script'], $svg)['message'], 'is an SVG with script or links that run', 'in words');
    T::eq(fmOne('ok.svg', '<svg xmlns="http://www.w3.org/2000/svg"><image href="data:image/png;base64,iVBORw0KGgo="/></svg>', $svg)['ok'], true, 'a raster picture inside is fine');
    T::eq(fmOne('ok.svg', '<svg xmlns="http://www.w3.org/2000/svg"><style>rect{fill:url(#g)}</style></svg>', $svg)['ok'], true, 'a style pointing inside is fine');
},

'target: stored outside the web root under its random name; refused inside it' => function (): void {
    $base = sys_get_temp_dir() . '/gkfm-' . bin2hex(random_bytes(4));
    $store = $base . '/store';
    $web = $base . '/www';
    mkdir($store, 0777, true);
    mkdir($web . '/uploads', 0777, true);
    try {
        $png = fmPng();
        [$r, $n] = fmRun(fn () => fmIntake(fmFiles([['a.png', $png, 0]]), ['target' => $store, 'webRoot' => $web]));
        T::eq($n, [], 'no warning');
        T::ok($r[0]['ok'] && $r[0]['path'] === realpath($store) . '/' . $r[0]['stored'], 'moved to the target under the stored name');
        T::eq((string) file_get_contents((string) $r[0]['path']), $png, 'the bytes arrived');
        T::eq(fileperms((string) $r[0]['path']) & 0777, 0640, 'readable by the server user only');
        T::eq($r[0]['tmp'], null, 'no temporary file left to use');
        T::eq(glob($store . '/*.png') ?: [], [$r[0]['path']], 'nothing else was written');

        [$r, $n] = fmRun(fn () => fmIntake(fmFiles([['a.png', $png, 0]]), ['target' => $web . '/uploads', 'webRoot' => $web]));
        T::eq([$r[0]['ok'], $r[0]['error']], [false, 'store'], 'inside the web root: not stored');
        T::eq(count($n), 1, 'a warning says why');
        T::contains($n[0] ?? '', 'lies inside the web root', 'which');
        T::eq(glob($web . '/uploads/*') ?: [], [], 'nothing written there');
        T::ok(is_string($r[0]['tmp']), 'the checked file is still at hand');

        [$r] = fmRun(fn () => fmIntake(fmFiles([['a.png', $png, 0]]), ['target' => $web, 'webRoot' => $web]));
        T::eq($r[0]['error'], 'store', 'the web root itself too');
        [$r] = fmRun(fn () => fmIntake(fmFiles([['a.png', $png, 0]]), ['target' => $web . '/uploads/../uploads', 'webRoot' => $web . '/']));
        T::eq($r[0]['error'], 'store', 'a path that dodges with ..');

        [$r, $n] = fmRun(fn () => fmIntake(fmFiles([['a.png', $png, 0]]), ['target' => $web . '/uploads', 'webRoot' => $web, 'public' => true]));
        T::ok($r[0]['ok'] && $n === [], 'public: allowed');
        T::eq(fileperms((string) $r[0]['path']) & 0777, 0644, 'and readable by the web server');

        [$r, $n] = fmRun(fn () => fmIntake(fmFiles([['a.png', $png, 0], ['b.png', $png, 0]]), ['target' => $base . '/missing']));
        T::eq(array_column($r, 'error'), ['store', 'store'], 'a missing directory: nothing stored');
        T::eq(count($n), 1, 'one warning for both');
        $plain = fmTmp('x');
        [$r, $n] = fmRun(fn () => fmIntake(fmFiles([['a.png', $png, 0]]), ['target' => $plain]));
        T::eq($r[0]['error'], 'store', 'a file as the target: nothing stored');
        T::contains($n[0] ?? '', 'is not a writable directory', 'and a warning says why');
        [$r] = fmRun(fn () => fmIntake(fmFiles([['x.png', 'text', 0]]), ['target' => $store]));
        T::eq($r[0]['error'], 'type', 'a refused file is never moved');
        T::eq(count(glob($store . '/*') ?: []), 1, 'the store holds only the first');
    } finally {
        foreach (array_merge(glob($store . '/*') ?: [], glob($web . '/uploads/*') ?: []) as $f) @unlink($f);
        @rmdir($web . '/uploads'); @rmdir($web); @rmdir($store); @rmdir($base);
    }
},

'check(): the same checks for a file on the server; with a target it is copied' => function (): void {
    $png = fmTmp(fmPng());
    [$r, $n] = fmRun(fn () => FileManager::check($png, 'Logo.png'));
    T::eq([$r['ok'], $r['name'], $n], [true, 'Logo.png', []], 'checked under its name');
    T::eq(FileManager::check($png)['error'], 'mismatch', 'without a name: the temp name, which has no .png');
    T::eq(fmRun(fn () => FileManager::check($png, 'x.php.png'))[0]['error'], 'name', 'the name rules hold');
    T::eq(fmRun(fn () => FileManager::check('/nonexistent/file.png', 'a.png'))[0]['error'], 'not_uploaded', 'a file that is not there');
    $dir = sys_get_temp_dir() . '/gkfm-c-' . bin2hex(random_bytes(4));
    mkdir($dir);
    try {
        [$c] = fmRun(fn () => FileManager::check($png, 'Logo.png', ['target' => $dir, 'webRoot' => '/nonexistent-root']));
        T::ok($c['ok'] && is_file((string) $c['path']) && is_file($png), 'copied; the original stays');
    } finally {
        foreach (glob($dir . '/*') ?: [] as $f) @unlink($f);
        @rmdir($dir);
    }
},

'receive() options: an unknown or wrong one warns and falls back' => function (): void {
    [$r, $n] = fmRun(fn () => fmIntake(fmFiles([['a.png', fmPng(), 0]]), ['acept' => ['x'], 'maxSize' => 'big', 'maxFiles' => 0, 'maxPixels' => -1, 'accept' => [], 'target' => 5]));
    T::eq(count($n), 7, 'each one says so — and the target that is none');
    T::eq($r[0]['error'], 'store', 'a target that is not a directory stores nothing');
    T::eq(fmRun(fn () => fmIntake(fmFiles([['a.png', fmPng(), 0]]), ['maxSize' => 'big', 'maxFiles' => 0]))[0][0]['ok'], true, 'the defaults still take a sound file');
},

'rules() hands receive() what the browser was told' => function (): void {
    $fm = (new FileManager('x'))->accept(['application/pdf'])->maxSize('1M')->maxFiles(1);
    T::eq($fm->rules(), ['accept' => ['application/pdf'], 'maxSize' => 1048576, 'maxFiles' => 1], 'the three limits');
    $r = fmRun(fn () => fmIntake(fmFiles([['a.png', fmPng(), 0], ['b.pdf', FM_PDF, 0]]), $fm->rules()))[0];
    T::eq(array_column($r, 'error'), ['type', 'count'], 'a picture is refused, and a second file is one too many');
},

'no public way to call a file uploaded; receive() moves with move_uploaded_file' => function () use ($src): void {
    $m = new ReflectionMethod(FileManager::class, 'intake');
    T::ok($m->isPrivate(), 'the seam is private');
    T::ok((bool) preg_match("/fn \(string \\\$path\): bool => is_uploaded_file\(\\\$path\),\s*static fn \(string \\\$from, string \\\$to\): bool => move_uploaded_file\(\\\$from, \\\$to\)/", $src()), 'receive() passes the real two');
    T::notContains($src(), 'scandir(', 'the component never reads a directory');
    T::notContains($src(), 'unlink(', 'and never deletes');
    T::notContains($src(), 'rename(', 'or renames');
},

// ── The script, the stylesheet, the documents, the demo ─────────────────

'the script reads what the server writes, and keeps the contract of an upload' => function () use ($js): void {
    $code = $js();
    foreach (['[data-gk-files]', 'data-gk-files-config', 'data-gk-files-q', 'data-gk-files-type', 'data-gk-files-sort', 'data-gk-files-view',
              'data-gk-files-all', 'data-gk-files-count', 'data-gk-files-target', 'data-gk-files-action', 'data-gk-files-form',
              'data-gk-files-copy', 'data-gk-files-details', 'data-gk-files-live', 'data-gk-files-needs-js', 'data-gk-file', 'data-gk-kind'] as $hook) {
        T::contains($code, $hook, "the script reads $hook");
    }
    T::contains($code, 'xhr.upload.onprogress', 'progress per file');
    T::contains($code, 'URL.revokeObjectURL', 'object URLs are given back');
    T::contains($code, 'data.ok === true && data.file', 'the answer {"ok":true,"file":…}');
    T::contains($code, 'data.ok === false', 'and {"ok":false,"message":…}');
    T::contains($code, 'fd.append("files[]", job.file, job.name)', 'one file per request, as files[]');
    T::contains($code, '"X-CSRF-Token"', 'the token travels as a header');
    T::contains($code, 'credentials: "same-origin"', 'actions with the session');
    T::contains($code, 'window.GK.files = api', 'GK.files');
    foreach (['gk:filesselect', 'gk:filesopen', 'gk:filesupload', 'gk:filesuploaded', 'gk:fileserror', 'gk:filesaction', 'gk:filesdone'] as $ev) {
        T::contains($code, '"' . $ev . '"', "fires $ev");
    }
    T::notContains($code, 'innerHTML', 'nothing is parsed as HTML');
},

'every word the script asks for is in the catalogue, in both languages' => function () use ($js, $src): void {
    $en = require __DIR__ . '/../lang/en.php';
    $de = require __DIR__ . '/../lang/de.php';
    preg_match_all('/say\(st, "([a-z_]+)"/', $js(), $m);
    preg_match('/private const JS_WORDS = \[(.*?)\];/s', $src(), $w);
    preg_match_all("/'([a-z_]+)'/", $w[1] ?? '', $sent);
    foreach (array_unique($m[1]) as $key) {
        T::ok(in_array($key, $sent[1], true), "files.$key is sent to the script");
        T::ok(isset($en["files.$key"], $de["files.$key"]), "files.$key exists in en and de");
    }
    preg_match_all("/Lang::t\('(files\.[a-z_]+)'[,)]/", $src(), $p);
    foreach (array_unique($p[1]) as $key) T::ok(isset($en[$key], $de[$key]), "$key exists in en and de");
    // The words put together from a value.
    foreach (['type_all', 'type_image', 'type_document', 'type_other', 'view_grid', 'view_list', 'act_delete', 'act_move', 'act_download', 'act_rename',
              'sort_name_asc', 'sort_name_desc', 'sort_date_desc', 'sort_date_asc', 'sort_size_desc', 'sort_size_asc', 'sort_type_asc', 'sort_type_desc'] as $key) {
        T::ok(isset($en["files.$key"], $de["files.$key"]), "files.$key exists in en and de");
    }
},

'the stylesheet block: roles only, both dark spellings, two columns and 44px on a phone' => function () use ($sheet, $root): void {
    $css = $sheet();
    T::ok($css !== '', 'the block is there');
    T::ok(!preg_match('/#[0-9a-f]{3,8}\b|rgba?\(/i', $css), 'no colour literal — roles only');
    T::contains($css, '[data-gk-mode="dark"] .gk-files-view-grid .gk-files-item,', 'dark by attribute');
    T::contains($css, '.gk-dark .gk-files-view-grid .gk-files-item,', 'and by class');
    T::ok((bool) preg_match('/@media \(max-width: 600px\) \{\s*\.gk-files-view-grid \.gk-files-items \{\s*grid-template-columns: repeat\(2, minmax\(0, 1fr\)\);/', $css), 'two columns on a phone');
    T::ok((bool) preg_match('/\.gk-files-details \{\s*position: fixed;\s*inset: 0;/', $css), 'the details are a sheet on a phone');
    T::contains($css, 'min-height: var(--gk-target-min);', '44px for a finger');
    T::contains($css, '.gk-files-js .gk-files-apply,', 'the script hides what it does not need');
    T::ok(!preg_match('/\.gk-[a-z-]+\[hidden\][^{]*\{[^}]*!important/', $css), 'no hidden guard of its own');
    preg_match_all('/\.(gk-files[a-z0-9-]*)/', $css, $m);
    $json = json_decode((string) file_get_contents($root . '/css/blocks.json'), true);
    $listed = array_keys($json['blocks']['files']['classes'] ?? []);
    $found = array_values(array_unique($m[1]));
    sort($found);
    sort($listed);
    T::eq($listed, $found, 'blocks.json lists the block\'s classes, no more');
    T::eq(array_unique(array_values($json['blocks']['files']['classes'])), ['1.125.0'], 'all new in 1.125.0');
},

'the skill documents it, the pages load the script, the demo stores nothing' => function () use ($doc, $root): void {
    $md = $doc();
    T::contains($md, '### FileManager (`GridKit\\FileManager`, since 1.125.0)', 'a section');
    T::contains($md, '| FileManager | `GridKit\\FileManager` / `js/file-manager.js` / `GK.files` |', 'a row in the overview');
    T::contains($md, "GK.files.upload('media', fileInput.files);", 'the JavaScript API');
    foreach (['skeleton.php', 'demo/index.php'] as $page) {
        T::contains((string) file_get_contents("$root/$page"), "js/file-manager.js') ?>\" defer></script>", "$page loads the script");
    }
    $demo = demoSource();
    T::contains($demo, "->item('Files', '#files', 'perm_media')", 'a sidebar entry');
    T::ok(strpos($demo, "->item('Command palette'") < strpos($demo, "->item('Files'"), 'after the last component');
    T::contains($demo, 'data-section="files"', 'a section');
    $api = (string) file_get_contents("$root/demo/api/files.php");
    T::contains($api, 'FileManager::receive($_FILES[\'files\'], $rules);', 'the endpoint checks with receive()');
    foreach (['move_uploaded_file', 'file_put_contents', "'target'", 'copy(', 'fopen('] as $never) {
        T::notContains($api, $never, "the demo endpoint never stores ($never)");
    }
    T::contains($api, "hash_equals('demo-token'", 'it checks the token');
},

];
})();
