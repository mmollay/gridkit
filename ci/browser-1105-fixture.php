<?php
/**
 * The page ci/browser-1105.js drives (the additions of 1.105.0): tables with bulk actions
 * and CSV export, and a form with conditional fields. One self-contained HTML document,
 * stylesheet and script inlined, so it loads from file:// with no server.
 *
 *     php ci/browser-1105-fixture.php > /tmp/f.html
 *     php ci/browser-1105-fixture.php --csv '<rows json>' '<columns json>' [delimiter]
 *
 * The second form prints Table::csv() of what it is given: the browser test compares the file
 * the script builds with what PHP writes for the same rows, byte for byte.
 */

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use GridKit\{Form, Lang, Table};

Lang::set('en');

if (($argv[1] ?? '') === '--csv') {
    echo Table::csv(
        json_decode($argv[2], true, 512, JSON_THROW_ON_ERROR),
        json_decode($argv[3], true, 512, JSON_THROW_ON_ERROR),
        ['delimiter' => $argv[4] ?? ','],
    );
    exit;
}

$root = dirname(__DIR__);

$rows = [
    ['id' => 1, 'name' => 'Ada Lovelace',      'email' => 'ada@example.org',    'note' => '=1+1',       'qty' => 3,    'secret' => 's1'],
    ['id' => 2, 'name' => 'Bob "the builder"', 'email' => 'bob@example.org',    'note' => 'a,b',        'qty' => -5,   'secret' => 's2'],
    ['id' => 3, 'name' => 'Cy',                'email' => 'cy@example.org',     'note' => "two\nlines", 'qty' => 12.5, 'secret' => 's3'],
    ['id' => 4, 'name' => 'Dörte Ünal',        'email' => 'doerte@example.org', 'note' => '@SUM(A1)',   'qty' => 0,    'secret' => 's4'],
    ['id' => 5, 'name' => 'Eve',               'email' => 'eve@example.org',    'note' => '+49 123',    'qty' => 7,    'secret' => 's5'],
];

ob_start();
(new Table('people'))->setData($rows)
    ->search(['name'])
    ->column('name', 'Name', ['sortable' => true])
    ->column('email', 'Email')
    ->column('note', 'Note')
    ->column('qty', 'Qty', ['sortable' => true])
    ->column('secret', 'Secret', ['export' => false])
    ->bulkAction('archive', ['icon' => 'archive', 'label' => 'Archive'])
    ->bulkAction('void', ['confirm' => 'Void these rows?', 'color' => 'danger'])
    ->exportCsv('people.csv')
    ->render();
echo '<hr>';
(new Table('semi'))->setData(array_slice($rows, 0, 2))
    ->column('name', 'Name')
    ->column('note', 'Note')
    ->exportCsv('semi.csv', ['delimiter' => ';'])
    ->render();
echo '<hr>';
(new Table('server'))->rows(array_slice($rows, 0, 3), 5)->paginate(3)
    ->column('name', 'Name')
    ->exportCsv('server.csv')
    ->render();
$tables = ob_get_clean();

ob_start();
(new Form('cond'))->action('/x')
    ->field('kind', 'Kind', 'select', ['options' => ['person' => 'Person', 'company' => 'Company', 'other' => 'Other'], 'native' => true, 'value' => 'person'])
    ->field('company', 'Company name', 'text', ['required' => true, 'showIf' => ['field' => 'kind', 'equals' => 'company']])
    ->field('vat', 'VAT id', 'text', ['showIf' => ['field' => 'company', 'filled' => true]])
    ->field('plain', 'Not a person', 'text', ['showIf' => ['field' => 'kind', 'not' => 'person']])
    ->field('newsletter', 'Newsletter', 'toggle')
    ->field('email', 'Email', 'text', ['showIf' => ['field' => 'newsletter', 'equals' => '1']])
    ->field('channel', 'Channel', 'radio', ['options' => ['a' => 'A', 'b' => 'B'], 'value' => 'a'])
    ->field('detail', 'Detail', 'text', ['showIf' => ['field' => 'channel', 'in' => ['b', 'c']]])
    ->render();
$cond = ob_get_clean();

$head = '<head><meta charset="utf-8"><title>1.105.0</title><style>' . file_get_contents($root . '/css/gridkit.css') . '</style></head>';
$script = '<script>' . file_get_contents($root . '/js/gridkit.js') . '</script>';

echo '<!DOCTYPE html><html lang="en">' . $head . '<body class="gk-root">' . Lang::jsConfig()
   . '<main style="padding:16px;max-width:900px">' . $tables . '<hr>' . $cond . '<div id="late"></div></main>'
   . $script . '</body></html>';
