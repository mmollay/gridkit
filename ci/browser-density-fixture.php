<?php
/**
 * The page ci/browser-density.js drives (Table::densityToggle(), 1.109.0): one table of every
 * kind the step has to move — md with a selection, buttons and a footer, sm, lg, a list, a row
 * link, the variants compact, padded and very-compact, the size xs, a server table that reloads, groups, scroll
 * mode — each with the control, and each again without it (id "n-…"), the twin a measurement is
 * compared with. One self-contained HTML document, stylesheets and script inlined, so it loads
 * from file:// with no server.
 *
 *     php ci/browser-density-fixture.php [--page=konsole|weit] [--no-themes] [--old-js] [--late] > f.html
 *
 *   --page=…     the page's own density, as Theme::density() writes it (on <body>)
 *   --no-themes  css/gridkit.css only: the toggle must not need css/themes.css
 *   --old-js     js/gridkit.js without its 1.109.0 block, as the PawBot dashboard keeps it
 *   --late       the script is added after the document is complete (GK.init() then runs at once)
 *
 * GK_CSS and GK_JS name other files to inline instead of css/gridkit.css and js/gridkit.js — the
 * counter-checks run the page on copies with one rule taken out.
 */

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use GridKit\{Lang, Table};

Lang::set('en');

$root = dirname(__DIR__);
$opt = static fn (string $name): bool => in_array("--$name", $argv, true);
$page = '';
foreach ($argv as $a) {
    if (str_starts_with($a, '--page=')) $page = substr($a, 7);
}

$rows = [
    ['id' => 1, 'no' => 'A-1', 'name' => 'Carla', 'email' => 'c@example.com', 'amount' => 1200.5, 'state' => 'active'],
    ['id' => 2, 'no' => 'A-2', 'name' => 'Anton', 'email' => 'a@example.com', 'amount' => 9.9, 'state' => 'draft'],
    ['id' => 3, 'no' => 'A-3', 'name' => 'Berta', 'email' => 'b@example.com', 'amount' => 0, 'state' => 'inactive'],
];

/** Each table twice: with the control (id $id) and without (id "n-$id"). */
$tables = [
    'md' => fn (string $id) => (new Table($id))->setData($rows)->search(['name'])->selectable('id')
        ->column('no', 'No.', ['muted' => true])
        ->column('name', 'Name', ['sortable' => true, 'sub' => 'email'])
        ->column('amount', 'Amount', ['format' => 'currency'])
        ->column('state', 'State', ['format' => 'label'])
        ->button('edit', ['icon' => 'edit', 'position' => 'right', 'params' => ['id' => 'id']])
        ->button('delete', ['icon' => 'delete', 'class' => 'danger', 'position' => 'right', 'params' => ['id' => 'id']])
        ->footer(['Total', ['text' => '1,210.40', 'align' => 'right']]),
    'sm' => fn (string $id) => (new Table($id))->setData($rows)->size('sm')
        ->column('name', 'Name')->column('state', 'State', ['format' => 'label'])
        ->button('edit', ['icon' => 'edit', 'params' => ['id' => 'id']]),
    'lg' => fn (string $id) => (new Table($id))->setData($rows)->size('lg')
        ->column('name', 'Name')->column('state', 'State', ['format' => 'label']),
    'list' => fn (string $id) => (new Table($id))->setData($rows)->size('list')->rowLink('/u/{id}')
        ->column('name', 'Name', ['sub' => 'email'])->column('state', 'State', ['format' => 'label']),
    'link' => fn (string $id) => (new Table($id))->setData($rows)->rowLink('/u/{id}')
        ->column('name', 'Name')->column('amount', 'Amount', ['format' => 'currency']),
    'cmp' => fn (string $id) => (new Table($id))->setData($rows)->variant('compact')
        ->column('name', 'Name')->column('state', 'State', ['format' => 'label']),
    'pad' => fn (string $id) => (new Table($id))->setData($rows)->variant('padded')
        ->column('name', 'Name')->column('state', 'State', ['format' => 'label']),
    'vc' => fn (string $id) => (new Table($id))->setData($rows)->variant('very-compact')
        ->column('name', 'Name')->column('state', 'State', ['format' => 'label']),
    'xs' => fn (string $id) => (new Table($id))->setData($rows)->size('xs')
        ->column('name', 'Name')->column('state', 'State', ['format' => 'label']),
    'grp' => fn (string $id) => (new Table($id))->setData($rows)->groupBy('state')
        ->column('name', 'Name')->column('amount', 'Amount', ['format' => 'currency'])
        ->footer(['Total', ['text' => '1,210.40', 'align' => 'right']]),
    'scr' => fn (string $id) => (new Table($id))->setData($rows)->mobile('scroll')
        ->column('name', 'Name')->column('email', 'Email')->column('state', 'State', ['format' => 'label']),
    'scrs' => fn (string $id) => (new Table($id))->setData($rows)->size('sm')->mobile('scroll')
        ->column('name', 'Name')->column('email', 'Email')->column('state', 'State', ['format' => 'label']),
    'srv' => fn (string $id) => (new Table($id))->rows(array_slice($rows, 0, 2), 3)->paginate(2)
        ->column('name', 'Name')->column('state', 'State', ['format' => 'label']),
];

$render = static function (Table $t): string {
    ob_start();
    $t->render();
    return (string) ob_get_clean();
};

$out = [];
foreach ($tables as $key => $make) {
    $out[] = '<h3>' . $key . '</h3>' . $render($make($key)->densityToggle()) . $render($make("n-$key"));
}

// What the server answers when the table "srv" reloads (page 2), for the stubbed fetch.
$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
$_GET = ['gk_table' => 'srv', 'gk_page' => '2'];
$fragment = $render((new Table('srv'))->rows(array_slice($rows, 2), 3)->paginate(2)
    ->column('name', 'Name')->column('state', 'State', ['format' => 'label'])->densityToggle());
unset($_SERVER['HTTP_X_REQUESTED_WITH']);
$_GET = [];

// A table that arrives after the page is up: GK.initContent() is handed it.
$later = $render((new Table('later'))->setData($rows)->column('name', 'Name')->densityToggle());

$css = (string) file_get_contents(getenv('GK_CSS') ?: $root . '/css/gridkit.css');
$js = (string) file_get_contents(getenv('GK_JS') ?: $root . '/js/gridkit.js');
if ($opt('old-js')) {
    $js = substr($js, 0, (int) strpos($js, '// === 1.109.0 — density toggle'));
}
$themes = $opt('no-themes') ? '' : '<style>' . file_get_contents($root . '/css/themes.css') . '</style>';
$density = in_array($page, ['konsole', 'weit'], true) ? ' data-gk-density="' . $page . '"' : '';
$json = static fn (mixed $v): string => (string) json_encode($v, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE);
$script = $opt('late')
    ? '<script type="text/plain" id="gk-js">' . str_replace('</', '<\/', $js) . '</script>'
      . '<script>window.addEventListener("load", function () { var s = document.createElement("script");'
      . ' s.textContent = document.getElementById("gk-js").textContent; document.body.appendChild(s); });</script>'
    : '<script>' . $js . '</script>';

echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
   . '<meta name="viewport" content="width=device-width, initial-scale=1"><title>Density</title>'
   . '<style>' . $css . '</style>' . $themes
   . '<style>body{background:var(--gk-bg);color:var(--gk-on-surface);margin:0;padding:12px;font-family:var(--gk-font)}h3{font-size:13px;margin:18px 0 6px}</style>'
   // .gk-root as on a GridKit page (Layout::bodyTag('gk-root')): it sets box-sizing, and a list's
   // 64px row is 64px with it.
   . '</head><body class="gk-root" data-gk-theme="indigo" data-gk-mode="light"' . $density . '>'
   . implode("\n", $out)
   . '<div id="later-host"></div>'
   . '<script type="application/json" id="gk-fixture">' . $json(['fragment' => $fragment, 'later' => $later]) . '</script>'
   . $script . '</body></html>';
