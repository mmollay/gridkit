<?php
use GridKit\Calendar;
use GridKit\DateRange;
use GridKit\Form;
use GridKit\TableHeader;

/*
 * The sample events are counted from today, so the demo never shows a month gone by: a meeting
 * today, a trade fair across the coming weekend, a campaign of ten days, a busy day that overflows.
 */
$dDay = static fn (int $n): string => (new DateTimeImmutable('today'))->modify(($n >= 0 ? '+' : '') . $n . ' days')->format('Y-m-d');
// Sixty invoices, one every six days back from today, for the filter bar to count.
$invoices = [];
for ($i = 0; $i < 60; $i++) $invoices[] = ['date' => $dDay(-6 * $i - $i % 3), 'amount' => 120 + ($i * 37) % 480 + 0.5 * ($i % 2)];
?>
<!-- ===== DATES (1.120.0) ===== -->
<div class="demo-section" data-section="dates">
    <h2>Dates <?= since('daterange', 'calendar') ?></h2>
    <p class="demo-intro">Two components for time: <code>GridKit\DateRange</code>, a period picker for reports and
        invoice lists, and <code>GridKit\Calendar</code>, a month view for appointments, deadlines and campaigns. Both
        work without any script — a select and two date fields that are sent, a table of links. <code>js/calendar.js</code>
        fills the dates when a preset is chosen, writes the period in words, and makes the month a grid for the keyboard.</p>

    <h3 class="demo-h3" id="dates-filter">A period in a filter bar</h3>
    <p class="demo-intro">In the bar of a <code>TableHeader</code>: choose a preset and the list is asked for it at once;
        type a date and the select says <em>Custom</em>, then <em>Apply</em>. The page reads the same period with
        <code>current()</code> (or <code>DateRange::fromRequest()</code>) — checked, in order, within <code>min()</code>
        and <code>max()</code>, "today" in the page's time zone — and filters its invoices by it.</p>
    <div class="demo-card">
        <?php
        showcase(function () use ($invoices) {
            $range = (new DateRange('period'))
                ->presets(['today', 'last-7-days', 'last-30-days', 'this-month', 'last-month', 'this-quarter', 'this-year'])
                ->default('last-30-days')
                ->filter()                       // its own GET form and Apply; a preset is sent at once
                ->baseUrl('#dates-filter')
                ->preserve(['lang']);
            $period = $range->current();     // ['from' => 'Y-m-d', 'to' => 'Y-m-d', 'preset' => 'last-30-days']

            TableHeader::make('invoices')
                ->filter(fn () => $range->render())
                ->reset('?#dates-filter')
                ->render();

            $found = array_filter($invoices, fn (array $i): bool => $i['date'] >= $period['from'] && $i['date'] <= $period['to']);
            echo '<pre class="demo-log">' . htmlspecialchars(json_encode($period + [
                'invoices' => count($found),
                'total'    => number_format(array_sum(array_column($found, 'amount')), 2, '.', ''),
            ])) . '</pre>';
        });
        ?>
    </div>

    <h3 class="demo-h3" id="dates-form">In a Form: the field type <code>daterange</code></h3>
    <p class="demo-intro">The field's options are the picker's; its label names the group, its message line describes
        it. The form below is sent with GET to this page and shows what it read.</p>
    <div class="demo-card">
        <?php
        showcase(function () use ($lang) {
            $read = DateRange::fromRequest('report', 'last-month', ['max' => date('Y-m-d')]);

            (new Form('report-form'))
                ->method('get')
                ->action('#dates-form')
                ->hidden('lang', $lang)
                ->row()
                    ->field('report', 'Period', 'daterange', ['width' => 10, 'default' => 'last-month',
                        'presets' => ['last-7-days', 'last-30-days', 'this-month', 'last-month', 'last-quarter', 'last-year'],
                        'max' => date('Y-m-d'), 'required' => true])
                    ->field('group', 'Grouped by', 'select', ['width' => 6, 'native' => true,
                        'options' => ['day' => 'Day', 'week' => 'Week', 'month' => 'Month']])
                ->endRow()
                ->values($_GET)
                ->submit('Show report')
                ->render();

            echo '<pre class="demo-log">' . htmlspecialchars(json_encode($read)) . '</pre>';
        });
        ?>
    </div>

    <h3 class="demo-h3" id="dates-calendar">A month of appointments</h3>
    <p class="demo-intro">Click into the month, then <kbd>←</kbd> <kbd>→</kbd> <kbd>↑</kbd> <kbd>↓</kbd> walk the days,
        <kbd>Home</kbd> and <kbd>End</kbd> go to the ends of the week, <kbd>PgUp</kbd> and <kbd>PgDn</kbd> to the month
        before and after (the page loads it, the day keeps the focus), <kbd>Enter</kbd> chooses the day, <kbd>Tab</kbd>
        reaches its events. A bar runs across the days of the fair and the campaign; a busy day shows three and
        <em>+3 more</em>, which opens the whole day. Narrower than 600px — on a phone — the month is an agenda.</p>
    <div class="demo-card">
        <pre class="demo-log" id="demo-cal-log" aria-label="Event log">Choose a day or an event.</pre>
        <?php
        showcase(function () use ($dDay) {
            (new Calendar('appointments'))
                ->label('Appointments')
                ->weekNumbers()
                ->baseUrl('?#dates-calendar')
                ->preserve(['lang'])
                ->dayUrl('#dates-calendar&day={date}')
                ->event($dDay(0) . ' 09:30', 'Team meeting', ['href' => '#dates-calendar', 'color' => 'blue'])
                ->event($dDay(0) . ' 14:00', 'Call with Acme <Ltd>', ['color' => 'green', 'id' => 'call-7'])
                ->event($dDay(2), 'Invoice run', ['color' => 'red', 'allDay' => true])
                ->event(['start' => $dDay(3), 'end' => $dDay(6)], 'Trade fair Vienna', ['color' => 'orange', 'href' => '#dates-calendar'])
                ->event(['start' => $dDay(4), 'end' => $dDay(13)], 'Winter campaign', ['href' => '#dates-calendar'])
                ->event(['start' => $dDay(3), 'end' => $dDay(4)], 'Booth set-up', ['color' => 'gray'])
                ->events([
                    ['date' => $dDay(8) . ' 08:00', 'title' => 'Breakfast with the board', 'color' => 'green'],
                    ['date' => $dDay(8) . ' 10:00', 'title' => 'Release 1.120', 'color' => 'blue'],
                    ['date' => $dDay(8) . ' 11:30', 'title' => 'Dentist'],
                    ['date' => $dDay(8) . ' 15:00', 'title' => 'Review of the quarter', 'color' => 'orange'],
                    ['date' => $dDay(8) . ' 17:00', 'title' => 'Yoga', 'color' => 'gray'],
                    ['date' => $dDay(-3), 'title' => 'Tax deadline', 'color' => 'red'],
                    ['date' => $dDay(-9) . ' 16:00', 'title' => 'Workshop: accessible forms'],
                    ['date' => $dDay(17), 'title' => 'Offsite', 'color' => 'blue'],
                ])
                ->render();
        });
        ?>
        <script>
        (function () {
            var log = document.getElementById('demo-cal-log');
            var say = function (t) { log.textContent = t; };
            document.addEventListener('gk:calendarselect', function (e) {
                if (e.detail.calendar !== 'appointments') return;
                say('gk:calendarselect ' + JSON.stringify({ date: e.detail.date, event: e.detail.event && e.detail.event.title,
                    events: e.detail.events.map(function (x) { return x.title; }) }));
            });
            document.addEventListener('gk:calendarnav', function (e) {
                if (e.detail.calendar === 'appointments') say('gk:calendarnav ' + JSON.stringify({ month: e.detail.month }));
            });
        })();
        </script>
    </div>

    <h3 class="demo-h3" id="dates-agenda">The same month in a side column: the agenda</h3>
    <p class="demo-intro">The calendar is a size container: in a column narrower than 600px it shows the days that hold
        something, one under the other — what a phone shows. Weeks start on Monday here, as in German.</p>
    <div class="demo-card" style="max-width: 380px">
        <?php
        showcase(function () use ($dDay) {
            (new Calendar('agenda'))
                ->weekStart('monday')
                ->baseUrl('?#dates-agenda')
                ->preserve(['lang'])
                ->event($dDay(0) . ' 09:30', 'Team meeting', ['href' => '#dates-agenda', 'color' => 'blue'])
                ->event(['start' => $dDay(3), 'end' => $dDay(6)], 'Trade fair Vienna', ['color' => 'orange'])
                ->event($dDay(8) . ' 10:00', 'Release 1.120', ['color' => 'blue'])
                ->render();
        });
        ?>
    </div>
</div>
