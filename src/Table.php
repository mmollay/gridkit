<?php
namespace GridKit;

class Table
{
    private string $id;
    private array $columns = [];
    private array $buttons = [];
    private array $modals = [];
    private array $rows = [];
    private array $searchCols = [];
    private array $filters = [];
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

    public function paginate(int|bool $perPage): static
    {
        $this->perPage = (int)$perPage;
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
        $staticAttr = $this->isStatic ? ' data-gk-static' : '';
        echo '<div class="gk-root gk-table-wrap" data-gk-table="' . $e($this->id) . '"' . $staticAttr . '>';

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
        echo '<div class="gk-toolbar">';
        if ($this->searchCols) {
            echo '<input type="text" class="gk-search" data-gk-search placeholder="Suchen…" value="' . $e($this->searchQuery) . '">';
        }
        foreach ($this->filters as $col => $f) {
            echo '<select class="gk-filter" data-gk-filter="' . $e($col) . '">';
            echo '<option value="">' . $e($f['placeholder'] ?? 'Alle') . '</option>';
            foreach ($f['options'] ?? [] as $val => $label) {
                echo '<option value="' . $e($val) . '">' . $e($label) . '</option>';
            }
            echo '</select>';
        }
        if ($this->newBtnLabel) {
            echo '<div class="gk-toolbar-spacer"></div>';
            $modal = $e($this->newBtnOpts['modal'] ?? '');
            echo '<button class="gk-btn gk-btn-primary" data-gk-modal="' . $modal . '">' . $e($this->newBtnLabel) . '</button>';
        }
        echo '</div>';

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

        echo '<table class="gk-table"><thead><tr>';
        foreach ($this->columns as $key => $col) {
            $style = isset($col['width']) ? ' style="width:' . $e($col['width']) . '"' : '';
            $sortable = $col['sortable'] ?? false;
            $cls = $sortable ? ' class="gk-sortable"' : '';
            $attrs = '';
            if ($sortable) {
                $newDir = ($this->sortCol === $key && $this->sortDir === 'asc') ? 'desc' : 'asc';
                $attrs = ' data-gk-sort="' . $e($key) . '" data-gk-dir="' . $newDir . '"';
                if ($this->sortCol === $key) {
                    $cls = ' class="gk-sortable gk-sorted-' . $this->sortDir . '"';
                }
            }
            echo "<th{$cls}{$style}{$attrs}>" . $e($col['label']) . "</th>";
        }
        $leftButtons = array_filter($this->buttons, fn($b) => ($b['position'] ?? 'right') === 'left');
        $rightButtons = array_filter($this->buttons, fn($b) => ($b['position'] ?? 'right') === 'right');
        if ($leftButtons) echo '<th class="gk-actions-col"></th>';
        if ($rightButtons) echo '<th class="gk-actions-col"></th>';
        echo '</tr></thead><tbody>';

        foreach ($this->rows as $row) {
            echo '<tr>';
            if ($leftButtons) {
                echo '<td class="gk-actions gk-actions-left"><div class="gk-btn-group">';
                $this->renderButtons($leftButtons, $row, $e);
                echo '</div></td>';
            }
            foreach ($this->columns as $key => $col) {
                $val = $row[$key] ?? '';
                $align = isset($col['align']) ? ' style="text-align:' . $e($col['align']) . '"' : '';
                $formatted = $this->format($val, $col);
                echo "<td{$align}>{$formatted}</td>";
            }
            if ($rightButtons) {
                echo '<td class="gk-actions gk-actions-right"><div class="gk-btn-group">';
                $this->renderButtons($rightButtons, $row, $e);
                echo '</div></td>';
            }
            echo '</tr>';
        }

        if (!$this->rows) {
            $colspan = count($this->columns) + ($leftButtons ? 1 : 0) + ($rightButtons ? 1 : 0);
            echo "<tr><td colspan=\"{$colspan}\" class=\"gk-empty\">Keine Einträge gefunden</td></tr>";
        }

        echo '</tbody></table>';

        // Pagination
        if ($this->perPage > 0 && $this->totalRows > $this->perPage) {
            $pages = (int)ceil($this->totalRows / $this->perPage);
            echo '<div class="gk-pagination">';
            for ($i = 1; $i <= $pages; $i++) {
                $active = $i === $this->currentPage ? ' gk-active' : '';
                echo '<button class="gk-page-btn' . $active . '" data-gk-page="' . $i . '">' . $i . '</button>';
            }
            echo '</div>';
        }
    }

    private function renderButtons(array $buttons, array $row, \Closure $e): void
    {
        foreach ($buttons as $bname => $bopts) {
            $hasText = !empty($bopts['text']);
            $cls = $hasText ? 'gk-btn gk-btn-icon-text' : 'gk-btn gk-btn-icon';
            if (isset($bopts['class'])) $cls .= ' gk-btn-' . $bopts['class'];
            $params = [];
            foreach ($bopts['params'] ?? [] as $pkey => $pcol) {
                $params[$pkey] = $row[$pcol] ?? '';
            }
            $attrs = ' data-gk-action="' . $e($bname) . '"';
            if (isset($bopts['modal'])) {
                $attrs .= ' data-gk-modal="' . $e($bopts['modal']) . '"';
            }
            if (isset($bopts['title'])) {
                $attrs .= ' title="' . $e($bopts['title']) . '"';
            }
            $attrs .= " data-gk-params='" . $e(json_encode($params)) . "'";
            $icon = isset($bopts['icon']) ? $this->icon($bopts['icon']) : '';
            $text = $hasText ? '<span>' . $e($bopts['text']) . '</span>' : '';
            echo "<button class=\"{$cls}\"{$attrs}>{$icon}{$text}</button>";
        }
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

    private function icon(string $name): string
    {
        return match ($name) {
            'pencil', 'edit' => '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 3a2.85 2.85 0 0 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>',
            'trash', 'delete' => '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6h14Z"/></svg>',
            'plus' => '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>',
            default => '<span class="material-icons" style="font-size:16px;vertical-align:middle;">' . htmlspecialchars($name) . '</span>',
        };
    }
}
