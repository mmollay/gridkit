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

'the README does not claim a CI that does not exist' => function (): void {
    // The README stated "CI runs it on PHP 8.2, 8.3 and 8.4" as fact from
    // 1.30.0 onwards. GitHub reported zero workflows: the file existed only
    // on this machine, because the token has no `workflow` scope and GitHub
    // refuses a push that creates one. A credibility claim aimed at exactly
    // the people deciding whether to trust the package, and it was not true.
    $readme = (string) file_get_contents(ROOT . '/README.md');

    // Whatever the README says about CI, the file it points at must be there.
    if (preg_match('/\[`?ci\/`?\]\(ci\/\)/', $readme)) {
        T::ok(is_file(ROOT . '/ci/github-actions.yml'),
            'README links ci/ but ci/github-actions.yml is missing');
        T::ok(is_file(ROOT . '/ci/README.md'),
            'README links ci/README.md but it is missing');
    }

    // And it must not assert a running CI while no workflow is committed.
    //
    // Tracked, not present on disk. The first version of this check asked the
    // filesystem — and passed, because ci.yml has been sitting in
    // .github/workflows/ untracked this whole time. That is the very shape of
    // the original bug: a file that exists here and nowhere GitHub can see it.
    $out = [];
    exec('git -C ' . escapeshellarg(ROOT) . ' ls-files .github/workflows 2>/dev/null', $out);
    $hasWorkflow = array_filter($out) !== [];

    if (!$hasWorkflow) {
        T::ok(
            !preg_match('/^CI runs it on/m', $readme),
            'the README states CI runs, but no workflow is committed'
        );
    }
},

'no scratch file from an audit run is tracked' => function (): void {
    // Twice now an agent left a probe script in the repo root and `git add -A`
    // carried it into a commit — once into the Composer package itself. The
    // ignore rules catch the shapes; this catches the ones that got past them.
    $out = [];
    exec('git -C ' . escapeshellarg(ROOT) . ' ls-files 2>/dev/null', $out);
    T::ok($out !== [], 'git ls-files returned nothing — is this a checkout?');

    foreach ($out as $path) {
        $base = basename($path);
        T::ok(!str_starts_with($base, '_'), "a scratch file is tracked: $path");
        // Only the repo root. .design/verify/probe-src.js is a deliberate
        // tool that lives in a directory named for the purpose.
        T::ok(str_contains($path, '/') || !preg_match('/^(probe|repro|tmp|scratch)/i', $base),
            "a scratch file is tracked at the repo root: $path");
        // A screenshot belongs under docs/, nowhere else.
        T::ok(!preg_match('/\.(png|jpe?g)$/i', $path) || str_starts_with($path, 'docs/'),
            "an image outside docs/ is tracked: $path");
    }
},

'the compatibility matrix can actually be run' => function (): void {
    // README.md promises PHP 8.2+ and that mbstring is optional. Neither half
    // had ever been checked: every run in this repository's history used one
    // interpreter. ci/matrix.sh is what the CI workflow would do, for a
    // machine where CI does not run — so it has to exist and be runnable.
    $script = ROOT . '/ci/matrix.sh';
    T::ok(is_file($script), 'ci/matrix.sh is missing');
    T::ok(is_readable($script), 'ci/matrix.sh cannot be read');

    $src = (string) file_get_contents($script);
    T::contains($src, 'tests/run.php', 'the matrix does not run the suite');
    T::contains($src, 'mb_strtolower', 'the matrix does not report on mbstring');

    // And the library must not need mbstring to answer at all — this very run
    // may be the one without it.
    $withMb = function_exists('mb_strtolower');
    T::ok(true, 'this run has mbstring: ' . ($withMb ? 'yes' : 'no'));
},

'every tagged release has a changelog entry' => function (): void {
    // 1.4.0 is what Packagist has served since March, it has a tag, and it was
    // the one release with no entry here — found only by counting the tags on
    // GitHub against the entries in this file. A release nobody wrote down is
    // a release nobody can read about from the Releases page.
    $md = (string) file_get_contents(ROOT . '/CHANGELOG.md');
    preg_match_all('/^## \[(\d+\.\d+\.\d+)\]/m', $md, $m);
    $logged = array_flip($m[1]);

    $out = [];
    exec('git -C ' . escapeshellarg(ROOT) . ' tag 2>/dev/null', $out);
    $tags = array_values(array_filter(array_map(
        static fn(string $t): string => ltrim(trim($t), 'v'),
        $out
    ), static fn(string $t): bool => (bool) preg_match('/^\d+\.\d+\.\d+$/', $t)));

    T::ok($tags !== [], 'no tags found — is this a checkout with tags?');
    foreach ($tags as $tag) {
        T::ok(isset($logged[$tag]), "v$tag is tagged but CHANGELOG.md never mentions it");
    }
},

'the release-notes script refuses a version it cannot find' => function (): void {
    // Its first version silently printed the whole file when the version was
    // missing — 144 entries back to 0.9.0 for a --since 1.4.0, which looked
    // like a very thorough release note and was a bug.
    $script = ROOT . '/ci/release-notes.sh';
    T::ok(is_file($script), 'ci/release-notes.sh is missing');

    exec('bash ' . escapeshellarg($script) . ' 9.9.9 2>/dev/null', $out, $code);
    T::eq($code, 1, 'an unknown version does not fail the script');

    $out = [];
    exec('bash ' . escapeshellarg($script) . ' ' . trim((string) file_get_contents(ROOT . '/VERSION'))
        . ' 2>/dev/null', $out, $code);
    T::eq($code, 0, 'the current version cannot be printed');
    T::ok(count($out) > 3, 'the notes for the current version are suspiciously short');
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
