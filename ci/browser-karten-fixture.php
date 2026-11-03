<?php
/**
 * The page ci/browser-karten.js drives: tables in card mode (the compact card of 1.106.0)
 * in every combination the card has to hold — a link on the row, a link on the second
 * column, buttons on both sides, a checkbox, a muted first column, a first column hidden on
 * a phone, every column muted, a single column, groups with a footer, the empty state, a
 * link with a second line. One self-contained HTML document, stylesheet and script inlined,
 * so it loads from file:// with no server.
 *
 *     php ci/browser-karten-fixture.php > /tmp/f.html
 */

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use GridKit\{Lang, Table};

Lang::set('en');

$root = dirname(__DIR__);

$rows = [
    ['id' => 1, 'no' => 'A-1', 'name' => 'A rather long customer name that has to wrap on a phone GmbH & Co. KG', 'email' => 'a@example.com', 'amount' => 1200.5, 'state' => 'active'],
    ['id' => 2, 'no' => 'A-2', 'name' => 'Short', 'email' => 'b@example.com', 'amount' => 9.9, 'state' => 'draft'],
    ['id' => 3, 'no' => 'A-3', 'name' => 'Third', 'email' => 'c@example.com', 'amount' => 0, 'state' => 'inactive'],
];

$out = [];
$add = static function (string $title, Table $t) use (&$out): void {
    ob_start();
    $t->toolbar(false)->render();
    $out[] = '<h3>' . htmlspecialchars($title) . '</h3>' . ob_get_clean();
};

$add('A link on the row, on the first column, with a second line', (new Table('rl1'))->setData($rows)->rowLink('/item?id={id}')
    ->column('name', 'Name', ['sub' => 'email'])
    ->column('amount', 'Amount', ['format' => 'currency'])
    ->column('state', 'State', ['format' => 'label']));

$add('A link on the row, on the second column, the first muted', (new Table('rl2'))->setData($rows)
    ->rowLink(['href' => '/item', 'column' => 'name', 'params' => ['id' => 'id']])
    ->column('no', 'No.', ['muted' => true])
    ->column('name', 'Name')
    ->column('amount', 'Amount', ['format' => 'currency']));

$add('A link on the row and a button', (new Table('rl3'))->setData($rows)->rowLink('/item?id={id}')
    ->column('name', 'Name')
    ->column('amount', 'Amount', ['format' => 'currency'])
    ->button('edit', ['icon' => 'edit', 'position' => 'right', 'params' => ['id' => 'id']]));

$add('Checkbox, a button on the left, two on the right, the first column muted, a title with a link and a second line', (new Table('sel'))->setData($rows)->selectable('id')
    ->column('no', 'No.', ['muted' => true])
    ->column('name', 'Name', ['href' => '/c/{id}', 'sub' => 'email'])
    ->column('amount', 'Amount', ['format' => 'currency'])
    ->button('view', ['icon' => 'visibility', 'position' => 'left', 'params' => ['id' => 'id']])
    ->button('edit', ['icon' => 'edit', 'position' => 'right', 'params' => ['id' => 'id']])
    ->button('delete', ['icon' => 'delete', 'class' => 'danger', 'position' => 'right', 'params' => ['id' => 'id']]));

$add('The first column hidden on a phone', (new Table('hid'))->setData($rows)
    ->column('no', 'No.', ['hideOnMobile' => true])
    ->column('name', 'Name')
    ->column('state', 'State', ['format' => 'label']));

$add('Every column muted', (new Table('mut'))->setData($rows)
    ->column('no', 'No.', ['muted' => true])
    ->column('email', 'E-mail', ['muted' => true])
    ->button('edit', ['icon' => 'edit', 'position' => 'right', 'params' => ['id' => 'id']]));

$add('One column', (new Table('one'))->setData($rows)->column('name', 'Name'));

$add('Groups and a footer', (new Table('grp'))->setData($rows)->groupBy('state')
    ->column('name', 'Name')
    ->column('amount', 'Amount', ['format' => 'currency'])
    ->footer(['Total', ['text' => '1,210.40', 'align' => 'right']]));

$add('Empty', (new Table('emp'))->setData([])->column('name', 'Name')->column('amount', 'Amount'));

$add('A link in the cell with a second line, and a button', (new Table('hrf'))->setData($rows)
    ->column('name', 'Name', ['href' => '/c/{id}', 'sub' => 'email'])
    ->column('amount', 'Amount', ['format' => 'currency'])
    ->button('edit', ['icon' => 'edit', 'position' => 'right', 'params' => ['id' => 'id']]));

$add('Six fields, buttons on both sides (the invoice list of the demo)', (new Table('inv'))->setData([
        ['id' => 1, 'number' => 'RE-2026-001', 'customer' => 'Mustermann GmbH', 'date' => '2026-02-01', 'due' => '2026-03-01', 'total' => 1450.0, 'status' => 'paid'],
        ['id' => 2, 'number' => 'RE-2026-002', 'customer' => 'Tech Solutions AG', 'date' => '2026-02-05', 'due' => '2026-03-05', 'total' => 3200.0, 'status' => 'pending'],
    ])
    ->column('number', 'Inv. No.', ['width' => '120px', 'nowrap' => true])
    ->column('customer', 'Customer', ['nowrap' => true])
    ->column('date', 'Date', ['format' => 'date', 'width' => '100px'])
    ->column('due', 'Due', ['format' => 'date', 'width' => '100px'])
    ->column('total', 'Amount', ['format' => 'currency', 'align' => 'right'])
    ->column('status', 'Status', ['format' => 'label'])
    ->button('view', ['icon' => 'visibility', 'position' => 'left', 'params' => ['id' => 'id']])
    ->button('edit', ['icon' => 'edit', 'position' => 'right', 'params' => ['id' => 'id']])
    ->button('delete', ['icon' => 'delete', 'class' => 'danger', 'position' => 'right', 'params' => ['id' => 'id']]));

echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
   . '<meta name="viewport" content="width=device-width, initial-scale=1"><title>Cards</title>'
   . '<style>' . file_get_contents($root . '/css/gridkit.css') . '</style>'
   . '<style>body{background:var(--gk-bg);color:var(--gk-on-surface);margin:0;padding:12px;font-family:var(--gk-font)}h3{font-size:13px;margin:18px 0 6px}</style>'
   . '</head><body>' . implode("\n", $out)
   . '<script>' . file_get_contents($root . '/js/gridkit.js') . '</script></body></html>';
