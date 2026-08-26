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

];
