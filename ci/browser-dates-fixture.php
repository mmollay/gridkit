<?php
/**
 * The page ci/browser-dates.js drives (GridKit\DateRange and GridKit\Calendar, 1.120.0). One
 * self-contained HTML document — both stylesheets and both scripts inlined — that the check serves
 * from http://gk.test/ with page.route(), so a month link (http://gk.test/cal?month=…) loads this page
 * again for that month, in the same origin, and the focus can follow. Today is 4 November 2026.
 *
 *     php ci/browser-dates-fixture.php                   the page
 *     php ci/browser-dates-fixture.php --month=2026-12   the page, the calendar on another month
 *     php ci/browser-dates-fixture.php --lang=de         the page in German
 *     php ci/browser-dates-fixture.php --read 'period[preset]=…&period[from]=…'
 *                                                        what DateRange::fromRequest() reads from a query
 *     php ci/browser-dates-fixture.php --parity          presetRange() and text() for the parity checks (JSON)
 */

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use GridKit\{Calendar, DateRange, Form, Lang, TableHeader};

const TODAY = '2026-11-04';

$args = [];
foreach (array_slice($argv, 1) as $i => $a) {
    if (preg_match('/^--(\w+)=(.*)$/s', $a, $m)) $args[$m[1]] = $m[2];
    elseif (str_starts_with($a, '--')) $args[substr($a, 2)] = $argv[$i + 2] ?? '';
}
$root = dirname(__DIR__);
Lang::set(($args['lang'] ?? 'en') === 'de' ? 'de' : 'en');

$filter = static fn (): DateRange => (new DateRange('period'))
    ->presets(['today', 'last-7-days', 'last-30-days', 'this-month', 'last-month', 'this-quarter', 'this-year'])
    ->default('this-month')->today(TODAY)->min('2020-01-01')->max('2026-12-31')
    ->filter()->baseUrl('http://gk.test/list')->preserve(['status']);

// ── What the server reads from a query the browser sent ──────────────────────
if (isset($args['read'])) {
    parse_str($args['read'], $q);
    $which = $args['name'] ?? 'period';
    $opts = $which === 'period'
        ? ['presets' => ['today', 'last-7-days', 'last-30-days', 'this-month', 'last-month', 'this-quarter', 'this-year'], 'min' => '2020-01-01', 'max' => '2026-12-31']
        : [];
    echo json_encode(DateRange::fromRequest($which, $which === 'report' ? 'last-month' : 'this-month', $opts + ['today' => TODAY, 'request' => $q]));
    exit;
}

// ── The answers the browser must give too ────────────────────────────────────
if (isset($args['parity'])) {
    $todays = ['2026-11-04', '2026-01-01', '2026-12-31', '2026-03-31', '2026-03-01', '2026-04-01', '2026-06-30', '2026-07-01',
        '2026-09-30', '2026-10-01', '2028-02-29', '2028-03-01', '2028-03-29', '2000-03-01', '2100-03-01', '1900-03-01',
        '0001-01-01', '0001-03-15', '9999-12-31', '2026-03-29', '2026-10-25', '2027-01-01', '2026-02-28', '2024-02-29'];
    $presets = [];
    foreach ($todays as $t) {
        foreach (DateRange::PRESETS as $p) {
            if ($p !== 'custom') $presets[] = ['today' => $t, 'preset' => $p, 'range' => DateRange::presetRange($p, $t)];
        }
    }
    $pairs = [['2026-10-01', '2026-10-31'], ['2026-09-28', '2026-10-04'], ['2025-12-29', '2026-01-04'], ['2026-10-01', '2026-10-01'],
        ['2026-10-31', '2026-10-01'], ['0001-01-01', '9999-12-31'], ['2028-02-29', '2028-03-01'], ['2026-01-01', '2026-12-31'],
        ['2026-03-01', '2026-05-31'], ['2026-02-30', '2026-03-01'], ['', '2026-03-01']];
    mt_srand(1120);
    for ($i = 0; $i < 60; $i++) {
        $a = sprintf('%04d-%02d-%02d', mt_rand(1990, 2040), mt_rand(1, 12), mt_rand(1, 28));
        $b = sprintf('%04d-%02d-%02d', mt_rand(1990, 2040), mt_rand(1, 12), mt_rand(1, 28));
        $pairs[] = [$a, $b];
    }
    $texts = [];
    foreach (['en', 'de'] as $l) {
        Lang::set($l);
        foreach ($pairs as [$a, $b]) $texts[] = ['lang' => $l, 'from' => $a, 'to' => $b, 'text' => DateRange::text($a, $b)];
    }
    echo json_encode(['presets' => $presets, 'texts' => $texts], JSON_UNESCAPED_UNICODE);
    exit;
}

$capture = static function (callable $fn): string {
    ob_start();
    $fn();
    return (string) ob_get_clean();
};

// 1. A filter bar: DateRange stand-alone, its own GET form, a kept parameter.
$_GET = ['status' => 'open'];
$filterHtml = $capture(fn () => TableHeader::make('inv')->filter(fn () => $filter()->render())->render());
$_GET = [];

// 2. In a Form: the field type daterange.
$formHtml = $capture(fn () => (new Form('report-form'))->method('get')->action('http://gk.test/report')
    ->field('report', 'Report period', 'daterange', ['presets' => ['last-7-days', 'last-30-days', 'last-month', 'last-year'],
        'default' => 'last-month', 'today' => TODAY, 'max' => TODAY, 'required' => true])
    ->submit('Show')
    ->render());

// 3. Every preset, in a page's own form; and one in German for the words.
$plainHtml = '<form id="plain-form" method="get" action="http://gk.test/plain">'
    . (new DateRange('plain'))->today(TODAY)->label('Plain period')->html()
    . '<button type="submit" id="plain-submit">Send</button></form>';
Lang::set('de');
$deHtml = (new DateRange('de-range'))->today(TODAY)->html();
Lang::set(($args['lang'] ?? 'en') === 'de' ? 'de' : 'en');

// 4. A month of appointments.
$month = $args['month'] ?? '2026-11';
$cal = (new Calendar('cal'))->label('Appointments')->month($month)->today(TODAY)->weekNumbers()->weekStart('sunday')
    ->baseUrl('http://gk.test/cal')->preserve(['user' => '7'])->dayUrl('http://gk.test/day?date={date}')
    ->event('2026-11-04 09:30', 'Team meeting', ['href' => 'http://gk.test/e/1', 'color' => 'blue', 'id' => 'm1'])
    ->event('2026-11-04 14:00', 'Call with <Acme>', ['color' => 'green', 'id' => 'call'])
    ->event('2026-11-06', 'Invoice run', ['color' => 'red', 'allDay' => true, 'href' => 'http://gk.test/e/2'])
    ->event(['start' => '2026-11-07', 'end' => '2026-11-10'], 'Trade fair', ['color' => 'orange', 'href' => 'http://gk.test/e/3', 'id' => 'fair'])
    ->event(['start' => '2026-11-08', 'end' => '2026-11-18'], 'Winter campaign', ['href' => 'http://gk.test/e/4', 'id' => 'campaign'])
    ->event(['start' => '2026-11-07', 'end' => '2026-11-08'], 'Set-up', ['color' => 'gray', 'id' => 'setup']);
foreach (['08:00' => 'Breakfast', '10:00' => 'Release', '11:30' => 'Dentist', '15:00' => 'Review', '17:00' => 'Yoga'] as $t => $title) {
    $cal->event("2026-11-12 $t", $title, ['href' => 'http://gk.test/e/' . strtolower($title), 'id' => strtolower($title)]);
}
$cal->event('2026-11-20', 'Gray day', ['color' => 'gray'])->event('2026-11-27', 'Accent day');
// A second busy day: two "+n more" on one page, one open at a time.
foreach (['One', 'Two', 'Three', 'Four'] as $n => $title) $cal->event('2026-11-25 1' . $n . ':00', $title, ['href' => 'http://gk.test/e/' . $title]);
$calHtml = $cal->html();

// 5. The same month in a narrow column: the agenda.
$narrowHtml = (new Calendar('narrow'))->month('2026-11')->today(TODAY)->weekStart('monday')->baseUrl('http://gk.test/cal')
    ->event('2026-11-04 09:30', 'Team meeting', ['href' => 'http://gk.test/e/1', 'color' => 'blue'])
    ->event(['start' => '2026-11-07', 'end' => '2026-11-09'], 'Trade fair', ['color' => 'orange'])
    ->html();
// 6. An empty month in a narrow column.
$emptyHtml = (new Calendar('empty'))->month('2026-02')->today(TODAY)->html();

echo '<!doctype html><html lang="' . Lang::locale() . '"><head><meta charset="utf-8">'
   . '<meta name="viewport" content="width=device-width, initial-scale=1"><title>Dates</title>'
   . '<style>' . file_get_contents($root . '/css/gridkit.css') . '</style>'
   . '<style>' . file_get_contents($root . '/css/themes.css') . '</style>'
   . '<style>body{background:var(--gk-surface);color:var(--gk-on-surface);margin:0;padding:12px;font-family:var(--gk-font)}'
   . 'h3{font-size:13px;margin:18px 0 6px}section{max-width:1100px}.narrow{max-width:380px}</style>'
   . '</head><body class="gk-root">' . Lang::jsConfig()
   . '<button id="before" type="button">before</button>'
   . '<section id="s-filter"><h3>Filter</h3>' . $filterHtml . '</section>'
   . '<section id="s-form"><h3>Form</h3>' . $formHtml . '</section>'
   . '<section id="s-plain"><h3>Plain</h3>' . $plainHtml . '</section>'
   . '<section id="s-de"><h3>Deutsch</h3>' . $deHtml . '</section>'
   . '<section id="s-cal"><h3>Calendar</h3>' . $calHtml . '</section>'
   . '<button id="after" type="button">after</button>'
   . '<section id="s-narrow" class="narrow"><h3>Agenda</h3>' . $narrowHtml . '</section>'
   . '<section id="s-empty" class="narrow"><h3>Empty</h3>' . $emptyHtml . '</section>'
   . '<script>' . file_get_contents($root . '/js/gridkit.js') . '</script>'
   . '<script>' . file_get_contents($root . '/js/calendar.js') . '</script>'
   . '</body></html>';
