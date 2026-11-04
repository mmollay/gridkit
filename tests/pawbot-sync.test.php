<?php
declare(strict_types=1);

/**
 * ci/pawbot-sync.php — brings the PawBot dashboard's own gridkit.js up to GridKit's release blocks.
 *
 * What it must never do: touch the dashboard's own code. It appends missing blocks, replaces a block
 * only where the copy holds one of GridKit's earlier texts of it, and leaves anything else — the
 * dashboard's code after or between blocks, a block the dashboard changed — to a person.
 */

require_once dirname(__DIR__) . '/ci/pawbot-sync.php';

/** A small gridkit.js: base code and the blocks given as version => body. */
$file = static function (array $blocks, string $base = "var GK = {};\n_t(\"field_required\");\n"): string {
    $js = $base;
    foreach ($blocks as $v => $body) $js .= "\n// === $v — block $v ===\n$body\n";
    return $js;
};
$pawbot = "\n// PawBot extension\nGK.pawbot = { hello: 1 };\n";

return [

'blocks are read by their header, each to the next one or the end' => function () use ($file): void {
    $b = gkSyncBlocks($file(['1.107.0' => 'a();', '1.108.0' => 'b();']));
    T::eq(array_keys($b), ['1.107.0', '1.108.0'], 'both blocks, in file order');
    T::contains($b['1.107.0']['text'], 'a();', 'the first holds its body');
    T::notContains($b['1.107.0']['text'], 'b();', 'and stops at the next header');
    $real = gkSyncBlocks((string) file_get_contents(dirname(__DIR__) . '/js/gridkit.js'));
    T::ok(isset($real['1.107.0'], $real['1.118.0']), 'GridKit\'s own file has its release blocks from 1.107.0 on');
},

'missing blocks are appended after the dashboard\'s own code, which stays as it was' => function () use ($file, $pawbot): void {
    $gk = $file(['1.107.0' => 'a();', '1.108.0' => 'b();', '1.109.0' => 'c();']);
    $copy = $file(['1.107.0' => 'a();']) . $pawbot;
    $r = gkPawbotSync($gk, $copy, false, false);
    T::eq($r['missing'], ['1.108.0', '1.109.0'], 'two missing');
    T::eq($r['same'], ['1.107.0'], 'one there — although the dashboard\'s code follows it');
    T::eq($r['out'], $copy, 'a report writes nothing');
    $a = gkPawbotSync($gk, $copy, true, false);
    T::ok(str_starts_with($a['out'], rtrim($copy, "\n")), 'the copy is kept, byte for byte, in front');
    T::ok(strpos($a['out'], '// === 1.108.0') < strpos($a['out'], '// === 1.109.0'), 'the blocks in version order');
    $again = gkPawbotSync($gk, $a['out'], true, false);
    T::eq([$again['missing'], $again['out']], [[], $a['out']], 'a second run finds nothing to do');
},

'a block a later release changed is replaced only when the copy holds GridKit\'s earlier text' => function () use ($file, $pawbot): void {
    $gk = $file(['1.118.0' => "pager(1);\npager(2);"]);
    $old = "\n// === 1.118.0 — block 1.118.0 ===\npager(1);\n";
    $copy = "var GK = {};\n_t(\"field_required\");\n" . $old . $pawbot;
    $history = ['1.118.0' => [trim($old)]];
    $r = gkPawbotSync($gk, $copy, false, false, $history);
    T::eq($r['differs'], ['1.118.0'], 'recognised as GridKit\'s earlier text');
    $u = gkPawbotSync($gk, $copy, false, true, $history);
    T::contains($u['out'], "pager(1);\npager(2);", '--update writes the new text');
    T::contains($u['out'], 'GK.pawbot = { hello: 1 };', 'and the dashboard\'s code after it is still there');
    T::eq(substr_count($u['out'], '// === 1.118.0'), 1, 'one block, not two');
    T::eq(gkPawbotSync($gk, $copy, true, false, $history)['out'], $copy, '--apply alone does not replace it');
    $own = str_replace('pager(1);', 'pager(1); pawbotTweak();', $copy);
    $o = gkPawbotSync($gk, $own, false, true, $history);
    T::eq([$o['changed'], $o['differs']], [['1.118.0'], []], 'a text that is none of GridKit\'s is the dashboard\'s own');
    T::eq($o['out'], $own, 'and is never overwritten');
    // A whole line added by the dashboard at the end of the block is kept: it reads as its code after the block.
    $added = str_replace("pager(1);\n", "pager(1);\npawbotLine();\n", $copy);
    $a = gkPawbotSync($gk, $added, false, true, $history);
    T::contains($a['out'], "pager(2);\npawbotLine();", 'replaced, and the added line follows the new text');
},

'the two changes inside the old code are checked, never patched' => function () use ($file): void {
    $gk = $file(['1.107.0' => 'a();']);
    $r = gkPawbotSync($gk, $file(['1.107.0' => 'a();']), true, true);
    $versions = array_column($r['inline'], 0);
    T::ok(in_array('1.113.0', $versions, true), 'the rich-text check without _tOr is reported');
    T::ok(in_array('1.110.1', $versions, true), 'the header cell over left buttons is reported');
    T::contains($r['out'], '_t("field_required")', 'and the line is left as it is');
    $real = (string) file_get_contents(dirname(__DIR__) . '/js/gridkit.js');
    T::eq(gkPawbotSync($real, $real, false, false)['inline'], [], 'GridKit\'s own file has both');
},

'GridKit\'s own file is in sync with itself, and the script runs from the command line' => function (): void {
    $root = dirname(__DIR__);
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/ci/pawbot-sync.php') . ' ' . escapeshellarg($root . '/js/gridkit.js') . ' 2>&1', $out, $code);
    T::eq($code, 0, 'exit 0: ' . implode(' | ', $out));
    T::eq(end($out), 'in sync', 'it says so');
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/ci/pawbot-sync.php') . ' 2>&1', $o2, $c2);
    T::eq($c2, 2, 'without a path: usage, exit 2');
},

];
