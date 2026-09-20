<?php

declare(strict_types=1);

namespace GridKit;

class StatCards
{
    private string $id;
    private array $cards = [];

    public function __construct(string $id = 'stats')
    {
        $this->id = $id;
    }

    public function card(string $label, string|int|float $value, array $opts = []): static
    {
        $this->cards[] = ['label' => $label, 'value' => $value, ...$opts];
        return $this;
    }

    public function render(): void
    {
        $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

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

            // Format value
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
                    'percent' => (isset($card['decimals'])
                        ? number_format((float) $val, (int) $card['decimals'], $dec, $thou)
                        : (is_numeric($val) ? str_replace('.', $dec, (string) $val) : (string) $val)) . ' %',
                    default   => (string) $val,
                };
            }
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
}
