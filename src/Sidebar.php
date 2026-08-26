<?php
declare(strict_types=1);

namespace GridKit;

class Sidebar
{
    private string $id;
    private string $brand = '';
    private string $brandIcon = '';
    private string $version = '';
    private array $items = [];
    private array $groups = [];
    private string $currentGroup = '';
    private string $collapsePosition = 'bottom';
    private bool $headerOffset = false;
    private bool $ajaxNavEnabled = false;

    public function __construct(string $id = 'main')
    {
        $this->id = $id;
    }

    public function brand(string $title, string $icon = 'dashboard', string $version = ''): self
    {
        $this->brand = $title;
        $this->brandIcon = $icon;
        $this->version = $version;
        return $this;
    }

    /** Set collapse button position: 'top' (in brand) or 'bottom' (after nav). Default: 'bottom' */
    public function collapsePosition(string $position): self
    {
        $this->collapsePosition = $position;
        return $this;
    }

    public function headerOffset(bool $enabled = true): self
    {
        $this->headerOffset = $enabled;
        return $this;
    }

    /** Enable AJAX navigation — sidebar links load content via fetch() without full-page reload */
    public function ajaxNav(bool $enabled = true): self
    {
        $this->ajaxNavEnabled = $enabled;
        return $this;
    }

    public function group(string $label): self
    {
        $this->currentGroup = $label;
        if (!isset($this->groups[$label])) {
            $this->groups[$label] = [];
        }
        return $this;
    }

    public function item(string $label, string $href, string $icon = '', array $opts = []): self
    {
        $item = [
            'label' => $label,
            'href' => $href,
            'icon' => $icon,
            'active' => $opts['active'] ?? false,
            'badge' => $opts['badge'] ?? null,
            'children' => $opts['children'] ?? [],
            'id' => $opts['id'] ?? '',
        ];
        if ($this->currentGroup !== '') {
            $this->groups[$this->currentGroup][] = $item;
        } else {
            $this->items[] = $item;
        }
        return $this;
    }

    public function divider(): self
    {
        $divider = ['type' => 'divider'];
        if ($this->currentGroup !== '') {
            $this->groups[$this->currentGroup][] = $divider;
        } else {
            $this->items[] = $divider;
        }
        return $this;
    }

    public function render(): void
    {
        $this->usedIds = [];
        $e = fn(string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        echo '<div class="gk-sidebar-overlay" data-gk-sidebar-overlay onclick="GK.sidebar.close()"></div>';
        $sidebarCls = 'gk-sidebar';
        if ($this->headerOffset) $sidebarCls .= ' gk-sidebar-with-header';
        $ajaxAttr = $this->ajaxNavEnabled ? ' data-gk-ajax-nav' : '';
        echo '<aside class="' . $sidebarCls . '" data-gk-sidebar="' . $e($this->id) . '"' . $ajaxAttr . '>';

        // Brand
        if ($this->brand !== '') {
            echo '<div class="gk-sidebar-brand">';
            if ($this->collapsePosition === 'top') {
                echo '<button class="gk-sidebar-collapse-btn" aria-label="' . $e(Lang::t('sidebar.collapse')) . '" onclick="window.GK&&GK.sidebar.collapse()" title="' . $e(Lang::t('sidebar.toggle')) . '">';
                echo '<span class="material-icons" aria-hidden="true">menu</span>';
                echo '</button>';
            }
            if ($this->brandIcon) {
                echo '<span class="material-icons gk-sidebar-brand-icon" aria-hidden="true">' . $e($this->brandIcon) . '</span>';
            }
            echo '<div class="gk-sidebar-brand-text">';
            echo '<span class="gk-sidebar-brand-title">' . $e($this->brand) . '</span>';
            if ($this->version !== '') {
                echo '<span class="gk-sidebar-brand-version">' . $e($this->version) . '</span>';
            }
            echo '</div>';
            // The glyph is hidden from the accessibility tree, as decoration
            // should be — which leaves the button with nothing to be named by
            // unless it says so itself. It read as "close" before, by accident
            // of the ligature.
            $closeLabel = $e(Lang::t('sidebar.close'));
            echo '<button class="gk-sidebar-close-mobile" aria-label="' . $closeLabel . '" title="' . $closeLabel . '" onclick="window.GK&&GK.sidebar.close()">';
            echo '<span class="material-icons" aria-hidden="true">close</span>';
            echo '</button>';
            echo '</div>';
        }

        // Nav
        echo '<nav class="gk-sidebar-nav">';

        // Ungrouped items
        foreach ($this->items as $item) {
            if (isset($item['type']) && $item['type'] === 'divider') {
                echo '<div class="gk-sidebar-divider"></div>';
                continue;
            }
            $this->renderItem($item, $e);
        }

        // Grouped items
        foreach ($this->groups as $label => $items) {
            // The label is an array key, so PHP casts a decimal string to int:
            // ->group('2024') arrives here as the integer 2024, and under
            // strict_types the escaper's string parameter makes that a fatal
            // TypeError — mid-output, leaving <aside> and <nav> unclosed and
            // the rest of the page unrendered. A year is an ordinary heading.
            echo '<div class="gk-sidebar-group-label">' . $e((string) $label) . '</div>';
            foreach ($items as $item) {
                if (isset($item['type']) && $item['type'] === 'divider') {
                    echo '<div class="gk-sidebar-divider"></div>';
                    continue;
                }
                $this->renderItem($item, $e);
            }
        }

        echo '</nav>';

        // Collapse button (bottom)
        if ($this->collapsePosition === 'bottom') {
            echo '<button class="gk-sidebar-collapse-btn gk-sidebar-collapse-bottom" aria-label="' . $e(Lang::t('sidebar.collapse')) . '" onclick="window.GK&&GK.sidebar.collapse()" title="' . $e(Lang::t('sidebar.toggle')) . '">';
            echo '<span class="material-icons" aria-hidden="true">chevron_left</span>';
            echo '<span class="gk-sidebar-collapse-label">' . $e(Lang::t('sidebar.collapse')) . '</span>';
            echo '</button>';
        }

        echo '</aside>';
    }

    /** Submenu ids handed out during the current render(), so none repeats. */
    private array $usedIds = [];

    private function renderItem(array $item, \Closure $e): void
    {
        $hasChildren = !empty($item['children']);

        if ($hasChildren) {
            $childActive = false;
            foreach ($item['children'] as $child) {
                if ($child['active'] ?? false) { $childActive = true; break; }
            }
            $isOpen = $item['active'] || $childActive;
            // The default id was md5(label). Two collapsible items sharing a
            // label — "Monthly" under Sales and under Purchases — produced the
            // same id twice, and since getElementById returns the first match,
            // clicking the second toggle opened the first submenu. A suffix
            // only appears from the second collision on, so existing pages
            // with unique labels keep the ids they already had.
            $groupId = $item['id'] ?: 'gk-sub-' . md5($item['label']);
            if ($item['id'] === '' || $item['id'] === null) {
                $seen = $this->usedIds[$groupId] ?? 0;
                $this->usedIds[$groupId] = $seen + 1;
                if ($seen > 0) $groupId .= '-' . $seen;
            }

            echo '<div class="gk-sidebar-group">';
            echo '<button class="gk-sidebar-item gk-sidebar-group-toggle' . ($isOpen ? ' active' : '') . '" data-gk-toggle="' . $e($groupId) . '" data-label="' . $e($item['label']) . '">';
            if ($item['icon'] !== '') {
                echo '<span class="material-icons gk-sidebar-icon" aria-hidden="true">' . $e($item['icon']) . '</span>';
            }
            echo '<span class="gk-sidebar-label">' . $e($item['label']) . '</span>';
            echo '<span class="material-icons gk-sidebar-chevron" aria-hidden="true">expand_more</span>';
            echo '</button>';
            echo '<div class="gk-sidebar-subitems' . ($isOpen ? '' : ' collapsed') . '" id="' . $e($groupId) . '">';
            foreach ($item['children'] as $child) {
                $cls = 'gk-sidebar-subitem';
                if ($child['active'] ?? false) $cls .= ' active';
                echo '<a href="' . $e($child['href'] ?? '#') . '" class="' . $cls . '" data-label="' . $e($child['label'] ?? '') . '">';
                if (!empty($child['icon'])) {
                    echo '<span class="material-icons gk-sidebar-icon" aria-hidden="true">' . $e($child['icon']) . '</span>';
                }
                echo '<span class="gk-sidebar-label">' . $e($child['label'] ?? '') . '</span>';
                echo '</a>';
            }
            echo '</div>';
            echo '</div>';
        } else {
            $cls = 'gk-sidebar-item';
            if ($item['active']) $cls .= ' active';
            echo '<a href="' . $e($item['href']) . '" class="' . $cls . '" data-label="' . $e($item['label']) . '">';
            if ($item['icon'] !== '') {
                echo '<span class="material-icons gk-sidebar-icon" aria-hidden="true">' . $e($item['icon']) . '</span>';
            }
            echo '<span class="gk-sidebar-label">' . $e($item['label']) . '</span>';
            if ($item['badge'] !== null) {
                echo '<span class="gk-sidebar-badge">' . $e((string)$item['badge']) . '</span>';
            }
            echo '</a>';
        }
    }

    /** Render the mobile toggle button (place in your header) */
    public static function toggleButton(): void
    {
        $openLabel = htmlspecialchars(Lang::t('sidebar.open'), ENT_QUOTES, 'UTF-8');
        echo '<button class="gk-sidebar-toggle" aria-label="' . $openLabel . '" title="' . $openLabel . '" onclick="GK.sidebar.toggle()">';
        echo '<span class="material-icons" aria-hidden="true">menu</span>';
        echo '</button>';
    }
}
