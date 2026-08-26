<?php
/**
 * Form field types.
 *
 * `Form` renders eleven types itself and passes everything else through as an
 * `<input type="…">`. That passthrough is useful — it covers `month`, `week`
 * and whatever HTML adds next — but it also swallowed typos: the skill
 * documented `'searchable-select'`, which is not a type, and every browser
 * rendered it as a plain text box. Nothing said a word.
 */

declare(strict_types=1);

use GridKit\{Form, Lang};

/** Render one field and return the markup. */
function field(string $type, array $opts = []): string
{
    return T::capture(fn() => (new Form('f_' . preg_replace('/\W/', '_', $type)))
        ->field('probe', 'Probe', $type, $opts)
        ->render());
}

/** @return array<string,callable> */
return [

'each type renders the element it promises' => function (): void {
    Lang::set('en');

    $expect = [
        'text'     => '/<input[^>]*type="text"[^>]*name="probe"|<input[^>]*name="probe"[^>]*type="text"/',
        'number'   => '/type="number"/',
        'email'    => '/type="email"/',
        'date'     => '/type="date"/',
        'time'     => '/type="time"/',
        'url'      => '/type="url"/',
        'tel'      => '/type="tel"/',
        'password' => '/type="password"/',
        'hidden'   => '/type="hidden"/',
        'color'    => '/type="color"/',
        'range'    => '/type="range"/',
        'textarea' => '/<textarea[^>]*name="probe"/',
        'checkbox' => '/type="checkbox"/',
        'toggle'   => '/type="checkbox"/',
    ];

    foreach ($expect as $type => $pattern) {
        $html = field($type, ['options' => ['a' => 'A']]);
        T::ok((bool) preg_match($pattern, $html), "$type did not render its element");
    }
},

'datetime becomes datetime-local' => function (): void {
    Lang::set('en');
    T::contains(field('datetime'), 'type="datetime-local"',
        'browsers do not know type="datetime"');
},

'the select types carry their values' => function (): void {
    Lang::set('en');
    $opts = ['options' => ['a' => 'Apple', 'b' => 'Banana'], 'value' => 'b'];

    foreach (['select', 'multiselect', 'ajaxselect'] as $type) {
        $html = field($type, $opts);
        T::ok(trim($html) !== '', "$type rendered nothing");
        T::contains($html, 'probe', "$type lost the field name");
    }

    T::contains(field('select', $opts), 'Banana', 'the selected option is shown');
    T::contains(field('radio', $opts), 'type="radio"', 'radio renders inputs');
    T::contains(field('radio', $opts), 'Apple', 'and their labels');
},

'an unknown type warns instead of failing silently' => function (): void {
    Lang::set('en');

    $warning = null;
    set_error_handler(static function (int $n, string $m) use (&$warning): bool {
        $warning = $m;
        return true;
    });
    $html = field('searchable-select');
    restore_error_handler();

    T::ok($warning !== null, 'a typo in a field type must not pass in silence');
    T::contains((string) $warning, 'searchable-select', 'the message names the type');
    T::contains((string) $warning, 'select', 'and suggests what was meant');
    T::contains($html, 'probe', 'it still renders something usable');
},

'valid HTML types the switch does not handle stay silent' => function (): void {
    Lang::set('en');

    foreach (['month', 'week', 'search', 'image'] as $type) {
        $warning = null;
        set_error_handler(static function (int $n, string $m) use (&$warning): bool {
            $warning = $m;
            return true;
        });
        field($type);
        restore_error_handler();

        T::ok($warning === null, "$type is valid HTML and must not warn");
    }
},

'required, width and placeholder reach the markup' => function (): void {
    Lang::set('en');
    $html = field('text', ['required' => true, 'width' => 8, 'placeholder' => 'Type here']);
    T::contains($html, 'required', 'required is set');
    T::contains($html, 'Type here', 'the placeholder is set');
},

'every field gets the error slot the AJAX handler writes into' => function (): void {
    Lang::set('en');
    // GK.form.submit() looks for [data-gk-error="<name>"]. A field without one
    // swallows its server-side error message.
    foreach (['text', 'textarea', 'select', 'checkbox', 'radio', 'file', 'color', 'range'] as $type) {
        $html = field($type, ['options' => ['a' => 'A']]);
        T::contains($html, 'data-gk-error="probe"', "$type has nowhere to show an error");
    }
},


'the rich text editor speaks the page language' => function (): void {
    // It was pinned to 'de' for every user, which put lang="de" around English
    // content — wrong for a screen reader, and wrong for the toolbar as soon as
    // a translation file for that locale is present.
    Lang::set('en');
    T::contains(field('richtext', ['value' => '<p>x</p>']), 'language:"en"',
        'an english page gets an english editor');

    Lang::set('de');
    T::contains(field('richtext', ['value' => '<p>x</p>']), 'language:"de"',
        'a german page gets a german one');

    Lang::set('en');
    T::contains(field('richtext', ['language' => 'fr']), 'language:"fr"',
        'a field may pin its own');
},

'a hidden field declared through field() does not warn' => function (): void {
    Lang::set('en');
    // `->hidden()` always sets a value; `->field($n, $l, 'hidden')` reaches the
    // same branch and used to read a key that was never there.
    $warning = null;
    set_error_handler(static function (int $n, string $m) use (&$warning): bool {
        $warning = $m; return true;
    });
    $html = T::capture(fn() => (new Form('f'))->field('p', 'P', 'hidden')->render());
    restore_error_handler();

    T::ok($warning === null, 'no diagnostic: ' . (string) $warning);
    T::contains($html, 'name="p"', 'and the field is still there');
},

];
