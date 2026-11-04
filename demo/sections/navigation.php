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
use GridKit\PageSize;
use GridKit\Pagination;
?>
<!-- ===== NAVIGATION (merged: filterchips + yearfilter + formatter) ===== -->
<div class="demo-section" data-section="navigation">
    <h2>Navigation & Filter</h2>

    <h3 style="margin: 32px 0 16px;">Accordion <?= since('accordion') ?></h3>
    <p class="demo-intro">Collapsible content areas &mdash; individually or as a group.</p>

    <div class="gk-accordion" data-gk-single>
        <div class="gk-accordion-item open">
            <button class="gk-accordion-trigger">
                <span>What is GridKit?</span>
                <span class="material-icons" aria-hidden="true">expand_more</span>
            </button>
            <div class="gk-accordion-content">
                <div class="gk-accordion-body">GridKit is a lightweight PHP/CSS/JS framework for admin dashboards and internal tools. Zero dependencies, M3-inspired.</div>
            </div>
        </div>
        <div class="gk-accordion-item">
            <button class="gk-accordion-trigger">
                <span>What components are available?</span>
                <span class="material-icons" aria-hidden="true">expand_more</span>
            </button>
            <div class="gk-accordion-content">
                <div class="gk-accordion-body">Table, Form, Modal, Cards, StatCards, Sidebar, Header, Tabs, Accordion, Breadcrumb, Toast, Confirm and more. All with Light &amp; Dark Mode.</div>
            </div>
        </div>
        <div class="gk-accordion-item">
            <button class="gk-accordion-trigger">
                <span>Do I need npm or build tools?</span>
                <span class="material-icons" aria-hidden="true">expand_more</span>
            </button>
            <div class="gk-accordion-content">
                <div class="gk-accordion-body">No. GridKit has no dependencies &mdash; one CSS file, one JS file, done. Just include and get started.</div>
            </div>
        </div>
    </div>

    <div class="demo-code"><pre>&lt;div class="gk-accordion" data-gk-single&gt;
    &lt;div class="gk-accordion-item open"&gt;
        &lt;button class="gk-accordion-trigger"&gt;
            &lt;span&gt;Question?&lt;/span&gt;
            &lt;span class="material-icons"&gt;expand_more&lt;/span&gt;
        &lt;/button&gt;
        &lt;div class="gk-accordion-content"&gt;
            &lt;div class="gk-accordion-body"&gt;Answer...&lt;/div&gt;
        &lt;/div&gt;
    &lt;/div&gt;
&lt;/div&gt;

// data-gk-single: only one item open at a time
// .open: item open by default</pre></div>

    <hr style="border:none;border-top:1px solid var(--gk-outline-variant);margin:40px 0">

    <h3 style="margin: 32px 0 16px;">Avatar <?= since('avatar') ?></h3>
    <p class="demo-intro">Profile pictures with initials fallback, status dot and groups.</p>

    <div class="gk-segment">
        <div class="gk-segment-header">Sizes</div>
        <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
            <div class="gk-avatar gk-avatar-xs">XS</div>
            <div class="gk-avatar gk-avatar-sm">SM</div>
            <div class="gk-avatar gk-avatar-md">MD</div>
            <div class="gk-avatar gk-avatar-lg">LG</div>
            <div class="gk-avatar gk-avatar-xl">XL</div>
            <div class="gk-avatar gk-avatar-lg"><img src="<?= ph(112, 112, 12, 'JD') ?>" alt=""></div>
            <div class="gk-avatar gk-avatar-lg"><img src="<?= ph(112, 112, 32, 'MK') ?>" alt=""></div>
        </div>
    </div>

    <div class="gk-segment" style="margin-top:12px">
        <div class="gk-segment-header">Status</div>
        <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
            <div class="gk-avatar gk-avatar-lg"><img src="<?= ph(112, 112, 12, 'JD') ?>" alt=""><span class="gk-avatar-status online"></span></div>
            <div class="gk-avatar gk-avatar-lg"><img src="<?= ph(112, 112, 32, 'MK') ?>" alt=""><span class="gk-avatar-status away"></span></div>
            <div class="gk-avatar gk-avatar-lg"><img src="<?= ph(112, 112, 44, 'AS') ?>" alt=""><span class="gk-avatar-status busy"></span></div>
            <div class="gk-avatar gk-avatar-lg">MM<span class="gk-avatar-status offline"></span></div>
        </div>
    </div>

    <div class="gk-segment" style="margin-top:12px">
        <div class="gk-segment-header">Group (stacked)</div>
        <div class="gk-avatar-group">
            <div class="gk-avatar gk-avatar-md"><img src="<?= ph(80, 80, 12, 'JD') ?>" alt=""></div>
            <div class="gk-avatar gk-avatar-md"><img src="<?= ph(80, 80, 32, 'MK') ?>" alt=""></div>
            <div class="gk-avatar gk-avatar-md"><img src="<?= ph(80, 80, 44, 'AS') ?>" alt=""></div>
            <div class="gk-avatar gk-avatar-md"><img src="<?= ph(80, 80, 55, 'TW') ?>" alt=""></div>
            <div class="gk-avatar gk-avatar-md">+3</div>
        </div>
    </div>

    <div class="demo-code"><pre>.gk-avatar.gk-avatar-lg           // Sizes: xs, sm, md, lg, xl
.gk-avatar-status.online          // Status: online, away, busy, offline
.gk-avatar-group                  // Stacked group
.gk-avatar-square                 // Square instead of round</pre></div>

    <hr style="border:none;border-top:1px solid var(--gk-outline-variant);margin:40px 0">

    <h3 style="margin: 32px 0 16px;">Breadcrumb <?= since('breadcrumb') ?></h3>
    <p class="demo-intro">Path navigation for orientation in nested areas.</p>

    <div class="gk-segment">
        <nav class="gk-breadcrumb">
            <a href="#"><span class="material-icons" aria-hidden="true">home</span></a>
            <span class="gk-breadcrumb-sep"><span class="material-icons" aria-hidden="true">chevron_right</span></span>
            <a href="#">Products</a>
            <span class="gk-breadcrumb-sep"><span class="material-icons" aria-hidden="true">chevron_right</span></span>
            <a href="#">Hosting</a>
            <span class="gk-breadcrumb-sep"><span class="material-icons" aria-hidden="true">chevron_right</span></span>
            <span class="gk-breadcrumb-current">Managed Server</span>
        </nav>
        <p style="color:var(--gk-on-surface-variant);font-size:13px;margin:0">Page content here...</p>
    </div>

    <div class="demo-code"><pre>&lt;nav class="gk-breadcrumb"&gt;
    &lt;a href="#"&gt;&lt;span class="material-icons"&gt;home&lt;/span&gt;&lt;/a&gt;
    &lt;span class="gk-breadcrumb-sep"&gt;&lt;span class="material-icons"&gt;chevron_right&lt;/span&gt;&lt;/span&gt;
    &lt;a href="#"&gt;Products&lt;/a&gt;
    &lt;span class="gk-breadcrumb-sep"&gt;...&lt;/span&gt;
    &lt;span class="gk-breadcrumb-current"&gt;Current Page&lt;/span&gt;
&lt;/nav&gt;</pre></div>

    <hr style="border:none;border-top:1px solid var(--gk-outline-variant);margin:40px 0">

    <h3 style="margin: 32px 0 16px;">Tabs — previous shape <?= since('tabs') ?></h3>
    <p class="demo-intro">The line shape every tab row keeps unless it orders the Karteireiter. The current shape, levels, nesting and the phone grid are under <a href="#tabs">Tabs</a>.</p>

    <div class="gk-tabs">
        <div class="gk-tab-nav">
            <button class="gk-tab-btn gk-active" data-tab="tab-overview">Overview</button>
            <button class="gk-tab-btn" data-tab="tab-details">Details</button>
            <button class="gk-tab-btn" data-tab="tab-settings">Settings</button>
        </div>
        <div class="gk-tab-panel gk-active" data-tab="tab-overview">
            <p>This is the overview &mdash; the first tab is active by default.</p>
        </div>
        <div class="gk-tab-panel" data-tab="tab-details">
            <p>Detail information is displayed here.</p>
        </div>
        <div class="gk-tab-panel" data-tab="tab-settings">
            <p>Settings and configuration options.</p>
        </div>
    </div>

    <div class="demo-code"><pre>&lt;div class="gk-tabs"&gt;
    &lt;div class="gk-tab-nav"&gt;
        &lt;button class="gk-tab-btn gk-active" data-tab="tab-1"&gt;Tab 1&lt;/button&gt;
        &lt;button class="gk-tab-btn" data-tab="tab-2"&gt;Tab 2&lt;/button&gt;
    &lt;/div&gt;
    &lt;div class="gk-tab-panel gk-active" data-tab="tab-1"&gt;Content 1&lt;/div&gt;
    &lt;div class="gk-tab-panel" data-tab="tab-2"&gt;Content 2&lt;/div&gt;
&lt;/div&gt;</pre></div>

    <h3 class="demo-h3" id="tabs-links">Tabs between pages (previous shape), steps, a quiet disclosure <?= since('steps', 'disclosure') ?></h3>
    <p class="demo-intro">When each tab is a page of its own: links in a <code>&lt;nav&gt;</code>, the current page marked with <code>aria-current="page"</code> (it looks like <code>.gk-active</code>). <code>.gk-tab-nav-fill</code> is the main row (44px; on a phone the symbol stands over the word), <code>.gk-tab-nav-secondary</code> a quieter row under it. Above them a status band: a message with steps, a short to-do list (a compact timeline) and a disclosure.</p>
    <div class="demo-card">
        <section class="gk-message gk-message-info" aria-label="Status of this page">
            <span class="material-icons" aria-hidden="true">fact_check</span>
            <div class="gk-message-content">
                <div class="gk-message-header">Proposed for approval</div>
                <ol class="gk-steps" aria-label="Way to live">
                    <li class="gk-steps-item gk-steps-done">Draft<span class="gk-sr-only"> (done)</span></li>
                    <li class="gk-steps-item gk-steps-current" aria-current="step">Approval</li>
                    <li class="gk-steps-item">Live</li>
                </ol>
                <ol class="gk-timeline gk-timeline-compact" role="list" aria-label="Still open">
                    <li class="gk-timeline-step gk-timeline-todo"><span class="gk-timeline-marker" aria-hidden="true"></span>
                        <div class="gk-timeline-body"><div class="gk-timeline-head"><span class="gk-timeline-title">Choose where the sign-ups go.</span><span class="gk-timeline-state">Open</span></div></div>
                        <div class="gk-timeline-actions"><a class="gk-btn gk-btn-outlined gk-btn-primary gk-btn-sm" href="#tabs-links"><span class="material-icons" aria-hidden="true">arrow_forward</span><span>To step 5</span></a></div></li>
                    <li class="gk-timeline-step gk-timeline-todo"><span class="gk-timeline-marker" aria-hidden="true"></span>
                        <div class="gk-timeline-body"><div class="gk-timeline-head"><span class="gk-timeline-title">The legal notice has to be confirmed first.</span><span class="gk-timeline-state">Open</span></div></div></li>
                    <li class="gk-timeline-step gk-timeline-waiting"><span class="gk-timeline-marker" aria-hidden="true"></span>
                        <div class="gk-timeline-body"><div class="gk-timeline-head"><span class="gk-timeline-title">Publish</span><span class="gk-timeline-state">Afterwards</span></div></div></li>
                </ol>
                <details class="gk-disclosure"><summary>Show 3 completed checks</summary>
                    <ul><li>Title and text are there.</li><li>The date lies ahead.</li><li>The picture is uploaded.</li></ul>
                </details>
            </div>
        </section>
        <nav class="gk-tab-nav gk-tab-nav-fill" aria-label="Areas of this page">
            <a class="gk-tab-btn" href="#tabs-links"><span class="material-icons gk-tab-icon" aria-hidden="true">edit</span><span>Edit</span></a>
            <a class="gk-tab-btn" href="#tabs-links"><span class="material-icons gk-tab-icon" aria-hidden="true">rocket_launch</span><span>Publish</span></a>
            <a class="gk-tab-btn" href="#tabs-links" aria-current="page"><span class="material-icons gk-tab-icon" aria-hidden="true">bar_chart</span><span>Results</span></a>
            <a class="gk-tab-btn" href="#tabs-links"><span class="material-icons gk-tab-icon" aria-hidden="true">mail</span><span>Mails</span></a>
        </nav>
        <nav class="gk-tab-nav gk-tab-nav-secondary" aria-label="Views">
            <a class="gk-tab-btn" href="#tabs-links">Sign-ups</a>
            <a class="gk-tab-btn" href="#tabs-links" aria-current="page">Statistics</a>
        </nav>
    </div>
    <div class="demo-code"><pre>&lt;nav class="gk-tab-nav gk-tab-nav-fill" aria-label="Areas"&gt;
    &lt;a class="gk-tab-btn" href="/edit"&gt;&lt;span class="material-icons gk-tab-icon" aria-hidden="true"&gt;edit&lt;/span&gt;&lt;span&gt;Edit&lt;/span&gt;&lt;/a&gt;
    &lt;a class="gk-tab-btn" href="/results" aria-current="page"&gt;…&lt;/a&gt;
&lt;/nav&gt;
&lt;nav class="gk-tab-nav gk-tab-nav-secondary" aria-label="Views"&gt;…&lt;/nav&gt;
&lt;ol class="gk-steps"&gt;&lt;li class="gk-steps-item gk-steps-done"&gt;Draft&lt;/li&gt;
  &lt;li class="gk-steps-item gk-steps-current" aria-current="step"&gt;Approval&lt;/li&gt;&lt;li class="gk-steps-item"&gt;Live&lt;/li&gt;&lt;/ol&gt;
&lt;details class="gk-disclosure"&gt;&lt;summary&gt;Show 3 completed checks&lt;/summary&gt;…&lt;/details&gt;</pre></div>

    <hr style="border:none;border-top:1px solid var(--gk-outline-variant);margin:40px 0">

    <h3 style="margin: 32px 0 16px;">Combinations</h3>
    <p class="demo-intro">Combination examples showing the full potential of GridKit.</p>

    <h3 style="margin: 32px 0 16px;">Tabs + Accordion (combined)</h3>
    <p class="demo-intro">Tabs for main categories, accordion for details &mdash; a common pattern in admin UIs.</p>

    <div class="gk-tabs">
        <div class="gk-tab-nav">
            <button class="gk-tab-btn gk-active" data-tab="combo-produkte">Products</button>
            <button class="gk-tab-btn" data-tab="combo-kunden">Customers</button>
            <button class="gk-tab-btn" data-tab="combo-einstellungen">Settings</button>
        </div>
        <div class="gk-tab-panel gk-active" data-tab="combo-produkte">
            <div class="gk-accordion" data-gk-single style="margin-top:16px">
                <div class="gk-accordion-item open">
                    <button class="gk-accordion-trigger">
                        <span>Webdesign Packages</span>
                        <span class="material-icons" aria-hidden="true">expand_more</span>
                    </button>
                    <div class="gk-accordion-content">
                        <div class="gk-accordion-body">3 packages available: S (1,200 &euro;), M (2,400 &euro;), L (3,500 &euro;). All incl. responsive design and CMS.</div>
                    </div>
                </div>
                <div class="gk-accordion-item">
                    <button class="gk-accordion-trigger">
                        <span>Hosting &amp; Server</span>
                        <span class="material-icons" aria-hidden="true">expand_more</span>
                    </button>
                    <div class="gk-accordion-content">
                        <div class="gk-accordion-body">Managed hosting from 9.90 &euro;/month. SSD, backups, SSL included. 99.9% uptime guarantee.</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="gk-tab-panel" data-tab="combo-kunden">
            <div class="gk-message gk-message-info" style="margin-top:16px">
                <span class="material-icons" aria-hidden="true">info</span>
                <div class="gk-message-content">24 active customers, 3 open inquiries</div>
            </div>
            <div class="gk-cards gk-cards-3" style="margin-top:12px">
                <div class="gk-card"><div class="gk-card-body"><strong>Mustermann GmbH</strong><div class="gk-card-meta">Since 2024 &middot; 12 projects</div></div></div>
                <div class="gk-card"><div class="gk-card-body"><strong>Tech Solutions AG</strong><div class="gk-card-meta">Since 2023 &middot; 8 projects</div></div></div>
                <div class="gk-card"><div class="gk-card-body"><strong>Weber &amp; Partner</strong><div class="gk-card-meta">Since 2025 &middot; 3 projects</div></div></div>
            </div>
        </div>
        <div class="gk-tab-panel" data-tab="combo-einstellungen">
            <div class="gk-segment" style="margin-top:16px">
                <div class="gk-segment-header">Notifications</div>
                <label class="gk-checkbox-wrap"><input type="checkbox" checked><span class="gk-checkbox-custom"></span><span>Email on new inquiries</span></label>
                <br><br>
                <label class="gk-checkbox-wrap"><input type="checkbox"><span class="gk-checkbox-custom"></span><span>Weekly report</span></label>
            </div>
        </div>
    </div>

    <h3 style="margin: 32px 0 16px;">Segment + Message + Table</h3>
    <p class="demo-intro">Dashboard view with status message and data table in a segment.</p>

    <div class="gk-segment">
        <div class="gk-segment-header">Server-Status</div>
        <div class="gk-message gk-message-success gk-message-compact" style="margin-bottom:16px">
            <span class="material-icons" aria-hidden="true">check_circle</span>
            <div class="gk-message-content">All 3 servers are online &mdash; last check 2 minutes ago.</div>
        </div>
        <div class="gk-table-scroll">
            <table class="gk-table">
                <thead><tr><th>Server</th><th>Status</th><th>CPU</th><th>RAM</th><th>Uptime</th></tr></thead>
                <tbody>
                    <tr><td>Baerli (server8)</td><td><span class="gk-label gk-label-green">Online</span></td><td>12%</td><td>4.2 GB</td><td>47 days</td></tr>
                    <tr><td>Theo (server7)</td><td><span class="gk-label gk-label-green">Online</span></td><td>8%</td><td>3.8 GB</td><td>23 days</td></tr>
                    <tr><td>Waldi (server6)</td><td><span class="gk-label gk-label-green">Online</span></td><td>15%</td><td>5.1 GB</td><td>31 days</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <hr style="border:none;border-top:1px solid var(--gk-outline-variant);margin:40px 0">

    <h3 class="demo-h3" id="pager-match">One pager for tables and lists <?= since('pagination', 'table') ?></h3>
    <p class="demo-intro">Since 1.118.0 the pager of <code>Table::paginate()</code> is the bar of <code>Pagination</code>: the count on the left, the page buttons on the right, first and last included &mdash; the same boxes, sizes and colours in light and dark and in every theme. Below, a table of twelve rows that pages itself, and the bar for a list you page yourself, with the same numbers. The table counts the rows it shows (&ldquo;1&ndash;5 of 12&rdquo;); <code>Pagination</code>, which is handed pages and not rows, counts its entries.</p>
        <?php
        showcase(function () {
            $rows = [];
            for ($i = 1; $i <= 12; $i++) {
                $rows[] = ['id' => $i, 'no' => sprintf('RE-2026-%03d', $i), 'customer' => "Customer $i", 'total' => $i * 125.5];
            }
            (new Table('demo-one-pager'))->setData($rows)
                ->column('no', 'Inv. No.', ['sortable' => true, 'nowrap' => true])
                ->column('customer', 'Customer')
                ->column('total', 'Amount', ['format' => 'currency', 'align' => 'right', 'sortable' => true])
                ->toolbar(false)
                ->caption('Invoices')
                ->paginate(5)
                ->render();
        });
        showcase(function () {
            echo Pagination::build(['page' => 1, 'totalPages' => 3, 'total' => 12, 'baseUrl' => '#pager-match']);
        });
        ?>

    <hr style="border:none;border-top:1px solid var(--gk-outline-variant);margin:40px 0">

    <h3 style="margin: 32px 0 16px;">FilterChips <?= since('chips') ?></h3>
    <div class="demo-pair">
    <div class="demo-card">
        <?php
        $chips = new FilterChips('status-filter', 'status');
        $chips->chip('', 'All', ['count' => 152])
            ->chip('active', 'Active', ['count' => 89, 'color' => 'green'])
            ->chip('draft', 'Draft', ['count' => 23, 'color' => 'orange'])
            ->chip('paid', 'Paid', ['count' => 31, 'color' => 'blue'])
            ->chip('overdue', 'Overdue', ['count' => 9, 'color' => 'red'])
            ->render();
        ?>
    </div>
    <div class="demo-code"><pre>$chips = new FilterChips('status-filter', 'status');
$chips->chip('', 'All', ['count' => 152])
    ->chip('active', 'Active', ['count' => 89, 'color' => 'green'])
    ->render();</pre></div>
    </div>

    <h3 class="demo-h3" id="segmented">Segmented button <?= since('segmented') ?></h3>
    <p class="demo-intro">One choice of a few that switches the view of a page — a period, overview or visitors. Joined; the chosen one is tinted <em>and</em> ticked. <code>FilterChips::segmented($label)</code> draws the same links in it.</p>
    <div class="demo-pair">
    <div class="demo-card">
        <?php (new FilterChips('demo-period', 'demo_days'))->current('7')->segmented('Period')
            ->chip('1', 'Today')->chip('7', '7 days')->chip('30', '30 days')->chip('90', '90 days')->render(); ?>
    </div>
    <div class="demo-code"><pre>(new FilterChips('period', 'days'))->current('7')->segmented('Period')
    ->chip('1', 'Today')->chip('7', '7 days')->chip('30', '30 days')->render();</pre></div>
    </div>

    <hr style="border:none;border-top:1px solid var(--gk-outline-variant);margin:40px 0">

    <h3>YearFilter <?= since('year-filter') ?></h3>
    <div class="demo-pair">
    <div class="demo-card">
        <?php
        $years = new YearFilter('demo-years', 'year');
        $years->range(2022, 2026)->render();
        ?>
    </div>
    <div class="demo-code"><pre>$years = new YearFilter('year-nav', 'year');
$years->range(2022, 2026)->render();</pre></div>
    </div>

    <hr style="border:none;border-top:1px solid var(--gk-outline-variant);margin:40px 0">

    <h3>TableHeader <?= since('tableheader') ?></h3>
    <p class="demo-intro">One unified filter/search bar for all tables. Three fixed sections: Status row · Toolbar · Advanced (collapsible). Stops every page from looking different.</p>
    <div class="demo-pair">
    <div class="demo-card" style="padding:0;overflow:hidden;border-radius:8px;">
        <?php
        TableHeader::make('th-demo')
            ->status(function() {
                $st = new FilterChips('th-status', 'status');
                $st->chip('', 'All')
                   ->chip('open', 'Open', ['count' => 12, 'color' => 'orange'])
                   ->chip('paid', 'Paid', ['count' => 47, 'color' => 'green'])
                   ->render();
            })
            ->search('q', '', 'Search invoices, customer…', ['live' => 'th-live'])
            ->filter(function() {
                $yf = new YearFilter('th-year', 'year');
                $yf->range(2022, 2026)->mode('dropdown')->allOption('All years')->render();
            })
            ->filter('<select class="gk-filter"><option>All payment methods</option><option>Bank</option><option>Cash</option></select>')
            ->advanced(function() {
                echo '<input type="date" class="gk-filter" title="From">';
                echo '<span style="color:var(--gk-text-muted);font-size:12px;">–</span>';
                echo '<input type="date" class="gk-filter" title="To">';
                echo '<input type="number" class="gk-filter" placeholder="€ from" style="max-width:110px;">';
                echo '<span style="color:var(--gk-text-muted);font-size:12px;">–</span>';
                echo '<input type="number" class="gk-filter" placeholder="€ to" style="max-width:110px;">';
            }, 'Date & amount range')
            ->reset('?')
            ->render();
        ?>
    </div>
    <div class="demo-code"><pre>TableHeader::make('exp')
    -&gt;status(fn() =&gt; $statusChips-&gt;render())
    -&gt;search('q', $q, 'Search…', ['live' =&gt; 'exp-live'])
    -&gt;filter(fn() =&gt; $yearFilter-&gt;render())
    -&gt;filter('&lt;select class="gk-filter"&gt;…&lt;/select&gt;')
    -&gt;advanced(fn() =&gt; renderDateRange())
    -&gt;reset('/faktura/expenses')
    -&gt;render();</pre></div>
    </div>

    <hr style="border:none;border-top:1px solid var(--gk-outline-variant);margin:40px 0">

    <h3>Formatter <?= since('formatter') ?></h3>
    <div class="demo-pair">
    <div class="demo-card">
        <p class="demo-intro">Built-in formatting for table columns: currency, percent, date, boolean, label, email.</p>
        <?php
        $fmtData = [
            ['input' => 1234.56, 'pct' => 20, 'date' => '2026-02-13', 'active' => 1, 'status' => 'paid', 'email' => 'info@ssi.at'],
            ['input' => 99.00, 'pct' => 10, 'date' => '2026-01-28', 'active' => 0, 'status' => 'pending', 'email' => 'office@panel.at'],
            ['input' => 5500.00, 'pct' => 0, 'date' => '2025-12-01', 'active' => 1, 'status' => 'cancelled', 'email' => ''],
        ];
        $fmtTable = new Table('formatters');
        $fmtTable->setData($fmtData)
            ->column('input', 'Currency', ['format' => 'currency'])
            ->column('pct', 'Percent', ['format' => 'percent'])
            ->column('date', 'Date', ['format' => 'date'])
            ->column('active', 'Boolean', ['format' => 'boolean'])
            ->column('status', 'Label', ['format' => 'label'])
            ->column('email', 'Email', ['format' => 'email'])
            ->searchable(false)->paginate(false)->render();
        ?>
    </div>
    <div class="demo-code"><pre>->column('amount', 'Amount', ['format' => 'currency'])    // €1,234.56  (follows the locale)
->column('tax', 'VAT', ['format' => 'percent'])           // 20 %
->column('date', 'Date', ['format' => 'date'])            // Feb 13, 2026  (follows the locale)
->column('active', 'Active', ['format' => 'boolean'])     // ✓ / –
->column('status', 'Status', ['format' => 'label'])       // Colored label
->column('email', 'E-Mail', ['format' => 'email'])        // mailto: Link</pre></div>
    </div>

    <h3 class="demo-h3" id="chip-colours">FilterChips: colour names and icons <?= since('chips') ?></h3>
    <p class="demo-intro">A chip takes <code>green</code>, <code>orange</code>, <code>blue</code> and <code>red</code> or the role names <code>success</code>, <code>danger</code>, <code>primary</code>, <code>neutral</code>; <code>'icon'</code> puts a symbol in front. A colour shows on the chip that is active, here the first.</p>
        <?php
        showcase(function () {
            (new FilterChips('demo-chip-roles', 'role_status'))
                ->current('ok')
                ->chip('ok', 'Done', ['color' => 'success', 'icon' => 'check_circle'])
                ->chip('stop', 'Failed', ['color' => 'danger', 'icon' => 'error'])
                ->chip('me', 'Mine', ['color' => 'primary', 'icon' => 'person'])
                ->chip('rest', 'Others', ['color' => 'neutral', 'icon' => 'group'])
                ->render();
        });
        ?>

    <h3 class="demo-h3" id="pager">Pagination, page size and a loading state <?= since('pagination') ?></h3>
    <p class="demo-intro"><code>Pagination::build()</code> is the pager for a list you page yourself, <code>PageSize</code> the rows-per-page choice that belongs next to it. Both keep the other query parameters (<code>'params'</code>, <code>preserve()</code>). While something loads, <code>gk-skeleton</code> stands in for the text.</p>
        <?php
        showcase(function () {
            echo Pagination::build(['page' => 5, 'totalPages' => 12, 'baseUrl' => '#pager', 'total' => 238, 'label' => 'invoices']);
            (new PageSize())->options([10, 25, 50, 100])->current(25)->baseUrl('#pager')->render();
        });
        ?>
        <?php
        showcase(function () {
            echo '<div class="gk-flex gk-flex-col gk-gap-sm" aria-busy="true">';
            echo '<div class="gk-skeleton" style="height:14px;width:60%"></div>';
            echo '<div class="gk-skeleton" style="height:14px;width:90%"></div>';
            echo '<div class="gk-skeleton" style="height:14px;width:40%"></div>';
            echo '</div>';
        });
        ?>
</div>

