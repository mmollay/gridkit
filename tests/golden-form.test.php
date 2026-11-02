<?php
/**
 * What a form emits, byte for byte.
 *
 * `Form::renderField()` is 598 lines: a switch over thirteen field types around
 * a shared preamble, and the other tests of it (fields.test.php, form.test.php)
 * look for single attributes with a regular expression. That is the right size
 * of check for a rule ("required reaches a control the browser can enforce it
 * on") and the wrong size for a restructuring, where the question is "did one
 * character of any field change?".
 *
 * So this file renders every field type with the options that change its
 * markup (value, required, error, aria, width, inline, placeholder, the
 * options of each type) and compares the result with what the same code
 * emitted when the case was recorded, in tests/golden/form.json. The file is
 * the markup itself, so a change shows up in `git diff` the way a change of
 * source does.
 *
 * A deliberate change of markup: run
 *
 *     GK_UPDATE_GOLDEN=1 php tests/run.php golden-form
 *
 * and read the diff of tests/golden/form.json before committing it. A
 * refactoring must leave that file untouched.
 *
 * Two things differ from run to run and are normalised: the six hex digits
 * that colour and rich-text fields append to their ids (md5 of name and
 * microtime), and nothing else. A case that still differs between two
 * renderings in one process fails on its own, so a new source of randomness
 * cannot hide in the recording.
 */

declare(strict_types=1);

use GridKit\{Form, Lang};

const GOLDEN_FORM_FILE = __DIR__ . '/golden/form.json';

/** gk-color-fld-3fa91c-wrap → gk-color-fld-RRRRRR-wrap, the same for gk-editor-. */
function goldenNormalize(string $html): string
{
    return preg_replace('/(gk-(?:color|editor)-[A-Za-z0-9_-]*?)-[0-9a-f]{6}(?=[-"\'])/', '$1-RRRRRR', $html) ?? $html;
}

/** The markup of a form, plus the warnings GridKit itself raised while rendering it. */
function goldenRender(Form $form): string
{
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
    return goldenNormalize($html) . ($notes ? "\n<!-- E_USER_WARNING: " . implode(' | ', $notes) . ' -->' : '');
}

/** Where two strings first differ, with a little context from each. */
function goldenFirstDiff(string $now, string $recorded): string
{
    $n = min(strlen($now), strlen($recorded));
    $i = 0;
    while ($i < $n && $now[$i] === $recorded[$i]) $i++;
    return sprintf('differs at byte %d — recorded «%s», now «%s»',
        $i, substr($recorded, max(0, $i - 25), 70), substr($now, max(0, $i - 25), 70));
}

/** @return array<string, Form> */
function goldenFormCases(): array
{
    Lang::set('en');

    $fruit = ['a' => 'Apple', 'b' => 'Banana', 'c' => 'Cherry'];
    $long = [];
    for ($i = 1; $i <= 30; $i++) $long["k$i"] = "Option $i";
    $tiles = ['x' => 'Fix it now', 'y' => ['title' => 'Later', 'hint' => 'Next sprint', 'mark' => 'Z'], 'z' => 'Never'];
    $quoted = 'Tom "T" <b>&</b> \'q\'';

    /** One field in a form of its own. */
    $one = fn(string $type, array $opts = [], string $label = 'My label', string $name = 'fld'): Form
        => (new Form('g'))->field($name, $label, $type, $opts);

    return [
        // ── text and the <input> passthrough ─────────────────────────────
        'text/basic'              => $one('text'),
        'text/value-escaped'      => $one('text', ['value' => $quoted]),
        'text/required-placeholder' => $one('text', ['required' => true, 'placeholder' => 'Your name']),
        'text/error'              => $one('text', ['error' => 'Too short <3>']),
        'text/aria-without-label' => $one('text', ['aria' => 'Search'], ''),
        'text/min-max-step'       => $one('text', ['min' => 1, 'max' => 9, 'step' => 2]),
        'text/clearable-empty'    => $one('text', ['clearable' => true]),
        'text/clearable-filled'   => $one('text', ['clearable' => true, 'value' => 'x']),
        'text/width-6'            => $one('text', ['width' => 6]),
        'text/width-auto'         => $one('text', ['width' => 'auto']),
        'text/width-px'           => $one('text', ['width' => '120px']),
        'text/inline'             => $one('text', ['inline' => true]),
        'number/min-max-step'     => $one('number', ['min' => 1, 'max' => 9, 'step' => 2, 'value' => 5]),
        'date/default'            => $one('date', ['value' => '2026-11-02']),
        'date/with-max'           => $one('date', ['max' => '2030-01-01']),
        'datetime/default'        => $one('datetime'),
        'email/required'          => $one('email', ['required' => true]),
        'password/basic'          => $one('password'),
        'tel/basic'               => $one('tel'),
        'url/basic'               => $one('url'),
        'time/basic'              => $one('time'),
        'hidden/field'            => $one('hidden', ['value' => 'v']),
        'unknown/searchable-select' => $one('searchable-select'),

        // ── textarea ─────────────────────────────────────────────────────
        'textarea/basic'          => $one('textarea'),
        'textarea/rows-value'     => $one('textarea', ['rows' => 6, 'value' => "line 1\n<b>line 2</b>"]),
        'textarea/required-error' => $one('textarea', ['required' => true, 'error' => 'Required']),

        // ── select ───────────────────────────────────────────────────────
        'select/default'          => $one('select', ['options' => $fruit]),
        'select/selected'         => $one('select', ['options' => $fruit, 'value' => 'b']),
        'select/placeholder'      => $one('select', ['options' => $fruit, 'placeholder' => 'Pick one']),
        'select/required'         => $one('select', ['options' => $fruit, 'required' => true]),
        'select/disabled'         => $one('select', ['options' => $fruit, 'disabled' => true]),
        'select/searchable'       => $one('select', ['options' => $fruit, 'searchable' => true]),
        'select/long-list'        => $one('select', ['options' => $long, 'value' => 'k7']),
        'select/error'            => $one('select', ['options' => $fruit, 'error' => 'Pick something']),
        'select/aria-without-label' => $one('select', ['options' => $fruit, 'aria' => 'Fruit'], ''),
        'select/no-label'         => $one('select', ['options' => $fruit], ''),
        'select/native'           => $one('select', ['options' => $fruit, 'native' => true]),
        'select/native-selected-required' => $one('select', ['options' => $fruit, 'native' => true, 'value' => 'c', 'required' => true]),

        // ── multiselect ──────────────────────────────────────────────────
        'multiselect/empty'       => $one('multiselect', ['options' => $fruit]),
        'multiselect/selected-array' => $one('multiselect', ['options' => $fruit, 'value' => ['a', 'c']]),
        'multiselect/selected-csv' => $one('multiselect', ['options' => $fruit, 'value' => 'b,c']),
        'multiselect/searchable'  => $one('multiselect', ['options' => $fruit, 'searchable' => true, 'placeholder' => 'Find…']),
        'multiselect/required'    => $one('multiselect', ['options' => $fruit, 'required' => true, 'error' => 'Pick at least one']),
        'multiselect/aria-without-label' => $one('multiselect', ['options' => $fruit, 'aria' => 'Fruits'], ''),

        // ── ajaxselect ───────────────────────────────────────────────────
        'ajaxselect/basic'        => $one('ajaxselect', ['url' => '/api/people']),
        'ajaxselect/with-value'   => $one('ajaxselect', ['url' => '/api/people', 'value' => '42', 'displayValue' => 'Ada Lovelace']),
        'ajaxselect/all-options'  => $one('ajaxselect', ['url' => '/api/people?x=1&y=2', 'labelField' => 'title', 'valueField' => 'uid',
                                        'subtextField' => 'email', 'minChars' => 3, 'searchParam' => 'term', 'placeholder' => 'Search people']),
        'ajaxselect/required-error' => $one('ajaxselect', ['url' => '/api/people', 'required' => true, 'error' => 'Required']),

        // ── toggle and checkbox ──────────────────────────────────────────
        'toggle/plain'            => $one('toggle'),
        'toggle/checked'          => $one('toggle', ['checked' => true]),
        'toggle/value'            => $one('toggle', ['value' => 1]),
        'toggle/required-error'   => $one('toggle', ['required' => true, 'error' => 'Must be on']),
        'toggle/setting'          => $one('toggle', ['hint' => 'Sends a mail on every change.']),
        'toggle/setting-danger'   => $one('toggle', ['hint' => 'Locks you out.', 'danger' => true, 'required' => true, 'error' => 'No', 'checked' => true]),
        'checkbox/plain'          => $one('checkbox'),
        'checkbox/checked'        => $one('checkbox', ['checked' => true]),
        'checkbox/required-error' => $one('checkbox', ['required' => true, 'error' => 'Accept the terms']),

        // ── choice ───────────────────────────────────────────────────────
        'choice/single'           => $one('choice', ['options' => $tiles]),
        'choice/single-chosen'    => $one('choice', ['options' => $tiles, 'value' => 'y']),
        'choice/multiple-csv'     => $one('choice', ['options' => $tiles, 'multiple' => true, 'value' => 'x, z']),
        'choice/multiple-array'   => $one('choice', ['options' => $fruit, 'multiple' => true, 'value' => ['a']]),
        'choice/required-single'  => $one('choice', ['options' => $tiles, 'required' => true]),
        'choice/required-multiple' => $one('choice', ['options' => $fruit, 'multiple' => true, 'required' => true]),
        'choice/aria-without-label' => $one('choice', ['options' => $fruit, 'aria' => 'Pick'], ''),

        // ── radio ────────────────────────────────────────────────────────
        'radio/basic'             => $one('radio', ['options' => $fruit]),
        'radio/value'             => $one('radio', ['options' => $fruit, 'value' => 'b']),
        'radio/inline'            => $one('radio', ['options' => $fruit, 'inline' => true]),
        'radio/required-error'    => $one('radio', ['options' => $fruit, 'required' => true, 'error' => 'Choose']),
        'radio/aria-without-label' => $one('radio', ['options' => $fruit, 'aria' => 'Fruit'], ''),

        // ── range ────────────────────────────────────────────────────────
        'range/default'           => $one('range'),
        'range/custom'            => $one('range', ['min' => 10, 'max' => 50, 'step' => 5, 'value' => 30]),

        // ── file ─────────────────────────────────────────────────────────
        'file/single'             => $one('file'),
        'file/multiple-accept-array' => $one('file', ['multiple' => true, 'accept' => ['pdf', '.jpg']]),
        'file/accept-string'      => $one('file', ['accept' => '.png,.gif']),
        'file/limits-preview'     => $one('file', ['maxSize' => '5 MB', 'minSize' => '10 KB', 'maxTotalSize' => '20 MB', 'maxFiles' => 3, 'preview' => true]),
        'file/hint-icon-label'    => $one('file', ['hint' => 'Drop here', 'icon' => 'image', 'label_text' => 'Choose a picture']),
        'file/required-error'     => $one('file', ['required' => true, 'error' => 'A file is required']),

        // ── color ────────────────────────────────────────────────────────
        'color/default'           => $one('color'),
        'color/value'             => $one('color', ['value' => '#ff8800']),
        'color/aria-without-label' => $one('color', ['aria' => 'Accent'], ''),

        // ── richtext ─────────────────────────────────────────────────────
        'richtext/full'           => $one('richtext'),
        'richtext/basic'          => $one('richtext', ['preset' => 'basic']),
        'richtext/full-upload'    => $one('richtext', ['upload' => '/api/upload']),
        'richtext/basic-upload-headers' => $one('richtext', ['preset' => 'basic', 'upload' => '/api/upload', 'uploadHeaders' => ['X-CSRF' => 'abc"def']]),
        'richtext/language'       => $one('richtext', ['language' => 'de']),
        'richtext/required-error' => $one('richtext', ['required' => true, 'error' => 'Write something']),
        'richtext/value-quotes'   => $one('richtext', ['value' => "<p>He said \"hi\" & left</p>\n<p>'bye'</p>"]),
        'richtext/aria-without-label' => $one('richtext', ['aria' => 'Message'], ''),

        // ── the form around the fields ───────────────────────────────────
        'form/empty'              => new Form('f_empty'),
        'form/submit-only'        => (new Form('f_submit'))->submit('Go'),
        'form/card-ajax-actions'  => (new Form('f_card'))->card()->action('/save?a=1&b=2')->method('POST')->ajax()
                                        ->hidden('id', 7)->field('title', 'Title', 'text', ['required' => true])
                                        ->cancel('Back', '/list')->submit('Save'),
        'form/rows'               => (new Form('f_rows'))
                                        ->row(8)->field('a', 'A', 'text', ['width' => 6])->field('b', 'B', 'text', ['width' => 6])->endRow()
                                        ->row()->field('c', 'C', 'text')->endRow(),
    ];
}

/** @return array<string,callable> */
return [

'every case renders the markup that was recorded' => function (): void {
    $now = [];
    foreach (goldenFormCases() as $name => $form) {
        $now[$name] = goldenRender($form);
        T::ok($now[$name] === goldenRender($form),
            "$name renders differently on a second run — something in it changes between runs and is not normalised");
    }

    if (getenv('GK_UPDATE_GOLDEN') === '1') {
        if (!is_dir(dirname(GOLDEN_FORM_FILE))) mkdir(dirname(GOLDEN_FORM_FILE), 0775, true);
        file_put_contents(GOLDEN_FORM_FILE, json_encode($now,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n");
        return;
    }

    $raw = is_file(GOLDEN_FORM_FILE) ? file_get_contents(GOLDEN_FORM_FILE) : false;
    T::ok($raw !== false, 'tests/golden/form.json is missing — record it from known-good code: GK_UPDATE_GOLDEN=1 php tests/run.php golden-form');
    if ($raw === false) return;

    $recorded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    $missing = array_diff(array_keys($now), array_keys($recorded));
    $stale   = array_diff(array_keys($recorded), array_keys($now));
    T::ok($missing === [], 'cases without a recording: ' . implode(', ', $missing) . ' — record again and read the diff');
    T::ok($stale === [], 'recordings without a case: ' . implode(', ', $stale) . ' — record again and read the diff');

    foreach ($now as $name => $html) {
        if (!isset($recorded[$name])) continue;
        T::ok($html === $recorded[$name], "$name " . goldenFirstDiff($html, $recorded[$name]));
    }
},

'the cases cover every field type the class renders itself' => function (): void {
    $names = implode(' ', array_keys(goldenFormCases()));
    foreach (['textarea', 'select', 'multiselect', 'ajaxselect', 'checkbox', 'toggle', 'radio', 'file',
              'richtext', 'color', 'range', 'choice', 'text', 'number', 'date', 'datetime'] as $type) {
        T::ok(str_contains(" $names", " $type/"), "no golden case for the $type field");
    }
},

];
