<?php
/** @var callable $demoPaletteBuild  the palette of this page, built in index.php before the header */
?>
<!-- ===== COMMAND PALETTE (1.122.0) ===== -->
<div class="demo-section" data-section="palette">
    <h2>Command palette <?= since('palette') ?></h2>
    <p class="demo-intro">Type a few letters, jump to a page, a customer, an invoice, or run a command. <code>GridKit\CommandPalette</code> writes a closed dialog; <code>js/command-palette.js</code> opens it with <kbd>Ctrl</kbd>+<kbd>K</kbd> (<kbd>⌘</kbd>+<kbd>K</kbd> on a Mac), <kbd>/</kbd>, the search button in the header or <code>GK.palette.open()</code>. On this page it knows every section of the sidebar, every heading of the demo, a few commands and the demo's customers and invoices, fetched as you type.</p>

    <h3 class="demo-h3" id="palette-try">Try it</h3>
    <div class="demo-pair">
    <div class="demo-card">
        <p class="demo-intro">Type <kbd>tre</kbd> for the tree, <kbd>dark</kbd> for dark mode, <kbd>mueller</kbd> or <kbd>muller</kbd> for <em>Müller &amp; Söhne</em>, <kbd>inv 02</kbd> for invoices. The best match comes first: a name that starts with what you typed, then a word that does, then a word that holds it, then its letters in order. <kbd>↑</kbd> <kbd>↓</kbd>, <kbd>Home</kbd>, <kbd>End</kbd>, <kbd>Page Up</kbd> and <kbd>Page Down</kbd> move, <kbd>Enter</kbd> opens, <kbd>Ctrl</kbd>+<kbd>Enter</kbd> opens a link in a new tab, <kbd>Esc</kbd> closes and gives the focus back. What you chose comes first next time. <kbd>slow</kbd> shows the server thinking, <kbd>fail</kbd> a server that failed.</p>
        <div class="demo-btn-row">
            <?= $demoPalette->trigger(['label' => 'Search the demo']) ?>
            <?= \GridKit\Button::render("GK.palette.open('inv')", ['variant' => 'outlined', 'color' => 'neutral', 'size' => 'sm', 'id' => 'demo-palette-inv']) ?>
        </div>
        <pre class="demo-log" id="demo-palette-log" aria-label="Event log">Events appear here.</pre>
    </div>
    <div class="demo-code"><pre><?= htmlspecialchars("// The page's palette, built before the header (demo/index.php):\n" . showcaseSource($demoPaletteBuild)
        . "\n\n// In the header:\n->action(\$palette->trigger())\n\n// Once, anywhere in <body>:\n\$palette->render();", ENT_QUOTES, 'UTF-8') ?></pre></div>
    </div>

    <h3 class="demo-h3" id="palette-records">Records from the server</h3>
    <p class="demo-intro"><code>search('api/palette.php')</code>: after two letters and a quarter of a second of quiet the palette asks <code>GET api/palette.php?q=…</code> — on the page's own origin, never another — and drops an answer a newer question has overtaken. The answer is the shape <code>GK.search</code> reads, so an endpoint written for it works unchanged. Every label is put in as text.</p>
    <details class="demo-showcase-code">
        <summary><span class="material-icons" aria-hidden="true">code</span><span>The endpoint: api/palette.php</span></summary>
        <div class="demo-showcase-src">
            <pre><code><?= htmlspecialchars((string) file_get_contents(__DIR__ . '/../api/palette.php'), ENT_QUOTES, 'UTF-8') ?></code></pre>
        </div>
    </details>

    <h3 class="demo-h3" id="palette-script">Entries from a script</h3>
    <p class="demo-intro">The headings of this demo are registered by the script below with <code>GK.palette.register()</code>, asked again each time the palette opens; choosing one fires <code>gk:paletteaction</code>, and the page scrolls to it. The commands of the page answer the same event.</p>
<?php
$demoPaletteJs = <<<'JS'
document.addEventListener('DOMContentLoaded', function () {
    // Every heading of the demo, under the name of its section.
    GK.palette.register({
        group: 'Demo headings',
        items: function () {
            return Array.prototype.map.call(document.querySelectorAll('.demo-section h3'), function (h) {
                var section = h.closest('.demo-section').getAttribute('data-section');
                var link = document.querySelector('.gk-sidebar a[href="#' + section + '"] .gk-sidebar-label');
                return { label: h.textContent, hint: link ? link.textContent : section, icon: 'short_text',
                         action: 'demo-heading', heading: h, section: section };
            }).filter(function (e) { return e.label.trim() !== ''; });
        }
    });

    document.addEventListener('gk:paletteaction', function (e) {
        var action = e.detail.action;
        if (action === 'demo-heading') {
            var h = e.detail.item.data.heading;
            if (location.hash.split('&')[0] !== '#' + e.detail.item.data.section) location.hash = '#' + e.detail.item.data.section;
            setTimeout(function () {
                window.scrollTo({ top: h.getBoundingClientRect().top + window.scrollY - 80 });
                h.setAttribute('tabindex', '-1');
                h.focus({ preventScroll: true });
            }, 30);
        } else if (action === 'demo-dark') {
            GK.theme.toggleMode();
        } else if (action.indexOf('demo-theme:') === 0) {
            GK.theme.set(action.slice(11));
        } else if (action === 'demo-toast') {
            GK.toast.success('Hello from the command palette.');
        }
    });

    // What the page hears, in the log above.
    var log = document.getElementById('demo-palette-log');
    var lines = [];
    ['gk:paletteopen', 'gk:paletteselect', 'gk:paletteaction'].forEach(function (name) {
        document.addEventListener(name, function (e) {
            var d = e.detail, what = d.source || (d.item ? d.item.label + (d.newTab ? ' (new tab)' : '') : '');
            if (d.action) what += ' → ' + d.action;
            lines.unshift(name + '  ' + what);
            log.textContent = lines.slice(0, 6).join('\n');
        });
    });
    document.getElementById('demo-palette-inv').addEventListener('click', function () { GK.palette.open('inv'); });
});
JS;
?>
    <script><?= $demoPaletteJs ?></script>
    <details class="demo-showcase-code">
        <summary><span class="material-icons" aria-hidden="true">code</span><span>The script of this page</span></summary>
        <div class="demo-showcase-src"><pre><code><?= htmlspecialchars($demoPaletteJs, ENT_QUOTES, 'UTF-8') ?></code></pre></div>
    </details>

    <h3 class="demo-h3" id="palette-a11y">What a screen reader and a phone get</h3>
    <p class="demo-intro">A modal dialog that keeps the focus — <kbd>Tab</kbd> goes round inside it — and gives it back when it closes; the field is a combobox, the list a listbox in groups named by their headings, and the entry you are on is <code>aria-activedescendant</code>. Once the list has settled, a live region says how many results there are, or that there are none, or that the server failed. On a phone the palette covers the screen and nothing scrolls sideways; the trigger is its symbol. Without script the trigger is hidden — or, with <code>fallback('/search')</code>, a plain link to a search page.</p>
</div>
