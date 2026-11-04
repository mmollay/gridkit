<?php
declare(strict_types=1);

namespace GridKit;

/**
 * DateRange — a period picker for reports and invoice lists (1.120.0).
 *
 *   (new DateRange('period'))
 *       ->presets(['this-month', 'last-month', 'this-quarter', 'this-year', 'custom'])
 *       ->default('this-month')
 *       ->filter()                     // its own GET form with an Apply button
 *       ->preserve(['status'])
 *       ->render();
 *
 *   ['from' => $from, 'to' => $to] = DateRange::fromRequest('period', 'this-month');
 *
 * Two native <input type="date"> and a <select> of presets. Without any script it works as it is:
 * choose a preset or type the dates, send. With js/calendar.js a preset fills the dates at once,
 * typing a date switches the select to "custom", and the period is written out in words beside them
 * ("Oct 1 – 31, 2026", "1.–31. Okt. 2026").
 *
 * What is posted: name[preset], name[from], name[to] and name[shown] — the dates the page showed. The
 * last one is how the server tells, without a script, whether the person changed the select or the
 * dates: a preset wins unless the dates were changed since the page was drawn. The script keeps the
 * three in step and leaves name[shown] out, so a preset that comes back is counted from today again.
 *
 * Every date the class hands back has been checked (a real day, 0001 to 9999), put in order
 * (to ≥ from) and held within min() and max(). "Today" is today in timezone(), and every step from
 * it is taken on the calendar, not in hours — a day of 23 or 25 hours changes nothing. A wrong
 * option is an E_USER_WARNING and the default; nothing throws.
 */
class DateRange
{
    /** Every preset, in the order they are offered. 'custom' stands for the dates as typed. */
    public const PRESETS = ['today', 'yesterday', 'last-7-days', 'last-30-days', 'this-month', 'last-month',
        'this-quarter', 'last-quarter', 'this-year', 'last-year', 'custom'];

    /** The days a date input can hold: four-digit years only. */
    private const FIRST = '0001-01-01';
    private const LAST = '9999-12-31';

    /** Options fromRequest() reads. */
    private const REQUEST_OPTS = ['presets', 'min', 'max', 'timezone', 'today', 'request'];

    private string $name;
    /** @var list<string> */
    private array $presets = self::PRESETS;
    /** @var string|array{from:string,to:string} */
    private string|array $default = 'this-month';
    private mixed $value = null;
    private bool $valueSet = false;
    private ?string $min = null;
    private ?string $max = null;
    private ?string $timezone = null;
    private string|\DateTimeInterface|null $today = null;
    private string $label = '';
    private bool $filter = false;
    private string $baseUrl = '';
    /** @var array<int|string,mixed> */
    private array $preserve = [];
    private bool $required = false;
    /** @var array<mixed>|null what fromRequest() hands in instead of $_GET / $_POST */
    private ?array $request = null;

    /** Set by forForm(): the field's label, message line and state. */
    private ?string $labelledBy = null;
    private ?string $describedBy = null;
    private bool $invalid = false;

    public function __construct(string $name = 'period')
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_-]{0,63}$/', $name)) {
            $clean = preg_replace('/[^A-Za-z0-9_-]/', '', $name) ?? '';
            if ($clean === '' || !preg_match('/^[A-Za-z_]/', $clean)) $clean = 'period';
            $clean = substr($clean, 0, 64);
            self::warn("the name '$name' is not a plain name (letters, digits, _ and -); it posts as '$clean'.");
            $name = $clean;
        }
        $this->name = $name;
    }

    /* ── Options ─────────────────────────────────────────────────────────── */

    /**
     * The presets offered, in this order. 'custom' (the dates as typed) is added at the end when it is
     * missing; an empty list shows no select, only the two dates.
     *
     * @param iterable<mixed> $keys
     */
    public function presets(iterable $keys): static
    {
        $list = [];
        foreach ($keys as $key) {
            if (!is_string($key) || !in_array($key, self::PRESETS, true)) {
                self::warn('presets() does not know ' . var_export($key, true) . '; it is left out. Known: ' . implode(', ', self::PRESETS) . '.');
                continue;
            }
            if (!in_array($key, $list, true)) $list[] = $key;
        }
        if ($list !== [] && !in_array('custom', $list, true)) $list[] = 'custom';
        $this->presets = $list;
        return $this;
    }

    /**
     * What the picker shows when the request names no period: a preset ('this-month', the default) or
     * ['from' => 'Y-m-d', 'to' => 'Y-m-d'].
     *
     * @param string|array<mixed> $default
     */
    public function default(string|array $default): static
    {
        if (is_string($default)) {
            if ($default === 'custom' || !in_array($default, self::PRESETS, true)) {
                self::warn("default('$default') is not a preset; it stays '" . (is_string($this->default) ? $this->default : 'custom') . "'.");
                return $this;
            }
            $this->default = $default;
            return $this;
        }
        $from = self::date($default['from'] ?? null);
        $to = self::date($default['to'] ?? null);
        if ($from === null || $to === null) {
            self::warn("default() takes a preset or ['from' => 'Y-m-d', 'to' => 'Y-m-d'] with two real days; it is unchanged.");
            return $this;
        }
        $this->default = ['from' => $from, 'to' => $to];
        return $this;
    }

    /**
     * The period to show instead of the request's: a preset, ['from' => …, 'to' => …], or what was posted
     * under the name (['preset' => …, 'from' => …, 'to' => …, 'shown' => …]) — read by the same rules.
     */
    public function value(mixed $value): static
    {
        $this->value = is_string($value) ? ['preset' => $value] : $value;
        $this->valueSet = true;
        return $this;
    }

    /** The earliest day that can be chosen ('Y-m-d'). Presets are held within it. */
    public function min(?string $date): static
    {
        $this->min = $this->bound('min', $date, null, $this->max);
        return $this;
    }

    /** The latest day that can be chosen ('Y-m-d'), e.g. today for a report of the past. */
    public function max(?string $date): static
    {
        $this->max = $this->bound('max', $date, $this->min, null);
        return $this;
    }

    /** The time zone "today" is counted in; default date_default_timezone_get(). */
    public function timezone(string $tz): static
    {
        try {
            new \DateTimeZone($tz);
            $this->timezone = $tz;
        } catch (\Throwable) {
            self::warn("timezone('$tz') is not a time zone; it stays " . ($this->timezone ?? date_default_timezone_get()) . '.');
        }
        return $this;
    }

    /**
     * The day the presets count from instead of today: 'Y-m-d', or a moment (a DateTimeInterface), which
     * is read in timezone(). For a report of a fixed day, and for tests.
     */
    public function today(string|\DateTimeInterface $today): static
    {
        if ($today instanceof \DateTimeInterface) {
            $this->today = $today; // resolved in todayDate(), after timezone() may still change
            return $this;
        }
        $d = self::date($today);
        if ($d === null) {
            self::warn("today('$today') is not a day (Y-m-d); today stays today.");
            return $this;
        }
        $this->today = $d;
        return $this;
    }

    /** The accessible name of the picker; default "Period". In a Form the field's label names it. */
    public function label(string $text): static
    {
        $this->label = $text;
        return $this;
    }

    /**
     * Stand-alone, in a filter bar: the picker brings its own <form method="get"> and an Apply button,
     * and with the script a chosen preset is sent at once. Without it the picker is part of the form it
     * stands in.
     */
    public function filter(bool $on = true): static
    {
        $this->filter = $on;
        return $this;
    }

    /** Where the filter form is sent (its action); default the page itself. */
    public function baseUrl(string $url): static
    {
        $this->baseUrl = $url;
        return $this;
    }

    /**
     * The other query parameters the filter form carries along, so a period does not drop the status
     * or the search: names read from the URL (['status', 'q']), or name => value.
     *
     * @param array<int|string,mixed> $params
     */
    public function preserve(array $params): static
    {
        $this->preserve = $params;
        return $this;
    }

    /** Both dates must be filled in before the form can be sent. */
    public function required(bool $on = true): static
    {
        $this->required = $on;
        return $this;
    }

    /* ── Reading ─────────────────────────────────────────────────────────── */

    /**
     * The period of this picker as the request (or value()) gives it, checked:
     * ['from' => 'Y-m-d', 'to' => 'Y-m-d', 'preset' => 'this-month' | … | 'custom'].
     *
     * @return array{from:string,to:string,preset:string}
     */
    public function current(): array
    {
        $raw = $this->valueSet ? $this->value : $this->fromSource();
        $today = $this->todayDate();
        $min = $this->min ?? self::FIRST;
        $max = $this->max ?? self::LAST;
        $allowed = $this->presets === [] ? [] : $this->presets;

        $picked = null;
        if (is_array($raw)) {
            $preset = $raw['preset'] ?? null;
            $preset = is_string($preset) && in_array($preset, $allowed, true) ? $preset : null;
            $from = self::date($raw['from'] ?? null);
            $to = self::date($raw['to'] ?? null);
            $shown = self::shown($raw['shown'] ?? null);
            if ($preset !== null && $preset !== 'custom') {
                // A preset wins — unless the dates were typed over since the page was drawn (no script).
                $picked = ($from !== null && $to !== null && $shown !== null && $shown !== [$from, $to])
                    ? ['from' => $from, 'to' => $to, 'preset' => 'custom']
                    : ['from' => null, 'to' => null, 'preset' => $preset];
            } elseif ($from !== null && $to !== null) {
                $picked = ['from' => $from, 'to' => $to, 'preset' => 'custom'];
            }
        }
        if ($picked === null) {
            $picked = is_string($this->default)
                ? ['from' => null, 'to' => null, 'preset' => $this->default]
                : ['from' => $this->default['from'], 'to' => $this->default['to'], 'preset' => 'custom'];
        }
        if ($picked['preset'] !== 'custom') {
            $r = self::presetRange($picked['preset'], $today) ?? ['from' => $today, 'to' => $today];
            $picked['from'] = $r['from'];
            $picked['to'] = $r['to'];
        }
        [$from, $to] = [(string) $picked['from'], (string) $picked['to']];
        if ($to < $from) [$from, $to] = [$to, $from];
        $from = min(max($from, $min), $max);
        $to = min(max($to, $min), $max);
        return ['from' => $from, 'to' => $to, 'preset' => $picked['preset']];
    }

    /**
     * The checked period a request names, for the page that queries with it:
     * `['from' => $from, 'to' => $to] = DateRange::fromRequest('period', 'this-month');`
     *
     * $default: a preset or ['from' => …, 'to' => …]. $opts: 'presets' (the ones the picker offers; a
     * preset outside them is ignored), 'min', 'max', 'timezone', 'today' — as on the picker, so both
     * read the same period — and 'request' (an array to read instead of $_POST / $_GET).
     *
     * @param string|array<mixed> $default
     * @param array<string,mixed> $opts
     * @return array{from:string,to:string,preset:string}
     */
    public static function fromRequest(string $name, string|array $default = 'this-month', array $opts = []): array
    {
        $unknown = array_diff(array_keys($opts), self::REQUEST_OPTS);
        if ($unknown !== []) self::warn('fromRequest() does not know the option(s) ' . implode(', ', $unknown) . '; they are ignored.');
        $r = new self($name);
        $r->default($default);
        if (isset($opts['presets']) && is_iterable($opts['presets'])) $r->presets($opts['presets']);
        if (isset($opts['min'])) $r->min(is_string($opts['min']) ? $opts['min'] : null);
        if (isset($opts['max'])) $r->max(is_string($opts['max']) ? $opts['max'] : null);
        if (isset($opts['timezone']) && is_string($opts['timezone'])) $r->timezone($opts['timezone']);
        if (isset($opts['today']) && (is_string($opts['today']) || $opts['today'] instanceof \DateTimeInterface)) $r->today($opts['today']);
        if (isset($opts['request']) && is_array($opts['request'])) $r->request = $opts['request'];
        return $r->current();
    }

    /**
     * The days a preset covers, counted from $today ('Y-m-d' or a moment; default today in $timezone):
     * ['from' => …, 'to' => …], or null for 'custom' and an unknown key. "last-7-days" is today and the
     * six days before it; "this-month", "this-quarter" and "this-year" are the whole period, the days
     * still to come included.
     *
     * @return array{from:string,to:string}|null
     */
    public static function presetRange(string $preset, string|\DateTimeInterface|null $today = null, ?string $timezone = null): ?array
    {
        if ($today instanceof \DateTimeInterface || $today === null) {
            $today = self::dayIn($today, $timezone);
        } elseif (($checked = self::date($today)) === null) {
            self::warn("presetRange(): '$today' is not a day (Y-m-d); today is used.");
            $today = self::dayIn(null, $timezone);
        } else {
            $today = $checked;
        }
        [$y, $m] = [(int) substr($today, 0, 4), (int) substr($today, 5, 2)];
        $q = intdiv($m - 1, 3) * 3 + 1; // the first month of the quarter
        $range = match ($preset) {
            'today'        => [$today, $today],
            'yesterday'    => [self::addDays($today, -1), self::addDays($today, -1)],
            'last-7-days'  => [self::addDays($today, -6), $today],
            'last-30-days' => [self::addDays($today, -29), $today],
            'this-month'   => [self::ymd($y, $m, 1), self::ymd($y, $m, self::daysIn($y, $m))],
            'last-month'   => $m === 1
                ? [self::ymd($y - 1, 12, 1), self::ymd($y - 1, 12, 31)]
                : [self::ymd($y, $m - 1, 1), self::ymd($y, $m - 1, self::daysIn($y, $m - 1))],
            'this-quarter' => [self::ymd($y, $q, 1), self::ymd($y, $q + 2, self::daysIn($y, $q + 2))],
            'last-quarter' => $q === 1
                ? [self::ymd($y - 1, 10, 1), self::ymd($y - 1, 12, 31)]
                : [self::ymd($y, $q - 3, 1), self::ymd($y, $q - 1, self::daysIn($y, $q - 1))],
            'this-year'    => [self::ymd($y, 1, 1), self::ymd($y, 12, 31)],
            'last-year'    => [self::ymd($y - 1, 1, 1), self::ymd($y - 1, 12, 31)],
            default        => null,
        };
        if ($range === null) return null;
        // Year 1 has no year before it: the first day is as far back as a date input goes.
        return ['from' => max($range[0], self::FIRST), 'to' => max($range[1], self::FIRST)];
    }

    /**
     * A period in words, in the page's language (Lang): "Oct 1 – 31, 2026", "1.–31. Okt. 2026",
     * "Sep 28 – Oct 4, 2026", "Dec 29, 2025 – Jan 4, 2026", "Oct 1, 2026". js/calendar.js writes the same.
     */
    public static function text(string $from, string $to): string
    {
        $a = self::date($from);
        $b = self::date($to);
        if ($a === null || $b === null) return '';
        if ($b < $a) [$a, $b] = [$b, $a];
        $months = self::words('date.months_short', 12);
        [$y1, $m1, $d1] = array_map('intval', explode('-', $a));
        [$y2, $m2, $d2] = array_map('intval', explode('-', $b));
        if ($a === $b) {
            return Lang::t('daterange.text_day', ['month' => $months[$m1 - 1], 'd' => $d1, 'y' => $y1]);
        }
        if ($y1 === $y2 && $m1 === $m2) {
            return Lang::t('daterange.text_month', ['month' => $months[$m1 - 1], 'd1' => $d1, 'd2' => $d2, 'y' => $y1]);
        }
        if ($y1 === $y2) {
            return Lang::t('daterange.text_year', ['month1' => $months[$m1 - 1], 'd1' => $d1,
                'month2' => $months[$m2 - 1], 'd2' => $d2, 'y' => $y1]);
        }
        return Lang::t('daterange.text_years', ['month1' => $months[$m1 - 1], 'd1' => $d1, 'y1' => $y1,
            'month2' => $months[$m2 - 1], 'd2' => $d2, 'y2' => $y2]);
    }

    /* ── Output ──────────────────────────────────────────────────────────── */

    /** Prints the picker. */
    public function render(): void
    {
        echo $this->html();
    }

    /** The picker as a string. */
    public function html(): string
    {
        $e = static fn(mixed $s): string => htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $cur = $this->current();
        $today = $this->todayDate();
        $min = $this->min ?? self::FIRST;
        $max = $this->max ?? self::LAST;
        $slug = preg_replace('/[^A-Za-z0-9_-]/', '', $this->name) ?: 'period';
        $base = $this->labelledBy !== null ? $slug : 'gk-daterange-' . $slug;
        $field = $this->labelledBy !== null ? 'gk-input' : 'gk-filter';

        // A preset that lies wholly outside min()…max() would only ever give the edge day: not offered.
        $offered = array_values(array_filter($this->presets, function (string $p) use ($today, $min, $max): bool {
            if ($p === 'custom') return true;
            $r = self::presetRange($p, $today);
            return $r !== null && $r['to'] >= $min && $r['from'] <= $max;
        }));
        $chosen = in_array($cur['preset'], $offered, true) ? $cur['preset'] : 'custom';

        $name = $this->labelledBy !== null
            ? ' aria-labelledby="' . $e($this->labelledBy) . '"' . ($this->describedBy !== null ? ' aria-describedby="' . $e($this->describedBy) . '"' : '')
            : ' aria-label="' . $e($this->label !== '' ? $this->label : Lang::t('daterange.label')) . '"';
        $config = [
            'today'   => $today,
            'min'     => $min,
            'max'     => $max,
            'months'  => self::words('date.months_short', 12),
            'text'    => [
                'day'   => Lang::t('daterange.text_day'),
                'month' => Lang::t('daterange.text_month'),
                'year'  => Lang::t('daterange.text_year'),
                'years' => Lang::t('daterange.text_years'),
            ],
            'submit'  => $this->filter,
        ];
        $json = (string) json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        // In a Form every control points at the field's message line too: a group's description is
        // not read when a control inside it takes the focus.
        $described = $this->describedBy !== null ? ' aria-describedby="' . $e($this->describedBy) . '"' : '';
        $state = ($this->required ? ' required' : '') . $described . ($this->invalid ? ' aria-invalid="true"' : '');

        $out = '';
        if ($this->filter) {
            $out .= '<form class="gk-daterange-form" method="get"' . ($this->baseUrl !== '' ? ' action="' . $e($this->baseUrl) . '"' : '') . '>';
            foreach ($this->preserved() as $k => $v) {
                $out .= '<input type="hidden" name="' . $e($k) . '" value="' . $e($v) . '">';
            }
        }
        $out .= '<div class="gk-daterange" id="' . $e($base) . '" role="group"' . $name
              . ' data-gk-daterange="' . $e($json) . '">';
        if ($offered !== []) {
            $out .= '<select class="' . $field . ' gk-daterange-preset" name="' . $e($this->name) . '[preset]" id="' . $e($base) . '-preset"'
                  . ' aria-label="' . $e(Lang::t('daterange.preset')) . '"' . $described . '>';
            foreach ($offered as $p) {
                $out .= '<option value="' . $e($p) . '"' . ($p === $chosen ? ' selected' : '') . '>'
                      . $e(Lang::t('daterange.' . str_replace('-', '_', $p))) . '</option>';
            }
            $out .= '</select>';
        }
        $out .= '<span class="gk-daterange-dates">'
              . '<input type="date" class="' . $field . ' gk-daterange-from" name="' . $e($this->name) . '[from]" id="' . $e($base) . '-from"'
              . ' value="' . $e($cur['from']) . '" min="' . $e($min) . '" max="' . $e($max) . '" aria-label="' . $e(Lang::t('daterange.from')) . '"' . $state . '>'
              . '<span class="gk-daterange-sep" aria-hidden="true">–</span>'
              . '<input type="date" class="' . $field . ' gk-daterange-to" name="' . $e($this->name) . '[to]" id="' . $e($base) . '-to"'
              . ' value="' . $e($cur['to']) . '" min="' . $e($min) . '" max="' . $e($max) . '" aria-label="' . $e(Lang::t('daterange.to')) . '"' . $state . '>'
              . '</span>';
        $out .= '<output class="gk-daterange-text" for="' . $e($base) . '-from ' . $e($base) . '-to" aria-live="polite">'
              . $e(self::text($cur['from'], $cur['to'])) . '</output>';
        // What the page showed: without the script, the server tells by it whether the dates were typed over.
        $out .= '<input type="hidden" name="' . $e($this->name) . '[shown]" value="' . $e($cur['from'] . '/' . $cur['to']) . '" data-gk-daterange-shown>';
        if ($this->filter) {
            $out .= Button::render(Lang::t('daterange.apply'), ['variant' => 'tonal', 'size' => 'sm', 'type' => 'submit',
                'class' => 'gk-daterange-apply']);
        }
        $out .= '</div>';
        if ($this->filter) $out .= '</form>';
        return $out;
    }

    /**
     * What Form draws for a field of type 'daterange' (1.120.0): a picker named by the field's label and
     * described by its message line. The field's options are the picker's: 'presets', 'default', 'min',
     * 'max', 'timezone', 'today', 'required'; the Form's value (values($_POST) hands back what was
     * posted) is read like a request.
     *
     * @param array<string,mixed> $f
     */
    public static function forForm(array $f, string $field, mixed $value, bool $invalid = false): string
    {
        $r = new self($field);
        if (isset($f['presets'])) {
            if (is_iterable($f['presets'])) $r->presets($f['presets']);
            else self::warn("Form field '$field': 'presets' is a list of presets; it is ignored.");
        }
        if (isset($f['default']) && (is_string($f['default']) || is_array($f['default']))) $r->default($f['default']);
        if (isset($f['min'])) $r->min(is_string($f['min']) ? $f['min'] : null);
        if (isset($f['max'])) $r->max(is_string($f['max']) ? $f['max'] : null);
        if (isset($f['timezone']) && is_string($f['timezone'])) $r->timezone($f['timezone']);
        if (isset($f['today']) && (is_string($f['today']) || $f['today'] instanceof \DateTimeInterface)) $r->today($f['today']);
        $r->required(!empty($f['required']));
        // A field never reads the request on its own: what it shows is what the Form was given.
        $r->value($value === '' || $value === null ? [] : $value);
        $r->labelledBy = $field . '-label';
        $r->describedBy = $field . '-error';
        $r->invalid = $invalid;
        return $r->html();
    }

    /* ── Inside ──────────────────────────────────────────────────────────── */

    /** What the request holds under the name: the POST when it carries it, else the query string. */
    private function fromSource(): mixed
    {
        if ($this->request !== null) return $this->request[$this->name] ?? null;
        return $_POST[$this->name] ?? $_GET[$this->name] ?? null;
    }

    /** The hidden fields of the filter form. @return array<string,string> */
    private function preserved(): array
    {
        $out = [];
        foreach ($this->preserve as $k => $v) {
            if (is_int($k)) {
                if (!is_string($v) || $v === $this->name) continue;
                $got = $_GET[$v] ?? null;
                if (is_scalar($got) && (string) $got !== '') $out[$v] = (string) $got;
            } elseif ($k !== $this->name && is_scalar($v)) {
                $out[$k] = (string) $v;
            }
        }
        return $out;
    }

    /** Today in the picker's time zone, or the day today() set. */
    private function todayDate(): string
    {
        if ($this->today instanceof \DateTimeInterface) return self::dayIn($this->today, $this->timezone);
        return $this->today ?? self::dayIn(null, $this->timezone);
    }

    /** The day a moment falls on in a time zone (default: now, date_default_timezone_get()). */
    private static function dayIn(?\DateTimeInterface $at, ?string $timezone): string
    {
        try {
            $tz = new \DateTimeZone($timezone ?? date_default_timezone_get());
        } catch (\Throwable) {
            $tz = new \DateTimeZone('UTC');
        }
        $at = $at === null ? new \DateTimeImmutable('now', $tz) : \DateTimeImmutable::createFromInterface($at)->setTimezone($tz);
        return $at->format('Y-m-d');
    }

    /** min() and max(): a real day that keeps min ≤ max, else a warning and no bound. */
    private function bound(string $which, ?string $date, ?string $lower, ?string $upper): ?string
    {
        if ($date === null || $date === '') return null;
        $d = self::date($date);
        if ($d === null) {
            self::warn("$which('$date') is not a day (Y-m-d); there is no $which.");
            return null;
        }
        if (($lower !== null && $d < $lower) || ($upper !== null && $d > $upper)) {
            self::warn("$which('$date') would put min after max; there is no $which.");
            return null;
        }
        return $d;
    }

    /**
     * A day as 'Y-m-d' when it exists (years 1 to 9999), else null — '2026-02-30', '26-1-5', an array: null.
     * The check every date of DateRange and Calendar goes through.
     */
    public static function date(mixed $value): ?string
    {
        if (!is_string($value)) return null;
        $value = trim($value, " \t\n\r");   // spaces a paste brings; not \0, which trim() takes by default
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) return null;
        [$y, $mo, $d] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        return $y >= 1 && checkdate($mo, $d, $y) ? $value : null;
    }

    /** name[shown] as two days, or null. @return array{0:string,1:string}|null */
    private static function shown(mixed $value): ?array
    {
        if (!is_string($value) || substr_count($value, '/') !== 1) return null;
        [$a, $b] = explode('/', $value);
        $a = self::date($a);
        $b = self::date($b);
        return $a !== null && $b !== null ? [$a, $b] : null;
    }

    private static function ymd(int $y, int $m, int $d): string
    {
        return sprintf('%04d-%02d-%02d', max(0, $y), $m, $d);
    }

    /** Days in a month, by the Gregorian rule (1900 has 28 in February, 2000 has 29). */
    private static function daysIn(int $y, int $m): int
    {
        if ($m === 2) return ($y % 4 === 0 && ($y % 100 !== 0 || $y % 400 === 0)) ? 29 : 28;
        return in_array($m, [4, 6, 9, 11], true) ? 30 : 31;
    }

    /** A day plus n days, counted on the calendar (in UTC, where no day is 23 or 25 hours long). */
    private static function addDays(string $date, int $n): string
    {
        $d = new \DateTimeImmutable($date . ' 12:00:00', new \DateTimeZone('UTC'));
        $r = $d->modify(($n >= 0 ? '+' : '') . $n . ' days')->format('Y-m-d');
        return self::date($r) ?? ($n < 0 ? self::FIRST : self::LAST);
    }

    /** A list from the catalogue ('a|b|c'), padded to $n so a short translation never breaks a date. @return list<string> */
    private static function words(string $key, int $n): array
    {
        $list = explode('|', Lang::t($key));
        for ($i = count($list); $i < $n; $i++) $list[] = (string) ($i + 1);
        return array_slice($list, 0, $n);
    }

    private static function warn(string $message): void
    {
        trigger_error("GridKit: DateRange: $message", E_USER_WARNING);
    }
}
