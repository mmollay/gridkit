<?php
/**
 * Table::densityToggle() (1.109.0): Compact · Normal · Roomy in the toolbar of one table.
 *
 * What a click does is script and is measured in a browser (ci/browser-density.js). Held here:
 * the markup of the control and that it is the only thing the option adds; the steps the
 * stylesheet puts on a wrap, against themes.css, which holds the same numbers for the page and is
 * optional; and that every padding written against the tokens adds up, at Normal, to the pixels
 * of the rule it stands for — so a table at Normal looks as it did, and a size rule changed later
 * without its twin here fails at once.
 */

declare(strict_types=1);

use GridKit\{Lang, Table, Theme};

require_once __DIR__ . '/css-helpers.php';

/** The rows the tables below are built from. */
function gkDtRows(): array
{
    return [
        ['id' => 1, 'no' => 'A-1', 'name' => 'Ada', 'amount' => 12.5, 'state' => 'active'],
        ['id' => 2, 'no' => 'A-2', 'name' => 'Bob', 'amount' => 3.0, 'state' => 'draft'],
    ];
}

/** The control as the option writes it, in the active language. */
function gkDtControl(string $label, string $compact, string $normal, string $roomy): string
{
    return '<div class="gk-segmented" role="group" aria-label="' . $label . '" data-gk-density-toggle hidden>'
         . '<button type="button" class="gk-segmented-item" data-gk-set-density="konsole" aria-pressed="false">' . $compact . '</button>'
         . '<button type="button" class="gk-segmented-item" data-gk-set-density="normal" aria-pressed="false">' . $normal . '</button>'
         . '<button type="button" class="gk-segmented-item" data-gk-set-density="weit" aria-pressed="false">' . $roomy . '</button>'
         . '</div>';
}

/** Tables of every kind the option meets, each built twice by $make(bool $toggle). @return array<string,callable> */
function gkDtTables(): array
{
    $rows = gkDtRows();
    return [
        'static, search, sort, filter, buttons' => fn (bool $t) => (new Table('d1'))->setData($rows)->search(['name'])
            ->column('name', 'Name', ['sortable' => true])->column('state', 'State', ['format' => 'label'])
            ->filter('state', 'select', ['options' => ['active' => 'Active']])
            ->button('edit', ['icon' => 'edit']),
        'selectable, CSV, a new button, a footer' => fn (bool $t) => (new Table('d2'))->setData($rows)->selectable()
            ->column('name', 'Name')->column('amount', 'Amount', ['format' => 'currency'])
            ->exportCsv('x.csv')->newButton('New')->footer(['Total', '15.50']),
        'server rows, paged' => fn (bool $t) => (new Table('d3'))->rows($rows, 9)->paginate(2)->column('name', 'Name'),
        'a list with a row link' => fn (bool $t) => (new Table('d4'))->setData($rows)->size('list')->rowLink('/u/{id}')
            ->column('name', 'Name', ['sub' => 'no']),
        'sm, lg, a variant, scroll on a phone' => fn (bool $t) => (new Table('d5'))->setData($rows)->size('sm')
            ->variant('compact')->mobile('scroll')->column('name', 'Name'),
    ];
}

function gkDtRender(Table $table): string
{
    return T::capture(fn () => $table->render());
}

/** The 1.109.0 block of gridkit.css, up to the next block header (a later version's) or the end, comments stripped. */
function gkDtCssBlock(): string
{
    $css = gkCssText();
    $at = strpos($css, '/* === 1.109.0 — density toggle ===');
    if ($at === false) return '';
    $next = strpos($css, "\n/* === ", $at + 1);
    return (string) preg_replace('#/\*.*?\*/#s', '', $next === false ? substr($css, $at) : substr($css, $at, $next - $at));
}

/** A selector list split at its own commas, not at those inside :not(a, b). @return list<string> */
function gkDtSelectors(string $list): array
{
    $out = [];
    $depth = 0;
    $cur = '';
    foreach (str_split((string) preg_replace('/\s+/', ' ', $list)) as $ch) {
        $depth += $ch === '(' ? 1 : ($ch === ')' ? -1 : 0);
        if ($ch === ',' && $depth === 0) { $out[] = trim($cur); $cur = ''; continue; }
        $cur .= $ch;
    }
    $out[] = trim($cur);
    return $out;
}

/** The declarations of the first rule in $css whose selector list has $selector as one of its items. */
function gkDtDecl(string $css, string $selector): array
{
    preg_match_all('/(?:^|\n)\s*([^{}@]+?)\s*\{([^{}]*)\}/', $css, $m, PREG_SET_ORDER);
    foreach ($m as [, $list, $body]) {
        if (in_array($selector, gkDtSelectors($list), true)) {
            return gkCssDecl($body);
        }
    }
    return [];
}

/**
 * The value a padding (or any length list) of the block takes with the tokens at $y / $x:
 * "calc(var(--gk-table-pad-y) - 6px) calc(var(--gk-table-pad-x) - 6px)" => "6px 10px".
 * Knows var(), calc(), max(), + - * / and brackets — what the block writes, nothing more.
 */
function gkDtResolve(string $value, int $y, int $x): string
{
    $parts = [];
    $depth = 0;
    $cur = '';
    foreach (str_split(trim($value)) as $ch) {
        $depth += $ch === '(' ? 1 : ($ch === ')' ? -1 : 0);
        if ($ch === ' ' && $depth === 0) { if ($cur !== '') $parts[] = $cur; $cur = ''; continue; }
        $cur .= $ch;
    }
    if ($cur !== '') $parts[] = $cur;
    return implode(' ', array_map(static function (string $p) use ($y, $x): string {
        $expr = str_replace(['var(--gk-table-pad-y)', 'var(--gk-table-pad-x)', 'calc(', 'px'], [(string) $y, (string) $x, '(', ''], $p);
        $tokens = preg_split('/\s*([()+\-*\/,]|max)\s*/', $expr, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
        $i = 0;
        $sum = null;
        $atom = null;
        $atom = static function () use (&$tokens, &$i, &$sum, &$atom): float {
            $t = $tokens[$i++] ?? '';
            if ($t === '(') { $v = $sum(); $i++; return $v; }
            if ($t === 'max') { $i++; $a = $sum(); $i++; $b = $sum(); $i++; return max($a, $b); }
            if ($t === '-') return -$atom();
            return (float) $t;
        };
        $product = static function () use (&$tokens, &$i, $atom): float {
            $v = $atom();
            while (in_array($tokens[$i] ?? '', ['*', '/'], true)) { $op = $tokens[$i++]; $w = $atom(); $v = $op === '*' ? $v * $w : $v / $w; }
            return $v;
        };
        $sum = static function () use (&$tokens, &$i, $product): float {
            $v = $product();
            while (in_array($tokens[$i] ?? '', ['+', '-'], true)) { $op = $tokens[$i++]; $w = $product(); $v = $op === '+' ? $v + $w : $v - $w; }
            return $v;
        };
        $n = $sum();
        return ($n == (int) $n ? (string) (int) $n : (string) $n) . (preg_match('/^0$/', (string) $n) ? '' : 'px');
    }, $parts));
}

/** @return array<string,callable> */
return [

'the control is a labelled group of three buttons in the toolbar, hidden until the script shows it' => function (): void {
    Lang::set('en');
    $html = gkDtRender((new Table('d'))->setData(gkDtRows())->search(['name'])->column('name', 'Name')
        ->exportCsv('x.csv')->newButton('New')->densityToggle());
    T::contains($html, gkDtControl('Row spacing', 'Compact', 'Normal', 'Roomy'), 'the control is not written as it should be');
    $at = strpos($html, 'data-gk-density-toggle');
    T::ok($at > strpos($html, '<div class="gk-toolbar">'), 'the control is not in the toolbar');
    T::ok($at > strpos($html, 'data-gk-search') && $at > strpos($html, 'data-gk-export-csv'), 'the control is not after the search and the CSV button');
    T::ok($at < strpos($html, 'gk-toolbar-spacer') && $at < strpos($html, '<table class="gk-table'), 'the control is not before the new button and the table');
    T::eq(substr_count($html, 'data-gk-set-density='), 3, 'not three steps');
    T::notContains($html, 'aria-pressed="true"', 'a step is pressed before the script knows which one is on');
},

'the labels come from the catalogue, in German too, and are escaped' => function (): void {
    Lang::set('de');
    T::contains(gkDtRender((new Table('d'))->setData(gkDtRows())->column('name', 'Name')->densityToggle()),
        gkDtControl('Zeilenabstand', 'Kompakt', 'Normal', 'Weit'), 'the German control is not written as it should be');
    Lang::load('xx', ['table.density' => 'A "b" <c>', 'table.density_compact' => '<i>', 'table.density_normal' => '&', 'table.density_roomy' => "'r'"]);
    Lang::set('xx');
    T::contains(gkDtRender((new Table('d'))->setData(gkDtRows())->column('name', 'Name')->densityToggle()),
        gkDtControl('A &quot;b&quot; &lt;c&gt;', '&lt;i&gt;', '&amp;', '&#039;r&#039;'), 'a label is not escaped');
    Lang::set('en');
},

'the option adds the control and nothing else' => function (): void {
    Lang::set('en');
    $control = gkDtControl('Row spacing', 'Compact', 'Normal', 'Roomy');
    foreach (gkDtTables() as $name => $make) {
        $with = gkDtRender($make(true)->densityToggle());
        $without = gkDtRender($make(false));
        T::eq(substr_count($with, $control), 1, "$name: the control is not there once");
        T::eq(str_replace($control, '', $with), $without, "$name: the option changed more than the toolbar");
        T::notContains($without, 'density', "$name: a table without the option carries something of it");
    }
},

'without a toolbar there is no control, and an AJAX reload is the same with the option or without' => function (): void {
    Lang::set('en');
    T::notContains(gkDtRender((new Table('d'))->setData(gkDtRows())->column('name', 'Name')->toolbar(false)->densityToggle()),
        'data-gk-density', 'the control is there without a toolbar');

    $server = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? null;
    $get = $_GET;
    $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
    $_GET = ['gk_table' => 'd3'];
    try {
        $make = gkDtTables()['server rows, paged'];
        $fragment = gkDtRender($make(true)->densityToggle());
        T::ok($fragment !== '' && !str_contains($fragment, 'gk-toolbar'), 'the reload fragment is not the fragment');
        T::eq($fragment, gkDtRender($make(false)), 'the reload fragment changed with the option');
    } finally {
        if ($server === null) unset($_SERVER['HTTP_X_REQUESTED_WITH']); else $_SERVER['HTTP_X_REQUESTED_WITH'] = $server;
        $_GET = $get;
    }
},

'the page-wide density is what it was' => function (): void {
    // Theme keeps its state in statics; the test leaves them as it found them (light indigo, no density).
    Theme::set('indigo', 'light');
    Theme::density('konsole');
    T::contains(Theme::attributes(), 'data-gk-density="konsole"', 'Theme::density() no longer writes the attribute');
    Theme::density('normal');
    T::notContains(Theme::attributes(), 'data-gk-density', 'Theme::density() takes a step only the toggle knows');
    Theme::density('');
},

'the steps on a wrap are the numbers of themes.css, and Normal those of :root' => function (): void {
    $block = gkDtCssBlock();
    T::ok($block !== '', 'the 1.109.0 block is missing from gridkit.css');
    $themes = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents(__DIR__ . '/../css/themes.css'));
    $root = gkDtDecl(gkCssBare(), ':root');
    $tokens = ['--gk-table-pad-y', '--gk-table-pad-x', '--gk-label-radius'];
    foreach ($tokens as $t) T::ok(isset($root[$t]), "$t has no default on :root");
    foreach (['konsole', 'normal', 'weit'] as $step) {
        $mine = gkDtDecl($block, ':where(.gk-table-wrap)[data-gk-density="' . $step . '"]');
        T::eq(array_keys($mine), $tokens, "the step $step does not name the three tokens");
        // themes.css's own rule for the page, where it has one; a token it leaves alone is the default.
        $page = $step === 'normal' ? [] : gkDtDecl($themes, '[data-gk-density="' . $step . '"]');
        T::ok($step === 'normal' || $page !== [], "themes.css has no rule for $step");
        foreach ($tokens as $t) {
            T::eq($mine[$t] ?? null, $page[$t] ?? $root[$t] ?? '', "$step: $t is not the value of " . (isset($page[$t]) ? 'themes.css' : ':root'));
        }
    }
},

'at Normal every padding written against the tokens is the pixels of the rule it stands for' => function (): void {
    $block = gkDtCssBlock();
    $bare = gkCssBare();
    $before = substr($bare, 0, (int) strpos($bare, ':where(.gk-table-wrap)[data-gk-density="konsole"]'));
    // Desktop: each size and padding variant, top-level rules of the file before the block.
    foreach (['sm th', 'sm td', 'md th', 'md td', 'lg th', 'lg td', 'padded th', 'padded td', 'xs th', 'xs td',
              'compact th', 'compact td', 'very-compact th', 'very-compact td'] as $rule) {
        $old = gkCssDeclFor(".gk-table-$rule")['padding'] ?? null;
        $new = gkDtDecl($block, ":where(.gk-table-wrap[data-gk-density], [data-gk-density] .gk-table-wrap).gk-table-$rule")['padding'] ?? null;
        T::ok($old !== null && $new !== null, ".gk-table-$rule: a padding is missing (old " . var_export($old, true) . ', new ' . var_export($new, true) . ')');
        if ($old !== null && $new !== null) T::eq(gkDtResolve($new, 12, 16), $old, ".gk-table-$rule at Normal");
    }
    // The phone (the first 768px block that sets them).
    preg_match('/\n  \.gk-table th,\n  \.gk-table td \{\s*padding: ([^;]+);/', $before, $all);
    preg_match('/\n  \.gk-table-sm th,\n  \.gk-table-sm td \{\s*padding: ([^;]+);/', $before, $sm);
    T::ok(isset($all[1], $sm[1]), 'the phone paddings of the table are not where they were');
    $media = substr($block, (int) strpos($block, '@media (max-width: 768px)'));
    T::eq(gkDtResolve(gkDtDecl($media, ':where(.gk-table-wrap[data-gk-density]:not(.gk-table-list), [data-gk-density] .gk-table-wrap:not(.gk-table-list)) .gk-table td')['padding'] ?? '', 12, 16), $all[1] ?? '?', 'the phone padding at Normal');
    T::eq(gkDtResolve(gkDtDecl($media, ':where(.gk-table-wrap[data-gk-density], [data-gk-density] .gk-table-wrap).gk-table-sm td')['padding'] ?? '', 12, 16), $sm[1] ?? '?', 'the phone padding of sm at Normal');
    // The compact card (1.106.0).
    $cardSel = '.gk-table-mobile-card .gk-table tbody tr:not(.gk-table-group, .gk-table-more, .gk-empty-row)';
    $card = gkDtDecl($before, $cardSel);
    $field = gkDtDecl($before, $cardSel . ' > td');
    T::ok(($card['padding'] ?? '') !== '' && ($field['padding'] ?? '') !== '', 'the compact card is not where it was');
    $myCard = gkDtDecl($media, ':where(.gk-table-wrap[data-gk-density], [data-gk-density] .gk-table-wrap)' . $cardSel);
    $myField = gkDtDecl($media, ':where(.gk-table-wrap[data-gk-density], [data-gk-density] .gk-table-wrap)' . $cardSel . ' > td:where(:not(.gk-cb-col, .gk-actions))');
    T::eq(gkDtResolve(($myCard['padding-top'] ?? '?') . ' ' . ($myCard['padding-left'] ?? '?'), 12, 16), $card['padding'] ?? '', 'the card at Normal');
    T::eq(gkDtResolve($myCard['margin-bottom'] ?? '?', 12, 16), $card['margin-bottom'] ?? '', 'the space between cards at Normal');
    T::eq(gkDtResolve(($myField['padding-top'] ?? '?') . ' 0', 12, 16), $field['padding'] ?? '', 'a field of a card at Normal');
    // And the steps move every one of them: Compact is tighter than Normal is tighter than Roomy.
    $md = gkDtDecl($block, ':where(.gk-table-wrap[data-gk-density], [data-gk-density] .gk-table-wrap).gk-table-md td')['padding'] ?? '';
    T::eq([gkDtResolve($md, 8, 12), gkDtResolve($md, 16, 20)], ['8px 12px', '16px 20px'], 'md does not take the steps of themes.css');
    T::eq(gkDtResolve(gkDtDecl($block, ':where(.gk-table-wrap[data-gk-density], [data-gk-density] .gk-table-wrap).gk-table-xs td')['padding'] ?? '', 8, 12), '2px 4px', 'xs goes below its floor at Compact');
},

'a list steps by its row, and the page-wide rules are not touched' => function (): void {
    $block = gkDtCssBlock();
    $row = '.gk-table-list .gk-table tbody tr:not(.gk-table-group, .gk-table-more) > td';
    T::eq(gkDtDecl(gkCssBare(), $row)['height'] ?? null, '64px', 'a list row is no longer 64px');
    T::eq(gkDtDecl($block, ':where(.gk-table-wrap[data-gk-density="konsole"], [data-gk-density="konsole"] .gk-table-wrap:not([data-gk-density]))' . $row)['height'] ?? null, '52px', 'a compact list row');
    T::eq(gkDtDecl($block, ':where(.gk-table-wrap[data-gk-density="weit"], [data-gk-density="weit"] .gk-table-wrap:not([data-gk-density]))' . $row)['height'] ?? null, '76px', 'a roomy list row');
    // Every rule of the block that moves a table names the attribute on the wrap: without it, nothing here applies.
    preg_match_all('/(?:^|\n)\s*([^{}@]+?)\s*\{/', $block, $m);
    foreach ($m[1] as $list) {
        foreach (gkDtSelectors($list) as $sel) {
            if (str_contains($sel, 'data-gk-density-toggle')) continue; // the control itself
            T::ok(str_contains($sel, '.gk-table-wrap') && str_contains($sel, '[data-gk-density'), "a rule of the block reaches past the wrap: $sel");
        }
    }
},

'the script is one block at the end of gridkit.js, and every storage access is wrapped' => function (): void {
    $js = (string) file_get_contents(__DIR__ . '/../js/gridkit.js');
    $head = '// === 1.109.0 — density toggle (Table::densityToggle) ===';
    T::eq(substr_count($js, $head), 1, 'the block header is not there once');
    $at = (int) strpos($js, $head);
    // Up to the next block of the same kind (a later version's) or the end of the file.
    $next = strpos($js, "\n// === ", $at + 1);
    $block = (string) ($next === false ? substr($js, $at) : substr($js, $at, $next - $at));
    T::ok(strpos($js, '1.105.0 — additions that need script') < $at, 'the block is not after the 1.105.0 block');
    T::ok(str_ends_with(rtrim($block), '})();'), 'the block is not one closed function, or something other than a block follows it');
    T::eq(substr_count($js, 'data-gk-set-density'), substr_count($block, 'data-gk-set-density'), 'the steps are read outside the block');
    $code = (string) preg_replace(['#/\*.*?\*/#s', '#//[^\n]*#'], '', $block);
    $outside = (string) preg_replace('/try \{[^{}]*\}/', '', $code);
    T::ok(str_contains($code, 'localStorage'), 'the block stores nothing');
    T::notContains($outside, 'localStorage', 'localStorage is used outside a try');
    T::ok(str_contains($code, '_gkReady('), 'the first pass does not use the ready helper');
},

];
