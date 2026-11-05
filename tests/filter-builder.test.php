<?php
/**
 * GridKit\FilterBuilder (1.123.0) — conditions combined freely, read back safely.
 *
 * Checked here: the state in both shapes and every way it is read (clean(), fromRequest(), the form without
 * script with its commands), everything hostile input can try (unknown fields and operators, values of the
 * wrong type, depth, row and size bombs, broken UTF-8), toSql() — the exact SQL of every operator,
 * placeholders only, LIKE escaping, the column map and its refusals — run against SQLite and compared row
 * for row with matches(), the summary in both languages, the markup and its names, the warnings, the
 * stylesheet block, the words, the script's contract and the demo. What the script does in a browser —
 * instant rows, focus, keys, contrast, 390px, PHP = JS — is ci/browser-filter-builder.js.
 */

declare(strict_types=1);

use GridKit\{FilterBuilder, Lang};

require_once __DIR__ . '/css-helpers.php';

/** Run, and collect what it said with E_USER_WARNING. @return array{0:mixed,1:list<string>} */
function fbRun(callable $fn): array
{
    $notes = [];
    set_error_handler(static function (int $no, string $msg) use (&$notes): bool {
        if ($no !== E_USER_WARNING) return false;
        $notes[] = $msg;
        return true;
    });
    try {
        $out = $fn();
    } finally {
        restore_error_handler();
    }
    return [$out, $notes];
}

/** The invoice fields most tests use. */
function fbFields(): FilterBuilder
{
    return (new FilterBuilder('f'))
        ->field('status', 'Status', ['type' => 'select', 'options' => ['open' => 'Open', 'paid' => 'Paid', 'overdue' => 'Overdue', 7 => 'Seven']])
        ->field('amount', 'Amount', ['type' => 'money'])
        ->field('qty', 'Quantity', ['type' => 'number', 'operators' => ['eq', 'ne', 'lt', 'lte', 'gt', 'gte', 'between', 'empty', 'not_empty']])
        ->field('customer', 'Customer', ['operators' => ['contains', 'not_contains', 'equals', 'not_equals', 'starts', 'ends', 'empty', 'not_empty']])
        ->field('due', 'Due', ['type' => 'date', 'operators' => ['eq', 'ne', 'lt', 'lte', 'gt', 'gte', 'between', 'empty', 'not_empty']])
        ->field('tags', 'Tags', ['type' => 'multiselect', 'options' => ['vip' => 'VIP', 'eu' => 'EU', 'rc' => 'Reverse charge'],
            'operators' => ['has_any', 'has_all', 'has_none', 'empty', 'not_empty']])
        ->field('reminded', 'Reminded', ['type' => 'bool'])
        ->field('kind', 'Kind', ['type' => 'select', 'options' => ['a' => 'A', 'b' => 'B', 'c' => 'C'],
            'operators' => ['is', 'is_not', 'any_of', 'none_of', 'empty', 'not_empty']]);
}

/** The DOM of some markup. */
function fbDom(string $html): DOMXPath
{
    $doc = new DOMDocument();
    @$doc->loadHTML('<?xml encoding="utf-8"?><body>' . $html . '</body>', LIBXML_NOERROR);
    return new DOMXPath($doc);
}

/** The configuration the script reads. @return array<string,mixed> */
function fbConfig(string $html): array
{
    $x = fbDom($html);
    $form = $x->query('//form[@data-gk-filter-builder]')->item(0);
    return $form instanceof DOMElement ? (array) json_decode($form->getAttribute('data-gk-filter-builder'), true) : [];
}

$root = dirname(__DIR__);
$sheet = static function (): string {
    $css = gkCssText();
    $from = strpos($css, '/* === 1.123.0 — filter builder === */');
    if ($from === false) return '';
    $next = strpos($css, "\n/* === ", $from + 10);
    return (string) preg_replace('#/\*.*?\*/#s', '', $next === false ? substr($css, $from) : substr($css, $from, $next - $from));
};

return [

// ── The state ───────────────────────────────────────────────────────────

'both shapes are read, and the documented one comes back' => function (): void {
    $fb = fbFields();
    $readable = ['join' => 'and', 'rules' => [
        ['field' => 'status', 'op' => 'any_of', 'value' => ['open', 'overdue']],
        ['join' => 'or', 'rules' => [
            ['field' => 'amount', 'op' => 'between', 'value' => [500, 100]],
            ['field' => 'customer', 'op' => 'starts', 'value' => '  Eck '],
            ['field' => 'reminded', 'op' => 'true'],
        ]],
    ]];
    $want = ['join' => 'and', 'rules' => [
        ['field' => 'status', 'op' => 'any_of', 'value' => ['open', 'overdue']],
        ['join' => 'or', 'rules' => [
            ['field' => 'amount', 'op' => 'between', 'value' => [100, 500]],
            ['field' => 'customer', 'op' => 'starts', 'value' => 'Eck'],
            ['field' => 'reminded', 'op' => 'true'],
        ]],
    ]];
    T::eq(FilterBuilder::clean($readable, $fb), $want, 'the readable shape, checked: between put in order, text trimmed');
    $json = FilterBuilder::encode($want);
    T::eq($json, '["and",["status","any_of",["open","overdue"]],["or",["amount","between",[100,500]],["customer","starts","Eck"],["reminded","true"]]]', 'the compact JSON');
    T::eq(FilterBuilder::clean($json, $fb), $want, 'the compact JSON reads back to the same tree');
    T::eq(FilterBuilder::clean(json_decode($json, true), $fb), $want, 'and the compact array');
    T::eq(FilterBuilder::clean(['join' => 'OR', 'rules' => []], $fb), ['join' => 'or', 'rules' => []], 'the join in any case');
    T::eq(FilterBuilder::clean(null, $fb), ['join' => 'and', 'rules' => []], 'nothing: an empty AND group');
    T::eq(FilterBuilder::clean('not json', $fb), ['join' => 'and', 'rules' => []], 'not JSON: empty');
    T::eq(FilterBuilder::clean(['field' => 'status', 'op' => 'is', 'value' => 'open'], $fb), ['join' => 'and', 'rules' => []], 'a bare condition is no group');
},

'unknown fields, operators and values of the wrong type are left out' => function (): void {
    $fb = fbFields();
    $bad = ['and',
        ['nope', 'eq', 1],                         // unknown field
        ['status', 'gt', 'open'],                  // an operator of another type
        ['status', 'is', 'closed'],                // not an option
        ['status', 'is', true],                    // a boolean is no option key
        ['amount', 'gt', 'abc'],
        ['amount', 'gt', '1e5'],
        ['amount', 'gt', 1e20],
        ['amount', 'gt', '12.3456789'],            // more than six decimals
        ['amount', 'between', [1]],
        ['amount', 'between', [1, 2, 3]],
        ['due', 'eq', '2026-02-30'],
        ['due', 'eq', '26-1-5'],
        ['due', 'eq', ['2026-01-05']],
        ['customer', 'contains', ''],
        ['customer', 'contains', "  \t "],
        ['customer', 'contains', ['x']],
        ['status', 'any_of', []],
        ['status', 'any_of', ['zzz']],
        ['tags', 'has_any', 'nope'],
        ['customer', 'contains'],                  // no value where one is needed
        ['reminded', 'yes'],
        'a string',
        ['status'],
        [['field' => 'status']],
    ];
    T::eq(FilterBuilder::clean($bad, $fb), ['join' => 'and', 'rules' => []], 'nothing of it survives');
    T::eq(FilterBuilder::clean(['and', ['customer', 'contains', 1e20], ['status', 'is', 7.0], ['customer', 'contains', 0.1234567]], $fb)['rules'],
        [['field' => 'customer', 'op' => 'contains', 'value' => '100000000000000000000'], ['field' => 'status', 'op' => 'is', 'value' => '7'],
         ['field' => 'customer', 'op' => 'contains', 'value' => '0.123457']], 'a number as text: its digits without exponent, as the script writes them');
    $good = ['and',
        ['amount', 'gt', '19,99'], ['amount', 'gt', ' 100 '], ['amount', 'lt', -5], ['amount', 'eq', 2.0], ['qty', 'eq', '007'],
        ['status', 'is', 7], ['status', 'any_of', ['open', 'open', 'paid', 'nope']], ['status', 'any_of', 'paid'],
        ['due', 'eq', ' 2026-11-04 '], ['customer', 'contains', 'Ec' . "\x00\x07" . 'k'],
        ['reminded', 'false', 'ignored'],
    ];
    T::eq(FilterBuilder::clean($good, $fb)['rules'], [
        ['field' => 'amount', 'op' => 'gt', 'value' => 19.99],
        ['field' => 'amount', 'op' => 'gt', 'value' => 100],
        ['field' => 'amount', 'op' => 'lt', 'value' => -5],
        ['field' => 'amount', 'op' => 'eq', 'value' => 2],
        ['field' => 'qty', 'op' => 'eq', 'value' => 7],
        ['field' => 'status', 'op' => 'is', 'value' => '7'],
        ['field' => 'status', 'op' => 'any_of', 'value' => ['open', 'paid']],
        ['field' => 'status', 'op' => 'any_of', 'value' => ['paid']],
        ['field' => 'due', 'op' => 'eq', 'value' => '2026-11-04'],
        ['field' => 'customer', 'op' => 'contains', 'value' => 'Eck'],
        ['field' => 'reminded', 'op' => 'false'],
    ], 'what can be read is read: 19,99, spaces, an int key, a list without repeats, control characters out');
},

'a text value is at most 200 characters, and broken UTF-8 does not break it' => function (): void {
    $fb = fbFields();
    $t = FilterBuilder::clean(['and', ['customer', 'contains', str_repeat('ä', 300)]], $fb);
    T::eq(mb_strlen($t['rules'][0]['value']), 200, '200 characters, not bytes');
    $t = FilterBuilder::clean(['and', ['customer', 'contains', "Ab\xC3\x28cd"]], $fb);
    T::ok(isset($t['rules'][0]) && preg_match('//u', $t['rules'][0]['value']) === 1, 'broken UTF-8 becomes valid text');
    T::ok(json_encode($t) !== false, 'and can be encoded');
},

'depth, rows and size are limited, however the input is built' => function (): void {
    $fb = fbFields()->maxDepth(2)->maxRows(5);
    $deep = ['and', ['status', 'is', 'open'], ['or', ['amount', 'gt', 1], ['and', ['amount', 'gt', 2]]]];
    T::eq(FilterBuilder::encode(FilterBuilder::clean($deep, $fb)), '["and",["status","is","open"],["or",["amount","gt",1]]]', 'a group past maxDepth goes, with what is in it');
    $many = ['and'];
    for ($i = 0; $i < 50; $i++) $many[] = ['amount', 'gt', $i];
    T::eq(count(FilterBuilder::clean($many, $fb)['rules']), 5, 'maxRows: the first five');
    $nested = ['or'];
    for ($i = 0; $i < 30; $i++) $nested[] = ['and', ['amount', 'gt', $i], ['amount', 'lt', 99]];
    T::eq(FilterBuilder::encode(FilterBuilder::clean($nested, $fb)), '["or",["and",["amount","gt",0],["amount","lt",99]],["and",["amount","gt",1],["amount","lt",99]],["and",["amount","gt",2]]]', 'rows counted across groups');
    T::eq(FilterBuilder::clean(['and', ['or'], ['and', ['or']]], fbFields()), ['join' => 'and', 'rules' => []], 'empty groups go');

    // A nesting bomb: deeper than json_decode goes, and deeper than any limit.
    $bomb = str_repeat('["and",', 5000) . '["status","is","open"]' . str_repeat(']', 5000);
    [$t, $notes] = fbRun(fn () => FilterBuilder::clean($bomb, fbFields()));
    T::eq($t, ['join' => 'and', 'rules' => []], 'a nesting bomb is nothing');
    T::eq($notes, [], 'and says nothing: it is input');
    $big = '["and",' . implode(',', array_fill(0, 3000, '["status","is","open"]')) . ']';
    T::ok(strlen($big) > 8192, 'the probe is larger than 8 KB');
    T::eq(FilterBuilder::clean($big, fbFields()), ['join' => 'and', 'rules' => []], 'JSON over 8 KB is not read');
    $wide = ['and'];
    for ($i = 0; $i < 5000; $i++) $wide[] = ['nope', 'eq', $i];
    $t0 = microtime(true);
    T::eq(FilterBuilder::clean($wide, fbFields()), ['join' => 'and', 'rules' => []], '5000 unknown conditions: nothing');
    T::ok(microtime(true) - $t0 < 0.5, 'and quickly — at most 1000 nodes are looked at');
    $visits = array_merge(['and'], array_fill(0, 1200, ['nope', 'eq', 1]), [['status', 'is', 'open']]);
    T::eq(FilterBuilder::clean($visits, fbFields())['rules'], [], 'at most 1000 nodes are looked at: a condition after 1200 others is not reached');
    T::eq(count(FilterBuilder::clean(array_merge(['and'], array_fill(0, 900, ['nope', 'eq', 1]), [['status', 'is', 'open']]), fbFields())['rules']), 1, '…one after 900 is');
    $list = ['and', ['status', 'any_of', array_merge(array_fill(0, 100000, 'nope'), ['open'])]];
    T::eq(FilterBuilder::clean($list, fbFields())['rules'], [], 'a list is read up to 200 values: the option after 100000 is not reached');
},

'fromRequest reads the JSON parameter, POST before GET, or a default' => function (): void {
    $fb = fbFields();
    $t = FilterBuilder::fromRequest('f', $fb, ['request' => ['f' => '["and",["status","is","open"]]']]);
    T::eq($t['rules'], [['field' => 'status', 'op' => 'is', 'value' => 'open']], 'the JSON parameter');
    T::eq(FilterBuilder::fromRequest('f', $fb, ['request' => []]), ['join' => 'and', 'rules' => []], 'no parameter: empty');
    T::eq(FilterBuilder::fromRequest('f', $fb, ['request' => [], 'default' => '["and",["status","is","paid"]]'])['rules'][0]['value'], 'paid', 'no parameter: the default');
    T::eq(FilterBuilder::fromRequest('f', $fb, ['request' => ['f' => '["and"]'], 'default' => '["and",["status","is","paid"]]'])['rules'], [], 'an empty filter is a filter: not the default');
    T::eq(FilterBuilder::fromRequest('f', $fb, ['request' => ['f' => 42]]), ['join' => 'and', 'rules' => []], 'a number is nothing');
    [$saveGet, $savePost] = [$_GET, $_POST];
    try {
        $_GET = ['f' => '["and",["status","is","open"]]'];
        $_POST = ['f' => '["and",["status","is","paid"]]'];
        T::eq(FilterBuilder::fromRequest('f', $fb)['rules'][0]['value'], 'paid', 'POST before GET');
        $_POST = [];
        T::eq(FilterBuilder::fromRequest('f', $fb)['rules'][0]['value'], 'open', 'then GET');
    } finally {
        [$_GET, $_POST] = [$saveGet, $savePost];
    }
    // An array of fields instead of the builder.
    $t = FilterBuilder::fromRequest('q', ['city' => ['label' => 'City'], 'n' => ['type' => 'number']], ['request' => ['q' => '["and",["city","starts","Gr"],["n","gt","3"]]']]);
    T::eq($t['rules'], [['field' => 'city', 'op' => 'starts', 'value' => 'Gr'], ['field' => 'n', 'op' => 'gt', 'value' => 3]], 'fields as an array');
    [, $notes] = fbRun(fn () => FilterBuilder::fromRequest('f', $fb, ['request' => [], 'colour' => 1]));
    T::eq(count($notes), 1, 'an option fromRequest does not know is a warning');
    $t = FilterBuilder::fromRequest('f', ['s' => ['type' => 'select', 'options' => ['x' => 'X']]], ['request' => ['f' => '["and",["s","any_of",["x"]]]'], 'operators' => ['select' => ['is']]]);
    T::eq($t['rules'], [], "'operators' narrows a type: any_of is not offered, so it is left out");
},

'the form without script: nested arrays, and its commands' => function (): void {
    $fb = fbFields();
    $form = ['cmd' => 'apply', 'join' => 'or', 'rules' => [
        ['field' => 'status', 'op' => 'is', 'value' => 'open'],
        ['join' => 'and', 'rules' => [['field' => 'amount', 'op' => 'between', 'value' => ['10', '20,5']], ['field' => 'kind', 'op' => 'any_of', 'value' => ['a', 'c']]]],
    ]];
    $t = FilterBuilder::fromRequest('f', $fb, ['request' => ['f' => $form]]);
    T::eq(FilterBuilder::encode($t), '["or",["status","is","open"],["and",["amount","between",[10,20.5]],["kind","any_of",["a","c"]]]]', 'the form arrays read like the JSON');

    $run = static function (string $cmd) use ($form): string {
        $f = fbFields();
        return FilterBuilder::encode(FilterBuilder::clean((new ReflectionMethod(FilterBuilder::class, 'fromRaw'))->invoke(null, ['cmd' => $cmd] + $form,
            (new ReflectionMethod(FilterBuilder::class, 'spec'))->invoke($f), null), $f));
    };
    T::eq($run('remove:0'), '["or",["and",["amount","between",[10,20.5]],["kind","any_of",["a","c"]]]]', 'remove:0');
    T::eq($run('remove:1.0'), '["or",["status","is","open"],["and",["kind","any_of",["a","c"]]]]', 'remove:1.0');
    T::eq($run('dup:0'), '["or",["status","is","open"],["status","is","open"],["and",["amount","between",[10,20.5]],["kind","any_of",["a","c"]]]]', 'dup:0 puts the copy after it');
    T::eq($run('dup:1'), '["or",["status","is","open"],["and",["amount","between",[10,20.5]],["kind","any_of",["a","c"]]],["and",["amount","between",[10,20.5]],["kind","any_of",["a","c"]]]]', 'dup:1 copies a group');
    foreach (['remove:', 'remove:9', 'dup:7.7', 'add:5', 'remove:1.0.0.0.0.0.0', 'remove:../1', 'remove:-1', 'explode', 'add:1;DROP', ''] as $cmd) {
        T::eq($run($cmd), FilterBuilder::encode($t), "'$cmd' changes nothing");
    }

    // Removing the last condition of a group removes the group.
    $one = ['cmd' => 'remove:1.0', 'join' => 'and', 'rules' => [['field' => 'status', 'op' => 'is', 'value' => 'open'], ['join' => 'or', 'rules' => [['field' => 'reminded', 'op' => 'true']]]]];
    $h = (new FilterBuilder('f'))->field('status', 'Status', ['type' => 'select', 'options' => ['open' => 'Open']])->field('reminded', 'Reminded', ['type' => 'bool'])
        ->request(['f' => $one])->html();
    T::eq(substr_count($h, '<fieldset'), 1, 'a group left empty goes with its last condition');

    // add and group draw an empty condition, quietly; Apply says what is missing.
    $h = fbFields()->request(['f' => ['cmd' => 'add:', 'join' => 'and', 'rules' => [['field' => 'status', 'op' => 'is', 'value' => 'open']]]])->html();
    $x = fbDom($h);
    T::eq($x->query('//li[contains(@class,"gk-fb-row")]')->length, 2, 'add: a second row');
    T::eq($x->query('//select[@id="gk-fb-f-c1-field"]/option[@selected]')->item(0)?->getAttribute('value'), 'status', 'the first field');
    T::eq($x->query('//*[@aria-invalid]')->length, 0, 'a row just added is not called wrong');
    T::ok($x->query('//p[contains(@class,"gk-fb-note")][@hidden]')->length === 1, 'and the note stays hidden');
    $h = fbFields()->request(['f' => ['cmd' => 'group:', 'join' => 'and', 'rules' => [['field' => 'status', 'op' => 'is', 'value' => 'open']]]])->html();
    T::contains($h, '<select class="gk-filter gk-fb-join" id="gk-fb-f-g1-join" name="f[rules][1][join]"><option value="and">all conditions (AND)</option><option value="or" selected>', 'group: a new group joined the other way (OR inside AND)');
    $h = fbFields()->request(['f' => ['cmd' => 'apply', 'join' => 'and', 'rules' => [['field' => 'amount', 'op' => 'gt', 'value' => ''], ['field' => 'amount', 'op' => 'gt', 'value' => 'abc']]]])->html();
    $x = fbDom($h);
    T::eq($x->query('//input[@aria-invalid="true"]')->length, 2, 'Apply: both incomplete values are marked');
    T::contains($h, 'aria-describedby="gk-fb-f-c1-err"', 'and described by their sentence');
    T::contains($h, '<p class="gk-fb-error" id="gk-fb-f-c1-err"><svg', 'a symbol');
    T::contains($h, '<span>Enter a number, such as 100 or 19.99.</span></p>', 'and words — not the colour alone');
    T::contains($h, 'value="abc"', 'what was typed stays in the field');
    T::contains($h, '<p class="gk-fb-note" data-gk-fb-note><svg', 'the note is shown');
    T::contains($h, '2 conditions are incomplete and not applied.', 'and counts them');
    T::eq(fbFields()->request(['f' => ['cmd' => 'apply', 'rules' => [['field' => 'amount', 'op' => 'gt', 'value' => 'abc']]]])->tree()['rules'], [], 'an incomplete condition is not applied');

    // At maxRows a command adds nothing.
    $full = ['cmd' => 'add:', 'rules' => [['field' => 'status', 'op' => 'is', 'value' => 'open'], ['field' => 'status', 'op' => 'is', 'value' => 'paid']]];
    T::eq(fbDom(fbFields()->maxRows(2)->request(['f' => $full])->html())->query('//li[contains(@class,"gk-fb-row")]')->length, 2, 'add at maxRows: nothing added');
    T::eq(fbDom(fbFields()->maxRows(2)->request(['f' => ['cmd' => 'group:'] + $full])->html())->query('//fieldset')->length, 1, 'group at maxRows: nothing added');
    T::eq(fbDom(fbFields()->maxRows(2)->request(['f' => ['cmd' => 'dup:0'] + $full])->html())->query('//li[contains(@class,"gk-fb-row")]')->length, 2, 'dup at maxRows: nothing added');

    // An operator the field does not offer is never applied unseen: the form shows the field's first, the value goes.
    $wrong = fbFields()->request(['f' => '["and",["status","gt","open"],["reminded","maybe"]]']);
    T::eq($wrong->tree()['rules'], [], 'a wrong operator: not applied, not even as the first one');
    $h = $wrong->html();
    T::contains($h, '<option value="is" selected>is</option>', 'the form shows the first operator');
    T::contains($h, '<span>Choose a value.</span></p>', 'and asks for the value again');
    T::contains($h, '<span>Choose an operator.</span></p>', 'a value-less type asks for the operator');

    // Reset: back to value().
    $h = fbFields()->value('["and",["status","is","paid"]]')->request(['f' => ['cmd' => 'reset', 'rules' => [['field' => 'status', 'op' => 'is', 'value' => 'open']]]]);
    T::eq(FilterBuilder::encode($h->tree()), '["and",["status","is","paid"]]', 'reset: the value() state');

    // A field changed without script: the operator falls back to the field's first, the value is checked again.
    $h = fbFields()->request(['f' => ['cmd' => 'apply', 'rules' => [['field' => 'due', 'op' => 'contains', 'value' => 'x']]]])->html();
    T::contains($h, '<option value="eq" selected>is on</option>', 'an operator the new field lacks: its first');
    T::contains($h, '<span>Enter a date.</span></p>', 'and the value is said to be wrong');
},

'maxRows and maxDepth reach the buttons' => function (): void {
    $h = fbFields()->maxRows(2)->value('["and",["status","is","open"],["or",["amount","gt",1]]]')->html();
    $x = fbDom($h);
    T::eq($x->query('//button[contains(@class,"gk-fb-add-rule")][@disabled]')->length, 2, 'at the limit every "Condition" is disabled');
    T::eq($x->query('//button[contains(@class,"gk-fb-dup")][@disabled]')->length, 3, 'and every duplicate');
    T::eq($x->query('//button[contains(@class,"gk-fb-remove")][@disabled]')->length, 0, 'remove still works');
    T::contains($h, '<span class="gk-fb-limit">At most 2 conditions.</span>', 'the limit is said');
    $h = fbFields()->maxDepth(2)->value('["and",["status","is","open"],["or",["amount","gt",1]]]')->html();
    $x = fbDom($h);
    T::eq($x->query('//button[contains(@class,"gk-fb-add-group")]')->length, 1, 'maxDepth 2: a group inside the top, none inside that');
    T::eq(fbDom(fbFields()->maxDepth(1)->html())->query('//button[contains(@class,"gk-fb-add-group")]')->length, 0, 'maxDepth 1: no groups at all');
},

// ── SQL ─────────────────────────────────────────────────────────────────

'toSql: every operator, placeholders only' => function (): void {
    $fb = fbFields();
    $q = static function (string $json, array $map = []) use ($fb): array {
        return FilterBuilder::toSql($json, $fb, $map);
    };
    $cases = [
        ['["and",["customer","contains","50%_off!"]]', "customer LIKE ? ESCAPE '!'", ['%50!%!_off!!%']],
        ['["and",["customer","not_contains","a"]]', "(customer IS NULL OR customer NOT LIKE ? ESCAPE '!')", ['%a%']],
        ['["and",["customer","equals","A_b"]]', "customer LIKE ? ESCAPE '!'", ['A!_b']],
        ['["and",["customer","not_equals","x"]]', "(customer IS NULL OR customer NOT LIKE ? ESCAPE '!')", ['x']],
        ['["and",["customer","starts","Eck"]]', "customer LIKE ? ESCAPE '!'", ['Eck%']],
        ['["and",["customer","ends","GmbH"]]', "customer LIKE ? ESCAPE '!'", ['%GmbH']],
        ['["and",["customer","empty"]]', "(customer IS NULL OR customer = '')", []],
        ['["and",["customer","not_empty"]]', "(customer IS NOT NULL AND customer <> '')", []],
        ['["and",["qty","eq",3]]', 'qty = ?', [3]],
        ['["and",["qty","ne",3]]', '(qty IS NULL OR qty <> ?)', [3]],
        ['["and",["qty","lt",3]]', 'qty < ?', [3]],
        ['["and",["qty","lte",3]]', 'qty <= ?', [3]],
        ['["and",["qty","gt",3.5]]', 'qty > ?', [3.5]],
        ['["and",["qty","gte",3]]', 'qty >= ?', [3]],
        ['["and",["qty","between",[9,2]]]', 'qty BETWEEN ? AND ?', [2, 9]],
        ['["and",["qty","empty"]]', 'qty IS NULL', []],
        ['["and",["qty","not_empty"]]', 'qty IS NOT NULL', []],
        ['["and",["due","eq","2026-11-04"]]', '(due >= ? AND due < ?)', ['2026-11-04', '2026-11-05']],
        ['["and",["due","ne","2026-12-31"]]', '(due IS NULL OR due < ? OR due >= ?)', ['2026-12-31', '2027-01-01']],
        ['["and",["due","lt","2026-03-01"]]', 'due < ?', ['2026-03-01']],
        ['["and",["due","lte","2028-02-28"]]', 'due < ?', ['2028-02-29']],
        ['["and",["due","gt","2026-11-04"]]', 'due >= ?', ['2026-11-05']],
        ['["and",["due","gte","2026-11-04"]]', 'due >= ?', ['2026-11-04']],
        ['["and",["due","between",["2026-11-30","2026-11-01"]]]', '(due >= ? AND due < ?)', ['2026-11-01', '2026-12-01']],
        ['["and",["due","eq","9999-12-31"]]', '(due >= ? AND due IS NOT NULL)', ['9999-12-31']],
        ['["and",["due","gt","9999-12-31"]]', '1=0', []],
        ['["and",["due","lte","9999-12-31"]]', 'due IS NOT NULL', []],
        ['["and",["due","ne","9999-12-31"]]', '(due IS NULL OR due < ?)', ['9999-12-31']],
        ['["and",["due","empty"]]', 'due IS NULL', []],
        ['["and",["status","is","open"]]', 'status = ?', ['open']],
        ['["and",["status","is","7"]]', 'status = ?', [7]],
        ['["and",["status","is_not","paid"]]', '(status IS NULL OR status <> ?)', ['paid']],
        ['["and",["kind","any_of",["a","b","c"]]]', 'kind IN (?, ?, ?)', ['a', 'b', 'c']],
        ['["and",["kind","none_of",["a"]]]', '(kind IS NULL OR kind NOT IN (?))', ['a']],
        ['["and",["kind","empty"]]', "(kind IS NULL OR kind = '')", []],
        ['["and",["reminded","true"]]', 'reminded = ?', [1]],
        ['["and",["reminded","false"]]', '(reminded IS NULL OR reminded <> ?)', [1]],
        ['["and"]', '1=1', []],
        ['["or",["qty","eq",1],["and",["qty","gt",5],["qty","lt",9]]]', '(qty = ? OR (qty > ? AND qty < ?))', [1, 5, 9]],
    ];
    foreach ($cases as [$json, $sql, $params]) {
        $r = $q($json);
        T::eq($r['sql'], $sql, "SQL of $json");
        T::eq($r['params'], $params, "params of $json");
        T::eq(substr_count($r['sql'], '?'), count($r['params']), "one placeholder per param: $json");
    }
    T::eq($q('["and",["amount","gt",5]]', ['amount' => 'i.total'])['sql'], 'i.total > ?', 'the column map');
    T::eq($q('["and",["amount","gt",5]]', ['amount' => '`order`.`total sum`'])['sql'], '`order`.`total sum` > ?', 'quoted identifiers');
    T::eq($q('["and",["amount","gt",5]]', ['amount' => '"t"."x"'])['sql'], '"t"."x" > ?', 'double quotes too');
},

'toSql refuses what is not an identifier, and never widens the result' => function (): void {
    $fb = fbFields();
    foreach (['total; DROP TABLE x', 'a b', '1=1 OR total', 'x--', '`a`b`', '"a"b"', 'a.b.c', '', 'total)', "t\0x", 'SLEEP(5)', '*'] as $col) {
        [$r, $notes] = fbRun(fn () => FilterBuilder::toSql('["and",["amount","gt",5]]', $fb, ['amount' => $col]));
        T::eq($r['sql'], '1=0', "the column '$col' is refused");
        T::eq($r['params'], [], 'and binds nothing');
        T::eq(count($notes), 1, 'with a warning');
    }
    [$r, $notes] = fbRun(fn () => FilterBuilder::toSql('["and",["tags","has_any",["vip"]],["status","is","open"]]', $fb));
    T::eq($r['sql'], '(1=0 AND status = ?)', 'a multiselect without a callable matches nothing');
    T::eq(count($notes), 1, 'with a warning');
    [$r, $notes] = fbRun(fn () => FilterBuilder::toSql('["or",["tags","has_any",["vip"]],["status","is","open"]]', $fb, ['tags' => fn () => ['sql' => 'x IN (?, ?)', 'params' => [1]]]));
    T::eq($r['sql'], '(1=0 OR status = ?)', 'a callable whose placeholders and params do not match: 1=0');
    T::eq(count($notes), 1, 'with a warning');
    [$r, $notes] = fbRun(fn () => FilterBuilder::toSql('["and",["tags","has_any",["vip"]]]', $fb, ['tags' => function (): array { throw new RuntimeException('down'); }]));
    T::eq($r['sql'], '1=0', 'a callable that throws: 1=0');
    [$r, $notes] = fbRun(fn () => FilterBuilder::toSql('["and",["tags","has_any",["vip"]]]', $fb, ['tags' => fn () => ['sql' => 'x = ?', 'params' => [[1]]]]));
    T::eq($r['sql'], '1=0', 'a param that is no scalar: 1=0');
    $seen = null;
    $r = FilterBuilder::toSql('["and",["tags","has_all",["vip","eu","vip"]]]', $fb, ['tags' => function (array $rule, array $field) use (&$seen): array {
        $seen = [$rule, $field];
        return ['sql' => 'tag_count(id, ?, ?) = 2', 'params' => $rule['value']];
    }]);
    T::eq($r, ['sql' => '(tag_count(id, ?, ?) = 2)', 'params' => ['vip', 'eu']], 'a callable: its SQL in parentheses, its params');
    T::eq($seen, [['field' => 'tags', 'op' => 'has_all', 'value' => ['vip', 'eu']], ['key' => 'tags', 'label' => 'Tags', 'type' => 'multiselect']], 'it is handed the checked rule and the field');
},

'hostile input never reaches the SQL text' => function (): void {
    $fb = fbFields();
    $evil = ["'; DROP TABLE invoices; --", '" OR "1"="1', '\\\' OR 1=1 #', "x' UNION SELECT password FROM users --", '%', '_', '!', "\0", '?'];
    foreach ($evil as $v) {
        foreach (['contains', 'equals', 'starts', 'ends', 'not_contains'] as $op) {
            $r = FilterBuilder::toSql(['and', ['customer', $op, $v]], $fb);
            T::ok(!str_contains($r['sql'], 'DROP') && !str_contains($r['sql'], 'UNION') && !str_contains($r['sql'], '"1"'), "a value stays out of the SQL ($op)");
            T::eq(substr_count($r['sql'], '?'), count($r['params']), 'one placeholder per param');
        }
    }
    // Hostile field names and operators are not fields and operators.
    $tree = ['and',
        ['status; DROP TABLE x', 'is', 'open'], ['status', 'is OR 1=1', 'open'], ['status', "is' --", 'open'],
        ['field' => '1=1', 'op' => 'eq', 'value' => 1], ['join' => 'and) OR (1=1', 'rules' => [['status', 'is', 'open']]],
    ];
    $r = FilterBuilder::toSql($tree, $fb);
    T::eq($r, ['sql' => 'status = ?', 'params' => ['open']], 'only the real condition is left; a hostile join is AND');
    // A huge list: as many placeholders as there are options, at most.
    $r = FilterBuilder::toSql(['and', ['kind', 'any_of', array_merge(array_fill(0, 150, 'a'), ['b', 'c', 'zzz', "a') OR ('1'='1"])]], $fb);
    T::eq($r, ['sql' => 'kind IN (?, ?, ?)', 'params' => ['a', 'b', 'c']], 'a huge list: the options it names, once each');
    $r = FilterBuilder::toSql(['and', ['kind', 'any_of', array_fill(0, 100000, 'a')]], $fb);
    T::eq($r['params'], ['a'], '100000 values: one placeholder');
},

'toSql and matches agree, row for row, on SQLite' => function (): void {
    if (!class_exists(PDO::class) || !in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        T::ok(true, 'no pdo_sqlite: skipped');
        return;
    }
    $fb = fbFields();
    $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('CREATE TABLE invoices (id INTEGER PRIMARY KEY, status TEXT, amount REAL, qty INTEGER, customer TEXT, due TEXT, reminded INTEGER, kind TEXT)');
    $pdo->exec('CREATE TABLE invoice_tags (invoice_id INTEGER, tag TEXT)');
    mt_srand(1123);
    $statuses = ['open', 'paid', 'overdue', '7', null, ''];
    $names = ['Ecklund & Partner', 'eckhart', 'NORDLICHT', 'Atelier 50% off', 'a_b', null, '', 'Mueller'];
    $rows = [];
    for ($id = 1; $id <= 60; $id++) {
        $tags = array_values(array_filter(['vip', 'eu', 'rc'], static fn (): bool => mt_rand(0, 2) === 0));
        $row = [
            'id' => $id,
            'status' => $statuses[mt_rand(0, 5)],
            'amount' => mt_rand(0, 9) === 0 ? null : mt_rand(-50, 2000) + mt_rand(0, 3) * 0.25,
            'qty' => mt_rand(0, 6) === 0 ? null : mt_rand(0, 12),
            'customer' => $names[mt_rand(0, 7)],
            'due' => mt_rand(0, 8) === 0 ? null : sprintf('2026-%02d-%02d', mt_rand(10, 12), mt_rand(1, 28)) . (mt_rand(0, 2) === 0 ? ' 13:45:00' : ''),
            'reminded' => [0, 1, null][mt_rand(0, 2)],
            'kind' => ['a', 'b', 'c', null][mt_rand(0, 3)],
        ];
        $pdo->prepare('INSERT INTO invoices VALUES (?, ?, ?, ?, ?, ?, ?, ?)')->execute(array_values($row));
        foreach ($tags as $t) $pdo->prepare('INSERT INTO invoice_tags VALUES (?, ?)')->execute([$id, $t]);
        $rows[] = $row + ['tags' => $tags];
    }
    $tagSql = static function (array $rule): array {
        $in = implode(', ', array_fill(0, count($rule['value'] ?? []), '?'));
        return match ($rule['op']) {
            'has_any' => ['sql' => "EXISTS (SELECT 1 FROM invoice_tags t WHERE t.invoice_id = invoices.id AND t.tag IN ($in))", 'params' => $rule['value']],
            'has_none' => ['sql' => "NOT EXISTS (SELECT 1 FROM invoice_tags t WHERE t.invoice_id = invoices.id AND t.tag IN ($in))", 'params' => $rule['value']],
            'has_all' => ['sql' => "(SELECT COUNT(DISTINCT t.tag) FROM invoice_tags t WHERE t.invoice_id = invoices.id AND t.tag IN ($in)) = " . count($rule['value']), 'params' => $rule['value']],
            'empty' => ['sql' => 'NOT EXISTS (SELECT 1 FROM invoice_tags t WHERE t.invoice_id = invoices.id)', 'params' => []],
            default => ['sql' => 'EXISTS (SELECT 1 FROM invoice_tags t WHERE t.invoice_id = invoices.id)', 'params' => []],
        };
    };
    $values = [
        'status' => fn () => ['open', 'paid', 'overdue', '7'][mt_rand(0, 3)],
        'kind' => fn () => ['a', 'b', 'c'][mt_rand(0, 2)],
        'customer' => fn () => ['eck', 'ECK', 'licht', '50%', 'a_b', '_', 'Partner', 'mueller', 'x'][mt_rand(0, 8)],
        'amount' => fn () => [mt_rand(-50, 2000), mt_rand(0, 2000) + 0.25][mt_rand(0, 1)],
        'qty' => fn () => mt_rand(0, 12),
        'due' => fn () => sprintf('2026-%02d-%02d', mt_rand(10, 12), mt_rand(1, 28)),
        'tags' => fn () => ['vip', 'eu', 'rc'][mt_rand(0, 2)],
    ];
    $spec = (new ReflectionMethod(FilterBuilder::class, 'spec'))->invoke($fb);
    $randRule = static function () use ($spec, $values): array {
        $key = array_rand($spec['fields']);
        $op = $spec['ops'][$key][mt_rand(0, count($spec['ops'][$key]) - 1)];
        $kind = (new ReflectionClassConstant(FilterBuilder::class, 'KIND'))->getValue()[$op];
        if ($kind === 'none') return [$key, $op];
        if ($kind === 'two') return [$key, $op, [$values[$key](), $values[$key]()]];
        if ($kind === 'list') return [$key, $op, array_map(fn () => $values[$key](), range(0, mt_rand(0, 2)))];
        return [$key, $op, $values[$key]()];
    };
    $randGroup = static function (int $depth) use (&$randGroup, $randRule): array {
        $g = [mt_rand(0, 1) ? 'and' : 'or'];
        for ($i = mt_rand(1, 3); $i > 0; $i--) $g[] = $depth < 3 && mt_rand(0, 4) === 0 ? $randGroup($depth + 1) : $randRule();
        return $g;
    };
    $differ = [];
    $trees = 0;
    for ($n = 0; $n < 400; $n++) {
        $tree = $randGroup(1);
        $r = FilterBuilder::toSql($tree, $fb, ['tags' => $tagSql]);
        $st = $pdo->prepare('SELECT id FROM invoices WHERE ' . $r['sql'] . ' ORDER BY id');
        $st->execute($r['params']);
        $sql = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
        $php = array_values(array_map(fn ($row) => $row['id'], array_filter($rows, fn ($row) => FilterBuilder::matches($tree, $row, $fb))));
        if ($sql !== $php) $differ[] = FilterBuilder::encode(FilterBuilder::clean($tree, $fb)) . ' sql=' . implode(',', $sql) . ' php=' . implode(',', $php);
        $trees++;
    }
    T::eq($trees, 400, '400 random trees');
    T::eq(array_slice($differ, 0, 3), [], 'SQL and matches() find the same rows (' . count($differ) . ' differ)');

    // Hostile values, run for real: the table is still there and nothing was found by an injection.
    foreach (["'; DROP TABLE invoices; --", "x' OR '1'='1", '") OR 1=1 --'] as $v) {
        $r = FilterBuilder::toSql(['or', ['customer', 'equals', $v], ['status', 'is_not', 'open'], ['amount', 'gt', $v]], $fb);
        $st = $pdo->prepare('SELECT COUNT(*) FROM invoices WHERE ' . $r['sql']);
        $st->execute($r['params']);
        $st->fetchColumn();
    }
    T::eq((int) $pdo->query('SELECT COUNT(*) FROM invoices')->fetchColumn(), 60, 'the table survived every hostile value');
    $r = FilterBuilder::toSql(['and', ['customer', 'equals', "x' OR '1'='1"]], $fb);
    $st = $pdo->prepare('SELECT COUNT(*) FROM invoices WHERE ' . $r['sql']);
    $st->execute($r['params']);
    T::eq((int) $st->fetchColumn(), 0, 'an injection in a value finds nothing');
},

'matches reads arrays, objects and odd rows' => function (): void {
    $fb = fbFields();
    $t = '["and",["status","is","open"],["due","lt","2026-11-01"]]';
    T::ok(FilterBuilder::matches($t, ['status' => 'open', 'due' => '2026-10-31 23:59:59'], $fb), 'an array, a date with a time');
    T::ok(FilterBuilder::matches($t, (object) ['status' => 'open', 'due' => new DateTimeImmutable('2026-10-02')], $fb), 'an object, a DateTime');
    T::ok(FilterBuilder::matches($t, new ArrayObject(['status' => 'open', 'due' => '2026-01-01']), $fb), 'ArrayAccess');
    T::ok(!FilterBuilder::matches($t, ['status' => 'open'], $fb), 'a missing key is NULL: not before anything');
    T::ok(FilterBuilder::matches('["and",["status","is_not","paid"]]', [], $fb), 'is not: NULL passes, as in SQL');
    T::ok(FilterBuilder::matches('["and"]', [], $fb), 'no conditions: every row');
    T::ok(FilterBuilder::matches('["and",["tags","has_all",["vip","eu"]]]', ['tags' => 'eu, vip,rc'], $fb), 'a multiselect as a comma list');
    T::ok(FilterBuilder::matches('["and",["tags","empty"]]', ['tags' => ''], $fb), 'an empty list');
    T::ok(FilterBuilder::matches('["and",["customer","contains","MÜL"]]', ['customer' => 'Müller'], $fb), 'case does not count, umlauts too');
    T::ok(!FilterBuilder::matches('["and",["customer","contains","x"]]', ['customer' => ['x']], $fb), 'an array is no text');
    T::ok(FilterBuilder::matches('["and",["amount","eq",19.99]]', ['amount' => '19.99'], $fb), 'a numeric string');
    T::ok(FilterBuilder::matches('["and",["reminded","true"]]', ['reminded' => '1'], $fb) && !FilterBuilder::matches('["and",["reminded","true"]]', ['reminded' => 'yes'], $fb), 'bool: 1, "1", true');
    T::ok(FilterBuilder::matches('{"join":"or","rules":[{"field":"nope","op":"eq","value":1}]}', ['x' => 1], $fb), 'an unknown field is left out — no conditions left, every row');
},

// ── Words ───────────────────────────────────────────────────────────────

'the summary says it in words, in both languages' => function (): void {
    $fb = fbFields();
    $t = '["and",["status","any_of",["open","overdue"]],["or",["amount","gt",1234.5],["customer","contains","Eck"],["due","between",["2026-11-30","2026-11-01"]]],["reminded","false"],["qty","lte",3]]';
    Lang::set('en');
    T::eq(FilterBuilder::summary($t, $fb), 'Status is any of Open, Overdue AND (Amount > 1,234.50 OR Customer contains “Eck” OR Due between Nov 1, 2026 and Nov 30, 2026) AND Reminded is no AND Quantity ≤ 3', 'English');
    T::eq(FilterBuilder::summary('["and"]', $fb), 'every row, no conditions', 'nothing');
    Lang::set('de');
    try {
        $de = (new FilterBuilder('f'))->field('status', 'Status', ['type' => 'select', 'options' => ['open' => 'Offen']])
            ->field('amount', 'Betrag', ['type' => 'money'])->field('customer', 'Kunde')->field('due', 'Fällig', ['type' => 'date']);
        T::eq(FilterBuilder::summary('["or",["status","is","open"],["amount","gt",1234.5],["customer","starts","Eck"],["due","lt","2026-11-04"]]', $de),
            'Status ist Offen ODER Betrag > 1.234,50 ODER Kunde beginnt mit „Eck“ ODER Fällig vor 4. Nov. 2026', 'German');
        $h = $de->html();
        T::contains($h, 'alle Bedingungen (UND)', 'the form in German');
        T::contains($h, 'aria-label="Bedingung zu den Filter hinzufügen"', 'the add button named in German');
    } finally {
        Lang::set('en');
    }
    T::eq(FilterBuilder::summary('["and",["amount","eq",-1000000],["qty","eq",0.000001]]', $fb), 'Amount = -1,000,000.00 AND Quantity = 0.000001', 'thousands, a minus, six decimals without exponent');
    T::eq(FilterBuilder::summary('["and",["amount","eq",19.999]]', $fb), 'Amount = 19.999', 'money is never rounded');
},

'every word the class asks for is in both catalogues' => function () use ($root): void {
    $src = (string) file_get_contents($root . '/src/FilterBuilder.php');
    preg_match_all("/'filterbuilder\\.([a-z_]+)'/", $src, $m);
    preg_match("/\\\$keys = \\[(.*?)\\];/s", $src, $js);
    preg_match_all("/'([a-z_]+)'/", $js[1] ?? '', $k);
    $en = require $root . '/lang/en.php';
    $de = require $root . '/lang/de.php';
    $keys = array_unique(array_merge($m[1], $k[1]));
    foreach (array_keys(FilterBuilder::OPERATORS) as $type) {
        foreach (FilterBuilder::OPERATORS[$type] as $op) {
            $keys[] = 'op_' . ($type === 'date' && in_array($op, ['eq', 'ne', 'lt', 'lte', 'gt', 'gte'], true) ? 'date_' . $op : $op);
        }
    }
    T::ok(count($keys) > 40, 'the class asks for many words');
    foreach (array_unique($keys) as $key) {
        if (str_ends_with($key, '_')) continue;
        T::ok(isset($en["filterbuilder.$key"]), "en has filterbuilder.$key");
        T::ok(isset($de["filterbuilder.$key"]), "de has filterbuilder.$key");
    }
},

// ── Markup ──────────────────────────────────────────────────────────────

'the form: names for every control, groups as fieldsets, the default button first' => function (): void {
    Lang::set('en');
    [$h, $notes] = fbRun(fn () => fbFields()->value('["and",["status","is","open"],["or",["amount","between",[1,2]],["kind","any_of",["a"]]]]')->html());
    T::eq($notes, [], 'a correct builder says nothing');
    T::ok(str_starts_with($h, '<form class="gk-filter-builder" id="gk-fb-f" method="get" aria-label="Filter" data-gk-filter-builder="'), 'a form, named');
    T::ok((bool) preg_match('~data-gk-filter-builder="[^"]*"><button type="submit" class="gk-fb-default" name="f\[cmd\]" value="apply" hidden tabindex="-1">Apply</button>~', $h), 'the first submit button applies (Enter in a field), and is hidden');
    $x = fbDom($h);
    foreach ($x->query('//select|//input[not(@type="hidden")]') as $c) {
        /** @var DOMElement $c */
        $named = $c->getAttribute('aria-label') !== '' || ($c->getAttribute('id') !== '' && $x->query('//label[@for="' . $c->getAttribute('id') . '"]')->length === 1);
        T::ok($named, 'a control without a name: ' . $c->getAttribute('id'));
    }
    foreach ($x->query('//button') as $b) {
        /** @var DOMElement $b */
        T::ok(trim($b->textContent) !== '' || $b->getAttribute('aria-label') !== '', 'a button without a name: ' . $b->getAttribute('value'));
    }
    foreach ($x->query('//fieldset') as $fs) {
        T::ok($x->query('legend', $fs)->length === 1 && trim($x->query('legend', $fs)->item(0)->textContent) !== '', 'every group is named by its legend');
    }
    T::eq($x->query('//fieldset//fieldset/legend')->item(0)?->textContent, 'Group 2', 'a nested group: "Group 2"');
    T::contains($h, '<legend class="gk-fb-legend gk-sr-only">Filter</legend>', 'the top group: named for a screen reader');
    T::contains($h, 'aria-label="Field of condition 2.1"', 'a condition in a group: numbered by its path');
    T::contains($h, 'aria-label="Remove group 2" title="Remove group 2"', 'remove names what it removes');
    T::contains($h, 'name="f[rules][1][rules][0][value][0]"', 'between: two fields');
    T::contains($h, 'aria-label="From, condition 2.1"', 'named From');
    T::contains($h, 'name="f[rules][1][rules][1][value][]" multiple size="3"', 'any of: a multiple select');
    T::contains($h, '<span class="gk-fb-conj" aria-hidden="true">OR</span>', 'the joining word, for the eye only');
    $ids = [];
    foreach ($x->query('//*[@id]') as $el) $ids[] = $el->getAttribute('id');
    T::eq(count($ids), count(array_unique($ids)), 'no id twice');
    $two = fbFields()->html() . (new FilterBuilder('g'))->field('a', 'A')->html();
    $x2 = fbDom($two);
    $ids = [];
    foreach ($x2->query('//*[@id]') as $el) $ids[] = $el->getAttribute('id');
    T::eq(count($ids), count(array_unique($ids)), 'two builders on a page: no id twice');
    T::contains($h, '<p class="gk-fb-summary" id="gk-fb-f-summary" aria-live="polite"><span class="gk-fb-summary-label">Shown:</span> <span class="gk-fb-summary-text">Status is Open AND (Amount between 1.00 and 2.00 OR Kind is any of A)</span></p>', 'the summary, a live region');
    $cfg = fbConfig($h);
    T::eq($cfg['state'], ['and', ['status', 'is', 'open'], ['or', ['amount', 'between', [1, 2]], ['kind', 'any_of', ['a']]]], 'the configuration carries the state');
    T::eq($cfg['fields'][0]['ops'], [['is', 'is'], ['is_not', 'is not'], ['any_of', 'is any of']], 'and the operators of each field, with their words');
    T::eq([$cfg['maxDepth'], $cfg['maxRows'], $cfg['loud']], [3, 20, true], 'the limits');
    T::ok(isset($cfg['words']['err_number'], $cfg['words']['months'], $cfg['words']['decimal']), 'and the words of the script');
},

'everything is escaped, the configuration cannot be broken out of' => function (): void {
    $evil = '"><img src=x onerror=alert(1)></script><script>alert(2)</script>\'';
    $fb = (new FilterBuilder('f'))
        ->field('s', $evil, ['type' => 'select', 'options' => [$evil => $evil, 'ok' => 'OK']])
        ->field('t', 'T', ['placeholder' => $evil])
        ->label($evil)
        ->views([['label' => $evil, 'state' => ['and', ['s', 'is', $evil]]]])
        ->preserve(['x' => $evil])
        ->request(['f' => json_encode(['and', ['s', 'is', $evil], ['t', 'contains', $evil]])]);
    [$h] = fbRun(fn () => $fb->html());
    T::notContains($h, '<img', 'no tag from a label, an option, a value or a view');
    T::notContains($h, '<script', 'no script');
    T::notContains($h, '</script', 'and nothing that closes one');
    T::ok(substr_count($h, 'onerror=alert') === substr_count($h, '&quot;&gt;&lt;img src=x onerror=alert(1)'), 'every copy is escaped');
    $cfg = fbConfig($h);
    T::eq($cfg['fields'][0]['label'], $evil, 'the configuration holds the text as it is');
    [, $notes] = fbRun(fn () => (new FilterBuilder('f'))->field('a', 'A')->action('javascript:alert(1)')->saveUrl(' JaVaScRiPt:alert(1)')->views([['label' => 'v', 'href' => "java\tscript:x"]])->html());
    T::eq(count($notes), 3, 'javascript: in action, saveUrl and a view link: three warnings');
},

'views: links with the state, the applied one marked, the parameters kept' => function (): void {
    $fb = fbFields()->views([
        ['label' => 'Open', 'state' => '["and",["status","is","open"]]'],
        ['name' => 'Mine', 'state' => ['join' => 'and', 'rules' => []], 'href' => '/mine'],
        (object) ['label' => 'Paid', 'state' => ['and', ['status', 'is', 'paid']]],
    ])->action('/invoices#list')->preserve(['sort', 'page' => 2])->request(['f' => '["and",["status","is","open"]]', 'sort' => 'due']);
    $h = $fb->html();
    T::contains($h, '<nav class="gk-fb-views" aria-labelledby="gk-fb-f-views"><span class="gk-fb-views-label" id="gk-fb-f-views">Saved views</span>', 'a named navigation');
    T::contains($h, '<a class="gk-fb-view" href="/invoices?sort=due&amp;page=2&amp;f=%5B%22and%22%2C%5B%22status%22%2C%22is%22%2C%22open%22%5D%5D#list" aria-current="true">Open</a>', 'a link with the state, the kept parameters and the fragment — and marked');
    T::contains($h, '<a class="gk-fb-view" href="/mine">Mine</a>', 'a link of its own');
    T::contains($h, '>Paid</a>', 'an object');
    T::contains($h, '<input type="hidden" name="sort" value="due"><input type="hidden" name="page" value="2">', 'the form carries them too');
    T::notContains(fbFields()->html(), 'gk-fb-views', 'no views: no navigation');
    T::contains(fbFields()->localViews()->html(), '<nav class="gk-fb-views" aria-labelledby="gk-fb-f-views" hidden>', 'localViews: an empty navigation the script fills');
    T::contains(fbFields()->localViews()->html(), '<div class="gk-fb-save" hidden>', 'and Save view, hidden until the script is there');
    $h = fbFields()->saveUrl('/views')->html();
    T::contains($h, '<label class="gk-fb-save-label" for="gk-fb-f-view">Name of the view</label><input type="text" class="gk-filter gk-fb-save-name" id="gk-fb-f-view" name="f_view" maxlength="80" autocomplete="off">', 'a labelled name');
    T::contains($h, 'name="f[cmd]" value="save" formaction="/views" formmethod="post"', 'Save view posts to the URL');
    $v = FilterBuilder::savedView('f', fbFields(), ['request' => ['f' => ['cmd' => 'save', 'rules' => [['field' => 'status', 'op' => 'is', 'value' => 'open']]], 'f_view' => "  My\x00 view  "]]);
    T::eq($v, ['label' => 'My view', 'state' => ['join' => 'and', 'rules' => [['field' => 'status', 'op' => 'is', 'value' => 'open']]], 'json' => '["and",["status","is","open"]]'], 'savedView: the name, the checked state, the JSON');
    T::eq(FilterBuilder::savedView('f', fbFields(), ['request' => ['f' => '["and"]', 'f_view' => '   ']]), null, 'no name: null');
    T::eq(FilterBuilder::savedView('f', fbFields(), ['request' => ['f' => '["and"]', 'f_view' => ['x']]]), null, 'a name that is no text: null');
},

'a method, an action, a label, and value() as the state without a request' => function (): void {
    $h = fbFields()->method('post')->action('/x')->label('Invoices filter')->html();
    T::ok(str_starts_with($h, '<form class="gk-filter-builder" id="gk-fb-f" method="post" action="/x" aria-label="Invoices filter"'), 'post, an action, a label');
    $fb = fbFields()->value(['and', ['status', 'is', 'open']]);
    T::eq(FilterBuilder::encode($fb->tree()), '["and",["status","is","open"]]', 'value() without a request');
    T::eq(FilterBuilder::encode($fb->request(['f' => '["and",["status","is","paid"]]'])->tree()), '["and",["status","is","paid"]]', 'the request wins');
    T::eq(fbConfig(fbFields()->value('["and",["status","is","open"]]')->request(['f' => '["and"]'])->html())['initial'], ['and', ['status', 'is', 'open']], 'Reset of the script goes back to value()');
},

'a wrong option is a warning and a default, never an exception' => function (): void {
    $cases = [
        'name' => fn () => new FilterBuilder('a b<'),
        'key' => fn () => (new FilterBuilder())->field('a b', 'A'),
        'key2' => fn () => (new FilterBuilder())->field('1a', 'A'),
        'type' => fn () => (new FilterBuilder())->field('a', 'A', ['type' => 'currency']),
        'option' => fn () => (new FilterBuilder())->field('a', 'A', ['colour' => 'red']),
        'no options' => fn () => (new FilterBuilder())->field('a', 'A', ['type' => 'select']),
        'options on text' => fn () => (new FilterBuilder())->field('a', 'A', ['options' => ['x' => 'X']]),
        'field operators' => fn () => (new FilterBuilder())->field('a', 'A', ['operators' => ['gt']]),
        'twice' => fn () => (new FilterBuilder())->field('a', 'A')->field('a', 'B'),
        'operators type' => fn () => (new FilterBuilder())->operators('color', ['eq']),
        'operators op' => fn () => (new FilterBuilder())->operators('text', ['contains', 'gt']),
        'maxDepth' => fn () => (new FilterBuilder())->maxDepth(9),
        'maxRows' => fn () => (new FilterBuilder())->maxRows(0),
        'method' => fn () => (new FilterBuilder())->method('put'),
        'view label' => fn () => (new FilterBuilder())->views([['state' => '["and"]']]),
        'no fields' => fn () => (new FilterBuilder())->html(),
        'value' => fn () => (new FilterBuilder())->field('a', 'A', ['type' => 'number'])->value('["and",["a","gt","x"]]')->html(),
        'fields array' => fn () => FilterBuilder::clean('["and"]', ['a b' => ['label' => 'x']]),
        'limit opts' => fn () => FilterBuilder::clean('["and"]', ['a' => ['label' => 'A']], ['maxRows' => 1000]),
    ];
    foreach ($cases as $what => $fn) {
        [, $notes] = fbRun($fn);
        T::ok(count($notes) >= 1, "$what: a warning");
        T::ok(isset($notes[0]) && str_starts_with($notes[0], 'GridKit: FilterBuilder: '), "$what: it says who speaks");
    }
    [$fb] = fbRun(fn () => (new FilterBuilder())->field('a', 'A', ['type' => 'currency'])->maxDepth(9)->maxRows(0)->method('put'));
    [$h] = fbRun(fn () => $fb->html());
    T::ok(str_contains($h, 'method="get"') && fbConfig($h)['maxDepth'] === 3 && fbConfig($h)['maxRows'] === 20 && fbConfig($h)['fields'][0]['type'] === 'text', 'the defaults stay');
    $src = (string) file_get_contents(__DIR__ . '/../src/FilterBuilder.php');
    T::notContains($src, 'throw ', 'the class throws nothing');
    T::eq(preg_match_all('/trigger_error\(/', $src), 1, 'one way to warn');
},

// ── The stylesheet, the script, the demo ────────────────────────────────

'the stylesheet block: at the end, roles only, both dark spellings, a phone' => function () use ($sheet, $root): void {
    $css = gkCssText();
    $at = strpos($css, '/* === 1.123.0 — filter builder === */');
    T::ok($at !== false, 'the block heading');
    T::ok($at > (int) strpos($css, '/* === 1.122.0 — command palette === */'), 'after the block of 1.122.0');
    $later = preg_match_all('~/\* === 1\.(\d+)\.~', substr($css, (int) $at + 10), $v) ? $v[1] : [];
    T::ok(array_filter($later, static fn (string $m): bool => (int) $m <= 123) === [], 'only a later release may follow it');
    $s = $sheet();
    T::ok(!preg_match('/#[0-9a-f]{3,8}\b|rgba?\(/i', $s), 'no colour literal: roles only');
    foreach (['[data-gk-mode="dark"] .gk-fb-group .gk-fb-group,', '.gk-dark .gk-fb-group .gk-fb-group {', '[data-gk-mode="dark"] .gk-fb-conj,', '.gk-dark .gk-fb-conj {'] as $w) {
        T::contains($s, $w, "dark: $w");
    }
    T::contains($s, '@media (max-width: 600px)', 'a phone');
    T::ok((bool) preg_match('/\.gk-fb-group \{[^}]*min-width: 0;/', $s), 'a fieldset does not hold its min-content width (390px)');
    T::contains($s, 'var(--gk-target-min)', 'targets of a finger');
    T::contains($s, '@media (forced-colors: active)', 'forced colours');
    T::ok((bool) preg_match('/\.gk-fb-view\[aria-current="true"\]::before \{\s*content: "✓";/', $s), 'the applied view: a tick, not the colour alone');
    $index = json_decode((string) file_get_contents($root . '/css/blocks.json'), true);
    preg_match_all('/\.(gk-(?:fb|filter-builder)[a-z0-9-]*)/', $s, $m);
    $classes = array_values(array_unique($m[1]));
    sort($classes);
    $listed = array_keys($index['blocks']['filter-builder']['classes'] ?? []);
    T::eq($listed, $classes, 'css/blocks.json lists every class of the block under "filter-builder"');
    T::eq(array_unique(array_values($index['blocks']['filter-builder']['classes'] ?? [])), ['1.123.0'], 'all at 1.123.0');
    // Every class the markup uses is styled (and so in the index).
    $h = fbFields()->saveUrl('/v')->views([['label' => 'v', 'state' => '["and"]']])->request(['f' => ['cmd' => 'apply', 'rules' => [['field' => 'amount', 'op' => 'between', 'value' => ['x', '']], ['join' => 'or', 'rules' => [['field' => 'kind', 'op' => 'any_of', 'value' => ['a']]]]]]])->maxRows(2)->html();
    preg_match_all('/class="([^"]*)"/', $h . fbFields()->html(), $cm);
    $used = [];
    foreach ($cm[1] as $list) foreach (preg_split('/\s+/', $list) as $c) if (preg_match('/^gk-(fb|filter-builder)/', $c)) $used[$c] = true;
    $used['gk-fb-view-remove'] = true;   // drawn by the script
    $used = array_keys($used);
    sort($used);
    T::eq(array_values(array_diff($used, $classes)), [], 'the markup uses no class the stylesheet lacks');
    T::eq(array_values(array_diff($classes, $used)), [], 'and the stylesheet styles none the markup lacks');
},

'the script: its own file, the contract with the markup, no HTML from the page' => function () use ($root): void {
    $js = (string) file_get_contents($root . '/js/filter-builder.js');
    T::ok($js !== '', 'js/filter-builder.js');
    T::contains($js, '"[data-gk-filter-builder]"', 'it finds the builders by their attribute');
    foreach (['get:', 'set:', 'reset:', 'apply:', 'json:', 'summary:'] as $api) T::contains($js, $api, "GK.filterBuilder.$api");
    T::contains($js, 'window.GK.filterBuilder', 'the API');
    T::contains($js, '"gk:filterchange", { bubbles: true, cancelable: true', 'gk:filterchange, cancelable');
    T::contains($js, '"gk:filtersave", { bubbles: true, cancelable: true', 'gk:filtersave, cancelable');
    T::contains($js, 'var TEXT_MAX = 200, LIST_MAX = 200, JSON_MAX = 8192, VISIT_MAX = 1000', 'the limits of the server');
    $src = (string) file_get_contents($root . '/src/FilterBuilder.php');
    foreach (['TEXT_MAX = 200', 'LIST_MAX = 200', 'JSON_MAX = 8192', 'VISIT_MAX = 1000'] as $c) T::contains($src, $c, "the server's $c");
    preg_match("/NUMBER_RE = '([^']+)'/", $src, $p);
    T::contains($js, 'var NUMBER_RE = ' . ($p[1] ?? '?') . ';', 'the same number pattern');
    foreach (['ICON_ADD', 'ICON_COPY', 'ICON_REMOVE', 'ICON_ERROR'] as $icon) {
        preg_match("/$icon = '([^']+)'/", $src, $a);
        preg_match("/$icon = '([^']+)'/", $js, $b);
        T::eq($b[1] ?? null, $a[1] ?? '', "$icon is the same in both");
    }
    T::notContains($js, '</script', 'the file can be inlined');
    T::notContains($js, 'eval(', 'no eval');
    T::ok(substr_count($js, '.innerHTML =') === 2, 'two places write markup, both from escaped strings');
    T::notContains((string) file_get_contents($root . '/js/gridkit.js'), 'filterBuilder', 'gridkit.js stays as it is (PawBot keeps its own)');
},

'the demo, the skeleton and the docs carry it' => function () use ($root): void {
    $demo = demoSource();
    T::contains($demo, "->item('Filter builder', '#filterbuilder', 'filter_alt')", 'a sidebar item');
    $index = (string) file_get_contents($root . '/demo/index.php');
    T::ok(strpos($index, "->item('Filter builder'") > strpos($index, "->item('Command palette'"), 'after the last component');
    T::contains($demo, 'data-section="filterbuilder"', 'its section');
    T::contains($demo, "since('filter-builder')", 'marked as new');
    T::contains($demo, 'FilterBuilder::matches(', 'the demo filters rows with matches()');
    T::contains($demo, 'FilterBuilder::toSql(', 'and shows the SQL');
    T::contains($index, "js/filter-builder.js') ?>\" defer></script>", 'the demo loads the script');
    T::contains((string) file_get_contents($root . '/skeleton.php'), "js/filter-builder.js') ?>\" defer></script>", 'so does the skeleton');
    $skill = (string) file_get_contents($root . '/GRIDKIT_SKILL.md');
    T::contains($skill, '### FilterBuilder (`GridKit\FilterBuilder`, since 1.123.0)', 'a section in the skill');
    T::contains($skill, '| FilterBuilder | `GridKit\FilterBuilder` / `js/filter-builder.js` / `GK.filterBuilder` |', 'a row in the overview');
    foreach (FilterBuilder::OPERATORS as $type => $ops) {
        T::contains($skill, "`$type`", "the skill names the type $type");
        foreach ($ops as $op) T::contains($skill, "`$op`", "and the operator $op");
    }
    T::contains((string) file_get_contents($root . '/ci/README.md'), 'browser-filter-builder.js', 'ci/README.md lists the browser test');
},

];
