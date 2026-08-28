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

];
