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

];
