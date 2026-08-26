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
