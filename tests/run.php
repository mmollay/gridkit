<?php
/**
 * GridKit test runner — deliberately zero-dependency.
 *
 * GridKit promises "no build step, no dependencies". A test suite that needs
 * Composer and PHPUnit to run would quietly break that promise for anyone who
 * clones the repo. This runner is ~60 lines and needs nothing but PHP.
 *
 *   php tests/run.php            run everything
 *   php tests/run.php lang       run only tests/lang.test.php
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

require __DIR__ . '/../autoload.php';

final class T
{
    public static int $passed = 0;
    /** @var list<string> */
    public static array $failures = [];
    public static string $current = '';

    public static function ok(bool $cond, string $what): void
    {
        if ($cond) { self::$passed++; return; }
        self::$failures[] = self::$current . ' — ' . $what;
    }

    public static function eq(mixed $actual, mixed $expected, string $what): void
    {
        self::ok($actual === $expected, sprintf(
            '%s (expected %s, got %s)', $what,
            var_export($expected, true), var_export($actual, true)
        ));
    }

    public static function contains(string $haystack, string $needle, string $what): void
    {
        self::ok(str_contains($haystack, $needle), $what . ' — missing: ' . $needle);
    }

    public static function notContains(string $haystack, string $needle, string $what): void
    {
        self::ok(!str_contains($haystack, $needle), $what . ' — should not contain: ' . $needle);
    }

    /** Capture everything a component echoes. */
    public static function capture(callable $fn): string
    {
        ob_start();
        try { $fn(); }
        finally { $out = ob_get_clean(); }
        return (string) $out;
    }
}

$filter = $argv[1] ?? '';
$files  = glob(__DIR__ . '/*.test.php') ?: [];
sort($files);

foreach ($files as $file) {
    $name = basename($file, '.test.php');
    if ($filter !== '' && $name !== $filter) continue;

    $tests = require $file;
    foreach ($tests as $label => $fn) {
        T::$current = "$name/$label";
        try {
            $fn();
        } catch (\Throwable $e) {
            T::$failures[] = T::$current . ' — threw ' . get_class($e) . ': ' . $e->getMessage();
        }
    }
}

echo "\n";
if (T::$failures) {
    echo "FAILED — " . count(T::$failures) . " problem(s), " . T::$passed . " assertion(s) passed\n\n";
    foreach (T::$failures as $f) echo "  ✗ $f\n";
    echo "\n";
    exit(1);
}
echo "ok — " . T::$passed . " assertions passed\n\n";
exit(0);
