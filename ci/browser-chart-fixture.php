<?php
/**
 * The page ci/browser-chart.js drives (GridKit\Chart + js/chart.js, 1.121.0). One self-contained
 * HTML document, stylesheets and scripts inlined, so it loads from file:// with no server.
 *
 *     php ci/browser-chart-fixture.php [--mode=light|dark|dark-class] [--theme=ocean] [--lang=de]
 *                                      [--cases=400] [--seed=7] [--no-js] [--bare] > f.html
 *
 *   --mode    light (default); dark as data-gk-mode="dark"; dark-class as .gk-dark
 *   --theme   one of the six themes of css/themes.css (none: the stylesheet's own colours)
 *   --lang    en (default) or de — the words of the charts; their numbers keep their own locale
 *   --cases   how many random charts go into #gk-cases (PHP's config and html, for GK.chart.html())
 *   --no-js   without js/chart.js: what the server's chart does on its own
 *   --bare    js/chart.js without js/gridkit.js (the script needs nothing of it)
 *
 * On the page: charts in boxes of a known width (#box-*), and <template>s with what Chart.php
 * writes for the states the check puts them in — new data, a series switched off, another width,
 * hostile strings — so the SVG the script draws can be compared with the server's, byte for byte.
 *
 * GK_CSS and GK_CHART_JS name other files to inline instead of css/gridkit.css and js/chart.js:
 * the counter-checks run the page on copies with one rule taken out.
 */

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use GridKit\{Chart, Lang, StatCards};

$root = dirname(__DIR__);
$val = static function (string $name, string $default) use ($argv): string {
    foreach ($argv as $a) {
        if (str_starts_with($a, "--$name=")) return substr($a, strlen($name) + 3);
    }
    return $default;
};
$mode = $val('mode', 'light');
$theme = $val('theme', '');
Lang::loadDir($root . '/lang');
Lang::set($val('lang', 'en'));
$noJs = in_array('--no-js', $argv, true);
$bare = in_array('--bare', $argv, true);
// The charts warn about what they are given on purpose (the hostile ones); the page is the point.
set_error_handler(static fn (): bool => true, E_USER_WARNING);

$months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

/** Every chart on the page, by name: a function that configures a Chart. */
$charts = [
    'bar' => static fn (Chart $c) => $c->type('bar')->title('Revenue per month')->labels($months)
        ->series('2025', [8200, 7900, 9400, 10100, 9800, 11200, 12500, 11900, 10400, 12800, 14100, 16900])
        ->series('2026', [9100, 8800, 10900, 11600, 12100, 12900, 13800, 13100, 12200, null, null, null])
        ->series('Plan', [9000, 9000, 9500, 10000, 10500, 11000, 11500, 12000, 12500, 13000, 13500, 14000])
        ->format('money', ['currency' => 'EUR', 'locale' => 'de']),
    'line' => static fn (Chart $c) => $c->type('line')->title('Open rate')->labels(['W1', 'W2', 'W3', 'W4', 'W5', 'W6', 'W7', 'W8'])
        ->series('Customers', [41.2, 43.5, 39.8, 44.1, 46.3, 45.0, 47.2, 48.9])
        ->series('Trial', [28.4, 30.1, 33.6, 31.2, 35.8, 34.4, 37.1, 36.5])
        ->series('Partners', [52.0, 49.5, 51.3, null, 54.2, 55.8, 53.9, 57.4])
        ->series('Press', [12.0, 14.5, 13.1, 15.0, 16.2, 15.8, 17.9, 18.4])
        ->series('Staff', [61.0, 63.5, 62.1, 64.0, 65.2, 66.8, 64.9, 68.4])
        ->format('percent', ['locale' => 'en'])->zero(false)->markers()->dashes(),
    'area' => static fn (Chart $c) => $c->type('area')->stacked()->title('Visits by channel')->labels(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'])
        ->series('Search', [1820, 1940, 2010, 1890, 1760, 980, 860])
        ->series('Direct', [640, 700, 690, 720, 680, 410, 380])
        ->series('Social', [310, 290, 350, 420, 510, 620, 580])
        ->format('number', ['locale' => 'en'])->height(240),
    'hbar' => static fn (Chart $c) => $c->type('bar')->horizontal()->title('Change since last month')
        ->labels(['/pricing', '/blog/how-we-cut-our-invoice-processing-time-in-half', '/features/reports', '/contact'])
        ->series('Change', [18.5, 42.0, -7.5, -12.0])->format('percent', ['locale' => 'en'])->height(220),
    'donut' => static fn (Chart $c) => $c->type('donut')->title('Orders by source')
        ->labels(['Online shop', 'Marketplace', 'Phone', 'Retail partners', 'Trade fairs', 'E-mail', 'Other'])
        ->series('Orders', [1240, 860, 410, 380, 150, 60, 25])->format('number', ['locale' => 'de'])->height(220),
    'stack' => static fn (Chart $c) => $c->type('bar')->stacked()->title('Cash flow')->labels(['Q1', 'Q2', 'Q3', 'Q4'])
        ->series('In', [5200, 6100, 4800, 7300])->series('Out', [-4100, -6900, -3900, -5200])
        ->format('money', ['locale' => 'de'])->height(220),
    'empty' => static fn (Chart $c) => $c->type('line')->title('Sign-ups')->labels(['Jan', 'Feb'])->series('Sign-ups', [null, null])->height(160),
    'one' => static fn (Chart $c) => $c->type('line')->title('First month')->labels(['Nov'])->series('Orders', [37])->height(160),
    'zero' => static fn (Chart $c) => $c->type('bar')->title('Refunds')->labels(['Q1', 'Q2', 'Q3'])->series('Refunds', [0, 0, 0])
        ->format('money', ['locale' => 'en', 'currency' => 'USD'])->height(160),
];

/** The states the check drives a chart into, and what the server writes for each. */
$states = [
    // GK.chart.update(el, data) with these keys …
    'bar-update' => ['bar', ['series' => [
        ['name' => '2025', 'values' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12.5]],
        ['name' => '2026', 'values' => [-3, 2.25, null, '4.5', 0, 6, 7, 8, 9, 10, 11, 12]],
    ]], static fn (Chart $c) => $c->type('bar')->title('Revenue per month')->labels($months)
        ->series('2025', [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12.5])->series('2026', [-3, 2.25, null, '4.5', 0, 6, 7, 8, 9, 10, 11, 12])
        ->format('money', ['currency' => 'EUR', 'locale' => 'de'])],
    'line-update' => ['line', ['labels' => ['a', 'b', 'c'], 'type' => 'area', 'stacked' => true, 'markers' => false, 'dashes' => false, 'zero' => true,
        'series' => [['name' => 'One', 'values' => [1, 2, 3]], ['name' => 'Two', 'values' => [3, 2, 1]]],
        'format' => ['kind' => 'number', 'locale' => 'de', 'decimals' => 1]],
        static fn (Chart $c) => $c->type('area')->stacked()->title('Open rate')->labels(['a', 'b', 'c'])
            ->series('One', [1, 2, 3])->series('Two', [3, 2, 1])->format('number', ['locale' => 'de', 'decimals' => 1])],
    'donut-update' => ['donut', ['series' => [['name' => 'Orders', 'values' => [5, 0, 0, 0, 0, 0, 0]]]],
        static fn (Chart $c) => $c->type('donut')->title('Orders by source')
            ->labels(['Online shop', 'Marketplace', 'Phone', 'Retail partners', 'Trade fairs', 'E-mail', 'Other'])
            ->series('Orders', [5, 0, 0, 0, 0, 0, 0])->format('number', ['locale' => 'de'])->height(220)],
    'hostile' => ['bar', ['title' => '</svg><script>window.__pwned=1</script>', 'labels' => ['<img src=x onerror="window.__pwned=2">', "a\"b'c&d", "tab\there\x01\x7f", '{n} {type} {name}'],
        'series' => [['name' => '<b>bold</b> & "quoted"', 'values' => [1, 'NaN', true, 1e20]], ['name' => '', 'values' => [2, 3]]]],
        static fn (Chart $c) => $c->type('bar')->title('</svg><script>window.__pwned=1</script>')
            ->labels(['<img src=x onerror="window.__pwned=2">', "a\"b'c&d", "tab\there\x01\x7f", '{n} {type} {name}'])
            ->series('<b>bold</b> & "quoted"', [1, 'NaN', true, 1e20])->series('', [2, 3])
            ->format('money', ['currency' => 'EUR', 'locale' => 'de'])],
];

$render = static function (string $id, callable $fn, ?int $width = null): string {
    $c = new Chart($id);
    $fn($c);
    if ($width !== null) $c->width($width);
    return $c->html();
};

// The bar chart with its second series switched off, as Chart.php writes it.
$barOff = static function (?int $width = null) use ($months): string {
    $c = (new Chart('bar'))->type('bar')->title('Revenue per month')->labels($months)
        ->series('2025', [8200, 7900, 9400, 10100, 9800, 11200, 12500, 11900, 10400, 12800, 14100, 16900])
        ->series('2026', [9100, 8800, 10900, 11600, 12100, 12900, 13800, 13100, 12200, null, null, null], ['hidden' => true])
        ->series('Plan', [9000, 9000, 9500, 10000, 10500, 11000, 11500, 12000, 12500, 13000, 13500, 14000])
        ->format('money', ['currency' => 'EUR', 'locale' => 'de']);
    if ($width !== null) $c->width($width);
    return $c->html();
};

$templates = [];
foreach ($states as $name => [$chart, $data, $fn]) {
    $templates[$name] = ['chart' => $chart, 'data' => $data, 'html' => $render($chart, $fn, 640)];
}
$templates['bar-off'] = ['chart' => 'bar', 'data' => null, 'html' => $barOff(640)];
// The same charts at the width of a narrower box (what the script fits them to).
foreach (['bar', 'line', 'area', 'hbar', 'stack'] as $name) {
    $templates["$name-480"] = ['chart' => $name, 'data' => null, 'html' => $render($name, $charts[$name], 480)];
    $templates["$name-358"] = ['chart' => $name, 'data' => null, 'html' => $render($name, $charts[$name], 358)];
}

// Random charts: PHP's config and html, for GK.chart.html() to match byte for byte.
mt_srand((int) $val('seed', '7'));
$cases = [];
$words = ['Jan', 'Feb', 'Mär', 'Ünïcødé 🎉', '<b>&"\'', '{n} {type}', 'A rather long category name for an axis', '', 'x'];
$count = (int) $val('cases', '0');
for ($t = 0; $t < $count; $t++) {
    $types = ['line', 'area', 'bar', 'donut', 'sparkline'];
    $type = $types[mt_rand(0, 4)];
    $n = [1, 2, 3, 5, 7, 12, 31, 0][mt_rand(0, 7)];
    $c = (new Chart(mt_rand(0, 3) ? "r$t" : "id with spaces $t"))->type($type);
    $labels = [];
    for ($i = 0; $i < $n; $i++) $labels[] = $words[mt_rand(0, count($words) - 1)] . $i;
    if (mt_rand(0, 4)) $c->labels($labels);
    $ns = mt_rand(0, 5) ? mt_rand(1, 3) : mt_rand(0, 6);
    for ($s = 0; $s < $ns; $s++) {
        $vals = [];
        for ($i = 0; $i < $n; $i++) {
            $r = mt_rand(0, 9);
            $mag = [1, 10, 1000, 1e5, 0.01, 1e9, 3][mt_rand(0, 6)];
            $vals[] = match (true) {
                $r === 0 => null,
                $r === 1 => 0,
                $r === 2 => (string) mt_rand(0, 5000),
                default => (mt_rand(-1000000, 1000000) / 1000000) * $mag,
            };
        }
        $opts = [];
        if (mt_rand(0, 5) === 0) $opts['color'] = mt_rand(1, 5);
        if (mt_rand(0, 6) === 0) $opts['hidden'] = true;
        $c->series(mt_rand(0, 6) ? 'S' . $s . $words[mt_rand(0, count($words) - 1)] : '', $vals, $opts);
    }
    $fo = ['locale' => mt_rand(0, 1) ? 'de' : 'en'];
    if (mt_rand(0, 3) === 0) $fo['decimals'] = mt_rand(0, 3);
    if (mt_rand(0, 3) === 0) $fo['currency'] = ['USD', 'GBP', 'CHF'][mt_rand(0, 2)];
    if (mt_rand(0, 4)) $c->format(['number', 'money', 'percent'][mt_rand(0, 2)], $fo);
    if (mt_rand(0, 2) === 0) $c->stacked();
    if (mt_rand(0, 3) === 0) $c->horizontal();
    if (mt_rand(0, 3) === 0) $c->zero(false);
    if (mt_rand(0, 3) === 0) $c->markers();
    if (mt_rand(0, 4) === 0) $c->dashes();
    if (mt_rand(0, 2) === 0) $c->height(mt_rand(100, 500));
    if (mt_rand(0, 2) === 0) $c->width(mt_rand(150, 1400));
    if (mt_rand(0, 3) === 0) $c->title($words[mt_rand(0, count($words) - 1)], (bool) mt_rand(0, 1));
    if (mt_rand(0, 5) === 0) $c->table(['toggle', 'open', 'hidden'][mt_rand(0, 2)]);
    $cases[] = ['name' => "random $t ($type)", 'config' => $c->config(), 'html' => $c->html()];
}

$json = static fn (mixed $v): string => (string) json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
$css = (string) file_get_contents(getenv('GK_CSS') ?: $root . '/css/gridkit.css');
$chartJs = (string) file_get_contents(getenv('GK_CHART_JS') ?: $root . '/js/chart.js');
$gridkitJs = (string) file_get_contents($root . '/js/gridkit.js');

$bodyAttrs = 'class="gk-root' . ($mode === 'dark-class' ? ' gk-dark' : '') . '"'
    . ($theme !== '' ? ' data-gk-theme="' . htmlspecialchars($theme) . '"' : '')
    . ($mode === 'dark' ? ' data-gk-mode="dark"' : ($mode === 'light' ? ' data-gk-mode="light"' : ''));

$box = static fn (string $name, int $width, string $inner): string =>
    '<section class="box" id="box-' . $name . '" style="width:' . $width . 'px">' . $inner . '</section>';

$page = '';
foreach ($charts as $name => $fn) {
    $page .= $box($name, $name === 'donut' ? 560 : 640, $render($name, $fn, 640));
}
$page .= $box('narrow', 480, $render('narrow', $charts['bar'], 640));
$page .= '<section class="box" id="box-spark" style="width:640px">' . (static function (): string {
    ob_start();
    (new StatCards('spark-stats'))
        ->card('Revenue', 16900, ['format' => 'currency', 'trend' => '+12%', 'sparkline' => [8200, 7900, 9400, 12800, 14100, 16900]])
        ->card('Open rate', 48.9, ['format' => 'percent', 'sparkline' => ['values' => [41.2, 43.5, 39.8, 48.9], 'color' => 3, 'area' => true]])
        ->render();
    return (string) ob_get_clean();
})() . '<p>In a sentence: ' . Chart::sparkline([3, 5, 4, 6, 8, 7], ['label' => 'Weekly sign-ups', 'color' => 2]) . ' and on.</p></section>';
$page .= '<div id="events"></div>';
foreach ($templates as $name => $t) {
    $page .= '<template id="tpl-' . $name . '" data-chart="' . $t['chart'] . '" data-update="'
        . htmlspecialchars($t['data'] === null ? '' : $json($t['data'])) . '">' . $t['html'] . '</template>';
}

echo '<!doctype html><html lang="' . Lang::locale() . '"><head><meta charset="utf-8">'
   . '<meta name="viewport" content="width=device-width, initial-scale=1">'
   . '<title>chart fixture</title>'
   . '<style>' . $css . '</style><style>' . file_get_contents($root . '/css/themes.css') . '</style>'
   . '<style>body{background:var(--gk-surface-container);color:var(--gk-on-surface);margin:0;padding:16px;font-family:var(--gk-font);line-height:1.5}'
   . '.box{box-sizing:content-box;max-width:calc(100vw - 72px);background:var(--gk-surface);border:1px solid var(--gk-outline-variant);border-radius:12px;padding:20px;margin:0 0 16px}</style>'
   . Lang::jsConfig()
   . '</head><body ' . $bodyAttrs . '>' . $page
   . '<script type="application/json" id="gk-cases">' . $json($cases) . '</script>'
   . '<script>window.__events=[];["gk:chartselect","gk:charttoggle"].forEach(function(t){document.addEventListener(t,function(e){window.__events.push({type:t,detail:e.detail,target:e.target.id})})});</script>'
   . ($noJs || $bare ? '' : '<script>' . $gridkitJs . '</script>')
   . ($noJs ? '' : '<script>' . $chartJs . '</script>')
   . '</body></html>';
