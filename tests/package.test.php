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

    // A leading underscore means two different things. On a probe script left
    // behind by an agent it means "temporary"; on a partial it means "included,
    // never served". The rule cannot tell them apart, so the partials are named
    // here — a short list that has to be edited deliberately, which is the point.
    $partials = [
        'demo/_showcase.php',
        // The people of the side-sheet demo, included by demo/index.php and by
        // the sheet's endpoint — one list for both (1.91.0).
        'demo/form/_people.php',
    ];

    foreach ($out as $path) {
        if (in_array($path, $partials, true)) {
            continue;
        }
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

    // ci/farben.js measures what no string comparison can: whether an alias still
    // resolves to its role once themes.css is loaded on top. Nothing in the suite
    // runs it, so without this it could quietly disappear — and the fault it found
    // in 1.89.0 would come back unseen.
    T::ok(is_file(ROOT . '/ci/farben.js'), 'ci/farben.js is missing');
    T::ok(str_contains((string) file_get_contents(ROOT . '/ci/README.md'), 'ci/farben.js'),
        'ci/README.md does not mention ci/farben.js');

    $src = (string) file_get_contents($script);
    T::contains($src, 'tests/run.php', 'the matrix does not run the suite');
    T::contains($src, 'mb_strtolower', 'the matrix does not report on mbstring');

    // And the library must not need mbstring to answer at all. This used to be
    // `T::ok(true, 'this run has mbstring: yes')` — it computed the answer and
    // then asserted a literal, so it passed on every interpreter including one
    // that would have crashed on the first umlaut. The promise is about the
    // source, so check the source: an mb_* call is only allowed where the file
    // has worked out for itself that the extension is there.
    foreach (glob(ROOT . '/src/*.php') ?: [] as $file) {
        $src = (string) file_get_contents($file);
        if (!preg_match('/\bmb_[a-z_]+\s*\(/', $src)) continue;
        T::contains($src, "function_exists('mb_",
            basename($file) . ' calls mb_* with no function_exists guard —'
            . ' README.md promises mbstring is optional');
    }
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
    // The NEWEST entry, not any entry. "Contains" was satisfied by an older
    // release further down: a branch carrying the notes for 1.81.0 and a
    // VERSION of 1.80.3 was green — and merged like that, the new script and
    // stylesheet would have stayed behind the old cache key for a year.
    T::ok((bool) preg_match('/^## \[(\d+\.\d+\.\d+)\]/m', $changelog, $newest), 'CHANGELOG.md has no release heading');
    T::eq($newest[1] ?? '', $version, 'the newest CHANGELOG entry and VERSION name different releases');
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

/**
 * Nothing that is not the library may ride along into `vendor/`.
 *
 * `.htaccess` was doing exactly that, and its own first line says "GridKit —
 * the public site". It sets a cache policy for the whole directory it sits in,
 * rewrites /skill, and answers 403 for /tests, /ci and /.design — paths that
 * belong to whoever's site is serving it. Dropped into somebody's application,
 * those are their rules now. index.php, sitemap.xml, robots.txt and
 * favicon.ico had all been excluded for precisely this reason; .htaccess and
 * llms.txt were missed.
 *
 * The check reads .gitattributes rather than running `git archive`: the answer
 * is a decision recorded in a file, and reading the file needs no tar. Note
 * that `git check-attr` is NOT a substitute — it matches path patterns, while
 * archive prunes an entire directory when the directory itself is ignored, so
 * check-attr calls tests/foo.php "unspecified" for a file that never ships.
 */
'nothing outside the library ships to a composer require' => function (): void {
    $out = [];
    exec('git -C ' . escapeshellarg(ROOT) . ' ls-files 2>/dev/null', $out);
    T::ok($out !== [], 'git ls-files returned nothing — is this a checkout?');

    $ignored = [];
    foreach (explode("\n", (string) file_get_contents(ROOT . '/.gitattributes')) as $line) {
        if (preg_match('~^/(\S+)\s+export-ignore~', trim($line), $m)) {
            $ignored[$m[1]] = true;
        }
    }
    // Without this the loop below has nothing to compare against and passes on
    // everything: a broken pattern would read as "all clear".
    T::ok(count($ignored) > 5,
        'parsed ' . count($ignored) . ' export-ignore paths out of .gitattributes — '
        . 'too few to be the real file, so the pattern broke rather than the rules');

    // What a `composer require mmollay/gridkit` is allowed to contain. A new
    // entry at the repo root does not join this list by accident.
    $library = [
        'src', 'css', 'js', 'lang', 'skill',
        'autoload.php', 'composer.json', 'LICENSE',
        'README.md', 'CHANGELOG.md', 'GRIDKIT_SKILL.md', 'VERSION', 'skeleton.php',
    ];

    $tops = [];
    foreach ($out as $path) {
        $tops[explode('/', $path)[0]] = true;
    }

    foreach (array_keys($tops) as $top) {
        if (in_array($top, $library, true)) continue;
        T::ok(isset($ignored[$top]),
            "`$top` is tracked, is not part of the library, and carries no "
            . 'export-ignore — so it ships into every vendor/mmollay/gridkit');
    }
},

/**
 * The utility table states numbers — "4/6/8/12/16/20 px" — and nothing had
 * ever checked them against the stylesheet they describe. A scale is exactly
 * the kind of thing that gets nudged in CSS by someone who never opens the
 * documentation, and a table of wrong pixel values is worse than no table:
 * it is believed.
 *
 * The claims are read out of the document rather than restated here, so the
 * test cannot drift from the thing it checks. Writing this found one wrong
 * number that had just been added by hand — `gk-spacer-md` documented as 16 px
 * where the rule says 20.
 */
'the numbers the utility table states are the numbers in the stylesheet' => function (): void {
    $doc = (string) file_get_contents(ROOT . '/GRIDKIT_SKILL.md');
    $css = (string) file_get_contents(ROOT . '/css/gridkit.css');

    preg_match_all('/`gk-([a-z-]+)-\{([^}]+)\}`\s*→\s*([0-9\/]+)\s*px/u', $doc, $rows, PREG_SET_ORDER);
    // A pattern that stops matching would check nothing and report green.
    T::ok(count($rows) >= 3,
        'found ' . count($rows) . ' scales stated in the utility table; there were 4 '
        . 'when this was written, so a much smaller number means the pattern broke');

    foreach ($rows as [, $prefix, $keyList, $numList]) {
        $keys = array_map('trim', explode(',', $keyList));
        $nums = explode('/', $numList);
        T::eq(count($nums), count($keys),
            "gk-$prefix-{...} lists " . count($keys) . ' names and ' . count($nums)
            . ' numbers — the table cannot be read as pairs');
        if (count($nums) !== count($keys)) continue;

        foreach ($keys as $i => $key) {
            $cls = "gk-$prefix-$key";
            if (!preg_match('/\.' . preg_quote($cls, '/') . '\s*\{([^}]*)\}/', $css, $rule)) {
                T::ok(false, "the table names .$cls; css/gridkit.css has no such rule");
                continue;
            }
            $want = $nums[$i];
            $body = trim(preg_replace('/\s+/', ' ', $rule[1]));
            // 0 is written without a unit in CSS, everything else with px.
            $ok = $want === '0'
                ? (bool) preg_match('/:\s*0\s*;/', $rule[1])
                : str_contains($rule[1], $want . 'px');
            T::ok($ok, ".$cls is documented as {$want}px, but its rule reads: $body");
        }
    }
},

/**
 * A rule that can never apply.
 *
 * Twice in this stylesheet the same selector opened a rule twice at the top
 * level and the later one set every property the earlier one did — so the
 * earlier was dead, and the file stated two different answers with only the
 * second being true. `.gk-modal-large` said 860px and then 900px three lines
 * later; `.gk-field` said `margin-bottom: 20px` and then 16px. Both came from
 * changing a value by adding a rule instead of editing the one already there.
 *
 * Duplicates as such are fine and there are a dozen deliberate ones — a later
 * block that adds a border, a dark-mode block, a layout override, each with a
 * comment saying why. What this forbids is the pointless kind, so it needs no
 * list of exceptions to keep up to date.
 *
 * It reads only single-selector rules opened at column 0. That is a real
 * limit: an indented rule, a comma-separated selector list or one inside a
 * media query is invisible to it. It is enough to catch the shape that
 * actually occurred, and a parser that pretended to more would be lying.
 */
'no stylesheet rule is fully overridden by a later copy of itself' => function (): void {
    $lines = explode("\n", (string) file_get_contents(ROOT . '/css/gridkit.css'));

    $rules = [];   // selector => list of [line, [property, ...]]
    $open = null;
    $props = [];
    $startLine = 0;

    foreach ($lines as $i => $line) {
        if ($open === null) {
            if (preg_match('/^([.#\[][^{},]*?)\s*\{\s*$/', $line, $m)) {
                $open = trim($m[1]);
                $props = [];
                $startLine = $i + 1;
            }
            continue;
        }
        if (preg_match('/^\}/', $line)) {
            $rules[$open][] = [$startLine, $props];
            $open = null;
            continue;
        }
        if (preg_match('/^\s*([a-z-]+)\s*:/', $line, $m)) {
            $props[] = $m[1];
        }
    }

    // A pattern that stops matching would check nothing at all.
    T::ok(count($rules) > 300,
        'parsed ' . count($rules) . ' top-level rules out of gridkit.css; there were '
        . 'well over 300 when this was written, so a much smaller number means the '
        . 'pattern broke rather than the stylesheet shrinking');

    foreach ($rules as $selector => $blocks) {
        if (count($blocks) < 2) continue;
        for ($a = 0; $a < count($blocks) - 1; $a++) {
            [$lineA, $propsA] = $blocks[$a];
            if ($propsA === []) continue;
            for ($b = $a + 1; $b < count($blocks); $b++) {
                [$lineB, $propsB] = $blocks[$b];
                $survives = array_diff($propsA, $propsB);
                T::ok($survives !== [],
                    "$selector at line $lineA sets " . implode(', ', $propsA)
                    . " and nothing else; the same selector at line $lineB sets all of "
                    . 'them again, so the first rule can never apply');
            }
        }
    }
},

];
