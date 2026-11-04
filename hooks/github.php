<?php
declare(strict_types=1);

// GitHub webhook: a push to main asks the server to deliver it. The work is in ci/deploy-hook.php
// (ci/ answers 403 over HTTP); setup in ci/README.md, "Automatic delivery". Both paths can be
// moved with SetEnv in the server's configuration.
require dirname(__DIR__) . '/ci/deploy-hook.php';

[$status, $text] = gkDeployHook(
    (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'),
    $_SERVER,
    (string) file_get_contents('php://input'),
    (string) (getenv('GK_HOOK_SECRET_FILE') ?: '/etc/gridkit-deploy/hook-secret'),
    (string) (getenv('GK_HOOK_TRIGGER') ?: '/var/lib/gridkit-deploy/trigger'),
);

http_response_code($status);
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
echo $text;
