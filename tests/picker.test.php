<?php
/**
 * The image picker (1.94.0): a compact grid of tiles that works like a radio
 * group, with an enlarged view (the lightbox) that can pick.
 *
 * Why it exists: an application that lets a person choose one image out of
 * their own stock — a header picture, a portrait — drew each image as a full
 * row (preview left, title, a loose button right). With ten images the choice
 * filled the screen, with thirty it was unusable, and nobody could look at a
 * picture in detail before choosing it. The SSI panel built that list by hand
 * twice. This is the component it should have been.
 *
 * What these tests hold (the behaviour in a browser is ci/browser-picker.js —
 * this suite executes no JavaScript):
 *   - the markup: one native radio per image, named by its visible title, so
 *     the form, the arrow keys and the screen reader work without a line of
 *     script;
 *   - the caller's words never reach the page unescaped;
 *   - an image without a large version has no zoom button and no viewer entry;
 *   - the stylesheet keeps the selected state out of colour alone, keeps the
 *     focus ring, and sizes the buttons for a finger;
 *   - the script's contract: events, the roving tab stop, folding, the viewer.
 */

declare(strict_types=1);

use GridKit\{ImagePicker, Icon, Lang};

const PK_XSS = '<script>alert(1)</script>';
const PK_BREAK = '" onmouseover="alert(1)';

/** @return array{0:\DOMDocument,1:\DOMXPath} */
function pkDom(string $html): array
{
    $dom = new \DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8"?><body>' . $html . '</body>');
    libxml_clear_errors();
    return [$dom, new \DOMXPath($dom)];
}

/** The picker's own part of the stylesheet: from its marker to the next section. */
function pkBlock(string $css): string
{
    $von = strpos($css, '/* === IMAGE PICKER');
    T::ok($von !== false, 'the stylesheet has the picker block');
    $block = substr($css, (int) $von);
    $bis = strpos($block, '/* ── Phones: the type in a field');
    return $bis === false ? $block : substr($block, 0, $bis);
}

/** @return list<array<string,mixed>> */
function pkItems(): array
{
    return [
        ['value' => 'a.webp', 'title' => 'Fireplace at dusk', 'thumb' => '/t/a.webp', 'full' => '/g/a.webp', 'meta' => 'Own image', 'checked' => true],
        ['value' => 'b.webp', 'title' => 'Mountain road', 'thumb' => '/t/b.webp', 'full' => '/g/b.webp', 'meta' => 'AI generated', 'badge' => 'AI',
         'actions' => [['name' => 'remove', 'label' => 'Remove']]],
        ['value' => 'c.webp', 'title' => 'Not released yet', 'note' => 'Waiting for approval', 'disabled' => true],
    ];
}

return [

'a picker is one native radio per image, all in one group' => function (): void {
    Lang::set('en');
    [$dom, $x] = pkDom(ImagePicker::html(pkItems(), ['name' => 'cover', 'id' => 'pk']));

    $radios = $x->query('//input[@type="radio"]');
    T::eq($radios->length, 3, 'one radio per image');
    foreach ($radios as $r) {
        T::eq($r->getAttribute('name'), 'cover', 'every radio is in the one group');
        T::contains($r->getAttribute('class'), 'gk-picker-input', 'and wears the class the stylesheet draws');
    }
    T::eq($x->query('//div[@data-gk-picker]')->length, 1, 'one container the script finds');
    T::eq($x->query('//div[contains(@class,"gk-picker-item")]')->length, 3, 'one tile per image');
    T::eq($x->query('//input[@checked]')->length, 1, 'only the checked one is checked');
    T::eq($x->query('//input[@disabled]')->length, 1, 'and only the disabled one is disabled');
    T::eq($x->query('//input[@disabled]')->item(0)->getAttribute('value'), 'c.webp', 'the right one');
},

'a radio is named by the tile title and described by what it says about the image' => function (): void {
    Lang::set('en');
    [$dom, $x] = pkDom(ImagePicker::html(pkItems(), ['name' => 'cover', 'id' => 'pk']));

    $r = $x->query('//input[@value="b.webp"]')->item(0);
    $titelId = $r->getAttribute('aria-labelledby');
    T::ok($titelId !== '', 'the radio points at its title');
    $titel = $dom->getElementById($titelId);
    T::ok($titel !== null && $titel->nodeName === 'label', 'the title is a label …');
    T::eq($titel?->getAttribute('for'), $r->getAttribute('id'), '… for the radio, so a click on the words picks too');
    T::eq(trim((string) $titel?->textContent), 'Mountain road', 'and it says the title');
    T::eq($titel?->getAttribute('title'), 'Mountain road', 'the whole title is the tooltip, however far the tile cuts it');

    $beschreibung = $dom->getElementById($r->getAttribute('aria-describedby'));
    T::eq(trim((string) $beschreibung?->textContent), 'AI generated', 'the origin is the description, not part of the name');
    T::contains((string) $beschreibung?->getAttribute('class'), 'gk-sr-only', 'read by a screen reader, not drawn twice');

    $img = $x->query('//input[@value="b.webp"]/ancestor::div[contains(@class,"gk-picker-item")]//img')->item(0);
    T::eq($img?->getAttribute('alt'), '', 'the picture is decoration: the title already names it, an alt would say it twice');
    T::eq($img?->getAttribute('src'), '/t/b.webp', 'the small version fills the tile');
    T::eq($img?->getAttribute('loading'), 'lazy', 'a tile out of sight does not load');
    T::eq($img?->getAttribute('decoding'), 'async', 'and decoding does not block the page');
},

'the origin chip, the note and the disabled state are drawn, not just named' => function (): void {
    Lang::set('en');
    [, $x] = pkDom(ImagePicker::html(pkItems(), ['name' => 'cover', 'id' => 'pk']));

    $chip = $x->query('//span[contains(@class,"gk-picker-badge")]');
    T::eq($chip->length, 1, 'one chip, on the image that asked for one');
    T::eq(trim((string) $chip->item(0)->textContent), 'AI', 'with the short word');
    T::eq($chip->item(0)->getAttribute('aria-hidden'), 'true', 'hidden from a reader: the description says it in full');

    $note = $x->query('//*[contains(@class,"gk-picker-note")]');
    T::eq($note->length, 1, 'one note');
    T::eq(trim((string) $note->item(0)->textContent), 'Waiting for approval', 'under the title of the tile it belongs to');

    T::eq($x->query('//span[contains(@class,"gk-picker-check")]')->length, 3,
        'every tile carries the check mark — whether it shows is the stylesheet\'s :checked, so the state never rests on colour alone');
    T::eq($x->query('//span[contains(@class,"gk-picker-check")]')->item(0)->getAttribute('aria-hidden'), 'true', 'decoration');
},

'only an image with a large version can be enlarged' => function (): void {
    Lang::set('en');
    [, $x] = pkDom(ImagePicker::html(pkItems(), ['name' => 'cover', 'id' => 'pk']));

    $mitGross = $x->query('//div[contains(@class,"gk-picker-item")][@data-lightbox]');
    T::eq($mitGross->length, 2, 'two tiles have a large version');
    T::eq($mitGross->item(0)->getAttribute('data-lightbox'), '/g/a.webp', 'which is what the viewer loads');
    T::eq($mitGross->item(0)->getAttribute('data-caption'), 'Fireplace at dusk', 'with the title as caption');
    T::eq($mitGross->item(0)->getAttribute('data-meta'), 'Own image', 'and the origin under it');
    T::eq($x->query('//button[@data-gk-picker-zoom]')->length, 2, 'and only those have a zoom button');

    $zoom = $x->query('//button[@data-gk-picker-zoom]')->item(1);
    T::eq($zoom->getAttribute('type'), 'button', 'it never submits a form');
    T::eq($zoom->getAttribute('aria-label'), 'Enlarge: Mountain road', 'it says what it enlarges');
    T::ok($zoom->getElementsByTagName('svg')->length === 1, 'the icon is an inline SVG, so a missing icon font leaves no ligature word on the tile');
    T::ok($zoom->parentNode !== null && !str_contains((string) $zoom->parentNode->getAttribute('class'), 'gk-picker-caption'),
        'the zoom button sits on the picture, not next to the title');

    $ohne = $x->query('//input[@value="c.webp"]/ancestor::div[contains(@class,"gk-picker-item")]')->item(0);
    T::eq($ohne->hasAttribute('data-lightbox'), false, 'no large version: nothing for the viewer');
    T::eq($x->query('.//*[contains(@class,"gk-picker-ph")]', $ohne)->length, 1, 'the tile shows a placeholder instead of a picture');
    T::eq($x->query('.//img', $ohne)->length, 0, 'and loads no image');
},

'an action is a button the caller names, in the caption row, and says which tile it is for' => function (): void {
    Lang::set('en');
    [, $x] = pkDom(ImagePicker::html(pkItems(), ['name' => 'cover', 'id' => 'pk']));

    $a = $x->query('//button[@data-gk-picker-action]');
    T::eq($a->length, 1, 'one action was given');
    T::eq($a->item(0)->getAttribute('data-gk-picker-action'), 'remove', 'its name travels in the event');
    T::eq($a->item(0)->getAttribute('type'), 'button', 'it never submits a form');
    T::eq($a->item(0)->getAttribute('aria-label'), 'Remove: Mountain road', 'the label and the title: which image it acts on');
    T::eq($a->item(0)->getAttribute('title'), 'Remove', 'and the tooltip is the bare label');
    T::contains((string) $a->item(0)->parentNode->getAttribute('class'), 'gk-picker-caption',
        'it sits in the caption row, away from the picture a tap selects — an action that deletes is not at the thumb');
    T::ok($a->item(0)->getElementsByTagName('svg')->length === 1, 'with an inline icon');
},

'what the caller supplies is escaped everywhere it lands' => function (): void {
    Lang::set('en');
    $html = ImagePicker::html([[
        'value' => PK_BREAK, 'title' => PK_XSS, 'thumb' => '/t/"' . PK_BREAK, 'full' => '/g/' . PK_BREAK, 'meta' => PK_XSS, 'note' => PK_XSS,
        'badge' => PK_XSS, 'alt' => PK_XSS, 'data' => ['x' => PK_BREAK],
        'actions' => [['name' => PK_BREAK, 'label' => PK_XSS]],
    ]], ['name' => PK_BREAK, 'id' => PK_BREAK, 'label' => PK_XSS, 'class' => PK_BREAK,
         'labels' => ['zoom' => PK_XSS, 'choose' => PK_XSS, 'more' => PK_XSS, 'less' => PK_XSS, 'selected' => PK_XSS, 'none' => PK_XSS]]);

    T::notContains($html, '<script>alert(1)</script>', 'a script tag survived');
    T::notContains($html, ' onmouseover="alert(1)', 'an attribute break survived');
    T::contains($html, '&lt;script&gt;', 'the payload is there, as text');
    T::eq(preg_match('/\sdata-[a-z0-9-]*[^a-z0-9="\s-][^=\s]*=/', $html), 0, 'a data attribute name is cut to letters, digits and dashes');
},

'the markup has no inline style, no inline handler and no script' => function (): void {
    Lang::set('en');
    $html = ImagePicker::html(pkItems(), ['name' => 'cover', 'id' => 'pk']);
    T::notContains($html, 'style=', 'design lives in the stylesheet');
    T::notContains($html, 'onclick', 'behaviour lives in the script');
    T::notContains($html, '<script', 'and the component writes none');
},

'rows and the words reach the browser as data attributes, and rows 0 never folds' => function (): void {
    Lang::set('de');
    [, $x] = pkDom(ImagePicker::html(pkItems(), ['name' => 'cover', 'id' => 'pk', 'rows' => 3,
        'labels' => ['more' => 'Alle {n} Bilder zeigen', 'less' => 'Weniger Bilder', 'choose' => 'Dieses Bild wählen', 'none' => 'Kein Bild gewählt']]));
    $c = $x->query('//div[@data-gk-picker]')->item(0);
    T::eq($c->getAttribute('data-gk-picker-rows'), '3', 'the rows to show before "show all"');
    T::eq($c->getAttribute('data-gk-picker-label-more'), 'Alle {n} Bilder zeigen', 'the words of the fold button, with {n} for the count');
    T::eq($c->getAttribute('data-gk-picker-label-less'), 'Weniger Bilder', 'and of the way back');
    T::eq($c->getAttribute('data-gk-picker-label-choose'), 'Dieses Bild wählen', 'the viewer button');
    T::eq($c->getAttribute('data-gk-picker-label-none'), 'Kein Bild gewählt', 'what the status line says with nothing chosen');
    T::eq($c->getAttribute('data-gk-picker-name'), 'cover', 'the group name, for a tile the script adds later');
    T::eq($c->getAttribute('data-gk-picker-id'), 'pk', 'and the id prefix');

    [, $x] = pkDom(ImagePicker::html(pkItems(), ['name' => 'cover', 'id' => 'pk', 'rows' => 0]));
    T::eq($x->query('//div[@data-gk-picker]')->item(0)->getAttribute('data-gk-picker-rows'), '0', 'rows 0 means: never fold');

    [, $x] = pkDom(ImagePicker::html(pkItems(), ['name' => 'cover', 'id' => 'pk']));
    T::eq($x->query('//div[@data-gk-picker]')->item(0)->getAttribute('data-gk-picker-rows'), '2', 'two rows by default');
},

'the words default to the active language' => function (): void {
    Lang::set('de');
    [, $x] = pkDom(ImagePicker::html(pkItems(), ['name' => 'cover', 'id' => 'pk']));
    T::eq($x->query('//button[@data-gk-picker-zoom]')->item(0)->getAttribute('aria-label'), 'Vergrößern: Fireplace at dusk', 'German');
    Lang::set('en');
    [, $x] = pkDom(ImagePicker::html(pkItems(), ['name' => 'cover', 'id' => 'pk']));
    T::eq($x->query('//button[@data-gk-picker-zoom]')->item(0)->getAttribute('aria-label'), 'Enlarge: Fireplace at dusk', 'English');
},

'the status line says what is chosen, and what it is when nothing is' => function (): void {
    Lang::set('en');
    [, $x] = pkDom(ImagePicker::html(pkItems(), ['name' => 'cover', 'id' => 'pk']));
    $s = $x->query('//p[@data-gk-picker-status]');
    T::eq($s->length, 1, 'one status line');
    T::eq(trim((string) $s->item(0)->textContent), 'Selected: Fireplace at dusk', 'it names the checked image');

    [, $x] = pkDom(ImagePicker::html([['value' => 'x', 'title' => 'X', 'thumb' => '/t/x']], ['name' => 'cover', 'id' => 'pk']));
    T::eq(trim((string) $x->query('//p[@data-gk-picker-status]')->item(0)->textContent), 'Nothing selected', 'with nothing checked');

    [, $x] = pkDom(ImagePicker::html(pkItems(), ['name' => 'cover', 'id' => 'pk', 'status' => false]));
    T::eq($x->query('//p[@data-gk-picker-status]')->length, 0, 'and the caller can leave it out');
},

'the group can be named, and an empty list still renders the shell the script fills' => function (): void {
    Lang::set('en');
    [, $x] = pkDom(ImagePicker::html(pkItems(), ['name' => 'cover', 'id' => 'pk', 'label' => 'Header image']));
    $g = $x->query('//div[contains(@class,"gk-picker-grid")]')->item(0);
    T::eq($g->getAttribute('role'), 'group', 'a group …');
    T::eq($g->getAttribute('aria-label'), 'Header image', '… with a name');

    [, $x] = pkDom(ImagePicker::html([], ['name' => 'cover', 'id' => 'pk']));
    T::eq($x->query('//div[@data-gk-picker]')->length, 1, 'no images: the container is there, so an image can be added later');
    T::eq($x->query('//div[contains(@class,"gk-picker-item")]')->length, 0, 'with no tile');
},

'ids are unique and follow the prefix' => function (): void {
    Lang::set('en');
    $html = ImagePicker::html(pkItems(), ['name' => 'cover', 'id' => 'pk']);
    preg_match_all('/\sid="([^"]+)"/', $html, $m);
    T::eq(count($m[1]), count(array_unique($m[1])), 'no id twice');
    foreach ($m[1] as $id) {
        T::ok(str_starts_with($id, 'pk'), "the id \"$id\" starts with the prefix");
    }
    $zwei = ImagePicker::html(pkItems(), ['name' => 'cover', 'id' => 'pk']) . ImagePicker::html(pkItems(), ['name' => 'person', 'id' => 'pp']);
    preg_match_all('/\sid="([^"]+)"/', $zwei, $m2);
    T::eq(count($m2[1]), count(array_unique($m2[1])), 'two pickers on one page share no id');
},

'render prints what html returns' => function (): void {
    Lang::set('en');
    T::eq(T::capture(fn() => ImagePicker::render(pkItems(), ['name' => 'cover', 'id' => 'pk'])), ImagePicker::html(pkItems(), ['name' => 'cover', 'id' => 'pk']),
        'the same markup either way — the library\'s usual pair');
},

'one tile is the unit: tileHtml is what html is made of' => function (): void {
    Lang::set('en');
    $opts = ['name' => 'cover', 'id' => 'pk'];
    $eins = ImagePicker::tileHtml(pkItems()[1], $opts + ['index' => 2]);
    T::contains(ImagePicker::html(pkItems(), $opts), $eins, 'the tile html builds the picker');
    $geworfen = false;
    try {
        ImagePicker::html(pkItems(), []);
    } catch (\InvalidArgumentException) {
        $geworfen = true;
    }
    T::ok($geworfen, 'a picker without a group name has nothing to group by: it refuses, rather than render radios nobody can submit');
},

'two icons the picker needs are in the icon set' => function (): void {
    foreach (['zoom_in', 'image'] as $name) {
        T::ok(Icon::svg($name, 16, false) !== '', "$name is an inline SVG — the parity test in js.test.php holds the client's copy to it");
    }
},

'the picker brings its words in both languages, and the browser gets them' => function (): void {
    $de = require __DIR__ . '/../lang/de.php';
    $en = require __DIR__ . '/../lang/en.php';
    foreach (['picker.zoom', 'picker.more', 'picker.less', 'picker.selected', 'picker.none', 'js.lightbox_choose', 'js.lightbox_chosen'] as $k) {
        T::ok(isset($de[$k]) && $de[$k] !== '', "German has $k");
        T::ok(isset($en[$k]) && $en[$k] !== '', "English has $k");
    }
    T::contains($de['picker.more'], '{n}', 'the fold button carries the count');
    Lang::set('en');
    $js = Lang::jsConfig();
    foreach (['picker_zoom', 'picker_more', 'picker_less', 'picker_selected', 'picker_none', 'lightbox_choose', 'lightbox_chosen'] as $k) {
        T::contains($js, '"' . $k . '"', "the browser gets $k");
    }
},

'the stylesheet keeps the selected state out of colour alone, the focus ring, and a finger-sized target' => function (): void {
    $css = (string) file_get_contents(__DIR__ . '/../css/gridkit.css');
    T::ok((bool) preg_match('/\.gk-picker-input:checked\s*~\s*\.gk-picker-check\s*\{[^}]*display:\s*(flex|grid|inline-flex|block)/', $css),
        'the check mark appears when the radio is checked — a shape, not only a colour');
    T::ok((bool) preg_match('/\.gk-picker-input:checked\s*\{[^}]*box-shadow:\s*inset 0 0 0 3px/', $css), 'and the ring thickens (3px against 1px)');
    T::ok((bool) preg_match('/\.gk-picker-input:focus-visible\s*\{[^}]*outline:\s*3px solid var\(--gk-primary\)/', $css), 'the keyboard focus ring is 3px in the primary colour');
    T::ok((bool) preg_match('/:where\(\[class\^="gk-"\][^{]*\[hidden\]\s*\{[^}]*display:\s*none\s*!important/', $css), 'a folded tile is really gone: the library-wide [hidden] rule covers every gk- class, so the picker carries no guard of its own');
    T::notContains(pkBlock($css), '[hidden]', 'and none is written in the picker block (one rule for every class that starts with gk-)');
    T::ok((bool) preg_match('/\.gk-picker-input:disabled/', $css), 'a disabled tile looks it');
    T::ok((bool) preg_match('/\.gk-picker-zoom\s*\{[^}]*(width|min-width):\s*var\(--gk-target-min\)/s', $css), 'the zoom button is as big as a finger (--gk-target-min)');
    T::ok((bool) preg_match('/@media \(pointer: coarse\)\s*\{[^@]*\.gk-picker-action/s', $css), 'and the caption action grows to a finger on a touch screen');
    T::ok((bool) preg_match('/\.gk-picker-grid\s*\{[^}]*grid-template-columns:\s*repeat\(auto-fill,\s*minmax\(/s', $css), 'the grid fills the row with as many tiles as fit');
    T::ok((bool) preg_match('/\.gk-picker-media\s*\{[^}]*aspect-ratio:\s*16\s*\/\s*9/s', $css), 'every tile is 16:9, so a row never jumps with the picture');
    T::notContains((string) preg_replace('~/\*.*?\*/~s', '', pkBlock($css)), '!important',
        'no !important in the picker — an application must be able to override a tile with one class');
},

'the stylesheet leaves the gallery\'s own tile rules alone and the picker does not use them' => function (): void {
    $html = ImagePicker::html(pkItems(), ['name' => 'cover', 'id' => 'pk']);
    T::notContains($html, 'gk-gallery-item', 'a picker tile is not a gallery tile: the gallery opens its viewer on any click, the picker picks');
},

'the picker respects reduced motion' => function (): void {
    $css = (string) file_get_contents(__DIR__ . '/../css/gridkit.css');
    $block = pkBlock($css);
    if (preg_match_all('/transition:/', $block)) {
        T::ok((bool) preg_match('/@media \(prefers-reduced-motion: reduce\)\s*\{[^@]*\.gk-picker/s', $css),
            'a picker that animates stops when the person asked for no motion');
    } else {
        T::ok(true, 'no motion to stop');
    }
},

'the script: events, folding, a roving tab stop, and the viewer' => function (): void {
    $js = (string) file_get_contents(__DIR__ . '/../js/gridkit.js');

    T::contains($js, 'GK.picker = {', 'the picker has a public surface');
    foreach (['init:', 'refresh:', 'add:', 'remove:', 'tileHtml:'] as $m) {
        T::contains($js, $m, "GK.picker.$m");
    }
    T::contains($js, 'new CustomEvent("gk:pickeraction"', 'an action is an event the application decides about — the library never deletes');
    T::contains($js, 'detail: { action:', 'carrying the action, the value and the tile');
    T::contains($js, 'bubbles: true', 'it bubbles to the page');
    T::contains($js, '_gkReady(function () { GK.picker.init(); })', 'it starts through the library\'s ready guard');

    // The roving tab stop: thirty tiles must not be sixty tab stops.
    T::contains($js, 'setAttribute("tabindex", "-1")', 'the buttons of a tile that does not have the focus leave the tab order');
    T::contains($js, 'focusin', 'and follow the focus');

    // Folding.
    T::contains($js, 'aria-expanded', 'the fold button says whether it is open');
    T::contains($js, 'data-gk-picker-more', 'its words come from the container');
    T::contains($js, 'gridTemplateColumns', 'rows are counted by what the browser laid out (the columns the grid resolved to), not by a guess about the width');
    T::notContains($js, 't.offsetTop', 'and without laying every tile out first — folding and unfolding read the grid, they do not show the whole list to measure it');
    T::contains($js, 'ResizeObserver', 'and counted again when the width changes');

    // The viewer.
    T::contains($js, 'GK.lightbox.show', 'the picker opens the library\'s own viewer, not a second one');
    T::contains($js, 'gk-lightbox-actions', 'the viewer has a row for what can be done with the picture');
    T::contains($js, 'gk-lightbox-choose', 'including the button that picks it');
    T::contains($js, 'gk-lightbox-meta', 'and a second line for where the picture comes from');
},

'the viewer: picking returns the focus to the radio, closing to the zoom button' => function (): void {
    $js = (string) file_get_contents(__DIR__ . '/../js/gridkit.js');
    T::contains($js, 'entry.choose', 'an entry can offer "pick this one"');
    T::contains($js, 'entry.returnTo', 'and says where the focus goes afterwards');
    T::contains($js, '_gkRestoreFocus(opener)', 'the focus comes back through the one helper every overlay uses');
    T::contains($js, 'opener = galleryItem;', 'the plain gallery still remembers its tile');
},

'documented: the guide has a section, the component table a row, the demo a page' => function (): void {
    $doc = (string) file_get_contents(__DIR__ . '/../GRIDKIT_SKILL.md');
    T::contains($doc, '### Image picker', 'a section in the guide');
    T::contains($doc, 'gk:pickeraction', 'that names the event');
    T::contains($doc, 'ImagePicker::html', 'and the PHP call');
    T::ok((bool) preg_match('/\| Image picker[^\n]*ImagePicker/', $doc), 'a row in the component table');
    $demo = (string) file_get_contents(__DIR__ . '/../demo/index.php');
    T::contains($demo, 'ImagePicker::', 'the demo shows it');
    T::contains($demo, "since('picker')", 'with its "new in" mark');
},

];
