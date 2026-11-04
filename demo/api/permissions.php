<?php
/**
 * Example endpoint for PermissionMatrix::saveUrl() — the demo's "save as you go" matrix posts here.
 *
 * The contract: GridKit POSTs `{"matrix": "<id>", "changes": {"<role>": {"<permission>": true|false}}}`
 * as JSON and expects `{"ok": true}`; anything else puts the cells back and shows `error` in a toast.
 * A real endpoint checks the session and a CSRF token (saveUrl()'s `headers` option), reads the body
 * with PermissionMatrix::fromPost() and writes the result. This one stores nothing: it answers yes —
 * except to granting "Delete pages", which it refuses, so the demo can show what a refusal looks like.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../autoload.php';

use GridKit\PermissionMatrix;

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST only.']);
    return;
}

$body = json_decode((string) file_get_contents('php://input'), true);
$changes = PermissionMatrix::fromPost(
    is_array($body) ? ($body['changes'] ?? []) : [],
    ['editor', 'author'],
    ['cms.view', 'cms.edit', 'cms.publish', 'cms.delete']
);

if ($changes === []) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Nothing this matrix can change was sent.']);
    return;
}

foreach ($changes as $role => $cells) {
    if (($cells['cms.delete'] ?? false) === true) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'This demo does not let anyone delete pages — the box was put back.']);
        return;
    }
}

echo json_encode(['ok' => true]);
