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
        'Modal'        => fn() => Modal::container(),
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

];
