<?php

declare(strict_types=1);

namespace GridKit;

/**
 * GridKit\TableHeader — a unified filter/search bar above tables.
 *
 * Structure (always in this order):
 *   1. Status row  (e.g. FilterChips "All / Open / Paid", full width)
 *   2. Toolbar     (search + filter dropdowns inline, optional reset button)
 *   3. Advanced    (collapsible <details>, e.g. date/amount filters)
 *
 * Every section is optional. Content is handed in as closures — the
 * respective render function (e.g. of a FilterChips instance) is invoked at
 * render time. That keeps the call site in the view compact:
 *
 *   TableHeader::make('exp')
 *     ->status(fn() => (new FilterChips('exp-status','paid'))->...->render())
 *     ->search('q', $q, 'Beschreibung, Lieferant, Nr…', ['live' => 'exp-live'])
 *     ->filter(fn() => (new YearFilter('exp-year','year'))->...->render())
 *     ->filter('<select class="gk-filter" name="payment_method" data-gk-live-input="exp-live">…</select>')
 *     ->advanced(function() use ($date_from, $date_to) {
 *         echo '<input type="date" class="gk-filter" value="'.$date_from.'">';
 *         echo '<input type="date" class="gk-filter" value="'.$date_to.'">';
 *     })
 *     ->reset('/expenses')
 *     ->render();
 */
class TableHeader
{
    private string $id;

    /** @var \Closure|null */
    private $statusRenderer = null;

    /** @var array{name:string,value:string,placeholder:string,liveInput:string,id:string}|null */
    private ?array $search = null;

    /** @var array<int, \Closure|string> */
    private array $filters = [];

    /** @var \Closure|null */
    private $advancedRenderer = null;
    private string $advancedSummary = '';
    private bool $advancedOpen = false;

    private ?string $resetUrl = null;
    private string $resetLabel = '';

    public function __construct(string $id)
    {
        $this->id = $id;
    }

    public static function make(string $id): self
    {
        return new self($id);
    }

    /**
     * Status row (full width, at the top) — typically FilterChips.
     */
    public function status(\Closure $renderer): self
    {
        $this->statusRenderer = $renderer;
        return $this;
    }

    /**
     * Search input in the toolbar. An empty $placeholder uses the translated default.
     * @param array{live?:string,id?:string} $opts
     */
    public function search(string $name, string $value = '', string $placeholder = '', array $opts = []): self
    {
        $this->search = [
            'name'        => $name,
            'value'       => $value,
            'placeholder' => $placeholder,
            'liveInput'   => $opts['live'] ?? '',
            'id'          => $opts['id']   ?? ($this->id . '-search'),
        ];
        return $this;
    }

    /**
     * Filter slot in the toolbar — closure (echo'd) or raw HTML string.
     */
    public function filter($content): self
    {
        if (!($content instanceof \Closure) && !is_string($content)) {
            throw new \InvalidArgumentException('filter() expects a Closure or a string.');
        }
        $this->filters[] = $content;
        return $this;
    }

    /**
     * Advanced filters (collapsible <details>).
     */
    public function advanced(\Closure $renderer, string $summary = '', bool $open = false): self
    {
        $this->advancedRenderer = $renderer;
        $this->advancedSummary  = $summary;
        $this->advancedOpen     = $open;
        return $this;
    }

    /**
     * Reset button (points at baseUrl without any parameters — removes all filters).
     */
    public function reset(string $baseUrl, string $label = ''): self
    {
        $this->resetUrl   = $baseUrl;
        $this->resetLabel = $label;
        return $this;
    }

    public function render(): void
    {
        $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

        echo '<div class="gk-tableheader" data-gk-tableheader="' . $e($this->id) . '">';

        // 1. Status row
        if ($this->statusRenderer) {
            echo '<div class="gk-tableheader-status">';
            ($this->statusRenderer)();
            echo '</div>';
        }

        // 2. Toolbar (search + filters)
        $hasToolbar = $this->search !== null || !empty($this->filters) || $this->resetUrl !== null;
        if ($hasToolbar) {
            echo '<div class="gk-tableheader-toolbar">';

            if ($this->search !== null) {
                $s = $this->search;
                $liveAttr = $s['liveInput'] !== '' ? ' data-gk-live-input="' . $e($s['liveInput']) . '"' : '';
                echo '<input type="text"'
                    . ' id="' . $e($s['id']) . '"'
                    . ' name="' . $e($s['name']) . '"'
                    . ' class="gk-search"'
                    . ' placeholder="' . $e($s['placeholder'] !== '' ? $s['placeholder'] : Lang::t('table.search')) . '"'
                    . ' value="' . $e($s['value']) . '"'
                    . $liveAttr . '>';
            }

            foreach ($this->filters as $f) {
                if ($f instanceof \Closure) {
                    $f();
                } else {
                    echo $f;
                }
            }

            // Spacer + Reset
            if ($this->resetUrl !== null) {
                echo '<div class="gk-tableheader-spacer"></div>';
                $resetText = $this->resetLabel !== '' ? $this->resetLabel : Lang::t('tableheader.reset');
                echo '<a href="' . $e($this->resetUrl) . '" class="gk-btn gk-btn-text gk-btn-sm" title="' . $e($resetText) . '">';
                echo '<span class="material-icons" style="font-size:16px;vertical-align:-3px;" aria-hidden="true">close</span> ' . $e($resetText);
                echo '</a>';
            }

            echo '</div>';
        }

        // 3. Advanced filters
        if ($this->advancedRenderer) {
            $openAttr = $this->advancedOpen ? ' open' : '';
            echo '<details class="gk-tableheader-advanced"' . $openAttr . '>';
            $summaryText = $this->advancedSummary !== '' ? $this->advancedSummary : Lang::t('tableheader.advanced');
            echo '<summary>' . $e($summaryText) . '</summary>';
            echo '<div class="gk-tableheader-advanced-body">';
            ($this->advancedRenderer)();
            echo '</div>';
            echo '</details>';
        }

        echo '</div>';
    }
}
