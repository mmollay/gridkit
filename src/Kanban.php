<?php
declare(strict_types=1);

namespace GridKit;

/**
 * Kanban — columns with cards for tasks, orders, campaigns and support tickets (1.124.0).
 *
 *   (new Kanban('tickets'))
 *       ->label('Support tickets')
 *       ->column('new', 'New', ['color' => 'blue'])
 *       ->column('doing', 'In progress', ['limit' => 3, 'description' => 'Three at a time'])
 *       ->column('done', 'Done', ['color' => 'green'])
 *       ->cards($rows, ['id' => 'id', 'title' => 'subject', 'column' => 'status'])
 *       ->moveUrl('/tickets/move', ['headers' => ['X-CSRF-Token' => $csrf], 'fields' => ['_token' => $csrf]])
 *       ->wipLimits('block')
 *       ->addUrl('/tickets/new?status={column}')
 *       ->render();
 *
 * Why it exists: the dashboards that use GridKit keep orders, campaigns and tickets in states, and
 * each drew its own row of columns — none of which a keyboard could move a card in, or a phone could
 * show without pushing the page sideways. This is the one place for it.
 *
 * The server writes a labelled region of columns, each a heading, a count and a list of cards (links
 * where a card has an href). That board works without any script: every card is in view, and a card
 * that may move carries a small form — "Move to …" and a button — that posts id and column to the
 * moveUrl() like any form. js/kanban.js adds what needs a script: dragging with the mouse or a finger
 * (the board scrolls under the pointer), moving with the keyboard (Space lifts, the arrows move, Space
 * drops, Escape cancels) with every step spoken, the counts and limits kept up to date, and every move
 * posted as JSON, one at a time, with the old place given back when the save fails. It is a file of its
 * own for the reason js/tree.js is: the PawBot dashboard keeps its own copy of gridkit.js that no sync
 * ever overwrites.
 *
 * Every misuse the class can see (a duplicate id, a card in a column the board does not have, an option
 * it does not know or of the wrong type) is an E_USER_WARNING with a fallback that still renders.
 */
class Kanban
{
    /** The colours of a column, a badge and a label: GridKit's label colours. */
    private const COLORS = ['gray', 'green', 'orange', 'red', 'blue'];

    private const COLUMN_OPTS = ['limit', 'color', 'description'];

    /** What a card may carry besides its id, title and column. */
    private const CARD_OPTS = ['text', 'badge', 'avatar', 'assignee', 'due', 'labels', 'href', 'count', 'draggable'];

    private const WIP_MODES = ['warn', 'block'];

    private string $id;
    /** @var array<string, array{key:string,label:string,opts:array<string,mixed>}> */
    private array $columns = [];
    /** @var array<string, array{id:string,title:string,column:string,opts:array<string,mixed>}> */
    private array $cards = [];
    private string $moveUrl = '';
    /** @var array<string,string> */
    private array $moveHeaders = [];
    /** @var array<string,string> */
    private array $moveFields = [];
    private bool $movable = false;
    private string $wip = 'warn';
    private string $empty = '';
    private string $addUrl = '';
    private bool $compact = false;
    private string $label = '';
    private string|\DateTimeInterface|null $today = null;
    /** Worked out once per render. @var array<string, array{key:string,label:string,opts:array<string,mixed>}> */
    private array $shown = [];
    /** @var array<string, list<string>> */
    private array $placed = [];

    public function __construct(string $id)
    {
        $this->id = $id;
    }

    // ── Building the board ──────────────────────────────────────────────────

    /**
     * A column, in the order they are added. Options: 'limit' (a work-in-progress limit, a whole number
     * from 1), 'color' (gray, green, orange, red, blue — a stripe above the column), 'description' (a
     * line under the heading).
     *
     * @param array<string,mixed> $opts
     */
    public function column(string $key, string $label, array $opts = []): static
    {
        if ($key === '') {
            $this->warn("a column with an empty key was left out ('$label').");
            return $this;
        }
        if (isset($this->columns[$key])) {
            $this->warn("the column '$key' is there already — the second one ('$label') was left out.");
            return $this;
        }
        $clean = [];
        foreach ($opts as $name => $value) {
            $name = (string) $name;
            if (!in_array($name, self::COLUMN_OPTS, true)) {
                $this->warn("column '$key' has the option '$name', which a column does not know — it was ignored.");
                continue;
            }
            if ($value === null || $value === '') continue;
            switch ($name) {
                case 'limit':
                    $n = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                    if ($n === false || is_bool($value)) $this->badColumn($key, $name, $value, 'a whole number from 1');
                    else $clean['limit'] = $n;
                    break;
                case 'color':
                    if (in_array($value, self::COLORS, true)) $clean['color'] = $value;
                    else $this->badColumn($key, $name, $value, implode(', ', self::COLORS));
                    break;
                case 'description':
                    if (is_scalar($value) && !is_bool($value)) $clean['description'] = (string) $value;
                    else $this->badColumn($key, $name, $value, 'text');
                    break;
            }
        }
        $this->columns[$key] = ['key' => $key, 'label' => $label !== '' ? $label : $key, 'opts' => $clean];
        return $this;
    }

    /**
     * One card. $column is the key of its column; a column the board does not have puts the card in the
     * first column, with a warning. Options: see CARD_OPTS and GRIDKIT_SKILL.md ("Kanban").
     *
     * @param array<string,mixed> $opts
     */
    public function card(string $id, string $title, string $column, array $opts = []): static
    {
        $this->addCard($id, $title, $column, $opts);
        return $this;
    }

    /**
     * Rows as a database hands them over. $map names the field of each part of a card: 'id', 'title'
     * (default 'title') and 'column' (default 'column'); every card option is read from the row under its
     * own name, or under the name the map gives it ('assignee' => 'owner_name'). Rows may be objects.
     *
     * @param iterable<mixed> $rows
     * @param array<string,string> $map
     */
    public function cards(iterable $rows, array $map = []): static
    {
        $map += ['id' => 'id', 'title' => 'title', 'column' => 'column'];
        foreach ($rows as $i => $row) {
            if (is_object($row)) $row = get_object_vars($row);
            if (!is_array($row)) {
                $this->warn("cards() row $i is " . get_debug_type($row) . ', not an array — it was left out.');
                continue;
            }
            $rawId = $row[$map['id']] ?? null;
            if (!is_scalar($rawId) || is_bool($rawId) || (string) $rawId === '') {
                $this->warn("cards() row $i has no '{$map['id']}' — it was left out.");
                continue;
            }
            $rawTitle = $row[$map['title']] ?? null;
            $rawColumn = $row[$map['column']] ?? null;
            $opts = [];
            foreach (self::CARD_OPTS as $opt) {
                $field = $map[$opt] ?? $opt;
                if (array_key_exists($field, $row)) $opts[$opt] = $row[$field];
            }
            $this->addCard(
                (string) $rawId,
                is_scalar($rawTitle) && !is_bool($rawTitle) ? (string) $rawTitle : '',
                is_scalar($rawColumn) && !is_bool($rawColumn) ? (string) $rawColumn : '',
                $opts
            );
        }
        return $this;
    }

    /**
     * Cards can be moved, and every move is saved here. With the script: POST as JSON {"id", "column",
     * "index"} (index counted from 0 among the cards of the new column — the place the card now has).
     * Without it: the card's "Move to" form posts id and column as a plain form (no index: the end of
     * the column). Options: 'headers' — sent with every JSON move (['X-CSRF-Token' => $token]);
     * 'fields' — hidden fields of the form (['_token' => $token]), since a form sends no header.
     *
     * @param array{headers?:array<string,string>,fields?:array<string,string>} $opts
     */
    public function moveUrl(string $url, array $opts = []): static
    {
        $this->moveUrl = $url;
        $this->movable = true;
        foreach ($opts as $name => $value) {
            if (!in_array($name, ['headers', 'fields'], true)) {
                $this->warn("moveUrl() has the option '$name', which it does not know — it takes 'headers' and 'fields'. It was ignored.");
                continue;
            }
            if (!is_array($value)) {
                $this->warn("moveUrl() takes '$name' as an array of name => value — none is sent.");
                continue;
            }
            foreach ($value as $k => $v) {
                if (!is_string($k) || $k === '' || !is_scalar($v)) {
                    $this->warn("moveUrl() '$name': " . var_export($k, true) . ' is left out — it takes name => text.');
                    continue;
                }
                if ($name === 'headers') $this->moveHeaders[$k] = (string) $v;
                else $this->moveFields[$k] = (string) $v;
            }
        }
        return $this;
    }

    /** Cards can be moved without a moveUrl(): the page hears gk:kanbanmove / gk:kanbanmoved and saves itself. */
    public function movable(bool $on = true): static
    {
        $this->movable = $on;
        return $this;
    }

    /**
     * What a move into a column at its limit does. 'warn' (the default, also without a call): it is
     * made, and the column is marked as over its limit. 'block': it is refused — no drop, no keyboard
     * step, the column is disabled in the "Move to" list — and the person is told why.
     */
    public function wipLimits(string $mode = 'block'): static
    {
        if (!in_array($mode, self::WIP_MODES, true)) {
            $this->warn("wipLimits('$mode') knows 'warn' and 'block' — a full column still takes cards ('warn').");
            $mode = 'warn';
        }
        $this->wip = $mode;
        return $this;
    }

    /** The words in a column without cards; default the catalogue's "No cards". */
    public function empty(string $text): static
    {
        $this->empty = $text;
        return $this;
    }

    /**
     * An "Add card" link at the foot of every column. {column} in the URL is replaced by the column's key
     * (URL-encoded); without it ?column=<key> is added.
     */
    public function addUrl(string $url): static
    {
        $this->addUrl = $url;
        return $this;
    }

    /** Smaller cards: a card's text is left out and the gaps are tighter — a board with many cards. */
    public function compact(bool $on = true): static
    {
        $this->compact = $on;
        return $this;
    }

    /** The accessible name of the board ("Support tickets"). Without it the catalogue's "Board". */
    public function label(string $label): static
    {
        $this->label = $label;
        return $this;
    }

    /** The day a due date is measured against ('Y-m-d' or a moment) — for a fixed page and for tests. Default: today. */
    public function today(string|\DateTimeInterface $today): static
    {
        if (is_string($today) && DateRange::date($today) === null) {
            $this->warn("today('$today') is not a day (Y-m-d); today stays today.");
            return $this;
        }
        $this->today = $today;
        return $this;
    }

    // ── Rendering ───────────────────────────────────────────────────────────

    /** Prints the board. */
    public function render(): void
    {
        echo $this->html();
    }

    /** The board as a string — the twin of render(). */
    public function html(): string
    {
        $e = self::escaper();
        $board = self::key($this->id);
        $name = $this->label !== '' ? $this->label : Lang::t('kanban.label');
        $columns = $this->columns;
        $byColumn = $this->placeCards($columns);
        // What the "Move to" lists offer: the columns as drawn, with their cards as placed.
        $this->shown = $columns;
        $this->placed = $byColumn;

        $attrs = ' class="gk-kanban' . ($this->compact ? ' gk-kanban-compact' : '') . '" id="gk-kanban-' . $e($board) . '"'
            . ' data-gk-kanban="' . $e($this->id) . '" data-gk-kanban-wip="' . $this->wip . '"';
        if ($this->movable) $attrs .= ' data-gk-kanban-movable';
        if ($this->moveUrl !== '') $attrs .= ' data-gk-kanban-move-url="' . $e($this->moveUrl) . '"';
        if ($this->moveHeaders) {
            $attrs .= " data-gk-kanban-headers='" . $e((string) json_encode($this->moveHeaders, JSON_UNESCAPED_UNICODE)) . "'";
        }

        // The board is the region and the box that scrolls sideways — never the page. A region with
        // nothing to focus in it would be out of reach of a keyboard: it takes the focus itself then.
        $focusable = $this->movable || $this->addUrl !== '';
        foreach ($this->cards as $c) {
            if (isset($c['opts']['href'])) { $focusable = true; break; }
        }
        $html = '<div' . $attrs . '>'
            . '<section class="gk-kanban-board" aria-label="' . $e($name) . '"' . ($focusable ? '' : ' tabindex="0"') . '>';
        if ($columns === []) {
            return $html . '<p class="gk-kanban-empty">' . $e(Lang::t('kanban.no_columns')) . '</p></section></div>';
        }
        foreach ($columns as $key => $col) {
            $html .= $this->renderColumn((string) $key, $col, $byColumn[$key] ?? [], $board);
        }
        return $html . '</section></div>';
    }

    /**
     * The move a request carries: the script's JSON {"id", "column", "index"} or the form's id and column
     * (and an index, if a page adds one). null when there is none or it cannot be read. 'form' says which
     * of the two it was — after a form, answer with a redirect back to the page; after JSON, with JSON.
     * $body and $post default to php://input and $_POST.
     *
     * @param array<mixed>|null $post
     * @return array{id:string,column:string,index:?int,form:bool}|null
     */
    public static function readMove(?string $body = null, ?array $post = null): ?array
    {
        $type = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? ''));
        $json = $body !== null || str_contains($type, 'json');
        if ($json) {
            $data = json_decode($body ?? (string) file_get_contents('php://input'), true);
            $form = false;
        } else {
            $data = $post ?? $_POST;
            $form = true;
        }
        if (!is_array($data)) return null;
        $id = $data['id'] ?? null;
        $column = $data['column'] ?? null;
        if (!is_scalar($id) || is_bool($id) || (string) $id === '' || !is_scalar($column) || is_bool($column) || (string) $column === '') {
            return null;
        }
        $index = null;
        if (isset($data['index']) && $data['index'] !== '') {
            $n = filter_var($data['index'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
            if ($n === false || is_bool($data['index'])) return null;
            $index = $n;
        }
        return ['id' => (string) $id, 'column' => (string) $column, 'index' => $index, 'form' => $form];
    }

    /**
     * The cards of every column, in the order they were added. A card whose column the board does not
     * have goes to the first column; without any column() the columns are made from the cards.
     *
     * @param array<string, array{key:string,label:string,opts:array<string,mixed>}> $columns
     * @return array<string, list<string>>
     */
    private function placeCards(array &$columns): array
    {
        if ($columns === [] && $this->cards !== []) {
            $this->warn('there is no column() — the columns were made from the cards, named by their keys.');
            foreach ($this->cards as $c) {
                $k = $c['column'] !== '' ? $c['column'] : '-';
                $columns[$k] ??= ['key' => $k, 'label' => $k, 'opts' => []];
            }
        }
        $first = (string) array_key_first($columns);
        $by = [];
        foreach ($this->cards as $c) {
            $col = $c['column'];
            if ($columns !== [] && !isset($columns[$col])) {
                $this->warn("card '{$c['id']}' is in the column '$col', which the board does not have — it is shown in '$first'.");
                $col = $first;
            }
            if ($col === '') $col = '-';
            $by[$col][] = $c['id'];
        }
        return $by;
    }

    /**
     * One column: the heading with its count (and limit), the description, the list of cards, the words
     * of an empty column and the link to add a card.
     *
     * @param array{key:string,label:string,opts:array<string,mixed>} $col
     * @param list<string> $ids
     */
    private function renderColumn(string $key, array $col, array $ids, string $board): string
    {
        $e = self::escaper();
        $o = $col['opts'];
        $base = 'gk-kanban-' . $board . '-' . self::key($key);
        $n = count($ids);
        $limit = $o['limit'] ?? null;
        $over = $limit !== null && $n > $limit;

        $cls = 'gk-kanban-column';
        if (isset($o['color'])) $cls .= ' gk-kanban-column-' . $o['color'];
        if ($over) $cls .= ' gk-kanban-over';
        $html = '<div class="' . $cls . '" data-gk-kanban-column="' . $e($key) . '"'
            . ($limit !== null ? ' data-gk-kanban-limit="' . $limit . '"' : '') . '>';

        $html .= '<div class="gk-kanban-head">'
            . '<h3 class="gk-kanban-title" id="' . $e($base . '-h') . '">' . $e($col['label']) . '</h3>'
            . '<span class="gk-kanban-count" id="' . $e($base . '-n') . '">' . $this->countInner($n, $limit) . '</span>'
            . '<span class="gk-kanban-over-note"' . ($over ? '' : ' hidden') . '>' . $e(Lang::t('kanban.over')) . '</span>';
        if (isset($o['description'])) {
            $html .= '<p class="gk-kanban-desc" id="' . $e($base . '-d') . '">' . $e($o['description']) . '</p>';
        }
        $html .= '</div>';

        $html .= '<ul class="gk-kanban-list" id="' . $e($base . '-list') . '" aria-labelledby="' . $e($base . '-h') . ' ' . $e($base . '-n') . '"'
            . (isset($o['description']) ? ' aria-describedby="' . $e($base . '-d') . '"' : '') . '>';
        foreach ($ids as $id) $html .= $this->renderCard($this->cards[$id], $key, $board);
        $html .= '</ul>';

        $html .= '<p class="gk-kanban-empty"' . ($n > 0 ? ' hidden' : '') . '>'
            . $e($this->empty !== '' ? $this->empty : Lang::t('kanban.empty')) . '</p>';

        if ($this->addUrl !== '') {
            $html .= '<a class="gk-kanban-add" href="' . $e($this->addHref($key)) . '">'
                . '<span class="material-icons" aria-hidden="true">add</span>' . $e(Lang::t('kanban.add'))
                . '<span class="gk-sr-only"> ' . $e(Lang::t('kanban.add_to', ['column' => $col['label']])) . '</span></a>';
        }
        return $html . '</div>';
    }

    /** The count of a column: the number (with its limit) for the eye, the words for a screen reader. */
    private function countInner(int $n, ?int $limit): string
    {
        $e = self::escaper();
        $shown = $limit !== null ? $n . ' / ' . $limit : (string) $n;
        $said = $limit !== null
            ? Lang::t($n === 1 ? 'kanban.count_limit_one' : 'kanban.count_limit', ['n' => $n, 'limit' => $limit])
            : Lang::t($n === 1 ? 'kanban.count_one' : 'kanban.count', ['n' => $n]);
        return '<span aria-hidden="true">' . $e($shown) . '</span><span class="gk-sr-only">' . $e($said) . '</span>';
    }

    /** @param array{id:string,title:string,column:string,opts:array<string,mixed>} $c */
    private function renderCard(array $c, string $column, string $board): string
    {
        $e = self::escaper();
        $o = $c['opts'];
        $base = 'gk-kanban-' . $board . '-c-' . self::key($c['id']);
        $moves = $this->movable && ($o['draggable'] ?? true) !== false;

        $html = '<li class="gk-kanban-card" id="' . $e($base) . '" data-gk-kanban-id="' . $e($c['id']) . '"'
            . ($moves ? '' : ' data-gk-kanban-nodrag') . '>';

        $html .= '<div class="gk-kanban-card-head">';
        $titleId = $e($base . '-t');
        if (isset($o['href'])) {
            $html .= '<a class="gk-kanban-card-title" id="' . $titleId . '" href="' . $e($o['href']) . '">' . $e($c['title']) . '</a>';
        } else {
            $html .= '<span class="gk-kanban-card-title" id="' . $titleId . '">' . $e($c['title']) . '</span>';
        }
        if (isset($o['badge'])) {
            $html .= '<span class="gk-label gk-label-' . $o['badge']['color'] . ' gk-kanban-badge">' . $e($o['badge']['text']) . '</span>';
        }
        $html .= '</div>';

        if (isset($o['text']) && !$this->compact) {
            $html .= '<p class="gk-kanban-text">' . $e($o['text']) . '</p>';
        }
        if (!empty($o['labels'])) {
            $html .= '<ul class="gk-kanban-labels">';
            foreach ($o['labels'] as $l) {
                $html .= '<li class="gk-label gk-label-' . $l['color'] . '">' . $e($l['text']) . '</li>';
            }
            $html .= '</ul>';
        }

        $meta = '';
        if (isset($o['due'])) $meta .= $this->dueHtml($o['due']);
        if (isset($o['count'])) {
            $meta .= '<span class="gk-kanban-cardcount">' . $e($o['count']) . '</span>';
        }
        if (isset($o['assignee']) || isset($o['avatar'])) $meta .= $this->avatarHtml($o['assignee'] ?? null, $o['avatar'] ?? null);
        if ($meta !== '') $html .= '<div class="gk-kanban-meta">' . $meta . '</div>';

        if ($moves && $this->moveUrl !== '') $html .= $this->moveForm($c, $column, $base);
        return $html . '</li>';
    }

    /**
     * The way to move a card without a script: a disclosure with a select of the columns and a button,
     * posted to moveUrl() as a plain form. The script keeps it and sends it as JSON instead.
     *
     * @param array{id:string,title:string,column:string,opts:array<string,mixed>} $c
     */
    private function moveForm(array $c, string $column, string $base): string
    {
        $e = self::escaper();
        $html = '<details class="gk-kanban-move"><summary class="gk-kanban-move-toggle">'
            . '<span class="material-icons" aria-hidden="true">drive_file_move</span>'
            . '<span class="gk-kanban-move-word">' . $e(Lang::t('kanban.move')) . '</span>'
            . '<span class="gk-sr-only">: ' . $e($c['title']) . '</span></summary>'
            . '<form class="gk-kanban-move-form" method="post" action="' . $e($this->moveUrl) . '">'
            . '<input type="hidden" name="id" value="' . $e($c['id']) . '">';
        foreach ($this->moveFields as $k => $v) {
            $html .= '<input type="hidden" name="' . $e($k) . '" value="' . $e($v) . '">';
        }
        $html .= '<label class="gk-kanban-move-label" for="' . $e($base . '-to') . '">' . $e(Lang::t('kanban.move_to')) . '</label>'
            . '<select class="gk-kanban-move-select" name="column" id="' . $e($base . '-to') . '">';
        foreach ($this->shown as $key => $col) {
            $key = (string) $key;
            $limit = $col['opts']['limit'] ?? null;
            $full = $this->wip === 'block' && $limit !== null && $key !== $column && count($this->placed[$key] ?? []) >= $limit;
            $html .= '<option value="' . $e($key) . '"' . ($key === $column ? ' selected' : '') . ($full ? ' disabled' : '') . '>'
                . $e($col['label'] . ($full ? ' ' . Lang::t('kanban.full') : '')) . '</option>';
        }
        return $html . '</select>'
            . '<button type="submit" class="gk-btn gk-btn-tonal gk-btn-primary gk-btn-sm gk-kanban-move-button">' . $e(Lang::t('kanban.move_button')) . '</button>'
            . '</form></details>';
    }

    /** The due date: the day in words, and "Overdue" or "Due today" written out — never the colour alone. */
    private function dueHtml(string $due): string
    {
        $e = self::escaper();
        $today = $this->todayDate();
        [$y, $m, $d] = array_map('intval', explode('-', $due));
        $months = explode('|', Lang::t('date.months_short'));
        $date = Lang::t($y === (int) substr($today, 0, 4) ? 'kanban.date' : 'kanban.date_year',
            ['month' => $months[$m - 1] ?? (string) $m, 'day' => $d, 'year' => $y]);
        if ($due < $today) {
            $cls = ' gk-kanban-overdue';
            $icon = 'warning';
            $text = Lang::t('kanban.overdue', ['date' => $date]);
        } elseif ($due === $today) {
            $cls = ' gk-kanban-due-today';
            $icon = 'today';
            $text = Lang::t('kanban.due_today');
        } else {
            $cls = '';
            $icon = 'event';
            $text = Lang::t('kanban.due', ['date' => $date]);
        }
        return '<span class="gk-kanban-due' . $cls . '"><span class="material-icons" aria-hidden="true">' . $icon . '</span>'
            . '<time datetime="' . $e($due) . '">' . $e($text) . '</time></span>';
    }

    /** The person a card is assigned to: a picture or the initials, the name in words. */
    private function avatarHtml(?string $name, ?string $src): string
    {
        $e = self::escaper();
        $html = '<span class="gk-kanban-avatar"' . ($name !== null ? ' title="' . $e($name) . '"' : '') . ' aria-hidden="true">';
        if ($src !== null) {
            $html .= '<img src="' . $e($src) . '" alt="" loading="lazy" decoding="async">';
        } else {
            $html .= $e(self::initials((string) $name));
        }
        $html .= '</span>';
        if ($name !== null) $html .= '<span class="gk-sr-only">' . $e(Lang::t('kanban.assignee', ['name' => $name])) . '</span>';
        return $html;
    }

    private static function initials(string $name): string
    {
        $parts = preg_split('/[\s\-_.]+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = '';
        foreach ([reset($parts), count($parts) > 1 ? end($parts) : false] as $p) {
            if (!is_string($p) || $p === '') continue;
            // mbstring is optional (README): without it the first letter is cut by a pattern.
            $first = function_exists('mb_substr') ? mb_substr($p, 0, 1) : (preg_match('/^./u', $p, $m) ? $m[0] : $p[0]);
            $out .= function_exists('mb_strtoupper') ? mb_strtoupper($first) : strtoupper($first);
        }
        return $out !== '' ? $out : '?';
    }

    private function addHref(string $key): string
    {
        if (str_contains($this->addUrl, '{column}')) {
            return str_replace('{column}', rawurlencode($key), $this->addUrl);
        }
        $url = $this->addUrl;
        $hash = '';
        if (($at = strpos($url, '#')) !== false) {
            $hash = substr($url, $at);
            $url = substr($url, 0, $at);
        }
        return $url . (str_contains($url, '?') ? '&' : '?') . 'column=' . rawurlencode($key) . $hash;
    }

    private function todayDate(): string
    {
        if (is_string($this->today)) return $this->today;
        $at = $this->today === null
            ? new \DateTimeImmutable('now')
            : \DateTimeImmutable::createFromInterface($this->today);
        return $at->format('Y-m-d');
    }

    // ── Taking cards in ─────────────────────────────────────────────────────

    /** @param array<string,mixed> $opts */
    private function addCard(string $id, string $title, string $column, array $opts): void
    {
        if ($id === '') {
            $this->warn("a card with an empty id was left out ('$title').");
            return;
        }
        if (isset($this->cards[$id])) {
            $this->warn("the card id '$id' is taken — the second card ('$title') was left out.");
            return;
        }
        if ($title === '') {
            $this->warn("card '$id' has no title — its id is shown instead.");
            $title = $id;
        }
        $clean = [];
        foreach ($opts as $key => $value) {
            $key = (string) $key;
            if (!in_array($key, self::CARD_OPTS, true)) {
                $this->warn("card '$id' has the option '$key', which a card does not know — it was ignored.");
                continue;
            }
            if ($value === null || $value === '' || $value === []) continue;
            switch ($key) {
                case 'text':
                case 'assignee':
                    if (is_scalar($value) && !is_bool($value)) $clean[$key] = (string) $value;
                    else $this->badCard($id, $key, $value, 'text');
                    break;
                case 'href':
                case 'avatar':
                    if (!is_string($value)) {
                        $this->badCard($id, $key, $value, 'a URL');
                    } elseif (preg_match('/^\s*(javascript|vbscript|data):/i', (string) preg_replace('/[\x00-\x20]+/', '', $value))
                        && !($key === 'avatar' && preg_match('#^data:image/(png|jpeg|gif|webp);#i', $value))) {
                        // Cards are fed from a database people write into: a script URL there would run in
                        // every viewer's browser on a click.
                        $this->warn("card '$id' has a script URL as '$key' — it was left out.");
                    } else {
                        $clean[$key] = $value;
                    }
                    break;
                case 'count':
                    if (is_int($value) || is_float($value) || (is_string($value) && $value !== '')) $clean['count'] = (string) $value;
                    else $this->badCard($id, $key, $value, 'a number');
                    break;
                case 'badge':
                    $b = $this->colored($value);
                    if ($b === null) $this->badCard($id, $key, $value, "text, or [text, color]");
                    else $clean['badge'] = $b;
                    break;
                case 'labels':
                    if (!is_array($value)) $value = [$value];
                    $list = [];
                    foreach ($value as $l) {
                        $c = $this->colored($l);
                        if ($c === null) $this->badCard($id, 'labels', $l, "text, or [text, color]");
                        else $list[] = $c;
                    }
                    if ($list) $clean['labels'] = $list;
                    break;
                case 'due':
                    $date = $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : DateRange::date($value);
                    if ($date === null) $this->badCard($id, $key, $value, 'a day (Y-m-d) or a DateTimeInterface');
                    else $clean['due'] = $date;
                    break;
                case 'draggable':
                    $flag = self::flag($value);
                    if ($flag === null) $this->badCard($id, $key, $value, 'true or false');
                    else $clean['draggable'] = $flag;
                    break;
            }
        }
        $this->cards[$id] = ['id' => $id, 'title' => $title, 'column' => $column, 'opts' => $clean];
    }

    /**
     * A badge or a label: text (gray), [text, color] or ['text' => …, 'color' => …]. An unknown colour is
     * gray, with a warning.
     *
     * @return array{text:string,color:string}|null
     */
    private function colored(mixed $value): ?array
    {
        if (is_scalar($value) && !is_bool($value) && (string) $value !== '') {
            return ['text' => (string) $value, 'color' => 'gray'];
        }
        if (!is_array($value)) return null;
        $text = $value['text'] ?? $value[0] ?? null;
        $color = $value['color'] ?? $value[1] ?? 'gray';
        if (!is_scalar($text) || is_bool($text) || (string) $text === '') return null;
        if (!in_array($color, self::COLORS, true)) {
            $shown = is_scalar($color) ? var_export($color, true) : get_debug_type($color);
            $this->warn("the colour $shown of '$text' is not one of " . implode(', ', self::COLORS) . ' — it is gray.');
            $color = 'gray';
        }
        return ['text' => (string) $text, 'color' => $color];
    }

    private static function flag(mixed $value): ?bool
    {
        if (is_bool($value)) return $value;
        if ($value === 0 || $value === 1) return (bool) $value;
        if (is_string($value)) {
            $v = strtolower(trim($value));
            if (in_array($v, ['1', 'true', 'yes', 'on'], true)) return true;
            if (in_array($v, ['0', 'false', 'no', 'off'], true)) return false;
        }
        return null;
    }

    private function badCard(string $id, string $key, mixed $value, string $wants): void
    {
        $shown = is_scalar($value) ? var_export($value, true) : get_debug_type($value);
        $this->warn("card '$id' has '$key' => $shown — it takes $wants. It was ignored.");
    }

    private function badColumn(string $key, string $opt, mixed $value, string $wants): void
    {
        $shown = is_scalar($value) ? var_export($value, true) : get_debug_type($value);
        $this->warn("column '$key' has '$opt' => $shown — it takes $wants. It was ignored.");
    }

    private function warn(string $message): void
    {
        trigger_error("GridKit: kanban '{$this->id}': $message", E_USER_WARNING);
    }

    /** An id as a piece of an HTML id: as it is when it is safe, else a short hash of it. */
    private static function key(string $id): string
    {
        return preg_match('/^[A-Za-z0-9_-]{1,64}$/', $id) ? $id : 'h' . substr(md5($id), 0, 12);
    }

    private static function escaper(): \Closure
    {
        return static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
