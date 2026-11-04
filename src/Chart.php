<?php
declare(strict_types=1);

namespace GridKit;

/**
 * Chart — server-rendered SVG charts without a library (1.121.0).
 *
 *   (new Chart('revenue'))
 *       ->type('bar')
 *       ->title('Revenue per month')
 *       ->labels(['Jan', 'Feb', 'Mar'])
 *       ->series('2025', [1200, 1350.5, 990])
 *       ->series('2026', [1400, 1610, 1205])
 *       ->format('money', ['currency' => 'EUR', 'locale' => 'de'])
 *       ->height(280)
 *       ->render();
 *
 * Why it exists: the dashboards among GridKit's users drew their revenue, their open rates and
 * their usage with a chart library each — one with ten fixed hex colours that stayed the same in
 * dark mode, one that drew nothing until its script had run, none a screen reader could read.
 *
 * What the server writes works on its own: an inline <svg> with a viewBox that scales to its box,
 * a <title> and a <desc> that sums the data up in words, a legend with the names (never colour
 * alone) and the data as a table behind "Show as table". js/chart.js adds what needs a script —
 * a tooltip on hover and on the keyboard, the crosshair of a line chart, a legend that switches a
 * series off, gk:chartselect, GK.chart.update() — and draws the chart again at the width of its
 * box, so its text keeps its size on a phone.
 *
 * The script draws with the very code below, ported line by line: GK.chart.html($chart->config())
 * is $chart->html(), byte for byte (ci/browser-chart.js holds it). That is why every number here
 * goes through a decimal string (canon()) and a sine of its own (sin()): PHP's round() and
 * number_format() and the platform's sin() are not what a browser has.
 *
 * Every misuse the class can see is an E_USER_WARNING with a fallback that still renders.
 */
class Chart
{
    public const TYPES = ['line', 'area', 'bar', 'donut', 'sparkline'];
    private const KINDS = ['number', 'money', 'percent'];
    private const TABLES = ['toggle', 'open', 'hidden'];

    /** The colour slots: --gk-chart-1 … 5. A donut's sixth part on folds into "Other". */
    private const SLOTS = 5;
    private const MAX_LABELS = 2000;
    private const MAX_ABS = 1e15;
    private const MIN_ABS = 1e-9;

    /** Number shapes by language: decimal sign, thousands sign, the currency before or after, %, K/M/B. */
    private const LOCALES = [
        'en' => ['.', ',', 'before', '%', ['K', 'M', 'B']],
        'de' => [',', '.', 'after', "\u{00A0}%", ["\u{00A0}Tsd.", "\u{00A0}Mio.", "\u{00A0}Mrd."]],
    ];
    private const SYMBOLS = ['EUR' => '€', 'USD' => '$', 'GBP' => '£'];

    // Geometry, in px of the viewBox (one px on the screen once js/chart.js has fitted the width).
    private const CH = 7;          // the width of a character of the 12px axis text, estimated
    private const GAP = 2;         // the gap between touching marks
    private const BAR_MAX = 24;    // the thickest bar
    private const RADIUS = 4;      // the rounded end of a bar

    private string $id;
    private string $type = 'line';
    private string $title = '';
    private bool $showTitle = true;
    private string $desc = '';
    /** @var list<string>|null */
    private ?array $labels = null;
    /** @var list<array{name:string,values:list<mixed>,opts:array<string,mixed>}> */
    private array $series = [];
    private string $kind = 'number';
    private string $currency = 'EUR';
    private ?string $locale = null;
    private ?int $decimals = null;
    private ?int $width = null;
    private ?int $height = null;
    private bool $stacked = false;
    private bool $horizontal = false;
    private bool $zero = true;
    private bool $markers = false;
    private bool $dashes = false;
    private string $table = 'toggle';
    /** A sparkline with a wash under its line (sparkline()'s 'area'). */
    private bool $sparkArea = false;
    /** @var array<string,mixed>|null the config, worked out once (warnings once) */
    private ?array $config = null;

    public function __construct(string $id = 'chart')
    {
        $this->id = $id;
    }

    // ── Building the chart ───────────────────────────────────────────────

    /** 'line' (default), 'area', 'bar', 'donut' or 'sparkline'. */
    public function type(string $type): static
    {
        if (!in_array($type, self::TYPES, true)) {
            $this->warn("type('$type') is not a chart type — line, area, bar, donut and sparkline are. It stays {$this->type}.");
            return $this;
        }
        $this->type = $type;
        return $this->changed();
    }

    /** The name of the chart: the caption above it (unless $show is false) and the name a screen reader hears. */
    public function title(string $text, bool $show = true): static
    {
        $this->title = $text;
        $this->showTitle = $show;
        return $this->changed();
    }

    /** A sentence of your own for the <desc> instead of the summary the chart writes. */
    public function description(string $text): static
    {
        $this->desc = $text;
        return $this->changed();
    }

    /** The categories along the axis (a donut: the names of its parts). @param iterable<mixed> $labels */
    public function labels(iterable $labels): static
    {
        $this->labels = [];
        foreach ($labels as $label) $this->labels[] = $label;
        return $this->changed();
    }

    /**
     * One series: a name and a value per label. null or '' is a gap. Options: 'color' (1 … 5, the
     * slot of --gk-chart-N — keep an entity on its slot when a filter removes others), 'hidden'
     * (true: drawn switched off; the legend switches it on).
     *
     * @param iterable<mixed> $values
     * @param array<string,mixed> $opts
     */
    public function series(string $name, iterable $values, array $opts = []): static
    {
        $list = [];
        foreach ($values as $v) $list[] = $v;
        $this->series[] = ['name' => $name, 'values' => $list, 'opts' => $opts];
        return $this->changed();
    }

    /**
     * How the values are written: 'number' (default), 'money' or 'percent' (12.5 is 12.5 %, as in
     * StatCards and Table). Options: 'currency' (an ISO code, default EUR), 'locale' ('de' or 'en'
     * and their regions; default the page's language), 'decimals' (0 … 6; default: money 2,
     * number up to 2, percent up to 1).
     *
     * @param array<string,mixed> $opts
     */
    public function format(string $kind, array $opts = []): static
    {
        if (!in_array($kind, self::KINDS, true)) {
            $this->warn("format('$kind') is not a format — number, money and percent are. It stays {$this->kind}.");
        } else {
            $this->kind = $kind;
        }
        foreach ($opts as $key => $value) {
            if ($key === 'currency') {
                $code = is_string($value) ? strtoupper(trim($value)) : '';
                if (preg_match('/^[A-Z]{3}$/', $code) === 1) $this->currency = $code;
                else $this->warn("format: 'currency' takes an ISO code like EUR. It stays {$this->currency}.");
            } elseif ($key === 'locale') {
                if (is_string($value) && isset(self::LOCALES[strtolower(substr($value, 0, 2))])) {
                    $this->locale = strtolower(substr($value, 0, 2));
                } else {
                    $shown = is_scalar($value) ? (string) $value : get_debug_type($value);
                    $this->warn("format: locale '$shown' is not known — 'en' and 'de' (with their regions) are. Numbers are written the English way.");
                    $this->locale = 'en';
                }
            } elseif ($key === 'decimals') {
                if (is_int($value) && $value >= 0 && $value <= 6) $this->decimals = $value;
                else $this->warn("format: 'decimals' takes a whole number from 0 to 6. The format's own is used.");
            } else {
                $this->warn("format: the option '$key' is not known (currency, locale, decimals). It was ignored.");
            }
        }
        return $this->changed();
    }

    /** The height in px (charts 120 … 1200, default 280; a sparkline 12 … 200, default 28). */
    public function height(int $px): static
    {
        $this->height = $px;
        return $this->changed();
    }

    /**
     * The width of the viewBox in px (200 … 4000, default 640; a sparkline 24 … 1000, default 96).
     * The chart always fills its box; with js/chart.js it is drawn again at the box's width, so
     * set this only for the first paint without the script (a phone card: 360).
     */
    public function width(int $px): static
    {
        $this->width = $px;
        return $this->changed();
    }

    /** Bars or areas on top of each other (bar, area). Negative values stack below zero. */
    public function stacked(bool $on = true): static
    {
        $this->stacked = $on;
        return $this->changed();
    }

    /** Bars from the left instead of from the bottom (bar) — for long category names. */
    public function horizontal(bool $on = true): static
    {
        $this->horizontal = $on;
        return $this->changed();
    }

    /** false: a line chart's axis starts near its data instead of at zero. Bars and areas always start at zero. */
    public function zero(bool $on = true): static
    {
        $this->zero = $on;
        return $this->changed();
    }

    /** A point shape of its own per series — circle, square, diamond, triangle, triangle down (line, area). */
    public function markers(bool $on = true): static
    {
        $this->markers = $on;
        return $this->changed();
    }

    /** A dash pattern of its own per series (line, area); in print and forced colours it is always on. */
    public function dashes(bool $on = true): static
    {
        $this->dashes = $on;
        return $this->changed();
    }

    /** The data as a table: 'toggle' (default, behind "Show as table"), 'open' (shown) or 'hidden' (for a screen reader only). */
    public function table(string $mode = 'toggle'): static
    {
        if (!in_array($mode, self::TABLES, true)) {
            $this->warn("table('$mode') knows 'toggle', 'open' and 'hidden'. It stays {$this->table}.");
            return $this;
        }
        $this->table = $mode;
        return $this->changed();
    }

    /**
     * The chart as data: what data-gk-chart holds and GK.chart.update() and GK.chart.html() take.
     * Values are decimal strings ('1234.5'); an answer of a JSON endpoint can be this array.
     *
     * @return array<string,mixed>
     */
    public function config(): array
    {
        return $this->config ??= $this->buildConfig();
    }

    public function render(): void
    {
        echo $this->html();
    }

    public function html(): string
    {
        return self::draw($this->config());
    }

    /**
     * A sparkline to place: a small inline line for a StatCards card or a table cell (Table's
     * 'format' => 'html'). Options: 'label' (the words before the summary, default "Trend"),
     * 'format', 'currency', 'locale', 'decimals' (as format()), 'width', 'height', 'color' (1 … 5),
     * 'area' (true: a wash under the line).
     *
     * @param iterable<mixed> $values
     * @param array<string,mixed> $opts
     */
    public static function sparkline(iterable $values, array $opts = []): string
    {
        $chart = (new self('spark'))->type('sparkline');
        $format = [];
        foreach (['currency', 'locale', 'decimals'] as $k) {
            if (array_key_exists($k, $opts)) $format[$k] = $opts[$k];
        }
        $chart->format(is_string($opts['format'] ?? null) ? $opts['format'] : 'number', $format);
        if (isset($opts['width'])) $chart->width((int) $opts['width']);
        if (isset($opts['height'])) $chart->height((int) $opts['height']);
        if (isset($opts['label'])) $chart->title((string) $opts['label']);
        $chart->sparkArea = !empty($opts['area']);
        $seriesOpts = isset($opts['color']) ? ['color' => $opts['color']] : [];
        return $chart->series($chart->title !== '' ? $chart->title : 'trend', $values, $seriesOpts)->html();
    }

    // ── The config: everything checked once, in the order the script keeps ──

    private function changed(): static
    {
        $this->config = null;
        return $this;
    }

    /** @return array<string,mixed> */
    private function buildConfig(): array
    {
        $type = $this->type;
        $axis = in_array($type, ['line', 'area', 'bar'], true);

        $stacked = $this->stacked;
        if ($stacked && !in_array($type, ['bar', 'area'], true)) {
            $this->warn("stacked() applies to bar and area charts — ignored for $type.");
            $stacked = false;
        }
        $horizontal = $this->horizontal;
        if ($horizontal && $type !== 'bar') {
            $this->warn("horizontal() applies to bar charts — ignored for $type.");
            $horizontal = false;
        }
        $zero = $this->zero;
        if (!$zero && $type !== 'line') {
            if ($axis) $this->warn("zero(false) applies to line charts: the length of a bar or an area is its value, so it starts at zero.");
            $zero = true;
        }
        $markers = $this->markers;
        $dashes = $this->dashes;
        if (($markers || $dashes) && !in_array($type, ['line', 'area'], true)) {
            $this->warn(($markers ? 'markers()' : 'dashes()') . " applies to line and area charts — ignored for $type.");
            $markers = $dashes = false;
        }

        // The series: at most five (the colour slots); a donut and a sparkline draw the first.
        $series = $this->series;
        $max = in_array($type, ['donut', 'sparkline'], true) ? 1 : self::SLOTS;
        if (count($series) > $max) {
            $this->warn($max === 1
                ? "a $type draws one series — the first; " . (count($series) - 1) . ' more were left out.'
                : 'a chart has five colours — series ' . ($max + 1) . ' to ' . count($series) . ' were left out. Fold them into one ("Other") or draw a second chart.');
            $series = array_slice($series, 0, $max);
        }

        // The labels: given, or 1 … n for the longest series.
        $longest = 0;
        foreach ($series as $s) $longest = max($longest, count($s['values']));
        if ($this->labels === null) {
            $labels = [];
            for ($i = 1; $i <= min($longest, self::MAX_LABELS); $i++) $labels[] = (string) $i;
        } else {
            $labels = [];
            foreach ($this->labels as $i => $label) {
                if (is_array($label) || (is_object($label) && !method_exists($label, '__toString'))) {
                    $this->warn("label $i is " . get_debug_type($label) . ' — a label is text. It was left empty.');
                    $label = '';
                }
                $labels[] = self::clean($label === null ? '' : (is_bool($label) ? ($label ? '1' : '') : (is_float($label) ? self::canon($label) : (string) $label)));
            }
        }
        if (count($labels) > self::MAX_LABELS) {
            $this->warn('a chart draws at most ' . self::MAX_LABELS . ' labels — ' . (count($labels) - self::MAX_LABELS) . ' were left out.');
            $labels = array_slice($labels, 0, self::MAX_LABELS);
        }
        $n = count($labels);

        $out = [];
        $used = [];
        foreach ($series as $si => $s) {
            $name = self::clean($s['name']);
            if ($name === '') $name = Lang::t('chart.series', ['n' => $si + 1]);
            $values = [];
            foreach (array_slice($s['values'], 0, $n) as $vi => $v) {
                $values[] = $this->value($v, $name, $vi, $type === 'donut');
            }
            if (count($s['values']) > $n && $this->labels !== null) {
                $this->warn("series '$name' has " . count($s['values']) . " values for $n labels — the rest were left out.");
            }
            while (count($values) < $n) $values[] = null;

            $color = $si + 1;
            $hidden = false;
            foreach ($s['opts'] as $key => $value) {
                if ($key === 'color') {
                    if (is_int($value) && $value >= 1 && $value <= self::SLOTS) $color = $value;
                    else $this->warn("series '$name': 'color' takes a slot from 1 to " . self::SLOTS . " — it keeps slot $color.");
                } elseif ($key === 'hidden') {
                    if (is_bool($value)) $hidden = $value;
                    else $this->warn("series '$name': 'hidden' takes true or false. It was ignored.");
                } else {
                    $this->warn("series '$name': the option '$key' is not known (color, hidden). It was ignored.");
                }
            }
            if ($type === 'donut' || $type === 'sparkline') $hidden = false;
            $used[] = $color;
            $out[] = ['name' => $name, 'values' => $values, 'color' => $color, 'hidden' => $hidden];
        }

        [$wMin, $wMax, $wDef, $hMin, $hMax, $hDef] = $type === 'sparkline'
            ? [24, 1000, 96, 12, 200, 28]
            : [200, 4000, 640, 120, 1200, 280];
        $width = $this->width ?? $wDef;
        if ($width < $wMin || $width > $wMax) {
            $this->warn("width($width) is outside $wMin … $wMax — it is " . max($wMin, min($wMax, $width)) . '.');
            $width = max($wMin, min($wMax, $width));
        }
        $height = $this->height ?? $hDef;
        if ($height < $hMin || $height > $hMax) {
            $this->warn("height($height) is outside $hMin … $hMax — it is " . max($hMin, min($hMax, $height)) . '.');
            $height = max($hMin, min($hMax, $height));
        }

        return [
            'id' => self::clean($this->id),
            'type' => $type,
            'title' => self::clean($this->title),
            'showTitle' => $this->showTitle,
            'desc' => self::clean($this->desc),
            'labels' => $labels,
            'series' => $out,
            'format' => [
                'kind' => $this->kind,
                'currency' => $this->currency,
                'locale' => $this->locale ?? self::family(Lang::locale()),
                'decimals' => $this->decimals,
            ],
            'width' => $width,
            'height' => $height,
            'stacked' => $stacked,
            'horizontal' => $horizontal,
            'zero' => $zero,
            'markers' => $markers,
            'dashes' => $dashes,
            'table' => $this->table,
            'area' => $type === 'sparkline' && $this->sparkArea,
        ];
    }

    /** One value as a decimal string, or null for a gap. */
    private function value(mixed $v, string $name, int $i, bool $donut): ?string
    {
        if ($v === null || $v === '') return null;
        if (is_string($v) && is_numeric($v)) $f = (float) $v;
        elseif (is_int($v) || is_float($v)) $f = (float) $v;
        else {
            $shown = is_scalar($v) ? var_export($v, true) : get_debug_type($v);
            $this->warn("series '$name', value $i is $shown — not a number. It is a gap.");
            return null;
        }
        if (!is_finite($f)) {
            $this->warn("series '$name', value $i is not finite. It is a gap.");
            return null;
        }
        if (abs($f) > self::MAX_ABS) {
            $this->warn("series '$name', value $i is beyond ±1e15 — it was cut to that.");
            $f = $f < 0 ? -self::MAX_ABS : self::MAX_ABS;
        }
        if (abs($f) < self::MIN_ABS) $f = 0.0;
        if ($donut && $f < 0) {
            $this->warn("series '$name', value $i is negative — a part of a whole cannot be. It counts as 0.");
            $f = 0.0;
        }
        return self::canon($f);
    }

    private function warn(string $message): void
    {
        trigger_error("GridKit: chart '{$this->id}': $message", E_USER_WARNING);
    }

    // ── Drawing: pure functions of the config (js/chart.js has each of them) ──

    /** @param array<string,mixed> $c */
    private static function draw(array $c): string
    {
        if ($c['type'] === 'sparkline') return self::spark($c);
        $e = self::escaper();
        $key = self::key($c['id']);
        $title = $c['title'] !== '' ? $c['title'] : Lang::t('chart.label');
        $cls = 'gk-chart gk-chart-type-' . $c['type']
            . ($c['stacked'] ? ' gk-chart-stacked' : '')
            . ($c['horizontal'] ? ' gk-chart-horizontal' : '')
            . ($c['dashes'] ? ' gk-chart-dashes' : '');
        $json = json_encode($c, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS);
        return '<figure class="' . $cls . '" id="gk-chart-' . $key . '" data-gk-chart="' . $e((string) $json) . '">'
            . '<figcaption class="gk-chart-title' . ($c['showTitle'] ? '' : ' gk-sr-only') . '">' . $e($title) . '</figcaption>'
            . '<div class="gk-chart-plot">' . self::svg($c, $c['width']) . '</div>'
            . self::legend($c)
            . self::dataTable($c, $title)
            . '</figure>';
    }

    /**
     * The <svg> of a chart at a width — what js/chart.js puts in .gk-chart-plot when it fits the
     * chart to its box.
     *
     * @param array<string,mixed> $c
     */
    private static function svg(array $c, int $width): string
    {
        $e = self::escaper();
        $key = self::key($c['id']);
        $title = $c['title'] !== '' ? $c['title'] : Lang::t('chart.label');
        $W = $c['type'] === 'donut' ? $c['height'] : $width;
        $H = $c['height'];
        $empty = self::isEmpty($c);
        $desc = $c['desc'] !== '' ? $c['desc'] : ($empty ? Lang::t('chart.empty') : self::summary($c));
        if ($empty) $body = self::emptyBody($W, $H);
        elseif ($c['type'] === 'donut') $body = self::donutBody($c, $W);
        elseif ($c['horizontal']) $body = self::hbarBody($c, $W, $H);
        else $body = self::axisBody($c, $W, $H);
        return '<svg class="gk-chart-svg" viewBox="0 0 ' . $W . ' ' . $H . '" width="' . $W . '" height="' . $H . '"'
            . ' role="img" aria-labelledby="gk-chart-' . $key . '-t" aria-describedby="gk-chart-' . $key . '-d">'
            . '<title id="gk-chart-' . $key . '-t">' . $e($title) . '</title>'
            . '<desc id="gk-chart-' . $key . '-d">' . $e($desc) . '</desc>'
            . $body . '</svg>';
    }

    /** No labels, no series, or not one value: a word in the middle instead of an empty frame. */
    private static function isEmpty(array $c): bool
    {
        foreach ($c['series'] as $s) {
            foreach ($s['values'] as $v) {
                if ($v !== null) return false;
            }
        }
        return true;
    }

    private static function emptyBody(int $W, int $H): string
    {
        return '<text class="gk-chart-empty" x="' . self::num($W / 2) . '" y="' . self::num($H / 2 + 4) . '" text-anchor="middle">'
            . self::escaper()(Lang::t('chart.empty')) . '</text>';
    }

    /** The visible series, in order. @return list<array<string,mixed>> */
    private static function visible(array $c): array
    {
        $out = [];
        foreach ($c['series'] as $i => $s) {
            if (!$s['hidden']) $out[] = $s + ['index' => $i];
        }
        return $out;
    }

    /**
     * The value axis: domain and ticks for what is visible. Stacked: the sums above and below
     * zero; bars and areas always include zero.
     *
     * @return array{lo:float,hi:float,ticks:list<array{v:float,text:string}>}
     */
    private static function valueAxis(array $c, array $vis, int $target): array
    {
        $vals = [];
        $n = count($c['labels']);
        if ($c['stacked']) {
            for ($i = 0; $i < $n; $i++) {
                $pos = 0.0;
                $neg = 0.0;
                foreach ($vis as $s) {
                    $v = $s['values'][$i] === null ? 0.0 : (float) $s['values'][$i];
                    if ($v > 0) $pos += $v; else $neg += $v;
                }
                $vals[] = $pos;
                $vals[] = $neg;
            }
        } else {
            foreach ($vis as $s) {
                foreach ($s['values'] as $v) {
                    if ($v !== null) $vals[] = (float) $v;
                }
            }
        }
        if ($c['zero']) $vals[] = 0.0;
        if ($vals === []) $vals = [0.0, 1.0];
        $lo = min($vals);
        $hi = max($vals);
        // One value, or values a millionth apart: a band around them, so a step is never a
        // millionth of the numbers it labels.
        $mag = max(abs($lo), abs($hi));
        if ($mag == 0.0) {
            $hi = 1.0;
        } elseif ($hi - $lo < $mag * 1e-6) {
            $d = $mag / 10;
            $lo -= $d;
            $hi += $d;
            if ($c['zero']) {
                $lo = min($lo, 0.0);
                $hi = max($hi, 0.0);
            }
        }
        return self::nice($lo, $hi, $target, $c['format']);
    }

    /**
     * "Nice" ticks: steps of 1, 2 or 5 times a power of ten that cover lo … hi in about $target
     * steps. The powers are made by multiplying and dividing by ten — no log10(), no pow(), so the
     * script finds the very same numbers.
     *
     * @return array{lo:float,hi:float,ticks:list<array{v:float,text:string}>}
     */
    private static function nice(float $lo, float $hi, int $target, array $format): array
    {
        $rough = ($hi - $lo) / $target;
        $e = 0;
        $p = 1.0;
        if ($rough >= 1) {
            while ($p * 10 <= $rough) { $p *= 10; $e++; }
        } else {
            while ($p > $rough) { $p /= 10; $e--; }
        }
        $m = $rough / $p;
        $mant = $m <= 1 ? 1 : ($m <= 2 ? 2 : ($m <= 5 ? 5 : 10));
        if ($mant === 10) { $mant = 1; $e++; }
        $step = self::exact($mant, $e);
        $kLo = (int) floor($lo / $step + 1e-9);
        $kHi = (int) ceil($hi / $step - 1e-9);
        if ($kHi <= $kLo) $kHi = $kLo + 1;
        $ticks = [];
        $maxAbs = max(abs(self::exact($kLo * $mant, $e)), abs(self::exact($kHi * $mant, $e)));
        $tier = $maxAbs >= 1e10 ? 3 : ($maxAbs >= 1e7 ? 2 : ($maxAbs >= 1e4 ? 1 : 0));
        for ($k = $kLo; $k <= $kHi; $k++) {
            $v = self::exact($k * $mant, $e);
            if ($tier > 0 && $v != 0.0) {
                $text = self::compose(self::upTo(self::canon(self::exact($k * $mant, $e - 3 * $tier)), max(0, 3 * $tier - $e), $format)
                    . self::LOCALES[$format['locale']][4][$tier - 1], $v < 0, $format);
            } else {
                $text = self::compose(self::upTo(self::canon($v), max(0, -$e), $format), $v < 0, $format);
            }
            $ticks[] = ['v' => $v, 'text' => $text];
        }
        return ['lo' => self::exact($kLo * $mant, $e), 'hi' => self::exact($kHi * $mant, $e), 'ticks' => $ticks];
    }

    /** $int × 10^$e with as little rounding as a double allows. */
    private static function exact(int $int, int $e): float
    {
        $p = 1.0;
        for ($i = 0; $i < abs($e); $i++) $p *= 10;
        return $e >= 0 ? $int * $p : $int / $p;
    }

    /**
     * Line, area and vertical bar charts: grid, value axis, category axis, marks.
     */
    private static function axisBody(array $c, int $W, int $H): string
    {
        $e = self::escaper();
        $vis = self::visible($c);
        $labels = $c['labels'];
        $n = count($labels);
        $top = 12;
        $bottom = $H - 28;
        $plotH = $bottom - $top;
        $target = max(2, min(8, intdiv($plotH, 44)));
        $ax = self::valueAxis($c, $vis, $target);
        $longest = 0;
        foreach ($ax['ticks'] as $t) $longest = max($longest, self::len($t['text']));
        $left = max(28, $longest * self::CH + 14);
        $right = $W - 12;
        if ($right - $left < 40) $left = $right - 40;
        $plotW = $right - $left;
        $span = $ax['hi'] - $ax['lo'];
        $y = static fn (float $v): float => $top + ($ax['hi'] - $v) / $span * $plotH;
        $band = $plotW / $n;
        $cx = static fn (int $i): float => $left + $band * $i + $band / 2;

        // Grid and value axis.
        $out = '<g class="gk-chart-grid">';
        foreach ($ax['ticks'] as $t) {
            $ty = self::num($y($t['v']));
            $out .= '<line class="gk-chart-gridline' . ($t['v'] == 0.0 ? ' gk-chart-zero' : '') . '" x1="' . self::num($left) . '" y1="' . $ty
                . '" x2="' . self::num($right) . '" y2="' . $ty . '"/>';
        }
        $out .= '</g><g class="gk-chart-axis">';
        foreach ($ax['ticks'] as $t) {
            $out .= '<text x="' . self::num($left - 8) . '" y="' . self::num($y($t['v']) + 4) . '" text-anchor="end">' . $e($t['text']) . '</text>';
        }
        // Category axis: every label that has room, cut to it.
        $long = 0;
        foreach ($labels as $l) $long = max($long, self::len($l));
        $need = min($long, 16) * self::CH + 8;
        $step = max(1, (int) ceil($need / $band));
        $chars = max(2, (int) floor(($band * $step - 6) / self::CH));
        for ($i = 0; $i < $n; $i += $step) {
            $out .= self::label($labels[$i], $chars, $cx($i), $H - 10, 'middle');
        }
        $out .= '</g><g class="gk-chart-marks">';

        $base = $y(max($ax['lo'], min(0.0, $ax['hi'])));
        if ($c['type'] === 'bar') {
            $out .= self::bars($c, $vis, $n, $band, $cx, $y, $base);
        } else {
            $out .= self::lines($c, $vis, $n, $band, $cx, $y, $base);
        }
        return $out . '</g>';
    }

    /** A category name at a place, cut to $chars with its full text in a <title>. */
    private static function label(string $text, int $chars, float $x, float $y, string $anchor): string
    {
        $e = self::escaper();
        $shown = self::cut($text, $chars);
        return '<text x="' . self::num($x) . '" y="' . self::num($y) . '" text-anchor="' . $anchor . '">' . $e($shown)
            . ($shown !== $text ? '<title>' . $e($text) . '</title>' : '') . '</text>';
    }

    /** Vertical bars, grouped or stacked. */
    private static function bars(array $c, array $vis, int $n, float $band, \Closure $cx, \Closure $y, float $base): string
    {
        $k = count($vis);
        $out = '';
        if ($k === 0) return $out;
        $single = $c['stacked'] || $k === 1;
        $barW = $single ? min(self::BAR_MAX, $band * 0.64) : min(self::BAR_MAX, ($band * 0.72 - self::GAP * ($k - 1)) / $k);
        if ($barW < 1) $barW = 1.0;
        $groupW = $single ? $barW : $barW * $k + self::GAP * ($k - 1);

        // Stacked: where each segment starts and ends, and which ones carry the rounded end.
        $from = [];
        $to = [];
        $first = [];
        $last = [];
        if ($c['stacked']) {
            for ($i = 0; $i < $n; $i++) {
                $pos = 0.0;
                $neg = 0.0;
                $lastPos = -1;
                $lastNeg = -1;
                $seenPos = false;
                $seenNeg = false;
                foreach ($vis as $j => $s) {
                    $v = $s['values'][$i] === null ? 0.0 : (float) $s['values'][$i];
                    if ($v > 0) {
                        $from[$j][$i] = $pos;
                        $pos += $v;
                        $to[$j][$i] = $pos;
                        $first[$j][$i] = !$seenPos;
                        $seenPos = true;
                        $lastPos = $j;
                    } elseif ($v < 0) {
                        $from[$j][$i] = $neg;
                        $neg += $v;
                        $to[$j][$i] = $neg;
                        $first[$j][$i] = !$seenNeg;
                        $seenNeg = true;
                        $lastNeg = $j;
                    }
                }
                if ($lastPos >= 0) $last[$lastPos][$i] = true;
                if ($lastNeg >= 0) $last[$lastNeg][$i] = true;
            }
        }

        foreach ($vis as $j => $s) {
            $out .= '<g class="gk-chart-series gk-chart-c' . $s['color'] . '" data-gk-chart-s="' . $s['index'] . '">';
            for ($i = 0; $i < $n; $i++) {
                if ($s['values'][$i] === null) continue;
                $v = (float) $s['values'][$i];
                if ($v == 0.0) continue;
                $x = $cx($i) - $groupW / 2 + ($single ? 0 : $j * ($barW + self::GAP));
                if ($c['stacked']) {
                    $y0 = $y($from[$j][$i]);
                    $y1 = $y($to[$j][$i]);
                    $up = $v > 0;
                    if (!$first[$j][$i]) $y0 = $up ? $y0 - self::GAP : $y0 + self::GAP;
                    $round = isset($last[$j][$i]);
                    $isFirst = $first[$j][$i];
                } else {
                    $y0 = $base;
                    $y1 = $y($v);
                    $up = $v > 0;
                    $round = true;
                    $isFirst = true;
                }
                $h = $up ? $y0 - $y1 : $y1 - $y0;
                if ($h < 1) {
                    if (!$isFirst) continue;
                    $y1 = $up ? $y0 - 1 : $y0 + 1;
                    $h = 1.0;
                }
                $out .= '<path class="gk-chart-bar" data-gk-chart-i="' . $i . '" d="'
                    . self::barPath($x, $barW, $y0, $y1, $round ? min(self::RADIUS, $barW / 2, $h) : 0.0, false) . '"/>';
            }
            $out .= '</g>';
        }
        return $out;
    }

    /**
     * A bar from $b0 (its base) to $b1 (its data end), $w thick at $a, the data end rounded by $r.
     * Vertical: $a is x, $b is y. Horizontal: $a is y, $b is x.
     */
    private static function barPath(float $a, float $w, float $b0, float $b1, float $r, bool $horizontal): string
    {
        $n = static fn (float $v): string => self::num($v);
        if (!$horizontal) {
            if ($r <= 0) {
                return 'M' . $n($a) . ',' . $n($b0) . 'V' . $n($b1) . 'H' . $n($a + $w) . 'V' . $n($b0) . 'Z';
            }
            $up = $b1 < $b0;
            $s = $up ? '1' : '0';
            $edge = $up ? $b1 + $r : $b1 - $r;
            return 'M' . $n($a) . ',' . $n($b0) . 'V' . $n($edge)
                . 'A' . $n($r) . ',' . $n($r) . ' 0 0 ' . $s . ' ' . $n($a + $r) . ',' . $n($b1)
                . 'H' . $n($a + $w - $r)
                . 'A' . $n($r) . ',' . $n($r) . ' 0 0 ' . $s . ' ' . $n($a + $w) . ',' . $n($edge)
                . 'V' . $n($b0) . 'Z';
        }
        if ($r <= 0) {
            return 'M' . $n($b0) . ',' . $n($a) . 'H' . $n($b1) . 'V' . $n($a + $w) . 'H' . $n($b0) . 'Z';
        }
        $right = $b1 > $b0;
        $s = $right ? '1' : '0';
        $edge = $right ? $b1 - $r : $b1 + $r;
        return 'M' . $n($b0) . ',' . $n($a) . 'H' . $n($edge)
            . 'A' . $n($r) . ',' . $n($r) . ' 0 0 ' . $s . ' ' . $n($b1) . ',' . $n($a + $r)
            . 'V' . $n($a + $w - $r)
            . 'A' . $n($r) . ',' . $n($r) . ' 0 0 ' . $s . ' ' . $n($edge) . ',' . $n($a + $w)
            . 'H' . $n($b0) . 'Z';
    }

    /** Lines and areas: a path per run of values, a point per value (shown when there is room or a point stands alone). */
    private static function lines(array $c, array $vis, int $n, float $band, \Closure $cx, \Closure $y, float $base): string
    {
        $out = '';
        $area = $c['type'] === 'area';
        $pos = array_fill(0, $n, 0.0);
        $neg = array_fill(0, $n, 0.0);
        $dots = $c['markers'] || $band >= 24;
        foreach ($vis as $s) {
            $vals = [];
            $lows = [];
            for ($i = 0; $i < $n; $i++) {
                $raw = $s['values'][$i];
                if ($c['stacked']) {
                    $v = $raw === null ? 0.0 : (float) $raw;
                    if ($v >= 0) { $lows[$i] = $pos[$i]; $pos[$i] += $v; $vals[$i] = $pos[$i]; }
                    else { $lows[$i] = $neg[$i]; $neg[$i] += $v; $vals[$i] = $neg[$i]; }
                } else {
                    $vals[$i] = $raw === null ? null : (float) $raw;
                }
            }
            // Runs of values without a gap.
            $runs = [];
            $run = [];
            for ($i = 0; $i < $n; $i++) {
                if ($vals[$i] === null) {
                    if ($run) $runs[] = $run;
                    $run = [];
                } else {
                    $run[] = $i;
                }
            }
            if ($run) $runs[] = $run;

            $out .= '<g class="gk-chart-series gk-chart-c' . $s['color'] . '" data-gk-chart-s="' . $s['index'] . '">';
            if ($area) {
                foreach ($runs as $r) {
                    if (count($r) < 2) continue;
                    $d = '';
                    foreach ($r as $k => $i) $d .= ($k === 0 ? 'M' : 'L') . self::num($cx($i)) . ',' . self::num($y($vals[$i]));
                    if ($c['stacked']) {
                        for ($k = count($r) - 1; $k >= 0; $k--) $d .= 'L' . self::num($cx($r[$k])) . ',' . self::num($y($lows[$r[$k]]));
                    } else {
                        $d .= 'L' . self::num($cx($r[count($r) - 1])) . ',' . self::num($base) . 'L' . self::num($cx($r[0])) . ',' . self::num($base);
                    }
                    $out .= '<path class="gk-chart-area" d="' . $d . 'Z"/>';
                }
            }
            $d = '';
            foreach ($runs as $r) {
                if (count($r) < 2) continue;
                foreach ($r as $k => $i) $d .= ($k === 0 ? 'M' : 'L') . self::num($cx($i)) . ',' . self::num($y($vals[$i]));
            }
            if ($d !== '') $out .= '<path class="gk-chart-line" d="' . $d . '"/>';
            foreach ($runs as $r) {
                foreach ($r as $i) {
                    $cls = 'gk-chart-dot' . ($dots ? ' gk-chart-dot-on' : (count($r) === 1 ? ' gk-chart-dot-on' : ''));
                    $out .= self::marker($cls, $i, $cx($i), $y($vals[$i]), $c['markers'] ? $s['color'] : 1);
                }
            }
            $out .= '</g>';
        }
        return $out;
    }

    /** A point: a circle, or with markers() the shape of its slot. */
    private static function marker(string $cls, ?int $i, float $x, float $y, int $shape): string
    {
        $a = ' class="' . $cls . '"' . ($i === null ? '' : ' data-gk-chart-i="' . $i . '"');
        $n = static fn (float $v): string => self::num($v);
        return match ($shape) {
            2 => '<path' . $a . ' d="M' . $n($x - 3.5) . ',' . $n($y - 3.5) . 'H' . $n($x + 3.5) . 'V' . $n($y + 3.5) . 'H' . $n($x - 3.5) . 'Z"/>',
            3 => '<path' . $a . ' d="M' . $n($x) . ',' . $n($y - 4.5) . 'L' . $n($x + 4.5) . ',' . $n($y) . 'L' . $n($x) . ',' . $n($y + 4.5) . 'L' . $n($x - 4.5) . ',' . $n($y) . 'Z"/>',
            4 => '<path' . $a . ' d="M' . $n($x) . ',' . $n($y - 4.5) . 'L' . $n($x + 4.5) . ',' . $n($y + 3.5) . 'L' . $n($x - 4.5) . ',' . $n($y + 3.5) . 'Z"/>',
            5 => '<path' . $a . ' d="M' . $n($x) . ',' . $n($y + 4.5) . 'L' . $n($x + 4.5) . ',' . $n($y - 3.5) . 'L' . $n($x - 4.5) . ',' . $n($y - 3.5) . 'Z"/>',
            default => '<circle' . $a . ' cx="' . $n($x) . '" cy="' . $n($y) . '" r="4"/>',
        };
    }

    /** Horizontal bars: the categories down the left, the values along the bottom. */
    private static function hbarBody(array $c, int $W, int $H): string
    {
        $e = self::escaper();
        $vis = self::visible($c);
        $labels = $c['labels'];
        $n = count($labels);
        $long = 0;
        foreach ($labels as $l) $long = max($long, self::len($l));
        $left = max(40, min(min($long, 24) * self::CH + 12, intdiv($W * 2, 5)));
        $top = 8;
        $bottom = $H - 28;
        $plotH = $bottom - $top;
        $plotW0 = $W - $left - 12;
        $target = max(2, min(8, intdiv($plotW0, 90)));
        $ax = self::valueAxis($c, $vis, $target);
        $tickLong = 0;
        foreach ($ax['ticks'] as $t) $tickLong = max($tickLong, self::len($t['text']));
        $right = $W - max(12, intdiv($tickLong * self::CH, 2) + 4);
        if ($right - $left < 40) $right = $left + 40;
        $plotW = $right - $left;
        $span = $ax['hi'] - $ax['lo'];
        $x = static fn (float $v): float => $left + ($v - $ax['lo']) / $span * $plotW;
        $band = $plotH / $n;
        $cy = static fn (int $i): float => $top + $band * $i + $band / 2;

        $out = '<g class="gk-chart-grid">';
        foreach ($ax['ticks'] as $t) {
            $tx = self::num($x($t['v']));
            $out .= '<line class="gk-chart-gridline' . ($t['v'] == 0.0 ? ' gk-chart-zero' : '') . '" x1="' . $tx . '" y1="' . self::num($top)
                . '" x2="' . $tx . '" y2="' . self::num($bottom) . '"/>';
        }
        $out .= '</g><g class="gk-chart-axis">';
        foreach ($ax['ticks'] as $t) {
            $out .= '<text x="' . self::num($x($t['v'])) . '" y="' . self::num($H - 10) . '" text-anchor="middle">' . $e($t['text']) . '</text>';
        }
        $step = max(1, (int) ceil(16 / $band));
        $chars = max(2, intdiv($left - 12, self::CH));
        for ($i = 0; $i < $n; $i += $step) {
            $out .= self::label($labels[$i], $chars, $left - 8, $cy($i) + 4, 'end');
        }
        $out .= '</g><g class="gk-chart-marks">';

        $k = count($vis);
        if ($k > 0) {
            $base = $x(max($ax['lo'], min(0.0, $ax['hi'])));
            $single = $c['stacked'] || $k === 1;
            $barW = $single ? min(self::BAR_MAX, $band * 0.64) : min(self::BAR_MAX, ($band * 0.72 - self::GAP * ($k - 1)) / $k);
            if ($barW < 1) $barW = 1.0;
            $groupW = $single ? $barW : $barW * $k + self::GAP * ($k - 1);
            $pos = array_fill(0, $n, 0.0);
            $neg = array_fill(0, $n, 0.0);
            $seenPos = array_fill(0, $n, false);
            $seenNeg = array_fill(0, $n, false);
            $lastPos = array_fill(0, $n, -1);
            $lastNeg = array_fill(0, $n, -1);
            if ($c['stacked']) {
                foreach ($vis as $j => $s) {
                    for ($i = 0; $i < $n; $i++) {
                        $v = $s['values'][$i] === null ? 0.0 : (float) $s['values'][$i];
                        if ($v > 0) $lastPos[$i] = $j;
                        elseif ($v < 0) $lastNeg[$i] = $j;
                    }
                }
            }
            foreach ($vis as $j => $s) {
                $out .= '<g class="gk-chart-series gk-chart-c' . $s['color'] . '" data-gk-chart-s="' . $s['index'] . '">';
                for ($i = 0; $i < $n; $i++) {
                    if ($s['values'][$i] === null) continue;
                    $v = (float) $s['values'][$i];
                    if ($v == 0.0) continue;
                    $a = $cy($i) - $groupW / 2 + ($single ? 0 : $j * ($barW + self::GAP));
                    $right2 = $v > 0;
                    if ($c['stacked']) {
                        if ($right2) {
                            $b0 = $x($pos[$i]);
                            $pos[$i] += $v;
                            $b1 = $x($pos[$i]);
                            $isFirst = !$seenPos[$i];
                            $seenPos[$i] = true;
                            $round = $lastPos[$i] === $j;
                        } else {
                            $b0 = $x($neg[$i]);
                            $neg[$i] += $v;
                            $b1 = $x($neg[$i]);
                            $isFirst = !$seenNeg[$i];
                            $seenNeg[$i] = true;
                            $round = $lastNeg[$i] === $j;
                        }
                        if (!$isFirst) $b0 = $right2 ? $b0 + self::GAP : $b0 - self::GAP;
                    } else {
                        $b0 = $base;
                        $b1 = $x($v);
                        $isFirst = true;
                        $round = true;
                    }
                    $h = $right2 ? $b1 - $b0 : $b0 - $b1;
                    if ($h < 1) {
                        if (!$isFirst) continue;
                        $b1 = $right2 ? $b0 + 1 : $b0 - 1;
                        $h = 1.0;
                    }
                    $out .= '<path class="gk-chart-bar" data-gk-chart-i="' . $i . '" d="'
                        . self::barPath($a, $barW, $b0, $b1, $round ? min(self::RADIUS, $barW / 2, $h) : 0.0, true) . '"/>';
                }
                $out .= '</g>';
            }
        }
        return $out . '</g>';
    }

    /**
     * The parts of a donut: the first five in colour, the rest folded into "Other".
     *
     * @return list<array{label:string,value:float,canon:string,slot:int|string,index:int}>
     */
    private static function parts(array $c): array
    {
        $values = $c['series'][0]['values'] ?? [];
        $parts = [];
        $other = 0.0;
        foreach ($c['labels'] as $i => $label) {
            $v = ($values[$i] ?? null) === null ? 0.0 : (float) $values[$i];
            if ($i < self::SLOTS) {
                $parts[] = ['label' => $label, 'value' => $v, 'canon' => self::canon($v), 'slot' => $i + 1, 'index' => $i];
            } else {
                $other += $v;
            }
        }
        if (count($c['labels']) > self::SLOTS) {
            $parts[] = ['label' => Lang::t('chart.other'), 'value' => $other, 'canon' => self::canon($other), 'slot' => 'other', 'index' => self::SLOTS];
        }
        return $parts;
    }

    private static function donutBody(array $c, int $S): string
    {
        $e = self::escaper();
        $parts = self::parts($c);
        $total = 0.0;
        foreach ($parts as $part) $total += $part['value'];
        $ctr = $S / 2;
        $R = $ctr - 2;
        $r = $R * 0.62;
        $n = static fn (float $v): string => self::num($v);
        $out = '<g class="gk-chart-marks">';
        if ($total <= 0) {
            $out .= '<circle class="gk-chart-track" cx="' . $n($ctr) . '" cy="' . $n($ctr) . '" r="' . $n(($R + $r) / 2) . '" stroke-width="' . $n($R - $r) . '"/>';
        }
        $nonZero = 0;
        foreach ($parts as $part) if ($part['value'] > 0) $nonZero++;
        $start = -M_PI / 2;
        $tau = 2 * M_PI;
        $cum = 0.0;
        foreach ($parts as $part) {
            if ($total <= 0 || $part['value'] <= 0) continue;
            $a0 = $start + $tau * ($cum / $total);
            $cum += $part['value'];
            $a1 = $start + $tau * ($cum / $total);
            $cls = 'gk-chart-slice gk-chart-' . ($part['slot'] === 'other' ? 'other' : 'c' . $part['slot']);
            $attr = ' class="' . $cls . '" data-gk-chart-i="' . $part['index'] . '"';
            if ($nonZero === 1) {
                $out .= '<path' . $attr . ' fill-rule="evenodd" d="M' . $n($ctr) . ',' . $n($ctr - $R) . 'A' . $n($R) . ',' . $n($R) . ' 0 1 1 ' . $n($ctr) . ',' . $n($ctr + $R)
                    . 'A' . $n($R) . ',' . $n($R) . ' 0 1 1 ' . $n($ctr) . ',' . $n($ctr - $R) . 'Z'
                    . 'M' . $n($ctr) . ',' . $n($ctr - $r) . 'A' . $n($r) . ',' . $n($r) . ' 0 1 0 ' . $n($ctr) . ',' . $n($ctr + $r)
                    . 'A' . $n($r) . ',' . $n($r) . ' 0 1 0 ' . $n($ctr) . ',' . $n($ctr - $r) . 'Z"/>';
                continue;
            }
            $po = 1 / $R;
            $pi = 1 / $r;
            if ($a1 - $a0 <= 2 * $pi) continue;   // thinner than its gap: in the legend and the table only
            $o0 = $a0 + $po;
            $o1 = $a1 - $po;
            $i0 = $a0 + $pi;
            $i1 = $a1 - $pi;
            $out .= '<path' . $attr . ' d="M' . $n($ctr + $R * self::cos($o0)) . ',' . $n($ctr + $R * self::sin($o0))
                . 'A' . $n($R) . ',' . $n($R) . ' 0 ' . ($o1 - $o0 > M_PI ? '1' : '0') . ' 1 ' . $n($ctr + $R * self::cos($o1)) . ',' . $n($ctr + $R * self::sin($o1))
                . 'L' . $n($ctr + $r * self::cos($i1)) . ',' . $n($ctr + $r * self::sin($i1))
                . 'A' . $n($r) . ',' . $n($r) . ' 0 ' . ($i1 - $i0 > M_PI ? '1' : '0') . ' 0 ' . $n($ctr + $r * self::cos($i0)) . ',' . $n($ctr + $r * self::sin($i0))
                . 'Z"/>';
        }
        $out .= '</g>';
        // The total in the hole, compact when it would not fit, left out when even that does not.
        $text = self::formatValue(self::canon($total), $c['format']);
        if (self::len($text) * 11 > 2 * $r - 8) $text = self::compact($total, $c['format']);
        if (self::len($text) * 11 <= 2 * $r - 8) {
            $out .= '<text class="gk-chart-total" x="' . $n($ctr) . '" y="' . $n($ctr + 2) . '" text-anchor="middle">' . $e($text) . '</text>'
                . '<text class="gk-chart-total-label" x="' . $n($ctr) . '" y="' . $n($ctr + 20) . '" text-anchor="middle">' . $e(Lang::t('chart.total')) . '</text>';
        }
        return $out;
    }

    // ── Words: summary, legend, table ─────────────────────────────────────

    /** The <desc>: the type, the categories, each visible series' range (a donut: its parts). */
    private static function summary(array $c): string
    {
        $f = $c['format'];
        $labels = $c['labels'];
        $n = count($labels);
        $typeKey = 'chart.type_' . $c['type'] . ($c['stacked'] ? '_stacked' : '');
        $type = Lang::t($typeKey);
        if ($c['type'] === 'donut') {
            $parts = self::parts($c);
            $total = 0.0;
            foreach ($parts as $part) $total += $part['value'];
            $list = [];
            foreach ($parts as $part) {
                $list[] = self::fill(Lang::t('chart.part'), [
                    'label' => $part['label'], 'value' => self::formatValue($part['canon'], $f), 'share' => self::share($part['value'], $total, $f),
                ]);
            }
            return self::fill(Lang::t('chart.summary_donut'), ['type' => $type, 'total' => self::formatValue(self::canon($total), $f), 'parts' => implode('; ', $list)]);
        }
        $out = $n === 1
            ? self::fill(Lang::t('chart.summary_one'), ['type' => $type, 'first' => $labels[0]])
            : self::fill(Lang::t('chart.summary'), ['type' => $type, 'n' => (string) $n, 'first' => $labels[0], 'last' => $labels[$n - 1]]);
        foreach (self::visible($c) as $s) {
            $first = $last = $min = $max = null;
            $minAt = $maxAt = 0;
            foreach ($s['values'] as $i => $v) {
                if ($v === null) continue;
                $fv = (float) $v;
                if ($first === null) $first = $v;
                $last = $v;
                if ($min === null || $fv < (float) $min) { $min = $v; $minAt = $i; }
                if ($max === null || $fv > (float) $max) { $max = $v; $maxAt = $i; }
            }
            $out .= ' ' . ($first === null
                ? self::fill(Lang::t('chart.summary_none'), ['name' => $s['name']])
                : self::fill(Lang::t('chart.summary_series'), [
                    'name' => $s['name'], 'first' => self::formatValue($first, $f), 'last' => self::formatValue($last, $f),
                    'min' => self::formatValue($min, $f), 'minAt' => $labels[$minAt], 'max' => self::formatValue($max, $f), 'maxAt' => $labels[$maxAt],
                ]));
        }
        if ($c['stacked']) {
            $best = null;
            $at = 0;
            foreach ($labels as $i => $_) {
                $sum = 0.0;
                foreach (self::visible($c) as $s) $sum += $s['values'][$i] === null ? 0.0 : (float) $s['values'][$i];
                if ($best === null || $sum > $best) { $best = $sum; $at = $i; }
            }
            if ($best !== null && self::visible($c) !== []) {
                $out .= ' ' . self::fill(Lang::t('chart.summary_total'), ['max' => self::formatValue(self::canon($best), $f), 'maxAt' => $labels[$at]]);
            }
        }
        return $out;
    }

    /** The legend: the names beside a mark like theirs. A single series has none — the title names it. */
    private static function legend(array $c): string
    {
        $e = self::escaper();
        if (self::isEmpty($c)) return '';
        if ($c['type'] === 'donut') {
            $parts = self::parts($c);
            $total = 0.0;
            foreach ($parts as $part) $total += $part['value'];
            $out = '<ul class="gk-chart-legend">';
            foreach ($parts as $part) {
                $out .= '<li class="gk-chart-key gk-chart-' . ($part['slot'] === 'other' ? 'other' : 'c' . $part['slot']) . '">'
                    . self::keyMark('donut', 1, false)
                    . '<span class="gk-chart-key-label">' . $e($part['label']) . '</span>'
                    . '<span class="gk-chart-key-value">' . $e(self::formatValue($part['canon'], $c['format'])) . ' · ' . $e(self::share($part['value'], $total, $c['format'])) . '</span></li>';
            }
            return $out . '</ul>';
        }
        if (count($c['series']) < 2) return '';
        $out = '<ul class="gk-chart-legend">';
        foreach ($c['series'] as $i => $s) {
            $out .= '<li class="gk-chart-key gk-chart-c' . $s['color'] . ($s['hidden'] ? ' gk-chart-key-off' : '') . '" data-gk-chart-key="' . $i . '">'
                . self::keyMark($c['type'], $s['color'], $c['markers'])
                . '<span class="gk-chart-key-label">' . $e($s['name']) . '</span></li>';
        }
        return $out . '</ul>';
    }

    /** The mark beside a name: a short line (with its point shape) for lines, a swatch for bars, areas and parts. */
    private static function keyMark(string $type, int $slot, bool $markers): string
    {
        $inner = $type === 'line'
            ? '<line class="gk-chart-line" x1="1" y1="6" x2="23" y2="6"/>' . ($markers ? self::marker('gk-chart-dot gk-chart-dot-on', null, 12, 6, $slot) : '')
            : '<rect class="gk-chart-swatch" x="6" y="1" width="12" height="10" rx="2"/>';
        return '<svg class="gk-chart-keymark" viewBox="0 0 24 12" width="24" height="12" aria-hidden="true">' . $inner . '</svg>';
    }

    /** The data as a table: every series (switched off or not), every value in full. */
    private static function dataTable(array $c, string $title): string
    {
        $e = self::escaper();
        $f = $c['format'];
        $num = ' class="gk-num"';
        $head = '<th scope="col">' . $e(Lang::t('chart.category')) . '</th>';
        $rows = '';
        if ($c['type'] === 'donut') {
            $values = $c['series'][0]['values'] ?? [];
            $total = 0.0;
            foreach ($values as $v) $total += $v === null ? 0.0 : (float) $v;
            $head .= '<th scope="col"' . $num . '>' . $e($c['series'][0]['name'] ?? Lang::t('chart.value')) . '</th><th scope="col"' . $num . '>' . $e(Lang::t('chart.share')) . '</th>';
            foreach ($c['labels'] as $i => $label) {
                $v = $values[$i] ?? null;
                $rows .= '<tr><th scope="row">' . $e($label) . '</th><td' . $num . '>' . $e(self::formatValue($v, $f)) . '</td><td' . $num . '>'
                    . $e(self::share($v === null ? 0.0 : (float) $v, $total, $f)) . '</td></tr>';
            }
            $rows .= '<tr><th scope="row">' . $e(Lang::t('chart.total')) . '</th><td' . $num . '>' . $e(self::formatValue(self::canon($total), $f)) . '</td><td' . $num . '>'
                . $e(self::share($total, $total, $f)) . '</td></tr>';
        } else {
            foreach ($c['series'] as $s) $head .= '<th scope="col"' . $num . '>' . $e($s['name']) . '</th>';
            if ($c['stacked']) $head .= '<th scope="col"' . $num . '>' . $e(Lang::t('chart.total')) . '</th>';
            foreach ($c['labels'] as $i => $label) {
                $rows .= '<tr><th scope="row">' . $e($label) . '</th>';
                $sum = 0.0;
                foreach ($c['series'] as $s) {
                    $rows .= '<td' . $num . '>' . $e(self::formatValue($s['values'][$i], $f)) . '</td>';
                    $sum += $s['values'][$i] === null ? 0.0 : (float) $s['values'][$i];
                }
                if ($c['stacked']) $rows .= '<td' . $num . '>' . $e(self::formatValue(self::canon($sum), $f)) . '</td>';
                $rows .= '</tr>';
            }
        }
        $table = '<div class="gk-chart-table-wrap"><table class="gk-table gk-chart-table"><caption class="gk-sr-only">' . $e($title) . '</caption>'
            . '<thead><tr>' . $head . '</tr></thead><tbody>' . $rows . '</tbody></table></div>';
        if ($c['table'] === 'hidden') return '<div class="gk-chart-data gk-sr-only">' . $table . '</div>';
        return '<details class="gk-chart-data"' . ($c['table'] === 'open' ? ' open' : '') . '><summary>' . $e(Lang::t('chart.show_table')) . '</summary>' . $table . '</details>';
    }

    /** A sparkline: a line, maybe a wash, the last point — and its numbers in words as its name. */
    private static function spark(array $c): string
    {
        $e = self::escaper();
        $W = $c['width'];
        $H = $c['height'];
        $s = $c['series'][0] ?? ['name' => '', 'values' => [], 'color' => 1];
        $f = $c['format'];
        $label = $c['title'] !== '' ? $c['title'] : Lang::t('chart.trend');
        $vals = $s['values'];
        $n = count($vals);
        $first = $last = $min = $max = null;
        $count = 0;
        foreach ($vals as $v) {
            if ($v === null) continue;
            $count++;
            if ($first === null) $first = $v;
            $last = $v;
            if ($min === null || (float) $v < (float) $min) $min = $v;
            if ($max === null || (float) $v > (float) $max) $max = $v;
        }
        $name = $first === null
            ? self::fill(Lang::t('chart.spark_empty'), ['label' => $label])
            : self::fill(Lang::t('chart.spark'), [
                'label' => $label, 'n' => (string) $count, 'first' => self::formatValue($first, $f), 'last' => self::formatValue($last, $f),
                'min' => self::formatValue($min, $f), 'max' => self::formatValue($max, $f),
            ]);
        $out = '<svg class="gk-chart-spark gk-chart-c' . $s['color'] . '" viewBox="0 0 ' . $W . ' ' . $H . '" width="' . $W . '" height="' . $H . '" role="img">'
            . '<title>' . $e($name) . '</title>';
        if ($first !== null) {
            $pad = 3;
            $lo = (float) $min;
            $hi = (float) $max;
            $x = static fn (int $i): float => $n === 1 ? $W / 2 : $pad + ($W - 2 * $pad) * $i / ($n - 1);
            $y = static fn (float $v): float => $hi == $lo ? $H / 2 : $pad + ($hi - $v) / ($hi - $lo) * ($H - 2 * $pad);
            $d = '';
            $area = '';
            $run = [];
            $flush = static function () use (&$run, &$d, &$area, $x, $y, $H): void {
                if (count($run) >= 2) {
                    $part = '';
                    foreach ($run as $k => [$i, $v]) $part .= ($k === 0 ? 'M' : 'L') . self::num($x($i)) . ',' . self::num($y($v));
                    $d .= $part;
                    $area .= $part . 'L' . self::num($x($run[count($run) - 1][0])) . ',' . self::num($H) . 'L' . self::num($x($run[0][0])) . ',' . self::num($H) . 'Z';
                }
                $run = [];
            };
            $lastI = 0;
            foreach ($vals as $i => $v) {
                if ($v === null) { $flush(); continue; }
                $run[] = [$i, (float) $v];
                $lastI = $i;
            }
            $flush();
            if ($c['area'] && $area !== '') $out .= '<path class="gk-chart-area" d="' . $area . '"/>';
            if ($d !== '') $out .= '<path class="gk-chart-line" d="' . $d . '"/>';
            $out .= '<circle class="gk-chart-dot gk-chart-dot-on" cx="' . self::num($x($lastI)) . '" cy="' . self::num($y((float) $last)) . '" r="2.5"/>';
        }
        return $out . '</svg>';
    }

    // ── Numbers as text ───────────────────────────────────────────────────

    /**
     * A double as its shortest decimal string that reads back as the same double, without an
     * exponent: 1234.5, 0.30000000000000004, -0.00005. The script's String(x) finds the same
     * digits (ties to even, as sprintf() here); 400 000 random doubles agreed when this was built.
     */
    private static function canon(float $x): string
    {
        if ($x == 0.0 || !is_finite($x)) return '0';
        $s = '';
        for ($p = 0; $p <= 16; $p++) {
            $s = sprintf('%.' . $p . 'e', $x);
            if ((float) $s === $x) break;
        }
        $neg = $s[0] === '-';
        if ($neg) $s = substr($s, 1);
        $ePos = stripos($s, 'e');
        $exp = 0;
        if ($ePos !== false) {
            $exp = (int) substr($s, $ePos + 1);
            $s = substr($s, 0, $ePos);
        }
        $dot = strpos($s, '.');
        $digits = $dot === false ? $s : substr($s, 0, $dot) . substr($s, $dot + 1);
        $point = ($dot === false ? strlen($s) : $dot) + $exp;
        $trimmed = ltrim($digits, '0');
        $point -= strlen($digits) - strlen($trimmed);
        $digits = rtrim($trimmed, '0');
        if ($digits === '') return '0';
        if ($point <= 0) {
            $int = '0';
            $frac = str_repeat('0', -$point) . $digits;
        } elseif ($point >= strlen($digits)) {
            $int = $digits . str_repeat('0', $point - strlen($digits));
            $frac = '';
        } else {
            $int = substr($digits, 0, $point);
            $frac = substr($digits, $point);
        }
        return ($neg ? '-' : '') . $int . ($frac !== '' ? '.' . $frac : '');
    }

    /** A coordinate: two decimals at most, half away from zero, no trailing zeros, never "-0". */
    private static function num(float $x): string
    {
        $n = (int) floor(abs($x) * 100 + 0.5);
        if ($n === 0) return '0';
        $int = intdiv($n, 100);
        $frac = $n % 100;
        $s = ($x < 0 ? '-' : '') . $int;
        if ($frac === 0) return $s;
        return $s . '.' . rtrim(str_pad((string) $frac, 2, '0', STR_PAD_LEFT), '0');
    }

    /**
     * A decimal string rounded to $d places, half away from zero, as digits only (no sign):
     * ['1234', '50'] for ('-1234.495', 2). Returns [int, frac, isZero].
     *
     * @return array{0:string,1:string,2:bool}
     */
    private static function roundDigits(string $canon, int $d): array
    {
        $s = ltrim($canon, '-');
        $dot = strpos($s, '.');
        $int = $dot === false ? $s : substr($s, 0, $dot);
        $frac = $dot === false ? '' : substr($s, $dot + 1);
        if (strlen($frac) > $d) {
            $up = $frac[$d] >= '5';
            $frac = substr($frac, 0, $d);
            if ($up) {
                $all = $int . $frac;
                $i = strlen($all) - 1;
                while ($i >= 0 && $all[$i] === '9') {
                    $all[$i] = '0';
                    $i--;
                }
                $all = $i < 0 ? '1' . $all : substr($all, 0, $i) . chr(ord($all[$i]) + 1) . substr($all, $i + 1);
                $int = substr($all, 0, strlen($all) - $d);
                $frac = substr($all, strlen($all) - $d);
            }
        } else {
            $frac = str_pad($frac, $d, '0');
        }
        return [$int, $frac, trim($int . $frac, '0') === ''];
    }

    /** Digits rounded to at most $d places, trailing zeros dropped. */
    private static function upTo(string $canon, int $d, array $f): string
    {
        [$int, $frac] = self::roundDigits($canon, $d);
        return self::group($int, rtrim($frac, '0'), $f);
    }

    private static function group(string $int, string $frac, array $f): string
    {
        [$dec, $thou] = self::LOCALES[$f['locale']];
        $out = '';
        $len = strlen($int);
        for ($i = 0; $i < $len; $i++) {
            if ($i > 0 && ($len - $i) % 3 === 0) $out .= $thou;
            $out .= $int[$i];
        }
        return $out . ($frac !== '' ? $dec . $frac : '');
    }

    /** The digits in the format's dress: €1,234.50, 1.234,50 €, 12,5 %, and the minus in front. */
    private static function compose(string $digits, bool $neg, array $f): string
    {
        $loc = self::LOCALES[$f['locale']];
        if ($f['kind'] === 'money') {
            $sym = self::SYMBOLS[$f['currency']] ?? $f['currency'];
            $space = preg_match('/^[A-Z]+$/', $sym) === 1 ? "\u{00A0}" : '';
            $text = $loc[2] === 'after' ? $digits . "\u{00A0}" . $sym : $sym . $space . $digits;
        } elseif ($f['kind'] === 'percent') {
            $text = $digits . $loc[3];
        } else {
            $text = $digits;
        }
        return ($neg ? '-' : '') . $text;
    }

    /** A value as the chart writes it in a tooltip, the legend, the table and the summary; null is "—". */
    private static function formatValue(?string $canon, array $f): string
    {
        if ($canon === null) return '—';
        $d = $f['decimals'];
        if ($d !== null) [$int, $frac, $zero] = self::roundDigits($canon, $d);
        elseif ($f['kind'] === 'money') [$int, $frac, $zero] = self::roundDigits($canon, 2);
        else {
            [$int, $frac, $zero] = self::roundDigits($canon, $f['kind'] === 'percent' ? 1 : 2);
            $frac = rtrim($frac, '0');
        }
        return self::compose(self::group($int, $frac, $f), !$zero && $canon[0] === '-', $f);
    }

    /** A value in thousands, millions or billions with one decimal at most: 12,5 Tsd. €. */
    private static function compact(float $v, array $f): string
    {
        $a = abs($v);
        $tier = $a >= 1e10 ? 3 : ($a >= 1e7 ? 2 : ($a >= 1e4 ? 1 : 0));
        if ($tier === 0) return self::formatValue(self::canon($v), $f);
        $scaled = self::canon($a / self::exact(1, 3 * $tier));
        return self::compose(self::upTo($scaled, 1, $f) . self::LOCALES[$f['locale']][4][$tier - 1], $v < 0, $f);
    }

    /** A part of a whole in percent, one decimal at most, written by the format's locale. */
    private static function share(float $v, float $total, array $f): string
    {
        if ($total <= 0) return '—';
        $p = self::canon($v / $total * 100);
        return self::compose(self::upTo($p, 1, $f), false, ['kind' => 'percent'] + $f);
    }

    // ── Text helpers ──────────────────────────────────────────────────────

    /** Valid UTF-8, no control characters, tabs and line breaks as spaces, no spaces at the ends. */
    private static function clean(string $s): string
    {
        if (preg_match('//u', $s) !== 1) {
            $s = htmlspecialchars_decode(htmlspecialchars($s, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8'), ENT_NOQUOTES);
        }
        $s = (string) preg_replace('/[\t\n\r]/', ' ', $s);
        $s = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $s);
        return (string) preg_replace('/^ +| +$/', '', $s);
    }

    /** Characters (code points), not bytes — without mbstring. */
    private static function len(string $s): int
    {
        return (int) preg_match_all('/./su', $s);
    }

    /** Cut to $max characters, the last of them an ellipsis. */
    private static function cut(string $s, int $max): string
    {
        if (self::len($s) <= $max) return $s;
        preg_match_all('/./su', $s, $m);
        return implode('', array_slice($m[0], 0, $max - 1)) . '…';
    }

    /** {name} replaced in one pass: a label that holds "{n}" stays as it is. */
    private static function fill(string $text, array $params): string
    {
        return (string) preg_replace_callback('/\{(\w+)\}/', static fn (array $m): string => array_key_exists($m[1], $params) ? (string) $params[$m[1]] : $m[0], $text);
    }

    /** The chart's id as a piece of an HTML id: as it is when it is safe, else a short FNV-1a hash (the script computes the same). */
    private static function key(string $id): string
    {
        if (preg_match('/^[A-Za-z0-9_-]{1,64}$/', $id) === 1) return $id;
        $h = 0x811c9dc5;
        $len = strlen($id);
        for ($i = 0; $i < $len; $i++) {
            $h ^= ord($id[$i]);
            $h = ($h * 0x01000193) & 0xFFFFFFFF;
        }
        return 'h' . str_pad(dechex($h), 8, '0', STR_PAD_LEFT);
    }

    private static function family(string $locale): string
    {
        $f = strtolower(substr($locale, 0, 2));
        return isset(self::LOCALES[$f]) ? $f : 'en';
    }

    /**
     * sin() and cos() of their own: a Taylor series on a quarter turn, nothing but +, -, × and ÷,
     * so PHP and every browser get the same digits — the platform's sin() differs in the last bit.
     */
    private static function sin(float $x): float
    {
        $tau = 2 * M_PI;
        $x -= $tau * floor($x / $tau + 0.5);
        if ($x > M_PI / 2) $x = M_PI - $x;
        elseif ($x < -M_PI / 2) $x = -M_PI - $x;
        $s = $x * $x;
        return $x * (1 + $s * (-1 / 6 + $s * (1 / 120 + $s * (-1 / 5040 + $s * (1 / 362880 + $s * (-1 / 39916800
            + $s * (1 / 6227020800 + $s * (-1 / 1307674368000 + $s * (1 / 355687428096000)))))))));
    }

    private static function cos(float $x): float
    {
        return self::sin($x + M_PI / 2);
    }

    private static function escaper(): \Closure
    {
        return static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
