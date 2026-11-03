<?php
/**
 * Table::editable(), the column option 'edit' and the markup gk:celledit hooks into (1.110.0).
 *
 * Inline editing is script — opening a field, saving, the event, the request and its answers are
 * checked in a browser (ci/browser-inline-edit.js). What the script finds is checked here: the
 * wrap's data-gk-editable, the row ids, the three attributes of an editable cell (and the raw value
 * of a server table's), the sentence the cells point at, every refusal said out loud, and above all
 * that a table without an editable column is the table it was, byte for byte.
 */

declare(strict_types=1);

use GridKit\{Lang, Table};

function tieRows(): array
{
    return [
        ['id' => 1, 'name' => 'Ada', 'amount' => 1200.5, 'status' => 'paid', 'ok' => true, 'note' => null],
        ['id' => 2, 'name' => 'Bob "B" <b>', 'amount' => 0, 'status' => 'open', 'ok' => false, 'note' => 'x'],
    ];
}

/** Render, and keep every warning instead of printing it. @return array{0:string,1:list<string>} */
function tieRender(Table $t): array
{
    $warnings = [];
    set_error_handler(static function (int $n, string $m) use (&$warnings): bool {
        $warnings[] = $m;
        return true;
    });
    try {
        $html = T::capture(static function () use ($t): void { $t->render(); });
    } finally {
        restore_error_handler();
    }
    return [$html, $warnings];
}

/** The decoded data-gk-editable of the first table in $html, or null. */
function tieConfig(string $html): ?array
{
    if (preg_match('/data-gk-editable="([^"]*)"/', $html, $m) !== 1) return null;
    return json_decode(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
}

/** The opening tags of the cells of one row, by row id. @return list<string> */
function tieCells(string $html, string $id): array
{
    if (preg_match('/<tr[^>]*data-gk-row-id="' . preg_quote($id, '/') . '"[^>]*>(.*?)<\/tr>/s', $html, $m) !== 1) return [];
    preg_match_all('/<td[^>]*>/', $m[1], $c);
    return $c[0];
}

/** @return array<string,callable> */
return [

// ── Nothing changes for a table that edits nothing ─────────────────────

'a table without an editable column renders byte for byte as without editable()' => function (): void {
    Lang::set('en');
    $build = static fn (): Table => (new Table('t'))->setData(tieRows())
        ->column('name', 'Name', ['sortable' => true])
        ->column('amount', 'Amount', ['format' => 'currency'])
        ->button('edit', ['icon' => 'edit', 'params' => ['id' => 'id']]);
    [$plain] = tieRender($build());
    [$with, $w] = tieRender($build()->editable('id', '/save.php'));
    T::eq($with, $plain, 'editable() alone changed the markup');
    T::eq($w, [], 'editable() alone warned');

    // 'edit' => false, null or '' is "not editable" — a permission switching a column off.
    // (The data block echoes the option as given, so each value is compared with its own table.)
    foreach ([false, null, ''] as $v) {
        [$base] = tieRender($build()->column('note', 'Note', ['edit' => $v]));
        [$h, $w] = tieRender($build()->editable()->column('note', 'Note', ['edit' => $v]));
        T::eq($h, $base, "'edit' => " . var_export($v, true) . ' made something editable');
        T::eq($w, [], "'edit' => " . var_export($v, true) . ' warned');
    }
    T::notContains($base, 'data-gk-row-id', 'a table that is neither selectable nor editable got row ids');
},

'a selectable table keeps its markup, and a server table without editable() too' => function (): void {
    Lang::set('en');
    $sel = static fn (): Table => (new Table('t'))->setData(tieRows())->selectable('id')->column('name', 'Name');
    [$a] = tieRender($sel());
    [$b] = tieRender($sel()->editable('id', '/x'));
    T::eq($b, $a, 'a selectable table with editable() and no editable column changed');
    T::contains($a, '<tr data-gk-row-id="1"><td class="gk-cb-col">', 'the row of a selectable table is not written as before');

    $srv = static fn (): Table => (new Table('s'))->rows(tieRows(), 2)->column('name', 'Name')->column('amount', 'Amount', ['format' => 'currency']);
    [$c] = tieRender($srv());
    [$d] = tieRender($srv()->editable());
    T::eq($d, $c, 'a server table with editable() and no editable column changed');
    T::notContains($c, 'data-gk-raw', 'a server table carries raw values without editable()');
},

// ── The markup of an editable table ────────────────────────────────────

'the wrap carries what the browser needs, and only what it needs' => function (): void {
    Lang::set('en');
    [$html, $w] = tieRender((new Table('inv'))->setData(tieRows())
        ->editable('id', '/api/cell.php', ['headers' => ['X-CSRF-Token' => 'a"b<c']])
        ->column('name', 'Name', ['edit' => 'text'])
        ->column('amount', 'Amount', ['format' => 'currency', 'edit' => 'number'])
        ->column('status', 'Status', ['format' => 'label', 'labels' => ['paid' => ['color' => 'green', 'text' => 'Paid'], 'open' => 'orange'], 'edit' => 'select'])
        ->column('ok', 'OK', ['format' => 'boolean', 'edit' => 'select', 'options' => [1 => 'Yes', 0 => 'No']]));
    T::eq($w, [], 'a valid table warned');
    T::contains($html, 'data-gk-table="inv" data-gk-static data-gk-editable="', 'data-gk-editable is not on the wrap, after data-gk-static');
    T::eq(tieConfig($html), [
        'url' => '/api/cell.php',
        'key' => 'id',
        'hint' => 'gk-edit-hint-inv',
        'columns' => [
            'name' => ['type' => 'text'],
            'amount' => ['type' => 'number'],
            'status' => ['type' => 'select', 'options' => [['paid', 'Paid'], ['open', 'open']]],
            'ok' => ['type' => 'select', 'options' => [['1', 'Yes'], ['0', 'No']]],
        ],
        'headers' => ['X-CSRF-Token' => 'a"b<c'],
    ], 'the config is not what the browser reads');
    preg_match('/data-gk-editable="([^"]*)"/', $html, $m);
    T::ok(!str_contains($m[1], '<') && !str_contains($m[1], "'"), 'the config attribute is not escaped');
    // A setData() table: the column config is in its data block already, and so are the raw values.
    T::ok(!isset(tieConfig($html)['labelColors']), 'a static table repeats the label colours in the config');
    T::notContains($html, 'data-gk-raw', 'a static table carries raw values twice');
},

'every editable cell is reachable, says it can be edited, and is written in a fixed order' => function (): void {
    Lang::set('en');
    [$html] = tieRender((new Table('inv'))->setData(tieRows())->editable('id', '/x')
        ->column('name', 'Name', ['edit' => 'text'])
        ->column('amount', 'Amount', ['format' => 'currency', 'edit' => 'number'])
        ->column('note', 'Note'));
    T::eq(tieCells($html, '1'), [
        '<td data-label="Name" data-gk-edit="name" tabindex="0" aria-describedby="gk-edit-hint-inv">',
        '<td class="gk-td-num" style="white-space:nowrap" data-label="Amount" data-gk-edit="amount" tabindex="0" aria-describedby="gk-edit-hint-inv">',
        '<td data-label="Note">',
    ], 'the cell tags are not what js/gridkit.js writes on a rebuild (same attributes, same order)');
    T::contains($html, '<tr data-gk-row-id="1">', 'an editable table does not write the row id');
    T::contains($html, '<span id="gk-edit-hint-inv" hidden>Editable: press Enter to change it.</span>', 'the sentence the cells point at is missing');
    T::ok(strpos($html, 'gk-edit-hint-inv" hidden') < strpos($html, '<table'), 'the sentence is not before the table (a rebuild replaces the table)');
    T::contains($html, '€1,200.50', 'the cell no longer shows its formatted value');

    Lang::set('de');
    [$de] = tieRender((new Table('inv'))->setData(tieRows())->editable()->column('name', 'Name', ['edit' => 'text']));
    T::contains($de, 'hidden>Bearbeitbar: mit der Eingabetaste ändern.</span>', 'the German sentence is missing');
    Lang::set('en');
},

'a server table carries the raw value of each editable cell, as PHP casts it' => function (): void {
    Lang::set('en');
    $rows = tieRows();
    $rows[1]['name'] = 'Bob "B" <b>&amp;';
    [$html] = tieRender((new Table('srv'))->rows($rows, 2)->editable()
        ->column('name', 'Name', ['edit' => 'text'])
        ->column('amount', 'Amount', ['format' => 'currency', 'edit' => 'number'])
        ->column('ok', 'OK', ['format' => 'boolean', 'edit' => 'select', 'options' => ['1' => 'Yes', '0' => 'No']])
        ->column('note', 'Note', ['edit' => 'text']));
    T::eq(tieCells($html, '1'), [
        '<td data-label="Name" data-gk-edit="name" tabindex="0" aria-describedby="gk-edit-hint-srv" data-gk-raw="Ada">',
        '<td class="gk-td-num" style="white-space:nowrap" data-label="Amount" data-gk-edit="amount" tabindex="0" aria-describedby="gk-edit-hint-srv" data-gk-raw="1200.5">',
        '<td data-label="OK" data-gk-edit="ok" tabindex="0" aria-describedby="gk-edit-hint-srv" data-gk-raw="1">',
        '<td data-label="Note" data-gk-edit="note" tabindex="0" aria-describedby="gk-edit-hint-srv" data-gk-raw="">',
    ], 'the raw values of row 1 (float, true, null)');
    $two = implode('', tieCells($html, '2'));
    T::contains($two, 'data-gk-raw="Bob &quot;B&quot; &lt;b&gt;&amp;amp;"', 'a raw value is not escaped for its attribute');
    T::contains($two, 'data-gk-raw="0"', 'the number 0 is not "0"');
    T::contains($two, 'data-gk-edit="ok" tabindex="0" aria-describedby="gk-edit-hint-srv" data-gk-raw=""', 'false is not ""');

    // The browser has no column config of a server table: the editable columns travel in the config.
    $cfg = tieConfig($html);
    T::eq($cfg['columns']['amount']['column'] ?? null, ['label' => 'Amount', 'format' => 'currency', 'edit' => 'number'], 'the column config of a server table is missing');
    T::ok(!isset($cfg['labelColors']), 'label colours travel without a label column');
    [$lab] = tieRender((new Table('srv'))->rows(tieRows(), 2)->editable()
        ->column('status', 'Status', ['format' => 'label', 'edit' => 'select', 'options' => ['paid' => 'Paid']]));
    T::ok(isset(tieConfig($lab)['labelColors']['green']), 'an editable label column of a server table has no colour table');
},

'the AJAX fragment of a server table carries the editable cells and the sentence' => function (): void {
    Lang::set('en');
    $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
    $_GET['gk_table'] = 'srv';
    try {
        [$html] = tieRender((new Table('srv'))->rows(tieRows(), 2)->editable()->column('name', 'Name', ['edit' => 'text']));
    } finally {
        unset($_SERVER['HTTP_X_REQUESTED_WITH'], $_GET['gk_table']);
    }
    T::notContains($html, 'gk-table-wrap', 'the fragment is not a fragment');
    T::contains($html, 'id="gk-edit-hint-srv" hidden', 'the reload drops the sentence the cells point at');
    T::contains($html, 'data-gk-edit="name" tabindex="0"', 'the reload drops the editable cells');
    T::contains($html, 'data-gk-raw="Ada"', 'the reload drops the raw values');
},

'a row whose key is empty is not editable, and its id stays empty' => function (): void {
    Lang::set('en');
    $rows = tieRows();
    $rows[] = ['name' => 'New', 'amount' => 3];
    $rows[] = ['id' => '', 'name' => 'Blank', 'amount' => 4];
    [$html] = tieRender((new Table('t'))->setData($rows)->editable()->column('name', 'Name', ['edit' => 'text']));
    T::eq(substr_count($html, '<tr data-gk-row-id="">'), 2, 'a row without its key has no empty row id');
    T::eq(substr_count($html, 'data-gk-edit="name"'), 2, 'a row without its key got an editable cell');
},

'editable() names its key; with a selection the selection\'s key is the row id' => function (): void {
    Lang::set('en');
    $rows = [['sku' => 'A-1', 'id' => 9, 'name' => 'Ada'], ['sku' => 'B "2"', 'id' => 8, 'name' => 'Bob']];
    [$html, $w] = tieRender((new Table('t'))->setData($rows)->editable('sku', '/x')->column('name', 'Name', ['edit' => 'text']));
    T::eq($w, [], 'a key of its own warned');
    T::contains($html, '<tr data-gk-row-id="A-1">', 'the row id is not the key editable() names');
    T::contains($html, '<tr data-gk-row-id="B &quot;2&quot;">', 'a row id is not escaped');
    T::eq(tieConfig($html)['key'] ?? null, 'sku', 'the config does not name the key');

    [$both, $w] = tieRender((new Table('t'))->setData($rows)->selectable('id')->editable('sku')->column('name', 'Name', ['edit' => 'text']));
    T::eq(count($w), 1, 'two different keys are not said');
    T::contains($w[0] ?? '', "selectable('id') and editable('sku') name different keys", 'the warning does not name both keys');
    T::contains($both, '<tr data-gk-row-id="9">', 'with two keys the row id is not the selection\'s');
    T::eq(tieConfig($both)['key'] ?? null, 'id', 'with two keys the config does not follow the selection');

    [, $w] = tieRender((new Table('t'))->setData($rows)->selectable('sku')->editable('sku')->column('name', 'Name', ['edit' => 'text']));
    T::eq($w, [], 'one key named twice warned');
},

'escaping: the table id, a column key and the hint reach their attributes escaped' => function (): void {
    Lang::set('en');
    [$html] = tieRender((new Table('a"b'))->setData([['id' => 1, 'x"y' => 'v']])->editable()->column('x"y', 'X', ['edit' => 'text']));
    T::contains($html, 'data-gk-edit="x&quot;y" tabindex="0" aria-describedby="gk-edit-hint-a&quot;b"', 'the cell attributes are not escaped');
    T::contains($html, '<span id="gk-edit-hint-a&quot;b" hidden>', 'the id of the sentence is not escaped');
    T::eq(tieConfig($html)['hint'] ?? null, 'gk-edit-hint-a"b', 'the config does not name the sentence the cells point at');
},

// ── Select options ─────────────────────────────────────────────────────

'a select takes its options from options, else from the labels of a label column' => function (): void {
    Lang::set('en');
    $labels = ['paid' => ['color' => 'green', 'text' => 'Paid'], 'open' => 'orange', 'void' => ['color' => 'red', 'text' => null]];
    [$html, $w] = tieRender((new Table('t'))->setData(tieRows())->editable()
        ->column('status', 'Status', ['format' => 'label', 'labels' => $labels, 'edit' => 'select'])
        ->column('both', 'Both', ['format' => 'label', 'labels' => $labels, 'options' => ['x' => 'X'], 'edit' => 'select'])
        ->column('num', 'Num', ['options' => [10 => 'Ten', 2 => 'Two'], 'edit' => 'select']));
    T::eq($w, [], 'valid selects warned');
    $cols = tieConfig($html)['columns'];
    T::eq($cols['status']['options'], [['paid', 'Paid'], ['open', 'open'], ['void', 'void']], 'labels: the key is the value, the text or the key the text');
    T::eq($cols['both']['options'], [['x', 'X']], 'options do not win over labels');
    T::eq($cols['num']['options'], [['10', 'Ten'], ['2', 'Two']], 'numeric keys lose their order or their text type');
},

// ── Refusals ───────────────────────────────────────────────────────────

'what cannot be edited stays read-only and says why, once per render' => function (): void {
    Lang::set('en');
    $cases = [
        "has 'edit' => 'date' — it takes 'text', 'number' or 'select'" => ['edit' => 'date'],
        "has 'edit' => true — it takes" => ['edit' => true],
        "has 'edit' => array — it takes" => ['edit' => ['type' => 'text']],
        "'format' => 'html' writes markup of its own" => ['format' => 'html', 'edit' => 'text'],
        "'format' => 'email' writes markup of its own" => ['format' => 'email', 'edit' => 'text'],
        "its 'href' makes the cell a link" => ['href' => '/c/{id}', 'edit' => 'text'],
        "has 'edit' => 'select' and nothing to choose from" => ['edit' => 'select'],
        "has 'edit' => 'select' and nothing to choose from — give it 'options'" => ['edit' => 'select', 'labels' => ['paid' => 'green']],
    ];
    foreach ($cases as $why => $opts) {
        [$html, $w] = tieRender((new Table('t'))->setData(tieRows())->editable()
            ->column('name', 'Name', ['edit' => 'text'])
            ->column('bad', 'Bad', $opts));
        T::eq(count($w), 1, "one warning for: $why");
        T::contains($w[0] ?? '', "GridKit: column 'bad' ", "the warning does not name the column ($why)");
        T::contains($w[0] ?? '', $why, 'the warning does not say why');
        T::contains($w[0] ?? '', 'The column stays read-only.', 'the warning does not say what happens');
        T::notContains($html, 'data-gk-edit="bad"', "a refused column is editable ($why)");
        T::contains($html, 'data-gk-edit="name"', "a refused column took the others with it ($why)");
    }

    [$html, $w] = tieRender((new Table('t'))->setData(tieRows())->rowLink('/c/{id}')->editable()
        ->column('name', 'Name', ['edit' => 'text'])->column('amount', 'Amount', ['edit' => 'number']));
    T::eq(count($w), 1, 'the column of rowLink() is not refused');
    T::contains($w[0] ?? '', "column 'name' cannot be edited: it carries the control of rowLink()", 'the rowLink warning');
    T::notContains($html, 'data-gk-edit="name"', 'the column of rowLink() is editable all the same');
    T::contains($html, 'class="gk-row-target"', 'the row lost its link');
    T::contains($html, 'data-gk-edit="amount"', 'the other columns of a row with a link are not editable');

    [$html, $w] = tieRender((new Table('t'))->setData(tieRows())->column('name', 'Name', ['edit' => 'text']));
    T::eq(count($w), 1, "'edit' without editable() is not said");
    T::contains($w[0] ?? '', "has 'edit', but the table is not editable()", "the warning of 'edit' without editable()");
    T::notContains($html, 'data-gk-edit', "'edit' without editable() made a cell editable");
    T::notContains($html, 'data-gk-editable', "'edit' without editable() made the table editable");

    [$html, $w] = tieRender((new Table('t'))->setData(tieRows())->editable()->column('bad', 'Bad', ['edit' => 'color']));
    T::notContains($html, 'data-gk-editable', 'a table whose only editable column was refused is still marked editable');
    T::notContains($html, 'data-gk-row-id', 'a table whose only editable column was refused writes row ids');
},

// ── The two other halves ───────────────────────────────────────────────

'the script and the stylesheet carry their 1.110.0 blocks at the end, and the words exist' => function (): void {
    $js = (string) file_get_contents(__DIR__ . '/../js/gridkit.js');
    $css = (string) file_get_contents(__DIR__ . '/../css/gridkit.css');
    $at = strpos($js, '// === 1.110.0 — inline editing (Table::editable) ===');
    T::ok($at !== false && $at > strpos($js, '1.105.0 — additions that need script'), 'the script block is missing or not after the 1.105.0 block');
    foreach (['gk:celledit', 'respondWith', 'data-gk-raw', 'data-gk-editable', 'aria-busy'] as $needle) {
        T::ok(strpos($js, $needle, (int) $at) !== false, "the script block does not handle $needle");
    }
    $c = strpos($css, '/* === 1.110.0 — inline editing === */');
    T::ok($c !== false, 'the stylesheet block is missing');
    // The block runs to the next release's block (1.111.0 and later append theirs after it), or to the end.
    $next = strpos($css, "\n/* === ", (int) $c + 10);
    $block = $next === false ? substr($css, (int) $c) : substr($css, (int) $c, $next - (int) $c);
    T::contains($block, '[data-gk-mode="dark"] [data-gk-editable]', 'the block has no [data-gk-mode="dark"] rule');
    T::contains($block, '.gk-dark [data-gk-editable]', 'the block has no .gk-dark rule');
    // Cells and fields are found by data-gk-edit, data-gk-editing and data-gk-edit-was: no class of its own,
    // so none for css/blocks.json. A class added later must go there (tests/blocks.test.php says so too).
    preg_match_all('/\.(gk-[a-z0-9-]+)/', (string) preg_replace('~/\*.*?\*/~s', '', $block), $m);
    T::eq(array_values(array_diff(array_unique($m[1]), ['gk-table', 'gk-dark', 'gk-table-mobile-card', 'gk-td-num'])), [],
        'the block uses a class that is not an existing one');
    foreach (['en', 'de'] as $loc) {
        $l = require __DIR__ . "/../lang/$loc.php";
        foreach (['table.edit_hint', 'js.edit_error', 'js.edit_number', 'js.edit_saved'] as $k) {
            T::ok(isset($l[$k]) && trim($l[$k]) !== '', "$loc/$k is missing");
        }
    }
},

];
