<?php
declare(strict_types=1);

/**
 * ci/gridkit-deploy.sh — the server's automatic delivery of main to gridkit.at.
 *
 * Run for real against a stand-in: a bare "origin", a "live" clone (the website), and commits
 * whose tests/run.php is a stub that passes or fails. What it must do: deliver a green commit and
 * tag its VERSION, leave the website alone when the tests fail (and not retry that commit every
 * minute), never overwrite a local change, never move a tag that exists.
 */

$script = dirname(__DIR__) . '/ci/gridkit-deploy.sh';

/** A fresh stand-in: origin (bare), dev (where commits are made), live (the website). */
$world = static function (): array {
    $dir = sys_get_temp_dir() . '/gk-deploy-' . bin2hex(random_bytes(4));
    mkdir($dir);
    $git = static function (string $cwd, string $args) use ($dir): string {
        $env = 'GIT_AUTHOR_NAME=t GIT_AUTHOR_EMAIL=t@t GIT_COMMITTER_NAME=t GIT_COMMITTER_EMAIL=t@t ';
        return (string) shell_exec('cd ' . escapeshellarg($cwd) . ' && ' . $env . 'git ' . $args . ' 2>&1');
    };
    $git($dir, 'init -q --bare -b main origin.git');
    $git($dir, 'clone -q origin.git dev');
    return ['dir' => $dir, 'git' => $git];
};

/** A commit in dev, pushed to origin: VERSION and a tests/run.php that passes or fails. */
$commit = static function (array $w, string $version, bool $green, string $msg = 'c'): void {
    $dev = $w['dir'] . '/dev';
    @mkdir($dev . '/tests');
    file_put_contents($dev . '/VERSION', $version . "\n");
    file_put_contents($dev . '/tests/run.php', '<?php echo "suite\n"; exit(' . ($green ? 0 : 1) . ');');
    ($w['git'])($dev, 'add -A');
    ($w['git'])($dev, 'commit -q -m ' . escapeshellarg($msg));
    ($w['git'])($dev, 'push -q origin HEAD:main');
};

/** One run of the script on the stand-in: [exit code, output]. */
$run = static function (array $w) use ($script): array {
    $env = 'GK_LIVE=' . escapeshellarg($w['dir'] . '/live')
         . ' GK_CHECK=' . escapeshellarg($w['dir'] . '/check')
         . ' GK_STATE=' . escapeshellarg($w['dir'] . '/state')
         . ' GK_PHP=' . escapeshellarg(PHP_BINARY);
    exec($env . ' bash ' . escapeshellarg($script) . ' 2>&1', $out, $code);
    return [$code, implode("\n", $out)];
};

$head = static fn(array $w, string $repo, string $ref = 'HEAD'): string
    => trim(($w['git'])($w['dir'] . '/' . $repo, 'rev-parse ' . $ref));

$cleanup = static function (array $w): void {
    exec('rm -rf ' . escapeshellarg($w['dir']));
};

return [

'the script is valid bash, executable, and holds to its rules' => function () use ($script): void {
    T::ok(is_file($script), 'ci/gridkit-deploy.sh is missing');
    T::ok(is_executable($script), 'ci/gridkit-deploy.sh is not executable');
    exec('bash -n ' . escapeshellarg($script) . ' 2>&1', $o, $code);
    T::eq($code, 0, 'bash -n: ' . implode(' ', $o));
    $s = (string) file_get_contents($script);
    T::contains($s, 'set -euo pipefail', 'it stops at the first error');
    T::contains($s, 'flock -n', 'two runs never overlap (a run is still testing when cron starts the next)');
    T::contains($s, '--ff-only', 'the website only moves forward; a local change stops it');
    T::notContains($s, 'push -f', 'it never force-pushes');
    T::notContains($s, '--force', 'it never forces anything');
    T::notContains($s, 'tag -f', 'it never moves a tag');
    T::notContains($s, 'reset --hard', 'it never throws away what is in the website');
},

'a green commit is delivered and its version tagged; the next run is quiet' => function () use ($world, $commit, $run, $head, $cleanup): void {
    $w = $world();
    $commit($w, '1.0.0', true, 'first');
    ($w['git'])($w['dir'], 'clone -q origin.git live');
    $commit($w, '1.1.0', true, 'second');
    [$code, $out] = $run($w);
    T::eq($code, 0, "exit code; output: $out");
    T::eq($head($w, 'live'), $head($w, 'dev'), 'the website is on the new commit');
    T::contains($out, 'delivered', 'it says what it did');
    T::eq(trim(($w['git'])($w['dir'] . '/origin.git', 'tag -l v1.1.0')), 'v1.1.0', 'the tag of VERSION is on origin');
    T::eq($head($w, 'origin.git', 'v1.1.0^{commit}'), $head($w, 'dev'), 'on the delivered commit');
    [$code2, $out2] = $run($w);
    T::eq([$code2, $out2], [0, ''], 'nothing new: no output, no error');
    $cleanup($w);
},

'a red commit is not delivered, is tried once, and the next green one goes out' => function () use ($world, $commit, $run, $head, $cleanup): void {
    $w = $world();
    $commit($w, '1.0.0', true, 'first');
    ($w['git'])($w['dir'], 'clone -q origin.git live');
    $before = $head($w, 'live');
    $commit($w, '1.1.0', false, 'broken');
    [$code, $out] = $run($w);
    T::ok($code !== 0, 'a failed suite is an error');
    T::contains($out, 'NOT delivered', 'and says so');
    T::eq($head($w, 'live'), $before, 'the website did not move');
    T::eq(trim(($w['git'])($w['dir'] . '/origin.git', 'tag -l v1.1.0')), '', 'and no tag was made');
    T::ok(is_file($w['dir'] . '/state/tests.log'), 'the output of the suite is kept');
    [$code2, $out2] = $run($w);
    T::eq([$code2, $out2], [0, ''], 'the same red commit is not tested every minute');
    $commit($w, '1.1.1', true, 'fixed');
    [$code3] = $run($w);
    T::eq($code3, 0, 'the fix is delivered');
    T::eq($head($w, 'live'), $head($w, 'dev'), 'the website is on the fix');
    T::eq(trim(($w['git'])($w['dir'] . '/origin.git', 'tag -l v1.1.1')), 'v1.1.1', 'tagged');
    $cleanup($w);
},

'a local change in the website stops the delivery instead of being overwritten' => function () use ($world, $commit, $run, $head, $cleanup): void {
    $w = $world();
    $commit($w, '1.0.0', true, 'first');
    ($w['git'])($w['dir'], 'clone -q origin.git live');
    file_put_contents($w['dir'] . '/live/VERSION', "9.9.9\n");          // edited by hand on the server
    $before = $head($w, 'live');
    $commit($w, '1.1.0', true, 'second');
    [$code] = $run($w);
    T::ok($code !== 0, 'it stops with an error');
    T::eq($head($w, 'live'), $before, 'the website did not move');
    T::eq(trim((string) file_get_contents($w['dir'] . '/live/VERSION')), '9.9.9', 'and the change is still there');
    $cleanup($w);
},

'an existing tag is never moved' => function () use ($world, $commit, $run, $head, $cleanup): void {
    $w = $world();
    $commit($w, '1.0.0', true, 'first');
    ($w['git'])($w['dir'], 'clone -q origin.git live');
    ($w['git'])($w['dir'] . '/dev', 'tag v1.0.0');
    ($w['git'])($w['dir'] . '/dev', 'push -q origin v1.0.0');
    $tagged = $head($w, 'dev');
    $commit($w, '1.0.0', true, 'same version, new commit');              // VERSION not raised
    [$code] = $run($w);
    T::eq($code, 0, 'delivered');
    T::eq($head($w, 'origin.git', 'v1.0.0^{commit}'), $tagged, 'v1.0.0 still points where it pointed');
    $cleanup($w);
},

];
