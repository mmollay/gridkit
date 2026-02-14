<?php

declare(strict_types=1);

namespace GridKit;

class FilterChips
{
    private string $id;
    private string $paramName;
    private array $chips = [];
    private string $currentValue = '';
    private string $baseUrl = '';
    private array $preserveParams = [];

    public function __construct(string $id, string $paramName = 'status')
    {
        $this->id = $id;
        $this->paramName = $paramName;
        $this->currentValue = $_GET[$paramName] ?? '';
    }

    public function baseUrl(string $url): static
    {
        $this->baseUrl = $url;
        return $this;
    }

    public function preserve(array $params): static
    {
        $this->preserveParams = $params;
        return $this;
    }

    public function chip(string $value, string $label, array $opts = []): static
    {
        $this->chips[] = ['value' => $value, 'label' => $label, ...$opts];
        return $this;
    }

    public function render(): void
    {
        $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

        echo '<div class="gk-root gk-filter-chips" data-gk-chips="' . $e($this->id) . '">';
        foreach ($this->chips as $chip) {
            $val = $chip['value'];
            $isActive = $this->currentValue === $val;
            $cls = 'gk-chip';
            if ($isActive) $cls .= ' gk-chip-active';
            if (isset($chip['color'])) $cls .= ' gk-chip-' . $chip['color'];

            // Build URL
            $params = [];
            foreach ($this->preserveParams as $p) {
                if (isset($_GET[$p]) && $_GET[$p] !== '') {
                    $params[$p] = $_GET[$p];
                }
            }
            if ($val !== '') {
                $params[$this->paramName] = $val;
            }
            $url = $this->baseUrl ?: strtok($_SERVER['REQUEST_URI'] ?? '', '?');
            if ($params) {
                $url .= '?' . http_build_query($params);
            }

            echo '<a href="' . $e($url) . '" class="' . $cls . '">';
            if (isset($chip['icon'])) {
                echo '<span class="gk-chip-icon material-icons">' . $e($chip['icon']) . '</span>';
            }
            if (isset($chip['count'])) {
                echo $e($chip['label']) . ' <span class="gk-chip-count">' . $e((string)$chip['count']) . '</span>';
            } else {
                echo $e($chip['label']);
            }
            echo '</a>';
        }
        echo '</div>';
    }
}
