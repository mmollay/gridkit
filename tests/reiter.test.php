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

/** The variants section of the stylesheet (1.103.0), comments stripped. */
$gkTabVariantsFn = static function (bool $bare = false): string {
    $css = gkCssText();
    $from = strpos($css, '/* ── Reiter-Varianten (1.103.0)');
    $to = strpos($css, '/* ── Segmented button', (int) $from);
    if ($from === false || $to === false) return '';
    return (string) preg_replace('#/\*.*?\*/#s', '', substr($css, $from, $to - $from));
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
    T::eq($tok['--gk-tab-kante'] ?? '', 'color-mix(in oklab, var(--gk-outline-variant) 50%, var(--gk-outline))', 'the edge is not the middle of the card hairline and the control outline');
    // 1.103.1: every declaration of the edge (the Karteireiter AND the three new styles) is the same mix of the two
    // roles cards, tables and inputs already use — no text colour in it (that was 30 % near-black: "far too black"),
    // no literal. Contrast is held in ci/farben.js (the edge is never quieter than a card's, never harder than the outline).
    preg_match_all('/--gk-tab-kante:\s*([^;]+);/', gkCssBare(), $kanten);
    T::eq(array_unique($kanten[1]), ['color-mix(in oklab, var(--gk-outline-variant) 50%, var(--gk-outline))'], 'a declaration of --gk-tab-kante differs from the others');
    T::ok(count($kanten[1]) === 2, 'the edge is declared ' . count($kanten[1]) . ' times (Karteireiter, new styles), expected 2');
    T::ok(!preg_match('/on-surface|#[0-9a-f]{3,8}\b|rgba?\(/i', implode(' ', $kanten[1])), 'the edge reaches for a text colour or a literal instead of the roles of the system');
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

'Karteireiter, second row and inside a window: a LINE tab row — words on a thin line, no box, no fill; the chosen one a 3px stroke in the main colour' => function () use ($media): void {
    $nav = gkCssDeclFor('.gk-tab-nav.gk-tab-nav-karte.gk-tab-nav-secondary');
    T::eq($nav['border-bottom'] ?? '', '1px solid var(--gk-tab-kante)', 'the second row stands on no thin line');
    $off = gkCssDeclFor('.gk-tab-nav-karte.gk-tab-nav-secondary .gk-tab-btn');
    T::eq([$off['border'] ?? '', $off['border-bottom'] ?? '', $off['background'] ?? '', $off['font-size'] ?? ''],
        ['0', '3px solid transparent', 'transparent', 'var(--gk-text-label)'], 'a tab of the second row is a box, or grey, or as loud as the first row');
    T::ok(!isset($off['border-radius']), 'the second row sets its own corners (it takes those of the Karteireiter: the hover tint is the only thing that shows them)');
    T::eq(gkCssDeclFor('.gk-tab-nav-karte.gk-tab-nav-secondary .gk-tab-btn:hover')['background'] ?? '', 'var(--gk-state-hover)', 'the hover of the second row is not the faint tint');
    T::eq(gkCssDeclFor('.gk-tab-nav-karte.gk-tab-nav-secondary .gk-tab-btn:focus-visible')['outline-offset'] ?? '', '-2px', 'the focus ring of the second row lies outside, where a scrolling row cuts it');
    $on = gkCssDeclFor('.gk-tab-nav-karte.gk-tab-nav-secondary .gk-tab-btn.gk-active');
    T::eq($on, gkCssDeclFor('.gk-tab-nav-karte.gk-tab-nav-secondary .gk-tab-btn[aria-current="page"]'), 'a link tab of the second row looks different from .gk-active');
    T::eq([$on['border-bottom-color'] ?? '', $on['color'] ?? '', $on['background'] ?? '', $on['font-weight'] ?? '', $on['box-shadow'] ?? ''],
        ['var(--gk-primary)', 'var(--gk-primary-text)', 'transparent', '600', 'none'], 'the chosen tab of the second row is not the main colour, a stroke and bold — or it carries the accent of the first row');
    // The levels never look alike: the first row is framed and grey, the second is neither.
    $erste = gkCssDeclFor('.gk-tab-nav-karte .gk-tab-btn');
    T::ok(($erste['border'] ?? '') !== ($off['border'] ?? '') && ($erste['background'] ?? '') !== ($off['background'] ?? ''), 'the two levels of tabs look alike');
    // On a phone and in the fitted grid the second row stays a line row: one word on its own line per cell, no frame.
    T::contains($media('(max-width: 600px)'), '.gk-tab-nav-karte.gk-tab-nav-secondary .gk-tab-btn { border-width: 0 0 1px;', 'on a phone the cells of the second row are boxes again');
    T::contains($media('(max-width: 600px)'), '.gk-tab-nav-karte.gk-tab-nav-secondary .gk-tab-btn[aria-current="page"] { border-bottom-width: 3px; }', 'on a phone the chosen cell of the second row has no stroke');
},

'the corners of the Karteireiter are ONE token (--gk-tab-radius, 4px): tab, surface and cells; none of them rounds like a button' => function (): void {
    T::ok((bool) preg_match('/--gk-tab-radius:\s*4px;/', gkCssBare()), 'the token --gk-tab-radius is not 4px');
    T::ok((int) preg_match('/--gk-radius-sm:\s*8px;/', gkCssBare()) === 1 && (int) preg_match('/--gk-radius:\s*12px;/', gkCssBare()) === 1, 'the radii of the other components changed');
    T::eq(gkCssDeclFor('.gk-tab-nav-karte')['--gk-tab-radius'] ?? '', '', 'the row sets the token itself, so a consumer cannot change it on an ancestor');
    $n = 0;
    foreach (array_merge(gkCssRulesNaming('gk-tab-nav-karte'), gkCssRulesNaming('gk-tab-flaeche')) as [$list, $body]) {
        $d = gkCssDecl($body);
        if (!isset($d['border-radius'])) continue;
        $n++;
        T::contains($d['border-radius'], '--gk-tab-radius', "$list rounds with something else than the token");
        T::notContains($d['border-radius'], '--gk-radius', "$list rounds like a button or a card");
    }
    T::ok($n >= 6, 'the corner rules of the Karteireiter are gone (found ' . $n . ')');
    T::eq(gkCssDeclFor('.gk-tab-nav-karte ~ .gk-tab-flaeche')['border-radius'] ?? '', '0 var(--gk-tab-radius) var(--gk-tab-radius) var(--gk-tab-radius)', 'the corner under the chosen tab is not square, or the others differ from the tabs');
    T::eq(gkCssDeclFor('.gk-tab-nav-karte .gk-tab-btn')['border-radius'] ?? '', 'var(--gk-tab-radius) var(--gk-tab-radius) 0 0', 'the top corners of a tab differ from those of the surface');
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


/* ── 1.102.0: eine Reihe, die nicht in ihren Kasten passt; das Fehler-Etikett; gleich hohe Zellen ── */

'a Karteireiter row that does not fit its box becomes the phone grid by a class; a row that fits stays the line of 1.101.0' => function () use ($media): void {
    // The line of 1.101.0, held literally: the bottoms stay level, the tabs stay one line.
    $line = gkCssDeclFor('.gk-tab-nav.gk-tab-nav-karte');
    T::eq([$line['align-items'] ?? '', $line['gap'] ?? '', $line['padding'] ?? ''], ['flex-end', '4px', '0 12px'], 'the single-line Karteireiter row changed');
    T::eq(gkCssDeclFor('.gk-tab-nav-karte .gk-tab-btn')['white-space'] ?? '', 'nowrap', 'a tab of the single-line row breaks inside');
    // The class: a grid at any window width, as weak as one class so a hiding class of the page still wins.
    T::eq(gkCssDeclFor('.gk-tab-nav-karte:where(.gk-tab-nav-raster)')['display'] ?? '', 'grid', 'the class does not turn the row into a grid (and it must stay as weak as one class)');
    $nav = gkCssDeclFor('.gk-tab-nav.gk-tab-nav-karte.gk-tab-nav-raster');
    T::eq($nav['grid-template-columns'] ?? '', 'repeat(auto-fit, minmax(min(150px, calc(50% - 3px)), 1fr))', 'the grid is not "as many as fit, at least two" (a box under 306px has two columns)');
    T::eq([$nav['align-items'] ?? '', $nav['gap'] ?? '', $nav['padding'] ?? '', $nav['border-bottom'] ?? ''], ['stretch', '6px', '0', '0'], 'the grid row keeps a line, a padding or cells that do not stretch');
    // The cell is the cell of the phone — one shape, held in two places, so the two are compared.
    T::ok((bool) preg_match('/\.gk-tab-nav-karte \.gk-tab-btn,\s*\.gk-tab-nav-karte\.gk-tab-nav-secondary \.gk-tab-btn\s*\{([^}]*)\}/', $media('(max-width: 600px)'), $ph), 'the cell of the phone grid is gone');
    T::eq(gkCssDeclFor('.gk-tab-nav-karte.gk-tab-nav-raster .gk-tab-btn'), gkCssDecl($ph[1]), 'a cell of the fitted grid differs from a cell of the phone grid');
    T::eq(gkCssDeclFor('.gk-tab-nav-karte.gk-tab-nav-raster .gk-tab-btn.gk-active'), gkCssDeclFor('.gk-tab-nav-karte.gk-tab-nav-raster .gk-tab-btn[aria-current="page"]'), 'a link tab in the fitted grid looks different from .gk-active');
    $an = gkCssDeclFor('.gk-tab-nav-karte.gk-tab-nav-secondary.gk-tab-nav-raster .gk-tab-btn.gk-active');
    T::eq([$an['border-bottom-width'] ?? '', $an['border-bottom-color'] ?? ''], ['3px', 'var(--gk-primary)'], 'the chosen cell of a fitted second row has no stroke in the main colour');
    T::eq(gkCssDeclFor('.gk-tab-nav-karte.gk-tab-nav-secondary.gk-tab-nav-raster .gk-tab-btn')['border-width'] ?? '', '0 0 1px', 'a cell of a fitted second row is framed');
    // Nothing here reaches a tab row that did not order the Karteireiter shape.
    $rules = gkCssRulesNaming('gk-tab-nav-raster');
    T::ok(count($rules) >= 6, 'the rules of the fitted grid are gone');
    foreach ($rules as [$list]) {
        foreach (array_map('trim', preg_split('/,(?![^()]*\))/', $list)) as $sel) {
            T::contains($sel, 'gk-tab-nav-karte', "$sel reaches tab rows that did not order the Karteireiter");
        }
    }
    // The same specificity as the second-row rules: the later one wins, so the grid comes after them.
    $css = gkCssBare();
    T::ok(strpos($css, '.gk-tab-nav.gk-tab-nav-karte.gk-tab-nav-raster {') > strpos($css, '.gk-tab-nav.gk-tab-nav-karte.gk-tab-nav-secondary {'), 'the fitted grid lies before the second row and loses its padding and line to it');
    T::ok(strpos($css, '.gk-tab-nav-karte.gk-tab-nav-raster .gk-tab-btn {') > strpos($css, '.gk-tab-nav-karte.gk-tab-nav-secondary .gk-tab-btn {'), 'the cell of the fitted grid lies before the cell of the second row');
},

// 1.104.0 — Martin, 31.10.2026 (iPhone): the second row of three tabs took two rows, four main tabs a 118px grid.
'Karteireiter on a phone can stand flat and in one row: opt-in, phone only, as strong as the grid (1.104.0)' => function () use ($media): void {
    $phone = $media('(max-width: 600px)');
    $css = gkCssBare();
    // One row: a single row of equal cells, however many tabs.
    T::ok((bool) preg_match('/\.gk-tab-nav\.gk-tab-nav-karte\.gk-tab-nav-einzeilig\s*\{([^}]*)\}/', $phone, $e), 'the one-row class is not inside @media (max-width: 600px) (it must not reach the computer)');
    $one = gkCssDecl($e[1] ?? '');
    T::eq([$one['grid-template-columns'] ?? '', $one['grid-auto-flow'] ?? '', $one['grid-auto-columns'] ?? ''], ['none', 'column', 'minmax(0, 1fr)'], 'the one-row class is not "one row of equal cells"');
    // Flat: icon beside the word, a cell as high as a tap target, in the cell and in the chosen cell.
    foreach (['.gk-tab-nav-karte.gk-tab-nav-flach .gk-tab-btn', '.gk-tab-nav-karte.gk-tab-nav-flach .gk-tab-btn.gk-active', '.gk-tab-nav-karte.gk-tab-nav-flach .gk-tab-btn[aria-current="page"]'] as $sel) {
        T::ok(str_contains($phone, $sel), "$sel is not inside @media (max-width: 600px)");
    }
    T::ok((bool) preg_match('/\.gk-tab-nav-karte\.gk-tab-nav-flach \.gk-tab-btn,[^{}]*\{([^}]*)\}/', $phone, $f), 'the cell rule of the flat class is gone');
    $flat = gkCssDecl($f[1] ?? '');
    T::eq([$flat['flex-direction'] ?? '', $flat['align-items'] ?? '', $flat['min-height'] ?? '', $flat['padding-block'] ?? ''], ['row', 'center', 'var(--gk-target-min)', '6px'], 'the flat class is not "icon beside the word, 44px high"');
    // Opt-in: every rule that names either class also names the Karteireiter shape; nothing reaches a row that did not order them.
    $rules = array_merge(gkCssRulesNaming('gk-tab-nav-einzeilig'), gkCssRulesNaming('gk-tab-nav-flach'));
    T::ok(count($rules) >= 2, 'the rules of the flat and one-row classes are gone');
    foreach ($rules as [$list]) {
        foreach (array_map('trim', explode(',', $list)) as $sel) {
            T::contains($sel, 'gk-tab-nav-karte', "$sel reaches tab rows that did not order the Karteireiter");
        }
    }
    // Three classes like the rows of the grid, so the order decides: the one-row rule comes after the second row (two columns up to 480px) and after the fitted grid.
    $at = strpos($css, '.gk-tab-nav.gk-tab-nav-karte.gk-tab-nav-einzeilig {');
    T::ok($at > strrpos($css, '.gk-tab-nav.gk-tab-nav-karte.gk-tab-nav-secondary { grid-template-columns: repeat(2'), 'the one-row rule is before the two columns of the second row: it would lose');
    T::ok($at > strpos($css, '.gk-tab-nav.gk-tab-nav-karte.gk-tab-nav-raster {'), 'the one-row rule is before the fitted grid: it would lose');
},

'the cells of a grid are equally tall, also with a badge or a count (the phone and the fitted grid)' => function () use ($media): void {
    $line = gkCssDeclFor('.gk-tab-nav.gk-tab-nav-karte');
    T::eq($line['grid-auto-rows'] ?? '', '1fr', 'the rows of the grid are not equal in height');
    // The row aligned its cells at the bottom (flex-end): in a grid a cell with a badge was taller than one without.
    T::contains($media('(max-width: 600px)'), '.gk-tab-nav.gk-tab-nav-karte { align-items: stretch; }', 'on a phone the cells do not stretch to the height of their row');
    T::eq(gkCssDeclFor('.gk-tab-nav.gk-tab-nav-karte.gk-tab-nav-raster')['align-items'] ?? '', 'stretch', 'in the fitted grid the cells do not stretch');
    T::eq(gkCssDeclFor('.gk-tab-nav-karte .gk-tab-btn')['min-height'] ?? '', 'var(--gk-target-min)', 'a cell is smaller than a finger');
},

'the error badge is the tonal error pair, comes after the plain badge, and the demo and the skill keep the word' => function (): void {
    $e = gkCssDeclFor('.gk-tab-badge-error');
    T::eq([$e['background'] ?? '', $e['color'] ?? ''], ['var(--gk-error-container)', 'var(--gk-on-error-container)'], 'the error badge is not the tonal error pair');
    $red = gkCssDeclFor('.gk-label-red');
    T::eq([$e['background'] ?? '', $e['color'] ?? ''], [$red['background'] ?? '', $red['color'] ?? ''], 'the error badge differs from the red label in its roles');
    $css = gkCssBare();
    T::ok(strpos($css, '.gk-tab-badge-error {') > strpos($css, ".gk-tab-badge {\n"), 'the error badge lies before the plain badge and loses to it (same specificity)');
    T::ok(!isset($e['font-size']) && !isset($e['padding']), 'the error variant restates the shape of the badge instead of only changing its colour');
    $demo = (string) file_get_contents(__DIR__ . '/../demo/index.php');
    T::contains($demo, 'class="gk-tab-badge gk-tab-badge-error">Error</span>', 'the demo shows no error badge with its word');
    $skill = (string) preg_replace('/\s+/', ' ', (string) file_get_contents(__DIR__ . '/../GRIDKIT_SKILL.md'));   // lines wrap
    foreach (['gk-tab-badge-error', 'gk-tab-nav-raster', 'GK.tabsFit', 'the word stays'] as $k) {
        T::contains($skill, $k, "the skill does not say $k");
    }
},

'GK.tabsFit puts the grid on a row that overflows and takes it off when it fits; new content is covered' => function (): void {
    $js = (string) file_get_contents(__DIR__ . '/../js/gridkit.js');
    $from = strpos($js, 'GK.tabsFit = {');
    T::ok($from !== false, 'GK.tabsFit is gone');
    $code = substr($js, (int) $from, (int) strpos($js, '// === AJAX PAGINATION ===', (int) $from) - (int) $from);
    T::contains($code, 'cls: "gk-tab-nav-raster"', 'tabsFit does not set the grid class');
    T::ok((bool) preg_match('/nav\.classList\.remove\(this\.cls\);\s*if \(nav\.scrollWidth > nav\.clientWidth\) nav\.classList\.add\(this\.cls\);/', $code),
        'tabsFit does not measure the row as a row (class off, overflow decides, class on)');
    T::contains($code, '.gk-tab-nav-karte', 'tabsFit looks at other rows than the Karteireiter');
    T::contains($code, 'new ResizeObserver', 'a row that changes size (a font arrives, a badge appears) is not measured again');
    T::contains($code, 'requestAnimationFrame', 'the re-measure is not put off one frame (ResizeObserver loop error)');
    T::contains($code, 'nav._gkTabsFit', 'a row is watched twice');
    T::contains($js, "if (GK.tabsFit) GK.tabsFit.init(root);", 'content loaded later is not measured (GK.initContent)');
},

'GK.tabsScroll: link tabs keep their place — guarded storage, the exceptions, the opt-out, started once' => function (): void {
    $js = (string) file_get_contents(__DIR__ . '/../js/gridkit.js');
    $from = strpos($js, 'GK.tabsScroll = {');
    T::ok($from !== false, 'GK.tabsScroll is gone');
    $code = substr($js, (int) $from, (int) strpos($js, '// === AJAX PAGINATION ===', (int) $from) - (int) $from);
    T::ok(substr_count($code, 'sessionStorage') === 3, 'sessionStorage is used outside the three guarded places');
    T::ok(substr_count($code, 'try {') === 3 && substr_count($code, 'catch (e)') === 3, 'a storage or scroll call is not in try/catch');
    foreach (['maxAge: 15000,', 'location.hash', 'm.url !== location.pathname + location.search', 'nav.type !== "navigate"',
        'data-gk-tab-scroll="off"', 'e.ctrlKey', 'e.metaKey', 'e.shiftKey', 'e.altKey', 'e.button !== 0', 'e.defaultPrevented',
        'href.indexOf("#") !== -1', 'link.origin !== location.origin', 'behavior: "instant"'] as $part) {
        T::contains($code, $part, 'tabsScroll lost its guard: ' . $part);
    }
    T::contains($js, 'if (GK.tabsScroll) GK.tabsScroll.restore();', 'the page does not restore the place at start (GK.init)');
    T::ok(substr_count($js, 'GK.tabsScroll.restore()') === 1, 'the place is restored more than once');
    T::ok(is_int(strpos($code, 'removeItem')), 'the note is not deleted after it is read');
},

// ── Reiter-Varianten (1.103.0) ───────────────────────────────────────────

'tab variants: three styles beside the Karteireiter, four sides, six options — each a class, the bare row untouched' => function () use ($gkTabVariantsFn): void {
    $sec = $gkTabVariantsFn();
    foreach (['linie', 'kapsel', 'text', 'links', 'rechts', 'unten', 'fest', 'sm', 'lg', 'fluid', 'icon-oben', 'nur-icon', 'neutral'] as $name) {
        T::contains($sec, '.gk-tab-nav-' . $name, "the class gk-tab-nav-$name has no rule in the variants section");
    }
    $bare = gkCssDeclFor('.gk-tab-nav');
    T::eq($bare['border-bottom'] ?? '', '2px solid var(--gk-border)', 'the bare row (no variant class) lost its line');
    T::eq($bare['margin-bottom'] ?? '', '20px', 'the bare row lost its space under it');
    T::eq(gkCssDeclFor('.gk-tab-btn.gk-active')['border-bottom-color'] ?? '', 'var(--gk-primary)', 'the bare active tab lost its line');
},

'tab variants speak tokens: no colour or size literal for a colour, the stroke is an inset shadow steered by --gk-tab-sx/-sy' => function () use ($gkTabVariantsFn): void {
    $sec = $gkTabVariantsFn();
    T::ok(!preg_match('/#[0-9a-fA-F]{3,8}\b|\brgba?\(|\bhsla?\(|\boklch\(|\boklab\(/', $sec), 'the variants section names a colour literal — every colour is a token');
    T::contains($sec, 'box-shadow: inset calc(var(--gk-tab-sx) * var(--gk-tab-linie)) calc(var(--gk-tab-sy) * var(--gk-tab-linie)) 0 0 var(--gk-tab-akzent)', 'the stroke of the chosen tab is not steered by --gk-tab-sx/-sy');
    T::contains($sec, 'box-shadow: inset calc(var(--gk-tab-sx) * 1px) calc(var(--gk-tab-sy) * 1px) 0 0 var(--gk-tab-kante)', 'the track of the line style is not steered by --gk-tab-sx/-sy');
    T::ok((bool) preg_match('/:where\(\.gk-tab-nav-linie, \.gk-tab-nav-kapsel, \.gk-tab-nav-text\)\s*\{[^}]*--gk-tab-sx: 0;[^}]*--gk-tab-sy: -1;/', $sec), 'the top side (stroke below) is not the default');
    foreach ([':where(.gk-tab-nav-unten)' => ['0', '1']] as $sel => [$x, $y]) {
        T::ok((bool) preg_match('/' . preg_quote($sel, '/') . '\s*\{[^}]*--gk-tab-sx: ' . $x . ';[^}]*--gk-tab-sy: ' . $y . ';/', $sec), "$sel does not put the stroke on the top");
    }
    T::ok((bool) preg_match('/\.gk-tab-nav-links \{ --gk-tab-sx: -1; --gk-tab-sy: 0; \}/', $sec), 'the left side does not put the stroke on the right');
    T::ok((bool) preg_match('/\.gk-tab-nav-rechts \{ --gk-tab-sx: 1; --gk-tab-sy: 0; \}/', $sec), 'the right side does not put the stroke on the left');
    T::ok((bool) preg_match('/:where\(\.gk-tab-nav-text\)\s*\{\s*--gk-tab-linie: 2px;/', $sec), 'the text style has no thinner line');
    T::contains($sec, '--gk-tab-akzent-auf: var(--gk-surface)', 'the text on a filled tab is not the surface colour (on-primary measured 4.37:1 in dark)');
    T::ok((bool) preg_match('/\.gk-tab-nav-kapsel \.gk-tab-btn:is\(\.gk-active, \[aria-current="page"\]\)\s*\{\s*background: var\(--gk-tab-akzent-text\);\s*color: var\(--gk-tab-akzent-auf\)/', $sec), 'the filled tab is not the text-safe main colour with its own text colour');
},

'tab variants: a side row is a column only from 601px, a phone gets the horizontal row; every new style has 44px targets there' => function () use ($media, $gkTabVariantsFn): void {
    $sec = $gkTabVariantsFn();
    $wide = $media('(min-width: 601px)');
    foreach (['.gk-tab-nav.gk-tab-nav-links', 'flex-direction: column', 'fit-content(var(--gk-tab-seitenbreite))', '.gk-tabs:has(> .gk-tab-nav-links)', '.gk-tabs:has(> .gk-tab-nav-rechts)'] as $part) {
        T::contains($wide, $part, "the side row has lost a part of its 601px block: $part");
    }
    // outside the media blocks the side rows set no column and no grid
    $outside = preg_replace('/@media[^{]*\{(?:[^{}]|\{[^{}]*\})*\}/', '', $sec);
    T::notContains((string) $outside, 'fit-content(', 'a side row builds its grid below 601px — a phone must get the horizontal row');
    T::notContains((string) $outside, 'aria-orientation', 'nothing in the stylesheet should know the orientation');
    T::ok(!preg_match('/\.gk-tab-nav\.gk-tab-nav-links\s*[,{][^}]*flex-direction/', (string) $outside), 'a side row is a column below 601px');
    T::contains($media('(max-width: 600px)'), '.gk-tab-nav:is(.gk-tab-nav-linie, .gk-tab-nav-kapsel, .gk-tab-nav-text) .gk-tab-btn { min-height: var(--gk-target-min); }', 'a tab of a new style is smaller than a finger on a phone');
},

'tab variants: the bottom bar sticks, keeps clear of the home bar and shares the width; icon-only keeps its word readable' => function () use ($gkTabVariantsFn): void {
    $fest = gkCssDeclFor('.gk-tab-nav.gk-tab-nav-fest');
    T::eq($fest['position'] ?? '', 'sticky', 'the bottom bar does not stick');
    T::eq($fest['bottom'] ?? '', '0', 'the bottom bar does not sit on the lower edge');
    T::eq($fest['padding-bottom'] ?? '', 'env(safe-area-inset-bottom, 0px)', 'the bottom bar ignores the home bar of a phone');
    $tab = gkCssDeclFor('.gk-tab-nav.gk-tab-nav-fest .gk-tab-btn');
    T::eq($tab['flex-direction'] ?? '', 'column', 'the symbol of a bottom tab is not above the word');
    T::eq($tab['flex'] ?? '', '1 1 0', 'the bottom tabs do not share the width');
    T::ok((int) ($tab['min-height'] ?? 0) >= 44, 'a bottom tab is smaller than a finger');
    $sec = $gkTabVariantsFn();
    T::contains($sec, 'clip-path: inset(50%)', 'icon-only hides the word by taking it out of the tree instead of clipping it');
    T::ok(!preg_match('/\.gk-tab-nav-nur-icon[^{]*\{[^}]*display:\s*none/', $sec), 'icon-only hides the word with display: none — a screen reader loses it');
    T::eq(gkCssDeclFor('.gk-tab-nav.gk-tab-nav-nur-icon .gk-tab-btn')['min-width'] ?? '', 'var(--gk-target-min)', 'an icon-only tab is narrower than a finger');
},

'tab variants: a disabled tab is dimmed, shows no pointer and no hover tint — in every style' => function () use ($gkTabVariantsFn): void {
    $sec = $gkTabVariantsFn();
    T::ok((bool) preg_match('/\.gk-tab-btn:is\(:disabled, \[aria-disabled="true"\]\)\s*\{\s*opacity: 0\.45;\s*cursor: not-allowed;/', $sec), 'a disabled tab looks like the others');
    T::contains($sec, ':is(:disabled, [aria-disabled="true"]):hover:not(.gk-active)', 'a disabled tab still takes a hover tint');
    T::contains($sec, '.gk-tab-nav-karte, .gk-tab-nav-linie, .gk-tab-nav-kapsel, .gk-tab-nav-text', 'the Karteireiter has no disabled look');
},

'GK.tabsMarkup 1.103: orientation from the layout, arrows by axis, disabled tabs stepped over, a side row is not a grid' => function (): void {
    $js = (string) file_get_contents(__DIR__ . '/../js/gridkit.js');
    $from = strpos($js, 'GK.tabsMarkup = {');
    T::ok($from !== false, 'GK.tabsMarkup is gone');
    $code = substr($js, (int) $from, (int) strpos($js, 'GK.tabsFit = {', (int) $from) - (int) $from);
    foreach (['getComputedStyle(nav).flexDirection === "column"', 'aria-orientation', 'new ResizeObserver(orient)', '"ArrowDown"', '"ArrowUp"', '"ArrowRight"', '"ArrowLeft"',
        'btns[j].disabled', 'aria-disabled', 'step(-1, 1)', 'step(btns.length, -1)'] as $part) {
        T::contains($code, $part, 'tabsMarkup lost a part of the orientation: ' . $part);
    }
    T::contains($js, '.gk-tab-nav-karte:not(.gk-tab-nav-links):not(.gk-tab-nav-rechts)', 'a side Karteireiter row would be measured as a row (and become a grid)');
},
];
})();
