<?php
/**
 * The body of the side sheet a row of the people list opens. GK.sheet POSTs
 * the row's params here (->rowLink(['sheet' => …, 'url' => …])) and puts the
 * answer into .gk-sheet-body. Everything that CHANGES lives here, not in the
 * row: the switch with one sentence on what it does, the plan, the breakdown
 * the row only shows one number of.
 */
require_once __DIR__ . '/../../autoload.php';

use GridKit\{Form, StatCards};

$people = require __DIR__ . '/_people.php';
$id = (int) ($_POST['id'] ?? 0);
$person = null;
foreach ($people as $p) {
    if ($p['id'] === $id) { $person = $p; break; }
}
if ($person === null) {
    http_response_code(404);
    exit;
}

$e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

echo '<p class="gk-text-muted gk-mt-0 gk-mb-4">' . $e($person['email']) . ' · last seen ' . $e($person['seen']) . '</p>';

// Rule 1: the switch is the one sign of its state, with one sentence on what
// it does — a toggle with a 'hint' is a settings row (.gk-setting, 1.92.0).
(new Form('person-login-' . $id))
    ->field('person_login', 'Sign-in allowed', 'toggle', [
        'value' => $person['login'],
        'hint'  => 'Takes effect at once — running sessions included.',
    ])
    ->render();

(new Form('person-plan-' . $id))
    ->field('person_plan', 'Plan', 'select', [
        'options' => ['Free' => 'Free', 'Team' => 'Team', 'Pro' => 'Pro'],
        'value'   => $person['plan'],
    ])
    ->render();

// The breakdown the row shows one number of, as compact figures. Rule 4: only
// the count that needs acting on is coloured, and only when it is not zero.
echo '<h3 class="gk-fs-md gk-fw-semibold gk-mt-5 gk-mb-2">Last 30 days</h3>';
(new StatCards('person-figures-' . $id))
    ->compact()
    ->card('failing', $person['failing'], $person['failing'] > 0 ? ['color' => 'danger'] : [])
    ->card('open', $person['open'])
    ->card('done', $person['done'])
    ->render();
