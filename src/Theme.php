<?php
declare(strict_types=1);
namespace GridKit;

class Theme {
    private static string $theme = 'indigo';
    private static string $mode = 'light';

    /**
     * How tightly tables are set: '' (the default rhythm), 'konsole' (tighter,
     * for people who work in the tool all day) or 'weit' (roomier, for pages
     * that are read rather than worked). Independent of theme and mode.
     */
    private static string $density = '';

    /** Which density this page uses. Empty string leaves the default rhythm. */
    public static function density(string $density): void {
        self::$density = in_array($density, ['konsole', 'weit'], true) ? $density : '';
    }

    public static function set(string $theme, string $mode = 'light'): void {
        self::$theme = $theme;
        self::$mode = $mode;
    }

    public static function attributes(): string {
        return 'data-gk-theme="' . htmlspecialchars(self::$theme) . '"'
            . ' data-gk-mode="' . htmlspecialchars(self::$mode) . '"'
            . (self::$density !== '' ? ' data-gk-density="' . htmlspecialchars(self::$density) . '"' : '');
    }

    public static function bodyTag(string $class = ''): string {
        $cls = $class ? ' class="' . htmlspecialchars($class) . '"' : '';
        return '<body ' . self::attributes() . $cls . '>';
    }

    public static function switcher(): string {
        // Driven by available(), which is the list. The switcher used to carry
        // a second copy of the same six names and the same six hex values, so
        // a theme added to one appeared in the other only if you remembered
        // both — and the dots would have kept showing the old set.
        $e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $html = '<div class="gk-theme-switcher">';
        // 1.114.0: each group says what it is — the swatches choose an accent,
        // the buttons beside them the mode.
        $html .= '<div class="gk-theme-colors" role="group" aria-label="' . $e(Lang::t('theme.accent')) . '">';
        foreach (self::available() as $t => $meta) {
            $active = ($t === self::$theme) ? ' gk-theme-active' : '';
            // The dot is an empty <button> — a coloured circle and nothing
            // else. title was its only name, and GK.tip removes title on the
            // first hover, so it went silent the moment a pointer crossed it.
            // aria-pressed says which one is on; the colour alone cannot.
            // --gk-swatch hands the colour to the ring and the check mark of the
            // chosen one; the background stays inline for a page without the
            // stylesheet. type="button": inside a form a bare <button> submits it.
            $name = Lang::t('theme.choose', ['name' => $meta['name']]);
            $html .= '<button type="button" class="gk-theme-dot' . $active . '" data-gk-set-theme="' . $t . '"'
                   . ' style="--gk-swatch:' . $meta['color'] . ';background:' . $meta['color'] . '"'
                   . ' aria-pressed="' . ($active !== '' ? 'true' : 'false') . '"'
                   . ' aria-label="' . $e($name) . '"'
                   . ' title="' . $e($meta['name']) . '"></button>';
        }
        $html .= '</div>';
        // Both glyphs sit in the button at once and CSS shows one. Exposed,
        // a screen reader read the pair as "light_modedark_mode".
        $modeName = $e(Lang::t('theme.toggle_mode'));
        // Which mode is on was readable from the icon and nowhere else, and
        // both icons are aria-hidden — so the one control that switches light
        // and dark announced no state at all. Dark is the pressed state.
        // 1.114.0: the toggle and an "auto" button (follow the system) form one
        // group. The auto button is rendered hidden: gridkit.js shows it and turns
        // the group into light | dark | auto. A copy of gridkit.js without that
        // block (the PawBot dashboard keeps its own) keeps the plain toggle.
        $html .= '<div class="gk-mode-group" role="group" aria-label="' . $e(Lang::t('theme.mode')) . '">';
        $html .= '<button type="button" class="gk-mode-toggle" data-gk-toggle-mode'
               . ' aria-pressed="' . (self::$mode === 'dark' ? 'true' : 'false') . '"'
               . ' aria-label="' . $modeName . '" title="' . $modeName . '">';
        $html .= '<span class="material-icons gk-mode-light" aria-hidden="true">light_mode</span>';
        $html .= '<span class="material-icons gk-mode-dark" aria-hidden="true">dark_mode</span>';
        $html .= '</button>';
        $autoName = $e(Lang::t('theme.mode_auto'));
        $html .= '<button type="button" class="gk-mode-auto" data-gk-mode-auto aria-pressed="false"'
               . ' aria-label="' . $autoName . '" title="' . $autoName . '" hidden>'
               . '<span class="material-icons" aria-hidden="true">contrast</span></button>';
        $html .= '</div>';
        $html .= '</div>';
        return $html;
    }

    /**
     * The themes, with the accent each one has in light mode — the colour of its
     * swatch. 1.114.0: these are the accents themes.css really derives (OKLCH,
     * L 0.53; slate 0.40), not the brighter 500-steps they were before, so the
     * swatch shows the colour the buttons will have.
     */
    public static function available(): array {
        return [
            'indigo' => ['name' => 'Indigo', 'color' => '#4e5ad7'],
            'ocean'  => ['name' => 'Ocean',  'color' => '#00769f'],
            'forest' => ['name' => 'Forest', 'color' => '#0a7f60'],
            'rose'   => ['name' => 'Rose',   'color' => '#b13276'],
            'amber'  => ['name' => 'Amber',  'color' => '#a55115'],
            'slate'  => ['name' => 'Slate',  'color' => '#424852'],
        ];
    }
}
