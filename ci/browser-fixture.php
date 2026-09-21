<?php
/**
 * The page ci/browser.js drives. Prints one self-contained HTML document:
 * stylesheet and script inlined, so it loads from file:// with no server.
 *
 *     php ci/browser-fixture.php > /tmp/fixture.html
 *
 * Real component markup on purpose — the point of the browser run is the seam
 * between what PHP writes and what the script does with it.
 */

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use GridKit\{Form, Lang, Select, Table};

Lang::set('en');

$options = [];
foreach (range(1, 8) as $i) $options["v$i"] = "Option $i";

ob_start();
(new Form('f'))->action('/save')
    ->field('customer', 'Customer', 'ajaxselect', ['url' => '/search'])
    ->field('kind', 'Kind', 'select', ['options' => $options, 'searchable' => true])
    ->field('tags', 'Tags', 'multiselect', ['options' => $options])
    ->submit('Save')
    ->render();
$form = ob_get_clean();

ob_start();
(new Table('prices'))
    ->setData([
        // Descending by price on purpose: a sort that really happens changes the order.
        ['id' => 1, 'name' => 'Anvil',  'price' => 99.0, 'qty' => 2, 'state' => 'ok'],
        ['id' => 2, 'name' => 'Widget', 'price' => 12.5, 'qty' => 7, 'state' => 'ok'],
    ])
    ->caption('Price list')
    ->nowrap()
    ->footer(['Total', ['text' => '111,50 €', 'align' => 'right', 'bold' => true]])
    ->selectable()
    ->column('name',  'Product', ['sortable' => true])
    ->column('price', 'Price',   ['format' => 'currency', 'sortable' => true])
    ->column('qty',   'Qty',     ['format' => 'number', 'hideOnMobile' => true])   // the branch with no sort class
    ->column('state', 'State',   ['align' => 'center'])
    ->render();
$table = ob_get_clean();

// A searchable select on its own, for the navigation case in ci/browser.js.
if (isset($argv[1]) && $argv[1] === '--select') {
    echo Select::searchable('country', $options);
    exit;
}

$root = dirname(__DIR__);
echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>GridKit browser fixture</title>'
   . '<style>' . file_get_contents($root . '/css/gridkit.css') . '</style></head><body class="gk-root">'
   . Lang::jsConfig()
   . '<button id="menu" data-gk-dropdown aria-expanded="false">Menu<div class="gk-dropdown-menu"><a href="#">x</a></div></button>'
   . $table
   // A modal written by hand, the way many pages do it: no role, a bare &times;.
   . '<div class="gk-modal-overlay" id="static" style="display:none"><div class="gk-modal">'
   . '<div class="gk-modal-header"><h3>Rename</h3><button class="gk-modal-close">&times;</button></div>'
   . '<div class="gk-modal-body">…</div></div></div>'
   // The heading in a header of the page's own making, and a close button that says something.
   . '<div class="gk-modal-overlay" id="static-own" style="display:none"><div class="gk-modal">'
   . '<div class="my-head"><h4>Move</h4><button class="gk-btn gk-modal-close">Cancel</button></div></div></div>'
   // No heading at all: it must stay the neutral div it was.
   . '<div class="gk-modal-overlay" id="static-bare" style="display:none"><div class="gk-modal"><p>…</p></div></div>'
   . '<template id="form">' . $form . '</template>'
   . '<script>' . file_get_contents($root . '/js/gridkit.js') . '</script></body></html>';
