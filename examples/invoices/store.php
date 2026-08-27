<?php

/**
 * A tiny invoice store kept in the session.
 *
 * Deliberately not a database: this example has to run wherever PHP runs,
 * with `php -S localhost:8000` and nothing else installed. Everything the
 * table does — search, sort, filter, paging — happens here against a plain
 * array, in the same places you would otherwise write SQL. The comments mark
 * each of those places.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../autoload.php';

use GridKit\Lang;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ── Language ──────────────────────────────────────────────────────────── */

$lang = $_GET['lang'] ?? $_POST['lang'] ?? $_SESSION['inv_lang'] ?? 'en';
if (!in_array($lang, ['en', 'de'], true)) {
    $lang = 'en';
}
$_SESSION['inv_lang'] = $lang;
Lang::loadDir(__DIR__ . '/lang');   // this example's strings, beside GridKit's
Lang::set($lang);

/**
 * This example's own strings, in GridKit's catalogue rather than beside it.
 *
 * Lang::loadDir() reads lang/en.php and lang/de.php and MERGES them, so one
 * Lang::t() answers for both GridKit's chrome and the application — with
 * {placeholder} substitution, and with the js.* keys reaching the browser
 * through Lang::jsConfig(). Until 1.49.0 this file carried a 63-entry array
 * and a lookup of its own, which is what people write when they have not
 * found loadDir(); GRIDKIT_SKILL.md recommended exactly that, for two
 * releases, because nobody had found it.
 *
 * @param array<string,scalar> $params
 */
function t(string $key, array $params = []): string
{
    return Lang::t('app.' . $key, $params);
}

/** The four states an invoice can be in, as value => label. */
function statuses(): array
{
    return [
        'draft'   => t('st_draft'),
        'sent'    => t('st_sent'),
        'paid'    => t('st_paid'),
        'overdue' => t('st_overdue'),
    ];
}

/* ── The store ─────────────────────────────────────────────────────────── */

function seed(): array
{
    $rows = [
        ['Ecklund & Partner',   1840.00, '2026-07-02', '2026-08-01', 'paid'],
        ['Nordlicht Media',     6250.00, '2026-07-11', '2026-08-10', 'paid'],
        ['Bergmann Logistik',    980.50, '2026-07-18', '2026-08-17', 'overdue'],
        ['Studio Kestner',      3400.00, '2026-08-01', '2026-08-31', 'sent'],
        ['Vogel Immobilien',     420.00, '2026-08-03', '2026-09-02', 'sent'],
        ['Hafner Elektro',      12750.00, '2026-08-05', '2026-09-04', 'draft'],
        ['Tannbach Consulting',  2190.00, '2026-06-20', '2026-07-20', 'overdue'],
        ['Lindner Reisen',       775.00, '2026-08-08', '2026-09-07', 'sent'],
        ['Kranz Architektur',   5600.00, '2026-08-12', '2026-09-11', 'draft'],
        ['Seewald Gastro',      1120.00, '2026-07-25', '2026-08-24', 'paid'],
        ['Portner Textil',      8300.00, '2026-08-14', '2026-09-13', 'sent'],
        ['Aubrig Werkstätten',   640.00, '2026-06-30', '2026-07-30', 'overdue'],
        ['Ravensberg Verlag',   2875.00, '2026-08-18', '2026-09-17', 'draft'],
        ['Delling Sanitär',     1495.00, '2026-08-20', '2026-09-19', 'sent'],
        ['Marbach Software',    9900.00, '2026-08-22', '2026-09-21', 'sent'],
    ];

    $out = [];
    foreach ($rows as $i => [$customer, $amount, $issued, $due, $status]) {
        $out[] = [
            'id'       => $i + 1,
            'number'   => sprintf('INV-2026-%03d', $i + 1),
            'customer' => $customer,
            'amount'   => $amount,
            'issued'   => $issued,
            'due'      => $due,
            'status'   => $status,
            'notes'    => '',
        ];
    }
    return $out;
}

function allInvoices(): array
{
    if (!isset($_SESSION['invoices'])) {
        $_SESSION['invoices'] = seed();
    }
    return $_SESSION['invoices'];
}

function saveAll(array $rows): void
{
    $_SESSION['invoices'] = array_values($rows);
}

function findInvoice(int $id): ?array
{
    foreach (allInvoices() as $row) {
        if ((int) $row['id'] === $id) {
            return $row;
        }
    }
    return null;
}

function nextId(): int
{
    $ids = array_column(allInvoices(), 'id');
    return $ids ? max($ids) + 1 : 1;
}

function nextNumber(): string
{
    $n = count(allInvoices()) + 1;
    do {
        $candidate = sprintf('INV-2026-%03d', $n++);
    } while (in_array($candidate, array_column(allInvoices(), 'number'), true));
    return $candidate;
}

/* ── Query ─────────────────────────────────────────────────────────────── */

/**
 * Everything the table asks for, applied to the array.
 *
 * This is the part worth reading. `Table` renders the search box, the filter
 * dropdown and the sort headers, and it puts `gk_search`, `gk_filter_<column>`,
 * `gk_sort`, `gk_dir` and `gk_page` into the URL — but it never touches your
 * data. Reading those parameters is the application's job, exactly as it would
 * be if the four blocks below were a WHERE, an ORDER BY and a LIMIT.
 *
 * @return array{rows: list<array>, total: int, pages: int, page: int}
 */
function queryInvoices(int $perPage = 8): array
{
    $rows = allInvoices();

    // WHERE … LIKE — the search box
    $search = trim((string) ($_GET['gk_search'] ?? ''));
    if ($search !== '') {
        $needle = mb_strtolower_compat($search);
        $rows = array_filter($rows, static function (array $r) use ($needle): bool {
            return str_contains(mb_strtolower_compat((string) $r['number']), $needle)
                || str_contains(mb_strtolower_compat((string) $r['customer']), $needle);
        });
    }

    // WHERE status = ? — the filter dropdown
    $status = (string) ($_GET['gk_filter_status'] ?? '');
    if ($status !== '') {
        $rows = array_filter($rows, static fn(array $r): bool => $r['status'] === $status);
    }

    // ORDER BY — the sortable column headers
    $sortable = ['number', 'customer', 'amount', 'issued', 'due'];
    $sort = (string) ($_GET['gk_sort'] ?? 'number');
    $dir  = strtolower((string) ($_GET['gk_dir'] ?? 'asc')) === 'desc' ? -1 : 1;
    if (in_array($sort, $sortable, true)) {
        usort($rows, static function (array $a, array $b) use ($sort, $dir): int {
            $x = $a[$sort];
            $y = $b[$sort];
            return $dir * (is_numeric($x) && is_numeric($y)
                ? (float) $x <=> (float) $y
                : strcasecmp((string) $x, (string) $y));
        });
    }

    // LIMIT / OFFSET — the pager
    $total = count($rows);
    $pages = max(1, (int) ceil($total / $perPage));
    $page  = min($pages, max(1, (int) ($_GET['gk_page'] ?? 1)));

    return [
        'rows'  => array_slice(array_values($rows), ($page - 1) * $perPage, $perPage),
        'total' => $total,
        'pages' => $pages,
        'page'  => $page,
    ];
}

/** Totals for the cards above the table — over all invoices, not the page. */
function invoiceTotals(): array
{
    $rows = allInvoices();
    $sum = static fn(callable $keep): float => array_sum(
        array_column(array_filter($rows, $keep), 'amount')
    );

    return [
        'count'   => count($rows),
        'total'   => array_sum(array_column($rows, 'amount')),
        'open'    => $sum(static fn($r) => in_array($r['status'], ['sent', 'overdue'], true)),
        'overdue' => $sum(static fn($r) => $r['status'] === 'overdue'),
    ];
}

/* ── Small helpers ─────────────────────────────────────────────────────── */

/** mb_strtolower where available; GridKit does not require mbstring. */
function mb_strtolower_compat(string $s): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
}

function e(mixed $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

/** Answer an AJAX form with the shape GridKit's form handler expects. */
function respond(array $payload): never
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
