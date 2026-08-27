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

    /** @param array $items Assoc: ['Dashboard' => '/', 'Invoices' => '/invoices', 'Current page'] or mixed */
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

    public function search(string $placeholder = '', string $name = 'q'): self
    {
        $this->searchOpts = ['placeholder' => $placeholder ?: Lang::t('header.search'), 'name' => $name];
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
            // The control that opens the navigation on a phone — where it is
            // often the only way in. Its glyph is decoration; without a name
            // of its own it announced as "button".
            $menuLabel = htmlspecialchars(Lang::t('sidebar.open'), ENT_QUOTES, 'UTF-8');
            $html .= '<button class="gk-header-menu-toggle" aria-label="' . $menuLabel . '" title="' . $menuLabel . '" aria-expanded="false" onclick="GK.sidebar.toggle()">';
            $html .= '<span class="material-icons" aria-hidden="true">menu</span>';
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
                        $parts[] = '<a href="' . $e($value) . '" title="Dashboard" style="display:inline-flex;align-items:center;"><span class="material-icons" style="font-size:16px;vertical-align:middle;" aria-hidden="true">home</span></a>';
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
            $html .= '<span class="material-icons" aria-hidden="true">search</span>';
            // A placeholder is not an accessible name: it is not reliably
            // announced and it disappears the moment anything is typed. Table
            // got this in 1.32.0; the header search kept the old shape.
            $searchLabel = $e($this->searchOpts['placeholder']);
            $html .= '<input type="text" name="' . $e($this->searchOpts['name'])
                   . '" placeholder="' . $searchLabel . '" aria-label="' . $searchLabel . '">';
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

            // The trigger was a plain <div>: not in the tab order, announced
            // as nothing, and the only way to Profile, Settings and Sign out.
            // Same treatment 1.32.0 gave the sortable table headers.
            $html .= '<div class="gk-header-user" data-gk-dropdown tabindex="0" role="button"'
                   . ' aria-haspopup="true" aria-expanded="false">';

            // Avatar
            if (!empty($u['avatar'])) {
                $html .= '<img class="gk-avatar" src="' . $e($u['avatar']) . '" alt="' . $e($u['name']) . '">';
            } else {
                $html .= '<div class="gk-avatar gk-avatar-initials">' . $e(self::initials($u['name'])) . '</div>';
            }
            $html .= '<span class="gk-header-user-name">' . $e($u['name']) . '</span>';
            $html .= '<span class="material-icons" aria-hidden="true">expand_more</span>';

            // Dropdown menu
            $html .= '<div class="gk-dropdown-menu">';

            // Role label
            if (!empty($u['role'])) {
                $html .= '<div class="gk-dropdown-item" style="pointer-events:none;opacity:.6">';
                $html .= '<span class="material-icons" aria-hidden="true">badge</span>' . $e($u['role']);
                $html .= '</div>';
                $html .= '<div class="gk-dropdown-divider"></div>';
            }

            // User-defined menu items
            foreach (($u['menu'] ?? []) as $item) {
                if (!is_array($item)) {
                    // Only 'divider' means anything here. Any other scalar used
                    // to fall through to the anchor branch and emit
                    // <a class="gk-dropdown-item" href="#"></a> — an empty,
                    // focusable, nameless link in the menu. A typo should be a
                    // no-op, not a tab stop.
                    if ($item === 'divider') $html .= '<div class="gk-dropdown-divider"></div>';
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
                if ($icon !== '') $html .= '<span class="material-icons" aria-hidden="true">' . $e($icon) . '</span>';
                $html .= $e($label);
                $html .= '</a>';
            }

            // Auto theme switcher section
            if ($showThemeSwitcher) {
                $html .= '<div class="gk-dropdown-divider"></div>';
                $html .= '<div class="gk-dropdown-item gk-dropdown-html">'
                       . '<span style="font-size:11px;color:var(--gk-on-surface-variant);font-weight:600;text-transform:uppercase;letter-spacing:.5px;padding:2px 0;">' . $e(Lang::t('header.appearance')) . '</span>'
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

    /**
     * Initials from a name. Deliberately without a hard mbstring dependency:
     * the extension is optional and often not present on slim PHP
     * installations. GridKit advertises "zero dependencies" — failing at that
     * with a fatal error would be a broken promise. Without mbstring, umlauts
     * are left unchanged instead of being uppercased; that is an acceptable
     * loss compared with a blank page.
     */
    private static function initials(string $name): string
    {
        $hasMb = function_exists('mb_substr') && function_exists('mb_strtoupper');
        $out = '';
        foreach (preg_split('/\s+/u', trim($name)) ?: [] as $w) {
            if ($w === '') continue;
            if ($hasMb) {
                $out .= mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8');
            } else {
                // Grab the first character UTF-8-safely, then uppercase as ASCII.
                $firstChar = preg_match('/^./u', $w, $m) ? $m[0] : '';
                $out .= strtoupper($firstChar);
            }
        }
        return $out;
    }
}
