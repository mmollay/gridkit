# GridKit – Agent Skill

> **Version:** 1.59.0 | **License:** MIT | **Repository:** https://github.com/mmollay/gridkit
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
| Icon | `GridKit\Icon` | Inline SVG icons with a Material Icons fallback — `Icon::svg($name, $px)`: the 2nd argument is an **int** pixel size (default 16), not an options array |

## The one rule to read first: echo or return

Half of GridKit prints; half hands you a string. Getting this wrong produces no
error and no warning — the page renders, the piece is simply missing. Five
agents were given this file and a page to build; not one got a first draft
without tripping over this.

**These twelve PRINT. Call them as a statement — `<?php $x->render(); ?>`:**

| | | |
|---|---|---|
| `Table::render()` | `Form::render()` | `Sidebar::render()` |
| `StatCards::render()` | `FilterChips::render()` | `TableHeader::render()` |
| `YearFilter::render()` | `Pagination::render()` | `PageSize::make()->…->render()` |
| `ActionGroup::render()` | `Modal::container()` | `BelegModal::container()` |

`PageSize::render()` above is an INSTANCE method — build it with the static
`PageSize::make('per_page')` first, then chain. Same for `TableHeader::make()`.
Everything else in that table is called on an object you constructed with `new`.

**These RETURN a string. You must echo it — `<?= … ?>`:**

| | | |
|---|---|---|
| `Header::render()` | `Button::render()` | `Button::icon()` |
| `Theme::switcher()` | `Theme::attributes()` | `Layout::bodyTag()` |
| `Icon::svg()` | `Select::searchable()` | `Layout::asset()` |
| `Lang::jsConfig()` | `Pagination::build()` | |

`Header::render()` is the exception that catches people: every other component
you construct with `new` prints, and this one does not. `<?php (new Header())
->render(); ?>` renders nothing at all, silently.

The rule behind it, if you want one: a component that owns a block of the page
prints it; a helper that produces a fragment for you to place returns it. Header
sits on the wrong side of that line for historical reasons and is not going to
move, because every existing page echoes it.

---

## Page skeleton

Every class lives under the `GridKit\` namespace and is autoloaded by
`autoload.php`. A complete page needs nothing beyond that — no template engine,
no build step:

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';   // or '/path/to/gridkit/autoload.php'

use GridKit\{Button, Form, Lang, Layout, Sidebar, StatCards, Table, Theme};

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
<?= Layout::bodyTag('gk-root') ?>

<!-- components go here -->

<script src="<?= Layout::asset('js/gridkit.js') ?>"></script>
</body>
</html>
```

`skeleton.php` in the repository is this file, filled in.

**`Layout::asset()` stamps a path; it does not resolve one.** It appends the
file's modification time so a changed stylesheet is not served from a stale
cache — and it hands back whatever path you gave it. So the path has to be one
the *browser* can reach from the page:

```php
// page inside the GridKit directory
echo Layout::asset('css/gridkit.css');                    // css/gridkit.css?v=…

// page in an app directory beside the checkout
echo Layout::asset('../gridkit/css/gridkit.css');

// Composer install — the files live under vendor/
echo Layout::asset('vendor/mmollay/gridkit/css/gridkit.css');
```

Get it wrong and the stamp still appears, so the URL looks right while the
browser gets a 404 and the page renders unstyled. If `vendor/` is outside your
document root, copy or symlink its `css/` and `js/` into the public root.

`Modal::container()` is **not** in the skeleton above and should not be in
yours: since 1.42.0 it emits nothing. `GK.modal.open()` builds its own overlay.

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
<?= Layout::bodyTag('gk-root') ?>

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

**`filter($column, $type, $opts)`:** a dropdown in the toolbar, bound to the
table — this is what "every declared filter" above means. `$type` is `'select'`;
`$opts` takes `options` (value → label), `placeholder` (the empty "all" entry)
and `label` (accessible name, defaults to the column's label).

```php
->filter('status', 'select', [
    'options'     => ['open' => 'Open', 'paid' => 'Paid'],
    'placeholder' => 'All statuses',
])
```

The choice travels as `gk_filter_<column>` and comes back selected after a
reload. `setData()` filters in the browser, `query()` adds `` `column` = ? `` to
the WHERE, and with `rows()` you read `$_GET['gk_filter_<column>']` where you
build the query. Use this for a status dropdown that belongs to a table — not
`FilterChips` plus a GET parameter of your own.

**⚠️ Search rule:** `search()` searches the column keys you name. If a column contains HTML (badges, links), use a separate plain-text key for search and a `_display` key for rendering. Never put HTML in searchable columns.

**Column formats:** `currency`, `percent`, `date`, `datetime`, `boolean`, `label`, `html`, `email`, `number`

**A second line inside a cell** (subject, account, reference) — never widens the column:

```html
<div class="gk-cell-sub" title="Full text">Your receipt from Anthropic…</div>
```

**`->groupBy($column, $labels)`:** inserts a group row whenever the value changes. Sort the rows by that column first.

**Button `onclick`:** `{field}` is replaced with the row's value, JSON-encoded (`'onclick' => 'open({id})'`).

**Button colors:** `danger`, `success`, `warning`, `primary` (default: neutral)

**Button `modal`:** `'modal' => 'edit_form'` names a modal registered on the same
table with `->modal('edit_form', 'Edit', 'forms/edit.php', ['size' => 'medium'])`.
GridKit opens it itself — `GK.modal.open(title, url, params, size)` with the row's
`data-gk-params` as the params — so no JavaScript of yours is involved.
`->newButton('New product', ['modal' => 'edit_form'])` opens the same modal for a
new record. Wiring `onclick` to `GK.modal.open` by hand is the fallback, not the API.

```php
->modal('edit_form', 'Edit customer', 'forms/edit.php', ['size' => 'medium'])
->button('edit', ['icon' => 'edit', 'modal' => 'edit_form'])
```

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

**`->emptyState($title, $opts)`** sets the wording for a table with no rows —
`['hint' => …, 'icon' => …, 'action' => …]`. GridKit works out by itself whether
the table is genuinely empty or only filtered, and offers the way back in the
second case, so only describe the first.

**Shaping the table.** All chainable, all optional:

```php
->toolbar(false)     // no search/filter bar above the table at all
->searchable(false)  // keep the toolbar, drop the search box
->size('sm')         // sm | md (default) | lg — row height and padding
->variant('striped') // default | bordered | striped | celled | padded |
                     // minimal | flat | inverted | compact.
                     // ONE slot: a second call replaces the first.
->nowrap()           // no cell wraps anywhere in the table
->footer(['', 'Total', '€12,480.00'])   // a <tfoot> row, cell by cell.
                     // A plain string is a left-aligned cell. For anything
                     // else pass an array — a currency total under a
                     // right-aligned column needs one:
                     //   ['text' => '€12,480.00', 'align' => 'right',
                     //    'bold' => true, 'colspan' => 2]
->loadTime(38)       // a <tfoot> meta row "N entries · 38 ms", NOT a toolbar
                     // item: ->toolbar(false) still shows it, and with
                     // ->footer() it shares that one row — the time only
                     // fills the columns the footer cells leave over.
->mobile('card')     // card | scroll — how it collapses on a phone
```

### Server-side tables (`rows()` + `isAjaxReload()`)

Three ways to get data into a table, in order of how much GridKit does for you:

```php
->setData($rows)              // client-side: the browser gets everything and
                              // searches, sorts and pages in JavaScript.
                              // Fine up to a few hundred rows.

->query($db, $sql)            // GridKit builds the SQL: LIKE for the search,
                              // WHERE for every declared filter, ORDER BY,
                              // COUNT and LIMIT. mysqli only.

->rows($pageRows, $total)     // you ran the query. PDO, SQLite, Postgres, an
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
    // addressed by a CSS selector. The matched element is replaced whole
    // (outerHTML), so the template body must itself re-emit an element the
    // selector matches — here the complete StatCards container, because
    // (new StatCards('invoice-stats'))->render() writes
    // data-gk-stats="invoice-stats" onto that container. Emitting only the
    // inner cards deletes the target on the first reload: no error, and the
    // stats silently keep their first-load values forever.
    echo '<template data-gk-replace="[data-gk-stats=invoice-stats]">';
    renderStats();   // (new StatCards('invoice-stats'))->card(…)->render();
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
    'draft'   => 'gray',                                     // colour only
    'paid'    => ['color' => 'green', 'text' => $t('paid')], // colour + text
]])
```

**The colours are a fixed list:** `gray`, `green`, `red`, `orange`, `blue`,
`plain`. Anything else renders as an unstyled label — `primary`, `success`,
`danger` and `warning` are Button and StatCards vocabulary and do **not** work
here.

**`$t('paid')` above is your own translator, not `Lang::t()`.** `Lang` holds
GridKit's own interface strings — "Search…", "No entries found", "Delete". It
is not a catalogue for your application, and `Lang::t('paid')` returns the
string `paid` because there is no such key. See *Translating your own strings*
below.

Without a `labels` entry the colour is guessed from a built-in word list
(English and German), and the raw value is shown.

### Button

```php
// RETURNS a string — echo it. See "echo or return" at the top.
echo Button::render('Label', [
    'variant' => 'filled',    // filled | outlined | tonal | text
    'color'   => 'primary',   // primary | success | danger | warning | neutral
    'icon'    => 'add',       // Material Icon name
    'size'    => 'sm',        // sm | md (default) | lg
    'shape'   => 'pill',      // rounded (default) | pill | circle | square
    'href'    => '/path',     // renders as <a>
    'onclick' => 'jsCode()',
    'title'   => 'Add a row', // tooltip
    'aria'    => 'Add a row', // accessible name; see below
    'disabled' => false,
    'type'    => 'submit',    // button (default) | submit | reset
    'form'    => 'form-id',   // submit a form this button is NOT inside.
                              // Setting it makes the button a submit button,
                              // because a type="button" cannot submit anything
                              // — the attribute would just sit there inert.
]);

// A floating action button — round, fixed, bottom right.
echo Button::fab('add', ['color' => 'primary', 'extended' => true, 'label' => 'New']);

// Icon-only: pass an empty label. GridKit gives it an accessible name from
// the icon, translated for the active locale, so a screen reader does not
// read the ligature. Pass 'aria' when the icon alone does not say what the
// button does.
echo Button::render('', ['icon' => 'delete', 'color' => 'danger']);
echo Button::icon('content_copy', ['aria' => 'Copy the invoice number']);
```

### Header

The only component with no section here until 1.46.0, which is why an agent
building a dashboard put two theme switchers on the page: `->user()` renders one
of its own.

**`Header::render()` RETURNS a string** — echo it. It is the one component you
build with `new` that does not print (see *echo or return* at the top).

```php
echo (new Header())
    ->title('Dashboard')                  // or ->title($html, raw: true)
    ->breadcrumb(['Home' => '/', 'Invoices' => '/invoices', 'INV-2026-001'])
    ->sidebarToggle(true)                 // the hamburger that opens the Sidebar
    ->fixed(true)                         // stays at the top of the viewport
    ->sticky(true)                        // scrolls away, comes back on scroll up
    ->search('Search invoices…', 'q')     // only for a page you filter yourself
    ->action(Button::render('New', ['icon' => 'add', 'size' => 'sm']))
    ->action(Theme::switcher())           // ONLY if you skip the user menu — see below
    ->user('Jane Doe', [
        'role'   => 'Administrator',      // a non-clickable label at the top
        'avatar' => '/img/jane.jpg',      // initials are used when absent
        'theme_switcher' => true,         // DEFAULT — the menu carries its own switcher
        'menu'   => [
            ['label' => 'Profile',  'href' => '/profile',  'icon' => 'person'],
            ['label' => 'Settings', 'href' => '/settings', 'icon' => 'settings'],
            'divider',                    // exactly this string; anything else is ignored
            ['label' => 'Sign out', 'href' => '/logout',   'icon' => 'logout'],
        ],
    ])
    ->render();
```

**The name and role above are yours to supply — `Auth::user()` does not return
this array.** It returns the *username* as a plain string, or `null` when nobody
is signed in. There is no `name`, `role` or `id` on it, and `$me['role']` against
it is a fatal `TypeError: Cannot access offset of type string on string`. The
users file holds `username:bcrypt-hash` and nothing more, so a display name or a
role has to come from a table of your own, keyed by that username:

```php
$profiles = ['jsmith' => ['name' => 'Jane Smith', 'role' => 'Administrator']];

$user = Auth::user();                     // 'jsmith', or null when signed out
if ($user !== null) {
    // The fallback has to be a string: ->user() takes one, and handing it the
    // null that Auth::user() returns when nobody is signed in is a TypeError.
    $me = $profiles[$user] ?? ['name' => $user, 'role' => ''];
    echo (new Header())->user($me['name'], ['role' => $me['role']])->render();
}
```

**`->user()` already contains a theme switcher.** Adding
`->action(Theme::switcher())` beside it puts twelve theme dots and two mode
toggles on the page, with competing active states and no error. Pick one:

| You want | Do |
|---|---|
| a user menu, switcher inside it | `->user($name, […])` — nothing else |
| a switcher, no user menu | `->action(Theme::switcher())` |
| a user menu with no switcher | `->user($name, ['theme_switcher' => false])` |

**`->search()` is not a `Table` search.** It renders a plain input with a name;
you read `$_GET['q']` and narrow the data yourself. A `Table` that declares
`->search([…])` needs none of it — see the search rule under *TableHeader*.

### Sidebar

`Sidebar::render()` PRINTS. It is `position: fixed`, so whatever sits beside it
needs the wrapper — see *With a sidebar* above; without it the page renders
underneath the sidebar, silently.

```php
(new Sidebar('main'))                     // the id, for collapse state
    ->brand('My project', 'widgets', 'v2.1')     // name, icon, optional version
    ->group('Navigation')                        // a heading; items follow it
    ->item('Dashboard', '?section=dashboard', 'dashboard', ['active' => true])
    ->item('Invoices',  '?section=invoices',  'receipt_long', ['badge' => 3])
    ->item('Reports',   '#', 'bar_chart', ['children' => [
        ['label' => 'Monthly', 'href' => '/reports/monthly'],
        ['label' => 'Yearly',  'href' => '/reports/yearly', 'active' => true],
    ]])
    ->divider()
    ->group('System')
    ->item('Settings', '?section=settings', 'settings')
    ->ajaxNav(true)                       // SPA-lite navigation, see below
    ->collapsePosition('bottom')          // 'top' (default) | 'bottom'
    ->headerOffset(true)                  // start below a full-width header
    ->render();
```

`->item($label, $href, $icon = '', $opts = [])` — the options are `active`,
`badge`, `children` (a submenu, same item shape) and `id` (the submenu's DOM id;
one is derived from the label otherwise).

`Sidebar::toggleButton()` RETURNS the hamburger for a page with no `Header`;
`Header::sidebarToggle(true)` is the usual way.

### Select

`Select::searchable()` RETURNS a string — echo it. It is the standalone form of
the widget `Form`'s `'select'` field type renders, for a `<select>` you are
placing yourself rather than inside a `Form`.

```php
echo Select::searchable('country', ['at' => 'Austria', 'de' => 'Germany'], [
    'selected'          => 'at',
    'placeholder'       => 'Choose a country',   // shown when nothing is picked
    'searchPlaceholder' => 'Type to filter…',
    'required'          => true,                 // real browser validation
    'aria'              => 'Country',            // accessible name; falls back
                                                 // to label, then placeholder
    'label'             => 'Country',
    'id'                => 'country',
    'class'             => 'my-extra-class',
]);
```

Inside a `Form` use the field type instead — `->field('country', 'Country',
'select', ['options' => …])` — which wires the label and the 16-column grid for
you. Reach for `Select::searchable()` only outside one.

### FilterChips

```php
(new FilterChips('filter-id', 'status'))   // 2nd param = GET param name
    ->baseUrl('/my-page')
    ->chip('',       'All (24)')           // value='' = "All" chip -> ?status= (empty, never omitted)
    ->chip('active', 'Active (18)')
    ->chip('won',    'Won',  ['color' => 'success'])
    ->chip('lost',   'Lost', ['color' => 'danger'])
    ->preserve(['year'])                   // keep other GET params on click
    ->render();
```

Active chip is auto-detected from `$_GET`. Color options: `success`, `danger`, `warning`, `primary`.
The param is always present in the URL, the "All" chip included (`?status=`) — this empty query string is what stops
`GK.liveTable.restoreSession` from jumping back to the last remembered filter. Read it with
`($_GET['status'] ?? '') !== ''`, never with `isset($_GET['status'])`.

### YearFilter

```php
(new YearFilter('year-filter', 'year'))   // id, query parameter
    ->years([2024, 2025, 2026])           // default: 2020 … this year
    ->mode('chips')                       // chips (default) | dropdown
    ->allOption('All years', 0)           // adds an "everything" entry
    ->baseUrl('/expenses')
    ->preserve(['q' => $q])
    ->selectClass('gk-filter')            // dropdown mode only
    ->render();
```

`->current()` gives back the selected year as an int — validate it yourself if
it steers a query; a visitor can put anything in the URL.


```php
$yf = new YearFilter('year-filter', 'year');  // 2nd param = GET param name
$yf->baseUrl('/my-page')
   ->range(2022, (int)date('Y'))              // newest first
   ->preserve(['status'])
   ->render();

$currentYear = $yf->current();  // int — but UNVALIDATED, see below
```

`current()` is a raw `(int)` cast of the query parameter. `range()` and `years()`
only build the chips; they do not constrain it. `?year=abc` hands you `0` and
`?year=1999` hands you `1999` — the report comes back empty, no chip is active,
and nothing warns you. Clamp it against your own list before it reaches a query:

```php
$raw = (string) ($_GET['year'] ?? '');                       // check the string first
$currentYear = ctype_digit($raw) ? (int) $raw : (int) date('Y');
$years = range(2022, (int)date('Y'));
if (!in_array($currentYear, $years, true)) $currentYear = (int)date('Y');
```

Validate the raw string **before** the cast, not the int after it — `(int) 'abc'`
is `0`, indistinguishable from a real `0`.

With `allOption()` set, `0` is the legitimate "all years" value — allow it too,
but only on top of the `ctype_digit()` guard above:
`if ($currentYear !== 0 && !in_array(...))`. Casting first and then exempting `0`
reopens the exact hole the clamp closes: `?year=abc` becomes `0`, passes as
"all years", and silently widens the report to every year instead of falling
back to the current one.

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

### TableHeader (since v1.10.0)

**Who owns the search — read this before you use both.** `Table::search([…])`
and `TableHeader::search(…)` are two different things, and using both puts two
boxes on the page, only one of which works:

| You are building | Use | Not |
|---|---|---|
| a `Table` with `setData()` or `rows()` | `->search(['col', …])` on the Table | `TableHeader::search()` |
| your own `<table>`, or a live container | `TableHeader::search($name, $value, …)` | — |

`TableHeader::search()` renders an input bound to nothing unless you give it
`['live' => 'container-id']` or wrap it in your own `<form>`. It does not know
about a `Table` and cannot filter one. Earlier versions of this file called
TableHeader "required for every table page"; it is not, and a `Table` that
declares its own `search()` needs no TableHeader at all.

The single source of truth for filter/search bars above tables you build
yourself. Three fixed sections in this exact order:

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
- `advanced(\Closure $renderer, string $summary = '', bool $open = false)` — an
  empty `$summary` takes the translated default ("Advanced filters" /
  "Erweiterte Filter"). Pass one only to override it.
- `reset(string $baseUrl, string $label = '')` — an empty label uses the translation

CSS classes (all auto-applied): `gk-tableheader`, `gk-tableheader-status`, `gk-tableheader-toolbar`, `gk-tableheader-advanced`, `gk-tableheader-spacer`.

**Do NOT** build your own filter row with raw `gk-toolbar` / `gk-toolbar-stacked` if `TableHeader` fits — every table page must use this for visual consistency.

### StatCards

```php
(new StatCards('stats-id'))
    ->card('Revenue',  12450.80, ['format' => 'currency', 'icon' => 'euro',   'color' => 'primary', 'trend' => '+12%'])
    ->card('Users',    1284,     ['format' => 'number',   'icon' => 'people', 'color' => 'success', 'trend' => '+3.1%'])
    ->card('Errors',   3,        ['format' => 'number',   'icon' => 'error',  'color' => 'danger', 'highlight' => true])
    ->card('Rate',     78,       ['format' => 'percent',  'icon' => 'speed',  'color' => 'warning'])
    ->card('Details',  '/url',   ['icon' => 'arrow_forward', 'href' => '/url'])  // clickable
    ->render();
```

**Colors:** `primary`, `success`, `danger`, `warning`, `info`
**Formats:** `currency`, `number`, `percent` — each follows the active locale,
so `12450.80` is `€12,450.80` under `en` and `12.450,80 €` under `de`.

**`trend`** is printed verbatim, exactly as you pass it — GridKit does no
rounding, no sign and no percent sign of its own. A leading `-` colours it as a
fall, anything else as a rise. So pass a finished string: `'+12%'`, `'-0.4%'`,
`'▲ 3'`. Passing a raw float gives you a bare `-8` in the card.

### Modal

```php
// Nothing to place in the layout: GK.modal.open() creates its own overlay and
// appends it to <body>. (Modal::container() still exists and emits nothing —
// it printed an empty shell nobody read until 1.42.0 retired it.)

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
    ->ajax()                    // REQUIRED for AJAX — without it the form does a native POST
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

**Field options** (the 4th argument of `field()`):

| Option | Applies to | Effect |
|---|---|---|
| `width` | every field | columns out of 16 (default `16`); also `'auto'` or `'220px'` |
| `required` | every field | red star + browser validation |
| `value` | every field | pre-fill / pre-select. `multiselect` takes an array (or a comma string) and pre-checks the chips; a truthy value checks `checkbox`/`toggle`; `ajaxselect` needs `'displayValue' => 'Acme GmbH'` as well, or the box shows the id-less search field |
| `placeholder` | input types, `select`, `multiselect`, `ajaxselect` | the input's placeholder — on `select` the empty-state text, on `multiselect`/`ajaxselect` the search box's. `textarea` and `richtext` are not in that list: they drop it silently, and `rows` is `textarea`'s only extra option |
| `options` | `select`, `multiselect`, `radio` | `value => label` map |
| `rows` | `textarea` | height in rows (default `3`) |
| `min`, `max`, `step` | `range`, `number`, `date`/`time` | bounds (`range` defaults to `0`/`100`/`1` and starts at `min`) |
| `->cancel($label, $href)` | — | a link beside the submit button — a method, not a field |
| `->hidden($name, $value)` | — | a hidden input — a method, not a field |
| `error` | every field | a validation message, rendered red under the field. This is how a classic POST-redisplay shows errors: `['value' => $_POST['email'] ?? '', 'error' => $errors['email'] ?? '']`. The AJAX handler writes into the same slot, so both paths look identical |

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

**`->ajax()` is the opt-in.** It is what renders `data-gk-ajax` on the `<form>`,
and `GK.form.bind()` binds the submit handler to nothing else. Leave it off and
the form still renders and still validates, but the browser submits it natively
and navigates to the action URL — the JSON below is then shown as a raw page.

**Form endpoint must return JSON:**
```php
echo json_encode(['ok' => true]);                          // success
echo json_encode(['ok' => true, 'message' => 'Saved!']);  // with toast
echo json_encode(['ok' => false, 'errors' => ['email' => 'Already exists']]);  // validation
```

### Auth

**Accounts live in a file, not in your code.** There is no array, DSN or
callback way to register users — `Auth::users([...])` and friends do not exist,
and calling one is a fatal error. The only knob is which file to read:

```php
Auth::setUsersFile(__DIR__ . '/users.conf');   // default: /etc/gridkit-users.conf
```

One account per line, `username:bcrypt-hash`; `#` starts a comment. The file
stores nothing else — no display name, no role, no e-mail. `Auth::user()`
returns the username string and that is the whole identity GridKit has.

```
# users.conf — generate hashes with Auth::hashPassword('secret')
jane:$2y$12$37brFYi./gIWudvG263/x.TjcGi0cAE/RfrL2KAAlpcgUuHtlPiDq
```

The whole surface — six calls:

- `Auth::protect(string $loginUrl = 'login.php'): void` — guard a page. Redirects
  and exits when nobody is signed in, remembering where they were headed.
- `Auth::login(string $username, string $password, bool $remember = false): bool`
  — `$remember` sets a 30-day cookie. There is no `Auth::attempt()`.
- `Auth::check(): bool` — signed in? No redirect.
- `Auth::user(): ?string` — the username, or `null`.
- `Auth::logout(string $redirectTo = 'login.php'): void`
- `Auth::hashPassword(string $password): string` — bcrypt, cost 12. This is what
  you write into `users.conf`.

Plus `Auth::renderLogin(array $opts = [])`, which PRINTS a complete login page —
its own `<html>`, its own stylesheet. Give it `['error' => '…']` after a failed
attempt and `['action' => '…']` if the form should post somewhere other than the
current URL.

There is no `Auth::attempt()` — the login call is `login()`, and its full
signature is `login(string $username, string $password, bool $remember = false): bool`.

`Auth::renderLogin([...])` PRINTS a complete standalone login page — call it as
a statement, never echo it. Options: `error`, `title`, `subtitle`, `icon`,
`action`, `cssPath`, `jsPath`, `footer`.

### Theme

```php
Theme::set('indigo', 'light');  // themes: indigo, ocean, forest, rose, amber, slate
echo Theme::switcher();          // RETURNS the switcher HTML — must be echoed
```

### The small helpers

```php
Theme::available();          // ['indigo' => ['name' => …, 'color' => '#…'], …]
                             // — build your own switcher from this
Icon::has('receipt_long');   // does GridKit ship an inline SVG for it?
                             // false means Icon::svg() falls back to the font
echo ActionGroup::html([              // the string form of ActionGroup::render()
    ['label' => 'Edit',   'href' => '/edit/1', 'icon' => 'edit'],
    ['label' => 'Delete', 'onclick' => 'del(1)', 'icon' => 'delete', 'color' => 'danger'],
]);
```

### Layout

```php
Layout::mode('header-first');   // header-first (default) | sidebar-first
Layout::getMode();              // the active one
echo Layout::asset('css/gridkit.css');   // a cache-busted URL — see the skeleton
echo Layout::version();         // the VERSION file, e.g. '1.48.0'
echo Layout::bodyTag('gk-root');   // <body> with the layout AND theme attributes
echo Layout::attributes();        // just data-gk-layout, no tag
```

`header-first` puts the header across the full width with the sidebar beneath
it; `sidebar-first` gives the sidebar the full height and starts the header
beside it.

Mind which `bodyTag()` you call: **`Layout::bodyTag()` emits both sets** —
`data-gk-layout`, `data-gk-theme` and `data-gk-mode` — while `Theme::bodyTag()`
emits only the theme pair. Use `Layout::bodyTag()` on a page that sets a layout
mode, or the sidebar-first arrangement is chosen and never applied. Their
`attributes()` are narrow in the same way: each gives back only its own.

### Lang

```php
Lang::set('en');     // set the locale: 'en' | 'de'
echo Lang::jsConfig();   // MUST be in <head> before gridkit.js — sets window.GK_LANG
```

`Lang` translates **GridKit's own** interface: the search placeholder, the empty
state, the pager, the confirm dialog, the row-action names. `Lang::set()` plus
`Lang::jsConfig()` is all it needs — every built-in string then follows, on the
server and in the browser.

**Translating your own strings — put them in the same catalogue.** `Lang` holds
GridKit's interface strings, and it will hold yours beside them. Asking for a
key nobody registered returns the key itself, silently, so
`Lang::t('paid')` prints `paid` until you load a `paid`.

```php
Lang::loadDir(__DIR__ . '/lang');   // every en.php / de.php in that directory
Lang::loadFile(__DIR__ . '/lang/en.php');          // just one
Lang::load('en', ['app.title' => 'Invoices']);     // or an array, inline

Lang::set($_GET['lang'] ?? 'en');
echo Lang::t('app.title');           // yours
echo Lang::t('table.search');        // GridKit's — still there
```

Each file returns a `key => string` array and is named for its locale
(`lang/en.php`, `lang/de.php`). Loading merges rather than replaces, so
GridKit's own strings survive; prefix yours (`app.`, or your module's name) and
nothing can collide. `Lang::locale()` gives the active one back.

**`Lang::jsConfig()` ships only the `js.*` and `action.*` keys.** It is a filter,
not a dump of the catalogue: `js.` keys reach `window.GK_LANG` with that prefix
stripped, `action.foo` arrives as `action_foo`, and every other key — your
`app.*` included — stays server-side. So a string you also need in JavaScript
takes a `js.` prefix on top of your own:

```php
Lang::load('en', ['js.app.toast.sent' => 'Sent to {name}.']);

echo Lang::t('js.app.toast.sent', ['name' => 'Jane']);   // server: "Sent to Jane."
// browser, same string, prefix stripped:
//   GK.t('app.toast.sent', {name: 'Jane'})
```

Register it as plain `app.toast.sent` and `GK.t('app.toast.sent')` prints the raw
key — no error, no warning, in every locale. Strings you only ever render on the
server need no prefix.

There is no need for a `$t()` closure over an array of your own — that is the
workaround people write when they have not found `loadDir()`, and it costs you
the `{placeholder}` substitution that `Lang::t()` does for free.

`format => 'currency'` and `format => 'date'` localise on their own from
`Lang::set()` — `€1,240.00` and `Mar 12, 2026` under `en`, `1.240,00 €` and
`12.03.2026` under `de`. You do not translate those yourself.

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

## Filters forget each other unless you say otherwise

Four components build their own URLs — `Pagination`, `PageSize`, `FilterChips`
and `YearFilter` — and each one rebuilds it from its base plus its **own**
parameter. Everything else on the page is dropped, with no error and nothing in
the console. On a report with a year, a status and a search, changing the row
count sends you back to an unfiltered newest-year view, and it looks like a
feature nobody finished.

Tell each of them what to keep:

```php
->preserve(['year', 'status', 'q'])        // names — values read from $_GET
->preserve(['year' => $year, 'q' => $q])   // or a name => value map
Pagination::render([..., 'params' => ['year' => $year, 'q' => $q]]);
```

`Pagination` passes its own `baseUrl` and `params` down to a nested
`pageSize`, so those two agree by themselves. The other two you tell yourself.
A page that has exactly one filter needs none of this; a page with two needs all
of it.

### Pagination + PageSize (since 1.22 / 1.27)

Server-side pager **below** `.gk-table-wrap`, not inside the card and not
and not inside the live container. Same look as `GK.rowPager`
(`.gk-rowpager` / `.gk-pg`).

**The two URL parameters you read yourself.** This pager does *not* use the
`gk_*` convention of `Table`: the page links are `?…&page=N` and the PageSize
select carries `per_page`. Reading `gk_page` here leaves the page stuck on 1
with no error.

```php
$page    = max(1, (int) ($_GET['page'] ?? 1));   // NOT gk_page
$perPage = PageSize::make()->resolve(25);        // $_GET['per_page'], checked against the options
```

Rename either with `'pageParam' => 'p'` on `Pagination` and
`'pageSize' => ['param' => 'rows']` / `PageSize::make('rows')`.

**`Pagination::render(array $o)` is the one to use.** GridKit ships no paginator
class, so you hand it plain numbers. `page` and `totalPages` are the two keys
that build the link list — miss either and you silently get the count bar with
no page links at all.

```php
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 25;
$total = 148;
$year = (int) ($_GET['year'] ?? date('Y'));
$q = trim((string) ($_GET['q'] ?? ''));

// The page, on first render — a sibling below the table, not inside it.
Pagination::render([
    'page'       => $page,                            // 1-based
    'totalPages' => (int) ceil($total / $perPage),    // NOT 'pages'/'last'/'pageCount'
    'total'      => $total,                           // the count in the bar
    'label'      => 'Expenses',                       // what the count counts
    'params'     => ['year' => $year, 'q' => $q ?: null, 'per_page' => $perPage],  // kept on every link
    'pageParam'  => 'page',                           // the query key, default 'page'
    'baseUrl'    => '/expenses',                      // default: the current path
    'live'       => 'exp-live',                       // binds AJAX clicks + replace target
    'pageSize'   => ['current' => $perPage, 'options' => [10, 25, 50]],
]);
```

The nested `pageSize` inherits the pager's own `baseUrl` and `params`, so the
rows-per-page select keeps the same filters every page link keeps. (Until
1.47.0 it did not, and changing rows per page threw the year filter and the
sort away without a word.) The one parameter it deliberately drops is `page` —
in both live and navigate mode, and even if you list it in `preserve()` — so a
new row count always lands back on page 1.

That inheritance runs **one way only**. Nothing feeds the row count back into
the page links, so `per_page` (or whatever you named it) has to be listed in
`params` by hand — as above. Leave it out and the links come out
`?year=2025&page=2` with no row count: clicking page 2 snaps the table back to
the `resolve()` default and the select then shows that default as if the user
had picked it. No error either way.

`Pagination::fromPaginator(object $p, array $o = [])` exists for applications
that already have a paginator object carrying `currentPage()`, `totalPages()`
and `total()` — it duck-types those three. **GridKit does not ship such a
class**, and handing it an array is a `TypeError`, so reach for `render()`
unless you have one.

```php
// In the live partial (AJAX), so the counter and page list follow the filter:
<template data-gk-replace="[data-gk-pager=exp-live]">
<?php Pagination::render([/* the same options */]); ?>
</template>
```

`PageSize` on its own, outside a `Pagination`:

```php
PageSize::make('per_page')          // the query parameter
    ->current(25)                   // what is selected now
    ->options([10, 25, 50, 100])    // default: 10/25/50/100
    ->baseUrl('/expenses')          // default: the current path
    ->preserve(['year', 'sort'])    // names, read from $_GET …
    ->preserve(['year' => 2024])    // … or a name => value map
    ->live('exp-live')              // AJAX instead of a full navigation
    ->render();
```

Without `preserve()` the select rebuilds the URL from the base alone, so every
other filter on the page is dropped when somebody changes the row count.

Short lists with no server-side LIMIT: put `data-gk-rows="25"` on the table and
the client-side `GK.rowPager` builds the same bar.

`PageSize` on its own is the one printer in that table you do **not** call
statically: build it fluently, then `render()`.

```php
// Live mode — bound to a data-gk-live-table container:
PageSize::make('per_page')->current($perPage)->options([25, 50, 100])
    ->live('exp-live')->label('Rows')->render();

// Navigate mode — full reload, keeping the listed $_GET keys:
PageSize::make('per_page')->current($perPage)
    ->baseUrl('/expenses')->preserve(['year', 'sort', 'dir', 'lang'])->render();

// In the controller: the chosen value, checked against the options whitelist.
$perPage = PageSize::make('per_page')->options([25, 50, 100])->resolve(25);
```

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
