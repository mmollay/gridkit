<?php
/**
 * GridKit\Chart (1.121.0): server-rendered SVG charts.
 *
 * What is checked here: the axis (nice 1-2-5 ticks, zero for bars, a band around a single
 * value), numbers in German and English (rounding half away from zero on the decimal string,
 * money, percent, compact axis labels), the edges of the data (none, one point, only zeros,
 * negative values, gaps), stacked sums, the angles of a donut and its "Other", escaping and
 * hostile input (every misuse a warning, never an exception, never an empty page), the words a
 * screen reader gets, the sparkline and its place in StatCards, and the seams with js/chart.js,
 * the stylesheet, the block index and the pages that load the script. That the script draws
 * byte for byte what this class draws is ci/browser-chart.js's job — it runs JavaScript.
 */

declare(strict_types=1);

use GridKit\{Chart, Lang, StatCards};

/** @return array<string,callable> */
return (static function (): array {

$root = dirname(__DIR__);
Lang::loadDir($root . '/lang');

/** A private static helper of Chart, called directly. */
$call = static function (string $method, mixed ...$args): mixed {
    $m = new ReflectionMethod(Chart::class, $method);
    return $m->invoke(null, ...$args);
};
/** Render with warnings collected instead of raised. @return array{0:string,1:list<string>} */
$quiet = static function (callable $fn): array {
    $warnings = [];
    set_error_handler(static function (int $no, string $msg) use (&$warnings): bool {
        $warnings[] = $msg;
        return true;
    }, E_USER_WARNING);
    try {
        $out = (string) $fn();
    } finally {
        restore_error_handler();
    }
    return [$out, $warnings];
};
$fmt = static fn (string $kind, string $locale, ?int $dec = null, string $cur = 'EUR'): array =>
    ['kind' => $kind, 'currency' => $cur, 'locale' => $locale, 'decimals' => $dec];
/** The texts of the value axis, top to bottom as the SVG writes them (bottom first). @return list<string> */
$ticks = static function (string $html): array {
    preg_match_all('#<text x="[\d.]+" y="[\d.-]+" text-anchor="end">([^<]*)</text>#', $html, $m);
    return array_map(static fn (string $s): string => html_entity_decode($s, ENT_QUOTES, 'UTF-8'), $m[1]);
};
$nb = "\u{00A0}";

return [

// ── The axis ───────────────────────────────────────────────────────────────

'ticks step by 1, 2 or 5 times a power of ten and cover the data' => function () use ($call, $fmt): void {
    $f = $fmt('number', 'en');
    $r = $call('nice', 0.0, 16900.0, 5, $f);
    T::eq(array_column($r['ticks'], 'v'), [0.0, 5000.0, 10000.0, 15000.0, 20000.0], 'revenue up to 16,900 in steps of 5,000');
    T::eq(array_column($r['ticks'], 'text'), ['0', '5K', '10K', '15K', '20K'], 'from 10,000 on the axis is compact, zero stays 0');
    $r = $call('nice', 0.0, 0.73, 5, $f);
    T::eq(array_column($r['ticks'], 'text'), ['0', '0.2', '0.4', '0.6', '0.8'], 'below one: decimals as the step needs, no 0.6000000000000001');
    $r = $call('nice', -7.5, 42.0, 5, $f);
    T::eq(array_column($r['ticks'], 'v'), [-10.0, 0.0, 10.0, 20.0, 30.0, 40.0, 50.0], 'negative and positive: the zero is a tick');

    // Random ranges: the step is 1, 2 or 5 × 10^e, every tick a multiple of it, lo and hi covered.
    mt_srand(4);
    for ($i = 0; $i < 400; $i++) {
        $a = (mt_rand(-1000000, 1000000) / 1000) * [1, 0.001, 1000, 1e6][mt_rand(0, 3)];
        $b = $a + mt_rand(1, 1000000) / 100 * [1, 0.001, 1000][mt_rand(0, 2)];
        $target = mt_rand(2, 8);
        $r = $call('nice', $a, $b, $target, $f);
        $v = array_column($r['ticks'], 'v');
        $step = $v[1] - $v[0];
        $e = floor(log10($step));
        $mant = round($step / 10 ** $e, 6);
        T::ok(in_array($mant, [1.0, 2.0, 5.0], true), "step $step of $a … $b is 1, 2 or 5 × 10^e");
        T::ok($r['lo'] <= $a + abs($step) * 1e-9 && $r['hi'] >= $b - abs($step) * 1e-9, "$a … $b is covered by {$r['lo']} … {$r['hi']}");
        T::ok(count($v) <= 2 * $target + 2, "about $target steps for $a … $b, not " . count($v));
    }
},

'bars and areas start at zero, a line may start near its data' => function () use ($ticks): void {
    Lang::set('en');
    $bar = (new Chart('b'))->type('bar')->labels(['a', 'b'])->series('s', [40, 50])->html();
    T::eq($ticks($bar)[0], '0', 'a bar chart of 40 and 50 starts at 0');
    $line = (new Chart('l'))->type('line')->labels(['a', 'b'])->series('s', [40, 50])->zero(false)->html();
    T::ok((float) $ticks($line)[0] >= 30, 'zero(false): the line axis starts near 40, at ' . $ticks($line)[0]);
    $one = (new Chart('o'))->type('line')->labels(['a'])->series('s', [37])->zero(false)->html();
    T::ok(count($ticks($one)) >= 2, 'a single value gets a band around it, not a zero-height axis');
    $zero = (new Chart('z'))->type('bar')->labels(['a', 'b'])->series('s', [0, 0])->html();
    T::eq($ticks($zero), ['0', '0.2', '0.4', '0.6', '0.8', '1'], 'only zeros: an axis 0 … 1');
    T::notContains($zero, 'gk-chart-bar"', 'and no bar');
},

// ── Numbers ────────────────────────────────────────────────────────────────

'numbers in German and English' => function () use ($call, $fmt, $nb): void {
    $v = static fn (?string $c, array $f): string => $call('formatValue', $c, $f);
    T::eq($v('1234.5', $fmt('money', 'de')), "1.234,50{$nb}€", 'de money');
    T::eq($v('1234.5', $fmt('money', 'en')), '€1,234.50', 'en money');
    T::eq($v('-1234.5', $fmt('money', 'de')), "-1.234,50{$nb}€", 'de negative money');
    T::eq($v('-1234.5', $fmt('money', 'en')), '-€1,234.50', 'en negative money');
    T::eq($v('12.5', $fmt('money', 'en', null, 'CHF')), "CHF{$nb}12.50", 'a currency without a sign is written out');
    T::eq($v('12.5', $fmt('money', 'de', null, 'USD')), "12,50{$nb}$", 'USD after the figure in German');
    T::eq($v('12.5', $fmt('percent', 'de')), "12,5{$nb}%", 'de percent');
    T::eq($v('12.5', $fmt('percent', 'en')), '12.5%', 'en percent');
    T::eq($v('12.04', $fmt('percent', 'en')), '12%', 'percent: up to one decimal, trailing zeros dropped');
    T::eq($v('1234567.891', $fmt('number', 'de')), '1.234.567,89', 'number: up to two decimals');
    T::eq($v('1234.5', $fmt('number', 'en', 0)), '1,235', 'decimals 0');
    T::eq($v('1.005', $fmt('number', 'en')), '1.01', '1.005 rounds up: the decimal string is rounded, not the double');
    T::eq($v('999.995', $fmt('money', 'de')), "1.000,00{$nb}€", 'a carry through every digit');
    T::eq($v('-0.004', $fmt('number', 'en')), '0', 'no minus in front of a zero');
    T::eq($v(null, $fmt('number', 'en')), '—', 'a gap is a dash');
},

'canon is the shortest decimal string that reads back as the same double' => function () use ($call): void {
    T::eq($call('canon', 0.1 + 0.2), '0.30000000000000004', '0.1 + 0.2');
    T::eq($call('canon', 1e-7), '0.0000001', 'no exponent for small numbers');
    T::eq($call('canon', 1e15), '1000000000000000', 'no exponent for large ones');
    T::eq($call('canon', -0.0), '0', 'no negative zero');
    T::eq($call('canon', 123456789.125), '123456789.125', 'every digit kept');
    mt_srand(9);
    $bad = 0;
    for ($i = 0; $i < 3000; $i++) {
        $x = (mt_rand() / mt_getrandmax() - 0.5) * 10 ** mt_rand(-8, 15);
        if ((float) $call('canon', $x) !== $x) $bad++;
    }
    T::eq($bad, 0, '3000 random doubles read back exactly');
},

'coordinates have two decimals at most and never read -0' => function () use ($call): void {
    T::eq($call('num', 12.0), '12', 'whole');
    T::eq($call('num', 12.5), '12.5', 'trailing zero dropped');
    T::eq($call('num', -3.456), '-3.46', 'half away from zero');
    T::eq($call('num', -0.004), '0', 'no -0');
    T::eq($call('num', 0.105), '0.11', 'two decimals');
},

'a sine of its own, the same digits in PHP and the browser, near enough to the platform\'s' => function () use ($call): void {
    $worst = 0.0;
    for ($i = -400; $i <= 400; $i++) {
        $x = $i * 0.0173;
        $worst = max($worst, abs($call('sin', $x) - sin($x)), abs($call('cos', $x) - cos($x)));
    }
    T::ok($worst < 1e-12, "the Taylor series is within 1e-12 of sin() and cos() (worst $worst)");
},

// ── The edges of the data ──────────────────────────────────────────────────

'no data, one point, gaps and negative values' => function (): void {
    Lang::set('en');
    $empty = (new Chart('e'))->type('line')->labels(['a', 'b'])->series('s', [null, null])->html();
    T::contains($empty, '<text class="gk-chart-empty"', 'no value: a word in the middle');
    T::contains($empty, '<desc id="gk-chart-e-d">No data</desc>', 'and the description says so');
    T::notContains($empty, 'gk-chart-legend', 'no legend for nothing');
    $none = (new Chart('n'))->type('bar')->html();
    T::contains($none, 'gk-chart-empty', 'no labels and no series: the same');

    $one = (new Chart('o'))->type('line')->labels(['Nov'])->series('Orders', [37])->html();
    T::notContains($one, 'class="gk-chart-line"', 'one point: no line');
    T::eq(substr_count($one, 'gk-chart-dot gk-chart-dot-on'), 1, 'one point: one dot, shown');

    $gaps = (new Chart('g'))->type('line')->labels(['a', 'b', 'c', 'd', 'e'])->series('s', [1, 2, null, 4, 5])->width(300)->html();
    preg_match('#class="gk-chart-line" d="([^"]+)"#', $gaps, $m);
    T::eq(substr_count($m[1] ?? '', 'M'), 2, 'a gap breaks the line into two runs');

    $lone = (new Chart('l'))->type('line')->labels(array_map('strval', range(1, 40)))->series('s', array_merge([5, null, 7, null], array_fill(0, 36, 3)))->width(300)->html();
    T::contains($lone, 'class="gk-chart-dot gk-chart-dot-on" data-gk-chart-i="0"', 'a point between two gaps shows even where points are hidden');
    T::contains($lone, 'class="gk-chart-dot" data-gk-chart-i="5"', 'the others on a dense line stay hidden until hovered');

    $neg = (new Chart('n'))->type('bar')->labels(['a', 'b'])->series('s', [5, -5])->html();
    preg_match('#gk-chart-zero" x1="[\d.]+" y1="([\d.]+)"#', $neg, $z);
    preg_match_all('#class="gk-chart-bar" data-gk-chart-i="\d" d="M[\d.]+,([\d.]+)V([\d.]+)#', $neg, $bars, PREG_SET_ORDER);
    T::ok(isset($z[1]) && count($bars) === 2, 'a zero line and two bars');
    T::eq($bars[0][1] ?? '', $z[1] ?? '-', 'the positive bar stands on the zero line');
    T::ok((float) ($bars[1][2] ?? 0) > (float) ($z[1] ?? 0), 'the negative bar hangs below it');
},

'stacked: the Total column is every series added up' => function (): void {
    Lang::set('en');
    $html = (new Chart('s'))->type('bar')->stacked()->labels(['Q1', 'Q2'])
        ->series('In', [5200, 6100])->series('Extra', [800, null])->series('Out', [-4100, -6900])->format('number', ['locale' => 'en'])->html();
    T::contains($html, '<tr><th scope="row">Q1</th><td class="gk-num">5,200</td><td class="gk-num">800</td><td class="gk-num">-4,100</td><td class="gk-num">1,900</td></tr>', 'Q1: 5,200 + 800 - 4,100');
    T::contains($html, '<tr><th scope="row">Q2</th><td class="gk-num">6,100</td><td class="gk-num">—</td><td class="gk-num">-6,900</td><td class="gk-num">-800</td></tr>', 'Q2: a gap counts as 0');
    T::contains($html, 'Highest total 1,900 (Q1).', 'the summary names the highest total');
},

'stacked: the scale holds the sums above and below zero' => function () use ($ticks): void {
    Lang::set('en');
    $html = (new Chart('s'))->type('bar')->stacked()->labels(['Q1'])->series('a', [30])->series('b', [45])->series('c', [-20])
        ->format('number', ['locale' => 'en'])->html();
    $t = array_map('floatval', $ticks($html));
    T::ok(min($t) <= -20 && max($t) >= 75, 'the axis reaches -20 and 75 (30 + 45): ' . implode(' ', $t));
    // The segments of one bar touch with a 2px gap and only the outer one is rounded.
    preg_match_all('#d="(M[^"]+)"#', $html, $m);
    $paths = array_values(array_filter($m[1], static fn (string $d): bool => str_starts_with($d, 'M')));
    T::eq(count(array_filter($paths, static fn (string $d): bool => str_contains($d, 'A'))), 2, 'two rounded ends: the top of the positive stack, the bottom of the negative');
},

'a donut: the angles of its parts, the fold into Other, the total' => function () use ($nb): void {
    Lang::set('en');
    $html = (new Chart('d'))->type('donut')->labels(['a', 'b', 'c', 'd'])->series('n', [1, 1, 1, 1])->height(200)->html();
    preg_match_all('#class="gk-chart-slice gk-chart-c(\d)" data-gk-chart-i="(\d)" d="M([\d.-]+),([\d.-]+)A[\d.]+,[\d.]+ 0 [01] 1 ([\d.-]+),([\d.-]+)#', $html, $m, PREG_SET_ORDER);
    T::eq(count($m), 4, 'four parts, four slices');
    $ctr = 100.0;
    foreach ($m as $k => $s) {
        $a0 = atan2((float) $s[4] - $ctr, (float) $s[3] - $ctr);
        $a1 = atan2((float) $s[6] - $ctr, (float) $s[5] - $ctr);
        $span = fmod($a1 - $a0 + 4 * M_PI, 2 * M_PI);
        $start = fmod($a0 - (-M_PI / 2) + 4 * M_PI, 2 * M_PI);
        T::ok(abs($span - (M_PI / 2 - 2 / 98)) < 0.002, "part $k spans a quarter less its gap (" . round($span, 4) . ')');
        T::ok(abs($start - ($k * M_PI / 2 + 1 / 98)) < 0.002, "part $k starts at its quarter from the top (" . round($start, 4) . ')');
    }
    T::contains($html, '>4</text><text class="gk-chart-total-label"', 'the total in the hole');

    $seven = (new Chart('d7'))->type('donut')->labels(['a', 'b', 'c', 'd', 'e', 'f', 'g'])->series('n', [40, 20, 10, 10, 10, 6, 4])->html();
    T::eq(substr_count($seven, 'class="gk-chart-slice'), 6, 'five parts in colour and one Other');
    T::contains($seven, 'class="gk-chart-slice gk-chart-other" data-gk-chart-i="5"', 'the sixth slice is the grey Other');
    T::contains($seven, '<span class="gk-chart-key-label">Other</span><span class="gk-chart-key-value">10 · 10%</span>', 'Other is 6 + 4, a tenth');
    T::contains($seven, '<tr><th scope="row">g</th><td class="gk-num">4</td><td class="gk-num">4%</td></tr>', 'the table keeps every part on its own');

    $whole = (new Chart('d1'))->type('donut')->labels(['a', 'b'])->series('n', [5, 0])->html();
    T::contains($whole, 'fill-rule="evenodd"', 'one part of the whole: a full ring');
    $zero = (new Chart('d0'))->type('donut')->labels(['a', 'b'])->series('n', [0, 0])->html();
    T::contains($zero, 'class="gk-chart-track"', 'all zero: an empty ring');
    T::notContains($zero, 'gk-chart-slice', 'and no slice');
},

// ── Words ──────────────────────────────────────────────────────────────────

'what a screen reader gets: a name, a summary, a table' => function () use ($nb): void {
    Lang::set('en');
    $html = (new Chart('rev'))->type('bar')->title('Revenue')->labels(['Jan', 'Feb', 'Mar'])
        ->series('2025', [100, 80, 120])->series('2026', [110, null, 90])->format('money', ['locale' => 'en'])->html();
    T::contains($html, 'role="img" aria-labelledby="gk-chart-rev-t" aria-describedby="gk-chart-rev-d"', 'an image named and described');
    T::contains($html, '<title id="gk-chart-rev-t">Revenue</title>', 'named by the title');
    T::contains($html, '<desc id="gk-chart-rev-d">Bar chart, 3 categories from Jan to Mar. 2025: from €100.00 to €120.00, lowest €80.00 (Feb), highest €120.00 (Mar). 2026: from €110.00 to €90.00, lowest €90.00 (Mar), highest €110.00 (Jan).</desc>', 'the summary in words');
    T::contains($html, '<figcaption class="gk-chart-title">Revenue</figcaption>', 'the caption');
    T::contains($html, '<details class="gk-chart-data"><summary>Show as table</summary>', 'the table behind "Show as table"');
    T::contains($html, '<caption class="gk-sr-only">Revenue</caption>', 'the table is named');
    T::contains($html, '<li class="gk-chart-key gk-chart-c2" data-gk-chart-key="1">', 'a legend names each series');
    $one = (new Chart('one'))->type('bar')->labels(['a'])->series('s', [1])->html();
    T::notContains($one, 'gk-chart-legend', 'one series: no legend, the title names it');
    $hidden = (new Chart('h'))->type('line')->title('T', false)->table('hidden')->labels(['a'])->series('s', [1])->series('t', [2], ['hidden' => true])->html();
    T::contains($hidden, '<figcaption class="gk-chart-title gk-sr-only">T</figcaption>', 'a title for a screen reader only');
    T::contains($hidden, '<div class="gk-chart-data gk-sr-only">', "table('hidden'): a table for a screen reader only");
    T::contains($hidden, 'gk-chart-key-off', 'a hidden series is marked off in the legend');
    T::notContains($hidden, 'data-gk-chart-s="1"', 'and not drawn');
    Lang::set('de');
    $de = (new Chart('de'))->type('line')->labels(['a', 'b'])->series('s', [1, 2])->html();
    T::contains($de, 'Liniendiagramm, 2 Kategorien von a bis b.', 'the words follow the page language');
    T::contains($de, '<summary>Als Tabelle anzeigen</summary>', 'the link too');
    Lang::set('en');
},

'long names are cut with their full text kept, crowded labels thin out' => function (): void {
    Lang::set('en');
    $long = 'A category name far too long for its place on the axis';
    $html = (new Chart('l'))->type('bar')->labels([$long, 'b', 'c', 'd', 'e', 'f'])->series('s', [1, 2, 3, 4, 5, 6])->width(400)->html();
    T::ok(preg_match('#text-anchor="middle">([^<]*…)<title>' . preg_quote($long, '#') . '</title></text>#u', $html) === 1, 'cut with an ellipsis, the whole name in its <title>');
    $many = (new Chart('m'))->type('line')->labels(array_map(static fn (int $i): string => "Day $i", range(1, 31)))->series('s', range(1, 31))->width(400)->html();
    T::ok(preg_match_all('#text-anchor="middle">Day#', $many) < 31, 'thirty-one days on 400px: not every label is written');
    T::ok(preg_match_all('#text-anchor="middle">Day#', $many) >= 4, 'but some are');
    $h = (new Chart('h'))->type('bar')->horizontal()->labels(['/blog/how-we-cut-our-invoice-processing-time', 'b'])->series('s', [1, 2])->html();
    T::contains($h, 'text-anchor="end">/blog/how-we-cut-our-in…<title>', 'horizontal: the names down the left, cut to their column');
},

// ── Escaping and hostile input ─────────────────────────────────────────────

'everything is escaped' => function () use ($quiet): void {
    Lang::set('en');
    [$html] = $quiet(static fn () => (new Chart('x"><script>alert(1)</script>'))->type('bar')
        ->title('</svg><script>alert(2)</script>')
        ->description('<img src=x onerror=alert(3)>')
        ->labels(['<b onclick="x">', "'quoted' & \"double\""])
        ->series('<i>name</i>', [1, 2])->series("A&B", [3, 4])
        ->html());
    T::notContains($html, '<script', 'no script element');
    T::notContains($html, '<img', 'no image element');
    T::notContains($html, '<b ', 'no bold element');
    T::notContains($html, '<i>', 'no italic element');
    T::contains($html, '&lt;/svg&gt;&lt;script&gt;alert(2)&lt;/script&gt;', 'the title as text');
    T::contains($html, '&#039;quoted&#039; &amp; &quot;double&quot;', 'quotes and ampersands as entities');
    T::ok(preg_match('/id="gk-chart-(h[0-9a-f]{8})"/', $html, $m) === 1, 'an id with markup becomes a hash');
    T::ok(!preg_match('/data-gk-chart="[^"]*"[^ >]/', $html), 'the config attribute is closed where it should be');
    $doc = new DOMDocument();
    T::ok(@$doc->loadHTML('<?xml encoding="utf-8"?><div>' . $html . '</div>') !== false, 'the markup parses');
    $fig = $doc->getElementsByTagName('figure')->item(0);
    $cfg = json_decode((string) $fig?->getAttribute('data-gk-chart'), true);
    T::eq($cfg['title'] ?? null, '</svg><script>alert(2)</script>', 'the config attribute holds the title as it was');
},

'invalid UTF-8 and control characters are cleaned, the same way the script cleans them' => function (): void {
    $c = (new Chart('u'))->type('bar')->labels(["a\xffb\x01c\td  "])->series("s\x00x", [1])->config();
    T::eq($c['labels'][0], "a\u{FFFD}bc d", 'a broken byte becomes U+FFFD, control characters go, a tab is a space, the ends are trimmed');
    T::eq($c['series'][0]['name'], 'sx', 'a NUL is dropped');
    T::ok(json_encode($c) !== false, 'the config is valid JSON');
},

'every misuse is a warning with a fallback, never an exception and never an empty chart' => function () use ($quiet): void {
    Lang::set('en');
    $cases = [
        'unknown type' => [static fn () => (new Chart('a'))->type('pie')->labels(['a'])->series('s', [1]), 'is not a chart type'],
        'unknown format' => [static fn () => (new Chart('a'))->format('euro')->labels(['a'])->series('s', [1]), 'is not a format'],
        'bad currency' => [static fn () => (new Chart('a'))->format('money', ['currency' => 'Euro'])->labels(['a'])->series('s', [1]), 'ISO code'],
        'bad locale' => [static fn () => (new Chart('a'))->format('money', ['locale' => 'fr'])->labels(['a'])->series('s', [1]), 'is not known'],
        'bad decimals' => [static fn () => (new Chart('a'))->format('number', ['decimals' => 9])->labels(['a'])->series('s', [1]), "'decimals'"],
        'unknown format option' => [static fn () => (new Chart('a'))->format('number', ['precision' => 2])->labels(['a'])->series('s', [1]), "'precision'"],
        'not a number' => [static fn () => (new Chart('a'))->labels(['a', 'b'])->series('s', [1, 'many']), 'not a number'],
        'a bool' => [static fn () => (new Chart('a'))->labels(['a', 'b'])->series('s', [1, true]), 'not a number'],
        'not finite' => [static fn () => (new Chart('a'))->labels(['a', 'b'])->series('s', [1, NAN]), 'not finite'],
        'too large' => [static fn () => (new Chart('a'))->labels(['a', 'b'])->series('s', [1, 1e20]), '1e15'],
        'too many values' => [static fn () => (new Chart('a'))->labels(['a'])->series('s', [1, 2, 3]), 'the rest were left out'],
        'a sixth series' => [static fn () => (new Chart('a'))->labels(['a'])->series('1', [1])->series('2', [1])->series('3', [1])->series('4', [1])->series('5', [1])->series('6', [1]), 'five colours'],
        'bad colour' => [static fn () => (new Chart('a'))->labels(['a'])->series('s', [1], ['color' => 9]), "'color'"],
        'bad hidden' => [static fn () => (new Chart('a'))->labels(['a'])->series('s', [1], ['hidden' => 'yes']), "'hidden'"],
        'unknown series option' => [static fn () => (new Chart('a'))->labels(['a'])->series('s', [1], ['dash' => true]), "'dash'"],
        'stacked line' => [static fn () => (new Chart('a'))->type('line')->stacked()->labels(['a'])->series('s', [1]), 'stacked()'],
        'horizontal area' => [static fn () => (new Chart('a'))->type('area')->horizontal()->labels(['a'])->series('s', [1]), 'horizontal()'],
        'zero(false) bar' => [static fn () => (new Chart('a'))->type('bar')->zero(false)->labels(['a'])->series('s', [1]), 'zero(false)'],
        'markers on bars' => [static fn () => (new Chart('a'))->type('bar')->markers()->labels(['a'])->series('s', [1]), 'markers()'],
        'table mode' => [static fn () => (new Chart('a'))->table('accordion')->labels(['a'])->series('s', [1]), "table('accordion')"],
        'width' => [static fn () => (new Chart('a'))->width(50)->labels(['a'])->series('s', [1]), 'width(50)'],
        'height' => [static fn () => (new Chart('a'))->height(5000)->labels(['a'])->series('s', [1]), 'height(5000)'],
        'negative part' => [static fn () => (new Chart('a'))->type('donut')->labels(['a', 'b'])->series('s', [5, -2]), 'negative'],
        'two donut series' => [static fn () => (new Chart('a'))->type('donut')->labels(['a'])->series('s', [5])->series('t', [5]), 'the first'],
        'array label' => [static fn () => (new Chart('a'))->labels([['x']])->series('s', [1]), 'a label is text'],
    ];
    foreach ($cases as $name => [$build, $words]) {
        try {
            [$html, $warnings] = $quiet(static fn () => $build()->html());
        } catch (\Throwable $e) {
            T::ok(false, "$name threw " . get_class($e) . ': ' . $e->getMessage());
            continue;
        }
        T::eq(count($warnings), 1, "$name: one warning (" . implode(' | ', $warnings) . ')');
        T::contains($warnings[0] ?? '', $words, "$name: the warning says what is wrong");
        T::contains($warnings[0] ?? '', "GridKit: chart 'a':", "$name: the warning names the chart");
        T::contains($html, '<figure class="gk-chart', "$name: the chart is still drawn");
    }
    [$html, $w] = $quiet(static fn () => (new Chart('a'))->labels(['a', 'b'])->series('s', [1, 1e20])->html());
    T::contains($html, '1000000000000000', 'a value beyond 1e15 is cut to it');
    // The config is worked out once: html() twice warns once.
    $c = (new Chart('a'))->labels(['a', 'b'])->series('s', [1, 'x']);
    [, $w] = $quiet(static function () use ($c): string { return $c->html() . $c->html() . json_encode($c->config()); });
    T::eq(count($w), 1, 'a warning is given once, however often the chart is drawn');
},

'the config: decimal strings, the order the script keeps' => function (): void {
    Lang::set('de');
    $c = (new Chart('k'))->type('area')->labels(['a', 'b', 'c'])->series('s', [1, '2.50', 1e-12])->config();
    T::eq(array_keys($c), ['id', 'type', 'title', 'showTitle', 'desc', 'labels', 'series', 'format', 'width', 'height', 'stacked',
        'horizontal', 'zero', 'markers', 'dashes', 'table', 'area'], 'the keys, in the order the script writes them');
    T::eq($c['series'][0]['values'], ['1', '2.5', '0'], 'values as decimal strings; below 1e-9 a value is 0');
    T::eq($c['format']['locale'], 'de', 'the locale defaults to the page language');
    T::eq(array_keys($c['series'][0]), ['name', 'values', 'color', 'hidden'], 'a series: name, values, color, hidden');
    Lang::set('en');
},

// ── Sparklines and StatCards ───────────────────────────────────────────────

'a sparkline: a line, its last point and its numbers as its name' => function () use ($nb): void {
    Lang::set('en');
    $s = Chart::sparkline([3, 5, null, 4, 8], ['label' => 'Sign-ups', 'color' => 2, 'area' => true]);
    T::ok(str_starts_with($s, '<svg class="gk-chart-spark gk-chart-c2" viewBox="0 0 96 28" width="96" height="28" role="img"><title>'), 'an image of 96 × 28 in slot 2');
    T::contains($s, '<title>Sign-ups: 4 values from 3 to 8, lowest 3, highest 8.</title>', 'named by its numbers');
    T::contains($s, 'class="gk-chart-area"', 'a wash under the line when asked');
    T::contains($s, '<circle class="gk-chart-dot gk-chart-dot-on" cx="93" cy="3" r="2.5"/>', 'a dot on the last value, at the top right');
    T::eq(substr_count(Chart::sparkline([1, 2, null, 3, 4]), 'M'), 2, 'a gap breaks it');
    T::contains(Chart::sparkline([]), '<title>Trend: no data.</title>', 'nothing: says so');
    T::contains(Chart::sparkline([1234.5], ['format' => 'money', 'locale' => 'de', 'label' => 'Umsatz']), "from 1.234,50{$nb}€", 'written in its format');
},

'StatCards draws a sparkline only when a card asks for one' => function () use ($nb): void {
    Lang::set('en');
    $plain = T::capture(static fn () => (new StatCards('s'))->card('Revenue', 12450.8, ['format' => 'currency', 'trend' => '+12%'])->render());
    T::notContains($plain, 'gk-chart', 'a card without the option is drawn as before');
    T::eq($plain, '<div class="gk-stat-cards" data-gk-stats="s"><div class="gk-stat-card"><div class="gk-stat-content"><span class="gk-stat-label">Revenue</span><span class="gk-stat-value">€12,450.80</span><span class="gk-stat-trend gk-stat-trend-up">+12%</span></div></div></div>', 'byte for byte the card of 1.118');
    $with = T::capture(static fn () => (new StatCards('s'))->card('Revenue', 16900, ['format' => 'currency', 'sparkline' => [8200, 16900]])->render());
    T::ok(preg_match('#<div class="gk-stat-content">.*<svg class="gk-chart-spark gk-chart-c1"[^>]*><title>Revenue: 2 values from €8,200.00 to €16,900.00, lowest €8,200.00, highest €16,900.00\.</title>.*</svg></div>#s', $with) === 1, 'under the figure, named by the label, in the card\'s format');
    $tile = T::capture(static fn () => (new StatCards('t'))->compact()->card('open', 12, ['sparkline' => ['values' => [1, 2, 3], 'color' => 4]])->render());
    T::contains($tile, '<svg class="gk-chart-spark gk-chart-c4"', 'a tile takes it too, with its options');
},

// ── Seams ──────────────────────────────────────────────────────────────────

'the script has the same words, numbers and constants' => function () use ($root): void {
    $js = (string) file_get_contents($root . '/js/chart.js');
    $en = require $root . '/lang/en.php';
    preg_match('/var EN = \{(.*?)\n  \};/s', $js, $m);
    preg_match_all('/^\s*(\w+): "((?:[^"\\\\]|\\\\.)*)",?$/m', $m[1] ?? '', $pairs, PREG_SET_ORDER);
    $script = [];
    foreach ($pairs as [, $k, $v]) $script[$k] = json_decode('"' . $v . '"');
    $php = [];
    foreach ($en as $k => $v) if (str_starts_with($k, 'chart.')) $php[substr($k, 6)] = $v;
    T::eq($script, $php, "js/chart.js's English words are lang/en.php's chart.*");
    Lang::set('de');
    T::contains(Lang::jsConfig(), '"chart_show_table":"Als Tabelle anzeigen"', 'Lang::jsConfig() hands chart.* to the script as chart_*');
    Lang::set('en');
    foreach (['CH = 7', 'GAP = 2', 'BAR_MAX = 24', 'RADIUS = 4', 'SLOTS = 5', 'MAX_LABELS = 2000', 'MAX_ABS = 1e15', 'MIN_ABS = 1e-9'] as $const) {
        T::contains($js, $const, "js/chart.js: $const");
        T::ok(preg_match('/const ' . str_replace(' = ', ' = ', preg_quote($const, '/')) . ';/', (string) file_get_contents($root . '/src/Chart.php')) === 1, "src/Chart.php: $const");
    }
    T::contains($js, 'en: [".", ",", "before", "%", ["K", "M", "B"]]', 'English number shapes');
    T::contains($js, 'de: [",", ".", "after", "\\u00a0%", ["\\u00a0Tsd.", "\\u00a0Mio.", "\\u00a0Mrd."]]', 'German number shapes');
    if (trim((string) shell_exec('command -v node 2>/dev/null')) !== '') {
        exec('node --check ' . escapeshellarg($root . '/js/chart.js') . ' 2>&1', $out, $rc);
        T::eq($rc, 0, 'js/chart.js parses: ' . implode(' ', $out));
    }
},

'the script is loaded where the other scripts are, and the stylesheet ends with the chart' => function () use ($root): void {
    $skeleton = (string) file_get_contents($root . '/skeleton.php');
    T::ok(strpos($skeleton, "js/chart.js') ?>\" defer></script>") > strpos($skeleton, "js/tree.js') ?>\" defer></script>"), 'skeleton.php loads js/chart.js after js/tree.js, deferred');
    $demo = demoSource();
    T::contains($demo, "Layout::asset('../js/chart.js') ?>\" defer></script>", 'the demo loads it');
    T::contains($demo, "->item('Charts', '#charts', 'bar_chart')", 'the demo has a Charts item');
    T::ok(strpos($demo, "->item('Charts'") > strpos($demo, "->item('Line items'"), 'after Line items');
    T::contains($demo, "data-section=\"charts\"", 'and a Charts section');
    foreach (['line', 'area', 'bar', 'donut'] as $type) T::contains($demo, "->type('$type')", "the demo shows a $type chart");
    T::contains($demo, 'Chart::sparkline(', 'and a sparkline');

    $css = (string) file_get_contents($root . '/css/gridkit.css');
    $at = strpos($css, '/* === 1.121.0 — chart === */');
    T::ok($at !== false, 'the block is headed /* === 1.121.0 — chart === */');
    $block = substr($css, (int) $at);
    T::eq(preg_match_all('#/\* === 1\.\d+\.\d+ #', $block), 1, 'it is the last block of the stylesheet');
    T::eq(preg_match_all('/^\.gk-chart \{/m', $css), 1, 'and the only place .gk-chart is styled');
    foreach (['[data-gk-mode="dark"] :is(.gk-chart, .gk-chart-spark)', '.gk-dark :is(.gk-chart, .gk-chart-spark)',
              '@media (prefers-reduced-motion: no-preference)', '@media print', '@media (forced-colors: active)',
              '@supports (color: color-mix(in oklab, red, blue))'] as $part) {
        T::contains($block, $part, "the block has $part");
    }
    T::ok(!preg_match('/#[0-9a-f]{3,6}\b/i', preg_replace('#/\*.*?\*/#s', '', (string) preg_replace('/@supports.*$/s', '', substr($block, strpos($block, '--gk-chart-5: #c56c8f;') + 22)))), 'no colour literal after the fallback palette — roles only');
    $index = json_decode((string) file_get_contents($root . '/css/blocks.json'), true);
    preg_match_all('/\.(gk-chart[a-z0-9-]*)/', (string) preg_replace('#/\*.*?\*/#s', '', $block), $m);
    $classes = array_values(array_unique($m[1]));
    sort($classes);
    $listed = array_keys($index['blocks']['chart']['classes'] ?? []);
    T::eq($listed, $classes, 'css/blocks.json lists every class of the block under "chart"');
    T::eq(array_unique(array_values($index['blocks']['chart']['classes'] ?? [])), ['1.121.0'], 'all at 1.121.0');
},

'the fallback palette (a browser without color-mix) clears 3:1 too' => function () use ($root): void {
    $css = (string) file_get_contents($root . '/css/gridkit.css');
    $block = substr($css, (int) strpos($css, '/* === 1.121.0 — chart === */'));
    preg_match('/\.gk-chart,\n\.gk-chart-spark \{(.*?)\}/s', $block, $m);
    preg_match_all('/--gk-chart-(\d): (#[0-9a-f]{6});/', $m[1] ?? '', $lit, PREG_SET_ORDER);
    T::eq(count($lit), 5, 'five literal colours before the @supports block');
    $lum = static function (string $hex): float {
        $c = array_map(static fn (string $h): float => hexdec($h) / 255, str_split(substr($hex, 1), 2));
        $c = array_map(static fn (float $v): float => $v <= 0.04045 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4, $c);
        return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
    };
    $ratio = static fn (string $a, string $b): float => (max($lum($a), $lum($b)) + 0.05) / (min($lum($a), $lum($b)) + 0.05);
    // White (a card) and the light hover step of gridkit.css's own ladder, the darkest light ground.
    preg_match('/--gk-surface-container-high: (#[0-9a-f]{6});/', $css, $hover);
    foreach ($lit as [, $n, $hex]) {
        T::ok($ratio($hex, '#ffffff') >= 3 && $ratio($hex, $hover[1] ?? '#ffffff') >= 3,
            "--gk-chart-$n: $hex is " . round($ratio($hex, $hover[1] ?? '#ffffff'), 2) . ':1 on the hover ground');
    }
    preg_match('/@supports \(color: color-mix\(in oklab, red, blue\)\) \{\s*\.gk-chart,\s*\.gk-chart-spark \{(.*?)\}/s', $block, $d);
    foreach ([2 => 94, 3 => 87, 4 => 75, 5 => 85] as $n => $p) {
        T::contains($d[1] ?? '', "--gk-chart-$n: color-mix(in oklab, var(--gk-series-$n) $p%, var(--gk-on-surface));", "slot $n is derived from the roles");
    }
},

'English output stays English' => function (): void {
    Lang::set('en');
    $html = (new Chart('e'))->type('bar')->stacked()->labels(['a', 'b'])->series('x', [1, 2])->series('y', [3, null])->html()
        . (new Chart('d'))->type('donut')->labels(['a', 'b', 'c', 'd', 'e', 'f'])->series('x', [1, 2, 3, 4, 5, 6])->html()
        . (new Chart('n'))->type('line')->html() . Chart::sparkline([]);
    foreach (['Diagramm', 'Kategorie', 'Gesamt', 'Sonstige', 'Tabelle', 'Keine', 'Reihe', 'Verlauf', 'Tsd.'] as $word) {
        T::notContains($html, $word, "no German in English output: $word");
    }
},

];
})();
