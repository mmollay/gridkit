<?php
declare(strict_types=1);

/**
 * The GitHub webhook of the automatic delivery (ci/README.md, "Automatic delivery").
 *
 * GitHub posts every push to https://gridkit.at/hooks/github.php, which hands the request to
 * gkDeployHook(). It checks the signature (X-Hub-Signature-256, HMAC-SHA256 with the secret set
 * in the webhook) and, for a push to main, writes one line to a trigger file. That is all it does:
 * no git, no shell, nothing that runs as the web server's user. A systemd path unit watches the
 * trigger file and starts ci/gridkit-deploy.sh as pawbot, which runs the suite and delivers.
 *
 * It lives in ci/, which the web server refuses (403); only hooks/github.php is reachable.
 */

/**
 * @param array<string,mixed> $server  $_SERVER: HTTP_X_HUB_SIGNATURE_256, HTTP_X_GITHUB_EVENT
 * @return array{0:int,1:string}       status code and a short plain-text answer
 */
function gkDeployHook(string $method, array $server, string $body, string $secretFile, string $triggerFile): array
{
    if ($method !== 'POST') return [405, "POST only\n"];

    $secret = is_file($secretFile) && is_readable($secretFile) ? trim((string) file_get_contents($secretFile)) : '';
    if ($secret === '') return [503, "not set up\n"];

    // The signature first: nothing of an unsigned request is read, and the answer says no more.
    $sig = (string) ($server['HTTP_X_HUB_SIGNATURE_256'] ?? '');
    if (!hash_equals('sha256=' . hash_hmac('sha256', $body, $secret), $sig)) return [401, "bad signature\n"];

    $event = (string) ($server['HTTP_X_GITHUB_EVENT'] ?? '');
    if ($event === 'ping') return [200, "pong\n"];          // GitHub's test when the hook is saved
    if ($event !== 'push') return [202, "ignored\n"];

    // application/json, or the form encoding GitHub also offers (payload=<json>).
    $data = json_decode($body, true);
    if (!is_array($data) && str_starts_with($body, 'payload=')) {
        parse_str($body, $form);
        $data = json_decode((string) ($form['payload'] ?? ''), true);
    }
    if (!is_array($data) || ($data['ref'] ?? '') !== 'refs/heads/main') return [202, "ignored\n"];

    $after = preg_match('/^[0-9a-f]{40}$/', (string) ($data['after'] ?? '')) ? $data['after'] : '-';
    if (@file_put_contents($triggerFile, gmdate('c') . ' ' . $after . "\n") === false) {
        return [500, "cannot queue\n"];
    }
    return [202, "queued\n"];
}
