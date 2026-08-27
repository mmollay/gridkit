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

'the skill states which calls print and which return' => function (): void {
    // Five agents were given this file and a page to build. None got a first
    // draft without tripping over this, and the failure is silent: the page
    // renders, the piece is simply absent. Theme::switcher() and
    // Header::render() return strings; called as statements they emit nothing.
    $md = (string) file_get_contents(SKILL);
    T::contains($md, 'echo or return', 'the rule is gone from the skill file');

    // And the file must not contradict it in its own examples.
    T::ok(!preg_match('/<\?php\s+Theme::switcher\(\);/', $md),
        'the skill calls Theme::switcher() as a statement — it returns a string');
    // The anti-example inside the rule itself is allowed to show the wrong
    // form. What must not appear is a line presented as one to copy.
    T::eq(preg_match_all('/^<\?php\s+Modal::container\(\);/m', $md), 0,
        'the skill still shows Modal::container() as a line to place — a no-op since 1.42.0');

    // The table has to name every returning call, or it is worse than nothing.
    foreach (['Header::render()', 'Button::render()', 'Theme::switcher()',
              'Select::searchable()', 'Layout::asset()', 'Lang::jsConfig()'] as $call) {
        T::contains($md, $call, "the echo/return table omits $call");
    }
},

'every component the README lists has a section in the skill' => function (): void {
    // Header was the only one without. An agent building a dashboard from this
    // file put two theme switchers on the page, because ->user() renders one
    // of its own and nothing said so.
    $md     = (string) file_get_contents(SKILL);
    $readme = (string) file_get_contents(__DIR__ . '/../README.md');

    preg_match('/Sixteen, each a PHP class.*?\n\n(.*?)\n\nPlus/s', $readme, $m);
    preg_match_all('/`(\w+)` +—/', $m[1] ?? '', $c);
    T::ok(count($c[1]) === 16, 'the README component table changed shape');

    foreach ($c[1] as $component) {
        T::ok(
            // Anywhere in a heading — "Pagination + PageSize" covers both.
            (bool) preg_match('/^#{2,3} .*\\b' . $component . '\\b/m', $md),
            "GRIDKIT_SKILL.md has no section for $component"
        );
    }
},

'the skill names every public method of every component' => function (): void {
    // Three rounds of the agent test agree on where the file holds and where
    // it does not: the components with a real section — Table, Form, Header,
    // Sidebar, StatCards, Theme, Lang — were right first time in every run,
    // and every failure landed on one documented as a table row. Layout was
    // 1 of 6 methods, and Lang::loadDir() — the mechanism for an application's
    // own translations — was missing entirely, so agents kept inventing a
    // $t() closure and the file went and recommended one.
    $md = (string) file_get_contents(SKILL);

    // A handful are genuinely internal or adapters nobody should reach for.
    $exempt = [
        'Pagination::fromPaginatorHtml',  // the string twin of a documented call
        'Table::loadTime',                // instrumentation for a page that measures
        'Auth::renderLogin',              // covered in prose, not as a signature
    ];

    $gaps = [];
    foreach (glob(__DIR__ . '/../src/*.php') as $file) {
        $class = basename($file, '.php');
        $ref   = new ReflectionClass('GridKit\\' . $class);
        foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $m) {
            if ($m->class !== $ref->getName() || $m->name === '__construct') continue;
            if (in_array("$class::{$m->name}", $exempt, true)) continue;
            $named = str_contains($md, "$class::{$m->name}")
                  || str_contains($md, "->{$m->name}(");
            if (!$named) $gaps[] = "$class::{$m->name}()";
        }
    }
    T::eq($gaps, [], 'the skill file never names: ' . implode(', ', $gaps));
},

'the installable skill is current with the document it is built from' => function (): void {
    // skill/ is generated from GRIDKIT_SKILL.md so the two cannot drift: one
    // source, two shapes. The single file is what the site serves at /skill
    // and what a paste-into-context user gets; skill/ is the same content
    // split, so an assistant reads the rules first and fetches one reference
    // instead of 61 KB to answer a question about one method.
    $root = __DIR__ . '/..';
    T::ok(is_file("$root/skill/SKILL.md"), 'skill/SKILL.md is missing — run ci/build-skill.sh');
    T::ok(is_file("$root/ci/build-skill.sh"), 'the generator is gone');

    // A skill needs its frontmatter, and the description is what an assistant
    // matches on — an empty one means the skill is never chosen.
    $md = (string) file_get_contents("$root/skill/SKILL.md");
    T::ok(str_starts_with($md, "---\n"), 'SKILL.md has no frontmatter');
    preg_match('/^---\n(.*?)\n---/s', $md, $m);
    T::contains($m[1] ?? '', 'name: gridkit', 'the skill has no name');
    T::ok(strlen($m[1] ?? '') > 120, 'the description is too thin to be matched on');

    // The split must be lossless: every heading and every runnable example.
    $src = (string) file_get_contents("$root/GRIDKIT_SKILL.md");
    $gen = '';
    foreach (glob("$root/skill/*.md") as $f)            $gen .= file_get_contents($f);
    foreach (glob("$root/skill/reference/*.md") as $f)  $gen .= file_get_contents($f);

    preg_match_all('/^#{2,3} .+$/m', $src, $h);
    $lost = array_values(array_filter($h[0], static fn(string $x): bool => !str_contains($gen, $x)));
    T::eq($lost, [], 'the split dropped headings: ' . implode(' | ', array_slice($lost, 0, 3)));
    T::eq(substr_count($gen, '```php'), substr_count($src, '```php'),
        'the split dropped code examples');

    // And it must carry the current version, like the document does.
    $version = trim((string) file_get_contents("$root/VERSION"));
    T::contains($md, "# GridKit $version", "SKILL.md is stamped for another version");
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
