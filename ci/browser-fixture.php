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

use GridKit\{Form, Header, Lang, Select, Sidebar, StatCards, Table};

Lang::set('en');

$options = [];
foreach (range(1, 8) as $i) $options["v$i"] = "Option $i";

ob_start();
(new Form('f'))->action('/save')
    ->field('customer', 'Customer', 'ajaxselect', ['url' => '/search'])
    ->field('kind', 'Kind', 'select', ['options' => $options, 'searchable' => true])
    ->field('tags', 'Tags', 'multiselect', ['options' => $options])
    ->field('farbe', 'Colour', 'color')
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
        ['id' => 1, 'name' => 'Anvil',  'price' => 99.0, 'qty' => 2, 'state' => 'active',      'share' => null,  'slug' => "a!b'c(d) e*f", 'leer' => '', 'flag' => false],
        ['id' => 2, 'name' => 'Widget', 'price' => 12.5, 'qty' => 7, 'state' => 'sonderfall', 'share' => 12.5, 'slug' => 'plain', 'leer' => '', 'flag' => true],
        // A label value that is markup: it must arrive as text, not as markup.
        ['id' => 3, 'name' => 'Clamp',  'price' => 5.0,  'qty' => 1, 'state' => '<b>kaputt</b>', 'share' => 0.5, 'slug' => 'clamp', 'leer' => '', 'flag' => 'ja'],
    ])
    ->caption('Price list')
    ->nowrap()
    ->loadTime(38)
    ->footer(['Total', ['text' => '111,50 €', 'align' => 'right', 'bold' => true]])
    ->selectable()
    // A linked value with a second line: both used to be hand-built HTML strings
    // in 62 places across the SSI Panel, each with its own escaping.
    // Das & in der Vorlage wird NICHT kodiert (nur die Platzhalterwerte) — es muss
    // also als &amp; im Attribut stehen, sonst liest der Browser eine Entität.
    ->column('name',  'Product', ['sortable' => true, 'href' => '/p/{slug}?a=1&b=2', 'sub' => 'state'])
    ->column('price', 'Price',   ['format' => 'currency', 'sortable' => true])

    // A status column: 'active' is green from the shared table, 'sonderfall'
    // gets its colour and its text from the column's own labels.
    // A column whose target carries a disguised scheme: no link may come out of
    // it, on either side.
    ->column('qty', 'Qty', ['format' => 'number', 'hideOnMobile' => true, 'muted' => true, 'href' => "java\tscript:x{id}"])
    // Leerer Wert: kein Link. Wahrheitswert: (string) false ist "" — auch kein Link.
    ->column('leer', 'Leer', ['href' => '/x/{id}'])
    ->column('flag', 'Flag', ['href' => '/f/{id}', 'sub' => 'flag'])
    ->column('state', 'State', ['align' => 'center', 'format' => 'label',
        'labels' => ['sonderfall' => ['color' => 'blue', 'text' => 'Sonderfall']]])
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

// Rows that open a side sheet (1.91.0). A second linked cell and a row button
// sit in the same row: a click on either must stay theirs. Selectable, so the
// checkbox column is there too. Descending by name, so a sort reorders.
ob_start();
(new Table('people'))
    ->setData([
        ['id' => 7, 'name' => 'Zoe Adams',   'mail' => 'zoe@example.com',   'plan' => 'Pro',  'site' => 'zoe'],
        ['id' => 8, 'name' => "Mia O'Brien", 'mail' => 'mia@example.com',   'plan' => 'Free', 'site' => 'mia'],
        // Nothing to name the control with: this row must stay a plain row.
        ['id' => 9, 'name' => '',            'mail' => 'nobody@example.com', 'plan' => 'Free', 'site' => 'x'],
    ])
    ->selectable()
    ->rowLink(['sheet' => 'person-sheet'])
    ->column('name', 'Name', ['sortable' => true, 'sub' => 'mail'])
    ->column('plan', 'Plan')
    ->column('site', 'Site', ['href' => '#site-{site}'])
    ->button('note', ['icon' => 'edit'])
    ->render();
$people = ob_get_clean();

// Rows that are links. Fragment targets, so following one keeps the test page.
ob_start();
(new Table('pages'))
    ->setData([
        ['id' => 1, 'title' => 'Beta',  'slug' => "b e'ta"],
        ['id' => 2, 'title' => 'Alpha', 'slug' => 'alpha'],
    ])
    ->rowLink('#page-{slug}')
    ->column('title', 'Title', ['sortable' => true])
    ->column('slug', 'Slug')
    ->render();
$pages = ob_get_clean();

// The side sheet the people table opens: written the way the skill teaches,
// with no role and no name on the close button — GK.sheet fills both in.
$personSheet = '<div class="gk-sheet" id="person-sheet" hidden>'
   . '<div class="gk-sheet-header"><h2 class="gk-sheet-title">Person</h2>'
   . '<button type="button" class="gk-sheet-close">&times;</button></div>'
   . '<div class="gk-sheet-body"><label for="plan-pick">Plan</label>'
   . '<select id="plan-pick"><option>Free</option><option>Pro</option></select>'
   . '<button type="button" id="sheet-ask">Delete account</button></div>'
   . '<div class="gk-sheet-footer"><button type="button" id="sheet-last">Done</button></div>'
   . '</div>';

// A searchable select on its own, for the navigation case in ci/browser.js.
if (isset($argv[1]) && $argv[1] === '--select') {
    echo Select::searchable('country', $options);
    exit;
}

$root = dirname(__DIR__);
$head = '<head><meta charset="utf-8">'
   // Ohne diese Zeile rechnet der Browser mit 980 px Breite, und keine
   // Media-Query für Telefone greift — eine mobile Prüfung wäre wertlos.
   . '<meta name="viewport" content="width=device-width, initial-scale=1">'
   . '<title>GridKit browser fixture</title>'
   . '<style>' . file_get_contents($root . '/css/gridkit.css') . '</style></head>';
$script = '<script>' . file_get_contents($root . '/js/gridkit.js') . '</script>';

// An application page: GridKit's own fixed header with its user menu, the list
// below it and the sheet the list opens. On a wide screen the sheet docks
// beside the list — and the header's menu, which opens over the sheet's top
// corner, has to stay on top of it. ci/browser.js switches the layout and the
// header variant on this one page.
if (isset($argv[1]) && $argv[1] === '--header') {
    echo '<!DOCTYPE html><html lang="en" data-gk-layout="header-first">' . $head . '<body class="gk-root">'
       . Lang::jsConfig()
       . (new Header())->title('People')->fixed()->user('Demo User', ['role' => 'Admin', 'menu' => [
             ['label' => 'Profile', 'href' => '#profile', 'icon' => 'person'],
             ['label' => 'Settings', 'href' => '#settings', 'icon' => 'settings'],
             'divider',
             ['label' => 'Sign out', 'href' => '#sign-out', 'icon' => 'logout'],
         ]])->render()
       . '<main class="gk-body-with-header">' . $people . '</main>'
       . $personSheet
       . $script . '</body></html>';
    exit;
}

// An admin page without a stylesheet of its own (1.92.0): GridKit's sidebar
// and header, the content area, a list whose columns give way, a who cell, the
// sheet with a line under its title, and the small parts. Names are made up.
// ci/browser.js resizes #list-box and #who-box to measure the list by its own
// width, and switches the viewport, the colour mode and reduced motion.
if (isset($argv[1]) && $argv[1] === '--admin') {
    ob_start();
    (new Sidebar('adm'))->brand('Admin', 'dashboard')
        ->group('Manage')
        ->item('Users', '#users', 'people', ['active' => true])
        ->item('Modules', '#modules', 'extension')
        ->render();
    $sidebar = ob_get_clean();

    // Header::title() writes the <h1> only; the line beside it is markup.
    $header = str_replace('<h1>Users</h1>',
        '<h1>Users</h1><span class="gk-header-meta" id="head-meta">5 users · 2 never signed in · 1 locked · 3 invited this week, none of them back yet</span>',
        (new Header())->title('Users')->fixed()->sidebarToggle(true)->user('Demo Admin', ['theme_switcher' => false])->render());

    ob_start();
    (new Table('members'))
        ->setData([
            ['id' => 1, 'name' => 'Jana Novak',  'email' => 'jana.novak@example.org',  'state' => '3 failing', 'plan' => 'Pro',  'seen' => 'today 08:12', 'usage' => '12.40 EUR'],
            ['id' => 2, 'name' => 'Tom Weber',   'email' => 'tom.weber@example.org',   'state' => 'fine',      'plan' => 'Free', 'seen' => 'yesterday',   'usage' => '0.80 EUR'],
            ['id' => 3, 'name' => 'Ada Lindqvist', 'email' => 'ada.lindqvist@example.org', 'state' => 'fine',  'plan' => 'Team', 'seen' => 'never',       'usage' => '0.00 EUR'],
        ])
        ->caption('Members')
        ->size('list')
        ->rowLink(['sheet' => 'member-sheet'])
        ->column('name',  'User',      ['sub' => 'email'])
        ->column('state', 'State',     ['priority' => 2])
        ->column('plan',  'Plan',      ['priority' => 3])
        ->column('seen',  'Last seen', ['priority' => 4])
        ->column('usage', 'Usage',     ['priority' => 5])
        ->toolbar(false)
        ->render();
    $list = ob_get_clean();

    ob_start();
    (new Form('parts'))
        ->field('login', 'Sign-in allowed', 'toggle', ['hint' => 'Takes effect at once.', 'value' => 1])
        ->field('next', 'What now?', 'choice', ['options' => [
            'fix'   => 'Fix it now',
            'later' => ['title' => 'Later', 'hint' => 'Stays on the list until next week.'],
        ]])
        ->render();
    (new StatCards('figures'))->compact()
        ->card('failing', 3, ['color' => 'danger'])
        ->card('open', 12, ['sub' => 'for 3 days'])
        ->render();
    $parts = ob_get_clean();

    echo '<!DOCTYPE html><html lang="en" data-gk-layout="header-first">' . $head . '<body class="gk-root">'
       . Lang::jsConfig()
       . $sidebar
       . '<div class="gk-with-sidebar">' . $header
       . '<main class="gk-main" id="main">'
       . '<div id="list-box">' . $list . '</div>'
       // A who cell whose name and label do not fit side by side at 420px.
       . '<div id="who-box"><div class="gk-table-wrap gk-table-list"><table class="gk-table">'
       . '<thead><tr><th scope="col">User</th><th scope="col" class="gk-col-p2">Plan</th></tr></thead><tbody>'
       . '<tr><td><div class="gk-cell-who">'
       . '<span class="gk-avatar gk-avatar-sm gk-avatar-initials gk-avatar-tone-3" id="tone3" aria-hidden="true">MB</span>'
       . '<div class="gk-cell-who-text"><span id="who-name">Maximilian Bergmann-Hollerbach</span>'
       . '<span class="gk-label gk-label-gray" id="who-label">locked</span>'
       . '<span class="gk-cell-sub gk-cell-sub-wrap" id="who-mail">maximilian.bergmann-hollerbach@example.org</span>'
       . '</div></div></td><td class="gk-col-p2">Pro</td></tr></tbody></table></div></div>'
       . '<section id="parts-box">' . $parts
       . '<div class="gk-stat-tiles"><button type="button" class="gk-stat-tile" id="tile-btn">'
       . '<span class="gk-stat-tile-value">7</span><span class="gk-stat-tile-label">open</span></button></div>'
       // A definition list: the word comes first in the markup, the figure still on top.
       . '<dl class="gk-stat-tiles" id="tiles-dl"><div class="gk-stat-tile"><dt class="gk-stat-tile-label" id="dl-word">open</dt>'
       . '<dd class="gk-stat-tile-value" id="dl-figure">12</dd></div></dl>'
       . '<div class="gk-field-static" id="static"><span class="gk-field-static-label">Plan</span>'
       . '<span class="gk-field-static-value">everything</span><span class="gk-field-hint">The superuser always has everything.</span></div>'
       . '<div class="gk-message gk-message-warning" id="msg"><span>2 runs are stuck.</span>'
       . '<div class="gk-message-actions"><button type="button" class="gk-btn gk-btn-outlined gk-btn-warning gk-btn-touch" id="msg-btn">Check now</button></div></div>'
       // The same blocks with the hidden attribute: every one has a display of its own.
       . '<div class="gk-message gk-message-error" id="msg-hidden" hidden>Hidden message</div>'
       . '<div class="gk-stat-tiles" id="tiles-hidden" hidden><div class="gk-stat-tile">1</div></div>'
       . '<div class="gk-setting" id="setting-hidden" hidden>x</div>'
       . '<label class="gk-choice" id="choice-hidden" hidden><input type="radio" name="h"><span class="gk-choice-mark">A</span></label>'
       . '<button type="button" class="gk-btn gk-btn-text gk-btn-icon-only gk-btn-sm gk-btn-touch" id="touch-icon" aria-label="Edit"><span aria-hidden="true">E</span></button>'
       . '<span class="gk-show-mobile" id="only-phone">phone only</span>'
       . '<span class="gk-dot gk-dot-primary gk-dot-pulse" id="dot-pulse" aria-hidden="true"></span> running'
       . '<span class="gk-dot gk-dot-outline" id="dot-ring" aria-hidden="true"></span> never'
       . '<svg width="100" height="10" aria-hidden="true"><rect id="bar1" class="gk-series-fill-1" width="50" height="10"/>'
       . '<rect id="bar2" class="gk-series-stroke-2" fill="none" x="50" width="50" height="10"/></svg>'
       . '<span class="gk-swatch gk-swatch-3" id="sw3" aria-hidden="true"></span> third'
       . '</section>'
       . '</main></div>'
       . '<div class="gk-sheet" id="member-sheet" hidden><div class="gk-sheet-header">'
       . '<h2 class="gk-sheet-title">Jana Novak</h2>'
       . '<p class="gk-sheet-meta" id="sheet-meta"><a href="mailto:jana.novak@example.org">jana.novak@example.org</a> · last seen today</p>'
       . '<button type="button" class="gk-sheet-close">&times;</button></div>'
       . '<div class="gk-sheet-body"><p>Record</p></div></div>'
       . $script . '</body></html>';
    exit;
}

echo '<!DOCTYPE html><html lang="en">' . $head . '<body class="gk-root">'
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
   . $people
   . $pages
   . $personSheet
   // A second sheet, for "one at a time".
   . '<div class="gk-sheet" id="other-sheet" hidden><div class="gk-sheet-header">'
   . '<h2 class="gk-sheet-title">Other</h2><button type="button" class="gk-sheet-close">&times;</button></div>'
   . '<div class="gk-sheet-body"><button type="button" id="other-btn">x</button></div></div>'
   // One row standing for many, in card mode: the folded rows must stay folded
   // on a phone, where card mode sets display:block on every tbody and tr.
   . '<div class="gk-table-wrap gk-table-mobile-card"><table class="gk-table">'
   . '<thead><tr><th scope="col">Name</th><th scope="col">Plan</th></tr></thead>'
   . '<tbody><tr><td data-label="Name">Ann</td><td data-label="Plan">Pro</td></tr>'
   . '<tr class="gk-table-more"><td colspan="2">'
   . '<button type="button" class="gk-table-more-toggle" aria-expanded="false" aria-controls="folded">'
   . '<span class="gk-table-more-name">3 chart profiles</span>'
   . '<span class="gk-cell-sub">Created from charts; they cannot sign in.</span></button></td></tr></tbody>'
   . '<tbody id="folded" hidden>'
   . '<tr><td data-label="Name">Profile 1</td><td data-label="Plan">-</td></tr>'
   . '<tr><td data-label="Name">Profile 2</td><td data-label="Plan">-</td></tr>'
   . '<tr><td data-label="Name">Profile 3</td><td data-label="Plan">-</td></tr>'
   . '</tbody></table></div>'
   . '<template id="form">' . $form . '</template>'
   . $script . '</body></html>';
