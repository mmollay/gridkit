<?php
header('Content-Type: application/json');
$q = strtolower(trim($_GET['q'] ?? ''));
$data = [
    ['id' => 1, 'name' => 'Mustermann GmbH', 'city' => 'Wien'],
    ['id' => 2, 'name' => 'Tech Solutions AG', 'city' => 'Graz'],
    ['id' => 3, 'name' => 'Weber & Partner', 'city' => 'Salzburg'],
    ['id' => 4, 'name' => 'Digital Agentur Wien', 'city' => 'Wien'],
    ['id' => 5, 'name' => 'Startup Hub Vienna', 'city' => 'Wien'],
    ['id' => 6, 'name' => 'Alpen Consulting', 'city' => 'Innsbruck'],
    ['id' => 7, 'name' => 'Cafe Central KG', 'city' => 'Wien'],
    ['id' => 8, 'name' => 'Donau Logistics', 'city' => 'Linz'],
];
$results = array_filter($data, fn($r) => !$q || str_contains(strtolower($r['name']), $q) || str_contains(strtolower($r['city']), $q));
echo json_encode(array_values($results));
