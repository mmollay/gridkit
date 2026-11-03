<?php
require_once __DIR__ . '/../autoload.php';
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

// Language switcher — ?lang=de or ?lang=en
$lang = $_GET['lang'] ?? $_COOKIE['gk_lang'] ?? 'en';
if (!in_array($lang, ['en', 'de'])) $lang = 'en';
Lang::set($lang);

/**
 * The sample data is written in German. Translating it for the English demo
 * matters more than it looks: this table is the project's shop window, and
 * German product names tell an English visitor that GridKit is a
 * German-market tool. Prices stay as they are — the currency is genuinely EUR.
 */
$demoRows = static function (array $rows) use ($lang): array {
    if ($lang !== 'en') return $rows;

    static $map = [
        'Webdesign Paket S'   => 'Web Design Package S',
        'Webdesign Paket L'   => 'Web Design Package L',
        'Webdesign Paket'     => 'Web Design Package',
        'SEO Beratung'        => 'SEO Consulting',
        'Social Media Paket'  => 'Social Media Package',
        'Content Erstellung'  => 'Content Creation',
        'SSL Zertifikat'      => 'SSL Certificate',
        'App Entwicklung'     => 'App Development',
        'Datenbank Migration' => 'Database Migration',
        'E-Mail Marketing'    => 'Email Marketing',
        'psch'                => 'flat',
        'Stk'                 => 'pcs',
    ];

    foreach ($rows as &$row) {
        foreach ($row as $key => $value) {
            if (is_string($value) && isset($map[$value])) {
                $row[$key] = $map[$value];
            }
        }
    }
    unset($row);

    return $rows;
};

/**
 * A placeholder image as a data URI.
 *
 * The demo used to pull these from picsum.photos and i.pravatar.cc. For a
 * project whose first line is "no dependencies", a shop window that breaks when
 * a third party is slow — and that sends every visitor's IP to two of them —
 * was the wrong thing to ship. Generated here instead: no files, no requests.
 */
require_once __DIR__ . '/_showcase.php';
require_once __DIR__ . '/_whatsnew.php';

function ph(int $w, int $h, int $seed, string $initials = ''): string
{
    $hue  = ($seed * 47) % 360;
    $hue2 = ($hue + 38) % 360;

    if ($initials !== '') {
        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%1$d" height="%2$d" viewBox="0 0 %1$d %2$d">'
            . '<rect width="%1$d" height="%2$d" fill="hsl(%3$d 52%% 62%%)"/>'
            . '<text x="50%%" y="50%%" dy=".35em" text-anchor="middle" fill="#fff"'
            . ' font-family="system-ui,sans-serif" font-size="%4$d" font-weight="600">%5$s</text>'
            . '</svg>',
            $w, $h, $hue, (int) round($w * 0.4), htmlspecialchars($initials, ENT_QUOTES, 'UTF-8')
        );
    } else {
        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%1$d" height="%2$d" viewBox="0 0 %1$d %2$d">'
            . '<defs><linearGradient id="g%3$d" x1="0" y1="0" x2="1" y2="1">'
            . '<stop offset="0" stop-color="hsl(%4$d 58%% 66%%)"/>'
            . '<stop offset="1" stop-color="hsl(%5$d 54%% 44%%)"/></linearGradient></defs>'
            . '<rect width="%1$d" height="%2$d" fill="url(#g%3$d)"/>'
            . '<circle cx="%6$d" cy="%7$d" r="%8$d" fill="#fff" opacity=".16"/>'
            . '<circle cx="%9$d" cy="%10$d" r="%11$d" fill="#000" opacity=".10"/>'
            . '</svg>',
            $w, $h, $seed, $hue, $hue2,
            (int) ($w * 0.72), (int) ($h * 0.28), (int) ($w * 0.22),
            (int) ($w * 0.24), (int) ($h * 0.78), (int) ($w * 0.30)
        );
    }

    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

if (isset($_GET['lang'])) {
    setcookie('gk_lang', $lang, time() + 86400 * 365, '/');
}

$version = trim(file_get_contents(__DIR__ . '/../VERSION'));
?>
<!DOCTYPE html>
<!--
    The locale, not a constant. With ?lang=de the page renders GridKit's strings
    in German while claiming to be English, so a screen reader reads German text
    with English pronunciation rules — which makes it close to unintelligible.
-->
<html lang="<?= htmlspecialchars(Lang::locale(), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
    /*
     * Search metadata.
     *
     * The demo had a description and nothing else: no canonical, so ?lang=de,
     * ?lang=en and every cache-busting query a visitor arrives with counted as
     * separate pages competing with each other; and no Open Graph, so sharing
     * the link — the one page that actually shows what GridKit does — produced
     * a bare URL with no picture and no title.
     */
    $demoBase = 'https://gridkit.at/demo/';
    $demoUrl  = $demoBase . ($lang !== 'en' ? '?lang=' . $lang : '');
    $demoDesc = $lang === 'de'
        ? 'Jede GridKit-Komponente live: Tabellen mit Suche, Sortierung und Filter, Formulare, Modale, sechs Farbschemata und Dunkelmodus.'
        : 'Every GridKit component, live: tables with search, sort and filter, forms, modals, six themes and dark mode.';
    $e = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
    ?>
    <?php /* The version belongs in the page, not the title: a title that changes
             every release gives a search engine a moving target for one URL. */ ?>
    <title><?= $lang === 'de' ? 'GridKit Demo — alle Komponenten live' : 'GridKit Demo — every component, live' ?></title>
    <meta name="description" content="<?= $e($demoDesc) ?>">
    <link rel="canonical" href="<?= $e($demoUrl) ?>">
    <link rel="alternate" hreflang="en" href="<?= $e($demoBase) ?>">
    <link rel="alternate" hreflang="de" href="<?= $e($demoBase) ?>?lang=de">
    <link rel="alternate" hreflang="x-default" href="<?= $e($demoBase) ?>">
    <meta name="robots" content="index, follow">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="GridKit">
    <meta property="og:title" content="<?= $lang === 'de' ? 'GridKit Demo — alle Komponenten live' : 'GridKit Demo — every component, live' ?>">
    <meta property="og:description" content="<?= $e($demoDesc) ?>">
    <meta property="og:url" content="<?= $e($demoUrl) ?>">
    <meta property="og:image" content="https://gridkit.at/docs/social-preview.png">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= $lang === 'de' ? 'GridKit Demo — alle Komponenten live' : 'GridKit Demo — every component, live' ?>">
    <meta name="twitter:description" content="<?= $e($demoDesc) ?>">
    <meta name="twitter:image" content="https://gridkit.at/docs/social-preview.png">
    <link rel="stylesheet" href="<?= Layout::asset('../css/gridkit.css') ?>">
    <link rel="stylesheet" href="<?= Layout::asset('../css/themes.css') ?>">
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <script>
    /*
     * CKEditor is 1.66 MB — 1446 KB of script and 217 KB of stylesheet — and
     * it backs exactly one card, in one of eleven sections, ten of which are
     * hidden when this page opens. It used to sit right here as a blocking
     * <script> in the head: every visitor to the demo paid for the editor
     * before anything rendered, and almost none of them ever scrolled to it.
     *
     * The paragraph beside that card has always said "Initialization via
     * IntersectionObserver". That was true of the initialisation and never of
     * the download.
     *
     * It is fetched the first time a rich-text field actually comes into view.
     * GridKit's own field waits for the bundle instead of giving up on it
     * (1.78.0) — before that, arriving late meant the field stayed a plain
     * textarea for good, which is why this had to block in the first place.
     */
    (function () {
      var geladen = false;
      function laden() {
        if (geladen) return;
        geladen = true;
        var l = document.createElement('link');
        l.rel = 'stylesheet';
        l.href = '../assets/ckeditor5/ckeditor5.css';
        document.head.appendChild(l);
        var s = document.createElement('script');
        s.src = '../assets/ckeditor5/ckeditor5.umd.js';
        document.head.appendChild(s);
      }
      function beobachten() {
        var el = document.querySelector('.gk-richtext-wrap');
        if (!el) return;
        // No observer: load it rather than leave the field dead.
        if (typeof IntersectionObserver === 'undefined') { laden(); return; }
        var o = new IntersectionObserver(function (e) {
          if (e[0].isIntersecting) { laden(); o.disconnect(); }
        }, { threshold: 0 });
        o.observe(el);
      }
      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', beobachten);
      } else {
        beobachten();
      }
    })();
    </script>
    <style>
        body { margin:0; padding:0; background:var(--gk-surface-container, #f0f1f3); font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; color:var(--gk-on-surface, #1f2937); }
        .demo-section { max-width:1100px; margin:24px auto; padding:0 24px; display:none; }
        .demo-section.active { display:block; }
        .demo-section h2 { font-size:20px; margin:0 0 16px; color:var(--gk-on-surface, #374151); }
        .demo-section h3.demo-h3 { margin:32px 0 8px; }
        .demo-tabs-oben { margin-top:16px; }
        .demo-tabs-box { max-width:100%; margin:0 0 24px; }
        .demo-tabs-box-700 { width:700px; }
        .demo-tabs-box-560 { width:560px; }
        .demo-tabs-box-340 { width:340px; }
        .demo-tabs-log { min-height:4.6em; margin:12px 0 0; padding:8px 12px; border-radius:8px; background:var(--gk-surface-container); color:var(--gk-on-surface); font-size:13px; white-space:pre-wrap; }
        .demo-tabs-api { display:flex; flex-wrap:wrap; align-items:center; gap:8px; margin-top:12px; }
        .demo-tabs-api output { font-size:13px; color:var(--gk-on-surface-variant); }
        /* Reiter-Varianten (1.103.0): Baukasten und Telefonrahmen der Demo */
        .demo-baukasten-felder { display:grid; grid-template-columns:repeat(auto-fit, minmax(10rem, 1fr)); gap:12px; margin-bottom:16px; }
        .demo-baukasten-felder .gk-field { margin:0; }
        .demo-baukasten-vorschau { border:1px dashed var(--gk-outline); border-radius:8px; padding:16px; margin-bottom:16px; min-height:8rem; }
        .demo-baukasten-kopf { display:flex; align-items:center; flex-wrap:wrap; gap:12px; }
        .demo-baukasten-status { font-size:14px; color:var(--gk-on-surface-variant); }
        .demo-tabs-telefon { width:360px; max-width:100%; height:300px; overflow:auto; border:2px solid var(--gk-outline); border-radius:16px; background:var(--gk-surface); }
        .demo-tabs-telefon > .gk-tabs { min-height:100%; display:flex; flex-direction:column; }
        .demo-tabs-telefon > .gk-tabs > .gk-tab-nav.gk-tab-nav-fest { margin-top:auto; }
        .demo-tabs-telefon .gk-tab-panel { padding:16px; }
        [data-section="tabs"] .demo-showcase-output { background:var(--gk-surface, #fff); border-radius:8px; padding:24px; border:1px solid transparent; box-shadow:var(--gk-shadow); }
        [data-gk-mode="dark"] [data-section="tabs"] .demo-showcase-output, .gk-dark [data-section="tabs"] .demo-showcase-output { background:var(--gk-surface-container); border:1px solid rgba(255,255,255,0.14); box-shadow:none; }
        [data-section="tabs"] .demo-showcase-output > .gk-tab-nav + .gk-tab-nav, [data-section="tabs"] .demo-showcase-output > .gk-tabs + .gk-tabs { margin-top:24px; }
        .demo-section .gk-form { max-width:none; }
        .demo-section .gk-richtext-wrap { border-width:1px; }
        .demo-card-flush { padding:0; overflow:hidden; }
        .demo-rule { border:none; border-top:1px solid var(--gk-outline-variant); margin:40px 0; }
        .demo-intro-pad { padding:16px 24px 0; margin:0; }
        .demo-card { background:var(--gk-surface, #fff); border-radius:8px; padding:24px; border:1px solid transparent; box-shadow:var(--gk-shadow); margin-bottom:24px; }
        [data-gk-mode="dark"] .demo-card, .gk-dark .demo-card {
            background:var(--gk-surface-container);
            border:1px solid rgba(255,255,255,0.14);
            box-shadow:none;
        }
        [data-gk-mode="dark"] .demo-stat, .gk-dark .demo-stat {
            background:var(--gk-surface-container);
            border:1px solid rgba(255,255,255,0.14);
            box-shadow:none;
        }
        [data-gk-mode="dark"] .demo-code, .gk-dark .demo-code {
            background:var(--gk-surface);
            border:1px solid rgba(255,255,255,0.08);
            color:#E6EDF3;
        }
        [data-gk-mode="dark"] .demo-intro, .gk-dark .demo-intro {
            color:#8B949E;
        }
        [data-gk-mode="dark"] .demo-section h2, .gk-dark .demo-section h2 {
            color:#E6EDF3;
        }
        .demo-card .gk-table-wrap { border:none !important; }
        .demo-code { background:var(--gk-surface-dim, #1e293b); color:var(--gk-on-surface, #e2e8f0); padding:20px; border-radius:8px; overflow-x:auto; font-family:'SF Mono',Monaco,Consolas,monospace; font-size:13px; line-height:1.6; margin-top:16px; }
        .demo-code pre { margin:0; }

        /* ── Gallery section: the image picker demos ────────────────────── */
        .demo-fieldset { border:0; margin:0; padding:0; min-width:0; }
        .demo-fieldset .gk-label-text { padding:0; margin-bottom:10px; }
        .demo-picker-form .demo-picker-value { margin:16px 0 8px; font-size:13px; color:var(--gk-on-surface-variant); }
        .demo-log { margin:0 0 12px; padding:12px 14px; max-height:140px; overflow:auto; border-radius:8px; background:var(--gk-surface-container); color:var(--gk-on-surface);
            font:12px/1.6 'SF Mono',Monaco,Consolas,monospace; white-space:pre-wrap; }
        .demo-pair { display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:24px; }
        .demo-keys { width:100%; font-size:13px; }
        .demo-keys kbd { display:inline-block; min-width:1.6em; padding:1px 6px; border:1px solid var(--gk-outline-variant); border-bottom-width:2px; border-radius:5px; background:var(--gk-surface-container-low);
            font:600 12px/1.5 system-ui,sans-serif; text-align:center; }
        .demo-rule { border:none; border-top:1px solid var(--gk-outline-variant); margin:40px 0; }

        /* ── Timeline and portrait video frame (1.96.0) ─────────────────── */
        .demo-timelines { display:grid; grid-template-columns:repeat(auto-fit, minmax(300px, 1fr)); gap:24px; }
        .demo-timelines h4 { margin:0 0 12px; font-size:13px; font-weight:600; color:var(--gk-on-surface-variant); }
        .demo-frames { display:flex; flex-wrap:wrap; gap:24px; align-items:flex-start; }
        .demo-frames .gk-video-frame { flex:0 1 220px; }

        /* ── Sortable container (1.97.0) ────────────────────────────────── */
        .demo-sort-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:16px; }
        .demo-sort-list { list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:8px; }
        .demo-sort-grid > *, .demo-sort-list > * { padding:16px 64px 16px 16px; border:1px solid var(--gk-outline-variant);
            border-radius:var(--gk-radius); background:var(--gk-surface-container); }
        /* ~, not +: js/sortierbar.js puts its live region (.gk-sort-meldungen) right after each
           container, so the list is no longer the grid's next sibling and + never matched. */
        .demo-sort-grid ~ .demo-sort-list { margin-top:24px; }
        .demo-sort-grid h4, .demo-sort-list h4 { margin:0 0 4px; font-size:15px; }
        .demo-sort-grid p, .demo-sort-list p { margin:0; font-size:13px; color:var(--gk-on-surface-variant); }

        /* ── Beispiel mit ausklappbarem Code ─────────────────────────────
           <details> statt eines Divs mit Klick-Handler: es funktioniert ohne
           JavaScript, ist von Haus aus mit der Tastatur bedienbar und wird von
           Vorleseprogrammen korrekt als aufklappbarer Bereich angesagt. */
        .demo-showcase { margin-bottom: 24px; }
        .demo-showcase-code { margin-top: 12px; }
        .demo-showcase-code summary {
            display: inline-flex; align-items: center; gap: 6px;
            cursor: pointer; user-select: none; list-style: none;
            font-size: 13px; font-weight: 600; color: var(--gk-on-surface-variant);
            padding: 6px 12px; border: 1px solid var(--gk-outline-variant);
            border-radius: 999px; transition: .15s;
        }
        .demo-showcase-code summary::-webkit-details-marker { display: none; }
        .demo-showcase-code summary:hover { color: var(--gk-primary); border-color: var(--gk-primary); }
        .demo-showcase-code summary:focus-visible { outline: 2px solid var(--gk-primary); outline-offset: 2px; }
        .demo-showcase-code summary .material-icons { font-size: 16px; }
        .demo-showcase-code[open] summary { margin-bottom: 10px; }
        .demo-showcase-src { position: relative; }
        .demo-showcase-src pre {
            margin: 0; padding: 16px; overflow-x: auto;
            background: var(--gk-surface-dim, #1e293b); color: var(--gk-on-surface, #e2e8f0);
            border-radius: 8px; font-size: 12.5px; line-height: 1.6;
            font-family: "JetBrains Mono", ui-monospace, monospace;
        }
        /* Token-based, not white-on-dark: .demo-code renders light in light mode,
           so a button styled for a dark block was invisible on it. */
        .demo-showcase-copy {
            position: absolute; top: 8px; right: 8px; z-index: 1;
            font: inherit; font-size: 11px; font-weight: 600; cursor: pointer;
            background: var(--gk-surface); color: var(--gk-on-surface-variant);
            border: 1px solid var(--gk-outline-variant); border-radius: 6px; padding: 4px 10px;
            opacity: .75; transition: .15s;
        }
        .demo-showcase-copy:hover { opacity: 1; color: var(--gk-primary); border-color: var(--gk-primary); }
        .demo-showcase-copy:focus-visible { opacity: 1; outline: 2px solid var(--gk-primary); outline-offset: 2px; }
        .demo-showcase-copy[data-ok] { opacity: 1; color: var(--gk-success); border-color: var(--gk-success); }
        /* ── What is new, and since when (1.93.0) ─────────────────────────
           The words come from CHANGELOG.md, the marks from css/blocks.json;
           demo/_whatsnew.php reads both. Labels are GridKit's own green and
           blue — no colour of the demo's. No margin before the marks: the
           space in the source parts them from the name, and a space at a line
           break goes away — a margin went with them and indented the wrapped
           line on a phone. */
        .demo-since { display:inline-flex; flex-wrap:wrap; align-items:center; gap:6px; font-size:var(--gk-text-label, 13px); font-weight:400; vertical-align:middle; }
        .demo-news-wrap { max-width:1100px; margin:24px auto 0; padding:0 24px; }
        /* News by component in the sidebar (1.110.4): GridKit's own label colours, green new and
           blue changed; collapsed, a dot on the symbol. */
        .demo-nav-news { margin-left:auto; font-size:10px; font-weight:600; line-height:1; padding:3px 7px; border-radius:9999px; white-space:nowrap; letter-spacing:.02em; }
        .demo-nav-news-new { background:var(--gk-success-container, #dcfce7); color:var(--gk-on-success-container, #14532d); }
        .demo-nav-news-changed { background:var(--gk-info-container, #dbeafe); color:var(--gk-on-info-container, #1e3a8a); }
        .gk-sidebar-item { position:relative; }
        .gk-sidebar.collapsed .demo-nav-news { position:absolute; top:6px; left:34px; width:8px; height:8px; padding:0; margin:0; }
        .gk-sidebar.collapsed .demo-nav-news > [aria-hidden] { display:none; }
        .gk-sidebar.collapsed .demo-nav-news-new { background:var(--gk-success, #16a34a); }
        .gk-sidebar.collapsed .demo-nav-news-changed { background:var(--gk-info, #2563eb); }
        .demo-component-news { margin-bottom:32px; }
        .demo-news { margin:0; }
        .demo-news-summary { cursor:pointer; font-size:17px; font-weight:600; }
        .demo-news-summary:focus-visible { outline:2px solid var(--gk-primary); outline-offset:2px; }
        .demo-news a { color:var(--gk-primary-text, var(--gk-primary)); text-decoration:underline; text-underline-offset:2px; }
        .demo-news-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(min(100%, 320px), 1fr)); gap:16px; margin-top:12px; align-items:start; }
        .demo-news-card { margin:0; display:flex; flex-direction:column; gap:12px; padding:16px 20px; min-width:0; }
        .demo-news-head { display:flex; flex-wrap:wrap; align-items:baseline; gap:4px 12px; }
        .demo-news-version { margin:0; font-size:18px; font-weight:700; }
        .demo-news-date { font-size:var(--gk-text-label, 13px); color:var(--gk-on-surface-variant); }
        .demo-news-intro { margin:0; font-size:14px; line-height:1.5; }
        .demo-news-group-title { display:flex; align-items:center; gap:8px; margin:0 0 6px; font-size:14px; font-weight:600; }
        .demo-news-count { color:var(--gk-on-surface-variant); font-weight:500; }
        .demo-news-list { margin:0; padding:0; list-style:none; display:flex; flex-direction:column; gap:4px; font-size:14px; line-height:1.5; }
        .demo-news-list li { padding-left:14px; text-indent:-14px; }
        .demo-news-list li::before { content:"\00b7\00a0\00a0"; }
        .demo-news-list .demo-news-more::before { content:none; }
        .demo-news-more { color:var(--gk-on-surface-variant); }
        .demo-news-classes { display:flex; flex-wrap:wrap; align-items:center; gap:6px; font-size:var(--gk-text-label, 13px); }
        .demo-news-classes-title { font-weight:600; margin-right:2px; }
        .demo-news-class { font-family:ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size:12px; white-space:normal; overflow-wrap:anywhere; }
        .demo-news-jump { margin:auto 0 0; font-size:14px; }
        .demo-news-older { margin-top:16px; }
        .demo-news-rows { list-style:none; margin:8px 0 0; padding:0; border-top:1px solid var(--gk-outline-variant); }
        .demo-news-row { display:grid; grid-template-columns:10rem minmax(0, 1fr); gap:4px 16px; padding:10px 0; border-bottom:1px solid var(--gk-outline-variant); font-size:14px; line-height:1.5; }
        .demo-news-row-head { display:flex; flex-wrap:wrap; align-items:baseline; gap:2px 10px; }
        .demo-news-row .demo-news-version { font-size:15px; }
        .demo-news-row-text { display:flex; flex-direction:column; gap:6px; }
        .demo-news-counts { display:flex; flex-wrap:wrap; align-items:center; gap:4px 6px; }
        .demo-news-all { margin:16px 0 0; }
        .demo-util-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(170px, 1fr)); gap:8px; }
        .demo-util-cell { border:1px solid var(--gk-outline-variant); border-radius:6px; padding:8px; display:flex; flex-direction:column; gap:6px; min-width:0; overflow:hidden; }
        .demo-util-cell code { font-size:11px; overflow-wrap:anywhere; }
        .demo-util-box { min-height:32px; background:var(--gk-surface-variant, rgba(0,0,0,.04)); border-radius:4px; overflow:hidden; }
        @media (max-width:600px) { .demo-news-row { grid-template-columns:minmax(0, 1fr); } }
        .demo-announce-row { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:12px; }
        .demo-pair { display:flex; flex-direction:column; gap:16px; margin-bottom:24px; }
        .demo-pair-left { display:contents; }
        .demo-stats-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:16px; margin-bottom:24px; }
        .demo-stat { background:var(--gk-surface, #fff); border-radius:8px; padding:20px; text-align:center; box-shadow:var(--gk-shadow); }
        .demo-stat .num { font-size:28px; font-weight:700; color:var(--gk-primary); }
        .demo-stat .lbl { font-size:13px; color:var(--gk-on-surface-variant, #6b7280); margin-top:4px; }
        .demo-intro { color:var(--gk-on-surface-variant, #6b7280); margin:0 0 16px; font-size:14px; line-height:1.6; }
        .demo-btn-row { display:flex; gap:8px; flex-wrap:wrap; }
        .anatomy-layout { display:flex; gap:16px; align-items:stretch; }
        /* display lives here, not inline: an inline display:flex outranks any
           stylesheet rule, so the display:none below never fired and the
           schematic stayed on phones — 292px wide instead of its 220px basis,
           clipping its own header row by 17px. */
        .anatomy-mockup { flex:0 0 220px; display:flex; flex-direction:column; }
        @media (max-width: 768px) {
            .anatomy-layout { flex-direction:column; }
            .anatomy-mockup { display:none; }
        }
    </style>
<?= Lang::jsConfig() ?>
</head>
<?= Layout::bodyTag('gk-root') ?>

<?php
$sidebar = new Sidebar('demo');
$sidebar->brand('', 'widgets')
    ->group('Components')
    ->item('Table', '#table', 'table_chart', ['active' => true])
    ->item('Form', '#form', 'edit_note')
    ->item('Cards', '#cards', 'grid_view')
    ->item('Layout', '#layout', 'layers')
    ->item('Tabs', '#tabs', 'tab')
    ->item('Navigation', '#navigation', 'filter_list')
    ->item('Gallery', '#gallery', 'photo_library')
    ->item('Search', '#suche', 'search')
    ->item('Feedback', '#feedback', 'notifications')
    ->item('UI', '#ui', 'palette')
    ->item('Tooltip', '#tooltip', 'chat_bubble')
    ->group('Examples')
    ->item('Examples', '#examples', 'rocket_launch')
    ->group('Info')
    ->item('Changelog', '#changelog', 'history');
// News by component (1.110.4): each section with something new or changed in the last two
// minors says so beside its name — "New" or "Update" — before anyone scrolls to it.
ob_start();
$sidebar->render();
echo gkNavNews((string) ob_get_clean());
?>

<div class="gk-with-sidebar">

<?php
$demoHeader = new Header();
$headerTitle = 'GridKit <span style="font-size:11px;font-weight:400;color:var(--gk-on-surface-variant);margin-left:4px">v' . $version . '</span>';
$langSwitch = $lang === 'en' ? 'de' : 'en';
$langLabel  = $lang === 'en' ? 'DE' : 'EN';
$langBtn    = Button::render($langLabel, ['variant' => 'outlined', 'color' => 'neutral', 'size' => 'sm', 'icon' => 'translate', 'href' => '?lang=' . $langSwitch]);

echo $demoHeader->title($headerTitle, true)
    ->sidebarToggle(true)
    ->fixed(true)
    ->action($langBtn)
    ->user('Demo User', [
        'avatar' => ph(72, 72, 12, 'JD'),
        'menu' => [
            ['label' => 'Profile', 'href' => '#', 'icon' => 'person'],
            ['label' => 'Settings', 'href' => '#', 'icon' => 'settings'],
            'divider',
            ['label' => 'Sign out', 'href' => 'login.php?logout=1', 'icon' => 'logout'],
        ],
    ])
    ->render();
?>

<div class="demo-news-wrap"><?= whatsNew() ?></div>

<?php require __DIR__ . '/sections/table.php'; ?>
<?php require __DIR__ . '/sections/form.php'; ?>
<?php require __DIR__ . '/sections/cards.php'; ?>
<?php require __DIR__ . '/sections/layout.php'; ?>
<?php require __DIR__ . '/sections/tabs.php'; ?>
<?php require __DIR__ . '/sections/navigation.php'; ?>
<?php require __DIR__ . '/sections/gallery.php'; ?>
<?php require __DIR__ . '/sections/search.php'; ?>
<?php require __DIR__ . '/sections/feedback.php'; ?>
<?php require __DIR__ . '/sections/ui.php'; ?>
<?php require __DIR__ . '/sections/examples.php'; ?>
<?php require __DIR__ . '/sections/tooltip.php'; ?>
<?php require __DIR__ . '/sections/changelog.php'; ?>
</div><!-- /gk-with-sidebar -->

<script src="<?= Layout::asset('../js/gridkit.js') ?>"></script>
<script src="<?= Layout::asset('../js/sortierbar.js') ?>" defer></script>
<script>
(function() {
    var sections = document.querySelectorAll('.demo-section');
    var links = document.querySelectorAll('.gk-sidebar-item');

    // The fragment is shared since 1.111.0: Tabs::hash() keeps "id=key" pairs in it, joined by "&",
    // beside the demo's own section ("#tabs&demo-deep=billing"). The section is the part without "=";
    // an id of an element inside a section (an anchor such as #tabs-php) shows that section; with only
    // pairs, the section of the first row they name.
    function sectionOf(hash) {
        var parts = hash.replace(/^#/, '').split('&').filter(Boolean);
        var inside = function (id) {
            var el = id ? document.getElementById(id) : null;
            var s = el ? el.closest('.demo-section') : null;
            return s ? s.getAttribute('data-section') : null;
        };
        for (var i = 0; i < parts.length; i++) {
            if (parts[i].indexOf('=') !== -1) continue;
            if (document.querySelector('[data-section="' + parts[i].replace(/"/g, '') + '"]')) return parts[i];
            if (inside(parts[i])) return inside(parts[i]);
        }
        for (var j = 0; j < parts.length; j++) {
            var name = parts[j].split('=')[0];
            try { name = decodeURIComponent(name); } catch (e) {}
            if (inside(name)) return inside(name);
        }
        return null;
    }

    function showSection(id) {
        sections.forEach(function(s) { s.classList.remove('active'); });
        var target = document.querySelector('[data-section="' + id + '"]');
        if (target) target.classList.add('active');
        links.forEach(function(a) {
            a.classList.remove('active');
            if (a.getAttribute('href') === '#' + id) a.classList.add('active');
        });
        if (window.innerWidth <= 768) GK.sidebar.close();
    }

    links.forEach(function(a) {
        a.addEventListener('click', function(e) {
            var href = this.getAttribute('href');
            if (href && href.startsWith('#')) {
                e.preventDefault();
                showSection(href.substring(1));
                history.replaceState(null, '', href);
            }
        });
    });

    showSection(sectionOf(window.location.hash) || 'table');
    // The news box is open while it has news for this browser: on a first visit and
    // whenever the newest release is one it has not shown yet. After that it starts
    // folded, so the first example is on the first screen; its summary line still names
    // the newest versions. On a phone it always starts folded: open, it stood a screen
    // and a half above the first example. Without storage (a private window) it stays open.
    var news = document.querySelector('.demo-news');
    if (news) {
        var card = news.querySelector('.demo-news-card');
        var newest = card ? card.getAttribute('aria-labelledby') || '' : '';
        var seen = '';
        try {
            seen = localStorage.getItem('gk-demo-news-seen') || '';
            if (newest !== '') localStorage.setItem('gk-demo-news-seen', newest);
        } catch (e) { /* no storage: nothing is remembered */ }
        var phone = window.matchMedia && window.matchMedia('(max-width: 768px)').matches;
        if (phone || (newest !== '' && seen === newest)) news.open = false;
    }

    // "All release notes" in the news box is a plain link, not a sidebar item.
    window.addEventListener('hashchange', function () {
        var id = sectionOf(window.location.hash);
        if (id) showSection(id);
    });
})();

// Image picker demos: show what the page hears, ask before removing, add a tile from script.
(function () {
    var six = document.getElementById('demo-pk-six');
    if (!six) return;
    var log = document.getElementById('demo-picker-log');
    var value = document.getElementById('demo-picker-value');
    var lines = [];
    function say(text) {
        lines.unshift(text);
        log.textContent = lines.slice(0, 6).join('\n');
    }
    document.getElementById('demo-picker-form').addEventListener('change', function (e) {
        if (!e.target.classList.contains('gk-picker-input')) return;
        value.textContent = e.target.value;
        say('change → ' + e.target.name + ' = ' + e.target.value);
    });
    six.addEventListener('gk:pickeraction', function (e) {
        var d = e.detail;
        say('gk:pickeraction → ' + d.action + ' ' + d.value);
        GK.confirm('Remove this image?', { danger: true, confirmText: 'Remove' }).then(function (ok) {
            if (!ok) { say('  … cancelled, nothing removed'); return; }
            var checked = d.picker.querySelector('.gk-picker-input:checked');
            GK.picker.remove(d.picker, d.value);
            say('  … removed ' + d.value);
            if (checked && checked.value === d.value) value.textContent = '(none)';
        });
    });
    var added = 0;
    document.getElementById('demo-picker-add').addEventListener('click', function () {
        added++;
        var seed = 40 + added;
        var svg = function (w, h) {
            return 'data:image/svg+xml;base64,' + btoa('<svg xmlns="http://www.w3.org/2000/svg" width="' + w + '" height="' + h + '"><rect width="100%" height="100%" fill="hsl(' + (seed * 47 % 360) + ' 55% 50%)"/>'
                + '<text x="50%" y="50%" font-size="' + Math.round(h / 3) + '" text-anchor="middle" dominant-baseline="central" fill="#fff">new ' + added + '</text></svg>');
        };
        GK.picker.add(six, { value: 'new-' + added, title: 'Fresh upload ' + added, thumb: svg(320, 180), full: svg(1600, 900), meta: 'Own image',
            actions: [{ name: 'remove', label: 'Remove' }] }, { select: true, focus: true });
        say('GK.picker.add → new-' + added + ' (picked, focused)');
    });
    var empty = document.getElementById('demo-pk-empty');
    if (empty) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'gk-btn gk-btn-outlined gk-btn-neutral gk-btn-sm';
        button.textContent = 'Add one image to the empty picker';
        empty.appendChild(button);
        button.addEventListener('click', function () {
            GK.picker.add(empty, { value: 'first', title: 'The first image', thumb: 'data:image/svg+xml;base64,' + btoa('<svg xmlns="http://www.w3.org/2000/svg" width="320" height="180"><rect width="100%" height="100%" fill="teal"/></svg>') }, { select: true });
            button.remove();
        });
    }
})();

document.querySelectorAll('.gk-upload-zone[data-gk-upload]').forEach(function(zone) {
    zone.addEventListener('gk:files', function(e) {
        e.detail.items.forEach(function(item) {
            GK.uqSetUploading && item.el && GK.uqSetUploading(item);
            setTimeout(function() {
                GK.uqSetDone && item.el && GK.uqSetDone(item, item.file ? item.file.name : 'File');
            }, 1000 + Math.random() * 1000);
        });
    });
});

(function() {
    var simCounter = 0;
    function makeQueueItem(label, isError) {
        var list = document.getElementById('queue-demo-list');
        if (!list) return;
        var fakeFile = { name: label, size: Math.floor(Math.random() * 5 * 1024 * 1024) };
        var item = { file: fakeFile, el: null, id: 'qdemo-' + (++simCounter) };
        var el = document.createElement('div');
        el.style.cssText = 'display:flex;align-items:center;gap:10px;padding:10px 14px;background:var(--gk-surface-container);border-radius:6px;font-size:13px;';
        el.innerHTML = '<span class="material-icons" style="font-size:18px;color:var(--gk-primary);" aria-hidden="true">hourglass_empty</span>'
                     + '<span style="flex:1;">' + label + '</span>'
                     + '<span style="color:var(--gk-on-surface-variant);">' + (GK._formatSize ? GK._formatSize(fakeFile.size) : '') + '</span>';
        item.el = el;
        list.appendChild(el);
        setTimeout(function() {
            el.querySelector('.material-icons').textContent = 'upload';
            el.querySelector('.material-icons').style.color = 'var(--gk-primary)';
            GK.uqSetUploading && GK.uqSetUploading && item.el && GK.uqSetUploading(item);
            setTimeout(function() {
                if (isError) { GK.uqSetError && GK.uqSetError(item, 'Connection interrupted'); }
                else { GK.uqSetDone && GK.uqSetDone(item, label); }
            }, 1200 + Math.random() * 800);
        }, 300);
    }
    var btnSim = document.getElementById('btn-queue-sim');
    var btnErr = document.getElementById('btn-queue-err');
    if (btnSim) btnSim.addEventListener('click', function() { makeQueueItem('dokument_' + simCounter + '.pdf', false); });
    if (btnErr) btnErr.addEventListener('click', function() { makeQueueItem('fehler_' + simCounter + '.zip', true); });
})();

GK.search.init({ url: 'api/quicksearch.php', hotkey: 'ctrl+k', minLength: 0 });
</script>

<script>
/* The copy button on a code disclosure. Delegated, because the disclosures are
   spread across the page and several are added by showcase() at render time. */
document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-demo-copy]');
    if (!btn) return;
    var code = btn.parentElement.querySelector('code');
    if (!code) return;
    var done = function () {
        var was = btn.textContent;
        btn.textContent = '\u2713';
        btn.setAttribute('data-ok', '');
        setTimeout(function () { btn.textContent = was; btn.removeAttribute('data-ok'); }, 1600);
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(code.textContent).then(done, function () {});
    }
});
</script>
</body>
</html>
