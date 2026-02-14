<?php
declare(strict_types=1);

namespace GridKit;

class Button
{
    /**
     * Render a button/link element
     *
     * @param string $label Text label (empty for icon-only)
     * @param array $opts Options:
     *   - icon: Material Icon name (e.g. 'edit', 'delete', 'send')
     *   - variant: 'filled'|'outlined'|'text'|'tonal' (default: 'filled')
     *   - color: 'primary'|'success'|'warning'|'danger'|'neutral' (default: 'primary')
     *   - size: 'sm'|'md'|'lg' (default: 'md')
     *   - disabled: bool
     *   - href: string (renders <a> instead of <button>)
     *   - target: string (for links, e.g. '_blank')
     *   - type: 'button'|'submit'|'reset' (default: 'button')
     *   - id: string
     *   - class: string (additional classes)
     *   - title: string (tooltip)
     *   - onclick: string
     *   - data: array of data-attributes ['action' => 'save']
     *   - iconPosition: 'left'|'right' (default: 'left')
     *   - loading: bool (shows spinner)
     *   - badge: string|int (small badge on button)
     *   - fullWidth: bool
     *   - shape: 'rounded'|'pill'|'circle'|'square' (default: 'rounded')
     */
    public static function render(string $label = '', array $opts = []): string
    {
        $e = fn(string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

        $icon = $opts['icon'] ?? '';
        $variant = $opts['variant'] ?? 'filled';
        $color = $opts['color'] ?? 'primary';
        $size = $opts['size'] ?? 'md';
        $disabled = $opts['disabled'] ?? false;
        $href = $opts['href'] ?? '';
        $type = $opts['type'] ?? 'button';
        $iconPos = $opts['iconPosition'] ?? 'left';
        $loading = $opts['loading'] ?? false;
        $fullWidth = $opts['fullWidth'] ?? false;
        $shape = $opts['shape'] ?? 'rounded';

        // Build class list
        $classes = ['gk-btn'];
        $classes[] = 'gk-btn-' . $variant;
        $classes[] = 'gk-btn-' . $color;
        if ($size !== 'md') $classes[] = 'gk-btn-' . $size;
        if ($disabled) $classes[] = 'disabled';
        if ($loading) $classes[] = 'gk-btn-loading';
        if ($fullWidth) $classes[] = 'gk-btn-full';
        if ($label === '' && $icon !== '') $classes[] = 'gk-btn-icon-only';
        if ($shape !== 'rounded') $classes[] = 'gk-btn-' . $shape;
        if (!empty($opts['class'])) $classes[] = $opts['class'];

        $cls = implode(' ', $classes);

        // Build icon HTML
        $iconHtml = '';
        if ($icon !== '') {
            $iconSize = match($size) { 'sm' => '16', 'lg' => '22', default => '18' };
            $iconHtml = '<span class="material-icons" style="font-size:' . $iconSize . 'px">' . $e($icon) . '</span>';
        }

        // Loading spinner
        if ($loading) {
            $iconHtml = '<span class="gk-btn-spinner"></span>';
        }

        // Badge
        $badgeHtml = '';
        if (isset($opts['badge'])) {
            $badgeHtml = '<span class="gk-btn-badge">' . $e((string)$opts['badge']) . '</span>';
        }

        // Content assembly
        $inner = '';
        if ($iconPos === 'left') {
            $inner = $iconHtml . ($label !== '' ? '<span>' . $e($label) . '</span>' : '') . $badgeHtml;
        } else {
            $inner = ($label !== '' ? '<span>' . $e($label) . '</span>' : '') . $iconHtml . $badgeHtml;
        }

        // Data attributes
        $dataAttrs = '';
        if (!empty($opts['data'])) {
            foreach ($opts['data'] as $k => $v) {
                $dataAttrs .= ' data-' . $e($k) . '="' . $e((string)$v) . '"';
            }
        }

        // Extra attributes
        $extras = '';
        if (!empty($opts['id'])) $extras .= ' id="' . $e($opts['id']) . '"';
        if (!empty($opts['title'])) $extras .= ' title="' . $e($opts['title']) . '"';
        if (!empty($opts['onclick'])) $extras .= ' onclick="' . $e($opts['onclick']) . '"';
        if (!empty($opts['target'])) $extras .= ' target="' . $e($opts['target']) . '"';

        if ($href !== '' && !$disabled) {
            return '<a href="' . $e($href) . '" class="' . $cls . '"' . $extras . $dataAttrs . '>' . $inner . '</a>';
        }

        $disabledAttr = $disabled ? ' disabled' : '';
        return '<button type="' . $e($type) . '" class="' . $cls . '"' . $disabledAttr . $extras . $dataAttrs . '>' . $inner . '</button>';
    }

    /** Shortcut: icon-only button */
    public static function icon(string $icon, array $opts = []): string
    {
        $opts['icon'] = $icon;
        return self::render('', $opts);
    }

    /**
     * Floating Action Button (FAB)
     *
     * @param string $icon Material Icon name
     * @param array $opts Options:
     *   - size: 'sm'|'md'|'lg' (default: 'md' = 56px)
     *   - color: 'primary'|'success'|'warning'|'danger'|'neutral' (default: 'primary')
     *   - variant: 'filled'|'tonal'|'outlined' (default: 'filled')
     *   - extended: bool (pill shape with icon + label)
     *   - label: string (text for extended FAB)
     *   - href, onclick, id, class, title, data, disabled
     */
    public static function fab(string $icon, array $opts = []): string
    {
        $size = $opts['size'] ?? 'md';
        $extended = $opts['extended'] ?? false;
        $label = $opts['label'] ?? '';

        $opts['icon'] = $icon;
        $opts['variant'] = $opts['variant'] ?? 'filled';
        $opts['color'] = $opts['color'] ?? 'primary';

        // Build FAB-specific classes
        $fabClass = 'gk-fab';
        if ($size === 'sm') $fabClass .= ' gk-fab-sm';
        if ($size === 'lg') $fabClass .= ' gk-fab-lg';
        if ($extended) $fabClass .= ' gk-fab-extended';

        $opts['class'] = trim(($opts['class'] ?? '') . ' ' . $fabClass);

        // For non-extended FAB, render as icon-only
        if (!$extended) {
            return self::render('', $opts);
        }

        // Extended FAB: render with label
        return self::render($label, $opts);
    }

    /** Render a button group */
    public static function group(array $buttons): string
    {
        return '<div class="gk-btn-group">' . implode('', $buttons) . '</div>';
    }
}
