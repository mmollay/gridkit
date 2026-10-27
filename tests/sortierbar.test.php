<?php
/**
 * A sortable container: `data-gk-sortierbar` (1.97.0) — entries that are moved by hand and
 * remembered on the server.
 *
 * Why it exists: the SSI Panel's site list has to put one card at the front, and two screens of the
 * invoicing module had each built their own up/down handling with inline styles. GridKit knew only
 * the sort of a table column (SortLink, `sortable`), never the order of a set of entries. This is
 * the one place for it: the page writes the container and the ids, the script adds the handle,
 * moves the entries, announces what happened and, with a URL, saves the new order.
 *
 * What is held here is what can be read in the script, the stylesheet and the documented markup:
 * the markup contract, the words (from the catalogue, both locales), the save call (method, header,
 * body, one request at a time), the keys, that touch is claimed at the handle only, that motion
 * stands still when asked, that the script writes no style but a position and keeps no global.
 * What needs a browser — the dragging itself with mouse, keys and touch, the save with an answer
 * of 200, 500 and a dead line, the handle count, light and dark, a phone — is in
 * ci/browser-sortierbar.js.
 */

declare(strict_types=1);

use GridKit\Lang;

require_once __DIR__ . '/css-helpers.php';

/** @return array<string,callable> */
return (static function (): array {

$root = __DIR__ . '/..';
$js   = static fn (): string => (string) @file_get_contents($root . '/js/sortierbar.js');
/** The script without its comments, so a word in a comment is not code. */
$code = static fn (): string => (string) preg_replace(['#/\*.*?\*/#s', '#(?<![:"\'])//[^\n]*#'], '', $js());
$doc  = static fn (): string => (string) file_get_contents($root . '/GRIDKIT_SKILL.md');
/** The block of the stylesheet that belongs to the sortable container, comments stripped. */
$sheet = static function (): string {
    $css = gkCssText();
    $from = strpos($css, '=== SORTABLE');
    if ($from === false) return '';
    $to = strpos($css, "\n/* ===", $from + 10);
    return (string) preg_replace('#/\*.*?\*/#s', '', substr($css, $from, $to === false ? null : $to - $from));
};

return [

// ── The script ──────────────────────────────────────────────────────────

'the script is a file of its own, strict, closed in a function, with no global of its own' => function () use ($js, $code, $root): void {
    T::ok(is_file($root . '/js/sortierbar.js'), 'js/sortierbar.js does not exist — the dashboard sync never overwrites it, gridkit.js it does not touch');
    $c = $code();
    T::contains($c, '"use strict"', 'the script is not strict');
    T::ok((bool) preg_match('/\A\s*\(function \(\) \{/', $c), 'the script does not open with a function that closes over everything');
    T::ok((bool) preg_match('/\}\)\(\);\s*\z/', $c), 'the script does not end by calling that function');
    T::ok(!preg_match('/\bwindow\.[A-Za-z_]+\s*=[^=]/', $c), 'the script assigns a property of window — no global');
    T::ok(!preg_match('/\b(jQuery|\$\()/', $c), 'the script needs jQuery');
    T::ok(!preg_match('/\bimport\s|\brequire\(/', $c), 'the script has a dependency');
    // One initialisation per container, however often the page is scanned.
    T::contains($c, 'WeakSet', 'a container could be set up twice');
    // A container that arrives after the page was drawn (a fragment, a modal) needs no call from the page.
    T::contains($c, 'MutationObserver', 'a container inserted later is never set up');
    T::contains($c, 'readyState', 'the script does not start when it is loaded late (defer, async, a fragment)');
},

'the markup contract: the container, the entries, the address, the token, the title' => function () use ($js): void {
    $s = $js();
    foreach ([
        'data-gk-sortierbar', 'data-gk-sort-id', 'data-gk-sort-titel',
        'data-gk-sortierbar-url', 'data-gk-sortierbar-csrf', 'data-gk-sortierbar-csrf-header',
    ] as $hook) {
        T::contains($s, $hook, "the script does not read $hook");
    }
    T::contains($s, 'X-CSRF-Token', 'the default name of the token header is not X-CSRF-Token');
    T::contains($s, 'gk-sort-griff', 'the handle has no class gk-sort-griff');
    T::contains($s, 'gk-sort-zieht', 'the lifted entry has no class gk-sort-zieht');
    T::contains($s, 'gk-sort-platz', 'the gap has no class gk-sort-platz');
    T::contains($s, 'drag_indicator', 'the handle does not draw the Material icon drag_indicator');
},

'the hooks a page author writes by hand are in the skill document' => function () use ($js, $doc): void {
    preg_match_all('/data-gk-sort[a-z-]*/', $js(), $m);
    $hooks = array_values(array_unique($m[0]));
    T::ok(count($hooks) >= 6, 'found ' . count($hooks) . ' hooks in the script — the pattern broke');
    foreach ($hooks as $hook) {
        T::contains($doc(), $hook, "$hook is bound by the script and not in GRIDKIT_SKILL.md");
    }
    T::contains($doc(), 'gk:sortiert', 'the event is not in GRIDKIT_SKILL.md');
    T::contains($doc(), 'sortierbar.js', 'the skill document never says the block has a script of its own');
},

'every word it shows comes from the catalogue, in both locales, and reaches the page' => function () use ($code, $root): void {
    $en = require $root . '/lang/en.php';
    $de = require $root . '/lang/de.php';
    preg_match_all('/\bt\(\s*"(sortierbar_[a-z_]+)"/', $code(), $m);
    $keys = array_values(array_unique($m[1]));
    T::ok(count($keys) >= 8, 'expected the handle name, the help, 3 announcements, saved and failed — found ' . count($keys));
    foreach ($keys as $key) {
        T::ok(isset($en["js.$key"]), "the script asks for $key — not in lang/en.php as js.$key");
        T::ok(isset($de["js.$key"]), "the script asks for $key — not in lang/de.php as js.$key");
    }
    // The catalogue reaches the browser as window.GK_LANG (Lang::jsConfig strips "js.").
    Lang::set('de');
    $cfg = Lang::jsConfig();
    foreach ($keys as $key) T::contains($cfg, '"' . $key . '"', "Lang::jsConfig() does not carry $key");
    T::contains($cfg, 'Die Reihenfolge konnte nicht gespeichert werden. Bitte noch einmal versuchen.', 'the German failure line is not the agreed words');
    Lang::set('en');
    // The two places a name is read aloud need the title in them.
    T::contains((string) $en['js.sortierbar_griff'], '{titel}', 'the handle name has no place for the title');
    T::contains((string) $de['js.sortierbar_griff'], '{titel}', 'the German handle name has no place for the title');
    T::ok(str_starts_with((string) $de['js.sortierbar_griff'], 'Verschieben'), 'the German handle name does not start with "Verschieben"');
    // No German identifier in the script itself: the markup hooks and classes are contracts, the names inside are ours.
    T::ok(!preg_match('/\b(const|let|var|function)\s+(griff|eintrag|behaelter|ziel|platz|zieht|speichern)\b/i', $code()), 'a German identifier in the script');
},

'the handle is a labelled button that says its state, and the page is told what happens' => function () use ($code): void {
    $c = $code();
    T::contains($c, '"button"', 'the handle is not a <button type="button">');
    T::contains($c, 'aria-label', 'the handle has no name');
    T::contains($c, 'aria-pressed', 'the handle does not say whether the entry is lifted');
    T::contains($c, 'aria-describedby', 'the handle does not point at the instructions');
    T::contains($c, 'aria-live', 'there is no live region for the position and the result');
    T::contains($c, 'role', 'the failure line is not an alert');
    T::contains($c, 'alert', 'the failure line is not an alert');
    T::contains($c, 'gk-sr-only', 'the announcements and the instructions are not hidden visually with the library\'s own class');
    // Moving an entry with the keys must not take the focus off the handle: the passed entries move, not the lifted one.
    T::contains($c, '.before(', 'the entries are not reordered by moving the neighbours (focus would be lost)');
},

'the keys: space and enter lift and set down, arrows move, home and end jump, escape gives it back' => function () use ($code): void {
    $c = $code();
    foreach (['" "', '"Enter"', '"Escape"', 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End'] as $key) {
        T::contains($c, $key, "the script never reads the key $key");
    }
    T::contains($c, 'rtl', 'left and right do not swap in a right-to-left container');
    // Up and down in a grid move a whole row; the row length is measured, not assumed — by the layout
    // (offsetTop), because an entry that is still gliding is drawn away from its row.
    T::ok((bool) preg_match('/function rowLength\([^)]*\)\s*\{[^}]*offsetTop[^}]*\}/s', $c), 'nothing is measured by the layout: a grid cannot know its row length');
    T::ok(!preg_match('/function rowLength\([^)]*\)\s*\{[^}]*getBoundingClientRect[^}]*\}/s', $c), 'the row length is read from where entries are drawn, and a glide in flight falsifies it');
    // The lifted state ends when the handle loses the focus.
    T::contains($c, 'focusout', 'a lifted entry stays lifted when the focus leaves');
},

'the pointer: mouse, pen and touch through pointer events; the page does not scroll under the handle' => function () use ($code, $sheet): void {
    $c = $code();
    foreach (['pointerdown', 'pointermove', 'pointerup', 'pointercancel', 'setPointerCapture'] as $w) {
        T::contains($c, $w, "the script does not use $w");
    }
    T::notContains($c, 'touchstart', 'the script listens to touch events — pointer events do mouse, pen and finger');
    T::notContains($c, 'mousedown', 'the script listens to mouse events — pointer events do mouse, pen and finger');
    T::contains($c, 'scrollBy', 'the page does not scroll when an entry is dragged to its edge');
    // touch-action is claimed at the handle and nowhere else: the rest of the entry still scrolls the page.
    $css = $sheet();
    T::eq(substr_count($css, 'touch-action'), 1, 'touch-action appears ' . substr_count($css, 'touch-action') . ' times in the block — it belongs to the handle only');
    T::eq(gkCssDecl(gkCssRule('.gk-sort-griff', $css))['touch-action'] ?? '', 'none', 'the handle does not take the touch for itself');
},

'the save: a POST with the ids as text, the token in a header, one request at a time, a failure puts the entry back' => function () use ($code): void {
    $c = $code();
    T::contains($c, 'method: "POST"', 'the save is not a POST');
    T::contains($c, 'credentials: "same-origin"', 'the save does not send the session');
    T::contains($c, '"Content-Type": "application/json"', 'the body is not announced as JSON');
    T::contains($c, 'JSON.stringify({ ids', 'the body is not {ids: […]}');
    T::contains($c, 'String(', 'the ids are not sent as text');
    T::contains($c, 'AbortController', 'two saves can run at once');
    T::contains($c, '.abort()', 'the earlier save is not cancelled by the later');
    T::contains($c, 'AbortError', 'a cancelled save is reported as a failure');
    T::contains($c, 'gk:sortiert', 'the event gk:sortiert is never dispatched');
    T::contains($c, 'bubbles: true', 'the event does not bubble');
    T::contains($c, 'behaelter', 'the event detail has no behaelter');
    T::contains($c, 'sortierbar_gespeichert', 'success is not announced');
    // The put-down words and "saved" are one message: two a moment apart let the second wipe the first.
    T::ok((bool) preg_match('/sortierbar_gespeichert"\)\s*,\s*true\)/', $c), 'saved is announced on its own, and can wipe out the words of the drop');
    T::contains($c, 'sortierbar_fehler', 'failure is not said in words');
    // The address comes from the page; the library knows no route of its own.
    T::ok(!preg_match('#\bfetch\(\s*[\'"]/#', $c), 'the script fetches a fixed path');
},

'motion stands still when asked, and the script writes no style but a position' => function () use ($code): void {
    $c = $code();
    T::contains($c, 'prefers-reduced-motion', 'the script does not look at prefers-reduced-motion');
    // A move that comes while the entries still glide starts from where they are drawn, not from a half-way point.
    T::contains($c, '.cancel()', 'the glide of an earlier move is not stopped before the next is measured');
    T::notContains($c, 'cssText', 'the script writes a whole style attribute');
    T::notContains($c, 'setAttribute("style"', 'the script writes a style attribute');
    T::notContains($c, 'style="', 'the script writes markup with an inline style');
    preg_match_all('/\.style\.([A-Za-z]+)/', $c, $m);
    $props = array_values(array_unique($m[1]));
    T::ok($props !== [], 'the script sets no position at all — the lifted entry cannot follow the pointer');
    foreach ($props as $p) {
        T::ok(in_array($p, ['left', 'top', 'width', 'height'], true), "the script sets style.$p — only left, top, width and height of a lifted entry are positions");
    }
},

// ── The stylesheet ──────────────────────────────────────────────────────

'the handle is a 44px target, drawn by tokens, with a visible focus' => function () use ($sheet): void {
    $css = $sheet();
    T::ok($css !== '', 'the stylesheet has no block "=== SORTABLE" — the section heading is how this test finds it');
    $h = gkCssDecl(gkCssRule('.gk-sort-griff', $css));
    T::ok($h !== [], '.gk-sort-griff has no rule');
    T::eq($h['width'] ?? '', 'var(--gk-target-min)', 'the handle is narrower than the finger-sized token');
    T::eq($h['height'] ?? '', 'var(--gk-target-min)', 'the handle is lower than the finger-sized token');
    T::ok((bool) preg_match('/--gk-target-min:\s*44px;/', gkCssText()), 'the finger-sized token is not 44px any more');
    T::eq($h['position'] ?? '', 'absolute', 'the handle takes room from the card');
    T::ok(str_contains($h['cursor'] ?? '', 'grab'), 'the handle does not show a hand');
    T::eq($h['user-select'] ?? '', 'none', 'the handle can be selected like text');
    T::contains($css, '.gk-sort-griff:focus-visible', 'the handle shows no focus ring');
    T::contains($css, '.gk-sort-griff[aria-pressed="true"]', 'a lifted entry shows no pressed handle');
    T::ok(!preg_match('/#[0-9a-fA-F]{3,8}\b/', $css), 'a literal colour in the block — roles only, so light and dark follow from the tokens');
    T::ok(!preg_match('/\brgba?\(/', preg_replace('/box-shadow:[^;]*;/', '', $css)), 'a literal colour (rgb) outside a shadow');
},

'the handles are there only where there is something to reorder' => function (): void {
    // The script removes the handles of a container with fewer than two entries; the stylesheet addresses the
    // entries only in a container that has them.
    T::contains(gkCssBare(), '[data-gk-sortierbar].gk-sort-aktiv) > [data-gk-sort-id]', 'an entry becomes the positioning parent only in a container with handles');
},

'the lifted entry, the gap and the free entry are three different things' => function () use ($sheet): void {
    $css = $sheet();
    $z = gkCssDecl(gkCssRule('.gk-sort-zieht', $css));
    T::ok($z !== [], '.gk-sort-zieht has no rule');
    T::ok(isset($z['box-shadow']) || isset($z['outline']), 'a lifted entry does not stand out');
    T::ok(str_contains($z['cursor'] ?? '', 'grabbing'), 'a lifted entry does not show the closed hand');
    $p = gkCssDecl(gkCssRule('.gk-sort-platz', $css));
    T::ok(str_contains($p['border'] ?? '', 'dashed'), 'the gap is not drawn as a dashed slot');
    T::eq($p['box-sizing'] ?? '', 'border-box', 'the gap is bigger than the entry by its border');
    $f = gkCssDecl(gkCssRule('.gk-sort-frei', $css));
    T::eq($f['position'] ?? '', 'fixed !important', 'an entry dragged by pointer does not leave the flow');
    T::eq($f['box-sizing'] ?? '', 'border-box !important', 'a free entry grows by its padding');
    T::eq($f['margin'] ?? '', '0 !important', 'a free entry keeps its margin and jumps away from the pointer');
    T::ok(isset($f['z-index']), 'a free entry goes under the entries it passes');
},

'the failure is a sentence beside a shape, the saved note is brief; neither is colour alone' => function () use ($sheet): void {
    $css = $sheet();
    foreach (['.gk-sort-fehler', '.gk-sort-ok'] as $sel) T::contains($css, $sel, "no rule for $sel");
    T::contains($css, '.gk-sort-fehler .material-icons', 'the failure has no shape beside the words');
    T::contains($css, '.gk-sort-ok .material-icons', 'the saved note has no shape beside the words');
    T::contains($css, 'var(--gk-on-error-container', 'the failure does not use the error text role');
},

'under prefers-reduced-motion the lifted entry does not animate' => function () use ($sheet): void {
    $css = $sheet();
    T::ok((bool) preg_match('/@media \(prefers-reduced-motion: no-preference\)\s*\{[^@]*gk-sort-/s', $css)
        || (bool) preg_match('/@media \(prefers-reduced-motion: reduce\)\s*\{[^@]*gk-sort-/s', $css), 'the block has no prefers-reduced-motion rule');
},

// ── Where it is written down and wired ────────────────────────────────────

'the block is in the index, the page skeleton loads the script, the demo shows it' => function () use ($root): void {
    $blocks = json_decode((string) file_get_contents($root . '/css/blocks.json'), true)['blocks'] ?? [];
    T::ok(isset($blocks['sortierbar']), 'css/blocks.json has no block "sortierbar"');
    foreach (['gk-sort-griff', 'gk-sort-zieht', 'gk-sort-platz', 'gk-sort-frei', 'gk-sort-aktiv', 'gk-sort-meldungen', 'gk-sort-ok', 'gk-sort-fehler'] as $class) {
        T::eq($blocks['sortierbar']['classes'][$class] ?? '', '1.97.0', "$class is not in the index as new in 1.97.0");
    }
    T::contains((string) file_get_contents($root . '/skeleton.php'), 'js/sortierbar.js', 'skeleton.php never loads js/sortierbar.js');
    $demo = (string) file_get_contents($root . '/demo/index.php');
    T::contains($demo, "since('sortierbar')", 'the demo has no marked heading for the block');
    T::contains($demo, 'id="sortierbar"', 'the demo heading has no anchor');
    T::contains($demo, 'data-gk-sortierbar', 'the demo shows no container');
    T::contains($demo, 'js/sortierbar.js', 'the demo never loads the script');
    foreach (['README.md', 'SPEC.md', 'GRIDKIT_SKILL.md'] as $f) {
        T::contains((string) file_get_contents("$root/$f"), 'sortierbar', "$f never mentions the sortable container");
    }
},

];

})();
