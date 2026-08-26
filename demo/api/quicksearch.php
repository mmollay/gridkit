<?php
/**
 * Example endpoint for GK.search — returns hits grouped by category.
 *
 * The shape below is the contract. Every application fills it with its own
 * data; GridKit only draws the widget and decides nothing about what is
 * searched.
 *
 *   { "groups": [ { "title": "Customers",
 *                   "items": [ { "title", "subtitle", "amount", "url", "icon" } ] } ] }
 *
 * The German key names this endpoint used to emit — gruppen / titel / treffer /
 * untertitel / betrag — are still accepted by GK.search so existing endpoints
 * keep working, but English is the documented shape.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

/** Lowercase without requiring mbstring — GridKit runs without it. */
function lower(string $s): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
}

$q = trim((string) ($_GET['q'] ?? ''));

$data = [
    'Customers' => [
        ['title' => 'Ecklund & Partner',   'subtitle' => 'Vienna · Customer no. 1042',   'url' => '#customer-1042', 'icon' => 'business'],
        ['title' => 'Nordlicht Media',     'subtitle' => 'Graz · Customer no. 1088',     'url' => '#customer-1088', 'icon' => 'business'],
        ['title' => 'Bergmann Logistik',   'subtitle' => 'Salzburg · Customer no. 1120', 'url' => '#customer-1120', 'icon' => 'business'],
    ],
    'Invoices' => [
        ['title' => 'INV-2026-0184', 'subtitle' => 'Ecklund & Partner · Mar 12, 2026', 'amount' => '€1,240.00', 'url' => '#inv-184', 'icon' => 'receipt_long'],
        ['title' => 'INV-2026-0201', 'subtitle' => 'Nordlicht Media · Mar 28, 2026',   'amount' => '€890.50',   'url' => '#inv-201', 'icon' => 'receipt_long'],
    ],
    'Transactions' => [
        ['title' => 'Zurich Insurance', 'subtitle' => 'Oct 3, 2025 · Trailer WB-447EU', 'amount' => '€8.86',  'url' => '#tx-1', 'icon' => 'account_balance'],
        ['title' => 'Hetzner',          'subtitle' => 'Jul 7, 2026 · Server Germany',   'amount' => '€64.80', 'url' => '#tx-2', 'icon' => 'account_balance'],
    ],
];

$groups = [];
foreach ($data as $title => $items) {
    $found = array_values(array_filter($items, static function (array $item) use ($q): bool {
        if ($q === '') return true;
        $haystack = lower($item['title'] . ' ' . ($item['subtitle'] ?? '') . ' ' . ($item['amount'] ?? ''));
        return str_contains($haystack, lower($q));
    }));

    if ($found) {
        $groups[] = ['title' => $title, 'items' => $found];
    }
}

echo json_encode(['groups' => $groups], JSON_UNESCAPED_UNICODE);
