<?php

/**
 * Create or update one invoice.
 *
 * GridKit's AJAX form expects JSON back, in one of two shapes:
 *
 *   {"ok": true}                          close the modal, reload the table
 *   {"errors": {"field": "message"}}      show the messages next to the fields
 *
 * Anything else is treated as a failure.
 */

declare(strict_types=1);

require_once __DIR__ . '/store.php';

$errors = [];

$number   = trim((string) ($_POST['number']   ?? ''));
$customer = trim((string) ($_POST['customer'] ?? ''));
$amountIn = trim((string) ($_POST['amount']   ?? ''));

if ($number === '')   $errors['number']   = t('required');
if ($customer === '') $errors['customer'] = t('required');

// Accept both 1234.50 and 1.234,50 — the form shows whichever the locale uses.
$normalised = str_replace(' ', '', $amountIn);
if (substr_count($normalised, ',') === 1 && strrpos($normalised, ',') > (int) strrpos($normalised, '.')) {
    $normalised = str_replace(['.', ','], ['', '.'], $normalised);
} else {
    $normalised = str_replace(',', '', $normalised);
}
if ($amountIn === '' || !is_numeric($normalised)) {
    $errors['amount'] = t('not_a_number');
}

if ($errors) {
    respond(['ok' => false, 'errors' => $errors]);
}

$statusIn = (string) ($_POST['status'] ?? 'draft');
$record = [
    'number'   => $number,
    'customer' => $customer,
    'amount'   => round((float) $normalised, 2),
    'issued'   => (string) ($_POST['issued'] ?? date('Y-m-d')),
    'due'      => (string) ($_POST['due']    ?? date('Y-m-d')),
    'status'   => isset(statuses()[$statusIn]) ? $statusIn : 'draft',
    'notes'    => trim((string) ($_POST['notes'] ?? '')),
];

$id   = (int) ($_POST['id'] ?? 0);
$rows = allInvoices();

if ($id > 0) {
    foreach ($rows as $i => $row) {
        if ((int) $row['id'] === $id) {
            $rows[$i] = ['id' => $id] + $record;
            break;
        }
    }
} else {
    $rows[] = ['id' => nextId()] + $record;
}

saveAll($rows);
respond(['ok' => true]);
