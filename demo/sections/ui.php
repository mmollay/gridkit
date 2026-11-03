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
use GridKit\ActionGroup;
?>
<!-- ===== UI (merged: buttons + header + sidebar + themes) ===== -->
<div class="demo-section" data-section="ui">
    <h2>UI Components</h2>

    <h3 style="margin: 32px 0 16px;">Buttons <?= since('button') ?></h3>

    <div class="demo-card">
        <h3 style="margin:0 0 12px; font-size:15px; color:var(--gk-on-surface, #374151);">Variants</h3>
        <div class="demo-btn-row" style="margin-bottom:16px">
            <?= \GridKit\Button::render('Filled', ['variant' => 'filled', 'color' => 'primary', 'icon' => 'save']) ?>
            <?= \GridKit\Button::render('Outlined', ['variant' => 'outlined', 'color' => 'primary', 'icon' => 'save']) ?>
            <?= \GridKit\Button::render('Text', ['variant' => 'text', 'color' => 'primary', 'icon' => 'save']) ?>
            <?= \GridKit\Button::render('Tonal', ['variant' => 'tonal', 'color' => 'primary', 'icon' => 'save']) ?>
        </div>
    </div>

    <div class="demo-card">
        <h3 style="margin:0 0 12px; font-size:15px; color:var(--gk-on-surface, #374151);">Colors – Filled</h3>
        <div class="demo-btn-row" style="margin-bottom:16px">
            <?= \GridKit\Button::render('Primary', ['variant' => 'filled', 'color' => 'primary', 'icon' => 'star']) ?>
            <?= \GridKit\Button::render('Success', ['variant' => 'filled', 'color' => 'success', 'icon' => 'check_circle']) ?>
            <?= \GridKit\Button::render('Warning', ['variant' => 'filled', 'color' => 'warning', 'icon' => 'warning']) ?>
            <?= \GridKit\Button::render('Danger', ['variant' => 'filled', 'color' => 'danger', 'icon' => 'delete']) ?>
            <?= \GridKit\Button::render('Neutral', ['variant' => 'filled', 'color' => 'neutral', 'icon' => 'settings']) ?>
        </div>
        <h3 style="margin:16px 0 12px; font-size:15px; color:var(--gk-on-surface, #374151);">Colors – Outlined</h3>
        <div class="demo-btn-row" style="margin-bottom:16px">
            <?= \GridKit\Button::render('Primary', ['variant' => 'outlined', 'color' => 'primary', 'icon' => 'star']) ?>
            <?= \GridKit\Button::render('Success', ['variant' => 'outlined', 'color' => 'success', 'icon' => 'check_circle']) ?>
            <?= \GridKit\Button::render('Warning', ['variant' => 'outlined', 'color' => 'warning', 'icon' => 'warning']) ?>
            <?= \GridKit\Button::render('Danger', ['variant' => 'outlined', 'color' => 'danger', 'icon' => 'delete']) ?>
            <?= \GridKit\Button::render('Neutral', ['variant' => 'outlined', 'color' => 'neutral', 'icon' => 'settings']) ?>
        </div>
        <h3 style="margin:16px 0 12px; font-size:15px; color:var(--gk-on-surface, #374151);">Colors – Tonal</h3>
        <div class="demo-btn-row" style="margin-bottom:16px">
            <?= \GridKit\Button::render('Primary', ['variant' => 'tonal', 'color' => 'primary', 'icon' => 'star']) ?>
            <?= \GridKit\Button::render('Success', ['variant' => 'tonal', 'color' => 'success', 'icon' => 'check_circle']) ?>
            <?= \GridKit\Button::render('Warning', ['variant' => 'tonal', 'color' => 'warning', 'icon' => 'warning']) ?>
            <?= \GridKit\Button::render('Danger', ['variant' => 'tonal', 'color' => 'danger', 'icon' => 'delete']) ?>
            <?= \GridKit\Button::render('Neutral', ['variant' => 'tonal', 'color' => 'neutral', 'icon' => 'settings']) ?>
        </div>
        <h3 style="margin:16px 0 12px; font-size:15px; color:var(--gk-on-surface, #374151);">Colors – Text</h3>
        <div class="demo-btn-row">
            <?= \GridKit\Button::render('Primary', ['variant' => 'text', 'color' => 'primary', 'icon' => 'star']) ?>
            <?= \GridKit\Button::render('Success', ['variant' => 'text', 'color' => 'success', 'icon' => 'check_circle']) ?>
            <?= \GridKit\Button::render('Warning', ['variant' => 'text', 'color' => 'warning', 'icon' => 'warning']) ?>
            <?= \GridKit\Button::render('Danger', ['variant' => 'text', 'color' => 'danger', 'icon' => 'delete']) ?>
            <?= \GridKit\Button::render('Neutral', ['variant' => 'text', 'color' => 'neutral', 'icon' => 'settings']) ?>
        </div>
    </div>

    <div class="demo-card">
        <h3 style="margin:0 0 12px; font-size:15px; color:var(--gk-on-surface, #374151);">Icon-Only</h3>
        <div class="demo-btn-row" style="margin-bottom:16px">
            <?= \GridKit\Button::icon('edit', ['variant' => 'filled', 'color' => 'primary', 'title' => 'Edit']) ?>
            <?= \GridKit\Button::icon('delete', ['variant' => 'filled', 'color' => 'danger', 'title' => 'Delete']) ?>
            <?= \GridKit\Button::icon('add', ['variant' => 'filled', 'color' => 'success', 'title' => 'New']) ?>
            <?= \GridKit\Button::icon('send', ['variant' => 'filled', 'color' => 'primary', 'title' => 'Send']) ?>
            <?= \GridKit\Button::icon('print', ['variant' => 'filled', 'color' => 'neutral', 'title' => 'Print']) ?>
        </div>
        <div class="demo-btn-row" style="margin-bottom:16px">
            <?= \GridKit\Button::icon('edit', ['variant' => 'outlined', 'color' => 'primary']) ?>
            <?= \GridKit\Button::icon('delete', ['variant' => 'outlined', 'color' => 'danger']) ?>
            <?= \GridKit\Button::icon('add', ['variant' => 'outlined', 'color' => 'success']) ?>
            <?= \GridKit\Button::icon('send', ['variant' => 'outlined', 'color' => 'primary']) ?>
            <?= \GridKit\Button::icon('print', ['variant' => 'outlined', 'color' => 'neutral']) ?>
        </div>
        <div class="demo-btn-row">
            <?= \GridKit\Button::icon('edit', ['variant' => 'text', 'color' => 'primary']) ?>
            <?= \GridKit\Button::icon('delete', ['variant' => 'text', 'color' => 'danger']) ?>
            <?= \GridKit\Button::icon('add', ['variant' => 'text', 'color' => 'success']) ?>
            <?= \GridKit\Button::icon('send', ['variant' => 'text', 'color' => 'primary']) ?>
            <?= \GridKit\Button::icon('print', ['variant' => 'text', 'color' => 'neutral']) ?>
        </div>
    </div>

    <div class="demo-card">
        <h3 style="margin:0 0 12px; font-size:15px; color:var(--gk-on-surface, #374151);">Sizes</h3>
        <div class="demo-btn-row" style="align-items:center">
            <?= \GridKit\Button::render('Small', ['variant' => 'filled', 'color' => 'primary', 'size' => 'sm', 'icon' => 'edit']) ?>
            <?= \GridKit\Button::render('Medium', ['variant' => 'filled', 'color' => 'primary', 'size' => 'md', 'icon' => 'edit']) ?>
            <?= \GridKit\Button::render('Large', ['variant' => 'filled', 'color' => 'primary', 'size' => 'lg', 'icon' => 'edit']) ?>
        </div>
    </div>

    <div class="demo-card">
        <h3 style="margin:0 0 12px; font-size:15px; color:var(--gk-on-surface, #374151);">States</h3>
        <div class="demo-btn-row" style="margin-bottom:16px">
            <?= \GridKit\Button::render('Normal', ['variant' => 'filled', 'color' => 'primary', 'icon' => 'check']) ?>
            <?= \GridKit\Button::render('Disabled', ['variant' => 'filled', 'color' => 'primary', 'icon' => 'block', 'disabled' => true]) ?>
            <?= \GridKit\Button::render('Loading', ['variant' => 'filled', 'color' => 'primary', 'loading' => true]) ?>
            <?= \GridKit\Button::render('Inbox', ['variant' => 'filled', 'color' => 'primary', 'icon' => 'mail', 'badge' => '3']) ?>
        </div>
        <div class="demo-btn-row">
            <?= \GridKit\Button::render('Disabled', ['variant' => 'outlined', 'color' => 'primary', 'icon' => 'block', 'disabled' => true]) ?>
            <?= \GridKit\Button::render('Disabled', ['variant' => 'tonal', 'color' => 'danger', 'icon' => 'block', 'disabled' => true]) ?>
            <?= \GridKit\Button::render('Disabled', ['variant' => 'text', 'color' => 'success', 'icon' => 'block', 'disabled' => true]) ?>
        </div>
    </div>

    <div class="demo-card">
        <h3 style="margin:0 0 12px; font-size:15px; color:var(--gk-on-surface, #374151);">Icon Position &amp; Special</h3>
        <div class="demo-btn-row" style="margin-bottom:16px">
            <?= \GridKit\Button::render('Icon left', ['variant' => 'filled', 'color' => 'primary', 'icon' => 'arrow_back', 'iconPosition' => 'left']) ?>
            <?= \GridKit\Button::render('Icon right', ['variant' => 'filled', 'color' => 'primary', 'icon' => 'arrow_forward', 'iconPosition' => 'right']) ?>
            <?= \GridKit\Button::render('Text only', ['variant' => 'filled', 'color' => 'primary']) ?>
        </div>
        <div class="demo-btn-row" style="margin-bottom:16px">
            <?= \GridKit\Button::render('As Link', ['variant' => 'filled', 'color' => 'primary', 'icon' => 'open_in_new', 'href' => '#ui', 'target' => '_self']) ?>
            <?= \GridKit\Button::render('Submit', ['variant' => 'filled', 'color' => 'success', 'icon' => 'save', 'type' => 'submit']) ?>
        </div>
        <div><?= \GridKit\Button::render('Full Width', ['variant' => 'filled', 'color' => 'primary', 'icon' => 'send', 'fullWidth' => true]) ?></div>
    </div>

    <div class="demo-card">
        <h3 style="margin:0 0 12px; font-size:15px; color:var(--gk-on-surface, #374151);">Button Group</h3>
        <div class="demo-btn-row">
            <?= \GridKit\Button::group([\GridKit\Button::render('Left', ['variant' => 'outlined', 'color' => 'primary', 'icon' => 'format_align_left']), \GridKit\Button::render('Center', ['variant' => 'outlined', 'color' => 'primary', 'icon' => 'format_align_center']), \GridKit\Button::render('Right', ['variant' => 'outlined', 'color' => 'primary', 'icon' => 'format_align_right'])]) ?>
            &nbsp;
            <?= \GridKit\Button::group([\GridKit\Button::icon('undo', ['variant' => 'outlined', 'color' => 'neutral']), \GridKit\Button::icon('redo', ['variant' => 'outlined', 'color' => 'neutral'])]) ?>
        </div>
    </div>

    <div class="demo-card">
        <h3 style="margin:0 0 12px; font-size:15px; color:var(--gk-on-surface, #374151);">Shapes</h3>
        <div class="demo-btn-row" style="align-items:center">
            <?= \GridKit\Button::render('Rounded', ['variant' => 'filled', 'color' => 'primary', 'icon' => 'star', 'shape' => 'rounded']) ?>
            <?= \GridKit\Button::render('Pill', ['variant' => 'filled', 'color' => 'primary', 'icon' => 'star', 'shape' => 'pill']) ?>
            <?= \GridKit\Button::icon('star', ['variant' => 'filled', 'color' => 'primary', 'shape' => 'circle', 'title' => 'Circle']) ?>
            <?= \GridKit\Button::render('Square', ['variant' => 'filled', 'color' => 'primary', 'icon' => 'star', 'shape' => 'square']) ?>
        </div>
        <div class="demo-btn-row" style="align-items:center; margin-top:12px">
            <?= \GridKit\Button::render('Pill Outlined', ['variant' => 'outlined', 'color' => 'success', 'icon' => 'check_circle', 'shape' => 'pill']) ?>
            <?= \GridKit\Button::render('Pill Tonal', ['variant' => 'tonal', 'color' => 'danger', 'icon' => 'delete', 'shape' => 'pill']) ?>
            <?= \GridKit\Button::render('Pill Text', ['variant' => 'text', 'color' => 'primary', 'icon' => 'link', 'shape' => 'pill']) ?>
        </div>
    </div>

    <div class="demo-card">
        <h3 style="margin:0 0 12px; font-size:15px; color:var(--gk-on-surface, #374151);">FAB (Floating Action Button) <?= since('fab') ?></h3>
        <div class="demo-btn-row" style="align-items:center; gap:16px">
            <?= \GridKit\Button::fab('add', ['size' => 'sm']) ?>
            <?= \GridKit\Button::fab('add') ?>
            <?= \GridKit\Button::fab('add', ['size' => 'lg']) ?>
        </div>
        <div class="demo-btn-row" style="align-items:center; gap:16px; margin-top:16px">
            <?= \GridKit\Button::fab('edit', ['extended' => true, 'label' => 'Edit']) ?>
            <?= \GridKit\Button::fab('add', ['extended' => true, 'label' => 'Create', 'color' => 'success']) ?>
            <?= \GridKit\Button::fab('delete', ['extended' => true, 'label' => 'Remove', 'color' => 'danger']) ?>
        </div>
        <div class="demo-btn-row" style="align-items:center; gap:16px; margin-top:16px">
            <?= \GridKit\Button::fab('star', ['color' => 'warning']) ?>
            <?= \GridKit\Button::fab('favorite', ['color' => 'danger']) ?>
            <?= \GridKit\Button::fab('share', ['color' => 'neutral', 'variant' => 'tonal']) ?>
        </div>
    </div>

    <div class="demo-code"><pre>use GridKit\Button;
echo Button::render('Save', ['variant' => 'filled', 'color' => 'primary', 'icon' => 'save']);
echo Button::icon('edit', ['variant' => 'filled', 'color' => 'primary']);
echo Button::fab('add');
echo Button::fab('edit', ['extended' => true, 'label' => 'Edit']);</pre></div>

    <hr style="border:none;border-top:1px solid var(--gk-outline-variant);margin:40px 0">

    <h3>Labels <?= since('label') ?></h3>
    <div class="demo-card">
        <p class="demo-intro">Colored labels for status display &mdash; also usable standalone, not only in tables.</p>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px">
            <span class="gk-label gk-label-green">Active</span>
            <span class="gk-label gk-label-orange">Draft</span>
            <span class="gk-label gk-label-red">Error</span>
            <span class="gk-label gk-label-gray">Archived</span>
            <span class="gk-label gk-label-blue">Info</span>
        </div>
    </div>
    <div class="demo-code"><pre>&lt;span class="gk-label gk-label-green"&gt;Active&lt;/span&gt;
&lt;span class="gk-label gk-label-orange"&gt;Draft&lt;/span&gt;
&lt;span class="gk-label gk-label-red"&gt;Error&lt;/span&gt;
&lt;span class="gk-label gk-label-gray"&gt;Archived&lt;/span&gt;
&lt;span class="gk-label gk-label-blue"&gt;Info&lt;/span&gt;</pre></div>

    <hr style="border:none;border-top:1px solid var(--gk-outline-variant);margin:40px 0">

    <h3>Tooltip <?= since('tooltip') ?></h3>
    <div class="demo-card">
        <p class="demo-intro">Tooltips appear automatically on buttons with a <code>title</code> attribute. Hover over the buttons:</p>
        <div class="demo-btn-row">
            <?= \GridKit\Button::icon('edit', ['variant' => 'filled', 'color' => 'primary', 'title' => 'Edit entry']) ?>
            <?= \GridKit\Button::icon('delete', ['variant' => 'filled', 'color' => 'danger', 'title' => 'Delete entry']) ?>
            <?= \GridKit\Button::icon('visibility', ['variant' => 'filled', 'color' => 'neutral', 'title' => 'Show preview']) ?>
            <?= \GridKit\Button::icon('download', ['variant' => 'outlined', 'color' => 'primary', 'title' => 'Download PDF']) ?>
        </div>
    </div>
    <div class="demo-code"><pre>// Tooltip via title attribute (CSS-only, no JS)
Button::icon('edit', ['title' => 'Edit']);
&lt;button class="gk-btn" title="Tooltip-Text"&gt;...&lt;/button&gt;</pre></div>

    <hr style="border:none;border-top:1px solid var(--gk-outline-variant);margin:40px 0">

    <h3>Empty State <?= since('empty') ?></h3>
    <div class="demo-card">
        <p class="demo-intro">Placeholder for empty tables or lists.</p>
        <div class="gk-table-wrap">
            <table class="gk-table">
                <thead><tr><th>Name</th><th>E-Mail</th><th>Status</th></tr></thead>
                <tbody>
                    <tr><td colspan="3"><div class="gk-empty"><span class="material-icons" style="font-size:32px;display:block;margin-bottom:8px;opacity:0.5" aria-hidden="true">inbox</span>No entries found</div></td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="demo-code"><pre>&lt;div class="gk-empty"&gt;No entries found&lt;/div&gt;</pre></div>

    <hr style="border:none;border-top:1px solid var(--gk-outline-variant);margin:40px 0">

    <h3>Header <?= since('header') ?></h3>
    <div class="demo-card" style="padding:0; overflow:hidden;">
        <?php
        $header = new Header();
        echo $header->title('Invoices')
            ->breadcrumb(['Dashboard' => '/', 'Billing' => '/faktura', 'Invoices'])
            ->search('Search...', 'q')
            ->action(Button::render('New Invoice', ['variant' => 'filled', 'color' => 'primary', 'icon' => 'add', 'size' => 'sm']))
            ->action(Button::icon('notifications', ['variant' => 'text', 'color' => 'neutral', 'title' => 'Notifications']))
            ->user('Martin Huber', [
                'avatar' => ph(72, 72, 12, 'JD'), 'role' => 'Administrator',
                'menu' => [
                    ['label' => 'Profile', 'href' => '/profil', 'icon' => 'person'],
                    ['label' => 'Settings', 'href' => '/settings', 'icon' => 'settings'],
                    'divider',
                    ['label' => 'Sign out', 'href' => '/logout', 'icon' => 'logout'],
                ],
            ])
            ->sticky()->render();
        ?>
    </div>
    <div class="demo-card" style="padding:0; overflow:hidden;">
        <p style="padding:16px 24px 0; margin:0; font-size:13px; color:var(--gk-on-surface-variant, #6b7280);">Minimal (title + user with initials only)</p>
        <?php
        $header2 = new Header();
        echo $header2->title('Dashboard')
            ->user('Anna K.', ['menu' => [['label' => 'Sign out', 'href' => '/logout', 'icon' => 'logout']]])
            ->render();
        ?>
    </div>
    <div class="demo-code"><pre>$header = new Header();
echo $header->title('Invoices')
    ->breadcrumb(['Dashboard' => '/', 'Invoices'])
    ->search('Search...', 'q')
    ->action(Button::render('New', ['icon' => 'add', 'size' => 'sm']))
    ->user('Martin Huber', ['avatar' => '...', 'menu' => [...]])
    ->sticky()->render();</pre></div>

    <hr style="border:none;border-top:1px solid var(--gk-outline-variant);margin:40px 0">

    <h3>Ortszeile <?= since('ortszeile') ?></h3>
    <p class="demo-intro">"You are here": the dashboard and the module as links, the steps between, the page last and without a link. Only the page's title is shortened. Put it in a <code>sticky()</code> header — it wraps on a phone. Resize the window to see it.</p>
    <?php
    $ortUser = ['menu' => [['label' => 'Sign out', 'href' => '/logout', 'icon' => 'logout']]];
    $ortModul = ['label' => 'Billing', 'href' => '#', 'icon' => 'receipt_long'];
    $ortFaelle = [
        'On the dashboard itself' => (new Header())->title('Dashboard')
            ->ortszeile([]),   // only the dashboard step, as the current page — never "Dashboard › Dashboard"
        'On the start page of a module' => (new Header())->title('Billing')
            ->ortszeile(['modul' => $ortModul]),   // the module anchor is the current page — no second "Billing"
        'Deep in a module' => (new Header())->title('Invoice INV-2026-001')
            ->ortszeile(['modul' => $ortModul, 'stufen' => ['Invoices' => '#'], 'aktuell' => 'Invoice INV-2026-001']),
        'A very long title' => (new Header())->title('Quarterly statement')
            ->ortszeile(['modul' => $ortModul, 'stufen' => ['Statements' => '#', '2026' => '#'], 'aktuell' => 'Quarterly statement of the fixed assets, annex 3 to the annual accounts, draft of 28 October']),
        'One row, for a header of fixed height (einzeilig)' => (new Header())->title('Quarterly statement')
            ->ortszeile(['modul' => $ortModul, 'stufen' => ['Statements of the year' => '#'], 'aktuell' => 'Quarterly statement of the fixed assets, annex 3 to the annual accounts', 'einzeilig' => true]),
    ];
    foreach ($ortFaelle as $titel => $zeile) {
        echo '<div class="demo-card demo-card-flush"><p class="demo-intro demo-intro-pad">' . htmlspecialchars($titel) . '</p>'
            . $zeile->user('Anna K.', $ortUser)->sticky()->render() . '</div>';
    }
    ?>
    <div class="demo-card demo-card-flush">
        <p class="demo-intro demo-intro-pad">The menu button with its word, for a first-time visitor</p>
        <?= (new Header())->title('Billing')->sidebarToggle(true, ['label' => true])->ortszeile(['modul' => $ortModul])->sticky()->render() ?>
    </div>
    <div class="demo-code"><pre>echo (new Header())
    -&gt;sidebarToggle(true, ['label' =&gt; true, 'controls' =&gt; 'main-nav'])   // "Menü" beside the symbol
    -&gt;ortszeile([
        'dashboard' =&gt; ['label' =&gt; 'Dashboard', 'href' =&gt; '/', 'icon' =&gt; 'home'],   // the defaults
        'modul'     =&gt; ['label' =&gt; 'Billing', 'href' =&gt; '/faktura', 'icon' =&gt; 'receipt_long'],
        'stufen'    =&gt; ['Invoices' =&gt; '/faktura/rechnungen'],
        'aktuell'   =&gt; 'Invoice INV-2026-001',   // the page: no link, aria-current="page"
    ])
    -&gt;sticky()-&gt;render();

echo Layout::skipLink('#main');                   // first in &lt;body&gt;; &lt;main id="main" tabindex="-1"&gt;
(new Sidebar('main'))-&gt;elementId('main-nav')       // the button's aria-controls
    -&gt;brand('Panel', 'dashboard', 'v1', '/')       // the brand as a link
    -&gt;mobileClose()                                // a brand-less sidebar keeps its close button
    -&gt;render();</pre></div>

    <hr class="demo-rule">

    <h3>Sidebar <?= since('sidebar') ?></h3>
    <div class="demo-pair">
    <div class="demo-card">
        <p class="demo-intro">Responsive sidebar navigation with groups, icons, badges and mobile toggle. Visible live on this page.</p>
    </div>
    <div class="demo-code"><pre>$sidebar = new Sidebar('main');
$sidebar->brand('My Project', 'dashboard', 'v0.7.0')
    ->group('Modules')
    ->item('Dashboard', '/dashboard', 'analytics', ['active' => true])
    ->item('Invoices', '/invoices', 'receipt_long', ['badge' => 3])
    ->render();

GK.sidebar.toggle(); GK.sidebar.open(); GK.sidebar.close();</pre></div>
    </div>

    <hr style="border:none;border-top:1px solid var(--gk-outline-variant);margin:40px 0">

    <h3>Themes <?= since('theme') ?></h3>
    <div class="demo-card">
        <p class="demo-intro">M3-compliant theme system with 6 themes and Dark/Light Mode.</p>
        <h3 style="margin:16px 0 12px; font-size:15px;">Layout Mode</h3>
        <div style="display:flex; gap:8px; margin-bottom:16px;">
            <?= Button::render('Header First', ['variant' => 'tonal', 'color' => 'primary', 'onclick' => "GK.layout.set('header-first')"]) ?>
            <?= Button::render('Sidebar First', ['variant' => 'tonal', 'color' => 'primary', 'onclick' => "GK.layout.set('sidebar-first')"]) ?>
        </div>
        <h3 style="margin:16px 0 12px; font-size:15px;">Theme Selection</h3>
        <?= Theme::switcher() ?>
        <h3 style="margin:24px 0 12px; font-size:15px;">Live Preview</h3>
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(min(280px, 100%), 1fr)); gap:16px;">
            <div style="background:var(--gk-surface-container); border-radius:var(--gk-radius); padding:20px; border:1px solid var(--gk-outline-variant);">
                <h4 style="margin:0 0 8px; color:var(--gk-on-surface);">Card Title</h4>
                <p style="margin:0 0 12px; color:var(--gk-on-surface-variant); font-size:13px;">Surface-container background with on-surface text colors.</p>
                <input type="text" placeholder="Input field..." style="width:100%; padding:8px 12px; border:1px solid var(--gk-outline); border-radius:var(--gk-radius-sm); background:var(--gk-surface); color:var(--gk-on-surface); margin-bottom:12px; box-sizing:border-box;">
                <div style="display:flex; gap:8px;">
                    <button class="gk-btn gk-btn-primary" style="background:var(--gk-primary); color:var(--gk-on-primary); border:none; padding:8px 16px; border-radius:var(--gk-radius-sm); cursor:pointer;">Primary</button>
                    <button style="background:var(--gk-primary-container); color:var(--gk-on-primary-container); border:none; padding:8px 16px; border-radius:var(--gk-radius-sm); cursor:pointer;">Container</button>
                    <button style="background:var(--gk-error); color:var(--gk-on-error); border:none; padding:8px 16px; border-radius:var(--gk-radius-sm); cursor:pointer;">Error</button>
                </div>
            </div>
            <div style="background:var(--gk-surface-container-high); border-radius:var(--gk-radius); padding:20px; border:1px solid var(--gk-outline-variant);">
                <h4 style="margin:0 0 12px; color:var(--gk-on-surface);">Color Roles</h4>
                <div style="display:flex; flex-wrap:wrap; gap:8px;">
                    <span style="background:var(--gk-primary); color:var(--gk-on-primary); padding:4px 12px; border-radius:99px; font-size:12px;">Primary</span>
                    <span style="background:var(--gk-secondary); color:var(--gk-on-secondary); padding:4px 12px; border-radius:99px; font-size:12px;">Secondary</span>
                    <span style="background:var(--gk-tertiary); color:var(--gk-on-tertiary); padding:4px 12px; border-radius:99px; font-size:12px;">Tertiary</span>
                    <span style="background:var(--gk-error); color:var(--gk-on-error); padding:4px 12px; border-radius:99px; font-size:12px;">Error</span>
                </div>
                <div style="display:flex; flex-wrap:wrap; gap:8px; margin-top:8px;">
                    <span style="background:var(--gk-primary-container); color:var(--gk-on-primary-container); padding:4px 12px; border-radius:99px; font-size:12px;">Primary Container</span>
                    <span style="background:var(--gk-secondary-container); color:var(--gk-on-secondary-container); padding:4px 12px; border-radius:99px; font-size:12px;">Secondary Container</span>
                    <span style="background:var(--gk-tertiary-container); color:var(--gk-on-tertiary-container); padding:4px 12px; border-radius:99px; font-size:12px;">Tertiary Container</span>
                </div>
                <div style="display:flex; flex-wrap:wrap; gap:8px; margin-top:8px;">
                    <span style="background:var(--gk-surface); color:var(--gk-on-surface); padding:4px 12px; border-radius:99px; font-size:12px; border:1px solid var(--gk-outline);">Surface</span>
                    <span style="background:var(--gk-surface-container); color:var(--gk-on-surface); padding:4px 12px; border-radius:99px; font-size:12px; border:1px solid var(--gk-outline);">Container</span>
                    <span style="background:var(--gk-surface-container-highest); color:var(--gk-on-surface); padding:4px 12px; border-radius:99px; font-size:12px; border:1px solid var(--gk-outline);">Highest</span>
                </div>
            </div>
        </div>
    </div>
    <div class="demo-code"><pre>Theme::set('ocean', 'dark');
echo Theme::bodyTag('gk-root');
echo Theme::switcher();
GK.theme.set('forest'); GK.theme.toggleMode(); GK.theme.restore();
GK.theme.setMode('auto');   // 'light' | 'dark' | 'auto' — auto follows the system (1.114.0)</pre></div>

    <h3 class="demo-h3" id="action-group">Action group <?= since('action-group') ?></h3>
    <p class="demo-intro"><code>ActionGroup::html()</code> returns a row of small buttons in one go; <code>render()</code> prints it. An item with <code>'showIf' => false</code> is left out, <code>'pill'</code> rounds it, and an icon-only item needs <code>'title'</code> for its name.</p>
        <?php
        showcase(function () {
            echo ActionGroup::html([
                ['icon' => 'edit', 'title' => 'Edit'],
                ['icon' => 'content_copy', 'title' => 'Duplicate'],
                ['label' => 'Archive', 'icon' => 'archive', 'variant' => 'tonal', 'color' => 'warning'],
                ['icon' => 'delete', 'title' => 'Delete', 'color' => 'danger', 'showIf' => false],
                ['label' => 'Send', 'icon' => 'send', 'variant' => 'filled', 'color' => 'primary', 'pill' => true],
            ]);
        });
        ?>

    <h3 class="demo-h3" id="button-options">Button: size xs, icon on the right, full width, data and aria attributes</h3>
        <?php
        showcase(function () {
            echo '<div class="gk-flex gk-gap-md gk-items-center" style="flex-wrap:wrap">';
            echo Button::render('Extra small', ['variant' => 'outlined', 'size' => 'xs']);
            echo Button::render('Next', ['icon' => 'arrow_forward', 'iconPosition' => 'right', 'variant' => 'filled', 'color' => 'primary']);
            echo Button::render('Page 3', ['variant' => 'tonal', 'data' => ['row' => 3], 'attrs' => ['aria-current' => 'page']]);
            echo '</div>';
            echo '<div class="gk-mt-2">' . Button::render('Full width', ['variant' => 'outlined', 'fullWidth' => true]) . '</div>';
        });
        ?>

    <h3 class="demo-h3" id="utilities">Utility classes <?= since('utilities') ?></h3>
    <p class="demo-intro">Every utility class on a small sample, generated from <code>css/blocks.json</code>, so a new class shows up here without anyone writing it twice. Spacing and size classes act on the sample's box, colours and type on its text.</p>
    <div class="demo-util-grid">
        <?php foreach (array_keys(gkBlocks()['utilities']['classes']) as $utilityClass): ?>
        <div class="demo-util-cell"><code><?= htmlspecialchars($utilityClass) ?></code><div class="demo-util-box"><span class="<?= htmlspecialchars($utilityClass) ?>">Aa</span></div></div>
        <?php endforeach; ?>
    </div>
</div>

