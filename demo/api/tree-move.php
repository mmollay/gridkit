<?php
/**
 * The move endpoint of the demo's page tree (GridKit\Tree::moveUrl(), 1.116.0).
 *
 * js/tree.js posts every move as JSON {"id", "parent", "index"} — parent null for the
 * top level, index counted from 0 among the new siblings — with the headers given to
 * moveUrl(). A 2xx is saved unless the JSON says {"ok": false}; anything else puts the
 * node back, and "message" is what the person reads.
 *
 * The demo stores nothing (a reload shows the tree as it was) and refuses one move on
 * purpose, so the way back can be seen: nothing may take the place of Home at the top.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$answer = static function (int $status, array $body): never {
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
};

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    $answer(405, ['ok' => false, 'message' => 'Moves are posted.']);
}
// The token the page gave moveUrl() in 'headers'. A real application compares it with the session's.
if (($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '') !== 'demo-token') {
    $answer(403, ['ok' => false, 'message' => 'The page is out of date. Reload it and try again.']);
}

$move = json_decode((string) file_get_contents('php://input'), true);
$id = is_array($move) && is_scalar($move['id'] ?? null) ? (string) $move['id'] : '';
$parent = is_array($move) && array_key_exists('parent', $move) ? $move['parent'] : false;
$index = is_array($move) ? filter_var($move['index'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) : false;
if ($id === '' || $index === false || !($parent === null || is_string($parent))) {
    $answer(400, ['ok' => false, 'message' => 'The move could not be read.']);
}

if ($parent === null && $index === 0 && $id !== 'home') {
    $answer(409, ['ok' => false, 'message' => 'Home stays at the top — the page went back to its place.']);
}

usleep(250000);
$answer(200, ['ok' => true]);
