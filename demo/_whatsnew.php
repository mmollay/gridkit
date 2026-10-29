<?php
/**
 * What is new, and since when — read, never written a second time. (1.93.0)
 *
 * Asked for by the person who uses the demo most: "make the new things
 * visible — NEW marks in the demo like Fomantic UI has, so you know what has
 * changed; at least the news of each version in the changelog."
 *
 * Two sources, each the only one:
 *
 *  - CHANGELOG.md for the words. The box at the top of the demo is one card per
 *    release (1.103.1): version, date, the first sentence of its introduction,
 *    its Added / Changed / Fixed entries counted, the classes it brought; the
 *    newest three open, older ones folded. Nothing here restates what a release
 *    did — "Tests" and "Notes" say how it was checked, not what it brings, and
 *    stay in the changelog.
 *  - css/blocks.json for the facts: every gk- class, the release that first
 *    shipped it, and the block the demo shows it as. The marks beside the
 *    headings are computed from it. tests/blocks.test.php keeps it complete —
 *    a class in the stylesheet that is not in it fails the suite.
 */

declare(strict_types=1);

/** The directory everything is read from — the repository root. */
function gkRepoRoot(): string
{
    return dirname(__DIR__);
}

/** "1.92.0" → "1.92". */
function gkMinor(string $version): string
{
    $p = explode('.', $version);
    return ($p[0] ?? '0') . '.' . ($p[1] ?? '0');
}

/**
 * The block index, with each block's "since" and "changed" worked out.
 *
 * "since" is the oldest class of the block; "changed" the newer of what the
 * file says and the newest class — a class added to a block is a change to
 * it, whether or not anybody wrote that down.
 *
 * @return array<string, array{name: string, since: string, changed: ?string, classes: array<string,string>}>
 */
function gkBlocks(?string $file = null): array
{
    static $cache = [];
    $file ??= gkRepoRoot() . '/css/blocks.json';
    if (isset($cache[$file])) return $cache[$file];

    $json = json_decode((string) file_get_contents($file), true);
    $out = [];
    foreach (($json['blocks'] ?? []) as $key => $b) {
        $versions = array_values($b['classes'] ?? []);
        usort($versions, 'version_compare');
        $since = $versions[0] ?? '0.0.0';
        $newest = $versions ? $versions[count($versions) - 1] : $since;
        $changed = $b['changed'] ?? null;
        if (version_compare($newest, $since, '>') && ($changed === null || version_compare($newest, $changed, '>'))) {
            $changed = $newest;
        }
        $out[(string) $key] = [
            'name'    => (string) ($b['name'] ?? $key),
            'since'   => $since,
            'changed' => $changed,
            'classes' => $b['classes'] ?? [],
        ];
    }
    return $cache[$file] = $out;
}

/** A text without its fenced code blocks (their lines start with "- " too, and are not entries). */
function gkStripFences(string $md): string
{
    return preg_replace('/^[ \t]*```.*?^[ \t]*```[^\n]*$/ms', '', $md) ?? $md;
}

/** The first sentence of a text, the whitespace of its lines closed up. */
function gkFirstSentence(string $text): string
{
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    $parts = preg_split('/(?<=[.!?])\s+(?=[A-ZÄÖÜ])/u', $text, 2);
    return $parts[0] ?? '';
}

/**
 * A markdown text cut at a word, with an ellipsis — and never in the middle of
 * a `code` span or a **bold** pair, which gkInlineMd would show as stray marks.
 */
function gkShorten(string $md, int $max): string
{
    if (mb_strlen($md) <= $max) return $md;
    $cut = mb_substr($md, 0, $max);
    $space = mb_strrpos($cut, ' ');
    if ($space !== false && $space > $max / 2) $cut = mb_substr($cut, 0, $space);
    if (substr_count($cut, '`') % 2 === 1) $cut = mb_substr($cut, 0, (int) mb_strrpos($cut, '`'));
    if (substr_count($cut, '**') % 2 === 1) $cut = mb_substr($cut, 0, (int) mb_strrpos($cut, '**'));
    return rtrim($cut, " ,;:-—") . '…';
}

/**
 * What an entry of a list is about, in a few words: a bold start ("**Looks**
 * (one per row): …" → "Looks"), else the words up to the first colon or the end
 * of the first sentence.
 */
function gkLead(string $md): string
{
    $md = trim(preg_replace('/\[([^\]]+)\]\([^)]*\)/', '$1', $md) ?? $md);
    if (preg_match('/^\*\*(.+?)\*\*/u', $md, $m) && !str_ends_with($m[1], ':')) {
        $lead = $m[1];
    } else {
        $lead = gkFirstSentence($md);
        if (preg_match('/^(.{8,}?):\s/u', $lead . ' ', $m)) $lead = $m[1];
    }
    return gkShorten(rtrim($lead, ' .:;,'), 90);
}

/** The "- " entries of one section, a wrapped entry joined to one line. @return list<string> */
function gkBullets(string $text): array
{
    $items = [];
    $cur = null;
    foreach (preg_split('/\R/', $text) ?: [] as $line) {
        if (preg_match('/^- (.*)$/', $line, $m)) {
            if ($cur !== null) $items[] = $cur;
            $cur = $m[1];
        } elseif ($cur !== null && trim($line) !== '' && !str_starts_with($line, '#')) {
            $cur .= ' ' . trim($line);
        } elseif ($cur !== null) {
            $items[] = $cur;
            $cur = null;
        }
    }
    if ($cur !== null) $items[] = $cur;
    return $items;
}

/**
 * The introduction of an entry — the paragraph under its heading — else the
 * first prose paragraph under its first Added / Changed / Fixed heading (the
 * older entries have none): one sentence, at most 240 characters.
 */
function gkEntryIntro(string $body): string
{
    $body = gkStripFences($body);
    $parts = preg_split('/^### /m', $body);
    $paragraphs = preg_split('/\n\s*\n/', trim(preg_replace('/^---\s*$/m', '', $parts[0]) ?? '')) ?: [];
    $first = trim($paragraphs[0] ?? '');
    if ($first === '' || str_starts_with($first, '- ')) {
        $first = '';
        foreach (array_slice($parts, 1) as $section) {
            $lines = explode("\n", $section, 2);
            if (!preg_match('/^(Added|Changed|Fixed|Neu|Behoben|Geändert)\b/u', $lines[0])) continue;
            foreach (preg_split('/\n\s*\n/', trim($lines[1] ?? '')) ?: [] as $para) {
                $para = trim($para);
                if ($para !== '' && !preg_match('/^([-|>#]|\d+\.\s)/', $para)) { $first = $para; break 2; }
            }
        }
    }
    return gkShorten(gkFirstSentence($first), 240);
}

/**
 * What an entry brings, by kind: Added, Changed, Fixed, Removed — each with the
 * number of its entries and their leads. A "### Added — a title" heading is one
 * entry (the title); a plain "### Added" is the "- " entries under it. Notes,
 * Tests, Migration and the rest are not "what is new" and are left out.
 *
 * @return array<string, array{count: int, items: list<string>}> only the kinds the entry has
 */
function gkEntryGroups(string $body): array
{
    $kinds = ['Added' => 'Added', 'Neu' => 'Added', 'Changed' => 'Changed', 'Geändert' => 'Changed',
        'Fixed' => 'Fixed', 'Behoben' => 'Fixed', 'Removed' => 'Removed', 'Entfernt' => 'Removed'];
    $parts = preg_split('/^### (.+)$/m', gkStripFences($body), -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
    $found = [];
    for ($i = 1; $i < count($parts); $i += 2) {
        if (!preg_match('/^(Added|Neu|Changed|Geändert|Fixed|Behoben|Removed|Entfernt)\b(.*)$/u', trim($parts[$i]), $m)) continue;
        $kind = $kinds[$m[1]];
        $title = trim(preg_replace('/^[\s—–:-]+/u', '', $m[2]) ?? '');
        $items = $title !== '' ? [gkLead($title)] : array_map('gkLead', gkBullets($parts[$i + 1] ?? ''));
        $found[$kind] = array_merge($found[$kind] ?? [], $items);
    }
    $out = [];
    foreach (['Added', 'Changed', 'Fixed', 'Removed'] as $kind) {
        if (!empty($found[$kind])) $out[$kind] = ['count' => count($found[$kind]), 'items' => $found[$kind]];
    }
    return $out;
}

/**
 * The release entries of CHANGELOG.md, newest first: version, date, its "###"
 * headings (without "Tests"), the introduction's first sentence and the groups
 * of what it brings.
 *
 * @return list<array{version: string, date: string, headings: list<string>, intro: string, groups: array<string, array{count: int, items: list<string>}>}>
 */
function gkChangelog(?string $file = null): array
{
    static $cache = [];
    $md = (string) file_get_contents($file ?? gkRepoRoot() . '/CHANGELOG.md');
    $key = strlen($md) . ':' . md5($md);
    if (isset($cache[$key])) return $cache[$key];

    preg_match_all('/^## \[(\d+\.\d+\.\d+)\](?:\s*-\s*(\S+))?[^\n]*\n(.*?)(?=^## |\z)/ms', $md, $m, PREG_SET_ORDER);
    $out = [];
    foreach ($m as $e) {
        preg_match_all('/^### (.+)$/m', $e[3], $h);
        // "Tests" says how a release was checked, not what it brings.
        $headings = array_values(array_filter(array_map('trim', $h[1]), static fn(string $t): bool => $t !== 'Tests'));
        $out[] = [
            'version'  => $e[1],
            'date'     => $e[2] ?? '',
            'headings' => $headings,
            'intro'    => gkEntryIntro($e[3]),
            'groups'   => gkEntryGroups($e[3]),
        ];
    }
    return $cache[$key] = $out;
}

/**
 * The newest minor releases the changelog names — the two the marks speak of.
 *
 * @param list<array{version: string}> $entries
 * @return list<string> e.g. ['1.93', '1.92']
 */
function gkRecentMinors(array $entries, int $n = 2): array
{
    $minors = [];
    foreach ($entries as $e) {
        $minor = gkMinor($e['version']);
        if (!in_array($minor, $minors, true)) $minors[] = $minor;
        if (count($minors) === $n) break;
    }
    return $minors;
}

/**
 * Whether a block is new or changed in one of the recent minors.
 *
 * @param array{since: string, changed: ?string} $block
 * @param list<string> $recent
 * @return array{new: ?string, changed: ?string} the minor, or null
 */
function gkBlockState(array $block, array $recent): array
{
    $new = in_array(gkMinor($block['since']), $recent, true) ? gkMinor($block['since']) : null;
    $changed = null;
    if ($block['changed'] !== null && in_array(gkMinor($block['changed']), $recent, true)
        && gkMinor($block['changed']) !== $new) {
        $changed = gkMinor($block['changed']);
    }
    return ['new' => $new, 'changed' => $changed];
}

/** The words of the marks, in the language the page speaks. */
function gkMarkWords(): array
{
    $de = str_starts_with(\GridKit\Lang::locale(), 'de');
    return $de
        ? ['since' => 'seit', 'new' => 'Neu in', 'changed' => 'Geändert in']
        : ['since' => 'since', 'new' => 'New in', 'changed' => 'Changed in'];
}

/** A mark: an existing label colour, nothing invented — green new, blue changed. */
function gkMark(string $kind, string $minor): string
{
    $w = gkMarkWords();
    $class = $kind === 'new' ? 'gk-label-green' : 'gk-label-blue';
    return '<span class="gk-label ' . $class . '">' . htmlspecialchars($w[$kind] . ' ' . $minor, ENT_QUOTES, 'UTF-8') . '</span>';
}

/**
 * What a heading in the demo carries after its name: "since 1.0" and, for a
 * block new or changed in the last two minors, its mark.
 *
 * A heading that shows more than one block names them all in ONE call —
 * since('row-link', 'sheet') — and says "since" once, for the oldest of them,
 * with each mark once. Two calls side by side read "since 1.91 since 1.91" to
 * the eye and to a screen reader, and the second wrapped onto its own,
 * indented line on a phone.
 *
 * Unknown keys throw: a mark for a block the index does not know would be a
 * claim with nothing behind it.
 */
function since(string $key, string ...$more): string
{
    $blocks = gkBlocks();
    $keys = array_values(array_unique([$key, ...$more]));
    $recent = gkRecentMinors(gkChangelog());
    $first = null;
    $marks = ['new' => [], 'changed' => []];
    foreach ($keys as $k) {
        if (!isset($blocks[$k])) {
            throw new InvalidArgumentException("css/blocks.json has no block \"$k\"");
        }
        $b = $blocks[$k];
        if ($first === null || version_compare($b['since'], $first, '<')) $first = $b['since'];
        $state = gkBlockState($b, $recent);
        if ($state['new'] !== null) $marks['new'][$state['new']] = true;
        if ($state['changed'] !== null) $marks['changed'][$state['changed']] = true;
    }
    // A minor one block is new in says it all; "changed in" the same minor
    // for its neighbour would only repeat it.
    $marks['changed'] = array_diff_key($marks['changed'], $marks['new']);
    $w = gkMarkWords();

    $html = '<span class="demo-since" data-block="' . htmlspecialchars(implode(' ', $keys), ENT_QUOTES, 'UTF-8') . '">'
        . '<span class="gk-text-muted">' . $w['since'] . ' ' . gkMinor((string) $first) . '</span>';
    foreach (['new', 'changed'] as $kind) {
        $minors = array_map('strval', array_keys($marks[$kind]));
        usort($minors, static fn(string $a, string $b): int => version_compare($b, $a));
        // The space is for whoever reads the text: "since 1.91 Changed in
        // 1.92", not "1.91Changed". Between flex items it takes no room.
        foreach ($minors as $minor) $html .= ' ' . gkMark($kind, $minor);
    }
    return $html . '</span>';
}

/** A changelog heading as HTML: `code` stays code, **bold** loses its stars. */
function gkInlineMd(string $text): string
{
    $html = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $html = preg_replace('/`([^`]+)`/', '<code>$1</code>', $html) ?? $html;
    return preg_replace('/\*\*([^*]+)\*\*/', '$1', $html) ?? $html;
}

/**
 * Where the demo shows a block: the data-section of the first page part whose
 * headings carry its since() mark — read from demo/index.php, never listed twice.
 *
 * @return array<string, string> block key → section id ("tabs")
 */
function gkBlockSections(?string $demoFile = null): array
{
    static $cache = [];
    $file = $demoFile ?? gkRepoRoot() . '/demo/index.php';
    if (isset($cache[$file])) return $cache[$file];

    $src = is_file($file) ? (string) file_get_contents($file) : '';
    preg_match_all('/class="demo-section[^"]*"\s+data-section="([a-z0-9-]+)"|\bsince\(([^)]*)\)/', $src, $hits, PREG_SET_ORDER);
    $map = [];
    $section = null;
    foreach ($hits as $hit) {
        if (($hit[1] ?? '') !== '') { $section = $hit[1]; continue; }
        if ($section === null) continue;
        preg_match_all("/'([a-z0-9-]+)'/", $hit[2] ?? '', $keys);
        foreach ($keys[1] as $key) $map[$key] ??= $section;
    }
    return $cache[$file] = $map;
}

/**
 * What a release did to the blocks (css/blocks.json): the classes it brought and
 * the demo sections that show the blocks it brought or changed.
 *
 * @return array{classes: list<string>, sections: array<string, list<string>>} sections: id → names of its blocks
 */
function gkReleaseBlocks(string $version): array
{
    $classes = [];
    $sections = [];
    $where = gkBlockSections();
    foreach (gkBlocks() as $key => $b) {
        $own = array_keys(array_filter($b['classes'], static fn($v): bool => $v === $version));
        if ($own === [] && $b['changed'] !== $version) continue;
        array_push($classes, ...$own);
        if (isset($where[$key])) $sections[$where[$key]][] = $b['name'];
    }
    sort($classes);
    return ['classes' => $classes, 'sections' => $sections];
}

/** The words of the news box, in the language the page speaks. */
function gkNewsWords(): array
{
    $de = str_starts_with(\GridKit\Lang::locale(), 'de');
    return $de ? [
        'title' => 'Neu in', 'and' => 'und', 'entries' => 'Einträge', 'more' => 'weitere', 'classes' => 'Neue Klassen',
        'example' => 'Beispiele', 'older' => 'Ältere Versionen', 'all' => 'Alle Versionen im Changelog',
        'kind' => ['Added' => 'Neu', 'Changed' => 'Geändert', 'Fixed' => 'Behoben', 'Removed' => 'Entfernt'],
    ] : [
        'title' => 'What is new in', 'and' => 'and', 'entries' => 'entries', 'more' => 'more', 'classes' => 'New classes',
        'example' => 'Examples', 'older' => 'Older versions', 'all' => 'All versions in the changelog',
        'kind' => ['Added' => 'Added', 'Changed' => 'Changed', 'Fixed' => 'Fixed', 'Removed' => 'Removed'],
    ];
}

/** The label of a kind: GridKit's own colours — green added, blue changed, orange fixed, red removed. */
function gkKindLabel(string $kind, array $words): string
{
    $tone = ['Added' => 'green', 'Changed' => 'blue', 'Fixed' => 'orange', 'Removed' => 'red'][$kind];
    return '<span class="gk-label gk-label-' . $tone . '">' . htmlspecialchars($words['kind'][$kind], ENT_QUOTES, 'UTF-8') . '</span>';
}

/** The date of an entry: <time> with the ISO date, in the page's habit (29.10.2026 or 2026-10-29). */
function gkNewsDate(string $date): string
{
    if ($date === '') return '';
    $de = str_starts_with(\GridKit\Lang::locale(), 'de');
    $shown = $de && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m) ? "$m[3].$m[2].$m[1]" : $date;
    return '<time class="demo-news-date" datetime="' . htmlspecialchars($date, ENT_QUOTES, 'UTF-8') . '">'
        . htmlspecialchars($shown, ENT_QUOTES, 'UTF-8') . '</time>';
}

/** One release as a card: groups with their count and leads, the new classes, the jump to the demo. */
function gkNewsCard(array $e, array $w, int $leads = 4, int $chips = 6): string
{
    $id = 'demo-news-v-' . str_replace('.', '-', $e['version']);
    $html = '<article class="gk-card demo-news-card" aria-labelledby="' . $id . '">'
        . '<header class="demo-news-head"><h3 class="demo-news-version" id="' . $id . '">' . $e['version'] . '</h3>'
        . gkNewsDate($e['date']) . '</header>';
    if ($e['intro'] !== '') $html .= '<p class="demo-news-intro">' . gkInlineMd($e['intro']) . '</p>';

    foreach ($e['groups'] as $kind => $g) {
        $html .= '<div class="demo-news-group"><h4 class="demo-news-group-title">' . gkKindLabel($kind, $w)
            . ' <span class="demo-news-count">' . $g['count'] . '<span class="gk-sr-only"> ' . $w['entries'] . '</span></span></h4>'
            . '<ul class="demo-news-list">';
        foreach (array_slice($g['items'], 0, $leads) as $item) $html .= '<li>' . gkInlineMd($item) . '</li>';
        if ($g['count'] > $leads) $html .= '<li class="demo-news-more">+' . ($g['count'] - $leads) . ' ' . $w['more'] . '</li>';
        $html .= '</ul></div>';
    }

    $rb = gkReleaseBlocks($e['version']);
    if ($rb['classes']) {
        $html .= '<div class="demo-news-classes"><span class="demo-news-classes-title">' . $w['classes'] . '</span>';
        foreach (array_slice($rb['classes'], 0, $chips) as $class) {
            $html .= ' <code class="gk-label gk-label-gray demo-news-class">' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '</code>';
        }
        if (count($rb['classes']) > $chips) $html .= ' <span class="demo-news-more">+' . (count($rb['classes']) - $chips) . '</span>';
        $html .= '</div>';
    }
    $jumps = [];
    foreach ($rb['sections'] as $section => $names) {
        $jumps[] = '<a href="#' . htmlspecialchars($section, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars(implode(', ', $names), ENT_QUOTES, 'UTF-8') . '</a>';
    }
    if ($jumps) $html .= '<p class="demo-news-jump">' . $w['example'] . ': ' . implode(' · ', $jumps) . '</p>';
    return $html . '</article>';
}

/** An older release as one row: version, date, the sentence, the kinds counted. */
function gkNewsRow(array $e, array $w): string
{
    $html = '<li class="demo-news-row"><span class="demo-news-row-head"><strong class="demo-news-version">' . $e['version'] . '</strong> '
        . gkNewsDate($e['date']) . '</span><span class="demo-news-row-text">';
    if ($e['intro'] !== '') $html .= '<span class="demo-news-intro">' . gkInlineMd($e['intro']) . '</span>';
    $html .= '<span class="demo-news-counts">';
    foreach ($e['groups'] as $kind => $g) $html .= gkKindLabel($kind, $w) . ' <span class="demo-news-count">' . $g['count'] . '</span> ';
    return $html . '</span></span></li>';
}

/**
 * The box at the top of the demo: one card per release for the newest three,
 * the next ones folded into a native <details>, and the way to the whole changelog.
 * The phone folds the whole box (demo/index.php) — its summary line says what is new.
 */
function whatsNew(int $open = 3, int $older = 12): string
{
    $entries = gkChangelog();
    $w = gkNewsWords();
    $shown = array_slice($entries, 0, $open);
    $rest = array_slice($entries, $open, $older);

    $versions = array_column($shown, 'version');
    $last = array_pop($versions);
    $title = $w['title'] . ' ' . ($versions ? implode(', ', $versions) . ' ' . $w['and'] . ' ' : '') . $last;

    $html = '<details class="demo-news" open><summary class="demo-news-summary">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</summary>'
        . '<div class="demo-news-grid">';
    foreach ($shown as $e) $html .= gkNewsCard($e, $w);
    $html .= '</div>';

    if ($rest) {
        $html .= '<details class="gk-disclosure demo-news-older"><summary>' . $w['older'] . ' (' . count($rest) . ')</summary><ul class="demo-news-rows">';
        foreach ($rest as $e) $html .= gkNewsRow($e, $w);
        $html .= '</ul></details>';
    }
    return $html . '<p class="demo-news-all"><a href="#changelog">' . $w['all'] . '</a></p></details>';
}
