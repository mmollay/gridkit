<?php

declare(strict_types=1);

namespace GridKit;

class FilterChips
{
    private string $id;
    private string $paramName;
    private array $chips = [];
    private string $currentValue = '';
    private ?string $defaultValue = null;
    private string $baseUrl = '';
    private array $preserveParams = [];

    public function __construct(string $id, string $paramName = 'status')
    {
        $this->id = $id;
        $this->paramName = $paramName;
        // is_string: ?status[]=x is an array, and assigning that to a string
        // property is a TypeError — one hand-typed link, one server error.
        $value = $_GET[$paramName] ?? '';
        $this->currentValue = is_string($value) ? $value : '';
    }

    /**
     * Which chip is active, when the page's default is not the empty value.
     *
     * The constructor reads the parameter out of the URL, and a page reached
     * without that parameter therefore highlights nothing — even though it is
     * showing one of the filters. The SSI Panel's website watch defaults to
     * "disturbed only": the list was filtered, no chip was lit, and the filter
     * row read as decoration (Martin, 16.09.2026).
     */
    public function current(string $value): static
    {
        // Decided in render(), once every chip is known: the call may come
        // before the chips, and whether the URL's value is one of them matters.
        $this->defaultValue = $value;
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

    public function chip(string $value, string $label, array $opts = []): static
    {
        $this->chips[] = ['value' => $value, 'label' => $label, ...$opts];
        return $this;
    }

    public function render(): void
    {
        $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

        echo '<div class="gk-filter-chips" data-gk-chips="' . $e($this->id) . '">';
        // The page default is lit while the URL names no filter — and also when it
        // names one no chip carries: a controller falls back to its default for a
        // value it does not know, and a row with nothing lit would contradict the
        // list below it. "Names" means present: the "All" chip links to ?status=
        // on purpose, and reading empty as absent made All impossible to select.
        $active = $this->currentValue;
        if ($this->defaultValue !== null) {
            $named = is_string($_GET[$this->paramName] ?? null);
            $known = in_array($active, array_column($this->chips, 'value'), true);
            if (!$named || !$known) $active = $this->defaultValue;
        }

        foreach ($this->chips as $chip) {
            $val = $chip['value'];
            $isActive = $active === $val;
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
            // ALWAYS set the param — even for the empty 'Alle' value. Otherwise the
            // 'Alle' chip points at the bare URL without a query; GK.liveTable.restoreSession
            // reads that as a 'fresh page load' and jumps back to the filter it last
            // remembered (bug: 'All' jumps to 'Suggestions'). With an explicit empty
            // param (?param=) the URL carries a query string → no jumping back.
            $params[$this->paramName] = $val;
            $url = $this->baseUrl ?: strtok($_SERVER['REQUEST_URI'] ?? '', '?');
            if ($params) {
                $url .= '?' . http_build_query($params);
            }

            // A class is a colour. aria-current says the same thing to a screen
            // reader — "true", not "page": a filter is not a page.
            echo '<a href="' . $e($url) . '" class="' . $cls . '"' . ($isActive ? ' aria-current="true"' : '') . '>';
            if (isset($chip['icon'])) {
                echo '<span class="gk-chip-icon material-icons" aria-hidden="true">' . $e($chip['icon']) . '</span>';
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
