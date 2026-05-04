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
    private string $mode = 'chips'; // 'chips' | 'dropdown'
    private ?array $allOption = null; // ['label' => 'Alle Jahre', 'value' => 0]
    private string $selectClass = 'gk-filter';

    public function __construct(string $id = 'year-filter', string $paramName = 'year')
    {
        $this->id = $id;
        $this->paramName = $paramName;
        // Wenn URL-Param gesetzt: nimm den. Sonst:
        // - currentYear = 0 als „nicht entschieden". Wird in render() finalisiert,
        //   abhängig davon ob allOption gesetzt ist (dann 0 = „Alle Jahre"-Default)
        //   oder nicht (dann date('Y') = aktuelles Jahr).
        $this->currentYear = isset($_GET[$paramName]) && $_GET[$paramName] !== ''
            ? (int)$_GET[$paramName]
            : -1;
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

    public function mode(string $mode): static
    {
        $this->mode = $mode === 'dropdown' ? 'dropdown' : 'chips';
        return $this;
    }

    /**
     * Fügt eine "Alle"-Option am Anfang des Dropdowns ein.
     * Der Controller muss den $value-Wert (default 0) als "kein Filter" interpretieren.
     */
    public function allOption(string $label = 'Alle Jahre', int $value = 0): static
    {
        $this->allOption = ['label' => $label, 'value' => $value];
        return $this;
    }

    /**
     * Setzt eine abweichende CSS-Klasse für das <select>. Nützlich, um das Dropdown
     * in eine bestehende Toolbar (z.B. .gk-toolbar > .gk-filter) zu integrieren.
     */
    public function selectClass(string $class): static
    {
        $this->selectClass = $class;
        return $this;
    }

    public function current(): int
    {
        return $this->resolveYear();
    }

    /**
     * currentYear finalisieren: -1 bedeutet „URL-Param war nicht gesetzt".
     * Mit allOption → Default = allOption-Wert (typisch 0 = „Alle Jahre").
     * Ohne allOption → Default = aktuelles Jahr.
     */
    private function resolveYear(): int
    {
        if ($this->currentYear === -1) {
            $this->currentYear = $this->allOption !== null
                ? (int)$this->allOption['value']
                : (int)date('Y');
        }
        return $this->currentYear;
    }

    public function render(): void
    {
        if (empty($this->years)) return;

        $this->resolveYear();

        $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

        $params = [];
        foreach ($this->preserveParams as $p) {
            if (isset($_GET[$p]) && $_GET[$p] !== '') {
                $params[$p] = $_GET[$p];
            }
        }
        $base = $this->baseUrl ?: strtok($_SERVER['REQUEST_URI'] ?? '', '?');

        if ($this->mode === 'dropdown') {
            $selectId = $e($this->id) . '-select';
            echo '<select id="' . $selectId . '" class="' . $e($this->selectClass) . '" data-gk-years="' . $e($this->id) . '" onchange="(function(s){';
            echo 'var u=new window.URL(s.dataset.base,window.location.origin);';
            echo 'var pres=JSON.parse(s.dataset.preserve||\'{}\');';
            echo 'Object.keys(pres).forEach(function(k){u.searchParams.set(k,pres[k]);});';
            echo 'u.searchParams.set(s.dataset.param,s.value);';
            echo 'window.location.href=u.toString();';
            echo '})(this)"';
            echo ' data-base="' . $e($base) . '"';
            echo ' data-param="' . $e($this->paramName) . '"';
            echo ' data-preserve="' . $e(json_encode((object)$params, JSON_UNESCAPED_SLASHES)) . '">';
            if ($this->allOption !== null) {
                $allVal = (int)$this->allOption['value'];
                $sel = $allVal === $this->currentYear ? ' selected' : '';
                echo '<option value="' . $allVal . '"' . $sel . '>' . $e($this->allOption['label']) . '</option>';
            }
            foreach ($this->years as $year) {
                $year = (int)$year;
                $sel = $year === $this->currentYear ? ' selected' : '';
                echo '<option value="' . $year . '"' . $sel . '>' . $year . '</option>';
            }
            echo '</select>';
            return;
        }

        echo '<div class="gk-year-filter" data-gk-years="' . $e($this->id) . '">';
        foreach ($this->years as $year) {
            $year = (int)$year;
            $isActive = $year === $this->currentYear;
            $cls = 'gk-chip gk-chip-sm';
            if ($isActive) $cls .= ' gk-chip-active';

            $params[$this->paramName] = $year;
            $url = $base . '?' . http_build_query($params);

            echo '<a href="' . $e($url) . '" class="' . $cls . '">' . $year . '</a>';
        }
        echo '</div>';
    }
}
