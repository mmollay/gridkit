<?php
require_once __DIR__ . '/../autoload.php';
use GridKit\Table;
use GridKit\Form;
use GridKit\Modal;
use GridKit\StatCards;
use GridKit\FilterChips;
use GridKit\YearFilter;
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GridKit Demo</title>
    <link rel="stylesheet" href="../css/gridkit.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <style>
        body {
            margin: 0;
            padding: 0;
            background: #f0f1f3;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: #1f2937;
        }
        .demo-header {
            background: #1e293b;
            color: #fff;
            padding: 40px;
            text-align: center;
        }
        .demo-header h1 {
            margin: 0 0 8px;
            font-size: 32px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }
        .demo-header p {
            margin: 0;
            opacity: 0.7;
            font-size: 16px;
        }
        .demo-header .version {
            display: inline-block;
            background: rgba(255,255,255,0.15);
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 13px;
            margin-left: 8px;
        }
        .demo-section {
            max-width: 1100px;
            margin: 30px auto;
            padding: 0 20px;
        }
        .demo-section h2 {
            font-size: 20px;
            margin: 0 0 16px;
            color: #374151;
        }
        .demo-card {
            background: #fff;
            border-radius: 8px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        .demo-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 16px;
            margin-bottom: 30px;
        }
        .demo-stat {
            background: #fff;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }
        .demo-stat .num { font-size: 28px; font-weight: 700; color: #2563eb; }
        .demo-stat .lbl { font-size: 13px; color: #6b7280; margin-top: 4px; }
        .demo-code {
            background: #1e293b;
            color: #e2e8f0;
            padding: 20px;
            border-radius: 8px;
            overflow-x: auto;
            font-family: 'SF Mono', Monaco, Consolas, monospace;
            font-size: 13px;
            line-height: 1.6;
            margin-top: 16px;
        }
    </style>
</head>
<body>

<div class="demo-header">
    <h1>GridKit <span class="version">v<?= trim(file_get_contents(__DIR__ . '/../VERSION')) ?></span></h1>
    <p>Schlankes PHP-Framework fuer Tabellen, Formulare, Dashboards &amp; UI-Kit. Null Abhaengigkeiten.</p>
</div>

<div class="demo-section">
    <div class="demo-stats">
        <div class="demo-stat">
            <div class="num">6</div>
            <div class="lbl">Klassen</div>
        </div>
        <div class="demo-stat">
            <div class="num">1</div>
            <div class="lbl">CSS File</div>
        </div>
        <div class="demo-stat">
            <div class="num">~1400</div>
            <div class="lbl">Zeilen gesamt</div>
        </div>
        <div class="demo-stat">
            <div class="num">0</div>
            <div class="lbl">Abhängigkeiten</div>
        </div>
    </div>
</div>

<!-- TABLE DEMO -->
<div class="demo-section">
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
            ->filter('is_active', 'select', [
                'options' => ['aktiv' => 'Aktiv', 'inaktiv' => 'Inaktiv', 'entwurf' => 'Entwurf'],
                'placeholder' => 'Alle Status'
            ])
            ->filter('unit', 'select', [
                'options' => ['Stk' => 'Stück', 'h' => 'Stunde', 'psch' => 'Pauschal'],
                'placeholder' => 'Alle Einheiten'
            ])
            ->button('edit', ['icon' => 'pencil', 'text' => 'Bearbeiten', 'position' => 'left', 'modal' => 'edit_form', 'params' => ['id' => 'article_id']])
            ->button('delete', ['icon' => 'trash', 'class' => 'danger', 'position' => 'right', 'modal' => 'delete_form', 'params' => ['id' => 'article_id']])
            ->modal('edit_form', 'Artikel bearbeiten', 'form/f_articles.php', ['size' => 'medium'])
            ->modal('delete_form', 'Artikel löschen', 'form/f_delete.php', ['size' => 'small'])
            ->newButton('Neuer Artikel', ['modal' => 'edit_form'])
            ->paginate(25)
            ->render();
        ?>
    </div>

    <div class="demo-code"><pre>&lt;?php
$table = new Table('articles');
$table-&gt;query($db, "SELECT * FROM articles ORDER BY name")
    -&gt;search(['article_number', 'name'])
    -&gt;column('article_number', 'Artikelnr.', ['width' =&gt; '110px', 'sortable' =&gt; true])
    -&gt;column('name', 'Bezeichnung', ['sortable' =&gt; true])
    -&gt;column('net_price', 'Netto', ['format' =&gt; 'currency', 'align' =&gt; 'right'])
    -&gt;column('is_active', 'Status', ['format' =&gt; 'label'])
    -&gt;button('edit', ['icon' =&gt; 'pencil', 'modal' =&gt; 'edit_form'])
    -&gt;newButton('Neuer Artikel', ['modal' =&gt; 'edit_form'])
    -&gt;paginate(25)
    -&gt;render();</pre></div>
</div>

<!-- FORM DEMO -->
<div class="demo-section">
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
                ->field('unit', 'Einheit', 'select', [
                    'options' => ['Stk' => 'Stück', 'h' => 'Stunde', 'psch' => 'Pauschal'],
                    'width' => 5
                ])
                ->field('net_price', 'Netto-Preis', 'number', ['step' => '0.01', 'width' => 5])
                ->field('tax_rate', 'MwSt %', 'select', [
                    'options' => ['20' => '20%', '10' => '10%', '0' => '0%'],
                    'width' => 6
                ])
            ->endRow()
            ->field('is_active', 'Aktiv', 'toggle')
            ->submit('Speichern')
            ->render();
        ?>
    </div>

    <div class="demo-code"><pre>&lt;?php
$form = new Form('article_form');
$form-&gt;action('save/process.php')
    -&gt;ajax()
    -&gt;row()
        -&gt;field('name', 'Name', 'text', ['required' =&gt; true, 'width' =&gt; 8])
        -&gt;field('email', 'E-Mail', 'email', ['width' =&gt; 8])
    -&gt;endRow()
    -&gt;field('notes', 'Notizen', 'textarea', ['rows' =&gt; 3])
    -&gt;field('active', 'Aktiv', 'toggle')
    -&gt;submit('Speichern')
    -&gt;render();</pre></div>
</div>

<!-- MODAL DEMO -->
<div class="demo-section">
    <h2>Modal</h2>
    <div class="demo-card">
        <p style="color:#6b7280; margin:0 0 16px; font-size:14px;">
            Modals werden per AJAX geladen und arbeiten nahtlos mit Table und Form zusammen.
        </p>
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <button class="gk-btn gk-btn-primary" onclick="window.GK && GK.modal.open('Neuer Artikel', 'form/f_articles.php', {}, 'small')">
                <span class="material-icons" style="font-size:16px;">add</span>
                Small Modal (Formular)
            </button>
            <button class="gk-btn gk-btn-primary" onclick="window.GK && GK.modal.open('Artikel bearbeiten', 'form/f_articles.php', {}, 'medium')">
                <span class="material-icons" style="font-size:16px;">edit</span>
                Medium Modal (Formular)
            </button>
            <button class="gk-btn" onclick="window.GK && GK.modal.open('Eintrag loeschen', 'form/f_delete.php', {}, 'small')">
                <span class="material-icons" style="font-size:16px;">delete</span>
                Delete Modal
            </button>
        </div>
    </div>

    <div class="demo-code"><pre>&lt;?php
// Tabelle mit Modal-Integration
$table = new Table('articles');
$table-&gt;query($db, "SELECT * FROM articles")
    -&gt;button('edit', ['icon' =&gt; 'edit', 'modal' =&gt; 'edit_form'])
    -&gt;modal('edit_form', 'Bearbeiten', 'form/edit.php', ['size' =&gt; 'medium'])
    -&gt;render();

// Am Ende der Seite:
Modal::container();

// Groessen: small (420px), medium (640px), large (900px)
// Features: AJAX-Loading, Backdrop-Close, ESC-Close, Auto-Refresh</pre></div>
</div>

<!-- TOAST DEMO -->
<div class="demo-section">
    <h2>Toast</h2>
    <div class="demo-card">
        <p style="color:#6b7280; margin:0 0 16px; font-size:14px;">
            Toast-Benachrichtigungen fuer Erfolgs-, Fehler- und Info-Meldungen.
        </p>
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <button class="gk-btn" style="background:#059669;color:#fff;border-color:#059669;" onclick="window.GK && GK.toast.success('Erfolgreich gespeichert!')">
                <span class="material-icons" style="font-size:16px;">check_circle</span> Success
            </button>
            <button class="gk-btn" style="background:#dc2626;color:#fff;border-color:#dc2626;" onclick="window.GK && GK.toast.error('Fehler beim Speichern!')">
                <span class="material-icons" style="font-size:16px;">error</span> Error
            </button>
            <button class="gk-btn" style="background:#d97706;color:#fff;border-color:#d97706;" onclick="window.GK && GK.toast.warning('Achtung: Limit erreicht!')">
                <span class="material-icons" style="font-size:16px;">warning</span> Warning
            </button>
            <button class="gk-btn" style="background:#2563eb;color:#fff;border-color:#2563eb;" onclick="window.GK && GK.toast.info('3 neue Eintraege verfuegbar')">
                <span class="material-icons" style="font-size:16px;">info</span> Info
            </button>
        </div>
    </div>

    <div class="demo-code"><pre>// Toast-Benachrichtigungen
GK.toast.success('Erfolgreich gespeichert!');
GK.toast.error('Fehler beim Speichern!');
GK.toast.warning('Achtung: Limit erreicht!');
GK.toast.info('3 neue Eintraege verfuegbar');

// Optional: Dauer in ms (Standard: 3000)
GK.toast.success('Gespeichert!', 5000);</pre></div>
</div>

<!-- CONFIRM DEMO -->
<div class="demo-section">
    <h2>Confirm</h2>
    <div class="demo-card">
        <p style="color:#6b7280; margin:0 0 16px; font-size:14px;">
            Confirm-Dialoge als saubere Modals. Promise-basiert, mit Danger-Mode fuer destruktive Aktionen.
        </p>
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <button class="gk-btn gk-btn-primary" onclick="window.GK && GK.confirm('Rechnung an den Kunden versenden?', {title:'Rechnung versenden', confirmText:'Versenden'}).then(function(ok){ if(ok) GK.toast.success('Versendet!'); })">
                <span class="material-icons" style="font-size:16px;">send</span> Standard Confirm
            </button>
            <button class="gk-btn gk-btn-danger" onclick="window.GK && GK.confirm('Diesen Eintrag wirklich unwiderruflich loeschen?', {title:'Eintrag loeschen', confirmText:'Loeschen', danger:true}).then(function(ok){ if(ok) GK.toast.success('Geloescht!'); })">
                <span class="material-icons" style="font-size:16px;">delete_forever</span> Danger Confirm
            </button>
        </div>
    </div>

    <div class="demo-code"><pre>// Standard-Bestaetigung
GK.confirm('Rechnung versenden?', {
    title: 'Rechnung versenden',
    confirmText: 'Versenden'
}).then(function(ok) {
    if (ok) GK.toast.success('Versendet!');
});

// Danger-Modus (roter Button)
GK.confirm('Eintrag wirklich loeschen?', {
    title: 'Loeschen',
    confirmText: 'Loeschen',
    danger: true
}).then(function(ok) {
    if (ok) { /* delete logic */ }
});</pre></div>
</div>

<!-- FORMATTER DEMO -->
<div class="demo-section">
    <h2>Formatter</h2>
    <div class="demo-card">
        <?php
        $formatDemo = [
            ['typ' => 'currency', 'input' => 1234.56, 'email' => 'info@ssi.at', 'active' => 1, 'status' => 'bezahlt', 'date' => '2026-02-13', 'percent' => 20],
            ['typ' => 'currency', 'input' => 99.00, 'email' => 'office@panel.at', 'active' => 0, 'status' => 'offen', 'date' => '2026-01-28', 'percent' => 10],
            ['typ' => 'currency', 'input' => 5500.00, 'email' => '', 'active' => 1, 'status' => 'storniert', 'date' => '2025-12-01', 'percent' => 0],
        ];

        $fmtTable = new Table('formatters');
        $fmtTable->setData($formatDemo)
            ->column('input', 'Currency', ['format' => 'currency'])
            ->column('percent', 'Percent', ['format' => 'percent'])
            ->column('date', 'Date', ['format' => 'date'])
            ->column('active', 'Boolean', ['format' => 'boolean'])
            ->column('status', 'Label', ['format' => 'label'])
            ->column('email', 'Email', ['format' => 'email'])
            ->searchable(false)
            ->paginate(false)
            ->render();
        ?>
    </div>
</div>

<!-- STATCARDS DEMO -->
<div class="demo-section">
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

    <div class="demo-code"><pre>&lt;?php
$stats = new StatCards('dashboard');
$stats-&gt;card('Kunden', 248, ['format' =&gt; 'number', 'color' =&gt; 'blue'])
    -&gt;card('Umsatz', 84250.00, ['format' =&gt; 'currency', 'color' =&gt; 'green'])
    -&gt;card('Bestellungen', 64, ['format' =&gt; 'number', 'color' =&gt; 'orange'])
    -&gt;card('Offene Posten', 12480.00, ['format' =&gt; 'currency', 'color' =&gt; 'red'])
    -&gt;render();</pre></div>
</div>

<!-- FILTERCHIPS DEMO -->
<div class="demo-section">
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

    <div class="demo-code"><pre>&lt;?php
$chips = new FilterChips('status-filter', 'status');
$chips-&gt;chip('', 'Alle', ['count' =&gt; 152])
    -&gt;chip('aktiv', 'Aktiv', ['count' =&gt; 89, 'color' =&gt; 'green'])
    -&gt;chip('entwurf', 'Entwurf', ['count' =&gt; 23, 'color' =&gt; 'orange'])
    -&gt;chip('bezahlt', 'Bezahlt', ['count' =&gt; 31, 'color' =&gt; 'blue'])
    -&gt;chip('ueberfaellig', 'Ueberfaellig', ['count' =&gt; 9, 'color' =&gt; 'red'])
    -&gt;render();</pre></div>
</div>

<!-- YEARFILTER DEMO -->
<div class="demo-section">
    <h2>YearFilter</h2>
    <div class="demo-card">
        <?php
        $years = new YearFilter('demo-years', 'year');
        $years->range(2022, 2026)
            ->render();
        ?>
    </div>

    <div class="demo-code"><pre>&lt;?php
$years = new YearFilter('year-nav', 'year');
$years-&gt;range(2022, 2026)
    -&gt;render();

// Oder manuell:
// $years-&gt;years([2026, 2025, 2024])
// Aktuelles Jahr: $years-&gt;current()</pre></div>
</div>

<!-- ZUSAMMENSPIEL DEMO -->
<div class="demo-section">
    <h2>Dashboard - Zusammenspiel</h2>
    <div class="demo-card">
        <?php
        // StatCards
        $dashStats = new StatCards('dash-stats');
        $dashStats->card('Rechnungen', 152, ['format' => 'number', 'color' => 'blue'])
            ->card('Umsatz 2026', 127840.00, ['format' => 'currency', 'color' => 'green'])
            ->card('Offen', 18320.00, ['format' => 'currency', 'color' => 'orange'])
            ->card('Ueberfaellig', 4280.00, ['format' => 'currency', 'color' => 'red'])
            ->render();

        // FilterChips
        $dashChips = new FilterChips('dash-filter', 'dash_status');
        $dashChips->chip('', 'Alle')
            ->chip('bezahlt', 'Bezahlt', ['color' => 'green'])
            ->chip('offen', 'Offen', ['color' => 'orange'])
            ->chip('ueberfaellig', 'Ueberfaellig', ['color' => 'red'])
            ->render();

        // YearFilter
        $dashYears = new YearFilter('dash-years', 'dash_year');
        $dashYears->range(2024, 2026)->render();

        // Table
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
            ->paginate(10)
            ->render();
        ?>
    </div>

    <div class="demo-code"><pre>&lt;?php
// StatCards
$stats = new StatCards('dashboard');
$stats-&gt;card('Rechnungen', 152, ['format' =&gt; 'number', 'color' =&gt; 'blue'])
    -&gt;card('Umsatz', 127840.00, ['format' =&gt; 'currency', 'color' =&gt; 'green'])
    -&gt;render();

// FilterChips
$chips = new FilterChips('filter', 'status');
$chips-&gt;chip('', 'Alle')
    -&gt;chip('bezahlt', 'Bezahlt', ['color' =&gt; 'green'])
    -&gt;chip('offen', 'Offen', ['color' =&gt; 'orange'])
    -&gt;render();

// YearFilter
$years = new YearFilter('years', 'year');
$years-&gt;range(2024, 2026)-&gt;render();

// Table
$table = new Table('invoices');
$table-&gt;query($db, "SELECT * FROM invoices WHERE year = :y", [':y' =&gt; $years-&gt;current()])
    -&gt;column('number', 'Rechnungsnr.', ['sortable' =&gt; true])
    -&gt;column('customer', 'Kunde', ['sortable' =&gt; true])
    -&gt;column('amount', 'Betrag', ['format' =&gt; 'currency'])
    -&gt;column('status', 'Status', ['format' =&gt; 'label'])
    -&gt;render();</pre></div>
</div>

<?php Modal::container(); ?>
<script src="../js/gridkit.js"></script>
</body>
</html>
