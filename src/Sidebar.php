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
        ];
        if ($this->currentGroup !== '') {
            $this->groups[$this->currentGroup][] = $item;
        } else {
            $this->items[] = $item;
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
            if ($this->brandIcon) {
                echo '<span class="material-icons gk-sidebar-brand-icon">' . $e($this->brandIcon) . '</span>';
            }
            echo '<div class="gk-sidebar-brand-text">';
            echo '<span class="gk-sidebar-brand-title">' . $e($this->brand) . '</span>';
            if ($this->version !== '') {
                echo '<span class="gk-sidebar-brand-version">' . $e($this->version) . '</span>';
            }
            echo '</div>';
            echo '<button class="gk-sidebar-close" onclick="GK.sidebar.close()">';
            echo '<span class="material-icons">close</span>';
            echo '</button>';
            echo '</div>';
        }

        // Nav
        echo '<nav class="gk-sidebar-nav">';

        // Ungrouped items
        foreach ($this->items as $item) {
            $this->renderItem($item, $e);
        }

        // Grouped items
        foreach ($this->groups as $label => $items) {
            echo '<div class="gk-sidebar-group-label">' . $e($label) . '</div>';
            foreach ($items as $item) {
                $this->renderItem($item, $e);
            }
        }

        echo '</nav>';
        echo '</aside>';
    }

    private function renderItem(array $item, \Closure $e): void
    {
        $cls = 'gk-sidebar-item';
        if ($item['active']) $cls .= ' active';
        echo '<a href="' . $e($item['href']) . '" class="' . $cls . '">';
        if ($item['icon'] !== '') {
            echo '<span class="material-icons gk-sidebar-icon">' . $e($item['icon']) . '</span>';
        }
        echo '<span class="gk-sidebar-label">' . $e($item['label']) . '</span>';
        if ($item['badge'] !== null) {
            echo '<span class="gk-sidebar-badge">' . $e((string)$item['badge']) . '</span>';
        }
        echo '</a>';
    }

    /** Render the mobile toggle button (place in your header) */
    public static function toggleButton(): void
    {
        echo '<button class="gk-sidebar-toggle" onclick="GK.sidebar.toggle()">';
        echo '<span class="material-icons">menu</span>';
        echo '</button>';
    }
}
