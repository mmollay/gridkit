<?php
/**
 * Beispiel-Endpunkt für GK.search — liefert Treffer gruppiert zurück.
 * Jedes System füllt das mit seinen eigenen Daten; das Format ist der Vertrag.
 */
header('Content-Type: application/json; charset=utf-8');

$q = trim((string) ($_GET['q'] ?? ''));
$daten = [
    'Kunden' => [
        ['titel' => 'Mustermann GmbH',    'untertitel' => 'Wien · Kundennr. 1042',   'url' => '#kunde-1042', 'icon' => 'business'],
        ['titel' => 'Tech Solutions AG',  'untertitel' => 'Graz · Kundennr. 1088',   'url' => '#kunde-1088', 'icon' => 'business'],
        ['titel' => 'Weber & Partner',    'untertitel' => 'Salzburg · Kundennr. 1120','url' => '#kunde-1120','icon' => 'business'],
    ],
    'Rechnungen' => [
        ['titel' => 'RE-2026-0184', 'untertitel' => 'Mustermann GmbH · 12.03.2026', 'betrag' => '1.240,00 €', 'url' => '#re-184', 'icon' => 'receipt_long'],
        ['titel' => 'RE-2026-0201', 'untertitel' => 'Tech Solutions AG · 28.03.2026','betrag' => '890,50 €',  'url' => '#re-201', 'icon' => 'receipt_long'],
    ],
    'Buchungen' => [
        ['titel' => 'Zürich Versicherung', 'untertitel' => '03.10.2025 · Anhänger WB-447EU', 'betrag' => '8,86 €',  'url' => '#bu-1', 'icon' => 'account_balance'],
        ['titel' => 'Hetzner',             'untertitel' => '07.07.2026 · Server Deutschland','betrag' => '64,80 €', 'url' => '#bu-2', 'icon' => 'account_balance'],
    ],
];

$gruppen = [];
foreach ($daten as $titel => $treffer) {
    $gefunden = array_values(array_filter($treffer, static function (array $t) use ($q): bool {
        if ($q === '') return true;
        $heu = mb_strtolower($t['titel'] . ' ' . ($t['untertitel'] ?? '') . ' ' . ($t['betrag'] ?? ''));
        return mb_strpos($heu, mb_strtolower($q)) !== false;
    }));
    if ($gefunden) $gruppen[] = ['titel' => $titel, 'treffer' => $gefunden];
}

echo json_encode(['gruppen' => $gruppen], JSON_UNESCAPED_UNICODE);
