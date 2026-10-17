<?php
/**
 * A timeline: `.gk-timeline` (1.96.0) — a log of steps, one under the other.
 *
 * Why it exists: the SSI Panel's "Shorts" module makes a short video in several
 * steps (script, voice, pictures, assembly) and shows the person who has to approve
 * the result what ran, in which order, what each step cost and where one broke.
 * Any admin that runs a pipeline needs that list; a Panel-only copy would be the
 * third hand-built "log" the family of systems carries.
 *
 * What is held here is what can be read in the stylesheet and the documented
 * markup: the list stays an ordered list, the five states are told apart by SHAPE
 * and by a WORD (never by colour alone), the colours are roles only — so light,
 * `[data-gk-mode="dark"]` and `.gk-dark` follow from the tokens with no dark rule of
 * its own — the text stays readable (computed from the tokens), long words break,
 * the running step is the only thing that moves and it stands still when asked.
 * What a browser has to show — the geometry, a phone, both dark spellings, the
 * animation itself — is in ci/browser-timeline-video.js; the colours of every text
 * in every theme and mode are in ci/farben.js.
 */

declare(strict_types=1);

require_once __DIR__ . '/css-helpers.php';

/** @return array<string,callable> */
return (static function (): array {

$root = __DIR__ . '/..';
$states = ['ok', 'failed', 'running', 'waiting', 'skipped'];

return [

// ── The list ─────────────────────────────────────────────────────────────

'a timeline is a plain list: no bullets, no margin of its own, the markers and the line do the drawing' => function (): void {
    $r = gkCssDecl(gkCssRule('.gk-timeline'));
    T::ok($r !== [], '.gk-timeline has no rule');
    T::eq($r['list-style'] ?? '', 'none', 'the list shows its own numbers or bullets next to the markers');
    T::eq($r['margin'] ?? '', '0', 'the list has the margin of an <ol>');
    T::eq($r['padding'] ?? '', '0', 'the list has the indent of an <ol>');
    $step = gkCssDecl(gkCssRule('.gk-timeline-step'));
    T::eq($step['display'] ?? '', 'grid', 'a step is not a grid of marker and body');
    T::contains($step['grid-template-columns'] ?? '', 'minmax(0, 1fr)', 'the body of a step cannot shrink below its longest word — a long address pushes the page wide');
    T::eq($step['position'] ?? '', 'relative', 'a step does not hold the line to the next one');
},

'the line runs from each marker to the next and stops at the last step' => function (): void {
    $line = gkCssDecl(gkCssRule('.gk-timeline-step:not(:last-child)::before'));
    T::ok($line !== [], 'there is no line between the steps');
    T::eq($line['content'] ?? '', '""', 'the line is not drawn (no content)');
    T::eq($line['position'] ?? '', 'absolute', 'the line is in the flow and moves the steps');
    T::eq($line['width'] ?? '', '2px', 'the line is not 2px wide');
    T::ok(str_contains($line['top'] ?? '', '--gk-timeline-marker'), 'the line does not start under the marker');
    T::ok(str_contains($line['left'] ?? '', '--gk-timeline-marker'), 'the line does not run through the middle of the markers');
    T::eq(gkCssDecl(gkCssRule('.gk-timeline-step:last-child'))['padding-bottom'] ?? '', '0', 'the last step keeps the space for a next one');
},

// ── The five states: a shape and a word each ────────────────────────────────

'every state draws its own symbol in the marker — five different shapes, none of them a colour' => function () use ($states): void {
    $shapes = [];
    // What every symbol shares (drawn, centred by inset 0 + margin auto) and what each state adds: a size and a shape.
    $symbol = static fn (string $s): array => array_replace(gkCssDeclFor('.gk-timeline-marker::before'), gkCssDeclFor(".gk-timeline-$s .gk-timeline-marker::before"));
    foreach ($states as $s) {
        $d = $symbol($s);
        T::ok(gkCssDeclFor(".gk-timeline-$s .gk-timeline-marker::before") !== [], "the state \"$s\" has no symbol (.gk-timeline-$s .gk-timeline-marker::before)");
        T::eq($d['position'] ?? '', 'absolute', "the symbol of \"$s\" is in the flow and moves the marker");
        T::eq([$d['inset'] ?? '', $d['margin'] ?? ''], ['0', 'auto'], "the symbol of \"$s\" is not centred in the disc (inset 0 + margin auto)");
        T::eq($d['content'] ?? '', '""', "the symbol of \"$s\" has no content: \"\" — it would not be drawn");
        T::ok(isset($d['width']) || isset($d['border-width']), "the symbol of \"$s\" has no size");
        // What can be told apart without colour: size, border sides, radius, turn, animation.
        $shapes[$s] = implode('|', array_map(static fn (string $p): string => $d[$p] ?? '-',
            ['width', 'height', 'border', 'border-width', 'border-left', 'border-top', 'border-right-color', 'border-radius', 'transform', 'background']));
    }
    T::eq(count(array_unique($shapes)), count($states), 'two states draw the same symbol — they would differ by colour only: ' . json_encode($shapes));
    // The cross is two strokes: the second one is the ::after.
    $stroke = gkCssDeclFor('.gk-timeline-failed .gk-timeline-marker::after');
    T::ok(($stroke['content'] ?? '') === '""' && ($stroke['border-left'] ?? '') !== '' && ($stroke['transform'] ?? '') !== '', 'the cross of "failed" has only one stroke');
    T::ok(!isset($symbol('ok')['border-left']) && gkCssDeclFor('.gk-timeline-ok .gk-timeline-marker::after') === [], 'a state other than "failed" draws a second symbol');
    // The symbols are lines and borders in the colour of the text, not fills of a colour of their own: a
    // high-contrast mode keeps borders and repaints backgrounds, and a symbol must not turn into a blank disc.
    foreach ($states as $s) {
        $d = $symbol($s);
        T::ok(!isset($d['background']) || $d['background'] === 'currentColor', "the symbol of \"$s\" is a fill of its own colour, not a line in the colour of the text");
    }
},

'the state is also a word beside the title, in the colour of its tone — and a quiet one for waiting and skipped' => function (): void {
    $state = gkCssDecl(gkCssRule('.gk-timeline-state'));
    T::eq($state['color'] ?? '', 'var(--gk-on-surface-variant)', 'a step without a tone (waiting, skipped) does not speak in the quiet colour');
    T::eq($state['white-space'] ?? '', 'nowrap', 'the word of the state breaks in the middle');
    T::ok((int) ($state['font-weight'] ?? 0) >= 600, 'the word of the state is not set in a weight that reads as a status');
    foreach (['ok' => 'var(--gk-success-text)', 'failed' => 'var(--gk-danger-text)', 'running' => 'var(--gk-primary-text)'] as $s => $role) {
        T::eq(gkCssDecl(gkCssRule(".gk-timeline-$s .gk-timeline-state"))['color'] ?? '', $role,
            "the word of \"$s\" is not in $role — the text-safe variant, because the colour IS the text here");
    }
    foreach (['waiting', 'skipped'] as $s) {
        T::eq(gkCssDeclFor(".gk-timeline-$s .gk-timeline-title")['color'] ?? '', 'var(--gk-on-surface-variant)', "the title of \"$s\" is not set back");
    }
},

'the markers wear the container pair of their tone, so the symbol reads on its disc' => function (): void {
    $pairs = ['ok' => 'success', 'failed' => 'error', 'running' => 'primary'];
    foreach ($pairs as $s => $tone) {
        $d = gkCssDecl(gkCssRule(".gk-timeline-$s .gk-timeline-marker"));
        T::eq($d['background'] ?? '', "var(--gk-$tone-container)", "the disc of \"$s\" is not the $tone container");
        T::eq($d['color'] ?? '', "var(--gk-on-$tone-container)", "the symbol of \"$s\" is not the on-$tone-container colour");
    }
    // The pair is computed, light and dark: a symbol is not text, but it is the only thing the person sees first.
    foreach ($pairs as $tone) {
        foreach ([false, true] as $dark) {
            [$bg, $fg] = [gkCssRole("$tone-container", $dark), gkCssRole("on-$tone-container", $dark)];
            T::ok($bg !== '' && $fg !== '', "$tone container pair not found in the " . ($dark ? 'dark' : 'light') . ' block');
            if ($bg === '' || $fg === '') continue;
            $r = gkCssRatio($fg, $bg);
            T::ok($r >= 4.5, sprintf('%s symbol %s on %s is %.2f:1 in %s mode', $tone, $fg, $bg, $r, $dark ? 'dark' : 'light'));
        }
    }
},

'the word of each state reads 4.5:1 on the surfaces a timeline sits on, light and dark' => function (): void {
    $text = ['success-text', 'danger-text', 'primary-text', 'on-surface-variant', 'on-surface'];
    foreach ([false, true] as $dark) {
        $grounds = [];
        foreach (['surface', 'surface-container'] as $g) {
            if (($hex = gkCssRole($g, $dark)) !== '') $grounds[$g] = $hex;
        }
        T::eq(count($grounds), 2, 'the surfaces were not read in the ' . ($dark ? 'dark' : 'light') . ' block');
        foreach ($text as $role) {
            $fg = gkCssRole($role, $dark);
            T::ok($fg !== '', "--gk-$role is not a plain colour in the " . ($dark ? 'dark' : 'light') . ' block');
            foreach ($grounds as $g => $bg) {
                if ($fg === '') continue;
                $r = gkCssRatio($fg, $bg);
                T::ok($r >= 4.5, sprintf('--gk-%s %s on --gk-%s %s is %.2f:1 in %s mode, WCAG AA asks 4.5:1', $role, $fg, $g, $bg, $r, $dark ? 'dark' : 'light'));
            }
        }
    }
},

// ── Colour: roles only ─────────────────────────────────────────────────────

'a timeline paints with roles only and has no dark rule of its own — light and both dark spellings follow the tokens' => function (): void {
    $rules = gkCssRulesNaming('.gk-timeline');
    T::ok(count($rules) >= 20, 'found ' . count($rules) . ' rules of .gk-timeline — the stylesheet was not read');
    foreach ($rules as [$selector, $body]) {
        T::ok(!preg_match('/#[0-9a-fA-F]{3,8}\b|rgba?\(|hsla?\(|oklch\(/', $body), "$selector paints with a literal colour — a role (--gk-*) follows the theme and the mode");
        T::ok(!preg_match('/data-gk-mode|\.gk-dark/', $selector), "$selector is a dark rule — the tokens already change in dark");
    }
},

// ── The error ───────────────────────────────────────────────────────────────

'a failed step shows its message as a block that cannot be missed' => function (): void {
    $e = gkCssDecl(gkCssRule('.gk-timeline-error'));
    T::ok($e !== [], '.gk-timeline-error has no rule');
    T::eq($e['background'] ?? '', 'var(--gk-error-container)', 'the message has no ground in the error container');
    T::eq($e['color'] ?? '', 'var(--gk-on-error-container)', 'the message is not in the on-error-container text');
    T::ok(str_contains($e['border'] ?? '', 'var(--gk-error)'), 'the message has no border in the error colour');
    T::ok((int) filter_var($e['border-left-width'] ?? '0', FILTER_SANITIZE_NUMBER_INT) >= 3, 'the message has no thick edge on the left — a stripe a glance finds');
    T::ok((int) ($e['font-weight'] ?? 0) >= 500, 'the message is set in the weight of the body text — it should stand out');
    T::eq($e['overflow-wrap'] ?? '', 'anywhere', 'a long path in the message cannot break');
    foreach ([false, true] as $dark) {
        [$bg, $fg] = [gkCssRole('error-container', $dark), gkCssRole('on-error-container', $dark)];
        if ($bg === '' || $fg === '') { T::ok(false, 'error container pair not found'); continue; }
        $r = gkCssRatio($fg, $bg);
        T::ok($r >= 4.5, sprintf('the message %s on %s is %.2f:1 in %s mode', $fg, $bg, $r, $dark ? 'dark' : 'light'));
    }
},

// ── Words break, the page does not widen ───────────────────────────────────

'long words break in the title, the detail, the message and the chips — and the column cannot be pushed wider' => function (): void {
    // The grid track is minmax(0, 1fr) (held in the first test): that, and not a min-width on the body, is what keeps the column.
    T::eq(gkCssDecl(gkCssRule('.gk-timeline-body'))['padding-top'] ?? '', '4px', 'the title is not level with the middle of the marker (the body starts 4px down)');
    T::ok(!isset(gkCssDecl(gkCssRule('.gk-timeline-head'))['padding-top']), 'the head carries the 4px as well — the first row would stand 8px down');
    foreach (['title', 'detail', 'error'] as $part) {
        T::eq(gkCssDecl(gkCssRule(".gk-timeline-$part"))['overflow-wrap'] ?? '', 'anywhere', ".gk-timeline-$part cannot break a long address");
    }
    $chips = gkCssDecl(gkCssRule('.gk-timeline-chips'));
    T::eq($chips['display'] ?? '', 'flex', 'chips do not sit in a row');
    T::eq($chips['flex-wrap'] ?? '', 'wrap', 'chips do not wrap onto a second row on a phone');
    $label = gkCssDecl(gkCssRule('.gk-timeline-chips .gk-label'));
    T::eq($label['white-space'] ?? '', 'normal', 'a long chip (a provider with a long name) keeps .gk-label\'s nowrap and pushes out of the box');
    // The label it reuses is the one GridKit has; nothing is invented.
    T::contains(gkCssText(), "\n.gk-label {", 'the chip is a .gk-label — and .gk-label is gone');
},

'on a phone the time goes under the title instead of crowding it' => function (): void {
    $css = gkCssText();
    T::ok((bool) preg_match('/@media \(max-width: 480px\) \{\s*\.gk-timeline-time \{[^}]*flex-basis: 100%/', $css), 'no phone rule puts the time on its own row');
    T::eq(gkCssDecl(gkCssRule('.gk-timeline-time'))['font-variant-numeric'] ?? '', 'tabular-nums', 'the digits of the time jump about');
},

// ── Motion: only the running step moves, and it stops when asked ───────────

'the running step turns its ring, and the ring stands still under reduced motion' => function (): void {
    $css = gkCssText();
    $run = gkCssDecl(gkCssRule('.gk-timeline-running .gk-timeline-marker::before'));
    T::ok(str_contains($run['animation'] ?? '', 'gk-spin'), 'the ring of the running step does not use gk-spin');
    T::ok(str_contains($css, '@keyframes gk-spin'), 'the keyframes gk-spin are gone');
    T::ok(str_contains($run['border-right-color'] ?? '', 'transparent'), 'the ring has no gap — it would turn without anyone seeing it');
    T::ok((bool) preg_match('/@media \(prefers-reduced-motion: reduce\) \{\s*\.gk-timeline-running \.gk-timeline-marker::before \{\s*animation: none;/', $css),
        'the ring turns under prefers-reduced-motion');
    // Nothing else in the block moves.
    foreach (gkCssRulesNaming('.gk-timeline') as [$selector, $body]) {
        if ($selector === '.gk-timeline-running .gk-timeline-marker::before') continue;
        T::ok(!preg_match('/animation\s*:|transition\s*:/', $body), "$selector moves — only the ring of the running step may");
    }
},

// ── Place in the file ──────────────────────────────────────────────────────

'it stands before the phone-last block, which has to stay the last thing in the file' => function (): void {
    $c = gkCssText();
    [$tl, $last] = [strpos($c, '/* === TIMELINE'), strpos($c, '/* ── Phones: the type in a field, last of all')];
    T::ok($tl !== false && $last !== false, 'the markers of the timeline block or of the last block were not found');
    T::ok($tl < $last, 'the timeline block stands after the block that has to be last');
},

// ── The markup that is documented is the markup that works ────────────────

'the documented markup is an ordered list with a role, a hidden marker and a word for the state in every step' => function () use ($root): void {
    foreach (['GRIDKIT_SKILL.md' => 'skill', 'demo/index.php' => 'demo'] as $file => $where) {
        $src = (string) file_get_contents("$root/$file");
        preg_match_all('~<ol class="gk-timeline".*?</ol>~s', $src, $lists);
        T::ok(count($lists[0]) >= ($where === 'demo' ? 2 : 1), "$file shows only " . count($lists[0]) . ' timelines');
        $steps = 0;
        foreach ($lists[0] as $n => $ol) {
            T::contains($ol, 'role="list"', "$file, list " . ($n + 1) . ': the <ol> has no role="list" — a screen reader that sees list-style:none drops the list');
            T::contains($ol, 'aria-label=', "$file, list " . ($n + 1) . ': the <ol> has no name');
            preg_match_all('~<li class="gk-timeline-step[^"]*".*?</li>~s', $ol, $items);
            foreach ($items[0] as $i => $step) {
                $steps++;
                T::contains($step, '<span class="gk-timeline-marker" aria-hidden="true"></span>', "$file, list " . ($n + 1) . ', step ' . ($i + 1) . ': the marker is not hidden from a screen reader');
                T::contains($step, 'class="gk-timeline-state"', "$file, list " . ($n + 1) . ', step ' . ($i + 1) . ': no word for the state — the symbol alone is a picture');
                T::contains($step, 'class="gk-timeline-title"', "$file, list " . ($n + 1) . ', step ' . ($i + 1) . ': no title');
            }
        }
        T::ok($steps >= ($where === 'demo' ? 5 : 2), "$file shows only $steps steps");
        if ($where === 'demo') {
            // Together the lists of the demo show every state, a running step marked as current, and a message.
            $all = implode("\n", $lists[0]);
            foreach (['ok', 'failed', 'running', 'waiting', 'skipped'] as $s) {
                T::contains($all, 'class="gk-timeline-step gk-timeline-' . $s, "the demo shows no step in the state \"$s\"");
            }
            T::contains($all, 'aria-current="step"', 'the demo does not mark the running step with aria-current');
            T::contains($all, 'class="gk-timeline-error"', 'the demo shows no error message');
        }
    }
},

];

})();
