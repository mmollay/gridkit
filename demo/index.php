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

$version = trim(file_get_contents(__DIR__ . '/../VERSION'));
?>
<!DOCTYPE html>
<html lang="de">
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
    </style>
</head>
<?= Layout::bodyTag('gk-root') ?>

<?php
$sidebar = new Sidebar('demo');
$sidebar->brand('', 'widgets')
    ->group('Komponenten')
    ->item('Table', '#table', 'table_chart', ['active' => true])
    ->item('Form', '#form', 'edit_note')
    ->item('Cards', '#cards', 'grid_view')
    ->item('Layout', '#layout', 'layers')
    ->item('Navigation', '#navigation', 'filter_list')
    ->item('Feedback', '#feedback', 'notifications')
    ->item('UI', '#ui', 'palette')
    ->group('Beispiele')
    ->item('Beispiele', '#examples', 'rocket_launch')
    ->group('Info')
    ->item('Changelog', '#changelog', 'history');
$sidebar->render();
?>

<div class="gk-with-sidebar">

<?php
$demoHeader = new Header();
$headerTitle = 'GridKit <span style="font-size:11px;font-weight:400;color:var(--gk-on-surface-variant);margin-left:4px">v' . $version . '</span>';
echo $demoHeader->title($headerTitle, true)
    ->sidebarToggle(true)
    ->fixed(true)
    ->user('Demo User', [
        'avatar' => 'https://i.pravatar.cc/72?img=12',
        'menu' => [
            ['label' => 'Profil', 'href' => '#', 'icon' => 'person'],
            ['label' => 'Einstellungen', 'href' => '#', 'icon' => 'settings'],
            '---',
            ['label' => 'Abmelden', 'href' => 'login.php?logout=1', 'icon' => 'logout'],
        ],
    ])
    ->render();
?>

<!-- ===== TABLE ===== -->
<div class="demo-section active" data-section="table">
    <h2>Table</h2>

    <h3 style="margin: 32px 0 16px;">Vollstaendige Tabelle mit allen Features</h3>
        <?php
        $articles = [
            ['article_id' => 1, 'article_number' => 'ART-001', 'name' => 'Webdesign Paket S', 'unit' => 'psch', 'net_price' => 1200.00, 'tax_rate' => 20, 'is_active' => 'aktiv'],
            ['article_id' => 2, 'article_number' => 'ART-002', 'name' => 'Hosting Standard', 'unit' => 'Stk', 'net_price' => 9.90, 'tax_rate' => 20, 'is_active' => 'aktiv'],
            ['article_id' => 3, 'article_number' => 'ART-003', 'name' => 'SEO Beratung', 'unit' => 'h', 'net_price' => 95.00, 'tax_rate' => 20, 'is_active' => 'inaktiv'],
            ['article_id' => 4, 'article_number' => 'ART-004', 'name' => 'Logo Design', 'unit' => 'psch', 'net_price' => 450.00, 'tax_rate' => 20, 'is_active' => 'entwurf'],
            ['article_id' => 5, 'article_number' => 'ART-005', 'name' => 'Newsletter Setup', 'unit' => 'psch', 'net_price' => 350.00, 'tax_rate' => 20, 'is_active' => 'aktiv'],
            ['article_id' => 6, 'article_number' => 'ART-006', 'name' => 'Social Media Paket', 'unit' => 'psch', 'net_price' => 680.00, 'tax_rate' => 20, 'is_active' => 'aktiv'],
            ['article_id' => 7, 'article_number' => 'ART-007', 'name' => 'E-Mail Marketing', 'unit' => 'psch', 'net_price' => 420.00, 'tax_rate' => 20, 'is_active' => 'entwurf'],
            ['article_id' => 8, 'article_number' => 'ART-008', 'name' => 'Content Erstellung', 'unit' => 'h', 'net_price' => 75.00, 'tax_rate' => 20, 'is_active' => 'aktiv'],
            ['article_id' => 9, 'article_number' => 'ART-009', 'name' => 'Server Administration', 'unit' => 'h', 'net_price' => 110.00, 'tax_rate' => 20, 'is_active' => 'inaktiv'],
            ['article_id' => 10, 'article_number' => 'ART-010', 'name' => 'SSL Zertifikat', 'unit' => 'Stk', 'net_price' => 49.00, 'tax_rate' => 20, 'is_active' => 'aktiv'],
            ['article_id' => 11, 'article_number' => 'ART-011', 'name' => 'Domain Registration', 'unit' => 'Stk', 'net_price' => 15.00, 'tax_rate' => 20, 'is_active' => 'aktiv'],
            ['article_id' => 12, 'article_number' => 'ART-012', 'name' => 'Webdesign Paket L', 'unit' => 'psch', 'net_price' => 3500.00, 'tax_rate' => 20, 'is_active' => 'aktiv'],
            ['article_id' => 13, 'article_number' => 'ART-013', 'name' => 'App Entwicklung', 'unit' => 'h', 'net_price' => 125.00, 'tax_rate' => 20, 'is_active' => 'entwurf'],
            ['article_id' => 14, 'article_number' => 'ART-014', 'name' => 'Datenbank Migration', 'unit' => 'psch', 'net_price' => 890.00, 'tax_rate' => 20, 'is_active' => 'aktiv'],
            ['article_id' => 15, 'article_number' => 'ART-015', 'name' => 'Security Audit', 'unit' => 'psch', 'net_price' => 1500.00, 'tax_rate' => 20, 'is_active' => 'inaktiv'],
        ];
        $table = new Table('articles');
        $table->setData($articles)
            ->search(['article_number', 'name'])
            ->column('article_number', 'Artikelnr.', ['width' => '120px', 'sortable' => true, 'nowrap' => true])
            ->column('name', 'Bezeichnung', ['sortable' => true, 'nowrap' => true])
            ->column('unit', 'Einheit', ['width' => '80px', 'nowrap' => true])
            ->column('net_price', 'Netto', ['format' => 'currency', 'align' => 'right', 'width' => '100px', 'nowrap' => true])
            ->column('tax_rate', 'MwSt', ['format' => 'percent', 'width' => '80px', 'nowrap' => true])
            ->column('is_active', 'Status', ['format' => 'label', 'nowrap' => true])
            ->filter('is_active', 'select', ['options' => ['aktiv' => 'Aktiv', 'inaktiv' => 'Inaktiv', 'entwurf' => 'Entwurf'], 'placeholder' => 'Alle Status'])
            ->button('edit', ['icon' => 'edit', 'class' => 'primary', 'position' => 'right', 'params' => ['id' => 'article_id']])
            ->button('delete', ['icon' => 'delete', 'class' => 'danger', 'position' => 'right', 'params' => ['id' => 'article_id']])
            ->newButton('Neuer Artikel', ['icon' => 'add'])
            ->nowrap(true)
            ->paginate(5)
            ->render();
        ?>

    <h3 style="margin: 32px 0 16px;">Rechnungsliste mit Datums- und Waehrungsformatierung</h3>
        <?php
        $invoiceData = [
            ['number' => 'RE-2026-001', 'customer' => 'Mustermann GmbH', 'date' => '2026-02-01', 'due_date' => '2026-03-01', 'total' => 1450.00, 'status' => 'bezahlt'],
            ['number' => 'RE-2026-002', 'customer' => 'Tech Solutions AG', 'date' => '2026-02-05', 'due_date' => '2026-03-05', 'total' => 3200.00, 'status' => 'offen'],
            ['number' => 'RE-2026-003', 'customer' => 'Weber & Partner', 'date' => '2026-02-08', 'due_date' => '2026-03-08', 'total' => 890.50, 'status' => 'ueberfaellig'],
            ['number' => 'RE-2026-004', 'customer' => 'Digital Agentur Wien', 'date' => '2026-02-10', 'due_date' => '2026-03-10', 'total' => 5600.00, 'status' => 'entwurf'],
            ['number' => 'RE-2026-005', 'customer' => 'Startup Hub Vienna', 'date' => '2026-02-12', 'due_date' => '2026-03-12', 'total' => 2100.00, 'status' => 'bezahlt'],
            ['number' => 'RE-2026-006', 'customer' => 'Cafe Central KG', 'date' => '2026-02-14', 'due_date' => '2026-03-14', 'total' => 780.00, 'status' => 'offen'],
            ['number' => 'RE-2026-007', 'customer' => 'Alpen Consulting', 'date' => '2026-02-15', 'due_date' => '2026-03-15', 'total' => 4200.00, 'status' => 'bezahlt'],
            ['number' => 'RE-2026-008', 'customer' => 'Donau Logistics', 'date' => '2026-02-17', 'due_date' => '2026-03-17', 'total' => 1890.00, 'status' => 'offen'],
            ['number' => 'RE-2026-009', 'customer' => 'Wiener Werkstatt', 'date' => '2026-02-18', 'due_date' => '2026-03-18', 'total' => 560.00, 'status' => 'entwurf'],
            ['number' => 'RE-2026-010', 'customer' => 'Graz IT Services', 'date' => '2026-02-20', 'due_date' => '2026-03-20', 'total' => 3450.00, 'status' => 'bezahlt'],
            ['number' => 'RE-2026-011', 'customer' => 'Salzburg Media', 'date' => '2026-02-22', 'due_date' => '2026-03-22', 'total' => 1200.00, 'status' => 'ueberfaellig'],
            ['number' => 'RE-2026-012', 'customer' => 'Linz Digital', 'date' => '2026-02-25', 'due_date' => '2026-03-25', 'total' => 2750.00, 'status' => 'offen'],
        ];
        $invoiceTable = new Table('invoices');
        $invoiceTable->setData($invoiceData)
            ->search(['number', 'customer'])
            ->column('number', 'Re.-Nr.', ['width' => '120px', 'nowrap' => true, 'sortable' => true])
            ->column('customer', 'Kunde', ['sortable' => true, 'nowrap' => true])
            ->column('date', 'Datum', ['format' => 'date', 'width' => '100px', 'sortable' => true])
            ->column('due_date', 'Faellig', ['format' => 'date', 'width' => '100px'])
            ->column('total', 'Betrag', ['format' => 'currency', 'align' => 'right', 'width' => '120px', 'sortable' => true])
            ->column('status', 'Status', ['format' => 'label', 'width' => '100px'])
            ->button('view', ['icon' => 'visibility', 'position' => 'left'])
            ->button('edit', ['icon' => 'edit', 'class' => 'primary'])
            ->button('pdf', ['icon' => 'picture_as_pdf'])
            ->button('delete', ['icon' => 'delete', 'class' => 'danger'])
            ->newButton('Neue Rechnung', ['icon' => 'add'])
            ->nowrap(true)
            ->paginate(5)
            ->render();
        ?>

    <h3 style="margin: 32px 0 16px;">Kompakt-Tabelle ohne Toolbar</h3>
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
            ->column('role', 'Rolle', ['format' => 'label'])
            ->column('active', 'Aktiv', ['format' => 'boolean'])
            ->toolbar(false)
            ->paginate(false)
            ->render();
        ?>

    <h3 style="margin: 32px 0 16px;">Sizes</h3>
    <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:20px;">
        <?php
        $sizeData = [
            ['name' => 'Widget A', 'value' => '1.200 €', 'status' => 'aktiv'],
            ['name' => 'Widget B', 'value' => '340 €', 'status' => 'inaktiv'],
            ['name' => 'Widget C', 'value' => '890 €', 'status' => 'aktiv'],
        ];
        $sizeLabels = ['sm' => 'Kompakt', 'md' => 'Standard', 'lg' => 'Grosszuegig'];
        foreach (['sm', 'md', 'lg'] as $sz) {
            echo '<div>';
            echo '<div style="font-size:13px;font-weight:500;color:var(--gk-on-surface-variant);display:flex;align-items:center;gap:6px;margin-bottom:8px"><span style="font-size:11px;font-family:monospace;background:var(--gk-surface-container);padding:2px 8px;border-radius:4px;color:var(--gk-on-surface-variant)">size(\'' . $sz . '\')</span> ' . $sizeLabels[$sz] . '</div>';
            $t = new Table('size-' . $sz);
            $t->setData($sizeData)
                ->column('name', 'Name')
                ->column('value', 'Wert')
                ->column('status', 'Status', ['format' => 'label'])
                ->size($sz)->toolbar(false)->paginate(false)->render();
            echo "</div>";
        }
        ?>
    </div>
    <div class="demo-code"><pre>$table->size('sm');  // kompakt
$table->size('md');  // standard (default)
$table->size('lg');  // großzügig</pre></div>

    <h3 style="margin: 32px 0 16px;">Darstellungsvarianten</h3>
    <?php
    $varData = [
        ['name' => 'Webdesign Paket', 'price' => '1.200 €', 'status' => 'aktiv'],
        ['name' => 'Hosting Standard', 'price' => '9,90 €', 'status' => 'aktiv'],
        ['name' => 'SEO Beratung', 'price' => '95 €', 'status' => 'inaktiv'],
        ['name' => 'Logo Design', 'price' => '450 €', 'status' => 'entwurf'],
    ];
    $variants = [
        'default'      => 'Standard',
        'bordered'     => 'Mit Rahmen',
        'striped'      => 'Zebra-Streifen',
        'celled'       => 'Gitterlinien',
        'padded'       => 'Extra Platz',
        'compact'      => 'Kompakt',
        'selectable'   => 'Auswaehlbar',
        'minimal'      => 'Minimal',
        'flat'         => 'Flach',
        'inverted'     => 'Invertiert',
    ];
    echo '<div style="display:flex;flex-direction:column;gap:24px;margin-bottom:24px;">';
    foreach ($variants as $var => $label) {
        echo '<div>';
        echo '<div style="font-size:13px;font-weight:500;color:var(--gk-on-surface-variant);display:flex;align-items:center;gap:6px;margin-bottom:8px"><span style="font-size:11px;font-family:monospace;background:var(--gk-surface-container);padding:2px 8px;border-radius:4px;color:var(--gk-on-surface-variant)">variant(\'' . $var . '\')</span> ' . $label . '</div>';
        $t = new Table('var-' . $var);
        $t->setData($varData)
            ->column('name', 'Bezeichnung')
            ->column('price', 'Preis')
            ->column('status', 'Status', ['format' => 'label'])
            ->variant($var)->toolbar(false)->paginate(false)->render();
        echo '</div>';
    }
    echo '</div>';
    ?>
    <h3 style="margin: 32px 0 16px;">Definition-Tabelle</h3>
    <p class="demo-intro">Erste Spalte als Label/Schluessel — ideal fuer Detailansichten.</p>
    <div class="gk-table-wrap">
        <table class="gk-table gk-table-definition">
            <tbody>
                <tr><td>Firmenname</td><td>SSI Schaefer IT Solutions GmbH</td></tr>
                <tr><td>Gruendung</td><td>2003</td></tr>
                <tr><td>Standort</td><td>Wien, Oesterreich</td></tr>
                <tr><td>Mitarbeiter</td><td>24</td></tr>
                <tr><td>Website</td><td>ssi.at</td></tr>
                <tr><td>Status</td><td><span class="gk-label gk-label-green">Aktiv</span></td></tr>
            </tbody>
        </table>
    </div>

    <div class="demo-code"><pre>$table->variant('default');     // Standard
$table->variant('bordered');    // Volle Rahmenlinien
$table->variant('striped');     // Zebra-Streifen
$table->variant('celled');      // Gitterlinien um jede Zelle
$table->variant('padded');      // Extra Platz
$table->variant('compact');     // Kompakt, mehr Zeilen
$table->variant('selectable');  // Hover-Cursor, klickbar
$table->variant('minimal');     // Nur Separator, kein Rahmen
$table->variant('flat');        // Komplett flach
$table->variant('inverted');    // Dunkle Tabelle (auch im Light Mode)

// Kombinierbar:
$table->variant('striped')->size('compact');
$table->variant('celled')->variant('padded');</pre></div>

    <h3 style="margin: 32px 0 16px;">Mobile-Responsive</h3>
    <p class="demo-intro">Verkleinere das Browserfenster auf &lt;768px um die Mobile-Darstellung zu sehen.</p>

    <div style="overflow:hidden">
        <div style="padding:10px 16px;border-bottom:1px solid var(--gk-border);background:var(--gk-bg-muted);font-size:12px;font-weight:600;color:var(--gk-text-muted);letter-spacing:.04em;text-transform:uppercase;">mobile('card') – Standard</div>
        <?php
        $mobileData = [
            ['nr' => 'ART-001', 'name' => 'Webdesign Paket S', 'price' => '1.200 €', 'status' => 'aktiv'],
            ['nr' => 'ART-002', 'name' => 'Hosting Standard', 'price' => '9,90 €', 'status' => 'aktiv'],
            ['nr' => 'ART-003', 'name' => 'SEO Beratung', 'price' => '95 €', 'status' => 'inaktiv'],
        ];
        $t = new Table('mobile-card');
        $t->setData($mobileData)
            ->column('nr', 'Artikelnr.')
            ->column('name', 'Bezeichnung')
            ->column('price', 'Preis')
            ->column('status', 'Status', ['format' => 'label'])
            ->mobile('card')->toolbar(false)->paginate(false)->render();
        ?>
    </div>
    <div style="margin-top:16px;overflow:hidden">
        <div style="padding:10px 16px;border-bottom:1px solid var(--gk-border);background:var(--gk-bg-muted);font-size:12px;font-weight:600;color:var(--gk-text-muted);letter-spacing:.04em;text-transform:uppercase;">mobile('scroll') – Horizontal Scroll</div>
        <?php
        $t = new Table('mobile-scroll');
        $t->setData($mobileData)
            ->column('nr', 'Artikelnr.')
            ->column('name', 'Bezeichnung')
            ->column('price', 'Preis')
            ->column('status', 'Status', ['format' => 'label'])
            ->mobile('scroll')->toolbar(false)->paginate(false)->render();
        ?>
    </div>
    <div style="margin-top:16px;overflow:hidden">
        <div style="padding:10px 16px;border-bottom:1px solid var(--gk-border);background:var(--gk-bg-muted);font-size:12px;font-weight:600;color:var(--gk-text-muted);letter-spacing:.04em;text-transform:uppercase;">hideOnMobile – Spalten ausblenden</div>
        <?php
        $t = new Table('mobile-hide');
        $t->setData($mobileData)
            ->column('nr', 'Artikelnr.')
            ->column('name', 'Bezeichnung')
            ->column('price', 'Preis', ['hideOnMobile' => true])
            ->column('status', 'Status', ['format' => 'label'])
            ->mobile('scroll')->toolbar(false)->paginate(false)->render();
        ?>
    </div>

    <div class="demo-code"><pre>// Demo 1: Vollstaendige Artikel-Tabelle mit Pagination
$table = new Table('articles');
$table->setData($articles)
    ->search(['article_number', 'name'])
    ->column('name', 'Bezeichnung', ['sortable' => true])
    ->column('net_price', 'Netto', ['format' => 'currency'])
    ->column('tax_rate', 'MwSt', ['format' => 'percent'])
    ->column('is_active', 'Status', ['format' => 'label'])
    ->button('edit', ['icon' => 'edit', 'class' => 'primary'])
    ->button('delete', ['icon' => 'delete', 'class' => 'danger'])
    ->newButton('Neuer Artikel', ['icon' => 'add'])
    ->nowrap(true)
    ->paginate(5)
    ->render();

// Mobile-Responsive
$table->mobile('card');      // Cards auf Mobile (default)
$table->mobile('scroll');    // Horizontal Scroll
$table->column('desc', 'Beschreibung', ['hideOnMobile' => true]);</pre></div>
</div>

<!-- ===== FORM (merged: form + upload + color) ===== -->
<div class="demo-section" data-section="form">
    <h2>Form</h2>

    <div class="demo-card">
        <h3 style="margin:0 0 12px; font-size:15px; color:var(--gk-on-surface, #374151);">Grid-Layout (16-Spalten)</h3>
        <?php
        $form = new Form('article_form');
        $form->action('save/process_article.php')
            ->ajax()
            ->hidden('article_id', '')
            ->row()
                ->field('article_number', 'Artikelnr.', 'text', ['required' => true, 'width' => 8])
                ->field('name', 'Bezeichnung', 'text', ['required' => true, 'width' => 8])
            ->endRow()
            ->field('description', 'Beschreibung', 'textarea', ['rows' => 3])
            ->row()
                ->field('unit', 'Einheit', 'select', ['options' => ['Stk' => 'Stueck', 'h' => 'Stunde', 'psch' => 'Pauschal'], 'width' => 5])
                ->field('net_price', 'Netto-Preis', 'number', ['step' => '0.01', 'width' => 5])
                ->field('tax_rate', 'MwSt %', 'select', ['options' => ['20' => '20%', '10' => '10%', '0' => '0%'], 'width' => 6])
            ->endRow()
            ->field('is_active', 'Aktiv', 'toggle', ['inline' => true])
            ->submit('Speichern')
            ->render();
        ?>
    </div>

    <div class="demo-card">
        <h3 style="margin:0 0 12px; font-size:15px; color:var(--gk-on-surface, #374151);">Checkbox &amp; Radio</h3>
        <?php
        $form2 = new Form('checkbox_radio_form');
        $form2->field('agree', 'AGB akzeptieren', 'checkbox', ['checked' => true])
            ->field('newsletter', 'Newsletter abonnieren', 'checkbox')
            ->field('payment', 'Zahlungsart', 'radio', [
                'options' => ['card' => 'Kreditkarte', 'bank' => 'Bankueberweisung', 'paypal' => 'PayPal'],
                'value' => 'card',
                'inline' => true,
            ])
            ->field('priority', 'Prioritaet', 'radio', [
                'options' => ['low' => 'Niedrig', 'medium' => 'Mittel', 'high' => 'Hoch'],
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
            ->field('notifications', 'Benachrichtigungen', 'toggle', ['inline' => true, 'checked' => true])
            ->field('volume', 'Lautstaerke', 'range', ['min' => 0, 'max' => 100, 'step' => 1, 'value' => 50])
            ->field('brightness', 'Helligkeit', 'range', ['min' => 0, 'max' => 100, 'step' => 5, 'value' => 75])
            ->render();
        ?>
    </div>

    <div class="demo-card">
        <h3 style="margin:0 0 12px; font-size:15px; color:var(--gk-on-surface, #374151);">Datei-Upload</h3>
        <?php
        $form4 = new Form('upload_form');
        $form4->field('document', 'Dokument', 'file', ['accept' => '.pdf,.doc,.docx', 'multiple' => true, 'maxSize' => '10MB'])
            ->render();
        ?>
    </div>

    <div class="demo-card">
        <h3 style="margin:0 0 8px; font-size:15px; color:var(--gk-on-surface, #374151);">RichText Editor (CKEditor 5)</h3>
        <p class="demo-intro">Lokaler Vendor-Bundle (<code>vendor/ckeditor5/</code>), kein CDN. Initialisierung per <code>IntersectionObserver</code> — funktioniert auch in Tabs und Modals.</p>
        <?php
        $form5 = new Form('richtext_form');
        $form5->field('content_basic', 'Inhalt (Basic)', 'richtext', ['preset' => 'basic'])
            ->field('content_full', 'Inhalt (Full)', 'richtext', ['preset' => 'full'])
            ->render();
        ?>
    </div>

    <div class="demo-card">
        <h3 style="margin:0 0 8px; font-size:15px; color:var(--gk-on-surface, #374151);">Clearable Datum/Zeit-Felder</h3>
        <p class="demo-intro">Mistkübel-Icon erscheint nur wenn ein Wert gesetzt ist — verschwindet nach dem Löschen automatisch.</p>
        <?php
        $formClearable = new Form('clearable_form');
        $formClearable
            ->field('datum',  'Datum',    'date',     ['value' => date('Y-m-d'),      'clearable' => true])
            ->field('zeit',   'Uhrzeit',  'time',     ['value' => '09:00',            'clearable' => true])
            ->field('termin', 'Termin',   'datetime', ['value' => date('Y-m-d\TH:i'), 'clearable' => true])
            ->render();
        ?>
    </div>

    <div class="demo-card">
        <h3 style="margin:0 0 8px; font-size:15px; color:var(--gk-on-surface, #374151);">Neue Feldtypen</h3>
        <p class="demo-intro">Einheitliche Höhe von 44px für alle Input-Typen. Bei <code>number</code> kein Browser-Spinner.</p>
        <?php
        $formNew = new Form('new_fields_form');
        $formNew
            ->field('farbe', 'Farbe',  'color',  ['value' => '#6750a4'])
            ->field('monat', 'Monat',  'month',  ['value' => date('Y-m')])
            ->field('kw',    'KW',     'week',   ['value' => date('Y-\WW')])
            ->field('preis', 'Preis',  'number', ['placeholder' => '0.00'])
            ->render();
        ?>
    </div>

    <div class="demo-card">
        <h3 style="margin:0 0 12px; font-size:15px; color:var(--gk-on-surface, #374151);">Select-Erweiterungen</h3>
        <p style="margin:0 0 12px; font-size:13px; color:var(--gk-on-surface-variant, #6b7280);"><strong>Searchable Select</strong> – Land-Auswahl mit Suchfeld</p>
        <?php
        $formSearch = new Form('searchable_select_demo');
        $formSearch->field('country', 'Land', 'select', [
                'options' => [
                    'AT' => 'Österreich', 'DE' => 'Deutschland', 'CH' => 'Schweiz',
                    'IT' => 'Italien', 'FR' => 'Frankreich', 'ES' => 'Spanien',
                    'GB' => 'Großbritannien', 'NL' => 'Niederlande', 'BE' => 'Belgien',
                    'PL' => 'Polen', 'CZ' => 'Tschechien', 'HU' => 'Ungarn',
                    'SK' => 'Slowakei', 'SI' => 'Slowenien', 'HR' => 'Kroatien',
                    'SE' => 'Schweden', 'NO' => 'Norwegen', 'DK' => 'Dänemark',
                    'FI' => 'Finnland', 'PT' => 'Portugal',
                ],
                'searchable' => true, 'placeholder' => 'Land suchen...', 'value' => 'AT'
            ])->render();
        ?>
        <hr style="border:none; border-top:1px solid var(--gk-outline-variant); margin:20px 0;">
        <p style="margin:0 0 12px; font-size:13px; color:var(--gk-on-surface-variant, #6b7280);"><strong>Multi-Select</strong> – Mehrere Kategorien wählen (mit Chips)</p>
        <?php
        $formMulti = new Form('multiselect_demo');
        $formMulti->field('tags', 'Kategorien', 'multiselect', [
                'options' => ['web' => 'Webdesign', 'seo' => 'SEO', 'hosting' => 'Hosting', 'dev' => 'Entwicklung', 'support' => 'Support', 'beratung' => 'Beratung'],
                'value' => ['web', 'seo'], 'placeholder' => 'Kategorien suchen...', 'searchable' => true,
            ])->render();
        ?>
        <hr style="border:none; border-top:1px solid var(--gk-outline-variant); margin:20px 0;">
        <p style="margin:0 0 12px; font-size:13px; color:var(--gk-on-surface-variant, #6b7280);"><strong>Ajax Select</strong> – Kundensuche per API (min. 2 Zeichen)</p>
        <?php
        $formAjax = new Form('ajax_select_demo');
        $formAjax->field('customer_id', 'Kunde', 'ajaxselect', [
                'url' => 'demo/api/search.php', 'value' => '', 'displayValue' => '', 'placeholder' => 'Kunde suchen...',
                'labelField' => 'name', 'valueField' => 'id', 'subtextField' => 'city', 'minChars' => 2, 'searchParam' => 'q',
            ])->render();
        ?>
    </div>

    <div class="demo-code"><pre>// Grid-Layout (16-Spalten)
$form->row()
    ->field('name', 'Name', 'text', ['width' => 8])
    ->field('email', 'E-Mail', 'email', ['width' => 8])
->endRow()
->field('agree', 'AGB akzeptieren', 'checkbox', ['checked' => true])
->field('payment', 'Zahlungsart', 'radio', [
    'options' => ['card' => 'Kreditkarte', 'bank' => 'Ueberweisung'],
    'value' => 'card', 'inline' => true
])
->field('active', 'Aktiv', 'toggle', ['inline' => true])
->field('volume', 'Lautstaerke', 'range', ['min' => 0, 'max' => 100, 'value' => 50])
->field('doc', 'Dokument', 'file', ['accept' => '.pdf', 'multiple' => true, 'maxSize' => '10MB'])
->field('content', 'Inhalt', 'richtext', ['preset' => 'full'])
->field('datum',  'Datum',    'date',     ['value' => date('Y-m-d'),      'clearable' => true])
->field('farbe', 'Farbe',  'color',  ['value' => '#6750a4'])
->field('preis', 'Preis',  'number', ['placeholder' => '0.00'])</pre></div>

    <hr style="border:none;border-top:1px solid var(--gk-outline-variant);margin:40px 0">

    <h3>Datei-Upload (erweitert)</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-bottom:24px;">
        <div class="demo-card" style="text-align:center;"><span class="material-icons" style="font-size:32px;color:var(--gk-primary);display:block;margin-bottom:8px;">drag_indicator</span><strong>Drag &amp; Drop</strong><p class="demo-intro" style="margin:8px 0 0;">Dateien per Drag &amp; Drop oder Klick auswählen</p></div>
        <div class="demo-card" style="text-align:center;"><span class="material-icons" style="font-size:32px;color:var(--gk-primary);display:block;margin-bottom:8px;">verified</span><strong>Validierung</strong><p class="demo-intro" style="margin:8px 0 0;">Typ, Größe (min/max), Anzahl und Gesamtgröße</p></div>
        <div class="demo-card" style="text-align:center;"><span class="material-icons" style="font-size:32px;color:var(--gk-primary);display:block;margin-bottom:8px;">image</span><strong>Vorschau</strong><p class="demo-intro" style="margin:8px 0 0;">Bild-Thumbnails direkt in der Upload-Zone</p></div>
        <div class="demo-card" style="text-align:center;"><span class="material-icons" style="font-size:32px;color:var(--gk-primary);display:block;margin-bottom:8px;">list_alt</span><strong>Queue-UI</strong><p class="demo-intro" style="margin:8px 0 0;">Fortschrittsanzeige, Zustände, Fehler pro Datei</p></div>
    </div>

    <div class="demo-card">
        <h3 style="margin:0 0 8px;font-size:15px;color:var(--gk-on-surface, #374151);">Variante 1 — Einfach</h3>
        <p class="demo-intro">Dokument-Upload mit Typfilter und Größenlimit.</p>
        <?php (new Form('up-simple'))->field('doc', 'Dokument', 'file', ['accept' => ['pdf', 'doc', 'docx'], 'maxSize' => '10 MB', 'hint' => 'PDF oder Word, max. 10 MB'])->render(); ?>
    </div>

    <div class="demo-card">
        <h3 style="margin:0 0 8px;font-size:15px;color:var(--gk-on-surface, #374151);">Variante 2 — Bilder mit Vorschau</h3>
        <p class="demo-intro">Mehrfach-Upload mit Bild-Thumbnails direkt in der Zone.</p>
        <?php (new Form('up-images'))->field('fotos', 'Fotos', 'file', ['multiple' => true, 'preview' => true, 'accept' => ['jpg', 'jpeg', 'png', 'gif', 'webp'], 'maxSize' => '8 MB', 'maxFiles' => 6])->render(); ?>
    </div>

    <div class="demo-card">
        <h3 style="margin:0 0 8px;font-size:15px;color:var(--gk-on-surface, #374151);">Variante 3 — Volle Konfiguration</h3>
        <p class="demo-intro">Alle Optionen kombiniert: Typen, Min/Max-Größe, Gesamtlimit, Dateianzahl.</p>
        <?php (new Form('up-full'))->field('attachments', 'Anhänge', 'file', ['multiple' => true, 'preview' => true, 'accept' => ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'zip', 'doc', 'docx', 'txt'], 'minSize' => '1 KB', 'maxSize' => '10 MB', 'maxTotalSize' => '50 MB', 'maxFiles' => 10, 'hint' => 'Max. 10 MB/Datei · 50 MB gesamt · 10 Dateien'])->render(); ?>
    </div>

    <div class="demo-card">
        <h3 style="margin:0 0 8px;font-size:15px;color:var(--gk-on-surface, #374151);">Queue-Zustände live</h3>
        <p class="demo-intro">Queue-Items manuell durch Zustände schalten: pending → uploading → done / error.</p>
        <div class="demo-btn-row" style="margin-bottom:16px;">
            <button class="gk-btn gk-btn-primary" id="btn-queue-sim"><span class="material-icons" style="font-size:16px;">add_circle</span> Datei simulieren</button>
            <button class="gk-btn gk-btn-filled gk-btn-danger" id="btn-queue-err"><span class="material-icons" style="font-size:16px;">error_outline</span> Fehler simulieren</button>
        </div>
        <div id="queue-demo-list" style="display:flex;flex-direction:column;gap:8px;"></div>
    </div>

    <div class="demo-card">
        <h3 style="margin:0 0 8px;font-size:15px;color:var(--gk-on-surface, #374151);">Validierungsfehler Demo</h3>
        <p class="demo-intro">Zone mit strikten Limits — PDF only, max. 100 KB.</p>
        <?php (new Form('up-validate'))->field('strict_file', 'Strikt (PDF, max. 100 KB)', 'file', ['accept' => ['pdf'], 'maxSize' => '100 KB', 'hint' => 'Nur PDF, max. 100 KB — andere Dateien werden abgelehnt'])->render(); ?>
    </div>

    <hr style="border:none;border-top:1px solid var(--gk-outline-variant);margin:40px 0">

    <h3>Color Picker</h3>
    <p class="demo-intro">Styled nativer Color-Input — Farbswatch links (klickbar) + Hex-Feld rechts. Swatch und Hex-Wert synchronisieren sich automatisch, Validierung auf <code>#RRGGBB</code>.</p>

    <div class="demo-card">
        <h3 style="margin:0 0 8px;font-size:15px;color:var(--gk-on-surface, #374151);">Live-Demo</h3>
        <?php
        (new Form('color-demo'))
            ->field('primary',   'Primärfarbe',   'color', ['value' => '#6750a4'])
            ->field('secondary', 'Sekundärfarbe', 'color', ['value' => '#2563eb'])
            ->field('accent',    'Akzentfarbe',   'color', ['value' => '#16a34a'])
            ->render();
        ?>
    </div>

    <div class="demo-card">
        <h3 style="margin:0 0 12px;font-size:15px;color:var(--gk-on-surface, #374151);">Verhalten</h3>
        <ul style="margin:0;padding-left:20px;font-size:14px;line-height:1.8;color:var(--gk-on-surface-variant);">
            <li>Klick auf den <strong>Farbswatch</strong> öffnet den nativen Browser-Color-Picker</li>
            <li>Das <strong>Hex-Textfeld</strong> zeigt den aktuellen Wert und ist direkt editierbar</li>
            <li>Beide Felder synchronisieren sich in Echtzeit</li>
            <li>Validierung: nur gültige <code>#RRGGBB</code> Werte werden akzeptiert</li>
            <li>Einheitliche <strong>44px Höhe</strong> wie alle anderen GridKit-Inputs</li>
        </ul>
    </div>
</div>

<!-- ===== CARDS (merged: cards + statcards) ===== -->
<div class="demo-section" data-section="cards">
    <h2>Cards</h2>
    <p class="demo-intro">Karten-Grid für strukturierte Inhalte — responsiv, mit Header, Body und Footer.</p>

    <h3 style="margin: 32px 0 16px;">Auto-Grid (responsive)</h3>
    <div class="gk-cards">
        <div class="gk-card"><div class="gk-card-header">Webdesign</div><div class="gk-card-body"><div class="gk-card-meta">Paket S · ab 1.200 €</div><div class="gk-card-description">Responsives Design, CMS-Integration und SEO-Grundlagen für kleine Projekte.</div></div><div class="gk-card-footer">5 Projekte aktiv</div></div>
        <div class="gk-card"><div class="gk-card-header">Hosting</div><div class="gk-card-body"><div class="gk-card-meta">Managed · ab 9,90 €/Monat</div><div class="gk-card-description">SSD-Speicher, tägliche Backups und SSL inklusive. 99,9% Uptime.</div></div><div class="gk-card-footer">12 Server aktiv</div></div>
        <div class="gk-card"><div class="gk-card-header">SEO</div><div class="gk-card-body"><div class="gk-card-meta">Beratung · 95 €/h</div><div class="gk-card-description">Keyword-Analyse, On-Page-Optimierung und monatliche Reports.</div></div><div class="gk-card-footer">3 Kunden aktiv</div></div>
    </div>

    <h3 style="margin: 32px 0 16px;">Feste Spaltenanzahl</h3>
    <div class="gk-cards gk-cards-4">
        <div class="gk-card"><div class="gk-card-body" style="text-align:center"><span class="material-icons" style="font-size:32px;color:var(--gk-primary);margin-bottom:8px;display:block">speed</span><strong>Performance</strong><div class="gk-card-meta" style="margin-top:4px">99.9% Uptime</div></div></div>
        <div class="gk-card"><div class="gk-card-body" style="text-align:center"><span class="material-icons" style="font-size:32px;color:var(--gk-success);margin-bottom:8px;display:block">security</span><strong>Sicherheit</strong><div class="gk-card-meta" style="margin-top:4px">SSL & Firewall</div></div></div>
        <div class="gk-card"><div class="gk-card-body" style="text-align:center"><span class="material-icons" style="font-size:32px;color:var(--gk-warning);margin-bottom:8px;display:block">support_agent</span><strong>Support</strong><div class="gk-card-meta" style="margin-top:4px">24/7 erreichbar</div></div></div>
        <div class="gk-card"><div class="gk-card-body" style="text-align:center"><span class="material-icons" style="font-size:32px;color:var(--gk-info);margin-bottom:8px;display:block">backup</span><strong>Backups</strong><div class="gk-card-meta" style="margin-top:4px">Täglich automatisch</div></div></div>
    </div>

    <h3 style="margin: 32px 0 16px;">2-Spalten</h3>
    <div class="gk-cards gk-cards-2">
        <div class="gk-card"><div class="gk-card-header">Entwicklung</div><div class="gk-card-body"><div class="gk-card-description">Frontend und Backend Entwicklung mit modernen Technologien.</div></div></div>
        <div class="gk-card"><div class="gk-card-header">Consulting</div><div class="gk-card-body"><div class="gk-card-description">Strategische IT-Beratung f&uuml;r digitale Transformation.</div></div></div>
    </div>

    <h3 style="margin: 32px 0 16px;">3-Spalten</h3>
    <div class="gk-cards gk-cards-3">
        <div class="gk-card"><div class="gk-card-body" style="text-align:center"><span class="material-icons" style="font-size:28px;color:var(--gk-primary);display:block;margin-bottom:6px">web</span><strong>Web</strong></div></div>
        <div class="gk-card"><div class="gk-card-body" style="text-align:center"><span class="material-icons" style="font-size:28px;color:var(--gk-success);display:block;margin-bottom:6px">phone_android</span><strong>Mobile</strong></div></div>
        <div class="gk-card"><div class="gk-card-body" style="text-align:center"><span class="material-icons" style="font-size:28px;color:var(--gk-warning);display:block;margin-bottom:6px">cloud</span><strong>Cloud</strong></div></div>
    </div>

    <div class="demo-code"><pre>.gk-cards          // Auto-Grid (min 280px)
.gk-cards-2 / -3 / -4  // Feste Spalten
.gk-card .gk-card-header / .gk-card-body / .gk-card-footer
.gk-card-link      // Klickbare Card</pre></div>

    <hr style="border:none;border-top:1px solid var(--gk-outline-variant);margin:40px 0">

    <h3>Stat Cards</h3>
    <div class="demo-pair">
    <div class="demo-card">
        <?php
        $stats = new StatCards('demo-stats');
        $stats->card('Kunden', 248, ['format' => 'number', 'color' => 'blue'])
            ->card('Umsatz', 84250.00, ['format' => 'currency', 'color' => 'green'])
            ->card('Bestellungen', 64, ['format' => 'number', 'color' => 'orange'])
            ->card('Offene Posten', 12480.00, ['format' => 'currency', 'color' => 'red'])
            ->render();
        ?>
    </div>
    <div class="demo-code"><pre>$stats = new StatCards('dashboard');
$stats->card('Kunden', 248, ['format' => 'number', 'color' => 'blue'])
    ->card('Umsatz', 84250.00, ['format' => 'currency', 'color' => 'green'])
    ->card('Offene Posten', 12480.00, ['format' => 'currency', 'color' => 'red'])
    ->render();</pre></div>
    </div>
</div>

<!-- ===== LAYOUT (merged: segment + message) ===== -->
<div class="demo-section" data-section="layout">
    <h2>Layout</h2>

    <h3 style="margin: 32px 0 16px;">Segment</h3>
    <p class="demo-intro">Container für zusammengehörige Inhalte — einzeln, gestapelt oder mit Header.</p>

    <div class="gk-segment"><p>Ein einfaches Segment gruppiert zusammengehörige Inhalte visuell.</p></div>

    <h3 style="margin: 32px 0 16px;">Segment mit Header</h3>
    <div class="gk-segment"><div class="gk-segment-header">Projektübersicht</div><p>Segmente können einen Header haben um den Inhalt zu beschreiben.</p></div>

    <h3 style="margin: 32px 0 16px;">Gestapelte Segments</h3>
    <div class="gk-segments">
        <div class="gk-segment"><div class="gk-segment-header">Schritt 1</div><p>Konto erstellen und E-Mail bestätigen.</p></div>
        <div class="gk-segment"><div class="gk-segment-header">Schritt 2</div><p>Profil ausfüllen und Einstellungen konfigurieren.</p></div>
        <div class="gk-segment"><div class="gk-segment-header">Schritt 3</div><p>Erstes Projekt anlegen und loslegen.</p></div>
    </div>

    <h3 style="margin: 32px 0 16px;">Varianten</h3>
    <div class="gk-segment gk-segment-raised"><div class="gk-segment-header">Raised</div><p>Mit Schatten — hebt sich stärker vom Hintergrund ab.</p></div>
    <div class="gk-segment gk-segment-muted"><div class="gk-segment-header">Muted</div><p>Gedämpfter Hintergrund — für sekundäre Inhalte.</p></div>
    <div class="gk-segment gk-segment-compact"><div class="gk-segment-header">Compact</div><p>Weniger Padding — für dichtere Layouts.</p></div>

    <div class="demo-code"><pre>.gk-segment / .gk-segment-raised / .gk-segment-muted / .gk-segment-compact
.gk-segment-padded / .gk-segment-basic
.gk-segments > .gk-segment  // Gestapelt</pre></div>

    <hr style="border:none;border-top:1px solid var(--gk-outline-variant);margin:40px 0">

    <h3>Message</h3>
    <p class="demo-intro">Hinweise, Warnungen und Statusmeldungen für Benutzer.</p>

    <div class="gk-message"><span class="material-icons">info</span><div class="gk-message-content">Eine neutrale Nachricht ohne spezifischen Status.</div></div>

    <h3 style="margin: 32px 0 16px;">Typen</h3>
    <div class="gk-message gk-message-info"><span class="material-icons">info</span><div class="gk-message-content"><div class="gk-message-header">Information</div>Deine Änderungen werden automatisch gespeichert.</div></div>
    <div class="gk-message gk-message-success"><span class="material-icons">check_circle</span><div class="gk-message-content"><div class="gk-message-header">Erfolg</div>Das Profil wurde erfolgreich aktualisiert.</div></div>
    <div class="gk-message gk-message-warning"><span class="material-icons">warning</span><div class="gk-message-content"><div class="gk-message-header">Achtung</div>Dein SSL-Zertifikat läuft in 7 Tagen ab.</div></div>
    <div class="gk-message gk-message-error"><span class="material-icons">error</span><div class="gk-message-content"><div class="gk-message-header">Fehler</div>Die Verbindung zur Datenbank konnte nicht hergestellt werden.<ul class="gk-message-list"><li>Host nicht erreichbar</li><li>Timeout nach 30 Sekunden</li></ul></div></div>

    <h3 style="margin: 32px 0 16px;">Kompakt</h3>
    <div class="gk-message gk-message-info gk-message-compact"><span class="material-icons">info</span><div class="gk-message-content">Kompakte Nachricht für weniger Platz.</div></div>

    <h3 style="margin: 32px 0 16px;">Dismissible</h3>
    <div class="gk-message gk-message-warning" id="demo-dismiss-msg"><span class="material-icons">warning</span><div class="gk-message-content">Diese Nachricht kann geschlossen werden.</div><button class="gk-message-dismiss" onclick="this.parentElement.style.display='none'"><span class="material-icons">close</span></button></div>

    <div class="demo-code"><pre>.gk-message / .gk-message-info / .gk-message-success
.gk-message-warning / .gk-message-error / .gk-message-compact
.gk-message-dismiss  // Schliessen-Button</pre></div>
</div>

<!-- ===== NAVIGATION (merged: filterchips + yearfilter + formatter) ===== -->
<div class="demo-section" data-section="navigation">
    <h2>Navigation & Filter</h2>

    <h3 style="margin: 32px 0 16px;">Accordion</h3>
    <p class="demo-intro">Auf-/zuklappbare Inhaltsbereiche &mdash; einzeln oder als Gruppe.</p>

    <div class="gk-accordion" data-gk-single>
        <div class="gk-accordion-item open">
            <button class="gk-accordion-trigger">
                <span>Was ist GRIDKit?</span>
                <span class="material-icons">expand_more</span>
            </button>
            <div class="gk-accordion-content">
                <div class="gk-accordion-body">GRIDKit ist ein leichtgewichtiges PHP/CSS/JS Framework f&uuml;r Admin-Dashboards und interne Tools. Zero Dependencies, M3-inspiriert.</div>
            </div>
        </div>
        <div class="gk-accordion-item">
            <button class="gk-accordion-trigger">
                <span>Welche Komponenten gibt es?</span>
                <span class="material-icons">expand_more</span>
            </button>
            <div class="gk-accordion-content">
                <div class="gk-accordion-body">Table, Form, Modal, Cards, StatCards, Sidebar, Header, Tabs, Accordion, Breadcrumb, Toast, Confirm und mehr. Alle mit Light &amp; Dark Mode.</div>
            </div>
        </div>
        <div class="gk-accordion-item">
            <button class="gk-accordion-trigger">
                <span>Brauche ich npm oder Build-Tools?</span>
                <span class="material-icons">expand_more</span>
            </button>
            <div class="gk-accordion-content">
                <div class="gk-accordion-body">Nein. GRIDKit hat keine Abh&auml;ngigkeiten &mdash; eine CSS-Datei, eine JS-Datei, fertig. Einfach einbinden und loslegen.</div>
            </div>
        </div>
    </div>

    <div class="demo-code"><pre>&lt;div class="gk-accordion" data-gk-single&gt;
    &lt;div class="gk-accordion-item open"&gt;
        &lt;button class="gk-accordion-trigger"&gt;
            &lt;span&gt;Frage?&lt;/span&gt;
            &lt;span class="material-icons"&gt;expand_more&lt;/span&gt;
        &lt;/button&gt;
        &lt;div class="gk-accordion-content"&gt;
            &lt;div class="gk-accordion-body"&gt;Antwort...&lt;/div&gt;
        &lt;/div&gt;
    &lt;/div&gt;
&lt;/div&gt;

// data-gk-single: nur ein Item gleichzeitig offen
// .open: Item standardm&auml;ssig ge&ouml;ffnet</pre></div>

    <hr style="border:none;border-top:1px solid var(--gk-outline-variant);margin:40px 0">

    <h3 style="margin: 32px 0 16px;">Avatar</h3>
    <p class="demo-intro">Profilbilder mit Initialen-Fallback, Status-Dot und Gruppen.</p>

    <div class="gk-segment">
        <div class="gk-segment-header">Gr&ouml;ssen</div>
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
        <div class="gk-segment-header">Gruppe (gestapelt)</div>
        <div class="gk-avatar-group">
            <div class="gk-avatar gk-avatar-md"><img src="https://i.pravatar.cc/80?img=12" alt=""></div>
            <div class="gk-avatar gk-avatar-md"><img src="https://i.pravatar.cc/80?img=32" alt=""></div>
            <div class="gk-avatar gk-avatar-md"><img src="https://i.pravatar.cc/80?img=44" alt=""></div>
            <div class="gk-avatar gk-avatar-md"><img src="https://i.pravatar.cc/80?img=55" alt=""></div>
            <div class="gk-avatar gk-avatar-md">+3</div>
        </div>
    </div>

    <div class="demo-code"><pre>.gk-avatar.gk-avatar-lg           // Gr&ouml;ssen: xs, sm, md, lg, xl
.gk-avatar-status.online          // Status: online, away, busy, offline
.gk-avatar-group                  // Gestapelte Gruppe
.gk-avatar-square                 // Eckig statt rund</pre></div>

    <hr style="border:none;border-top:1px solid var(--gk-outline-variant);margin:40px 0">

    <h3 style="margin: 32px 0 16px;">Gallery + Lightbox</h3>
    <p class="demo-intro">Bilder-Grid mit Lazy-Loading, Hover-Overlay und Lightbox (Pfeiltasten, Escape).</p>

    <div class="gk-gallery">
        <div class="gk-gallery-item" data-lightbox="https://picsum.photos/800/600?random=1" data-caption="Landschaft 1" data-lazy>
            <img data-src="https://picsum.photos/400/400?random=1" alt="Landschaft 1">
            <div class="gk-gallery-overlay"><span class="gk-gallery-caption">Landschaft</span></div>
        </div>
        <div class="gk-gallery-item" data-lightbox="https://picsum.photos/800/600?random=2" data-caption="Architektur" data-lazy>
            <img data-src="https://picsum.photos/400/400?random=2" alt="Architektur">
            <div class="gk-gallery-overlay"><span class="gk-gallery-caption">Architektur</span></div>
        </div>
        <div class="gk-gallery-item" data-lightbox="https://picsum.photos/800/600?random=3" data-caption="Natur" data-lazy>
            <img data-src="https://picsum.photos/400/400?random=3" alt="Natur">
            <div class="gk-gallery-overlay"><span class="gk-gallery-caption">Natur</span></div>
        </div>
        <div class="gk-gallery-item" data-lightbox="https://picsum.photos/800/600?random=4" data-caption="Stadt" data-lazy>
            <img data-src="https://picsum.photos/400/400?random=4" alt="Stadt">
            <div class="gk-gallery-overlay"><span class="gk-gallery-caption">Stadt</span></div>
        </div>
        <div class="gk-gallery-item" data-lightbox="https://picsum.photos/800/600?random=5" data-caption="Abstrakt" data-lazy>
            <img data-src="https://picsum.photos/400/400?random=5" alt="Abstrakt">
            <div class="gk-gallery-overlay"><span class="gk-gallery-caption">Abstrakt</span></div>
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

// Varianten:
.gk-gallery-sm              // Kleinere Thumbnails (100px min)
.gk-gallery-lg              // Gr&ouml;ssere Thumbnails (220px min)
.gk-gallery-masonry         // Pinterest-Layout (Spalten)
// Lightbox: Pfeiltasten, Escape, Klick-Aussen-Schliessen
// data-lazy: Bilder erst laden wenn sichtbar</pre></div>

    <hr style="border:none;border-top:1px solid var(--gk-outline-variant);margin:40px 0">

    <h3 style="margin: 32px 0 16px;">Breadcrumb</h3>
    <p class="demo-intro">Pfad-Navigation zur Orientierung in verschachtelten Bereichen.</p>

    <div class="gk-segment">
        <nav class="gk-breadcrumb">
            <a href="#"><span class="material-icons">home</span></a>
            <span class="gk-breadcrumb-sep"><span class="material-icons">chevron_right</span></span>
            <a href="#">Produkte</a>
            <span class="gk-breadcrumb-sep"><span class="material-icons">chevron_right</span></span>
            <a href="#">Hosting</a>
            <span class="gk-breadcrumb-sep"><span class="material-icons">chevron_right</span></span>
            <span class="gk-breadcrumb-current">Managed Server</span>
        </nav>
        <p style="color:var(--gk-on-surface-variant);font-size:13px;margin:0">Seiteninhalt hier...</p>
    </div>

    <div class="demo-code"><pre>&lt;nav class="gk-breadcrumb"&gt;
    &lt;a href="#"&gt;&lt;span class="material-icons"&gt;home&lt;/span&gt;&lt;/a&gt;
    &lt;span class="gk-breadcrumb-sep"&gt;&lt;span class="material-icons"&gt;chevron_right&lt;/span&gt;&lt;/span&gt;
    &lt;a href="#"&gt;Produkte&lt;/a&gt;
    &lt;span class="gk-breadcrumb-sep"&gt;...&lt;/span&gt;
    &lt;span class="gk-breadcrumb-current"&gt;Aktuelle Seite&lt;/span&gt;
&lt;/nav&gt;</pre></div>

    <hr style="border:none;border-top:1px solid var(--gk-outline-variant);margin:40px 0">

    <h3 style="margin: 32px 0 16px;">Tabs</h3>
    <p class="demo-intro">Tab-Navigation zum Umschalten zwischen Inhaltsbereichen.</p>

    <div class="gk-tabs">
        <div class="gk-tab-nav">
            <button class="gk-tab-btn gk-active" data-tab="tab-overview">&Uuml;bersicht</button>
            <button class="gk-tab-btn" data-tab="tab-details">Details</button>
            <button class="gk-tab-btn" data-tab="tab-settings">Einstellungen</button>
        </div>
        <div class="gk-tab-panel gk-active" data-tab="tab-overview">
            <p>Hier steht die &Uuml;bersicht &mdash; der erste Tab ist standardm&auml;ssig aktiv.</p>
        </div>
        <div class="gk-tab-panel" data-tab="tab-details">
            <p>Detail-Informationen werden hier angezeigt.</p>
        </div>
        <div class="gk-tab-panel" data-tab="tab-settings">
            <p>Einstellungen und Konfigurationsoptionen.</p>
        </div>
    </div>

    <div class="demo-code"><pre>&lt;div class="gk-tabs"&gt;
    &lt;div class="gk-tab-nav"&gt;
        &lt;button class="gk-tab-btn gk-active" data-tab="tab-1"&gt;Tab 1&lt;/button&gt;
        &lt;button class="gk-tab-btn" data-tab="tab-2"&gt;Tab 2&lt;/button&gt;
    &lt;/div&gt;
    &lt;div class="gk-tab-panel gk-active" data-tab="tab-1"&gt;Inhalt 1&lt;/div&gt;
    &lt;div class="gk-tab-panel" data-tab="tab-2"&gt;Inhalt 2&lt;/div&gt;
&lt;/div&gt;</pre></div>

    <hr style="border:none;border-top:1px solid var(--gk-outline-variant);margin:40px 0">

    <h3 style="margin: 32px 0 16px;">Kombinationen</h3>
    <p class="demo-intro">Kombinations-Beispiele die das volle Potenzial von GRIDKit zeigen.</p>

    <h3 style="margin: 32px 0 16px;">Tabs + Accordion (kombiniert)</h3>
    <p class="demo-intro">Tabs f&uuml;r Hauptkategorien, Accordion f&uuml;r Details &mdash; ein h&auml;ufiges Pattern in Admin-UIs.</p>

    <div class="gk-tabs">
        <div class="gk-tab-nav">
            <button class="gk-tab-btn gk-active" data-tab="combo-produkte">Produkte</button>
            <button class="gk-tab-btn" data-tab="combo-kunden">Kunden</button>
            <button class="gk-tab-btn" data-tab="combo-einstellungen">Einstellungen</button>
        </div>
        <div class="gk-tab-panel gk-active" data-tab="combo-produkte">
            <div class="gk-accordion" data-gk-single style="margin-top:16px">
                <div class="gk-accordion-item open">
                    <button class="gk-accordion-trigger">
                        <span>Webdesign Pakete</span>
                        <span class="material-icons">expand_more</span>
                    </button>
                    <div class="gk-accordion-content">
                        <div class="gk-accordion-body">3 Pakete verf&uuml;gbar: S (1.200 &euro;), M (2.400 &euro;), L (3.500 &euro;). Alle inkl. responsive Design und CMS.</div>
                    </div>
                </div>
                <div class="gk-accordion-item">
                    <button class="gk-accordion-trigger">
                        <span>Hosting &amp; Server</span>
                        <span class="material-icons">expand_more</span>
                    </button>
                    <div class="gk-accordion-content">
                        <div class="gk-accordion-body">Managed Hosting ab 9,90 &euro;/Monat. SSD, Backups, SSL inklusive. 99,9% Uptime-Garantie.</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="gk-tab-panel" data-tab="combo-kunden">
            <div class="gk-message gk-message-info" style="margin-top:16px">
                <span class="material-icons">info</span>
                <div class="gk-message-content">24 aktive Kunden, 3 offene Anfragen</div>
            </div>
            <div class="gk-cards gk-cards-3" style="margin-top:12px">
                <div class="gk-card"><div class="gk-card-body"><strong>Mustermann GmbH</strong><div class="gk-card-meta">Seit 2024 &middot; 12 Projekte</div></div></div>
                <div class="gk-card"><div class="gk-card-body"><strong>Tech Solutions AG</strong><div class="gk-card-meta">Seit 2023 &middot; 8 Projekte</div></div></div>
                <div class="gk-card"><div class="gk-card-body"><strong>Weber &amp; Partner</strong><div class="gk-card-meta">Seit 2025 &middot; 3 Projekte</div></div></div>
            </div>
        </div>
        <div class="gk-tab-panel" data-tab="combo-einstellungen">
            <div class="gk-segment" style="margin-top:16px">
                <div class="gk-segment-header">Benachrichtigungen</div>
                <label class="gk-checkbox-wrap"><input type="checkbox" checked><span class="gk-checkbox-custom"></span><span>E-Mail bei neuen Anfragen</span></label>
                <br><br>
                <label class="gk-checkbox-wrap"><input type="checkbox"><span class="gk-checkbox-custom"></span><span>W&ouml;chentlicher Report</span></label>
            </div>
        </div>
    </div>

    <h3 style="margin: 32px 0 16px;">Segment + Message + Table</h3>
    <p class="demo-intro">Dashboard-Ansicht mit Status-Meldung und Datentabelle in einem Segment.</p>

    <div class="gk-segment">
        <div class="gk-segment-header">Server-Status</div>
        <div class="gk-message gk-message-success gk-message-compact" style="margin-bottom:16px">
            <span class="material-icons">check_circle</span>
            <div class="gk-message-content">Alle 3 Server sind online &mdash; letzter Check vor 2 Minuten.</div>
        </div>
        <table class="gk-table">
            <thead><tr><th>Server</th><th>Status</th><th>CPU</th><th>RAM</th><th>Uptime</th></tr></thead>
            <tbody>
                <tr><td>Baerli (server8)</td><td><span class="gk-label gk-label-green">Online</span></td><td>12%</td><td>4.2 GB</td><td>47 Tage</td></tr>
                <tr><td>Theo (server7)</td><td><span class="gk-label gk-label-green">Online</span></td><td>8%</td><td>3.8 GB</td><td>23 Tage</td></tr>
                <tr><td>Waldi (server6)</td><td><span class="gk-label gk-label-green">Online</span></td><td>15%</td><td>5.1 GB</td><td>31 Tage</td></tr>
            </tbody>
        </table>
    </div>

    <hr style="border:none;border-top:1px solid var(--gk-outline-variant);margin:40px 0">

    <h3 style="margin: 32px 0 16px;">Pagination (standalone)</h3>
    <p class="demo-intro">Seitennavigation &mdash; wird automatisch von Table generiert, kann aber auch standalone genutzt werden.</p>
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
        $chips->chip('', 'Alle', ['count' => 152])
            ->chip('aktiv', 'Aktiv', ['count' => 89, 'color' => 'green'])
            ->chip('entwurf', 'Entwurf', ['count' => 23, 'color' => 'orange'])
            ->chip('bezahlt', 'Bezahlt', ['count' => 31, 'color' => 'blue'])
            ->chip('ueberfaellig', 'Ueberfaellig', ['count' => 9, 'color' => 'red'])
            ->render();
        ?>
    </div>
    <div class="demo-code"><pre>$chips = new FilterChips('status-filter', 'status');
$chips->chip('', 'Alle', ['count' => 152])
    ->chip('aktiv', 'Aktiv', ['count' => 89, 'color' => 'green'])
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
        <p class="demo-intro">Eingebaute Formatierungen fuer Table-Spalten: currency, percent, date, boolean, label, email.</p>
        <?php
        $fmtData = [
            ['input' => 1234.56, 'pct' => 20, 'date' => '2026-02-13', 'active' => 1, 'status' => 'bezahlt', 'email' => 'info@ssi.at'],
            ['input' => 99.00, 'pct' => 10, 'date' => '2026-01-28', 'active' => 0, 'status' => 'offen', 'email' => 'office@panel.at'],
            ['input' => 5500.00, 'pct' => 0, 'date' => '2025-12-01', 'active' => 1, 'status' => 'storniert', 'email' => ''],
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
    <div class="demo-code"><pre>->column('amount', 'Betrag', ['format' => 'currency'])   // 1.234,56 EUR
->column('tax', 'MwSt', ['format' => 'percent'])         // 20%
->column('date', 'Datum', ['format' => 'date'])           // 13.02.2026
->column('active', 'Aktiv', ['format' => 'boolean'])      // Ja / Nein
->column('status', 'Status', ['format' => 'label'])       // Farbiges Label
->column('email', 'E-Mail', ['format' => 'email'])        // mailto: Link</pre></div>
    </div>
</div>

<!-- ===== FEEDBACK (merged: toast + confirm + modal) ===== -->
<div class="demo-section" data-section="feedback">
    <h2>Feedback & Dialoge</h2>

    <h3 style="margin: 32px 0 16px;">Toast</h3>
    <div class="demo-pair">
    <div class="demo-card">
        <p class="demo-intro">Toast-Benachrichtigungen fuer Erfolgs-, Fehler- und Info-Meldungen. Verschwinden nach 3 Sekunden.</p>
        <div class="demo-btn-row">
            <button class="gk-btn gk-btn-filled gk-btn-success" onclick="GK.toast.success('Erfolgreich gespeichert!')"><span class="material-icons" style="font-size:16px">check_circle</span> Success</button>
            <button class="gk-btn gk-btn-filled gk-btn-danger" onclick="GK.toast.error('Fehler beim Speichern!')"><span class="material-icons" style="font-size:16px">error</span> Error</button>
            <button class="gk-btn gk-btn-filled gk-btn-warning" onclick="GK.toast.warning('Achtung: Limit erreicht!')"><span class="material-icons" style="font-size:16px">warning</span> Warning</button>
            <button class="gk-btn gk-btn-primary" onclick="GK.toast.info('3 neue Eintraege verfuegbar')"><span class="material-icons" style="font-size:16px">info</span> Info</button>
        </div>
    </div>
    <div class="demo-code"><pre>GK.toast.success('Erfolgreich gespeichert!');
GK.toast.error('Fehler beim Speichern!');
GK.toast.warning('Achtung: Limit erreicht!');
GK.toast.info('3 neue Eintraege');</pre></div>
    </div>

    <hr style="border:none;border-top:1px solid var(--gk-outline-variant);margin:40px 0">

    <h3>Confirm</h3>
    <div class="demo-pair">
    <div class="demo-card">
        <p class="demo-intro">Confirm-Dialoge als saubere Modals. Promise-basiert, mit Danger-Mode fuer destruktive Aktionen.</p>
        <div class="demo-btn-row">
            <button class="gk-btn gk-btn-primary" onclick="GK.confirm('Rechnung an den Kunden versenden?', {title:'Rechnung versenden', confirmText:'Versenden'}).then(function(ok){ if(ok) GK.toast.success('Versendet!'); })"><span class="material-icons" style="font-size:16px">send</span> Standard Confirm</button>
            <button class="gk-btn gk-btn-danger" onclick="GK.confirm('Diesen Eintrag wirklich unwiderruflich loeschen?', {title:'Eintrag loeschen', confirmText:'Loeschen', danger:true}).then(function(ok){ if(ok) GK.toast.success('Geloescht!'); })"><span class="material-icons" style="font-size:16px">delete_forever</span> Danger Confirm</button>
        </div>
    </div>
    <div class="demo-code"><pre>GK.confirm('Rechnung versenden?', {
    title: 'Rechnung versenden', confirmText: 'Versenden'
}).then(ok => { if (ok) GK.toast.success('Versendet!'); });

GK.confirm('Wirklich loeschen?', {
    title: 'Loeschen', confirmText: 'Loeschen', danger: true
}).then(ok => { if (ok) { /* ... */ } });</pre></div>
    </div>

    <hr style="border:none;border-top:1px solid var(--gk-outline-variant);margin:40px 0">

    <h3>Modal</h3>
    <div class="demo-pair">
        <div class="demo-pair-left">
            <div class="demo-card">
                <p class="demo-intro">Modals in vier Groessen. Werden per AJAX geladen, Backdrop-Click und ESC schliessen.</p>
                <div class="demo-btn-row">
                    <button class="gk-btn" onclick="GK.modal.open('Small (420px)', 'demo/form/f_delete.php', {}, 'small')"><span class="material-icons" style="font-size:16px">crop_square</span> Small</button>
                    <button class="gk-btn gk-btn-primary" onclick="GK.modal.open('Medium (640px)', 'demo/form/f_articles.php', {}, 'medium')"><span class="material-icons" style="font-size:16px">crop_din</span> Medium</button>
                    <button class="gk-btn gk-btn-primary" onclick="GK.modal.open('Large (900px)', 'demo/form/f_articles.php', {}, 'large')"><span class="material-icons" style="font-size:16px">crop_free</span> Large</button>
                    <button class="gk-btn gk-btn-filled gk-btn-neutral" onclick="GK.modal.open('Fullscreen Modal', 'demo/form/f_articles.php', {}, 'full')"><span class="material-icons" style="font-size:16px">fullscreen</span> Full</button>
                </div>
            </div>
            <div class="demo-card">
                <h3 style="margin:0 0 8px; font-size:15px; color:var(--gk-on-surface, #374151);">Verschachtelung: Modal mit Formular</h3>
                <p class="demo-intro">Ein Modal laedt ein Formular per AJAX – der haeufigste Anwendungsfall.</p>
                <div class="demo-btn-row">
                    <button class="gk-btn gk-btn-primary" onclick="GK.modal.open('Artikel bearbeiten', 'demo/form/f_articles.php', {}, 'medium')"><span class="material-icons" style="font-size:16px">edit</span> Modal + Form</button>
                </div>
            </div>
            <div class="demo-card">
                <h3 style="margin:0 0 8px; font-size:15px; color:var(--gk-on-surface, #374151);">Verschachtelung: Modal mit Tabelle + Sub-Modal</h3>
                <p class="demo-intro">Ein Large-Modal zeigt eine Kundenliste. Klick auf "Bearbeiten" oeffnet ein zweites Modal mit dem Formular.</p>
                <div class="demo-btn-row">
                    <button class="gk-btn gk-btn-primary" onclick="GK.modal.open('Kundenverwaltung', 'demo/form/f_table_modal.php', {}, 'large')"><span class="material-icons" style="font-size:16px">people</span> Modal + Table + Sub-Modal</button>
                </div>
            </div>
        </div>
        <div class="demo-code"><pre>GK.modal.open('Titel', 'form/edit.php', {id: 42}, 'small');
GK.modal.open('Titel', 'form/edit.php', {id: 42}, 'medium');
GK.modal.open('Titel', 'form/edit.php', {id: 42}, 'large');
GK.modal.open('Titel', 'form/edit.php', {id: 42}, 'full');

$table->button('edit', ['icon' => 'edit', 'modal' => 'edit_form'])
    ->modal('edit_form', 'Bearbeiten', 'form/edit.php', ['size' => 'large']);</pre></div>
    </div>
</div>

<!-- ===== UI (merged: buttons + header + sidebar + themes) ===== -->
<div class="demo-section" data-section="ui">
    <h2>UI-Komponenten</h2>

    <h3 style="margin: 32px 0 16px;">Buttons</h3>

    <div class="demo-card">
        <h3 style="margin:0 0 12px; font-size:15px; color:var(--gk-on-surface, #374151);">Varianten</h3>
        <div class="demo-btn-row" style="margin-bottom:16px">
            <?= \GridKit\Button::render('Filled', ['variant' => 'filled', 'color' => 'primary', 'icon' => 'save']) ?>
            <?= \GridKit\Button::render('Outlined', ['variant' => 'outlined', 'color' => 'primary', 'icon' => 'save']) ?>
            <?= \GridKit\Button::render('Text', ['variant' => 'text', 'color' => 'primary', 'icon' => 'save']) ?>
            <?= \GridKit\Button::render('Tonal', ['variant' => 'tonal', 'color' => 'primary', 'icon' => 'save']) ?>
        </div>
    </div>

    <div class="demo-card">
        <h3 style="margin:0 0 12px; font-size:15px; color:var(--gk-on-surface, #374151);">Farben – Filled</h3>
        <div class="demo-btn-row" style="margin-bottom:16px">
            <?= \GridKit\Button::render('Primary', ['variant' => 'filled', 'color' => 'primary', 'icon' => 'star']) ?>
            <?= \GridKit\Button::render('Success', ['variant' => 'filled', 'color' => 'success', 'icon' => 'check_circle']) ?>
            <?= \GridKit\Button::render('Warning', ['variant' => 'filled', 'color' => 'warning', 'icon' => 'warning']) ?>
            <?= \GridKit\Button::render('Danger', ['variant' => 'filled', 'color' => 'danger', 'icon' => 'delete']) ?>
            <?= \GridKit\Button::render('Neutral', ['variant' => 'filled', 'color' => 'neutral', 'icon' => 'settings']) ?>
        </div>
        <h3 style="margin:16px 0 12px; font-size:15px; color:var(--gk-on-surface, #374151);">Farben – Outlined</h3>
        <div class="demo-btn-row" style="margin-bottom:16px">
            <?= \GridKit\Button::render('Primary', ['variant' => 'outlined', 'color' => 'primary', 'icon' => 'star']) ?>
            <?= \GridKit\Button::render('Success', ['variant' => 'outlined', 'color' => 'success', 'icon' => 'check_circle']) ?>
            <?= \GridKit\Button::render('Warning', ['variant' => 'outlined', 'color' => 'warning', 'icon' => 'warning']) ?>
            <?= \GridKit\Button::render('Danger', ['variant' => 'outlined', 'color' => 'danger', 'icon' => 'delete']) ?>
            <?= \GridKit\Button::render('Neutral', ['variant' => 'outlined', 'color' => 'neutral', 'icon' => 'settings']) ?>
        </div>
        <h3 style="margin:16px 0 12px; font-size:15px; color:var(--gk-on-surface, #374151);">Farben – Tonal</h3>
        <div class="demo-btn-row" style="margin-bottom:16px">
            <?= \GridKit\Button::render('Primary', ['variant' => 'tonal', 'color' => 'primary', 'icon' => 'star']) ?>
            <?= \GridKit\Button::render('Success', ['variant' => 'tonal', 'color' => 'success', 'icon' => 'check_circle']) ?>
            <?= \GridKit\Button::render('Warning', ['variant' => 'tonal', 'color' => 'warning', 'icon' => 'warning']) ?>
            <?= \GridKit\Button::render('Danger', ['variant' => 'tonal', 'color' => 'danger', 'icon' => 'delete']) ?>
            <?= \GridKit\Button::render('Neutral', ['variant' => 'tonal', 'color' => 'neutral', 'icon' => 'settings']) ?>
        </div>
        <h3 style="margin:16px 0 12px; font-size:15px; color:var(--gk-on-surface, #374151);">Farben – Text</h3>
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
            <?= \GridKit\Button::icon('edit', ['variant' => 'filled', 'color' => 'primary', 'title' => 'Bearbeiten']) ?>
            <?= \GridKit\Button::icon('delete', ['variant' => 'filled', 'color' => 'danger', 'title' => 'Loeschen']) ?>
            <?= \GridKit\Button::icon('add', ['variant' => 'filled', 'color' => 'success', 'title' => 'Neu']) ?>
            <?= \GridKit\Button::icon('send', ['variant' => 'filled', 'color' => 'primary', 'title' => 'Senden']) ?>
            <?= \GridKit\Button::icon('print', ['variant' => 'filled', 'color' => 'neutral', 'title' => 'Drucken']) ?>
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
        <h3 style="margin:0 0 12px; font-size:15px; color:var(--gk-on-surface, #374151);">Groessen</h3>
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
        <h3 style="margin:0 0 12px; font-size:15px; color:var(--gk-on-surface, #374151);">Icon-Position &amp; Spezial</h3>
        <div class="demo-btn-row" style="margin-bottom:16px">
            <?= \GridKit\Button::render('Icon links', ['variant' => 'filled', 'color' => 'primary', 'icon' => 'arrow_back', 'iconPosition' => 'left']) ?>
            <?= \GridKit\Button::render('Icon rechts', ['variant' => 'filled', 'color' => 'primary', 'icon' => 'arrow_forward', 'iconPosition' => 'right']) ?>
            <?= \GridKit\Button::render('Nur Text', ['variant' => 'filled', 'color' => 'primary']) ?>
        </div>
        <div class="demo-btn-row" style="margin-bottom:16px">
            <?= \GridKit\Button::render('Als Link', ['variant' => 'filled', 'color' => 'primary', 'icon' => 'open_in_new', 'href' => '#ui', 'target' => '_self']) ?>
            <?= \GridKit\Button::render('Submit', ['variant' => 'filled', 'color' => 'success', 'icon' => 'save', 'type' => 'submit']) ?>
        </div>
        <div><?= \GridKit\Button::render('Full Width', ['variant' => 'filled', 'color' => 'primary', 'icon' => 'send', 'fullWidth' => true]) ?></div>
    </div>

    <div class="demo-card">
        <h3 style="margin:0 0 12px; font-size:15px; color:var(--gk-on-surface, #374151);">Button Group</h3>
        <div class="demo-btn-row">
            <?= \GridKit\Button::group([\GridKit\Button::render('Links', ['variant' => 'outlined', 'color' => 'primary', 'icon' => 'format_align_left']), \GridKit\Button::render('Mitte', ['variant' => 'outlined', 'color' => 'primary', 'icon' => 'format_align_center']), \GridKit\Button::render('Rechts', ['variant' => 'outlined', 'color' => 'primary', 'icon' => 'format_align_right'])]) ?>
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
            <?= \GridKit\Button::fab('edit', ['extended' => true, 'label' => 'Bearbeiten']) ?>
            <?= \GridKit\Button::fab('add', ['extended' => true, 'label' => 'Erstellen', 'color' => 'success']) ?>
            <?= \GridKit\Button::fab('delete', ['extended' => true, 'label' => 'Entfernen', 'color' => 'danger']) ?>
        </div>
        <div class="demo-btn-row" style="align-items:center; gap:16px; margin-top:16px">
            <?= \GridKit\Button::fab('star', ['color' => 'warning']) ?>
            <?= \GridKit\Button::fab('favorite', ['color' => 'danger']) ?>
            <?= \GridKit\Button::fab('share', ['color' => 'neutral', 'variant' => 'tonal']) ?>
        </div>
    </div>

    <div class="demo-code"><pre>use GridKit\Button;
echo Button::render('Speichern', ['variant' => 'filled', 'color' => 'primary', 'icon' => 'save']);
echo Button::icon('edit', ['variant' => 'filled', 'color' => 'primary']);
echo Button::fab('add');
echo Button::fab('edit', ['extended' => true, 'label' => 'Bearbeiten']);</pre></div>

    <hr style="border:none;border-top:1px solid var(--gk-outline-variant);margin:40px 0">

    <h3>Labels</h3>
    <div class="demo-card">
        <p class="demo-intro">Farbige Labels f&uuml;r Status-Anzeigen &mdash; auch standalone nutzbar, nicht nur in Tabellen.</p>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px">
            <span class="gk-label gk-label-green">Aktiv</span>
            <span class="gk-label gk-label-orange">Entwurf</span>
            <span class="gk-label gk-label-red">Fehler</span>
            <span class="gk-label gk-label-gray">Archiviert</span>
            <span class="gk-label gk-label-blue">Info</span>
        </div>
    </div>
    <div class="demo-code"><pre>&lt;span class="gk-label gk-label-green"&gt;Aktiv&lt;/span&gt;
&lt;span class="gk-label gk-label-orange"&gt;Entwurf&lt;/span&gt;
&lt;span class="gk-label gk-label-red"&gt;Fehler&lt;/span&gt;
&lt;span class="gk-label gk-label-gray"&gt;Archiviert&lt;/span&gt;
&lt;span class="gk-label gk-label-blue"&gt;Info&lt;/span&gt;</pre></div>

    <hr style="border:none;border-top:1px solid var(--gk-outline-variant);margin:40px 0">

    <h3>Tooltip</h3>
    <div class="demo-card">
        <p class="demo-intro">Tooltips erscheinen automatisch bei Buttons mit <code>title</code>-Attribut. Hover &uuml;ber die Buttons:</p>
        <div class="demo-btn-row">
            <?= \GridKit\Button::icon('edit', ['variant' => 'filled', 'color' => 'primary', 'title' => 'Eintrag bearbeiten']) ?>
            <?= \GridKit\Button::icon('delete', ['variant' => 'filled', 'color' => 'danger', 'title' => 'Eintrag l&ouml;schen']) ?>
            <?= \GridKit\Button::icon('visibility', ['variant' => 'filled', 'color' => 'neutral', 'title' => 'Vorschau anzeigen']) ?>
            <?= \GridKit\Button::icon('download', ['variant' => 'outlined', 'color' => 'primary', 'title' => 'PDF herunterladen']) ?>
        </div>
    </div>
    <div class="demo-code"><pre>// Tooltip via title-Attribut (CSS-only, kein JS)
Button::icon('edit', ['title' => 'Bearbeiten']);
&lt;button class="gk-btn" title="Tooltip-Text"&gt;...&lt;/button&gt;</pre></div>

    <hr style="border:none;border-top:1px solid var(--gk-outline-variant);margin:40px 0">

    <h3>Empty State</h3>
    <div class="demo-card">
        <p class="demo-intro">Platzhalter f&uuml;r leere Tabellen oder Listen.</p>
        <div class="gk-table-wrap">
            <table class="gk-table">
                <thead><tr><th>Name</th><th>E-Mail</th><th>Status</th></tr></thead>
                <tbody>
                    <tr><td colspan="3"><div class="gk-empty"><span class="material-icons" style="font-size:32px;display:block;margin-bottom:8px;opacity:0.5">inbox</span>Keine Eintr&auml;ge vorhanden</div></td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="demo-code"><pre>&lt;div class="gk-empty"&gt;Keine Eintr&auml;ge vorhanden&lt;/div&gt;</pre></div>

    <hr style="border:none;border-top:1px solid var(--gk-outline-variant);margin:40px 0">

    <h3>Header</h3>
    <div class="demo-card" style="padding:0; overflow:hidden;">
        <?php
        $header = new Header();
        echo $header->title('Rechnungen')
            ->breadcrumb(['Dashboard' => '/', 'Faktura' => '/faktura', 'Rechnungen'])
            ->search('Suchen...', 'q')
            ->action(Button::render('Neue Rechnung', ['variant' => 'filled', 'color' => 'primary', 'icon' => 'add', 'size' => 'sm']))
            ->action(Button::icon('notifications', ['variant' => 'text', 'color' => 'neutral', 'title' => 'Benachrichtigungen']))
            ->user('Martin Huber', [
                'avatar' => 'https://i.pravatar.cc/72?img=12', 'role' => 'Administrator',
                'menu' => [
                    ['label' => 'Profil', 'href' => '/profil', 'icon' => 'person'],
                    ['label' => 'Einstellungen', 'href' => '/settings', 'icon' => 'settings'],
                    'divider',
                    ['label' => 'Abmelden', 'href' => '/logout', 'icon' => 'logout'],
                ],
            ])
            ->sticky()->render();
        ?>
    </div>
    <div class="demo-card" style="padding:0; overflow:hidden;">
        <p style="padding:16px 24px 0; margin:0; font-size:13px; color:var(--gk-on-surface-variant, #6b7280);">Minimal (nur Titel + User mit Initialen)</p>
        <?php
        $header2 = new Header();
        echo $header2->title('Dashboard')
            ->user('Anna K.', ['menu' => [['label' => 'Abmelden', 'href' => '/logout', 'icon' => 'logout']]])
            ->render();
        ?>
    </div>
    <div class="demo-code"><pre>$header = new Header();
echo $header->title('Rechnungen')
    ->breadcrumb(['Dashboard' => '/', 'Rechnungen'])
    ->search('Suchen...', 'q')
    ->action(Button::render('Neu', ['icon' => 'add', 'size' => 'sm']))
    ->user('Martin Huber', ['avatar' => '...', 'menu' => [...]])
    ->sticky()->render();</pre></div>

    <hr style="border:none;border-top:1px solid var(--gk-outline-variant);margin:40px 0">

    <h3>Sidebar</h3>
    <div class="demo-pair">
    <div class="demo-card">
        <p class="demo-intro">Responsive Sidebar-Navigation mit Gruppen, Icons, Badges und Mobile-Toggle. Auf dieser Seite live zu sehen.</p>
    </div>
    <div class="demo-code"><pre>$sidebar = new Sidebar('main');
$sidebar->brand('Mein Projekt', 'dashboard', 'v0.7.0')
    ->group('Module')
    ->item('Dashboard', '/dashboard', 'analytics', ['active' => true])
    ->item('Rechnungen', '/invoices', 'receipt_long', ['badge' => 3])
    ->render();

GK.sidebar.toggle(); GK.sidebar.open(); GK.sidebar.close();</pre></div>
    </div>

    <hr style="border:none;border-top:1px solid var(--gk-outline-variant);margin:40px 0">

    <h3>Themes</h3>
    <div class="demo-card">
        <p class="demo-intro">M3-konformes Theme-System mit 6 Themes und Dark/Light Mode.</p>
        <h3 style="margin:16px 0 12px; font-size:15px;">Layout-Modus</h3>
        <div style="display:flex; gap:8px; margin-bottom:16px;">
            <?= Button::render('Header First', ['variant' => 'tonal', 'color' => 'primary', 'onclick' => "GK.layout.set('header-first')"]) ?>
            <?= Button::render('Sidebar First', ['variant' => 'tonal', 'color' => 'primary', 'onclick' => "GK.layout.set('sidebar-first')"]) ?>
        </div>
        <h3 style="margin:16px 0 12px; font-size:15px;">Theme-Auswahl</h3>
        <?= Theme::switcher() ?>
        <h3 style="margin:24px 0 12px; font-size:15px;">Live-Vorschau</h3>
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
    <h2>Beispiele</h2>

    <h3 style="margin: 32px 0 16px;">Dashboard Demo</h3>
    <div class="demo-card">
        <?php
        $dashStats = new StatCards('dash-stats');
        $dashStats->card('Rechnungen', 152, ['format' => 'number', 'color' => 'blue'])
            ->card('Umsatz 2026', 127840.00, ['format' => 'currency', 'color' => 'green'])
            ->card('Offen', 18320.00, ['format' => 'currency', 'color' => 'orange'])
            ->card('Ueberfaellig', 4280.00, ['format' => 'currency', 'color' => 'red'])
            ->render();

        $dashChips = new FilterChips('dash-filter', 'dash_status');
        $dashChips->chip('', 'Alle')
            ->chip('bezahlt', 'Bezahlt', ['color' => 'green'])
            ->chip('offen', 'Offen', ['color' => 'orange'])
            ->chip('ueberfaellig', 'Ueberfaellig', ['color' => 'red'])
            ->render();

        $dashYears = new YearFilter('dash-years', 'dash_year');
        $dashYears->range(2024, 2026)->render();

        $invoices = [
            ['id' => 1, 'number' => 'RE-2026-001', 'customer' => 'Mustermann GmbH', 'amount' => 2400.00, 'date' => '2026-02-01', 'status' => 'bezahlt'],
            ['id' => 2, 'number' => 'RE-2026-002', 'customer' => 'Technik AG', 'amount' => 5800.00, 'date' => '2026-02-05', 'status' => 'offen'],
            ['id' => 3, 'number' => 'RE-2026-003', 'customer' => 'Design Studio', 'amount' => 1200.00, 'date' => '2026-01-15', 'status' => 'ueberfaellig'],
            ['id' => 4, 'number' => 'RE-2026-004', 'customer' => 'Web Solutions', 'amount' => 3600.00, 'date' => '2026-02-10', 'status' => 'bezahlt'],
            ['id' => 5, 'number' => 'RE-2026-005', 'customer' => 'Media House', 'amount' => 950.00, 'date' => '2026-02-12', 'status' => 'offen'],
        ];
        $dashTable = new Table('dashboard-invoices');
        $dashTable->setData($invoices)
            ->search(['number', 'customer'])
            ->column('number', 'Rechnungsnr.', ['width' => '140px', 'sortable' => true])
            ->column('customer', 'Kunde', ['sortable' => true])
            ->column('amount', 'Betrag', ['format' => 'currency', 'align' => 'right'])
            ->column('date', 'Datum', ['format' => 'date', 'width' => '120px'])
            ->column('status', 'Status', ['format' => 'label'])
            ->paginate(10)->render();
        ?>
    </div>

    <hr style="border:none;border-top:1px solid var(--gk-outline-variant);margin:40px 0">

    <h3>Skeleton — Startpunkt für neue Projekte</h3>
    <div class="demo-card">
        <p class="demo-intro"><code>skeleton.php</code> ist das fertige Grundgerüst für ein neues GridKit-Projekt. Einfach kopieren, Titel + Navigation anpassen, Sektionen befüllen — fertig.</p>
        <div style="background:var(--gk-primary-container);color:var(--gk-on-primary-container);border-radius:var(--gk-radius);padding:16px 20px;font-size:14px;line-height:1.7;">
            <strong>Schnellstart:</strong><br>
            <code style="font-size:13px;">cp /path/to/gridkit/skeleton.php mein-projekt/index.php</code>
        </div>
    </div>
    <div class="demo-card">
        <h3 style="margin:0 0 16px;font-size:15px;color:var(--gk-on-surface, #374151);">Anatomie</h3>
        <div style="display:grid;grid-template-columns:1fr 2fr;gap:12px;font-size:13px;">
            <div style="background:var(--gk-surface-container);padding:12px 16px;border-radius:var(--gk-radius-sm);border-left:3px solid var(--gk-primary);"><strong>Konfiguration</strong><br><span style="color:var(--gk-on-surface-variant)">Theme, Layout-Modus, Seiten-Titel</span></div>
            <div style="font-size:12px;color:var(--gk-on-surface-variant);padding:12px 0;">Theme::set('indigo', 'light') · Layout::mode('header-first')</div>
            <div style="background:var(--gk-surface-container);padding:12px 16px;border-radius:var(--gk-radius-sm);border-left:3px solid var(--gk-secondary);"><strong>Sidebar</strong><br><span style="color:var(--gk-on-surface-variant)">Brand, Gruppen, Navigation-Items</span></div>
            <div style="font-size:12px;color:var(--gk-on-surface-variant);padding:12px 0;">->brand() · ->group() · ->item(label, url, icon, opts)</div>
            <div style="background:var(--gk-surface-container);padding:12px 16px;border-radius:var(--gk-radius-sm);border-left:3px solid var(--gk-tertiary);"><strong>Header</strong><br><span style="color:var(--gk-on-surface-variant)">Fixed, Toggle, Actions, User-Menü</span></div>
            <div style="font-size:12px;color:var(--gk-on-surface-variant);padding:12px 0;">->title() · ->fixed() · ->action() · ->user(name, opts)</div>
            <div style="background:var(--gk-surface-container);padding:12px 16px;border-radius:var(--gk-radius-sm);border-left:3px solid var(--gk-error);"><strong>Content</strong><br><span style="color:var(--gk-on-surface-variant)">Sektionen via ?section=...</span></div>
            <div style="font-size:12px;color:var(--gk-on-surface-variant);padding:12px 0;">&lt;main class="gk-main"&gt; · if/elseif-Blöcke</div>
            <div style="background:var(--gk-surface-container);padding:12px 16px;border-radius:var(--gk-radius-sm);border-left:3px solid var(--gk-outline);"><strong>Footer</strong><br><span style="color:var(--gk-on-surface-variant)">Modal::container() + gridkit.js</span></div>
            <div style="font-size:12px;color:var(--gk-on-surface-variant);padding:12px 0;">Modal::container() · &lt;script src="gridkit.js"&gt;</div>
        </div>
    </div>

    <hr style="border:none;border-top:1px solid var(--gk-outline-variant);margin:40px 0">

    <h3>Auth — Login-Schutz</h3>
    <div class="demo-card">
        <p style="color:var(--gk-on-surface-variant);margin:0 0 16px"><code>Auth</code> schützt Seiten mit Session-basiertem Login. Passwörter werden als bcrypt-Hash gespeichert.</p>
        <div class="demo-code">Auth::protect();              // Seite schützen
Auth::login($user, $pass);   // Login-Versuch
Auth::logout('login.php');   // Session löschen
Auth::check();               // eingeloggt?
Auth::user();                // Username
Auth::hashPassword('abc');  // bcrypt-Hash
Auth::renderLogin([...]);    // Login-Page</div>
    </div>
    <div class="demo-card" style="text-align:center">
        <p style="margin:0 0 12px;color:var(--gk-on-surface-variant)">Live-Demo der Login-Seite öffnen:</p>
        <a href="login.php" target="_blank" class="gk-btn gk-btn-filled gk-btn-primary"><span class="material-icons">lock_open</span> Login Demo öffnen</a>
        <p style="margin:12px 0 0;font-size:12px;color:var(--gk-on-surface-variant)">Zugangsdaten: <strong>demo</strong> / <strong>demo</strong></p>
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
                GK.uqSetDone && item.el && GK.uqSetDone(item, item.file ? item.file.name : 'Datei');
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
                if (isError) { GK.uqSetError && GK.uqSetError(item, 'Verbindung unterbrochen'); }
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
