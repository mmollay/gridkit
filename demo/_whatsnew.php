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
 *  - CHANGELOG.md for the words. The box at the top of the demo is its newest
 *    entries' headings; nothing here restates what a release did.
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

/**
 * The release entries of CHANGELOG.md, newest first, each with its "###"
 * headings — the one-line summaries the entry is already divided into.
 *
 * @return list<array{version: string, date: string, headings: list<string>}>
 */
function gkChangelog(?string $file = null): array
{
    $md = (string) file_get_contents($file ?? gkRepoRoot() . '/CHANGELOG.md');
    preg_match_all('/^## \[(\d+\.\d+\.\d+)\](?:\s*-\s*(\S+))?[^\n]*\n(.*?)(?=^## |\z)/ms', $md, $m, PREG_SET_ORDER);
    $out = [];
    foreach ($m as $e) {
        preg_match_all('/^### (.+)$/m', $e[3], $h);
        // "Tests" says how a release was checked, not what it brings.
        $headings = array_values(array_filter(array_map('trim', $h[1]), static fn(string $t): bool => $t !== 'Tests'));
        $out[] = ['version' => $e[1], 'date' => $e[2] ?? '', 'headings' => $headings];
    }
    return $out;
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
 * Unknown keys throw: a mark for a block the index does not know would be a
 * claim with nothing behind it.
 */
function since(string $key): string
{
    $blocks = gkBlocks();
    if (!isset($blocks[$key])) {
        throw new InvalidArgumentException("css/blocks.json has no block \"$key\"");
    }
    $b = $blocks[$key];
    $state = gkBlockState($b, gkRecentMinors(gkChangelog()));
    $w = gkMarkWords();

    $html = '<span class="demo-since" data-block="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '">'
        . '<span class="gk-text-muted">' . $w['since'] . ' ' . gkMinor($b['since']) . '</span>';
    if ($state['new'] !== null) $html .= gkMark('new', $state['new']);
    if ($state['changed'] !== null) $html .= gkMark('changed', $state['changed']);
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
 * The box at the top of the demo: the last two minors, each with the blocks
 * it brought or changed (from the index) and its entries' headings (from the
 * changelog).
 */
function whatsNew(): string
{
    $entries = gkChangelog();
    $recent = gkRecentMinors($entries);
    $blocks = gkBlocks();
    $de = str_starts_with(\GridKit\Lang::locale(), 'de');

    $title = ($de ? 'Neu in ' : 'What is new in ') . implode($de ? ' und ' : ' and ', $recent);
    $html = '<details class="demo-news gk-segment" open>'
        . '<summary class="demo-news-summary">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</summary>'
        . '<div class="demo-news-grid">';

    foreach ($recent as $minor) {
        $html .= '<section class="demo-news-minor"><h3 class="demo-news-version">' . htmlspecialchars($minor, ENT_QUOTES, 'UTF-8') . '</h3>';
        // One line per kind: the label once, then the blocks — a label per block
        // made 1.92 a column of 23 rows.
        $names = ['new' => [], 'changed' => []];
        foreach ($blocks as $b) {
            $s = gkBlockState($b, $recent);
            foreach (['new', 'changed'] as $kind) {
                if ($s[$kind] === $minor) $names[$kind][] = htmlspecialchars($b['name'], ENT_QUOTES, 'UTF-8');
            }
        }
        foreach ($names as $kind => $list) {
            if ($list) $html .= '<p class="demo-news-blocks">' . gkMark($kind, $minor) . ' ' . implode(', ', $list) . '</p>';
        }
        foreach ($entries as $e) {
            if (gkMinor($e['version']) !== $minor) continue;
            $html .= '<h4 class="demo-news-entry">' . $e['version']
                . ($e['date'] !== '' ? ' <span class="gk-text-muted">' . htmlspecialchars($e['date'], ENT_QUOTES, 'UTF-8') . '</span>' : '')
                . '</h4>';
            if ($e['headings']) {
                $html .= '<ul class="demo-news-list">';
                foreach ($e['headings'] as $h) $html .= '<li>' . gkInlineMd($h) . '</li>';
                $html .= '</ul>';
            }
        }
        $html .= '</section>';
    }

    return $html . '</div><p class="gk-mb-0"><a href="#changelog">'
        . ($de ? 'Alle Versionshinweise' : 'All release notes') . '</a></p></details>';
}
