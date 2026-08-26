<?php
/**
 * Every control a component emits must be able to say what it is.
 *
 * tests/a11y.test.php came out of one round of work and checks the controls
 * that round touched: sortable headers, the search box, the filter select.
 * It never asked the general question — does EVERY interactive control have
 * an accessible name? — and the answer, measured across the demo page, was
 * 81 controls with no name at all and 179 icon glyphs exposing their ligature
 * text to a screen reader as if it were a label.
 *
 * An icon-only button whose whole content is <span class="material-icons">delete</span>
 * announces as "delete" — the ligature, by accident. Strip the ligature (which
 * is what aria-hidden does, correctly) and it announces as nothing at all.
 *
 * This file asks the general question, so the next component to grow a control
 * has to answer it too.
 */

declare(strict_types=1);

use GridKit\{Lang, Table, Form, Button, Header, Sidebar, Pagination, StatCards, FilterChips, Select, TableHeader, YearFilter, Theme};

/**
 * The accessible name, computed the way a browser computes it — near enough
 * for markup that has no aria-labelledby: aria-label wins, then the contents
 * with every aria-hidden subtree removed, then title.
 *
 * @return array{0:string,1:string} the name and where it came from
 */
function accessibleName(string $attrs, string $inner): array
{
    if (preg_match('/aria-label="([^"]*)"/', $attrs, $m) && trim($m[1]) !== '') {
        return [trim($m[1]), 'aria-label'];
    }
    // Anything marked aria-hidden is not part of the name.
    $body = preg_replace('/<(\w+)[^>]*aria-hidden="true"[^>]*>.*?<\/\1>/s', '', $inner) ?? $inner;
    $body = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($body))) ?? '');
    if ($body !== '') return [$body, 'contents'];
    if (preg_match('/\btitle="([^"]*)"/', $attrs, $m) && trim($m[1]) !== '') {
        return [trim($m[1]), 'title'];
    }
    return ['', 'none'];
}

/** @return list<array{tag:string,attrs:string,name:string,from:string}> */
function namedControls(string $html): array
{
    $out = [];
    preg_match_all('/<(button|a)\b([^>]*)>(.*?)<\/\1>/is', $html, $m, PREG_SET_ORDER);
    foreach ($m as [, $tag, $attrs, $inner]) {
        if ($tag === 'a' && !str_contains($attrs, 'href')) continue;
        [$name, $from] = accessibleName($attrs, $inner);
        $out[] = ['tag' => $tag, 'attrs' => $attrs, 'name' => $name, 'from' => $from];
    }
    return $out;
}

/** A short description of a control, for a failure message worth reading. */
function describe(array $c): string
{
    preg_match('/class="([^"]*)"/', $c['attrs'], $m);
    $cls = isset($m[1]) ? implode('.', array_slice(explode(' ', $m[1]), 0, 3)) : '(no class)';
    preg_match('/data-gk-action="([^"]*)"/', $c['attrs'], $a);
    return '<' . $c['tag'] . ' class="' . $cls . '"' . (isset($a[1]) ? ' action="' . $a[1] . '"' : '') . '>';
}

/** @return array<string,string> component name => rendered HTML */
function everyComponent(): array
{
    Lang::set('en');
    $rows = [['id' => 1, 'name' => 'Widget', 'price' => 9.9, 'status' => 'paid']];

    return [
        'Table with row actions' => T::capture(fn() => (new Table('t1'))
            ->rows($rows, 1)
            ->search(['name'])
            ->column('name', 'Product', ['sortable' => true])
            ->column('price', 'Price', ['format' => 'currency'])
            ->button('edit',   ['icon' => 'edit'])
            ->button('delete', ['icon' => 'delete', 'confirm' => true])
            ->newButton('New', ['icon' => 'add'])
            ->paginate(1)
            ->render()),

        'Icon-only buttons' => Button::render('', ['icon' => 'delete', 'color' => 'danger'])
            . Button::icon('edit', ['color' => 'primary']),

        'Theme switcher' => Theme::switcher(),

        'Form with a clearable field and an upload' => T::capture(fn() => (new Form('f'))
            ->field('q',    'Search',     'text', ['clearable' => true])
            ->field('file', 'Attachment', 'file')
            ->render()),

        'Pagination' => Pagination::build(['page' => 3, 'totalPages' => 8, 'total' => 80]),

        'StatCards' => T::capture(fn() => (new StatCards('s'))
            ->card('Revenue', 1200.0, ['format' => 'currency', 'icon' => 'euro'])
            ->render()),

        'FilterChips' => T::capture(fn() => (new FilterChips('f'))
            ->chip('all', 'All')->chip('open', 'Open')->render()),

        'Sidebar' => T::capture(fn() => (new Sidebar('nav'))
            ->brand('App', 'widgets')
            ->group('Main')
            ->item('Dashboard', '/', 'dashboard', ['active' => true])
            ->render()),

        'Header' => T::capture(fn() => (new Header())
            ->title('App')->sidebarToggle(true)
            ->action(Button::render('New', ['icon' => 'add']))
            ->user('Jane Doe', ['menu' => [['label' => 'Profile', 'href' => '/p', 'icon' => 'person']]])
            ->render()),
    ];
}

return [

'every control a component emits has an accessible name' => function (): void {
    foreach (everyComponent() as $what => $html) {
        foreach (namedControls($html) as $c) {
            T::ok(
                $c['from'] !== 'none',
                "$what emits a control with no accessible name: " . describe($c)
            );
        }
    }
},

'no control depends on title alone for its name' => function (): void {
    // title is a weak naming mechanism at the best of times — it is not shown
    // on touch and not always announced. Here it is worse than weak: GK.tip
    // moves every title into data-gk-tip on the first hover and removes the
    // attribute, so a control named only by its title becomes nameless the
    // moment the pointer crosses it.
    foreach (everyComponent() as $what => $html) {
        foreach (namedControls($html) as $c) {
            T::ok(
                $c['from'] !== 'title',
                "$what names a control with title alone — GK.tip will strip it: " . describe($c)
            );
        }
    }
},

'an icon glyph is never mistaken for a label' => function (): void {
    // A Material Icons span contains the ligature ("delete"), not text meant
    // for a person. Left exposed, a screen reader reads the ligature and it
    // sounds almost right — which is why this survived so long.
    foreach (everyComponent() as $what => $html) {
        preg_match_all('/<span[^>]*class="[^"]*material-icons[^"]*"[^>]*>/i', $html, $m);
        foreach ($m[0] as $span) {
            T::contains($span, 'aria-hidden', "$what emits an icon glyph a screen reader will read: $span");
        }
    }
},

'an empty value never becomes an empty link' => function (): void {
    // A column formatted as an address emitted <a href="mailto:"></a> for
    // every row without one: a focusable, nameless link in the tab order that
    // opens a blank composer. The date formats beside it have always guarded
    // this; the address format did not.
    Lang::set('en');
    $html = T::capture(fn() => (new Table('t'))
        ->rows([['id' => 1, 'email' => 'a@b.c'], ['id' => 2, 'email' => ''], ['id' => 3, 'email' => null]], 3)
        ->column('email', 'Mail', ['format' => 'email'])
        ->render());

    preg_match_all('/<a\b[^>]*href="([^"]*)"[^>]*>(.*?)<\/a>/s', $html, $m, PREG_SET_ORDER);
    foreach ($m as [$whole, $href, $inner]) {
        T::ok(trim($href) !== 'mailto:', "an empty address became a link: $whole");
        T::ok(trim(strip_tags($inner)) !== '' || str_contains($whole, 'aria-label'),
            "a link with no text and no name: $whole");
    }
    T::eq(count($m), 1, 'exactly the one row that has an address gets a link');
},

'the accessible name is in the requested language' => function (): void {
    Lang::set('de');
    $html = T::capture(fn() => (new Table('t'))
        ->rows([['id' => 1, 'name' => 'Ware']], 1)
        ->column('name', 'Produkt')
        ->button('delete', ['icon' => 'delete'])
        ->render());
    foreach (namedControls($html) as $c) {
        if (str_contains($c['attrs'], 'data-gk-action="delete"')) {
            T::ok($c['name'] !== '', 'the delete action has a name in German too');
            T::notContains($c['name'], 'Delete', 'the German name is not the English one');
        }
    }
    Lang::set('en');
},

];
