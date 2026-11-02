<?php
use GridKit\Table;
use GridKit\Form;
use GridKit\Modal;
use GridKit\StatCards;
use GridKit\Sidebar;
use GridKit\FilterChips;
use GridKit\YearFilter;
use GridKit\TableHeader;
use GridKit\Header;
use GridKit\Button;
use GridKit\Theme;
use GridKit\Layout;
use GridKit\Lang;
?>
<!-- ===== GALLERY (gallery + lightbox + image picker) ===== -->
<div class="demo-section" data-section="gallery">
    <h2>Gallery <?= since('gallery', 'lightbox', 'picker') ?></h2>
    <p class="demo-intro">Two ways to show pictures. A <strong>gallery</strong> looks: tiles that open a viewer. An <strong>image picker</strong>
        chooses: the same tiles, but each one is a radio button, and the viewer can pick. Both use the same viewer, so it pages with the arrow keys,
        closes on Escape and gives the focus back.</p>

<?php
// Pictures for the picker demos: a small one for the tile (about 400 px in real use) and a large one for the viewer.
$pickerItems = static function (int $n, int $checked = 0, bool $varied = false): array {
    $names = ['Fireplace at dusk', 'Mountain road in the fog', 'Workshop table', 'Group on the summit', 'Compass and map', 'Tent at sunrise',
              'Teaching the knot', 'Lake in the morning', 'Cabin door', 'Trail marker', 'Evening meal', 'Campfire sparks'];
    $out = [];
    for ($i = 1; $i <= $n; $i++) {
        $it = ['value' => 'img-' . $i, 'title' => $names[($i - 1) % count($names)] . ($i > count($names) ? ' (' . (intdiv($i - 1, count($names)) + 1) . ')' : ''),
               'thumb' => ph(320, 180, $i), 'full' => ph(1600, 900, $i), 'meta' => 'Own image', 'checked' => $i === $checked];
        if ($varied && $i === 2) { $it['meta'] = 'AI generated'; $it['badge'] = 'AI'; }
        if ($varied && $i === 3) { $it['actions'] = [['name' => 'remove', 'label' => 'Remove']]; }
        if ($varied && $i === 4) { unset($it['thumb'], $it['full']); $it['title'] = 'From the stock — no preview'; $it['meta'] = 'stock-image.webp'; }
        if ($varied && $i === 5) { $it['disabled'] = true; $it['note'] = 'Not released yet'; }
        $out[] = $it;
    }
    return $out;
};
?>

    <h3 class="demo-h3" id="picker-six">Image picker <?= since('picker') ?> — six images</h3>
    <p class="demo-intro">Tap a tile to pick it, or Tab into the group and use the arrow keys. The magnifier opens the viewer; <strong>Select this image</strong> in it
        picks the picture you are looking at. The status line, the form value and the event log below follow every change.
        One tile has a small origin chip ("AI"), one a remove button, one has no preview, one is disabled and says why.</p>

    <form class="demo-card demo-picker-form" id="demo-picker-form" onsubmit="return false">
        <fieldset class="demo-fieldset">
            <legend class="gk-label-text">Header image</legend>
            <?php showcase(function () use ($pickerItems) {
                \GridKit\ImagePicker::render($pickerItems(6, 1, true), ['name' => 'cover', 'id' => 'demo-pk-six']);
            }); ?>
        </fieldset>
        <p class="demo-picker-value">Form value: <code id="demo-picker-value">img-1</code></p>
        <pre class="demo-log" id="demo-picker-log" aria-live="polite" aria-label="Event log">Events appear here.</pre>
        <div class="demo-btn-row">
            <button type="button" class="gk-btn gk-btn-outlined gk-btn-neutral gk-btn-sm" id="demo-picker-add">Add a new image</button>
        </div>
    </form>

    <h3 class="demo-h3" id="picker-thirty">Thirty images — two rows, then "Show all 30"</h3>
    <p class="demo-intro">The picked tile (21 here) stays visible even though it lies beyond the fold. Resize the window: the number of tiles in the two rows follows the width. On a
        phone two tiles fit in a row, and thirty images are about four tiles and a button instead of a screen-long list.</p>
    <div class="demo-card">
        <fieldset class="demo-fieldset">
            <legend class="gk-label-text">Pick one of thirty</legend>
            <?php \GridKit\ImagePicker::render($pickerItems(30, 21), ['name' => 'thirty', 'id' => 'demo-pk-thirty', 'labels' => ['more' => 'Show all {n} images', 'less' => 'Show fewer images']]); ?>
        </fieldset>
    </div>

    <h3 class="demo-h3" id="picker-one">One image, and none</h3>
    <div class="demo-card demo-pair">
        <fieldset class="demo-fieldset">
            <legend class="gk-label-text">One image</legend>
            <?php \GridKit\ImagePicker::render($pickerItems(1), ['name' => 'single', 'id' => 'demo-pk-one']); ?>
        </fieldset>
        <fieldset class="demo-fieldset">
            <legend class="gk-label-text">Nothing yet (the shell waits for GK.picker.add)</legend>
            <?php \GridKit\ImagePicker::render([], ['name' => 'empty', 'id' => 'demo-pk-empty', 'status' => true]); ?>
        </fieldset>
    </div>

    <h3 class="demo-h3" id="picker-keys">Keyboard</h3>
    <table class="gk-table demo-keys">
        <caption class="gk-sr-only">Keys in the picker and in its viewer</caption>
        <thead><tr><th scope="col">Where</th><th scope="col">Key</th><th scope="col">What happens</th></tr></thead>
        <tbody>
            <tr><td>Picker</td><td><kbd>Tab</kbd></td><td>Enters the group once, on the picked tile; the next Tab reaches that tile's magnifier (and remove button), then the fold button — thirty tiles are not sixty tab stops.</td></tr>
            <tr><td>Picker</td><td><kbd>&larr;</kbd> <kbd>&rarr;</kbd> <kbd>&uarr;</kbd> <kbd>&darr;</kbd></td><td>Moves to the next tile and picks it (native radios); disabled tiles are skipped.</td></tr>
            <tr><td>Picker</td><td><kbd>Enter</kbd> on the magnifier</td><td>Opens the viewer on that picture.</td></tr>
            <tr><td>Viewer</td><td><kbd>&larr;</kbd> <kbd>&rarr;</kbd></td><td>Previous or next picture (all of them, folded ones included).</td></tr>
            <tr><td>Viewer</td><td><kbd>Tab</kbd></td><td>Stays inside: close, previous, next, "Select this image", "Close".</td></tr>
            <tr><td>Viewer</td><td><kbd>Esc</kbd></td><td>Closes it; the focus goes back to the magnifier of the picture last shown — or to the radio, when one was picked from the viewer.</td></tr>
        </tbody>
    </table>

    <h3 class="demo-h3" id="picker-events">Events and script</h3>
    <p class="demo-intro">Picking is the radio's own <code>change</code> event, so a form needs nothing new. A caption button fires <code>gk:pickeraction</code> and does nothing else —
        here the page asks "Really?" first (<code>GK.confirm</code>) and only then calls <code>GK.picker.remove()</code>. The library never deletes anything itself.</p>
    <div class="demo-code"><pre>picker.addEventListener('gk:pickeraction', (e) =&gt; {
    // e.detail = { action: 'remove', value: 'img-3', item: &lt;the tile&gt;, picker: &lt;the container&gt; }
    GK.confirm('Remove this image?', { danger: true }).then((ok) =&gt; {
        if (ok) GK.picker.remove(e.detail.picker, e.detail.value);
    });
});

GK.picker.add(picker, { value: 'img-9', title: 'Fresh upload', thumb: '/t/9.webp', full: '/g/9.webp' },
              { select: true, focus: true });   // picks it as a click would — one change event

// The viewer is open to your own lists too:
GK.lightbox.show([{ src: '/g/1.webp', caption: 'Fireplace', meta: 'Own image' }], 0, openerButton);</pre></div>

    <hr class="demo-rule">

    <h3 class="demo-h3" id="gallery-plain">Gallery + Lightbox <?= since('gallery', 'lightbox') ?></h3>
    <p class="demo-intro">Image grid with lazy loading, hover overlay and lightbox (arrow keys, Escape).</p>

    <div class="gk-gallery">
        <div class="gk-gallery-item" data-lightbox="<?= ph(800, 600, 1) ?>" data-caption="Landscape 1" data-lazy>
            <img data-src="<?= ph(400, 400, 1) ?>" alt="Landscape 1">
            <div class="gk-gallery-overlay"><span class="gk-gallery-caption">Landscape</span></div>
        </div>
        <div class="gk-gallery-item" data-lightbox="<?= ph(800, 600, 2) ?>" data-caption="Architecture" data-lazy>
            <img data-src="<?= ph(400, 400, 2) ?>" alt="Architecture">
            <div class="gk-gallery-overlay"><span class="gk-gallery-caption">Architecture</span></div>
        </div>
        <div class="gk-gallery-item" data-lightbox="<?= ph(800, 600, 3) ?>" data-caption="Nature" data-lazy>
            <img data-src="<?= ph(400, 400, 3) ?>" alt="Nature">
            <div class="gk-gallery-overlay"><span class="gk-gallery-caption">Nature</span></div>
        </div>
        <div class="gk-gallery-item" data-lightbox="<?= ph(800, 600, 4) ?>" data-caption="City" data-lazy>
            <img data-src="<?= ph(400, 400, 4) ?>" alt="City">
            <div class="gk-gallery-overlay"><span class="gk-gallery-caption">City</span></div>
        </div>
        <div class="gk-gallery-item" data-lightbox="<?= ph(800, 600, 5) ?>" data-caption="Abstract" data-lazy>
            <img data-src="<?= ph(400, 400, 5) ?>" alt="Abstract">
            <div class="gk-gallery-overlay"><span class="gk-gallery-caption">Abstract</span></div>
        </div>
        <div class="gk-gallery-item" data-lightbox="<?= ph(800, 600, 6) ?>" data-caption="Panorama" data-lazy>
            <img data-src="<?= ph(400, 400, 6) ?>" alt="Panorama">
            <div class="gk-gallery-overlay"><span class="gk-gallery-caption">Panorama</span></div>
        </div>
    </div>

    <div class="demo-code"><pre>&lt;div class="gk-gallery"&gt;
    &lt;div class="gk-gallery-item" data-lightbox="full.jpg" data-caption="Caption" data-lazy&gt;
        &lt;img data-src="thumb.jpg" alt=""&gt;
        &lt;div class="gk-gallery-overlay"&gt;
            &lt;span class="gk-gallery-caption"&gt;Caption&lt;/span&gt;
        &lt;/div&gt;
    &lt;/div&gt;
&lt;/div&gt;

// Variants:
.gk-gallery-sm              // Smaller thumbnails (100px min)
.gk-gallery-lg              // Larger thumbnails (220px min)
.gk-gallery-masonry         // Pinterest layout (columns)
// Lightbox: arrow keys, Escape, click outside to close
// data-lazy: load images only when visible</pre></div>

    <hr class="demo-rule">

    <h3 class="demo-h3" id="video-frame">Portrait video frame <?= since('video-frame') ?></h3>
    <p class="demo-intro">A frame for a 9:16 video or picture — a short, a story, a phone screenshot. It keeps its shape, is never wider than its column and never taller than 75 % of the window, and shows the whole picture. <code>gk-video-frame-safe</code> draws the zones a platform covers with its own buttons (about the top tenth and the bottom fifth) as guide lines — they never catch a click, so the controls still work. Nothing to show yet, or it broke: <code>gk-video-frame-busy</code> and <code>gk-video-frame-error</code> reuse the empty state. No autoplay.</p>
        <?php
        showcase(function () {
            echo <<<'HTML'
            <div class="demo-frames">
              <figure class="gk-video-frame">
                <div class="gk-video-frame-media">
                  <video controls playsinline preload="metadata" aria-label="Sample video, 3 seconds" src="media/sample-portrait.webm"></video>
                </div>
                <figcaption class="gk-video-frame-caption">Version 2 · 3 s</figcaption>
              </figure>
              <figure class="gk-video-frame gk-video-frame-safe">
                <div class="gk-video-frame-media">
                  <video controls playsinline preload="metadata" aria-label="Sample video with the safe zones shown" src="media/sample-portrait.webm"></video>
                </div>
                <figcaption class="gk-video-frame-caption">Safe zones shown: top 10 %, bottom 20 %</figcaption>
              </figure>
              <figure class="gk-video-frame">
                <div class="gk-video-frame-media">
                  <img src="media/sample-portrait.svg" alt="The first frame of the video: a blue field with a pale disc">
                </div>
                <figcaption class="gk-video-frame-caption">A picture shows in the same frame</figcaption>
              </figure>
              <figure class="gk-video-frame gk-video-frame-busy">
                <div class="gk-video-frame-media">
                  <div class="gk-empty" role="status">
                    <div class="gk-empty-inner">
                      <span class="material-icons gk-empty-icon" aria-hidden="true">movie</span>
                      <div class="gk-empty-title">Video is being made</div>
                      <div class="gk-empty-hint">This takes a minute or two. You can leave the page.</div>
                    </div>
                  </div>
                </div>
                <figcaption class="gk-video-frame-caption">Version 3</figcaption>
              </figure>
              <figure class="gk-video-frame gk-video-frame-error">
                <div class="gk-video-frame-media">
                  <div class="gk-empty">
                    <div class="gk-empty-inner">
                      <span class="material-icons gk-empty-icon" aria-hidden="true">error</span>
                      <div class="gk-empty-title">No video</div>
                      <div class="gk-empty-hint">The image service did not answer. Try again, or change the topic.</div>
                    </div>
                  </div>
                </div>
                <figcaption class="gk-video-frame-caption">Version 3 failed</figcaption>
              </figure>
            </div>
            HTML;
        });
        ?>

    <hr style="border:none;border-top:1px solid var(--gk-outline-variant);margin:40px 0">
</div>

