<?php
/**
 * The page ci/browser-zurueck.js drives (1.128.0): a setData() table with search, a select filter
 * and paging (kunden), and a hand-written table under a RowPager with a search box (liste). One
 * self-contained HTML document, stylesheets and script inlined; the check serves it over http with
 * Cache-Control: no-store — what a PHP session sends — so Back reloads it instead of taking it
 * from the back/forward cache, the way the SSI Panel's /customers came back unfiltered.
 *
 *     php ci/browser-zurueck-fixture.php [--mode=light|dark] > f.html
 *
 * GK_CSS and GK_JS name other files to inline instead of css/gridkit.css and js/gridkit.js.
 */

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use GridKit\{Lang, Table};

$root = dirname(__DIR__);
$mode = in_array('--mode=dark', $argv, true) ? 'dark' : 'light';
Lang::set('de');

$cities = ['Wien', 'Graz', 'Linz'];
$rows = [];
for ($i = 1; $i <= 30; $i++) {
    $name = in_array($i, [4, 17, 25], true) ? "LoA Kunde $i" : "Kunde $i";
    $rows[] = ['id' => $i, 'name' => $name, 'city' => $cities[$i % 3],
               'lock' => $i % 2 === 0 ? "hat $i Rechnungen" : ''];
}
$t = (new Table('kunden'))
    ->setData($rows)
    ->search(['name'])
    ->filter('city', 'select', ['options' => array_combine($cities, $cities), 'placeholder' => 'Stadt'])
    ->column('name', 'Firma', ['sortable' => true])
    ->column('city', 'Stadt')
    ->button('edit', ['icon' => 'edit', 'href' => 'ziel.html?id={id}', 'title' => 'Bearbeiten'])
    ->button('delete', ['icon' => 'delete', 'color' => 'danger', 'confirm' => true, 'title' => 'Löschen', 'lockedIf' => 'lock'])
    ->paginate(5);
ob_start();
$t->render();
$table = (string) ob_get_clean();

$list = '<input id="such" type="search" class="gk-input" aria-label="Liste durchsuchen">'
      . '<table id="liste" class="gk-table" data-gk-rows="5" data-gk-search="#such"><thead><tr><th>Eintrag</th></tr></thead><tbody>';
for ($i = 1; $i <= 30; $i++) {
    $list .= '<tr><td>' . (in_array($i, [3, 9, 22], true) ? "LoA Eintrag $i" : "Eintrag $i") . ' <a href="ziel.html">öffnen</a></td></tr>';
}
$list .= '</tbody></table>';

$css = (string) file_get_contents(getenv('GK_CSS') ?: $root . '/css/gridkit.css');
$js = (string) file_get_contents(getenv('GK_JS') ?: $root . '/js/gridkit.js');
echo '<!doctype html><html lang="de"><head><meta charset="utf-8">'
   . '<meta name="viewport" content="width=device-width, initial-scale=1"><title>Zurück</title>'
   . '<style>' . $css . '</style>'
   . '<style>body{background:var(--gk-bg);color:var(--gk-on-surface);margin:0;font-family:var(--gk-font)}main{padding:24px}'
   . '*,*::before,*::after{transition:none!important;animation:none!important}</style>'
   . Lang::jsConfig()
   . '</head><body class="gk-root" data-gk-mode="' . $mode . '"><main>' . $table . '<h3>Liste</h3>' . $list . '</main>'
   . '<script>' . $js . '</script></body></html>';
