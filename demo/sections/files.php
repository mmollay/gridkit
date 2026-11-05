<?php
use GridKit\FileManager;

/**
 * The demo's sample files: made up here, the pictures drawn by api/files.php?picture=n, their thumbnails
 * data: URLs from ph(). Nothing of it exists on a disk.
 *
 * @return list<array<string,mixed>>
 */
function demoFiles(): array
{
    $day = 86400;
    $t = strtotime('2026-10-30 09:15');
    $pic = static fn (string $id, string $name, int $n, int $kb, int $age, ?string $folder, int $w = 1200, int $h = 800): array => [
        'id' => $id, 'filename' => $name, 'bytes' => $kb * 1024, 'type' => 'image/' . (str_ends_with($name, '.png') ? 'png' : (str_ends_with($name, '.webp') ? 'webp' : 'jpeg')),
        'changed' => $t - $age * $day, 'url' => 'api/files.php?picture=' . $n, 'thumb' => ph(320, 240, $n), 'folder' => $folder,
        'width' => $w, 'height' => $h,
    ];
    $doc = static fn (string $id, string $name, string $mime, int $kb, int $age, ?string $folder): array => [
        'id' => $id, 'filename' => $name, 'bytes' => $kb * 1024, 'type' => $mime, 'changed' => $t - $age * $day, 'folder' => $folder,
    ];
    return [
        $pic('f-team', 'Team photo 2026.jpg', 3, 842, 2, null, 2400, 1600),
        $pic('f-hero', 'Homepage hero.webp', 11, 311, 9, null, 1920, 1080),
        $pic('f-logo', 'Logo on dark.png', 21, 46, 40, null, 800, 800),
        $doc('f-price', 'Price list 2027.pdf', 'application/pdf', 188, 1, null),
        $doc('f-press', 'Press kit.zip', 'application/zip', 18432, 30, null),
        $doc('f-contacts', 'Contacts export.csv', 'text/csv', 12, 4, null),
        $doc('f-contract', 'Framework contract (signed).pdf', 'application/pdf', 1260, 120, null),
        $doc('f-video', 'Product teaser.mp4', 'video/mp4', 48000, 15, null),
        $doc('f-r1', 'Receipt 2026-10-02 Office supplies.pdf', 'application/pdf', 96, 28, 'receipts-2026'),
        $doc('f-r2', 'Receipt 2026-10-14 Train ticket Vienna–Graz.pdf', 'application/pdf', 64, 16, 'receipts-2026'),
        $pic('f-r3', 'Receipt 2026-10-21 Lunch (photo).jpg', 33, 1420, 9, 'receipts-2026', 3024, 4032),
        $doc('f-r4', 'Mileage log October.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 31, 3, 'receipts-2026'),
        $doc('f-r5', 'Receipt 2025-12-18 Hosting.pdf', 'application/pdf', 88, 316, 'receipts-2025'),
        $pic('f-m1', 'Autumn campaign banner.png', 41, 512, 12, 'marketing', 1500, 500),
        $doc('f-m2', 'Campaign brief.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 74, 20, 'marketing'),
        $pic('f-p1', 'Office kitchen.jpg', 52, 905, 60, 'photos'),
        $pic('f-p2', 'Summer party.jpg', 63, 1104, 95, 'photos'),
        $pic('f-p3', 'Workshop whiteboard.jpg', 74, 760, 7, 'photos', 1600, 1200),
    ];
}
?>
<!-- ===== FILE MANAGER (1.125.0) ===== -->
<div class="demo-section" data-section="files">
    <h2>File manager <?= since('files') ?></h2>
    <p class="demo-intro">A media library and a drawer for receipts. <code>GridKit\FileManager</code> draws folders, a breadcrumb, thumbnails or a list, a search, a filter by type, the order, the selection with its actions and a details panel — from rows the page hands it; it never reads a directory and never deletes or moves a file itself: it posts to the page's URLs. <code>js/file-manager.js</code> uploads many files at once with a progress bar each, takes files dropped on the manager and pictures pasted with <kbd>Ctrl</kbd>+<kbd>V</kbd>, and puts what the server took into the listing without a reload. On the server, <code>FileManager::receive()</code> checks every upload by what it holds, not by what the browser says.</p>

    <h3 class="demo-h3" id="files-library">A media library: folders, upload, actions</h3>
    <p class="demo-intro">Choose, drop or paste files: each one is checked here first (type, size, at most ten at once), then sent — three at the same time — to <code>api/files.php</code>, which checks it again with <code>receive()</code> and <strong>stores nothing</strong>: a picture comes back as a data URL so it can be shown. Try a file larger than 5 MB, a <code>.docx</code>, or a text file renamed to <code>.png</code>. Click a name for the details (on a phone they cover the screen); tick files for <em>Move</em> and <em>Delete</em> — <em>Framework contract</em> cannot be deleted, so a refusal can be seen. Keys in the listing: <kbd>←</kbd> <kbd>→</kbd> <kbd>↑</kbd> <kbd>↓</kbd>, <kbd>Home</kbd>, <kbd>End</kbd>, <kbd>Space</kbd> ticks, <kbd>Shift</kbd>+arrow ticks a range, <kbd>Ctrl</kbd>+<kbd>A</kbd> all, <kbd>Enter</kbd> opens the details, <kbd>Delete</kbd> deletes, <kbd>Esc</kbd> lets go.</p>
        <?php
        showcase(function () {
            $folders = [
                ['id' => 'receipts', 'name' => 'Receipts', 'parent' => null, 'count' => 2],
                ['id' => 'receipts-2026', 'name' => '2026', 'parent' => 'receipts', 'count' => 4],
                ['id' => 'receipts-2025', 'name' => '2025', 'parent' => 'receipts', 'count' => 1],
                ['id' => 'marketing', 'name' => 'Marketing', 'parent' => null, 'count' => 2],
                ['id' => 'photos', 'name' => 'Photos', 'parent' => null, 'count' => 3],
            ];
            (new FileManager('demo-media'))
                ->label('Media')
                ->folders($folders)
                ->files(demoFiles(), ['name' => 'filename', 'size' => 'bytes', 'mime' => 'type', 'modified' => 'changed', 'thumbUrl' => 'thumb'])
                ->param('media')->anchor('files')            // ?media[folder]=photos#files — links that work without script
                ->fromQuery()                                // folder, view, sort, type, search, details from the request
                ->uploadUrl('api/files.php')
                ->accept(['image/*', 'application/pdf', 'text/csv', '.csv', '.txt'])
                ->maxSize('5M')->maxFiles(10)
                ->actions(['delete' => 'api/files.php', 'move' => 'api/files.php', 'rename' => 'api/files.php',
                           'folder' => 'api/files.php', 'download' => true])
                ->csrf('demo-token')
                ->render();
        });
        ?>
    <pre class="demo-log" id="demo-files-log" aria-label="Event log">Events appear here.</pre>

    <h3 class="demo-h3" id="files-pick">Pick one receipt: a list, single selection</h3>
    <p class="demo-intro"><code>select('single')</code> and <code>view('list')</code>, newest first, no upload and no actions: a drawer to choose a receipt from for a booking. The choice posts <code>ids[]</code> with a form, and the page hears <code>gk:filesselect</code>.</p>
        <?php
        showcase(function () {
            $receipts = array_values(array_filter(demoFiles(), fn (array $f): bool => str_starts_with((string) $f['folder'], 'receipts')));
            (new FileManager('demo-receipts'))
                ->label('Receipts')
                ->files($receipts, ['name' => 'filename', 'size' => 'bytes', 'mime' => 'type', 'modified' => 'changed', 'thumbUrl' => 'thumb'])
                ->param('receipts')->anchor('files')
                ->select('single')->selected(['f-r2'])
                ->view('list')->sort('date', 'desc')
                ->render();
        });
        ?>

    <h3 class="demo-h3" id="files-server">The server side: receive()</h3>
    <p class="demo-intro">What arrives in <code>$_FILES</code> is the visitor's: the name, the type and the size are claims. <code>FileManager::receive()</code>, given the manager's <code>rules()</code> and a directory to store in, takes only a file PHP received in this request, reads its real size and its type from its content, wants the name's extension to fit that type, refuses names with <code>.php</code> anywhere in them, PHP code in any file, page markup in a picture, an SVG with script, and a picture so large in pixels that opening it would eat the server's memory — and stores what passes under a random name of its own, outside the web root unless told otherwise. This demo's endpoint is below; it moves nothing.</p>
    <details class="demo-showcase-code">
        <summary><span class="material-icons" aria-hidden="true">code</span><span>The endpoint: api/files.php</span></summary>
        <div class="demo-showcase-src">
            <pre><code><?= htmlspecialchars((string) file_get_contents(__DIR__ . '/../api/files.php'), ENT_QUOTES, 'UTF-8') ?></code></pre>
        </div>
    </details>
</div>
<script>
/* The file manager demos: what the page hears. */
(function () {
    var log = document.getElementById('demo-files-log');
    var section = document.querySelector('[data-section="files"]');
    if (!log || !section) return;
    var lines = [];
    function say(text) {
        lines.unshift(text);
        log.textContent = lines.slice(0, 8).join('\n');
    }
    ['gk:filesselect', 'gk:filesopen', 'gk:filesuploaded', 'gk:fileserror', 'gk:filesaction', 'gk:filesdone'].forEach(function (name) {
        section.addEventListener(name, function (e) {
            var d = {};
            Object.keys(e.detail).forEach(function (k) {
                var v = e.detail[k];
                if (k === 'file' && v) v = { id: v.id, name: v.name };
                if (k === 'answer') return;
                d[k] = v;
            });
            say(name + ' ' + JSON.stringify(d));
        });
    });
    // "Download" is an event on this page: a real one posts the ticked ids to a URL that answers with a ZIP.
    section.addEventListener('gk:filesaction', function (e) {
        if (e.detail.action === 'download' && window.GK && GK.toast) GK.toast.info(e.detail.ids.length + ' files would be downloaded as a ZIP archive.');
    });
})();
</script>
