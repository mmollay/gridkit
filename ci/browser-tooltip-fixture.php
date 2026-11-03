<?php
/**
 * The page ci/browser-tooltip.js drives (the tooltip engine, 1.112.0): triggers at the four
 * window edges and corners, in a scrolling box, in a modal; every form (title, CSS-only plain,
 * rich, toggletip, the help icon); every option (pos incl. auto, tone, max, kbd, delay, wrap);
 * the component options on Button, a setData() Table and Form; content that arrives later.
 * One self-contained HTML document, stylesheets and script inlined, so it loads from file://
 * with no server.
 *
 *     php ci/browser-tooltip-fixture.php [--mode=light|dark|dark-class] [--theme=ocean]
 *                                         [--no-js] [--old-js] [--late] > f.html
 *
 *   --mode       light (default); dark as data-gk-mode="dark"; dark-class as .gk-dark
 *   --theme      one of the six themes of css/themes.css (none: the bare stylesheet)
 *   --no-js      no script at all: the CSS-only tooltip as it always was
 *   --old-js     js/gridkit.js without its 1.112.0 block, as the PawBot dashboard keeps it
 *   --late       the script is added after the document is complete (the old GK.tip.init and
 *                GK.tooltip.init then run at once, before the engine replaces them)
 *
 * GK_CSS and GK_JS name other files to inline instead of css/gridkit.css and js/gridkit.js — the
 * counter-checks run the page on copies with one rule taken out.
 */

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use GridKit\{Button, Form, Lang, Table, Tooltip};

Lang::set('en');

$root = dirname(__DIR__);
$opt = static fn (string $name): bool => in_array("--$name", $argv, true);
$val = static function (string $name, string $default) use ($argv): string {
    foreach ($argv as $a) {
        if (str_starts_with($a, "--$name=")) return substr($a, strlen($name) + 3);
    }
    return $default;
};
$mode = $val('mode', 'light');
$theme = $val('theme', '');
$capture = static function (callable $fn): string {
    ob_start();
    $fn();
    return (string) ob_get_clean();
};

$rows = [
    ['id' => 1, 'name' => 'Carla', 'amount' => 1200.5],
    ['id' => 2, 'name' => 'Anton', 'amount' => 9.9],
    ['id' => 3, 'name' => 'Berta', 'amount' => 0],
];

$table = $capture(fn () => (new Table('tt'))->setData($rows)
    ->column('name', 'Name', ['sortable' => true, 'tooltip' => 'The full name, as signed'])
    ->column('amount', 'Amount', ['format' => 'currency', 'align' => 'right', 'sortable' => true,
                                  'tooltip' => ['text' => 'Net, before VAT', 'tone' => 'info']])
    ->button('edit', ['icon' => 'edit', 'tooltip' => 'Edit this person'])
    ->button('del', ['icon' => 'delete', 'title' => 'Remove', 'tooltip' => ['text' => 'Removes the person for good', 'tone' => 'danger']])
    ->render());

$form = $capture(fn () => (new Form('f'))
    ->field('email', 'Email', 'email', ['help' => 'We never share it.'])
    ->field('agree', 'I agree', 'checkbox', ['help' => 'Read the terms first.'])
    ->field('plain', 'Plain', 'text')
    ->render());

$modal = '<div class="gk-modal-overlay" id="m" hidden><div class="gk-modal gk-modal-small" role="dialog" aria-modal="true" aria-labelledby="m-title">'
    . '<div class="gk-modal-header"><h3 class="gk-modal-title" id="m-title">Modal</h3></div>'
    . '<div class="gk-modal-body"><p><button type="button" id="m-tip"' . Tooltip::attrs('Inside the modal') . '>In modal</button> '
    . '<span id="m-help">' . Tooltip::help('Help inside the modal', ['label' => 'Modal']) . '</span></p></div></div></div>';

// Arrives later: GK.initContent() is handed it.
$later = '<p><span id="later-plain"' . Tooltip::attrs('Arrived later') . '>Later</span> '
    . '<button type="button" id="later-tt"' . Tooltip::toggletip('Later toggletip') . '>Later toggle</button></p>';

$edge = static fn (string $id, string $style, string $text, array $o = []): string =>
    '<span id="' . $id . '" class="edge" style="' . $style . '"' . Tooltip::attrs($text, $o) . '>' . $id . '</span>';
$long = 'A rather long tooltip text that needs room to be read';

$body = ''
    // At the edges of the window: fixed, so a scroll of the page does not move them.
    . $edge('edge-top', 'top:2px;left:45%', 'Top edge', ['pos' => 'top'])
    . $edge('edge-bottom', 'bottom:2px;left:45%', 'Bottom edge', ['pos' => 'bottom'])
    . $edge('edge-left', 'left:2px;top:45%', 'Left edge', ['pos' => 'left'])
    . $edge('edge-right', 'right:2px;top:45%', 'Right edge', ['pos' => 'right'])
    . $edge('corner-tl', 'top:2px;left:2px', $long, ['pos' => 'top'])
    . $edge('corner-br', 'bottom:2px;right:2px', $long, ['pos' => 'bottom'])
    . $edge('auto-top', 'top:2px;left:25%', 'Auto at the top', ['pos' => 'auto'])
    . $edge('auto-bottom', 'bottom:2px;left:25%', 'Auto at the bottom', ['pos' => 'auto'])
    . $edge('auto-right', 'right:2px;top:30%', $long, ['pos' => 'auto'])
    . '<main>'
    . '<h1>Tooltips</h1>'
    . '<p class="row">'
    . '<span id="plain"' . Tooltip::attrs('Plain text') . '>plain</span>'
    . '<span id="delay0"' . Tooltip::attrs('No delay', ['delay' => 0]) . '>delay 0</span>'
    . '<span id="delay800"' . Tooltip::attrs('Long delay', ['delay' => 800]) . '>delay 800</span>'
    . '<span id="kbd"' . Tooltip::attrs('Save', ['kbd' => 'Ctrl+S']) . '>kbd</span>'
    . '<span id="max"' . Tooltip::attrs($long . ' and a little more besides', ['max' => 120]) . '>max</span>'
    . '<span id="wrap"' . Tooltip::attrs($long, ['wrap' => true]) . '>wrap</span>'
    . '<span id="other"' . Tooltip::attrs('The other one', ['pos' => 'bottom']) . '>other</span>'
    . '</p><p class="row">'
    . implode('', array_map(static fn (string $t): string =>
        '<span id="tone-' . $t . '"' . Tooltip::attrs("Tone $t", ['tone' => $t, 'kbd' => 'K']) . '>' . $t . '</span>', Tooltip::TONES))
    . '</p><p class="row">'
    . Button::render('Save', ['id' => 'btn-tip', 'title' => 'Save draft', 'tooltip' => ['text' => 'Saves the draft', 'kbd' => 'Ctrl+S']])
    . Button::icon('delete', ['id' => 'btn-icon', 'tooltip' => 'Deletes the record for good'])
    . '<button type="button" id="btn-click" onclick="window.clicks=(window.clicks||0)+1"' . Tooltip::attrs('Counts its clicks') . '>Click</button>'
    . '<a id="link-tip" href="#went"' . Tooltip::attrs('Goes somewhere') . '>Link</a>'
    . '</p><p class="row">'
    . '<span id="title-span" title="Title text&#10;line two">title span</span>'
    . '<button type="button" id="title-btn" title="Save the current draft">Save</button>'
    . '<button type="button" id="title-icon" title="Delete row"><svg width="16" height="16" aria-hidden="true"><circle cx="8" cy="8" r="6"/></svg></button>'
    . '<span data-gk-tip-off><span id="tip-off" title="The browser\'s own">opt-out</span></span>'
    . '<span id="title-slow" title="Slow title" data-gk-tooltip-delay="1000">slow title</span>'
    . '<span id="wrapper"' . Tooltip::attrs('Wrapper tip') . '><button type="button" id="wrapped">Inner</button></span>'
    . '</p><p class="row">'
    . '<span id="rich"' . Tooltip::rich('#rich-content') . '>rich</span>'
    . '<span id="rich-plain-src">.</span>'
    . '</p>'
    . '<div id="rich-content"><strong>Rich</strong> tooltip with <a href="#rich-link" id="rich-link">a link</a>.</div>'
    . '<p class="row">'
    . '<button type="button" id="tt-text"' . Tooltip::toggletip('Toggle text', ['pos' => 'bottom']) . '>Toggle</button>'
    . '<span id="tt-span"' . Tooltip::toggletip('#tt-content') . '>span toggle</span>'
    . '<span id="help-host">VAT' . Tooltip::help('Reduced rate for food and books.', ['label' => 'VAT']) . '</span>'
    . '<button type="button" id="after-help">After</button>'
    . '</p>'
    . '<div id="tt-content" hidden>Content with <a href="#tt-link" id="tt-link">a link</a> and <button type="button" id="tt-btn">a button</button>.</div>'
    // A box that scrolls, its triggers at its edges.
    . '<div id="box" style="position:relative;width:min(300px,100%);height:170px;overflow:auto;border:1px solid #888;margin:20px 0">'
    . '<div style="height:700px;width:100%;position:relative">'
    . '<span id="box-top" style="position:absolute;top:4px;left:100px"' . Tooltip::attrs('In the box, at its top', ['pos' => 'top']) . '>box top</span>'
    . '<span id="box-right" style="position:absolute;top:80px;right:2px"' . Tooltip::attrs('At the right of the box, long', ['pos' => 'top']) . '>box right</span>'
    . '<span id="box-scroll" style="position:absolute;top:50px;left:20px"' . Tooltip::attrs('Scrolls away', ['pos' => 'bottom']) . '>box scroll</span>'
    . '</div></div>'
    // A box that holds position:fixed (a transform) and sits away from the window's corner: the
    // toggletip inside is placed against that box, not the window.
    . '<div id="xf" style="transform:translateX(0);margin:10px 0 10px min(180px,8vw);padding:40px 10px;border:1px dashed #888;width:min(260px,70vw)">'
    . 'Transformed ' . Tooltip::help('Placed beside its icon, inside a transformed box', ['label' => 'Transformed']) . '</div>'
    . '<div id="table-host">' . $table . '</div>'
    . '<div id="form-host" style="max-width:420px">' . $form . '</div>'
    . '<p><button type="button" id="open-modal" onclick="GK.modal.show(\'#m\')">Open modal</button></p>'
    . '<div id="later-host"></div>'
    . '<div style="height:900px"></div>'
    . '</main>'
    . $modal;

$css = (string) file_get_contents(getenv('GK_CSS') ?: $root . '/css/gridkit.css');
$js = (string) file_get_contents(getenv('GK_JS') ?: $root . '/js/gridkit.js');
if ($opt('old-js')) {
    $js = substr($js, 0, (int) strpos($js, '// === 1.112.0 — tooltip engine'));
}
$themes = '<style>' . file_get_contents($root . '/css/themes.css') . '</style>';
$json = static fn (mixed $v): string => (string) json_encode($v, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE);
$script = match (true) {
    $opt('no-js') => '',
    $opt('late') => '<script type="text/plain" id="gk-js">' . str_replace('</', '<\/', $js) . '</script>'
        . '<script>window.addEventListener("load", function () { var s = document.createElement("script");'
        . ' s.textContent = document.getElementById("gk-js").textContent; document.body.appendChild(s); });</script>',
    default => '<script>' . $js . '</script>',
};
$bodyAttrs = 'class="gk-root' . ($mode === 'dark-class' ? ' gk-dark' : '') . '"'
    . ($theme !== '' ? ' data-gk-theme="' . htmlspecialchars($theme) . '"' : '')
    . ($mode === 'dark' ? ' data-gk-mode="dark"' : ($mode === 'light' ? ' data-gk-mode="light"' : ''));

echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
   . '<meta name="viewport" content="width=device-width, initial-scale=1"><title>Tooltips</title>'
   . '<style>' . $css . '</style>' . $themes
   . '<style>body{background:var(--gk-bg);color:var(--gk-on-surface);margin:0;font-family:var(--gk-font)}'
   . '.edge{position:fixed;z-index:5;font-size:12px;padding:2px 4px;background:var(--gk-surface-container)}'
   . '.row{display:flex;flex-wrap:wrap;gap:14px;align-items:center}.row>span{padding:2px 4px;background:var(--gk-surface-container)}'
   . 'main{padding:60px 120px}@media (max-width:640px){main{padding:60px 16px}}</style>'
   . '</head><body ' . $bodyAttrs . '>'
   . $body
   . '<script type="application/json" id="gk-fixture">' . $json(['later' => $later]) . '</script>'
   . $script . '</body></html>';
