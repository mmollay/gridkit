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
    // The window moves with every minor (the last two in the changelog: 1.104 and 1.103). No block is new in
    // it — the green label is tested through the mark itself; the line "you are here" (new in 1.100) fell out.
    // The tabs got two classes in 1.104 (flat, one row; thirteen in 1.103), so they carry the blue label;
    // the steps changed in 1.101 and are out of the window.
    // The header, the sidebar and the breadcrumb changed in 1.100, the message and the timeline in 1.99.
    $new = gkMark('new', '1.100');
    T::contains($new, 'gk-label gk-label-green', 'a new block is not marked with the green label');
    T::contains($new, 'New in 1.100', 'the mark does not say what is new');
    T::contains(since('ortszeile'), 'since 1.100', 'the heading does not say since when');
    T::notContains(since('ortszeile'), 'gk-label', 'the line "you are here", new in 1.100, is out of the window and still marked');
    T::notContains(since('steps'), 'gk-label', 'the steps, changed in 1.101, are out of the window and still marked');
    T::contains(since('tabs'), 'Changed in 1.104', 'a block that got a new class in the newest minor is not marked as changed');
    $changed = gkMark('changed', '1.95');
    T::notContains(since('header'), 'gk-label', 'the header, changed in 1.100, is out of the window and still marked');
    T::contains($changed, 'gk-label gk-label-blue', 'a changed block is not marked with the blue label');
    T::contains($changed, 'Changed in 1.95', 'the mark does not say when it changed');
    T::notContains(since('message'), 'gk-label', 'the message, changed in 1.99, is out of the window and still marked');
    T::notContains(since('timeline'), 'New in 1.96', 'the timeline, new in 1.96, is out of the window and still marked new');
    T::notContains(since('timeline'), 'gk-label', 'the timeline, changed in 1.99, is out of the window and still marked');

    // A block that fell out of the window (the line "you are here" is new in 1.100, the sortable container in 1.97,
    // the window is 1.103 and 1.102; the picker and the announcement are older still) carries no mark.
    foreach (['ortszeile' => '1.100', 'sortierbar' => '1.97', 'picker' => '1.94', 'announce' => '1.93'] as $key => $minor) {
        T::notContains(since($key), 'gk-label', "a block older than the last two minors carries a mark ($key)");
        T::contains(since($key), "since $minor", "a block that fell out of the window does not say since when ($key)");
    }

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
    T::contains(gkMark('new', '1.100'), 'Neu in 1.100', 'a German demo marks in English');
    T::contains(gkMark('changed', '1.95'), 'Geändert in 1.95', 'a German demo marks in English');
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
    // "since" is the older block's: the tabs (changed in 1.103) beside the message (1.0).
    $mixed = since('tabs', 'message');
    T::contains($mixed, 'since ' . gkMinor(gkBlocks()['message']['since']) . '<', 'the heading does not say since when the older block exists');
    T::contains($mixed, 'Changed in 1.104', 'the changed block of the two is not marked');
    T::eq(substr_count($mixed, 'Changed in 1.104'), 1, '"Changed in 1.103" twice in one heading');
    // Two blocks marked in two minors of the window: each mark once.
    $two = since('tabs', 'steps');
    T::eq(substr_count($two, 'Changed in 1.104'), 1, 'a block changed in the window beside one that fell out does not give one mark');
    T::notContains($two, 'Changed in 1.101', 'the steps, changed in 1.101, are out of the window and still marked');
    // The rest depends on which blocks the window marks, so the pairs are found, not named: they move with every minor.
    $window = gkRecentMinors(gkChangelog());
    $marked = ['new' => [], 'changed' => []];
    foreach (gkBlocks() as $key => $b) {
        $state = gkBlockState($b, $window);
        foreach (['new', 'changed'] as $kind) if ($state[$kind] !== null) $marked[$kind][$state[$kind]][] = $key;
    }
    // Two blocks changed in the same minor: the mark once.
    foreach ($marked['changed'] as $minor => $keys) {
        if (count($keys) < 2) continue;
        T::eq(substr_count(since(...$keys), "Changed in $minor"), 1, "two blocks changed in $minor give \"Changed in $minor\" twice in one heading");
    }
    // One new beside one changed in the SAME minor: "New in" says it all.
    foreach ($marked['new'] as $minor => $keys) {
        if (empty($marked['changed'][$minor])) continue;
        $same = since($keys[0], $marked['changed'][$minor][0]);
        T::eq(substr_count($same, "New in $minor"), 1, "\"New in $minor\" is not given once beside a block changed in the same minor");
        T::notContains($same, "Changed in $minor", 'a block changed in the minor of its new neighbour repeats the news');
    }
    $recent = ['1.100', '1.99'];
    T::eq(gkBlockState(['since' => '1.100.0', 'changed' => '1.100.0'], $recent), ['new' => '1.100', 'changed' => null], 'a block new in this minor is also marked changed in it');
    T::eq(gkBlockState(['since' => '1.0.0', 'changed' => '1.100.0'], $recent), ['new' => null, 'changed' => '1.100'], 'a block changed in this minor is not marked');
    T::eq(since('toast', 'toast'), since('toast'), 'a key named twice is not one block');
    $threw = false;
    try { since('toast', 'no-such-block'); } catch (InvalidArgumentException $e) { $threw = true; }
    T::ok($threw, 'an unknown second key renders instead of failing');
},

/*
 * The news box (1.103.1): one card per release. The parser is tested on a changelog of its own making (what it keeps,
 * what it leaves out), the box on the real entries 1.103.0 and 1.102.0 — historic, so their numbers do not move.
 */

'the changelog reader keeps what a release brings and leaves out how it was checked' => function (): void {
    $md = <<<'MD'
# Changelog

---
## [2.0.1] - 2026-01-02

Intro sentence one. A second sentence that must not show.

### Added
- **Bold lead** (details): more words
- `gk-x` — a wrapped entry
  that goes on here
```html
- not an entry, it is inside a code block
```
### Changed — a titled change
Some prose under it.
- a bullet under a titled heading is part of the title, not counted
### Fixed
- First fix: the cause
### Notes
- not news
### Tests
- 100 assertions pass

---
## [2.0.0] - 2026-01-01

### Added — the old style
The first paragraph of an old entry. More words.
MD;
    $file = tempnam(sys_get_temp_dir(), 'gk-cl-');
    file_put_contents($file, $md);
    try {
        $e = gkChangelog($file);
    } finally {
        unlink($file);
    }
    T::eq(array_column($e, 'version'), ['2.0.1', '2.0.0'], 'the entries are not read newest first');
    T::eq($e[0]['date'], '2026-01-02', 'the date is not read');
    T::eq($e[0]['intro'], 'Intro sentence one.', 'the intro is not the first sentence of the paragraph under the heading');
    T::eq(array_keys($e[0]['groups']), ['Added', 'Changed', 'Fixed'], 'Notes or Tests count as what a release brings');
    T::eq($e[0]['groups']['Added']['count'], 2, 'a "- " inside a code block, or a wrapped entry, is counted');
    T::eq($e[0]['groups']['Added']['items'], ['Bold lead', '`gk-x` — a wrapped entry that goes on here'], 'the lead of an entry is not its bold start or its first words');
    T::eq($e[0]['groups']['Changed'], ['count' => 1, 'items' => ['a titled change']], 'a titled heading is one entry, its title');
    T::eq($e[0]['groups']['Fixed']['items'], ['First fix'], 'the lead does not stop at the colon');
    T::notContains(json_encode($e[0]), '100 assertions', 'the Tests section is part of what is new');
    T::eq($e[1]['intro'], 'The first paragraph of an old entry.', 'an entry without introduction takes its first prose paragraph');
    T::eq($e[1]['groups']['Added']['items'], ['the old style'], 'the old heading "Added — title" is not read');

    // A cut never leaves half a code span or half a bold pair.
    $cut = gkShorten('A long text with `a code span that is cut in the middle` and more words after it', 40);
    T::eq(substr_count($cut, '`') % 2, 0, 'a cut text keeps half a code span');
    T::ok(str_ends_with($cut, '…') && mb_strlen($cut) <= 41, 'a cut text does not end in an ellipsis within the length');
    T::eq(gkShorten('short', 40), 'short', 'a short text is changed');
},

'the demo section of a block is read from the demo, the first heading wins' => function () use ($demo): void {
    $file = tempnam(sys_get_temp_dir(), 'gk-demo-');
    file_put_contents($file, <<<'PHP'
<style>[data-section="css-only"] .x { color: red; }</style>
<div class="demo-section active" data-section="a"><h2><?= since('x') ?></h2></div>
<div class="demo-section" data-section="b"><h2><?= since('x', 'y') ?></h2></div>
PHP);
    try {
        $map = gkBlockSections($file);
    } finally {
        unlink($file);
    }
    T::eq($map, ['x' => 'a', 'y' => 'b'], 'the section of a block is not the first one that shows it, or a CSS rule counted as a section');
    $real = gkBlockSections();
    T::eq($real['tabs'] ?? null, 'tabs', 'the tabs block is not found in the demo section "tabs"');
    foreach (array_unique($real) as $section) {
        T::contains($demo(), 'data-section="' . $section . '"', "the box would jump to a section \"$section\" the demo does not have");
    }
},

'the news box is one card per release, the newest three open and the rest folded' => function (): void {
    Lang::set('en');
    $entries = gkChangelog();
    $box = whatsNew();
    T::contains($box, 'What is new in ' . implode(', ', array_slice(array_column($entries, 'version'), 0, 2)) . ' and ' . $entries[2]['version'],
        'the summary does not say which releases the box covers');
    preg_match_all('/<article class="gk-card demo-news-card" aria-labelledby="(demo-news-v-[0-9-]+)"/', $box, $m);
    T::eq(count($m[1]), 3, 'the box does not open exactly three cards');
    foreach ([0, 1, 2] as $i) {
        T::eq($m[1][$i], 'demo-news-v-' . str_replace('.', '-', $entries[$i]['version']), "card $i is not the release number $i of the changelog");
    }
    T::contains($box, '<details class="gk-disclosure demo-news-older"><summary>Older versions (12)</summary>', 'the older releases are not folded into a native <details>');
    T::eq(substr_count($box, 'class="demo-news-row"'), 12, 'the folded part is not the next twelve releases');
    T::notContains($box, $entries[15]['version'] . '</strong>', 'the box lists more than the twelve older releases — the changelog has them');
    T::contains($box, '<a href="#changelog">All versions in the changelog</a>', 'there is no way to the whole changelog');
    // What the box shows, it shows with GridKit's parts and the demo's classes — no style attribute, no script.
    T::notContains($box, ' style=', 'the news box carries an inline style');
    T::notContains($box, '<script', 'the news box carries a script');
    // Native and operable: summary and links are focusable by themselves; a card names itself for a screen reader.
    T::contains($box, '<summary class="demo-news-summary">', 'the box has no summary line');
    T::eq(substr_count($box, '<article'), substr_count($box, 'aria-labelledby='), 'a card does not name itself');
    T::notContains($box, '>Tests<', 'the box lists how a release was tested as news');
    T::notContains($box, '>Notes<', 'the box lists the notes of a release as news');
},

'a card has the version, the date, a sentence, the kinds counted and the classes of the release' => function () use ($demo): void {
    Lang::set('en');
    $all = whatsNew(60, 0);
    $card = static function (string $version) use ($all): string {
        preg_match('#<article class="gk-card demo-news-card" aria-labelledby="demo-news-v-' . str_replace('.', '-', $version) . '">.*?</article>#s', $all, $m);
        return $m[0] ?? '';
    };
    $byVersion = array_column(gkChangelog(), null, 'version');

    $c = $card('1.103.0');
    T::ok($c !== '', 'there is no card for 1.103.0');
    T::contains($c, '>1.103.0</h3>', 'the card has no version');
    T::contains($c, '<time class="demo-news-date" datetime="2026-10-29">2026-10-29</time>', 'the card has no date');
    T::contains($c, 'Tab variants: four looks, five places', 'the card does not carry the first sentence of the introduction');
    T::notContains($c, 'A count (', 'the card carries more than the first sentence of the entry');
    // The numbers of a historic entry: if they move, an old entry was edited.
    T::eq(array_map(static fn(array $g): int => $g['count'], $byVersion['1.103.0']['groups']), ['Added' => 7, 'Changed' => 2], '1.103.0 is not 7 added, 2 changed (Notes and Tests left out)');
    T::contains($c, '<span class="gk-label gk-label-green">Added</span> <span class="demo-news-count">7<span class="gk-sr-only"> entries</span></span>', 'the count of Added is not beside its label');
    T::contains($c, '<span class="gk-label gk-label-blue">Changed</span> <span class="demo-news-count">2', 'the count of Changed is not beside its label');
    T::notContains($c, 'gk-label-orange', '1.103.0 has no Fixed and the card shows one');
    T::contains($c, '<li class="demo-news-more">+3 more</li>', 'the entries beyond the fourth are not counted as "+3 more"');
    T::eq(substr_count($c, '<li>'), 4 + 2, 'a group shows more than its first four leads');

    $c2 = $card('1.102.0');
    T::eq(array_map(static fn(array $g): int => $g['count'], $byVersion['1.102.0']['groups']), ['Added' => 6, 'Changed' => 2, 'Fixed' => 1], '1.102.0 is not 6 added, 2 changed, 1 fixed');
    T::contains($c2, 'gk-label-orange">Fixed</span>', 'the Fixed group of 1.102.0 has no orange label');

    // The classes are the index's: those of the release, six as labels, the rest counted.
    $own = [];
    foreach (gkBlocks() as $b) foreach ($b['classes'] as $class => $v) if ($v === '1.103.0') $own[] = $class;
    sort($own);
    T::ok(count($own) > 6, 'the check needs more than six new classes in 1.103.0');
    foreach (array_slice($own, 0, 6) as $class) T::contains($c, '>' . $class . '</code>', "$class, new in 1.103.0, is not a label on its card");
    T::notContains($c, '>' . $own[6] . '</code>', 'a seventh class is a label too');
    T::contains($c, '<span class="demo-news-more">+' . (count($own) - 6) . '</span>', 'the classes beyond the sixth are not counted');
    T::contains($c2, '>gk-tab-nav-raster</code>', 'a class new in 1.102.0 is not on its card');
    T::contains($c2, '>gk-tab-badge-error</code>', 'a class new in 1.102.0 is not on its card');
    T::notContains($c2, 'gk-tab-nav-linie', 'a class of 1.103.0 is on the card of 1.102.0');

    // The way to the demo: where the blocks of the release are shown.
    T::contains($c, '<p class="demo-news-jump">Examples: <a href="#tabs">Tabs</a></p>', 'the card does not lead to the demo section of the tabs');
    foreach (gkChangelog() as $e) {
        foreach (gkReleaseBlocks($e['version'])['sections'] as $section => $names) {
            T::contains($demo(), 'data-section="' . $section . '"', "$e[version] leads to a section \"$section\" the demo does not have");
        }
    }

    // A release without a sentence has no empty paragraph, and no group counts nothing more.
    T::notContains($all, '<p class="demo-news-intro"></p>', 'an empty sentence is a paragraph');
    T::notContains($all, 'class="demo-news-more">+0', 'a count of nothing');
},

'the news box speaks the language of the page' => function (): void {
    Lang::set('de');
    $de = whatsNew();
    $deAll = whatsNew(60, 0);
    Lang::set('en');
    $entries = gkChangelog();
    T::contains($de, 'Neu in ' . $entries[0]['version'], 'the German summary does not say "Neu in"');
    T::contains($de, 'gk-label-green">Neu</span>', 'the German kind label is not "Neu"');
    T::contains($de, 'Beispiele: <a href="#tabs">Tabs</a>', 'the German jump is not "Beispiele"');
    T::contains($de, 'Ältere Versionen (12)', 'the German fold is not "Ältere Versionen"');
    T::contains($de, 'Alle Versionen im Changelog', 'the German way to the changelog is not named');
    T::contains($deAll, 'datetime="2026-10-29">29.10.2026</time>', 'the date of a German page is not 29.10.2026');
},

'the news box is in the demo, and the demo folds it on a phone' => function () use ($demo): void {
    T::contains($demo(), '<?= whatsNew() ?>', 'the demo does not show the box');
    T::contains($demo(), "document.querySelector('.demo-news')", 'the demo does not fold the box on a phone');
    // Every class the box uses is styled in the demo — and the old ones of the unstructured box are gone.
    foreach (['demo-news-card', 'demo-news-head', 'demo-news-version', 'demo-news-date', 'demo-news-intro', 'demo-news-group-title', 'demo-news-count',
        'demo-news-list', 'demo-news-more', 'demo-news-classes', 'demo-news-class', 'demo-news-jump', 'demo-news-older', 'demo-news-rows', 'demo-news-row'] as $class) {
        T::contains($demo(), '.' . $class . ' ', "the demo has no rule for .$class");
    }
    foreach (['demo-news-entry', 'demo-news-blocks'] as $gone) {
        T::notContains($demo(), $gone, "the demo still styles .$gone of the old box");
    }
},

'the box changes with the changelog, not with the code: a new entry is the first card' => function (): void {
    Lang::set('en');
    $md = file_get_contents(__DIR__ . '/../CHANGELOG.md');
    $file = tempnam(sys_get_temp_dir(), 'gk-cl-');
    file_put_contents($file, preg_replace('/^---\n## \[/m', "---\n## [9.9.9] - 2099-01-01\n\nA release from the future.\n\n### Added\n- **A thing**: it is new\n\n---\n## [", $md, 1));
    try {
        $first = gkChangelog($file)[0];
    } finally {
        unlink($file);
    }
    T::eq($first['version'], '9.9.9', 'a new entry at the top is not the first the reader sees');
    T::eq($first['groups']['Added']['items'], ['A thing'], 'a new entry is not read like the others');
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
