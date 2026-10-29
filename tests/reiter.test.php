<?php
/**
 * Tabs as links, a segmented button, steps, a quiet disclosure and a to-do in a timeline (1.99.0).
 *
 * Why it exists: the SSI Panel built the head of its landing pages out of local parts — register
 * tabs with their own backgrounds, a pill group for the second row, single chips for a period, a
 * card with a 6px edge for the status and numbered cards for what is still open. Martin, 28.10.2026:
 * "ugly tabs", "a strange card with a thick edge", "terrible pill group and chips", "no inline home
 * constructions". Every one of those parts is something another SSI system needs too, so GRIDKit
 * learns them and the Panel keeps only its logic.
 *
 * What is held here is what can be read in the stylesheet and in the markup of FilterChips. What a
 * browser has to show — contrast in every theme, light and dark — is in ci/farben.js.
 */

declare(strict_types=1);

use GridKit\FilterChips;

require_once __DIR__ . '/css-helpers.php';

/** @return array<string,callable> */
return (static function (): array {

/** The bodies of all rules (any depth) whose selector list contains $selector exactly. */
$anywhere = static function (string $selector): array {
    preg_match_all('/([^{}]+)\{([^{}]*)\}/', gkCssBare(), $m, PREG_SET_ORDER);
    $out = [];
    foreach ($m as [, $list, $body]) {
        if (in_array($selector, array_map('trim', explode(',', $list)), true)) {
            $out[] = gkCssDecl($body);
        }
    }
    return $out;
};
/** The body of the @media block that starts with $query (the first one). */
$media = static function (string $query): string {
    $css = gkCssBare();
    $all = '';
    $at = 0;
    while (($p = strpos($css, '@media ' . $query, $at)) !== false) {
        $open = strpos($css, '{', $p);
        $depth = 0;
        for ($i = $open; $i < strlen($css); $i++) {
            if ($css[$i] === '{') $depth++;
            if ($css[$i] === '}' && --$depth === 0) break;
        }
        $all .= substr($css, $open + 1, $i - $open - 1) . "\n";
        $at = $i;
    }
    return $all;
};

return [

'a tab can be a link: aria-current="page" looks like the active tab, without an underline' => function (): void {
    $active = gkCssDeclFor('.gk-tab-btn[aria-current="page"]');
    T::eq($active, gkCssDeclFor('.gk-tab-btn.gk-active'), 'a link tab with aria-current="page" does not look like .gk-active');
    T::eq($active['color'] ?? '', 'var(--gk-primary-text)', 'the active tab is not in the text-safe primary (--gk-primary measured 4.47:1)');
    T::ok((int) ($active['font-weight'] ?? 0) >= 600, 'the active tab differs from the others by colour alone — it needs a weight and its line');
    T::eq($active['border-bottom-color'] ?? '', 'var(--gk-primary)', 'the active tab has lost its line');
    T::eq(gkCssDeclFor(':where(a).gk-tab-btn')['text-decoration'] ?? '', 'none', 'a link tab is underlined (and the rule must stay as weak as one class)');
    $focus = gkCssDeclFor('.gk-tab-btn:focus-visible');
    T::ok(str_starts_with($focus['outline'] ?? '', '2px solid'), 'a tab has no visible focus ring');
},

'a tab carries a symbol, and a filled row has 44px tabs that share a phone width' => function () use ($media): void {
    T::eq(gkCssDeclFor('.gk-tab-icon')['font-size'] ?? '', 'var(--gk-icon-md)', 'the symbol of a tab has no size of its own');
    T::eq(gkCssDeclFor('.gk-tab-nav-fill .gk-tab-btn')['min-height'] ?? '', 'var(--gk-target-min)', 'a filled tab is smaller than a finger');
    $phone = $media('(max-width: 600px)');
    T::ok((bool) preg_match('/\.gk-tab-nav-fill \.gk-tab-btn\s*\{[^}]*flex: 1 1 auto;[^}]*flex-direction: column/', $phone),
        'on a phone the filled tabs do not share the width with the symbol over the word');
    T::ok(!preg_match('/\.gk-tab-nav-fill \.gk-tab-btn\s*\{[^}]*flex: 1 1 0/', $phone),
        'equal widths break "Veröffentlichen" in the middle of the word at 390px');
},

'a second row of tabs is quieter: no rule of its own under it, smaller words, the plain text colour' => function (): void {
    $nav = gkCssDeclFor('.gk-tab-nav-secondary');
    T::eq($nav['border-bottom'] ?? '', '0', 'the second row draws a second full line under the first');
    T::eq(gkCssDeclFor('.gk-tab-nav-secondary .gk-tab-btn')['font-size'] ?? '', 'var(--gk-text-label)', 'the second row is as loud as the first');
    T::eq(gkCssDeclFor('.gk-tab-nav-secondary .gk-tab-btn')['min-height'] ?? '', 'var(--gk-target-min)', 'a tab of the second row is smaller than a finger');
    T::eq(gkCssDeclFor('.gk-tab-nav-secondary .gk-tab-btn[aria-current="page"]')['color'] ?? '', 'var(--gk-on-surface)', 'the second row is coloured like the first');
},

'a segmented button: one choice of a few, joined, the chosen one tinted AND ticked' => function () use ($media): void {
    T::eq(gkCssDeclFor(':where(.gk-segmented)')['display'] ?? '', 'inline-flex', 'the segments are not a joined row');
    T::eq(gkCssDeclFor('.gk-segmented')['overflow-x'] ?? '', 'auto', 'a row too wide for a phone is cut instead of scrolled');
    $sel = '.gk-segmented-item[aria-current]:not([aria-current="false"])';
    foreach ([$sel, '.gk-segmented-item[aria-pressed="true"]', '.gk-segmented-item.gk-active'] as $s) {
        T::eq(gkCssDeclFor($s)['background'] ?? '', 'var(--gk-primary-container)', "$s is not the chosen segment");
        T::eq(gkCssDeclFor($s)['color'] ?? '', 'var(--gk-on-primary-container)', "$s has no text colour for its tint");
        T::eq(gkCssDeclFor("$s::before")['content'] ?? '', '""', "$s is told apart by colour alone — it needs its tick");
    }
    T::ok(str_starts_with(gkCssDeclFor('.gk-segmented-item:focus-visible')['outline'] ?? '', '2px solid'), 'a segment has no visible focus ring');
    T::ok((bool) preg_match('/\.gk-segmented-item\s*\{\s*min-height: var\(--gk-target-min\)/', $media('(pointer: coarse)')), 'on a touch screen a segment is smaller than a finger');
    T::ok(str_contains(gkCssBare(), '@media (pointer: coarse), (max-width: 600px) { .gk-segmented-item { min-height: var(--gk-target-min)'), 'on a phone-wide screen with a mouse a segment is smaller than a finger');
    T::ok(!str_contains($media('(max-width: 480px)'), '.gk-segmented { display: flex; width: 100%'),
        'two segmented rows at full width look like one bar on a phone');
},

'steps: done is a tick, now is a dot and bold, open is an empty ring — three shapes, not three colours' => function (): void {
    $base = gkCssDeclFor('.gk-steps-item::before');
    $done = array_replace($base, gkCssDeclFor('.gk-steps-done::before'));
    $now = array_replace($base, gkCssDeclFor('.gk-steps-current::before'));
    T::eq(gkCssDeclFor('.gk-steps')['list-style'] ?? '', 'none', 'the steps show list bullets');
    T::eq($done['content'] ?? '', '"✓" / ""', 'a done step has no tick (with an empty text for a screen reader)');
    T::eq($done['color'] ?? '', 'var(--gk-on-success)', 'the tick is not in the colour that reads on its disc');
    T::ok(str_contains($now['background'] ?? '', 'radial-gradient'), 'the current step has no dot');
    T::eq(count(array_unique([$base['background'] ?? '', $done['background'] ?? '', $now['background'] ?? ''])), 3, 'two steps share one shape');
    T::eq(gkCssDeclFor('.gk-steps-current')['font-weight'] ?? '', '700', 'the current step is not set in bold');
    T::ok(gkCssDeclFor('.gk-steps-item:not(:last-child)::after') !== [], 'there is no line between two steps');
},

'a timeline knows a step still to do, a button in a step and a compact size for a message' => function () use ($media): void {
    T::eq(gkCssDeclFor('.gk-timeline-todo .gk-timeline-marker')['border-color'] ?? '', 'var(--gk-primary)', 'a step to do has no ring in the primary colour');
    T::eq(gkCssDeclFor('.gk-timeline-todo .gk-timeline-state')['color'] ?? '', 'var(--gk-primary-text)', 'the word of a step to do is not in the text-safe primary');
    $grid = gkCssDeclFor('.gk-timeline-step:has(> .gk-timeline-actions)');
    T::ok(str_contains($grid['grid-template-columns'] ?? '', 'max-content'), 'the button of a step stands far right, away from its sentence');
    T::eq(gkCssDeclFor('.gk-timeline-compact')['--gk-timeline-marker'] ?? '', '20px', 'the compact timeline has the full 28px markers');
    T::ok(str_contains($media('(max-width: 480px)'), '.gk-timeline-actions { grid-column: 2;'), 'on a phone the button of a step does not move under its sentence');
},

'a disclosure is a native details: no marker of the browser, a target of 44px, a focus ring' => function (): void {
    T::eq(gkCssDeclFor(':where(.gk-disclosure) > summary')['display'] ?? '', 'inline-flex', 'the summary is not a row of word and arrow');
    $sum = gkCssDeclFor('.gk-disclosure > summary');
    T::eq($sum['min-height'] ?? '', 'var(--gk-target-min)', 'the summary is smaller than a finger');
    T::eq($sum['list-style'] ?? '', 'none', 'the browser draws its own triangle beside ours');
    T::eq(gkCssDeclFor('.gk-disclosure > summary::-webkit-details-marker')['display'] ?? '', 'none', 'Safari draws its own triangle beside ours');
    T::ok(gkCssDeclFor('.gk-disclosure[open] > summary::after') !== [], 'the arrow does not turn when it is open');
    T::ok(str_starts_with(gkCssDeclFor('.gk-disclosure > summary:focus-visible')['outline'] ?? '', '2px solid'), 'the summary has no visible focus ring');
},

'a message with actions keeps its symbol at the top, and a button in it keeps its own colour' => function (): void {
    T::eq(gkCssDeclFor('.gk-message:has(> .gk-message-actions) > .material-icons')['align-self'] ?? '', 'flex-start', 'the symbol slides to the middle of a tall message');
    T::eq(gkCssDeclFor(':where(.gk-message) .gk-btn .material-icons')['color'] ?? '', 'inherit', 'the symbol in a button takes the tone of the message, not the colour of its button');
    foreach (['.gk-btn' => '18px', '.gk-btn-xs' => '14px', '.gk-btn-sm' => '16px', '.gk-btn-lg' => '22px'] as $btn => $px) {
        T::eq(gkCssDeclFor(":where(.gk-message) $btn .material-icons")['font-size'] ?? '', $px, "the symbol in a $btn inside a message takes the 24px of the message symbol");
    }
    T::eq(gkCssDeclFor(':where(.gk-message) .gk-timeline-actions .gk-btn-outlined')['background-color'] ?? '', 'var(--gk-surface)', 'an outlined button of a to-do step stands on the tint of the message (4.35:1 in a warning)');
},

'in a toned message the focus ring takes the tone, and on a phone the actions stand under the text' => function () use ($media): void {
    foreach (['info', 'success', 'warning', 'error'] as $t) {
        T::eq(gkCssDeclFor(":where(.gk-message-$t) .gk-btn:focus-visible")['outline-color'] ?? '', "var(--gk-on-$t-container)", "the focus ring in a $t message is the violet of the primary colour");
        $dark = gkCssDeclFor("[data-gk-mode=\"dark\"] .gk-message-$t")['color'] ?? 'none';
        T::eq(gkCssDeclFor(":where([data-gk-mode=\"dark\"] .gk-message-$t) .gk-btn:focus-visible")['outline-color'] ?? '', $dark, "in dark mode the focus ring in a $t message is not the text colour of the message");
    }
    $phone = $media('(max-width: 480px)');
    T::ok(preg_match('/\.gk-message-actions\s*\{[^}]*flex-direction:\s*column[^}]*align-items:\s*stretch[^}]*width:\s*100%[^}]*margin-left:\s*0/', $phone) === 1, 'on a phone two buttons of a message stand side by side at different widths');
},

'what sets display stays as weak as one class, so a consumer\'s hiding class still wins' => function () use ($anywhere): void {
    foreach ([':where(.gk-segmented)', ':where(.gk-steps)', ':where(.gk-steps-item)', ':where(.gk-disclosure) > summary', ':where(.gk-segmented-item)'] as $s) {
        T::ok($anywhere($s) !== [], "$s is missing");
    }
    preg_match_all('/([^{}@]+)\{([^{}]*display:[^{}]*)\}/', gkCssBare(), $m, PREG_SET_ORDER);
    foreach ($m as [, $list]) {
        foreach (array_map('trim', explode(',', $list)) as $s) {
            if (preg_match('/^\.gk-(segmented|steps|disclosure)/', $s) && !str_contains($s, '::')) {
                T::ok(false, "$s sets display stronger than one class");
            }
        }
    }
},

'FilterChips::segmented() draws the same links as a segmented button' => function (): void {
    $_GET = ['tage' => '7'];
    $html = T::capture(fn() => (new FilterChips('zeitraum', 'tage'))->baseUrl('/s')->segmented('Zeitraum')
        ->chip('1', 'Heute')->chip('7', '7 <Tage>')->chip('30', '30 Tage')->render());
    $_GET = [];
    T::contains($html, '<div class="gk-segmented" role="group" aria-label="Zeitraum" data-gk-chips="zeitraum">', 'the row is not a labelled group');
    T::eq(substr_count($html, 'class="gk-segmented-item'), 3, 'not every value is a segment');
    T::contains($html, 'href="/s?tage=7" class="gk-segmented-item gk-active" aria-current="true">', 'the value of the URL is not the chosen segment');
    T::contains($html, '7 &lt;Tage&gt;', 'a label is not escaped');
    T::notContains($html, 'class="gk-chip', 'a segment still wears the classes of a chip');
    $plain = T::capture(fn() => (new FilterChips('z', 'tage'))->chip('1', 'Heute')->render());
    T::contains($plain, 'class="gk-filter-chips"', 'without segmented() the chips have changed');
},


/* ── 1.101.0: Karteireiter, Raster am Handy, Reiter in Reitern, Stufen am Handy ─────────────────── */

'a Karteireiter row is ordered with a class, and without it every tab row looks as it did' => function (): void {
    // The shapes every other SSI system uses today, held literally.
    $nav = gkCssDeclFor('.gk-tab-nav');
    T::eq([$nav['display'] ?? '', $nav['border-bottom'] ?? '', $nav['margin-bottom'] ?? ''], ['flex', '2px solid var(--gk-border)', '20px'], 'the plain tab row changed');
    $btn = gkCssDeclFor('.gk-tab-btn');
    T::eq([$btn['padding'] ?? '', $btn['background'] ?? '', $btn['border-bottom'] ?? ''], ['9px 18px', 'none', '2px solid transparent'], 'the plain tab changed');
    T::eq(gkCssDeclFor('.gk-tab-nav-secondary')['border-bottom'] ?? '', '0', 'the quiet second row changed');
    T::eq(gkCssDeclFor('.gk-tab-nav-fill .gk-tab-btn')['min-height'] ?? '', 'var(--gk-target-min)', 'the filled row changed');
    T::ok(gkCssDeclFor('.gk-tabs-nav') !== [] && gkCssDeclFor('.gk-tab') !== [] && gkCssDeclFor('.gk-tab-active') !== [], 'the older pair for [data-gk-tabs] is gone');
    // Every rule in a media block of the Karteireiter names the ordered shape.
    T::ok(preg_match_all('/@media[^{]*\{(?:[^{}]*\{[^{}]*\})*[^{}]*\}/', gkCssBare(), $blocks) > 0, 'no @media blocks read');
    foreach ($blocks[0] as $b) {
        if (!str_contains($b, '.gk-tab-nav-karte') && !str_contains($b, '.gk-tab-flaeche')) continue;
        preg_match_all('/([^{}]+)\{[^{}]*\}/', substr($b, strpos($b, '{') + 1), $rules);
        foreach ($rules[1] as $list) {
            foreach (array_map('trim', explode(',', $list)) as $s) {
                T::ok(str_contains($s, 'gk-tab-nav-karte') || str_contains($s, 'gk-tab-flaeche') || !str_contains($s, 'gk-tab'),
                    "$s in a media block of the Karteireiter reaches tabs that did not order it");
            }
        }
    }
},

'Karteireiter: the chosen tab grows into its surface, taller, bold and with a 3px accent; the others lie grey behind' => function (): void {
    $tok = gkCssDeclFor('.gk-tab-flaeche');
    T::eq($tok['--gk-tab-kante'] ?? '', 'color-mix(in oklab, var(--gk-outline) 70%, var(--gk-on-surface))', 'the edge is not the outline mixed up to 3:1');
    T::eq($tok, gkCssDeclFor('.gk-tab-nav-karte'), 'the row and its surface do not carry the same tokens (set on the element, not :root, so dark mode reaches them)');
    $nav = gkCssDeclFor('.gk-tab-nav.gk-tab-nav-karte');
    T::eq([$nav['margin-bottom'] ?? '', $nav['border-bottom'] ?? ''], ['-1px', '0'], 'the tabs do not sit on the top edge of their surface');
    $off = gkCssDeclFor('.gk-tab-nav-karte .gk-tab-btn');
    T::eq([$off['border'] ?? '', $off['background'] ?? '', $off['min-height'] ?? ''], ['1px solid var(--gk-tab-kante)', 'var(--gk-surface-container-high)', 'var(--gk-target-min)'], 'a tab that is not chosen is not a framed grey tab of 44px');
    $on = gkCssDeclFor('.gk-tab-nav-karte .gk-tab-btn.gk-active');
    T::eq($on, gkCssDeclFor('.gk-tab-nav-karte .gk-tab-btn[aria-current="page"]'), 'a link Karteireiter with aria-current looks different from .gk-active');
    T::eq([$on['background'] ?? '', $on['border-bottom-color'] ?? '', $on['font-weight'] ?? '', $on['box-shadow'] ?? ''],
        ['var(--gk-tab-flaeche)', 'var(--gk-tab-flaeche)', '700', 'inset 0 3px 0 var(--gk-primary)'], 'the chosen tab does not grow into its surface with weight and accent');
    T::ok((int) ($on['padding-top'] ?? '0') > (int) ($off['padding'] ?? '0'), 'the chosen tab is not taller than the others');
    T::eq(gkCssDeclFor('.gk-tab-nav-karte ~ .gk-tab-flaeche')['border'] ?? '', '1px solid var(--gk-tab-kante)', 'the surface has no edge');
    T::eq(gkCssDeclFor('.gk-tab-nav-karte .gk-tab-btn:focus-visible')['outline-offset'] ?? '', '2px', 'the focus ring lies on the edge of the tab');
},

'Karteireiter, second row and inside a window: on a line, no fill, no accent, only the chosen one framed' => function (): void {
    $nav = gkCssDeclFor('.gk-tab-nav.gk-tab-nav-karte.gk-tab-nav-secondary');
    T::eq($nav['border-bottom'] ?? '', '1px solid var(--gk-tab-kante)', 'the second row stands on no line');
    $off = gkCssDeclFor('.gk-tab-nav-karte.gk-tab-nav-secondary .gk-tab-btn');
    T::eq([$off['background'] ?? '', $off['border-color'] ?? '', $off['font-size'] ?? ''], ['transparent', 'transparent', 'var(--gk-text-label)'], 'the second row is as loud as the first');
    $on = gkCssDeclFor('.gk-tab-nav-karte.gk-tab-nav-secondary .gk-tab-btn.gk-active');
    T::eq([$on['border-color'] ?? '', $on['box-shadow'] ?? '', $on['font-weight'] ?? ''], ['var(--gk-tab-kante)', 'none', '600'], 'the chosen tab of the second row is not framed, or carries the accent of the first');
},

'a badge and a count in a tab; the count of the chosen tab is a pair that reads (4.47:1 on the filled primary)' => function (): void {
    T::eq(gkCssDeclFor('.gk-tab-badge')['white-space'] ?? '', 'nowrap', 'a badge breaks inside');
    T::eq(gkCssDeclFor('.gk-tab-count')['font-variant-numeric'] ?? '', 'tabular-nums', 'the digits of a count jump');
    $on = gkCssDeclFor('.gk-tab-btn.gk-active .gk-tab-count');
    T::eq([$on['background'] ?? '', $on['color'] ?? ''], ['var(--gk-primary-container)', 'var(--gk-on-primary-container)'], 'the count of the chosen tab is not the tonal pair');
    T::eq($on, gkCssDeclFor('.gk-tab-btn[aria-current="page"] .gk-tab-count'), 'a link tab counts differently');
},

'on a phone a Karteireiter row is a grid: everything visible, two columns up to 480px, symbol over the word' => function () use ($media): void {
    $p600 = $media('(max-width: 600px)');
    T::contains($p600, '.gk-tab-nav-karte { display: grid; }', 'on a phone the Karteireiter still scroll or are cut (and display must stay one class strong)');
    T::ok((bool) preg_match('/\.gk-tab-nav-karte \.gk-tab-btn,\s*\.gk-tab-nav-karte\.gk-tab-nav-secondary \.gk-tab-btn\s*\{[^}]*flex-direction: column[^}]*white-space: normal/', $p600),
        'in the grid the symbol does not stand over the word, or a long word cannot break');
    T::ok(!preg_match('/gk-tab-nav-karte[^{]*\{[^}]*overflow-x/', $p600), 'a Karteireiter row on a phone hides tabs behind a scroll');
    T::contains($media('(max-width: 480px)'), 'grid-template-columns: repeat(2, minmax(0, 1fr))', 'up to 480px the grid is not two columns');
},

'nested tabs: a tab and its panels are those of its own .gk-tabs, never of one inside a panel' => function (): void {
    $js = (string) file_get_contents(__DIR__ . '/../js/gridkit.js');
    $from = strpos($js, 'GK.tabsMarkup = {');
    $code = substr($js, $from, strpos($js, '// === AJAX PAGINATION ===', $from) - $from);
    T::contains($code, 'return el.closest(".gk-tabs") === wrap;', 'tabsMarkup.own() does not stop at the nearest .gk-tabs');
    T::notContains($code, 'tabs.querySelectorAll(".gk-tab-btn")', 'the click handler clears the tabs of every inner .gk-tabs too');
    T::notContains($code, 'tabs.querySelectorAll(".gk-tab-panel")', 'the click handler closes the panels of every inner .gk-tabs too');
    T::notContains($code, "wrap.querySelector('.gk-tab-panel[data-tab=", 'init() pairs a tab with a panel of the same key one level down');
    T::eq(substr_count($code, 'GK.tabsMarkup.own('), 4, 'not every lookup of tabs, panels and the nav goes through own()');
    foreach (['ArrowRight', 'ArrowLeft', '"Home"', '"End"', 'aria-selected', 'role", "tablist"', 'aria-controls'] as $k) {
        T::contains($code, $k, "the authored tabs lost $k");
    }
},

'steps on a phone: a shorter line BEFORE its step, cut at the start of a row, so none hangs at the end' => function () use ($media): void {
    $p = $media('(max-width: 480px)');
    T::ok((bool) preg_match('/\.gk-steps \{ --gk-steps-vorlauf: (\d+)px; margin-left: calc\(-1 \* var\(--gk-steps-vorlauf\)\); clip-path: inset\(0 0 0 var\(--gk-steps-vorlauf\)\); \}/', $p, $v),
        'on a phone the list does not move left by the cut margin');
    T::ok((bool) preg_match('/\.gk-steps-item:not\(:last-child\)::after,\s*\.gk-steps-item:last-child::after \{ content: ""; order: -1; flex: none; width: (\d+)px; height: 2px; margin: 0 0 0 (\d+)px;/', $p, $l),
        'on a phone the line does not stand before its step');
    T::ok((int) $l[1] < 24, 'the line is not shorter on a phone');
    // margin + line + the 6px gap of the step = the margin cut away: the first disc stands where it stood.
    T::eq((int) $l[2] + (int) $l[1] + (int) (gkCssDeclFor('.gk-steps-item')['gap'] ?? 0), (int) $v[1], 'the cut margin is not exactly the line before a step');
    T::eq(gkCssDeclFor('.gk-steps-item:not(:last-child)::after')['width'] ?? '', '24px', 'the steps changed at a desk');
},

];
})();
