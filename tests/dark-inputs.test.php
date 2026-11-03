<?php
/**
 * Dark mode keeps the icons that live in an input's background (1.105.1).
 *
 * The magnifier of .gk-search and the arrow of .gk-filter, .gk-pagesize-select and
 * select.gk-input are background images. Two dark-mode rules wrote `background: #0d1117`
 * on every input, select, textarea and .gk-input; the shorthand also sets
 * background-image to none, and both rules outweigh the single class that draws the
 * icon. In dark mode the search field of every table had no magnifier and every select
 * no arrow — 24 fields of the demo.
 *
 * Held here: a dark rule that reaches these fields sets background-color, never the
 * shorthand. In a browser: the fields keep the image of light mode (checked by hand
 * with Playwright over all 6811 elements of the demo, see CHANGELOG 1.105.1).
 */

declare(strict_types=1);

require_once __DIR__ . '/css-helpers.php';

/** @return array<string,callable> */
return (static function (): array {

/** The last compound of a selector: "[data-gk-mode=\"dark\"] select.gk-input" => "select.gk-input". */
$last = static function (string $selector): string {
    $parts = preg_split('/\s+|>|\+|~/', trim($selector)) ?: [];
    return (string) end($parts);
};

/**
 * Does a dark selector reach a field that draws an icon in its background? The bare element
 * counts only in the general rule ("[data-gk-mode="dark"] select"): ".gk-header-search input"
 * is a field without an icon, and its `background: transparent` takes nothing away.
 */
$reachesIconField = static function (string $selector) use ($last): bool {
    if (preg_match('/^(?:\[data-gk-mode="dark"\]|\.gk-dark) (?:input|select|textarea)$/', $selector)) return true;
    return (bool) preg_match('/^(?:input|select|textarea)?\.gk-(?:input|search|filter|pagesize-select)(?:$|[.:\[])/', $last($selector));
};

return [

'the fields that draw an icon in their background still do so in light mode' => function (): void {
    foreach (['.gk-search', '.gk-filter', 'select.gk-input'] as $sel) {
        $d = gkCssDeclFor($sel);
        T::ok(str_starts_with($d['background-image'] ?? '', 'url("data:image/svg+xml'), "$sel has no icon in its background");
    }
},

'no dark-mode rule that reaches input, select, textarea, .gk-input, .gk-search or .gk-filter uses the background shorthand' => function () use ($reachesIconField): void {
    preg_match_all('/(?:^|\n)([^\s@{}][^{}]*)\{([^{}]*)\}/', gkCssBare(), $m, PREG_SET_ORDER);
    $checked = 0;
    foreach ($m as [, $list, $body]) {
        $decl = gkCssDecl($body);
        foreach (array_map('trim', explode(',', $list)) as $sel) {
            if (!str_starts_with($sel, '[data-gk-mode="dark"]') && !str_starts_with($sel, '.gk-dark')) continue;
            if (!$reachesIconField($sel)) continue;
            $checked++;
            T::ok(!array_key_exists('background', $decl) || array_key_exists('background-image', $decl),
                "\"$sel\" writes `background: " . ($decl['background'] ?? '') . "` — the shorthand removes the icon; write background-color");
        }
    }
    // The two rules this test was written for must still be found, or it checks nothing.
    T::ok($checked >= 10, "only $checked dark selectors reach the fields — has the stylesheet been reorganised?");
},

'the two dark rules set the colour of the field' => function (): void {
    T::eq(gkCssDeclFor('[data-gk-mode="dark"] select')['background-color'] ?? '', '#0d1117', 'dark select has another background colour');
    T::eq(gkCssDeclFor('[data-gk-mode="dark"] .gk-input')['background-color'] ?? '', '#0d1117', 'dark .gk-input has another background colour');
},

];
})();
