<?php

declare(strict_types=1);

namespace GridKit;

class StatCards
{
    private string $id;
    private array $cards = [];
    private bool $compact = false;

    public function __construct(string $id = 'stats')
    {
        $this->id = $id;
    }

    public function card(string $label, string|int|float $value, array $opts = []): static
    {
        $this->cards[] = ['label' => $label, 'value' => $value, ...$opts];
        return $this;
    }

    /**
     * Compact tiles instead of cards (1.92.0): a figure over its word, small
     * enough for a side sheet or a card — .gk-stat-tiles / .gk-stat-tile. The
     * cards stay the block for an overview page. Each card() becomes a tile:
     * 'format' and 'decimals' as for a card, 'sub' a quiet third line, 'href'
     * a link, and 'color' => 'danger' or 'warning' ('red', 'orange') for the
     * one figure that needs acting on — any other colour stays neutral, because
     * a colour that means nothing costs the one that does (rule 4 for lists).
     * 'icon' and 'trend' belong to the full card and are not shown.
     */
    public function compact(bool $enabled = true): static
    {
        $this->compact = $enabled;
        return $this;
    }

    public function render(): void
    {
        $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

        if ($this->compact) {
            $this->renderTiles($e);
            return;
        }

        echo '<div class="gk-stat-cards" data-gk-stats="' . $e($this->id) . '">';
        foreach ($this->cards as $card) {
            $cls = 'gk-stat-card';
            if (isset($card['color'])) $cls .= ' gk-stat-' . $card['color'];
            if (isset($card['href'])) {
                echo '<a href="' . $e($card['href']) . '" class="' . $cls . '">';
            } else {
                echo '<div class="' . $cls . '">';
            }

            echo '<div class="gk-stat-content">';
            echo '<span class="gk-stat-label">' . $e($card['label']) . '</span>';

            $val = self::formatted($card);
            $valCls = 'gk-stat-value';
            if (isset($card['highlight']) && $card['highlight']) $valCls .= ' gk-stat-highlight';
            echo '<span class="' . $valCls . '">' . $e((string)$val) . '</span>';

            // The trend indicator. README.md sells the whole component on it —
            // "KPI tiles with trend" — the landing page shows the call, the
            // changelog announced it, and render() never read the option: the
            // string appeared nowhere in the output and no CSS rule existed.
            // A leading minus reads as a fall, anything else as a rise.
            if (isset($card['trend']) && (string) $card['trend'] !== '') {
                $trend = (string) $card['trend'];
                $dir   = str_starts_with(ltrim($trend), '-') ? 'down' : 'up';
                echo '<span class="gk-stat-trend gk-stat-trend-' . $dir . '">' . $e($trend) . '</span>';
            }
            echo '</div>';

            if (isset($card['icon'])) {
                echo '<span class="gk-stat-icon material-icons" aria-hidden="true">' . $e($card['icon']) . '</span>';
            }

            echo isset($card['href']) ? '</a>' : '</div>';
        }
        echo '</div>';
    }

    /**
     * The value as the card or tile shows it — one formatting for both, the
     * same locale keys as Table's column formats.
     */
    private static function formatted(array $card): string
    {
        $val = $card['value'];
        if (isset($card['format'])) {
            // Same locale keys as Table's column formats, so a card and
            // the column below it never disagree about what a number
            // looks like.
            $dec   = Lang::t('format.decimal');
            $thou  = Lang::t('format.thousands');
            $val = match ($card['format']) {
                'currency' => str_replace(
                    '{value}',
                    number_format((float) $val, 2, $dec, $thou),
                    $card['currency'] ?? Lang::t('format.currency')
                ),
                // (float) and 'decimals', as Table does it. This cut the value
                // off with (int): 1999.9 read "1.999" on the card and "2.000"
                // in the column under it.
                'number'  => number_format((float) $val, (int) ($card['decimals'] ?? 0), $dec, $thou),
                // The decimal sign follows the locale, as the skill says of all
                // three formats; it used to be a dot under every locale. Without
                // 'decimals' the digits stay exactly as passed.
                'percent' => Table::percent($val, isset($card['decimals']) ? (int) $card['decimals'] : null),
                default   => (string) $val,
            };
        }
        return (string) $val;
    }

    /** The compact form: .gk-stat-tiles, one .gk-stat-tile per card(). */
    private function renderTiles(\Closure $e): void
    {
        echo '<div class="gk-stat-tiles" data-gk-stats="' . $e($this->id) . '">';
        foreach ($this->cards as $card) {
            $tone = match ((string) ($card['color'] ?? '')) {
                'danger', 'red'     => ' gk-stat-tile-danger',
                'warning', 'orange' => ' gk-stat-tile-warning',
                default             => '',
            };
            if ($tone === '' && !empty($card['highlight'])) $tone = ' gk-stat-tile-danger';
            $inner = '<span class="gk-stat-tile-value">' . $e(self::formatted($card)) . '</span>'
                   . '<span class="gk-stat-tile-label">' . $e($card['label']) . '</span>';
            if (isset($card['sub']) && (string) $card['sub'] !== '') {
                $inner .= '<span class="gk-stat-tile-sub">' . $e($card['sub']) . '</span>';
            }
            echo isset($card['href'])
                ? '<a href="' . $e($card['href']) . '" class="gk-stat-tile' . $tone . '">' . $inner . '</a>'
                : '<div class="gk-stat-tile' . $tone . '">' . $inner . '</div>';
        }
        echo '</div>';
    }
}
