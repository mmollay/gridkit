<?php
/**
 * Language tests.
 *
 * The important one is `english output stays english`: GridKit shipped for a
 * long time with German defaults baked into public method signatures
 * (`search($name, $value, $placeholder = 'Suche…')`). Those are invisible to a
 * grep for translation calls and invisible to anyone developing in German.
 * Rendering every component under `en` and failing on a German word is the
 * only check that catches the whole class at once.
 */

declare(strict_types=1);

use GridKit\{Lang, Pagination, PageSize, Select, TableHeader, YearFilter, Table, Form, Button, StatCards};

/** Words that must never appear in English output. */
const GERMAN_WORDS = [
    'Suche', 'Suchen', 'Wählen', 'Zeilen', 'Einträge', 'Seite ', ' von ',
    'Erste', 'Letzte', 'Zurück', 'Weiter', 'Filter zurücksetzen',
    'Erweiterte', 'Alle Jahre', 'Speichern', 'Abbrechen', 'Löschen',
    'Keine', 'Auswahl', 'Bearbeiten',
];

/** @return array<string,callable> */
return [

'en and de define exactly the same keys' => function (): void {
    $en = require __DIR__ . '/../lang/en.php';
    $de = require __DIR__ . '/../lang/de.php';
    $onlyEn = array_diff(array_keys($en), array_keys($de));
    $onlyDe = array_diff(array_keys($de), array_keys($en));
    T::ok($onlyEn === [], 'keys missing from de.php: ' . implode(', ', $onlyEn));
    T::ok($onlyDe === [], 'keys missing from en.php: ' . implode(', ', $onlyDe));
    T::ok(count($en) > 60, 'expected a substantial catalogue, got ' . count($en));
},

'no translation value is left empty' => function (): void {
    foreach (['en', 'de'] as $loc) {
        $strings = require __DIR__ . "/../lang/$loc.php";
        foreach ($strings as $k => $v) {
            T::ok(is_string($v) && trim($v) !== '', "$loc/$k is empty");
        }
    }
},

'placeholders match between en and de' => function (): void {
    $en = require __DIR__ . '/../lang/en.php';
    $de = require __DIR__ . '/../lang/de.php';
    foreach ($en as $key => $value) {
        preg_match_all('/\{(\w+)\}/', (string) $value,        $a);
        preg_match_all('/\{(\w+)\}/', (string) ($de[$key] ?? ''), $b);
        sort($a[1]); sort($b[1]);
        T::eq($b[1], $a[1], "placeholders differ for '$key'");
    }
},

'english output stays english' => function (): void {
    Lang::set('en');

    $rendered = [
        'Pagination' => T::capture(fn() => Pagination::render([
            'page' => 2, 'totalPages' => 5, 'total' => 1234, 'baseUrl' => '/x',
        ])),
        'PageSize' => T::capture(fn() => PageSize::make()->current(25)->render()),
        'Select'   => Select::searchable('country', ['at' => 'Austria', 'de' => 'Germany']),
        'TableHeader' => T::capture(fn() => TableHeader::make('t')
            ->search('q')
            ->advanced(fn() => print('<i></i>'))
            ->reset('/x')
            ->render()),
        'YearFilter' => T::capture(fn() => (new YearFilter())
            ->range(2020, 2024)->allOption()->render()),
        'Table' => T::capture(fn() => (new Table('t'))
            ->setData([['id' => 1, 'name' => 'Widget']])
            ->column('name', 'Name')
            ->render()),
    ];

    foreach ($rendered as $component => $html) {
        T::ok($html !== '', "$component rendered nothing");
        foreach (GERMAN_WORDS as $word) {
            T::notContains($html, $word, "$component leaks German in en locale");
        }
        T::ok(!preg_match('/[äöüÄÖÜß]/u', $html),
            "$component contains umlauts in en locale");
    }
},

'german locale actually produces german' => function (): void {
    Lang::set('de');
    $html = T::capture(fn() => Pagination::render([
        'page' => 2, 'totalPages' => 5, 'total' => 1234, 'baseUrl' => '/x',
    ]));
    T::contains($html, 'Einträge', 'de locale should say Einträge');
    T::contains($html, 'Seite 2 von 5', 'de locale should say Seite X von Y');
    Lang::set('en');
},

'thousands separator follows the locale' => function (): void {
    Lang::set('en');
    $en = T::capture(fn() => Pagination::render(
        ['page' => 1, 'totalPages' => 2, 'total' => 1234567, 'baseUrl' => '/x']));
    T::contains($en, '1,234,567', 'english grouping');

    Lang::set('de');
    $de = T::capture(fn() => Pagination::render(
        ['page' => 1, 'totalPages' => 2, 'total' => 1234567, 'baseUrl' => '/x']));
    T::contains($de, '1.234.567', 'german grouping');
    Lang::set('en');
},

'an explicit label still wins over the translation' => function (): void {
    Lang::set('en');
    $html = T::capture(fn() => PageSize::make()->label('Per page')->current(10)->render());
    T::contains($html, 'Per page', 'caller-supplied label must not be overridden');
},

'jsConfig exports only js.* keys, as valid JSON' => function (): void {
    Lang::set('en');
    $tag = Lang::jsConfig();
    T::contains($tag, 'window.GK_LANG=', 'jsConfig emits the global');
    preg_match('/window\.GK_LANG=(.*?);<\/script>/s', $tag, $m);
    $data = json_decode($m[1] ?? '', true);
    T::ok(is_array($data) && $data !== [], 'jsConfig payload is a non-empty object');
    foreach (array_keys($data ?: []) as $k) {
        T::ok(!str_starts_with((string) $k, 'js.'), "js. prefix should be stripped: $k");
    }
},

'an unknown key falls back to english, then to itself' => function (): void {
    Lang::set('de');
    T::eq(Lang::t('definitely.not.a.key'), 'definitely.not.a.key', 'unknown key returns itself');
    Lang::set('en');
},

];
