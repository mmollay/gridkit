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
     * Avatar user menu — theme switcher is included automatically by default.
     *
     * @param string $name  Display name
     * @param array  $opts  Keys:
     *   avatar         string  — URL to avatar image (optional, initials used otherwise)
     *   role           string  — shown as non-clickable role label at top of menu
     *   theme_switcher bool    — include theme/mode switcher in menu (default: true)
     *   menu           array   — items: ['label'=>.., 'href'=>.., 'icon'=>..] or 'divider' or ['html'=>..]
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
                        $parts[] = '<span class="gk-breadcrumb-current">' . $e($value) . '</span>';
                    } elseif ($key === 'home') {
                        $parts[] = '<a href="' . $e($value) . '" title="Dashboard" style="display:inline-flex;align-items:center;"><span class="material-icons" style="font-size:16px;vertical-align:middle;">home</span></a>';
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
            $showThemeSwitcher = $u['theme_switcher'] ?? true;

            $html .= '<div class="gk-header-user" data-gk-dropdown>';

            // Avatar
            if (!empty($u['avatar'])) {
                $html .= '<img class="gk-avatar" src="' . $e($u['avatar']) . '" alt="' . $e($u['name']) . '">';
            } else {
                $initials = '';
                foreach (explode(' ', $u['name']) as $w) {
                    if ($w !== '') $initials .= mb_strtoupper(mb_substr($w, 0, 1));
                }
                $html .= '<div class="gk-avatar gk-avatar-initials">' . $e($initials) . '</div>';
            }
            $html .= '<span class="gk-header-user-name">' . $e($u['name']) . '</span>';
            $html .= '<span class="material-icons">expand_more</span>';

            // Dropdown menu
            $html .= '<div class="gk-dropdown-menu">';

            // Role label
            if (!empty($u['role'])) {
                $html .= '<div class="gk-dropdown-item" style="pointer-events:none;opacity:.6">';
                $html .= '<span class="material-icons">badge</span>' . $e($u['role']);
                $html .= '</div>';
                $html .= '<div class="gk-dropdown-divider"></div>';
            }

            // User-defined menu items
            foreach (($u['menu'] ?? []) as $item) {
                if ($item === 'divider') {
                    $html .= '<div class="gk-dropdown-divider"></div>';
                    continue;
                }
                if (isset($item['html'])) {
                    $html .= '<div class="gk-dropdown-item gk-dropdown-html">' . $item['html'] . '</div>';
                    continue;
                }
                $href  = $item['href']  ?? '#';
                $icon  = $item['icon']  ?? '';
                $label = $item['label'] ?? '';
                $html .= '<a class="gk-dropdown-item" href="' . $e($href) . '">';
                if ($icon !== '') $html .= '<span class="material-icons">' . $e($icon) . '</span>';
                $html .= $e($label);
                $html .= '</a>';
            }

            // Auto theme switcher section
            if ($showThemeSwitcher) {
                $html .= '<div class="gk-dropdown-divider"></div>';
                $html .= '<div class="gk-dropdown-item gk-dropdown-html">'
                       . '<span style="font-size:11px;color:var(--gk-on-surface-variant);font-weight:600;text-transform:uppercase;letter-spacing:.5px;padding:2px 0;">Design</span>'
                       . '</div>';
                $html .= '<div class="gk-dropdown-item gk-dropdown-html">' . Theme::switcher() . '</div>';
            }

            $html .= '</div>'; // gk-dropdown-menu
            $html .= '</div>'; // gk-header-user
        }
        $html .= '</div>'; // gk-header-right

        $html .= '</header>';
        return $html;
    }
}
