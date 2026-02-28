<?php
declare(strict_types=1);

namespace GridKit;

class Header
{
    private string $title    = '';
    private bool   $titleRaw = false;
    private array $breadcrumb = [];
    private array $actions = [];
    private ?array $searchOpts = null;
    private ?array $userOpts = null;
    private bool $sticky = false;
    private bool $fixed = false;
    private bool $sidebarToggle = true;

    public function title(string $title, bool $raw = false): self
    {
        $this->title    = $title;
        $this->titleRaw = $raw;
        return $this;
    }

    /** @param array $items Assoc: ['Dashboard' => '/', 'Faktura' => '/faktura', 'Rechnungen'] or mixed */
    public function breadcrumb(array $items): self
    {
        $this->breadcrumb = $items;
        return $this;
    }

    /** Pass pre-rendered HTML (e.g. Button::render()) */
    public function action(string $html): self
    {
        $this->actions[] = $html;
        return $this;
    }

    public function search(string $placeholder = 'Suchen...', string $name = 'q'): self
    {
        $this->searchOpts = ['placeholder' => $placeholder, 'name' => $name];
        return $this;
    }

    /**
     * @param string $name Display name
     * @param array $opts Keys: avatar, role, menu (array of ['label'=>..,'href'=>..,'icon'=>..] or 'divider')
     */
    public function user(string $name, array $opts = []): self
    {
        $this->userOpts = array_merge(['name' => $name], $opts);
        return $this;
    }

    public function sticky(bool $enabled = true): self
    {
        $this->sticky = $enabled;
        return $this;
    }

    public function fixed(bool $enabled = true): self
    {
        $this->fixed = $enabled;
        return $this;
    }

    public function sidebarToggle(bool $enabled = true): self
    {
        $this->sidebarToggle = $enabled;
        return $this;
    }

    public function render(): string
    {
        $e = fn(string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

        $cls = 'gk-header';
        if ($this->fixed) $cls .= ' gk-header-fixed';
        elseif ($this->sticky) $cls .= ' gk-header-sticky';

        $html = '<header class="' . $cls . '">';

        // Left
        $html .= '<div class="gk-header-left">';
        if ($this->sidebarToggle) {
            $html .= '<button class="gk-header-menu-toggle" onclick="GK.sidebar.toggle()">';
            $html .= '<span class="material-icons">menu</span>';
            $html .= '</button>';
        }
        if ($this->title !== '' || !empty($this->breadcrumb)) {
            $html .= '<div class="gk-header-title">';
            if ($this->title !== '') {
                $html .= '<h1>' . ($this->titleRaw ? $this->title : $e($this->title)) . '</h1>';
            }
            if (!empty($this->breadcrumb)) {
                $html .= '<nav class="gk-breadcrumb">';
                $parts = [];
                foreach ($this->breadcrumb as $key => $value) {
                    if (is_int($key)) {
                        // Last item (no link)
                        $parts[] = '<span class="gk-breadcrumb-current">' . $e($value) . '</span>';
                    } else {
                        $parts[] = '<a href="' . $e($value) . '">' . $e($key) . '</a>';
                    }
                }
                $html .= implode('<span class="gk-breadcrumb-sep">/</span>', $parts);
                $html .= '</nav>';
            }
            $html .= '</div>';
        }
        $html .= '</div>';

        // Center (search)
        $html .= '<div class="gk-header-center">';
        if ($this->searchOpts !== null) {
            $html .= '<div class="gk-header-search">';
            $html .= '<span class="material-icons">search</span>';
            $html .= '<input type="text" name="' . $e($this->searchOpts['name']) . '" placeholder="' . $e($this->searchOpts['placeholder']) . '">';
            $html .= '</div>';
        }
        $html .= '</div>';

        // Right
        $html .= '<div class="gk-header-right">';
        if (!empty($this->actions)) {
            $html .= '<div class="gk-header-actions">' . implode('', $this->actions) . '</div>';
        }
        if ($this->userOpts !== null) {
            $u = $this->userOpts;
            $html .= '<div class="gk-header-user" data-gk-dropdown>';
            if (!empty($u['avatar'])) {
                $html .= '<img class="gk-avatar" src="' . $e($u['avatar']) . '" alt="' . $e($u['name']) . '">';
            } else {
                // Generate initials avatar
                $initials = '';
                foreach (explode(' ', $u['name']) as $w) {
                    if ($w !== '') $initials .= mb_strtoupper(mb_substr($w, 0, 1));
                }
                $html .= '<div class="gk-avatar gk-avatar-initials">' . $e($initials) . '</div>';
            }
            $html .= '<span class="gk-header-user-name">' . $e($u['name']) . '</span>';
            $html .= '<span class="material-icons">expand_more</span>';

            if (!empty($u['menu'])) {
                $html .= '<div class="gk-dropdown-menu">';
                if (!empty($u['role'])) {
                    $html .= '<div class="gk-dropdown-item" style="pointer-events:none;opacity:.6">';
                    $html .= '<span class="material-icons">badge</span>' . $e($u['role']);
                    $html .= '</div>';
                    $html .= '<div class="gk-dropdown-divider"></div>';
                }
                foreach ($u['menu'] as $item) {
                    if ($item === 'divider') {
                        $html .= '<div class="gk-dropdown-divider"></div>';
                        continue;
                    }
                    // HTML-Block direkt einfügen
                    if (isset($item['html'])) {
                        $html .= '<div class="gk-dropdown-item gk-dropdown-html">' . $item['html'] . '</div>';
                        continue;
                    }
                    $href = $item['href'] ?? '#';
                    $icon = $item['icon'] ?? '';
                    $label = $item['label'] ?? '';
                    $html .= '<a class="gk-dropdown-item" href="' . $e($href) . '">';
                    if ($icon !== '') $html .= '<span class="material-icons">' . $e($icon) . '</span>';
                    $html .= $e($label);
                    $html .= '</a>';
                }
                $html .= '</div>';
            }
            $html .= '</div>';
        }
        $html .= '</div>';

        $html .= '</header>';
        return $html;
    }
}
