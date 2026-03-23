<?php
require_once __DIR__ . '/../autoload.php';
use GridKit\Table;
use GridKit\Form;
use GridKit\Modal;
use GridKit\StatCards;
use GridKit\Sidebar;
use GridKit\FilterChips;
use GridKit\YearFilter;
use GridKit\Header;
use GridKit\Button;
use GridKit\Theme;
use GridKit\Layout;
use GridKit\Lang;

// Language switcher — ?lang=de or ?lang=en
$lang = $_GET['lang'] ?? $_COOKIE['gk_lang'] ?? 'en';
if (!in_array($lang, ['en', 'de'])) $lang = 'en';
Lang::set($lang);
if (isset($_GET['lang'])) {
    setcookie('gk_lang', $lang, time() + 86400 * 365, '/');
}

$version = trim(file_get_contents(__DIR__ . '/../VERSION'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GridKit Demo v<?= $version ?></title>
    <link rel="stylesheet" href="../css/gridkit.css">
    <link rel="stylesheet" href="../css/themes.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <link rel="stylesheet" href="../vendor/ckeditor5/ckeditor5.css">
    <script src="../vendor/ckeditor5/ckeditor5.umd.js"></script>
    <style>
        body { margin:0; padding:0; background:var(--gk-surface-container, #f0f1f3); font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; color:var(--gk-on-surface, #1f2937); }
        .demo-section { max-width:1100px; margin:24px auto; padding:0 24px; display:none; }
        .demo-section.active { display:block; }
        .demo-section h2 { font-size:20px; margin:0 0 16px; color:var(--gk-on-surface, #374151); }
        .demo-section .gk-form { max-width:none; }
        .demo-section .gk-richtext-wrap { border-width:1px; }
        .demo-card { background:var(--gk-surface, #fff); border-radius:8px; padding:24px; border:1px solid transparent; box-shadow:var(--gk-shadow); margin-bottom:24px; }
        [data-gk-mode="dark"] .demo-card, .gk-dark .demo-card {
            background:var(--gk-surface-container);
            border:1px solid rgba(255,255,255,0.14);
            box-shadow:none;
        }
        [data-gk-mode="dark"] .demo-stat, .gk-dark .demo-stat {
            background:var(--gk-surface-container);
            border:1px solid rgba(255,255,255,0.14);
            box-shadow:none;
        }
        [data-gk-mode="dark"] .demo-code, .gk-dark .demo-code {
            background:var(--gk-surface);
            border:1px solid rgba(255,255,255,0.08);
            color:#E6EDF3;
        }
        [data-gk-mode="dark"] .demo-intro, .gk-dark .demo-intro {
            color:#8B949E;
        }
        [data-gk-mode="dark"] .demo-section h2, .gk-dark .demo-section h2 {
            color:#E6EDF3;
        }
        .demo-card .gk-table-wrap { border:none !important; }
        .demo-code { background:var(--gk-surface-dim, #1e293b); color:var(--gk-on-surface, #e2e8f0); padding:20px; border-radius:8px; overflow-x:auto; font-family:'SF Mono',Monaco,Consolas,monospace; font-size:13px; line-height:1.6; margin-top:16px; }
        .demo-code pre { margin:0; }
        .demo-pair { display:flex; flex-direction:column; gap:16px; margin-bottom:24px; }
        .demo-pair-left { display:contents; }
        .demo-stats-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:16px; margin-bottom:24px; }
        .demo-stat { background:var(--gk-surface, #fff); border-radius:8px; padding:20px; text-align:center; box-shadow:var(--gk-shadow); }
        .demo-stat .num { font-size:28px; font-weight:700; color:var(--gk-primary); }
        .demo-stat .lbl { font-size:13px; color:var(--gk-on-surface-variant, #6b7280); margin-top:4px; }
        .demo-intro { color:var(--gk-on-surface-variant, #6b7280); margin:0 0 16px; font-size:14px; line-height:1.6; }
        .demo-btn-row { display:flex; gap:8px; flex-wrap:wrap; }
        .anatomy-layout { display:flex; gap:16px; align-items:stretch; }
        .anatomy-mockup { flex:0 0 220px; }
        @media (max-width: 768px) {
            .anatomy-layout { flex-direction:column; }
            .anatomy-mockup { display:none; }
        }
    </style>
<?= Lang::jsConfig() ?>
</head>
<?= Layout::bodyTag('gk-root') ?>

<?php
$sidebar = new Sidebar('demo');
$sidebar->brand('', 'widgets')
    ->group('Components')
    ->item('Table', '#table', 'table_chart', ['active' => true])
    ->item('Form', '#form', 'edit_note')
    ->item('Cards', '#cards', 'grid_view')
    ->item('Layout', '#layout', 'layers')
    ->item('Navigation', '#navigation', 'filter_list')
    ->item('Feedback', '#feedback', 'notifications')
    ->item('UI', '#ui', 'palette')
    ->group('Examples')
    ->item('Examples', '#examples', 'rocket_launch')
    ->group('Info')
    ->item('Changelog', '#changelog', 'history');
$sidebar->render();
?>

<div class="gk-with-sidebar">

<?php
$demoHeader = new Header();
$headerTitle = 'GridKit <span style="font-size:11px;font-weight:400;color:var(--gk-on-surface-variant);margin-left:4px">v' . $version . '</span>';
$langSwitch = $lang === 'en' ? 'de' : 'en';
$langLabel  = $lang === 'en' ? 'DE' : 'EN';
$langBtn    = Button::render($langLabel, ['variant' => 'outlined', 'color' => 'neutral', 'size' => 'sm', 'icon' => 'translate', 'href' => '?lang=' . $langSwitch]);

echo $demoHeader->title($headerTitle, true)
    ->sidebarToggle(true)
    ->fixed(true)
    ->action($langBtn)
    ->user('Demo User', [
        'avatar' => 'https://i.pravatar.cc/72?img=12',
        'menu' => [
            ['label' => 'Profile', 'href' => '#', 'icon' => 'person'],
            ['label' => 'Settings', 'href' => '#', 'icon' => 'settings'],
            '---',
            ['label' => 'Sign out', 'href' => 'login.php?logout=1', 'icon' => 'logout'],
        ],
    ])
    ->render();
?>

<!-- ===== TABLE ===== -->
<div class="demo-section active" data-section="table">
    <h2>Table</h2>

    <h3 style="margin: 32px 0 16px;">Complete table with all features</h3>
        <?php
        $articles = [
            ['article_id' => 1, 'article_number' => 'ART-001', 'name' => 'Webdesign Paket S', 'unit' => 'psch', 'net_price' => 1200.00, 'tax_rate' => 20, 'is_active' => 'active'],
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
            ['article_id' => 12, 'article_number' => 'ART-012', 'name' => 'Webdesign Paket L', 'unit' => 'psch', 'net_price' => 3500.00, 'tax_rate' => 20, 'is_active' => 'active'],
            ['article_id' => 13, 'article_number' => 'ART-013', 'name' => 'App Entwicklung', 'unit' => 'h', 'net_price' => 125.00, 'tax_rate' => 20, 'is_active' => 'draft'],
            ['article_id' => 14, 'article_number' => 'ART-014', 'name' => 'Datenbank Migration', 'unit' => 'psch', 'net_price' => 890.00, 'tax_rate' => 20, 'is_active' => 'active'],
            ['article_id' => 15, 'article_number' => 'ART-015', 'name' => 'Security Audit', 'unit' => 'psch', 'net_price' => 1500.00, 'tax_rate' => 20, 'is_active' => 'inactive'],
        ];
        $table = new Table('articles');
        $table->setData($articles)
            ->search(['article_number', 'name'])
            ->column('article_number', 'Article No.', ['width' => '120px', 'sortable' => true, 'nowrap' => true])
            ->column('name', 'Description', ['sortable' => true, 'nowrap' => true])
            ->column('unit', 'Unit', ['width' => '80px', 'nowrap' => true])
            ->column('net_price', 'Net', ['format' => 'currency', 'align' => 'right', 'width' => '100px', 'nowrap' => true])
            ->column('tax_rate', 'VAT', ['format' => 'percent', 'width' => '80px', 'nowrap' => true])
            ->column('is_active', 'Status', ['format' => 'label', 'nowrap' => true])
            ->filter('is_active', 'select', ['options' => ['active' => 'Active', 'inactive' => 'Inactive', 'draft' => 'Draft'], 'placeholder' => 'All Status'])
            ->button('edit', ['icon' => 'edit', 'class' => 'primary', 'position' => 'right', 'params' => ['id' => 'article_id']])
            ->button('delete', ['icon' => 'delete', 'class' => 'danger', 'position' => 'right', 'params' => ['id' => 'article_id']])
            ->newButton('New Article', ['icon' => 'add'])
            ->nowrap(true)
            ->paginate(5)
            ->render();
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

    <h3 style="margin: 32px 0 16px;">Sizes</h3>
    <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:20px;">
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
    $varData = [
        ['name' => 'Webdesign Paket', 'price' => '1.200 €', 'status' => 'active'],
        ['name' => 'Hosting Standard', 'price' => '9,90 €', 'status' => 'active'],
        ['name' => 'SEO Beratung', 'price' => '95 €', 'status' => 'inactive'],
        ['name' => 'Logo Design', 'price' => '450 €', 'status' => 'draft'],
    ];
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

// Combinable:
$table->variant('striped')->size('compact');
$table->variant('celled')->variant('padded');</pre></div>

    <h3 style="margin: 32px 0 16px;">Mobile-Responsive</h3>
    <p class="demo-intro">Resize the browser window to &lt;768px to see the mobile layout.</p>

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
            ->column('nr', 'Article No.')
            ->column('name', 'Description')
            ->column('price', 'Price')
            ->column('status', 'Status', ['format' => 'label'])
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
$table->mobile('card');      // Cards on mobile (default)
$table->mobile('scroll');    // Horizontal Scroll
$table->column('desc', 'Description', ['hideOnMobile' => true]);</pre></div>
</div>

<!-- ===== FORM (merged: form + upload + color) ===== -->
<div class="demo-section" data-section="form">
    <h2>Form</h2>

    <div class="demo-card">
        <h3 style="margin:0 0 12px; font-size:15px; color:var(--gk-on-surface, #374151);">Grid Layout (16 columns)</h3>
        <?php
        $form = new Form('article_form');
        $form->action('save/process_article.php')
            ->ajax()
            ->hidden('article_id', '')
            ->row()
                ->field('article_number', 'Article No.', 'text', ['required' => true, 'width' => 8])
                ->field('name', 'Description', 'text', ['required' => true, 'width' => 8])
            ->endRow()
            ->field('description', 'Description', 'textarea', ['rows' => 3])
            ->row()
                ->field('unit', 'Unit', 'select', ['options' => ['Stk' => 'Piece', 'h' => 'Hour', 'psch' => 'Flat rate'], 'width' => 5])
                ->field('net_price', 'Net price', 'number', ['step' => '0.01', 'width' => 5])
                ->field('tax_rate', 'VAT %', 'select', ['options' => ['20' => '20%', '10' => '10%', '0' => '0%'], 'width' => 6])
            ->endRow()
            ->field('is_active', 'Active', 'toggle', ['inline' => true])
            ->submit('Save')
            ->render();
        ?>
    </div>

    <div class="demo-card">
        <h3 style="margin:0 0 12px; font-size:15px; color:var(--gk-on-surface, #374151);">Checkbox &amp; Radio</h3>
        <?php
        $form2 = new Form('checkbox_radio_form');
        $form2->field('agree', 'Accept terms', 'checkbox', ['checked' => true])
            ->field('newsletter', 'Subscribe to newsletter', 'checkbox')
            ->field('payment', 'Payment method', 'radio', [
                'options' => ['card' => 'Credit card', 'bank' => 'Bank transfer', 'paypal' => 'PayPal'],
                'value' => 'card',
                'inline' => true,
            ])
            ->field('priority', 'Priority', 'radio', [
                'options' => ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High'],
                'value' => 'medium',
            ])
            ->render();
        ?>
    </div>

    <div class="demo-card">
        <h3 style="margin:0 0 12px; font-size:15px; color:var(--gk-on-surface, #374151);">Toggle &amp; Slider</h3>
        <?php
        $form3 = new Form('toggle_slider_form');
        $form3->field('dark_mode', 'Dark Mode', 'toggle', ['inline' => true])
            ->field('notifications', 'Notifications', 'toggle', ['inline' => true, 'checked' => true])
            ->field('volume', 'Volume', 'range', ['min' => 0, 'max' => 100, 'step' => 1, 'value' => 50])
            ->field('brightness', 'Brightness', 'range', ['min' => 0, 'max' => 100, 'step' => 5, 'value' => 75])
            ->render();
        ?>
    </div>

    <div class="demo-card">
        <h3 style="margin:0 0 12px; font-size:15px; color:var(--gk-on-surface, #374151);">File Upload</h3>
        <?php
        $form4 = new Form('upload_form');
        $form4->field('document', 'Document', 'file', ['accept' => '.pdf,.doc,.docx', 'multiple' => true, 'maxSize' => '10MB'])
            ->render();
        ?>
    </div>

    <div class="demo-card">
        <h3 style="margin:0 0 8px; font-size:15px; color:var(--gk-on-surface, #374151);">RichText Editor (CKEditor 5)</h3>
        <p class="demo-intro">Local vendor bundle (<code>vendor/ckeditor5/</code>), no CDN. Initialization via <code>IntersectionObserver</code> — works in tabs and modals too.</p>
        <?php
        $form5 = new Form('richtext_form');
        $form5->field('content_basic', 'Content (Basic)', 'richtext', ['preset' => 'basic'])
            ->field('content_full', 'Content (Full)', 'richtext', ['preset' => 'full'])
            ->render();
        ?>
    </div>

    <div class="demo-card">
        <h3 style="margin:0 0 8px; font-size:15px; color:var(--gk-on-surface, #374151);">Clearable date/time fields</h3>
        <p class="demo-intro">Trash icon only appears when a value is set — disappears automatically after clearing.</p>
        <?php
        $formClearable = new Form('clearable_form');
        $formClearable
            ->field('datum',  'Date',     'date',     ['value' => date('Y-m-d'),      'clearable' => true])
            ->field('zeit',   'Time',     'time',     ['value' => '09:00',            'clearable' => true])
            ->field('termin', 'Appointment', 'datetime', ['value' => date('Y-m-d\TH:i'), 'clearable' => true])
            ->render();
        ?>
    </div>

    <div class="demo-card">
        <h3 style="margin:0 0 8px; font-size:15px; color:var(--gk-on-surface, #374151);">New field types</h3>
        <p class="demo-intro">Uniform height of 44px for all input types. No browser spinner for <code>number</code>.</p>
        <?php
        $formNew = new Form('new_fields_form');
        $formNew
            ->field('farbe', 'Color',  'color',  ['value' => '#6750a4'])
            ->field('monat', 'Month',  'month',  ['value' => date('Y-m')])
            ->field('kw',    'Week',   'week',   ['value' => date('Y-\WW')])
            ->field('preis', 'Price',  'number', ['placeholder' => '0.00'])
            ->render();
        ?>
    </div>

    <div class="demo-card">
        <h3 style="margin:0 0 12px; font-size:15px; color:var(--gk-on-surface, #374151);">Select extensions</h3>
        <p style="margin:0 0 12px; font-size:13px; color:var(--gk-on-surface-variant, #6b7280);"><strong>Searchable Select</strong> – Country selection with search field</p>
        <?php
        $formSearch = new Form('searchable_select_demo');
        $formSearch->field('country', 'Country', 'select', [
                'options' => [
                    'AT' => 'Austria', 'DE' => 'Germany', 'CH' => 'Switzerland',
                    'IT' => 'Italy', 'FR' => 'France', 'ES' => 'Spain',
                    'GB' => 'United Kingdom', 'NL' => 'Netherlands', 'BE' => 'Belgium',
                    'PL' => 'Poland', 'CZ' => 'Czech Republic', 'HU' => 'Hungary',
                    'SK' => 'Slovakia', 'SI' => 'Slovenia', 'HR' => 'Croatia',
                    'SE' => 'Sweden', 'NO' => 'Norway', 'DK' => 'Denmark',
                    'FI' => 'Finland', 'PT' => 'Portugal',
                ],
                'searchable' => true, 'placeholder' => 'Search country...', 'value' => 'AT'
            ])->render();
        ?>
        <hr style="border:none; border-top:1px solid var(--gk-outline-variant); margin:20px 0;">
        <p style="margin:0 0 12px; font-size:13px; color:var(--gk-on-surface-variant, #6b7280);"><strong>Multi-Select</strong> – Select multiple categories (with chips)</p>
        <?php
        $formMulti = new Form('multiselect_demo');
        $formMulti->field('tags', 'Categories', 'multiselect', [
                'options' => ['web' => 'Webdesign', 'seo' => 'SEO', 'hosting' => 'Hosting', 'dev' => 'Development', 'support' => 'Support', 'beratung' => 'Consulting'],
                'value' => ['web', 'seo'], 'placeholder' => 'Search categories...', 'searchable' => true,
            ])->render();
        ?>
        <hr style="border:none; border-top:1px solid var(--gk-outline-variant); margin:20px 0;">
        <p style="margin:0 0 12px; font-size:13px; color:var(--gk-on-surface-variant, #6b7280);"><strong>Ajax Select</strong> – Customer search via API (min. 2 characters)</p>
        <?php
        $formAjax = new Form('ajax_select_demo');
        $formAjax->field('customer_id', 'Customer', 'ajaxselect', [
                'url' => 'demo/api/search.php', 'value' => '', 'displayValue' => '', 'placeholder' => 'Search customer...',
                'labelField' => 'name', 'valueField' => 'id', 'subtextField' => 'city', 'minChars' => 2, 'searchParam' => 'q',
            ])->render();
        ?>
    </div>

    <div class="demo-code"><pre>// Grid Layout (16 columns)
$form->row()
    ->field('name', 'Name', 'text', ['width' => 8])
    ->field('email', 'E-Mail', 'email', ['width' => 8])
->endRow()
->field('agree', 'Accept terms', 'checkbox', ['checked' => true])
->field('payment', 'Payment method', 'radio', [
    'options' => ['card' => 'Credit card', 'bank' => 'Bank transfer'],
    'value' => 'card', 'inline' => true
])
->field('active', 'Active', 'toggle', ['inline' => true])
->field('volume', 'Volume', 'range', ['min' => 0, 'max' => 100, 'value' => 50])
->field('doc', 'Document', 'file', ['accept' => '.pdf', 'multiple' => true, 'maxSize' => '10MB'])
->field('content', 'Content', 'richtext', ['preset' => 'full'])
->field('datum',  'Date',    'date',     ['value' => date('Y-m-d'),      'clearable' => true])
->field('farbe', 'Color',  'color',  ['value' => '#6750a4'])
->field('preis', 'Price',  'number', ['placeholder' => '0.00'])</pre></div>

    <hr style="border:none;border-top:1px solid var(--gk-outline-variant);margin:40px 0">

    <h3>File Upload (extended)</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-bottom:24px;">
        <div class="demo-card" style="text-align:center;"><span class="material-icons" style="font-size:32px;color:var(--gk-primary);display:block;margin-bottom:8px;">drag_indicator</span><strong>Drag &amp; Drop</strong><p class="demo-intro" style="margin:8px 0 0;">Select files via drag &amp; drop or click</p></div>
        <div class="demo-card" style="text-align:center;"><span class="material-icons" style="font-size:32px;color:var(--gk-primary);display:block;margin-bottom:8px;">verified</span><strong>Validation</strong><p class="demo-intro" style="margin:8px 0 0;">Type, size (min/max), count and total size</p></div>
        <div class="demo-card" style="text-align:center;"><span class="material-icons" style="font-size:32px;color:var(--gk-primary);display:block;margin-bottom:8px;">image</span><strong>Preview</strong><p class="demo-intro" style="margin:8px 0 0;">Image thumbnails directly in the upload zone</p></div>
        <div class="demo-card" style="text-align:center;"><span class="material-icons" style="font-size:32px;color:var(--gk-primary);display:block;margin-bottom:8px;">list_alt</span><strong>Queue UI</strong><p class="demo-intro" style="margin:8px 0 0;">Progress indicator, states, errors per file</p></div>
    </div>

    <div class="demo-card">
        <h3 style="margin:0 0 8px;font-size:15px;color:var(--gk-on-surface, #374151);">Variant 1 — Simple</h3>
        <p class="demo-intro">Document upload with type filter and size limit.</p>
        <?php (new Form('up-simple'))->field('doc', 'Document', 'file', ['accept' => ['pdf', 'doc', 'docx'], 'maxSize' => '10 MB', 'hint' => 'PDF or Word, max. 10 MB'])->render(); ?>
    </div>

    <div class="demo-card">
        <h3 style="margin:0 0 8px;font-size:15px;color:var(--gk-on-surface, #374151);">Variant 2 — Images with preview</h3>
        <p class="demo-intro">Multi-upload with image thumbnails directly in the zone.</p>
        <?php (new Form('up-images'))->field('fotos', 'Photos', 'file', ['multiple' => true, 'preview' => true, 'accept' => ['jpg', 'jpeg', 'png', 'gif', 'webp'], 'maxSize' => '8 MB', 'maxFiles' => 6])->render(); ?>
    </div>

    <div class="demo-card">
        <h3 style="margin:0 0 8px;font-size:15px;color:var(--gk-on-surface, #374151);">Variant 3 — Full configuration</h3>
        <p class="demo-intro">All options combined: types, min/max size, total limit, file count.</p>
        <?php (new Form('up-full'))->field('attachments', 'Attachments', 'file', ['multiple' => true, 'preview' => true, 'accept' => ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'zip', 'doc', 'docx', 'txt'], 'minSize' => '1 KB', 'maxSize' => '10 MB', 'maxTotalSize' => '50 MB', 'maxFiles' => 10, 'hint' => 'Max. 10 MB/file · 50 MB total · 10 files'])->render(); ?>
    </div>

    <div class="demo-card">
        <h3 style="margin:0 0 8px;font-size:15px;color:var(--gk-on-surface, #374151);">Queue states live</h3>
        <p class="demo-intro">Manually cycle queue items through states: pending → uploading → done / error.</p>
        <div class="demo-btn-row" style="margin-bottom:16px;">
            <button class="gk-btn gk-btn-primary" id="btn-queue-sim"><span class="material-icons" style="font-size:16px;">add_circle</span> Simulate file</button>
            <button class="gk-btn gk-btn-filled gk-btn-danger" id="btn-queue-err"><span class="material-icons" style="font-size:16px;">error_outline</span> Simulate error</button>
        </div>
        <div id="queue-demo-list" style="display:flex;flex-direction:column;gap:8px;"></div>
    </div>

    <div class="demo-card">
        <h3 style="margin:0 0 8px;font-size:15px;color:var(--gk-on-surface, #374151);">Validation error demo</h3>
        <p class="demo-intro">Zone with strict limits — PDF only, max. 100 KB.</p>
        <?php (new Form('up-validate'))->field('strict_file', 'Strict (PDF, max. 100 KB)', 'file', ['accept' => ['pdf'], 'maxSize' => '100 KB', 'hint' => 'PDF only, max. 100 KB — other files will be rejected'])->render(); ?>
    </div>

    <hr style="border:none;border-top:1px solid var(--gk-outline-variant);margin:40px 0">

    <h3>Color Picker</h3>
    <p class="demo-intro">Styled native color input — color swatch on the left (clickable) + hex field on the right. Swatch and hex value sync automatically, validated as <code>#RRGGBB</code>.</p>

    <div class="demo-card">
        <h3 style="margin:0 0 8px;font-size:15px;color:var(--gk-on-surface, #374151);">Live Demo</h3>
        <?php
        (new Form('color-demo'))
            ->field('primary',   'Primary color',   'color', ['value' => '#6750a4'])
            ->field('secondary', 'Secondary color', 'color', ['value' => '#2563eb'])
            ->field('accent',    'Accent color',   'color', ['value' => '#16a34a'])
            ->render();
        ?>
    </div>

    <div class="demo-card">
        <h3 style="margin:0 0 12px;font-size:15px;color:var(--gk-on-surface, #374151);">Behavior</h3>
        <ul style="margin:0;padding-left:20px;font-size:14px;line-height:1.8;color:var(--gk-on-surface-variant);">
            <li>Clicking the <strong>color swatch</strong> opens the native browser color picker</li>
            <li>The <strong>hex text field</strong> shows the current value and is directly editable</li>
            <li>Both fields synchronize in real time</li>
            <li>Validation: only valid <code>#RRGGBB</code> values are accepted</li>
            <li>Uniform <strong>44px height</strong> like all other GridKit inputs</li>
        </ul>
    </div>
</div>

<!-- ===== CARDS (merged: cards + statcards) ===== -->
<div class="demo-section" data-section="cards">
    <h2>Cards</h2>
    <p class="demo-intro">Card grid for structured content — responsive, with header, body and footer.</p>

    <h3 style="margin: 32px 0 16px;">Auto-Grid (responsive)</h3>
    <div class="gk-cards">
        <div class="gk-card"><div class="gk-card-header">Webdesign</div><div class="gk-card-body"><div class="gk-card-meta">Package S · from 1,200 €</div><div class="gk-card-description">Responsive design, CMS integration and SEO basics for small projects.</div></div><div class="gk-card-footer">5 projects active</div></div>
        <div class="gk-card"><div class="gk-card-header">Hosting</div><div class="gk-card-body"><div class="gk-card-meta">Managed · from 9.90 €/month</div><div class="gk-card-description">SSD storage, daily backups and SSL included. 99.9% uptime.</div></div><div class="gk-card-footer">12 servers active</div></div>
        <div class="gk-card"><div class="gk-card-header">SEO</div><div class="gk-card-body"><div class="gk-card-meta">Consulting · 95 €/h</div><div class="gk-card-description">Keyword analysis, on-page optimization and monthly reports.</div></div><div class="gk-card-footer">3 customers active</div></div>
    </div>

    <h3 style="margin: 32px 0 16px;">Fixed column count</h3>
    <div class="gk-cards gk-cards-4">
        <div class="gk-card"><div class="gk-card-body" style="text-align:center"><span class="material-icons" style="font-size:32px;color:var(--gk-primary);margin-bottom:8px;display:block">speed</span><strong>Performance</strong><div class="gk-card-meta" style="margin-top:4px">99.9% Uptime</div></div></div>
        <div class="gk-card"><div class="gk-card-body" style="text-align:center"><span class="material-icons" style="font-size:32px;color:var(--gk-success);margin-bottom:8px;display:block">security</span><strong>Security</strong><div class="gk-card-meta" style="margin-top:4px">SSL & Firewall</div></div></div>
        <div class="gk-card"><div class="gk-card-body" style="text-align:center"><span class="material-icons" style="font-size:32px;color:var(--gk-warning);margin-bottom:8px;display:block">support_agent</span><strong>Support</strong><div class="gk-card-meta" style="margin-top:4px">Available 24/7</div></div></div>
        <div class="gk-card"><div class="gk-card-body" style="text-align:center"><span class="material-icons" style="font-size:32px;color:var(--gk-info);margin-bottom:8px;display:block">backup</span><strong>Backups</strong><div class="gk-card-meta" style="margin-top:4px">Daily automatic</div></div></div>
    </div>

    <h3 style="margin: 32px 0 16px;">2 columns</h3>
    <div class="gk-cards gk-cards-2">
        <div class="gk-card"><div class="gk-card-header">Development</div><div class="gk-card-body"><div class="gk-card-description">Frontend and backend development with modern technologies.</div></div></div>
        <div class="gk-card"><div class="gk-card-header">Consulting</div><div class="gk-card-body"><div class="gk-card-description">Strategic IT consulting for digital transformation.</div></div></div>
    </div>

    <h3 style="margin: 32px 0 16px;">3 columns</h3>
    <div class="gk-cards gk-cards-3">
        <div class="gk-card"><div class="gk-card-body" style="text-align:center"><span class="material-icons" style="font-size:28px;color:var(--gk-primary);display:block;margin-bottom:6px">web</span><strong>Web</strong></div></div>
        <div class="gk-card"><div class="gk-card-body" style="text-align:center"><span class="material-icons" style="font-size:28px;color:var(--gk-success);display:block;margin-bottom:6px">phone_android</span><strong>Mobile</strong></div></div>
        <div class="gk-card"><div class="gk-card-body" style="text-align:center"><span class="material-icons" style="font-size:28px;color:var(--gk-warning);display:block;margin-bottom:6px">cloud</span><strong>Cloud</strong></div></div>
    </div>

    <div class="demo-code"><pre>.gk-cards          // Auto-Grid (min 280px)
.gk-cards-2 / -3 / -4  // Fixed columns
.gk-card .gk-card-header / .gk-card-body / .gk-card-footer
.gk-card-link      // Clickable Card</pre></div>

    <hr style="border:none;border-top:1px solid var(--gk-outline-variant);margin:40px 0">

    <h3>Stat Cards</h3>
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
</div>

<!-- ===== LAYOUT (merged: segment + message) ===== -->
<div class="demo-section" data-section="layout">
    <h2>Layout</h2>

    <h3 style="margin: 32px 0 16px;">Segment</h3>
    <p class="demo-intro">Container for related content — single, stacked or with header.</p>

    <div class="gk-segment"><p>A simple segment visually groups related content.</p></div>

    <h3 style="margin: 32px 0 16px;">Segment with header</h3>
    <div class="gk-segment"><div class="gk-segment-header">Project overview</div><p>Segments can have a header to describe the content.</p></div>

    <h3 style="margin: 32px 0 16px;">Stacked segments</h3>
    <div class="gk-segments">
        <div class="gk-segment"><div class="gk-segment-header">Step 1</div><p>Create account and confirm email.</p></div>
        <div class="gk-segment"><div class="gk-segment-header">Step 2</div><p>Fill out profile and configure settings.</p></div>
        <div class="gk-segment"><div class="gk-segment-header">Step 3</div><p>Create first project and get started.</p></div>
    </div>

    <h3 style="margin: 32px 0 16px;">Variants</h3>
    <div class="gk-segment gk-segment-raised"><div class="gk-segment-header">Raised</div><p>With shadow — stands out more from the background.</p></div>
    <div class="gk-segment gk-segment-muted"><div class="gk-segment-header">Muted</div><p>Muted background — for secondary content.</p></div>
    <div class="gk-segment gk-segment-compact"><div class="gk-segment-header">Compact</div><p>Less padding — for denser layouts.</p></div>

    <div class="demo-code"><pre>.gk-segment / .gk-segment-raised / .gk-segment-muted / .gk-segment-compact
.gk-segment-padded / .gk-segment-basic
.gk-segments > .gk-segment  // Stacked</pre></div>

    <hr style="border:none;border-top:1px solid var(--gk-outline-variant);margin:40px 0">

    <h3>Message</h3>
    <p class="demo-intro">Notices, warnings and status messages for users.</p>

    <div class="gk-message"><span class="material-icons">info</span><div class="gk-message-content">A neutral message without a specific status.</div></div>

    <h3 style="margin: 32px 0 16px;">Types</h3>
    <div class="gk-message gk-message-info"><span class="material-icons">info</span><div class="gk-message-content"><div class="gk-message-header">Information</div>Your changes are saved automatically.</div></div>
    <div class="gk-message gk-message-success"><span class="material-icons">check_circle</span><div class="gk-message-content"><div class="gk-message-header">Success</div>The profile has been successfully updated.</div></div>
    <div class="gk-message gk-message-warning"><span class="material-icons">warning</span><div class="gk-message-content"><div class="gk-message-header">Warning</div>Your SSL certificate expires in 7 days.</div></div>
    <div class="gk-message gk-message-error"><span class="material-icons">error</span><div class="gk-message-content"><div class="gk-message-header">Error</div>Could not establish connection to the database.<ul class="gk-message-list"><li>Host unreachable</li><li>Timeout after 30 seconds</li></ul></div></div>

    <h3 style="margin: 32px 0 16px;">Compact</h3>
    <div class="gk-message gk-message-info gk-message-compact"><span class="material-icons">info</span><div class="gk-message-content">Compact message for less space.</div></div>

    <h3 style="margin: 32px 0 16px;">Dismissible</h3>
    <div class="gk-message gk-message-warning" id="demo-dismiss-msg"><span class="material-icons">warning</span><div class="gk-message-content">This message can be closed.</div><button class="gk-message-dismiss" onclick="this.parentElement.style.display='none'"><span class="material-icons">close</span></button></div>

    <div class="demo-code"><pre>.gk-message / .gk-message-info / .gk-message-success
.gk-message-warning / .gk-message-error / .gk-message-compact
.gk-message-dismiss  // Close button</pre></div>
</div>

<!-- ===== NAVIGATION (merged: filterchips + yearfilter + formatter) ===== -->
<div class="demo-section" data-section="navigation">
    <h2>Navigation & Filter</h2>

    <h3 style="margin: 32px 0 16px;">Accordion</h3>
    <p class="demo-intro">Collapsible content areas &mdash; individually or as a group.</p>

    <div class="gk-accordion" data-gk-single>
        <div class="gk-accordion-item open">
            <button class="gk-accordion-trigger">
                <span>What is GRIDKit?</span>
                <span class="material-icons">expand_more</span>
            </button>
            <div class="gk-accordion-content">
                <div class="gk-accordion-body">GRIDKit is a lightweight PHP/CSS/JS framework for admin dashboards and internal tools. Zero dependencies, M3-inspired.</div>
            </div>
        </div>
        <div class="gk-accordion-item">
            <button class="gk-accordion-trigger">
                <span>What components are available?</span>
                <span class="material-icons">expand_more</span>
            </button>
            <div class="gk-accordion-content">
                <div class="gk-accordion-body">Table, Form, Modal, Cards, StatCards, Sidebar, Header, Tabs, Accordion, Breadcrumb, Toast, Confirm and more. All with Light &amp; Dark Mode.</div>
            </div>
        </div>
        <div class="gk-accordion-item">
            <button class="gk-accordion-trigger">
                <span>Do I need npm or build tools?</span>
                <span class="material-icons">expand_more</span>
            </button>
            <div class="gk-accordion-content">
                <div class="gk-accordion-body">No. GRIDKit has no dependencies &mdash; one CSS file, one JS file, done. Just include and get started.</div>
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

    <h3 style="margin: 32px 0 16px;">Avatar</h3>
    <p class="demo-intro">Profile pictures with initials fallback, status dot and groups.</p>

    <div class="gk-segment">
        <div class="gk-segment-header">Sizes</div>
        <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
            <div class="gk-avatar gk-avatar-xs">XS</div>
            <div class="gk-avatar gk-avatar-sm">SM</div>
            <div class="gk-avatar gk-avatar-md">MD</div>
            <div class="gk-avatar gk-avatar-lg">LG</div>
            <div class="gk-avatar gk-avatar-xl">XL</div>
            <div class="gk-avatar gk-avatar-lg"><img src="https://i.pravatar.cc/112?img=12" alt=""></div>
            <div class="gk-avatar gk-avatar-lg"><img src="https://i.pravatar.cc/112?img=32" alt=""></div>
        </div>
    </div>

    <div class="gk-segment" style="margin-top:12px">
        <div class="gk-segment-header">Status</div>
        <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
            <div class="gk-avatar gk-avatar-lg"><img src="https://i.pravatar.cc/112?img=12" alt=""><span class="gk-avatar-status online"></span></div>
            <div class="gk-avatar gk-avatar-lg"><img src="https://i.pravatar.cc/112?img=32" alt=""><span class="gk-avatar-status away"></span></div>
            <div class="gk-avatar gk-avatar-lg"><img src="https://i.pravatar.cc/112?img=44" alt=""><span class="gk-avatar-status busy"></span></div>
            <div class="gk-avatar gk-avatar-lg">MM<span class="gk-avatar-status offline"></span></div>
        </div>
    </div>

    <div class="gk-segment" style="margin-top:12px">
        <div class="gk-segment-header">Group (stacked)</div>
        <div class="gk-avatar-group">
            <div class="gk-avatar gk-avatar-md"><img src="https://i.pravatar.cc/80?img=12" alt=""></div>
            <div class="gk-avatar gk-avatar-md"><img src="https://i.pravatar.cc/80?img=32" alt=""></div>
            <div class="gk-avatar gk-avatar-md"><img src="https://i.pravatar.cc/80?img=44" alt=""></div>
            <div class="gk-avatar gk-avatar-md"><img src="https://i.pravatar.cc/80?img=55" alt=""></div>
            <div class="gk-avatar gk-avatar-md">+3</div>
        </div>
    </div>

    <div class="demo-code"><pre>.gk-avatar.gk-avatar-lg           // Sizes: xs, sm, md, lg, xl
.gk-avatar-status.online          // Status: online, away, busy, offline
.gk-avatar-group                  // Stacked group
.gk-avatar-square                 // Square instead of round</pre></div>

    <hr style="border:none;border-top:1px solid var(--gk-outline-variant);margin:40px 0">

    <h3 style="margin: 32px 0 16px;">Gallery + Lightbox</h3>
    <p class="demo-intro">Image grid with lazy loading, hover overlay and lightbox (arrow keys, Escape).</p>

    <div class="gk-gallery">
        <div class="gk-gallery-item" data-lightbox="https://picsum.photos/800/600?random=1" data-caption="Landscape 1" data-lazy>
            <img data-src="https://picsum.photos/400/400?random=1" alt="Landscape 1">
            <div class="gk-gallery-overlay"><span class="gk-gallery-caption">Landscape</span></div>
        </div>
        <div class="gk-gallery-item" data-lightbox="https://picsum.photos/800/600?random=2" data-caption="Architecture" data-lazy>
            <img data-src="https://picsum.photos/400/400?random=2" alt="Architecture">
            <div class="gk-gallery-overlay"><span class="gk-gallery-caption">Architecture</span></div>
        </div>
        <div class="gk-gallery-item" data-lightbox="https://picsum.photos/800/600?random=3" data-caption="Nature" data-lazy>
            <img data-src="https://picsum.photos/400/400?random=3" alt="Nature">
            <div class="gk-gallery-overlay"><span class="gk-gallery-caption">Nature</span></div>
        </div>
        <div class="gk-gallery-item" data-lightbox="https://picsum.photos/800/600?random=4" data-caption="City" data-lazy>
            <img data-src="https://picsum.photos/400/400?random=4" alt="City">
            <div class="gk-gallery-overlay"><span class="gk-gallery-caption">City</span></div>
        </div>
        <div class="gk-gallery-item" data-lightbox="https://picsum.photos/800/600?random=5" data-caption="Abstract" data-lazy>
            <img data-src="https://picsum.photos/400/400?random=5" alt="Abstract">
            <div class="gk-gallery-overlay"><span class="gk-gallery-caption">Abstract</span></div>
        </div>
        <div class="gk-gallery-item" data-lightbox="https://picsum.photos/800/600?random=6" data-caption="Panorama" data-lazy>
            <img data-src="https://picsum.photos/400/400?random=6" alt="Panorama">
            <div class="gk-gallery-overlay"><span class="gk-gallery-caption">Panorama</span></div>
        </div>
    </div>

    <div class="demo-code"><pre>&lt;div class="gk-gallery"&gt;
    &lt;div class="gk-gallery-item" data-lightbox="full.jpg" data-caption="Titel" data-lazy&gt;
        &lt;img data-src="thumb.jpg" alt=""&gt;
        &lt;div class="gk-gallery-overlay"&gt;
            &lt;span class="gk-gallery-caption"&gt;Titel&lt;/span&gt;
        &lt;/div&gt;
    &lt;/div&gt;
&lt;/div&gt;

// Variants:
.gk-gallery-sm              // Smaller thumbnails (100px min)
.gk-gallery-lg              // Larger thumbnails (220px min)
.gk-gallery-masonry         // Pinterest layout (columns)
// Lightbox: arrow keys, Escape, click outside to close
// data-lazy: load images only when visible</pre></div>

    <hr style="border:none;border-top:1px solid var(--gk-outline-variant);margin:40px 0">

    <h3 style="margin: 32px 0 16px;">Breadcrumb</h3>
    <p class="demo-intro">Path navigation for orientation in nested areas.</p>

    <div class="gk-segment">
        <nav class="gk-breadcrumb">
            <a href="#"><span class="material-icons">home</span></a>
            <span class="gk-breadcrumb-sep"><span class="material-icons">chevron_right</span></span>
            <a href="#">Products</a>
            <span class="gk-breadcrumb-sep"><span class="material-icons">chevron_right</span></span>
            <a href="#">Hosting</a>
            <span class="gk-breadcrumb-sep"><span class="material-icons">chevron_right</span></span>
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

    <h3 style="margin: 32px 0 16px;">Tabs</h3>
    <p class="demo-intro">Tab navigation for switching between content areas.</p>

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

    <hr style="border:none;border-top:1px solid var(--gk-outline-variant);margin:40px 0">

    <h3 style="margin: 32px 0 16px;">Combinations</h3>
    <p class="demo-intro">Combination examples showing the full potential of GRIDKit.</p>

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
                        <span class="material-icons">expand_more</span>
                    </button>
                    <div class="gk-accordion-content">
                        <div class="gk-accordion-body">3 packages available: S (1,200 &euro;), M (2,400 &euro;), L (3,500 &euro;). All incl. responsive design and CMS.</div>
                    </div>
                </div>
                <div class="gk-accordion-item">
                    <button class="gk-accordion-trigger">
                        <span>Hosting &amp; Server</span>
                        <span class="material-icons">expand_more</span>
                    </button>
                    <div class="gk-accordion-content">
                        <div class="gk-accordion-body">Managed hosting from 9.90 &euro;/month. SSD, backups, SSL included. 99.9% uptime guarantee.</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="gk-tab-panel" data-tab="combo-kunden">
            <div class="gk-message gk-message-info" style="margin-top:16px">
                <span class="material-icons">info</span>
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
            <span class="material-icons">check_circle</span>
            <div class="gk-message-content">All 3 servers are online &mdash; last check 2 minutes ago.</div>
        </div>
        <table class="gk-table">
            <thead><tr><th>Server</th><th>Status</th><th>CPU</th><th>RAM</th><th>Uptime</th></tr></thead>
            <tbody>
                <tr><td>Baerli (server8)</td><td><span class="gk-label gk-label-green">Online</span></td><td>12%</td><td>4.2 GB</td><td>47 days</td></tr>
                <tr><td>Theo (server7)</td><td><span class="gk-label gk-label-green">Online</span></td><td>8%</td><td>3.8 GB</td><td>23 days</td></tr>
                <tr><td>Waldi (server6)</td><td><span class="gk-label gk-label-green">Online</span></td><td>15%</td><td>5.1 GB</td><td>31 days</td></tr>
            </tbody>
        </table>
    </div>

    <hr style="border:none;border-top:1px solid var(--gk-outline-variant);margin:40px 0">

    <h3 style="margin: 32px 0 16px;">Pagination (standalone)</h3>
    <p class="demo-intro">Page navigation &mdash; automatically generated by Table, but can also be used standalone.</p>
    <div class="gk-segment">
        <nav class="gk-pagination">
            <button class="gk-page-btn" disabled>&laquo;</button>
            <button class="gk-page-btn gk-active">1</button>
            <button class="gk-page-btn">2</button>
            <button class="gk-page-btn">3</button>
            <button class="gk-page-btn">4</button>
            <button class="gk-page-btn">5</button>
            <button class="gk-page-btn">&raquo;</button>
        </nav>
    </div>
    <div class="demo-code"><pre>&lt;nav class="gk-pagination"&gt;
    &lt;button class="gk-page-btn" disabled&gt;&amp;laquo;&lt;/button&gt;
    &lt;button class="gk-page-btn gk-active"&gt;1&lt;/button&gt;
    &lt;button class="gk-page-btn"&gt;2&lt;/button&gt;
    &lt;button class="gk-page-btn"&gt;&amp;raquo;&lt;/button&gt;
&lt;/nav&gt;</pre></div>

    <hr style="border:none;border-top:1px solid var(--gk-outline-variant);margin:40px 0">

    <h3 style="margin: 32px 0 16px;">FilterChips</h3>
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

    <hr style="border:none;border-top:1px solid var(--gk-outline-variant);margin:40px 0">

    <h3>YearFilter</h3>
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

    <h3>Formatter</h3>
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
    <div class="demo-code"><pre>->column('amount', 'Amount', ['format' => 'currency'])    // 1,234.56 EUR
->column('tax', 'VAT', ['format' => 'percent'])           // 20%
->column('date', 'Date', ['format' => 'date'])            // 02/13/2026
->column('active', 'Active', ['format' => 'boolean'])     // Yes / No
->column('status', 'Status', ['format' => 'label'])       // Colored label
->column('email', 'E-Mail', ['format' => 'email'])        // mailto: Link</pre></div>
    </div>
</div>

<!-- ===== FEEDBACK (merged: toast + confirm + modal) ===== -->
<div class="demo-section" data-section="feedback">
    <h2>Feedback & Dialogs</h2>

    <h3 style="margin: 32px 0 16px;">Toast</h3>
    <div class="demo-pair">
    <div class="demo-card">
        <p class="demo-intro">Toast notifications for success, error and info messages. Disappear after 3 seconds.</p>
        <div class="demo-btn-row">
            <button class="gk-btn gk-btn-filled gk-btn-success" onclick="GK.toast.success('Successfully saved!')"><span class="material-icons" style="font-size:16px">check_circle</span> Success</button>
            <button class="gk-btn gk-btn-filled gk-btn-danger" onclick="GK.toast.error('Error while saving!')"><span class="material-icons" style="font-size:16px">error</span> Error</button>
            <button class="gk-btn gk-btn-filled gk-btn-warning" onclick="GK.toast.warning('Warning: Limit reached!')"><span class="material-icons" style="font-size:16px">warning</span> Warning</button>
            <button class="gk-btn gk-btn-primary" onclick="GK.toast.info('3 new entries available')"><span class="material-icons" style="font-size:16px">info</span> Info</button>
        </div>
    </div>
    <div class="demo-code"><pre>GK.toast.success('Successfully saved!');
GK.toast.error('Error while saving!');
GK.toast.warning('Warning: Limit reached!');
GK.toast.info('3 new entries');</pre></div>
    </div>

    <hr style="border:none;border-top:1px solid var(--gk-outline-variant);margin:40px 0">

    <h3>Confirm</h3>
    <div class="demo-pair">
    <div class="demo-card">
        <p class="demo-intro">Confirm dialogs as clean modals. Promise-based, with danger mode for destructive actions.</p>
        <div class="demo-btn-row">
            <button class="gk-btn gk-btn-primary" onclick="GK.confirm('Send invoice to customer?', {title:'Send Invoice', confirmText:'Send'}).then(function(ok){ if(ok) GK.toast.success('Sent!'); })"><span class="material-icons" style="font-size:16px">send</span> Standard Confirm</button>
            <button class="gk-btn gk-btn-danger" onclick="GK.confirm('Really permanently delete this entry?', {title:'Delete Entry', confirmText:'Delete', danger:true}).then(function(ok){ if(ok) GK.toast.success('Deleted!'); })"><span class="material-icons" style="font-size:16px">delete_forever</span> Danger Confirm</button>
        </div>
    </div>
    <div class="demo-code"><pre>GK.confirm('Send invoice?', {
    title: 'Send Invoice', confirmText: 'Send'
}).then(ok => { if (ok) GK.toast.success('Sent!'); });

GK.confirm('Really delete?', {
    title: 'Delete', confirmText: 'Delete', danger: true
}).then(ok => { if (ok) { /* ... */ } });</pre></div>
    </div>

    <hr style="border:none;border-top:1px solid var(--gk-outline-variant);margin:40px 0">

    <h3>Modal</h3>
    <div class="demo-pair">
        <div class="demo-pair-left">
            <div class="demo-card">
                <p class="demo-intro">Modals in four sizes. Loaded via AJAX, backdrop click and ESC close them.</p>
                <div class="demo-btn-row">
                    <button class="gk-btn" onclick="GK.modal.open('Small (420px)', 'demo/form/f_delete.php', {}, 'small')"><span class="material-icons" style="font-size:16px">crop_square</span> Small</button>
                    <button class="gk-btn gk-btn-primary" onclick="GK.modal.open('Medium (640px)', 'demo/form/f_articles.php', {}, 'medium')"><span class="material-icons" style="font-size:16px">crop_din</span> Medium</button>
                    <button class="gk-btn gk-btn-primary" onclick="GK.modal.open('Large (900px)', 'demo/form/f_articles.php', {}, 'large')"><span class="material-icons" style="font-size:16px">crop_free</span> Large</button>
                    <button class="gk-btn gk-btn-filled gk-btn-neutral" onclick="GK.modal.open('Fullscreen Modal', 'demo/form/f_articles.php', {}, 'full')"><span class="material-icons" style="font-size:16px">fullscreen</span> Full</button>
                </div>
            </div>
            <div class="demo-card">
                <h3 style="margin:0 0 8px; font-size:15px; color:var(--gk-on-surface, #374151);">Nesting: Modal with form</h3>
                <p class="demo-intro">A modal loads a form via AJAX – the most common use case.</p>
                <div class="demo-btn-row">
                    <button class="gk-btn gk-btn-primary" onclick="GK.modal.open('Edit Article', 'demo/form/f_articles.php', {}, 'medium')"><span class="material-icons" style="font-size:16px">edit</span> Modal + Form</button>
                </div>
            </div>
            <div class="demo-card">
                <h3 style="margin:0 0 8px; font-size:15px; color:var(--gk-on-surface, #374151);">Nesting: Modal with table + sub-modal</h3>
                <p class="demo-intro">A large modal shows a customer list. Clicking "Edit" opens a second modal with the form.</p>
                <div class="demo-btn-row">
                    <button class="gk-btn gk-btn-primary" onclick="GK.modal.open('Customer Management', 'demo/form/f_table_modal.php', {}, 'large')"><span class="material-icons" style="font-size:16px">people</span> Modal + Table + Sub-Modal</button>
                </div>
            </div>
        </div>
        <div class="demo-code"><pre>GK.modal.open('Titel', 'form/edit.php', {id: 42}, 'small');
GK.modal.open('Titel', 'form/edit.php', {id: 42}, 'medium');
GK.modal.open('Titel', 'form/edit.php', {id: 42}, 'large');
GK.modal.open('Titel', 'form/edit.php', {id: 42}, 'full');

$table->button('edit', ['icon' => 'edit', 'modal' => 'edit_form'])
    ->modal('edit_form', 'Edit', 'form/edit.php', ['size' => 'large']);</pre></div>
    </div>
</div>

<!-- ===== UI (merged: buttons + header + sidebar + themes) ===== -->
<div class="demo-section" data-section="ui">
    <h2>UI Components</h2>

    <h3 style="margin: 32px 0 16px;">Buttons</h3>

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
        <h3 style="margin:0 0 12px; font-size:15px; color:var(--gk-on-surface, #374151);">FAB (Floating Action Button)</h3>
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

    <h3>Labels</h3>
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

    <h3>Tooltip</h3>
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

    <h3>Empty State</h3>
    <div class="demo-card">
        <p class="demo-intro">Placeholder for empty tables or lists.</p>
        <div class="gk-table-wrap">
            <table class="gk-table">
                <thead><tr><th>Name</th><th>E-Mail</th><th>Status</th></tr></thead>
                <tbody>
                    <tr><td colspan="3"><div class="gk-empty"><span class="material-icons" style="font-size:32px;display:block;margin-bottom:8px;opacity:0.5">inbox</span>No entries found</div></td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="demo-code"><pre>&lt;div class="gk-empty"&gt;No entries found&lt;/div&gt;</pre></div>

    <hr style="border:none;border-top:1px solid var(--gk-outline-variant);margin:40px 0">

    <h3>Header</h3>
    <div class="demo-card" style="padding:0; overflow:hidden;">
        <?php
        $header = new Header();
        echo $header->title('Invoices')
            ->breadcrumb(['Dashboard' => '/', 'Billing' => '/faktura', 'Invoices'])
            ->search('Search...', 'q')
            ->action(Button::render('New Invoice', ['variant' => 'filled', 'color' => 'primary', 'icon' => 'add', 'size' => 'sm']))
            ->action(Button::icon('notifications', ['variant' => 'text', 'color' => 'neutral', 'title' => 'Notifications']))
            ->user('Martin Huber', [
                'avatar' => 'https://i.pravatar.cc/72?img=12', 'role' => 'Administrator',
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

    <h3>Sidebar</h3>
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

    <h3>Themes</h3>
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
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:16px;">
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
GK.theme.set('forest'); GK.theme.toggleMode(); GK.theme.restore();</pre></div>
</div>

<!-- ===== EXAMPLES (merged: dashboard + skeleton + auth) ===== -->
<div class="demo-section" data-section="examples">
    <h2>Examples</h2>

    <h3 style="margin: 32px 0 16px;">Dashboard Demo</h3>
    <div class="demo-card">
        <?php
        $dashStats = new StatCards('dash-stats');
        $dashStats->card('Invoices', 152, ['format' => 'number', 'color' => 'blue'])
            ->card('Revenue 2026', 127840.00, ['format' => 'currency', 'color' => 'green'])
            ->card('Open', 18320.00, ['format' => 'currency', 'color' => 'orange'])
            ->card('Overdue', 4280.00, ['format' => 'currency', 'color' => 'red'])
            ->render();

        $dashChips = new FilterChips('dash-filter', 'dash_status');
        $dashChips->chip('', 'All')
            ->chip('paid', 'Paid', ['color' => 'green'])
            ->chip('pending', 'Pending', ['color' => 'orange'])
            ->chip('overdue', 'Overdue', ['color' => 'red'])
            ->render();

        $dashYears = new YearFilter('dash-years', 'dash_year');
        $dashYears->range(2024, 2026)->render();

        $invoices = [
            ['id' => 1, 'number' => 'RE-2026-001', 'customer' => 'Mustermann GmbH', 'amount' => 2400.00, 'date' => '2026-02-01', 'status' => 'paid'],
            ['id' => 2, 'number' => 'RE-2026-002', 'customer' => 'Technik AG', 'amount' => 5800.00, 'date' => '2026-02-05', 'status' => 'pending'],
            ['id' => 3, 'number' => 'RE-2026-003', 'customer' => 'Design Studio', 'amount' => 1200.00, 'date' => '2026-01-15', 'status' => 'overdue'],
            ['id' => 4, 'number' => 'RE-2026-004', 'customer' => 'Web Solutions', 'amount' => 3600.00, 'date' => '2026-02-10', 'status' => 'paid'],
            ['id' => 5, 'number' => 'RE-2026-005', 'customer' => 'Media House', 'amount' => 950.00, 'date' => '2026-02-12', 'status' => 'pending'],
        ];
        $dashTable = new Table('dashboard-invoices');
        $dashTable->setData($invoices)
            ->search(['number', 'customer'])
            ->column('number', 'Invoice No.', ['width' => '140px', 'sortable' => true])
            ->column('customer', 'Customer', ['sortable' => true])
            ->column('amount', 'Amount', ['format' => 'currency', 'align' => 'right'])
            ->column('date', 'Date', ['format' => 'date', 'width' => '120px'])
            ->column('status', 'Status', ['format' => 'label'])
            ->paginate(10)->render();
        ?>
    </div>

    <hr style="border:none;border-top:1px solid var(--gk-outline-variant);margin:40px 0">

    <h3>Skeleton — Starting point for new projects</h3>
    <div class="demo-card">
        <p class="demo-intro"><code>skeleton.php</code> is the ready-made scaffolding for a new GridKit project. Just copy, adjust title + navigation, fill in sections — done.</p>
        <div style="background:var(--gk-primary-container);color:var(--gk-on-primary-container);border-radius:var(--gk-radius);padding:16px 20px;font-size:14px;line-height:1.7;">
            <strong>Quick start:</strong><br>
            <code style="font-size:13px;">cp /path/to/gridkit/skeleton.php my-project/index.php</code>
        </div>
    </div>
    <div class="demo-card">
        <h3 style="margin:0 0 8px;font-size:15px;color:var(--gk-on-surface, #374151);">Anatomy of a GRIDKit Page</h3>
        <p style="margin:0 0 20px;font-size:13px;color:var(--gk-on-surface-variant);">Every page follows this structure — from <code>skeleton.php</code> to production.</p>

        <div class="anatomy-layout">
            <!-- Visual page mockup -->
            <div class="anatomy-mockup" style="border:2px solid var(--gk-outline-variant);border-radius:10px;overflow:hidden;font-size:11px;display:flex;flex-direction:column;">
                <!-- Config bar -->
                <div style="background:var(--gk-primary);color:#fff;padding:6px 10px;font-weight:600;font-size:10px;letter-spacing:0.04em;text-transform:uppercase;display:flex;align-items:center;gap:6px;">
                    <span class="material-icons" style="font-size:13px">settings</span> Config
                </div>
                <div style="display:flex;flex:1;">
                    <!-- Sidebar mock -->
                    <div style="width:56px;background:var(--gk-surface-dim);border-right:1px solid var(--gk-outline-variant);display:flex;flex-direction:column;align-items:center;padding:10px 0;gap:6px;">
                        <span class="material-icons" style="font-size:16px;color:var(--gk-primary)">widgets</span>
                        <div style="width:24px;height:2px;background:var(--gk-outline-variant);border-radius:1px;margin:2px 0;"></div>
                        <span class="material-icons" style="font-size:14px;color:var(--gk-on-surface-variant)">home</span>
                        <span class="material-icons" style="font-size:14px;color:var(--gk-on-surface-variant)">people</span>
                        <span class="material-icons" style="font-size:14px;color:var(--gk-on-surface-variant)">settings</span>
                        <div style="flex:1"></div>
                        <span style="font-size:9px;color:var(--gk-on-surface-variant);writing-mode:vertical-rl;transform:rotate(180deg);letter-spacing:0.1em;">SIDEBAR</span>
                    </div>
                    <div style="flex:1;display:flex;flex-direction:column;">
                        <!-- Header mock -->
                        <div style="padding:8px 10px;border-bottom:1px solid var(--gk-outline-variant);display:flex;align-items:center;justify-content:space-between;background:var(--gk-surface);">
                            <span style="font-weight:600;font-size:11px;color:var(--gk-on-surface)">Header</span>
                            <div style="display:flex;gap:4px;align-items:center;">
                                <span class="material-icons" style="font-size:13px;color:var(--gk-on-surface-variant)">search</span>
                                <div style="width:16px;height:16px;border-radius:50%;background:var(--gk-primary-container)"></div>
                            </div>
                        </div>
                        <!-- Content mock -->
                        <div style="flex:1;padding:10px;background:var(--gk-surface-container);min-height:100px;">
                            <div style="font-size:9px;font-weight:600;color:var(--gk-on-surface-variant);text-transform:uppercase;letter-spacing:0.08em;margin-bottom:6px;">Content</div>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:4px;margin-bottom:6px;">
                                <div style="background:var(--gk-surface);border-radius:3px;height:18px;"></div>
                                <div style="background:var(--gk-surface);border-radius:3px;height:18px;"></div>
                            </div>
                            <div style="background:var(--gk-surface);border-radius:3px;height:40px;"></div>
                        </div>
                        <!-- Footer mock -->
                        <div style="padding:6px 10px;border-top:1px solid var(--gk-outline-variant);background:var(--gk-surface);font-size:9px;color:var(--gk-on-surface-variant);text-align:center;">
                            Footer · JS
                        </div>
                    </div>
                </div>
            </div>

            <!-- Code reference -->
            <div style="flex:1;display:flex;flex-direction:column;gap:8px;min-width:0;">
                <div style="display:flex;align-items:center;gap:10px;padding:10px 14px;background:var(--gk-primary);border-radius:8px;color:#fff;">
                    <span class="material-icons" style="font-size:18px">settings</span>
                    <div style="flex:1;min-width:0;">
                        <div style="font-weight:600;font-size:13px;">Configuration</div>
                        <code style="font-size:11px;opacity:0.85;word-break:break-all;">Theme::set('indigo') · Layout::bodyTag('gk-root')</code>
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:10px;padding:10px 14px;background:var(--gk-surface-container);border-radius:8px;border:1px solid var(--gk-outline-variant);">
                    <span class="material-icons" style="font-size:18px;color:var(--gk-secondary)">menu</span>
                    <div style="flex:1;min-width:0;">
                        <div style="font-weight:600;font-size:13px;color:var(--gk-on-surface);">Sidebar</div>
                        <code style="font-size:11px;color:var(--gk-on-surface-variant);word-break:break-all;">->brand() · ->group() · ->item(label, url, icon)</code>
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:10px;padding:10px 14px;background:var(--gk-surface-container);border-radius:8px;border:1px solid var(--gk-outline-variant);">
                    <span class="material-icons" style="font-size:18px;color:var(--gk-tertiary, #7c3aed)">web</span>
                    <div style="flex:1;min-width:0;">
                        <div style="font-weight:600;font-size:13px;color:var(--gk-on-surface);">Header</div>
                        <code style="font-size:11px;color:var(--gk-on-surface-variant);word-break:break-all;">->title() · ->fixed() · ->user(name, opts)</code>
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:10px;padding:10px 14px;background:var(--gk-surface-container);border-radius:8px;border:1px solid var(--gk-outline-variant);flex:1;">
                    <span class="material-icons" style="font-size:18px;color:var(--gk-error)">dashboard</span>
                    <div style="flex:1;min-width:0;">
                        <div style="font-weight:600;font-size:13px;color:var(--gk-on-surface);">Content</div>
                        <code style="font-size:11px;color:var(--gk-on-surface-variant);word-break:break-all;">&lt;main class="gk-main"&gt; · Tables, Forms, Cards…</code>
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:10px;padding:10px 14px;background:var(--gk-surface-container);border-radius:8px;border:1px solid var(--gk-outline-variant);">
                    <span class="material-icons" style="font-size:18px;color:var(--gk-outline)">code</span>
                    <div style="flex:1;min-width:0;">
                        <div style="font-weight:600;font-size:13px;color:var(--gk-on-surface);">Footer</div>
                        <code style="font-size:11px;color:var(--gk-on-surface-variant);word-break:break-all;">Modal::container() · &lt;script src="gridkit.js"&gt;</code>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <hr style="border:none;border-top:1px solid var(--gk-outline-variant);margin:40px 0">

    <h3>Auth — Login Protection</h3>
    <div class="demo-card">
        <p style="color:var(--gk-on-surface-variant);margin:0 0 16px"><code>Auth</code> protects pages with session-based login. Passwords are stored as bcrypt hashes.</p>
        <div class="demo-code">Auth::protect();              // Protect page
Auth::login($user, $pass);   // Login attempt
Auth::logout('login.php');   // Clear session
Auth::check();               // Logged in?
Auth::user();                // Username
Auth::hashPassword('abc');  // bcrypt hash
Auth::renderLogin([...]);    // Login page</div>
    </div>
    <div class="demo-card" style="text-align:center">
        <p style="margin:0 0 12px;color:var(--gk-on-surface-variant)">Open the login page live demo:</p>
        <a href="login.php" target="_blank" class="gk-btn gk-btn-filled gk-btn-primary"><span class="material-icons">lock_open</span> Open Login Demo</a>
        <p style="margin:12px 0 0;font-size:12px;color:var(--gk-on-surface-variant)">Credentials: <strong>demo</strong> / <strong>demo</strong></p>
    </div>
</div>

<!-- ===== CHANGELOG ===== -->
<div class="demo-section" data-section="changelog">
    <h2>Changelog</h2>
    <?php
    $changelog = file_get_contents(__DIR__ . '/../CHANGELOG.md');
    $versions = preg_split('/^## /m', $changelog);
    array_shift($versions);
    $count = 0;
    foreach ($versions as $v) {
        if ($count >= 8) break;
        if (str_starts_with($v, '[Unreleased]')) continue;
        $lines = explode("\n", trim($v));
        $title = array_shift($lines);
        $title = trim($title, '[] ');
        $parts = explode(' - ', $title, 2);
        $ver = $parts[0] ?? $title;
        $date = $parts[1] ?? '';
        echo '<div class="gk-segment" style="margin-bottom:12px">';
        echo '<div class="gk-segment-header" style="display:flex;align-items:center;gap:8px">';
        echo '<span style="font-family:monospace;background:var(--gk-primary);color:#fff;padding:2px 10px;border-radius:4px;font-size:12px;font-weight:600">' . htmlspecialchars($ver) . '</span>';
        if ($date) echo '<span style="font-size:12px;color:var(--gk-on-surface-variant)">' . htmlspecialchars($date) . '</span>';
        echo '</div>';
        echo '<div style="font-size:13px;line-height:1.7;color:var(--gk-on-surface-variant);margin-top:8px">';
        $body = implode("\n", $lines);
        $body = htmlspecialchars($body);
        $body = preg_replace('/^### (.+)$/m', '<strong style="color:var(--gk-on-surface);display:block;margin-top:8px">$1</strong>', $body);
        $body = preg_replace('/^- (.+)$/m', '<span style="display:block;padding-left:12px">&middot; $1</span>', $body);
        echo $body;
        echo '</div></div>';
        $count++;
    }
    ?>
</div>

<?php Modal::container(); ?>
</div><!-- /gk-with-sidebar -->

<script src="../js/gridkit.js"></script>
<script>
(function() {
    var sections = document.querySelectorAll('.demo-section');
    var links = document.querySelectorAll('.gk-sidebar-item');

    function showSection(id) {
        sections.forEach(function(s) { s.classList.remove('active'); });
        var target = document.querySelector('[data-section="' + id + '"]');
        if (target) target.classList.add('active');
        links.forEach(function(a) {
            a.classList.remove('active');
            if (a.getAttribute('href') === '#' + id) a.classList.add('active');
        });
        if (window.innerWidth <= 768) GK.sidebar.close();
    }

    links.forEach(function(a) {
        a.addEventListener('click', function(e) {
            var href = this.getAttribute('href');
            if (href && href.startsWith('#')) {
                e.preventDefault();
                showSection(href.substring(1));
                history.replaceState(null, '', href);
            }
        });
    });

    if (window.location.hash) {
        showSection(window.location.hash.substring(1));
    } else {
        showSection('table');
    }
})();

document.querySelectorAll('.gk-upload-zone[data-gk-upload]').forEach(function(zone) {
    zone.addEventListener('gk:files', function(e) {
        e.detail.items.forEach(function(item) {
            GK.uqSetUploading && item.el && GK.uqSetUploading(item);
            setTimeout(function() {
                GK.uqSetDone && item.el && GK.uqSetDone(item, item.file ? item.file.name : 'File');
            }, 1000 + Math.random() * 1000);
        });
    });
});

(function() {
    var simCounter = 0;
    function makeQueueItem(label, isError) {
        var list = document.getElementById('queue-demo-list');
        if (!list) return;
        var fakeFile = { name: label, size: Math.floor(Math.random() * 5 * 1024 * 1024) };
        var item = { file: fakeFile, el: null, id: 'qdemo-' + (++simCounter) };
        var el = document.createElement('div');
        el.style.cssText = 'display:flex;align-items:center;gap:10px;padding:10px 14px;background:var(--gk-surface-container);border-radius:6px;font-size:13px;';
        el.innerHTML = '<span class="material-icons" style="font-size:18px;color:var(--gk-primary);">hourglass_empty</span>'
                     + '<span style="flex:1;">' + label + '</span>'
                     + '<span style="color:var(--gk-on-surface-variant);">' + (GK._formatSize ? GK._formatSize(fakeFile.size) : '') + '</span>';
        item.el = el;
        list.appendChild(el);
        setTimeout(function() {
            el.querySelector('.material-icons').textContent = 'upload';
            el.querySelector('.material-icons').style.color = 'var(--gk-primary)';
            GK.uqSetUploading && GK.uqSetUploading && item.el && GK.uqSetUploading(item);
            setTimeout(function() {
                if (isError) { GK.uqSetError && GK.uqSetError(item, 'Connection interrupted'); }
                else { GK.uqSetDone && GK.uqSetDone(item, label); }
            }, 1200 + Math.random() * 800);
        }, 300);
    }
    var btnSim = document.getElementById('btn-queue-sim');
    var btnErr = document.getElementById('btn-queue-err');
    if (btnSim) btnSim.addEventListener('click', function() { makeQueueItem('dokument_' + simCounter + '.pdf', false); });
    if (btnErr) btnErr.addEventListener('click', function() { makeQueueItem('fehler_' + simCounter + '.zip', true); });
})();
</script>
</body>
</html>
