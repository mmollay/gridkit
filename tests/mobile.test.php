<?php
/**
 * The mobile layout the README promises.
 *
 * "a mobile layout you didn't have to think about" is the third line of the
 * README, and nothing checked it. At 390 px the invoice example scrolled
 * sideways, every table card was 540 px wide on a 390 px screen — so the values
 * sat off-screen and the layout the card mode exists to avoid came back — and
 * the header pushed its New button, theme switcher and mode toggle out of reach
 * with nothing to scroll them back.
 *
 * CSS cannot be executed here, so these check that the rules which encode those
 * fixes are still in the stylesheet. Brittle by nature, deliberately so: each
 * one stands for a defect that was found by opening the page on a phone.
 */

declare(strict_types=1);

/** The (max-width: 768px) blocks, concatenated. */
function mobileCss(): string
{
    static $css = null;
    if ($css !== null) return $css;

    $lines = explode("\n", (string) file_get_contents(__DIR__ . '/../css/gridkit.css'));
    $out   = '';
    for ($i = 0; $i < count($lines); $i++) {
        if (!str_contains($lines[$i], '@media (max-width: 768px)')
            && !str_contains($lines[$i], '@media (max-width: 480px)')) continue;

        $depth = 0;
        for ($j = $i; $j < count($lines); $j++) {
            $depth += substr_count($lines[$j], '{') - substr_count($lines[$j], '}');
            $out   .= $lines[$j] . "\n";
            if ($depth === 0 && $j > $i) break;
        }
    }
    return $css = $out;
}

/**
 * Nur der 768-px-Block. mobileCss() wirft den 480-px-Block mit hinein, und eine
 * Regel, die dorthin rutscht, gilt für Telefone zwischen 481 und 768 px nicht
 * mehr — im gemeinsamen Text sähe sie trotzdem richtig aus.
 */
function telefonCss(): string
{
    $lines = explode("\n", (string) file_get_contents(__DIR__ . '/../css/gridkit.css'));
    $out   = '';
    for ($i = 0; $i < count($lines); $i++) {
        if (!str_contains($lines[$i], '@media (max-width: 768px)')) continue;
        $depth = 0;
        for ($j = $i; $j < count($lines); $j++) {
            $depth += substr_count($lines[$j], '{') - substr_count($lines[$j], '}');
            $out   .= $lines[$j] . "\n";
            if ($depth === 0 && $j > $i) break;
        }
    }

    return $out;
}

/** @return array<string,callable> */
return [

'the stylesheet still parses and has mobile rules at all' => function (): void {
    $css = (string) file_get_contents(__DIR__ . '/../css/gridkit.css');
    T::eq(substr_count($css, '{'), substr_count($css, '}'), 'braces balance');
    T::ok(strlen(mobileCss()) > 2000, 'the mobile blocks are substantial, got ' . strlen(mobileCss()));
},

'card mode drops the column widths it inherits' => function (): void {
    $css = mobileCss();

    // Table renders width/min-width/max-width as inline styles on every <td>.
    // As a card row a `'width' => '140px'` squeezed the label and the value
    // into a strip — they overlapped. An inline style can only be beaten with
    // !important, which is why it is here and nowhere else in this rule.
    T::contains($css, '.gk-table-mobile-card td', 'the card cell rule exists');
    T::ok((bool) preg_match('/\.gk-table-mobile-card td \{[^}]*width:\s*auto\s*!important/s', $css),
        'card cells reset the declared width');
    T::ok((bool) preg_match('/\.gk-table-mobile-card td \{[^}]*min-width:\s*0\s*!important/s', $css),
        'and the declared min-width');
},

'card mode is exempt from the 540px table minimum' => function (): void {
    $css = mobileCss();

    // 540px keeps the columns readable while the wrapper scrolls sideways,
    // which is the whole point of the scroll mode. Applied to card mode it made
    // every card 540px wide on a 390px phone.
    T::contains($css, '.gk-table-wrap:not(.gk-table-mobile-card) .gk-table',
        'the minimum applies to the scrolling mode only');
    T::ok((bool) preg_match('/\.gk-table-mobile-card \.gk-table \{[^}]*min-width:\s*0/s', $css),
        'and card mode shrinks to its container');
},

'row actions stay side by side and stay tappable' => function (): void {
    $css = mobileCss();

    // The generic mobile rule stacks .gk-btn-group vertically, which suits a
    // segmented group of labelled buttons. Applied to a table row it turned two
    // icons into a 51px-tall column inside every card.
    T::ok((bool) preg_match('/\.gk-actions \.gk-btn-group,\s*\.gk-action-group \{[^}]*flex-direction:\s*row/s', $css),
        'row actions are laid out horizontally');

    // They rendered at 26x25 px, under every touch-target guideline.
    T::ok((bool) preg_match('/\.gk-actions \.gk-btn-icon-only[^{]*\{[^}]*min-height:\s*(4[0-9]|[5-9][0-9])px/s', $css),
        'and are at least 40px tall');
},

'tapping a field does not zoom the page in' => function (): void {
    // Safari on iOS zooms the whole page into any input whose font is smaller
    // than 16px, the moment it is tapped — and it stays zoomed. Everything in
    // GridKit is 14px, so every field a user types into did it.
    //
    // The 768px block, NOT mobileCss(): a rule that slipped into the 480px block
    // would look right in the combined text and leave every phone between 481
    // and 768px zooming. ci/browser.js measures the rendered size at 390px —
    // this only guards the wiring.
    $css = telefonCss();

    foreach ([
        '.gk-input', '.gk-search', '.gk-filter', 'textarea.gk-input',
        '.gk-select-search-input input', '.gk-multiselect-input',
        '.gk-ajax-search-input', '.gk-color-hex', '.gk-form-compact .gk-input',
    ] as $sel) {
        $q = preg_quote($sel, '/');
        // 16px or 1rem — the same size, and a rewrite from one to the other is
        // not a regression. What the field actually renders at is measured in
        // ci/browser.js; this only guards that the rule exists and is in reach.
        T::ok((bool) preg_match('/' . $q . '[,\s][^{]*\{[^}]*font-size:\s*(16px|1rem)/s', $css),
            $sel . ' keeps a font iOS zooms into');
    }

    // And nothing later in the same block may take it back: a rule with higher
    // specificity further down would undo all of the above silently.
    $nach = substr($css, (int) strpos($css, 'font-size: 16px') ?: (int) strpos($css, 'font-size: 1rem'));
    T::ok(!preg_match('/\.gk-(input|search|filter)[^{]*\{[^}]*font-size:\s*1[0-5]px/s', $nach),
        'a later rule in the same block puts the small font back');
},

'the page title has an action area to put things in' => function (): void {
    // The skill has called .gk-page-header "page title + action area" since 1.9,
    // but the class for that area existed in no stylesheet: the SSI Panel wrote
    // it itself and uses it in 41 views. Every other system had to invent it.
    $css = (string) file_get_contents(__DIR__ . '/../css/gridkit.css');
    T::ok((bool) preg_match('/\.gk-page-header-actions \{[^}]*display:\s*flex/s', $css),
        '.gk-page-header-actions is not defined');
    T::ok((bool) preg_match('/\.gk-page-header-actions \{[^}]*flex-wrap:\s*wrap/s', $css),
        'a long title squeezes the buttons instead of wrapping them');
},

'the header can shrink instead of pushing controls off-screen' => function (): void {
    $css = mobileCss();

    // .gk-header > * is flex-shrink: 0 by design, so anything that does not fit
    // leaves the screen rather than wrapping — silently, because nothing
    // scrolls to reveal it.
    T::ok((bool) preg_match('/\.gk-header-right,\s*\.gk-header-actions \{[^}]*flex-shrink:\s*1/s', $css),
        'the right-hand group may shrink');
    T::ok((bool) preg_match('/\.gk-theme-switcher \.gk-theme-dot \{[^}]*display:\s*none/s', $css),
        'the accent swatches step aside; the light/dark toggle stays');
},

'the example app does not lay out a row that cannot wrap' => function (): void {
    $app = (string) file_get_contents(__DIR__ . '/../examples/invoices/index.php');

    // Its own toolbar — theme switcher, language link, reset button — made the
    // page scroll sideways at 390px before this.
    T::ok((bool) preg_match('/\.app-tools\s*\{[^}]*flex-wrap:\s*wrap/s', $app),
        'the example wraps its toolbar');
},

];
