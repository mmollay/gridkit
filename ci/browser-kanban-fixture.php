<?php
/**
 * The page ci/browser-kanban.js drives (GridKit\Kanban, 1.124.0): a support board that saves to a URL,
 * refuses a move into a full column and keeps a card that may not move; a compact board that moves
 * without a URL and only warns at its limit; a board of eight columns that is wider than any screen.
 * One self-contained HTML document — both stylesheets and the scripts inlined — so it loads from
 * file:// with no server. The moves (POST http://gk.test/move, JSON with the script, a plain form
 * without it) are answered by the browser test with page.route().
 *
 *     php ci/browser-kanban-fixture.php            > /tmp/f.html   # with js/kanban.js
 *     php ci/browser-kanban-fixture.php old-script > /tmp/o.html   # gridkit.js only: a page whose copy has no kanban.js
 */

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use GridKit\{Kanban, Lang};

Lang::set('en');

$root = dirname(__DIR__);
$withKanbanJs = ($argv[1] ?? '') !== 'old-script';
$capture = static function (callable $fn): string {
    ob_start();
    $fn();
    return (string) ob_get_clean();
};

// ── 1. Support tickets: saved to a URL, limits that refuse ─────────────────
$tickets = [
    ['id' => 'T-1', 'subject' => 'Printer on floor 2 offline', 'status' => 'new', 'text' => 'Since this morning nobody can print; the queue shows 14 jobs.',
     'badge' => ['High', 'red'], 'assignee' => 'Anna Huber', 'due' => '2026-11-02', 'labels' => ['Hardware', ['Office', 'blue']], 'href' => '#ticket-T-1', 'count' => 3],
    ['id' => 'T-2', 'subject' => 'New laptop for Jonas', 'status' => 'new', 'assignee' => 'Ben Ortner', 'due' => '2026-11-04', 'labels' => ['Purchase']],
    ['id' => 'T-3', 'subject' => 'VPN drops every hour', 'status' => 'new', 'badge' => 'Normal', 'due' => '2026-11-20', 'href' => '#ticket-T-3'],
    ['id' => 'T-4', 'subject' => 'Mail signature for the sales team', 'status' => 'doing', 'assignee' => 'Clara Wimmer', 'labels' => [['Mail', 'green']]],
    ['id' => 'T-5', 'subject' => 'Backup restore test', 'status' => 'doing', 'due' => '2027-01-15', 'badge' => ['Audit', 'orange']],
    ['id' => 'T-6', 'subject' => 'Password policy (locked by the auditor)', 'status' => 'review', 'draggable' => false, 'badge' => 'Locked'],
    ['id' => 'T-7', 'subject' => 'Wi-Fi in the meeting room', 'status' => 'done', 'assignee' => 'Anna Huber'],
];
$ticketsHtml = $capture(fn () => (new Kanban('tickets'))
    ->label('Support tickets')
    ->today('2026-11-04')
    ->column('new', 'New', ['color' => 'blue', 'description' => 'Not looked at yet'])
    ->column('doing', 'In progress', ['color' => 'orange', 'limit' => 2])
    ->column('review', 'Review', ['limit' => 3])
    ->column('done', 'Done', ['color' => 'green'])
    ->cards($tickets, ['title' => 'subject', 'column' => 'status'])
    ->moveUrl('http://gk.test/move', ['headers' => ['X-CSRF-Token' => 'tok-kanban'], 'fields' => ['_token' => 'tok-kanban']])
    ->wipLimits('block')
    ->addUrl('http://gk.test/new?status={column}')
    ->render());

// ── 2. A compact board, moved without a URL, a limit that only warns ───────
$plainHtml = $capture(fn () => (new Kanban('plain'))
    ->label('Campaigns')
    ->compact()
    ->movable()
    ->column('idea', 'Ideas')
    ->column('live', 'Live', ['limit' => 1, 'color' => 'green'])
    ->column('ended', 'Ended', ['color' => 'gray'])
    ->card('c1', 'Spring newsletter', 'idea', ['text' => 'Left out: the board is compact.'])
    ->card('c2', 'Black Friday', 'idea')
    ->card('c3', 'Autumn sale', 'live')
    ->card('c4', 'Summer sale', 'ended')
    ->empty('Nothing here yet')
    ->render());

// ── 3. Eight columns: wider than any screen, the board scrolls, not the page ─
$wide = new Kanban('wide');
$wide->label('Orders')->movable();
foreach (['received', 'paid', 'picked', 'packed', 'shipped', 'delivered', 'returned', 'archived'] as $i => $key) {
    $wide->column($key, ucfirst($key));
    $wide->card("o$i", 'Order 10' . (40 + $i), $key, ['text' => 'Customer ' . ($i + 1)]);
}
$wideHtml = $capture(fn () => $wide->render());

echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
   . '<meta name="viewport" content="width=device-width, initial-scale=1"><title>Kanban</title>'
   . '<style>' . file_get_contents($root . '/css/gridkit.css') . '</style>'
   . '<style>' . file_get_contents($root . '/css/themes.css') . '</style>'
   . '<style>body{background:var(--gk-surface);color:var(--gk-on-surface);margin:0;padding:12px;font-family:var(--gk-font)}'
   . 'h2{font-size:15px;margin:18px 0 8px}section.demo{max-width:1240px}</style>'
   . '</head><body class="gk-root">' . Lang::jsConfig()
   . '<button id="before" type="button">before</button>'
   . '<section class="demo" id="s-tickets"><h2>Tickets</h2>' . $ticketsHtml . '</section>'
   . '<section class="demo" id="s-plain"><h2>Campaigns</h2>' . $plainHtml . '</section>'
   . '<section class="demo" id="s-wide"><h2>Orders</h2>' . $wideHtml . '</section>'
   . '<button id="after" type="button">after</button>'
   . '<div style="height:600px"></div>'
   . '<script>' . file_get_contents($root . '/js/gridkit.js') . '</script>'
   . ($withKanbanJs ? '<script>' . file_get_contents($root . '/js/kanban.js') . '</script>' : '')
   . '</body></html>';
