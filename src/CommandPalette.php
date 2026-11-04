<?php
declare(strict_types=1);

namespace GridKit;

/**
 * CommandPalette — type a few letters, jump to a page, a customer, an invoice, or run a command (1.122.0).
 *
 *   $palette = (new CommandPalette('main'))
 *       ->item('New invoice', '/invoices/new', ['icon' => 'add', 'group' => 'Create', 'keywords' => 'bill'])
 *       ->item('Dark mode', '', ['icon' => 'dark_mode', 'action' => 'toggle-dark'])
 *       ->search('/admin/palette-search')        // customers, invoices: JSON, see GRIDKIT_SKILL.md
 *       ->recent(5);
 *   echo (new Header())->action($palette->trigger())->render();   // the button that opens it
 *   $palette->render();                                           // once, anywhere in <body>
 *
 * Why it exists: an admin with forty pages and ten thousand records is crossed by search, not by
 * clicking — and every system among GridKit's users had either nothing or a search box of its own.
 *
 * The server writes a <dialog> (closed: nothing shows) holding the input, the empty list and the
 * configuration as JSON. js/command-palette.js opens it with Ctrl+K (Cmd+K on a Mac), a trigger or
 * GK.palette.open(), reads the sidebar's links out of the page, ranks what matches and fetches records
 * from the search URLs. It is a file of its own for the reason js/tree.js is: the PawBot dashboard keeps
 * its own copy of gridkit.js that no sync ever overwrites. Without the script the trigger is hidden — or,
 * with fallback(), a plain link to a search page — and nothing else is drawn.
 *
 * A wrong option is an E_USER_WARNING with a fallback that still renders, never an exception: an item
 * without a label, a javascript: link, an action name that is not a name, a count out of range.
 */
class CommandPalette
{
    /** What an item may carry besides its label and link. */
    private const ITEM_OPTS = ['icon', 'group', 'keywords', 'hint', 'shortcut', 'action'];

    /** What a search URL may carry. */
    private const SEARCH_OPTS = ['minLength', 'debounce', 'group', 'param'];

    /** What the trigger may carry. */
    private const TRIGGER_OPTS = ['label', 'class', 'kbd'];

    private const DEFAULT_HOTKEY = 'mod+k';
    private const DEFAULT_SIDEBAR = '.gk-sidebar-nav';

    private string $id;
    /** @var list<array<string,mixed>> */
    private array $items = [];
    /** @var list<array<string,mixed>> */
    private array $searches = [];
    private string $sidebar = self::DEFAULT_SIDEBAR;
    private int $recent = 5;
    private int $maxPerGroup = 6;
    private string $hotkey = self::DEFAULT_HOTKEY;
    private bool $slash = false;
    private string $placeholder = '';
    private string $label = '';
    private string $fallback = '';

    public function __construct(string $id = 'main')
    {
        $this->id = trim($id) === '' ? 'main' : $id;
        if (trim($id) === '') $this->warn("the palette needs an id — it is called 'main'.");
    }

    // ── What it offers ──────────────────────────────────────────────────────

    /**
     * One entry: a link ($href) or, with the option 'action', a command the page carries out when it
     * hears gk:paletteaction. Options: icon (a Material icon name), group (the heading it is listed
     * under), keywords (a string or a list: found, not shown), hint (a second line), shortcut (shown
     * at the end — the page binds the key itself), action (a name: letters, digits, - _ . :).
     *
     * @param array<string,mixed> $opts
     */
    public function item(string $label, string $href = '', array $opts = []): static
    {
        $this->addItem($label, $href, $opts, 'item(' . var_export($label, true) . ')');
        return $this;
    }

    /**
     * Many entries at once — rows from a database. Each row holds 'label' (or 'title'), 'href' (or
     * 'url') and any option of item() under its own name.
     *
     * @param iterable<mixed> $rows
     */
    public function items(iterable $rows): static
    {
        foreach ($rows as $i => $row) {
            if (is_object($row)) $row = get_object_vars($row);
            if (!is_array($row)) {
                $this->warn("items(): row $i is " . get_debug_type($row) . ', not an array — it was left out.');
                continue;
            }
            $label = $row['label'] ?? $row['title'] ?? null;
            $href = $row['href'] ?? $row['url'] ?? '';
            if (!is_scalar($label) || is_bool($label)) {
                $this->warn("items(): row $i has no label — it was left out.");
                continue;
            }
            if (!is_scalar($href) || is_bool($href)) {
                $this->warn("items(): row $i has a link that is not text — it was left out.");
                continue;
            }
            $opts = [];
            foreach ($row as $key => $value) {
                if (in_array($key, ['label', 'title', 'href', 'url'], true)) continue;
                $opts[(string) $key] = $value;
            }
            $this->addItem((string) $label, (string) $href, $opts, "items() row $i");
        }
        return $this;
    }

    /**
     * Records from the server: GET $url?q=<what was typed>, answered with JSON — the shape GK.search
     * reads ({"groups": [{"title", "items": [{"title", "url", "subtitle", "icon", "amount"}]}]}), or
     * {"items": […]}, or a bare list. Same origin only: the script refuses another one. Options:
     * minLength (letters before it asks, default 2), debounce (ms of quiet before it asks, default 250),
     * group (the heading of a bare list), param (the name of the query parameter, default 'q').
     * Call it again for a second URL.
     *
     * @param array<string,mixed> $opts
     */
    public function search(string $url, array $opts = []): static
    {
        if (trim($url) === '') {
            $this->warn('search() needs a URL — no records are searched.');
            return $this;
        }
        if (self::unsafeUrl($url)) {
            $this->warn('search() was given a ' . self::scheme($url) . ': URL — no records are searched.');
            return $this;
        }
        $src = ['url' => $url, 'minLength' => 2, 'debounce' => 250];
        foreach ($opts as $key => $value) {
            $key = (string) $key;
            if (!in_array($key, self::SEARCH_OPTS, true)) {
                $this->warn("search() does not know the option '$key' — it was ignored.");
                continue;
            }
            switch ($key) {
                case 'minLength':
                    $src['minLength'] = $this->intIn($value, 0, 10, 2, 'search() minLength');
                    break;
                case 'debounce':
                    $src['debounce'] = $this->intIn($value, 0, 2000, 250, 'search() debounce');
                    break;
                case 'group':
                case 'param':
                    if (is_string($value) && trim($value) !== '') {
                        $src[$key] = $value;
                    } else {
                        $this->warn("search() takes '$key' as text — it was ignored.");
                    }
                    break;
            }
        }
        $this->searches[] = $src;
        return $this;
    }

    /**
     * The sidebar's links are entries of their own, read out of the page when the palette opens —
     * labels, groups, symbols. true (default) reads GridKit's sidebar (.gk-sidebar-nav), a string any
     * other navigation by its CSS selector, false none.
     */
    public function sidebar(bool|string $source = true): static
    {
        if ($source === true) {
            $this->sidebar = self::DEFAULT_SIDEBAR;
        } elseif ($source === false) {
            $this->sidebar = '';
        } elseif (trim($source) === '') {
            $this->warn("sidebar('') names no selector — GridKit's sidebar is read.");
            $this->sidebar = self::DEFAULT_SIDEBAR;
        } else {
            $this->sidebar = $source;
        }
        return $this;
    }

    /** How many chosen entries the browser remembers and shows first (localStorage, per palette and per GK.theme scope). 0 to 20, default 5; 0 remembers nothing. */
    public function recent(int $count): static
    {
        $this->recent = $this->intIn($count, 0, 20, 5, 'recent()');
        return $this;
    }

    /** At most this many entries under one heading; the rest is counted ("3 more"). 1 to 50, default 6. */
    public function maxPerGroup(int $count): static
    {
        $this->maxPerGroup = $this->intIn($count, 1, 50, 6, 'maxPerGroup()');
        return $this;
    }

    /**
     * The key that opens it: 'mod+k' (default — Ctrl+K, Cmd+K on a Mac), or any of mod, ctrl, meta, alt,
     * shift joined with + to one key ('mod+shift+p', 'alt+k'). false or '' — no key; the trigger and
     * GK.palette.open() still work.
     */
    public function hotkey(string|false $combo): static
    {
        if ($combo === false || trim($combo) === '') {
            $this->hotkey = '';
            return $this;
        }
        $norm = strtolower(str_replace(' ', '', $combo));
        $parts = explode('+', $norm);
        $key = array_pop($parts);
        $mods = array_values(array_unique($parts));
        $ok = preg_match('/^[a-z0-9\/.,;]$/', $key) === 1
            && $mods !== []
            && array_diff($mods, ['mod', 'ctrl', 'meta', 'alt', 'shift']) === []
            && array_diff($mods, ['shift']) !== [];
        if (!$ok) {
            $this->warn("hotkey('$combo') is not a key with a modifier (mod, ctrl, meta or alt) — it opens with 'mod+k'.");
            $this->hotkey = self::DEFAULT_HOTKEY;
            return $this;
        }
        // One spelling: the modifiers in a fixed order.
        $order = array_values(array_intersect(['mod', 'ctrl', 'meta', 'alt', 'shift'], $mods));
        $this->hotkey = implode('+', [...$order, $key]);
        return $this;
    }

    /** "/" opens it too — not while someone types in a field. Default off. */
    public function slash(bool $on = true): static
    {
        $this->slash = $on;
        return $this;
    }

    /** The words in the empty field. Default: the catalogue's "Type a command or search…". */
    public function placeholder(string $text): static
    {
        $this->placeholder = $text;
        return $this;
    }

    /** The accessible name of the dialog. Default: the catalogue's "Command palette". */
    public function label(string $text): static
    {
        $this->label = $text;
        return $this;
    }

    /** Where the trigger leads without the script: a plain search page. Without it the trigger is hidden until the script shows it. */
    public function fallback(string $url): static
    {
        if (self::unsafeUrl($url)) {
            $this->warn('fallback() was given a ' . self::scheme($url) . ': URL — the trigger is hidden without the script.');
            return $this;
        }
        $this->fallback = trim($url) === '' ? '' : $url;
        return $this;
    }

    // ── Rendering ───────────────────────────────────────────────────────────

    /**
     * The button that opens the palette — for Header::action() or anywhere else. RETURNS the markup.
     * Options: label (default "Search"), class (more classes), kbd (false: no key hint).
     *
     * @param array<string,mixed> $opts
     */
    public function trigger(array $opts = []): string
    {
        $e = self::escaper();
        $label = Lang::t('palette.trigger');
        $class = '';
        $kbd = true;
        foreach ($opts as $key => $value) {
            $key = (string) $key;
            if (!in_array($key, self::TRIGGER_OPTS, true)) {
                $this->warn("trigger() does not know the option '$key' — it was ignored.");
                continue;
            }
            if ($key === 'label') {
                if (is_string($value) && trim($value) !== '') $label = $value;
                else $this->warn("trigger() takes 'label' as text — the button says \"$label\".");
            } elseif ($key === 'class') {
                if (is_string($value)) $class = trim($value);
                else $this->warn("trigger() takes 'class' as text — it was ignored.");
            } else {
                $flag = self::flag($value);
                if ($flag === null) $this->warn("trigger() takes 'kbd' as true or false — the key is shown.");
                else $kbd = $flag;
            }
        }

        $cls = 'gk-palette-trigger' . ($class !== '' ? ' ' . $class : '');
        $attrs = ' class="' . $e($cls) . '" data-gk-palette-open="' . $e($this->id) . '"';
        if ($this->hotkey !== '') {
            $attrs .= ' aria-keyshortcuts="' . $e(self::ariaKeys($this->hotkey) . ($this->slash ? ' /' : '')) . '"';
        } elseif ($this->slash) {
            $attrs .= ' aria-keyshortcuts="/"';
        }
        $inner = self::glyph('search')
            . '<span class="gk-palette-trigger-label">' . $e($label) . '</span>';
        if ($kbd && ($this->hotkey !== '' || $this->slash)) {
            $combo = $this->hotkey !== '' ? $this->hotkey : '/';
            // The words of the keys are for the eye; the name of the button is its label, and the key is
            // in aria-keyshortcuts. The script writes the Mac's symbols over it.
            $inner .= '<kbd class="gk-palette-kbd" data-gk-palette-hotkey="' . $e($combo) . '" aria-hidden="true">'
                . $e(self::keyWords($combo)) . '</kbd>';
        }
        if ($this->fallback !== '') {
            return '<a href="' . $e($this->fallback) . '"' . $attrs . '>' . $inner . '</a>';
        }
        // A button that does nothing without the script is not drawn without it.
        return '<button type="button"' . $attrs . ' hidden>' . $inner . '</button>';
    }

    /** Prints the palette: a closed <dialog>, once per page, anywhere in <body>. */
    public function render(): void
    {
        echo $this->html();
    }

    /** The string twin of render(). */
    public function html(): string
    {
        $e = self::escaper();
        $key = self::key($this->id);
        $base = 'gk-palette-' . $key;
        $label = $this->label !== '' ? $this->label : Lang::t('js.palette_label');
        $placeholder = $this->placeholder !== '' ? $this->placeholder : Lang::t('js.palette_placeholder');
        $close = Lang::t('js.palette_close');

        $html = '<dialog class="gk-palette" id="' . $e($base) . '" data-gk-palette="' . $e($this->id) . '" aria-label="' . $e($label) . '">';
        $html .= '<div class="gk-palette-head">' . self::glyph('search')
            . '<input type="text" class="gk-palette-input" id="' . $e($base . '-input') . '" role="combobox"'
            . ' aria-expanded="false" aria-controls="' . $e($base . '-list') . '" aria-autocomplete="list"'
            . ' aria-describedby="' . $e($base . '-help') . '" aria-label="' . $e($placeholder) . '"'
            . ' placeholder="' . $e($placeholder) . '" autocomplete="off" autocapitalize="off" spellcheck="false" enterkeyhint="go">'
            . '<button type="button" class="gk-palette-close" aria-label="' . $e($close) . '" title="' . $e($close) . '">'
            . self::glyph('close') . '</button></div>';
        $html .= '<div class="gk-palette-list" id="' . $e($base . '-list') . '" role="listbox" aria-label="' . $e(Lang::t('js.palette_results')) . '"></div>';
        $html .= '<p class="gk-palette-empty" hidden></p>';
        $html .= '<div class="gk-palette-status" hidden></div>';
        $html .= '<p class="gk-sr-only" id="' . $e($base . '-help') . '">' . $e(Lang::t('js.palette_help')) . '</p>';
        $html .= '<p class="gk-sr-only" aria-live="polite" data-gk-palette-live></p>';
        $html .= $this->foot();
        $html .= '<script type="application/json" data-gk-palette-config>' . $this->configJson() . '</script>';
        return $html . '</dialog>';
    }

    /** The line of keys under the list: for the eye — the input's description says the same in words. */
    private function foot(): string
    {
        $e = self::escaper();
        $k = static fn (string $text, string $attr = ''): string => '<kbd class="gk-palette-kbd"' . $attr . '>' . $e($text) . '</kbd>';
        return '<div class="gk-palette-foot" aria-hidden="true">'
            . '<span>' . $k('↑') . $k('↓') . ' ' . $e(Lang::t('js.palette_foot_move')) . '</span>'
            . '<span>' . $k('↵') . ' ' . $e(Lang::t('js.palette_foot_open')) . '</span>'
            . '<span>' . $k('Ctrl', ' data-gk-palette-mod') . $k('↵') . ' ' . $e(Lang::t('js.palette_foot_tab')) . '</span>'
            . '<span>' . $k('Esc') . ' ' . $e(Lang::t('js.palette_foot_close')) . '</span>'
            . '</div>';
    }

    /**
     * What the script reads, as JSON in a <script type="application/json">. Every <, >, & and quote is
     * written as < and so on: a label holding "</script>" cannot end the element.
     */
    private function configJson(): string
    {
        $cfg = [
            'id'      => $this->id,
            'hotkey'  => $this->hotkey,
            'slash'   => $this->slash,
            'sidebar' => $this->sidebar,
            'recent'  => $this->recent,
            'max'     => $this->maxPerGroup,
            'items'   => $this->items,
            'search'  => $this->searches,
        ];
        $json = json_encode($cfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
            | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        return $json === false ? '{}' : $json;
    }

    // ── Taking entries in ───────────────────────────────────────────────────

    /** @param array<string,mixed> $opts */
    private function addItem(string $label, string $href, array $opts, string $where): void
    {
        $label = trim((string) preg_replace('/\s+/u', ' ', $label) ?: $label);
        if ($label === '') {
            $this->warn("$where has no label — it was left out.");
            return;
        }
        $item = ['label' => $label];
        $href = trim($href);
        if ($href !== '' && self::unsafeUrl($href)) {
            $this->warn("$where links to a " . self::scheme($href) . ': URL — it was left out.');
            return;
        }
        if ($href !== '') $item['href'] = $href;

        foreach ($opts as $key => $value) {
            $key = (string) $key;
            if (!in_array($key, self::ITEM_OPTS, true)) {
                $this->warn("$where has the option '$key', which a palette entry does not know — it was ignored.");
                continue;
            }
            if ($value === null || $value === '' || $value === []) continue;
            switch ($key) {
                case 'icon':
                    if (is_string($value) && preg_match('/^[a-z0-9_]{1,40}$/', $value)) $item['icon'] = $value;
                    else $this->badOpt($where, $key, $value, 'a Material icon name');
                    break;
                case 'group':
                case 'hint':
                case 'shortcut':
                    if (is_scalar($value) && !is_bool($value) && trim((string) $value) !== '') $item[$key] = trim((string) $value);
                    else $this->badOpt($where, $key, $value, 'text');
                    break;
                case 'keywords':
                    $words = is_string($value) ? [$value] : (is_array($value) ? $value : null);
                    if ($words === null) {
                        $this->badOpt($where, $key, $value, 'text or a list of words');
                        break;
                    }
                    $clean = [];
                    foreach ($words as $w) {
                        if (is_scalar($w) && !is_bool($w) && trim((string) $w) !== '') $clean[] = trim((string) $w);
                    }
                    if ($clean !== []) $item['keywords'] = array_values(array_unique($clean));
                    break;
                case 'action':
                    if (is_string($value) && preg_match('/^[A-Za-z][A-Za-z0-9_.:-]{0,63}$/', $value)) {
                        $item['action'] = $value;
                    } else {
                        $this->badOpt($where, $key, $value, 'a name (letters, digits, - _ . :) — the entry was left out');
                        return;
                    }
                    break;
            }
        }

        if (isset($item['action'], $item['href'])) {
            $this->warn("$where has a link and an action — it is a link; the action '{$item['action']}' was ignored.");
            unset($item['action']);
        }
        if (!isset($item['href']) && !isset($item['action'])) {
            $this->warn("$where has neither a link nor an action — it was left out.");
            return;
        }
        $this->items[] = $item;
    }

    private function intIn(mixed $value, int $min, int $max, int $default, string $what): int
    {
        if (is_int($value) && $value >= $min && $value <= $max) return $value;
        $shown = is_scalar($value) ? var_export($value, true) : get_debug_type($value);
        $this->warn("$what takes a whole number from $min to $max, not $shown — it is $default.");
        return $default;
    }

    private function badOpt(string $where, string $key, mixed $value, string $wants): void
    {
        $shown = is_scalar($value) ? var_export($value, true) : get_debug_type($value);
        $this->warn("$where has '$key' => $shown — it takes $wants. It was ignored.");
    }

    private function warn(string $message): void
    {
        trigger_error("GridKit: command palette '{$this->id}': $message", E_USER_WARNING);
    }

    // ── Small helpers ───────────────────────────────────────────────────────

    /**
     * A link a click would run instead of follow. Browsers drop tabs, line breaks and control characters
     * in a scheme ("java\tscript:"), so they are dropped before the scheme is read.
     */
    private static function unsafeUrl(string $url): bool
    {
        return preg_match('/^(javascript|vbscript|data):/i', self::bare($url)) === 1;
    }

    private static function scheme(string $url): string
    {
        return strtolower((string) strstr(self::bare($url), ':', true));
    }

    private static function bare(string $url): string
    {
        return (string) preg_replace('/[\x00-\x20\x7f]+/', '', $url);
    }

    /** 'mod+shift+p' → "Ctrl Shift P" — the words on a keyboard that is not a Mac's. */
    private static function keyWords(string $combo): string
    {
        $names = ['mod' => 'Ctrl', 'ctrl' => 'Ctrl', 'meta' => 'Meta', 'alt' => 'Alt', 'shift' => 'Shift'];
        $out = [];
        foreach (explode('+', $combo) as $part) {
            $out[] = $names[$part] ?? strtoupper($part);
        }
        return implode(' ', array_unique($out));
    }

    /** 'mod+k' → "Control+K", the way aria-keyshortcuts spells it. */
    private static function ariaKeys(string $combo): string
    {
        $names = ['mod' => 'Control', 'ctrl' => 'Control', 'meta' => 'Meta', 'alt' => 'Alt', 'shift' => 'Shift'];
        $out = [];
        foreach (explode('+', $combo) as $part) {
            $out[] = $names[$part] ?? strtoupper($part);
        }
        return implode('+', array_unique($out));
    }

    private static function flag(mixed $value): ?bool
    {
        if (is_bool($value)) return $value;
        if ($value === 0 || $value === 1) return (bool) $value;
        if (is_string($value)) {
            $v = strtolower(trim($value));
            if (in_array($v, ['1', 'true', 'yes', 'on'], true)) return true;
            if (in_array($v, ['0', 'false', 'no', 'off', ''], true)) return false;
        }
        return null;
    }

    /** The two symbols the palette draws itself, so they do not wait for the icon font. Decoration. */
    private static function glyph(string $name): string
    {
        $path = $name === 'search'
            ? '<circle cx="11" cy="11" r="7"/><line x1="20" y1="20" x2="16.2" y2="16.2"/>'
            : '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>';
        return '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor"'
            . ' stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false">' . $path . '</svg>';
    }

    /** An id as a piece of an HTML id: as it is when it is safe, else a short hash of it. */
    private static function key(string $id): string
    {
        return preg_match('/^[A-Za-z0-9_-]{1,64}$/', $id) ? $id : 'h' . substr(md5($id), 0, 12);
    }

    private static function escaper(): \Closure
    {
        return static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
