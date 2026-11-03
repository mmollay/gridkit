<?php
/**
 * News by component in the demo (1.110.4): the sidebar says beside each section that it has
 * something new or changed, and the changelog section opens with a list by component.
 *
 * Both are computed from css/blocks.json and CHANGELOG.md, like the marks on the headings
 * (tests/blocks.test.php). Held here: the sidebar says exactly what the headings say, once per
 * section and nowhere else; the list names every block new or changed in its span with the exact
 * version; the demo uses both. Which blocks are in the window moves with every release, so the
 * cases find them, they do not name them.
 */

declare(strict_types=1);

use GridKit\{Lang, Sidebar};

require_once __DIR__ . '/../demo/_whatsnew.php';

/** @return array<string,callable> */
return (static function (): array {

$sidebar = static function (): string {
    $s = new Sidebar('demo');
    $s->brand('', 'widgets')->group('Components');
    // The demo's own items, read from demo/index.php: a component section added later (Permissions 1.115.0,
    // Tree 1.116.0) is in the stand-in without an edit here.
    preg_match_all("~->item\\('[^']*', '#([a-z-]+)'~", (string) file_get_contents(__DIR__ . '/../demo/index.php'), $items);
    foreach (array_diff($items[1], ['examples']) as $id) {
        $s->item(ucfirst($id), '#' . $id, 'circle');
    }
    ob_start();
    $s->render();
    return (string) ob_get_clean();
};

return [

'every section the sidebar marks has the same mark on a heading, and no other section is marked' => function () use ($sidebar): void {
    Lang::set('en');
    $news = gkSectionNews();
    $html = gkNavNews($sidebar());
    foreach ($news as $section => $list) {
        foreach ($list as $n) {
            $want = ($n['kind'] === 'new' ? 'New in ' : 'Changed in ') . $n['minor'];
            T::contains(since($n['key']), $want, "the sidebar marks {$n['name']} in #$section, its heading does not say \"$want\"");
        }
        T::eq(preg_match_all('#href="\#' . preg_quote($section, '#') . '"[^>]*>(?:(?!</a>).)*demo-nav-news#s', $html), 1, "#$section is not marked exactly once");
    }
    T::eq(substr_count($html, 'class="demo-nav-news '), count($news), 'a section without news is marked, or one with news is not');
},

'a mark says new or update, and which components since when, to the eye, the popup and a screen reader' => function () use ($sidebar): void {
    Lang::set('en');
    $html = gkNavNews($sidebar());
    foreach (gkSectionNews() as $section => $list) {
        $kind = in_array('new', array_column($list, 'kind'), true) ? 'new' : 'changed';
        preg_match('#href="\#' . preg_quote($section, '#') . '"(?:(?!</a>).)*?(<span class="demo-nav-news [^"]*" title="([^"]*)">.*?</span></span>)#s', $html, $m);
        T::ok(isset($m[1]), "#$section has no mark");
        T::contains($m[1] ?? '', 'demo-nav-news-' . $kind, "#$section is marked as the wrong kind");
        T::contains($m[1] ?? '', '<span aria-hidden="true">' . ($kind === 'new' ? 'New' : 'Update') . '</span>', "#$section shows the wrong word");
        foreach ($list as $n) {
            T::contains($m[2] ?? '', htmlspecialchars($n['name'], ENT_QUOTES, 'UTF-8'), "the popup of #$section does not name {$n['name']}");
            T::contains($m[1] ?? '', '<span class="gk-sr-only">', "a screen reader hears only \"" . ($kind === 'new' ? 'New' : 'Update') . '"');
        }
    }
    Lang::set('de');
    $de = gkNavNews($sidebar());
    if (gkSectionNews()) T::ok(str_contains($de, '>Neu<') || str_contains($de, '>Update<'), 'the German sidebar does not speak German');
    Lang::set('en');
},

'the list by component names every block new or changed in its span, newest first, with the exact version' => function (): void {
    Lang::set('en');
    $span = gkRecentMinors(gkChangelog(), 6);
    $rows = gkComponentNews(6);
    $html = componentNews(6);
    $named = array_column($rows, 'key');
    foreach (gkBlocks() as $key => $b) {
        $in = in_array(gkMinor($b['since']), $span, true) || ($b['changed'] !== null && in_array(gkMinor($b['changed']), $span, true));
        T::eq(in_array((string) $key, $named, true), $in, "block $key is " . ($in ? 'missing from' : 'wrongly in') . ' the list');
    }
    $versions = array_column($rows, 'version');
    $sorted = $versions;
    usort($sorted, static fn(string $a, string $b): int => version_compare($b, $a));
    T::eq($versions, $sorted, 'the list is not newest first');
    foreach ($rows as $r) {
        T::contains($html, '<td>' . htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8') . '</td>', "{$r['name']} is not in the list");
        T::contains($html, '<code>' . $r['version'] . '</code>', "{$r['name']} has no version");
        if ($r['section'] !== null) T::contains($html, '<a href="#' . $r['section'] . '">', "{$r['name']} does not lead to the demo");
    }
},

'the demo uses both: the sidebar goes through gkNavNews(), the changelog section opens with componentNews()' => function (): void {
    $index = (string) file_get_contents(__DIR__ . '/../demo/index.php');
    T::contains($index, 'echo gkNavNews((string) ob_get_clean());', 'the sidebar of the demo is not given its news');
    T::ok(strpos($index, 'echo gkNavNews(') > strpos($index, '$sidebar->render();'), 'the news are not put into the rendered sidebar');
    $log = (string) file_get_contents(__DIR__ . '/../demo/sections/changelog.php');
    T::ok(strpos($log, '<?= componentNews() ?>') !== false && strpos($log, '<?= componentNews() ?>') < strpos($log, '$changelog = '), 'the changelog section does not open with the list by component');
},

];
})();
