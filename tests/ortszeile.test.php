<?php
/**
 * "Du bist hier" (Header::ortszeile) and the small parts of the shell around it (1.100.0).
 *
 * Why it exists: the SSI Panel could not tell its visitors where they were.
 * Every view wrote its own breadcrumb by hand (68 of them, 58 starting at the
 * module with no way back to the dashboard), the trail was small and pale, on a
 * phone the middle steps were hidden by CSS, and nothing in it named itself or
 * its current page to a screen reader. The line that answers "where am I" is
 * not the Panel's alone — every SSI system has a start page, a module and a deep
 * page — so it lives here, and the Panel only hands over the names.
 *
 * What is held here is what PHP and the stylesheet can show: the markup of each
 * case (dashboard, module start, deep page, long title, steps in between), the
 * aria attributes, no inline style, targets of the size WCAG asks for, nothing
 * hidden on a phone, the closed menu out of the tab order, and — for every
 * call that existed before — the exact bytes it produced before. What a
 * browser has to show (the line on a phone, in both modes) is in the photos
 * taken with the release and in ci/browser.js.
 */

declare(strict_types=1);

use GridKit\{Header, Lang, Layout, Sidebar};

/** @return array<string,callable> */
return (static function (): array {

$root = __DIR__ . '/..';
$css  = static fn (): string => (string) file_get_contents($root . '/css/gridkit.css');

/** The one <nav class="gk-ortszeile"> of a rendered header. */
$nav = static fn (string $html): string =>
    preg_match('#<nav class="gk-ortszeile".*?</nav>#s', $html, $m) ? $m[0] : '';

/** Capture what a component echoes. */
$echoed = static function (callable $fn): string {
    ob_start();
    try { $fn(); } finally { $out = ob_get_clean(); }
    return (string) $out;
};

/** The declarations of every rule whose selector list is exactly $selector, nested or not. */
$rules = static function (string $css, string $selector): string {
    preg_match_all('/(?:^|\n)[ \t]*' . preg_quote($selector, '/') . '\s*\{([^}]*)\}/', $css, $m);
    return implode(' ', $m[1]);
};

/** The phone block (max-width: 768px) that holds $needle. */
$phone = static function (string $css, string $needle): string {
    preg_match_all('/@media \(max-width: 768px\) \{(.*?)\n\}/s', $css, $m);
    foreach ($m[1] as $block) if (str_contains($block, $needle)) return $block;
    return '';
};

$tiefe = static fn (array $extra = []): array => array_merge([
    'modul'  => ['label' => 'Shorts', 'href' => '/shorts', 'icon' => 'play_circle'],
    'stufen' => ['Alle Shorts' => '/shorts/liste'],
    'aktuell' => 'Bunkertraining',
], $extra);

return [

// ---- the line, case by case --------------------------------------------

'on the dashboard only "Dashboard" stands, as the current step, with no link' => function () use ($nav): void {
    Lang::set('en');
    $n = $nav((new Header())->title('Home')->ortszeile([])->render());
    T::ok($n !== '', 'the nav is rendered');
    T::eq(substr_count($n, '<li'), 1, 'one step');
    T::eq(substr_count($n, '<a '), 0, 'and it is no link');
    T::contains($n, 'aria-current="page"', 'it is the current page');
    T::contains($n, '>Dashboard</span>', 'its word is Dashboard');
    T::contains($n, 'aria-hidden="true">home</span>', 'with the house, as decoration');
},

'on a module start the module is the current step, the dashboard a link' => function () use ($nav): void {
    Lang::set('en');
    $n = $nav((new Header())->title('Shorts')->ortszeile([
        'modul' => ['label' => 'Shorts', 'href' => '/shorts', 'icon' => 'play_circle'],
    ])->render());
    T::eq(substr_count($n, '<li'), 2, 'two steps');
    T::eq(substr_count($n, '<a '), 1, 'one link — the dashboard');
    T::contains($n, '<a class="gk-ortszeile-link" href="/">', 'the dashboard link goes to the start page');
    T::eq(substr_count($n, 'aria-current="page"'), 1, 'one current step');
    T::ok((bool) preg_match('#<span class="gk-ortszeile-current" aria-current="page"[^>]*>.*?play_circle.*?>Shorts</span>#s', $n),
        'the module is the current step, with its symbol and word');
    T::notContains($n, 'href="/shorts"', 'the current step has no link of its own');
},

'on a deep page: dashboard, module, steps in between, then the page — only the page without a link' => function () use ($nav, $tiefe): void {
    Lang::set('en');
    $n = $nav((new Header())->title('x')->ortszeile($tiefe(['stufen' => [
        'Alle Shorts' => '/shorts/liste', 'Serie Bunker' => '/shorts/serie/4',
    ]]))->render());
    T::eq(substr_count($n, '<li'), 5, 'five steps');
    T::eq(substr_count($n, '<a '), 4, 'four links');
    $order = [];
    foreach (['href="/"', 'href="/shorts"', 'href="/shorts/liste"', 'href="/shorts/serie/4"', 'aria-current="page"'] as $needle) {
        $order[] = strpos($n, $needle);
    }
    $sorted = $order; sort($sorted);
    T::ok(!in_array(false, $order, true) && $order === $sorted, 'in the order they were given');
    T::ok((bool) preg_match('#<li><span class="gk-ortszeile-current" aria-current="page"[^>]*><span class="gk-ortszeile-text">Bunkertraining</span></span></li></ol>#', $n),
        'the page is the last step, a span, not a link');
},

'the long title is kept whole in the markup and in title; only the end is cut — by the stylesheet' => function () use ($nav, $css, $rules): void {
    Lang::set('en');
    $long = str_repeat('Ein sehr langer Titel ', 12) . 'Ende';
    $n = $nav((new Header())->title('x')->ortszeile([
        'modul' => ['label' => 'Shorts', 'href' => '/shorts'], 'aktuell' => $long,
    ])->render());
    T::contains($n, 'title="' . $long . '"', 'the full title is in title');
    T::contains($n, '>' . $long . '</span>', 'and in the text — PHP cuts nothing');
    T::notContains($n, '…', 'no ellipsis is written into the text');
    $c = $css();
    $text = $rules($c, '.gk-ortszeile-current .gk-ortszeile-text');
    T::contains($text, 'text-overflow: ellipsis', 'the current page ends in …');
    T::contains($text, 'white-space: nowrap', 'on one line');
    // The anchors and the steps in between are never shortened: no ellipsis on
    // their rules, and they do not give way when the row is tight.
    T::notContains($rules($c, '.gk-ortszeile-link'), 'text-overflow', 'a link is not cut');
    T::notContains($rules($c, '.gk-ortszeile-link .gk-ortszeile-text'), 'text-overflow', "a link's word is not cut");
    T::contains($rules($c, '.gk-ortszeile li'), 'flex: 0 0 auto', 'a step keeps its size');
    T::contains($rules($c, '.gk-ortszeile li:last-child'), 'flex: 1 1 0', 'only the last gives way');
},

'the label of the nav is "You are here" / "Du bist hier", and can be replaced' => function () use ($nav): void {
    Lang::set('en');
    T::contains($nav((new Header())->title('x')->ortszeile([])->render()), 'aria-label="You are here"', 'English');
    Lang::set('de');
    T::contains($nav((new Header())->title('x')->ortszeile([])->render()), 'aria-label="Du bist hier"', 'German');
    T::contains($nav((new Header())->title('x')->ortszeile(['label' => 'Wo bin ich "?"'])->render()),
        'aria-label="Wo bin ich &quot;?&quot;"', 'a label of the caller, escaped');
    Lang::set('en');
},

'the dashboard step can be renamed, pointed elsewhere and given another symbol' => function () use ($nav): void {
    Lang::set('en');
    $n = $nav((new Header())->title('x')->ortszeile([
        'dashboard' => ['label' => 'Start', 'href' => '/start', 'icon' => 'dashboard'],
        'aktuell' => 'Page',
    ])->render());
    T::contains($n, '<a class="gk-ortszeile-link" href="/start">', 'the link');
    T::contains($n, '>dashboard</span>', 'the symbol');
    T::contains($n, '>Start</span>', 'the word');
    T::notContains($n, 'home', 'and no house');
},

'a step in between without an address is text, not a dead link' => function () use ($nav): void {
    Lang::set('en');
    $n = $nav((new Header())->title('x')->ortszeile([
        'modul' => ['label' => 'M', 'href' => '/m'], 'stufen' => ['Nur Text' => ''], 'aktuell' => 'P',
    ])->render());
    T::eq(substr_count($n, '<a '), 2, 'only dashboard and module are links');
    T::contains($n, '>Nur Text</span>', 'the step is still there');
    T::eq(substr_count($n, 'href=""'), 0, 'no empty href');
},

'every step is escaped' => function () use ($nav): void {
    Lang::set('en');
    $xss = '<img src=x onerror=alert(1)>';
    $n = $nav((new Header())->title('x')->ortszeile([
        'dashboard' => ['label' => $xss, 'href' => '"><script>alert(1)</script>', 'icon' => $xss],
        'modul' => ['label' => $xss, 'href' => '"><b>', 'icon' => $xss],
        'stufen' => [$xss => '"><i>'], 'aktuell' => $xss,
    ])->render());
    foreach (['<img', '<script', '<b>', '<i>'] as $tag) {
        T::notContains($n, $tag, "no $tag gets through, in a word, a symbol or an address");
    }
},

'the line replaces the breadcrumb: one truth, not two trails' => function () use ($nav): void {
    Lang::set('en');
    $html = (new Header())->title('x')->breadcrumb(['A' => '/a', 'B'])->ortszeile([])->render();
    T::ok($nav($html) !== '', 'the line is there');
    T::notContains($html, 'gk-breadcrumb', 'and the breadcrumb is not written beside it');
},

'the line writes no inline style, and its symbols are decoration' => function () use ($nav, $tiefe): void {
    Lang::set('en');
    $n = $nav((new Header())->title('x')->ortszeile($tiefe())->render());
    T::notContains($n, 'style=', 'no inline style');
    T::notContains($n, 'onclick', 'no inline script');
    T::eq(substr_count($n, 'class="material-icons"'), substr_count($n, 'aria-hidden="true"'), 'every symbol is hidden from a screen reader');
},

// ---- the stylesheet ----------------------------------------------------

'the targets are big enough: 24px or more at a desk, the target minimum on a phone' => function () use ($css, $rules, $phone): void {
    $c = $css();
    $link = $rules($c, '.gk-ortszeile-link');
    T::ok((bool) preg_match('/min-height:\s*(\d+)px/', $link, $m) && (int) $m[1] >= 24, 'a link is at least 24px high at a desk (WCAG 2.5.8)');
    $mobile = $phone($c, '.gk-ortszeile-link');
    T::ok($mobile !== '', 'the phone has a rule for the line');
    T::contains($rules($mobile, '.gk-ortszeile-link'), 'min-height: var(--gk-target-min)', 'and 44px there');
},

'nothing of the line is hidden on a phone — it wraps instead' => function () use ($css, $rules, $phone): void {
    $c = $css();
    $mobile = $phone($c, '.gk-ortszeile-link');
    T::notContains($mobile, 'display: none', 'no step is switched off');
    T::notContains($mobile, 'visibility: hidden', 'none is hidden another way');
    T::contains($rules($c, '.gk-ortszeile ol'), 'flex-wrap: wrap', 'the steps wrap');
},

'the line takes its colours from the tokens, so both modes follow' => function () use ($css, $rules): void {
    $c = $css();
    $all = $rules($c, '.gk-ortszeile-link') . $rules($c, '.gk-ortszeile-current') . $rules($c, '.gk-ortszeile li + li::before');
    T::ok((bool) preg_match('/var\(--gk-on-surface/', $all), 'text from a token');
    T::notContains($all, '#', 'no colour of its own');
    $focus = $rules($c, '.gk-ortszeile-link:focus-visible');
    T::contains($focus, 'outline: 2px solid', 'a link shows where the keyboard is');
    T::notContains($focus, 'outline: none', 'the ring is switched off');
},

// ---- the breadcrumb (2) and the house without style (6) ----------------

'the breadcrumb names itself and its current page; nothing else about it changed' => function (): void {
    Lang::set('en');
    $new = (new Header())->title('Invoices')->breadcrumb(['Billing' => '/b', 'Invoices'])->render();
    T::contains($new, '<nav class="gk-breadcrumb" aria-label="Breadcrumb">', 'the nav has a name');
    T::contains($new, '<span class="gk-breadcrumb-current" aria-current="page">', 'the last step says it is the page');
    $stripped = str_replace([' aria-label="Breadcrumb"', ' aria-current="page"'], '', $new);
    T::eq(sha1($stripped), 'a126385588d55c82aee1dfbbdb3feaf98b560613', 'the rest is byte for byte what 1.97.0 wrote');
    Lang::set('de');
    T::contains((new Header())->title('x')->breadcrumb(['A' => '/a', 'B'])->render(), 'aria-label="Brotkrume"', 'and in German');
    Lang::set('en');
},

'the house in the breadcrumb is a class, not an inline style' => function (): void {
    Lang::set('en');
    $h = (new Header())->title('x')->breadcrumb(['home' => '/', 'B' => '/b', 'C'])->render();
    T::notContains($h, 'style=', 'no inline style');
    T::contains($h, '<a class="gk-breadcrumb-home" href="/" title="Dashboard">', 'the link carries a class');
    T::contains($h, '<span class="material-icons" aria-hidden="true">home</span>', 'the symbol stays decoration');
},

// ---- the brand with an address, the bar without a brand (3) ------------

'Sidebar::brand without an address writes what it always wrote' => function () use ($echoed): void {
    Lang::set('en');
    $s1 = $echoed(fn () => (new Sidebar('main'))->brand('App', 'apps', 'v1')->group('G')->item('A', '/a', 'home', ['active' => true])->render());
    $s2 = $echoed(fn () => (new Sidebar('main'))->brand('App', 'apps')->collapsePosition('top')->item('A', '/a')->render());
    $s3 = $echoed(fn () => (new Sidebar('main'))->item('A', '/a')->render());
    T::eq(sha1($s1), 'b4184dec9e20b6aa6b625d167165ebca64697d34', 'with brand, version, group');
    T::eq(sha1($s2), '1163d75977065c54ab9ce3e67e533dde356a5417', 'with the collapse button on top');
    T::eq(sha1($s3), '873e1cccfc2b0084409eeca6d0de1252c4c77533', 'with no brand at all');
},

'Sidebar::brand with an address wraps symbol and name in one link' => function () use ($echoed): void {
    Lang::set('en');
    $h = $echoed(fn () => (new Sidebar('main'))->brand('Shorts', 'play_circle', 'v2', '/shorts')->item('A', '/a')->render());
    T::ok((bool) preg_match('#<a class="gk-sidebar-brand-link" href="/shorts">'
        . '<span class="material-icons gk-sidebar-brand-icon" aria-hidden="true">play_circle</span>'
        . '<div class="gk-sidebar-brand-text"><span class="gk-sidebar-brand-title">Shorts</span>'
        . '<span class="gk-sidebar-brand-version">v2</span></div></a>#', $h), 'the link holds the symbol and the text');
    T::ok(strpos($h, 'gk-sidebar-brand-link') < strpos($h, 'gk-sidebar-close-mobile'), 'and the close button stays outside it');
    T::eq(substr_count($h, 'gk-sidebar-close-mobile'), 1, 'one close button');
    $x = $echoed(fn () => (new Sidebar('main'))->brand('B', 'x', '', '"><script>')->render());
    T::notContains($x, '<script', 'the address is escaped');
},

'Sidebar::mobileClose keeps the close button on a bar without a brand' => function () use ($echoed): void {
    Lang::set('en');
    $h = $echoed(fn () => (new Sidebar('main'))->mobileClose()->item('A', '/a')->render());
    T::contains($h, '<div class="gk-sidebar-brand gk-sidebar-brand-bare">', 'a bar of its own');
    T::contains($h, 'aria-label="Close navigation"', 'the button has a name');
    T::contains($h, 'data-gk-sidebar-action="close"', 'and closes through the same listener');
    T::contains($h, '<span class="gk-sidebar-close-label">Close</span>', 'with the word beside the symbol');
    T::notContains($h, 'gk-sidebar-brand-text', 'and no brand');
    T::notContains($h, 'style=', 'no inline style');
    // With a brand the bar already has its button: the option must not add a second.
    $b = $echoed(fn () => (new Sidebar('main'))->brand('App')->mobileClose()->render());
    T::eq(substr_count($b, 'gk-sidebar-close-mobile'), 1, 'with a brand there is still one');
    T::notContains($b, 'gk-sidebar-brand-bare', 'and the ordinary bar');
    // Off, it is nothing.
    $o = $echoed(fn () => (new Sidebar('main'))->mobileClose(false)->item('A', '/a')->render());
    T::eq(sha1($o), '873e1cccfc2b0084409eeca6d0de1252c4c77533', 'switched off it writes what it always wrote');
},

'the bar without a brand is a phone thing: gone at a desk, the button a full target' => function () use ($css, $rules, $phone): void {
    $c = $css();
    T::contains($rules($c, '.gk-sidebar-brand-bare'), 'display: none', 'not drawn at a desk — an empty bar there would be a stripe');
    T::contains($rules($phone($c, '.gk-sidebar-brand-bare'), '.gk-sidebar-brand-bare'), 'display: flex', 'drawn on a phone');
    T::contains($rules($c, '.gk-sidebar-brand-bare .gk-sidebar-close-mobile'), 'min-height: var(--gk-target-min)', 'the button is 44px');
    T::contains($rules($c, '.gk-sidebar.collapsed .gk-sidebar-brand-link'), 'display: none', 'a collapsed bar has no empty link in the tab order');
},

// ---- the menu button with a word, aria-controls (4) --------------------

'the menu button is what it was, unless a word is asked for' => function (): void {
    Lang::set('en');
    $h = (new Header())->title('Invoices')->search('Find')->user('Jane Doe')->render();
    T::eq(sha1($h), 'ee321d8429e6da97c0cde55c97e06ca96298dfc6', 'byte for byte, as before');
    T::eq(sha1((new Header())->title('x')->sidebarToggle(true, [])->render()), sha1((new Header())->title('x')->render()), 'an empty option list changes nothing');
},

'the menu button with the word: symbol and "Menu", its name is the visible word' => function (): void {
    Lang::set('en');
    $h = (new Header())->title('x')->sidebarToggle(true, ['label' => true])->render();
    preg_match('#<button class="gk-header-menu-toggle[^>]*>.*?</button>#s', $h, $m);
    $b = $m[0] ?? '';
    T::contains($b, 'class="gk-header-menu-toggle gk-header-menu-toggle-word"', 'a modifier, so the plain button is untouched');
    T::contains($b, '<span class="gk-header-menu-label">Menu</span>', 'the word');
    T::notContains($b, 'aria-label', 'no second name that could differ from the word (WCAG 2.5.3)');
    T::contains($b, 'title="Open navigation"', 'the longer sentence stays as the tooltip');
    T::contains($b, 'aria-expanded="false"', 'it says whether the menu is open');
    T::contains($b, 'data-gk-sidebar-action="toggle"', 'and still opens it through the same listener');
    Lang::set('de');
    T::contains((new Header())->title('x')->sidebarToggle(true, ['label' => true])->render(), '>Menü</span>', 'German: Menü');
    Lang::set('en');
    T::contains((new Header())->title('x')->sidebarToggle(true, ['label' => 'Navigation'])->render(), '>Navigation</span>', 'or a word of the caller');
    T::notContains((new Header())->title('x')->sidebarToggle(true, ['label' => false])->render(), 'gk-header-menu-label', 'false is the old button');
    T::notContains((new Header())->title('x')->sidebarToggle(true, ['label' => true])->render(), 'style=', 'no inline style');
},

'aria-controls names the sidebar — only when both sides are given the same id' => function () use ($echoed): void {
    Lang::set('en');
    $btn = (new Header())->title('x')->sidebarToggle(true, ['controls' => 'panel-leiste'])->render();
    T::contains($btn, 'aria-expanded="false" aria-controls="panel-leiste"', 'the button points at it');
    $side = $echoed(fn () => (new Sidebar('main'))->elementId('panel-leiste')->item('A', '/a')->render());
    T::contains($side, '<aside class="gk-sidebar" id="panel-leiste" data-gk-sidebar="main">', 'and the aside carries that id');
    T::notContains((new Header())->title('x')->render(), 'aria-controls', 'without it, no dangling reference');
    T::notContains($echoed(fn () => (new Sidebar('main'))->item('A', '/a')->render()), ' id="', 'and the aside has no id of its own accord');
    $x = $echoed(fn () => (new Sidebar('main'))->elementId('"><script>')->render());
    T::notContains($x, '<script', 'the id is escaped');
},

// ---- skip link, tab order (5) -----------------------------------------

'Layout::skipLink is a link to the content, named, escaped, without inline style' => function (): void {
    Lang::set('en');
    T::eq(Layout::skipLink(), '<a class="gk-skip-link" href="#main">Skip to content</a>', 'the default');
    T::eq(Layout::skipLink('#inhalt', 'Zum Text'), '<a class="gk-skip-link" href="#inhalt">Zum Text</a>', 'a target and a word of the caller');
    Lang::set('de');
    T::contains(Layout::skipLink(), '>Zum Inhalt</a>', 'German: Zum Inhalt');
    Lang::set('en');
    T::notContains(Layout::skipLink('"><script>', '<b>'), '<script', 'the target is escaped');
    T::notContains(Layout::skipLink('"><script>', '<b>'), '<b>', 'and so is the word');
},

'the skip link hides above the screen until the keyboard reaches it, and is then a full target' => function () use ($css, $rules): void {
    $c = $css();
    $rest = $rules($c, '.gk-skip-link');
    T::contains($rest, 'position: absolute', 'out of the flow');
    T::contains($rest, 'translateY(-', 'moved off the top — not display:none, which no keyboard could reach');
    T::notContains($rest, 'display: none', 'still focusable');
    T::contains($rest, 'min-height: var(--gk-target-min)', 'a full target');
    T::contains($rules($c, '.gk-skip-link:focus'), 'transform: none', 'back in view on focus');
},

'a closed menu on a phone is out of the tab order and away from a screen reader' => function () use ($css, $rules, $phone): void {
    $mobile = $phone($css(), '.gk-sidebar.open');
    T::ok($mobile !== '', 'the phone block of the sidebar is found');
    $closed = $rules($mobile, '.gk-sidebar');
    T::contains($closed, 'visibility: hidden', 'closed: hidden, so Tab skips it (what `inert` does, without script)');
    T::contains($closed, 'visibility 0s linear', 'after the slide, so it is seen going out');
    $open = $rules($mobile, '.gk-sidebar.open');
    T::contains($open, 'visibility: visible', 'open: reachable');
    T::contains($open, 'transform: translateX(0)', 'and in place');
},

'in the header-first layout the header may come before the sidebar in the source' => function () use ($css): void {
    // The sidebar is position: fixed, so where it sits in the markup is a
    // matter of order for the keyboard, not of looks — as long as no rule
    // couples the two by sibling combinator. One did, and only for sidebar-first.
    $sheet = $css();
    $coupled = [];
    foreach (preg_split('/\n(?=\S)/', $sheet) ?: [] as $block) {
        if (!str_contains($block, 'header-first')) continue;
        $selector = explode('{', $block, 2)[0];
        if (preg_match('/\.gk-sidebar[^,{]*[~+]/', $selector)) $coupled[] = trim($selector);
    }
    T::eq($coupled, [], 'no header-first rule names the sidebar as an earlier sibling');
},

// ---- words and documentation ------------------------------------------

'the new words exist in both languages' => function (): void {
    $de = require __DIR__ . '/../lang/de.php';
    $en = require __DIR__ . '/../lang/en.php';
    foreach (['ortszeile.aria', 'ortszeile.dashboard', 'breadcrumb.aria', 'header.menu', 'layout.skip', 'sidebar.close_short'] as $key) {
        T::ok(isset($de[$key]) && $de[$key] !== '', "de has $key");
        T::ok(isset($en[$key]) && $en[$key] !== '', "en has $key");
    }
},

'on a phone the line takes a row of its own below the menu button and the user menu' => function () use ($css, $phone): void {
    $block = $phone($css(), '.gk-ortszeile-link');
    T::ok((bool) preg_match('/\.gk-header:has\(\.gk-ortszeile\)\s*\{[^}]*flex-wrap: wrap/s', $block), 'the header does not wrap');
    T::ok((bool) preg_match('/\.gk-header:has\(\.gk-ortszeile\) \.gk-ortszeile\s*\{[^}]*order: 1;[^}]*flex: 1 1 100%/s', $block), 'the line does not take the row below');
    T::ok((bool) preg_match('/\.gk-header-left:has\(\.gk-ortszeile\)[^{]*\{[^}]*display: contents/s', $block), 'the wrappers around the line do not step aside');
},

'a sticky header in the content column is not pushed down by the room a fixed one needs' => function () use ($css): void {
    $c = $css();
    $at = strpos($c, '.gk-with-sidebar:has(> .gk-header-sticky) {');
    T::ok($at !== false, 'no rule for the sticky header');
    T::contains(substr($c, (int) $at, 120), 'padding-top: 0', 'the sticky header keeps the 56px band');
    // Equal weight as the layout rules (an attribute and a class): the later one wins, so it must come after both.
    T::ok($at > (int) strrpos($c, '[data-gk-layout="sidebar-first"] .gk-with-sidebar'), 'the rule stands before the layout rules and loses');
},

'on a phone the open menu lies above its overlay in the sidebar-first layout too' => function () use ($css): void {
    $c = $css();
    preg_match('/\.gk-sidebar-overlay \{[^}]*z-index: (\d+)/s', $c, $o);
    preg_match('/@media \(max-width: 768px\) \{\s*\[data-gk-layout="sidebar-first"\] \.gk-header-fixed.*?\[data-gk-layout="sidebar-first"\] \.gk-sidebar \{[^}]*z-index: (\d+)/s', $c, $m);
    T::ok(isset($o[1], $m[1]), 'the z-index of the overlay or of the sidebar on a phone is not there');
    T::ok((int) $m[1] > (int) $o[1], 'the menu lies under its overlay: ' . ($m[1] ?? '?') . ' against ' . ($o[1] ?? '?'));
},

// ---- one row ('einzeilig') ----------------------------------------------

'one row: a class of its own, and the full text in title where it can be shortened' => function () use ($tiefe): void {
    $html = (new Header())->ortszeile($tiefe(['einzeilig' => true]))->render();
    T::contains($html, '<nav class="gk-ortszeile gk-ortszeile-einzeilig" aria-label=', 'the one-row line has no class of its own');
    T::contains($html, '<a class="gk-ortszeile-link" href="/shorts/liste" title="Alle Shorts">', 'a step that may be shortened keeps its text in title');
    T::contains($html, 'aria-current="page" title="Bunkertraining"', 'the page keeps its title');
    T::ok((bool) preg_match('#<a class="gk-ortszeile-link" href="/" title="[^"]+">#', $html), 'the dashboard, its house alone on a phone, has no title');
    T::contains($html, '<a class="gk-ortszeile-link" href="/shorts">', 'the module anchor, never shortened, repeats its word as a tooltip');
    $plain = (new Header())->ortszeile($tiefe())->render();
    T::contains($plain, '<nav class="gk-ortszeile" aria-label=', 'without the option the class changed');
    T::notContains($plain, 'gk-ortszeile-link" href="/shorts/liste" title=', 'without the option a link got a title');
},

'one row never wraps: the anchors keep their size, the rest shortens at its end' => function () use ($css, $rules): void {
    $c = $css();
    T::contains($rules($c, '.gk-ortszeile-einzeilig ol'), 'flex-wrap: nowrap', 'the one-row line wraps');
    T::contains($rules($c, '.gk-ortszeile-einzeilig li:nth-child(-n + 2)'), 'flex: 0 0 auto', 'an anchor gives way');
    T::contains($rules($c, '.gk-ortszeile-einzeilig li:last-child'), 'flex: 1 1 0', 'the page does not take what is left');
    $text = $rules($c, '.gk-ortszeile-einzeilig .gk-ortszeile-link .gk-ortszeile-text');
    T::contains($text, 'text-overflow: ellipsis', 'a shortened step is cut without a sign');
    T::contains($rules($c, '.gk-ortszeile-einzeilig .gk-ortszeile-link'), 'min-width: 0', 'a step cannot shorten');
},

'one row on a phone: the page stays in the row, the targets keep their height' => function () use ($css, $rules, $phone): void {
    $block = $phone($css(), '.gk-ortszeile-einzeilig');
    T::ok($block !== '', 'the phone has no rule for one row');
    T::contains($rules($block, '.gk-ortszeile-einzeilig li:nth-child(n + 3):last-child'), 'flex: 1 1 0', 'the page still takes a row of its own');
    T::notContains($rules($block, '.gk-ortszeile-einzeilig .gk-ortszeile-link'), 'min-height', 'one row lowers the target on a phone');
    T::notContains($block, 'display: none', 'one row hides a step');
    $word = $rules($block, '.gk-ortszeile-einzeilig li:first-child:not(:last-child) .gk-ortszeile-text');
    T::contains($word, 'clip-path: inset(50%)', 'the dashboard\'s word takes the room of the steps on a phone');
    T::notContains($word, 'display: none', 'the dashboard\'s word is gone for a screen reader too');
    T::contains($rules($block, '.gk-ortszeile-einzeilig li:first-child:not(:last-child) .gk-ortszeile-link'), 'min-width: var(--gk-target-min)', 'the house alone is smaller than a target');
},

'a page change without a full load takes the new line into the header' => function () use ($root): void {
    $js = (string) file_get_contents($root . '/js/gridkit.js');
    $at = strpos($js, '_render: function');
    T::ok($at !== false, 'GK.navigate has no _render');
    $render = substr($js, (int) $at, 2500);
    T::contains($render, "doc.querySelector('header .gk-ortszeile')", 'the new page\'s line is not read');
    T::contains($render, 'oldLine.replaceWith(', 'the old line stays');
},

'the skill file teaches the new calls' => function (): void {
    $doc = (string) file_get_contents(__DIR__ . '/../GRIDKIT_SKILL.md');
    foreach (['->ortszeile(', "'einzeilig'", 'Layout::skipLink(', '->mobileClose(', '->elementId(', "'controls'"] as $call) {
        T::contains($doc, $call, "the skill does not mention $call");
    }
},

];

})();
