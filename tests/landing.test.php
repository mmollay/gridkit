<?php
/**
 * The landing page.
 *
 * Its own comment above the product shot reads "a UI library has to show what
 * it looks like" — and that image had been a 404 since 1.30.0, when the
 * screenshots were renamed from German filenames and every reference was
 * updated except this one.
 */

declare(strict_types=1);

const LANDING = __DIR__ . '/../index.php';

/** @return array<string,callable> */
return [

'every image the landing page references exists' => function (): void {
    $src  = (string) file_get_contents(LANDING);
    $root = dirname(__DIR__);

    preg_match_all("/\\\$asset\('([^']+\.(?:png|jpg|svg|webp))'\)/", $src, $m);
    T::ok($m[1] !== [], 'the page shows images at all');

    foreach ($m[1] as $path) {
        T::ok(is_file($root . '/' . $path), "missing: $path");
    }
},

'declared image dimensions match the files' => function (): void {
    $src  = (string) file_get_contents(LANDING);
    $root = dirname(__DIR__);

    // A wrong intrinsic size reserves the wrong box and the page jumps when the
    // image arrives. These said 2800x1760 for a 1400x900 file.
    preg_match_all(
        '/\$asset\(\'([^\']+\.png)\'\)(?:(?!<img).)*?width="(\d+)"\s+height="(\d+)"/s',
        $src, $m, PREG_SET_ORDER
    );
    T::ok($m !== [], 'images declare their size');

    foreach ($m as [$_, $path, $w, $h]) {
        $size = getimagesize($root . '/' . $path);
        T::eq((int) $w, $size[0], "declared width of $path");
        T::eq((int) $h, $size[1], "declared height of $path");
    }
},

'the page agrees with itself about how many components there are' => function (): void {
    $src = (string) file_get_contents(LANDING);

    // src/ holds 21 classes: 16 components plus five infrastructure ones
    // (Theme, Layout, Lang, Auth, Icon). The hero stat said 16; the meta
    // description, the og: tags and two body sentences said 12.
    $classes = count(glob(dirname(__DIR__) . '/src/*.php') ?: []);
    T::eq($classes, 21, 'the class count this claim rests on');

    T::ok(!str_contains($src, '12 components'), 'no stale count left');
    T::ok(substr_count($src, '16 components') >= 3,
        'the count appears where a reader and a search engine both see it');
},

'the name is spelled the same everywhere' => function (): void {
    // GRIDKit appeared in <title>, og:title, og:site_name and twitter:title —
    // exactly the strings a search result and a shared link show.
    $files = ['index.php', 'demo/index.php', 'GRIDKIT_SKILL.md', 'README.md',
              'js/gridkit.js', 'lang/en.php', 'lang/de.php',
              'src/Auth.php', 'src/Lang.php', 'src/Icon.php'];

    foreach ($files as $rel) {
        $path = dirname(__DIR__) . '/' . $rel;
        if (!is_file($path)) continue;
        T::notContains((string) file_get_contents($path), 'GRIDKit',
            "the name is GridKit, not GRIDKit — in $rel");
    }
},

'the landing page keeps its layout on a phone' => function (): void {
    $src = (string) file_get_contents(LANDING);

    // A fixed 140px label column left the value about 190px wide at 390px, and
    // .skill-table clips: the right half of every row was unreadable.
    $start = strpos($src, '@media (max-width: 768px)');
    T::ok($start !== false, 'the page has a phone breakpoint');

    $depth = 0; $end = $start;
    for ($i = $start; $i < strlen($src); $i++) {
        if ($src[$i] === '{') $depth++;
        if ($src[$i] === '}') { $depth--; if ($depth === 0) { $end = $i; break; } }
    }
    $mobile = substr($src, $start, $end - $start);

    T::ok((bool) preg_match('/\.skill-table-row \{[^}]*flex-direction:\s*column/s', $mobile),
        'the skill table stacks on narrow screens');
    T::contains($src, '.code-body', 'code blocks have their own container');
    T::ok((bool) preg_match('/\.code-body \{[^}]*overflow-x:\s*auto/s', $src),
        'and that container scrolls rather than clipping');
},

/**
 * The manual does not ride along on the landing page.
 *
 * GRIDKIT_SKILL.md used to be delivered inline twice on every visit: 111 KB of
 * rendered HTML inside a collapsed toggle nobody had opened, and another 68 KB
 * of the same text in a hidden textarea so that one copy button had something
 * to read. Together, 71 % of a 254 KB page. It also put 44 of its own headings
 * into the outline a screen reader walks and a search engine indexes, so the
 * page read as the manual with a landing page attached — and competed with
 * /skill, which serves exactly that document.
 *
 * Both are fetched on demand now. The page is 74 KB and has 27 headings, all
 * of them its own.
 */
'the landing page does not carry the skill document inline' => function (): void {
    $src = (string) file_get_contents(LANDING);

    T::ok(!preg_match('/<textarea[^>]*id="skill-content"/', $src),
        'the raw document is back in a hidden textarea — 68 KB on every page load '
        . 'so that one button can read it');
    T::ok(!preg_match('/id="skill-preview"[^>]*>\s*<\?=\s*\$skillHtml/', $src),
        'the rendered document is back inside the collapsed preview — 111 KB '
        . 'nobody sees until they ask for it');

    // And the two ways of asking are still wired up.
    T::contains($src, "isset(\$_GET['skill-html'])",
        'the rendered document has no endpoint to be fetched from');
    T::contains($src, "fetch(preview.dataset.src)",
        'the toggle does not fetch the preview');
    T::contains($src, "fetch('/skill/')",
        'the copy button does not fetch the raw document');

    // A disclosure states what it controls and whether it is open — the same
    // rule the library's own components had to learn.
    T::ok((bool) preg_match('/id="skill-toggle"[^>]*aria-expanded="false"/s', $src)
       || (bool) preg_match('/id="skill-toggle"[\s\S]{0,200}aria-expanded="false"/', $src),
        'the toggle does not say whether it is open');
},

/**
 * A dark colour that only the operating system can reach.
 *
 * The landing page kept its dark palette behind
 * `@media (prefers-color-scheme: dark)` — the visitor's SYSTEM setting — and
 * nothing else. But the page also carries a moon button, and that button sets
 * `data-gk-mode="dark"` on the body, which no media query can see. Press it on
 * a machine set to light and the ground went dark while every text colour
 * stayed where it was: the hero subline, the section subtitles and all body
 * text came out at **1.93:1** where 4.5:1 is required. A page nobody can read,
 * reached by pressing the button the page offers.
 *
 * It is worth naming why it happened here and nowhere else: css/gridkit.css
 * and css/themes.css use `data-gk-mode` for all 118 of their dark rules, and
 * so does the demo. The landing page — the one advertising that library — was
 * the only file that chose the other mechanism, and its own toggle spoke the
 * library's language.
 *
 * Three states, not two: system-dark with no choice made, an explicit dark
 * choice, and an explicit light choice that has to win even on a dark machine.
 * The `:not([data-gk-mode="light"])` is what makes the third one work.
 */
'the landing page dark palette reaches its own toggle, not only the system' => function (): void {
    $src = (string) file_get_contents(LANDING);

    // The shape of the original fault: variables redefined for the system
    // setting alone.
    T::ok(!preg_match('/@media \(prefers-color-scheme: dark\)\s*\{\s*:root\s*\{/', $src),
        'the dark palette is defined on :root behind the media query alone, so the '
        . "page's own toggle changes the ground and leaves the text where it was");

    $system = substr_count($src, 'body:not([data-gk-mode="light"])');
    $gewaehlt = substr_count($src, 'body[data-gk-mode="dark"]');
    T::ok($system >= 2,
        "found $system system-dark selectors; a much smaller number means the pattern "
        . 'broke rather than the page losing its dark mode');
    T::ok($gewaehlt >= 2,
        "the dark palette is applied for the system setting ($system places) but not "
        . "for an explicit dark choice ($gewaehlt) — which is what the moon button sets");

    // Every selector after a comma needs the prefix too. The first version of
    // the helper prefixed the line, so `.a code, .b code` left the second half
    // loose inside the media query.
    T::contains($src, "explode(',', trim(\$sel))",
        'the prefixing helper does not split comma-separated selectors, so half of '
        . 'each pair escapes the scope it was meant to get');
},

/**
 * One hostname, not two.
 *
 * www.gridkit.at and gridkit.at both answered 200 with byte-identical pages.
 * To a search engine that is two sites carrying the same content, splitting
 * whatever standing either would have had — on a domain Googlebot had visited
 * nine times in total. The canonical tag named the right one, but a canonical
 * is a hint a crawler weighs against other signals; a 301 is not.
 *
 * The rule names one host on purpose: gridkit.ssi.at serves the same directory
 * and already redirects from its own vhost, and a broader rule would have
 * caught hosts nobody checked.
 */
'the site answers under one hostname' => function (): void {
    $ht = (string) file_get_contents(__DIR__ . '/../.htaccess');

    T::contains($ht, 'RewriteCond %{HTTP_HOST} ^www\\.gridkit\\.at$ [NC]',
        'www.gridkit.at is served as a second copy of the site rather than redirected');
    T::contains($ht, 'RewriteRule ^(.*)$ https://gridkit.at/$1 [R=301,L]',
        'the redirect is missing or is not a permanent one');

    // The homepage names itself exactly. Without the slash the tag pointed at
    // https://gridkit.at while the page it sits on is https://gridkit.at/.
    $src = (string) file_get_contents(LANDING);
    T::contains($src, '$canonicalSelf = $canonicalUrl . \'/\';',
        'the homepage canonical drops the trailing slash the page actually has');
    T::contains($src, '<link rel="canonical" href="<?= $canonicalSelf ?>">',
        'the canonical tag does not use it');
},

];
