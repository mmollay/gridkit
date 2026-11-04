<?php
use GridKit\Kanban;
?>
<!-- ===== KANBAN (1.124.0) ===== -->
<div class="demo-section" data-section="kanban">
    <h2>Kanban <?= since('kanban') ?></h2>
    <p class="demo-intro">Columns with cards for tasks, orders, campaigns and support tickets. <code>GridKit\Kanban</code> writes a labelled region of columns — a heading, a count and a list of cards — that works without any script: every card is in view, and a card that may move has a small <em>Move</em> form that posts. <code>js/kanban.js</code> adds dragging with the mouse or a finger, moving with the keyboard, counts and limits that follow every move, and a save to a URL that gives the card back when the server says no.</p>

    <?php if (isset($_GET['kanban'])): ?>
    <p class="demo-intro" role="status"><strong><?= $_GET['kanban'] === 'saved' ? 'The "Move to" form was posted without a script and saved' : 'The "Move to" form was posted without a script and refused' ?></strong> — the endpoint answered a form with a redirect back here. The demo stores nothing, so the board shows what it showed before.</p>
    <?php endif; ?>
    <h3 class="demo-h3" id="kanban-tickets">Support tickets: saved to a URL, limits that refuse</h3>
    <p class="demo-intro">Drag a card by its handle (or, with a mouse, by the card itself) into another column; held at the edge, the board scrolls. With the keyboard: <kbd>Tab</kbd> to a handle, <kbd>Space</kbd> lifts the card, <kbd>↑</kbd> <kbd>↓</kbd> move it in its column, <kbd>←</kbd> <kbd>→</kbd> to the next column, <kbd>Home</kbd> and <kbd>End</kbd> to the ends, <kbd>Space</kbd> puts it down, <kbd>Esc</kbd> cancels — every step is spoken. <em>In progress</em> takes three cards (<code>wipLimits('block')</code>): a fourth is refused. Every move is posted to <code>api/kanban-move.php</code>, which refuses one on purpose: <em>Refund for order 1043</em> cannot go to <em>Done</em>, so it goes back and a toast says why. <em>Password policy</em> is locked (<code>'draggable' =&gt; false</code>).</p>
        <?php
        showcase(function () {
            $tickets = [
                ['id' => 'T-101', 'subject' => 'Printer on floor 2 offline', 'status' => 'new', 'priority' => ['High', 'red'],
                 'body' => 'Since this morning nobody can print; the queue shows 14 jobs.', 'owner' => 'Anna Huber',
                 'due' => date('Y-m-d', strtotime('-2 days')), 'tags' => ['Hardware', ['Office', 'blue']], 'comments' => 3],
                ['id' => 'T-102', 'subject' => 'New laptop for Jonas', 'status' => 'new', 'owner' => 'Ben Ortner',
                 'due' => date('Y-m-d'), 'tags' => ['Purchase']],
                ['id' => 'T-103', 'subject' => 'VPN drops every hour', 'status' => 'new', 'priority' => 'Normal',
                 'due' => date('Y-m-d', strtotime('+9 days'))],
                ['id' => 'T-104', 'subject' => 'Refund for order 1043', 'status' => 'doing', 'priority' => ['Billing', 'orange'], 'owner' => 'Clara Wimmer'],
                ['id' => 'T-105', 'subject' => 'Mail signature for the sales team', 'status' => 'doing', 'tags' => [['Mail', 'green']]],
                ['id' => 'T-106', 'subject' => 'Backup restore test', 'status' => 'doing', 'owner' => 'Ben Ortner', 'comments' => 7],
                ['id' => 'T-107', 'subject' => 'Password policy', 'status' => 'review', 'priority' => 'Locked', 'locked' => true,
                 'body' => 'Waits for the auditor; it stays where it is.'],
                ['id' => 'T-108', 'subject' => 'Wi-Fi in the meeting room', 'status' => 'done', 'owner' => 'Anna Huber'],
            ];
            foreach ($tickets as &$t) {
                $t['href'] = '#kanban-ticket-' . $t['id'];
                $t['draggable'] = empty($t['locked']);
            }
            unset($t);

            (new Kanban('demo-tickets'))
                ->label('Support tickets')
                ->column('new', 'New', ['color' => 'blue', 'description' => 'Not looked at yet'])
                ->column('doing', 'In progress', ['color' => 'orange', 'limit' => 3])
                ->column('review', 'Review', ['limit' => 4])
                ->column('done', 'Done', ['color' => 'green'])
                ->cards($tickets, ['title' => 'subject', 'column' => 'status', 'text' => 'body', 'badge' => 'priority',
                    'assignee' => 'owner', 'labels' => 'tags', 'count' => 'comments'])
                ->moveUrl('api/kanban-move.php', ['headers' => ['X-CSRF-Token' => 'demo-token'], 'fields' => ['_token' => 'demo-token']])
                ->wipLimits('block')
                ->addUrl('#kanban-new-{column}')
                ->render();
        });
        ?>
    <pre class="demo-log" id="demo-kanban-log" aria-label="Event log">Events appear here.</pre>
    <details class="demo-showcase-code">
        <summary><span class="material-icons" aria-hidden="true">code</span><span>The endpoint: api/kanban-move.php</span></summary>
        <div class="demo-showcase-src">
            <pre><code><?= htmlspecialchars((string) file_get_contents(__DIR__ . '/../api/kanban-move.php'), ENT_QUOTES, 'UTF-8') ?></code></pre>
        </div>
    </details>

    <h3 class="demo-h3" id="kanban-campaigns">Campaigns: compact, moved without a URL, a limit that warns</h3>
    <p class="demo-intro"><code>movable()</code> without <code>moveUrl()</code>: the board fires the cancelable <code>gk:kanbanmove</code> and the page decides. <code>compact()</code> leaves a card's text out and sets the cards closer. <em>Live</em> has a limit of one and the default <code>'warn'</code>: it holds two campaigns and says it is over its limit — move one on, and the mark goes. This page refuses to end a campaign that has not run — try <em>Black Friday</em> to <em>Ended</em>.</p>
        <?php
        showcase(function () {
            (new Kanban('demo-campaigns'))
                ->label('Campaigns')
                ->compact()
                ->movable()
                ->column('idea', 'Ideas')
                ->column('planned', 'Planned', ['color' => 'blue'])
                ->column('live', 'Live', ['color' => 'green', 'limit' => 1])
                ->column('paused', 'Paused', ['color' => 'red'])
                ->column('ended', 'Ended', ['color' => 'gray'])
                ->card('c-spring', 'Spring newsletter', 'idea', ['labels' => ['Mail']])
                ->card('c-bf', 'Black Friday', 'planned', ['due' => date('Y') . '-11-27', 'labels' => [['Shop', 'orange']]])
                ->card('c-xmas', 'Christmas vouchers', 'planned', ['labels' => ['Shop']])
                ->card('c-autumn', 'Autumn sale', 'live', ['assignee' => 'Clara Wimmer'])
                ->card('c-loyalty', 'Loyalty points', 'live', ['labels' => [['App', 'blue']]])
                ->card('c-summer', 'Summer sale', 'ended')
                ->empty('No campaigns')
                ->render();
        });
        ?>
</div>
<script>
/* The kanban demos: what the page hears, and the page's own rule for the campaigns. */
(function () {
    var log = document.getElementById('demo-kanban-log');
    var section = document.querySelector('[data-section="kanban"]');
    if (!log || !section) return;
    var lines = [];
    function say(text) {
        lines.unshift(text);
        log.textContent = lines.slice(0, 8).join('\n');
    }
    ['gk:kanbanmove', 'gk:kanbanmoved'].forEach(function (name) {
        section.addEventListener(name, function (e) { say(name + ' ' + JSON.stringify(e.detail)); });
    });
    // The links of the demo go nowhere: the click is written down instead.
    section.addEventListener('click', function (e) {
        var a = e.target.closest && e.target.closest('.gk-kanban a[href^="#kanban-"]');
        if (!a) return;
        e.preventDefault();
        say('link → ' + a.getAttribute('href').slice(1));
    });
    section.addEventListener('gk:kanbanmove', function (e) {
        if (e.detail.board === 'demo-campaigns' && e.detail.to === 'ended' && e.detail.from !== 'live' && e.detail.from !== 'paused') {
            e.preventDefault();
            if (window.GK && GK.toast) GK.toast.warning('Only a campaign that ran can end — this page said no.');
        }
    });
})();
</script>
