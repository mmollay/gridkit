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
<!-- ===== TABS (1.101.0: Karteireiter) ===== -->
<div class="demo-section" data-section="tabs">
    <h2>Tabs <?= since('tabs', 'steps') ?></h2>
    <p class="demo-intro">Karteireiter: the tabs sit framed on their surface, the chosen one grows into it — taller, bold, a 3px accent on top. Ordered with <code>.gk-tab-nav-karte</code> and <code>.gk-tab-flaeche</code>; without them every tab row keeps the <a href="#navigation">previous shape</a>. Levels: page tabs &gt; second row (on a line, also inside a window) &gt; segmented button. On a phone every Karteireiter row is a grid, two columns up to 480px — nothing scrolls, nothing is cut.</p>

    <h3 class="demo-h3">Page tabs with symbol, badge and count</h3>
    <p class="demo-intro">A badge only reports a deviation from the default (“Customised”, “Error”) — never “Default” on every tab. <code>gk-tab-badge-error</code> is the red one: the word stays, because colour alone says nothing. A count is a number of things behind the tab.</p>
    <div class="demo-card">
        <div class="gk-tabs">
            <div class="gk-tab-nav gk-tab-nav-karte" aria-label="Texts of this page">
                <button class="gk-tab-btn gk-active" data-tab="k-anmeldung"><span class="material-icons gk-tab-icon" aria-hidden="true">edit_note</span><span>Sign-up</span></button>
                <button class="gk-tab-btn" data-tab="k-bestaetigung"><span class="material-icons gk-tab-icon" aria-hidden="true">task_alt</span><span>Confirmation page</span><span class="gk-tab-badge">Customised</span></button>
                <button class="gk-tab-btn" data-tab="k-willkommen"><span class="material-icons gk-tab-icon" aria-hidden="true">mail</span><span>Welcome mail</span></button>
                <button class="gk-tab-btn" data-tab="k-erinnerung"><span class="material-icons gk-tab-icon" aria-hidden="true">alarm</span><span>Reminders</span><span class="gk-tab-count">3</span></button>
                <button class="gk-tab-btn" data-tab="k-abmeldung"><span class="material-icons gk-tab-icon" aria-hidden="true">logout</span><span>Unsubscribe</span></button>
                <button class="gk-tab-btn" data-tab="k-recht"><span class="material-icons gk-tab-icon" aria-hidden="true">gavel</span><span>Legal</span><span class="gk-tab-badge gk-tab-badge-error">Error</span></button>
            </div>
            <div class="gk-tab-panel gk-tab-flaeche gk-active" data-tab="k-anmeldung"><p>The sign-up form — our default texts apply.</p></div>
            <div class="gk-tab-panel gk-tab-flaeche" data-tab="k-bestaetigung"><p>Your own text for the confirmation page.</p></div>
            <div class="gk-tab-panel gk-tab-flaeche" data-tab="k-willkommen"><p>The welcome mail.</p></div>
            <div class="gk-tab-panel gk-tab-flaeche" data-tab="k-erinnerung"><p>Three reminders.</p></div>
            <div class="gk-tab-panel gk-tab-flaeche" data-tab="k-abmeldung"><p>The unsubscribe page.</p></div>
            <div class="gk-tab-panel gk-tab-flaeche" data-tab="k-recht"><p>Legal notice and privacy.</p></div>
        </div>
    </div>
    <div class="demo-code"><pre>&lt;div class="gk-tabs"&gt;
    &lt;div class="gk-tab-nav gk-tab-nav-karte"&gt;
        &lt;button class="gk-tab-btn gk-active" data-tab="a"&gt;&lt;span class="material-icons gk-tab-icon" aria-hidden="true"&gt;edit_note&lt;/span&gt;&lt;span&gt;Sign-up&lt;/span&gt;&lt;/button&gt;
        &lt;button class="gk-tab-btn" data-tab="b"&gt;…&lt;span&gt;Confirmation page&lt;/span&gt;&lt;span class="gk-tab-badge"&gt;Customised&lt;/span&gt;&lt;/button&gt;
        &lt;button class="gk-tab-btn" data-tab="c"&gt;…&lt;span&gt;Reminders&lt;/span&gt;&lt;span class="gk-tab-count"&gt;3&lt;/span&gt;&lt;/button&gt;
        &lt;button class="gk-tab-btn" data-tab="d"&gt;…&lt;span&gt;Legal&lt;/span&gt;&lt;span class="gk-tab-badge gk-tab-badge-error"&gt;Error&lt;/span&gt;&lt;/button&gt;
    &lt;/div&gt;
    &lt;div class="gk-tab-panel gk-tab-flaeche gk-active" data-tab="a"&gt;…&lt;/div&gt;
    &lt;div class="gk-tab-panel gk-tab-flaeche" data-tab="b"&gt;…&lt;/div&gt;
&lt;/div&gt;</pre></div>

    <h3 class="demo-h3" id="tabs-seite">A page of tabs: page tabs, surface, second row, card</h3>
    <p class="demo-intro">The shape of an editor page. The page tabs are cards on their surface; the second row inside the surface is a <em>line</em> tab row — words on a thin line, the chosen one with a 3px stroke in the main colour, no boxes, no grey. The levels never look alike. The corners of both are <code>--gk-tab-radius</code> (4px; set it on any ancestor to change it).</p>
    <div class="demo-card">
        <nav class="gk-tab-nav gk-tab-nav-karte" aria-label="Areas of this page">
            <a class="gk-tab-btn" href="#tabs-seite" aria-current="page"><span class="material-icons gk-tab-icon" aria-hidden="true">edit</span><span>Edit</span></a>
            <a class="gk-tab-btn" href="#tabs-seite"><span class="material-icons gk-tab-icon" aria-hidden="true">rocket_launch</span><span>Publish</span></a>
            <a class="gk-tab-btn" href="#tabs-seite"><span class="material-icons gk-tab-icon" aria-hidden="true">bar_chart</span><span>Results</span></a>
            <a class="gk-tab-btn" href="#tabs-seite"><span class="material-icons gk-tab-icon" aria-hidden="true">mail</span><span>Mails</span><span class="gk-tab-count">2</span></a>
        </nav>
        <div class="gk-tab-flaeche">
            <nav class="gk-tab-nav gk-tab-nav-karte gk-tab-nav-secondary" aria-label="Ways to edit">
                <a class="gk-tab-btn" href="#tabs-seite" aria-current="page">Step by step</a>
                <a class="gk-tab-btn" href="#tabs-seite">With AI <span class="gk-tab-badge">New</span></a>
                <a class="gk-tab-btn" href="#tabs-seite">Change in the picture</a>
            </nav>
            <div class="gk-card">
                <div class="gk-card-body">
                    <ol class="gk-steps" aria-label="Way to live">
                        <li class="gk-steps-item gk-steps-current" aria-current="step">Changes</li>
                        <li class="gk-steps-item">Approval</li>
                        <li class="gk-steps-item">Publish</li>
                    </ol>
                    <p>Two steps to go until publishing: the preview is not up to date, and the page changed after it was approved.</p>
                </div>
            </div>
        </div>
    </div>
    <div class="demo-code"><pre>&lt;nav class="gk-tab-nav gk-tab-nav-karte" aria-label="Areas"&gt;…&lt;/nav&gt;
&lt;div class="gk-tab-flaeche"&gt;
    &lt;nav class="gk-tab-nav gk-tab-nav-karte gk-tab-nav-secondary" aria-label="Ways to edit"&gt;…&lt;/nav&gt;
    &lt;div class="gk-card"&gt;…&lt;/div&gt;
&lt;/div&gt;</pre></div>

    <h3 class="demo-h3" id="tabs-schmal">A row that does not fit its box</h3>
    <p class="demo-intro">Six tabs in boxes of 700, 560 and 340px. A row that fits stays the one line above; one that does not becomes the grid of the phone — every tab whole, every cell as tall as its row, the chosen one still grown into the surface. <code>GK.tabsFit</code> measures the row itself, so no window width has to be guessed (it puts <code>.gk-tab-nav-raster</code> on and takes it off). Without the script the row simply stays a line.</p>
    <div class="demo-card">
        <?php $schmal = ['preview' => 'Preview', 'script' => 'Script', 'talk' => 'Conversation', 'material' => 'Material &amp; rights', 'log' => 'Log &amp; costs', 'publish' => 'Publication']; ?>
        <?php foreach ([700, 560, 340] as $breite): ?>
        <div class="demo-tabs-box demo-tabs-box-<?= $breite ?>" id="tabs-schmal-<?= $breite ?>">
            <div class="gk-tabs">
                <div class="gk-tab-nav gk-tab-nav-karte" aria-label="Areas of this short, <?= $breite ?>px">
                    <?php $erster = true; foreach ($schmal as $schluessel => $name): ?>
                    <button type="button" class="gk-tab-btn<?= $erster ? ' gk-active' : '' ?>" data-tab="b<?= $breite ?>-<?= $schluessel ?>"><?= $name ?><?php if ($schluessel === 'publish'): ?><span class="gk-tab-badge gk-tab-badge-error">Error</span><?php endif; ?></button>
                    <?php $erster = false; endforeach; ?>
                </div>
                <?php $erster = true; foreach ($schmal as $schluessel => $name): ?>
                <div class="gk-tab-panel gk-tab-flaeche<?= $erster ? ' gk-active' : '' ?>" data-tab="b<?= $breite ?>-<?= $schluessel ?>"><p><?= $name ?>, <?= $breite ?>px box.</p></div>
                <?php $erster = false; endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <h3 class="demo-h3" id="tabs-seiten">Tabs between pages, with a second row</h3>
    <p class="demo-intro">Links in a <code>&lt;nav&gt;</code>, the current page with <code>aria-current="page"</code>. The second row stands quieter on a line inside the surface; a third level is a segmented button, never a tab row.</p>
    <div class="demo-card">
        <ol class="gk-steps" aria-label="Way to live">
            <li class="gk-steps-item gk-steps-done">Changes<span class="gk-sr-only"> (done)</span></li>
            <li class="gk-steps-item gk-steps-current" aria-current="step">Approval</li>
            <li class="gk-steps-item">Publish</li>
        </ol>
        <nav class="gk-tab-nav gk-tab-nav-karte demo-tabs-oben" aria-label="Areas of this page">
            <a class="gk-tab-btn" href="#tabs-seiten"><span class="material-icons gk-tab-icon" aria-hidden="true">edit</span><span>Edit</span></a>
            <a class="gk-tab-btn" href="#tabs-seiten"><span class="material-icons gk-tab-icon" aria-hidden="true">rocket_launch</span><span>Publish</span></a>
            <a class="gk-tab-btn" href="#tabs-seiten" aria-current="page"><span class="material-icons gk-tab-icon" aria-hidden="true">bar_chart</span><span>Results</span></a>
            <a class="gk-tab-btn" href="#tabs-seiten"><span class="material-icons gk-tab-icon" aria-hidden="true">mail</span><span>Mails</span></a>
        </nav>
        <div class="gk-tab-flaeche">
            <nav class="gk-tab-nav gk-tab-nav-karte gk-tab-nav-secondary" aria-label="Views">
                <a class="gk-tab-btn" href="#tabs-seiten">Sign-ups</a>
                <a class="gk-tab-btn" href="#tabs-seiten" aria-current="page">Statistics</a>
            </nav>
            <div class="gk-segmented" role="group" aria-label="Period">
                <a class="gk-segmented-item" href="#tabs-seiten">Today</a>
                <a class="gk-segmented-item" href="#tabs-seiten" aria-current="true">7 days</a>
                <a class="gk-segmented-item" href="#tabs-seiten">30 days</a>
            </div>
        </div>
    </div>
    <div class="demo-code"><pre>&lt;nav class="gk-tab-nav gk-tab-nav-karte" aria-label="Areas"&gt;
    &lt;a class="gk-tab-btn" href="/results" aria-current="page"&gt;…&lt;/a&gt;
&lt;/nav&gt;
&lt;div class="gk-tab-flaeche"&gt;
    &lt;nav class="gk-tab-nav gk-tab-nav-karte gk-tab-nav-secondary" aria-label="Views"&gt;…&lt;/nav&gt;
    &lt;div class="gk-segmented" role="group" aria-label="Period"&gt;…&lt;/div&gt;
&lt;/div&gt;</pre></div>

    <h3 class="demo-h3">Nested tabs</h3>
    <p class="demo-intro">A <code>.gk-tabs</code> inside a panel of another: a click on an outer tab leaves the inner choice alone (fixed in 1.101.0 — it used to clear every inner tab). Arrow keys move inside one row only.</p>
    <div class="demo-card">
        <div class="gk-tabs">
            <div class="gk-tab-nav gk-tab-nav-karte">
                <button class="gk-tab-btn gk-active" data-tab="n-kunde">Customer</button>
                <button class="gk-tab-btn" data-tab="n-rechnung">Invoices</button>
            </div>
            <div class="gk-tab-panel gk-tab-flaeche gk-active" data-tab="n-kunde">
                <div class="gk-tabs">
                    <div class="gk-tab-nav gk-tab-nav-karte gk-tab-nav-secondary">
                        <button class="gk-tab-btn gk-active" data-tab="n-stamm">Master data</button>
                        <button class="gk-tab-btn" data-tab="n-kontakt">Contacts</button>
                        <button class="gk-tab-btn" data-tab="n-notiz">Notes</button>
                    </div>
                    <div class="gk-tab-panel gk-active" data-tab="n-stamm"><p>Name, address, tax number.</p></div>
                    <div class="gk-tab-panel" data-tab="n-kontakt"><p>Two contacts.</p></div>
                    <div class="gk-tab-panel" data-tab="n-notiz"><p>No notes yet.</p></div>
                </div>
            </div>
            <div class="gk-tab-panel gk-tab-flaeche" data-tab="n-rechnung"><p>Twelve invoices.</p></div>
        </div>
    </div>

    <h3 class="demo-h3">Tabs inside a window</h3>
    <p class="demo-intro">The window is already the surface, so its tabs take the second-row shape and no <code>.gk-tab-flaeche</code>.</p>
    <div class="demo-card">
        <div class="gk-tabs">
            <div class="gk-tab-nav gk-tab-nav-karte gk-tab-nav-secondary">
                <button class="gk-tab-btn gk-active" data-tab="f-allg"><span class="material-icons gk-tab-icon" aria-hidden="true">business</span><span>General</span></button>
                <button class="gk-tab-btn" data-tab="f-bank"><span class="material-icons gk-tab-icon" aria-hidden="true">account_balance</span><span>Bank</span></button>
                <button class="gk-tab-btn" data-tab="f-logo"><span class="material-icons gk-tab-icon" aria-hidden="true">image</span><span>Logo</span></button>
            </div>
            <div class="gk-tab-panel gk-active" data-tab="f-allg"><p>Company name and address.</p></div>
            <div class="gk-tab-panel" data-tab="f-bank"><p>IBAN and BIC.</p></div>
            <div class="gk-tab-panel" data-tab="f-logo"><p>The logo for invoices.</p></div>
        </div>
    </div>

    <h3 class="demo-h3">Light and dark side by side</h3>
    <p class="demo-intro">Roles only: the edge is the outline mixed with 30% text colour (≥ 3:1 in every theme, light and dark).</p>
    <div class="gk-grid gk-grid-2">
        <?php foreach (['light', 'dark'] as $modus): ?>
        <div class="demo-card" data-gk-mode="<?= $modus ?>">
            <div class="gk-tabs">
                <div class="gk-tab-nav gk-tab-nav-karte">
                    <button class="gk-tab-btn gk-active" data-tab="m-<?= $modus ?>-a">Overview</button>
                    <button class="gk-tab-btn" data-tab="m-<?= $modus ?>-b">Details<span class="gk-tab-count">2</span></button>
                </div>
                <div class="gk-tab-panel gk-tab-flaeche gk-active" data-tab="m-<?= $modus ?>-a"><p><?= $modus === 'dark' ? 'Dark' : 'Light' ?> mode.</p></div>
                <div class="gk-tab-panel gk-tab-flaeche" data-tab="m-<?= $modus ?>-b"><p>Two details.</p></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <!-- ===== Reiter-Varianten (1.103.0) ===== -->
    <h3 class="demo-h3" id="tabs-varianten">Variants: style × alignment × option <?= since('tabs') ?></h3>
    <p class="demo-intro">One row, three choices. The <strong>style</strong> is a class on the row (<code>.gk-tab-nav-karte</code>, <code>-linie</code>, <code>-kapsel</code>, <code>-text</code>), the <strong>alignment</strong> another (top is the default; <code>-links</code>, <code>-rechts</code>, <code>-unten</code>, <code>-fest</code>), and any number of <strong>options</strong> (<code>-sm</code>, <code>-lg</code>, <code>-fluid</code>, <code>-icon-oben</code>, <code>-nur-icon</code>, <code>-neutral</code>) join them. One script serves every form: role, selection, arrow keys by axis, Home and End. A row without any of these classes looks as it did before. Links between pages (<code>&lt;nav&gt;</code> with <code>&lt;a&gt;</code> and <code>aria-current="page"</code>) take the same classes and keep their place: they are links, not a switch.</p>

    <h3 class="demo-h3" id="tabs-baukasten">Builder: choose the shape, copy the markup</h3>
    <p class="demo-intro">Six choices, a live preview and the markup it is made from. The preview and the listing are one string, so what you see is what you copy. Every choice is a class; try the arrow keys inside the preview.</p>
    <div class="demo-card">
        <form class="demo-baukasten-felder" id="tabs-baukasten-form" aria-label="Tab builder">
            <div class="gk-field">
                <label class="gk-label-text" for="bk-stil">Style</label>
                <select class="gk-input" id="bk-stil" name="stil">
                    <option value="karte">Card (Karteireiter)</option>
                    <option value="linie" selected>Line</option>
                    <option value="kapsel">Pill</option>
                    <option value="text">Text</option>
                </select>
            </div>
            <div class="gk-field">
                <label class="gk-label-text" for="bk-lage">Alignment</label>
                <select class="gk-input" id="bk-lage" name="lage">
                    <option value="oben" selected>Top</option>
                    <option value="links">Left</option>
                    <option value="rechts">Right</option>
                    <option value="unten">Bottom</option>
                    <option value="fest">Fixed bar (bottom edge)</option>
                </select>
            </div>
            <div class="gk-field">
                <label class="gk-label-text" for="bk-groesse">Size</label>
                <select class="gk-input" id="bk-groesse" name="groesse">
                    <option value="normal" selected>Normal</option>
                    <option value="sm">Small</option>
                    <option value="lg">Large</option>
                </select>
            </div>
            <div class="gk-field">
                <label class="gk-label-text" for="bk-breite">Width</label>
                <select class="gk-input" id="bk-breite" name="breite">
                    <option value="inhalt" selected>Content</option>
                    <option value="fluid">Full width</option>
                </select>
            </div>
            <div class="gk-field">
                <label class="gk-label-text" for="bk-symbol">Symbol</label>
                <select class="gk-input" id="bk-symbol" name="symbol">
                    <option value="ohne" selected>None</option>
                    <option value="neben">Beside the word</option>
                    <option value="oben">Above the word</option>
                    <option value="nur">Symbol only</option>
                </select>
            </div>
            <div class="gk-field">
                <label class="gk-label-text" for="bk-zaehler">Count or label</label>
                <select class="gk-input" id="bk-zaehler" name="zaehler">
                    <option value="ohne" selected>None</option>
                    <option value="zaehler">Count</option>
                    <option value="etikett">Error label</option>
                </select>
            </div>
        </form>
        <div class="demo-baukasten-vorschau" id="tabs-baukasten-vorschau" role="group" aria-label="Live preview"></div>
        <div class="demo-baukasten-kopf">
            <button type="button" class="gk-btn gk-btn-outlined gk-btn-neutral" id="tabs-baukasten-kopieren">Copy markup</button>
            <span class="demo-baukasten-status" id="tabs-baukasten-status" role="status"></span>
        </div>
        <div class="demo-code"><pre><code id="tabs-baukasten-code" tabindex="0" aria-label="Markup of the preview"></code></pre></div>
    </div>

    <h3 class="demo-h3" id="tabs-stile">Styles</h3>
    <p class="demo-intro">The same three tabs in each style. Colours come from tokens on the row (<code>--gk-tab-akzent</code> for the stroke or fill, <code>--gk-tab-akzent-text</code> for the chosen word, <code>--gk-tab-kante</code> for the track): set them on the row itself to change one row. Light and dark are measured, not guessed — the chosen word is at least 4.5:1, the track and focus ring at least 3:1.</p>
    <p class="demo-intro"><strong>Card</strong> — <code>.gk-tab-nav-karte</code> with <code>.gk-tab-flaeche</code> on the panels, as before.</p>
        <?php
        showcase(function () {
            echo <<<'HTML'
            <div class="gk-tabs">
              <div class="gk-tab-nav gk-tab-nav-karte" aria-label="Card style">
                <button class="gk-tab-btn gk-active" data-tab="s-karte-a">Overview</button>
                <button class="gk-tab-btn" data-tab="s-karte-b">Details<span class="gk-tab-count">3</span></button>
                <button class="gk-tab-btn" data-tab="s-karte-c">History</button>
              </div>
              <div class="gk-tab-panel gk-tab-flaeche gk-active" data-tab="s-karte-a"><p>The overview.</p></div>
              <div class="gk-tab-panel gk-tab-flaeche" data-tab="s-karte-b"><p>Three details.</p></div>
              <div class="gk-tab-panel gk-tab-flaeche" data-tab="s-karte-c"><p>The history.</p></div>
            </div>
            HTML;
        });
        ?>
    <p class="demo-intro"><strong>Line</strong> — <code>.gk-tab-nav-linie</code>: words on a thin track, a 3px stroke under the chosen one.</p>
        <?php
        showcase(function () {
            echo <<<'HTML'
            <div class="gk-tabs">
              <div class="gk-tab-nav gk-tab-nav-linie" aria-label="Line style">
                <button class="gk-tab-btn gk-active" data-tab="s-linie-a">Overview</button>
                <button class="gk-tab-btn" data-tab="s-linie-b">Details<span class="gk-tab-count">3</span></button>
                <button class="gk-tab-btn" data-tab="s-linie-c">History</button>
              </div>
              <div class="gk-tab-panel gk-active" data-tab="s-linie-a"><p>The overview.</p></div>
              <div class="gk-tab-panel" data-tab="s-linie-b"><p>Three details.</p></div>
              <div class="gk-tab-panel" data-tab="s-linie-c"><p>The history.</p></div>
            </div>
            HTML;
        });
        ?>
    <p class="demo-intro"><strong>Pill</strong> — <code>.gk-tab-nav-kapsel</code>: the row lies on a quiet track, the chosen tab is filled.</p>
        <?php
        showcase(function () {
            echo <<<'HTML'
            <div class="gk-tabs">
              <div class="gk-tab-nav gk-tab-nav-kapsel" aria-label="Pill style">
                <button class="gk-tab-btn gk-active" data-tab="s-kapsel-a">Overview</button>
                <button class="gk-tab-btn" data-tab="s-kapsel-b">Details<span class="gk-tab-count">3</span></button>
                <button class="gk-tab-btn" data-tab="s-kapsel-c">History</button>
              </div>
              <div class="gk-tab-panel gk-active" data-tab="s-kapsel-a"><p>The overview.</p></div>
              <div class="gk-tab-panel" data-tab="s-kapsel-b"><p>Three details.</p></div>
              <div class="gk-tab-panel" data-tab="s-kapsel-c"><p>The history.</p></div>
            </div>
            HTML;
        });
        ?>
    <p class="demo-intro"><strong>Text</strong> — <code>.gk-tab-nav-text</code>: only words; the chosen one is bold with a thin line.</p>
        <?php
        showcase(function () {
            echo <<<'HTML'
            <div class="gk-tabs">
              <div class="gk-tab-nav gk-tab-nav-text" aria-label="Text style">
                <button class="gk-tab-btn gk-active" data-tab="s-text-a">Overview</button>
                <button class="gk-tab-btn" data-tab="s-text-b">Details<span class="gk-tab-count">3</span></button>
                <button class="gk-tab-btn" data-tab="s-text-c">History</button>
              </div>
              <div class="gk-tab-panel gk-active" data-tab="s-text-a"><p>The overview.</p></div>
              <div class="gk-tab-panel" data-tab="s-text-b"><p>Three details.</p></div>
              <div class="gk-tab-panel" data-tab="s-text-c"><p>The history.</p></div>
            </div>
            HTML;
        });
        ?>
    <p class="demo-intro"><strong>Links between pages</strong> take the same classes. The current page is <code>aria-current="page"</code>; a click leaves the page, so these tabs keep their place from one page to the next.</p>
        <?php
        showcase(function () {
            echo <<<'HTML'
            <nav class="gk-tab-nav gk-tab-nav-linie" aria-label="Pages">
              <a class="gk-tab-btn" href="#tabs-stile" aria-current="page">Overview</a>
              <a class="gk-tab-btn" href="#tabs-stile">Results</a>
              <a class="gk-tab-btn" href="#tabs-stile">Settings</a>
            </nav>
            HTML;
        });
        ?>

    <h3 class="demo-h3" id="tabs-ausrichtung">Alignment</h3>
    <p class="demo-intro">Top is the default and needs no class. A side row stands next to its panels — from 601px wide; on a phone it becomes the horizontal row, so nothing is squeezed. The arrow keys follow the axis: up and down in a side row, left and right in a horizontal one (<code>aria-orientation</code> says which). The width of the side column is <code>--gk-tab-seitenbreite</code> (14rem at most).</p>
    <p class="demo-intro"><strong>Left</strong> — <code>.gk-tab-nav-links</code>. The row and the panels sit in a <code>.gk-tabs</code>, the row as its direct child.</p>
        <?php
        showcase(function () {
            echo <<<'HTML'
            <div class="gk-tabs">
              <div class="gk-tab-nav gk-tab-nav-linie gk-tab-nav-links" aria-label="Settings, left">
                <button class="gk-tab-btn gk-active" data-tab="a-links-a"><span class="material-icons gk-tab-icon" aria-hidden="true">tune</span><span>General</span></button>
                <button class="gk-tab-btn" data-tab="a-links-b"><span class="material-icons gk-tab-icon" aria-hidden="true">palette</span><span>Look</span></button>
                <button class="gk-tab-btn" data-tab="a-links-c"><span class="material-icons gk-tab-icon" aria-hidden="true">lock</span><span>Privacy</span></button>
              </div>
              <div class="gk-tab-panel gk-active" data-tab="a-links-a"><p>General settings.</p></div>
              <div class="gk-tab-panel" data-tab="a-links-b"><p>Colours and fonts.</p></div>
              <div class="gk-tab-panel" data-tab="a-links-c"><p>Who sees what.</p></div>
            </div>
            HTML;
        });
        ?>
    <p class="demo-intro"><strong>Right</strong> — <code>.gk-tab-nav-rechts</code>, here with the card style: the stroke moves to the outer edge.</p>
        <?php
        showcase(function () {
            echo <<<'HTML'
            <div class="gk-tabs">
              <div class="gk-tab-nav gk-tab-nav-karte gk-tab-nav-rechts" aria-label="Settings, right">
                <button class="gk-tab-btn gk-active" data-tab="a-rechts-a">General</button>
                <button class="gk-tab-btn" data-tab="a-rechts-b">Look</button>
                <button class="gk-tab-btn" data-tab="a-rechts-c">Privacy</button>
              </div>
              <div class="gk-tab-panel gk-tab-flaeche gk-active" data-tab="a-rechts-a"><p>General settings.</p></div>
              <div class="gk-tab-panel gk-tab-flaeche" data-tab="a-rechts-b"><p>Colours and fonts.</p></div>
              <div class="gk-tab-panel gk-tab-flaeche" data-tab="a-rechts-c"><p>Who sees what.</p></div>
            </div>
            HTML;
        });
        ?>
    <p class="demo-intro"><strong>Bottom</strong> — <code>.gk-tab-nav-unten</code>: the row comes <em>after</em> the panels in the markup, so the order of reading matches what you see.</p>
        <?php
        showcase(function () {
            echo <<<'HTML'
            <div class="gk-tabs">
              <div class="gk-tab-panel gk-active" data-tab="a-unten-a"><p>The overview.</p></div>
              <div class="gk-tab-panel" data-tab="a-unten-b"><p>Three details.</p></div>
              <div class="gk-tab-panel" data-tab="a-unten-c"><p>The history.</p></div>
              <div class="gk-tab-nav gk-tab-nav-kapsel gk-tab-nav-unten" aria-label="Sections, bottom">
                <button class="gk-tab-btn gk-active" data-tab="a-unten-a">Overview</button>
                <button class="gk-tab-btn" data-tab="a-unten-b">Details</button>
                <button class="gk-tab-btn" data-tab="a-unten-c">History</button>
              </div>
            </div>
            HTML;
        });
        ?>
    <p class="demo-intro"><strong>Fixed bar</strong> — <code>.gk-tab-nav-unten .gk-tab-nav-fest</code>: sticks to the lower edge of its box, the symbol above the word, the tabs share the width, and the bar keeps clear of a phone's home bar (<code>env(safe-area-inset-bottom)</code>). For up to five tabs. Scroll inside the frame; the frame is only the demo's phone.</p>
        <?php
        showcase(function () {
            echo <<<'HTML'
            <div class="demo-tabs-telefon">
              <div class="gk-tabs">
                <div class="gk-tab-panel gk-active" data-tab="a-fest-a">
                  <p>The bar below stays where it is while this text scrolls.</p>
                  <p>Home: the things you did last. A fixed bar suits the few places an app is made of, not a long list of sections.</p>
                  <p>Each place is a symbol above its word. The chosen one is marked by the stroke and by the colour, never by colour alone.</p>
                  <p>On a phone with a home bar, the bar's own padding lifts the tabs above it.</p>
                </div>
                <div class="gk-tab-panel" data-tab="a-fest-b"><p>Search.</p></div>
                <div class="gk-tab-panel" data-tab="a-fest-c"><p>Inbox.</p></div>
                <div class="gk-tab-nav gk-tab-nav-linie gk-tab-nav-unten gk-tab-nav-fest" aria-label="Main places">
                  <button class="gk-tab-btn gk-active" data-tab="a-fest-a"><span class="material-icons gk-tab-icon" aria-hidden="true">home</span><span>Home</span></button>
                  <button class="gk-tab-btn" data-tab="a-fest-b"><span class="material-icons gk-tab-icon" aria-hidden="true">search</span><span>Search</span></button>
                  <button class="gk-tab-btn" data-tab="a-fest-c"><span class="material-icons gk-tab-icon" aria-hidden="true">inbox</span><span>Inbox</span><span class="gk-tab-count">2</span></button>
                </div>
              </div>
            </div>
            HTML;
        });
        ?>

    <h3 class="demo-h3" id="tabs-optionen">Options</h3>
    <p class="demo-intro">Options join any style and any alignment. All of them read the same tokens, so a row stays in tune in light and dark, and on a phone every tab is at least a finger high (44px).</p>
    <p class="demo-intro"><strong>Size</strong> — <code>.gk-tab-nav-sm</code> and <code>.gk-tab-nav-lg</code> (the card style takes them from 601px up; on a phone every tab is a finger high).</p>
        <?php
        showcase(function () {
            echo <<<'HTML'
            <nav class="gk-tab-nav gk-tab-nav-linie gk-tab-nav-sm" aria-label="Small">
              <a class="gk-tab-btn" href="#tabs-optionen" aria-current="page">Small</a>
              <a class="gk-tab-btn" href="#tabs-optionen">Compact</a>
              <a class="gk-tab-btn" href="#tabs-optionen">Dense</a>
            </nav>
            <nav class="gk-tab-nav gk-tab-nav-linie" aria-label="Normal">
              <a class="gk-tab-btn" href="#tabs-optionen" aria-current="page">Normal</a>
              <a class="gk-tab-btn" href="#tabs-optionen">Regular</a>
              <a class="gk-tab-btn" href="#tabs-optionen">Plain</a>
            </nav>
            <nav class="gk-tab-nav gk-tab-nav-linie gk-tab-nav-lg" aria-label="Large">
              <a class="gk-tab-btn" href="#tabs-optionen" aria-current="page">Large</a>
              <a class="gk-tab-btn" href="#tabs-optionen">Roomy</a>
              <a class="gk-tab-btn" href="#tabs-optionen">Big</a>
            </nav>
            HTML;
        });
        ?>
    <p class="demo-intro"><strong>Full width</strong> — <code>.gk-tab-nav-fluid</code>: the tabs share the row equally.</p>
        <?php
        showcase(function () {
            echo <<<'HTML'
            <nav class="gk-tab-nav gk-tab-nav-kapsel gk-tab-nav-fluid" aria-label="Period">
              <a class="gk-tab-btn" href="#tabs-optionen">Today</a>
              <a class="gk-tab-btn" href="#tabs-optionen" aria-current="page">7 days</a>
              <a class="gk-tab-btn" href="#tabs-optionen">30 days</a>
            </nav>
            HTML;
        });
        ?>
    <p class="demo-intro"><strong>Symbol above the word</strong> — <code>.gk-tab-nav-icon-oben</code>. <strong>Symbol only</strong> — <code>.gk-tab-nav-nur-icon</code>: the word is hidden to the eye and stays for a screen reader; give every tab an <code>aria-label</code> and a <code>title</code> too, so the symbol is also named for the mouse.</p>
        <?php
        showcase(function () {
            echo <<<'HTML'
            <div class="gk-tabs">
              <div class="gk-tab-nav gk-tab-nav-linie gk-tab-nav-icon-oben" aria-label="Symbol above">
                <button class="gk-tab-btn gk-active" data-tab="o-icon-a"><span class="material-icons gk-tab-icon" aria-hidden="true">dashboard</span><span>Overview</span></button>
                <button class="gk-tab-btn" data-tab="o-icon-b"><span class="material-icons gk-tab-icon" aria-hidden="true">list_alt</span><span>Details</span></button>
                <button class="gk-tab-btn" data-tab="o-icon-c"><span class="material-icons gk-tab-icon" aria-hidden="true">history</span><span>History</span></button>
              </div>
              <div class="gk-tab-panel gk-active" data-tab="o-icon-a"><p>The overview.</p></div>
              <div class="gk-tab-panel" data-tab="o-icon-b"><p>The details.</p></div>
              <div class="gk-tab-panel" data-tab="o-icon-c"><p>The history.</p></div>
            </div>
            <div class="gk-tabs">
              <div class="gk-tab-nav gk-tab-nav-kapsel gk-tab-nav-nur-icon" aria-label="Symbol only">
                <button class="gk-tab-btn gk-active" data-tab="o-nur-a" aria-label="Overview" title="Overview"><span class="material-icons gk-tab-icon" aria-hidden="true">dashboard</span><span>Overview</span></button>
                <button class="gk-tab-btn" data-tab="o-nur-b" aria-label="Details" title="Details"><span class="material-icons gk-tab-icon" aria-hidden="true">list_alt</span><span>Details</span></button>
                <button class="gk-tab-btn" data-tab="o-nur-c" aria-label="History" title="History"><span class="material-icons gk-tab-icon" aria-hidden="true">history</span><span>History</span></button>
              </div>
              <div class="gk-tab-panel gk-active" data-tab="o-nur-a"><p>The overview.</p></div>
              <div class="gk-tab-panel" data-tab="o-nur-b"><p>The details.</p></div>
              <div class="gk-tab-panel" data-tab="o-nur-c"><p>The history.</p></div>
            </div>
            HTML;
        });
        ?>
    <p class="demo-intro"><strong>Count and label</strong> — <code>.gk-tab-count</code> is a number of things behind the tab; <code>.gk-tab-badge</code> reports a deviation and <code>.gk-tab-badge-error</code> the red one (the word stays: colour alone says nothing).</p>
        <?php
        showcase(function () {
            echo <<<'HTML'
            <nav class="gk-tab-nav gk-tab-nav-linie" aria-label="With count and label">
              <a class="gk-tab-btn" href="#tabs-optionen" aria-current="page">Inbox<span class="gk-tab-count">12</span></a>
              <a class="gk-tab-btn" href="#tabs-optionen">Drafts<span class="gk-tab-badge">Customised</span></a>
              <a class="gk-tab-btn" href="#tabs-optionen">Legal<span class="gk-tab-badge gk-tab-badge-error">Error</span></a>
            </nav>
            HTML;
        });
        ?>
    <p class="demo-intro"><strong>Neutral</strong> — <code>.gk-tab-nav-neutral</code>: the chosen tab in the text colour instead of the main colour, for a page that already has a strong accent.</p>
        <?php
        showcase(function () {
            echo <<<'HTML'
            <nav class="gk-tab-nav gk-tab-nav-linie gk-tab-nav-neutral" aria-label="Neutral line">
              <a class="gk-tab-btn" href="#tabs-optionen" aria-current="page">Overview</a>
              <a class="gk-tab-btn" href="#tabs-optionen">Results</a>
              <a class="gk-tab-btn" href="#tabs-optionen">Settings</a>
            </nav>
            <nav class="gk-tab-nav gk-tab-nav-kapsel gk-tab-nav-neutral" aria-label="Neutral pill">
              <a class="gk-tab-btn" href="#tabs-optionen" aria-current="page">Overview</a>
              <a class="gk-tab-btn" href="#tabs-optionen">Results</a>
              <a class="gk-tab-btn" href="#tabs-optionen">Settings</a>
            </nav>
            HTML;
        });
        ?>
    <p class="demo-intro"><strong>Disabled</strong> — <code>disabled</code> on a button or <code>aria-disabled="true"</code>: dimmed, no hand cursor, no hover tint, skipped by the arrow keys, and a click does nothing.</p>
        <?php
        showcase(function () {
            echo <<<'HTML'
            <div class="gk-tabs">
              <div class="gk-tab-nav gk-tab-nav-linie" aria-label="With a disabled tab">
                <button class="gk-tab-btn gk-active" data-tab="o-aus-a">Overview</button>
                <button class="gk-tab-btn" data-tab="o-aus-b" disabled>Reports</button>
                <button class="gk-tab-btn" data-tab="o-aus-c" aria-disabled="true">Billing</button>
                <button class="gk-tab-btn" data-tab="o-aus-d">Settings</button>
              </div>
              <div class="gk-tab-panel gk-active" data-tab="o-aus-a"><p>The overview. Press the right arrow: it jumps over the two dimmed tabs.</p></div>
              <div class="gk-tab-panel" data-tab="o-aus-b"><p>Reports.</p></div>
              <div class="gk-tab-panel" data-tab="o-aus-c"><p>Billing.</p></div>
              <div class="gk-tab-panel" data-tab="o-aus-d"><p>The settings.</p></div>
            </div>
            HTML;
        });
        ?>

    <h3 class="demo-h3" id="tabs-kombinationen">Combinations</h3>
    <p class="demo-intro">Rows inside rows. Each <code>.gk-tabs</code> answers only for its own tabs: an outer click leaves the inner choice alone, and the arrow keys stay inside one row. In every example the outer and the inner tabs use the same <code>data-tab</code> keys on purpose — they are never mixed up.</p>
    <p class="demo-intro"><strong>1 · Card outside, line inside</strong> — the page of an editor. The links above are a separate row of pages.</p>
        <?php
        showcase(function () {
            echo <<<'HTML'
            <nav class="gk-tab-nav gk-tab-nav-text" aria-label="Pages">
              <a class="gk-tab-btn" href="#tabs-kombinationen" aria-current="page">Editor</a>
              <a class="gk-tab-btn" href="#tabs-kombinationen">Statistics</a>
            </nav>
            <div class="gk-tabs">
              <div class="gk-tab-nav gk-tab-nav-karte" aria-label="Areas">
                <button class="gk-tab-btn gk-active" data-tab="a">Texts</button>
                <button class="gk-tab-btn" data-tab="b">Mails</button>
              </div>
              <div class="gk-tab-panel gk-tab-flaeche gk-active" data-tab="a">
                <div class="gk-tabs">
                  <div class="gk-tab-nav gk-tab-nav-linie" aria-label="Texts">
                    <button class="gk-tab-btn gk-active" data-tab="a">Sign-up</button>
                    <button class="gk-tab-btn" data-tab="b">Thanks</button>
                  </div>
                  <div class="gk-tab-panel gk-active" data-tab="a"><p>The sign-up text.</p></div>
                  <div class="gk-tab-panel" data-tab="b"><p>The thank-you text.</p></div>
                </div>
              </div>
              <div class="gk-tab-panel gk-tab-flaeche" data-tab="b"><p>The mails.</p></div>
            </div>
            HTML;
        });
        ?>
    <p class="demo-intro"><strong>2 · Side row on the left, line row in its panel</strong> — a vertical row outside, a horizontal one inside.</p>
        <?php
        showcase(function () {
            echo <<<'HTML'
            <div class="gk-tabs">
              <div class="gk-tab-nav gk-tab-nav-linie gk-tab-nav-links" aria-label="Areas">
                <button class="gk-tab-btn gk-active" data-tab="a">Account</button>
                <button class="gk-tab-btn" data-tab="b">Team</button>
                <button class="gk-tab-btn" data-tab="c">Billing</button>
              </div>
              <div class="gk-tab-panel gk-active" data-tab="a">
                <div class="gk-tabs">
                  <div class="gk-tab-nav gk-tab-nav-linie" aria-label="Account">
                    <button class="gk-tab-btn gk-active" data-tab="a">Profile</button>
                    <button class="gk-tab-btn" data-tab="b">Password</button>
                    <button class="gk-tab-btn" data-tab="c">Sessions</button>
                  </div>
                  <div class="gk-tab-panel gk-active" data-tab="a"><p>Your profile.</p></div>
                  <div class="gk-tab-panel" data-tab="b"><p>Your password.</p></div>
                  <div class="gk-tab-panel" data-tab="c"><p>Where you are signed in.</p></div>
                </div>
              </div>
              <div class="gk-tab-panel" data-tab="b"><p>The team.</p></div>
              <div class="gk-tab-panel" data-tab="c"><p>Billing.</p></div>
            </div>
            HTML;
        });
        ?>
    <p class="demo-intro"><strong>3 · Bottom bar around, card row in the page</strong> — an app's places below, a Karteireiter in one of them (in the demo's phone frame, so the bar has an edge to stick to).</p>
        <?php
        showcase(function () {
            echo <<<'HTML'
            <div class="demo-tabs-telefon">
              <div class="gk-tabs">
                <div class="gk-tab-panel gk-active" data-tab="a">
                  <div class="gk-tabs">
                    <div class="gk-tab-nav gk-tab-nav-karte" aria-label="Inside the home place">
                      <button class="gk-tab-btn gk-active" data-tab="a">Today</button>
                      <button class="gk-tab-btn" data-tab="b">Week</button>
                    </div>
                    <div class="gk-tab-panel gk-tab-flaeche gk-active" data-tab="a"><p>Today.</p></div>
                    <div class="gk-tab-panel gk-tab-flaeche" data-tab="b"><p>This week.</p></div>
                  </div>
                </div>
                <div class="gk-tab-panel" data-tab="b"><p>Search.</p></div>
                <div class="gk-tab-nav gk-tab-nav-linie gk-tab-nav-unten gk-tab-nav-fest" aria-label="Places">
                  <button class="gk-tab-btn gk-active" data-tab="a"><span class="material-icons gk-tab-icon" aria-hidden="true">home</span><span>Home</span></button>
                  <button class="gk-tab-btn" data-tab="b"><span class="material-icons gk-tab-icon" aria-hidden="true">search</span><span>Search</span></button>
                </div>
              </div>
            </div>
            HTML;
        });
        ?>
    <p class="demo-intro"><strong>4 · Pill row over a card row</strong> — a switch of areas above, the sections of one area below.</p>
        <?php
        showcase(function () {
            echo <<<'HTML'
            <div class="gk-tabs">
              <div class="gk-tab-nav gk-tab-nav-kapsel" aria-label="Areas">
                <button class="gk-tab-btn gk-active" data-tab="a">Sales</button>
                <button class="gk-tab-btn" data-tab="b">Support</button>
              </div>
              <div class="gk-tab-panel gk-active" data-tab="a">
                <div class="gk-tabs">
                  <div class="gk-tab-nav gk-tab-nav-karte" aria-label="Sales">
                    <button class="gk-tab-btn gk-active" data-tab="a">Offers</button>
                    <button class="gk-tab-btn" data-tab="b">Orders</button>
                    <button class="gk-tab-btn" data-tab="c">Invoices</button>
                  </div>
                  <div class="gk-tab-panel gk-tab-flaeche gk-active" data-tab="a"><p>Open offers.</p></div>
                  <div class="gk-tab-panel gk-tab-flaeche" data-tab="b"><p>Orders.</p></div>
                  <div class="gk-tab-panel gk-tab-flaeche" data-tab="c"><p>Invoices.</p></div>
                </div>
              </div>
              <div class="gk-tab-panel" data-tab="b"><p>Support.</p></div>
            </div>
            HTML;
        });
        ?>

    <script>
    /* The tab builder: the choices become one markup string, the preview is that string
       and the listing shows it — nothing is kept twice. Only fixed words from the lists below
       ever reach the string. */
    (function () {
        var form = document.getElementById('tabs-baukasten-form');
        var vorschau = document.getElementById('tabs-baukasten-vorschau');
        var code = document.getElementById('tabs-baukasten-code');
        var knopf = document.getElementById('tabs-baukasten-kopieren');
        var status = document.getElementById('tabs-baukasten-status');
        if (!form || !vorschau || !code || !knopf || !status) return;

        var NAMEN = ['Overview', 'Details', 'History'];
        var SYMBOLE = ['dashboard', 'list_alt', 'history'];
        var ERLAUBT = {
            stil: ['karte', 'linie', 'kapsel', 'text'],
            lage: ['oben', 'links', 'rechts', 'unten', 'fest'],
            groesse: ['normal', 'sm', 'lg'],
            breite: ['inhalt', 'fluid'],
            symbol: ['ohne', 'neben', 'oben', 'nur'],
            zaehler: ['ohne', 'zaehler', 'etikett']
        };

        function wahl(name) {
            var wert = form.elements[name] ? form.elements[name].value : '';
            return ERLAUBT[name].indexOf(wert) === -1 ? ERLAUBT[name][0] : wert;
        }

        function bauen() {
            var stil = wahl('stil'), lage = wahl('lage'), groesse = wahl('groesse');
            var breite = wahl('breite'), symbol = wahl('symbol'), zaehler = wahl('zaehler');
            var klassen = ['gk-tab-nav', 'gk-tab-nav-' + stil];
            if (lage === 'fest') klassen.push('gk-tab-nav-unten', 'gk-tab-nav-fest');
            else if (lage !== 'oben') klassen.push('gk-tab-nav-' + lage);
            if (groesse !== 'normal') klassen.push('gk-tab-nav-' + groesse);
            if (breite === 'fluid') klassen.push('gk-tab-nav-fluid');
            if (symbol === 'oben') klassen.push('gk-tab-nav-icon-oben');
            if (symbol === 'nur') klassen.push('gk-tab-nav-nur-icon');

            var unten = lage === 'unten' || lage === 'fest';
            var flaeche = stil === 'karte' ? ' gk-tab-flaeche' : '';
            var reihe = ['    <div class="' + klassen.join(' ') + '" aria-label="Sections">'];
            var flaechen = [];
            NAMEN.forEach(function (name, i) {
                var aktiv = i === 0 ? ' gk-active' : '';
                var benannt = symbol === 'nur' ? ' aria-label="' + name + '" title="' + name + '"' : '';
                var ikon = symbol === 'ohne' ? '' : '<span class="material-icons gk-tab-icon" aria-hidden="true">' + SYMBOLE[i] + '</span>';
                var zusatz = '';
                if (zaehler === 'zaehler' && i === 1) zusatz = '<span class="gk-tab-count">3</span>';
                if (zaehler === 'etikett' && i === 2) zusatz = '<span class="gk-tab-badge gk-tab-badge-error">Error</span>';
                reihe.push('        <button class="gk-tab-btn' + aktiv + '" data-tab="t' + i + '"' + benannt + '>' + ikon + '<span>' + name + '</span>' + zusatz + '</button>');
                flaechen.push('    <div class="gk-tab-panel' + flaeche + aktiv + '" data-tab="t' + i + '"><p>' + name + ' — the content of this tab.</p></div>');
            });
            reihe.push('    </div>');
            var teile = unten ? flaechen.concat(reihe) : reihe.concat(flaechen);
            return '<div class="gk-tabs">\n' + teile.join('\n') + '\n</div>';
        }

        function zeigen() {
            var markup = bauen();
            vorschau.innerHTML = markup;
            code.textContent = markup;
            status.textContent = '';
            if (window.GK && GK.initContent) GK.initContent(vorschau);
        }

        function auswaehlen() {
            var auswahl = window.getSelection();
            var bereich = document.createRange();
            bereich.selectNodeContents(code);
            auswahl.removeAllRanges();
            auswahl.addRange(bereich);
        }

        function ersatz() {
            auswaehlen();
            var gelungen = false;
            try { gelungen = document.execCommand('copy'); } catch (e) { gelungen = false; }
            status.textContent = gelungen ? 'Copied.' : 'Could not copy by itself — the markup is selected, press Ctrl+C.';
        }

        knopf.addEventListener('click', function () {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(code.textContent).then(function () { status.textContent = 'Copied.'; }, ersatz);
            } else {
                ersatz();
            }
        });
        form.addEventListener('change', zeigen);
        form.addEventListener('submit', function (e) { e.preventDefault(); });
        zeigen();
    })();
    </script>
</div>

