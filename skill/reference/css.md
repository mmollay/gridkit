# GridKit 1.93.1 — CSS

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
| `gk-tabs-nav` `gk-tab` | Tabs, generated nav (state class: `gk-tab-active`) |
| `gk-accordion` `gk-accordion-item` `gk-accordion-trigger` `gk-accordion-content` `gk-accordion-body` | Accordion (state class: `open` on the item) |
| `gk-gallery` `gk-gallery-item` `gk-gallery-overlay` | Image gallery |
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
| `gk-announce` | A live region that is always on the page (since 1.93.0): empty it has no box but stays in the accessibility tree; `GK.announce()` writes its words |
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
