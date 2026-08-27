<?php
/**
 * The first fifteen minutes.
 *
 * Fifteen rounds of fixing the inside of the library, and the front door was
 * shut the whole time: following README.md literally produced an uncaught
 * Error on the first line of the copied file, 404s for every asset, and a
 * modal that filled with the marketing landing page. None of it showed up in
 * a suite that only ever called the classes directly.
 *
 * These tests walk the documented path instead of trusting it.
 */

declare(strict_types=1);

define('GK_ROOT', __DIR__ . '/..');

/** Run a php file in its own process; returns [stdout, stderr, exitCode]. */
function runPhp(string $file, string $cwd, array $get = []): array
{
    // The CLI does not populate $_GET from QUERY_STRING, so set it and include
    // the file — which is also closer to what a front controller does.
    $boot = '$_GET = ' . var_export($get, true) . '; $_SERVER["REQUEST_METHOD"]="GET"; '
          . 'include ' . var_export($file, true) . ';';
    $cmd = 'cd ' . escapeshellarg($cwd) . ' && php -r ' . escapeshellarg($boot)
         . ' 2>/tmp/gk-firstrun-err';
    $out = shell_exec($cmd) ?? '';
    $err = @file_get_contents('/tmp/gk-firstrun-err') ?: '';
    return [$out, $err];
}

/** A scratch directory that is removed when the test ends. */
function scratch(string $name): string
{
    $dir = sys_get_temp_dir() . '/gk-firstrun-' . getmypid() . '-' . $name;
    if (!is_dir($dir)) mkdir($dir, 0700, true);
    return $dir;
}

/** @return array<string,callable> */
return [

'the skeleton runs where it lives' => function (): void {
    [$out, $err] = runPhp('skeleton.php', GK_ROOT);
    T::ok(!str_contains($err, 'Fatal error'), "skeleton.php died in place: $err");
    T::contains($out, '<!DOCTYPE html>', 'skeleton.php produced no page');
    T::contains($out, 'css/gridkit.css', 'the stylesheet is not linked');
},

'the skeleton runs where the README tells you to copy it' => function (): void {
    // README: git clone …; mkdir -p my-app && cp gridkit/skeleton.php my-app/index.php
    // Until 1.44.0 this died on line 14 — the require was relative to the copy.
    $base = scratch('copy');
    @mkdir($base . '/my-app', 0700, true);
    @symlink(GK_ROOT, $base . '/gridkit');
    copy(GK_ROOT . '/skeleton.php', $base . '/my-app/index.php');

    [$out, $err] = runPhp('my-app/index.php', $base);
    T::ok(!str_contains($err, 'Fatal error'), "the copied skeleton died: $err");
    T::contains($out, '<!DOCTYPE html>', 'the copied skeleton produced no page');
    // And its assets must point at something that exists, not at nothing.
    preg_match('/href="([^"]*gridkit\.css[^"]*)"/', $out, $m);
    T::ok(isset($m[1]), 'the copied skeleton links no stylesheet');
    $path = $base . '/my-app/' . preg_replace('/\?.*/', '', $m[1]);
    T::ok(is_file($path), "the stylesheet URL {$m[1]} resolves to nothing");
},

'the skeleton says what is wrong instead of throwing' => function (): void {
    // Copied somewhere GridKit cannot be found, it used to end in an uncaught
    // Error and zero bytes of output. A person needs a sentence, not a trace.
    $base = scratch('lost');
    copy(GK_ROOT . '/skeleton.php', $base . '/index.php');

    [$out, $err] = runPhp('index.php', $base);
    T::ok(!str_contains($err, 'Uncaught'), "it still throws: $err");
    T::contains($out, 'GridKit was not found', 'it fails without explaining itself');
    T::contains($out, 'autoload.php', 'the message does not name the file to point at');
},

'the skeleton answers its own modal' => function (): void {
    // The table's edit button fetched forms/product.php, which does not exist.
    // PHP's built-in server — the one the README tells you to run — falls back
    // to the repo's index.php for an unmatched path, so the modal filled with
    // the GridKit landing page.
    $skeleton = (string) file_get_contents(GK_ROOT . '/skeleton.php');
    T::notContains($skeleton, 'forms/product.php',
        'the skeleton still points its modal at a file it does not ship');

    [$out, $err] = runPhp('skeleton.php', GK_ROOT, ['gk_form' => '1']);
    T::ok(!str_contains($err, 'Fatal error'), "the modal branch died: $err");
    T::contains($out, '<form', 'the modal URL returns no form');
    T::notContains($out, '<!DOCTYPE', 'the modal returns a whole page, not a fragment');
    T::ok(strlen($out) < 20000, 'the modal fragment is suspiciously large: ' . strlen($out) . ' bytes');
},

'the README does not promise a shape the example does not have' => function (): void {
    $readme = (string) file_get_contents(GK_ROOT . '/README.md');
    $files  = glob(GK_ROOT . '/examples/invoices/*.php');
    $lines  = 0;
    foreach ($files as $f) $lines += count(file($f));

    if (preg_match('/([\d,]+)\s*lines across (\w+) files/i', $readme, $m)) {
        $claimed = (int) str_replace(',', '', $m[1]);
        // "Around 670" against 673 is fine; "about 300" against 673 is not.
        T::ok(abs($claimed - $lines) < $lines * 0.25,
            "README says $claimed lines, the example has $lines");
    }
    T::eq(count($files), 5, 'the example no longer has five files');
},

'the example narrows its empty state by the parameters GridKit actually sends' => function (): void {
    // The application's "no invoices yet" copy is only true when nothing is
    // filtered — the table has its own wording for "nothing matched". Getting
    // the guard right means naming GridKit's own query parameters: a guess at
    // 'status' instead of 'gk_filter_status' leaves the filter case wrong and
    // nothing fails loudly.
    $index = (string) file_get_contents(GK_ROOT . '/examples/invoices/index.php');
    $store = (string) file_get_contents(GK_ROOT . '/examples/invoices/store.php');

    // The direction that matters is store -> index, not index -> store. Naming
    // a parameter store.php does not read is one mistake; failing to name one
    // it DOES read is the mistake that leaves the guard quietly wrong, and it
    // shows up as nothing at all.
    preg_match_all('/\$_GET\[\x27(gk_[a-z_]+)\x27\]/', $store, $inStore);
    $narrowing = array_values(array_unique(array_filter(
        $inStore[1],
        // The two the query narrows by. Sort, direction and page change which
        // rows you see, not whether any exist.
        static fn(string $p): bool => $p === 'gk_search' || str_starts_with($p, 'gk_filter_')
    )));
    T::ok($narrowing !== [], 'store.php narrows by nothing — the fixture changed');

    foreach ($narrowing as $param) {
        T::contains($index, "\$_GET['$param']",
            "store.php narrows by \$_GET['$param'] and the empty-state guard never asks about it");
    }
},

'a documented example uses a translation key that exists' => function (): void {
    // Lang.php's own docblock showed Lang::t('bulk.selected', ['n' => 5]).
    // There is no such key, so anyone copying the line gets the key back.
    $en = require GK_ROOT . '/lang/en.php';
    foreach (glob(GK_ROOT . '/src/*.php') as $file) {
        $src = (string) file_get_contents($file);
        // Only the docblock examples — real calls are covered elsewhere.
        preg_match_all('/^\s*\*\s+Lang::t\(\x27([a-z0-9_.]+)\x27/m', $src, $m);
        foreach ($m[1] as $key) {
            T::ok(isset($en[$key]),
                basename($file) . " documents Lang::t('$key'), which lang/en.php does not define");
        }
    }
},

'the README accounts for every class, and no others' => function (): void {
    // "Sixteen components" plus five named as infrastructure. Both numbers are
    // on the landing page and in the meta description too, and a count like
    // this drifts the moment a class is added — the repository description
    // still said "17+" long after the README said sixteen.
    $readme = (string) file_get_contents(GK_ROOT . '/README.md');

    preg_match('/Sixteen, each a PHP class.*?\n\n(.*?)\n\nPlus (.*?)\n/s', $readme, $m);
    T::ok(isset($m[1]), 'the component table is gone from the README');

    preg_match_all('/`(\w+)` +—/', $m[1], $c);
    preg_match_all('/`(\w+)`/', $m[2], $i);
    $listed = array_merge($c[1], $i[1]);

    $actual = array_map(
        static fn(string $f): string => basename($f, '.php'),
        glob(GK_ROOT . '/src/*.php')
    );

    T::eq(count($c[1]), 16, 'the README says sixteen components and lists ' . count($c[1]));
    sort($listed); sort($actual);
    T::eq($listed, $actual,
        'README and src/ disagree: ' . implode(', ', array_diff($actual, $listed))
        . ' unlisted / ' . implode(', ', array_diff($listed, $actual)) . ' invented');
},

'the example uses the translation mechanism the library ships' => function (): void {
    // The example carried a 63-entry array and a lookup of its own — which is
    // what people write when they have not found Lang::loadDir(). Three agents
    // reported that "there is no way to register application strings", and
    // 1.45.0 documented that workaround as the recommended pattern, because
    // nobody involved had found it either. The example is what people copy.
    $store = (string) file_get_contents(GK_ROOT . '/examples/invoices/store.php');

    T::contains($store, 'Lang::loadDir', 'the example no longer uses the catalogue');
    T::ok(!preg_match('/static \$strings = \[/', $store),
        'the example keeps a translation array of its own again');

    foreach (['en', 'de'] as $loc) {
        $file = GK_ROOT . "/examples/invoices/lang/$loc.php";
        T::ok(is_file($file), "examples/invoices/lang/$loc.php is missing");
        $strings = require $file;
        T::ok(count($strings) > 20, "$loc.php has only " . count($strings) . ' strings');
        foreach (array_keys($strings) as $key) {
            T::ok(str_starts_with($key, 'app.'),
                "an unprefixed key can collide with GridKit's own: $key");
        }
    }

    // en and de must define the same keys, like GridKit's own catalogue.
    $en = array_keys(require GK_ROOT . '/examples/invoices/lang/en.php');
    $de = array_keys(require GK_ROOT . '/examples/invoices/lang/de.php');
    sort($en); sort($de);
    T::eq($en, $de, 'the example locales define different keys');
},

'a Composer install works, laid out the way Composer lays it out' => function (): void {
    // The README has offered `composer require mmollay/gridkit` since the
    // start, and nobody had ever run one — Packagist has served v1.4.0 since
    // March and the package has one download. So: build the archive Packagist
    // would ship, put it where Composer would put it, generate the autoloader
    // Composer would generate, and see whether the documented asset path
    // resolves. That path is the one thing a plain clone never exercises.
    $base = sys_get_temp_dir() . '/gk-composer-' . getmypid();
    $pkg  = $base . '/vendor/mmollay/gridkit';
    @mkdir($pkg, 0700, true);

    // git archive honours .gitattributes export-ignore — this IS the package.
    exec('git -C ' . escapeshellarg(GK_ROOT) . ' archive --format=tar HEAD | tar -x -C '
        . escapeshellarg($pkg) . ' 2>/dev/null', $ignored, $code);
    T::eq($code, 0, 'could not build the package archive');
    T::ok(is_file($pkg . '/autoload.php'), 'the package has no autoload.php');
    T::ok(is_file($pkg . '/css/gridkit.css'), 'the package ships no stylesheet');

    // Composer's autoloader in miniature: the PSR-4 map, then the files entry.
    $boot = "<?php\n"
        . "spl_autoload_register(function (\$c) {\n"
        . "    if (!str_starts_with(\$c, 'GridKit\\\\')) return;\n"
        . "    \$f = __DIR__ . '/mmollay/gridkit/src/' . str_replace('\\\\', '/', substr(\$c, 8)) . '.php';\n"
        . "    if (is_file(\$f)) require \$f;\n"
        . "});\n"
        . "require __DIR__ . '/mmollay/gridkit/autoload.php';\n";
    file_put_contents($base . '/vendor/autoload.php', $boot);

    $app = "<?php\n"
        . "require __DIR__ . '/vendor/autoload.php';\n"
        . "GridKit\\Lang::set('de');\n"
        . "echo 'LANG:' . GridKit\\Lang::t('table.search') . PHP_EOL;\n"
        . "ob_start();\n"
        . "(new GridKit\\Table('t'))->setData([['n' => 'A']])->column('n', 'N')->render();\n"
        . "echo 'TABLE:' . strlen(ob_get_clean()) . PHP_EOL;\n"
        . "echo 'ASSET:' . GridKit\\Layout::asset('vendor/mmollay/gridkit/css/gridkit.css') . PHP_EOL;\n";
    file_put_contents($base . '/index.php', $app);

    $out = shell_exec('cd ' . escapeshellarg($base) . ' && php index.php 2>&1') ?? '';
    T::notContains($out, 'Fatal error', "a Composer install dies: $out");
    T::notContains($out, 'Warning',     "a Composer install warns: $out");
    T::contains($out, 'LANG:Suchen',  'Lang did not load through the files autoload');
    T::ok((bool) preg_match('/TABLE:(\\d+)/', $out, $m) && (int) $m[1] > 200,
        'the table rendered nothing through a Composer install');

    // The documented path must resolve to the file that is really there.
    preg_match('/ASSET:(\\S+)/', $out, $a);
    T::ok(isset($a[1]), 'Layout::asset() produced no URL');
    $path = $base . '/' . preg_replace('/\\?.*/', '', $a[1]);
    T::ok(is_file($path), "the documented asset URL {$a[1]} resolves to nothing");
    T::contains($a[1], '?v=' . filemtime($pkg . '/css/gridkit.css'),
        'the URL does not carry the vendored file own timestamp');
},

'every documented install path names something reachable' => function (): void {
    $readme = (string) file_get_contents(GK_ROOT . '/README.md');
    // A Composer install puts the assets under vendor/, and Layout::asset()
    // stamps a path rather than resolving one — so the README has to say where
    // the CSS actually is, or the page renders unstyled with no clue why.
    if (str_contains($readme, 'composer require mmollay/gridkit')) {
        T::contains($readme, 'vendor/mmollay/gridkit',
            'the README offers composer with no word on where the CSS ends up');
    }
},

];
