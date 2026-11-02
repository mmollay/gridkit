<?php
/**
 * What a table emits, byte for byte.
 *
 * `Table::renderInner()` is 295 lines — header cells, the group headings, the
 * rows and their buttons, the empty state, the footer and the pager in one
 * method — and the tests of the table look for single attributes. Same
 * reasoning as tests/golden-form.test.php: a restructuring has to prove that no
 * character moved, and a regular expression for one attribute cannot.
 *
 * Every case renders a whole table (the toolbar, the data block and the modal
 * templates around the inner part included) from fixed rows, so the recording
 * in tests/golden/table.json covers `render()`, `renderInner()`, the row
 * buttons, the cell formats and the empty state. What a table takes from the
 * request (sort, page, search, filter, the AJAX header) is set per case and
 * put back afterwards.
 *
 * A deliberate change of markup: run
 *
 *     GK_UPDATE_GOLDEN=1 php tests/run.php golden-table
 *
 * and read the diff of tests/golden/table.json before committing it. A
 * refactoring must leave that file untouched.
 */

declare(strict_types=1);

use GridKit\{Lang, Table};

const GOLDEN_TABLE_FILE = __DIR__ . '/golden/table.json';

/** The markup of a table, plus the warnings GridKit itself raised while rendering it. */
function goldenTableRender(array $case): string
{
    $getBefore = $_GET;
    $serverBefore = $_SERVER;
    $notes = [];
    set_error_handler(function (int $no, string $msg) use (&$notes): bool {
        if ($no !== E_USER_WARNING) return false;
        $notes[] = $msg;
        return true;
    });
    try {
        Lang::set('en');
        $_GET = $case['get'];
        foreach ($case['server'] as $k => $v) $_SERVER[$k] = $v;
        $table = ($case['build'])();
        $html = T::capture(fn() => $table->render());
    } finally {
        restore_error_handler();
        $_GET = $getBefore;
        $_SERVER = $serverBefore;
    }
    return $html . ($notes ? "\n<!-- E_USER_WARNING: " . implode(' | ', $notes) . ' -->' : '');
}

/** Where two strings first differ, with a little context from each. */
function goldenTableFirstDiff(string $now, string $recorded): string
{
    $n = min(strlen($now), strlen($recorded));
    $i = 0;
    while ($i < $n && $now[$i] === $recorded[$i]) $i++;
    return sprintf('differs at byte %d — recorded «%s», now «%s»',
        $i, substr($recorded, max(0, $i - 25), 70), substr($now, max(0, $i - 25), 70));
}

/** Five rows that between them hit every format and every empty-value branch. */
function goldenTableRows(): array
{
    return [
        ['id' => 1, 'name' => 'Alpha <GmbH>', 'city' => 'Wien',     'amount' => 1234.5, 'qty' => 12,   'share' => 12.5,   'due' => '2026-11-02', 'at' => '2026-11-02 14:30:00', 'paid' => 1, 'mail' => 'a@example.com',   'status' => 'active',     'grp' => 'A', 'note' => '<b>bold</b>'],
        ['id' => 2, 'name' => 'Beta "B"',      'city' => 'Graz',     'amount' => 0,      'qty' => 0,    'share' => 0,      'due' => '',           'at' => '',                    'paid' => 0, 'mail' => '',                'status' => 'draft',      'grp' => 'A', 'note' => ''],
        ['id' => 3, 'name' => 'Gamma',         'city' => '',         'amount' => 99.99,  'qty' => 1500, 'share' => 100,    'due' => '2026-12-24', 'at' => '2026-12-24 08:00:00', 'paid' => 1, 'mail' => 'c@example.com',   'status' => 'Überfällig', 'grp' => 'B', 'note' => 'plain'],
        ['id' => 4, 'name' => 'Delta',         'city' => 'Linz',     'amount' => -5,     'qty' => 7,    'share' => '12,5', 'due' => '2027-01-31', 'at' => '2027-01-31 23:59:59', 'paid' => 0, 'mail' => "d'q@example.com", 'status' => 'custom',     'grp' => 'B', 'note' => '</script><!--<script'],
        ['id' => 5, 'name' => 'Epsilon',       'city' => 'Salzburg', 'amount' => 1000000, 'qty' => 3,   'share' => 0.5,    'due' => '2027-02-14', 'at' => '2027-02-14 00:00:00', 'paid' => 1, 'mail' => 'e@example.com',   'status' => 'done',       'grp' => 'C', 'note' => 'ä ö ü €'],
    ];
}

/** n rows, for the pager. */
function goldenTableMany(int $n): array
{
    $rows = [];
    for ($i = 1; $i <= $n; $i++) $rows[] = ['id' => $i, 'name' => "Row $i", 'city' => 'City ' . ($i % 3)];
    return $rows;
}

/** Name and city: the smallest table that has something to look at. */
function goldenTableBasic(?array $rows = null): Table
{
    return (new Table('t'))->setData($rows ?? goldenTableRows())->column('name', 'Name')->column('city', 'City');
}

/** One column of every format, with the options that change a cell. */
function goldenTableFormats(): Table
{
    return (new Table('t'))->setData(goldenTableRows())
        ->column('name', 'Name', ['href' => '/customers/{id}', 'sub' => 'city', 'sortable' => true])
        ->column('amount', 'Amount', ['format' => 'currency'])
        ->column('qty', 'Qty', ['format' => 'number', 'decimals' => 1, 'sortable' => true])
        ->column('share', 'Share', ['format' => 'percent', 'decimals' => 1])
        ->column('due', 'Due', ['format' => 'date'])
        ->column('at', 'At', ['format' => 'datetime', 'dateFormat' => 'd.m.Y H:i'])
        ->column('paid', 'Paid', ['format' => 'boolean'])
        ->column('mail', 'Mail', ['format' => 'email'])
        ->column('status', 'Status', ['format' => 'label', 'labels' => ['custom' => 'blue', 'done' => ['color' => 'green', 'text' => 'Finished']]])
        ->column('note', 'Note', ['format' => 'html']);
}

/** @return array<string, array{build: callable, get: array, server: array}> */
function goldenTableCases(): array
{
    $c = fn(callable $build, array $get = [], array $server = []): array => ['build' => $build, 'get' => $get, 'server' => $server];

    $buttons = fn(Table $t): Table => $t
        ->button('edit', ['icon' => 'edit', 'modal' => 'm-edit', 'position' => 'left'])
        ->button('view', ['icon' => 'visibility', 'href' => '/rows/{id}', 'title' => 'Open'])
        ->button('note', ['text' => 'Note', 'onclick' => 'openNote({id}, {name})'])
        ->button('archive', ['icon' => 'archive', 'color' => 'warning', 'showIf' => 'paid', 'aria' => 'Archive row'])
        ->button('delete', ['icon' => 'delete', 'color' => 'danger', 'confirm' => true, 'hideIf' => 'paid', 'params' => ['id' => 'id', 'who' => 'name']])
        ->button('drop', ['icon' => 'delete', 'class' => 'danger', 'confirm' => 'Really drop <this>?', 'onclick' => 'drop({id})'])
        ->button('go', ['text' => 'Go', 'icon' => 'visibility', 'href' => '/go/{id}', 'confirm' => 'Leave?']);

    return [
        // ── the plain table and the cell formats ─────────────────────────
        'table/minimal'            => $c(fn() => goldenTableBasic()),
        'table/formats'            => $c(fn() => goldenTableFormats()),
        'table/column-options'     => $c(fn() => (new Table('t'))->setData(goldenTableRows())
                                            ->column('id', 'No.', ['width' => '60px', 'muted' => true, 'align' => 'center', 'nowrap' => true])
                                            ->column('name', 'Name', ['minWidth' => '10rem', 'maxWidth' => '20rem', 'priority' => 3])
                                            ->column('city', 'City', ['hideOnMobile' => true, 'width' => 'auto', 'align' => 'right'])
                                            ->column('qty', 'Qty', ['format' => 'number', 'priority' => '2', 'blankZero' => false])),
        'table/priority-invalid'   => $c(fn() => (new Table('t'))->setData(goldenTableRows())->column('name', 'Name', ['priority' => 9])->column('city', 'City', ['priority' => 'x'])),

        // ── sorting ──────────────────────────────────────────────────────
        'table/sortable'           => $c(fn() => goldenTableFormats()),
        'table/sorted-asc'         => $c(fn() => goldenTableFormats(), ['gk_sort' => 'name']),
        'table/sorted-desc'        => $c(fn() => goldenTableFormats(), ['gk_sort' => 'qty', 'gk_dir' => 'desc']),

        // ── selection, buttons ───────────────────────────────────────────
        'table/selectable'         => $c(fn() => goldenTableBasic()->selectable()),
        'table/selectable-key'     => $c(fn() => goldenTableBasic()->selectable('city')),
        'table/buttons'            => $c(fn() => $buttons(goldenTableBasic())),
        'table/buttons-selectable' => $c(fn() => $buttons(goldenTableBasic())->selectable()->caption('Customers')),
        'table/buttons-right-only' => $c(fn() => goldenTableBasic()->button('edit', ['icon' => 'edit'])->button('x', ['icon' => 'delete', 'color' => 'danger'])),
        'table/button-unnamed'     => $c(fn() => goldenTableBasic()->button('mark_done', ['icon' => 'edit'])->button('plain', [])),

        // ── caption, size, variant, mobile ───────────────────────────────
        'table/caption-nowrap'     => $c(fn() => goldenTableBasic()->caption('  Customers  ')->nowrap()),
        'table/size-variant'       => $c(fn() => goldenTableBasic()->size('sm')->variant('striped')),
        'table/mobile-scroll'      => $c(fn() => goldenTableBasic()->mobile('scroll')),
        'table/mobile-none'        => $c(fn() => goldenTableBasic()->mobile('')),
        'table/list'               => $c(fn() => goldenTableBasic()->size('list')),
        'table/list-mobile-card'   => $c(fn() => goldenTableBasic()->size('list')->mobile('card')),

        // ── grouping ─────────────────────────────────────────────────────
        'table/group'              => $c(fn() => goldenTableBasic()->groupBy('grp')),
        'table/group-labels'       => $c(fn() => goldenTableBasic()->groupBy('grp', ['A' => 'Group <A>', 'B' => 'Group B'])->selectable()->button('x', ['icon' => 'edit', 'position' => 'left'])),

        // ── a row that opens something ───────────────────────────────────
        'table/rowlink-href'       => $c(fn() => goldenTableBasic()->rowLink('/rows/{id}')),
        'table/rowlink-sheet'      => $c(fn() => goldenTableBasic()->rowLink(['sheet' => 'row-sheet', 'url' => 'panels/row.php', 'params' => ['k' => 'id'], 'column' => 'city'])),
        'table/rowlink-bad-column' => $c(fn() => goldenTableBasic()->rowLink(['href' => '/x/{id}', 'column' => 'nope'])),
        'table/rowlink-refused'    => $c(fn() => (new Table('t'))->setData(goldenTableRows())->column('mail', 'Mail', ['format' => 'email'])->column('name', 'Name')->rowLink('/rows/{id}')),
        'table/rowlink-has-href'   => $c(fn() => (new Table('t'))->setData(goldenTableRows())->column('name', 'Name', ['href' => '/c/{id}'])->rowLink('/rows/{id}')),
        'table/rowlink-unsafe'     => $c(fn() => goldenTableBasic()->rowLink('javascript:alert({id})')),

        // ── paging ───────────────────────────────────────────────────────
        'table/paginate-first'     => $c(fn() => goldenTableBasic()->paginate(2)),
        'table/paginate-second'    => $c(fn() => goldenTableBasic()->paginate(2), ['gk_page' => '2']),
        'table/paginate-last'      => $c(fn() => goldenTableBasic()->paginate(2), ['gk_page' => '3']),
        'table/paginate-past-end'  => $c(fn() => goldenTableBasic()->paginate(2), ['gk_page' => '9']),
        'table/paginate-many'      => $c(fn() => goldenTableBasic(goldenTableMany(60))->paginate(3), ['gk_page' => '9']),
        'table/paginate-fits'      => $c(fn() => goldenTableBasic()->paginate(10)),
        'table/server-rows'        => $c(fn() => (new Table('t'))->rows(array_slice(goldenTableMany(25), 10, 10), 25)->column('name', 'Name')->paginate(10), ['gk_page' => '2']),

        // ── empty states ─────────────────────────────────────────────────
        'table/empty'              => $c(fn() => goldenTableBasic([])),
        'table/empty-custom'       => $c(fn() => goldenTableBasic([])->emptyState('Nothing here', ['hint' => 'Add one', 'icon' => 'folder', 'action' => '<a href="/new">New</a>'])),
        'table/empty-searched'     => $c(fn() => goldenTableBasic([])->search(['name']), ['gk_search' => 'zzz']),
        'table/empty-filtered'     => $c(fn() => goldenTableBasic([])->filter('city', 'select', ['options' => ['Wien' => 'Vienna']]), ['gk_filter_city' => 'Wien']),

        // ── the toolbar ──────────────────────────────────────────────────
        'table/search'             => $c(fn() => goldenTableBasic()->search(['name', 'city']), ['gk_search' => ' "Al" <x> ']),
        'table/toolbar-html'       => $c(fn() => goldenTableBasic()->toolbarHtml('<span class="x">custom</span>')),
        'table/filters'            => $c(fn() => (new Table('t'))->setData(goldenTableRows())->column('name', 'Name')->column('status', 'Status')
                                            ->filter('status', 'select', ['options' => ['active' => 'Active', 'draft' => 'Draft'], 'placeholder' => 'Any status'])
                                            ->filter('city', 'select', ['label' => 'Town', 'options' => ['Wien' => 'Vienna']])
                                            ->filter('name', 'select', []), ['gk_filter_status' => 'draft']),
        'table/new-button'         => $c(fn() => goldenTableBasic()->newButton('New customer', ['modal' => 'm-new', 'icon' => 'person_add'])),
        'table/new-button-plain'   => $c(fn() => goldenTableBasic()->newButton('New')),
        'table/toolbar-off'        => $c(fn() => goldenTableBasic()->toolbar(false)->search(['name'])->newButton('New')),
        'table/modals'             => $c(fn() => goldenTableBasic()->modal('m-edit', 'Edit <row>', '/modal/edit', ['size' => 'large'])->modal('m-new', 'New', '/modal/new')),

        // ── footer, load time ────────────────────────────────────────────
        'table/footer-strings'     => $c(fn() => goldenTableBasic()->footer(['Total', '5 rows'])),
        'table/footer-cells'       => $c(fn() => goldenTableBasic()->footer([['text' => 'Sum <€>', 'colspan' => 1, 'bold' => true], ['text' => '1.234', 'align' => 'right', 'bold' => true]])),
        'table/footer-wide'        => $c(fn() => goldenTableBasic()->selectable()->button('x', ['icon' => 'edit'])->footer([['text' => 'All', 'colspan' => 2], 'x'])->loadTime(42)),
        'table/footer-full-width'  => $c(fn() => goldenTableBasic()->footer([['text' => 'Everything', 'colspan' => 2]])->loadTime(42)),
        'table/load-time-ms'       => $c(fn() => goldenTableBasic()->loadTime(42)),
        'table/load-time-seconds'  => $c(fn() => goldenTableBasic()->loadTime(2500)->paginate(2)),
        'table/footer-load-seconds' => $c(fn() => goldenTableBasic()->footer(['Total'])->loadTime(1234)),

        // ── the request side ─────────────────────────────────────────────
        'table/ajax-fragment'      => $c(fn() => goldenTableFormats()->paginate(2)->footer(['Total']),
                                         ['gk_table' => 't', 'gk_sort' => 'qty', 'gk_page' => '2'], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']),
        'table/ajax-other-table'   => $c(fn() => goldenTableBasic(), ['gk_table' => 'other'], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']),
        'table/array-params'       => $c(fn() => goldenTableBasic()->search(['name']), ['gk_sort' => ['x'], 'gk_search' => ['y'], 'gk_dir' => 'desc']),

        // ── the data block of a static table ─────────────────────────────
        'table/data-block-labels'  => $c(fn() => goldenTableFormats()->search(['name'])->footer(['A', ['text' => 'B', 'colspan' => 2]])->groupBy('grp', ['A' => 'a'])->rowLink('/r/{id}')->button('e', ['icon' => 'edit'])->nowrap()->caption('Cap')->loadTime(5)->selectable('id')->paginate(3)),
        'table/data-block-invalid-utf8' => $c(fn() => goldenTableBasic([['id' => 1, 'name' => "bad \xB1 byte", 'city' => 'x']])),
        'table/escaping'           => $c(fn() => goldenTableBasic([['id' => 1, 'name' => '<script>alert(1)</script>', 'city' => '"quoted" & \'single\'']])->caption('A "cap" <b>')),
    ];
}

/** @return array<string,callable> */
return [

'every case renders the markup that was recorded' => function (): void {
    $now = [];
    foreach (goldenTableCases() as $name => $case) {
        $now[$name] = goldenTableRender($case);
        T::ok($now[$name] === goldenTableRender($case),
            "$name renders differently on a second run — something in it changes between runs");
    }

    if (getenv('GK_UPDATE_GOLDEN') === '1') {
        if (!is_dir(dirname(GOLDEN_TABLE_FILE))) mkdir(dirname(GOLDEN_TABLE_FILE), 0775, true);
        file_put_contents(GOLDEN_TABLE_FILE, json_encode($now,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n");
        return;
    }

    $raw = is_file(GOLDEN_TABLE_FILE) ? file_get_contents(GOLDEN_TABLE_FILE) : false;
    T::ok($raw !== false, 'tests/golden/table.json is missing — record it from known-good code: GK_UPDATE_GOLDEN=1 php tests/run.php golden-table');
    if ($raw === false) return;

    $recorded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    $missing = array_diff(array_keys($now), array_keys($recorded));
    $stale   = array_diff(array_keys($recorded), array_keys($now));
    T::ok($missing === [], 'cases without a recording: ' . implode(', ', $missing) . ' — record again and read the diff');
    T::ok($stale === [], 'recordings without a case: ' . implode(', ', $stale) . ' — record again and read the diff');

    foreach ($now as $name => $html) {
        if (!isset($recorded[$name])) continue;
        T::ok($html === $recorded[$name], "$name " . goldenTableFirstDiff($html, $recorded[$name]));
    }
},

'the cases reach every part of the table' => function (): void {
    $all = implode("\n", array_map(fn($case) => goldenTableRender($case), goldenTableCases()));
    foreach (['<caption', 'gk-cb-col', 'gk-actions-left', 'gk-actions-right', 'gk-table-group', 'gk-row-link', 'gk-empty-row',
              '<tfoot', 'gk-table-meta', 'gk-pagination', 'gk-pg-gap', 'data-gk-data', 'gk-bulk-bar', 'gk-toolbar-spacer',
              'data-gk-modal-tpl', 'gk-filter', 'data-gk-search', 'gk-sort-btn', 'aria-sort', 'gk-td-num', 'gk-hide-mobile',
              'gk-col-p3', 'gk-label-green', 'gk-bool-yes', 'mailto:', 'gk-cell-sub', 'gk-cell-link', 'data-gk-confirm',
              'data-gk-href', 'onclick="', 'E_USER_WARNING'] as $needle) {
        T::ok(str_contains($all, $needle), "no golden case reaches `$needle`");
    }
},

];
