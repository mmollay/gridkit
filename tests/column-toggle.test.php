<?php
/**
 * Table::columnToggle() and the column options 'hidden' and 'toggle' (1.108.0).
 *
 * A wide admin list had two answers to "I don't need these four columns": scroll sideways, or ask
 * the developer to remove them for everybody. The menu lets each user choose. The script and what
 * the stylesheet does with the checkboxes are measured in a browser (ci/browser-column-toggle.js);
 * the markup they hook into, the places it names, the warnings, the rules and the export are
 * checked here — and that a table without the menu, and the rows of a table with it, are written
 * byte for byte as before.
 */

declare(strict_types=1);

use GridKit\{Lang, Table};

function ctRows(): array
{
    return [
        ['id' => 1, 'no' => 'K-1', 'name' => 'Ada', 'email' => 'ada@example.org', 'city' => 'London', 'amount' => 12.5, 'team' => 'a'],
        ['id' => 2, 'no' => 'K-2', 'name' => 'Bob', 'email' => 'bob@example.org', 'city' => 'Berlin', 'amount' => 3, 'team' => 'b'],
    ];
}

/** Render, and collect what it said with E_USER_WARNING. @return array{0:string,1:list<string>} */
function ctRender(callable $fn): array
{
    $notes = [];
    set_error_handler(static function (int $no, string $msg) use (&$notes): bool {
        if ($no !== E_USER_WARNING) return false;
        $notes[] = $msg;
        return true;
    });
    try {
        $html = T::capture($fn);
    } finally {
        restore_error_handler();
    }
    return [$html, $notes];
}

/** The menu of a rendered table: the column toggle's own markup, nothing around it. */
function ctMenu(string $html): string
{
    return preg_match('~<div class="gk-col-toggle">.*?</div></div>~s', $html, $m) ? $m[0] : '';
}

/** What an AJAX reload of table $id answers. */
function ctFragment(string $id, callable $fn): string
{
    $get = $_GET;
    $_GET = ['gk_table' => $id];
    $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
    try {
        return T::capture($fn);
    } finally {
        $_GET = $get;
        unset($_SERVER['HTTP_X_REQUESTED_WITH']);
    }
}

function ctCss(): string
{
    return (string) file_get_contents(__DIR__ . '/../css/gridkit.css');
}

function ctJs(): string
{
    return (string) file_get_contents(__DIR__ . '/../js/gridkit.js');
}

/** @return array<string,callable> */
return [

'the button opens a menu of one checkbox per column, and says which' => function (): void {
    Lang::set('en');
    [$html, $notes] = ctRender(fn() => (new Table('people'))->setData(ctRows())
        ->column('name', 'Name')->column('email', 'E-mail')->columnToggle()->render());
    T::eq($notes, [], 'a plain menu raised a warning');
    T::eq(ctMenu($html),
        '<div class="gk-col-toggle">'
        . '<button type="button" class="gk-btn gk-btn-outlined gk-btn-neutral gk-btn-sm gk-btn-pill" popovertarget="gk-cols-people" aria-controls="gk-cols-people">'
        . '<span class="material-icons" style="font-size:16px" aria-hidden="true">view_column</span><span>Columns</span></button>'
        . '<div class="gk-col-toggle-menu" id="gk-cols-people" popover role="group" aria-label="Columns">'
        . '<label class="gk-dropdown-item"><input type="checkbox" data-gk-col="name" data-gk-col-pos="1" data-gk-col-head="1" checked><span>Name</span></label>'
        . '<label class="gk-dropdown-item"><input type="checkbox" data-gk-col="email" data-gk-col-pos="2" data-gk-col-head="2" checked><span>E-mail</span></label>'
        . '</div></div>',
        'the button and its menu are not written as they should be');
    // aria-expanded is the script's: written here once it would say "false" while the menu stood
    // open on a page without the script.
    T::notContains(ctMenu($html), 'aria-expanded', 'the server writes an aria-expanded nobody keeps in step');
    T::ok(strpos($html, 'gk-col-toggle') > strpos($html, '<div class="gk-toolbar">'), 'the button is not in the toolbar');
},

'a column starts hidden, cannot be hidden, or carries the row link' => function (): void {
    Lang::set('en');
    [$html, $notes] = ctRender(fn() => (new Table('t'))->setData(ctRows())->rowLink('/p/{id}')
        ->column('name', 'Name')
        ->column('no', 'No.', ['toggle' => false])
        ->column('email', 'E-mail', ['hidden' => true])
        ->column('city', 'City', ['hidden' => false, 'toggle' => true])
        ->columnToggle()->render());
    T::eq($notes, [], 'a valid combination raised a warning');
    $menu = ctMenu($html);
    T::contains($menu, '<input type="checkbox" data-gk-col="name" checked disabled>', 'the rowLink() column can be unticked');
    T::contains($menu, '<input type="checkbox" data-gk-col="no" checked disabled>', "'toggle' => false can be unticked");
    T::contains($menu, '<input type="checkbox" data-gk-col="email" data-gk-col-pos="3" data-gk-col-head="3"><span>', "'hidden' => true starts ticked");
    T::contains($menu, '<input type="checkbox" data-gk-col="city" data-gk-col-pos="4" data-gk-col-head="4" checked>', "'hidden' => false does not start ticked");
},

'the places count the checkbox and the left buttons; the header writes its button cells at its end' => function (): void {
    Lang::set('en');
    $html = T::capture(fn() => (new Table('t'))->setData(ctRows())->selectable()
        ->column('no', 'No.')->column('name', 'Name')->column('email', 'E-mail')
        ->button('view', ['icon' => 'visibility', 'position' => 'left'])
        ->button('edit', ['icon' => 'edit'])
        ->columnToggle()->render());
    $menu = ctMenu($html);
    // Cells: 1 checkbox, 2 left buttons, 3 no, 4 name, 5 email, 6 right buttons.
    // Header: 1 checkbox, 2 no, 3 name, 4 email, 5 and 6 the two button headers.
    T::contains($menu, 'data-gk-col="no" data-gk-col-pos="3" data-gk-col-head="2"', 'the first column is not counted past the checkbox and the left buttons');
    T::contains($menu, 'data-gk-col="email" data-gk-col-pos="5" data-gk-col-head="4"', 'the last column is not where its cells are');
    // What the places stand on: the order renderTableHead() and renderRow() write.
    T::ok((bool) preg_match('~<thead><tr><th scope="col" class="gk-cb-col">.*?</th><th scope="col">No\.</th><th scope="col">Name</th><th scope="col">E-mail</th><th scope="col" class="gk-actions-col">~', $html),
        'the header no longer writes its button cells at its end — data-gk-col-head must follow');
    T::ok((bool) preg_match('~<tr data-gk-row-id="1"><td class="gk-cb-col">.*?</td><td class="gk-actions gk-actions-left">.*?</td><td data-label="No\.">~', $html),
        'a row no longer writes its left buttons before the first column — data-gk-col-pos must follow');
},

'a footer cell over one column goes with it, one over several does not' => function (): void {
    Lang::set('en');
    $html = T::capture(fn() => (new Table('t'))->setData(ctRows())->selectable()
        ->column('name', 'Name')->column('email', 'E-mail')->column('city', 'City')->column('amount', 'Amount')
        ->footer([['text' => 'Total', 'colspan' => 3], 'cities'])
        ->columnToggle()->render());
    $menu = ctMenu($html);
    // Footer: [1-3 Total] [4 over city] [5, the padding over amount].
    T::contains($menu, 'data-gk-col="name" data-gk-col-pos="2" data-gk-col-head="2" checked', 'a column under a spanning footer cell got one of its own');
    T::contains($menu, 'data-gk-col="city" data-gk-col-pos="4" data-gk-col-head="4" data-gk-col-foot="2" checked', 'the footer cell over City is not named');
    T::contains($menu, 'data-gk-col="amount" data-gk-col-pos="5" data-gk-col-head="5" data-gk-col-foot="3" checked', 'the padding cell over the last column is not named');

    // A colspan of 0 is written as it is given, and a browser reads it as 1: the next cell stands
    // one place further, not on top of it.
    $zero = ctMenu(T::capture(fn() => (new Table('t'))->setData(ctRows())->column('name', 'Name')->column('city', 'City')
        ->footer([['text' => 'x', 'colspan' => 0], 'y'])->columnToggle()->render()));
    T::contains($zero, 'data-gk-col="name" data-gk-col-pos="1" data-gk-col-head="1" data-gk-col-foot="1"', 'a footer cell with colspan 0 is not read as one column');
    T::contains($zero, 'data-gk-col="city" data-gk-col-pos="2" data-gk-col-head="2" data-gk-col-foot="2"', 'the cell after a colspan of 0 is not one place further');

    $time = T::capture(fn() => (new Table('t'))->setData(ctRows())->column('name', 'Name')->column('city', 'City')
        ->loadTime(12)->columnToggle()->render());
    T::notContains(ctMenu($time), 'data-gk-col-foot', 'the load time spans the row and went with one column');
},

'labels, keys and ids are escaped; an id that is no safe one is made safe and kept apart' => function (): void {
    Lang::set('en');
    $render = fn(string $id) => T::capture(fn() => (new Table($id))->setData([['a"b' => 'x']])
        ->column('a"b', '<b>"Name"</b> & co')->column('n', '')->columnToggle()->render());
    $html = $render('t x"<y>');
    $menu = ctMenu($html);
    T::contains($menu, 'data-gk-col="a&quot;b"', 'the key is not escaped');
    T::contains($menu, '<span>&lt;b&gt;&quot;Name&quot;&lt;/b&gt; &amp; co</span>', 'the label is not escaped');
    T::notContains($menu, '<b>', 'markup of a label reached the menu');
    T::contains($menu, '<span>n</span>', 'a column with an empty heading has a checkbox without a name');
    preg_match('~popovertarget="([^"]*)"~', $menu, $m);
    T::ok(isset($m[1]) && preg_match('~^gk-cols-t-x--y--[0-9a-f]{6}$~', $m[1]) === 1, 'the menu id is not made safe: ' . ($m[1] ?? ''));
    T::contains($menu, 'aria-controls="' . ($m[1] ?? '') . '"', 'aria-controls names another element');
    T::contains($menu, 'id="' . ($m[1] ?? '') . '" popover', 'the menu does not carry the id its button names');
    preg_match('~popovertarget="([^"]*)"~', ctMenu($render('t-x--y-')), $n);
    T::ok(($n[1] ?? '') !== ($m[1] ?? ''), 'two ids that make the same safe id share a menu');
},

'German says Spalten' => function (): void {
    Lang::set('de');
    $menu = ctMenu(T::capture(fn() => (new Table('t'))->setData(ctRows())->column('name', 'Name')->columnToggle()->render()));
    Lang::set('en');
    T::contains($menu, '<span>Spalten</span></button>', 'the German button is not "Spalten"');
    T::contains($menu, 'aria-label="Spalten"', 'the German menu is not named');
},

'a table without the menu is written as before; the rows of one with it too' => function (): void {
    Lang::set('en');
    $plain = fn() => (new Table('t'))->setData(ctRows())->selectable()->groupBy('team')
        ->column('name', 'Name')->column('email', 'E-mail')->footer(['a', 'b', 'c'])
        ->button('view', ['icon' => 'visibility', 'position' => 'left']);
    $without = T::capture(fn() => $plain()->render());
    T::eq(T::capture(fn() => $plain()->columnToggle(false)->render()), $without, 'columnToggle(false) changed the table');
    foreach (['gk-col-toggle', 'data-gk-col', 'popover', 'gk-col-hidden'] as $mark) {
        T::notContains($without, $mark, 'a table without the menu carries a part of it');
    }

    // The rows of a table with the menu are the rows without it: the toolbar holds all of it.
    $with = T::capture(fn() => $plain()->columnToggle()->render());
    $rows = static fn(string $h): string => substr($h, (int) strpos($h, '<div class="gk-sr-only" role="status"'));
    T::eq($rows($with), $rows($without), 'the menu changed the markup of the rows');
    T::eq(str_replace(ctMenu($with), '', $with), $without, 'the menu changed something outside the toolbar');

    // A server table's AJAX reload answers the same fragment, 'hidden' column or not.
    $server = fn(bool $menu) => (new Table('srv'))->rows(ctRows(), 2)->groupBy('team')->footer(['x'])
        ->column('name', 'Name')->column('email', 'E-mail', $menu ? ['hidden' => true] : [])
        ->columnToggle($menu);
    $a = ctFragment('srv', fn() => $server(true)->render());
    $b = ctFragment('srv', fn() => $server(false)->render());
    T::ok($a !== '' && str_contains($a, '<table class="gk-table">'), 'the AJAX fragment is missing');
    T::eq($a, $b, 'the AJAX fragment of a table with a hidden column differs from the one without');
    T::notContains($a, 'gk-col-toggle', 'the AJAX fragment carries the menu — the toolbar it lives in stays on the page');
},

'bad values and combinations that cannot work are said, and the column shows' => function (): void {
    Lang::set('en');
    [$html, $notes] = ctRender(fn() => (new Table('t'))->setData(ctRows())
        ->column('name', 'Name', ['hidden' => 'yes'])
        ->column('email', 'E-mail', ['toggle' => 0])
        ->column('city', 'City', ['hidden' => [1]])
        ->columnToggle()->render());
    T::eq(count($notes), 3, 'three bad values, ' . count($notes) . ' warnings: ' . implode(' | ', $notes));
    T::contains($notes[0] ?? '', "column 'name' has 'hidden' => 'yes' — it takes true or false. The column starts shown.", 'the warning for a bad hidden');
    T::contains($notes[1] ?? '', "column 'email' has 'toggle' => 0 — it takes true or false. The column can be hidden.", 'the warning for a bad toggle');
    T::contains($notes[2] ?? '', "'hidden' => array", 'an array is not named by its type');
    $menu = ctMenu($html);
    T::contains($menu, 'data-gk-col="name" data-gk-col-pos="1" data-gk-col-head="1" checked', 'a bad hidden hid the column');
    T::contains($menu, 'data-gk-col="email" data-gk-col-pos="2" data-gk-col-head="2" checked', 'a bad toggle fixed the column');
    T::notContains($html, '"hidden":"yes"', 'the dropped value still reached the browser');

    [$html, $notes] = ctRender(fn() => (new Table('t'))->setData(ctRows())->column('name', 'Name', ['hidden' => true])->render());
    T::eq(count($notes), 1, "'hidden' without columnToggle() is not said");
    T::contains($notes[0] ?? '', "column 'name' is 'hidden', but the table has no columnToggle() — nobody could show it again. The column shows.", 'the warning');
    T::notContains($html, 'data-gk-col', 'a menu appeared without columnToggle()');

    [$html, $notes] = ctRender(fn() => (new Table('t'))->setData(ctRows())->column('name', 'Name', ['hidden' => true])
        ->columnToggle()->toolbar(false)->render());
    T::eq(count($notes), 2, 'columnToggle() with toolbar(false): ' . implode(' | ', $notes));
    T::contains(implode(' | ', $notes), "is 'hidden', but the table has no toolbar for its columnToggle() menu", 'the hidden column is not said');
    T::contains(implode(' | ', $notes), 'columnToggle() puts its button in the toolbar, and toolbar(false) leaves none. Every column shows.', 'the missing toolbar is not said');
    T::notContains($html, 'data-gk-col', 'a menu appeared without a toolbar');

    [$html, $notes] = ctRender(fn() => (new Table('t'))->setData(ctRows())->rowLink('/p/{id}')
        ->column('name', 'Name', ['hidden' => true])
        ->column('no', 'No.', ['hidden' => true, 'toggle' => false])
        ->columnToggle()->render());
    T::eq(count($notes), 2, 'a hidden column that cannot be hidden: ' . implode(' | ', $notes));
    T::contains($notes[0] ?? '', "column 'name' is 'hidden' and cannot be hidden (it carries the row's rowLink() control). The column shows.", 'the rowLink column');
    T::contains($notes[1] ?? '', "column 'no' is 'hidden' and cannot be hidden ('toggle' => false). The column shows.", "'toggle' => false");
    T::contains(ctMenu($html), 'data-gk-col="no" checked disabled', 'the contradiction did not leave the column shown');

    [, $notes] = ctRender(fn() => ctFragment('t', fn() => (new Table('t'))->rows(ctRows(), 2)
        ->column('name', 'Name', ['hidden' => true])->render()));
    T::eq($notes, [], 'an AJAX reload repeats the warning the page has said');
},

'Table::sendCsv() leaves out the columns the export button names as hidden' => function (): void {
    $get = $_GET;
    try {
        $columns = ['name' => 'Name', 'email' => 'E-mail', 'city' => 'City'];
        $_GET = ['gk_export' => 'csv', 'gk_hidden' => 'email,nope'];
        $csv = T::capture(fn() => Table::sendCsv(ctRows(), $columns));
        T::eq($csv, Table::csv(ctRows(), ['name' => 'Name', 'city' => 'City']), 'the hidden column is in the file, or a name it does not know did something');
        T::eq(Table::csv(ctRows(), $columns), Table::csv(ctRows(), $columns, []), 'Table::csv() reads the request');
        T::contains(Table::csv(ctRows(), $columns), 'E-mail', 'Table::csv() left a column out because of the request');

        $_GET = ['gk_export' => 'csv', 'gk_hidden' => ['email']];
        T::eq(T::capture(fn() => Table::sendCsv(ctRows(), $columns)), Table::csv(ctRows(), $columns), 'a gk_hidden that is not text changed the file');
        $_GET = ['gk_export' => 'csv'];
        T::eq(T::capture(fn() => Table::sendCsv(ctRows(), $columns)), Table::csv(ctRows(), $columns), 'without gk_hidden the file changed');
    } finally {
        $_GET = $get;
    }
},

'the stylesheet hides by place: header, row and footer, twenty places, and the mark' => function (): void {
    $css = ctCss();
    $at = strpos($css, '/* === 1.108.0 — column toggle === */');
    T::ok($at !== false, 'the block of 1.108.0 is missing');
    $block = substr($css, (int) $at);
    for ($p = 1; $p <= 20; $p++) {
        T::contains($block, '.gk-table-wrap:has(> .gk-toolbar [data-gk-col-head="' . $p . '"]:not(:checked)) > .gk-table > thead > tr > :nth-child(' . $p . '),', "header place $p");
        T::contains($block, '.gk-table-wrap:has(> .gk-toolbar [data-gk-col-pos="' . $p . '"]:not(:checked)) > .gk-table > tbody > tr:not(.gk-table-group, .gk-table-more, .gk-empty-row) > :nth-child(' . $p . '),', "row place $p");
        T::contains($block, '.gk-table-wrap:has(> .gk-toolbar [data-gk-col-foot="' . $p . '"]:not(:checked)) > .gk-table > tfoot > tr > :nth-child(' . $p . ')', "footer place $p");
    }
    T::ok((bool) preg_match('~:nth-child\(20\) \{\s*display: none !important;\s*\}~', $block), 'the places are not one list that hides');
    T::ok((bool) preg_match('~\n\.gk-table \.gk-col-hidden \{\s*display: none !important;\s*\}~', $block), 'the mark of the script is not a rule of its own');
    T::ok((bool) preg_match('~@supports not selector\(:popover-open\) \{\s*\.gk-col-toggle-menu \{\s*display: none;~', $block), 'a browser without popovers shows the menu open in the toolbar');
    T::contains($block, '[data-gk-mode="dark"] .gk-col-toggle-menu,' . "\n" . '.gk-dark .gk-col-toggle-menu {', 'the menu has no dark rule in both spellings');
    T::contains($block, '.gk-dark .gk-col-toggle-menu .gk-dropdown-item:focus-within', 'the row under the keyboard has no dark rule');
    T::ok(strlen(rtrim($css)) - strlen(rtrim($block)) === $at, 'the block is not at the end of the stylesheet');
},

'a card passes over a hidden cell for its title' => function (): void {
    $block = substr(ctCss(), (int) strpos(ctCss(), '/* === 1.108.0 — column toggle === */'));
    T::ok((bool) preg_match('~@media \(max-width: 768px\) \{(.*)\n\}~s', $block, $m), 'there is no card rule in the block');
    $card = $m[1] ?? '';
    // One in each of the three rules of the title, two in each of the other three (a :has() and an :nth-child).
    T::eq(substr_count($card, '.gk-col-hidden'), 9, 'the six title rules of 1.106.0 do not all pass over the mark');
    T::contains($card, 'td:nth-child(1 of :not(.gk-cb-col, .gk-actions, .gk-td-muted, .gk-hide-mobile, .gk-col-hidden)) {', 'the title');
    T::contains($card, ':not(:has(> td:not(.gk-cb-col, .gk-actions, .gk-td-muted, .gk-hide-mobile, .gk-col-hidden))) > td:nth-child(1 of :not(.gk-cb-col, .gk-actions, .gk-hide-mobile, .gk-col-hidden)) {', 'every visible field muted');
    T::contains($card, 'tr:has(> td:nth-child(1 of :not(.gk-cb-col, .gk-actions, .gk-hide-mobile, .gk-col-hidden)):nth-last-child(1 of :not(.gk-cb-col, .gk-actions, .gk-hide-mobile, .gk-col-hidden))) > td {', 'a card that is nothing but its head');
},

'the script is one block at the end, and wraps what it needs instead of changing it' => function (): void {
    $js = ctJs();
    $head = '// === 1.108.0 — column toggle (Table::columnToggle) ===';
    T::eq(substr_count($js, $head), 1, 'the block heading is not there once');
    $at = (int) strpos($js, $head);
    T::ok($at > (int) strpos($js, '1.105.0 — additions that need script'), 'the block is not after the one of 1.105.0');
    $block = substr($js, $at);
    T::ok(str_ends_with(rtrim($block), '})();'), 'something follows the block');
    foreach (['gk-col-toggle', 'gk-col-hidden', 'data-gk-col', 'gk-cols-'] as $mark) {
        T::notContains(substr($js, 0, $at), $mark, 'the column toggle reaches into the file before its block');
    }
    T::contains($block, 'var buildCsv = GK.tableCsv;', 'the CSV of 1.105.0 is not wrapped');
    T::contains($block, 'var initTables = GK.table.init;', 'tables that arrive later are not bound');
    T::contains($block, 'url.searchParams.set("gk_hidden", off.join(","));', 'a server table does not name its hidden columns');
    // Storage can be missing or throw: every touch of it inside a try.
    preg_match_all('~localStorage\.~', $block, $uses);
    preg_match_all('~try \{\s*(?:var v = JSON\.parse\(localStorage|if \(n\) localStorage)~', $block, $guarded);
    T::eq(count($uses[0]), 3, 'the block touches storage in more places than are guarded');
    T::eq(count($guarded[0]), 2, 'a storage call is outside a try');
},

'the documentation names the method, both options and the export' => function (): void {
    $skill = (string) file_get_contents(__DIR__ . '/../GRIDKIT_SKILL.md');
    T::contains($skill, '**`->columnToggle()` (since 1.108.0):**', 'the method has no paragraph');
    T::contains($skill, "| `hidden` | `true` starts the column hidden", "'hidden' is not in the column options");
    T::contains($skill, "| `toggle` | `false` keeps the column on screen", "'toggle' is not in the column options");
    T::contains($skill, '?gk_hidden=', 'the export of a server table is not explained');
    T::contains(demoSource(), "->columnToggle()", 'the demo does not show it');
    T::contains(demoSource(), "since('col-toggle')", 'the demo heading does not carry the mark of the block');
},

];
