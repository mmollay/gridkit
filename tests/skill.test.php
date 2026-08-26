<?php
/**
 * The agent skill's own examples.
 *
 * `GRIDKIT_SKILL.md` is the headline feature: one file that teaches an
 * assistant the whole API. That only holds if the code in it runs. It did not
 * — the skill showed `'color' => 'danger'` on a row button, which `Table` read
 * as nothing at all, and its only example of the `use` statements was wrapped
 * in a template engine that exists in one private codebase.
 *
 * Each runnable block is executed in its own process, with every PHP
 * diagnostic promoted to a failure.
 */

declare(strict_types=1);

const SKILL   = __DIR__ . '/../GRIDKIT_SKILL.md';
const PRELUDE = <<<'PHP_PRELUDE'
<?php
declare(strict_types=1);
error_reporting(E_ALL);
require_once '%ROOT%/autoload.php';
use GridKit\{ActionGroup, Auth, BelegModal, Button, FilterChips, Form, Header, Icon,
              Lang, Layout, Modal, PageSize, Pagination, Select, Sidebar, SortLink,
              StatCards, Table, TableHeader, Theme, YearFilter};
Lang::set('en');

// Context the examples assume a caller has already set up.
$rows     = [['id' => 1, 'name' => 'Widget', 'email' => 'a@b.c', 'status' => 'paid', 'role' => 'admin']];
$result   = ['rows' => $rows, 'total' => 1];
$sort     = 'name'; $dir = 'asc'; $q = 'x'; $year = 2026; $perPage = 25;
$id       = 7; $isOverdue = true;
$units    = ['pc' => 'Piece'];
$items    = new class { public $currentPage = 1; public $totalPages = 3; public $total = 42; };
$sidebar  = new Sidebar('probe');

set_error_handler(static function (int $n, string $m): bool {
    fwrite(STDERR, "DIAGNOSTIC: $m\n");
    exit(2);
});
ob_start();
register_shutdown_function(static function (): void {
    if (ob_get_level()) ob_end_clean();
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_COMPILE_ERROR], true)) {
        fwrite(STDERR, "FATAL: {$e['message']}\n");
    }
});
PHP_PRELUDE;

/**
 * Blocks that cannot run on their own, with the reason.
 * Anything skipped is reported — a silent skip would read as "all covered".
 */
function skipReason(string $code): ?string
{
    if (str_contains($code, '$this->layout'))  return 'SSI Panel template engine';
    if (str_contains($code, '$request->'))     return 'another framework';
    if (preg_match('/^\s*->/', $code))         return 'method-chain fragment';
    if (str_contains($code, '$statusChips'))   return 'composes objects the reader supplies';
    if (str_contains($code, '->query($db'))    return 'needs a live MySQL connection';
    // A ```php block that is really mixed HTML/JS pseudo-code.
    if (preg_match('/^\s*(<div|<template|<!--)/m', $code)
        && !str_contains($code, '<?php')
        && !str_contains($code, '<?=')) return 'markup sketch, not PHP';
    if (substr_count($code, '<?php') + substr_count($code, '<?=') > 0
        && preg_match('/^\s*<(div|template)/m', $code)) return 'markup sketch with inline PHP';
    return null;
}

/** @return array<string,callable> */
return [

'every runnable example in the agent skill runs' => function (): void {
    $md = (string) file_get_contents(SKILL);
    preg_match_all('/```php\n(.*?)```/s', $md, $m);
    $blocks = $m[1];

    T::ok(count($blocks) >= 15, 'the skill still carries its examples, found ' . count($blocks));

    $root    = realpath(__DIR__ . '/..');
    $prelude = str_replace('%ROOT%', $root, PRELUDE);
    $tmp     = sys_get_temp_dir() . '/gk-skill-' . getmypid() . '.php';
    $skipped = [];
    $ran     = 0;

    foreach ($blocks as $i => $code) {
        $label = 'block ' . ($i + 1);

        if ($why = skipReason($code)) {
            $skipped[] = "$label ($why)";
            continue;
        }

        // A leading <?php would reopen a tag that is already open; the block's
        // own imports collide with the prelude's; and the autoloader line names
        // the reader's own path, which the prelude has already handled.
        $body = preg_replace('/^\s*<\?php\s*$/m', '', $code);
        $body = preg_replace('/use\s+GridKit\\\\[^;]*;/s', '', (string) $body);
        $body = preg_replace('/require(_once)?[^;]*autoload[^;]*;/', '', (string) $body);
        file_put_contents($tmp, $prelude . "\n// ---- example ----\n" . $body);

        exec('php ' . escapeshellarg($tmp) . ' 2>&1', $out, $code_);
        $text = implode("\n", $out);
        $out  = [];

        T::ok(
            $code_ === 0 && !preg_match('/DIAGNOSTIC|FATAL|Parse error|Warning:|Notice:|Deprecated:/', $text),
            "$label does not run: " . substr(preg_replace('/\s+/', ' ', $text), 0, 160)
        );
        $ran++;
    }

    @unlink($tmp);

    T::ok($ran >= 12, "expected most blocks to be runnable, ran only $ran");

    // Not an assertion — a note, so a growing skip list stays visible.
    if ($skipped) {
        fwrite(STDERR, "    (skill: " . count($skipped) . " blocks not runnable — "
            . implode('; ', $skipped) . ")\n");
    }
},

'the skill shows how to import the classes, outside any framework' => function (): void {
    $md = (string) file_get_contents(SKILL);

    // The only `use` example used to sit inside an SSI Panel view, complete
    // with `$this->layout(...)`. An agent copying it produced code that needs
    // a template engine nobody outside one codebase has.
    $pos = strpos($md, '## Page skeleton');
    T::ok($pos !== false, 'a standalone page skeleton section exists');

    $end     = strpos($md, '### Inside SSI Panel', (int) $pos);
    $section = substr($md, (int) $pos, ($end !== false ? $end - $pos : 2500));
    T::contains($section, 'use GridKit\\', 'it shows the import');
    T::contains($section, 'autoload.php', 'and where the autoloader comes from');
    T::ok(!preg_match('/```php\n[^`]*\$this->layout/s', $section),
        'the first skeleton must not depend on a template engine');
},

'no German is left in the skill' => function (): void {
    $md = (string) file_get_contents(SKILL);
    T::ok(!preg_match('/[äöüÄÖÜß]/u', $md), 'umlauts');

    // German without umlauts is the part a naive sweep misses — "Titel",
    // "Inhalt", "seit", "Seite" all slipped through once.
    foreach (['Titel', 'Inhalt', 'Ausgaben', 'Geschwister', 'Beliebiges', 'Aktionsleiste'] as $word) {
        T::notContains($md, $word, "German word left in the skill");
    }
},

];
