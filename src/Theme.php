<?php
declare(strict_types=1);
namespace GridKit;

class Theme {
    private static string $theme = 'indigo';
    private static string $mode = 'light';

    public static function set(string $theme, string $mode = 'light'): void {
        self::$theme = $theme;
        self::$mode = $mode;
    }

    public static function attributes(): string {
        return 'data-gk-theme="' . htmlspecialchars(self::$theme) . '" data-gk-mode="' . htmlspecialchars(self::$mode) . '"';
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
        $html = '<div class="gk-theme-switcher">';
        $html .= '<div class="gk-theme-colors">';
        foreach (self::available() as $t => $meta) {
            $active = ($t === self::$theme) ? ' gk-theme-active' : '';
            // The dot is an empty <button> — a coloured circle and nothing
            // else. title was its only name, and GK.tip removes title on the
            // first hover, so it went silent the moment a pointer crossed it.
            // aria-pressed says which one is on; the colour alone cannot.
            $name = Lang::t('theme.choose', ['name' => $meta['name']]);
            $html .= '<button class="gk-theme-dot' . $active . '" data-gk-set-theme="' . $t . '"'
                   . ' style="background:' . $meta['color'] . '"'
                   . ' aria-pressed="' . ($active !== '' ? 'true' : 'false') . '"'
                   . ' aria-label="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '"'
                   . ' title="' . htmlspecialchars($meta['name'], ENT_QUOTES, 'UTF-8') . '"></button>';
        }
        $html .= '</div>';
        // Both glyphs sit in the button at once and CSS shows one. Exposed,
        // a screen reader read the pair as "light_modedark_mode".
        $modeName = htmlspecialchars(Lang::t('theme.toggle_mode'), ENT_QUOTES, 'UTF-8');
        $html .= '<button class="gk-mode-toggle" data-gk-toggle-mode aria-label="' . $modeName . '" title="' . $modeName . '">';
        $html .= '<span class="material-icons gk-mode-light" aria-hidden="true">light_mode</span>';
        $html .= '<span class="material-icons gk-mode-dark" aria-hidden="true">dark_mode</span>';
        $html .= '</button>';
        $html .= '</div>';
        return $html;
    }

    public static function available(): array {
        return [
            'indigo' => ['name' => 'Indigo', 'color' => '#6366f1'],
            'ocean'  => ['name' => 'Ocean',  'color' => '#0ea5e9'],
            'forest' => ['name' => 'Forest', 'color' => '#059669'],
            'rose'   => ['name' => 'Rose',   'color' => '#ec4899'],
            'amber'  => ['name' => 'Amber',  'color' => '#d97706'],
            'slate'  => ['name' => 'Slate',  'color' => '#475569'],
        ];
    }
}
