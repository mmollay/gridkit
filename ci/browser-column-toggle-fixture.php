<?php
/**
 * The page ci/browser-column-toggle.js drives (Table::columnToggle(), 1.108.0): tables with a
 * column menu in every combination it has to hold — a checkbox, buttons on both sides, groups, a
 * footer with cells over one column and over several, a column that cannot be hidden, one that
 * starts hidden, the empty state, more than twenty columns, a row link, a server-driven table —
 * and one table without the menu. One self-contained HTML document, stylesheet and script inlined,
 * so it loads from file:// with no server.
 *
 *     php ci/browser-column-toggle-fixture.php > /tmp/f.html
 *     php ci/browser-column-toggle-fixture.php --old-js         # the script without the 1.108.0 block
 *     php ci/browser-column-toggle-fixture.php --no-script      # no script: the test adds it after the load
 *     php ci/browser-column-toggle-fixture.php --fragment 2     # what the server table's AJAX reload of page 2 answers
 *     php ci/browser-column-toggle-fixture.php --csv '<rows json>' '<columns json>'
 *
 * --fragment is Table::render() under the conditions of an AJAX reload, so the browser test puts
 * the real answer of the server where the reload expects it. --csv prints Table::csv() of what it
 * is given, to compare the file the browser builds with.
 */

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use GridKit\{Lang, Table};

Lang::set('en');

if (($argv[1] ?? '') === '--csv') {
    echo Table::csv(
        json_decode($argv[2], true, 512, JSON_THROW_ON_ERROR),
        json_decode($argv[3], true, 512, JSON_THROW_ON_ERROR),
    );
    exit;
}

$root = dirname(__DIR__);

$rows = [
    ['id' => 1, 'no' => 'K-1', 'name' => 'Ada Lovelace', 'email' => 'ada@example.org', 'city' => 'London', 'amount' => 1200.5, 'team' => 'a'],
    ['id' => 2, 'no' => 'K-2', 'name' => 'Bob Builder', 'email' => 'bob@example.org', 'city' => 'Berlin', 'amount' => 9.9, 'team' => 'a'],
    ['id' => 3, 'no' => 'K-3', 'name' => 'Cy Young', 'email' => 'cy@example.org', 'city' => 'Cairo', 'amount' => 300, 'team' => 'b'],
    ['id' => 4, 'no' => 'K-4', 'name' => 'Dora Explorer', 'email' => 'dora@example.org', 'city' => 'Dublin', 'amount' => 45, 'team' => 'b'],
];

/** The server table: six rows in all, three to a page. */
$server = static function (): Table {
    $all = [
        ['id' => 11, 'name' => 'Server one', 'email' => 's1@example.org', 'city' => 'Graz', 'land' => 'at'],
        ['id' => 12, 'name' => 'Server two', 'email' => 's2@example.org', 'city' => 'Linz', 'land' => 'at'],
        ['id' => 13, 'name' => 'Server three', 'email' => 's3@example.org', 'city' => 'Wels', 'land' => 'at'],
        ['id' => 14, 'name' => 'Server four', 'email' => 's4@example.org', 'city' => 'Steyr', 'land' => 'at'],
        ['id' => 15, 'name' => 'Server five', 'email' => 's5@example.org', 'city' => 'Krems', 'land' => 'at'],
        ['id' => 16, 'name' => 'Server six', 'email' => 's6@example.org', 'city' => 'Melk', 'land' => 'at'],
    ];
    $page = max(1, (int) ($_GET['gk_page'] ?? 1));
    return (new Table('srv'))->rows(array_slice($all, ($page - 1) * 3, 3), count($all))->paginate(3)
        ->column('name', 'Name')
        ->column('email', 'E-mail')
        ->column('city', 'City', ['hidden' => true])
        ->groupBy('land', ['at' => 'Austria'])
        ->footer(['Six people', 'all', 'in Austria'])
        ->exportCsv('srv.csv')
        ->columnToggle();
};

if (($argv[1] ?? '') === '--fragment') {
    $_GET = ['gk_table' => 'srv', 'gk_page' => $argv[2] ?? '2'];
    $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
    $server()->render();
    exit;
}

$out = [];
$add = static function (string $title, Table $t) use (&$out): void {
    ob_start();
    $t->render();
    $out[] = '<h3>' . htmlspecialchars($title) . '</h3>' . ob_get_clean();
};

// Places in a row: 1 checkbox, 2 left buttons, 3 no, 4 name, 5 email, 6 city, 7 amount, 8 right buttons.
// The footer: [1-2] [3-4 "Total"] [5 over email] [6 over city] [7 over amount] [8, the padding].
$add('Checkbox, buttons on both sides, groups, a footer, a fixed column, a hidden one', (new Table('cols'))
    ->setData($rows)->selectable('id')
    ->search(['name', 'city'])
    ->groupBy('team', ['a' => 'Team A', 'b' => 'Team B'])
    ->column('no', 'No.', ['muted' => true, 'toggle' => false])
    ->column('name', 'Name', ['sortable' => true])
    ->column('email', 'E-mail', ['hidden' => true])
    ->column('city', 'City', ['sortable' => true, 'sub' => 'team'])
    ->column('amount', 'Amount', ['format' => 'currency', 'sortable' => true])
    ->button('view', ['icon' => 'visibility', 'position' => 'left', 'params' => ['id' => 'id']])
    ->button('edit', ['icon' => 'edit', 'position' => 'right', 'params' => ['id' => 'id']])
    ->footer([['text' => '', 'colspan' => 2], ['text' => 'Total', 'colspan' => 2], 'mails', 'cities', ['text' => '1,555.40', 'align' => 'right']])
    ->exportCsv('cols.csv')
    ->columnToggle());

// The same table in the box of stickyHead()/stickyColumn() (1.107.0): the table is no longer a child
// of the wrap, and until 1.110.1 nothing was hidden there.
$add('In the sticky box', (new Table('colbox'))
    ->setData($rows)->selectable('id')
    ->groupBy('team', ['a' => 'Team A', 'b' => 'Team B'])
    ->column('no', 'No.', ['muted' => true, 'toggle' => false])
    ->column('name', 'Name', ['sortable' => true])
    ->column('email', 'E-mail', ['hidden' => true])
    ->column('city', 'City', ['sortable' => true])
    ->column('amount', 'Amount', ['format' => 'currency', 'sortable' => true])
    ->button('view', ['icon' => 'visibility', 'position' => 'left', 'params' => ['id' => 'id']])
    ->button('edit', ['icon' => 'edit', 'position' => 'right', 'params' => ['id' => 'id']])
    ->stickyHead('300px')->stickyColumn()
    ->columnToggle());

$add('The empty state', (new Table('none'))->setData([])
    ->column('name', 'Name')->column('city', 'City', ['hidden' => true])->column('email', 'E-mail')
    ->columnToggle());

$wideRow = ['id' => 1];
for ($i = 1; $i <= 22; $i++) $wideRow["c$i"] = "v$i";
$wide = (new Table('wide'))->setData([$wideRow])->mobile('scroll');
for ($i = 1; $i <= 22; $i++) $wide->column("c$i", "C$i", $i === 22 ? ['hidden' => true] : []);
$add('Twenty-two columns, the last one hidden', $wide->columnToggle());

// The spacer puts the button at the right edge, and a long heading makes the menu wider than the
// room right of the button: the menu has to move left to stay in the window.
$add('A row link: its column cannot be hidden', (new Table('lnk'))->setData($rows)->rowLink('/people/{id}')
    ->toolbarHtml('<div class="gk-toolbar-spacer"></div>')
    ->column('name', 'Name')->column('city', 'City of residence, as the contract gives it')->columnToggle());

$add('A server-driven table, three rows to a page', $server());

$add('A table without the menu', (new Table('plain'))->setData($rows)->column('name', 'Name')->column('city', 'City'));

// A table that arrives later: page code puts it in and hands it to GK.initContent().
ob_start();
(new Table('late'))->setData($rows)->column('name', 'Name')->column('city', 'City')->column('email', 'E-mail')->columnToggle()->render();
$out[] = '<template id="late-table">' . ob_get_clean() . '</template><div id="late"></div>';

$js = (string) file_get_contents($root . '/js/gridkit.js');
if (in_array('--no-script', $argv, true)) {
    // For a script added after the page has loaded: the test adds it.
    $js = '';
} elseif (in_array('--old-js', $argv, true)) {
    // The copy the PawBot dashboard keeps: everything up to this release's block.
    $cut = strpos($js, '// === 1.108.0 — column toggle');
    $js = $cut === false ? $js : substr($js, 0, $cut);
}

echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
   . '<meta name="viewport" content="width=device-width, initial-scale=1"><title>Columns</title>'
   . '<style>' . file_get_contents($root . '/css/gridkit.css') . '</style>'
   . '<style>body{background:var(--gk-bg);color:var(--gk-on-surface);margin:0;padding:12px;font-family:var(--gk-font)}h3{font-size:13px;margin:18px 0 6px}</style>'
   . '</head><body class="gk-root">' . Lang::jsConfig() . implode("\n", $out)
   . '<p><a href="#end" id="after">after the tables</a></p>'
   . ($js === '' ? '' : '<script>' . $js . '</script>') . '</body></html>';
