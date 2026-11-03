<?php
/**
 * GridKit\Tree (1.116.0) — a tree view for page trees, categories and folders.
 *
 * What the server writes is checked here: the nested list from flat rows and from nested arrays
 * (the same markup for the same tree), what a broken input becomes (duplicate ids, orphans, cycles,
 * options of the wrong type — each a warning and a tree that still renders), escaping, the shape a
 * selection is posted in, expandTo(), the lazy markers and renderBranch(), the contract with
 * js/tree.js (the attributes it reads, the words it asks for) and with the stylesheet. What the
 * script does with the markup — keys, moves, lazy loading, the filter, contrast — needs a browser:
 * ci/browser-tree.js.
 */

declare(strict_types=1);

use GridKit\{Lang, Tree};

require_once __DIR__ . '/css-helpers.php';

/** Render, and collect what it said with E_USER_WARNING. @return array{0:string,1:list<string>} */
function trRender(callable $fn): array
{
    $notes = [];
    set_error_handler(static function (int $no, string $msg) use (&$notes): bool {
        if ($no !== E_USER_WARNING) return false;
        $notes[] = $msg;
        return true;
    });
    try {
        $html = T::capture($fn);
    } finally {
        restore_error_handler();
    }
    return [$html, $notes];
}

/** The ids of the nodes in document order, with their depth: "home:1 about:1 team:2". */
function trShape(string $html): string
{
    $doc = new DOMDocument();
    @$doc->loadHTML('<?xml encoding="utf-8"?>' . $html, LIBXML_NOERROR);
    $out = [];
    foreach ((new DOMXPath($doc))->query('//li[contains(concat(" ", @class, " "), " gk-tree-node ")]') as $li) {
        $depth = 0;
        for ($p = $li->parentNode; $p; $p = $p->parentNode) {
            if ($p instanceof DOMElement && $p->tagName === 'li') $depth++;
        }
        $out[] = $li->getAttribute('data-gk-tree-id') . ':' . ($depth + 1);
    }
    return implode(' ', $out);
}

/** A CMS page tree as a database hands it over: flat, each row naming its parent. */
function trPages(): array
{
    return [
        ['id' => 1, 'parent_id' => null, 'title' => 'Home', 'icon' => 'home', 'href' => '/', 'draggable' => 0],
        ['id' => 2, 'parent_id' => 0, 'title' => 'About us'],
        ['id' => 3, 'parent_id' => 2, 'title' => 'Team'],
        ['id' => 4, 'parent_id' => 2, 'title' => 'History', 'muted' => 1],
        ['id' => 5, 'parent_id' => '', 'title' => 'Services', 'count' => 3],
        ['id' => 6, 'parent_id' => 5, 'title' => 'Web design', 'badge' => 'New'],
        ['id' => 7, 'parent_id' => 5, 'title' => 'Hosting'],
        ['id' => 8, 'parent_id' => null, 'title' => 'Blog', 'children' => 'lazy'],
    ];
}

/** The same tree as nested arrays. */
function trPagesNested(): array
{
    return [
        ['id' => 1, 'title' => 'Home', 'icon' => 'home', 'href' => '/', 'draggable' => 0],
        ['id' => 2, 'title' => 'About us', 'children' => [
            ['id' => 3, 'title' => 'Team'],
            ['id' => 4, 'title' => 'History', 'muted' => 1],
        ]],
        ['id' => 5, 'title' => 'Services', 'count' => 3, 'children' => [
            ['id' => 6, 'title' => 'Web design', 'badge' => 'New'],
            ['id' => 7, 'title' => 'Hosting'],
        ]],
        ['id' => 8, 'title' => 'Blog', 'children' => 'lazy'],
    ];
}

/** @return array<string,callable> */
return (static function (): array {

$root = __DIR__ . '/..';
$js   = static fn (): string => (string) @file_get_contents($root . '/js/tree.js');
$code = static fn (): string => (string) preg_replace(['#/\*.*?\*/#s', '#(?<![:"\'])//[^\n]*#'], '', $js());
$doc  = static fn (): string => (string) file_get_contents($root . '/GRIDKIT_SKILL.md');
/** The tree's block of the stylesheet, comments stripped. */
$sheet = static function (): string {
    $css = gkCssText();
    $from = strpos($css, '=== 1.116.0 — tree ===');
    return $from === false ? '' : (string) preg_replace('#/\*.*?\*/#s', '', substr($css, $from));
};

return [

// ── Input ───────────────────────────────────────────────────────────────

'flat rows become a nested list in the order they were given' => function (): void {
    Lang::set('en');
    [$html, $notes] = trRender(fn () => (new Tree('pages'))->nodes(trPages())->render());
    T::eq($notes, [], 'a clean tree says nothing');
    T::eq(trShape($html), '1:1 2:1 3:2 4:2 5:1 6:2 7:2 8:1', 'the shape of the tree');
    T::contains($html, '<div class="gk-tree" id="gk-tree-pages" data-gk-tree="pages" data-gk-tree-select="none" data-gk-tree-label="Tree"', 'the wrap and its contract');
    T::contains($html, '<ul class="gk-tree-list" id="gk-tree-pages-list">', 'the top level is one list');
    T::contains($html, '<ul class="gk-tree-group" id="gk-tree-pages-2-g">', 'a branch is a nested list');
    T::contains($html, '<div class="gk-tree-row" data-gk-tree-level="2">', 'every row knows its level (the indent is drawn from it)');
    T::notContains($html, 'role="tree', 'the server writes no tree roles: without the script it is a list, and says so');
},

'nested arrays give the same markup as the same tree written flat' => function (): void {
    [$flat] = trRender(fn () => (new Tree('pages'))->nodes(trPages())->render());
    [$nested, $notes] = trRender(fn () => (new Tree('pages'))->nodes(trPagesNested())->render());
    T::eq($notes, [], 'nested input says nothing');
    T::eq($nested, $flat, 'the two inputs write the same tree, byte for byte');
},

'node() takes a parent that is added later, and children in its options' => function (): void {
    [$html, $notes] = trRender(fn () => (new Tree('t'))
        ->node('b', 'B', 'a')
        ->node('a', 'A')
        ->node('c', 'C', null, ['children' => [['id' => 'c1', 'title' => 'C one']]])
        ->render());
    T::eq($notes, [], 'no warning');
    T::eq(trShape($html), 'a:1 b:2 c:1 c1:2', 'b sits under a although it came first, c1 under c');
},

'the map renames fields, and a node option comes from a row under its own name or the mapped one' => function (): void {
    $rows = [
        ['key' => 'p', 'up' => null, 'name' => 'Parent', 'hidden' => 'yes', 'kids' => [['key' => 'k', 'name' => 'Kid', 'href' => '/k']]],
        (object) ['key' => 'o', 'up' => 'p', 'name' => 'From an object'],
    ];
    [$html, $notes] = trRender(fn () => (new Tree('m'))
        ->nodes($rows, ['id' => 'key', 'parent' => 'up', 'label' => 'name', 'children' => 'kids', 'muted' => 'hidden'])->render());
    T::eq($notes, [], 'no warning');
    T::eq(trShape($html), 'p:1 k:2 o:2', 'nested kids, then the flat row that names p');
    T::contains($html, 'class="gk-tree-node gk-tree-muted" data-gk-tree-id="p"', "'muted' read from the mapped field");
    T::contains($html, 'href="/k"', 'an option read under its own name');
},

'yes and no as a database hands them over: 1/0, "1"/"0", "yes"/"no" — "no" is not true' => function (): void {
    [$html, $notes] = trRender(fn () => (new Tree('f'))->select('multi')->nodes([
        ['id' => 'a', 'title' => 'A', 'expanded' => 'no', 'disabled' => '0', 'muted' => 'no', 'children' => [['id' => 'a1', 'title' => 'A1']]],
        ['id' => 'b', 'title' => 'B', 'expanded' => 'yes', 'disabled' => 1, 'muted' => '1', 'draggable' => 'no', 'children' => [['id' => 'b1', 'title' => 'B1']]],
    ])->render());
    T::eq($notes, [], 'no warning');
    T::contains($html, 'class="gk-tree-node" data-gk-tree-id="a">', "'no' and '0' leave a node closed, enabled and not muted");
    T::contains($html, 'class="gk-tree-node gk-tree-open gk-tree-muted gk-tree-disabled" data-gk-tree-id="b" data-gk-tree-disabled data-gk-tree-nodrag>', "'yes', 1 and '1' are true; 'no' keeps it in place");
},

'a parent of null, empty or 0 is the top level — 0 only while no node is called 0' => function (): void {
    [$html] = trRender(fn () => (new Tree('z'))->nodes([
        ['id' => 'a', 'parent_id' => 0, 'title' => 'A'],
        ['id' => 'b', 'parent_id' => '', 'title' => 'B'],
    ])->render());
    T::eq(trShape($html), 'a:1 b:1', '0 and empty are the top level');
    [$html, $notes] = trRender(fn () => (new Tree('z'))->nodes([
        ['id' => 0, 'parent_id' => null, 'title' => 'Zero'],
        ['id' => 'a', 'parent_id' => 0, 'title' => 'A'],
    ])->render());
    T::eq(trShape($html), '0:1 a:2', 'a node called 0 is a parent like any other');
    T::eq($notes, [], 'and no warning');
},

// ── What a broken input becomes ─────────────────────────────────────────

'a duplicate id: the second node is left out, with a warning' => function (): void {
    [$html, $notes] = trRender(fn () => (new Tree('d'))->node('a', 'First')->node('a', 'Second')->render());
    T::contains($html, '>First<', 'the first one stays');
    T::notContains($html, 'Second', 'the second one is gone');
    T::ok(count($notes) === 1 && str_contains($notes[0], "the id 'a' is taken — the second node ('Second') was left out"), 'warned: ' . implode(' | ', $notes));
},

'an orphan is shown at the top level, with a warning' => function (): void {
    [$html, $notes] = trRender(fn () => (new Tree('o'))->node('a', 'A')->node('x', 'Orphan', 'nobody')->render());
    T::eq(trShape($html), 'a:1 x:1', 'the orphan is a top-level node');
    T::ok(count($notes) === 1 && str_contains($notes[0], "GridKit: tree 'o': node 'x' names the parent 'nobody', which the tree does not have"), 'warned: ' . implode(' | ', $notes));
},

'a cycle is cut where it was first written, with a warning; nothing loops' => function (): void {
    [$html, $notes] = trRender(fn () => (new Tree('c'))
        ->node('top', 'Top')
        ->node('b', 'B', 'c')
        ->node('a', 'A', 'b')
        ->node('c', 'C', 'a')
        ->node('self', 'Self', 'self')
        ->render());
    T::eq(trShape($html), 'top:1 self:1 b:1 a:2 c:3', "a node that is its own parent is a top-level node in its place; b (written first of the ring) is lifted to the top, the ring hangs under it");
    $joined = implode(' | ', $notes);
    T::contains($joined, "nodes 'b', 'c', 'a' are each other's ancestors — 'b' is shown at the top level", 'the ring is named');
    T::contains($joined, "node 'self' is its own parent", 'the self-parent is named');
    T::eq(count($notes), 2, 'one warning each');
},

'options of the wrong type are dropped with a warning, the node still shows' => function (): void {
    [$html, $notes] = trRender(fn () => (new Tree('w'))
        ->node('a', 'A', null, ['expanded' => 'maybe', 'disabled' => [], 'badge' => ['text' => 'X', 'color' => 'purple'], 'colour' => 'red',
            'children' => 'later', 'actions' => 'edit', 'count' => true, 'href' => 'javascript:alert(1)'])
        ->render());
    $joined = implode(' | ', $notes);
    foreach (["'expanded' => 'maybe' — it takes true or false", "'disabled' => array", "'badge color' => 'purple'",
              "the option 'colour', which a tree node does not know", "'children' => 'later'", "'actions' => 'edit'",
              "'count' => true", 'javascript: href'] as $said) {
        T::contains($joined, $said, 'warned about it');
    }
    T::contains($html, '<span class="gk-tree-label" id="gk-tree-w-a-l">A</span>', 'the node is there, its label plain text (no javascript: link)');
    T::contains($html, 'gk-label gk-label-gray gk-tree-badge', 'the badge falls back to gray');
    T::notContains($html, 'javascript:', 'the script URL never reaches the page');
},

'a row without an id is left out, one without a label shows its id' => function (): void {
    [$html, $notes] = trRender(fn () => (new Tree('r'))->nodes([['title' => 'No id'], ['id' => 'x'], 'not a row'])->render());
    T::eq(trShape($html), 'x:1', 'only the row with an id');
    T::contains($html, '>x</span>', 'the id stands in for the label');
    T::eq(count($notes), 3, 'three warnings: ' . implode(' | ', $notes));
},

'an unknown selection mode, a moveUrl header that is no array and actions() that return no array warn' => function (): void {
    [$html, $notes] = trRender(fn () => (new Tree('s'))->select('several')->moveUrl('/m', ['headers' => 'X-Token: 1'])
        ->actions(fn () => 'edit')->node('a', 'A')->render());
    $joined = implode(' | ', $notes);
    T::contains($joined, "select('several') knows 'none', 'single' and 'multi'", 'the mode');
    T::contains($joined, "moveUrl() takes 'headers' as an array", 'the headers');
    T::contains($joined, 'actions() returned string', 'the callback');
    T::contains($html, 'data-gk-tree-select="none"', 'the fallback: no selection');
    T::notContains($html, 'data-gk-tree-headers', 'no header is sent');
},

// ── Escaping ────────────────────────────────────────────────────────────

'labels, ids, links, badges, counts, icons, actions and the tree id are escaped' => function (): void {
    $x = '<script>alert(1)</script>';
    $q = '" onmouseover="alert(1)';
    [$html] = trRender(fn () => (new Tree('t' . $q))->label($x)->select('multi')->name('n' . $q)->searchable()
        ->moveUrl('/m' . $q, ['headers' => ['X-T' => "a'b" . $x]])->lazyUrl('/l' . $q)
        ->node('a' . $q, $x, null, ['href' => '/p?' . $q, 'badge' => $x, 'count' => $q, 'icon' => $q, 'muted' => $x,
            'actions' => [['icon' => 'edit', 'title' => $q, 'href' => '/e' . $q]], 'children' => [['id' => 'k', 'title' => 'K']]])
        ->render());
    T::notContains($html, '<script>alert(1)</script>', 'no script tag survived');
    T::notContains($html, ' onmouseover="alert(1)', 'no attribute was broken out of');
    T::contains($html, '&lt;script&gt;', 'the payload is there, escaped');
    T::ok(!preg_match("/data-gk-tree-headers='[^']*'[^>]*'/", $html), 'the JSON of the headers stays inside its quotes');
},

// ── Selection, as a form posts it ───────────────────────────────────────

'multi: a checkbox per node posted as name[]=id; a selected parent ticks its branch, a full branch ticks its parent' => function (): void {
    [$html, $notes] = trRender(fn () => (new Tree('cats'))->select('multi')->name('cats')->selected(['books', 'cd'])
        ->node('books', 'Books')->node('novels', 'Novels', 'books')->node('rare', 'Rare', 'books', ['disabled' => true])
        ->node('music', 'Music')->node('cd', 'CDs', 'music')
        ->node('games', 'Games')
        ->render());
    T::eq($notes, [], 'no warning');
    preg_match_all('/<input type="checkbox" class="gk-tree-check" id="[^"]+" name="cats\[\]" value="([^"]+)"( checked)?( disabled)?/', $html, $m, PREG_SET_ORDER);
    $state = [];
    foreach ($m as $hit) $state[$hit[1]] = trim(($hit[2] ?? '') . ($hit[3] ?? ''));
    T::eq($state, ['books' => 'checked', 'novels' => 'checked', 'rare' => 'disabled', 'music' => 'checked', 'cd' => 'checked', 'games' => ''],
        'books ticks novels but not the disabled rare; music is ticked because all its children are');
    T::contains($html, '<label class="gk-tree-label" id="gk-tree-cats-novels-l" for="gk-tree-cats-novels-c">Novels</label>', 'the name is the label of its box');
},

'multi: a parent with a child left out is not ticked (the script makes it mixed); the default name is the tree id' => function (): void {
    [$html] = trRender(fn () => (new Tree('cats'))->select('multi')->selected(['novels'])
        ->node('books', 'Books')->node('novels', 'Novels', 'books')->node('poetry', 'Poetry', 'books')->render());
    T::contains($html, 'name="cats[]" value="books">', 'books is not ticked');
    T::contains($html, 'name="cats[]" value="novels" checked>', 'novels is');
},

'single: a radio button per node posted as name=id, the first selected id wins (with a warning)' => function (): void {
    [$html, $notes] = trRender(fn () => (new Tree('page'))->select('single')->selected(['b', 'a'])->node('a', 'A')->node('b', 'B')->render());
    T::contains($html, '<input type="radio" class="gk-tree-radio" id="gk-tree-page-a-c" name="page" value="a">', 'a radio, named by the tree id');
    T::contains($html, 'name="page" value="b" checked>', 'b, the first named, is chosen');
    T::ok(count($notes) === 1 && str_contains($notes[0], "'b' is the one"), 'warned: ' . implode(' | ', $notes));
},

'a node with a link keeps its box, named by the link' => function (): void {
    [$html] = trRender(fn () => (new Tree('l'))->select('multi')->node('a', 'A', null, ['href' => '/a'])->render());
    T::contains($html, 'value="a" aria-labelledby="gk-tree-l-a-l">', 'the box points at the link');
    T::contains($html, '<a class="gk-tree-label" id="gk-tree-l-a-l" href="/a">A</a>', 'the link');
},

'a selected id the tree does not have: a hidden field in a tree with lazy branches, a warning without' => function (): void {
    [$html, $notes] = trRender(fn () => (new Tree('c'))->select('multi')->selected(['deep'])->node('a', 'A', null, ['children' => 'lazy'])->render());
    T::contains($html, '<input type="hidden" name="c[]" value="deep" data-gk-tree-pending>', 'posted although its node is not loaded');
    T::eq($notes, [], 'no warning there');
    [$html, $notes] = trRender(fn () => (new Tree('c'))->select('multi')->selected(['deep'])->node('a', 'A')->render());
    T::notContains($html, 'data-gk-tree-pending', 'no hidden field without lazy branches');
    T::ok(count($notes) === 1 && str_contains($notes[0], "selected() names 'deep', which the tree does not have"), 'warned');
},

'a disabled node: no link, a disabled box, marked for the script' => function (): void {
    [$html] = trRender(fn () => (new Tree('d'))->select('multi')->node('a', 'A', null, ['disabled' => true, 'href' => '/a'])->render());
    T::contains($html, 'class="gk-tree-node gk-tree-disabled" data-gk-tree-id="a" data-gk-tree-disabled>', 'marked');
    T::contains($html, 'value="a" disabled>', 'the box is disabled (and not posted)');
    T::notContains($html, 'href="/a"', 'the link is gone');
},

// ── Open, lazy, branch ──────────────────────────────────────────────────

'expandTo() opens the ancestors of a node, marks it, and leaves the rest closed' => function (): void {
    [$html, $notes] = trRender(fn () => (new Tree('pages'))->nodes(trPages())->expandTo('3')->render());
    T::contains($html, 'class="gk-tree-node gk-tree-open" data-gk-tree-id="2"', 'the parent of 3 is open');
    T::contains($html, 'data-gk-tree-id="3" data-gk-tree-target>', 'the node is the one the keyboard starts on');
    T::contains($html, 'class="gk-tree-node" data-gk-tree-id="5"', 'another parent stays closed');
    [, $notes] = trRender(fn () => (new Tree('pages'))->nodes(trPages())->expandTo('99')->render());
    T::ok(count($notes) === 1 && str_contains($notes[0], "expandTo('99')"), 'an unknown node is named');
},

"'expanded' opens a parent; a lazy node is marked and has an empty group, and may start open" => function (): void {
    [$html] = trRender(fn () => (new Tree('x'))
        ->node('a', 'A', null, ['expanded' => true])->node('a1', 'A1', 'a')
        ->node('leaf', 'Leaf', null, ['expanded' => true])
        ->node('lazy', 'Lazy', null, ['children' => 'lazy'])
        ->node('lazyopen', 'Lazy open', null, ['children' => 'lazy', 'expanded' => true])
        ->render());
    T::contains($html, 'class="gk-tree-node gk-tree-open" data-gk-tree-id="a"', 'an expanded parent is open');
    T::contains($html, 'class="gk-tree-node" data-gk-tree-id="leaf"', 'a leaf is never open');
    T::contains($html, 'data-gk-tree-id="lazy" data-gk-tree-lazy><div', 'the lazy marker');
    T::contains($html, '<ul class="gk-tree-group" id="gk-tree-x-lazy-g"></ul>', 'its empty group, waiting for the children');
    T::contains($html, 'class="gk-tree-node gk-tree-open" data-gk-tree-id="lazyopen" data-gk-tree-lazy', 'an open lazy node: the script fetches it at once');
},

'renderBranch() writes the <li> of one parent and their branches, nothing around them, no warning' => function (): void {
    [$html, $notes] = trRender(fn () => (new Tree('pages'))->select('multi')->name('pages')->expandTo('elsewhere')->nodes([
        ['id' => 'p1', 'parent_id' => 'blog', 'title' => 'Post 1'],
        ['id' => 'p2', 'parent_id' => 'blog', 'title' => 'Post 2', 'children' => 'lazy'],
        ['id' => 'c1', 'parent_id' => 'p1', 'title' => 'Comment'],
    ])->renderBranch('blog'));
    T::eq($notes, [], 'the branch\'s parent and the node to open are elsewhere, which is no mistake');
    T::ok(str_starts_with($html, '<li class="gk-tree-node" data-gk-tree-id="p1">') && str_ends_with($html, '</li>'), 'only list items');
    T::notContains($html, 'gk-tree-toolbar', 'no toolbar');
    T::eq(trShape('<ul>' . $html . '</ul>'), 'p1:1 c1:2 p2:1', 'the branch and its own children');
    T::contains($html, 'name="pages[]" value="p1"', 'the boxes post under the same name as the page');
    [$whole] = trRender(fn () => (new Tree('pages'))->nodes(trPages())->renderBranch('2'));
    T::eq(trShape('<ul>' . $whole . '</ul>'), '3:1 4:1', 'from a whole tree, the children of one node');
},

// ── The wrap: toolbar, contract, words ──────────────────────────────────

'the toolbar: a named filter field and expand/collapse all where there is something to open' => function (): void {
    Lang::set('en');
    [$html] = trRender(fn () => (new Tree('p'))->label('Pages')->searchable()->nodes(trPages())->render());
    T::contains($html, 'class="gk-search gk-tree-search" autocomplete="off" placeholder="Filter…" aria-label="Filter Pages" aria-controls="gk-tree-p-list"', 'the filter is named and points at the list');
    T::contains($html, 'data-gk-tree-all="expand"', 'expand all');
    T::contains($html, 'data-gk-tree-all="collapse"', 'collapse all');
    [$flat] = trRender(fn () => (new Tree('f'))->node('a', 'A')->node('b', 'B')->render());
    T::notContains($flat, 'gk-tree-toolbar', 'nothing to filter or open: no toolbar');
    [$off] = trRender(fn () => (new Tree('p'))->searchable()->toolbar(false)->nodes(trPages())->render());
    T::notContains($off, 'gk-tree-toolbar', 'toolbar(false)');
},

'what the script reads is on the wrap: urls, headers, movable, memory, selection, name' => function (): void {
    [$html] = trRender(fn () => (new Tree('p'))->label('Pages')->select('single')->lazyUrl('/kids?x=1')
        ->moveUrl('/move', ['headers' => ['X-CSRF-Token' => 'abc']])->remember(false)->node('a', 'A')->render());
    foreach (['data-gk-tree-select="single"', 'data-gk-tree-label="Pages"', 'data-gk-tree-lazy-url="/kids?x=1"', 'data-gk-tree-movable',
              'data-gk-tree-move-url="/move"', "data-gk-tree-headers='{&quot;X-CSRF-Token&quot;:&quot;abc&quot;}'", 'data-gk-tree-remember="off"'] as $attr) {
        T::contains($html, $attr, 'the wrap carries it');
    }
    [$movable] = trRender(fn () => (new Tree('p'))->movable()->node('a', 'A')->node('b', 'B', null, ['draggable' => false])->render());
    T::contains($movable, 'data-gk-tree-movable>', 'movable() without a url');
    T::notContains($movable, 'data-gk-tree-move-url', 'and no url');
    T::contains($movable, 'data-gk-tree-id="b" data-gk-tree-nodrag>', "'draggable' => false");
},

'muted, badge and count: words for a screen reader, labels from GridKit\'s palette' => function (): void {
    Lang::set('en');
    [$html] = trRender(fn () => (new Tree('m'))->node('a', 'A', null, ['muted' => true, 'badge' => ['text' => 'Draft', 'color' => 'orange'], 'count' => 12])
        ->node('b', 'B', null, ['muted' => 'unpublished'])->render());
    T::contains($html, '<span class="gk-sr-only gk-tree-note" id="gk-tree-m-a-n">(inactive)</span>', 'muted: the catalogue word');
    T::contains($html, '(unpublished)</span>', 'muted: its own word');
    T::contains($html, '<span class="gk-label gk-label-orange gk-tree-badge" id="gk-tree-m-a-b">Draft</span>', 'a badge is a label');
    T::contains($html, '<span class="gk-tree-count" id="gk-tree-m-a-k">12</span>', 'a count');
},

'row buttons: the node\'s own, then actions(); showIf; described by the node\'s name' => function (): void {
    $seen = [];
    [$html] = trRender(function () use (&$seen): void { (new Tree('a'))
        ->node('p', 'Parent', null, ['actions' => [['icon' => 'add', 'title' => 'Add page', 'href' => '/new?parent=p']]])
        ->node('c', 'Child', 'p')
        ->actions(function (array $n) use (&$seen): array {
            $seen[$n['id']] = $n['level'] . '/' . $n['children'];
            return [['icon' => 'delete', 'title' => 'Delete', 'onclick' => 'del()', 'showIf' => $n['children'] === 0]];
        })
        ->render(); });
    T::eq($seen, ['p' => '1/1', 'c' => '2/0'], 'the callback gets the level and the number of children');
    T::contains($html, '<div class="gk-action-group gk-tree-actions"><a href="/new?parent=p" class="gk-btn gk-btn-text gk-btn-neutral gk-btn-sm gk-btn-icon-only" aria-label="Add page" title="Add page" aria-describedby="gk-tree-a-p-l">',
        'the action-group look, named and described by the node');
    T::eq(substr_count($html, 'aria-label="Delete"'), 1, 'showIf: delete only where there are no children');
},

'an empty tree says so, in the language of the page' => function (): void {
    Lang::set('de');
    [$html] = trRender(fn () => (new Tree('e'))->render());
    T::contains($html, '<p class="gk-tree-empty">Keine Einträge</p>', 'German');
    [$bar] = trRender(fn () => (new Tree('e'))->searchable()->label('Seiten')->node('a', 'A', null, ['children' => 'lazy'])->render());
    T::contains($bar, 'aria-label="Seiten filtern"', 'the filter field in German');
    T::contains($bar, 'Alle aufklappen', 'expand all in German');
    Lang::set('en');
},

// ── The script and the stylesheet ───────────────────────────────────────

'the script is a file of its own, strict, closed in a function, with GK.tree as its only name outside' => function () use ($js, $code, $root): void {
    T::ok(is_file($root . '/js/tree.js'), 'js/tree.js does not exist — the dashboard keeps its own gridkit.js, a block there would never reach it');
    $c = $code();
    T::contains($c, '"use strict"', 'strict');
    T::ok((bool) preg_match('/\A\s*\(function \(\) \{/', $c), 'opens with a function that closes over everything');
    T::ok((bool) preg_match('/\}\)\(\);\s*\z/', $c), 'and calls it');
    preg_match_all('/\bwindow\.([A-Za-z_]+)(?:\.[A-Za-z_]+)?\s*=[^=]/', $c, $m);
    T::eq(array_values(array_unique($m[1])), ['GK'], 'the only global it writes is GK (GK.tree)');
    T::ok(!preg_match('/\b(jQuery|\$\()|\bimport\s|\brequire\(/', $c), 'no dependency');
    T::contains($c, 'MutationObserver', 'a tree inserted later is set up');
    T::contains($c, 'readyState', 'it starts when loaded late (defer, a fragment)');
    T::contains($c, 'WeakMap', 'one state per tree, however often the page is scanned');
    exec('node --version 2>/dev/null', $probe, $hasNode);
    if ($hasNode === 0) {
        exec('node --check ' . escapeshellarg($root . '/js/tree.js') . ' 2>&1', $out, $rc);
        T::eq($rc, 0, 'js/tree.js parses: ' . implode(' ', $out));
    }
},

'every data-gk-tree attribute Tree.php writes is read by the script or the stylesheet' => function () use ($js, $root): void {
    $php = (string) file_get_contents($root . '/src/Tree.php');
    preg_match_all('/data-gk-tree(?:-[a-z]+)*/', $php, $m);
    $attrs = array_values(array_unique($m[0]));
    T::ok(count($attrs) >= 14, 'found ' . count($attrs) . ' attributes — the pattern broke');
    $css = gkCssText();
    foreach ($attrs as $attr) {
        T::ok(str_contains($js(), $attr) || str_contains($css, $attr), "$attr is written and read by nothing");
    }
},

'every word the script shows comes from the catalogue, in both locales, and reaches the page' => function () use ($code, $root): void {
    $en = require $root . '/lang/en.php';
    $de = require $root . '/lang/de.php';
    preg_match_all('/\bt\(\s*"(tree_[a-z_]+)"/', $code(), $m);
    preg_match_all('/"(tree_(?:into_itself|not_target))"/', $code(), $m2);
    $keys = array_values(array_unique(array_merge($m[1], $m2[1])));
    T::ok(count($keys) >= 18, 'found ' . count($keys) . ' keys');
    foreach ($keys as $key) {
        T::ok(isset($en["js.$key"]), "the script asks for $key — not in lang/en.php as js.$key");
        T::ok(isset($de["js.$key"]), "the script asks for $key — not in lang/de.php as js.$key");
    }
    Lang::set('de');
    $cfg = Lang::jsConfig();
    foreach ($keys as $key) T::contains($cfg, '"' . $key . '"', "Lang::jsConfig() carries $key");
    Lang::set('en');
},

'the keys of the WAI tree pattern, the roles and the states are all in the script' => function () use ($code): void {
    $c = $code();
    foreach (['"tree"', '"treeitem"', '"group"', 'aria-level', 'aria-setsize', 'aria-posinset', 'aria-expanded', 'aria-selected', 'aria-checked',
              'aria-multiselectable', 'aria-busy', 'aria-live', '"mixed"', 'indeterminate'] as $w) {
        T::contains($c, $w, "the script never writes $w");
    }
    foreach (['"ArrowDown"', '"ArrowUp"', '"ArrowRight"', '"ArrowLeft"', '"Home"', '"End"', '"*"', '"Enter"', '" "', '"Escape"'] as $key) {
        T::contains($c, $key, "the script never reads the key $key");
    }
    foreach (['pointerdown', 'pointermove', 'pointerup', 'pointercancel', 'setPointerCapture', 'localStorage', 'gk:treeselect', 'gk:treetoggle', 'gk:treemove', 'gk:treemoved'] as $w) {
        T::contains($c, $w, "the script does not use $w");
    }
    T::notContains($c, 'touchstart', 'pointer events do mouse, pen and finger');
    T::ok((bool) preg_match('/try\s*\{\s*localStorage/', $c), 'localStorage is read and written inside try');
    T::contains($c, '"POST"', 'a move is posted');
    T::contains($c, 'JSON.stringify({ id: m.detail.id, parent: m.detail.parent, index: m.detail.index })', 'the body is {id, parent, index}');
    T::contains($c, '"parent=" + encodeURIComponent', 'the lazy request names its parent, encoded');
},

'the stylesheet block: at the end, both dark spellings, only roles for colour, the indent capped' => function () use ($sheet): void {
    $css = gkCssText();
    $at = strpos($css, '/* === 1.116.0 — tree === */');
    T::ok($at !== false, 'the block heading is there');
    T::ok(!preg_match('~/\* === ~', substr($css, (int) $at + 10)), 'it is the last block of the file');
    $s = $sheet();
    T::contains($s, '[data-gk-mode="dark"] .gk-tree-hit', 'dark, attribute spelling');
    T::contains($s, '.gk-dark .gk-tree-hit', 'dark, class spelling');
    T::contains($s, '@media (scripting: enabled)', 'a closed branch is closed from the first paint, but only where a script can open it');
    T::contains($s, '@media (scripting: none)', 'without script no dead controls');
    T::contains($s, 'touch-action: none', 'the handle takes the finger');
    T::contains($s, 'content: attr(data-gk-tree-level)', 'the level number past the cap');
    T::contains($s, '@media (max-width: 600px)', 'a smaller cap on a phone');
    preg_match_all('/(?<![-\w])color:\s*(#[0-9a-f]{3,8}|rgb)/i', $s, $lit);
    T::eq(count($lit[0]), 1, 'one literal text colour only: the search hit on dark (#fef9c3), like .gk-search-treffer');
},

'the skill documents every hook, event and call of the tree' => function () use ($doc): void {
    $d = $doc();
    T::ok((bool) preg_match('/^### Tree\b/m', $d), 'a "### Tree" section');
    foreach (['js/tree.js', 'gk:treeselect', 'gk:treetoggle', 'gk:treemove', 'gk:treemoved', 'GK.tree.expand(', 'GK.tree.collapse(',
              'GK.tree.select(', 'GK.tree.reload(', 'GK.tree.selected(', '{"id"', 'renderBranch(', 'data-gk-tree-pending'] as $w) {
        T::contains($d, $w, "GRIDKIT_SKILL.md never says $w");
    }
},

];
})();
