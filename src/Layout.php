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
    
    /** Version aus der VERSION-Datei, einmal gelesen. */
    public static function version(): string
    {
        static $v = null;
        if ($v === null) {
            $datei = dirname(__DIR__) . '/VERSION';
            $v = is_readable($datei) ? trim((string)file_get_contents($datei)) : '0';
        }
        return $v;
    }

    /**
     * Pfad zu einer GridKit-Datei mit angehaengter Version.
     *
     * Ohne diesen Zusatz liefern CDNs und Browser nach einem Update weiter die
     * alte Datei aus — bei gridkit.ssi.at stand eine themes.css vom 11. Maerz
     * noch im Cache, waehrend die Seite bereits 1.28.0 meldete. Der
     * Theme-Umschalter setzte dann korrekt data-gk-theme, aber das
     * ausgelieferte CSS kannte die Farben nicht: es sah aus, als sei das
     * Theme kaputt. Mit ?v=<VERSION> holt jeder Release die Dateien neu.
     */
    public static function asset(string $pfad): string
    {
        // Der Aenderungszeitstempel der Datei ist der genauere Stempel als die
        // Release-Version: er wechselt genau dann, wenn sich die Datei
        // tatsaechlich aendert. Mit der blossen Version bleibt beim Entwickeln
        // eine geaenderte Datei hinter dem alten Parameter haengen, und ein
        // Hotfix an einer einzelnen Datei ginge ohne Versionssprung unter.
        $wurzel = dirname(__DIR__);
        $rest = preg_replace('#^.*?((?:css|js|vendor)/.*)$#', '$1', $pfad);
        $datei = $wurzel . '/' . ltrim((string)$rest, '/');
        $stempel = ($rest !== null && $rest !== $pfad && is_file($datei))
            ? (string)filemtime($datei)
            : self::version();

        $trenner = str_contains($pfad, '?') ? '&' : '?';
        return htmlspecialchars($pfad . $trenner . 'v=' . $stempel, ENT_QUOTES, 'UTF-8');
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
