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

// Accept 1234.50, 1,234.50 and 1.234,50 — the form shows whichever the locale
// uses, and people paste all three.
//
// The old rule kept a lone dot as a decimal point, so a German typing a round
// amount without cents — 12.750, which is twelve thousand seven hundred and
// fifty — was stored as 12.75. A thousandth of what they meant, silently, with
// {"ok":true} coming back.
//
// Where BOTH separators appear the last one is the decimal point, always. A
// lone separator is only ambiguous in front of exactly three digits (1.500 is
// fifteen hundred to a German and one and a half to an English reader), and
// that one case is decided by the language the form was filled in.
$normalised = str_replace([' ', "\u{00A0}"], '', $amountIn);
$lastDot    = strrpos($normalised, '.');
$lastComma  = strrpos($normalised, ',');

if ($lastDot !== false && $lastComma !== false) {
    $decimal    = $lastComma > $lastDot ? ',' : '.';
    $thousands  = $decimal === ',' ? '.' : ',';
    $normalised = str_replace($thousands, '', $normalised);
    $normalised = str_replace($decimal, '.', $normalised);
} elseif ($lastDot !== false || $lastComma !== false) {
    $sep   = $lastDot !== false ? '.' : ',';
    $pos   = $lastDot !== false ? $lastDot : $lastComma;
    $tail  = substr($normalised, $pos + 1);
    $only  = substr_count($normalised, $sep) === 1;
    // Three trailing digits after a single separator: ambiguous. Anything else
    // — one, two, or four-plus digits, or a repeated separator — is decided by
    // the shape alone.
    $ambiguous = $only && strlen($tail) === 3 && ctype_digit($tail);
    if (!$only) {
        $isThousands = true;                     // 1.234.567 — repeated, so grouping
    } elseif (!$ambiguous) {
        $isThousands = false;                    // 12.5 or 12.3456 — a decimal point
    } else {
        // 12.750 / 12,750 — decided by the language the form was filled in.
        $formLang    = ($lang ?? 'en') === 'de' ? 'de' : 'en';
        $isThousands = ($formLang === 'de' && $sep === '.')
                    || ($formLang === 'en' && $sep === ',');
    }
    $normalised = $isThousands
        ? str_replace($sep, '', $normalised)
        : str_replace($sep, '.', $normalised);
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
