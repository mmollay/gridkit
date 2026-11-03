# GridKit 1.110.0 — CSS

Generated from GRIDKIT_SKILL.md. Rules first: see ../SKILL.md.

## CSS Classes Reference

| Class | Purpose |
|-------|---------|
| `gk-root` | Root container (on `<body>`) |
| `gk-with-sidebar` | Content area beside sidebar |
| `gk-body-with-header` | Content area below fixed header |
| `gk-btn` | Button base |
| `gk-btn-filled` | Filled button variant |
| `gk-btn-outlined` | Outlined button variant |
| `gk-btn-tonal` | Tonal button variant |
| `gk-btn-text` | Text button variant |
| `gk-btn-icon-only` | Icon-only button (no text) |
| `gk-btn-sm` | Small button size |
| `gk-card` | Card container |
| `gk-toolbar-spacer` | Pushes toolbar content right |
| `gk-filter-chips` | FilterChips container |
| `gk-chip` `gk-chip-active` | Individual chip |
| `gk-stat-cards` | StatCards container |
| `gk-modal-overlay` | Modal background |
| `gk-modal` | Modal box |
| `gk-modal-small` `gk-modal-large` | Modal size modifiers |
| `gk-text-muted` | Muted text color |
| `gk-section-title` | Section heading style |
| `gk-page-header` | Page title + action area |
| `gk-page-header-actions` | The action area itself (since 1.88.0): a wrapping flex row, right-aligned. Outside every media query — a rule of your own must load after gridkit.css or be more specific |
| `gk-empty` | Empty state (centered, padded) |
| `gk-tabs` `gk-tab-nav` `gk-tab-btn` `gk-tab-panel` | Tabs, authored markup (state class: `gk-active`) |
| `gk-tab-nav-karte` `gk-tab-flaeche` `gk-tab-badge` `gk-tab-count` | Karteireiter: framed tabs on their surface, badge, count (1.101.0) |
| `gk-tab-nav-raster` `gk-tab-badge-error` | Karteireiter row as a grid when it does not fit its box (set by `GK.tabsFit`, not by hand); the error badge (1.102.0) |
| `gk-ortszeile-schlicht` | Option `schlicht` of `Header::ortszeile()`: on a phone (up to 768px) plain links without the pill outline, 44px target kept (1.104.0) |
| `gk-tab-nav-einzeilig` `gk-tab-nav-flach` | Karteireiter on a phone (up to 600px), opt-in: ONE row of equal cells instead of two columns; icon beside the word, cell 44px high instead of about 56px (1.104.0) |
| `gk-tab-nav-linie` `gk-tab-nav-kapsel` `gk-tab-nav-text` | Tab row styles beside `gk-tab-nav-karte`: line with a stroke under the chosen tab, pill with the chosen tab filled, words only (1.103.0) |
| `gk-tab-nav-links` `gk-tab-nav-rechts` `gk-tab-nav-unten` `gk-tab-nav-fest` | Tab row alignment: a side column from 601px (the horizontal row below), the row after the panels, a bar that sticks to the lower edge (1.103.0) |
| `gk-tab-nav-sm` `gk-tab-nav-lg` `gk-tab-nav-fluid` `gk-tab-nav-icon-oben` `gk-tab-nav-nur-icon` `gk-tab-nav-neutral` | Tab row options: size, full width, symbol above the word, symbol only (`aria-label` + `title`), the chosen tab in the text colour (1.103.0) |
| `gk-tabs-nav` `gk-tab` | Tabs, generated nav (state class: `gk-tab-active`) |
| `gk-accordion` `gk-accordion-item` `gk-accordion-trigger` `gk-accordion-content` `gk-accordion-body` | Accordion (state class: `open` on the item) |
| `gk-gallery` `gk-gallery-item` `gk-gallery-overlay` | Image gallery |
| `gk-picker` `gk-picker-grid` `gk-picker-item` `gk-picker-media` `gk-picker-input` `gk-picker-caption` | Image picker (since 1.94.0): container, grid, one tile, its picture area, the radio drawn as that area, the title row |
| `gk-card-elevated` `gk-card-flat` | Card with a shadow / with a hairline border instead |
| `gk-card-image` | A picture flush with the top of a card (rounds the two upper corners) |
| `gk-card-title` `gk-card-actions` | Heading inside a card / a right-aligned row of buttons in its header |
| `gk-field-hint` `gk-field-has-error` | Help text under a field / colours a label to match its error |
| `gk-checkbox-label` `gk-radio-label` | Label sitting beside its own box or dot |
| `gk-form-page` `gk-form-wide` | A form capped at 800 px / one with no cap at all |
| `gk-toolbar-row` `gk-toolbar-row-search` `gk-toolbar-row-filters` | Wrapping toolbar; the search half grows, the filter half does not |
| `gk-accordion-flush` | Accordion without its own border or rounding, for one already inside a card |
| `gk-richtext-toolbar` `gk-richtext-content` `gk-richtext-btn` | The rich-text field's own parts |
| `gk-skeleton` | Grey shimmer standing in for text that has not arrived |
| `gk-sheet` `gk-sheet-header` `gk-sheet-title` `gk-sheet-close` `gk-sheet-body` `gk-sheet-footer` | Side sheet (since 1.91.0): docked right, full screen and modal on a phone; hidden with the `hidden` attribute |
| `gk-sheet-push` | Content that makes room for a docked sheet while one is open (wide screens only) |
| `gk-row-link` `gk-row-target` | A row that opens something (on the `<tr>`), and the one control in its main cell that it forwards clicks to |
| `gk-table-more` `gk-table-more-toggle` `gk-table-more-name` | One row standing for many: the row, the full-width toggle (`aria-controls` names the rows, usually a `<tbody hidden>`), its label |
| `gk-main` | The content area (since 1.92.0 a rule, before only a name): padding 24px 28px 48px, 16px on a phone, capped at `--gk-main-max` (1680px) |
| `gk-header-meta` | A quiet line beside the header title, on its baseline; shortened first, gone at 768px and below |
| `gk-table-sticky-head` `gk-table-sticky-col` `gk-table-sticky-box` | `Table->stickyHead()` / `->stickyColumn()` (since 1.107.0): on `.gk-table-wrap`, what sticks; the box around the `<table>` it scrolls in (`data-gk-sticky-col` = how many cells of a row stick, `--gk-table-sticky-height` its height) |
| `gk-table-list` | On `.gk-table-wrap` (`Table->size('list')`): the list row of the six rules — 64px, name 15px over 13px, quiet groups; a size container, never cards, never sideways scroll |
| `gk-col-p2` `gk-col-p3` `gk-col-p4` `gk-col-p5` | On th and td of a list column (`'priority' => n`): gone below 560 / 720 / 900 / 1040px of LIST width |
| `gk-cell-who` `gk-cell-who-text` `gk-cell-sub-wrap` | The who cell: avatar, name, a label that moves under the name, the address underneath; a second line that breaks instead of being shortened |
| `gk-avatar-tone-1` … `gk-avatar-tone-5` | Five quiet role pairs for initials, told apart side by side |
| `gk-dot` `gk-dot-success` `gk-dot-warning` `gk-dot-danger` `gk-dot-primary` `gk-dot-muted` `gk-dot-outline` `gk-dot-pulse` | Status dot beside its words; outline = empty ring, pulse = running (still under reduced motion) |
| `gk-setting` `gk-setting-text` `gk-setting-title` `gk-setting-hint` `gk-setting-control` `gk-setting-danger` | A settings row: title and one sentence, the switch on the right |
| `gk-field-static` `gk-field-static-label` `gk-field-static-value` | A read-only field (reason under it: `gk-field-hint`) |
| `gk-stat-tiles` `gk-stat-tile` `gk-stat-tile-value` `gk-stat-tile-label` `gk-stat-tile-sub` `gk-stat-tile-warning` `gk-stat-tile-danger` | Compact key figures (`StatCards->compact()`); a tile may be a `<button>` or `<a>` |
| `gk-sheet-meta` | A line under the side sheet's title |
| `gk-swatch` `gk-swatch-1` … `gk-swatch-5`, `gk-series-fill-1` … `-5`, `gk-series-stroke-1` … `-5` | Chart colours from `--gk-series-1` … `-5`: legend mark, SVG fill, SVG stroke |
| `gk-btn-touch` | A button at least `--gk-target-min` (44px) in both directions, type unchanged |
| `gk-message-actions` | Actions on the right of a `.gk-message` |
| `gk-message-sm` | A small message (since 1.95.0): denser than `.gk-message-compact` and with no margin of its own; the tone comes from `-info` `-success` `-warning` `-error` |
| `gk-announce` | A live region that is always on the page (since 1.93.0): empty it has no box but stays in the accessibility tree; `GK.announce()` writes its words |
| `gk-timeline` `gk-timeline-step` `gk-timeline-marker` `gk-timeline-body` `gk-timeline-head` `gk-timeline-title` `gk-timeline-state` `gk-timeline-time` `gk-timeline-chips` `gk-timeline-detail` `gk-timeline-error` | A log of steps (since 1.96.0): `<ol role="list">`, one `<li>` per step, a symbol and a word for the state, title, time, chips, a detail and an error block |
| `gk-timeline-ok` `gk-timeline-failed` `gk-timeline-running` `gk-timeline-waiting` `gk-timeline-skipped` | The state of a step, on its `<li>`: check, cross, a turning ring (still under reduced motion), an empty ring, a dash |
| `gk-video-frame` `gk-video-frame-media` `gk-video-frame-caption` | A 9:16 frame for a video or picture (since 1.96.0): never wider than the column or taller than 75% of the window, the picture shown whole, no autoplay |
| `gk-video-frame-safe` `gk-video-frame-busy` `gk-video-frame-error` | On the frame: guide lines for the zones a platform covers (top 10%, bottom 20%, no click caught) / nothing to show yet / it broke — the last two reuse `.gk-empty` |
| `gk-sort-griff` `gk-sort-aktiv` | The handle the script puts in an entry (since 1.97.0) and the mark on a container that has handles (two entries or more) |
| `gk-sort-zieht` `gk-sort-frei` `gk-sort-platz` | The entry in the air (outline, shadow), the entry flying with the pointer (fixed), and the dashed gap it left |
| `gk-sort-meldungen` `gk-sort-ok` `gk-sort-fehler` | Under a sortable container: the wrapper of the two lines, "saved" for a moment, and the failure as a sentence beside a symbol |
| `gk-show-mobile` | Shown at 768px and below only — the counterpart of `gk-hide-mobile` |
| `gk-choice` `gk-choice-mark` `gk-choice-text` `gk-choice-title` `gk-choice-hint` | A radio or checkbox as an answer tile with a letter (Form `'choice'`) |

### Tabs

Two shapes ship, and they are not interchangeable. Pick by whether you want to
write the panels' titles as markup or have the nav built for you.

**Authored markup** — the shape the demo shows. You write both halves and pair
them with `data-tab`:

```html
<div class="gk-tabs">
    <div class="gk-tab-nav">
        <button class="gk-tab-btn gk-active" data-tab="overview">Overview</button>
        <button class="gk-tab-btn" data-tab="details">Details</button>
    </div>
    <div class="gk-tab-panel gk-active" data-tab="overview">…</div>
    <div class="gk-tab-panel" data-tab="details">…</div>
</div>
```

The `data-tab` values must match between a button and its panel, and exactly
one of each starts with `gk-active`.

**Generated nav** — you write only the panels, the buttons are built from them:

```html
<div data-gk-tabs>
    <div data-gk-tabpanel="overview" data-gk-tab-title="Overview">…</div>
    <div data-gk-tabpanel="details"  data-gk-tab-title="Details">…</div>
</div>
```

The first panel is the active one; the nav is inserted as the first child with
class `gk-tabs-nav`.

Since 1.69.0 both shapes get the full tablist structure from the library —
`role="tablist"`, `role="tab"` with `aria-selected` and `aria-controls`,
`role="tabpanel"` with `aria-labelledby`, and a roving tabindex so the set is
one tab stop with **Left / Right / Home / End** moving inside it. **Write none
of that yourself**; ids are supplied only where you have not.

**Tabs as links between pages** (since 1.99.0) — when each tab is a page of its own, write
links in a `<nav>` and mark the current page with `aria-current="page"`; it looks exactly like
`.gk-active`. No `.gk-tabs` around it (that would turn the links into a tablist), no script:

```html
<nav class="gk-tab-nav gk-tab-nav-fill" aria-label="Areas of this page">
    <a class="gk-tab-btn" href="/page/7/edit"><span class="material-icons gk-tab-icon" aria-hidden="true">edit</span><span>Edit</span></a>
    <a class="gk-tab-btn" href="/page/7/results" aria-current="page"><span class="material-icons gk-tab-icon" aria-hidden="true">bar_chart</span><span>Results</span></a>
</nav>
<nav class="gk-tab-nav gk-tab-nav-secondary" aria-label="Views">
    <a class="gk-tab-btn" href="…/signups">Sign-ups</a>
    <a class="gk-tab-btn" href="…/stats" aria-current="page">Statistics</a>
</nav>
```

- `.gk-tab-icon` — a symbol before the word (20px).
- `.gk-tab-nav-fill` — the main row of a page: 44px targets; up to 600px the tabs share the width,
  the symbol over the word, each as wide as its word needs.
- `.gk-tab-nav-secondary` — a second row under the first, one step quieter: no rule of its own,
  13px words, the chosen one in the text colour with its line. A third row is never a tab row —
  use a segmented button.
- The active tab (`.gk-active` too) is in `--gk-primary-text` and 600 since 1.99.0 (`--gk-primary`
  measured 4.47:1 on white); every tab has a visible focus ring.

**Karteireiter** (since 1.101.0) — the shape for new pages, ordered with a class; without it every
tab row keeps the line shape above. The tabs sit framed on their surface (`.gk-tab-flaeche`); the
chosen one grows into it — taller, bold, a 3px accent on top — the others lie grey behind it.

```html
<div class="gk-tabs">
    <div class="gk-tab-nav gk-tab-nav-karte">
        <button class="gk-tab-btn gk-active" data-tab="signup"><span class="material-icons gk-tab-icon" aria-hidden="true">edit_note</span><span>Sign-up</span></button>
        <button class="gk-tab-btn" data-tab="confirm"><span>Confirmation page</span><span class="gk-tab-badge">Customised</span></button>
        <button class="gk-tab-btn" data-tab="remind"><span>Reminders</span><span class="gk-tab-count">3</span></button>
    </div>
    <div class="gk-tab-panel gk-tab-flaeche gk-active" data-tab="signup">…</div>
    <div class="gk-tab-panel gk-tab-flaeche" data-tab="confirm">…</div>
    <div class="gk-tab-panel gk-tab-flaeche" data-tab="remind">…</div>
</div>
```

Links between pages take the same classes (`<nav class="gk-tab-nav gk-tab-nav-karte">` with
`aria-current="page"`, the content in one `<div class="gk-tab-flaeche">` after the nav).

| Level | Shape | Classes |
|---|---|---|
| Page tabs (the main row of a page or section) | Karteireiter, filled, accent | `gk-tab-nav gk-tab-nav-karte` + `gk-tab-flaeche` |
| Second row (inside the surface of the first) | quiet line tabs: no boxes, the chosen one carries a 3px accent line (1.102.0) | `gk-tab-nav gk-tab-nav-karte gk-tab-nav-secondary` |
| Tabs inside a window (modal, sheet) | the second-row shape — the window is the surface | `gk-tab-nav gk-tab-nav-karte gk-tab-nav-secondary`, no `gk-tab-flaeche` |
| A third level (period, view of a list) | never a tab row | `gk-segmented` |

- **Corners** (since 1.102.0): one token, `--gk-tab-radius` (4px — sharper than a button's 8px): the
  tab corners, the corners of the surface and the phone cells all read it, so they always match. Set it
  on an ancestor to change it for a region; never write a `border-radius` of your own on these classes.
- **Never mix** `gk-tab-nav-karte` with `gk-tab-nav-fill` or with local tab rules of your own
  (backgrounds, borders, a phone grid on `.gk-tab-btn`) — the shapes mix and the edge breaks.
- **Phone:** up to 600px a Karteireiter row is a grid, as many columns as fit; up to 480px exactly
  two. Every tab stays visible (nothing scrolls, nothing is cut), each cell is a framed 44px target,
  the symbol stands over the word, a badge or count under it; the cells of a row are equally tall,
  also with a badge or count (1.102.0). Write no phone grid of your own.
- **A row that does not fit its box** (since 1.102.0): the row is one line while it fits; when it is
  too wide for its box (a side column, a narrow card, a window), `GK.tabsFit` puts `gk-tab-nav-raster`
  on it and it becomes the same grid as on a phone — at any window width, measured, not guessed. Never
  set `gk-tab-nav-raster` by hand and never write `overflow-x: auto`, a scrolling row or a media query on
  `.gk-tab-nav-karte` for this. It covers link rows too; without the script the row stays a line.
- **Badge (`gk-tab-badge`) only for a deviation from the default** — "Customised", "Error". A badge
  that says "Default" on every tab is noise: a tab with nothing special carries no badge. A count
  (`gk-tab-count`) is a number of things behind the tab, not a status.
- **Error badge** (since 1.102.0): `<span class="gk-tab-badge gk-tab-badge-error">Error</span>` — the
  word stays, colour alone never says it. Contrast ≥ 4.5:1 light and dark (`ci/farben.js`). Write no
  `.gk-tab-badge.<system>-error` rule of your own.
- **Link tabs keep their place** (since 1.102.0): a click on a link tab (`<a class="gk-tab-btn">` in a
  `.gk-tab-nav`) notes where the row stands in the window; the page it lands on scrolls so the row
  stands there again, as if only the content below the tabs changed. Nothing to write. Not done for a
  `#` target, another origin, Ctrl/Shift/middle click, back/forward/reload, or a note older than 15 s.
  Switch it off with `data-gk-tab-scroll="off"` on the row or any ancestor.
- **Keyboard:** `.gk-tabs` rows get `role="tablist"`, `aria-selected` and a roving tabindex from the
  library; Left / Right / Home / End move inside ONE row — Up / Down in a side row (`aria-orientation`
  is set from the layout, so a phone, where a side row is horizontal again, gets Left / Right), and a
  disabled tab is stepped over (1.103.0). Link rows are ordinary links with
  `aria-current="page"` and need no script.
- **Contrast:** the edge `--gk-tab-kante` is a calm frame line, the middle between the card hairline
  (`--gk-outline-variant`) and the control edge (`--gk-outline`) — about 1.5–1.8:1 against its ground,
  like the edge of a card (1.103.1; do not harden it, a test fails on any other mix). The chosen tab is
  told by its stroke or fill (≥ 3:1) and its word (≥ 4.5:1), never by the edge or by colour alone:
  surface, accent and weight — measured in all six themes, light and both dark spellings
  (`ci/farben.js`).
- **Nesting:** a `.gk-tabs` inside a panel of another is fine since 1.101.0 — a click on an outer tab
  touches only its own row and panels; the inner choice stays. The inner row takes the second-row
  shape. `data-tab` keys may repeat between levels.
- **Steps on a phone:** `.gk-steps` draws a shorter line before each step up to 480px; three steps
  of the length "Changes · Approval · Publish" fit in 340px, and when a band wraps after all, no line
  hangs at the end or the start of a row.

**Tab variants** (since 1.103.0) — three choices, each a class on the row (`.gk-tab-nav`), and they
combine freely. One script serves every form; a row without any of these classes looks as before.
Put exactly one **style** class on the row, at most one **alignment**, any number of **options**.

| Choice | Class on `.gk-tab-nav` | Example |
|---|---|---|
| **Style** — card (1.101.0) | `gk-tab-nav-karte` + `gk-tab-flaeche` on the panels | `<div class="gk-tab-nav gk-tab-nav-karte">` |
| Style — line | `gk-tab-nav-linie`: words on a thin track, a 3px stroke under the chosen tab | `<div class="gk-tab-nav gk-tab-nav-linie">` |
| Style — pill | `gk-tab-nav-kapsel`: the row on a quiet track, the chosen tab filled | `<div class="gk-tab-nav gk-tab-nav-kapsel">` |
| Style — text | `gk-tab-nav-text`: only words, the chosen one bold with a thin line | `<div class="gk-tab-nav gk-tab-nav-text">` |
| **Alignment** — top | none (the default) | `<div class="gk-tab-nav gk-tab-nav-linie">` |
| Alignment — left | `gk-tab-nav-links`: a column beside the panels from 601px; below that the horizontal row | `<div class="gk-tab-nav gk-tab-nav-linie gk-tab-nav-links">` |
| Alignment — right | `gk-tab-nav-rechts`: the same, on the right (the stroke on the outer edge) | `<div class="gk-tab-nav gk-tab-nav-karte gk-tab-nav-rechts">` |
| Alignment — bottom | `gk-tab-nav-unten`: the row stands AFTER the panels in the markup | `<div class="gk-tab-nav gk-tab-nav-kapsel gk-tab-nav-unten">` |
| Alignment — fixed bar | `gk-tab-nav-unten gk-tab-nav-fest`: sticks to the lower edge of its box, symbol above the word, tabs share the width, keeps clear of a phone's home bar (`env(safe-area-inset-bottom)`); up to five tabs | `<div class="gk-tab-nav gk-tab-nav-linie gk-tab-nav-unten gk-tab-nav-fest">` |
| **Option** — size | `gk-tab-nav-sm` / `gk-tab-nav-lg` (the card style takes them from 601px up) | `<div class="gk-tab-nav gk-tab-nav-linie gk-tab-nav-lg">` |
| Option — full width | `gk-tab-nav-fluid`: the tabs share the row equally | `<nav class="gk-tab-nav gk-tab-nav-kapsel gk-tab-nav-fluid">` |
| Option — symbol above | `gk-tab-nav-icon-oben` | `<div class="gk-tab-nav gk-tab-nav-linie gk-tab-nav-icon-oben">` |
| Option — symbol only | `gk-tab-nav-nur-icon`: the word stays for a screen reader; **`aria-label` and `title` on every tab are required** | `<button class="gk-tab-btn" data-tab="a" aria-label="Overview" title="Overview"><span class="material-icons gk-tab-icon" aria-hidden="true">dashboard</span><span>Overview</span></button>` |
| Option — neutral | `gk-tab-nav-neutral`: the chosen tab in the text colour, not the main colour | `<div class="gk-tab-nav gk-tab-nav-kapsel gk-tab-nav-neutral">` |
| Option — disabled | `disabled` on a button or `aria-disabled="true"`: dimmed, no hand, no hover; arrow keys step over it, a click does nothing | `<button class="gk-tab-btn" data-tab="b" disabled>Reports</button>` |
| Option — count, label | `gk-tab-count`, `gk-tab-badge`, `gk-tab-badge-error` inside the tab | `<span class="gk-tab-count">3</span>` |

- **Colours and sizes are tokens on the row** — `--gk-tab-akzent` (the stroke or the fill),
  `--gk-tab-akzent-text` (the chosen word on the surface), `--gk-tab-akzent-auf` (the word on a fill),
  `--gk-tab-kante` (the track), `--gk-tab-pad-y` / `-pad-x` / `-font`, `--gk-tab-linie` (stroke width),
  and `--gk-tab-seitenbreite` (14rem, the widest a side column grows). Set them on the row itself — they
  do not pass into a row inside a panel. Write no colour or `border` of your own on `.gk-tab-btn`.
- **Light and dark** are measured (`ci/farben.js`, `ci/browser-reiter-varianten.js`): the chosen word
  ≥ 4.5:1, the stroke or fill of the chosen tab and the focus ring ≥ 3:1; the track is a calm frame line
  like a card edge. On a phone every tab of the line, pill and text styles
  is at least 44px high; a row too wide for the screen scrolls sideways (the card style becomes the grid).
- **Side rows** sit in the `.gk-tabs` as its direct child; the panels follow in the same wrapper.
  `aria-orientation="vertical"` and the Up / Down keys come from the layout, not from a class.
- **Combining rows** (a row inside a panel of another): each `.gk-tabs` answers for its own tabs,
  `data-tab` keys may repeat between levels, and each row's arrow keys stay inside it. Mixing styles
  is the point: card outside and line inside, a side column outside and a line row inside, a fixed bar
  around a card row, a pill row over a card row (all four in the demo, topic "Tabs").
- **Link tabs keep their place** (`<nav class="gk-tab-nav gk-tab-nav-linie">` with `<a class="gk-tab-btn"
  aria-current="page">`): the new styles take them too, and `GK.tabsScroll` keeps the row where it
  stood. Keep a row of links OUTSIDE any `.gk-tabs` wrapper — a link inside a panel of a wrapper would
  be taken for a tab.
- **Not supported:** the `gk-tab-nav-secondary` shape together with `gk-tab-nav-unten`; a
  `position: fixed` bar (`gk-tab-nav-fest` is sticky — it follows its box, so a page-wide bar is the
  page's own job).

### Accordion

```html
<div class="gk-accordion" data-gk-single>
    <div class="gk-accordion-item open">
        <button class="gk-accordion-trigger">
            <span>What is GridKit?</span>
            <span class="material-icons" aria-hidden="true">expand_more</span>
        </button>
        <div class="gk-accordion-content">
            <div class="gk-accordion-body">A zero-dependency PHP component library.</div>
        </div>
    </div>
    <!-- … more items … -->
</div>
```

- `data-gk-single` on the container: opening one item closes the others. Omit
  it and any number can be open at once.
- `open` on an item: it starts expanded.
- The trigger must be a `<button>`, and the body belongs inside
  `.gk-accordion-content` — that is the element the library shows and hides.

Since 1.71.0 the trigger carries `aria-expanded` and `aria-controls`, the panel
is a `region` named by its trigger, and a closed panel leaves the tab order —
so a link inside a shut section is not reachable, which it used to be.

⚠️ `.gk-accordion-content` is capped at `max-height: 500px`. Content taller
than that is clipped. For long sections, use a Modal instead.

### Gallery + Lightbox

```html
<div class="gk-gallery">
    <div class="gk-gallery-item" data-lightbox="/img/full-1.jpg" data-caption="Landscape" data-lazy>
        <img data-src="/img/thumb-1.jpg" alt="Landscape">
        <div class="gk-gallery-overlay"><span class="gk-gallery-caption">Landscape</span></div>
    </div>
</div>
```

- `data-lightbox` — the full-size image. Without it the tile is only a picture
  and nothing opens.
- `data-caption` — names the tile for a screen reader AND becomes the viewer's
  caption and the large image's `alt`. Worth setting.
- `data-lazy` — the picture loads when it nears the viewport. **With `data-lazy`
  the `<img>` carries `data-src`, not `src`.** Write `src` and it loads at once,
  which is the opposite of what you asked for.
- Container: `gk-gallery`, `gk-gallery-sm`, `gk-gallery-lg` (tile size) or
  `gk-gallery-masonry`.

Since 1.72.0 a tile is a real keyboard control (role, tab stop, **Enter** and
**Space** open it) and the viewer is a proper dialog: focus moves in, Tab stays
inside, **Escape** closes and focus returns to the tile it came from. **←/→**
page through the gallery.

```js
GK.lightbox.open(0);      // by index within the gallery last clicked
GK.lightbox.close();
GK.lightbox.init(root);   // for a gallery added to the page after load
```

Since 1.94.0 the viewer can show more than a caption: a second line
(`meta`), and a row with **Select this image** and **Close** when the entries it
was given can be picked (the image picker below). It is also open to your own
lists:

```js
GK.lightbox.show(
    [{ src: '/g/1.webp', caption: 'Fireplace', meta: 'Own image' }, { src: '/g/2.webp', caption: 'Road' }],
    0,            // start here
    button        // the element the focus returns to when it closes
);
```

An entry may carry `choose()` (picks it and returns the element that should have
the focus afterwards), `selected()`, `disabled`, `returnTo()` and
`chooseLabel`. With none of them it is the viewer it always was — pictures,
paging, Escape, the focus back — with one difference in layout: the caption stands
under the picture and wraps (it was a one-line pill over its lower edge), and the
picture takes the room the caption leaves instead of a fixed 85 % of the height.

### Image picker — ImagePicker

Choose **one** image out of many. A full row per image is fine for three and
unusable for thirty; this is a grid of 16:9 tiles (about 150 px, two per row on a
phone) that works like a radio group, shows the first two rows with a "Show all
30" button, and opens the library's viewer on the large version.

```php
<?php
\GridKit\ImagePicker::render([
    ['value' => 'a.webp', 'title' => 'Fireplace at dusk', 'thumb' => '/t/a.webp', 'full' => '/g/a.webp',
     'meta' => 'Own image', 'checked' => true],
    ['value' => 'b.webp', 'title' => 'Mountain road', 'thumb' => '/t/b.webp', 'full' => '/g/b.webp',
     'meta' => 'AI generated', 'badge' => 'AI', 'actions' => [['name' => 'remove', 'label' => 'Remove']]],
    ['value' => 'c.webp', 'title' => 'Not released yet', 'note' => 'Waiting for approval', 'disabled' => true],
], ['name' => 'cover', 'label' => 'Header image']);
```

`ImagePicker::render()` prints, `ImagePicker::html()` returns the same markup,
`ImagePicker::tileHtml($item, $opts)` returns one tile (what an AJAX answer would
carry). Put the picker in a `<fieldset>` with a `<legend>`, or pass `label`:
a group a reader cannot name is a list of unlabelled radios.

| Per image | |
|---|---|
| `value` | what the form submits — the radio's value (required) |
| `title` | one line on the tile, cut with an ellipsis; the whole of it is the tooltip and the radio's name |
| `thumb` | the **small** picture for the tile (omitted: a placeholder is drawn) |
| `full` | the **large** picture the viewer loads when it opens (omitted: no zoom button, nothing for the viewer) |
| `alt` | the viewer's caption and the large picture's `alt` (default: the title) |
| `meta` | where it comes from ("AI generated"): a second line in the viewer, the radio's description |
| `note` | a line under the title on the tile ("Waiting for approval"), also in the description |
| `badge` | a short word on the picture ("AI") — `meta` says it in full for a reader |
| `checked` / `disabled` | the state |
| `actions` | `[['name' => 'remove', 'label' => 'Remove', 'icon' => 'delete']]` — buttons in the caption row |
| `data` | `['x' => 'y']` — `data-x="y"` on the tile, for your own bookkeeping |

Options: `name` (required — the radios' group), `id` (prefix of every id), `label`
(names the group), `rows` (rows shown before "show all"; default `2`, `0` = never
fold), `status` (`false`: no "Selected: …" line), `class`, and `labels`
(`zoom`, `choose`, `more` with `{n}`, `less`, `selected` with `{name}`, `none`) —
by default the active language's words.

**The pictures are yours to size.** The library draws what it is given: give
`thumb` a small file (about 400 px on the long edge — a tile is 150 px wide) and
`full` the large one. A tile that loads the original makes thirty tiles thirty
originals.

Nothing is in the library that deletes. A caption button fires an event and
nothing else — the application decides, and asks "Really?":

```js
picker.addEventListener('gk:pickeraction', (e) => {
    // e.detail = { action: 'remove', value: 'b.webp', item: <the tile>, picker: <the container> }
    GK.confirm('Remove this image?', { danger: true }).then((ok) => {
        if (ok) removeOnTheServer(e.detail.value).then(() => GK.picker.remove(e.detail.picker, e.detail.value));
    });
});
```

Picking is the radio's own `change` event — it bubbles, and the form submits the
value, so a page that listened to the old list of radios needs nothing new.

```js
GK.picker.add(picker, { value: 'd.webp', title: 'Fresh upload', thumb: '…', full: '…' },
              { select: true, focus: true });   // picks it as a click would, one change event
GK.picker.remove(picker, 'd.webp');             // takes the tile out; the focus is yours to place
GK.picker.refresh(picker);                      // after you changed which radio is checked in script
GK.picker.init(root);                           // for a picker added to the page after load
GK.picker.tileHtml(item, { name, id, index });  // the same markup PHP writes
```

What the keyboard and a reader get, without being asked: native radios (Tab
enters the group once, the arrow keys move *and* pick, disabled tiles are
skipped); a 3 px focus ring; the check mark, a thicker ring and a bold title for
the picked tile — never colour alone; the zoom button of **one** tile (the one
with the focus, else the picked one) is in the tab order, the others are not, so
thirty tiles are not thirty extra tab stops — a caption action stays in the tab
order on every tile, because the focused tile is always the picked one and an
application will not let you remove that; the viewer traps Tab, closes on
Escape, pages with ← →, and gives the focus back to the zoom button of the tile it
was last showing (or to the radio, when a picture was picked from it). A tile out
of rows folds away unless it is picked or has the focus. The tile's picture has
`alt=""` on purpose: the title already names it, and an `alt` would say it twice.
Touch screens get finger-sized buttons (`pointer: coarse`); reduced motion stops
the ring's transition and the viewer's entrance.

### Tooltips

Three forms, from least to most work:

```html
<!-- 1. Any title attribute. GK.tip restyles it on first hover and moves the
        text to data-gk-tip so the browser's own popup stops competing.
        Opt out with data-gk-tip-off on the element or any ancestor. -->
<button title="Save the current draft">Save</button>

<!-- 2. CSS-only, no JavaScript involved. -->
<span data-gk-tooltip="Short explanation" data-gk-tooltip-pos="top">Hover me</span>

<!-- 3. Rich: real HTML in the popup. -->
<span data-gk-tooltip-rich="#vat-help">VAT</span>
<div id="vat-help">Reduced rate — <strong>10&nbsp;%</strong> for food and books.</div>
```

`data-gk-tooltip-pos` takes `top` (default), `bottom`, `left` or `right`.

For the rich form the target is a CSS selector, so `#id` is the usual choice —
and **give it an id**: since 1.73.0 the trigger is joined to it with
`aria-describedby`, which needs one. The trigger also becomes focusable if it
is not already, opens on **focus** as well as hover, and **Escape** dismisses
it — until then the rich tooltip existed for pointers and for nobody else.

⚠️ A tooltip is a hint, never the only place something is said. Anything a
person must have to complete the task belongs in the label or beneath the
field.

## Utility Classes (since v1.14.0)

Tailwind-style utilities so consumers never need inline `style="…"` for spacing,
layout, typography, or semantic colors. **Spacing scale: 0/1/2/3/4/5/6 = 0/4/8/12/16/20/24 px** (MD3 8-grid with half-steps).

| Group | Classes |
|---|---|
| Display | `gk-hidden` `gk-block` `gk-inline` `gk-inline-block` |
| Flex | `gk-flex` `gk-inline-flex` `gk-flex-col` `gk-flex-wrap` `gk-flex-1` `gk-flex-center` `gk-flex-between` |
| Items / Justify | `gk-items-{start,center,end,baseline}` `gk-justify-{start,center,end,between}` |
| Gap | `gk-gap-{xs,sm,md,lg,xl,2xl}` → 4/6/8/12/16/20 px |
| Margin | `gk-m-{0..6}` `gk-mt-{0..6}` `gk-mb-{0..6}` `gk-ml-{0..4,auto}` `gk-mr-{0..4,auto}` `gk-mx-auto` |
| Padding | `gk-p-{0..6}` `gk-px-{0..6}` `gk-py-{0..6}` |
| Font-Size | `gk-fs-{xs,sm,md,base,lg,xl,2xl}` → 11/12/13/14/16/18/20 px |
| Font-Weight | `gk-fw-{normal,medium,semibold,bold}` |
| Text-Align | `gk-text-{left,center,right}` |
| Text-Color | `gk-text-{primary,success,danger,warning,muted,on-surface}` |
| Background | `gk-bg-{surface,muted,primary-soft,success-soft,danger-soft,warning-soft}` |
| Border-Radius | `gk-rounded-{none,sm,md,lg,xl,full}` → 0/6/8/10/14/999 px |
| Width / Height | `gk-w-{full,auto}` `gk-h-{full,auto}` — plus `gk-w-{1..16}`, the same sixteenths the form grid uses (6.25 % a step, so `gk-w-8` is half and `gk-w-16` is full) |
| Spacer | `gk-spacer` (16 px) `gk-spacer-{sm,md,lg,xl}` → 8/20/24/32 px (note `-md` is 20, one step above the bare `gk-spacer`) of vertical air, for when a margin would collapse |
| Grid | `gk-grid` (grid, 16 px gap) with `gk-grid-2` or `gk-grid-4` — auto-fit columns of at least 280 px / 200 px, so they reflow on their own |
| Misc | `gk-clickable` `gk-not-clickable` `gk-disabled` (dimmed and inert) `gk-nowrap` `gk-overflow-{x,y}-auto` `gk-font-mono` `gk-no-decoration` `gk-truncate` `gk-break-word` |

```html
<!-- Don't: -->
<div style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--gk-text-muted)">…</div>

<!-- Do: -->
<div class="gk-flex-center gk-gap-md gk-fs-md gk-text-muted">…</div>
```
