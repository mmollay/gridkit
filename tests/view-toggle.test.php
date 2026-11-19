<?php
/**
 * The view toggle (1.132.0): [data-gk-view-toggle] chooses between the drawings of one list
 * ([data-gk-view] blocks inside a [data-gk-view-scope]) — a table and cards, a list and a grid.
 *
 * What a click does is script and is measured in a browser (ci/browser-view.js). Held here: the
 * script is one block at the end of gridkit.js that touches storage only inside a try, hooks
 * GK.initContent and the ready helper, writes the state only through the hidden attribute and the
 * documented attributes; and that the documentation and the demo show the markup the script reads.
 */

declare(strict_types=1);

/** The 1.132.0 block of js/gridkit.js, up to the next block header or the end of the file. */
function gkVtBlock(): string
{
    $js = (string) file_get_contents(__DIR__ . '/../js/gridkit.js');
    $head = '// === 1.132.0 — view toggle ([data-gk-view-toggle]) ===';
    $at = strpos($js, $head);
    if ($at === false) return '';
    $next = strpos($js, "\n// === ", $at + 1);
    return (string) ($next === false ? substr($js, $at) : substr($js, $at, $next - $at));
}

return [

'the script is one closed block, found once, after the density block' => function (): void {
    $js = (string) file_get_contents(__DIR__ . '/../js/gridkit.js');
    $block = gkVtBlock();
    T::ok($block !== '', 'the block header is not in gridkit.js');
    T::eq(substr_count($js, '// === 1.132.0 — view toggle'), 1, 'the block header is not there once');
    T::ok(strpos($js, '1.109.0 — density toggle') < strpos($js, '1.132.0 — view toggle'), 'the block stands before the density block');
    T::ok(str_ends_with(rtrim($block), '})();'), 'the block is not one closed function, or something other than a block follows it');
    foreach (['data-gk-set-view', 'data-gk-view-toggle', 'data-gk-view-scope'] as $attr) {
        T::eq(substr_count($js, $attr), substr_count($block, $attr), "$attr is read outside the block");
    }
},

'every storage access is wrapped, the first pass uses the ready helper, later content is hooked' => function (): void {
    $code = (string) preg_replace(['#/\*.*?\*/#s', '#//[^\n]*#'], '', gkVtBlock());
    $outside = (string) preg_replace('/try \{[^{}]*\}/', '', $code);
    T::ok(str_contains($code, 'localStorage.getItem') && str_contains($code, 'localStorage.setItem'), 'the block stores or reads nothing');
    T::notContains($outside, 'localStorage', 'localStorage is used outside a try');
    T::contains($code, '_gkReady(', 'the first pass does not use the ready helper');
    T::contains($code, 'GK.initContent = function', 'content that arrives later is not handed to the block');
    T::contains($code, 'GK.view.init(root);', 'the hook does not hand the content to GK.view.init');
    T::contains($code, 'GK.view.init(document);', 'the first pass does not show the stored choice');
    T::contains($code, 'GK.theme._key(', 'the key is not in the namespace of GK.theme');
    T::contains($code, 'gk:viewchange', 'the event is not fired');
},

'the block shows a drawing through the hidden attribute and the pressed state, and writes no style' => function (): void {
    $code = (string) preg_replace(['#/\*.*?\*/#s', '#//[^\n]*#'], '', gkVtBlock());
    T::contains($code, '.hidden =', 'the drawings are not hidden with the hidden attribute');
    T::contains($code, '"aria-pressed"', 'the pressed state is not written');
    T::contains($code, 'data-gk-view-active', 'the active drawing is not named on the scope');
    T::notContains($code, '.style', 'the block writes a style');
    T::notContains($code, 'classList', 'the block works with classes — the state is the attributes');
},

'the documentation and the demo show the markup the script reads' => function (): void {
    foreach (['GRIDKIT_SKILL.md', 'skill/reference/components.md', 'SPEC.md'] as $file) {
        $doc = sourceOf($file);
        foreach (['data-gk-view-toggle', 'data-gk-view-scope', 'data-gk-view="', 'data-gk-set-view', 'gk:viewchange'] as $needle) {
            T::contains($doc, $needle, "$file does not document the view toggle");
        }
    }
    $demo = sourceOf('demo/index.php');
    T::contains($demo, 'data-gk-view-toggle="demo-ansicht"', 'the demo does not show the view toggle');
    T::contains($demo, 'data-gk-view="cards"', 'the demo has no second drawing');
},

];
