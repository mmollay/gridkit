# GRIDKit – Agent Skill

> **Version:** 1.4.13 | **License:** MIT | **Repository:** https://github.com/mmollay/gridkit
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
| Lang | `GridKit\Lang` | i18n / multilingual support |

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
