<?php

declare(strict_types=1);

namespace GridKit;

/**
 * PageSize — dropdown for choosing the number of rows per page (25 / 50 / 100 / …).
 *
 * Two modes:
 *  - live(<containerId>): renders a <select data-gk-live-input> — GK.liveTable
 *    handles reload + reset to page 1 automatically (collectParams drops "page").
 *  - otherwise (navigate): onchange navigates via a full reload, keeping other filters.
 *
 * The controller has to read the parameter (default "per_page"), check it against
 * a whitelist and use it as the LIMIT.
 *
 * Example (live):
 *   PageSize::make('per_page')->current($perPage)->live('exp-live')->render();
 *
 * Example (navigate):
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

    /** Live mode: bind to a data-gk-live-table container. */
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
     * Reads the chosen value from $_GET, checked against the options whitelist.
     * Falls back to $default when unset or invalid. Usable inside the controller.
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
            // Live mode: GK.liveTable binds the select automatically.
            echo '<select id="' . $selId . '" class="' . $e($this->selectClass) . '"'
                . ' name="' . $e($this->paramName) . '"'
                . ' data-gk-live-input="' . $e($this->liveTarget) . '">';
        } else {
            // Navigate mode: onchange keeps the other filters and reloads fully.
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
