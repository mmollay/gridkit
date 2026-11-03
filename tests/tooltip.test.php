<?php
/**
 * GridKit\Tooltip and the tooltip options of Button, Table and Form (1.112.0).
 *
 * Tooltips were attributes typed by hand, escaped by whoever remembered to. The helpers return
 * them escaped and checked; the components take 'tooltip' (Button, a Table column and row
 * button) and 'help' (a Form field). What the browser does with them — placement, timing,
 * keyboard, touch, contrast — is measured in ci/browser-tooltip.js; the markup, the escaping,
 * the warnings, the absence of a second popup (no title beside a tooltip) and that everything
 * without the options is written byte for byte as before are checked here.
 */

declare(strict_types=1);

use GridKit\{Button, Form, Lang, Table, Tooltip};

/** Run, and collect what it said with E_USER_WARNING. @return array{0:mixed,1:list<string>} */
function ttRun(callable $fn): array
{
    $notes = [];
    set_error_handler(static function (int $no, string $msg) use (&$notes): bool {
        if ($no !== E_USER_WARNING) return false;
        $notes[] = $msg;
        return true;
    });
    try {
        ob_start();
        try {
            $result = $fn();
        } finally {
            $out = (string) ob_get_clean();
        }
    } finally {
        restore_error_handler();
    }
    return [$result ?? $out, $notes];
}

function ttRows(): array
{
    return [
        ['id' => 1, 'name' => 'Ada', 'amount' => 12.5],
        ['id' => 2, 'name' => 'Bob', 'amount' => 3],
        ['id' => 3, 'name' => 'Cy', 'amount' => 0],
    ];
}

/** @return array<string,callable> */
return [

'attrs(): the text, escaped, and every option in one fixed order' => function (): void {
    Lang::set('en');
    T::eq(Tooltip::attrs('Net, before VAT'), ' data-gk-tooltip="Net, before VAT"', 'a plain tooltip');
    T::eq(Tooltip::attrs('a "b" <c> & \'d\''), ' data-gk-tooltip="a &quot;b&quot; &lt;c&gt; &amp; &#039;d&#039;"', 'quotes, angle brackets and ampersands are escaped');
    T::eq(
        Tooltip::attrs('Save', ['wrap' => true, 'delay' => 0, 'kbd' => 'Ctrl+S', 'max' => 240, 'tone' => 'info', 'pos' => 'auto']),
        ' data-gk-tooltip="Save" data-gk-tooltip-pos="auto" data-gk-tooltip-tone="info" data-gk-tooltip-max="240"'
        . ' data-gk-tooltip-kbd="Ctrl+S" data-gk-tooltip-delay="0" data-gk-tooltip-wrap',
        'all options, in the order pos, tone, max, kbd, delay, wrap'
    );
    T::eq(Tooltip::attrs(['text' => 'Hi', 'pos' => 'left']), ' data-gk-tooltip="Hi" data-gk-tooltip-pos="left"', 'the array form');
    T::eq(Tooltip::attrs(['text' => 'Hi', 'pos' => 'left'], ['pos' => 'right', 'tone' => 'light']),
        ' data-gk-tooltip="Hi" data-gk-tooltip-pos="left" data-gk-tooltip-tone="light"', 'the array\'s keys win, the second argument fills in');
    T::eq(Tooltip::attrs('x', ['max' => '20rem', 'kbd' => '<Ctrl>']), ' data-gk-tooltip="x" data-gk-tooltip-max="20rem" data-gk-tooltip-kbd="&lt;Ctrl&gt;"', 'a length and an escaped kbd');
    T::eq(Tooltip::attrs('x', ['wrap' => false]), ' data-gk-tooltip="x"', 'wrap false writes nothing');
    T::eq(Tooltip::attrs(''), '', 'no text, no tooltip');
    T::eq(Tooltip::attrs('   '), '', 'white space is no text');
    T::eq(Tooltip::attrs(['text' => '0']), ' data-gk-tooltip="0"', '"0" is a text');
    foreach (Tooltip::POSITIONS as $p) T::contains(Tooltip::attrs('x', ['pos' => $p]), 'data-gk-tooltip-pos="' . $p . '"', "pos $p");
    foreach (Tooltip::TONES as $t) T::contains(Tooltip::attrs('x', ['tone' => $t]), 'data-gk-tooltip-tone="' . $t . '"', "tone $t");
},

'a bad option is a warning and is left out; the tooltip still shows' => function (): void {
    $cases = [
        [['pos' => 'middle'], "'pos' => 'middle'"],
        [['tone' => 'red'], "'tone' => 'red'"],
        [['max' => 10], "'max' => 10"],
        [['max' => '50%; color: red'], "'max'"],
        [['max' => 'calc(1px)'], "'max'"],
        [['delay' => -1], "'delay' => -1"],
        [['delay' => '300'], "'delay' => '300'"],
        [['wrap' => 'yes'], "'wrap' => 'yes'"],
        [['kbd' => ['Ctrl', 'S']], "'kbd' => array"],
        [['position' => 'top'], "no option 'position'"],
    ];
    foreach ($cases as [$opts, $said]) {
        [$out, $notes] = ttRun(static fn () => Tooltip::attrs('Text', $opts));
        T::eq($out, ' data-gk-tooltip="Text"', 'left out: ' . json_encode($opts));
        T::eq(count($notes), 1, 'one warning for ' . json_encode($opts));
        T::contains($notes[0] ?? '', $said, 'the warning names the option');
        T::contains($notes[0] ?? '', 'GridKit:', 'and is GridKit\'s');
    }
    [$out, $notes] = ttRun(static fn () => Tooltip::attrs(['pos' => 'top']));
    T::eq($out, '', 'an array without text writes nothing');
    T::ok(count($notes) === 1 && str_contains($notes[0], "'text'"), 'and says where the text goes');
    [$out, $notes] = ttRun(static fn () => Tooltip::attrs(['text' => ['x']]));
    T::ok($out === '' && count($notes) === 1, 'a text that is not a string writes nothing, with a warning');
    T::eq(Tooltip::attrs(42), ' data-gk-tooltip="42"', 'a number is its digits');
    foreach ([true, new \stdClass()] as $odd) {
        [$out, $notes] = ttRun(static fn () => Tooltip::attrs($odd));
        T::ok($out === '' && count($notes) === 1, 'not a text (' . get_debug_type($odd) . '): a warning, no TypeError');
    }
    [$out, $notes] = ttRun(static fn () => Button::render('Go', ['tooltip' => true]));
    T::ok($out === '<button type="button" class="gk-btn gk-btn-filled gk-btn-primary"><span>Go</span></button>' && count($notes) === 1,
        'a component with a tooltip of the wrong type still renders, and says so');
},

'rich(): an id, with or without the hash; a bad one writes nothing' => function (): void {
    T::eq(Tooltip::rich('#vat-help'), ' data-gk-tooltip-rich="#vat-help"', '#id');
    T::eq(Tooltip::rich('vat-help', ['pos' => 'bottom', 'max' => 320, 'delay' => 100, 'tone' => 'light']),
        ' data-gk-tooltip-rich="#vat-help" data-gk-tooltip-pos="bottom" data-gk-tooltip-tone="light" data-gk-tooltip-max="320" data-gk-tooltip-delay="100"', 'id and options');
    [$out, $notes] = ttRun(static fn () => Tooltip::rich('#a b"c'));
    T::ok($out === '' && count($notes) === 1, 'not an id: nothing, and a warning');
    [$out, $notes] = ttRun(static fn () => Tooltip::rich('#x', ['kbd' => 'K', 'wrap' => true]));
    T::eq($out, ' data-gk-tooltip-rich="#x"', 'kbd and wrap are not for a rich tooltip');
    T::eq(count($notes), 2, 'each one says so');
},

'toggletip(): a text carries the CSS-only tooltip as its fallback, an #id does not' => function (): void {
    T::eq(Tooltip::toggletip('Opens on a click'), ' data-gk-toggletip="Opens on a click" data-gk-tooltip="Opens on a click"', 'text');
    T::eq(Tooltip::toggletip('#rules', ['pos' => 'bottom']), ' data-gk-toggletip="#rules" data-gk-tooltip-pos="bottom"', '#id: nothing to show without the script');
    T::contains(Tooltip::toggletip('#1 priority'), 'data-gk-tooltip="#1 priority"', 'a text that starts with # is a text');
    [$out, $notes] = ttRun(static fn () => Tooltip::toggletip('x', ['delay' => 5]));
    T::ok(!str_contains($out, 'delay') && count($notes) === 1, 'a toggletip has no delay — it opens on a click');
},

'help(): a 24px button named after what it explains, never by a title' => function (): void {
    Lang::set('en');
    $h = Tooltip::help('Reduced rate for food & books.', ['label' => 'VAT']);
    T::ok(str_starts_with($h, '<button type="button" class="gk-help" data-gk-toggletip="Reduced rate for food &amp; books."'), 'a type=button with the toggletip: ' . substr($h, 0, 120));
    T::contains($h, 'data-gk-tooltip="Reduced rate for food &amp; books."', 'and the CSS-only fallback');
    T::contains($h, 'aria-label="More about VAT"', 'named after its label');
    T::contains($h, 'aria-hidden="true" focusable="false"', 'the icon is decoration');
    T::contains($h, '<path d="M12 17h.01"/>', 'a question mark');
    T::notContains($h, 'title=', 'no title: a second popup');
    T::notContains($h, 'aria-expanded', 'aria-expanded comes with the script that can expand it');
    T::contains(Tooltip::help('x'), 'aria-label="More information"', 'without a label');
    T::contains(Tooltip::help('x', ['icon' => 'info']), '<path d="M12 16v-4"/>', 'an i');
    T::contains(Tooltip::help('x', ['label' => '"Q" <b>']), 'aria-label="More about &quot;Q&quot; &lt;b&gt;"', 'the label escaped');
    T::contains(Tooltip::help(['text' => 'x', 'tone' => 'warning', 'pos' => 'left']), 'data-gk-tooltip-pos="left" data-gk-tooltip-tone="warning"', 'the array form with options');
    T::eq(Tooltip::help(''), '', 'no text, no icon');
    [$out, $notes] = ttRun(static fn () => Tooltip::help('x', ['icon' => 'star', 'colour' => 'red']));
    T::contains((string) $out, '<path d="M12 17h.01"/>', 'a bad icon falls back to the question mark');
    T::eq(count($notes), 2, 'one warning for the icon, one for the unknown key — not two each');
    Lang::set('de');
    T::contains(Tooltip::help('x', ['label' => 'MwSt.']), 'aria-label="Mehr zu MwSt."', 'German');
    T::contains(Tooltip::help('x'), 'aria-label="Mehr Informationen"', 'German without a label');
    Lang::set('en');
},

'Button: tooltip instead of title — never both — and a description, not the name' => function (): void {
    Lang::set('en');
    $b = Button::render('Save', ['title' => 'Save draft', 'tooltip' => ['text' => 'Saves the draft', 'kbd' => 'Ctrl+S']]);
    T::eq($b, '<button type="button" class="gk-btn gk-btn-filled gk-btn-primary" data-gk-tooltip="Saves the draft" data-gk-tooltip-kbd="Ctrl+S"><span>Save</span></button>', 'a labelled button');
    $i = Button::icon('delete', ['tooltip' => 'Deletes the record']);
    T::contains($i, 'aria-label="Delete"', 'an icon-only button keeps its name from the icon');
    T::contains($i, 'data-gk-tooltip="Deletes the record"', 'the tooltip describes it');
    T::notContains($i, 'title=', 'no title');
    $t = Button::icon('delete', ['title' => 'Remove it', 'tooltip' => 'Removes it for good']);
    T::ok(str_contains($t, 'aria-label="Remove it"') && !str_contains($t, 'title='), 'a title still names it, and is not written beside the tooltip');
    $a = Button::render('Docs', ['href' => '/docs', 'tooltip' => 'Opens the manual']);
    T::eq($a, '<a href="/docs" class="gk-btn gk-btn-filled gk-btn-primary" data-gk-tooltip="Opens the manual"><span>Docs</span></a>', 'a link');
},

'Button without tooltip: byte for byte as before' => function (): void {
    Lang::set('en');
    T::eq(Button::render('Save', ['title' => 'Save draft']),
        '<button type="button" class="gk-btn gk-btn-filled gk-btn-primary" title="Save draft"><span>Save</span></button>', 'title');
    T::eq(Button::icon('delete', ['title' => 'Remove', 'onclick' => 'x()']),
        '<button type="button" class="gk-btn gk-btn-filled gk-btn-primary gk-btn-icon-only" aria-label="Remove" title="Remove" onclick="x()"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6h14Z"/></svg></button>',
        'icon-only with title and onclick');
    foreach ([[], ['title' => 'T'], ['icon' => 'edit'], ['href' => '/x', 'title' => 'T']] as $opts) {
        T::eq(Button::render('L', $opts + ['tooltip' => '']), Button::render('L', $opts), 'an empty tooltip is no tooltip: ' . json_encode($opts));
        T::eq(Button::render('L', $opts + ['tooltip' => []]), Button::render('L', $opts), 'an empty array neither');
    }
},

'Table: a column tooltip is a help icon beside the sort button, not inside it' => function (): void {
    Lang::set('en');
    [$html, $notes] = ttRun(static fn () => (new Table('tt1'))->setData(ttRows())
        ->column('name', 'Name', ['sortable' => true, 'tooltip' => 'As on the contract'])
        ->column('amount', 'Amount', ['tooltip' => ['text' => 'Net', 'tone' => 'info']])
        ->render());
    T::eq($notes, [], 'no warning');
    T::contains($html, '<button type="button" class="gk-sort-btn" data-gk-sort="name" data-gk-dir="asc">Name</button><button type="button" class="gk-help" data-gk-toggletip="As on the contract"',
        'the help icon follows the sort button');
    T::contains($html, 'aria-label="More about Name"', 'named after the column');
    T::contains($html, '<th scope="col">Amount<button type="button" class="gk-help" data-gk-toggletip="Net" data-gk-tooltip="Net" data-gk-tooltip-tone="info" aria-label="More about Amount">', 'a column that does not sort: after its label');
    T::ok(!preg_match('~class="gk-sort-btn"[^>]*>[^<]*<button~', $html), 'never a button in the sort button');
    // The data block carries the same markup for the rebuild after a sort.
    preg_match('~<script type="application/json" data-gk-data>(.*?)</script>~s', $html, $m);
    $data = json_decode($m[1] ?? '', true);
    T::eq($data['columns']['name']['tooltipHtml'] ?? null, Tooltip::help('As on the contract', ['label' => 'Name']), 'the rebuild gets the server\'s markup');
    T::ok(str_contains($html, (string) ($data['columns']['amount']['tooltipHtml'] ?? '-')), 'the same the header shows');
},

'Table: a row button tooltip replaces its title, in every row and after a sort' => function (): void {
    Lang::set('en');
    [$html, $notes] = ttRun(static fn () => (new Table('tt2'))->setData(ttRows())
        ->column('name', 'Name')
        ->button('edit', ['icon' => 'edit', 'tooltip' => 'Edit this person'])
        ->button('del', ['icon' => 'delete', 'title' => 'Remove', 'tooltip' => ['text' => 'Removes it', 'tone' => 'danger', 'pos' => 'sideways']])
        ->button('view', ['icon' => 'visibility', 'title' => 'Look'])
        ->render());
    T::eq(count($notes), 1, 'a bad option warns once — not once a row, not again for the data block');
    T::eq(substr_count($html, 'aria-label="Edit" data-gk-tooltip="Edit this person"'), 3, 'each row: the name, then the tooltip');
    T::eq(substr_count($html, 'aria-label="Remove" data-gk-tooltip="Removes it" data-gk-tooltip-tone="danger"'), 3, 'a title still names it');
    T::eq(substr_count($html, 'title="Remove"'), 0, 'and is not written beside the tooltip');
    T::eq(substr_count($html, 'aria-label="Look" title="Look"'), 3, 'a button without tooltip keeps its title');
    preg_match('~<script type="application/json" data-gk-data>(.*?)</script>~s', $html, $m);
    $data = json_decode($m[1] ?? '', true);
    T::eq($data['buttons']['edit']['tooltipAttrs'] ?? null, ' data-gk-tooltip="Edit this person"', 'the rebuild gets the attributes');
    T::ok(!isset($data['buttons']['view']['tooltipAttrs']), 'and nothing for a button without');
},

'Table without tooltips: no help icon, nothing added to the data block' => function (): void {
    $make = static fn (array $extra) => T::capture(static fn () => (new Table('tt3'))->setData(ttRows())
        ->column('name', 'Name', ['sortable' => true] + $extra)
        ->button('edit', ['icon' => 'edit', 'title' => 'Edit'] + $extra)
        ->render());
    $plain = $make([]);
    T::notContains($plain, 'gk-help', 'no icon');
    T::notContains($plain, 'tooltip', 'nothing about tooltips anywhere');
    // The data block hands every option to the client as given, this one too; apart from that
    // key an empty tooltip writes exactly what none writes.
    T::eq(str_replace(',"tooltip":""', '', $make(['tooltip' => ''])), $plain, 'an empty tooltip writes what none writes');
    T::eq(str_replace(',"tooltip":null', '', $make(['tooltip' => null])), $plain, 'null neither');
},

'Form: help is an icon beside the label, never inside it; a field without it is unchanged' => function (): void {
    Lang::set('en');
    $render = static fn (array $opts, string $type = 'email', string $label = 'Email') => T::capture(static fn () => (new Form('ff'))->field('email', $label, $type, $opts)->render());
    $with = $render(['help' => 'We never share it.', 'required' => true]);
    $without = $render(['required' => true]);
    T::contains($with, '<div class="gk-label-row"><label class="gk-label-text" id="email-label" for="email">Email <span class="gk-required" aria-hidden="true">*</span></label><button type="button" class="gk-help" data-gk-toggletip="We never share it."',
        'label and icon in a row, the icon after the label');
    T::contains($with, 'aria-label="More about Email"', 'named after the field');
    T::ok(!preg_match('~<label[^>]*>(?:(?!</label>).)*gk-help~s', $with), 'never inside a <label>: it would become part of the field\'s name');
    $help = Tooltip::help('We never share it.', ['label' => 'Email']);
    T::eq(str_replace(['<div class="gk-label-row">', $help . '</div>'], '', $with), $without, 'take the row and the icon away and it is the field without help, byte for byte');
    T::notContains($without, 'gk-label-row', 'no row without help');
    $cb = $render(['help' => 'Read the terms.'], 'checkbox', 'I agree');
    T::contains($cb, '</span></label><button type="button" class="gk-help"', 'a checkbox (no label above): the icon follows the control');
    T::contains($cb, 'aria-label="More about I agree"', 'named after the checkbox text');
    T::eq($render(['help' => '']), $render([]), 'an empty help is no help');
    T::contains($render(['help' => ['text' => '#iban-help', 'pos' => 'right']]), 'data-gk-toggletip="#iban-help" data-gk-tooltip-pos="right"', 'an element as content, and options');
},

'the language files name the help icon in English and German' => function (): void {
    $en = require __DIR__ . '/../lang/en.php';
    $de = require __DIR__ . '/../lang/de.php';
    T::eq([$en['tooltip.help'] ?? null, $en['tooltip.help_about'] ?? null], ['More information', 'More about {label}'], 'en');
    T::eq([$de['tooltip.help'] ?? null, $de['tooltip.help_about'] ?? null], ['Mehr Informationen', 'Mehr zu {label}'], 'de');
},

'the script and the stylesheet carry their 1.112.0 blocks at the end, with both dark spellings' => function (): void {
    $js = (string) file_get_contents(__DIR__ . '/../js/gridkit.js');
    $css = (string) file_get_contents(__DIR__ . '/../css/gridkit.css');
    $at = strpos($js, '// === 1.112.0 — tooltip engine (GK.tooltip, GK.tip, toggletips) ===');
    T::ok($at !== false && $at > strpos($js, '// === 1.110.0 — inline editing'), 'the script block is missing or not after the 1.110.0 block');
    $block = substr($js, (int) $at);
    foreach (['data-gk-tooltip-pos', 'data-gk-tooltip-tone', 'data-gk-tooltip-max', 'data-gk-tooltip-kbd', 'data-gk-tooltip-delay',
              'data-gk-tooltip-wrap', 'data-gk-toggletip', 'data-gk-tip-off', 'aria-describedby', 'aria-expanded', ':focus-visible',
              'tooltipHtml', 'tooltipAttrs', 'GK.tooltip.show', 'GK.tip.show'] as $needle) {
        T::contains($block, $needle, 'the script block handles ' . $needle);
    }
    $c = strpos($css, '/* === 1.112.0 — tooltip engine === */');
    T::ok($c !== false, 'the stylesheet block is missing');
    $sheet = substr($css, (int) $c);
    T::contains($sheet, '[data-gk-mode="dark"]', 'no [data-gk-mode="dark"] rule');
    T::contains($sheet, '.gk-dark ', 'no .gk-dark rule');
    T::contains($sheet, 'html[data-gk-tt] [data-gk-tooltip]::after', 'the CSS-only ::after is not switched off under the engine');
    T::contains($sheet, 'prefers-reduced-motion', 'reduced motion');
    // The CSS-only tooltip itself is untouched: without the mark of the script it is what it was.
    T::ok(strpos($css, '[data-gk-tooltip]::after {') < $c, 'the CSS-only rules stay where they were');
},

];
