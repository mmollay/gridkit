<?php

declare(strict_types=1);

namespace GridKit;

class YearFilter
{
    private string $id;
    private string $paramName;
    private array $years = [];
    private int $currentYear;
    private string $baseUrl = '';
    private array $preserveParams = [];

    public function __construct(string $id = 'year-filter', string $paramName = 'year')
    {
        $this->id = $id;
        $this->paramName = $paramName;
        $this->currentYear = (int)($_GET[$paramName] ?? date('Y'));
    }

    public function years(array $years): static
    {
        $this->years = $years;
        return $this;
    }

    public function range(int $from, int $to): static
    {
        $this->years = range($to, $from);
        return $this;
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

    public function current(): int
    {
        return $this->currentYear;
    }

    public function render(): void
    {
        if (empty($this->years)) return;

        $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

        echo '<div class="gk-year-filter" data-gk-years="' . $e($this->id) . '">';
        foreach ($this->years as $year) {
            $year = (int)$year;
            $isActive = $year === $this->currentYear;
            $cls = 'gk-chip gk-chip-sm';
            if ($isActive) $cls .= ' gk-chip-active';

            $params = [];
            foreach ($this->preserveParams as $p) {
                if (isset($_GET[$p]) && $_GET[$p] !== '') {
                    $params[$p] = $_GET[$p];
                }
            }
            $params[$this->paramName] = $year;
            $url = ($this->baseUrl ?: strtok($_SERVER['REQUEST_URI'] ?? '', '?')) . '?' . http_build_query($params);

            echo '<a href="' . $e($url) . '" class="' . $cls . '">' . $year . '</a>';
        }
        echo '</div>';
    }
}
