<?php
/**
 * The page ci/browser-sticky.js drives: tables with Table::stickyHead() and
 * Table::stickyColumn() (1.107.0) — a head on the page and one in a box, a column with and
 * without the checkbox and the left buttons in front of it, both at once, a setData() table
 * that is rebuilt in the browser, a server table that is reloaded, the variants that paint
 * rows of their own, groups and a footer, a row of an open side sheet, card mode next to a
 * twin without the options, mobile('scroll') and a list. One self-contained HTML document,
 * stylesheet and script inlined, so it loads from file:// with no server.
 *
 *     php ci/browser-sticky-fixture.php > /tmp/f.html
 *     php ci/browser-sticky-fixture.php --fragment   # what the server table answers a reload
 */

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use GridKit\{Lang, Table};

Lang::set('en');

$root = dirname(__DIR__);

/** Forty rows, wide enough in every column that a table of all of them scrolls sideways. */
$rows = [];
$cities = ['Wien', 'Graz', 'Linz', 'Salzburg', 'Innsbruck', 'Klagenfurt'];
$states = ['active', 'draft', 'inactive', 'paid'];
for ($i = 1; $i <= 40; $i++) {
    $rows[] = [
        'id'      => $i,
        'no'      => sprintf('A-%03d', $i),
        'name'    => "Customer number $i with a longer name",
        'email'   => "customer$i@example.org",
        'city'    => $cities[$i % 6],
        'amount'  => $i * 101.25,
        'qty'     => $i * 3,
        'state'   => $states[$i % 4],
        'created' => sprintf('2026-%02d-%02d', ($i % 12) + 1, ($i % 27) + 1),
        'note'    => "A note that runs on for a while, row $i",
        'owner'   => 'Owner ' . chr(65 + $i % 26),
        'group'   => $i <= 20 ? 'North' : 'South',
    ];
}

/** Every column of a wide table; nowrap() keeps each on one line, so the table is wider than the page. */
$wide = static function (Table $t): Table {
    return $t->column('name', 'Name', ['sortable' => true])
        ->column('no', 'No.', ['sortable' => true])
        ->column('email', 'Email')
        ->column('city', 'City', ['sortable' => true])
        ->column('amount', 'Amount', ['format' => 'currency', 'sortable' => true])
        ->column('qty', 'Quantity', ['format' => 'number'])
        ->column('state', 'State', ['format' => 'label'])
        ->column('created', 'Created', ['format' => 'date'])
        ->column('note', 'Note')
        ->column('owner', 'Owner')
        ->nowrap();
};
$buttons = static function (Table $t): Table {
    return $t->button('view', ['icon' => 'visibility', 'position' => 'left', 'params' => ['id' => 'id']])
        ->button('copy', ['icon' => 'content_copy', 'position' => 'left', 'params' => ['id' => 'id']])
        ->button('edit', ['icon' => 'edit', 'params' => ['id' => 'id']])
        ->button('delete', ['icon' => 'delete', 'color' => 'danger', 'params' => ['id' => 'id']]);
};

/** The server table, the same way on the page and in the fragment a reload gets. */
$server = static function () use ($rows, $wide): Table {
    $sorted = $rows;
    if (($_GET['gk_sort'] ?? '') === 'name') usort($sorted, static fn ($a, $b) => strcmp($b['name'], $a['name']));
    return $wide((new Table('sv')))->rows(array_slice($sorted, 0, 12), 40)
        ->selectable('id')->stickyHead('300px')->stickyColumn()->caption('Server table');
};

if (in_array('--fragment', $argv ?? [], true)) {
    $_GET = ['gk_table' => 'sv', 'gk_sort' => 'name', 'gk_dir' => 'desc'];
    $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
    $server()->render();
    exit;
}

$out = [];
$add = static function (string $title, Table $t, string $style = '') use (&$out): void {
    ob_start();
    $t->render();
    $out[] = '<section' . ($style !== '' ? ' style="' . $style . '"' : '') . '><h3>' . htmlspecialchars($title) . '</h3>' . ob_get_clean() . '</section>';
};

$add('A head on the page', (new Table('hp'))->setData($rows)
    ->column('name', 'Name', ['sortable' => true])->column('email', 'Email')->column('city', 'City')
    ->column('amount', 'Amount', ['format' => 'currency'])->stickyHead());

// Something to Tab from: the next stop is the box of 'hb' (a region with a tab stop).
$out[] = '<button type="button" id="before-hb">Before the box</button>';
$add('A head in a box of 240px', (new Table('hb'))->setData(array_slice($rows, 0, 30))
    ->column('name', 'Name')->column('email', 'Email')->column('city', 'City')->stickyHead('240px')->toolbar(false));

$add('The column behind a checkbox and two left buttons, a footer', $buttons($wide((new Table('sc'))->setData(array_slice($rows, 0, 8))))
    ->selectable('id')->stickyColumn()->toolbar(false)
    ->footer(['', '', 'Total', '', ['text' => '3,645.00', 'align' => 'right']]));

$add('The column alone', $wide((new Table('nc'))->setData(array_slice($rows, 0, 6)))->stickyColumn()->toolbar(false));

$add('The column behind the left buttons', $buttons($wide((new Table('lb'))->setData(array_slice($rows, 0, 6))))->stickyColumn()->toolbar(false));

$add('The column behind a checkbox', $wide((new Table('cb'))->setData(array_slice($rows, 0, 6)))->selectable('id')->stickyColumn()->toolbar(false));

$add('Head and column: a box of 70vh', $buttons($wide((new Table('bo'))->setData($rows)))->selectable('id')->stickyHead()->stickyColumn());

$add('A setData() table rebuilt in the browser', $buttons($wide((new Table('st'))->setData($rows)))
    ->search(['name', 'email'])->selectable('id')->paginate(15)->stickyHead('300px')->stickyColumn());

$add('A server table reloaded', $server());

foreach (['striped', 'inverted', 'minimal', 'bordered'] as $variant) {
    $add("Variant $variant", $wide((new Table("v-$variant"))->setData(array_slice($rows, 0, 6)))
        ->variant($variant)->selectable('id')->stickyHead('220px')->stickyColumn()->toolbar(false));
}

$add('Groups and a footer', $wide((new Table('gf'))->setData(array_slice($rows, 16, 8)))->groupBy('group')
    ->selectable('id')->stickyColumn()->toolbar(false)
    ->footer([['text' => 'Total', 'colspan' => 2], '', ['text' => '9,000.00', 'align' => 'right']]));

// The last cell of a row link is position: relative, a button too: both come later in the
// document than the head and the column, and would paint over them without a z-index.
$add('A row that opens a side sheet', $wide((new Table('rl'))->setData(array_slice($rows, 0, 6)))
    ->rowLink(['sheet' => 'nowhere'])->button('edit', ['icon' => 'edit', 'params' => ['id' => 'id']])
    ->stickyHead('200px')->stickyColumn()->toolbar(false));

// The same table twice, once with both options: in card mode the two must not differ.
$cardTable = static fn (string $id): Table => $buttons($wide((new Table($id))->setData(array_slice($rows, 0, 4))))
    ->selectable('id')->toolbar(false)->footer(['', '', 'Total', ['text' => '1.00', 'align' => 'right']]);
$add('Card mode with both options', $cardTable('cd')->stickyHead()->stickyColumn());
$add('Card mode without', $cardTable('cd-plain'));
$add('Card mode, a head in a box', $cardTable('cd-box')->stickyHead('200px'));
$add('Card mode, a box, without', $cardTable('cd-box-plain'));

$cardLink = static fn (string $id): Table => $wide((new Table($id))->setData(array_slice($rows, 0, 3)))
    ->rowLink(['sheet' => 'nowhere'])->toolbar(false);
$add('Card mode, rows that open a sheet, with the column', $cardLink('cd-rl')->stickyColumn());
$add('Card mode, rows that open a sheet, without', $cardLink('cd-rl-plain'));
$add('Card mode, empty, with both options', $cardTable('cd-empty')->setData([])->stickyHead()->stickyColumn());
$add('Card mode, empty, without', $cardTable('cd-empty-plain')->setData([]));

// At rest — not scrolled, narrow enough to fit — a sticky table looks exactly like its twin
// without the options: separate borders instead of collapsed ones must not show.
$narrow = static fn (string $id, string $variant): Table => $buttons((new Table($id))->setData(array_slice($rows, 18, 5)))
    ->column('name', 'Name')->column('city', 'City')->column('amount', 'Amount', ['format' => 'currency'])
    ->groupBy('group')->selectable('id')->variant($variant)->toolbar(false)
    ->footer(['', '', ['text' => 'Total', 'colspan' => 2], ['text' => '9.00', 'align' => 'right']]);
foreach (['default', 'bordered', 'celled', 'striped'] as $variant) {
    $add("At rest: $variant, both options", $narrow("rest-$variant", $variant)->stickyHead('600px')->stickyColumn());
    $add("At rest: $variant, without", $narrow("rest-$variant-plain", $variant));
}
$add('At rest: a head on the page', $narrow('rest-page', 'default')->stickyHead());

// Two buttons at the far end: on a phone they come under the column when the box is scrolled
// all the way. A footer: the last row's rounded first cell is not at the corner of the box.
$add("mobile('scroll') with the column", $wide((new Table('ms'))->setData(array_slice($rows, 0, 6)))
    ->button('edit', ['icon' => 'edit', 'params' => ['id' => 'id']])
    ->button('delete', ['icon' => 'delete', 'color' => 'danger', 'params' => ['id' => 'id']])
    ->footer(['', 'Total', ['text' => '2,126.25', 'align' => 'right', 'colspan' => 3]])
    ->selectable('id')->mobile('scroll')->stickyColumn()->toolbar(false));

$add("size('list') with both", (new Table('li'))->setData(array_slice($rows, 0, 12))->size('list')
    ->column('name', 'User', ['sub' => 'email'])->column('city', 'City', ['priority' => 2])
    ->column('state', 'State', ['priority' => 3, 'format' => 'label'])
    ->stickyHead('300px')->stickyColumn()->toolbar(false));

echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
   . '<meta name="viewport" content="width=device-width, initial-scale=1"><title>Sticky</title>'
   . '<style>' . file_get_contents($root . '/css/gridkit.css') . '</style>'
   . '<style>body{background:var(--gk-bg);color:var(--gk-on-surface);margin:0;padding:12px;font-family:var(--gk-font)}'
   . 'h3{font-size:13px;margin:18px 0 6px}section{margin-bottom:24px}</style>'
   . '</head><body>' . implode("\n", $out)
   . '<div style="height:120vh"></div>'
   . '<script>' . file_get_contents($root . '/js/gridkit.js') . '</script></body></html>';
