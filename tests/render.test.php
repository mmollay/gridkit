<?php
/**
 * Smoke tests: every component renders, and renders without PHP diagnostics.
 *
 * `display_errors` is off on most production hosts, so a warning inside a
 * component shows up as a silently truncated page rather than an error — which
 * is exactly how three broken deploys went unnoticed. Here every notice,
 * warning and deprecation during a render is a test failure.
 */

declare(strict_types=1);

use GridKit\{
    ActionGroup, Auth, BelegModal, Button, FilterChips, Form, Header, Icon,
    Lang, Layout, Modal, PageSize, Pagination, Select, Sidebar, SortLink,
    StatCards, Table, TableHeader, Theme, YearFilter
};

/** Render with every diagnostic promoted to an exception. */
function strictRender(callable $fn): string
{
    set_error_handler(static function (int $no, string $msg, string $file, int $line): bool {
        throw new \RuntimeException("PHP diagnostic: $msg ($file:$line)");
    });
    try {
        return T::capture($fn);
    } finally {
        restore_error_handler();
    }
}

/** @return array<string,callable> */
return [

'every class under src/ is autoloadable' => function (): void {
    foreach (glob(__DIR__ . '/../src/*.php') ?: [] as $file) {
        $class = 'GridKit\\' . basename($file, '.php');
        T::ok(class_exists($class), "$class did not autoload");
    }
},

'components render non-empty markup without diagnostics' => function (): void {
    Lang::set('en');

    $cases = [
        'Button'       => fn() => print(Button::render('Save', ['icon' => 'save'])),
        'Button::icon' => fn() => print(Button::icon('edit')),
        'Button::fab'  => fn() => print(Button::fab('add')),
        'Button::group'=> fn() => print(Button::group([Button::render('A'), Button::render('B')])),
        'ActionGroup'  => fn() => ActionGroup::render([
                              ['icon' => 'edit', 'title' => 'Edit'],
                              ['icon' => 'delete', 'title' => 'Delete', 'color' => 'danger'],
                          ]),
        'StatCards'    => fn() => (new StatCards())
                              ->card('Revenue', '12,400', ['icon' => 'euro', 'trend' => '+8%'])
                              ->card('Orders', 87)
                              ->render(),
        'Sidebar'      => fn() => (new Sidebar())
                              ->brand('Demo', 'dashboard', '1.0')
                              ->group('Main')
                              ->item('Home', '/', 'home')
                              ->divider()
                              ->item('Settings', '/settings', 'settings', ['badge' => 3])
                              ->render(),
        'Header'       => fn() => print((new Header())
                              ->title('Dashboard')
                              ->breadcrumb(['Home' => '/', 'Dashboard' => ''])
                              ->search('Find anything')
                              ->user('Martin Mollay')
                              ->render()),
        'Form'         => fn() => (new Form('product'))
                              ->field('sku', 'SKU', 'text', ['required' => true, 'width' => 4])
                              ->field('notes', 'Notes', 'textarea', ['width' => 16])
                              ->field('unit', 'Unit', 'select', ['options' => ['pc' => 'Piece']])
                              ->submit('Save')
                              ->render(),
        'Table'        => fn() => (new Table('products'))
                              ->setData([
                                  ['id' => 1, 'name' => 'Widget', 'price' => 9.9,  'status' => 'active'],
                                  ['id' => 2, 'name' => 'Gadget', 'price' => 19.5, 'status' => 'overdue'],
                              ])
                              ->column('name',   'Product', ['sortable' => true])
                              ->column('price',  'Price',   ['format' => 'currency'])
                              ->column('status', 'Status',  ['format' => 'label'])
                              ->button('edit', ['icon' => 'edit'])
                              ->paginate(25)
                              ->render(),
        'Table (empty)'=> fn() => (new Table('empty'))
                              ->setData([])->column('name', 'Name')->render(),
        'TableHeader'  => fn() => TableHeader::make('th')->search('q')->reset('/x')->render(),
        'Pagination'   => fn() => Pagination::render(
                              ['page' => 3, 'totalPages' => 9, 'total' => 214, 'baseUrl' => '/x']),
        'PageSize'     => fn() => PageSize::make()->options([10, 25, 50])->current(25)->render(),
        'Select'       => fn() => print(Select::searchable('c', ['at' => 'Austria'])),
        'FilterChips'  => fn() => (new FilterChips('f', 'status'))
                              ->chip('', 'All')->chip('open', 'Open')->render(),
        'YearFilter'   => fn() => (new YearFilter())->range(2020, 2025)->allOption()->render(),
        'SortLink'     => fn() => print(SortLink::header('name', 'Name')),
        // Modal::container() is deliberately absent: since 1.42.0 it emits
        // nothing. It used to print an empty hidden shell that nothing ever
        // read — GK.modal.open() builds its own overlay — and this very list
        // was what kept the dead markup in place, by asserting it was there.

        'BelegModal'   => fn() => BelegModal::container(),
        'Theme'        => fn() => print(Theme::switcher()),
        'Icon'         => fn() => print(Icon::svg('search', 20)),
    ];

    foreach ($cases as $label => $render) {
        $html = strictRender($render);
        T::ok(trim($html) !== '', "$label rendered nothing");
        T::ok(substr_count($html, '<') === substr_count($html, '>'),
            "$label has unbalanced angle brackets");
    }
},

'Table honours its column formats' => function (): void {
    Lang::set('en');
    $html = T::capture(fn() => (new Table('t'))
        ->setData([['id' => 1, 'name' => 'Widget', 'status' => 'active']])
        ->column('name',   'Product')
        ->column('status', 'Status', ['format' => 'label'])
        ->render());

    T::contains($html, 'Widget', 'row data is rendered');
    T::contains($html, 'gk-label', 'label format produces a status pill');
    T::contains($html, '<table', 'a table element is produced');
},

'a parameter sent as an array does not take the page down' => function (): void {
    // ?gk_sort[]=x is one link anybody can type. Until 1.80.1 the constructor
    // assigned it to a string property and the page died with a TypeError.
    Lang::set('en');
    $saved = $_GET;
    try {
        $_GET = ['gk_sort' => ['x'], 'gk_search' => ['x'], 'gk_filter_status' => ['x'], 'status' => ['x']];
        $table = strictRender(fn() => (new Table('t'))
            ->setData([['id' => 1, 'name' => 'Widget', 'status' => 'active']])
            ->column('name', 'Product')
            ->column('status', 'Status')
            ->filter('status', 'select', ['options' => ['active' => 'Active']])
            ->search(['name'])
            ->render());
        T::contains($table, 'Widget', 'the table still renders its rows');

        $chips = strictRender(fn() => (new FilterChips('f', 'status'))
            ->chip('', 'All')->chip('active', 'Active')->current('active')->render());
        T::contains($chips, 'Active', 'the chips still render');
    } finally {
        $_GET = $saved;
    }
},

'a chip row lights its default only while the url names no filter' => function (): void {
    Lang::set('en');
    $saved = $_GET;
    $active = static function (string $html): string {
        return preg_match('/<a[^>]*gk-chip-active[^>]*>\s*([^<]+)/', $html, $m) ? trim($m[1]) : '';
    };
    $chips = static fn() => (new FilterChips('f', 'status'))
        ->chip('', 'All')->chip('open', 'Open')->chip('done', 'Done')->current('open')->render();
    try {
        $_GET = [];
        T::eq($active(T::capture($chips)), 'Open', 'no parameter: the page default is lit');
        $_GET = ['status' => 'done'];
        T::eq($active(T::capture($chips)), 'Done', 'a chosen filter wins over the default');
        // "All" links to ?status= — present but empty. Treating that like an
        // absent parameter made the All chip impossible to select.
        $_GET = ['status' => ''];
        T::eq($active(T::capture($chips)), 'All', 'an explicit empty value selects the All chip');
        // A controller falls back to its default for a value it does not know;
        // a chip row with nothing lit would say the opposite of the list.
        $_GET = ['status' => 'nonsense'];
        T::eq($active(T::capture($chips)), 'Open', 'a value no chip carries falls back to the default');
        // The same on a row without an All chip: ?status= names nothing there.
        $_GET = ['status' => ''];
        T::eq($active(T::capture(fn() => (new FilterChips('f', 'status'))
            ->current('open')->chip('open', 'Open')->chip('done', 'Done')->render())), 'Open',
            'an empty value on a row without an All chip falls back to the default');
    } finally {
        $_GET = $saved;
    }
},

'one invalid byte does not silence a whole static table' => function (): void {
    // json_encode() answers false for malformed UTF-8; the data block came out
    // empty, the client had no rows, and sort, search and paging went dead
    // without a word. Latin-1 leftovers in imported data are enough.
    Lang::set('en');
    $html = strictRender(fn() => (new Table('t'))
        ->setData([['id' => 1, 'name' => "M\xfcller <!--<script"], ['id' => 2, 'name' => 'Huber']])
        ->column('name', 'Name')
        ->render());
    T::ok((bool) preg_match('~<script type="application/json" data-gk-data>(.*?)</script>~s', $html, $m),
        'the data block is there');
    $data = json_decode($m[1] ?? '', true);
    T::ok(is_array($data) && count($data['rows'] ?? []) === 2, 'the data block is valid JSON with both rows');
    T::notContains($m[1] ?? '', '<!--', 'a comment opener in the data cannot reach the HTML parser');
    T::contains($html, "M\u{FFFD}ller", 'the damaged cell shows a replacement character, not nothing');
},

'a header stands where its column stands' => function (): void {
    // Numbers are right-aligned — the cell had the class since early on, but the
    // rule lost to ".gk-table td { text-align: left }", and the header never got
    // anything: fixing the cell alone would have put every figure on the right
    // and its heading on the left.
    Lang::set('en');
    $html = T::capture(fn() => (new Table('t'))
        ->setData([['id' => 1, 'name' => 'Widget', 'price' => 12.5, 'qty' => 3, 'state' => 'ok', 'note' => 'x']])
        ->column('name',  'Product')
        ->column('price', 'Price', ['format' => 'currency', 'sortable' => true])
        ->column('qty',   'Qty',   ['format' => 'number', 'align' => 'left'])
        ->column('state', 'State', ['align' => 'center'])
        ->column('note',  'Note',  ['align' => 'right'])
        ->render());
    $th = static function (string $label) use ($html): string {
        return preg_match('/<th\b([^>]*)>(?:<button[^>]*>)?' . preg_quote($label, '/') . '/', $html, $m) ? $m[1] : 'MISSING';
    };
    T::contains($th('Price'), 'gk-td-num', 'the header of a numeric column is not marked numeric');
    T::notContains($th('Qty'), 'gk-td-num', 'an explicit align=left on a numeric column is ignored by its header');
    T::contains($th('State'), 'gk-text-center', 'align=center does not reach the header');
    T::contains($th('Note'), 'gk-text-right', 'align=right does not reach the header');
    T::notContains($th('Product'), 'gk-t', 'a plain column got an alignment class');

    $css = (string) file_get_contents(__DIR__ . '/../css/gridkit.css');
    T::contains($css, '.gk-table td.gk-td-num', 'the numeric cell still loses to the base rule of the table');
    T::contains($css, '.gk-table th.gk-td-num', 'the numeric header has no rule');
    // The sort control is an inline-flex button of full width; text-align on
    // the th does not move its label.
    T::ok((bool) preg_match('/th\.gk-td-num \.gk-sort-btn[^{]*\{[^}]*justify-content:\s*flex-end/s', $css),
        'a sortable numeric header keeps its label on the left');

    // The browser rebuilds the head on the first sort — same classes or it drifts.
    $js = (string) file_get_contents(__DIR__ . '/../js/gridkit.js');
    T::contains($js, 'thAlignClass(col)', 'the client-side rebuild does not align its headers');
},

'a row button with href is a real link, and a field cannot become a scheme' => function (): void {
    // Ten SSI Panel views pushed window.location.href into a <button>: no middle
    // click, no "open in new tab", no status bar. 'href' makes it an <a>.
    Lang::set('en');
    $zeile = static fn (array $btn, array $row = []): string => T::capture(fn () => (new Table('t'))
        ->setData([$row + ['id' => 7, 'slug' => "a!b'c(d)e*f", 'link' => 'javascript:alert(1)']])
        ->column('id', 'Id')->button('open', ['icon' => 'edit'] + $btn)->render());

    $html = $zeile(['href' => '/artikel/{slug}']);
    T::contains($html, 'href="/artikel/a%21b%27c%28d%29e%2Af"', 'the row value is not encoded the way rawurlencode does it');
    T::ok((bool) preg_match('/<a class="gk-btn[^"]*"[^>]*href="\/artikel/', $html), 'href does not render an anchor');
    // A link must not also fire gk:rowaction — the delegated handler reads that.
    T::ok(!preg_match('/<a [^>]*data-gk-action/', $html), 'the link still carries data-gk-action');

    // A template that IS a field: the encoding disarms it (no colon survives),
    // so it stays a link — to a relative path that leads nowhere.
    T::contains($zeile(['href' => '{link}']), 'href="javascript%3Aalert%281%29"', 'an encoded scheme was not left encoded');
    // A template carrying a foreign scheme itself is refused outright.
    // What may be linked to and what may not. An allow list, not a deny list:
    // "java<TAB>script:" walked straight through the deny list, because the
    // browser strips control characters before it reads the scheme — and then
    // ran it (measured in Chromium, 21.09.2026).
    $ziel = static fn (string $href): string => (string) (preg_match('/<a [^>]*href="([^"]*)"/', $zeile(['href' => $href]), $m) ? $m[1] : 'KEIN LINK');
    foreach (['/a/1' => '/a/1', './x' => './x', '../y' => '../y', '?q=1' => '?q=1', '#top' => '#top',
              'users/7' => 'users/7', 'https://example.org/' => 'https://example.org/',
              'mailto:a@b.c' => 'mailto:a@b.c', 'tel:+431' => 'tel:+431'] as $ein => $aus) {
        T::eq($ziel($ein), $aus, "a plain target was refused: $ein");
    }
    foreach (['javascript:alert(1)', "java\tscript:alert(1)", "java\nscript:x", 'data:text/html,x',
              '//fremde.example/x', "\xc2\xa0javascript:x"] as $boese) {
        T::eq($ziel($boese), 'KEIN LINK', 'a dangerous target was linked: ' . addcslashes($boese, "\0..\37"));
    }
    T::contains($zeile(['href' => 'javascript:alert({id})']), '<button type="button"', 'a refused href does not fall back to a button');

    // A question cannot live on a link: a middle click, a Ctrl-click and Enter
    // all walk past a click handler, and a page whose JavaScript never loaded
    // would follow it unasked. So it becomes a button carrying its target.
    $gefragt = $zeile(['href' => '/a/{id}', 'confirm' => 'Sicher?']);
    T::contains($gefragt, '<button type="button"', 'a target with a question is still a link');
    T::contains($gefragt, 'data-gk-href="/a/7"', 'the target is not kept on the button');
    T::contains($gefragt, 'data-gk-confirm="Sicher?"', 'the question is gone');
    T::ok(!str_contains($gefragt, 'data-gk-action'), 'going somewhere is announced as a row action as well');

    // modal and onclick keep what they had — href must not take it away silently.
    T::contains($zeile(['href' => '/a/{id}', 'modal' => 'm']), 'data-gk-modal="m"', 'href swallowed the modal');
    T::ok(!str_contains($zeile(['href' => '/a/{id}', 'modal' => 'm']), 'href="/a/7"'), 'href won over the modal');
    T::contains($zeile(['href' => '/a/{id}', 'onclick' => 'boom({id})']), 'onclick=', 'href swallowed the onclick');
},

'the client-side rebuild keeps nowrap and the totals row' => function (): void {
    // The rebuild wrote a bare <table class="gk-table"> and no <tfoot>: the first
    // sort of a static table unwrapped its cells and took its totals row away.
    Lang::set('en');
    $html = T::capture(fn() => (new Table('t'))
        ->setData([['id' => 1, 'name' => 'Widget', 'price' => 12.5]])
        ->column('name', 'Product')->column('price', 'Price', ['format' => 'currency'])
        ->nowrap()
        ->loadTime(38)
        ->footer(['Total', ['text' => '12,50 €', 'align' => 'right', 'bold' => true]])
        ->render());
    T::ok((bool) preg_match('~data-gk-data>(.*?)</script>~s', $html, $m), 'the data block is there');
    $data = json_decode($m[1] ?? '', true) ?: [];
    T::eq($data['nowrap'] ?? null, true, 'nowrap does not reach the client');
    T::eq($data['footer'][1]['align'] ?? null, 'right', 'the footer does not reach the client');
    T::eq($data['loadTimeMs'] ?? null, 38, 'loadTime() does not reach the client');
    T::contains($js = (string) file_get_contents(__DIR__ . '/../js/gridkit.js'), 'class="gk-table-meta">', 'the rebuild writes no meta cell');
    T::contains($js, 'data.nowrap ? " gk-table-nowrap"', 'the rebuild drops gk-table-nowrap');
    T::contains($js, "'<tfoot><tr class=\"gk-table-footer\">'", 'the rebuild writes no tfoot');
},

];
