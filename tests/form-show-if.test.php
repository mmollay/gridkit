<?php
/**
 * Form 'showIf' (1.105.0): a field that shows only while another field of the form has a value.
 *
 * The markup is checked here; what the script does with it is checked in a browser
 * (ci/browser-1105.js). The rule on the PHP side is "when in doubt, show the field": an
 * unusable condition is reported and ignored, because a field that hides itself for a
 * typo in the condition is a field nobody can fill in.
 */

declare(strict_types=1);

use GridKit\{Form, Lang};

/** @return array{0:string,1:list<string>} the markup and the warnings raised while rendering */
function fsiRender(Form $form): array
{
    Lang::set('en');
    $notes = [];
    set_error_handler(function (int $no, string $msg) use (&$notes): bool {
        if ($no !== E_USER_WARNING) return false;
        $notes[] = $msg;
        return true;
    });
    try {
        $html = T::capture(fn() => $form->render());
    } finally {
        restore_error_handler();
    }
    return [$html, $notes];
}

function fsiForm(mixed $showIf, string $type = 'text', array $opts = []): Form
{
    return (new Form('f'))
        ->field('kind', 'Kind', 'select', ['options' => ['p' => 'P', 'c' => 'C'], 'native' => true])
        ->field('target', 'Target', $type, $opts + ['showIf' => $showIf]);
}

/** @return array<string,callable> */
return [

'showIf puts the condition, as JSON, on the wrapper of the field' => function (): void {
    [$html, $notes] = fsiRender(fsiForm(['field' => 'kind', 'equals' => 'c']));
    T::eq($notes, [], 'a usable condition raised a warning');
    T::contains($html, '<div class="gk-field gk-form-col-16" data-gk-show-if="{&quot;field&quot;:&quot;kind&quot;,&quot;equals&quot;:&quot;c&quot;}">',
        'the condition is not on the wrapper of the field');
    T::eq(substr_count($html, 'data-gk-show-if'), 1, 'another field got the condition');
},

'every test is carried: equals, not, in, filled' => function (): void {
    foreach ([
        [['field' => 'kind', 'not' => 'p'], '{&quot;field&quot;:&quot;kind&quot;,&quot;not&quot;:&quot;p&quot;}'],
        [['field' => 'kind', 'in' => ['p', 'c']], '{&quot;field&quot;:&quot;kind&quot;,&quot;in&quot;:[&quot;p&quot;,&quot;c&quot;]}'],
        [['field' => 'kind', 'filled' => true], '{&quot;field&quot;:&quot;kind&quot;,&quot;filled&quot;:true}'],
        [['field' => 'kind', 'filled' => false], '{&quot;field&quot;:&quot;kind&quot;,&quot;filled&quot;:false}'],
    ] as [$cond, $json]) {
        [$html, $notes] = fsiRender(fsiForm($cond));
        T::eq($notes, [], 'a usable condition raised a warning: ' . json_encode($cond));
        T::contains($html, 'data-gk-show-if="' . $json . '"', 'the condition is missing or changed: ' . json_encode($cond));
    }
},

'values are written as text, because a control holds text' => function (): void {
    [$html] = fsiRender(fsiForm(['field' => 'kind', 'equals' => 5]));
    T::contains($html, '{&quot;field&quot;:&quot;kind&quot;,&quot;equals&quot;:&quot;5&quot;}', 'equals => 5 is not written as "5"');
    [$html] = fsiRender(fsiForm(['field' => 'kind', 'in' => [1, 2.5, true]]));
    T::contains($html, '&quot;in&quot;:[&quot;1&quot;,&quot;2.5&quot;,&quot;1&quot;]', 'in => [1, 2.5, true] is not written as text');
    [$html] = fsiRender(fsiForm(['field' => 'kind', 'filled' => 1]));
    T::contains($html, '&quot;filled&quot;:true', "filled => 1 is not written as true");
},

'what is written into the attribute is escaped' => function (): void {
    [$html] = fsiRender(fsiForm(['field' => 'kind', 'equals' => '"><script>alert(1)</script>']));
    T::notContains($html, '<script>', 'a value ended the attribute and wrote a tag');
    T::contains($html, 'data-gk-show-if="', 'the attribute is gone');
},

'a field without showIf is rendered as it was' => function (): void {
    [$html] = fsiRender((new Form('f'))->field('a', 'A', 'text'));
    T::notContains($html, 'data-gk-show-if', 'the attribute appeared by itself');
    T::contains($html, '<div class="gk-field gk-form-col-16"><label', 'the wrapper changed');
},

'every field type carries it on its own wrapper' => function (): void {
    foreach (['text', 'textarea', 'select', 'multiselect', 'checkbox', 'toggle', 'radio', 'richtext', 'color', 'range', 'file', 'choice', 'date'] as $type) {
        [$html, $notes] = fsiRender(fsiForm(['field' => 'kind', 'equals' => 'c'], $type, ['options' => ['x' => 'X']]));
        T::eq($notes, [], "a $type field raised a warning");
        T::eq(substr_count($html, 'data-gk-show-if'), 1, "a $type field does not carry the condition exactly once");
    }
},

'an unusable condition is reported and ignored: the field stays visible' => function (): void {
    $cases = [
        'not an array'         => ['a string', "it is not an array"],
        'no field'             => [['equals' => 'c'], "it names no 'field'"],
        'an empty field name'  => [['field' => '', 'equals' => 'c'], "it names no 'field'"],
        'a field that is not there' => [['field' => 'nope', 'equals' => 'c'], "no field 'nope' in this form"],
        'an unknown key'       => [['field' => 'kind', 'equals' => 'c', 'contains' => 'x'], 'unknown keys contains'],
        'no test'              => [['field' => 'kind'], "exactly one of 'equals'"],
        'two tests'            => [['field' => 'kind', 'equals' => 'c', 'not' => 'p'], "exactly one of 'equals'"],
        'in without a list'    => [['field' => 'kind', 'in' => 'c'], "'in' is not a list"],
    ];
    foreach ($cases as $what => [$cond, $why]) {
        [$html, $notes] = fsiRender(fsiForm($cond));
        T::eq(count($notes), 1, "$what: expected one warning, got " . count($notes));
        T::contains($notes[0] ?? '', $why, "$what: the warning does not say why");
        T::contains($notes[0] ?? '', "field 'target'", "$what: the warning does not name the field");
        T::notContains($html, 'data-gk-show-if', "$what: the field was hidden anyway");
    }
},

'a condition may name a field that comes after it' => function (): void {
    [$html, $notes] = fsiRender((new Form('f'))
        ->field('a', 'A', 'text', ['showIf' => ['field' => 'b', 'filled' => true]])
        ->field('b', 'B', 'text'));
    T::eq($notes, [], 'a field later in the form was not found');
    T::contains($html, 'data-gk-show-if', 'the condition is missing');
},

'showIf works in a row, beside values() and errors()' => function (): void {
    [$html, $notes] = fsiRender((new Form('f'))->row()
        ->field('kind', 'Kind', 'text', ['width' => 8])
        ->field('t', 'T', 'text', ['width' => 8, 'showIf' => ['field' => 'kind', 'equals' => 'c']])
        ->endRow()->values(['kind' => 'c', 't' => 'x'])->errors(['t' => 'Required']));
    T::eq($notes, [], 'a warning');
    T::contains($html, 'value="x"', 'values() does not reach the conditional field');
    T::contains($html, 'data-gk-error="t">Required</div>', 'errors() does not reach the conditional field');
    T::contains($html, 'gk-form-col-8" data-gk-show-if=', 'the width class and the condition do not share the wrapper');
},

];
