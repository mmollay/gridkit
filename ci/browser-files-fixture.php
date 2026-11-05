<?php
/**
 * The page ci/browser-files.js drives (GridKit\FileManager, 1.125.0): a media library with folders, every
 * action, an upload and a hostile file name; a receipt drawer with single selection in a list. One
 * self-contained HTML document — both stylesheets and the scripts inlined. The browser test serves it as
 * http://gk.test/fixture.html with page.route() and answers the uploads and actions there.
 *
 *     php ci/browser-files-fixture.php [nojs] > /tmp/f.html
 */

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use GridKit\{FileManager, Lang};

Lang::set('en');
$root = dirname(__DIR__);
$mode = $argv[1] ?? '';
// The query of the address the page was asked for: the server draws what the links and the toolbar ask for.
parse_str($argv[2] ?? '', $_GET);

$pic = static function (int $n): string {
    $hue = ($n * 47) % 360;
    return 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="160" height="120"><rect width="160" height="120" fill="hsl(' . $hue . ' 55% 55%)"/></svg>');
};
$t = 1790000000;
$files = [
    ['id' => 'a', 'name' => 'Alpine lake.jpg', 'size' => 482113, 'mime' => 'image/jpeg', 'modified' => $t - 86400, 'url' => 'http://gk.test/img/a.png', 'thumbUrl' => $pic(1), 'width' => 2400, 'height' => 1600],
    ['id' => 'b', 'name' => 'Budget 2027.xlsx', 'size' => 31000, 'modified' => $t - 3 * 86400],
    ['id' => 'c', 'name' => 'Contract (signed).pdf', 'size' => 1260000, 'mime' => 'application/pdf', 'modified' => $t - 9 * 86400, 'url' => 'http://gk.test/doc/c.pdf'],
    ['id' => 'd', 'name' => 'Desk.png', 'size' => 20480, 'mime' => 'image/png', 'modified' => $t, 'url' => 'http://gk.test/img/d.png'],
    ['id' => 'e', 'name' => 'Evening.webp', 'size' => 99000, 'mime' => 'image/webp', 'modified' => $t - 5 * 86400, 'thumbUrl' => $pic(5), 'url' => 'http://gk.test/img/e.png'],
    ['id' => 'x', 'name' => '<img src=x onerror="window.__pwned=1">.png', 'size' => 10, 'mime' => 'image/png', 'modified' => $t - 7 * 86400],
    ['id' => 'r1', 'name' => 'Receipt 2026-10-02.pdf', 'size' => 96000, 'mime' => 'application/pdf', 'modified' => $t - 30 * 86400, 'folder' => 'r'],
    ['id' => 'r2', 'name' => 'Receipt 2026-10-14.pdf', 'size' => 64000, 'mime' => 'application/pdf', 'modified' => $t - 20 * 86400, 'folder' => 'r'],
];

$media = (new FileManager('media'))
    ->label('Media')
    ->folders([
        ['id' => 'r', 'name' => 'Receipts', 'parent' => null, 'count' => 2],
        ['id' => 'm', 'name' => 'Marketing', 'parent' => null],
    ])
    ->files($files)
    ->uploadUrl('http://gk.test/upload')
    ->accept(['image/*', 'application/pdf'])->maxSize('64K')->maxFiles(4)->parallel(2)
    ->actions(['delete' => 'http://gk.test/act', 'move' => 'http://gk.test/act', 'rename' => 'http://gk.test/act',
               'folder' => 'http://gk.test/act', 'download' => true])
    ->csrf('tok-files')->hidden(['tenant' => '7'])
    ->fromQuery();

$pick = (new FileManager('pick'))
    ->label('Receipts')
    ->files(array_slice($files, 6), [])
    ->param('pick')
    ->select('single')->selected(['r2'])
    ->view('list')->sort('date', 'desc');

echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
   . '<meta name="viewport" content="width=device-width, initial-scale=1"><title>Files</title>'
   . '<style>' . file_get_contents($root . '/css/gridkit.css') . '</style>'
   . '<style>' . file_get_contents($root . '/css/themes.css') . '</style>'
   . '<style>main{padding:16px;max-width:1240px;margin:0 auto}h2{margin:32px 0 8px}</style>'
   . Lang::jsConfig()
   . '</head><body class="gk-root" data-gk-theme="indigo" data-gk-mode="light">'
   . '<main class="gk-main" id="main"><h1>Files</h1><button type="button" id="before">before</button>'
   . $media->html()
   . '<h2>Pick one</h2>' . $pick->html()
   . '<p style="height:200px"></p></main>'
   . ($mode === 'nojs' ? '' : '<script>' . file_get_contents($root . '/js/gridkit.js') . '</script>'
        . '<script>' . file_get_contents($root . '/js/file-manager.js') . '</script>')
   . '</body></html>';
