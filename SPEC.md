# GridKit Framework – Spezifikation v1.0

## Überblick

GridKit ist ein schlankes PHP-Framework für einheitliche Tabellen und Formulare.
**Null externe Abhängigkeiten.** Kein jQuery, kein Bootstrap, kein Fomantic UI.

## Zielstruktur

```
gridkit/
├── autoload.php           # PSR-4 Autoloader
├── src/
│   ├── Table.php          # Listen/Tabellen-Klasse
│   ├── Form.php           # Formular-Klasse
│   └── Modal.php          # Leichtgewichtiges Modal
├── css/
│   ├── gridkit.css        # Core-Styles (alles in einem File)
│   └── themes/
│       └── default.css    # Default Theme (CSS Custom Properties)
└── js/
    └── gridkit.js         # Minimal: Modal, AJAX, Sort, Filter, Search, Pagination
```

## PHP API

### Table (Fluent Interface)

```php
use GridKit\Table;

$table = new Table('articles');
$table->query($db, "SELECT * FROM articles ORDER BY name")
    ->search(['article_number', 'name'])           // Volltextsuche über Spalten
    ->column('article_number', 'Artikelnr.', ['width' => '110px', 'sortable' => true])
    ->column('name', 'Bezeichnung', ['sortable' => true])
    ->column('unit', 'Einheit', ['width' => '80px'])
    ->column('net_price', 'Netto', ['format' => 'currency', 'align' => 'right'])
    ->column('tax_rate', 'MwSt', ['format' => 'percent'])
    ->column('is_active', 'Status', ['format' => 'label'])
    ->button('edit', ['icon' => 'pencil', 'modal' => 'edit_form', 'params' => ['id' => 'article_id']])
    ->button('delete', ['icon' => 'trash', 'class' => 'danger', 'modal' => 'delete_form', 'params' => ['id' => 'article_id']])
    ->modal('edit_form', 'Artikel bearbeiten', 'form/f_articles.php', ['size' => 'medium'])
    ->modal('delete_form', 'Artikel löschen', 'form/f_delete.php', ['size' => 'small'])
    ->newButton('Neuer Artikel', ['modal' => 'edit_form'])  // Button über der Tabelle
    ->paginate(25)
    ->render();
```

### Form (Fluent Interface)

```php
use GridKit\Form;

$form = new Form('article_form');
$form->action('save/process_article.php')
    ->ajax()                                        // AJAX-Submit, kein Page-Reload
    ->hidden('article_id', $data['article_id'] ?? '')
    ->row()
        ->field('article_number', 'Artikelnr.', 'text', ['required' => true, 'width' => 8])
        ->field('name', 'Bezeichnung', 'text', ['required' => true, 'width' => 8])
    ->endRow()
    ->field('description', 'Beschreibung', 'textarea', ['rows' => 3])
    ->row()
        ->field('unit', 'Einheit', 'select', ['options' => ['Stk' => 'Stück', 'h' => 'Stunde', 'psch' => 'Pauschal'], 'width' => 5])
        ->field('net_price', 'Netto-Preis', 'number', ['step' => '0.01', 'width' => 5])
        ->field('tax_rate', 'MwSt %', 'select', ['options' => ['20' => '20%', '10' => '10%', '0' => '0%'], 'width' => 6])
    ->endRow()
    ->field('is_active', 'Aktiv', 'toggle')
    ->submit('Speichern')
    ->render();
```

#### Form Density

Add `gk-form-compact` to a form or wrapper for reduced spacing. All elements (inputs, selects, toggles, checkboxes) scale proportionally.

```html
<!-- Compact form -->
<form class="gk-form-compact">
  <div class="gk-field">
    <label class="gk-label-text">Name</label>
    <input type="text" class="gk-input">
  </div>
</form>
```

| Property | Normal | Compact |
|----------|--------|---------|
| Input height | 44px | 34px |
| Toggle | 48×28 | 38×22 |
| Checkbox | 20×20 | 16×16 |
| Field margin | 20px | 10px |

### Zusammenspiel Table ↔ Form

1. Table rendert Button mit `data-modal="edit_form"` und `data-params='{"id":123}'`
2. gridkit.js fängt Click ab, öffnet Modal, lädt Form per AJAX (POST mit params)
3. Form rendert sich, User füllt aus, klickt Submit
4. Form submittet per AJAX an action-URL
5. Response: `{"ok": true}` → Modal schließt, Table refresht sich per AJAX
6. Response: `{"ok": false, "errors": {...}}` → Fehler werden am Feld angezeigt

## Built-in Formatter

Table-Spalten mit `'format'` Option werden automatisch formatiert:

| Format | Darstellung | Beispiel |
|--------|------------|---------|
| `currency` | Rechtsbündig, `1.234,56 €` | `['format' => 'currency']` |
| `percent` | `20 %` | `['format' => 'percent']` |
| `date` | `13.02.2026` | `['format' => 'date']` |
| `datetime` | `13.02.2026 08:30` | `['format' => 'datetime']` |
| `boolean` | Grüner Haken / Grauer Strich | `['format' => 'boolean']` |
| `label` | Farbiges Label (auto-mapping) | `['format' => 'label']` |
| `email` | Klickbarer mailto-Link | `['format' => 'email']` |

### Label-Mapping (automatisch)

Werte werden automatisch auf Farben gemappt:
- **Grün:** aktiv, bezahlt, paid, ja, yes, 1, true, gesendet, delivered
- **Orange:** offen, pending, entwurf, draft, warnung
- **Rot:** storniert, cancelled, überfällig, overdue, fehler, error
- **Grau:** inaktiv, 0, false, nein, no

Eigenes Mapping möglich: `['format' => 'label', 'labels' => ['custom' => 'blue']]`

## CSS-Konzept

### gridkit.css – Core (ein File, alles drin)

Enthält:
- Minimaler Reset (nur für GridKit-Elemente, kein globaler Reset)
- Alle Komponenten: `.gk-table`, `.gk-form`, `.gk-modal`, `.gk-btn`, `.gk-label`, `.gk-field`
- Responsive (Mobile-first)
- Alle Styles in `.gk-` Namespace (kein Konflikt mit bestehendem CSS)

### CSS Custom Properties für Theming

```css
.gk-root {
    /* Farben */
    --gk-primary: #2563eb;
    --gk-primary-hover: #1d4ed8;
    --gk-success: #16a34a;
    --gk-warning: #f59e0b;
    --gk-danger: #dc2626;
    --gk-info: #0ea5e9;
    
    /* Oberfläche */
    --gk-bg: #ffffff;
    --gk-bg-subtle: #f9fafb;
    --gk-bg-hover: #f3f4f6;
    --gk-border: #e5e7eb;
    --gk-text: #1f2937;
    --gk-text-muted: #6b7280;
    
    /* Typografie */
    --gk-font: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    --gk-font-size: 14px;
    --gk-font-size-sm: 12px;
    
    /* Spacing & Radius */
    --gk-radius: 6px;
    --gk-radius-sm: 4px;
    --gk-spacing: 12px;
    
    /* Schatten */
    --gk-shadow: 0 1px 3px rgba(0,0,0,0.1);
    --gk-shadow-lg: 0 10px 25px rgba(0,0,0,0.15);
}
```

Theme wechseln = `themes/dark.css` einbinden, das nur die Variables überschreibt.

## Feldtypen Form

Nur was wir brauchen:
- `text`, `number`, `email`, `tel`, `url`, `password`
- `textarea`
- `select` (mit options-Array)
- `date`, `time`, `datetime`
- `toggle` (on/off Switch)
- `hidden`
- `checkbox`, `radio`
- `file`

**KEIN** CKEditor, kein Color Picker, kein Slider, kein Accordion, kein Tab-System.
Das kann bei Bedarf SPÄTER dazu.

## JavaScript (gridkit.js)

Vanilla JS, kein jQuery. Features:

1. **Modal:** Öffnen/Schließen, AJAX Content laden, Keyboard (ESC)
2. **Table AJAX:** Refresh nach Form-Submit, Pagination per AJAX
3. **Search:** Debounced Live-Search (sendet Query an Server)
4. **Sort:** Click auf Header → Reload mit Sort-Params
5. **Form Submit:** AJAX POST, Error-Handling, Loading-State
6. **Confirm-Dialog:** Für Delete-Buttons

Geschätzt: ~300-400 Zeilen JS.

## Constraints

- PHP 8.3 kompatibel
- Namespace: `GridKit\`
- Alle CSS-Klassen: `gk-` Prefix
- Alle data-Attribute: `data-gk-`
- Keine globalen CSS-Resets
- MySQLi als DB-Verbindung (wie bestehendes Panel)
- Prepared Statements für Search/Filter
- HTML: `htmlspecialchars()` überall

## Nicht im Scope v1.0

- Export (CSV/Excel/PDF)
- Bulk-Actions (Checkboxen + Massenaktionen)
- Drag & Drop Sortierung
- Inline-Editing
- File-Upload im Form

Diese Features können in v1.1+ dazu, aber v1.0 muss FUNKTIONIEREN, schlank und fehlerfrei.

## Tooltip

CSS-only tooltips and rich HTML tooltips.

### CSS-only Tooltip

```html
<!-- Simple tooltip (default: top) -->
<span data-gk-tooltip="Hello!">Hover me</span>

<!-- Position variants -->
<span data-gk-tooltip="Info" data-gk-tooltip-pos="bottom">Bottom</span>
<span data-gk-tooltip="Info" data-gk-tooltip-pos="left">Left</span>
<span data-gk-tooltip="Info" data-gk-tooltip-pos="right">Right</span>

<!-- Multiline (wrapping) -->
<span data-gk-tooltip="Longer text that wraps" data-gk-tooltip-wrap>Hover</span>
```

- Pure CSS, no JavaScript needed
- 300ms hover delay (via transition-delay)
- Uses `::after` pseudo-element
- Dark theme compatible via CSS custom properties

### Rich Tooltip (HTML content)

```html
<span data-gk-tooltip-rich="#tipId">Hover for details</span>
<div id="tipId">
    <strong>Title</strong>
    <p>Any HTML content, links, etc.</p>
</div>
```

- JavaScript-powered (GK.tooltip.init)
- Viewport-aware positioning (flips if clipped)
- Stays open when hovering the tooltip itself
- Supports links, images, interactive content

## Six rules for lists

Since 1.91.0. Each rule names the building block that implements it; the full
text, with the measured row it was drawn from, is in `GRIDKIT_SKILL.md`.

| # | Rule | Building block |
|---|------|----------------|
| 1 | One state, one sign — a switch needs no label beside it saying the same | `->groupBy()` / `.gk-table-group`; `.gk-toggle` alone, in a `.gk-setting` row in the sheet |
| 2 | Read in the row, change in the sheet — a click anywhere on the row opens it | `->rowLink()` / `tr.gk-row-link` + `.gk-row-target`; `.gk-sheet` |
| 3 | Who: one cell, two lines — avatar, name, address underneath | `.gk-cell-who` with `.gk-avatar` + `.gk-cell-sub` (`'sub' => 'email'`) |
| 4 | Colour only for what needs acting on | `.gk-label-red` for the one count that needs attention |
| 5 | Group instead of scrolling — at most six columns at 1280px | `.gk-table-group`; `tr.gk-table-more`; `.gk-col-p2` … `-p5` (`'priority'`) |
| 6 | Running text is never bold and never narrow | `.gk-table` cells at weight 400; `.gk-cell-sub` |

The row: 64px in the redesign it came from, never under 44px, the whole row the
click target — since 1.92.0 the class `.gk-table-list` (`Table->size('list')`).

## Row link (`tr.gk-row-link`)

A row whose whole surface opens a page or a side sheet.

```php
->rowLink('/users/{id}')                    // a page
->rowLink(['sheet' => 'user-sheet'])        // a side sheet; 'url', 'params', 'column' optional
```

- The `<tr>` gets `gk-row-link` and no role and no tabindex — it stays a row.
- Its main cell (the first column, or `'column'`) carries ONE control,
  `<a class="gk-row-target" href>` or `<button type="button" class="gk-row-target"
  data-gk-sheet data-gk-params aria-haspopup="dialog">`, named by the cell's value.
- gridkit.js forwards a click anywhere else in the row to that control, with its
  button and modifier keys. Not forwarded: clicks on a control of the row's own
  (links, buttons, fields, labels), in the checkbox or action column, and the end
  of a text selection.
- At least 44px tall; a chevron in the last cell; with `:has()` the focus ring
  goes round the row; in card mode (`.gk-table-mobile-card`) round the card.
- A row whose main cell shows nothing, or whose target the allow list refuses,
  stays a plain row. `'format' => 'html'` and `'email'` are refused for the main
  cell, and its own `href` is dropped — each with a warning.
- The client-side rebuild of a `setData()` table writes the same row, byte for byte.

## Side sheet (`.gk-sheet`)

```html
<div class="gk-sheet" id="user-sheet" hidden>
  <div class="gk-sheet-header"><h2 class="gk-sheet-title">User</h2>
    <button type="button" class="gk-sheet-close">&times;</button></div>
  <div class="gk-sheet-body">…</div>
  <div class="gk-sheet-footer">…</div>
</div>
```

```javascript
GK.sheet.open('user-sheet', { returnFocus, focus, params, url, title });  // → the sheet or null
GK.sheet.close();
```

| | Wide screen (≥ 769px) | Phone (≤ 768px) |
|---|---|---|
| Position | docked right, `--gk-sheet-width` (440px), below a fixed/sticky header | full screen |
| Layer | part of the page (z-index 150): under the header, its user menu and every dropdown | over the page and the header (1200), under modals |
| Dialog | non-modal: no `aria-modal`, no focus trap, page scrolls | modal: `aria-modal="true"`, focus trap, page held still |

- `role="dialog"` and `aria-labelledby` (from `.gk-sheet-title`) are filled in
  where missing; a bare `&times;` close button is named from `js.close`.
- Focus moves to `.gk-sheet-title` on open (tabindex -1, never in the tab
  order); Escape closes it while the focus is inside (a widget
  that handled Escape keeps it; a confirm or modal opened from the sheet answers
  first); focus returns to the opener on close.
- One open at a time. `gk:sheetopen` and `gk:sheetclose` fire on the sheet.
- Hidden is the `hidden` attribute; the scroll lock on a phone is CSS keyed to it.
- Without JavaScript: rendered without `hidden` it is a docked panel.
- Dark mode: a role colour (`--gk-surface-container`). Reduced motion: no slide-in.

## Summary row (`tr.gk-table-more`)

One row standing for many: a full-width toggle that shows and hides whatever its
`aria-controls` names, usually a `<tbody hidden>` right after it.

```html
<tr class="gk-table-more"><td colspan="3">
  <button type="button" class="gk-table-more-toggle" aria-expanded="false" aria-controls="profiles">
    <span class="gk-table-more-name">13 chart profiles</span>
    <span class="gk-cell-sub">Created from charts — they cannot sign in.</span>
  </button>
</td></tr>
<tbody id="profiles" hidden>…</tbody>
```

- `aria-expanded` and the `hidden` attribute move together; the label does not
  change — the chevron shows the state to the eye, `aria-expanded` to a screen
  reader.
- At least 44px; hidden rows stay hidden in card mode.

## Blocks for an admin without its own CSS (1.92.0)

Each block replaces rules an admin built with GridKit had to write itself
(Vespera: about 465 rules, around 100 of them overriding GridKit). Colours are
roles only; light and dark follow from them.

| Block | Markup | PHP |
|---|---|---|
| Content area | `<main class="gk-main">` — padding 24/28/48px, 16px on a phone, max `--gk-main-max` (1680px) | — |
| Hidden | every `gk-` element with `[hidden]` is `display: none`, unless an inline style sets a display | — |
| Line beside the title | `.gk-header-title > h1 + .gk-header-meta` | — |
| List | `.gk-table-wrap.gk-table-list` — 64px rows, 15/13px, size container | `->size('list')` |
| Columns that give way | `.gk-col-p2` … `.gk-col-p5` on th and td; gone below 560/720/900/1040px of list width, its border included | `'priority' => 2…5` |
| Who cell | `.gk-cell-who > .gk-avatar + .gk-cell-who-text > (name, .gk-label, .gk-cell-sub.gk-cell-sub-wrap)` | — |
| Avatar colours | `.gk-avatar-tone-1` … `-5` | — |
| Status dot | `.gk-dot` + `.gk-dot-success` `-warning` `-danger` `-primary` `-muted`; `.gk-dot-outline` (empty ring), `.gk-dot-pulse` (running, still under reduced motion) | — |
| Setting | `.gk-setting > .gk-setting-text > (.gk-setting-title, .gk-setting-hint)` + `.gk-toggle.gk-setting-control`; `.gk-setting-danger`; never shrinks below its content in a scrolling column | `'toggle'` with `'hint'` (`'danger'`) |
| Read-only field | `.gk-field-static > (-label, -value, .gk-field-hint)` | — |
| Compact figures | `.gk-stat-tiles > .gk-stat-tile > (-value, -label, -sub)`; `.gk-stat-tile-warning`, `.gk-stat-tile-danger`; also `<button>`/`<a>`, or a `<dl>` (word as `<dt>` first, figure as `<dd>` — still on top) | `StatCards->compact()` |
| Sheet sub-line | `.gk-sheet-header > .gk-sheet-meta` | — |
| Chart colours | `--gk-series-1` … `-5`; `.gk-swatch .gk-swatch-1` … `-5`, `.gk-series-fill-1` … `-5`, `.gk-series-stroke-1` … `-5` | — |
| Touch button | `.gk-btn.gk-btn-touch` — min `--gk-target-min` (44px) both ways | — |
| Message actions | `.gk-message > .gk-message-actions`; an outlined button in it stands on `--gk-surface` | — |
| Phone only | `.gk-show-mobile` | — |
| Answer tile | `label.gk-choice > input + .gk-choice-mark + .gk-choice-text > (-title, -hint)` | `'choice'` (`'multiple'`) |

- `.gk-table-list` is the only `.gk-table-wrap` that is a size container; a list
  never turns into cards and never scrolls sideways (`->mobile()` on it warns).
- `'priority'` outside 2…5 (1 = never) warns; the column then always shows.
  Server and client rebuild write the same class.
- The sidebar writes `data-gk-sidebar-action="toggle|close|collapse"`, no
  `onclick`; gridkit.js delegates to `GK.sidebar.*`, which stay the API.
- `.gk-sheet-close` is `--gk-target-min` wide and tall, docked and on a phone.
- A `'choice'` with `'multiple'` cannot be required in the browser: GridKit warns
  and draws no star.

## Announcement (`.gk-announce`, 1.93.0)

A live region that exists before it speaks. Screen readers read a change inside
a live region that is already in the accessibility tree; one that is shown and
filled in the same step is often not read at all.

| Part | Contract |
|---|---|
| Markup | `<div class="gk-announce" role="status" aria-live="polite" aria-atomic="true"></div>` — usually also `.gk-message` (and `-compact`); on the page from the start, empty, never `hidden` |
| Empty | `.gk-announce:empty` is clipped like `.gk-sr-only` (position absolute, 1px, `clip-path: inset(50%)`, no border or padding) — in the accessibility tree, no box, no gap. Never `display: none` or `visibility: hidden` |
| Script | `GK.announce(target, text, tone)` — target an element, id or selector; text set as `textContent`; tone `info`/`success`/`warning`/`error` (default `info`) toggles `.gk-message-*` on a `.gk-message`; `''` empties it; the same text again empties the region and writes the text back after 150 ms, so it is read again; returns the element or `null`. `GK.melde` is the same function |
| Repair | `GK.announce()` and `GK.init()` add a missing `role="status"`, `aria-live` (`assertive` for `role="alert"`) and `aria-atomic="true"`, add the class, and take `hidden` off (on init only from an empty region) |

## Block index (`css/blocks.json`, 1.93.0)

`{"about": "…", "blocks": {"<key>": {"name": "…", "changed"?: "x.y.z", "classes": {"gk-…": "x.y.z"}}}}`

- Every class in `css/gridkit.css` and `css/themes.css` (comments stripped) is in
  exactly one block, and every class listed is in a stylesheet.
- A class's version is the release that first shipped it; a block's "since" is
  its oldest class, its effective "changed" the newer of `changed` and its newest
  class. No version is newer than `VERSION`.
- The demo marks a block "New in x.y" / "Changed in x.y" when that minor is one
  of the two newest in `CHANGELOG.md` (`.gk-label-green` / `.gk-label-blue`), and
  shows those two minors' entry headings at the top — read from the changelog.
