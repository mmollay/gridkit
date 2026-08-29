<?php
/**
 * JavaScript-surface tests.
 *
 * These check the seams between the PHP that writes the markup, the JavaScript
 * that animates it, and the documentation that tells people — and agents —
 * what to call. Nothing here executes JavaScript; every one of them is a
 * consistency check between files that drift apart silently.
 *
 * The one that started this file: GRIDKIT_SKILL.md documented
 * `GK.table.refresh('table-id')` from 1.10 onwards. The method never existed.
 * A doc that teaches an API is only useful if the API is really there, so the
 * check below reads every call out of the documentation and looks for it.
 */

declare(strict_types=1);

/** @return array<string,callable> */
return [

'every GK call the skill document shows really exists' => function (): void {
    $doc = (string) file_get_contents(__DIR__ . '/../GRIDKIT_SKILL.md');
    $js  = (string) file_get_contents(__DIR__ . '/../js/gridkit.js');

    // GK.a.b( … ) — the two-level calls the JavaScript API section teaches.
    preg_match_all('/\bGK\.([a-zA-Z_]\w*)\.([a-zA-Z_]\w*)\s*\(/', $doc, $m, PREG_SET_ORDER);
    T::ok(count($m) > 5, 'expected the document to show some JS calls, found ' . count($m));

    foreach ($m as [$call, $ns, $method]) {
        // The namespace: GK.toast = {  |  toast: {
        T::ok(
            (bool) preg_match('/\bGK\.' . $ns . '\s*=|^\s*' . $ns . ':\s*\{/m', $js),
            "GRIDKIT_SKILL.md calls $call but js/gridkit.js has no GK.$ns"
        );
        // The method: `method(` or `method:` at the head of a line.
        T::ok(
            (bool) preg_match('/^\s*' . $method . '\s*[:(]/m', $js),
            "GRIDKIT_SKILL.md calls $call but js/gridkit.js defines no $method()"
        );
    }
},

'the skill document is stamped with the current version' => function (): void {
    $version = trim((string) file_get_contents(__DIR__ . '/../VERSION'));
    $doc     = (string) file_get_contents(__DIR__ . '/../GRIDKIT_SKILL.md');
    preg_match('/^> \*\*Version:\*\* ([0-9.]+)/m', $doc, $m);
    T::ok(isset($m[1]), 'GRIDKIT_SKILL.md has no version header');
    // An agent reads this file to learn the API. A stale stamp tells it the
    // API is twelve releases older than it is.
    T::eq($m[1], $version, 'GRIDKIT_SKILL.md version header');
},

'every translation key the JavaScript asks for exists in both locales' => function (): void {
    $js = (string) file_get_contents(__DIR__ . '/../js/gridkit.js');
    $en = require __DIR__ . '/../lang/en.php';
    $de = require __DIR__ . '/../lang/de.php';

    // Only whole literal keys. `_t("action_" + name)` builds its key at run
    // time from the action catalogue and cannot be checked here.
    preg_match_all('/\b_t(?:Or)?\(\s*"([a-z0-9_]+)"\s*[,)]/', $js, $m);
    $keys = array_unique($m[1]);
    T::ok(count($keys) > 10, 'expected a good number of JS keys, found ' . count($keys));

    foreach ($keys as $key) {
        // _t() falls back to returning the key itself, so a missing entry
        // shows the user the literal string "too_large" and throws nothing.
        T::ok(isset($en["js.$key"]), "js/gridkit.js asks for _t(\"$key\") — missing from lang/en.php");
        T::ok(isset($de["js.$key"]), "js/gridkit.js asks for _t(\"$key\") — missing from lang/de.php");
    }
},

'no upload error is hardcoded past the translation layer' => function (): void {
    $js = (string) file_get_contents(__DIR__ . '/../js/gridkit.js');
    // Every branch of the upload validator pushes onto the same array. If one
    // of them concatenates English instead of calling _t(), a German user gets
    // a mixed-language error list — which is what "too large" used to do.
    preg_match_all('/errors\.push\(\s*([^\n]*)/', $js, $m);
    T::ok(count($m[1]) >= 5, 'expected several error branches, found ' . count($m[1]));
    foreach ($m[1] as $line) {
        T::ok(
            str_contains($line, '_t('),
            'an upload error is built without _t(): ' . trim($line)
        );
    }
},

'every data-gk attribute the PHP writes is read by something' => function (): void {
    $php = '';
    foreach (glob(__DIR__ . '/../src/*.php') as $f) $php .= file_get_contents($f);
    $js  = (string) file_get_contents(__DIR__ . '/../js/gridkit.js');
    $css = (string) file_get_contents(__DIR__ . '/../css/gridkit.css')
         . (string) file_get_contents(__DIR__ . '/../css/themes.css');

    preg_match_all('/data-gk-([a-z-]+)/', $php, $m);
    $attrs = array_unique($m[1]);
    T::ok(count($attrs) > 15, 'expected many data-gk attributes, found ' . count($attrs));

    // Some attributes only label an element — the JavaScript finds it by id
    // or class and the attribute is there for anyone inspecting the DOM.
    // Those are inert by design. The ones that matter are the attributes
    // that DECLARE something: a limit, a mode, a target. When nobody reads
    // one of those, the markup makes a promise it does not keep —
    // data-gk-multiple said "one file only" and the drop handler never
    // looked, so five files dropped on a single-file field were all taken.
    $markers = ['beleg-modal', 'chips', 'stats', 'tableheader', 'upload', 'modal-overlay'];

    foreach ($attrs as $attr) {
        if (in_array($attr, $markers, true)) continue;
        // JavaScript reads them either literally or through dataset camelCase.
        $camel = 'gk' . str_replace(' ', '', ucwords(str_replace('-', ' ', $attr)));
        $read  = str_contains($js, "data-gk-$attr")
              || str_contains($js, $camel)
              || str_contains($css, "data-gk-$attr");
        T::ok($read, "src/ writes data-gk-$attr but no JS or CSS reads it");
    }
},

'the tooltip bootstraps the same way the rest of the library does' => function (): void {
    $js = (string) file_get_contents(__DIR__ . '/../js/gridkit.js');
    // A bare DOMContentLoaded listener never fires for a script that loads
    // with `async` or arrives inside an AJAX fragment — the tooltip then does
    // nothing at all, with no error to go on.
    T::ok(str_contains($js, 'function _gkReady('), 'the ready helper is gone');
    preg_match_all('/document\.addEventListener\(\s*"DOMContentLoaded"/', $js, $m);
    T::ok(
        count($m[0]) <= 2,
        'a DOMContentLoaded listener was added without the readyState guard (' . count($m[0]) . ' found)'
    );
},

'the library never calls an address of its own' => function (): void {
    $js = (string) file_get_contents(__DIR__ . '/../js/gridkit.js');
    // Strip comments — the history of this bug is written in one of them.
    $code = preg_replace('#//[^\n]*#', '', $js);

    // GK.belegModal used to POST to /faktura/api/beleg/unlink, a route from
    // the author's own invoicing application. On any other site that is a
    // 404 whose rejection nobody caught. A component library may know its
    // own markup; it may never know your routes.
    preg_match_all('#\bfetch\(\s*[\'"](/[^\'"]*)#', (string) $code, $m);
    foreach ($m[1] as $path) {
        T::ok(false, "js/gridkit.js fetches the fixed path $path — that belongs to an application, not a library");
    }
    T::ok(true, 'no fixed application path is fetched');
},

'the JavaScript carries no German identifiers' => function (): void {
    $js = (string) file_get_contents(__DIR__ . '/../js/gridkit.js');
    // The compat reads (d.groups || d.gruppen) and the CSS class names stay —
    // both are contracts with code that already exists. These are ours.
    foreach (['_tOderText', 'ersatz', 'kombi', '/api/suche'] as $word) {
        T::notContains($js, $word, "js/gridkit.js still contains \"$word\"");
    }
    // The German response keys must survive as a fallback, though.
    T::contains($js, 'd.gruppen', 'the pre-1.39 response fallback was dropped');
},

/**
 * A modal was a div lying on top of the page: no role, no name, and focus left
 * on the button behind the overlay. Pressing Tab walked the page underneath —
 * controls the user could neither see nor click — with Escape the only way out.
 */
'a modal announces itself as a dialog' => function (): void {
    $js = (string) file_get_contents(__DIR__ . '/../js/gridkit.js');
    T::contains($js, 'role="dialog"', 'it is a dialog, not a div');
    T::contains($js, 'aria-modal="true"', 'and everything behind it is out of bounds');
    T::contains($js, 'aria-labelledby="', 'and it has a name');
    T::contains($js, 'gk-modal-title-', 'the title id is generated, since modals stack');
},

'a modal keeps the keyboard inside it' => function (): void {
    $js = (string) file_get_contents(__DIR__ . '/../js/gridkit.js');
    T::contains($js, '_trap(ov, e)', 'Tab is intercepted');
    T::contains($js, 'e.shiftKey && document.activeElement === first',
        'and wraps backwards from the first control');
    T::contains($js, '!e.shiftKey && document.activeElement === last',
        'and forwards from the last');
},

'a modal moves focus in and gives it back' => function (): void {
    $js = (string) file_get_contents(__DIR__ . '/../js/gridkit.js');
    T::contains($js, '_focusInto(ov)', 'focus starts inside the dialog');
    T::contains($js, 'ov._gkOpener = opener', 'the opener is remembered');
    T::contains($js, 'opener.isConnected',
        'and only refocused if it still exists — a reloaded table has replaced its rows');
},

'a failed field is marked invalid and unmarked when fixed' => function (): void {
    $js = (string) file_get_contents(__DIR__ . '/../js/gridkit.js');
    T::contains($js, 'input.setAttribute("aria-invalid", "true")', 'set on failure');
    T::contains($js, 'el.removeAttribute("aria-invalid")',
        'and cleared on the next submit — otherwise a fixed field stays wrong forever');
    T::contains($js, 'form.querySelector(".gk-has-error")',
        'and the first failed field is focused, not left off-screen');
},

/**
 * A row button with both `onclick` and `confirm` asked before acting on the
 * server-rendered page and then, from the first sort onwards, acted without
 * asking. Table::renderButtons wraps the handler in GK.confirm because an
 * inline handler runs before any delegated listener could stop it — and the
 * client renderer, drawing the same button, set the handler raw. The delegated
 * data-gk-confirm path it relied on cannot intercept an inline onclick.
 *
 * The failure mode is a delete that happens with no prompt, so this is pinned
 * on both sides.
 */
'the client wraps a confirmed onclick the way the server does' => function (): void {
    $js = (string) file_get_contents(__DIR__ . '/../js/gridkit.js');

    T::contains($js, '"GK.confirm(" +',
        'the client builds the same wrapper');
    T::contains($js, ',{danger:true}).then(function(ok){if(ok){',
        'with the same shape the server emits');
    T::ok(
        (bool) preg_match('/if \(bopts\.confirm\) \{\s*const msg =/', $js),
        'and only when confirm is set'
    );

    // The server side of the pair, so a change to either is caught here.
    $php = (string) file_get_contents(__DIR__ . '/../src/Table.php');
    T::contains($php, "'GK.confirm('",
        'the server still wraps too — this test exists because the two disagreed');
},

];
