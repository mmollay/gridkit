<?php
/**
 * Colour contrast.
 *
 * "Themes, dark mode" is in the README's opening sentence, and nothing had ever
 * measured either. A probe page with every component, driven through six themes
 * in both modes, failed in twelve of twelve combinations. The worst was not
 * subtle: the active pagination button rendered white on white in dark mode,
 * at 1.8:1.
 *
 * CSS cannot be executed here, so these pin the values the measurement settled
 * on. The ratios in the comments were measured in a browser, not estimated.
 */

declare(strict_types=1);

function css(): string
{
    static $c = null;
    return $c ??= (string) file_get_contents(__DIR__ . '/../css/gridkit.css');
}

function themesCss(): string
{
    static $c = null;
    return $c ??= (string) file_get_contents(__DIR__ . '/../css/themes.css');
}

/**
 * The actual WCAG maths, not a pinned string.
 *
 * Every other test in this file asserts that a particular value is present,
 * which catches a change but cannot tell a good change from a bad one. These
 * two compute the ratio, so a future edit to any of these colours is judged on
 * whether it reads, not on whether it matches what was written down.
 */
function gkLuminance(string $hex): float
{
    $hex = ltrim($hex, '#');
    $chan = static function (int $v): float {
        $c = $v / 255;
        return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
    };
    return 0.2126 * $chan((int) hexdec(substr($hex, 0, 2)))
         + 0.7152 * $chan((int) hexdec(substr($hex, 2, 2)))
         + 0.0722 * $chan((int) hexdec(substr($hex, 4, 2)));
}

function gkContrast(string $a, string $b): float
{
    $la = gkLuminance($a);
    $lb = gkLuminance($b);
    return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
}

/** Reads a --gk-* custom property out of the core stylesheet. */
function gkToken(string $name): string
{
    preg_match('/--' . preg_quote($name, '/') . ':\s*(#[0-9a-fA-F]{6})/', css(), $m);
    return $m[1] ?? '';
}

/** The same, out of the dark root block — empty when that block does not set it. */
function gkDarkToken(string $name): string
{
    static $block = null;
    $block ??= preg_match('/^\[data-gk-mode="dark"\],\s*\n\.gk-dark \{(.*?)^\}/ms', css(), $m) ? $m[1] : '';
    preg_match('/--' . preg_quote($name, '/') . ':\s*(#[0-9a-fA-F]{6})/', $block, $t);
    return $t[1] ?? '';
}

/**
 * A selector list split at its top-level commas, whitespace collapsed the way
 * the stylesheet's formatter breaks long :not() chains.
 *
 * @return list<string>
 */
function gkSelectorList(string $list): array
{
    $out = [];
    $depth = 0;
    $cur = '';
    foreach (str_split($list) as $ch) {
        $depth += $ch === '(' ? 1 : ($ch === ')' ? -1 : 0);
        if ($ch === ',' && $depth === 0) {
            $out[] = $cur;
            $cur = '';
            continue;
        }
        $cur .= $ch;
    }
    $out[] = $cur;
    return array_map(static fn(string $s): string =>
        (string) preg_replace(['/\s+/', '/\(\s+/', '/\s+\)/'], [' ', '(', ')'], trim($s)), $out);
}

/**
 * Every rule of the core stylesheet, comments stripped: its selectors, its
 * declarations and where it stands.
 *
 * @return list<array{selectors: list<string>, decl: array<string,string>, pos: int}>
 */
function gkRules(): array
{
    static $rules = null;
    if ($rules !== null) {
        return $rules;
    }
    $plain = (string) preg_replace('#/\*.*?\*/#s', '', css());
    preg_match_all('/([^{}]+)\{([^{}]*)\}/', $plain, $m, PREG_OFFSET_CAPTURE);
    $rules = [];
    foreach ($m[1] as $i => [$selectors, $pos]) {
        $decl = [];
        foreach (explode(';', $m[2][$i][0]) as $d) {
            if (str_contains($d, ':')) {
                [$p, $v] = explode(':', $d, 2);
                $decl[strtolower(trim($p))] = trim($v);
            }
        }
        $rules[] = ['selectors' => gkSelectorList($selectors), 'decl' => $decl, 'pos' => $pos];
    }
    return $rules;
}

/**
 * Classes, attributes and pseudo-classes; :not() counts its argument, not itself.
 * Enough for chip and button selectors, which carry no ids and no elements.
 */
function gkSpecificity(string $selector): int
{
    return (int) preg_match_all('/\.[\w-]+|\[[^\]]+\]|(?<!:):(?!:|not\()[\w-]+/', $selector);
}

/**
 * The value of $props on an element that every selector in $matching matches: the
 * highest specificity wins, then the later rule — the cascade, for the handful of
 * rules a test names. A browser proves the result; this finds the rule that decides.
 * A shorthand and its longhand (background, background-color) are passed together:
 * they decide as one property, and the later declaration of a rule wins.
 *
 * @param list<string> $matching
 */
function gkWinner(array $matching, string ...$props): ?string
{
    $best = null;
    $bestKey = [-1, -1];
    foreach (gkRules() as $rule) {
        $set = array_intersect_key($rule['decl'], array_flip($props));
        if ($set === []) {
            continue;
        }
        foreach ($rule['selectors'] as $s) {
            $key = [gkSpecificity($s), $rule['pos']];
            if (in_array($s, $matching, true) && $key > $bestKey) {
                $bestKey = $key;
                $best = end($set);
            }
        }
    }
    return $best;
}

/**
 * Does $selector reach a button that carries exactly $classes — under the pointer
 * or at rest — inside the dark-mode ancestor $mode ('' in light mode)? Understood
 * is what button rules are written with: classes, :hover and :not(.class), after at
 * most that one ancestor. A selector naming anything else (another ancestor, focus,
 * a disabled state) does not reach the button asked about. Picking rules this way,
 * and not by the value they set, is what finds a second rule with another colour.
 *
 * @param list<string> $classes
 */
function gkReaches(string $selector, array $classes, bool $hover, string $mode = ''): bool
{
    if ($mode !== '' && str_starts_with($selector, "$mode ")) {
        $selector = substr($selector, strlen($mode) + 1);
    }
    if (!preg_match('/^(?:\.[\w-]+|:hover|:not\(\.[\w-]+\))+$/', $selector)) {
        return false;
    }
    preg_match_all('/:not\(\.([\w-]+)\)|\.([\w-]+)|:hover/', $selector, $parts, PREG_SET_ORDER);
    foreach ($parts as $p) {
        $holds = match (true) {
            ($p[1] ?? '') !== '' => !in_array($p[1], $classes, true),
            ($p[2] ?? '') !== '' => in_array($p[2], $classes, true),
            default => $hover,
        };
        if (!$holds) {
            return false;
        }
    }
    return true;
}

/**
 * Every selector of the stylesheet that reaches that button — the list gkWinner() takes.
 * With $child (".gk-chip-count"): every selector that reaches that child inside it —
 * the child alone, or a selector reaching the element followed by " $child".
 *
 * @param list<string> $classes
 * @return list<string>
 */
function gkReaching(array $classes, bool $hover, string $mode = '', string $child = ''): array
{
    $out = [];
    foreach (gkRules() as $rule) {
        foreach ($rule['selectors'] as $s) {
            $reaches = $child === ''
                ? gkReaches($s, $classes, $hover, $mode)
                : $s === $child || ($mode !== '' && $s === "$mode $child")
                  || (str_ends_with($s, " $child")
                      && gkReaches(substr($s, 0, -strlen(" $child")), $classes, $hover, $mode));
            if ($reaches) {
                $out[] = $s;
            }
        }
    }
    return array_values(array_unique($out));
}

/**
 * What a chip with $classes shows in $mode, followed through the cascade of the bare
 * stylesheet: [ground, label, ground of its count, text of its count] as hex.
 *
 * @param list<string> $classes
 * @return array{0: string, 1: string, 2: string, 3: string}
 */
function gkChipLook(array $classes, bool $hover, string $mode = ''): array
{
    $dark = $mode !== '';
    $chip = gkReaching($classes, $hover, $mode);
    $count = gkReaching($classes, $hover, $mode, '.gk-chip-count');
    $ground = gkColour(gkWinner($chip, 'background', 'background-color'), $dark);
    $text = gkColour(gkWinner($chip, 'color'), $dark);
    $tint = gkOver(gkWinner($count, 'background', 'background-color'), $ground);
    return [$ground, $text, $tint, gkColour(gkWinner($count, 'color'), $dark) ?: $text];
}

/** A colour value as hex: var(--gk-x[, fallback]) through the token of the mode, or a literal. */
function gkColour(?string $value, bool $dark): string
{
    if ($value !== null && preg_match('/^var\(--(gk-[a-z0-9-]+)/', $value, $m)) {
        return ($dark ? gkDarkToken($m[1]) : '') ?: gkToken($m[1]);
    }
    return $value !== null && preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? $value : '';
}

/** An rgba() layer painted over a hex ground, the way a browser composites it. */
function gkOver(?string $layer, string $ground): string
{
    if ($layer === null || $ground === ''
        || !preg_match('/^rgba\((\d+),\s*(\d+),\s*(\d+),\s*([\d.]+)\)$/', $layer, $m)) {
        return '';
    }
    $g = sscanf(ltrim($ground, '#'), '%2x%2x%2x');
    $a = (float) $m[4];
    return vsprintf('#%02x%02x%02x', array_map(
        static fn(int $i): int => (int) round((int) $m[$i + 1] * $a + $g[$i] * (1 - $a)), [0, 1, 2]));
}

/** @return array<string,callable> */
return [

'the accent lightness clears AA on every theme' => function (): void {
    // 0.60 ran from 2.77:1 (ocean) to 7.58:1 (slate). 0.55 pulled that to
    // 4.35–5.27, which still left forest under the 4.5:1 body text needs.
    // 0.53 measures 4.69 (forest) to 5.73 (rose).
    T::ok((bool) preg_match('/--gk-l-primary:\s*0\.53\b/', themesCss()),
        'one number decides whether white carries on all six accents');
},

'the accent has a text-safe variant' => function (): void {
    // --gk-primary is pinned so white carries *on* it. The same colour as text
    // on white measured 3.97:1 in forest — the inverse case needs its own token,
    // exactly as --gk-warning-text and --gk-success-text already did.
    T::contains(css(), '--gk-primary-text:', 'the token exists');
    T::ok((bool) preg_match('/\.gk-btn-text\.gk-btn-primary \{[^}]*color:\s*var\(--gk-primary-text\)/s', css()),
        'text buttons use it');
    T::ok((bool) preg_match('/\.gk-btn-outlined\.gk-btn-primary \{[^}]*color:\s*var\(--gk-primary-text\)/s', css()),
        'outlined buttons use it');
},

'filled semantic buttons have their own darker fill' => function (): void {
    // White on the role colours themselves: 2.54:1 success, 2.15:1 warning,
    // 3.67:1 danger. The role colours stay as they are — they are right for
    // pills, borders and icons — and the buttons take a darker fill.
    foreach (['success' => '#047857', 'warning' => '#b45309', 'danger' => '#dc2626'] as $role => $value) {
        T::ok((bool) preg_match("/--gk-{$role}-fill:\s*" . preg_quote($value, '/') . '/', css()),
            "--gk-{$role}-fill is defined as {$value}");
    }
    T::ok(substr_count(css(), '-fill)') >= 12,
        'and used for background and border in both rule sets');

    // The role colours themselves must not have been moved.
    T::contains(css(), '--gk-success: #10b981;', 'the success role is unchanged');
    T::contains(css(), '--gk-warning: #f59e0b;', 'the warning role is unchanged');
},

'a filled primary, success, warning or danger button names its hover colour once' => function (): void {
    // Two rules painted the hover of a filled success, warning or danger button:
    // .gk-btn-filled.gk-btn-X:hover and the default-variant rule, which matches a
    // button carrying both .gk-btn-filled and .gk-btn and wins on specificity. Both
    // named the same colour, so changing the filled one changed nothing on screen —
    // a counter-probe of the SSI Panel stayed green (round 18). The filled rule is
    // not redundant, though (round 22): it alone reaches a button with gk-btn-filled
    // and WITHOUT gk-btn, and it carries the shadow. So background and border stand
    // in ONE selector list, and the shadow stays where it was.
    //
    // Round 29 review: the rules were picked by the VALUE they set. A second hover
    // rule with another, readable colour went uncounted — a counter-probe put one
    // back for success and the suite stayed green while the shared list no longer
    // reached a single Button::render button. Rules are now picked by the buttons
    // they REACH, in every spelling of a filled button, light and both dark
    // spellings, and the cascade over all of them has to end on the hover token.
    // Primary had the same two rules (same colour) and joined the list then.
    // Neutral is left out on purpose: its two rules name DIFFERENT colours, and
    // which one is right is a decision still open.
    $modes = ['light' => '', 'dark' => '[data-gk-mode="dark"]', 'dark (.gk-dark)' => '.gk-dark'];
    $tokens = ['primary' => 'var(--gk-primary-hover)', 'success' => 'var(--gk-success-fill-hover)',
               'warning' => 'var(--gk-warning-fill-hover)', 'danger' => 'var(--gk-danger-fill-hover)'];
    foreach ($tokens as $role => $token) {
        $spellings = [
            'gk-btn gk-btn-filled' => ['gk-btn', 'gk-btn-filled', "gk-btn-$role"],
            'gk-btn (default variant)' => ['gk-btn', "gk-btn-$role"],
            'gk-btn-filled without gk-btn' => ['gk-btn-filled', "gk-btn-$role"],
        ];
        $painting = [];
        foreach (gkRules() as $i => $r) {
            if (array_intersect_key($r['decl'], array_flip(['background', 'background-color', 'border', 'border-color'])) === []) {
                continue;
            }
            foreach ($r['selectors'] as $s) {
                foreach ($spellings as $classes) {
                    foreach ($modes as $p) {
                        if (gkReaches($s, $classes, true, $p) && !gkReaches($s, $classes, false, $p)) {
                            $painting[$i] = $r;
                        }
                    }
                }
            }
        }
        T::eq(count($painting), 1,
            "$role: exactly one rule paints a filled button once the pointer is on it — any spelling, light or dark");
        $rule = array_values($painting)[0] ?? ['selectors' => [], 'decl' => []];
        T::ok(in_array(".gk-btn-filled.gk-btn-$role:hover", $rule['selectors'], true),
            "$role: that rule covers a filled button, with or without .gk-btn");
        T::ok(in_array(".gk-btn.gk-btn-$role:not(.gk-btn-outlined):not(.gk-btn-text):not(.gk-btn-tonal):hover",
            $rule['selectors'], true), "$role: and the default variant (.gk-btn without a variant class)");
        T::eq($rule['decl']['background'] ?? '', $token, "$role: its background is the hover fill");
        T::eq($rule['decl']['border-color'] ?? '', $token, "$role: its border is the hover fill");
        foreach ($spellings as $name => $classes) {
            foreach ($modes as $mode => $p) {
                $reaching = gkReaching($classes, true, $p);
                T::eq(gkWinner($reaching, 'background', 'background-color'), $token,
                    "$role, $name, $mode: under the pointer the cascade ends on the hover fill");
                T::eq(gkWinner($reaching, 'border-color', 'border'), $token,
                    "$role, $name, $mode: and the border on the same colour");
            }
        }
    }
    foreach (['primary' => '--gk-primary', 'success' => '--gk-success', 'danger' => '--gk-error'] as $role => $tone) {
        foreach ([['gk-btn', 'gk-btn-filled', "gk-btn-$role"], ['gk-btn-filled', "gk-btn-$role"]] as $classes) {
            T::ok(str_contains((string) gkWinner(gkReaching($classes, true), 'box-shadow'), "var($tone) 30%"),
                "$role: the filled hover keeps its shadow (" . implode(' ', $classes) . ')');
        }
    }
},

'the count in an active chip reads on the tint it sits on' => function (): void {
    // .gk-chip-count lays a tint over its chip. On an active chip that was 20 % WHITE
    // under white text, so the tint took away what the accent had: measured in a
    // browser (1.93.2) at 3.19–3.90:1 light and 3.68–4.67:1 dark, in every theme, on
    // every page with FilterChips. The tint has to move AWAY from the text — darker
    // under light text, lighter under dark text — so the count never reads weaker
    // than the label beside it.
    //
    // Followed through the rules of the bare stylesheet (literal tokens, no theme).
    // ci/farben.js measures the same counts in all six themes and both dark spellings.
    // Round 30: the rules are picked by the chips they REACH (gkChipLook), no longer
    // from a hand-written list — that list knew only the selectors of its day, and a
    // dark rule for a colour role would have gone unseen.
    $modes = ['light' => '', 'dark' => '[data-gk-mode="dark"]', 'dark (.gk-dark)' => '.gk-dark'];
    $colours = ['', 'primary', 'blue', 'danger', 'red', 'success', 'green', 'warning', 'orange', 'neutral'];
    foreach ($modes as $mode => $p) {
        foreach ($colours as $c) {
            foreach ([false, true] as $hover) {
                $classes = $c === '' ? ['gk-chip', 'gk-chip-active'] : ['gk-chip', "gk-chip-$c", 'gk-chip-active'];
                $name = trim("$mode, " . ($c ?: 'plain') . ($hover ? ', under the pointer' : ''));
                [$ground, $text, $tint, $countText] = gkChipLook($classes, $hover, $p);
                T::ok($ground !== '' && $text !== '' && $tint !== '', "$name: chip, label and tint resolve");
                if ($ground === '' || $text === '' || $tint === '') {
                    continue;
                }
                $label = gkContrast($text, $ground);
                $number = gkContrast($countText, $tint);
                T::ok($number >= 4.5, sprintf('%s: the count reads at %.2f:1 — AA asks 4.5', $name, $number));
                T::ok($number >= $label - 0.005, sprintf(
                    '%s: the tint takes contrast from the count (%.2f:1, the label beside it %.2f:1)', $name, $number, $label));
            }
        }
    }
},

'an active chip keeps its colour role in dark mode' => function (): void {
    // In dark mode "[data-gk-mode="dark"] .gk-chip.gk-chip-active" (0,3,0) outranked
    // every colour role (".gk-chip-danger.gk-chip-active", 0,2,0): the invoice list's
    // Mahnung, Bezahlt, Warten and Archiv all wore the same tonal indigo (found in
    // round 29, measured 1.93.3: #4345b0 on every active chip, in every theme). A role
    // is the same rule in both modes, so in dark it wears what it wears in light;
    // primary and blue stay what they are in light — the chip without a role — which
    // is the tonal container in dark, unchanged.
    //
    // Label AND count at 4.5:1 or better, at rest and under the pointer, in both dark
    // spellings; the four roles and the chip without one never share a ground.
    // ci/farben.js measures the same in all six themes.
    $modes = ['light' => '', 'dark' => '[data-gk-mode="dark"]', 'dark (.gk-dark)' => '.gk-dark'];
    $roles = ['danger', 'red', 'success', 'green', 'warning', 'orange', 'neutral'];
    foreach ($modes as $mode => $p) {
        foreach ([false, true] as $hover) {
            $at = $mode . ($hover ? ', under the pointer' : '');
            $grounds = [];
            foreach (['', 'primary', 'blue', ...$roles] as $c) {
                $classes = $c === '' ? ['gk-chip', 'gk-chip-active'] : ['gk-chip', "gk-chip-$c", 'gk-chip-active'];
                $name = "$at, " . ($c ?: 'no role');
                [$ground, $text, $tint, $countText] = $grounds[$c] = gkChipLook($classes, $hover, $p);
                $bg = gkWinner(gkReaching($classes, $hover, $p), 'background', 'background-color');
                $border = gkWinner(gkReaching($classes, $hover, $p), 'border-color', 'border');
                if (!in_array($c, $roles, true)) {
                    if ($p !== '') {
                        T::eq($bg, 'var(--gk-primary-container)', "$name: the tonal container, as before");
                    }
                    continue;
                }
                $light = gkReaching($classes, $hover);
                T::eq($bg, gkWinner($light, 'background', 'background-color'),
                    "$name: the chip wears its role colour, the one it wears in light");
                T::eq($border, gkWinner($light, 'border-color', 'border'), "$name: and the border of its role");
                $label = $ground !== '' && $text !== '' ? gkContrast($text, $ground) : 0.0;
                T::ok($label >= 4.5, sprintf('%s: the label reads at %.2f:1 — AA asks 4.5', $name, $label));
                $number = $tint !== '' && $countText !== '' ? gkContrast($countText, $tint) : 0.0;
                T::ok($number >= 4.5, sprintf('%s: the count reads at %.2f:1 — AA asks 4.5', $name, $number));
                T::ok($number >= $label - 0.005, sprintf(
                    '%s: the tint takes contrast from the count (%.2f:1, the label beside it %.2f:1)', $name, $number, $label));
            }
            $looks = array_map(static fn(string $c): string => $grounds[$c][0], ['', 'danger', 'success', 'warning', 'neutral']);
            T::eq(count(array_unique($looks)), 5,
                "$at: no role, danger, success, warning and neutral each have a ground of their own (" . implode(' ', $looks) . ')');
        }
    }
},

'nothing paints a literal white on the accent' => function (): void {
    // In dark mode --gk-primary is the *light* end of the scale, so a literal
    // #fff on it is white on white. The active pagination button measured
    // 1.78–1.97:1 across every theme.
    T::ok(!preg_match('/background:\s*var\(--gk-primary\)[^}]*color:\s*#(fff|ffffff)\b/s', css()),
        'a primary background is paired with --gk-on-primary, not a literal');
    T::contains(css(), '.gk-pg-active { background: var(--gk-primary); border-color: var(--gk-primary); color: var(--gk-on-primary)',
        'the active pager takes the role colour');
},

'the sidebar\'s muted text is readable in both modes' => function (): void {
    // Light was #9ca3af (2.27:1), dark was rgba(255,255,255,0.38) (3.40:1) —
    // group labels, the version and the collapse label all sat under half of
    // what they needed.
    T::contains(css(), '--gk-sidebar-text-muted: #5b6472;', 'light: 5.35:1');
    T::contains(css(), '--gk-sidebar-text-muted: rgba(255, 255, 255, 0.55);', 'dark: 5.48:1');
    T::contains(css(), '--gk-sidebar-icon-muted: #5b6472;', 'and the icons beside them');
},

'a border colour is not used as a text colour' => function (): void {
    // --gk-outline is a 1px-line colour: 1.45:1 on white, 1.93:1 on the dark
    // surface. The breadcrumb separator and the select arrow were drawing text
    // and icons with it.
    T::ok(!preg_match('/\.gk-breadcrumb-sep \{[^}]*color:\s*var\(--gk-outline\)/s', css()),
        'the breadcrumb separator');
    T::ok(!preg_match('/\.gk-select-arrow \{[^}]*color:\s*var\(--gk-outline\)/s', css()),
        'the select arrow');
},

'the client does not throw away the theme the server set' => function (): void {
    $js = (string) file_get_contents(__DIR__ . '/../js/gridkit.js');

    // restore() wrote `theme || ""` and `mode || ""`, so an empty localStorage —
    // every first visit, every private window — wiped Theme::set(). A site
    // configured for dark mode in PHP opened in light mode for everyone who had
    // not been there before.
    T::ok(!str_contains($js, 'document.body.dataset.gkTheme = theme || ""'),
        'an absent preference must not blank the attribute');
    T::ok(!str_contains($js, 'document.body.dataset.gkMode = mode || ""'),
        'nor the mode');
    T::ok((bool) preg_match('/restore\(\)\s*\{.*?if \(theme\) this\.set\(theme\);.*?if \(mode\)/s', $js),
        'a stored preference wins; nothing stored leaves the server\'s choice alone');
},


'the semantic roles have text-safe variants that are actually safe' => function (): void {
    // --gk-warning-text was introduced to fix white-on-amber and measured
    // 3.19:1 as text on white — better than the 2.15:1 it replaced, still under
    // what body text needs. --gk-success-text was defined and never used at all,
    // so outlined and text success buttons ran on --gk-success at 2.54:1.
    T::contains(css(), '--gk-warning-text: #b45309', 'warning: 5.02:1 on white');
    T::contains(css(), '--gk-success-text: #047857', 'success: 5.48:1');
    T::ok((bool) preg_match('/--gk-danger-text:\s*#c81e3a/', css()), 'danger: 5.67:1');

    foreach (['success', 'danger'] as $role) {
        T::ok((bool) preg_match(
            '/\.gk-btn-outlined\.gk-btn-' . $role . ' \{[^}]*color:\s*var\(--gk-' . $role . '-text\)/s', css()),
            "outlined $role uses the text-safe value");
        T::ok((bool) preg_match(
            '/\.gk-btn-text\.gk-btn-' . $role . ' \{[^}]*color:\s*var\(--gk-' . $role . '-text\)/s', css()),
            "text $role uses it too");
    }
},

'the dark alias variables are derived, never literals' => function (): void {
    // Two names for one colour: the role (--gk-on-surface-variant) and an older
    // alias (--gk-text-muted). The light block makes the alias BE the role, so the
    // two cannot drift. The dark block wrote literals — and themes.css moves the
    // role underneath them (--gk-on-surface-variant: #94a3b8 there). Measured in a
    // browser across six themes: seven of these eight pointed at a different colour
    // than their role, in every dark theme. Which colour a component got depended on
    // which of the two names its author had typed.
    //
    // The same fault was fixed for --gk-surface-variant and --gk-text-secondary in
    // 1.80.1; these eight were left behind. ci/farben.js measures the resolved values.
    $paare = [
        '--gk-text' => '--gk-on-surface',
        '--gk-text-muted' => '--gk-on-surface-variant',
        '--gk-text-subtle' => '--gk-outline',
        '--gk-border' => '--gk-outline-variant',
    ];
    // The four surface aliases are NOT in this list, deliberately: themes.css gives
    // --gk-surface and --gk-surface-container the same colour in dark mode, so
    // deriving them would make --gk-bg and --gk-bg-muted identical and every muted
    // surface lying on a plain one would lose its edge. Held back until the ladder
    // is decided; the comment in the dark block says so, and ci/farben.js measures
    // and reports them without failing.
    foreach (['--gk-bg', '--gk-bg-muted', '--gk-bg-subtle', '--gk-bg-hover'] as $offen) {
        T::ok(!isset($paare[$offen]), "$offen is knowingly still a literal in dark mode");
    }
    // Only the dark ROOT block — not the many component rules that also carry
    // [data-gk-mode="dark"], and not the light block, which spells these the same way.
    // preg_match_all, not preg_match: with a single match this test would silently
    // check the FIRST block if someone ever adds a second one above it.
    $n = preg_match_all('/^\[data-gk-mode="dark"\],\s*\n\.gk-dark \{(.*?)^\}/ms', css(), $m);
    T::ok($n === 1, "there is exactly one dark root block (found $n)");
    $dunkel = $m[1][0] ?? '';
    // The light block carries the same eight lines. Pinning only the dark side would
    // let the two drift apart again from the other end.
    $hell = preg_match('/^:root,?[^{]*\{(.*?)^\}/ms', css(), $mh) ? $mh[1] : '';
    T::ok($hell !== '', 'the light root block is where it is expected');

    foreach ($paare as $alias => $rolle) {
        $muster = '/' . preg_quote($alias, '/') . ':\s*var\(' . preg_quote($rolle, '/') . '\)/';
        T::ok((bool) preg_match($muster, $dunkel), "$alias follows $rolle in dark mode");
        T::ok((bool) preg_match($muster, $hell), "$alias follows $rolle in light mode");
    }
},

'dark component rules take their TEXT colour from a role, not from a literal' => function (): void {
    // The dark component rules carried GridKit's own palette as literals next to
    // role-based declarations, often in the same rule block: a search field took its
    // background from one palette and its text from the other. Under a theme the
    // literals belong to no theme value at all.
    //
    // An ALLOW list, not a deny list: a deny list of known greys lets #3a4250,
    // #262d38, an !important, an rgb() spelling or a second declaration on the same
    // line slip through — and it missed .gk-avatar, which carried --gk-primary's own
    // value as a literal and stayed indigo under every other theme.
    $anfang = strpos(css(), '/* Dark mode: Component adjustments */');
    T::ok($anfang !== false, 'the dark component section is where it is expected');
    $abschnitt = substr(css(), (int) $anfang);

    // Semantic colours that are deliberately literals: they carry a meaning
    // (message kinds, tonal buttons) and have no role to read.
    $erlaubt = ['#38bdf8', '#6ee7b7', '#fcd34d', '#fda4af',   // message kinds
                '#c7d2fe', '#a7f3d0', '#fde68a', '#fecaca',   // tonal buttons
                '#fef9c3',                                    // search hit on #854d0e (1.80.1)
                '#fff', '#ffffff', 'inherit', 'currentcolor', 'transparent'];

    $verstoesse = [];
    $zeilen = explode("\n", $abschnitt);
    $versatz = substr_count(substr(css(), 0, (int) $anfang), "\n");
    foreach ($zeilen as $i => $zeile) {
        if (!preg_match_all('/(?:^|[;{\s])(?:-webkit-text-fill-color|caret-color|color)\s*:\s*([^;}]+)/i', $zeile, $treffer)) {
            continue;
        }
        foreach ($treffer[1] as $wert) {
            $wert = strtolower(trim(str_ireplace('!important', '', $wert)));
            if ($wert === '' || str_starts_with($wert, 'var(') || str_starts_with($wert, 'color-mix(')
                || in_array($wert, $erlaubt, true)) {
                continue;
            }
            $verstoesse[] = 'Zeile ' . ($versatz + $i + 1) . ': color: ' . $wert;
        }
    }
    T::ok($verstoesse === [],
        'every text colour in the dark component rules reads a role'
        . ($verstoesse ? "\n      " . implode("\n      ", $verstoesse) : ''));
},

'the sticky header keeps the colour of the header it sticks to' => function (): void {
    // .gk-header is var(--gk-surface); .gk-header-sticky was rgba(13,17,23,0.92) —
    // GridKit's own near-black. Under a theme the header therefore changed colour
    // the moment it stuck: measured rgb(30,41,59) against rgb(14,19,26). The light
    // rule had the same defect (a literal white) and was changed with it, which
    // moves nothing today because the light surface IS white.
    foreach ([['/\n\.gk-header-sticky \{(.*?)\}/s', 'light'],
              ['/\[data-gk-mode="dark"\] \.gk-header-sticky,\s*\n\.gk-dark \.gk-header-sticky \{(.*?)\}/s', 'dark']] as [$muster, $wo]) {
        T::ok((bool) preg_match($muster, css(), $m), "the $wo sticky-header rule is where it is expected");
        T::ok(str_contains($m[1] ?? '', 'color-mix(in oklab, var(--gk-surface)'),
            "the $wo sticky header mixes its own surface instead of a literal");
    }
},

'the placeholder reads in every state a field can be in' => function (): void {
    // Two rules became one. The muted role already switches with the mode, so a dark
    // override is not needed — and both sides were broken in their own way: light was
    // #6e7781 at 4.27:1 on the resting field, dark was var(--gk-outline) at 2.28:1 and
    // had never been readable at all. 85% of the role over whatever ground the field
    // has carries every state: light 4.93 resting and 5.16 focused; dark 5.62 resting,
    // 4.56 focused (the field moves to var(--gk-surface) then), 4.96 read-only, and
    // 4.73 throughout without themes.css. 75% failed three of those.
    //
    // .gk-search and .gk-filter were never named in the rule and carried the browser
    // default — in the panel the table search is the placeholder a user sees most.
    T::ok((bool) preg_match('/((?:\.gk-[a-z]+::placeholder,?\s*)+)\{([^}]*)\}/s', css(), $m),
        'the placeholder rule is where it is expected');
    foreach (['.gk-input::placeholder', '.gk-search::placeholder', '.gk-filter::placeholder'] as $wahl) {
        T::ok(str_contains($m[1], $wahl), "the rule covers $wahl");
    }
    T::ok(str_contains($m[2], 'color-mix(in oklab, var(--gk-on-surface-variant) 85%'),
        'it is 85% of the muted role, not a literal');
    // A dark override would re-introduce the split this replaced.
    T::ok(!preg_match('/\[data-gk-mode="dark"\][^{]*::placeholder(?![^{]*disabled)/', css()),
        'and no dark rule overrides it again');
},

'the text roles are not darkened in dark mode' => function (): void {
    // The derivation block applies to both modes. In dark the role colours are
    // already the light end of the scale, so the same darkening step landed them
    // mid-range — the worst place against a dark ground, 3.45–3.97:1.
    T::ok((bool) preg_match(
        '/\[data-gk-mode="dark"\][^{]*\{[^}]*--gk-success-text:\s*#34d399/s', css()),
        'dark mode keeps its own literals');
},

'a theme that overrides a role also sets its text colour' => function (): void {
    $themes = themesCss();

    // Five themes set --gk-secondary to a mid grey and left --gk-on-secondary
    // alone. In light mode the base white paired fine; in dark mode the base
    // block had already set a *dark* on-secondary to pair with its own light
    // secondary — so the theme's grey ended up carrying dark text at 2.73:1.
    $secondaries = preg_match_all('/--gk-secondary:/', $themes);
    $onSecondaries = preg_match_all('/--gk-on-secondary:/', $themes);
    T::eq($onSecondaries, $secondaries,
        'every --gk-secondary in themes.css is paired with an --gk-on-secondary');
},

'the spinner class actually spins' => function (): void {
    // Form.php puts `gk-spin` on a Material Icons `sync` glyph for the AJAX
    // select and the upload indicator. The keyframes existed; nothing applied
    // them to the class, so both sat still.
    T::ok((bool) preg_match('/\.gk-spin \{[^}]*animation:\s*gk-spin/s', css()),
        'the class carries the animation');
    T::contains(css(), '@keyframes gk-spin', 'and the keyframes exist');
    T::eq(substr_count(css(), '@keyframes gk-spin {'), 1, 'defined once, not twice');
},

'a live table shows that it is loading' => function (): void {
    // GK.liveTable adds .gk-live-loading around its fetch. Nothing styled it,
    // so the rows simply sat there until new ones replaced them.
    T::contains(css(), '.gk-live-loading::after', 'it gets the same bar as the plain table');
    T::contains(css(), '.gk-live-loading .gk-table', 'and the same receding content');
},

/**
 * neutral-400 (#94a3b8) measures 2.56:1 on white. It was the colour of the
 * upload hint, the upload icon, the rich-text placeholder — and, worse, the
 * border of every unticked checkbox and unselected radio, which is the entire
 * visible control. Under 3:1 that control is invisible to some people, and the
 * suite had nothing that would notice.
 */
'the muted text token is readable on white' => function (): void {
    $c = gkContrast(gkToken('gk-neutral-500'), '#ffffff');
    T::ok($c >= 4.5, sprintf('neutral-500 on white is %.2f:1, AA body text needs 4.5', $c));
},

'no control is drawn in a colour too faint to see' => function (): void {
    // 1.4.11 asks 3:1 of a control's visible boundary. neutral-400 does not
    // reach it, so nothing that draws a control or its label may use it.
    $tooFaint = gkContrast(gkToken('gk-neutral-400'), '#ffffff');
    T::ok($tooFaint < 3.0,
        sprintf('neutral-400 is %.2f:1 — this test exists because it is too faint', $tooFaint));

    foreach ([
        '.gk-upload-hint'        => 'the caption under an upload zone',
        '.gk-upload-icon'        => 'the icon inside it',
        '.gk-checkbox-custom'    => 'the box of an unticked checkbox',
        '.gk-radio-custom'       => 'the ring of an unselected radio',
    ] as $sel => $what) {
        preg_match('/' . preg_quote($sel, '/') . '\s*\{([^}]*)\}/s', css(), $m);
        T::ok($m !== [] && $m[1] !== '', "$sel is styled at all");
        T::ok(!str_contains($m[1] ?? '', 'gk-neutral-400'),
            "$what must not be drawn in neutral-400");
    }
},

'every token the stylesheet reads is a token it defines' => function (): void {
    // --gk-surface-variant and --gk-text-secondary were read in a dozen places
    // and defined in none. The fallback after the comma is a light colour, so
    // in dark mode a hovered page number became near-white text on #f1f5f9 —
    // 1.08:1 — and consumers who trusted the names got light patches in dark.
    $all = css() . themesCss();
    preg_match_all('/(--gk-[a-z0-9-]+)\s*:/', $all, $def);
    preg_match_all('/var\(\s*(--gk-[a-z0-9-]+)/', $all, $use);
    // Hooks a page may set and GridKit never does; each is read with a fallback.
    // --gk-main-max (1.92.0): the cap of .gk-main. Declared on .gk-root it would
    // reset a value the page sets on <html>, so it is not declared at all.
    $hooks = ['--gk-inverse-surface', '--gk-on-inverse-surface', '--gk-primary-rgb', '--gk-main-max'];
    $missing = array_values(array_diff(array_unique($use[1]), array_unique($def[1]), $hooks));
    T::eq($missing, [], 'tokens read but never defined');

    // An alias on :root resolves with the light values; dark needs its own line.
    preg_match('/\[data-gk-mode="dark"\],\s*\.gk-dark\s*\{(.*?)\n\}/s', css(), $dark);
    foreach (['--gk-surface-variant', '--gk-text-secondary'] as $token) {
        T::contains($dark[1] ?? '', $token . ':', "$token has no dark value");
        // A literal is right for one ground only. themes.css moves the dark
        // surfaces, and 1.80.1's #21262d then sat 1.04:1 on its own ground.
        T::ok((bool) preg_match('/' . preg_quote($token, '/') . ':\s*var\(--gk-/', $dark[1] ?? ''),
            "$token is a literal in dark mode — it has to follow the theme");
    }
},

'dark rules hang on the attribute GridKit sets' => function (): void {
    // Theme::bodyAttrs() writes data-gk-mode. Two rules waited for data-theme,
    // which nothing sets: the marked part of a search hit stayed #fef08a under
    // inherited near-white text, 1.02:1.
    T::notContains(css(), '[data-theme=', 'a rule waits for an attribute nobody sets');
    T::contains(css(), '[data-gk-mode="dark"] .gk-search-treffer mark', 'search hits have no dark rule');
},

'the alignment classes exist and win inside a table' => function (): void {
    // .gk-text-right was recommended by the skill and by SortLink and had never
    // been defined; in a table even a defined class lost to
    // ".gk-table td { text-align: left }" on specificity.
    T::ok((bool) preg_match('/(^|\n)\.gk-text-right\s*\{\s*text-align:\s*right/', css()), '.gk-text-right is not defined');
    foreach (['.gk-table td.gk-text-right', '.gk-table th.gk-text-right', '.gk-table td.gk-text-center', '.gk-table td.gk-actions-right'] as $sel) {
        T::contains(css(), $sel, "$sel is missing, so the base rule of the table wins");
    }
},

'the current page number keeps its colours under the pointer' => function (): void {
    // .gk-pg:hover (0,2,0) beat .gk-pg-active (0,1,0): white on #f1f5f9.
    T::contains(css(), '.gk-pg-active:hover', 'the active page has no hover rule of its own');
},

'a coloured key figure is still a readable one' => function (): void {
    // StatCards painted their numbers in the FILL colours: success 2.54:1 and
    // warning 2.15:1 on the white card — under 3:1 even for large type. The
    // stylesheet has carried text variants of all three for exactly this since
    // the labels were fixed; the key figures never picked them up. And in dark
    // mode a later rule of equal specificity turned every value white, so a red
    // figure stopped being red precisely where it mattered.
    foreach (['danger', 'success', 'warning'] as $tone) {
        preg_match('/\.gk-stat-' . $tone . ' \.gk-stat-value\s*\{([^}]*)\}/', css(), $m);
        T::contains($m[1] ?? '', 'var(--gk-' . $tone . '-text)', "the $tone figure is painted in a fill colour");
        $c = gkContrast(gkToken('gk-' . $tone . '-text'), '#ffffff');
        T::ok($c >= 4.5, sprintf('%s-text on a white card is %.2f:1', $tone, $c));
    }
    preg_match('/\.gk-stat-highlight\s*\{([^}]*)\}/', css(), $h);
    T::contains($h[1] ?? '', 'var(--gk-danger-text)', 'the highlighted figure is painted in a fill colour');
    T::ok(!preg_match('/\[data-gk-mode="dark"\] \.gk-stat-value,/', css()),
        'a dark rule still paints every figure white, the coloured ones included');
},

];
