<?php
/**
 * GridKit\Import (1.119.0) — a CSV file into checked rows, with the columns matched to fields.
 *
 * What the server does is checked here: reading a CSV as Excel and other programs write it (a byte
 * order mark, CRLF, LF and CR, quotes around separators, line breaks and quotes, empty lines,
 * Windows-1252 and UTF-16, Excel's sep= line), finding the separator, the quotation mark and the
 * header row, the limits and what hostile input becomes; matching columns to fields by name and
 * alias; every type, required and unique; the three steps of the form and the file kept between
 * them (under a token, or in the form), with nothing taken from the browser unchecked; the markup,
 * its escaping and its words; the contract with js/import.js and with the stylesheet. What the
 * script does — the same reading and checking in a browser, drag and drop, the live preview,
 * contrast — needs a browser: ci/browser-import.js.
 */

declare(strict_types=1);

use GridKit\{Import, Lang};

require_once __DIR__ . '/css-helpers.php';

/** Run, and collect what it said with E_USER_WARNING. @return array{0:mixed,1:list<string>} */
function impQuiet(callable $fn): array
{
    $notes = [];
    set_error_handler(static function (int $no, string $msg) use (&$notes): bool {
        if ($no !== E_USER_WARNING) return false;
        $notes[] = $msg;
        return true;
    });
    try {
        $out = $fn();
    } finally {
        restore_error_handler();
    }
    return [$out, $notes];
}

/** The customer import the tests share. */
function impCustomers(string $name = 'c'): Import
{
    return (new Import($name))
        ->field('no', 'Customer no.', ['required' => true, 'unique' => true, 'aliases' => ['Kundennummer', 'Nr']])
        ->field('first', 'First name', ['aliases' => ['Vorname']])
        ->field('last', 'Last name', ['required' => true, 'aliases' => ['Nachname']])
        ->field('email', 'E-mail', ['type' => 'email', 'required' => true, 'unique' => true, 'aliases' => ['E-Mail', 'Mail']])
        ->field('country', 'Country', ['type' => 'select', 'options' => ['AT' => 'Austria', 'DE' => 'Germany', 'CH' => 'Switzerland'], 'aliases' => ['Land']])
        ->field('born', 'Birthday', ['type' => 'date', 'aliases' => ['Geburtsdatum']])
        ->field('revenue', 'Revenue', ['type' => 'money', 'aliases' => ['Umsatz']])
        ->field('news', 'Newsletter', ['type' => 'bool'])
        ->locale('de');
}

/** A CSV as German Excel writes it: ;, CRLF, Windows-1252. */
function impExcel(array $rows): string
{
    $out = '';
    foreach ($rows as $r) {
        $out .= implode(';', array_map(static fn (string $c): string => preg_match('/[;"\r\n]/', $c) ? '"' . str_replace('"', '""', $c) . '"' : $c, $r)) . "\r\n";
    }
    return (string) iconv('UTF-8', 'Windows-1252', $out);
}

/** A directory of its own for the files kept between the steps. */
function impDir(): string
{
    $dir = sys_get_temp_dir() . '/gk-import-test-' . getmypid() . '-' . bin2hex(random_bytes(3));
    @mkdir($dir, 0700, true);
    return $dir;
}

function impRmDir(string $dir): void
{
    foreach (glob($dir . '/*') ?: [] as $f) @unlink($f);
    @rmdir($dir);
}

/** Lets an Import take a file from $files that was not uploaded by PHP — only tests do this. */
function impTrustFiles(Import $i): Import
{
    \Closure::bind(function (): void { $this->uploadCheck = static fn (string $p): bool => true; }, $i, Import::class)();
    return $i;
}

/** The value of a hidden field of the importer's markup. */
function impHidden(string $html, string $name): ?string
{
    return preg_match('/<input type="hidden" name="' . preg_quote(htmlspecialchars($name, ENT_QUOTES), '/') . '" value="([^"]*)"/', $html, $m)
        ? html_entity_decode($m[1], ENT_QUOTES) : null;
}

/** A temporary file holding $bytes, removed at the end of the run. */
function impTmp(string $bytes): string
{
    $path = (string) tempnam(sys_get_temp_dir(), 'gk-imp');
    file_put_contents($path, $bytes);
    register_shutdown_function(static fn () => @unlink($path));
    return $path;
}

/** @return array<string,callable> */
return (static function (): array {

$root = dirname(__DIR__);
$js = static fn (): string => (string) @file_get_contents($root . '/js/import.js');
$code = static fn (): string => (string) preg_replace(['~/\*.*?\*/~s', '~(?<![:"\'\\\\])//[^\n]*~'], '', (string) @file_get_contents($root . '/js/import.js'));
$doc = static fn (): string => (string) file_get_contents($root . '/GRIDKIT_SKILL.md');
$sheet = static function () use ($root): string {
    $css = (string) file_get_contents($root . '/css/gridkit.css');
    $at = strpos($css, '/* === 1.119.0 — import === */');
    if ($at === false) return '';
    $next = strpos($css, '/* === 1.', $at + 10);
    return substr($css, $at, $next === false ? null : $next - $at);
};

return [

// ── Reading a CSV ───────────────────────────────────────────────────────

'German Excel: ; CRLF Windows-1252, umlauts, the euro sign and a dash arrive as UTF-8' => function (): void {
    Lang::set('en');
    $csv = impExcel([['Name', 'Ort', 'Betrag'], ['Müller', 'Wörgl', '€ 1.234,50'], ['Weiß', 'Graz – Süd', '12,00']]);
    $p = Import::parseCsv($csv);
    T::eq($p['error'], null, 'read');
    T::eq($p['meta']['encoding'], 'Windows-1252', 'the encoding');
    T::eq($p['meta']['delimiter'], ';', 'the separator');
    T::eq($p['meta']['lineEnding'], 'CRLF', 'the line ending');
    T::eq($p['meta']['header'], true, 'a header row');
    T::eq($p['columns'], ['Name', 'Ort', 'Betrag'], 'the columns');
    T::eq($p['rows'], [2 => ['Müller', 'Wörgl', '€ 1.234,50'], 3 => ['Weiß', 'Graz – Süd', '12,00']], 'the rows, in UTF-8, keyed by their row');
    T::eq(Import::parseCsv($csv, ['encoding' => 'ISO-8859-1'])['rows'][2][0], 'Müller', 'ISO-8859-1 is read as Windows-1252');
    // Every byte 0x80–0xFF: the WHATWG table (TextDecoder in the browser says the same).
    $all = '';
    for ($b = 0x80; $b <= 0xFF; $b++) $all .= chr($b);
    $p = Import::parseCsv("a\r\n" . $all, ['encoding' => 'Windows-1252', 'header' => true, 'delimiter' => ';']);
    $text = $p['rows'][2][0];
    T::ok(preg_match('//u', $text) === 1, 'the upper half is valid UTF-8');
    T::eq(mb_substr($text, 0, 3), "€\u{0081}‚", '0x80 is the euro sign, 0x81 stays a control');
    T::eq(mb_substr($text, 0x9F - 0x80, 2), "Ÿ\u{00A0}", '0x9F Ÿ, 0xA0 the no-break space');
    T::eq(mb_strlen($text), 128, 'one character per byte');
},

'UTF-8 with and without a byte order mark, UTF-16 LE and BE with one and LE without' => function (): void {
    $text = "Name;Stadt\nZoë;Köln\n";
    $p = Import::parseCsv("\xEF\xBB\xBF" . $text);
    T::eq([$p['meta']['encoding'], $p['meta']['bom'], $p['columns'][0]], ['UTF-8', true, 'Name'], 'the BOM is gone, not in the first name');
    $p = Import::parseCsv($text);
    T::eq([$p['meta']['encoding'], $p['meta']['bom'], $p['rows'][2]], ['UTF-8', false, ['Zoë', 'Köln']], 'UTF-8 without a BOM');
    $le = (string) iconv('UTF-8', 'UTF-16LE', $text);
    $be = (string) iconv('UTF-8', 'UTF-16BE', $text);
    $p = Import::parseCsv("\xFF\xFE" . $le);
    T::eq([$p['meta']['encoding'], $p['meta']['bom'], $p['rows'][2]], ['UTF-16LE', true, ['Zoë', 'Köln']], 'UTF-16 LE (Excel\'s "Unicode text")');
    $p = Import::parseCsv("\xFE\xFF" . $be);
    T::eq([$p['meta']['encoding'], $p['rows'][2]], ['UTF-16BE', ['Zoë', 'Köln']], 'UTF-16 BE');
    $p = Import::parseCsv($le);
    T::eq([$p['meta']['encoding'], $p['meta']['bom'], $p['rows'][2]], ['UTF-16LE', false, ['Zoë', 'Köln']], 'UTF-16 LE without a BOM, from its NUL bytes');
    // A lone surrogate and an odd last byte: one U+FFFD each place, as TextDecoder writes them.
    $p = Import::parseCsv("\xFF\xFE" . "a\x00\x00\xD8b\x00" . "c", ['header' => false]);
    T::eq($p['rows'][1][0], "a\u{FFFD}b\u{FFFD}", 'an unpaired surrogate and an odd byte');
    T::eq(Import::parseCsv("\xFF\xFE" . "a\x00\x3D\xD8\x00\xDE", ['header' => false])['rows'][1][0], 'a😀', 'a surrogate pair');
},

'a file that is not text, or not the encoding chosen, is refused in words' => function (): void {
    Lang::set('en');
    $png = "\x89PNG\r\n\x1a\n\0\0\0\rIHDR\0\0\0\x10";
    T::eq(Import::parseCsv($png)['error'], 'This is not a text file. Save the table as CSV and choose that file.', 'binary');
    T::eq(Import::parseCsv(impExcel([['Müller']]), ['encoding' => 'UTF-8'])['error'], 'The file is not valid UTF-8. Choose Windows-1252.', 'UTF-8 chosen for Windows-1252');
    // Invalid UTF-8 without a choice is Windows-1252, never an error: overlong, a lone continuation, a surrogate.
    foreach (["\xC0\xAF", "\x80", "\xED\xA0\x80", "\xF8\x88\x80\x80\x80"] as $bad) {
        $p = Import::parseCsv("a;b\r\nx" . $bad . ";y\r\n");
        T::ok($p['error'] === null && $p['meta']['encoding'] === 'Windows-1252' && preg_match('//u', $p['rows'][2][0]) === 1, 'invalid UTF-8 ' . bin2hex($bad) . ' is read as Windows-1252');
    }
    T::eq(Import::parseCsv("\xEF\xBB\xBFa;b\r\n\xFC;1\r\n")['rows'][2][0], 'ü', 'a BOM before invalid UTF-8: the rest as Windows-1252');
},

'line endings: CRLF, LF and CR give the same rows; the most frequent is reported' => function (): void {
    $want = [2 => ['1', 'a'], 3 => ['2', 'b']];
    foreach (['CRLF' => "\r\n", 'LF' => "\n", 'CR' => "\r"] as $name => $nl) {
        $p = Import::parseCsv("id;name{$nl}1;a{$nl}2;b{$nl}");
        T::eq($p['rows'], $want, "rows with $name");
        T::eq($p['meta']['lineEnding'], $name, "$name is reported");
    }
    T::eq(Import::parseCsv("id;name\r\n1;a\n2;b")['rows'], $want, 'mixed, and no line break at the end');
    T::eq(Import::parseCsv('id;name')['meta']['lineEnding'], '', 'one line: none');
},

'quotes: separators, line breaks and doubled quotes inside; the rest of a cell after the quote; a quote inside a word' => function (): void {
    $csv = "Name;Note;Size\r\n\"Huber; Max\";\"Line one\r\nline two\";\"24\"\" screen\"\r\n"
        . "O'Brien;5\" display;\"quoted\"tail\r\n\"\";\"\"\"\";x\r\n";
    $p = Import::parseCsv($csv);
    T::eq($p['meta']['enclosure'], '"', 'the double quote');
    T::eq($p['rows'][2], ['Huber; Max', "Line one\nline two", '24" screen'], 'separator, CRLF (as \n) and "" inside quotes');
    T::eq($p['rows'][3], ["O'Brien", '5" display', 'quotedtail'], 'an apostrophe and a quote inside an unquoted cell are characters; text after a closing quote stays');
    T::eq($p['rows'][4], ['', '"', 'x'], 'an empty quoted cell and a quoted quote');
    T::eq(array_keys($p['rows']), [2, 3, 4], 'a line break inside quotes is not a row: the rows of the sheet');
    $p = Import::parseCsv("'Name';'Ort'\n'Huber; Max';'Linz'\n'Gruber';'St. Pölten'\n");
    T::eq([$p['meta']['enclosure'], $p['rows'][2]], ["'", ['Huber; Max', 'Linz']], 'single quotes when the file quotes with them');
    $p = Import::parseCsv("Name;Note\r\nAnna;\"never closed\r\nBen;x\r\n");
    T::eq([$p['meta']['unclosed'], $p['rows'][2][1]], [2, "never closed\nBen;x\n"], 'a quote never closed takes the rest; its row is reported');
},

'empty lines and lines of separators are rows of the sheet but not data; trailing empty columns go' => function (): void {
    $p = Import::parseCsv("a;b;;\r\n\r\n1;2;;\r\n;;;\r\n   ;  \r\n3;4;;\r\n;;;;\r\n");
    T::eq($p['columns'], ['a', 'b'], 'the empty columns at the end are dropped');
    T::eq($p['rows'], [3 => ['1', '2'], 6 => ['3', '4']], 'empty lines are skipped, and counted');
    $p = Import::parseCsv("a;;c\r\n1;;3\r\n");
    T::eq($p['columns'], ['a', 'Column 2', 'c'], 'an empty column in the middle stays, with a name');
    $p = Import::parseCsv("a;b\r\n1;2;3\r\n4\r\n");
    T::eq([$p['columns'], $p['rows']], [['a', 'b', 'Column 3'], [2 => ['1', '2', '3'], 3 => ['4', '', '']]], 'a longer row adds a column, a shorter one is padded');
    T::eq(Import::parseCsv("\r\n\r\n")['rows'], [], 'only empty lines: no rows');
    T::eq(Import::parseCsv('')['rows'], [], 'nothing: no rows');
},

'the separator: ; , tab and | are found; a comma in the text does not fool it; sep= decides' => function (): void {
    T::eq(Import::parseCsv("Name;Adresse\nHuber;Hauptstr. 1, Wien\nGruber;Ring 2, Graz\n")['meta']['delimiter'], ';', '; with commas in the text');
    T::eq(Import::parseCsv("name,city\n\"Huber; Max\",Vienna\nGruber,Graz\n")['meta']['delimiter'], ',', ', with a ; inside quotes');
    T::eq(Import::parseCsv("a\tb\tc\n1\t2\t3\n")['meta']['delimiter'], "\t", 'tab');
    T::eq(Import::parseCsv("a|b\n1|2\n")['meta']['delimiter'], '|', 'pipe');
    T::eq(Import::parseCsv("a;b,c\n1;2,3\n4;5,6\n")['meta']['delimiter'], ';', 'a tie goes to ;');
    T::eq(Import::parseCsv("a,b,c;d\n1,2,3;4\n")['meta']['delimiter'], ',', 'between two that split every line alike, the one with more cells');
    $p = Import::parseCsv("Note\nA; B\nC\nD\n");
    T::eq([$p['meta']['delimiter'], $p['rows'][2]], [',', ['A; B']], 'none splits the lines: the one that occurs least (here not ;)');
    $p = Import::parseCsv("Firma\nHuber GmbH\nMeier, Müller & Co\nGruber KG\n");
    T::eq([$p['meta']['delimiter'], $p['rows'][3]], [';', ['Meier, Müller & Co']], 'one column with a comma in one value stays one column');
    $p = Import::parseCsv("sep=,\r\na;b,c\r\n1;2,3\r\n");
    T::eq([$p['meta']['sep'], $p['meta']['delimiter'], $p['columns'], array_keys($p['rows'])], [true, ',', ['a;b', 'c'], [2]], 'Excel\'s sep= line names it and is not a row');
    T::eq(Import::parseCsv("a;b\n1;2", ['delimiter' => 'tab'])['meta']['delimiter'], "\t", 'a delimiter given wins');
},

'the header row: a cell that names a field, or no data in the first row' => function (): void {
    $names = ['email', 'E-mail', 'Mail'];
    T::eq(Import::parseCsv("Mail;x\na@b.at;1\n", ['names' => $names])['meta']['header'], true, 'a cell names a field');
    $p = Import::parseCsv("anna@example.at;Anna\nben@example.at;Ben\n", ['names' => $names]);
    T::eq([$p['meta']['header'], $p['columns'], array_keys($p['rows'])], [false, ['Column 1', 'Column 2'], [1, 2]], 'an address in the first row: data, from row 1');
    T::eq(Import::parseCsv("12;Anna\n13;Ben\n")['meta']['header'], false, 'a number in the first row');
    T::eq(Import::parseCsv("31.12.2026;x\n")['meta']['header'], false, 'a date in the first row');
    T::eq(Import::parseCsv("Produkt;Farbe\nTisch;rot\n")['meta']['header'], true, 'words only: a header');
    T::eq(Import::parseCsv("Produkt;Farbe\nTisch;rot\n", ['header' => false])['meta']['header'], false, 'header(false) wins');
    T::eq(Import::parseCsv("12;13\n1;2\n", ['header' => true])['columns'], ['12', '13'], 'header(true) wins');
},

'limits: rows and bytes refused with a sentence, never cut; exactly the limit passes; 200 columns' => function (): void {
    Lang::set('en');
    $csv = "n\n" . implode("\n", range(1, 50)) . "\n";
    T::eq(count(Import::parseCsv($csv, ['maxRows' => 50])['rows']), 50, '50 rows with a limit of 50');
    $p = Import::parseCsv($csv, ['maxRows' => 49]);
    T::eq([$p['error'], $p['rows']], ['The file has more than 49 rows. Import it in parts of at most 49 rows.', []], '51 is refused, and nothing is returned');
    T::eq(count(Import::parseCsv("1\n2\n3\n", ['maxRows' => 3, 'header' => false])['rows']), 3, 'without a header the first row counts');
    T::eq(Import::parseCsv(str_repeat('x', 2049), ['maxBytes' => 2048])['error'], 'The file is 2 KB; at most 2 KB can be imported.', 'bytes');
    T::eq(Import::parseCsv(str_repeat('x', 5 * 1048576 + 1))['error'], 'The file is 5 MB; at most 5 MB can be imported.', 'the default 5 MB');
    $wide = implode(';', range(1, 200)) . "\n";
    T::eq(count(Import::parseCsv($wide)['columns']), 200, '200 columns');
    T::eq(Import::parseCsv($wide . implode(';', range(1, 201)))['error'], 'A row of the file has more than 200 columns.', '201 columns');
    Lang::set('de');
    T::eq(Import::parseCsv(str_repeat("1\n", 12), ['maxRows' => 10])['error'], 'Die Datei hat mehr als 10 Zeilen. Bitte in Teilen von höchstens 10 Zeilen importieren.', 'in German');
    Lang::set('en');
},

'hostile files are read quickly and refused or read, never hanging: a megabyte of separators, of line breaks, of quotes' => function (): void {
    $t = microtime(true);
    T::eq(Import::parseCsv(str_repeat(';', 1048576))['error'], 'A row of the file has more than 200 columns.', 'a line of separators');
    T::eq(Import::parseCsv(str_repeat("\n", 1048576))['rows'], [], 'a megabyte of empty lines');
    $q = Import::parseCsv('"' . str_repeat('"', 1048575));
    T::ok($q['error'] === null, 'a megabyte of quotes');
    T::eq(Import::parseCsv(str_repeat("a\n", 524288), ['maxRows' => 10000])['error'] !== null, true, 'half a million rows: refused');
    T::ok(microtime(true) - $t < 6, 'in a few seconds: ' . round(microtime(true) - $t, 2) . ' s');
    // 10,000 rows of 10 columns, the default limit, in reasonable time.
    $big = "a;b;c;d;e;f;g;h;i;j\n" . str_repeat("1;Müller;x@y.at;1.234,50;31.12.2026;ja;AT;\"q;q\";z;w\n", 10000);
    $t = microtime(true);
    $r = impCustomers()->fromString($big)->result();
    T::eq($r['total'], 10000, 'ten thousand rows checked');
    T::ok(microtime(true) - $t < 5, 'in ' . round(microtime(true) - $t, 2) . ' s');
},

// ── Matching and checking ───────────────────────────────────────────────

'columns are matched by key, label and alias — case, umlauts, signs and spaces do not count' => function (): void {
    $i = (new Import('m'))
        ->field('email', 'E-mail')
        ->field('street', 'Straße')
        ->field('last', 'Last name', ['aliases' => ['Müller-Name']])
        ->field('zip', 'ZIP', ['aliases' => ['PLZ']])
        ->field('note', 'Note');
    $r = $i->fromString("E MAIL;strasse;Mueller Name;plz;Zip;Other\na@b.at;x;y;1;2;3\n")->result();
    T::eq($r['mapping'], ['email', 'street', 'last', null, 'zip', null], 'E MAIL, strasse, Mueller Name; the label "Zip" beats the alias "plz" left of it; an unknown column is ignored');
    $r = (new Import('m'))->field('last', 'Last name', ['aliases' => ['Müller']])->fromString("MULLER\nx\n")->result();
    T::eq($r['mapping'], ['last'], 'ü matches u as well as ue');
    $r = (new Import('m'))->field('email', 'E-mail', ['aliases' => ['Mail']])->fromString("Mail;E-Mail\na@b.at;c@d.at\n")->result();
    T::eq($r['mapping'], [null, 'email'], 'the label beats an alias in a column further left');
    $r = (new Import('m'))->field('email', 'E-mail')->fromString("x@y.at;1\n")->result();
    T::eq($r['mapping'], [null, null], 'no header row: nothing is matched by name');
},

'map(): by name and index, unknown fields warn, unnamed columns are ignored' => function (): void {
    [$r, $notes] = impQuiet(fn () => impCustomers()->map(['E-Mail' => 'email', 0 => 'no', 2 => 'last', 'Land' => 'nope'])
        ->fromString("Kundennummer;Vorname;Nachname;E-Mail;Land\nK1;Anna;Huber;a@b.at;AT\n")->result());
    T::eq($r['mapping'], ['no', null, 'last', 'email', null], 'what map() names, nothing else');
    T::eq(count($notes), 1, 'one warning');
    T::contains($notes[0] ?? '', "'nope' is no field", 'it names the field');
},

'every type: what a cell may hold and what result() hands over' => function (): void {
    Lang::set('en');
    $i = (new Import('t'))
        ->field('txt', 'Text')->field('mail', 'Mail', ['type' => 'email'])->field('num', 'Num', ['type' => 'number'])
        ->field('eur', 'Eur', ['type' => 'money'])->field('day', 'Day', ['type' => 'date'])->field('yes', 'Yes', ['type' => 'bool'])
        ->field('land', 'Land', ['type' => 'select', 'options' => ['AT' => 'Österreich', 'DE' => 'Deutschland']])
        ->locale('de');
    $check = function (string $field, array $cases) use ($i): void {
        foreach ($cases as [$in, $want, $error]) {
            // A column no field fills keeps the row from being an empty line.
            $csv = "txt;mail;num;eur;day;yes;land;filler\n";
            $cells = array_fill_keys(['txt', 'mail', 'num', 'eur', 'day', 'yes', 'land', 'filler'], '');
            $cells['filler'] = 'x';
            $cells[$field] = $in;
            $csv .= implode(';', array_map(static fn (string $c): string => '"' . str_replace('"', '""', $c) . '"', $cells)) . "\n";
            $r = (clone $i)->fromString($csv)->result();
            if ($error === null) {
                T::eq(array_key_exists($field, $r['rows'][2] ?? []) ? $r['rows'][2][$field] : 'MISSING', $want, "$field " . var_export($in, true));
            } else {
                T::eq($r['errors'][2][$field] ?? 'NO ERROR', $error, "$field " . var_export($in, true) . ' is refused');
            }
        }
    };
    $check('txt', [['  Anna  ', 'Anna', null], ["\u{00A0}Ben\u{00A0}", 'Ben', null], ['', null, null], ["a\nb", "a\nb", null]]);
    $check('mail', [['anna@example.at', 'anna@example.at', null], ['Max.Huber+news@Example.CO.AT', 'Max.Huber+news@example.co.at', null],
        ['a@xn--mller-kva.at', 'a@xn--mller-kva.at', null], ['max@', null, 'Not a valid e-mail address.'], ['max@localhost', null, 'Not a valid e-mail address.'],
        ['a@b.c', null, 'Not a valid e-mail address.'], ['a..b@c.at', null, 'Not a valid e-mail address.'], ['.a@c.at', null, 'Not a valid e-mail address.'],
        ['a b@c.at', null, 'Not a valid e-mail address.'], ['müller@x.at', null, 'Not a valid e-mail address.'], ['a@-b.at', null, 'Not a valid e-mail address.'],
        ['Anna <a@b.at>', null, 'Not a valid e-mail address.'], [str_repeat('a', 65) . '@b.at', null, 'Not a valid e-mail address.']]);
    $check('num', [['1.234,5', '1234.5', null], ['1,234.5', '1234.5', null], ['1.500', '1500', null], ['1 234,5', '1234.5', null], ['-12', '-12', null],
        ['0,000', '0', null], ['1e5', null, 'Enter a number, such as 1.234,5.'], ['0x10', null, 'Enter a number, such as 1.234,5.'],
        ['١٢', null, 'Enter a number, such as 1.234,5.'], ['1.2.3,4', null, 'Enter a number, such as 1.234,5.'], ['12 Stück', null, 'Enter a number, such as 1.234,5.']]);
    $check('eur', [['1.234,50', '1234.50', null], ['€ 12', '12.00', null], ['-0,5', '-0.50', null], ['EUR 3,1', '3.10', null], ['1.005', '1005.00', null],
        ['1,005', null, 'At most 2 decimal places.'],
        ['1,0055', null, 'At most 2 decimal places.'], ['zwölf', null, 'Enter an amount, such as 1.234,50.']]);
    $check('day', [['31.12.2026', '2026-12-31', null], ['1.2.2026', '2026-02-01', null], ['2026-12-31', '2026-12-31', null], ['2026-2-1', '2026-02-01', null],
        ['14/02/2001', '2001-02-14', null], ['29.02.2024', '2024-02-29', null], ['29.02.2026', null, 'Enter a date such as 31.12.2026 or 2026-12-31.'],
        ['31.04.2026', null, 'Enter a date such as 31.12.2026 or 2026-12-31.'], ['31.12.26', null, 'Enter a date such as 31.12.2026 or 2026-12-31.'],
        ['31.12.2026 14:30', null, 'Enter a date such as 31.12.2026 or 2026-12-31.'], ['00.01.2026', null, 'Enter a date such as 31.12.2026 or 2026-12-31.'],
        ['12/31/2026', null, 'Enter a date such as 31.12.2026 or 2026-12-31.'], ['0000-01-01', null, 'Enter a date such as 31.12.2026 or 2026-12-31.']]);
    $check('yes', [['ja', true, null], ['JA', true, null], ['x', true, null], ['1', true, null], ['yes', true, null], ['Wahr', true, null],
        ['nein', false, null], ['0', false, null], ['no', false, null], ['', false, null], ['vielleicht', null, 'Enter yes or no (ja/nein, 1/0, x).']]);
    $check('land', [['AT', 'AT', null], ['at', 'AT', null], ['Österreich', 'AT', null], ['oesterreich', 'AT', null], ['deutschland', 'DE', null],
        ['Atlantis', null, 'Not one of: Österreich, Deutschland.']]);
    $r = (new Import('t'))->field('n', 'N', ['type' => 'number'])->locale('en')->fromString("n\n1.500\n1,500\n")->result();
    T::eq([$r['rows'][2]['n'], $r['rows'][3]['n']], ['1.5', '1500'], 'English reads 1.500 as one and a half');
    $long = (new Import('t'))->field('s', 'S', ['type' => 'select', 'options' => range(1, 10)])->fromString("s\n11\n")->result();
    T::eq($long['errors'][2]['s'], 'Not one of: 1, 2, 3, 4, 5, 6, 7, 8, ….', 'a long list is cut after eight');
},

'required, unique and the counts: the first of two equal values stays valid, case does not count' => function (): void {
    Lang::set('en');
    $csv = "Nr;Nachname;E-Mail\nK1;Huber;anna@example.at\nk1;Gruber;ANNA@Example.at\nK3;;ben@example.at\nK4;Moser;\nK1;Bauer;cleo@example.at\n";
    $r = impCustomers()->fromString($csv)->result();
    T::eq([$r['total'], $r['valid'], $r['invalid']], [5, 1, 4], 'counts');
    T::eq(array_keys($r['rows']), [2], 'only row 2 passes');
    T::eq($r['errors'], [
        3 => ['no' => 'Already in row 2.', 'email' => 'Already in row 2.'],
        4 => ['last' => 'Last name is required.'],
        5 => ['email' => 'E-mail is required.'],
        6 => ['no' => 'Already in row 2.'],
    ], 'every message, in the order of the fields');
    T::eq($r['rows'][2], ['no' => 'K1', 'last' => 'Huber', 'email' => 'anna@example.at'], 'only mapped fields are in a row');
    $r = impCustomers()->fromString("Vorname;Land\nAnna;AT\n")->result();
    T::eq($r['missing'], ['no', 'last', 'email'], 'required fields no column fills');
    T::eq([$r['valid'], $r['errors'][2]], [0, ['no' => 'Customer no. is required.', 'last' => 'Last name is required.', 'email' => 'E-mail is required.']], 'and no row can pass');
},

'the sample of the demo: German Excel with five mistakes' => function (): void {
    Lang::set('en');
    $r = impCustomers()->fromString((string) file_get_contents(dirname(__DIR__) . '/demo/media/kunden.csv'), 'kunden.csv')->result();
    T::eq([$r['meta']['encoding'], $r['meta']['delimiter'], $r['meta']['lineEnding']], ['Windows-1252', ';', 'CRLF'], 'read as Excel wrote it');
    T::eq([$r['total'], $r['valid'], $r['invalid']], [20, 15, 5], 'twenty rows, five with errors');
    T::eq(array_keys($r['errors']), [8, 9, 10, 11, 15], 'the rows of the sheet');
    T::eq($r['mapping'], ['no', 'first', 'last', 'email', 'country', 'born', 'revenue', 'news', null], 'Notiz is ignored');
    T::eq($r['rows'][3], ['no' => 'K-1002', 'first' => 'Jürgen', 'last' => 'Weiß', 'email' => 'juergen.weiss@example.de', 'country' => 'DE',
        'born' => '1979-07-01', 'revenue' => '980.00', 'news' => false], 'a row, typed');
    T::eq($r['rows'][21]['email'], 'LEA.REITER@example.at', 'the domain in lower case');
},

// ── Rendering ───────────────────────────────────────────────────────────

'step 1: a form of its own, named, a labelled file field, the limits in words, a box to paste into' => function (): void {
    Lang::set('en');
    $html = impCustomers()->label('Import customers')->action('/admin/import?x=1&y=2')->hidden(['_token' => 'a"b<c'])->html();
    T::contains($html, '<form class="gk-import" id="gk-import-c" method="post" enctype="multipart/form-data" action="/admin/import?x=1&amp;y=2" aria-label="Import customers" data-gk-import="c" data-gk-import-step="upload">', 'the form');
    T::contains($html, '<input type="hidden" name="_token" value="a&quot;b&lt;c">', 'the hidden field, escaped');
    T::contains($html, '<li class="gk-steps-item gk-steps-current" aria-current="step">File</li>', 'the first step is the current one');
    T::contains($html, '<label class="gk-import-drop-text" for="gk-import-c-file">Choose a CSV file, or drop it here.</label>', 'the file field has a label');
    T::contains($html, 'name="c[file]" accept=".csv,.txt,.tsv,text/csv,text/plain,text/tab-separated-values" aria-describedby="gk-import-c-hint"', 'what it accepts, described by the hint');
    T::contains($html, 'Up to 5 MB and 10,000 rows.', 'the limits');
    T::contains($html, '<details class="gk-disclosure gk-import-paste"><summary>Or paste the text</summary><label class="gk-label-text" for="gk-import-c-text">CSV text</label>', 'the box to paste into');
    T::contains($html, '<button type="submit" class="gk-btn gk-btn-filled gk-btn-primary" name="c[step]" value="upload">', 'the button posts the step');
    T::notContains($html, 'gk-import-panel', 'no panel yet');
    $cfg = json_decode((string) preg_replace('/^.*<script type="application\/json" data-gk-import-config>(.*?)<\/script>.*$/s', '$1', $html), true);
    T::eq([$cfg['name'], count($cfg['fields']), $cfg['locale'], $cfg['maxRows'], $cfg['t']['err_email']], ['c', 8, 'de', 10000, 'Not a valid e-mail address.'], 'the config the script reads');
    T::eq($cfg['fields'][4]['options'], [['AT', 'Austria'], ['DE', 'Germany'], ['CH', 'Switzerland']], 'options as pairs, in order');
    $hostile = (new Import('h'))->field('a', '</script><script>alert(1)</script>', ['aliases' => ['</script>']])->html();
    T::eq(substr_count($hostile, '</script>'), 1, 'a label cannot close the config');
},

'step 2: how it was read, a select per column, the summary, the preview with every error in words, the list, the buttons' => function (): void {
    Lang::set('en');
    $csv = impExcel([['Kundennummer', 'Nachname', 'E-Mail', 'Land', 'Notiz'], ['K1', 'Huber', 'anna@example.at', 'AT', ''],
        ['K2', 'Gruber', 'max@', 'Atlantis', 'Rückruf'], ['K3', '', 'cleo@example.at', 'DE', '']]);
    $html = impCustomers()->storage(false)->fromString($csv, 'kunden.csv')->html();
    T::contains($html, 'data-gk-import-step="map"', 'the step');
    T::contains($html, '<strong>kunden.csv</strong> · ' . strlen($csv) . ' bytes · 3 rows</p>', 'the file in one line');
    T::contains($html, '<option value=";" selected>Semicolon ;</option>', 'the separator found');
    T::contains($html, '<option value="Windows-1252" selected>Windows-1252 (Excel, ISO-8859-1)</option>', 'the encoding found');
    T::contains($html, '<input type="hidden" name="c[header]" value="0"><label class="gk-checkbox-wrap gk-import-option"><input type="checkbox" name="c[header]" value="1" checked>', 'the header box posts 0 or 1');
    T::contains($html, '<label class="gk-import-col-name" for="gk-import-c-map-2"><span class="gk-sr-only">Column </span>E-Mail</label>', 'a column is named for a screen reader');
    T::contains($html, '<option value="email" selected>E-mail (required)</option>', 'its field chosen, required marked');
    T::contains($html, '<div class="gk-import-col gk-import-ignored"><label class="gk-import-col-name" for="gk-import-c-map-4">', 'Notiz is ignored');
    T::contains($html, '<span class="gk-import-col-sample" id="gk-import-c-sample-4">e.g. Rückruf</span>', 'its sample');
    T::contains($html, '<div class="gk-message gk-message-warning gk-message-sm gk-import-summary" role="status"><span class="material-icons" aria-hidden="true">error</span><div class="gk-message-content">3 rows, 2 with errors.</div></div>', 'the summary, a status');
    T::contains($html, '<caption class="gk-sr-only" id="gk-import-c-caption">Preview of every row</caption>', 'the preview has a caption');
    T::contains($html, '<div class="gk-table-wrap gk-import-preview" role="region" tabindex="0" aria-labelledby="gk-import-c-caption">', 'a named region with a tab stop');
    T::contains($html, '<th scope="col">E-Mail<span class="gk-import-target">→ E-mail</span></th>', 'a column head says its field');
    T::contains($html, '<th scope="col" class="gk-import-ignored">Notiz<span class="gk-import-target">ignored</span></th>', 'and an ignored one says so');
    T::contains($html, '<tr class="gk-import-badrow"><th scope="row" class="gk-import-rowno">3</th><td class="gk-import-state"><span class="material-icons" aria-hidden="true">error</span>2 errors</td>', 'a row with errors says how many');
    T::contains($html, '<td class="gk-import-bad">max@<span class="gk-import-msg"><span class="material-icons" aria-hidden="true">error</span>Not a valid e-mail address.</span></td>', 'the message under the cell');
    T::contains($html, '<th scope="row" class="gk-import-rowno">2</th><td class="gk-import-state"><span class="material-icons" aria-hidden="true">check_circle</span>OK</td>', 'a valid row says OK');
    T::contains($html, '<li><strong>Row 3</strong> · E-mail: Not a valid e-mail address. <q>max@</q></li>', 'the list names row, field, message and value');
    T::contains($html, '<li><strong>Row 4</strong> · Last name: Last name is required.</li>', 'a required field');
    T::contains($html, 'name="c[step]" value="confirm"><span class="material-icons" style="font-size:18px" aria-hidden="true">download_done</span><span>Import the 1 valid row</span>', 'the import button counts');
    T::contains($html, '<span class="gk-import-skip">Rows with errors are left out.</span>', 'and says what happens to the rest');
    T::contains($html, 'value="map"><span class="material-icons" style="font-size:18px" aria-hidden="true">refresh</span><span>Check again</span>', 'check again');
    T::contains($html, 'value="cancel" formnovalidate><span>Cancel</span>', 'cancel');
    T::eq(impHidden($html, 'c[data]'), base64_encode($csv), 'storage(false): the file travels in the form');
    T::eq(impHidden($html, 'c[cols]'), hash('fnv1a32', "5\x1eKundennummer\x1fNachname\x1fE-Mail\x1fLand\x1fNotiz"), 'the signature of the columns');
},

'step 2 when nothing can be imported: the missing fields named, the button off, no list' => function (): void {
    Lang::set('en');
    $html = impCustomers()->storage(false)->fromString("Vorname\nAnna\n")->html();
    T::contains($html, '<div class="gk-message gk-message-error gk-message-sm gk-import-missing"><span class="material-icons" aria-hidden="true">error</span><div class="gk-message-content">Required, but no column fills it: Customer no., Last name, E-mail</div></div>', 'the fields named');
    T::ok((bool) preg_match('/value="confirm" disabled>/', $html), 'the import button is off');
    T::notContains($html, 'gk-import-errors', 'no list of rows: every row lacks the same');
    $html = impCustomers()->storage(false)->fromString("Kundennummer;Nachname;E-Mail\n")->html();
    T::contains($html, 'The file holds no rows.', 'a file with only a header');
},

'the preview shows the first rows, the list every row with errors, the first 100 and how many more' => function (): void {
    Lang::set('en');
    $csv = "Nr;Nachname;E-Mail\n";
    for ($n = 1; $n <= 130; $n++) $csv .= "K$n;Huber;bad$n\n";
    $html = impCustomers()->storage(false)->preview(5)->fromString($csv)->html();
    T::eq(substr_count($html, 'class="gk-import-rowno"'), 5, 'five rows in the preview');
    T::contains($html, 'Preview of the first 5 rows', 'the caption says so');
    T::eq(substr_count($html, '<li><strong>Row '), 100, 'a hundred in the list');
    T::contains($html, '<p class="gk-import-more">And 30 more rows with errors.</p>', 'and the rest counted');
    T::contains($html, 'Rows with errors (130)', 'the title counts all');
},

'everything from the file and the page is escaped: names, cells, file name, labels, options' => function (): void {
    Lang::set('en');
    $x = '<img src=x onerror=alert(1)>';
    $csv = "\"$x\";Nachname\n\"$x\";\"$x\"\n";
    $html = (new Import('e'))->field('last', "Last $x", ['aliases' => ['Nachname']])
        ->field('s', 'S', ['type' => 'select', 'options' => ['a' => $x]])->storage(false)->fromString($csv, "$x.csv")->html();
    T::notContains($html, '<img', 'no element from the data');
    T::contains($html, '&lt;img src=x onerror=alert(1)&gt;', 'shown as text');
    $html = (new Import('e'))->field('a', 'A')->storage(false)->fromString("a\nx\n", "../../etc/pass\x01wd")->html();
    T::contains($html, '<strong>passwd</strong>', 'a file name is its last part, without control characters');
},

'in German' => function (): void {
    Lang::set('de');
    $html = impCustomers()->storage(false)->fromString(impExcel([['Kundennummer', 'Nachname', 'E-Mail'], ['K1', 'Huber', 'x']]))->html();
    T::contains($html, '1 Zeile, 1 mit Fehlern.', 'the summary');
    T::contains($html, 'Keine gültige E-Mail-Adresse.', 'a message');
    T::contains($html, 'aria-label="CSV-Import"', 'the name');
    $up = impCustomers()->limits(12000, 2621440)->html();
    T::contains($html . $up, 'Bis 2,5 MB und 12.000 Zeilen.', 'the limits in the German shape');
    Lang::set('en');
},

// ── The flow ────────────────────────────────────────────────────────────

'upload → map → confirm with the file kept under a token; the token file is the server\'s alone and goes after the import' => function (): void {
    Lang::set('en');
    $dir = impDir();
    $csv = impExcel([['Kundennummer', 'Nachname', 'E-Mail'], ['K1', 'Huber', 'anna@example.at'], ['K2', 'Gruber', 'max@']]);
    $i = impCustomers()->storage($dir)->handle(['c' => ['step' => 'upload', 'text' => '']], []);
    T::eq([$i->step(), $i->result()['error']], ['upload', 'Choose a CSV file or paste its text.'], 'nothing chosen');
    $i = impTrustFiles(impCustomers()->storage($dir))->handle(['c' => ['step' => 'upload']], ['c' => [
        'name' => ['file' => 'kunden.csv'], 'type' => ['file' => 'text/csv'], 'tmp_name' => ['file' => impTmp($csv)],
        'error' => ['file' => UPLOAD_ERR_OK], 'size' => ['file' => strlen($csv)]]]);
    T::eq($i->step(), 'map', 'an upload moves on');
    $html = $i->html();
    $token = impHidden($html, 'c[token]');
    T::ok(is_string($token) && preg_match('/^[0-9a-f]{32}$/', $token) === 1, 'a token of 128 bits');
    T::eq(impHidden($html, 'c[data]'), null, 'and not the file');
    $file = "$dir/gk-import-$token.csv";
    T::ok(is_file($file), 'the file waits');
    T::eq(fileperms($file) & 0777, 0600, 'readable by the server user alone');
    $cols = (string) impHidden($html, 'c[cols]');
    // Step 2 → check again with a mapping of the person's own.
    $j = impCustomers()->storage($dir)->handle(['c' => ['step' => 'map', 'token' => $token, 'cols' => $cols, 'delimiter' => ';', 'encoding' => 'Windows-1252', 'header' => '1',
        'map' => [0 => 'no', 1 => 'last', 2 => '']]], []);
    T::eq([$j->step(), $j->result()['mapping'], $j->result()['missing']], ['map', ['no', 'last', null], ['email']], 'the posted mapping, checked again');
    // Confirm: read again, checked again, the file is gone.
    $k = impCustomers()->storage($dir)->handle(['c' => ['step' => 'confirm', 'token' => $token, 'cols' => $cols,
        'map' => [0 => 'no', 1 => 'last', 2 => 'email']]], []);
    T::eq($k->step(), 'done', 'confirmed');
    T::eq($k->result()['rows'], [2 => ['no' => 'K1', 'last' => 'Huber', 'email' => 'anna@example.at']], 'the valid row');
    T::ok(!is_file($file), 'the kept file is removed');
    T::contains($k->html(), '1 row imported; 1 with errors left out.', 'done, in words');
    T::contains($k->html(), 'value="new"', 'another file');
    $again = impCustomers()->storage($dir)->handle(['c' => ['step' => 'confirm', 'token' => $token]], []);
    T::eq([$again->step(), $again->result()['error']], ['upload', 'The file is no longer here (a file is kept for 60 minutes). Choose it again.'], 'the token is used up');
    impRmDir($dir);
},

'confirm stays in step 2 while a required field has no column or no row is valid' => function (): void {
    $dir = impDir();
    $i = impCustomers()->storage($dir)->handle(['c' => ['step' => 'upload', 'text' => "Nr;Nachname\nK1;Huber\n"]], []);
    $token = (string) impHidden($i->html(), 'c[token]');
    $j = impCustomers()->storage($dir)->handle(['c' => ['step' => 'confirm', 'token' => $token]], []);
    T::eq([$j->step(), $j->result()['missing']], ['map', ['email']], 'no import without the e-mail column');
    T::ok(is_file("$dir/gk-import-$token.csv"), 'the file still waits');
    impRmDir($dir);
},

'a mapping posted for other columns is not applied: the columns changed, the match starts again' => function (): void {
    $dir = impDir();
    $i = impCustomers()->storage($dir)->handle(['c' => ['step' => 'upload', 'text' => "Nr,Nachname;E-Mail\nK1,Huber;a@b.at\n"]], []);
    $html = $i->html();
    $token = (string) impHidden($html, 'c[token]');
    T::eq($i->result()['columns'], ['Nr,Nachname', 'E-Mail'], 'read with ;');
    $j = impCustomers()->storage($dir)->handle(['c' => ['step' => 'map', 'token' => $token, 'cols' => impHidden($html, 'c[cols]'),
        'delimiter' => ',', 'map' => [0 => '', 1 => 'no']]], []);
    T::eq($j->result()['columns'], ['Nr', 'Nachname;E-Mail'], 'read again with ,');
    T::eq($j->result()['mapping'], ['no', null], 'matched by name, not by the posted mapping');
    impRmDir($dir);
},

'the posted mapping is checked: unknown keys are ignored, a field twice keeps the first, hostile shapes are ignored' => function (): void {
    Lang::set('en');
    $csv = "Nr;Nachname;E-Mail\nK1;Huber;a@b.at\n";
    $sig = hash('fnv1a32', "3\x1eNr\x1fNachname\x1fE-Mail");
    [$i, $notes] = impQuiet(fn () => impCustomers()->storage(false)->handle(['c' => ['step' => 'map', 'data' => base64_encode($csv), 'cols' => $sig,
        'map' => [0 => 'no', 1 => 'no', 2 => '<script>', 99999 => 'email', 'x' => 'last']]], []));
    T::eq($i->result()['mapping'], ['no', null, null], 'the second "no" and an unknown key are ignored');
    T::eq($i->result()['notes'], ['Customer no. was chosen for two columns; only the first (Nr) is used.'], 'and said');
    T::eq($notes, [], 'without a warning');
    foreach ([['c' => 'x'], ['c' => ['step' => ['upload']]], ['c' => ['step' => 'upload', 'text' => ['a']]], ['c' => ['step' => 'map', 'map' => 'x', 'token' => ['a']]],
              ['c' => ['step' => 'confirm', 'data' => ['x'], 'delimiter' => ['x'], 'encoding' => 'EBCDIC', 'header' => 'maybe']], ['c' => ['step' => 'drop table']]] as $post) {
        [$r, $warn] = impQuiet(fn () => impCustomers()->storage(false)->handle($post, ['c' => 'nope'])->html());
        T::ok(is_string($r) && $warn === [], 'a hostile POST is read without a warning: ' . json_encode($post));
    }
},

'tokens cannot reach outside the directory, nor another session\'s file, nor an old one' => function (): void {
    Lang::set('en');
    $dir = impDir();
    file_put_contents("$dir/secret.txt", "{\"name\":\"x\"}\nSECRET;DATA\n1;2\n");
    // A file named like a kept one, but not by a token: only 32 hexadecimal digits are a token.
    file_put_contents("$dir/gk-import-planted.csv", "{\"name\":\"x\"}\nSECRET;DATA\n1;2\n");
    T::eq(impCustomers()->storage($dir)->handle(['c' => ['step' => 'map', 'token' => 'planted']], [])->step(), 'upload', 'a token is 32 hexadecimal digits, nothing else');
    foreach (['../secret', '../../etc/passwd', str_repeat('a', 31) . '/', strtoupper(str_repeat('a', 32)), "secret.txt\0"] as $bad) {
        $i = impCustomers()->storage($dir)->handle(['c' => ['step' => 'map', 'token' => $bad]], []);
        T::eq([$i->step(), $i->result()['columns']], ['upload', []], 'refused: ' . json_encode($bad));
    }
    // An old file is not read, and is removed when an importer is handled.
    $i = impCustomers()->storage($dir, 60)->handle(['c' => ['step' => 'upload', 'text' => "Nr\nK1\n"]], []);
    $token = (string) impHidden($i->html(), 'c[token]');
    touch("$dir/gk-import-$token.csv", time() - 3600);
    $j = impCustomers()->storage($dir, 60)->handle(['c' => ['step' => 'map', 'token' => $token]], []);
    T::eq([$j->step(), $j->result()['error']], ['upload', 'The file is no longer here (a file is kept for 1 minutes). Choose it again.'], 'an expired file');
    T::ok(!is_file("$dir/gk-import-$token.csv"), 'and it is gone');
    T::ok(is_file("$dir/secret.txt"), 'what is not the importer\'s stays');
    // With a session, a token belongs to it.
    @session_start();
    $_SESSION = [];
    $i = impCustomers()->storage($dir)->handle(['c' => ['step' => 'upload', 'text' => "Nr\nK1\n"]], []);
    $token = (string) impHidden($i->html(), 'c[token]');
    T::eq($_SESSION['gk_import'] ?? null, [$token], 'the session knows its token');
    $_SESSION['gk_import'] = ['ffffffffffffffffffffffffffffffff'];
    $j = impCustomers()->storage($dir)->handle(['c' => ['step' => 'map', 'token' => $token]], []);
    T::eq($j->step(), 'upload', 'another session cannot use it');
    $j = impCustomers()->storage($dir)->handle(['c' => ['step' => 'cancel', 'token' => $token]], []);
    T::ok(is_file("$dir/gk-import-$token.csv"), 'nor cancel it');
    $_SESSION['gk_import'] = [$token];
    T::eq(impCustomers()->storage($dir)->handle(['c' => ['step' => 'map', 'token' => $token]], [])->step(), 'map', 'its own session can');
    session_write_close();
    $_SESSION = [];
    impRmDir($dir);
},

'cancel forgets the file and says so; a new file replaces the kept one' => function (): void {
    Lang::set('en');
    $dir = impDir();
    $i = impCustomers()->storage($dir)->handle(['c' => ['step' => 'upload', 'text' => "Nr\nK1\n"]], []);
    $token = (string) impHidden($i->html(), 'c[token]');
    $j = impCustomers()->storage($dir)->handle(['c' => ['step' => 'cancel', 'token' => $token]], []);
    T::eq($j->step(), 'upload', 'back to the start');
    T::ok(!is_file("$dir/gk-import-$token.csv"), 'the file is gone');
    T::contains($j->html(), '<div class="gk-message gk-message-info gk-message-sm" role="status"><span class="material-icons" aria-hidden="true">info</span><div class="gk-message-content">The import was cancelled. Nothing was imported.</div></div>', 'and said');
    $i = impCustomers()->storage($dir)->handle(['c' => ['step' => 'upload', 'text' => "Nr\nK1\n"]], []);
    $token = (string) impHidden($i->html(), 'c[token]');
    impCustomers()->storage($dir)->handle(['c' => ['step' => 'map', 'token' => $token, 'text' => "Nr\nK2\n"]], [])->html();
    T::ok(!is_file("$dir/gk-import-$token.csv"), 'text sent with a token replaces the kept file');
    impRmDir($dir);
},

'storage(false): the file travels in the form, every step reads it again; a broken or oversized one is refused' => function (): void {
    Lang::set('en');
    $csv = "Nr;Nachname;E-Mail\nK1;Huber;a@b.at\nK2;Gruber;x\n";
    $dirBefore = glob(sys_get_temp_dir() . '/gridkit-import/*') ?: [];
    $i = impCustomers()->storage(false)->handle(['c' => ['step' => 'upload', 'text' => $csv]], []);
    $html = $i->html();
    T::eq(base64_decode((string) impHidden($html, 'c[data]')), $csv, 'the file in the form');
    T::eq(impHidden($html, 'c[token]'), null, 'no token');
    T::eq(glob(sys_get_temp_dir() . '/gridkit-import/*') ?: [], $dirBefore, 'nothing written on the server');
    // A forged form is another file: read and checked like one.
    $forged = "Nr;Nachname;E-Mail\nK9;<b>;evil@\n";
    $k = impCustomers()->storage(false)->handle(['c' => ['step' => 'confirm', 'data' => base64_encode($forged)]], []);
    T::eq([$k->step(), $k->result()['valid']], ['map', 0], 'checked again: nothing valid, no import');
    $k = impCustomers()->storage(false)->handle(['c' => ['step' => 'confirm', 'data' => base64_encode($csv)]], []);
    T::eq([$k->step(), array_keys($k->result()['rows'])], ['done', [2]], 'the valid row only');
    T::eq(impCustomers()->storage(false)->handle(['c' => ['step' => 'map', 'data' => '!!not base64!!']], [])->step(), 'upload', 'broken data');
    $big = impCustomers()->storage(false)->limits(10, 100)->handle(['c' => ['step' => 'map', 'data' => base64_encode(str_repeat('x', 500))]], []);
    T::eq([$big->step(), $big->result()['error']], ['upload', 'The file is 500 bytes; at most 100 bytes can be imported.'], 'too large');
    $wrongMode = impCustomers()->storage(sys_get_temp_dir())->handle(['c' => ['step' => 'map', 'data' => base64_encode($csv)]], []);
    T::eq($wrongMode->step(), 'upload', 'with a storage directory, data in the form is not taken');
},

'uploads: only what PHP received as an upload, its errors in words, its size before it is read' => function (): void {
    Lang::set('en');
    $path = impTmp("Nr\nK1\n");
    $files = ['c' => ['name' => ['file' => 'x.csv'], 'tmp_name' => ['file' => $path], 'error' => ['file' => 0], 'size' => ['file' => 6]]];
    $i = impCustomers()->handle(['c' => ['step' => 'upload']], $files);
    T::eq([$i->step(), $i->result()['error']], ['upload', 'The file did not arrive (upload error tmp). Choose it again.'], 'a path PHP did not upload is never read');
    $files['c']['tmp_name']['file'] = '/etc/passwd';
    T::eq(impTrustFiles(impCustomers())->handle(['c' => ['step' => 'upload']], $files)->step() === 'map', true, 'with the check replaced, a file is read');
    foreach ([UPLOAD_ERR_INI_SIZE => 'The file is larger than this server takes. At most 5 MB can be imported.',
              UPLOAD_ERR_PARTIAL => 'The file did not arrive (upload error 3). Choose it again.'] as $code => $message) {
        $files['c']['error']['file'] = $code;
        T::eq(impCustomers()->handle(['c' => ['step' => 'upload']], $files)->result()['error'], $message, "upload error $code");
    }
    $files['c']['error']['file'] = UPLOAD_ERR_NO_FILE;
    T::eq(impCustomers()->handle(['c' => ['step' => 'upload', 'text' => "Nr\nK1\n"]], $files)->step(), 'map', 'no file: the pasted text');
    $files = ['c' => ['name' => ['file' => 'x.csv'], 'tmp_name' => ['file' => impTmp(str_repeat('x', 3000))], 'error' => ['file' => 0], 'size' => ['file' => 1]]];
    $i = impTrustFiles(impCustomers()->limits(10, 2048))->handle(['c' => ['step' => 'upload']], $files);
    T::eq($i->result()['error'], 'The file is 3 KB; at most 2 KB can be imported.', 'the size of the file on disk, not the size claimed');
    $i = impCustomers()->limits(10, 100)->handle(['c' => ['step' => 'upload', 'text' => str_repeat('x', 101)]], []);
    T::eq($i->result()['error'], 'The file is 101 bytes; at most 100 bytes can be imported.', 'pasted text too');
    // Larger than post_max_size: PHP drops the whole POST.
    $saved = $_SERVER;
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['CONTENT_LENGTH'] = '99999999';
    $_SERVER['CONTENT_TYPE'] = 'multipart/form-data; boundary=x';
    $_POST = [];
    $i = impCustomers()->handle();
    $_SERVER = $saved;
    T::eq($i->result()['error'], 'The file is larger than this server takes. At most 5 MB can be imported.', 'post_max_size in words');
},

'a choice that makes the file unreadable is said, and the file is read as before' => function (): void {
    Lang::set('en');
    $csv = impExcel([['Nr', 'Nachname', 'E-Mail'], ['K1', 'Müller', 'a@b.at']]);
    $i = impCustomers()->storage(false)->handle(['c' => ['step' => 'map', 'data' => base64_encode($csv), 'encoding' => 'UTF-8']], []);
    T::eq([$i->step(), $i->result()['meta']['encoding'], $i->result()['rows'][2]['last']], ['map', 'Windows-1252', 'Müller'], 'still step 2, read as before');
    T::contains($i->html(), '<div class="gk-message gk-message-error gk-message-sm" role="alert"><span class="material-icons" aria-hidden="true">error</span><div class="gk-message-content">The file is not valid UTF-8. Choose Windows-1252.</div></div>', 'and why');
},

'fromFile() and the server API without a form' => function (): void {
    Lang::set('en');
    $path = impTmp("Nr;Nachname;E-Mail\nK1;Huber;a@b.at\n");
    $r = impCustomers()->fromFile($path)->result();
    T::eq([$r['valid'], $r['meta']['filename'] ?? basename($path)], [1, basename($path)], 'read from disk');
    [$i, $notes] = impQuiet(fn () => impCustomers()->fromFile('/nope/missing.csv'));
    T::eq([$i->step(), count($notes)], ['upload', 1], 'a missing file warns');
    T::eq(impCustomers()->limits(10, 10)->fromFile($path)->result()['error'], 'The file is 35 bytes; at most 10 bytes can be imported.', 'its size is checked before it is read');
    T::eq(impCustomers()->fromString("\x00\x01binary")->step(), 'upload', 'a refused string is no step 2');
},

// ── Definitions that go wrong ───────────────────────────────────────────

'wrong options warn and fall back; nothing throws' => function (): void {
    [$i, $notes] = impQuiet(fn () => (new Import('bad name!'))
        ->field('ok', 'Ok')
        ->field('ok', 'Twice')
        ->field('1x', 'Key')
        ->field('t', 'T', ['type' => 'phone', 'color' => 'red', 'required' => 'maybe', 'aliases' => 7, 'options' => ['a']])
        ->field('s', 'S', ['type' => 'select'])
        ->locale('fr')->limits(0, 99999999999)->preview(9999)->delimiter(':')->enclosure('*')->encoding('EBCDIC')
        ->storage('/tmp', 5)->action('javascript:alert(1)')->hidden(['' => 'x', 'y' => ['z']]));
    $all = implode("\n", $notes);
    foreach (["is 'badname' now", 'defined twice', "field('1x')", "the type 'phone'", "the option 'color'", "'required' => 'maybe'",
              "'aliases' => 7", "'options', which only a 'select' reads", "'select' without 'options'", "locale('fr')", 'limits(): 0 rows',
              'limits(): 99999999999 bytes', 'preview(9999)', "delimiter(':')", "enclosure('*')", "encoding('EBCDIC')", 'storage(): a file is kept 5 seconds',
              "a 'javascript:' URL is refused", 'hidden() takes'] as $w) {
        T::contains($all, $w, 'a warning says ' . $w);
    }
    T::ok(str_starts_with($notes[0] ?? '', "GridKit: import 'bad name!':"), 'every warning names the importer');
    $html = $i->html();
    T::contains($html, 'data-gk-import="badname"', 'the cleaned name');
    T::contains($html, 'action=""', 'the page itself');
    T::contains($html, 'Up to 20 MB and 1 row.', 'the clamped limits');
},

// ── The script, the stylesheet, the words, the skill ─────────────────────

'the script is a file of its own, strict, closed in a function, with GK.import as its only name outside' => function () use ($root, $code): void {
    T::ok(is_file($root . '/js/import.js'), 'js/import.js — the dashboard keeps its own gridkit.js, a block there would never reach it');
    $c = $code();
    T::contains($c, '"use strict"', 'strict');
    T::ok((bool) preg_match('/\A\s*\(function \(\) \{/', $c), 'opens with a function that closes over everything');
    T::ok((bool) preg_match('/\}\)\(\);\s*\z/', $c), 'and calls it');
    preg_match_all('/\bwindow\.([A-Za-z_]+)(?:\.[A-Za-z_]+)?\s*=[^=]/', $c, $m);
    T::eq(array_values(array_unique($m[1])), ['GK'], 'the only global it writes is GK (GK.import)');
    T::ok(!preg_match('/\b(jQuery|\$\()|\bimport\s+[\w{*]|\brequire\(/', $c), 'no dependency');
    foreach (['MutationObserver', 'readyState', 'WeakMap', 'FileReader', 'readAsArrayBuffer', 'TextDecoder', '"windows-1252"', 'fatal: true',
              'ignoreBOM: true', 'dragover', 'dataTransfer', 'input.files = e.dataTransfer.files', 'gk:importread', 'gk:importmap', 'gk:importready',
              'gk:importerror', 'Math.imul(h, 0x01000193)'] as $w) {
        T::contains($c, $w, "the script uses $w");
    }
    // A page may inline the script (the fixture of ci/browser-import.js does): nothing in it may end the element.
    T::ok(stripos((string) file_get_contents($root . '/js/import.js'), '</script') === false && !str_contains((string) file_get_contents($root . '/js/import.js'), '<!--'),
        'no "</script" and no "<!--" in the file, comments included');
    exec('node --version 2>/dev/null', $probe, $hasNode);
    if ($hasNode === 0) {
        exec('node --check ' . escapeshellarg($root . '/js/import.js') . ' 2>&1', $out, $rc);
        T::eq($rc, 0, 'js/import.js parses: ' . implode(' ', $out));
    }
},

'the script and Import.php share their tables: the folding, yes and no, the e-mail rule, the limits' => function () use ($root, $js): void {
    $php = (string) file_get_contents($root . '/src/Import.php');
    $s = $js();
    $pairs = static function (string $src, string $name, string $open): array {
        $at = strpos($src, $name);
        if ($at === false) return [];
        $body = substr($src, $at, (int) strpos($src, $open === '[' ? '];' : '};', $at) - $at);
        preg_match_all('/[\'"]([^\'"]+)[\'"]\s*(?:=>|:)\s*[\'"]([^\'"]*)[\'"]/u', $body, $m, PREG_SET_ORDER);
        $out = [];
        foreach ($m as $x) $out[$x[1]] = $x[2];
        return $out;
    };
    T::eq($pairs($s, 'var FOLD = {', '{'), $pairs($php, 'private const FOLD = [', '['), 'FOLD');
    T::ok(count($pairs($php, 'private const FOLD = [', '[')) > 50, 'the table was read: ' . count($pairs($php, 'private const FOLD = [', '[')));
    T::eq($pairs($s, 'var UMLAUT_AE', '{'), $pairs($php, 'private const UMLAUT_AE', '['), 'UMLAUT_AE');
    T::eq($pairs($s, 'var UMLAUT_A ', '{'), $pairs($php, 'private const UMLAUT_A ', '['), 'UMLAUT_A');
    foreach (['TRUE_WORDS', 'FALSE_WORDS'] as $list) {
        preg_match('/' . $list . ' = \[([^\]]*)\]/', $php, $a);
        preg_match('/' . $list . ' = \[([^\]]*)\]/', $s, $b);
        T::eq(str_replace("'", '"', $a[1] ?? 'x'), $b[1] ?? 'y', $list);
    }
    preg_match("/preg_match\\('\\/\\^\\(\\?=\\.\\{1,254\\}\\$\\)(.*?)' *\\n *\\. '(.*?)\\$\\/D'/s", $php, $p);
    preg_match('/var EMAIL = \/\^\(\?=\.\{1,254\}\$\)(.*?)\$\/;/', $s, $q);
    T::eq(str_replace(["\\'", '\\/'], ["'", '/'], ($p[1] ?? 'x') . ($p[2] ?? '')), str_replace('\\/', '/', $q[1] ?? 'y'), 'the e-mail rule');
    foreach (['MAX_COLUMNS' => '200', 'HARD_ROWS' => '100000', 'HARD_BYTES' => '20971520'] as $k => $v) {
        T::contains($php, "private const $k = $v;", "Import.php: $k");
        T::contains($s, "var $k = $v;", "import.js: $k");
    }
    T::contains($s, 'var SAMPLE = 50;', 'the same sample of records for the separator');
    T::contains($php, 'private const SAMPLE_RECORDS = 50;', 'in both');
},

'every data-gk-import attribute Import.php writes is read by the script or the stylesheet' => function () use ($root, $js): void {
    $php = (string) file_get_contents($root . '/src/Import.php');
    preg_match_all('/data-gk-import(?:-[a-z]+)*/', $php, $m);
    $attrs = array_values(array_unique($m[0]));
    T::ok(count($attrs) >= 5, 'found ' . count($attrs) . ' attributes');
    $css = gkCssText();
    foreach ($attrs as $attr) {
        T::ok(str_contains($js(), $attr) || str_contains($css, $attr), "$attr is written and read by nothing");
    }
},

'every word: in both languages, carried to the script, and every word the script says is carried' => function () use ($root, $js): void {
    $en = require $root . '/lang/en.php';
    $de = require $root . '/lang/de.php';
    $php = (string) file_get_contents($root . '/src/Import.php');
    preg_match_all("/Lang::t\\('(import\\.[a-z0-9_]*[a-z0-9])'/", $php, $m);
    $keys = array_values(array_unique($m[1]));
    T::ok(count($keys) > 50, 'found ' . count($keys) . ' keys');
    foreach ($keys as $k) {
        T::ok(isset($en[$k]) && isset($de[$k]), "$k is in lang/en.php and lang/de.php");
    }
    // Built from a word: 'import.step_' . $s, 'import.delim_' . $word, 'import.enc_' . the encoding.
    $built = ['step_file', 'step_map', 'step_done', 'delim_semicolon', 'delim_comma', 'delim_tab', 'delim_pipe', 'enc_utf8', 'enc_windows1252', 'enc_utf16le', 'enc_utf16be'];
    foreach (array_keys($en) as $k) {
        if (!str_starts_with($k, 'import.') || in_array(substr($k, 7), $built, true)) continue;
        T::ok(str_contains($php, "'" . $k . "'") || str_contains($php, "'" . substr($k, 7) . "'"), "$k is used");
    }
    foreach ($built as $k) T::ok(isset($en["import.$k"]) && isset($de["import.$k"]), "import.$k is in both languages");
    preg_match('/private const WORDS = \[(.*?)\];/s', $php, $w);
    preg_match_all("/'([a-z0-9_]+)'/", $w[1] ?? '', $words);
    preg_match_all('/say\((?:st\.)?cfg, "([a-z0-9_]*[a-z0-9])"/', $js(), $said);
    $needed = array_values(array_unique($said[1]));
    T::ok(count($needed) > 40, 'the script says ' . count($needed) . ' words');
    foreach ($needed as $k) T::ok(in_array($k, $words[1], true), "the script says $k — Import::WORDS does not carry it");
    foreach (['delim_semicolon', 'delim_comma', 'delim_tab', 'delim_pipe', 'enc_utf8', 'enc_windows1252', 'enc_utf16le', 'enc_utf16be'] as $k) {
        T::ok(in_array($k, $words[1], true), "Import::WORDS carries $k");
    }
    foreach ($words[1] as $k) T::ok(isset($en["import.$k"]), "WORDS: import.$k exists");
    foreach ($words[1] as $k) {
        T::ok(in_array($k, $needed, true) || preg_match('/^(delim|enc)_/', $k) === 1, "WORDS carries $k, which the script never says");
    }
},

'the stylesheet block: at the end, roles only, a phone rule, every class Import.php and the script write' => function () use ($root, $sheet, $code): void {
    $css = (string) file_get_contents($root . '/css/gridkit.css');
    $at = strpos($css, '/* === 1.119.0 — import === */');
    T::ok($at !== false, 'the block heading is there');
    $later = preg_match_all('~/\* === 1\.(\d+)\.~', substr($css, (int) $at + 10), $v) ? $v[1] : [];
    T::ok(array_filter($later, static fn (string $m): bool => (int) $m <= 119) === [], 'it is the last block, but for later releases');
    $s = $sheet();
    $plain = (string) preg_replace('~/\*.*?\*/~s', '', $s);
    T::eq(preg_match_all('/#[0-9a-f]{3,8}\b|rgba?\(|hsla?\(/i', $plain), 0, 'no literal colour: roles only, so both dark spellings and the six themes follow');
    T::ok(!preg_match('/\[data-gk-mode="dark"\]|\.gk-dark\b/', $plain), 'and no rule of its own for dark mode is needed');
    T::contains($s, '@media (max-width: 600px)', 'a phone');
    T::contains($s, '.gk-import-preview {' . "\n" . '  overflow-x: auto;', 'the preview scrolls in its own box');
    T::contains($s, 'input:focus-visible + .gk-checkbox-custom', 'the header box shows its focus');
    // Every gk-import class the code writes, comments left out; data-gk-import… and ids built on gk-import- are not classes.
    $php = '';
    foreach (token_get_all((string) file_get_contents($root . '/src/Import.php')) as $t) {
        if (is_array($t) && in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true)) continue;
        $php .= is_array($t) ? $t[1] : $t;
    }
    preg_match_all('/(?<![\w-])gk-import(?:-[a-z0-9]+)*(?![a-z0-9-])/', $php . $code(), $m);
    $classes = array_fill_keys($m[0], true);
    T::ok(count($classes) >= 30, 'found ' . count($classes) . ' classes');
    foreach (array_keys($classes) as $c) T::ok(preg_match('/\.' . preg_quote($c, '/') . '(?![\w-])/', $plain) === 1, "$c has a rule in the block");
    $blocks = json_decode((string) file_get_contents($root . '/css/blocks.json'), true)['blocks']['import'] ?? [];
    T::eq($blocks['name'] ?? '', 'Import', 'the block "Import" in css/blocks.json');
    T::eq(array_keys($blocks['classes'] ?? []), (static function (array $c): array { $k = array_keys($c); sort($k); return $k; })($classes), 'every class, nothing else');
},

'the demo, the skeleton and the skill bring the script and say how it works' => function () use ($root, $doc): void {
    T::contains((string) file_get_contents($root . '/skeleton.php'), "js/import.js') ?>\" defer></script>", 'skeleton.php loads the script');
    T::contains(demoSource(), "<script src=\"<?= Layout::asset('../js/import.js') ?>\" defer></script>", 'the demo loads it');
    T::contains(demoSource(), "->item('Import', '#import', 'upload_file')", 'and lists the section after Line items');
    T::ok(is_file($root . '/demo/media/kunden.csv'), 'the sample');
    $d = $doc();
    T::ok((bool) preg_match('/^### Import\b/m', $d), 'a "### Import" section');
    foreach (['js/import.js', 'gk:importread', 'gk:importmap', 'gk:importready', 'gk:importerror', 'GK.import.read(', 'GK.import.map(',
              'GK.import.result(', 'GK.import.check(', 'GK.import.reset(', 'GK.import.parse(', 'Import::parseCsv(', 'storage(false)',
              'Windows-1252', 'CSV injection', 'row 1'] as $w) {
        T::contains($d, $w, "GRIDKIT_SKILL.md says $w");
    }
},

];
})();

