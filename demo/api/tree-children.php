<?php
/**
 * The lazy branches of the demo's page tree (GridKit\Tree::lazyUrl(), 1.116.0).
 *
 * js/tree.js asks GET tree-children.php?parent=<id> the first time a node with
 * 'children' => 'lazy' is opened, and puts the <li> of the answer into the node.
 * This file is that answer: the rows of one parent, written by renderBranch() on
 * a tree configured as on the page — same id, selection, name and buttons — so the
 * children look and post like their neighbours. A real endpoint reads the rows from
 * its database; nothing else changes.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../autoload.php';

use GridKit\{Lang, Tree};

$lang = $_GET['lang'] ?? $_COOKIE['gk_lang'] ?? 'en';
Lang::set(in_array($lang, ['en', 'de'], true) ? $lang : 'en');

$parent = is_string($_GET['parent'] ?? null) ? $_GET['parent'] : '';

// A moment of latency, so the demo shows the ring that turns in the chevron.
usleep(450000);

// One branch fails on purpose: the node closes again, its row says so and offers "Try again".
if ($parent === 'archive') {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'The archive server is asleep (on purpose, in this demo).';
    exit;
}

$rows = [
    'blog' => [
        ['id' => 'blog-2026', 'title' => '2026', 'icon' => 'folder', 'children' => 'lazy', 'count' => 3],
        ['id' => 'blog-2025', 'title' => '2025', 'icon' => 'folder', 'children' => 'lazy', 'count' => 2],
    ],
    'blog-2026' => [
        ['id' => 'post-spring', 'title' => 'Spring sale: 20% on all hosting plans', 'icon' => 'article'],
        ['id' => 'post-office', 'title' => 'Our new office in Graz', 'icon' => 'article'],
        ['id' => 'post-green', 'title' => 'Hosting moves to green power — what changes for you and your websites', 'icon' => 'article', 'muted' => 'draft'],
    ],
    'blog-2025' => [
        ['id' => 'post-ten', 'title' => 'Ten years of web design', 'icon' => 'article'],
        ['id' => 'post-seo', 'title' => 'Five SEO myths', 'icon' => 'article'],
    ],
][$parent] ?? [];

foreach ($rows as &$row) {
    $row['parent_id'] = $parent;
    $row['href'] = '#tree-page-' . $row['id'];
}
unset($row);

header('Content-Type: text/html; charset=utf-8');
(new Tree('demo-pages'))
    ->select('single')->name('page')
    ->actions(static fn (array $n): array => [
        ['icon' => 'edit', 'title' => 'Edit', 'href' => '#tree-edit-' . $n['id']],
        ['icon' => 'add', 'title' => 'Add a page below', 'href' => '#tree-add-' . $n['id']],
    ])
    ->nodes($rows)
    ->renderBranch($parent);
