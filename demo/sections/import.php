<?php
require_once __DIR__ . '/../_import.php';
?>
<!-- ===== IMPORT (1.119.0) ===== -->
<div class="demo-section" data-section="import">
    <h2>Import <?= since('import') ?></h2>
    <p class="demo-intro">Recipients, customers, articles from a CSV file: <code>GridKit\Import</code>. The fields are defined in PHP; the columns of the file are matched to them by name and alias (case, umlauts and spaces do not count), and every cell is checked by its field's type — e-mail, number and amount in German or English notation, a date as <code>31.12.2026</code>, <code>2026-12-31</code> or <code>31/12/2026</code>, yes or no, one of a list — as well as required fields and values that must be unique in the file. The separator, the quotation mark, the header row and the encoding (UTF-8, or Windows-1252 as German Excel writes it) are found by themselves. Without script it is a form in three steps; <code>js/import.js</code> reads the file in the browser, so the preview is there the moment a file is chosen, dropped or pasted, and follows every change of the mapping. Either way the server reads and checks the file again before it hands over a row.</p>

    <h3 class="demo-h3" id="import-customers">Customers from a CSV file</h3>
    <p class="demo-intro">Choose or drop a file, paste its text — or take the sample: <a href="media/kunden.csv" download>kunden.csv</a>, as German Excel saves it (semicolons, Windows-1252, a decimal comma, a note with a line break in it) with five mistakes in it. Change a column's field and the check follows. <em>Import</em> posts the file and the mapping to <code>api/import.php</code>, which reads and checks it once more and shows what it would store — this demo stores nothing.</p>
    <form method="post" action="api/import.php" class="demo-import-sample">
        <button type="submit" name="sample" value="1" class="gk-btn gk-btn-tonal gk-btn-primary gk-btn-sm" id="demo-import-sample"><span class="material-icons" style="font-size:16px" aria-hidden="true">table_view</span><span>Use the sample file</span></button>
    </form>
        <?php
        showcase(function () {
            demoImporter()                      // the fields: demo/_import.php, below
                ->action('api/import.php')      // the steps after the first are answered there
                ->render();
        });
        ?>
    <pre class="demo-log" id="demo-import-log" aria-label="Event log">Events appear here.</pre>
    <script>
    (function () {
        var log = document.getElementById('demo-import-log');
        ['gk:importread', 'gk:importmap', 'gk:importready', 'gk:importerror'].forEach(function (type) {
            document.addEventListener(type, function (e) {
                if (!e.target.closest('[data-section="import"]')) return;
                var d = e.detail, line = type;
                if (type === 'gk:importread') line += '  ' + (d.filename || 'pasted text') + ', ' + d.meta.encoding + ', separator ' + JSON.stringify(d.meta.delimiter) + ', ' + d.total + ' rows';
                if (type === 'gk:importmap') line += '  ' + d.source + ': ' + JSON.stringify(d.mapping);
                if (type === 'gk:importready') line += '  valid ' + d.result.valid + ', with errors ' + d.result.invalid + (d.result.missing.length ? ', no column for ' + d.result.missing.join(', ') : '');
                if (type === 'gk:importerror') line += '  ' + d.message;
                log.textContent = (line + '\n' + (log.textContent === 'Events appear here.' ? '' : log.textContent)).split('\n').slice(0, 8).join('\n');
            });
        });
        // With script the sample is read here, at once; without, the button posts it to the endpoint.
        document.getElementById('demo-import-sample').addEventListener('click', function (e) {
            if (!window.GK || !GK.import) return;
            e.preventDefault();
            fetch('media/kunden.csv').then(function (r) { return r.blob(); }).then(function (blob) {
                return GK.import.read('customers', blob, 'kunden.csv');
            }).then(function () {
                var first = document.querySelector('#gk-import-customers .gk-import-map select');
                if (first) first.focus();
            });
        });
    })();
    </script>
    <details class="demo-showcase-code">
        <summary><span class="material-icons" aria-hidden="true">code</span><span>The importer (demo/_import.php) and its endpoint (api/import.php)</span></summary>
        <div class="demo-showcase-src">
            <pre><code><?= htmlspecialchars((string) file_get_contents(__DIR__ . '/../_import.php'), ENT_QUOTES, 'UTF-8') ?></code></pre>
            <pre><code><?= htmlspecialchars((string) file_get_contents(__DIR__ . '/../api/import.php'), ENT_QUOTES, 'UTF-8') ?></code></pre>
        </div>
    </details>

    <h3 class="demo-h3" id="import-server">Step two, drawn by the server</h3>
    <p class="demo-intro">The sample read on the server with <code>fromString()</code>: what a browser without JavaScript gets after the upload — how the file was read, a field for every column (<em>Notiz</em> matches none and is ignored), the summary, the first rows with every error under its cell in words, the list of every row that will be left out, and the choice: import the valid rows, check again, or cancel. With <code>storage(false)</code> the file travels in the form, so <code>js/import.js</code> takes this one over too and checks every change at once.</p>
        <?php
        showcase(function () {
            demoImporter('customers-server')
                ->action('api/import.php')
                ->preview(8)
                ->fromString((string) file_get_contents(__DIR__ . '/../media/kunden.csv'), 'kunden.csv')
                ->render();
        });
        ?>

    <h3 class="demo-h3" id="import-result">What the page stores: result()</h3>
    <p class="demo-intro">The rows that passed, keyed by their row in the sheet (the header is row 1), typed — amounts as exact strings, dates as <code>Y-m-d</code>, yes and no as booleans, a country as its code — and the messages of the rows that did not.</p>
        <?php
        showcase(function () {
            $r = demoImporter()->fromString((string) file_get_contents(__DIR__ . '/../media/kunden.csv'), 'kunden.csv')->result();
            echo '<pre class="demo-log">' . htmlspecialchars((string) json_encode([
                'total' => $r['total'], 'valid' => $r['valid'], 'invalid' => $r['invalid'],
                'rows' => array_slice($r['rows'], 0, 2, true),
                'errors' => $r['errors'],
                'meta' => array_intersect_key($r['meta'], array_flip(['encoding', 'delimiter', 'lineEnding', 'header'])),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') . '</pre>';
        });
        ?>
</div>
