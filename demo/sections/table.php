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
<!-- ===== TABLE ===== -->
<div class="demo-section active" data-section="table">
    <h2>Table <?= since('table') ?></h2>

    <h3 style="margin: 32px 0 16px;">Complete table with all features</h3>
    <p class="demo-intro">The "VAT" header has a help icon, and the delete button a tooltip of its own instead of the browser's title <?= since('tooltip') ?>. Two articles are on invoices: their delete is locked with the reason (<code>'lockedIf'</code>, 1.128.0), and Back after a search shows the list as it was left. The PDF symbol is one of the 40 row-action symbols that are SVG since 1.129.0; the buttons rest grey at full strength and take their colour on hover.</p>
        <?php
        $articles = $demoRows([
            ['article_id' => 1, 'article_number' => 'ART-001', 'name' => 'Webdesign Paket S', 'unit' => 'psch', 'net_price' => 1200.00, 'tax_rate' => 20, 'is_active' => 'active', 'in_use' => 'On 3 invoices'],
            ['article_id' => 2, 'article_number' => 'ART-002', 'name' => 'Hosting Standard', 'unit' => 'Stk', 'net_price' => 9.90, 'tax_rate' => 20, 'is_active' => 'active'],
            ['article_id' => 3, 'article_number' => 'ART-003', 'name' => 'SEO Beratung', 'unit' => 'h', 'net_price' => 95.00, 'tax_rate' => 20, 'is_active' => 'inactive'],
            ['article_id' => 4, 'article_number' => 'ART-004', 'name' => 'Logo Design', 'unit' => 'psch', 'net_price' => 450.00, 'tax_rate' => 20, 'is_active' => 'draft'],
            ['article_id' => 5, 'article_number' => 'ART-005', 'name' => 'Newsletter Setup', 'unit' => 'psch', 'net_price' => 350.00, 'tax_rate' => 20, 'is_active' => 'active'],
            ['article_id' => 6, 'article_number' => 'ART-006', 'name' => 'Social Media Paket', 'unit' => 'psch', 'net_price' => 680.00, 'tax_rate' => 20, 'is_active' => 'active'],
            ['article_id' => 7, 'article_number' => 'ART-007', 'name' => 'E-Mail Marketing', 'unit' => 'psch', 'net_price' => 420.00, 'tax_rate' => 20, 'is_active' => 'draft'],
            ['article_id' => 8, 'article_number' => 'ART-008', 'name' => 'Content Erstellung', 'unit' => 'h', 'net_price' => 75.00, 'tax_rate' => 20, 'is_active' => 'active'],
            ['article_id' => 9, 'article_number' => 'ART-009', 'name' => 'Server Administration', 'unit' => 'h', 'net_price' => 110.00, 'tax_rate' => 20, 'is_active' => 'inactive'],
            ['article_id' => 10, 'article_number' => 'ART-010', 'name' => 'SSL Zertifikat', 'unit' => 'Stk', 'net_price' => 49.00, 'tax_rate' => 20, 'is_active' => 'active'],
            ['article_id' => 11, 'article_number' => 'ART-011', 'name' => 'Domain Registration', 'unit' => 'Stk', 'net_price' => 15.00, 'tax_rate' => 20, 'is_active' => 'active'],
            ['article_id' => 12, 'article_number' => 'ART-012', 'name' => 'Webdesign Paket L', 'unit' => 'psch', 'net_price' => 3500.00, 'tax_rate' => 20, 'is_active' => 'active', 'in_use' => 'On 1 invoice'],
            ['article_id' => 13, 'article_number' => 'ART-013', 'name' => 'App Entwicklung', 'unit' => 'h', 'net_price' => 125.00, 'tax_rate' => 20, 'is_active' => 'draft'],
            ['article_id' => 14, 'article_number' => 'ART-014', 'name' => 'Datenbank Migration', 'unit' => 'psch', 'net_price' => 890.00, 'tax_rate' => 20, 'is_active' => 'active'],
            ['article_id' => 15, 'article_number' => 'ART-015', 'name' => 'Security Audit', 'unit' => 'psch', 'net_price' => 1500.00, 'tax_rate' => 20, 'is_active' => 'inactive'],
        ]);
        showcase(function () use ($articles) {
                    $table = new Table('articles');
                    $table->setData($articles)
                        ->search(['article_number', 'name'])
                        ->column('article_number', 'Article No.', ['width' => '120px', 'sortable' => true, 'nowrap' => true, 'muted' => true])
                        ->column('name', 'Description', ['sortable' => true, 'nowrap' => true])
                        ->column('unit', 'Unit', ['width' => '80px', 'nowrap' => true])
                        ->column('net_price', 'Net', ['format' => 'currency', 'align' => 'right', 'width' => '100px', 'nowrap' => true])
                        ->column('tax_rate', 'VAT', ['format' => 'percent', 'width' => '80px', 'nowrap' => true,
                                                     'tooltip' => 'The rate the net price is taxed at'])
                        ->column('is_active', 'Status', ['format' => 'label', 'nowrap' => true])
                        ->filter('is_active', 'select', ['options' => ['active' => 'Active', 'inactive' => 'Inactive', 'draft' => 'Draft'], 'placeholder' => 'All Status'])
                        ->button('edit', ['icon' => 'edit', 'class' => 'primary', 'position' => 'right', 'params' => ['id' => 'article_id']])
                        ->button('pdf', ['icon' => 'picture_as_pdf', 'class' => 'secondary', 'position' => 'right', 'params' => ['id' => 'article_id'], 'title' => 'PDF'])
                        ->button('delete', ['icon' => 'delete', 'class' => 'danger', 'position' => 'right', 'params' => ['id' => 'article_id'],
                                            'tooltip' => ['text' => 'Deletes the article', 'tone' => 'danger'], 'lockedIf' => 'in_use'])
                        ->newButton('New Article', ['icon' => 'add'])
                        ->nowrap(true)
                        // A box of its own where the content is narrower than the table
                        // (a 900px window beside the sidebar); the page no longer scrolls sideways.
                        ->stickyColumn()
                        ->paginate(5)
                        ->render();
        });
        ?>

    <h3 style="margin: 32px 0 16px;">Invoice list with date and currency formatting</h3>
        <?php
        $invoiceData = [
            ['number' => 'RE-2026-001', 'customer' => 'Mustermann GmbH', 'date' => '2026-02-01', 'due_date' => '2026-03-01', 'total' => 1450.00, 'status' => 'paid'],
            ['number' => 'RE-2026-002', 'customer' => 'Tech Solutions AG', 'date' => '2026-02-05', 'due_date' => '2026-03-05', 'total' => 3200.00, 'status' => 'pending'],
            ['number' => 'RE-2026-003', 'customer' => 'Weber & Partner', 'date' => '2026-02-08', 'due_date' => '2026-03-08', 'total' => 890.50, 'status' => 'overdue'],
            ['number' => 'RE-2026-004', 'customer' => 'Digital Agentur Wien', 'date' => '2026-02-10', 'due_date' => '2026-03-10', 'total' => 5600.00, 'status' => 'draft'],
            ['number' => 'RE-2026-005', 'customer' => 'Startup Hub Vienna', 'date' => '2026-02-12', 'due_date' => '2026-03-12', 'total' => 2100.00, 'status' => 'paid'],
            ['number' => 'RE-2026-006', 'customer' => 'Cafe Central KG', 'date' => '2026-02-14', 'due_date' => '2026-03-14', 'total' => 780.00, 'status' => 'pending'],
            ['number' => 'RE-2026-007', 'customer' => 'Alpen Consulting', 'date' => '2026-02-15', 'due_date' => '2026-03-15', 'total' => 4200.00, 'status' => 'paid'],
            ['number' => 'RE-2026-008', 'customer' => 'Donau Logistics', 'date' => '2026-02-17', 'due_date' => '2026-03-17', 'total' => 1890.00, 'status' => 'pending'],
            ['number' => 'RE-2026-009', 'customer' => 'Wiener Werkstatt', 'date' => '2026-02-18', 'due_date' => '2026-03-18', 'total' => 560.00, 'status' => 'draft'],
            ['number' => 'RE-2026-010', 'customer' => 'Graz IT Services', 'date' => '2026-02-20', 'due_date' => '2026-03-20', 'total' => 3450.00, 'status' => 'paid'],
            ['number' => 'RE-2026-011', 'customer' => 'Salzburg Media', 'date' => '2026-02-22', 'due_date' => '2026-03-22', 'total' => 1200.00, 'status' => 'overdue'],
            ['number' => 'RE-2026-012', 'customer' => 'Linz Digital', 'date' => '2026-02-25', 'due_date' => '2026-03-25', 'total' => 2750.00, 'status' => 'pending'],
        ];
        $invoiceTable = new Table('invoices');
        $invoiceTable->setData($invoiceData)
            ->search(['number', 'customer'])
            ->column('number', 'Inv. No.', ['width' => '120px', 'nowrap' => true, 'sortable' => true])
            ->column('customer', 'Customer', ['sortable' => true, 'nowrap' => true])
            ->column('date', 'Date', ['format' => 'date', 'width' => '100px', 'sortable' => true])
            ->column('due_date', 'Due', ['format' => 'date', 'width' => '100px'])
            ->column('total', 'Amount', ['format' => 'currency', 'align' => 'right', 'width' => '120px', 'sortable' => true])
            ->column('status', 'Status', ['format' => 'label', 'width' => '100px'])
            ->button('view', ['icon' => 'visibility', 'position' => 'left'])
            ->button('edit', ['icon' => 'edit', 'class' => 'primary'])
            ->button('pdf', ['icon' => 'picture_as_pdf'])
            ->button('delete', ['icon' => 'delete', 'class' => 'danger'])
            ->newButton('New Invoice', ['icon' => 'add'])
            ->nowrap(true)
            // Seven columns that do not wrap and buttons on both sides are wider than the
            // content beside an open sidebar on a 1280px screen: without a box of its own the
            // table pushed the whole page sideways. stickyColumn() (1.107.0) gives it one, with
            // the number and the view button in front.
            ->stickyColumn()
            ->paginate(5)
            ->render();
        ?>

    <h3 style="margin: 32px 0 16px;">Compact table without toolbar</h3>
        <?php
        $userData = [
            ['name' => 'Martin Huber', 'email' => 'martin@example.com', 'role' => 'admin', 'active' => 1],
            ['name' => 'Anna Schneider', 'email' => 'anna@example.com', 'role' => 'editor', 'active' => 1],
            ['name' => 'Thomas Berger', 'email' => 'thomas@example.com', 'role' => 'viewer', 'active' => 0],
            ['name' => 'Lisa Wagner', 'email' => 'lisa@example.com', 'role' => 'editor', 'active' => 1],
            ['name' => 'Peter Gruber', 'email' => 'peter@example.com', 'role' => 'admin', 'active' => 0],
        ];
        $miniTable = new Table('users');
        $miniTable->setData($userData)
            ->column('name', 'Name', ['sortable' => true])
            ->column('email', 'E-Mail', ['format' => 'email'])
            ->column('role', 'Role', ['format' => 'label'])
            ->column('active', 'Active', ['format' => 'boolean'])
            ->toolbar(false)
            ->paginate(false)
            ->render();
        ?>

    <h3 class="demo-h3">Rows that open a side sheet <?= since('row-link', 'sheet') ?></h3>
    <p class="demo-intro">List table <?= since('table-list') ?> · column priority <?= since('col-priority') ?> · who cell <?= since('cell-who') ?></p>
    <p class="demo-intro">Read in the row, change in the sheet: a click anywhere on a row opens its record beside the list — full screen on a phone. One name cell with two lines, groups instead of a status label per row, and colour only for what needs acting on. The six rules behind it are in the agent skill. <code>size('list')</code> sets the row they describe, and <code>'priority'</code> lets columns give way by the list's own width — open the sheet or narrow the window and watch "Last seen" go first.</p>
        <?php
        $people = require __DIR__ . '/../form/_people.php';
        showcase(function () use ($people) {
            // Rule 4: colour only where something needs acting on — a count that
            // is fine stays plain text.
            foreach ($people as &$p) {
                $p['failing_html'] = $p['failing'] > 0
                    ? '<span class="gk-label gk-label-red">' . (int) $p['failing'] . ' failing</span>'
                    : '—';
            }
            unset($p);

            (new Table('demo-people'))
                ->setData($people)
                ->caption('Users')
                ->size('list')
                ->groupBy('access', ['with' => 'With access', 'without' => 'Without access'])
                ->rowLink(['sheet' => 'demo-person-sheet', 'url' => 'form/f_person_sheet.php'])
                ->column('name', 'User', ['sub' => 'email'])
                ->column('plan', 'Plan', ['priority' => 3])
                ->column('seen', 'Last seen', ['muted' => true, 'priority' => 4])
                ->column('failing_html', 'Last 30 days', ['format' => 'html', 'priority' => 2])
                ->toolbar(false)
                ->render();
        });
        ?>
    <div class="gk-sheet" id="demo-person-sheet" hidden>
        <div class="gk-sheet-header">
            <h2 class="gk-sheet-title">User</h2>
            <button type="button" class="gk-sheet-close">&times;</button>
        </div>
        <div class="gk-sheet-body"></div>
        <div class="gk-sheet-footer">
            <button type="button" class="gk-btn gk-btn-outlined gk-btn-neutral" data-gk-sheet-close>Done</button>
        </div>
    </div>

    <h3 class="demo-h3">One row standing for many <?= since('table-more') ?></h3>
    <p class="demo-intro">What is rarely needed folds into one row. The toggle shows and hides the rows its <code>aria-controls</code> names; the chevron and <code>aria-expanded</code> carry the state, so the label never has to change.</p>
        <?php
        showcase(function () {
            echo <<<'HTML'
            <div class="gk-table-wrap gk-table-mobile-card">
              <table class="gk-table">
                <caption class="gk-sr-only">Users and chart profiles</caption>
                <thead><tr><th scope="col">User</th><th scope="col">Plan</th></tr></thead>
                <tbody>
                  <tr><td data-label="User">Anna Schneider</td><td data-label="Plan">Pro</td></tr>
                  <tr><td data-label="User">Thomas Berger</td><td data-label="Plan">Team</td></tr>
                  <tr class="gk-table-more"><td colspan="2">
                    <button type="button" class="gk-table-more-toggle" aria-expanded="false" aria-controls="demo-profiles">
                      <span class="gk-table-more-name">3 chart profiles</span>
                      <span class="gk-cell-sub">Created from charts — they cannot sign in and have no plan.</span>
                    </button>
                  </td></tr>
                </tbody>
                <tbody id="demo-profiles" hidden>
                  <tr><td data-label="User">Profile: Berlin, 1984</td><td data-label="Plan">—</td></tr>
                  <tr><td data-label="User">Profile: Graz, 1991</td><td data-label="Plan">—</td></tr>
                  <tr><td data-label="User">Profile: Linz, 2002</td><td data-label="Plan">—</td></tr>
                </tbody>
              </table>
            </div>
            HTML;
        });
        ?>

    <h3 style="margin: 32px 0 16px;">Sizes</h3>
    <?php /* 300px, not 240: size('lg') needs about 290px for its three columns, and at 240 a
             1100px window put it in a narrower track and pushed the page sideways. */ ?>
    <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(min(300px,100%),1fr)); gap:20px;">
        <?php
        $sizeData = [
            ['name' => 'Widget A', 'value' => '1.200 €', 'status' => 'active'],
            ['name' => 'Widget B', 'value' => '340 €', 'status' => 'inactive'],
            ['name' => 'Widget C', 'value' => '890 €', 'status' => 'active'],
        ];
        $sizeLabels = ['sm' => 'Compact', 'md' => 'Standard', 'lg' => 'Spacious'];
        foreach (['sm', 'md', 'lg'] as $sz) {
            echo '<div>';
            echo '<div style="font-size:13px;font-weight:500;color:var(--gk-on-surface-variant);display:flex;align-items:center;gap:6px;margin-bottom:8px"><span style="font-size:11px;font-family:monospace;background:var(--gk-surface-container);padding:2px 8px;border-radius:4px;color:var(--gk-on-surface-variant)">size(\'' . $sz . '\')</span> ' . $sizeLabels[$sz] . '</div>';
            $t = new Table('size-' . $sz);
            $t->setData($sizeData)
                ->column('name', 'Name')
                ->column('value', 'Value')
                ->column('status', 'Status', ['format' => 'label'])
                ->size($sz)->toolbar(false)->paginate(false)->render();
            echo "</div>";
        }
        ?>
    </div>
    <div class="demo-code"><pre>$table->size('sm');  // compact
$table->size('md');  // standard (default)
$table->size('lg');  // spacious</pre></div>

    <h3 style="margin: 32px 0 16px;">Display variants</h3>
    <?php
    $varData = $demoRows([
        ['name' => 'Webdesign Paket', 'price' => '1.200 €', 'status' => 'active'],
        ['name' => 'Hosting Standard', 'price' => '9,90 €', 'status' => 'active'],
        ['name' => 'SEO Beratung', 'price' => '95 €', 'status' => 'inactive'],
        ['name' => 'Logo Design', 'price' => '450 €', 'status' => 'draft'],
    ]);
    $variants = [
        'default'      => 'Default',
        'bordered'     => 'Bordered',
        'striped'      => 'Striped',
        'celled'       => 'Celled',
        'padded'       => 'Padded',
        'compact'      => 'Compact',
        'selectable'   => 'Selectable',
        'minimal'      => 'Minimal',
        'flat'         => 'Flat',
        'inverted'     => 'Inverted',
    ];
    echo '<div style="display:flex;flex-direction:column;gap:24px;margin-bottom:24px;">';
    foreach ($variants as $var => $label) {
        echo '<div>';
        echo '<div style="font-size:13px;font-weight:500;color:var(--gk-on-surface-variant);display:flex;align-items:center;gap:6px;margin-bottom:8px"><span style="font-size:11px;font-family:monospace;background:var(--gk-surface-container);padding:2px 8px;border-radius:4px;color:var(--gk-on-surface-variant)">variant(\'' . $var . '\')</span> ' . $label . '</div>';
        $t = new Table('var-' . $var);
        $t->setData($varData)
            ->column('name', 'Description')
            ->column('price', 'Price')
            ->column('status', 'Status', ['format' => 'label'])
            ->variant($var)->toolbar(false)->paginate(false)->render();
        echo '</div>';
    }
    echo '</div>';
    ?>
    <h3 style="margin: 32px 0 16px;">Definition table</h3>
    <p class="demo-intro">First column as label/key — ideal for detail views.</p>
    <div class="gk-table-wrap">
        <table class="gk-table gk-table-definition">
            <tbody>
                <tr><td>Company name</td><td>SSI Schaefer IT Solutions GmbH</td></tr>
                <tr><td>Founded</td><td>2003</td></tr>
                <tr><td>Location</td><td>Wien, Oesterreich</td></tr>
                <tr><td>Employees</td><td>24</td></tr>
                <tr><td>Website</td><td>ssi.at</td></tr>
                <tr><td>Status</td><td><span class="gk-label gk-label-green">Active</span></td></tr>
            </tbody>
        </table>
    </div>

    <div class="demo-code"><pre>$table->variant('default');     // Default
$table->variant('bordered');    // Full border lines
$table->variant('striped');     // Zebra stripes
$table->variant('celled');      // Grid lines around each cell
$table->variant('padded');      // Extra spacing
$table->variant('compact');     // Compact, more rows
$table->variant('selectable');  // Hover cursor, clickable
$table->variant('minimal');     // Only separator, no border
$table->variant('flat');        // Completely flat
$table->variant('inverted');    // Dark table (also in Light Mode)

// A variant is one slot: a second call REPLACES the first, it does not add.
$table->variant('celled')->variant('padded');   // celled is dropped — padded wins

// Variant and size are separate slots, so those two do stack:
$table->variant('striped')->size('sm');</pre></div>

    <h3 class="demo-h3" id="table-sticky">Sticky head and column <?= since('table') ?></h3>
    <p class="demo-intro"><code>stickyHead()</code> keeps the header row in view while the rows scroll under it — on the page, or in a box as high as you say (<code>'320px'</code> here). <code>stickyColumn()</code> keeps the first column in view while the table scrolls sideways, with the checkbox in front of it. Scroll the box both ways. On a phone a table in card mode stays as it is.</p>
        <?php
        $stickyArticles = array_map(static fn (array $a): array => $a + [
            'gross'    => round($a['net_price'] * 1.2, 2),
            'supplier' => 'Supplier ' . chr(64 + $a['article_id']),
            'stock'    => $a['article_id'] * 7,
        ], $articles);
        showcase(function () use ($stickyArticles) {
            (new Table('demo-sticky'))
                ->setData($stickyArticles)
                ->caption('Articles with a sticky head and column')
                ->selectable('article_id')
                ->column('name', 'Description', ['sortable' => true, 'minWidth' => '200px'])
                ->column('article_number', 'Article No.', ['muted' => true])
                ->column('unit', 'Unit')
                ->column('net_price', 'Net', ['format' => 'currency', 'sortable' => true])
                ->column('tax_rate', 'VAT', ['format' => 'percent'])
                ->column('gross', 'Gross', ['format' => 'currency'])
                ->column('stock', 'Stock', ['align' => 'right'])
                ->column('supplier', 'Supplier', ['minWidth' => '360px'])
                ->column('is_active', 'Status', ['format' => 'label'])
                ->button('edit', ['icon' => 'edit', 'params' => ['id' => 'article_id']])
                ->nowrap()
                ->toolbar(false)
                ->stickyHead('320px')
                ->stickyColumn()
                ->render();
        });
        ?>

    <h3 class="demo-h3" id="table-columns">Columns to show and hide <?= since('col-toggle') ?></h3>
    <p class="demo-intro"><code>columnToggle()</code> puts a "Columns" button in the toolbar. Untick a column in its menu and it is gone from the header and the rows — through a sort, a search and a page change, and on your next visit too: the browser remembers it for this table. <code>'hidden' =&gt; true</code> starts a column hidden (here the unit and the VAT), <code>'toggle' =&gt; false</code> keeps one on screen (the article number). The CSV holds what the table shows; on a phone a hidden column is left out of the card, and if it was the card's title the next column takes its place.</p>
        <?php
        showcase(function () use ($articles) {
            (new Table('demo-columns'))
                ->setData(array_slice($articles, 0, 8))
                ->search(['article_number', 'name'])
                ->column('article_number', 'Article No.', ['muted' => true, 'toggle' => false])
                ->column('name', 'Description', ['sortable' => true])
                ->column('unit', 'Unit', ['hidden' => true])
                ->column('net_price', 'Net', ['format' => 'currency', 'align' => 'right', 'sortable' => true])
                ->column('tax_rate', 'VAT', ['format' => 'percent', 'hidden' => true])
                ->column('is_active', 'Status', ['format' => 'label'])
                ->columnToggle()
                ->exportCsv('articles.csv')
                ->paginate(5)
                ->render();
        });
        ?>

    <h3 class="demo-h3" id="table-density">Row spacing, chosen at the screen <?= since('table') ?></h3>
    <p class="demo-intro"><code>densityToggle()</code> puts Compact · Normal · Roomy into the toolbar. The steps are those of <code>Theme::density()</code> (<code>konsole</code>, the default, <code>weit</code>), applied to this table only and remembered for its id in this browser — choose one, sort or search, reload the page. Normal is GridKit's default rhythm; on a page set to <code>konsole</code> the control starts at Compact. Without the script the control is not shown.</p>
        <?php
        showcase(function () use ($articles) {
            (new Table('demo-density'))
                ->setData(array_slice($articles, 0, 6))
                ->search(['name'])
                ->column('article_number', 'No.', ['muted' => true, 'width' => '110px'])
                ->column('name', 'Description', ['sortable' => true])
                ->column('net_price', 'Net', ['format' => 'currency', 'align' => 'right'])
                ->column('is_active', 'Status', ['format' => 'label'])
                ->button('edit', ['icon' => 'edit', 'position' => 'right', 'params' => ['id' => 'article_id']])
                ->densityToggle()
                ->render();
        });
        ?>

    <h3 class="demo-h3" id="table-edit">Editing in the cell <?= since('table') ?></h3>
    <p class="demo-intro"><code>editable()</code> and a column's <code>'edit'</code> (<code>'text'</code>, <code>'number'</code> or <code>'select'</code>) let a value be changed where it stands: click a cell with the dotted line, or Tab to it and press Enter. Enter or leaving the field saves, Escape puts the old value back, Tab saves and opens the next one. The field holds the stored value (1200, not €1,200.00). Every change fires <code>gk:celledit</code>; with a URL GridKit posts it there and shows the answer. This page has no server, so the listener below answers with <code>respondWith()</code> after a moment, the way an endpoint would — and refuses a negative price.</p>
        <?php
        showcase(function () use ($articles) {
            (new Table('demo-edit'))
                ->setData(array_slice($articles, 0, 4))
                ->caption('Articles, editable in the cell')
                ->editable('article_id')           // with a URL: ->editable('article_id', '/api/article-cell.php')
                ->column('article_number', 'No.', ['muted' => true, 'width' => '110px'])
                ->column('name', 'Description', ['edit' => 'text'])
                ->column('net_price', 'Net', ['format' => 'currency', 'edit' => 'number'])
                ->column('is_active', 'Status', ['format' => 'label', 'edit' => 'select', 'labels' => [
                    'active'   => ['color' => 'green', 'text' => 'Active'],
                    'draft'    => ['color' => 'orange', 'text' => 'Draft'],
                    'inactive' => ['color' => 'gray', 'text' => 'Inactive'],
                ]])
                ->toolbar(false)
                ->render();
        });
        ?>
    <script>
    document.addEventListener('gk:celledit', function (e) {
        if (e.detail.tableId !== 'demo-edit') return;
        // No server on this page: answer after a moment, as an endpoint would ({ ok, value?, error? }).
        e.detail.respondWith(new Promise(function (done) {
            setTimeout(function () {
                done(e.detail.column === 'net_price' && e.detail.value < 0
                    ? { ok: false, error: 'A price cannot be negative.' }
                    : { ok: true });
            }, 400);
        }));
    });
    </script>

    <h3 style="margin: 32px 0 16px;">Mobile-Responsive</h3>
    <p class="demo-intro">Resize the browser window to &lt;768px to see the mobile layout. In card mode the first line of a card is its head: the checkbox, the left buttons, the title and the right buttons side by side, then the other fields without rules (since 1.106.0). The title is the first column that is not <code>'muted'</code> — here the description, because the article number is muted; on a row with <code>rowLink()</code> it is the cell with the link.</p>

    <div style="overflow:hidden">
        <div style="padding:10px 16px;border-bottom:1px solid var(--gk-border);background:var(--gk-bg-muted);font-size:12px;font-weight:600;color:var(--gk-text-muted);letter-spacing:.04em;text-transform:uppercase;">mobile('card') – Standard</div>
        <?php
        $mobileData = [
            ['nr' => 'ART-001', 'name' => 'Webdesign Paket S', 'price' => '1.200 €', 'status' => 'active'],
            ['nr' => 'ART-002', 'name' => 'Hosting Standard', 'price' => '9,90 €', 'status' => 'active'],
            ['nr' => 'ART-003', 'name' => 'SEO Beratung', 'price' => '95 €', 'status' => 'inactive'],
        ];
        $t = new Table('mobile-card');
        $t->setData($mobileData)
            ->column('nr', 'Article No.', ['muted' => true])
            ->column('name', 'Description')
            ->column('price', 'Price')
            ->column('status', 'Status', ['format' => 'label'])
            ->button('edit', ['icon' => 'edit', 'position' => 'right'])
            ->mobile('card')->toolbar(false)->paginate(false)->render();
        ?>
    </div>
    <div style="margin-top:16px;overflow:hidden">
        <div style="padding:10px 16px;border-bottom:1px solid var(--gk-border);background:var(--gk-bg-muted);font-size:12px;font-weight:600;color:var(--gk-text-muted);letter-spacing:.04em;text-transform:uppercase;">mobile('scroll') – Horizontal Scroll</div>
        <?php
        $t = new Table('mobile-scroll');
        $t->setData($mobileData)
            ->column('nr', 'Article No.')
            ->column('name', 'Description')
            ->column('price', 'Price')
            ->column('status', 'Status', ['format' => 'label'])
            ->mobile('scroll')->toolbar(false)->paginate(false)->render();
        ?>
    </div>
    <div style="margin-top:16px;overflow:hidden">
        <div style="padding:10px 16px;border-bottom:1px solid var(--gk-border);background:var(--gk-bg-muted);font-size:12px;font-weight:600;color:var(--gk-text-muted);letter-spacing:.04em;text-transform:uppercase;">hideOnMobile – Hide columns</div>
        <?php
        $t = new Table('mobile-hide');
        $t->setData($mobileData)
            ->column('nr', 'Article No.')
            ->column('name', 'Description')
            ->column('price', 'Price', ['hideOnMobile' => true])
            ->column('status', 'Status', ['format' => 'label'])
            ->mobile('scroll')->toolbar(false)->paginate(false)->render();
        ?>
    </div>

    <div class="demo-code"><pre>// Demo 1: Complete article table with pagination
$table = new Table('articles');
$table->setData($articles)
    ->search(['article_number', 'name'])
    ->column('name', 'Description', ['sortable' => true])
    ->column('net_price', 'Net', ['format' => 'currency'])
    ->column('tax_rate', 'VAT', ['format' => 'percent'])
    ->column('is_active', 'Status', ['format' => 'label'])
    ->button('edit', ['icon' => 'edit', 'class' => 'primary'])
    ->button('delete', ['icon' => 'delete', 'class' => 'danger'])
    ->newButton('New Article', ['icon' => 'add'])
    ->nowrap(true)
    ->paginate(5)
    ->render();

// Mobile-Responsive
$table->mobile('card');      // Cards on mobile (default): the first column that is
                             // not 'muted' is the card's title, beside the buttons
$table->mobile('scroll');    // Horizontal Scroll
$table->column('desc', 'Description', ['hideOnMobile' => true]);</pre></div>

    <h3 class="demo-h3" id="table-select">Selection, bulk actions, CSV export, footer, a link in a cell <?= since('table') ?></h3>
    <p class="demo-intro"><code>selectable()</code> adds a checkbox column, a select-all in the header and a bulk bar that appears while rows are ticked; its delete button fires <code>gk:bulkdelete</code> with <code>{ ids, tableId }</code>. <code>footer()</code> writes a summary row, <code>loadTime()</code> the milliseconds, <code>toolbarHtml()</code> puts your own markup into the toolbar, <code>'href'</code> turns a cell into a link with a quiet second line from <code>'sub'</code>, <code>bulkAction()</code> adds a button to the bulk bar (it fires <code>gk:bulkaction</code>; tick a row and try it) and <code>exportCsv()</code> a toolbar button that downloads what the table shows, after the search and the sort, as CSV.</p>
        <?php
        showcase(function () use ($articles) {
            (new Table('demo-select'))
                ->setData(array_slice($articles, 0, 6))
                ->caption('Articles with a selection')
                ->selectable('article_id')
                ->search(['name'])
                ->toolbarHtml('<span class="gk-label gk-label-blue">toolbarHtml()</span>')
                ->column('article_number', 'No.', ['muted' => true, 'width' => '110px'])
                ->column('name', 'Description', ['href' => '#table-select', 'sub' => 'unit', 'sortable' => true])
                ->column('net_price', 'Net', ['format' => 'currency', 'align' => 'right'])
                ->column('is_active', 'Status', ['format' => 'label'])
                ->footer(['Total', ['text' => '2,849.40', 'align' => 'right', 'bold' => true]])
                ->loadTime(37)
                ->bulkAction('archive', ['icon' => 'archive', 'label' => 'Archive'])
                ->bulkAction('void', ['icon' => 'block', 'confirm' => 'Void the selected articles?', 'color' => 'danger'])
                ->exportCsv('articles.csv')
                ->stickyColumn()   // a box of its own just above the phone width, where it was 8px too wide
                ->render();
        });
        ?>
    <script>document.addEventListener('gk:bulkaction', function (e) { GK.toast.info(e.detail.action + ': ' + e.detail.ids.length + ' article(s)'); });</script>
</div>

