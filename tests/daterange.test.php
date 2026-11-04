<?php
/**
 * GridKit\DateRange (1.120.0) — a period picker for reports and invoice lists.
 *
 * What the server decides is checked here: the days every preset covers on the edges of months,
 * quarters and years, in leap years and around a change of clock (a day of 23 or 25 hours must change
 * nothing), "today" in a time zone, how a request is read (a preset wins, dates typed over without a
 * script win over it, reversed dates are swapped, everything held within min and max), what a hostile
 * request becomes (the default, without a warning), the period in words in both languages, the markup
 * (names, the group, the hidden field the no-script rule rests on, the filter form, the Form field),
 * escaping, the warnings, and the contract with js/calendar.js and the stylesheet. What the script does
 * with the markup — the same days as presetRange(), the same words as text() — needs a browser:
 * ci/browser-dates.js.
 */

declare(strict_types=1);

use GridKit\{DateRange, Form, Lang};

require_once __DIR__ . '/css-helpers.php';

/** Run, and collect what it said with E_USER_WARNING. @return array{0:mixed,1:list<string>} */
function drRun(callable $fn): array
{
    $notes = [];
    set_error_handler(static function (int $no, string $msg) use (&$notes): bool {
        if ($no !== E_USER_WARNING) return false;
        $notes[] = $msg;
        return true;
    });
    try {
        ob_start();
        try { $out = $fn(); } finally { $printed = (string) ob_get_clean(); }
    } finally {
        restore_error_handler();
    }
    return [$out ?? $printed, $notes];
}

/** fromRequest() on a request array, today fixed. */
function drRead(array $period, array $opts = [], string|array $default = 'this-month'): array
{
    return DateRange::fromRequest('period', $default, $opts + ['today' => '2026-11-04', 'request' => ['period' => $period]]);
}

/** @return array<string,callable> */
return (static function (): array {

$root = __DIR__ . '/..';
$js   = static fn (): string => (string) @file_get_contents($root . '/js/calendar.js');
$doc  = static fn (): string => (string) file_get_contents($root . '/GRIDKIT_SKILL.md');
/** The dates block of the stylesheet, comments stripped. */
$sheet = static function (): string {
    $css = gkCssText();
    $from = strpos($css, '=== 1.120.0 — dates ===');
    if ($from === false) return '';
    $next = strpos($css, "\n/* === ", $from + 10);
    $block = $next === false ? substr($css, $from) : substr($css, $from, $next - $from);
    return (string) preg_replace('#/\*.*?\*/#s', '', $block);
};

return [

// ── The presets ─────────────────────────────────────────────────────────

'every preset on an ordinary day' => function (): void {
    $want = [
        'today'        => ['2026-11-04', '2026-11-04'],
        'yesterday'    => ['2026-11-03', '2026-11-03'],
        'last-7-days'  => ['2026-10-29', '2026-11-04'],
        'last-30-days' => ['2026-10-06', '2026-11-04'],
        'this-month'   => ['2026-11-01', '2026-11-30'],
        'last-month'   => ['2026-10-01', '2026-10-31'],
        'this-quarter' => ['2026-10-01', '2026-12-31'],
        'last-quarter' => ['2026-07-01', '2026-09-30'],
        'this-year'    => ['2026-01-01', '2026-12-31'],
        'last-year'    => ['2025-01-01', '2025-12-31'],
    ];
    foreach ($want as $preset => [$from, $to]) {
        T::eq(DateRange::presetRange($preset, '2026-11-04'), ['from' => $from, 'to' => $to], $preset);
    }
    T::eq(DateRange::presetRange('custom', '2026-11-04'), null, 'custom covers no days of its own');
    T::eq(DateRange::presetRange('next-week', '2026-11-04'), null, 'an unknown preset is null');
    T::eq(count(DateRange::PRESETS), 11, 'eleven presets, custom included');
},

'presets on the edges of months, quarters and years' => function (): void {
    $cases = [
        // today, preset, from, to
        ['2026-01-01', 'yesterday', '2025-12-31', '2025-12-31'],
        ['2026-01-01', 'last-month', '2025-12-01', '2025-12-31'],
        ['2026-01-01', 'last-quarter', '2025-10-01', '2025-12-31'],
        ['2026-01-01', 'this-quarter', '2026-01-01', '2026-03-31'],
        ['2026-01-01', 'last-7-days', '2025-12-26', '2026-01-01'],
        ['2026-01-01', 'last-year', '2025-01-01', '2025-12-31'],
        ['2026-12-31', 'this-quarter', '2026-10-01', '2026-12-31'],
        ['2026-12-31', 'last-30-days', '2026-12-02', '2026-12-31'],
        ['2026-03-31', 'last-month', '2026-02-01', '2026-02-28'],
        ['2026-03-31', 'this-quarter', '2026-01-01', '2026-03-31'],
        ['2026-04-01', 'this-quarter', '2026-04-01', '2026-06-30'],
        ['2026-04-01', 'last-quarter', '2026-01-01', '2026-03-31'],
        ['2026-07-01', 'last-quarter', '2026-04-01', '2026-06-30'],
        ['2026-09-30', 'this-quarter', '2026-07-01', '2026-09-30'],
        ['2026-10-01', 'last-quarter', '2026-07-01', '2026-09-30'],
        ['2026-05-31', 'this-month', '2026-05-01', '2026-05-31'],
        ['2026-06-01', 'last-month', '2026-05-01', '2026-05-31'],
        ['2026-08-01', 'yesterday', '2026-07-31', '2026-07-31'],
        ['2026-03-01', 'yesterday', '2026-02-28', '2026-02-28'],
        ['2026-03-01', 'last-7-days', '2026-02-23', '2026-03-01'],
    ];
    foreach ($cases as [$today, $preset, $from, $to]) {
        T::eq(DateRange::presetRange($preset, $today), ['from' => $from, 'to' => $to], "$preset on $today");
    }
},

'leap years: 2028 and 2000 have a 29 February, 2100 and 1900 do not' => function (): void {
    $cases = [
        ['2028-03-01', 'last-month', '2028-02-01', '2028-02-29'],
        ['2028-03-01', 'yesterday', '2028-02-29', '2028-02-29'],
        ['2028-03-01', 'last-7-days', '2028-02-24', '2028-03-01'],
        ['2028-02-29', 'this-month', '2028-02-01', '2028-02-29'],
        ['2028-02-29', 'last-year', '2027-01-01', '2027-12-31'],
        ['2028-02-29', 'this-quarter', '2028-01-01', '2028-03-31'],
        ['2028-03-29', 'last-30-days', '2028-02-29', '2028-03-29'],
        ['2026-03-29', 'last-30-days', '2026-02-28', '2026-03-29'],
        ['2000-03-15', 'last-month', '2000-02-01', '2000-02-29'],
        ['2100-03-15', 'last-month', '2100-02-01', '2100-02-28'],
        ['1900-03-01', 'yesterday', '1900-02-28', '1900-02-28'],
        ['2024-12-31', 'this-year', '2024-01-01', '2024-12-31'],
    ];
    foreach ($cases as [$today, $preset, $from, $to]) {
        T::eq(DateRange::presetRange($preset, $today), ['from' => $from, 'to' => $to], "$preset on $today");
    }
    T::eq(DateRange::date('2028-02-29'), '2028-02-29', 'a leap day is a day');
    T::eq(DateRange::date('2026-02-29'), null, 'in 2026 it is not');
    T::eq(DateRange::date('2100-02-29'), null, 'nor in 2100');
    T::eq(DateRange::date('2000-02-29'), '2000-02-29', 'but in 2000');
},

'the years 1 and 9999: as far as a date field goes, no further' => function (): void {
    T::eq(DateRange::presetRange('yesterday', '0001-01-01'), ['from' => '0001-01-01', 'to' => '0001-01-01'], 'no day 0');
    T::eq(DateRange::presetRange('last-year', '0001-06-01'), ['from' => '0001-01-01', 'to' => '0001-01-01'], 'no year 0');
    T::eq(DateRange::presetRange('this-year', '9999-12-31'), ['from' => '9999-01-01', 'to' => '9999-12-31'], 'the last year');
    T::eq(DateRange::presetRange('last-30-days', '0001-01-10'), ['from' => '0001-01-01', 'to' => '0001-01-10'], 'held at the first day');
},

'a day of 23 or 25 hours changes nothing: the steps are taken on the calendar' => function (): void {
    $saved = date_default_timezone_get();
    try {
        // Vienna: clocks forward on 29 March 2026, back on 25 October. Santiago: midnight does not
        // exist on 6 September 2026 (00:00 → 01:00). Lord Howe moves half an hour.
        foreach (['Europe/Vienna', 'America/Santiago', 'Australia/Lord_Howe', 'America/Havana', 'UTC'] as $tz) {
            date_default_timezone_set($tz);
            T::eq(DateRange::presetRange('last-7-days', '2026-03-31'), ['from' => '2026-03-25', 'to' => '2026-03-31'], "$tz: seven days over the spring change");
            T::eq(DateRange::presetRange('last-7-days', '2026-10-28'), ['from' => '2026-10-22', 'to' => '2026-10-28'], "$tz: seven days over the autumn change");
            T::eq(DateRange::presetRange('yesterday', '2026-09-07'), ['from' => '2026-09-06', 'to' => '2026-09-06'], "$tz: the day whose midnight does not exist");
            T::eq(DateRange::presetRange('yesterday', '2026-03-30'), ['from' => '2026-03-29', 'to' => '2026-03-29'], "$tz: the day of 23 hours");
            T::eq(DateRange::presetRange('last-30-days', '2026-04-05'), ['from' => '2026-03-07', 'to' => '2026-04-05'], "$tz: thirty days");
            T::eq(DateRange::presetRange('this-month', '2026-10-25'), ['from' => '2026-10-01', 'to' => '2026-10-31'], "$tz: the month with a day of 25 hours");
        }
    } finally {
        date_default_timezone_set($saved);
    }
},

'"today" is today in the time zone, not on the server\'s clock' => function (): void {
    // 23:30 UTC on 28 March is 00:30 on the 29th in Vienna (UTC+1) and 19:30 on the 28th in New York.
    $at = new DateTimeImmutable('2026-03-28T23:30:00Z');
    T::eq(DateRange::presetRange('today', $at, 'Europe/Vienna'), ['from' => '2026-03-29', 'to' => '2026-03-29'], 'Vienna is a day ahead');
    T::eq(DateRange::presetRange('today', $at, 'America/New_York'), ['from' => '2026-03-28', 'to' => '2026-03-28'], 'New York is not');
    T::eq(DateRange::presetRange('today', $at, 'Pacific/Kiritimati'), ['from' => '2026-03-29', 'to' => '2026-03-29'], 'UTC+14');
    T::eq(DateRange::presetRange('this-month', new DateTimeImmutable('2026-10-31T23:30:00Z'), 'Europe/Vienna'),
        ['from' => '2026-11-01', 'to' => '2026-11-30'], 'the month turns at Vienna\'s midnight');
    // The same through a picker and through fromRequest().
    $r = (new DateRange('p'))->timezone('Asia/Tokyo')->today(new DateTimeImmutable('2026-12-31T16:00:00Z'))->default('this-year')->current();
    T::eq([$r['from'], $r['to']], ['2027-01-01', '2027-12-31'], 'in Tokyo it is already next year');
    $r = DateRange::fromRequest('p', 'today', ['timezone' => 'Pacific/Pago_Pago', 'today' => new DateTimeImmutable('2026-01-01T05:00:00Z'), 'request' => []]);
    T::eq($r['from'], '2025-12-31', 'in Pago Pago (UTC-11) it is still the last day of the year');
    // Without today(): the real today in the zone.
    $now = (new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d');
    T::eq(DateRange::fromRequest('p', 'today', ['timezone' => 'Asia/Kolkata', 'request' => []])['from'], $now, 'today, Kolkata');
},

// ── Reading a request ───────────────────────────────────────────────────

'a preset wins, and is counted from today again' => function (): void {
    T::eq(drRead(['preset' => 'last-month']), ['from' => '2026-10-01', 'to' => '2026-10-31', 'preset' => 'last-month'], 'a link with only a preset');
    // The script left name[shown] out; the dates it filled agree with the preset.
    T::eq(drRead(['preset' => 'last-month', 'from' => '2026-10-01', 'to' => '2026-10-31']),
        ['from' => '2026-10-01', 'to' => '2026-10-31', 'preset' => 'last-month'], 'with the script');
    // A bookmark from a month ago: preset, the dates of then, shown = the dates of then.
    T::eq(drRead(['preset' => 'this-month', 'from' => '2026-09-01', 'to' => '2026-09-30', 'shown' => '2026-09-01/2026-09-30']),
        ['from' => '2026-11-01', 'to' => '2026-11-30', 'preset' => 'this-month'], 'a bookmarked "this month" is this month');
    // Without script: the select was changed, the dates are still those shown.
    T::eq(drRead(['preset' => 'last-quarter', 'from' => '2026-11-01', 'to' => '2026-11-30', 'shown' => '2026-11-01/2026-11-30']),
        ['from' => '2026-07-01', 'to' => '2026-09-30', 'preset' => 'last-quarter'], 'no script, the select changed');
    // Without script: the dates were typed over, the select left as it was.
    T::eq(drRead(['preset' => 'this-month', 'from' => '2026-11-03', 'to' => '2026-11-20', 'shown' => '2026-11-01/2026-11-30']),
        ['from' => '2026-11-03', 'to' => '2026-11-20', 'preset' => 'custom'], 'no script, the dates typed over');
    // With the script a preset is never posted with dates of another period; when it is (no script, a
    // preset chosen AND dates typed), the typed dates win: they are what was done last, field by field.
    T::eq(drRead(['preset' => 'last-year', 'from' => '2026-02-01', 'to' => '2026-02-10', 'shown' => '2026-11-01/2026-11-30'])['preset'],
        'custom', 'both changed: the dates');
},

'custom dates, reversed dates, missing dates' => function (): void {
    T::eq(drRead(['preset' => 'custom', 'from' => '2026-02-01', 'to' => '2026-02-14']),
        ['from' => '2026-02-01', 'to' => '2026-02-14', 'preset' => 'custom'], 'custom');
    T::eq(drRead(['from' => '2026-02-01', 'to' => '2026-02-14']),
        ['from' => '2026-02-01', 'to' => '2026-02-14', 'preset' => 'custom'], 'dates without a preset are custom');
    T::eq(drRead(['preset' => 'custom', 'from' => '2026-02-14', 'to' => '2026-02-01']),
        ['from' => '2026-02-01', 'to' => '2026-02-14', 'preset' => 'custom'], 'an end before the start is swapped');
    T::eq(drRead(['preset' => 'custom', 'from' => '2026-02-14', 'to' => '']),
        ['from' => '2026-11-01', 'to' => '2026-11-30', 'preset' => 'this-month'], 'one date missing: the default');
    T::eq(drRead(['preset' => 'custom', 'from' => '2026-02-14', 'to' => '2026-02-14']),
        ['from' => '2026-02-14', 'to' => '2026-02-14', 'preset' => 'custom'], 'one day');
    T::eq(drRead([], [], ['from' => '2026-01-05', 'to' => '2026-01-09']),
        ['from' => '2026-01-05', 'to' => '2026-01-09', 'preset' => 'custom'], 'a default of two days');
    T::eq(drRead([], [], 'last-30-days')['from'], '2026-10-06', 'a default preset');
    T::eq(DateRange::fromRequest('nothing', 'this-year', ['today' => '2026-11-04', 'request' => []])['from'], '2026-01-01', 'no request at all');
},

'min and max hold every date, the presets too' => function (): void {
    $o = ['min' => '2026-01-01', 'max' => '2026-11-04'];
    T::eq(drRead(['preset' => 'this-month'], $o), ['from' => '2026-11-01', 'to' => '2026-11-04', 'preset' => 'this-month'], 'this month so far');
    T::eq(drRead(['preset' => 'custom', 'from' => '2025-06-01', 'to' => '2026-02-01'], $o)['from'], '2026-01-01', 'a start before min');
    T::eq(drRead(['preset' => 'custom', 'from' => '2026-06-01', 'to' => '2030-01-01'], $o)['to'], '2026-11-04', 'an end after max');
    T::eq(drRead(['preset' => 'last-year'], $o), ['from' => '2026-01-01', 'to' => '2026-01-01', 'preset' => 'last-year'], 'a period wholly before min');
    T::eq(drRead(['preset' => 'custom', 'from' => '2026-12-01', 'to' => '2026-12-24'], $o), ['from' => '2026-11-04', 'to' => '2026-11-04', 'preset' => 'custom'], 'wholly after max');
},

'a preset the picker does not offer is not taken from the URL' => function (): void {
    $o = ['presets' => ['this-month', 'last-month']];
    T::eq(drRead(['preset' => 'last-year'], $o)['preset'], 'this-month', 'last-year is not offered: the default');
    T::eq(drRead(['preset' => 'last-year', 'from' => '2026-03-01', 'to' => '2026-03-05'], $o)['preset'], 'custom', 'its dates still count');
    T::eq(drRead(['preset' => 'last-month'], $o)['preset'], 'last-month', 'an offered one is');
    T::eq(drRead(['preset' => 'custom', 'from' => '2026-03-01', 'to' => '2026-03-05'], ['presets' => []])['preset'], 'custom', 'no presets: dates only');
    T::eq(drRead(['preset' => 'this-month'], ['presets' => []])['preset'], 'this-month', 'no presets, a preset in the URL: the default (which is this one)');
},

'a hostile request is the default, without a warning' => function (): void {
    $hostile = [
        'not an array'   => 'this-month',
        'nested'         => ['preset' => ['x'], 'from' => ['2026-01-01'], 'to' => ['a' => 'b']],
        'invalid day'    => ['from' => '2026-02-30', 'to' => '2026-03-01'],
        'month 13'       => ['from' => '2026-13-01', 'to' => '2026-13-02'],
        'short'          => ['from' => '2026-1-5', 'to' => '2026-1-6'],
        'year 0'         => ['from' => '0000-01-01', 'to' => '0000-01-02'],
        'five digits'    => ['from' => '10000-01-01', 'to' => '10000-01-02'],
        'plus sign'      => ['from' => '+2026-01-01', 'to' => '+2026-01-02'],
        'other digits'   => ['from' => '２０２６-01-01', 'to' => '٢٠٢٦-01-02'],
        'time attached'  => ['from' => '2026-01-01T00:00', 'to' => '2026-01-02 12:00'],
        'null byte'      => ['from' => "2026-01-01\0", 'to' => "2026-01-02\0"],
        'script'         => ['preset' => '<script>alert(1)</script>', 'from' => '"><img src=x>', 'to' => "'"],
        'numbers'        => ['preset' => 5, 'from' => 20260101, 'to' => 1.5],
        'huge'           => ['preset' => str_repeat('a', 100000), 'from' => str_repeat('9', 100000), 'to' => ''],
        'shown broken'   => ['preset' => 'this-month', 'from' => '2026-01-01', 'to' => '2026-01-02', 'shown' => ['a']],
        'shown garbage'  => ['preset' => 'this-month', 'from' => '2026-01-01', 'to' => '2026-01-02', 'shown' => '2026-01-01/2026-01-02/x'],
        'invalid UTF-8'  => ['preset' => "\xff\xfe", 'from' => "\xc3\x28", 'to' => "\xa0\xa1"],
    ];
    foreach ($hostile as $what => $value) {
        [$r, $notes] = drRun(fn () => DateRange::fromRequest('period', 'this-month', ['today' => '2026-11-04', 'request' => ['period' => $value]]));
        T::eq($notes, [], "$what: no warning — a visitor wrote it, not the developer");
        T::ok(is_array($r) && DateRange::date($r['from']) !== null && DateRange::date($r['to']) !== null && $r['from'] <= $r['to'], "$what: two real days in order");
        if (!in_array($what, ['shown broken', 'shown garbage'], true)) {
            T::eq($r, ['from' => '2026-11-01', 'to' => '2026-11-30', 'preset' => 'this-month'], "$what: the default");
        }
        [$html, $notes] = drRun(fn () => (new DateRange('period'))->today('2026-11-04')->value($value)->filter()->render());
        T::eq($notes, [], "$what: the picker renders without a warning");
        T::notContains((string) $html, '<script>alert', "$what: nothing of it reaches the page unescaped");
        T::notContains((string) $html, '<img src=x>', "$what: nothing of it reaches the page unescaped");
    }
    // ' 2026-01-05 ' with spaces is the day: a person may paste one.
    T::eq(drRead(['from' => ' 2026-01-05 ', 'to' => "2026-01-09\n"])['from'], '2026-01-05', 'spaces around a date are no reason to throw it away');
    // $_GET and $_POST: the POST when it carries the name.
    $saved = [$_GET, $_POST];
    try {
        $_GET = ['period' => ['preset' => 'last-year']];
        $_POST = [];
        T::eq(DateRange::fromRequest('period', 'this-month', ['today' => '2026-11-04'])['preset'], 'last-year', 'read from the query string');
        $_POST = ['period' => ['preset' => 'yesterday']];
        T::eq(DateRange::fromRequest('period', 'this-month', ['today' => '2026-11-04'])['preset'], 'yesterday', 'the POST wins');
        T::eq((new DateRange('period'))->today('2026-11-04')->current()['preset'], 'yesterday', 'and the picker reads the same');
    } finally {
        [$_GET, $_POST] = $saved;
    }
},

// ── Words ───────────────────────────────────────────────────────────────

'the period in words, in English and in German' => function (): void {
    Lang::set('en');
    T::eq(DateRange::text('2026-10-01', '2026-10-31'), 'Oct 1 – 31, 2026', 'one month');
    T::eq(DateRange::text('2026-09-28', '2026-10-04'), 'Sep 28 – Oct 4, 2026', 'one year');
    T::eq(DateRange::text('2025-12-29', '2026-01-04'), 'Dec 29, 2025 – Jan 4, 2026', 'two years');
    T::eq(DateRange::text('2026-10-01', '2026-10-01'), 'Oct 1, 2026', 'one day');
    T::eq(DateRange::text('2026-10-31', '2026-10-01'), 'Oct 1 – 31, 2026', 'reversed, put in order');
    T::eq(DateRange::text('2026-02-30', '2026-03-01'), '', 'no day, no words');
    Lang::set('de');
    T::eq(DateRange::text('2026-10-01', '2026-10-31'), '1.–31. Okt. 2026', 'ein Monat');
    T::eq(DateRange::text('2026-09-28', '2026-10-04'), '28. Sep. – 4. Okt. 2026', 'ein Jahr');
    T::eq(DateRange::text('2025-12-29', '2026-01-04'), '29. Dez. 2025 – 4. Jan. 2026', 'zwei Jahre');
    T::eq(DateRange::text('2026-03-01', '2026-03-01'), '1. März 2026', 'ein Tag, März ohne Punkt');
    [$html] = drRun(fn () => (new DateRange('p'))->today('2026-11-04')->render());
    T::contains((string) $html, '>Dieser Monat</option>', 'the presets in German');
    T::contains((string) $html, 'aria-label="Zeitraum"', 'the group in German');
    T::contains((string) $html, 'aria-label="Von"', 'from');
    T::contains((string) $html, '>1.–30. Nov. 2026</output>', 'the words in German');
    Lang::set('en');
    [$html] = drRun(fn () => (new DateRange('p'))->today('2026-11-04')->filter()->render());
    foreach (['Dieser', 'Zeitraum', 'Anwenden', 'Okt.', 'Vorauswahl'] as $de) T::notContains((string) $html, $de, 'English stays English');
},

// ── Markup ──────────────────────────────────────────────────────────────

'the markup: a named group, a select, two date fields, the words, the field the server reads back' => function (): void {
    Lang::set('en');
    [$html, $notes] = drRun(fn () => (new DateRange('period'))->today('2026-11-04')->render());
    T::eq($notes, [], 'says nothing');
    T::contains($html, '<div class="gk-daterange" id="gk-daterange-period" role="group" aria-label="Period" data-gk-daterange="', 'the group');
    T::contains($html, '<select class="gk-filter gk-daterange-preset" name="period[preset]" id="gk-daterange-period-preset" aria-label="Preset">', 'the select');
    T::contains($html, '<option value="this-month" selected>This month</option>', 'the default chosen');
    T::eq(substr_count($html, '<option '), 11, 'every preset');
    T::contains($html, '<input type="date" class="gk-filter gk-daterange-from" name="period[from]" id="gk-daterange-period-from" value="2026-11-01" min="0001-01-01" max="9999-12-31" aria-label="From">', 'from');
    T::contains($html, '<input type="date" class="gk-filter gk-daterange-to" name="period[to]" id="gk-daterange-period-to" value="2026-11-30" min="0001-01-01" max="9999-12-31" aria-label="To">', 'to');
    T::contains($html, '<span class="gk-daterange-sep" aria-hidden="true">–</span>', 'the dash is decoration');
    T::contains($html, '<output class="gk-daterange-text" for="gk-daterange-period-from gk-daterange-period-to" aria-live="polite">Nov 1 – 30, 2026</output>', 'the words, for both fields');
    T::contains($html, '<input type="hidden" name="period[shown]" value="2026-11-01/2026-11-30" data-gk-daterange-shown>', 'what the page showed');
    T::notContains($html, '<form', 'no form of its own without filter()');
    T::notContains($html, 'gk-daterange-apply', 'no Apply without filter()');
    // The config the script reads.
    preg_match('/data-gk-daterange="([^"]*)"/', $html, $m);
    $cfg = json_decode(html_entity_decode($m[1] ?? '', ENT_QUOTES), true);
    T::eq($cfg['today'] ?? null, '2026-11-04', 'the server\'s today');
    T::eq(count($cfg['months'] ?? []), 12, 'twelve month names');
    T::eq(array_keys($cfg['text'] ?? []), ['day', 'month', 'year', 'years'], 'the four shapes');
    T::eq($cfg['submit'] ?? null, false, 'not a filter');
},

'presets(): order, custom always, unknown left out, an empty list shows no select' => function (): void {
    [$html, $notes] = drRun(fn () => (new DateRange('p'))->today('2026-11-04')->presets(['last-month', 'this-month', 'bogus', 'last-month', 3])->render());
    T::eq(count($notes), 2, 'two warnings: bogus and 3');
    preg_match_all('/<option value="([^"]+)"/', (string) $html, $m);
    T::eq($m[1], ['last-month', 'this-month', 'custom'], 'in the order given, once each, custom at the end');
    T::contains((string) $html, '<option value="this-month" selected>', 'the default is offered, and chosen');
    [$html] = drRun(fn () => (new DateRange('p'))->today('2026-11-04')->presets(['custom', 'today'])->render());
    preg_match_all('/<option value="([^"]+)"/', (string) $html, $m);
    T::eq($m[1], ['custom', 'today'], 'custom where it was put');
    [$html] = drRun(fn () => (new DateRange('p'))->today('2026-11-04')->presets([])->render());
    T::notContains((string) $html, '<select', 'no presets: no select');
    T::contains((string) $html, 'value="2026-11-01"', 'the dates of the default');
},

'a preset outside min and max is not offered' => function (): void {
    [$html] = drRun(fn () => (new DateRange('p'))->today('2026-11-04')->min('2026-06-01')->max('2026-11-04')->render());
    preg_match_all('/<option value="([^"]+)"/', (string) $html, $m);
    T::eq($m[1], ['today', 'yesterday', 'last-7-days', 'last-30-days', 'this-month', 'last-month', 'this-quarter', 'last-quarter', 'this-year', 'custom'],
        'last-year lies before min');
    T::contains((string) $html, 'min="2026-06-01" max="2026-11-04"', 'the bounds on both fields');
    T::contains((string) $html, 'value="2026-11-04" min', 'this month ends today');
},

'filter(): its own GET form, the kept parameters, Apply' => function (): void {
    $saved = $_GET;
    try {
        $_GET = ['status' => 'open', 'q' => '"x" & <y>', 'list' => ['a'], 'period' => ['preset' => 'last-year']];
        [$html, $notes] = drRun(fn () => (new DateRange('period'))->today('2026-11-04')->filter()
            ->baseUrl('/invoices?x="1"#list')->preserve(['status', 'q', 'list', 'period', 'missing', 'page' => 2])->render());
    } finally {
        $_GET = $saved;
    }
    T::eq($notes, [], 'says nothing');
    T::contains((string) $html, '<form class="gk-daterange-form" method="get" action="/invoices?x=&quot;1&quot;#list">', 'the form, its action escaped');
    T::contains((string) $html, '<input type="hidden" name="status" value="open">', 'a kept parameter');
    T::contains((string) $html, '<input type="hidden" name="q" value="&quot;x&quot; &amp; &lt;y&gt;">', 'escaped');
    T::contains((string) $html, '<input type="hidden" name="page" value="2">', 'name => value');
    T::notContains((string) $html, 'name="list"', 'an array is not carried');
    T::notContains((string) $html, 'name="missing"', 'an absent one is not');
    T::eq(substr_count((string) $html, 'name="period'), 4, 'its own name is not carried twice: preset, from, to, shown');
    T::contains((string) $html, 'class="gk-btn gk-btn-tonal gk-btn-primary gk-btn-sm gk-daterange-apply"', 'Apply');
    T::contains((string) $html, 'type="submit"', 'Apply sends');
    T::contains((string) $html, '&quot;submit&quot;:true', 'the script is told to send a preset at once');
    T::contains((string) $html, '<option value="last-year" selected>', 'and it reads the URL');
},

'in a Form: the field type daterange' => function (): void {
    Lang::set('en');
    [$html, $notes] = drRun(fn () => (new Form('f'))
        ->field('period', 'Invoice date', 'daterange', ['presets' => ['this-month', 'last-month'], 'default' => 'last-month',
            'today' => '2026-11-04', 'min' => '2026-01-01', 'max' => '2026-12-31', 'timezone' => 'Europe/Vienna', 'required' => true])
        ->errors(['period' => 'Choose a period.'])
        ->render());
    T::eq($notes, [], 'says nothing');
    T::contains((string) $html, '<label class="gk-label-text" id="period-label">Invoice date <span class="gk-required" aria-hidden="true">*</span></label>', 'the label, no for: a group');
    T::contains((string) $html, '<div class="gk-daterange" id="period" role="group" aria-labelledby="period-label" aria-describedby="period-error"', 'named by the label, described by the message');
    T::contains((string) $html, '<select class="gk-input gk-daterange-preset" name="period[preset]" id="period-preset" aria-label="Preset" aria-describedby="period-error">', 'the Form\'s fields');
    T::contains((string) $html, 'value="2026-10-01" min="2026-01-01" max="2026-12-31" aria-label="From" required aria-describedby="period-error" aria-invalid="true">', 'required, described, invalid');
    T::contains((string) $html, '<div class="gk-field-error" id="period-error" role="alert" data-gk-error="period">Choose a period.</div>', 'the message under it');
    // values() hands back what was posted.
    [$html] = drRun(fn () => (new Form('f'))->field('period', 'P', 'daterange', ['today' => '2026-11-04'])
        ->values(['period' => ['preset' => 'custom', 'from' => '2026-03-01', 'to' => '2026-03-09']])->render());
    T::contains((string) $html, 'value="2026-03-01"', 'the posted dates');
    T::contains((string) $html, '<option value="custom" selected>', 'custom');
    // A field never reads the request on its own.
    $saved = $_GET;
    try {
        $_GET = ['period' => ['preset' => 'last-year']];
        [$html] = drRun(fn () => (new Form('f'))->field('period', 'P', 'daterange', ['today' => '2026-11-04'])->render());
    } finally {
        $_GET = $saved;
    }
    T::contains((string) $html, '<option value="this-month" selected>', 'the default, not the query string');
    // A bad option warns and the field renders.
    [$html, $notes] = drRun(fn () => (new Form('f'))->field('period', 'P', 'daterange', ['presets' => 'this-month', 'today' => '2026-11-04'])->render());
    T::eq(count($notes), 1, 'presets that are no list warn');
    T::contains((string) $html, 'gk-daterange', 'and the field is there');
},

'escaping' => function (): void {
    [$html] = drRun(fn () => (new DateRange('p'))->today('2026-11-04')->label('<b>"Period"</b> & co')->render());
    T::contains((string) $html, 'aria-label="&lt;b&gt;&quot;Period&quot;&lt;/b&gt; &amp; co"', 'the label');
    Lang::load('en', ['daterange.this_month' => 'This <month>']);
    [$html] = drRun(fn () => (new DateRange('p'))->today('2026-11-04')->render());
    Lang::load('en', ['daterange.this_month' => 'This month']);
    T::contains((string) $html, '>This &lt;month&gt;</option>', 'a translation is text');
},

// ── Warnings ────────────────────────────────────────────────────────────

'every misuse warns and the picker still renders' => function (): void {
    $cases = [
        'a name that is no name'  => fn () => new DateRange('period[x]"'),
        'a default that is none'  => fn () => (new DateRange('p'))->default('custom'),
        'a default of no days'    => fn () => (new DateRange('p'))->default(['from' => '2026-02-30', 'to' => 'x']),
        'an unknown preset'       => fn () => (new DateRange('p'))->presets(['tomorrow']),
        'a min that is no day'    => fn () => (new DateRange('p'))->min('yesterday'),
        'a max before min'        => fn () => (new DateRange('p'))->min('2026-06-01')->max('2026-01-01'),
        'a min after max'         => fn () => (new DateRange('p'))->max('2026-01-01')->min('2026-06-01'),
        'a time zone that is none'=> fn () => (new DateRange('p'))->timezone('Mars/Olympus'),
        'a today that is no day'  => fn () => (new DateRange('p'))->today('soon'),
        'an unknown option'       => fn () => DateRange::fromRequest('p', 'this-month', ['tz' => 'UTC', 'request' => []]),
        'presetRange of no day'   => fn () => DateRange::presetRange('today', '2026-13-01'),
    ];
    foreach ($cases as $what => $fn) {
        [$r, $notes] = drRun($fn);
        T::eq(count($notes), 1, "$what: one warning");
        T::ok(str_starts_with($notes[0] ?? '', 'GridKit: DateRange: '), "$what: it says who speaks");
        if ($r instanceof DateRange) {
            [$html, $more] = drRun(fn () => $r->render());
            T::contains((string) $html, 'gk-daterange', "$what: it still renders");
            T::eq($more, [], "$what: and says nothing more");
        }
    }
    [$r] = drRun(fn () => (new DateRange('period[x]"'))->today('2026-11-04')->html());
    T::contains((string) $r, 'name="periodx[from]"', 'the name is cleaned');
},

// ── Contracts ───────────────────────────────────────────────────────────

'the script: its presets and words are the server\'s, its events and API are documented' => function () use ($js, $doc): void {
    $c = $js();
    T::ok($c !== '', 'js/calendar.js is there');
    foreach (DateRange::PRESETS as $p) {
        if ($p === 'custom') continue;
        T::contains($c, 'case "' . $p . '":', "the script counts $p");
    }
    foreach (['data-gk-daterange', 'data-gk-daterange-shown', 'gk:daterange', 'requestSubmit', 'setUTCFullYear', '.gk-daterange-preset',
              '.gk-daterange-from', '.gk-daterange-to', '.gk-daterange-text', 'GK.dateRange'] as $w) {
        T::contains($c, $w, "the script uses $w");
    }
    T::notContains($c, 'Date.UTC(', 'Date.UTC reads the years 0 to 99 as 1900 to 1999');
    T::notContains($c, 'toLocaleDateString', 'the words are the server\'s, not the browser\'s');
    exec('node --version 2>/dev/null', $o, $has);
    if ($has === 0) {
        exec('node --check ' . escapeshellarg(__DIR__ . '/../js/calendar.js') . ' 2>&1', $out, $code);
        T::eq($code, 0, 'js/calendar.js parses: ' . implode(' ', $out));
    }
    $d = $doc();
    T::ok((bool) preg_match('/^### DateRange\b/m', $d), 'a "### DateRange" section');
    foreach (['gk:daterange', 'GK.dateRange.get(', 'GK.dateRange.set(', 'GK.dateRange.presetRange(', 'GK.dateRange.text(', 'name[shown]',
              "'daterange'", 'js/calendar.js'] as $w) {
        T::contains($d, $w, "the skill names $w");
    }
    foreach (DateRange::PRESETS as $p) T::contains($d, "`$p`", "the skill names the preset $p");
},

'the stylesheet block: at the end, roles only, a phone rule, no arrow on a date field' => function () use ($sheet): void {
    $css = gkCssText();
    $at = strpos($css, '/* === 1.120.0 — dates === */');
    T::ok($at !== false, 'the block heading is there');
    $later = preg_match_all('~/\* === 1\.(\d+)\.~', substr($css, (int) $at + 10), $v) ? $v[1] : [];
    T::ok(array_filter($later, static fn(string $m): bool => (int) $m <= 120) === [], 'it is the last block, but for later releases');
    $s = $sheet();
    foreach (['.gk-daterange', '.gk-daterange-form', '.gk-daterange-dates', '.gk-daterange-sep', '.gk-daterange-text', 'input.gk-daterange-from'] as $sel) {
        T::contains($s, $sel, "$sel is styled");
    }
    T::ok((bool) preg_match('/\.gk-daterange input\.gk-filter\s*\{[^}]*background-image:\s*none/', $s), 'a date field in the bar loses the arrow of .gk-filter');
    T::ok((bool) preg_match('/@media \(max-width: 600px\)\s*\{[^@]*\.gk-daterange input\.gk-daterange-from[^}]*\{[^}]*width:\s*0/s', $s), 'on a phone the two dates share the row and hold no column open');
    preg_match_all('/(?<![-\w])(?:color|background(?:-color)?|border(?:-color)?)\s*:[^;]*(#[0-9a-f]{3,8}\b|rgba?\()/i', $s, $lit);
    T::eq($lit[0], [], 'no literal colour: roles only');
},

];
})();
