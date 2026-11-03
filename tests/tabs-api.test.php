<?php
/**
 * GridKit\Tabs (1.111.0): the markup it writes, option by option, against expected strings.
 *
 * Until 1.111.0 every page wrote its tabs by hand. Tabs writes exactly the markup the skill documents —
 * nothing of its own — so the first case compares its default output with the authored markup the skill
 * shows, byte for byte once the line breaks are set aside. The others hold each option, the escaping, the
 * warnings and their fallbacks (a wrong value never throws and never reaches the page unescaped), and the
 * server-side choice of the active tab: active(), then the query parameter, then the first tab that is not
 * disabled. What the script does with the markup is ci/browser-tabs.js.
 */

declare(strict_types=1);

use GridKit\{Lang, Tabs};

/** The output of $fn and the warnings it raised, without letting a warning reach the runner. */
function tabsRun(callable $fn): array
{
    $warnings = [];
    set_error_handler(static function (int $no, string $msg) use (&$warnings): bool {
        $warnings[] = $msg;
        return true;
    });
    try {
        $html = T::capture($fn);
    } finally {
        restore_error_handler();
    }
    return [$html, $warnings];
}

/** Markup with the line breaks between tags taken out, as the skill's example is indented. */
function tabsFlat(string $html): string
{
    return trim((string) preg_replace('/>\s+</', '><', trim($html)));
}

/** The button of one tab, and the panel of one tab. */
function tabsButton(string $html, string $key): string
{
    return preg_match('/<(?:button|a) [^>]*data-tab="' . preg_quote($key, '/') . '"[^>]*>.*?<\/(?:button|a)>/s', $html, $m) ? $m[0] : '';
}
function tabsPanel(string $html, string $key): string
{
    return preg_match('/<div class="gk-tab-panel[^"]*" data-tab="' . preg_quote($key, '/') . '"[^>]*>.*?<\/div>/s', $html, $m) ? $m[0] : '';
}
function tabsNavClass(string $html): string
{
    return preg_match('/<(?:div|nav) class="(gk-tab-nav[^"]*)"/', $html, $m) ? $m[1] : '';
}

/** @return array<string,callable> */
return [

'the default output is the authored markup the skill documents' => function (): void {
    Lang::set('en');
    $skill = (string) file_get_contents(__DIR__ . '/../GRIDKIT_SKILL.md');
    $at = strpos($skill, "### Tabs\n");
    T::ok($at !== false, 'the skill has no ### Tabs section');
    preg_match('/```html\n(.*?)```/s', $skill, $m, 0, (int) $at);
    $documented = tabsFlat($m[1] ?? '');
    T::contains($documented, '<div class="gk-tabs">', 'the first html block of the Tabs section is not the authored shape');

    [$html, $warnings] = tabsRun(static fn() => (new Tabs('demo'))
        ->tab('overview', 'Overview', ['content' => '…'])
        ->tab('details', 'Details', ['content' => '…'])
        ->render());
    T::eq($warnings, [], 'the default case warns');
    // The only addition: the id the row is named by (GK.tabs.select('demo', …), the address, the memory).
    T::eq(tabsFlat(str_replace(' id="demo"', '', $html)), $documented, 'Tabs writes other markup than the skill documents');
    T::contains($html, '<div class="gk-tabs" id="demo">', 'the row carries its id');
},

'render() prints what html() returns' => function (): void {
    $t = (new Tabs('same'))->style('linie')->tab('a', 'A', ['content' => '<p>a</p>'])->tab('b', 'B');
    T::eq(T::capture(static fn() => $t->render()), $t->html(), 'render() and html() differ');
},

'every tab option has its markup: icon, word, badge, error badge, count, disabled, title' => function (): void {
    [$html, $warnings] = tabsRun(static fn() => (new Tabs('opt'))->style('karte')
        ->tab('a', 'Sign-up', ['icon' => 'edit_note', 'content' => '<p>a</p>'])
        ->tab('b', 'Confirmation', ['badge' => 'Customised'])
        ->tab('c', 'Legal', ['badge' => ['text' => 'Error', 'tone' => 'error']])
        ->tab('d', 'Legal 2', ['badge' => 'Error', 'badgeError' => true])
        ->tab('e', 'Reminders', ['count' => 3])
        ->tab('f', 'Reports', ['disabled' => true, 'title' => 'Not yet'])
        ->tab('g', 'All', ['icon' => 'mail', 'badge' => 'New', 'count' => 0])
        ->render());
    T::eq($warnings, [], 'valid options warn');
    T::eq(tabsButton($html, 'a'), '<button type="button" class="gk-tab-btn gk-active" data-tab="a"><span class="material-icons gk-tab-icon" aria-hidden="true">edit_note</span><span>Sign-up</span></button>', 'icon');
    T::eq(tabsButton($html, 'b'), '<button type="button" class="gk-tab-btn" data-tab="b"><span>Confirmation</span><span class="gk-tab-badge">Customised</span></button>', 'badge');
    T::eq(tabsButton($html, 'c'), '<button type="button" class="gk-tab-btn" data-tab="c"><span>Legal</span><span class="gk-tab-badge gk-tab-badge-error">Error</span></button>', 'badge with tone error');
    T::eq(tabsButton($html, 'd'), '<button type="button" class="gk-tab-btn" data-tab="d"><span>Legal 2</span><span class="gk-tab-badge gk-tab-badge-error">Error</span></button>', 'badgeError');
    T::eq(tabsButton($html, 'e'), '<button type="button" class="gk-tab-btn" data-tab="e"><span>Reminders</span><span class="gk-tab-count">3</span></button>', 'count');
    T::eq(tabsButton($html, 'f'), '<button type="button" class="gk-tab-btn" data-tab="f" title="Not yet" disabled>Reports</button>', 'disabled with a title');
    T::eq(tabsButton($html, 'g'), '<button type="button" class="gk-tab-btn" data-tab="g"><span class="material-icons gk-tab-icon" aria-hidden="true">mail</span><span>All</span><span class="gk-tab-badge">New</span><span class="gk-tab-count">0</span></button>', 'icon, word, badge, count — in that order; 0 is a count');
},

'the row: styles, alignments, sizes and options are the classes of 1.103.0 and 1.104.0' => function (): void {
    $nav = static function (callable $build): string {
        [$html] = tabsRun(static fn() => $build((new Tabs('r'))->tab('a', 'A', ['icon' => 'home'])->tab('b', 'B', ['icon' => 'mail']))->render());
        return tabsNavClass($html);
    };
    T::eq($nav(static fn(Tabs $t) => $t), 'gk-tab-nav', 'no style');
    foreach (['karte', 'linie', 'kapsel', 'text'] as $s) T::eq($nav(static fn(Tabs $t) => $t->style($s)), "gk-tab-nav gk-tab-nav-$s", "style $s");
    T::eq($nav(static fn(Tabs $t) => $t->style('linie')->align('links')), 'gk-tab-nav gk-tab-nav-linie gk-tab-nav-links', 'left');
    T::eq($nav(static fn(Tabs $t) => $t->style('karte')->align('rechts')), 'gk-tab-nav gk-tab-nav-karte gk-tab-nav-rechts', 'right');
    T::eq($nav(static fn(Tabs $t) => $t->style('kapsel')->align('unten')), 'gk-tab-nav gk-tab-nav-kapsel gk-tab-nav-unten', 'bottom');
    T::eq($nav(static fn(Tabs $t) => $t->style('linie')->align('fest')), 'gk-tab-nav gk-tab-nav-linie gk-tab-nav-unten gk-tab-nav-fest', 'fixed bar');
    T::eq($nav(static fn(Tabs $t) => $t->style('linie')->align('top')), 'gk-tab-nav gk-tab-nav-linie', 'top is no class');
    T::eq($nav(static fn(Tabs $t) => $t->style('linie')->align('oben')), 'gk-tab-nav gk-tab-nav-linie', 'oben is top');
    T::eq($nav(static fn(Tabs $t) => $t->style('linie')->size('sm')), 'gk-tab-nav gk-tab-nav-linie gk-tab-nav-sm', 'sm');
    T::eq($nav(static fn(Tabs $t) => $t->style('linie')->size('md')), 'gk-tab-nav gk-tab-nav-linie', 'md is no class');
    T::eq($nav(static fn(Tabs $t) => $t->style('linie')->size('lg')), 'gk-tab-nav gk-tab-nav-linie gk-tab-nav-lg', 'lg');
    T::eq($nav(static fn(Tabs $t) => $t->style('kapsel')->fluid()->iconTop()->neutral()), 'gk-tab-nav gk-tab-nav-kapsel gk-tab-nav-fluid gk-tab-nav-icon-oben gk-tab-nav-neutral', 'options');
    T::eq($nav(static fn(Tabs $t) => $t->style('kapsel')->iconOnly()), 'gk-tab-nav gk-tab-nav-kapsel gk-tab-nav-nur-icon', 'icon only');
    T::eq($nav(static fn(Tabs $t) => $t->style('karte')->secondary()), 'gk-tab-nav gk-tab-nav-karte gk-tab-nav-secondary', 'second row of the card style');
    T::eq($nav(static fn(Tabs $t) => $t->secondary()), 'gk-tab-nav gk-tab-nav-secondary', 'second row of the classic row');
    T::eq($nav(static fn(Tabs $t) => $t->fill()), 'gk-tab-nav gk-tab-nav-fill', 'fill (classic row)');
    T::eq($nav(static fn(Tabs $t) => $t->style('karte')->oneRow()->flat()), 'gk-tab-nav gk-tab-nav-karte gk-tab-nav-einzeilig gk-tab-nav-flach', 'card on a phone: one row, flat');
    T::eq($nav(static fn(Tabs $t) => $t->style('linie')->align('links')->size('lg')->fluid()->iconTop()->neutral()),
        'gk-tab-nav gk-tab-nav-linie gk-tab-nav-links gk-tab-nav-lg gk-tab-nav-fluid gk-tab-nav-icon-oben gk-tab-nav-neutral', 'the order of the classes: style, alignment, size, options');
},

'a bottom row and a fixed bar stand after the panels; side and top rows before them' => function (): void {
    foreach (['unten' => true, 'fest' => true, 'links' => false, 'rechts' => false, 'top' => false] as $align => $after) {
        $html = (new Tabs('pos'))->style('linie')->align($align)->tab('a', 'A', ['content' => 'x'])->html();
        $nav = strpos($html, '<div class="gk-tab-nav');
        $panel = strpos($html, '<div class="gk-tab-panel');
        T::ok($after ? $nav > $panel : $nav < $panel, "align('$align'): the row stands " . ($after ? 'before' : 'after') . ' the panels');
    }
},

'the surface: on for the card style, off for its second row, switchable' => function (): void {
    $panel = static fn(Tabs $t): string => tabsPanel($t->tab('a', 'A', ['content' => 'x'])->html(), 'a');
    T::eq($panel((new Tabs('s1'))->style('karte')), '<div class="gk-tab-panel gk-tab-flaeche gk-active" data-tab="a">x</div>', 'card');
    T::eq($panel((new Tabs('s2'))->style('karte')->secondary()), '<div class="gk-tab-panel gk-active" data-tab="a">x</div>', 'card, second row');
    T::eq($panel((new Tabs('s3'))->style('karte')->surface(false)), '<div class="gk-tab-panel gk-active" data-tab="a">x</div>', 'card without its surface');
    T::eq($panel((new Tabs('s4'))->style('karte')->secondary()->surface()), '<div class="gk-tab-panel gk-tab-flaeche gk-active" data-tab="a">x</div>', 'second row with a surface asked for');
    T::eq($panel((new Tabs('s5'))->style('linie')), '<div class="gk-tab-panel gk-active" data-tab="a">x</div>', 'line');
},

'icon only: the word stays for a screen reader, every tab has aria-label (with its badge and count) and title' => function (): void {
    $html = (new Tabs('io'))->style('kapsel')->iconOnly()
        ->tab('a', 'Overview', ['icon' => 'dashboard'])
        ->tab('b', 'Inbox', ['icon' => 'inbox', 'badge' => ['text' => 'Error', 'tone' => 'error'], 'count' => 12])
        ->tab('c', 'Help', ['icon' => 'help', 'title' => 'Help and support'])
        ->html();
    T::eq(tabsButton($html, 'a'), '<button type="button" class="gk-tab-btn gk-active" data-tab="a" aria-label="Overview" title="Overview"><span class="material-icons gk-tab-icon" aria-hidden="true">dashboard</span><span>Overview</span></button>', 'icon only');
    T::eq(tabsButton($html, 'b'), '<button type="button" class="gk-tab-btn" data-tab="b" aria-label="Inbox, Error, 12" title="Inbox"><span class="material-icons gk-tab-icon" aria-hidden="true">inbox</span><span>Inbox</span><span class="gk-tab-badge gk-tab-badge-error">Error</span><span class="gk-tab-count">12</span></button>', 'the name carries the badge and the count');
    T::contains(tabsButton($html, 'c'), 'aria-label="Help" title="Help and support"', 'an own title is the tooltip, the name stays the word');
},

'a row of links: a <nav> with <a>, aria-current on the current page, data-tab for GK.tabs' => function (): void {
    [$html, $warnings] = tabsRun(static fn() => (new Tabs('pages'))->style('linie')->label('Areas')->active('b')
        ->tab('a', 'Edit', ['href' => '/page/7/edit', 'icon' => 'edit'])
        ->tab('b', 'Results', ['href' => '/page/7/results?x=1&y=2', 'count' => 4])
        ->tab('c', 'Archive', ['href' => '/page/7/archive', 'disabled' => true])
        ->render());
    T::eq($warnings, [], 'a valid link row warns');
    T::eq(tabsFlat($html), '<nav class="gk-tab-nav gk-tab-nav-linie" id="pages" aria-label="Areas">'
        . '<a class="gk-tab-btn" data-tab="a" href="/page/7/edit"><span class="material-icons gk-tab-icon" aria-hidden="true">edit</span><span>Edit</span></a>'
        . '<a class="gk-tab-btn" data-tab="b" href="/page/7/results?x=1&amp;y=2" aria-current="page"><span>Results</span><span class="gk-tab-count">4</span></a>'
        . '<a class="gk-tab-btn" data-tab="c" aria-disabled="true">Archive</a></nav>', 'the link row');
    T::notContains($html, 'gk-tabs', 'a row of links is never inside a .gk-tabs (it would be taken for a tablist)');
    $none = (new Tabs('pages2'))->tab('a', 'A', ['href' => '/a'])->tab('b', 'B', ['href' => '/b'])->html();
    T::notContains($none, 'aria-current', 'without active() or the parameter a link row marks no page current');
},

'the address, the memory and the parameter are attributes for the script — only when asked for' => function (): void {
    $plain = (new Tabs('p'))->tab('a', 'A')->html();
    foreach (['data-gk-tabs-hash', 'data-gk-tabs-remember', 'data-gk-tabs-param', 'data-gk-tabs-chosen', 'data-gk-tab-url'] as $attr) {
        T::notContains($plain, $attr, "$attr without being asked for");
    }
    $html = (new Tabs('p'))->hash()->remember()->param('view')->tab('a', 'A')->tab('b', 'B')->html();
    T::contains($html, '<div class="gk-tabs" id="p" data-gk-tabs-hash data-gk-tabs-remember data-gk-tabs-param="view">', 'the attributes');
    $off = (new Tabs('p'))->hash()->hash(false)->remember()->remember(false)->tab('a', 'A')->html();
    T::contains($off, '<div class="gk-tabs" id="p">', 'hash(false) and remember(false) switch them off');
},

'the active tab on the server: active(), then the parameter, then the first tab that is not disabled' => function (): void {
    $active = static function (Tabs $t): array {
        [$html, $warnings] = tabsRun(static fn() => $t->render());
        preg_match_all('/class="gk-tab-btn gk-active" data-tab="([^"]*)"/', $html, $m);
        preg_match('/data-gk-tabs-chosen="([^"]*)"/', $html, $c);
        return [implode(',', $m[1]), $c[1] ?? '', $warnings, $html];
    };
    $row = static fn(): Tabs => (new Tabs('srv'))->tab('a', 'A', ['disabled' => true])->tab('b', 'B')->tab('c', 'C')->tab('d', 'D', ['disabled' => true]);
    $saved = $_GET;
    try {
        $_GET = [];
        T::eq($active($row())[0], 'b', 'the first tab that is not disabled');
        $_GET = ['tab' => 'c'];
        T::eq($active($row())[0], 'b', 'without param() the address is not read');
        T::eq(array_slice($active($row()->param()), 0, 3), ['c', '', []], 'param(): ?tab=c opens C (no chosen attribute without hash() or remember())');
        T::eq(array_slice($active($row()->param()->remember()), 0, 2), ['c', 'url'], 'param() with remember(): the script is told the address decided');
        $_GET = ['view' => 'c'];
        T::eq($active($row()->param('view'))[0], 'c', 'param(name)');
        $_GET = ['tab' => 'd'];
        T::eq(array_slice($active($row()->param()), 0, 3), ['b', '', []], 'a disabled tab in the address: the first usable one, without a warning (a hand-typed address is no page bug)');
        $_GET = ['tab' => 'nope'];
        T::eq(array_slice($active($row()->param()), 0, 3), ['b', '', []], 'an unknown key in the address: the same');
        $_GET = ['tab' => ['c']];
        T::eq(array_slice($active($row()->param()), 0, 3), ['b', '', []], '?tab[]=c is passed over');
        $_GET = ['tab' => 'c'];
        T::eq(array_slice($active($row()->param()->hash()->active('b')), 0, 3), ['b', 'page', []], 'active() wins over the parameter, and says so to the script');
        [$key, , $warnings] = $active($row()->param()->active('a'));
        T::eq($key, 'c', 'a disabled active(): the parameter is next');
        T::ok(count($warnings) === 1 && str_contains($warnings[0], "active('a') is disabled"), 'and it warns');
        [$key, , $warnings] = $active($row()->active('zzz'));
        T::eq($key, 'b', 'an unknown active(): the first usable tab');
        T::ok(count($warnings) === 1 && str_contains($warnings[0], "active('zzz') names no tab"), 'and it warns');
        $_GET = ['tab' => 'b'];
        [$key, , , $html] = $active((new Tabs('lnk'))->param()->tab('a', 'A', ['href' => '/a'])->tab('b', 'B', ['href' => '/b']));
        T::contains($html, 'data-tab="b" href="/b" aria-current="page"', 'a link row reads the parameter too');
    } finally {
        $_GET = $saved;
    }
},

'a lazy panel: the address for the script, a link for a page without it, reload' => function (): void {
    Lang::set('en');
    [$html, $warnings] = tabsRun(static fn() => (new Tabs('lz'))
        ->tab('a', 'Now', ['content' => 'x'])
        ->tab('b', 'Report & more', ['url' => '/panel.php?x=1&y="2"'])
        ->tab('c', 'Live', ['url' => '/live.php', 'reload' => true])
        ->render());
    T::eq($warnings, [], 'a lazy panel warns');
    T::eq(tabsPanel($html, 'b'), '<div class="gk-tab-panel" data-tab="b" data-gk-tab-url="/panel.php?x=1&amp;y=&quot;2&quot;">'
        . '<p class="gk-tab-fallback"><a href="/panel.php?x=1&amp;y=&quot;2&quot;">Open “Report &amp; more”</a></p></div>', 'the lazy panel');
    T::contains(tabsPanel($html, 'c'), 'data-gk-tab-url="/live.php" data-gk-tab-reload>', 'reload');
    Lang::set('de');
    $de = (new Tabs('lz'))->tab('b', 'Bericht', ['url' => '/p.php'])->html();
    T::contains($de, '>„Bericht“ öffnen</a>', 'the link speaks the language of the page');
    Lang::set('en');
},

'content is the panel markup — a string, or a callable that echoes or returns it' => function (): void {
    $html = (new Tabs('ct'))
        ->tab('a', 'A', ['content' => '<p class="x">html is not escaped: it is the page\'s markup</p>'])
        ->tab('b', 'B', ['content' => static function (): void { echo '<p>echoed</p>'; }])
        ->tab('c', 'C', ['content' => static fn(): string => '<p>returned</p>'])
        ->html();
    T::contains(tabsPanel($html, 'a'), '<p class="x">html is not escaped', 'a string');
    T::contains(tabsPanel($html, 'b'), '<p>echoed</p>', 'a callable that echoes');
    T::contains(tabsPanel($html, 'c'), '<p>returned</p>', 'a callable that returns');
},

'everything that comes from data is escaped' => function (): void {
    $x = '"><script>alert(1)</script>';
    [$html] = tabsRun(static fn() => (new Tabs('esc' . $x))->style('linie')->label($x)->iconOnly()->param($x)->remember()
        ->tab($x, $x, ['icon' => $x, 'badge' => $x, 'count' => $x, 'title' => $x, 'url' => '/p?q=' . $x])
        ->render());
    T::notContains($html, '<script>', 'a script tag reached the page');
    T::notContains($html, $x, 'the raw value reached the page (an attribute was broken out of)');
    T::ok(!preg_match('/="[^"]*[<>][^"]*"/', $html), 'an attribute value holds a raw < or >');
    T::contains($html, 'data-tab="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"', 'the key');
    T::contains($html, 'aria-label="&quot;&gt;&lt;script&gt;', 'the label of the row and the name of the tab');
    [$links] = tabsRun(static fn() => (new Tabs('esc2'))->tab('a', $x, ['href' => '/a?' . $x])->render());
    T::notContains($links, '<script>', 'a link row let a script tag through');
},

'an address that is not one is refused: javascript:, data:, empty' => function (): void {
    [$html, $warnings] = tabsRun(static fn() => (new Tabs('bad'))
        ->tab('a', 'A', ['content' => 'x'])
        ->tab('b', 'B', ['url' => 'javascript:alert(1)'])
        ->tab('c', 'C', ['url' => ' DATA:text/html,<b>x</b>'])
        ->render());
    T::notContains(strtolower($html), 'javascript:', 'a javascript: address reached the page');
    T::notContains(strtolower($html), 'data:text', 'a data: address reached the page');
    T::eq(tabsPanel($html, 'b'), '<div class="gk-tab-panel" data-tab="b"></div>', 'the panel stays empty');
    T::eq(count($warnings), 2, 'each one warns');
    [$links, $w2] = tabsRun(static fn() => (new Tabs('bad2'))->tab('a', 'A', ['href' => 'javascript:void(0)'])->tab('b', 'B', ['href' => 'https://example.com/b'])->render());
    T::contains($links, '<a class="gk-tab-btn" data-tab="a" aria-disabled="true">A</a>', 'a javascript: link is shown disabled, without an address');
    T::contains($links, 'href="https://example.com/b"', 'an http(s) address is fine');
    T::eq(count($w2), 1, 'and it warns');
},

'a wrong value warns and falls back — never an exception' => function (): void {
    $cases = [
        'empty id' => [static fn() => (new Tabs(''))->tab('a', 'A'), 'an empty id', 'id="tabs"'],
        'id with spaces' => [static fn() => (new Tabs('my tabs'))->tab('a', 'A'), "the id 'my tabs' has spaces", 'id="my-tabs"'],
        'empty key' => [static fn() => (new Tabs('w'))->tab('', 'Lost')->tab('a', 'A'), 'a tab without a key', '>A</button>'],
        'duplicate key' => [static fn() => (new Tabs('w'))->tab('a', 'First')->tab('a', 'Second'), "the key 'a' is used twice", '>First</button>'],
        'unknown style' => [static fn() => (new Tabs('w'))->style('fancy')->tab('a', 'A'), "style('fancy')", 'class="gk-tab-nav"'],
        'unknown alignment' => [static fn() => (new Tabs('w'))->style('linie')->align('middle')->tab('a', 'A'), "align('middle')", 'class="gk-tab-nav gk-tab-nav-linie"'],
        'unknown size' => [static fn() => (new Tabs('w'))->style('linie')->size('xl')->tab('a', 'A'), "size('xl')", 'class="gk-tab-nav gk-tab-nav-linie"'],
        'an option without a style' => [static fn() => (new Tabs('w'))->align('links')->neutral()->tab('a', 'A'), "align('links'), neutral() needs a style", 'class="gk-tab-nav gk-tab-nav-linie gk-tab-nav-links gk-tab-nav-neutral"'],
        'secondary with the line style' => [static fn() => (new Tabs('w'))->style('linie')->secondary()->tab('a', 'A'), 'secondary() is the second row', 'class="gk-tab-nav gk-tab-nav-linie"'],
        'secondary at the bottom' => [static fn() => (new Tabs('w'))->style('karte')->secondary()->align('unten')->tab('a', 'A'), "secondary() together with align('unten')", 'class="gk-tab-nav gk-tab-nav-karte gk-tab-nav-unten"'],
        'fill with a style' => [static fn() => (new Tabs('w'))->style('karte')->fill()->tab('a', 'A'), 'fill() is the classic row', 'class="gk-tab-nav gk-tab-nav-karte"'],
        'oneRow without the card style' => [static fn() => (new Tabs('w'))->style('linie')->oneRow()->tab('a', 'A'), "oneRow() is an option of style('karte')", 'class="gk-tab-nav gk-tab-nav-linie"'],
        'flat without the card style' => [static fn() => (new Tabs('w'))->flat()->tab('a', 'A'), "flat() is an option of style('karte')", 'class="gk-tab-nav"'],
        'a surface without the card style' => [static fn() => (new Tabs('w'))->style('kapsel')->surface()->tab('a', 'A', ['content' => 'x']), "surface() is the panel surface", '<div class="gk-tab-panel gk-active" data-tab="a">x</div>'],
        'iconTop and iconOnly' => [static fn() => (new Tabs('w'))->style('linie')->iconTop()->iconOnly()->tab('a', 'A', ['icon' => 'home']), 'iconOnly() wins', 'gk-tab-nav-nur-icon"'],
        'icon only without an icon' => [static fn() => (new Tabs('w'))->iconOnly()->tab('a', 'Word'), "tab 'a' has no 'icon'", 'aria-label="Word" title="Word">Word</button>'],
        'an unknown badge tone' => [static fn() => (new Tabs('w'))->tab('a', 'A', ['badge' => ['text' => 'Hm', 'tone' => 'pink']]), "badge tone 'pink'", '<span class="gk-tab-badge">Hm</span>'],
        'a badge that is no text' => [static fn() => (new Tabs('w'))->tab('a', 'A', ['badge' => ['tone' => 'error']]), "a 'badge' that is neither text", '>A</button>'],
        'a count that is no number' => [static fn() => (new Tabs('w'))->tab('a', 'A', ['count' => [3]]), "a 'count' that is not a number", '>A</button>'],
        'a url and content' => [static fn() => (new Tabs('w'))->tab('a', 'A', ['url' => '/p', 'content' => '<p>lost</p>']), "has a 'url' and a 'content'", 'data-gk-tab-url="/p"'],
        'reload without a url' => [static fn() => (new Tabs('w'))->tab('a', 'A', ['reload' => true, 'content' => 'x']), "'reload' without a 'url'", 'data-tab="a">x</div>'],
        'content that is no markup' => [static fn() => (new Tabs('w'))->tab('a', 'A', ['content' => 42]), "a 'content' that is neither HTML nor a callable", 'data-tab="a"></div>'],
        'every tab disabled' => [static fn() => (new Tabs('w'))->tab('a', 'A', ['disabled' => true])->tab('b', 'B', ['disabled' => true]), 'every tab is disabled', 'class="gk-tab-btn gk-active" data-tab="a" disabled'],
        'links and panels mixed' => [static fn() => (new Tabs('w'))->tab('a', 'Panel', ['content' => 'x'])->tab('b', 'Link', ['href' => '/b']), "some tabs have an 'href' and some do not", '<div class="gk-tabs" id="w">'],
        'hash() on a row of links' => [static fn() => (new Tabs('w'))->hash()->tab('a', 'A', ['href' => '/a']), 'hash() has no effect on a row of links', '<nav class="gk-tab-nav" id="w">'],
        'remember() on a row of links' => [static fn() => (new Tabs('w'))->remember()->tab('a', 'A', ['href' => '/a']), 'remember() has no effect on a row of links', '<nav class="gk-tab-nav" id="w">'],
        'an empty parameter name' => [static fn() => (new Tabs('w'))->param('')->tab('a', 'A'), "param('') names no parameter", '<div class="gk-tabs" id="w">'],
        'no tab at all' => [static fn() => (new Tabs('w')), 'no tab', null],
    ];
    foreach ($cases as $name => [$build, $warning, $fallback]) {
        $html = '';
        $warnings = [];
        try {
            [$html, $warnings] = tabsRun(static fn() => $build()->render());
        } catch (\Throwable $e) {
            T::ok(false, "$name threw " . get_class($e) . ': ' . $e->getMessage());
            continue;
        }
        T::ok(count($warnings) >= 1 && str_contains(implode(' | ', $warnings), $warning), "$name: no warning that says \"$warning\" (" . implode(' | ', $warnings) . ')');
        T::ok(str_starts_with($warnings[0] ?? '', 'GridKit: Tabs'), "$name: the warning does not say where it comes from");
        if ($fallback === null) T::eq($html, '', "$name renders nothing");
        else T::contains($html, $fallback, "$name: the fallback");
    }
    [$mixed] = tabsRun(static fn() => (new Tabs('w'))->tab('a', 'Panel', ['content' => 'x'])->tab('b', 'Link', ['href' => '/b'])->render());
    T::notContains($mixed, '>Link<', 'mixed: the tabs with an href were left out — a link inside a tablist would be taken for a tab');
    T::eq(substr_count($mixed, 'class="gk-tab-btn'), 1, 'mixed: only the panel tab is left');
},

'Tabs warns through trigger_error only, and every public method is documented in the skill section' => function (): void {
    $src = (string) file_get_contents(__DIR__ . '/../src/Tabs.php');
    T::notContains($src, 'throw new', 'Tabs throws');
    T::ok(substr_count($src, 'trigger_error(') === 1, 'warnings go through one helper');
    $skill = (string) file_get_contents(__DIR__ . '/../GRIDKIT_SKILL.md');
    T::ok((bool) preg_match('/^### Tabs\n(.*?)(?=^### |\z)/ms', $skill, $m), 'the skill has no ### Tabs section');
    foreach ((new \ReflectionClass(Tabs::class))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
        if ($method->name === '__construct') continue;
        T::contains($m[1] ?? '', '->' . $method->name . '(', "the Tabs section does not show ->{$method->name}()");
    }
    foreach (['gk:tabchange', 'gk:tabshown', 'gk:tabload', 'GK.tabs.select(', 'GK.tabs.current(', 'GK.tabs.badge(', 'GK.tabs.count(', 'GK.tabs.reload(', 'gk-tab-nav-overflow', 'data-gk-tab-url'] as $word) {
        T::contains($m[1] ?? '', $word, "the Tabs section does not explain $word");
    }
},

];
