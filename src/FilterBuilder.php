<?php
declare(strict_types=1);

namespace GridKit;

/**
 * FilterBuilder — conditions a person combines freely, for tables, newsletter segments and invoice
 * lists (1.123.0): rows of [field] [operator] [value], AND/OR groups nested to a set depth, rows
 * added, removed and duplicated, saved views.
 *
 *   $fb = (new FilterBuilder('f'))
 *       ->field('status', 'Status', ['type' => 'select', 'options' => ['open' => 'Open', 'paid' => 'Paid']])
 *       ->field('amount', 'Amount', ['type' => 'money'])
 *       ->field('customer', 'Customer');
 *   $tree = $fb->tree();                                              // the checked conditions
 *   ['sql' => $where, 'params' => $params] = FilterBuilder::toSql($tree, $fb, ['amount' => 'i.total']);
 *   $fb->render();
 *
 * The state has two shapes. The documented one, which tree(), clean() and fromRequest() return:
 *
 *   ['join' => 'and', 'rules' => [
 *       ['field' => 'status', 'op' => 'is', 'value' => 'open'],
 *       ['join' => 'or', 'rules' => [['field' => 'amount', 'op' => 'gt', 'value' => 100], …]],
 *   ]]
 *
 * and the compact one that travels in the URL as JSON in one parameter (encode()):
 * a group is [join, child, child, …], a condition [field, op] or [field, op, value] —
 * ["and",["status","is","open"],["or",["amount","gt",100]]]. Both are read everywhere.
 *
 * Without script the builder is a plain form: nested fieldsets, the controls named name[rules][i][…],
 * submit buttons that add, duplicate and remove (name[cmd]), and the page drawn again from what came
 * back. js/filter-builder.js makes the same form instant and sends the compact JSON instead.
 *
 * Whatever arrives is checked against the fields: an unknown field or operator, a value of the wrong
 * type, a group too deep, rows past the limit — all left out. toSql() writes placeholders only and
 * takes column names from the page's map, never from the request. A wrong option is an E_USER_WARNING
 * and a default; nothing throws.
 */
class FilterBuilder
{
    public const TYPES = ['text', 'number', 'money', 'date', 'bool', 'select', 'multiselect'];

    /** Every operator a type knows, in the order they are offered. */
    public const OPERATORS = [
        'text'        => ['contains', 'not_contains', 'equals', 'not_equals', 'starts', 'ends', 'empty', 'not_empty'],
        'number'      => ['eq', 'ne', 'lt', 'lte', 'gt', 'gte', 'between', 'empty', 'not_empty'],
        'money'       => ['eq', 'ne', 'lt', 'lte', 'gt', 'gte', 'between', 'empty', 'not_empty'],
        'date'        => ['eq', 'ne', 'lt', 'lte', 'gt', 'gte', 'between', 'empty', 'not_empty'],
        'select'      => ['is', 'is_not', 'any_of', 'none_of', 'empty', 'not_empty'],
        'multiselect' => ['has_any', 'has_all', 'has_none', 'empty', 'not_empty'],
        'bool'        => ['true', 'false'],
    ];

    /** What a type offers unless operators() says otherwise. */
    public const DEFAULT_OPERATORS = [
        'text'        => ['contains', 'not_contains', 'equals', 'starts', 'ends', 'empty', 'not_empty'],
        'number'      => ['eq', 'ne', 'lt', 'gt', 'between'],
        'money'       => ['eq', 'ne', 'lt', 'gt', 'between'],
        'date'        => ['eq', 'ne', 'lt', 'gt', 'between'],
        'select'      => ['is', 'is_not', 'any_of'],
        'multiselect' => ['has_any', 'has_all', 'has_none'],
        'bool'        => ['true', 'false'],
    ];

    /** What an operator takes: no value, one, two (between) or a list. */
    private const KIND = [
        'contains' => 'one', 'not_contains' => 'one', 'equals' => 'one', 'not_equals' => 'one',
        'starts' => 'one', 'ends' => 'one', 'empty' => 'none', 'not_empty' => 'none',
        'eq' => 'one', 'ne' => 'one', 'lt' => 'one', 'lte' => 'one', 'gt' => 'one', 'gte' => 'one',
        'between' => 'two', 'is' => 'one', 'is_not' => 'one', 'any_of' => 'list', 'none_of' => 'list',
        'has_any' => 'list', 'has_all' => 'list', 'has_none' => 'list', 'true' => 'none', 'false' => 'none',
    ];

    /** Hard limits, whatever a page sets. */
    private const DEPTH_CAP = 5;
    private const ROWS_CAP = 100;
    private const TEXT_MAX = 200;        // characters of a text value
    private const LIST_MAX = 200;        // values of a list
    private const JSON_MAX = 8192;       // bytes of the JSON parameter
    private const VISIT_MAX = 1000;      // nodes looked at, however the input is shaped
    private const NUMBER_RE = '/^-?\d{1,15}(?:[.,]\d{1,6})?$/';
    private const KEY_RE = '/^[A-Za-z_][A-Za-z0-9_.-]{0,63}$/';
    private const IDENT = '(?:[A-Za-z_][A-Za-z0-9_]{0,63}|`[A-Za-z0-9_ $-]{1,64}`|"[A-Za-z0-9_ $-]{1,64}")';

    private const ICON_ADD = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M12 5v14M5 12h14"/></svg>';
    private const ICON_COPY = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/></svg>';
    private const ICON_REMOVE = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18"/></svg>';
    private const ICON_ERROR = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M12 7v6M12 16.5v.5"/></svg>';

    private string $name;
    /** @var array<string,array{key:string,label:string,type:string,options:list<array{0:string,1:string,2:int|string}>,operators:list<string>|null,placeholder:string}> */
    private array $fields = [];
    /** @var array<string,list<string>> operators() per type */
    private array $typeOps = [];
    private mixed $value = null;
    private int $maxDepth = 3;
    private int $maxRows = 20;
    /** @var list<array{label:string,state:mixed,href:string}> */
    private array $views = [];
    private string $saveUrl = '';
    private bool $localViews = false;
    private string $method = 'get';
    private string $action = '';
    /** @var array<int|string,mixed> */
    private array $preserve = [];
    private string $label = '';
    /** @var array<mixed>|null read instead of $_GET / $_POST */
    private ?array $request = null;

    public function __construct(string $name = 'f')
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_-]{0,63}$/', $name)) {
            $clean = substr((string) preg_replace('/[^A-Za-z0-9_-]/', '', $name), 0, 64);
            if ($clean === '' || !preg_match('/^[A-Za-z_]/', $clean)) $clean = 'f';
            self::warn("the name '$name' is not a plain name (letters, digits, _ and -); it is '$clean'.");
            $name = $clean;
        }
        $this->name = $name;
    }

    /* ── Options ─────────────────────────────────────────────────────────── */

    /**
     * A field the person can filter by. $opts: 'type' (text, number, money, date, bool, select,
     * multiselect; default text), 'options' (select and multiselect: key => label, or a list of
     * ['value' => …, 'label' => …]), 'operators' (the operators of this field, a subset of its type's),
     * 'placeholder' (of a text or number value).
     *
     * @param array<string,mixed> $opts
     */
    public function field(string $key, string $label, array $opts = []): static
    {
        $f = self::normField($key, $label, $opts, true);
        if ($f !== null) {
            if (isset($this->fields[$f['key']])) self::warn("field('{$f['key']}') is defined twice; the second one counts.");
            $this->fields[$f['key']] = $f;
        }
        return $this;
    }

    /**
     * The operators a type offers, in this order — a subset of FilterBuilder::OPERATORS[$type]:
     * ->operators('text', ['contains', 'equals']). A field's own 'operators' option wins.
     *
     * @param iterable<mixed> $ops
     */
    public function operators(string $type, iterable $ops): static
    {
        if (!in_array($type, self::TYPES, true)) {
            self::warn("operators('$type') is not a type; known: " . implode(', ', self::TYPES) . '.');
            return $this;
        }
        $list = self::opList($type, $ops, "operators('$type')");
        if ($list !== []) $this->typeOps[$type] = $list;
        return $this;
    }

    /**
     * What the builder shows when the request carries no state of its own (and what Reset brings
     * back): the documented array shape, the compact one, or its JSON.
     */
    public function value(mixed $state): static
    {
        $this->value = $state;
        return $this;
    }

    /** How deep groups nest, the top level counted as 1 (1 to 5, default 3; 1: no groups). */
    public function maxDepth(int $depth): static
    {
        if ($depth < 1 || $depth > self::DEPTH_CAP) {
            self::warn("maxDepth($depth) is out of range (1 to " . self::DEPTH_CAP . "); it stays {$this->maxDepth}.");
            return $this;
        }
        $this->maxDepth = $depth;
        return $this;
    }

    /** How many conditions in all (1 to 100, default 20). */
    public function maxRows(int $rows): static
    {
        if ($rows < 1 || $rows > self::ROWS_CAP) {
            self::warn("maxRows($rows) is out of range (1 to " . self::ROWS_CAP . "); it stays {$this->maxRows}.");
            return $this;
        }
        $this->maxRows = $rows;
        return $this;
    }

    /**
     * Saved views, shown as links above the conditions: each ['label' => 'My open invoices',
     * 'state' => a state in either shape or its JSON] — and 'href' for a link of your own. The page
     * stores them; the builder only shows them (and marks the one that is applied).
     *
     * @param iterable<mixed> $views
     */
    public function views(iterable $views): static
    {
        $this->views = [];
        foreach ($views as $i => $v) {
            if (is_object($v)) $v = get_object_vars($v);
            $label = is_array($v) ? ($v['label'] ?? $v['name'] ?? null) : null;
            if (!is_string($label) || trim($label) === '') {
                self::warn("views(): view $i has no label; it is left out.");
                continue;
            }
            $href = $v['href'] ?? '';
            if (!is_string($href) || ($href !== '' && self::unsafeUrl($href))) {
                self::warn("views(): the link of view '$label' is not a link; the state is linked instead.");
                $href = '';
            }
            $this->views[] = ['label' => trim($label), 'state' => $v['state'] ?? null, 'href' => $href];
        }
        return $this;
    }

    /**
     * Shows "Save view": a name and a button that sends the form (POST) to this URL — the state under
     * the builder's name, the view's name under name_view. FilterBuilder::savedView() reads both.
     */
    public function saveUrl(string $url): static
    {
        if ($url !== '' && self::unsafeUrl($url)) {
            self::warn("saveUrl('$url') is not a link; there is no Save view.");
            return $this;
        }
        $this->saveUrl = $url;
        return $this;
    }

    /**
     * With the script, views are also kept in this browser (localStorage, gk-filterbuilder-<name>),
     * when no saveUrl() is set, or when the page cancels gk:filtersave and does not store them itself.
     */
    public function localViews(bool $on = true): static
    {
        $this->localViews = $on;
        return $this;
    }

    /** 'get' (default: the filter is in the URL, a view is a link) or 'post'. */
    public function method(string $method): static
    {
        $m = strtolower($method);
        if ($m !== 'get' && $m !== 'post') {
            self::warn("method('$method') is get or post; it stays {$this->method}.");
            return $this;
        }
        $this->method = $m;
        return $this;
    }

    /** Where the form is sent (default: the page itself). A '#section' keeps its fragment. */
    public function action(string $url): static
    {
        if ($url !== '' && self::unsafeUrl($url)) {
            self::warn("action('$url') is not a link; the form is sent to the page itself.");
            return $this;
        }
        $this->action = $url;
        return $this;
    }

    /**
     * Other query parameters the form carries along, so a filter does not drop the sort or the
     * period: names read from the URL (['sort', 'period']), or name => value.
     *
     * @param array<int|string,mixed> $params
     */
    public function preserve(array $params): static
    {
        $this->preserve = $params;
        return $this;
    }

    /** The accessible name of the builder (default "Filter"). */
    public function label(string $text): static
    {
        $this->label = $text;
        return $this;
    }

    /** Read this array instead of $_GET / $_POST (a framework's request, a test). */
    public function request(array $data): static
    {
        $this->request = $data;
        return $this;
    }

    /* ── Reading ─────────────────────────────────────────────────────────── */

    /** The checked conditions of the current state (request, else value()), in the documented shape. @return array<string,mixed> */
    public function tree(): array
    {
        return self::walk($this->draft()[0], $this->spec(), false);
    }

    /**
     * The checked tree a request names, for the page that queries with it:
     * `$tree = FilterBuilder::fromRequest('f', $fb);` — $fields is the builder or an array
     * key => ['label' => …, 'type' => …, 'options' => …]. $opts: 'maxDepth', 'maxRows', 'operators'
     * (type => list), 'default' (the state without a request), 'request' (an array to read instead
     * of $_POST / $_GET). The builder's own commands (add, remove, …) are applied first.
     *
     * @param array<string,mixed>|self $fields
     * @param array<string,mixed> $opts
     * @return array<string,mixed>
     */
    public static function fromRequest(string $name, array|self $fields, array $opts = []): array
    {
        $unknown = array_diff(array_keys($opts), ['maxDepth', 'maxRows', 'operators', 'default', 'request']);
        if ($unknown !== []) self::warn('fromRequest() does not know the option(s) ' . implode(', ', $unknown) . '; they are ignored.');
        $spec = self::specOf($fields, $opts);
        $request = isset($opts['request']) && is_array($opts['request']) ? $opts['request'] : null;
        $raw = self::source($name, $request);
        $draft = $raw === null ? self::walk($opts['default'] ?? null, $spec, true) : self::fromRaw($raw, $spec, $opts['default'] ?? null);
        return self::walk($draft, $spec, false);
    }

    /**
     * Any state — from a request, a stored view, a hand — checked against the fields, in the
     * documented shape. Unknown fields and operators, values of the wrong type, groups too deep and
     * rows past maxRows are left out; an empty group goes too.
     *
     * @param array<string,mixed>|self $fields
     * @param array<string,mixed> $opts 'maxDepth', 'maxRows', 'operators'
     * @return array<string,mixed>
     */
    public static function clean(mixed $state, array|self $fields, array $opts = []): array
    {
        return self::walk($state, self::specOf($fields, $opts), false);
    }

    /** A state as the compact JSON of the URL: ["and",["status","is","open"]]. */
    public static function encode(array $tree): string
    {
        return (string) json_encode(self::compact($tree), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    }

    /**
     * What the "Save view" button sent to saveUrl(): ['label' => …, 'state' => the checked tree,
     * 'json' => the compact JSON] — or null without a name. $opts as fromRequest().
     *
     * @param array<string,mixed>|self $fields
     * @param array<string,mixed> $opts
     * @return array{label:string,state:array<string,mixed>,json:string}|null
     */
    public static function savedView(string $name, array|self $fields, array $opts = []): ?array
    {
        $request = isset($opts['request']) && is_array($opts['request']) ? $opts['request'] : null;
        $label = $request !== null ? ($request[$name . '_view'] ?? null) : ($_POST[$name . '_view'] ?? $_GET[$name . '_view'] ?? null);
        if (!is_string($label)) return null;
        $label = self::text($label, 80);
        if ($label === '') return null;
        $tree = self::fromRequest($name, $fields, $opts);
        return ['label' => $label, 'state' => $tree, 'json' => self::encode($tree)];
    }

    /**
     * The conditions as SQL for a WHERE clause, with placeholders only:
     * ['sql' => "(status = ? AND i.total > ?)", 'params' => ['open', 100]].
     *
     * The tree is checked again first (clean()), so a raw request is safe too. A column is
     * $columnMap[$field] or the field key itself — a plain or quoted identifier, `table.column`
     * allowed — or a callable fn(array $rule, array $field): ['sql' => …, 'params' => […]] for what a
     * column cannot say (a tag table, a JSON column). Text is compared with LIKE … ESCAPE '!' (the
     * database's collation decides about case), a date column may hold times (a day is [day, next
     * day)), "is not" keeps the rows where the column is NULL. Nothing: '1=1'. A column that is not
     * an identifier, a multiselect without a callable: a warning and '1=0' — never a wider result.
     *
     * @param array<string,mixed>|self $fields
     * @param array<string,mixed> $columnMap
     * @return array{sql:string,params:list<int|float|string>}
     */
    public static function toSql(mixed $tree, array|self $fields, array $columnMap = []): array
    {
        $spec = self::specOf($fields, []);
        $tree = self::walk($tree, $spec, false);
        $params = [];
        $sql = self::sqlGroup($tree, $spec, $columnMap, $params, true);
        return ['sql' => $sql === '' ? '1=1' : $sql, 'params' => $params];
    }

    /**
     * Whether a row (an array or an object, keyed by the field keys) passes the conditions — the
     * same answers as toSql() on a database with a case-insensitive collation. A missing key is NULL.
     *
     * @param array<string,mixed>|self $fields
     */
    public static function matches(mixed $tree, array|object $row, array|self $fields): bool
    {
        $spec = self::specOf($fields, []);
        return self::matchGroup(self::walk($tree, $spec, false), $row, $spec);
    }

    /**
     * The conditions in words, in the page's language: "Status is Open AND Amount > 100" —
     * the line under the builder, and a heading for a filtered list.
     *
     * @param array<string,mixed>|self $fields
     */
    public static function summary(mixed $tree, array|self $fields): string
    {
        $spec = self::specOf($fields, []);
        $tree = self::walk($tree, $spec, false);
        return $tree['rules'] === [] ? Lang::t('filterbuilder.summary_none') : self::words($tree, $spec, true);
    }

    /* ── Output ──────────────────────────────────────────────────────────── */

    /** Prints the builder. */
    public function render(): void
    {
        echo $this->html();
    }

    /** The builder as a string. */
    public function html(): string
    {
        $e = self::esc(...);
        $spec = $this->spec();
        if ($spec['fields'] === []) self::warn('the builder has no field(); it shows an empty form.');
        [$draft, $loud] = $this->draft();
        $spec['loud'] = $loud;
        $tree = self::walk($draft, $spec, false);
        $base = 'gk-fb-' . $this->name;
        $label = $this->label !== '' ? $this->label : Lang::t('filterbuilder.label');

        $initial = self::walk($this->value, $spec, true);
        $config = [
            'name'       => $this->name,
            'maxDepth'   => $spec['maxDepth'],
            'maxRows'    => $spec['maxRows'],
            'fields'     => array_values(array_map(fn (array $f): array => [
                'key' => $f['key'], 'label' => $f['label'], 'type' => $f['type'],
                'ops' => array_map(static fn (string $op): array => [$op, self::opLabel($f['type'], $op)], $spec['ops'][$f['key']]),
                'options' => array_map(static fn (array $o): array => [$o[0], $o[1]], $f['options']),
                'placeholder' => $f['placeholder'],
            ], $spec['fields'])),
            'state'      => self::compact($draft, true),
            'initial'    => self::compact($initial, true),
            'loud'       => $loud,
            'localViews' => $this->localViews,
            'saveUrl'    => $this->saveUrl,
            'words'      => self::jsWords($label),
        ];
        $json = (string) json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);

        $out = '<form class="gk-filter-builder" id="' . $e($base) . '" method="' . $this->method . '"'
             . ($this->action !== '' ? ' action="' . $e($this->action) . '"' : '')
             . ' aria-label="' . $e($label) . '" data-gk-filter-builder="' . $e($json) . '">';
        // The default button: Enter in a field applies — not the first remove button of the form.
        $out .= '<button type="submit" class="gk-fb-default" name="' . $e($this->name) . '[cmd]" value="apply" hidden tabindex="-1">' . $e(Lang::t('filterbuilder.apply')) . '</button>';
        foreach ($this->preserved() as $k => $v) {
            $out .= '<input type="hidden" name="' . $e($k) . '" value="' . $e($v) . '">';
        }
        $out .= $this->viewsHtml($tree, $spec);
        $out .= self::groupHtml($draft, [], 1, $spec, $this->name, $base, self::count($draft), $label);

        $incomplete = $loud ? self::countIncomplete($draft) : 0;
        $out .= '<p class="gk-fb-note" data-gk-fb-note' . ($incomplete === 0 ? ' hidden' : '') . '>'
              . self::ICON_ERROR . '<span>' . $e($incomplete === 0 ? '' : self::incompleteText($incomplete)) . '</span></p>';

        $out .= '<div class="gk-fb-foot">';
        $out .= '<p class="gk-fb-summary" id="' . $e($base) . '-summary" aria-live="polite">'
              . '<span class="gk-fb-summary-label">' . $e(Lang::t('filterbuilder.summary_label')) . '</span> '
              . '<span class="gk-fb-summary-text">' . $e(self::summary($tree, $spec['fields'] === [] ? [] : $this)) . '</span></p>';
        $cmd = $e($this->name) . '[cmd]';
        $out .= '<div class="gk-fb-buttons">'
              . '<button type="submit" class="gk-btn gk-btn-filled gk-btn-primary gk-btn-sm gk-fb-apply" name="' . $cmd . '" value="apply"><span>' . $e(Lang::t('filterbuilder.apply')) . '</span></button>'
              . '<button type="submit" class="gk-btn gk-btn-text gk-btn-neutral gk-btn-sm gk-fb-reset" name="' . $cmd . '" value="reset"><span>' . $e(Lang::t('filterbuilder.reset')) . '</span></button>'
              . '</div>';
        if ($this->saveUrl !== '' || $this->localViews) {
            $out .= '<div class="gk-fb-save"' . ($this->saveUrl === '' ? ' hidden' : '') . '>'
                  . '<label class="gk-fb-save-label" for="' . $e($base) . '-view">' . $e(Lang::t('filterbuilder.view_name')) . '</label>'
                  . '<input type="text" class="gk-filter gk-fb-save-name" id="' . $e($base) . '-view" name="' . $e($this->name) . '_view" maxlength="80" autocomplete="off">'
                  . '<button type="submit" class="gk-btn gk-btn-tonal gk-btn-primary gk-btn-sm gk-fb-save-btn" name="' . $e($this->name) . '[cmd]" value="save"'
                  . ($this->saveUrl !== '' ? ' formaction="' . $e($this->saveUrl) . '" formmethod="post"' : '') . '>'
                  . '<span>' . $e(Lang::t('filterbuilder.save')) . '</span></button>'
                  . '</div>';
        }
        $out .= '</div></form>';
        return $out;
    }

    /* ── Inside: the state ───────────────────────────────────────────────── */

    /** The fields, operators and limits every reader works with. @return array<string,mixed> */
    private function spec(): array
    {
        $ops = [];
        foreach ($this->fields as $k => $f) {
            $ops[$k] = $f['operators'] ?? $this->typeOps[$f['type']] ?? self::DEFAULT_OPERATORS[$f['type']];
        }
        return ['fields' => $this->fields, 'ops' => $ops, 'maxDepth' => $this->maxDepth, 'maxRows' => $this->maxRows];
    }

    /** @param array<string,mixed>|self $fields @param array<string,mixed> $opts @return array<string,mixed> */
    private static function specOf(array|self $fields, array $opts): array
    {
        if ($fields instanceof self) {
            $spec = $fields->spec();
        } else {
            $list = [];
            foreach ($fields as $key => $def) {
                if (!is_array($def)) $def = ['label' => is_string($def) ? $def : (string) $key];
                $label = $def['label'] ?? (string) $key;
                $f = self::normField((string) $key, is_string($label) ? $label : (string) $key, $def, true);
                if ($f !== null) $list[$f['key']] = $f;
            }
            $spec = ['fields' => $list, 'ops' => [], 'maxDepth' => 3, 'maxRows' => 20];
            $typeOps = [];
            if (isset($opts['operators']) && is_array($opts['operators'])) {
                foreach ($opts['operators'] as $type => $l) {
                    if (is_string($type) && in_array($type, self::TYPES, true) && is_iterable($l)) {
                        $typeOps[$type] = self::opList($type, $l, "operators['$type']");
                    }
                }
            }
            foreach ($list as $k => $f) {
                $spec['ops'][$k] = $f['operators'] ?? (($typeOps[$f['type']] ?? []) ?: self::DEFAULT_OPERATORS[$f['type']]);
            }
        }
        foreach (['maxDepth' => self::DEPTH_CAP, 'maxRows' => self::ROWS_CAP] as $k => $cap) {
            if (isset($opts[$k])) {
                if (is_int($opts[$k]) && $opts[$k] >= 1 && $opts[$k] <= $cap) $spec[$k] = $opts[$k];
                else self::warn("the option '$k' is 1 to $cap; it stays {$spec[$k]}.");
            }
        }
        return $spec;
    }

    /** @param array<string,mixed> $opts @return array<string,mixed>|null */
    private static function normField(string $key, string $label, array $opts, bool $warn): ?array
    {
        if (!preg_match(self::KEY_RE, $key)) {
            if ($warn) self::warn("field('$key'): a key is letters, digits, _ . and -, starting with a letter; the field is left out.");
            return null;
        }
        $known = ['label', 'type', 'options', 'operators', 'placeholder'];
        foreach (array_keys($opts) as $o) {
            if (!in_array($o, $known, true) && $warn) self::warn("field('$key') does not know the option '$o'; it is ignored.");
        }
        $type = $opts['type'] ?? 'text';
        if (!is_string($type) || !in_array($type, self::TYPES, true)) {
            if ($warn) self::warn("field('$key'): the type " . var_export($type, true) . ' is not one of ' . implode(', ', self::TYPES) . '; it is text.');
            $type = 'text';
        }
        $options = [];
        if ($type === 'select' || $type === 'multiselect') {
            $raw = $opts['options'] ?? [];
            if (!is_iterable($raw)) {
                if ($warn) self::warn("field('$key'): 'options' is key => label; there are none.");
                $raw = [];
            }
            $seen = [];
            foreach ($raw as $k => $v) {
                if (is_array($v)) {
                    $rk = $v['value'] ?? $v['key'] ?? null;
                    $rl = $v['label'] ?? $rk;
                } else {
                    $rk = $k;
                    $rl = $v;
                }
                if (!is_int($rk) && !is_string($rk)) continue;
                $sk = (string) $rk;
                if (isset($seen[$sk])) continue;
                $seen[$sk] = true;
                $options[] = [$sk, is_scalar($rl) ? (string) $rl : $sk, $rk];
            }
            if ($options === [] && $warn) self::warn("field('$key') is a $type without options; nothing can be chosen.");
        } elseif (isset($opts['options']) && $warn) {
            self::warn("field('$key'): 'options' belong to select and multiselect; they are ignored.");
        }
        $ops = null;
        if (isset($opts['operators'])) {
            if (is_iterable($opts['operators'])) {
                $ops = self::opList($type, $opts['operators'], "field('$key')", $warn) ?: null;
            } elseif ($warn) {
                self::warn("field('$key'): 'operators' is a list; the type's are used.");
            }
        }
        $ph = $opts['placeholder'] ?? '';
        return ['key' => $key, 'label' => $label !== '' ? $label : $key, 'type' => $type, 'options' => $options,
            'operators' => $ops, 'placeholder' => is_string($ph) ? $ph : ''];
    }

    /** @param iterable<mixed> $ops @return list<string> */
    private static function opList(string $type, iterable $ops, string $where, bool $warn = true): array
    {
        $list = [];
        foreach ($ops as $op) {
            if (!is_string($op) || !in_array($op, self::OPERATORS[$type], true)) {
                if ($warn) self::warn("$where: " . var_export($op, true) . " is not an operator of $type; known: " . implode(', ', self::OPERATORS[$type]) . '.');
                continue;
            }
            if (!in_array($op, $list, true)) $list[] = $op;
        }
        if ($list === [] && $warn) self::warn("$where names no operator of $type; the defaults stay.");
        return $list;
    }

    /**
     * The state to draw: the request's (with its command applied), else value() — and whether an
     * incomplete condition says so (not after add, group, duplicate or remove: a new row is empty).
     *
     * @return array{0:array<string,mixed>,1:bool}
     */
    private function draft(): array
    {
        $spec = $this->spec();
        $raw = self::source($this->name, $this->request);
        if ($raw === null) {
            if ($this->value !== null && $this->value !== [] && $this->value !== '') {
                $checked = self::walk($this->value, $spec, false);
                $drafted = self::walk($this->value, $spec, true);
                if (self::count($checked) < self::count($drafted) || self::countIncomplete($drafted) > 0) {
                    self::warn('value() holds conditions that are not complete or not known; they are not applied.');
                }
                return [$drafted, true];
            }
            return [['join' => 'and', 'rules' => []], true];
        }
        $cmd = is_array($raw) && isset($raw['cmd']) && is_string($raw['cmd']) ? $raw['cmd'] : '';
        return [self::fromRaw($raw, $spec, $this->value), !preg_match('/^(add|group|remove|dup):/', $cmd)];
    }

    /** What the request holds under the name: POST when it carries it, else the query string. */
    private static function source(string $name, ?array $request): mixed
    {
        if ($request !== null) return $request[$name] ?? null;
        return $_POST[$name] ?? $_GET[$name] ?? null;
    }

    /** A request's value (JSON or the form's arrays), read as a draft, its command applied. @param array<string,mixed> $spec @return array<string,mixed> */
    private static function fromRaw(mixed $raw, array $spec, mixed $default): array
    {
        $cmd = null;
        if (is_string($raw)) {
            $raw = strlen($raw) > self::JSON_MAX ? null : json_decode($raw, true, 64);
        } elseif (is_array($raw)) {
            $cmd = isset($raw['cmd']) && is_string($raw['cmd']) ? $raw['cmd'] : null;
        } else {
            $raw = null;
        }
        if ($cmd === 'reset') return self::walk($default, $spec, true);
        $draft = self::walk($raw, $spec, true);
        if ($cmd !== null && preg_match('/^(add|group|remove|dup):((?:\d{1,3}(?:\.\d{1,3}){0,5})?)$/', $cmd, $m)) {
            $path = $m[2] === '' ? [] : array_map('intval', explode('.', $m[2]));
            $draft = self::command($draft, $m[1], $path, $spec);
        }
        return $draft;
    }

    /**
     * Reads any node shape into a group: checked (false — complete conditions only) or as a draft
     * (true — a condition with a known field is kept with what was typed and an 'error').
     *
     * @param array<string,mixed> $spec
     * @return array<string,mixed>
     */
    private static function walk(mixed $node, array $spec, bool $draft): array
    {
        if (is_string($node)) $node = strlen($node) > self::JSON_MAX ? null : json_decode($node, true, 64);
        $rows = 0;
        $visits = 0;
        $group = is_array($node) ? self::walkGroup($node, $spec, $draft, 1, $rows, $visits) : null;
        return $group ?? ['join' => 'and', 'rules' => []];
    }

    /** @param array<mixed> $node @param array<string,mixed> $spec @return array<string,mixed>|null */
    private static function walkGroup(array $node, array $spec, bool $draft, int $depth, int &$rows, int &$visits): ?array
    {
        if (++$visits > self::VISIT_MAX) return null;
        if (array_key_exists('rules', $node)) {
            $join = $node['join'] ?? 'and';
            $children = is_array($node['rules']) ? $node['rules'] : [];
        } elseif (array_is_list($node) && isset($node[0]) && is_string($node[0]) && in_array(strtolower($node[0]), ['and', 'or'], true)
            && (count($node) === 1 || is_array($node[1]))) {
            $join = $node[0];
            $children = array_slice($node, 1);
        } else {
            return null;
        }
        $join = is_string($join) && strtolower($join) === 'or' ? 'or' : 'and';
        $out = [];
        foreach ($children as $child) {
            if (!is_array($child) || $visits > self::VISIT_MAX) continue;
            if (self::isGroup($child)) {
                if ($depth >= $spec['maxDepth']) { $visits++; continue; }
                $g = self::walkGroup($child, $spec, $draft, $depth + 1, $rows, $visits);
                if ($g !== null && $g['rules'] !== []) $out[] = $g;
                continue;
            }
            if (++$visits > self::VISIT_MAX || $rows >= $spec['maxRows']) continue;
            $r = self::walkRule($child, $spec, $draft);
            if ($r !== null) {
                $out[] = $r;
                $rows++;
            }
        }
        return ['join' => $join, 'rules' => $out];
    }

    /** @param array<mixed> $node */
    private static function isGroup(array $node): bool
    {
        if (array_key_exists('rules', $node)) return true;
        return array_is_list($node) && isset($node[0]) && is_string($node[0]) && in_array(strtolower($node[0]), ['and', 'or'], true)
            && (count($node) === 1 || is_array($node[1]));
    }

    /** @param array<mixed> $node @param array<string,mixed> $spec @return array<string,mixed>|null */
    private static function walkRule(array $node, array $spec, bool $draft): ?array
    {
        if (array_key_exists('field', $node)) {
            [$field, $op, $value, $has] = [$node['field'], $node['op'] ?? null, $node['value'] ?? null, array_key_exists('value', $node)];
        } elseif (array_is_list($node) && count($node) >= 2 && count($node) <= 3) {
            [$field, $op, $value, $has] = [$node[0], $node[1], $node[2] ?? null, count($node) === 3];
        } else {
            return null;
        }
        if (!is_string($field) || !isset($spec['fields'][$field])) return null;
        $f = $spec['fields'][$field];
        $ops = $spec['ops'][$field];
        if (!is_string($op) || !in_array($op, $ops, true)) {
            if (!$draft) return null;
            // Kept for the form, which shows the field's first operator — incomplete, with the operator as it
            // came, so it is never applied unseen (the script reads the same state the same way).
            $kind = self::KIND[$ops[0]];
            return ['field' => $field, 'op' => is_string($op) ? self::text($op, 40) : ''] + ($kind === 'none' ? [] : ['value' => self::rawValue(null, $kind)])
                 + ['error' => $kind === 'none' ? 'op' : self::errorCode($f['type'], $kind)];
        }
        $kind = self::KIND[$op];
        if ($kind === 'none') return ['field' => $field, 'op' => $op];
        $parsed = $has ? self::parseValue($f, $kind, $value) : null;
        if ($parsed !== null) return ['field' => $field, 'op' => $op, 'value' => $parsed];
        if (!$draft) return null;
        return ['field' => $field, 'op' => $op, 'value' => self::rawValue($value, $kind), 'error' => self::errorCode($f['type'], $kind)];
    }

    /** A value as its type and operator need it, or null. @param array<string,mixed> $f */
    private static function parseValue(array $f, string $kind, mixed $value): mixed
    {
        if ($kind === 'list') {
            if (is_scalar($value)) $value = [$value];
            if (!is_array($value)) return null;
            $keys = array_column($f['options'], 0);
            $out = [];
            foreach (array_slice($value, 0, self::LIST_MAX) as $v) {
                if (!is_scalar($v) || is_bool($v)) continue;
                $v = self::str($v);
                if (in_array($v, $keys, true) && !in_array($v, $out, true)) $out[] = $v;
            }
            return $out === [] ? null : $out;
        }
        if ($kind === 'two') {
            if (!is_array($value) || count($value) !== 2) return null;
            $value = array_values($value);
            $a = self::parseOne($f, $value[0]);
            $b = self::parseOne($f, $value[1]);
            if ($a === null || $b === null) return null;
            return ($f['type'] === 'date' ? strcmp((string) $a, (string) $b) > 0 : $a > $b) ? [$b, $a] : [$a, $b];
        }
        return self::parseOne($f, $value);
    }

    /** @param array<string,mixed> $f */
    private static function parseOne(array $f, mixed $v): int|float|string|null
    {
        switch ($f['type']) {
            case 'number':
            case 'money':
                return self::number($v);
            case 'date':
                return DateRange::date(is_string($v) ? $v : null);
            case 'select':
            case 'multiselect':
                if (!is_scalar($v) || is_bool($v)) return null;
                $v = self::str($v);
                return in_array($v, array_column($f['options'], 0), true) ? $v : null;
            default:
                if (!is_string($v) && !is_int($v) && !is_float($v)) return null;
                $t = self::text(self::str($v), self::TEXT_MAX);
                return $t === '' ? null : $t;
        }
    }

    /** A number as typed ("19.99", "19,99", 100) as int or float; null when it is not one. */
    private static function number(mixed $v): int|float|null
    {
        if (is_int($v)) return abs($v) < 1e15 ? $v : null;
        if (is_float($v)) {
            if (!is_finite($v) || abs($v) >= 1e15) return null;
            $v = round($v, 6);
            return floor($v) === $v ? (int) $v : $v;
        }
        if (!is_string($v)) return null;
        $v = trim($v, ' ');
        if (!preg_match(self::NUMBER_RE, $v)) return null;
        $v = str_replace(',', '.', $v);
        if (!str_contains($v, '.')) return (int) $v;
        $n = (float) $v;
        return floor($n) === $n ? (int) $n : $n;
    }

    /** Text without control characters, trimmed of spaces, at most $max characters. */
    private static function text(string $v, int $max): string
    {
        // Broken UTF-8 becomes U+FFFD (no mbstring needed: it is optional, README).
        if (!preg_match('//u', $v)) $v = htmlspecialchars_decode(htmlspecialchars($v, ENT_SUBSTITUTE | ENT_NOQUOTES, 'UTF-8'), ENT_NOQUOTES);
        $v = (string) preg_replace('/[\x00-\x1F\x7F]/u', '', $v);
        $v = trim($v, ' ');
        return preg_match('/^.{0,' . $max . '}/su', $v, $m) ? $m[0] : '';
    }

    /** What was typed, kept for the form: text, or a list of texts. */
    private static function rawValue(mixed $value, string $kind): string|array
    {
        if ($kind === 'two' || $kind === 'list') {
            $out = [];
            foreach (is_array($value) ? array_slice(array_values($value), 0, $kind === 'two' ? 2 : self::LIST_MAX) : [] as $v) {
                $out[] = is_scalar($v) && !is_bool($v) ? self::text(self::str($v), self::TEXT_MAX) : '';
            }
            if ($kind === 'two') $out = array_pad($out, 2, '');
            return $kind === 'list' ? array_values(array_filter($out, static fn (string $s): bool => $s !== '')) : $out;
        }
        return is_scalar($value) && !is_bool($value) ? self::text(self::str($value), self::TEXT_MAX) : '';
    }

    /** A scalar as text; a float without exponent (js/filter-builder.js writes the same digits). */
    private static function str(string|int|float $v): string
    {
        if (is_float($v)) return is_finite($v) ? self::plain($v) : '';
        return (string) $v;
    }

    private static function errorCode(string $type, string $kind): string
    {
        if ($kind === 'list') return 'list';
        if ($kind === 'two') return 'between';
        return match ($type) {
            'number', 'money' => 'number',
            'date' => 'date',
            'select', 'multiselect' => 'select',
            default => 'text',
        };
    }

    /** Nothing typed: '', [] or ['', '']. */
    private static function blank(mixed $v): bool
    {
        if (is_array($v)) return array_filter($v, static fn ($x): bool => $x !== '') === [];
        return $v === '' || $v === null;
    }

    /** Conditions in a group and its groups. @param array<string,mixed> $g */
    private static function count(array $g): int
    {
        $n = 0;
        foreach ($g['rules'] as $r) $n += isset($r['rules']) ? self::count($r) : 1;
        return $n;
    }

    /** @param array<string,mixed> $g */
    private static function countIncomplete(array $g): int
    {
        $n = 0;
        foreach ($g['rules'] as $r) $n += isset($r['rules']) ? self::countIncomplete($r) : (isset($r['error']) ? 1 : 0);
        return $n;
    }

    private static function incompleteText(int $n): string
    {
        return $n === 1 ? Lang::t('filterbuilder.incomplete_one') : Lang::t('filterbuilder.incomplete', ['n' => $n]);
    }

    /**
     * A command of the form without script: add a condition or a group to the group at $path,
     * remove or duplicate the node at $path. Limits hold.
     *
     * @param array<string,mixed> $g @param list<int> $path @param array<string,mixed> $spec
     * @return array<string,mixed>
     */
    private static function command(array $g, string $cmd, array $path, array $spec): array
    {
        $total = self::count($g);
        $first = array_key_first($spec['fields']);
        if ($first === null) return $g;
        $newRule = static function () use ($spec, $first): array {
            $op = $spec['ops'][$first][0];
            $kind = self::KIND[$op];
            $r = ['field' => $first, 'op' => $op];
            if ($kind !== 'none') {
                $r['value'] = $kind === 'two' ? ['', ''] : ($kind === 'list' ? [] : '');
                $r['error'] = self::errorCode($spec['fields'][$first]['type'], $kind);
            }
            return $r;
        };
        if ($cmd === 'add' || $cmd === 'group') {
            $target = &self::nodeAt($g, $path);
            if ($target === null || !isset($target['rules']) || $total >= $spec['maxRows']) return $g;
            if ($cmd === 'add') {
                $target['rules'][] = $newRule();
            } elseif (count($path) + 1 < $spec['maxDepth']) {
                $target['rules'][] = ['join' => $target['join'] === 'and' ? 'or' : 'and', 'rules' => [$newRule()]];
            }
            return $g;
        }
        if ($path === []) return $g;
        $index = array_pop($path);
        $parent = &self::nodeAt($g, $path);
        if ($parent === null || !isset($parent['rules'][$index])) return $g;
        if ($cmd === 'dup') {
            $copy = $parent['rules'][$index];
            if ($total + (isset($copy['rules']) ? self::count($copy) : 1) > $spec['maxRows']) return $g;
            array_splice($parent['rules'], $index + 1, 0, [$copy]);
            return $g;
        }
        array_splice($parent['rules'], $index, 1);
        unset($parent);
        // A group left empty goes with its last condition.
        while ($path !== []) {
            $up = &self::nodeAt($g, $path);
            if ($up === null || $up['rules'] !== []) break;
            unset($up);
            $i = array_pop($path);
            $holder = &self::nodeAt($g, $path);
            array_splice($holder['rules'], $i, 1);
            unset($holder);
        }
        return $g;
    }

    /** @param array<string,mixed> $g @param list<int> $path */
    private static function &nodeAt(array &$g, array $path): ?array
    {
        $null = null;
        $node = &$g;
        foreach ($path as $i) {
            if (!isset($node['rules'][$i]) || !is_array($node['rules'][$i])) return $null;
            $node = &$node['rules'][$i];
        }
        return $node;
    }

    /** The compact shape. With $draft, what was typed and is not complete yet stays in. @param array<string,mixed> $g @return list<mixed> */
    private static function compact(array $g, bool $draft = false): array
    {
        $out = [$g['join'] ?? 'and'];
        foreach ($g['rules'] ?? [] as $r) {
            if (!is_array($r)) continue;
            if (isset($r['rules'])) {
                $out[] = self::compact($r, $draft);
                continue;
            }
            if (!$draft && isset($r['error'])) continue;
            $out[] = array_key_exists('value', $r) ? [$r['field'], $r['op'], $r['value']] : [$r['field'], $r['op']];
        }
        return $out;
    }

    /* ── Inside: SQL and rows ────────────────────────────────────────────── */

    /** @param array<string,mixed> $g @param array<string,mixed> $spec @param array<string,mixed> $map @param list<mixed> $params */
    private static function sqlGroup(array $g, array $spec, array $map, array &$params, bool $root = false): string
    {
        $parts = [];
        foreach ($g['rules'] as $r) {
            $s = isset($r['rules']) ? self::sqlGroup($r, $spec, $map, $params) : self::sqlRule($r, $spec, $map, $params);
            if ($s !== '') $parts[] = $s;
        }
        if ($parts === []) return '';
        if (count($parts) === 1) return $parts[0];
        return '(' . implode($g['join'] === 'or' ? ' OR ' : ' AND ', $parts) . ')';
    }

    /** @param array<string,mixed> $r @param array<string,mixed> $spec @param array<string,mixed> $map @param list<mixed> $params */
    private static function sqlRule(array $r, array $spec, array $map, array &$params): string
    {
        $f = $spec['fields'][$r['field']];
        $col = $map[$r['field']] ?? $r['field'];
        if ($col instanceof \Closure || (is_array($col) && is_callable($col))) {
            try {
                $res = $col($r, ['key' => $f['key'], 'label' => $f['label'], 'type' => $f['type']]);
            } catch (\Throwable $ex) {
                self::warn("toSql(): the callable of '{$r['field']}' threw (" . $ex->getMessage() . '); the condition matches nothing.');
                return '1=0';
            }
            $sql = is_array($res) ? ($res['sql'] ?? null) : null;
            $p = is_array($res) ? ($res['params'] ?? []) : null;
            if (!is_string($sql) || $sql === '' || !is_array($p) || substr_count($sql, '?') !== count($p)
                || array_filter($p, static fn ($v): bool => !is_int($v) && !is_float($v) && !is_string($v) && $v !== null) !== []) {
                self::warn("toSql(): the callable of '{$r['field']}' returns ['sql' => …, 'params' => […]] with one param per ?; the condition matches nothing.");
                return '1=0';
            }
            foreach ($p as $v) $params[] = $v;
            return '(' . $sql . ')';
        }
        if (!is_string($col) || !preg_match('/^' . self::IDENT . '(?:\.' . self::IDENT . ')?$/', $col)) {
            self::warn("toSql(): the column of '{$r['field']}' is not an identifier; the condition matches nothing.");
            return '1=0';
        }
        if ($f['type'] === 'multiselect') {
            self::warn("toSql(): '{$r['field']}' is a multiselect; give it a callable in the column map. The condition matches nothing.");
            return '1=0';
        }
        $op = $r['op'];
        $v = $r['value'] ?? null;
        $add = static function (mixed ...$vals) use (&$params): void { foreach ($vals as $x) $params[] = $x; };
        $like = static fn (string $s): string => strtr($s, ['!' => '!!', '%' => '!%', '_' => '!_']);
        $L = " LIKE ? ESCAPE '!'";

        if ($f['type'] === 'text') {
            switch ($op) {
                case 'contains': $add('%' . $like($v) . '%'); return $col . $L;
                case 'not_contains': $add('%' . $like($v) . '%'); return "($col IS NULL OR $col NOT" . $L . ')';
                case 'equals': $add($like($v)); return $col . $L;
                case 'not_equals': $add($like($v)); return "($col IS NULL OR $col NOT" . $L . ')';
                case 'starts': $add($like($v) . '%'); return $col . $L;
                case 'ends': $add('%' . $like($v)); return $col . $L;
            }
        }
        if ($op === 'empty') return in_array($f['type'], ['text', 'select'], true) ? "($col IS NULL OR $col = '')" : "$col IS NULL";
        if ($op === 'not_empty') return in_array($f['type'], ['text', 'select'], true) ? "($col IS NOT NULL AND $col <> '')" : "$col IS NOT NULL";
        if ($f['type'] === 'bool') {
            $add(1);
            return $op === 'true' ? "$col = ?" : "($col IS NULL OR $col <> ?)";
        }
        if ($f['type'] === 'select') {
            $raw = static function (string $k) use ($f): int|string {
                foreach ($f['options'] as $o) if ($o[0] === $k) return $o[2];
                return $k;
            };
            switch ($op) {
                case 'is': $add($raw($v)); return "$col = ?";
                case 'is_not': $add($raw($v)); return "($col IS NULL OR $col <> ?)";
                case 'any_of':
                case 'none_of':
                    $add(...array_map($raw, $v));
                    $in = 'IN (' . implode(', ', array_fill(0, count($v), '?')) . ')';
                    return $op === 'any_of' ? "$col $in" : "($col IS NULL OR $col NOT $in)";
            }
        }
        if ($f['type'] === 'date') {
            // A day is [day, next day): the column may hold a time of day.
            $next = static fn (string $d): ?string => $d === '9999-12-31' ? null
                : (new \DateTimeImmutable($d . ' 12:00:00', new \DateTimeZone('UTC')))->modify('+1 day')->format('Y-m-d');
            $before = static function (?string $d) use ($col, $add): string {     // col < d, or every date when d is past the end
                if ($d === null) return "$col IS NOT NULL";
                $add($d);
                return "$col < ?";
            };
            switch ($op) {
                case 'eq': $add($v); $b = $before($next($v)); return "($col >= ? AND $b)";
                case 'ne':
                    $add($v);
                    $n = $next($v);
                    if ($n === null) return "($col IS NULL OR $col < ?)";
                    $add($n);
                    return "($col IS NULL OR $col < ? OR $col >= ?)";
                case 'lt': return $before($v);
                case 'lte': return $before($next($v));
                case 'gt':
                    $n = $next($v);
                    if ($n === null) return '1=0';
                    $add($n);
                    return "$col >= ?";
                case 'gte': $add($v); return "$col >= ?";
                case 'between': $add($v[0]); $b = $before($next($v[1])); return "($col >= ? AND $b)";
            }
        }
        // number, money
        switch ($op) {
            case 'eq': $add($v); return "$col = ?";
            case 'ne': $add($v); return "($col IS NULL OR $col <> ?)";
            case 'lt': $add($v); return "$col < ?";
            case 'lte': $add($v); return "$col <= ?";
            case 'gt': $add($v); return "$col > ?";
            case 'gte': $add($v); return "$col >= ?";
            case 'between': $add($v[0], $v[1]); return "$col BETWEEN ? AND ?";
        }
        return '1=0';
    }

    /** @param array<string,mixed> $g @param array<string,mixed> $spec */
    private static function matchGroup(array $g, array|object $row, array $spec): bool
    {
        if ($g['rules'] === []) return true;
        $or = $g['join'] === 'or';
        foreach ($g['rules'] as $r) {
            $hit = isset($r['rules']) ? self::matchGroup($r, $row, $spec) : self::matchRule($r, $row, $spec);
            if ($or && $hit) return true;
            if (!$or && !$hit) return false;
        }
        return !$or;
    }

    /** @param array<string,mixed> $r @param array<string,mixed> $spec */
    private static function matchRule(array $r, array|object $row, array $spec): bool
    {
        $f = $spec['fields'][$r['field']];
        $key = $r['field'];
        if (is_array($row)) $x = $row[$key] ?? null;
        elseif ($row instanceof \ArrayAccess) $x = $row[$key] ?? null;
        else $x = $row->$key ?? null;
        $op = $r['op'];
        $v = $r['value'] ?? null;
        $lower = static fn (string $s): string => function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);

        switch ($f['type']) {
            case 'text':
                $s = is_scalar($x) && !is_bool($x) ? (string) $x : ($x === true ? '1' : null);
                if ($op === 'empty') return $s === null || $s === '';
                if ($op === 'not_empty') return $s !== null && $s !== '';
                if ($s === null) return in_array($op, ['not_contains', 'not_equals'], true);
                [$hay, $needle] = [$lower($s), $lower((string) $v)];
                return match ($op) {
                    'contains' => str_contains($hay, $needle),
                    'not_contains' => !str_contains($hay, $needle),
                    'equals' => $hay === $needle,
                    'not_equals' => $hay !== $needle,
                    'starts' => str_starts_with($hay, $needle),
                    'ends' => str_ends_with($hay, $needle),
                    default => false,
                };
            case 'number':
            case 'money':
                $n = is_int($x) || is_float($x) ? $x : (is_string($x) && is_numeric(trim($x)) ? (float) trim($x) : null);
                if ($op === 'empty') return $x === null;
                if ($op === 'not_empty') return $x !== null;
                if ($n === null) return $op === 'ne';
                return match ($op) {
                    'eq' => $n == $v, 'ne' => $n != $v, 'lt' => $n < $v, 'lte' => $n <= $v,
                    'gt' => $n > $v, 'gte' => $n >= $v, 'between' => $n >= $v[0] && $n <= $v[1], default => false,
                };
            case 'date':
                $d = $x instanceof \DateTimeInterface ? $x->format('Y-m-d') : (is_string($x) ? DateRange::date(substr($x, 0, 10)) : null);
                if ($op === 'empty') return $x === null;
                if ($op === 'not_empty') return $x !== null;
                if ($d === null) return $op === 'ne';
                return match ($op) {
                    'eq' => $d === $v, 'ne' => $d !== $v, 'lt' => $d < $v, 'lte' => $d <= $v,
                    'gt' => $d > $v, 'gte' => $d >= $v, 'between' => $d >= $v[0] && $d <= $v[1], default => false,
                };
            case 'bool':
                $on = $x === true || $x === 1 || $x === '1' || $x === 1.0;
                return $op === 'true' ? $on : !$on;
            case 'select':
                $s = is_scalar($x) && !is_bool($x) ? (string) $x : null;
                if ($op === 'empty') return $s === null || $s === '';
                if ($op === 'not_empty') return $s !== null && $s !== '';
                return match ($op) {
                    'is' => $s === $v, 'is_not' => $s !== $v,
                    'any_of' => $s !== null && in_array($s, $v, true),
                    'none_of' => $s === null || !in_array($s, $v, true), default => false,
                };
            case 'multiselect':
                $set = is_array($x) ? $x : (is_string($x) && $x !== '' ? explode(',', $x) : []);
                $set = array_values(array_unique(array_map(static fn ($s): string => trim((string) $s), array_filter($set, 'is_scalar'))));
                $set = array_values(array_filter($set, static fn (string $s): bool => $s !== ''));
                if ($op === 'empty') return $set === [];
                if ($op === 'not_empty') return $set !== [];
                $common = array_intersect($v, $set);
                return match ($op) {
                    'has_any' => $common !== [], 'has_all' => count($common) === count($v), 'has_none' => $common === [], default => false,
                };
        }
        return false;
    }

    /* ── Inside: words ───────────────────────────────────────────────────── */

    private static function opLabel(string $type, string $op): string
    {
        $key = $type === 'date' && in_array($op, ['eq', 'ne', 'lt', 'lte', 'gt', 'gte'], true) ? 'date_' . $op : $op;
        return Lang::t('filterbuilder.op_' . $key);
    }

    /** @param array<string,mixed> $g @param array<string,mixed> $spec */
    private static function words(array $g, array $spec, bool $root = false): string
    {
        $parts = [];
        foreach ($g['rules'] as $r) {
            $parts[] = isset($r['rules']) ? '(' . self::words($r, $spec) . ')' : self::ruleWords($r, $spec);
        }
        return implode(' ' . Lang::t($g['join'] === 'or' ? 'filterbuilder.or' : 'filterbuilder.and') . ' ', $parts);
    }

    /** @param array<string,mixed> $r @param array<string,mixed> $spec */
    private static function ruleWords(array $r, array $spec): string
    {
        $f = $spec['fields'][$r['field']];
        $text = $f['label'] . ' ' . self::opLabel($f['type'], $r['op']);
        if (!array_key_exists('value', $r)) return $text;
        $one = static function (mixed $v) use ($f): string {
            switch ($f['type']) {
                case 'text': return Lang::t('filterbuilder.quote', ['v' => (string) $v]);
                case 'date': return DateRange::text((string) $v, (string) $v);
                case 'number': return self::formatNumber($v, false);
                case 'money': return self::formatNumber($v, true);
                default:
                    foreach ($f['options'] as $o) if ($o[0] === (string) $v) return $o[1];
                    return (string) $v;
            }
        };
        $v = $r['value'];
        if ($r['op'] === 'between') return $text . ' ' . Lang::t('filterbuilder.range', ['a' => $one($v[0]), 'b' => $one($v[1])]);
        if (is_array($v)) return $text . ' ' . implode(', ', array_map($one, $v));
        return $text . ' ' . $one($v);
    }

    /** 1234.5 → "1,234.5" (or "1.234,50" for money in German): the separators of the page's language. */
    private static function formatNumber(mixed $v, bool $money): string
    {
        $dec = Lang::t('format.decimal');
        $thou = Lang::t('format.thousands');
        $neg = $v < 0;
        [$int, $frac] = array_pad(explode('.', self::plain(abs($v)), 2), 2, '');
        if ($money && strlen($frac) < 2) $frac = str_pad($frac, 2, '0');   // never rounded: 19.999 stays
        $int = (string) preg_replace('/\B(?=(\d{3})+(?!\d))/', $thou, $int);
        return ($neg ? '-' : '') . $int . ($frac !== '' ? $dec . $frac : '');
    }

    /** A number without exponent and without trailing zeros: 19.9, 100, 0.000001. */
    private static function plain(int|float $v): string
    {
        if (is_int($v)) return (string) $v;
        $s = rtrim(rtrim(sprintf('%.6F', $v), '0'), '.');
        return $s === '-0' ? '0' : $s;
    }

    /** The words the script needs, from the catalogue. @return array<string,mixed> */
    private static function jsWords(string $label): array
    {
        $keys = ['match', 'join_and', 'join_or', 'and', 'or', 'group', 'field', 'operator', 'value', 'value_from', 'value_to',
            'between_and', 'add', 'add_label', 'add_group', 'add_group_label', 'remove', 'duplicate', 'remove_group',
            'duplicate_group', 'the_filter', 'summary_none', 'quote', 'range', 'max_rows', 'incomplete', 'incomplete_one',
            'err_op', 'err_text', 'err_number', 'err_date', 'err_between', 'err_select', 'err_list', 'views', 'view_remove', 'save',
            'view_saved', 'view_name_missing', 'empty', 'choose', 'saved_here'];
        $w = [];
        foreach ($keys as $k) $w[$k] = Lang::t('filterbuilder.' . $k);
        $w['label'] = $label;
        $w['decimal'] = Lang::t('format.decimal');
        $w['thousands'] = Lang::t('format.thousands');
        $w['months'] = explode('|', Lang::t('date.months_short'));
        $w['day'] = Lang::t('daterange.text_day');
        return $w;
    }

    /* ── Inside: markup (js/filter-builder.js writes the same) ───────────── */

    /** @param list<int> $path */
    private static function num(array $path): string
    {
        return implode('.', array_map(static fn (int $i): int => $i + 1, $path));
    }

    /**
     * One group: a fieldset named by its legend, the join, the conditions, the add buttons.
     *
     * @param array<string,mixed> $g @param list<int> $path @param array<string,mixed> $spec
     */
    private static function groupHtml(array $g, array $path, int $depth, array $spec, string $prefix, string $base, int $total, string $label): string
    {
        $e = self::esc(...);
        $name = $path === [] ? $label : Lang::t('filterbuilder.group', ['n' => self::num($path)]);
        $gid = $base . '-g' . ($path === [] ? '' : implode('-', $path));
        $out = '<fieldset class="gk-fb-group" id="' . $e($gid) . '" data-gk-fb-path="' . $e(implode('.', $path)) . '">'
             . '<legend class="gk-fb-legend' . ($path === [] ? ' gk-sr-only' : '') . '">' . $e($name) . '</legend>'
             . '<div class="gk-fb-group-head">'
             . '<label class="gk-fb-join-label" for="' . $e($gid) . '-join">' . $e(Lang::t('filterbuilder.match')) . '</label>'
             . '<select class="gk-filter gk-fb-join" id="' . $e($gid) . '-join" name="' . $e($prefix) . '[join]">'
             . '<option value="and"' . ($g['join'] === 'and' ? ' selected' : '') . '>' . $e(Lang::t('filterbuilder.join_and')) . '</option>'
             . '<option value="or"' . ($g['join'] === 'or' ? ' selected' : '') . '>' . $e(Lang::t('filterbuilder.join_or')) . '</option>'
             . '</select>';
        if ($path !== []) {
            $n = self::num($path);
            $out .= '<span class="gk-fb-actions">'
                  . self::iconButton('dup', $prefix, implode('.', $path), Lang::t('filterbuilder.duplicate_group', ['n' => $n]), self::ICON_COPY, $total >= $spec['maxRows'])
                  . self::iconButton('remove', $prefix, implode('.', $path), Lang::t('filterbuilder.remove_group', ['n' => $n]), self::ICON_REMOVE, false)
                  . '</span>';
        }
        $out .= '</div>';
        if ($g['rules'] === []) {
            $out .= '<p class="gk-fb-empty">' . $e(Lang::t('filterbuilder.empty')) . '</p>';
        } else {
            $out .= '<ol class="gk-fb-rules">';
            foreach ($g['rules'] as $i => $r) {
                $p = [...$path, $i];
                $conj = $i === 0 ? '' : '<span class="gk-fb-conj" aria-hidden="true">' . $e(Lang::t($g['join'] === 'or' ? 'filterbuilder.or' : 'filterbuilder.and')) . '</span>';
                if (isset($r['rules'])) {
                    $out .= '<li class="gk-fb-row gk-fb-row-group">' . $conj
                          . self::groupHtml($r, $p, $depth + 1, $spec, $prefix . '[rules][' . $i . ']', $base, $total, $label) . '</li>';
                } else {
                    $out .= self::ruleHtml($r, $p, $spec, $prefix . '[rules][' . $i . ']', $base, $total, $conj);
                }
            }
            $out .= '</ol>';
        }
        $where = $path === [] ? Lang::t('filterbuilder.the_filter') : $name;
        $full = $total >= $spec['maxRows'];
        $out .= '<div class="gk-fb-add">'
              . '<button type="submit" class="gk-btn gk-btn-text gk-btn-primary gk-btn-sm gk-fb-add-rule" name="' . $e(self::cmdName($prefix)) . '" value="add:' . $e(implode('.', $path)) . '"'
              . ' aria-label="' . $e(Lang::t('filterbuilder.add_label', ['group' => $where])) . '"' . ($full ? ' disabled' : '') . '>'
              . self::ICON_ADD . '<span>' . $e(Lang::t('filterbuilder.add')) . '</span></button>';
        if ($depth < $spec['maxDepth']) {
            $out .= '<button type="submit" class="gk-btn gk-btn-text gk-btn-primary gk-btn-sm gk-fb-add-group" name="' . $e(self::cmdName($prefix)) . '" value="group:' . $e(implode('.', $path)) . '"'
                  . ' aria-label="' . $e(Lang::t('filterbuilder.add_group_label', ['group' => $where])) . '"' . ($full ? ' disabled' : '') . '>'
                  . self::ICON_ADD . '<span>' . $e(Lang::t('filterbuilder.add_group')) . '</span></button>';
        }
        if ($full && $path === []) {
            $out .= '<span class="gk-fb-limit">' . $e(Lang::t('filterbuilder.max_rows', ['n' => $spec['maxRows']])) . '</span>';
        }
        $out .= '</div></fieldset>';
        return $out;
    }

    /** The name of the command button: the builder's name, not the group's. */
    private static function cmdName(string $prefix): string
    {
        $at = strpos($prefix, '[');
        return ($at === false ? $prefix : substr($prefix, 0, $at)) . '[cmd]';
    }

    private static function iconButton(string $cmd, string $prefix, string $path, string $label, string $icon, bool $disabled): string
    {
        $e = self::esc(...);
        return '<button type="submit" class="gk-fb-btn gk-fb-' . $cmd . '" name="' . $e(self::cmdName($prefix)) . '" value="' . $cmd . ':' . $e($path) . '"'
             . ' aria-label="' . $e($label) . '" title="' . $e($label) . '"' . ($disabled ? ' disabled' : '') . '>' . $icon . '</button>';
    }

    /**
     * One condition: field, operator, value(s), duplicate and remove, and what is wrong with it.
     *
     * @param array<string,mixed> $r @param list<int> $path @param array<string,mixed> $spec
     */
    private static function ruleHtml(array $r, array $path, array $spec, string $prefix, string $base, int $total, string $conj): string
    {
        $e = self::esc(...);
        $f = $spec['fields'][$r['field']];
        $n = self::num($path);
        $id = $base . '-c' . implode('-', $path);
        $show = isset($r['error']) && (!empty($spec['loud']) || !self::blank($r['value'] ?? ''));
        $err = $show ? $id . '-err' : '';
        $state = $err !== '' ? ' aria-invalid="true" aria-describedby="' . $e($err) . '"' : '';
        $out = '<li class="gk-fb-row' . ($err !== '' ? ' gk-fb-invalid' : '') . '" id="' . $e($id) . '" data-gk-fb-path="' . $e(implode('.', $path)) . '">' . $conj
             . '<span class="gk-fb-cells">';
        $out .= '<select class="gk-filter gk-fb-field" id="' . $e($id) . '-field" name="' . $e($prefix) . '[field]" aria-label="' . $e(Lang::t('filterbuilder.field', ['n' => $n])) . '">';
        foreach ($spec['fields'] as $k => $ff) {
            $out .= '<option value="' . $e($k) . '"' . ($k === $r['field'] ? ' selected' : '') . '>' . $e($ff['label']) . '</option>';
        }
        $out .= '</select>';
        $out .= '<select class="gk-filter gk-fb-op" id="' . $e($id) . '-op" name="' . $e($prefix) . '[op]" aria-label="' . $e(Lang::t('filterbuilder.operator', ['n' => $n])) . '">';
        $ops = $spec['ops'][$r['field']];
        $shown = in_array($r['op'], $ops, true) ? $r['op'] : $ops[0];   // an operator the field lacks: its first
        foreach ($ops as $op) {
            $out .= '<option value="' . $e($op) . '"' . ($op === $shown ? ' selected' : '') . '>' . $e(self::opLabel($f['type'], $op)) . '</option>';
        }
        $out .= '</select>';
        $kind = self::KIND[$shown];
        if ($kind !== 'none') {
            $out .= '<span class="gk-fb-value">';
            $v = $r['value'] ?? null;
            if ($kind === 'list') {
                $chosen = array_map('strval', is_array($v) ? $v : []);
                $out .= '<select class="gk-filter gk-fb-input gk-fb-multi" id="' . $e($id) . '-value" name="' . $e($prefix) . '[value][]" multiple size="' . min(4, max(2, count($f['options']))) . '"'
                      . ' aria-label="' . $e(Lang::t('filterbuilder.value', ['n' => $n])) . '"' . $state . '>';
                foreach ($f['options'] as $o) {
                    $out .= '<option value="' . $e($o[0]) . '"' . (in_array($o[0], $chosen, true) ? ' selected' : '') . '>' . $e($o[1]) . '</option>';
                }
                $out .= '</select>';
            } elseif ($kind === 'two') {
                $pair = is_array($v) ? array_values($v) : ['', ''];
                $out .= self::input($f, $id . '-value', $prefix . '[value][0]', $pair[0] ?? '', Lang::t('filterbuilder.value_from', ['n' => $n]), $state)
                      . '<span class="gk-fb-between-and" aria-hidden="true">' . $e(Lang::t('filterbuilder.between_and')) . '</span>'
                      . self::input($f, $id . '-value2', $prefix . '[value][1]', $pair[1] ?? '', Lang::t('filterbuilder.value_to', ['n' => $n]), $state);
            } else {
                $out .= self::input($f, $id . '-value', $prefix . '[value]', $v ?? '', Lang::t('filterbuilder.value', ['n' => $n]), $state);
            }
            $out .= '</span>';
        }
        $out .= '</span><span class="gk-fb-actions">'
              . self::iconButton('dup', $prefix, implode('.', $path), Lang::t('filterbuilder.duplicate', ['n' => $n]), self::ICON_COPY, $total >= $spec['maxRows'])
              . self::iconButton('remove', $prefix, implode('.', $path), Lang::t('filterbuilder.remove', ['n' => $n]), self::ICON_REMOVE, false)
              . '</span>';
        if ($err !== '') {
            $out .= '<p class="gk-fb-error" id="' . $e($err) . '">' . self::ICON_ERROR . '<span>' . $e(Lang::t('filterbuilder.err_' . $r['error'])) . '</span></p>';
        }
        return $out . '</li>';
    }

    /** One value field, as its type asks: a select, a date, a number or text. @param array<string,mixed> $f */
    private static function input(array $f, string $id, string $name, mixed $value, string $label, string $state): string
    {
        $e = self::esc(...);
        $value = is_scalar($value) && !is_bool($value) ? (is_float($value) ? self::plain($value) : (string) $value) : '';
        if ($f['type'] === 'select' || $f['type'] === 'multiselect') {
            $out = '<select class="gk-filter gk-fb-input" id="' . $e($id) . '" name="' . $e($name) . '" aria-label="' . $e($label) . '"' . $state . '>'
                 . '<option value=""' . ($value === '' ? ' selected' : '') . '>' . $e(Lang::t('filterbuilder.choose')) . '</option>';
            foreach ($f['options'] as $o) {
                $out .= '<option value="' . $e($o[0]) . '"' . ($o[0] === $value ? ' selected' : '') . '>' . $e($o[1]) . '</option>';
            }
            return $out . '</select>';
        }
        $type = $f['type'] === 'date' ? 'date' : 'text';
        $mode = in_array($f['type'], ['number', 'money'], true) ? ' inputmode="decimal"' : '';
        $ph = $f['placeholder'] !== '' ? ' placeholder="' . $e($f['placeholder']) . '"' : '';
        return '<input type="' . $type . '" class="gk-filter gk-fb-input" id="' . $e($id) . '" name="' . $e($name) . '" value="' . $e($value) . '"'
             . $mode . $ph . ' aria-label="' . $e($label) . '"' . $state . ($type === 'text' ? ' maxlength="' . self::TEXT_MAX . '"' : '') . '>';
    }

    /** @param array<string,mixed> $tree @param array<string,mixed> $spec */
    private function viewsHtml(array $tree, array $spec): string
    {
        if ($this->views === [] && !$this->localViews) return '';
        $e = self::esc(...);
        $current = self::encode($tree);
        $out = '<nav class="gk-fb-views" aria-labelledby="gk-fb-' . $e($this->name) . '-views"' . ($this->views === [] ? ' hidden' : '') . '>'
             . '<span class="gk-fb-views-label" id="gk-fb-' . $e($this->name) . '-views">' . $e(Lang::t('filterbuilder.views')) . '</span>'
             . '<ul class="gk-fb-view-list">';
        foreach ($this->views as $v) {
            $state = self::walk($v['state'], $spec, false);
            $json = self::encode($state);
            $href = $v['href'] !== '' ? $v['href'] : $this->link($json);
            $on = $json === $current;
            $out .= '<li><a class="gk-fb-view" href="' . $e($href) . '"' . ($on ? ' aria-current="true"' : '') . '>'
                  . $e($v['label']) . '</a></li>';
        }
        return $out . '</ul></nav>';
    }

    /** A link to the page with this state (and the preserved parameters) — what a view is. */
    private function link(string $json): string
    {
        $parts = parse_url($this->action === '' ? '' : $this->action);
        $path = is_array($parts) ? ($parts['path'] ?? '') : '';
        $frag = is_array($parts) && isset($parts['fragment']) ? '#' . $parts['fragment'] : '';
        if (is_array($parts) && isset($parts['scheme'], $parts['host'])) {
            $path = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '') . $path;
        }
        $query = $this->preserved();
        $query[$this->name] = $json;
        return $path . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) . $frag;
    }

    /** The hidden fields of the form. @return array<string,string> */
    private function preserved(): array
    {
        $out = [];
        foreach ($this->preserve as $k => $v) {
            if (is_int($k)) {
                if (!is_string($v) || $v === $this->name || $v === $this->name . '_view') continue;
                $got = $this->request !== null ? ($this->request[$v] ?? null) : ($_GET[$v] ?? null);
                if (is_scalar($got) && (string) $got !== '') $out[$v] = (string) $got;
            } elseif ($k !== $this->name && is_scalar($v)) {
                $out[$k] = (string) $v;
            }
        }
        return $out;
    }

    private static function unsafeUrl(string $url): bool
    {
        $u = strtolower((string) preg_replace('/[\x00-\x20\x7F]+/', '', $url));
        return (bool) preg_match('/^(javascript|data|vbscript):/', $u);
    }

    private static function esc(mixed $s): string
    {
        return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function warn(string $message): void
    {
        trigger_error("GridKit: FilterBuilder: $message", E_USER_WARNING);
    }
}
