<?php
/**
 * GridKit\CommandPalette (1.122.0) — Ctrl+K, a few letters, Enter.
 *
 * What the server writes is checked here: the closed dialog and its contract with js/command-palette.js
 * (the roles, the ids the field points at, the attributes the script reads), the configuration as JSON
 * that no label can break out of, the trigger with and without a fallback link, every option and what a
 * wrong one becomes (a warning and a palette that still renders), hostile input, the words in both
 * languages, the stylesheet block, the demo's endpoint and the pages that load the script. What the
 * script does with it — the keys, the ranking, the records, the focus, contrast — needs a browser:
 * ci/browser-palette.js.
 */

declare(strict_types=1);

use GridKit\{CommandPalette, Lang};

require_once __DIR__ . '/css-helpers.php';

/** Run, and collect what it said with E_USER_WARNING. @return array{0:mixed,1:list<string>} */
function cpRun(callable $fn): array
{
    $notes = [];
    set_error_handler(static function (int $no, string $msg) use (&$notes): bool {
        if ($no !== E_USER_WARNING) return false;
        $notes[] = $msg;
        return true;
    });
    try {
        $out = $fn();
    } finally {
        restore_error_handler();
    }
    return [$out, $notes];
}

/** The configuration the script reads, decoded. @return array<string,mixed> */
function cpConfig(string $html): array
{
    if (!preg_match('~<script type="application/json" data-gk-palette-config>(.*?)</script>~s', $html, $m)) return [];
    $cfg = json_decode($m[1], true);
    return is_array($cfg) ? $cfg : [];
}

/** The HTML as a DOM, for the questions a string search answers badly. */
function cpDom(string $html): DOMXPath
{
    $doc = new DOMDocument();
    @$doc->loadHTML('<?xml encoding="utf-8"?><body>' . $html . '</body>', LIBXML_NOERROR);
    return new DOMXPath($doc);
}

/** @return array<string,callable> */
return (static function (): array {

$root = __DIR__ . '/..';
$js   = static fn (): string => (string) @file_get_contents($root . '/js/command-palette.js');
$code = static fn (): string => (string) preg_replace(['#/\*.*?\*/#s', '#(?<![:"\'\\\\])//[^\n]*#'], '', $js());
$doc  = static fn (): string => (string) file_get_contents($root . '/GRIDKIT_SKILL.md');
/** The palette's block of the stylesheet, comments stripped. */
$sheet = static function (): string {
    $css = gkCssText();
    $from = strpos($css, '/* === 1.122.0 — command palette === */');
    if ($from === false) return '';
    $next = strpos($css, "\n/* === ", $from + 10);
    $block = $next === false ? substr($css, $from) : substr($css, $from, $next - $from);
    return (string) preg_replace('#/\*.*?\*/#s', '', $block);
};

return [

// ── The dialog ──────────────────────────────────────────────────────────

'the server writes a closed dialog: the field, an empty list, the words, the configuration' => function (): void {
    Lang::set('en');
    [$html, $notes] = cpRun(fn () => (new CommandPalette('main'))->html());
    T::eq($notes, [], 'a plain palette says nothing');
    T::contains($html, '<dialog class="gk-palette" id="gk-palette-main" data-gk-palette="main" aria-label="Command palette">', 'the dialog, its id, its name');
    T::ok((bool) preg_match('/^<dialog [^>]*>/', $html, $tag) && !preg_match('/\sopen[\s>=]/', $tag[0]), 'closed: nothing shows until the script opens it');
    T::contains($html, '<input type="text" class="gk-palette-input" id="gk-palette-main-input" role="combobox" aria-expanded="false" aria-controls="gk-palette-main-list" aria-autocomplete="list" aria-describedby="gk-palette-main-help" aria-label="Type a command or search…" placeholder="Type a command or search…" autocomplete="off" autocapitalize="off" spellcheck="false" enterkeyhint="go">', 'the field is a combobox that controls the list and is described by the keys');
    T::contains($html, '<button type="button" class="gk-palette-close" aria-label="Close" title="Close">', 'the close button has a name');
    T::contains($html, '<div class="gk-palette-list" id="gk-palette-main-list" role="listbox" aria-label="Results"></div>', 'an empty, named listbox — the script fills it');
    T::contains($html, '<p class="gk-sr-only" id="gk-palette-main-help">Up and Down move through the results, Enter opens one, Control and Enter opens a link in a new tab, Escape closes.</p>', 'the keys in words, for the field\'s description');
    T::contains($html, '<p class="gk-sr-only" aria-live="polite" data-gk-palette-live></p>', 'a polite live region for the count');
    T::contains($html, '<p class="gk-palette-empty" hidden></p><div class="gk-palette-status" hidden></div>', 'the empty sentence and the status line, hidden');
    T::contains($html, '<div class="gk-palette-foot" aria-hidden="true">', 'the line of keys is for the eye');
    T::contains($html, '<kbd class="gk-palette-kbd" data-gk-palette-mod>Ctrl</kbd>', 'the modifier the script turns into ⌘ on a Mac');
    T::ok(substr_count($html, 'aria-hidden="true" focusable="false"') === 2, 'both symbols are decoration');
    $x = cpDom($html);
    T::eq($x->query('//*[@role="option"]')->length, 0, 'the server writes no options: without the script there is nothing to choose');
    T::eq($x->query('//*[@id="gk-palette-main-help"]')->length, 1, 'the description the field names exists');
    T::eq($x->query('//*[@id="gk-palette-main-list"]')->length, 1, 'the list the field controls exists');
    T::eq(cpConfig($html), ['id' => 'main', 'hotkey' => 'mod+k', 'slash' => false, 'sidebar' => '.gk-sidebar-nav', 'recent' => 5, 'max' => 6, 'items' => [], 'search' => []],
        'the defaults the script reads');
},

'render() prints what html() returns; the id has a default' => function (): void {
    Lang::set('en');
    $p = (new CommandPalette('x'))->item('A', '/a');
    T::eq(T::capture(fn () => $p->render()), $p->html(), 'the same markup');
    [$html, $notes] = cpRun(fn () => (new CommandPalette())->html());
    T::contains((string) $html, 'data-gk-palette="main"', 'new CommandPalette() is "main"');
    T::eq($notes, [], 'and says nothing');
    [$html, $notes] = cpRun(fn () => (new CommandPalette('  '))->html());
    T::contains((string) $html, 'data-gk-palette="main"', 'an empty id becomes "main"');
    T::eq(count($notes), 1, 'with a warning');
},

'the entries reach the script as JSON, cleaned' => function (): void {
    [$html, $notes] = cpRun(fn () => (new CommandPalette('main'))
        ->item('  New   invoice ', ' /invoices/new ', ['icon' => 'add', 'group' => 'Create', 'keywords' => ['bill', ' create ', 'bill', '', 7], 'hint' => ' Billing ', 'shortcut' => 'N'])
        ->item('Dark mode', '', ['action' => 'toggle-dark', 'keywords' => 'night'])
        ->item('Theme: Ocean', '', ['action' => 'theme:ocean.v2_x-y', 'group' => 'Themes'])
        ->html());
    T::eq($notes, [], 'clean entries say nothing');
    T::eq(cpConfig((string) $html)['items'], [
        ['label' => 'New invoice', 'href' => '/invoices/new', 'icon' => 'add', 'group' => 'Create', 'keywords' => ['bill', 'create', '7'], 'hint' => 'Billing', 'shortcut' => 'N'],
        ['label' => 'Dark mode', 'action' => 'toggle-dark', 'keywords' => ['night']],
        ['label' => 'Theme: Ocean', 'action' => 'theme:ocean.v2_x-y', 'group' => 'Themes'],
    ], 'labels and texts trimmed, white space folded, keywords a list without doubles or blanks, empty options left out');
},

'items() takes rows from a database: label or title, href or url, options by name, objects too' => function (): void {
    $rows = [
        ['label' => 'Customers', 'href' => '/customers', 'icon' => 'people'],
        ['title' => 'Invoices', 'url' => '/invoices', 'group' => 'Billing', 'hint' => null],
        (object) ['title' => 'Reports', 'url' => '/reports'],
        ['title' => 7, 'url' => '/seven'],
        'not a row',
        ['href' => '/no-label'],
        ['title' => 'Bad link', 'url' => ['/x']],
        ['title' => 'Action', 'action' => 'go'],
    ];
    [$html, $notes] = cpRun(fn () => (new CommandPalette('db'))->items($rows)->html());
    T::eq(array_column(cpConfig((string) $html)['items'], 'label'), ['Customers', 'Invoices', 'Reports', '7', 'Action'], 'the good rows, in order');
    T::eq(cpConfig((string) $html)['items'][1], ['label' => 'Invoices', 'href' => '/invoices', 'group' => 'Billing'], 'title and url are read, a null option is no option');
    T::eq(count($notes), 3, 'a string, a row without a label and a link that is not text are each a warning: ' . implode(' | ', $notes));
    T::ok(str_contains($notes[0] ?? '', "command palette 'db'") && str_contains($notes[0] ?? '', 'row 4'), 'a warning names the palette and the row');
},

'a wrong entry is a warning, and the palette still renders' => function (): void {
    $cases = [
        'no label'            => fn (CommandPalette $p) => $p->item('   ', '/x'),
        'javascript:'         => fn (CommandPalette $p) => $p->item('Run', 'javascript:alert(1)'),
        'JaVaScRiPt: spaced'  => fn (CommandPalette $p) => $p->item('Run', "  JaVa\tScR\nipt:alert(1)"),
        'data:'               => fn (CommandPalette $p) => $p->item('Data', 'data:text/html,<script>alert(1)</script>'),
        'vbscript:'           => fn (CommandPalette $p) => $p->item('VB', 'vbscript:msgbox'),
        'neither'             => fn (CommandPalette $p) => $p->item('Nothing'),
        'bad action'          => fn (CommandPalette $p) => $p->item('Bad', '', ['action' => 'alert(1)']),
        'action not text'     => fn (CommandPalette $p) => $p->item('Bad', '', ['action' => ['x']]),
        'action from a digit' => fn (CommandPalette $p) => $p->item('Bad', '', ['action' => '1go']),
    ];
    foreach ($cases as $what => $add) {
        [$html, $notes] = cpRun(fn () => $add(new CommandPalette('w'))->item('Fine', '/fine')->html());
        T::eq(array_column(cpConfig((string) $html)['items'], 'label'), ['Fine'], "$what: the entry is left out, the others stay");
        T::eq(count($notes), 1, "$what: one warning");
    }
    // Options that are wrong are dropped one by one; the entry stays.
    [$html, $notes] = cpRun(fn () => (new CommandPalette('w'))->item('Kept', '/kept', [
        'icon' => 'Not An Icon', 'group' => ['x'], 'hint' => true, 'shortcut' => 3.5, 'keywords' => 42, 'colour' => 'red',
    ])->html());
    T::eq(cpConfig((string) $html)['items'], [['label' => 'Kept', 'href' => '/kept', 'shortcut' => '3.5']], 'the entry stays with what was right');
    T::eq(count($notes), 5, 'icon, group, hint, keywords and the unknown option: five warnings — ' . implode(' | ', $notes));
    // A link and an action: a link.
    [$html, $notes] = cpRun(fn () => (new CommandPalette('w'))->item('Both', '/both', ['action' => 'both'])->html());
    T::eq(cpConfig((string) $html)['items'], [['label' => 'Both', 'href' => '/both']], 'a link and an action is a link');
    T::eq(count($notes), 1, 'with a warning');
    // Links that are not dangerous pass.
    [$html, $notes] = cpRun(fn () => (new CommandPalette('w'))->item('Mail', 'mailto:office@example.org')->item('Rel', '../x?a=1&b=2#c')
        ->item('Abs', 'https://example.org/javascript:x')->item('Word', 'javascriptish')->html());
    T::eq(count(cpConfig((string) $html)['items']), 4, 'mailto:, relative, absolute and a word that only starts like a scheme are links');
    T::eq($notes, [], 'and say nothing');
},

'the options and their limits' => function (): void {
    [$html, $notes] = cpRun(fn () => (new CommandPalette('o'))->recent(0)->maxPerGroup(50)->sidebar(false)->slash()->hotkey(false)
        ->placeholder('Find…')->label('Jump to')->html());
    $cfg = cpConfig((string) $html);
    T::eq([$cfg['recent'], $cfg['max'], $cfg['sidebar'], $cfg['slash'], $cfg['hotkey']], [0, 50, '', true, ''], 'recent 0, max 50, no sidebar, slash, no key');
    T::contains((string) $html, 'aria-label="Jump to"', 'the label names the dialog');
    T::contains((string) $html, 'placeholder="Find…" ', 'the placeholder');
    T::contains((string) $html, 'aria-label="Find…"', 'and names the field');
    T::eq($notes, [], 'in range: nothing said');
    [$html] = cpRun(fn () => (new CommandPalette('o'))->sidebar('#main-nav .links')->html());
    T::eq(cpConfig((string) $html)['sidebar'], '#main-nav .links', 'a selector for another navigation');
    foreach ([
        'recent(-1)'       => fn (CommandPalette $p) => $p->recent(-1),
        'recent(21)'       => fn (CommandPalette $p) => $p->recent(21),
        'maxPerGroup(0)'   => fn (CommandPalette $p) => $p->maxPerGroup(0),
        'maxPerGroup(51)'  => fn (CommandPalette $p) => $p->maxPerGroup(51),
        "sidebar('')"      => fn (CommandPalette $p) => $p->sidebar('  '),
    ] as $what => $set) {
        [$html, $notes] = cpRun(fn () => $set(new CommandPalette('o'))->html());
        $cfg = cpConfig((string) $html);
        T::eq([$cfg['recent'], $cfg['max'], $cfg['sidebar']], [5, 6, '.gk-sidebar-nav'], "$what falls back to the default");
        T::eq(count($notes), 1, "$what: one warning");
    }
},

'the hotkey: one spelling, and a wrong one falls back to mod+k' => function (): void {
    $good = ['mod+k' => 'mod+k', 'Shift+Mod+P' => 'mod+shift+p', 'alt + k' => 'alt+k', 'ctrl+/' => 'ctrl+/', 'meta+alt+1' => 'meta+alt+1', 'mod+mod+k' => 'mod+k', '' => ''];
    foreach ($good as $in => $out) {
        [$html, $notes] = cpRun(fn () => (new CommandPalette('h'))->hotkey($in)->html());
        T::eq(cpConfig((string) $html)['hotkey'], $out, "hotkey('$in')");
        T::eq($notes, [], "hotkey('$in') says nothing");
    }
    foreach (['k', 'shift+k', 'mod+', 'hyper+k', 'mod+kk', 'mod+k+x', '+k', 'mod+ä', 'mod+<'] as $bad) {
        [$html, $notes] = cpRun(fn () => (new CommandPalette('h'))->hotkey($bad)->html());
        T::eq(cpConfig((string) $html)['hotkey'], 'mod+k', "hotkey('$bad') opens with mod+k");
        T::eq(count($notes), 1, "hotkey('$bad') is a warning");
    }
},

'search URLs: same shape for the script, options checked, a script URL refused' => function (): void {
    [$html, $notes] = cpRun(fn () => (new CommandPalette('s'))
        ->search('/api/search')
        ->search('api/people.php?scope=all', ['minLength' => 0, 'debounce' => 2000, 'group' => 'People', 'param' => 'term'])
        ->html());
    T::eq(cpConfig((string) $html)['search'], [
        ['url' => '/api/search', 'minLength' => 2, 'debounce' => 250],
        ['url' => 'api/people.php?scope=all', 'minLength' => 0, 'debounce' => 2000, 'group' => 'People', 'param' => 'term'],
    ], 'every URL with its options and the defaults');
    T::eq($notes, [], 'and nothing said');
    [$html, $notes] = cpRun(fn () => (new CommandPalette('s'))->search('')->search('javascript:alert(1)')->search(' DATA:x')->html());
    T::eq(cpConfig((string) $html)['search'], [], 'an empty URL and a script URL are not searched');
    T::eq(count($notes), 3, 'three warnings');
    [$html, $notes] = cpRun(fn () => (new CommandPalette('s'))->search('/x', ['minLength' => 11, 'debounce' => -1, 'group' => '', 'param' => 5, 'wait' => 1])->html());
    T::eq(cpConfig((string) $html)['search'], [['url' => '/x', 'minLength' => 2, 'debounce' => 250]], 'wrong options fall back');
    T::eq(count($notes), 5, 'one warning each');
},

// ── The trigger ─────────────────────────────────────────────────────────

'the trigger: a hidden button the script shows, its key in words and in aria-keyshortcuts' => function (): void {
    Lang::set('en');
    [$html, $notes] = cpRun(fn () => (new CommandPalette('main'))->trigger());
    T::eq($notes, [], 'nothing said');
    T::ok(str_starts_with((string) $html, '<button type="button" class="gk-palette-trigger" data-gk-palette-open="main" aria-keyshortcuts="Control+K" hidden>'),
        'a button, hidden without the script, naming its palette and its key: ' . substr((string) $html, 0, 160));
    T::contains((string) $html, '<span class="gk-palette-trigger-label">Search</span>', 'its word is its name');
    T::contains((string) $html, '<kbd class="gk-palette-kbd" data-gk-palette-hotkey="mod+k" aria-hidden="true">Ctrl K</kbd>', 'the key for the eye; the script writes the Mac\'s');
    T::notContains((string) $html, 'aria-label', 'the visible word is the name (WCAG 2.5.3)');
    [$html] = cpRun(fn () => (new CommandPalette('main'))->slash()->hotkey('mod+shift+p')->trigger(['label' => 'Jump', 'class' => 'my-one two', 'kbd' => 'yes']));
    T::contains((string) $html, 'class="gk-palette-trigger my-one two"', 'more classes');
    T::contains((string) $html, 'aria-keyshortcuts="Control+Shift+P /"', 'both keys');
    T::contains((string) $html, '>Ctrl Shift P</kbd>', 'the combination in words');
    T::contains((string) $html, '>Jump</span>', 'its own word');
    [$html] = cpRun(fn () => (new CommandPalette('main'))->hotkey(false)->slash()->trigger());
    T::contains((string) $html, 'aria-keyshortcuts="/"', 'only "/": that is the key');
    T::contains((string) $html, 'data-gk-palette-hotkey="/" aria-hidden="true">/</kbd>', 'and shown');
    [$html] = cpRun(fn () => (new CommandPalette('main'))->hotkey(false)->trigger());
    T::notContains((string) $html, 'kbd', 'no key: no hint');
    T::notContains((string) $html, 'aria-keyshortcuts', 'and no shortcut announced');
    [$html, $notes] = cpRun(fn () => (new CommandPalette('main'))->trigger(['kbd' => false, 'label' => '', 'class' => 3, 'size' => 'sm']));
    T::notContains((string) $html, '<kbd', 'kbd false: no key shown');
    T::contains((string) $html, '>Search</span>', 'an empty label keeps the word');
    T::eq(count($notes), 3, 'label, class and the unknown option: three warnings — ' . implode(' | ', $notes));
},

'with fallback() the trigger is a link to a search page, shown without the script' => function (): void {
    [$html, $notes] = cpRun(fn () => (new CommandPalette('main'))->fallback('/search?q=&x="y"')->trigger());
    T::ok(str_starts_with((string) $html, '<a href="/search?q=&amp;x=&quot;y&quot;" class="gk-palette-trigger" data-gk-palette-open="main"'), 'a link, escaped: ' . substr((string) $html, 0, 120));
    T::ok(!preg_match('/^<a [^>]*\shidden[\s>]/', (string) $html), 'not hidden: it works without the script');
    T::eq($notes, [], 'nothing said');
    [$html, $notes] = cpRun(fn () => (new CommandPalette('main'))->fallback('javascript:alert(1)')->trigger());
    T::ok(str_starts_with((string) $html, '<button'), 'a script URL is no fallback: the hidden button');
    T::eq(count($notes), 1, 'with a warning');
},

// ── Hostile input ───────────────────────────────────────────────────────

'every text is escaped in the markup, and no label can end the JSON\'s <script>' => function (): void {
    $evil = '</script><script>alert(1)</script><!-- "\' & <img src=x onerror=alert(2)>';
    [$html] = cpRun(fn () => (new CommandPalette('a"b<c>'))
        ->item($evil, '/x?a=1&b="2"', ['hint' => $evil, 'group' => $evil, 'keywords' => [$evil], 'shortcut' => $evil])
        ->search('/s?x=</script>', ['group' => $evil])
        ->placeholder($evil)->label($evil)->sidebar('a[href="</script>"]')
        ->html());
    $html = (string) $html;
    T::eq(substr_count($html, '</script>'), 1, 'one </script>: the end of the configuration, nothing a label wrote');
    T::eq(substr_count(strtolower($html), '<script'), 1, 'one <script: the configuration');
    T::notContains($html, '<img', 'no element from a text');
    T::notContains($html, '<!--', 'no comment opened by a text');
    T::contains($html, 'data-gk-palette="a&quot;b&lt;c&gt;"', 'the id in an attribute, escaped');
    T::contains($html, 'id="gk-palette-h', 'an id with odd letters is hashed for the DOM ids');
    T::contains($html, 'aria-label="&lt;/script&gt;&lt;script&gt;alert(1)&lt;/script&gt;&lt;!-- &quot;&#039; &amp; &lt;img src=x onerror=alert(2)&gt;"', 'the label, escaped');
    preg_match('~data-gk-palette-config>(.*?)</script>~s', $html, $m);
    T::ok(!preg_match('/[<>&\']/', $m[1] ?? 'x'), 'the JSON carries no <, >, & or \' at all — each is \\u003C and so on');
    $cfg = cpConfig($html);
    T::eq($cfg['items'][0]['label'] ?? null, trim((string) preg_replace('/\s+/', ' ', $evil)), 'and decodes to the label as given');
    T::eq($cfg['search'][0]['url'] ?? null, '/s?x=</script>', 'the URL too');
    T::eq($cfg['id'] ?? null, 'a"b<c>', 'and the id');
},

'invalid UTF-8, a thousand entries and long texts: still one valid page' => function (): void {
    [$html, $notes] = cpRun(function () {
        $p = new CommandPalette('big');
        $p->item("Bad \xC3\x28 byte", '/bad', ['hint' => "\xFF\xFE"]);
        for ($i = 0; $i < 1000; $i++) $p->item("Entry $i " . str_repeat('x', 50), "/e/$i", ['group' => 'G' . ($i % 7)]);
        $p->item(str_repeat('long ', 2000), '/long');
        return $p->html();
    });
    $cfg = cpConfig((string) $html);
    T::eq(count($cfg['items'] ?? []), 1002, 'every entry is there, the JSON is valid');
    T::contains($cfg['items'][0]['label'] ?? '', 'Bad', 'the bad byte is replaced, the label kept');
    T::eq($notes, [], 'and nothing said');
},

// ── The words ───────────────────────────────────────────────────────────

'every word the script shows is in both catalogues, the same as its English floor, and reaches the page' => function () use ($code, $js, $root): void {
    $en = require $root . '/lang/en.php';
    $de = require $root . '/lang/de.php';
    preg_match_all('/\bt\(\s*"(palette_[a-z_]+)"/', $code(), $m);
    preg_match_all('/"(palette_foot_[a-z]+)"/', $code(), $m2);
    $keys = array_values(array_unique(array_merge($m[1], $m2[1])));
    T::ok(count($keys) >= 20, 'found ' . count($keys) . ' keys');
    foreach ($keys as $key) {
        T::ok(isset($en["js.$key"]), "the script asks for $key — not in lang/en.php as js.$key");
        T::ok(isset($de["js.$key"]), "the script asks for $key — not in lang/de.php as js.$key");
    }
    // The script's English floor (a page without Lang::jsConfig()) is the catalogue's English, word for word.
    preg_match('/var EN = \{(.*?)\n  \};/s', $js(), $floor);
    preg_match_all('/(palette_[a-z_]+): "((?:[^"\\\\]|\\\\.)*)"/', $floor[1] ?? '', $pairs, PREG_SET_ORDER);
    T::ok(count($pairs) >= 20, 'the floor holds ' . count($pairs) . ' words');
    foreach ($pairs as [, $k, $v]) {
        T::eq(stripcslashes($v), $en["js.$k"] ?? null, "the floor's $k is the catalogue's");
    }
    $jsKeys = array_filter(array_keys($en), static fn (string $k): bool => str_starts_with($k, 'js.palette_'));
    T::eq(count($pairs), count($jsKeys), 'the floor has every js.palette_ word of the catalogue');
    Lang::set('de');
    $cfg = Lang::jsConfig();
    foreach ($keys as $key) T::contains($cfg, '"' . $key . '"', "Lang::jsConfig() carries $key");
    Lang::set('en');
},

'in German the dialog and the trigger speak German' => function (): void {
    Lang::set('de');
    $p = new CommandPalette('main');
    $html = $p->html() . $p->trigger();
    Lang::set('en');
    foreach (['aria-label="Befehlspalette"', 'placeholder="Befehl oder Suchbegriff eingeben…"', 'aria-label="Schließen"', 'aria-label="Ergebnisse"',
              '>Suchen</span>', ' wechseln</span>', ' schließen</span>'] as $w) {
        T::contains($html, $w, 'German: ' . $w);
    }
    foreach (['Command palette', 'Type a command', '>Search<', 'Results', 'move<'] as $w) {
        T::notContains($html, $w, 'no English left: ' . $w);
    }
},

// ── The contract with the script and the stylesheet ─────────────────────

'the script reads what the server writes, and does what the pattern asks' => function () use ($code): void {
    $c = $code();
    foreach (['data-gk-palette', 'data-gk-palette-open', 'data-gk-palette-config', 'data-gk-palette-hotkey', 'data-gk-palette-mod', 'data-gk-palette-live',
              '.gk-palette-input', '.gk-palette-list', '.gk-palette-status', '.gk-palette-empty', '.gk-palette-close'] as $w) {
        T::contains($c, $w, "the script never reads $w");
    }
    foreach (['showModal', '"group"', '"option"', 'aria-activedescendant', 'aria-selected', 'aria-expanded', 'aria-busy', 'aria-labelledby',
              'aria-haspopup', 'aria-keyshortcuts', '"ArrowDown"', '"ArrowUp"', '"Home"', '"End"', '"PageDown"', '"PageUp"', '"Enter"', '"Escape"', '"Tab"',
              'gk:paletteopen', 'gk:paletteselect', 'gk:paletteaction', 'AbortController', 'credentials: "same-origin"', '"X-Requested-With": "XMLHttpRequest"',
              'location.origin', 'isContentEditable', 'stopPropagation', 'noopener', 'scrollIntoView', 'MutationObserver'] as $w) {
        T::contains($c, $w, "the script never uses $w");
    }
    T::ok((bool) preg_match('/try \{ raw = window\.localStorage\.getItem/', $c) && (bool) preg_match('/try \{ window\.localStorage\.setItem/', $c),
        'localStorage is read and written inside try');
    T::contains($c, 'theme._key(name)', 'the recent list is kept in GK.theme\'s scope');
    T::contains($c, 'GK_THEME_SCOPE', 'or the page\'s scope without GK.theme');
    T::ok(substr_count($c, 'textContent') > 15 && !preg_match('/innerHTML\s*=(?!\s*\'<div class="gk-palette-head">)/', $c),
        'labels go in as text: the one innerHTML is the fixed shell of a palette the script builds');
    T::contains($c, 'window.addEventListener("keydown", onPageKey, true)', 'the key is taken first (capture, on window)');
    T::notContains($js = (string) file_get_contents(__DIR__ . '/../js/command-palette.js'), '</script', 'the file can be inlined: no </script in it');
},

'the stylesheet block: at the end, roles for colour, both dark spellings, a sheet on a phone' => function () use ($sheet): void {
    $css = gkCssText();
    $at = strpos($css, '/* === 1.122.0 — command palette === */');
    T::ok($at !== false, 'the block heading is there');
    T::ok($at > (int) strrpos($css, '/* === 1.118.0'), 'after the block of 1.118.0');
    $later = preg_match_all('~/\* === 1\.(\d+)\.~', substr($css, (int) $at + 10), $v) ? $v[1] : [];
    T::ok(array_filter($later, static fn (string $m): bool => (int) $m <= 122) === [], 'only a later release may follow it');
    $s = $sheet();
    foreach (['[data-gk-mode="dark"] .gk-palette,', '.gk-dark .gk-palette {', '[data-gk-mode="dark"] .gk-palette::backdrop', '.gk-dark .gk-palette::backdrop',
              '.gk-dark .gk-palette-input'] as $w) {
        T::contains($s, $w, "dark: $w");
    }
    T::contains($s, '@media (max-width: 600px)', 'a phone');
    T::ok((bool) preg_match('/@media \(max-width: 600px\) \{\s*\.gk-palette \{[^}]*height: 100dvh;[^}]*margin: 0;/s', $s), 'the dialog covers the screen there');
    T::contains($s, 'var(--gk-target-min)', 'targets of a finger');
    T::contains($s, '@media (prefers-reduced-motion: no-preference)', 'it moves only for those who want it');
    T::contains($s, '@media (forced-colors: active)', 'the selection in forced colours');
    T::contains($s, '.gk-palette-trigger[hidden]', 'a hidden trigger stays hidden under display: inline-flex');
    T::contains($s, '.gk-palette-status[hidden]', 'a hidden status line too');
    T::contains($s, 'text-overflow: ellipsis', 'a long name is cut, not pushed sideways');
    T::contains($s, 'overflow-x: hidden', 'the list never scrolls sideways');
    preg_match_all('/(?<![-\w])(?:color|background(?:-color)?|border(?:-[a-z]+)?|box-shadow|outline):[^;]*?(#[0-9a-f]{3,8}\b|rgba?\()/i', $s, $lit);
    T::eq(count($lit[0]), 2, 'colours are roles; the two literals are the veil behind it, light and dark: ' . implode(' | ', $lit[0]));
},

'every class of the block is in the index, at 1.122.0, as "Command palette"' => function () use ($sheet, $root): void {
    preg_match_all('/\.(gk-palette[a-z0-9-]*)/', $sheet(), $m);
    $classes = array_values(array_unique($m[1]));
    sort($classes);
    $json = json_decode((string) file_get_contents($root . '/css/blocks.json'), true);
    $block = $json['blocks']['palette'] ?? [];
    T::eq($block['name'] ?? '', 'Command palette', 'the block\'s name');
    $listed = array_keys($block['classes'] ?? []);
    sort($listed);
    T::eq($listed, $classes, 'the index lists exactly the classes of the block');
    T::eq(array_values(array_unique(array_values($block['classes'] ?? []))), ['1.122.0'], 'all of them new in 1.122.0');
    T::eq(count($classes), 22, 'twenty-two classes');
},

// ── The pages that load it, the demo, the skill ─────────────────────────

'skeleton.php and the demo load the script and place a palette' => function () use ($root): void {
    $skeleton = (string) file_get_contents($root . '/skeleton.php');
    T::contains($skeleton, "js/command-palette.js') ?>\" defer></script>", 'skeleton.php loads js/command-palette.js, deferred');
    T::contains($skeleton, '$palette->render();', 'and renders its palette');
    T::contains($skeleton, '->action($palette->trigger())', 'with the trigger in the header');
    $demo = demoSource();
    T::contains($demo, "js/command-palette.js') ?>\" defer></script>", 'the demo loads it');
    T::contains($demo, '$demoPalette->render();', 'and renders the palette');
    T::contains($demo, "->item('Command palette', '#palette', 'keyboard_command_key')", 'the sidebar has the section');
    T::ok(strpos($demo, "->item('Command palette'") > strpos($demo, "->item('Line items'"), 'after Line items');
    T::contains($demo, "since('palette')", 'the section carries its mark');
    T::contains($demo, "->search('api/palette.php'", 'the demo searches its records');
    T::contains($demo, 'GK.palette.register(', 'and registers the headings from a script');
    [$out, $err] = (static function () use ($root): array {
        $p = proc_open([PHP_BINARY, $root . '/skeleton.php'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);
        $o = stream_get_contents($pipes[1]);
        $e = stream_get_contents($pipes[2]);
        proc_close($p);
        return [(string) $o, (string) $e];
    })();
    T::eq($err, '', 'the skeleton runs quietly');
    T::contains($out, '<dialog class="gk-palette" id="gk-palette-main"', 'and writes its palette');
},

'the demo endpoint answers in the documented shape, folds umlauts both ways, fails on purpose' => function () use ($root): void {
    $ask = static function (string $q) use ($root): array {
        $p = proc_open([PHP_BINARY, '-r', '$_GET = ["q" => ' . var_export($q, true) . ']; include ' . var_export($root . '/demo/api/palette.php', true) . ';'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $o = (string) stream_get_contents($pipes[1]);
        $e = (string) stream_get_contents($pipes[2]);
        proc_close($p);
        return [json_decode($o, true), $e];
    };
    [$r, $err] = $ask('eck');
    T::eq($err, '', 'quiet');
    T::eq($r['groups'][0]['title'] ?? '', 'Customers', 'groups with a title');
    foreach (['title', 'subtitle', 'url', 'icon'] as $k) T::ok(isset($r['groups'][0]['items'][0][$k]), "a record has $k");
    T::eq(array_column($r['groups'], 'title'), ['Customers', 'Invoices'], 'customers and the invoices that name them');
    foreach (['muller', 'mueller', 'Müller', 'MÜLLER'] as $q) {
        [$r] = $ask($q);
        T::eq($r['groups'][0]['items'][0]['title'] ?? '', 'Müller & Söhne GmbH', "\"$q\" finds Müller");
    }
    [$r] = $ask('gross');
    T::eq($r['groups'][0]['items'][0]['title'] ?? '', 'Bäckerei Groß', '"gross" finds Groß');
    [$r] = $ask('');
    T::eq($r, ['groups' => []], 'nothing asked, nothing found');
    [$r] = $ask('<script>');
    T::eq($r, ['groups' => []], 'a hostile query finds nothing and breaks nothing');
    [$r] = $ask('fail');
    T::ok(isset($r['error']), '"fail" fails on purpose');
    $src = (string) file_get_contents($root . '/demo/api/palette.php');
    T::ok(!preg_match('/\bmb_[a-z_]+\s*\(/', $src), 'no mbstring needed');
},

'the skill documents every option, call, event and the JSON' => function () use ($doc): void {
    $d = $doc();
    T::ok((bool) preg_match('/^### CommandPalette\b/m', $d), 'a "### CommandPalette" section');
    foreach (['js/command-palette.js', 'gk:paletteopen', 'gk:paletteselect', 'gk:paletteaction', 'GK.palette.open(', 'GK.palette.close(', 'GK.palette.register(',
              '->item(', '->items(', '->search(', '->sidebar(', '->recent(', '->maxPerGroup(', '->hotkey(', '->slash(', '->placeholder(', '->label(',
              '->fallback(', '->trigger(', '->render()', '->html()', 'minLength', 'debounce', 'param', 'keywords', 'shortcut', '"groups"', 'same origin',
              'gk-palette-<id>', 'GK_THEME_SCOPE', 'aria-activedescendant', 'Ctrl+Enter', 'CommandPalette::render()', 'CommandPalette::trigger()'] as $w) {
        T::contains($d, $w, "GRIDKIT_SKILL.md never says $w");
    }
    T::ok((bool) preg_match('/^\| CommandPalette \| `GridKit\\\\CommandPalette`/m', $d), 'a row in the table of components');
},

];
})();
