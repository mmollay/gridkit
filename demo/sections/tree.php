<?php
use GridKit\Tree;
?>
<!-- ===== TREE (1.116.0) ===== -->
<div class="demo-section" data-section="tree">
    <h2>Tree <?= since('tree') ?></h2>
    <p class="demo-intro">Page trees, categories, folders. <code>GridKit\Tree</code> writes a nested list that works without any script — a link is followed, a ticked box is posted. <code>js/tree.js</code> makes it a tree view: the arrow keys, <kbd>Home</kbd> and <kbd>End</kbd>, <kbd>*</kbd>, type-ahead, open and closed remembered per tree, a filter, children loaded when a node is first opened, and moving by drag and drop or by keyboard, saved to a URL and given back when the save fails.</p>

    <h3 class="demo-h3" id="tree-pages">A page tree: links, one page selected, moving, lazy branches</h3>
    <p class="demo-intro">Tab into the tree: <kbd>↑</kbd> <kbd>↓</kbd> walk, <kbd>→</kbd> opens or goes in, <kbd>←</kbd> closes or goes up, a letter jumps, <kbd>Enter</kbd> follows the link, <kbd>Space</kbd> selects. <kbd>Tab</kbd> from a page reaches its buttons and its handle: <kbd>Space</kbd> lifts the page, the arrows move it (<kbd>→</kbd> into the page above, <kbd>←</kbd> out of its parent), <kbd>Space</kbd> puts it down, <kbd>Esc</kbd> cancels — or drag the handle. Every move is posted to <code>api/tree-move.php</code>, which refuses one on purpose: nothing takes Home's place at the top, so the page goes back and a toast says why. <em>Blog</em> loads its years and posts from <code>api/tree-children.php</code>; <em>Archive</em> fails on purpose; <em>Legal</em> is disabled and takes no pages.</p>
        <?php
        showcase(function () {
            $pages = [
                ['id' => 'home', 'parent_id' => null, 'title' => 'Home', 'icon' => 'home', 'draggable' => false],
                ['id' => 'about', 'parent_id' => null, 'title' => 'About us', 'icon' => 'folder'],
                ['id' => 'team', 'parent_id' => 'about', 'title' => 'Team', 'icon' => 'description'],
                ['id' => 'history', 'parent_id' => 'about', 'title' => 'History', 'icon' => 'description', 'muted' => 'unpublished'],
                ['id' => 'services', 'parent_id' => null, 'title' => 'Services', 'icon' => 'folder', 'expanded' => true],
                ['id' => 'web', 'parent_id' => 'services', 'title' => 'Web design', 'icon' => 'description', 'badge' => ['text' => 'New', 'color' => 'green']],
                ['id' => 'hosting', 'parent_id' => 'services', 'title' => 'Hosting', 'icon' => 'description'],
                ['id' => 'seo', 'parent_id' => 'services', 'title' => 'Search engine optimisation for small and medium-sized businesses', 'icon' => 'description'],
                ['id' => 'blog', 'parent_id' => null, 'title' => 'Blog', 'icon' => 'folder', 'children' => 'lazy', 'count' => 5],
                ['id' => 'archive', 'parent_id' => null, 'title' => 'Archive', 'icon' => 'inventory_2', 'children' => 'lazy'],
                ['id' => 'contact', 'parent_id' => null, 'title' => 'Contact', 'icon' => 'description'],
                ['id' => 'legal', 'parent_id' => null, 'title' => 'Legal', 'icon' => 'gavel', 'disabled' => true, 'badge' => 'Locked'],
                ['id' => 'imprint', 'parent_id' => 'legal', 'title' => 'Imprint', 'icon' => 'description'],
                ['id' => 'privacy', 'parent_id' => 'legal', 'title' => 'Privacy', 'icon' => 'description'],
            ];
            foreach ($pages as &$page) $page['href'] = '#tree-page-' . $page['id'];
            unset($page);

            (new Tree('demo-pages'))
                ->label('Pages')
                ->nodes($pages)
                ->select('single')->name('page')->selected(['hosting'])
                ->expandTo('team')
                ->searchable()
                ->lazyUrl('api/tree-children.php')
                ->moveUrl('api/tree-move.php', ['headers' => ['X-CSRF-Token' => 'demo-token']])
                ->actions(fn (array $n): array => [
                    ['icon' => 'edit', 'title' => 'Edit', 'href' => '#tree-edit-' . $n['id']],
                    ['icon' => 'add', 'title' => 'Add a page below', 'href' => '#tree-add-' . $n['id']],
                ])
                ->render();
        });
        ?>
    <pre class="demo-log" id="demo-tree-log" aria-label="Event log">Events appear here.</pre>
    <details class="demo-showcase-code">
        <summary><span class="material-icons" aria-hidden="true">code</span><span>The two endpoints: api/tree-children.php and api/tree-move.php</span></summary>
        <div class="demo-showcase-src">
            <pre><code><?= htmlspecialchars((string) file_get_contents(__DIR__ . '/../api/tree-children.php'), ENT_QUOTES, 'UTF-8') ?></code></pre>
            <pre><code><?= htmlspecialchars((string) file_get_contents(__DIR__ . '/../api/tree-move.php'), ENT_QUOTES, 'UTF-8') ?></code></pre>
        </div>
    </details>

    <h3 class="demo-h3" id="tree-categories">Categories: several, with tri-state parents, in a form</h3>
    <p class="demo-intro">With <code>select('multi')</code> every node has a box. A ticked parent ticks its branch (but not a disabled node in it); a parent with some of its children ticked is mixed. The form posts <code>categories[]=id</code> for every ticked node — with or without the script. <em>Vinyl</em> starts selected.</p>
        <?php
        showcase(function () {
            echo '<form id="demo-tree-form" action="#tree" method="post">';
            (new Tree('demo-cats'))
                ->label('Categories')
                ->select('multi')->name('categories')->selected(['books', 'vinyl'])
                ->searchable()
                ->nodes([
                    ['id' => 'books', 'title' => 'Books', 'children' => [
                        ['id' => 'novels', 'title' => 'Novels', 'count' => 214],
                        ['id' => 'poetry', 'title' => 'Poetry', 'count' => 38],
                        ['id' => 'rare', 'title' => 'Rare editions', 'disabled' => true, 'badge' => ['text' => 'Sold out', 'color' => 'red']],
                    ]],
                    ['id' => 'music', 'title' => 'Music', 'expanded' => true, 'children' => [
                        ['id' => 'cds', 'title' => 'CDs', 'count' => 120],
                        ['id' => 'vinyl', 'title' => 'Vinyl', 'count' => 64],
                        ['id' => 'tapes', 'title' => 'Tapes', 'count' => 9, 'muted' => 'hidden in the shop'],
                    ]],
                    ['id' => 'games', 'title' => 'Games', 'children' => [
                        ['id' => 'board', 'title' => 'Board games'],
                        ['id' => 'puzzles', 'title' => 'Puzzles'],
                    ]],
                ])
                ->render();
            echo '<p><button type="submit" class="gk-btn gk-btn-filled gk-btn-primary">Save categories</button></p></form>';
        });
        ?>

    <h3 class="demo-h3" id="tree-folders">Folders: moved without a URL — the page decides</h3>
    <p class="demo-intro"><code>movable()</code> without <code>moveUrl()</code>: the tree fires the cancelable <code>gk:treemove</code> and the page saves (or not) on its own. This page refuses to put anything into <em>Trash</em> by dragging. The chain under <em>Projects</em> is ten levels deep: the indent stops after eight (five on a phone) and a small number shows the level.</p>
        <?php
        showcase(function () {
            (new Tree('demo-folders'))
                ->label('Folders')
                ->movable()
                ->toolbar(false)
                ->node('inbox', 'Inbox', null, ['icon' => 'inbox', 'count' => 12, 'expanded' => true])
                ->node('clients', 'Clients', 'inbox', ['icon' => 'folder'])
                ->node('offers', 'Offers', 'inbox', ['icon' => 'folder'])
                ->node('projects', 'Projects', null, ['icon' => 'folder'])
                ->node('relaunch', 'Website relaunch', 'projects', ['icon' => 'folder'])
                ->node('design', 'Design', 'relaunch', ['icon' => 'folder'])
                ->node('drafts', 'Drafts', 'design', ['icon' => 'folder'])
                ->node('round2', 'Round 2', 'drafts', ['icon' => 'folder'])
                ->node('mobile', 'Mobile', 'round2', ['icon' => 'folder'])
                ->node('header', 'Header', 'mobile', ['icon' => 'folder'])
                ->node('logo', 'Logo', 'header', ['icon' => 'folder'])
                ->node('variants', 'Variants', 'logo', ['icon' => 'folder'])
                ->node('final', 'Final', 'variants', ['icon' => 'image'])
                ->node('trash', 'Trash', null, ['icon' => 'delete', 'draggable' => false])
                ->expandTo('final')
                ->render();
        });
        ?>
</div>
<script>
/* The tree demos: what the page hears, what the form would post, and the page's own rule for the folders. */
(function () {
    var log = document.getElementById('demo-tree-log');
    var section = document.querySelector('[data-section="tree"]');
    if (!log || !section) return;
    var lines = [];
    function say(text) {
        lines.unshift(text);
        log.textContent = lines.slice(0, 8).join('\n');
    }
    ['gk:treeselect', 'gk:treetoggle', 'gk:treemove', 'gk:treemoved'].forEach(function (name) {
        section.addEventListener(name, function (e) {
            var d = Object.assign({}, e.detail);
            say(name + ' ' + JSON.stringify(d));
        });
    });
    // The links of the demo go nowhere: the click is written down instead.
    section.addEventListener('click', function (e) {
        var a = e.target.closest && e.target.closest('.gk-tree a[href^="#tree-"]');
        if (!a) return;
        e.preventDefault();
        say('link → ' + a.getAttribute('href').slice(1));
    });
    document.getElementById('demo-tree-form').addEventListener('submit', function (e) {
        e.preventDefault();
        var sent = [];
        new FormData(e.target).forEach(function (value, key) { sent.push(key + '=' + value); });
        say('POST ' + (sent.join('&') || '(nothing ticked)'));
    });
    section.addEventListener('gk:treemove', function (e) {
        if (e.detail.tree === 'demo-folders' && e.detail.parent === 'trash') {
            e.preventDefault();
            if (window.GK && GK.toast) GK.toast.warning('Nothing goes into Trash by dragging — this page said no.');
        }
    });
})();
</script>
