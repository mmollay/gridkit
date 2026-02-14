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
        $e = fn(string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        echo '<div class="gk-sidebar-overlay" data-gk-sidebar-overlay onclick="GK.sidebar.close()"></div>';
        echo '<aside class="gk-sidebar" data-gk-sidebar="' . $e($this->id) . '">';

        // Brand
        if ($this->brand !== '') {
            echo '<div class="gk-sidebar-brand">';
            if ($this->collapsePosition === 'top') {
                echo '<button class="gk-sidebar-collapse-btn" onclick="window.GK&&GK.sidebar.collapse()" title="Ein-/Ausklappen">';
                echo '<span class="material-icons">menu</span>';
                echo '</button>';
            }
            if ($this->brandIcon) {
                echo '<span class="material-icons gk-sidebar-brand-icon">' . $e($this->brandIcon) . '</span>';
            }
            echo '<div class="gk-sidebar-brand-text">';
            echo '<span class="gk-sidebar-brand-title">' . $e($this->brand) . '</span>';
            if ($this->version !== '') {
                echo '<span class="gk-sidebar-brand-version">' . $e($this->version) . '</span>';
            }
            echo '</div>';
            echo '<button class="gk-sidebar-close-mobile" onclick="window.GK&&GK.sidebar.close()">';
            echo '<span class="material-icons">close</span>';
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
            echo '<div class="gk-sidebar-group-label">' . $e($label) . '</div>';
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
            echo '<button class="gk-sidebar-collapse-btn gk-sidebar-collapse-bottom" onclick="window.GK&&GK.sidebar.collapse()" title="Ein-/Ausklappen">';
            echo '<span class="material-icons">chevron_left</span>';
            echo '<span class="gk-sidebar-collapse-label">Einklappen</span>';
            echo '</button>';
        }

        echo '</aside>';
    }

    private function renderItem(array $item, \Closure $e): void
    {
        $hasChildren = !empty($item['children']);

        if ($hasChildren) {
            $childActive = false;
            foreach ($item['children'] as $child) {
                if ($child['active'] ?? false) { $childActive = true; break; }
            }
            $isOpen = $item['active'] || $childActive;
            $groupId = $item['id'] ?: 'gk-sub-' . md5($item['label']);

            echo '<div class="gk-sidebar-group">';
            echo '<button class="gk-sidebar-item gk-sidebar-group-toggle' . ($isOpen ? ' active' : '') . '" data-gk-toggle="' . $e($groupId) . '" data-label="' . $e($item['label']) . '">';
            if ($item['icon'] !== '') {
                echo '<span class="material-icons gk-sidebar-icon">' . $e($item['icon']) . '</span>';
            }
            echo '<span class="gk-sidebar-label">' . $e($item['label']) . '</span>';
            echo '<span class="material-icons gk-sidebar-chevron">expand_more</span>';
            echo '</button>';
            echo '<div class="gk-sidebar-subitems' . ($isOpen ? '' : ' collapsed') . '" id="' . $e($groupId) . '">';
            foreach ($item['children'] as $child) {
                $cls = 'gk-sidebar-subitem';
                if ($child['active'] ?? false) $cls .= ' active';
                echo '<a href="' . $e($child['href'] ?? '#') . '" class="' . $cls . '" data-label="' . $e($child['label'] ?? '') . '">';
                if (!empty($child['icon'])) {
                    echo '<span class="material-icons gk-sidebar-icon">' . $e($child['icon']) . '</span>';
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
                echo '<span class="material-icons gk-sidebar-icon">' . $e($item['icon']) . '</span>';
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
        echo '<button class="gk-sidebar-toggle" onclick="GK.sidebar.toggle()">';
        echo '<span class="material-icons">menu</span>';
        echo '</button>';
    }
}
