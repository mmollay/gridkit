<?php
use GridKit\Table;
use GridKit\Form;
use GridKit\Modal;
use GridKit\StatCards;
use GridKit\Sidebar;
use GridKit\FilterChips;
use GridKit\YearFilter;
use GridKit\TableHeader;
use GridKit\Header;
use GridKit\Button;
use GridKit\Theme;
use GridKit\Layout;
use GridKit\Lang;
?>
<!-- ===== CARDS (merged: cards + statcards) ===== -->
<div class="demo-section" data-section="cards">
    <h2>Cards <?= since('card') ?></h2>
    <p class="demo-intro">Card grid for structured content — responsive, with header, body and footer.</p>

    <h3 style="margin: 32px 0 16px;">Auto-Grid (responsive)</h3>
    <div class="gk-cards">
        <div class="gk-card"><div class="gk-card-header">Webdesign</div><div class="gk-card-body"><div class="gk-card-meta">Package S · from 1,200 €</div><div class="gk-card-description">Responsive design, CMS integration and SEO basics for small projects.</div></div><div class="gk-card-footer">5 projects active</div></div>
        <div class="gk-card"><div class="gk-card-header">Hosting</div><div class="gk-card-body"><div class="gk-card-meta">Managed · from 9.90 €/month</div><div class="gk-card-description">SSD storage, daily backups and SSL included. 99.9% uptime.</div></div><div class="gk-card-footer">12 servers active</div></div>
        <div class="gk-card"><div class="gk-card-header">SEO</div><div class="gk-card-body"><div class="gk-card-meta">Consulting · 95 €/h</div><div class="gk-card-description">Keyword analysis, on-page optimization and monthly reports.</div></div><div class="gk-card-footer">3 customers active</div></div>
    </div>

    <h3 style="margin: 32px 0 16px;">Fixed column count</h3>
    <div class="gk-cards gk-cards-4">
        <div class="gk-card"><div class="gk-card-body" style="text-align:center"><span class="material-icons" style="font-size:32px;color:var(--gk-primary);margin-bottom:8px;display:block" aria-hidden="true">speed</span><strong>Performance</strong><div class="gk-card-meta" style="margin-top:4px">99.9% Uptime</div></div></div>
        <div class="gk-card"><div class="gk-card-body" style="text-align:center"><span class="material-icons" style="font-size:32px;color:var(--gk-success);margin-bottom:8px;display:block" aria-hidden="true">security</span><strong>Security</strong><div class="gk-card-meta" style="margin-top:4px">SSL & Firewall</div></div></div>
        <div class="gk-card"><div class="gk-card-body" style="text-align:center"><span class="material-icons" style="font-size:32px;color:var(--gk-warning);margin-bottom:8px;display:block" aria-hidden="true">support_agent</span><strong>Support</strong><div class="gk-card-meta" style="margin-top:4px">Available 24/7</div></div></div>
        <div class="gk-card"><div class="gk-card-body" style="text-align:center"><span class="material-icons" style="font-size:32px;color:var(--gk-info);margin-bottom:8px;display:block" aria-hidden="true">backup</span><strong>Backups</strong><div class="gk-card-meta" style="margin-top:4px">Daily automatic</div></div></div>
    </div>

    <h3 style="margin: 32px 0 16px;">2 columns</h3>
    <div class="gk-cards gk-cards-2">
        <div class="gk-card"><div class="gk-card-header">Development</div><div class="gk-card-body"><div class="gk-card-description">Frontend and backend development with modern technologies.</div></div></div>
        <div class="gk-card"><div class="gk-card-header">Consulting</div><div class="gk-card-body"><div class="gk-card-description">Strategic IT consulting for digital transformation.</div></div></div>
    </div>

    <h3 style="margin: 32px 0 16px;">3 columns</h3>
    <div class="gk-cards gk-cards-3">
        <div class="gk-card"><div class="gk-card-body" style="text-align:center"><span class="material-icons" style="font-size:28px;color:var(--gk-primary);display:block;margin-bottom:6px" aria-hidden="true">web</span><strong>Web</strong></div></div>
        <div class="gk-card"><div class="gk-card-body" style="text-align:center"><span class="material-icons" style="font-size:28px;color:var(--gk-success);display:block;margin-bottom:6px" aria-hidden="true">phone_android</span><strong>Mobile</strong></div></div>
        <div class="gk-card"><div class="gk-card-body" style="text-align:center"><span class="material-icons" style="font-size:28px;color:var(--gk-warning);display:block;margin-bottom:6px" aria-hidden="true">cloud</span><strong>Cloud</strong></div></div>
    </div>

    <div class="demo-code"><pre>.gk-cards          // Auto-Grid (min 280px)
.gk-cards-2 / -3 / -4  // Fixed columns
.gk-card .gk-card-header / .gk-card-body / .gk-card-footer
.gk-card-link      // Clickable Card</pre></div>

    <hr style="border:none;border-top:1px solid var(--gk-outline-variant);margin:40px 0">

    <h3>Stat Cards <?= since('stat-cards') ?></h3>
    <div class="demo-pair">
    <div class="demo-card">
        <?php
        $stats = new StatCards('demo-stats');
        $stats->card('Customers', 248, ['format' => 'number', 'color' => 'blue'])
            ->card('Revenue', 84250.00, ['format' => 'currency', 'color' => 'green'])
            ->card('Orders', 64, ['format' => 'number', 'color' => 'orange'])
            ->card('Outstanding', 12480.00, ['format' => 'currency', 'color' => 'red'])
            ->render();
        ?>
    </div>
    <div class="demo-code"><pre>$stats = new StatCards('dashboard');
$stats->card('Customers', 248, ['format' => 'number', 'color' => 'blue'])
    ->card('Revenue', 84250.00, ['format' => 'currency', 'color' => 'green'])
    ->card('Outstanding', 12480.00, ['format' => 'currency', 'color' => 'red'])
    ->render();</pre></div>
    </div>

    <h3 class="demo-h3">Admin blocks</h3>
    <p class="demo-intro">The small parts an admin page used to style itself, as classes. Light and dark come from the roles.</p>

    <h3 class="demo-h3">Compact figures <?= since('stat-tiles') ?></h3>
        <?php
        showcase(function () {
            (new StatCards('demo-tiles'))
                ->compact()
                ->card('failing', 3, ['color' => 'danger'])
                ->card('open', 12, ['sub' => 'for 3 days'])
                ->card('done', 40)
                ->render();
        });
        ?>

    <h3 class="demo-h3">Setting <?= since('setting') ?> and answer tiles <?= since('choice') ?></h3>
        <?php
        showcase(function () {
            (new Form('demo-settings'))
                ->field('demo_login', 'Sign-in allowed', 'toggle', ['hint' => 'Takes effect at once, also on running sessions.', 'value' => 1])
                ->field('demo_lock', 'Lock the account', 'toggle', ['hint' => 'Signs the person out everywhere.', 'danger' => true])
                ->field('demo_next', 'What happens now?', 'choice', ['value' => 'fix', 'options' => [
                    'fix'   => 'Fix it now',
                    'later' => ['title' => 'Later', 'hint' => 'Stays on the list until next week.'],
                ]])
                ->render();
        });
        ?>

    <h3 class="demo-h3">Read-only value <?= since('field-static') ?></h3>
        <?php
        showcase(function () {
            echo <<<'HTML'
            <div class="gk-field-static">
              <span class="gk-field-static-label">Plan</span>
              <span class="gk-field-static-value">Everything</span>
              <span class="gk-field-hint">The superuser always has everything.</span>
            </div>
            HTML;
        });
        ?>

    <h3 class="demo-h3">Status dots <?= since('dot') ?> and avatar colours <?= since('avatar') ?></h3>
        <?php
        showcase(function () {
            echo <<<'HTML'
            <div class="gk-flex gk-flex-col gk-gap-xl">
              <div class="gk-flex gk-flex-wrap gk-gap-xl gk-items-center">
                <span><span class="gk-dot gk-dot-success" aria-hidden="true"></span> today 08:12</span>
                <span><span class="gk-dot gk-dot-warning" aria-hidden="true"></span> 3 days ago</span>
                <span><span class="gk-dot gk-dot-primary gk-dot-pulse" aria-hidden="true"></span> running</span>
                <span><span class="gk-dot gk-dot-outline" aria-hidden="true"></span> never</span>
              </div>
              <div class="gk-flex gk-gap-md">
                <span class="gk-avatar gk-avatar-sm gk-avatar-initials gk-avatar-tone-1" aria-hidden="true">AS</span>
                <span class="gk-avatar gk-avatar-sm gk-avatar-initials gk-avatar-tone-2" aria-hidden="true">TB</span>
                <span class="gk-avatar gk-avatar-sm gk-avatar-initials gk-avatar-tone-3" aria-hidden="true">LW</span>
                <span class="gk-avatar gk-avatar-sm gk-avatar-initials gk-avatar-tone-4" aria-hidden="true">PG</span>
                <span class="gk-avatar gk-avatar-sm gk-avatar-initials gk-avatar-tone-5" aria-hidden="true">MH</span>
              </div>
            </div>
            HTML;
        });
        ?>

    <h3 class="demo-h3">Chart colours <?= since('series') ?></h3>
        <?php
        showcase(function () {
            echo <<<'HTML'
            <div class="gk-flex gk-flex-wrap gk-gap-xl">
              <span><span class="gk-swatch gk-swatch-1" aria-hidden="true"></span> Model A 41 %</span>
              <span><span class="gk-swatch gk-swatch-2" aria-hidden="true"></span> Model B 27 %</span>
              <span><span class="gk-swatch gk-swatch-3" aria-hidden="true"></span> Model C 18 %</span>
              <span><span class="gk-swatch gk-swatch-4" aria-hidden="true"></span> Model D 9 %</span>
              <span><span class="gk-swatch gk-swatch-5" aria-hidden="true"></span> Other 5 %</span>
            </div>
            HTML;
        });
        ?>

    <h3 class="demo-h3">Actions in a message <?= since('message') ?>, a finger-sized button <?= since('button') ?></h3>
        <?php
        showcase(function () {
            echo <<<'HTML'
            <div class="gk-message gk-message-warning">
              <span>2 runs are stuck.</span>
              <div class="gk-message-actions"><button type="button" class="gk-btn gk-btn-outlined gk-btn-warning gk-btn-touch">Check now</button></div>
            </div>
            HTML;
        });
        ?>
    <h3 class="demo-h3" id="sortierbar">Sortable container <?= since('sortierbar') ?></h3>
    <p class="demo-intro">Entries a person puts in order by hand. Write the container with <code>data-gk-sortierbar</code> and give each entry a <code>data-gk-sort-id</code>; <code>js/sortierbar.js</code> adds a handle to every entry (from two entries on). Drag the handle with a mouse or a finger, or focus it and press <kbd>Space</kbd>, move with the arrow keys, press <kbd>Space</kbd> again to put it down or <kbd>Esc</kbd> to cancel. Links and buttons inside an entry keep working. With <code>data-gk-sortierbar-url</code> and <code>-csrf</code> the new order is posted as <code>{"ids":[…]}</code>; without a url the container only fires <code>gk:sortiert</code>, as here.</p>
        <?php
        showcase(function () {
            echo <<<'HTML'
            <div class="demo-sort-grid" data-gk-sortierbar aria-label="Websites, in the order you want them">
              <div data-gk-sort-id="12"><h4>survivaltraining.at</h4><p>Online, 14 pages</p></div>
              <div data-gk-sort-id="7"><h4>manifestor-akademie.net</h4><p>Draft, 6 pages</p></div>
              <div data-gk-sort-id="31"><h4>paneon.ssi.at</h4><p>Online, 9 pages</p></div>
              <div data-gk-sort-id="18" data-gk-sort-titel="Deike Pfeil"><h4>deike.at</h4><p>Online, 4 pages · <a href="#sortierbar">Open</a></p></div>
              <div data-gk-sort-id="22"><h4>lohr.example</h4><p>Draft, 2 pages</p></div>
            </div>
            <ul class="demo-sort-list" data-gk-sortierbar>
              <li data-gk-sort-id="a"><h4>Welcome mail</h4><p>Sent right after the sign-up</p></li>
              <li data-gk-sort-id="b"><h4>Reminder</h4><p>Two days later</p></li>
              <li data-gk-sort-id="c"><h4>Offer</h4><p>A week later</p></li>
            </ul>
            <script>
              document.addEventListener('gk:sortiert', function (e) { console.log('gk:sortiert', e.detail.ids); });
            </script>
            HTML;
        });
        ?>
</div>

