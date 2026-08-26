<?php
declare(strict_types=1);
namespace GridKit;

class Layout {
    private static string $mode = 'header-first';
    
    public static function mode(string $mode): void {
        self::$mode = $mode; // 'header-first' or 'sidebar-first'
    }
    
    public static function getMode(): string {
        return self::$mode;
    }
    
    public static function attributes(): string {
        return 'data-gk-layout="' . htmlspecialchars(self::$mode) . '"';
    }
    
    /** Version from the VERSION file, read once. */
    public static function version(): string
    {
        static $v = null;
        if ($v === null) {
            $file = dirname(__DIR__) . '/VERSION';
            $v = is_readable($file) ? trim((string)file_get_contents($file)) : '0';
        }
        return $v;
    }

    /**
     * Path to a GridKit file with a cache-busting stamp appended.
     *
     * Without that suffix, CDNs and browsers keep serving the old file after an
     * update — on gridkit.ssi.at a themes.css from 11 March was still sitting
     * in the cache while the page already reported 1.28.0. The theme switcher
     * then set data-gk-theme correctly, but the CSS that was delivered did not
     * know the colors: it looked as if the theme were broken. With
     * ?v=<VERSION> every release fetches the files anew.
     */
    public static function asset(string $path): string
    {
        // The file's modification timestamp is the more precise stamp than the
        // release version: it changes exactly when the file actually changes.
        // With the bare version, a changed file stays stuck behind the old
        // parameter during development, and a hotfix to a single file would go
        // unnoticed without a version bump.
        // The test used to be `$tail !== $path` — did preg_replace change
        // anything? For '../css/gridkit.css' it does, so that spelling got the
        // timestamp. For the bare 'css/gridkit.css' the capture equals the
        // whole input, nothing changes, and the branch was skipped — which is
        // exactly the form skeleton.php and GRIDKIT_SKILL.md tell you to use.
        // So the one path everybody copies was the one that never got the
        // precise stamp the comment above promises. Match instead of compare.
        $root = dirname(__DIR__);
        $tail = preg_match('#^(?:.*/)?((?:css|js|vendor)/.*)$#', $path, $m) ? $m[1] : null;
        $file = $root . '/' . ltrim((string)$tail, '/');
        $stamp = ($tail !== null && is_file($file))
            ? (string)filemtime($file)
            : self::version();

        $sep = str_contains($path, '?') ? '&' : '?';
        return htmlspecialchars($path . $sep . 'v=' . $stamp, ENT_QUOTES, 'UTF-8');
    }

    // Convenience: body tag with all attributes (theme + layout)
    public static function bodyTag(string $class = ''): string {
        $attrs = self::attributes();
        // Include the theme attributes when the Theme class is available
        if (class_exists('\GridKit\Theme')) {
            $attrs .= ' ' . Theme::attributes();
        }
        $cls = $class ? ' class="' . htmlspecialchars($class) . '"' : '';
        return '<body ' . $attrs . $cls . '>';
    }
}
