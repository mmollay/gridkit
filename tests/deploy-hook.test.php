<?php
declare(strict_types=1);

/**
 * hooks/github.php → ci/deploy-hook.php: the GitHub webhook of the automatic delivery.
 *
 * It must answer only a correctly signed request, start a delivery only for a push to main, and do
 * nothing but write the trigger file a systemd path unit watches — no git, no shell.
 */

require_once dirname(__DIR__) . '/ci/deploy-hook.php';

$setup = static function (): array {
    $dir = sys_get_temp_dir() . '/gk-hook-' . bin2hex(random_bytes(4));
    mkdir($dir);
    file_put_contents($dir . '/secret', "s3cret\n");
    return [$dir . '/secret', $dir . '/trigger', $dir];
};
$sign = static fn(string $body, string $secret = 's3cret'): string => 'sha256=' . hash_hmac('sha256', $body, $secret);
$push = static fn(string $ref): string => (string) json_encode(['ref' => $ref, 'after' => str_repeat('a', 40)]);
$cleanup = static function (string $dir): void { exec('rm -rf ' . escapeshellarg($dir)); };

return [

'a signed push to main writes the trigger, and only that' => function () use ($setup, $sign, $push, $cleanup): void {
    [$secret, $trigger, $dir] = $setup();
    $body = $push('refs/heads/main');
    $r = gkDeployHook('POST', ['HTTP_X_HUB_SIGNATURE_256' => $sign($body), 'HTTP_X_GITHUB_EVENT' => 'push'], $body, $secret, $trigger);
    T::eq($r, [202, "queued\n"], 'queued');
    T::ok(is_file($trigger), 'the trigger file is written');
    T::contains((string) file_get_contents($trigger), str_repeat('a', 40), 'with the commit GitHub named');
    $cleanup($dir);
},

'nothing happens for an unsigned, a wrongly signed or a GET request' => function () use ($setup, $sign, $push, $cleanup): void {
    [$secret, $trigger, $dir] = $setup();
    $body = $push('refs/heads/main');
    T::eq(gkDeployHook('GET', [], '', $secret, $trigger)[0], 405, 'GET is refused');
    T::eq(gkDeployHook('POST', ['HTTP_X_GITHUB_EVENT' => 'push'], $body, $secret, $trigger)[0], 401, 'no signature');
    T::eq(gkDeployHook('POST', ['HTTP_X_HUB_SIGNATURE_256' => $sign($body, 'other'), 'HTTP_X_GITHUB_EVENT' => 'push'], $body, $secret, $trigger)[0], 401, 'another secret');
    T::eq(gkDeployHook('POST', ['HTTP_X_HUB_SIGNATURE_256' => $sign($body), 'HTTP_X_GITHUB_EVENT' => 'push'], $body . ' ', $secret, $trigger)[0], 401, 'a body changed after signing');
    T::ok(!is_file($trigger), 'and no trigger was written');
    $cleanup($dir);
},

'without a secret on the server it is not set up, and says no more' => function () use ($sign, $push): void {
    $body = $push('refs/heads/main');
    $r = gkDeployHook('POST', ['HTTP_X_HUB_SIGNATURE_256' => $sign($body), 'HTTP_X_GITHUB_EVENT' => 'push'], $body, '/nonexistent/secret', '/nonexistent/trigger');
    T::eq($r, [503, "not set up\n"], '503, no path, no detail');
},

'a ping answers, other events and other branches are ignored' => function () use ($setup, $sign, $push, $cleanup): void {
    [$secret, $trigger, $dir] = $setup();
    T::eq(gkDeployHook('POST', ['HTTP_X_HUB_SIGNATURE_256' => $sign('{}'), 'HTTP_X_GITHUB_EVENT' => 'ping'], '{}', $secret, $trigger), [200, "pong\n"], 'ping');
    $body = $push('refs/heads/feature');
    T::eq(gkDeployHook('POST', ['HTTP_X_HUB_SIGNATURE_256' => $sign($body), 'HTTP_X_GITHUB_EVENT' => 'push'], $body, $secret, $trigger), [202, "ignored\n"], 'a push to another branch');
    $tag = $push('refs/tags/v1.0.0');
    T::eq(gkDeployHook('POST', ['HTTP_X_HUB_SIGNATURE_256' => $sign($tag), 'HTTP_X_GITHUB_EVENT' => 'push'], $tag, $secret, $trigger)[1], "ignored\n", 'a tag push (the delivery\'s own)');
    $main = $push('refs/heads/main');
    T::eq(gkDeployHook('POST', ['HTTP_X_HUB_SIGNATURE_256' => $sign($main), 'HTTP_X_GITHUB_EVENT' => 'issues'], $main, $secret, $trigger)[1], "ignored\n", 'another event');
    T::ok(!is_file($trigger), 'none of them wrote the trigger');
    $cleanup($dir);
},

'the form encoding GitHub offers works as well' => function () use ($setup, $sign, $push, $cleanup): void {
    [$secret, $trigger, $dir] = $setup();
    $body = 'payload=' . rawurlencode($push('refs/heads/main'));
    $r = gkDeployHook('POST', ['HTTP_X_HUB_SIGNATURE_256' => $sign($body), 'HTTP_X_GITHUB_EVENT' => 'push'], $body, $secret, $trigger);
    T::eq($r[1], "queued\n", 'queued');
    $cleanup($dir);
},

'it runs nothing, is reachable, keeps its logic unreachable and out of the package' => function (): void {
    $root = dirname(__DIR__);
    foreach (['/ci/deploy-hook.php', '/hooks/github.php'] as $f) {
        $src = (string) preg_replace('~/\*.*?\*/|//[^\n]*~s', '', (string) file_get_contents($root . $f));
        foreach (['exec(', 'shell_exec', 'system(', 'passthru', 'proc_open', 'popen', '`'] as $bad) {
            T::notContains($src, $bad, "$f runs no command ($bad)");
        }
    }
    $ht = (string) file_get_contents($root . '/.htaccess');
    T::ok((bool) preg_match('~RedirectMatch 403 \^/\([^)]*\bci\b~', $ht), 'ci/ (with the logic) answers 403');
    T::ok(!preg_match('~RedirectMatch 403 \^/\([^)]*\bhooks\b~', $ht), 'hooks/ is reachable for GitHub');
    T::contains((string) file_get_contents($root . '/.gitattributes'), '/hooks            export-ignore', 'not part of the Composer package');
    foreach (['/ci/gridkit-deploy.path', '/ci/gridkit-deploy.service'] as $f) {
        T::ok(is_file($root . $f), "$f is there");
    }
    T::contains((string) file_get_contents($root . '/ci/gridkit-deploy.path'), 'PathModified=/var/lib/gridkit-deploy/trigger', 'the path unit watches the file the hook writes');
    T::contains((string) file_get_contents($root . '/hooks/github.php'), "'/var/lib/gridkit-deploy/trigger'", 'the same file');
    T::contains((string) file_get_contents($root . '/ci/gridkit-deploy.service'), 'User=pawbot', 'the delivery runs as pawbot, not as root and not as the web server');
},

];
