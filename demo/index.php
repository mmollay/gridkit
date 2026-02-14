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
    <style>
        body { margin:0; padding:0; background:var(--gk-surface-container, #f0f1f3); font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; color:var(--gk-on-surface, #1f2937); }
        .demo-header { background:var(--gk-surface-dim, #1e293b); color:var(--gk-on-surface, #fff); padding:24px 32px; display:flex; align-items:center; gap:16px; }
        .demo-header h1 { margin:0; font-size:22px; font-weight:700; }
        .demo-header .version { background:rgba(255,255,255,0.15); padding:2px 10px; border-radius:12px; font-size:12px; }
        .demo-section { max-width:1100px; margin:24px auto; padding:0 24px; display:none; }
        .demo-section.active { display:block; }
        .demo-section h2 { font-size:20px; margin:0 0 16px; color:#374151; }
        .demo-card { background:var(--gk-surface, #fff); border-radius:8px; padding:24px; box-shadow:var(--gk-shadow); margin-bottom:24px; }
        .demo-code { background:var(--gk-surface-dim, #1e293b); color:var(--gk-on-surface, #e2e8f0); padding:20px; border-radius:8px; overflow-x:auto; font-family:'SF Mono',Monaco,Consolas,monospace; font-size:13px; line-height:1.6; margin-top:16px; }
        .demo-code pre { margin:0; }
        .demo-stats-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:16px; margin-bottom:24px; }
        .demo-stat { background:var(--gk-surface, #fff); border-radius:8px; padding:20px; text-align:center; box-shadow:var(--gk-shadow); }
        .demo-stat .num { font-size:28px; font-weight:700; color:var(--gk-primary); }
        .demo-stat .lbl { font-size:13px; color:var(--gk-on-surface-variant, #6b7280); margin-top:4px; }
        .demo-intro { color:#6b7280; margin:0 0 16px; font-size:14px; line-height:1.6; }
        .demo-btn-row { display:flex; gap:8px; flex-wrap:wrap; }
    </style>
</head>
<body data-gk-theme="indigo" data-gk-mode="light" class="gk-root">

<?php
$sidebar = new Sidebar('demo');
$sidebar->brand('GridKit', 'widgets', 'v' . $version)
    ->group('Komponenten')
    ->item('Table', '#table', 'table_chart', ['active' => true])
    ->item('Form', '#form', 'edit_note')
    ->item('Modal', '#modal', 'open_in_new')
    ->item('Formatter', '#formatter', 'format_paint')
    ->group('Dashboard')
    ->item('StatCards', '#statcards', 'analytics')
    ->item('FilterChips', '#filterchips', 'filter_list')
    ->item('YearFilter', '#yearfilter', 'date_range')
    ->group('Design')
    ->item('Themes', '#themes', 'palette')
    ->group('UI-Kit')
    ->item('Toast', '#toast', 'notifications')
    ->item('Confirm', '#confirm', 'help_outline')
    ->item('Sidebar', '#sidebar', 'menu')
    ->item('Buttons', '#buttons', 'smart_button')
    ->item('Header', '#header', 'web_asset')
    ->group('Beispiele')
    ->item('Dashboard Demo', '#dashboard', 'dashboard');
$sidebar->render();
?>

<div class="gk-with-sidebar">

<div class="demo-header">
    <?php Sidebar::toggleButton(); ?>
    <h1>GridKit <span class="version">v<?= $version ?></span></h1>
    <div style="flex:1"></div>
    <?= Theme::switcher() ?>
</div>

<!-- ===== OVERVIEW (default) ===== -->
<div class="demo-section active" data-section="overview">
    <div class="demo-stats-grid">
        <div class="demo-stat"><div class="num">7</div><div class="lbl">PHP-Klassen</div></div>
        <div class="demo-stat"><div class="num">1</div><div class="lbl">CSS + 1 JS</div></div>
        <div class="demo-stat"><div class="num">~1500</div><div class="lbl">Zeilen gesamt</div></div>
        <div class="demo-stat"><div class="num">0</div><div class="lbl">Abhaengigkeiten</div></div>
    </div>
</div>

<!-- ===== TABLE ===== -->
<div class="demo-section" data-section="table">
    <h2>Table</h2>

    <h3 style="margin: 32px 0 16px;">Vollstaendige Tabelle mit allen Features</h3>
    <div class="demo-card">
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
    </div>

    <h3 style="margin: 32px 0 16px;">Rechnungsliste mit Datums- und Waehrungsformatierung</h3>
    <div class="demo-card">
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
    </div>

    <h3 style="margin: 32px 0 16px;">Kompakt-Tabelle ohne Toolbar</h3>
    <div class="demo-card">
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
    </div>

    <!-- === TABLE SIZES === -->
    <h3 style="margin: 32px 0 16px;">Sizes: sm / md / lg</h3>
    <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:16px;">
        <?php
        $sizeData = [
            ['name' => 'Widget A', 'value' => '1.200 €', 'status' => 'aktiv'],
            ['name' => 'Widget B', 'value' => '340 €', 'status' => 'inaktiv'],
            ['name' => 'Widget C', 'value' => '890 €', 'status' => 'aktiv'],
        ];
        foreach (['sm', 'md', 'lg'] as $sz) {
            echo '<div class="demo-card" style="padding:0;overflow:hidden"><h4 style="padding:12px 16px 0;margin:0;font-size:13px;color:#6b7280">size(\'' . $sz . '\')</h4>';
            $t = new Table('size-' . $sz);
            $t->setData($sizeData)
                ->column('name', 'Name')
                ->column('value', 'Wert')
                ->column('status', 'Status', ['format' => 'label'])
                ->size($sz)->toolbar(false)->paginate(false)->render();
            echo '</div>';
        }
        ?>
    </div>

    <!-- === TABLE VARIANTS === -->
    <h3 style="margin: 32px 0 16px;">Darstellungsvarianten</h3>
    <?php
    $varData = [
        ['name' => 'Webdesign Paket', 'price' => '1.200 €', 'status' => 'aktiv'],
        ['name' => 'Hosting Standard', 'price' => '9,90 €', 'status' => 'aktiv'],
        ['name' => 'SEO Beratung', 'price' => '95 €', 'status' => 'inaktiv'],
        ['name' => 'Logo Design', 'price' => '450 €', 'status' => 'entwurf'],
    ];
    foreach (['default', 'bordered', 'striped', 'minimal', 'flat'] as $var) {
        echo '<div class="demo-card" style="margin-bottom:16px;padding:0;overflow:hidden"><h4 style="padding:12px 16px 0;margin:0;font-size:13px;color:#6b7280">variant(\'' . $var . '\')</h4>';
        $t = new Table('var-' . $var);
        $t->setData($varData)
            ->column('name', 'Bezeichnung')
            ->column('price', 'Preis')
            ->column('status', 'Status', ['format' => 'label'])
            ->variant($var)->toolbar(false)->paginate(false)->render();
        echo '</div>';
    }
    ?>

    <!-- === MOBILE DEMO === -->
    <h3 style="margin: 32px 0 16px;">Mobile-Responsive</h3>
    <p class="demo-intro">Verkleinere das Browserfenster auf &lt;768px um die Mobile-Darstellung zu sehen.</p>

    <div class="demo-card" style="padding:0;overflow:hidden">
        <h4 style="padding:12px 16px 0;margin:0;font-size:13px;color:#6b7280">mobile('card') – Standard</h4>
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
    <div class="demo-card" style="margin-top:16px;padding:0;overflow:hidden">
        <h4 style="padding:12px 16px 0;margin:0;font-size:13px;color:#6b7280">mobile('scroll') – Horizontal Scroll</h4>
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
    <div class="demo-card" style="margin-top:16px;padding:0;overflow:hidden">
        <h4 style="padding:12px 16px 0;margin:0;font-size:13px;color:#6b7280">hideOnMobile – Spalten ausblenden</h4>
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

// Demo 2: Rechnungsliste
$invoiceTable = new Table('invoices');
$invoiceTable->setData($invoiceData)
    ->column('number', 'Re.-Nr.', ['width' => '120px', 'sortable' => true])
    ->column('total', 'Betrag', ['format' => 'currency', 'align' => 'right'])
    ->column('date', 'Datum', ['format' => 'date'])
    ->column('status', 'Status', ['format' => 'label'])
    ->paginate(5)
    ->render();

// Demo 3: Kompakt ohne Toolbar
$miniTable = new Table('users');
$miniTable->setData($userData)
    ->column('name', 'Name', ['sortable' => true])
    ->column('email', 'E-Mail', ['format' => 'email'])
    ->column('role', 'Rolle', ['format' => 'label'])
    ->column('active', 'Aktiv', ['format' => 'boolean'])
    ->toolbar(false)
    ->render();

// Sizes
$table->size('sm');  // kompakt
$table->size('md');  // standard (default)
$table->size('lg');  // grosszuegig

// Varianten
$table->variant('bordered');  // Volle Rahmenlinien
$table->variant('striped');   // Zebra-Streifen
$table->variant('minimal');   // Nur Zeilen-Separator
$table->variant('flat');      // Komplett flach

// Kombinierbar
$table->variant('bordered')->size('sm');

// Mobile-Responsive
$table->mobile('card');      // Cards auf Mobile (default)
$table->mobile('scroll');    // Horizontal Scroll
$table->mobile('scroll');    // + hideOnMobile auf Spalten:
$table->column('desc', 'Beschreibung', ['hideOnMobile' => true]);</pre></div>
</div>

<!-- ===== FORM ===== -->
<div class="demo-section" data-section="form">
    <h2>Form</h2>

    <!-- Classic Form -->
    <div class="demo-card">
        <h3 style="margin:0 0 12px; font-size:15px; color:#374151;">Grid-Layout (16-Spalten)</h3>
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

    <!-- Checkbox & Radio -->
    <div class="demo-card">
        <h3 style="margin:0 0 12px; font-size:15px; color:#374151;">Checkbox &amp; Radio</h3>
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

    <!-- Toggle & Slider -->
    <div class="demo-card">
        <h3 style="margin:0 0 12px; font-size:15px; color:#374151;">Toggle &amp; Slider</h3>
        <?php
        $form3 = new Form('toggle_slider_form');
        $form3->field('dark_mode', 'Dark Mode', 'toggle', ['inline' => true])
            ->field('notifications', 'Benachrichtigungen', 'toggle', ['inline' => true, 'checked' => true])
            ->field('volume', 'Lautstaerke', 'range', ['min' => 0, 'max' => 100, 'step' => 1, 'value' => 50])
            ->field('brightness', 'Helligkeit', 'range', ['min' => 0, 'max' => 100, 'step' => 5, 'value' => 75])
            ->render();
        ?>
    </div>

    <!-- File Upload -->
    <div class="demo-card">
        <h3 style="margin:0 0 12px; font-size:15px; color:#374151;">Datei-Upload</h3>
        <?php
        $form4 = new Form('upload_form');
        $form4->field('document', 'Dokument', 'file', ['accept' => '.pdf,.doc,.docx', 'multiple' => true, 'maxSize' => '10MB'])
            ->render();
        ?>
    </div>

    <!-- RichText Editor -->
    <div class="demo-card">
        <h3 style="margin:0 0 12px; font-size:15px; color:#374151;">RichText Editor</h3>
        <?php
        $form5 = new Form('richtext_form');
        $form5->field('content_basic', 'Inhalt (Basic)', 'richtext', ['toolbar' => 'basic'])
            ->field('content_full', 'Inhalt (Full)', 'richtext', ['toolbar' => 'full'])
            ->render();
        ?>
    </div>

    <div class="demo-code"><pre>// Grid-Layout (16-Spalten)
$form->row()
    ->field('name', 'Name', 'text', ['width' => 8])
    ->field('email', 'E-Mail', 'email', ['width' => 8])
->endRow()

// Checkbox
->field('agree', 'AGB akzeptieren', 'checkbox', ['checked' => true])

// Radio (inline)
->field('payment', 'Zahlungsart', 'radio', [
    'options' => ['card' => 'Kreditkarte', 'bank' => 'Ueberweisung'],
    'value' => 'card', 'inline' => true
])

// Toggle (inline)
->field('active', 'Aktiv', 'toggle', ['inline' => true])

// Slider
->field('volume', 'Lautstaerke', 'range', ['min' => 0, 'max' => 100, 'value' => 50])

// File Upload
->field('doc', 'Dokument', 'file', ['accept' => '.pdf', 'multiple' => true, 'maxSize' => '10MB'])

// RichText Editor
->field('content', 'Inhalt', 'richtext', ['toolbar' => 'full'])</pre></div>
</div>

<!-- ===== MODAL ===== -->
<div class="demo-section" data-section="modal">
    <h2>Modal</h2>
    <div class="demo-card">
        <p class="demo-intro">Modals in vier Groessen. Werden per AJAX geladen, Backdrop-Click und ESC schliessen.</p>
        <div class="demo-btn-row">
            <button class="gk-btn" onclick="GK.modal.open('Small (420px)', 'demo/form/f_delete.php', {}, 'small')">
                <span class="material-icons" style="font-size:16px">crop_square</span> Small
            </button>
            <button class="gk-btn gk-btn-primary" onclick="GK.modal.open('Medium (640px)', 'demo/form/f_articles.php', {}, 'medium')">
                <span class="material-icons" style="font-size:16px">crop_din</span> Medium
            </button>
            <button class="gk-btn gk-btn-primary" onclick="GK.modal.open('Large (900px)', 'demo/form/f_articles.php', {}, 'large')">
                <span class="material-icons" style="font-size:16px">crop_free</span> Large
            </button>
            <button class="gk-btn" style="background:#1e293b;color:#fff;border-color:#1e293b" onclick="GK.modal.open('Fullscreen Modal', 'demo/form/f_articles.php', {}, 'full')">
                <span class="material-icons" style="font-size:16px">fullscreen</span> Full
            </button>
        </div>
    </div>
    <div class="demo-card">
        <h3 style="margin:0 0 8px; font-size:15px; color:#374151;">Verschachtelung: Modal mit Formular</h3>
        <p class="demo-intro">Ein Modal laedt ein Formular per AJAX – der haeufigste Anwendungsfall.</p>
        <div class="demo-btn-row">
            <button class="gk-btn gk-btn-primary" onclick="GK.modal.open('Artikel bearbeiten', 'demo/form/f_articles.php', {}, 'medium')">
                <span class="material-icons" style="font-size:16px">edit</span> Modal + Form
            </button>
        </div>
    </div>

    <div class="demo-card">
        <h3 style="margin:0 0 8px; font-size:15px; color:#374151;">Verschachtelung: Modal mit Tabelle + Sub-Modal</h3>
        <p class="demo-intro">Ein Large-Modal zeigt eine Kundenliste. Klick auf "Bearbeiten" oeffnet ein zweites Modal mit dem Formular.</p>
        <div class="demo-btn-row">
            <button class="gk-btn gk-btn-primary" onclick="GK.modal.open('Kundenverwaltung', 'demo/form/f_table_modal.php', {}, 'large')">
                <span class="material-icons" style="font-size:16px">people</span> Modal + Table + Sub-Modal
            </button>
        </div>
    </div>

    <div class="demo-code"><pre>// Sizes: small (420px), medium (640px), large (900px), full (100%)
GK.modal.open('Titel', 'form/edit.php', {id: 42}, 'small');
GK.modal.open('Titel', 'form/edit.php', {id: 42}, 'medium');
GK.modal.open('Titel', 'form/edit.php', {id: 42}, 'large');
GK.modal.open('Titel', 'form/edit.php', {id: 42}, 'full');

// PHP: Modal an Table-Button binden
$table->button('edit', ['icon' => 'edit', 'modal' => 'edit_form'])
    ->modal('edit_form', 'Bearbeiten', 'form/edit.php', ['size' => 'large']);

// Am Seitenende: Modal::container();</pre></div>
</div>

<!-- ===== FORMATTER ===== -->
<div class="demo-section" data-section="formatter">
    <h2>Formatter</h2>
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

<!-- ===== STATCARDS ===== -->
<div class="demo-section" data-section="statcards">
    <h2>StatCards</h2>
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

<!-- ===== FILTERCHIPS ===== -->
<div class="demo-section" data-section="filterchips">
    <h2>FilterChips</h2>
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
    ->chip('offen', 'Offen', ['count' => 23, 'color' => 'orange'])
    ->render();</pre></div>
</div>

<!-- ===== YEARFILTER ===== -->
<div class="demo-section" data-section="yearfilter">
    <h2>YearFilter</h2>
    <div class="demo-card">
        <?php
        $years = new YearFilter('demo-years', 'year');
        $years->range(2022, 2026)->render();
        ?>
    </div>
    <div class="demo-code"><pre>$years = new YearFilter('year-nav', 'year');
$years->range(2022, 2026)->render();
// Aktuelles Jahr: $years->current()</pre></div>
</div>

<!-- ===== TOAST ===== -->
<div class="demo-section" data-section="toast">
    <h2>Toast</h2>
    <div class="demo-card">
        <p class="demo-intro">Toast-Benachrichtigungen fuer Erfolgs-, Fehler- und Info-Meldungen. Verschwinden nach 3 Sekunden.</p>
        <div class="demo-btn-row">
            <button class="gk-btn" style="background:#059669;color:#fff;border-color:#059669" onclick="GK.toast.success('Erfolgreich gespeichert!')">
                <span class="material-icons" style="font-size:16px">check_circle</span> Success
            </button>
            <button class="gk-btn" style="background:#dc2626;color:#fff;border-color:#dc2626" onclick="GK.toast.error('Fehler beim Speichern!')">
                <span class="material-icons" style="font-size:16px">error</span> Error
            </button>
            <button class="gk-btn" style="background:#d97706;color:#fff;border-color:#d97706" onclick="GK.toast.warning('Achtung: Limit erreicht!')">
                <span class="material-icons" style="font-size:16px">warning</span> Warning
            </button>
            <button class="gk-btn" style="background:#2563eb;color:#fff;border-color:#2563eb" onclick="GK.toast.info('3 neue Eintraege verfuegbar')">
                <span class="material-icons" style="font-size:16px">info</span> Info
            </button>
        </div>
    </div>
    <div class="demo-code"><pre>GK.toast.success('Erfolgreich gespeichert!');
GK.toast.error('Fehler beim Speichern!');
GK.toast.warning('Achtung: Limit erreicht!');
GK.toast.info('3 neue Eintraege');

// Mit Dauer (ms): GK.toast.success('OK!', 5000);</pre></div>
</div>

<!-- ===== CONFIRM ===== -->
<div class="demo-section" data-section="confirm">
    <h2>Confirm</h2>
    <div class="demo-card">
        <p class="demo-intro">Confirm-Dialoge als saubere Modals. Promise-basiert, mit Danger-Mode fuer destruktive Aktionen.</p>
        <div class="demo-btn-row">
            <button class="gk-btn gk-btn-primary" onclick="GK.confirm('Rechnung an den Kunden versenden?', {title:'Rechnung versenden', confirmText:'Versenden'}).then(function(ok){ if(ok) GK.toast.success('Versendet!'); })">
                <span class="material-icons" style="font-size:16px">send</span> Standard Confirm
            </button>
            <button class="gk-btn gk-btn-danger" onclick="GK.confirm('Diesen Eintrag wirklich unwiderruflich loeschen?', {title:'Eintrag loeschen', confirmText:'Loeschen', danger:true}).then(function(ok){ if(ok) GK.toast.success('Geloescht!'); })">
                <span class="material-icons" style="font-size:16px">delete_forever</span> Danger Confirm
            </button>
        </div>
    </div>
    <div class="demo-code"><pre>GK.confirm('Rechnung versenden?', {
    title: 'Rechnung versenden',
    confirmText: 'Versenden'
}).then(ok => { if (ok) GK.toast.success('Versendet!'); });

// Danger-Modus (roter Button)
GK.confirm('Wirklich loeschen?', {
    title: 'Loeschen', confirmText: 'Loeschen', danger: true
}).then(ok => { if (ok) { /* ... */ } });</pre></div>
</div>

<!-- ===== SIDEBAR ===== -->
<div class="demo-section" data-section="sidebar">
    <h2>Sidebar</h2>
    <div class="demo-card">
        <p class="demo-intro">Responsive Sidebar-Navigation mit Gruppen, Icons, Badges und Mobile-Toggle. Auf dieser Seite live zu sehen.</p>
    </div>
    <div class="demo-code"><pre>$sidebar = new Sidebar('main');
$sidebar->brand('Mein Projekt', 'dashboard', 'v0.5.0')
    ->group('Module')
    ->item('Dashboard', '/dashboard', 'analytics', ['active' => true])
    ->item('Rechnungen', '/invoices', 'receipt_long', ['badge' => 3])
    ->item('Kunden', '/customers', 'people')
    ->group('System')
    ->item('Einstellungen', '/settings', 'settings');
$sidebar->render();

// Im Header:
Sidebar::toggleButton();

// JS API:
GK.sidebar.toggle();
GK.sidebar.open();
GK.sidebar.close();</pre></div>
</div>

<!-- ===== BUTTONS ===== -->
<div class="demo-section" data-section="buttons">
    <h2>Buttons</h2>

    <!-- Variants -->
    <div class="demo-card">
        <h3 style="margin:0 0 12px; font-size:15px; color:#374151;">Varianten</h3>
        <div class="demo-btn-row" style="margin-bottom:16px">
            <?= \GridKit\Button::render('Filled', ['variant' => 'filled', 'color' => 'primary', 'icon' => 'save']) ?>
            <?= \GridKit\Button::render('Outlined', ['variant' => 'outlined', 'color' => 'primary', 'icon' => 'save']) ?>
            <?= \GridKit\Button::render('Text', ['variant' => 'text', 'color' => 'primary', 'icon' => 'save']) ?>
            <?= \GridKit\Button::render('Tonal', ['variant' => 'tonal', 'color' => 'primary', 'icon' => 'save']) ?>
        </div>
    </div>

    <!-- Colors -->
    <div class="demo-card">
        <h3 style="margin:0 0 12px; font-size:15px; color:#374151;">Farben – Filled</h3>
        <div class="demo-btn-row" style="margin-bottom:16px">
            <?= \GridKit\Button::render('Primary', ['variant' => 'filled', 'color' => 'primary', 'icon' => 'star']) ?>
            <?= \GridKit\Button::render('Success', ['variant' => 'filled', 'color' => 'success', 'icon' => 'check_circle']) ?>
            <?= \GridKit\Button::render('Warning', ['variant' => 'filled', 'color' => 'warning', 'icon' => 'warning']) ?>
            <?= \GridKit\Button::render('Danger', ['variant' => 'filled', 'color' => 'danger', 'icon' => 'delete']) ?>
            <?= \GridKit\Button::render('Neutral', ['variant' => 'filled', 'color' => 'neutral', 'icon' => 'settings']) ?>
        </div>

        <h3 style="margin:16px 0 12px; font-size:15px; color:#374151;">Farben – Outlined</h3>
        <div class="demo-btn-row" style="margin-bottom:16px">
            <?= \GridKit\Button::render('Primary', ['variant' => 'outlined', 'color' => 'primary', 'icon' => 'star']) ?>
            <?= \GridKit\Button::render('Success', ['variant' => 'outlined', 'color' => 'success', 'icon' => 'check_circle']) ?>
            <?= \GridKit\Button::render('Warning', ['variant' => 'outlined', 'color' => 'warning', 'icon' => 'warning']) ?>
            <?= \GridKit\Button::render('Danger', ['variant' => 'outlined', 'color' => 'danger', 'icon' => 'delete']) ?>
            <?= \GridKit\Button::render('Neutral', ['variant' => 'outlined', 'color' => 'neutral', 'icon' => 'settings']) ?>
        </div>

        <h3 style="margin:16px 0 12px; font-size:15px; color:#374151;">Farben – Tonal</h3>
        <div class="demo-btn-row" style="margin-bottom:16px">
            <?= \GridKit\Button::render('Primary', ['variant' => 'tonal', 'color' => 'primary', 'icon' => 'star']) ?>
            <?= \GridKit\Button::render('Success', ['variant' => 'tonal', 'color' => 'success', 'icon' => 'check_circle']) ?>
            <?= \GridKit\Button::render('Warning', ['variant' => 'tonal', 'color' => 'warning', 'icon' => 'warning']) ?>
            <?= \GridKit\Button::render('Danger', ['variant' => 'tonal', 'color' => 'danger', 'icon' => 'delete']) ?>
            <?= \GridKit\Button::render('Neutral', ['variant' => 'tonal', 'color' => 'neutral', 'icon' => 'settings']) ?>
        </div>

        <h3 style="margin:16px 0 12px; font-size:15px; color:#374151;">Farben – Text</h3>
        <div class="demo-btn-row">
            <?= \GridKit\Button::render('Primary', ['variant' => 'text', 'color' => 'primary', 'icon' => 'star']) ?>
            <?= \GridKit\Button::render('Success', ['variant' => 'text', 'color' => 'success', 'icon' => 'check_circle']) ?>
            <?= \GridKit\Button::render('Warning', ['variant' => 'text', 'color' => 'warning', 'icon' => 'warning']) ?>
            <?= \GridKit\Button::render('Danger', ['variant' => 'text', 'color' => 'danger', 'icon' => 'delete']) ?>
            <?= \GridKit\Button::render('Neutral', ['variant' => 'text', 'color' => 'neutral', 'icon' => 'settings']) ?>
        </div>
    </div>

    <!-- Icon only -->
    <div class="demo-card">
        <h3 style="margin:0 0 12px; font-size:15px; color:#374151;">Icon-Only</h3>
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

    <!-- Sizes -->
    <div class="demo-card">
        <h3 style="margin:0 0 12px; font-size:15px; color:#374151;">Groessen</h3>
        <div class="demo-btn-row" style="align-items:center">
            <?= \GridKit\Button::render('Small', ['variant' => 'filled', 'color' => 'primary', 'size' => 'sm', 'icon' => 'edit']) ?>
            <?= \GridKit\Button::render('Medium', ['variant' => 'filled', 'color' => 'primary', 'size' => 'md', 'icon' => 'edit']) ?>
            <?= \GridKit\Button::render('Large', ['variant' => 'filled', 'color' => 'primary', 'size' => 'lg', 'icon' => 'edit']) ?>
        </div>
    </div>

    <!-- States -->
    <div class="demo-card">
        <h3 style="margin:0 0 12px; font-size:15px; color:#374151;">States</h3>
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

    <!-- Icon position + special -->
    <div class="demo-card">
        <h3 style="margin:0 0 12px; font-size:15px; color:#374151;">Icon-Position &amp; Spezial</h3>
        <div class="demo-btn-row" style="margin-bottom:16px">
            <?= \GridKit\Button::render('Icon links', ['variant' => 'filled', 'color' => 'primary', 'icon' => 'arrow_back', 'iconPosition' => 'left']) ?>
            <?= \GridKit\Button::render('Icon rechts', ['variant' => 'filled', 'color' => 'primary', 'icon' => 'arrow_forward', 'iconPosition' => 'right']) ?>
            <?= \GridKit\Button::render('Nur Text', ['variant' => 'filled', 'color' => 'primary']) ?>
        </div>
        <div class="demo-btn-row" style="margin-bottom:16px">
            <?= \GridKit\Button::render('Als Link', ['variant' => 'filled', 'color' => 'primary', 'icon' => 'open_in_new', 'href' => '#buttons', 'target' => '_self']) ?>
            <?= \GridKit\Button::render('Submit', ['variant' => 'filled', 'color' => 'success', 'icon' => 'save', 'type' => 'submit']) ?>
        </div>
        <div>
            <?= \GridKit\Button::render('Full Width', ['variant' => 'filled', 'color' => 'primary', 'icon' => 'send', 'fullWidth' => true]) ?>
        </div>
    </div>

    <!-- Button Group -->
    <div class="demo-card">
        <h3 style="margin:0 0 12px; font-size:15px; color:#374151;">Button Group</h3>
        <div class="demo-btn-row">
            <?= \GridKit\Button::group([
                \GridKit\Button::render('Links', ['variant' => 'outlined', 'color' => 'primary', 'icon' => 'format_align_left']),
                \GridKit\Button::render('Mitte', ['variant' => 'outlined', 'color' => 'primary', 'icon' => 'format_align_center']),
                \GridKit\Button::render('Rechts', ['variant' => 'outlined', 'color' => 'primary', 'icon' => 'format_align_right']),
            ]) ?>
            &nbsp;
            <?= \GridKit\Button::group([
                \GridKit\Button::icon('undo', ['variant' => 'outlined', 'color' => 'neutral']),
                \GridKit\Button::icon('redo', ['variant' => 'outlined', 'color' => 'neutral']),
            ]) ?>
        </div>
    </div>

    <!-- Shapes -->
    <div class="demo-card">
        <h3 style="margin:0 0 12px; font-size:15px; color:#374151;">Shapes</h3>
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

    <!-- FAB -->
    <div class="demo-card">
        <h3 style="margin:0 0 12px; font-size:15px; color:#374151;">FAB (Floating Action Button)</h3>
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

// Filled + Icon
echo Button::render('Speichern', ['variant' => 'filled', 'color' => 'primary', 'icon' => 'save']);
echo Button::render('Loeschen', ['variant' => 'outlined', 'color' => 'danger', 'icon' => 'delete']);
echo Button::render('Abbrechen', ['variant' => 'text', 'color' => 'neutral']);
echo Button::render('Entwurf', ['variant' => 'tonal', 'color' => 'warning', 'icon' => 'edit']);

// Icon-only
echo Button::icon('edit', ['variant' => 'filled', 'color' => 'primary', 'title' => 'Bearbeiten']);
echo Button::icon('delete', ['variant' => 'text', 'color' => 'danger']);

// Sizes: sm, md (default), lg
echo Button::render('Klein', ['size' => 'sm', 'icon' => 'edit']);
echo Button::render('Gross', ['size' => 'lg', 'icon' => 'edit']);

// States
echo Button::render('Disabled', ['disabled' => true]);
echo Button::render('Loading', ['loading' => true]);
echo Button::render('Badge', ['icon' => 'mail', 'badge' => 3]);

// Group
echo Button::group([
    Button::icon('undo', ['variant' => 'outlined', 'color' => 'neutral']),
    Button::icon('redo', ['variant' => 'outlined', 'color' => 'neutral']),
]);

// Shapes: rounded (default), pill, circle, square
echo Button::render('Pill', ['shape' => 'pill', 'icon' => 'star']);
echo Button::icon('star', ['shape' => 'circle']);

// FAB (Floating Action Button)
echo Button::fab('add');                              // 56px default
echo Button::fab('add', ['size' => 'sm']);            // 40px
echo Button::fab('add', ['size' => 'lg']);            // 96px
echo Button::fab('edit', ['extended' => true, 'label' => 'Bearbeiten']); // Extended FAB</pre></div>
</div>

<!-- ===== HEADER ===== -->
<div class="demo-section" data-section="header">
    <h2>Header</h2>
    <div class="demo-card" style="padding:0; overflow:hidden;">
        <?php
        $header = new Header();
        echo $header->title('Rechnungen')
            ->breadcrumb(['Dashboard' => '/', 'Faktura' => '/faktura', 'Rechnungen'])
            ->search('Suchen...', 'q')
            ->action(Button::render('Neue Rechnung', ['variant' => 'filled', 'color' => 'primary', 'icon' => 'add', 'size' => 'sm']))
            ->action(Button::icon('notifications', ['variant' => 'text', 'color' => 'neutral', 'title' => 'Benachrichtigungen']))
            ->user('Martin Huber', [
                'avatar' => 'https://i.pravatar.cc/72?img=12',
                'role' => 'Administrator',
                'menu' => [
                    ['label' => 'Profil', 'href' => '/profil', 'icon' => 'person'],
                    ['label' => 'Einstellungen', 'href' => '/settings', 'icon' => 'settings'],
                    'divider',
                    ['label' => 'Abmelden', 'href' => '/logout', 'icon' => 'logout'],
                ],
            ])
            ->sticky()
            ->render();
        ?>
    </div>
    <div class="demo-card" style="padding:0; overflow:hidden;">
        <p style="padding:16px 24px 0; margin:0; font-size:13px; color:#6b7280;">Minimal (nur Titel + User mit Initialen)</p>
        <?php
        $header2 = new Header();
        echo $header2->title('Dashboard')
            ->user('Anna K.', [
                'menu' => [
                    ['label' => 'Abmelden', 'href' => '/logout', 'icon' => 'logout'],
                ],
            ])
            ->render();
        ?>
    </div>
    <p class="gk-text-muted" style="padding: 12px 24px; margin: 0; font-size: 13px; color: #6b7280;">In der Praxis: <code>-&gt;fixed(true)</code> fixiert den Header am oberen Rand.
Die Sidebar beginnt dann automatisch unterhalb (64px offset).</p>
    <div class="demo-code"><pre>use GridKit\Header;
use GridKit\Button;

$header = new Header();
echo $header->title('Rechnungen')
    ->breadcrumb(['Dashboard' => '/', 'Faktura' => '/faktura', 'Rechnungen'])
    ->search('Suchen...', 'q')
    ->action(Button::render('Neue Rechnung', ['icon' => 'add', 'size' => 'sm']))
    ->user('Martin Huber', [
        'avatar' => 'https://example.com/avatar.jpg',
        'role' => 'Administrator',
        'menu' => [
            ['label' => 'Profil', 'href' => '/profil', 'icon' => 'person'],
            ['label' => 'Einstellungen', 'href' => '/settings', 'icon' => 'settings'],
            'divider',
            ['label' => 'Abmelden', 'href' => '/logout', 'icon' => 'logout'],
        ],
    ])
    ->sticky()
    ->render();

// Standard-Layout mit fixed Header + Sidebar:
// echo $header->title('Dashboard')->fixed(true)->render();
// echo $sidebar->render();  // beginnt unter dem Header
// echo '&lt;main class="gk-main gk-body-with-header"&gt;...&lt;/main&gt;';</pre></div>
</div>

<!-- ===== THEMES ===== -->
<div class="demo-section" data-section="themes">
    <h2>Themes</h2>
    <div class="demo-card">
        <p class="demo-intro">M3-konformes Theme-System mit 6 Themes und Dark/Light Mode. Themes werden per <code>data-gk-theme</code> und <code>data-gk-mode</code> Attributen am Body gesteuert.</p>

        <h3 style="margin:16px 0 12px; font-size:15px;">Theme-Auswahl</h3>
        <?= Theme::switcher() ?>

        <h3 style="margin:24px 0 12px; font-size:15px;">Live-Vorschau</h3>
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:16px;">
            <div style="background:var(--gk-surface-container); border-radius:var(--gk-radius); padding:20px; border:1px solid var(--gk-outline-variant);">
                <h4 style="margin:0 0 8px; color:var(--gk-on-surface);">Card Title</h4>
                <p style="margin:0 0 12px; color:var(--gk-on-surface-variant); font-size:13px;">This card uses surface-container background with on-surface text colors.</p>
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
    <div class="demo-code"><pre>use GridKit\Theme;

// Set theme + mode
Theme::set('ocean', 'dark');

// Body tag with attributes
echo Theme::bodyTag('gk-root');
// &lt;body data-gk-theme="ocean" data-gk-mode="dark" class="gk-root"&gt;

// Theme switcher widget
echo Theme::switcher();

// Available themes
$themes = Theme::available();

// JS API
GK.theme.set('forest');     // Switch theme
GK.theme.toggleMode();      // Toggle dark/light
GK.theme.restore();         // Restore from localStorage</pre></div>
</div>

<!-- ===== DASHBOARD DEMO ===== -->
<div class="demo-section" data-section="dashboard">
    <h2>Dashboard - Alle Komponenten</h2>
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
</div>

<?php Modal::container(); ?>
</div><!-- /gk-with-sidebar -->

<script src="../js/gridkit.js"></script>
<script>
(function() {
    // SPA navigation via sidebar
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

        // Mobile: close sidebar after nav
        if (window.innerWidth <= 768) GK.sidebar.close();
    }

    links.forEach(function(a) {
        a.addEventListener('click', function(e) {
            var href = this.getAttribute('href');
            if (href && href.startsWith('#')) {
                e.preventDefault();
                var id = href.substring(1);
                showSection(id);
                history.replaceState(null, '', href);
            }
        });
    });

    // Handle initial hash
    if (window.location.hash) {
        var id = window.location.hash.substring(1);
        showSection(id);
    } else {
        // Show overview + table by default
        showSection('table');
    }
})();
</script>
</body>
</html>
