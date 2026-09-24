<?php
/**
 * What is new, and since when — and the announcement that is heard. (1.93.0)
 *
 * The demo marks every block "since 1.0" and, for the last two minors, "New in
 * 1.92" or "Changed in 1.92". Those marks are only as true as css/blocks.json,
 * the index they are computed from; so the index is checked against the
 * stylesheets in both directions. A class that lands in gridkit.css without an
 * entry fails here — the one moment somebody is sure to be looking.
 *
 * The box at the top of the demo is the changelog's own headings. The check
 * for VERSION is the one the demo depends on: the newest heading the box reads
 * must be the version the page says it is.
 */

declare(strict_types=1);

use GridKit\Lang;

require_once __DIR__ . '/../demo/_whatsnew.php';

/** @return array<string,callable> */
return (static function (): array {

$root = __DIR__ . '/..';
$version = static fn (): string => trim((string) file_get_contents($root . '/VERSION'));
$css = static fn (): string => (string) file_get_contents($root . '/css/gridkit.css');
$js  = static fn (): string => (string) file_get_contents($root . '/js/gridkit.js');
$doc = static fn (): string => (string) file_get_contents($root . '/GRIDKIT_SKILL.md');
$demo = static fn (): string => (string) file_get_contents($root . '/demo/index.php');

/** Every gk- class a selector of both stylesheets names, comments stripped. @return list<string> */
$sheetClasses = static function () use ($root): array {
    $all = '';
    foreach (['gridkit.css', 'themes.css'] as $f) {
        $all .= preg_replace('~/\*.*?\*/~s', '', (string) file_get_contents("$root/css/$f"));
    }
    preg_match_all('/\.(gk-[a-z0-9-]+)/', $all, $m);
    $u = array_values(array_unique($m[1]));
    sort($u);
    return $u;
};

/** class => [block, version], read raw — a class listed twice shows up in $dupes. */
$index = static function (?array &$dupes = null) use ($root): array {
    $json = json_decode((string) file_get_contents("$root/css/blocks.json"), true);
    $out = [];
    $dupes = [];
    foreach (($json['blocks'] ?? []) as $key => $b) {
        foreach (($b['classes'] ?? []) as $class => $v) {
            if (isset($out[$class])) $dupes[] = "$class ($out[$class][0] and $key)";
            $out[$class] = [$key, $v];
        }
    }
    return $out;
};

/** The declarations of a top-level rule whose selector list is exactly $selector. */
$rule = static function (string $css, string $selector): string {
    return preg_match('/(?:^|\n)' . preg_quote($selector, '/') . '\s*\{([^}]*)\}/', $css, $m) ? $m[1] : '';
};

return [

// ── The index ─────────────────────────────────────────────────────────────

'every class in a stylesheet is in the block index' => function () use ($sheetClasses, $index, $version): void {
    $known = $index();
    $classes = $sheetClasses();
    T::ok(count($classes) > 600, 'found only ' . count($classes) . ' classes — the stylesheets were not read');
    $missing = array_values(array_filter($classes, static fn (string $c): bool => !isset($known[$c])));
    T::eq($missing, [], 'in the stylesheet, not in css/blocks.json — add each under its block with the version that brings it ('
        . $version() . ')');
},

'every class in the index is in a stylesheet, and in one block only' => function () use ($sheetClasses, $index): void {
    $dupes = [];
    $known = $index($dupes);
    $stale = array_values(array_diff(array_keys($known), $sheetClasses()));
    T::eq($stale, [], 'in css/blocks.json but in no stylesheet — a removed class leaves the index too');
    T::eq($dupes, [], 'a class in two blocks');
},

'every version in the index is a release, none newer than VERSION' => function () use ($root, $index, $version): void {
    $v = $version();
    foreach ($index() as $class => [$block, $since]) {
        T::ok((bool) preg_match('/^\d+\.\d+\.\d+$/', (string) $since), "$class: \"$since\" is not a version");
        T::ok(version_compare((string) $since, $v, '<='), "$class is dated $since, after VERSION $v");
    }
    $logged = array_column(gkChangelog(), 'version');
    foreach (gkBlocks() as $key => $b) {
        if ($b['changed'] === null) continue;
        T::ok(version_compare($b['changed'], $b['since'], '>='), "$key changed ($b[changed]) before it existed ($b[since])");
        T::ok(version_compare($b['changed'], $v, '<='), "$key changed in $b[changed], after VERSION $v");
        T::ok(in_array($b['changed'], $logged, true), "$key changed in $b[changed] — a release the changelog has no heading for");
    }
    // Each block has a name the demo can print, and a class.
    $json = json_decode((string) file_get_contents("$root/css/blocks.json"), true);
    foreach ($json['blocks'] as $key => $b) {
        T::ok(trim((string) ($b['name'] ?? '')) !== '', "block $key has no name");
        T::ok(!empty($b['classes']), "block $key has no class");
    }
},

'a class new in this version is named in this version\'s changelog entry' => function () use ($root, $index, $version): void {
    $v = $version();
    preg_match('/^## \[' . preg_quote($v, '/') . '\][^\n]*\n(.*?)(?=^## |\z)/ms', (string) file_get_contents("$root/CHANGELOG.md"), $m);
    $entry = $m[1] ?? '';
    foreach ($index() as $class => [, $since]) {
        if ($since !== $v) continue;
        T::contains($entry, $class, "$class is new in $v and its changelog entry never mentions it");
    }
},

'VERSION has its changelog heading, and it is the first the demo reads' => function () use ($version): void {
    $entries = gkChangelog();
    T::ok($entries !== [], 'the demo read no entry out of CHANGELOG.md');
    T::eq($entries[0]['version'] ?? '', $version(), 'VERSION has no changelog heading "## [x.y.z] - date" at the top');
    T::ok((bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $entries[0]['date'] ?? ''), 'the newest heading carries no date');
    T::ok(($entries[0]['headings'] ?? []) !== [], 'the newest entry has no "###" headings — the news box would show nothing');
},

// ── The marks and the news box ────────────────────────────────────────────

'the marks are GridKit\'s own green and blue labels, in both modes' => function () use ($css, $rule): void {
    Lang::set('en');
    $new = since('announce');
    T::contains($new, 'gk-label gk-label-green', 'a new block is not marked with the green label');
    T::contains($new, 'New in 1.93', 'the mark does not say what is new');
    T::contains($new, 'since 1.93', 'the heading does not say since when');

    $changed = since('message');
    T::contains($changed, 'gk-label gk-label-blue', 'a changed block is not marked with the blue label');
    T::contains($changed, 'Changed in 1.92', 'the mark does not say when it changed');

    $old = since('toast');
    T::notContains($old, 'gk-label', 'a block unchanged for years carries a mark');
    T::contains($old, 'since 0.9', 'an old block does not say since when');

    // No new colour: both labels are rules GridKit already had, dark ones too.
    $c = $css();
    foreach (['green', 'blue'] as $tone) {
        T::contains($rule($c, ".gk-label-$tone"), 'color:', "gk-label-$tone has no colour of its own");
        T::ok(str_contains($c, "[data-gk-mode=\"dark\"] .gk-label-$tone"), "gk-label-$tone has no dark rule");
    }

    Lang::set('de');
    T::contains(since('announce'), 'Neu in 1.93', 'a German demo marks in English');
    T::contains(since('message'), 'Geändert in 1.92', 'a German demo marks in English');
    Lang::set('en');
},

'every mark in the demo names a block the index knows, and every new block is marked' => function () use ($demo): void {
    $src = $demo();
    // since('row-link', 'sheet') names two blocks in one call.
    preg_match_all("/since\\(((?:'[a-z0-9-]+'(?:,\\s*)?)+)\\)/", $src, $calls);
    preg_match_all("/'([a-z0-9-]+)'/", implode(' ', $calls[1]), $m);
    $used = array_values(array_unique($m[1]));
    T::ok(count($used) > 30, 'the demo marks only ' . count($used) . ' headings');
    $blocks = gkBlocks();
    foreach ($used as $key) {
        T::ok(isset($blocks[$key]), "the demo marks \"$key\", a block css/blocks.json does not have");
    }
    // A block new in the last two minors that the demo shows nowhere is news
    // nobody sees.
    $recent = gkRecentMinors(gkChangelog());
    foreach ($blocks as $key => $b) {
        if (gkBlockState($b, $recent)['new'] === null) continue;
        T::ok(in_array($key, $used, true), "$key is new in " . gkMinor($b['since']) . ' and no heading in the demo carries its mark');
    }
    $threw = false;
    try { since('no-such-block'); } catch (InvalidArgumentException $e) { $threw = true; }
    T::ok($threw, 'a mark for a block the index does not know renders instead of failing');
},

'a heading showing two blocks says "since" once, and each mark once' => function () use ($demo): void {
    Lang::set('en');
    // Two calls side by side read "since 1.91 since 1.91 Changed in 1.92" — to
    // the eye and to a screen reader — and the second wrapped, indented, on a
    // phone. One call per heading, the keys in it.
    T::ok(!preg_match('/since\\([^)]*\\)\\s*\\?>\\s*<\\?=\\s*since\\(/', $demo()),
        'a heading in the demo carries two since() side by side — name both blocks in one call');

    $both = since('row-link', 'sheet');
    T::eq(substr_count($both, '>since '), 1, 'two blocks in one heading say "since" twice');
    T::eq(substr_count($both, 'class="demo-since"'), 1, 'two blocks in one heading give two marks');
    // "since" is the older block's: the region (1.93) beside the message (1.0).
    $mixed = since('announce', 'message');
    T::contains($mixed, 'since ' . gkMinor(gkBlocks()['message']['since']) . '<', 'the heading does not say since when the older block exists');
    T::contains($mixed, 'New in 1.93', 'the new block of the two is not marked');
    // Two blocks changed in the same release: the mark once.
    T::eq(substr_count(since('table', 'sheet'), 'Changed in 1.92'), 1, '"Changed in 1.92" twice in one heading');
    // One new in 1.92 beside one changed in 1.92: "New in" says it all.
    $newAndChanged = since('table-list', 'table');
    T::contains($newAndChanged, 'New in 1.92', 'the new block of the two is not marked');
    T::notContains($newAndChanged, 'Changed in 1.92', 'a release marked new is marked changed as well');
    T::eq(since('toast', 'toast'), since('toast'), 'a key named twice is not one block');
    $threw = false;
    try { since('toast', 'no-such-block'); } catch (InvalidArgumentException $e) { $threw = true; }
    T::ok($threw, 'an unknown second key renders instead of failing');
},

'the news box is the changelog\'s own headings for the last two minors' => function () use ($demo): void {
    Lang::set('en');
    $entries = gkChangelog();
    $recent = gkRecentMinors($entries);
    T::eq(count($recent), 2, 'the changelog names fewer than two minors');
    $box = whatsNew();
    T::contains($box, 'What is new in ' . $recent[0] . ' and ' . $recent[1], 'the box does not say which releases it covers');
    $shown = 0;
    foreach ($entries as $e) {
        if (!in_array(gkMinor($e['version']), $recent, true)) {
            T::notContains($box, '>' . $e['version'] . ' ', "the box shows $e[version], older than the last two minors");
            continue;
        }
        foreach ($e['headings'] as $h) {
            T::contains($box, gkInlineMd($h), "the box leaves out \"$h\" of $e[version]");
            $shown++;
        }
    }
    T::ok($shown > 3, "the box shows $shown headings");
    T::notContains($box, '>Tests<', 'the box lists how a release was tested as news');
    T::contains($box, 'gk-label-green', 'the box does not mark the new blocks');
    T::contains($demo(), '<?= whatsNew() ?>', 'the demo does not show the box');
},

// ── The announcement ─────────────────────────────────────────────────────

'an empty announcement has no box and stays in the accessibility tree' => function () use ($css, $rule): void {
    $r = $rule($css(), '.gk-announce:empty');
    T::ok($r !== '', 'there is no rule for an empty .gk-announce');
    foreach (['position: absolute', 'clip-path: inset(50%)', 'width: 1px', 'height: 1px', 'padding: 0', 'border: 0'] as $d) {
        T::contains($r, $d, 'the empty region is not clipped to nothing');
    }
    // Either of these removes it from the tree — the fault this block exists for.
    T::ok(!preg_match('/display:\s*none|visibility:\s*hidden|opacity:\s*0\b/', $r), 'the empty region is taken out of the accessibility tree');
    T::ok(!preg_match('/\.gk-announce[^{,]*\{[^}]*display:\s*none/', $css()), 'some rule hides .gk-announce with display: none');
},

'the announcement markup is on the page from the start, empty and never hidden' => function () use ($doc, $demo, $root): void {
    // The skill says why "empty" means no line break either, and so does the spec.
    T::contains($doc(), '**nothing between the tags**', 'the skill does not warn that a line break between the tags draws the box');
    T::contains((string) file_get_contents($root . '/SPEC.md'), 'nothing between the tags, not even a line break', 'the spec does not say a line break is not empty');
    foreach (['GRIDKIT_SKILL.md' => $doc(), 'demo/index.php' => $demo()] as $where => $src) {
        preg_match_all('/<div class="[^"]*gk-announce[^"]*"[^>]*>(.*?)<\/div>/s', $src, $m, PREG_SET_ORDER);
        T::ok($m !== [], "$where shows no .gk-announce region");
        foreach ($m as [$tag, $inner]) {
            T::contains($tag, 'role="status"', "$where: the region has no role");
            T::contains($tag, 'aria-live="polite"', "$where: the region is not live");
            T::contains($tag, 'aria-atomic="true"', "$where: the region is read in pieces");
            T::ok(!preg_match('/\shidden[\s>=]/', $tag), "$where: the region is written hidden — the pattern that stays silent");
            T::eq($inner, '', "$where: the region is not written empty (whitespace would draw a box)");
        }
    }
},

'GK.announce changes the words only, and a repeat is heard' => function () use ($js): void {
    $src = $js();
    T::ok((bool) preg_match('/GK\.announce = function \(target, text, tone\) \{(.*?)\n  \};/s', $src, $m), 'GK.announce(target, text, tone) is missing');
    $body = $m[1] ?? '';
    T::contains($body, 'el.textContent = words', 'the words are not set as text');
    T::notContains($body, 'innerHTML', 'the words go through the HTML parser');
    T::ok((bool) preg_match('/if \(el\.textContent === words\) \{\s*el\.textContent = "";\s*el\._gkAnnounceTimer = setTimeout/', $body),
        'the same words twice are not emptied and written back — a screen reader hears no change');
    T::contains($body, '_gkAnnounceRegion(el, true)', 'a hidden region is not taken out of hiding before it speaks');
    T::contains($src, 'if (unhide && el.hidden) el.hidden = false;', 'the region helper does not unhide');
    T::contains($src, 'GK.melde = GK.announce;', 'GK.melde is not the same function');
    T::ok((bool) preg_match('/GK\.initContent = function \(root\) \{.*?_gkAnnounceInit\(root\);\s*\};/s', $src),
        'init does not prepare the regions before the first message');
    // `:empty` matches no child at all: a line break between the tags, as a
    // template writes it, drew the empty box. Init empties white space.
    T::ok((bool) preg_match('/function _gkAnnounceInit\(root\) \{.*?var blank = !el\.textContent\.trim\(\);\s*if \(blank && el\.firstChild\) el\.textContent = "";.*?_gkAnnounceRegion\(el, blank\);/s', $src),
        'init leaves a region holding only white space as it is — a line break draws the empty box');
},

];

})();
