<?php
/**
 * A small message: `.gk-message-sm` (1.95.0).
 *
 * Why it exists: the SSI Panel put a privacy sentence above its chat as a
 * `.gk-field-hint` — 12px grey text that read like body copy. A `.gk-message`
 * has the look such a sentence needs (tint, border, coloured symbol, a title),
 * but it is too heavy for that place: 14px 20px of padding and 16px of margin
 * underneath. `.gk-message-compact` already existed (10px 16px, 13px) and some
 * twenty Panel views use it, most of them counting on the 16px margin it
 * inherits from `.gk-message`. Taking that margin away from it would move every
 * one of them, so the small one is a class of its own and `-compact` is left
 * exactly as it was.
 *
 * What is held here is what can be read in the stylesheet: the box, its order
 * in the file, that it paints no colour of its own, that the old classes did not
 * move, and the dark text of each tone on its tint (computed, not pinned). What
 * a browser has to show — the box on a phone, a long word that breaks, a margin
 * utility that still wins, the empty announcement — is in ci/browser.js; the
 * colours of all five tones in every theme, light and dark, are in ci/farben.js.
 */

declare(strict_types=1);

/** @return array<string,callable> */
return (static function (): array {

$root = __DIR__ . '/..';
$css  = static fn (): string => (string) file_get_contents($root . '/css/gridkit.css');

/** The body of the first top-level rule whose selector list is exactly $selector. */
$rule = static fn (string $css, string $selector): string =>
    preg_match('/(?:^|\n)' . preg_quote($selector, '/') . '\s*\{([^}]*)\}/', $css, $m) ? $m[1] : '';

/** "a: b; c: d" => ['a' => 'b', 'c' => 'd'] */
$decl = static function (string $body): array {
    $out = [];
    foreach (explode(';', $body) as $d) {
        if (str_contains($d, ':')) {
            [$p, $v] = explode(':', $d, 2);
            $out[trim($p)] = trim($v);
        }
    }
    return $out;
};

/** A length token of :root as a number of pixels: --gk-text-label => 13. */
$px = static fn (string $css, string $token): float =>
    preg_match('/--' . preg_quote($token, '/') . ':\s*([0-9.]+)px/', $css, $m) ? (float) $m[1] : -1.0;

/** The WCAG ratio, as in contrast.test.php (a copy: that file declares its functions unguarded). */
$ratio = static function (string $a, string $b): float {
    $lum = static function (string $hex): float {
        $h = ltrim($hex, '#');
        $ch = static fn (int $v): float => ($c = $v / 255) <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        return 0.2126 * $ch((int) hexdec(substr($h, 0, 2))) + 0.7152 * $ch((int) hexdec(substr($h, 2, 2)))
             + 0.0722 * $ch((int) hexdec(substr($h, 4, 2)));
    };
    [$la, $lb] = [$lum($a), $lum($b)];
    return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
};

return [

// ── The box ───────────────────────────────────────────────────────────────

'a small message is denser than a message and than a compact one, and leaves the room outside to its surroundings' => function () use ($css, $rule, $decl, $px): void {
    $c = $css();
    $sm = $decl($rule($c, '.gk-message-sm'));
    T::ok($sm !== [], '.gk-message-sm has no rule');
    T::eq($sm['margin'] ?? '', '0', 'a small message keeps the 16px margin-bottom of .gk-message — its surroundings set the spacing');
    T::eq($sm['padding'] ?? '', '10px 14px', 'a small message does not have the padding of 10px 14px');
    T::eq($sm['gap'] ?? '', '10px', 'symbol and text are not 10px apart');
    T::eq($sm['font-size'] ?? '', 'var(--gk-text-label)', 'a small message does not set its text in the label size');
    T::eq($sm['line-height'] ?? '', '1.45', 'the line height of a small message is not 1.45');
    T::eq($sm['overflow-wrap'] ?? '', 'anywhere', 'a long address in a small message cannot break — it pushes out of the box');

    // Denser than what exists — read from the rules, not written down twice.
    $base = $decl($rule($c, '.gk-message'));
    $compact = $decl($rule($c, '.gk-message-compact'));
    T::eq($base['margin-bottom'] ?? '', '16px', 'the case this guards against is gone: .gk-message has no 16px margin — check the test');
    [$bv, $bh] = array_map('intval', explode(' ', $base['padding'] ?? '0 0'));
    [$cv, $ch] = array_map('intval', explode(' ', $compact['padding'] ?? '0 0'));
    [$sv, $sh] = array_map('intval', explode(' ', $sm['padding'] ?? '99 99'));
    T::ok($sv <= $cv && $sh < $ch, "padding $sv/$sh is not tighter than the compact message ($cv/$ch)");
    T::ok($sv < $bv && $sh < $bh, "padding $sv/$sh is not tighter than the message ($bv/$bh)");
    T::ok((int) ($sm['gap'] ?? 99) < (int) ($base['gap'] ?? 0), 'the gap is not smaller than the message\'s');

    // Smaller than body text and still readable: a token, between 12px and the body size.
    $size = $px($c, 'gk-text-label');
    T::ok($size >= 12.0 && $size < $px($c, 'gk-text-body'), "the text of a small message is {$size}px — it has to stay between 12px and the body size");
},

'its symbol, its title and its text are sized for the small box' => function () use ($css, $rule, $decl, $px): void {
    $c = $css();
    $icon = $decl($rule($c, '.gk-message-sm .material-icons'));
    T::eq($icon['font-size'] ?? '', 'var(--gk-icon-sm)', 'the symbol of a small message is not one step down from the message\'s (--gk-icon-sm)');
    T::ok($px($c, 'gk-icon-sm') < $px($c, 'gk-icon-md'), 'the case this guards against is gone: --gk-icon-sm is not smaller than --gk-icon-md');
    T::eq($decl($rule($c, '.gk-message .material-icons'))['font-size'] ?? '', 'var(--gk-icon-md)', 'the symbol of the message moved — a small message is measured against it');

    $title = $decl($rule($c, '.gk-message-sm .gk-message-header'));
    T::eq($title['margin-bottom'] ?? '', '2px', 'the title of a small message does not sit 2px above its text');

    // The text may break: a flex item has min-width:auto, so a long word or address would push the box wider.
    $content = $decl($rule($c, '.gk-message-sm .gk-message-content'));
    T::eq($content['min-width'] ?? '', '0', 'the text of a small message cannot shrink below its longest word');
},

'it holds on a phone and still yields to the margin utilities — its place in the file' => function () use ($css): void {
    $c = $css();
    // The phone rule of .gk-message (12px 14px) has the same weight as the class: whichever comes later wins.
    $phone = strpos($c, "  .gk-message {\n    padding: 12px 14px;");
    $small = strpos($c, "\n.gk-message-sm {");
    $util  = strpos($c, "\n.gk-mb-0 { margin-bottom: 0; }");
    T::ok($phone !== false, 'the case this guards against is gone: .gk-message has no phone rule any more — check the test');
    T::ok($util !== false, 'the case this guards against is gone: .gk-mb-0 is not where the test looks — check the test');
    T::ok($small !== false, '.gk-message-sm has no rule');
    if ($phone === false || $small === false || $util === false) return;
    T::ok($small > $phone, 'the rule stands before the phone rule of .gk-message — on a phone it would be padded 12px 14px again');
    T::ok($small < $util, 'the rule stands after the margin utilities — "gk-mb-3" on a small message would lose to margin: 0');
},

'it paints no colour of its own — the tone classes decide, light and dark' => function () use ($css): void {
    $c = (string) preg_replace('#/\*.*?\*/#s', '', $css());   // the comment above the rule names the class too
    preg_match_all('/(?:^|\n)([^{}\n]*\.gk-message-sm[^{}]*)\{([^}]*)\}/', $c, $rules, PREG_SET_ORDER);
    T::ok(count($rules) >= 4, 'found ' . count($rules) . ' rules of .gk-message-sm — the stylesheet was not read');
    foreach ($rules as [, $selector, $body]) {
        foreach (['color', 'background', 'background-color', 'border', 'border-color'] as $property) {
            T::ok(!preg_match('/(?:^|[;\s])' . preg_quote($property, '/') . '\s*:/', $body),
                trim($selector) . ' sets ' . $property . ' — the colours belong to .gk-message-info, -success, -warning and -error');
        }
    }
    // Nor does it need a dark rule: it carries the tone class, whose dark rule already applies.
    T::ok(!preg_match('/\[data-gk-mode="dark"\][^{}]*\.gk-message-sm|\.gk-dark [^{}]*\.gk-message-sm/', $c),
        'a dark rule of its own for .gk-message-sm — the tone class carries the dark colours');
},

// ── What must not move ────────────────────────────────────────────────────

'the message and the compact message are what they were' => function () use ($css, $rule, $decl): void {
    $c = $css();
    // The Panel's twenty compact messages count on this; so does every message.
    $base = $decl($rule($c, '.gk-message'));
    T::eq($base['padding'] ?? '', '14px 20px', '.gk-message changed its padding');
    T::eq($base['margin-bottom'] ?? '', '16px', '.gk-message changed its margin-bottom');
    T::eq($base['gap'] ?? '', '12px', '.gk-message changed its gap');
    T::eq($base['font-size'] ?? '', 'var(--gk-text-body)', '.gk-message changed its type size');
    T::eq($base['line-height'] ?? '', '1.5', '.gk-message changed its line height');
    $compact = $decl($rule($c, '.gk-message-compact'));
    T::eq($compact, ['padding' => '10px 16px', 'font-size' => 'var(--gk-text-label)'],
        '.gk-message-compact changed — it has to stay padding 10px 16px and the label size, margin from .gk-message');
    T::eq($decl($rule($c, '.gk-message-header'))['margin-bottom'] ?? '', '4px', 'the title of a message changed its margin');
},

// ── Colour: the dark text of each tone on its tint ────────────────────────

'the dark text of every tone reads 4.5:1 on its tint, over each dark ground a message sits on' => function () use ($css, $root, $ratio): void {
    $c = $css();
    $themes = (string) file_get_contents($root . '/css/themes.css');
    // The grounds: the dark surfaces of gridkit.css, and those themes.css puts over them under a theme.
    preg_match('/\n\[data-gk-mode="dark"\],\n\.gk-dark \{(.*?)\n\}/s', $c, $own);
    preg_match('/\n\[data-gk-mode="dark"\] \{(.*?)\n\}/s', $themes, $theirs);
    $grounds = [];
    foreach ([$own[1] ?? '', $theirs[1] ?? ''] as $block) {
        foreach (['surface', 'surface-container-low', 'surface-container', 'surface-container-high'] as $role) {
            if (preg_match('/--gk-' . $role . ':\s*(#[0-9a-fA-F]{6})/', $block, $m)) $grounds[strtolower($m[1])] = $role;
        }
    }
    T::ok(count($grounds) >= 5, 'found ' . count($grounds) . ' dark grounds — the dark blocks were not read');

    $tones = 0;
    foreach (['info', 'success', 'warning', 'error'] as $tone) {
        $found = preg_match('/\[data-gk-mode="dark"\] \.gk-message-' . $tone . ',\s*\n\.gk-dark \.gk-message-' . $tone
            . '\s*\{([^}]*)\}/', $c, $m);
        T::ok((bool) $found, "no dark rule for .gk-message-$tone");
        if (!$found) continue;
        $ok = preg_match('/background:\s*rgba\((\d+),\s*(\d+),\s*(\d+),\s*([0-9.]+)\)/', $m[1], $bg)
           && preg_match('/(?:^|[;\s])color:\s*(#[0-9a-fA-F]{6})/', $m[1], $fg);
        T::ok((bool) $ok, ".gk-message-$tone in dark mode is not an rgba tint with a hex text colour — extend the test");
        if (!$ok) continue;
        $tones++;
        foreach ($grounds as $ground => $role) {
            // The tint is laid over the ground it sits on, as the browser does.
            $mix = '#';
            foreach ([1, 2, 3] as $i) {
                $g = hexdec(substr($ground, 1 + ($i - 1) * 2, 2));
                $mix .= sprintf('%02x', (int) round((float) $bg[$i] * (float) $bg[4] + $g * (1 - (float) $bg[4])));
            }
            $r = $ratio($fg[1], $mix);
            T::ok($r >= 4.5, sprintf('%s text %s on its tint over %s (%s) is %.2f:1, WCAG AA asks 4.5:1', $tone, $fg[1], $ground, $role, $r));
        }
    }
    T::eq($tones, 4, 'not all four tones were measured');
},

];

})();
