<?php
declare(strict_types=1);

namespace GridKit;

/**
 * Calendar — a month view for appointments, deadlines and campaigns (1.120.0).
 *
 *   (new Calendar('appointments'))
 *       ->label('Appointments')
 *       ->event('2026-11-04', 'Team meeting', ['time' => '09:30', 'href' => '/appointments/7', 'color' => 'blue'])
 *       ->event(['start' => '2026-11-10', 'end' => '2026-11-13'], 'Trade fair', ['color' => 'orange'])
 *       ->weekNumbers()
 *       ->render();
 *
 * The server writes the month as a plain table — a column per weekday, a row per week, every day
 * named by its full date, today marked — with the events as links (or text) in their days. A
 * multi-day event is one bar across the days it covers: a segment in each day, in the same lane of
 * the week, the title written once per week. A day that holds more than maxPerDay() shows the first
 * ones and "+3 more", a <details> that opens the whole day in place — no script needed. The months
 * are links with a query parameter (?month=2026-12), so the calendar works as plain links.
 *
 * Narrower than 600px (a container query, so a narrow column counts too) the same month is an
 * agenda: the days that hold something, one under the other, nothing that scrolls sideways.
 *
 * js/calendar.js makes the table a grid for the keyboard — arrows, Home/End, PageUp/PageDown for the
 * month — and tells the page what is chosen (gk:calendarselect) and where it goes (gk:calendarnav).
 *
 * Every misuse the class can see — a date that is none, an option it does not know, a colour outside
 * the label palette, a javascript: link — is an E_USER_WARNING, and the calendar still renders.
 */
class Calendar
{
    /** The label palette an event may take; without a colour it takes the theme's accent. */
    public const COLORS = ['blue', 'green', 'orange', 'red', 'gray'];

    private const EVENT_OPTS = ['href', 'color', 'time', 'allDay', 'id'];

    private string $id;
    private ?string $month = null;
    private string $param = 'month';
    private ?string $weekStart = null;
    private bool $weekNumbers = false;
    private ?string $timezone = null;
    private string|\DateTimeInterface|null $today = null;
    private string $baseUrl = '';
    /** @var array<int|string,mixed> */
    private array $preserve = [];
    private int $maxPerDay = 3;
    private string $label = '';
    private string $dayUrl = '';
    /**
     * @var list<array{start:string,end:string,title:string,href:?string,color:?string,time:?string,allDay:bool,id:string,n:int}>
     */
    private array $events = [];

    public function __construct(string $id = 'calendar')
    {
        $this->id = $id;
    }

    /* ── Options ─────────────────────────────────────────────────────────── */

    /** The month shown ('Y-m'); default the query parameter, else this month. */
    public function month(string $month): static
    {
        $m = self::monthOf($month);
        if ($m === null) {
            $this->warn("month('$month') is not a month (Y-m); the request or this month is shown.");
        } else {
            $this->month = $m;
        }
        return $this;
    }

    /** The query parameter that names the month in the links (default 'month': ?month=2026-12). */
    public function param(string $name): static
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_-]{0,63}$/', $name)) {
            $this->warn("param('$name') is not a plain name; it stays '{$this->param}'.");
            return $this;
        }
        $this->param = $name;
        return $this;
    }

    /** 'monday' or 'sunday'; default by the page's language — Sunday for English, Monday otherwise. */
    public function weekStart(string $day): static
    {
        $day = strtolower($day);
        if (!in_array($day, ['monday', 'sunday'], true)) {
            $this->warn("weekStart('$day') takes 'monday' or 'sunday'; the language decides.");
            return $this;
        }
        $this->weekStart = $day;
        return $this;
    }

    /** A column with the ISO 8601 week number of each row (the week of its Monday). */
    public function weekNumbers(bool $on = true): static
    {
        $this->weekNumbers = $on;
        return $this;
    }

    /** The time zone "today" is counted in; default date_default_timezone_get(). */
    public function timezone(string $tz): static
    {
        try {
            new \DateTimeZone($tz);
            $this->timezone = $tz;
        } catch (\Throwable) {
            $this->warn("timezone('$tz') is not a time zone; it stays " . ($this->timezone ?? date_default_timezone_get()) . '.');
        }
        return $this;
    }

    /** The day marked as today ('Y-m-d', or a moment read in timezone()) — for a fixed page and for tests. */
    public function today(string|\DateTimeInterface $today): static
    {
        if (is_string($today) && DateRange::date($today) === null) {
            $this->warn("today('$today') is not a day (Y-m-d); today stays today.");
            return $this;
        }
        $this->today = $today;
        return $this;
    }

    /** Where the month links lead; default the page itself. A #fragment is kept at the end. */
    public function baseUrl(string $url): static
    {
        $this->baseUrl = $url;
        return $this;
    }

    /**
     * The other query parameters the month links carry: names read from the URL (['user']), or
     * name => value.
     *
     * @param array<int|string,mixed> $params
     */
    public function preserve(array $params): static
    {
        $this->preserve = $params;
        return $this;
    }

    /** How many events a day shows before "+n more" (default 3, at least 1). */
    public function maxPerDay(int $n): static
    {
        if ($n < 1) {
            $this->warn("maxPerDay($n) — a day shows at least one event; it stays {$this->maxPerDay}.");
            return $this;
        }
        $this->maxPerDay = $n;
        return $this;
    }

    /** The accessible name of the calendar; default "Calendar". */
    public function label(string $text): static
    {
        $this->label = $text;
        return $this;
    }

    /**
     * Makes the number of every day a link: '/appointments/new?date={date}' — {date} becomes the day
     * (Y-m-d). Enter on a day follows it.
     */
    public function dayUrl(string $url): static
    {
        if (self::unsafeUrl($url)) {
            $this->warn('dayUrl() is a script URL; it is left out.');
            return $this;
        }
        $this->dayUrl = $url;
        return $this;
    }

    /**
     * One event. $when: a day ('2026-11-04'), a day with a time ('2026-11-04 09:30'), a
     * DateTimeInterface, or ['start' => …, 'end' => …] (the last day included) for one that lasts
     * several days. Options: 'href', 'color' (blue, green, orange, red, gray), 'time' (text shown before
     * the title; default the time of the start), 'allDay', 'id' (handed to the script's events).
     *
     * @param string|\DateTimeInterface|array<string,mixed> $when
     * @param array<string,mixed> $opts
     */
    public function event(string|\DateTimeInterface|array $when, string $title, array $opts = []): static
    {
        $this->addEvent($when, $title, $opts);
        return $this;
    }

    /**
     * Many events from rows (a database result). Each row has 'date', or 'start' and maybe 'end', a
     * 'title' and any event option under its own name; $map renames them: ['title' => 'subject',
     * 'start' => 'begins_at', 'end' => 'ends_at'].
     *
     * @param iterable<mixed> $rows
     * @param array<string,string> $map
     */
    public function events(iterable $rows, array $map = []): static
    {
        foreach ($rows as $i => $row) {
            if (is_object($row)) $row = get_object_vars($row);
            if (!is_array($row)) {
                $this->warn("events(): row $i is not an array; it is left out.");
                continue;
            }
            $key = static fn(string $k): string => $map[$k] ?? $k;
            $title = $row[$key('title')] ?? null;
            if (!is_scalar($title)) {
                $this->warn("events(): row $i has no title; it is left out.");
                continue;
            }
            $when = isset($row[$key('date')])
                ? $row[$key('date')]
                : ['start' => $row[$key('start')] ?? null, 'end' => $row[$key('end')] ?? null];
            $opts = [];
            foreach (self::EVENT_OPTS as $o) {
                if (array_key_exists($key($o), $row) && $row[$key($o)] !== null) $opts[$o] = $row[$key($o)];
            }
            if (!is_string($when) && !is_array($when) && !$when instanceof \DateTimeInterface) {
                $this->warn("events(): row $i has no date; it is left out.");
                continue;
            }
            $this->addEvent($when, (string) $title, $opts);
        }
        return $this;
    }

    /* ── Reading ─────────────────────────────────────────────────────────── */

    /** The month shown, 'Y-m': month(), else the query parameter when it names a month, else this month. */
    public function current(): string
    {
        if ($this->month !== null) return $this->month;
        $got = $_GET[$this->param] ?? null;
        $m = is_string($got) ? self::monthOf($got) : null;
        return $m ?? substr($this->todayDate(), 0, 7);
    }

    /**
     * The first and the last day the grid shows (the days of the weeks around the month), for the
     * query that fetches the events: ['from' => 'Y-m-d', 'to' => 'Y-m-d'].
     *
     * @return array{from:string,to:string}
     */
    public function visibleRange(): array
    {
        $days = $this->gridDays();
        return ['from' => $days[0], 'to' => $days[count($days) - 1]];
    }

    /* ── Output ──────────────────────────────────────────────────────────── */

    /** Prints the calendar. */
    public function render(): void
    {
        echo $this->html();
    }

    /** The calendar as a string. */
    public function html(): string
    {
        $e = static fn(mixed $s): string => htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $ym = $this->current();
        [$y, $m] = [(int) substr($ym, 0, 4), (int) substr($ym, 5, 2)];
        $today = $this->todayDate();
        $days = $this->gridDays();
        $monday = $this->startsMonday();
        $months = self::words('date.months', 12);
        $wdays = self::words('date.weekdays', 7);
        $wdaysShort = self::words('date.weekdays_short', 7);
        $slug = 'gk-cal-' . (preg_replace('/[^A-Za-z0-9_-]/', '', $this->id) ?: 'calendar');
        $title = Lang::t('date.month_year', ['month' => $months[$m - 1], 'year' => $y]);
        $label = $this->label !== '' ? $this->label : Lang::t('calendar.label');

        $prev = $m === 1 ? sprintf('%04d-12', $y - 1) : sprintf('%04d-%02d', $y, $m - 1);
        $next = $m === 12 ? sprintf('%04d-01', $y + 1) : sprintf('%04d-%02d', $y, $m + 1);
        $thisMonth = substr($today, 0, 7);

        $out = '<div class="gk-cal" id="' . $e($slug) . '" data-gk-cal="' . $e($this->id) . '"'
             . ' data-gk-cal-month="' . $e($ym) . '" data-gk-cal-today="' . $e($today) . '"'
             . ' data-gk-cal-url="' . $e($this->url('0000-00')) . '">';

        // The head: the month in words, and the way to the months around it.
        $out .= '<div class="gk-cal-head">'
              . '<div class="gk-cal-title" id="' . $e($slug) . '-title">' . $e($title) . '</div>'
              . '<div class="gk-cal-nav" role="group" aria-label="' . $e(Lang::t('calendar.nav')) . '">'
              . $this->navLink($prev, 'chevron_left', Lang::t('calendar.prev'), 'gk-cal-prev', $y > 1 || $m > 1, $e)
              . '<a class="gk-btn gk-btn-outlined gk-btn-neutral gk-btn-sm gk-cal-goto-today" href="' . $e($this->url($thisMonth)) . '"'
              . ' data-gk-cal-go="' . $e($thisMonth) . '">'
              . $e(Lang::t('calendar.today')) . '</a>'
              . $this->navLink($next, 'chevron_right', Lang::t('calendar.next'), 'gk-cal-next', $y < 9999 || $m < 12, $e)
              . '</div></div>';

        // ── The grid ─────────────────────────────────────────────────────
        $out .= '<table class="gk-cal-grid" aria-labelledby="' . $e($slug) . '-caption ' . $e($slug) . '-title">'
              . '<caption class="gk-sr-only" id="' . $e($slug) . '-caption">' . $e($label) . '</caption><thead><tr>';
        if ($this->weekNumbers) {
            $out .= '<th scope="col" class="gk-cal-wk"><span aria-hidden="true">' . $e(Lang::t('calendar.week_short')) . '</span>'
                  . '<span class="gk-sr-only">' . $e(Lang::t('calendar.week_head')) . '</span></th>';
        }
        for ($i = 0; $i < 7; $i++) {
            $w = $monday ? $i : ($i + 6) % 7;
            $out .= '<th scope="col" class="gk-cal-wd"><span aria-hidden="true">' . $e($wdaysShort[$w]) . '</span>'
                  . '<span class="gk-sr-only">' . $e($wdays[$w]) . '</span></th>';
        }
        $out .= '</tr></thead><tbody>';

        $weeks = array_chunk($days, 7);
        $placed = [];
        foreach ($weeks as $week) {
            $layout = $this->layWeek($week);
            $placed[] = $layout;
            $out .= '<tr>';
            if ($this->weekNumbers) {
                $mon = $monday ? $week[0] : $week[1];
                $wk = (int) (new \DateTimeImmutable($mon . ' 12:00:00', new \DateTimeZone('UTC')))->format('W');
                $out .= '<th scope="row" class="gk-cal-wk"><span aria-hidden="true">' . $wk . '</span>'
                      . '<span class="gk-sr-only">' . $e(Lang::t('calendar.week', ['n' => $wk])) . '</span></th>';
            }
            foreach ($week as $c => $day) {
                $out .= $this->dayCell($day, $c, $layout, $ym, $today, $e);
            }
            $out .= '</tr>';
        }
        $out .= '</tbody></table>';

        // ── The agenda (a narrow container shows it instead of the grid) ──
        $out .= $this->agenda($ym, $today, $title, $label, $e);
        $out .= '</div>';
        return $out;
    }

    /* ── Inside: the layout of a week ────────────────────────────────────── */

    /**
     * Where every event of a week goes: events that cover two days or more of the week share a lane
     * across them (so the bar is straight), the others fill the lanes left free, day by day. Then each
     * day shows the lanes below the limit — maxPerDay(), or one less when "+n more" needs the last line.
     *
     * @param list<string> $week
     * @return array{lane: array<string, array<int,int>>, shown: array<string, array<int,int>>, all: array<string, list<int>>}
     *   lane: day => [lane => event index]; shown: the lanes a day shows; all: day => its events in time order
     */
    private function layWeek(array $week): array
    {
        [$first, $last] = [$week[0], $week[6]];
        $in = [];
        foreach ($this->events as $k => $ev) {
            if ($ev['end'] >= $first && $ev['start'] <= $last) $in[] = $k;
        }
        $order = function (int $a, int $b): int {
            $x = $this->events[$a];
            $y = $this->events[$b];
            return [$x['start'], $this->timed($x), $x['time'] ?? '', $y['end'], $x['n']]
               <=> [$y['start'], $this->timed($y), $y['time'] ?? '', $x['end'], $y['n']];
        };
        usort($in, $order);

        $lane = array_fill_keys($week, []);
        // 1. Bars across two days or more of this week: one lane for all their days.
        foreach ($in as $k) {
            $ev = $this->events[$k];
            $span = array_values(array_filter($week, static fn(string $d): bool => $d >= $ev['start'] && $d <= $ev['end']));
            if (count($span) < 2) continue;
            for ($l = 0; ; $l++) {
                $free = true;
                foreach ($span as $d) if (isset($lane[$d][$l])) { $free = false; break; }
                if ($free) break;
            }
            foreach ($span as $d) $lane[$d][$l] = $k;
        }
        // 2. Everything else, day by day, in the lowest free lane.
        $all = array_fill_keys($week, []);
        foreach ($week as $d) {
            foreach ($in as $k) {
                $ev = $this->events[$k];
                if ($d < $ev['start'] || $d > $ev['end']) continue;
                $all[$d][] = $k;
                if (in_array($k, $lane[$d], true)) continue;
                for ($l = 0; isset($lane[$d][$l]); $l++);
                $lane[$d][$l] = $k;
            }
            ksort($lane[$d]);
        }
        // 3. What each day shows.
        $shown = [];
        foreach ($week as $d) {
            $limit = (count($lane[$d]) > 0 && max(array_keys($lane[$d])) >= $this->maxPerDay)
                || count($lane[$d]) > $this->maxPerDay
                ? $this->maxPerDay - 1 : $this->maxPerDay;
            $shown[$d] = array_filter($lane[$d], static fn(int $l): bool => $l < $limit, ARRAY_FILTER_USE_KEY);
        }
        return ['lane' => $lane, 'shown' => $shown, 'all' => $all];
    }

    /** 0 for an event without a time (all day, or spanning days), 1 for one at a time: they come first. */
    private function timed(array $ev): int
    {
        return ($ev['allDay'] || $ev['time'] === null || $ev['start'] !== $ev['end']) ? 0 : 1;
    }

    /**
     * One day of the grid: its number with its full date for a screen reader, its events in their lanes
     * (empty lanes above a bar keep their place), "+n more" when the day holds more than it shows.
     *
     * @param array{lane: array<string, array<int,int>>, shown: array<string, array<int,int>>, all: array<string, list<int>>} $layout
     */
    private function dayCell(string $day, int $col, array $layout, string $ym, string $today, \Closure $e): string
    {
        // Before 1 January of year 1 or after 9999 (the weeks around ?month=0001-01 and 9999-12): no day.
        if (DateRange::date($day) === null) return '<td class="gk-cal-day gk-cal-outside"></td>';
        $outside = substr($day, 0, 7) !== $ym;
        $isToday = $day === $today;
        $cls = 'gk-cal-day' . ($outside ? ' gk-cal-outside' : '') . ($isToday ? ' gk-cal-today' : '');
        $full = $this->fullDate($day);
        $out = '<td class="' . $cls . '" data-gk-cal-date="' . $day . '"' . ($isToday ? ' aria-current="date"' : '') . '>';

        $num = '<span aria-hidden="true">' . (int) substr($day, 8, 2) . '</span>'
             . '<span class="gk-sr-only">' . $e($full) . ($isToday ? ', ' . $e(Lang::t('calendar.today_mark')) : '') . '</span>';
        $out .= '<div class="gk-cal-daynum">'
              . ($this->dayUrl !== ''
                  ? '<a class="gk-cal-daylink" href="' . $e(str_replace('{date}', $day, $this->dayUrl)) . '"><time datetime="' . $day . '">' . $num . '</time></a>'
                  : '<time datetime="' . $day . '">' . $num . '</time>')
              . '</div>';

        $shown = $layout['shown'][$day];
        $all = $layout['all'][$day];
        $out .= '<div class="gk-cal-events">';
        if ($shown !== []) {
            $top = max(array_keys($shown));
            for ($l = 0; $l <= $top; $l++) {
                if (!isset($shown[$l])) {
                    $out .= '<span class="gk-cal-slot" aria-hidden="true"></span>';
                    continue;
                }
                $out .= $this->chip($shown[$l], $day, $col, $layout, $e);
            }
        }
        $hidden = count($all) - count($shown);
        if ($hidden > 0) {
            $out .= '<details class="gk-cal-more"><summary>' . $e(Lang::t('calendar.more', ['n' => $hidden]))
                  . '<span class="gk-sr-only"> ' . $e(Lang::t('calendar.more_on', ['date' => $full])) . '</span></summary>'
                  . '<div class="gk-cal-more-list" role="group" aria-label="' . $e($full) . '">'
                  . '<div class="gk-cal-more-date" aria-hidden="true">' . $e($full) . '</div><ul>';
            foreach ($all as $k) $out .= '<li>' . $this->listChip($k, $e) . '</li>';
            $out .= '</ul></div></details>';
        }
        $out .= '</div></td>';
        return $out;
    }

    /**
     * An event in a day of the grid. A bar across days is a segment in each of them: joined to the day
     * before or after when that day shows it too (gk-cal-span-in / -out), its title written in the
     * first segment of each visible run and carried across the run (--gk-cal-days), and read in every
     * segment by a screen reader.
     *
     * @param array{lane: array<string, array<int,int>>, shown: array<string, array<int,int>>, all: array<string, list<int>>} $layout
     */
    private function chip(int $k, string $day, int $col, array $layout, \Closure $e): string
    {
        $ev = $this->events[$k];
        $multi = $ev['start'] !== $ev['end'];
        $cls = 'gk-cal-event' . ($ev['color'] !== null ? ' gk-label-' . $ev['color'] : ' gk-cal-accent');
        $style = '';
        $head = true;
        if ($multi) {
            $days = array_keys($layout['shown']);
            $visible = static fn(int $c): bool => isset($days[$c]) && in_array($k, $layout['shown'][$days[$c]], true);
            $in = $visible($col - 1);
            $out = $visible($col + 1);
            $cls .= ' gk-cal-span' . ($in ? ' gk-cal-span-in' : '') . ($out ? ' gk-cal-span-out' : '');
            $head = !$in;
            if ($head) {
                $run = 1;
                while ($visible($col + $run)) $run++;
                if ($run > 1) {
                    $cls .= ' gk-cal-span-head';
                    $style = ' style="--gk-cal-days: ' . $run . '"';
                }
            } else {
                $cls .= ' gk-cal-span-tail';
            }
        }
        $time = $ev['time'] !== null && !$ev['allDay'] && $day === $ev['start']
            ? '<span class="gk-cal-time">' . $e($ev['time']) . '</span> ' : '';
        $body = $time . '<span class="gk-cal-event-title' . ($head ? '' : ' gk-sr-only') . '">' . $e($ev['title']) . '</span>';
        return $this->tag($ev, $cls, $style, $body, $e);
    }

    /** An event in a list (the "+n more" box, the agenda): its time — or "All day", or its days — and its title. */
    private function listChip(int $k, \Closure $e): string
    {
        $ev = $this->events[$k];
        $cls = 'gk-cal-event gk-cal-event-row' . ($ev['color'] !== null ? ' gk-label-' . $ev['color'] : ' gk-cal-accent');
        $when = $ev['start'] !== $ev['end']
            ? DateRange::text($ev['start'], $ev['end'])
            : ($ev['allDay'] || $ev['time'] === null ? Lang::t('calendar.all_day') : $ev['time']);
        $body = '<span class="gk-cal-time">' . $e($when) . '</span> <span class="gk-cal-event-title">' . $e($ev['title']) . '</span>';
        return $this->tag($ev, $cls, '', $body, $e);
    }

    /** The element of an event: a link with 'href', else text the script can make a button of. */
    private function tag(array $ev, string $cls, string $style, string $body, \Closure $e): string
    {
        $attrs = ' class="' . $cls . '"' . $style . ' data-gk-cal-event="' . $e($ev['id']) . '"'
               . ' data-gk-cal-start="' . $ev['start'] . '" data-gk-cal-end="' . $ev['end'] . '"';
        return $ev['href'] !== null
            ? '<a href="' . $e($ev['href']) . '"' . $attrs . '>' . $body . '</a>'
            : '<span' . $attrs . '>' . $body . '</span>';
    }

    /** The month as a list of the days that hold something — what a narrow container shows. */
    private function agenda(string $ym, string $today, string $title, string $label, \Closure $e): string
    {
        $byDay = [];
        $order = array_keys($this->events);
        usort($order, fn(int $a, int $b): int => [$this->timed($this->events[$a]), $this->events[$a]['time'] ?? '', $this->events[$a]['n']]
            <=> [$this->timed($this->events[$b]), $this->events[$b]['time'] ?? '', $this->events[$b]['n']]);
        // Day by day through the month: an event of a year still costs one look per day, never a loop of its own.
        $days = self::daysIn((int) substr($ym, 0, 4), (int) substr($ym, 5, 2));
        for ($i = 1; $i <= $days; $i++) {
            $d = $ym . '-' . sprintf('%02d', $i);
            foreach ($order as $k) {
                if ($this->events[$k]['start'] <= $d && $this->events[$k]['end'] >= $d) $byDay[$d][] = $k;
            }
        }
        $out = '<div class="gk-cal-agenda" role="region" aria-label="' . $e($label . ', ' . $title) . '">';
        if ($byDay === []) {
            return $out . '<p class="gk-cal-empty">' . $e(Lang::t('calendar.empty')) . '</p></div>';
        }
        $out .= '<ol class="gk-cal-agenda-list">';
        foreach ($byDay as $day => $list) {
            $day = (string) $day;
            $isToday = $day === $today;
            $out .= '<li class="gk-cal-agenda-day' . ($isToday ? ' gk-cal-today' : '') . '"' . ($isToday ? ' aria-current="date"' : '') . '>'
                  . '<div class="gk-cal-agenda-date"><time datetime="' . $day . '">' . $e($this->fullDate($day)) . '</time>'
                  . ($isToday ? ' <span class="gk-cal-today-mark">' . $e(Lang::t('calendar.today')) . '</span>' : '') . '</div><ul>';
            foreach ($list as $k) $out .= '<li>' . $this->listChip($k, $e) . '</li>';
            $out .= '</ul></li>';
        }
        return $out . '</ol></div>';
    }

    /** A month link of the head; one that would leave the years 1 to 9999 is drawn switched off. */
    private function navLink(string $month, string $icon, string $name, string $cls, bool $ok, \Closure $e): string
    {
        $glyph = '<span class="material-icons" aria-hidden="true">' . $icon . '</span>';
        if (!$ok) {
            return '<span class="gk-btn gk-btn-text gk-btn-neutral gk-btn-sm gk-btn-icon-only ' . $cls . '" role="link" aria-disabled="true"'
                 . ' aria-label="' . $e($name) . '">' . $glyph . '</span>';
        }
        return '<a class="gk-btn gk-btn-text gk-btn-neutral gk-btn-sm gk-btn-icon-only ' . $cls . '" href="' . $e($this->url($month)) . '"'
             . ' data-gk-cal-go="' . $e($month) . '" aria-label="' . $e($name) . '" title="' . $e($name) . '">' . $glyph . '</a>';
    }

    /* ── Inside: input ───────────────────────────────────────────────────── */

    /** @param string|\DateTimeInterface|array<string,mixed> $when @param array<string,mixed> $opts */
    private function addEvent(string|\DateTimeInterface|array $when, string $title, array $opts): void
    {
        $show = $title === '' ? '(untitled)' : $title;
        if (is_array($when)) {
            $start = $this->point($when['start'] ?? null);
            $end = array_key_exists('end', $when) && $when['end'] !== null && $when['end'] !== '' ? $this->point($when['end']) : $start;
            if ($start === null) {
                $this->warn("event '$show' has no start that is a day (Y-m-d); it is left out.");
                return;
            }
            if ($end === null) {
                $this->warn("event '$show' has an end that is not a day; it ends on its first day.");
                $end = $start;
            }
            $unknownKeys = array_diff(array_keys($when), ['start', 'end']);
            if ($unknownKeys !== []) $this->warn("event '$show': the period knows 'start' and 'end', not " . implode(', ', $unknownKeys) . '.');
        } else {
            $start = $this->point($when);
            if ($start === null) {
                $this->warn("event '$show' has no date that is a day (Y-m-d); it is left out.");
                return;
            }
            $end = $start;
        }
        if ($end[0] < $start[0]) {
            $this->warn("event '$show' ends before it starts; the two days are swapped.");
            [$start, $end] = [$end, $start];
        }

        $unknown = array_diff(array_keys($opts), self::EVENT_OPTS);
        if ($unknown !== []) $this->warn("event '$show' has unknown options " . implode(', ', $unknown) . '; they are ignored.');

        $href = null;
        if (isset($opts['href'])) {
            if (!is_string($opts['href']) || $opts['href'] === '') {
                $this->warn("event '$show': 'href' is not a URL; the event is text.");
            } elseif (self::unsafeUrl($opts['href'])) {
                $this->warn("event '$show' has a script URL as its href; it was left out, the event is text.");
            } else {
                $href = $opts['href'];
            }
        }
        $color = null;
        if (isset($opts['color'])) {
            if (is_string($opts['color']) && in_array($opts['color'], self::COLORS, true)) {
                $color = $opts['color'];
            } else {
                $this->warn("event '$show': color " . var_export($opts['color'], true) . ' is not one of ' . implode(', ', self::COLORS) . '; it takes the accent.');
            }
        }
        $allDay = false;
        if (array_key_exists('allDay', $opts)) {
            $b = self::yesNo($opts['allDay']);
            if ($b === null) $this->warn("event '$show': 'allDay' is not yes or no; it is ignored.");
            else $allDay = $b;
        }
        $time = $start[1];
        if (isset($opts['time'])) {
            if (is_scalar($opts['time']) && trim((string) $opts['time']) !== '') $time = trim((string) $opts['time']);
            else $this->warn("event '$show': 'time' is not text; it is ignored.");
        }
        $id = isset($opts['id']) && is_scalar($opts['id']) ? (string) $opts['id'] : '';
        $n = count($this->events);
        $this->events[] = ['start' => $start[0], 'end' => $end[0], 'title' => $title, 'href' => $href, 'color' => $color,
            'time' => $allDay ? null : $time, 'allDay' => $allDay, 'id' => $id !== '' ? $id : 'e' . ($n + 1), 'n' => $n];
    }

    /**
     * A day and maybe a time out of what an event was given: 'Y-m-d', 'Y-m-d H:i[:s]', 'Y-m-dTH:i…', or
     * a DateTimeInterface (read in timezone(); midnight is no time).
     *
     * @return array{0:string,1:?string}|null
     */
    private function point(mixed $v): ?array
    {
        if ($v instanceof \DateTimeInterface) {
            $at = \DateTimeImmutable::createFromInterface($v)->setTimezone($this->zone());
            $time = $at->format('H:i:s') === '00:00:00' ? null : $at->format('H:i');
            return DateRange::date($at->format('Y-m-d')) !== null ? [$at->format('Y-m-d'), $time] : null;
        }
        if (!is_string($v)) return null;
        if (!preg_match('/^\s*(\d{4}-\d{2}-\d{2})(?:[ T](\d{2}):(\d{2})(?::\d{2}(?:\.\d+)?)?)?\s*$/', $v, $m)) return null;
        $day = DateRange::date($m[1]);
        if ($day === null) return null;
        $time = null;
        if (isset($m[2]) && $m[2] !== '') {
            if ((int) $m[2] > 23 || (int) $m[3] > 59) return null;
            $time = $m[2] . ':' . $m[3];
        }
        return [$day, $time];
    }

    /* ── Inside: days ────────────────────────────────────────────────────── */

    /** Every day the grid shows: whole weeks from the one the month starts in to the one it ends in. @return list<string> */
    private function gridDays(): array
    {
        $ym = $this->current();
        [$y, $m] = [(int) substr($ym, 0, 4), (int) substr($ym, 5, 2)];
        $first = $ym . '-01';
        $dow = (int) (new \DateTimeImmutable($first . ' 12:00:00', new \DateTimeZone('UTC')))->format('N'); // 1 Mon … 7 Sun
        $lead = $this->startsMonday() ? $dow - 1 : $dow % 7;
        $count = (int) ceil(($lead + self::daysIn($y, $m)) / 7) * 7;
        $start = self::addDays($first, -$lead);
        $days = [];
        for ($i = 0; $i < $count; $i++) $days[] = self::addDays($start, $i);
        return $days;
    }

    private function startsMonday(): bool
    {
        return ($this->weekStart ?? (str_starts_with(strtolower(Lang::locale()), 'en') ? 'sunday' : 'monday')) === 'monday';
    }

    /** "Wednesday, November 4, 2026" — the name of a day in the page's language. */
    private function fullDate(string $day): string
    {
        [$y, $m, $d] = array_map('intval', explode('-', $day));
        $dow = (int) (new \DateTimeImmutable($day . ' 12:00:00', new \DateTimeZone('UTC')))->format('N');
        return Lang::t('date.full', ['weekday' => self::words('date.weekdays', 7)[$dow - 1],
            'month' => self::words('date.months', 12)[$m - 1], 'day' => $d, 'year' => $y]);
    }

    /** A month link: the base URL, the kept parameters and the month, the #fragment last. */
    private function url(string $month): string
    {
        $base = $this->baseUrl !== '' ? $this->baseUrl : (string) strtok((string) ($_SERVER['REQUEST_URI'] ?? ''), '?');
        $fragment = '';
        if (($hash = strpos($base, '#')) !== false) {
            $fragment = substr($base, $hash);
            $base = substr($base, 0, $hash);
        }
        $params = [];
        if (($q = strpos($base, '?')) !== false) {
            parse_str(substr($base, $q + 1), $params);
            $base = substr($base, 0, $q);
        }
        foreach ($this->preserve as $k => $v) {
            if (is_int($k)) {
                if (!is_string($v) || $v === $this->param) continue;
                $got = $_GET[$v] ?? null;
                if (is_scalar($got) && (string) $got !== '') $params[$v] = (string) $got;
            } elseif ($k !== $this->param && is_scalar($v)) {
                $params[$k] = (string) $v;
            }
        }
        $params[$this->param] = $month;
        return $base . '?' . http_build_query($params) . $fragment;
    }

    private function todayDate(): string
    {
        if (is_string($this->today)) return $this->today;
        $at = $this->today === null
            ? new \DateTimeImmutable('now', $this->zone())
            : \DateTimeImmutable::createFromInterface($this->today)->setTimezone($this->zone());
        return $at->format('Y-m-d');
    }

    private function zone(): \DateTimeZone
    {
        try {
            return new \DateTimeZone($this->timezone ?? date_default_timezone_get());
        } catch (\Throwable) {
            return new \DateTimeZone('UTC');
        }
    }

    /** 'Y-m' when it names a month of the years 1 to 9999, else null. */
    private static function monthOf(string $v): ?string
    {
        if (!preg_match('/^(\d{4})-(\d{2})$/', $v, $m)) return null;
        return (int) $m[1] >= 1 && (int) $m[2] >= 1 && (int) $m[2] <= 12 ? $v : null;
    }

    private static function daysIn(int $y, int $m): int
    {
        if ($m === 2) return ($y % 4 === 0 && ($y % 100 !== 0 || $y % 400 === 0)) ? 29 : 28;
        return in_array($m, [4, 6, 9, 11], true) ? 30 : 31;
    }

    /** A day plus n days, on the calendar (UTC: no day of 23 or 25 hours). */
    private static function addDays(string $date, int $n): string
    {
        return (new \DateTimeImmutable($date . ' 12:00:00', new \DateTimeZone('UTC')))
            ->modify(($n >= 0 ? '+' : '') . $n . ' days')->format('Y-m-d');
    }

    /** @return list<string> */
    private static function words(string $key, int $n): array
    {
        $list = explode('|', Lang::t($key));
        for ($i = count($list); $i < $n; $i++) $list[] = (string) ($i + 1);
        return array_slice($list, 0, $n);
    }

    private static function yesNo(mixed $v): ?bool
    {
        if (is_bool($v)) return $v;
        if ($v === 1 || $v === '1' || $v === 'yes') return true;
        if ($v === 0 || $v === '0' || $v === 'no') return false;
        return null;
    }

    /** A URL a link must not carry: javascript:, vbscript:, data: (spaces and control characters ignored). */
    private static function unsafeUrl(string $url): bool
    {
        return (bool) preg_match('/^(?:javascript|vbscript|data):/i', (string) preg_replace('/[\x00-\x20]+/', '', $url));
    }

    private function warn(string $message): void
    {
        trigger_error("GridKit: calendar '{$this->id}': $message", E_USER_WARNING);
    }
}
