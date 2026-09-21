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

    /*
     * jsConfig() exports four families, each flattened with its own prefix:
     * js.* loses it entirely, while action.*, pagination.* and format.*
     * keep it with the dot turned into an underscore. A key the script asks
     * for may live under any of them, so the test has to look where the
     * export actually put it. Checking only js.* reported the shared
     * pagination catalogue as missing when it was there under its own name.
     */
    $sources = static function (string $key): array {
        $candidates = ["js.$key"];
        foreach (['action', 'pagination', 'format'] as $family) {
            if (str_starts_with($key, $family . '_')) {
                $candidates[] = $family . '.' . substr($key, strlen($family) + 1);
            }
        }
        return $candidates;
    };

    foreach ($keys as $key) {
        // _t() falls back to returning the key itself, so a missing entry
        // shows the user the literal string "too_large" and throws nothing.
        $where = $sources($key);
        $inEn = array_filter($where, static fn(string $k): bool => isset($en[$k]));
        $inDe = array_filter($where, static fn(string $k): bool => isset($de[$k]));
        T::ok($inEn !== [], "js/gridkit.js asks for _t(\"$key\") — not in lang/en.php under " . implode(' or ', $where));
        T::ok($inDe !== [], "js/gridkit.js asks for _t(\"$key\") — not in lang/de.php under " . implode(' or ', $where));
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
    // The guard moved into _gkRestoreFocus when the lightbox turned out to
    // need the same thing; the requirement is unchanged, so this points at
    // where it lives now rather than being dropped.
    T::contains($js, 'if (!el || !el.isConnected || typeof el.focus !== "function") return;',
        'and only refocused if it still exists — a reloaded table has replaced its rows');
    T::contains($js, '_gkRestoreFocus(opener);',
        'which the modal calls when it closes');
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

/**
 * A markup hook the page author has to type must be taught somewhere they can
 * read it.
 *
 * Most `data-gk-*` attributes are written by GridKit's own PHP — nobody hand-
 * types `data-gk-select-search`, `Form` emits it — and those need no entry of
 * their own. The ones that matter are the hooks NO component writes, because
 * then the author is the only possible source, and if the skill document does
 * not show them the feature might as well not exist. That is derivable rather
 * than a list somebody has to remember to extend, which is the point: a widget
 * added tomorrow with markup nobody documents fails here on its own.
 *
 * When this was written, three hooks qualified — `data-gk-tabs`,
 * `data-gk-tabpanel` and `data-gk-tooltip-rich` — and NONE of the three was
 * documented. Tabs and the rich tooltip were shipped, demonstrated on the
 * landing page, announced in the changelog, and absent from the one file an
 * agent reads to learn this library.
 */
'a markup hook only the page author can write is documented' => function (): void {
    $js  = (string) file_get_contents(__DIR__ . '/../js/gridkit.js');
    $doc = (string) file_get_contents(__DIR__ . '/../GRIDKIT_SKILL.md');

    $php = '';
    foreach (glob(__DIR__ . '/../src/*.php') as $f) {
        $php .= (string) file_get_contents($f);
    }

    preg_match_all('/querySelectorAll?\(\s*[\'"]\[(data-gk-[a-z-]+)/', $js, $m);
    $hooks = array_values(array_unique($m[1]));

    $handWritten = 0;
    foreach ($hooks as $hook) {
        // A component writes it, so the author never types it.
        if (str_contains($php, $hook)) continue;
        $handWritten++;
        T::ok(str_contains($doc, $hook),
            "the library binds to [$hook], no src/ component writes it, and "
            . 'GRIDKIT_SKILL.md never mentions it — so it can only be written '
            . 'by hand, by someone with no way to learn it exists');
    }

    // Without this the whole test passes by finding nothing: change how the
    // library queries its hooks and the loop above quietly checks zero of them
    // while still reporting green.
    T::ok($handWritten >= 3,
        "the scan found $handWritten hand-written hooks; it found 3 when this "
        . 'was written, so 0 means the pattern stopped matching, not that the '
        . 'library stopped having them');
},

'the live table does not put an error page where the rows were' => function (): void {
    // GK.liveTable wrote whatever came back into the container: the 401 JSON of
    // an expired session, a 500 page, the 403 of a firewall. GK.table.reload had
    // handled all of that for a long time; its sibling never did.
    $js = (string) file_get_contents(__DIR__ . '/../js/gridkit.js');
    T::ok((bool) preg_match('/GK\.liveTable\s*=\s*\{(.*?)\n  \};/s', $js, $m), 'GK.liveTable was not found');
    $live = $m[1] ?? '';
    T::eq(substr_count($live, 'fetch('), 1, 'loadUrl and reload share one request path');
    T::contains($live, 'r.ok', 'the status of the answer is never looked at');
    T::contains($live, '_gkRun', 'an older answer may still overwrite a newer one');
    T::contains($live, 'gk-table-error', 'a failed request tells nobody');
    T::ok(!preg_match('/\.catch\(function \(\) \{\}\)/', $live), 'errors are swallowed by an empty catch');
    // Self mode cuts its container out of a whole page. A whole page WITHOUT
    // that container is some other page, and used to be inserted sidebar and all.
    T::contains($live, '<!doctype', 'a whole page is still inserted where a list belongs');
},

'escape closes the layer on top, not the modal underneath it' => function (): void {
    // The modal's handler hangs on document and used to ask only "is a modal
    // open?". Escape in a searchable select inside a modal closed the list AND
    // the modal with everything typed into it; Escape on a GK.confirm over a
    // modal answered "cancel" and closed the modal as well.
    $js = (string) file_get_contents(__DIR__ . '/../js/gridkit.js');
    T::ok((bool) preg_match('/modal:\s*\{\s*stack: \[\],\s*init\(\) \{(.*?)\n      \},/s', $js, $m), 'GK.modal.init was not found');
    T::contains($m[1] ?? '', 'defaultPrevented', 'a widget that already handled the key is ignored');
    T::contains($m[1] ?? '', '_gkLayerAbove()', 'nothing checks whether another layer lies on top');
    T::ok((bool) preg_match('/function _gkLayerAbove\(\) \{(.*?)\n  \}/s', $js, $layer), '_gkLayerAbove() is missing');
    foreach (['.gk-confirm-overlay', '.gk-lightbox.open', '#gk-beleg-modal.is-open', '.gk-search-overlay'] as $sel) {
        T::contains($layer[1] ?? '', $sel, "the layer check does not know $sel");
    }
    // The header dropdown's listener is OLDER than the modal's: by the time the
    // modal asks, the menu is closed. A selector cannot see that — the dropdown
    // has to claim the key. (1.80.3 first shipped the selector; it never matched.)
    T::ok((bool) preg_match('/\[data-gk-dropdown\]\.open"\);\s*if \(open\) \{\s*(\/\/[^\n]*\s*)*e\.preventDefault\(\);/', $js),
        'the header dropdown closes on Escape without claiming the key');
    // The multi select had no key handling at all.
    T::ok((bool) preg_match('/GK\.multiSelect = \{(.*?)\n  \};/s', $js, $multi), 'GK.multiSelect was not found');
    T::contains($multi[1] ?? '', 'e.key !== "Escape"', 'the multi select still leaves Escape to the modal underneath');
    // The AJAX select returned early on an empty or loading list, so its Escape
    // branch was never reached exactly when the list showed "no results".
    T::ok((bool) preg_match('/GK\.ajaxSelect = \{(.*?)\n  \};/s', $js, $ajax), 'GK.ajaxSelect was not found');
    $esc  = strpos($ajax[1] ?? '', 'e.key === "Escape"');
    $gate = strpos($ajax[1] ?? '', 'if (!opts.length) return;');
    T::ok($esc !== false && $gate !== false && $esc < $gate, 'the AJAX select asks for its options before it looks at Escape');
    // Its list is hidden by CSS until first use: style.display is "" then, not
    // "none", and a guard reading the inline style let a never-opened list
    // swallow the first Escape in a modal.
    T::notContains($ajax[1] ?? '', 'dropdown.style.display === "none"', 'the AJAX select guesses its open state from the inline style');
},

'an AJAX form says what happened' => function (): void {
    // {ok:false, error:"…"} changed nothing on screen: the button came back and
    // that was all. The skill promised a toast for {ok:true, message:"…"} that
    // no code ever showed, and a non-JSON answer raised a native alert().
    $js = (string) file_get_contents(__DIR__ . '/../js/gridkit.js');
    T::ok((bool) preg_match('/\n      submit\(form\) \{(.*?)\n      \},\n    \},/s', $js, $m), 'GK.form.submit was not found');
    $submit = $m[1] ?? '';
    T::contains($submit, 'GK.toast.success(data.message)', 'a success message is never shown');
    T::contains($submit, 'data.error || data.message', 'a general error is never shown');
    T::notContains($submit, 'alert(', 'a failure still raises a native alert');
    // The modal loader put the body of an error response where the form belongs.
    T::ok((bool) preg_match('/\n      open\(title, url, params, size\) \{(.*?)\n      close\(\)/s', $js, $l), 'GK.modal.open was not found');
    T::contains($l[1] ?? '', 'r.ok', 'the modal loader never looks at the status');
},

'a hand-written modal gets what a GK.modal has' => function (): void {
    // GK.modal.open() builds role="dialog", a name and a named close button.
    // Pages that write the same markup by hand got none of it: a screen reader
    // read the close button as "multiplication sign" and walked past the dialog.
    // The SSI Panel alone has 87 such overlays and 56 unnamed close buttons.
    $js = (string) file_get_contents(__DIR__ . '/../js/gridkit.js');
    T::ok((bool) preg_match('/\n      upgradeStatic\(root\) \{(.*?)\n      \},/s', $js, $m), 'GK.modal.upgradeStatic was not found');
    $fn = $m[1] ?? '';
    T::contains($fn, '_t("close")', 'close buttons are not named');
    T::contains($fn, '"role", "dialog"', 'the dialog role is not set');
    T::contains($fn, 'aria-labelledby', 'the dialog is not named by its heading');
    // Static modals are shown with style.display and never receive the focus;
    // aria-modal would declare the rest of the page out of bounds while the
    // focus is still in it, and VoiceOver users get stuck.
    T::notContains($fn, 'aria-modal', 'aria-modal on a dialog that does not take the focus');
    // Content arrives three ways: page load and AJAX navigation go through
    // GK.initContent, a live reload has its own call.
    T::ok((bool) preg_match('/GK\.initContent = function \(root\) \{.*?GK\.modal\.upgradeStatic\(root\);.*?\n  \};/s', $js), 'initContent does not upgrade static modals');
    T::contains($js, 'GK.modal.upgradeStatic(e.target || document);', 'a live reload does not upgrade static modals');
},

'AJAX navigation brings the page\'s own styles along' => function (): void {
    // It swapped the content and the title. A page linking a stylesheet of its
    // own in <head> arrived unstyled when reached through the sidebar — and fine
    // after a reload. ci/browser.js shows the behaviour; this guards the wiring.
    $js = (string) file_get_contents(__DIR__ . '/../js/gridkit.js');
    T::ok((bool) preg_match('/\n    _syncHead: function \(html, done\) \{(.*?)\n    \},\n/s', $js, $m), 'GK.navigate._syncHead was not found');
    T::contains($m[1] ?? '', 'link[rel~="stylesheet"][href], style', 'head stylesheets and style blocks are not collected');
    T::contains($m[1] ?? '', 'data-gk-nav-asset', 'added styles are not marked, so they can never be removed again');
    T::contains($m[1] ?? '', 'setTimeout(go', 'a stylesheet that never loads would block navigation for good');
    // _render has to stay synchronous: the SSI Panel wraps it to re-initialise
    // its own widgets right after the swap.
    T::ok((bool) preg_match('/self\._syncHead\(html, function \(cleanUp\) \{\s*self\._render\(html, url, content, pushState\);/', $js),
        'the styles are not synced before the content is rendered');
},

'AJAX navigation binds every widget, not just tables and tooltips' => function (): void {
    // Reached through the sidebar, a searchable select did not open, an AJAX
    // form posted natively, the client-side pager was missing — until a reload.
    $js = (string) file_get_contents(__DIR__ . '/../js/gridkit.js');
    T::ok((bool) preg_match('/\n  GK\.initContent = function \(root\) \{(.*?)\n  \};/s', $js, $m), 'GK.initContent was not found');
    foreach (['selectSearch', 'multiSelect', 'ajaxSelect', 'liveTable', 'tabs', 'rowPager', 'accordion', 'form.bind', 'upgradeStatic'] as $w) {
        T::contains($m[1] ?? '', $w, "initContent does not bind $w");
    }
    T::contains($js, "if (typeof GK.initContent === 'function') GK.initContent(content);", 'navigation does not call initContent');
    T::contains($js, "new CustomEvent('gk-ajax-nav'", 'the skill promised a gk-ajax-nav event that nothing fired');
    // GK.init and the modal body go through the same list — one list, or they drift.
    T::contains($js, 'GK.initContent(document);', 'GK.init keeps a list of its own');
},

];
