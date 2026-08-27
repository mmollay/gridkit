<?php
/**
 * Promises the components make, checked against what they do.
 *
 * Every case here comes from one round of asking a single question of each
 * documented option: take the claim, use it exactly as written, and look at
 * what comes out. Thirteen of them diverged. The pattern that recurs is an
 * option a class accepts, stores, and never reads — which looks like a working
 * feature from the outside and fails silently forever.
 */

declare(strict_types=1);

use GridKit\{FilterChips, Lang, Layout, Modal, PageSize, Pagination, Sidebar, StatCards, Header, Select, Table, YearFilter};

/** @return array<string,callable> */
return [

'a numeric group label is a heading, not a fatal error' => function (): void {
    // Labels are array keys, so PHP casts '2024' to the integer 2024, and
    // under strict_types the escaper's string parameter made that a hard
    // TypeError — thrown mid-output, leaving <aside> and <nav> unclosed and
    // the rest of the page unrendered. A year is an ordinary heading.
    Lang::set('en');
    foreach (['2024', '0', '-5', 'Navigation', '<b>x</b>'] as $label) {
        $html = T::capture(fn() => (new Sidebar('s'))
            ->group($label)->item('Item', '/i')->render());
        T::contains($html, 'gk-sidebar-group-label', "group('$label') renders a heading");
        T::contains($html, '</aside>', "group('$label') closes the sidebar");
    }
    $html = T::capture(fn() => (new Sidebar('s'))->group('<b>x</b>')->item('I', '/i')->render());
    T::contains($html, '&lt;b&gt;x&lt;/b&gt;', 'the label is still escaped');
},

'two submenus with the same label get different ids' => function (): void {
    // The id was md5(label). Two collapsible items sharing a label produced
    // the same id twice, and getElementById returns the first — so clicking
    // the second toggle opened the first submenu.
    Lang::set('en');
    $html = T::capture(fn() => (new Sidebar('s'))
        ->group('Sales')->item('Monthly', '#', '', ['children' => [['label' => 'Jan', 'href' => '#']]])
        ->group('Purchases')->item('Monthly', '#', '', ['children' => [['label' => 'Feb', 'href' => '#']]])
        ->render());

    preg_match_all('/id="(gk-sub-[^"]*)"/', $html, $m);
    T::eq(count($m[1]), 2, 'both submenus render');
    T::eq(count(array_unique($m[1])), 2, 'their ids differ: ' . implode(', ', $m[1]));
},

'the trend a StatCard is sold on actually appears' => function (): void {
    // README.md describes the component as "KPI tiles with trend" and the
    // landing page shows the call. render() never read the option: the string
    // appeared nowhere and no CSS rule existed.
    Lang::set('en');
    $html = T::capture(fn() => (new StatCards('s'))
        ->card('Revenue', '12450', ['trend' => '+12%'])
        ->card('Churn',   '3.1%',  ['trend' => '-0.4%'])
        ->card('Flat',    '10')
        ->render());

    T::contains($html, '+12%', 'the trend value reaches the page');
    T::contains($html, 'gk-stat-trend-up',   'a rise is marked as one');
    T::contains($html, 'gk-stat-trend-down', 'a fall is marked as one');
    T::eq(preg_match_all('/<span class="gk-stat-trend /', $html), 2,
        'a card without a trend emits none');

    $css = (string) file_get_contents(__DIR__ . '/../css/gridkit.css');
    T::contains($css, '.gk-stat-trend-up',   'the rise has a rule');
    T::contains($css, '.gk-stat-trend-down', 'the fall has a rule');
},

'every chip colour the docs and demo use has a rule' => function (): void {
    // FilterChips passes ['color' => x] straight through as .gk-chip-x, so
    // whatever a caller writes needs a rule. Three existed; the demo ships
    // green, orange, red and blue and the skill file teaches primary — all of
    // which rendered as an unstyled chip.
    $css = (string) file_get_contents(__DIR__ . '/../css/gridkit.css');
    foreach (['danger', 'success', 'warning', 'primary', 'neutral',
              'red', 'green', 'orange', 'blue'] as $c) {
        T::contains($css, ".gk-chip-$c.gk-chip-active",
            "FilterChips accepts 'color' => '$c' and the stylesheet says nothing about it");
    }
},

'the asset stamp follows the file for the path the docs tell you to use' => function (): void {
    // The old test was "did preg_replace change the string?". For
    // '../css/gridkit.css' it does; for the bare 'css/gridkit.css' the capture
    // equals the input, so the one spelling skeleton.php and GRIDKIT_SKILL.md
    // teach was the one that never got the timestamp.
    $mtime = (string) filemtime(__DIR__ . '/../css/gridkit.css');
    foreach (['css/gridkit.css', '../css/gridkit.css', '/gridkit/css/gridkit.css'] as $p) {
        T::contains(Layout::asset($p), 'v=' . $mtime, "$p should carry the file's timestamp");
    }
    // Anything not a real file in the package falls back to the release.
    T::contains(Layout::asset('css/does-not-exist.css'), 'v=' . Layout::version(),
        'a missing file falls back to the version');
    T::contains(Layout::asset('https://cdn.example/css/x.css'), 'v=' . Layout::version(),
        'a remote URL falls back to the version');
},

'Modal::container emits nothing and stays callable' => function (): void {
    // It printed an empty hidden shell that nothing read — GK.modal.open()
    // builds its own overlay — so it sat in the DOM of every page, complete
    // with a close button a screen reader announced as the multiplication
    // sign. Kept as a no-op so existing layouts do not break.
    T::eq(T::capture(fn() => Modal::container()), '', 'container() emits nothing');
},

'a stray scalar in a header menu is a no-op, not a nameless link' => function (): void {
    // Anything that was not the string 'divider' fell through to the anchor
    // branch and emitted <a class="gk-dropdown-item" href="#"></a> — an empty,
    // focusable link with no name, sitting in the menu.
    Lang::set('en');
    $html = (new Header())->title('A')
        ->user('Jane', ['menu' => [
            ['label' => 'Profile', 'href' => '/p'],
            'divider',
            '---',                       // a typo, or another library's spelling
            ['label' => 'Sign out', 'href' => '/o'],
        ]])->render();

    T::eq(preg_match_all('/<a[^>]*gk-dropdown-item[^>]*><\/a>/', $html), 0,
        'no empty anchor reaches the menu');
    T::contains($html, 'gk-dropdown-divider', "'divider' still draws one");
},

'the controls that open things say that they do' => function (): void {
    Lang::set('en');
    // The user menu was a plain <div>: not in the tab order, announced as
    // nothing, and the only route to Profile, Settings and Sign out.
    $header = (new Header())->title('A')->user('Jane', ['menu' => [['label' => 'Out', 'href' => '/o']]])->render();
    preg_match('/<div class="gk-header-user"[^>]*>/', $header, $m);
    T::ok(isset($m[0]), 'the user menu trigger renders');
    T::contains($m[0], 'tabindex="0"',    'reachable by Tab');
    T::contains($m[0], 'role="button"',   'announced as a control');
    T::contains($m[0], 'aria-expanded',   'says whether it is open');

    // The searchable select is a <div tabindex="0"> — markup that claims the
    // element is operable — with only a click listener bound to it.
    $select = Select::searchable('country', ['at' => 'Austria'], ['placeholder' => 'Choose a country']);
    preg_match('/<div class="gk-select-display"[^>]*>/', $select, $s);
    T::ok(isset($s[0]), 'the select display renders');
    T::contains($s[0], 'role="combobox"', 'announced as a combobox');
    T::contains($s[0], 'aria-expanded',   'says whether it is open');
    T::contains($s[0], 'aria-label',      'has a name');

    $js = (string) file_get_contents(__DIR__ . '/../js/gridkit.js');
    T::contains($js, 'display.addEventListener("keydown"',
        'the select answers the keyboard it put itself in the tab order for');
},

'a required searchable select can actually be validated' => function (): void {
    // ['required' => true] was accepted, stored, and emitted onto an
    // <input type="hidden">. The HTML standard bars hidden inputs from
    // constraint validation, so the attribute was inert and the form
    // submitted with nothing selected.
    $html = Select::searchable('country', ['at' => 'Austria'], ['required' => true]);
    preg_match('/<input[^>]*name="country"[^>]*>/', $html, $m);
    T::ok(isset($m[0]), 'the value carrier renders');
    T::contains($m[0], 'required', 'the attribute is emitted');
    T::notContains($m[0], 'type="hidden"',
        'a hidden input cannot be validated — required on it does nothing');
    T::notContains($m[0], 'readonly',
        'readonly also bars constraint validation');

    $css = (string) file_get_contents(__DIR__ . '/../css/gridkit.css');
    T::contains($css, '.gk-select-value-input', 'the carrier is styled out of sight');
    // display:none would leave the browser unable to anchor its validation
    // bubble — "an invalid form control is not focusable".
    preg_match('/\.gk-select-value-input\s*\{[^}]*\}/s', $css, $rule);
    T::ok(isset($rule[0]), 'the rule exists');
    T::notContains($rule[0], 'display: none', 'a display:none control cannot show its message');
    T::notContains($rule[0], 'visibility: hidden', 'nor can a visibility:hidden one');
},

'the stylesheet has no rule whose selector swallowed a comment' => function (): void {
    // 1.42.0 inserted a rule between the selector and the brace of an existing
    // one. It stayed valid CSS and shipped: the searchable select's value
    // carrier was hidden only inside a toolbar — a visible 177px text input in
    // every plain form — and the toolbar's 34px height escaped onto every
    // select on every page. Neither shows up as a parse error.
    $css = (string) file_get_contents(__DIR__ . '/../css/gridkit.css')
         . (string) file_get_contents(__DIR__ . '/../css/themes.css');

    // A comment must not sit between a selector and its own opening brace.
    T::eq(preg_match_all('/[.#\w\]\)]\s+\/\*(?:[^*]|\*(?!\/))*\*\/\s*[.#\w\[]/s', $css), 0,
        'a comment is spliced into a selector — the rule below it is not the rule you think');

    // And the two rules that collided must both be their own thing.
    T::contains($css, "\n.gk-select-value-input {",
        'the carrier rule must apply everywhere, not only under another selector');
    T::contains($css, '.gk-toolbar .gk-select-search .gk-select-display {',
        'the 34px height belongs to toolbars only');
},

'a static table pages, as the skill file says it does' => function (): void {
    // GRIDKIT_SKILL.md has always said setData() "searches, sorts and pages in
    // JavaScript". Search and sort did; paging did not. The server emitted
    // every row plus a pager, the client re-render dropped the pager and never
    // rebuilt it, and the page buttons fired an AJAX reload — for a table
    // whose rows were already all in the browser. Given to five agents with
    // only this file to work from, the one who built a list shipped a table
    // showing everything on page one under a pager that led nowhere.
    Lang::set('en');
    $rows = [];
    for ($i = 1; $i <= 25; $i++) $rows[] = ['id' => $i, 'n' => 'Row ' . $i];

    $html = T::capture(fn() => (new Table('t'))
        ->setData($rows)->column('n', 'N')->paginate(10)->render());

    preg_match('/<tbody>(.*?)<\/tbody>/s', $html, $m);
    T::eq(substr_count($m[1] ?? '', '<tr'), 10,
        'the first render must show one page, not every row');
    T::contains($html, 'gk-pagination', 'a paginated static table renders a pager');

    // The client needs all of them to page without asking the server.
    preg_match('/data-gk-data>(.*?)<\/script>/s', $html, $d);
    $data = json_decode($d[1] ?? '{}', true);
    T::eq(count($data['rows'] ?? []), 25, 'the payload must carry every row');
    T::eq($data['perPage'] ?? null, 10, 'the payload must carry the page size');

    // Without paginate() nothing is sliced.
    $all = T::capture(fn() => (new Table('u'))->setData($rows)->column('n', 'N')->render());
    preg_match('/<tbody>(.*?)<\/tbody>/s', $all, $m2);
    T::eq(substr_count($m2[1] ?? '', '<tr'), 25, 'an unpaginated table shows everything');

    $js = (string) file_get_contents(__DIR__ . '/../js/gridkit.js');
    T::contains($js, '_staticPager', 'the client cannot rebuild the pager it removes');
    T::contains($js, 'wrap._gkPage = parseInt(btn.dataset.gkPage', 'the page button still asks the server');
},

'no component needs a superglobal that may not be there' => function (): void {
    // Auth::protect() read $_SERVER['REQUEST_URI'] without a fallback, so
    // guarding a page from a CLI task or a queue worker emitted a notice
    // before it redirected. Every other class in the library guards it.
    //
    // Reading the source for this is a losing game — !empty($_SERVER['HTTPS'])
    // is perfectly safe and a regex cannot tell. So strip the superglobal and
    // watch what the components actually do.
    $saved = $_SERVER;
    foreach (['REQUEST_URI', 'HTTP_HOST', 'QUERY_STRING', 'HTTPS', 'SCRIPT_NAME'] as $k) {
        unset($_SERVER[$k]);
    }

    $notices = [];
    set_error_handler(static function (int $n, string $msg) use (&$notices): bool {
        if (str_contains($msg, 'Undefined array key') || str_contains($msg, 'Undefined index')) {
            $notices[] = $msg;
        }
        return true;
    });

    Lang::set('en');
    try {
        // Enough rows that the pager really renders — one row at paginate(1)
        // renders none, which is how the first version of this check passed
        // while a missing guard sat two files away.
        $rows = [];
        for ($i = 1; $i <= 5; $i++) $rows[] = ['id' => $i, 'n' => 'Row ' . $i];
        T::capture(fn() => (new Table('t'))->setData($rows)
            ->search(['n'])->filter('n', 'select', ['options' => ['a' => 'A']])
            ->column('n', 'N')->paginate(2)->render());
        T::capture(fn() => Pagination::render(['page' => 2, 'totalPages' => 4, 'total' => 40]));
        T::capture(fn() => PageSize::make('per_page')->current(10)->render());
        T::capture(fn() => (new FilterChips('f', 'status'))->chip('a', 'A')->render());
        T::capture(fn() => (new StatCards('s'))->card('X', 1)->render());
        T::capture(fn() => (new YearFilter('y'))->years([2025, 2026])->render());
        (new Header())->title('T')->render();
        Layout::asset('css/gridkit.css');
    } finally {
        restore_error_handler();
        $_SERVER = $saved;
    }

    T::eq($notices, [], 'a component needs a superglobal that was not there: '
        . implode(' | ', array_unique($notices)));
},

'the year dropdown has a name like every other filter' => function (): void {
    Lang::set('en');
    $html = T::capture(fn() => (new YearFilter('y'))
        ->mode('dropdown')->baseUrl('/x')->years([2024, 2025])->render());
    preg_match('/<select[^>]*>/', $html, $m);
    T::ok(isset($m[0]), 'the dropdown renders');
    T::contains($m[0], 'aria-label', 'the year select announces what it filters');
},

];
