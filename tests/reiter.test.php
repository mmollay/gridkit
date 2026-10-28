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

];
})();
