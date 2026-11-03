<?php
/**
 * A group of outlined buttons is closed on the right (1.110.5).
 *
 * In `.gk-btn-group` every button gives up its right edge, so that two edges never stand side by
 * side — the next button's left edge is the divider. The last one has no neighbour: without its
 * right edge a group of outlined buttons (Button::group() of 'variant' => 'outlined') stood open
 * on the right, its rounded corners running out into nothing — seen in the demo's "Button Group".
 * Measured in a browser while fixing (the right edge 0px before, the width of the others after);
 * held here as the rule that does it.
 */

declare(strict_types=1);

require_once __DIR__ . '/css-helpers.php';

/** @return array<string,callable> */
return [

'the last outlined button of a group keeps its right edge, as wide as the others' => function (): void {
    $edge = gkCssDeclFor('.gk-btn')['border'] ?? '';
    T::ok((bool) preg_match('/^(\S+) solid/', $edge, $w), 'a button has no edge of its own to compare with');
    T::eq(gkCssDeclFor('.gk-btn-group:not(.gk-btn-group-bordered) .gk-btn.gk-btn-outlined:last-child')['border-right-width'] ?? null,
        $w[1] ?? '?', 'the last outlined button of a group has no right edge, or another width than its other edges');
},

'the rule comes after the one that takes the right edge from every button, and leaves a bordered group alone' => function (): void {
    $css = gkCssBare();
    $take = strpos($css, ".gk-btn-group .gk-btn:last-child {");
    $give = strpos($css, ".gk-btn-group:not(.gk-btn-group-bordered) .gk-btn.gk-btn-outlined:last-child {");
    T::ok($take !== false && $give !== false && $give > $take, 'the last button\'s edge is taken again after it was given back');
    T::eq(gkCssDeclFor('.gk-btn-group .gk-btn:last-child')['border-right-width'] ?? null, '0', 'a filled or text button at the end now carries an edge it never had');
},

];
