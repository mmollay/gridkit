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

];
