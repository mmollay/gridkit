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
     * Setzt Fußzeilen-Zellen für die Tabelle.
     * Jede Zelle ist ein String oder ['text' => '...', 'align' => 'right', 'colspan' => 2, 'bold' => true]
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

    public function filter(string $column, string $type, array $opts = []): static
    {
        $this->filters[$column] = ['type' => $type, ...$opts];
        return $this;
    }

    private function loadData(): void
    {
        if (!$this->db || !$this->baseQuery) return;

        $sql = $this->baseQuery;
        $params = [];
        $types = '';

        // Search
        if ($this->searchQuery !== '' && $this->searchCols) {
            $clauses = [];
            foreach ($this->searchCols as $col) {
                $clauses[] = "`$col` LIKE ?";
                $params[] = '%' . $this->searchQuery . '%';
                $types .= 's';
            }
            $sql = "SELECT * FROM ($sql) AS _gk WHERE " . implode(' OR ', $clauses);
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

    public function render(): void
    {
        if ($this->db) $this->loadData();

        // AJAX request: return just the table body
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest'
            && ($_GET['gk_table'] ?? '') === $this->id) {
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
                'columns' => $colConfig,
                'buttons' => $this->buttons,
            ], JSON_UNESCAPED_UNICODE) . '</script>';
        }

        // Toolbar
        if ($this->showToolbar) {
        echo '<div class="gk-toolbar">';
        if ($this->searchCols) {
            echo '<input type="text" class="gk-search" data-gk-search placeholder="' . $e(Lang::t('table.search')) . '" value="' . $e($this->searchQuery) . '">';
        }
        if ($this->toolbarHtml !== '') {
            echo $this->toolbarHtml;
        }
        foreach ($this->filters as $col => $f) {
            echo '<select class="gk-filter" data-gk-filter="' . $e($col) . '">';
            echo '<option value="">' . $e($f['placeholder'] ?? Lang::t('table.filter_all')) . '</option>';
            foreach ($f['options'] ?? [] as $val => $label) {
                echo '<option value="' . $e($val) . '">' . $e($label) . '</option>';
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
               . '<span class="material-icons" style="font-size:18px;">check_box</span>'
               . '<span class="gk-bulk-count">0 ' . $e(Lang::t('table.selected', ['n' => ''])) . '</span>'
               . '<div class="gk-toolbar-spacer"></div>'
               . '<button type="button" data-gk-bulk-delete>'
               .   '<span class="material-icons">delete</span> ' . $e(Lang::t('table.delete'))
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
                $attrs = ' data-gk-sort="' . $e($key) . '" data-gk-dir="' . $newDir . '"';
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

        foreach ($this->rows as $row) {
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
                if (isset($col['align'])) $tdStyles[] = 'text-align:' . $e($col['align']);
                if (isset($col['width']) && $col['width'] !== 'auto') $tdStyles[] = 'width:' . $e($col['width']);
                if (isset($col['minWidth'])) $tdStyles[] = 'min-width:' . $e($col['minWidth']);
                if (isset($col['maxWidth'])) $tdStyles[] = 'max-width:' . $e($col['maxWidth']);
                if (!empty($col['nowrap'])) $tdStyles[] = 'white-space:nowrap';
                $tdStyle = $tdStyles ? ' style="' . implode(';', $tdStyles) . '"' : '';
                $tdClass = !empty($col['hideOnMobile']) ? ' class="gk-hide-mobile"' : '';
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
            echo "<tr><td colspan=\"{$colspan}\" class=\"gk-empty\">" . $e(Lang::t('table.empty')) . "</td></tr>";
        }

        echo '</tbody>';

        // Fußzeile: benutzerdefinierte Zellen oder Ladezeit
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
                // Restliche Spalten + Ladezeit
                $remaining = $totalCols - $usedCols;
                if ($remaining > 0 && $this->loadTimeMs !== null) {
                    $timeDisplay = $this->loadTimeMs < 1000 ? $this->loadTimeMs . ' ms' : number_format($this->loadTimeMs / 1000, 2, ',', '.') . ' s';
                    echo '<td colspan="' . $remaining . '" class="gk-table-meta">' . $timeDisplay . '</td>';
                } elseif ($remaining > 0) {
                    echo '<td colspan="' . $remaining . '"></td>';
                }
            } else {
                // Nur Ladezeit
                $timeDisplay = $this->loadTimeMs < 1000 ? $this->loadTimeMs . ' ms' : number_format($this->loadTimeMs / 1000, 2, ',', '.') . ' s';
                echo '<td colspan="' . $totalCols . '" class="gk-table-meta">'
                    . $e((string) $this->totalRows) . ' Einträge · ' . $timeDisplay
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
            for ($i = 1; $i <= $pages; $i++) {
                $isActive = $i === $this->currentPage;
                echo Button::render((string)$i, [
                    'variant' => $isActive ? 'tonal' : 'text',
                    'color' => $isActive ? 'primary' : 'neutral',
                    'size' => 'sm',
                    'shape' => 'pill',
                    'data' => ['gk-page' => $i],
                ]);
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
            // showIf: Button nur anzeigen wenn Row-Feld truthy ist
            if (isset($bopts["showIf"])) {
                $field = $bopts["showIf"];
                if (empty($row[$field])) continue;
            }
            // hideIf: Button verstecken wenn Row-Feld truthy ist
            if (isset($bopts["hideIf"])) {
                $field = $bopts["hideIf"];
                if (!empty($row[$field])) continue;
            }
            $params = [];
            foreach ($bopts['params'] ?? [] as $pkey => $pcol) {
                $params[$pkey] = $row[$pcol] ?? '';
            }

            // Map legacy 'class' option to Button color
            $colorMap = ['danger' => 'danger', 'success' => 'success', 'warning' => 'warning', 'primary' => 'primary'];
            $color = $colorMap[$bopts['class'] ?? ''] ?? 'neutral';

            // Data attributes
            $dataAttrs = ' data-gk-action="' . $e($bname) . '"'
                       . " data-gk-params='" . $e(json_encode($params)) . "'";
            if (isset($bopts['modal'])) {
                $dataAttrs .= ' data-gk-modal="' . $e($bopts['modal']) . '"';
            }
            $titleAttr = !empty($bopts['title']) ? ' title="' . $e($bopts['title']) . '"' : '';

            $hasText  = !empty($bopts['text']);
            $iconName = $bopts['icon'] ?? '';
            $iconHtml = $iconName ? $this->iconSvg($iconName) : '';

            if ($hasText && $iconHtml) {
                // Icon + Text button
                $cls = 'gk-btn gk-btn-icon-text gk-btn-text gk-btn-' . $color;
                echo '<button type="button" class="' . $cls . '"' . $titleAttr . $dataAttrs . '>'
                   . $iconHtml . '<span>' . $e($bopts['text']) . '</span></button>';
            } elseif ($iconHtml) {
                // Icon-only button (sm) — same classes as JS renderBtnGroup
                $cls = 'gk-btn gk-btn-icon-only gk-btn-text gk-btn-' . $color . ' gk-btn-sm';
                echo '<button type="button" class="' . $cls . '"' . $titleAttr . $dataAttrs . '>'
                   . $iconHtml . '</button>';
            }
        }
    }

    /** SVG icons for table buttons — mirrors JS GK.table.iconSvg() */
    private function iconSvg(string $name): string
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
                => '<span class="material-icons" style="font-size:16px;">' . htmlspecialchars($name, ENT_QUOTES) . '</span>',
        };
    }

    private function format(mixed $val, array $col): string
    {
        $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        $fmt = $col['format'] ?? null;
        if ($fmt === null) return $e($val);

        return match ($fmt) {
            'currency' => $e(number_format((float)$val, 2, ',', '.') . ' €'),
            'percent' => $e((int)$val . '%'),
            'date' => $val ? $e(date('d.m.Y', strtotime($val))) : '',
            'datetime' => $val ? $e(date('d.m.Y H:i', strtotime($val))) : '',
            'boolean' => (int)$val ? '<span class="gk-bool gk-bool-yes">✓</span>' : '<span class="gk-bool gk-bool-no">–</span>',
            'email' => '<a href="mailto:' . $e($val) . '">' . $e($val) . '</a>',
            'label' => $this->renderLabel($val, $col['labels'] ?? []),
            'html' => (string)$val,
            default => $e($val),
        };
    }

    private function renderLabel(mixed $val, array $custom): string
    {
        $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        $v = strtolower(trim((string)$val));
        $map = [
            'green' => ['aktiv', 'bezahlt', 'paid', 'ja', 'yes', '1', 'true', 'gesendet', 'delivered'],
            'orange' => ['offen', 'pending', 'entwurf', 'draft', 'warnung'],
            'red' => ['storniert', 'cancelled', 'überfällig', 'overdue', 'fehler', 'error'],
            'gray' => ['inaktiv', '0', 'false', 'nein', 'no'],
        ];
        $color = $custom[$v] ?? null;
        if (!$color) {
            foreach ($map as $c => $vals) {
                if (in_array($v, $vals)) { $color = $c; break; }
            }
        }
        $color = $color ?? 'gray';
        return '<span class="gk-label gk-label-' . $e($color) . '">' . $e($val) . '</span>';
    }

}
