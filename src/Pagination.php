<?php

declare(strict_types=1);

namespace GridKit;

/**
 * Pagination — EINE einheitliche, gefensterte Server-Pagination für das ganze System.
 *
 * Ersetzt alle bespoke Pager-Varianten (.bl-pager / .ssi-pagination / .gk-page-btn /
 * .gk-pagination …). Optik identisch zur client-seitigen GK.rowPager: nutzt dieselben
 * CSS-Klassen `.gk-rowpager` (Wrapper) + `.gk-pg` (Buttons) — kein eigenes/inline CSS.
 *
 * Darstellung: « Erste · ‹ Zurück · 1 … aktuell±2 … letzte · Weiter › · Letzte »
 * + Info „N Einträge · Seite X von Y".
 *
 * Verwendung:
 *   Pagination::render(['page'=>$p, 'totalPages'=>$tp, 'params'=>['filter'=>$f], 'total'=>$n]);
 *   Pagination::fromPaginator($paginator, ['params'=>['q'=>$q], 'label'=>'Belege']);
 */
final class Pagination
{
    /**
     * @param array{
     *   page?:int, totalPages?:int, baseUrl?:string, params?:array,
     *   pageParam?:string, total?:int|null, label?:string
     * } $o
     */
    public static function render(array $o): void
    {
        $page       = max(1, (int) ($o['page'] ?? 1));
        $totalPages = max(1, (int) ($o['totalPages'] ?? 1));
        if ($totalPages <= 1) return; // nichts zu blättern

        $base      = $o['baseUrl'] ?? strtok($_SERVER['REQUEST_URI'] ?? '', '?');
        $pageParam = $o['pageParam'] ?? 'page';
        $params    = $o['params'] ?? [];
        $total     = $o['total'] ?? null;
        $label     = $o['label'] ?? 'Einträge';

        $e = static fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
        $url = static function (int $p) use ($base, $pageParam, $params, $e): string {
            $q = array_filter(
                array_merge($params, [$pageParam => $p]),
                static fn($v) => $v !== '' && $v !== null
            );
            return $e($base . '?' . http_build_query($q));
        };

        // Gefenstertes Seitenset: 1 … aktuell±2 … letzte
        $win = 2;
        $set = [1, $totalPages];
        for ($i = $page - $win; $i <= $page + $win; $i++) {
            if ($i >= 1 && $i <= $totalPages) $set[] = $i;
        }
        $set = array_values(array_unique($set));
        sort($set);

        $icon = static fn(string $name, int $target, string $title, bool $on)
            => $on
                ? '<a class="gk-pg gk-pg-icon" href="' . $url($target) . '" title="' . $e($title) . '"><span class="material-icons">' . $name . '</span></a>'
                : '<span class="gk-pg gk-pg-icon gk-pg-off"><span class="material-icons">' . $name . '</span></span>';

        echo '<nav class="gk-rowpager" aria-label="Seitennavigation">';
        if ($total !== null) {
            echo '<span class="gk-rowpager-info">'
                . number_format((int) $total, 0, ',', '.') . ' ' . $e($label)
                . ' · Seite ' . $page . ' von ' . $totalPages . '</span>';
        }
        echo '<div class="gk-rowpager-nav">';
        echo $icon('first_page', 1, 'Erste Seite', $page > 1);
        echo $icon('chevron_left', $page - 1, 'Zurück', $page > 1);
        $prev = 0;
        foreach ($set as $p) {
            if ($prev && $p - $prev > 1) echo '<span class="gk-pg-gap">…</span>';
            echo '<a class="gk-pg' . ($p === $page ? ' gk-pg-active' : '') . '" href="' . $url($p) . '">' . $p . '</a>';
            $prev = $p;
        }
        echo $icon('chevron_right', $page + 1, 'Weiter', $page < $totalPages);
        echo $icon('last_page', $totalPages, 'Letzte Seite', $page < $totalPages);
        echo '</div></nav>';
    }

    /**
     * Bequemlichkeit für einen Paginator (SsiCore\Pagination\Paginator o.ä.) —
     * per Duck-Typing (currentPage/totalPages/total), ohne harte Abhängigkeit.
     */
    public static function fromPaginator(object $p, array $o = []): void
    {
        self::render(array_merge([
            'page'       => method_exists($p, 'currentPage') ? (int) $p->currentPage() : 1,
            'totalPages' => method_exists($p, 'totalPages') ? (int) $p->totalPages() : 1,
            'total'      => method_exists($p, 'total') ? (int) $p->total() : null,
        ], $o));
    }
}
