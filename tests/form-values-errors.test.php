<?php
/**
 * Form::values() and Form::errors() (1.105.0).
 *
 * Every edit form used to carry `'value' => $row['x'] ?? ''` on each of its fields, and a
 * form shown again after a failed POST carried `'error' => $errors['x'] ?? ''` the same way.
 * values() and errors() say it once. The strongest claim they make is that they change
 * nothing about the markup: a form filled by values() is byte for byte the form whose fields
 * carry the same 'value' options.
 */

declare(strict_types=1);

use GridKit\{Form, Lang};

/** The random hex digits that colour and rich-text ids append (md5 of name and microtime). */
function fveNormalize(string $html): string
{
    return preg_replace('/(gk-(?:color|editor)-[A-Za-z0-9_-]*?)-[0-9a-f]{6}(?=[-"\'])/', '$1-RRRRRR', $html) ?? $html;
}

function fveRender(Form $form): string
{
    Lang::set('en');
    return fveNormalize(T::capture(fn() => $form->render()));
}

/** Every field type that takes a value, with the value a record would hold for it. */
function fveCases(): array
{
    $fruit = ['a' => 'Apple', 'b' => 'Banana', 'c' => 'Cherry'];
    return [
        'text'        => ['text', [], 'Tom "T" <b>&</b>'],
        'number'      => ['number', ['min' => 0], 5],
        'date'        => ['date', [], '2026-11-02'],
        'textarea'    => ['textarea', [], "line 1\nline 2"],
        'select'      => ['select', ['options' => $fruit], 'b'],
        'native'      => ['select', ['options' => $fruit, 'native' => true], 'c'],
        'multiselect' => ['multiselect', ['options' => $fruit], ['a', 'c']],
        'multi-csv'   => ['multiselect', ['options' => $fruit], 'b,c'],
        'radio'       => ['radio', ['options' => $fruit], 'c'],
        'checkbox'    => ['checkbox', [], 1],
        'toggle'      => ['toggle', [], true],
        'range'       => ['range', ['min' => 10, 'max' => 50], 30],
        'color'       => ['color', [], '#ff8800'],
        'richtext'    => ['richtext', [], '<p>Hello "world"</p>'],
        'choice'      => ['choice', ['options' => $fruit], 'b'],
        'ajaxselect'  => ['ajaxselect', ['url' => '/api/x', 'displayValue' => 'Ada'], '42'],
        'hidden'      => ['hidden', [], 'secret'],
    ];
}

/** @return array<string,callable> */
return [

'values() renders byte for byte what the same value options render' => function (): void {
    foreach (fveCases() as $name => [$type, $opts, $value]) {
        $explicit = (new Form('f'))->field('fld', 'Label', $type, $opts + ['value' => $value]);
        $filled   = (new Form('f'))->field('fld', 'Label', $type, $opts)->values(['fld' => $value, 'other' => 'unused']);
        T::ok(fveRender($explicit) === fveRender($filled), "values() changes the markup of a $name field");
    }
},

'a field\'s own value wins, a second call adds to the first, keys without a field are ignored' => function (): void {
    $own = fveRender((new Form('f'))->field('a', 'A', 'text', ['value' => 'own'])->values(['a' => 'from values']));
    T::contains($own, 'value="own"', 'the own value was replaced');
    T::notContains($own, 'from values', 'the value from values() leaked in beside the own one');

    $two = fveRender((new Form('f'))->field('a', 'A', 'text')->field('b', 'B', 'text')->values(['a' => 'one'])->values(['b' => 'two']));
    T::contains($two, 'value="one"', 'the first call was lost');
    T::contains($two, 'value="two"', 'the second call did not apply');

    $none = fveRender((new Form('f'))->field('a', 'A', 'text')->values(['zzz' => 'x']));
    T::ok($none === fveRender((new Form('f'))->field('a', 'A', 'text')), 'a key without a field changed the form');

    $empty = fveRender((new Form('f'))->field('a', 'A', 'text', ['value' => ''])->values(['a' => 'from values']));
    T::contains($empty, 'value=""', 'an own empty value is still an own value');
    T::notContains($empty, 'from values', 'values() replaced an own empty value');
},

'values() and errors() with nothing in them change nothing' => function (): void {
    $plain = fveRender((new Form('f'))->field('a', 'A', 'text')->field('b', 'B', 'select', ['options' => ['x' => 'X']])->submit('Save'));
    $same  = fveRender((new Form('f'))->field('a', 'A', 'text')->field('b', 'B', 'select', ['options' => ['x' => 'X']])->values([])->errors([])->submit('Save'));
    T::ok($plain === $same, 'empty values() and errors() changed the markup');
},

'errors() renders byte for byte what the same error options render' => function (): void {
    foreach (fveCases() as $name => [$type, $opts, $value]) {
        if ($type === 'hidden') continue;
        $explicit = (new Form('f'))->field('fld', 'Label', $type, $opts + ['error' => 'It is wrong <b>']);
        $shown    = (new Form('f'))->field('fld', 'Label', $type, $opts)->errors(['fld' => 'It is wrong <b>']);
        T::ok(fveRender($explicit) === fveRender($shown), "errors() changes the markup of a $name field");
    }
    $html = fveRender((new Form('f'))->field('email', 'Email', 'text')->errors(['email' => 'Not an address']));
    T::contains($html, 'aria-invalid="true"', 'the field is not marked invalid');
    T::contains($html, 'data-gk-error="email">Not an address</div>', 'the message is not in the error slot of its field');
    T::notContains($html, 'gk-message-error', 'a message that belongs to a field was shown above the form too');
},

'an error no field takes is shown once, above the form, escaped' => function (): void {
    $one = fveRender((new Form('f'))->field('a', 'A', 'text')->errors(['_form' => 'Could not save <script>']));
    T::contains($one, '<div class="gk-message gk-message-error" role="alert">', 'no message above the form');
    T::contains($one, 'Could not save &lt;script&gt;', 'the message is not escaped');
    T::ok(strpos($one, 'gk-message-error') < strpos($one, 'class="gk-field'), 'the message is not above the first field');

    $two = fveRender((new Form('f'))->field('a', 'A', 'text')->errors(['_form' => 'First', 'other' => 'Second', 'a' => 'Own']));
    T::contains($two, '<ul class="gk-message-list"><li>First</li><li>Second</li></ul>', 'two messages are not a list');
    T::notContains($two, '<li>Own</li>', 'the message of a field is in the list as well');

    $blank = fveRender((new Form('f'))->field('a', 'A', 'text')->errors(['_form' => '   ']));
    T::notContains($blank, 'gk-message-error', 'an empty message was shown');
},

'a field\'s own error wins, an empty own error is filled' => function (): void {
    $own = fveRender((new Form('f'))->field('a', 'A', 'text', ['error' => 'Mine'])->errors(['a' => 'Theirs']));
    T::contains($own, '>Mine</div>', 'the own error was replaced');
    T::notContains($own, 'Theirs', 'the error of errors() shows beside the own one');
    $empty = fveRender((new Form('f'))->field('a', 'A', 'text', ['error' => ''])->errors(['a' => 'Theirs']));
    T::contains($empty, '>Theirs</div>', 'an empty own error was not filled');
},

'values and errors together fill a form shown again after a POST' => function (): void {
    $post = ['number' => 'INV-7', 'customer' => '', 'amount' => '12,5'];
    $html = fveRender((new Form('invoice'))->action('save.php')
        ->field('number', 'Number', 'text')->field('customer', 'Customer', 'text', ['required' => true])->field('amount', 'Amount', 'text')
        ->values($post)->errors(['customer' => 'Required', 'amount' => 'Not a number'])->submit('Save'));
    T::contains($html, 'value="INV-7"', 'the posted number is gone');
    T::contains($html, 'value="12,5"', 'the posted amount is gone');
    T::contains($html, 'data-gk-error="customer">Required</div>', 'the customer error is missing');
    T::contains($html, 'data-gk-error="amount">Not a number</div>', 'the amount error is missing');
},

];
