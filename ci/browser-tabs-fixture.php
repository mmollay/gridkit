<?php
/**
 * The page ci/browser-tabs.js drives: rows of GridKit\Tabs (1.111.0) and of hand-written authored
 * markup — deep links, memory, a parameter, the page's own decision, lazy panels that load, fail,
 * reload and hold widgets, a switch that is refused, the JS API, rows wider than their box, nested
 * rows. One self-contained HTML document, stylesheet and script inlined; the browser check serves it
 * from http://gk.test/ (page.route) so a lazy panel has an origin to load from.
 *
 *     php ci/browser-tabs-fixture.php            > page.html    the page
 *     php ci/browser-tabs-fixture.php old        > page.html    the page with the script BEFORE 1.111.0
 *                                                               (the file cut at its 1.111.0 block, as the
 *                                                               PawBot dashboard keeps it)
 *     php ci/browser-tabs-fixture.php fragments  > parts.json   what the lazy panels and the modal answer
 */

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use GridKit\{Lang, Tabs};

Lang::set('en');
$root = dirname(__DIR__);
$mode = $argv[1] ?? 'page';

// The server read ?ptab=two (Tabs::param): the browser check opens the page with that address.
$_GET['ptab'] = 'two';

$h = static fn(Tabs $t): string => $t->html();
$words = ['Overview', 'Details', 'History', 'Settings', 'Members', 'Billing', 'Invoices', 'Reports', 'Integrations', 'Audit log'];

if ($mode === 'fragments') {
    $inner = (new Tabs('loaded-inner'))->style('linie')->label('Inside the loaded panel')
        ->tab('x', 'Inner X', ['content' => '<p>inner x</p>'])
        ->tab('y', 'Inner Y', ['url' => '/panel/ok'])
        ->html();
    $modal = (new Tabs('in-modal'))->style('karte')->secondary()->label('Tabs in a window')->hash()
        ->tab('m1', 'First', ['content' => '<p class="probe-m1">first in the window</p>'])
        ->tab('m2', 'Second', ['url' => '/panel/ok'])
        ->html();
    echo json_encode([
        'ok' => '<p class="probe-ok">Loaded <b>panel</b></p>',
        'widgets' => '<p class="probe-widgets">With widgets</p>' . $inner,
        'modal' => $modal,
        'page' => "<!doctype html>\n<html><body><p class=\"probe-login\">Please sign in</p></body></html>",
        'error' => '<p class="probe-evil">A server error page that must never be shown</p>',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$out = [];

// 1. Deep link, memory, nesting: an outer row with an inner row in its second panel, both in the address.
$innerRow = (new Tabs('t-inner'))->style('linie')->label('Inner')->hash()
    ->tab('i1', 'Inner one', ['content' => '<p>inner one</p>'])
    ->tab('i2', 'Inner two', ['content' => '<p>inner two</p>'])
    ->html();
$out[] = '<h3>Address and memory</h3>' . $h((new Tabs('t-api'))->style('linie')->label('Address and memory')->hash()->remember()
    ->tab('a', 'Alpha', ['content' => '<p>alpha</p>'])
    ->tab('b', 'Beta', ['content' => '<p>beta</p>' . $innerRow])
    ->tab('c', 'Gamma', ['disabled' => true, 'content' => '<p>gamma</p>'])
    ->tab('d', 'Delta', ['content' => '<p>delta</p>']));

// 2. Memory only (no address): the URL is not touched.
$out[] = '<h3>Memory only</h3>' . $h((new Tabs('t-mem'))->style('kapsel')->label('Memory only')->remember()
    ->tab('one', 'One', ['content' => '<p>one</p>'])
    ->tab('two', 'Two', ['content' => '<p>two</p>'])
    ->tab('three', 'Three', ['content' => '<p>three</p>']));

// 3. The parameter (?ptab=two) and memory: the address wins.
$out[] = '<h3>Parameter</h3>' . $h((new Tabs('t-param'))->style('text')->label('Parameter')->param('ptab')->remember()
    ->tab('one', 'One', ['content' => '<p>one</p>'])
    ->tab('two', 'Two', ['content' => '<p>two</p>'])
    ->tab('three', 'Three', ['content' => '<p>three</p>']));

// 4. The page decides (active()): neither the address nor the memory override it.
$out[] = '<h3>The page decides</h3>' . $h((new Tabs('t-page'))->style('linie')->label('The page decides')->hash()->remember()->active('b')
    ->tab('a', 'A', ['content' => '<p>a</p>'])
    ->tab('b', 'B (with the error)', ['content' => '<p>b</p>'])
    ->tab('c', 'C', ['content' => '<p>c</p>']));

// 5. Lazy panels.
$out[] = '<h3>Lazy panels</h3>' . $h((new Tabs('t-lazy'))->style('karte')->label('Lazy panels')
    ->tab('plain', 'Plain', ['content' => '<p class="probe-plain">plain</p>'])
    ->tab('ok', 'Loads', ['url' => '/panel/ok'])
    ->tab('fail', 'Fails (500)', ['url' => '/panel/error'])
    ->tab('net', 'Network', ['url' => '/panel/net'])
    ->tab('again', 'Every time', ['url' => '/panel/time', 'reload' => true])
    ->tab('widgets', 'Widgets', ['url' => '/panel/widgets'])
    ->tab('page', 'A whole page', ['url' => '/panel/page'])
    ->tab('cross', 'Elsewhere', ['url' => 'http://elsewhere.test/panel/ok']));

// 6. A switch that is refused: unsaved changes.
$out[] = '<h3>Unsaved changes</h3>' . $h((new Tabs('t-cancel'))->style('linie')->label('Unsaved changes')->hash()
    ->tab('edit', 'Edit', ['content' => '<label>Name <input id="probe-dirty" class="gk-input"></label>'])
    ->tab('preview', 'Preview', ['content' => '<p>preview</p>'])
    ->tab('more', 'More', ['content' => '<p>more</p>']));

// 7. Hand-written authored markup (no Tabs), for the JS API.
$out[] = '<h3>Hand-written</h3>
<div class="gk-tabs" id="t-hand">
  <div class="gk-tab-nav gk-tab-nav-linie">
    <button class="gk-tab-btn gk-active" data-tab="a">Inbox</button>
    <button class="gk-tab-btn" data-tab="b">Drafts<span class="gk-tab-count">2</span></button>
    <button class="gk-tab-btn" data-tab="c">Sent</button>
  </div>
  <div class="gk-tab-panel gk-active" data-tab="a"><p>inbox</p></div>
  <div class="gk-tab-panel" data-tab="b"><p>drafts</p></div>
  <div class="gk-tab-panel" data-tab="c"><p>sent</p></div>
</div>
<div class="gk-tabs" id="t-hand-icons">
  <div class="gk-tab-nav gk-tab-nav-kapsel gk-tab-nav-nur-icon">
    <button class="gk-tab-btn gk-active" data-tab="a" aria-label="Overview" title="Overview"><span class="material-icons gk-tab-icon" aria-hidden="true">dashboard</span><span>Overview</span></button>
    <button class="gk-tab-btn" data-tab="b" aria-label="Details" title="Details"><span class="material-icons gk-tab-icon" aria-hidden="true">list_alt</span><span>Details</span></button>
  </div>
  <div class="gk-tab-panel gk-active" data-tab="a"><p>overview</p></div>
  <div class="gk-tab-panel" data-tab="b"><p>details</p></div>
</div>';

// 8. Rows wider than their box: line, pill and text, a row of links, and a card row (its grid stays).
foreach (['linie', 'kapsel', 'text'] as $style) {
    $t = (new Tabs('t-over-' . $style))->style($style)->label("Many tabs, $style");
    foreach ($words as $i => $w) $t->tab('k' . $i, $w, ['content' => "<p>$w</p>"]);
    $out[] = "<h3>Overflow, $style</h3><div class=\"probe-box\">" . $t->html() . '</div>';
}
$links = (new Tabs('t-over-links'))->style('linie')->label('Many pages')->active('k8');
foreach ($words as $i => $w) $links->tab('k' . $i, $w, ['href' => '/page/' . $i]);
$out[] = '<h3>Overflow, links</h3><div class="probe-box">' . $links->html() . '</div>';
$card = (new Tabs('t-over-karte'))->style('karte')->label('Many cards');
foreach ($words as $i => $w) $card->tab('k' . $i, $w, ['content' => "<p>$w</p>"]);
$out[] = '<h3>Overflow, card</h3><div class="probe-box">' . $card->html() . '</div>';
$few = (new Tabs('t-few'))->style('linie')->label('Few tabs')
    ->tab('a', 'One', ['content' => '<p>one</p>'])->tab('b', 'Two', ['content' => '<p>two</p>']);
$out[] = '<h3>A row that fits</h3><div class="probe-box">' . $few->html() . '</div>';

// 9. Injected later and handed to GK.initContent().
$late = (new Tabs('t-late'))->style('linie')->label('Injected')->hash()
    ->tab('a', 'Late A', ['content' => '<p>late a</p>'])
    ->tab('b', 'Late B', ['url' => '/panel/ok'])
    ->html();
$out[] = '<h3>Injected</h3><template id="probe-late">' . $late . '</template><div id="probe-late-box"></div>';

$script = (string) file_get_contents($root . '/js/gridkit.js');
if ($mode === 'old') {
    $cut = strpos($script, '// === 1.111.0 — tabs:');
    if ($cut === false) { fwrite(STDERR, "the 1.111.0 block is not in js/gridkit.js\n"); exit(1); }
    $script = substr($script, 0, $cut);
}

echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
   . '<meta name="viewport" content="width=device-width, initial-scale=1"><title>Tabs</title>'
   . '<style>' . file_get_contents($root . '/css/gridkit.css') . '</style>'
   . '<style>' . file_get_contents($root . '/css/themes.css') . '</style>'
   . '<style>body{background:var(--gk-bg);color:var(--gk-on-surface);margin:0;padding:12px;font-family:var(--gk-font)}'
   . 'h3{font-size:13px;margin:18px 0 6px}.probe-box{width:360px;max-width:100%}'
   . '*,*::before,*::after{transition:none!important;animation:none!important;scroll-behavior:auto!important}</style>'
   . Lang::jsConfig()
   . '</head><body class="gk-root"><main class="gk-container">' . implode("\n", $out) . '</main>'
   . '<script>' . $script . '</script>'
   // What a page does with the events: a log the check reads, and the refusal of a switch with unsaved changes.
   . '<script>window.probeLog = [];
document.addEventListener("gk:tabchange", function (e) { probeLog.push(["change", e.target.id, e.detail.tabsId, e.detail.from, e.detail.to, e.detail.source, !!e.detail.panel, e.cancelable]); });
document.addEventListener("gk:tabshown", function (e) { probeLog.push(["shown", e.target.id, e.detail.from, e.detail.to, e.detail.source]); });
document.addEventListener("gk:tabload", function (e) { probeLog.push(["load", e.target.id, e.detail.key, e.detail.ok, e.detail.status]); });
document.getElementById("t-cancel").addEventListener("gk:tabchange", function (e) {
  if (e.target !== this) return;
  if (document.getElementById("probe-dirty").value !== "" && e.detail.from === "edit") e.preventDefault();
});</script>'
   . '</body></html>';
