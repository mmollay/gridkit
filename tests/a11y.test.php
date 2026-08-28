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

/**
 * These two used to assert the bug. They required tabindex and role="button"
 * ON the <th> — which is what the code emitted, and which the W3C validator
 * rejects twice over: role="button" replaces the columnheader role, so the
 * cell stops being a column header at all, and aria-sort is only defined on a
 * header, so it turns invalid on that same element. A screen reader user lost
 * the column. The header now keeps aria-sort; the control is a <button> inside.
 */
'a sortable header is a header containing a control' => function (): void {
    Lang::set('en');
    unset($_GET['gk_sort'], $_GET['gk_dir']);

    $html = tableHtml();
    preg_match('/<th[^>]*aria-sort[^>]*>/', $html, $m);
    $th = $m[0] ?? '';

    T::ok($th !== '', 'the sortable header renders');
    T::contains($th, 'aria-sort="none"', 'unsorted columns say so');
    T::ok(
        !str_contains($th, 'role='),
        'no role on the th — it would replace columnheader and invalidate aria-sort'
    );
    T::ok(
        !str_contains($th, 'tabindex='),
        'no tabindex on the th — the button inside is the tab stop'
    );

    preg_match('/<button[^>]*data-gk-sort="name"[^>]*>/', $html, $b);
    T::ok(($b[0] ?? '') !== '', 'the control is a real button, a tab stop by itself');
    T::contains($b[0] ?? '', 'type="button"', 'typed, so it cannot submit a surrounding form');
},

'aria-sort follows the direction the column is sorted in' => function (): void {
    Lang::set('en');

    $_GET['gk_sort'] = 'name';
    $_GET['gk_dir']  = 'asc';
    preg_match('/<th[^>]*aria-sort[^>]*>/', tableHtml(), $m);
    T::contains($m[0] ?? '', 'aria-sort="ascending"', 'ascending is reported');

    $_GET['gk_dir'] = 'desc';
    preg_match('/<th[^>]*aria-sort[^>]*>/', tableHtml(), $m);
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

/**
 * Form errors were rendered, styled, and announced to nobody. There was no
 * aria-describedby in Form.php or in gridkit.js, so a screen reader read
 * "Email address, required, edit text" and stopped — the reason the form had
 * rejected the entry was available only to people who could see it.
 */
'a field points at the message that explains it' => function (): void {
    Lang::set('en');
    ob_start();
    (new GridKit\Form('f'))
        ->field('email', 'Email', 'email', ['required' => true, 'error' => 'Not valid'])
        ->render();
    $html = (string) ob_get_clean();

    preg_match('/<input[^>]*id="email"[^>]*>/', $html, $m);
    $input = $m[0] ?? '';
    T::contains($input, 'aria-describedby="email-error"', 'the field names its message');
    T::contains($input, 'aria-invalid="true"', 'and reports that it is in an error state');

    preg_match('/<div class="gk-field-error"[^>]*>/', $html, $m2);
    T::contains($m2[0] ?? '', 'id="email-error"', 'the message carries the id pointed at');
    T::contains($m2[0] ?? '', 'role="alert"', 'so a message written after submit is announced');
},

'a field with no error is not marked invalid' => function (): void {
    Lang::set('en');
    ob_start();
    (new GridKit\Form('f'))->field('name', 'Name', 'text', ['required' => true])->render();
    $html = (string) ob_get_clean();
    T::ok(!str_contains($html, 'aria-invalid'),
        'required is a state, not a failure — marking it invalid before submit is a lie');
},

'the required asterisk is decoration' => function (): void {
    Lang::set('en');
    ob_start();
    (new GridKit\Form('f'))->field('name', 'Name', 'text', ['required' => true])->render();
    $html = (string) ob_get_clean();
    T::contains($html, 'class="gk-required" aria-hidden="true"',
        'the input already carries `required`; the star would be read as "Name star"');
},

/**
 * A table announced nothing. Sorting, filtering and paging swap the rows in
 * place: obvious on screen, silent to everything else. And the headers carried
 * no scope, so a cell in a table with an action column was associated with the
 * wrong one — or with nothing.
 */
'every column header says it is a column header' => function (): void {
    Lang::set('en');
    $rows = [];
    for ($i = 1; $i <= 5; $i++) $rows[] = ['id' => $i, 'name' => "Item $i"];
    ob_start();
    (new GridKit\Table('t'))->setData($rows)->selectable('id')
        ->column('name', 'Name')->button('edit', ['icon' => 'edit'])->render();
    $html = (string) ob_get_clean();

    preg_match_all('/<th\b[^>]*>/', $html, $m);
    T::ok(count($m[0]) >= 3, 'there are headers to check');
    foreach ($m[0] as $th) {
        T::contains($th, 'scope="col"', 'every th, including the checkbox and action columns');
    }
},

'the table has somewhere to say what changed' => function (): void {
    Lang::set('en');
    ob_start();
    (new GridKit\Table('t'))->setData([['id' => 1, 'name' => 'a']])->column('name', 'Name')->render();
    $html = (string) ob_get_clean();
    preg_match('/<div[^>]*data-gk-table-status[^>]*>/', $html, $m);
    $el = $m[0] ?? '';
    T::ok($el !== '', 'the region exists');
    T::contains($el, 'role="status"', 'it is a status region');
    T::contains($el, 'aria-live="polite"', 'polite — it waits rather than interrupting');
    T::contains($el, 'gk-sr-only', 'and is not visible, because it is for people who are not looking');
},

'the pager is a named landmark, not loose digits' => function (): void {
    Lang::set('en');
    $rows = [];
    for ($i = 1; $i <= 30; $i++) $rows[] = ['id' => $i, 'name' => "Item $i"];
    ob_start();
    (new GridKit\Table('t'))->setData($rows)->column('name', 'Name')->paginate(10)->render();
    $html = (string) ob_get_clean();

    T::contains($html, '<nav class="gk-pagination"', 'a landmark that can be skipped');
    T::ok((bool) preg_match('/<nav class="gk-pagination" aria-label="[^"]+"/', $html),
        'and it is named');
    T::contains($html, 'aria-current="page"', 'the page you are on is stated, not only coloured');
    T::ok(!str_contains($html, 'aria-label="Chevron left"'),
        'the previous button is named for what it does, not for its glyph');
},

/**
 * The user menu — the one containing Sign out — could not be opened without a
 * mouse. Its trigger is a div with role="button" and tabindex="0", and only
 * `click` was handled; a div does not synthesise a click from Enter the way a
 * real button does. aria-expanded was written once as "false" and never
 * updated, so it stated the opposite of the truth whenever the menu was open.
 */
'the dropdown answers the keyboard and reports its state' => function (): void {
    $js = (string) file_get_contents(__DIR__ . '/../js/gridkit.js');
    T::contains($js, '_gkDropdownSet', 'open and aria-expanded move together');
    T::contains($js, 'el.setAttribute("aria-expanded", open ? "true" : "false")',
        'the attribute follows the state instead of being written once');
    T::contains($js, 'if (e.key !== "Enter" && e.key !== " ") return',
        'Enter and Space open it');
    T::ok(str_contains($js, 'if (e.key === "Escape")') && str_contains($js, '_gkDropdownSet(open, false)'),
        'Escape closes it');
    T::contains($js, 'if (typeof open.focus === "function") open.focus()',
        'and hands focus back to the trigger');
},

'the sidebar says which page you are on' => function (): void {
    Lang::set('en');
    ob_start();
    (new GridKit\Sidebar('s'))->group('Nav')
        ->item('Dashboard', '?a', 'dashboard', ['active' => true])
        ->item('Invoices', '?b', 'receipt_long', ['badge' => 3])
        ->render();
    $html = (string) ob_get_clean();

    T::contains($html, 'aria-current="page"',
        'the current item was a colour and nothing else');
    T::eq(substr_count($html, 'aria-current="page"'), 1, 'and only one item is current');
    T::ok((bool) preg_match('/<nav class="gk-sidebar-nav" aria-label="[^"]+"/', $html),
        'the navigation is named — the page has more than one nav now');
    T::contains($html, 'gk-sidebar-badge">3<span class="gk-sr-only">',
        '"Invoices 3" says three of what; the hidden word answers it');
},

];
