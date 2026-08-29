<?php
/**
 * The demo — the project's most-visited page.
 *
 * Three things were wrong that only show up by opening it:
 *
 *  - `GK.search` required *German* JSON keys from your endpoint: `gruppen`,
 *    `titel`, `treffer`, `untertitel`, `betrag`. An English-facing library
 *    asking an English-speaking developer for German field names.
 *  - The whole Search section had no navigation entry — eleven sections, ten
 *    links. It was unreachable unless you knew the anchor.
 *  - The demo pulled its images from picsum.photos and i.pravatar.cc: a shop
 *    window for "no dependencies" that breaks when a third party is slow, and
 *    that sends every visitor's IP to two of them.
 */

declare(strict_types=1);

const DEMO = __DIR__ . '/../demo/index.php';

/** @return array<string,callable> */
return [

'the search endpoint answers in the documented shape' => function (): void {
    $_GET['q'] = 'nord';
    ob_start();
    include __DIR__ . '/../demo/api/quicksearch.php';
    $json = json_decode((string) ob_get_clean(), true);
    unset($_GET['q']);

    T::ok(is_array($json), 'valid JSON');
    T::ok(isset($json['groups']), 'the top level is `groups`, not `gruppen`');

    $group = $json['groups'][0] ?? [];
    T::ok(isset($group['title']), 'a group carries `title`');
    T::ok(isset($group['items']), 'and `items`');

    $item = $group['items'][0] ?? [];
    foreach (['title', 'subtitle', 'url'] as $key) {
        T::ok(array_key_exists($key, $item), "a hit carries `$key`");
    }
    foreach (['titel', 'untertitel', 'treffer', 'gruppen'] as $german) {
        T::ok(!array_key_exists($german, $item + $group + $json),
            "no German key `$german` in the response");
    }
},

'the search widget still accepts the old German keys' => function (): void {
    $js = (string) file_get_contents(__DIR__ . '/../js/gridkit.js');

    // Breaking every endpoint written against the old contract would be the
    // wrong way to fix the naming.
    T::contains($js, 'd.groups || d.gruppen', 'the top level accepts both');
    T::contains($js, 'g.items || g.treffer',  'so does the item list');
    T::contains($js, 'g.title || g.titel',    'and the group title');
    T::contains($js, 't.title || t.titel',    'and the hit title');
},

'every demo section sits inside the sidebar wrapper' => function (): void {
    // One extra </div> ended .gk-with-sidebar right after the Form section, so
    // nine of the eleven sections were children of <body> and the left 260px of
    // each sat under the fixed sidebar — clipped AND unclickable
    // (elementFromPoint(150,300) returned a sidebar link). CHANGELOG 1.36.0
    // describes this exact failure as the thing the wrapper prevents.
    //
    // Counting tags cannot catch it: the stray close demotes the intended one
    // at the end of the file to a trailing stray, browsers discard that
    // silently, and the total nets to zero. Only ancestry catches it.
    $html = T::capture(static function (): void {
        $_GET = ['lang' => 'en'];
        include __DIR__ . '/../demo/index.php';
    });

    // Walk the tags and record, for each [data-section], whether any open
    // ancestor is the wrapper.
    preg_match_all('/<(\/?)(div)\b([^>]*)>/i', $html, $m, PREG_SET_ORDER);
    $stack = [];
    $sections = [];
    foreach ($m as [$whole, $slash, $tag, $attrs]) {
        if ($slash === '/') { array_pop($stack); continue; }
        $isWrapper = (bool) preg_match('/class="[^"]*gk-with-sidebar/', $attrs);
        if (preg_match('/data-section="([^"]+)"/', $attrs, $s)) {
            $sections[$s[1]] = in_array(true, $stack, true);
        }
        $stack[] = $isWrapper;
    }

    T::ok(count($sections) >= 8, 'the demo lost its sections: ' . count($sections));
    foreach ($sections as $name => $inside) {
        T::ok($inside, "the demo section '$name' renders outside .gk-with-sidebar — "
            . 'its left 260px will sit under the sidebar');
    }
},

'the search endpoint does not require mbstring' => function (): void {
    $src = (string) file_get_contents(__DIR__ . '/../demo/api/quicksearch.php');

    // A guarded call is the right shape; an unguarded one fatals on a host
    // without the extension, which GridKit says it supports.
    $unguarded = array_filter(
        explode("\n", $src),
        static fn(string $line): bool =>
            preg_match('/\bmb_(strtolower|strpos|substr)\s*\(/', $line) === 1
            && !str_contains($line, 'function_exists')
    );
    T::eq(array_values($unguarded), [],
        'unguarded mbstring call: ' . implode(' | ', array_map('trim', $unguarded)));
    T::contains($src, "function_exists('mb_strtolower')", 'and the guard is there');
},

'every demo section is reachable from the navigation' => function (): void {
    $demo = (string) file_get_contents(DEMO);

    preg_match_all('/data-section="([a-z]+)"/', $demo, $sections);
    preg_match_all('/->item\([^,]+,\s*\'#([a-z]+)\'/', $demo, $links);

    $orphans = array_diff(array_unique($sections[1]), array_unique($links[1]));
    T::eq(array_values($orphans), [],
        'a section with no link is invisible unless you know the anchor: '
        . implode(', ', $orphans));
},

'the demo does not depend on a third-party image service' => function (): void {
    $demo = (string) file_get_contents(DEMO);

    foreach (['picsum.photos', 'pravatar.cc', 'placehold', 'placekitten', 'unsplash.com'] as $host) {
        // The comment explaining why they are gone may name them; the markup may not.
        $inMarkup = preg_match('/(src|href|data-src|data-lightbox)="[^"]*' . preg_quote($host, '/') . '/', $demo);
        T::ok(!$inMarkup, "the demo still loads from $host");
    }

    T::contains($demo, 'data:image/svg+xml', 'placeholders are generated locally');
    T::ok(substr_count($demo, 'ph(') >= 15, 'and used throughout');
},

'the demo carries no German in its own copy' => function (): void {
    $demo = (string) file_get_contents(DEMO);

    // The changelog section quotes German release notes on purpose; everything
    // else is the demo's own writing.
    $pos = strpos($demo, 'data-section="changelog"');
    $body = $pos !== false ? substr($demo, 0, $pos) : $demo;

    foreach (['Titel', 'Mehrzeilig', 'nötig', 'Suche</h2>', 'Inhalt<'] as $word) {
        T::notContains($body, $word, "German left in the demo: $word");
    }
},

/**
 * The demo used to carry hand-written code blocks beside its examples — a
 * second copy of the truth, and the copy is the one that rots. showcase()
 * reads the closure's body back out of the file using the line numbers PHP
 * reports for it, so the listing is the code that just ran, by construction.
 */
'the code shown is the code that ran' => function (): void {
    require_once __DIR__ . '/../demo/_showcase.php';

    $marker = 'a-string-that-appears-nowhere-else-in-this-repo';
    $src = showcaseSource(static function () use ($marker) {
        $x = 'a-string-that-appears-nowhere-else-in-this-repo';
        return $x;
    });

    T::contains($src, $marker, 'the body is read from the file, not guessed');
    T::ok(!str_contains($src, 'use ($marker)'),
        'the use clause is plumbing for the page, not part of the example');
    T::ok(!str_starts_with($src, ' '),
        'and it is dedented to column zero rather than carrying this file\'s indentation');
},

'a showcase renders the example and the listing' => function (): void {
    require_once __DIR__ . '/../demo/_showcase.php';
    \GridKit\Lang::set('en');

    ob_start();
    showcase(static function () {
        echo '<p id="the-example">rendered</p>';
    });
    $html = (string) ob_get_clean();

    T::contains($html, 'id="the-example"', 'the example runs and its output is shown');
    T::contains($html, '<details', 'the listing is a details element — keyboard and screen reader for free');
    T::contains($html, 'the-example', 'and the listing contains the source that produced it');
},

];
