<?php
/**
 * The page ci/browser-line-items.js drives (line items, 1.117.0): editors of every kind on one
 * self-contained HTML document — stylesheet and both scripts inlined, so it loads from file:// with
 * no server. The article search asks http://gk.test/articles and the forms post to
 * http://gk.test/post; the browser test answers both with page.route().
 *
 * Next to the editors, in <script id="php-totals">, what LineItems::totals() says of each of them —
 * the figures the browser has to arrive at by itself.
 *
 *     php ci/browser-line-items-fixture.php [seed] > /tmp/f.html
 *     php ci/browser-line-items-fixture.php --parse inv < post.json   # parse() of a POST, as JSON
 *
 * With --parse the same editors are built, and the POST — [[name, value], …] as the browser's
 * FormData has it — goes through parse() of the one named. One definition for both sides.
 */

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use GridKit\{Form, Lang, LineItems};

Lang::set('en');
$root = dirname(__DIR__);
$parseOnly = ($argv[1] ?? '') === '--parse' ? (string) ($argv[2] ?? '') : null;
$seed = $parseOnly === null ? (int) ($argv[1] ?? 1117) : 1117;
mt_srand($seed);

/** A random figure in machine form, with up to $dec decimals. */
$fig = static function (int $max, int $dec): string {
    $v = (string) mt_rand(0, $max);
    if ($dec > 0 && mt_rand(0, 3) > 0) $v .= '.' . str_pad((string) mt_rand(0, 10 ** $dec - 1), $dec, '0', STR_PAD_LEFT);
    return $v;
};
/** Random lines: quantities to 3 decimals, prices to 2 (some to 4), discounts, four rates, some credits. */
$random = static function (int $n, array $rates) use ($fig): array {
    $rows = [];
    for ($i = 0; $i < $n; $i++) {
        $price = $fig(mt_rand(0, 4) === 0 ? 99999 : 2500, mt_rand(0, 5) === 0 ? 4 : 2);
        if (mt_rand(0, 9) === 0) $price = '-' . $price;
        $rows[] = [
            'description' => 'Line ' . ($i + 1),
            'qty' => mt_rand(0, 6) === 0 ? $fig(20, 3) : (string) mt_rand(1, 40),
            'unit' => 'pcs',
            'price' => $price === '-0' ? '0' : $price,
            'discount' => mt_rand(0, 2) === 0 ? $fig(30, 2) : '',
            'vat' => $rates[mt_rand(0, count($rates) - 1)],
        ];
    }
    return $rows;
};

/**
 * What a browser would post from this markup, without script: every named field outside the
 * <template>, the selected option of a select. The same thing ci/browser-line-items.js checks
 * a real browser posts.
 *
 * @return array<string,mixed>
 */
function postOf(string $html): array
{
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8"><div>' . $html . '</div>');
    libxml_clear_errors();
    $pairs = [];
    $xp = new DOMXPath($doc);
    foreach ($xp->query('//*[@name][not(ancestor::template)]') as $node) {
        /** @var DOMElement $node */
        $name = $node->getAttribute('name');
        $value = match ($node->nodeName) {
            'textarea' => $node->textContent,
            'select' => (static function (DOMElement $s) use ($xp): string {
                $sel = $xp->query('.//option[@selected]', $s);
                $first = $xp->query('.//option', $s);
                $o = $sel->length ? $sel->item(0) : $first->item(0);
                return $o instanceof DOMElement ? $o->getAttribute('value') : '';
            })($node),
            'input' => in_array($node->getAttribute('type'), ['button', 'submit'], true) ? null : $node->getAttribute('value'),
            default => null,
        };
        if ($value !== null) $pairs[] = rawurlencode($name) . '=' . rawurlencode($value);
    }
    parse_str(implode('&', $pairs), $post);
    return $post;
}

$editors = [];
$posted = [];
$out = [];
$add = static function (string $title, string $id, LineItems $li, string $wrap = '') use (&$out, &$editors, &$posted): void {
    $html = $li->html();
    $editors[$id] = $li;
    $posted[$id] = postOf($html);
    $out[] = '<section id="sec-' . $id . '"><h3>' . htmlspecialchars($title) . '</h3>'
           . ($wrap !== '' ? str_replace('%s', $html, $wrap) : $html) . '</section>';
};

// 1. An Austrian invoice: German figures, 20 % and 10 %, a line discount, an overall discount, shipping.
$invoice = [
    ['id' => 41, 'description' => "Website relaunch\nDesign, templates, 3 rounds of changes", 'qty' => '1', 'unit' => 'flat', 'price' => '2400', 'discount' => '5', 'vat' => '20'],
    ['id' => 42, 'description' => 'Hosting, annual', 'qty' => '12', 'unit' => 'month', 'price' => '19.90', 'discount' => '', 'vat' => '20'],
    ['id' => 43, 'description' => 'Handbook (print)', 'qty' => '3', 'unit' => 'pcs', 'price' => '34.50', 'discount' => '', 'vat' => '10'],
];
$add('An invoice, de', 'inv', (new LineItems('inv'))->invoiceColumns()->locale('de')->vatRates([20, 10, 13, 0])
    ->discount(3)->shipping('4.90')->items($invoice),
    '<form id="inv-form" action="http://gk.test/post" method="post">%s<button type="submit" id="inv-submit">Send</button></form>');

// 2.–4. Random lines: net with line rounding, net with total rounding, gross with line rounding.
$rates = ['20', '10', '13', '0', '7.7'];
$add('Random, net, line', 'rnd', (new LineItems('rnd'))->invoiceColumns()->locale('de')->vatRates($rates)->discount('2.5')->shipping('9.99', 10)->items($random(60, $rates)));
$add('Random, net, total', 'rnt', (new LineItems('rnt'))->invoiceColumns()->locale('en')->vatRates($rates)->rounding('total')->discount('7')->items($random(60, $rates)));
$add('Random, gross', 'rng', (new LineItems('rng'))->invoiceColumns()->locale('de')->vatRates($rates)->pricesNet(false)->shipping('5')->items($random(60, $rates)));

// 5. Limits: at least one line, at most three; English.
$add('Limits 1..3, en', 'lim', (new LineItems('lim'))->invoiceColumns()->locale('en')->limits(1, 3),
    '<form id="lim-form" action="http://gk.test/post" method="post">%s<button type="submit" id="lim-submit">Send</button></form>');

// 6. Empty, no limits.
$add('Empty', 'emp', (new LineItems('emp'))->invoiceColumns()->locale('en'));

// 7. A sent invoice.
$add('Read-only', 'ro', (new LineItems('ro'))->invoiceColumns()->locale('de')->vatRates([20, 10])->discount(3)->shipping('4.90')->items($invoice)->readonly());

// 8. An article search that fills the line.
$add('Lookup', 'lk', (new LineItems('lk'))
    ->column('article', 'Article', ['type' => 'lookup', 'url' => 'http://gk.test/articles', 'minChars' => 1,
        'fill' => ['description' => 'name', 'unit' => 'unit', 'price' => 'price', 'vat' => 'vat'], 'display' => 'article_name'])
    ->column('description', 'Description', ['type' => 'text', 'required' => true])
    ->column('qty', 'Qty', ['type' => 'number', 'required' => true])
    ->column('unit', 'Unit', ['type' => 'select'])
    ->column('price', 'Price', ['type' => 'money', 'required' => true])
    ->column('vat', 'VAT', ['type' => 'select'])
    ->column('total', 'Total', ['type' => 'computed'])
    ->locale('en')->vatRates([20, 10])->limits(1));

// 9. A Form (AJAX) with the editor as a field of type 'lineitems': named by the field's label, its
//    cells reached by the {"errors": {"fx[1][qty]": …}} of the server's answer.
$fx = (new LineItems('fx'))->invoiceColumns()->locale('en')->vatRates([20, 10]);
ob_start();
(new Form('fx-form'))->action('http://gk.test/ajax')->ajax()
    ->field('customer', 'Customer', 'text', ['value' => 'Ecklund & Partner'])
    ->field('fx', 'Lines', 'lineitems', ['lines' => $fx])
    ->values(['fx' => [
        ['description' => 'Consulting', 'qty' => '2', 'unit' => 'h', 'price' => '95', 'vat' => '20'],
        ['description' => 'Travel', 'qty' => '1', 'unit' => 'flat', 'price' => '40', 'vat' => '20'],
    ]])
    ->submit('Save')
    ->render();
$formHtml = (string) ob_get_clean();
$editors['fx'] = $fx;
$posted['fx'] = postOf($formHtml);
$out[] = '<section id="sec-fx"><h3>In a Form (AJAX)</h3>' . $formHtml . '</section>';

if ($parseOnly !== null) {
    // The POST the browser built, through parse() of that editor.
    $pairs = json_decode((string) stream_get_contents(STDIN), true) ?: [];
    parse_str(implode('&', array_map(static fn(array $p): string => rawurlencode((string) $p[0]) . '=' . rawurlencode((string) $p[1]), $pairs)), $post);
    echo json_encode($editors[$parseOnly]->parse($post), JSON_UNESCAPED_UNICODE);
    exit;
}

// The figures PHP arrives at, for every editor as drawn: what parse() makes of the POST the editor
// would send now (every field as the page shows it, in the page's own number shape).
$php = [];
foreach ($editors as $id => $li) $php[$id] = $li->parse($posted[$id])['totals'];
// A sent invoice posts nothing: its figures are the totals of its lines.
$php['ro'] = LineItems::totals($invoice, $editors['ro']->totalsOptions());

echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
   . '<meta name="viewport" content="width=device-width, initial-scale=1"><title>Line items</title>'
   . '<style>' . file_get_contents($root . '/css/gridkit.css') . '</style>'
   . '<style>' . file_get_contents($root . '/css/themes.css') . '</style>'
   . '<style>body{background:var(--gk-bg);color:var(--gk-on-surface);margin:0;padding:16px;font-family:var(--gk-font)}'
   . 'h3{font-size:13px;margin:24px 0 8px}section{max-width:1040px}</style>'
   . '</head><body class="gk-root">' . Lang::jsConfig()
   . '<script type="application/json" id="php-totals">' . json_encode($php, JSON_HEX_TAG) . '</script>'
   . implode("\n", $out)
   . '<script>' . file_get_contents($root . '/js/gridkit.js') . '</script>'
   . '<script>' . file_get_contents($root . '/js/sortierbar.js') . '</script></body></html>';
