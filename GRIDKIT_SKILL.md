# GRIDKit – Agent Skill

> **Version:** 1.9.0 | **License:** MIT | **Repository:** https://github.com/mmollay/gridkit
> **Demo:** https://gridkit.ssi.at | **Source:** `/home/pawbot/projects/gridkit/` (= `/home/develop/gridkit/`)

## Purpose

You are building or maintaining a web application using **GRIDKit**, a lightweight PHP component framework for admin dashboards. This skill is the authoritative reference for correct GRIDKit usage.

## Architecture

- **Stack:** PHP 8.2+, Vanilla JS, CSS (Material Design 3)
- **Zero Dependencies:** 1 CSS file + 1 JS file, no build process
- **Namespace:** `GridKit\` | CSS prefix: `gk-` | Data attributes: `data-gk-`

## CRITICAL: Change Workflow

**NEVER modify GRIDKit files inside consuming projects.** Always change at the source first.

```
1. Edit source:  /home/pawbot/projects/gridkit/  (= /home/develop/gridkit/)
2. Bump version: echo '1.4.x' > /home/pawbot/projects/gridkit/VERSION
3. Update:       /home/develop/ssi-core/public/gridkit/VERSION  (same if hardlinked)
4. Update CHANGELOG.md
5. Sync dashboard if needed (see Sync section)
```

## Available Components

| Component | Class | Purpose |
|-----------|-------|---------|
| Table | `GridKit\Table` | Data tables with search, sort, pagination |
| Form | `GridKit\Form` | Grid-based forms (16-column), all field types, AJAX submit |
| Header | `GridKit\Header` | Fixed header with breadcrumb, user menu |
| Sidebar | `GridKit\Sidebar` | Navigation with groups, badges, collapse |
| Modal | `GridKit\Modal` | Dialog overlays |
| Button | `GridKit\Button` | Filled/Outlined/Text/Tonal, icons, sizes |
| Auth | `GridKit\Auth` | Session auth, bcrypt, remember-me |
| Theme | `GridKit\Theme` | 6 themes (indigo/ocean/forest/rose/amber/slate), light/dark |
| Layout | `GridKit\Layout` | Layout modes (sidebar-first, header-first) |
| StatCards | `GridKit\StatCards` | KPI cards with icon, color, format |
| FilterChips | `GridKit\FilterChips` | URL-based filter chip buttons |
| YearFilter | `GridKit\YearFilter` | Year navigation filter |
| TableHeader | `GridKit\TableHeader` | **Unified filter/search bar above tables — Status / Toolbar / Advanced (since v1.10.0)** |
| Lang | `GridKit\Lang` | i18n / multilingual support |
| liveTable (JS) | `GK.liveTable` | AJAX tables (search/filter/sort/pagination live, no reload) |
| BelegModal | `GridKit\BelegModal` | PDF / document preview modal with iframe + mobile fallback (since v1.15.0) |
| ActionGroup | `GridKit\ActionGroup` | Container für Action-Buttons in Tabellen-Spalten (since v1.16.0) |

## Page Skeleton (SSI Panel context)

In SSI Panel, GRIDKit is loaded via the `layouts/panel` layout. Individual views just use components:

```php
<?php
$this->layout('layouts/panel');
use GridKit\Table;
use GridKit\Form;
use GridKit\StatCards;
use GridKit\FilterChips;
use GridKit\Button;
?>
<?php $this->start('content') ?>
<!-- Your components here -->
<?php $this->end() ?>
```

## Component Reference

### Table

```php
// Static (client-side search/sort/pagination — for small datasets)
(new Table('my-table'))
    ->setData($rows)                          // array of assoc arrays
    ->search(['name', 'email'])               // plain-text column keys only!
    ->toolbarHtml('<div class="gk-toolbar-spacer"></div>' . $addBtn)
    ->column('name',   'Name',   ['sortable' => true])
    ->column('email',  'Email',  ['sortable' => true])
    ->column('status', 'Status', ['format' => 'html'])  // HTML column: not searched
    ->button('edit',   ['icon' => 'edit',   'params' => ['id' => 'id']])
    ->button('delete', ['icon' => 'delete', 'params' => ['id' => 'id'], 'color' => 'danger'])
    ->paginate(25)
    ->render();

// Server-side (DB query)
(new Table('users'))
    ->query($db, "SELECT id, name, email, role FROM users ORDER BY name")
    ->search(['name', 'email'])
    ->column('name',  'Name',  ['sortable' => true])
    ->column('email', 'Email', ['sortable' => true])
    ->column('role',  'Role',  ['format' => 'label'])
    ->button('edit', ['icon' => 'edit', 'params' => ['id' => 'id']])
    ->paginate(25)
    ->render();
```

**⚠️ Search rule:** `search()` searches the column keys you name. If a column contains HTML (badges, links), use a separate plain-text key for search and a `_display` key for rendering. Never put HTML in searchable columns.

**Column formats:** `currency`, `percent`, `date`, `datetime`, `boolean`, `label`, `html`, `email`

**Button colors:** `danger`, `success`, `warning`, `primary` (default: neutral)

**`showIf`:** `->button('preview', ['icon' => 'open_in_new', 'params' => ['url' => 'url'], 'showIf' => 'has_preview'])`
— button only renders if the row's `has_preview` value is truthy.

### Button

```php
// Static render (returns HTML string)
Button::render('Label', [
    'variant' => 'filled',    // filled | outlined | tonal | text
    'color'   => 'primary',   // primary | success | danger | warning | neutral
    'icon'    => 'add',       // Material Icon name
    'href'    => '/path',     // renders as <a>
    'onclick' => 'jsCode()',
    'size'    => 'sm',        // sm | md (default) | lg
]);
```

### FilterChips

```php
(new FilterChips('filter-id', 'status'))   // 2nd param = GET param name
    ->baseUrl('/my-page')
    ->chip('',      'Alle (24)')           // value='' = "All" chip (no GET param)
    ->chip('active', 'Aktiv (18)')
    ->chip('won',    'Gewonnen', ['color' => 'success'])
    ->chip('lost',   'Verloren', ['color' => 'danger'])
    ->preserve(['year'])                   // keep other GET params on click
    ->render();
```

Active chip is auto-detected from `$_GET`. Color options: `success`, `danger`, `warning`, `primary`.

### YearFilter

```php
$yf = new YearFilter('year-filter', 'year');  // 2nd param = GET param name
$yf->baseUrl('/my-page')
   ->range(2022, (int)date('Y'))              // newest first
   ->preserve(['status'])
   ->render();

$currentYear = $yf->current();  // int — use for DB queries
```

### TableHeader (since v1.10.0) — **Required for every table page**

The single source of truth for filter/search bars above tables. Three fixed sections in this exact order:

1. **Status row** (full-width, typically `FilterChips` like „All / Open / Paid")
2. **Toolbar** (search + filter dropdowns inline, optional reset button)
3. **Advanced** (collapsible `<details>` for date / amount / detail filters)

```php
TableHeader::make('exp')
    ->status(fn() => $statusChips->render())                  // closure
    ->search('q', $q, 'Search…', ['live' => 'exp-live'])      // built-in
    ->filter(fn() => $yearFilter->render())                    // closure
    ->filter('<select class="gk-filter">…</select>')           // raw HTML
    ->advanced(fn() => renderDateRange(), 'Date & amount')     // optional collapsible
    ->reset('/faktura/expenses')                               // optional reset btn
    ->render();
```

API:
- `make($id)` static factory
- `status(\Closure $renderer)`: top row, full width
- `search(string $name, string $value = '', string $placeholder = '…', array $opts = ['live' => '…', 'id' => '…'])`
- `filter($contentOrClosure)`: any number of toolbar slots — Closure (echo'd) or raw HTML string
- `advanced(\Closure $renderer, string $summary = 'Erweiterte Filter', bool $open = false)`
- `reset(string $baseUrl, string $label = 'Filter zurücksetzen')`

CSS classes (all auto-applied): `gk-tableheader`, `gk-tableheader-status`, `gk-tableheader-toolbar`, `gk-tableheader-advanced`, `gk-tableheader-spacer`.

**Do NOT** build your own filter row with raw `gk-toolbar` / `gk-toolbar-stacked` if `TableHeader` fits — every table page must use this for visual consistency.

### StatCards

```php
(new StatCards('stats-id'))
    ->card('Umsatz',   12450.80, ['format' => 'currency', 'icon' => 'euro',    'color' => 'primary'])
    ->card('Benutzer', 1284,     ['format' => 'number',   'icon' => 'people',  'color' => 'success'])
    ->card('Fehler',   3,        ['format' => 'number',   'icon' => 'error',   'color' => 'danger', 'highlight' => true])
    ->card('Quote',    78,       ['format' => 'percent',  'icon' => 'speed',   'color' => 'warning'])
    ->card('Zu Details', '/url', ['icon' => 'arrow_forward', 'href' => '/url'])  // clickable
    ->render();
```

**Colors:** `primary`, `success`, `danger`, `warning`, `info`
**Formats:** `currency` (1.234,56 €), `number` (1.234), `percent` (78 %)

### Modal

```php
// In layout (panel.php does this automatically):
<?php Modal::container(); ?>

// JS API — dynamic modal:
GK.modal.open('Titel', '<p>Beliebiges HTML</p>');
GK.modal.close();

// Static inline modal (for complex content):
<div class="gk-modal-overlay" id="my-modal" style="display:none;">
    <div class="gk-modal gk-modal-small">   <!-- or gk-modal-large -->
        <div class="gk-modal-header">
            <h3 class="gk-modal-title">Titel</h3>
            <button class="gk-modal-close"
                onclick="document.getElementById('my-modal').style.display='none'">&times;</button>
        </div>
        <div class="gk-modal-body">Inhalt</div>
    </div>
</div>
```

### Form (AJAX)

```php
(new Form('user-form'))
    ->action('/api/save-user')
    ->method('POST')
    ->row()
        ->field('first_name', 'Vorname', 'text',  ['width' => 8, 'required' => true])
        ->field('last_name',  'Nachname', 'text', ['width' => 8, 'required' => true])
    ->endRow()
    ->field('email', 'E-Mail', 'email', ['width' => 16])
    ->field('role',  'Rolle',  'select', ['width' => 8, 'options' => ['admin' => 'Admin', 'user' => 'User']])
    ->field('active', 'Aktiv', 'toggle')
    ->submit('Speichern')
    ->render();
```

**Field types:** `text`, `textarea`, `select`, `number`, `date`, `time`, `email`, `tel`, `url`, `toggle`, `checkbox`, `radio`, `file`, `hidden`, `richtext`, `searchable-select`

**Form Density:** Add `gk-form-compact` class to a `<form>` or wrapper `<div>` for compact forms. All elements scale down proportionally:

| Element | Normal | Compact |
|---------|--------|---------|
| Input height | 44px | 34px |
| Input padding | 10px 14px | 6px 10px |
| Input font | 14px | 13px |
| Field margin | 20px | 10px |
| Label size | 12px | 11px |
| Toggle | 48×28px | 38×22px |
| Checkbox | 20×20px | 16×16px |
| Select display | 44px | 34px |

```html
<!-- Normal -->
<form>...</form>

<!-- Compact -->
<form class="gk-form-compact">...</form>

<!-- As wrapper around multiple cards -->
<div class="gk-form-compact">
  <div class="gk-card">...</div>
  <div class="gk-card">...</div>
</div>
```

**Form endpoint must return JSON:**
```php
echo json_encode(['ok' => true]);                          // success
echo json_encode(['ok' => true, 'message' => 'Saved!']);  // with toast
echo json_encode(['ok' => false, 'errors' => ['email' => 'Already exists']]);  // validation
```

### Theme

```php
Theme::set('indigo', 'light');  // themes: indigo, ocean, forest, rose, amber, slate
Theme::switcher();               // renders theme-switcher UI
```

### Lang

```php
Lang::set('de');     // set locale (default: de)
Lang::jsConfig();    // MUST be output in <head> before gridkit.js — sets window.GK_LANG
```

## JavaScript API

```javascript
// Toast notifications (use these exact forms!)
GK.toast.success('Gespeichert!');
GK.toast.error('Fehler aufgetreten!');
GK.toast.warning('Achtung!');
GK.toast.info('Information.');

// Dynamic modal
GK.modal.open('Titel', '<p>HTML-Inhalt</p>');
GK.modal.close();

// Table refresh (after save/delete in server-side mode)
GK.table.refresh('table-id');
```

### Live Tables (`GK.liveTable`) — seit 1.9.0

AJAX-gefilterte Tabellen: Search + Filter + Sort + Pagination ohne Full-Page-Reload.
Cursor bleibt beim Tippen, URL wird via `history.replaceState` synchron gehalten.

```html
<!-- Inputs: beliebig ausserhalb des Containers -->
<input data-gk-live-input="my-tbl" name="q" placeholder="Suche">
<select data-gk-live-input="my-tbl" name="status">...</select>

<!-- Container: wird AJAX-getauscht -->
<div id="my-tbl" data-gk-live-table="/my-list">
    <!-- Tabelle, Sort-Header (<a>), Paginierung — alles live -->
</div>
```

**Controller-Seite**: bei `X-Requested-With: XMLHttpRequest` oder `?partial=1` nur den Container-Inhalt ohne Layout rendern. Beispiel PHP:

```php
if ($request->isAjax() || $request->get('partial') === '1') {
    return $this->view('my-list-partial', $data);
}
return $this->view('my-list', $data);
```

Features:
- **Debounce 250 ms** für XHR-fetch, URL-Sync aber sofort.
- **Link-Interceptor**: `<a href>` innerhalb des Containers die auf denselben Endpoint zeigen → AJAX-Reload (Sort-Header, Pagination).
- **`patchNavSelects()`**: überschreibt `onchange` von `<select data-gk-years>` sodass sie `window.location.search` als Basis nehmen. Behält aktuelle Suche beim Jahr-Wechsel.
- Event `gk-live-reloaded` wird nach jedem Swap auf dem Container gefeuert — an Eigen-Code für Re-Init binden.

### AJAX Navigation (SPA-lite)

```php
// Sidebar mit AJAX-Navigation aktivieren
$sidebar->ajaxNav(true);
```

```html
<!-- Content-Container markieren -->
<div class="gk-with-sidebar" data-gk-content>
  <!-- Dieser Bereich wird bei Navigation ersetzt -->
</div>
```

Features:
- Sidebar-Links laden Content per fetch() ohne Seiten-Reload
- Ladebalken am oberen Bildschirmrand
- Browser Zurück/Vorwärts funktioniert via pushState
- Automatische Re-Initialisierung von Table, Tooltip etc.
- Fallback auf normale Navigation bei Fehler
- Externe Links und Ctrl/Cmd+Click werden nicht abgefangen

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
| `gk-empty` | Empty state (centered, padded) |

### BelegModal (since v1.15.0)

Globaler PDF-/Dokument-Vorschau-Modal mit `<iframe>`. Eliminiert `window.open()` für PDF-Previews.

```php
// Einmal pro Page (im Layout, vor </body>):
\GridKit\BelegModal::container();
```

```javascript
// JS-API überall:
GK.belegModal.open('/path/to/file.pdf');
GK.belegModal.open(url, { title: 'Rechnung 123' });
GK.belegModal.open(url, { autoPrint: true });             // druckt iframe nach load
GK.belegModal.open(url, {
    unlinkExpenseId: 456,                                  // zeigt "Verknüpfung trennen"-Btn
    onUnlink: function() { location.reload(); }
});
GK.belegModal.close();
```

- **Desktop**: iframe lädt URL inline (Browser-PDF-Viewer).
- **Mobile (≤ 768px)**: iframe versteckt, „PDF öffnen"-Button öffnet nativen Viewer.
- **ESC** schliesst, Click-outside schliesst.
- Falls Container fehlt: Fallback auf `window.open(url)` mit Console-Warning.

### ActionGroup (since v1.16.0)

Container für Action-Buttons in Tabellen-Spalten — vereinheitlicht das wiederkehrende
„flex-row mit Mini-Buttons"-Pattern. Eliminiert eigene `.xx-btn-icon`/`.xx-btn-match` Klassen.

```php
// PHP-API (deklarativ):
\GridKit\ActionGroup::render([
    ['icon' => 'edit',   'onclick' => "edit($id)",  'title' => 'Bearbeiten'],
    ['icon' => 'delete', 'onclick' => "del($id)",   'title' => 'Löschen', 'color' => 'danger'],
    ['icon' => 'send',   'label' => 'Mahnen',       'color' => 'warning', 'variant' => 'filled',
     'pill' => true, 'onclick' => "remind($id)", 'showIf' => $isOverdue],
]);
```

```html
<!-- Oder rohes HTML (für JS-generierten Inhalt): -->
<div class="gk-action-group">
    <button class="gk-btn gk-btn-xs gk-btn-text gk-btn-neutral gk-btn-icon-only">…</button>
    <button class="gk-btn gk-btn-xs gk-btn-filled gk-btn-warning gk-btn-pill">…</button>
</div>
```

Neue CSS-Klassen:
- `.gk-action-group` — `inline-flex; gap:4px; flex-wrap:nowrap` Container
- `.gk-btn-xs` — kleiner als `gk-btn-sm` (padding 3px 8px, font 11px). Icon-only: 26×26 px
- `.gk-btn-pill` — `border-radius:999px` (Badge-Stil)

Action-Item-Optionen: `icon`, `label`, `href`, `onclick`, `title`, `variant`, `color`, `size`,
`pill`, `disabled`, `showIf`, `class`.

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
| Width / Height | `gk-w-{full,auto}` `gk-h-{full,auto}` |
| Misc | `gk-clickable` `gk-overflow-{x,y}-auto` `gk-font-mono` `gk-no-decoration` `gk-truncate` `gk-break-word` |

```html
<!-- Don't: -->
<div style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--gk-text-muted)">…</div>

<!-- Do: -->
<div class="gk-flex-center gk-gap-md gk-fs-md gk-text-muted">…</div>
```

## Sync: GridKit to Consuming Systems

### → SSI Panel (s7, already hardlinked)
`/home/develop/gridkit/` = `/home/pawbot/projects/gridkit/` — same inode, no sync needed.
Public assets served from `/home/develop/ssi-core/public/gridkit/` — check if hardlinked too.

### → PawBot Dashboard (s7)
```bash
rsync -av /home/pawbot/projects/gridkit/ /home/pawbot/core/dashboard/gridkit/ \
  --exclude='.git' --exclude='demo' --exclude='vendor' \
  --exclude='src/Auth.php' --exclude='src/Sidebar.php' --exclude='js/gridkit.js'
```
⚠️ These 3 files have PawBot-specific extensions — NEVER overwrite:
- `src/Auth.php` — Session-Lock, Remember-Cookie
- `src/Sidebar.php` — Brand-Image, HTML-Column support
- `js/gridkit.js` — Dashboard-specific extensions

**Dashboard is currently at v1.0.0 — needs sync!**

## Common Pitfalls

1. **Search through HTML** — Never put HTML in `search()` column keys. Use plain-text key + separate display key.
2. **Missing `Lang::jsConfig()`** — "no_entries" shows as raw key. Must be in `<head>` before `gridkit.js`.
3. **Wrong button classes** — Use `gk-btn-filled` not `gk-btn--filled` (no double dash).
4. **Wrong toast API** — Use `GK.toast.success()` not `GK.toast()`.
5. **Wrong modal API** — `GK.modal.open(title, html)` takes title + HTML string, not an ID.
6. **Direct project edits** — Always edit source at `/home/pawbot/projects/gridkit/`, never in consuming projects.
