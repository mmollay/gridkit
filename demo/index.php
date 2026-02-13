<?php
require_once __DIR__ . '/../autoload.php';
use GridKit\Table;
use GridKit\Form;
use GridKit\Modal;
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GridKit Demo</title>
    <link rel="stylesheet" href="../css/gridkit.css">
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
    <p>Schlankes PHP-Framework für Tabellen &amp; Formulare. Null Abhängigkeiten.</p>
</div>

<div class="demo-section">
    <div class="demo-stats">
        <div class="demo-stat">
            <div class="num">2</div>
            <div class="lbl">Klassen</div>
        </div>
        <div class="demo-stat">
            <div class="num">1</div>
            <div class="lbl">CSS File</div>
        </div>
        <div class="demo-stat">
            <div class="num">847</div>
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
            ->button('edit', ['icon' => 'pencil', 'modal' => 'edit_form', 'params' => ['id' => 'article_id']])
            ->button('delete', ['icon' => 'trash', 'class' => 'danger', 'modal' => 'delete_form', 'params' => ['id' => 'article_id']])
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

<?php Modal::renderContainer(); ?>
<script src="../js/gridkit.js"></script>
</body>
</html>
