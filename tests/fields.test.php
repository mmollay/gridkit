<?php
/**
 * What a form field promises, and what it emits.
 *
 * Round fourteen. Every case here is a documented option that a field type
 * accepted, stored and then did nothing with — the shape this repo keeps
 * producing — plus the seams where the same widget is built twice in two
 * places and only one copy gets fixed.
 *
 * The `required` case is the sharpest: 1.42.0 announced it fixed, and it was
 * fixed — in Select::searchable(), which nothing outside these tests calls.
 * Every doc, the demo and SPEC.md build a select through Form::field(), and
 * that copy still emitted a hidden input the browser cannot validate, under a
 * label wearing the red asterisk that says the field is required.
 */

declare(strict_types=1);

use GridKit\{Auth, Form, Lang, Table};

/** Field types where HTML `required` can be enforced by the browser. */
const VALIDATABLE = ['text', 'textarea', 'select', 'multiselect', 'ajaxselect',
                     'toggle', 'checkbox', 'radio', 'file'];

/** Every type the class documents. */
const ALL_TYPES = ['text', 'textarea', 'select', 'multiselect', 'ajaxselect',
                   'checkbox', 'toggle', 'radio', 'range', 'color', 'file', 'richtext'];

function fieldHtml(string $type, array $opts = []): string
{
    Lang::set('en');
    return T::capture(fn() => (new Form('f'))
        ->field('fld', 'My label', $type, $opts + ['options' => ['a' => 'A', 'b' => 'B'], 'url' => '/s'])
        ->render());
}

/** Attributes that are real, i.e. not sitting inside a quoted value. */
function injectedHandlers(string $html): array
{
    $found = [];
    preg_match_all('/<[a-zA-Z][^>]*>/', $html, $tags);
    foreach ($tags[0] as $tag) {
        $stripped = preg_replace('/="[^"]*"/', '=""', $tag) ?? $tag;
        if (preg_match('/\s(on[a-z]+)\s*=/i', $stripped, $m)) $found[] = $m[1];
    }
    return array_unique($found);
}

/** @return array<string,callable> */
return [

'required reaches a control the browser can enforce it on' => function (): void {
    foreach (VALIDATABLE as $type) {
        $html = fieldHtml($type, ['required' => true]);
        // A hidden input is barred from constraint validation, so `required`
        // on one is decoration. The carrier has to be something else.
        T::ok(
            (bool) preg_match('/<(input|select|textarea)(?![^>]*type="hidden")[^>]*\srequired/', $html),
            "['required' => true] on a $type field emits nothing the browser will enforce"
        );
    }
},

'the required marker is not shown for a requirement nothing enforces' => function (): void {
    // range and color always carry a value, so HTML required is a no-op there;
    // richtext is checked in script because CKEditor hides its carrier. What
    // must never happen is a starred label with no enforcement anywhere.
    $rich = fieldHtml('richtext', ['required' => true]);
    T::contains($rich, 'data-gk-required-rich',
        'a required rich-text field must be marked for the script check');

    $js = (string) file_get_contents(__DIR__ . '/../js/gridkit.js');
    T::contains($js, 'data-gk-required-rich', 'nothing in the client acts on that mark');
},

'no label points at an element that is not there' => function (): void {
    foreach (ALL_TYPES as $type) {
        $html = fieldHtml($type);
        if (!preg_match('/<label[^>]*class="gk-label-text"[^>]*>/', $html, $l)) continue;
        if (!preg_match('/\sfor="([^"]*)"/', $l[0], $m)) continue;   // composite: named otherwise
        T::ok(
            (bool) preg_match('/id="' . preg_quote($m[1], '/') . '"/', $html),
            "the $type label points at id=\"{$m[1]}\", which nothing on the page has"
        );
    }
},

'every widget has a name, however it is built' => function (): void {
    foreach (ALL_TYPES as $type) {
        if ($type === 'checkbox') continue;          // its label is inside the control
        $html = fieldHtml($type);
        $named = str_contains($html, 'aria-labelledby="fld-label"')
              || preg_match('/<label[^>]*\sfor="fld"/', $html)
              || str_contains($html, 'aria-label=');
        T::ok((bool) $named, "a $type field renders with nothing naming it");
    }
},

'a hostile field name cannot become an attribute' => function (): void {
    // The name reaches ids that are then written into an inline <script> as a
    // single-quoted JS string. Escaping alone is wrong there — the two
    // spellings must stay byte-identical — so the name is slugged.
    $evil = 'c" onmouseover="window.__GK_XSS=1//';
    foreach (['color', 'richtext', 'textarea', 'text', 'select', 'file'] as $type) {
        $html = T::capture(fn() => (new Form('f'))
            ->field($evil, 'L', $type, ['options' => ['a' => 'A'], 'rows' => '3" onload="x'])
            ->render());
        T::eq(injectedHandlers($html), [],
            "a $type field let the name become a real event handler");
    }
},

'a malformed remember cookie cannot take the application down' => function (): void {
    // A cookie sent as gk_remember[] arrives as an array. Under strict_types
    // str_contains() on it is a fatal TypeError — so any anonymous visitor
    // could 500 every guarded page by setting one cookie, and keep it that
    // way, because logout crashed on the same line and never cleared it.
    $before = $_COOKIE['gk_remember'] ?? null;
    $r = new ReflectionMethod(Auth::class, 'checkRememberCookie');
    $r->setAccessible(true);

    foreach ([['a' => 'b'], [], '', 'no-colon', ':', 'x:'] as $value) {
        $_COOKIE['gk_remember'] = $value;
        $ok = true;
        try { $r->invoke(null); } catch (\Throwable $e) { $ok = false; }
        T::ok($ok, 'checkRememberCookie() died on ' . var_export($value, true));
    }

    if ($before === null) unset($_COOKIE['gk_remember']); else $_COOKIE['gk_remember'] = $before;
},

'the login page cannot be handed an asset path that breaks out' => function (): void {
    Lang::set('en');
    $html = T::capture(fn() => Auth::renderLogin([
        'cssPath' => 'x" onload="window.__X=1',
        'jsPath'  => 'y" onerror="window.__Y=1',
    ]));
    T::eq(injectedHandlers($html), [], 'a caller-supplied asset path injected an attribute');
},

'a table ships the key it was told to select rows by' => function (): void {
    // The client re-render read data.rowId and fell back to "id". The key was
    // never in the payload, so on a table keyed by anything else every row id
    // went empty on the first sort and the selection collapsed.
    Lang::set('en');
    $html = T::capture(fn() => (new Table('people'))
        ->setData([['uuid' => 'a1', 'name' => 'Cara']])
        ->selectable('uuid')
        ->column('name', 'Name')
        ->render());

    preg_match('/data-gk-data>(.*?)<\/script>/s', $html, $m);
    $data = json_decode($m[1] ?? '{}', true);
    T::eq($data['rowId'] ?? null, 'uuid', 'the select key is missing from the embedded data');
},

'the client keeps what it is not re-rendering' => function (): void {
    $js = (string) file_get_contents(__DIR__ . '/../js/gridkit.js');

    // The bulk bar sits beside the toolbar, not inside the table, and the
    // reload swept it away — selection went on working with nothing on screen.
    T::contains($js, 'ch !== bulkBar', 'the reload removes the bulk bar again');

    // toolbar(false) is a documented option; the reload assumed a toolbar and
    // threw after emptying the wrapper, leaving a blank space and no way back.
    T::contains($js, 'else wrap.insertAdjacentHTML("afterbegin", html)',
        'the reload still assumes a toolbar is there');

    // textContent flattened the submit button into "saveSave" for good.
    T::contains($js, 'btn._origHTML = btn.innerHTML', 'the submit button loses its markup again');
    T::notContains($js, '_origText', 'the old flattening restore is still in place');

    // Modal content arrives after DOMContentLoaded, so the widget binders
    // have to run again — without this a form in a table modal is inert.
    T::contains($js, 'if (GK.selectSearch) GK.selectSearch.init(body)',
        'a form loaded into a modal never gets its selects bound');
},

];
