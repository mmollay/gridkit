<?php
declare(strict_types=1);
namespace GridKit;

class Layout {
    private static string $mode = 'header-first';
    
    public static function mode(string $mode): void {
        self::$mode = $mode; // 'header-first' oder 'sidebar-first'
    }
    
    public static function getMode(): string {
        return self::$mode;
    }
    
    public static function attributes(): string {
        return 'data-gk-layout="' . htmlspecialchars(self::$mode) . '"';
    }
    
    // Convenience: Body-Tag mit allen Attributen (Theme + Layout)
    public static function bodyTag(string $class = ''): string {
        $attrs = self::attributes();
        // Theme-Attribute mit einbauen wenn Theme gesetzt
        if (class_exists('\GridKit\Theme')) {
            $attrs .= ' ' . Theme::attributes();
        }
        $cls = $class ? ' class="' . htmlspecialchars($class) . '"' : '';
        return '<body ' . $attrs . $cls . '>';
    }
}
