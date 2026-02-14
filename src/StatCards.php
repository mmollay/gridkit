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

        echo '<div class="gk-root gk-stat-cards" data-gk-stats="' . $e($this->id) . '">';
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
                $val = match ($card['format']) {
                    'currency' => number_format((float)$val, 2, ',', '.') . ' €',
                    'number' => number_format((int)$val, 0, ',', '.'),
                    'percent' => $val . ' %',
                    default => (string)$val,
                };
            }
            $valCls = 'gk-stat-value';
            if (isset($card['highlight']) && $card['highlight']) $valCls .= ' gk-stat-highlight';
            echo '<span class="' . $valCls . '">' . $e((string)$val) . '</span>';
            echo '</div>';

            if (isset($card['icon'])) {
                echo '<span class="gk-stat-icon material-icons">' . $e($card['icon']) . '</span>';
            }

            echo isset($card['href']) ? '</a>' : '</div>';
        }
        echo '</div>';
    }
}
