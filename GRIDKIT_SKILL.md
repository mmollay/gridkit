# GridKit – Agent Skill

> **Version:** 1.107.0 | **License:** MIT | **Repository:** https://github.com/mmollay/gridkit
> **Demo:** https://gridkit.at

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

A new class goes into `css/blocks.json` with the version that brings it, under
the block it belongs to — the index the demo reads its "since" and "New in"
marks from. The suite fails on a class that is in a stylesheet and not there,
and on a VERSION the changelog has no heading for. To ask whether your copy has
a block: `css/blocks.json` names the release that first shipped each class.

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
| Tabs (JS) | `.gk-tabs` / `[data-gk-tabs]` | Tab navigation, two shapes — full tablist semantics and arrow keys since 1.69.0; the Karteireiter shape `.gk-tab-nav-karte` + `.gk-tab-flaeche`, nesting and a phone grid since 1.101.0; a row that does not fit its box becomes the grid by itself (`GK.tabsFit`) and `gk-tab-badge-error` since 1.102.0; line tabs for the second row, `--gk-tab-radius` and link tabs that keep their place (`GK.tabsScroll`) since 1.102.0; three more styles (line, pill, text), four alignments (left, right, bottom, fixed bar) and options (size, full width, symbol above or only, neutral, disabled) as classes on the row since 1.103.0 |
| Accordion (JS) | `.gk-accordion` | Collapsible sections, optional single-open (`data-gk-single`) |
| Tooltips (JS/CSS) | `title` / `data-gk-tooltip` / `data-gk-tooltip-rich` | Hint popups — plain, CSS-only, or with HTML in them |
| Gallery + Lightbox (JS) | `.gk-gallery` / `GK.lightbox` | Image grid with lazy loading and a keyboard-operable viewer |
| Image picker | `GridKit\ImagePicker` / `GK.picker` | Choose one image out of many: a compact grid of 16:9 tiles that works like a radio group, folds after two rows, and an enlarged view that can pick (since 1.94.0) |
| Announcement (JS/CSS) | `.gk-announce` / `GK.announce` | The line that says "Saved." where the person works, read by a screen reader; empty it draws no box (since 1.93.0) |
| Timeline | `.gk-timeline` | A log of steps, one under the other: a real ordered list, each step with a symbol and a word for its state (ok · failed · running · waiting · skipped), title, time, small chips, a detail and — when it broke — a message block (since 1.96.0) |
| Portrait video frame | `.gk-video-frame` | A 9:16 frame for a video or picture: never wider than its column or taller than the window, optional guide lines for the zones a platform covers, an empty "being made" state and a failed state, a caption (since 1.96.0) |
| Sortable container (JS) | `[data-gk-sortierbar]` / `js/sortierbar.js` | Cards or rows a person puts in order by hand: a handle in every entry (from two entries on), dragging with mouse or finger, Space and arrow keys, a spoken position, the order posted as JSON when there is a `url`, and a sentence beside a symbol when saving failed (since 1.97.0) |
| Side sheet (JS) | `.gk-sheet` / `GK.sheet` | A panel a row opens: docked right beside the list, full screen and modal on a phone (since 1.91.0) |
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

`Theme::density('konsole')` sets how tightly tables are set — `konsole` fits
about a third more rows on a screen, `weit` gives them room, and the default
leaves the rhythm alone. It names no colour, so it composes with every theme
and both modes; an unknown value is ignored.
| `Icon::svg()` | `Select::searchable()` | `Layout::asset()` |
| `Lang::jsConfig()` | `Pagination::build()` | `Layout::skipLink()` |

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
Theme::density('konsole');          // '' (default) | konsole (tight) | weit (roomy)
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
collapses; `gk-main` carries the padding — 24px 28px 48px, 16px at the edge of a
phone — and caps the content at `--gk-main-max` (1680px; set it on the page to
change it). Until 1.92.0 the class was documented here and had no rule, so every
page wrote that padding itself. `skeleton.php` in the repository is this file,
filled in.

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

## Six rules for lists

Read these before you build a list. They come from two admin lists that were
built with GridKit and turned down (since 1.91.0): a user list of 21 rows had
nine columns, two selects, a switch and a label in every row, rows 100px tall,
and its actions scrolled off the right at 1280px. The approved redesign has six
columns and 64px rows — an avatar, the name at 15px semibold over the address
at 13px, one coloured count — and a side sheet for everything that changes.

1. **One state, one sign.** A switch shows its state itself. A label beside it
   saying "allowed" says it again, once per row. In the list, group by the
   state; the switch lives in the side sheet, with one sentence on what it does.
   Building block: `->groupBy()` with `.gk-table-group`; `.gk-toggle` with no `.gk-label` beside it, in a `.gk-setting` row (a Form toggle with a hint writes it).
2. **Read in the row, change in the sheet.** A row shows values as text. Selects,
   switches and delete belong in the side sheet the row opens — and a click
   anywhere on the row opens it, not only on its name.
   Building block: `->rowLink()`, i.e. `tr.gk-row-link` + `.gk-row-target`; `.gk-sheet` opened by `GK.sheet.open()`.
3. **Who: one cell, two lines.** Name and address belong together: an avatar,
   the name, the address underneath. In two columns both break mid-word. A
   `Table` column writes the two lines with `'sub' => 'email'`; with a label
   after the name, `.gk-cell-who` moves the label under the name instead of
   shortening it.
   Building block: `.gk-cell-who` with `.gk-avatar` + `.gk-cell-sub`.
4. **Colour only for what needs acting on.** Red means something is broken.
   Counts that are fine stay neutral text, and the breakdown goes in the sheet
   rather than in three coloured numbers per row.
   Building block: `.gk-label-red` for the one count that needs attention; `.gk-stat-tile-danger` in the sheet.
5. **Group instead of scrolling.** At most six columns at 1280px; nothing
   scrolls sideways. What is rarely needed folds into one row that stands for
   many — and records that are not like the others (profiles among users) are
   such a group. When the list itself gets narrow — a docked sheet, a
   phone — the least needed columns give way, by the list's width, not the
   window's.
   Building block: `.gk-table-group`; `tr.gk-table-more` with a `.gk-table-more-toggle`; `.gk-col-p2` … `.gk-col-p5` (a column's priority).
6. **Running text is never bold and never narrow.** A sentence in a list is set
   at normal weight and gets the width it needs (`minWidth` on its column); a
   second line of detail is quiet text, not a second column.
   Building block: `.gk-table` cells at weight 400 — add no bold; `.gk-cell-sub` for the second line.

The row the rules were drawn from, measured: 64px tall, the whole row the click
target (never under 44px), name 15/13px, at most six columns at 1280px, one
colour — for what needs acting on. Since 1.92.0 that row is a class:
`.gk-table-list` on the wrap (`->size('list')`), so a list needs no CSS of its
own.

A list that follows all six, by hand — a `Table` writes the same row with
`->size('list')`, `->rowLink(['sheet' => 'user-sheet'])`, `'sub' => 'email'` and
`'priority'` on the columns that may give way:

```html
<div class="gk-table-wrap gk-table-list">
<table class="gk-table">
  <thead><tr><th scope="col">User</th><th scope="col" class="gk-col-p3">Plan</th><th scope="col">Last 30 days</th></tr></thead>
  <tbody>
    <tr class="gk-table-group"><td colspan="3">
      <span class="gk-table-group-name">With access</span><span class="gk-table-group-n">5</span>
    </td></tr>
    <tr class="gk-row-link">
      <td data-label="User">
        <div class="gk-cell-who">
          <span class="gk-avatar gk-avatar-sm gk-avatar-initials gk-avatar-tone-2" aria-hidden="true">JN</span>
          <div class="gk-cell-who-text">
            <button type="button" class="gk-row-target" data-gk-sheet="user-sheet"
                    data-gk-params='{"id":2}' aria-haspopup="dialog">Jana Novak</button>
            <span class="gk-cell-sub gk-cell-sub-wrap">jana@example.com</span>
          </div>
        </div>
      </td>
      <td data-label="Plan" class="gk-col-p3">Life book</td>
      <td data-label="Last 30 days"><span class="gk-label gk-label-red">17 failing</span></td>
    </tr>
    <tr class="gk-table-more"><td colspan="3">
      <button type="button" class="gk-table-more-toggle" aria-expanded="false" aria-controls="profiles">
        <span class="gk-table-more-name">13 chart profiles</span>
        <span class="gk-cell-sub">Created from charts — they cannot sign in.</span>
      </button>
    </td></tr>
  </tbody>
  <tbody id="profiles" hidden>
    <!-- the 13 rows, right after the row that stands for them -->
  </tbody>
</table>
</div>
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
    ->caption('Customers')                    // what it is a table of — for screen readers only
    ->render();
```

Give every table a `caption()` when a page has more than one: the heading above a table is
not associated with it, and without a caption a screen reader announces "table" twice.

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

**A second line inside a cell** (subject, account, reference) — never widens the
column: `->column('name', 'Customer', ['sub' => 'city'])`, see below. By hand only
where the truncated text needs a `title`, which the option does not write:

```html
<div class="gk-cell-sub" title="Full text">Your receipt from Anthropic…</div>
```

**Column `href` and `sub` (since 1.87.0):** the two commonest cell shapes, without
building HTML by hand.

```php
->column('name', 'Customer', ['href' => '/customers/{id}', 'sub' => 'city'])
```

`href` wraps the formatted value in `<a class="gk-cell-link">`; `{field}` comes
from the row, URL-encoded, and the same targets are allowed as for a row button.
`{field}` is a path segment, never a whole target — a scheme belongs in the
template, so a stored `https://…` cannot be linked this way. A cell that shows
nothing gets no link. `href` and `'format' => 'html'` exclude each other: with
html the caller writes the markup, links included, and wrapping that would nest
anchors — GridKit leaves the link out and says so.

`sub` names a second field, rendered as text under the value in
`<div class="gk-cell-sub">` — always text, whatever the cell's format is, and
left out when empty. It truncates with an ellipsis and carries no `title`; where
the full text has to stay reachable, build that cell by hand. In a `setData()`
table a column declared in `search()` searches its second line too, so a row can
be found by the text standing under its name; in a `query()` table name the field
in `search()` yourself — it has to be a real SQL column there.

Both keep the raw value, so sorting and searching go on working — which is what
`'format' => 'html'` costs you. There is no `target`: a link opens in the same
tab, and middle click does the rest.

**`->groupBy($column, $labels)`:** inserts a group row whenever the value changes. Sort the rows by that column first.

**`->rowLink($target)` (since 1.91.0):** the whole row opens something — a page, or
a side sheet on this page. Use it instead of putting selects, switches or a delete
button into every row (see *Six rules for lists*).

```php
->rowLink('/users/{id}')                                   // the row opens a page
->rowLink(['sheet' => 'user-sheet'])                       // … the side sheet #user-sheet
->rowLink(['sheet' => 'user-sheet', 'url' => 'panels/user.php', 'params' => ['uid' => 'id']])
->rowLink(['href' => '/users/{id}', 'column' => 'name'])   // which cell carries the control
```

The row is not made a control — `role=link` or a `tabindex` on a `<tr>` takes away
its row role. Its main cell (the first column, or `'column'`) carries ONE real
control, `<a class="gk-row-target">` or `<button class="gk-row-target" data-gk-sheet>`,
and a click anywhere else in the row is forwarded to it, with its modifier keys:
Ctrl-click and a middle click open a link row in a new tab. The keyboard and a
screen reader use the control itself; without JavaScript a link still works.
Clicks on a control of its own — a checkbox, a row button, a cell link — and the
end of a text selection stay theirs. The row is at least 44px tall and shows a
chevron.

`href` follows the rules of a row button's `href`: `{field}` URL-encoded, the
same allow list of targets. A sheet row posts `params` (mapped like a row
button's, always with the row's `id`) to `url` and fills the sheet's body with the
answer — or, without `url`, hands them to `gk:sheetopen` for your own code. The
shown value becomes the sheet's title. A row whose main cell shows nothing, or
whose target is not allowed, stays a plain row. The main cell cannot be
`'format' => 'html'` or `'email'` (both write a link of their own) and loses its
own `href` — GridKit says so with a warning.

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

**Button `href`:** `->button('open', ['icon' => 'visibility', 'href' => '/users/{id}'])`
renders a real link (`<a class="gk-btn …">`) instead of a button, so middle click,
"open in new tab" and the browser's status bar work. `{field}` is replaced from the
row and URL-encoded. Allowed targets are a relative path, a fragment, a query, or a
spelt-out `http`, `https`, `mailto` or `tel`; anything else — including a leading
`//` — falls back to a plain button. A row's own value can never become a scheme.
A link fires no `gk:rowaction`. With `confirm` it is deliberately NOT a link but a
button carrying its target, so that a middle click cannot walk past the question;
it goes only after the answer. `modal` and `onclick` win over `href`: a button that
already does something keeps doing it. Use `href` instead of
`'onclick' => 'location.href=…'`.

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

**`hideIf`:** the opposite: `->button('edit', ['icon' => 'edit', 'hideIf' => 'archived'])` leaves the
button out when the row's `archived` value is truthy.

**Column options** (the 3rd argument of `column()`):

| Option | Effect |
|---|---|
| `sortable` | the header becomes a sort button (`aria-sort`) |
| `width`, `minWidth`, `maxWidth` | CSS lengths on header and cells (`'120px'`, `'20%'`); `width` also takes `'auto'` |
| `nowrap` | the cell never wraps |
| `align` | `'left'`, `'center'` or `'right'`; `number` and `currency` columns are right-aligned without it |
| `muted` | quieter text colour for secondary columns (numbers, identifiers) |
| `hideOnMobile` | header and cells are gone at 768px and below |
| `priority` | `2` to `5`, how early the column gives way in a `size('list')` table (see below) |
| `format` | `currency`, `number`, `percent`, `date`, `datetime`, `boolean`, `email`, `label`, `html` |
| `decimals` | digits after the decimal sign for `number` and `percent` |
| `blankZero` | `number` only: `0`, an empty value and `null` show an em dash; `false` shows the `0` |
| `currency` | `currency` only: the pattern with the amount at `{value}`, e.g. `'{value} CHF'` (default from the locale) |
| `dateFormat` | `date` and `datetime`: a PHP `date()` format, e.g. `'d.m.Y'` (default from the locale) |
| `labels` | `label` only: `'value' => 'green'`, or `'value' => ['color' => 'green', 'text' => 'Paid']` |
| `href`, `sub` | the cell links to `'/customers/{id}'`; `sub` names a row key shown as a quiet second line |
| `export` | `false` leaves the column out of the CSV file of `exportCsv()` |
| `hidden` | `true` starts the column hidden in a table with `columnToggle()`; the user's stored choice wins |
| `toggle` | `false` keeps the column on screen: in the `columnToggle()` menu, ticked and disabled |

**`->selectable('id')`:** checkbox column, a select-all in the header, and a bulk bar. Deleting fires `gk:bulkdelete` with `{ ids, tableId }` — the application does the deleting. Every change fires `gk:selectionchange` with `{ ids, tableId, count }`, bulk bar or not. Shift-click selects a range. Without the `Table` class: `data-gk-table` + `data-gk-selectable`, rows carrying `data-gk-row-id` and a `td.gk-cb-col`. A row without `data-gk-row-id` cannot be selected. Select-all covers the visible rows only. After a live reload (`gk-live-reloaded`) GridKit re-binds the table.

**`->bulkAction($name, $opts)` (since 1.105.0):** one more button in the bulk bar, beside the delete
button. Clicking it fires `gk:bulkaction` on the table with `{ action, ids, tableId }` — the shape
`gk:bulkdelete` has — and the application decides what it does. It turns `selectable()` on when it is
not on yet. Options: `label` (default: the catalogue's `action.<name>`, else the name), `icon`,
`confirm` (`true` asks the standard question, a string asks that one first) and `color` (`'danger'`
makes the question a danger one). Needs `js/gridkit.js` 1.105.0; an older copy shows the bar without it.

```php
(new Table('invoices'))->setData($rows)
    ->column('number', 'No.')
    ->bulkAction('archive', ['icon' => 'archive', 'label' => 'Archive'])
    ->bulkAction('void',    ['icon' => 'block', 'confirm' => 'Void these invoices?', 'color' => 'danger'])
    ->render();
// document.addEventListener('gk:bulkaction', e => { e.detail.action, e.detail.ids });
```

**`->exportCsv($filename, $opts)` (since 1.105.0):** a button in the toolbar that downloads the table as
CSV. A `setData()` table builds the file in the browser from the rows it holds: after the search and
the filters, in the order they are sorted in, every column except those with `'export' => false`. A
`rows()` or `query()` table has no rows in the browser: the button reloads the page with `?gk_export=csv`,
and the page answers it — `Table::wantsCsv()` says so, `Table::sendCsv($rows, $columns, $filename)` sends the
headers and the file, and `exit` ends the request. `Table::csv($rows, $columns)` returns the text. The file is
RFC 4180 (CRLF, quotes where needed) in UTF-8 with a byte-order mark so Excel reads the encoding; a cell
a spreadsheet would read as a formula (`=`, `+`, `-`, `@`, a tab or a carriage return first, and not a plain
number) gets a leading apostrophe. Options: `delimiter` (`','`, or `';'` for German Excel), `bom` (`Table::csv()` only, default `true`) and `label` (the button text).

```php
(new Table('people'))->setData($rows)
    ->column('name',  'Name')
    ->column('email', 'Email')
    ->column('token', 'Token', ['export' => false])    // never in the file
    ->exportCsv('people.csv', ['delimiter' => ';'])
    ->render();

// A server-driven table: the page answers the button itself.
if (Table::wantsCsv()) {
    Table::sendCsv($repo->all($_GET), ['name' => 'Name', 'email' => 'Email'], 'people.csv');
    exit;
}
```

**`->columnToggle()` (since 1.108.0):** a "Columns" button in the toolbar. Its menu has one checkbox
per column; unticking one hides the column — its header, its cells and a footer cell that stands
alone over it — and a group heading, the empty state and a footer cell over several columns get
narrower. The browser remembers the choice per table id (`localStorage` `gk-cols-<id>`, only what
differs from the page's defaults; storage that is missing or throws remembers nothing). Column
options: `'hidden' => true` starts the column hidden (a stored choice wins), `'toggle' => false` keeps
it on screen — it stays in the menu, ticked and disabled, so the list still reads as the table's
columns. The `rowLink()` column is kept the same way (it carries the row's only control), and the
last column shown cannot be unticked.

```php
(new Table('people'))->setData($rows)
    ->column('name',  'Name',  ['toggle' => false])     // always shown
    ->column('email', 'Email')
    ->column('role',  'Role',  ['hidden' => true])      // hidden until the user shows it
    ->columnToggle()
    ->render();
```

The rows are written as before: the hiding is CSS keyed on the menu's checkboxes, so a sort, a
search, a page change and the AJAX reload of a `rows()`/`query()` table keep it, and a `'hidden'`
column is hidden in the server's markup — no flash before the script runs. The menu is a native
popover: Escape and a click elsewhere close it, the focus goes into it and back to the button, and
`aria-expanded` follows. Without `js/gridkit.js` 1.108.0 (an older copy, or no script) the menu still
opens and its checkboxes still work, but nothing is remembered. Search and sort do not change: the
search looks at its keys whether their columns show or not. **The CSV holds what the table shows:**
a `setData()` table leaves hidden columns out of the file (and `'export' => false` ones, as before);
a server table's button adds `?gk_hidden=email,role`, and `Table::sendCsv()` leaves those keys out of
the `$columns` it is given. On a phone a hidden column is left out of the card, and when it was the
card's title the next column that is not muted takes the title. `'hidden'` and `'toggle'` take `true`
or `false`; anything else, `'hidden'` without `columnToggle()`, on a `'toggle' => false` column or on
the `rowLink()` column, and `columnToggle()` with `toolbar(false)` raise a warning, and the column shows.

**`->emptyState($title, $opts)`** sets the wording for a table with no rows —
`['hint' => …, 'icon' => …, 'action' => …]`. GridKit works out by itself whether
the table is genuinely empty or only filtered, and offers the way back in the
second case, so only describe the first.

**Shaping the table.** All chainable, all optional:

```php
->toolbar(false)     // no search/filter bar above the table at all
->searchable(false)  // keep the toolbar, drop the search box
->size('sm')         // sm | md (default) | lg — row height and padding;
                     // list — the row of the six rules, see below
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
->stickyHead()       // the header row stays in view; stickyHead('70vh'): in a box that high
->stickyColumn()     // the first column stays in view when the table scrolls sideways
```

**Card mode (the default below 768px, except for a list).** Each row becomes a
card. Since 1.106.0 its first line is a head — checkbox, left buttons, title,
right buttons, side by side — and the other fields follow as label and value
without rules. The **title is the first column that is not `'muted'`** and not
`'hideOnMobile'` (nor hidden with `columnToggle()`); on a row with `rowLink()` it is the cell with the link; when
every column is muted, the first one. Order the columns, or mark a number column
`'muted'`, to choose it. A long title wraps. A card with nothing but its head has
no rule under it. Group headings, the summary row, the empty state and the footer
are not cards of fields and keep their layout. CSS only: no class, no markup.

**`->stickyHead($maxHeight)` and `->stickyColumn()` (since 1.107.0).** A long list keeps its
header row in view, a wide one its first column:

```php
(new Table('invoices'))->setData($rows)
    ->selectable('id')
    ->column('customer', 'Customer')        // the first column: it sticks
    ->column('number', 'No.')
    // … many more columns
    ->stickyHead()                          // the header row sticks to the page
    ->stickyColumn()                        // the checkbox and the first column stick sideways
    ->render();
```

- `stickyHead()` without an argument sticks the header row to the top of the page — or of
  whatever scrolls around the table. Under a fixed or sticky GridKit header it stops at 56px
  (`--gk-sheet-top`); set `--gk-table-sticky-top` on the wrap or higher up for another offset.
  It needs an uninterrupted way up: an ancestor with `overflow: hidden` or `auto` (a
  `.gk-table-card`, a container of your own) is the scroller then, and the head sticks to
  that. On a phone a scrolling table (`mobile('scroll')`) scrolls in its wrap, so its head
  only sticks in a box.
- `stickyHead('70vh')` — a CSS length, a number with `px`, `vh`, `dvh`, `svh`, `lvh`, `rem`,
  `em` or `%` — puts the table in a box of at most that height, and the head sticks to the top
  of the box. Anything else raises a warning and is left out (the head sticks to the page).
  The pager stays below the box.
- `stickyColumn()` keeps the first column in view while the table scrolls sideways, and the
  cells in front of it too: the checkbox of `selectable()` and the buttons with
  `'position' => 'left'`. Scrolling sideways needs a box, so the table gets one
  (`overflow-x: auto`). A box is a scroller in both directions, so **with `stickyHead()` the
  head cannot stick to the page any more: the box gets a height, the one you gave
  `stickyHead()` or `70vh`.** While the box is scrolled sideways a hairline marks the edge of
  the column. The footer's cells stick under their columns as long as every cell before them
  is one column wide; group headings and the empty state span the row and do not stick (the
  empty state is kept in the middle of the box).
- The box is `.gk-table-sticky-box` inside `.gk-table-wrap`, written around the `<table>` only;
  the wrap carries `.gk-table-sticky-head` / `.gk-table-sticky-col`. It is a region named by
  `caption()` (else "Scrollable table") with a tab stop, so the keyboard scrolls it too. A
  sort, search, page change or AJAX reload keeps it where it was scrolled.
- Sticky cells get the surface of the table and the colour of their row (selected, striped,
  hovered, dark), so nothing shows through them. A sticky table draws its borders separate,
  not collapsed (`border-spacing: 0`) — at rest it looks the same.
- Card mode (a phone) is untouched: no box, nothing sticks. `size('list')` keeps its columns
  giving way; a list does not scroll sideways, so there only the head matters.
- The offsets of the cells after the first are measured by `js/gridkit.js` 1.107.0 (on load,
  after every rebuild, on resize). An older copy still renders the table: the head and the
  first cell stick, the cell after a checkbox at 36px, a column after left buttons stays put.

**A list: `->size('list')` and `'priority'` (since 1.92.0).** The row the six
rules describe, without CSS of your own: 64px rows, the name at 15px over the
address at 13px, a group heading without a bar, the summary row set in. The wrap
gets `.gk-table-list`. A list does not scroll sideways and does not turn into
cards on a phone — its columns give way instead, measured on the **list's own
width**, not the window's (a docked sheet takes 440px of it, a collapsed sidebar
gives some back):

```php
(new Table('users'))
    ->setData($rows)
    ->size('list')
    ->rowLink(['sheet' => 'user-sheet'])
    ->column('name',   'User',   ['sub' => 'email'])     // no priority: never goes
    ->column('role',   'Role',   ['priority' => 2])       // goes below 560px of list
    ->column('status', 'Status', ['priority' => 3])       // below 720px
    ->column('email',  'Email',  ['priority' => 5])       // first, below 1040px
    ->render();
```

`'priority'` takes 2 to 5 (5 gives way first; 1, or none, means never) and puts
`.gk-col-p2` … `.gk-col-p5` on the header and every cell of the column; anything
else raises a warning and the column always shows. The widths are the list as it
stands on the page, its 1px border included. The classes work only inside
`.gk-table-list`, which is a CSS size container — in a flex row of its own, give
it a width (`gk-flex-1`, a grid track), as any container needs one. `->mobile()`
on a list is refused with a warning: card mode would box every row and scroll
mode gives the table 600px, and either undoes what the list is for.

**The who cell** — rule 3 as markup, for a column you write with
`'format' => 'html'` or by hand: `.gk-cell-who` holds the avatar and
`.gk-cell-who-text`; in it the name comes first, a `.gk-label` after it moves
under the name when both do not fit (the name is shortened only when it does not
fit on its own), and the `.gk-cell-sub` spans the width underneath.
`.gk-cell-sub-wrap` breaks a second line instead of shortening it — of two people
with the same name, the addresses are what tells them apart. It breaks
anywhere if it must, so it never widens a phone's list; give an address its
natural break points with `<wbr>` after the `@` and before each dot, and it
breaks there first ("anna.probe@<wbr>example<wbr>.org"). In a list the who
column takes the width the others leave.

```html
<td data-label="User"><div class="gk-cell-who">
  <span class="gk-avatar gk-avatar-sm gk-avatar-initials gk-avatar-tone-2" aria-hidden="true">AP</span>
  <div class="gk-cell-who-text">
    <button type="button" class="gk-row-target" data-gk-sheet="user-sheet" data-gk-params='{"id":2}'>Anna Probe</button>
    <span class="gk-label gk-label-gray">locked</span>
    <span class="gk-cell-sub gk-cell-sub-wrap">anna@example.org</span>
  </div>
</div></td>
```

`.gk-avatar-tone-1` … `-5` give initials one of five quiet role pairs (primary,
tertiary, info and warning containers, a raised surface) — pick by the person's
id modulo 5, never by rank, so a person keeps their colour when the list is
sorted.

### Server-side tables (`rows()` + `isAjaxReload()`)

Three ways to get data into a table, in order of how much GridKit does for you:

```php
->setData($rows)              // client-side: the browser gets everything and
                              // searches, sorts and pages in JavaScript.
                              // Fine up to a few hundred rows.

->query($db, $sql)            // GridKit builds the SQL: LIKE for the search,
                              // WHERE for every declared filter, ORDER BY,
                              // COUNT and LIMIT. mysqli only.

->rows($pageRows, $total)     // Table::rows() — you ran the query. PDO, SQLite, Postgres, an
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
    'data'    => ['row' => 3],              // data-row="3": the key is written without `data-`
    'attrs'   => ['aria-current' => 'page'], // only `aria-*`, `role`, `lang`, `dir` and `tabindex`;
                                            // anything else is dropped, so a caller cannot add `onclick`
    'iconPosition' => 'right', // left (default) | right — which side of the label
    'fullWidth'    => true,    // fills the row (class gk-btn-full)
    'iconStyle'    => 'svg',   // auto (default: GridKit's own outline icon when it has the
                               // name, else the icon font) | svg | material (force the font)
]);

// A floating action button — round, fixed, bottom right.
echo Button::fab('add', ['color' => 'primary', 'extended' => true, 'label' => 'New']);

// Several buttons as one joined group. Button::group() takes finished button
// strings and returns the wrapper — it renders nothing of its own.
echo Button::group([
    Button::render('Day',  ['variant' => 'outlined', 'size' => 'sm']),
    Button::render('Week', ['variant' => 'outlined', 'size' => 'sm']),
]);

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

**A quiet line beside the title (`.gk-header-meta`, since 1.92.0)** — "2 users ·
4 never used", on the title's baseline. It is the part that gives way: shortened
with an ellipsis before the user menu is pushed off the edge, and gone at 768px
and below. `Header::title()` writes the `<h1>` only, so the line is for a header
written by hand; it takes the breadcrumb's place — use one or the other.

```html
<div class="gk-header-title"><h1>Users</h1><span class="gk-header-meta">2 users · 4 never used</span></div>
```

**A header written by hand keeps the user menu BESIDE the actions**, the way
`Header` writes it: `.gk-header-right` > `.gk-header-actions` + `.gk-header-user`.
On a phone the actions row scrolls sideways (`overflow-x: auto`, so a squeeze
cannot push buttons over the avatar), and anything that opens out of it is cut
off — a user menu written inside it could not be seen or tapped at 402px.

```html
<div class="gk-header-right">
  <div class="gk-header-actions"><button type="button" class="gk-btn gk-btn-primary gk-btn-touch">New</button></div>
  <div class="gk-header-user" data-gk-dropdown tabindex="0" role="button" aria-haspopup="true" aria-expanded="false">…</div>
</div>
```

**"You are here" (`->ortszeile()`, since 1.100.0).** A page more than one click deep
should say where it is and give a way back: the dashboard and the module as two
links (word and symbol), the steps in between, and the page last — not a link,
`aria-current="page"`, the whole title in `title`. Only the page's title is ever
shortened (an ellipsis at its end); the links and steps never are, and on a
phone nothing is hidden — the line wraps. It replaces `->breadcrumb()` when both
are given (one trail, not two).

```php
echo (new Header())
    ->title('Invoice INV-2026-001')
    ->ortszeile([
        'dashboard' => ['label' => 'Dashboard', 'href' => '/', 'icon' => 'home'],   // the defaults
        'modul'     => ['label' => 'Billing', 'href' => '/billing', 'icon' => 'receipt_long'],
        'stufen'    => ['Invoices' => '/billing/invoices'],   // optional; '' as URL = plain text
        'aktuell'   => 'Invoice INV-2026-001',                // the page itself
        'label'     => 'You are here',                        // the nav landmark's name (language default)
    ])
    ->sticky()->render();
```

On the dashboard itself leave `'modul'` out: "Dashboard" is then the current
page and no link. On a phone the line takes a row of its own under the menu
button and the user menu, so the header grows (105–181px at 390px).
A header of fixed height passes `'einzeilig' => true` instead: the line then
never wraps — the two anchors keep their size, the steps in between and the
page shorten at their end, each with its full text in `title`.
**Use `->sticky()`, not `->fixed()`, with it** — a fixed header has a fixed
height and the page under it a fixed offset (56px), and a line that wraps would
cover the page's first rows. Put the sticky header in the content column of the
sidebar-first layout (or on a page without a sidebar); in the header-first
layout the fixed sidebar would start at `top: 56px`, below a gap.

**The menu button with its word, the sidebar it points at, a way past both.**
`->sidebarToggle(true, ['label' => true, 'controls' => 'main-nav'])` writes
a word beside the symbol (`true` = the language file's `header.menu`, or pass
your own; the default is the symbol alone) and `aria-controls`; the sidebar carries the same
id through `Sidebar::elementId('main-nav')`. With a word the button has no
`aria-label` — what is read is what is said (WCAG 2.5.3). A shut sidebar on a
phone is `visibility: hidden`: its links are out of the tab order and out of
what a screen reader reads. `Layout::skipLink('#main')` returns the skip
link (`layout.skip`); echo it first in `<body>` and give the content `<main id="main" tabindex="-1">`.
In the header-first layout the header may come before the `<aside>` in the
source — the sidebar is `position: fixed`, nothing depends on the order.

```php
echo Layout::skipLink('#main');                                  // first thing in <body>
echo (new Header())->title('Billing')->sidebarToggle(true, ['label' => true, 'controls' => 'main-nav'])->render();
```

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

`Sidebar::toggleButton()` prints the hamburger for a page with no `Header`;
`Header::sidebarToggle(true)` is the usual way.

**Since 1.100.0:** `->brand($title, $icon, $version, $href)` — with the fourth
argument the symbol and the name are one link (the way home), so nothing has to
be patched into the markup afterwards. A sidebar **without** a brand calls
`->mobileClose()`: on a phone it still gets a bar with the close button and its word
(`sidebar.close_short`), which is not drawn at a desk. `->elementId('main-nav')` writes the `id` on the
`<aside>` for `aria-controls` (see *Header*).

```php
(new Sidebar('main'))
    ->elementId('main-nav')
    ->brand('My project', 'widgets', 'v2.1', '/')   // a link home
    ->item('Dashboard', '/', 'dashboard')
    ->render();
(new Sidebar('side'))->mobileClose()->item('Dashboard', '/', 'dashboard')->render();
```

**No `onclick` (since 1.92.0).** The sidebar's controls carry
`data-gk-sidebar-action="toggle"`, `"close"` or `"collapse"`, and gridkit.js
answers them by delegation — so a page under a Content-Security-Policy without
`'unsafe-inline'` keeps a working sidebar. A shell written by hand uses the same
attribute instead of `onclick="GK.sidebar.toggle()"`; `GK.sidebar.toggle()`,
`open()`, `close()` and `collapse()` stay the API. A toggle that carries
`aria-expanded` is kept in step with the sidebar.

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
    'invalid'           => true,                 // aria-invalid="true" on the control
    'listId'            => 'country-list',       // id of the options list (aria-controls);
                                                 // derived from `id` when not given
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
    ->current('active')                    // optional: the chip to light while the URL names none
    ->render();
```

Active chip is auto-detected from `$_GET`. Color options: `success`, `danger`, `warning`, `primary`.
`FilterChips::current()` is for a page whose default view is already filtered: reached without the
parameter, the list shows one filter and no chip is lit, so the row reads as decoration. The default is
lit while the parameter is absent, and when it names a value no chip carries; `?status=` (the "All"
chip) and any chosen value win over it.
The param is always present in the URL, the "All" chip included (`?status=`) — this empty query string is what stops
`GK.liveTable.restoreSession` from jumping back to the last remembered filter. Read it with
`($_GET['status'] ?? '') !== ''`, never with `isset($_GET['status'])`.

A page with `current()` is the one exception: there "absent" means the default and "empty" means All, so
the controller has to tell them apart — `$status = is_string($_GET['status'] ?? null) ? $_GET['status'] : 'open';`.
Read the usual way, `?status=` would filter the list by the default while the All chip is lit.

**As a segmented button** (since 1.99.0): `->segmented('Period')` draws the same links as
`.gk-segmented` — one joined row, the chosen value tinted and ticked, a labelled `role="group"`.
For a choice that switches the view of a page (a period, overview / visitors), not for a filter
of a list. Everything else (URLs, `current()`, `preserve()`) is unchanged; an icon is drawn, a count is not.

```html
<div class="gk-segmented" role="group" aria-label="Period">
    <a class="gk-segmented-item" href="?days=1">Today</a>
    <a class="gk-segmented-item gk-active" href="?days=7" aria-current="true">7 days</a>
</div>
```

The chosen one is `aria-current` (any value but "false"), `aria-pressed="true"` (buttons) or
`.gk-active`. A row too wide for a phone scrolls; it never takes the whole width by itself.

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
`number` rounds to whole numbers like the table column does; pass `'decimals' => 2`
to keep some. `percent` shows the digits as given (`12.5` → `12,5 %` under `de`) or `'decimals'`
of them — the same in a card and in a table column (`Table::percent()` is the one rule).

**`trend`** is printed verbatim, exactly as you pass it — GridKit does no
rounding, no sign and no percent sign of its own. A leading `-` colours it as a
fall, anything else as a rise. So pass a finished string: `'+12%'`, `'-0.4%'`,
`'▲ 3'`. Passing a raw float gives you a bare `-8` in the card.

**`->compact()` (since 1.92.0)** renders the same `card()`s as compact tiles —
a figure over its word, small enough for a side sheet or a card
(`.gk-stat-tiles`, `.gk-stat-tile`). The cards stay the block for an overview
page.

```php
(new StatCards('user-lage'))
    ->compact()
    ->card('failing', 3,  ['color' => 'danger'])                // the one that needs acting on
    ->card('open',    12, ['sub' => 'for 3 days', 'href' => '#open'])
    ->card('done',    40)
    ->render();
```

`format` and `decimals` work as on a card; `sub` is a quiet third line; `href`
makes the tile a link. `'color' => 'danger'` or `'warning'` (also `'red'`,
`'orange'`, and `'highlight' => true`) tones the tile — every other colour stays
neutral, because a colour that means nothing costs the one that does (rule 4).
`icon` and `trend` belong to the full card and are not shown. By hand a tile may
also be a `<button>`: at least 44px tall, with a focus ring and a hover that
keeps its tone.

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

### Side sheet (`.gk-sheet`, since 1.91.0)

Where a record is read in full and changed — the row itself only shows values.
On a wide screen it docks at the right edge, 440px (`--gk-sheet-width` on
`.gk-root`), and the list beside it stays usable and scrollable: a non-modal
dialog. Below 769px it covers the screen and is modal — `aria-modal`, the focus
held inside, the page behind it held still. Docked, it is part of the page and
lies under the header: below a fixed or sticky GridKit header it starts where
the header ends (`--gk-sheet-top`), and the header's user menu, a dropdown or a
select list opened beside it lies over it. Give a page's own fixed bar a
z-index above 150 if it has to stay over an open sheet.

```html
<div class="gk-sheet" id="user-sheet" hidden>
  <div class="gk-sheet-header">
    <h2 class="gk-sheet-title">User</h2>
    <button type="button" class="gk-sheet-close">&times;</button>
  </div>
  <div class="gk-sheet-body">…</div>
  <div class="gk-sheet-footer">…</div>   <!-- optional: actions, pinned at the bottom -->
</div>

<button type="button" data-gk-sheet="user-sheet" data-gk-params='{"id":7}'>Jana</button>
```

GridKit gives it the dialog role, names it by `.gk-sheet-title` and names a bare
`&times;` close button, as it does for a static modal. `data-gk-sheet` on any
button or link opens it (`data-gk-params`, `data-gk-sheet-url` and
`data-gk-sheet-title` fill in the options); so does `->rowLink()`. Opening moves
the focus to its title (a screen reader starts at the record's name, one Tab
reaches the close button), Escape closes it while the focus is inside, and
closing gives the focus back to what opened it. One sheet at a time: opening another closes the
first. The row whose record it shows carries `aria-current="true"`. An AJAX form
inside closes the sheet on `{ok: true}`, not the modal.

Hidden is the `hidden` attribute. Without JavaScript the markup still works: render
it without `hidden` and it is a docked panel, with a plain link in the close
button's place. Add `.gk-sheet-push` to the content area if it should make room
for a docked sheet instead of lying under it.

**A line under the title (`.gk-sheet-meta`, since 1.92.0)** — the address, when
the record last moved, a link. With it the header becomes a grid: the line sits
under the title and the close button keeps its corner; links in it carry a dotted
underline. The close button is `--gk-target-min` (44px) docked and on a phone.

```html
<div class="gk-sheet-header">
  <h2 class="gk-sheet-title">Anna Probe</h2>
  <p class="gk-sheet-meta"><a href="mailto:anna@example.org">anna@example.org</a> · last seen today</p>
  <button type="button" class="gk-sheet-close">&times;</button>
</div>
```

### Form (AJAX)

```php
(new Form('user-form'))
    ->action('/api/save-user')
    ->method('POST')
    ->ajax()                    // REQUIRED for AJAX — without it the form does a native POST
    ->card()                    // optional: wrap the form in a gk-card. Leave it out inside a
                                // modal or an existing card — Form::card() is off by default
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

**Filling a form from a record, and showing errors (since 1.105.0).** `->values($record)` fills every
field whose name is a key of the array (keys without a field are ignored, so a whole database row can
be passed); a field's own `'value'` wins, and a second call adds to the first. `->errors($errors)` puts
each message where the field's own `'error'` would (red under the field, `aria-invalid`); a field's own
non-empty `'error'` wins, and a message whose key names no field (`'_form'`, say) is shown once in a
message above the form. Both change nothing else in the markup.

```php
// An edit form, shown again after a failed POST:
$statuses = ['draft' => 'Draft', 'paid' => 'Paid'];
$invoice  = ['number' => 'INV-7', 'customer' => 'Acme', 'status' => 'paid'];
$errors   = ['customer' => 'Required', '_form' => 'Could not save'];

(new Form('invoice'))
    ->action('save.php')
    ->field('number',   'Number',   'text',   ['required' => true, 'width' => 8])
    ->field('customer', 'Customer', 'text',   ['required' => true])
    ->field('status',   'Status',   'select', ['options' => $statuses])
    ->values($_POST ?: $invoice)                 // instead of 'value' => $invoice['x'] ?? '' on each field
    ->errors($errors)                            // ['customer' => 'Required', '_form' => 'Could not save']
    ->submit('Save')
    ->render();
```

**Field types.** Twelve have rendering of their own:

`textarea` · `select` (searchable) · `multiselect` · `ajaxselect` · `checkbox` ·
`toggle` · `radio` · `file` (drag & drop) · `richtext` (CKEditor) · `color` · `range` ·
`choice` (answer tiles, since 1.92.0)

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
| `clearable` | text-type inputs (`text`, `number`, `date`, …) | a small button inside the box that empties it; it shows only while there is something to clear |
| `accept` | `file` | which files the zone takes: an array `['pdf', 'jpg']` or a string `'.pdf,.jpg'`; also the `accept` of the input |
| `maxSize`, `minSize`, `maxTotalSize`, `maxFiles` | `file` | limits the browser checks before anything is sent: sizes as text (`'5 MB'`, `'200 KB'`), `maxTotalSize` for all files together, `maxFiles` a count. `maxSize` also fills the hint line (`Max. 5 MB`) unless `hint` is given |
| `multiple`, `preview` | `file` | several files, posted as `name[]`; thumbnails of the chosen pictures |
| `label_text`, `icon`, `hint` | `file` | the wording of the zone, its icon (default `cloud_upload`) and the quiet line under it |
| `labelField`, `valueField`, `subtextField` | `ajaxselect` | which keys of each JSON result the list reads: the text shown (default `name`), the value posted (default `id`), and an optional second line (default none) |
| `minChars`, `searchParam` | `ajaxselect` | how many characters before the first request (default `2`) and the query parameter the typed text travels in (default `q`) |
| `uploadHeaders` | `richtext` with `upload` | extra headers for the upload request, typically a CSRF token: `['X-CSRF-Token' => $token]` |
| `showIf` | every field | the field shows only while another field of the form has a value (since 1.105.0): `['field' => 'kind', 'equals' => 'company']`, with exactly one of `equals`, `not` (a value), `in` (a list of values) or `filled` (`true`: anything entered, `false`: empty). A field that is not shown is hidden **and disabled**: it is not posted, `required` blocks nothing, it takes no focus. A condition on a field that is itself hidden counts as empty, so a chain folds away together. An unusable condition raises `E_USER_WARNING` and the field stays visible. Needs `js/gridkit.js` 1.105.0; a copy without it shows every field. |
| `preset` | `richtext` | `'full'` (default) or `'basic'` — how much toolbar |
| `upload` | `richtext` | a URL. Turns pictures on: an upload button, drag & drop, paste, **alignment** (left/right with text flowing beside it, own line centred), size handles and alt text. Off without it, because an upload button with nowhere to put the file is worse than none. The endpoint takes a POST with the file under `upload` and answers `{"url":"https://…"}`, or `{"error":{"message":"…"}}` — CKEditor's SimpleUploadAdapter contract. **Return an absolute URL**: relative ones work on the page and break the moment the content is mailed. Pictures render as a plain `<img>` in the paragraph with the size as an inline `style`, deliberately not as `<figure class="image">`, so the same content survives being sent as an e-mail |
| `min`, `max`, `step` | `range`, `number`, `date`/`time` | bounds (`range` defaults to `0`/`100`/`1` and starts at `min`) |
| `->cancel($label, $href)` | — | a link beside the submit button — a method, not a field |
| `->hidden($name, $value)` | — | a hidden input — a method, not a field |
| `error` | every field | a validation message, rendered red under the field. This is how a classic POST-redisplay shows errors: `['value' => $_POST['email'] ?? '', 'error' => $errors['email'] ?? '']`. The AJAX handler writes into the same slot, so both paths look identical |
| `hint` | `toggle` | one sentence on what the switch does — the field becomes a settings row (`.gk-setting`): title and sentence on the left, the switch on the right, no label above it, and the input a `role="switch"` described by the sentence (since 1.92.0) |
| `danger` | `toggle` with `hint` | marks a switch that locks someone out: the title in the error colour, the switch red when on |
| `options` | `choice` | `value => label`, or `value => ['title' => …, 'hint' => …, 'mark' => …]`; the mark is A, B, C … by position unless given |
| `multiple` | `choice` | checkboxes instead of radios, posted as `name[]`; `value` takes an array or a comma string. Cannot be `required` in the browser — GridKit warns and draws no star; check it on the server |

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

**Settings and choices (since 1.92.0)** — the two fields a side sheet needs most:

```php
(new Form('user-7'))
    ->field('login', 'Sign-in allowed', 'toggle', ['hint' => 'Takes effect at once, also on running sessions.', 'value' => 1])
    ->field('lock',  'Lock the account', 'toggle', ['hint' => 'Signs the person out everywhere.', 'danger' => true])
    ->field('next',  'What happens now?', 'choice', ['required' => true, 'options' => [
        'fix'   => 'Fix it now',
        'later' => ['title' => 'Later', 'hint' => 'Stays on the list until next week.'],
    ]])
    ->render();
```

A `choice` is a radio group (`role="radiogroup"`, named by the field's label) of
`.gk-choice` tiles; each input is named by its mark and title and described by its
hint. The tile carries the state and the focus ring; where `:has()` is missing the
native control stays visible.

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

### Admin blocks: settings, figures, dots, colours (since 1.92.0)

The small parts an admin page kept writing for itself — each a class, light and
dark through roles, so a page needs no stylesheet of its own. The Form fields and
`StatCards::compact()` above write the first three; by hand they look like this.

**A setting (`.gk-setting`)** — rule 1 in the sheet: the switch alone, with one
sentence on what it does. `-danger` for a switch that locks someone out.

```html
<div class="gk-setting">
  <div class="gk-setting-text">
    <span class="gk-setting-title" id="login-t">Sign-in allowed</span>
    <span class="gk-setting-hint" id="login-h">Takes effect at once, also on running sessions.</span>
  </div>
  <label class="gk-toggle gk-setting-control">
    <input type="checkbox" role="switch" aria-labelledby="login-t" aria-describedby="login-h">
    <span class="gk-toggle-slider"></span>
  </label>
</div>
```

**A value that is only read (`.gk-field-static`)** — a field in the sheet that
cannot be changed there, and why:

```html
<div class="gk-field-static">
  <span class="gk-field-static-label">Plan</span>
  <span class="gk-field-static-value">everything</span>
  <span class="gk-field-hint">The superuser always has everything.</span>
</div>
```

**Compact figures (`.gk-stat-tiles`)** — see `StatCards::compact()`. By hand, a
tile that opens something is a `<button>`:

```html
<div class="gk-stat-tiles">
  <div class="gk-stat-tile gk-stat-tile-danger"><span class="gk-stat-tile-value">3</span><span class="gk-stat-tile-label">failing</span></div>
  <button type="button" class="gk-stat-tile"><span class="gk-stat-tile-value">12</span><span class="gk-stat-tile-label">open</span><span class="gk-stat-tile-sub">for 3 days</span></button>
</div>
```

As a definition list the word is the `<dt>` and comes first, as HTML wants it —
the figure still stands on top, and a `<dd>` brings no indent:

```html
<dl class="gk-stat-tiles">
  <div class="gk-stat-tile"><dt class="gk-stat-tile-label">open</dt><dd class="gk-stat-tile-value">12</dd></div>
</dl>
```

**A status dot (`.gk-dot`)** — a state or a presence beside its words, never
instead of them (`aria-hidden`). Tones `-success`, `-warning`, `-danger`,
`-primary`, `-muted`; `-outline` is an empty ring, told apart by its shape
("never"); `-pulse` for something running — it stands still under
`prefers-reduced-motion`.

```html
<span class="gk-dot gk-dot-success" aria-hidden="true"></span> today 08:12
<span class="gk-dot gk-dot-outline" aria-hidden="true"></span> never
<span class="gk-dot gk-dot-primary gk-dot-pulse" aria-hidden="true"></span> running
```

**Chart colours (`--gk-series-1` … `-5`)** — five categorical slots, the same in
every theme (a series follows its entity, never the accent), stepped separately
for dark mode. Assign them in order and keep an entity on its slot when a filter
removes others; a sixth series folds into "other". `.gk-swatch .gk-swatch-N` is
the legend mark, `.gk-series-fill-N` / `.gk-series-stroke-N` colour an SVG mark.
Slots 3 to 5 sit under 3:1 on a white ground, so a chart that uses them shows
its values as text as well — figures in the legend, or a table.

```html
<svg viewBox="0 0 100 20" role="img" aria-label="Opus 60 %, Sonnet 40 %">
  <rect class="gk-series-fill-1" width="60" height="20"/>
  <rect class="gk-series-fill-2" x="61" width="39" height="20"/>
</svg>
<span class="gk-swatch gk-swatch-1" aria-hidden="true"></span> Opus 60 %
```

**A finger-sized button (`.gk-btn-touch`)** — at least `--gk-target-min` (44px)
in both directions, the type unchanged (`.gk-btn-lg` grows the text as well).

**Actions in a message (`.gk-message-actions`)** — a place on the right of a
`.gk-message` for what to do about it; the status band of a page is a message
with a button. A long message takes its line and the actions move under it.
Written by hand, an outlined button needs its colour class as well:
`.gk-btn-outlined` alone has a transparent border (`Button::render()` always
writes one). In a message an outlined button stands on the surface, not on the
tinted band — its text colour is chosen for the surface (a warning's measured
4.35:1 on the band).

**A small message (`.gk-message-sm`)** — for a notice that sits among other
things and has to be seen: the sentence above a chat, a hint beside a form. It
has the look of `.gk-message` (tone, tint, symbol, `.gk-message-header`) in a
smaller box: padding 10px 14px, 13px text, a symbol one step down, and **no
margin** — the surroundings set the spacing (`gk-mb-*` still works on it, and a
flex column with `gk-gap-*` stacks several). The tone classes give the colours,
so it takes all four and none. `.gk-message-compact` is a different thing and
unchanged: it keeps the 16px margin of `.gk-message`.

```html
<div class="gk-message gk-message-info gk-message-sm">
  <span class="material-icons" aria-hidden="true">lock</span>
  <div class="gk-message-content">The conversation is stored on our server and never passed on.</div>
</div>
```

```html
<div class="gk-message gk-message-warning">
  <span>2 runs are stuck.</span>
  <div class="gk-message-actions"><button type="button" class="gk-btn gk-btn-outlined gk-btn-warning gk-btn-touch">Check now</button></div>
</div>
```

**Answer tiles (`.gk-choice`)** — see the Form's `'choice'` field; by hand:

```html
<label class="gk-choice">
  <input type="radio" name="q12" value="a">
  <span class="gk-choice-mark">A</span>
  <span class="gk-choice-text"><span class="gk-choice-title">Fix it now</span><span class="gk-choice-hint">Takes a minute.</span></span>
</label>
```

**Shown on a phone only (`.gk-show-mobile`)** — the counterpart of
`.gk-hide-mobile`: hidden above 768px, its own display below.

**Hidden means hidden.** Every element with a `gk-` class disappears with the
`hidden` attribute, whatever display its class gives it — `<div class="gk-message"
hidden>` used to stay on the page, because the browser's own `[hidden]` rule
loses to any display an author rule sets. An inline display is left alone: a
script that shows a block with `el.style.display = "block"` and leaves `hidden`
on it still shows it, as before 1.92.0. Hide and show with the attribute.

### Announcement (`.gk-announce`, since 1.93.0)

The line that says "Saved." after a save — where the person is working, not in
a corner like a toast — and a screen reader reads it. A live region is heard
only when it was in the accessibility tree BEFORE its words changed, so:

- Write it on the page from the start, **empty and never `hidden`**. Empty, it
  has no box (the `.gk-sr-only` clip, which keeps it in the accessibility tree —
  not `display: none`); words bring the box back.
- Change **only its words**, with `GK.announce()`. Never show it and fill it in
  the same step — that is the pattern that stays silent.
- Write **nothing between the tags** — not a space, not a line break. "Empty"
  is CSS `:empty`, and a line break is a text node: the region draws the empty
  box until `GK.init()` clears it, and for good on a page without the script.

```html
<div class="gk-message gk-message-compact gk-announce" id="saved" role="status" aria-live="polite" aria-atomic="true"></div>
```

```javascript
GK.announce('saved', 'Saved.', 'success');                 // element, id or selector
GK.announce('saved', 'Could not save — try again.', 'error');
GK.announce('saved', '');                                   // empty again, no box
```

The tone (`info`, `success`, `warning`, `error`; default `info`) sets the
`.gk-message-*` colour. The same words twice are read twice: the region is
emptied and the words come back 150 ms later. Words are text, never HTML.
`GK.init()` gives every `.gk-announce` its `role="status"`, `aria-live` and
`aria-atomic` if the markup left them out, and takes `hidden` off an empty one.
A region with `role="alert"` is read at once (assertive); keep that for errors
that must interrupt. `GK.melde()` is the same function.

### Timeline (`.gk-timeline`, since 1.96.0)

A log of steps, one under the other: what ran, in which order, how each step ended — a
pipeline, an import, an approval chain. Markup and classes only; there is no PHP class and
no script, because the page that has the steps writes them.

```html
<ol class="gk-timeline" role="list" aria-label="Steps of the run">
  <li class="gk-timeline-step gk-timeline-ok">
    <span class="gk-timeline-marker" aria-hidden="true"></span>
    <div class="gk-timeline-body">
      <div class="gk-timeline-head">
        <span class="gk-timeline-title">Write the script</span>
        <span class="gk-timeline-state">Done</span>
        <span class="gk-timeline-time"><time datetime="2026-10-17T09:12:03">09:12:03</time> · 4.2 s</span>
      </div>
      <div class="gk-timeline-chips"><span class="gk-label gk-label-gray">Claude</span><span class="gk-label gk-label-gray">$0.03</span></div>
      <p class="gk-timeline-detail">Three scenes, 38 words.</p>
    </div>
  </li>
  <li class="gk-timeline-step gk-timeline-failed">
    <span class="gk-timeline-marker" aria-hidden="true"></span>
    <div class="gk-timeline-body">
      <div class="gk-timeline-head">
        <span class="gk-timeline-title">Make the pictures</span>
        <span class="gk-timeline-state">Failed</span>
      </div>
      <p class="gk-timeline-error">The image service did not answer after 3 tries.</p>
    </div>
  </li>
  <li class="gk-timeline-step gk-timeline-running" aria-current="step">
    <span class="gk-timeline-marker" aria-hidden="true"></span>
    <div class="gk-timeline-body"><div class="gk-timeline-head">
      <span class="gk-timeline-title">Assemble the video</span><span class="gk-timeline-state">Running</span>
    </div></div>
  </li>
</ol>
```

- **A real ordered list.** The order is the information, so it is an `<ol>`, with a name
  (`aria-label`) and `role="list"`: the numbers and bullets are off (the markers and the line
  draw the sequence), and a screen reader that sees `list-style: none` drops the list unless
  the role says it is one. Do not number the steps in the text.
- **The state is a symbol AND a word, never colour alone.** One class on the `<li>` says
  which: `gk-timeline-ok` (a check), `gk-timeline-failed` (a cross), `gk-timeline-running` (a ring with a gap,
  turning), `gk-timeline-waiting` (an empty dashed ring with a dot), `gk-timeline-skipped` (a dash).
  The word stands beside the title in `.gk-timeline-state` — **write it in every step**; the
  marker is decoration (`aria-hidden="true"`, empty — CSS draws it), so a screen reader hears
  "Make the pictures, Failed". Put `aria-current="step"` on the running step.
- **Parts, all optional but the marker, the title and the state word:** `.gk-timeline-head`
  (title, state word and time on one row; on a phone the time goes under them),
  `.gk-timeline-time` (a `<time datetime>` for the timestamp, then the duration as text),
  `.gk-timeline-chips` (small `.gk-label`s — cost, provider — that may wrap),
  `.gk-timeline-detail` (a quiet sentence), `.gk-timeline-error` (the message of a step that
  broke: a tinted block with a 4px edge, in the error container pair — not a coloured sentence).
  `.gk-timeline-body` holds everything right of the marker.
- **Long words break** — a title, a detail, a message or a chip with an address in it wraps
  inside the column; nothing pushes the page wide, from 320px up.
- **Colour is roles only**, so light, `[data-gk-mode="dark"]` and `.gk-dark` follow from the
  tokens — there is no dark rule. The word of a tone uses the text-safe variants
  (`--gk-success-text`, `--gk-danger-text`, `--gk-primary-text`), the marker the container pair
  of its tone. Measured in a browser in all six themes and without one, light and both dark
  spellings, on a card and on the page (weakest value, light / dark / dark class): title, word,
  time and detail 5.36 / 5.62 / 5.62, the symbol on its disc 6.50 / 4.95 / 4.95 (the skipped dash,
  and the gray chip, which is the `.gk-label` GridKit has), the message of a failed step 7.97 /
  7.97 / 7.97; WCAG AA asks 4.5:1. The symbols are lines and borders, so a high-contrast mode
  (`forced-colors`) keeps them.
- **Motion:** the only thing that moves is the ring of the running step (`gk-spin`), and
  it stands still under `prefers-reduced-motion`.
- **Updating a step while the person watches:** replace the `<li>` — its state class, its
  word and, if it moved, `aria-current` together. The list is not a live region; say what
  happened with `GK.announce()`. Give the message `role="alert"` only when it appears while
  the person is looking; a log that is rendered with the page needs none.

**A to-do list** (since 1.99.0) — the same list for what is still open, e.g. in a status band:

- `gk-timeline-todo` — open, to be done next: a ring in the primary colour, its word
  (`Open`) in `--gk-primary-text`. "Afterwards" is the `waiting` state.
- `.gk-timeline-actions` — after `.gk-timeline-body` inside the `<li>`: a button right beside the
  sentence of its step; up to 480px under it.
- `.gk-timeline-compact` on the `<ol>` — 20px markers, a regular title, less space; for a short
  list inside a `.gk-message`.

```html
<ol class="gk-timeline gk-timeline-compact" role="list" aria-label="Still open">
  <li class="gk-timeline-step gk-timeline-todo"><span class="gk-timeline-marker" aria-hidden="true"></span>
    <div class="gk-timeline-body"><div class="gk-timeline-head"><span class="gk-timeline-title">Approve the page.</span><span class="gk-timeline-state">Open</span></div></div>
    <div class="gk-timeline-actions"><a class="gk-btn gk-btn-outlined gk-btn-primary gk-btn-sm" href="…">To the approval</a></div></li>
  <li class="gk-timeline-step gk-timeline-waiting">…<span class="gk-timeline-state">Afterwards</span>…</li>
</ol>
```

### Steps (`.gk-steps`, since 1.99.0)

Where something stands on a short way (2–5 stages): a draft, a release, live; the pages of a form.

```html
<ol class="gk-steps" aria-label="Way to live">
  <li class="gk-steps-item gk-steps-done">Draft<span class="gk-sr-only"> (done)</span></li>
  <li class="gk-steps-item gk-steps-current" aria-current="step">Approval</li>
  <li class="gk-steps-item">Live</li>
</ol>
```

Three shapes, never colour alone: done is a tick on a disc (`--gk-success` / `--gk-on-success`),
now is a dot in a ring and bold, open is an empty ring; a short line between two steps. The tick
is a pseudo-element with an empty alternative text — say "done" with `.gk-sr-only`.

### Disclosure (`.gk-disclosure`, since 1.99.0)

A quiet "show more" for a native `<details>`: a link-coloured summary with an arrow that turns,
44px high, a focus ring, no script. `.gk-accordion` is the framed box for sections of a page.

```html
<details class="gk-disclosure"><summary>Show 5 completed checks</summary><ul>…</ul></details>
```

**A message as the status band of a page** (since 1.99.0): with `.gk-message-actions` the
symbol stays at the top of a tall message, and a symbol inside a `.gk-btn` keeps the colour of
its button instead of the tone of the message.

### Portrait video frame (`.gk-video-frame`, since 1.96.0)

A frame for a 9:16 video or picture — a short, a story, a phone screenshot. A bare `<video>`
of a vertical file is as wide as its column and three screens tall; the frame keeps the shape.
Markup and classes only.

```html
<figure class="gk-video-frame">
  <div class="gk-video-frame-media">
    <video controls playsinline preload="metadata" poster="/p/3.jpg" aria-label="Version 3 of the short" src="/v/3.mp4"></video>
  </div>
  <figcaption class="gk-video-frame-caption">Version 3 · 24 s</figcaption>
</figure>
```

- **The box:** `.gk-video-frame-media` is `aspect-ratio: 9 / 16`, rounded (`--gk-radius-lg`) and
  cuts what lies in it. The `<video>` or `<img>` fills it and is **shown whole** (`object-fit:
  contain`, on black): whoever approves a video has to see all of it. It lies in the box
  (absolute), so a portrait file cannot give the box a height of its own.
- **Limits:** never wider than its column, never taller than `--gk-video-frame-max-h` — 75% of
  the window (`75dvh`, `75vh` behind it). The frame takes its WIDTH from that limit,
  `min(100%, limit × 9/16)`, so the shape holds when the limit bites (a `max-height` alone would
  leave a wide, flat box). A page changes the limit on the frame (`--gk-video-frame-max-h`), not
  with a width of its own. It is left-aligned; add `gk-mx-auto` to the `<figure>` to centre it.
- **No autoplay, ever:** write `controls` and `playsinline` (a phone opens a video without
  `playsinline` full screen on its own), give the `<video>` an `aria-label`, never `autoplay`.
- **Safe zones — `gk-video-frame-safe`:** a second class on the `<figure>` draws, over the
  picture, the zones a platform covers with its own buttons — the top 10% and the bottom 20% — as
  hatching with a dashed edge. They are guide lines: they take no room and **never catch a click**
  (the video's controls lie in the bottom zone). The two numbers are `--gk-video-safe-top` and
  `--gk-video-safe-bottom` on the frame (YouTube Shorts and Instagram Reels differ). Toggle the
  class with your own button; there is no script.
- **Nothing to show yet — `gk-video-frame-busy`:** the empty state, reused. `.gk-empty` fills the
  box; the symbol pulses (and stands still under reduced motion). Mark it `role="status"`.
- **It broke — `gk-video-frame-error`:** the same box, in the error colour: say what happened
  and what to do in `.gk-empty-title` and `.gk-empty-hint`.

```html
<figure class="gk-video-frame gk-video-frame-busy">
  <div class="gk-video-frame-media">
    <div class="gk-empty" role="status"><div class="gk-empty-inner">
      <span class="material-icons gk-empty-icon" aria-hidden="true">movie</span>
      <div class="gk-empty-title">Video is being made</div>
      <div class="gk-empty-hint">This takes a minute or two. You can leave the page.</div>
    </div></div>
  </div>
  <figcaption class="gk-video-frame-caption">Version 3</figcaption>
</figure>
```

A box with nothing in it has a dashed edge, so it shows on a page of the same grey. A long
caption or message breaks; in a frame 120px wide the message scrolls inside the box instead of
being cut. Colours are roles (light and both dark spellings follow), apart from the black behind
a video and the guide lines, which lie over any picture; the empty states measured 5.52 / 5.62 / 5.62
(light / dark / dark class, weakest of title, hint, symbol and caption in all six themes).

### Sortable container (`data-gk-sortierbar`, since 1.97.0)

Entries a person puts in order by hand — website cards on an overview, the steps of a mail
series — and the server remembers it. It is its own file, **`js/sortierbar.js`**, loaded with
`defer` after `gridkit.js` (`skeleton.php` and the demo do it; a page of your own adds
`<script src=".../js/sortierbar.js" defer></script>`). There is no global and no call: the
script finds every `[data-gk-sortierbar]`, also one inserted later.

```html
<div class="my-cards" data-gk-sortierbar
     data-gk-sortierbar-url="/websites/reihenfolge" data-gk-sortierbar-csrf="<?= $csrf ?>">
  <article class="my-card" data-gk-sort-id="12"><h3>survivaltraining.at</h3> … <a href="…">Open</a></article>
  <article class="my-card" data-gk-sort-id="7" data-gk-sort-titel="Manifestor">…</article>
</div>
```

- **The container** carries `data-gk-sortierbar`; its **children are the entries**, each with
  `data-gk-sort-id` (any element; a card in a grid is as good as a `<li>`). The name of an entry
  for a screen reader is `data-gk-sort-titel`, else its first heading, else its text.
- **The handle is made by the script:** a `<button type="button" class="gk-sort-griff">` with
  the icon `drag_indicator` and the name "Move: <title>", in the top right corner of every entry,
  only while the container has **two entries or more** (`gk-sort-aktiv` on the container).
  Give the entry room for it (padding of 56px or more on that side) or move the handle with
  `--gk-sort-griff-oben` / `--gk-sort-griff-rand`. Only the handle drags: links and buttons in an
  entry keep working, and the rest of the entry still scrolls the page on a phone
  (`touch-action: none` is on the handle alone).
- **With the pointer** (mouse, pen, finger): press the handle and move more than 4px. The
  entry follows (`.gk-sort-zieht`, `.gk-sort-frei`), a dashed gap (`.gk-sort-platz`) marks
  the place it would take; near the window's edge the page scrolls. **With the keyboard:**
  Space lifts the entry (the handle is `aria-pressed`), the arrow keys move it (left/right and
  up/down both work in a grid, Home and End go to the ends, `rtl` is respected), Space puts it
  down, Escape gives it back. The focus stays on the handle all the time. A polite live region
  says "Position 2 of 5".
- **Saving** happens only with `data-gk-sortierbar-url`: after the drop
  `POST` with `Content-Type: application/json`, `credentials: same-origin`, the header
  `X-CSRF-Token: <data-gk-sortierbar-csrf>` (another name in `data-gk-sortierbar-csrf-header`) and
  the body `{"ids":["12","7",…]}` — **all ids, as text, in the new order**. Answer with a 2xx
  (a body of `{"ok":false}` counts as a refusal). "Order saved" shows for a moment; on a
  refusal, an error status or no network the entry goes back to the last saved place and a line
  with a symbol and the words "The order could not be saved. Please try again." stays until the
  next try. Never two requests at once: a second drop aborts the first.
- **The event** `gk:sortiert` is fired on the container after every change of order, with or
  without a url (`bubbles`; `event.detail.ids`, `event.detail.behaelter`, and `detail.zurueck`
  when the order went back after a failure).
- **Words** come from `js.sortierbar_*` in `lang/` and reach the page with `Lang::jsConfig()`
  as `GK_LANG.sortierbar_*`. Motion (the small slide of the neighbours) stands still under
  `prefers-reduced-motion`. Colours are roles, so light and dark follow.
- **Do not** write the handle yourself, add a second drag script, or set `position` on the
  entry to something other than static/relative (the handle needs a positioned parent).

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

// A modal whose markup already stands on the page (since 1.83.0). show() only
// switches one that is already there; open() builds and removes its own.
// It brings the dialog role and a name, aria-modal, the focus trap, Escape,
// the backdrop click, every close button inside, and gives the focus back to
// whatever opened it. Hidden state is the hidden attribute, so you need no CSS.
GK.modal.show('#reg-overlay', {
  focus: '#reg-name',            // optional: where the caret goes (default: first control)
  onClose: () => reload(),       // optional: runs after it closes, however it closed
});
GK.modal.hide('#reg-overlay');   // or hide() for the topmost modal, whichever kind
// While one is open its overlay carries the class gk-modal-open — the hook for
// a page's own CSS that has to know whether a modal is up.
// show() returns null and leaves the overlay alone when it cannot own it: one
// that open() built, or one the page hides with a class of its own (it clears
// the hidden attribute and an inline display, nothing else). Do not hide a
// modal you hand to show() with a class — use the hidden attribute.

// Table refresh (after save/delete in server-side mode).
// Returns false when no table with that id is on the page.
GK.table.refresh('table-id');
GK.table.refreshAll();          // every table on the page

// Ask before something irreversible. Resolves true or false; the dialog traps
// focus, answers Escape with "cancel" and hands focus back afterwards.
// Use it instead of the browser's confirm() — that one cannot be styled, named
// or translated, and it blocks the page.
GK.confirm('Delete this invoice?', { title: 'Delete', confirmText: 'Delete', danger: true })
  .then(function (yes) { if (yes) removeInvoice(); });

// Side sheet (since 1.91.0) — markup on the page, see "Side sheet".
// The first argument is an id, "#id" or the element. Returns the sheet or null.
GK.sheet.open('user-sheet', {
  returnFocus: rowButton,        // optional: where the focus goes back (default: what has it now)
  focus: '#plan',                // optional: where the caret goes (default: the title)
  params: { id: 7 },             // optional: handed to gk:sheetopen, posted with url
  url: 'panels/user.php',        // optional: its answer fills .gk-sheet-body
  title: 'Jana Novak',        // optional: replaces the text of .gk-sheet-title
});
GK.sheet.close();                // the open one

// Announcement (since 1.93.0) — markup on the page, see "Announcement".
GK.announce('saved', 'Saved.', 'success');   // tone: info, success, warning, error
GK.announce('saved', '');                    // empty: no box, still a live region
document.addEventListener('gk:sheetopen', e => fill(e.target, e.detail.params));  // detail: { opener, params }
document.addEventListener('gk:sheetclose', e => { /* e.detail.opener */ });
```

An AJAX form (`Form::ajax()`) reports its own outcome: `{ok: true, message: '…'}` closes the modal, refreshes
the tables and shows the message as a success toast; `{ok: false, errors: {field: '…'}}` marks the fields;
`{ok: false, error: '…'}` (or `message`) shows an error toast. Anything that is not JSON shows the generic
error toast (language key `js.error_saving`).

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

**`data-gk-replace` works in a live table too (since 1.86.0).** Anything in the
fresh markup wrapped in `<template data-gk-replace="a-css-selector">` replaces
the matching element OUTSIDE the container — summary cards above the list, a
status select beside it, the pager below. GridKit does it before it fires
`gk-live-reloaded` and re-binds the searchable selects, multi-selects, tooltips
and `data-gk-live-input` fields that came with it, so a page needs no listener
of its own. Put one root element in each template.

`PageSize` on its own, outside a `Pagination`:

```php
PageSize::make('per_page')          // the query parameter
    ->current(25)                   // what is selected now
    ->options([10, 25, 50, 100])    // default: 10/25/50/100
    ->baseUrl('/expenses')          // default: the current path
    ->preserve(['year', 'sort'])    // names, read from $_GET …
    ->preserve(['year' => 2024])    // … or a name => value map
    ->live('exp-live')              // AJAX instead of a full navigation
    ->selectClass('gk-filter')      // PageSize::selectClass(): the class of the <select>;
                                    // default 'gk-filter gk-pagesize-select'
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
<!-- Inputs: anywhere outside the container -->
<input data-gk-live-input="my-tbl" name="q" placeholder="Search" aria-label="Search">
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
- **Out-of-band updates (since 1.86.0):** a `<template data-gk-replace="a-css-selector">`
  in the fresh markup replaces the matching element OUTSIDE the container — summary
  cards above the list, a status select beside it, the pager below. GridKit applies
  them, re-binds every widget that came with them (`GK.initContent`) and keeps the
  focus on the control that had it. One root element per template; a template that
  cannot be applied costs only itself. See "Out-of-band updates" under Pagination.
- The `gk-live-reloaded` event fires on the container after every swap — **after** the
  replacements above, so your listener sees the finished page. Bind your own
  re-initialisation to it.
- **A failed request leaves the rows alone.** A 4xx/5xx answer, a network error, or a whole page where a
  fragment (or, in self mode, the container) should be: the old rows stay, an error toast appears and `gk-table-error` fires on the container
  (`event.detail.error`). A 401 — or an answer that was redirected to another path, which is how many
  backends say "log in again" — reloads the page instead. Of two requests in flight only the newer one writes.

**No partial branch? Use self mode.** Add `data-gk-live-self` to the container and the controller needs no
change at all: the answer is the whole page, and GridKit cuts the element with the same `id` out of it.

```html
<div id="my-tbl" data-gk-live-table="/my-list" data-gk-live-self> … </div>
```

`TableHeader::search('q', $q, 'Search…', ['live' => 'my-tbl'])` renders the bound input for you.

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
- A loading bar along the top edge of the screen
- Browser back/forward works through pushState
- Every widget on the new page is bound after the swap (`GK.initContent(root)` — the same list page
  load and modals use: selects, live tables, tabs, pager, accordion, AJAX forms, hand-written modals).
  Call `GK.initContent(el)` yourself after inserting markup with widgets in it, and
  `GK.modal.upgradeStatic(overlay)` after inserting an overlay you built in JavaScript.
- `gk-ajax-nav` fires on `document` after each swap (`event.detail.url`, `event.detail.content`) for
  page code that binds things of its own
- **The target page's own styles come along.** A `<link rel="stylesheet">` or `<style>` in the new
  page's `<head>` that the current document lacks is added and awaited before the content is swapped,
  and removed again when you navigate on. GridKit marks what it added with `data-gk-nav-asset` — do
  not write that attribute yourself. Scripts in `<head>` are NOT brought along: put a page's script
  inside `[data-gk-content]`, where it is re-executed on every swap.
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

## Common Pitfalls

1. **Search through HTML** — Never put HTML in `search()` column keys. Use plain-text key + separate display key.
2. **Missing `Lang::jsConfig()`** — "no_entries" shows as raw key. Must be in `<head>` before `gridkit.js`.
3. **Wrong button classes** — Use `gk-btn-filled` not `gk-btn--filled` (no double dash).
4. **Wrong toast API** — Use `GK.toast.success()` not `GK.toast()`.
5. **Wrong modal API** — two methods, two jobs. `GK.modal.open(title, url, params, size)`
   builds its own overlay and POSTs to `url` to fill the body; it does NOT take an HTML
   string, so passing markup makes the browser request it as a path and the modal fills
   with the server's 404 page. For an overlay whose markup is already on the page, use
   `GK.modal.show('#id')` / `GK.modal.hide('#id')` — never `open()`.
6. **Direct project edits** — Always change GridKit at its own source, never inside a consuming project.
7. **A clickable row made by hand** — `onclick` on a `<tr>`, `role="link"` or a `tabindex` on it: the keyboard
   cannot reach it or a screen reader loses the row. Use `->rowLink()`, or `tr.gk-row-link` with one
   `.gk-row-target` in its main cell. And never a select or a switch in every row — that is what the side sheet is for.
