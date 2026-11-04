<?php
/**
 * What a hand-typed query string may do to a page.
 *
 * "?gk_page=99999999999999999999" is PHP_INT_MAX. On a table with paginate() it was a
 * TypeError (the offset overflowed to a float inside array_slice) or a request that never
 * ended: the pager counts from the page to the page + 2, and past PHP_INT_MAX the counter
 * stops moving. One link, one PHP worker held until the time limit. Found in 1.104.4 by
 * rendering every component once for every pair of parameter and hostile value.
 *
 * Everything here runs in a child process with a time limit, so a relapse fails this test
 * instead of stopping the whole suite.
 */

declare(strict_types=1);

/** Runs a fixture in a child process; returns [stdout, timedOut]. */
function hostileRun(string $fixture, array $args, int $seconds = 20): array
{
    $cmd = [PHP_BINARY, __DIR__ . '/fixtures/' . $fixture, ...$args];
    $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($proc)) return ['', false];
    stream_set_blocking($pipes[1], false);
    $out = '';
    $deadline = microtime(true) + $seconds;
    $timedOut = false;
    while (true) {
        $out .= (string) stream_get_contents($pipes[1]);
        if (!proc_get_status($proc)['running']) break;
        if (microtime(true) > $deadline) { $timedOut = true; proc_terminate($proc, 9); break; }
        usleep(20000);
    }
    $out .= (string) stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($proc);
    return [$out, $timedOut];
}

/** @return array<string,callable> */
return [

'no hostile query parameter hangs or breaks a component' => function (): void {
    foreach (['Table', 'TableRows', 'FilterChips', 'PageSize', 'YearFilter', 'Pagination', 'Tabs', 'DateRange', 'Calendar'] as $component) {
        [$out, $timedOut] = hostileRun('hostile-probe.php', [$component]);
        $lines = explode("\n", trim($out));
        $current = '';
        foreach ($lines as $line) if (str_starts_with($line, '> ')) $current = substr($line, 2);
        T::ok(!$timedOut, "$component did not answer within 20 s — stuck on ?$current");
        T::ok(end($lines) === 'done' || $timedOut, "$component: the probe stopped early (" . end($lines) . ')');
        $current = '';
        foreach ($lines as $line) {
            if (str_starts_with($line, '> ')) { $current = substr($line, 2); continue; }
            if ($line === 'ok' || $line === 'done' || $line === '') continue;
            T::ok(false, "$component with ?$current: $line");
        }
    }
},

'a page far past the end of a static table shows the empty state' => function (): void {
    foreach (['99999999999999999999', (string) PHP_INT_MAX, '1000001', '3'] as $page) {
        [$out, $timedOut] = hostileRun('hostile-page.php', [$page], 10);
        T::ok(!$timedOut, "a static table asked for page $page did not answer within 10 s");
        T::contains($out, 'gk-empty-row', "a static table asked for page $page shows no empty state");
        T::contains($out, 'done', "a static table asked for page $page stopped early");
    }
},

];
