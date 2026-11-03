<?php
/**
 * The page ci/browser-inline-edit.js drives (inline editing, 1.110.0): tables with editable cells
 * of every type — a setData() table that posts its changes, one that pages and searches, one with
 * a selection, one with a row link, one that only fires the event (inside a form), and a server
 * table (rows()) whose raw values travel in data-gk-raw. One self-contained HTML document,
 * stylesheet and script inlined, so it loads from file:// with no server; the POSTs go to
 * http://gk.test/cell, which the browser test answers with page.route().
 *
 *     php ci/browser-inline-edit-fixture.php > /tmp/f.html
 */

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use GridKit\{Lang, Table};

Lang::set('en');

$root = dirname(__DIR__);
$url = 'http://gk.test/cell';

$rows = [
    ['id' => 1, 'name' => 'Ada Lovelace', 'email' => 'ada@example.org', 'amount' => 1200.5, 'status' => 'paid', 'kind' => 'b2b', 'qty' => 3],
    ['id' => 2, 'name' => 'Bob', 'email' => 'bob@example.org', 'amount' => 9.9, 'status' => 'open', 'kind' => 'b2c', 'qty' => 0],
    ['id' => 3, 'name' => 'Cy', 'email' => 'cy@example.org', 'amount' => 95, 'status' => 'void', 'kind' => 'b2b', 'qty' => 12],
    ['id' => 4, 'name' => 'Dörte Ünal', 'email' => 'doerte@example.org', 'amount' => 450, 'status' => 'paid', 'kind' => 'b2c', 'qty' => 1],
    ['id' => 5, 'name' => 'Eve', 'email' => 'eve@example.org', 'amount' => 0, 'status' => 'open', 'kind' => 'gov', 'qty' => 7],
];
$labels = ['paid' => ['color' => 'green', 'text' => 'Paid'], 'open' => ['color' => 'orange', 'text' => 'Open'], 'void' => 'red'];
$kinds = ['b2b' => 'Business', 'b2c' => 'Consumer']; // row 5 holds 'gov', which is not on the list

$out = [];
$add = static function (string $title, Table $t) use (&$out): void {
    ob_start();
    $t->render();
    $out[] = '<h3>' . htmlspecialchars($title) . '</h3>' . ob_get_clean();
};

$add('A setData() table that posts its changes', (new Table('inv'))->setData($rows)
    ->editable('id', $url, ['headers' => ['X-CSRF-Token' => 'tok-123']])
    ->column('name', 'Name', ['edit' => 'text', 'sortable' => true, 'sub' => 'email'])
    ->column('amount', 'Amount', ['format' => 'currency', 'edit' => 'number', 'sortable' => true])
    ->column('status', 'Status', ['format' => 'label', 'labels' => $labels, 'edit' => 'select'])
    ->column('kind', 'Kind', ['edit' => 'select', 'options' => $kinds])
    ->column('qty', 'Qty', ['format' => 'number', 'edit' => 'number'])
    ->button('view', ['icon' => 'visibility', 'position' => 'right', 'params' => ['id' => 'id']]));

$add('Paged and searched', (new Table('pg'))->setData($rows)->paginate(2)->search(['name'])
    ->editable('id', $url)
    ->column('name', 'Name', ['edit' => 'text'])
    ->column('amount', 'Amount', ['format' => 'currency', 'edit' => 'number']));

$add('With a selection under the same key', (new Table('sel'))->setData(array_slice($rows, 0, 3))
    ->selectable('id')->editable('id', $url)
    ->column('name', 'Name', ['edit' => 'text'])
    ->column('amount', 'Amount', ['format' => 'currency'])
    ->column('qty', 'Qty', ['format' => 'number', 'edit' => 'number', 'width' => '40px'])); // narrower than a field

$add('A row link on the name, the amount editable', (new Table('rl'))->setData(array_slice($rows, 0, 3))
    ->rowLink('#row-{id}')->editable('id', $url)
    ->column('name', 'Name')
    ->column('amount', 'Amount', ['format' => 'currency', 'edit' => 'number']));

// The fourth row has no id: it is not editable, on the server and after a rebuild.
$add('Event only (no URL), inside a form', (new Table('evt'))->setData([...array_slice($rows, 0, 3), ['id' => '', 'name' => 'Not saved yet', 'status' => 'open']])
    ->editable()
    ->column('name', 'Name', ['edit' => 'text'])
    ->column('status', 'Status', ['format' => 'label', 'labels' => $labels, 'edit' => 'select'])
    ->column('qty', 'Qty', ['format' => 'number', 'edit' => 'number']));

$add('A server table (rows())', (new Table('srv'))->rows(array_slice($rows, 0, 3), 3)
    ->editable('id', $url)
    ->column('name', 'Name', ['edit' => 'text', 'sub' => 'email'])
    ->column('amount', 'Amount', ['format' => 'currency', 'edit' => 'number'])
    ->column('status', 'Status', ['format' => 'label', 'labels' => $labels, 'edit' => 'select']));

// The event-only table sits in a form: Enter in its field must not submit it. novalidate, as admin
// forms often have it: otherwise a number field holding no number blocks the submission by itself.
$out[4] = '<form id="evt-form" action="#submitted" novalidate onsubmit="window._submitted = true; return false;">' . $out[4] . '</form>';

echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
   . '<meta name="viewport" content="width=device-width, initial-scale=1"><title>Inline editing</title>'
   . '<style>' . file_get_contents($root . '/css/gridkit.css') . '</style>'
   . '<style>body{background:var(--gk-bg);color:var(--gk-on-surface);margin:0;padding:12px;font-family:var(--gk-font)}h3{font-size:13px;margin:18px 0 6px}</style>'
   . '</head><body class="gk-root">' . Lang::jsConfig() . '<button id="elsewhere" type="button">elsewhere</button>' . implode("\n", $out)
   . '<script>' . file_get_contents($root . '/js/gridkit.js') . '</script></body></html>';
