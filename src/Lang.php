<?php
declare(strict_types=1);

namespace GridKit;

/**
 * GRIDKit Lang — Minimal i18n for zero-dependency frameworks.
 *
 * Usage:
 *   Lang::set('en');                          // Switch locale
 *   Lang::load('en', [...]);                  // Load translations
 *   Lang::loadFile(__DIR__.'/../lang/en.php'); // Load from file
 *   Lang::t('table.search');                  // Translate
 *   Lang::t('bulk.selected', ['n' => 5]);     // With parameters
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
        foreach (self::$strings[self::$locale] ?? [] as $key => $val) {
            if (str_starts_with($key, 'js.')) {
                $jsKeys[substr($key, 3)] = $val;
            }
        }
        $json = json_encode($jsKeys, JSON_UNESCAPED_UNICODE);
        return '<script>window.GK_LANG=' . $json . ';</script>';
    }
}
