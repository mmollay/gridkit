<?php
/**
 * The demo's lazy tab panels (GridKit\Tabs, 'url' => …, since 1.111.0).
 *
 * The contract: asked by the script (X-Requested-With: XMLHttpRequest) the endpoint answers the
 * panel's markup and nothing else — no <html>, no layout. Opened directly (the link a page with an
 * older gridkit.js shows in the panel, or a new tab) it may answer a page of its own; here it wraps
 * the same fragment in a bare page. GridKit only fetches and inserts; what a panel holds is the
 * application's.
 *
 *   ?part=figures  key figures and a table: GK.initContent() binds the table inside the panel
 *   ?part=slow     answers after 1.2 seconds, so the skeleton can be seen
 *   ?part=fail     a 500 with a body of its own — the panel shows its failure, never this body
 *   ?part=time     the time of the answer, for a panel with 'reload' => true
 */

declare(strict_types=1);

require_once __DIR__ . '/../../autoload.php';

use GridKit\{Lang, StatCards, Table};

$lang = $_GET['lang'] ?? $_COOKIE['gk_lang'] ?? 'en';
Lang::set(is_string($lang) && in_array($lang, ['en', 'de'], true) ? $lang : 'en');

$part = is_string($_GET['part'] ?? null) ? $_GET['part'] : '';
$ajax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');

ob_start();
switch ($part) {
    case 'figures':
        (new StatCards('tab-figures'))->compact()
            ->card('Open', 12)
            ->card('Overdue', 3, ['color' => 'danger'])
            ->card('Paid this month', 1840.5, ['format' => 'currency'])
            ->render();
        (new Table('tab-invoices'))->toolbar(false)->size('sm')
            ->setData([
                ['no' => 'INV-0184', 'customer' => 'Ecklund & Partner', 'total' => 1240.0],
                ['no' => 'INV-0201', 'customer' => 'Nordlicht Media', 'total' => 890.5],
                ['no' => 'INV-0207', 'customer' => 'Bergmann Logistik', 'total' => 312.0],
            ])
            ->column('no', 'No.', ['sortable' => true])
            ->column('customer', 'Customer', ['sortable' => true])
            ->column('total', 'Amount', ['format' => 'currency', 'align' => 'right', 'sortable' => true])
            ->render();
        echo '<p class="gk-text-muted">Loaded when the tab was first opened. The table sorts: GK.initContent() bound it.</p>';
        break;
    case 'slow':
        usleep(1200000);
        echo '<p>This answer took 1.2 seconds. Meanwhile the panel showed its skeleton and said <code>aria-busy="true"</code>.</p>';
        break;
    case 'fail':
        http_response_code(500);
        echo '<h1>Internal Server Error</h1><p>A stack trace nobody should see in a panel.</p>';
        exit;
    case 'time':
        echo '<p>Fetched at <strong>' . htmlspecialchars(date('H:i:s'), ENT_QUOTES, 'UTF-8') . '</strong>. Choose another tab and come back: <code>\'reload\' => true</code> fetches again.</p>';
        break;
    default:
        http_response_code(404);
        echo '<p>No such panel.</p>';
        exit;
}
$fragment = (string) ob_get_clean();

if ($ajax) {
    echo $fragment;
    exit;
}
echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
    . '<title>Panel</title><link rel="stylesheet" href="../../css/gridkit.css"></head>'
    . '<body class="gk-root"><main class="gk-container" style="padding:24px">' . $fragment
    . '<p><a href="../#tabs">Back to the demo</a></p></main></body></html>';
