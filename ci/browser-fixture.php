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
        // The slug carries an apostrophe (which used to break out of the
        // single-quoted data-gk-params), a space (rawurlencode writes %20 where
        // urlencode writes +) and the five characters the two encoders disagree on.
        ['id' => 1, 'name' => 'Anvil',  'price' => 99.0, 'qty' => 2, 'state' => 'ok', 'share' => null,  'slug' => "a!b'c(d) e*f"],
        ['id' => 2, 'name' => 'Widget', 'price' => 12.5, 'qty' => 7, 'state' => 'ok', 'share' => 12.5, 'slug' => 'plain'],
    ])
    ->caption('Price list')
    ->nowrap()
    ->loadTime(38)
    ->footer(['Total', ['text' => '111,50 €', 'align' => 'right', 'bold' => true]])
    ->selectable()
    ->column('name',  'Product', ['sortable' => true])
    ->column('price', 'Price',   ['format' => 'currency', 'sortable' => true])
    ->column('qty',   'Qty',     ['format' => 'number', 'hideOnMobile' => true])   // the branch with no sort class
    ->column('state', 'State',   ['align' => 'center'])
    ->column('share', 'Share',   ['format' => 'percent', 'decimals' => 1])   // 12.5 and nothing — as the server writes them
    // A row button that is a real link, with the five characters where PHP and
    // JavaScript encode differently, plus one with a question before it.
    ->button('open', ['icon' => 'visibility', 'href' => '/artikel/{slug}'])
    ->button('go', ['icon' => 'edit', 'href' => '/x/{id}', 'confirm' => 'Sure?'])
    // A template carrying a foreign scheme: both renderers must refuse it and
    // fall back to a plain button, before and after a client-side rebuild.
    ->button('evil', ['icon' => 'delete', 'href' => "java\tscript:alert({id})"])
    // Text and colour: the client used to shrink labelled buttons and grey out
    // a 'color' it never read. Both only show on a button like this one.
    ->button('note', ['text' => 'Note', 'color' => 'danger', 'href' => '/note/{id}'])
    // A fragment target: following it really happens, and the test page stays.
    ->button('jump', ['icon' => 'arrow_forward', 'href' => '#ziel-{id}', 'confirm' => 'Jump?'])
    ->render();
$table = ob_get_clean();

// A table with loadTime() and no footer cells: the meta cell on its own, in seconds.
ob_start();
(new Table('timed'))
    ->setData([['id' => 1, 'n' => 'b'], ['id' => 2, 'n' => 'a']])
    ->loadTime(1234)
    ->column('n', 'Name', ['sortable' => true])
    ->render();
$timed = ob_get_clean();

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
   . $timed
   // A modal written by hand, the way many pages do it: no role, a bare &times;.
   . '<div class="gk-modal-overlay" id="static" style="display:none"><div class="gk-modal">'
   . '<div class="gk-modal-header"><h3>Rename</h3><button class="gk-modal-close">&times;</button></div>'
   . '<div class="gk-modal-body">…</div></div></div>'
   // The heading in a header of the page's own making, and a close button that says something.
   . '<div class="gk-modal-overlay" id="static-own" style="display:none"><div class="gk-modal">'
   . '<div class="my-head"><h4>Move</h4><button class="gk-btn gk-modal-close">Cancel</button></div></div></div>'
   // No heading at all: it must stay the neutral div it was.
   . '<div class="gk-modal-overlay" id="static-bare" style="display:none"><div class="gk-modal"><p>…</p></div></div>'
   // A hand-written modal the way the SSI Panel writes them — hidden with an
   // inline style, opened from a button — for GK.modal.show()/hide().
   . '<button id="open-static">Edit</button>'
   . '<div class="gk-modal-overlay" id="static-show" style="display:none"><div class="gk-modal">'
   . '<div class="gk-modal-header"><h3 class="gk-modal-title">Registrar</h3>'
   . '<button class="gk-modal-close">&times;</button></div>'
   . '<div class="gk-modal-body"><input id="reg-name" type="text"><button id="reg-save">Save</button>'
   // A modal inside a modal: its close button must close the INNER one only.
   . '<div class="gk-modal-overlay" id="inner-show" hidden><div class="gk-modal">'
   . '<div class="gk-modal-header"><h3 class="gk-modal-title">Sure?</h3>'
   . '<button class="gk-modal-close" id="inner-close">&times;</button></div>'
   . '<div class="gk-modal-body"><button id="inner-ok">Yes</button></div>'
   . '</div></div>'
   . '</div>'
   . '</div></div>'
   // An overlay a page hides with a class of its own — show() cannot clear that
   // and must refuse rather than trap the focus in something nobody can see.
   . '<style>.seiten-versteck{display:none}</style>'
   . '<div class="gk-modal-overlay seiten-versteck" id="klassen-versteck"><div class="gk-modal">'
   . '<div class="gk-modal-header"><h3 class="gk-modal-title">Hidden</h3></div></div></div>'
   . '<template id="form">' . $form . '</template>'
   . '<script>' . file_get_contents($root . '/js/gridkit.js') . '</script></body></html>';
