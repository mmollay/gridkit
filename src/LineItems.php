<?php
declare(strict_types=1);

namespace GridKit;

/**
 * LineItems (1.117.0) — the lines of an invoice, an offer or an order: an editor that posts
 * `name[i][field]`, works out its totals while the person types, and the same totals in PHP.
 *
 *     $lines = (new LineItems('items'))->invoiceColumns()->locale('de')->vatRates([20, 10])
 *         ->items($invoice['items']);
 *     $lines->render();
 *
 *     // On the POST:
 *     $in = $lines->parse($_POST);   // items (clean), rows (as typed), errors, totals
 *
 * Why the arithmetic is done in integers: an invoice that is a cent off is wrong, and binary
 * floats are a cent off often enough (0.1 + 0.2, 1.005 × 100). Every figure is read into a
 * scaled integer — quantities and prices to 4 decimals, percentages to 2 — and multiplied,
 * summed and rounded as such. The products outgrow 64 bits (qty × price × discount is up to
 * 10^30 before it is divided down), so the few operations needed are written out on decimal
 * strings below; GMP and BCMath are not there on every host, and GridKit has no dependencies.
 * js/gridkit.js does the same with BigInt; the browser test compares both to the cent.
 */
class LineItems
{
    /** What a column may be. */
    private const TYPES = ['text', 'textarea', 'number', 'select', 'money', 'percent', 'computed', 'readonly', 'lookup'];

    /** The roles the totals read, and the types each may stand on. */
    private const ROLES = [
        'qty'      => ['number'],
        'price'    => ['money', 'number'],
        'discount' => ['percent', 'number'],
        'vat'      => ['select', 'percent', 'number'],
        'total'    => ['computed'],
    ];

    /** Internal scales: a quantity and a price are kept to 4 decimals, a percentage to 2. */
    private const SCALE = ['qty' => 4, 'price' => 4, 'discount' => 2, 'vat' => 2];

    /** The default decimals a person may type, and the most a type can keep. */
    private const DECIMALS = ['number' => [3, 4], 'money' => [2, 4], 'percent' => [2, 2]];

    /** A grid track per type. No 'auto' track: every line is a grid of its own, and only fixed
     *  tracks and fr shares with a fixed minimum come out the same width in every line. */
    private const TRACKS = [
        'text' => 'minmax(9rem, 2fr)', 'textarea' => 'minmax(9rem, 2fr)', 'lookup' => 'minmax(8rem, 1fr)',
        'number' => '4.5rem', 'select' => '7rem', 'money' => '7rem', 'percent' => '4.25rem',
        'computed' => '7.5rem', 'readonly' => '6rem',
    ];

    /** The most lines parse() reads, whatever maxRows() says: a POST with a million rows is an attack. */
    private const HARD_MAX_ROWS = 1000;

    /** Number shapes by language: decimal sign, thousands sign, where the currency goes. */
    private const LOCALES = [
        'en' => ['.', ',', 'before'],
        'de' => [',', '.', 'after'],
    ];

    private const SYMBOLS = ['EUR' => '€', 'USD' => '$', 'GBP' => '£'];

    private string $name;
    /** @var array<string,array<string,mixed>> */
    private array $columns = [];
    /** @var list<array<string,mixed>> */
    private array $items = [];
    private bool $itemsSet = false;
    private string $currency = 'EUR';
    private ?string $locale = null;
    /** @var list<string> canonical rates */
    private array $vatRates = ['20', '10', '13', '0'];
    /** @var array<string,string>|null value => label; null: the default set from the language files */
    private ?array $units = null;
    private bool $pricesNet = true;
    private string $rounding = 'line';
    private ?string $discount = null;
    private ?string $shipping = null;
    private ?string $shippingVat = null;
    private int $minRows = 0;
    private ?int $maxRows = null;
    private bool $readonly = false;
    /** @var array<string,string> by HTML name: items[0][qty] */
    private array $errors = [];
    private string $label = '';
    /** Set by forForm(): the id of the Form label that names the editor, and of its error line. */
    private ?string $labelledBy = null;
    private ?string $describedBy = null;

    public function __construct(string $name)
    {
        // The name is the key of the POST array and the stem of every id; brackets would make
        // it a path ("invoice[items]") that parse() cannot follow.
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_-]*$/', $name) !== 1) {
            trigger_error("GridKit: LineItems name '$name' is not a plain name (letters, digits, _ and -)."
                . " The editor renders, but parse() reads \$request['$name'] literally.", E_USER_WARNING);
        }
        $this->name = $name;
    }

    /* ── Setup ─────────────────────────────────────────────────────────── */

    /**
     * One column. Types: text, textarea (grows, so a description has room for a second line),
     * number, select, money, percent, computed (the line total), readonly (shown and posted back,
     * not edited) and lookup (an article search that fills the line).
     *
     * Options: type, role, label shown when '' is given is the role's own, required, decimals,
     * min, max, default, placeholder, width (a grid track: 6rem, 120px, 2fr, minmax(8rem, 1fr)),
     * options (select: value => label), and for a lookup url, fill, labelField, valueField,
     * subtextField, minChars, searchParam and display.
     *
     * @param array<string,mixed> $opts
     */
    public function column(string $key, string $label, array $opts = []): static
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_-]*$/', $key) !== 1 || $key === 'id') {
            trigger_error("GridKit: LineItems column '$key' — a key is a plain name and not 'id' (the line's own id"
                . " travels by itself). The column was left out.", E_USER_WARNING);
            return $this;
        }
        $type = $opts['type'] ?? 'text';
        if (!is_string($type) || !in_array($type, self::TYPES, true)) {
            $shown = is_scalar($type) ? (string) $type : get_debug_type($type);
            trigger_error("GridKit: LineItems column '$key' has type '$shown' — it takes "
                . implode(', ', self::TYPES) . ". It is a text column.", E_USER_WARNING);
            $type = 'text';
        }
        $role = array_key_exists('role', $opts) ? $opts['role'] : (isset(self::ROLES[$key]) ? $key : null);
        if ($type === 'computed' && $role === null) $role = 'total';
        if ($role !== null && (!is_string($role) || !isset(self::ROLES[$role]) || !in_array($type, self::ROLES[$role], true))) {
            $shown = is_scalar($role) ? (string) $role : get_debug_type($role);
            if (array_key_exists('role', $opts)) {
                trigger_error("GridKit: LineItems column '$key' ($type) cannot have the role '$shown'. The totals do not read it.", E_USER_WARNING);
            }
            $role = null;
        }
        if ($role !== null) {
            foreach ($this->columns as $other => $c) {
                if ($c['role'] === $role && $other !== $key) {
                    trigger_error("GridKit: LineItems columns '$other' and '$key' both have the role '$role'; '$other' keeps it.", E_USER_WARNING);
                    $role = null;
                    break;
                }
            }
        }

        $col = ['key' => $key, 'label' => $label, 'type' => $type, 'role' => $role, 'required' => !empty($opts['required'])];
        if (isset(self::DECIMALS[$type])) {
            [$default, $most] = self::DECIMALS[$type];
            $dec = $opts['decimals'] ?? $default;
            if (!is_int($dec) || $dec < 0 || $dec > $most) {
                $shown = is_scalar($dec) ? (string) $dec : get_debug_type($dec);
                trigger_error("GridKit: LineItems column '$key' has 'decimals' => $shown — a $type column keeps 0 to $most. It takes $default.", E_USER_WARNING);
                $dec = $default;
            }
            $col['decimals'] = $dec;
            foreach (['min', 'max'] as $bound) {
                if (!array_key_exists($bound, $opts) || $opts[$bound] === null) { $col[$bound] = null; continue; }
                $col[$bound] = self::machine($opts[$bound]);
                if ($col[$bound] === null) {
                    trigger_error("GridKit: LineItems column '$key' has a '$bound' that is not a number. It is left out.", E_USER_WARNING);
                }
            }
        }
        if (array_key_exists('default', $opts) && $opts['default'] !== null) {
            $col['default'] = is_scalar($opts['default']) ? (isset(self::DECIMALS[$type]) ? (self::machine($opts['default']) ?? '') : (string) $opts['default']) : '';
        }
        if (isset($opts['placeholder']) && is_scalar($opts['placeholder'])) $col['placeholder'] = (string) $opts['placeholder'];
        if ($type === 'textarea') $col['rows'] = max(1, (int) ($opts['rows'] ?? 1));
        if ($type === 'select' && isset($opts['options'])) {
            $col['options'] = [];
            foreach ((array) $opts['options'] as $value => $text) $col['options'][(string) $value] = (string) $text;
        }
        if (isset($opts['width'])) {
            $w = is_string($opts['width']) ? trim($opts['width']) : '';
            if (preg_match('/^(\d+(\.\d+)?(px|rem|em|ch|fr|%)|minmax\(\s*\d+(\.\d+)?(px|rem|em|ch|%)\s*,\s*\d+(\.\d+)?(px|rem|em|ch|fr|%)\s*\))$/', $w) === 1) {
                $col['width'] = $w;
            } else {
                trigger_error("GridKit: LineItems column '$key' has a 'width' that is not a grid track (6rem, 120px, 2fr, minmax(8rem, 1fr)). It keeps its default.", E_USER_WARNING);
            }
        }
        if ($type === 'lookup') {
            $col['url'] = is_string($opts['url'] ?? null) ? $opts['url'] : '';
            if ($col['url'] === '') trigger_error("GridKit: LineItems lookup column '$key' has no 'url' to search.", E_USER_WARNING);
            $fill = [];
            foreach ((array) ($opts['fill'] ?? []) as $target => $field) {
                if (is_string($target) && is_string($field)) $fill[$target] = $field;
            }
            $col['fill'] = $fill;
            $col['labelField'] = (string) ($opts['labelField'] ?? 'name');
            $col['valueField'] = (string) ($opts['valueField'] ?? 'id');
            $col['subtextField'] = (string) ($opts['subtextField'] ?? '');
            $col['minChars'] = max(0, (int) ($opts['minChars'] ?? 2));
            $col['searchParam'] = (string) ($opts['searchParam'] ?? 'q');
            $col['display'] = isset($opts['display']) && is_string($opts['display']) ? $opts['display'] : null;
        }
        $this->columns[$key] = $col;
        return $this;
    }

    /**
     * The columns of an invoice: description (grows to a second line), quantity, unit, unit
     * price, discount %, VAT % and the line total. The position number is always the first
     * cell. Labels follow the language; units() and vatRates() may be called before or after.
     */
    public function invoiceColumns(): static
    {
        return $this
            ->column('description', '', ['type' => 'textarea', 'required' => true])
            ->column('qty', '', ['type' => 'number', 'required' => true, 'default' => '1'])
            ->column('unit', '', ['type' => 'select'])
            ->column('price', '', ['type' => 'money', 'required' => true])
            ->column('discount', '', ['type' => 'percent', 'min' => 0, 'max' => 100])
            ->column('vat', '', ['type' => 'select', 'required' => true])
            ->column('total', '', ['type' => 'computed']);
    }

    /**
     * The lines there are: rows of the database or what parse() returned as 'rows'. Numbers are
     * read in machine form (1234.5, an int or a float); a string that is no such number is shown
     * as it is — that is what parse() hands back for a figure that could not be read. A line's
     * 'id' is posted back with it.
     *
     * @param array<int|string,mixed> $rows
     */
    public function items(array $rows): static
    {
        $this->items = [];
        foreach ($rows as $row) {
            if (is_array($row)) $this->items[] = $row;
        }
        $this->itemsSet = true;
        return $this;
    }

    /** The currency of the amounts: 'EUR' (default), 'USD', 'GBP' or any other ISO code. */
    public function currency(string $code): static
    {
        $code = strtoupper(trim($code));
        if (preg_match('/^[A-Z]{3}$/', $code) !== 1) {
            trigger_error("GridKit: LineItems currency '$code' is not an ISO code like EUR. It stays {$this->currency}.", E_USER_WARNING);
            return $this;
        }
        $this->currency = $code;
        return $this;
    }

    /**
     * How numbers are read and written: 'de' (and de-AT, de-CH …) with a decimal comma, 'en' with
     * a decimal point. Default: the language of the page (Lang::locale()). Whatever is set, a
     * person may type 1.234,50, 1234.5 or 1 234,50 — see parseNumber().
     */
    public function locale(string $locale): static
    {
        self::localeFamily($locale, true);
        $this->locale = $locale;
        return $this;
    }

    /**
     * The VAT rates the VAT column offers, in the order given: [20, 10, 13, 0] (the default,
     * Austria). Decimal rates are fine (7.7).
     *
     * @param list<int|float|string> $rates
     */
    public function vatRates(array $rates): static
    {
        $out = [];
        foreach ($rates as $rate) {
            $c = self::machine($rate);
            if ($c === null || str_starts_with($c, '-')) {
                trigger_error('GridKit: LineItems vatRates() takes numbers of 0 or more; ' . var_export($rate, true) . ' was left out.', E_USER_WARNING);
                continue;
            }
            if (!in_array($c, $out, true)) $out[] = $c;
        }
        if ($out !== []) $this->vatRates = $out;
        return $this;
    }

    /**
     * The units the unit column offers: value => label (['h' => 'hours', 'pcs' => 'pieces']).
     * Default: pcs, h, day, month, km, flat rate in the language of the page.
     *
     * @param array<string,string> $units
     */
    public function units(array $units): static
    {
        $this->units = [];
        foreach ($units as $value => $text) $this->units[(string) $value] = (string) $text;
        return $this;
    }

    /** true (default): prices are net and VAT is added. false: prices include VAT, and it is taken out. */
    public function pricesNet(bool $net = true): static
    {
        $this->pricesNet = $net;
        return $this;
    }

    /**
     * 'line' (default): every line total is rounded to the cent, and the totals are the sums of
     * what the lines show. 'total': the lines are summed unrounded, each VAT rate's sum is rounded
     * once; the lines still show cents, and their sum may differ from the total by a cent.
     */
    public function rounding(string $mode): static
    {
        if (!in_array($mode, ['line', 'total'], true)) {
            trigger_error("GridKit: LineItems rounding('$mode') — it takes 'line' or 'total'. It stays '{$this->rounding}'.", E_USER_WARNING);
            return $this;
        }
        $this->rounding = $mode;
        return $this;
    }

    /**
     * An overall discount in percent, a line of the totals with its own field (posted as
     * name_discount). false takes the line out again.
     */
    public function discount(bool|int|float|string $percent = 0): static
    {
        if ($percent === false) { $this->discount = null; return $this; }
        $this->discount = $percent === true ? '0' : (self::machine($percent) ?? '0');
        return $this;
    }

    /**
     * Shipping, a line of the totals with its own field (posted as name_shipping), net or gross
     * like the prices. Its VAT is $vatRate, by default the first of vatRates(). false takes it out.
     */
    public function shipping(bool|int|float|string $amount = 0, int|float|string|null $vatRate = null): static
    {
        if ($amount === false) { $this->shipping = null; return $this; }
        $this->shipping = $amount === true ? '0' : (self::machine($amount) ?? '0');
        $this->shippingVat = $vatRate === null ? null : self::machine($vatRate);
        return $this;
    }

    /** At least $min lines (the editor starts with that many empty ones), at most $max (null: no limit). */
    public function limits(int $min = 0, ?int $max = null): static
    {
        if ($min < 0 || ($max !== null && $max < max(1, $min))) {
            trigger_error("GridKit: LineItems limits($min, " . var_export($max, true) . ') — min is 0 or more and max at least min and 1. Unchanged.', E_USER_WARNING);
            return $this;
        }
        $this->minRows = $min;
        $this->maxRows = $max;
        return $this;
    }

    /** A sent invoice: the same layout, the figures as text, no field, no button, nothing that posts. */
    public function readonly(bool $readonly = true): static
    {
        $this->readonly = $readonly;
        return $this;
    }

    /**
     * Messages to show in the cells, keyed like the POST — items[0][qty] — or with dots, items.0.qty.
     * `items` itself is a message for the whole editor (too few lines), `items_discount` and
     * `items_shipping` belong to those fields. parse() returns them in this shape.
     *
     * @param array<string,string> $errors
     */
    public function errors(array $errors): static
    {
        foreach ($errors as $key => $message) {
            $key = (string) $key;
            if (preg_match('/^' . preg_quote($this->name, '/') . '\.(\d+)\.([A-Za-z_][A-Za-z0-9_-]*)$/', $key, $m) === 1) {
                $key = $this->name . '[' . $m[1] . '][' . $m[2] . ']';
            }
            if (is_scalar($message)) $this->errors[$key] = (string) $message;
        }
        return $this;
    }

    /** The accessible name of the editor (default: "Line items"). Inside a Form the field's label names it. */
    public function label(string $text): static
    {
        $this->label = $text;
        return $this;
    }

    /* ── Output ────────────────────────────────────────────────────────── */

    /** Prints the editor. */
    public function render(): void
    {
        echo $this->html();
    }

    /**
     * What Form draws for a field of type 'lineitems' (1.117.0): the editor given as 'lines',
     * named by the field's label. The Form's value for the field becomes the lines when the editor
     * has none of its own.
     */
    public static function forForm(mixed $lines, string $field, mixed $value = null): string
    {
        if (!$lines instanceof self) {
            trigger_error("GridKit: Form field '$field' is of type 'lineitems' and needs 'lines' => new LineItems(…). It is left out.", E_USER_WARNING);
            return '';
        }
        if (!$lines->itemsSet && is_array($value)) $lines->items($value);
        $lines->labelledBy = $field . '-label';
        $lines->describedBy = $field . '-error';
        return $lines->html();
    }

    /** The editor as a string. */
    public function html(): string
    {
        $e = static fn(mixed $s): string => htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $cols = $this->resolvedColumns();
        $rows = $this->displayRows($cols);
        // Every line shows its own total; the sums count the lines that are lines (not blank) —
        // exactly what parse() keeps, so the page and the POST add up the same.
        $numbers = array_map(fn(array $r): array => $this->numbersOf($r, $cols), $rows);
        $lineTotals = self::totals($numbers, $this->totalsOptions())['lines'];
        $kept = [];
        foreach ($rows as $k => $r) if (!$this->isBlank($r, $cols)) $kept[] = $numbers[$k];
        $totals = self::totals($kept, $this->totalsOptions());
        $base = self::slug($this->name);
        $editable = !$this->readonly;

        $tracks = ['var(--gk-lines-pos)'];
        foreach ($cols as $c) $tracks[] = $c['width'] ?? self::TRACKS[$c['type']];
        if ($editable) $tracks[] = 'var(--gk-lines-act)';

        $name = $this->labelledBy !== null
            ? ' aria-labelledby="' . $e($this->labelledBy) . '"' . ($this->describedBy !== null ? ' aria-describedby="' . $e($this->describedBy) . '"' : '')
            : ' aria-label="' . $e($this->label !== '' ? $this->label : Lang::t('lineitems.label')) . '"';
        $out = '<div class="gk-lines' . ($this->readonly ? ' gk-lines-readonly' : '') . '" id="gk-lines-' . $e($base) . '"'
             . ' role="group"' . $name
             . ' style="--gk-lines-cols: ' . $e(implode(' ', $tracks)) . '"'
             . ' data-gk-lines="' . $e($this->configJson($cols)) . '">';

        // The heading row is for the eye: every field carries its own label (hidden on a wide
        // screen, shown on a card), so a screen reader hears each column's name where it is.
        $out .= '<div class="gk-lines-head" aria-hidden="true"><span class="gk-lines-th gk-lines-pos">' . $e(Lang::t('lineitems.pos')) . '</span>';
        foreach ($cols as $c) {
            // A rate is a figure: right-aligned as text; as a select it reads from the left.
            $num = in_array($c['type'], ['number', 'money', 'percent', 'computed'], true) || ($this->readonly && $c['role'] === 'vat');
            $out .= '<span class="gk-lines-th' . ($num ? ' gk-td-num' : '') . '">' . $e($c['label']) . '</span>';
        }
        if ($editable) $out .= '<span class="gk-lines-th"></span>';
        $out .= '</div>';

        $out .= '<ol class="gk-lines-body"' . ($editable ? ' data-gk-sortierbar' : '') . '>';
        $nonBlank = 0;
        foreach ($rows as $r) if (!$this->isBlank($r, $cols)) $nonBlank++;
        $requiredBlank = max(0, $this->minRows - $nonBlank);
        foreach ($rows as $i => $row) {
            $blank = $this->isBlank($row, $cols);
            $required = !$blank || $requiredBlank-- > 0;
            $out .= $this->rowHtml($row, $i, 'r' . ($i + 1), $cols, $lineTotals[$i]['total'], $required, $e);
        }
        $out .= '</ol>';

        $out .= '<div class="gk-empty" data-gk-lines-empty' . ($rows === [] ? '' : ' hidden') . '><div class="gk-empty-inner">'
              . '<span class="material-icons gk-empty-icon" aria-hidden="true">receipt_long</span>'
              . '<span class="gk-empty-title">' . $e(Lang::t('lineitems.empty')) . '</span>'
              . ($editable ? '<span class="gk-empty-hint">' . $e(Lang::t('lineitems.empty_hint')) . '</span>' : '')
              . '</div></div>';

        // The editor's own message line — inside a Form the field has one already, keyed the same.
        if ($this->labelledBy === null && $editable) {
            $out .= '<div class="gk-field-error" role="alert" data-gk-error="' . $e($this->name) . '">' . $e($this->errors[$this->name] ?? '') . '</div>';
        }

        $out .= '<div class="gk-lines-foot">';
        if ($editable) {
            $full = $this->maxRows !== null && count($rows) >= $this->maxRows;
            $out .= '<div class="gk-lines-tools">' . Button::render(Lang::t('lineitems.add'), [
                'icon' => 'add', 'variant' => 'tonal', 'color' => 'primary', 'size' => 'sm',
                'disabled' => $full, 'data' => ['gk-lines-action' => 'add'],
            ]) . '</div>';
        }
        $out .= $this->totalsHtml($totals, $base, $e);
        $out .= '</div>';

        if ($editable) {
            // What "Add line" puts in: an empty line as the server draws it, renumbered by the script.
            $blank = $this->blankRow($cols);
            $out .= '<template data-gk-lines-template>' . $this->rowHtml($blank, 0, 'new', $cols, '0', true, $e) . '</template>';
            $out .= '<p class="gk-sr-only" aria-live="polite" data-gk-lines-live></p>';
        }
        return $out . '</div>';
    }

    /* ── Reading a POST ────────────────────────────────────────────────── */

    /**
     * Reads what the editor posted: `$lines->parse($_POST)`.
     *
     *  - items:    the lines to keep — empty lines dropped, numbers in machine form ('1234.5'),
     *              text trimmed, the line total under the total column's key, the 'id' if posted
     *  - rows:     every posted line in posted order, a figure that could not be read left as typed;
     *              hand it to items() with errors() to show the form again
     *  - errors:   messages keyed by the POST name of the field: items[2][qty]; 'items' for the
     *              number of lines; items_discount, items_shipping
     *  - discount, shipping: the posted values in machine form, or null
     *  - totals:   totals() of the items — the figures to store, whatever the browser showed
     *
     * @param array<string,mixed> $request
     * @return array{items: list<array<string,mixed>>, rows: list<array<string,mixed>>, errors: array<string,string>, discount: ?string, shipping: ?string, totals: array<string,mixed>}
     */
    public function parse(array $request): array
    {
        $cols = $this->resolvedColumns();
        $locale = $this->localeKey();
        $posted = $request[$this->name] ?? [];
        $posted = is_array($posted) ? array_values($posted) : [];
        $errors = [];
        $cap = min(self::HARD_MAX_ROWS, $this->maxRows ?? self::HARD_MAX_ROWS);
        if (count($posted) > $cap) {
            $errors[$this->name] = Lang::t('lineitems.err_max_rows', ['n' => $cap]);
            $posted = array_slice($posted, 0, $cap);
        }

        $rows = [];
        $items = [];
        foreach ($posted as $i => $raw) {
            $raw = is_array($raw) ? $raw : [];
            $row = [];
            $rowErrors = [];
            if (array_key_exists('id', $raw)) $row['id'] = is_scalar($raw['id']) ? trim((string) $raw['id']) : '';
            foreach ($cols as $key => $c) {
                if ($c['type'] === 'computed') continue;
                $value = $raw[$key] ?? '';
                if (!is_scalar($value)) {
                    $row[$key] = '';
                    $rowErrors[$key] = Lang::t('lineitems.err_invalid');
                    continue;
                }
                $text = trim((string) $value);
                if (isset(self::DECIMALS[$c['type']])) {
                    if ($text === '') { $row[$key] = ''; continue; }
                    $canon = self::parseNumber($text, $locale);
                    $row[$key] = $canon ?? $text;
                    $problem = $this->numberProblem($canon, $c);
                    if ($problem !== null) $rowErrors[$key] = $problem;
                } elseif ($c['type'] === 'select') {
                    $row[$key] = $text;
                    if ($text !== '' && !array_key_exists($text, $c['options'])) $rowErrors[$key] = Lang::t('lineitems.err_option');
                } elseif ($c['type'] === 'lookup') {
                    $row[$key] = $text;
                    if ($c['display'] !== null) {
                        $shown = $raw[$c['display']] ?? '';
                        $row[$c['display']] = is_scalar($shown) ? trim((string) $shown) : '';
                    }
                } else {
                    // A form posts the line breaks of a textarea as CRLF; stored, they are \n, as typed.
                    $row[$key] = str_replace("\r\n", "\n", $text);
                }
            }
            $rows[] = $row;
            if ($this->isBlank($row, $cols)) continue;
            foreach ($cols as $key => $c) {
                if ($c['required'] && $c['type'] !== 'computed' && ($row[$key] ?? '') === '' && !isset($rowErrors[$key])) {
                    $rowErrors[$key] = Lang::t('lineitems.err_required', ['name' => $c['label']]);
                }
            }
            foreach ($rowErrors as $key => $message) $errors[$this->name . '[' . $i . '][' . $key . ']'] = $message;
            $items[] = $row;
        }
        if (count($items) < $this->minRows) {
            $errors[$this->name] ??= Lang::t('lineitems.err_min_rows', ['n' => $this->minRows]);
        }

        $adjust = [];
        foreach (['discount', 'shipping'] as $what) {
            $adjust[$what] = null;
            if ($this->$what === null) continue;
            $value = $request[$this->name . '_' . $what] ?? '';
            $text = is_scalar($value) ? trim((string) $value) : "\0";
            if ($text === '') continue;
            $canon = self::parseNumber($text, $locale);
            $spec = $what === 'discount'
                ? ['type' => 'percent', 'decimals' => 2, 'min' => '0', 'max' => '100']
                : ['type' => 'money', 'decimals' => 2, 'min' => '0', 'max' => null];
            // Counted as typed when it can be read, like a figure of a line — an error does not
            // change what the totals say, so the browser and this agree on both.
            $problem = $this->numberProblem($canon, $spec);
            if ($problem !== null) $errors[$this->name . '_' . $what] = $problem;
            $adjust[$what] = $canon;
        }

        $opts = $this->totalsOptions();
        $opts['discount'] = $this->discount === null ? null : ($adjust['discount'] ?? '0');
        $opts['shipping'] = $this->shipping === null ? null : ($adjust['shipping'] ?? '0');
        $totals = self::totals(array_map(fn(array $r): array => $this->numbersOf($r, $cols), $items), $opts);
        $totalKey = $this->roleKey($cols, 'total');
        if ($totalKey !== null) {
            foreach ($items as $j => $item) $items[$j][$totalKey] = $totals['lines'][$j]['total'];
        }

        return ['items' => $items, 'rows' => $rows, 'errors' => $errors,
                'discount' => $adjust['discount'], 'shipping' => $adjust['shipping'], 'totals' => $totals];
    }

    /**
     * What totals() needs to agree with this editor: pricesNet, rounding, discount, shipping,
     * shippingVat and keys (which column is the quantity, the price, the discount and the VAT).
     *
     * @return array<string,mixed>
     */
    public function totalsOptions(): array
    {
        $cols = $this->resolvedColumns();
        $keys = [];
        foreach (['qty', 'price', 'discount', 'vat'] as $role) $keys[$role] = $this->roleKey($cols, $role);
        return [
            'pricesNet' => $this->pricesNet,
            'rounding' => $this->rounding,
            'discount' => $this->discount,
            'shipping' => $this->shipping,
            'shippingVat' => $this->shippingRate(),
            'keys' => $keys,
        ];
    }

    /* ── The arithmetic ────────────────────────────────────────────────── */

    /**
     * The totals of a set of lines — exact to the cent, the same as the editor shows.
     *
     * Rows hold numbers in machine form (1234.5, '1234.50', an int or a float); with
     * $opts['locale'] strings are read as a person types them (parseNumber()). A figure that
     * cannot be read counts as 0. Options:
     *   pricesNet (true)   prices are net: VAT = round(net × rate). false: prices are gross,
     *                      VAT = round(gross × rate / (100 + rate)), net = gross − VAT.
     *   rounding ('line')  'line': each line to the cent; 'total': each VAT rate's sum once.
     *   discount (null)    an overall discount in %, taken off each rate's sum before VAT.
     *   shipping (null)    an amount (net or gross like the prices) under the rate shippingVat.
     *   shippingVat        default: the highest rate among the lines, 0 without lines.
     *   keys               ['qty' => 'qty', 'price' => 'price', 'discount' => 'discount',
     *                      'vat' => 'vat']; null for one the lines do not have (qty then is 1).
     *   locale             read strings as typed in this language instead of machine form.
     *
     * Every amount comes back as a string with two decimals, "-12.30":
     *   lines    [['total' => …, 'vat' => '20'], …] in the order given
     *   subtotal the sum of the lines (net or gross like the prices)
     *   discount the overall discount (≤ 0), shipping, net, gross
     *   vat      [['rate' => '20', 'base' => net, 'amount' => …], …], highest rate first; a
     *            rate whose amount and base are both 0 is left out
     *   vatTotal the sum of the VAT
     * Rounding is half away from zero: 0.005 → 0.01, −0.005 → −0.01.
     *
     * @param array<int|string,mixed> $rows
     * @param array<string,mixed> $opts
     * @return array{lines: list<array{total: string, vat: string}>, subtotal: string, discount: string, shipping: string, net: string, vat: list<array{rate: string, base: string, amount: string}>, vatTotal: string, gross: string, pricesNet: bool, rounding: string}
     */
    public static function totals(array $rows, array $opts = []): array
    {
        $net = (bool) ($opts['pricesNet'] ?? true);
        $mode = ($opts['rounding'] ?? 'line') === 'total' ? 'total' : 'line';
        $keys = (array) ($opts['keys'] ?? []) + ['qty' => 'qty', 'price' => 'price', 'discount' => 'discount', 'vat' => 'vat'];
        $locale = isset($opts['locale']) && is_string($opts['locale']) ? $opts['locale'] : null;
        $read = static fn(mixed $v): ?string => $locale === null ? self::machine($v) : self::parseNumber($v, $locale);
        $scaled = static function (array $row, string $role) use ($keys, $read): ?string {
            $key = $keys[$role];
            if ($key === null) return null;
            return self::toScaled($read($row[$key] ?? null) ?? '0', self::SCALE[$role]);
        };

        $lineScale = '10000000000';   // 10^4 (qty) × 10^4 (price) × 10^4 (100 − discount, in hundredths of a %) / 10^2 (cents)
        $groups = [];   // scaled rate => [exact sum, rounded sum]
        $lines = [];
        foreach ($rows as $row) {
            $row = is_array($row) ? $row : [];
            $q = $scaled($row, 'qty') ?? '10000';
            $p = $scaled($row, 'price') ?? '0';
            $d = $scaled($row, 'discount') ?? '0';
            $r = $scaled($row, 'vat') ?? '0';
            $exact = self::bMul(self::bMul($q, $p), self::bAdd('10000', self::bNeg($d)));
            $cents = self::bDivRound($exact, $lineScale);
            $lines[] = ['total' => self::fromScaled($cents, 2, 2), 'vat' => self::fromScaled($r, 2)];
            $groups[$r] ??= ['0', '0'];
            $groups[$r] = [self::bAdd($groups[$r][0], $exact), self::bAdd($groups[$r][1], $cents)];
        }

        $sums = [];
        $subtotal = '0';
        foreach ($groups as $r => [$exact, $cents]) {
            $sums[(string) $r] = $mode === 'line' ? $cents : self::bDivRound($exact, $lineScale);
            $subtotal = self::bAdd($subtotal, $sums[(string) $r]);
        }

        $discount = '0';
        $percent = isset($opts['discount']) ? self::toScaled(self::machine($opts['discount']) ?? '0', 2) : '0';
        if ($percent !== '0') {
            foreach ($sums as $r => $sum) {
                $off = self::bDivRound(self::bMul($sum, $percent), '10000');
                $sums[$r] = self::bAdd($sum, self::bNeg($off));
                $discount = self::bAdd($discount, self::bNeg($off));
            }
        }

        $shipping = isset($opts['shipping']) ? self::bDivRound(self::toScaled(self::machine($opts['shipping']) ?? '0', 4), '100') : '0';
        if ($shipping !== '0') {
            $rate = isset($opts['shippingVat']) ? self::machine($opts['shippingVat']) : null;
            if ($rate !== null) {
                $rate = self::toScaled($rate, 2);
            } else {
                $rate = '0';
                foreach (array_keys($sums) as $r) if (self::bCmp((string) $r, $rate) > 0) $rate = (string) $r;
            }
            $sums[$rate] = self::bAdd($sums[$rate] ?? '0', $shipping);
        }

        $vat = [];
        $vatTotal = '0';
        $netTotal = '0';
        $grossTotal = '0';
        uksort($sums, static fn(int|string $a, int|string $b): int => self::bCmp((string) $b, (string) $a));
        foreach ($sums as $r => $sum) {
            $r = (string) $r;
            if ($net) {
                $base = $sum;
                $amount = self::bDivRound(self::bMul($sum, $r), '10000');
                $gross = self::bAdd($sum, $amount);
            } else {
                $gross = $sum;
                $amount = self::bDivRound(self::bMul($sum, $r), self::bAdd('10000', $r));
                $base = self::bAdd($sum, self::bNeg($amount));
            }
            $netTotal = self::bAdd($netTotal, $base);
            $grossTotal = self::bAdd($grossTotal, $gross);
            $vatTotal = self::bAdd($vatTotal, $amount);
            if ($base === '0' && $amount === '0') continue;
            $vat[] = ['rate' => self::fromScaled($r, 2), 'base' => self::fromScaled($base, 2, 2), 'amount' => self::fromScaled($amount, 2, 2)];
        }

        return [
            'lines' => $lines,
            'subtotal' => self::fromScaled($subtotal, 2, 2),
            'discount' => self::fromScaled($discount, 2, 2),
            'shipping' => self::fromScaled($shipping, 2, 2),
            'net' => self::fromScaled($netTotal, 2, 2),
            'vat' => $vat,
            'vatTotal' => self::fromScaled($vatTotal, 2, 2),
            'gross' => self::fromScaled($grossTotal, 2, 2),
            'pricesNet' => $net,
            'rounding' => $mode,
        ];
    }

    /**
     * A number as a person types it, in machine form — '1234.5' — or null when it is none.
     *
     * Spaces (also no-break and thin ones) and apostrophes are thousands marks and go; a currency
     * sign or code (€ $ £ EUR USD GBP CHF) and a % at either end go. A minus may be - or −.
     * With both . and , the last one is the decimal sign: 1.234,50 and 1,234.50 are the same.
     * With one of them: repeated, it groups thousands (1.234.567, every group three digits);
     * once, it is the decimal sign — unless exactly three digits follow and one to three come
     * before (1.500, 12,500): only then does the language decide, as it would for a person.
     * No exponent, no hex, no other digits, at most 15 digits before the decimal sign.
     */
    public static function parseNumber(mixed $input, string $locale = 'en'): ?string
    {
        if (is_int($input) || is_float($input)) return self::machine($input);
        if (!is_string($input) || strlen($input) > 64) return null;
        $dec = self::LOCALES[self::localeFamily($locale)][0];
        // The spaces by name, not \s: js/gridkit.js reads the same list, and \s means more there.
        $s = (string) preg_replace("/[ \\t\\n\\x0B\\f\\r\x{00A0}\x{2007}\x{2009}\x{202F}'\x{2019}]+/u", '', $input);
        $cur = '(?:€|\$|£|EUR|USD|GBP|CHF)';
        $s = (string) preg_replace('/^' . $cur . '|(?:' . $cur . '|%)$/iu', '', $s);
        $sign = '';
        foreach (['-' => '-', '+' => '', "\u{2212}" => '-'] as $mark => $as) {
            if (str_starts_with($s, $mark)) {
                $sign = $as;
                $s = (string) preg_replace('/^' . $cur . '/iu', '', substr($s, strlen($mark)));
                break;
            }
        }
        if (preg_match('/^[0-9.,]+$/', $s) !== 1 || preg_match('/[0-9]/', $s) !== 1) return null;

        $dots = substr_count($s, '.');
        $commas = substr_count($s, ',');
        $frac = '';
        if ($dots > 0 && $commas > 0) {
            $decSep = strrpos($s, '.') > strrpos($s, ',') ? '.' : ',';
            $thou = $decSep === '.' ? ',' : '.';
            if (substr_count($s, $decSep) !== 1) return null;
            [$int, $frac] = explode($decSep, $s);
            if (str_contains($frac, $thou) || !self::groupsOk($int, $thou)) return null;
            $int = str_replace($thou, '', $int);
        } elseif ($dots + $commas > 0) {
            $sep = $dots > 0 ? '.' : ',';
            if ($dots + $commas > 1) {
                if (!self::groupsOk($s, $sep)) return null;
                $int = str_replace($sep, '', $s);
            } else {
                [$int, $frac] = explode($sep, $s);
                if (preg_match('/^[1-9]\d{0,2}$/', $int) === 1 && preg_match('/^\d{3}$/', $frac) === 1 && $sep !== $dec) {
                    $int .= $frac;
                    $frac = '';
                }
            }
        } else {
            $int = $s;
        }
        $int = ltrim($int, '0');
        $frac = rtrim($frac, '0');
        if ($int === '') $int = '0';
        if (strlen($int) > 15 || strlen($frac) > 12) return null;
        $out = $frac === '' ? $int : $int . '.' . $frac;
        return $out === '0' ? '0' : $sign . $out;
    }

    /* ── Private: the parts of the markup ──────────────────────────────── */

    /**
     * The columns with what render() needs settled: labels, options of the unit and VAT selects.
     *
     * @return array<string,array<string,mixed>>
     */
    private function resolvedColumns(): array
    {
        $out = [];
        foreach ($this->columns as $key => $c) {
            if ($c['label'] === '') {
                $c['label'] = match (true) {
                    $c['role'] === 'price' && !$this->pricesNet => Lang::t('lineitems.price_gross'),
                    $c['role'] !== null => Lang::t('lineitems.' . $c['role']),
                    $key === 'description', $key === 'unit' => Lang::t('lineitems.' . $key),
                    default => $key,
                };
            }
            if ($c['type'] === 'select' && !isset($c['options'])) {
                if ($c['role'] === 'vat') {
                    $c['options'] = [];
                    foreach ($this->vatRates as $rate) $c['options'][$rate] = $this->percentText($rate);
                } elseif ($key === 'unit') {
                    $c['options'] = ['' => ''] + $this->unitOptions();
                } else {
                    $c['options'] = [];
                }
            }
            if ($c['role'] === 'vat' && $c['type'] === 'select' && !isset($c['default'])) {
                $c['default'] = (string) array_key_first($c['options']);
            }
            $c['default'] ??= '';
            $out[$key] = $c;
        }
        return $out;
    }

    /** @return array<string,string> */
    private function unitOptions(): array
    {
        if ($this->units !== null) return $this->units;
        $out = [];
        foreach (['pcs', 'h', 'day', 'month', 'km', 'flat'] as $u) $out[$u] = Lang::t('lineitems.unit_' . $u);
        return $out;
    }

    /**
     * The lines to draw: the items, or as many empty ones as limits() asks for.
     *
     * @param array<string,array<string,mixed>> $cols
     * @return list<array<string,mixed>>
     */
    private function displayRows(array $cols): array
    {
        $rows = $this->items;
        while (count($rows) < $this->minRows) $rows[] = $this->blankRow($cols);
        return $rows;
    }

    /**
     * @param array<string,array<string,mixed>> $cols
     * @return array<string,string>
     */
    private function blankRow(array $cols): array
    {
        $row = [];
        foreach ($cols as $key => $c) if ($c['type'] !== 'computed') $row[$key] = $c['default'];
        return $row;
    }

    /**
     * A line nobody has filled in: every field empty or at its default. It is not a line — parse()
     * drops it and nothing in it is required (except the first ones limits() asks for).
     *
     * @param array<string,mixed> $row
     * @param array<string,array<string,mixed>> $cols
     */
    private function isBlank(array $row, array $cols): bool
    {
        foreach ($cols as $key => $c) {
            if (!in_array($c['type'], ['text', 'textarea', 'number', 'money', 'percent', 'lookup'], true)) continue;
            $v = $row[$key] ?? '';
            $v = is_scalar($v) ? trim((string) $v) : '';
            if (isset(self::DECIMALS[$c['type']])) $v = self::machine($v) ?? $v;
            if ($v !== '' && $v !== $c['default']) return false;
        }
        return true;
    }

    /**
     * The figures of a row the way totals() reads them: machine numbers, null for what is none.
     *
     * @param array<string,mixed> $row
     * @param array<string,array<string,mixed>> $cols
     * @return array<string,?string>
     */
    private function numbersOf(array $row, array $cols): array
    {
        $out = [];
        foreach ($cols as $key => $c) {
            if ($c['role'] !== null && $c['role'] !== 'total') $out[$key] = self::machine($row[$key] ?? null);
        }
        return $out;
    }

    /** @param array<string,array<string,mixed>> $cols */
    private function roleKey(array $cols, string $role): ?string
    {
        foreach ($cols as $key => $c) if ($c['role'] === $role) return (string) $key;
        return null;
    }

    private function shippingRate(): ?string
    {
        return $this->shipping === null ? null : $this->shippingVat;
    }

    /**
     * Why a typed figure cannot be taken, or null. The message the browser shows is the same.
     *
     * @param array<string,mixed> $c
     */
    private function numberProblem(?string $canon, array $c): ?string
    {
        if ($canon === null) {
            return Lang::t('lineitems.err_number', ['example' => $this->display('1234.5', 2)]);
        }
        $frac = str_contains($canon, '.') ? strlen(substr($canon, strpos($canon, '.') + 1)) : 0;
        if ($frac > $c['decimals']) return Lang::t('lineitems.err_decimals', ['n' => $c['decimals']]);
        $num = self::toScaled($canon, 4);
        if (($c['min'] ?? null) !== null && self::bCmp($num, self::toScaled($c['min'], 4)) < 0) {
            return Lang::t('lineitems.err_min', ['min' => $this->display($c['min'], 0)]);
        }
        if (($c['max'] ?? null) !== null && self::bCmp($num, self::toScaled($c['max'], 4)) > 0) {
            return Lang::t('lineitems.err_max', ['max' => $this->display($c['max'], 0)]);
        }
        return null;
    }

    /**
     * One line. $key is its id in the page (r1, r2 …; the script gives new lines their own),
     * $i its place, which is what the POST name carries.
     *
     * @param array<string,mixed> $row
     * @param array<string,array<string,mixed>> $cols
     */
    private function rowHtml(array $row, int $i, string $key, array $cols, string $total, bool $required, \Closure $e): string
    {
        $n = $i + 1;
        $line = Lang::t('lineitems.line', ['n' => $n]);
        $first = '';
        foreach ($cols as $k => $c) {
            if (in_array($c['type'], ['text', 'textarea'], true)) {
                $first = trim(strtok(is_scalar($row[$k] ?? '') ? (string) ($row[$k] ?? '') : '', "\n") ?: '');
                break;
            }
        }
        $title = $first === '' ? $line : Lang::t('lineitems.line_titled', ['n' => $n, 'text' => $first]);
        $out = '<li class="gk-lines-row"' . ($this->readonly ? '' : ' data-gk-sort-id="' . $e($key) . '" data-gk-sort-titel="' . $e($title) . '"') . '>'
             . '<div class="gk-lines-line" role="group" aria-label="' . $e($line) . '">'
             . '<div class="gk-lines-cell gk-lines-pos" aria-hidden="true">' . $n . '</div>';
        foreach ($cols as $k => $c) {
            $out .= $this->readonly
                ? $this->readonlyCell($row, (string) $k, $c, $total, $e)
                : $this->cellHtml($row, $i, $key, (string) $k, $c, $total, $required, $e);
        }
        if (!$this->readonly) {
            $out .= '<div class="gk-lines-cell gk-lines-actions"><div class="gk-action-group">'
                  . Button::render('', ['icon' => 'content_copy', 'variant' => 'text', 'color' => 'neutral', 'size' => 'sm',
                        'aria' => Lang::t('lineitems.duplicate', ['n' => $n]), 'title' => Lang::t('action.duplicate'),
                        'data' => ['gk-lines-action' => 'duplicate']])
                  . Button::render('', ['icon' => 'delete', 'variant' => 'text', 'color' => 'danger', 'size' => 'sm',
                        'aria' => Lang::t('lineitems.delete', ['n' => $n]), 'title' => Lang::t('action.delete'),
                        'data' => ['gk-lines-action' => 'delete']])
                  . '</div></div>';
            if (array_key_exists('id', $row) && is_scalar($row['id'])) {
                $out .= '<input type="hidden" data-gk-lines-field="id" name="' . $e($this->name . '[' . $i . '][id]') . '" value="' . $e($row['id']) . '">';
            }
        }
        return $out . '</div></li>';
    }

    /**
     * One editable cell: its label (hidden on a wide screen, shown on a card), the field, and the
     * line under it that a message goes into — keyed like the POST, so an AJAX Form's
     * {"errors": {"items[0][qty]": …}} lands here without anything else.
     *
     * @param array<string,mixed> $row
     * @param array<string,mixed> $c
     */
    private function cellHtml(array $row, int $i, string $rowKey, string $key, array $c, string $total, bool $required, \Closure $e): string
    {
        $base = self::slug($this->name);
        $id = $base . '-' . $rowKey . '-' . $key;
        $postName = $this->name . '[' . $i . '][' . $key . ']';
        $raw = $row[$key] ?? '';
        $raw = is_scalar($raw) ? (string) $raw : '';
        $num = isset(self::DECIMALS[$c['type']]);
        $cls = 'gk-lines-cell' . ($num || $c['type'] === 'computed' ? ' gk-lines-num' : '') . self::wideClass($c);
        $cell = '<div class="' . $cls . '" data-gk-lines-cell="' . $e($key) . '">';

        if ($c['type'] === 'computed') {
            return $cell . '<span class="gk-lines-label">' . $e($c['label']) . '</span>'
                 . '<span class="gk-lines-out gk-td-num" data-gk-lines-out="' . $e($key) . '">' . $e($this->money($total)) . '</span></div>';
        }
        if ($c['type'] === 'readonly') {
            return $cell . '<span class="gk-lines-label">' . $e($c['label']) . '</span>'
                 . '<span class="gk-lines-out">' . $e($raw) . '</span>'
                 . '<input type="hidden" data-gk-lines-field="' . $e($key) . '" name="' . $e($postName) . '" value="' . $e($raw) . '"></div>';
        }

        $error = $this->errors[$postName] ?? '';
        $invalid = $error !== '' ? ' aria-invalid="true"' : '';
        $errCls = $error !== '' ? ' gk-has-error' : '';
        $isReq = $c['required'] && $required;
        $attrs = ' id="' . $e($id) . '" name="' . $e($postName) . '" data-gk-lines-field="' . $e($key) . '"'
               . ($c['required'] ? ' data-gk-lines-required' : '') . ($isReq ? ' required' : '')
               . ' aria-describedby="' . $e($id . '-error') . '"' . $invalid
               . (isset($c['placeholder']) ? ' placeholder="' . $e($c['placeholder']) . '"' : '');
        $label = $c['type'] === 'lookup'
            ? '<span class="gk-lines-label" id="' . $e($id . '-label') . '">' . $e($c['label']) . '</span>'
            : '<label class="gk-lines-label" for="' . $e($id) . '">' . $e($c['label']) . '</label>';

        $field = match ($c['type']) {
            'textarea' => '<textarea class="gk-input' . $errCls . '" rows="' . (int) $c['rows'] . '"' . $attrs . '>' . $e($raw) . '</textarea>',
            'select' => $this->selectHtml($c, $raw, $attrs, $errCls, $e),
            'number', 'money', 'percent' => '<input type="text" inputmode="decimal" autocomplete="off" class="gk-input gk-td-num' . $errCls . '"'
                . $attrs . ' value="' . $e($this->inputValue($raw, $c)) . '">',
            'lookup' => $this->lookupHtml($row, $c, $id, $postName, $raw, $isReq, $e, $invalid),
            default => '<input type="text" class="gk-input' . $errCls . '"' . $attrs . ' value="' . $e($raw) . '">',
        };
        return $cell . $label . $field
             . '<div class="gk-field-error" id="' . $e($id . '-error') . '" role="alert" data-gk-error="' . $e($postName) . '">' . $e($error) . '</div></div>';
    }

    /** Text, a description and an article search take the whole width of a card on a phone. */
    private static function wideClass(array $c): string
    {
        return in_array($c['type'], ['text', 'textarea', 'lookup'], true) ? ' gk-lines-wide' : '';
    }

    /** @param array<string,mixed> $c */
    private function selectHtml(array $c, string $raw, string $attrs, string $errCls, \Closure $e): string
    {
        $options = $c['options'];
        $wanted = $c['role'] === 'vat' ? (self::machine($raw) ?? $raw) : $raw;
        // A value the list does not know (an old unit, a rate no longer offered) is shown as it is,
        // rather than silently turning into the first option — and marked, because parse() will
        // not take it: the browser says so before the form is sent.
        $unknown = $wanted !== '' && !array_key_exists($wanted, $options);
        $out = '<select class="gk-input' . $errCls . '"' . $attrs . '>';
        foreach ($options as $value => $text) {
            $value = (string) $value;
            $out .= '<option value="' . $e($value) . '"' . ($value === $wanted ? ' selected' : '') . '>' . $e($text) . '</option>';
        }
        if ($unknown) {
            $text = $c['role'] === 'vat' && self::machine($raw) !== null ? $this->percentText((string) self::machine($raw)) : $raw;
            $out .= '<option value="' . $e($wanted) . '" selected data-gk-lines-unknown>' . $e($text) . '</option>';
        }
        return $out . '</select>';
    }

    /**
     * The search of a 'lookup' column: Form's 'ajaxselect' markup, which GK.ajaxSelect binds, from
     * its one source (Form::ajaxSelectHtml()) — the article's id posted under the column's key.
     *
     * @param array<string,mixed> $row
     * @param array<string,mixed> $c
     */
    private function lookupHtml(array $row, array $c, string $id, string $postName, string $raw, bool $isReq, \Closure $e, string $invalid = ''): string
    {
        $shown = $c['display'] !== null && is_scalar($row[$c['display']] ?? null) ? (string) $row[$c['display']] : '';
        $labelledBy = ' aria-labelledby="' . $e($id . '-label') . '"';
        return Form::ajaxSelectHtml(
            $e, $c,
            value: $raw,
            shown: $shown,
            placeholder: $c['placeholder'] ?? Lang::t('lineitems.search_article'),
            idBase: $id,
            hideClear: $raw === '',
            hidden: ' id="' . $e($id) . '" name="' . $e($postName) . '" data-gk-lines-field="' . $e($c['key']) . '"'
                . ($c['required'] ? ' data-gk-lines-required' : '') . ($isReq ? ' required' : ''),
            // aria-invalid on the combobox when the line has an error, as the other cells have (1.122.1).
            combo: $labelledBy . ' aria-describedby="' . $e($id . '-error') . '"' . $invalid,
            // With 'display' the words shown post too (name[i][display]), so a form shown again
            // after its POST still names the article.
            searchBox: $c['display'] !== null
                ? ' name="' . $e(preg_replace('/\[[^\[\]]*\]$/', '[' . $c['display'] . ']', $postName)) . '" data-gk-lines-field="' . $e($c['display']) . '"'
                : '',
            searchTail: $labelledBy,
        );
    }

    /**
     * One cell of a sent invoice: the label and the figure as text. The figure also stands in
     * data-gk-lines-value in machine form, so GK.lineItems.items() reads it like a field.
     *
     * @param array<string,mixed> $row
     * @param array<string,mixed> $c
     */
    private function readonlyCell(array $row, string $key, array $c, string $total, \Closure $e): string
    {
        $raw = $row[$key] ?? '';
        $raw = is_scalar($raw) ? (string) $raw : '';
        $num = isset(self::DECIMALS[$c['type']]) || $c['type'] === 'computed';
        $value = $c['type'] === 'computed' ? $total : (isset(self::DECIMALS[$c['type']]) ? (self::machine($raw) ?? $raw) : $raw);
        $text = match (true) {
            $c['type'] === 'computed' => $this->money($total),
            $c['type'] === 'money' => self::machine($raw) !== null ? $this->money((string) self::machine($raw), $c['decimals']) : $raw,
            $num => $this->inputValue($raw, $c),
            $c['type'] === 'select' => $c['role'] === 'vat' && self::machine($raw) !== null
                ? ($c['options'][(string) self::machine($raw)] ?? $this->percentText((string) self::machine($raw)))
                : ($c['options'][$raw] ?? $raw),
            $c['type'] === 'lookup' => $c['display'] !== null && is_scalar($row[$c['display']] ?? null) ? (string) $row[$c['display']] : $raw,
            default => $raw,
        };
        $right = $num || $c['role'] === 'vat';
        $out = '<div class="gk-lines-cell' . ($right ? ' gk-lines-num' : '') . self::wideClass($c) . '" data-gk-lines-cell="' . $e($key) . '" data-gk-lines-value="' . $e($value) . '">'
             . '<span class="gk-lines-label">' . $e($c['label']) . '</span>';
        if ($c['type'] === 'textarea' || $c['type'] === 'text') {
            // The first line is the item, the rest its detail — set quieter beneath it.
            $parts = preg_split('/\R/', $text, 2) ?: [''];
            $out .= '<span class="gk-lines-out">' . $e($parts[0]) . '</span>';
            if (isset($parts[1]) && trim($parts[1]) !== '') $out .= '<span class="gk-lines-sub">' . nl2br($e(trim($parts[1])), false) . '</span>';
        } else {
            $out .= '<span class="gk-lines-out' . ($right ? ' gk-td-num' : '') . '"' . ($c['type'] === 'computed' ? ' data-gk-lines-out="' . $e($key) . '"' : '') . '>' . $e($text) . '</span>';
        }
        return $out . '</div>';
    }

    /**
     * The totals under the lines: subtotal, the discount and shipping when there are any (with
     * their fields), net, one line per VAT rate, the total. The script rewrites the figures and
     * the VAT lines in place, in this very markup.
     *
     * @param array<string,mixed> $t
     */
    private function totalsHtml(array $t, string $base, \Closure $e): string
    {
        $sum = fn(string $what, string $label, string $amount, string $extra = '', string $cls = '', string $value = ''): string =>
            '<div class="gk-lines-sum' . $cls . '" data-gk-lines-sum="' . $what . '"'
            . ($value !== '' ? ' data-gk-lines-value="' . $e($value) . '"' : '') . '><dt>' . $label . $extra . '</dt>'
            . '<dd class="gk-td-num">' . $e($this->money($amount)) . '</dd></div>';

        $out = '<dl class="gk-lines-totals">';
        $out .= $sum('subtotal', $e(Lang::t($this->pricesNet ? 'lineitems.subtotal_net' : 'lineitems.subtotal_gross')), $t['subtotal']);
        foreach (['discount' => $t['discount'], 'shipping' => $t['shipping']] as $what => $amount) {
            if ($this->$what === null) continue;
            $label = Lang::t('lineitems.' . ($what === 'discount' ? 'overall_discount' : 'shipping'));
            $postName = $this->name . '_' . $what;
            $id = $base . '-' . $what;
            if ($this->readonly) {
                // The figure in machine form too, for GK.lineItems.totals() on a sent invoice.
                $extra = $what === 'discount' ? ' ' . $e($this->percentText((string) $this->discount)) : '';
                $out .= $sum($what, $e($label), $amount, $extra, '', (string) $this->$what);
                continue;
            }
            $error = $this->errors[$postName] ?? '';
            $field = '<span class="gk-lines-adjust"><input type="text" inputmode="decimal" autocomplete="off"'
                   . ' class="gk-input gk-td-num' . ($error !== '' ? ' gk-has-error' : '') . '" id="' . $e($id) . '" name="' . $e($postName) . '"'
                   . ' data-gk-lines-adjust="' . $what . '" aria-describedby="' . $e($id . '-error') . '"' . ($error !== '' ? ' aria-invalid="true"' : '')
                   . ' value="' . $e($this->display((string) $this->$what, $what === 'discount' ? 0 : 2)) . '">'
                   . ($what === 'discount' ? '<span aria-hidden="true">%</span>' : '') . '</span>'
                   . '<span class="gk-field-error" id="' . $e($id . '-error') . '" role="alert" data-gk-error="' . $e($postName) . '">' . $e($error) . '</span>';
            $out .= $sum($what, '<label for="' . $e($id) . '">' . $e($label) . '</label>', $amount, $field);
        }
        if ($this->discount !== null || $this->shipping !== null || !$this->pricesNet) {
            $out .= $sum('net', $e(Lang::t('lineitems.net')), $t['net']);
        }
        $out .= $this->vatRowsHtml($t['vat'], $e);
        $out .= $sum('gross', $e(Lang::t('lineitems.gross')), $t['gross'], '', ' gk-lines-grand');
        return $out . '</dl>';
    }

    /** @param list<array{rate: string, base: string, amount: string}> $vat */
    private function vatRowsHtml(array $vat, \Closure $e): string
    {
        $out = '';
        foreach ($vat as $v) {
            $out .= '<div class="gk-lines-sum" data-gk-lines-vat="' . $e($v['rate']) . '"><dt>'
                  . $e(Lang::t($this->pricesNet ? 'lineitems.vat_rate' : 'lineitems.vat_incl', ['rate' => $this->display($v['rate'], 0)]))
                  . '</dt><dd class="gk-td-num">' . $e($this->money($v['amount'])) . '</dd></div>';
        }
        return $out;
    }

    /**
     * What the script needs and cannot read off the markup: the number shapes, the rules of each
     * column and the sentences it writes (so the browser says what the server would).
     *
     * @param array<string,array<string,mixed>> $cols
     */
    private function configJson(array $cols): string
    {
        [$dec, $thou] = self::LOCALES[$this->localeKey()];
        $columns = [];
        foreach ($cols as $key => $c) {
            $col = ['key' => (string) $key, 'type' => $c['type'], 'role' => $c['role'], 'required' => $c['required'],
                    'label' => $c['label'], 'default' => $c['default']];
            if (isset(self::DECIMALS[$c['type']])) {
                $col += ['decimals' => $c['decimals'], 'min' => $c['min'], 'max' => $c['max']];
            }
            if ($c['type'] === 'lookup') $col += ['fill' => (object) $c['fill'], 'display' => $c['display']];
            $columns[] = $col;
        }
        $text = [];
        foreach (['line', 'line_titled', 'duplicate', 'delete', 'deleted', 'undo', 'err_number', 'err_decimals', 'err_min',
                  'err_max', 'err_required', 'err_option', 'err_max_rows', 'vat_rate', 'vat_incl', 'said_total'] as $k) {
            $text[$k] = Lang::t('lineitems.' . $k);
        }
        $text['example'] = $this->display('1234.5', 2);
        return (string) json_encode([
            'name' => $this->name,
            'base' => self::slug($this->name),
            'fmt' => ['dec' => $dec, 'thou' => $thou, 'money' => $this->moneyPattern()],
            'net' => $this->pricesNet,
            'rounding' => $this->rounding,
            'min' => $this->minRows,
            'max' => $this->maxRows,
            'discount' => $this->discount !== null,
            'shipping' => $this->shipping !== null,
            'shippingVat' => $this->shippingRate(),
            'readonly' => $this->readonly,
            'columns' => $columns,
            'text' => $text,
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_HEX_TAG | JSON_HEX_AMP);
    }

    /* ── Private: numbers as text ──────────────────────────────────────── */

    private function localeKey(): string
    {
        return self::localeFamily($this->locale ?? Lang::locale());
    }

    private static function localeFamily(string $locale, bool $warn = false): string
    {
        $family = strtolower(substr($locale, 0, 2));
        if (isset(self::LOCALES[$family])) return $family;
        if ($warn) {
            trigger_error("GridKit: LineItems locale '$locale' is not known — 'en' and 'de' (with their regions) are. It reads and writes numbers the English way.", E_USER_WARNING);
        }
        return 'en';
    }

    /** "{value} €" or "€{value}": where the amount goes in the currency's text. */
    private function moneyPattern(): string
    {
        $sym = self::SYMBOLS[$this->currency] ?? $this->currency;
        $space = preg_match('/^[A-Z]+$/', $sym) === 1 ? "\u{00A0}" : '';   // CHF 12.50, but €12.50
        return self::LOCALES[$this->localeKey()][2] === 'after' ? "{value}\u{00A0}$sym" : $sym . $space . '{value}';
    }

    /** An amount in machine form as the page shows it: 1.234,50 € (de) or €1,234.50 (en). */
    private function money(string $amount, int $decimals = 2): string
    {
        $neg = str_starts_with($amount, '-');
        $text = str_replace('{value}', $this->display($neg ? substr($amount, 1) : $amount, $decimals), $this->moneyPattern());
        return ($neg ? '-' : '') . $text;
    }

    /** A rate as the VAT select and the totals name it: "20 %", "7,7 %". */
    private function percentText(string $rate): string
    {
        return $this->display($rate, 0) . "\u{00A0}%";
    }

    /** What a number field shows: the figure in the page's shape, money padded to its decimals. */
    private function inputValue(string $raw, array $c): string
    {
        $canon = self::machine($raw);
        if ($canon === null) return $raw;
        return $this->display($canon, $c['type'] === 'money' ? $c['decimals'] : 0);
    }

    /** A machine number with the page's separators, at least $minDec decimals, never rounded. */
    private function display(string $canon, int $minDec): string
    {
        [$dec, $thou] = self::LOCALES[$this->localeKey()];
        return self::formatNumber($canon, $minDec, $dec, $thou);
    }

    private static function formatNumber(string $canon, int $minDec, string $dec, string $thou): string
    {
        $neg = str_starts_with($canon, '-');
        $digits = $neg ? substr($canon, 1) : $canon;
        [$int, $frac] = array_pad(explode('.', $digits, 2), 2, '');
        $frac = str_pad($frac, $minDec, '0');
        $grouped = '';
        $len = strlen($int);
        for ($k = 0; $k < $len; $k++) {
            if ($k > 0 && ($len - $k) % 3 === 0) $grouped .= $thou;
            $grouped .= $int[$k];
        }
        return ($neg ? '-' : '') . $grouped . ($frac !== '' ? $dec . $frac : '');
    }

    /**
     * A machine number — an int, a finite float, or a string like -1234.5 — in canonical form
     * ('-1234.5', '0', never '-0', no exponent), or null.
     */
    private static function machine(mixed $v): ?string
    {
        // An int goes through the same door as text: at most 15 digits, as for everything else (and
        // as in the browser, where every number is a float).
        if (is_int($v)) $v = (string) $v;
        if (is_float($v)) {
            // Twelve decimals, fixed: a float from a database (0.30000000000000004) reads as the
            // figure it was meant to be, whatever `precision` the page set — and JavaScript's
            // toFixed(12) writes the same digits.
            if (!is_finite($v)) return null;
            $v = rtrim(rtrim(sprintf('%.12F', $v), '0'), '.');
        }
        if (!is_string($v) || preg_match('/^\s*([+-]?)(\d{1,15})(?:\.(\d{1,20}))?\s*$/', $v, $m) !== 1) return null;
        $int = ltrim($m[2], '0');
        $frac = rtrim($m[3] ?? '', '0');
        $out = ($int === '' ? '0' : $int) . ($frac === '' ? '' : '.' . $frac);
        return $out === '0' || $m[1] !== '-' ? $out : '-' . $out;
    }

    /** Thousands groups: one to three digits, then groups of exactly three. */
    private static function groupsOk(string $s, string $sep): bool
    {
        $parts = explode($sep, $s);
        if (preg_match('/^\d{1,3}$/', $parts[0]) !== 1) return false;
        foreach (array_slice($parts, 1) as $p) if (preg_match('/^\d{3}$/', $p) !== 1) return false;
        return true;
    }

    /** A canonical number as an integer of $scale decimals, rounded half away from zero. */
    private static function toScaled(string $canon, int $scale): string
    {
        $neg = str_starts_with($canon, '-');
        [$int, $frac] = array_pad(explode('.', $neg ? substr($canon, 1) : $canon, 2), 2, '');
        $mag = self::bNorm($int . str_pad(substr($frac, 0, $scale), $scale, '0'));
        if (strlen($frac) > $scale && $frac[$scale] >= '5') $mag = self::bAdd($mag, '1');
        return $neg ? self::bNeg($mag) : $mag;
    }

    /** An integer of $scale decimals as a machine number, with at least $minDec decimals. */
    private static function fromScaled(string $n, int $scale, int $minDec = 0): string
    {
        $neg = str_starts_with($n, '-');
        $mag = str_pad($neg ? substr($n, 1) : $n, $scale + 1, '0', STR_PAD_LEFT);
        $int = substr($mag, 0, -$scale);
        $frac = rtrim(substr($mag, -$scale), '0');
        $frac = str_pad($frac, $minDec, '0');
        $out = $int . ($frac === '' ? '' : '.' . $frac);
        return $neg && $mag !== str_repeat('0', strlen($mag)) ? '-' . $out : $out;
    }

    /** A name reduced to what an id may hold. */
    private static function slug(string $name): string
    {
        return (string) preg_replace('/[^A-Za-z0-9_-]/', '-', $name);
    }

    /* ── Private: integers of any length, as decimal strings ───────────── */

    private static function bNorm(string $a): string
    {
        $neg = str_starts_with($a, '-');
        $mag = ltrim($neg ? substr($a, 1) : $a, '0');
        if ($mag === '') return '0';
        return $neg ? '-' . $mag : $mag;
    }

    private static function bNeg(string $a): string
    {
        if ($a === '0') return '0';
        return str_starts_with($a, '-') ? substr($a, 1) : '-' . $a;
    }

    /** Compares two magnitudes (no sign). */
    private static function mCmp(string $a, string $b): int
    {
        return strlen($a) <=> strlen($b) ?: strcmp($a, $b) <=> 0;
    }

    private static function bCmp(string $a, string $b): int
    {
        $na = str_starts_with($a, '-');
        $nb = str_starts_with($b, '-');
        if ($na !== $nb) return $na ? -1 : 1;
        $c = self::mCmp($na ? substr($a, 1) : $a, $nb ? substr($b, 1) : $b);
        return $na ? -$c : $c;
    }

    private static function mAdd(string $a, string $b): string
    {
        $out = '';
        $carry = 0;
        for ($i = strlen($a) - 1, $j = strlen($b) - 1; $i >= 0 || $j >= 0 || $carry; $i--, $j--) {
            $s = ($i >= 0 ? (int) $a[$i] : 0) + ($j >= 0 ? (int) $b[$j] : 0) + $carry;
            $out = ($s % 10) . $out;
            $carry = intdiv($s, 10);
        }
        return $out === '' ? '0' : $out;
    }

    /** $a − $b for magnitudes with $a ≥ $b. */
    private static function mSub(string $a, string $b): string
    {
        $out = '';
        $borrow = 0;
        for ($i = strlen($a) - 1, $j = strlen($b) - 1; $i >= 0; $i--, $j--) {
            $s = (int) $a[$i] - ($j >= 0 ? (int) $b[$j] : 0) - $borrow;
            $borrow = $s < 0 ? 1 : 0;
            $out = ($s + 10 * $borrow) . $out;
        }
        return self::bNorm($out);
    }

    private static function bAdd(string $a, string $b): string
    {
        $na = str_starts_with($a, '-');
        $nb = str_starts_with($b, '-');
        $ma = $na ? substr($a, 1) : $a;
        $mb = $nb ? substr($b, 1) : $b;
        if ($na === $nb) {
            $s = self::bNorm(self::mAdd($ma, $mb));
            return $na ? self::bNeg($s) : $s;
        }
        $c = self::mCmp($ma, $mb);
        if ($c === 0) return '0';
        return $c > 0 ? ($na ? self::bNeg(self::mSub($ma, $mb)) : self::mSub($ma, $mb))
                      : ($nb ? self::bNeg(self::mSub($mb, $ma)) : self::mSub($mb, $ma));
    }

    private static function bMul(string $a, string $b): string
    {
        $neg = str_starts_with($a, '-') !== str_starts_with($b, '-');
        $ma = ltrim($a, '-');
        $mb = ltrim($b, '-');
        if ($ma === '0' || $mb === '0') return '0';
        // Limbs of four digits: a product of two is under 10^8, a column of them stays far from 2^63.
        $la = array_map('intval', str_split(str_pad($ma, (int) ceil(strlen($ma) / 4) * 4, '0', STR_PAD_LEFT), 4));
        $lb = array_map('intval', str_split(str_pad($mb, (int) ceil(strlen($mb) / 4) * 4, '0', STR_PAD_LEFT), 4));
        $la = array_reverse($la);
        $lb = array_reverse($lb);
        $res = array_fill(0, count($la) + count($lb), 0);
        foreach ($la as $i => $x) {
            if ($x === 0) continue;
            foreach ($lb as $j => $y) $res[$i + $j] += $x * $y;
        }
        $carry = 0;
        foreach ($res as $k => $v) {
            $v += $carry;
            $res[$k] = $v % 10000;
            $carry = intdiv($v, 10000);
        }
        while ($carry > 0) { $res[] = $carry % 10000; $carry = intdiv($carry, 10000); }
        $out = '';
        foreach (array_reverse($res) as $limb) $out .= str_pad((string) $limb, 4, '0', STR_PAD_LEFT);
        $out = self::bNorm($out);
        return $neg ? self::bNeg($out) : $out;
    }

    /** $n / $d rounded half away from zero; $d is positive and small enough for an int (it is: 10^10 at most). */
    private static function bDivRound(string $n, string $d): string
    {
        $neg = str_starts_with($n, '-');
        $mag = $neg ? substr($n, 1) : $n;
        $div = (int) $d;
        $q = '';
        $r = 0;
        $len = strlen($mag);
        for ($k = 0; $k < $len; $k++) {
            $r = $r * 10 + (int) $mag[$k];
            $q .= intdiv($r, $div);
            $r %= $div;
        }
        $q = self::bNorm($q);
        if (2 * $r >= $div) $q = self::bAdd($q, '1');
        return $neg ? self::bNeg($q) : $q;
    }
}
