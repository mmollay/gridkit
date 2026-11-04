<?php
declare(strict_types=1);

namespace GridKit;

use GridKit\Button;

class Table
{
    /** Formats that write a link of their own — no row control may wrap them. */
    private const ROW_LINK_REFUSED = ['html', 'email'];

    /** The highest page a request may ask for. A page past the end shows the empty state anyway. */
    private const MAX_PAGE = 1000000;

    /** What a column's 'edit' may ask for (1.110.0). */
    private const EDIT_TYPES = ['text', 'number', 'select'];

    private string $id;
    private array $columns = [];
    private array $buttons = [];
    private array $modals = [];
    private array $rows = [];
    private array $searchCols = [];
    private array $filters = [];
    private string $toolbarHtml = '';
    private ?string $newBtnLabel = null;
    private array $newBtnOpts = [];
    private int $perPage = 0;
    private int $currentPage = 1;
    private int $totalRows = 0;
    private string $sortCol = '';
    private string $sortDir = 'asc';
    private string $searchQuery = '';
    /** Empty state: ['title' => …, 'hint' => …, 'icon' => …, 'action' => html] */
    private array $emptyState = [];
    private ?\mysqli $db = null;
    private string $baseQuery = '';
    private bool $isStatic = false;
    private bool $globalNowrap = false;
    private bool $showToolbar = true;
    private string $size = 'md';
    private string $variant = 'default';
    /** 'card' | 'scroll'; null until mobile() is called — the default depends on size(). */
    private ?string $mobileMode = null;
    private bool $selectable = false;
    private string $selectKey = 'id';
    private ?int $loadTimeMs = null;
    private array $footerCells = [];
    /** @var array<string,array<string,mixed>> */
    private array $bulkActions = [];
    private ?string $csvFile = null;
    /** @var array<string,mixed> */
    private array $csvOpts = [];
    /** densityToggle() (1.109.0): Compact / Normal / Roomy in the toolbar. */
    private bool $densityToggle = false;
    private string $groupCol = '';
    private string $caption = '';
    private array $groupLabels = [];
    /** What a row opens: ['href' => …] or ['sheet' => …, 'url' => …], plus 'column' and 'params'. */
    private array $rowLink = [];
    /** stickyHead() (1.107.0): the header row stays in view; $stickyHeight is its box, '' for the page. */
    private bool $stickyHead = false;
    private string $stickyHeight = '';
    /** stickyColumn() (1.107.0): the first data column, and the cells before it, stay in view sideways. */
    private bool $stickyColumn = false;

    /** columnToggle() (1.108.0): a toolbar button whose menu shows and hides columns. */
    private bool $columnToggle = false;
    /** editable(): the row field that names a record; null while the table is not editable. */
    private ?string $editKey = null;
    private string $editUrl = '';
    /** @var array{headers?:array<string,string>} */
    private array $editOpts = [];
    /**
     * The columns that can be edited in the render under way, worked out once per render():
     * key => ['type' => …, 'options' => [[value, text], …]]. Empty for every table without editable().
     * @var array<string,array<string,mixed>>
     */
    private array $editSpecs = [];
    /**
     * The tooltip markup of a column ('c:key', its help icon) or a row button ('b:name', its
     * attributes), worked out once: a row button renders in every row, and a bad option would
     * otherwise warn once per row and again for the data block (1.112.0).
     * @var array<string,string>
     */
    private array $tooltipMarkup = [];

    public function __construct(string $id)
    {
        $this->id = $id;
        $this->sortCol = self::param('gk_sort');
        $this->sortDir = ($_GET['gk_dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        // Capped: "?gk_page=99999999999999999999" is PHP_INT_MAX, and the offset worked out from it
        // overflowed to a float (a TypeError in array_slice) while the pager's loop, which counts from
        // the page to the page + 2, never ended — one hand-typed link held a PHP worker.
        $this->currentPage = min(self::MAX_PAGE, max(1, (int)($_GET['gk_page'] ?? 1)));
        $this->searchQuery = trim(self::param('gk_search'));
    }

    /**
     * A GET parameter as text — and nothing else.
     *
     * ?gk_sort[]=x arrives as an array. Assigned to a string property it threw
     * a TypeError, so any page with a table could be turned into a server error
     * by one hand-typed link; cast with (string) it became the word "Array" and
     * was bound into the query as a filter value.
     */
    private static function param(string $name): string
    {
        $value = $_GET[$name] ?? '';
        return is_string($value) ? $value : '';
    }

    /**
     * The class that puts a header where its cells are: an explicit 'align' wins,
     * a number or currency column is right-aligned without one.
     * js/gridkit.js carries the same rule as thAlignClass() — keep them in step.
     */
    private static function headerAlignClass(array $col): string
    {
        $align = (string) ($col['align'] ?? '');
        if ($align === 'right')  return 'gk-text-right';
        if ($align === 'center') return 'gk-text-center';
        if ($align !== '')       return '';
        return in_array($col['format'] ?? '', ['currency', 'number'], true) ? 'gk-td-num' : '';
    }

    public function query(\mysqli $db, string $sql): static
    {
        $this->db = $db;
        $this->baseQuery = $sql;
        return $this;
    }

    public function search(array $columns): static
    {
        $this->searchCols = $columns;
        return $this;
    }

    /**
     * Every column a search should look at: the ones named, plus the second-line
     * field of each of them. Without it a row could not be found by the text
     * standing right there under its name.
     *
     * Only for the client-side search of a setData() table, where a missing field
     * is simply never matched. The SQL path does NOT use this — see buildWhere().
     *
     * @param  list<string> $cols
     * @return list<string>
     */
    private function withSubColumns(array $cols): array
    {
        foreach ($this->columns as $key => $col) {
            if (isset($col['sub']) && in_array($key, $cols, true) && !in_array($col['sub'], $cols, true)) {
                $cols[] = $col['sub'];
            }
        }

        return $cols;
    }

    public function searchable(bool $enabled): static
    {
        if (!$enabled) $this->searchCols = [];
        return $this;
    }

    public function column(string $key, string $label, array $opts = []): static
    {
        // 'priority' (1.92.0): 2 to 5, how early the column gives way in a list
        // that is narrow (size('list')); 1 is "never", as is no priority. Stored
        // as an int or not at all, so the client rebuild reads the same number
        // the server did — a '3.0' or a 7 would otherwise mean one thing in PHP
        // and another in JavaScript.
        if (array_key_exists('priority', $opts)) {
            $raw = $opts['priority'];
            $p = is_scalar($raw) ? filter_var($raw, FILTER_VALIDATE_INT) : false;
            unset($opts['priority']);
            if ($p !== false && $p >= 2 && $p <= 5) {
                $opts['priority'] = $p;
            } elseif ($p !== 1) {
                $shown = is_scalar($raw) ? var_export($raw, true) : get_debug_type($raw);
                trigger_error("GridKit: column '$key' has 'priority' => $shown — it takes 2 to 5"
                    . " (5 gives way first) or 1 for never. The column always shows.", E_USER_WARNING);
            }
        }
        // 'hidden' and 'toggle' (1.108.0, see columnToggle()) take true or false and nothing else:
        // a 'hidden' => 'no' is true to PHP and would hide the column it meant to show. Anything
        // else is dropped with a warning, and the column keeps the default (shown, can be hidden).
        foreach (['hidden' => 'starts shown', 'toggle' => 'can be hidden'] as $flag => $fallback) {
            if (array_key_exists($flag, $opts) && !is_bool($opts[$flag])) {
                $raw = $opts[$flag];
                $shown = is_scalar($raw) ? var_export($raw, true) : get_debug_type($raw);
                trigger_error("GridKit: column '$key' has '$flag' => $shown — it takes true or false. The column $fallback.", E_USER_WARNING);
                unset($opts[$flag]);
            }
        }
        $this->columns[$key] = ['label' => $label, ...$opts];
        return $this;
    }

    public function button(string $name, array $opts = []): static
    {
        $this->buttons[$name] = $opts;
        return $this;
    }

    public function modal(string $id, string $title, string $url, array $opts = []): static
    {
        $this->modals[$id] = ['title' => $title, 'url' => $url, ...$opts];
        return $this;
    }

    public function newButton(string $label, array $opts = []): static
    {
        $this->newBtnLabel = $label;
        $this->newBtnOpts = $opts;
        return $this;
    }

    public function nowrap(bool $enabled = true): static
    {
        $this->globalNowrap = $enabled;
        return $this;
    }

    public function toolbarHtml(string $html): static
    {
        $this->toolbarHtml = $html;
        return $this;
    }

    public function toolbar(bool $show = true): static
    {
        $this->showToolbar = $show;
        return $this;
    }

    public function paginate(int|bool $perPage): static
    {
        $this->perPage = (int)$perPage;
        return $this;
    }

    public function size(string $size): static
    {
        $this->size = $size;
        return $this;
    }

    public function variant(string $variant): static
    {
        $this->variant = $variant;
        return $this;
    }

    public function loadTime(int $ms): static
    {
        $this->loadTimeMs = $ms;
        return $this;
    }

    /**
     * Sets the footer cells for the table.
     * Each cell is a string or ['text' => '...', 'align' => 'right', 'colspan' => 2, 'bold' => true]
     */
    public function footer(array $cells): static
    {
        $this->footerCells = $cells;
        return $this;
    }

    public function mobile(string $mode): static
    {
        $this->mobileMode = $mode;
        return $this;
    }

    /**
     * The header row stays in view while the rows scroll under it (1.107.0).
     *
     * Without an argument it sticks to the page, or to whatever scrolls around the table: a
     * table writes no scroll box of its own and .gk-table-wrap keeps overflow visible, so the
     * page is the nearest scroller. Under a fixed or sticky GridKit header it stops at
     * --gk-sheet-top (56px), not under the header. With a CSS length ('70vh', '480px') the table
     * scrolls in a box of that height and the header sticks to the top of the box.
     *
     * The length goes into a style attribute, so anything that is not a plain number and unit
     * is refused with a warning — the head then sticks to the page as without an argument.
     */
    public function stickyHead(string $maxHeight = ''): static
    {
        $this->stickyHead = true;
        $maxHeight = trim($maxHeight);
        if ($maxHeight !== '' && !self::isBoxHeight($maxHeight)) {
            // JSON with every special character hex-escaped: the refused value is quoted in the
            // log and on a page with display_errors, and neither may get markup from it.
            $shown = json_encode($maxHeight, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
                | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
            trigger_error("GridKit: stickyHead() takes a CSS length such as '70vh' or '480px' — $shown was left out,"
                . ' the head sticks to the page.', E_USER_WARNING);
            $maxHeight = '';
        }
        $this->stickyHeight = $maxHeight;
        return $this;
    }

    /**
     * The first data column stays in view while the table scrolls sideways (1.107.0), and so
     * do the cells in front of it: the checkbox of selectable() and the buttons on the left.
     *
     * Scrolling sideways needs a box that scrolls, so the table gets one (overflow-x: auto).
     * A box that scrolls sideways is a scroller for the head as well — with stickyHead() the
     * head can no longer stick to the page, so the box gets a height: the one stickyHead()
     * was given, or 70vh.
     */
    public function stickyColumn(): static
    {
        $this->stickyColumn = true;
        return $this;
    }

    /** A length a box may be given as its height: a positive number and a unit, nothing else. */
    private static function isBoxHeight(string $value): bool
    {
        return preg_match('/^(\d+(?:\.\d+)?|\.\d+)(px|vh|dvh|svh|lvh|rem|em|%)$/', $value, $m) === 1
            && (float) $m[1] > 0;
    }

    public function selectable(string $key = 'id'): static
    {
        $this->selectable = true;
        $this->selectKey  = $key;
        return $this;
    }

    /**
     * Inline editing (1.110.0): a cell of a column with `'edit'` turns into a field on a click, or on
     * Enter or F2 while it has the focus — every such cell is reachable with Tab and tells a screen
     * reader that it can be edited. Enter and leaving the field save, Escape restores the old value,
     * Tab saves and moves to the next editable cell.
     *
     * Saving fires `gk:celledit` on the table with `{ tableId, id, column, value, oldValue }`. It is
     * cancelable: preventDefault() puts the old value back and sends nothing. With `$url` GridKit then
     * POSTs `{ id, column, value }` as JSON there and expects `{ ok: true }` back (optionally with the
     * stored `value` and the cell's `display` markup); anything else puts the old value back and says
     * why. Without `$url` nothing is sent — the application saves in its listener, and may answer
     * through `event.detail.respondWith(promise)` in the shape the server would.
     *
     * `$key` is the row field that names a record, as in selectable(): it is written into every row as
     * `data-gk-row-id` and posted as `id`. A row whose key is empty is not editable. Option `headers`
     * adds request headers, typically `['X-CSRF-Token' => $token]`.
     *
     * A table without a column that can be edited renders exactly as it would without this call.
     *
     * @param array{headers?:array<string,string>} $opts
     */
    public function editable(string $key = 'id', string $url = '', array $opts = []): static
    {
        $this->editKey  = $key;
        $this->editUrl  = $url;
        $this->editOpts = $opts;
        return $this;
    }

    /**
     * A button in the bulk bar, the bar that appears while rows are ticked (1.105.0).
     *
     * Clicking it fires `gk:bulkaction` on the table with `{ action, ids, tableId }` — the shape
     * `gk:bulkdelete` has — and the application decides what it does. Calling it turns selectable()
     * on (with the key 'id') when it is not on yet.
     *
     * Options: `label` (default: the name, or the catalogue's `action.<name>`), `icon`,
     * `confirm` (`true` for the standard question, or the sentence to ask) and `color` (`'danger'`
     * marks the confirmation as one).
     *
     * @param array{label?:string,icon?:string,confirm?:bool|string,color?:string} $opts
     */
    public function bulkAction(string $name, array $opts = []): static
    {
        if (!$this->selectable) $this->selectable();
        $this->bulkActions[$name] = $opts;
        return $this;
    }

    /**
     * A button in the toolbar that downloads the table as CSV (1.105.0).
     *
     * A `setData()` table builds the file in the browser from the rows it already holds — after the
     * search and the filters, in the order they are sorted in, every column except those with
     * `'export' => false`. A `rows()` or `query()` table has no rows to hand: the button reloads
     * the page with `?gk_export=csv` added and the application answers it with Table::sendCsv()
     * (see Table::wantsCsv()). Option `delimiter` is `','` or `';'` (German Excel), `label` the button text.
     *
     * @param array{delimiter?:string,label?:string} $opts
     */
    public function exportCsv(string $filename = 'export.csv', array $opts = []): static
    {
        $this->csvFile = self::csvFilename($filename);
        $this->csvOpts = $opts;
        return $this;
    }

    /** Did the request come from the export button of a server-driven table (`?gk_export=csv`)? */
    public static function wantsCsv(): bool
    {
        return ($_GET['gk_export'] ?? '') === 'csv';
    }

    /**
     * Rows as CSV text: RFC 4180 (CRLF, quotes where needed), UTF-8 with a byte-order mark so Excel
     * reads the encoding, and a cell that would be read as a formula (`=`, `+`, `-`, `@`, a tab or a
     * carriage return first, and not a plain number) gets a leading apostrophe.
     *
     * `$columns` maps a row key to its heading, or to `['label' => …, 'export' => false]` to leave it out.
     * Options: `delimiter` (default `','`), `bom` (default `true`).
     *
     * @param list<array<string,mixed>>                        $rows
     * @param array<string,string|array{label?:string,export?:bool}> $columns
     * @param array{delimiter?:string,bom?:bool}               $opts
     */
    public static function csv(array $rows, array $columns, array $opts = []): string
    {
        $delimiter = (string) ($opts['delimiter'] ?? ',');
        if ($delimiter === '' || strlen($delimiter) !== 1) $delimiter = ',';
        $keys = [];
        $head = [];
        foreach ($columns as $key => $col) {
            if (is_array($col) && ($col['export'] ?? true) === false) continue;
            $keys[] = (string) $key;
            $head[] = self::csvCell(is_array($col) ? ($col['label'] ?? $key) : $col, $delimiter);
        }
        $lines = [implode($delimiter, $head)];
        foreach ($rows as $row) {
            $cells = [];
            foreach ($keys as $key) $cells[] = self::csvCell($row[$key] ?? '', $delimiter);
            $lines[] = implode($delimiter, $cells);
        }
        return (($opts['bom'] ?? true) ? "\xEF\xBB\xBF" : '') . implode("\r\n", $lines) . "\r\n";
    }

    /**
     * Answer the export button of a server-driven table: the headers, then the file. Ends nothing —
     * `exit` after it, like after an AJAX fragment (see isAjaxReload()).
     *
     * The button of a table with columnToggle() names the columns the user has hidden
     * (`gk_hidden=email,phone`, 1.108.0); they are left out, so the file holds what the table
     * showed — as a setData() table's file does. A name that is not in `$columns` changes nothing.
     *
     * @param list<array<string,mixed>> $rows
     * @param array<string,string|array{label?:string,export?:bool}> $columns
     * @param array{delimiter?:string,bom?:bool} $opts
     */
    public static function sendCsv(array $rows, array $columns, string $filename = 'export.csv', array $opts = []): void
    {
        $hidden = self::param('gk_hidden');
        if ($hidden !== '') {
            foreach (explode(',', $hidden) as $key) unset($columns[$key]);
        }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . self::csvFilename($filename) . '"');
        header('X-Content-Type-Options: nosniff');
        echo self::csv($rows, $columns, $opts);
    }

    /** A file name that cannot break the header it is written into. */
    private static function csvFilename(string $name): string
    {
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', $name) ?? '';
        $name = trim($name, '.-');
        return $name === '' ? 'export.csv' : $name;
    }

    private static function csvCell(mixed $value, string $delimiter): string
    {
        $s = match (true) {
            $value === null => '',
            is_bool($value) => $value ? '1' : '0',
            is_scalar($value) => (string) $value,
            default => (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        };
        if ($s !== '' && preg_match('/^[=+\-@\t\r]/', $s) === 1 && preg_match('/^[+-]?\d+(?:[.,]\d+)?$/', $s) !== 1) {
            $s = "'" . $s;
        }
        if (preg_match('/[' . preg_quote($delimiter, '/') . '"\r\n]/', $s) === 1) {
            $s = '"' . str_replace('"', '""', $s) . '"';
        }
        return $s;
    }

    /**
     * A control in the toolbar that sets this table Compact, Normal or Roomy (1.109.0).
     *
     * The three steps are the rhythms of Theme::density(): `konsole`, the default and `weit`. The
     * script writes the choice as `data-gk-density` on the table's wrap, so it reaches this table
     * and no other, and remembers it per table id in this browser (localStorage, in the namespace
     * of GK.theme). It needs css/gridkit.css only; themes.css, which holds the page-wide rules,
     * is optional.
     *
     * Normal is GridKit's default rhythm, not "whatever the page is": on a page set to
     * Theme::density('konsole') the control starts at Compact and Normal is roomier than the
     * rest of the page. Choosing the page's own step forgets the stored choice, so the table
     * follows the page again. The control is rendered hidden and shown by the script: without
     * it the table is the table without this option. With toolbar(false) there is no control.
     */
    public function densityToggle(): static
    {
        $this->densityToggle = true;
        return $this;
    }

    public function setData(array $rows): static
    {
        $this->rows = $rows;
        $this->totalRows = count($rows);
        $this->isStatic = true;
        return $this;
    }

    /**
     * Rows the application has already fetched, plus the total before paging.
     *
     * `query()` speaks mysqli and nothing else. Every other source — PDO,
     * SQLite, Postgres, an HTTP API, a plain array — comes in here: run your
     * own query, hand over one page of rows and how many rows there are in
     * total.
     *
     * Unlike `setData()` the table stays server-driven. Search, sort, filter
     * and paging go back to the server as `gk_search`, `gk_sort` / `gk_dir`,
     * `gk_filter_<column>` and `gk_page`, which is what you want as soon as the
     * list outgrows what a browser should hold. Read them where you build the
     * query, and end the request with the fragment — see `isAjaxReload()`.
     *
     *     $result = $repo->page($_GET);
     *     $table  = (new Table('invoices'))
     *         ->rows($result['rows'], $result['total'])
     *         ->paginate(25);
     *
     * @param list<array<string,mixed>> $rows  One page, already filtered and sorted.
     * @param int                       $total All matching rows, before LIMIT.
     */
    public function rows(array $rows, int $total): static
    {
        $this->rows      = array_values($rows);
        $this->totalRows = max(0, $total);
        $this->isStatic  = false;
        return $this;
    }

    public function filter(string $column, string $type, array $opts = []): static
    {
        $this->filters[$column] = ['type' => $type, ...$opts];
        return $this;
    }

    /**
     * What this is a table of — for screen readers, not shown on screen.
     *
     * Without it two tables on one page are indistinguishable: a reader hears
     * "table, 4 columns" twice. The visible heading above a table is not
     * associated with it; a caption is.
     */
    public function caption(string $text): static
    {
        $this->caption = trim($text);
        return $this;
    }

    /**
     * A "Columns" button in the toolbar (1.108.0). Its menu has one checkbox per column; unticking
     * one hides the column — header, cells, and a footer cell that stands alone over it — and the
     * browser remembers the choice per table id.
     *
     * Column options: `'hidden' => true` starts the column hidden (a stored choice wins), and
     * `'toggle' => false` keeps it on screen — it stays in the menu, ticked and disabled, so the
     * list still reads as the table's columns. The column that carries rowLink()'s control is
     * kept the same way: hidden, the row would lose the one control the keyboard reaches.
     *
     * Hiding is CSS, keyed on the checkbox: the rows are written as before, so a sort, a search or
     * an AJAX reload redraws them and the hidden column stays hidden. The menu is a native popover,
     * so it opens and works without js/gridkit.js 1.108.0; the script adds the memory, aria-expanded,
     * the place under the button, and narrower group rows, empty state and footer.
     */
    public function columnToggle(bool $enabled = true): static
    {
        $this->columnToggle = $enabled;
        return $this;
    }

    /**
     * What the menu of columnToggle() lists: one entry per column, with the place its cells take in
     * a row (the checkbox and the left buttons stand before the first column), the place of its
     * header, and the footer cell that stands alone over it, if one does. null when the table has
     * no menu — and then a column marked 'hidden' is said to show, since nobody could show it again.
     *
     * The header's place is the place of its cells. Until 1.110.1 renderTableHead() — and
     * renderStatic() after it — wrote the header cell of the left buttons at the END of the header
     * row, so with left buttons a heading stood one place before its cells and had a place of its
     * own (data-gk-col-head). The attribute stays, now with the same number.
     *
     * 'hidden' is read only where 'toggle' is true: a column that cannot be hidden is ticked anyway.
     *
     * @return list<array{key:string,label:string,pos:int,head:int,foot:?int,toggle:bool,hidden:bool}>|null
     */
    private function columnMenu(): ?array
    {
        if (!$this->columnToggle || !$this->showToolbar) {
            foreach ($this->columns as $key => $col) {
                if (($col['hidden'] ?? false) === true) {
                    trigger_error("GridKit: column '$key' is 'hidden', but the table has no "
                        . ($this->columnToggle ? 'toolbar for its columnToggle() menu' : 'columnToggle()')
                        . ' — nobody could show it again. The column shows.', E_USER_WARNING);
                }
            }
            if ($this->columnToggle) {
                trigger_error('GridKit: columnToggle() puts its button in the toolbar, and toolbar(false) leaves none. Every column shows.', E_USER_WARNING);
            }
            return null;
        }

        $hasLeft = (bool) array_filter($this->buttons, fn($b) => ($b['position'] ?? 'right') === 'left');
        $hasRight = (bool) array_filter($this->buttons, fn($b) => ($b['position'] ?? 'right') === 'right');
        $lead = ($this->selectable ? 1 : 0) + ($hasLeft ? 1 : 0);
        $foot = $this->footerCellAt($lead + count($this->columns) + ($hasRight ? 1 : 0));
        $linkKey = $this->rowLinkColumn();

        $menu = [];
        $pos = $lead;
        foreach ($this->columns as $key => $col) {
            $pos++;
            $key = (string) $key;
            $toggle = ($col['toggle'] ?? true) !== false && $key !== $linkKey;
            $hidden = ($col['hidden'] ?? false) === true;
            if ($hidden && !$toggle) {
                trigger_error("GridKit: column '$key' is 'hidden' and cannot be hidden ("
                    . ($key === $linkKey ? "it carries the row's rowLink() control" : "'toggle' => false")
                    . '). The column shows.', E_USER_WARNING);
            }
            $label = (string) $col['label'];
            $menu[] = [
                'key' => $key,
                // A checkbox needs a name; a column with an empty heading gets its key.
                'label' => trim($label) !== '' ? $label : $key,
                'pos' => $pos,
                // Since 1.110.1 the header writes the cell over the left buttons where a row
                // writes the buttons, so a heading takes the place of its cells.
                'head' => $pos,
                'foot' => $foot[$pos] ?? null,
                'toggle' => $toggle,
                'hidden' => $hidden,
            ];
        }
        return $menu;
    }

    /**
     * Which footer cell stands alone over which column: place in the row => place in the footer
     * row, for the cells renderTableFoot() writes with a colspan of 1. A cell spanning more than
     * one column has no single column to go with; the script narrows it instead.
     *
     * @return array<int,int>
     */
    private function footerCellAt(int $colCount): array
    {
        if (!$this->footerCells && $this->loadTimeMs === null) return [];
        $spans = [];
        foreach ($this->footerCells as $cell) {
            $spans[] = is_array($cell) ? (int) ($cell['colspan'] ?? 1) : 1;
        }
        // The cell renderTableFoot() pads the row with: the load time, or an empty one.
        $rest = $colCount - array_sum($spans);
        if ($rest > 0) $spans[] = $rest;

        $at = [];
        $pos = 1;
        foreach ($spans as $i => $span) {
            $span = max(1, $span);   // a browser reads colspan="0" as 1
            if ($span === 1) $at[$pos] = $i + 1;
            $pos += $span;
        }
        return $at;
    }

    /** The id of the column menu: the table id where it is a safe one, made safe (and kept unique) where not. */
    private function columnMenuId(): string
    {
        $safe = preg_replace('/[^A-Za-z0-9_-]/', '-', $this->id) ?? '';
        return 'gk-cols-' . $safe . ($safe === $this->id ? '' : '-' . substr(md5($this->id), 0, 6));
    }

    /**
     * The button and its menu. The button names the menu twice: popovertarget opens it without a
     * line of script, aria-controls says what it opens. aria-expanded is left to the script, which
     * keeps it in step — written here once, it would say "false" while the menu stood open on a page
     * without the script (the browser reports a popover's state for its button by itself).
     *
     * @param list<array{key:string,label:string,pos:int,head:int,foot:?int,toggle:bool,hidden:bool}> $menu
     */
    private function renderColumnToggle(array $menu, \Closure $e): string
    {
        $label = Lang::t('table.columns');
        $id = $e($this->columnMenuId());
        $html = '<div class="gk-col-toggle">'
            . '<button type="button" class="gk-btn gk-btn-outlined gk-btn-neutral gk-btn-sm gk-btn-pill"'
            . ' popovertarget="' . $id . '" aria-controls="' . $id . '">'
            . '<span class="material-icons" style="font-size:16px" aria-hidden="true">view_column</span>'
            . '<span>' . $e($label) . '</span></button>'
            . '<div class="gk-col-toggle-menu" id="' . $id . '" popover role="group" aria-label="' . $e($label) . '">';
        foreach ($menu as $box) {
            $attrs = ' data-gk-col="' . $e($box['key']) . '"';
            if ($box['toggle']) {
                // The checkbox IS the state: the stylesheet hides the cells at data-gk-col-pos, the
                // header at data-gk-col-head and the footer cell at data-gk-col-foot while it is unticked.
                $attrs .= ' data-gk-col-pos="' . $box['pos'] . '" data-gk-col-head="' . $box['head'] . '"'
                    . ($box['foot'] !== null ? ' data-gk-col-foot="' . $box['foot'] . '"' : '')
                    . ($box['hidden'] ? '' : ' checked');
            } else {
                $attrs .= ' checked disabled';
            }
            $html .= '<label class="gk-dropdown-item"><input type="checkbox"' . $attrs . '>'
                . '<span>' . $e($box['label']) . '</span></label>';
        }
        return $html . '</div></div>';
    }

    /**
     * Insert a group row as soon as $column changes.
     * The rows have to arrive sorted by that column — otherwise the
     * heading repeats on every change.
     *
     * @param array<string,string> $labels  raw value → display name
     */
    public function groupBy(string $column, array $labels = []): static
    {
        $this->groupCol = $column;
        $this->groupLabels = $labels;
        return $this;
    }

    /**
     * The whole row opens something: a page, or a side sheet.
     *
     *     ->rowLink('/users/{id}')                              // a page
     *     ->rowLink(['sheet' => 'user-sheet'])                  // a .gk-sheet on this page
     *     ->rowLink(['sheet' => 'user-sheet', 'url' => 'panels/user.php'])   // … filled from a URL
     *
     * Reading belongs in the row, changing in what the row opens. Two admin
     * lists that had nowhere else to put it carried a select, a switch and a
     * label saying the same thing in every cell.
     *
     * The row is not turned into a control — role=link or a tabindex on a
     * <tr> takes away its row role. Its main cell (the first column, or
     * 'column' => key) carries one real control, <a class="gk-row-target"> or
     * <button class="gk-row-target" data-gk-sheet>, and gridkit.js forwards a
     * click anywhere else in the row to it. The keyboard and a screen reader
     * use the control; without JavaScript a link still works. 'href' follows
     * the rules of a row button's href; 'params' maps like a row button's and
     * always carries the row's id — it reaches gk:sheetopen and the POST to
     * 'url'. A row whose main cell shows nothing, or whose target is not
     * allowed, stays a plain row: a link without a name is a dead focus stop.
     *
     * @param string|array{href?:string,sheet?:string,url?:string,column?:string,params?:array<string,string>} $target
     */
    public function rowLink(string|array $target): static
    {
        $this->rowLink = is_string($target) ? ['href' => $target] : $target;
        return $this;
    }

    /** The column whose cell carries the row's control, or null when there is none to be had. */
    private function rowLinkColumn(): ?string
    {
        if (!$this->rowLink || !$this->columns) return null;
        $key = (string) ($this->rowLink['column'] ?? array_key_first($this->columns));
        return isset($this->columns[$key]) ? $key : null;
    }

    /**
     * The one control a row carries, as its opening and closing tag — or null
     * when this row cannot have one. js/gridkit.js builds the same pair in
     * renderStatic(); keep them in step.
     *
     * @return array{0:string,1:string}|null
     */
    private function rowTarget(array $row, \Closure $e): ?array
    {
        $key = $this->rowLinkColumn();
        if ($key === null) return null;
        $col = $this->columns[$key];
        // 'html' is markup the caller writes, links included, and 'email' writes
        // a mailto link: wrapping either nests one control inside another.
        // Said once in renderInner().
        if (in_array($col['format'] ?? '', self::ROW_LINK_REFUSED, true)) return null;
        // What the cell SHOWS names the control — the rule a linked cell follows.
        $shown = trim(html_entity_decode(strip_tags($this->format($row[$key] ?? '', $col)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($shown === '' || $shown === '—') return null;

        $sheet = (string) ($this->rowLink['sheet'] ?? '');
        if ($sheet !== '') {
            $params = self::rowParams($this->rowLink['params'] ?? [], $row);
            $url = (string) ($this->rowLink['url'] ?? '');
            return [
                '<button type="button" class="gk-row-target"'
                . ' data-gk-sheet="' . $e($sheet) . '"'
                . ($url !== '' ? ' data-gk-sheet-url="' . $e($url) . '"' : '')
                . ' data-gk-sheet-title="' . $e($shown) . '"'
                . " data-gk-params='" . $e(json_encode($params)) . "'"
                . ' aria-haspopup="dialog">',
                '</button>',
            ];
        }

        $href = (string) ($this->rowLink['href'] ?? '');
        $ziel = $href === '' ? null : self::fillTarget($href, $row);
        return $ziel === null ? null : ['<a class="gk-row-target" href="' . $e($ziel) . '">', '</a>'];
    }

    /**
     * What a row control carries in data-gk-params: 'params' mapped from the
     * row (key => field), plus the row's own id unless the map names one —
     * forgetting it opened an edit modal as if for a new record. Row buttons
     * and row links share it; js/gridkit.js carries it as _gkRowParams().
     *
     * @param  array<string,string> $map
     * @return array<string,mixed>
     */
    private static function rowParams(array $map, array $row): array
    {
        $params = [];
        foreach ($map as $pkey => $pcol) {
            $params[$pkey] = $row[$pcol] ?? '';
        }
        if (!array_key_exists('id', $params) && isset($row['id'])) {
            $params['id'] = $row['id'];
        }
        return $params;
    }

    /**
     * A target template filled from the row — every {field} encoded the way
     * rawurlencode() does it, so a value can never become a scheme — and then
     * held against the allow list (safeTarget). null when GridKit will not link
     * there. Row buttons, linked cells and row links share it;
     * js/gridkit.js carries it as _gkFillTarget().
     */
    private static function fillTarget(string $template, array $row): ?string
    {
        return self::safeTarget((string) preg_replace_callback(
            '/\{(\w+)\}/',
            static fn (array $m): string => rawurlencode((string) ($row[$m[1]] ?? '')),
            $template
        ));
    }

    /**
     * The columns a person may edit, worked out once per render (1.110.0).
     *
     * A column that asks for something GridKit will not do stays read-only and says why, out
     * loud: 'html' and 'email' write markup of their own (a link inside the field's cell would
     * take the click), a cell with 'href' is a link, and the cell of rowLink() carries the row's
     * control — a click there must open the row, not a field. A select needs something to choose
     * from. 'edit' => false (or null, or '') is simply "not editable", so a permission can switch
     * a column off without a warning.
     *
     * @return array<string,array<string,mixed>>
     */
    private function resolveEditColumns(): array
    {
        $specs = [];
        $linkKey = $this->rowLinkColumn();
        foreach ($this->columns as $key => $col) {
            $type = $col['edit'] ?? null;
            if ($type === null || $type === false || $type === '') continue;
            $k = (string) $key;
            $refuse = static fn (string $why) => trigger_error("GridKit: column '$k' $why The column stays read-only.", E_USER_WARNING);
            if ($this->editKey === null) {
                $refuse("has 'edit', but the table is not editable() — there is no key to save a change under.");
                continue;
            }
            if (!is_string($type) || !in_array($type, self::EDIT_TYPES, true)) {
                $refuse("has 'edit' => " . (is_scalar($type) ? var_export($type, true) : get_debug_type($type))
                    . " — it takes 'text', 'number' or 'select'.");
                continue;
            }
            $format = $col['format'] ?? '';
            if (is_string($format) && in_array($format, self::ROW_LINK_REFUSED, true)) {
                $refuse("cannot be edited: 'format' => '$format' writes markup of its own, and a click on a link in it cannot also open a field.");
                continue;
            }
            if (isset($col['href']) && (string) $col['href'] !== '') {
                $refuse("cannot be edited: its 'href' makes the cell a link, and a click cannot both follow it and open a field.");
                continue;
            }
            if ($k === $linkKey) {
                $refuse("cannot be edited: it carries the control of rowLink(), and a click on it opens the row.");
                continue;
            }
            $spec = ['type' => $type];
            if ($type === 'select') {
                $spec['options'] = self::editOptions($col);
                if ($spec['options'] === []) {
                    $refuse("has 'edit' => 'select' and nothing to choose from — give it 'options' (value => text), or make it a 'label' column with 'labels'.");
                    continue;
                }
            }
            $specs[$k] = $spec;
        }
        if ($specs && $this->selectable && $this->editKey !== $this->selectKey) {
            trigger_error("GridKit: selectable('{$this->selectKey}') and editable('{$this->editKey}') name different keys; a row has one id,"
                . " so both use '{$this->selectKey}'.", E_USER_WARNING);
        }
        return $specs;
    }

    /**
     * What a select offers: the column's 'options' (value => text, like a filter's), else the
     * 'labels' of a label column (each key a value, its 'text' or the key itself as the text).
     * Pairs, not an object: JavaScript reorders the numeric-looking keys of an object.
     *
     * @return list<array{0:string,1:string}>
     */
    private static function editOptions(array $col): array
    {
        $out = [];
        if (isset($col['options']) && is_array($col['options'])) {
            foreach ($col['options'] as $value => $text) {
                $out[] = [(string) $value, is_scalar($text) ? (string) $text : (string) $value];
            }
            return $out;
        }
        if (($col['format'] ?? '') === 'label' && isset($col['labels']) && is_array($col['labels'])) {
            foreach ($col['labels'] as $value => $entry) {
                $text = is_array($entry) && isset($entry['text']) && is_scalar($entry['text']) ? (string) $entry['text'] : (string) $value;
                $out[] = [(string) $value, $text];
            }
        }
        return $out;
    }

    /** The row field `data-gk-row-id` is written from: the selection's key, else editable()'s, else none. */
    private function rowIdKey(): ?string
    {
        if ($this->selectable) return $this->selectKey;
        return $this->editSpecs !== [] ? $this->editKey : null;
    }

    /** The id of the hidden sentence every editable cell points at with aria-describedby. */
    private function editHintId(): string
    {
        return 'gk-edit-hint-' . $this->id;
    }

    /**
     * A value as the text PHP makes of it, the way the client's twin does (asText() in the 1.110.0
     * block): true is "1", false and null are "", and what has no text of its own (an array) is "" too.
     */
    private static function rawText(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * What the browser needs to edit this table, on the wrap as data-gk-editable. A server table
     * (rows(), query()) has no column config in the browser, so its editable columns travel here
     * too — a saved value is then shown the way the column formats it, by the same code that
     * redraws a sorted setData() table — and the label colours when one of them is a label column.
     *
     * @return array<string,mixed>
     */
    private function editConfig(): array
    {
        $config = [
            'url'     => $this->editUrl,
            'key'     => $this->rowIdKey(),
            'hint'    => $this->editHintId(),
            'columns' => $this->editSpecs,
        ];
        $headers = $this->editOpts['headers'] ?? [];
        if (is_array($headers) && $headers !== []) {
            $config['headers'] = array_map(static fn ($v): string => is_scalar($v) ? (string) $v : '', $headers);
        }
        if (!$this->isStatic) {
            foreach (array_keys($this->editSpecs) as $k) {
                $config['columns'][$k]['column'] = $this->columns[$k];
                if (($this->columns[$k]['format'] ?? '') === 'label') $config['labelColors'] = self::labelColors();
            }
        }
        return $config;
    }

    /**
     * The attributes that make a cell editable — '' for every cell of a table without editable(),
     * so such a table renders byte for byte as before. js/gridkit.js writes the same three, in the
     * same order, when it redraws a setData() table (the 1.110.0 block); keep them in step. Only a
     * server table carries the raw value: a setData() table has it in its data block already.
     */
    private function editAttrs(int|string $key, array $row, mixed $val, \Closure $e): string
    {
        $k = (string) $key;
        if (!isset($this->editSpecs[$k])) return '';
        $idKey = $this->rowIdKey();
        if ($idKey === null || self::rawText($row[$idKey] ?? '') === '') return '';
        return ' data-gk-edit="' . $e($k) . '" tabindex="0" aria-describedby="' . $e($this->editHintId()) . '"'
            . ($this->isStatic ? '' : ' data-gk-raw="' . $e(self::rawText($val)) . '"');
    }

    /**
     * The WHERE clauses this table adds to the query it was given, with their
     * bound parameters.
     *
     * Search is a group of ORs across the searchable columns; every active
     * filter is an AND on top of it. Kept separate from loadData() so that it
     * can be checked without a database connection.
     *
     * @return array{0: list<string>, 1: list<string>, 2: string}
     */
    private function buildWhere(): array
    {
        $where  = [];
        $params = [];
        $types  = '';

        if ($this->searchQuery !== '' && $this->searchCols) {
            $clauses = [];
            // NOT withSubColumns() here: this builds SQL, and a second-line field
            // is a display option — it may well be computed, or come from a
            // second query. Binding it would put a name into `…` that the derived
            // table does not have, and the page would die on the first keystroke
            // in the search box. In a query() table, name the field in search()
            // yourself if it should be searched.
            foreach ($this->searchCols as $col) {
                $clauses[] = "`$col` LIKE ?";
                $params[]  = '%' . $this->searchQuery . '%';
                $types    .= 's';
            }
            $where[] = '(' . implode(' OR ', $clauses) . ')';
        }

        // Until 1.31 a declared filter rendered its dropdown, wrote its value
        // into the URL and was then ignored by the query — the list simply did
        // not change. Static tables were unaffected: the client filters those.
        foreach (array_keys($this->filters) as $col) {
            $value = self::param('gk_filter_' . $col);
            if ($value === '') continue;
            $where[]  = "`$col` = ?";
            $params[] = $value;
            $types   .= 's';
        }

        return [$where, $params, $types];
    }

    private function loadData(): void
    {
        if (!$this->db || !$this->baseQuery) return;

        $sql = $this->baseQuery;
        $params = [];
        $types = '';

        [$where, $params, $types] = $this->buildWhere();
        if ($where !== []) {
            $sql = "SELECT * FROM ($sql) AS _gk WHERE " . implode(' AND ', $where);
        }

        // Sort
        if ($this->sortCol && isset($this->columns[$this->sortCol]) && ($this->columns[$this->sortCol]['sortable'] ?? false)) {
            $dir = $this->sortDir === 'desc' ? 'DESC' : 'ASC';
            $sql .= " ORDER BY `{$this->sortCol}` $dir";
        }

        // Count
        $countSql = "SELECT COUNT(*) FROM ($sql) AS _cnt";
        if ($params) {
            $stmt = $this->db->prepare($countSql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $this->totalRows = $stmt->get_result()->fetch_row()[0];
            $stmt->close();
        } else {
            $this->totalRows = $this->db->query($countSql)->fetch_row()[0];
        }

        // Paginate
        if ($this->perPage > 0) {
            $offset = ($this->currentPage - 1) * $this->perPage;
            $sql .= " LIMIT {$this->perPage} OFFSET {$offset}";
        }

        if ($params) {
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $this->rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        } else {
            $this->rows = $this->db->query($sql)->fetch_all(MYSQLI_ASSOC);
        }
    }

    /**
     * True when this request is the AJAX reload of one table.
     *
     * The reload replaces the table's contents with the raw response body, so
     * that body must be the table fragment and nothing else. `render()` already
     * emits only the fragment for such a request — but it cannot stop the page
     * around it, and a page that draws a sidebar and a header would send those
     * along and inject the whole layout inside the table.
     *
     * So a page with a server-side table ends the request itself:
     *
     *     $table = (new Table('invoices'))->query($db, $sql)-> ... ;
     *
     *     if (Table::isAjaxReload('invoices')) {
     *         $table->render();
     *         exit;
     *     }
     *
     * Anything the reload should also update outside the table goes between
     * `render()` and `exit` as `<template data-gk-replace="css-selector">`.
     *
     * @param string|null $id Restrict to one table; null accepts any.
     */
    public static function isAjaxReload(?string $id = null): bool
    {
        $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        if (strcasecmp($requestedWith, 'XMLHttpRequest') !== 0) {
            return false;
        }

        $requested = $_GET['gk_table'] ?? '';
        if ($requested === '') {
            return false;
        }

        return $id === null || $requested === $id;
    }

    public function render(): void
    {
        if ($this->db) $this->loadData();
        // Before the AJAX branch: the fragment of a reload carries the editable cells too.
        $this->editSpecs = $this->resolveEditColumns();

        // AJAX reload of this table: emit the fragment only. Stopping the
        // page around it is the caller's job — see isAjaxReload().
        if (self::isAjaxReload($this->id)) {
            $this->renderInner();
            return;
        }

        $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo $this->wrapOpenTag($e);

        // Embed JSON data + column config for client-side operations
        if ($this->isStatic) $this->renderDataBlock();

        // Asked here, once per page: an AJAX reload keeps the toolbar it has (and the menu in it).
        // Asked without a toolbar too: then it is what says that a 'hidden' column shows.
        $columnMenu = $this->columnMenu();
        if ($this->showToolbar) $this->renderToolbar($columnMenu, $e);
        if ($this->selectable) $this->renderBulkBar($e);

        $this->renderInner();

        $this->renderModalTemplates($e);
        echo '</div>';
    }

    /**
     * The opening tag of the wrap: its classes, the table id, and what the script reads off it —
     * a static table, a selectable one, the config of an editable one.
     */
    private function wrapOpenTag(\Closure $e): string
    {
        $staticAttr    = $this->isStatic   ? ' data-gk-static'    : '';
        $selectAttr    = $this->selectable ? ' data-gk-selectable' : '';
        $editAttr      = $this->editSpecs !== []
            ? ' data-gk-editable="' . $e(json_encode($this->editConfig(), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)) . '"'
            : '';
        return '<div class="' . $this->wrapClasses() . '" data-gk-table="' . $e($this->id) . '"' . $staticAttr . $selectAttr . $editAttr . '>';
    }

    /** The classes of the wrap: size and variant, what a phone makes of the table, what sticks. */
    private function wrapClasses(): string
    {
        $wrapClasses   = 'gk-table-wrap';
        $wrapClasses  .= ' gk-table-' . $this->size;
        if ($this->variant !== 'default') $wrapClasses .= ' gk-table-' . $this->variant;
        // A list (size('list'), 1.92.0) neither turns into cards nor scrolls on
        // a phone: its columns give way by the list's own width. Card mode would
        // stack every row into a box and scroll mode gives the table 600px — each
        // undoes what the list is for, so asking for one is refused out loud.
        $mobile = $this->mobileMode ?? ($this->size === 'list' ? '' : 'card');
        if ($this->size === 'list' && $mobile !== '') {
            trigger_error("GridKit: a size('list') table sheds columns by its own width ('priority'); mobile('$mobile') was left out.", E_USER_WARNING);
            $mobile = '';
        }
        if ($mobile === 'card') $wrapClasses .= ' gk-table-mobile-card';
        elseif ($mobile === 'scroll') $wrapClasses .= ' gk-table-mobile-scroll';
        // On the wrap, which an AJAX reload keeps: the classes say what sticks, the box in
        // renderInner() is what scrolls.
        if ($this->stickyHead) $wrapClasses .= ' gk-table-sticky-head';
        if ($this->stickyColumn) $wrapClasses .= ' gk-table-sticky-col';
        return $wrapClasses;
    }

    /**
     * The data block of a setData() table: its rows and what the browser needs to sort, search
     * and page them itself and to draw the table again as the server drew it.
     */
    private function renderDataBlock(): void
    {
        $linkKey = $this->rowLinkColumn();
        echo '<script type="application/json" data-gk-data>' . json_encode([
            'rows' => $this->rows,
            // The key selectable() was told to use. Without it the client
            // re-render fell back to "id", so on a table keyed by anything
            // else every row's id became empty on the first sort or search
            // — the whole selection collapsed to one blank entry.
            'rowId' => $this->selectKey,
            // The page size, so the client can page a static table itself.
            // Without it the browser had every row and no way to slice
            // them, so paginate() on a setData() table showed everything
            // at once and its pager fired a server reload the page often
            // could not answer.
            'perPage' => $this->perPage,
            // A column's help icon and a row button's tooltip, as the server writes them: the
            // rebuild adds them back after a sort (1.112.0 block of gridkit.js). A table without
            // 'tooltip' gets exactly what it got before.
            'columns' => $this->withTooltipMarkup($this->columns, 'c', 'tooltipHtml'),
            // The label colour table, but only for a table that shows labels:
            // the client used to carry a shorter copy of it and a sorted
            // table changed its colours.
            'labelColors' => array_reduce(
                $this->columns,
                static fn (?array $c, array $col): ?array =>
                    $c ?? (($col['format'] ?? '') === 'label' ? self::labelColors() : null),
                null
            ),
            // The keys search() was told to use. Without them the client
            // fell back to every rendered column, so a declared search key
            // that is not itself a column was silently never searched —
            // and the markup of an HTML column matched instead.
            'search'  => array_values($this->withSubColumns($this->searchCols)),
            // nowrap() and footer(), so the client-side rebuild keeps both — it
            // used to write a bare <table class="gk-table"> without a <tfoot>.
            'nowrap'  => $this->globalNowrap,
            // loadTime(): the rebuild writes its meta cell too now — a loadTime()
            // row on its own vanished on the first sort. The count beside it is
            // the client's own (rows after search, before the page slice).
            'loadTimeMs' => $this->loadTimeMs,
            'footer'  => array_map(static fn ($c): array => is_string($c) ? ['text' => $c] : (array) $c, $this->footerCells),
            'buttons' => $this->withTooltipMarkup($this->buttons, 'b', 'tooltipAttrs'),
            // Rewritten by the client on every rebuild, like everything else here.
            'caption' => $this->caption,
            'groupBy' => $this->groupCol === '' ? null : [
                'column' => $this->groupCol,
                'labels' => $this->groupLabels,
            ],
            // rowLink(), with the column already resolved: the client must not
            // guess "the first column" from an object whose numeric-looking
            // keys JavaScript reorders.
            'rowLink' => $linkKey === null ? null : ['column' => $linkKey] + $this->rowLink,
        // SUBSTITUTE: one malformed byte made json_encode() answer false, the
        // block came out empty and sort, search and paging died silently.
        // HEX_TAG: "<!--<script" inside a cell puts the HTML parser into
        // its double-escaped state and the block swallows the rest of the page.
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_HEX_TAG) . '</script>';
    }

    /**
     * The toolbar, left to right: the search, the caller's own HTML, the filters, the column menu,
     * the CSV button, the density control, and the new button at the far end.
     */
    private function renderToolbar(?array $columnMenu, \Closure $e): void
    {
        echo '<div class="gk-toolbar">';
        if ($this->searchCols) $this->renderSearchField($e);
        if ($this->toolbarHtml !== '') echo $this->toolbarHtml;
        foreach ($this->filters as $col => $f) $this->renderFilter($col, $f, $e);
        if ($columnMenu !== null) echo $this->renderColumnToggle($columnMenu, $e);
        if ($this->csvFile !== null) $this->renderCsvButton();
        if ($this->densityToggle) echo $this->renderDensityToggle($e);
        if ($this->newBtnLabel) $this->renderNewButton();
        echo '</div>';
    }

    /** The search field of search(), holding the search the page was opened with. */
    private function renderSearchField(\Closure $e): void
    {
        // A placeholder is not an accessible name: it is not always
        // announced, and it disappears as soon as anything is typed.
        $searchLabel = $e(Lang::t('table.search'));
        echo '<input type="text" class="gk-search" data-gk-search'
           . ' placeholder="' . $searchLabel . '" aria-label="' . $searchLabel . '"'
           . ' value="' . $e($this->searchQuery) . '">';
    }

    /** One filter() as a select: "All" first, then its options, the one in the URL selected. */
    private function renderFilter(int|string $col, array $f, \Closure $e): void
    {
        // The active value comes back from the URL. Without this the
        // dropdown snaps to "All" on every full page load while the table
        // below it still shows filtered rows — which is what a shared link
        // or a plain reload does.
        $active = self::param('gk_filter_' . $col);
        $sel = static fn(string $value): string => $value === $active ? ' selected' : '';

        $filterLabel = $f['label']
            ?? $this->columns[$col]['label']
            ?? $f['placeholder']
            ?? Lang::t('table.filter_all');
        echo '<select class="gk-filter" data-gk-filter="' . $e($col) . '"'
           . ' aria-label="' . $e($filterLabel) . '">';
        echo '<option value=""' . $sel('') . '>'
           . $e($f['placeholder'] ?? Lang::t('table.filter_all')) . '</option>';
        foreach ($f['options'] ?? [] as $val => $label) {
            echo '<option value="' . $e($val) . '"' . $sel((string) $val) . '>'
               . $e($label) . '</option>';
        }
        echo '</select>';
    }

    /** The download button of exportCsv(). */
    private function renderCsvButton(): void
    {
        echo Button::render($this->csvOpts['label'] ?? Lang::t('table.export_csv'), [
            'variant' => 'outlined',
            'color' => 'neutral',
            'size' => 'sm',
            'shape' => 'pill',
            'icon' => 'download',
            'data' => ['gk-export-csv' => $this->csvFile, 'gk-export-delimiter' => $this->csvOpts['delimiter'] ?? ','],
        ]);
    }

    /** The button of newButton(), pushed to the end of the toolbar; with 'modal' it opens that modal. */
    private function renderNewButton(): void
    {
        echo '<div class="gk-toolbar-spacer"></div>';
        $modal = $this->newBtnOpts['modal'] ?? '';
        echo Button::render($this->newBtnLabel, [
            'variant' => 'filled',
            'color' => 'primary',
            'icon' => $this->newBtnOpts['icon'] ?? 'add',
            'shape' => 'pill',
            'data' => $modal ? ['gk-modal' => $modal] : [],
        ]);
    }

    /**
     * The bar that appears while rows are ticked: the count, the buttons of bulkAction(), delete
     * and cancel. Written hidden; the script shows it.
     */
    private function renderBulkBar(\Closure $e): void
    {
        echo '<div class="gk-bulk-bar" style="display:none;">'
           . '<span class="material-icons" style="font-size:18px;" aria-hidden="true">check_box</span>'
           . '<span class="gk-bulk-count">0 ' . $e(Lang::t('table.selected', ['n' => ''])) . '</span>'
           . '<div class="gk-toolbar-spacer"></div>'
           . $this->renderBulkActions($e)
           . '<button type="button" data-gk-bulk-delete>'
           .   '<span class="material-icons" aria-hidden="true">delete</span> ' . $e(Lang::t('table.delete'))
           . '</button>'
           . '<button type="button" data-gk-bulk-cancel>' . $e(Lang::t('table.cancel')) . '</button>'
           . '</div>';
    }

    /** One template per modal(): the title, URL and size a button with that 'modal' opens it with. */
    private function renderModalTemplates(\Closure $e): void
    {
        foreach ($this->modals as $mid => $m) {
            echo '<template data-gk-modal-tpl="' . $e($mid) . '" data-gk-modal-title="' . $e($m['title']) . '" data-gk-modal-url="' . $e($m['url']) . '" data-gk-modal-size="' . $e($m['size'] ?? 'medium') . '"></template>';
        }
    }

    private function renderInner(): void
    {
        $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $tableClass = 'gk-table' . ($this->globalNowrap ? ' gk-table-nowrap' : '');

        $leftButtons = array_filter($this->buttons, fn($b) => ($b['position'] ?? 'right') === 'left');
        $rightButtons = array_filter($this->buttons, fn($b) => ($b['position'] ?? 'right') === 'right');
        // How wide a full-width row is. The group heading, the empty state and
        // the footer each used to work this out again from the same four
        // terms; a column kind added to one of the three would have left the
        // other two spanning the wrong width.
        $colCount = count($this->columns)
                  + ($leftButtons ? 1 : 0) + ($rightButtons ? 1 : 0) + ($this->selectable ? 1 : 0);

        /*
         * Where the client says what just happened.
         *
         * Sorting, filtering and paging replace the rows in place. On screen
         * that is obvious; to a screen reader nothing announced itself at all —
         * the user pressed a control and the table silently became different
         * data, with no way to tell whether it had worked, or how much was left.
         * role=status is polite: it waits for a pause rather than interrupting.
         * Empty and off-screen, because this is for people who are not looking.
         */
        echo '<div class="gk-sr-only" role="status" aria-live="polite"'
           . ' data-gk-table-status="' . $e($this->id) . '"></div>';

        // What every editable cell says after its value (aria-describedby). Hidden, so a screen
        // reader does not read it as a loose sentence above the table; a hidden element still
        // describes what points at it. Here and not in render(): an AJAX reload swaps this part.
        if ($this->editSpecs !== []) {
            echo '<span id="' . $e($this->editHintId()) . '" hidden>' . $e(Lang::t('table.edit_hint')) . '</span>';
        }
        $box = $this->stickyBox($leftButtons, $e);
        echo $box;

        echo '<table class="' . $tableClass . '">'
           . ($this->caption !== '' ? '<caption class="gk-sr-only">' . $e($this->caption) . '</caption>' : '')
           . '<thead><tr>';
        $this->renderTableHead($leftButtons, $rightButtons, $e);
        $this->renderTableBody($leftButtons, $rightButtons, $colCount, $e);
        $this->renderTableFoot($colCount, $e);

        echo '</table>';
        if ($box !== '') echo '</div>';

        // Pagination
        $this->renderPager($e);
    }

    /**
     * The opening tag of the box a sticky table scrolls in, or '' when it needs none (1.107.0).
     *
     * Here and not in render(): an AJAX reload replaces everything renderInner() wrote, so the
     * box has to come back with the table, around the table only — the pager stays below it,
     * in view, however far the rows are scrolled.
     *
     * data-gk-sticky-col says how many cells of a row stick: the checkbox, the cell of the
     * left buttons and the first column. Counted here, because the header row writes the
     * cell over the left buttons at its end, so the stylesheet cannot count the classes of
     * the header; it takes the first n cells of every row, header and body alike.
     *
     * The box is a region with a name and a tab stop: content that scrolls has to be
     * scrollable by keyboard, and a table without a single control in it offers nothing
     * else to put the focus on.
     */
    private function stickyBox(array $leftButtons, \Closure $e): string
    {
        if (!$this->stickyColumn && $this->stickyHeight === '') return '';
        $height = $this->stickyHeight !== '' ? $this->stickyHeight : ($this->stickyHead ? '70vh' : '');
        $stuck = ($this->selectable ? 1 : 0) + ($leftButtons ? 1 : 0) + ($this->columns ? 1 : 0);
        $name = $this->caption !== '' ? $this->caption : Lang::t('table.scroll_region');
        return '<div class="gk-table-sticky-box" role="region" tabindex="0" aria-label="' . $e($name) . '"'
            . ($this->stickyColumn && $stuck > 0 ? ' data-gk-sticky-col="' . $stuck . '"' : '')
            . ($height !== '' ? ' style="--gk-table-sticky-height:' . $e($height) . '"' : '')
            . '>';
    }

    /** The header row up to its end: the select-all cell, one cell per column, the empty cells over the buttons. */
    private function renderTableHead(array $leftButtons, array $rightButtons, \Closure $e): void
    {
        if ($this->selectable) {
            // aria-label, not title alone: a title is announced inconsistently
            // and never on touch, so this control read as an unnamed checkbox.
            // scope="col" for the same reason as the headers below.
            echo '<th scope="col" class="gk-cb-col"><input type="checkbox" data-gk-select-all'
               . ' aria-label="' . $e(Lang::t('table.select_all')) . '"'
               . ' title="' . $e(Lang::t('table.select_all')) . '"></th>';
        }
        // Empty header cells still need the scope, or the cells beneath them
        // inherit the association of the last real header to their left.
        // The cell over the left buttons stands where the buttons stand in a row: before the
        // first column (1.110.1). It stood at the end of the header row, beside the one over the
        // right buttons, so with left buttons every heading stood one column before its cells.
        if ($leftButtons) echo '<th scope="col" class="gk-actions-col"><span class="gk-sr-only">'
            . $e(Lang::t('table.actions')) . '</span></th>';
        foreach ($this->columns as $key => $col) {
            $this->renderHeadCell($key, $col, $e);
        }
        if ($rightButtons) echo '<th scope="col" class="gk-actions-col"><span class="gk-sr-only">'
            . $e(Lang::t('table.actions')) . '</span></th>';
        echo '</tr></thead>';
    }

    /**
     * One header cell: width, classes, aria-sort and the sort button.
     * $key keeps its type — a numeric column key is an int, and `$this->sortCol === $key` relies on that.
     */
    private function renderHeadCell(int|string $key, array $col, \Closure $e): void
    {
        $styles = [];
        if (isset($col['width']) && $col['width'] !== 'auto') $styles[] = 'width:' . $e($col['width']);
        if (isset($col['minWidth'])) $styles[] = 'min-width:' . $e($col['minWidth']);
        if (isset($col['maxWidth'])) $styles[] = 'max-width:' . $e($col['maxWidth']);
        if (!empty($col['nowrap'])) $styles[] = 'white-space:nowrap';
        $style = $styles ? ' style="' . implode(';', $styles) . '"' : '';
        $sortable = $col['sortable'] ?? false;
        $clsList = [];
        if ($sortable) $clsList[] = 'gk-sortable';
        if (!empty($col['hideOnMobile'])) $clsList[] = 'gk-hide-mobile';
        // Header and cells give way together (a list's 'priority').
        if (isset($col['priority'])) $clsList[] = 'gk-col-p' . $col['priority'];
        // A header stands where its column stands. The cells have carried
        // 'align' and the numeric class all along; the header got neither, so
        // a right-aligned column of figures sat under a left-aligned heading.
        if (($alignCls = self::headerAlignClass($col)) !== '') $clsList[] = $alignCls;
        $attrs = '';
        $sortBtn = null;
        if ($sortable) {
            $newDir = ($this->sortCol === $key && $this->sortDir === 'asc') ? 'desc' : 'asc';
            // A sortable header is a control, so it has to behave like
            // one: reachable by Tab, announced as a button, and reporting
            // the direction it is currently sorted in.
            $ariaSort = $this->sortCol === $key
                ? ($this->sortDir === 'asc' ? 'ascending' : 'descending')
                : 'none';
            // aria-sort belongs on the header; the control goes inside it.
            // This used to be one element doing both — a <th> carrying
            // tabindex, role="button" and aria-sort. role="button" replaces
            // the columnheader role, so the cell stopped being announced as
            // a column header at all, and aria-sort, which is only defined
            // on a header, became invalid on it. The W3C validator rejects
            // that pair; a screen reader user simply lost the column.
            $attrs = ' aria-sort="' . $ariaSort . '"';
            $sortBtn = [$e($key), $newDir];
            if ($this->sortCol === $key) {
                $clsList[] = 'gk-sorted-' . $this->sortDir;
            }
        }
        $cls = $clsList ? ' class="' . implode(' ', $clsList) . '"' : '';
        $inner = $e($col['label']);
        if ($sortBtn !== null) {
            $inner = '<button type="button" class="gk-sort-btn"'
                   . ' data-gk-sort="' . $sortBtn[0] . '"'
                   . ' data-gk-dir="' . $sortBtn[1] . '">' . $inner . '</button>';
        }
        // 'tooltip' (1.112.0): a help icon beside the label, a button of its own. Inside the sort
        // button it would be a button in a button, and a click on it would sort.
        $inner .= $this->tooltipFor('c', $key, $col);
        // scope="col": without it a screen reader has to guess which
        // header a cell belongs to, and on a table with an action column or
        // a checkbox column it guesses wrong. This is the one attribute a
        // data table cannot do without.
        echo "<th scope=\"col\"{$cls}{$style}{$attrs}>" . $inner . "</th>";
    }

    /** The rows: group headings, one row per visible record, the empty state. */
    private function renderTableBody(array $leftButtons, array $rightButtons, int $colCount, \Closure $e): void
    {
        echo '<tbody>';

        $groupCounts = [];
        if ($this->groupCol !== '') {
            foreach ($this->rows as $r) {
                $gk = (string) ($r[$this->groupCol] ?? '');
                $groupCounts[$gk] = ($groupCounts[$gk] ?? 0) + 1;
            }
        }
        $lastGroup = null;

        // The column that carries each row's control. Said once per render
        // rather than once per row, and only for a combination that cannot work.
        $linkKey = $this->rowLinkColumn();
        if ($this->rowLink && $linkKey === null) {
            trigger_error("GridKit: rowLink() names a column this table does not have — the rows stay plain.", E_USER_WARNING);
        } elseif ($linkKey !== null && in_array($this->columns[$linkKey]['format'] ?? '', self::ROW_LINK_REFUSED, true)) {
            trigger_error("GridKit: the rowLink() column cannot be 'format' => '" . $this->columns[$linkKey]['format'] . "' — it writes a link of its own, and a control inside a control reaches nobody. The rows stay plain.", E_USER_WARNING);
        } elseif ($linkKey !== null && isset($this->columns[$linkKey]['href'])) {
            trigger_error("GridKit: the rowLink() column has an 'href' of its own — the row's control takes its place, the cell link was left out.", E_USER_WARNING);
        }

        // A static table holds every row in the browser and pages there. The
        // first render has to show one page all the same, or the page arrives
        // with all of them and collapses to ten as soon as JavaScript runs.
        // (query() and rows() already hand over a single page.)
        $visible = $this->rows;
        if ($this->isStatic && $this->perPage > 0) {
            $visible = array_slice($visible, ($this->currentPage - 1) * $this->perPage, $this->perPage);
        }

        foreach ($visible as $row) {
            if ($this->groupCol !== '') {
                $gk = (string) ($row[$this->groupCol] ?? '');
                if ($gk !== $lastGroup) {
                    $gLabel = $this->groupLabels[$gk] ?? $gk;
                    echo '<tr class="gk-table-group"><td colspan="' . $colCount . '">'
                       . '<span class="gk-table-group-name">' . $e($gLabel) . '</span>'
                       . '<span class="gk-table-group-n">' . (int) ($groupCounts[$gk] ?? 0) . '</span>'
                       . '</td></tr>';
                    $lastGroup = $gk;
                }
            }
            $this->renderRow($row, $leftButtons, $rightButtons, $linkKey, $e);
        }

        // $visible, not $this->rows: a static table slices its own page here,
        // so asking for a page past the end left a <tbody> with no rows AND no
        // empty state — a table that simply stopped, with the pager above it
        // still offering the way back.
        if (!$visible) {
            echo $this->renderEmpty($colCount);
        }

        echo '</tbody>';
    }

    /** One row: its selection cell, the buttons on both sides and one cell per column. */
    private function renderRow(array $row, array $leftButtons, array $rightButtons, ?string $linkKey, \Closure $e): void
    {
        // The record a row stands for: the selection's key, or editable()'s (a change is saved under it).
        $idKey = $this->rowIdKey();
        $rowId = $idKey !== null ? $e($row[$idKey] ?? '') : '';
        $rowIdAttr = $idKey !== null ? ' data-gk-row-id="' . $rowId . '"' : '';
        $target = $linkKey !== null ? $this->rowTarget($row, $e) : null;
        echo '<tr' . ($target !== null ? ' class="gk-row-link"' : '') . $rowIdAttr . '>';
        if ($this->selectable) {
            // Named like the one in the header: unnamed, every row read as
            // "checkbox" and nothing else.
            echo '<td class="gk-cb-col"><input type="checkbox" aria-label="' . $e(Lang::t('table.select_row')) . '" value="' . $rowId . '"></td>';
        }
        if ($leftButtons) {
            echo '<td class="gk-actions gk-actions-left"><div class="gk-btn-group">';
            $this->renderButtons($leftButtons, $row, $e);
            echo '</div></td>';
        }
        foreach ($this->columns as $key => $col) {
            $this->renderCell($key, $col, $row, $linkKey, $target, $e);
        }
        if ($rightButtons) {
            echo '<td class="gk-actions gk-actions-right"><div class="gk-btn-group">';
            $this->renderButtons($rightButtons, $row, $e);
            echo '</div></td>';
        }
        echo '</tr>';
    }

    /** One cell: alignment, widths, classes, the label of the column for the card layout, the formatted value. */
    private function renderCell(int|string $key, array $col, array $row, ?string $linkKey, ?array $target, \Closure $e): void
    {
        $val = $row[$key] ?? '';
        $tdStyles = [];
        $tdCls = [];
        if (isset($col['align'])) $tdStyles[] = 'text-align:' . $e($col['align']);
        if (isset($col['width']) && $col['width'] !== 'auto') $tdStyles[] = 'width:' . $e($col['width']);
        if (isset($col['minWidth'])) $tdStyles[] = 'min-width:' . $e($col['minWidth']);
        if (isset($col['maxWidth'])) $tdStyles[] = 'max-width:' . $e($col['maxWidth']);
        if (!empty($col['nowrap'])) $tdStyles[] = 'white-space:nowrap';
        if (($col['format'] ?? '') === 'currency' || ($col['format'] ?? '') === 'number') {
            $tdCls[] = 'gk-td-num';
            if (empty($col['nowrap'])) $tdStyles[] = 'white-space:nowrap';
        }
        if (!empty($col['hideOnMobile'])) $tdCls[] = 'gk-hide-mobile';
        if (isset($col['priority'])) $tdCls[] = 'gk-col-p' . $col['priority'];
        // Secondary columns (numbers, identifiers) step back in text color
        // so that the actual name is what gets the attention.
        if (!empty($col['muted'])) $tdCls[] = 'gk-td-muted';
        $tdStyle = $tdStyles ? ' style="' . implode(';', $tdStyles) . '"' : '';
        $tdClass = $tdCls ? ' class="' . implode(' ', $tdCls) . '"' : '';
        $dataLabel = ' data-label="' . $e($col['label']) . '"';
        // (string): a key like '2024' is an integer in a PHP array.
        $formatted = $this->cellContent($val, $col, $row, $e, (string) $key === $linkKey ? $target : null);
        $editAttrs = $this->editSpecs !== [] ? $this->editAttrs($key, $row, $val, $e) : '';
        echo "<td{$tdClass}{$tdStyle}{$dataLabel}{$editAttrs}>{$formatted}</td>";
    }

    /** The footer: the cells the caller gave, and the load time or the row count. */
    private function renderTableFoot(int $colCount, \Closure $e): void
    {
        // Footer: custom cells or load time
        if ($this->footerCells || $this->loadTimeMs !== null) {
            echo '<tfoot><tr class="gk-table-footer">';

            if ($this->footerCells) {
                $usedCols = 0;
                foreach ($this->footerCells as $cell) {
                    if (is_string($cell)) {
                        $cell = ['text' => $cell];
                    }
                    $colspan = (int) ($cell['colspan'] ?? 1);
                    $align = $cell['align'] ?? 'left';
                    $bold = !empty($cell['bold']);
                    $style = 'text-align:' . $align . ';';
                    if ($bold) $style .= 'font-weight:600;';
                    if ($align === 'right') $style .= 'color:var(--gk-primary);';
                    // Escaped like every other cell. Until 1.80.1 both went out raw,
                    // although the documentation calls a footer cell "a plain string".
                    echo '<td colspan="' . $colspan . '" style="' . $e($style) . '">' . $e($cell['text'] ?? '') . '</td>';
                    $usedCols += $colspan;
                }
                // Remaining columns + load time
                $remaining = $colCount - $usedCols;
                if ($remaining > 0 && $this->loadTimeMs !== null) {
                    $timeDisplay = $this->loadTimeMs < 1000 ? $this->loadTimeMs . ' ms' : number_format($this->loadTimeMs / 1000, 2, Lang::t('format.decimal'), Lang::t('format.thousands')) . ' s';
                    echo '<td colspan="' . $remaining . '" class="gk-table-meta">' . $timeDisplay . '</td>';
                } elseif ($remaining > 0) {
                    echo '<td colspan="' . $remaining . '"></td>';
                }
            } else {
                // Load time only
                $timeDisplay = $this->loadTimeMs < 1000 ? $this->loadTimeMs . ' ms' : number_format($this->loadTimeMs / 1000, 2, Lang::t('format.decimal'), Lang::t('format.thousands')) . ' s';
                echo '<td colspan="' . $colCount . '" class="gk-table-meta">'
                    . $e((string) $this->totalRows) . ' ' . $e(Lang::t('pagination.entries')) . ' · ' . $timeDisplay
                    . '</td>';
            }

            echo '</tr></tfoot>';
        }
    }

    /**
     * The pager under the table, for a table with more rows than one page.
     *
     * Since 1.118.0 it is the bar GridKit\Pagination draws: .gk-rowpager, the count on the left
     * ("6–10 of 12") and the page buttons on the right (.gk-rowpager-nav, .gk-pg, first and last
     * included), so a table and a list paged by hand look the same. What it keeps of its own:
     *
     *   - <button data-gk-page>, not links. A table pages in place: a setData() table slices
     *     the rows it holds, a server table reloads its fragment. A copy of js/gridkit.js from
     *     before 1.118.0 (the PawBot dashboard keeps its own) binds to [data-gk-page] and skips
     *     a disabled button, and it never cancels a click: a link would have navigated away
     *     under it.
     *   - .gk-pagination on the nav: what that script removes before it writes its own pager
     *     into a rebuilt table. Without it the table showed two.
     *   - a disabled button where Pagination leaves a hidden span, and "Page 3 of 12" as the
     *     name of a page button.
     *
     * The 1.118.0 block of js/gridkit.js writes the same markup when it rebuilds a setData()
     * table, attribute for attribute: keep the two in step.
     */
    private function renderPager(\Closure $e): void
    {
        if ($this->perPage <= 0 || $this->totalRows <= $this->perPage) return;
        $page  = $this->currentPage;
        $pages = (int) ceil($this->totalRows / $this->perPage);
        $num   = static fn (int $n): string => number_format($n, 0, Lang::t('format.decimal'), Lang::t('format.thousands'));
        // The rows this page shows. A page past the end (a hand-typed ?gk_page=99) shows the
        // empty state and has no rows; the count then says how many there are.
        $count = $page > $pages
            ? $num($this->totalRows) . ' ' . Lang::t('pagination.entries')
            : Lang::t('pagination.range', [
                'from'  => $num(($page - 1) * $this->perPage + 1),
                'to'    => $num(min($this->totalRows, $page * $this->perPage)),
                'total' => $num($this->totalRows),
            ]);
        // First, previous, next and last, named for what they do and not for their glyph. The ones
        // that lead somewhere carry the name as a title too, as Pagination's do, so the tooltip
        // engine shows it on hover and focus (1.118.1); a disabled button shows no tooltip.
        $icon = static fn (string $glyph, int $target, string $name, bool $off): string =>
            '<button type="button" class="gk-pg gk-pg-icon' . ($off ? ' gk-pg-off' : '') . '" data-gk-page="' . $target . '"'
            . ' aria-label="' . $e(Lang::t($name)) . '"'
            . ($off ? ' disabled' : ' title="' . $e(Lang::t($name)) . '"') . '>'
            . '<span class="material-icons" aria-hidden="true">' . $glyph . '</span></button>';

        // nav, so the pager is a landmark a screen reader can jump to and skip past, instead of
        // a run of unexplained numbers in the middle of the table.
        $html = '<nav class="gk-pagination gk-rowpager" aria-label="' . $e(Lang::t('pagination.aria')) . '">'
            . '<span class="gk-rowpager-info"><span class="gk-rowpager-count">' . $e($count) . '</span></span>'
            . '<div class="gk-rowpager-nav">'
            . $icon('first_page', 1, 'pagination.first', $page <= 1)
            . $icon('chevron_left', max(1, min($page - 1, $pages)), 'pagination.prev', $page <= 1);
        // A window around the current page, plus the first and the last: Pagination's shape.
        // Printing every page put 400 buttons in the DOM for a 10,000-row list, on every
        // reload, which is precisely the size the server-side path exists for.
        $show = [1, $pages];
        for ($i = $page - 2; $i <= $page + 2; $i++) {
            if ($i >= 1 && $i <= $pages) $show[] = $i;
        }
        $show = array_values(array_unique($show));
        sort($show);
        $previous = 0;
        foreach ($show as $i) {
            if ($previous && $i - $previous > 1) $html .= '<span class="gk-pg-gap">…</span>';
            // "3" on its own is not a name, it is a digit; aria-current says which page you are
            // on, which the colour says only to those who see it.
            $html .= '<button type="button" class="gk-pg' . ($i === $page ? ' gk-pg-active' : '') . '" data-gk-page="' . $i . '"'
                . ' aria-label="' . $e(Lang::t('pagination.page_of', ['page' => $i, 'total' => $pages])) . '"'
                . ($i === $page ? ' aria-current="page"' : '') . '>' . $i . '</button>';
            $previous = $i;
        }
        echo $html
            . $icon('chevron_right', min($pages, $page + 1), 'pagination.next', $page >= $pages)
            . $icon('last_page', $pages, 'pagination.last', $page >= $pages)
            . '</div></nav>';
    }

    /** The buttons on one side of a row, in the order they were added: each one that shows in this row. */
    private function renderButtons(array $buttons, array $row, \Closure $e): void
    {
        foreach ($buttons as $bname => $bopts) {
            if (self::buttonShows($bopts, $row)) $this->renderButton($bname, $bopts, $row, $e);
        }
    }

    /** showIf and hideIf: does this button show in this row? */
    private static function buttonShows(array $bopts, array $row): bool
    {
        // showIf: only show the button when the row field is truthy
        if (isset($bopts["showIf"]) && empty($row[$bopts["showIf"]])) return false;
        // hideIf: hide the button when the row field is truthy
        if (isset($bopts["hideIf"]) && !empty($row[$bopts["hideIf"]])) return false;
        return true;
    }

    /**
     * One row button: a <button> that fires its row action, opens its modal or runs its onclick —
     * or an <a> when it goes somewhere without asking first. It shows its text (after its icon,
     * if it has one) or its icon alone; a button with neither is not written.
     *
     * $bname keeps its type: a button named '1' has the key 1, an int, and a `string` parameter
     * would turn that button into a TypeError.
     */
    private function renderButton(int|string $bname, array $bopts, array $row, \Closure $e): void
    {
        // Almost every row button needs to say which row it belongs to, and
        // forgetting `'params' => ['id' => 'id']` failed silently: the edit
        // modal opened as if it were a new record. The row's own id is sent
        // unless the caller mapped one itself.
        $params = self::rowParams($bopts['params'] ?? [], $row);
        $color = self::buttonColor($bopts);
        $confirmMsg = self::buttonConfirm($bopts);
        $confirmAttr = $confirmMsg !== null ? ' data-gk-confirm="' . $e($confirmMsg) . '"' : '';

        // Data attributes
        $paramsAttr = " data-gk-params='" . $e(json_encode($params)) . "'";
        $dataAttrs = ' data-gk-action="' . $e($bname) . '"' . $paramsAttr . $confirmAttr;
        if (isset($bopts['modal'])) {
            $dataAttrs .= ' data-gk-modal="' . $e($bopts['modal']) . '"';
        }
        // aria-label is the name, title stays for the tooltip — see buttonName().
        $ariaAttr  = ' aria-label="' . $e(self::buttonName($bname, $bopts)) . '"';
        $titleAttr = !empty($bopts['title']) ? ' title="' . $e($bopts['title']) . '"' : '';
        // 'tooltip' (1.112.0) takes the title's place: one popup, GridKit's, not the browser's
        // on top of it. 'title' still names an icon-only button through buttonName().
        $tooltipAttrs = $this->tooltipFor('b', $bname, $bopts);
        if ($tooltipAttrs !== '') $titleAttr = $tooltipAttrs;

        $href = self::buttonHref($bopts, $row);
        // A link carries no data-gk-action: the delegated handler would fire
        // gk:rowaction on top of the navigation. The confirmation stays — the
        // handler below asks before it follows the link.
        // A question before a link cannot live on an <a>: a middle click, a
        // Ctrl-click and Enter each take the native path, and a page whose
        // JavaScript failed to load would follow it without asking at all.
        // So a link WITH a confirmation is a button carrying its target.
        if ($href !== null && $confirmMsg !== null) {
            // No data-gk-action: going somewhere is not a row action, and the
            // delegated handler would ask its own question on top of this one.
            $dataAttrs = $paramsAttr . $confirmAttr . ' data-gk-href="' . $e($href) . '"';
            $href = null;
        }
        $linkAttrs = $href === null ? '' : ' href="' . $e($href) . '"' . $paramsAttr;
        $clickAttr = self::buttonOnclick($bopts, $row, $confirmMsg, $e);

        $hasText  = !empty($bopts['text']);
        $iconName = $bopts['icon'] ?? '';
        $iconHtml = $iconName ? $this->iconSvg($iconName) : '';

        // $hasText alone, not $hasText && $iconHtml: a row button given
        // 'text' and no 'icon' fell past both arms and rendered nothing at
        // all. On a setData() table the client re-render draws that same
        // button, so it appeared out of nowhere on the first sort.
        if ($hasText) {
            // Icon + Text button
            $cls = 'gk-btn gk-btn-icon-text gk-btn-text gk-btn-' . $color;
            $nameAttr = '';
            $inner = $iconHtml . '<span>' . $e($bopts['text']) . '</span>';
        } elseif ($iconHtml) {
            // Icon-only button (sm) — same classes as JS renderBtnGroup
            // aria-label only here. The icon+text branch above is named by
            // its visible text, and an aria-label would override that —
            // which also breaks activating the control by speaking its
            // visible name.
            $cls = 'gk-btn gk-btn-icon-only gk-btn-text gk-btn-' . $color . ' gk-btn-sm';
            $nameAttr = $ariaAttr;
            $inner = $iconHtml;
        } else {
            return;
        }
        if ($href !== null) {
            echo '<a class="' . $cls . '"' . $linkAttrs . $nameAttr . $titleAttr . '>' . $inner . '</a>';
        } else {
            echo '<button type="button" class="' . $cls . '"' . $nameAttr . $titleAttr . $clickAttr . $dataAttrs . '>'
               . $inner . '</button>';
        }
    }

    /**
     * The colour of a row button: 'color', or 'class'; neutral for anything else.
     *
     * Every other component in GridKit names this option `color` — Button::render(), ActionGroup
     * items, StatCards. The row button read `class` and nothing else, so the `'color' => 'danger'`
     * in the skill's own example produced a grey delete button. Both names work; `color` is the
     * one to use.
     */
    private static function buttonColor(array $bopts): string
    {
        $colorMap = ['danger' => 'danger', 'success' => 'success', 'warning' => 'warning', 'primary' => 'primary'];
        $colorName = $bopts['color'] ?? $bopts['class'] ?? '';
        return $colorMap[$colorName] ?? 'neutral';
    }

    /**
     * The question a row button asks before it acts, or null: `'confirm' => true` asks the
     * standard one, a message asks itself.
     *
     * 'confirm' was documented in the README and read by nothing at all, which left the delete
     * button in the headline example deleting without asking — and, with neither onclick nor
     * modal, doing nothing.
     */
    private static function buttonConfirm(array $bopts): ?string
    {
        if (empty($bopts['confirm'])) return null;
        return is_string($bopts['confirm'])
            ? $bopts['confirm']
            : Lang::t('table.confirm_delete');
    }

    /**
     * The accessible name of a row button: 'aria', else 'title', else the catalogue's
     * action.<name>, else the name itself made readable.
     *
     * A row button is usually icon-only, so its whole content is an <svg> — nothing a screen
     * reader can read. It announced as "button", six times over on a three-row table, and one
     * of those six deletes the record.
     *
     * title is not enough on its own: GK.tip moves it into data-gk-tip on the first hover and
     * removes the attribute, so a control named only by its title goes silent as the pointer
     * crosses it. aria-label is the name; title stays for the tooltip.
     */
    private static function buttonName(int|string $bname, array $bopts): string
    {
        $actionName = $bopts['aria'] ?? $bopts['title'] ?? null;
        if ($actionName !== null) return (string) $actionName;
        $key = 'action.' . $bname;
        $translated = Lang::t($key);
        // Lang::t returns the key when it knows nothing about it.
        // (string): a button named '3' arrives as the key 3, and str_replace() under strict_types
        // refused the int — the whole table threw a TypeError (1.110.2). renderBulkActions() casts too.
        return $translated !== $key
            ? $translated
            : ucfirst(str_replace(['_', '-'], ' ', (string) $bname));
    }

    /**
     * The 'tooltip' of a column (kind 'c': the help icon of its header, named after its label)
     * or of a row button (kind 'b': the attributes that replace its title), '' without one.
     * Worked out once per table — see $tooltipMarkup.
     */
    private function tooltipFor(string $kind, int|string $key, array $opts): string
    {
        $memo = $kind . ':' . $key;
        if (!isset($this->tooltipMarkup[$memo])) {
            $tip = $opts['tooltip'] ?? null;
            $this->tooltipMarkup[$memo] = match (true) {
                $tip === null || $tip === '' || $tip === [] => '',
                $kind === 'c' => Tooltip::help($tip, ['label' => (string) ($opts['label'] ?? '')]),
                default => Tooltip::attrs($tip),
            };
        }
        return $this->tooltipMarkup[$memo];
    }

    /**
     * Columns or buttons for the data block, each with a tooltip carrying its markup under
     * $field, so the client rebuild writes what the server wrote. Untouched without one.
     */
    private function withTooltipMarkup(array $items, string $kind, string $field): array
    {
        foreach ($items as $key => $item) {
            $markup = $this->tooltipFor($kind, $key, $item);
            if ($markup !== '') $items[$key][$field] = $markup;
        }
        return $items;
    }

    /**
     * Where a row button goes, filled from the row: null without 'href', for a target
     * fillTarget() refuses, and for a button with 'modal' or 'onclick'.
     *
     * 'href' => '/users/{id}' makes the row button a real link: middle click, "open in new tab"
     * and the browser's status bar work again. Ten SSI Panel views pushed window.location.href
     * into a <button> instead, and none of them could be opened in a second tab. 'modal' and
     * 'onclick' keep what they had: a button that already opens something must not turn into a
     * link because someone added href — it would lose its behaviour without a word.
     */
    private static function buttonHref(array $bopts, array $row): ?string
    {
        if (isset($bopts['href']) && (string) $bopts['href'] !== ''
            && !isset($bopts['modal']) && empty($bopts['onclick'])) {
            return self::fillTarget((string) $bopts['href'], $row);
        }
        return null;
    }

    /** The onclick attribute of a row button, every {field} of its code filled from the row; '' without 'onclick'. */
    private static function buttonOnclick(array $bopts, array $row, ?string $confirmMsg, \Closure $e): string
    {
        if (empty($bopts['onclick'])) return '';
        $js = preg_replace_callback('/\{(\w+)\}/', static function ($m) use ($row) {
            return json_encode($row[$m[1]] ?? null, JSON_UNESCAPED_UNICODE);
        }, (string) $bopts['onclick']);
        // An inline handler runs before any delegated listener could stop
        // it, so the confirmation has to wrap the code itself.
        if ($confirmMsg !== null) {
            $js = 'GK.confirm(' . json_encode($confirmMsg, JSON_UNESCAPED_UNICODE)
                . ',{danger:true}).then(function(ok){if(ok){' . $js . '}})';
        }
        return ' onclick="' . $e($js) . '"';
    }

    /**
     * Is this a target we are willing to link to? A list of what is allowed,
     * not of what is forbidden — a deny list loses. "java\tscript:" passed one:
     * the browser strips control characters before reading the scheme, so it
     * ran while the pattern saw no scheme at all. So: every character up to
     * 0x20 goes first, then only a relative path, a fragment, a query, or a
     * spelt-out http/https/mailto/tel is kept. A leading "//" is refused too —
     * it leaves our own origin without naming a scheme.
     *
     * js/gridkit.js carries the same rule as _gkSafeTarget(); keep them in step.
     */
    private static function safeTarget(string $href): ?string
    {
        $rein = preg_replace('/[\x00-\x20]/', '', $href) ?? '';
        if ($rein === '' || str_starts_with($rein, '//')) {
            return null;
        }
        if (preg_match('~^(/|\./|\.\./|\?|\#)~', $rein) === 1) {
            return $rein;
        }
        if (preg_match('~^(https?|mailto|tel):~i', $rein) === 1) {
            return $rein;
        }
        // A bare word with no scheme and no slash is a relative path too
        // ("edit", "users/7") — but anything with a colon in it is not.
        return str_contains($rein, ':') ? null : $rein;
    }

    /** SVG icons for table buttons — delegated to GridKit\Icon since v1.17.0 */
    private function iconSvg(string $name): string
    {
        return Icon::svg($name, 16, true);
    }

    /**
     * A percentage, the way a card and a cell both show it: the digits as given,
     * the locale's decimal sign, $decimals when asked, a space before the sign.
     * Nothing, a placeholder without a digit ("–") and a value that already ends
     * in % come back as they are; text with a digit that is not a number ("12,5")
     * only gets the sign. js/gridkit.js carries the same rule as _gkPercent() —
     * keep them in step. Halves: number_format() and toFixed() can round an exact
     * .5 differently once binary floats are involved (1.005 → "1,01" here, "1,00"
     * there) — a known hairline, not worth a second rounding routine.
     */
    public static function percent(mixed $val, ?int $decimals = null): string
    {
        $s = trim((string) ($val ?? ''));
        $s = $s === '-0' ? '0' : $s;   // (string) -0.0 — the client says "0"
        if ($s === '' || str_ends_with($s, '%') || preg_match('/\d/', $s) !== 1) {
            return $s;
        }
        if (!is_numeric($s)) {
            return $s . ' %';
        }
        $dec = Lang::t('format.decimal');
        if ($decimals !== null) {
            return number_format((float) $s, $decimals, $dec, Lang::t('format.thousands')) . ' %';
        }
        return str_replace('.', $dec, $s) . ' %';
    }

    /** A number with 'decimals'; 0 and empty turn into an em dash unless blankZero is off. */
    private function formatNumber(mixed $val, array $col): string
    {
        $blank = ($col['blankZero'] ?? true)
            && ($val === null || $val === '' || (is_numeric($val) && (float) $val == 0.0));
        if ($blank) {
            return '<span class="gk-num gk-num-empty">—</span>';
        }
        $decimals = (int) ($col['decimals'] ?? 0);
        $text = is_numeric($val)
            ? number_format((float) $val, $decimals,
                Lang::t('format.decimal'), Lang::t('format.thousands'))
            : (string) $val;
        return '<span class="gk-num">' . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span>';
    }

    /**
     * What goes inside a cell: the formatted value, optionally wrapped in a link,
     * optionally followed by a second line.
     *
     * Two patterns the SSI Panel wrote by hand 62 times across 17 views — "a name
     * that links somewhere" and "a name with a quiet second line under it" — each
     * time as a concatenated HTML string with its own escaping, passed through as
     * 'format' => 'html'. Such a column is neither searchable nor sortable without
     * a second key, and every one of those strings is an escaping decision made
     * again.
     *
     *     ->column('name', 'Customer', ['href' => '/customers/{id}', 'sub' => 'city'])
     *
     * The raw value stays what it was, so search and sort keep working.
     */
    private function cellContent(mixed $val, array $col, array $row, \Closure $e, ?array $rowTarget = null): string
    {
        $inhalt = $this->format($val, $col);

        // The row's own control (rowLink()) takes the place of a cell link:
        // one control per cell, never one inside another.
        if ($rowTarget !== null) {
            $inhalt = $rowTarget[0] . $inhalt . $rowTarget[1];
            $col['href'] = null;
        }
        // What the cell SHOWS decides, not the raw value: a number column with
        // blankZero renders an em dash for 0, and a link whose whole name is a
        // dash is a focus stop that says nothing.
        $sichtbar = trim(strip_tags($inhalt)) !== '' && trim(strip_tags($inhalt)) !== '—';

        if (isset($col['href']) && (string) $col['href'] !== '' && $sichtbar) {
            $ziel = self::fillTarget((string) $col['href'], $row);
            // 'html' means the caller writes the markup — including any links in
            // it. Wrapping that in another <a> produces nested anchors: the
            // browser closes the outer one at the inner, and the safe link keeps
            // only the text before it while the rest belongs to a foreign target.
            if (($col['format'] ?? '') === 'html') {
                $ziel = null;
                trigger_error("GridKit: a column cannot have both 'href' and 'format' => 'html' — the link was left out.", E_USER_WARNING);
            }
            if ($ziel !== null) {
                $inhalt = '<a href="' . $e($ziel) . '" class="gk-cell-link">' . $inhalt . '</a>';
            }
        }

        if (isset($col['sub'])) {
            // Always text, never markup, whatever the main cell's format is.
            $unter = trim((string) ($row[$col['sub']] ?? ''));
            if ($unter !== '') {
                $inhalt .= '<div class="gk-cell-sub">' . $e($unter) . '</div>';
            }
        }

        return $inhalt;
    }

    private function format(mixed $val, array $col): string
    {
        $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $fmt = $col['format'] ?? null;
        if ($fmt === null) return $e($val);

        return match ($fmt) {
            'currency' => $e(str_replace(
                '{value}',
                number_format((float) $val, 2,
                    Lang::t('format.decimal'), Lang::t('format.thousands')),
                $col['currency'] ?? Lang::t('format.currency')
            )),
            // The same rule StatCards follows: the digits as given, the locale's
            // decimal sign, 'decimals' when asked, a space before the sign. The
            // cell cut to a whole number and wrote "12%" under a card saying "12,5 %".
            'percent' => $e(self::percent($val, isset($col['decimals']) ? (int) $col['decimals'] : null)),
            // strtotime() takes a string and date() an int. A timestamp from the database is an int and
            // an unreadable date makes strtotime() answer false; both used to be coerced silently.
            'date' => $val
                ? $e(date($col['dateFormat'] ?? Lang::t('format.date'), (int) strtotime(is_scalar($val) ? (string) $val : $val)))
                : '',
            'datetime' => $val
                ? $e(date($col['dateFormat'] ?? Lang::t('format.datetime'), (int) strtotime(is_scalar($val) ? (string) $val : $val)))
                : '',
            'boolean' => (int)$val ? '<span class="gk-bool gk-bool-yes">✓</span>' : '<span class="gk-bool gk-bool-no">–</span>',
            // The empty guard the date formats two lines up already have. An
            // empty address produced <a href="mailto:"></a> — a focusable link
            // with no name that opens a blank composer, once per row without
            // an address, all of them in the tab order.
            'email' => ($val === null || $val === '')
                ? ''
                : '<a href="mailto:' . $e($val) . '">' . $e($val) . '</a>',
            'label' => $this->renderLabel($val, $col['labels'] ?? []),
            'html' => (string)$val,
            'number' => $this->formatNumber($val, $col),
            default => $e($val),
        };
    }

    /**
     * Set the text of the empty state. Without a call the table shows a
     * sensible default — and works out by itself whether there is no data at
     * all or whether only the current search comes up empty.
     *
     * @param array{title?:string,hint?:string,icon?:string,action?:string} $opts
     */
    public function emptyState(string $title = '', array $opts = []): static
    {
        if ($title !== '') $opts['title'] = $title;
        $this->emptyState = $opts + $this->emptyState;
        return $this;
    }

    /**
     * Is THIS view currently narrowed down by a search or a filter?
     *
     * gk_search is a page-wide parameter. Without the check against this
     * table's own search columns, a search in one of two tables on the same
     * page would make the other one report "no matches" and offer a
     * "reset filters" button — even though it is not being searched at all.
     * The same goes for filters: only the ones declared by this table
     * count.
     */
    private function isFiltered(): bool
    {
        if ($this->searchQuery !== '' && $this->searchCols) return true;
        foreach (array_keys($this->filters) as $col) {
            if (self::param('gk_filter_' . $col) !== '') return true;
        }
        return false;
    }

    /**
     * The empty state is the one users see most often — every time a filter
     * matches nothing. That is why it needs more than one grey sentence: a
     * statement, some context, and a way out.
     */
    /** The buttons bulkAction() added to the bulk bar, written like the delete button beside them. */
    private function renderBulkActions(\Closure $e): string
    {
        $html = '';
        foreach ($this->bulkActions as $name => $o) {
            $label = $o['label'] ?? null;
            if ($label === null) {
                $key = 'action.' . $name;
                $translated = Lang::t($key);
                $label = $translated !== $key ? $translated : ucfirst(str_replace(['_', '-'], ' ', (string) $name));
            }
            $confirm = '';
            if (!empty($o['confirm'])) {
                $question = is_string($o['confirm']) ? $o['confirm'] : Lang::t('table.confirm_action');
                $confirm = ' data-gk-confirm="' . $e($question) . '"' . (($o['color'] ?? '') === 'danger' ? ' data-gk-danger' : '');
            }
            $icon = !empty($o['icon']) ? '<span class="material-icons" aria-hidden="true">' . $e($o['icon']) . '</span> ' : '';
            $html .= '<button type="button" data-gk-bulk-action="' . $e($name) . '"' . $confirm . '>' . $icon . $e($label) . '</button>';
        }
        return $html;
    }

    /**
     * The control of densityToggle(): the segmented button (.gk-segmented, 1.99.0) with three
     * aria-pressed buttons, the pattern of the theme switcher. It is written `hidden` and with no
     * step pressed. Which step is on depends on the stored choice and on the page's density, and
     * only the script knows both: shown by the server, the control would claim a state it may not
     * have and offer three buttons that do nothing without the script. The script marks the step
     * and shows the group; with no script, or with an older js/gridkit.js, the control stays
     * hidden and the table looks as it does without the option.
     */
    private function renderDensityToggle(\Closure $e): string
    {
        $html = '<div class="gk-segmented" role="group" aria-label="' . $e(Lang::t('table.density')) . '" data-gk-density-toggle hidden>';
        foreach (['konsole' => 'table.density_compact', 'normal' => 'table.density_normal', 'weit' => 'table.density_roomy'] as $step => $key) {
            $html .= '<button type="button" class="gk-segmented-item" data-gk-set-density="' . $step . '" aria-pressed="false">'
                   . $e(Lang::t($key)) . '</button>';
        }
        return $html . '</div>';
    }

    private function renderEmpty(int $colspan): string
    {
        $e = fn($x) => htmlspecialchars((string)$x, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $filtered = $this->isFiltered();
        $empty = $this->emptyState;

        $icon  = $empty['icon']  ?? ($filtered ? 'search_off' : 'inbox');
        $title = $empty['title'] ?? Lang::t($filtered ? 'table.empty_filtered' : 'table.empty');
        $hint  = $empty['hint']  ?? Lang::t($filtered ? 'table.empty_filtered_hint' : 'table.empty_hint');

        // When the view is narrowed down, the way out is always the same and
        // is therefore offered on its own.
        $action = $empty['action'] ?? '';
        if ($action === '' && $filtered) {
            $action = '<button type="button" class="gk-btn gk-btn-text gk-btn-primary gk-btn-sm"'
                . ' data-gk-reset-filters="' . $e($this->id) . '">'
                . $e(Lang::t('table.reset_filters')) . '</button>';
        }

        $html = '<tr class="gk-empty-row"><td colspan="' . $colspan . '" class="gk-empty">'
              . '<div class="gk-empty-inner">';
        if ($icon !== '') {
            $html .= '<span class="material-icons gk-empty-icon" aria-hidden="true">' . $e($icon) . '</span>';
        }
        $html .= '<span class="gk-empty-title">' . $e($title) . '</span>';
        if ($hint !== '') $html .= '<span class="gk-empty-hint">' . $e($hint) . '</span>';
        if ($action !== '') $html .= '<span class="gk-empty-action">' . $action . '</span>';
        return $html . '</div></td></tr>';
    }

    /**
     * Which value gets which label colour. ONE list: it travels in the data
     * block, so the client uses this very table instead of a copy of it. The
     * copy it had was shorter — it knew no 'blue' and half the words — so a
     * status label turned from green to grey on the first sort.
     *
     * @return array<string,list<string>>
     */
    private static function labelColors(): array
    {
        // The list used to know only the German forms: 'active' and 'inactive'
        // both fell through to 'gray', which left the most important distinction
        // of a status column without a color.
        return [
            'green' => ['aktiv', 'active', 'bezahlt', 'paid', 'ja', 'yes', '1', 'true',
                        'gesendet', 'delivered', 'erledigt', 'done', 'abgeschlossen',
                        'completed', 'freigegeben', 'approved', 'online'],
            'orange' => ['offen', 'open', 'pending', 'entwurf', 'draft', 'warnung',
                         'warning', 'in bearbeitung', 'in progress', 'wartet', 'waiting',
                         'geprüft', 'review'],
            'red' => ['storniert', 'cancelled', 'canceled', 'überfällig', 'overdue',
                      'fehler', 'error', 'failed', 'fehlgeschlagen', 'abgelehnt', 'rejected'],
            'blue' => ['neu', 'new', 'info', 'geplant', 'scheduled'],
            'gray' => ['inaktiv', 'inactive', 'deaktiviert', 'disabled', 'archiviert',
                       'archived', 'gesperrt', 'blocked', '0', 'false', 'nein', 'no', 'offline'],
        ];
    }

    private function renderLabel(mixed $val, array $custom): string
    {
        $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        // The table is shared with the client now, so the lookup key has to be
        // too. strtolower() has been ASCII-only since PHP 8.2 and left "Ü"
        // standing, while JavaScript's toLowerCase() folds it: "Überfällig" was
        // grey on the server and red after the first sort. Same for the space —
        // PHP's trim() does not touch U+00A0, JavaScript's does.
        $v = self::labelKey((string) $val);
        $map = self::labelColors();
        // A `labels` entry is either a colour, as it has always been:
        //     'labels' => ['paid' => 'green']
        // or a colour together with the text to show, which is what a status
        // column needs in an application that runs in more than one language —
        // the stored value stays 'paid', the cell reads "bezahlt".
        //     'labels' => ['paid' => ['color' => 'green', 'text' => 'bezahlt']]
        $entry = $custom[$v] ?? null;
        $color = null;
        $text  = (string) $val;

        if (is_array($entry)) {
            $color = $entry['color'] ?? null;
            // array_key_exists, not ??: a 'text' => null in a table built from a
            // database column meant "no text", and the cell came out empty on one
            // side and with the raw value on the other.
            $text  = array_key_exists('text', $entry) && $entry['text'] !== null
                ? (string) $entry['text']
                : (string) $val;
        } elseif (is_string($entry) && $entry !== '') {
            $color = $entry;
        }

        if (!$color) {
            foreach ($map as $c => $vals) {
                if (in_array($v, $vals, true)) { $color = $c; break; }
            }
        }

        // ?: not ??: a 'color' => false or '' left the class as "gk-label-",
        // and the label lost every bit of its styling.
        return '<span class="gk-label gk-label-' . $e($color ?: 'gray') . '">'
             . $e($text) . '</span>';
    }

    /**
     * The key a label value is looked up by — the same one on both sides.
     * mb_strtolower where mbstring is there (it always is on the SSI servers),
     * and NBSP counts as space, which PHP's own trim() does not know.
     */
    private static function labelKey(string $val): string
    {
        $v = trim(str_replace("\xc2\xa0", ' ', $val));

        return function_exists('mb_strtolower') ? mb_strtolower($v, 'UTF-8') : strtolower($v);
    }

}
