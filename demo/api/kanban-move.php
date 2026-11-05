<?php
/**
 * The move endpoint of the demo's ticket board (GridKit\Kanban::moveUrl(), 1.124.0).
 *
 * js/kanban.js posts every move as JSON {"id", "column", "index"} — index counted from 0 among the
 * cards of the new column — with the headers given to moveUrl(). Without the script the card's
 * "Move to" form posts id and column (and the hidden fields of moveUrl()) as a plain form.
 * Kanban::readMove() reads either. A 2xx is saved unless the JSON says {"ok": false}; anything else
 * puts the card back, and "message" is what the person reads.
 *
 * The demo stores nothing (a reload shows the board as it was) and refuses one move on purpose, so
 * the way back can be seen: a refund ticket is closed by accounting, never from the board.
 */

declare(strict_types=1);

require __DIR__ . '/../../autoload.php';

use GridKit\Kanban;

$move = Kanban::readMove();
$form = $move !== null ? $move['form'] : !str_contains(strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? '')), 'json');

// After a form, back to the page (the board shows what the server holds); after JSON, JSON.
$answer = static function (int $status, array $body) use ($form): never {
    if ($form) {
        $back = (string) ($_SERVER['HTTP_REFERER'] ?? '../index.php');
        $back = preg_replace('/[?#].*$/', '', $back) . '?kanban=' . ($body['ok'] ? 'saved' : 'refused') . '#kanban';
        header('Location: ' . $back, true, 303);
        exit;
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
};

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    $answer(405, ['ok' => false, 'message' => 'Moves are posted.']);
}
// The token the page gave moveUrl(): in a header from the script, in a hidden field from the form.
// A real application compares it with the session's.
$token = $form ? (string) ($_POST['_token'] ?? '') : (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if ($token !== 'demo-token') {
    $answer(403, ['ok' => false, 'message' => 'The page is out of date. Reload it and try again.']);
}
if ($move === null) {
    $answer(400, ['ok' => false, 'message' => 'The move could not be read.']);
}
$columns = ['new', 'doing', 'review', 'done'];
if (!in_array($move['column'], $columns, true)) {
    $answer(422, ['ok' => false, 'message' => 'The board has no such column.']);
}
// The server checks the limits too: the page can be old, or another person faster.
// (A real board counts the column in its database; the demo trusts the page.)

if ($move['id'] === 'T-104' && $move['column'] === 'done') {
    $answer(409, ['ok' => false, 'message' => 'A refund is closed by accounting, not on the board — the ticket went back.']);
}

usleep(250000);
$answer(200, ['ok' => true]);
