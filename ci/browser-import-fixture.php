<?php
/**
 * The pages ci/browser-import.js drives (GridKit\Import, 1.119.0) — a router for PHP's own server,
 * so the forms really post and the flow without script really runs:
 *
 *     php -S 127.0.0.1:8140 ci/browser-import-fixture.php
 *
 *   GET  /             importers: 'c' (the file kept under a token in GK_IMPORT_STORE), 's'
 *                      (storage(false)), 'a' (step 2 drawn by the server, for the script to take
 *                      over) and 'cmp' (the one the corpus is read into); ?lang=de, ?theme=…, ?mode=dark|class
 *   POST /             the same page after handle(); what result() says is in #server-result
 *   GET  /corpus.json  the cases: the bytes (base64), and what parseCsv(), result() and the panel of
 *                      Import.php make of them — the script has to arrive at the very same
 *   GET  /sample.csv   demo/media/kunden.csv
 *
 * Both stylesheets and both scripts are inlined, so the page needs nothing else.
 */

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use GridKit\{Import, Lang};

$root = dirname(__DIR__);
$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$lang = ($_GET['lang'] ?? '') === 'de' ? 'de' : 'en';
Lang::set($lang);

/** The fields every importer of the fixture has: one of each type, aliases, required, unique. */
function fxImporter(string $name): Import
{
    return (new Import($name))
        ->label('Import ' . $name)
        ->field('no', 'Customer no.', ['required' => true, 'unique' => true, 'aliases' => ['Kundennummer', 'Nr']])
        ->field('first', 'First name', ['aliases' => ['Vorname']])
        ->field('last', 'Last name', ['required' => true, 'aliases' => ['Nachname', 'Name']])
        ->field('email', 'E-mail', ['type' => 'email', 'unique' => true, 'aliases' => ['E-Mail', 'Mail']])
        ->field('country', 'Country', ['type' => 'select', 'options' => ['AT' => 'Österreich', 'DE' => 'Deutschland', 'CH' => 'Schweiz', 'IT' => 'Italia'], 'aliases' => ['Land']])
        ->field('born', 'Birthday', ['type' => 'date', 'aliases' => ['Geburtsdatum']])
        ->field('revenue', 'Revenue', ['type' => 'money', 'aliases' => ['Umsatz']])
        ->field('qty', 'Quantity', ['type' => 'number', 'aliases' => ['Menge']])
        ->field('news', 'Newsletter', ['type' => 'bool', 'aliases' => ['Abo']])
        ->locale('de');
}

function fxStore(): string
{
    $dir = getenv('GK_IMPORT_STORE') ?: sys_get_temp_dir() . '/gk-import-fixture';
    return $dir;
}

if ($path === '/sample.csv') {
    header('Content-Type: text/csv; charset=windows-1252');
    readfile($root . '/demo/media/kunden.csv');
    return;
}

if ($path === '/corpus.json') {
    header('Content-Type: application/json');
    echo json_encode(fxCorpus($root), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    return;
}

// ── The page ───────────────────────────────────────────────────────────────
$c = fxImporter('c')->storage(fxStore(), 600)->hidden(['_token' => 'tok-<&>"'])->preview(6);
$s = fxImporter('s')->storage(false);
$a = fxImporter('a')->storage(false)->preview(5);
$cmp = fxImporter('cmp')->storage(false)->limits(300, 262144);
$small = fxImporter('small')->storage(false)->limits(5, 4096);
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    foreach ([$c, $s, $a, $cmp, $small] as $i) $i->handle();
}
$sample = (string) file_get_contents($root . '/demo/media/kunden.csv');
if ($a->step() === 'upload' && !isset($_POST['a'])) $a->fromString($sample, 'kunden.csv');

$server = [];
foreach (['c' => $c, 's' => $s, 'a' => $a, 'cmp' => $cmp, 'small' => $small] as $name => $i) {
    if (isset($_POST[$name])) $server = ['name' => $name, 'step' => $i->step(), 'result' => fxPlain($i->result())];
}
$mode = $_GET['mode'] ?? '';
$theme = preg_match('/^[a-z]+$/', (string) ($_GET['theme'] ?? '')) ? (string) $_GET['theme'] : '';
// The theme, the mode and both dark spellings on <body>, where Theme::bodyTag() writes them.
$htmlAttrs = ' lang="' . $lang . '"';
$bodyAttrs = ' class="gk-root' . ($mode === 'class' ? ' gk-dark' : '') . '"' . ($mode === 'dark' ? ' data-gk-mode="dark"' : ($mode === 'class' ? '' : ' data-gk-mode="light"'))
    . ($theme !== '' ? ' data-gk-theme="' . $theme . '"' : '');

echo '<!doctype html><html' . $htmlAttrs . '><head><meta charset="utf-8">'
   . '<meta name="viewport" content="width=device-width, initial-scale=1"><title>Import</title>'
   . '<style>' . file_get_contents($root . '/css/gridkit.css') . '</style>'
   . '<style>' . file_get_contents($root . '/css/themes.css') . '</style>'
   . '<style>body{background:var(--gk-surface-container);color:var(--gk-on-surface);margin:0;padding:16px;font-family:var(--gk-font)}'
   . 'section{max-width:1240px;margin:0 auto 24px;background:var(--gk-surface);border:1px solid var(--gk-outline-variant);border-radius:12px;padding:20px;box-sizing:border-box}'
   . 'h2{font-size:15px;margin:0 0 12px}</style>'
   . Lang::jsConfig() . '</head><body' . $bodyAttrs . '>'
   . '<button type="button" id="before">before</button>'
   . '<section id="s-c"><h2>Kept under a token</h2>' . $c->html() . '</section>'
   . '<section id="s-s"><h2>Travels in the form</h2>' . $s->html() . '</section>'
   . '<section id="s-a"><h2>Step 2 by the server</h2>' . $a->html() . '</section>'
   . '<section id="s-small"><h2>Small limits</h2>' . $small->html() . '</section>'
   . '<section id="s-cmp"><h2>The corpus</h2>' . $cmp->html() . '</section>'
   . '<script type="application/json" id="server-result">' . json_encode($server, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_INVALID_UTF8_SUBSTITUTE) . '</script>'
   . (($_GET['nojs'] ?? '') === '1' ? '' : '<script>' . file_get_contents($root . '/js/gridkit.js') . '</script>'
       . '<script>' . file_get_contents($root . '/js/import.js') . '</script>')
   . '</body></html>';

// ── Helpers ────────────────────────────────────────────────────────────────

/** result() as JSON has it: rows and errors as objects even when empty. */
function fxPlain(array $r): array
{
    $r['rows'] = (object) $r['rows'];
    $r['errors'] = (object) $r['errors'];
    foreach ($r['errors'] as $k => $v) $r['errors']->$k = (object) $v;
    return $r;
}

/**
 * The cases: written by hand for each rule, then 160 drawn at random (a fixed seed) — every
 * separator, quoting, line ending, encoding and value shape, mixed.
 *
 * @return list<array<string,mixed>>
 */
function fxCorpus(string $root): array
{
    $cases = [];
    $add = static function (string $name, string $bytes, string $file = 'x.csv') use (&$cases): void {
        $cases[] = ['name' => $name, 'bytes' => $bytes, 'file' => $file];
    };
    $w = static fn (string $s): string => (string) iconv('UTF-8', 'Windows-1252', $s);
    $add('demo sample', (string) file_get_contents($root . '/demo/media/kunden.csv'), 'kunden.csv');
    $add('utf-8 bom', "\xEF\xBB\xBFNr;Name;E-Mail\r\nK1;Müller;a@b.at\r\n");
    $add('utf-16le bom', "\xFF\xFE" . iconv('UTF-8', 'UTF-16LE', "Nr\tName\tUmsatz\r\nK1\tZoë\t1.234,50\r\n"));
    $add('utf-16be bom', "\xFE\xFF" . iconv('UTF-8', 'UTF-16BE', "Nr;Name\nK1;Jürgen\n"));
    $add('utf-16le no bom', (string) iconv('UTF-8', 'UTF-16LE', "Nr;Name\nK1;Ann\nK2;Bo\n"));
    $add('utf-16 broken', "\xFF\xFE" . "N\x00r\x00;\x00a\x00\n\x00K\x001\x00;\x00\x00\xD8x\x00\n");
    $add('cr only', "Nr;Name\rK1;Huber\rK2;Gruber\r");
    $add('lf only', "Nr,Name,Menge\nK1,Huber,\"1,5\"\nK2,Gruber,12\n");
    $add('quotes everywhere', "Nr;Name;Notiz\r\n\"K1\";\"Huber; Max\";\"Zeile 1\r\nZeile 2\"\r\n\"K2\";\"O\"\"Neil\";\"\"\r\nK3;Gruber;5\" Bildschirm\r\n");
    $add('single quotes', "'Nr';'Name'\n'K1';'Huber; Max'\n'K2';'Gruber'\n");
    $add('sep line', "sep=|\r\nNr|Name|Land\r\nK1|Huber|AT\r\n");
    $add('pipe', "Nr|Name|Land\nK1|Huber|at\nK2|Gruber|Österreich\n");
    $add('tab', "Nr\tName\tGeburtsdatum\nK1\tHuber\t31.12.1990\nK2\tGruber\t31.02.1990\n");
    $add('no header', "K1;Huber;a@b.at\nK2;Gruber;c@d.at\n");
    $add('empty lines and separators', "Nr;Name;;\r\n\r\nK1;Huber;;\r\n;;;\r\n   ;  \r\nK2;Gruber;;\r\n;;;;\r\n");
    $add('wide and short rows', "Nr;Name\nK1;Huber;extra;more\nK2\n");
    $add('unclosed quote', "Nr;Name\nK1;\"Huber\nK2;Gruber\n");
    $add('duplicates', "Nr;Name;E-Mail\nK1;Huber;a@b.at\nk1;Gruber;A@B.AT\nK3;Moser;a@b.at\nK1;Bauer;c@d.at\n");
    $add('required missing', "Vorname;Land\nAnna;AT\nBen;DE\n");
    $add('a field twice by name', "Name;Nachname;Nr\nHuber;Gruber;K1\n");
    $add('hostile cells', "Nr;Name;Notiz\nK1;<script>alert(1)</script>;\"<img src=x onerror=alert(1)>\"\nK2;&amp;;\"\"\"><b>\"\n");
    $add('windows-1252 all', $w("Nr;Name;Notiz\r\nK1;Zoë;€ „Anführung“ – ‚x‘ … ™ Œ Š Ÿ\r\n") . "K2;\x81\x8D\x8F\x90\x9D;\xA0\xFF\r\n");
    $add('invalid utf-8', "Nr;Name\nK1;M\xFCller\nK2;\xC0\xAF\n");
    $add('binary', "\x89PNG\r\n\x1a\n\0\0\0\rIHDR");
    $add('too many rows', "Nr;Name\n" . str_repeat("K;x\n", 301));
    $add('too large', "Nr;Name\n" . str_repeat("K1;" . str_repeat('x', 1000) . "\n", 300));
    $add('too wide', implode(';', range(1, 201)) . "\n");
    $add('one column with a comma', "Name\nHuber GmbH\nMeier, Müller & Co\nGruber KG\n");
    $add('numbers and amounts', "Nr;Name;Umsatz;Menge\nK1;A;1.234,50;1.500\nK2;B;€ 12;1,5\nK3;C;1,005;1e5\nK4;D;-0,5;1 234,5\nK5;E;EUR 3;0x10\n");
    $add('dates', "Nr;Name;Geburtsdatum\nK1;A;1.2.2000\nK2;B;2000-02-29\nK3;C;14/02/2001\nK4;D;29.02.2001\nK5;E;31.12.26\nK6;F;2001-13-01\n");
    $add('umlaut spellings', "Nr;Name;Land\nK1;A;Osterreich\nK2;B;Oesterreich\nK3;C;Österreich\nK4;D;SCHWEIZ\n");
    $add('bools and selects', "Nr;Name;Abo;Land\nK1;A;JA;at\nK2;B;x;Deutschland\nK3;C;;schweiz\nK4;D;vielleicht;Atlantis\nK5;E;nein;IT\n");
    $add('emails', "Nr;Name;Mail\nK1;A;Max.Huber+x@Example.CO.AT\nK2;B;max@\nK3;C;a..b@c.at\nK4;D;müller@x.at\nK5;E;a@xn--mller-kva.at\nK6;F;a@b.c\n");
    $add('nbsp and spaces', "Nr;Name\n\xC2\xA0K1\xC2\xA0;  Huber  \n");
    $add('only a header', "Nr;Name;E-Mail\n");
    $add('empty', '');
    $add('long cells', "Nr;Name;Notiz\nK1;A;" . str_repeat('lang ', 60) . "\n");

    mt_srand(20261119);
    for ($n = 0; $n < 160; $n++) $add('random ' . $n, fxRandom(), 'random-' . $n . '.csv');

    $out = [];
    foreach ($cases as $case) {
        $i = fxImporter('cmp')->storage(false)->limits(300, 262144);
        $names = ['no', 'Customer no.', 'Kundennummer', 'Nr', 'first', 'First name', 'Vorname', 'last', 'Last name', 'Nachname', 'Name',
                  'email', 'E-mail', 'E-Mail', 'Mail', 'country', 'Country', 'Land', 'born', 'Birthday', 'Geburtsdatum', 'revenue', 'Revenue',
                  'Umsatz', 'qty', 'Quantity', 'Menge', 'news', 'Newsletter', 'Abo'];
        $parsed = Import::parseCsv($case['bytes'], ['maxRows' => 300, 'maxBytes' => 262144, 'names' => $names]);
        $parsed['rows'] = (object) $parsed['rows'];
        $i->fromString($case['bytes'], $case['file']);
        $html = $i->html();
        $panel = preg_match('/<div class="gk-import-panel">.*<\/div>(?=<\/form>)/s', $html, $m) ? $m[0] : '';
        $out[] = ['name' => $case['name'], 'file' => $case['file'], 'b64' => base64_encode($case['bytes']), 'names' => $names,
                  'parsed' => $parsed, 'result' => fxPlain($i->result()), 'panel' => $panel];
    }
    return $out;
}

/** One CSV drawn at random: a header of known and unknown names, rows of every shape. */
function fxRandom(): string
{
    $pick = static fn (array $a) => $a[mt_rand(0, count($a) - 1)];
    $heads = ['Nr', 'Kundennummer', 'Name', 'Nachname', 'NAME', 'Vorname', 'E-Mail', 'e mail', 'Mail', 'Land', 'Geburtsdatum', 'Umsatz',
              'Menge', 'Abo', 'Newsletter', 'Notiz', 'Straße', 'Strasse', 'Größe', 'Ort', '', 'Bemerkung; intern', "Zeile\nzwei", 'Quote "x"'];
    $values = [
        static fn (): string => 'K' . mt_rand(1, 30),
        static fn (): string => ['Anna', 'Jürgen', 'Zoë', 'Weiß', "O'Neil", 'Huber; Max', "zwei\nZeilen", 'say "hi"', ' Leer ', "\u{00A0}NB", '', '<b>x</b>', 'Ünal'][mt_rand(0, 12)],
        static fn (): string => ['a@b.at', 'Max@Example.AT', 'max@', 'x.y+z@sub.example.co.at', 'a..b@c.at', 'müller@x.at', '', 'a@b'][mt_rand(0, 7)],
        static fn (): string => ['1.234,50', '1,234.50', '12', '-3,5', '€ 9,99', '1.500', '1,005', 'zwölf', '', '0', '1 234,5', '1e5'][mt_rand(0, 11)],
        static fn (): string => ['31.12.2026', '1.2.2000', '2024-02-29', '29.02.2023', '14/02/2001', '31.12.26', '', 'gestern'][mt_rand(0, 7)],
        static fn (): string => ['ja', 'nein', 'x', 'X', '1', '0', 'yes', 'No', 'vielleicht', '', 'wahr'][mt_rand(0, 10)],
        static fn (): string => ['AT', 'at', 'Österreich', 'oesterreich', 'Deutschland', 'CH', 'Italia', 'Atlantis', ''][mt_rand(0, 8)],
    ];
    $d = $pick([';', ',', "\t", '|']);
    $nl = $pick(["\r\n", "\n", "\r"]);
    $style = $pick(['needed', 'needed', 'always', 'single']);
    $q = $style === 'single' ? "'" : '"';
    $cols = mt_rand(1, 8);
    $header = mt_rand(0, 5) > 0;
    $lines = [];
    $cell = static function (string $v) use ($d, $q, $style): string {
        if ($style === 'always' || ($style === 'single' && $v !== '') || preg_match('/[' . preg_quote($d . $q, '/') . '\r\n]/', $v)
            || ($v !== '' && $v[0] === $q)) {
            return $q . str_replace($q, $q . $q, $v) . $q;
        }
        return $v;
    };
    $kinds = [];
    for ($c = 0; $c < $cols; $c++) $kinds[] = mt_rand(0, count($values) - 1);
    if ($header) {
        $h = [];
        for ($c = 0; $c < $cols; $c++) $h[] = $cell($pick($heads));
        $lines[] = implode($d, $h);
    }
    $rows = mt_rand(0, 30);
    for ($r = 0; $r < $rows; $r++) {
        if (mt_rand(0, 12) === 0) { $lines[] = mt_rand(0, 1) ? '' : str_repeat($d, $cols - 1); continue; }
        $row = [];
        $width = mt_rand(0, 10) === 0 ? mt_rand(1, $cols + 2) : $cols;
        for ($c = 0; $c < $width; $c++) $row[] = $cell(($values[$kinds[$c] ?? 1])());
        $lines[] = implode($d, $row);
    }
    $text = implode($nl, $lines) . (mt_rand(0, 3) ? $nl : '');
    if (mt_rand(0, 15) === 0) $text = 'sep=' . $d . $nl . $text;
    switch (mt_rand(0, 9)) {
        case 0: return "\xEF\xBB\xBF" . $text;
        case 1: return "\xFF\xFE" . iconv('UTF-8', 'UTF-16LE', $text);
        case 2: return "\xFE\xFF" . iconv('UTF-8', 'UTF-16BE', $text);
        case 3: case 4: case 5:
            $b = @iconv('UTF-8', 'Windows-1252//IGNORE', $text);
            return is_string($b) ? $b : $text;
        default: return $text;
    }
}
