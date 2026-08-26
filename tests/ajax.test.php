<?php
/**
 * The server-side AJAX path.
 *
 * This is the feature the README leads with — "search, sort, filter and paging
 * run over AJAX" — and until now nothing exercised it: every table in the demo
 * uses setData(), which is the client-side path. The reload injects the raw
 * response into the table wrapper, so a page that answers with its whole
 * layout injects a sidebar and a header inside its own table.
 */

declare(strict_types=1);

use GridKit\{Lang, Table};

/** Pretend the browser is asking for one table. */
function asAjaxFor(?string $id): void
{
    $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
    if ($id === null) {
        unset($_GET['gk_table']);
    } else {
        $_GET['gk_table'] = $id;
    }
}

function asNormalRequest(): void
{
    unset($_SERVER['HTTP_X_REQUESTED_WITH'], $_GET['gk_table']);
}

/** @return array<string,callable> */
return [

'isAjaxReload recognises a reload of one table' => function (): void {
    asAjaxFor('invoices');
    T::ok(Table::isAjaxReload('invoices'), 'the table being asked for');
    T::ok(Table::isAjaxReload(), 'any table, when no id is given');
    T::ok(!Table::isAjaxReload('customers'), 'a different table on the same page');
    asNormalRequest();
},

'a normal page load is never a reload' => function (): void {
    asNormalRequest();
    T::ok(!Table::isAjaxReload('invoices'), 'no AJAX header, no gk_table');

    // The header alone is not enough: AJAX navigation and modal loads send it too.
    $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
    T::ok(!Table::isAjaxReload(), 'AJAX header without gk_table is some other request');
    asNormalRequest();
},

'the header comparison is case-insensitive' => function (): void {
    $_SERVER['HTTP_X_REQUESTED_WITH'] = 'xmlhttprequest';
    $_GET['gk_table'] = 'invoices';
    T::ok(Table::isAjaxReload('invoices'), 'lowercase header still counts');
    asNormalRequest();
},

'a reload renders the fragment, a page load renders the wrapper' => function (): void {
    Lang::set('en');
    $build = static fn(): Table => (new Table('invoices'))
        ->setData([['id' => 1, 'name' => 'Widget']])
        ->column('name', 'Product');

    asNormalRequest();
    $full = T::capture(fn() => $build()->render());
    T::contains($full, 'data-gk-table="invoices"', 'a page load emits the wrapper');

    asAjaxFor('invoices');
    $fragment = T::capture(fn() => $build()->render());
    T::notContains($fragment, 'data-gk-table="invoices"',
        'a reload must not repeat the wrapper — it is replaced inside it');
    T::contains($fragment, 'Widget', 'the rows are still there');
    T::ok(strlen($fragment) < strlen($full), 'the fragment is smaller than the page');
    asNormalRequest();
},

'a table ignores a reload meant for another table' => function (): void {
    Lang::set('en');
    asAjaxFor('customers');
    $html = T::capture(fn() => (new Table('invoices'))
        ->setData([['id' => 1, 'name' => 'Widget']])
        ->column('name', 'Product')
        ->render());
    T::contains($html, 'data-gk-table="invoices"',
        'the other table on the page renders normally');
    asNormalRequest();
},


'search and filters both reach the query' => function (): void {
    // buildWhere() is private on purpose — it is an implementation detail of
    // loadData(). Reaching it by reflection beats requiring a live MySQL
    // connection to prove that a filter dropdown changes the result at all.
    $where = static function (Table $t): array {
        // No setAccessible(): a no-op since PHP 8.1 and deprecated in 8.5.
        return (new \ReflectionMethod(Table::class, 'buildWhere'))->invoke($t);
    };

    $build = static fn(): Table => (new Table('invoices'))
        ->search(['number', 'customer'])
        ->column('number', 'No.')
        ->column('status', 'Status')
        ->filter('status', 'select', ['options' => ['paid' => 'Paid']])
        ->filter('year',   'select', ['options' => ['2026' => '2026']]);

    unset($_GET['gk_search'], $_GET['gk_filter_status'], $_GET['gk_filter_year']);
    [$clauses] = $where($build());
    T::eq($clauses, [], 'nothing selected adds no clause');

    $_GET['gk_search'] = 'nord';
    [$clauses, $params, $types] = $where($build());
    T::eq($clauses, ['(`number` LIKE ? OR `customer` LIKE ?)'], 'search is one OR group');
    T::eq($params, ['%nord%', '%nord%'], 'both columns bound');
    T::eq($types, 'ss', 'bound as strings, never interpolated');

    // The regression this guards: the dropdown rendered, put gk_filter_status
    // in the URL, and the query ignored it — the list did not change.
    $_GET['gk_filter_status'] = 'paid';
    [$clauses, $params, $types] = $where($build());
    T::eq($clauses[1] ?? null, '`status` = ?', 'the filter becomes a clause');
    T::eq($params, ['%nord%', '%nord%', 'paid'], 'the filter value is bound');
    T::eq($types, 'sss', 'three bound parameters');

    $_GET['gk_filter_year'] = '2026';
    [$clauses] = $where($build());
    T::eq(count($clauses), 3, 'a second filter is another AND');

    // A filter the table never declared must not reach the query.
    $_GET['gk_filter_secret'] = 'x';
    [$clauses] = $where($build());
    T::eq(count($clauses), 3, 'an undeclared filter is ignored');

    unset($_GET['gk_search'], $_GET['gk_filter_status'],
          $_GET['gk_filter_year'], $_GET['gk_filter_secret']);
},

'the filter dropdown keeps the value it was given' => function (): void {
    Lang::set('en');
    $_GET['gk_filter_status'] = 'paid';
    $html = T::capture(fn() => (new Table('t'))
        ->setData([['id' => 1, 'status' => 'paid']])
        ->column('status', 'Status')
        ->filter('status', 'select', ['options' => ['paid' => 'Paid', 'open' => 'Open']])
        ->render());
    T::contains($html, '<option value="paid" selected>',
        'a shared or reloaded URL must not snap the dropdown back to All');
    T::notContains($html, '<option value="open" selected>', 'only the active one');
    unset($_GET['gk_filter_status']);
},


'rows() keeps the table server-driven, setData() does not' => function (): void {
    Lang::set('en');
    asNormalRequest();

    $page = [['id' => 1, 'name' => 'A'], ['id' => 2, 'name' => 'B']];

    $server = T::capture(fn() => (new Table('srv'))
        ->rows($page, 57)->column('name', 'Name')->paginate(2)->render());
    $client = T::capture(fn() => (new Table('cli'))
        ->setData($page)->column('name', 'Name')->paginate(2)->render());

    T::notContains($server, 'data-gk-static',
        'rows() must not mark the table static — the browser has one page, not the set');
    T::contains($client, 'data-gk-static', 'setData() is the client-side path');

    // 57 rows at 2 per page is 29 pages. A static table would only ever know
    // about the two rows it was handed.
    preg_match_all('/data-gk-page="(\d+)"/', $server, $m);
    T::ok(in_array('29', $m[1], true), 'the pager knows about page 29 — the reported total was used');
},

'rows() refuses to invent a negative total' => function (): void {
    asNormalRequest();
    $html = T::capture(fn() => (new Table('t'))
        ->rows([], -5)->column('name', 'Name')->render());
    T::ok(!str_contains($html, '-5'), 'a negative total is clamped, not printed');
},


'the pager windows instead of printing every page' => function (): void {
    Lang::set('en');
    asNormalRequest();

    $render = static fn(int $total, int $perPage): string => T::capture(
        fn() => (new Table('t'))
            ->rows([['id' => 1, 'name' => 'A']], $total)
            ->column('name', 'Name')
            ->paginate($perPage)
            ->render()
    );

    // 10,000 rows at 25 a page is 400 pages. Printing them all put 400 buttons
    // in the DOM on every reload — at exactly the size the server-side path
    // exists for.
    $html = $render(10000, 25);
    preg_match_all('/data-gk-page="(\d+)"/', $html, $m);
    T::ok(count($m[1]) <= 10, 'a 400-page list must not render 400 buttons, got ' . count($m[1]));
    T::ok(in_array('1', $m[1], true),   'the first page stays reachable');
    T::ok(in_array('400', $m[1], true), 'so does the last');
    T::contains($html, 'gk-pg-gap', 'the skipped range is marked');

    // Short lists keep every page and need no gap.
    $short = $render(9, 3);
    preg_match_all('/data-gk-page="(\d+)"/', $short, $m);
    T::ok(in_array('2', $m[1], true), 'three pages: all of them shown');
    T::notContains($short, 'gk-pg-gap', 'nothing was skipped, so no ellipsis');

    // One page: no pager at all.
    T::notContains($render(3, 25), 'gk-pagination', 'a single page needs no pager');
},

];
