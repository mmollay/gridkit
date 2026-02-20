<?php
/**
 * GridKit Skeleton
 * ----------------
 * Kopieren, anpassen, loslegen.
 * Benötigt: autoload.php, css/gridkit.css, css/themes.css, js/gridkit.js
 */

declare(strict_types=1);

require_once __DIR__ . '/autoload.php';

use GridKit\Header;
use GridKit\Sidebar;
use GridKit\Layout;
use GridKit\Theme;
use GridKit\Modal;
use GridKit\Button;
use GridKit\Table;
use GridKit\Form;
use GridKit\StatCards;

// ─── Konfiguration ───────────────────────────────────────────────────────────

$pageTitle    = 'Mein Projekt';
$activeSection = $_GET['section'] ?? 'dashboard';

// Theme: indigo (default) | ocean | forest | sunset | rose | slate
// Mode:  light (default)  | dark
Theme::set('indigo', 'light');

// Layout: header-first (Header volle Breite) | sidebar-first (Sidebar volle Höhe)
Layout::mode('header-first');

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>

    <!-- GridKit Core -->
    <link rel="stylesheet" href="/gridkit/css/gridkit.css">
    <link rel="stylesheet" href="/gridkit/css/themes.css">

    <!-- Material Icons (benötigt für Sidebar, Header, Buttons) -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
</head>
<?= Layout::bodyTag('gk-root') ?>

<!-- ─── Sidebar ──────────────────────────────────────────────────────────── -->
<?php
$sidebar = new Sidebar('main');
$sidebar
    ->brand($pageTitle, 'widgets')            // Name, Icon [, Subtext]
    ->group('Navigation')
    ->item('Dashboard',   '?section=dashboard',   'dashboard',    ['active' => $activeSection === 'dashboard'])
    ->item('Artikel',     '?section=artikel',     'inventory_2',  ['active' => $activeSection === 'artikel'])
    ->item('Kunden',      '?section=kunden',      'people',       ['active' => $activeSection === 'kunden'])
    ->item('Rechnungen',  '?section=rechnungen',  'receipt_long', ['active' => $activeSection === 'rechnungen', 'badge' => 3])
    ->group('System')
    ->item('Einstellungen', '?section=einstellungen', 'settings')
    ->render();
?>

<!-- ─── Wrapper für Sidebar-Layout ──────────────────────────────────────── -->
<div class="gk-with-sidebar">

    <!-- ─── Header ──────────────────────────────────────────────────────── -->
    <?php
    $header = new Header();
    echo $header
        ->title($pageTitle)
        ->sidebarToggle(true)
        ->fixed(true)
        ->action(Button::render('Neu', [
            'variant' => 'filled',
            'color'   => 'primary',
            'icon'    => 'add',
            'size'    => 'sm',
        ]))
        ->action(Theme::switcher())
        ->user('Max Mustermann', [
            'role'   => 'Administrator',
            'menu'   => [
                ['label' => 'Profil',        'href' => '/profil',   'icon' => 'person'],
                ['label' => 'Einstellungen', 'href' => '/settings', 'icon' => 'settings'],
                'divider',
                ['label' => 'Abmelden',      'href' => '/logout',   'icon' => 'logout'],
            ],
        ])
        ->render();
    ?>

    <!-- ─── Content ──────────────────────────────────────────────────────── -->
    <main class="gk-main">

        <?php if ($activeSection === 'dashboard'): ?>
        <!-- Dashboard ─────────────────────────────────────────────────── -->

        <?php
        // StatCards: Kennzahlen-Übersicht
        $stats = new StatCards('dashboard-stats');
        $stats
            ->card('Kunden',    248,       ['format' => 'number',   'color' => 'blue'])
            ->card('Umsatz',    84250.00,  ['format' => 'currency', 'color' => 'green'])
            ->card('Offen',     12480.00,  ['format' => 'currency', 'color' => 'orange'])
            ->card('Überfällig', 3200.00,  ['format' => 'currency', 'color' => 'red'])
            ->render();
        ?>

        <?php elseif ($activeSection === 'artikel'): ?>
        <!-- Artikel-Liste ─────────────────────────────────────────────── -->

        <?php
        // Beispiel mit statischen Daten — ersetzbar durch DB-Query
        $artikel = [
            ['id' => 1, 'nr' => 'ART-001', 'name' => 'Webdesign Paket S', 'preis' => 1200.00, 'status' => 'aktiv'],
            ['id' => 2, 'nr' => 'ART-002', 'name' => 'Hosting Standard',  'preis' =>    9.90, 'status' => 'aktiv'],
            ['id' => 3, 'nr' => 'ART-003', 'name' => 'SEO Beratung',      'preis' =>   95.00, 'status' => 'inaktiv'],
        ];

        $table = new Table('artikel');
        $table
            ->setData($artikel)
            // ->query($db, "SELECT * FROM artikel ORDER BY name")  // alternativ: DB-Query
            ->search(['nr', 'name'])
            ->column('nr',     'Artikelnr.',  ['width' => '120px', 'sortable' => true, 'nowrap' => true])
            ->column('name',   'Bezeichnung', ['sortable' => true])
            ->column('preis',  'Preis',       ['format' => 'currency', 'align' => 'right', 'width' => '100px'])
            ->column('status', 'Status',      ['format' => 'label', 'width' => '100px'])
            ->button('edit',   ['icon' => 'edit',   'class' => 'primary', 'params' => ['id' => 'id']])
            ->button('delete', ['icon' => 'delete', 'class' => 'danger',  'params' => ['id' => 'id']])
            ->newButton('Neuer Artikel', ['icon' => 'add', 'modal' => 'artikel_form'])
            ->modal('artikel_form', 'Artikel bearbeiten', 'forms/artikel.php', ['size' => 'medium'])
            ->paginate(25)
            ->render();
        ?>

        <?php elseif ($activeSection === 'kunden'): ?>
        <!-- Kunden-Liste ──────────────────────────────────────────────── -->
        <p style="color:var(--gk-on-surface-variant)">Hier kommt die Kundenliste.</p>

        <?php elseif ($activeSection === 'rechnungen'): ?>
        <!-- Rechnungen ────────────────────────────────────────────────── -->
        <p style="color:var(--gk-on-surface-variant)">Hier kommt die Rechnungsliste.</p>

        <?php else: ?>
        <!-- Fallback ──────────────────────────────────────────────────── -->
        <p style="color:var(--gk-on-surface-variant)">Seite nicht gefunden.</p>

        <?php endif; ?>

    </main>

    <!-- Modal-Container (einmal am Ende, immer einbinden) -->
    <?php Modal::container(); ?>

</div><!-- /gk-with-sidebar -->

<script src="/gridkit/js/gridkit.js"></script>
</body>
</html>
