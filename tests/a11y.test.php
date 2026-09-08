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

/**
 * Pagination.php is the standalone server-side pager — the one the docblock
 * calls "ONE uniform pager for the whole system". Table.php's built-in pager,
 * the client-side pager in gridkit.js and both Sidebar levels all marked the
 * current entry with aria-current. This file did not, so the component whose
 * whole purpose is that every pager behaves alike was the only one that
 * behaved differently.
 */
'the standalone pager states the page you are on' => function (): void {
    Lang::set('en');
    $html = GridKit\Pagination::build([
        'page' => 3, 'totalPages' => 7, 'total' => 140, 'baseUrl' => '/x',
    ]);

    T::ok((bool) preg_match('/<a class="gk-pg gk-pg-active"[^>]*aria-current="page"/', $html),
        'the page you are on is stated, not only coloured');
    T::ok(substr_count($html, 'aria-current="page"') === 1,
        'and exactly once — the other page links are plain links');
},

/**
 * A collapsible sidebar group carried its open/closed state in a CSS class and
 * nowhere else, on the server and in the browser alike. The button announced
 * no state and never said what it controls: to a screen reader every group
 * looked shut, or looked like nothing at all.
 */
'a collapsible sidebar group reports whether it is open' => function (): void {
    Lang::set('en');
    ob_start();
    (new GridKit\Sidebar('s'))
        ->item('Sales', '#', 'sell', ['id' => 'grp-sales', 'children' => [
            ['label' => 'Open items', 'href' => '?o', 'active' => true],
        ]])
        ->item('Archive', '#', 'inventory', ['id' => 'grp-arch', 'children' => [
            ['label' => 'Last year', 'href' => '?x'],
        ]])
        ->render();
    $html = (string) ob_get_clean();

    T::ok((bool) preg_match('/gk-sidebar-group-toggle active"\s+aria-expanded="true"\s+aria-controls="grp-sales"/', $html),
        'the group holding the current page is open, and says so');
    T::ok((bool) preg_match('/aria-expanded="false"\s+aria-controls="grp-arch"/', $html),
        'and a shut group says the opposite rather than staying silent');
    T::contains($html, 'id="grp-sales"',
        'aria-controls names an element that is actually there');
},

/**
 * The half that is easy to forget: the browser remembers which groups the
 * visitor closed. Restoring that state by class alone would leave a button
 * rendered as aria-expanded="true" sitting over a submenu that is shut — a
 * lying attribute is worse than a missing one, because nothing looks wrong.
 */
'the sidebar toggle keeps its state attribute in step' => function (): void {
    $js = (string) file_get_contents(__DIR__ . '/../js/gridkit.js');
    T::contains($js, 'btn.setAttribute("aria-expanded", collapsed ? "false" : "true")',
        'clicking the toggle moves the attribute together with the class');
    T::ok(substr_count($js, 'btn.setAttribute("aria-expanded"') >= 3,
        'and the restore-from-localStorage path sets it on both branches');
},

/**
 * Theme.php renders aria-pressed on the swatch that is on. Nothing ever moved
 * it again: both GK.theme.set() (the click) and GK.theme.restore() (page load)
 * carried two copies of the same loop, and both copies toggled the CSS class
 * alone. So the picker started lying at the first colour change — and with a
 * stored preference it was already lying before anyone touched it, on every
 * page load. Two copies of one decision is how the attribute came to be
 * missing from both; there is one copy now.
 */
'the theme picker marks the swatch that is really on' => function (): void {
    Lang::set('en');
    GridKit\Theme::set('ocean', 'light');
    $html = GridKit\Theme::switcher();

    T::ok((bool) preg_match('/gk-theme-dot gk-theme-active"[^>]*aria-pressed="true"/', $html),
        'the chosen colour is stated, not only shown');
    T::ok(substr_count($html, 'aria-pressed="true"') === 1,
        'and exactly one swatch claims it');

    $js = (string) file_get_contents(__DIR__ . '/../js/gridkit.js');
    T::contains($js, 'b.setAttribute("aria-pressed", on ? "true" : "false")',
        'the browser moves the attribute together with the class');
    T::ok(substr_count($js, 'b.classList.toggle("gk-theme-active"') === 1,
        'one place decides which swatch is on — click and restore both use it');
    T::contains($js, 'this._mark(document.body.dataset.gkTheme',
        'including the restore path, where a stored theme overrides the server');

    GridKit\Theme::set('indigo', 'light');
},

/**
 * The light/dark button holds both glyphs at once and CSS shows one of them.
 * Both are aria-hidden — correctly, or the pair reads as "light_modedark_mode"
 * — which left the single control that switches mode announcing no state
 * whatsoever. Dark is its pressed state.
 */
'the light and dark button says which mode it is in' => function (): void {
    Lang::set('en');
    GridKit\Theme::set('indigo', 'dark');
    T::contains(GridKit\Theme::switcher(), 'data-gk-toggle-mode aria-pressed="true"',
        'dark is the pressed state');

    GridKit\Theme::set('indigo', 'light');
    T::contains(GridKit\Theme::switcher(), 'data-gk-toggle-mode aria-pressed="false"',
        'and light is not');

    $js = (string) file_get_contents(__DIR__ . '/../js/gridkit.js');
    T::contains($js, 'this._markMode(mode)',
        'toggling the mode moves the attribute with it');
    T::contains($js, 'this._markMode(document.body.dataset.gkMode',
        'and a restored mode is marked too, not just the server-rendered one');
},

/**
 * Two tab systems ship in gridkit.js — GK.tabs, which generates its own nav
 * from the panels, and the authored-markup one the demo teaches
 * (.gk-tabs > .gk-tab-nav > .gk-tab-btn). Neither said anything at all: no
 * tablist, no tab roles, no aria-selected, nothing tying a button to the panel
 * it reveals, and no arrow keys — which is how a tablist is operated. To a
 * screen reader both were a row of buttons above unrelated text.
 *
 * Both are public API and both are fixed; the counts below are 2 on purpose,
 * so adding the roles to one system and forgetting the other fails here.
 */
'tabs are a tablist, not a row of loose buttons' => function (): void {
    $js = (string) file_get_contents(__DIR__ . '/../js/gridkit.js');

    T::eq(substr_count($js, 'setAttribute("role", "tablist")'), 2,
        'both tab systems build a tablist');
    T::eq(substr_count($js, 'setAttribute("role", "tab")'), 2,
        'and give every button the tab role');
    T::eq(substr_count($js, '"tabpanel"'), 2,
        'the panels are tabpanels in both');
    T::eq(substr_count($js, 'setAttribute("aria-labelledby", b.id)'), 2,
        'each panel is named by its own tab');
    // Named precisely rather than counted: aria-controls is right for any
    // disclosure widget, and counting every occurrence in the file made this
    // fail the moment the accordion gained one too.
    T::contains($js, 'b.setAttribute("aria-controls", p.id)',
        'the generated tabs point at the panel each reveals');
    T::contains($js, 'b.setAttribute("aria-controls", panel.id)',
        'and so do the authored ones');
    T::eq(substr_count($js, 'e.key === "End"'), 2,
        'Home and End reach the ends of both sets');
    T::ok(substr_count($js, 'b.tabIndex = on ? 0 : -1') >= 1
       && substr_count($js, 'x.tabIndex = on ? 0 : -1') >= 1,
        'a roving tabindex: the set is one tab stop, the arrows move inside it');
},

/**
 * The searchable select is the component a form spends most of its time in,
 * and it was announced as an empty listbox.
 *
 * `Select::searchable()` put `role="listbox"` on the options container and
 * left the options themselves as plain `<div>`s. That is worse than no role:
 * a listbox IS announced, and a listbox with no options in it is announced as
 * having none, so the whole list disappeared. Which option was chosen lived in
 * the `selected` class — a tick and a tint.
 *
 * `Form`'s select, the one every doc and the demo actually use, had the
 * mirror-image problem: its combobox promised `aria-haspopup="listbox"` and
 * never said which element that was, and the element it would have pointed at
 * had no listbox role at all. Two renderers of one widget, drifted apart in
 * opposite directions.
 */
'the searchable select is a listbox with options in it' => function (): void {
    Lang::set('en');

    $html = GridKit\Select::searchable('fruit', ['a' => 'Apple', 'b' => 'Banana'], ['selected' => 'b']);
    T::eq(substr_count($html, 'role="option"'), 2, 'every entry is an option');
    T::contains($html, 'role="option" aria-selected="true" data-value="b"',
        'the chosen one says so');
    T::eq(substr_count($html, 'aria-selected="true"'), 1, 'and only it does');

    // The same widget built through Form, which is the path the docs teach.
    $form = T::capture(fn() => (new GridKit\Form('f_sel'))
        ->field('fruit', 'Fruit', 'select', ['options' => ['a' => 'Apple', 'b' => 'Banana'], 'value' => 'b'])
        ->render());

    T::ok((bool) preg_match('/<div class="gk-select-options" id="([^"]+)" role="listbox"/', $form, $m),
        'the options container is a listbox and has an id to be pointed at');
    T::contains($form, 'aria-controls="' . $m[1] . '"',
        'and the combobox points at exactly that id');
    T::eq(substr_count($form, 'role="option"'), 2, 'its entries are options too');
    T::contains($form, 'aria-selected="true"', 'and the current one is stated');
},

/**
 * The multi-select had the same gap plus one of its own: each chip carried a
 * remove button that was a bare &times; and nothing else. No name — so what a
 * screen reader met was a run of identical unlabelled buttons with no way to
 * tell which chip any of them would drop.
 */
'the multi-select names what each chip removes' => function (): void {
    Lang::set('en');
    $html = T::capture(fn() => (new GridKit\Form('f_multi'))
        ->field('tags', 'Tags', 'multiselect', [
            'options' => ['a' => 'Apple', 'b' => 'Banana', 'c' => 'Cherry'],
            'value'   => 'a,c',
        ])
        ->render());

    T::contains($html, 'aria-multiselectable="true"',
        'more than one option may be chosen, and the listbox says so');
    T::eq(substr_count($html, 'role="option"'), 3, 'every entry is an option');
    T::eq(substr_count($html, 'aria-selected="true"'), 2, 'both chosen ones are marked');
    T::contains($html, 'aria-label="Remove Apple"',
        'the remove button says what it removes');
    T::ok(!(bool) preg_match('/<button[^>]*class="gk-chip-remove"[^>]*>&times;/', $html),
        'the bare glyph is no longer the button\'s only content');

    Lang::set('de');
    $de = T::capture(fn() => (new GridKit\Form('f_multi_de'))
        ->field('tags', 'Tags', 'multiselect', ['options' => ['a' => 'Apfel'], 'value' => 'a'])
        ->render());
    T::contains($de, 'aria-label="Apfel entfernen"', 'and says it in the active language');
    Lang::set('en');
},

'choosing an option moves aria-selected, not just the class' => function (): void {
    $js = (string) file_get_contents(__DIR__ . '/../js/gridkit.js');
    T::contains($js, 'o.setAttribute("aria-selected", "false")',
        'the single select clears the attribute from the others');
    T::contains($js, 'this.setAttribute("aria-selected", "true")',
        'and sets it on the one just chosen');
    T::contains($js, 'o.setAttribute("aria-selected", isSelected ? "true" : "false")',
        'the multi-select keeps every option in step as they are toggled');
},

/**
 * The accordion, and the fourth appearance of one pattern: a control whose
 * open/closed state lives in a CSS class and nowhere else — after the sidebar
 * groups, the theme picker and both tab systems.
 *
 * It also had a fault the others did not. A closed panel was hidden with
 * `max-height: 0; overflow: hidden`, which hides it from the EYE alone: the
 * text stayed in the accessibility tree, so a screen reader read every panel
 * whether open or shut, and a link inside a closed one stayed in the tab
 * order, so Tab moved focus into a zero-height box where it could not be seen.
 *
 * And it bound at parse time, one line below the block that defers GK.init()
 * to DOMContentLoaded — so with the script in <head> it queried a document
 * with no accordions in it and silently bound nothing.
 */
'a closed accordion panel is closed for everyone, not only for the eye' => function (): void {
    $css = (string) file_get_contents(__DIR__ . '/../css/gridkit.css');
    T::ok((bool) preg_match('/\.gk-accordion-content \{[^}]*visibility: hidden/s', $css),
        'a closed panel leaves the accessibility tree and the tab order');
    T::ok((bool) preg_match('/\.gk-accordion-item\.open \.gk-accordion-content \{[^}]*visibility: visible/s', $css),
        'and an open one comes back');

    $js = (string) file_get_contents(__DIR__ . '/../js/gridkit.js');
    T::contains($js, 'trigger.setAttribute("aria-controls", content.id)',
        'the trigger names the panel it opens');
    T::contains($js, 'content.setAttribute("role", "region")',
        'and the panel is a region a reader can jump to');
    T::contains($js, 'if (t) t.setAttribute("aria-expanded", open ? "true" : "false")',
        'one place sets open/closed — including the items single-open mode '
        . 'closes as a side effect, which is the path that gets forgotten');
    T::contains($js, '_gkReady(function () { GK.accordion.init(); })',
        'and it starts through the library\'s own ready guard, not a bare '
        . 'DOMContentLoaded that never fires for an async or injected script');
},

];
