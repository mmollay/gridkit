<?php
/**
 * A portrait video frame: `.gk-video-frame` (1.96.0) — the box for a 9:16 video or picture.
 *
 * Why it exists: the SSI Panel's "Shorts" module shows the person who approves a short the
 * finished video before anything is published. A vertical video in a wide admin page needs a
 * frame that keeps its 9:16 shape, never grows taller than the window or wider than its
 * column, can show where a platform covers the picture with its own buttons (about the top
 * tenth and the bottom fifth), and says plainly when there is nothing to show yet or why
 * there is nothing. A portrait video in a bare <video> is as wide as its column and three
 * screens tall.
 *
 * Held here: the shape and the limits, the box the media lies in (absolute, so a portrait
 * file cannot inflate it), the overlay of the safe zones (a modifier class, nothing in the
 * way of the controls, two numbers a page can change), the two states that reuse
 * `.gk-empty`, roles only for colour, no autoplay in anything documented. The geometry in a
 * browser, a phone, a click that must reach the controls, the animation: ci/browser-timeline-video.js.
 */

declare(strict_types=1);

require_once __DIR__ . '/css-helpers.php';

/** @return array<string,callable> */
return (static function (): array {

$root = __DIR__ . '/..';

return [

// ── The frame: 9:16, limited in height, never wider than its column ───────

'the media box is 9:16 and may not be taller than the height limit — which is in dvh, with vh behind it' => function (): void {
    $m = gkCssDecl(gkCssRule('.gk-video-frame-media'));
    T::ok($m !== [], '.gk-video-frame-media has no rule');
    T::eq($m['aspect-ratio'] ?? '', '9 / 16', 'the box is not 9:16');
    T::eq($m['max-height'] ?? '', 'var(--gk-video-frame-max-h)', 'the box has no height limit');
    T::eq($m['position'] ?? '', 'relative', 'the picture and the overlay have nothing to be placed against');
    T::eq($m['overflow'] ?? '', 'hidden', 'the picture is not cut at the rounded corners');
    T::eq($m['box-sizing'] ?? '', 'border-box', 'the border is added to the 9:16 box');
    T::ok(in_array($m['border-radius'] ?? '', ['var(--gk-radius-lg)', 'var(--gk-radius)'], true), 'the box is not rounded with a radius token');

    $f = gkCssDecl(gkCssRule('.gk-video-frame'));
    T::ok(preg_match('/^(\d+)vh$/', $f['--gk-video-frame-max-h'] ?? '', $vh) === 1, 'the height limit has no value in vh to start from (a browser without dvh)');
    $dvh = gkCssBare();
    T::ok((bool) preg_match('/@supports \(height: 1dvh\) \{\s*\.gk-video-frame \{\s*--gk-video-frame-max-h: (\d+)dvh;/', $dvh, $d), 'the limit is not restated in dvh where the browser knows it');
    T::eq($d[1] ?? '', $vh[1] ?? 'x', 'the limit is another number in dvh than in vh');
    T::ok((int) ($vh[1] ?? 0) >= 50 && (int) ($vh[1] ?? 0) <= 90, 'the height limit is ' . ($vh[1] ?? '?') . '% of the window — outside 50 to 90');
},

'the frame is never wider than its column, and its width keeps the 9:16 when the height limit bites' => function (): void {
    $f = gkCssDecl(gkCssRule('.gk-video-frame'));
    // width: min(100%, limit * 9 / 16). With width: 100% and a max-height alone, a limit that bites leaves a wide, flat box.
    T::eq($f['width'] ?? '', 'min(100%, calc(var(--gk-video-frame-max-h) * 9 / 16))', 'the width is not the smaller of the column and 9/16 of the height limit');
    T::eq($f['margin'] ?? '', '0', 'the frame keeps the margin of a <figure>');
},

'the picture or the video lies in the box, not in the flow, and shows whole' => function (): void {
    $c = gkCssDeclFor('.gk-video-frame-media > video');
    T::ok($c !== [], 'no rule for the <video> and the <img> in the box');
    T::eq($c['position'] ?? '', 'absolute', 'a portrait file in the flow gives the box a height of its own and the 9:16 is gone');
    T::eq($c['inset'] ?? '', '0', 'the picture does not fill the box');
    T::eq($c['object-fit'] ?? '', 'contain', 'the picture is cropped — a person who approves it has to see all of it');
    T::eq($c['display'] ?? '', 'block', 'the video is inline and leaves a gap under it');
    T::ok(!isset($c['autoplay']) && !str_contains(gkCssBare(), 'autoplay'), 'something in the stylesheet speaks of autoplay');
},

// ── The safe zones ──────────────────────────────────────────────────────────

'the safe zones are guide lines on request: a modifier class, two numbers a page can change, nothing in the way of the controls' => function (): void {
    $f = gkCssDecl(gkCssRule('.gk-video-frame'));
    T::eq($f['--gk-video-safe-top'] ?? '', '10%', 'the top zone is not a tenth');
    T::eq($f['--gk-video-safe-bottom'] ?? '', '20%', 'the bottom zone is not a fifth');
    $top = gkCssDeclFor('.gk-video-frame-safe .gk-video-frame-media::before');
    $bottom = gkCssDeclFor('.gk-video-frame-safe .gk-video-frame-media::after');
    T::ok($top !== [] && $bottom !== [], 'the overlay has no rule for its two zones');
    T::eq($top['height'] ?? '', 'var(--gk-video-safe-top)', 'the top zone does not read its number');
    T::eq($bottom['height'] ?? '', 'var(--gk-video-safe-bottom)', 'the bottom zone does not read its number');
    T::eq($top['top'] ?? '', '0', 'the top zone does not start at the top edge');
    T::eq($bottom['bottom'] ?? '', '0', 'the bottom zone does not end at the bottom edge');
    foreach (['top' => $top, 'bottom' => $bottom] as $which => $z) {
        T::eq($z['pointer-events'] ?? '', 'none', "the $which zone catches the click meant for the video controls");
        T::eq($z['content'] ?? '', '""', "the $which zone is not drawn");
        T::eq($z['position'] ?? '', 'absolute', "the $which zone takes room in the box");
        T::ok(str_contains(($z['border-top'] ?? '') . ($z['border-bottom'] ?? ''), 'dashed'), "the edge of the $which zone is not a dashed line");
    }
    // Not on unless asked: no rule that draws the zones without the modifier.
    foreach (gkCssRulesNaming('.gk-video-frame-media::') as [$selector]) {
        T::contains($selector, '.gk-video-frame-safe', "$selector draws a zone without the modifier class");
    }
},

// ── Caption ─────────────────────────────────────────────────────────────────

'the caption sits under the box in the quiet colour and breaks a long word' => function (): void {
    $c = gkCssDecl(gkCssRule('.gk-video-frame-caption'));
    T::ok($c !== [], '.gk-video-frame-caption has no rule');
    T::eq($c['color'] ?? '', 'var(--gk-on-surface-variant)', 'the caption is not in the quiet colour');
    T::eq($c['font-size'] ?? '', 'var(--gk-text-caption)', 'the caption is not set in the caption size');
    T::eq($c['overflow-wrap'] ?? '', 'anywhere', 'a long name in the caption cannot break');
    T::eq($c['margin'] ?? '', '8px 0 0', 'the caption has no gap above it and one below it');
},

// ── The two states: .gk-empty, reused ───────────────────────────────────────

'busy and failed reuse .gk-empty: it fills the box, centred, with room for a small frame' => function (): void {
    $e = gkCssDecl(gkCssRule('.gk-video-frame-media > .gk-empty'));
    T::ok($e !== [], 'no rule that lets .gk-empty fill the box');
    T::eq($e['position'] ?? '', 'absolute', '.gk-empty is in the flow and gives the box a height of its own');
    T::eq($e['inset'] ?? '', '0', '.gk-empty does not fill the box');
    T::eq($e['display'] ?? '', 'flex', '.gk-empty is not centred in the box');
    T::eq($e['align-items'] ?? '', 'center', '.gk-empty is not centred vertically');
    T::eq($e['justify-content'] ?? '', 'center', '.gk-empty is not centred horizontally');
    T::eq($e['padding'] ?? '', '16px 12px', '.gk-empty keeps its 56px 24px — a frame 150px wide has no room for it');
    T::eq($e['overflow'] ?? '', 'auto', 'a message longer than a small frame is cut instead of scrolling');
    // What it reuses has to be there.
    T::contains(gkCssText(), "\n.gk-empty,\n.gk-table td.gk-empty {", '.gk-empty is gone — the frame reuses it');
    foreach (['.gk-empty-inner', '.gk-empty-title', '.gk-empty-hint'] as $s) {
        T::ok(gkCssRule($s) !== '', "$s is gone — the frame reuses it");
    }
    T::eq(gkCssDecl(gkCssRule('.gk-video-frame-media .gk-empty-hint'))['overflow-wrap'] ?? '', 'anywhere', 'a long reason in the hint cannot break');
},

'busy pulses its symbol and stands still under reduced motion; failed paints itself in the error colour' => function (): void {
    $css = gkCssText();
    $busy = gkCssDecl(gkCssRule('.gk-video-frame-busy .gk-empty-icon'));
    T::ok(str_contains($busy['animation'] ?? '', 'gk-dot-pulse'), 'the symbol of the busy state does not pulse (it reuses gk-dot-pulse)');
    T::eq($busy['opacity'] ?? '', '1', 'the busy symbol keeps the 0.5 of .gk-empty — the pulse goes from 0.5 to 0.35');
    T::ok(str_contains($css, '@keyframes gk-dot-pulse'), 'the keyframes gk-dot-pulse are gone');
    T::ok((bool) preg_match('/@media \(prefers-reduced-motion: reduce\) \{\s*\.gk-video-frame-busy \.gk-empty-icon \{\s*animation: none;/', $css), 'the busy symbol pulses under prefers-reduced-motion');
    $err = gkCssDecl(gkCssRule('.gk-video-frame-error .gk-empty-icon'));
    T::eq($err['color'] ?? '', 'var(--gk-danger-text)', 'the symbol of the failed state is not in the error text colour');
    T::eq($err['opacity'] ?? '', '1', 'the symbol of the failed state stays at the 0.5 of .gk-empty');
    T::eq(gkCssDecl(gkCssRule('.gk-video-frame-error .gk-empty-title'))['color'] ?? '', 'var(--gk-danger-text)', 'the title of the failed state is not in the error text colour');
    T::eq(gkCssDeclFor('.gk-video-frame-error .gk-video-frame-media')['border-color'] ?? '', 'var(--gk-error)', 'the box of the failed state has no edge in the error colour');
    foreach (['busy', 'error'] as $state) {
        T::eq(gkCssDeclFor(".gk-video-frame-$state .gk-video-frame-media")['border-style'] ?? '', 'dashed', "the empty box of \"$state\" has a solid edge — on a page of the same grey it is not there");
    }
},

'the empty states read 4.5:1 on the ground of the box, light and dark' => function (): void {
    foreach ([false, true] as $dark) {
        $bg = gkCssRole('surface-container', $dark);
        T::ok($bg !== '', 'the ground of the box was not read');
        foreach (['on-surface' => 'the title', 'on-surface-variant' => 'the hint', 'danger-text' => 'the failed title', 'primary-text' => 'the busy symbol'] as $role => $what) {
            $fg = gkCssRole($role, $dark);
            T::ok($fg !== '', "--gk-$role is not a plain colour");
            if ($bg === '' || $fg === '') continue;
            $r = gkCssRatio($fg, $bg);
            T::ok($r >= 4.5, sprintf('%s %s on %s is %.2f:1 in %s mode, WCAG AA asks 4.5:1', $what, $fg, $bg, $r, $dark ? 'dark' : 'light'));
        }
    }
},

// ── Colour: roles, apart from the black behind a video ──────────────────────

'the frame paints with roles; the only literals are the black behind a video and the guide lines over it' => function (): void {
    $rules = gkCssRulesNaming('.gk-video-frame');
    T::ok(count($rules) >= 15, 'found ' . count($rules) . ' rules of .gk-video-frame — the stylesheet was not read');
    $withLiterals = 0;
    foreach ($rules as [$selector, $body]) {
        T::ok(!preg_match('/data-gk-mode|\.gk-dark/', $selector), "$selector is a dark rule — the tokens already change in dark");
        if (!preg_match('/#[0-9a-fA-F]{3,8}\b|rgba?\(|hsla?\(|oklch\(/', $body)) continue;
        $withLiterals++;
        // The video's black (a letterbox is black in both modes, as the lightbox is) and the guide lines, which lie over any picture.
        T::ok(str_contains($selector, '> video') || str_contains($selector, '.gk-video-frame-safe'),
            "$selector has a literal colour — a role follows the theme and the mode");
    }
    T::ok($withLiterals >= 2, 'no literal found in the video rule or the guide lines — the test looks in the wrong place');
},

// ── Place in the file ──────────────────────────────────────────────────────

'it stands before the phone-last block, which has to stay the last thing in the file' => function (): void {
    $c = gkCssText();
    [$vf, $last] = [strpos($c, '/* === VIDEO FRAME'), strpos($c, '/* ── Phones: the type in a field, last of all')];
    T::ok($vf !== false && $last !== false, 'the markers of the video frame block or of the last block were not found');
    T::ok($vf < $last, 'the video frame block stands after the block that has to be last');
},

// ── The markup that is documented is the markup that works ────────────────

'the documented markup is a figure with a video that has controls and no autoplay, and a name' => function () use ($root): void {
    foreach (['GRIDKIT_SKILL.md', 'demo/index.php'] as $file) {
        $src = sourceOf($file);
        preg_match_all('~<figure class="gk-video-frame[^"]*".*?</figure>~s', $src, $frames);
        T::ok(count($frames[0]) >= 1, "$file shows no <figure class=\"gk-video-frame\">");
        $withVideo = 0;
        foreach ($frames[0] as $i => $frame) {
            T::contains($frame, 'gk-video-frame-media', "$file, frame " . ($i + 1) . ': no media box');
            T::contains($frame, '<figcaption class="gk-video-frame-caption"', "$file, frame " . ($i + 1) . ': no caption');
            T::notContains($frame, 'autoplay', "$file, frame " . ($i + 1) . ': the video plays by itself');
            if (preg_match('~<video\b[^>]*>~', $frame, $v)) {
                $withVideo++;
                T::contains($v[0], ' controls', "$file: a <video> without controls");
                T::contains($v[0], ' playsinline', "$file: a <video> without playsinline — a phone opens it full screen on its own");
                T::contains($v[0], 'aria-label=', "$file: a <video> without a name");
            }
            if (str_contains($frame, 'gk-empty')) {
                T::contains($frame, 'class="gk-empty-title"', "$file, frame " . ($i + 1) . ': a state without a title');
            }
        }
        T::ok($withVideo >= 1, "$file shows no frame with a <video>");
        if ($file === 'demo/index.php') {
            foreach (['gk-video-frame-safe', 'gk-video-frame-busy', 'gk-video-frame-error'] as $mod) {
                T::contains($src, $mod, "the demo shows no frame with $mod");
            }
            T::ok((bool) preg_match('~gk-video-frame-busy.*?role="status"~s', $src), 'the demo\'s busy state is not a status');
        }
    }
},

];

})();
