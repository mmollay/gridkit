<?php

declare(strict_types=1);

namespace GridKit;

/**
 * GridKit\TableHeader — einheitliche Filter-/Such-Leiste über Tabellen.
 *
 * Struktur (immer in dieser Reihenfolge):
 *   1. Status-Zeile  (z.B. FilterChips „Alle / Offen / Bezahlt", volle Breite)
 *   2. Toolbar       (Suche + Filter-Dropdowns inline, optional Reset-Button)
 *   3. Erweitert     (collapsible <details>, z.B. Datums-/Beträge-Filter)
 *
 * Jede Sektion ist optional. Inhalte werden via Closures übergeben — die
 * jeweilige Render-Funktion (z.B. einer FilterChips-Instanz) wird zur
 * Render-Zeit aufgerufen. Das hält den Aufruf in der View kompakt:
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
 *     ->reset('/faktura/expenses')
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
    private string $advancedSummary = 'Erweiterte Filter';
    private bool $advancedOpen = false;

    private ?string $resetUrl = null;
    private string $resetLabel = 'Filter zurücksetzen';

    public function __construct(string $id)
    {
        $this->id = $id;
    }

    public static function make(string $id): self
    {
        return new self($id);
    }

    /**
     * Status-Zeile (volle Breite, oben) — typisch FilterChips.
     */
    public function status(\Closure $renderer): self
    {
        $this->statusRenderer = $renderer;
        return $this;
    }

    /**
     * Such-Eingabe in der Toolbar.
     * @param array{live?:string,id?:string} $opts
     */
    public function search(string $name, string $value = '', string $placeholder = 'Suche…', array $opts = []): self
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
     * Filter-Slot in der Toolbar — Closure (echo'd) oder roher HTML-String.
     */
    public function filter($content): self
    {
        if (!($content instanceof \Closure) && !is_string($content)) {
            throw new \InvalidArgumentException('filter() erwartet Closure oder String.');
        }
        $this->filters[] = $content;
        return $this;
    }

    /**
     * Erweiterte Filter (collapsible <details>).
     */
    public function advanced(\Closure $renderer, string $summary = 'Erweiterte Filter', bool $open = false): self
    {
        $this->advancedRenderer = $renderer;
        $this->advancedSummary  = $summary;
        $this->advancedOpen     = $open;
        return $this;
    }

    /**
     * Reset-Button (löst zur baseUrl ohne Parameter — entfernt alle Filter).
     */
    public function reset(string $baseUrl, string $label = 'Filter zurücksetzen'): self
    {
        $this->resetUrl   = $baseUrl;
        $this->resetLabel = $label;
        return $this;
    }

    public function render(): void
    {
        $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

        echo '<div class="gk-tableheader" data-gk-tableheader="' . $e($this->id) . '">';

        // 1. Status-Zeile
        if ($this->statusRenderer) {
            echo '<div class="gk-tableheader-status">';
            ($this->statusRenderer)();
            echo '</div>';
        }

        // 2. Toolbar (Suche + Filter)
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
                    . ' placeholder="' . $e($s['placeholder']) . '"'
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
                echo '<a href="' . $e($this->resetUrl) . '" class="gk-btn gk-btn-text gk-btn-sm" title="' . $e($this->resetLabel) . '">';
                echo '<span class="material-icons" style="font-size:16px;vertical-align:-3px;">close</span> Reset';
                echo '</a>';
            }

            echo '</div>';
        }

        // 3. Erweiterte Filter
        if ($this->advancedRenderer) {
            $openAttr = $this->advancedOpen ? ' open' : '';
            echo '<details class="gk-tableheader-advanced"' . $openAttr . '>';
            echo '<summary>' . $e($this->advancedSummary) . '</summary>';
            echo '<div class="gk-tableheader-advanced-body">';
            ($this->advancedRenderer)();
            echo '</div>';
            echo '</details>';
        }

        echo '</div>';
    }
}
