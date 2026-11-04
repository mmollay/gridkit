<?php
/**
 * The page ci/browser-permission-matrix.js drives (PermissionMatrix, 1.115.0): a matrix in a form
 * with every kind of cell — a locked role, two roles inheriting from a third, a two-level parent
 * chain, `requires` chains, risky rights, a group that starts folded, permissions without a group —
 * then one that saves as you go (fetch is stubbed by the test) and a read-only one. One self-contained
 * HTML document, stylesheets and script inlined, so it loads from file:// with no server.
 *
 *     php ci/browser-permission-matrix-fixture.php [--old-js] [--mode=dark|dark-class] [--theme=ocean] > f.html
 *
 *   --old-js       js/gridkit.js without its 1.115.0 block, as the PawBot dashboard keeps it
 *   --mode=…       dark mode as [data-gk-mode="dark"] or as .gk-dark
 *   --theme=…      one of the six themes of css/themes.css (indigo, the default, when not given)
 *
 * GK_CSS and GK_JS name other files to inline instead of css/gridkit.css and js/gridkit.js — the
 * counter-checks run the page on copies with one rule taken out.
 */

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use GridKit\{Lang, PermissionMatrix};

Lang::set('en');

$root = dirname(__DIR__);
$opt = static fn (string $name): bool => in_array("--$name", $argv, true);
$value = static function (string $name, string $default) use ($argv): string {
    foreach ($argv as $a) if (str_starts_with($a, "--$name=")) return substr($a, strlen($name) + 3);
    return $default;
};
$mode = $value('mode', 'light');
$theme = $value('theme', 'indigo');

/** The main matrix; the test builds the same one in PHP to read the form back (see fixtureMatrix()). */
function fixtureMatrix(): PermissionMatrix
{
    return (new PermissionMatrix('m'))
        ->caption('Fixture rights')
        ->role('admin', 'Admin', ['locked' => true, 'color' => 'red', 'description' => 'Everything'])
        ->role('acc', 'Accounting', ['color' => 'green'])
        ->role('editor', 'Editor', ['parent' => 'guest', 'description' => 'Pages <b>and</b> news'])
        ->role('support', 'Support', ['parent' => 'guest'])
        ->role('junior', 'Junior support', ['parent' => 'support'])
        ->role('guest', 'Guest')
        ->permission('dash.view', 'See the dashboard')
        ->group('inv', 'Invoices')
        ->permission('inv.view', 'View invoices')
        ->permission('inv.edit', 'Edit invoices', ['requires' => 'inv.view'])
        ->permission('inv.delete', 'Delete invoices', ['requires' => 'inv.edit', 'danger' => true, 'description' => 'Kept for seven years'])
        ->group('cust', 'Customers', ['description' => 'Addresses and contacts'])
        ->permission('cust.view', 'View customers')
        ->permission('cust.edit', 'Edit customers', ['requires' => 'cust.view'])
        ->permission('cust.export', 'Export customers', ['requires' => 'cust.view', 'danger' => true])
        ->group('users', 'Users & rights', ['collapsed' => true])
        ->permission('users.view', 'View users')
        ->permission('users.roles', 'Change roles', ['requires' => 'users.view', 'danger' => true])
        ->grants([
            'guest'   => ['cust.view', 'dash.view'],
            'support' => ['cust.edit', 'cust.view'],   // cust.view also its own: kept through a round trip
            'acc'     => ['inv.view', 'inv.edit', 'cust.view'],
            'editor'  => ['users.view'],
        ]);
}

// --read: what the server makes of a post — the urlencoded body on stdin, parsed by PHP itself.
if ($opt('read')) {
    parse_str((string) stream_get_contents(STDIN), $post);
    echo json_encode([
        'read'     => fixtureMatrix()->read($post['perm'] ?? []),
        'fromPost' => PermissionMatrix::fromPost($post['perm'] ?? [], ['acc', 'editor', 'support', 'junior', 'guest'],
            ['dash.view', 'inv.view', 'inv.edit', 'inv.delete', 'cust.view', 'cust.edit', 'cust.export', 'users.view', 'users.roles']),
        'raw'      => $post['perm'] ?? null,
    ], JSON_FORCE_OBJECT);
    exit(0);
}

$render = static function (PermissionMatrix $m): string {
    ob_start();
    $m->render();
    return (string) ob_get_clean();
};

$main = $render(fixtureMatrix());
$auto = $render((new PermissionMatrix('auto'))
    ->role('a', 'Alpha')
    ->role('b', 'Beta', ['parent' => 'a'])
    ->group('g', 'Pages')
    ->permission('p.view', 'View pages')
    ->permission('p.edit', 'Edit pages', ['requires' => 'p.view'])
    ->grants(['a' => ['p.view']])
    ->saveUrl('https://save.example/perm', ['headers' => ['X-CSRF-Token' => 'tok-123']]));
$ro = $render((new PermissionMatrix('ro'))
    ->role('x', 'Xavier')
    ->role('y', 'Yvonne', ['parent' => 'x'])
    ->group('g', 'Things')
    ->permission('t.one', 'Thing one')
    ->permission('t.two', 'Thing two')
    ->grants(['x' => ['t.one'], 'y' => ['t.two']])
    ->readonly());
// A matrix that arrives after the page is up: GK.initContent() is handed it.
$later = $render((new PermissionMatrix('later'))
    ->role('r', 'Role')->permission('q', 'Q')->grants(['r' => ['q']]));

$css = (string) file_get_contents(getenv('GK_CSS') ?: $root . '/css/gridkit.css');
$js = (string) file_get_contents(getenv('GK_JS') ?: $root . '/js/gridkit.js');
if ($opt('old-js')) {
    $js = substr($js, 0, (int) strpos($js, '// === 1.115.0 — permission matrix'));
}
$modeAttr = $mode === 'dark' ? ' data-gk-mode="dark"' : ' data-gk-mode="light"';
$bodyClass = 'gk-root' . ($mode === 'dark-class' ? ' gk-dark' : '');
$json = static fn (mixed $v): string => (string) json_encode($v, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE);

echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
   . '<meta name="viewport" content="width=device-width, initial-scale=1"><title>Permission matrix</title>'
   . '<style>' . $css . '</style><style>' . file_get_contents($root . '/css/themes.css') . '</style>'
   . '<style>body{background:var(--gk-bg);color:var(--gk-on-surface);margin:0;padding:12px;font-family:var(--gk-font)}h3{font-size:13px;margin:18px 0 6px}</style>'
   . '</head><body class="' . $bodyClass . '" data-gk-theme="' . htmlspecialchars($theme, ENT_QUOTES) . '"' . $modeAttr . '>'
   . '<h3>Main</h3><form id="f" method="post" action="about:blank">' . $main . '<button type="reset" id="form-reset">Reset form</button></form>'
   . '<h3>Autosave</h3>' . $auto
   . '<h3>Read-only</h3>' . $ro
   . '<div id="later-host"></div>'
   . '<script type="application/json" id="gk-fixture">' . $json(['later' => $later]) . '</script>'
   . '<script>' . $js . '</script></body></html>';
