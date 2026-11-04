<?php
/**
 * The page ci/browser-filter-builder.js drives (GridKit\FilterBuilder, 1.123.0): an invoice list filtered by
 * a builder with every type of field, three saved views and a Save view that posts to http://gk.test/save;
 * a second, small builder that keeps its views in the browser; the rows that pass, found with matches().
 * One self-contained HTML document — both stylesheets and the scripts inlined.
 *
 * The query string comes as the second argument (the browser test runs this file for every request, so the
 * form without script really goes round the server):
 *
 *     php ci/browser-filter-builder-fixture.php ''|nojs|de '<query string>' > /tmp/f.html
 *
 * window.GK_TEST_PARITY holds states with what PHP makes of them — the checked tree as compact JSON, the
 * summary, and the group the form draws — for the browser test to compare with the script.
 */

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use GridKit\{FilterBuilder, Lang};

$mode = $argv[1] ?? '';
parse_str($argv[2] ?? '', $query);
$_GET = $query;
Lang::set($mode === 'de' ? 'de' : 'en');
$root = dirname(__DIR__);

$statuses = ['open' => 'Open', 'paid' => 'Paid', 'overdue' => 'Overdue', 'draft' => 'Draft'];
$build = static function () use ($statuses): FilterBuilder {
    return (new FilterBuilder('f'))
        ->field('status', 'Status', ['type' => 'select', 'options' => $statuses])
        ->field('amount', 'Amount', ['type' => 'money'])
        ->field('qty', 'Quantity', ['type' => 'number', 'operators' => ['eq', 'ne', 'lt', 'lte', 'gt', 'gte', 'between', 'empty']])
        ->field('customer', 'Customer', ['placeholder' => 'Name'])
        ->field('due', 'Due', ['type' => 'date'])
        ->field('tags', 'Tags', ['type' => 'multiselect', 'options' => ['vip' => 'VIP', 'eu' => 'EU', 'rc' => 'Reverse charge']])
        ->field('reminded', 'Reminded', ['type' => 'bool'])
        ->field('evil', '<img src=x onerror="window.__pwned=1">', ['type' => 'select', 'options' => ['<b>x</b>' => '"><svg onload="window.__pwned=2">']])
        ->value(['join' => 'and', 'rules' => [
            ['field' => 'status', 'op' => 'any_of', 'value' => ['open', 'overdue']],
            ['field' => 'amount', 'op' => 'gt', 'value' => 100],
        ]])
        ->views([
            ['label' => 'My open invoices', 'state' => '["and",["status","is","open"]]'],
            ['label' => 'Overdue or reminded', 'state' => '["or",["status","is","overdue"],["reminded","true"]]'],
            ['label' => '<i>Hostile</i> "view"', 'state' => '["and",["customer","contains","<script>"]]'],
        ])
        ->saveUrl('http://gk.test/save')
        ->preserve(['sort']);
};
$fb = $build();

$customers = ['Ecklund & Partner', 'Müller Logistik', 'Nordlicht Media', 'Bäckerei Groß', 'Café Crème', 'Atelier Sonnfeld'];
$rows = [];
foreach (range(1, 24) as $i) {
    $rows[] = [
        'number' => sprintf('INV-%04d', 180 + $i), 'customer' => $customers[($i * 5) % 6],
        'status' => array_keys($statuses)[($i * 7) % 4], 'amount' => 80 + (($i * 337) % 2400) + ($i % 3) * 0.5,
        'qty' => $i % 7, 'due' => sprintf('2026-%02d-%02d', 8 + ($i % 5), 1 + ($i * 3) % 28),
        'tags' => array_values(array_filter([$i % 3 === 0 ? 'vip' : null, $i % 4 === 1 ? 'eu' : null])), 'reminded' => $i % 4 === 3,
    ];
}
$tree = $fb->tree();
$found = array_values(array_filter($rows, static fn (array $r): bool => FilterBuilder::matches($tree, $r, $fb)));

$small = (new FilterBuilder('g'))
    ->field('name', 'Name')
    ->field('active', 'Active', ['type' => 'bool'])
    ->maxDepth(2)->maxRows(4)
    ->localViews()
    ->label('People filter');

// States for PHP = JS: well-formed, odd and hostile; each with what PHP makes of it.
$states = [
    '["and",["status","is","open"],["or",["amount","between",[500,"100"]],["customer","starts"," Eck "],["due","lt","2026-11-04"]]]',
    '["or",["qty","eq","007"],["qty","between",["3","1,5"]],["tags","has_all",["eu","vip","eu","nope"]],["reminded","false"]]',
    '{"join":"OR","rules":[{"field":"status","op":"any_of","value":["paid","draft"]},{"join":"and","rules":[{"field":"amount","op":"gt","value":"19,99"}]}]}',
    '["and",["nope","eq",1],["status","gt","open"],["amount","gt","abc"],["due","eq","2026-02-30"],["customer","contains",""],["status","any_of",[]],["evil","is","<b>x</b>"]]',
    '["and",["and",["and",["and",["amount","gt",1]]]],["customer","contains","' . str_repeat('x', 250) . '"]]',
    '["and",["customer","contains","Tab\tand\u0000null"],["amount","eq",1e20],["amount","eq",-0.5],["amount","eq",2.0],["qty","lte",12.3456789]]',
    '["or"]',
    '["and",["amount","between",["","7"]],["qty","eq",""],["status","is",""],["tags","has_any",[]]]',
    'not json',
    '["and",["customer","equals","a \"quoted\" <tag> & ampersand"],["due","between",["2026-12-31","2026-01-01"]]]',
];
$parity = [];
foreach ($states as $s) {
    $h = $build()->request(['f' => $s])->html();
    preg_match('~(<fieldset class="gk-fb-group".*</fieldset>)<p class="gk-fb-note"~s', $h, $m);
    $parity[] = ['state' => $s, 'json' => FilterBuilder::encode(FilterBuilder::clean($s, $fb)),
        'summary' => FilterBuilder::summary($s, $fb), 'group' => $m[1] ?? ''];
}

$table = '<table class="gk-table" id="result"><caption>' . count($found) . ' of ' . count($rows) . ' invoices</caption><tbody>';
foreach ($found as $r) {
    $table .= '<tr><td>' . htmlspecialchars($r['number']) . '</td><td>' . htmlspecialchars($r['customer']) . '</td><td>' . $r['status'] . '</td><td>' . $r['amount'] . '</td></tr>';
}
$table .= '</tbody></table>';

echo '<!doctype html><html lang="' . ($mode === 'de' ? 'de' : 'en') . '"><head><meta charset="utf-8">'
   . '<meta name="viewport" content="width=device-width, initial-scale=1"><title>Filter builder</title>'
   . '<style>' . file_get_contents($root . '/css/gridkit.css') . '</style>'
   . '<style>' . file_get_contents($root . '/css/themes.css') . '</style>'
   . '<style>main{padding:16px;max-width:1100px}</style>'
   . Lang::jsConfig()
   . '</head><body class="gk-root" data-gk-theme="indigo" data-gk-mode="light"><main class="gk-main" id="main"><h1>Invoices</h1>'
   . '<p id="tree">' . htmlspecialchars(FilterBuilder::encode($tree)) . '</p>'
   . $fb->html()
   . $table
   . '<h2>People</h2>' . $small->html()
   . '</main>'
   . '<script>window.GK_TEST_PARITY = ' . json_encode($parity, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . ';</script>'
   . ($mode === 'nojs' ? '' : '<script>' . file_get_contents($root . '/js/gridkit.js') . '</script>'
        . '<script>' . file_get_contents($root . '/js/filter-builder.js') . '</script>')
   . '</body></html>';
