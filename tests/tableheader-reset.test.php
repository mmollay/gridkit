<?php
/**
 * The reset button of GridKit\TableHeader (1.127.0).
 *
 * Why it exists: in the SSI Panel's invoice list the reset was a text button with a
 * 16px "close" icon and an inline style — a small grey "X" next to the filters that
 * nobody saw. It is now an outlined pill with its own icon (filter_alt_off) and the
 * words beside it, a finger-sized target on touch, and no inline style. What a
 * browser has to show (one row at 1440px, a wrapped but intact toolbar at 390px,
 * light and dark) is in the photos taken with the release.
 */

declare(strict_types=1);

use GridKit\{Lang, TableHeader};

return (static function (): array {

$echoed = static function (callable $fn): string {
    ob_start();
    try { $fn(); } finally { $out = ob_get_clean(); }
    return (string) $out;
};
$link = static fn (string $html): string =>
    preg_match('#<a [^>]*gk-tableheader-reset[^>]*>.*?</a>#s', $html, $m) ? $m[0] : '';

return [

'the reset is an outlined pill with the filter icon, the words beside it, and no inline style' => function () use ($echoed, $link): void {
    Lang::set('en');
    $a = $link($echoed(fn () => TableHeader::make('t')->search('q')->reset('/x')->render()));
    T::ok($a !== '', 'the reset link carries gk-tableheader-reset');
    T::contains($a, 'gk-btn-outlined', 'outlined, not text');
    T::notContains($a, 'gk-btn-neutral', 'not the neutral role: its dark-mode rule (3 classes deep) would win over ours');
    T::contains($a, 'filter_alt_off', 'the icon says "filters off"');
    T::contains($a, 'aria-hidden="true"', 'the icon is decoration');
    T::contains($a, 'Reset filters', 'the words stand beside the icon');
    T::notContains($a, 'style=', 'no inline style');
    T::notContains($a, '>close<', 'no more little X');
    T::contains($a, 'href="/x"', 'it still goes to the base URL');
},

'the label can still be handed in, and is escaped' => function () use ($echoed, $link): void {
    $a = $link($echoed(fn () => TableHeader::make('t')->reset('/x', 'Alle <b>weg</b>')->render()));
    T::contains($a, 'Alle &lt;b&gt;weg&lt;/b&gt;', 'escaped');
    T::notContains($a, '<b>', 'no markup from the label');
},

'German: the default word is "Filter zurücksetzen"' => function () use ($echoed, $link): void {
    Lang::set('de');
    $a = $link($echoed(fn () => TableHeader::make('t')->reset('/x')->render()));
    T::contains($a, 'Filter zurücksetzen', 'the German word');
    Lang::set('en');
},

'the stylesheet: tokens only, a finger-sized target on touch, a pill, the focus ring stays' => function (): void {
    require_once __DIR__ . '/css-helpers.php';
    $css  = gkCssBare();
    $base = gkCssRule('.gk-btn.gk-tableheader-reset', $css);
    T::ok($base !== '', 'the rule exists');
    T::notContains($base, '#', 'no literal colour');
    T::contains($base, 'var(--gk-on-surface)', 'text in the full text colour (AA in light and dark)');
    T::contains($base, 'var(--gk-on-surface)', 'a border mixed from the text colour (3:1 on the surface, light and dark)');
    T::notContains((string) preg_replace('/color-mix\([^;]*;/', '', $base), '#', 'still no literal colour');
    T::contains($base, 'border-radius: 9999px', 'a pill, like the filters beside it');
    T::ok((bool) preg_match('/@media \(pointer: coarse\), \(max-width: 600px\) \{\s*\.gk-btn\.gk-tableheader-reset \{\s*min-height: var\(--gk-target-min\)/', $css),
        'at least --gk-target-min tall on touch');
    T::notContains(gkCssRule('.gk-btn.gk-tableheader-reset:focus-visible', $css), 'outline: none', 'the focus ring is not taken away');
    T::ok(gkCssRule('.gk-btn.gk-tableheader-reset .material-icons', $css) !== '', 'the icon has a size from the stylesheet, not from a style attribute');
},

'the toolbar keeps the reset at the right edge' => function () use ($echoed): void {
    $h = $echoed(fn () => TableHeader::make('t')->search('q')->reset('/x')->render());
    T::ok(strpos($h, 'gk-tableheader-spacer') < strpos($h, 'gk-tableheader-reset'), 'spacer before the reset');
},
];

})();
