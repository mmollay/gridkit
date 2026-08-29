<?php
declare(strict_types=1);

namespace GridKit;

/**
 * GridKit Lang — Minimal i18n for zero-dependency frameworks.
 *
 * Usage:
 *   Lang::set('en');                          // Switch locale
 *   Lang::load('en', [...]);                  // Load translations
 *   Lang::loadFile(__DIR__.'/../lang/en.php'); // Load from file
 *   Lang::t('table.search');                  // Translate
 *   Lang::t('table.selected', ['n' => 5]);    // With parameters
 *   Lang::locale();                           // Get current locale
 */
class Lang
{
    private static string $locale = 'en';
    private static array $strings = [];

    /** Set the active locale */
    public static function set(string $locale): void
    {
        self::$locale = $locale;
    }

    /** Get the active locale */
    public static function locale(): string
    {
        return self::$locale;
    }

    /** Load translations for a locale */
    public static function load(string $locale, array $translations): void
    {
        self::$strings[$locale] = array_merge(self::$strings[$locale] ?? [], $translations);
    }

    /** Load translations from a PHP file that returns an array */
    public static function loadFile(string $path): void
    {
        if (!file_exists($path)) return;
        $locale = pathinfo($path, PATHINFO_FILENAME); // e.g. 'en' from 'en.php'
        $data = require $path;
        if (is_array($data)) {
            self::load($locale, $data);
        }
    }

    /** Load all language files from a directory */
    public static function loadDir(string $dir): void
    {
        if (!is_dir($dir)) return;
        foreach (glob($dir . '/*.php') as $file) {
            self::loadFile($file);
        }
    }

    /**
     * Translate a key. Returns the key itself if no translation found.
     * Supports {placeholder} replacement.
     */
    public static function t(string $key, array $params = []): string
    {
        $text = self::$strings[self::$locale][$key]
             ?? self::$strings['en'][$key]
             ?? $key;

        if ($params) {
            foreach ($params as $k => $v) {
                $text = str_replace('{' . $k . '}', (string)$v, $text);
            }
        }

        return $text;
    }

    /**
     * Output translations as a JS object for GK.lang.
     * Call this in your HTML <head> or before gridkit.js.
     */
    public static function jsConfig(): string
    {
        $jsKeys = [];
        // Same fallback order t() uses: English is the floor, the active locale
        // overrides it key by key. Without the floor a locale GridKit does not
        // ship (fr, es, ...) emitted an empty catalogue, and every browser
        // string came out as its raw key while the server side quietly read
        // English. JSON_FORCE_OBJECT keeps an empty payload a {} rather than an
        // [], which is truthy and survives `window.GK_LANG || {}`.
        $catalogue = array_merge(self::$strings['en'] ?? [], self::$strings[self::$locale] ?? []);
        foreach ($catalogue as $key => $val) {
            if (str_starts_with($key, 'js.')) {
                $jsKeys[substr($key, 3)] = $val;
            } elseif (str_starts_with($key, 'action.')) {
                // The names for icon-only controls. The client renders the same
                // row buttons the server does and has to name them the same
                // way, so this one catalogue serves both rather than a second
                // copy under js.* drifting away from the first.
                $jsKeys['action_' . substr($key, 7)] = $val;
            } elseif (str_starts_with($key, 'pagination.')) {
                // The pager is rendered by both sides, so both have to name its
                // controls the same way. They did not: the server said
                // "Previous" and "Page 1 of 3" from pagination.*, the client
                // said "Previous page" and "Page 1" from a separate js.* pair.
                // A screen reader heard one wording before a sort and another
                // after. Found by the parity harness, which is what it is for.
                $jsKeys['pagination_' . substr($key, 11)] = $val;
            } elseif (str_starts_with($key, 'format.')) {
                // Number and date shapes, for exactly the same reason as
                // action.* above — and this pair had already drifted. The
                // server built a currency cell from format.decimal,
                // format.thousands and format.currency; the browser, having
                // none of them, formatted with a hardcoded "de-DE". So a
                // sorted or filtered English table redrew "€1,200.00" as
                // "1.200,00 €" on the first click. Same catalogue, one shape.
                $jsKeys['format_' . substr($key, 7)] = $val;
            }
        }
        $json = json_encode($jsKeys, JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT);
        return '<script>window.GK_LANG=' . $json . ';</script>';
    }
}
