<?php
/**
 * The page ci/browser-palette.js drives (GridKit\CommandPalette, 1.122.0): an admin shell — a sidebar with
 * groups, a badge, a submenu and the current page; a header with the trigger; a palette with links,
 * commands, an umlaut, a hostile label, two search URLs (one on this origin, one on another); a field, a
 * rich-text editor, GK.search on Ctrl+K as well, and a modal to open the palette over. One self-contained
 * HTML document — both stylesheets and both scripts inlined. The browser test serves it as
 * http://gk.test/fixture.html, so the search URL is on the page's own origin; it answers the records.
 *
 *     php ci/browser-palette-fixture.php          # the page
 *     php ci/browser-palette-fixture.php bare     # no palette from the server: GK.palette.open() builds one
 *     php ci/browser-palette-fixture.php nojs     # the page without its scripts
 *     php ci/browser-palette-fixture.php link     # the trigger as a link to a search page (fallback())
 */

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use GridKit\{CommandPalette, Header, Lang, Sidebar};

$mode = $argv[1] ?? '';
Lang::set('en');
$root = dirname(__DIR__);
$capture = static function (callable $fn): string {
    ob_start();
    $fn();
    return (string) ob_get_clean();
};

$sidebar = $capture(fn () => (new Sidebar('main'))
    ->brand('Fixture', 'widgets')
    ->group('Main')
    ->item('Dashboard', '/dashboard', 'dashboard')
    ->item('Customers', '/customers', 'people')
    ->item('Invoices', '/invoices', 'receipt_long', ['badge' => 3])
    ->item('Reports', '#', 'bar_chart', ['children' => [
        ['label' => 'Monthly report', 'href' => '/reports/monthly'],
        ['label' => 'Yearly report', 'href' => '/reports/yearly'],
    ]])
    ->group('System')
    ->item('Settings', '/settings', 'settings', ['active' => true])
    ->item('Users & roles', '/users', 'admin_panel_settings')
    ->render());

$palette = (new CommandPalette('main'))
    ->item('New invoice', '/invoices/new', ['icon' => 'add', 'group' => 'Create', 'keywords' => ['bill', 'create'], 'shortcut' => 'N'])
    ->item('New customer', '/customers/new', ['icon' => 'person_add', 'group' => 'Create'])
    ->item('Print the invoice list', '/invoices/print', ['icon' => 'print', 'group' => 'Create'])
    ->item('Dark mode', '', ['icon' => 'dark_mode', 'action' => 'toggle-dark', 'hint' => 'Switches the colours'])
    ->item('Sign out', '', ['icon' => 'logout', 'action' => 'sign-out'])
    ->item('Bäckerei Groß & Söhne', '/customers/77', ['icon' => 'storefront', 'group' => 'Pinned'])
    ->item('Café Crème', '/customers/88', ['icon' => 'local_cafe', 'group' => 'Pinned'])
    ->item('<img src=x onerror="window.__pwned=1">"\'&</script>', '/hostile?a=1&b=<2>', ['group' => 'Pinned', 'hint' => '<b>bold</b>'])
    ->items(array_map(static fn (int $i): array => ['label' => "Archive $i", 'href' => "/archive/$i", 'group' => 'Archive'], range(1, 9)))
    ->search('http://gk.test/search', ['minLength' => 2, 'debounce' => 120, 'group' => 'Records'])
    ->search('http://other.test/search', ['minLength' => 1])
    ->recent(4)
    ->maxPerGroup(5)
    ->slash();
if ($mode === 'link') $palette->fallback('/search');

$header = (new Header())->title('Fixture')->action($palette->trigger())->render();
$paletteHtml = $mode === 'bare' ? '' : $palette->html();

echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
   . '<meta name="viewport" content="width=device-width, initial-scale=1"><title>Palette</title>'
   . '<style>' . file_get_contents($root . '/css/gridkit.css') . '</style>'
   . '<style>' . file_get_contents($root . '/css/themes.css') . '</style>'
   . '<style>main{padding:16px}#editor{min-height:40px;border:1px solid var(--gk-outline);padding:4px}</style>'
   . Lang::jsConfig()
   . '</head><body class="gk-root" data-gk-theme="indigo" data-gk-mode="light">'
   . $sidebar
   . '<div class="gk-with-sidebar">' . $header
   . '<main class="gk-main" id="main"><h1>Fixture</h1>'
   . '<button type="button" id="before">before</button> '
   . '<input type="text" id="field" aria-label="A field"> '
   . '<div id="editor" contenteditable="true" aria-label="An editor">Rich text</div>'
   . '<button type="button" id="open-modal">Open a modal</button>'
   . '<div class="gk-modal-overlay" id="modal" hidden><div class="gk-modal gk-modal-small"><div class="gk-modal-header">'
   . '<h3 class="gk-modal-title">A modal</h3><button type="button" class="gk-modal-close">&times;</button></div>'
   . '<div class="gk-modal-body"><button type="button" id="in-modal">In the modal</button></div></div></div>'
   . '<p style="height:1400px">Long page.</p>'
   . '</main></div>'
   . $paletteHtml
   . ($mode === 'nojs' ? '' : '<script>' . file_get_contents($root . '/js/gridkit.js') . '</script>'
        . '<script>GK.search.init({ url: "http://gk.test/quick", hotkey: "ctrl+k", minLength: 0 });'
        . 'document.getElementById("open-modal").addEventListener("click", function () { GK.modal.show("#modal", { focus: "#in-modal" }); });</script>'
        . '<script>' . file_get_contents($root . '/js/command-palette.js') . '</script>')
   . '</body></html>';
