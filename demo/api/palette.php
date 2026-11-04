<?php
/**
 * Records for the demo's command palette (1.122.0): GET api/palette.php?q=… — customers and invoices.
 *
 * Stateless: the records are written here, nothing is stored. The answer is the shape GK.search reads,
 * which CommandPalette::search() reads too:
 *
 *   { "groups": [ { "title": "Customers", "items": [ { "title", "subtitle", "url", "icon", "amount" } ] } ] }
 *
 * The query arrives as it was typed. It is folded the way the palette folds it — case, accents, and the
 * German umlauts both ways — so "muller", "mueller" and "Müller" find the same customer. Two words on
 * purpose: "fail" answers 500 (the palette says so and offers to ask again), "slow" waits a second
 * and a half (the palette says it is searching; typing on drops the late answer).
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

/** Lower case, accents off, ß → ss; with $german the umlauts become ae, oe, ue. No mbstring needed. */
function paletteFold(string $s, bool $german): string
{
    $map = ['ß' => 'ss', 'ẞ' => 'ss', 'æ' => 'ae', 'Æ' => 'ae', 'œ' => 'oe', 'Œ' => 'oe', 'ø' => 'o', 'Ø' => 'o',
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'å' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'í' => 'i',
        'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ú' => 'u', 'ù' => 'u', 'û' => 'u',
        'ç' => 'c', 'ñ' => 'n', 'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ç' => 'c', 'Ñ' => 'n'];
    $map += $german
        ? ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'ae', 'Ö' => 'oe', 'Ü' => 'ue']
        : ['ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'Ä' => 'a', 'Ö' => 'o', 'Ü' => 'u'];
    return strtolower(strtr($s, $map));
}

$q = $_GET['q'] ?? '';
$q = is_string($q) ? trim((string) preg_replace('/\s+/u', ' ', $q)) : '';
if (strlen($q) > 100) $q = substr($q, 0, 100);

if (paletteFold($q, false) === 'fail') {
    http_response_code(500);
    echo json_encode(['error' => 'The demo fails on purpose for "fail".']);
    return;
}
if (paletteFold($q, false) === 'slow') usleep(1500000);

$records = [
    'Customers' => [
        ['title' => 'Ecklund & Partner', 'subtitle' => 'Vienna · Customer no. 1042', 'url' => '#palette&record=c1042', 'icon' => 'business'],
        ['title' => 'Müller & Söhne GmbH', 'subtitle' => 'Graz · Customer no. 1050', 'url' => '#palette&record=c1050', 'icon' => 'business'],
        ['title' => 'Bäckerei Groß', 'subtitle' => 'Linz · Customer no. 1061', 'url' => '#palette&record=c1061', 'icon' => 'storefront'],
        ['title' => 'Nordlicht Media', 'subtitle' => 'Salzburg · Customer no. 1088', 'url' => '#palette&record=c1088', 'icon' => 'business'],
        ['title' => 'Café Central KG', 'subtitle' => 'Vienna · Customer no. 1093', 'url' => '#palette&record=c1093', 'icon' => 'local_cafe'],
        ['title' => 'Ørsted Wind Services', 'subtitle' => 'Innsbruck · Customer no. 1120', 'url' => '#palette&record=c1120', 'icon' => 'business'],
    ],
    'Invoices' => [
        ['title' => 'INV-2026-0184', 'subtitle' => 'Ecklund & Partner · Mar 12, 2026', 'amount' => '€1,240.00', 'url' => '#palette&record=i184', 'icon' => 'receipt_long'],
        ['title' => 'INV-2026-0201', 'subtitle' => 'Nordlicht Media · Mar 28, 2026', 'amount' => '€890.50', 'url' => '#palette&record=i201', 'icon' => 'receipt_long'],
        ['title' => 'INV-2026-0207', 'subtitle' => 'Müller & Söhne GmbH · Apr 2, 2026', 'amount' => '€4,312.80', 'url' => '#palette&record=i207', 'icon' => 'receipt_long'],
        ['title' => 'INV-2026-0215', 'subtitle' => 'Bäckerei Groß · Apr 9, 2026', 'amount' => '€96.00', 'url' => '#palette&record=i215', 'icon' => 'receipt_long'],
    ],
];

$groups = [];
if ($q !== '') {
    $needles = array_unique([paletteFold($q, false), paletteFold($q, true)]);
    foreach ($records as $title => $items) {
        $found = array_values(array_filter($items, static function (array $r) use ($needles): bool {
            $text = $r['title'] . ' ' . $r['subtitle'];
            foreach ([paletteFold($text, false), paletteFold($text, true)] as $hay) {
                foreach ($needles as $n) {
                    if (str_contains($hay, $n)) return true;
                }
            }
            return false;
        }));
        if ($found) $groups[] = ['title' => $title, 'items' => $found];
    }
}

echo json_encode(['groups' => $groups], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
