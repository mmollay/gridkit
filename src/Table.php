<?php
namespace GridKit;

use GridKit\Button;

class Table
{
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
    private array $groupLabels = [];

    public function __construct(string $id)
    {
        $this->id = $id;
        $this->sortCol = $_GET['gk_sort'] ?? '';
        $this->sortDir = ($_GET['gk_dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $this->currentPage = max(1, (int)($_GET['gk_page'] ?? 1));
        $this->searchQuery = trim($_GET['gk_search'] ?? '');
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
            $value = (string) ($_GET['gk_filter_' . $col] ?? '');
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

        $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
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
                // The keys search() was told to use. Without them the client
                // fell back to every rendered column, so a declared search key
                // that is not itself a column was silently never searched —
                // and the markup of an HTML column matched instead.
                'search'  => array_values($this->searchCols),
                'buttons' => $this->buttons,
                'groupBy' => $this->groupCol === '' ? null : [
                    'column' => $this->groupCol,
                    'labels' => $this->groupLabels,
                ],
            ], JSON_UNESCAPED_UNICODE) . '</script>';
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
            $active = (string) ($_GET['gk_filter_' . $col] ?? '');
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
        $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

        $tableClass = 'gk-table' . ($this->globalNowrap ? ' gk-table-nowrap' : '');
        echo '<table class="' . $tableClass . '"><thead><tr>';
        if ($this->selectable) {
            echo '<th class="gk-cb-col"><input type="checkbox" data-gk-select-all title="' . $e(Lang::t('table.select_all')) . '"></th>';
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
            $attrs = '';
            if ($sortable) {
                $newDir = ($this->sortCol === $key && $this->sortDir === 'asc') ? 'desc' : 'asc';
                // A sortable header is a control, so it has to behave like one:
                // reachable by Tab, announced as a button, and reporting the
                // direction it is currently sorted in. Without tabindex there
                // was no way to sort the table without a mouse at all.
                $ariaSort = $this->sortCol === $key
                    ? ($this->sortDir === 'asc' ? 'ascending' : 'descending')
                    : 'none';
                $attrs = ' data-gk-sort="' . $e($key) . '" data-gk-dir="' . $newDir . '"'
                       . ' tabindex="0" role="button" aria-sort="' . $ariaSort . '"';
                if ($this->sortCol === $key) {
                    $clsList[] = 'gk-sorted-' . $this->sortDir;
                }
            }
            $cls = $clsList ? ' class="' . implode(' ', $clsList) . '"' : '';
            echo "<th{$cls}{$style}{$attrs}>" . $e($col['label']) . "</th>";
        }
        $leftButtons = array_filter($this->buttons, fn($b) => ($b['position'] ?? 'right') === 'left');
        $rightButtons = array_filter($this->buttons, fn($b) => ($b['position'] ?? 'right') === 'right');
        if ($leftButtons) echo '<th class="gk-actions-col"></th>';
        if ($rightButtons) echo '<th class="gk-actions-col"></th>';
        echo '</tr></thead><tbody>';

        $groupCounts = [];
        if ($this->groupCol !== '') {
            foreach ($this->rows as $r) {
                $gk = (string) ($r[$this->groupCol] ?? '');
                $groupCounts[$gk] = ($groupCounts[$gk] ?? 0) + 1;
            }
        }
        $groupSpan = count($this->columns) + ($leftButtons ? 1 : 0) + ($rightButtons ? 1 : 0) + ($this->selectable ? 1 : 0);
        $lastGroup = null;

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
                    echo '<tr class="gk-table-group"><td colspan="' . $groupSpan . '">'
                       . '<span class="gk-table-group-name">' . $e($gLabel) . '</span>'
                       . '<span class="gk-table-group-n">' . (int) ($groupCounts[$gk] ?? 0) . '</span>'
                       . '</td></tr>';
                    $lastGroup = $gk;
                }
            }
            $rowId = $this->selectable ? $e($row[$this->selectKey] ?? '') : '';
            $rowIdAttr = $this->selectable ? ' data-gk-row-id="' . $rowId . '"' : '';
            echo '<tr' . $rowIdAttr . '>';
            if ($this->selectable) {
                echo '<td class="gk-cb-col"><input type="checkbox" value="' . $rowId . '"></td>';
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
                $formatted = $this->format($val, $col);
                echo "<td{$tdClass}{$tdStyle}{$dataLabel}>{$formatted}</td>";
            }
            if ($rightButtons) {
                echo '<td class="gk-actions gk-actions-right"><div class="gk-btn-group">';
                $this->renderButtons($rightButtons, $row, $e);
                echo '</div></td>';
            }
            echo '</tr>';
        }

        if (!$this->rows) {
            $colspan = count($this->columns) + ($leftButtons ? 1 : 0) + ($rightButtons ? 1 : 0) + ($this->selectable ? 1 : 0);
            echo $this->renderEmpty($colspan);
        }

        echo '</tbody>';

        // Footer: custom cells or load time
        if ($this->footerCells || $this->loadTimeMs !== null) {
            $totalCols = count($this->columns) + ($leftButtons ? 1 : 0) + ($rightButtons ? 1 : 0) + ($this->selectable ? 1 : 0);
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
                    echo '<td colspan="' . $colspan . '" style="' . $style . '">' . ($cell['text'] ?? '') . '</td>';
                    $usedCols += $colspan;
                }
                // Remaining columns + load time
                $remaining = $totalCols - $usedCols;
                if ($remaining > 0 && $this->loadTimeMs !== null) {
                    $timeDisplay = $this->loadTimeMs < 1000 ? $this->loadTimeMs . ' ms' : number_format($this->loadTimeMs / 1000, 2, Lang::t('format.decimal'), Lang::t('format.thousands')) . ' s';
                    echo '<td colspan="' . $remaining . '" class="gk-table-meta">' . $timeDisplay . '</td>';
                } elseif ($remaining > 0) {
                    echo '<td colspan="' . $remaining . '"></td>';
                }
            } else {
                // Load time only
                $timeDisplay = $this->loadTimeMs < 1000 ? $this->loadTimeMs . ' ms' : number_format($this->loadTimeMs / 1000, 2, Lang::t('format.decimal'), Lang::t('format.thousands')) . ' s';
                echo '<td colspan="' . $totalCols . '" class="gk-table-meta">'
                    . $e((string) $this->totalRows) . ' ' . $e(Lang::t('pagination.entries')) . ' · ' . $timeDisplay
                    . '</td>';
            }

            echo '</tr></tfoot>';
        }

        echo '</table>';

        // Pagination
        if ($this->perPage > 0 && $this->totalRows > $this->perPage) {
            $pages = (int)ceil($this->totalRows / $this->perPage);
            echo '<div class="gk-pagination">';
            echo Button::icon('chevron_left', [
                'variant' => 'text', 'color' => 'neutral', 'size' => 'sm',
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
                echo Button::render((string)$i, [
                    'variant' => $isActive ? 'tonal' : 'text',
                    'color' => $isActive ? 'primary' : 'neutral',
                    'size' => 'sm',
                    'shape' => 'pill',
                    'data' => ['gk-page' => $i],
                ]);
                $previous = $i;
            }
            echo Button::icon('chevron_right', [
                'variant' => 'text', 'color' => 'neutral', 'size' => 'sm',
                'data' => ['gk-page' => min($pages, $this->currentPage + 1)],
                'disabled' => $this->currentPage >= $pages,
            ]);
            echo '</div>';
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
            $params = [];
            foreach ($bopts['params'] ?? [] as $pkey => $pcol) {
                $params[$pkey] = $row[$pcol] ?? '';
            }
            // Almost every row button needs to say which row it belongs to, and
            // forgetting `'params' => ['id' => 'id']` failed silently: the edit
            // modal opened as if it were a new record. The row's own id is sent
            // unless the caller mapped one itself.
            if (!array_key_exists('id', $params) && isset($row['id'])) {
                $params['id'] = $row['id'];
            }

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

            if ($hasText && $iconHtml) {
                // Icon + Text button
                $cls = 'gk-btn gk-btn-icon-text gk-btn-text gk-btn-' . $color;
                echo '<button type="button" class="' . $cls . '"' . $titleAttr . $clickAttr . $dataAttrs . '>'
                   . $iconHtml . '<span>' . $e($bopts['text']) . '</span></button>';
            } elseif ($iconHtml) {
                // Icon-only button (sm) — same classes as JS renderBtnGroup
                // aria-label only here. The icon+text branch above is named by
                // its visible text, and an aria-label would override that —
                // which also breaks activating the control by speaking its
                // visible name.
                $cls = 'gk-btn gk-btn-icon-only gk-btn-text gk-btn-' . $color . ' gk-btn-sm';
                echo '<button type="button" class="' . $cls . '"' . $ariaAttr . $titleAttr . $clickAttr . $dataAttrs . '>'
                   . $iconHtml . '</button>';
            }
        }
    }

    /** SVG icons for table buttons — delegated to GridKit\Icon since v1.17.0 */
    private function iconSvg(string $name): string
    {
        return Icon::svg($name, 16, true);
    }

    /** @deprecated kept only as a backup in case Icon::svg ever differs in future */
    private function iconSvgLegacy(string $name): string
    {
        $s = 'viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"';
        return match ($name) {
            'pencil', 'edit'
                => '<svg ' . $s . '><path d="M17 3a2.85 2.85 0 0 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>',
            'trash', 'delete'
                => '<svg ' . $s . '><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6h14Z"/></svg>',
            'plus', 'add'
                => '<svg ' . $s . '><path d="M12 5v14M5 12h14"/></svg>',
            'eye', 'visibility'
                => '<svg ' . $s . '><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>',
            'download'
                => '<svg ' . $s . '><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>',
            'upload'
                => '<svg ' . $s . '><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"/></svg>',
            'copy', 'content_copy'
                => '<svg ' . $s . '><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>',
            'mail', 'email'
                => '<svg ' . $s . '><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>',
            'search'
                => '<svg ' . $s . '><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>',
            'settings'
                => '<svg ' . $s . '><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
            'open_in_new', 'external'
                => '<svg ' . $s . '><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15,3 21,3 21,9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>',
            'auto_awesome', 'generate', 'wand'
                => '<svg ' . $s . '><path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/><path d="M5 3v4M19 17v4M3 5h4M17 19h4"/></svg>',
            'login', 'impersonate'
                => '<svg ' . $s . '><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10,17 15,12 10,7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>',
            'print'
                => '<svg ' . $s . '><polyline points="6,9 6,2 18,2 18,9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>',
            default
                => '<span class="material-icons" style="font-size:16px;" aria-hidden="true">' . htmlspecialchars($name, ENT_QUOTES) . '</span>',
        };
    }

    /** Whole numbers; 0 and empty turn into an em dash (count columns). */
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
        return '<span class="gk-num">' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</span>';
    }

    private function format(mixed $val, array $col): string
    {
        $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        $fmt = $col['format'] ?? null;
        if ($fmt === null) return $e($val);

        return match ($fmt) {
            'currency' => $e(str_replace(
                '{value}',
                number_format((float) $val, 2,
                    Lang::t('format.decimal'), Lang::t('format.thousands')),
                $col['currency'] ?? Lang::t('format.currency')
            )),
            'percent' => $e((int)$val . '%'),
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
            if (($_GET['gk_filter_' . $col] ?? '') !== '') return true;
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
        $e = fn($x) => htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8');
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

    private function renderLabel(mixed $val, array $custom): string
    {
        $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        $v = strtolower(trim((string)$val));
        // The list used to know only the German forms: 'active' and 'inactive'
        // both fell through to 'gray', which left the most important distinction
        // of a status column without a color.
        $map = [
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
            $text  = (string) ($entry['text'] ?? $val);
        } elseif (is_string($entry) && $entry !== '') {
            $color = $entry;
        }

        if (!$color) {
            foreach ($map as $c => $vals) {
                if (in_array($v, $vals, true)) { $color = $c; break; }
            }
        }

        return '<span class="gk-label gk-label-' . $e($color ?? 'gray') . '">'
             . $e($text) . '</span>';
    }

}
