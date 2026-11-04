<?php
/**
 * GridKit\Calendar (1.120.0) — a month view for appointments, deadlines and campaigns.
 *
 * What the server writes is checked here: the weeks of a month for either first weekday (months that
 * start on that day, leap Februaries, the first and last month a date field knows), the ISO week of
 * every row, today in a time zone, the lanes of events that last several days (one lane across the
 * week, joined segments, the title carried across a run and started again after a gap), "+n more" and
 * the whole day behind it, the agenda a narrow container shows, the month links (kept parameters, a
 * fragment, hostile ?month= values), escaping, and every misuse as a warning. The keys, the focus, the
 * events and the look need a browser: ci/browser-dates.js.
 */

declare(strict_types=1);

use GridKit\{Calendar, Lang};

require_once __DIR__ . '/css-helpers.php';

/** Render, and collect what it said with E_USER_WARNING. @return array{0:string,1:list<string>} */
function calRender(callable $fn): array
{
    $notes = [];
    set_error_handler(static function (int $no, string $msg) use (&$notes): bool {
        if ($no !== E_USER_WARNING) return false;
        $notes[] = $msg;
        return true;
    });
    try {
        ob_start();
        try { $r = $fn(); } finally { $out = (string) ob_get_clean(); }
    } finally {
        restore_error_handler();
    }
    return [is_string($r ?? null) ? $r : $out, $notes];
}

/** The days of the grid in order, as their data-gk-cal-date. @return list<string> */
function calDays(string $html): array
{
    preg_match_all('/<td class="gk-cal-day[^"]*" data-gk-cal-date="([^"]+)"/', $html, $m);
    return $m[1];
}

/** The <td> of one day. */
function calCell(string $html, string $day): string
{
    return preg_match('#<td class="gk-cal-day[^"]*" data-gk-cal-date="' . $day . '"[^>]*>(.*?)</td>#s', $html, $m) ? $m[0] : '';
}

/** What a day of the grid shows, lane by lane: titles, "·" for an empty lane, "+n" for more. */
function calLanes(string $html, string $day): string
{
    $cell = calCell($html, $day);
    if (!preg_match('#<div class="gk-cal-events">(.*?)(?:<details|</div></td>)#s', $cell, $m)) return '';
    preg_match_all('#<span class="gk-cal-slot"|<(?:a|span) [^>]*class="gk-cal-event[^"]*"[^>]*>.*?<span class="gk-cal-event-title[^"]*">([^<]*)</span>#s', $m[1], $x, PREG_SET_ORDER);
    $out = array_map(static fn(array $h): string => isset($h[1]) ? html_entity_decode($h[1]) : '·', $x);
    if (preg_match('#<summary>\+(\d+)#', $cell, $more)) $out[] = '+' . $more[1];
    return implode(' | ', $out);
}

/** @return array<string,callable> */
return (static function (): array {

$root = __DIR__ . '/..';
$js   = static fn (): string => (string) @file_get_contents($root . '/js/calendar.js');
$doc  = static fn (): string => (string) file_get_contents($root . '/GRIDKIT_SKILL.md');
$sheet = static function (): string {
    $css = gkCssText();
    $from = strpos($css, '=== 1.120.0 — dates ===');
    if ($from === false) return '';
    $next = strpos($css, "\n/* === ", $from + 10);
    $block = $next === false ? substr($css, $from) : substr($css, $from, $next - $from);
    return (string) preg_replace('#/\*.*?\*/#s', '', $block);
};

return [

// ── The grid ────────────────────────────────────────────────────────────

'the weeks of a month, from Sunday in English and from Monday in German' => function (): void {
    Lang::set('en');
    [$html, $notes] = calRender(fn () => (new Calendar('c'))->month('2026-11')->today('2026-11-04')->html());
    T::eq($notes, [], 'a clean calendar says nothing');
    $days = calDays($html);
    T::eq(count($days), 35, 'five weeks');
    T::eq([$days[0], $days[34]], ['2026-11-01', '2026-12-05'], 'Sunday 1 November to Saturday 5 December');
    preg_match_all('#<th scope="col" class="gk-cal-wd"><span aria-hidden="true">(\w+)</span><span class="gk-sr-only">(\w+)</span>#', $html, $h);
    T::eq($h[1], ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'], 'the short names, from Sunday');
    T::eq($h[2], ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'], 'the whole names for a screen reader');

    Lang::set('de');
    [$html] = calRender(fn () => (new Calendar('c'))->month('2026-11')->today('2026-11-04')->html());
    $days = calDays($html);
    T::eq(count($days), 42, 'six weeks: 1 November 2026 is a Sunday, the last day of a German week');
    T::eq([$days[0], $days[41]], ['2026-10-26', '2026-12-06'], 'Monday 26 October to Sunday 6 December');
    preg_match_all('#<th scope="col" class="gk-cal-wd"><span aria-hidden="true">(\w+)</span>#', $html, $h);
    T::eq($h[1], ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'], 'from Monday');
    T::contains($html, '>November 2026</div>', 'the month in words');
    T::contains($html, '<span class="gk-sr-only">Mittwoch, 4. November 2026, heute</span>', 'the full date, today');
    Lang::set('en');

    [$html] = calRender(fn () => (new Calendar('c'))->month('2026-11')->weekStart('Monday')->today('2026-11-04')->html());
    T::eq(calDays($html)[0], '2026-10-26', 'weekStart() decides over the language (and takes any case)');
    [$html] = calRender(fn () => (new Calendar('c'))->month('2026-11')->weekStart('sunday')->today('2026-11-04')->html());
    T::eq(calDays($html)[0], '2026-11-01', 'sunday');
},

'months of four weeks, leap Februaries, the edges of the calendar' => function (): void {
    Lang::set('en');
    $cases = [
        // month, week start, weeks, first, last
        ['2026-02', 'sunday', 4, '2026-02-01', '2026-02-28'],   // starts on a Sunday, 28 days
        ['2027-02', 'monday', 4, '2027-02-01', '2027-02-28'],   // starts on a Monday
        ['2028-02', 'monday', 5, '2028-01-31', '2028-03-05'],   // 29 days
        ['2100-02', 'monday', 4, '2100-02-01', '2100-02-28'],   // 2100 is no leap year; 1 Feb 2100 is a Monday
        ['2000-02', 'sunday', 5, '2000-01-30', '2000-03-04'],   // 2000 is
        ['2026-03', 'monday', 6, '2026-02-23', '2026-04-05'],   // 31 days from a Sunday
        ['2026-08', 'sunday', 6, '2026-07-26', '2026-09-05'],
    ];
    foreach ($cases as [$m, $ws, $weeks, $first, $last]) {
        [$html, $notes] = calRender(fn () => (new Calendar('c'))->month($m)->weekStart($ws)->today('2026-11-04')->html());
        $days = calDays($html);
        T::eq($notes, [], "$m: says nothing");
        T::eq(count($days), $weeks * 7, "$m from $ws: $weeks weeks");
        T::eq([$days[0], $days[count($days) - 1]], [$first, $last], "$m from $ws: first and last day");
        $in = array_filter($days, static fn(string $d): bool => str_starts_with($d, $m));
        T::eq(count($in), (int) (new DateTimeImmutable("$m-01"))->format('t'), "$m: every day of the month, once");
        T::eq(substr_count($html, 'gk-cal-day gk-cal-outside'), count($days) - count($in), "$m: the others are outside");
    }
    foreach (['0001-01', '9999-12'] as $m) {
        foreach (['monday', 'sunday'] as $ws) {
            [$html, $notes] = calRender(fn () => (new Calendar('c'))->month($m)->weekStart($ws)->weekNumbers()->event('0001-01-01', 'First')->event('9999-12-31', 'Last')->html());
            T::eq($notes, [], "$m from $ws: renders, says nothing");
            T::eq(substr_count($html, '<td class="gk-cal-day') % 7, 0, "$m from $ws: whole weeks");
            T::notContains($html, 'data-gk-cal-date="0000-', "$m: no day of year 0");
            T::notContains($html, 'data-gk-cal-date="10000-', "$m: no day of year 10000");
        }
    }
    [$html] = calRender(fn () => (new Calendar('c'))->month('0001-01')->html());
    T::contains($html, 'gk-cal-prev" role="link" aria-disabled="true"', 'no month before January of year 1');
    [$html] = calRender(fn () => (new Calendar('c'))->month('9999-12')->html());
    T::contains($html, 'gk-cal-next" role="link" aria-disabled="true"', 'none after December 9999');
},

'a change of clock changes nothing: every day once, in order' => function (): void {
    $saved = date_default_timezone_get();
    try {
        foreach (['Europe/Vienna' => ['2026-03', '2026-10'], 'America/Santiago' => ['2026-04', '2026-09'], 'Australia/Lord_Howe' => ['2026-04', '2026-10'],
                  'America/Havana' => ['2026-03', '2026-11']] as $tz => $months) {
            date_default_timezone_set($tz);
            foreach ($months as $m) {
                foreach (['monday', 'sunday'] as $ws) {
                    [$html] = calRender(fn () => (new Calendar('c'))->month($m)->weekStart($ws)->html());
                    $days = calDays($html);
                    $ok = true;
                    for ($i = 1; $i < count($days); $i++) {
                        $next = (new DateTimeImmutable($days[$i - 1] . ' 12:00', new DateTimeZone('UTC')))->modify('+1 day')->format('Y-m-d');
                        if ($days[$i] !== $next) { $ok = false; break; }
                    }
                    T::ok($ok, "$tz $m from $ws: no day twice, none missing");
                    $first = (new DateTimeImmutable($days[0] . ' 12:00', new DateTimeZone('UTC')))->format('l');
                    T::eq($first, $ws === 'monday' ? 'Monday' : 'Sunday', "$tz $m: the grid starts on a $ws");
                }
            }
        }
    } finally {
        date_default_timezone_set($saved);
    }
},

'week numbers are ISO 8601, read as "Week n"' => function (): void {
    Lang::set('en');
    [$html] = calRender(fn () => (new Calendar('c'))->month('2027-01')->weekStart('monday')->weekNumbers()->html());
    preg_match_all('#<th scope="row" class="gk-cal-wk"><span aria-hidden="true">(\d+)</span><span class="gk-sr-only">Week (\d+)</span></th>#', $html, $m);
    T::eq($m[1], ['53', '1', '2', '3', '4'], 'January 2027 starts in week 53 of 2026');
    T::eq($m[1], $m[2], 'the number and its words');
    [$html] = calRender(fn () => (new Calendar('c'))->month('2026-11')->weekStart('sunday')->weekNumbers()->html());
    preg_match_all('#<th scope="row" class="gk-cal-wk"><span aria-hidden="true">(\d+)</span>#', $html, $m);
    T::eq($m[1], ['45', '46', '47', '48', '49'], 'from Sunday: the week of the row\'s Monday');
    [$html] = calRender(fn () => (new Calendar('c'))->month('2021-01')->weekStart('monday')->weekNumbers()->html());
    preg_match('#<th scope="row" class="gk-cal-wk"><span aria-hidden="true">(\d+)</span>#', $html, $m);
    T::eq($m[1] ?? '', '53', '2020 had 53 weeks');
    T::contains($html, '<th scope="col" class="gk-cal-wk"><span aria-hidden="true">Wk</span><span class="gk-sr-only">Week</span></th>', 'the column head');
    [$html] = calRender(fn () => (new Calendar('c'))->month('2026-11')->html());
    T::notContains($html, 'gk-cal-wk', 'off by default');
    Lang::set('de');
    [$html] = calRender(fn () => (new Calendar('c'))->month('2026-11')->weekNumbers()->html());
    T::contains($html, '<span class="gk-sr-only">Kalenderwoche 44</span>', 'German');
    Lang::set('en');
},

'today: marked, named, in the time zone' => function (): void {
    Lang::set('en');
    [$html] = calRender(fn () => (new Calendar('c'))->month('2026-11')->today('2026-11-04')->html());
    $cell = calCell($html, '2026-11-04');
    T::contains($cell, '<td class="gk-cal-day gk-cal-today" data-gk-cal-date="2026-11-04" aria-current="date">', 'the class and aria-current');
    T::contains($cell, '<time datetime="2026-11-04"><span aria-hidden="true">4</span><span class="gk-sr-only">Wednesday, November 4, 2026, today</span></time>', 'its name says so');
    T::eq(substr_count($html, 'aria-current="date"'), 1, 'one day only (the agenda is empty)');
    T::notContains(calCell($html, '2026-11-05'), 'today', 'not the next');
    // 23:30 UTC on 30 November is 1 December in Vienna.
    [$html] = calRender(fn () => (new Calendar('c'))->timezone('Europe/Vienna')->today(new DateTimeImmutable('2026-11-30T23:30:00Z'))->html());
    T::contains($html, 'data-gk-cal-month="2026-12"', 'the month of today in Vienna');
    T::contains($html, 'data-gk-cal-today="2026-12-01"', 'and its day');
    [$html] = calRender(fn () => (new Calendar('c'))->timezone('America/Los_Angeles')->today(new DateTimeImmutable('2026-11-30T23:30:00Z'))->html());
    T::contains($html, 'data-gk-cal-today="2026-11-30"', 'still November in Los Angeles');
    $real = (new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d');
    [$html] = calRender(fn () => (new Calendar('c'))->timezone('Asia/Kolkata')->html());
    T::contains($html, 'data-gk-cal-today="' . $real . '"', 'without today(): today in the zone');
},

// ── Events ──────────────────────────────────────────────────────────────

'an event: a link with its time and title, a colour of the label palette' => function (): void {
    Lang::set('en');
    [$html, $notes] = calRender(fn () => (new Calendar('c'))->month('2026-11')->today('2026-11-04')
        ->event('2026-11-04 09:30', 'Team meeting', ['href' => '/e/7', 'color' => 'blue', 'id' => 7])
        ->event('2026-11-04', 'Deadline', ['color' => 'red'])
        ->event(new DateTimeImmutable('2026-11-04 14:00'), 'Call')
        ->html());
    T::eq($notes, [], 'says nothing');
    $cell = calCell($html, '2026-11-04');
    T::contains($cell, '<a href="/e/7" class="gk-cal-event gk-label-blue" data-gk-cal-event="7" data-gk-cal-start="2026-11-04" data-gk-cal-end="2026-11-04"><span class="gk-cal-time">09:30</span> <span class="gk-cal-event-title">Team meeting</span></a>', 'a link');
    T::contains($cell, '<span class="gk-cal-event gk-label-red" data-gk-cal-event="e2"', 'without href: text (the script makes it a button); an id by its place');
    T::contains($cell, 'class="gk-cal-event gk-cal-accent"', 'without a colour: the accent');
    T::contains($cell, '<span class="gk-cal-time">14:00</span> <span class="gk-cal-event-title">Call</span>', 'the time of a DateTime');
    T::eq(calLanes($html, '2026-11-04'), 'Deadline | Team meeting | Call', 'without a time first, then by time');
},

'events from rows: a map, objects, DateTimes, no time at midnight' => function (): void {
    $rows = [
        ['id' => 1, 'subject' => 'Fair', 'begins' => '2026-11-10', 'ends' => '2026-11-12', 'color' => 'orange'],
        (object) ['id' => 2, 'subject' => 'Lunch', 'begins' => new DateTimeImmutable('2026-11-11 12:15'), 'ends' => null],
        ['id' => 3, 'subject' => 'Holiday', 'date' => '2026-11-11', 'allDay' => 1],
        ['id' => 4, 'subject' => 'Midnight', 'begins' => new DateTimeImmutable('2026-11-13 00:00')],
    ];
    [$html, $notes] = calRender(fn () => (new Calendar('c'))->month('2026-11')->today('2026-11-04')
        ->events($rows, ['title' => 'subject', 'start' => 'begins', 'end' => 'ends'])->html());
    T::eq($notes, [], 'says nothing');
    T::contains($html, 'data-gk-cal-event="1" data-gk-cal-start="2026-11-10" data-gk-cal-end="2026-11-12"', 'a period');
    T::contains($html, '<span class="gk-cal-time">12:15</span> <span class="gk-cal-event-title">Lunch</span>', 'an object, a time');
    T::contains(calCell($html, '2026-11-13'), '<span class="gk-cal-event-title">Midnight</span>', 'midnight is no time');
    T::notContains(calCell($html, '2026-11-13'), 'gk-cal-time', 'no time shown');
    T::eq(calLanes($html, '2026-11-11'), 'Fair | Holiday | Lunch', 'the bar, the all-day event, then the timed one');
    [$html, $notes] = calRender(fn () => (new Calendar('c'))->month('2026-11')->events([
        ['title' => 'No date'], ['date' => '2026-11-02'], 'not a row', ['title' => ['x'], 'date' => '2026-11-02'], ['title' => 'Bad', 'date' => '2026-11-31'],
    ])->html());
    T::eq(count($notes), 5, 'each bad row warns: ' . implode(' / ', $notes));
    T::eq(substr_count($html, 'class="gk-cal-event '), 0, 'and is left out');
},

'a bar across days: one lane in the week, joined segments, the title carried across the run' => function (): void {
    Lang::set('en');
    [$html, $notes] = calRender(fn () => (new Calendar('c'))->month('2026-11')->weekStart('monday')->today('2026-11-04')
        ->event('2026-11-10', 'Single')                                        // Tuesday, before the bars in time
        ->event(['start' => '2026-11-10', 'end' => '2026-11-11'], 'Short')     // Tue–Wed
        ->event(['start' => '2026-11-10', 'end' => '2026-11-18'], 'Long')      // Tue–next Wed: crosses the week
        ->html());
    T::eq($notes, [], 'says nothing');
    T::eq(calLanes($html, '2026-11-10'), 'Long | Short | Single', 'the longer bar takes the first lane, the bars come before the single day');
    T::eq(calLanes($html, '2026-11-11'), 'Long | Short', 'the same lanes the next day');
    T::eq(calLanes($html, '2026-11-12'), 'Long', 'and after');
    $tue = calCell($html, '2026-11-10');
    T::contains($tue, 'class="gk-cal-event gk-cal-accent gk-cal-span gk-cal-span-out gk-cal-span-head" style="--gk-cal-days: 6"', 'Long starts a run of six days to Sunday');
    T::contains($tue, 'class="gk-cal-event gk-cal-accent gk-cal-span gk-cal-span-out gk-cal-span-head" style="--gk-cal-days: 2"', 'Short a run of two');
    $wed = calCell($html, '2026-11-11');
    T::contains($wed, 'gk-cal-span gk-cal-span-in gk-cal-span-out gk-cal-span-tail"', 'Long in the middle: joined both ways');
    T::contains($wed, 'gk-cal-span gk-cal-span-in gk-cal-span-tail"', 'Short at its end: joined to the day before only');
    T::contains($wed, '<span class="gk-cal-event-title gk-sr-only">Long</span>', 'a tail reads the title, the eye sees it once');
    $sun = calCell($html, '2026-11-15');
    T::contains($sun, 'gk-cal-span gk-cal-span-in gk-cal-span-tail"', 'the end of the week: joined to Saturday, not past the edge');
    $mon = calCell($html, '2026-11-16');
    T::contains($mon, 'gk-cal-span gk-cal-span-out gk-cal-span-head" style="--gk-cal-days: 3"', 'the next week starts it again, with its title');
    T::contains($mon, '<span class="gk-cal-event-title">Long</span>', 'visible');
    // A bar that started before the grid.
    [$html] = calRender(fn () => (new Calendar('c'))->month('2026-11')->weekStart('monday')->event(['start' => '2026-09-01', 'end' => '2026-10-27'], 'Old')->html());
    T::contains(calCell($html, '2026-10-26'), 'gk-cal-span gk-cal-span-out gk-cal-span-head" style="--gk-cal-days: 2"', 'its title on the first day of the grid');
    // An empty lane above a bar keeps its place.
    [$html] = calRender(fn () => (new Calendar('c'))->month('2026-11')->weekStart('monday')
        ->event(['start' => '2026-11-09', 'end' => '2026-11-10'], 'A')->event(['start' => '2026-11-10', 'end' => '2026-11-13'], 'B')->html());
    T::eq(calLanes($html, '2026-11-11'), '· | B', 'lane 0 is empty on Wednesday, B stays in lane 1');
    T::eq(substr_count(calCell($html, '2026-11-11'), '<span class="gk-cal-slot" aria-hidden="true"></span>'), 1, 'a slot');
},

'"+n more": the day shows one less, the whole day behind a details' => function (): void {
    Lang::set('en');
    $cal = static function (int $max): Calendar {
        $c = (new Calendar('c'))->month('2026-11')->today('2026-11-04')->maxPerDay($max);
        foreach (['10:00' => 'B', '08:00' => 'A', '12:00' => 'C', '15:00' => 'D', '17:00' => 'E'] as $t => $title) {
            $c->event("2026-11-04 $t", $title, ['href' => "/$title"]);
        }
        return $c;
    };
    [$html] = calRender(fn () => $cal(3)->html());
    T::eq(calLanes($html, '2026-11-04'), 'A | B | +3', 'two and "+3 more"');
    $cell = calCell($html, '2026-11-04');
    T::contains($cell, '<details class="gk-cal-more"><summary>+3 more<span class="gk-sr-only"> on Wednesday, November 4, 2026</span></summary>', 'the summary says which day');
    T::contains($cell, '<div class="gk-cal-more-list" role="group" aria-label="Wednesday, November 4, 2026">', 'the list, named');
    preg_match_all('#<li><a href="/(\w)" class="gk-cal-event gk-cal-event-row gk-cal-accent"#', $cell, $m);
    T::eq($m[1], ['A', 'B', 'C', 'D', 'E'], 'every event of the day, in time order');
    [$html] = calRender(fn () => $cal(5)->html());
    T::eq(calLanes($html, '2026-11-04'), 'A | B | C | D | E', 'five fit in five');
    [$html] = calRender(fn () => $cal(1)->html());
    T::eq(calLanes($html, '2026-11-04'), '+5', 'one line: all behind "+5 more"');
    [$html] = calRender(fn () => $cal(6)->html());
    T::notContains($html, 'gk-cal-more', 'no more when it fits');
    // A bar hidden on a busy day splits its run: the next visible day carries the title again.
    $c = (new Calendar('c'))->month('2026-11')->weekStart('sunday')->maxPerDay(2)
        ->event(['start' => '2026-11-08', 'end' => '2026-11-10'], 'Top')
        ->event(['start' => '2026-11-09', 'end' => '2026-11-11'], 'Bar');
    foreach (['X', 'Y'] as $t) $c->event('2026-11-10', $t);
    [$html] = calRender(fn () => $c->html());
    T::eq(calLanes($html, '2026-11-10'), 'Top | +3', 'Tuesday: Bar is behind "+3 more"');
    T::contains(calCell($html, '2026-11-09'), 'gk-cal-accent gk-cal-span" data-gk-cal-event="e2"', 'Monday: Bar stands alone, joined to nothing');
    T::contains(calCell($html, '2026-11-11'), 'gk-cal-accent gk-cal-span" data-gk-cal-event="e2"', 'Wednesday: again, with its title');
    T::contains(calCell($html, '2026-11-11'), '<span class="gk-cal-event-title">Bar</span>', 'the title visible');
},

'the agenda: the days that hold something, multi-day events on each of their days' => function (): void {
    Lang::set('en');
    [$html] = calRender(fn () => (new Calendar('c'))->label('Appointments')->month('2026-11')->today('2026-11-04')
        ->event('2026-11-04 09:30', 'Meeting', ['href' => '/m'])
        ->event(['start' => '2026-10-30', 'end' => '2026-11-02'], 'Fair', ['color' => 'orange'])
        ->event('2026-11-02', 'Holiday', ['allDay' => true])
        ->event('2026-12-01', 'Next month')
        ->html());
    preg_match('#<div class="gk-cal-agenda" role="region" aria-label="([^"]+)">(.*)</div></div>$#s', $html, $a);
    T::eq($a[1] ?? '', 'Appointments, November 2026', 'a region, named');
    preg_match_all('#<time datetime="([\d-]+)">([^<]+)</time>#', $a[2] ?? '', $d);
    T::eq($d[1], ['2026-11-01', '2026-11-02', '2026-11-04'], 'the days of this month that hold something, in order');
    T::eq($d[2][0] ?? '', 'Sunday, November 1, 2026', 'each with its full date');
    T::contains($a[2] ?? '', '<span class="gk-cal-time">Oct 30 – Nov 2, 2026</span> <span class="gk-cal-event-title">Fair</span>', 'a bar says its days');
    T::contains($a[2] ?? '', '<span class="gk-cal-time">All day</span> <span class="gk-cal-event-title">Holiday</span>', 'all day');
    T::contains($a[2] ?? '', '<li class="gk-cal-agenda-day gk-cal-today" aria-current="date"><div class="gk-cal-agenda-date"><time datetime="2026-11-04">Wednesday, November 4, 2026</time> <span class="gk-cal-today-mark">Today</span>', 'today, marked in words');
    T::notContains($a[2] ?? '', 'Next month', 'another month is not in the agenda');
    // A bar that starts at 10:00 still comes before a single event at 08:00: what lasts days first.
    [$html] = calRender(fn () => (new Calendar('c'))->month('2026-11')
        ->event('2026-11-10 08:00', 'Early')->event(['start' => '2026-11-10 10:00', 'end' => '2026-11-12'], 'Fair')->html());
    preg_match('#<time datetime="2026-11-10">.*?</ul>#s', substr($html, (int) strpos($html, 'gk-cal-agenda')), $day);
    preg_match_all('#<span class="gk-cal-event-title">([^<]+)</span>#', $day[0] ?? '', $t);
    T::eq($t[1], ['Fair', 'Early'], 'the days of the fair before the breakfast at eight');
    [$html] = calRender(fn () => (new Calendar('c'))->month('2026-11')->event('2026-12-01', 'Elsewhere')->html());
    T::contains($html, '<p class="gk-cal-empty">Nothing this month.</p>', 'an empty month says so');
},

// ── Navigation ──────────────────────────────────────────────────────────

'the month links: the parameter, kept parameters, a fragment last' => function (): void {
    Lang::set('en');
    $saved = [$_GET, $_SERVER['REQUEST_URI'] ?? null];
    try {
        $_GET = ['user' => '7', 'q' => 'a b&c', 'arr' => ['x'], 'm' => '2026-02'];
        $_SERVER['REQUEST_URI'] = '/admin/calendar?user=7';
        [$html, $notes] = calRender(fn () => (new Calendar('c'))->param('m')->preserve(['user', 'q', 'arr', 'm', 'view' => 'month'])->today('2026-11-04')->html());
        T::eq($notes, [], 'says nothing');
        T::contains($html, 'data-gk-cal-month="2026-02"', 'the parameter names the month');
        T::contains($html, 'href="/admin/calendar?user=7&amp;q=a+b%26c&amp;view=month&amp;m=2026-01"', 'previous: the path, the kept parameters, the month');
        T::contains($html, 'href="/admin/calendar?user=7&amp;q=a+b%26c&amp;view=month&amp;m=2026-03"', 'next');
        T::contains($html, 'class="gk-btn gk-btn-outlined gk-btn-neutral gk-btn-sm gk-cal-goto-today" href="/admin/calendar?user=7&amp;q=a+b%26c&amp;view=month&amp;m=2026-11" data-gk-cal-go="2026-11">Today</a>', 'today');
        T::contains($html, 'data-gk-cal-url="/admin/calendar?user=7&amp;q=a+b%26c&amp;view=month&amp;m=0000-00"', 'the pattern the script fills');
        T::notContains($html, 'arr', 'an array is not carried');
        [$html] = calRender(fn () => (new Calendar('c'))->month('2026-12')->baseUrl('/cal?tab=2#appointments')->html());
        T::contains($html, 'href="/cal?tab=2&amp;month=2027-01#appointments"', 'December → January of the next year, the fragment last');
        T::contains($html, 'href="/cal?tab=2&amp;month=2026-11#appointments"', 'and back');
        T::contains($html, 'aria-label="Next month" title="Next month"', 'an arrow is named');
    } finally {
        $_GET = $saved[0];
        if ($saved[1] === null) unset($_SERVER['REQUEST_URI']); else $_SERVER['REQUEST_URI'] = $saved[1];
    }
},

'a hostile ?month= is this month, without a warning' => function (): void {
    $saved = $_GET;
    try {
        foreach (['2026-13', '2026-00', '0000-01', '10000-01', '2026-1', '2026-11-04', '<script>', '', ' 2026-11', '２０２６-11', "2026-11\0",
                  str_repeat('9', 10000)] as $bad) {
            $_GET = ['month' => $bad];
            [$html, $notes] = calRender(fn () => (new Calendar('c'))->today('2026-11-04')->html());
            T::eq($notes, [], 'no warning for ' . substr(json_encode($bad), 0, 30));
            T::contains($html, 'data-gk-cal-month="2026-11"', 'this month for ' . substr(json_encode($bad), 0, 30));
        }
        foreach ([['2026-05'], ['a' => 'b'], 202605] as $bad) {
            $_GET = ['month' => $bad];
            [$html, $notes] = calRender(fn () => (new Calendar('c'))->today('2026-11-04')->html());
            T::eq($notes, [], 'no warning for a ' . gettype($bad));
            T::contains($html, 'data-gk-cal-month="2026-11"', 'this month for a ' . gettype($bad));
        }
        $_GET = ['month' => '2027-03'];
        T::eq((new Calendar('c'))->today('2026-11-04')->current(), '2027-03', 'a real month is taken');
        T::eq((new Calendar('c'))->today('2026-11-04')->month('2025-01')->current(), '2025-01', 'month() wins over it');
    } finally {
        $_GET = $saved;
    }
},

'visibleRange() is the grid' => function (): void {
    T::eq((new Calendar('c'))->month('2026-11')->weekStart('sunday')->visibleRange(), ['from' => '2026-11-01', 'to' => '2026-12-05'], 'from Sunday');
    T::eq((new Calendar('c'))->month('2026-11')->weekStart('monday')->visibleRange(), ['from' => '2026-10-26', 'to' => '2026-12-06'], 'from Monday');
},

// ── Safety ──────────────────────────────────────────────────────────────

'escaping: titles, links, times, ids, labels, the day link' => function (): void {
    [$html] = calRender(fn () => (new Calendar('c"<x>'))->label('<b>Cal</b>')->month('2026-11')->dayUrl('/new?date={date}&x="1"')
        ->event('2026-11-04', '<img src=x onerror=alert(1)>', ['href' => '/e?a=1&b="2"', 'time' => '<i>9</i>', 'id' => '"><script>'])
        ->event(['start' => '2026-11-05', 'end' => '2026-11-06'], 'A & B "C"')
        ->html());
    T::notContains($html, '<img src=x', 'a title is text');
    T::notContains($html, '<script>', 'an id is text');
    T::notContains($html, '<i>9</i>', 'a time is text');
    T::notContains($html, '<b>Cal</b>', 'a label is text');
    T::contains($html, '&lt;img src=x onerror=alert(1)&gt;', 'escaped');
    T::contains($html, 'href="/e?a=1&amp;b=&quot;2&quot;"', 'a link, escaped');
    T::contains($html, 'A &amp; B &quot;C&quot;', 'a title with quotes');
    T::contains($html, 'href="/new?date=2026-11-04&amp;x=&quot;1&quot;"', 'the day link');
    T::contains($html, 'id="gk-cal-cx"', 'the element id: only what an id may hold');
    T::contains($html, 'data-gk-cal="c&quot;&lt;x&gt;"', 'the calendar id, escaped');
},

'every misuse warns and the calendar still renders' => function (): void {
    $cases = [
        'a month that is none'      => fn (Calendar $c) => $c->month('2026-13'),
        'a param that is no name'   => fn (Calendar $c) => $c->param('m"'),
        'a week start of Friday'    => fn (Calendar $c) => $c->weekStart('friday'),
        'a time zone that is none'  => fn (Calendar $c) => $c->timezone('Moon/Base'),
        'a today that is no day'    => fn (Calendar $c) => $c->today('2026-02-30'),
        'maxPerDay(0)'              => fn (Calendar $c) => $c->maxPerDay(0),
        'a script day link'         => fn (Calendar $c) => $c->dayUrl(' JaVa script:alert(1)'),
        'an event of no day'        => fn (Calendar $c) => $c->event('tomorrow', 'X'),
        'a period without a start'  => fn (Calendar $c) => $c->event(['end' => '2026-11-04'], 'X'),
        'an end that is no day'     => fn (Calendar $c) => $c->event(['start' => '2026-11-04', 'end' => 'later'], 'X'),
        'an end before the start'   => fn (Calendar $c) => $c->event(['start' => '2026-11-09', 'end' => '2026-11-04'], 'X'),
        'an unknown period key'     => fn (Calendar $c) => $c->event(['start' => '2026-11-04', 'until' => '2026-11-05'], 'X'),
        'an unknown option'         => fn (Calendar $c) => $c->event('2026-11-04', 'X', ['colour' => 'red']),
        'a colour outside the list' => fn (Calendar $c) => $c->event('2026-11-04', 'X', ['color' => 'purple']),
        'a javascript: link'        => fn (Calendar $c) => $c->event('2026-11-04', 'X', ['href' => "java\nscript:alert(1)"]),
        'a data: link'              => fn (Calendar $c) => $c->event('2026-11-04', 'X', ['href' => 'data:text/html,<b>x</b>']),
        'a link that is no string'  => fn (Calendar $c) => $c->event('2026-11-04', 'X', ['href' => ['x']]),
        'allDay of "maybe"'         => fn (Calendar $c) => $c->event('2026-11-04', 'X', ['allDay' => 'maybe']),
        'a time that is no text'    => fn (Calendar $c) => $c->event('2026-11-04', 'X', ['time' => ['9']]),
        'an hour of 25'             => fn (Calendar $c) => $c->event('2026-11-04 25:00', 'X'),
    ];
    foreach ($cases as $what => $fn) {
        [$html, $notes] = calRender(function () use ($fn): string {
            $c = (new Calendar('c'))->month('2026-11')->today('2026-11-04');
            $fn($c);
            return $c->html();
        });
        T::eq(count($notes), 1, "$what: one warning (" . implode(' / ', $notes) . ')');
        T::ok(str_starts_with($notes[0] ?? '', "GridKit: calendar 'c': "), "$what: it says who speaks");
        T::contains($html, 'class="gk-cal-grid"', "$what: the calendar renders");
        T::notContains($html, 'script:', "$what: no script URL");
        T::notContains($html, 'data:text', "$what: no data URL");
    }
    [$html] = calRender(fn () => (new Calendar('c'))->month('2026-11')->event(['start' => '2026-11-09', 'end' => '2026-11-04'], 'Swapped')->html());
    T::contains($html, 'data-gk-cal-start="2026-11-04" data-gk-cal-end="2026-11-09"', 'an end before the start is swapped');
    [$html] = calRender(fn () => (new Calendar('c'))->month('2026-11')->event('2026-11-04', 'Text', ['href' => 'javascript:x()'])->html());
    T::contains($html, '<span class="gk-cal-event gk-cal-accent" data-gk-cal-event="e1"', 'a refused link leaves the event as text');
},

'a thousand events stay a page, not a hang' => function (): void {
    $c = (new Calendar('c'))->month('2026-11');
    for ($i = 0; $i < 1000; $i++) {
        $c->event(['start' => sprintf('2026-11-%02d', 1 + $i % 28), 'end' => sprintf('2026-11-%02d', min(30, 1 + $i % 28 + $i % 4))], "E$i");
    }
    $c->event(['start' => '0001-01-01', 'end' => '9999-12-31'], 'Forever');
    $t = microtime(true);
    [$html] = calRender(fn () => $c->html());
    T::ok(microtime(true) - $t < 3, 'rendered in under three seconds (' . round(microtime(true) - $t, 2) . ' s)');
    T::contains($html, 'Forever', 'an event of eight thousand years is in the month');
    T::eq(substr_count($html, '<li class="gk-cal-agenda-day'), 30, 'the agenda lists each day once');
},

// ── Contracts ───────────────────────────────────────────────────────────

'the script reads what the server writes; the skill documents the calendar' => function () use ($js, $doc): void {
    $c = $js();
    foreach (['data-gk-cal', 'data-gk-cal-month', 'data-gk-cal-today', 'data-gk-cal-url', 'data-gk-cal-go', 'data-gk-cal-date',
              'data-gk-cal-event', 'data-gk-cal-start', 'data-gk-cal-end', 'gk-cal-goto-today', 'gk-cal-daylink', 'gk-cal-more-list',
              '"0000-00"', 'gk:calendarnav', 'gk:calendarselect', '"grid"', '"gridcell"', '"button"', 'sessionStorage',
              '"ArrowRight"', '"ArrowLeft"', '"ArrowUp"', '"ArrowDown"', '"Home"', '"End"', '"PageUp"', '"PageDown"', '"Enter"', '" "', '"Escape"',
              'GK.calendar', 'data-gk-cal-ready'] as $w) {
        T::contains($c, $w, "the script uses $w");
    }
    T::ok((bool) preg_match('/try\s*\{\s*(?:var saved = )?sessionStorage/', $c), 'sessionStorage inside try');
    T::contains($c, 'new MutationObserver', 'a calendar inserted later is set up too');
    $d = $doc();
    T::ok((bool) preg_match('/^### Calendar\b/m', $d), 'a "### Calendar" section');
    foreach (['gk:calendarnav', 'gk:calendarselect', 'GK.calendar.month(', 'GK.calendar.focus(', 'GK.calendar.go(', 'GK.calendar.events(',
              'visibleRange()', 'maxPerDay(', '`allDay`', 'agenda', 'PageUp'] as $w) {
        T::contains($d, $w, "the skill names $w");
    }
    foreach (Calendar::COLORS as $color) T::contains($d, "`$color`", "the skill names the colour $color");
},

'the stylesheet block: roles only, both dark spellings, the agenda under 600px, a bar joined across cells' => function () use ($sheet): void {
    $s = $sheet();
    T::contains($s, 'container: gk-cal / inline-size', 'a size container');
    T::ok((bool) preg_match('/@container gk-cal \(max-width: 599\.98px\)\s*\{\s*\.gk-cal-grid\s*\{\s*display:\s*none;\s*\}\s*\.gk-cal-agenda\s*\{\s*display:\s*block;/', $s),
        'narrower than 600px the agenda replaces the grid');
    T::ok((bool) preg_match('/\n\.gk-cal-agenda\s*\{\s*display:\s*none;/', $s), 'wide: no agenda');
    T::contains($s, '[data-gk-mode="dark"] .gk-cal-more-list', 'dark, attribute spelling');
    T::contains($s, '.gk-dark .gk-cal-more-list', 'dark, class spelling');
    T::ok((bool) preg_match('/\.gk-cal-span-in\s*\{[^}]*margin-inline-start:\s*calc\(-1 \* var\(--gk-cal-pad\) - 1px\)/', $s), 'a joined segment reaches over the padding and the line');
    T::ok((bool) preg_match('/\.gk-cal-span-out\s*\{[^}]*margin-inline-end:\s*calc\(-1 \* var\(--gk-cal-pad\)\)/', $s), 'and the other way');
    T::ok((bool) preg_match('/\.gk-cal-span-head\s*\{[^}]*z-index:\s*1;[^}]*overflow:\s*visible/', $s), 'the head lies above the segments its title crosses');
    T::ok((bool) preg_match('/\.gk-cal-day:nth-last-child\(-n \+ 3\) \.gk-cal-more-list\s*\{[^}]*inset-inline-end/', $s), 'the last columns open "+n more" inwards');
    T::ok((bool) preg_match('/\.gk-cal-day\.gk-cal-today \.gk-cal-daynum time > \[aria-hidden\]\s*\{[^}]*background:\s*var\(--gk-primary\);[^}]*color:\s*var\(--gk-on-primary\)/', $s), 'today: a filled disc in the accent pair');
    T::ok((bool) preg_match('/\.gk-cal-accent\s*\{[^}]*var\(--gk-primary-container\)[^}]*var\(--gk-on-primary-container\)/', $s), 'an event without a colour: the accent\'s tonal pair');
    T::contains($s, '@media (forced-colors: active)', 'forced colours');
    preg_match_all('/(?<![-\w])(?:color|background(?:-color)?|border(?:-color)?)\s*:[^;]*(#[0-9a-f]{3,8}\b|rgba?\()/i', $s, $lit);
    T::eq($lit[0], [], 'no literal colour: roles only');
},

];
})();
