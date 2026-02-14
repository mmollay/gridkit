<?php
require_once __DIR__ . '/../autoload.php';
use GridKit\Table;
use GridKit\Form;
use GridKit\Modal;
use GridKit\StatCards;
use GridKit\Sidebar;
use GridKit\FilterChips;
use GridKit\YearFilter;

$version = trim(file_get_contents(__DIR__ . '/../VERSION'));
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GridKit Demo v<?= $version ?></title>
    <link rel="stylesheet" href="../css/gridkit.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <style>
        body { margin:0; padding:0; background:#f0f1f3; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; color:#1f2937; }
        .demo-header { background:#1e293b; color:#fff; padding:24px 32px; display:flex; align-items:center; gap:16px; }
        .demo-header h1 { margin:0; font-size:22px; font-weight:700; }
        .demo-header .version { background:rgba(255,255,255,0.15); padding:2px 10px; border-radius:12px; font-size:12px; }
        .demo-section { max-width:1100px; margin:24px auto; padding:0 24px; display:none; }
        .demo-section.active { display:block; }
        .demo-section h2 { font-size:20px; margin:0 0 16px; color:#374151; }
        .demo-card { background:#fff; border-radius:8px; padding:24px; box-shadow:0 1px 3px rgba(0,0,0,0.08); margin-bottom:24px; }
        .demo-code { background:#1e293b; color:#e2e8f0; padding:20px; border-radius:8px; overflow-x:auto; font-family:'SF Mono',Monaco,Consolas,monospace; font-size:13px; line-height:1.6; margin-top:16px; }
        .demo-code pre { margin:0; }
        .demo-stats-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:16px; margin-bottom:24px; }
        .demo-stat { background:#fff; border-radius:8px; padding:20px; text-align:center; box-shadow:0 1px 3px rgba(0,0,0,0.08); }
        .demo-stat .num { font-size:28px; font-weight:700; color:#2563eb; }
        .demo-stat .lbl { font-size:13px; color:#6b7280; margin-top:4px; }
        .demo-intro { color:#6b7280; margin:0 0 16px; font-size:14px; line-height:1.6; }
        .demo-btn-row { display:flex; gap:8px; flex-wrap:wrap; }
    </style>
</head>
<body>

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
    ->group('UI-Kit')
    ->item('Toast', '#toast', 'notifications')
    ->item('Confirm', '#confirm', 'help_outline')
    ->item('Sidebar', '#sidebar', 'menu')
    ->group('Beispiele')
    ->item('Dashboard Demo', '#dashboard', 'dashboard');
$sidebar->render();
?>

<div class="gk-with-sidebar">

<div class="demo-header">
    <?php Sidebar::toggleButton(); ?>
    <h1>GridKit <span class="version">v<?= $version ?></span></h1>
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
    <div class="demo-card">
        <?php
        $articles = [
            ['article_id' => 1, 'article_number' => 'ART-001', 'name' => 'Webdesign Paket S', 'unit' => 'psch', 'net_price' => 1200.00, 'tax_rate' => 20, 'is_active' => 'aktiv'],
            ['article_id' => 2, 'article_number' => 'ART-002', 'name' => 'Hosting Standard', 'unit' => 'Stk', 'net_price' => 9.90, 'tax_rate' => 20, 'is_active' => 'aktiv'],
            ['article_id' => 3, 'article_number' => 'ART-003', 'name' => 'SEO Beratung', 'unit' => 'h', 'net_price' => 95.00, 'tax_rate' => 20, 'is_active' => 'inaktiv'],
            ['article_id' => 4, 'article_number' => 'ART-004', 'name' => 'Logo Design', 'unit' => 'psch', 'net_price' => 450.00, 'tax_rate' => 20, 'is_active' => 'entwurf'],
            ['article_id' => 5, 'article_number' => 'ART-005', 'name' => 'Newsletter Setup', 'unit' => 'psch', 'net_price' => 350.00, 'tax_rate' => 20, 'is_active' => 'aktiv'],
        ];
        $table = new Table('articles');
        $table->setData($articles)
            ->search(['article_number', 'name'])
            ->column('article_number', 'Artikelnr.', ['width' => '110px', 'sortable' => true])
            ->column('name', 'Bezeichnung', ['sortable' => true])
            ->column('unit', 'Einheit', ['width' => '80px'])
            ->column('net_price', 'Netto', ['format' => 'currency', 'align' => 'right'])
            ->column('tax_rate', 'MwSt', ['format' => 'percent', 'width' => '80px'])
            ->column('is_active', 'Status', ['format' => 'label'])
            ->filter('is_active', 'select', ['options' => ['aktiv' => 'Aktiv', 'inaktiv' => 'Inaktiv', 'entwurf' => 'Entwurf'], 'placeholder' => 'Alle Status'])
            ->button('edit', ['icon' => 'pencil', 'text' => 'Bearbeiten', 'position' => 'left', 'modal' => 'edit_form', 'params' => ['id' => 'article_id']])
            ->button('delete', ['icon' => 'trash', 'class' => 'danger', 'position' => 'right', 'modal' => 'delete_form', 'params' => ['id' => 'article_id']])
            ->modal('edit_form', 'Artikel bearbeiten', 'form/f_articles.php', ['size' => 'medium'])
            ->modal('delete_form', 'Artikel loeschen', 'form/f_delete.php', ['size' => 'small'])
            ->newButton('Neuer Artikel', ['modal' => 'edit_form'])
            ->paginate(25)
            ->render();
        ?>
    </div>
    <div class="demo-code"><pre>$table = new Table('articles');
$table->query($db, "SELECT * FROM articles")
    ->search(['article_number', 'name'])
    ->column('name', 'Bezeichnung', ['sortable' => true])
    ->column('net_price', 'Netto', ['format' => 'currency'])
    ->column('is_active', 'Status', ['format' => 'label'])
    ->button('edit', ['icon' => 'pencil', 'modal' => 'edit_form'])
    ->modal('edit_form', 'Bearbeiten', 'form/edit.php')
    ->newButton('Neuer Artikel', ['modal' => 'edit_form'])
    ->paginate(25)
    ->render();</pre></div>
</div>

<!-- ===== FORM ===== -->
<div class="demo-section" data-section="form">
    <h2>Form</h2>
    <div class="demo-card">
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
            ->field('is_active', 'Aktiv', 'toggle')
            ->submit('Speichern')
            ->render();
        ?>
    </div>
    <div class="demo-code"><pre>$form = new Form('article_form');
$form->action('save/process.php')
    ->ajax()
    ->row()
        ->field('name', 'Name', 'text', ['required' => true, 'width' => 8])
        ->field('email', 'E-Mail', 'email', ['width' => 8])
    ->endRow()
    ->field('notes', 'Notizen', 'textarea', ['rows' => 3])
    ->field('active', 'Aktiv', 'toggle')
    ->submit('Speichern')
    ->render();</pre></div>
</div>

<!-- ===== MODAL ===== -->
<div class="demo-section" data-section="modal">
    <h2>Modal</h2>
    <div class="demo-card">
        <p class="demo-intro">Modals werden per AJAX geladen. Drei Groessen: small (420px), medium (640px), large (900px).</p>
        <div class="demo-btn-row">
            <button class="gk-btn gk-btn-primary" onclick="GK.modal.open('Small Modal', 'form/f_articles.php', {}, 'small')">
                <span class="material-icons" style="font-size:16px">add</span> Small
            </button>
            <button class="gk-btn gk-btn-primary" onclick="GK.modal.open('Medium Modal', 'form/f_articles.php', {}, 'medium')">
                <span class="material-icons" style="font-size:16px">edit</span> Medium
            </button>
            <button class="gk-btn" onclick="GK.modal.open('Delete Modal', 'form/f_delete.php', {}, 'small')">
                <span class="material-icons" style="font-size:16px">delete</span> Delete
            </button>
        </div>
    </div>
    <div class="demo-code"><pre>// PHP: Modal an Table-Button binden
$table->button('edit', ['icon' => 'edit', 'modal' => 'edit_form'])
    ->modal('edit_form', 'Bearbeiten', 'form/edit.php', ['size' => 'medium']);

// JS: Modal direkt oeffnen
GK.modal.open('Titel', 'form/edit.php', {id: 42}, 'medium');

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
$sidebar->brand('Mein Projekt', 'dashboard', 'v1.0')
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
