<?php
/**
 * Package integrity.
 *
 * These are the things that break silently between a working checkout and a
 * published release: a VERSION that no longer matches the changelog, an asset
 * the loader points at but nobody shipped, a stylesheet truncated by a bad
 * merge, a German string that slipped back into a public signature.
 */

declare(strict_types=1);

use GridKit\{Lang, Layout};

const ROOT = __DIR__ . '/..';

/** @return array<string,callable> */
return [

'the shipped assets exist and are not truncated' => function (): void {
    foreach (['css/gridkit.css', 'css/themes.css', 'js/gridkit.js'] as $rel) {
        $path = ROOT . '/' . $rel;
        T::ok(is_file($path), "$rel is missing");
        if (!is_file($path)) continue;

        $src = (string) file_get_contents($path);
        T::ok(strlen($src) > 1000, "$rel is suspiciously small");

        // Counting braces only works for CSS. In JS they also live inside
        // strings, regexes and template literals, so a parser has the last word.
        if (str_ends_with($rel, '.css')) {
            T::eq(substr_count($src, '{'), substr_count($src, '}'), "$rel has unbalanced braces");
        }
    }

    exec('node --version 2>/dev/null', $probe, $hasNode);
    if ($hasNode === 0) {
        exec('node --check ' . escapeshellarg(ROOT . '/js/gridkit.js') . ' 2>&1', $out, $code);
        T::eq($code, 0, 'js/gridkit.js does not parse: ' . implode(' ', $out));
    }
},

'the stylesheets pull in nothing from the network' => function (): void {
    foreach (['css/gridkit.css', 'css/themes.css'] as $rel) {
        $src = (string) file_get_contents(ROOT . '/' . $rel);
        T::ok(!preg_match('/@import\s+(url\()?["\']?https?:/i', $src),
            "$rel imports a remote stylesheet — that breaks the zero-dependency promise");
    }
},

'VERSION, composer.json and the changelog agree' => function (): void {
    $version = trim((string) file_get_contents(ROOT . '/VERSION'));
    T::ok((bool) preg_match('/^\d+\.\d+\.\d+$/', $version), "VERSION is not semver: $version");
    T::eq(Layout::version(), $version, 'Layout::version() should report the VERSION file');

    $changelog = (string) file_get_contents(ROOT . '/CHANGELOG.md');
    T::contains($changelog, $version, "CHANGELOG.md has no entry for $version");
},

'composer.json is valid and describes this package' => function (): void {
    $raw  = (string) file_get_contents(ROOT . '/composer.json');
    $json = json_decode($raw, true);
    T::ok(is_array($json), 'composer.json is not valid JSON');
    if (!is_array($json)) return;

    T::eq($json['name'] ?? null, 'mmollay/gridkit', 'package name');
    T::eq($json['license'] ?? null, 'MIT', 'license');
    T::ok(isset($json['autoload']['psr-4']['GridKit\\']), 'PSR-4 prefix is declared');
    T::ok(in_array('autoload.php', $json['autoload']['files'] ?? [], true),
        'autoload.php is registered so language files load');

    // Composer prints `suggest` to every user — it must be English.
    foreach ($json['suggest'] ?? [] as $key => $text) {
        T::ok(!preg_match('/[äöüÄÖÜß]/u', (string) $text), "suggest[$key] is not English");
    }
},

'every file the package ships is syntactically valid PHP' => function (): void {
    foreach (glob(ROOT . '/src/*.php') ?: [] as $file) {
        exec('php -l ' . escapeshellarg($file) . ' 2>&1', $out, $code);
        T::eq($code, 0, basename($file) . ': ' . implode(' ', $out));
        $out = [];
    }
},

'no German remains in a public method signature' => function (): void {
    // Default parameter values are the blind spot: they never show up in a
    // search for translation calls, and they render for every user regardless
    // of locale.
    foreach (glob(ROOT . '/src/*.php') ?: [] as $file) {
        $src = (string) file_get_contents($file);
        preg_match_all('/public (?:static )?function \w+\s*\(([^)]*)\)/s', $src, $m);
        foreach ($m[1] as $params) {
            T::ok(!preg_match('/=\s*[\'"][^\'"]*[äöüÄÖÜß][^\'"]*[\'"]/u', $params),
                basename($file) . ' has a German default parameter: ' . trim($params));
        }
    }
},

'language files are plain arrays with no side effects' => function (): void {
    foreach (glob(ROOT . '/lang/*.php') ?: [] as $file) {
        // Scanning tokens, not text: 'header.search' is a translation string,
        // not a call to header().
        $forbidden = [T_ECHO, T_PRINT, T_REQUIRE, T_REQUIRE_ONCE, T_INCLUDE, T_INCLUDE_ONCE];
        foreach (token_get_all((string) file_get_contents($file)) as $token) {
            if (is_array($token) && in_array($token[0], $forbidden, true)) {
                T::ok(false, basename($file) . ' has a side effect: ' . $token[1]);
            }
        }
        T::ok(is_array(require $file), basename($file) . ' does not return an array');
    }
},

'the agent skill documents the current version' => function (): void {
    $skill = ROOT . '/GRIDKIT_SKILL.md';
    T::ok(is_file($skill), 'GRIDKIT_SKILL.md is missing — it is a headline feature');
    if (!is_file($skill)) return;

    $src   = (string) file_get_contents($skill);
    $classes = array_map(
        static fn(string $f): string => basename($f, '.php'),
        glob(ROOT . '/src/*.php') ?: []
    );
    foreach ($classes as $class) {
        T::contains($src, $class, "GRIDKIT_SKILL.md never mentions $class");
    }
},

];
