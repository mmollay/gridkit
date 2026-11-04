<?php
/**
 * Example endpoint for an article search in a line of an invoice — a LineItems column of type
 * 'lookup' (1.117.0). It answers ?q=… with the articles whose name or number contain it, as a
 * JSON array. The keys are the application's own: the column's 'fill' says which of them goes
 * into which field of the line (['description' => 'name', 'price' => 'price', …]).
 *
 * Prices in machine form ('95.00' or 95), as a database hands them out: the editor writes them in
 * the shape of the page (95,00 on a German one).
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

/** Lowercase without requiring mbstring — GridKit runs without it. */
function lowerCase(string $s): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
}

$articles = [
    ['id' => 101, 'number' => 'A-101', 'name' => 'Consulting hour', 'unit' => 'h', 'price' => '95.00', 'vat' => 20],
    ['id' => 102, 'number' => 'A-102', 'name' => 'Workshop day', 'unit' => 'day', 'price' => '780.00', 'vat' => 20],
    ['id' => 201, 'number' => 'B-201', 'name' => 'Handbook (print)', 'unit' => 'pcs', 'price' => '34.50', 'vat' => 10],
    ['id' => 202, 'number' => 'B-202', 'name' => 'Handbook (e-book)', 'unit' => 'pcs', 'price' => '24.90', 'vat' => 10],
    ['id' => 301, 'number' => 'C-301', 'name' => 'Web hosting, monthly', 'unit' => 'month', 'price' => '19.90', 'vat' => 20],
    ['id' => 302, 'number' => 'C-302', 'name' => 'Domain .at, yearly', 'unit' => 'pcs', 'price' => '29.00', 'vat' => 20],
    ['id' => 401, 'number' => 'D-401', 'name' => 'Travel, flat rate', 'unit' => 'flat', 'price' => '40.00', 'vat' => 20],
];

$q = lowerCase(trim((string) ($_GET['q'] ?? '')));
$found = array_values(array_filter($articles, static fn(array $a): bool =>
    $q === '' || str_contains(lowerCase($a['name'] . ' ' . $a['number']), $q)));

echo json_encode($found, JSON_UNESCAPED_UNICODE);
