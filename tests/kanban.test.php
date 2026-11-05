<?php
/**
 * GridKit\Kanban (1.124.0) — columns with cards for tasks, orders, campaigns and tickets.
 *
 * What the server writes is checked here: the region, the columns as headed and counted lists, the
 * cards and every option, what broken input becomes (duplicates, unknown columns, options of the wrong
 * type — each a warning and a board that still renders), escaping, the "Move to" form that works
 * without a script, the limits, Kanban::readMove(), the contract with js/kanban.js (the attributes it
 * reads, the words it asks for) and with the stylesheet. What the script does with the markup — drag,
 * keys, touch, the save and the way back, contrast — needs a browser: ci/browser-kanban.js.
 */

declare(strict_types=1);

use GridKit\{Kanban, Lang};

require_once __DIR__ . '/css-helpers.php';

/** Render, and collect what it said with E_USER_WARNING. @return array{0:string,1:list<string>} */
function kbRender(callable $fn): array
{
    $notes = [];
    set_error_handler(static function (int $no, string $msg) use (&$notes): bool {
        if ($no !== E_USER_WARNING) return false;
        $notes[] = $msg;
        return true;
    });
    try {
        $html = T::capture($fn);
    } finally {
        restore_error_handler();
    }
    return [$html, $notes];
}

/** column key => [card ids], in document order. @return array<string,list<string>> */
function kbShape(string $html): array
{
    $doc = new DOMDocument();
    @$doc->loadHTML('<?xml encoding="utf-8"?>' . $html, LIBXML_NOERROR);
    $x = new DOMXPath($doc);
    $out = [];
    foreach ($x->query('//*[@data-gk-kanban-column]') as $col) {
        $ids = [];
        foreach ($x->query('.//li[@data-gk-kanban-id]', $col) as $li) $ids[] = $li->getAttribute('data-gk-kanban-id');
        $out[$col->getAttribute('data-gk-kanban-column')] = $ids;
    }
    return $out;
}

/** A ticket board as a database hands it over. */
function kbTickets(): array
{
    return [
        ['id' => 1, 'subject' => 'Printer offline', 'status' => 'new', 'priority' => ['High', 'red'], 'owner' => 'Anna Huber',
         'due' => '2026-11-02', 'tags' => ['Hardware', ['Office', 'blue']], 'href' => '/t/1', 'comments' => 3, 'body' => 'Since this morning.'],
        ['id' => 2, 'subject' => 'New laptop', 'status' => 'new', 'due' => '2026-11-04'],
        ['id' => 3, 'subject' => 'VPN drops', 'status' => 'doing', 'due' => '2026-11-20'],
        ['id' => 4, 'subject' => 'Backup test', 'status' => 'doing', 'due' => '2027-01-15'],
        ['id' => 5, 'subject' => 'Password policy', 'status' => 'review', 'draggable' => '0'],
    ];
}

function kbBoard(): Kanban
{
    return (new Kanban('tickets'))
        ->label('Support tickets')
        ->today('2026-11-04')
        ->column('new', 'New', ['color' => 'blue', 'description' => 'Not looked at yet'])
        ->column('doing', 'In progress', ['limit' => 2, 'color' => 'orange'])
        ->column('review', 'Review', ['limit' => '3'])
        ->column('done', 'Done', ['color' => 'green'])
        ->cards(kbTickets(), ['title' => 'subject', 'column' => 'status', 'badge' => 'priority', 'assignee' => 'owner',
            'labels' => 'tags', 'count' => 'comments', 'text' => 'body']);
}

/** @return array<string,callable> */
return (static function (): array {

$root = __DIR__ . '/..';
$js   = static fn (): string => (string) @file_get_contents($root . '/js/kanban.js');
$code = static fn (): string => (string) preg_replace(['#/\*.*?\*/#s', '#(?<![:"\'])//[^\n]*#'], '', $js());
$doc  = static fn (): string => (string) file_get_contents($root . '/GRIDKIT_SKILL.md');
/** The board's block of the stylesheet, comments stripped. */
$sheet = static function (): string {
    $css = gkCssText();
    $from = strpos($css, '/* === 1.124.0 — kanban === */');
    if ($from === false) return '';
    $next = strpos($css, "\n/* === ", $from + 10);
    $block = $next === false ? substr($css, $from) : substr($css, $from, $next - $from);
    return (string) preg_replace('#/\*.*?\*/#s', '', $block);
};

return [

// ── The board ───────────────────────────────────────────────────────────

'rows become columns of cards, in the order they were given, inside a labelled region' => function (): void {
    Lang::set('en');
    [$html, $notes] = kbRender(fn () => kbBoard()->render());
    T::eq($notes, [], 'a clean board says nothing');
    T::eq(kbShape($html), ['new' => ['1', '2'], 'doing' => ['3', '4'], 'review' => ['5'], 'done' => []], 'the shape of the board');
    T::contains($html, '<div class="gk-kanban" id="gk-kanban-tickets" data-gk-kanban="tickets" data-gk-kanban-wip="warn">', 'the wrap and its contract');
    T::contains($html, '<section class="gk-kanban-board" aria-label="Support tickets">', 'the board is a region named by label()');
    T::contains($html, '<h3 class="gk-kanban-title" id="gk-kanban-tickets-new-h">New</h3>', 'every column has a heading');
    T::contains($html, '<ul class="gk-kanban-list" id="gk-kanban-tickets-new-list" aria-labelledby="gk-kanban-tickets-new-h gk-kanban-tickets-new-n" aria-describedby="gk-kanban-tickets-new-d">',
        'every column is a list named by its heading and its count, described by its description');
    T::contains($html, '<p class="gk-kanban-desc" id="gk-kanban-tickets-new-d">Not looked at yet</p>', 'the description');
    T::notContains($html, 'role="', 'the server writes no roles: the elements say what they are');
    T::notContains($html, 'gk-kanban-handle', 'the handles are the script\'s');
},

'a board with nothing to focus in it takes the focus itself, so a keyboard can scroll it' => function (): void {
    [$still] = kbRender(fn () => (new Kanban('k'))->column('a', 'A')->card('1', 'One', 'a')->render());
    T::contains($still, '<section class="gk-kanban-board" aria-label="Board" tabindex="0">', 'no link, no handle, no add: a tab stop');
    foreach (['moveUrl' => fn (Kanban $k) => $k->moveUrl('/m'), 'movable' => fn (Kanban $k) => $k->movable(), 'addUrl' => fn (Kanban $k) => $k->addUrl('/n'),
              'href' => fn (Kanban $k) => $k->card('2', 'Two', 'a', ['href' => '/t/2'])] as $what => $fn) {
        [$html] = kbRender(function () use ($fn): void { $k = (new Kanban('k'))->column('a', 'A')->card('1', 'One', 'a'); $fn($k); $k->render(); });
        T::notContains($html, 'tabindex', "with $what there is something to focus: no tab stop of its own");
    }
},

'html() returns what render() prints' => function (): void {
    [$printed] = kbRender(fn () => kbBoard()->render());
    T::eq(kbBoard()->html(), $printed, 'the string twin');
},

'the count: the number for the eye, the words for a screen reader, with the limit' => function (): void {
    [$html] = kbRender(fn () => kbBoard()->render());
    T::contains($html, '<span class="gk-kanban-count" id="gk-kanban-tickets-new-n"><span aria-hidden="true">2</span><span class="gk-sr-only">2 cards</span></span>', 'no limit');
    T::contains($html, '<span aria-hidden="true">2 / 2</span><span class="gk-sr-only">2 cards, limit 2</span>', 'a limit ("3" from a database is a number)');
    T::contains($html, '<span aria-hidden="true">1 / 3</span><span class="gk-sr-only">1 card, limit 3</span>', 'a limit as text, one card');
    [$one] = kbRender(fn () => (new Kanban('k'))->column('a', 'A')->card('x', 'X', 'a')->render());
    T::contains($one, '<span class="gk-sr-only">1 card</span>', 'one card is "1 card"');
    T::contains($html, 'data-gk-kanban-column="doing" data-gk-kanban-limit="2"', 'the limit is on the column for the script');
},

'a column over its limit is marked — the class, and the words' => function (): void {
    [$html, $notes] = kbRender(fn () => (new Kanban('k'))->column('a', 'A', ['limit' => 1])->column('b', 'B', ['limit' => 2])
        ->card('1', 'One', 'a')->card('2', 'Two', 'a')->card('3', 'Three', 'b')->render());
    T::eq($notes, [], 'over a limit is no mistake');
    T::contains($html, '<div class="gk-kanban-column gk-kanban-over" data-gk-kanban-column="a"', 'the column over its limit');
    T::contains($html, '<span class="gk-kanban-over-note">Over limit</span>', 'says so in words');
    T::contains($html, '<div class="gk-kanban-column" data-gk-kanban-column="b"', 'a column within its limit is not marked');
    T::contains($html, '<span class="gk-kanban-over-note" hidden>Over limit</span>', 'its note is there for the script, hidden');
},

'column colours are a stripe; an empty column says so; the empty words can be changed' => function (): void {
    [$html] = kbRender(fn () => kbBoard()->render());
    T::contains($html, 'class="gk-kanban-column gk-kanban-column-blue"', 'blue');
    T::contains($html, 'class="gk-kanban-column gk-kanban-column-green"', 'green');
    T::contains($html, '<p class="gk-kanban-empty">No cards</p>', 'an empty column');
    T::contains($html, '<p class="gk-kanban-empty" hidden>No cards</p>', 'a column with cards has its words hidden, for the script');
    [$own] = kbRender(fn () => (new Kanban('k'))->column('a', 'A')->empty('Nothing to do')->render());
    T::contains($own, '<p class="gk-kanban-empty">Nothing to do</p>', 'empty()');
},

// ── A card ──────────────────────────────────────────────────────────────

'a card: title link, badge, text, labels, due date, count, the person' => function (): void {
    [$html] = kbRender(fn () => kbBoard()->render());
    T::contains($html, '<li class="gk-kanban-card" id="gk-kanban-tickets-c-1" data-gk-kanban-id="1" data-gk-kanban-nodrag>', 'a card is a list item (not movable without moveUrl)');
    T::contains($html, '<a class="gk-kanban-card-title" id="gk-kanban-tickets-c-1-t" href="/t/1">Printer offline</a>', 'href: the title is a link');
    T::contains($html, '<span class="gk-kanban-card-title" id="gk-kanban-tickets-c-2-t">New laptop</span>', 'no href: text');
    T::contains($html, '<span class="gk-label gk-label-red gk-kanban-badge">High</span>', 'badge [text, color]');
    T::contains($html, '<p class="gk-kanban-text">Since this morning.</p>', 'text');
    T::contains($html, '<ul class="gk-kanban-labels"><li class="gk-label gk-label-gray">Hardware</li><li class="gk-label gk-label-blue">Office</li></ul>', 'labels: text and [text, color]');
    T::contains($html, '<span class="gk-kanban-cardcount">3</span>', 'count');
    T::contains($html, '<span class="gk-kanban-avatar" title="Anna Huber" aria-hidden="true">AH</span><span class="gk-sr-only">Assigned to Anna Huber</span>', 'assignee: initials, the name in words');
    [$pic] = kbRender(fn () => (new Kanban('k'))->column('a', 'A')->card('x', 'X', 'a', ['avatar' => '/p/anna.jpg', 'assignee' => 'Anna'])->render());
    T::contains($pic, '<span class="gk-kanban-avatar" title="Anna" aria-hidden="true"><img src="/p/anna.jpg" alt="" loading="lazy" decoding="async"></span>', 'avatar: a picture');
    [$solo] = kbRender(fn () => (new Kanban('k'))->column('a', 'A')->card('x', 'X', 'a', ['assignee' => 'cher'])->render());
    T::contains($solo, 'aria-hidden="true">C</span>', 'one name: one letter');
},

'a due date: overdue and today in words and a symbol, not the colour alone; the year when it is not this year' => function (): void {
    [$html] = kbRender(fn () => kbBoard()->render());
    T::contains($html, '<span class="gk-kanban-due gk-kanban-overdue"><span class="material-icons" aria-hidden="true">warning</span><time datetime="2026-11-02">Overdue: Nov 2</time></span>', 'overdue');
    T::contains($html, '<span class="gk-kanban-due gk-kanban-due-today"><span class="material-icons" aria-hidden="true">today</span><time datetime="2026-11-04">Due today</time></span>', 'today');
    T::contains($html, '<span class="gk-kanban-due"><span class="material-icons" aria-hidden="true">event</span><time datetime="2026-11-20">Due Nov 20</time></span>', 'later');
    T::contains($html, '<time datetime="2027-01-15">Due Jan 15, 2027</time>', 'another year');
    Lang::set('de');
    [$de] = kbRender(fn () => kbBoard()->render());
    T::contains($de, '<time datetime="2026-11-02">Überfällig: 2. Nov.</time>', 'German');
    T::contains($de, '<time datetime="2027-01-15">Fällig 15. Jan. 2027</time>', 'German, another year');
    Lang::set('en');
    [$dt] = kbRender(fn () => (new Kanban('k'))->today(new DateTimeImmutable('2026-11-04 10:00'))->column('a', 'A')
        ->card('x', 'X', 'a', ['due' => new DateTimeImmutable('2026-11-03')])->render());
    T::contains($dt, '<time datetime="2026-11-03">Overdue: Nov 3</time>', 'DateTimeInterface for both');
},

'compact() leaves a card\'s text out and marks the board' => function (): void {
    [$html] = kbRender(fn () => kbBoard()->compact()->render());
    T::contains($html, '<div class="gk-kanban gk-kanban-compact"', 'the class');
    T::notContains($html, 'gk-kanban-text', 'no text');
},

'addUrl(): {column} is replaced, or ?column= is added before the fragment' => function (): void {
    [$html] = kbRender(fn () => kbBoard()->addUrl('/t/new?status={column}')->render());
    T::contains($html, '<a class="gk-kanban-add" href="/t/new?status=doing"><span class="material-icons" aria-hidden="true">add</span>Add card<span class="gk-sr-only"> to In progress</span></a>', 'the link, named by its column');
    [$plain] = kbRender(fn () => (new Kanban('k'))->column('a b', 'A')->column('c', 'C')->addUrl('/new#form')->render());
    T::contains($plain, 'href="/new?column=a%20b#form"', 'added and encoded');
    [$q] = kbRender(fn () => (new Kanban('k'))->column('c', 'C')->addUrl('/new?x=1')->render());
    T::contains($q, 'href="/new?x=1&amp;column=c"', 'after a query');
},

// ── Moving ──────────────────────────────────────────────────────────────

'moveUrl(): the contract for the script, and a "Move to" form per movable card that posts without one' => function (): void {
    [$html, $notes] = kbRender(fn () => kbBoard()
        ->moveUrl('/t/move', ['headers' => ['X-CSRF-Token' => 'tok"en'], 'fields' => ['_token' => 'tok"en']])->render());
    T::eq($notes, [], 'no warning');
    T::contains($html, 'data-gk-kanban-movable data-gk-kanban-move-url="/t/move" data-gk-kanban-headers=\'{&quot;X-CSRF-Token&quot;:&quot;tok\&quot;en&quot;}\'', 'url and headers on the wrap');
    T::contains($html, '<li class="gk-kanban-card" id="gk-kanban-tickets-c-1" data-gk-kanban-id="1">', 'a movable card has no nodrag mark');
    T::contains($html, '<li class="gk-kanban-card" id="gk-kanban-tickets-c-5" data-gk-kanban-id="5" data-gk-kanban-nodrag>', "'draggable' => '0' keeps the card");
    T::eq(substr_count($html, '<form class="gk-kanban-move-form" method="post" action="/t/move">'), 4, 'a form for each of the four movable cards');
    T::contains($html, '<details class="gk-kanban-move"><summary class="gk-kanban-move-toggle"><span class="material-icons" aria-hidden="true">drive_file_move</span><span class="gk-kanban-move-word">Move</span><span class="gk-sr-only">: Printer offline</span></summary>', 'a disclosure named after its card');
    T::contains($html, '<input type="hidden" name="id" value="1"><input type="hidden" name="_token" value="tok&quot;en"><label class="gk-kanban-move-label" for="gk-kanban-tickets-c-1-to">Move to</label><select class="gk-kanban-move-select" name="column" id="gk-kanban-tickets-c-1-to"><option value="new" selected>New</option><option value="doing">In progress</option><option value="review">Review</option><option value="done">Done</option></select>',
        'id, the fields, a labelled select of the columns, its own column chosen');
    T::contains($html, '<button type="submit" class="gk-btn gk-btn-tonal gk-btn-primary gk-btn-sm gk-kanban-move-button">Move</button></form></details>', 'a submit button');
    T::notContains($html, 'name="index"', 'the form has no index: the end of the column');
},

"wipLimits('block'): a full column is disabled in every \"Move to\" list but its own cards'" => function (): void {
    [$html] = kbRender(fn () => kbBoard()->moveUrl('/m')->wipLimits('block')->render());
    T::contains($html, 'data-gk-kanban-wip="block"', 'the mode for the script');
    T::contains($html, '<option value="doing" disabled>In progress (full)</option>', 'full: disabled and marked');
    T::contains($html, '<option value="doing" selected>In progress</option>', 'a card in it can stay');
    T::contains($html, '<option value="review">Review</option>', 'review (1 of 3) is open');
    [$warn] = kbRender(fn () => kbBoard()->moveUrl('/m')->wipLimits('warn')->render());
    T::notContains($warn, ' disabled>', "'warn' disables nothing");
    T::contains($warn, 'data-gk-kanban-wip="warn"', "'warn'");
    [$default] = kbRender(fn () => kbBoard()->moveUrl('/m')->render());
    T::contains($default, 'data-gk-kanban-wip="warn"', "without a call: 'warn'");
},

'movable() without a URL: handles for the script, no form' => function (): void {
    [$html] = kbRender(fn () => kbBoard()->movable()->render());
    T::contains($html, 'data-gk-kanban-movable', 'movable');
    T::notContains($html, 'data-gk-kanban-move-url', 'no URL');
    T::notContains($html, '<form', 'a form would post nowhere');
    [$off] = kbRender(fn () => kbBoard()->moveUrl('/m')->movable(false)->render());
    T::notContains($off, 'data-gk-kanban-movable', 'movable(false) after moveUrl()');
    T::notContains($off, '<form', 'and no form');
},

'readMove(): the script\'s JSON, the form\'s fields, and what is no move' => function (): void {
    T::eq(Kanban::readMove('{"id":"T-1","column":"done","index":2}'), ['id' => 'T-1', 'column' => 'done', 'index' => 2, 'form' => false], 'JSON');
    T::eq(Kanban::readMove('{"id":7,"column":"done","index":0}'), ['id' => '7', 'column' => 'done', 'index' => 0, 'form' => false], 'a number id, index 0');
    $saved = $_SERVER['CONTENT_TYPE'] ?? null;
    $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
    T::eq(Kanban::readMove(null, ['id' => 'T-1', 'column' => 'review', '_token' => 'x']), ['id' => 'T-1', 'column' => 'review', 'index' => null, 'form' => true], 'the form: no index');
    T::eq(Kanban::readMove(null, ['id' => 'T-1', 'column' => 'review', 'index' => '3']), ['id' => 'T-1', 'column' => 'review', 'index' => 3, 'form' => true], 'a form with an index');
    T::eq(Kanban::readMove(null, ['id' => 'T-1']), null, 'no column');
    T::eq(Kanban::readMove(null, ['id' => ['x'], 'column' => 'a']), null, 'an id that is no text');
    if ($saved === null) unset($_SERVER['CONTENT_TYPE']); else $_SERVER['CONTENT_TYPE'] = $saved;
    T::eq(Kanban::readMove('not json'), null, 'not JSON');
    T::eq(Kanban::readMove('{"id":"T-1","column":"done","index":-1}'), null, 'a negative index');
    T::eq(Kanban::readMove('{"id":"T-1","column":"done","index":"2x"}'), null, 'an index that is no number');
    T::eq(Kanban::readMove('{"id":"","column":"done"}'), null, 'an empty id');
    T::eq(Kanban::readMove('{"id":true,"column":"done"}'), null, 'true is no id');
},

// ── What goes wrong ─────────────────────────────────────────────────────

'duplicates and unknown columns: a warning and a board that still renders' => function (): void {
    [$html, $notes] = kbRender(fn () => (new Kanban('k'))
        ->column('a', 'A')->column('a', 'Again')->column('', 'Nameless')->column('b', 'B')
        ->card('1', 'One', 'a')->card('1', 'Twin', 'b')->card('', 'No id', 'a')->card('2', '', 'b')->card('3', 'Lost', 'zzz')
        ->render());
    T::eq(kbShape($html), ['a' => ['1', '3'], 'b' => ['2']], 'what is left, and the lost card in the first column');
    T::eq(count($notes), 6, 'six warnings: ' . implode(' | ', $notes));
    T::ok(str_contains(implode(' ', $notes), "the column 'a' is there already"), 'a duplicate column');
    T::ok(str_contains(implode(' ', $notes), "the card id '1' is taken"), 'a duplicate card');
    T::ok(str_contains(implode(' ', $notes), "card '2' has no title — its id is shown"), 'no title');
    T::ok(str_contains(implode(' ', $notes), "card '3' is in the column 'zzz', which the board does not have — it is shown in 'a'"), 'an unknown column');
    T::contains($html, '>2</span>', 'the id stands in for a missing title');
    T::ok(str_starts_with($notes[0], "GridKit: kanban 'k': "), 'every warning names the board');
},

'no column(): the columns are made from the cards, with a warning; no cards either: "No columns"' => function (): void {
    [$html, $notes] = kbRender(fn () => (new Kanban('k'))->card('1', 'One', 'todo')->card('2', 'Two', 'done')->card('3', 'Three', 'todo')->render());
    T::eq(kbShape($html), ['todo' => ['1', '3'], 'done' => ['2']], 'one column per key, in the order met');
    T::eq(count($notes), 1, 'one warning');
    [$none, $quiet] = kbRender(fn () => (new Kanban('k'))->render());
    T::contains($none, '<p class="gk-kanban-empty">No columns</p>', 'an empty board says so');
    T::eq($quiet, [], 'and is no mistake');
},

'options of the wrong type are dropped with a warning, the card still shows' => function (): void {
    [$html, $notes] = kbRender(fn () => (new Kanban('k'))
        ->column('a', 'A', ['limit' => 0, 'color' => 'purple', 'description' => ['x'], 'width' => 3])
        ->column('b', 'B', ['limit' => true])
        ->card('1', 'One', 'a', ['badge' => ['Hot', 'pink'], 'labels' => ['ok', ['', 'red'], 5.5], 'due' => '2026-02-30',
            'draggable' => 'maybe', 'href' => 'javascript:alert(1)', 'avatar' => ' java script:x', 'count' => [], 'text' => ['no'], 'colour' => 'x'])
        ->card('2', 'Two', 'a', ['avatar' => 'data:text/html,<b>x</b>', 'href' => "jav\tascript:alert(1)"])
        ->card('3', 'Three', 'a', ['avatar' => 'data:image/png;base64,AAAA', 'count' => 0])
        ->render());
    $all = implode("\n", $notes);
    foreach (["column 'a' has 'limit' => 0", "column 'a' has 'color' => 'purple'", "column 'a' has 'description' => array",
              "column 'a' has the option 'width'", "column 'b' has 'limit' => true", "the colour 'pink' of 'Hot'",
              "card '1' has 'labels' => array", "card '1' has 'due' => '2026-02-30'", "card '1' has 'draggable' => 'maybe'",
              "card '1' has a script URL as 'href'", "card '1' has 'text' => array", "card '1' has the option 'colour'",
              "card '2' has a script URL as 'avatar'", "card '2' has a script URL as 'href'"] as $w) {
        T::ok(str_contains($all, $w), "warns: $w");
    }
    T::eq(kbShape($html), ['a' => ['1', '2', '3'], 'b' => []], 'every card still shows');
    T::contains($html, '<span class="gk-label gk-label-gray gk-kanban-badge">Hot</span>', 'an unknown colour is gray');
    T::contains($html, '<li class="gk-label gk-label-gray">ok</li><li class="gk-label gk-label-gray">5.5</li>', 'the good labels stay');
    T::notContains($html, 'javascript', 'no script URL reaches the page');
    T::notContains($html, 'data:text', 'no data: page as a picture');
    T::contains($html, '<img src="data:image/png;base64,AAAA"', 'a data: picture is a picture');
    T::contains($html, '<span class="gk-kanban-cardcount">0</span>', 'a count of 0 is shown');
    T::contains($html, '<div class="gk-kanban-column" data-gk-kanban-column="a">', 'no limit, no colour');
    [, $more] = kbRender(fn () => (new Kanban('k'))->wipLimits('strict')->today('tomorrow')
        ->moveUrl('/m', ['headers' => 'X', 'fields' => [5 => 'x', 'ok' => ['no']], 'method' => 'PUT'])->render());
    $m = implode("\n", $more);
    foreach (["wipLimits('strict')", "today('tomorrow')", "moveUrl() takes 'headers' as an array", "moveUrl() 'fields': 5", "moveUrl() 'fields': 'ok'",
              "moveUrl() has the option 'method'"] as $w) {
        T::ok(str_contains($m, $w), "warns: $w");
    }
},

'yes and no as a database hands them over' => function (): void {
    foreach ([[false, false], [0, false], ['0', false], ['no', false], ['off', false], [true, true], [1, true], ['yes', true]] as [$v, $moves]) {
        [$html, $notes] = kbRender(fn () => (new Kanban('k'))->movable()->column('a', 'A')->card('1', 'One', 'a', ['draggable' => $v])->render());
        T::eq($notes, [], var_export($v, true) . ' is understood');
        T::eq(!str_contains($html, 'data-gk-kanban-nodrag'), $moves, 'draggable ' . var_export($v, true));
    }
},

'labels, titles, ids, keys, links, texts and the board id are escaped' => function (): void {
    $x = '<script>alert(1)</script>"\'&';
    [$html] = kbRender(fn () => (new Kanban($x))->label($x)->empty($x)->addUrl('/n?a="' . $x)
        ->moveUrl('/m"' . $x, ['headers' => ['X' => $x], 'fields' => ['f"' => $x]])
        ->column($x, $x, ['description' => $x, 'limit' => 1])
        ->card($x, $x, $x, ['text' => $x, 'badge' => $x, 'labels' => [$x], 'assignee' => $x, 'href' => '/t?x="' . $x, 'count' => $x, 'avatar' => '/a"' . $x])
        ->render());
    T::notContains($html, '<script>', 'no raw tag');
    T::notContains($html, '"\'&', 'no raw quote or ampersand');
    T::ok(substr_count($html, '&lt;script&gt;') >= 12, 'escaped everywhere: ' . substr_count($html, '&lt;script&gt;'));
    T::contains($html, 'id="gk-kanban-h', 'an unsafe id becomes a hash in an HTML id');
    $dom = new DOMDocument();
    T::ok(@$dom->loadHTML('<?xml encoding="utf-8"?>' . $html) && $dom->getElementsByTagName('section')->length === 1, 'it parses as one board');
},

'every id on the page is unique, with two boards on it' => function (): void {
    [$html] = kbRender(function (): void {
        kbBoard()->moveUrl('/m')->addUrl('/n')->render();
        (new Kanban('other'))->column('new', 'New')->card('1', 'One', 'new')->moveUrl('/m')->render();
    });
    preg_match_all('/(?<![\w-])id="([^"]+)"/', $html, $m);
    T::eq(count($m[1]), count(array_unique($m[1])), 'duplicate ids: ' . implode(', ', array_diff_assoc($m[1], array_unique($m[1]))));
},

'in German' => function (): void {
    Lang::set('de');
    [$html] = kbRender(fn () => kbBoard()->moveUrl('/m')->wipLimits()->addUrl('/n')->render());
    Lang::set('en');
    foreach (['Karte hinzufügen', 'Verschieben nach', '2 Karten, Limit 2', '(voll)', 'Zugewiesen an Anna Huber', 'Heute fällig', 'aria-label="Support tickets"'] as $w) {
        T::contains($html, $w, "German: $w");
    }
    [$plain] = kbRender(fn () => (new Kanban('k'))->column('a', 'A')->render());
    T::contains($plain, 'aria-label="Board"', 'the default name, English');
},

// ── The script and the stylesheet ───────────────────────────────────────

'the script is a file of its own, strict, closed in a function, with GK.kanban as its only name outside' => function () use ($js, $code, $root): void {
    T::ok(is_file($root . '/js/kanban.js'), 'js/kanban.js does not exist — the dashboard keeps its own gridkit.js, a block there would never reach it');
    $c = $code();
    T::contains($c, '"use strict"', 'strict');
    T::ok((bool) preg_match('/\A\s*\(function \(\) \{/', $c), 'opens with a function that closes over everything');
    T::ok((bool) preg_match('/\}\)\(\);\s*\z/', $c), 'and calls it');
    preg_match_all('/\bwindow\.([A-Za-z_]+)(?:\.[A-Za-z_]+)?\s*=[^=]/', $c, $m);
    T::eq(array_values(array_unique($m[1])), ['GK'], 'the only global it writes is GK (GK.kanban)');
    T::ok(!preg_match('/\b(jQuery|\$\()|\bimport\s|\brequire\(/', $c), 'no dependency');
    T::contains($c, 'MutationObserver', 'a board inserted later is set up');
    T::contains($c, 'readyState', 'it starts when loaded late (defer, a fragment)');
    T::contains($c, 'WeakMap', 'one state per board');
    T::notContains($c, 'innerHTML', 'everything goes in as text or nodes');
    T::notContains($js(), '</script', 'the file can be inlined');
    exec('node --version 2>/dev/null', $probe, $hasNode);
    if ($hasNode === 0) {
        exec('node --check ' . escapeshellarg($root . '/js/kanban.js') . ' 2>&1', $out, $rc);
        T::eq($rc, 0, 'js/kanban.js parses: ' . implode(' ', $out));
    }
},

'every data-gk-kanban attribute Kanban.php writes is read by the script or the stylesheet' => function () use ($js, $root): void {
    $php = (string) file_get_contents($root . '/src/Kanban.php');
    preg_match_all('/data-gk-kanban(?:-[a-z]+)*/', $php, $m);
    $attrs = array_values(array_unique($m[0]));
    T::ok(count($attrs) >= 8, 'found ' . count($attrs) . ' attributes — the pattern broke');
    $css = gkCssText();
    foreach ($attrs as $attr) {
        T::ok(str_contains($js(), $attr) || str_contains($css, $attr), "$attr is written and read by nothing");
    }
},

'every word the script shows comes from the catalogue, in both locales, and reaches the page' => function () use ($code, $root): void {
    $en = require $root . '/lang/en.php';
    $de = require $root . '/lang/de.php';
    preg_match_all('/\bt\(\s*"(kanban_[a-z_]+)"/', $code(), $m);
    preg_match_all('/"(kanban_count(?:_limit)?(?:_one)?)"/', $code(), $m2);
    $keys = array_values(array_unique(array_merge($m[1], $m2[1])));
    T::ok(count($keys) >= 14, 'found ' . count($keys) . ' keys');
    foreach ($keys as $key) {
        T::ok(isset($en["js.$key"]), "the script asks for $key — not in lang/en.php as js.$key");
        T::ok(isset($de["js.$key"]), "the script asks for $key — not in lang/de.php as js.$key");
    }
    foreach (array_keys($en) as $k) {
        if (str_starts_with($k, 'kanban.') || str_starts_with($k, 'js.kanban_')) T::ok(isset($de[$k]), "$k has no German");
    }
    // The script counts the way the server does.
    foreach (['count', 'count_one', 'count_limit', 'count_limit_one'] as $k) T::eq($en["js.kanban_$k"], $en["kanban.$k"], "js.kanban_$k = kanban.$k");
    T::eq($en['js.kanban_full_mark'], $en['kanban.full'], 'the full mark, both sides');
    Lang::set('de');
    $cfg = Lang::jsConfig();
    foreach ($keys as $key) T::contains($cfg, '"' . $key . '"', "Lang::jsConfig() carries $key");
    Lang::set('en');
},

'the moving is all in the script: pointer, keys, the queue, the way back, the events' => function () use ($code): void {
    $c = $code();
    foreach (['"ArrowDown"', '"ArrowUp"', 'ArrowLeft', 'ArrowRight', '"Home"', '"End"', '"Enter"', '" "', '"Escape"'] as $key) {
        T::contains($c, $key, "the script never reads the key $key");
    }
    foreach (['pointerdown', 'pointermove', 'pointerup', 'pointercancel', 'setPointerCapture', 'elementFromPoint', 'scrollLeft',
              'aria-pressed', 'aria-live', 'aria-describedby', 'gk:kanbanmove', 'gk:kanbanmoved', 'cancelable', 'GK.toast'] as $w) {
        T::contains($c, $w, "the script does not use $w");
    }
    T::notContains($c, 'touchstart', 'pointer events do mouse, pen and finger');
    T::contains($c, '"POST"', 'a move is posted');
    T::contains($c, 'JSON.stringify({ id: m.detail.id, column: m.detail.to, index: m.detail.index })', 'the body is {id, column, index}');
    T::contains($c, 'credentials: "same-origin"', 'with the session');
    T::contains($c, 'if (st.busy || !st.queue.length) return;', 'one at a time');
    T::contains($c, 'var dropped = [m].concat(st.queue);', 'a failure takes the waiting moves back too');
    T::contains($c, 'restore(st, st.confirmed);', 'to what the server agreed to last');
},

'the stylesheet block: at the end, both dark spellings, only roles for colour, lanes on a phone, 44px for a finger' => function () use ($sheet): void {
    $css = gkCssText();
    $at = strpos($css, '/* === 1.124.0 — kanban === */');
    T::ok($at !== false, 'the block heading is there');
    $later = preg_match_all('~/\* === 1\.(\d+)\.~', substr($css, (int) $at + 10), $v) ? $v[1] : [];
    T::ok(array_filter($later, static fn (string $m): bool => (int) $m <= 124) === [], 'only a later release\'s block may follow it');
    T::eq(substr_count($css, '/* === 1.124.0'), 1, 'one block for the release');
    $s = $sheet();
    T::contains($s, '[data-gk-mode="dark"] .gk-kanban-card,', 'dark, attribute spelling');
    T::contains($s, '.gk-dark .gk-kanban-card {', 'dark, class spelling');
    T::ok(!preg_match('/#[0-9a-f]{3,8}\b|rgba?\(/i', $s), 'no colour literal: roles only');
    T::contains($s, 'overflow-x: auto', 'the board scrolls sideways, not the page');
    T::ok((bool) preg_match('/@media \(max-width: 600px\) \{\s*\.gk-kanban-board \{[^}]*grid-auto-columns: 85%;[^}]*scroll-snap-type: x mandatory;/s', $s), 'lanes that snap on a phone');
    T::contains($s, 'scroll-snap-align: start', 'each column snaps');
    T::ok((bool) preg_match('/\.gk-kanban-busy \.gk-kanban-board \{\s*scroll-snap-type: none;/', $s), 'no snapping while a card is dragged');
    T::ok((bool) preg_match('/@media \(pointer: coarse\) \{\s*\.gk-kanban-handle \{\s*width: var\(--gk-target-min\);\s*height: var\(--gk-target-min\);/s', $s), 'a handle a finger can take');
    T::contains($s, 'touch-action: none', 'the handle takes the finger');
    T::contains($s, '@media (forced-colors: active)', 'forced colours');
    T::contains($s, '@media (prefers-reduced-motion: no-preference)', 'motion only for those who want it');
    T::contains($s, '@media print', 'print');
    // A hidden note, empty line, error line or drop line stays hidden by the one rule for every gk- class
    // (:where([class^="gk-"], …)[hidden]); the block needs no guard of its own.
    T::contains($css, ':where([class^="gk-"], [class*=" gk-"]):not([style*="display"])[hidden]', 'the rule every hidden gk- block relies on');
    $index = json_decode((string) file_get_contents(__DIR__ . '/../css/blocks.json'), true);
    preg_match_all('/\.(gk-kanban[a-z0-9-]*)/', $s, $m);
    $classes = array_values(array_unique($m[1]));
    sort($classes);
    $listed = array_keys($index['blocks']['kanban']['classes'] ?? []);
    T::eq($listed, $classes, 'css/blocks.json lists every class of the block under "kanban"');
    T::eq(array_values(array_unique(array_values($index['blocks']['kanban']['classes'] ?? []))), ['1.124.0'], 'all at 1.124.0');
},

'the demo shows it, the skeleton and the demo load the script' => function () use ($root): void {
    $demo = demoSource();
    T::contains($demo, "->item('Kanban', '#kanban', 'view_kanban')", 'a sidebar item');
    T::ok(strpos($demo, "->item('Kanban'") > strpos($demo, "->item('Command palette'"), 'after the last component');
    T::contains($demo, 'data-section="kanban"', 'a section');
    foreach (['->moveUrl(', "->wipLimits('block')", '->compact()', '->movable()', '->addUrl(', '->empty('] as $w) T::contains($demo, $w, "the demo shows $w");
    T::contains($demo, "js/kanban.js') ?>\" defer></script>", 'the demo loads the script');
    T::contains((string) file_get_contents($root . '/skeleton.php'), "js/kanban.js') ?>\" defer></script>", 'skeleton.php too');
    T::ok(is_file($root . '/demo/api/kanban-move.php'), 'the demo endpoint');
    T::contains((string) file_get_contents($root . '/demo/api/kanban-move.php'), 'Kanban::readMove()', 'which reads both shapes');
},

'the skill documents every option, call, event and the contract' => function () use ($doc): void {
    $d = $doc();
    T::ok((bool) preg_match('/^### Kanban\b/m', $d), 'a "### Kanban" section');
    T::ok((bool) preg_match('/^\| Kanban \| `GridKit\\\\Kanban`/m', $d), 'a row in the overview');
    foreach (['js/kanban.js', 'gk:kanbanmove', 'gk:kanbanmoved', 'GK.kanban.move(', 'GK.kanban.cards(', 'GK.kanban.columns(', 'GK.kanban.refresh(',
              '{"id": "T-101", "column": "done", "index": 1}', 'Kanban::readMove(', '`Kanban::render()` (1.124.0)', 'save: false', "wipLimits('block'"] as $w) {
        T::contains($d, $w, "GRIDKIT_SKILL.md never says $w");
    }
},

];
})();
