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
        $themes = ['indigo', 'ocean', 'forest', 'rose', 'amber', 'slate'];
        $colors = [
            'indigo' => '#6366f1', 'ocean' => '#0ea5e9', 'forest' => '#059669',
            'rose' => '#ec4899', 'amber' => '#d97706', 'slate' => '#475569'
        ];
        $html = '<div class="gk-theme-switcher">';
        $html .= '<div class="gk-theme-colors">';
        foreach ($themes as $t) {
            $active = ($t === self::$theme) ? ' gk-theme-active' : '';
            $html .= '<button class="gk-theme-dot' . $active . '" data-gk-set-theme="' . $t . '" style="background:' . $colors[$t] . '" title="' . ucfirst($t) . '"></button>';
        }
        $html .= '</div>';
        $html .= '<button class="gk-mode-toggle" data-gk-toggle-mode title="Dark/Light Mode">';
        $html .= '<span class="material-icons gk-mode-light">light_mode</span>';
        $html .= '<span class="material-icons gk-mode-dark">dark_mode</span>';
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
