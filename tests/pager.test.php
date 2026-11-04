<?php
/**
 * One pager (1.118.0): the pager of Table::paginate() is the bar of GridKit\Pagination.
 *
 * It used to be a row of pill buttons (Button::render, centred) while Pagination drew
 * .gk-rowpager: the count on the left, square .gk-pg boxes on the right, first and last included.
 * Both stood in the same panel and looked like two products. Held here: that the table writes
 * Pagination's structure (the same classes in the same order, page for page), the count and its
 * separators, the names a screen reader hears, the window, and what the script before 1.118.0 —
 * the PawBot dashboard keeps its own copy — binds to: <button data-gk-page>, a disabled button it
 * skips, and the class .gk-pagination it removes before it draws its own pager. That the two
 * look the same, and that both scripts page, is measured in a browser (ci/browser-pager.js).
 */

declare(strict_types=1);

use GridKit\{Lang, PageSize, Pagination, Table};

require_once __DIR__ . '/css-helpers.php';

/** n rows, "Item 1" … */
function gkPgrRows(int $n): array
{
    $rows = [];
    for ($i = 1; $i <= $n; $i++) $rows[] = ['id' => $i, 'name' => "Item $i"];
    return $rows;
}

/** A table's markup at a page, the request a plain one (not an AJAX reload). */
function gkPgrTable(int $rows, int $perPage, int $page = 1, bool $server = false): string
{
    $get = $_GET;
    $srv = $_SERVER;
    $_GET = ['gk_page' => (string) $page];
    unset($_SERVER['HTTP_X_REQUESTED_WITH']);
    try {
        $t = new Table('t');
        $server ? $t->rows(array_slice(gkPgrRows($rows), ($page - 1) * $perPage, $perPage), $rows) : $t->setData(gkPgrRows($rows));
        return T::capture(fn () => $t->column('name', 'Name')->paginate($perPage)->render());
    } finally {
        $_GET = $get;
        $_SERVER = $srv;
    }
}

/** The pager of a markup, '' when there is none. */
function gkPgrNav(string $html): string
{
    return preg_match('~<nav class="gk-pagination gk-rowpager".*?</nav>~s', $html, $m) ? $m[0] : '';
}

/** @return array{0:\DOMDocument,1:\DOMXPath} */
function gkPgrDom(string $html): array
{
    $dom = new \DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8"?><body>' . $html . '</body>');
    libxml_clear_errors();
    return [$dom, new \DOMXPath($dom)];
}

/**
 * The bar as a list, one entry per child of .gk-rowpager-nav: its class and what it shows (the
 * page number, the glyph, the ellipsis). Tags are left out on purpose: Pagination writes links,
 * the table buttons.
 *
 * @return list<string>
 */
function gkPgrShape(string $html): array
{
    [, $x] = gkPgrDom($html);
    $out = [];
    foreach ($x->query('//*[contains(concat(" ", @class, " "), " gk-rowpager-nav ")]/*') as $el) {
        /** @var \DOMElement $el */
        $out[] = $el->getAttribute('class') . ' | ' . trim($el->textContent);
    }
    return $out;
}

/** The nav's own structure: the classes of the bar and its two parts. @return list<string> */
function gkPgrFrame(string $html): array
{
    [, $x] = gkPgrDom($html);
    $out = [];
    foreach ($x->query('//nav[contains(concat(" ", @class, " "), " gk-rowpager ")]') as $nav) {
        /** @var \DOMElement $nav */
        foreach ($nav->childNodes as $c) {
            if (!$c instanceof \DOMElement) continue;
            $out[] = $c->tagName . '.' . $c->getAttribute('class');
            foreach ($c->childNodes as $cc) {
                if ($cc instanceof \DOMElement && $c->getAttribute('class') === 'gk-rowpager-info') $out[] = '  ' . $cc->tagName . '.' . $cc->getAttribute('class');
            }
        }
    }
    return $out;
}

/** @return array<string,callable> */
return [

'the table writes the bar of Pagination, page for page' => function (): void {
    Lang::set('en');
    // Short lists, long ones, the first, the middle and the last page: the window, the gaps and
    // the switched-off controls have to fall where Pagination puts them.
    foreach ([[12, 5], [60, 3], [95, 10], [10000, 25]] as [$rows, $per]) {
        $pages = (int) ceil($rows / $per);
        foreach (array_unique([1, 2, 3, intdiv($pages, 2), $pages - 1, $pages]) as $p) {
            if ($p < 1) continue;
            $table = gkPgrNav(gkPgrTable($rows, $per, $p));
            $alone = Pagination::build(['page' => $p, 'totalPages' => $pages, 'total' => $rows, 'baseUrl' => '/x']);
            T::eq(gkPgrShape($table), gkPgrShape($alone), "$rows rows at $per, page $p: the same controls with the same classes as Pagination");
            T::eq(gkPgrFrame($table), gkPgrFrame($alone), "$rows rows at $per, page $p: the same frame (info with its count, then the nav)");
        }
    }
},

'the frame: a named nav that keeps .gk-pagination, the count, the buttons' => function (): void {
    Lang::set('en');
    $nav = gkPgrNav(gkPgrTable(12, 5, 2));
    T::ok($nav !== '', 'a paginated table has the new pager');
    T::ok(str_starts_with($nav, '<nav class="gk-pagination gk-rowpager" aria-label="Page navigation"><span class="gk-rowpager-info"><span class="gk-rowpager-count">6–10 of 12</span></span><div class="gk-rowpager-nav">'),
        'the nav, its name, the count and the bar, in that order: ' . substr($nav, 0, 240));
    // The live pager of Pagination carries data-gk-pager, and GK.liveTable lifts every
    // [data-gk-pager] out of a live container. On a table's pager it would carry the pager
    // out of the table.
    T::notContains($nav, 'data-gk-pager', 'no data-gk-pager: GK.liveTable.hoistPager would lift it out of the table');
    T::notContains($nav, '<a ', 'no links: the table pages in place');
    T::notContains($nav, 'gk-btn', 'none of the old pill buttons');
    T::notContains($nav, 'style=', 'no inline style');
},

'every control is a button the script before 1.118.0 binds to' => function (): void {
    Lang::set('en');
    foreach ([[12, 5, 1], [12, 5, 2], [12, 5, 3], [400, 10, 17]] as [$rows, $per, $p]) {
        $pages = (int) ceil($rows / $per);
        [, $x] = gkPgrDom(gkPgrNav(gkPgrTable($rows, $per, $p)));
        $controls = $x->query('//*[contains(concat(" ", @class, " "), " gk-pg ")]');
        T::ok($controls->length >= 5, "page $p: controls found");
        $targets = [];
        foreach ($controls as $b) {
            /** @var \DOMElement $b */
            T::eq($b->tagName, 'button', "page $p: a control is a <button>");
            T::eq($b->getAttribute('type'), 'button', "page $p: type=button, so a form around the table is not sent");
            T::ok(ctype_digit($b->getAttribute('data-gk-page')), "page $p: data-gk-page holds a page number");
            $off = str_contains(' ' . $b->getAttribute('class') . ' ', ' gk-pg-off ');
            T::eq($b->hasAttribute('disabled'), $off, "page $p: a control that leads nowhere is disabled (the old script skips it), and only that one");
            $glyph = $x->query('.//span[@class="material-icons"]', $b)->item(0);
            $targets[$glyph ? $glyph->textContent : 'n' . $b->textContent] = (int) $b->getAttribute('data-gk-page');
        }
        T::eq($targets['first_page'], 1, "page $p: first leads to 1");
        T::eq($targets['chevron_left'], max(1, $p - 1), "page $p: previous");
        T::eq($targets['chevron_right'], min($pages, $p + 1), "page $p: next");
        T::eq($targets['last_page'], $pages, "page $p: last leads to $pages");
        T::eq($targets['n' . $p] ?? null, $p, "page $p: the current page is among the numbers");
    }
},

'the count: the rows of this page, of how many, with the separators of the language' => function (): void {
    Lang::set('en');
    $count = static fn (string $html): string => preg_match('~<span class="gk-rowpager-count">(.*?)</span>~', $html, $m) ? $m[1] : '';
    T::eq($count(gkPgrTable(12, 5, 1)), '1–5 of 12', 'the first page');
    T::eq($count(gkPgrTable(12, 5, 3)), '11–12 of 12', 'the last page ends at the last row');
    T::eq($count(gkPgrTable(1234, 100, 13)), '1,201–1,234 of 1,234', 'thousands carry the separator');
    T::eq($count(gkPgrTable(95, 10, 4, true)), '31–40 of 95', 'a server table counts from the total it was given');
    // A hand-typed page past the end shows the empty state: no rows, so no range.
    T::eq($count(gkPgrTable(12, 5, 99)), '12 entries', 'past the end: how many there are');
    T::contains(gkPgrTable(12, 5, 99), 'data-gk-page="3" aria-label="Previous" title="Previous">', 'and its Previous leads to the last page, not to page 98');
    Lang::set('de');
    T::eq($count(gkPgrTable(1234, 100, 13)), '1.201–1.234 von 1.234', 'German: its words and its separator');
    Lang::set('en');
},

'the names a screen reader hears, in both languages' => function (): void {
    foreach (['en' => ['Page navigation', 'First page', 'Previous', 'Next', 'Last page', 'Page 2 of 3'],
              'de' => ['Seitennavigation', 'Erste Seite', 'Zurück', 'Weiter', 'Letzte Seite', 'Seite 2 von 3']] as $lang => $want) {
        Lang::set($lang);
        $nav = gkPgrNav(gkPgrTable(12, 5, 2));
        [, $x] = gkPgrDom($nav);
        T::eq($x->query('//nav')->item(0)->getAttribute('aria-label'), $want[0], "$lang: the landmark is named");
        $names = [];
        foreach ($x->query('//button') as $b) $names[] = $b->getAttribute('aria-label');
        T::eq($names, [$want[1], $want[2], str_replace('2', '1', $want[5]), $want[5], str_replace('2 ', '3 ', $want[5]), $want[3], $want[4]],
            "$lang: every button is named for what it does — a page by its number and the count of pages");
        T::eq(substr_count($nav, 'aria-current="page"'), 1, "$lang: one current page");
        T::ok((bool) preg_match('~aria-label="' . preg_quote($want[5], '~') . '" aria-current="page"~', $nav), "$lang: and it is page 2");
        T::eq($x->query('//span[@class="material-icons"][not(@aria-hidden="true")]')->length, 0,
            "$lang: no glyph is read out (\"chevron_left\")");
    }
    Lang::set('en');
},

'the window: first, last, two either side of the current page — never 400 buttons' => function (): void {
    Lang::set('en');
    $numbers = static function (string $html): array {
        preg_match_all('~<button type="button" class="gk-pg(?: gk-pg-active)?" data-gk-page="(\d+)"~', $html, $m);
        return array_map('intval', $m[1]);
    };
    $big = gkPgrNav(gkPgrTable(10000, 25, 200, true));
    T::eq($numbers($big), [1, 198, 199, 200, 201, 202, 400], 'a 400-page list: seven numbers');
    T::eq(substr_count($big, '<span class="gk-pg-gap">…</span>'), 2, 'and a gap on either side');
    T::eq($numbers(gkPgrNav(gkPgrTable(12, 5, 1))), [1, 2, 3], 'three pages: all of them');
    T::notContains(gkPgrTable(12, 5, 1), 'gk-pg-gap', 'nothing skipped, no gap');
    T::eq(gkPgrNav(gkPgrTable(5, 5)), '', 'one page: no pager');
    T::eq(gkPgrNav(gkPgrTable(5, 0)), '', 'paginate(0): no pager');
    T::eq(gkPgrNav(gkPgrTable(12, 5, 1000000)) !== '', true, 'the highest page a link may ask for still renders, and ends');
},

'a server table brings its pager back with every reload' => function (): void {
    Lang::set('en');
    $get = $_GET;
    $srv = $_SERVER;
    try {
        $_GET = ['gk_table' => 's', 'gk_page' => '3'];
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $frag = T::capture(fn () => (new Table('s'))->rows(array_slice(gkPgrRows(95), 20, 10), 95)
            ->column('name', 'Name')->paginate(10)->render());
    } finally {
        $_GET = $get;
        $_SERVER = $srv;
    }
    T::notContains($frag, 'gk-table-wrap', 'the fragment, not the page');
    T::ok((bool) preg_match('~</table><nav class="gk-pagination gk-rowpager"~', $frag), 'the pager follows the table in the fragment');
    T::contains($frag, '<span class="gk-rowpager-count">21–30 of 95</span>', 'with the count of the page asked for');
    T::contains($frag, 'data-gk-page="3" aria-label="Page 3 of 10" aria-current="page"', 'and that page current');
},

'rows per page from PageSize: the table counts with them, Pagination keeps its select' => function (): void {
    Lang::set('en');
    $get = $_GET;
    try {
        $_GET = ['per_page' => '50'];
        $per = PageSize::make('per_page')->options([25, 50, 100])->resolve(25);
        T::eq($per, 50, 'PageSize reads the choice');
        $count = static fn (string $html): string => preg_match('~<span class="gk-rowpager-count">(.*?)</span>~', $html, $m) ? $m[1] : '';
        T::eq($count(gkPgrTable(120, $per, 2, true)), '51–100 of 120', 'the table counts in the chosen size');
        $_GET = ['per_page' => '7'];
        T::eq(PageSize::make('per_page')->options([25, 50, 100])->resolve(25), 25, 'a size not on the list falls back');
    } finally {
        $_GET = $get;
    }
    // Pagination with its select: unchanged by this release, the select in the info part.
    $html = Pagination::build(['page' => 2, 'totalPages' => 5, 'total' => 120, 'baseUrl' => '/x',
        'params' => ['q' => 'a'], 'pageSize' => ['current' => 25, 'options' => [25, 50]]]);
    T::ok((bool) preg_match('~<span class="gk-rowpager-info"><label class="gk-pagesize"~', $html), 'the select opens the info part');
    T::contains($html, 'data-preserve="{&quot;q&quot;:&quot;a&quot;}"', 'and keeps the pager\'s params');
},

'the standalone Pagination is as it was' => function (): void {
    Lang::set('en');
    T::eq(Pagination::build(['page' => 2, 'totalPages' => 3, 'total' => 12, 'baseUrl' => '/x', 'params' => ['q' => 'a b']]),
        '<nav class="gk-rowpager" data-gk-pager="" aria-label="Page navigation"><span class="gk-rowpager-info"><span class="gk-rowpager-count">12 entries · Page 2 of 3</span></span>'
        . '<div class="gk-rowpager-nav">'
        . '<a class="gk-pg gk-pg-icon" href="/x?q=a+b&amp;page=1" aria-label="First page" title="First page"><span class="material-icons" aria-hidden="true">first_page</span></a>'
        . '<a class="gk-pg gk-pg-icon" href="/x?q=a+b&amp;page=1" aria-label="Previous" title="Previous"><span class="material-icons" aria-hidden="true">chevron_left</span></a>'
        . '<a class="gk-pg" href="/x?q=a+b&amp;page=1">1</a>'
        . '<a class="gk-pg gk-pg-active" href="/x?q=a+b&amp;page=2" aria-current="page">2</a>'
        . '<a class="gk-pg" href="/x?q=a+b&amp;page=3">3</a>'
        . '<a class="gk-pg gk-pg-icon" href="/x?q=a+b&amp;page=3" aria-label="Next" title="Next"><span class="material-icons" aria-hidden="true">chevron_right</span></a>'
        . '<a class="gk-pg gk-pg-icon" href="/x?q=a+b&amp;page=3" aria-label="Last page" title="Last page"><span class="material-icons" aria-hidden="true">last_page</span></a>'
        . '</div></nav>',
        'page links, params kept, the same markup as 1.117.0');
},

'the count reaches the browser in the catalogue' => function (): void {
    foreach (['en' => '{from}–{to} of {total}', 'de' => '{from}–{to} von {total}'] as $lang => $want) {
        Lang::set($lang);
        T::eq(Lang::t('pagination.range'), $want, "$lang: pagination.range");
        preg_match('/window\.GK_LANG=(.*?);<\/script>/s', Lang::jsConfig(), $m);
        $js = (array) json_decode($m[1] ?? '', true);
        T::eq($js['pagination_range'] ?? null, $want, "$lang: exported as pagination_range, which the script reads");
    }
    Lang::set('en');
},

'the script writes the same pager after a rebuild, in one block at the end' => function (): void {
    $js = (string) file_get_contents(__DIR__ . '/../js/gridkit.js');
    $at = strpos($js, '// === 1.118.0 — one pager (Table::paginate) ===');
    T::ok($at !== false, 'the block is there');
    $block = substr($js, (int) $at);
    T::ok(!preg_match('~\n// === \d+\.\d+\.\d+ — ~', substr($block, 10)), 'and it is the last one');
    foreach (['GK.table._staticPager = function', 'GK.table.renderStatic = function', 'GK.table.reload = function',
              '<nav class="gk-pagination gk-rowpager" aria-label="', '<span class="gk-rowpager-info"><span class="gk-rowpager-count">',
              '<div class="gk-rowpager-nav">', '<span class="gk-pg-gap">…</span>', '<button type="button" class="gk-pg gk-pg-icon',
              '" data-gk-page="', ' aria-current="page"', '"pagination_range"', '"pagination_first"', '"pagination_last"',
              '"pagination_page_of"', '.format_thousands', 'drawing._gkMatching'] as $needle) {
        T::contains($block, $needle, 'the block writes and reads what Table.php does');
    }
    // The fallbacks are the English catalogue, so a page without Lang::jsConfig() reads as the server does.
    foreach (['pagination_range' => 'pagination.range', 'pagination_first' => 'pagination.first', 'pagination_prev' => 'pagination.prev',
              'pagination_next' => 'pagination.next', 'pagination_last' => 'pagination.last', 'pagination_aria' => 'pagination.aria',
              'pagination_page_of' => 'pagination.page_of'] as $jsKey => $key) {
        Lang::set('en');
        T::contains($block, '"' . $jsKey . '", "' . Lang::t($key) . '"', "$jsKey falls back to the English text");
    }
    // The click handler above the block is unchanged: it is what a copy without the block runs.
    $above = substr($js, 0, (int) $at);
    T::contains($above, 'const btn = e.target.closest("[data-gk-page]");', 'the delegated page click');
    T::contains($above, 'if (!btn || btn.disabled) return;', 'which skips a disabled control');
    T::contains($above, 'const oldPag = wrap.querySelector(".gk-pagination");', 'and the rebuild that removes .gk-pagination');
},

'the stylesheet: one block, the bar over .gk-pagination, the old script\'s pager in the same boxes' => function (): void {
    $css = (string) file_get_contents(__DIR__ . '/../css/gridkit.css');
    $at = strpos($css, '/* === 1.118.0 — one pager === */');
    T::ok($at !== false, 'the block is there');
    T::ok($at > strrpos($css, '/* === 1.117.0'), 'after the block of 1.117.0');
    $bare = (string) preg_replace('~/\*.*?\*/~s', '', substr($css, (int) $at));
    $rule = static fn (string $sel): string => preg_match('~(?:^|\n)' . preg_quote($sel, '~') . '\s*\{([^}]*)\}~', $bare, $m) ? $m[1] : '';
    $bar = $rule('.gk-pagination.gk-rowpager');
    foreach (['justify-content: space-between', 'gap: 12px', 'margin: 0', 'padding: 10px var(--gk-table-pad-x, 16px)'] as $d) {
        T::contains($bar, $d, 'the table pager restates the bar over .gk-pagination');
    }
    // .gk-pagination's own rule comes later than .gk-rowpager's and would centre the bar.
    T::ok(strpos($css, "\n.gk-pagination {") > strpos($css, "\n.gk-rowpager {"), 'the reason for the rule still holds');
    // The line above the bar is .gk-pagination's, so the block does not say it a second time.
    T::eq(gkCssDeclFor('.gk-pagination')['border-top'] ?? '', '1px solid var(--gk-border)', 'the line above the bar comes from .gk-pagination');
    T::contains($rule('button.gk-pg'), 'font-family: inherit', 'a button takes the font of the page');
    T::contains($rule('.gk-pg:focus-visible'), 'outline: 2px solid var(--gk-primary)', 'a pager control shows its focus');
    $old = '.gk-table-wrap > .gk-pagination > .gk-btn[data-gk-page]';
    foreach (['min-width: 34px', 'height: 34px', 'padding: 0 10px', 'border-radius: 8px', 'font-size: 13px', 'font-weight: 600'] as $d) {
        T::contains($rule($old), $d, 'the old script\'s button takes the size of .gk-pg');
    }
    T::contains($rule('.gk-table-wrap > .gk-pagination > .gk-btn.gk-btn-primary[data-gk-page]'), 'background: var(--gk-primary)',
        'and its current page the colour of .gk-pg-active');
    // The rules for that pager's frame and its gap name it by its buttons: the new bar, whose
    // first child is the count (a span), is a .gk-pagination straight inside the wrap too.
    T::ok(!preg_match('~\n\.gk-table-wrap > \.gk-pagination(?: > span)?\s*\{~', $bare), 'no rule for every .gk-pagination in a wrap');
    T::contains($bare, '.gk-table-wrap > .gk-pagination:has(> .gk-btn[data-gk-page]) {', 'the frame of the old pager, by its buttons');
    // Exactly the numbers of .gk-pg, so the two cannot drift apart unnoticed.
    $pg = gkCssDeclFor('.gk-pg');
    T::eq([$pg['min-width'] ?? '', $pg['height'] ?? '', $pg['border-radius'] ?? '', $pg['font-size'] ?? '', $pg['font-weight'] ?? ''],
        ['34px', '34px', '8px', '13px', '600'], '.gk-pg measures what the fallback copies');
},

'the arrows that lead somewhere carry their name as a title, as Pagination\'s do; a disabled one none (1.118.1)' => function (): void {
    $first = gkPgrTable(12, 5, 1);
    T::contains($first, 'data-gk-page="1" aria-label="First page" disabled>', 'First on page 1: disabled, no title');
    T::contains($first, 'data-gk-page="1" aria-label="Previous" disabled>', 'Previous on page 1: disabled, no title');
    T::contains($first, 'data-gk-page="2" aria-label="Next" title="Next">', 'Next: the title is its name');
    T::contains($first, 'data-gk-page="3" aria-label="Last page" title="Last page">', 'Last: the title is its name');
    preg_match_all('/<button[^>]*gk-pg-icon[^>]*>/', gkPgrTable(30, 5, 3), $m);
    foreach ($m[0] as $b) {
        preg_match('/aria-label="([^"]*)"/', $b, $l);
        T::contains($b, ' title="' . $l[1] . '"', 'an arrow in the middle of the list has its name as a title: ' . $b);
    }
},

];
