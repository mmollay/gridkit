<?php
namespace GridKit;

use GridKit\Button;

class Table
{
    /** Formats that write a link of their own — no row control may wrap them. */
    private const ROW_LINK_REFUSED = ['html', 'email'];

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
    private string $mobileMode = 'card';
    private bool $selectable = false;
    private string $selectKey = 'id';
    private ?int $loadTimeMs = null;
    private array $footerCells = [];
    private string $groupCol = '';
    private string $caption = '';
    private array $groupLabels = [];
    /** What a row opens: ['href' => …] or ['sheet' => …, 'url' => …], plus 'column' and 'params'. */
    private array $rowLink = [];

    public function __construct(string $id)
    {
        $this->id = $id;
        $this->sortCol = self::param('gk_sort');
        $this->sortDir = ($_GET['gk_dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $this->currentPage = max(1, (int)($_GET['gk_page'] ?? 1));
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

    public function selectable(string $key = 'id'): static
    {
        $this->selectable = true;
        $this->selectKey  = $key;
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

        // AJAX reload of this table: emit the fragment only. Stopping the
        // page around it is the caller's job — see isAjaxReload().
        if (self::isAjaxReload($this->id)) {
            $this->renderInner();
            return;
        }

        $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $staticAttr    = $this->isStatic   ? ' data-gk-static'    : '';
        $selectAttr    = $this->selectable ? ' data-gk-selectable' : '';
        $wrapClasses   = 'gk-table-wrap';
        $wrapClasses  .= ' gk-table-' . $this->size;
        if ($this->variant !== 'default') $wrapClasses .= ' gk-table-' . $this->variant;
        if ($this->mobileMode === 'card') $wrapClasses .= ' gk-table-mobile-card';
        elseif ($this->mobileMode === 'scroll') $wrapClasses .= ' gk-table-mobile-scroll';
        echo '<div class="' . $wrapClasses . '" data-gk-table="' . $e($this->id) . '"' . $staticAttr . $selectAttr . '>';

        // Embed JSON data + column config for client-side operations
        if ($this->isStatic) {
            $colConfig = [];
            foreach ($this->columns as $key => $col) {
                $colConfig[$key] = $col;
            }
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
                'columns' => $colConfig,
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
                'buttons' => $this->buttons,
                // Rewritten by the client on every rebuild, like everything else here.
                'caption' => $this->caption,
                'groupBy' => $this->groupCol === '' ? null : [
                    'column' => $this->groupCol,
                    'labels' => $this->groupLabels,
                ],
                // rowLink(), with the column already resolved: the client must not
                // guess "the first column" from an object whose numeric-looking
                // keys JavaScript reorders.
                'rowLink' => $this->rowLinkColumn() === null ? null
                    : ['column' => $this->rowLinkColumn()] + $this->rowLink,
            // SUBSTITUTE: one malformed byte made json_encode() answer false, the
            // block came out empty and sort, search and paging died silently.
            // HEX_TAG: "<!--<script" inside a cell puts the HTML parser into
            // its double-escaped state and the block swallows the rest of the page.
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_HEX_TAG) . '</script>';
        }

        // Toolbar
        if ($this->showToolbar) {
        echo '<div class="gk-toolbar">';
        if ($this->searchCols) {
            // A placeholder is not an accessible name: it is not always
            // announced, and it disappears as soon as anything is typed.
            $searchLabel = $e(Lang::t('table.search'));
            echo '<input type="text" class="gk-search" data-gk-search'
               . ' placeholder="' . $searchLabel . '" aria-label="' . $searchLabel . '"'
               . ' value="' . $e($this->searchQuery) . '">';
        }
        if ($this->toolbarHtml !== '') {
            echo $this->toolbarHtml;
        }
        foreach ($this->filters as $col => $f) {
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
        if ($this->newBtnLabel) {
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
        echo '</div>';
        } // end toolbar

        if ($this->selectable) {
            echo '<div class="gk-bulk-bar" style="display:none;">'
               . '<span class="material-icons" style="font-size:18px;" aria-hidden="true">check_box</span>'
               . '<span class="gk-bulk-count">0 ' . $e(Lang::t('table.selected', ['n' => ''])) . '</span>'
               . '<div class="gk-toolbar-spacer"></div>'
               . '<button type="button" data-gk-bulk-delete>'
               .   '<span class="material-icons" aria-hidden="true">delete</span> ' . $e(Lang::t('table.delete'))
               . '</button>'
               . '<button type="button" data-gk-bulk-cancel>' . $e(Lang::t('table.cancel')) . '</button>'
               . '</div>';
        }

        $this->renderInner();

        // Modals
        foreach ($this->modals as $mid => $m) {
            echo '<template data-gk-modal-tpl="' . $e($mid) . '" data-gk-modal-title="' . $e($m['title']) . '" data-gk-modal-url="' . $e($m['url']) . '" data-gk-modal-size="' . $e($m['size'] ?? 'medium') . '"></template>';
        }

        echo '</div>';
    }

    private function renderInner(): void
    {
        $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $tableClass = 'gk-table' . ($this->globalNowrap ? ' gk-table-nowrap' : '');
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

        echo '<table class="' . $tableClass . '">'
           . ($this->caption !== '' ? '<caption class="gk-sr-only">' . $e($this->caption) . '</caption>' : '')
           . '<thead><tr>';
        if ($this->selectable) {
            // aria-label, not title alone: a title is announced inconsistently
            // and never on touch, so this control read as an unnamed checkbox.
            // scope="col" for the same reason as the headers below.
            echo '<th scope="col" class="gk-cb-col"><input type="checkbox" data-gk-select-all'
               . ' aria-label="' . $e(Lang::t('table.select_all')) . '"'
               . ' title="' . $e(Lang::t('table.select_all')) . '"></th>';
        }
        foreach ($this->columns as $key => $col) {
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
            // scope="col": without it a screen reader has to guess which
            // header a cell belongs to, and on a table with an action column or
            // a checkbox column it guesses wrong. This is the one attribute a
            // data table cannot do without.
            echo "<th scope=\"col\"{$cls}{$style}{$attrs}>" . $inner . "</th>";
        }
        $leftButtons = array_filter($this->buttons, fn($b) => ($b['position'] ?? 'right') === 'left');
        $rightButtons = array_filter($this->buttons, fn($b) => ($b['position'] ?? 'right') === 'right');
        // How wide a full-width row is. The group heading, the empty state and
        // the footer each used to work this out again from the same four
        // terms; a column kind added to one of the three would have left the
        // other two spanning the wrong width.
        $colCount = count($this->columns)
                  + ($leftButtons ? 1 : 0) + ($rightButtons ? 1 : 0) + ($this->selectable ? 1 : 0);
        // Empty header cells still need the scope, or the cells beneath them
        // inherit the association of the last real header to their left.
        if ($leftButtons) echo '<th scope="col" class="gk-actions-col"><span class="gk-sr-only">'
            . $e(Lang::t('table.actions')) . '</span></th>';
        if ($rightButtons) echo '<th scope="col" class="gk-actions-col"><span class="gk-sr-only">'
            . $e(Lang::t('table.actions')) . '</span></th>';
        echo '</tr></thead><tbody>';

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
            $rowId = $this->selectable ? $e($row[$this->selectKey] ?? '') : '';
            $rowIdAttr = $this->selectable ? ' data-gk-row-id="' . $rowId . '"' : '';
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
                // Secondary columns (numbers, identifiers) step back in text color
                // so that the actual name is what gets the attention.
                if (!empty($col['muted'])) $tdCls[] = 'gk-td-muted';
                $tdStyle = $tdStyles ? ' style="' . implode(';', $tdStyles) . '"' : '';
                $tdClass = $tdCls ? ' class="' . implode(' ', $tdCls) . '"' : '';
                $dataLabel = ' data-label="' . $e($col['label']) . '"';
                // (string): a key like '2024' is an integer in a PHP array.
                $formatted = $this->cellContent($val, $col, $row, $e, (string) $key === $linkKey ? $target : null);
                echo "<td{$tdClass}{$tdStyle}{$dataLabel}>{$formatted}</td>";
            }
            if ($rightButtons) {
                echo '<td class="gk-actions gk-actions-right"><div class="gk-btn-group">';
                $this->renderButtons($rightButtons, $row, $e);
                echo '</div></td>';
            }
            echo '</tr>';
        }

        // $visible, not $this->rows: a static table slices its own page here,
        // so asking for a page past the end left a <tbody> with no rows AND no
        // empty state — a table that simply stopped, with the pager above it
        // still offering the way back.
        if (!$visible) {
            echo $this->renderEmpty($colCount);
        }

        echo '</tbody>';

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

        echo '</table>';

        // Pagination
        if ($this->perPage > 0 && $this->totalRows > $this->perPage) {
            $pages = (int)ceil($this->totalRows / $this->perPage);
            // nav, so the pager is a landmark a screen reader can jump to and
            // skip past, instead of a run of unexplained numbers in the middle
            // of the table.
            echo '<nav class="gk-pagination" aria-label="' . $e(Lang::t('pagination.aria')) . '">';
            // Named from the catalogue. Without 'aria' Button::icon falls back
            // to the icon ligature, so this button announced itself as "Chevron
            // left" — the name of the glyph, not of what it does. Pagination.php
            // has always passed the label; this pager never did.
            echo Button::icon('chevron_left', [
                'variant' => 'text', 'color' => 'neutral', 'size' => 'sm',
                'aria' => Lang::t('pagination.prev'),
                'data' => ['gk-page' => max(1, $this->currentPage - 1)],
                'disabled' => $this->currentPage <= 1,
            ]);
            // A window around the current page, plus the first and the last —
            // the same shape Pagination uses. Printing every page put 400
            // buttons in the DOM for a 10,000-row list, on every reload, which
            // is precisely the size the server-side path exists for.
            $window = 2;
            $show = [1, $pages];
            for ($i = $this->currentPage - $window; $i <= $this->currentPage + $window; $i++) {
                if ($i >= 1 && $i <= $pages) $show[] = $i;
            }
            $show = array_values(array_unique($show));
            sort($show);

            $previous = 0;
            foreach ($show as $i) {
                if ($previous && $i - $previous > 1) {
                    echo '<span class="gk-pg-gap">…</span>';
                }
                $isActive = $i === $this->currentPage;
                // "3" on its own is not a name — it is a digit. The label says
                // which page, and aria-current marks the one you are on, which
                // was conveyed by colour alone.
                echo Button::render((string)$i, [
                    'variant' => $isActive ? 'tonal' : 'text',
                    'color' => $isActive ? 'primary' : 'neutral',
                    'size' => 'sm',
                    'shape' => 'pill',
                    'aria' => Lang::t('pagination.page_of', ['page' => $i, 'total' => $pages]),
                    'attrs' => $isActive ? ['aria-current' => 'page'] : [],
                    'data' => ['gk-page' => $i],
                ]);
                $previous = $i;
            }
            echo Button::icon('chevron_right', [
                'variant' => 'text', 'color' => 'neutral', 'size' => 'sm',
                'aria' => Lang::t('pagination.next'),
                'data' => ['gk-page' => min($pages, $this->currentPage + 1)],
                'disabled' => $this->currentPage >= $pages,
            ]);
            echo '</nav>';
        }
    }

    private function renderButtons(array $buttons, array $row, \Closure $e): void
    {
        foreach ($buttons as $bname => $bopts) {
            // showIf: only show the button when the row field is truthy
            if (isset($bopts["showIf"])) {
                $field = $bopts["showIf"];
                if (empty($row[$field])) continue;
            }
            // hideIf: hide the button when the row field is truthy
            if (isset($bopts["hideIf"])) {
                $field = $bopts["hideIf"];
                if (!empty($row[$field])) continue;
            }
            // Almost every row button needs to say which row it belongs to, and
            // forgetting `'params' => ['id' => 'id']` failed silently: the edit
            // modal opened as if it were a new record. The row's own id is sent
            // unless the caller mapped one itself.
            $params = self::rowParams($bopts['params'] ?? [], $row);

            // Every other component in GridKit names this option `color` —
            // Button::render(), ActionGroup items, StatCards. The row button
            // read `class` and nothing else, so the `'color' => 'danger'` in
            // the skill's own example produced a grey delete button. Both names
            // work; `color` is the one to use.
            $colorMap = ['danger' => 'danger', 'success' => 'success', 'warning' => 'warning', 'primary' => 'primary'];
            $colorName = $bopts['color'] ?? $bopts['class'] ?? '';
            $color = $colorMap[$colorName] ?? 'neutral';

            // `'confirm' => true` (or a message) asks before the button acts.
            // It was documented in the README and read by nothing at all, which
            // left the delete button in the headline example deleting without
            // asking — and, with neither onclick nor modal, doing nothing.
            $confirmAttr = '';
            $confirmMsg  = null;
            if (!empty($bopts['confirm'])) {
                $confirmMsg = is_string($bopts['confirm'])
                    ? $bopts['confirm']
                    : Lang::t('table.confirm_delete');
                $confirmAttr = ' data-gk-confirm="' . $e($confirmMsg) . '"';
            }

            // Data attributes
            $dataAttrs = ' data-gk-action="' . $e($bname) . '"'
                       . " data-gk-params='" . $e(json_encode($params)) . "'"
                       . $confirmAttr;
            if (isset($bopts['modal'])) {
                $dataAttrs .= ' data-gk-modal="' . $e($bopts['modal']) . '"';
            }
            // The accessible name. A row button is usually icon-only, so its
            // whole content is an <svg> — nothing a screen reader can read. It
            // announced as "button", six times over on a three-row table, and
            // one of those six deletes the record.
            //
            // title is not enough on its own: GK.tip moves it into
            // data-gk-tip on the first hover and removes the attribute, so a
            // control named only by its title goes silent as the pointer
            // crosses it. aria-label is the name; title stays for the tooltip.
            $actionName = $bopts['aria'] ?? $bopts['title'] ?? null;
            if ($actionName === null) {
                $key = 'action.' . $bname;
                $translated = Lang::t($key);
                // Lang::t returns the key when it knows nothing about it.
                $actionName = $translated !== $key
                    ? $translated
                    : ucfirst(str_replace(['_', '-'], ' ', $bname));
            }
            $ariaAttr  = ' aria-label="' . $e($actionName) . '"';
            $titleAttr = !empty($bopts['title']) ? ' title="' . $e($bopts['title']) . '"' : '';

            // 'href' => '/users/{id}' makes the row button a real link: middle
            // click, "open in new tab" and the browser's status bar work again.
            // Ten SSI Panel views pushed window.location.href into a <button>
            // instead, and none of them could be opened in a second tab.
            // 'modal' and 'onclick' keep what they had: a button that already
            // opens something must not turn into a link because someone added
            // href — it would lose its behaviour without a word.
            $href = null;
            if (isset($bopts['href']) && (string) $bopts['href'] !== ''
                && !isset($bopts['modal']) && empty($bopts['onclick'])) {
                $href = self::fillTarget((string) $bopts['href'], $row);
            }
            // A link carries no data-gk-action: the delegated handler would fire
            // gk:rowaction on top of the navigation. The confirmation stays — the
            // handler below asks before it follows the link.
            // A question before a link cannot live on an <a>: a middle click, a
            // Ctrl-click and Enter each take the native path, and a page whose
            // JavaScript failed to load would follow it without asking at all.
            // So a link WITH a confirmation is a button carrying its target.
            $fragtVorher = $href !== null && $confirmMsg !== null;
            if ($fragtVorher) {
                // No data-gk-action: going somewhere is not a row action, and the
                // delegated handler would ask its own question on top of this one.
                $dataAttrs = " data-gk-params='" . $e(json_encode($params)) . "'"
                    . $confirmAttr . ' data-gk-href="' . $e($href) . '"';
                $href = null;
            }
            $linkAttrs = $href === null ? '' : ' href="' . $e($href) . '"'
                . " data-gk-params='" . $e(json_encode($params)) . "'";
            $clickAttr = '';
            if (!empty($bopts['onclick'])) {
                $js = preg_replace_callback('/\{(\w+)\}/', static function ($m) use ($row) {
                    return json_encode($row[$m[1]] ?? null, JSON_UNESCAPED_UNICODE);
                }, (string) $bopts['onclick']);
                // An inline handler runs before any delegated listener could stop
                // it, so the confirmation has to wrap the code itself.
                if ($confirmMsg !== null) {
                    $js = 'GK.confirm(' . json_encode($confirmMsg, JSON_UNESCAPED_UNICODE)
                        . ',{danger:true}).then(function(ok){if(ok){' . $js . '}})';
                }
                $clickAttr = ' onclick="' . $e($js) . '"';
            }

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
                if ($href !== null) {
                    echo '<a class="' . $cls . '"' . $linkAttrs . $titleAttr . '>'
                       . $iconHtml . '<span>' . $e($bopts['text']) . '</span></a>';
                } else {
                    echo '<button type="button" class="' . $cls . '"' . $titleAttr . $clickAttr . $dataAttrs . '>'
                       . $iconHtml . '<span>' . $e($bopts['text']) . '</span></button>';
                }
            } elseif ($iconHtml) {
                // Icon-only button (sm) — same classes as JS renderBtnGroup
                // aria-label only here. The icon+text branch above is named by
                // its visible text, and an aria-label would override that —
                // which also breaks activating the control by speaking its
                // visible name.
                $cls = 'gk-btn gk-btn-icon-only gk-btn-text gk-btn-' . $color . ' gk-btn-sm';
                if ($href !== null) {
                    echo '<a class="' . $cls . '"' . $linkAttrs . $ariaAttr . $titleAttr . '>' . $iconHtml . '</a>';
                } else {
                    echo '<button type="button" class="' . $cls . '"' . $ariaAttr . $titleAttr . $clickAttr . $dataAttrs . '>'
                       . $iconHtml . '</button>';
                }
            }
        }
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
            'date' => $val
                ? $e(date($col['dateFormat'] ?? Lang::t('format.date'), strtotime($val)))
                : '',
            'datetime' => $val
                ? $e(date($col['dateFormat'] ?? Lang::t('format.datetime'), strtotime($val)))
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
