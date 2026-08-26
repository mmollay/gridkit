<?php
/**
 * GridKit skeleton
 * ----------------
 * Copy it, change the parts marked below, and you have a working page.
 * Needs: autoload.php, css/gridkit.css, css/themes.css, js/gridkit.js
 *
 * Copied out of the GridKit directory this file used to die on its first line
 * with an uncaught Error, because the require below was relative to itself.
 * It now looks in the four places GridKit is normally installed and, when it
 * finds none of them, says so in a sentence instead of a stack trace.
 */

declare(strict_types=1);

// ─── Finding GridKit ─────────────────────────────────────────────────────────
// Beside this file (a copy inside the checkout), one level up (an app directory
// next to the checkout), a Composer install, or a sibling clone.
$gkRoot = null;
foreach ([
    __DIR__,
    __DIR__ . '/vendor/mmollay/gridkit',
    __DIR__ . '/../gridkit',
    dirname(__DIR__) . '/vendor/mmollay/gridkit',
] as $candidate) {
    if (is_file($candidate . '/autoload.php')) { $gkRoot = $candidate; break; }
}

if ($gkRoot === null) {
    http_response_code(500);
    exit(
        "GridKit was not found.\n\n"
        . "This file needs GridKit's autoload.php. Point the require in "
        . basename(__FILE__) . " at it:\n\n"
        . "    require_once '/path/to/gridkit/autoload.php';\n\n"
        . "and set \$gkAssets below to the URL your browser can reach the same "
        . "directory at, e.g. 'vendor/mmollay/gridkit/'.\n"
    );
}

require_once $gkRoot . '/autoload.php';

// The URL prefix for css/ and js/. Empty means "beside this file", which is
// right when the copy lives inside the checkout. Otherwise it is derived from
// wherever autoload.php turned up, which covers the two common layouts — an
// app directory beside the clone, and a Composer install. Both assume the
// GridKit directory is reachable by the browser; if yours is not (vendor/
// outside the document root is the usual case), copy or symlink its css/ and
// js/ into your public root and set this to that path instead.
$gkAssets = '';
if (realpath($gkRoot) !== realpath(__DIR__)) {
    foreach (['vendor/mmollay/gridkit', '../gridkit'] as $guess) {
        if (realpath(__DIR__ . '/' . $guess) === realpath($gkRoot)) {
            $gkAssets = $guess . '/';
            break;
        }
    }
}


use GridKit\{Button, Form, Header, Lang, Layout, Sidebar, StatCards, Table, Theme};

// ─── The product modal answers itself ────────────────────────────────────────
// The table below opens a modal that fetches a URL. Pointing it at a file that
// does not exist is worse than it sounds: PHP's built-in server — the one the
// README tells you to run — falls back to the repo's index.php for any
// unmatched path, so the modal filled with the GridKit landing page. Answering
// it here keeps the skeleton one file, which is the point of a skeleton.
if (isset($_GET['gk_form'])) {
    Lang::set($_GET['lang'] ?? 'en');
    (new Form('product_form'))
        ->action('?gk_save=1')
        ->field('sku',    'SKU',     'text',     ['required' => true, 'width' => 6])
        ->field('name',   'Product', 'text',     ['required' => true, 'width' => 10])
        ->field('price',  'Price',   'text',     ['width' => 6])
        ->field('status', 'Status',  'select',   ['width' => 6, 'options' => [
            'active'   => 'Active',
            'inactive' => 'Inactive',
        ]])
        ->field('notes',  'Notes',   'textarea', ['width' => 16])
        ->render();
    exit;
}

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
    <link rel="stylesheet" href="<?= Layout::asset($gkAssets . 'css/gridkit.css') ?>">
    <link rel="stylesheet" href="<?= Layout::asset($gkAssets . 'css/themes.css') ?>">

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
            ->modal('product_form', 'Edit product', '?gk_form=1', ['size' => 'medium'])
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


</div><!-- /gk-with-sidebar -->

<script src="<?= Layout::asset($gkAssets . 'js/gridkit.js') ?>"></script>
</body>
</html>
