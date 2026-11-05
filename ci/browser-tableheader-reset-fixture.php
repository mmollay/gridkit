<?php
/**
 * The page for the photos of the TableHeader reset (1.127.0): the toolbars of the Panel's
 * lists (status chips, search, year, a select, the reset) with invented data, plus the
 * short ones (search + reset only, a long label). One self-contained HTML document.
 *
 *     php ci/browser-tableheader-reset-fixture.php > /tmp/f.html
 */

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use GridKit\{FilterChips, Lang, TableHeader, YearFilter};

Lang::set('de');
$root = dirname(__DIR__);

$cases = [
    'Rechnungsliste: Status, Suche, Jahr, Auswahl, Zurücksetzen' => fn () => TableHeader::make('inv')
        ->status(fn () => (new FilterChips('inv-st', 'status'))->chip('', 'Alle')->chip('open', 'Offen')->chip('paid', 'Bezahlt')->render())
        ->search('q', 'Muster', 'Kunde, Nummer, Betrag…')
        ->filter(fn () => (new YearFilter('inv-y', 'year'))->range(2024, 2026)->allOption()->render())
        ->filter('<select class="gk-filter" name="z" aria-label="Zahlart"><option>Alle Zahlarten</option></select>')
        ->reset('/rechnungen'),
    'Nur Suche und Zurücksetzen' => fn () => TableHeader::make('min')->search('q', '', 'Suchen…')->reset('/x'),
    'Langer eigener Text' => fn () => TableHeader::make('lang')->search('q', '', 'Beleg, Lieferant, Nummer…')
        ->filter('<select class="gk-filter" name="a" aria-label="Konto"><option>Alle Konten und Kostenstellen</option></select>')
        ->filter('<select class="gk-filter" name="b" aria-label="Monat"><option>Alle Monate</option></select>')
        ->reset('/x', 'Alle Filter wieder entfernen'),
];

$out = '';
foreach ($cases as $title => $make) {
    ob_start();
    $make()->render();
    $out .= '<h3>' . htmlspecialchars($title) . '</h3><div class="gk-table-wrap">' . ob_get_clean() . '</div>';
}

echo '<!doctype html><html lang="de"><head><meta charset="utf-8">'
   . '<meta name="viewport" content="width=device-width,initial-scale=1">'
   . '<link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">'
   . '<style>' . file_get_contents($root . '/css/gridkit.css') . '</style>'
   . '<style>body{background:var(--gk-bg);color:var(--gk-on-surface);margin:0;padding:12px;font-family:var(--gk-font)}h3{font-size:13px;margin:18px 0 6px}</style>'
   . '</head><body class="gk-root">' . $out
   . '<script>' . file_get_contents($root . '/js/gridkit.js') . '</script></body></html>';
