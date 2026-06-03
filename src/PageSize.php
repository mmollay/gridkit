<?php

declare(strict_types=1);

namespace GridKit;

/**
 * PageSize — Dropdown zur Wahl der Zeilenanzahl pro Seite (25 / 50 / 100 / …).
 *
 * Zwei Modi:
 *  - live(<containerId>): rendert ein <select data-gk-live-input> — GK.liveTable
 *    übernimmt Reload + Reset auf Seite 1 automatisch (collectParams löscht "page").
 *  - sonst (navigate): onchange navigiert per Voll-Reload und erhält andere Filter.
 *
 * Der Controller muss den Parameter (default "per_page") lesen, gegen eine
 * Whitelist prüfen und als LIMIT verwenden.
 *
 * Beispiel (live):
 *   PageSize::make('per_page')->current($perPage)->live('exp-live')->render();
 *
 * Beispiel (navigate):
 *   PageSize::make('per_page')->current($perPage)->baseUrl('/faktura/expenses')
 *       ->preserve(['q','status'])->render();
 */
final class PageSize
{
    private string $paramName;
    private int $current = 25;
    private array $options = [25, 50, 100, 200];
    private string $liveTarget = '';
    private string $baseUrl = '';
    private array $preserveParams = [];
    private string $label = 'Zeilen';
    private string $selectClass = 'gk-filter gk-pagesize-select';

    public function __construct(string $paramName = 'per_page')
    {
        $this->paramName = $paramName;
    }

    public static function make(string $paramName = 'per_page'): static
    {
        return new static($paramName);
    }

    public function options(array $options): static
    {
        $this->options = array_values(array_unique(array_map('intval', $options)));
        sort($this->options);
        return $this;
    }

    public function current(int $current): static
    {
        $this->current = $current;
        return $this;
    }

    /** Live-Modus: an einen data-gk-live-table-Container binden. */
    public function live(string $containerId): static
    {
        $this->liveTarget = $containerId;
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

    public function label(string $label): static
    {
        $this->label = $label;
        return $this;
    }

    public function selectClass(string $class): static
    {
        $this->selectClass = $class;
        return $this;
    }

    /**
     * Liest den gewählten Wert aus $_GET gegen die Optionen-Whitelist.
     * Fällt auf $default zurück, wenn nicht gesetzt/ungültig. Im Controller nutzbar.
     */
    public function resolve(int $default = 25): int
    {
        $v = isset($_GET[$this->paramName]) ? (int) $_GET[$this->paramName] : 0;
        return in_array($v, $this->options, true) ? $v : $default;
    }

    public function render(): void
    {
        $e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
        $selId = 'gk-pagesize-' . $e($this->paramName);

        echo '<label class="gk-pagesize" for="' . $selId . '">';
        if ($this->label !== '') {
            echo '<span class="gk-pagesize-text">' . $e($this->label) . '</span>';
        }

        if ($this->liveTarget !== '') {
            // Live-Modus: GK.liveTable bindet das Select automatisch.
            echo '<select id="' . $selId . '" class="' . $e($this->selectClass) . '"'
                . ' name="' . $e($this->paramName) . '"'
                . ' data-gk-live-input="' . $e($this->liveTarget) . '">';
        } else {
            // Navigate-Modus: onchange erhält andere Filter und lädt voll neu.
            $params = [];
            foreach ($this->preserveParams as $p) {
                if (isset($_GET[$p]) && $_GET[$p] !== '') $params[$p] = $_GET[$p];
            }
            $base = $this->baseUrl ?: strtok($_SERVER['REQUEST_URI'] ?? '', '?');
            echo '<select id="' . $selId . '" class="' . $e($this->selectClass) . '"'
                . ' data-base="' . $e($base) . '"'
                . ' data-param="' . $e($this->paramName) . '"'
                . ' data-preserve="' . $e(json_encode((object) $params, JSON_UNESCAPED_SLASHES)) . '"'
                . ' onchange="(function(s){var u=new window.URL(s.dataset.base,window.location.origin);'
                . 'var pres=JSON.parse(s.dataset.preserve||\'{}\');'
                . 'Object.keys(pres).forEach(function(k){u.searchParams.set(k,pres[k]);});'
                . 'u.searchParams.set(s.dataset.param,s.value);u.searchParams.delete(\'page\');'
                . 'window.location.href=u.toString();})(this)">';
        }

        foreach ($this->options as $opt) {
            $sel = $opt === $this->current ? ' selected' : '';
            echo '<option value="' . $opt . '"' . $sel . '>' . $opt . '</option>';
        }
        echo '</select></label>';
    }
}
