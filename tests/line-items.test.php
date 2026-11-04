<?php
/**
 * GridKit\LineItems (1.117.0): the editor of the lines of an invoice.
 *
 * What the script does with the markup is checked in a browser (ci/browser-line-items.js), and
 * there the browser's figures are held against these. Here: the markup the script finds, the
 * escaping, the POST it makes and what parse() makes of it, reading numbers (hostile ones too),
 * and above all the arithmetic — exact cents in every case an invoice runs into, against a second
 * implementation written differently (plain ints) on thousands of random lines.
 */

declare(strict_types=1);

use GridKit\{Form, Lang, LineItems};

/** Render, and keep every warning instead of printing it. @return array{0:string,1:list<string>} */
function liRender(callable $fn): array
{
    $warnings = [];
    set_error_handler(static function (int $n, string $m) use (&$warnings): bool {
        $warnings[] = $m;
        return true;
    });
    try {
        $html = T::capture($fn);
    } finally {
        restore_error_handler();
    }
    return [$html, $warnings];
}

/** The warnings a call raises. @return list<string> */
function liWarnings(callable $fn): array
{
    return liRender($fn)[1];
}

function liInvoice(): LineItems
{
    return (new LineItems('items'))->invoiceColumns()->locale('de')->vatRates([20, 10])->items([
        ['id' => 7, 'description' => "Beratung\nVor Ort", 'qty' => '3', 'unit' => 'h', 'price' => '120', 'discount' => '10', 'vat' => '20'],
        ['description' => 'Fachbuch', 'qty' => 2, 'unit' => 'pcs', 'price' => 39.9, 'discount' => '', 'vat' => 10],
    ]);
}

/** The decoded data-gk-lines of the first editor in $html. */
function liConfig(string $html): array
{
    preg_match('/data-gk-lines="([^"]*)"/', $html, $m);
    return (array) json_decode(html_entity_decode($m[1] ?? '', ENT_QUOTES), true);
}

/**
 * A second implementation of the 'line' rounding for net prices, written the plain way: ints,
 * intdiv, and the ranges kept small enough that nothing leaves 64 bits. If both say the same on
 * thousands of random lines, neither has a slip the other lacks.
 */
function liReference(array $rows, ?int $discountBp, ?int $shippingCents, ?int $shippingRateBp): array
{
    $round = static function (int $n, int $d): int {
        $q = intdiv(abs($n), $d);
        if (2 * (abs($n) % $d) >= $d) $q++;
        return $n < 0 ? -$q : $q;
    };
    $groups = [];
    foreach ($rows as [$q, $p, $d, $r]) {   // qty ×10^4, price ×10^4, discount ×10^2, rate ×10^2
        $groups[$r] = ($groups[$r] ?? 0) + $round($q * $p * (10000 - $d), 10 ** 10);
    }
    $sub = array_sum($groups);
    $disc = 0;
    if ($discountBp) foreach ($groups as $r => $s) { $off = $round($s * $discountBp, 10000); $groups[$r] -= $off; $disc -= $off; }
    if ($shippingCents) {
        $rate = $shippingRateBp ?? ($groups ? max(0, max(array_keys($groups))) : 0);
        $groups[$rate] = ($groups[$rate] ?? 0) + $shippingCents;
    }
    $vat = 0;
    foreach ($groups as $r => $s) $vat += $round($s * $r, 10000);
    $c = static fn(int $n): string => ($n < 0 ? '-' : '') . intdiv(abs($n), 100) . '.' . str_pad((string) (abs($n) % 100), 2, '0', STR_PAD_LEFT);
    return ['subtotal' => $c($sub), 'discount' => $c($disc), 'net' => $c(array_sum($groups)), 'vatTotal' => $c($vat), 'gross' => $c(array_sum($groups) + $vat)];
}

/** @return array<string,callable> */
return [

// ── Markup ────────────────────────────────────────────────────────────────

'the editor: a named group, a heading for the eye, an ordered list of lines the sortable container moves' => function (): void {
    Lang::set('en');
    [$html, $w] = liRender(fn() => liInvoice()->render());
    T::eq($w, [], 'no warning');
    T::contains($html, '<div class="gk-lines" id="gk-lines-items" role="group" aria-label="Line items"', 'the root');
    T::contains($html, 'style="--gk-lines-cols: var(--gk-lines-pos) minmax(9rem, 2fr) 4.5rem 7rem 7rem 4.25rem 7rem 7.5rem var(--gk-lines-act)"', 'one track per column');
    T::contains($html, '<div class="gk-lines-head" aria-hidden="true">', 'the heading is for the eye');
    T::contains($html, '<ol class="gk-lines-body" data-gk-sortierbar>', 'js/sortierbar.js orders the lines');
    T::eq(substr_count($html, '<li class="gk-lines-row" data-gk-sort-id="r'), 2, 'one entry per line, keyed');
    T::contains($html, '<div class="gk-lines-line" role="group" aria-label="Line 1">', 'each line is a group named by its number');
    T::contains($html, 'data-gk-sort-titel="Line 1: Beratung"', 'the handle names the line by its description');
    $cfg = liConfig($html);
    T::eq($cfg['fmt'] ?? null, ['dec' => ',', 'thou' => '.', 'money' => "{value}\u{00A0}€"], 'the number shapes of de travel to the script');
    T::eq(array_column($cfg['columns'] ?? [], 'key'), ['description', 'qty', 'unit', 'price', 'discount', 'vat', 'total'], 'invoiceColumns()');
    T::eq(array_column($cfg['columns'] ?? [], 'role'), [null, 'qty', null, 'price', 'discount', 'vat', 'total'], '…with their roles');
},

'every field posts name[i][field], has a label of its own and a message line keyed like the POST' => function (): void {
    Lang::set('en');
    $html = str_replace("\u{00A0}", '&nbsp;', T::capture(fn() => liInvoice()->render()));   // the no-break spaces, visible
    $body = substr($html, 0, (int) strpos($html, '<template'));
    preg_match_all('/<(?:input|select|textarea)[^>]* id="([^"]+)" name="([^"]+)"/', $body, $m, PREG_SET_ORDER);
    T::ok(count($m) === 12, 'twelve fields in two lines, found ' . count($m));
    foreach ($m as [, $id, $name]) {
        T::ok(preg_match('/^items\[[01]\]\[(description|qty|unit|price|discount|vat)\]$/', $name) === 1, "name $name");
        T::contains($body, '<label class="gk-lines-label" for="' . $id . '">', "a label for $id");
        T::contains($body, 'id="' . $id . '-error" role="alert" data-gk-error="' . $name . '"', "the message line of $name");
    }
    preg_match_all('/ id="([^"]+)"/', $body, $ids);
    T::eq(count($ids[1]), count(array_unique($ids[1])), 'no id twice');
    T::contains($body, '<input type="hidden" data-gk-lines-field="id" name="items[0][id]" value="7">', 'a line with an id posts it back');
    T::notContains($body, 'name="items[1][id]"', 'a line without one posts none');
    T::contains($body, 'inputmode="decimal"', 'figures get the number pad on a phone');
    T::contains($body, 'value="120,00"', 'a price in the page\'s shape, padded to its decimals');
    T::contains($body, 'value="39,90"', 'a float from the database, the same');
    T::contains($body, '<option value="10" selected>10&nbsp;%</option>', 'an int rate selects its option');
    T::contains($body, '<option value="h" selected>h</option>', 'the unit');
    T::contains($html, '<template data-gk-lines-template><li class="gk-lines-row" data-gk-sort-id="new"', 'an empty line to add');
    T::contains($html, 'data-gk-lines-action="add"', 'the add button');
    T::contains($html, '<p class="gk-sr-only" aria-live="polite" data-gk-lines-live></p>', 'a live region for the total');
},

'the totals as PHP computes them, in the page\'s shape' => function (): void {
    Lang::set('de');
    $html = str_replace("\u{00A0}", '&nbsp;', T::capture(fn() => liInvoice()->discount(5)->shipping('4.90')->render()));
    // 3 × 120 − 10 % = 324; 2 × 39,90 = 79,80; −5 % per rate; shipping to 20 %.
    T::contains($html, '<span class="gk-lines-out gk-td-num" data-gk-lines-out="total">324,00&nbsp;€</span>', 'a line total');
    T::contains($html, 'data-gk-lines-sum="subtotal"><dt>Zwischensumme (netto)</dt><dd class="gk-td-num">403,80&nbsp;€</dd>', 'subtotal');
    T::contains($html, 'value="5"', 'the overall discount\'s field');
    T::contains($html, '<dd class="gk-td-num">-20,19&nbsp;€</dd>', 'the discount, per rate');
    T::contains($html, 'data-gk-lines-vat="20"><dt>USt. 20 %</dt><dd class="gk-td-num">62,54&nbsp;€</dd>', 'VAT 20 % on 312,70');
    T::contains($html, 'data-gk-lines-vat="10"><dt>USt. 10 %</dt><dd class="gk-td-num">7,58&nbsp;€</dd>', 'VAT 10 % on 75,81');
    T::contains($html, 'gk-lines-grand" data-gk-lines-sum="gross"><dt>Gesamtbetrag</dt><dd class="gk-td-num">458,63&nbsp;€</dd>', 'the total');
    Lang::set('en');
},

'everything a caller hands in is escaped' => function (): void {
    Lang::set('en');
    $evil = '"><script>alert(1)</script>&';
    [$html] = liRender(fn() => (new LineItems('items'))
        ->column('description', $evil, ['type' => 'text', 'placeholder' => $evil])
        ->column('unit', $evil, ['type' => 'select', 'options' => [$evil => $evil]])
        ->column('sku', $evil, ['type' => 'readonly'])
        ->column('price', 'P', ['type' => 'money'])
        ->label($evil)
        ->items([['description' => $evil, 'unit' => $evil, 'sku' => $evil, 'price' => $evil, 'id' => $evil]])
        ->errors(['items[0][description]' => $evil])
        ->render());
    T::notContains($html, '<script>alert', 'no tag gets through');
    T::notContains($html, '"><script', 'no attribute is broken out of');
    T::eq(substr_count($html, '<script'), 0, 'not one script tag');
    T::contains($html, '&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;&amp;', 'the text is shown, escaped');
    $cfg = liConfig($html);
    T::eq($cfg['columns'][0]['label'] ?? null, $evil, 'the config carries the label as it was (JSON in an attribute, escaped twice over)');
    [$inv] = liRender(fn() => (new LineItems('items'))->invoiceColumns()->items([['description' => "</textarea><script>alert(2)</script>", 'qty' => '1"><b>']])->render());
    T::contains($inv, '>&lt;/textarea&gt;&lt;script&gt;alert(2)&lt;/script&gt;</textarea>', 'a description cannot close its textarea');
    T::contains($inv, 'value="1&quot;&gt;&lt;b&gt;"', 'a figure that is none is shown as typed, escaped');
    T::notContains($inv, '<script>alert(2)', 'no tag from a description');
    [$ro] = liRender(fn() => (new LineItems('items'))->column('description', 'D', ['type' => 'textarea'])
        ->items([['description' => "<b>a</b>\n<i>b</i>"]])->readonly()->render());
    T::contains($ro, '<span class="gk-lines-out">&lt;b&gt;a&lt;/b&gt;</span><span class="gk-lines-sub">&lt;i&gt;b&lt;/i&gt;</span>', 'read-only text, both lines');
},

'limits() starts with empty lines; only those it asks for are required, the rest of a blank line is not' => function (): void {
    Lang::set('en');
    $html = T::capture(fn() => (new LineItems('l'))->invoiceColumns()->limits(2, 3)->render());
    T::eq(substr_count($html, '<li class="gk-lines-row" data-gk-sort-id="r'), 2, 'two empty lines');
    T::eq(substr_count(substr($html, 0, (int) strpos($html, '<template')), ' required '), 8, 'both of them required (4 fields each): the limit asks for them');
    $one = T::capture(fn() => (new LineItems('l'))->invoiceColumns()->limits(1)->items([
        ['description' => 'A', 'qty' => '1', 'price' => '1', 'vat' => '20'], ['description' => '', 'qty' => '1', 'price' => '', 'vat' => '20'],
    ])->render());
    $at = (int) strpos($one, 'data-gk-sort-id="r');
    $first = substr($one, $at, (int) strpos($one, '</li>', $at) - $at);
    $from = (int) strpos($one, 'data-gk-sort-id="r2"');
    $second = substr($one, $from, (int) strpos($one, '</li>', $from) - $from);
    T::ok(strlen($second) > 1000 && strlen($first) > 1000, 'both lines found');
    T::eq(substr_count($first, ' required '), 4, 'the first line, filled, requires its four fields');
    T::eq(substr_count($second, ' required '), 0, 'a blank second line requires nothing');
    T::eq(substr_count($second, ' data-gk-lines-required'), 4, '…but says which fields would be, for the script');
    T::contains($html, 'data-gk-lines-empty hidden', 'no empty state with lines');
    $empty = T::capture(fn() => (new LineItems('e'))->invoiceColumns()->render());
    T::contains($empty, '<div class="gk-empty" data-gk-lines-empty><div class="gk-empty-inner">', 'the empty state without');
    $full = T::capture(fn() => (new LineItems('f'))->invoiceColumns()->limits(0, 1)->items([['description' => 'x']])->render());
    T::ok((bool) preg_match('/<button type="button" class="[^"]*" disabled data-gk-lines-action="add">/', $full), 'add is off at the maximum');
},

'read-only: the same grid, the figures as text, nothing that posts' => function (): void {
    Lang::set('en');
    $html = str_replace("\u{00A0}", '&nbsp;', T::capture(fn() => liInvoice()->discount(3)->readonly()->render()));
    foreach (['<input', '<select', '<textarea', '<button', '<template', 'data-gk-sortierbar', ' name='] as $no) {
        T::notContains($html, $no, "no $no");
    }
    T::contains($html, 'class="gk-lines gk-lines-readonly"', 'the modifier');
    T::contains($html, 'style="--gk-lines-cols: var(--gk-lines-pos) minmax(9rem, 2fr) 4.5rem 7rem 7rem 4.25rem 7rem 7.5rem"', 'the same tracks, no actions');
    T::contains($html, 'data-gk-lines-value="120"><span class="gk-lines-label">Unit price</span><span class="gk-lines-out gk-td-num">120,00&nbsp;€</span>', 'a price as money');
    T::contains($html, '<span class="gk-lines-out">Beratung</span><span class="gk-lines-sub">Vor Ort</span>', 'the description\'s second line beneath it');
    T::contains($html, 'data-gk-lines-sum="discount" data-gk-lines-value="3"><dt>Discount 3&nbsp;%</dt>', 'the discount with its rate, in machine form for the script');
},

'errors(): by POST name or with dots; the cell shows it and is marked' => function (): void {
    Lang::set('en');
    $html = T::capture(fn() => liInvoice()->errors(['items.1.qty' => 'Only 1 left.', 'items[0][price]' => 'Too low.', 'items' => 'Check.', 'other' => 'x'])->render());
    T::ok((bool) preg_match('/name="items\[1\]\[qty\]"[^>]*aria-invalid="true"/', $html), 'the field is marked');
    T::contains($html, 'data-gk-error="items[1][qty]">Only 1 left.</div>', 'its message (dots turned into brackets)');
    T::contains($html, 'data-gk-error="items[0][price]">Too low.</div>', 'by POST name');
    T::contains($html, 'class="gk-input gk-td-num gk-has-error"', 'the class GK.form clears');
    T::contains($html, 'data-gk-error="items">Check.</div>', 'the editor\'s own message');
},

'in a Form: a field of type lineitems, named by its label, filled by values()' => function (): void {
    Lang::set('en');
    $li = (new LineItems('items'))->invoiceColumns();
    [$html, $w] = liRender(fn() => (new Form('f'))->field('items', 'Positions', 'lineitems', ['lines' => $li])
        ->values(['items' => [['description' => 'From values()', 'qty' => '2', 'price' => '5', 'vat' => '20']]])->render());
    T::eq($w, [], 'no warning');
    T::contains($html, '<label class="gk-label-text" id="items-label">Positions</label>', 'the label, without a for= nothing could take');
    T::contains($html, 'role="group" aria-labelledby="items-label" aria-describedby="items-error"', 'the editor named by it, described by the field\'s message line');
    T::eq(substr_count($html, 'data-gk-error="items"'), 1, 'one message line for the whole: the Form\'s');
    T::contains($html, '>From values()</textarea>', 'values() fills the lines');
    [, $w2] = liRender(fn() => (new Form('f'))->field('items', 'X', 'lineitems')->render());
    T::ok(count($w2) === 1 && str_contains($w2[0], "needs 'lines' => new LineItems"), 'without an editor: said, and left out');
},

// ── Reading a POST ────────────────────────────────────────────────────────

'parse(): machine numbers, blank lines dropped, the totals, every message keyed like the POST' => function (): void {
    Lang::set('en');
    $in = liInvoice()->discount(0)->limits(1)->parse([
        'items' => [
            ['id' => '7', 'description' => "  Beratung\r\nVor Ort ", 'qty' => '1.234,5', 'unit' => 'h', 'price' => '12,5', 'discount' => '', 'vat' => '20'],
            ['description' => '', 'qty' => '1', 'unit' => '', 'price' => '', 'discount' => '', 'vat' => '20'],
            ['description' => '', 'qty' => 'abc', 'unit' => 'kg', 'price' => '1,234', 'discount' => '120', 'vat' => '99'],
        ],
        'items_discount' => '2,5',
    ]);
    T::eq($in['items'][0], ['id' => '7', 'description' => "Beratung\nVor Ort", 'qty' => '1234.5', 'unit' => 'h', 'price' => '12.5',
        'discount' => '', 'vat' => '20', 'total' => '15431.25'], 'the first line, ready to store');
    T::eq(count($in['items']), 2, 'the blank second line is gone');
    T::eq(count($in['rows']), 3, '…but not from the rows, which keep the POST\'s numbering for a second showing');
    T::eq($in['rows'][2]['qty'], 'abc', 'a figure that is none stays as typed');
    T::eq($in['errors'], [
        'items[2][qty]' => 'Enter a number, such as 1.234,50.',
        'items[2][unit]' => 'Choose an entry of the list.',
        'items[2][price]' => 'At most 2 decimal places.',
        'items[2][discount]' => 'At most 100.',
        'items[2][vat]' => 'Choose an entry of the list.',
        'items[2][description]' => 'Description is required.',
    ], 'every problem under its field, the posted index');
    T::eq($in['discount'], '2.5', 'the overall discount');
    T::eq($in['totals']['subtotal'], '15431.25', 'the totals of the items');
    T::eq($in['totals']['discount'], '-385.78', '2.5 % of it');
},

'parse() of hostile POSTs: nothing throws, nothing gets through' => function (): void {
    Lang::set('en');
    $li = liInvoice()->limits(0, 5);
    foreach ([
        ['items' => 'x'],
        ['items' => ['x', 7, null]],
        ['items' => [['qty' => ['1'], 'description' => ['a' => 'b'], 'price' => new stdClass()]]],
        ['items' => [['description' => 'ok', 'qty' => "1\0", 'price' => str_repeat('9', 100), 'vat' => '20']]],
        ['items' => [['description' => "\xff\xfe", 'qty' => '1e308', 'price' => '-0', 'vat' => '20']]],
        ['items_discount' => ['1'], 'items_shipping' => '1'],
        [],
    ] as $k => $post) {
        [, $w] = liRender(function () use ($li, $post, &$out): void { $out = $li->parse($post); });
        T::eq($w, [], "post $k: no warning");
        T::ok(is_array($out['totals'] ?? null) && preg_match('/^-?\d+\.\d\d$/', $out['totals']['gross']) === 1, "post $k: totals still come out");
    }
    $many = $li->parse(['items' => array_fill(0, 2000, ['description' => 'x', 'qty' => '1', 'price' => '1', 'vat' => '20'])]);
    T::eq($many['errors']['items'] ?? '', 'At most 5 lines.', 'too many lines: said');
    T::eq(count($many['rows']), 5, '…and only as many read');
    $unbounded = (new LineItems('items'))->invoiceColumns()->parse(['items' => array_fill(0, 1500, ['description' => 'x', 'qty' => '1', 'price' => '1', 'vat' => '20'])]);
    T::eq(count($unbounded['rows']), 1000, 'without a maximum, a thousand at most');
},

// ── Numbers ───────────────────────────────────────────────────────────────

'parseNumber(): what people type, in both languages' => function (): void {
    $cases = [
        // input            de           en
        ['1.234,50',        '1234.5',    '1234.5'],
        ['1,234.50',        '1234.5',    '1234.5'],
        ['1234.5',          '1234.5',    '1234.5'],
        ['1234,5',          '1234.5',    '1234.5'],
        ['1 234,50',        '1234.5',    '1234.5'],
        ["1\u{00A0}234,50", '1234.5',    '1234.5'],
        ["1'234.50",        '1234.5',    '1234.5'],
        ['1.234',           '1234',      '1.234'],     // three digits after one sign: the language decides
        ['1,234',           '1.234',     '1234'],
        ['0,500',           '0.5',       '0.5'],       // a leading 0 is no thousands group
        ['12.500,00',       '12500',     '12500'],
        ['1.234.567',       '1234567',   '1234567'],
        ['1.234.567,89',    '1234567.89', '1234567.89'],
        ['-5',              '-5',        '-5'],
        ["\u{2212}3,5",     '-3.5',      '-3.5'],
        ['+7',              '7',         '7'],
        ['-€5',             '-5',        '-5'],
        ['€ 1.234,50',      '1234.5',    '1234.5'],
        ['1.234,50 €',      '1234.5',    '1234.5'],
        ['EUR 10',          '10',        '10'],
        ['20 %',            '20',        '20'],
        ['.5',              '0.5',       '0.5'],
        ['5.',              '5',         '5'],
        ['-0',              '0',         '0'],
        ['00012,3400',      '12.34',     '12.34'],
    ];
    foreach ($cases as [$in, $de, $en]) {
        T::eq(LineItems::parseNumber($in, 'de'), $de, "de: $in");
        T::eq(LineItems::parseNumber($in, 'en'), $en, "en: $in");
    }
    T::eq(LineItems::parseNumber('1.234', 'de-AT'), '1234', 'a region keeps its language');
    T::eq(LineItems::parseNumber(12.5), '12.5', 'a float');
    T::eq(LineItems::parseNumber(1.005), '1.005', '…as it was meant, not as it is stored');
    T::eq(LineItems::parseNumber(0.1 + 0.2), '0.3', '…0.30000000000000004 too');
    T::eq(LineItems::parseNumber(-0.0), '0', 'no minus zero');
    // Twelve decimals, fixed — what (string) would write is the page's `precision` (14 digits) and an exponent.
    T::eq(LineItems::parseNumber(1e14), '100000000000000', 'a large float, not "1.0E+14"');
    T::eq(LineItems::parseNumber(123456789.123456789), '123456789.123456791043', 'the float as JavaScript\'s toFixed(12) writes it');
    T::eq(LineItems::parseNumber(0.1234567890123456), '0.123456789012', '…twelve decimals, not fourteen digits');
    T::eq(LineItems::parseNumber(42), '42', 'an int');
},

'parseNumber(): what is no number is null, hostile or not' => function (): void {
    foreach (['', ' ', ',', '.', 'abc', '1e5', '1E-3', '0x1A', '0b11', 'Infinity', 'NaN', '--5', '5-', '1,2,3', '12.34.5', '1..2',
              '1.000,000.5', '1,234.5.6', '1.234,5,6', '1,2.3', '€€5', "\u{0661}\u{0662}", '１２', '½', '1 2/3', '1;2', '<b>1</b>', "1\0", "\xff\xfe1",
              str_repeat('9', 16), '0.' . str_repeat('1', 13), str_repeat('1', 70), '1' . str_repeat(' ', 70) . '2'] as $in) {
        T::eq(LineItems::parseNumber($in, 'de'), null, 'de: ' . json_encode($in, JSON_INVALID_UTF8_SUBSTITUTE));
        T::eq(LineItems::parseNumber($in, 'en'), null, 'en: ' . json_encode($in, JSON_INVALID_UTF8_SUBSTITUTE));
    }
    foreach ([null, true, false, [], ['1'], new stdClass(), INF, NAN, 1e30] as $in) {
        T::eq(LineItems::parseNumber($in), null, 'not a string: ' . get_debug_type($in));
    }
    T::eq(LineItems::parseNumber(str_repeat('9', 15)), str_repeat('9', 15), 'fifteen digits are still a number');
    T::eq(LineItems::parseNumber(999999999999999), '999999999999999', '…an int of fifteen too');
    T::eq(LineItems::parseNumber(1000000000000000), null, '…an int of sixteen is not, as in the browser');
},

// ── The arithmetic ────────────────────────────────────────────────────────

'totals(): half a cent rounds away from zero, both ways' => function (): void {
    $t = LineItems::totals([['qty' => 1, 'price' => '0.005', 'vat' => 0], ['qty' => 1, 'price' => '-0.005', 'vat' => 10]]);
    T::eq(array_column($t['lines'], 'total'), ['0.01', '-0.01'], '0.005 → 0.01, −0.005 → −0.01');
    T::eq(LineItems::totals([['qty' => 1, 'price' => 1.005, 'vat' => 0]])['gross'], '1.01', '1.005 as a float: 1.01, not the 1.00 round(1.005, 2) gives');
    T::eq(LineItems::totals([['qty' => 3, 'price' => '0.1', 'vat' => 0], ['qty' => 1, 'price' => 0.2, 'vat' => 0]])['gross'], '0.50', 'no float drift');
    // VAT of exactly half a cent: 0.25 × 2 % = 0.005.
    T::eq(LineItems::totals([['qty' => 1, 'price' => '0.25', 'vat' => 2]])['vatTotal'], '0.01', 'VAT rounds half up too');
    T::eq(LineItems::totals([['qty' => 1, 'price' => '-0.25', 'vat' => 2]])['vatTotal'], '-0.01', '…and half down below zero');
},

'totals(): line or total rounding' => function (): void {
    $rows = array_fill(0, 3, ['qty' => '0.333', 'price' => '1', 'vat' => 20]);
    $line = LineItems::totals($rows);
    $total = LineItems::totals($rows, ['rounding' => 'total']);
    T::eq([$line['subtotal'], $line['vatTotal'], $line['gross']], ['0.99', '0.20', '1.19'], "'line': three times 0.33");
    T::eq([$total['subtotal'], $total['vatTotal'], $total['gross']], ['1.00', '0.20', '1.20'], "'total': 0.999 once");
    T::eq(array_column($total['lines'], 'total'), ['0.33', '0.33', '0.33'], 'the lines still show cents');
},

'totals(): several rates, a line discount, an overall discount per rate, shipping' => function (): void {
    $t = LineItems::totals([
        ['qty' => 2, 'price' => '100', 'discount' => '15', 'vat' => '20'],   // 170
        ['qty' => 1, 'price' => '50', 'vat' => '10'],                        // 50
        ['qty' => 4, 'price' => '2.5', 'vat' => '13'],                       // 10
        ['qty' => 1, 'price' => '30', 'vat' => '0'],                         // 30
        ['qty' => 1, 'price' => '7.7', 'vat' => '7.7'],                      // 7.70
        ['qty' => 0, 'price' => '999', 'vat' => '5.5'],                      // 0: no line of its own in the VAT
    ], ['discount' => '10', 'shipping' => '5.90']);
    T::eq($t['subtotal'], '267.70', 'subtotal');
    T::eq($t['discount'], '-26.77', '10 % of each rate: 17 + 5 + 1 + 3 + 0.77');
    T::eq($t['shipping'], '5.90', 'shipping');
    T::eq($t['vat'], [
        ['rate' => '20', 'base' => '158.90', 'amount' => '31.78'],   // 153 + 5.90 (the highest rate)
        ['rate' => '13', 'base' => '9.00', 'amount' => '1.17'],
        ['rate' => '10', 'base' => '45.00', 'amount' => '4.50'],
        ['rate' => '7.7', 'base' => '6.93', 'amount' => '0.53'],
        ['rate' => '0', 'base' => '27.00', 'amount' => '0.00'],
    ], 'one line per rate, highest first; a rate with nothing on it left out');
    T::eq([$t['net'], $t['vatTotal'], $t['gross']], ['246.83', '37.98', '284.81'], 'net, VAT, gross');
    $s = LineItems::totals([['qty' => 1, 'price' => '100', 'vat' => '20'], ['qty' => 1, 'price' => '100', 'vat' => '10']], ['shipping' => '10', 'shippingVat' => '10']);
    T::eq($s['vat'][1], ['rate' => '10', 'base' => '110.00', 'amount' => '11.00'], 'shippingVat names the rate');
    $only = LineItems::totals([], ['shipping' => '4.90', 'shippingVat' => '20']);
    T::eq([$only['gross'], $only['vat'][0]['rate'] ?? null], ['5.88', '20'], 'shipping alone, under its own rate');
},

'totals(): gross prices — the VAT is taken out, the total is what the lines say' => function (): void {
    $t = LineItems::totals([['qty' => 1, 'price' => '12', 'vat' => 20], ['qty' => 1, 'price' => '9.99', 'vat' => 20], ['qty' => 2, 'price' => '5.50', 'vat' => 10]], ['pricesNet' => false]);
    T::eq($t['gross'], '32.99', 'the total is the sum of the gross lines');
    T::eq($t['vat'], [['rate' => '20', 'base' => '18.32', 'amount' => '3.67'], ['rate' => '10', 'base' => '10.00', 'amount' => '1.00']],
        '21.99 × 20/120 = 3.665 → 3.67; 11 × 10/110 = 1');
    T::eq($t['net'], '28.32', 'net = gross − VAT');
    T::eq($t['subtotal'], '32.99', 'the subtotal is gross too');
    $d = LineItems::totals([['qty' => 1, 'price' => '120', 'vat' => 20]], ['pricesNet' => false, 'discount' => '10', 'shipping' => '6']);
    T::eq([$d['discount'], $d['gross'], $d['vatTotal'], $d['net']], ['-12.00', '114.00', '19.00', '95.00'], 'discount and shipping on gross figures');
},

'totals(): no overflow, however large' => function (): void {
    $t = LineItems::totals([['qty' => '999999999999999.9999', 'price' => '999999999999999.9999', 'discount' => '0.01', 'vat' => '20']]);
    // (10^19 − 1)² × 9999 / 10^10 cents: 9999·10^28 − 19998·10^9 + 0.0000009999, rounded.
    T::eq($t['lines'][0]['total'], '999899999999999999800020000000.00', 'fifteen digits times fifteen, to the cent');
    T::eq($t['gross'], '1199879999999999999760024000000.00', 'and its VAT');
    $t2 = LineItems::totals([['qty' => '123456789.1234', 'price' => '98765.4321', 'vat' => '0']]);
    T::eq($t2['gross'], '12193263123451.18', '123456789.1234 × 98765.4321 = 12193263123451.1782… (int arithmetic would overflow on the way)');
    $neg = LineItems::totals([['qty' => '-3', 'price' => '33.333', 'vat' => '10']]);
    T::eq([$neg['net'], $neg['vatTotal'], $neg['gross']], ['-100.00', '-10.00', '-110.00'], 'a credit line');
},

'totals(): columns of other names, a missing quantity, figures as people type them' => function (): void {
    $t = LineItems::totals([['menge' => '2', 'preis' => '1.234,50', 'ust' => '20']], ['keys' => ['qty' => 'menge', 'price' => 'preis', 'vat' => 'ust', 'discount' => null], 'locale' => 'de']);
    T::eq($t['net'], '2469.00', 'keys and locale');
    T::eq(LineItems::totals([['price' => '10', 'vat' => '20']], ['keys' => ['qty' => null]])['gross'], '12.00', 'no quantity column: one');
    T::eq(LineItems::totals([['qty' => 'two', 'price' => '10']])['gross'], '0.00', 'a figure that is none counts as 0');
    T::eq(LineItems::totals(['x', null, 5])['gross'], '0.00', 'rows that are none count as nothing');
    T::eq(LineItems::totals([['qty' => '1.00005', 'price' => '100']])['gross'], '100.01', 'a quantity is kept to 4 decimals, rounded half up');
},

'totals() against a second implementation on 3,000 random lines' => function (): void {
    mt_srand(1117);
    $diff = 0;
    $first = '';
    for ($set = 0; $set < 300; $set++) {
        $rows = [];
        $ref = [];
        for ($k = mt_rand(1, 10); $k > 0; $k--) {
            $q = mt_rand(-20000, 1000000);            // ±2, up to 100 with 4 decimals
            $p = mt_rand(-1000000, 10000000);         // up to 1000 with 4 decimals
            $d = mt_rand(0, 3) ? 0 : mt_rand(0, 10000);
            $r = [0, 500, 770, 1000, 1300, 2000][mt_rand(0, 5)];
            $dec = static fn(int $v, int $s): string => ($v < 0 ? '-' : '') . intdiv(abs($v), 10 ** $s) . '.' . str_pad((string) (abs($v) % 10 ** $s), $s, '0', STR_PAD_LEFT);
            $rows[] = ['qty' => $dec($q, 4), 'price' => $dec($p, 4), 'discount' => $dec($d, 2), 'vat' => $dec($r, 2)];
            $ref[] = [$q, $p, $d, $r];
        }
        $disc = mt_rand(0, 1) ? mt_rand(0, 3000) : null;
        $ship = mt_rand(0, 1) ? mt_rand(0, 9999) : null;
        $shipRate = mt_rand(0, 1) ? 1000 : null;
        $opts = ['discount' => $disc === null ? null : $disc / 100, 'shipping' => $ship === null ? null : $ship / 100, 'shippingVat' => $shipRate === null ? null : 10];
        $t = LineItems::totals($rows, $opts);
        $mine = array_intersect_key($t, array_flip(['subtotal', 'discount', 'net', 'vatTotal', 'gross']));
        $theirs = liReference($ref, $disc, $ship, $shipRate);
        ksort($mine);
        ksort($theirs);
        if ($mine !== $theirs) { $diff++; $first = $first ?: json_encode([$rows, $opts, $mine, $theirs]); }
    }
    T::eq($diff, 0, 'sets that differ — the first: ' . $first);
},

// ── Warnings, each with its fallback ──────────────────────────────────────

'what cannot be used is said, and something sensible happens instead' => function (): void {
    Lang::set('en');
    $cases = [
        "type 'money!'"              => fn() => (new LineItems('x'))->column('a', 'A', ['type' => 'money!']),
        "cannot have the role 'qty'" => fn() => (new LineItems('x'))->column('a', 'A', ['type' => 'text', 'role' => 'qty']),
        "both have the role 'price'" => fn() => (new LineItems('x'))->column('p1', 'P', ['type' => 'money', 'role' => 'price'])->column('p2', 'P', ['type' => 'money', 'role' => 'price']),
        "'decimals' => 7"            => fn() => (new LineItems('x'))->column('q', 'Q', ['type' => 'number', 'decimals' => 7]),
        "'min' that is not a number" => fn() => (new LineItems('x'))->column('q', 'Q', ['type' => 'number', 'min' => 'low']),
        "is not a grid track"        => fn() => (new LineItems('x'))->column('q', 'Q', ['width' => 'red;']),
        "has no 'url'"               => fn() => (new LineItems('x'))->column('a', 'A', ['type' => 'lookup']),
        "and not 'id'"               => fn() => (new LineItems('x'))->column('id', 'Id'),
        "is not a plain name"        => fn() => new LineItems('a[b]'),
        "locale 'fr' is not known"   => fn() => (new LineItems('x'))->locale('fr'),
        "is not an ISO code"         => fn() => (new LineItems('x'))->currency('Euro'),
        "rounding('cent')"           => fn() => (new LineItems('x'))->rounding('cent'),
        "limits(3, 1)"               => fn() => (new LineItems('x'))->limits(3, 1),
        "vatRates() takes numbers"   => fn() => (new LineItems('x'))->vatRates([20, -5, 'x']),
    ];
    foreach ($cases as $needle => $fn) {
        $w = liWarnings($fn);
        T::ok(count($w) >= 1 && str_contains(implode(' | ', $w), $needle), "warns: $needle — got " . implode(' | ', $w));
    }
    // The fallbacks.
    $html = '';
    liRender(function () use (&$html): void {
        $html = (new LineItems('x'))->column('a', 'A', ['type' => 'money!'])->column('q', 'Q', ['width' => 'red;', 'type' => 'number', 'decimals' => 9])
            ->rounding('cent')->locale('fr')->vatRates([-1])->html();
    });
    T::contains($html, 'data-gk-lines-cell="a"><label class="gk-lines-label" for="x-new-a">A</label><input type="text" class="gk-input" id="x-new-a"', 'an unknown type is a text column');
    T::contains($html, '--gk-lines-cols: var(--gk-lines-pos) minmax(9rem, 2fr) 4.5rem var(--gk-lines-act)', 'a bad width keeps the default track');
    T::eq(liConfig($html)['columns'][1]['decimals'] ?? null, 3, 'bad decimals: the default');
    T::eq(liConfig($html)['rounding'] ?? null, 'line', "an unknown rounding: 'line'");
    T::eq(liConfig($html)['fmt']['dec'] ?? null, '.', 'an unknown locale: English numbers');
},

'a currency and a language: where the sign goes' => function (): void {
    Lang::set('en');
    $t = static fn(string $loc, string $cur): string => T::capture(fn() => (new LineItems('m'))->invoiceColumns()->locale($loc)->currency($cur)
        ->items([['description' => 'x', 'qty' => 1, 'price' => '-1234.5', 'vat' => 0]])->render());
    T::contains($t('en', 'EUR'), '>-€1,234.50<', 'en, EUR');
    T::contains($t('de', 'EUR'), ">-1.234,50\u{00A0}€<", 'de, EUR');
    T::contains($t('en', 'USD'), '>-$1,234.50<', 'en, USD');
    T::contains($t('en', 'CHF'), ">-CHF\u{00A0}1,234.50<", 'a code is set apart');
},

'an unknown value of a select is shown, marked, and refused by parse()' => function (): void {
    Lang::set('en');
    $li = (new LineItems('u'))->invoiceColumns()->vatRates([20, 10])->items([['description' => 'x', 'qty' => 1, 'unit' => 'kg', 'price' => 1, 'vat' => '5.5']]);
    $html = T::capture(fn() => $li->render());
    T::contains($html, '<option value="kg" selected data-gk-lines-unknown>kg</option>', 'a unit no longer offered');
    T::contains($html, "<option value=\"5.5\" selected data-gk-lines-unknown>5.5\u{00A0}%</option>", 'a rate no longer offered');
    T::notContains(substr($html, 0, (int) strpos($html, '<template')), '<option value="20" selected>', 'nothing else is selected in its place');
    $in = $li->parse(['u' => [['description' => 'x', 'qty' => '1', 'unit' => 'kg', 'price' => '1', 'vat' => '5.5']]]);
    T::eq(array_keys($in['errors']), ['u[0][unit]', 'u[0][vat]'], 'parse() names both');
},

'the strings are in both languages, and German stays out of English' => function (): void {
    Lang::set('de');
    $de = T::capture(fn() => liInvoice()->discount(2)->shipping(1)->render());
    foreach (['Beschreibung', 'Menge', 'Einzelpreis', 'Position hinzufügen', 'Zwischensumme (netto)', 'USt. 20 %', 'Gesamtbetrag', 'Versand', 'Rabatt'] as $word) {
        T::contains($de, $word, "de: $word");
    }
    Lang::set('en');
    $en = T::capture(fn() => (new LineItems('items'))->invoiceColumns()->discount(2)->shipping(1)->items([['description' => 'x', 'qty' => 1, 'price' => 1, 'vat' => 20]])->render());
    T::ok(!preg_match('/[äöüÄÖÜß]/u', $en), 'no umlaut in English');
    foreach (['Beschreibung', 'Menge', 'Position', 'Gesamt', 'Rabatt', 'USt.'] as $word) T::notContains($en, $word, "en: no $word");
},

];
