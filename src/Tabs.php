<?php

declare(strict_types=1);

namespace GridKit;

/**
 * A row of tabs and its panels, written by PHP (since 1.111.0).
 *
 * Until 1.111.0 every page wrote the tab markup by hand: the authored shape of
 * the skill (`.gk-tabs > .gk-tab-nav > .gk-tab-btn[data-tab]` + `.gk-tab-panel`),
 * the classes of the styles, alignments and options of 1.103.0, badges, counts,
 * icons and disabled tabs. Every one of those is a place to get a class or an
 * attribute wrong, and none of it is checked. This class writes exactly that
 * markup — nothing of its own — so a page built with it looks and behaves like
 * one written by hand, also with an older copy of gridkit.js.
 *
 *     (new Tabs('settings'))
 *         ->style('karte')
 *         ->tab('general', 'General', ['icon' => 'tune', 'content' => $html])
 *         ->tab('billing', 'Billing', ['badge' => ['text' => 'Error', 'tone' => 'error']])
 *         ->tab('log', 'Log', ['url' => '/settings/log.php'])   // loaded on first view
 *         ->hash()->remember()
 *         ->render();
 *
 * It PRINTS (like Table and Form: a component that owns a block of the page);
 * html() returns the same markup as a string.
 *
 * A wrong value never throws and never reaches the page unescaped: it raises an
 * E_USER_WARNING that says what was left out and renders the closest thing that
 * is right — the same contract Table keeps for its columns.
 */
class Tabs
{
    private const STYLES = ['karte', 'linie', 'kapsel', 'text'];
    private const ALIGNS = ['top', 'oben', 'links', 'rechts', 'unten', 'fest'];
    private const SIZES = ['sm', 'md', 'lg'];
    private const TONES = ['error'];

    private string $id;
    /** @var array<string, array<string, mixed>> */
    private array $tabs = [];
    private ?string $style = null;
    private string $align = 'top';
    private string $size = 'md';
    private bool $fluid = false;
    private bool $iconTop = false;
    private bool $iconOnly = false;
    private bool $secondary = false;
    private bool $neutral = false;
    private bool $fill = false;
    private bool $oneRow = false;
    private bool $flat = false;
    private ?bool $surface = null;
    private ?string $active = null;
    private ?string $label = null;
    private bool $hash = false;
    private bool $remember = false;
    private ?string $param = null;

    public function __construct(string $id)
    {
        // The id is the element id of the row, the name in the address (hash()) and
        // the storage key (remember()): one word, no spaces.
        $clean = (string) preg_replace('/\s+/', '-', trim($id));
        if ($clean === '') {
            self::warn("Tabs: an empty id — 'tabs' is used.");
            $clean = 'tabs';
        } elseif ($clean !== $id) {
            self::warn("Tabs: the id '$id' has spaces — '$clean' is used.");
        }
        $this->id = $clean;
    }

    /**
     * One tab. $key pairs the tab with its panel (data-tab) and is what the address,
     * the memory, the parameter and GK.tabs.* name it by.
     *
     * Options: 'icon' (a Material icon name), 'badge' (text, or ['text' => …,
     * 'tone' => 'error']), 'badgeError' (true: the red badge), 'count' (a number of
     * things behind the tab), 'disabled', 'title' (a tooltip), 'content' (the panel's
     * HTML — trusted markup, not escaped — or a callable that echoes it), 'url' (a
     * lazy panel: fetched on first view), 'reload' (with 'url': fetched on every
     * view) and 'href' (the tab is a link to a page; every tab of the row then needs one).
     */
    public function tab(string $key, string $label, array $opts = []): static
    {
        if ($key === '') {
            self::warn("Tabs '{$this->id}': a tab without a key was left out.");
            return $this;
        }
        if (isset($this->tabs[$key])) {
            self::warn("Tabs '{$this->id}': the key '$key' is used twice — the second tab was left out.");
            return $this;
        }
        $this->tabs[$key] = ['key' => $key, 'label' => $label, 'opts' => $opts];
        return $this;
    }

    /** 'karte' (Karteireiter), 'linie', 'kapsel' or 'text'. Without a style the row keeps the classic line shape. */
    public function style(string $style): static
    {
        if (!in_array($style, self::STYLES, true)) {
            self::warn("Tabs '{$this->id}': style('$style') is not one of " . implode(', ', self::STYLES) . ' — the style was left out.');
            return $this;
        }
        $this->style = $style;
        return $this;
    }

    /** 'top' (the default), 'links', 'rechts', 'unten' or 'fest' (a bar that sticks to the lower edge of its box). */
    public function align(string $align): static
    {
        if (!in_array($align, self::ALIGNS, true)) {
            self::warn("Tabs '{$this->id}': align('$align') is not one of top, links, rechts, unten, fest — the row stays on top.");
            return $this;
        }
        $this->align = $align === 'oben' ? 'top' : $align;
        return $this;
    }

    /** 'sm', 'md' (the default) or 'lg'. */
    public function size(string $size): static
    {
        if (!in_array($size, self::SIZES, true)) {
            self::warn("Tabs '{$this->id}': size('$size') is not one of sm, md, lg — md is used.");
            return $this;
        }
        $this->size = $size;
        return $this;
    }

    /** The tabs share the width of the row equally. */
    public function fluid(bool $on = true): static { $this->fluid = $on; return $this; }

    /** The icon stands above the word. */
    public function iconTop(bool $on = true): static { $this->iconTop = $on; return $this; }

    /** Only the icon shows; the word stays for a screen reader, and every tab gets aria-label and title. */
    public function iconOnly(bool $on = true): static { $this->iconOnly = $on; return $this; }

    /** The quieter second-row shape (classic row, or a card row inside a surface or a window). */
    public function secondary(bool $on = true): static { $this->secondary = $on; return $this; }

    /** The chosen tab in the text colour, not the main colour. */
    public function neutral(bool $on = true): static { $this->neutral = $on; return $this; }

    /** The classic row as the main row of a page: 44px targets, shared width on a phone (gk-tab-nav-fill). */
    public function fill(bool $on = true): static { $this->fill = $on; return $this; }

    /** Card style on a phone: ONE row of equal cells instead of two columns (gk-tab-nav-einzeilig). */
    public function oneRow(bool $on = true): static { $this->oneRow = $on; return $this; }

    /** Card style on a phone: the icon beside the word, cells 44px high (gk-tab-nav-flach). */
    public function flat(bool $on = true): static { $this->flat = $on; return $this; }

    /** The panels stand on the card surface (gk-tab-flaeche). On by default with style('karte'), except for a secondary row. */
    public function surface(bool $on = true): static { $this->surface = $on; return $this; }

    /**
     * The tab the page decides on — e.g. the one with a validation error. It wins
     * over the address (param(), hash()) and the memory (remember()). Leave it out
     * to let them choose; without any of them the first tab that is not disabled
     * is active.
     */
    public function active(string $key): static { $this->active = $key; return $this; }

    /** The name of the row for a screen reader (aria-label of the nav). */
    public function label(string $label): static { $this->label = $label; return $this; }

    /**
     * Keep the chosen tab in the address as #<id>=<key>, beside whatever else the
     * fragment holds, and select it from the address on load and on hashchange.
     */
    public function hash(bool $on = true): static { $this->hash = $on; return $this; }

    /**
     * Read the active tab from the query string (?tab=billing) on the server, so a
     * link opens a tab without any script. When the address carries the parameter,
     * a switch in the browser rewrites it in place.
     */
    public function param(string $name = 'tab'): static
    {
        if ($name === '') {
            self::warn("Tabs '{$this->id}': param('') names no parameter — the address is not read.");
            return $this;
        }
        $this->param = $name;
        return $this;
    }

    /** Remember the last tab of this row in the browser (localStorage, per id and per GK.theme scope). */
    public function remember(bool $on = true): static { $this->remember = $on; return $this; }

    public function render(): void
    {
        echo $this->html();
    }

    /** The same markup render() prints, as a string. */
    public function html(): string
    {
        $e = static fn($s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

        if ($this->tabs === []) {
            self::warn("Tabs '{$this->id}': no tab — nothing is rendered.");
            return '';
        }

        // A row of links between pages, or a tablist over panels. Never half of each:
        // a link inside a tablist is taken for a tab, a panel tab inside a <nav> has
        // no panel to show.
        $links = array_filter($this->tabs, static fn(array $t): bool => isset($t['opts']['href']));
        $tabs = $this->tabs;
        $isLinks = $links !== [] && count($links) === count($tabs);
        if ($links !== [] && !$isLinks) {
            self::warn("Tabs '{$this->id}': some tabs have an 'href' and some do not — a row is links OR panels; the tabs with 'href' ("
                . implode(', ', array_keys($links)) . ') were left out.');
            $tabs = array_diff_key($tabs, $links);
        }
        if ($isLinks) {
            foreach (['hash' => 'hash()', 'remember' => 'remember()'] as $prop => $call) {
                if ($this->$prop) self::warn("Tabs '{$this->id}': $call has no effect on a row of links — each link is a page of its own.");
            }
        }

        [$nav, $style] = $this->navClasses($isLinks);
        $active = $this->resolveActive($tabs, $isLinks);
        $chosen = $active['from'];
        $activeKey = $active['key'];

        $label = $this->label !== null ? ' aria-label="' . $e($this->label) . '"' : '';

        if ($isLinks) {
            $out = '<nav class="' . $e(implode(' ', $nav)) . '" id="' . $e($this->id) . '"' . $label . ">\n";
            foreach ($tabs as $tab) $out .= $this->renderLink($tab, $tab['key'] === $activeKey, $e) . "\n";
            return $out . "</nav>\n";
        }

        $attrs = ' id="' . $e($this->id) . '"';
        if ($this->hash) $attrs .= ' data-gk-tabs-hash';
        if ($this->remember) $attrs .= ' data-gk-tabs-remember';
        if ($this->param !== null) $attrs .= ' data-gk-tabs-param="' . $e($this->param) . '"';
        // Where the server's choice came from, so the browser knows what may override it:
        // the page's own decision nothing, a parameter in the address only the fragment.
        if ($chosen !== null && ($this->hash || $this->remember)) $attrs .= ' data-gk-tabs-chosen="' . $chosen . '"';

        $row = '<div class="' . $e(implode(' ', $nav)) . '"' . $label . ">\n";
        foreach ($tabs as $tab) $row .= $this->renderButton($tab, $tab['key'] === $activeKey, $e) . "\n";
        $row .= "</div>\n";

        $panels = '';
        // The second row stands inside the surface of the first (or in a window, which is
        // the surface): it brings none of its own.
        $surface = $style === 'karte' && ($this->surface ?? !$this->secondary);
        foreach ($tabs as $tab) $panels .= $this->renderPanel($tab, $tab['key'] === $activeKey, $surface, $e) . "\n";

        // A bottom row stands AFTER the panels in the markup, so the reading order is
        // the order on the screen.
        $bottom = $this->align === 'unten' || $this->align === 'fest';
        return '<div class="gk-tabs"' . $attrs . ">\n" . ($bottom ? $panels . $row : $row . $panels) . "</div>\n";
    }

    /** @return array{0: list<string>, 1: ?string} the classes of the row, after every combination has been checked, and its style */
    private function navClasses(bool $isLinks): array
    {
        $style = $this->style;
        $needsStyle = [];
        if ($this->align !== 'top') $needsStyle[] = "align('{$this->align}')";
        if ($this->size !== 'md') $needsStyle[] = "size('{$this->size}')";
        if ($this->fluid) $needsStyle[] = 'fluid()';
        if ($this->iconTop) $needsStyle[] = 'iconTop()';
        if ($this->neutral) $needsStyle[] = 'neutral()';
        if ($style === null && $needsStyle !== []) {
            // The 1.103.0 variants read their tokens from a style class; on the classic row
            // they half apply. The line style is the classic row's nearest relative.
            self::warn("Tabs '{$this->id}': " . implode(', ', $needsStyle) . " needs a style — style('linie') is used.");
            $style = 'linie';
        }

        $cls = ['gk-tab-nav'];
        if ($style !== null) $cls[] = 'gk-tab-nav-' . $style;

        $secondary = $this->secondary;
        if ($secondary && $style !== null && $style !== 'karte') {
            self::warn("Tabs '{$this->id}': secondary() is the second row of the classic or the card row, not of style('$style') — left out.");
            $secondary = false;
        }
        if ($secondary && ($this->align === 'unten' || $this->align === 'fest')) {
            self::warn("Tabs '{$this->id}': secondary() together with align('{$this->align}') is not supported — secondary() was left out.");
            $secondary = false;
        }
        if ($secondary) $cls[] = 'gk-tab-nav-secondary';

        if ($this->align === 'fest') array_push($cls, 'gk-tab-nav-unten', 'gk-tab-nav-fest');
        elseif ($this->align !== 'top') $cls[] = 'gk-tab-nav-' . $this->align;

        if ($this->size !== 'md') $cls[] = 'gk-tab-nav-' . $this->size;
        if ($this->fluid) $cls[] = 'gk-tab-nav-fluid';
        if ($this->iconOnly) {
            if ($this->iconTop) self::warn("Tabs '{$this->id}': iconTop() and iconOnly() together — iconOnly() wins.");
            $cls[] = 'gk-tab-nav-nur-icon';
        } elseif ($this->iconTop) {
            $cls[] = 'gk-tab-nav-icon-oben';
        }
        if ($this->neutral) $cls[] = 'gk-tab-nav-neutral';

        if ($this->fill) {
            if ($style !== null) self::warn("Tabs '{$this->id}': fill() is the classic row's main shape and never mixes with style('$style') — left out.");
            else $cls[] = 'gk-tab-nav-fill';
        }
        foreach (['oneRow' => 'gk-tab-nav-einzeilig', 'flat' => 'gk-tab-nav-flach'] as $prop => $class) {
            if (!$this->$prop) continue;
            if ($style !== 'karte') self::warn("Tabs '{$this->id}': $prop() is an option of style('karte') on a phone — left out.");
            else $cls[] = $class;
        }
        if ($this->surface === true && ($style !== 'karte' || $isLinks)) {
            self::warn("Tabs '{$this->id}': surface() is the panel surface of style('karte')"
                . ($isLinks ? ' — a row of links has no panels; write the gk-tab-flaeche after it yourself' : '') . ' — left out.');
        }
        return [$cls, $style];
    }

    /**
     * The active tab and where it came from: 'page' (active()), 'url' (param()) or
     * null (the first tab that is not disabled).
     *
     * @param array<string, array<string, mixed>> $tabs
     * @return array{key: ?string, from: ?string}
     */
    private function resolveActive(array $tabs, bool $isLinks): array
    {
        $usable = static fn(?string $k): bool => $k !== null && isset($tabs[$k]) && empty($tabs[$k]['opts']['disabled']);

        if ($this->active !== null) {
            if ($usable($this->active)) return ['key' => $this->active, 'from' => 'page'];
            self::warn("Tabs '{$this->id}': active('{$this->active}') " . (isset($tabs[$this->active]) ? 'is disabled' : 'names no tab')
                . ' — the ' . ($this->param !== null ? 'address or the ' : '') . 'first tab is used.');
        }
        if ($this->param !== null) {
            // A hand-typed address is not the page's mistake: a value no tab has is
            // passed over in silence (?tab[]=x is an array and passed over too).
            $value = $_GET[$this->param] ?? null;
            if (is_string($value) && $usable($value)) return ['key' => $value, 'from' => 'url'];
        }
        // A row of links cannot know which page it is on: without active() or the
        // parameter no link is marked current.
        if ($isLinks) return ['key' => null, 'from' => null];
        foreach ($tabs as $key => $tab) {
            if ($usable((string) $key)) return ['key' => (string) $key, 'from' => null];
        }
        self::warn("Tabs '{$this->id}': every tab is disabled — the first one is shown.");
        return ['key' => (string) array_key_first($tabs), 'from' => null];
    }

    /** The inside of a tab: icon, word, badge, count — the order the skill documents. */
    private function inner(array $tab, callable $e): array
    {
        $o = $tab['opts'];
        $icon = isset($o['icon']) && is_string($o['icon']) && $o['icon'] !== ''
            ? '<span class="material-icons gk-tab-icon" aria-hidden="true">' . $e($o['icon']) . '</span>' : '';

        $badgeText = null;
        $tone = !empty($o['badgeError']) ? 'error' : null;
        if (isset($o['badge'])) {
            $b = $o['badge'];
            if (is_array($b)) {
                $badgeText = isset($b['text']) && (is_string($b['text']) || is_int($b['text'])) ? (string) $b['text'] : null;
                if (isset($b['tone'])) {
                    if (in_array($b['tone'], self::TONES, true)) $tone = $b['tone'];
                    else self::warn("Tabs '{$this->id}': tab '{$tab['key']}' has a badge tone '" . (is_scalar($b['tone']) ? $b['tone'] : gettype($b['tone'])) . "' — only 'error' exists; the plain badge is used.");
                }
            } elseif (is_string($b) || is_int($b)) {
                $badgeText = (string) $b;
            }
            if ($badgeText === null) self::warn("Tabs '{$this->id}': tab '{$tab['key']}' has a 'badge' that is neither text nor ['text' => …] — left out.");
        }
        if ($badgeText === '') $badgeText = null;
        $badge = $badgeText !== null
            ? '<span class="gk-tab-badge' . ($tone === 'error' ? ' gk-tab-badge-error' : '') . '">' . $e($badgeText) . '</span>' : '';

        $countText = null;
        if (isset($o['count'])) {
            if (is_int($o['count']) || (is_string($o['count']) && $o['count'] !== '')) $countText = (string) $o['count'];
            else self::warn("Tabs '{$this->id}': tab '{$tab['key']}' has a 'count' that is not a number — left out.");
        }
        $count = $countText !== null ? '<span class="gk-tab-count">' . $e($countText) . '</span>' : '';

        // The word is a bare text node when it stands alone — the documented markup —
        // and its own <span> beside an icon, a badge or a count. With iconOnly() the
        // span is what the stylesheet hides from the eye; a tab without an icon keeps
        // its word as a bare node there, so it is not left blank.
        if ($this->iconOnly && $icon === '') {
            self::warn("Tabs '{$this->id}': tab '{$tab['key']}' has no 'icon' — with iconOnly() it shows its word instead.");
            $word = $e($tab['label']);
        } elseif ($icon !== '' || $badge !== '' || $count !== '' || $this->iconOnly) {
            $word = '<span>' . $e($tab['label']) . '</span>';
        } else {
            $word = $e($tab['label']);
        }

        // iconOnly(): the tab's name for a screen reader is its aria-label, and the
        // badge and count are part of what it has to hear (GK.tabs.badge() keeps
        // this up to date in the browser).
        $name = implode(', ', array_filter([$tab['label'], $badgeText, $countText], static fn($v): bool => $v !== null && $v !== ''));

        return ['html' => $icon . $word . $badge . $count, 'name' => $name];
    }

    private function tabAttrs(array $tab, callable $e, string $name): string
    {
        $o = $tab['opts'];
        $title = isset($o['title']) && is_string($o['title']) && $o['title'] !== '' ? $o['title'] : null;
        $a = '';
        if ($this->iconOnly) {
            $a .= ' aria-label="' . $e($name) . '" title="' . $e($title ?? $tab['label']) . '"';
        } elseif ($title !== null) {
            $a .= ' title="' . $e($title) . '"';
        }
        return $a;
    }

    private function renderButton(array $tab, bool $on, callable $e): string
    {
        $in = $this->inner($tab, $e);
        $disabled = !empty($tab['opts']['disabled']) ? ' disabled' : '';
        // type="button": a tab inside a <form> must not submit it.
        return '<button type="button" class="gk-tab-btn' . ($on ? ' gk-active' : '') . '" data-tab="' . $e($tab['key']) . '"'
            . $this->tabAttrs($tab, $e, $in['name']) . $disabled . '>' . $in['html'] . '</button>';
    }

    private function renderLink(array $tab, bool $on, callable $e): string
    {
        $in = $this->inner($tab, $e);
        $o = $tab['opts'];
        $href = self::safeUrl($o['href'] ?? null);
        if ($href === null) {
            self::warn("Tabs '{$this->id}': tab '{$tab['key']}' has an unusable 'href' — it is shown disabled.");
        }
        // data-tab here too, so GK.tabs.badge() and .count() find a link tab by its key.
        $a = '<a class="gk-tab-btn" data-tab="' . $e($tab['key']) . '"';
        if (!empty($o['disabled']) || $href === null) {
            // A disabled link has no address: it cannot be followed or focused.
            $a .= ' aria-disabled="true"';
        } else {
            $a .= ' href="' . $e($href) . '"';
            if ($on) $a .= ' aria-current="page"';
        }
        return $a . $this->tabAttrs($tab, $e, $in['name']) . '>' . $in['html'] . '</a>';
    }

    private function renderPanel(array $tab, bool $on, bool $surface, callable $e): string
    {
        $o = $tab['opts'];
        $cls = 'gk-tab-panel' . ($surface ? ' gk-tab-flaeche' : '') . ($on ? ' gk-active' : '');
        $attrs = ' data-tab="' . $e($tab['key']) . '"';

        if (isset($o['url'])) {
            $url = self::safeUrl($o['url']);
            if ($url === null) {
                self::warn("Tabs '{$this->id}': tab '{$tab['key']}' has an unusable 'url' — the panel stays empty.");
                return '<div class="' . $cls . '"' . $attrs . '></div>';
            }
            if (isset($o['content'])) {
                self::warn("Tabs '{$this->id}': tab '{$tab['key']}' has a 'url' and a 'content' — the panel is loaded, the content was left out.");
            }
            $attrs .= ' data-gk-tab-url="' . $e($url) . '"' . (!empty($o['reload']) ? ' data-gk-tab-reload' : '');
            // What a page without the 1.111.0 script shows (the PawBot dashboard keeps an
            // older copy): a link to the panel's address instead of a skeleton that never
            // ends. The script replaces it on the first view.
            $fallback = '<p class="gk-tab-fallback"><a href="' . $e($url) . '">'
                . $e(Lang::t('tabs.open', ['label' => $tab['label']])) . '</a></p>';
            return '<div class="' . $cls . '"' . $attrs . '>' . $fallback . '</div>';
        }
        if (!empty($o['reload'])) {
            self::warn("Tabs '{$this->id}': tab '{$tab['key']}' has 'reload' without a 'url' — nothing to load.");
        }

        $content = '';
        if (isset($o['content'])) {
            $c = $o['content'];
            if (is_string($c)) {
                $content = $c;
            } elseif (is_callable($c)) {
                ob_start();
                try {
                    $returned = $c();
                } finally {
                    $content = (string) ob_get_clean();
                }
                if (is_string($returned)) $content .= $returned;
            } else {
                self::warn("Tabs '{$this->id}': tab '{$tab['key']}' has a 'content' that is neither HTML nor a callable — the panel stays empty.");
            }
        }
        return '<div class="' . $cls . '"' . $attrs . '>' . $content . '</div>';
    }

    /** An address a page may link to or fetch: no javascript:, data: or other scheme but http(s). */
    private static function safeUrl(mixed $url): ?string
    {
        if (!is_string($url) || trim($url) === '') return null;
        $url = trim($url);
        if (preg_match('/^([a-z][a-z0-9+.-]*):/i', $url, $m) && !in_array(strtolower($m[1]), ['http', 'https'], true)) return null;
        return $url;
    }

    private static function warn(string $message): void
    {
        trigger_error('GridKit: ' . $message, E_USER_WARNING);
    }
}
