<?php

declare(strict_types=1);

namespace GridKit;

/**
 * Pagination — ONE uniform, windowed server-side pagination for the whole system.
 *
 * Replaces every bespoke pager variant (.bl-pager / .ssi-pagination / .gk-page-btn /
 * .gk-pagination …). Looks identical to the client-side GK.rowPager: it uses the same
 * CSS classes `.gk-rowpager` (wrapper) + `.gk-pg` (buttons) — no own or inline CSS.
 *
 * Rendering: « First · ‹ Prev · 1 … current±2 … last · Next › · Last »
 * + the info "N entries · Page X of Y" + an optional PageSize dropdown.
 *
 * Placement: as a sibling BELOW `.gk-table-wrap` (not inside the card, not inside
 * the live container). Live updates: in the partial
 * `<template data-gk-replace="[data-gk-pager=ID]">` with the very same call.
 *
 * Usage:
 *   Pagination::render(['page'=>$p, 'totalPages'=>$tp, 'params'=>['filter'=>$f], 'total'=>$n]);
 *   Pagination::fromPaginator($paginator, ['params'=>['q'=>$q], 'label'=>'Belege', 'live'=>'exp-live']);
 */
final class Pagination
{
    /**
     * @param array{
     *   page?:int, totalPages?:int, baseUrl?:string, params?:array,
     *   pageParam?:string, total?:int|null, label?:string,
     *   live?:string, pageSize?:array|null
     * } $o
     */
    public static function render(array $o): void
    {
        echo self::build($o);
    }

    /**
     * HTML string (for `<template data-gk-replace>` in the live partial).
     *
     * @param array<string,mixed> $o
     */
    public static function build(array $o): string
    {
        $page       = max(1, (int) ($o['page'] ?? 1));
        $totalPages = max(1, (int) ($o['totalPages'] ?? 1));
        $pageSize   = is_array($o['pageSize'] ?? null) ? $o['pageSize'] : null;
        $live       = (string) ($o['live'] ?? '');

        $base      = $o['baseUrl'] ?? strtok($_SERVER['REQUEST_URI'] ?? '', '?');
        $pageParam = $o['pageParam'] ?? 'page';
        $params    = $o['params'] ?? [];
        $total     = $o['total'] ?? null;
        $label     = $o['label'] ?? Lang::t('pagination.entries');

        // Without paging and without a row-count selector: an empty placeholder, so
        // that a live replace reliably removes a pager that was visible before.
        if ($totalPages <= 1 && $pageSize === null) {
            return self::shell($live, true, '');
        }

        $e = static fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
        $url = static function (int $p) use ($base, $pageParam, $params, $e): string {
            $q = array_filter(
                array_merge($params, [$pageParam => $p]),
                static fn($v) => $v !== '' && $v !== null
            );
            return $e($base . '?' . http_build_query($q));
        };

        $inner = '';
        $inner .= '<span class="gk-rowpager-info">';
        if ($pageSize !== null) {
            $inner .= self::pageSizeHtml($pageSize);
        }
        if ($total !== null) {
            $inner .= '<span class="gk-rowpager-count">'
                . number_format((int) $total, 0, Lang::t('format.decimal'), Lang::t('format.thousands')) . ' ' . $e($label);
            if ($totalPages > 1) {
                $inner .= ' · ' . $e(Lang::t('pagination.page_of', ['page' => $page, 'total' => $totalPages]));
            }
            $inner .= '</span>';
        }
        $inner .= '</span>';

        if ($totalPages > 1) {
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

            $inner .= '<div class="gk-rowpager-nav">';
            $inner .= $icon('first_page', 1, Lang::t('pagination.first'), $page > 1);
            $inner .= $icon('chevron_left', $page - 1, Lang::t('pagination.prev'), $page > 1);
            $prev = 0;
            foreach ($set as $p) {
                if ($prev && $p - $prev > 1) $inner .= '<span class="gk-pg-gap">…</span>';
                $inner .= '<a class="gk-pg' . ($p === $page ? ' gk-pg-active' : '') . '" href="' . $url($p) . '">' . $p . '</a>';
                $prev = $p;
            }
            $inner .= $icon('chevron_right', $page + 1, Lang::t('pagination.next'), $page < $totalPages);
            $inner .= $icon('last_page', $totalPages, Lang::t('pagination.last'), $page < $totalPages);
            $inner .= '</div>';
        }

        return self::shell($live, false, $inner);
    }

    /**
     * Convenience for a paginator (SsiCore\Pagination\Paginator or similar) —
     * via duck typing (currentPage/totalPages/total), without a hard dependency.
     *
     * @param array<string,mixed> $o
     */
    public static function fromPaginator(object $p, array $o = []): void
    {
        echo self::fromPaginatorHtml($p, $o);
    }

    /**
     * @param array<string,mixed> $o
     */
    public static function fromPaginatorHtml(object $p, array $o = []): string
    {
        return self::build(array_merge([
            'page'       => method_exists($p, 'currentPage') ? (int) $p->currentPage() : 1,
            'totalPages' => method_exists($p, 'totalPages') ? (int) $p->totalPages() : 1,
            'total'      => method_exists($p, 'total') ? (int) $p->total() : null,
        ], $o));
    }

    private static function shell(string $live, bool $hidden, string $inner): string
    {
        $e = static fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
        $attr = ' class="gk-rowpager" data-gk-pager="' . $e($live) . '"';
        if ($live !== '') $attr .= ' data-gk-live-pager="' . $e($live) . '"';
        if ($hidden) $attr .= ' hidden';
        return '<nav' . $attr . ' aria-label="' . htmlspecialchars(Lang::t('pagination.aria'), ENT_QUOTES, 'UTF-8') . '">' . $inner . '</nav>';
    }

    /**
     * @param array{current?:int, live?:string, param?:string, options?:int[]} $cfg
     */
    private static function pageSizeHtml(array $cfg): string
    {
        $ps = PageSize::make((string) ($cfg['param'] ?? 'per_page'))
            ->current((int) ($cfg['current'] ?? 25));
        if (!empty($cfg['options']) && is_array($cfg['options'])) {
            $ps->options($cfg['options']);
        }
        if (!empty($cfg['live'])) {
            $ps->live((string) $cfg['live']);
        }
        ob_start();
        $ps->render();
        return (string) ob_get_clean();
    }
}
