<?php
/**
 * The page ci/browser-pager.js drives (one pager, 1.118.0): the pager of Table::paginate() beside
 * the standalone GridKit\Pagination it now looks like. A setData() table that pages in the
 * browser (st), a long one with a thousands separator (big), a server table that pages through
 * AJAX (srv), and two Pagination bars (alone: the same numbers as st; many: a gap and the PageSize
 * select). What the server writes for the pages the browser goes to is kept in <template>s, so the
 * check can compare the pager the script draws with the one Table.php draws for that page. One
 * self-contained HTML document, stylesheets and script inlined, so it loads from file:// with no
 * server; the AJAX reload is answered from the fragments Table.php renders for it.
 *
 *     php ci/browser-pager-fixture.php [--mode=light|dark|dark-class] [--theme=ocean] [--lang=de]
 *                                      [--width=390] [--old-js] > f.html
 *
 *   --mode    light (default); dark as data-gk-mode="dark"; dark-class as .gk-dark
 *   --theme   one of the six themes of css/themes.css (none: the stylesheet's own colours)
 *   --lang    en (default) or de
 *   --old-js  js/gridkit.js without its 1.118.0 block: the script of 1.117.0, as the PawBot
 *             dashboard keeps it. GK_OLD_JS names a file to use instead (a checkout of 1.117.0).
 *   --marks   the pager's strings with & " ' < > in them and no thousands separator, which
 *             Table.php and the script have to write alike
 *
 * GK_CSS and GK_JS name other files to inline instead of css/gridkit.css and js/gridkit.js: the
 * counter-checks run the page on copies with one rule taken out. GK_ICONFONT names the Material
 * Icons woff2; without it a glyph is a box of 1em, the size the font gives it.
 */

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use GridKit\{Lang, Pagination, Table};

$root = dirname(__DIR__);
$val = static function (string $name, string $default) use ($argv): string {
    foreach ($argv as $a) {
        if (str_starts_with($a, "--$name=")) return substr($a, strlen($name) + 3);
    }
    return $default;
};
$mode = $val('mode', 'light');
$theme = $val('theme', '');
Lang::set($val('lang', 'en'));
if (in_array('--marks', $argv, true)) {
    Lang::load(Lang::locale(), [
        'pagination.aria'    => 'Pages & "rows"',
        'pagination.range'   => "{from}–{to} <of> {total} 'rows'",
        'pagination.page_of' => 'Page {page} of {total} & "more"',
        'pagination.first'   => 'First <page>',
        'format.thousands'   => '',
    ]);
}
$capture = static function (callable $fn): string {
    ob_start();
    $fn();
    return (string) ob_get_clean();
};

$kinds = ['odd', 'even'];
$rows = static function (int $n) use ($kinds): array {
    $out = [];
    for ($i = 1; $i <= $n; $i++) $out[] = ['id' => $i, 'name' => "Item $i", 'kind' => $kinds[($i + 1) % 2], 'amount' => $i * 10];
    return $out;
};

/** A table as Table.php writes it for a request of $page ($ajax: the fragment of a reload). */
$table = static function (string $id, int $page, bool $ajax = false) use ($rows, $capture): string {
    $_GET = ['gk_page' => (string) $page];
    unset($_SERVER['HTTP_X_REQUESTED_WITH']);
    if ($ajax) {
        $_GET['gk_table'] = $id;
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
    }
    $t = new Table($id);
    match ($id) {
        'st' => $t->setData($rows(12))->search(['name', 'kind'])->paginate(5),
        'big' => $t->setData($rows(1234))->paginate(100),
        'srv' => $t->rows(array_slice($rows(95), ($page - 1) * 10, 10), 95)->paginate(10),
    };
    $t->caption("Table $id")
        ->column('name', 'Name', ['sortable' => true])
        ->column('kind', 'Kind')
        ->column('amount', 'Amount', ['format' => 'currency', 'align' => 'right', 'sortable' => true]);
    $html = $capture(fn () => $t->render());
    $_GET = [];
    unset($_SERVER['HTTP_X_REQUESTED_WITH']);
    return $html;
};

$fragments = [];
for ($p = 1; $p <= 10; $p++) $fragments[$p] = $table('srv', $p, true);

$body = '<main>'
    . '<h3>setData(), 12 rows, 5 to a page</h3>' . $table('st', 1)
    . '<h3>Pagination, the same numbers</h3><div id="alone">'
    . Pagination::build(['page' => 1, 'totalPages' => 3, 'total' => 12, 'baseUrl' => '/list', 'params' => ['q' => 'a b']]) . '</div>'
    . '<h3>rows(), 95 rows, 10 to a page, through AJAX</h3>' . $table('srv', 1)
    . '<h3>setData(), 1,234 rows</h3>' . $table('big', 1)
    . '<h3>Pagination with a gap and the page size</h3><div id="many">'
    . Pagination::build(['page' => 5, 'totalPages' => 12, 'total' => 238, 'label' => 'invoices', 'baseUrl' => '/list',
        'params' => ['q' => 'a b'], 'pageSize' => ['current' => 25, 'options' => [10, 25, 50]]]) . '</div>'
    . '</main>'
    // What Table.php writes for the pages the browser goes to.
    . '<template id="tpl-st-2">' . $table('st', 2) . '</template>'
    . '<template id="tpl-st-3">' . $table('st', 3) . '</template>'
    . '<template id="tpl-big-13">' . $table('big', 13) . '</template>'
    . '<template id="tpl-big-7">' . $table('big', 7) . '</template>';

$css = (string) file_get_contents(getenv('GK_CSS') ?: $root . '/css/gridkit.css');
$js = (string) file_get_contents(getenv('GK_JS') ?: $root . '/js/gridkit.js');
if (in_array('--old-js', $argv, true)) {
    if (getenv('GK_OLD_JS')) {
        $js = (string) file_get_contents((string) getenv('GK_OLD_JS'));
    } else {
        $cut = strpos($js, '// === 1.118.0 — one pager');
        if ($cut === false) { fwrite(STDERR, "the 1.118.0 block is not in js/gridkit.js\n"); exit(1); }
        $js = substr($js, 0, $cut);
    }
}
$font = getenv('GK_ICONFONT') && is_file((string) getenv('GK_ICONFONT'))
    ? '@font-face{font-family:"Material Icons";src:url(data:font/woff2;base64,' . base64_encode((string) file_get_contents((string) getenv('GK_ICONFONT'))) . ') format("woff2")}'
      . '.material-icons{font-family:"Material Icons";font-weight:400;font-style:normal;line-height:1;letter-spacing:normal;text-transform:none;display:inline-block;white-space:nowrap;direction:ltr;font-feature-settings:"liga"}'
    // The font's metrics: a ligature is one glyph of 1em. Without it the word would stand there.
    : '.material-icons{display:inline-block;width:1em;height:1em;line-height:1;overflow:hidden;white-space:nowrap;color:transparent}';

$json = static fn (mixed $v): string => (string) json_encode($v, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE);
$bodyAttrs = 'class="gk-root' . ($mode === 'dark-class' ? ' gk-dark' : '') . '"'
    . ($theme !== '' ? ' data-gk-theme="' . htmlspecialchars($theme) . '"' : '')
    . ($mode === 'dark' ? ' data-gk-mode="dark"' : ($mode === 'light' ? ' data-gk-mode="light"' : ''));

echo '<!doctype html><html lang="' . Lang::locale() . '"><head><meta charset="utf-8">'
   . '<meta name="viewport" content="width=device-width, initial-scale=1"><title>Pager</title>'
   . '<style>' . $css . '</style><style>' . file_get_contents($root . '/css/themes.css') . '</style>'
   // The line height an application's page sets: a link inherits it, a button does not by itself.
   . '<style>' . $font . 'body{background:var(--gk-bg);color:var(--gk-on-surface);margin:0;font-family:var(--gk-font);line-height:1.5}'
   . 'main{padding:24px 40px;max-width:1000px}h3{font-size:13px;margin:22px 0 6px}'
   . '@media (max-width:640px){main{padding:16px}}'
   . '*,*::before,*::after{transition:none!important;animation:none!important}</style>'
   . Lang::jsConfig()
   . '</head><body ' . $bodyAttrs . '>' . $body
   // The server of the AJAX reload: the fragment Table.php renders for the page asked for. Every
   // request is logged; __fetchDelay holds the answer back, to see the table while it loads.
   . '<script type="application/json" id="gk-fragments">' . $json($fragments) . '</script>'
   . '<script>window.__fetchLog = []; window.__fetchDelay = 0;
window.fetch = function (url) {
  var u = new URL(String(url), "http://x/");
  window.__fetchLog.push(u.search);
  var all = JSON.parse(document.getElementById("gk-fragments").textContent);
  var html = all[u.searchParams.get("gk_page") || "1"] || "";
  return new Promise(function (ok) { setTimeout(function () { ok(new Response(html, { status: 200 })); }, window.__fetchDelay); });
};</script>'
   . '<script>' . $js . '</script>'
   . '</body></html>';
