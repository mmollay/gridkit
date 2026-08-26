<?php
/**
 * GridKit skeleton
 * ----------------
 * Copy it, change the parts marked below, and you have a working page.
 * Needs: autoload.php, css/gridkit.css, css/themes.css, js/gridkit.js
 *
 * This file sits in the GridKit directory, so the asset paths below are
 * relative to it. Copy it somewhere else and adjust them.
 */

declare(strict_types=1);

require_once __DIR__ . '/autoload.php';

use GridKit\{Button, Header, Lang, Layout, Modal, Sidebar, StatCards, Table, Theme};

// ─── Configuration ───────────────────────────────────────────────────────────

$pageTitle     = 'My project';
$activeSection = $_GET['section'] ?? 'dashboard';

Lang::set($_GET['lang'] ?? 'en');            // 'en' | 'de'

// Theme: indigo (default) | ocean | forest | rose | amber | slate
// Mode:  light (default)  | dark
Theme::set('indigo', 'light');

// Layout: header-first (header spans the full width) | sidebar-first (sidebar does)
Layout::mode('header-first');

?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(Lang::locale(), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>

    <!-- GridKit -->
    <link rel="stylesheet" href="<?= Layout::asset('css/gridkit.css') ?>">
    <link rel="stylesheet" href="<?= Layout::asset('css/themes.css') ?>">

    <!-- Material Icons — the sidebar, header and buttons use them -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">

    <?= Lang::jsConfig() ?>
</head>
<?= Layout::bodyTag('gk-root') ?>

<!-- ─── Sidebar ──────────────────────────────────────────────────────────── -->
<?php
(new Sidebar('main'))
    ->brand($pageTitle, 'widgets')            // name, icon [, subtext]
    ->group('Navigation')
    ->item('Dashboard', '?section=dashboard', 'dashboard',    ['active' => $activeSection === 'dashboard'])
    ->item('Products',  '?section=products',  'inventory_2',  ['active' => $activeSection === 'products'])
    ->item('Customers', '?section=customers', 'people',       ['active' => $activeSection === 'customers'])
    ->item('Invoices',  '?section=invoices',  'receipt_long', ['active' => $activeSection === 'invoices', 'badge' => 3])
    ->group('System')
    ->item('Settings', '?section=settings', 'settings')
    ->render();
?>

<!--
    The sidebar is position:fixed, so the content beside it needs this wrapper.
    Without it everything renders underneath the sidebar — nothing breaks, it is
    simply covered.
-->
<div class="gk-with-sidebar">

    <!-- ─── Header ──────────────────────────────────────────────────────── -->
    <?= (new Header())
        ->title($pageTitle)
        ->sidebarToggle(true)
        ->fixed(true)
        ->action(Button::render('New', [
            'variant' => 'filled',
            'color'   => 'primary',
            'icon'    => 'add',
            'size'    => 'sm',
        ]))
        ->action(Theme::switcher())
        ->user('Jane Doe', [
            'role' => 'Administrator',
            'menu' => [
                ['label' => 'Profile',  'href' => '/profile',  'icon' => 'person'],
                ['label' => 'Settings', 'href' => '/settings', 'icon' => 'settings'],
                'divider',
                ['label' => 'Sign out', 'href' => '/logout',   'icon' => 'logout'],
            ],
        ])
        ->render() ?>

    <!-- ─── Content ─────────────────────────────────────────────────────── -->
    <main class="gk-main">

        <?php if ($activeSection === 'dashboard'): ?>

        <?php
        (new StatCards('dashboard-stats'))
            ->card('Customers',   248,      ['format' => 'number',   'color' => 'blue'])
            ->card('Revenue',     84250.00, ['format' => 'currency', 'color' => 'green'])
            ->card('Outstanding', 12480.00, ['format' => 'currency', 'color' => 'orange'])
            ->card('Overdue',      3200.00, ['format' => 'currency', 'color' => 'red'])
            ->render();
        ?>

        <?php elseif ($activeSection === 'products'): ?>

        <?php
        // Static rows, so this page runs before you have a database.
        // Three ways to feed a table:
        //   ->setData($rows)          the browser searches and sorts (small lists)
        //   ->rows($page, $total)     you ran the query — PDO, SQLite, an API
        //   ->query($db, $sql)        GridKit writes the SQL (mysqli only)
        $products = [
            ['id' => 1, 'sku' => 'ART-001', 'name' => 'Web design package S', 'price' => 1200.00, 'status' => 'active'],
            ['id' => 2, 'sku' => 'ART-002', 'name' => 'Hosting standard',     'price' =>    9.90, 'status' => 'active'],
            ['id' => 3, 'sku' => 'ART-003', 'name' => 'SEO consulting',       'price' =>   95.00, 'status' => 'inactive'],
        ];

        (new Table('products'))
            ->setData($products)
            ->search(['sku', 'name'])
            ->column('sku',    'SKU',     ['width' => '120px', 'sortable' => true, 'nowrap' => true])
            ->column('name',   'Product', ['sortable' => true])
            ->column('price',  'Price',   ['format' => 'currency', 'align' => 'right', 'width' => '100px'])
            ->column('status', 'Status',  ['format' => 'label', 'width' => '100px'])
            ->button('edit',   ['icon' => 'edit',   'color' => 'primary'])
            ->button('delete', ['icon' => 'delete', 'color' => 'danger', 'confirm' => true])
            ->newButton('New product', ['icon' => 'add', 'modal' => 'product_form'])
            ->modal('product_form', 'Edit product', 'forms/product.php', ['size' => 'medium'])
            ->paginate(25)
            ->render();
        ?>

        <?php elseif ($activeSection === 'customers'): ?>
        <p style="color:var(--gk-on-surface-variant)">The customer list goes here.</p>

        <?php elseif ($activeSection === 'invoices'): ?>
        <p style="color:var(--gk-on-surface-variant)">The invoice list goes here.</p>

        <?php else: ?>
        <p style="color:var(--gk-on-surface-variant)">Page not found.</p>

        <?php endif; ?>

    </main>

    <!-- The modal container. Once per page, at the end. -->
    <?php Modal::container(); ?>

</div><!-- /gk-with-sidebar -->

<script src="<?= Layout::asset('js/gridkit.js') ?>"></script>
</body>
</html>
