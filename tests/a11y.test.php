<?php
/**
 * Keyboard and screen-reader basics.
 *
 * A table is the component people spend the most time in, and until 1.32 its
 * sortable headers were plain `<th>` elements with a click handler: no way to
 * reach them by keyboard, nothing announcing what they do or which way the
 * column is sorted. The search box and the filter dropdown had no accessible
 * name either — a placeholder is not one; it is not always announced and it
 * vanishes as soon as anything is typed.
 */

declare(strict_types=1);

use GridKit\{Lang, Table};

/** @return array<string,callable> */
function tableHtml(array $extra = []): string
{
    $t = (new Table('t'))
        ->rows([['id' => 1, 'name' => 'Widget', 'status' => 'paid']], 1)
        ->search(['name'])
        ->column('name',   'Product', ['sortable' => true])
        ->column('status', 'Status')
        ->filter('status', 'select', ['options' => ['paid' => 'Paid']] + $extra);

    return T::capture(fn() => $t->render());
}

return [

'a sortable header is operable and announces itself' => function (): void {
    Lang::set('en');
    unset($_GET['gk_sort'], $_GET['gk_dir']);

    $html = tableHtml();
    preg_match('/<th[^>]*data-gk-sort="name"[^>]*>/', $html, $m);
    $th = $m[0] ?? '';

    T::ok($th !== '', 'the sortable header renders');
    T::contains($th, 'tabindex="0"', 'reachable by Tab — without this there is no way to sort without a mouse');
    T::contains($th, 'role="button"', 'announced as a control, not as a plain header');
    T::contains($th, 'aria-sort="none"', 'unsorted columns say so');
},

'aria-sort follows the direction the column is sorted in' => function (): void {
    Lang::set('en');

    $_GET['gk_sort'] = 'name';
    $_GET['gk_dir']  = 'asc';
    preg_match('/<th[^>]*data-gk-sort="name"[^>]*>/', tableHtml(), $m);
    T::contains($m[0] ?? '', 'aria-sort="ascending"', 'ascending is reported');

    $_GET['gk_dir'] = 'desc';
    preg_match('/<th[^>]*data-gk-sort="name"[^>]*>/', tableHtml(), $m);
    T::contains($m[0] ?? '', 'aria-sort="descending"', 'descending is reported');

    unset($_GET['gk_sort'], $_GET['gk_dir']);
},

'a non-sortable column is not presented as a control' => function (): void {
    Lang::set('en');
    $html = tableHtml();
    preg_match('/<th[^>]*>Status<\/th>/', $html, $m);
    T::ok(!str_contains($m[0] ?? '', 'tabindex'),
        'a column nobody can sort must not sit in the tab order');
},

'the search box and the filter carry accessible names' => function (): void {
    Lang::set('en');
    $html = tableHtml();

    preg_match('/<input[^>]*data-gk-search[^>]*>/', $html, $m);
    T::contains($m[0] ?? '', 'aria-label=', 'the search box is named');

    preg_match('/<select[^>]*data-gk-filter[^>]*>/', $html, $m);
    T::contains($m[0] ?? '', 'aria-label="Status"',
        'the filter takes its name from the column, not from "All"');
},

'a filter may override the name it is given' => function (): void {
    Lang::set('en');
    $html = tableHtml(['label' => 'Payment state']);
    preg_match('/<select[^>]*data-gk-filter[^>]*>/', $html, $m);
    T::contains($m[0] ?? '', 'aria-label="Payment state"', 'an explicit label wins');
},

'the language reaches the accessible names too' => function (): void {
    Lang::set('de');
    preg_match('/<input[^>]*data-gk-search[^>]*>/', tableHtml(), $m);
    T::contains($m[0] ?? '', 'aria-label="Suchen', 'a german page announces in german');
    Lang::set('en');
},

];
