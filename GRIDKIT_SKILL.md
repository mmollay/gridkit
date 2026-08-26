# GridKit – Agent Skill

> **Version:** 1.43.0 | **License:** MIT | **Repository:** https://github.com/mmollay/gridkit
> **Demo:** https://gridkit.ssi.at

## Purpose

You are building or maintaining a web application using **GridKit**, a lightweight PHP component framework for admin dashboards. This skill is the authoritative reference for correct GridKit usage.

## Architecture

- **Stack:** PHP 8.2+, Vanilla JS, CSS (Material Design 3)
- **Zero Dependencies:** 1 CSS file + 1 JS file, no build process
- **Namespace:** `GridKit\` | CSS prefix: `gk-` | Data attributes: `data-gk-`

## Change Workflow

**Never modify GridKit files inside a consuming project.** Change the framework
at its source, bump `VERSION`, note it in `CHANGELOG.md`, then update the copy
your project uses. Local edits in a consuming project are silently lost on the
next update and split the codebase in two.

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
| Pagination | `GridKit\Pagination` | Server-side pager below the table (`.gk-rowpager`), optional PageSize |
| PageSize | `GridKit\PageSize` | Rows per page — lives in the pager bar, not in the table footer |
| liveTable (JS) | `GK.liveTable` | AJAX tables (search/filter/sort/pagination live, no reload) |
| BelegModal | `GridKit\BelegModal` | PDF / document preview modal with iframe + mobile fallback (since v1.15.0) |
| ActionGroup | `GridKit\ActionGroup` | Container for action buttons inside table columns (since v1.16.0) |
| SortLink | `GridKit\SortLink` | Sortable column headers for hand-built tables (server-side sort) |
| Select | `GridKit\Select` | Searchable single/multi select, optionally AJAX-fed |
| Icon | `GridKit\Icon` | Inline SVG icons with a Material Icons fallback |

## Page skeleton

Every class lives under the `GridKit\` namespace and is autoloaded by
`autoload.php`. A complete page needs nothing beyond that — no template engine,
no build step:

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';   // or '/path/to/gridkit/autoload.php'

use GridKit\{Button, Form, Lang, Layout, Modal, Sidebar, StatCards, Table, Theme};

Lang::set($_GET['lang'] ?? 'en');   // 'en' | 'de'
Theme::set('indigo', 'light');      // indigo | ocean | forest | rose | amber | slate
?>
<!doctype html>
<html <?= Theme::attributes() ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
<link rel="stylesheet" href="<?= Layout::asset('css/gridkit.css') ?>">
<link rel="stylesheet" href="<?= Layout::asset('css/themes.css') ?>">
<?= Lang::jsConfig() ?>
</head>
<?= Theme::bodyTag('gk-root') ?>

<!-- components go here -->

<?php Modal::container(); ?>
<script src="<?= Layout::asset('js/gridkit.js') ?>"></script>
</body>
</html>
```

`skeleton.php` in the repository is this file, filled in. `Layout::asset()`
appends the file's modification time so a changed stylesheet is not served from
a stale cache.

The other classes import the same way — the full list is in the table above,
each one `GridKit\<Name>`:

```php
use GridKit\{ActionGroup, Auth, BelegModal, FilterChips, Header, Icon,
              PageSize, Pagination, Select, SortLink, TableHeader, YearFilter};
```

A complete working application is in [`examples/invoices/`](examples/invoices/).

### With a sidebar

A sidebar is `position: fixed`, so the content beside it needs the wrapper that
makes room. Without it everything renders **underneath** the sidebar — silently,
because nothing is broken, it is just covered:

```php
<?= Theme::bodyTag('gk-root') ?>

<?php (new Sidebar('main'))
    ->brand('My app', 'widgets')
    ->group('Navigation')
    ->item('Dashboard', '?p=dashboard', 'dashboard', ['active' => true])
    ->item('Invoices',  '?p=invoices',  'receipt_long', ['badge' => 12])
    ->render(); ?>

<div class="gk-with-sidebar">          <!-- required: clears the fixed sidebar -->
    <?= (new Header())->title('Dashboard')->user('Jane')->render() ?>

    <main class="gk-main">             <!-- padding and max-width -->
        <!-- components go here -->
    </main>
</div>
```

`gk-with-sidebar` carries the left margin and shrinks when the sidebar
collapses; `gk-main` carries the padding. `skeleton.php` in the repository is
this file, filled in.

### Inside SSI Panel

In SSI Panel the layout loads GridKit, so a view only imports what it uses:

```php
<?php
$this->layout('layouts/panel');
use GridKit\{Table, Form, StatCards, FilterChips, Button};
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
    ->toolbarHtml('<div class="gk-toolbar-spacer"></div>'
                  . Button::render('New', ['icon' => 'add', 'shape' => 'pill']))
    ->column('name',   'Name',   ['sortable' => true])
    ->column('email',  'Email',  ['sortable' => true])
    ->column('status', 'Status', ['format' => 'html'])  // HTML column: not searched
    ->button('edit',   ['icon' => 'edit',   'params' => ['id' => 'id']])
    ->button('delete', ['icon' => 'delete', 'params' => ['id' => 'id'], 'color' => 'danger'])
    ->paginate(25)
    ->render();
```

Server-side, straight from MySQL — GridKit builds the `LIKE`, the `WHERE` for
every declared filter, the `ORDER BY`, the `COUNT` and the `LIMIT`:

```php
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

**Column formats:** `currency`, `percent`, `date`, `datetime`, `boolean`, `label`, `html`, `email`, `number`

**A second line inside a cell** (subject, account, reference) — never widens the column:

```html
<div class="gk-cell-sub" title="Full text">Your receipt from Anthropic…</div>
```

**`groupBy($column, $labels)`:** inserts a group row whenever the value changes. Sort the rows by that column first.

**Button `onclick`:** `{field}` is replaced with the row's value, JSON-encoded (`'onclick' => 'open({id})'`).

**Button colors:** `danger`, `success`, `warning`, `primary` (default: neutral)

**`confirm`:** `->button('delete', ['icon' => 'delete', 'confirm' => true])` asks
before the button acts — `true` uses the translated default, a string is used as
the message. It gates whatever the button would otherwise do: an `onclick` is
wrapped so it only runs on confirmation, and a `modal` opens only after.

**Buttons with neither `modal` nor `onclick`** fire `gk:rowaction` on the table
element, the same shape `gk:bulkdelete` uses. This is how a delete button works
without you writing any JavaScript for it:

```js
document.querySelector('[data-gk-table=products]')
    .addEventListener('gk:rowaction', e => {
        const { action, params, tableId } = e.detail;   // 'delete', { id: 42 }
    });
```

**Row identity:** a row button always carries its row's `id` in `data-gk-params`,
so a modal knows which record it was opened for. `'params' => ['x' => 'column']`
adds more, and mapping `id` yourself overrides the default.

**`showIf`:** `->button('preview', ['icon' => 'open_in_new', 'params' => ['url' => 'url'], 'showIf' => 'has_preview'])`
— button only renders if the row's `has_preview` value is truthy.

**`->selectable('id')`:** checkbox column, a select-all in the header, and a bulk bar. Deleting fires `gk:bulkdelete` with `{ ids, tableId }` — the application does the deleting. Every change fires `gk:selectionchange` with `{ ids, tableId, count }`, bulk bar or not. Shift-click selects a range. Without the `Table` class: `data-gk-table` + `data-gk-selectable`, rows carrying `data-gk-row-id` and a `td.gk-cb-col`. A row without `data-gk-row-id` cannot be selected. Select-all covers the visible rows only. After a live reload (`gk-live-reloaded`) GridKit re-binds the table.

### Server-side tables (`rows()` + `isAjaxReload()`)

Three ways to get data into a table, in order of how much GridKit does for you:

```php
->setData($rows)              // client-side: the browser gets everything and
                              // searches, sorts and pages in JavaScript.
                              // Fine up to a few hundred rows.

->query($db, $sql)            // GridKit builds the SQL: LIKE for the search,
                              // WHERE for every declared filter, ORDER BY,
                              // COUNT and LIMIT. mysqli only.

->rows($page, $total)         // you ran the query. PDO, SQLite, Postgres, an
                              // HTTP API, an array — anything. Hand over one
                              // page plus the total before LIMIT.
```

`rows()` and `query()` are both **server-driven**: search, filter, sort and
paging go back to the server as URL parameters. Read them where you build the
query:

| Parameter | From |
|---|---|
| `gk_search` | the search box |
| `gk_filter_<column>` | a `filter()` dropdown |
| `gk_sort` / `gk_dir` | a sortable column header |
| `gk_page` | the pager |

**The page must end the request itself.** The reload injects the response body
straight into the table's wrapper, so it has to be the fragment and nothing
else — otherwise your sidebar, header and script tags land inside the table:

```php
$table = (new Table('invoices'))->rows($result['rows'], $result['total']);

if (Table::isAjaxReload('invoices')) {
    $table->render();

    // Anything outside the table that should keep up goes in a template,
    // addressed by a CSS selector.
    echo '<template data-gk-replace="[data-gk-stats=invoice-stats]">';
    renderStats();
    echo '</template>';

    exit;
}
```

`Table::isAjaxReload()` without an argument matches any table, which is enough
when the page has one.

A complete worked example is in [`examples/invoices/`](examples/invoices/).

### Status labels with their own text

`labels` maps a stored value to a colour, or to a colour and the text to show —
which is what a status column needs once the application runs in more than one
language. The value stays `paid`; the cell reads whatever this locale calls it.

```php
->column('status', 'Status', ['format' => 'label', 'labels' => [
    'draft'   => 'gray',                                        // colour only
    'paid'    => ['color' => 'green', 'text' => Lang::t('paid')],
]])
```

Without a `labels` entry the colour is guessed from a built-in word list
(English and German), and the raw value is shown.

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

### SortLink

Sortable headers for tables you build by hand. `Table` already sorts its own
columns — reach for `SortLink` when you are writing the `<table>` yourself.

```php
echo SortLink::header('invoice_date', 'Date', [
    'current_sort' => $sort,          // the column currently sorted
    'current_dir'  => $dir,           // 'asc' | 'desc'
    'base_url'     => '/invoices',
    'preserve'     => ['q' => $q, 'year' => $year],   // survives the sort click
    'extra_class'  => 'gk-text-right',
]);
```

Sharing one context across several columns is shorter:

```php
$sl = SortLink::context('/invoices', $sort, $dir, ['q' => $q, 'year' => $year]);

echo $sl('invoice_date',  'Date');
echo $sl('customer_name', 'Customer');
echo $sl('gross_total',   'Total', 'gk-text-right');   // 3rd arg = extra class
```

`context()` returns a closure, so it is passed around like any other callable.
It toggles `sort` and `dir` in the URL and re-encodes everything under
`preserve`, which is what keeps an active filter alive across a sort.

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
    ->reset('/expenses')                                     // optional reset btn
    ->render();
```

API:
- `make($id)` static factory
- `status(\Closure $renderer)`: top row, full width
- `search(string $name, string $value = '', string $placeholder = '…', array $opts = ['live' => '…', 'id' => '…'])`
- `filter($contentOrClosure)`: any number of toolbar slots — Closure (echo'd) or raw HTML string
- `advanced(\Closure $renderer, string $summary = 'Erweiterte Filter', bool $open = false)`
- `reset(string $baseUrl, string $label = '')` — an empty label uses the translation

CSS classes (all auto-applied): `gk-tableheader`, `gk-tableheader-status`, `gk-tableheader-toolbar`, `gk-tableheader-advanced`, `gk-tableheader-spacer`.

**Do NOT** build your own filter row with raw `gk-toolbar` / `gk-toolbar-stacked` if `TableHeader` fits — every table page must use this for visual consistency.

### StatCards

```php
(new StatCards('stats-id'))
    ->card('Umsatz',   12450.80, ['format' => 'currency', 'icon' => 'euro',    'color' => 'primary'])
    ->card('Benutzer', 1284,     ['format' => 'number',   'icon' => 'people',  'color' => 'success'])
    ->card('Errors',   3,        ['format' => 'number',   'icon' => 'error',   'color' => 'danger', 'highlight' => true])
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

// JS API — the body is FETCHED from a URL (POST, X-Requested-With: XMLHttpRequest).
// The second argument is an address, never markup.
GK.modal.open('Title', 'forms/edit.php', { id: 42 }, 'medium');   // params + size optional
GK.modal.close();

// Static inline modal (for complex content):
<div class="gk-modal-overlay" id="my-modal" style="display:none;">
    <div class="gk-modal gk-modal-small">   <!-- or gk-modal-large -->
        <div class="gk-modal-header">
            <h3 class="gk-modal-title">Title</h3>
            <button class="gk-modal-close"
                onclick="document.getElementById('my-modal').style.display='none'">&times;</button>
        </div>
        <div class="gk-modal-body">Content</div>
        <div class="gk-modal-footer">   <!-- since 1.22.3: action bar with its own padding -->
            <?= Button::render('Close', ['variant' => 'outlined', 'color' => 'neutral', 'onclick' => "..."]) ?>
        </div>
    </div>
</div>
```

**Footer:** action buttons at the end of a modal belong in `gk-modal-footer` — NOT
`gk-form-actions` straight inside the modal, which has no side padding. A
compatibility rule catches the old shape.

### Form (AJAX)

```php
(new Form('user-form'))
    ->action('/api/save-user')
    ->method('POST')
    ->row()
        ->field('first_name', 'First name', 'text', ['width' => 8, 'required' => true])
        ->field('last_name',  'Last name',  'text', ['width' => 8, 'required' => true])
    ->endRow()
    ->field('email', 'Email', 'email', ['width' => 16])
    ->field('role',  'Role',  'select', ['width' => 8, 'options' => ['admin' => 'Admin', 'user' => 'User']])
    ->field('active', 'Active', 'toggle')
    ->submit('Save')
    ->render();
```

**Field types.** Eleven have rendering of their own:

`textarea` · `select` (searchable) · `multiselect` · `ajaxselect` · `checkbox` ·
`toggle` · `radio` · `file` (drag & drop) · `richtext` (CKEditor) · `color` · `range`

Anything else becomes an `<input type="…">`, so every HTML type works:
`text`, `number`, `email`, `tel`, `url`, `password`, `date`, `time`,
`datetime` (rendered as `datetime-local`), `month`, `week`, `search`, `hidden`.

A type that is neither of those raises an `E_USER_WARNING` and falls back to a
text box — `'searchable-select'` was documented here for a long time, is not a
type, and rendered as a plain text field without a word of complaint. The
searchable select is plain `'select'`.

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
GK.toast.success('Saved.');
GK.toast.error('Something went wrong.');
GK.toast.warning('Check this before continuing.');
GK.toast.info('Nothing to do here yet.');

// Dynamic modal — the second argument is a URL whose response fills the body
GK.modal.open('Title', 'forms/edit.php', { id: 42 }, 'medium');
GK.modal.close();

// Table refresh (after save/delete in server-side mode).
// Returns false when no table with that id is on the page.
GK.table.refresh('table-id');
GK.table.refreshAll();          // every table on the page
```

### Pagination + PageSize (since 1.22 / 1.27)

Server-side pager **below** `.gk-table-wrap`, not inside the card and not
and not inside the live container. Same look as `GK.rowPager`
(`.gk-rowpager` / `.gk-pg`).

```php
// The page, on first render — a sibling below the table:
Pagination::fromPaginator($items, [
    'label'    => 'Expenses',
    'live'     => 'exp-live',          // binds the AJAX clicks + replace target
    'pageSize' => ['current' => $perPage, 'live' => 'exp-live'],
    'params'   => ['year' => $year, 'q' => $q ?: null],
]);

// In the live partial (AJAX), so the counter and page list follow the filter:
<template data-gk-replace="[data-gk-pager=exp-live]">
<?php Pagination::fromPaginator($items, /* the same options */); ?>
</template>
```

Short lists with no server-side LIMIT: put `data-gk-rows="25"` on the table and
the client-side `GK.rowPager` builds the same bar.

### Global search (`GK.search`)

A system-wide quick search, opened with Ctrl+K (Cmd+K on Mac) or by any element
carrying `data-gk-search`. GridKit draws the widget; **what** is searched is
entirely your endpoint's business.

```js
GK.search.init({
    url:       '/api/search',   // called with ?q=<query>
    hotkey:    'ctrl+k',
    minLength: 2,               // 0 opens with results already showing
});
```

Your endpoint answers with groups of hits:

```json
{ "groups": [
    { "title": "Invoices",
      "items": [
        { "title":    "INV-2026-0184",
          "subtitle": "Ecklund & Partner · Mar 12, 2026",
          "amount":   "€1,240.00",
          "url":      "/invoices/184",
          "icon":     "receipt_long" } ] } ] }
```

Only `title` and `url` are required. Arrow keys move, Enter opens, Escape
closes.

The German key names this contract used to require — `gruppen`, `titel`,
`treffer`, `untertitel`, `betrag` — are still accepted, so an endpoint written
against the old shape keeps working. New ones should use the English names.

### Live Tables (`GK.liveTable`) — since 1.9.0

AJAX-filtered tables: search, filter, sort and paging with no full page reload.
The caret stays put while typing; the URL is kept in step via `history.replaceState`.

```html
<!-- Inputs: beliebig ausserhalb des Containers -->
<input data-gk-live-input="my-tbl" name="q" placeholder="Suche">
<select data-gk-live-input="my-tbl" name="status">...</select>

<!-- Container: swapped over AJAX -->
<div id="my-tbl" data-gk-live-table="/my-list">
    <!-- Table, sort headers (<a>), pagination — all live -->
</div>
```

**On the controller page**: when `X-Requested-With: XMLHttpRequest` or `?partial=1` is present, render the container's contents only, without the layout. In PHP:

```php
if ($request->isAjax() || $request->get('partial') === '1') {
    return $this->view('my-list-partial', $data);
}
return $this->view('my-list', $data);
```

Features:
- **250 ms debounce** before the fetch; the URL is synced immediately.
- **Link interception**: an `<a href>` inside the container pointing at the same endpoint is followed over AJAX-Reload (Sort-Header, Pagination).
- **`patchNavSelects()`**: overrides `onchange` on `<select data-gk-years>` so they build on `window.location.search`. Keeps the current search when the year changes.
- The `gk-live-reloaded` event fires on the container after every swap — bind your own re-initialisation to it.

### AJAX Navigation (SPA-lite)

```php
// Turn on AJAX navigation for the sidebar
$sidebar->ajaxNav(true);
```

```html
<!-- Mark the content container -->
<div class="gk-with-sidebar" data-gk-content>
  <!-- This region is replaced on navigation -->
</div>
```

Features:
- Sidebar links load content with fetch(), no page reload
- Ladebalken am oberen Bildschirmrand
- Browser back/forward works through pushState
- Automatische Re-Initialisierung von Table, Tooltip etc.
- Falls back to a normal page load on error
- External links and Ctrl/Cmd-click are left alone

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

A global PDF / document preview modal built on an `<iframe>`. Replaces `window.open()` for previews.

```php
// Once per page, in the layout, before </body>:
\GridKit\BelegModal::container();
```

```javascript
// The JS API, available anywhere:
GK.belegModal.open('/path/to/file.pdf');
GK.belegModal.open(url, { title: 'Invoice 123' });
GK.belegModal.open(url, { autoPrint: true });             // prints the iframe once loaded
GK.belegModal.open(url, {
    unlinkExpenseId: 456,                                  // shows an "unlink" button
    onUnlink: function() { location.reload(); }
});
GK.belegModal.close();
```

- **Desktop**: the iframe loads the URL inline, in the browser's own PDF viewer.
- **Mobile (≤ 768px)**: the iframe is hidden; an "Open PDF" button hands off to the native viewer.
- **Esc** closes it, so does clicking outside.
- With no container on the page it falls back to `window.open(url)` and warns on the console.

### ActionGroup (since v1.16.0)

A container for action buttons inside table columns — one shape for the recurring
"flex row of small buttons" pattern, instead of per-project `.xx-btn-icon` classes.

```php
// The declarative PHP API:
\GridKit\ActionGroup::render([
    ['icon' => 'edit',   'onclick' => "edit($id)",  'title' => 'Edit'],
    ['icon' => 'delete', 'onclick' => "del($id)",   'title' => 'Delete', 'color' => 'danger'],
    ['icon' => 'send',   'label' => 'Remind',       'color' => 'warning', 'variant' => 'filled',
     'pill' => true, 'onclick' => "remind($id)", 'showIf' => $isOverdue],
]);
```

```html
<!-- Or raw HTML, for content generated in JavaScript: -->
<div class="gk-action-group">
    <button class="gk-btn gk-btn-xs gk-btn-text gk-btn-neutral gk-btn-icon-only">…</button>
    <button class="gk-btn gk-btn-xs gk-btn-filled gk-btn-warning gk-btn-pill">…</button>
</div>
```

New CSS classes:
- `.gk-action-group` — `inline-flex; gap:4px; flex-wrap:nowrap` Container
- `.gk-btn-xs` — smaller than `gk-btn-sm` (padding 3px 8px, font 11px). Icon-only: 26×26 px
- `.gk-btn-pill` — `border-radius:999px`, badge-shaped

Action item options: `icon`, `label`, `href`, `onclick`, `title`, `variant`, `color`, `size`,
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

## Common Pitfalls

1. **Search through HTML** — Never put HTML in `search()` column keys. Use plain-text key + separate display key.
2. **Missing `Lang::jsConfig()`** — "no_entries" shows as raw key. Must be in `<head>` before `gridkit.js`.
3. **Wrong button classes** — Use `gk-btn-filled` not `gk-btn--filled` (no double dash).
4. **Wrong toast API** — Use `GK.toast.success()` not `GK.toast()`.
5. **Wrong modal API** — the signature is `GK.modal.open(title, url, params, size)`. It
   POSTs to `url` and puts the response in the body. It does NOT take an HTML string: pass
   markup and the browser requests it as a path, so the modal fills with the server's 404
   page. For inline HTML use the static inline modal above.
6. **Direct project edits** — Always change GridKit at its own source, never inside a consuming project.
