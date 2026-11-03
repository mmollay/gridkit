<?php
/**
 * The page ci/browser-tree.js drives (GridKit\Tree, 1.116.0): a CMS page tree that selects one page,
 * moves and loads on demand; a category tree with checkboxes inside a form; a tree without selection
 * that moves without a URL; one deep chain for the level marker. One self-contained HTML document —
 * both stylesheets and both scripts inlined — so it loads from file:// with no server. The lazy
 * branches (GET http://gk.test/children?parent=…) and the moves (POST http://gk.test/move) are
 * answered by the browser test with page.route(); the answers to the lazy requests are written here,
 * by Tree::renderBranch(), into window.GK_TEST_BRANCHES.
 *
 *     php ci/browser-tree-fixture.php > /tmp/f.html
 */

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use GridKit\{Lang, Tree};

Lang::set('en');

$root = dirname(__DIR__);
$capture = static function (callable $fn): string {
    ob_start();
    $fn();
    return (string) ob_get_clean();
};

// ── 1. The page tree of a CMS ──────────────────────────────────────────────
$pages = [
    ['id' => 'home', 'parent_id' => null, 'title' => 'Home', 'icon' => 'home', 'href' => '#page-home', 'draggable' => false],
    ['id' => 'about', 'parent_id' => null, 'title' => 'About us', 'icon' => 'folder', 'href' => '#page-about'],
    ['id' => 'team', 'parent_id' => 'about', 'title' => 'Team', 'href' => '#page-team'],
    ['id' => 'history', 'parent_id' => 'about', 'title' => 'History', 'href' => '#page-history', 'muted' => 'unpublished'],
    ['id' => 'services', 'parent_id' => null, 'title' => 'Services', 'icon' => 'folder', 'count' => 3],
    ['id' => 'web', 'parent_id' => 'services', 'title' => 'Web design', 'badge' => ['text' => 'New', 'color' => 'green']],
    ['id' => 'hosting', 'parent_id' => 'services', 'title' => 'Hosting'],
    ['id' => 'seo', 'parent_id' => 'services', 'title' => 'Search engine optimisation for small and medium-sized businesses in the region'],
    ['id' => 'cafe', 'parent_id' => 'services', 'title' => 'Café & catering'],
    ['id' => 'blog', 'parent_id' => null, 'title' => 'Blog', 'icon' => 'article', 'children' => 'lazy', 'count' => 12],
    ['id' => 'legal', 'parent_id' => null, 'title' => 'Legal', 'disabled' => true, 'badge' => 'Locked'],
    ['id' => 'imprint', 'parent_id' => 'legal', 'title' => 'Imprint'],
    ['id' => 'shop', 'parent_id' => null, 'title' => 'Shop', 'children' => 'lazy'],
];
$tree = static fn (): Tree => (new Tree('pages'))
    ->label('Pages')
    ->select('single')
    ->name('page')
    ->selected(['hosting'])
    ->moveUrl('http://gk.test/move', ['headers' => ['X-CSRF-Token' => 'tok-tree']])
    ->lazyUrl('http://gk.test/children')
    ->searchable()
    ->expandTo('team')
    ->actions(static fn (array $n): array => [
        ['icon' => 'edit', 'title' => 'Edit', 'href' => '#edit-' . $n['id']],
        ['icon' => 'delete', 'title' => 'Delete', 'onclick' => "window._deleted = '" . $n['id'] . "'", 'showIf' => $n['children'] === 0],
    ]);
$pagesHtml = $capture(fn () => $tree()->nodes($pages)->render());

// The lazy branches, as an endpoint would answer them: the same tree, its rows for one parent.
$branches = [
    'blog' => $capture(fn () => $tree()->nodes([
        ['id' => 'y2026', 'parent_id' => 'blog', 'title' => '2026', 'children' => 'lazy'],
        ['id' => 'y2025', 'parent_id' => 'blog', 'title' => '2025'],
        ['id' => 'post-a', 'parent_id' => 'y2025', 'title' => 'Our new office'],
        ['id' => 'post-b', 'parent_id' => 'y2025', 'title' => 'Ten years of web design'],
    ])->renderBranch('blog')),
    'y2026' => $capture(fn () => $tree()->nodes([
        ['id' => 'post-c', 'parent_id' => 'y2026', 'title' => 'Spring sale'],
        ['id' => 'post-d', 'parent_id' => 'y2026', 'title' => 'Hosting moves to green power'],
    ])->renderBranch('y2026')),
    'shop' => '',
    'cat-more' => $capture(fn () => (new Tree('cats'))->select('multi')->name('cats')->nodes([
        ['id' => 'c-vinyl', 'parent_id' => 'cat-more', 'title' => 'Vinyl'],
        ['id' => 'c-tapes', 'parent_id' => 'cat-more', 'title' => 'Tapes'],
    ])->renderBranch('cat-more')),
];

// ── 2. Categories with checkboxes, in a form ───────────────────────────────
$cats = [
    ['id' => 'c-books', 'title' => 'Books', 'children' => [
        ['id' => 'c-novels', 'title' => 'Novels'],
        ['id' => 'c-poetry', 'title' => 'Poetry'],
        ['id' => 'c-rare', 'title' => 'Rare editions', 'disabled' => true],
    ]],
    ['id' => 'c-music', 'title' => 'Music', 'expanded' => true, 'children' => [
        ['id' => 'c-cds', 'title' => 'CDs'],
        ['id' => 'cat-more', 'title' => 'More formats', 'children' => 'lazy'],
    ]],
    ['id' => 'c-games', 'title' => 'Games'],
];
$catsHtml = $capture(fn () => (new Tree('cats'))->label('Categories')->select('multi')->name('cats')
    ->selected(['c-books', 'c-vinyl'])->lazyUrl('http://gk.test/children')->nodes($cats, ['label' => 'title'])->render());

// ── 3. No selection, movable without a URL, nothing remembered ─────────────
$plainHtml = $capture(fn () => (new Tree('plain'))->label('Folders')->movable()->remember(false)->toolbar(false)
    ->node('inbox', 'Inbox', null, ['icon' => 'inbox', 'expanded' => true])
    ->node('work', 'Work', 'inbox')
    ->node('private', 'Private', 'inbox')
    ->node('archive', 'Archive', null, ['icon' => 'archive'])
    ->node('a2025', '2025', 'archive')
    ->render());

// ── 4. A deep chain: the indent stops, the level number takes over ──────────
$deep = [];
for ($i = 1; $i <= 11; $i++) {
    $deep[] = ['id' => "d$i", 'parent_id' => $i === 1 ? null : 'd' . ($i - 1), 'title' => "Level $i", 'expanded' => true];
}
$deepHtml = $capture(fn () => (new Tree('deep'))->label('Deep')->remember(false)->nodes($deep)->expandTo('d11')->render());

echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
   . '<meta name="viewport" content="width=device-width, initial-scale=1"><title>Tree</title>'
   . '<style>' . file_get_contents($root . '/css/gridkit.css') . '</style>'
   . '<style>' . file_get_contents($root . '/css/themes.css') . '</style>'
   . '<style>body{background:var(--gk-surface);color:var(--gk-on-surface);margin:0;padding:12px;font-family:var(--gk-font)}'
   . 'h3{font-size:13px;margin:18px 0 6px}section{max-width:640px}</style>'
   . '</head><body class="gk-root">' . Lang::jsConfig()
   . '<button id="before" type="button">before</button>'
   . '<section id="s-pages"><h3>Pages</h3>' . $pagesHtml . '</section>'
   . '<section id="s-cats"><h3>Categories</h3><form id="cats-form" method="get" action="http://gk.test/submit">' . $catsHtml
   . '<button type="submit" id="cats-submit">Save</button></form></section>'
   . '<section id="s-plain"><h3>Folders</h3>' . $plainHtml . '</section>'
   . '<section id="s-deep"><h3>Deep</h3>' . $deepHtml . '</section>'
   . '<button id="after" type="button">after</button>'
   . '<script>window.GK_TEST_BRANCHES = ' . json_encode($branches, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . ';</script>'
   . '<script>' . file_get_contents($root . '/js/gridkit.js') . '</script>'
   . '<script>' . file_get_contents($root . '/js/tree.js') . '</script>'
   . '</body></html>';
