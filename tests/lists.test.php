<?php
/**
 * Lists that read well: the building blocks of 1.91.0 and the rules behind them.
 *
 * Two admin lists built with GridKit were turned down on 23.09.2026 — a select,
 * a switch AND a label saying the same thing in every cell, bold running text
 * in narrow columns, nine columns scrolling off the right. The redesign needed
 * three things GridKit did not have: a row that opens something, a side sheet
 * to open, and one row that stands for many. The rules that led there went
 * into GRIDKIT_SKILL.md, each naming the block that implements it.
 *
 * What a browser has to show — focus, Escape, the trap on a phone, the scroll
 * lock, both renderers agreeing after a sort — is in ci/browser.js. What is
 * here is what can be read: the markup PHP writes, and the contracts between
 * the stylesheet, the script and the documentation.
 */

declare(strict_types=1);

use GridKit\{Lang, Table};

// The WCAG maths live in contrast.test.php; loaded here too, so that
// `php tests/run.php lists` works on its own.
if (!function_exists('gkContrast')) {
    require_once __DIR__ . '/contrast.test.php';
}

/** @return array<string,callable> */
return (static function (): array {

$root = __DIR__ . '/..';
$css  = static fn (): string => (string) file_get_contents($root . '/css/gridkit.css');
$js   = static fn (): string => (string) file_get_contents($root . '/js/gridkit.js');
$doc  = static fn (): string => (string) file_get_contents($root . '/GRIDKIT_SKILL.md');

/** Render a table and keep every warning it raised. @return array{0:string,1:list<string>} */
$render = static function (callable $build): array {
    $warnings = [];
    set_error_handler(static function (int $n, string $m) use (&$warnings): bool {
        $warnings[] = $m;
        return true;
    });
    try {
        $html = T::capture(static function () use ($build): void { $build()->render(); });
    } finally {
        restore_error_handler();
    }
    return [$html, $warnings];
};

/** The <tr> elements of the body, each with its opening tag and its content. @return list<array{0:string,1:string}> */
$bodyRows = static function (string $html): array {
    $body = preg_match('~<tbody>(.*)</tbody>~s', $html, $m) ? $m[1] : '';
    preg_match_all('~(<tr\b[^>]*>)(.*?)</tr>~s', $body, $rows, PREG_SET_ORDER);
    return array_map(static fn (array $r): array => [$r[1], $r[2]], $rows);
};

/** The body of a JS function or object member, from its head to the next line at that indent. */
$jsBlock = static function (string $src, string $head, string $indent): string {
    $at = strpos($src, $head);
    if ($at === false) return '';
    $end = strpos($src, "\n" . $indent . '}', $at);
    return $end === false ? '' : substr($src, $at, $end - $at);
};

/** The declarations of the first rule whose selector list is exactly $selector. */
$rule = static function (string $css, string $selector): string {
    return preg_match('/(?:^|\n)' . preg_quote($selector, '/') . '\s*\{([^}]*)\}/', $css, $m) ? $m[1] : '';
};

return [

// ── A row that opens something ─────────────────────────────────────────────

'a row that opens a page carries one link in its main cell' => function () use ($render, $bodyRows): void {
    Lang::set('en');
    [$html, $warnings] = $render(static fn () => (new Table('users'))
        ->rows([
            ['id' => "7 o'k", 'name' => 'Jana Novak', 'mail' => 'jana@example.com', 'plan' => 'Pro'],
            ['id' => 8, 'name' => 'Tom Weber', 'mail' => 'tom@example.com', 'plan' => 'Free'],
        ], 2)
        ->rowLink('/users/{id}')
        ->column('name', 'User', ['sub' => 'mail'])
        ->column('plan', 'Plan'));

    T::eq($warnings, [], 'a plain row link raises no warning');
    $rows = $bodyRows($html);
    T::eq(count($rows), 2, 'two rows');
    foreach ($rows as [$tr, $inner]) {
        T::contains($tr, 'class="gk-row-link"', 'the row is not marked as one that opens something');
        T::eq(substr_count($inner, 'gk-row-target'), 1, 'a row carries exactly one control');
    }
    // The row's own value is encoded like a row button's: rawurlencode, so an
    // apostrophe and a space cannot leave the attribute or the path segment.
    T::contains($rows[0][1], '<a class="gk-row-target" href="/users/7%20o%27k">Jana Novak</a>',
        'the link is not the escaped, encoded target around the shown name');
    // The second line stays text under the control, not part of its name.
    T::ok((bool) preg_match('~</a><div class="gk-cell-sub">jana@example\.com</div>~', $rows[0][1]),
        'the second line moved into the link');
    // Only the main cell: the plan column is plain.
    T::ok((bool) preg_match('~<td data-label="Plan">Pro</td>~', $rows[0][1]), 'another cell was touched');
    // Nothing on the row itself pretends to be a control.
    T::notContains($html, '<tr class="gk-row-link" role=', 'the row was given a role — it would lose its row role');
    T::notContains($html, '<tr class="gk-row-link" tabindex', 'the row was put into the tab order');
},

'a row that opens a side sheet carries a button that names the sheet and the row' => function () use ($render, $bodyRows): void {
    Lang::set('en');
    [$html] = $render(static fn () => (new Table('users'))
        ->setData([['id' => 7, 'uid' => 'u-7', 'name' => 'Mia & "Co"']])
        ->rowLink(['sheet' => 'user-sheet', 'url' => 'panels/user.php?x=1&y=2', 'params' => ['uid' => 'uid']])
        ->column('name', 'User'));

    [$tr, $inner] = $bodyRows($html)[0] ?? ['', ''];
    T::contains($tr, 'class="gk-row-link"', 'the row is not marked');
    T::ok((bool) preg_match('~<button type="button" class="gk-row-target"([^>]*)>(.*?)</button>~s', $inner, $b),
        'the control is not a button of type button');
    $attrs = $b[1] ?? '';
    T::contains($attrs, 'data-gk-sheet="user-sheet"', 'the sheet is not named');
    T::contains($attrs, 'data-gk-sheet-url="panels/user.php?x=1&amp;y=2"', 'the url is not escaped into its attribute');
    // The title is text: the shown value, decoded once and escaped once.
    T::contains($attrs, 'data-gk-sheet-title="Mia &amp; &quot;Co&quot;"', 'the title is double-escaped or raw');
    T::contains($attrs, 'aria-haspopup="dialog"', 'nothing says the button opens a dialog');
    T::ok((bool) preg_match("~data-gk-params='([^']*)'~", $attrs, $p), 'the row carries no params');
    $params = json_decode(html_entity_decode($p[1] ?? '', ENT_QUOTES), true) ?: [];
    T::eq($params, ['uid' => 'u-7', 'id' => 7], 'the params are mapped like a row button\'s, with the row id');
    T::contains($b[2] ?? '', 'Mia &amp; &quot;Co&quot;', 'the shown name is not escaped');
},

'a row that cannot name its control stays a plain row' => function () use ($render, $bodyRows): void {
    // A link without a name is a focus stop that says nothing; a target the
    // allow list refuses must not become a row that opens nothing.
    Lang::set('en');
    [$html] = $render(static fn () => (new Table('t'))
        ->rows([
            ['id' => 1, 'name' => '',        'n' => 3, 'link' => 'ok'],
            ['id' => 2, 'name' => 'Visible', 'n' => 0, 'link' => 'ok'],
        ], 2)
        ->rowLink('/x/{id}')
        ->column('name', 'Name'));
    $rows = $bodyRows($html);
    T::ok(!str_contains($rows[0][0], 'gk-row-link') && !str_contains($rows[0][1], 'gk-row-target'),
        'a row with an empty main cell got a control');
    T::contains($rows[1][0], 'gk-row-link', 'the named row lost its control');

    // A number column that shows a dash for zero shows nothing to name it by.
    [$dash] = $render(static fn () => (new Table('t'))
        ->rows([['id' => 1, 'n' => 0]], 1)
        ->rowLink('/x/{id}')
        ->column('n', 'Count', ['format' => 'number']));
    T::notContains($dash, 'gk-row-target', 'a dash became the name of a link');

    // The same allow list as a row button: no disguised scheme in the
    // template, no protocol-relative address.
    foreach (["java\tscript:x{id}", 'javascript:{id}', '//evil.example/{id}'] as $template) {
        [$bad] = $render(static fn () => (new Table('t'))
            ->rows([['id' => 1, 'name' => 'A', 'link' => 'javascript:alert(1)']], 1)
            ->rowLink($template)
            ->column('name', 'Name'));
        T::notContains($bad, 'gk-row-target', "the target $template became a link");
        T::notContains($bad, 'gk-row-link', "the row still claims to open something for $template");
    }
    // A value can never become a scheme: it is encoded, colon included, even
    // where it makes up the whole template.
    foreach (['/go/{link}' => 'href="/go/javascript%3Aalert%281%29"', '{link}' => 'href="javascript%3Aalert%281%29"'] as $template => $want) {
        [$enc] = $render(static fn () => (new Table('t'))
            ->rows([['id' => 1, 'name' => 'A', 'link' => 'javascript:alert(1)']], 1)
            ->rowLink($template)
            ->column('name', 'Name'));
        T::contains($enc, $want, "the value in $template was not encoded");
        T::notContains($enc, 'href="javascript:', "the value in $template became a scheme");
    }
},

'a main cell that writes a link of its own is refused, and says so' => function () use ($render): void {
    Lang::set('en');
    foreach (['html', 'email'] as $format) {
        [$html, $warnings] = $render(static fn () => (new Table('t'))
            ->rows([['id' => 1, 'mail' => 'a@b.c']], 1)
            ->rowLink('/x/{id}')
            ->column('mail', 'Mail', ['format' => $format]));
        T::notContains($html, 'gk-row-target', "a '$format' cell was wrapped in the row's control");
        T::ok((bool) preg_grep('/rowLink.*' . $format . '/', $warnings), "the '$format' conflict is not reported");
    }
    // A column href on the same cell: the row's control takes its place — one
    // control per cell, never an <a> inside an <a>.
    [$html, $warnings] = $render(static fn () => (new Table('t'))
        ->rows([['id' => 1, 'name' => 'A']], 1)
        ->rowLink('/row/{id}')
        ->column('name', 'Name', ['href' => '/cell/{id}']));
    T::notContains($html, 'gk-cell-link', 'the cell link and the row control were nested');
    T::contains($html, 'href="/row/1"', 'the row control went missing');
    T::ok((bool) preg_grep('/rowLink.*href/', $warnings), 'the dropped cell link is not reported');
    // A column that is not there.
    [$html, $warnings] = $render(static fn () => (new Table('t'))
        ->rows([['id' => 1, 'name' => 'A']], 1)
        ->rowLink(['href' => '/x/{id}', 'column' => 'nope'])
        ->column('name', 'Name'));
    T::notContains($html, 'gk-row-link', 'a row link on a missing column marked the rows');
    T::ok((bool) preg_grep('/rowLink.*column/', $warnings), 'the missing column is not reported');
},

'the client rebuilds the row control from the data block, the same way' => function () use ($render, $js, $jsBlock): void {
    Lang::set('en');
    [$html] = $render(static fn () => (new Table('t'))
        ->setData([['2024' => 'x', 'name' => 'A', 'id' => 1]])
        ->rowLink(['sheet' => 's'])
        ->column('2024', 'Year')
        ->column('name', 'Name'));
    T::ok((bool) preg_match('~data-gk-data>(.*?)</script>~s', $html, $m), 'the data block is there');
    $data = json_decode($m[1] ?? '', true) ?: [];
    // Resolved on the server: JavaScript orders an object's numeric-looking
    // keys first, so "the first column" is not the same question on both sides.
    T::eq($data['rowLink']['column'] ?? null, '2024', 'the row link travels without its resolved column');
    T::eq($data['rowLink']['sheet'] ?? null, 's', 'the row link travels without its sheet');
    // And the server's own cell: PHP turns the key '2024' into the integer 2024,
    // so a strict comparison with the resolved (string) column never matched.
    T::ok((bool) preg_match('~<td data-label="Year"><button type="button" class="gk-row-target"~', $html),
        'a column with a numeric-looking key does not carry the row control on the server');

    $client = $jsBlock($js(), 'const rowTarget = (row) => {', '          ');
    T::ok($client !== '', 'renderStatic builds no row control');
    // The refusals, from the one list PHP keeps.
    $refused = (new ReflectionClassConstant(Table::class, 'ROW_LINK_REFUSED'))->getValue();
    foreach ($refused as $format) {
        T::contains($client, 'col.format === "' . $format . '"', "the client wraps a '$format' cell the server refuses");
    }
    foreach (['class="gk-row-target"', 'data-gk-sheet-title', 'aria-haspopup="dialog"', '_gkFillTarget(', '_gkRowParams(', 'shown === "—"'] as $part) {
        T::contains($client, $part, "the client's row control lacks $part");
    }
    T::contains($js(), "(target ? ' class=\"gk-row-link\"' : \"\")", 'the client does not mark the row');
},

'a target is filled and a row is mapped in one place per side' => function () use ($js, $root): void {
    // Row buttons, linked cells and now row links each fill a {field} template
    // and map a row's params. Written out three times on each side, every change
    // to the encoding or the allow list had to be made three times over — and
    // the two sides have to agree byte for byte. One helper per side, each
    // naming its twin.
    $php = (string) file_get_contents($root . '/src/Table.php');
    T::eq(substr_count($php, 'rawurlencode((string)'), 1, 'PHP fills a target template in more than one place');
    T::eq(substr_count($php, "array_key_exists('id', \$params)"), 1, 'PHP maps row params in more than one place');
    T::eq(substr_count($js(), 'encodeURIComponent(String(row[k]'), 1, 'the client fills a target template in more than one place');
    T::eq(substr_count($js(), 'hasOwnProperty.call(params, "id")'), 1, 'the client maps row params in more than one place');
    foreach (['self::fillTarget(' => 3, 'self::rowParams(' => 2] as $call => $n) {
        T::eq(substr_count($php, $call), $n, "$call is not what the three row controls use");
    }
    foreach (['_gkFillTarget(' => 4, '_gkRowParams(' => 3] as $call => $n) {
        T::eq(substr_count($js(), $call), $n, "$call is not what the three row controls use");
    }
},

'every control a row link writes has a name' => function () use ($render): void {
    // The general question names.test.php asks, asked of the new control.
    Lang::set('en');
    foreach ([['href' => '/x/{id}'], ['sheet' => 's']] as $target) {
        [$html] = $render(static fn () => (new Table('t'))
            ->rows([['id' => 1, 'name' => 'Jana']], 1)->rowLink($target)->column('name', 'Name'));
        T::ok((bool) preg_match('~class="gk-row-target"[^>]*>Jana</(a|button)>~', $html),
            'the row control is not named by the shown value: ' . json_encode($target));
    }
},

'the row is the target: 44px, a visible focus, a chevron in the text colour' => function () use ($css, $rule): void {
    $c = $css();
    $td = $rule($c, '.gk-table tbody tr.gk-row-link > td');
    T::ok((bool) preg_match('/height:\s*(\d+)px/', $td, $h) && (int) $h[1] >= 44, 'a link row is lower than 44px');
    T::contains($rule($c, '.gk-row-target:focus-visible'), 'outline: 2px solid var(--gk-primary)', 'the control shows no focus');
    // Where :has() exists the ring goes round the row — and only there may the
    // control's own ring be taken away.
    T::ok((bool) preg_match('/@supports selector\(:has\(\*\)\) \{\s*\.gk-table tbody tr\.gk-row-link \.gk-row-target:focus-visible \{\s*outline: none;/', $c),
        'the control loses its ring outside the :has() guard');
    $chevron = $rule($c, '.gk-table tbody tr.gk-row-link > td:last-child::after');
    T::contains($chevron, 'color: var(--gk-on-surface-variant)', 'the chevron is not in a role colour');
    // The chevron is a graphic: 3:1 against the row it sits on.
    T::ok(gkContrast(gkToken('gk-on-surface-variant'), gkToken('gk-surface')) >= 3.0, 'the chevron is too faint');
    // Card mode: the floor goes (every line of a card would be 44px) with a
    // selector that outranks the rule above, which is (0,2,3).
    T::contains($c, '.gk-table-mobile-card .gk-table tbody tr.gk-row-link > td {', 'card mode keeps the 44px floor on every line');
},

'a click in the row goes to its control, except where it belongs to something else' => function () use ($js, $jsBlock): void {
    $row = $jsBlock($js(), 'function _gkRowClick(e) {', '  ');
    T::ok($row !== '', 'the row click handler is gone');
    foreach (['a[href]', 'button', 'input', 'select', 'label', 'td.gk-cb-col', 'td.gk-actions'] as $own) {
        T::contains($row, $own, "a click on $own inside the row would open the row as well");
    }
    T::contains($row, 'row.contains(own)', 'a control OUTSIDE the row (an ancestor) would swallow every click');
    T::contains($row, 'getSelection', 'selecting text in a row follows the link');
    // The modifier keys travel along, so Ctrl-click opens a new tab.
    T::contains($row, 'ctrlKey: e.ctrlKey', 'Ctrl-click on a row loses the Ctrl');
    T::contains($js(), 'document.addEventListener("auxclick"', 'a middle click on a row does nothing');
},

// ── The side sheet ─────────────────────────────────────────────────────────

'the side sheet is hidden by its attribute and docks at the right' => function () use ($css, $rule): void {
    $c = $css();
    T::contains($rule($c, '.gk-sheet[hidden]'), 'display: none', 'hidden does not hide a sheet');
    // display:flex on the bare class, not on :not([hidden]) — same reasoning as the modal.
    $sheet = $rule($c, '.gk-sheet');
    T::contains($sheet, 'display: flex', 'the sheet is not a flex column');
    T::ok(!preg_match('/^\.gk-sheet:not\(\[hidden\]\)\s*\{/m', $c), 'display lives on :not([hidden]) and outranks a page\'s own hiding');
    T::contains($sheet, 'width: var(--gk-sheet-width)', 'the width is not the token');
    T::contains($sheet, 'right: 0', 'it does not dock right');
    T::ok((bool) preg_match('/--gk-sheet-width:\s*440px/', $c), 'the desktop width is not 440px');
    // Docked, it lies UNDER the header in every layout: 1.91.0 first put it at
    // 1200, over the header, and the user menu opened beneath the sheet. The
    // layers are read out of the stylesheet, so moving the header moves the
    // bar with it; ci/browser.js hit-tests the menu over an open sheet.
    $z = static fn (string $decls): int => preg_match('/z-index:\s*(\d+)/', $decls, $m) ? (int) $m[1] : -1;
    $koepfe = [
        $z($rule($c, '.gk-header')),
        $z($rule($c, "[data-gk-layout=\"header-first\"] .gk-header-fixed,\n.gk-layout-header-first .gk-header-fixed")),
        $z($rule($c, "[data-gk-layout=\"sidebar-first\"] .gk-header-fixed,\n.gk-layout-sidebar-first .gk-header-fixed")),
    ];
    T::ok(min($koepfe) > 0, 'a header layer could not be read: ' . implode(', ', $koepfe));
    $blatt = $z($sheet);
    T::ok($blatt > 0 && $blatt < min($koepfe), "the docked sheet ($blatt) is not under every header (" . implode(', ', $koepfe) . ')');
    // Under what opens from the page, too: a menu beside the sheet stays usable.
    T::ok($blatt < $z($rule($c, '.gk-dropdown-menu')), 'a dropdown opens under the docked sheet');
    // Dark mode reads a role, never a literal.
    T::ok((bool) preg_match('/\[data-gk-mode="dark"\] \.gk-sheet,\s*\.gk-dark \.gk-sheet \{[^}]*background: var\(--gk-/s', $c),
        'the dark sheet is not a role colour');
    T::ok((bool) preg_match('/@media \(prefers-reduced-motion: reduce\) \{\s*\.gk-sheet \{\s*animation: none;/', $c),
        'the slide-in ignores reduced motion');
},

'on a phone the sheet covers the screen and the page holds still — by markup, not by script' => function () use ($css, $js): void {
    $c = $css();
    T::ok((bool) preg_match('/@media \(max-width: 768px\) \{\s*\.gk-sheet \{([^}]*)\}/', $c, $m), 'no phone rule for the sheet');
    T::contains($m[1] ?? '', 'top: 0', 'the phone sheet does not start at the top');
    T::contains($m[1] ?? '', 'width: 100%', 'the phone sheet is not full width');
    // Full screen it is a layer over the page: over the header-first header and
    // the sidebar, under the modals GK.modal stacks from 9000.
    $voll = preg_match('/z-index:\s*(\d+)/', $m[1] ?? '', $zm) ? (int) $zm[1] : -1;
    $ueber = [];
    foreach (["[data-gk-layout=\"header-first\"] .gk-header-fixed,\n.gk-layout-header-first .gk-header-fixed", '.gk-sidebar'] as $sel) {
        $ueber[] = preg_match('/(?:^|\n)' . preg_quote($sel, '/') . '\s*\{[^}]*z-index:\s*(\d+)/', $c, $zm) ? (int) $zm[1] : PHP_INT_MAX;
    }
    T::ok($voll > max($ueber) && $voll < 9000, "the full-screen sheet ($voll) is not over the header and the sidebar (" . implode(', ', $ueber) . ') and under the modals');
    T::contains($js(), 'ov.style.zIndex = 9000 + this.stack.length * 10;', 'the modals no longer start at 9000 — check the sheet\'s layers');
    // The lock hangs on the sheet's own hidden attribute: nothing can be left
    // locked, and a server-rendered sheet locks too.
    T::ok((bool) preg_match('/:root:has\(\.gk-sheet:not\(\[hidden\]\)\) body \{\s*overflow: hidden;/', $c),
        'the page behind a full-screen sheet keeps scrolling');
    $sheet = substr($js(), (int) strpos($js(), 'GK.sheet = {'), 9000);
    T::notContains($sheet, 'style.overflow', 'the sheet writes the scroll lock itself — a second owner beside the lightbox\'s');
},

'the sheet is a dialog that is only modal where it covers the page' => function () use ($js, $jsBlock): void {
    $src = $js();
    T::ok(str_contains($src, 'GK.sheet = {'), 'GK.sheet is missing');
    foreach (['open(target, opts) {', 'close(target) {', 'upgrade(root) {'] as $m) {
        T::contains($src, $m, "GK.sheet has no $m");
    }
    // The breakpoint is the stylesheet's.
    T::contains($src, 'window.matchMedia("(max-width: 768px)")', 'the modal breakpoint is not the stylesheet\'s');
    $bind = $jsBlock($src, '_bind(sheet) {', '    ');
    // Escape on the sheet itself, so it closes only while the focus is in it.
    T::contains($bind, 'sheet.addEventListener("keydown"', 'Escape is not heard on the sheet');
    T::contains($bind, 'e.defaultPrevented', 'a widget inside that handled Escape loses it to the sheet');
    T::contains($bind, '_gkLayerAbove()', 'Escape meant for a confirm over the sheet closes the sheet');
    T::contains($bind, 'e.preventDefault();', 'the sheet does not claim its Escape — a modal underneath closes too');
    T::contains($bind, 'self._modal()) _gkTrap(sheet, e)', 'the trap is not limited to the modal case');
    // aria-modal only while it is true.
    $sync = $jsBlock($src, '_syncModal() {', '    ');
    T::contains($sync, 'sheet.removeAttribute("aria-modal")', 'a docked sheet keeps aria-modal');
    T::contains($src, '_gkSheetMq.addEventListener("change"', 'crossing the breakpoint while open changes nothing');
    // One implementation of each overlay behaviour in the library.
    $sheet = substr($src, (int) strpos($src, 'GK.sheet = {'), 9000);
    foreach (['_gkTrap(', '_gkRestoreFocus(', '_gkFocusInto(', '_gkNameCloseButton'] as $shared) {
        T::contains($sheet, $shared, "the sheet does not use the shared $shared");
    }
    T::notContains($sheet, 'function _gkTrap', 'the sheet carries a second focus trap');
    // Content that arrives later is upgraded: initContent and a live reload.
    T::ok((bool) preg_match('/GK\.initContent = function \(root\) \{.*?GK\.sheet\.upgrade\(root\);.*?\n  \};/s', $src),
        'initContent does not upgrade sheets');
    // An AJAX form in a sheet closes the sheet, not the top modal.
    T::contains($src, 'form.closest(".gk-modal-overlay, .gk-sheet")', 'a saved form in a sheet closes an unrelated modal');
},

// ── One row standing for many ──────────────────────────────────────────────

'a summary row folds its rows in every layout' => function () use ($css, $rule): void {
    $c = $css();
    // Card mode sets display:block on every tbody and tr at (0,1,1); the
    // browser's own [hidden] rule loses to that, so GridKit states it.
    T::ok((bool) preg_match('/\.gk-table tbody\[hidden\],\s*\.gk-table tr\[hidden\]\s*\{\s*display: none;/', $c),
        'hidden rows come back in card mode');
    $toggle = $rule($c, '.gk-table-more-toggle');
    T::ok((bool) preg_match('/min-height:\s*(\d+)px/', $toggle, $h) && (int) $h[1] >= 44, 'the summary toggle is under 44px');
    T::contains($toggle, 'width: 100%', 'the summary toggle does not fill its row');
    T::contains($c, '.gk-table-more-toggle[aria-expanded="true"]::after', 'the chevron does not follow the state');
    T::contains($rule($c, '.gk-table-more-toggle:focus-visible'), 'outline', 'the toggle shows no focus');
},

'the summary toggle moves both states and nothing else' => function () use ($js): void {
    $src = $js();
    $at  = strpos($src, 'e.target.closest(".gk-table-more-toggle")');
    T::ok($at !== false, 'nothing handles the summary toggle');
    $end = strpos($src, "\n  });", (int) $at);
    $block = substr($src, (int) $at, ($end === false ? 0 : $end - (int) $at));
    T::ok(strlen($block) > 200, 'the summary toggle handler was not found whole');
    T::contains($block, 'aria-controls', 'the toggle does not read what it controls');
    T::contains($block, 'setAttribute("aria-expanded"', 'aria-expanded is not kept in step');
    T::contains($block, 'removeAttribute("hidden")', 'the rows are not shown');
    // One state, one sign: the label does not flip between "show" and "hide".
    T::notContains($block, 'textContent', 'the toggle rewrites its own label');
    T::notContains($block, 'innerHTML', 'the toggle rewrites its own label');
},

// ── The rules ──────────────────────────────────────────────────────────────

'the six list rules are in the skill, each with a building block that exists' => function () use ($doc, $css, $js, $root): void {
    $md = $doc();
    T::ok((bool) preg_match('/^## Six rules for lists\n(.*?)(?=^## )/ms', $md, $m), 'the rules section is missing');
    $section = $m[1] ?? '';
    preg_match_all('/^(\d)\. \*\*(.+?)\*\*(.*?)(?=^\d\. \*\*|\z)/ms', $section, $rules, PREG_SET_ORDER);
    T::eq(array_map(static fn (array $r): string => $r[1], $rules), ['1', '2', '3', '4', '5', '6'], 'not six numbered rules');

    $php = '';
    foreach (glob($root . '/src/*.php') ?: [] as $f) $php .= (string) file_get_contents($f);
    $all = $css() . $js();
    foreach ($rules as [, $n, $title, $body]) {
        T::ok((bool) preg_match('/Building block:(.*)$/m', $body, $bb), "rule $n names no building block");
        preg_match_all('/`([^`]+)`/', $bb[1] ?? '', $names);
        T::ok(count($names[1]) > 0, "rule $n names its building block without code");
        foreach ($names[1] as $name) {
            // A class, a method or a GK call — each has to be something a
            // reader can actually use, or the rule points into the void.
            preg_match_all('/\.(gk-[a-z0-9-]+)|->(\w+)\(|(GK\.\w+(?:\.\w+)?)|\b(data-gk-[a-z-]+)|\b(aria-[a-z]+)/', $name, $parts, PREG_SET_ORDER);
            T::ok($parts !== [], "rule $n: `$name` is neither a class, a method nor a GK call");
            foreach ($parts as $p) {
                if (($p[1] ?? '') !== '') T::ok(str_contains($all, '.' . $p[1]), "rule $n names .{$p[1]}, which no stylesheet or script defines");
                if (($p[2] ?? '') !== '') T::ok((bool) preg_match('/function ' . $p[2] . '\(/', $php), "rule $n names ->{$p[2]}(), which no component has");
                if (($p[3] ?? '') !== '') T::ok(str_contains($js(), $p[3]) || str_contains($js(), explode('.', $p[3])[1] . ': {'), "rule $n names {$p[3]}, which gridkit.js does not define");
            }
        }
    }
    // The measured row the rules were drawn from.
    T::contains($section, '64px', 'the measured row is gone from the rules');
    T::contains($section, 'six columns', 'the column budget is gone from the rules');
},

'the specification carries the rules and the three blocks' => function () use ($root): void {
    $spec = (string) file_get_contents($root . '/SPEC.md');
    foreach (['Six rules for lists', '.gk-sheet', 'GK.sheet.open(', 'tr.gk-row-link', 'tr.gk-table-more', '->rowLink('] as $needle) {
        T::contains($spec, $needle, 'SPEC.md does not specify ' . $needle);
    }
},

];

})();
