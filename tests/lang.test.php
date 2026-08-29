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
        'StatCards' => T::capture(fn() => (new StatCards('s'))
            ->card('Revenue', 1234.5, ['format' => 'currency'])
            ->card('Orders', 9876, ['format' => 'number'])
            ->render()),
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

/**
 * This used to be called "exports only js.* keys" and its only loop asserted
 * that no exported key starts with "js." — which jsConfig guarantees by
 * construction, since stripping that prefix is what it does. The assertion
 * could not fail, and the two catalogues it did NOT check are the ones that
 * had already drifted: the client formatted with a hardcoded "de-DE" because
 * format.* never reached it, so an English table redrew "€1,200.00" as
 * "1.200,00 €" on the first click.
 */
'jsConfig hands the client every catalogue it renders from' => function (): void {
    $payload = static function (): array {
        preg_match('/window\.GK_LANG=(.*?);<\/script>/s', Lang::jsConfig(), $m);
        return (array) json_decode($m[1] ?? '', true);
    };

    Lang::set('en');
    $tag = Lang::jsConfig();
    T::contains($tag, 'window.GK_LANG=', 'jsConfig emits the global');
    T::ok((bool) preg_match('/window\.GK_LANG=\{/', $tag),
        'the payload must be an object — an [] is truthy and survives `|| {}` as an empty catalogue');

    $data = $payload();
    T::ok($data !== [], 'jsConfig payload is a non-empty object');

    // js.* arrives stripped, and carries its placeholders.
    T::ok(isset($data['selected']), 'js.selected should export as "selected"');
    T::contains((string) ($data['status'] ?? ''), '{pages}', 'the placeholders survive the export');

    // action.* names the row buttons. GK.table re-renders those on every sort
    // of a static table and looks them up as _t("action_" + name), so the
    // server and the client have to name the same button the same way.
    T::eq($data['action_delete'] ?? null, 'Delete', 'action.delete should export as action_delete');
    T::eq($data['action_edit'] ?? null, 'Edit', 'action.edit should export as action_edit');

    // format.* is what stopped a sorted English table turning German.
    T::eq($data['format_decimal'] ?? null, '.', 'format.decimal should export as format_decimal');
    T::eq($data['format_thousands'] ?? null, ',', 'format.thousands should export as format_thousands');
    T::eq($data['format_currency'] ?? null, '€{value}', 'format.currency should reach the client');

    Lang::set('de');
    $de = $payload();
    T::eq($de['format_decimal'] ?? null, ',', 'the active locale wins, key by key');
    T::eq($de['action_delete'] ?? null, 'Löschen', 'and so do its action names');

    // A locale GridKit does not ship must still get a catalogue: English is
    // the floor. Without it every browser string came out as its raw key
    // while the server side quietly went on reading English.
    Lang::set('fr');
    $fr = $payload();
    T::eq($fr, $data, 'an unshipped locale falls back to the full English catalogue, not to {}');

    Lang::set('en');
},

'an unknown key falls back to english, then to itself' => function (): void {
    Lang::set('de');
    T::eq(Lang::t('definitely.not.a.key'), 'definitely.not.a.key', 'unknown key returns itself');
    Lang::set('en');
},

'currency, dates and numbers follow the locale' => function (): void {
    $render = static fn(): string => T::capture(fn() => (new Table('t'))
        ->setData([['id' => 1, 'price' => 1234.5, 'due' => '2026-03-09', 'qty' => 9876]])
        ->column('price', 'Price', ['format' => 'currency'])
        ->column('due',   'Due',   ['format' => 'date'])
        ->column('qty',   'Qty',   ['format' => 'number'])
        ->render());

    Lang::set('en');
    $en = $render();
    T::contains($en, '€1,234.50', 'english currency: symbol first, comma grouping');
    T::contains($en, 'Mar 9, 2026', 'english date is unambiguous — not d.m.Y or m/d/Y');
    T::contains($en, '9,876', 'english number grouping');

    // A card and the column under it must never disagree about a number.
    $card = T::capture(fn() => (new StatCards('s'))
        ->card('Revenue', 1234.5, ['format' => 'currency'])->render());
    T::contains($card, '€1,234.50', 'StatCards uses the same locale keys as Table');

    Lang::set('de');
    $de = $render();
    T::contains($de, '1.234,50 €', 'german currency: symbol last, dot grouping');
    T::contains($de, '09.03.2026', 'german date');
    T::contains($de, '9.876', 'german number grouping');

    $cardDe = T::capture(fn() => (new StatCards('s'))
        ->card('Umsatz', 1234.5, ['format' => 'currency'])->render());
    T::contains($cardDe, '1.234,50 €', 'StatCards follows the german locale too');

    Lang::set('en');
},

'a column may override the format it was given' => function (): void {
    Lang::set('en');
    $html = T::capture(fn() => (new Table('t'))
        ->setData([['id' => 1, 'price' => 5, 'due' => '2026-03-09']])
        ->column('price', 'Price', ['format' => 'currency', 'currency' => '${value}'])
        ->column('due',   'Due',   ['format' => 'date', 'dateFormat' => 'Y-m-d'])
        ->render());
    T::contains($html, '$5.00', 'per-column currency pattern wins');
    T::contains($html, '2026-03-09', 'per-column date format wins');
},

];
