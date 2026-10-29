<?php
/**
 * Vorschlag „Reiter“ (Bahn RV, Phase 1, 29.10.2026) — Demoseite und Fotovorlage.
 *
 * Zeigt den heutigen Stand der Reiter neben drei Vorschlägen, damit Martin wählt:
 *   ?v=ist|a|b|c                     welche Gestalt (ist = heute, a = Karteireiter, b = Taschen, c = Kräftiger Strich)
 *   ?seite=thema|texte|landing|fenster  das Thema „Tabs“ der Demo oder eine nachgebaute Panel-Seite
 *   ?mode=light|dark  &theme=indigo|ocean|forest|rose|amber|slate
 * Alle Daten sind erfunden. Die Seite lädt css/vorschlag-reiter.css, das nach der Wahl
 * (Phase 2) in gridkit.css aufgeht; diese Datei wird dann zum Thema „Tabs“ der Demo.
 */

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use GridKit\{Button, Header, Layout, Sidebar, Theme};

$v      = in_array($_GET['v'] ?? '', ['ist', 'a', 'b', 'c'], true) ? (string) $_GET['v'] : 'a';
$seite  = in_array($_GET['seite'] ?? '', ['thema', 'texte', 'landing', 'fenster'], true) ? (string) $_GET['seite'] : 'thema';
$mode   = ($_GET['mode'] ?? '') === 'dark' ? 'dark' : 'light';
$themes = ['indigo', 'ocean', 'forest', 'rose', 'amber', 'slate'];
$theme  = in_array($_GET['theme'] ?? '', $themes, true) ? (string) $_GET['theme'] : 'indigo';
Theme::set($theme, $mode);

$namen = ['ist' => 'Heute', 'a' => 'A · Karteireiter', 'b' => 'B · Taschen', 'c' => 'C · Kräftiger Strich'];
$e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

/** Die Klassen einer Reiterzeile je Gestalt und Ebene. */
$navKlasse = static function (string $v, bool $unter, bool $links): string {
    $gestalt = ['ist' => '', 'a' => 'gk-tab-nav-karte', 'b' => 'gk-tab-nav-tasche', 'c' => 'gk-tab-nav-band'][$v];
    if ($v === 'ist') {
        return 'gk-tab-nav' . ($unter ? ' gk-tab-nav-secondary' : ($links ? ' gk-tab-nav-fill' : ''));
    }
    return 'gk-tab-nav ' . $gestalt . ($unter ? ' gk-tab-nav-secondary' : '');
};
/** Die Inhaltsfläche unter der Hauptzeile: A und B rahmen sie, heute und C nicht. */
$flaeche = static fn (string $v): string => in_array($v, ['a', 'b'], true) ? ' gk-tab-flaeche' : '';

/**
 * Eine Reiterzeile. $reiter: Liste von [schluessel, text, symbol|null, etikett|null, zaehler|null].
 * Links: <nav> mit aria-current; sonst Knöpfe für .gk-tabs (Rollen und Pfeiltasten setzt gridkit.js).
 */
$zeile = static function (array $reiter, string $aktiv, string $klasse, string $label, bool $links, string $v, string $id = '') use ($e): string {
    $h = $links ? '<nav class="' . $klasse . '" aria-label="' . $e($label) . '">' : '<div class="' . $klasse . '" aria-label="' . $e($label) . '">';
    foreach ($reiter as [$k, $text, $symbol, $etikett, $zaehler]) {
        $an = $k === $aktiv;
        $innen = ($symbol !== null ? '<span class="material-icons gk-tab-icon" aria-hidden="true">' . $e($symbol) . '</span>' : '')
            . '<span>' . $e($text) . '</span>';
        if ($etikett !== null) {
            $innen .= '<span class="' . ($v === 'ist' ? 'rv-stand-ist' : 'gk-tab-badge') . '">' . $e($etikett) . '</span>';
        }
        if ($zaehler !== null) {
            $innen .= '<span class="gk-tab-count" aria-label="' . (int) $zaehler . ' Einträge">' . (int) $zaehler . '</span>';
        }
        $h .= $links
            ? '<a class="gk-tab-btn' . ($an ? ' gk-active' : '') . '" href="#"' . ($an ? ' aria-current="page"' : '') . '>' . $innen . '</a>'
            : '<button type="button" class="gk-tab-btn' . ($an ? ' gk-active' : '') . '" data-tab="' . $e($id . $k) . '">' . $innen . '</button>';
    }
    return $h . ($links ? '</nav>' : '</div>');
};

$link = static function (array $over) use ($v, $seite, $mode, $theme): string {
    return '?' . http_build_query(array_merge(['v' => $v, 'seite' => $seite, 'mode' => $mode, 'theme' => $theme], $over));
};

$titel = ['thema' => 'Reiter (Tabs)', 'texte' => 'Texte der Marke', 'landing' => 'Survival-Wochenende im Wald', 'fenster' => 'Webseiten'][$seite];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $e($titel . ' — ' . $namen[$v]) ?></title>
    <link rel="stylesheet" href="<?= Layout::asset('../css/gridkit.css') ?>">
    <link rel="stylesheet" href="<?= Layout::asset('../css/themes.css') ?>">
    <link rel="stylesheet" href="<?= Layout::asset('../css/vorschlag-reiter.css') ?>">
    <link rel="stylesheet" href="reiter-demo.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
</head>
<?= Layout::bodyTag('gk-root') ?>
<?php
(new Sidebar('main'))
    ->brand('SSI Panel', 'widgets')
    ->group('Vertrieb')
    ->item('Landingpages', $link(['seite' => 'landing']), 'ads_click', ['active' => in_array($seite, ['landing', 'texte'], true)])
    ->item('Webseiten', $link(['seite' => 'fenster']), 'language', ['active' => $seite === 'fenster'])
    ->group('GRIDKit')
    ->item('Thema „Reiter“', $link(['seite' => 'thema']), 'tab', ['active' => $seite === 'thema'])
    ->render();
?>
<div class="gk-with-sidebar">
<?php
$krumen = ['thema' => ['Demo' => '#', 'Reiter (Tabs)'], 'texte' => ['Landingpages' => '#', 'Texte der Marke'],
           'landing' => ['Landingpages' => '#', 'Survival-Wochenende im Wald'], 'fenster' => ['Webseiten']][$seite];
echo (new Header())->breadcrumb($krumen)->sidebarToggle(true)->fixed(true)->user('Martin Mollay')->render();
?>
<main class="gk-main rv-main" id="main">

<?php if ($seite === 'thema'): ?>
    <div class="rv-kopf">
        <h1>Reiter (Tabs)</h1>
        <p>Drei Ebenen mit eigener Gestalt: Seitenreiter &gt; Unterreiter &gt; Segment. Gewählte Gestalt: <strong><?= $e($namen[$v]) ?></strong>.</p>
    </div>
    <nav class="gk-segmented rv-wahl" aria-label="Gestalt">
        <?php foreach ($namen as $k => $n): ?>
            <a class="gk-segmented-item<?= $k === $v ? ' gk-active' : '' ?>" href="<?= $e($link(['v' => $k])) ?>"<?= $k === $v ? ' aria-current="true"' : '' ?>><?= $e($n) ?></a>
        <?php endforeach; ?>
    </nav>

    <section class="rv-thema" aria-labelledby="rv-t1">
        <h2 id="rv-t1">Seitenreiter mit Unterreitern (Links)</h2>
        <p>Jeder Reiter ist eine eigene Seite: <code>&lt;nav&gt;</code> mit Links, die aktuelle trägt <code>aria-current="page"</code>. Die Unterreiter sitzen in der Fläche des Seitenreiters.</p>
        <?= $zeile([['bearbeiten', 'Bearbeiten', 'edit', null, null], ['veroeffentlichen', 'Veröffentlichen', 'rocket_launch', null, null],
                    ['ergebnisse', 'Ergebnisse', 'bar_chart', null, 14], ['mails', 'Mails', 'mail', null, null]],
                   'ergebnisse', $navKlasse($v, false, true), 'Bereiche dieser Landingpage', true, $v) ?>
        <div class="rv-reiter-inhalt<?= $flaeche($v) ?>">
            <?= $zeile([['uebersicht', 'Übersicht', null, null, null], ['besucher', 'Besucher', null, null, null], ['anmeldungen', 'Anmeldungen', null, null, 14]],
                       'uebersicht', $navKlasse($v, true, true), 'Ansichten dieses Bereichs', true, $v) ?>
            <div class="rv-zeile">
                <nav class="gk-segmented" aria-label="Zeitraum">
                    <a class="gk-segmented-item gk-active" href="#" aria-current="true">7 Tage</a>
                    <a class="gk-segmented-item" href="#">30 Tage</a>
                    <a class="gk-segmented-item" href="#">Alles</a>
                </nav>
                <span class="rv-leise">Segment = dritte Ebene: wählt nur, was angezeigt wird.</span>
            </div>
            <p>Inhalt der Ansicht „Übersicht“.</p>
        </div>
    </section>

    <section class="rv-thema" aria-labelledby="rv-t2">
        <h2 id="rv-t2">Abschnittsreiter mit Symbol, Etikett und Zähler (Knöpfe)</h2>
        <p><code>.gk-tabs</code> mit Knöpfen: Rollen, <code>aria-selected</code> und Pfeiltasten setzt gridkit.js. Etikett: <code>.gk-tab-badge</code>, Zähler: <code>.gk-tab-count</code>.</p>
        <div class="gk-tabs">
            <?= $zeile([['mail', 'Bestätigungsmail', 'mail', 'Angepasst', null], ['seite', 'Bestätigungsseite', 'task_alt', null, null],
                        ['zoom', 'Zoom-Link-Mail', 'videocam', null, null], ['erinnerung', 'Erinnerungen', 'notifications_active', null, 3]],
                       'mail', $navKlasse($v, false, false), 'Texte', false, $v, 't2-') ?>
            <?php foreach (['mail' => 'Bestätigungsmail', 'seite' => 'Bestätigungsseite', 'zoom' => 'Zoom-Link-Mail', 'erinnerung' => 'Erinnerungen'] as $k => $t): ?>
                <section class="gk-tab-panel<?= $flaeche($v) ?><?= $k === 'mail' ? ' gk-active' : '' ?>" data-tab="t2-<?= $k ?>"><p>Inhalt „<?= $e($t) ?>“.</p></section>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="rv-thema" aria-labelledby="rv-t3">
        <h2 id="rv-t3">Markup</h2>
        <pre class="demo-code"><code>&lt;nav class="gk-tab-nav <?= $e(['ist' => 'gk-tab-nav-fill', 'a' => 'gk-tab-nav-karte', 'b' => 'gk-tab-nav-tasche', 'c' => 'gk-tab-nav-band'][$v]) ?>" aria-label="Bereiche"&gt;
  &lt;a class="gk-tab-btn" href="…" aria-current="page"&gt;…&lt;span class="gk-tab-count"&gt;14&lt;/span&gt;&lt;/a&gt;
&lt;/nav&gt;
&lt;div class="gk-tab-flaeche"&gt;
  &lt;nav class="gk-tab-nav <?= $e(['ist' => '', 'a' => 'gk-tab-nav-karte ', 'b' => 'gk-tab-nav-tasche ', 'c' => 'gk-tab-nav-band '][$v]) ?>gk-tab-nav-secondary"&gt;…&lt;/nav&gt;
&lt;/div&gt;</code></pre>
    </section>

<?php elseif ($seite === 'texte'): ?>
    <div class="rv-kopf">
        <h1>Texte der Marke</h1>
        <p class="rv-marke"><span class="material-icons" aria-hidden="true">language</span>Marke: <strong>Survival Training</strong> (survivaltraining.at) · <a href="#">Andere Marke wählen</a></p>
    </div>
    <div class="gk-card rv-info">
        <h2>Was steht hier?</h2>
        <p>Wer sich auf einer Seite dieser Marke anmeldet, bekommt von uns der Reihe nach vier Dinge — jedes hat einen Reiter, in dem du den Wortlaut bestimmst.</p>
        <?php if ($v === 'ist'): ?>
            <p><strong>Leer heißt: Standard.</strong> Was grau im Feld steht, gilt, bis du etwas hineinschreibst.</p>
        <?php else: ?>
            <p>Ein leeres Feld zeigt grau unsere Vorgabe; sie gilt, bis du etwas hineinschreibst. Reiter mit eigenem Text tragen das Etikett „Angepasst“.</p>
        <?php endif; ?>
    </div>
    <?php
    $std = $v === 'ist';
    $texteReiter = [['mail', 'Bestätigungsmail', 'mail', $std ? 'Standard' : null, null], ['seite', 'Bestätigungsseite', 'task_alt', $std ? 'Standard' : 'Angepasst', null],
                    ['zoom', 'Zoom-Link-Mail', 'videocam', $std ? 'Standard' : null, null], ['erinnerung', 'Erinnerungen', 'notifications_active', $std ? 'Standard' : null, null]];
    ?>
    <div class="gk-tabs" id="rv-texte">
        <?= $zeile($texteReiter, 'mail', $navKlasse($v, false, false), 'Die Texte, die eine angemeldete Person bekommt', false, $v) ?>
        <?php foreach (['mail' => ['Bestätigungsmail', 'mail'], 'seite' => ['Bestätigungsseite', 'task_alt'], 'zoom' => ['Zoom-Link-Mail', 'videocam'], 'erinnerung' => ['Erinnerungen', 'notifications_active']] as $k => [$t, $s]): ?>
        <section class="gk-tab-panel<?= $flaeche($v) ?><?= $k === 'mail' ? ' gk-active' : '' ?>" data-tab="<?= $k ?>">
            <h2 class="rv-bereich-titel"><span class="material-icons" aria-hidden="true"><?= $s ?></span><?= $e($t) ?></h2>
            <p class="gk-field-hint">Die Mail, die jemand direkt nach der Anmeldung bekommt. Der Bestätigungslink und der Knopf sind fest; der Wortlaut ist deiner.</p>
            <div class="rv-raster">
                <details class="gk-card rv-felder" open>
                    <summary>Texte für „du“ <span class="rv-leise"><?= $std ? '(alles Standard)' : '— noch keine eigenen Texte, es gelten unsere Vorgaben.' ?></span></summary>
                    <div class="rv-felder-inhalt">
                        <h3>Betreff, Überschrift und Hauptsatz</h3>
                        <p class="gk-field-hint">Das liest die Person zuerst: die Betreffzeile im Postfach und den Anfang der Mail.</p>
                        <div class="gk-field">
                            <label for="rv-betreff-<?= $k ?>">Betreff</label>
                            <textarea class="gk-input" id="rv-betreff-<?= $k ?>" rows="2" placeholder="Bitte bestätige deine Anmeldung zu „{titel}“"></textarea>
                            <p class="gk-field-hint">Höchstens 120 Zeichen. Platzhalter: <code>{titel}</code>, <code>{absender}</code>.</p>
                        </div>
                    </div>
                </details>
                <aside class="gk-card rv-vorschau" aria-label="Vorschau">
                    <h3><span class="material-icons" aria-hidden="true">visibility</span>Vorschau der <?= $e($t) ?></h3>
                    <p class="gk-field-hint">So sieht sie mit den Texten aus, die gerade in den Feldern stehen.</p>
                    <div class="gk-segmented" role="group" aria-label="Vorschau für">
                        <button type="button" class="gk-segmented-item" aria-pressed="true">Du</button>
                        <button type="button" class="gk-segmented-item" aria-pressed="false">Sie</button>
                    </div>
                    <div class="rv-mail"><strong>Betreff: Bitte bestätige deine Anmeldung zu „Mein Online-Vortrag“</strong>Hallo Anna, schön, dass du dabei bist …</div>
                </aside>
            </div>
        </section>
        <?php endforeach; ?>
    </div>

<?php elseif ($seite === 'landing'): ?>
    <div class="rv-kopf">
        <h1>Survival-Wochenende im Wald</h1>
        <p>survival.lp.ssi.at · Marke Survival Training</p>
    </div>
    <p class="gk-message gk-message-success rv-zustand" role="status"><span class="material-icons" aria-hidden="true">check_circle</span>Online seit 12.10. · 14 Anmeldungen · letzte vor 2 Stunden</p>
    <?= $zeile([['bearbeiten', 'Bearbeiten', 'edit', null, null], ['veroeffentlichen', 'Veröffentlichen', 'rocket_launch', null, null],
                ['ergebnisse', 'Ergebnisse', 'bar_chart', null, null], ['mails', 'Mails', 'mail', null, null]],
               'ergebnisse', $navKlasse($v, false, true), 'Bereiche dieser Landingpage', true, $v) ?>
    <div class="rv-reiter-inhalt<?= $flaeche($v) ?>">
        <?= $zeile([['statistik', 'Statistik', null, null, null], ['anmeldungen', 'Anmeldungen', null, null, 14], ['abmeldungen', 'Abmeldungen', null, null, 1]],
                   'statistik', $navKlasse($v, true, true), 'Ansichten dieses Bereichs', true, $v) ?>
        <div class="rv-zeile">
            <nav class="gk-segmented" aria-label="Ansicht der Statistik">
                <a class="gk-segmented-item gk-active" href="#" aria-current="true">Übersicht</a>
                <a class="gk-segmented-item" href="#">Besucher</a>
            </nav>
            <nav class="gk-segmented" aria-label="Zeitraum">
                <a class="gk-segmented-item" href="#">7 Tage</a>
                <a class="gk-segmented-item gk-active" href="#" aria-current="true">30 Tage</a>
                <a class="gk-segmented-item" href="#">Alles</a>
            </nav>
        </div>
        <div class="gk-stat-tiles">
            <div class="gk-stat-tile"><span class="gk-stat-tile-value">312</span><span class="gk-stat-tile-label">Besuche</span></div>
            <div class="gk-stat-tile"><span class="gk-stat-tile-value">14</span><span class="gk-stat-tile-label">Anmeldungen</span></div>
            <div class="gk-stat-tile"><span class="gk-stat-tile-value">4,5 %</span><span class="gk-stat-tile-label">Quote</span></div>
            <div class="gk-stat-tile"><span class="gk-stat-tile-value">1</span><span class="gk-stat-tile-label">Abmeldungen</span></div>
        </div>
        <div class="gk-table-wrap rv-tabelle">
            <table class="gk-table gk-table-compact">
                <caption class="gk-sr-only">Besuche nach Quelle</caption>
                <thead><tr><th>Quelle</th><th class="gk-text-right">Besuche</th><th class="gk-text-right">Anmeldungen</th></tr></thead>
                <tbody>
                    <tr><td>Newsletter</td><td class="gk-text-right">141</td><td class="gk-text-right">9</td></tr>
                    <tr><td>Facebook</td><td class="gk-text-right">98</td><td class="gk-text-right">3</td></tr>
                    <tr><td>Direkt</td><td class="gk-text-right">73</td><td class="gk-text-right">2</td></tr>
                </tbody>
            </table>
        </div>
    </div>

<?php else: ?>
    <div class="rv-kopf rv-fenster-hinter">
        <h1>Webseiten</h1>
        <p>Alle Webseiten der Kunden und was die Werkstatt darf.</p>
    </div>
    <div class="gk-table-wrap">
        <table class="gk-table">
            <caption class="gk-sr-only">Webseiten</caption>
            <thead><tr><th>Adresse</th><th>Kunde</th><th>Werkstatt</th></tr></thead>
            <tbody>
                <tr><td>survivaltraining.at</td><td>Martin Mollay</td><td>ja</td></tr>
                <tr><td>paneon.ssi.at</td><td>Albert P.</td><td>ja</td></tr>
                <tr><td>deike.at</td><td>Deike Pfeil</td><td>nein</td></tr>
            </tbody>
        </table>
    </div>
    <div class="gk-modal-overlay">
        <div class="gk-modal gk-modal-medium rv-modal" role="dialog" aria-modal="true" aria-labelledby="rv-fenster-titel">
            <div class="gk-modal-header">
                <h2 class="gk-modal-title" id="rv-fenster-titel">Webseite bearbeiten: survivaltraining.at</h2>
                <button type="button" class="gk-modal-close" aria-label="Schließen"><span class="material-icons" aria-hidden="true">close</span></button>
            </div>
            <div class="gk-modal-body">
                <div class="gk-tabs">
                    <?= $zeile([['zuordnung', 'Zuordnung', $v === 'ist' ? null : 'link', null, null], ['rechte', 'Rechte der Werkstatt', $v === 'ist' ? null : 'build', null, null],
                                ['kontingent', 'Kontingent', $v === 'ist' ? null : 'data_usage', null, null]],
                               'rechte', $navKlasse($v, $v !== 'ist', false), 'Bereiche des Fensters', false, $v, 'f-') ?>
                    <div class="gk-tab-panel" data-tab="f-zuordnung"><p>Kunde und Adresse.</p></div>
                    <div class="gk-tab-panel gk-active" data-tab="f-rechte">
                        <div class="gk-field"><label for="rv-f1">Was darf die Werkstatt?</label>
                            <select class="gk-input" id="rv-f1"><option>Texte und Bilder ändern</option><option>Alles</option></select></div>
                        <div class="gk-field"><label for="rv-f2">Notiz</label><input class="gk-input" id="rv-f2" value="Termine kommen vom Sync, nicht ändern"></div>
                    </div>
                    <div class="gk-tab-panel" data-tab="f-kontingent"><p>Stunden im Monat.</p></div>
                </div>
            </div>
            <div class="gk-modal-footer">
                <?= Button::render('Abbrechen', ['variant' => 'text', 'color' => 'neutral']) ?>
                <?= Button::render('Speichern', ['variant' => 'filled', 'color' => 'primary', 'icon' => 'save']) ?>
            </div>
        </div>
    </div>
<?php endif; ?>

</main>
</div>
<script src="<?= Layout::asset('../js/gridkit.js') ?>"></script>
<script src="<?= Layout::asset('../js/vorschlag-reiter.js') ?>"></script>
</body>
</html>
