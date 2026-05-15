<?php

declare(strict_types=1);

namespace GridKit;

/**
 * SortLink: server-seitige sortierbare Tabellen-Header.
 *
 * Erzeugt HTML-Links mit Material-Icons (gk-sort-icon), die beim Klick
 * den URL-Parameter `sort` + `dir` toggeln und alle anderen Filter
 * erhalten (URL-encoded).
 *
 * Verwendung:
 *
 *   echo SortLink::header('invoice_date', 'Datum', [
 *       'current_sort' => $sort,      // aktuell sortierte Spalte
 *       'current_dir'  => $dir,       // 'asc' oder 'desc'
 *       'base_url'     => '/faktura/invoices',
 *       'preserve'     => ['q' => $q, 'year' => $year, 'status' => $status],
 *   ]);
 *
 * Alternativ Fluent-Style (wenn mehrere Spalten denselben Context teilen):
 *
 *   $sl = SortLink::context('/faktura/invoices', $sort, $dir, ['q'=>$q,'year'=>$year]);
 *   echo $sl('invoice_date', 'Datum');
 *   echo $sl('customer_name', 'Kunde');
 *   echo $sl('gross_total', 'Brutto', 'gk-text-right');
 */
class SortLink
{
    /**
     * @param string $key       Spalten-Key (z.B. 'invoice_date')
     * @param string $label     Sichtbarer Spaltentitel
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
        // URL-Parameter-Namen (Default 'sort' und 'dir'). Override wenn z.B. 'dir'
        // schon für anderen Zweck belegt ist (z.B. Banking-Automatik:
        // dir=income/expense für Richtungs-Filter, daher sort_dir_param='sdir').
        $sortParam   = (string)($opts['sort_param'] ?? 'sort');
        $dirParam    = (string)($opts['dir_param']  ?? 'dir');

        // Filter-Params säubern (leere/0-Werte weglassen)
        $params = [];
        foreach ($preserve as $k => $v) {
            if ($v === null || $v === '' || $v === 0 || $v === '0' || $v === false) continue;
            $params[$k] = $v;
        }

        $isActive = $currentSort === $key;
        // Toggle: wenn schon aktiv und asc → desc, sonst asc
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
            . '<span class="material-icons ' . $iconCls . '">' . $iconName . '</span>'
            . '</a>';
    }

    /**
     * Closure-Variante: ein Mal Context binden, dann pro Spalte aufrufen.
     *
     * Rückgabe ist ein Callable mit Signatur (string $key, string $label, string $extraClass = ''): string
     */
    public static function context(
        string $baseUrl,
        ?string $currentSort,
        ?string $currentDir,
        array $preserve = [],
        array $opts = []
    ): \Closure {
        // $opts kann sort_param + dir_param überschreiben (z.B. dir_param='sdir').
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
