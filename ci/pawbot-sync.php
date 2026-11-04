<?php
declare(strict_types=1);

/**
 * PawBot sync: bring the dashboard's own copy of js/gridkit.js up to GridKit's release blocks.
 *
 * The dashboard keeps its own gridkit.js (PawBot extensions) and its sync never overwrites it
 * (.claude/CLAUDE.md, "Handle with care"). Since 1.107.0 every release adds its script as ONE block
 * at the end of js/gridkit.js, headed `// === <version> — <name> ===`, wrapping existing functions
 * instead of editing them. So a copy is brought up to date by appending the blocks it lacks.
 *
 *   php ci/pawbot-sync.php /path/to/dashboard/js/gridkit.js            report only (exit 1 if behind)
 *   php ci/pawbot-sync.php /path/to/dashboard/js/gridkit.js --apply    append the missing blocks
 *   php ci/pawbot-sync.php /path/to/dashboard/js/gridkit.js --update   also replace blocks that differ
 *
 * --apply/--update write a backup next to the file first (gridkit.js.bak-<date>). A block counts
 * as there when its text stands in the copy word for word (the dashboard's own code may follow it).
 * A block a later release changed (e.g. 1.118.1 in the 1.118.0 block) is replaced with --update,
 * and only when the copy holds one of GridKit's own earlier texts of it (from the git history of
 * js/gridkit.js) — so nothing of the dashboard's is ever overwritten; any other text is reported.
 * Two changes since 1.107.0 are inside the old code, not blocks; they are checked and explained,
 * never patched: lines outside the blocks belong to the dashboard.
 */

const GK_SYNC_BLOCK = '~^// === (\d+\.\d+\.\d+) — .* ===$~m';

/** @return array<string,array{header:string,text:string}> version => block, in file order */
function gkSyncBlocks(string $js): array
{
    preg_match_all(GK_SYNC_BLOCK, $js, $m, PREG_OFFSET_CAPTURE);
    $blocks = [];
    $n = count($m[0]);
    for ($i = 0; $i < $n; $i++) {
        $start = $m[0][$i][1];
        $end = $i + 1 < $n ? $m[0][$i + 1][1] : strlen($js);
        $blocks[$m[1][$i][0]] = ['header' => $m[0][$i][0], 'text' => rtrim(substr($js, $start, $end - $start), "\n") . "\n"];
    }
    return $blocks;
}

/** The changes inside the old code since 1.107.0: [version, what, test on the copy, how]. */
function gkSyncInlineChanges(): array
{
    return [
        ['1.110.1', 'the header cell over left row buttons stands first (GK.table.renderStatic)',
            static fn(string $js): bool => str_contains($js, '(b.position || "right") === "left"))'),
            'In renderStatic, the <th class="gk-actions-col"> for left buttons is written before the loop over the columns, not after it. Copy the three lines after "as Table.php writes it since 1.110.1" from GridKit\'s js/gridkit.js.'],
        ['1.113.0', 'the required rich-text check names the field without throwing',
            static fn(string $js): bool => str_contains($js, '_tOr("field_required"'),
            'In the rich-text submit check, `_t("field_required", …)` becomes `_tOr("field_required", "{name} is required.")` — _t lives inside another closure and threw before the toast. The line is marked "_t lives inside the closure above".'],
    ];
}

/** Where $needle stands in $hay as whole lines (from a line start to a line end), or -1. */
function gkSyncFindLines(string $hay, string $needle): int
{
    for ($at = strpos($hay, $needle); $at !== false; $at = strpos($hay, $needle, $at + 1)) {
        $end = $at + strlen($needle);
        if (($at === 0 || $hay[$at - 1] === "\n") && ($end === strlen($hay) || $hay[$end] === "\n")) return $at;
    }
    return -1;
}

/**
 * Every earlier text of each release block, from the git history of js/gridkit.js: what a copy may
 * hold of a block GridKit has changed since. Only such a text is ever replaced.
 *
 * @return array<string,list<string>> version => earlier texts
 */
function gkSyncHistory(string $repo): array
{
    $hist = [];
    exec('git -C ' . escapeshellarg($repo) . ' log --format=%H -- js/gridkit.js 2>/dev/null', $commits);
    foreach ($commits as $c) {
        $js = (string) shell_exec('git -C ' . escapeshellarg($repo) . ' show ' . escapeshellarg($c . ':js/gridkit.js') . ' 2>/dev/null');
        foreach (gkSyncBlocks($js) as $v => $b) {
            // In an old file the last block runs to the end, like any other block: nothing follows it there.
            $t = trim($b['text']);
            if (!in_array($t, $hist[$v] ?? [], true)) $hist[$v][] = $t;
        }
        if (!str_contains($js, '// === 1.107.0')) break;          // older than the block era
    }
    return $hist;
}

/**
 * A block counts as there when its text stands in the copy word for word; the copy's own code may
 * follow it or sit between blocks. A block whose header is there but whose text is not is "differs":
 * with --update it is replaced only when the copy holds one of GridKit's earlier texts of it.
 *
 * @param array<string,list<string>> $history  version => earlier texts (gkSyncHistory)
 * @return array{missing:list<string>,differs:list<string>,changed:list<string>,same:list<string>,inline:list<array{0:string,1:string,2:string}>,out:string}
 */
function gkPawbotSync(string $gridkit, string $copy, bool $apply, bool $update, array $history = []): array
{
    $want = gkSyncBlocks($gridkit);
    $missing = $differs = $changed = $same = [];
    $out = $copy;
    foreach ($want as $v => $b) {
        $now = trim($b['text']);
        if (gkSyncFindLines($copy, $now) >= 0) { $same[] = $v; continue; }
        if (!preg_match('~^' . preg_quote($b['header'], '~') . '$~m', $copy)
            && !preg_match('~^// === ' . preg_quote($v, '~') . ' — ~m', $copy)) { $missing[] = $v; continue; }
        // The header is there, the text is not: an earlier text of GridKit's, or the dashboard's own.
        $old = null;
        foreach ($history[$v] ?? [] as $t) {                             // whole lines only: a line the dashboard extended is its own
            if ($t !== $now && gkSyncFindLines($copy, $t) >= 0) { $old = $t; break; }
        }
        if ($old === null) { $changed[] = $v; continue; }
        $differs[] = $v;
        if ($update && ($at = gkSyncFindLines($out, $old)) >= 0) $out = substr_replace($out, $now, $at, strlen($old));
    }
    if ($apply || $update) {
        foreach ($missing as $v) {
            $out = rtrim($out, "\n") . "\n\n" . $want[$v]['text'];
        }
    }
    $inline = [];
    foreach (gkSyncInlineChanges() as [$v, $what, $test, $how]) {
        if (!$test($copy)) $inline[] = [$v, $what, $how];
    }
    return ['missing' => $missing, 'differs' => $differs, 'changed' => $changed, 'same' => $same, 'inline' => $inline, 'out' => $out];
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $args = array_slice($argv, 1);
    $apply = in_array('--apply', $args, true);
    $update = in_array('--update', $args, true);
    $paths = array_values(array_filter($args, static fn(string $a): bool => !str_starts_with($a, '--')));
    if (count($paths) !== 1 || !is_file($paths[0])) {
        fwrite(STDERR, "usage: php ci/pawbot-sync.php /path/to/dashboard/js/gridkit.js [--apply|--update]\n");
        exit(2);
    }
    $path = $paths[0];
    $gridkit = (string) file_get_contents(dirname(__DIR__) . '/js/gridkit.js');
    $copy = (string) file_get_contents($path);
    $r = gkPawbotSync($gridkit, $copy, $apply, $update, gkSyncHistory(dirname(__DIR__)));

    echo 'GridKit ' . trim((string) @file_get_contents(dirname(__DIR__) . '/VERSION')) . " → $path\n";
    foreach ($r['same'] as $v) echo "  ok       $v\n";
    foreach ($r['differs'] as $v) echo '  ' . ($update ? 'updated ' : 'DIFFERS ') . " $v" . ($update ? '' : '  (a later release changed it; --update replaces it)') . "\n";
    foreach ($r['missing'] as $v) echo '  ' . ($apply || $update ? 'added   ' : 'MISSING ') . " $v\n";
    foreach ($r['changed'] as $v) echo "  OWN      $v  the dashboard's text of this block is none of GridKit's — compare and merge by hand\n";
    foreach ($r['inline'] as [$v, $what, $how]) echo "  BY HAND  $v  $what\n           $how\n";

    if (($apply || $update) && $r['out'] !== $copy) {
        $backup = $path . '.bak-' . date('Ymd-His');
        if (!copy($path, $backup) || file_put_contents($path, $r['out']) === false) {
            fwrite(STDERR, "could not write $path (backup: $backup)\n");
            exit(2);
        }
        echo "written; the old file is $backup\n";
        exit($r['inline'] === [] && $r['changed'] === [] ? 0 : 1);
    }
    $blocksBehind = $r['missing'] !== [] || $r['differs'] !== [];
    if ($r['changed'] !== [] && trim((string) shell_exec('git -C ' . escapeshellarg(dirname(__DIR__)) . ' rev-parse --is-shallow-repository 2>/dev/null')) === 'true') {
        echo "note: this GridKit clone is shallow, so earlier texts of a block are unknown here — `git fetch --unshallow` and run again\n";
    }
    if ($blocksBehind) echo "blocks behind — run again with --apply (missing blocks) or --update (also changed ones)\n";
    elseif ($r['changed'] !== [] || $r['inline'] !== []) echo "blocks in sync; what is marked OWN or BY HAND above is for a person\n";
    else echo "in sync\n";
    exit($blocksBehind || $r['changed'] !== [] || $r['inline'] !== [] ? 1 : 0);
}
