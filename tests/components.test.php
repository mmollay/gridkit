<?php
/**
 * The components that are not Table or Form.
 *
 * Two things came out of driving these in a browser:
 *
 *  - The wrapper a sidebar layout needs — `gk-with-sidebar` — appeared nowhere
 *    except inside `skeleton.php`. Leave it out and the whole page renders
 *    *underneath* the fixed sidebar. Nothing errors; it is simply covered.
 *  - `YearFilter::allOption()` was honoured in dropdown mode only. In chip mode,
 *    the default, it still moved the default year to its value — so no chip was
 *    active and there was no control to get back to "all".
 */

declare(strict_types=1);

use GridKit\{ActionGroup, FilterChips, Header, Icon, Lang, PageSize, Sidebar, YearFilter};

/** @return array<string,callable> */
return [

'YearFilter offers its all-option in both modes' => function (): void {
    Lang::set('en');
    $_SERVER['REQUEST_URI'] = '/x';

    foreach (['chips', 'dropdown'] as $mode) {
        $html = T::capture(fn() => (new YearFilter('y', 'yr'))
            ->baseUrl('/x')->range(2024, 2026)->mode($mode)->allOption()->render());
        T::contains($html, 'All years', "$mode mode shows the all-option");
        T::contains($html, '2026', "$mode mode shows the years");
    }

    // Without it, no such control — and the current year stays the default.
    $plain = T::capture(fn() => (new YearFilter('y', 'yr'))
        ->baseUrl('/x')->range(2024, 2026)->render());
    T::notContains($plain, 'All years', 'not offered unless asked for');
},

'YearFilter tolerates a baseUrl of "?"' => function (): void {
    Lang::set('en');
    $html = T::capture(fn() => (new YearFilter('y', 'yr'))
        ->baseUrl('?')->range(2025, 2026)->render());
    T::notContains($html, '??', 'a caller writing baseUrl("?") means "this page"');
    T::contains($html, '?yr=', 'and the parameter still lands in the URL');
},

'Sidebar renders groups, badges and the active item' => function (): void {
    Lang::set('en');
    $html = T::capture(fn() => (new Sidebar('nav'))
        ->brand('GridKit', 'grid_view', '1.0')
        ->group('Main')
        ->item('Dashboard', '/', 'dashboard', ['active' => true])
        ->item('Invoices', '/inv', 'receipt_long', ['badge' => 12])
        ->divider()
        ->item('Settings', '/set', 'settings')
        ->render());

    T::contains($html, 'GridKit', 'the brand');
    T::contains($html, 'Main', 'the group heading');
    T::contains($html, '12', 'the badge');
    T::contains($html, 'gk-sidebar', 'the sidebar class the layout wrapper pairs with');
    T::ok((bool) preg_match('/class="[^"]*active/', $html), 'the active item is marked');
},

'Header renders its parts' => function (): void {
    Lang::set('en');
    $html = (new Header())
        ->title('Dashboard')
        ->breadcrumb(['Home' => '/', 'Dashboard' => ''])
        ->search('Find anything')
        ->user('Jane Doe')
        ->render();

    T::contains($html, 'Dashboard', 'the title');
    T::contains($html, 'Home', 'the breadcrumb');
    T::contains($html, 'Find anything', 'the search placeholder');
    T::contains($html, 'JD', 'initials, which need no mbstring');
},

'FilterChips marks the chip the URL asks for' => function (): void {
    Lang::set('en');
    $_GET['st'] = 'paid';
    $html = T::capture(fn() => (new FilterChips('f', 'st'))->baseUrl('/x')
        ->chip('', 'All')->chip('open', 'Open')->chip('paid', 'Paid')->render());

    T::contains($html, 'Paid', 'the chips render');
    // The second half of this used to be `|| />\s*Paid\s*<\/a>/`, which any
    // chip labelled Paid satisfies whether it is active or not — so the
    // assertion could not fail and said nothing about the thing it names.
    T::ok((bool) preg_match('/gk-chip[^"]*gk-chip-active[^"]*"[^>]*>\s*Paid/', $html),
        'the chip the URL asks for is the one marked active');
    T::eq(substr_count($html, 'gk-chip-active'), 1, 'and it is the only one');
    unset($_GET['st']);
},

'PageSize keeps the value it was given' => function (): void {
    Lang::set('en');
    $html = T::capture(fn() => PageSize::make('per')->options([10, 25, 50])->current(25)->render());
    T::contains($html, 'value="25" selected', 'the current size stays selected');
    T::contains($html, 'Rows', 'and it is labelled');
},

'ActionGroup honours the colour it is given' => function (): void {
    $html = T::capture(fn() => ActionGroup::render([
        ['icon' => 'edit',   'title' => 'Edit'],
        ['icon' => 'delete', 'title' => 'Delete', 'color' => 'danger'],
        ['icon' => 'send',   'label' => 'Remind', 'color' => 'warning',
         'variant' => 'filled', 'pill' => true],
    ]));

    T::contains($html, 'gk-action-group', 'the container');
    T::contains($html, 'gk-btn-danger', 'danger reaches the button');
    T::contains($html, 'gk-btn-warning', 'so does warning');
    T::contains($html, 'gk-btn-pill', 'and the pill shape');
    T::contains($html, 'Remind', 'a labelled action keeps its text');
},

'Icon falls back to a Material ligature, or to nothing' => function (): void {
    T::ok(Icon::has('search'), 'a known icon');
    T::ok(!Icon::has('nope-not-real'), 'an unknown one');

    T::contains(Icon::svg('search', 24), '<svg', 'known icons are inline SVG');
    T::contains(Icon::svg('search', 24), 'width="24"', 'at the size asked for');

    T::contains(Icon::svg('nope-not-real'), 'material-icons', 'unknown falls back by default');
    T::eq(Icon::svg('nope-not-real', 16, false), '', 'and to nothing when told not to');

    // The name reaches an attribute-free text node, but escape it anyway.
    T::notContains(Icon::svg('<script>x</script>'), '<script>', 'the name is escaped');
},

'the starter file is English and uses the layout wrapper' => function (): void {
    $skeleton = (string) file_get_contents(__DIR__ . '/../skeleton.php');

    // The README tells people to copy this file, and it ships in the package.
    T::ok(!preg_match('/[äöüÄÖÜß]/u', $skeleton), 'no umlauts left');
    foreach (['Artikel', 'Kunden', 'Rechnungen', 'Neuer', 'Kennzahlen'] as $word) {
        T::notContains($skeleton, $word, 'German word in the starter file');
    }

    T::contains($skeleton, 'gk-with-sidebar', 'the wrapper that clears the fixed sidebar');
    T::contains($skeleton, 'gk-main', 'and the content element');
    T::ok(!str_contains($skeleton, "'/gridkit/css"), 'no hardcoded /gridkit/ asset path');
},

];
