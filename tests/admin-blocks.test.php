<?php
/**
 * Blocks for an admin without its own CSS (1.92.0).
 *
 * Vespera's admin was built with GridKit and still carried about 465 rules of
 * its own CSS, around 100 of them overriding GridKit. Each block here replaces
 * a family of those rules; four of them were GridKit's own faults (a class the
 * skill promised and no rule gave, a hidden attribute that did not hide, a
 * close button under the target size, inline script in the sidebar).
 *
 * What a browser has to show — the columns giving way at four list widths, a
 * hidden message, the sidebar answering its attribute, a tile's focus ring —
 * is in ci/browser.js; the colours resolved across both stylesheets are in
 * ci/farben.js. What is here is what can be read: the markup PHP writes and
 * the contracts between the stylesheet, the script and the documentation.
 */

declare(strict_types=1);

use GridKit\{Form, Header, Lang, Sidebar, StatCards, Table};


/** @return array<string,callable> */
return (static function (): array {

$root = __DIR__ . '/..';
$css  = static fn (): string => (string) file_get_contents($root . '/css/gridkit.css');
$js   = static fn (): string => (string) file_get_contents($root . '/js/gridkit.js');
$doc  = static fn (): string => (string) file_get_contents($root . '/GRIDKIT_SKILL.md');

/** The declarations of the first rule whose selector list is exactly $selector (top level). */
$rule = static function (string $css, string $selector): string {
    return preg_match('/(?:^|\n)' . preg_quote($selector, '/') . '\s*\{([^}]*)\}/', $css, $m) ? $m[1] : '';
};

/** Byte offset of a top-level rule, for "comes after" checks; -1 when missing. */
$at = static function (string $css, string $selector): int {
    return preg_match('/(?:^|\n)' . preg_quote($selector, '/') . '\s*\{/', $css, $m, PREG_OFFSET_CAPTURE) ? (int) $m[0][1] : -1;
};

/** A token's hex value in the light block or in the dark block of gridkit.css. */
$token = static function (string $name, bool $dark) use ($css): string {
    $c = $css();
    if ($dark) {
        preg_match('/\n\[data-gk-mode="dark"\],\n\.gk-dark \{(.*?)\n\}/s', $c, $b);
        $c = $b[1] ?? '';
    }
    return preg_match('/--' . preg_quote(ltrim($name, '-'), '/') . ':\s*(#[0-9a-fA-F]{6})/', $c, $m) ? $m[1] : '';
};

/*
 * The WCAG ratio, as contrast.test.php computes it (gkContrast()). A copy and
 * not a require: this file runs before that one, which declares its functions
 * unguarded — loading it here would make the runner declare them twice.
 */
$contrast = static function (string $a, string $b): float {
    $lum = static function (string $hex): float {
        $h = ltrim($hex, '#');
        $ch = static fn (int $v): float => ($c = $v / 255) <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        return 0.2126 * $ch((int) hexdec(substr($h, 0, 2))) + 0.7152 * $ch((int) hexdec(substr($h, 2, 2)))
             + 0.0722 * $ch((int) hexdec(substr($h, 4, 2)));
    };
    [$la, $lb] = [$lum($a), $lum($b)];
    return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
};

/** Render a component and keep every warning it raised. @return array{0:string,1:list<string>} */
$render = static function (callable $fn): array {
    $warnings = [];
    set_error_handler(static function (int $n, string $m) use (&$warnings): bool {
        $warnings[] = $m;
        return true;
    });
    try {
        $html = T::capture($fn);
    } finally {
        restore_error_handler();
    }
    return [$html, $warnings];
};

/** OKLab of a #rrggbb colour, x100 — the distance the series check uses. @return array{0:float,1:float,2:float} */
$oklab = static function (string $hex): array {
    $lin = static function (int $v): float {
        $c = $v / 255;
        return $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
    };
    $h = ltrim($hex, '#');
    [$r, $g, $b] = [$lin((int) hexdec(substr($h, 0, 2))), $lin((int) hexdec(substr($h, 2, 2))), $lin((int) hexdec(substr($h, 4, 2)))];
    $l = (0.4122214708 * $r + 0.5363325363 * $g + 0.0514459929 * $b) ** (1 / 3);
    $m = (0.2119034982 * $r + 0.6806995451 * $g + 0.1073969566 * $b) ** (1 / 3);
    $s = (0.0883024619 * $r + 0.2817188376 * $g + 0.6299787005 * $b) ** (1 / 3);
    return [
        100 * (0.2104542553 * $l + 0.7936177850 * $m - 0.0040720468 * $s),
        100 * (1.9779984951 * $l - 2.4285922050 * $m + 0.4505937099 * $s),
        100 * (0.0259040371 * $l + 0.7827717662 * $m - 0.8086757660 * $s),
    ];
};

return [

// ── Fixed ─────────────────────────────────────────────────────────────────

'every class name the skill and the skeleton name has a rule' => function () use ($css, $js, $doc, $root): void {
    // <main class="gk-main"> was in the skill ("padding and max-width") and in
    // skeleton.php for years, and no rule gave it either — every page wrote the
    // padding itself. A class that is documented has to exist.
    $text = $doc() . (string) file_get_contents($root . '/skeleton.php');
    preg_match_all('/(?<![\w-])gk-[a-z0-9]+(?:-[a-z0-9]+)*(?![\w{-])/', $text, $m);
    $names = array_unique($m[0]);
    T::ok(count($names) > 150, 'the scan found only ' . count($names) . ' class names — the pattern broke');

    // Events are not classes: gridkit.js dispatches them by these names.
    preg_match_all('/CustomEvent\(\s*["\'](gk-[a-z-]+)["\']/', $js(), $ev);
    $events = array_unique($ev[1]);
    T::ok(count($events) >= 3, 'no event names found in gridkit.js — the pattern broke');
    // A state class GridKit sets for a page's own CSS to hang on; it styles nothing itself.
    $hooks = ['gk-modal-open'];

    // Comments stripped: a class the stylesheet only talks about has no rule.
    $all = (string) preg_replace('~/\*.*?\*/~s', '', $css() . (string) file_get_contents($root . '/css/themes.css'));
    foreach ($names as $name) {
        if (in_array($name, $events, true) || in_array($name, $hooks, true)) continue;
        T::ok((bool) preg_match('/\.' . preg_quote($name, '/') . '(?![\w-])/', $all),
            "the skill or skeleton.php names .$name, and no stylesheet rule has it");
    }
    T::ok(str_contains((string) file_get_contents($root . '/skeleton.php'), '<main class="gk-main">'),
        'the skeleton no longer shows the content element');
},

'.gk-main carries the padding, the cap and a phone gutter' => function () use ($css, $rule): void {
    $main = $rule($css(), '.gk-main');
    T::contains($main, 'padding: 24px 28px 48px', 'the content area has no padding of its own');
    T::contains($main, 'max-width: var(--gk-main-max, 1680px)', 'the cap is not the documented hook');
    // Inside a phone query: the nearest @media before the rule is the 768px one.
    $c = $css();
    $phone = (int) strpos($c, "  .gk-main {\n    padding: 16px 16px 40px;");
    $media = strrpos(substr($c, 0, $phone), '@media');
    T::ok($phone > 0 && $media !== false && str_starts_with(substr($c, $media), '@media (max-width: 768px)'),
        'on a phone the content keeps the desktop padding instead of a 16px gutter');
},

'the hidden attribute hides every gk- block, whatever display its class sets' => function () use ($css, $rule): void {
    $c = $css();
    // The reason: a message is a flex box, so the browser's [hidden] lost to it.
    T::contains($rule($c, '.gk-message'), 'display: flex', 'the case this guards against is gone — check the test');
    $guard = $rule($c, ':where([class^="gk-"], [class*=" gk-"])[hidden]');
    T::contains($guard, 'display: none !important', 'a gk- element with the hidden attribute can still show');
    // Zero specificity for the class part: the guard must not win anything but
    // the hidden state, and !important is how it wins that one.
    T::ok(!preg_match('/\n\.gk-[a-z-]+\[hidden\]\s*\{[^}]*!important/', $c),
        'a second, class-specific !important guard crept in beside the general one');
},

'the side sheet closes from a 44px target, docked and on a phone' => function () use ($css, $rule): void {
    $c = $css();
    T::ok((bool) preg_match('/--gk-target-min:\s*44px;/', $c), 'the pointer target is not a 44px token');
    // The first top-level rule naming .gk-sheet-close is shared with the modal's
    // close button; the second is the sheet's own, and it decides.
    preg_match_all('/\n\.gk-sheet-close \{([^}]*)\}/', $c, $all);
    T::eq(count($all[1]), 2, 'the sheet close button is no longer set in two top-level rules — check which one decides');
    $close = $all[1][1] ?? '';
    T::contains($close, 'width: var(--gk-target-min)', 'the docked close button is not the target width');
    T::contains($close, 'height: var(--gk-target-min)', 'the docked close button is not the target height');
    // The header keeps the height the 40px button gave it.
    T::contains($close, 'margin: calc((40px - var(--gk-target-min)) / 2)', 'the larger button moves the title');
    // No media query sets it smaller again (a phone rule used to write 44px of its own).
    T::ok(!preg_match('/\n\s+\.gk-sheet-close \{[^}]*(?:width|height):/', $c),
        'a media query sizes the sheet close button on its own again');
},

'the sidebar writes no inline script, and one listener answers its controls' => function () use ($render, $js): void {
    Lang::set('en');
    $html = '';
    foreach (['top', 'bottom'] as $pos) {
        [$h] = $render(static fn () => (new Sidebar('s'))->brand('App', 'apps')->collapsePosition($pos)
            ->item('Home', '/', 'home')->render());
        $html .= $h;
    }
    [$toggle] = $render(static fn () => Sidebar::toggleButton());
    $header = (new Header())->title('T')->sidebarToggle(true)->render();
    $all = $html . $toggle . $header;

    T::notContains($all, 'onclick', 'a sidebar control still carries inline script');
    foreach (['toggle', 'close', 'collapse'] as $action) {
        T::contains($all, 'data-gk-sidebar-action="' . $action . '"', "no control asks for $action");
    }
    T::contains($html, 'data-gk-sidebar-overlay data-gk-sidebar-action="close"', 'the overlay no longer closes the sidebar');
    T::contains($header, 'data-gk-sidebar-action="toggle"', 'the header hamburger lost its action');

    $src = $js();
    T::ok((bool) preg_match('/var _gkSidebarActions = \{ toggle: 1, close: 1, collapse: 1 \};/', $src),
        'the delegation does not know exactly the three actions');
    T::contains($src, 'e.target.closest("[data-gk-sidebar-action]")', 'nothing listens for the attribute');
    T::contains($src, 'GK.sidebar[action]();', 'the listener does not call the API the attribute names');
    // The API stays.
    foreach (['toggle() {', 'close() {', 'open() {', 'collapse() {'] as $m) {
        T::contains(substr($src, (int) strpos($src, 'GK.sidebar = {'), 4000), $m, "GK.sidebar lost $m");
    }
    // aria-expanded on the hamburger follows the sidebar.
    T::contains($src, '[data-gk-sidebar-action="toggle"][aria-expanded]', 'the toggle keeps announcing "collapsed"');
},

// ── A list whose columns give way ─────────────────────────────────────────

'a list is a size container, and no other table is' => function () use ($css, $rule, $at): void {
    $c = $css();
    T::contains($rule($c, '.gk-table-wrap.gk-table-list'), 'container: gk-list / inline-size', 'the list is not a named size container');
    // container-type changes how a box is sized; on every wrap it would move
    // tables that sit in a flex row. One rule may set it.
    // Declarations only (indented), not the word in a comment.
    T::eq(preg_match_all('/\n\s+container(?:-type)?\s*:/', $c), 1, 'something besides the list is a size container');
    // A list does not scroll sideways: its min-width beats the phone rule that
    // gives every other table 540px — same specificity, so it must come later.
    $min = $at($c, '.gk-table-wrap.gk-table-list .gk-table');
    T::contains($rule($c, '.gk-table-wrap.gk-table-list .gk-table'), 'min-width: 0', 'a list keeps a minimum width');
    T::ok($min > (int) strpos($c, '.gk-table-wrap:not(.gk-table-mobile-card) .gk-table {'),
        'the phone rule (540px) comes after the list rule and wins');
    T::contains($rule($c, '.gk-table-list .gk-table tbody tr:not(.gk-table-group, .gk-table-more) > td'), 'height: 64px',
        'the list row is not the 64px of the six rules');
    T::contains($rule($c, '.gk-table-list .gk-row-target'), 'font-size: 15px', 'the name is not 15px');
    T::contains($rule($c, '.gk-table-list .gk-cell-sub'), 'font-size: var(--gk-text-label)', 'the second line is not 13px');
},

'size(list) writes the list, and refuses a mobile mode that would undo it' => function () use ($render): void {
    Lang::set('en');
    $rows = [['id' => 1, 'name' => 'A']];
    [$list, $w] = $render(static fn () => (new Table('l'))->setData($rows)->size('list')->column('name', 'Name')->render());
    T::ok((bool) preg_match('/<div class="gk-table-wrap gk-table-list"/', $list), 'the wrap does not carry gk-table-list alone');
    T::eq($w, [], 'a plain list raised a warning');
    foreach (['card', 'scroll'] as $mode) {
        [$h, $warn] = $render(static fn () => (new Table('l'))->setData($rows)->size('list')->mobile($mode)->column('name', 'Name')->render());
        T::notContains($h, 'gk-table-mobile-', "a list took mobile('$mode')");
        T::ok((bool) preg_grep('/size\(\'list\'\).*mobile\(\'' . $mode . '\'\)/', $warn), "mobile('$mode') on a list is dropped silently");
    }
    // Everything else keeps card mode as its default.
    [$plain] = $render(static fn () => (new Table('t'))->setData($rows)->column('name', 'Name')->render());
    T::contains($plain, 'gk-table-mobile-card', 'a table that is not a list lost its card mode');
},

'columns give way by the width of the list at 560, 720, 900 and 1040px' => function () use ($css, $rule): void {
    $c = $css();
    // The widths are the list as it stands on the page. A container measures
    // inside its border, so each query is the wrap's two border pixels short —
    // written as max-width: 899px, a list exactly 900px wide lost its column.
    T::contains($rule($c, '.gk-table-wrap'), 'border: 1px solid', 'the wrap border changed — move the list queries with it');
    foreach ([2 => 560, 3 => 720, 4 => 900, 5 => 1040] as $p => $list) {
        T::ok((bool) preg_match('/@container gk-list \(width < ' . ($list - 2) . 'px\) \{\s*\.gk-table-list \.gk-col-p' . $p . ' \{\s*display: none;/', $c),
            "priority $p does not give way below {$list}px of list, border included");
    }
    // The window never decides, and nothing outside a list hides by priority.
    T::ok(!preg_match('/@media[^{]*\{[^@]*\.gk-col-p\d/', $c), 'a priority class hides by the window width');
    T::ok(!preg_match('/(?<!gk-table-list )\.gk-col-p\d \{/', $c), 'a priority class works outside a list');
},

"'priority' reaches header and cells on both renderers, and only 2 to 5 are taken" => function () use ($render, $js): void {
    Lang::set('en');
    [$html, $w] = $render(static fn () => (new Table('t'))
        ->rows([['id' => 1, 'name' => 'A', 'plan' => 'Pro', 'seen' => 'today']], 1)
        ->size('list')
        ->column('name', 'Name')
        ->column('plan', 'Plan', ['priority' => 2, 'sortable' => true])
        ->column('seen', 'Seen', ['priority' => '4'])
        ->render());
    T::eq($w, [], 'a valid priority raised a warning');
    T::ok((bool) preg_match('/<th scope="col" class="gk-sortable gk-col-p2"/', $html), 'the header does not give way with its column');
    T::contains($html, '<td class="gk-col-p2" data-label="Plan">', 'the cell does not carry the priority');
    T::contains($html, '<th scope="col" class="gk-col-p4">Seen</th>', "the string '4' is not read as 4");
    T::contains($html, '<td data-label="Name">', 'the column without priority was given one');

    foreach ([0, 6, 9, 'x', 2.5, [3]] as $bad) {
        [$h, $warn] = $render(static fn () => (new Table('t'))->rows([['id' => 1, 'x' => 'v']], 1)
            ->column('x', 'X', ['priority' => $bad])->render());
        T::notContains($h, 'gk-col-p', 'priority ' . json_encode($bad) . ' became a class');
        T::ok((bool) preg_grep("/'priority'/", $warn), 'priority ' . json_encode($bad) . ' is refused silently');
    }
    [$one, $warnOne] = $render(static fn () => (new Table('t'))->rows([['id' => 1, 'x' => 'v']], 1)
        ->column('x', 'X', ['priority' => 1])->render());
    T::ok(!str_contains($one, 'gk-col-p') && $warnOne === [], 'priority 1 (never) is not a quiet "always show"');

    // The client rebuild writes the same class, from the int the server kept.
    $src = $js();
    T::contains($src, 'function _gkPriorityClass(col) {', 'the client has no priority helper');
    T::eq(substr_count($src, '_gkPriorityClass(col)'), 3, 'the client does not write the priority on header and cell');
    T::contains($src, '(prio ? " " + prio : "")', 'a sortable header drops its priority on rebuild');
},

'the who cell keeps the name whole and the address unshortened' => function () use ($css, $rule): void {
    $c = $css();
    $text = $rule($c, '.gk-cell-who-text');
    T::contains($text, 'flex-wrap: wrap', 'a label beside the name shortens it instead of moving under it');
    T::contains($rule($c, '.gk-cell-who-text > .gk-label'), 'flex: 0 0 auto', 'the label itself is squeezed');
    T::contains($rule($c, '.gk-cell-who-text > .gk-cell-sub'), 'flex: 1 0 100%', 'the address does not get its own line');
    $wrap = $rule($c, '.gk-cell-sub.gk-cell-sub-wrap');
    foreach (['white-space: normal', 'text-overflow: clip', 'overflow-wrap: anywhere'] as $d) {
        T::contains($wrap, $d, "the unshortened second line lacks $d");
    }
    T::contains($rule($c, '.gk-table-list .gk-table td:has(> .gk-cell-who)'), 'max-width: 0', 'the who column widens the list');
},

// ── Parts ─────────────────────────────────────────────────────────────────

'avatar tones are role pairs that read in both modes and beat the dark avatar rule' => function () use ($css, $rule, $at, $token, $contrast): void {
    $c = $css();
    $pairs = [1 => ['primary-container', 'on-primary-container'], 2 => ['tertiary-container', 'on-tertiary-container'],
              3 => ['info-container', 'on-info-container'], 4 => ['warning-container', 'on-warning-container'],
              5 => ['surface-container-high', 'on-surface-variant']];
    $measured = 0;
    $dark = max($at($c, "[data-gk-mode=\"dark\"] .gk-avatar,\n.gk-dark .gk-avatar"), 0);
    T::ok($dark > 0, 'the dark avatar rule moved — check what the tones have to beat');
    foreach ($pairs as $n => [$bg, $fg]) {
        $sel = '.gk-avatar.gk-avatar-tone-' . $n;
        $r = $rule($c, $sel);
        T::contains($r, "background: var(--gk-$bg)", "tone $n is not the $bg role");
        T::contains($r, "color: var(--gk-$fg)", "tone $n does not take its text from $fg");
        T::ok($at($c, $sel) > $dark, "tone $n comes before the dark .gk-avatar rule and loses to it");
        foreach ([false, true] as $isDark) {
            $a = $token("gk-$fg", $isDark); $b = $token("gk-$bg", $isDark);
            // The dark block restates only what changes; a role it leaves out
            // is the light one, which is not the ground in dark mode — so a pair
            // is measured where both are written. ci/farben.js sees the rest.
            if ($a === '' || $b === '') continue;
            $measured++;
            T::ok($contrast($a, $b) >= 4.5, sprintf('tone %d reads %.2f:1 in %s mode', $n, $contrast($a, $b), $isDark ? 'dark' : 'light'));
        }
    }
    T::ok($measured >= 8, "only $measured tone pairs could be read from the stylesheet — the token pattern broke");
},

'status dots speak in roles, and the pulse stands still for reduced motion' => function () use ($css, $rule): void {
    $c = $css();
    T::contains($rule($c, '.gk-dot'), 'background: var(--gk-dot)', 'the dot does not take its tone');
    foreach (['success' => 'success', 'warning' => 'warning', 'danger' => 'error', 'primary' => 'primary', 'muted' => 'outline'] as $tone => $role) {
        T::ok((bool) preg_match('/\.gk-dot-' . $tone . '\s*\{\s*--gk-dot: var\(--gk-' . $role . '\);/', $c), "the $tone dot is not the $role role");
    }
    T::contains($rule($c, '.gk-dot-outline'), 'background: transparent', 'the outline dot is filled — then only colour tells it apart');
    T::ok((bool) preg_match('/@media \(prefers-reduced-motion: reduce\) \{\s*\.gk-dot-pulse \{\s*animation: none;/', $c),
        'the pulse keeps moving for someone who asked for less motion');
},

'a toggle with a hint is a settings row: a switch, named by its title, described by its sentence' => function () use ($render, $css, $rule): void {
    Lang::set('en');
    [$html, $w] = $render(static fn () => (new Form('f'))
        ->field('login', 'Sign-in allowed', 'toggle', ['hint' => 'Takes effect at once.', 'value' => 1, 'danger' => true])
        ->render());
    T::eq($w, [], 'a settings row raised a warning');
    T::contains($html, '<div class="gk-setting gk-setting-danger">', 'no settings row, or danger is lost');
    T::contains($html, '<label class="gk-setting-title" id="login-label" for="login">Sign-in allowed</label>', 'the title is not the label of the switch');
    T::contains($html, '<span class="gk-setting-hint" id="login-hint">Takes effect at once.</span>', 'the sentence is missing');
    T::ok((bool) preg_match('/<input type="checkbox" role="switch" name="login" id="login" value="1" aria-describedby="login-hint login-error" checked>/', $html),
        'the input is not a checked switch described by its sentence and its error slot');
    T::notContains($html, 'gk-label-text', 'a label above the row says the title twice');
    T::contains($html, 'id="login-error"', 'the error slot is gone');

    // Without a hint nothing changes.
    [$plain] = $render(static fn () => (new Form('f'))->field('login', 'Sign-in', 'toggle')->render());
    T::notContains($plain, 'gk-setting', 'a toggle without a hint became a settings row');
    T::notContains($plain, 'role="switch"', 'the plain toggle changed its role');

    $c = $css();
    T::contains($rule($c, '.gk-setting'), 'min-height: var(--gk-target-min)', 'the row is under the pointer target');
    // The switch shows its focus — it showed it nowhere before.
    T::contains($rule($c, '.gk-toggle input:focus-visible + .gk-toggle-slider'), 'outline: 2px solid var(--gk-primary)', 'a focused switch shows nothing');
},

'a choice is a group of answer tiles with a letter each' => function () use ($render, $css): void {
    Lang::set('en');
    [$html, $w] = $render(static fn () => (new Form('f'))
        ->field('next', 'What now?', 'choice', ['required' => true, 'value' => 'later', 'options' => [
            'fix'   => 'Fix it now',
            'later' => ['title' => 'Later', 'hint' => 'Next week.'],
            'drop'  => ['title' => 'Drop it', 'mark' => 'X'],
        ]])->render());
    T::eq($w, [], 'a choice raised a warning');
    T::contains($html, 'role="radiogroup" aria-labelledby="next-label"', 'the group is not named by its label');
    T::eq(substr_count($html, '<label class="gk-choice">'), 3, 'not one tile per option');
    T::eq(substr_count($html, ' required '), 3, 'a required choice is not enforced by the browser');
    foreach (['next-c0-m">A<', 'next-c1-m">B<', 'next-c2-m">X<'] as $mark) {
        T::contains($html, $mark, 'the marks are not A, B and the given one');
    }
    T::contains($html, 'value="later" required aria-labelledby="next-c1-m next-c1-t" aria-describedby="next-c1-h" checked',
        'the chosen tile is not named by mark and title, described by its hint, and checked');

    // Several answers: checkboxes, posted as name[]; a browser cannot require one of them.
    [$multi, $warn] = $render(static fn () => (new Form('f'))
        ->field('tags', 'Tags', 'choice', ['multiple' => true, 'required' => true, 'value' => 'b', 'options' => ['a' => 'A', 'b' => 'B']])
        ->render());
    T::contains($multi, 'role="group"', 'several answers are announced as one');
    T::contains($multi, '<input type="checkbox" name="tags[]" value="b"', 'the checkboxes do not post as an array');
    T::notContains($multi, 'gk-required', 'a star is drawn that nothing enforces');
    T::ok((bool) preg_grep("/'multiple'/", $warn), 'the unenforceable requirement is dropped silently');

    // The tile carries state and focus — only where :has() can see the input;
    // elsewhere the native control stays visible.
    $c = $css();
    T::ok((bool) preg_match('/@supports selector\(:has\(\*\)\) \{\s*\.gk-choice input \{[^}]*opacity: 0;/', $c),
        'the native input is hidden where nothing else could show the state');
    foreach (['.gk-choice:has(input:checked)', '.gk-choice:has(input:focus-visible)'] as $sel) {
        T::contains($c, $sel, "the tile does not show $sel");
    }
},

'compact figures: StatCards->compact() writes tiles, tones only what needs acting on' => function () use ($render, $css, $rule, $token, $contrast): void {
    Lang::set('en');
    [$html, $w] = $render(static fn () => (new StatCards('s'))->compact()
        ->card('failing', 3, ['color' => 'danger'])
        ->card('late', 2, ['color' => 'orange'])
        ->card('open <b>', 12, ['sub' => 'for 3 days', 'href' => '/x?a=1&b=2', 'color' => 'primary', 'icon' => 'x', 'trend' => '+1'])
        ->card('sum', 1234.5, ['format' => 'currency'])
        ->render());
    T::eq($w, [], 'compact tiles raised a warning');
    T::contains($html, '<div class="gk-stat-tiles" data-gk-stats="s">', 'the container lost its replace hook');
    T::contains($html, '<div class="gk-stat-tile gk-stat-tile-danger"><span class="gk-stat-tile-value">3</span><span class="gk-stat-tile-label">failing</span></div>',
        'a danger tile is not value over word');
    T::contains($html, 'gk-stat-tile gk-stat-tile-warning', "'orange' is not the warning tone");
    T::contains($html, '<a href="/x?a=1&amp;b=2" class="gk-stat-tile"><span class="gk-stat-tile-value">12</span><span class="gk-stat-tile-label">open &lt;b&gt;</span><span class="gk-stat-tile-sub">for 3 days</span></a>',
        'a linked tile: wrong shape, a neutral colour toned it, or something is unescaped');
    T::contains($html, '€1,234.50', 'the tile does not format like the card');
    T::notContains($html, 'gk-stat-icon', 'a tile shows the card\'s icon');
    T::notContains($html, 'gk-stat-trend', 'a tile shows the card\'s trend');
    // The cards are unchanged.
    [$cards] = $render(static fn () => (new StatCards('s'))->card('A', 1)->render());
    T::contains($cards, 'gk-stat-card', 'the full cards are gone');

    $c = $css();
    T::contains($rule($c, '.gk-stat-tile'), 'min-height: var(--gk-target-min)', 'a tile is under the pointer target');
    // The figures of a row on one line: centred, a tile without a third line put
    // its figure 8px below the neighbour's.
    T::contains($rule($c, '.gk-stat-tile'), 'justify-content: flex-start', 'the figures of a row do not stand on one line');
    // A <dl>: the word is the <dt> and comes first, the figure goes on top by
    // order, and a <dd> brings no indent.
    T::contains($rule($c, '.gk-stat-tile-value'), 'order: -1', 'in a <dl> the figure ends up under its word');
    T::contains($rule($c, '.gk-stat-tile > *'), 'margin: 0', 'a <dd> in a tile keeps its 40px indent');
    foreach (['danger' => ['danger-text', 'error-container', 'on-error-container'], 'warning' => ['warning-text', 'warning-container', 'on-warning-container']] as $tone => [$value, $bg, $label]) {
        T::contains($rule($c, ".gk-stat-tile-$tone"), "background: var(--gk-$bg)", "the $tone tile is not its container role");
        foreach ([false, true] as $isDark) {
            foreach ([$value, $label] as $fg) {
                [$f, $g] = [$token("gk-$fg", $isDark), $token("gk-$bg", $isDark)];
                T::ok($f !== '' && $g !== '', "--gk-$fg or --gk-$bg has no " . ($isDark ? 'dark' : 'light') . ' value to measure');
                $r = $f !== '' && $g !== '' ? $contrast($f, $g) : 0.0;
                T::ok($r >= 4.5, sprintf('%s on the %s tile reads %.2f:1 in %s mode', $fg, $tone, $r, $isDark ? 'dark' : 'light'));
            }
        }
    }
},

'a line under the sheet title and beside the header title' => function () use ($css, $rule): void {
    $c = $css();
    $grid = $rule($c, '.gk-sheet-header:has(> .gk-sheet-meta)');
    T::contains($grid, 'display: grid', 'the line under the sheet title pushes the close button');
    T::contains($rule($c, '.gk-sheet-header:has(> .gk-sheet-meta) > .gk-sheet-close'), 'grid-row: 1 / span 2', 'the close button leaves its corner');
    T::contains($rule($c, '.gk-sheet-meta a'), 'text-decoration: underline dotted', 'a link in the line looks like text');

    T::contains($rule($c, '.gk-header-title:has(> .gk-header-meta)'), 'align-items: baseline', 'the header line is not on the title\'s baseline');
    $meta = $rule($c, '.gk-header-meta');
    T::contains($meta, 'text-overflow: ellipsis', 'the header line pushes the user menu instead of giving way');
    T::contains($rule($c, '.gk-header-title:has(> .gk-header-meta) > h1'), 'flex: 0 0 auto', 'the title gives way before the line beside it');
    T::ok((bool) preg_match('/@media \(max-width: 768px\) \{\s*\.gk-header-meta \{\s*display: none;/', $c), 'the header line stays on a phone');
},

'five series colours, stepped for each mode, and a class for each use' => function () use ($css, $token, $oklab, $contrast): void {
    $c = $css();
    $light = $dark = [];
    for ($n = 1; $n <= 5; $n++) {
        $light[$n] = $token("gk-series-$n", false);
        $dark[$n]  = $token("gk-series-$n", true);
        T::ok($light[$n] !== '' && $dark[$n] !== '', "series $n lacks a light or a dark value");
        T::ok($light[$n] !== $dark[$n], "series $n is not stepped for dark mode");
        foreach (["swatch-$n" => 'background', "series-fill-$n" => 'fill', "series-stroke-$n" => 'stroke'] as $cls => $prop) {
            T::ok((bool) preg_match('/\.gk-' . $cls . '\s*\{\s*' . $prop . ': var\(--gk-series-' . $n . '\);/', $c), ".gk-$cls does not paint series $n");
        }
    }
    // Series never follow the theme.
    T::notContains((string) file_get_contents(__DIR__ . '/../css/themes.css'), '--gk-series', 'a theme moves the series colours');
    // Dark: every slot a readable mark on both dark grounds.
    foreach (['#0d1117', '#1e293b'] as $ground) {
        foreach ($dark as $n => $hex) {
            T::ok($contrast($hex, $ground) >= 3.0, sprintf('dark series %d is %.2f:1 on %s', $n, $contrast($hex, $ground), $ground));
        }
    }
    // Light: the first two carry alone; 3 to 5 need their values as text (documented).
    foreach ([1, 2] as $n) {
        T::ok($contrast($light[$n], '#ffffff') >= 3.0, "light series $n is under 3:1 on white");
    }
    T::contains((string) file_get_contents(__DIR__ . '/../GRIDKIT_SKILL.md'), 'Slots 3 to 5 sit under 3:1', 'the relief rule for slots 3 to 5 is not documented');
    // Neighbours are told apart by normal vision (OKLab distance x100 >= 15).
    foreach (['light' => $light, 'dark' => $dark] as $mode => $set) {
        for ($n = 1; $n < 5; $n++) {
            [$a, $b] = [$oklab($set[$n]), $oklab($set[$n + 1])];
            $d = sqrt(($a[0] - $b[0]) ** 2 + ($a[1] - $b[1]) ** 2 + ($a[2] - $b[2]) ** 2);
            T::ok($d >= 15, sprintf('%s series %d and %d are only %.1f apart', $mode, $n, $n + 1, $d));
        }
    }
},

'a finger-sized button, actions in a message, a phone-only element' => function () use ($css, $rule): void {
    $c = $css();
    $touch = $rule($c, '.gk-btn.gk-btn-touch');
    T::contains($touch, 'min-height: var(--gk-target-min)', 'the touch button is not the target height');
    T::contains($touch, 'min-width: var(--gk-target-min)', 'an icon-only touch button is narrower than the target');
    T::ok(!str_contains($touch, 'font-size'), 'the touch button changes the type — that is gk-btn-lg');
    T::contains($rule($c, '.gk-message-actions'), 'margin-left: auto', 'the actions do not sit on the right');
    T::contains($rule($c, '.gk-message:has(> .gk-message-actions)'), 'flex-wrap: wrap', 'a long message squeezes its actions');
    T::ok((bool) preg_match('/@media \(min-width: 769px\) \{\s*\.gk-show-mobile \{\s*display: none !important;/', $c),
        '.gk-show-mobile is not the mirror of .gk-hide-mobile');
    foreach (['.gk-field-static', '.gk-field-static-label', '.gk-field-static-value'] as $sel) {
        T::ok($rule($c, $sel) !== '', "$sel has no rule");
    }
},

// ── Documentation ─────────────────────────────────────────────────────────

'every new block is in the skill, its class table, the specification and the demo' => function () use ($doc, $root): void {
    $md   = $doc();
    $spec = (string) file_get_contents($root . '/SPEC.md');
    $demo = (string) file_get_contents($root . '/demo/index.php');
    preg_match('/^## CSS Classes Reference\n(.*?)(?=^### |^## )/ms', $md, $ref);
    $table = $ref[1] ?? '';
    T::ok(strlen($table) > 1000, 'the CSS Classes Reference table was not found');
    $blocks = ['gk-main', 'gk-header-meta', 'gk-table-list', 'gk-col-p2', 'gk-cell-who', 'gk-cell-sub-wrap', 'gk-avatar-tone-1',
               'gk-dot', 'gk-dot-outline', 'gk-dot-pulse', 'gk-setting', 'gk-setting-danger', 'gk-field-static', 'gk-stat-tiles',
               'gk-stat-tile-danger', 'gk-sheet-meta', 'gk-swatch', 'gk-series-fill-1', 'gk-btn-touch', 'gk-message-actions',
               'gk-show-mobile', 'gk-choice', 'gk-choice-mark'];
    foreach ($blocks as $b) {
        T::contains($table, '`' . $b . '`', "the CSS Classes Reference has no row for .$b");
        T::contains($spec, $b, "SPEC.md does not specify .$b");
    }
    foreach (['gk-table-list', 'gk-col-p', 'gk-setting', 'gk-stat-tile', 'gk-dot', 'gk-avatar-tone', 'gk-swatch', 'gk-message-actions', 'gk-btn-touch', 'gk-field-static', 'gk-choice'] as $shown) {
        // The demo renders them — some through a component, so look at what it would print.
        T::ok(str_contains($demo, $shown) || str_contains($demo, "size('list')") && $shown === 'gk-table-list'
            || str_contains($demo, "->compact()") && $shown === 'gk-stat-tile'
            || str_contains($demo, "'hint' =>") && $shown === 'gk-setting'
            || str_contains($demo, "'choice'") && $shown === 'gk-choice'
            || str_contains($demo, "'priority' =>") && $shown === 'gk-col-p',
            "the demo shows nothing of .$shown");
    }
    foreach (["->size('list')", "'priority' => 2", '->compact()', "'hint' =>", "'choice'", 'data-gk-sidebar-action'] as $api) {
        T::contains($md, $api, "the skill never shows $api");
    }
},

];

})();
