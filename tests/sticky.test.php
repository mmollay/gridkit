<?php
/**
 * Table::stickyHead() and Table::stickyColumn() (1.107.0).
 *
 * A long list lost its header row as soon as it scrolled, a wide one its first column — the
 * name that says which row the figures belong to. What sticks, and whether it hides what
 * scrolls under it, is measured in a browser (ci/browser-sticky.js); the markup it hangs on,
 * the refusal of a height that is not a length, and the rules and the script are held here.
 */

declare(strict_types=1);

use GridKit\{Lang, Table};

/** @return list<array<string,mixed>> */
function stickyRows(): array
{
    return [
        ['id' => 1, 'name' => 'Ada', 'city' => 'Wien', 'amount' => 12.5],
        ['id' => 2, 'name' => 'Bob', 'city' => 'Graz', 'amount' => 7.0],
        ['id' => 3, 'name' => 'Cy', 'city' => 'Linz', 'amount' => 0.0],
    ];
}

function stickyTable(string $id = 't'): Table
{
    return (new Table($id))->setData(stickyRows())->column('name', 'Name')->column('city', 'City')
        ->column('amount', 'Amount', ['format' => 'currency']);
}

/** Render and keep the warnings. @return array{0:string,1:list<string>} */
function stickyRender(callable $build): array
{
    $warnings = [];
    set_error_handler(static function (int $n, string $m) use (&$warnings): bool {
        $warnings[] = $m;
        return true;
    });
    try {
        $html = T::capture(static function () use ($build): void { $build()->render(); });
    } finally {
        restore_error_handler();
    }
    return [$html, $warnings];
}

/** The opening tag of the box, or '' without one. */
function stickyBox(string $html): string
{
    return preg_match('/<div class="gk-table-sticky-box"[^>]*>/', $html, $m) ? $m[0] : '';
}

/** @return array<string,callable> */
return [

'stickyHead() alone writes a class on the wrap and no box: the head sticks to the page' => function (): void {
    Lang::set('en');
    [$html, $w] = stickyRender(fn () => stickyTable()->stickyHead());
    T::eq($w, [], 'a warning without a reason');
    T::contains($html, '<div class="gk-table-wrap gk-table-md gk-table-mobile-card gk-table-sticky-head" data-gk-table="t"', 'the wrap does not say that its head sticks');
    T::eq(stickyBox($html), '', 'a box around a table whose head sticks to the page — the box would be the scroller');
    T::contains($html, 'data-gk-table-status="t"></div><table class="gk-table">', 'the table is not where it always was');
    T::notContains($html, 'gk-table-sticky-col', 'the column sticks without stickyColumn()');
},

'stickyHead() with a height puts the table, and only the table, in a box of that height' => function (): void {
    Lang::set('en');
    [$html, $w] = stickyRender(fn () => stickyTable()->paginate(2)->stickyHead('480px'));
    T::eq($w, [], 'a valid length raised a warning');
    T::eq(stickyBox($html), '<div class="gk-table-sticky-box" role="region" tabindex="0" aria-label="Scrollable table" style="--gk-table-sticky-height:480px">',
        'the box is not a named region with a tab stop and its height');
    T::contains($html, 'data-gk-table-status="t"></div><div class="gk-table-sticky-box"', 'the box does not follow the status line');
    T::contains($html, '</table></div><nav class="gk-pagination"', 'the box does not end with the table, before the pager');
    T::notContains(stickyBox($html), 'data-gk-sticky-col', 'cells stick sideways without stickyColumn()');
},

'every length a box may have is taken, trimmed' => function (): void {
    foreach (['480px' => '480px', '70vh' => '70vh', '60dvh' => '60dvh', '50svh' => '50svh', '90lvh' => '90lvh',
              '32.5rem' => '32.5rem', '.5em' => '.5em', '80%' => '80%', ' 70vh ' => '70vh'] as $in => $out) {
        [$html, $w] = stickyRender(fn () => stickyTable()->stickyHead($in));
        T::eq($w, [], "'$in' raised a warning");
        T::contains(stickyBox($html), 'style="--gk-table-sticky-height:' . $out . '"', "'$in' did not become the height $out");
    }
},

'anything that is not a length is refused out loud, and the head sticks to the page' => function (): void {
    $bad = ['70', '0px', '0', '-5px', '70 vh', '70VH', 'auto', 'none', '1e3px', 'calc(100vh - 2rem)', 'min(70vh, 600px)',
            '70vh;background:red', '70vh" onmouseover="alert(1)', '"><script>alert(1)</script>', '٣٠px'];
    foreach ($bad as $value) {
        [$html, $w] = stickyRender(fn () => stickyTable()->stickyHead($value));
        T::eq(count($w), 1, 'no warning for ' . json_encode($value));
        T::contains($w[0] ?? '', "stickyHead() takes a CSS length such as '70vh' or '480px'", 'the warning does not say what is expected');
        T::notContains($w[0] ?? '', '<', 'the warning quotes the refused value with its markup');
        if (strpbrk($value, '<>"\'&') !== false) T::notContains($w[0] ?? '', $value, 'the warning quotes the refused value raw');
        T::eq(stickyBox($html), '', 'a box for ' . json_encode($value));
        T::notContains($html, 'gk-table-sticky-height', 'the refused value reached a style for ' . json_encode($value));
        T::notContains($html, 'alert(1)', 'the refused value reached the page');
        T::contains($html, 'gk-table-sticky-head"', 'the head no longer sticks after a refused height');
    }
    // A length with white space around it is trimmed and taken.
    [$html, $w] = stickyRender(fn () => stickyTable()->stickyHead("\t70vh\n"));
    T::eq($w, [], 'white space around a length is refused');
    // With the column the box stays (it is needed for sideways scrolling) and takes 70vh.
    [$html, $w] = stickyRender(fn () => stickyTable()->stickyHead('tall')->stickyColumn());
    T::eq(count($w), 1, 'no warning with the column');
    T::contains(stickyBox($html), 'style="--gk-table-sticky-height:70vh"', 'a refused height with the column does not fall back to 70vh');
},

'stickyColumn() puts the table in a box without a height and counts the cells that stick' => function (): void {
    Lang::set('en');
    $box = static fn (callable $build): string => stickyBox(stickyRender($build)[0]);
    T::eq($box(fn () => stickyTable()->stickyColumn()),
        '<div class="gk-table-sticky-box" role="region" tabindex="0" aria-label="Scrollable table" data-gk-sticky-col="1">',
        'the first column alone');
    T::contains($box(fn () => stickyTable()->selectable()->stickyColumn()), 'data-gk-sticky-col="2"', 'the checkbox does not stick with the column');
    T::contains($box(fn () => stickyTable()->button('view', ['icon' => 'visibility', 'position' => 'left'])->stickyColumn()), 'data-gk-sticky-col="2"', 'the left buttons do not stick with the column');
    T::contains($box(fn () => stickyTable()->selectable()->button('view', ['icon' => 'visibility', 'position' => 'left'])
        ->button('edit', ['icon' => 'edit'])->stickyColumn()), 'data-gk-sticky-col="3"', 'checkbox, left buttons and column are not three');
    T::contains($box(fn () => stickyTable()->button('edit', ['icon' => 'edit'])->stickyColumn()), 'data-gk-sticky-col="1"', 'a button on the right counted as one in front of the column');
    T::contains($box(fn () => stickyTable()->bulkAction('archive')->stickyColumn()), 'data-gk-sticky-col="2"', 'bulkAction() turns selectable() on, and its checkbox is not counted');
    [$html] = stickyRender(fn () => stickyTable()->stickyColumn());
    T::contains($html, 'class="gk-table-wrap gk-table-md gk-table-mobile-card gk-table-sticky-col"', 'the wrap does not say that its column sticks');
    T::notContains(stickyBox($html), 'style=', 'a column alone gave the box a height');
},

'with the head and the column, the box gets the height of the head or 70vh' => function (): void {
    T::contains(stickyBox(stickyRender(fn () => stickyTable()->stickyHead()->stickyColumn())[0]), 'style="--gk-table-sticky-height:70vh"',
        'the head cannot stick to the page inside a box that scrolls sideways — it needs a height');
    T::contains(stickyBox(stickyRender(fn () => stickyTable()->stickyColumn()->stickyHead('300px'))[0]), 'style="--gk-table-sticky-height:300px"',
        'the height given to stickyHead() is not the height of the box');
    [$html] = stickyRender(fn () => stickyTable()->stickyHead()->stickyColumn());
    T::contains($html, 'gk-table-mobile-card gk-table-sticky-head gk-table-sticky-col"', 'the wrap does not carry both classes');
},

'the box is named by the caption, escaped, and in German without one' => function (): void {
    Lang::set('en');
    T::contains(stickyBox(stickyRender(fn () => stickyTable()->caption('Orders <b>& "co"')->stickyColumn())[0]),
        'aria-label="Orders &lt;b&gt;&amp; &quot;co&quot;"', 'the caption is not the escaped name of the region');
    Lang::set('de');
    T::contains(stickyBox(stickyRender(fn () => stickyTable()->stickyColumn())[0]), 'aria-label="Scrollbare Tabelle"', 'the German name is missing');
    Lang::set('en');
},

'an AJAX reload writes the box with the table, the wrap keeps the classes' => function (): void {
    $get = $_GET;
    $server = $_SERVER;
    try {
        $_GET = ['gk_table' => 'srv'];
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        [$html] = stickyRender(fn () => (new Table('srv'))->rows(stickyRows(), 30)->column('name', 'Name')->paginate(3)
            ->selectable()->stickyHead('400px')->stickyColumn());
    } finally {
        $_GET = $get;
        $_SERVER = $server;
    }
    T::ok(str_starts_with($html, '<div class="gk-sr-only" role="status"'), 'the fragment does not start with the status line');
    T::contains($html, '<div class="gk-table-sticky-box" role="region" tabindex="0" aria-label="Scrollable table" data-gk-sticky-col="2" style="--gk-table-sticky-height:400px"><table',
        'the fragment has no box — the reload would leave the table outside it');
    T::contains($html, '</table></div><nav class="gk-pagination"', 'the pager went into the box');
    T::notContains($html, 'gk-table-wrap', 'the fragment carries a wrap of its own');
},

'with the options taken out again, a sticky table is the table without them, byte for byte' => function (): void {
    Lang::set('en');
    // The golden tests hold tables without the options byte for byte (tests/golden/table.json);
    // this holds that the options add their classes and the box, and nothing else.
    $build = static fn (): Table => stickyTable()->selectable()->search(['name'])->paginate(2)->caption('C')
        ->button('view', ['icon' => 'visibility', 'position' => 'left'])->button('edit', ['icon' => 'edit'])
        ->footer(['', '', 'Total', '19.50'])->groupBy('city');
    [$plain] = stickyRender($build);
    foreach (['head' => fn () => $build()->stickyHead(), 'box' => fn () => $build()->stickyHead('20rem'),
              'column' => fn () => $build()->stickyColumn(), 'both' => fn () => $build()->stickyHead()->stickyColumn()] as $case => $sticky) {
        [$html] = stickyRender($sticky);
        $back = str_replace([' gk-table-sticky-head', ' gk-table-sticky-col'], '', $html);
        $box = stickyBox($back);
        if ($box !== '') {
            $back = str_replace($box, '', $back);
            $back = preg_replace('~</table></div>~', '</table>', $back, 1, $closed);
            T::eq($closed, 1, "$case: the box is not closed right after the table");
        }
        T::eq($back, $plain, "$case: the sticky table differs from the plain one in more than its classes and its box");
    }
    T::notContains($plain, 'sticky', 'a table without the options says sticky somewhere');
    T::notContains($plain, 'role="region"', 'a table without the options is a region');
},

'the methods chain' => function (): void {
    $t = new Table('t');
    T::ok($t->stickyHead() === $t, 'stickyHead() does not return the table');
    T::ok($t->stickyHead('50vh') === $t, 'stickyHead($h) does not return the table');
    T::ok($t->stickyColumn() === $t, 'stickyColumn() does not return the table');
},

'the stylesheet: separate borders, opaque cells, both dark spellings, card mode untouched' => function (): void {
    $css = (string) file_get_contents(__DIR__ . '/../css/gridkit.css');
    $at = strpos($css, '/* === 1.107.0 — sticky head and column === */');
    T::ok($at !== false, 'the block is not headed as the others are');
    $block = substr($css, (int) $at);
    T::contains($block, "  border-collapse: separate;\n  border-spacing: 0;", 'collapsed borders stay where a sticky cell was — the slit shows what scrolls under it');
    T::contains($block, 'top: var(--gk-table-sticky-top, var(--gk-sheet-top, 0px));', 'the head does not stop under a fixed GridKit header');
    T::contains($block, '[data-gk-mode="dark"] :is(.gk-table-sticky-head, .gk-table-sticky-col),' . "\n" . '.gk-dark :is(.gk-table-sticky-head, .gk-table-sticky-col) {', 'the dark surfaces miss a spelling');
    T::contains($block, '.gk-dark .gk-table-sticky-col .gk-table tbody tr:nth-child(even)', 'the dark even rows miss a spelling');
    T::contains($block, 'background-color: var(--gk-table-sticky-row, var(--gk-table-sticky-bg));', 'a sticky body cell has no surface');
    T::contains($block, ':where(.gk-table-sticky-box[data-gk-sticky-col="1"] > .gk-table > tbody > tr > td:nth-child(1),', 'the surface of a sticky cell outweighs the hover fill');
    foreach ([1, 2, 3] as $n) {
        T::contains($block, '.gk-table-sticky-box[data-gk-sticky-col="' . $n . '"] > .gk-table > :is(thead, tbody) > tr:not(.gk-table-group, .gk-table-more, .gk-empty-row) > :nth-child(' . ($n === 1 ? '1' : "-n+$n") . ')',
            "$n sticky cells are not selected");
    }
    T::contains($block, 'left: var(--gk-table-sticky-left-2, 36px);', 'without the script the cell after a checkbox does not stick next to it');
    // Card mode: inside the phone's media query, the box gives up scrolling and every cell sticking.
    $phone = substr($block, (int) strpos($block, '@media (max-width: 768px) {'));
    T::ok(strpos($block, '@media (max-width: 768px) {') !== false, 'card mode is not handled');
    T::contains($phone, ".gk-table-mobile-card > .gk-table-sticky-box {\n    overflow: visible;\n    max-height: none;", 'the box still scrolls in card mode');
    T::contains($phone, "position: static;\n    z-index: auto;", 'the cells still stick in card mode');
},

'the script: one block at the end, wrapping what it needs' => function (): void {
    $js = (string) file_get_contents(__DIR__ . '/../js/gridkit.js');
    $at = strpos($js, '// === 1.107.0 — sticky head and column (Table::stickyHead / stickyColumn) ===');
    T::ok($at !== false, 'the block is not headed as the brief says');
    $block = substr($js, (int) $at);
    T::ok(strpos($js, '1.105.0 — additions that need script') < $at, 'the block is not after the 1.105.0 block');
    T::contains($block, 'var render = GK.table.renderStatic;', 'the rebuild of a setData() table is not wrapped');
    T::contains($block, 'render.apply(this, arguments)', 'the wrapped rebuild does not call the original');
    T::contains($block, 'var original = GK.initContent;', 'tables that arrive later are not bound');
    T::contains($block, 'new ResizeObserver(', 'the offsets do not follow a change of width');
    T::contains($block, 'new MutationObserver(', 'a reload is not noticed');
    T::contains($block, '"--gk-table-sticky-left-" + (i + 2)', 'the offsets are not written');
    T::contains($block, 'data-gk-scrolled-x', 'a box scrolled sideways is not marked');
    T::ok(substr_count($js, '// === 1.107.0') === 1, 'the block is in the file twice');
},

];
