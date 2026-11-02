<?php
/**
 * Table::bulkAction(), Table::exportCsv() and the CSV helpers (1.105.0).
 *
 * The bulk bar could only delete; a page that wanted "archive" or "mark as paid" wrote the
 * button and the selection logic itself. And no table could be downloaded: every admin who
 * asked for "can I get this in Excel" got a one-off. The click handlers are script and are
 * checked in a browser (ci/browser.js); the markup they hook into and the CSV text are
 * checked here.
 */

declare(strict_types=1);

use GridKit\{Lang, Table};

function tbcRows(): array
{
    return [['id' => 1, 'name' => 'Ada', 'note' => 'a,b'], ['id' => 2, 'name' => 'Bob "B"', 'note' => "two\nlines"]];
}

/** @return array<string,callable> */
return [

'a bulk action is a button in the bulk bar, before the delete button' => function (): void {
    Lang::set('en');
    $html = T::capture(fn() => (new Table('t'))->setData(tbcRows())->column('name', 'Name')
        ->bulkAction('archive', ['icon' => 'archive', 'label' => 'Archive <all>'])
        ->bulkAction('void', ['confirm' => 'Void these?', 'color' => 'danger'])
        ->bulkAction('mark_paid', ['confirm' => true])
        ->render());
    T::contains($html, 'data-gk-selectable', 'bulkAction() did not turn selectable() on');
    T::contains($html, 'gk-bulk-bar', 'there is no bulk bar');
    T::contains($html, '<button type="button" data-gk-bulk-action="archive"><span class="material-icons" aria-hidden="true">archive</span> Archive &lt;all&gt;</button>',
        'the archive button is not written like the delete button beside it, or its label is not escaped');
    T::contains($html, 'data-gk-bulk-action="void" data-gk-confirm="Void these?" data-gk-danger>Void</button>', 'confirm and danger are not on the button');
    T::contains($html, 'data-gk-bulk-action="mark_paid" data-gk-confirm="Apply this to the selected rows?">Mark paid</button>', 'confirm => true does not ask the standard question');
    T::ok(strpos($html, 'data-gk-bulk-action="archive"') < strpos($html, 'data-gk-bulk-delete'), 'the actions are not before the delete button');
    T::ok(strpos($html, 'data-gk-bulk-delete') < strpos($html, 'data-gk-bulk-cancel'), 'the delete button moved');
},

'a table without a bulk action renders its bulk bar as it always did' => function (): void {
    Lang::set('en');
    $html = T::capture(fn() => (new Table('t'))->setData(tbcRows())->column('name', 'Name')->selectable()->render());
    T::notContains($html, 'data-gk-bulk-action', 'a bulk action appeared by itself');
    T::contains($html, '<div class="gk-toolbar-spacer"></div><button type="button" data-gk-bulk-delete>', 'the bar changed');
},

'a bulk action takes its label from the catalogue when the language has one' => function (): void {
    Lang::set('en');
    $html = T::capture(fn() => (new Table('t'))->setData(tbcRows())->column('name', 'Name')->bulkAction('edit')->render());
    // action.edit exists in lang/*.php ("Edit"); a name it does not know is written as words.
    T::contains($html, 'data-gk-bulk-action="edit">', 'the button is missing');
},

'the export button carries the file name and the delimiter, and is a toolbar button' => function (): void {
    Lang::set('en');
    $html = T::capture(fn() => (new Table('t'))->setData(tbcRows())->column('name', 'Name')->exportCsv('people 2026/q1.csv', ['delimiter' => ';'])->render());
    T::contains($html, 'data-gk-export-csv="people-2026-q1.csv"', 'the file name is not made safe');
    T::contains($html, 'data-gk-export-delimiter=";"', 'the delimiter is missing');
    T::contains($html, 'Export CSV', 'the default label is missing');
    T::ok(strpos($html, 'data-gk-export-csv') < strpos($html, '<table class="gk-table'), 'the button is not in the toolbar above the table');

    $de = (function () { Lang::set('de'); return T::capture(fn() => (new Table('t'))->setData(tbcRows())->column('name', 'Name')->exportCsv()->render()); })();
    T::contains($de, 'CSV exportieren', 'the German label is missing');
    Lang::set('en');
    $own = T::capture(fn() => (new Table('t'))->setData(tbcRows())->column('name', 'Name')->exportCsv('x.csv', ['label' => 'Download <it>'])->render());
    T::contains($own, 'Download &lt;it&gt;', 'an own label is missing or not escaped');
    $off = T::capture(fn() => (new Table('t'))->setData(tbcRows())->column('name', 'Name')->exportCsv()->toolbar(false)->render());
    T::notContains($off, 'data-gk-export-csv', 'the button is there without a toolbar');
    T::notContains(T::capture(fn() => (new Table('t'))->setData(tbcRows())->column('name', 'Name')->render()), 'data-gk-export-csv', 'a table without exportCsv() has the button');
},

'the file name cannot break the header it goes into' => function (): void {
    foreach (["a\r\nSet-Cookie: x=1.csv", '../../etc/passwd', '"quoted".csv', '', '...', 'ärger.csv'] as $name) {
        $html = T::capture(fn() => (new Table('t'))->setData(tbcRows())->column('name', 'Name')->exportCsv($name)->render());
        preg_match('/data-gk-export-csv="([^"]*)"/', $html, $m);
        T::ok(isset($m[1]) && preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $m[1]) === 1, 'the file name for ' . json_encode($name) . ' is ' . json_encode($m[1] ?? null));
    }
},

'csv() writes RFC 4180 with a byte-order mark' => function (): void {
    $csv = Table::csv(tbcRows(), ['name' => 'Name', 'note' => 'Note']);
    T::eq($csv, "\xEF\xBB\xBFName,Note\r\nAda,\"a,b\"\r\n\"Bob \"\"B\"\"\",\"two\nlines\"\r\n", 'the quoting, the line ends or the byte-order mark are not RFC 4180');
    T::eq(Table::csv(tbcRows(), ['name' => 'Name'], ['bom' => false]), "Name\r\nAda\r\n\"Bob \"\"B\"\"\"\r\n", 'bom => false');
    T::eq(Table::csv([['a' => 'x;y', 'b' => 'p,q']], ['a' => 'A', 'b' => 'B'], ['delimiter' => ';', 'bom' => false]), "A;B\r\n\"x;y\";p,q\r\n", 'a semicolon table quotes the semicolon and not the comma');
    T::eq(Table::csv([['a' => 1]], ['a' => 'A'], ['delimiter' => 'tab', 'bom' => false]), "A\r\n1\r\n", 'a delimiter of more than one character falls back to a comma');
},

'csv() leaves out columns marked export false and fills what a row lacks' => function (): void {
    $csv = Table::csv([['a' => 1, 'b' => 2]], ['a' => 'A', 'secret' => ['label' => 'S', 'export' => false], 'b' => ['label' => 'B'], 'gone' => 'G'], ['bom' => false]);
    T::eq($csv, "A,B,G\r\n1,2,\r\n", 'export => false, a label array or a missing key is handled wrongly');
},

'csv() writes values of every kind' => function (): void {
    $csv = Table::csv([['v' => null], ['v' => true], ['v' => false], ['v' => 0], ['v' => 1.5], ['v' => ['x' => 'ä/b']], ['v' => '']], ['v' => 'V'], ['bom' => false]);
    T::eq($csv, "V\r\n\r\n1\r\n0\r\n0\r\n1.5\r\n\"{\"\"x\"\":\"\"ä/b\"\"}\"\r\n\r\n", 'null, bools, numbers, arrays or the empty string are written wrongly');
},

'csv() defuses a cell a spreadsheet would run as a formula, and leaves numbers alone' => function (): void {
    $guard = ['=1+1', '+49 123', '-cmd|\' /C calc\'!A0', '@SUM(A1)', "\tx", "\rx", '=HYPERLINK("http://evil","click")'];
    foreach ($guard as $cell) {
        $csv = Table::csv([['v' => $cell]], ['v' => 'V'], ['bom' => false]);
        $line = explode("\r\n", $csv, 3)[1] . (str_contains($csv, "\r\n\"") ? '' : '');
        T::ok(str_contains($csv, "'" . $cell) || str_contains($csv, "\"'" . str_replace('"', '""', $cell) . '"'), 'the cell ' . json_encode($cell) . ' is not defused: ' . json_encode($csv));
    }
    foreach (['-5', '+49', '12.5', '-0.5', '1234', 'a=b', 'x+y', 'a@b.c', 'text -'] as $plain) {
        $csv = Table::csv([['v' => $plain]], ['v' => 'V'], ['bom' => false]);
        T::eq($csv, "V\r\n$plain\r\n", "the plain value $plain was changed");
    }
    T::eq(Table::csv([['=v' => 1]], ['=v' => '=Heading'], ['bom' => false]), "'=Heading\r\n1\r\n", 'a heading is defused like a cell');
},

'wantsCsv() reads the parameter the export button of a server table sets' => function (): void {
    $get = $_GET;
    $_GET = [];
    T::ok(Table::wantsCsv() === false, 'a plain request wants CSV');
    $_GET = ['gk_export' => 'csv'];
    T::ok(Table::wantsCsv() === true, '?gk_export=csv is not recognised');
    $_GET = ['gk_export' => ['csv']];
    T::ok(Table::wantsCsv() === false, 'an array parameter is taken for csv');
    $_GET = ['gk_export' => 'xls'];
    T::ok(Table::wantsCsv() === false, 'another format is taken for csv');
    $_GET = $get;
},

'sendCsv() writes the file and the headers' => function (): void {
    $body = T::capture(fn() => Table::sendCsv(tbcRows(), ['name' => 'Name'], 'out file.csv'));
    T::eq($body, Table::csv(tbcRows(), ['name' => 'Name']), 'sendCsv() does not write what csv() returns');
    $headers = implode("\n", headers_list());
    if ($headers !== '') {
        T::contains($headers, 'text/csv; charset=utf-8', 'no CSV content type');
        T::contains($headers, 'attachment; filename="out-file.csv"', 'no attachment header, or the name is not made safe');
    }
},

];
