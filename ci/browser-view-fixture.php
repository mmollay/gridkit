<?php
/**
 * The page ci/browser-view.js drives (the view toggle, 1.132.0): one toggle "lists" for two
 * scopes (a table and cards each, the table the default), a second toggle "solo" with its own
 * default (cards), a scope whose toggle is missing, and a host the test hands a scope to later
 * (GK.initContent). One self-contained HTML document, stylesheet and script inlined, so it loads
 * from file:// with no server.
 *
 *     php ci/browser-view-fixture.php [--no-js] > f.html
 */

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use GridKit\{Lang, Table};

Lang::set('en');

$root = dirname(__DIR__);
$rows = [
    ['id' => 1, 'name' => 'Carla', 'state' => 'active'],
    ['id' => 2, 'name' => 'Anton', 'state' => 'draft'],
];

$toggle = static fn (string $id, string $default, string $label): string =>
    '<div class="gk-segmented" role="group" aria-label="' . $label . '" data-gk-view-toggle="' . $id . '" data-gk-view-default="' . $default . '" hidden>'
    . '<button type="button" class="gk-segmented-item" data-gk-set-view="table" aria-pressed="false">Table</button>'
    . '<button type="button" class="gk-segmented-item" data-gk-set-view="cards" aria-pressed="false">Cards</button>'
    . '</div>';

/** A scope with both drawings: the one named $default stands, the other is hidden, as the server writes it. */
$scope = static function (string $id, string $tag, string $default) use ($rows): string {
    ob_start();
    (new Table($tag . '-t'))->setData($rows)->column('name', 'Name')->column('state', 'State')->render();
    $table = (string) ob_get_clean();
    $cards = '<div class="gk-card"><p>Cards of ' . $tag . '</p></div>';
    return '<section data-gk-view-scope="' . $id . '" id="s-' . $tag . '">'
         . '<div data-gk-view="table"' . ($default === 'table' ? '' : ' hidden') . '>' . $table . '</div>'
         . '<div data-gk-view="cards"' . ($default === 'cards' ? '' : ' hidden') . '>' . $cards . '</div>'
         . '</section>';
};

$css = (string) file_get_contents($root . '/css/gridkit.css');
$js = (string) file_get_contents($root . '/js/gridkit.js');
$json = static fn (mixed $v): string => (string) json_encode($v, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE);
$later = $scope('lists', 'later', 'table');

echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
   . '<meta name="viewport" content="width=device-width, initial-scale=1"><title>View toggle</title>'
   . '<style>' . $css . '</style>'
   . '<style>body{background:var(--gk-bg);color:var(--gk-on-surface);margin:0;padding:12px;font-family:var(--gk-font)}</style>'
   . '</head><body class="gk-root" data-gk-theme="indigo" data-gk-mode="light">'
   . $toggle('lists', 'table', 'View of the lists')
   . $scope('lists', 'a', 'table') . $scope('lists', 'b', 'table')
   . $toggle('solo', 'cards', 'View of the solo list') . $scope('solo', 'c', 'cards')
   . $scope('nobody', 'd', 'table')
   . '<div id="later-host"></div>'
   . '<script type="application/json" id="gk-fixture">' . $json(['later' => $later]) . '</script>'
   . (in_array('--no-js', $argv, true) ? '' : '<script>' . $js . '</script>') . '</body></html>';
