<?php

declare(strict_types=1);

namespace GridKit;

/**
 * SortLink: server-side sortable table headers.
 *
 * Builds HTML links with Material icons (gk-sort-icon) that on click toggle
 * the URL parameters `sort` + `dir` and preserve all the other filters
 * (URL-encoded).
 *
 * Usage:
 *
 *   echo SortLink::header('invoice_date', 'Date', [
 *       'current_sort' => $sort,      // currently sorted column
 *       'current_dir'  => $dir,       // 'asc' or 'desc'
 *       'base_url'     => '/invoices',
 *       'preserve'     => ['q' => $q, 'year' => $year, 'status' => $status],
 *   ]);
 *
 * Alternatively fluent style (when several columns share the same context):
 *
 *   $sl = SortLink::context('/invoices', $sort, $dir, ['q'=>$q,'year'=>$year]);
 *   echo $sl('invoice_date', 'Date');
 *   echo $sl('customer_name', 'Kunde');
 *   echo $sl('gross_total', 'Brutto', 'gk-text-right');
 */
class SortLink
{
    /**
     * @param string $key       Column key (e.g. 'invoice_date')
     * @param string $label     Visible column title
     * @param array{
     *   current_sort?: string|null,
     *   current_dir?: string|null,
     *   base_url?: string,
     *   preserve?: array<string, mixed>,
     *   extra_class?: string,
     * } $opts
     */
    public static function header(string $key, string $label, array $opts = []): string
    {
        $currentSort = (string)($opts['current_sort'] ?? '');
        $currentDir  = strtolower((string)($opts['current_dir'] ?? 'desc'));
        $baseUrl     = (string)($opts['base_url'] ?? '');
        $preserve    = (array)($opts['preserve'] ?? []);
        $extraClass  = (string)($opts['extra_class'] ?? '');
        // URL parameter names (default 'sort' and 'dir'). Override them when 'dir'
        // is already taken for another purpose (e.g. the banking automation uses
        // dir=income/expense as a direction filter, hence dir_param='sdir').
        $sortParam   = (string)($opts['sort_param'] ?? 'sort');
        $dirParam    = (string)($opts['dir_param']  ?? 'dir');

        // Clean up the filter params (drop empty and 0 values)
        $params = [];
        foreach ($preserve as $k => $v) {
            if ($v === null || $v === '' || $v === 0 || $v === '0' || $v === false) continue;
            $params[$k] = $v;
        }

        $isActive = $currentSort === $key;
        // Toggle: if already active and asc → desc, otherwise asc
        $nextDir  = ($isActive && $currentDir === 'asc') ? 'desc' : 'asc';
        $params[$sortParam] = $key;
        $params[$dirParam]  = $nextDir;

        $iconName = $isActive
            ? ($currentDir === 'asc' ? 'arrow_upward' : 'arrow_downward')
            : 'unfold_more';
        $iconCls  = $isActive ? 'gk-sort-icon is-active' : 'gk-sort-icon';

        $linkCls  = 'gk-sort-link' . ($extraClass ? ' ' . $extraClass : '');
        $href     = $baseUrl . '?' . http_build_query($params);

        return '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" class="' . $linkCls . '">'
            . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
            . '<span class="material-icons ' . $iconCls . '" aria-hidden="true">' . $iconName . '</span>'
            . '</a>';
    }

    /**
     * Closure variant: bind the context once, then call it once per column.
     *
     * Returns a callable with the signature (string $key, string $label, string $extraClass = ''): string
     */
    public static function context(
        string $baseUrl,
        ?string $currentSort,
        ?string $currentDir,
        array $preserve = [],
        array $opts = []
    ): \Closure {
        // $opts can override sort_param + dir_param (e.g. dir_param='sdir').
        return function (string $key, string $label, string $extraClass = '') use ($baseUrl, $currentSort, $currentDir, $preserve, $opts): string {
            return self::header($key, $label, array_merge($opts, [
                'current_sort' => $currentSort,
                'current_dir'  => $currentDir,
                'base_url'     => $baseUrl,
                'preserve'     => $preserve,
                'extra_class'  => $extraClass,
            ]));
        };
    }
}
