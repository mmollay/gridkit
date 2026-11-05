# GridKit – Agent Skill

> **Version:** 1.126.0 | **License:** MIT | **Repository:** https://github.com/mmollay/gridkit
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
| LineItems | `GridKit\LineItems` | The lines of an invoice, offer or order: posts `name[i][field]`, live totals per VAT rate, the same totals in PHP to the cent, add/duplicate/delete with Undo/reorder, cards on a phone, read-only for a sent invoice (since 1.117.0) |
| Import | `GridKit\Import` / `js/import.js` / `GK.import` | A CSV file into checked rows: recipients, customers, articles. Fields with types, aliases, required and unique; separator, quotes, header row and encoding (UTF-8, Windows-1252 from Excel) found by themselves; columns matched by name, a select per column; a preview with every error in words; import the valid rows or cancel. A form in three steps without script, an instant preview with it; the server reads and checks the file again either way (since 1.119.0) |
| Header | `GridKit\Header` | Fixed header with breadcrumb, user menu |
| Sidebar | `GridKit\Sidebar` | Navigation with groups, badges, collapse |
| Modal | `GridKit\Modal` | Dialog overlays |
| Button | `GridKit\Button` | Filled/Outlined/Text/Tonal, icons, sizes |
| Auth | `GridKit\Auth` | Session auth, bcrypt, remember-me |
| Theme | `GridKit\Theme` | 6 themes (indigo/ocean/forest/rose/amber/slate), light/dark — and auto with the switcher since 1.114.0, which also refined every colour |
| Layout | `GridKit\Layout` | Layout modes (sidebar-first, header-first) |
| StatCards | `GridKit\StatCards` | KPI cards with icon, color, format |
| FilterChips | `GridKit\FilterChips` | URL-based filter chip buttons |
| YearFilter | `GridKit\YearFilter` | Year navigation filter |
| DateRange | `GridKit\DateRange` / `js/calendar.js` / `GK.dateRange` | A period for reports and invoice lists: presets (today … last year, custom) and two native date fields, the period in words, `DateRange::fromRequest()` for the checked `from`/`to` (in order, within min/max, today in a time zone); stand-alone as a GET filter or the Form field type `daterange`; works without script (since 1.120.0) |
| TableHeader | `GridKit\TableHeader` | **Unified filter/search bar above tables — Status / Toolbar / Advanced (since v1.10.0)** |
| Lang | `GridKit\Lang` | i18n / multilingual support |
| Pagination | `GridKit\Pagination` | Server-side pager below the table (`.gk-rowpager`), optional PageSize — the bar `Table::paginate()` draws too (since 1.118.0) |
| PageSize | `GridKit\PageSize` | Rows per page — lives in the pager bar, not in the table footer |
| liveTable (JS) | `GK.liveTable` | AJAX tables (search/filter/sort/pagination live, no reload) |
| BelegModal | `GridKit\BelegModal` | PDF / document preview modal with iframe + mobile fallback (since v1.15.0) |
| ActionGroup | `GridKit\ActionGroup` | Container for action buttons inside table columns (since v1.16.0) |
| SortLink | `GridKit\SortLink` | Sortable column headers for hand-built tables (server-side sort) |
| Select | `GridKit\Select` | Searchable single/multi select, optionally AJAX-fed |
| Tabs | `GridKit\Tabs` / `.gk-tabs` / `[data-gk-tabs]` | Tab navigation, two shapes — full tablist semantics and arrow keys since 1.69.0; the Karteireiter shape `.gk-tab-nav-karte` + `.gk-tab-flaeche`, nesting and a phone grid since 1.101.0; a row that does not fit its box becomes the grid by itself (`GK.tabsFit`) and `gk-tab-badge-error` since 1.102.0; line tabs for the second row, `--gk-tab-radius` and link tabs that keep their place (`GK.tabsScroll`) since 1.102.0; three more styles (line, pill, text), four alignments (left, right, bottom, fixed bar) and options (size, full width, symbol above or only, neutral, disabled) as classes on the row since 1.103.0; the PHP class `Tabs` for every option, deep links (`->hash()`, `->param()`), remembered tabs, lazy panels, `gk:tabchange`/`gk:tabshown` and `GK.tabs.select/current/badge/count/reload`, arrows on a row wider than its box (`GK.tabsOverflow`) since 1.111.0 |
| Accordion (JS) | `.gk-accordion` | Collapsible sections, optional single-open (`data-gk-single`) |
| Tooltips (JS/CSS) | `GridKit\Tooltip` / `title` / `data-gk-tooltip` / `data-gk-tooltip-rich` / `data-gk-toggletip` / `.gk-help` | Hint popups — plain, title, with HTML in them, or a toggletip that opens on a click; one engine places, flips and announces them all; `'tooltip'` on Button and Table, `'help'` on Form (since 1.112.0) |
| Gallery + Lightbox (JS) | `.gk-gallery` / `GK.lightbox` | Image grid with lazy loading and a keyboard-operable viewer |
| Image picker | `GridKit\ImagePicker` / `GK.picker` | Choose one image out of many: a compact grid of 16:9 tiles that works like a radio group, folds after two rows, and an enlarged view that can pick (since 1.94.0) |
| PermissionMatrix | `GridKit\PermissionMatrix` / `GK.permMatrix` | Roles × permissions for user and rights management: inheritance from a parent role, locked roles, `requires`, bulk toggles per row, column and group, filter, folding, changes marked until saved, a keyboard grid, one role at a time on a phone; posts `perm[role][permission]` as 0/1, read back with `read()` / `fromPost()`, or saves each change as JSON (since 1.115.0) |
| Announcement (JS/CSS) | `.gk-announce` / `GK.announce` | The line that says "Saved." where the person works, read by a screen reader; empty it draws no box (since 1.93.0) |
| Timeline | `.gk-timeline` | A log of steps, one under the other: a real ordered list, each step with a symbol and a word for its state (ok · failed · running · waiting · skipped), title, time, small chips, a detail and — when it broke — a message block (since 1.96.0) |
| Portrait video frame | `.gk-video-frame` | A 9:16 frame for a video or picture: never wider than its column or taller than the window, optional guide lines for the zones a platform covers, an empty "being made" state and a failed state, a caption (since 1.96.0) |
| Sortable container (JS) | `[data-gk-sortierbar]` / `js/sortierbar.js` | Cards or rows a person puts in order by hand: a handle in every entry (from two entries on), dragging with mouse or finger, Space and arrow keys, a spoken position, the order posted as JSON when there is a `url`, and a sentence beside a symbol when saving failed (since 1.97.0) |
| Chart | `GridKit\Chart` / `js/chart.js` / `GK.chart` | Line, area, bar (grouped, stacked, horizontal) and donut charts and sparklines as inline SVG from the server: nice axes, numbers in the chart's language, a summary in words, a legend, the data as a table; with the script a tooltip, the keys, a crosshair, series switched off in the legend, `GK.chart.update()` drawing the same SVG as PHP (since 1.121.0) |
| Tree | `GridKit\Tree` / `js/tree.js` / `GK.tree` | Page trees, categories, folders: a nested list that works without script, made a WAI-ARIA tree view — keys, type-ahead, open/closed remembered, single or multi selection with tri-state parents, a filter, children loaded on demand, moving by drag and drop or keyboard with a save URL and a way back (since 1.116.0) |
| Calendar | `GridKit\Calendar` / `js/calendar.js` / `GK.calendar` | A month of appointments, deadlines and campaigns: a table of days with events as links, bars across days, "+3 more", week numbers, months as links (`?month=2026-12`), an agenda when narrower than 600px, arrow keys / Home / End / PageUp / PageDown with the script (since 1.120.0) |
| CommandPalette | `GridKit\CommandPalette` / `js/command-palette.js` / `GK.palette` | Ctrl+K (Cmd+K on a Mac): a few letters, Enter — to a page of the sidebar (read from the page), an entry or a command of yours, or a record your search URL returns as you type (same origin, debounced, the stale answer dropped); fuzzy, umlauts both ways, marked matches, recent entries, groups; a modal dialog with the keys of a combobox and a listbox; a full-screen sheet on a phone (since 1.122.0) |
| FilterBuilder | `GridKit\FilterBuilder` / `js/filter-builder.js` / `GK.filterBuilder` | Conditions combined freely for tables, segments, invoice lists: rows of field, operator, value; AND/OR groups nested to a depth; add, duplicate, remove; saved views stored by the page. The state is compact JSON in one URL parameter, `FilterBuilder::fromRequest()` checks it, `toSql()` writes a WHERE clause with placeholders only, `matches()` filters rows in memory, `summary()` says it in words; a form without script, instant with it (since 1.123.0) |
| Kanban | `GridKit\Kanban` / `js/kanban.js` / `GK.kanban` | Columns with cards for tasks, orders, campaigns, tickets: a labelled region of lists with headings and counts that works without script (links, a "Move to" form per card); with the script dragging by mouse or finger with a drop line and the board scrolling under the card, full keyboard moving with every step spoken, work-in-progress limits that warn or refuse, every move saved as JSON one at a time with the way back on a refusal; lanes that snap on a phone (since 1.124.0) |
| FileManager | `GridKit\FileManager` / `js/file-manager.js` / `GK.files` | A media library and a receipt drawer: folders with a breadcrumb, thumbnails or a list, search, a filter by type, sorting, single or multi selection by mouse and keyboard, bulk actions (delete, move, download) and a details panel (dimensions, URL to copy, rename) — from rows the page supplies, posting to the page's URLs; many uploads at once, chosen, dropped or pasted, each with a progress bar, cancel and retry, the new file in the listing without a reload; `FileManager::receive()` checks an upload on the server by its content (type, extension, size, pixels, code in it, SVG script) and stores it under a random name outside the web root; a sheet on a phone; works without script (since 1.125.0) |
| Side sheet (JS) | `.gk-sheet` / `GK.sheet` | A panel a row opens: docked right beside the list, full screen and modal on a phone (since 1.91.0) |
| Icon | `GridKit\Icon` | Inline SVG icons with a Material Icons fallback — `Icon::svg($name, $px)`: the 2nd argument is an **int** pixel size (default 16), not an options array |

## The one rule to read first: echo or return

Half of GridKit prints; half hands you a string. Getting this wrong produces no
error and no warning — the page renders, the piece is simply missing. Five
agents were given this file and a page to build; not one got a first draft
without tripping over this.

**These thirteen PRINT. Call them as a statement — `<?php $x->render(); ?>`:**

| | | |
|---|---|---|
| `Table::render()` | `Form::render()` | `Sidebar::render()` |
| `StatCards::render()` | `FilterChips::render()` | `TableHeader::render()` |
| `YearFilter::render()` | `Pagination::render()` | `PageSize::make()->…->render()` |
| `ActionGroup::render()` | `Modal::container()` | `BelegModal::container()` |
| `Tabs::render()` (1.111.0) | | |

`Tree::render()` and `Tree::renderBranch()` (1.116.0) print as well, and so do `Import::render()` (1.119.0),
`DateRange::render()` and `Calendar::render()` (1.120.0), `Chart::render()` (1.121.0), `CommandPalette::render()`
(1.122.0), `Kanban::render()` (1.124.0) and `FileManager::render()` (1.125.0) — their `->html()` returns the same string (`Chart::sparkline()` and `CommandPalette::trigger()` return a
string to place).

`PageSize::render()` above is an INSTANCE method — build it with the static
`PageSize::make('per_page')` first, then chain. Same for `TableHeader::make()`.
Everything else in that table is called on an object you constructed with `new`.

**These RETURN a string. You must echo it — `<?= … ?>`:**

| | | |
|---|---|---|
| `Header::render()` | `Button::render()` | `Button::icon()` |
| `Theme::switcher()` | `Theme::attributes()` | `Layout::bodyTag()` |
| `Icon::svg()` | `Select::searchable()` | `Layout::asset()` |
| `Lang::jsConfig()` | `Pagination::build()` | `Layout::skipLink()` |
| `Tabs::html()` — the string twin of `Tabs::render()` (1.111.0) | | |

`Theme::density('konsole')` sets how tightly tables are set — `konsole` fits
about a third more rows on a screen, `weit` gives them room, and the default
leaves the rhythm alone. It names no colour, so it composes with every theme
and both modes; an unknown value is ignored. Its steps are tokens in
`css/themes.css`, which the page must load; since 1.110.3 they reach the padding
of every table `Table` draws, in each size and variant, a list's row and a
phone card (before, only the label radius moved). To let the person at the
screen choose for one table, see `Table::densityToggle()`.

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
use GridKit\{ActionGroup, Auth, BelegModal, CommandPalette, FilterChips, Header, Icon,
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

**`paginate($perPage)`: the pager under the rows.** `paginate(false)` or `0` shows
every row. With a page size, a table with more rows than that gets one pager, the
same bar `Pagination` draws (since 1.118.0; before, a centred row of pill
buttons): on the left which rows the page shows, "6–10 of 12" (`pagination.range`,
with the thousands separator of the language), on the right first, previous, the
first and the last page and two either side of the current one with `…` between
(never 400 buttons), next and last. A `setData()` table pages in the browser, a
`rows()` or `query()` table asks the server for `gk_page` (below); a search or a
filter goes back to page 1, a sort of a server table too. The controls are
`<button data-gk-page>`, not links — the table pages in place — inside
`<nav class="gk-pagination gk-rowpager">`, a landmark named "Page navigation";
each control has a name ("First page", "Page 3 of 12"), the current page
`aria-current="page"`, and the keyboard focus stays on the control you pressed.
Do not put a `Pagination` under a paginated table: that is two pagers. The rows
per page from a `PageSize` select: `->paginate(PageSize::make('per_page')->resolve(25))`,
the select in `toolbarHtml()` — it reloads the page and starts at page 1.

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

**`'tooltip'` on a column and on a row button (since 1.112.0):** on a column,
`->column('net', 'Net', ['tooltip' => 'Before VAT'])` puts a help icon (`.gk-help`) beside
the header label — a button of its own next to the sort button, opening its text on a click.
On a row button, `->button('edit', ['icon' => 'edit', 'tooltip' => 'Edit this customer'])`
writes GridKit's tooltip instead of the browser `title`; `'title'` or `'aria'` still name the
button. Both take a string or `['text' => …, 'pos' => …, 'tone' => …]` and survive a sort of a
`setData()` table. See *Tooltips*.

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
| `edit` | `'text'`, `'number'` or `'select'`: the cell can be edited in place, see `editable()` below; `false` (or `null`, `''`) is read-only without a word |
| `options` | `edit => 'select'`: what it offers, value => text (like a filter's `options`); a `label` column without it offers the keys of its `labels` |
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

**`->densityToggle()` (since 1.109.0):** a segmented control in the toolbar, Compact · Normal ·
Roomy (German: Kompakt · Normal · Weit), with which the person at the screen sets how tightly this
one table is set. The steps are those of `Theme::density()`: Compact is `konsole` (8px / 12px, label
radius 4px), Roomy is `weit` (16px / 20px), Normal the default (12px / 16px). The script writes the
step on the table's wrap as `data-gk-density`, so it reaches this table only, and remembers it per
table id in this browser (localStorage `gk-density-<id>`, in the namespace of `GK.theme`; where
storage is blocked it holds for the page view). It survives every sort, search, page and AJAX reload.
Every size keeps its distance from `md` (`sm` 6px tighter, `lg` 4px roomier), a `size('list')` row
goes from 64px to 52px or 76px, the variants `compact`, `padded` and `very-compact` move by the same
step, and so does the spacing of a phone card; a row link stays at least 44px and the row buttons
keep their size. **Normal is GridKit's default rhythm, not the page's:** the control starts at the
page's own step, so on a page with `Theme::density('konsole')` it starts at Compact and Normal is
roomier than the rest of the page. Choosing the page's own step forgets the stored choice, and the
table follows the page again. The page-wide `Theme::density()` reaches the same padding since 1.110.3
(with `themes.css`, which holds its tokens); the toggle's steps work with `css/gridkit.css` alone. The control
is rendered hidden and `js/gridkit.js` 1.109.0 shows it: without the script, or with an older copy,
the table is the table without the option. With `toolbar(false)` there is no control.

**`->editable($key, $url, $opts)` (since 1.110.0): inline editing.** A cell of a column with `'edit'`
turns into a field on a click, or on Enter or F2 while it has the focus; every such cell is in the tab
order and tells a screen reader that it can be edited (`aria-describedby` → "Editable: press Enter to
change it."). Enter saves, **leaving the field saves**, Escape puts the old value back, Tab saves and
opens the next editable cell (Shift+Tab the previous one). An unchanged value sends nothing. `$key` is
the row field that names a record, as in `selectable()` — it is written into every row as
`data-gk-row-id` and posted as `id`; with `selectable()` under another key, the selection's key is used
for both and GridKit says so. A row whose key is empty is not editable. `$url` is where a change is
POSTed; without it nothing is sent and your listener saves. Option `headers` adds request headers,
typically `['X-CSRF-Token' => $token]` (the name `uploadHeaders` and the sortable container use too).
A table without a column that can be edited renders exactly as it would without the call.

```
(new Table('invoices'))->setData($rows)
    ->editable('id', '/api/invoice-cell.php', ['headers' => ['X-CSRF-Token' => $csrf]])
    ->column('customer', 'Customer', ['edit' => 'text', 'sub' => 'city'])
    ->column('total', 'Amount', ['format' => 'currency', 'edit' => 'number'])
    ->column('status', 'Status', ['format' => 'label', 'edit' => 'select',
        'labels' => ['paid' => ['color' => 'green', 'text' => 'Paid'], 'open' => ['color' => 'orange', 'text' => 'Open']]])
    ->column('kind', 'Kind', ['edit' => 'select', 'options' => ['b2b' => 'Business', 'b2c' => 'Consumer']])
    ->render();
```

- **The field holds the raw value**, not the formatted one: a currency cell showing `€1,200.50` opens
  with `1200.5`. A `setData()` table takes it from its data block; a `rows()` or `query()` table writes it
  into each editable cell as `data-gk-raw`. `text` is an input, `number` an `<input type="number">`
  (empty is `null`), `select` offers the column's `options`, else the keys of a label column's `labels`
  with their `text`; a stored value that is not on the list stays in the list. A select saves as soon as an
  option is picked with the pointer; with the arrow keys it waits for Enter, Tab or leaving the field.
- **Refused, with a warning, and the column stays read-only:** `'format' => 'html'` and `'email'` (they
  write markup of their own), a column with `'href'` (a link cannot also open a field), the column that
  carries `rowLink()`'s control, a `select` with nothing to choose from, an unknown `edit` type, and `'edit'`
  on a table without `editable()`. The other cells of a row with a link are editable: a click on one opens
  its field, a click elsewhere in the row still follows the link.
- **The event** `gk:celledit` fires on the table (it bubbles) before anything is sent, with
  `{ tableId, id, column, value, oldValue }` — `id` as text; `value` and `oldValue` are text, numbers (or
  `null`) for a `number` column. `preventDefault()` puts the old value back and sends nothing.
  `event.detail.respondWith(promise)`, called in the listener, answers instead of the URL: the promise
  resolves to what the server would send (`true` is short for `{ ok: true }`); a rejection is a failure.
- **The request** (with `$url`, and no `respondWith()`): `POST`, `Content-Type: application/json`,
  `X-Requested-With: XMLHttpRequest`, `credentials: same-origin`, your `headers`, body
  `{"id":"7","column":"total","value":1500.25}`. **The answer:** a 2xx with `{"ok":true}` saves; it may
  add `"value"` (what was stored, when it differs from what was sent: trimmed, rounded) and `"display"`
  (the cell's markup — inserted as HTML, so escape what you put in it; used by a `rows()`/`query()` table
  only). Anything else — another status, `{"ok":false,"error":"…"}`, a body that is not JSON, no network —
  puts the old value back and shows a toast with your `error` as text (never as markup), else "The change
  could not be saved." (`js.edit_error`). While it saves the cell is `aria-busy="true"` and cannot be
  opened again.
- **After a save** the cell shows the value formatted the way the column formats it (the same code that
  redraws a sorted table), and the table's live region says "Saved.". A `setData()` table also changes its
  data, so a later sort, search, page or CSV export has the new value; the row stays where it is until then.
  A `rows()` or `query()` table shows `display` when the server sent it; its next reload comes from the
  server, which owns the value. A date column redrawn in the browser has the client's date format (the known
  gap of a sorted table) — send `display` if that matters. A rebuild of the table (a sort, a page,
  `GK.table.refresh()`) saves a field that is still open first.
- **Styling hooks:** an editable cell is `td[data-gk-edit]` (a dotted line under it, solid on hover, a focus
  ring), the open one `td[data-gk-editing]` (the field lies over the hidden value, so no column moves; in a
  phone card it takes the value's place), a saving one `td[aria-busy="true"]`. Needs `js/gridkit.js`
  1.110.0; an older copy shows the table read-only.

```js
// Without a URL: save it yourself, and let GridKit show the outcome.
document.querySelector('[data-gk-table=invoices]').addEventListener('gk:celledit', e => {
  const { id, column, value } = e.detail;
  if (column === 'total' && value < 0) return e.preventDefault();     // refused: old value back
  e.detail.respondWith(fetch('/api/cell', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id, column, value }),
  }).then(r => r.json()));                                            // { ok, value?, display?, error? }
});
```

An endpoint, in plain PHP:

```
$in = json_decode(file_get_contents('php://input'), true);
header('Content-Type: application/json');
if (!in_array($in['column'] ?? '', ['customer', 'total', 'status', 'kind'], true)) {   // never trust the column name
    http_response_code(422);
    exit(json_encode(['ok' => false, 'error' => 'This field cannot be changed.']));
}
$repo->update((int) $in['id'], $in['column'], $in['value']);
echo json_encode(['ok' => true, 'value' => $repo->find((int) $in['id'])[$in['column']]]);
```

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
->densityToggle()    // Compact · Normal · Roomy in the toolbar, kept per
                     // table id in the browser (see above)
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
    'title'   => 'Add a row', // the browser's tooltip (GK.tip restyles it)
    'tooltip' => 'Adds a row at the end',  // GridKit's tooltip instead: a string, or
                              // ['text' => …, 'pos' => …, 'tone' => …, 'kbd' => …] — a
                              // description, not the name; no title is written with it.
                              // See "Tooltips" (since 1.112.0)
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

**The system's name as the first step (1.126.0).** `'marke' => ['label' => 'SSI Panel',
'href' => '/', 'icon' => 'dashboard_customize']` makes the brand the first step in place of
"Dashboard": the name of the system is the way back to the dashboard and the path stands
to its right, like the repository path on GitHub. Put no brand and no title in the header
beside it (one name, not two). The line is one row at every width; up to 768px the steps in
between give way to a "…" menu (`<details>`, the same links), up to 520px the brand is its sign
alone (name stays for a screen reader and in `title`). On the dashboard the brand alone is the
page (`aria-current`). Without `'marke'` nothing changes.
`'ruhig' => true` (1.123.0, kept on the branch `kopf/harmonie`) draws the steps as plain links without the pill outline at every width.

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

### DateRange (since 1.120.0)

A period for reports and invoice lists: a select of presets and two native `<input type="date">`,
from and to, with the period written out in words beside them ("Oct 1 – 31, 2026",
"1.–31. Okt. 2026"). **Without any script it works as it is** — choose a preset or type the dates,
send. With `js/calendar.js` — a file of its own like `js/tree.js`, loaded with `defer` after `gridkit.js`;
`skeleton.php` and the demo do it, a page of your own adds `<script src=".../js/calendar.js" defer></script>`
— a preset fills the dates at once, typing a date switches the select to *Custom*, and the words follow.
There is no call to make: the script finds every picker and calendar, also one inserted later.
`DateRange::render()` prints, `->html()` returns the same string.

```php
// A list page: the period in the filter bar, and the query reads the very same period.
$range = (new DateRange('period'))
    ->presets(['this-month', 'last-month', 'this-quarter', 'last-quarter', 'this-year', 'custom'])
    ->default('this-month')
    ->max(date('Y-m-d'))           // nothing after today
    ->filter()                     // its own <form method="get"> with Apply; a preset is sent at once
    ->preserve(['status', 'q']);   // the other filters stay in the URL
['from' => $from, 'to' => $to] = $range->current();   // checked: real days, from <= to, within min/max
// $stmt = $pdo->prepare('SELECT … WHERE invoice_date BETWEEN ? AND ?'); $stmt->execute([$from, $to]);

TableHeader::make('invoices')
    ->filter(fn () => $range->render())
    ->render();
```

**Reading it** — on the page that queries, or on the POST of a form:

```php
$period = DateRange::fromRequest('period', 'this-month', ['max' => date('Y-m-d'), 'timezone' => 'Europe/Vienna']);
// ['from' => '2026-11-01', 'to' => '2026-11-04', 'preset' => 'this-month']

DateRange::presetRange('last-quarter', '2026-01-15');   // ['from' => '2025-10-01', 'to' => '2025-12-31']
DateRange::text('2026-10-01', '2026-10-31');            // 'Oct 1 – 31, 2026' — '1.–31. Okt. 2026' with Lang 'de'
DateRange::date('2026-02-30');                          // null: not a day
```

**In a Form** — the field type `daterange`; the field's options are the picker's, its label names the
group, its message line describes every control:

```php
(new Form('report'))
    ->field('period', 'Period', 'daterange', ['presets' => ['last-7-days', 'last-30-days', 'last-month'],
        'default' => 'last-30-days', 'max' => date('Y-m-d'), 'required' => true])
    ->values($_POST)                     // what was posted comes back, read by the same rules
    ->submit('Show')
    ->render();
```

| Method | What it does |
|---|---|
| `new DateRange($name)` | The name it posts under (default `period`): `name[preset]`, `name[from]`, `name[to]`, `name[shown]`. A plain name (letters, digits, `_`, `-`); anything else warns and is cleaned. |
| `->presets([...])` | The presets offered, in this order: `today`, `yesterday`, `last-7-days`, `last-30-days`, `this-month`, `last-month`, `this-quarter`, `last-quarter`, `this-year`, `last-year`, `custom` (all of them by default, `DateRange::PRESETS`). `custom` — the dates as typed — is added at the end when it is missing; `[]` shows no select, only the dates. An unknown key warns and is left out. A posted preset the list does not offer is ignored. |
| `->default('this-month')` | What is shown when the request names no period: a preset (`this-month` by default) or `['from' => 'Y-m-d', 'to' => 'Y-m-d']`. |
| `->value($v)` | The period to show instead of the request's: a preset, `['from' => …, 'to' => …]`, or what was posted under the name — read by the same rules as the request. |
| `->min('2020-01-01')` / `->max(date('Y-m-d'))` | The first and last day that can be chosen. Every date is held within them, the presets too; a preset that lies wholly outside is not offered. A day that is none, or a min after the max, warns and is no bound. |
| `->timezone('Europe/Vienna')` | The zone "today" is counted in (default `date_default_timezone_get()`): at 00:30 in Vienna it is already the next day there, whatever the server's clock says. |
| `->today('2026-11-04')` | The day the presets count from instead of today — `'Y-m-d'` or a `DateTimeInterface` (read in the time zone). For a report of a fixed day and for tests. |
| `->label('Invoice date')` | The accessible name of the group (default "Period"). In a Form the field's label names it. |
| `->filter()` | Stand-alone in a filter bar: its own `<form method="get">` (`gk-daterange-form`) and an Apply button; with the script a chosen preset is sent at once. Without `filter()` the picker is part of the form it stands in. |
| `->baseUrl('/invoices')` | The action of the filter form (default: the page itself; a `#fragment` is kept). |
| `->preserve(['status', 'q'])` | Query parameters the filter form carries along as hidden fields — names read from the URL, or `name => value`. |
| `->required()` | Both dates must be filled in (the browser's `required`). |
| `->current()` | `['from', 'to', 'preset']` of this picker — checked, as `fromRequest()` gives it. Call it on the picker you render, so both read the same options. |
| `DateRange::fromRequest($name, $default, $opts)` | The same, without a picker. `$opts`: `presets`, `min`, `max`, `timezone`, `today` (as above), `request` (an array to read instead of `$_POST`, then `$_GET`). An unknown option warns. |
| `DateRange::presetRange($preset, $today, $timezone)` | The days a preset covers: `['from', 'to']`, or `null` for `custom`. `$today` is `'Y-m-d'`, a moment, or null for today in `$timezone`. |
| `DateRange::text($from, $to)` | A period in words, in the page's language. |
| `DateRange::date($value)` | `'Y-m-d'` when it is a day that exists (years 1 to 9999), else `null` — the check every date here goes through. |
| `DateRange::forForm($f, $field, $value, $invalid)` | What `Form` calls for a `daterange` field; not for your own use. |
| `->render()` / `->html()` | Prints the picker / returns it. |

- **The presets** count from today (or `today()`), on the calendar — a day of 23 or 25 hours changes
  nothing: `last-7-days` is today and the six days before it, `last-30-days` today and 29 before;
  `this-month`, `this-quarter` and `this-year` are the **whole** period, the days still to come included
  (hold them with `->max(date('Y-m-d'))` for "so far"); `last-month`, `last-quarter` and `last-year` the
  whole one before. February has 29 days in 2028 and 2000, 28 in 2100.
- **What the server reads** (`current()`, `fromRequest()`): a preset wins and is counted from today
  again — so a bookmarked "this month" is always this month. Dates win when the preset is `custom` or
  missing, or when they were typed over without a script (they differ from `name[shown]`, the dates the
  page showed). Reversed dates are swapped, both are held within min/max. A date that is no day, an
  array, a hostile string: as if nothing was sent — the default. The script keeps preset and dates in
  step and leaves `name[shown]` out.
- **In the browser** (`js/calendar.js`): choosing a preset fills the dates (counted from the server's
  today, in the browser the same days as `presetRange()`), typing a date makes the select *Custom*, the
  words follow (`<output aria-live="polite">`), and each date bounds the other (`max` of from, `min` of
  to), so the browser itself refuses an end before the start. In `filter()` mode a preset is sent at
  once (`requestSubmit()`); typed dates wait for Apply. Every change by the person fires
  **`gk:daterange`** on the `.gk-daterange` (bubbles; `detail: { name, from, to, preset, text }`).
  `GK.dateRange.get(el)`, `GK.dateRange.set(el, 'last-month' | { from, to })` (fires nothing),
  `GK.dateRange.presetRange(key, today)`, `GK.dateRange.text(from, to, el)` — `el` is the element, its
  id or the name it posts under.
- **Accessible**: a `role="group"` named "Period" (or the Form label); the select is "Preset", the
  dates "From" and "To"; the words are an `<output>` for both. No colour carries anything.
- **On a phone** (600px and less) the picker is the width of the page: the select on top, the two dates
  side by side, the words below; the fields are 16px, iOS does not zoom.
- **Classes** (block "DateRange" in `css/blocks.json`): `gk-daterange`, `gk-daterange-form`,
  `gk-daterange-preset`, `gk-daterange-dates`, `gk-daterange-from`, `gk-daterange-to`,
  `gk-daterange-sep`, `gk-daterange-text`, `gk-daterange-apply`. Fields are `.gk-filter` stand-alone,
  `.gk-input` in a Form.
- **Words**: `daterange.*` and `date.months_short` in `lang/`; the script takes them from the picker's
  own `data-gk-daterange`, so it writes what PHP writes.
- **Do not** compare the posted strings yourself or count "this month" by hand — `current()` /
  `fromRequest()` have checked them; do not build `BETWEEN` from `$_GET['period']['from']`.

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
`icon` and `trend` belong to the full card and are not shown. `sparkline` (since 1.121.0) works on a
card and a tile alike: `'sparkline' => [8200, 7900, 9400]` draws `Chart::sparkline()` under the figure,
named by the label and written in the card's format (`['values' => [...], 'color' => 3, 'area' => true]`
passes options — see "Chart"). By hand a tile may
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

**Tabs in a form (since 1.113.0).** `->tab($key, $label, $opts)` starts a tab: every field added after it,
up to the next `tab()` or `->endTabs()`, goes into that tab's panel. Fields before the first `tab()` stand
above the tabs (the fields that belong to every tab: a number, a name); fields after `->endTabs()` stand
below them, and the buttons below everything. Without `endTabs()` the tabs run to the end. One set of tabs
per form. Never write tab markup into a form by hand — a required field in a hidden tab then blocks the
submit with a browser message nobody can see, and a message from `errors()` marks a field nobody can find.

```php
// A customer form shown again after a failed save ($_POST carries the tab the user was on):
$roles    = ['admin' => 'Admin', 'sales' => 'Sales'];
$canGrant = true;
$customer = ['number' => 'C-7', 'name' => 'Ada Lovelace', 'city' => ''];
$errors   = ['city' => 'Required'];

(new Form('customer'))
    ->action('save.php')
    ->field('number', 'Customer no.', 'text', ['width' => 8])        // above the tabs
    ->tab('general', 'General', ['icon' => 'person'])
        ->field('name',  'Name',  'text',  ['required' => true])
        ->field('email', 'Email', 'email')
    ->tab('address', 'Address', ['icon' => 'home'])
        ->field('city', 'City', 'text', ['required' => true])
    ->tab('rights', 'Rights', ['count' => count($roles), 'disabled' => !$canGrant])
        ->field('roles', 'Roles', 'multiselect', ['options' => $roles])
    ->endTabs()
    ->field('note', 'Internal note', 'textarea')                     // below the tabs
    ->tabsRemember()                     // optional: reopen the last tab next time (per form id)
    ->values($_POST ?: $customer)        // also hands back the posted tab (Form::TAB_FIELD)
    ->errors($errors)                    // the first tab with an error opens, each such tab gets a badge
    ->submit('Save')
    ->render();
```

| `tab()` option | Effect |
|---|---|
| `icon` | a Material icon before the word (`gk-tab-icon`) |
| `badge` | a short word after it (`gk-tab-badge`) — only for a deviation ("Customised"), never "Default" |
| `count` | a whole number of things behind the tab (`gk-tab-count`) |
| `disabled` | the tab cannot be chosen; its fields are still posted as they are |

| Form method | Effect |
|---|---|
| `->tabsStyle($style)` | the row's style: `'karte'` (the default: page tabs on their surface, `gk-tab-flaeche`), `'linie'`, `'kapsel'` or `'text'` |
| `->tabsSecondary()` | the second-row shape (`gk-tab-nav-karte gk-tab-nav-secondary`, no surface) — call it for a form in a **modal, a side sheet or a panel of the page's own tabs**: the window is the surface. The form cannot see where it is rendered, so the page says. Only with `'karte'` |
| `->activeTab($key)` | the tab to start on instead of the first, e.g. `->activeTab($_GET['tab'] ?? '')` |
| `->tabsRemember()` | the browser notes the chosen tab per form id (`localStorage`, key `gk-form-tab-<id>`) and opens it next time — only when the page chose none (no error, no posted tab, no `activeTab()`); blocked storage just means no memory. Give such a form an id that names its purpose |

Unknown options, a style that does not exist, a duplicate key (renamed `key-2`), `tab()` inside a `row()` (the
row is closed first), a second set of tabs (its fields stand below the first) and an `activeTab()` that names
no tab that can be opened raise `E_USER_WARNING` and fall back. A form without `tab()` renders exactly as before.

- **Which tab opens:** the first tab with an error (from `errors()` or a field's own `'error'`; an error above
  the tabs chooses none) — even a disabled one, since a message nobody can reach is the problem; else the tab
  posted back in the hidden field `_gk_tab` (`Form::TAB_FIELD`), handed in through `->values($_POST)`; else
  `activeTab()`; else the first tab that is not disabled. All of it in PHP, so it holds with no script.
- **The posted tab:** the form posts `_gk_tab` (the tab on screen), so a failed save opens where the user was.
  Leave it out when you store `$_POST` as a whole. A value that names no open tab is ignored without a warning.
- **Error badges:** every tab with errors carries `<span class="gk-tab-badge gk-tab-badge-error">2 errors</span>`
  — the count in words (`form.tab_errors_one` / `form.tab_errors`), never colour alone. A server message counts
  until the form is sent again.
- **Every panel is in the page and is posted:** a hidden panel is `display:none`, never `disabled`. `showIf`
  works across tabs (a field in one tab may depend on a field in another).
- **With `js/gridkit.js` 1.113.0:** a submit the browser blocks for a field in a hidden tab opens that tab, and
  the browser focuses the field and shows its own message — the first rejected field in page order, one switch
  per submit, for a submit button, Enter, `requestSubmit()` and `reportValidity()` (not for a script's
  `checkValidity()`). After that first try each tab with rejected fields carries the badge too, counted again on
  every input and change and gone when the tab is clean. Errors an AJAX form gets back open the tab of the first
  one when it is hidden, and so does an empty required `richtext`. The chosen tab is kept in `_gk_tab`.
  `GK.formTabs.refresh(form)` counts again after a script changed what is valid (`setCustomValidity()`).
- **With an older `js/gridkit.js`** (or none): the tabs switch as any tab row does, and the server's tab and
  badges are there; the jump to a rejected field, the live badges, remembering and following the user's clicks
  into `_gk_tab` are missing — the form then posts the tab it was rendered with.
- The markup is the authored shape of **Tabs** (each button `type="button"`, so a click on a tab never submits),
  so a card row turns into the phone grid up to 600px and nests inside a page's own tabs with the same keys.

**Field types.** Twelve have rendering of their own:

`textarea` · `select` (searchable) · `multiselect` · `ajaxselect` · `checkbox` ·
`toggle` · `radio` · `file` (drag & drop) · `richtext` (CKEditor) · `color` · `range` ·
`choice` (answer tiles, since 1.92.0)

and `daterange` (since 1.120.0): the period picker of `GridKit\DateRange` — the options `presets`,
`default`, `min`, `max`, `timezone`, `today` and `required` are the picker's; `values($_POST)` hands
back what was posted (`name[preset]`, `name[from]`, `name[to]`), read like a request. See **DateRange**.

And `lineitems` (since 1.117.0): the editor of `GridKit\LineItems`, handed in as the option
`lines` — `->field('items', 'Lines', 'lineitems', ['lines' => $lines])`. The field's label names
it, `values(['items' => $rows])` fills it when it has no `items()` of its own, and an AJAX
answer `{"errors": {"items[1][qty]": "…"}}` lands under that cell. See **LineItems**.

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
| `min`, `max`, `step` | `range`, `number`, `date`/`time`, `daterange` | bounds (`range` defaults to `0`/`100`/`1` and starts at `min`); a `daterange` takes `min` and `max` as `'Y-m-d'` |
| `presets`, `default`, `timezone`, `today` | `daterange` | the presets offered, the period without a request (a preset or `['from' => …, 'to' => …]`), the zone of "today", a fixed today — as `DateRange`'s methods (since 1.120.0) |
| `lines` | `lineitems` | the `GridKit\LineItems` the field shows (since 1.117.0); without it the field is left out with an `E_USER_WARNING` |
| `->cancel($label, $href)` | — | a link beside the submit button — a method, not a field |
| `->hidden($name, $value)` | — | a hidden input — a method, not a field |
| `error` | every field | a validation message, rendered red under the field. This is how a classic POST-redisplay shows errors: `['value' => $_POST['email'] ?? '', 'error' => $errors['email'] ?? '']`. The AJAX handler writes into the same slot, so both paths look identical |
| `help` | every field | a help icon (`.gk-help`) beside the label that opens this text on a click — a toggletip, see *Tooltips* (since 1.112.0). A string, `'#id'` of an element holding HTML, or `['text' => …, 'pos' => …, 'tone' => …]`. The label and the icon stand in a `.gk-label-row`; the field keeps its label as its name. Without a visible label the icon follows the control |
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

### LineItems (since 1.117.0)

The lines of an invoice, an offer or an order: a description that may run to a second line,
quantity, unit, unit price, discount, VAT rate, the line total — and under them the subtotal,
an optional overall discount and shipping, one VAT line per rate and the total. The totals
follow every keystroke, and **`LineItems::totals()` computes the very same figures in PHP, to
the cent**, so the server never has to take the browser's word for an amount. Every line is an
`<li>` of an `<ol data-gk-sortierbar>`: `js/sortierbar.js` moves it (mouse, finger, keyboard).

```php
$lines = (new LineItems('items'))
    ->invoiceColumns()          // description, qty, unit, unit price, discount %, VAT, amount
    ->locale('de')              // numbers with a decimal comma: 1.234,50
    ->currency('EUR')
    ->vatRates([20, 10, 13, 0]) // the VAT select, in this order; the first is a new line's rate
    ->discount(3)               // an overall discount line with its field (posts items_discount)
    ->shipping('4.90')          // a shipping line with its field (posts items_shipping)
    ->limits(1, 50)             // at least one line (it starts with one empty), at most fifty
    ->items([
        ['id' => 41, 'description' => "Website relaunch\nDesign, templates", 'qty' => 1,
         'unit' => 'flat', 'price' => '2400', 'discount' => 5, 'vat' => 20],
        ['id' => 42, 'description' => 'Hosting, annual', 'qty' => 12, 'unit' => 'month',
         'price' => 19.9, 'vat' => 20],
    ]);
$lines->render();   // prints, like Table::render(); ->html() returns the same string
```

**On the POST**, the same object (without `items()`) reads what came back:

```php
$lines = (new LineItems('items'))->invoiceColumns()->locale('de')->vatRates([20, 10])->discount(0);
$in = $lines->parse($_POST);
if ($in['errors']) {
    // Show the form again: what was typed, the messages under their fields.
    $lines->items($in['rows'])->errors($in['errors'])->discount($in['discount'] ?? 0);
    // An AJAX Form answers instead: echo json_encode(['ok' => false, 'errors' => $in['errors']]);
} else {
    $store = $in['items'];             // lines in machine form ('1234.5'), blank ones dropped
    $gross = $in['totals']['gross'];   // computed here — never taken from the browser
}
```

**The totals contract** — `LineItems::totals($rows, $opts)`, the same in the browser as
`GK.lineItems.calc(rows, opts)`:

```php
$t = LineItems::totals([
    ['qty' => 3, 'price' => '120', 'discount' => 10, 'vat' => 20],
    ['qty' => 2, 'price' => '39.90', 'vat' => 10],
], ['discount' => 5, 'shipping' => '4.90']);
// $t['gross'] === '458.63'
// $t['vat'] === [['rate' => '20', 'base' => '312.70', 'amount' => '62.54'],
//                ['rate' => '10', 'base' => '75.81', 'amount' => '7.58']]
```

- **Returns** strings with two decimals, `"-12.30"`, never floats: `lines` (one
  `['total' => …, 'vat' => '20']` per row, in order), `subtotal` (the lines, net or gross like
  the prices), `discount` (≤ 0), `shipping`, `net`, `vat` (per rate, highest first:
  `rate`, `base` = the net, `amount`; a rate whose base and amount are both 0 is left out),
  `vatTotal`, `gross`, and the `pricesNet` and `rounding` it used.
- **Rows** hold numbers in machine form — an int, a float, `'1234.5'`; with the option `locale`
  strings are read as a person types them. A figure that cannot be read counts as 0; a row
  without a quantity column counts once.
- **Options:** `pricesNet` (default `true`), `rounding` (`'line'`), `discount` (an overall
  percentage, taken off each rate's sum before VAT), `shipping` (an amount, net or gross like the
  prices, added after the discount), `shippingVat` (its rate; default the highest rate of the
  lines), `keys` (which row key is the quantity, price, discount and VAT:
  `['qty' => 'quantity', 'vat' => null]` — `null` for one the rows do not have), `locale`.
  `->totalsOptions()` returns these for an editor, so `LineItems::totals($rows, $lines->totalsOptions())`
  agrees with what it showed.
- **The arithmetic is integer arithmetic.** Quantities and prices are read to 4 decimals,
  percentages to 2, then multiplied, summed and rounded as integers of any length (on decimal
  strings in PHP, BigInt in the browser) — no float, no drift, no overflow.
  `->pricesNet(true)`: VAT = round(net × rate); `false` (gross entry): VAT =
  round(gross × rate / (100 + rate)), net = gross − VAT, and the total is exactly the sum of the
  lines. **Rounding** is half away from zero (0.005 → 0.01, −0.005 → −0.01).
  `->rounding('line')` (default) rounds every line to the cent and sums what the lines show;
  `->rounding('total')` sums the lines unrounded and rounds each rate's sum once (the lines still
  show cents; their sum may then differ from the subtotal by a cent).

**Posted shape.** `items[0][description]`, `items[0][qty]`, … — **the index is the position on
the screen, 0 to n−1**, renumbered after every add, delete and move, so reading the array in
order is reading the invoice in order. A line drawn from a row with an `id` posts it back as
`items[i][id]` (a duplicate gets none: it is a new line). `items_discount` and
`items_shipping` beside the array. A `readonly` column posts its value back (hidden) — the
server decides whether to trust it. The line total (`computed`) does not post; `parse()` puts
it into each item under the total column's key.

**`parse($request)`** returns `items` (to store), `rows` (every posted line in posted order, a
figure that could not be read left as typed — for `items()` when the form is shown again),
`errors`, `discount`, `shipping` (machine form, or `null`) and `totals`. A **blank line** — every
field empty or at its `default` — is not a line: it is dropped, nothing in it is required, and
in the browser it does not block the form (except the first ones `->limits()` asks for). At most
`maxRows` lines are read, never more than 1000 (a larger POST is an attack, and is said so).

**Errors are keyed like the POST:** `items[2][qty]`, `items` (too few or too many lines),
`items_discount`. That is the key `GK.form` looks for, so an AJAX Form's
`{"ok": false, "errors": {"items[2][qty]": "Only 3 in stock."}}` lands under that very cell
and moves the caret there. `->errors()` takes the same keys, or `items.2.qty`. The messages
`parse()` writes are the ones the browser shows on leaving a field and on sending the form
(constraint validation): a figure that is none ("Enter a number, such as 1.234,50."), too many
decimals, `min`/`max`, a required field, a select value the list does not offer.

**Numbers as people type them** — `LineItems::parseNumber($text, $locale)`, in the browser
`GK.lineItems.parse(text, locale)`: `1.234,50`, `1,234.50`, `1234.5` and `1 234,50` are all
1234.5; spaces, no-break spaces and apostrophes group thousands; a currency sign or code and a
`%` at either end are ignored; `-` and `−` are minus. With both `.` and `,` the last one is the
decimal sign. One sign alone is the decimal sign — unless exactly three digits follow it and one
to three come before (`1.500`, `12,500`): only then does the language decide, as it would for a
person (`de`: 1500, `en`: 1.5). Exponents, hex, other scripts' digits, a repeated decimal sign,
groups that are not three digits, more than 15 digits before the sign: `null`. A field that can
be read is written back in the page's shape when it is left (`1234,5` → `1.234,50`); it is
never rounded — a decimal too many stays, with its message.

**Columns** — `->column($key, $label, $opts)`; a `$label` of `''` takes the role's own words.
`->invoiceColumns()` is description (`textarea`, required), `qty` (`number`, required, default
1), `unit` (`select` of `->units()`), `price` (`money`, required), `discount` (`percent`, 0 to
100), `vat` (`select` of `->vatRates()`, required) and `total` (`computed`). The position number
is always the first cell and carries the handle.

| Option | Types | Effect |
|---|---|---|
| `type` | — | `text` (default), `textarea` (grows with its text, so a description has room for a second line; read-only, the second line is set quieter beneath the first), `number`, `select`, `money`, `percent`, `computed` (the line total), `readonly` (shown and posted back), `lookup` (below). An unknown type warns and is `text` |
| `role` | see effect | what the totals read: `qty` (`number`), `price` (`money`, `number`), `discount` (`percent`, `number`), `vat` (`select`, `percent`, `number`), `total` (`computed`). Default: the key itself when it is one of these names; a `computed` column is the total. One column per role |
| `required` | editable | the browser's `required` (only on lines that are lines) and `parse()`'s message |
| `decimals` | `number`, `money`, `percent` | how many a person may type: default 3, 2, 2; at most 4, 4, 2. `money` shows at least this many |
| `min`, `max` | `number`, `money`, `percent` | bounds, checked in both places |
| `default` | all | the value of a new line (`qty` 1, the first VAT rate); a line still at its defaults is blank |
| `placeholder` | `text`, `textarea`, figures, `lookup` | the field's placeholder |
| `width` | all | its grid track: `6rem`, `120px`, `2fr`, `minmax(8rem, 1fr)` — anything else warns |
| `options` | `select` | `value => label`. A `unit` key without options takes `->units()`, the `vat` role takes `->vatRates()`. A stored value the list does not offer is shown as it is and marked; `parse()` and the browser refuse it |
| `rows` | `textarea` | its height in rows to start with (default 1) |
| `url`, `fill`, `labelField`, `valueField`, `subtextField`, `minChars`, `searchParam`, `display` | `lookup` | an article search (below) |

**Article lookup.** A `lookup` column is Form's `ajaxselect` in a cell (`GK.ajaxSelect`, the
same markup): typing asks `url?q=…` (`searchParam`, after `minChars`, default 2) for a JSON
array; `labelField` (default `name`) is shown, `valueField` (default `id`) is posted under the
column's key, `subtextField` is a second line in the list. Picking one fills the line by `fill`
— `['description' => 'name', 'unit' => 'unit', 'price' => 'price', 'vat' => 'vat']`, target
column => key of the JSON item; figures in machine form are written in the page's shape — and
sets an empty quantity to 1. With `display` => a row key, the words shown post too
(`items[i][article_name]`) and `items()` shows them again.

**Other setters:** `->units(['h' => 'hours', 'pcs' => 'pieces'])` (default: pcs, h, day, month,
km, flat rate in the page's language), `->pricesNet(false)` (gross entry: the unit price label
says so), `->currency('USD')` (EUR, USD and GBP get their sign, any other ISO code is written
out: `CHF 12.50`), `->locale('en')` (default `Lang::locale()`; `de` and `en` with their regions,
anything else warns and reads English), `->label('Positions')` (the editor's accessible name,
default "Line items"), `->readonly()`.

**Read-only** (a sent invoice): the same columns and grid, the figures as text, no field, no
button, no handle, nothing that posts; `GK.lineItems.totals()` still reads it (each cell carries
its figure in machine form).

**On a phone** — whenever the editor itself is narrower than 56rem, so also in a medium modal —
every line is a card: the number and the handle (a finger's width) and the buttons on top, the
description across, the other fields two by two with their labels shown, the line total last;
the totals beneath, full width. Nothing scrolls sideways; fields are 16px, iOS does not zoom.

**In the browser** (the block at the end of `js/gridkit.js`; `js/sortierbar.js` for the order):
Add line (`data-gk-lines-action="add"`, also on a button of your own inside the editor), Enter
in the last field of the last line adds one, and Enter in any other field of a line does not send
the form. Duplicate puts a copy below. Delete takes the line out and says so in a toast with
**Undo**, which puts it back where it was. Add and Duplicate are off at the maximum, Delete at
the minimum. After every change `gk:lineitemschange` fires on the editor (bubbles; `detail.items`
as `parse()` would return them, `detail.totals` as `totals()`), and the total is said once the
typing stops. **Without script** the fields post as drawn and the server computes the totals;
the buttons that could not work are not shown.

```javascript
document.addEventListener('gk:lineitemschange', e => preview(e.detail.totals.gross));
const editor = document.querySelector('.gk-lines');   // or a selector, or any element inside it
GK.lineItems.totals(editor);                 // the totals of what the editor holds now
GK.lineItems.items(editor);                  // its lines, as parse() returns them as items
GK.lineItems.add(editor, { description: 'Travel', qty: 1, price: '40', vat: 20 });
GK.lineItems.calc(rows, opts);               // LineItems::totals() without an editor
GK.lineItems.parse('1.234,50', 'de');        // '1234.5'
```

**In a Form** — `->field('items', 'Lines', 'lineitems', ['lines' => $lines])` (see Form): what
`Form` calls is `LineItems::forForm($lines, $field, $value)`, which names the editor by the
field's label and its message line and takes the Form's value as the lines. An AJAX Form needs
nothing more: its JSON `errors` reach the cells by their names. For a Form shown again after a
plain POST, give the cells' messages to the editor — `$lines->errors($in['errors'])` — and the
others to `Form::errors()`: a cell is not a field of the Form, so `Form::errors()` would list its
message in the box above the form instead (only the key `items` itself goes under the field). **Do not** read the
totals from the POST, compute amounts with floats, or write a line's markup by hand: the script
renumbers what it finds by its `data-gk-lines-*` attributes.

### Import (`GridKit\Import`, since 1.119.0)

A CSV file into rows a page can store: the recipients of a newsletter, customers, articles. The
page defines the fields; the person chooses (or drops, or pastes) a file, sees which column fills
which field and a preview with every error in words, and imports the valid rows or cancels.
**It is a form of its own** (`<form method="post" enctype="multipart/form-data">`, so do not put it
inside another form) that works without any script, in three steps: the file, the columns and the
check, done. **`js/import.js`** — a file of its own like `js/tree.js`, loaded with `defer` after
`gridkit.js` (`skeleton.php` and the demo do it; a page of your own adds
`<script src=".../js/import.js" defer></script>`) — reads the file in the browser instead: the
mapping and the preview are there the moment a file is chosen, dropped or pasted, and follow every
change. `Import::render()` prints; `->html()` returns the same string.

```php
$import = (new Import('customers'))
    ->label('Import customers')                          // the form's accessible name
    ->field('customer_no', 'Customer no.', ['required' => true, 'unique' => true, 'aliases' => ['Kundennummer', 'Nr']])
    ->field('name', 'Name', ['required' => true, 'aliases' => ['Nachname', 'Last name']])
    ->field('email', 'E-mail', ['type' => 'email', 'required' => true, 'unique' => true, 'aliases' => ['E-Mail', 'Mail']])
    ->field('country', 'Country', ['type' => 'select', 'options' => ['AT' => 'Austria', 'DE' => 'Germany', 'CH' => 'Switzerland']])
    ->field('revenue', 'Revenue', ['type' => 'money', 'aliases' => ['Umsatz']])
    ->field('since', 'Customer since', ['type' => 'date'])
    ->field('newsletter', 'Newsletter', ['type' => 'bool'])
    ->locale('de')                                       // 1.500 is fifteen hundred, 1.234,50 an amount
    ->limits(5000, 2 * 1024 * 1024)                      // rows and bytes: a larger file is refused
    ->hidden(['_token' => 'the-csrf-token-of-the-page']); // carried through every step

// Check the page's CSRF token first, then:
$import->handle();                                       // reads $_POST and $_FILES of this importer
if ($import->step() === 'done') {
    foreach ($import->result()['rows'] as $row => $values) {
        // $row: the row in the sheet (the header is row 1). $values, typed:
        // ['customer_no' => 'K-1001', 'email' => 'anna@example.at', 'revenue' => '1234.50', 'newsletter' => true, ...]
    }
}
$import->render();                                       // the step it stands at
```

**The steps.** `step()` says where the importer stands; `render()` draws it.

| Step | What the person sees | What posts |
|---|---|---|
| `upload` | A drop box around the file field (`.csv`, `.txt`, `.tsv`), the limits in words, and in a disclosure a box for pasted text. "Check file". A refused file is said here (`role="alert"`). | `customers[file]` or `customers[text]`, `customers[step]=upload` |
| `map` | The file in one line (name, size, rows); notes (a field chosen twice, a quote never closed); **how it is read** — separator, encoding, the header checkbox, preset to what was found; **which field each column fills** — per column its name, two sample values and a select of the fields (or "ignore"; required fields marked); a message for every required field no column fills; the **summary** ("238 rows, 3 with errors.", `role="status"`); the **preview** of the first `preview()` rows (a `.gk-table` in a box that scrolls sideways by itself; per row its number and "OK" or "2 errors", the message under each wrong cell); **every row with errors**, the rows the preview does not show included (the first 100); the buttons "Import the 235 valid rows" (off while a required field has no column or no row is valid), "Check again" and "Cancel". | `customers[map][i]` (a field key or `''`), `delimiter`, `encoding`, `header`, `cols`, and `token` (or the file itself, see storage) |
| `done` | "235 rows imported; 3 with errors left out." and "Import another file". Take `result()['rows']` before you render — and if saving fails, show your own message instead. | |

"Check again" checks the posted mapping; "Cancel" forgets the file and goes back to the start
("The import was cancelled. Nothing was imported."). Import is only possible when every required
field has a column and at least one row is valid; rows with errors are left out — the button says
how many are imported.

**Fields** — `->field($key, $label, $opts)`; the key is letters, digits, `_`, `.` and `-`:

| Option | |
|---|---|
| `type` | `text` (default), `email`, `number`, `money`, `date`, `bool`, `select` — see below |
| `required` | a row without a value here is not imported; a required field without a column stops the import |
| `unique` | two rows of the file may not hold the same value: the second and every later one get "Already in row 5." (text and e-mail ignore case) |
| `aliases` | other column names that mean this field: `['E-Mail', 'Mail', 'E-Mail-Adresse']` |
| `options` | `select`: `value => label`, or a list of values |

**Types** — what a cell may hold, and what `result()` hands over. Spaces around a value (the
no-break space too) are ignored; an empty cell is `null` (`bool`: `false`) unless the field is
required.

| Type | Accepts | In `rows` |
|---|---|---|
| `text` | anything | the text |
| `email` | an address mail servers take: ASCII, a dot in the domain, a TLD of letters (or `xn--`) | the address, its domain in lower case |
| `number` | `1.234,5`, `1,234.5`, `1234.5`, `1 234,5`, `-12` — `LineItems::parseNumber()`: with both signs the last is the decimal sign; one sign before exactly three digits is decided by `locale()` | a string in machine form: `'1234.5'` |
| `money` | the same, at most 2 decimals; a currency sign or code is ignored | `'1234.50'` |
| `date` | `31.12.2026`, `2026-12-31`, `31/12/2026`, day and month with or without a zero; `31.02.` is an error | `'2026-12-31'` |
| `bool` | `ja`, `j`, `yes`, `y`, `x`, `1`, `true`, `wahr`, `on` / `nein`, `n`, `no`, `0`, `false`, `falsch`, `off`, empty | `true` / `false` |
| `select` | an option's value or its label; case, umlauts and spaces do not count | the option's value |

Numbers and amounts are strings so nothing is lost to a float: cast them where you store them.

**Reading the file** (`Import::parseCsv()` does it, and the script the same):

- **Encoding**: a byte order mark decides (UTF-8, UTF-16 LE or BE); a file with NUL bytes in every
  other place is UTF-16 without one; valid UTF-8 is UTF-8; anything else is **Windows-1252** — what
  German Excel writes, and a superset of ISO-8859-1, which is read as Windows-1252 the way browsers
  do. Everything comes out as UTF-8. A file with NUL bytes otherwise is "not a text file".
- **Separator**: `;`, `,`, tab or `|` — the one that gives the first 50 records the same number of
  cells most often (more than one); a tie goes to that order. A first line `sep=;` (Excel's hint)
  names it and is not a row. When none splits the lines, the one that occurs least, so a single
  column with a comma in one value stays one column.
- **Quotation mark**: `"` (RFC 4180: a doubled `""` is a quote, a quoted cell may hold the separator
  and line breaks), or `'` when the file quotes its cells with that. A quote inside an unquoted cell
  is a character. A quote that is never closed takes the rest of the file into one cell — said as a
  note ("Row 5 opens a quotation mark that is never closed…").
- **Header row**: yes when a cell of the first row names a field (key, label or alias); no when a
  cell holds what data holds (a number, a date, an e-mail address); yes otherwise. Without one the
  columns are "Column 1", "Column 2", … and nothing is matched by name.
- **Rows**: CRLF, LF and CR all end a row; a line break inside quotes does not, and becomes `\n`.
  Row numbers are those of the sheet: the header is row 1, an empty line is a row that is left out,
  a cell with a line break is still one row. Columns with no name and nothing in them at the end
  (Excel's `;;;;`) are dropped.

**Matching.** Column names are compared with the fields' keys and labels first, then with their
aliases, from the left: lower case, accents folded, an umlaut matching both its spellings (`ue`
and `u`), the sharp s as `ss`, signs and spaces dropped — "E-Mail" is "email", "Mueller" and
"Muller" are the same name with an umlaut.
A field takes one column; in the browser, choosing it for another column lets go of the first.
`->map(['E-Mail' => 'email', 3 => 'country'])` starts from a mapping of your own instead (one saved
from the last import, say): column name or index => field key, `null` to ignore; columns it does
not name are ignored. What the person chooses on the page wins over both — but only for the very
columns it was drawn for (the form posts their signature in `cols`).

**`result()`** — what the file holds, checked:

| Key | |
|---|---|
| `rows` | the valid rows, keyed by their row number: `[2 => ['email' => 'anna@example.at', ...], ...]`; only mapped fields are there |
| `errors` | row number => `[field key => message]` for every row that is left out |
| `total`, `valid`, `invalid` | counts of data rows (empty lines are not rows) |
| `columns` | the column names |
| `mapping` | column index => field key, or `null` when ignored |
| `missing` | required fields that no column fills |
| `notes` | what the person should know: a field chosen twice, a quote never closed |
| `meta` | `filename`, `bytes`, `encoding`, `bom`, `delimiter`, `enclosure`, `lineEnding` (`CRLF`, `LF`, `CR` or `''`), `header`, `sep`, `unclosed` |
| `error` | why the file was refused, or `null` |

**Without a form** — a cron job, an API, a sample:

```php
$csv = "Kundennummer;Nachname;E-Mail;Umsatz\r\nK-1;Mueller;anna@example.at;1.234,50\r\nK-2;Huber;max@;12\r\n";
$r = (new Import('api'))
    ->field('no', 'No.', ['required' => true, 'aliases' => ['Kundennummer']])
    ->field('name', 'Name', ['aliases' => ['Nachname']])
    ->field('email', 'E-mail', ['type' => 'email', 'required' => true])
    ->field('revenue', 'Revenue', ['type' => 'money', 'aliases' => ['Umsatz']])
    ->locale('de')
    ->fromString($csv, 'customers.csv')                  // or ->fromFile('/path/customers.csv')
    ->result();
// $r['rows'] === [2 => ['no' => 'K-1', 'name' => 'Mueller', 'email' => 'anna@example.at', 'revenue' => '1234.50']]
// $r['errors'] === [3 => ['email' => 'Not a valid e-mail address.']]

$parsed = Import::parseCsv($csv, ['delimiter' => 'auto', 'header' => true]);
// ['columns' => ['Kundennummer', 'Nachname', 'E-Mail', 'Umsatz'], 'rows' => [2 => [...], 3 => [...]], 'meta' => [...], 'error' => null]
```

`Import::parseCsv($bytes, $opts)` reads a CSV without fields: `columns`, `rows` (row number =>
cells, padded to the columns), `meta`, `error`. Options: `delimiter`, `enclosure`, `encoding`,
`header` (as the setters), `maxRows`, `maxBytes`, `names` (column names that mark a header row).

**Limits.** `->limits($rows, $bytes)` — default 10,000 rows and 5 MB, at most 100,000 and 20 MB; a
row may have at most 200 columns. A larger file is **refused, never cut short**, with a sentence
that says so ("The file has more than 10,000 rows. Import it in parts of at most 10,000 rows.",
"The file is 7.4 MB; at most 5 MB can be imported."). An upload larger than `upload_max_filesize`
or `post_max_size` is said in words too. The script refuses the same before it reads a byte.

**Between the steps — and why nothing from the browser is trusted.** Without script the file has
to wait for step 2 and 3. By default it waits **on the server**: `->storage($dir, $ttl)` (default:
the system's temporary directory `/gridkit-import`, 3600 seconds) keeps the raw file under a token
of 128 random bits (`random_bytes`), in a file only the server user may read; the form carries only
the token. With a PHP session the token also belongs to that session, so another browser cannot use
it. Files older than `$ttl` are removed whenever an importer is handled; "Cancel" and the import
remove their own at once. `->storage(false)` keeps **nothing** on the server: the file travels in
the form (base64), for several servers without a shared directory, or a public demo. Either way
**every step reads the file again and checks every row again**, with the mapping as posted (each
key checked against the fields) — the server never takes a row, a value or a count from the
browser, so a forged form is no more than another file. With the script the browser shows step 2
itself and the form posts the file and the mapping once (`step=confirm`); the server reads and
checks it as above. Checking the page's CSRF token before `handle()` is the page's job — put the
token in `->hidden()`.

**Methods**

| Method | |
|---|---|
| `->field($key, $label, $opts)` | a field a column can fill (above) |
| `->label('Import customers')` | the form's accessible name; default "CSV import" |
| `->locale('de')` | how a lone separator is read in numbers; default `Lang::locale()` |
| `->limits($rows, $bytes)` | the most a file may hold |
| `->preview(20)` | rows in the preview (1 to 500); every row is checked either way |
| `->delimiter('auto')` | `'auto'`, `';'`, `','`, `"\t"` (or `'tab'`) or the vertical bar |
| `->enclosure('auto')` | `'auto'`, `'"'`, `"'"` |
| `->encoding('auto')` | `'auto'`, `'UTF-8'`, `'Windows-1252'` (`'ISO-8859-1'`), `'UTF-16LE'`, `'UTF-16BE'` |
| `->header(null)` | `true`, `false`, or `null` to decide from the file |
| `->map([...])` | the mapping to start from (above) |
| `->action('/admin/import')` | where the form posts; default the page itself |
| `->hidden(['_token' => $csrf])` | hidden fields carried through every step |
| `->storage($dir, $ttl)` / `->storage(false)` | where the file waits between the steps (above) |
| `->handle($post = null, $files = null)` | reads the POST of this importer (default `$_POST`, `$_FILES`) and moves it on a step |
| `->fromString($bytes, $filename)` / `->fromFile($path)` | a CSV without an upload; the importer is then in step `map` |
| `->step()` | `'upload'`, `'map'` or `'done'` |
| `->result()` | the checked rows (above) |
| `->render()` / `->html()` | prints / returns the step it stands at |
| `Import::parseCsv($bytes, $opts)` | a CSV read without fields (above) |

A wrong option value is an `E_USER_WARNING` with a fallback that still works: an unknown type
(text), an unknown option, a field defined twice (the second is left out), a key that cannot be a
field name, a select without options (text), a limit out of range (clamped), a `javascript:`
action (the page itself), a name that is not a field name (cleaned).

**In the browser** (`js/import.js`; nothing to call): a file chosen or dropped on the box is read
with `FileReader`, decoded with `TextDecoder` (Windows-1252, UTF-16 and UTF-8 by the same rules) and
split, matched and checked by the same rules as the server — the same counts, rows, messages and
order. Text typed into the paste box is read a moment after the typing stops. A change of the
separator, the encoding or the header checkbox reads the file again; a change of a column's field
checks again and redraws what follows the mapping; the summary is a live region, so a screen reader
hears "238 rows, 3 with errors." The file stays in the file field (a dropped one too), so
"Import" posts it with the mapping. "Cancel" empties the form without a request. A step 2 the server
drew with `storage(false)` is taken over the same way. A choice that makes the file unreadable
(UTF-8 for a Windows-1252 file) is said, and the file is read as before — on the server as well.

| Event (on the form, bubbling) | `detail` |
|---|---|
| `gk:importread` | `{ import, filename, bytes, meta, columns, total }` — a file or text was read |
| `gk:importmap` | `{ import, mapping, source }` — `source`: `auto`, `user` or `api` |
| `gk:importready` | `{ import, result }` — the rows were checked, after every change; `result` as `result()` |
| `gk:importerror` | `{ import, message }` — a file was refused (too large, too many rows, not text) |

```javascript
GK.import.read('customers', file, 'customers.csv');   // a File, Blob or bytes as if chosen (it goes into the file field,
                                                       // so Import posts it); text without a name as if pasted. A Promise of the result
GK.import.map('customers', { 'E-Mail': 'email', 4: null });  // set the mapping; without a second argument, read it
GK.import.result('customers');                         // what result() would say about what the browser holds
GK.import.check('customers', 'revenue', '1.234,5');    // { value: '1234.50', error: null } — one value by a field's rules
GK.import.reset('customers');                          // back to step 1
GK.import.parse(bytesOrText, { delimiter: 'auto' });  // Import::parseCsv() without an importer
```

The first argument is the importer's name, its form, an element inside it, or a selector.

- **Accessible**: every control has a label (a column's select is "Column E-Mail"), the preview is
  a named region with a tab stop and a caption, the steps are `.gk-steps` with `aria-current`, a
  refused file is an alert, the summary a status. **Errors are never colour alone**: a symbol, the
  words under the cell, "2 errors" in the row's check column, a bar at the cell's start; an ignored
  column says "ignored" and has a dashed frame.
- **Phone**: the mapping is one column of cards, the options and buttons full width, the fields
  16px; only the preview scrolls sideways, inside its box.
- **Classes** (all `gk-import-*`, in `css/blocks.json` as "Import"): `gk-import` (the form),
  `-upload`, `-drop`, `-drop-icon`, `-drop-text`, `-hint`, `-paste`, `-text`, `-dragover` (a file
  over the box), `-panel`, `-source`, `-notes`, `-options`, `-option`, `-map`, `-col`, `-col-name`,
  `-col-sample`, `-ignored`, `-missing`, `-summary`, `-preview`, `-target`, `-rowno`, `-state`,
  `-badrow`, `-bad`, `-msg`, `-errors`, `-errors-title`, `-more`, `-actions`, `-skip`. Colours are
  roles: light, both dark spellings and the six themes follow.
- **Words** come from `import.*` in `lang/` and reach the script inside the importer's own config —
  no `Lang::jsConfig()` needed.
- **CSV injection** (a cell like `=HYPERLINK(...)` that a spreadsheet would run) concerns the other
  direction: writing CSV for Excel. An import stores text and runs nothing; escape on output as
  always. Guarding an export is not this component's job.
- **Do not** put the importer inside another `<form>`, take rows or counts from the POST, or
  trust `result()` of a request you did not `handle()` — and check the CSRF token before `handle()`.

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

### PermissionMatrix (since 1.115.0)

Roles as columns, permissions as rows grouped by module, a checkbox where they meet — the screen
of a user and rights administration. **It is a form first:** put it inside your own `<form>`
(with your CSRF field and a submit button); `render()` writes no `<form>` of its own, so it can sit
in a form that edits other things too.

```php
$matrix = (new PermissionMatrix('rights'))
    ->caption('Roles and rights')                       // for a screen reader, and the box's name
    ->role('admin', 'Admin', ['locked' => true, 'color' => 'red'])
    ->role('accounting', 'Accounting', ['description' => 'Invoices and payments', 'color' => 'green'])
    ->role('editor', 'Editor', ['parent' => 'guest', 'icon' => 'edit'])
    ->role('guest', 'Guest')
    ->group('invoices', 'Invoices', ['description' => 'Billing', 'icon' => 'receipt'])
    ->permission('invoices.view', 'View invoices')
    ->permission('invoices.edit', 'Create and edit invoices', ['requires' => 'invoices.view'])
    ->permission('invoices.delete', 'Delete invoices', ['requires' => ['invoices.edit'], 'danger' => true,
        'description' => 'Invoices have to be kept for seven years.'])
    ->group('users', 'Users & rights', ['collapsed' => true])
    ->permission('users.roles', 'Change roles and rights', ['danger' => true])
    ->grants(['accounting' => ['invoices.view', 'invoices.edit'], 'guest' => ['invoices.view']])
    ->grant('editor', 'users.roles');                  // adds to grants()

echo '<form method="post">';
$matrix->render();
echo Button::render('Save', ['type' => 'submit']) . '</form>';
```

- **`->role($key, $label, $opts)`** — `description` (a line under the name), `parent` (the key of
  the role whose rights this one inherits, through its parent's parent too), `locked` (`true`:
  every permission, read-only, posts nothing — the administrator), `color` (`blue`, `green`,
  `orange`, `red`, `gray`: the name as a `.gk-label`), `icon` (a Material icon name).
- **`->group($key, $label, $opts)`** — the permissions after it belong to it: a heading row with a
  fold button and the group's bulk toggles. `description`, `icon`, `collapsed` (starts folded —
  only with the script; without it every row shows). Permissions before the first group stand
  above it without a heading.
- **`->permission($key, $label, $opts)`** — `description`, `danger` (`true`: a risky right, marked
  "Risky" in words and ticked in the error colour), `requires` (a key or a list of keys).
- **`->grant($role, ...$permissions)` / `->grants([$role => [$permission, …]])`** — what each
  role was given itself, as your database holds it; both add. Unknown roles and permissions are
  reported (`E_USER_WARNING`) and left out.
- **`->name('perm')`** — the field name (default `perm`). A nested one works: `->name('user[rights]')`
  posts `user[rights][role][permission]`, read back from `$_POST['user']['rights']`.
- **`->readonly()`** shows the rights and posts nothing; **`->height('480px')`** is how high the
  matrix grows before it scrolls in its own box (default `70vh`); **`->saveUrl()`** see below.

Keys may contain dots, slashes and umlauts; a key with a bracket, a control character, nothing
in it or spaces around it cannot be a field name and is refused with a warning. So are a colour
that is not one of the five, a `locked` that is not a boolean, a `name()` PHP would not read back
as that array, a `height()` that is not a CSS length and a `saveUrl()` that is not http(s) or
relative. A `parent` that names no role, or a chain that loops, is reported when the matrix
renders and the role inherits nothing; a `requires` that names no permission is left out.

**The posted shape: `perm[role][permission]`, every cell, `0` or `1`.** Each cell writes a hidden
`0` and, after it, the checkbox's `1` — PHP keeps the last value, so a ticked cell reads `"1"`
and an unticked one `"0"`. That is what tells "unticked" from "not on this page". A locked role
posts nothing; an inherited cell posts `0` — the heir does not have it itself, and inheritance is
not stored: it follows from `parent`. (A role that has a right of its own *and* through its parent
shows it as its own, removable; unticked, it is then shown inherited.) Each editable cell is up to two fields: a matrix that could
post more than `max_input_vars` (PHP drops the rest without a word) says so with a warning.

**Reading it back** — build the matrix as for the page, then:

```php
$matrix = (new PermissionMatrix('rights'))
    ->role('editor', 'Editor', ['parent' => 'guest'])
    ->role('guest', 'Guest')
    ->permission('cms.view', 'View pages')
    ->permission('cms.edit', 'Edit pages', ['requires' => 'cms.view']);
$_POST = ['perm' => ['editor' => ['cms.view' => '0', 'cms.edit' => '1'], 'guest' => ['cms.view' => '0', 'cms.edit' => '0']]];

$map = $matrix->read($_POST['perm'] ?? []);
// ['editor' => ['cms.view' => true, 'cms.edit' => true], 'guest' => ['cms.view' => false, 'cms.edit' => false]]
foreach ($map as $role => $cells) {
    foreach ($cells as $permission => $granted) { /* $granted ? grant : revoke */ }
}

// Without the definition at hand: only the roles and keys you allow, only 0/1/true/false.
$map = PermissionMatrix::fromPost($_POST['perm'] ?? [], ['editor', 'guest'], ['cms.view', 'cms.edit']);
```

`PermissionMatrix::fromPost($posted, $roles, $permissions)` returns role → permission → bool for
**exactly the cells that were posted**: a cell not on the page is not in the result (a matrix
that showed one module cannot wipe the others), unknown roles and keys, arrays and any value but
`"0"`, `"1"`, `0`, `1`, `true`, `false`, `""` are dropped, a non-array is `[]`. Leave a locked
role out of `$roles`. **`$matrix->read($posted)`** does the same with the matrix's own editable
roles and permissions and makes `requires` whole the way ticking does in the browser: a granted
permission gets what it needs added (unless the role inherits it). A `readonly()` matrix reads `[]`.

**Inheritance.** A role with `parent` has its own grants plus everything the parent has. Such a
cell is shown ticked with **"via Guest"** under it and a dashed ring (a state in words and shape,
not colour alone), described to a screen reader as "Inherited from Guest — change it in the Guest
column", and cannot be unticked there: a click says so in a toast. Tick something for the parent
and it appears in the heirs at once; untick it and an heir that also has it as its own keeps it.

**`requires`.** Ticking a permission ticks what it needs, through what that needs. Unticking a
permission unticks what needs it — in that role and in the roles that inherit from it, which lost
it too. Both are said in the status line ("Also granted, because it is needed: …"). A cell that
something else needs is described as "Needed by …". Only what the change touches is looked at.

**Bulk, filter, folding, changes.** With the script: an "All roles" column toggles a row for every
role that can change; the toggle in a role's head toggles the column for the rows in view (filter
and folding count); a group's heading row has a toggle per role and one for all roles. Each is
mixed (indeterminate) while only some are granted; ticked → untick what can be unticked, else
tick what can be ticked — locked and inherited cells are left alone, and a grant for all roles goes
to the parent first, so heirs inherit it rather than get their own. The filter (label, key,
description, group) shows matches across folded groups and folds them back when cleared. Changed
cells are tinted with a dot until saved; the toolbar counts them ("3 changes") and Reset undoes
them; per-role counts ("7 of 20") follow. A legend explains the marks.

**Events and script API.** After every change `gk:permchange` bubbles from the matrix with
`detail = { matrix, role, permission, granted, kind, changed, changes }` — `kind` is `cell`,
`row`, `role`, `group`, `group-all`, `reset` or `revert`; `changed` lists this change's cells
(`{ role, permission, granted }`, the needed ones included), `changes` everything not saved yet.
It is **cancelable**: `preventDefault()` puts the change back (to ask before granting a risky
right, say). `GK.permMatrix.changes(el)` returns what is not saved, `GK.permMatrix.reset(el)` goes
back to the last saved state, `GK.permMatrix.commit(el)` makes the current state the saved one
(call it when an AJAX form has saved), `GK.permMatrix.init(root)` binds matrices added later
(`GK.initContent()` does it for you). `el` is the matrix, an element in it, or its id.

**Saving as you go:** `->saveUrl('/api/permissions.php', ['headers' => ['X-CSRF-Token' => $token]])`.
Each change is POSTed as JSON — `{"matrix":"rights","changes":{"editor":{"cms.edit":true}}}`,
one request per change, one after the other — and the answer must be `{"ok": true}`. Anything
else (`{"ok": false, "error": "…"}`, an error status, no network) puts the cells back and shows
the `error` as text in a toast. The endpoint reads the body with
`PermissionMatrix::fromPost($body['changes'], $roles, $permissions)` or `$matrix->read(...)`.
There is no Reset then; the toolbar says "Saving …" / "Saved.". Without the script the fields post
with the surrounding form as before.

**Keyboard and screen reader.** The matrix is a real `<table>`: roles are `<th scope="col">`,
permissions `<th scope="row">`, a group `<th scope="rowgroup">`, each checkbox is named
"Role: Permission". With the script the table becomes an ARIA grid with **one tab stop**: the
arrow keys move between cells (headers and toggles included), Home/End to the row's ends,
Ctrl+Home/End to the corners, PageUp/PageDown by ten rows, Space toggles; Tab leaves the grid and
comes back to the cell last used. Locked, inherited and read-only cells are `aria-disabled`
rather than disabled, so the arrow keys still reach them and they say why. The scroll box is a
named region with a tab stop; the role row and the permission column stick in it.

**Phone (≤ 768px):** a matrix does not fit, so a "Role" select appears and the matrix shows one
role's column at a time as a list of permissions — no "All roles" column, cells 48px high, the
head sticking under GridKit's header (`--gk-sheet-top`), no sideways scroll. Without the script
the box scrolls sideways inside itself instead; the page does not.

**Without the script** (a copy of `gridkit.js` without the 1.115.0 block) it is the plain form:
the toolbar, the bulk toggles, the fold buttons and the select stay hidden, inherited and locked
cells are disabled checkboxes, every row shows, and the post is the same shape. Words come from
`perm.*` in `lang/` and reach the script inside the matrix's own config — no `Lang::jsConfig()`
needed. Classes: `.gk-perm` (the card), `.gk-perm-table`, `.gk-perm-cell` with the states
`.gk-perm-inherited`, `.gk-perm-locked`, `.gk-perm-changed`, `.gk-perm-danger` on a risky row.

### Tree (`GridKit\Tree`, since 1.116.0)

A tree view for page trees, categories and folders. The server writes a plain nested list that
works without any script — a link is followed, a ticked box is posted, every node is in view.
**`js/tree.js`** makes it a WAI-ARIA tree view; it is a file of its own like `js/sortierbar.js`
(the PawBot dashboard keeps its own `gridkit.js`), loaded with `defer` after `gridkit.js` —
`skeleton.php` and the demo do it; a page of your own adds
`<script src=".../js/tree.js" defer></script>`. There is no call to make: the script finds every
`.gk-tree`, also one inserted later. `Tree::render()` prints.

```php
$pages = [
    ['id' => 1, 'parent_id' => null, 'title' => 'Home', 'icon' => 'home', 'href' => '/admin/pages/1', 'draggable' => false],
    ['id' => 2, 'parent_id' => null, 'title' => 'About us', 'icon' => 'folder'],
    ['id' => 3, 'parent_id' => 2, 'title' => 'Team', 'href' => '/admin/pages/3'],
    ['id' => 4, 'parent_id' => 2, 'title' => 'History', 'muted' => 'unpublished'],
    ['id' => 5, 'parent_id' => null, 'title' => 'Blog', 'icon' => 'article', 'children' => 'lazy', 'count' => 12],
];
(new Tree('pages'))
    ->label('Pages')                                   // the accessible name of the tree
    ->nodes($pages)                                    // map: id => 'id', parent => 'parent_id', label => 'title'
    ->select('single')->name('page')->selected([3])    // posts page=3
    ->expandTo('3')                                    // opens the path, the keyboard starts there
    ->searchable()
    ->lazyUrl('/admin/pages/children')                 // GET ?parent=5 when Blog is opened
    ->moveUrl('/admin/pages/move', ['headers' => ['X-CSRF-Token' => 'token']])
    ->actions(fn (array $n): array => [
        ['icon' => 'edit', 'title' => 'Edit', 'href' => '/admin/pages/' . $n['id'] . '/edit'],
        ['icon' => 'delete', 'title' => 'Delete', 'onclick' => "deletePage('{$n['id']}')", 'showIf' => $n['children'] === 0],
    ])
    ->render();
```

Nested arrays work as well, and so do single nodes — mixed as you like:

```php
(new Tree('cats'))
    ->label('Categories')
    ->select('multi')->name('cats')->selected(['books'])   // posts cats[]=books&cats[]=novels
    ->nodes([
        ['id' => 'books', 'title' => 'Books', 'children' => [
            ['id' => 'novels', 'title' => 'Novels'],
            ['id' => 'rare', 'title' => 'Rare editions', 'disabled' => true],
        ]],
        ['id' => 'music', 'title' => 'Music', 'children' => 'lazy'],
    ])
    ->node('games', 'Games', null, ['badge' => ['text' => 'New', 'color' => 'green']])
    ->node('consoles', 'Consoles', 'games')                // the parent may also come later
    ->render();
```

| Method | What it does |
|---|---|
| `->nodes(array $rows, array $map = [])` | Flat rows (each names its parent) or nested arrays (each carries `children`). `$map` renames the fields: `'id'`, `'parent'` (default `parent_id`), `'label'` (default `title`), `'children'`, and any node option (`'muted' => 'hidden'`). Every node option is read from the row under its own name unless the map says otherwise. A parent of `null`, `''` or `0` is the top level (`0` only while no node has the id 0). Rows may be objects. |
| `->node($id, $label, $parent = null, $opts = [])` | One node. `$parent` may be added later; `'children'` takes nested rows here too. |
| `->select('none' \| 'single' \| 'multi')` | `none` (default); `single` — one node, `aria-selected`, a radio button without script; `multi` — checkboxes with tri-state parents. |
| `->selected(array $ids)` | Selected when the page opens. In `multi` a selected parent ticks its branch, and a parent whose enabled children are all ticked is ticked itself — on the server and in the script alike. |
| `->name('pages')` | The field the selection posts under: `pages[]=id` (multi), `pages=id` (single). Default: the tree's id. |
| `->expandTo($id)` | Opens the ancestors of a node (whatever the browser remembers) and makes it the node the keyboard starts on. Call it for several. |
| `->lazyUrl($url)` | Where the children of a `'children' => 'lazy'` node come from (see "Lazy branches"). |
| `->moveUrl($url, ['headers' => [...]])` | Nodes can be moved; every move is posted here (see "Moving"). `headers` go with every move. |
| `->movable()` | Nodes can be moved without a URL: the page hears `gk:treemove` / `gk:treemoved` and saves itself. |
| `->searchable()` | A filter field above the tree. |
| `->toolbar(false)` | No bar above the tree (filter, Expand all, Collapse all). Without the filter and without parents there is none anyway. |
| `->remember(false)` | The browser does not keep which nodes were open (default: it does, in `localStorage` under `gk-tree:<id>`). |
| `->label('Pages')` | The accessible name of the tree; default the catalogue's "Tree". |
| `->actions(fn (array $node): array)` | Row buttons for every node. `$node` holds `id`, `label`, `parent`, `level`, `children` (their number), `opts` and `row` (what `nodes()` was given). Added after the node's own `actions`. |
| `->render()` | Prints the tree. |
| `->renderBranch($parent)` | Prints the `<li>` of the children of `$parent` and their branches — the answer to a lazy request. |

**Node options** (in `node()`'s `$opts`, or as fields of a row):

| Option | |
|---|---|
| `icon` | Material icon name before the label. |
| `href` | The label is a link (Enter on the node follows it). A `javascript:` URL is refused with a warning. |
| `badge` | Text, or `['text' => 'Draft', 'color' => 'orange']` — a `gk-label` (gray, green, orange, red, blue). |
| `count` | A number in a pill after the label (posts in a folder). |
| `muted` | `true` or a word (`'unpublished'`): the label in italics and the quieter colour; the word is read with the name ("inactive" by default). |
| `expanded` | Open when the page opens (unless the browser remembers otherwise). A lazy node that starts open is fetched at once. |
| `children` | Nested rows, or `'lazy'`: the node is a parent whose children come from `lazyUrl()`. |
| `actions` | Row buttons, ActionGroup items: `icon`, `label`, `href`, `onclick`, `title`, `color`, `variant`, `size`, `pill`, `disabled`, `class`, `showIf` (falsy: not drawn). Described by the node's name for a screen reader. |
| `disabled` | Cannot be selected (its box is disabled and not posted), moved or dropped into; no link. Its children are not disabled. |
| `draggable` | `false`: this node keeps its place (no handle); others can still be dropped beside or into it. |

Yes/no options take `true`/`false`, `1`/`0` or `'1'`/`'0'`/`'yes'`/`'no'` (what a database hands
over); anything else is dropped with a warning — `'expanded' => 'no'` is true to PHP.

- **What a screen reader gets** (with the script): `role="tree"` with the label,
  `aria-multiselectable` in `multi`; every node a `treeitem` with `aria-level`, `aria-setsize`,
  `aria-posinset`, `aria-expanded` (parents only), `aria-selected` (single) or `aria-checked`
  `true`/`false`/`mixed` (multi), named by its label, muted word, badge and count — never by its
  children or buttons. The boxes and radios stay in the form for posting, `aria-hidden` and out of
  the tab order. A polite live region speaks the moves, the matches and a branch that failed.
- **Keys** (WAI tree pattern, roving tabindex — one tab stop per tree): Up/Down move through the
  nodes in view, Right opens a parent or goes to its first child, Left closes it or goes to the
  parent, Home/End, `*` opens every parent beside the focused node, a letter (or several typed
  together) jumps to the next node that starts with it. Enter follows the link, or selects / ticks,
  or opens and closes; Space selects or ticks. Tab from a node reaches its own row buttons and its
  handle, then leaves the tree. In `rtl` Left and Right swap.
- **Open and closed** is kept per tree id in `localStorage` (`gk-tree:<id>`, `{open, closed}`,
  read and written in `try`); a node the browser has never seen keeps the server's state.
  "Expand all" opens what is loaded — never a lazy branch, which may hold a thousand posts.
  Without the script every branch is open; `@media (scripting: enabled)` keeps a closed one closed
  from the first paint.
- **The filter** (`searchable()`) hides what does not match, keeps the ancestors of a match (opened)
  and the branch of a matching parent, marks the match (`mark.gk-tree-hit`), ignores case and
  accents, says "3 matches" / "No matches", and Escape brings the tree back as it was. It searches
  the loaded nodes. Nothing can be moved while it filters.
- **Long names** are cut with an ellipsis; while a name is cut it gets a `title` (GK.tip shows it),
  and the focused node shows its name in full. Deep trees indent up to eight levels (five on a phone),
  then a small level number takes over — nothing pushes a 390px page sideways.

**Lazy branches.** A node with `'children' => 'lazy'` is a closed parent with an empty group. Opened
the first time, the script asks `GET lazyUrl?parent=<id>` (`Accept: text/html`,
`X-Requested-With: XMLHttpRequest`, `credentials: same-origin`) and puts the `<li>` of the answer
into the group — write them with `renderBranch()`, on a tree configured as on the page (select, name,
actions, movable), so the children look and post the same. A child may be `'lazy'` itself. An empty
answer makes the node a leaf; a node the tree already has is not taken twice. A status other than
2xx or no network: the node closes again and its row says "Not loaded." with a "Try again" button
(opening it again does the same). A selected id inside a branch not loaded yet travels as
`<input type="hidden" data-gk-tree-pending>`, and its node takes over when it arrives.

```php
// GET /admin/pages/children?parent=5 — the children of one node, nothing around them
$parent = (string) ($_GET['parent'] ?? '5');
$children = [
    ['id' => 51, 'parent_id' => 5, 'title' => 'Spring sale'],
    ['id' => 52, 'parent_id' => 5, 'title' => '2025', 'children' => 'lazy'],
];
(new Tree('pages'))->select('single')->name('page')->nodes($children)->renderBranch($parent);
```

**Moving.** With `moveUrl()` or `movable()` every node that may move (not disabled, `draggable` not
false) gets a handle (`.gk-tree-handle`, six dots, the only part that takes a finger).
**With the pointer**: drag the handle; over the top quarter of a row a line shows "before", over the
bottom quarter "after" (below an open parent: its first child), over the middle the row is framed:
"into it, last". Resting on a closed parent opens it (a lazy one loads). Escape cancels. **With the
keyboard**: Tab to the handle, Space lifts the node (`aria-pressed`), Up/Down move it among its
neighbours, Home/End to the ends, Right puts it into the node above it (as its last child), Left
takes it out of its parent (just after it), Space puts it down, Escape — or leaving the handle —
gives it back; each step is announced ("Level 2, position 1 of 3, in About us."). **Refused**, with
a word for a screen reader: into itself or its own branch, into a disabled node, and anything while
the filter is on.

Every move fires `gk:treemove` first — **cancelable**: `preventDefault()` and the node stays (a
keyboard move goes back). With `moveUrl()` it is then posted, one at a time in the order made:
`POST` with `Content-Type: application/json`, `credentials: same-origin`,
`X-Requested-With: XMLHttpRequest`, the `headers` of `moveUrl()` and the body
`{"id": "3", "parent": "5", "index": 0}` — `parent` is `null` for the top level, `index` counts from 0
among the new siblings (the place the node now has). A 2xx is saved unless its JSON says
`{"ok": false}`. A refusal, another status or no network puts the node back where the server last
agreed — that move and every later one still waiting — shows the answer's `"message"` (or "The move
could not be saved. The entry is back in its place.") in a toast (the line `.gk-tree-error` above the
list where `gridkit.js` is not loaded), and fires `gk:treemoved` with `ok: false, reverted: true`.

```php
// POST /admin/pages/move — {"id": "3", "parent": "5", "index": 0}
$move = json_decode((string) file_get_contents('php://input'), true) ?: ['id' => '3', 'parent' => null, 'index' => 0];
// … check the token, refuse what your tree does not allow, store the new parent and order …
header('Content-Type: application/json');
echo json_encode(['ok' => true]);   // or http_response_code(409) and ['ok' => false, 'message' => 'Shown to the person.']
```

**Events**, on the `.gk-tree`, bubbling — for what the person does, not for calls of `GK.tree`:

| Event | `detail` |
|---|---|
| `gk:treeselect` | `{ tree, id, selected, ids }` — `ids`: every selected id (pending ones included) |
| `gk:treetoggle` | `{ tree, id, expanded }` |
| `gk:treemove` | `{ tree, id, parent, index, oldParent, oldIndex }` — cancelable, before the move |
| `gk:treemoved` | the same plus `ok` and `reverted` — after the save (at once without a URL), or after the way back |

**JavaScript API** (the first argument is the tree's id as given to `new Tree()`, its element, or a selector):
`GK.tree.expand(el, id)` opens a node and the path to it (without an id every loaded parent; a
Promise that waits for a lazy branch), `GK.tree.collapse(el, id)`, `GK.tree.select(el, ids, on)`
(ticks or selects one id or several, `false` unticks; returns the selected ids),
`GK.tree.selected(el)`, `GK.tree.reload(el, id)` (fetches a branch again and opens it).

- **Classes** (all `gk-tree-*`, in `css/blocks.json` as "Tree"): `gk-tree`, `-toolbar`, `-search`,
  `-list`, `-node`, `-open`, `-row`, `-toggle`, `-check`, `-radio`, `-icon`, `-label`, `-muted`,
  `-disabled`, `-badge`, `-count`, `-actions`, `-group`, `-handle`, `-hit`, `-empty`, `-error`,
  `-loaderror`, `-retry`, and while moving `-lifted`, `-dragging`, `-ghost`, `-ghost-no`,
  `-drop-line`, `-drop-inside`. Colours are roles: light, both dark spellings and the six themes
  follow (measured: text 4.5:1, chevron, handle and focus ring 3:1).
- **Words** come from `tree.*` (server) and `js.tree_*` (script) in `lang/`; the script's reach the
  page through `Lang::jsConfig()`.
- **Warnings** (`E_USER_WARNING`, the tree still renders): a duplicate id (the second node is left
  out), a parent the tree does not have (the node goes to the top level), a cycle (its first node is
  lifted to the top), an option it does not know or of the wrong type, a row without an id, a
  selected id the tree does not have (without lazy branches), an unknown `expandTo()` id, an unknown
  selection mode.
- **Do not** write the handle, the roles or the tabindex yourself, put a `<form>` inside a row, or
  give a node a second link — the label is the link.

### Calendar (since 1.120.0)

A month view for appointments, deadlines and campaigns. The server writes the month as a plain table —
a column per weekday, a row per week, today marked, every day named by its full date — with the events
as links in their days; the months before and after are links with a query parameter
(`?month=2026-12`). **It works as it is, without script.** `js/calendar.js` (the file `DateRange` uses
too, loaded after `gridkit.js`) makes the table a grid for the keyboard. `Calendar::render()` prints,
`->html()` returns the same string.

```php
$cal = (new Calendar('appointments'))
    ->label('Appointments')                         // the accessible name (default "Calendar")
    ->weekNumbers()                                 // a column with the ISO week
    ->preserve(['user'])                            // kept in the month links
    ->dayUrl('/appointments/new?date={date}');      // the day number is a link
['from' => $from, 'to' => $to] = $cal->visibleRange();   // the days the grid shows: fetch these
$rows = [   // e.g. SELECT … WHERE begins_at <= :to AND COALESCE(ends_at, begins_at) >= :from
    ['id' => 7, 'subject' => 'Team meeting', 'begins_at' => '2026-11-04 09:30', 'ends_at' => null, 'color' => 'blue'],
    ['id' => 8, 'subject' => 'Trade fair', 'begins_at' => '2026-11-10', 'ends_at' => '2026-11-13', 'color' => 'orange'],
];
$cal->events($rows, ['title' => 'subject', 'start' => 'begins_at', 'end' => 'ends_at'])
    ->event('2026-11-20', 'Invoice run', ['href' => '/runs/3', 'color' => 'red', 'allDay' => true])
    ->event(['start' => '2026-11-23', 'end' => '2026-12-04'], 'Winter campaign')
    ->render();
```

| Method | What it does |
|---|---|
| `new Calendar($id)` | The id: the element is `#gk-cal-<id>`, the script and the events name it. |
| `->event($when, $title, $opts)` | One event. `$when`: a day `'2026-11-04'`, a day and time `'2026-11-04 09:30'` (or `T09:30`), a `DateTimeInterface` (read in the time zone; midnight is no time), or `['start' => …, 'end' => …]` — the last day included — for one that lasts days. |
| `->events($rows, $map)` | Many from rows (arrays or objects): each has `date`, or `start` and maybe `end`, a `title` and any option under its own name; `$map` renames them (`['title' => 'subject', 'start' => 'begins_at']`). A row without a title or a date warns and is left out. |
| `->month('2026-11')` | The month shown. Default: the query parameter when it names a month (`2026-11`, years 1 to 9999), else this month. Anything else in the URL is ignored. |
| `->current()` | That month, `'Y-m'`. |
| `->visibleRange()` | `['from', 'to']`: the first and last day of the weeks shown — the range to query. |
| `->param('m')` | The query parameter of the month links (default `month`). |
| `->weekStart('monday')` | `'monday'` or `'sunday'`; default by the page's language: Sunday for English, Monday otherwise. |
| `->weekNumbers()` | A first column with the ISO 8601 week of each row (the week of its Monday), read as "Week 45". |
| `->timezone('Europe/Vienna')` / `->today('2026-11-04')` | The zone "today" is counted in (default `date_default_timezone_get()`), or a fixed today (`'Y-m-d'` or a `DateTimeInterface`). |
| `->baseUrl('/calendar')` / `->preserve(['user'])` | Where the month links lead (default the page; a `#fragment` stays last) and which query parameters they keep — names read from the URL, or `name => value`. |
| `->maxPerDay(3)` | Events a day shows before "+n more" (default 3, at least 1). |
| `->dayUrl('/new?date={date}')` | Makes every day number a link; `{date}` is the day. Enter on a day follows it. |
| `->label('Appointments')` | The accessible name of the table and the agenda (default "Calendar"). |
| `->render()` / `->html()` | Prints the calendar / returns it. |

**Event options:** `href` (the event is a link; a `javascript:`, `vbscript:` or `data:` URL warns and is
left out — the event is text), `color` (the label palette: `blue`, `green`, `orange`, `red`, `gray`;
without one, the theme's accent; anything else warns), `time` (text before the title; default the time
of the start), `allDay` (`true`: no time; read as yes/no like a database hands it over), `id` (handed to
the script's events; default `e1`, `e2`, … in the order added). An unknown option, an end before the
start (swapped), a date that is no day (the event is left out): `E_USER_WARNING`, and the calendar
renders.

- **A bar across days.** An event of several days is a segment in each day it covers, in the same lane of
  the week, so the bar is straight (an empty lane above it keeps its place); the title is written once
  per week and runs across the bar. Every segment still reads the title to a screen reader — a day says
  what is on it. In a list (the agenda, "+n more") such an event says its days ("Nov 10 – 13, 2026").
- **Order in a day:** events without a time (all day, several days) first, then by time, then as added.
- **"+n more".** A day that holds more than `maxPerDay()` shows one less and "+3 more" — a `<details>`
  that opens the whole day over the grid (no script needed; in the last three columns it opens towards
  the start). With the script, Escape closes it and gives the focus back, a click elsewhere closes it,
  and one is open at a time.
- **Today** is the day number in a filled disc, `aria-current="date"` and ", today" in its name. Days of
  the months around are on a quieter ground and keep their events.
- **On a phone** — whenever the calendar itself is narrower than 600px (a container query, so a side
  column counts too) — the grid gives way to an **agenda**: the days of the month that hold something, one
  under the other, each event with its time, "All day" or its days. Nothing scrolls sideways. A month
  without events says "Nothing this month."
- **What a screen reader gets**: a table named by its label and the month ("Appointments November 2026"), column headers with the
  whole weekday, every day's number read as "Wednesday, November 4, 2026"; the events are links (or,
  without `href`, buttons once the script runs) named by their time and title. Without the script it is a
  table of links in reading order.
- **Keys** (with the script: `role="grid"`, one tab stop — the day; Tab from it reaches that day's events
  and "+n more", then leaves): arrows move a day and a week (past the edge of the grid: that month),
  Home/End to the ends of the week, PageUp/PageDown a month (Shift: a year) — the page loads it and the
  same day takes the focus there (`sessionStorage`, read in `try`) — Enter and Space choose the day
  (Enter then follows its `dayUrl`). A click on a day chooses it too. "Today" in the month shown only
  moves the focus. In `rtl` Left and Right swap.
- **Events**, on the `.gk-cal`, bubbling — for what the person does:
  `gk:calendarnav` (`{ calendar, month, url, date }`, **cancelable**: before another month loads; call
  `preventDefault()` and load it your own way) and `gk:calendarselect` (`{ calendar, date, event, events }`
  — `event` is `{ id, title, href, start, end }` of the event chosen, or `null` for the day; cancelable:
  then Enter does not follow the day link).
- **JavaScript API** (first argument: the calendar's id, element or a selector): `GK.calendar.month(el)`
  (`'2026-11'`), `GK.calendar.focus(el, '2026-11-12')` (false when the grid does not show it),
  `GK.calendar.go(el, '2027-01')` (after `gk:calendarnav`; false when cancelled), `GK.calendar.events(el,
  date)` (the events of a day).
- **Classes** (all `gk-cal-*`, block "Calendar" in `css/blocks.json`): `gk-cal`, `-head`, `-title`,
  `-nav`, `-grid`, `-wk`, `-day`, `-outside`, `-today`, `-daynum`, `-daylink`, `-events`, `-slot`,
  `-event`, `-accent`, `-time`, `-event-title`, `-span-in`, `-span-out`, `-span-head`, `-more`,
  `-more-list`, `-more-date`, `-event-row`, `-agenda`, `-agenda-list`, `-agenda-day`, `-agenda-date`,
  `-today-mark`, `-empty`; an event's colour is its `gk-label-*`. Roles only: light, both dark spellings
  and the six themes follow (the event colours are the labels' pairs, 4.5:1).
- **Words**: `calendar.*` and `date.*` in `lang/` — month and weekday names, the full date
  (`date.full`), "+{n} more". The week numbers, the order of the weekdays and the names follow `Lang`.
- **Do not** put a form inside a day, write the grid roles or tabindex yourself, or hand the calendar a
  month's events you did not limit to `visibleRange()` — a year of events is a year of markup.

### Chart (`GridKit\Chart`, since 1.121.0)

Line, area, bar and donut charts and sparklines, drawn on the server as inline SVG — no library,
no canvas, nothing to wait for. Revenue per month, newsletter open rates, the sources of orders.
**`js/chart.js`** adds what needs a script; it is a file of its own like `js/tree.js`, loaded with
`defer` after `gridkit.js` — `skeleton.php` and the demo do it; a page of your own adds
`<script src=".../js/chart.js" defer></script>`. There is no call to make: the script finds every
`.gk-chart`, also one inserted later. `Chart::render()` prints, `Chart::html()` returns the same
string, `Chart::sparkline()` returns a string.

```php
(new Chart('revenue'))
    ->type('bar')                                    // line (default), area, bar, donut, sparkline
    ->title('Revenue per month')                     // the caption and the name a screen reader hears
    ->labels(['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'])
    ->series('2025', [8200, 7900, 9400, 10100, 9800, 11200])
    ->series('2026', [9100, 8800, 10900, 11600, null, null])   // null: a gap (no bar, no point)
    ->format('money', ['currency' => 'EUR', 'locale' => 'de'])   // 9.100,00 € — the axis: 10 Tsd. €
    ->height(280)
    ->render();
```

```php
(new Chart('open-rates'))
    ->type('line')
    ->title('Newsletter open rate')
    ->labels(['W1', 'W2', 'W3', 'W4'])
    ->series('Customers', [41.2, 43.5, 39.8, 44.1])
    ->series('Trial users', [28.4, 30.1, 33.6, 31.2], ['color' => 3])   // keep an entity on its slot
    ->format('percent', ['locale' => 'en'])          // 41.2% — values are percentages, as in StatCards
    ->zero(false)                                    // a line's axis may start near its data
    ->markers()->dashes()                            // a point shape and a dash pattern per series
    ->render();

(new Chart('sources'))
    ->type('donut')
    ->title('Where the orders came from')
    ->labels(['Online shop', 'Marketplace', 'Phone', 'Retail', 'Fairs', 'E-mail'])
    ->series('Orders', [1240, 860, 410, 380, 150, 60])    // from the sixth part on: one grey "Other"
    ->render();

echo Chart::sparkline([30, 32, 35, 33, 38, 41, 44], ['label' => 'Sign-ups', 'color' => 2]);
```

| Method | What it does |
|---|---|
| `->type($type)` | `line` (default), `area`, `bar`, `donut`, `sparkline`. Anything else warns and keeps the type. |
| `->title($text, $show = true)` | The `<figcaption>` above the chart and the `<title>` of the SVG; `$show = false` keeps it for a screen reader only (`gk-sr-only`). Default: the catalogue's "Chart". |
| `->description($text)` | Your own sentence for the SVG's `<desc>` instead of the summary the chart writes. |
| `->labels($labels)` | The categories along the axis (a donut: the names of its parts). Without labels a chart numbers its values 1 … n. A float label is written as its digits; an array is no label (warning). |
| `->series($name, $values, $opts = [])` | One series, a value per label: an int, a float or a numeric string (`'1234.5'`); `null` or `''` is a gap. Options: `color` (the slot 1 … 5 of `--gk-chart-N`, default its position) and `hidden` (`true`: drawn switched off, the legend switches it on). At most five series (the five colours); a donut and a sparkline draw the first. |
| `->format($kind, $opts = [])` | `number` (default), `money` or `percent` (`12.5` is 12.5 %). Options: `currency` (an ISO code, default EUR — EUR, USD and GBP get their sign, others are written out: `CHF 12.50`), `locale` (`de`, `en` and their regions; default the page's language — a German chart on an English page keeps its `1.234,50`), `decimals` (0 … 6; default: money 2, number up to 2, percent up to 1). |
| `->height($px)` | 120 … 1200, default 280 (a sparkline 12 … 200, default 28). A donut is as wide as it is high. |
| `->width($px)` | The width of the viewBox, 200 … 4000, default 640 (a sparkline 24 … 1000, default 96). The chart always fills its box; with the script it is drawn again at the box's width, so this matters only for the first paint without it. |
| `->stacked()` | Bars or areas on top of each other (`bar`, `area`); negative values stack below zero, a gap counts as 0, the table gets a Total column. |
| `->horizontal()` | Bars from the left, the categories down the side — for long names (`bar`). |
| `->zero(false)` | A line chart's axis starts near its data. Bars and areas always start at zero (a warning says so). |
| `->markers()` | A point shape per series — circle, square, diamond, triangle, triangle down (`line`, `area`). |
| `->dashes()` | A dash pattern per series (`line`, `area`); in print and in forced colours they are always on. |
| `->table($mode)` | The data as a table: `toggle` (default — behind "Show as table"), `open`, or `hidden` (for a screen reader only, `gk-sr-only`). |
| `->config()` | The chart as data — what `data-gk-chart` holds and `GK.chart.update()` takes: `id`, `type`, `title`, `showTitle`, `desc`, `labels`, `series` (`name`, `values` as decimal strings, `color`, `hidden`), `format` (`kind`, `currency`, `locale`, `decimals`), `width`, `height`, `stacked`, `horizontal`, `zero`, `markers`, `dashes`, `table`, `area`. A JSON endpoint can answer with it. |
| `->html()` / `->render()` | The markup, returned / printed. |
| `Chart::sparkline($values, $opts)` | A small inline line for a sentence, a StatCards card or a table cell: an `<svg role="img">` named by its numbers in words ("Sign-ups: 7 values from 30 to 44, lowest 30, highest 44."). Options: `label` (default "Trend"), `format`, `currency`, `locale`, `decimals` (as `format()`), `width`, `height`, `color` (1 … 5), `area` (`true`: a wash under the line). |

**What it draws.** The value axis steps by 1, 2 or 5 times a power of ten ("nice" ticks), about one
tick per 44px, and from 10,000 on it writes `12K` / `12 Tsd.` (`M` / `Mio.`, `B` / `Mrd.`). Bars and
areas start at zero; negative values hang below the zero line, which is drawn firmer than the grid.
Bars are at most 24px thick, 2px apart, their end rounded and their base square. Lines are 2px; a
point shows where there is room (a category 24px wide or more), where it stands alone between two
gaps, and on the active category. A long category name is cut with "…" and keeps its full text in a
`<title>`; when the names do not fit side by side, every second (third, …) is written. No values at
all: the chart says "No data" in its middle. One value: a point. Only zeros: an axis 0 … 1 and no
bar. A donut takes the first five parts in colour and folds the rest into a grey "Other"; its total
stands in the hole (compact when it would not fit); a negative part counts as 0 (warning).

**Numbers** are written by the chart, not by `number_format()`: the decimal string of the value,
rounded half away from zero (`1.005` → `1.01`), grouped by the locale — `1.234,50 €`, `€1,234.50`,
`12,5 %`, `12.5%`, a minus in front. The tooltip, the legend of a donut, the table and the summary
use the value's decimals; the axis uses the tick's.

**What a screen reader gets.** The SVG is `role="img"`, named by its `<title>` and described by a
`<desc>` the chart writes from the data: "Bar chart, 6 categories from Jan to Jun. 2025: from
8.200,00 € to 11.200,00 €, lowest 7.900,00 € (Feb), highest 11.200,00 € (Jun). …" (a donut: each
part with its value and share). The legend names every series beside a mark like its own (a short
line with its point shape, or a swatch) — never colour alone; a single series has no legend, the
title names it. **The data table** is a `<details>` "Show as table" under the chart, not a visually
hidden table: it works without a script, every reader can open it — a person who wants the exact
figures, a keyboard user, a screen reader user — and it is not read twice by a screen reader that
already heard the summary. `->table('hidden')` makes it a screen-reader-only table where a card has
no room for the link.

**With the script** (`js/chart.js`):

- **Width.** The chart is drawn again at the width of its box, and again when the box changes — one
  px of the viewBox is one px on the screen, so its text stays 12px on a phone. The script draws
  with the code of `Chart.php`, ported line by line: `GK.chart.html($chart->config())` is
  `$chart->html()` byte for byte (`ci/browser-chart.js` holds it for 400 random charts). Without the
  script the SVG scales with its box; under 560px it keeps 560px and its box scrolls sideways.
- **Pointer.** Over a category the tooltip lists the value of every series (the value first, then
  the name, a line key in the series' colour), a line or area chart shows a crosshair on the
  category, the bars of the category stay and the others step back. The way from the category to
  its tooltip belongs to that category, so the pointer can reach the tooltip and rest on it.
  A tap does the same on a touch screen. Leaving the chart closes it.
- **Keys.** The plot is one tab stop (`role="application"`, named by the title and "Arrow keys move
  between the values, Enter selects one", described by the summary). ← → (↑ ↓) move between the
  categories that have a value, Home and End to the ends, Enter or Space selects, Escape closes the
  tooltip. Each move is said through a polite live region ("Feb. 2025: 7.900,00 €. 2026: 8.800,00 €.").
- **Legend.** With two series or more each name becomes a button (`.gk-chart-toggle`,
  `aria-pressed`) that switches its series off and on; the axis follows what is shown, the series
  keeps its colour, and the change is said ("2025 hidden"). A donut's legend lists its parts with
  value and share instead — taking a part away would change every share.
- `prefers-reduced-motion` drops the short fade of the marks; print hides the tooltip and turns the
  dashes on; forced colours draw in the system's ink with dashes.

**Events**, on the `.gk-chart`, bubbling — for what the person does, not for calls of `GK.chart`:

| Event | `detail` |
|---|---|
| `gk:chartselect` | `{ chart, index, label, series, values }` — a click on a mark or Enter on the keys; `series` is the clicked mark's series (null from the keys or the band), `values` is `[{ series, name, value, text }]` for every series shown (a donut: its part, `text` with the share) |
| `gk:charttoggle` | `{ chart, series, name, visible }` — a series switched in the legend |

**JavaScript API** (the first argument is the chart's id as given to `new Chart()`, its element, or a selector):
`GK.chart.update(el, data)` draws new data — any key of `config()`: `labels`, `series` (values as
numbers or strings), `title`, `type`, `format`, `stacked` … — checked as the server checks it;
`GK.chart.data(el)` returns the config as it is now (a copy), `GK.chart.toggle(el, series, visible)`
switches a series (without `visible` the other way round; returns whether it is shown),
`GK.chart.html(config)` returns the markup `Chart::html()` writes for a config, and
`GK.chart.init(root)` sets up charts inserted without the MutationObserver seeing them.

```javascript
const rows = await (await fetch('/api/revenue?year=2026')).json();   // e.g. $chart->config()['series']
GK.chart.update('revenue', { series: rows });
document.addEventListener('gk:chartselect', e => openMonth(e.detail.label));
```

**Colours.** `--gk-chart-1` … `5` on `.gk-chart` and `.gk-chart-spark`, derived from the roles: the
five `--gk-series-N`, mixed in light mode towards the theme's ink (`--gk-on-surface`) until each
clears 3:1 on the card, the page and a hover row; in dark mode the dark steps of `--gk-series-N`,
which clear it already. Measured in all six themes and both dark spellings — series ≥ 3:1, text
≥ 4.5:1. `--gk-chart-ground` (default `--gk-surface`) is the ring round a point; set it when a chart
sits on another ground. Text never wears a series colour.

**In StatCards and a Table.** `->card('Revenue', 16900, ['format' => 'currency', 'sparkline' => [8200,
…, 16900]])` puts a sparkline under the figure, named by the card's label and written in its format;
`'sparkline' => ['values' => [...], 'color' => 3, 'area' => true]` passes options. A card without
it is drawn exactly as before. In a table, a column with `'format' => 'html'` takes
`Chart::sparkline()` as its cell value.

- **Classes** (all `gk-chart-*`, in `css/blocks.json` as "Chart"): `gk-chart`, `-type-area`,
  `-type-donut` (and `-type-line`, `-type-bar` on the figure), `-stacked`, `-dashes`, `-title`,
  `-plot`, `-svg`, `-gridline`, `-zero`, `-bar`, `-line`, `-area`, `-dot`, `-dot-on`, `-slice`,
  `-track`, `-total`, `-total-label`, `-empty`, `-c1` … `-c5`, `-other`, `-legend`, `-key`,
  `-key-off`, `-keymark`, `-swatch`, `-key-label`, `-key-value`, `-toggle`, `-data`, `-table-wrap`,
  `-table`, `-spark`, and from the script `-cross`, `-tip`, `-tip-title`, `-tip-row`, `-tip-key`,
  `-tip-value`, `-tip-name`, `-tip-total`, `-active`, `-on`.
- **Words** come from `chart.*` in `lang/`; `Lang::jsConfig()` hands them to the script as
  `chart_*`, so a chart drawn again says what the server said.
- **Warnings** (`E_USER_WARNING`, the chart still renders): an unknown type, format, option or table
  mode; a sixth series; a value that is no number (a gap), not finite (a gap), beyond ±1e15 (cut);
  more values than labels; a negative donut part; `stacked()`, `horizontal()`, `zero(false)`,
  `markers()` or `dashes()` on a type that has no use for it; a width or height out of range.
- **Do not** write the SVG yourself, colour text with a series colour, put two value scales in one
  chart (draw two charts), or pass `0.125` for 12.5 % — `percent` takes the percentage.

### CommandPalette (`GridKit\CommandPalette`, since 1.122.0)

Quick navigation and commands for an admin: Ctrl+K (Cmd+K on a Mac), a few letters, Enter — to a
page, a customer, an invoice, or a command the page carries out. The server writes a closed
`<dialog>` holding the field, an empty list and the configuration as JSON; **`js/command-palette.js`**
opens it, reads the sidebar's links out of the page, ranks what matches and fetches records as you
type. It is a file of its own like `js/tree.js` (the PawBot dashboard keeps its own `gridkit.js`),
loaded with `defer` after `gridkit.js` — `skeleton.php` and the demo do it; a page of your own adds
`<script src=".../js/command-palette.js" defer></script>`.

**`CommandPalette::render()` PRINTS the dialog** — once per page, anywhere in `<body>` (not inside a
hidden container). **`->trigger()` RETURNS** the button that opens it, for `Header::action()` or
anywhere else; `->html()` returns what `render()` prints.

```php
$palette = (new CommandPalette('main'))                    // the id: storage key and DOM ids
    ->item('New invoice', '/invoices/new', ['icon' => 'add', 'group' => 'Create', 'keywords' => ['bill', 'create'], 'shortcut' => 'N'])
    ->item('Dark mode', '', ['icon' => 'dark_mode', 'action' => 'toggle-dark', 'hint' => 'Switches the colours'])
    ->items([['label' => 'Monthly report', 'href' => '/reports/monthly', 'group' => 'Reports']])
    ->search('/admin/palette-search', ['minLength' => 2, 'debounce' => 250])   // customers, invoices
    ->recent(5)                                            // what was chosen comes first next time
    ->maxPerGroup(6)
    ->slash();                                             // "/" opens it too, outside fields

echo (new Header())->title('Invoices')->action($palette->trigger())->render();   // the trigger: RETURNED
$palette->render();                                                               // the dialog: PRINTED
```

```javascript
// A command is the page's to carry out.
document.addEventListener('gk:paletteaction', e => { if (e.detail.action === 'toggle-dark') GK.theme.toggleMode(); });
```

| Method | What it does |
|---|---|
| `new CommandPalette($id = 'main')` | The id names the recent list in `localStorage` (`gk-palette-<id>`) and the DOM ids. Empty: a warning, `main`. |
| `->item($label, $href = '', $opts = [])` | One entry: a link, or with the option `action` a command. Options below. |
| `->items(iterable $rows)` | Many at once — rows from a database: `label` (or `title`), `href` (or `url`) and any option under its own name. Rows may be objects. |
| `->search($url, $opts = [])` | Records from the server, asked as you type (see "Records"). Options: `minLength` (letters before it asks, 0 to 10, default 2), `debounce` (ms of quiet before it asks, 0 to 2000, default 250), `group` (the heading of a bare list), `param` (the query parameter, default `q`). Call it again for a second URL. |
| `->sidebar(true \| false \| '#nav')` | The links of the sidebar are entries (default `true`: `.gk-sidebar-nav`, GridKit's sidebar); a selector reads another navigation; `false` none. |
| `->recent(int $count)` | How many chosen entries come first next time (0 to 20, default 5; 0 remembers nothing). |
| `->maxPerGroup(int $count)` | At most this many entries under one heading (1 to 50, default 6); the rest is counted ("3 more — keep typing to narrow it down"). |
| `->hotkey('mod+k')` | The key that opens it: `mod` is Ctrl, Cmd on a Mac; any of `mod`, `ctrl`, `meta`, `alt`, `shift` joined with `+` to one key (`'mod+shift+p'`). At least one of mod, ctrl, meta, alt. `false` or `''`: no key — the trigger and `GK.palette.open()` still work. |
| `->slash(true)` | `/` opens it as well, unless the focus is in a field (default off). |
| `->placeholder($text)` | The words in the empty field (default: the catalogue's "Type a command or search..."). |
| `->label($text)` | The accessible name of the dialog (default "Command palette"). |
| `->fallback($url)` | Without the script the trigger is a link to this search page; with it, the link opens the palette (Ctrl+click still follows it). Without `fallback()` the trigger is a `hidden` button the script shows. |
| `->trigger($opts = [])` | RETURNS the trigger: a search symbol, its word and the key (`Ctrl K`, `⌘K` on a Mac), `aria-keyshortcuts`. Options: `label` (default "Search"), `class` (more classes), `kbd` (`false`: no key shown). On a phone it is its symbol at 44px; its name stays for a screen reader. |
| `->render()` | Prints the dialog. |
| `->html()` | Returns it. |

**Entry options** (in `item()`'s `$opts`, or as fields of a row of `items()`):

| Option | |
|---|---|
| `icon` | A Material icon name (`[a-z0-9_]`). |
| `group` | The heading it is listed under. Without: "Pages" for a link, "Commands" for a command. |
| `keywords` | Text or a list of words: found, not shown ("bill" finds "New invoice"). |
| `hint` | A second line, smaller (searched too). |
| `shortcut` | Shown at the end of the entry as a key (`N`, `G I`). Shown only: the page binds its keys itself. |
| `action` | A name (letters, digits, `- _ . :`, starting with a letter): the entry is a command, and choosing it fires `gk:paletteaction` with that name. With an `$href` too, it stays a link and the action is ignored (a warning). |

**Where the entries come from**, each time the palette opens: the sidebar (labels from
`data-label` or `.gk-sidebar-label` — no badge, no symbol word; the group heading above; the symbol;
a submenu entry names its parent as hint; the current page, `aria-current="page"`, says so), the
items of the server, what a script added with `GK.palette.register()`, and — once something is typed
— the records of the search URLs. Chosen from the sidebar, an entry is followed by a click on that
very link, so AJAX navigation (`ajaxNav`) and the page's own handlers see it.

**Records.** After `minLength` letters and `debounce` ms of quiet the script asks
`GET url?q=<what was typed>` (`Accept: application/json`, `X-Requested-With: XMLHttpRequest`,
`credentials: same-origin`) — **on the page's own origin only**: a URL on another origin is refused
with a console warning and never asked. A newer question aborts the older one, and an answer that
arrives late anyway is dropped; closing aborts too. While it asks, a line says "Searching..." and
the list is `aria-busy`; a failed answer (no 2xx, not JSON, no network) says "The records could not
be searched." with a **Try again** button; an answer with nothing in it, while the page has entries,
says "No records found.". The answer is the shape `GK.search` reads, so an endpoint written for it
works unchanged — `{"groups": [{"title", "items": [...]}]}`, or `{"items": [...]}` (under the
search's `group`, default "Records"), or a bare list. A record has a `title` and a `url`, and may
have `subtitle`, `icon` and `amount` (shown at the end). Every label is put in as text; a record
without a link, or with a `javascript:`, `data:` or `vbscript:` one, is dropped. Records come after
the page's entries, in the server's order, at most `maxPerGroup` per heading.

```php
// GET /admin/palette-search?q=eck — the query arrives as it was typed
$q = trim((string) ($_GET['q'] ?? 'eck'));
$customers = [['id' => 1042, 'name' => 'Ecklund & Partner', 'city' => 'Vienna']];   // your query, LIKE '%eck%'
header('Content-Type: application/json');
echo json_encode(['groups' => [[
    'title' => 'Customers',
    'items' => array_map(fn (array $c): array => [
        'title' => $c['name'], 'subtitle' => $c['city'] . ' · no. ' . $c['id'],
        'url' => '/customers/' . $c['id'], 'icon' => 'business',
    ], $customers),
]]]);
```

**Finding and ranking.** Case and accents do not count, and the German umlauts are found both ways:
`muller` and `mueller` find the name written with a u-umlaut, `strasse` the one with a sharp s. The
best match comes first: a name that **starts** with the query, then one where a **word starts** with
it (a capital in `invoiceList` starts a word, and every word of a query with spaces may start a word:
`ne in` finds "New invoice"), then one that holds it **inside a word**, then its **letters in order**
— the first starting a word, a letter in another word starting that word, letters skipped only inside
a word (`ninv` and `invce` find "New invoice"; `tvl` does not find "Print the invoice list").
Within each step: earlier and shorter first. Keywords and the hint are searched a step and a half
lower and mark nothing. The letters that matched are marked (`mark.gk-palette-hit`, underlined and
heavier) on the letters that were written. With a query the group of the best match comes first;
with none, the recent entries, then every group in the order of the page.

**Recent entries.** What was chosen is kept in `localStorage` under `gk-palette-<id>` — in
`GK.theme`'s scope when one is set (`GK.theme.init({ scope: 'u42' })` or `window.GK_THEME_SCOPE`:
`gk-palette-<id>:u42`), so the next person at a shared computer does not find the last one's
customers. Shown first under "Recent" when nothing is typed (and not a second time below), found by
typing (a record too, before the server answers). A command this page does not have is left out; a
stored link with a `javascript:` URL is dropped; storage that throws (a private window) just forgets.

- **Keys** (the WAI combobox with a listbox, `aria-activedescendant` — the focus stays in the field):
  Down/Up move (round at the ends), Home/End to the first/last entry, Page Down/Page Up by a page,
  Enter opens, **Ctrl+Enter** (Cmd+Enter on a Mac) opens a link in a new tab, Escape closes. Tab goes
  round inside the dialog (the field, Try again, the close button). The pointer marks what it is over;
  a click opens, Ctrl/Cmd/Shift/middle click on a link is the browser's.
- **Its key belongs to it** while it is on the page: the palette takes Ctrl+K first, so `GK.search` on
  the same key stays shut (its `data-gk-search` button still works). Inside a rich-text editor
  (`contenteditable`) Ctrl+K stays the editor's — it makes a link there. The modifiers have to be
  exact (Ctrl+Shift+K is the browser's); a keyboard that writes another alphabet presses the same key.
  On a Mac it is Cmd+K and Ctrl+K is left to the text field. Pressed again, the key closes it.
- **A modal dialog** (`showModal()`): the page behind is inert, the focus stays inside, Escape closes
  the palette and not a modal underneath, and closing gives the focus back to what had it (else to the
  trigger). The field is `role="combobox"` with `aria-expanded` and `aria-controls`, the list a
  `listbox` of `group`s named by their headings, an entry an `option` (`aria-selected` on the one the
  field points at; a link is an `<a role="option">` out of the tab order). A polite live region says
  "12 results", "No results for ..." or that the server failed, once the list has settled. The field
  is described by a sentence that says the keys; the line of keys under the list is for the eye.
- **A phone** (up to 600px): the dialog covers the screen, every entry and the close button are 44px,
  the line of keys is gone, and nothing scrolls sideways — a long name is cut with an ellipsis.
- **Without script** the trigger is hidden (or the link of `fallback()`), and the closed dialog draws
  nothing.

**Events**, on the `<dialog>`, bubbling:

| Event | `detail` |
|---|---|
| `gk:paletteopen` | `{ palette, source }` — `source`: `hotkey`, `slash`, `trigger` or `api` |
| `gk:paletteselect` | `{ palette, item, query, newTab }` — **cancelable**: `preventDefault()` and nothing happens; the palette stays open and nothing is remembered |
| `gk:paletteaction` | `{ palette, action, item, query }` — a command was chosen; the palette has closed and given the focus back |

`item` is `{ label, href, action, group, hint, icon, kind, source, element, data }` — `kind`: `link`,
`action` or `record`; `source`: `sidebar`, `page`, `script`, `search` or `recent`; `element` the
sidebar's link; `data` what a script registered, or the record as the server sent it.

**JavaScript API**: `GK.palette.open()` (or `GK.palette.open('inv')` with a query typed in, or
`{ id, query }`; returns the dialog — on a page whose server wrote no palette it builds one: the
sidebar and what a script registered, no key of its own), `GK.palette.close()`,
`GK.palette.register(source)` — more entries for every palette of the page, returning a function
that takes them away:

```javascript
GK.palette.register([{ label: 'Reload', action: 'reload', icon: 'refresh' }]);            // a list of entries
const off = GK.palette.register({ group: 'Tools', items: () => tools.map(t => ({ label: t.name, href: t.url })) });  // asked at each open
GK.palette.register({ group: 'People', url: '/api/people', minLength: 2, debounce: 250, param: 'q' });               // records, same origin
GK.palette.register({ group: 'Orders', search: (q, signal) => fetch('/api/orders?q=' + encodeURIComponent(q), { signal }).then(r => r.json()) });
off();
```

- **Classes** (all `gk-palette-*`, in `css/blocks.json` as "Command palette"): `gk-palette`, `-head`,
  `-input`, `-close`, `-list`, `-group-label`, `-option`, `-icon`, `-text`, `-label`, `-hint`, `-meta`,
  `-shortcut`, `-hit`, `-more`, `-status`, `-retry`, `-empty`, `-foot`, `-kbd`, `-trigger`,
  `-trigger-label`. Colours are roles: light, both dark spellings and the six themes follow (measured:
  text 4.5:1, the selection bar 3:1); the veil behind it is the one literal.
- **Words** come from `js.palette_*` (the dialog — the server writes them, the script reads them for a
  palette it builds) and `palette.trigger` in `lang/`; the script's reach the page through
  `Lang::jsConfig()`, and it has the English ones as a floor.
- **Warnings** (`E_USER_WARNING`, the palette still renders): an entry without a label, with a
  `javascript:`, `data:` or `vbscript:` link, with neither a link nor an action, with a link and an
  action, with an action that is not a name, an option it does not know or of the wrong type; a
  search URL that is empty or not a link; a count out of range; a hotkey that is not one; an empty id.
- **Do not** render the same palette twice or inside a hidden container, bind Ctrl+K yourself on a
  page that has one, or put markup in a label — it is shown as text.

### FilterBuilder (`GridKit\FilterBuilder`, since 1.123.0)

Conditions a person combines freely, for an invoice list, a newsletter segment, any table: rows of
**[field] [operator] [value]**, joined by AND or OR, in groups nested to `maxDepth()`; rows added,
duplicated and removed; saved views ("My open invoices"). The page queries with the checked tree:
**`FilterBuilder::toSql()`** for a database (placeholders only), **`FilterBuilder::matches()`** for rows in
memory. **`js/filter-builder.js`** makes it instant; it is a file of its own like `js/tree.js` (the PawBot
dashboard keeps its own `gridkit.js`), loaded with `defer` after `gridkit.js` — `skeleton.php` and the
demo do it; a page of your own adds `<script src=".../js/filter-builder.js" defer></script>`.

**`->render()` PRINTS the form, `->html()` RETURNS it.** Nothing else prints.

```php
$fields = (new FilterBuilder('f'))                         // the name: the query parameter (?f=…) and the ids
    ->field('status', 'Status', ['type' => 'select', 'options' => ['open' => 'Open', 'paid' => 'Paid', 'overdue' => 'Overdue']])
    ->field('amount', 'Amount', ['type' => 'money'])
    ->field('customer', 'Customer', ['placeholder' => 'Name'])            // type text
    ->field('due', 'Due', ['type' => 'date', 'operators' => ['lt', 'gt', 'between']])
    ->field('tags', 'Tags', ['type' => 'multiselect', 'options' => ['vip' => 'VIP', 'eu' => 'EU']])
    ->field('reminded', 'Reminded', ['type' => 'bool'])
    ->value(['join' => 'and', 'rules' => [['field' => 'status', 'op' => 'is', 'value' => 'open']]])   // without ?f=
    ->maxDepth(3)->maxRows(20)
    ->views([['label' => 'My open invoices', 'state' => '["and",["status","is","open"]]'],
             ['label' => 'Large ones', 'state' => ['join' => 'and', 'rules' => [['field' => 'amount', 'op' => 'gt', 'value' => 1000]]]]])
    ->saveUrl('/invoices/views')                           // "Save view" posts here (see below)
    ->preserve(['sort', 'period']);                        // other query parameters the form carries along

$tree = $fields->tree();                                   // the checked conditions — or FilterBuilder::fromRequest('f', $fields)
['sql' => $where, 'params' => $params] = FilterBuilder::toSql($tree, $fields, [
    'amount' => 'i.total',                                 // the column of a field; default: the field key
    'tags'   => fn (array $rule, array $field): array => [  // a multiselect needs a callable
        'sql' => ($rule['op'] === 'has_none' ? 'NOT ' : '') . 'EXISTS (SELECT 1 FROM invoice_tags t WHERE t.invoice_id = i.id AND t.tag IN ('
               . implode(', ', array_fill(0, count($rule['value']), '?')) . '))',
        'params' => $rule['value'],
    ],
]);
// $pdo->prepare("SELECT … FROM invoices i WHERE $where ORDER BY …")->execute($params);

$fields->render();                                         // PRINTS the form
echo '<h2>' . htmlspecialchars(FilterBuilder::summary($tree, $fields)) . '</h2>';   // "Status is Open"
```

| Method | What it does |
|---|---|
| `new FilterBuilder($name = 'f')` | The query parameter the state travels in, and the ids (`gk-fb-<name>`). Letters, digits, `_` and `-`; anything else a warning and cleaned. |
| `->field($key, $label, $opts = [])` | A field. `$key`: letters, digits, `_ . -`. Options: `type` (`text` default, `number`, `money`, `date`, `bool`, `select`, `multiselect`), `options` (select and multiselect: `key => label`, or a list of `['value' => …, 'label' => …]`), `operators` (this field's operators, a subset of its type's), `placeholder` (of a text or number value). |
| `->operators($type, $list)` | The operators a type offers, in this order (a field's own `operators` wins). |
| `->value($state)` | What the builder shows when the request carries no state of its own, and what **Reset** brings back: either shape below, or its JSON. |
| `->maxDepth(int)` | How deep groups nest, the top level counted (1 to 5, default 3; 1: no groups). |
| `->maxRows(int)` | Conditions in all (1 to 100, default 20). At the limit "Condition", "Group" and duplicate are disabled and the limit is said. |
| `->views(iterable)` | Saved views as links above the conditions: `label` (or `name`), `state` (either shape or JSON), `href` (a link of your own instead). The one applied is marked (`aria-current`, a tick). |
| `->saveUrl($url)` | Shows **Save view**: a name and a button that posts the form there. |
| `->localViews(true)` | With the script, views are kept in this browser too (`localStorage`, `gk-filterbuilder-<name>`), when there is no `saveUrl()` or the page cancels `gk:filtersave`; each with a remove button. |
| `->method('get' \| 'post')` | Default `get`: the filter is in the URL and a view is a link. |
| `->action($url)` | Where the form goes (default: the page itself). A `#section` keeps its fragment. |
| `->preserve(array)` | Other query parameters as hidden fields: names read from the URL (`['sort']`) or `name => value`. |
| `->label($text)` | The accessible name (default "Filter"). |
| `->request(array)` | Read this array instead of `$_POST` / `$_GET` (a framework's request, a test). |
| `->tree()` | The checked conditions of the current state (request, else `value()`). |
| `->render()` / `->html()` | Prints / returns the form. |

**Types and operators** (`FilterBuilder::OPERATORS`; the default set first, the rest through `operators()`):

| Type | Default | Also |
|---|---|---|
| `text` | `contains`, `not_contains`, `equals`, `starts`, `ends`, `empty`, `not_empty` | `not_equals` |
| `number`, `money` | `eq` =, `ne` ≠, `lt` <, `gt` >, `between` | `lte`, `gte`, `empty`, `not_empty` |
| `date` | `eq` (is on), `ne`, `lt` (before), `gt` (after), `between` | `lte`, `gte`, `empty`, `not_empty` |
| `select` | `is`, `is_not`, `any_of` | `none_of`, `empty`, `not_empty` |
| `multiselect` (the row holds a list) | `has_any`, `has_all`, `has_none` | `empty`, `not_empty` |
| `bool` | `true`, `false` | |

**The state** has two shapes, and every reader takes both (and their JSON):

```php
// The documented shape — what tree(), clean() and fromRequest() return:
$state = ['join' => 'and', 'rules' => [
    ['field' => 'status', 'op' => 'any_of', 'value' => ['open', 'overdue']],
    ['join' => 'or', 'rules' => [
        ['field' => 'amount', 'op' => 'between', 'value' => [100, 500]],
        ['field' => 'customer', 'op' => 'starts', 'value' => 'Eck'],
        ['field' => 'reminded', 'op' => 'true'],               // no value: empty, not_empty, true, false
    ]],
]];
// The compact one of the URL (?f=…): a group is [join, …children], a condition [field, op] or [field, op, value].
echo FilterBuilder::encode($state);   // ["and",["status","any_of",["open","overdue"]],["or",["amount","between",[100,500]],…]]
```

A value is a string (text: trimmed, at most 200 characters, control characters removed), a number
(int or float; typed as `19.99` or `19,99`), a day `Y-m-d`, an option key (string), a list of option
keys, or `[from, to]` for `between` (put in order).

**Static readers** (`$fields` is the builder, or an array `key => ['label' => …, 'type' => …, 'options' => …]`):

| | |
|---|---|
| `FilterBuilder::fromRequest($name, $fields, $opts = [])` | The checked tree a request names (POST, else GET): the JSON parameter, or the arrays of the form without script with its command applied. `$opts`: `maxDepth`, `maxRows`, `operators` (type => list), `default` (the state without a request), `request` (an array to read instead). |
| `FilterBuilder::clean($state, $fields, $opts = [])` | Any state checked — a stored view, a hand-written one. |
| `FilterBuilder::encode($tree)` | The compact JSON. A link: `'?f=' . rawurlencode(FilterBuilder::encode($tree))`. |
| `FilterBuilder::toSql($tree, $fields, $columnMap = [])` | `['sql' => '(status = ? AND i.total > ?)', 'params' => ['open', 100]]` — see below. |
| `FilterBuilder::matches($tree, $row, $fields)` | Whether a row (array or object, keyed by the field keys) passes — `array_filter($rows, fn ($r) => FilterBuilder::matches($tree, $r, $fields))`. |
| `FilterBuilder::summary($tree, $fields)` | The conditions in words, in the page's language. |
| `FilterBuilder::savedView($name, $fields, $opts = [])` | What **Save view** sent: `['label', 'state', 'json']`, or null without a name. |

**What is checked**, every time a tree is read (`clean()` underlies them all; `toSql()` and `matches()`
clean again): a field the builder does not have, an operator its field does not offer, a value of the
wrong type (`"abc"` for a number, `2026-02-30`, an option that is not one), a group deeper than
`maxDepth`, conditions past `maxRows` — all left out, silently (it is input); an empty group goes too.
The JSON is read only up to 8 KB, lists up to 200 values, at most 1000 nodes are looked at. A
condition left incomplete in the form (nothing typed yet) is kept for the form and not applied.

**`toSql()`** writes **placeholders only**. A column name comes from `$columnMap` or is the field key —
never from the request; it has to be an identifier (`total`, `i.total`, `` `order` ``, `"order"`) or a
callable `fn (array $rule, array $field): array` returning `['sql' => …, 'params' => […]]` with one `?`
per param. Text is compared with `LIKE ? ESCAPE '!'` (`%`, `_` and `!` in the value are escaped; case
follows the column's collation — MySQL's default ignores it, as `matches()` does), `equals` too.
`between` is `BETWEEN ? AND ?`, `any_of` an `IN (?, ?, …)` with one placeholder per value. A date column
may hold times: a day is `[day, next day)`, `before` is `< day`. "is not", "does not contain", `ne` and
`none_of` keep the rows where the column is NULL; `empty` is `IS NULL` (text and select: `IS NULL OR = ''`);
`bool` is `= 1`. Nothing: `1=1`. A column that is not an identifier, a multiselect without a callable,
a callable that throws or miscounts: a warning and `1=0` — a broken condition never widens the result.

**Saved views.** The page stores them; the builder shows them (`views()`) and sends a new one:

```php
// POST /invoices/views — what "Save view" sends: the state under the builder's name, the name under f_view.
$fields = (new FilterBuilder('f'))->field('status', 'Status', ['type' => 'select', 'options' => ['open' => 'Open', 'paid' => 'Paid']]);
$view = FilterBuilder::savedView('f', $fields, ['request' => ['f' => '["and",["status","is","open"]]', 'f_view' => 'My open invoices']]);
// ['label' => 'My open invoices', 'state' => [...the checked tree...], 'json' => '["and",["status","is","open"]]']
// store $view['label'] and $view['json'] for the user, then redirect back to the list.
```

**Without script** the form works as it is: nested `<fieldset>`s, the controls named
`f[rules][0][field]`, `f[rules][1][rules][0][op]` …, and submit buttons named `f[cmd]` that add a
condition or a group, duplicate or remove (`add:1`, `group:`, `dup:0`, `remove:1.0`) — the page is drawn
again with the change; Apply and Reset are buttons too. A hidden first Apply button makes Enter in a field
apply, not remove. Changing a field or operator shows its operators and value fields after the next
submit; an incomplete or wrong value is kept and said under its row.

**With `js/filter-builder.js`** the same markup is drawn at once: add, duplicate and remove without a
request (the focus goes to the new row, or to the row that took the removed one's place), the operators
follow the field's type, the value fields follow the operator (a date field, a select, a multiple
select for "any of", two fields for `between`), and the line "Shown: Status is Open AND Amount > 100.00"
follows what is typed. Apply sends the state as compact JSON in one parameter (`?f=["and",…]`), the
nested fields switched off; an incomplete condition is marked and focused instead.

- **Accessible**: every control has a name ("Field of condition 2.1", "Remove group 2"), a group is a
  `fieldset` named by its legend, the join is a labelled select, a wrong value is
  `aria-invalid` and described by a sentence with a symbol (not the colour alone), the summary is a polite
  live region. On a phone (600px) field and operator share a line, the value takes the next, the buttons
  are 44px, nothing scrolls sideways.
- **Classes** (all in `css/blocks.json` as "Filter builder"): `gk-filter-builder`, and `gk-fb-` +
  `views`, `views-label`, `view-list`, `view`, `view-remove`, `group`, `legend`, `group-head`, `join`,
  `join-label`, `rules`, `row`, `row-group`, `conj`, `cells`, `field`, `op`, `value`, `input`, `multi`,
  `between-and`, `actions`, `btn`, `dup`, `remove`, `invalid`, `error`, `add`, `add-rule`, `add-group`,
  `limit`, `empty`, `note`, `foot`, `summary`, `summary-label`, `summary-text`, `buttons`, `apply`,
  `reset`, `default`, `save`, `save-label`, `save-name`, `save-btn`. Colours are roles: light, both dark
  spellings and the six themes follow.
- **Words** come from `filterbuilder.*` in `lang/`; the server hands the script the ones it needs in the
  builder's configuration.
- **Warnings** (`E_USER_WARNING`, the builder still renders): a field key that is not one, a type or
  operator that does not exist, a select without options, an option it does not know, a limit out of
  range, a method other than get/post, a `javascript:` link, a view without a label, a `value()` with
  conditions that are not complete, a builder without fields.

**Events**, on the form, bubbling:

| Event | `detail` |
|---|---|
| `gk:filterchange` | `{ name, tree, json, summary }` — **cancelable**: Apply, before the form is sent. `preventDefault()` and filter yourself (a fetch, a live table). |
| `gk:filtersave` | `{ name, label, tree, json, summary }` — **cancelable**: Save view. Cancelled, the page stores it; else it posts to `saveUrl()`, or stays in this browser with `localViews()`. |

**JavaScript API** (first argument: the form, its id, or the builder's name): `GK.filterBuilder.get('f')`
(the checked tree), `GK.filterBuilder.set('f', state)` (either shape or JSON, drawn at once, nothing
sent), `GK.filterBuilder.reset('f')` (back to `value()`, nothing sent), `GK.filterBuilder.apply('f')`
(as the button), `GK.filterBuilder.json('f')`, `GK.filterBuilder.summary('f')`.

### Kanban (`GridKit\Kanban`, since 1.124.0)

Columns with cards for tasks, orders, campaigns and support tickets. The server writes a labelled
region (`<section aria-label>`) of columns — each a heading, a count and a list of cards — that works
without any script: every card is in view, a card with `href` is a link, and a card that may move has a
**"Move to" form** (a `<details>` with a select of the columns and a button) that posts to `moveUrl()`
like any form. **`js/kanban.js`** adds a handle to every movable card and with it dragging (mouse, pen,
finger), moving with the keyboard, counts and limits that follow every move and the save as JSON; it is
a file of its own like `js/tree.js` (the PawBot dashboard keeps its own `gridkit.js`), loaded with
`defer` after `gridkit.js` — `skeleton.php` and the demo do it; a page of your own adds
`<script src=".../js/kanban.js" defer></script>`. There is no call to make: the script finds every
`.gk-kanban`, also one inserted later. `Kanban::render()` prints, `->html()` returns the same string.

```php
$tickets = [
    ['id' => 'T-101', 'subject' => 'Printer offline', 'status' => 'new', 'priority' => ['High', 'red'],
     'owner' => 'Anna Huber', 'due' => '2026-11-02', 'tags' => ['Hardware', ['Office', 'blue']], 'href' => '/tickets/101'],
    ['id' => 'T-102', 'subject' => 'New laptop', 'status' => 'doing', 'comments' => 3],
    ['id' => 'T-103', 'subject' => 'Password policy', 'status' => 'review', 'draggable' => false],   // stays where it is
];
(new Kanban('tickets'))
    ->label('Support tickets')                                    // the accessible name of the board
    ->column('new', 'New', ['color' => 'blue', 'description' => 'Not looked at yet'])
    ->column('doing', 'In progress', ['color' => 'orange', 'limit' => 3])
    ->column('review', 'Review')
    ->column('done', 'Done', ['color' => 'green'])
    ->cards($tickets, ['title' => 'subject', 'column' => 'status', 'badge' => 'priority', 'assignee' => 'owner',
                       'labels' => 'tags', 'count' => 'comments'])
    ->moveUrl('/tickets/move', ['headers' => ['X-CSRF-Token' => 'token'], 'fields' => ['_token' => 'token']])
    ->wipLimits('block')                                          // a full column takes no card
    ->addUrl('/tickets/new?status={column}')
    ->render();
```

| Method | What it does |
|---|---|
| `->column($key, $label, $opts = [])` | A column, in the order added. Options: `limit` (a work-in-progress limit, a whole number from 1: the count shows "2 / 3", a column over it is marked), `color` (`gray`, `green`, `orange`, `red`, `blue` — a stripe along its top), `description` (a line under the heading, also the list's description). |
| `->card($id, $title, $column, $opts = [])` | One card (options below). A column the board does not have puts it in the first column, with a warning. |
| `->cards($rows, $map = [])` | Rows as a database hands them over (arrays or objects). `$map` names the fields: `'id'`, `'title'` (default `title`), `'column'` (default `column`) and any card option (`'assignee' => 'owner'`); every option is read from the row under its own name unless the map says otherwise. |
| `->moveUrl($url, ['headers' => [...], 'fields' => [...]])` | Cards can be moved, and every move is saved here (see "Moving"). `headers` go with every JSON move; `fields` are hidden fields of the "Move to" form (a form sends no header — put the CSRF token here too). |
| `->movable()` | Cards can be moved without a URL: the page hears `gk:kanbanmove` / `gk:kanbanmoved` and saves itself. No "Move to" form (it would post nowhere). |
| `->wipLimits('block' \| 'warn')` | What a move into a column at its `limit` does. `'warn'` (the default, also without a call): it is made, and the column is marked over its limit — the count in the error role, a frame and the words "Over limit". `'block'` (the argument's default): it is refused — no drop, no keyboard step, the column disabled and marked "(full)" in every "Move to" list — and the person is told why. Check the limit on the server too. |
| `->empty('No tickets')` | The words in a column without cards; default "No cards". |
| `->addUrl($url)` | An "Add card" link at the foot of every column. `{column}` in the URL is replaced by the column's key (URL-encoded); without it `?column=<key>` (or `&column=`) is added before any `#fragment`. |
| `->compact()` | Smaller cards for a board with many: a card's `text` is left out, the gaps are tighter, columns narrower (200px instead of 232px), "Move" shows its symbol only. |
| `->label('Support tickets')` | The accessible name of the board; default the catalogue's "Board". |
| `->today('2026-11-04')` | The day a due date is measured against (`Y-m-d` or a `DateTimeInterface`); default today. For a fixed page and for tests. |
| `->render()` / `->html()` | Prints the board / returns it. |
| `Kanban::readMove($body = null, $post = null)` | Static. The move a request carries — the script's JSON or the form's fields — as `['id' => 'T-1', 'column' => 'done', 'index' => 1, 'form' => false]` (`index` null from the form: the end of the column), or null. `$body`/`$post` default to `php://input` and `$_POST`; JSON is read when the request's `Content-Type` says so (or `$body` is given). |

**Card options** (in `card()`'s `$opts`, or as fields of a row):

| Option | |
|---|---|
| `text` | A few lines under the title (cut after three; left out by `compact()`). |
| `badge` | Text, or `['High', 'red']` / `['text' => 'High', 'color' => 'red']` — a `gk-label` beside the title (gray, green, orange, red, blue). |
| `labels` | A list of the same: small labels under the text. |
| `assignee` | A name: the initials in a circle, "Assigned to Anna Huber" for a screen reader, the name as its tooltip. |
| `avatar` | A picture URL for that circle (with or without `assignee`). A `javascript:`/`vbscript:`/`data:` URL is refused (a `data:image/…` picture is taken). |
| `due` | A day (`Y-m-d`) or a `DateTimeInterface`. Shown as "Due Nov 20" (the year when it is not this year); a day before `today()` is **"Overdue: Nov 2"** with a warning symbol and the error role, today is **"Due today"** — written out, never the colour alone. |
| `href` | The title is a link. A `javascript:`/`vbscript:`/`data:` URL is refused with a warning. |
| `count` | A number in a small pill (comments, attachments). |
| `draggable` | `false`: the card keeps its place — no handle, no "Move to"; others can still be put beside it. |

Yes/no options take `true`/`false`, `1`/`0` or `'1'`/`'0'`/`'yes'`/`'no'`; anything else is dropped with a
warning.

- **What a screen reader gets**: a region named by `label()`; per column a heading (`h3`) and a list
  labelled by the heading and the count in words ("In progress 2 cards, limit 3"), described by the
  column's `description`; every card a list item. With the script: a handle per movable card, a button
  named "Move: <title>", described by the keys, `aria-pressed` while lifted; a polite live region speaks
  every step, the save and a failure.
- **Keys** (with the script): Tab reaches a card's links, its handle and its "Move" toggle. On the handle
  Space (or Enter) lifts the card, Up/Down move it within its column, Left/Right to the column beside it
  (at the same height, or its end), Home/End to the ends of the column; each step is spoken ("Column
  Review, position 2 of 3."). Space puts it down ("Card Wi-Fi moved to column Review, position 2 of 3."),
  Escape — or leaving the handle — gives it back. In `rtl` Left and Right swap. A full column in
  `'block'` is refused with a word, the board's edge as well.
- **The pointer**: drag the handle (mouse, pen or finger); a mouse may also take a card by its body (not
  by a link, a button or the form in it) — a finger on the body scrolls the board. A press that travels
  less than 4px is a click. While dragging a copy of the card follows the pointer, the card stays faded
  in its place, a line with two dots stands where it would go (before the first card whose middle is
  below the pointer, or at the end), the column under the pointer is framed (in the error role when it
  is full in `'block'`); held near the side of the board it scrolls sideways, near the top or the bottom
  of the window the page scrolls. Escape cancels.
- **Phone**: the board is the box that scrolls sideways, never the page. Below 600px the columns are
  lanes of 85% of the board that snap into place, the next one showing at the edge — every column stays
  in the page, so Tab, a screen reader and a drag reach each one (a column switcher would hide all but
  one and need a script). On a touch screen the handle, the title link, "Move", its form and "Add card"
  are 44px.
- **Counts and limits** follow every move, the way back too: the count ("4" or "2 / 3", in words for a
  screen reader), the over-limit mark, the empty words, what each "Move to" list offers.

**Moving.** Every move a person makes fires `gk:kanbanmove` first — **cancelable**: `preventDefault()`
and the card stays (a keyboard move goes back). With `moveUrl()` it is then posted, one at a time in the
order made: `POST` with `Content-Type: application/json`, `credentials: same-origin`,
`X-Requested-With: XMLHttpRequest`, the `headers` of `moveUrl()` and the body
`{"id": "T-101", "column": "done", "index": 1}` — `index` counts from 0 among the cards of the new
column (the place the card now has). A 2xx is saved unless its JSON says `{"ok": false}`. A refusal,
another status or no network puts the card back where the server last agreed — that move and every later
one still waiting (they are never sent) — shows the answer's `"message"` (or "The move could not be
saved. The card is back in its place.") in a toast (the line `.gk-kanban-error` above the board where
`gridkit.js` is not loaded), and fires `gk:kanbanmoved` with `ok: false, reverted: true` for each.

**Without the script** (or with an old `gridkit.js` that has no `kanban.js`) the card's "Move to" form
posts `id`, the `fields` of `moveUrl()` and `column` as `application/x-www-form-urlencoded` — no
`index`: the card goes to the end. The script sends the same form as JSON instead (no page load). Answer
a form with a redirect back to the board (`303`), JSON with JSON — `Kanban::readMove()` says which:

```php
// POST /tickets/move — {"id": "T-101", "column": "done", "index": 1}, or the form: id=T-101&_token=…&column=done
$move = Kanban::readMove('{"id": "T-101", "column": "done", "index": 1}');   // without an argument: the request
// … check the token (header or field), the column, the limit and the person's right, store column and order …
if ($move !== null && $move['form']) {
    // header('Location: /tickets', true, 303);          // back to the board
} else {
    // header('Content-Type: application/json');
    echo json_encode(['ok' => true]);   // or http_response_code(409) and ['ok' => false, 'message' => 'Shown to the person.']
}
```

**Events**, on the `.gk-kanban`, bubbling — for every move a person makes (and for `GK.kanban.move()`
without `save: false`):

| Event | `detail` |
|---|---|
| `gk:kanbanmove` | `{ board, id, from, to, index, fromIndex }` — cancelable, before the move; `from`/`to` are column keys |
| `gk:kanbanmoved` | the same plus `ok` and `reverted` — after the save (at once without a URL), or after the way back |

**JavaScript API** (the first argument is the board's id as given to `new Kanban()`, its element, any
element inside it, or a selector): `GK.kanban.move(el, id, column, index, opts)` moves a card the way a
person would — the veto, the save, the way back; a Promise of `{ ok, reverted }`, or `false` when
nothing moved (a full column in `'block'`, a card that may not move, an unknown id); `index` null or
left out: the end; with `{ save: false }` it only puts the card there (a change the server already
knows — another person's): no event, no request. `GK.kanban.cards(el, column)` — the ids of a column in
order, or of every column as `{ key: [ids] }`. `GK.kanban.columns(el)` — `[{ key, label, count, limit,
over }]`. `GK.kanban.refresh(el)` — after the page added or took out a card itself: handles, counts,
limits.

- **Classes** (all `gk-kanban-*`, in `css/blocks.json` as "Kanban"): `gk-kanban`, `-board`, `-column`,
  `-column-gray`, `-column-green`, `-column-orange`, `-column-red`, `-column-blue`, `-head`, `-title`,
  `-count`, `-over`, `-over-note`, `-desc`, `-list`, `-card`, `-card-head`, `-card-title`, `-badge`,
  `-text`, `-labels`, `-meta`, `-due`, `-overdue`, `-due-today`, `-cardcount`, `-avatar`, `-move`,
  `-move-toggle`, `-move-word`, `-move-form`, `-move-label`, `-move-select`, `-move-button`, `-empty`,
  `-add`, `-compact`, `-error`, and from the script `-handle`, `-lifted`, `-dragging`, `-ghost`,
  `-ghost-no`, `-target`, `-target-no`, `-drop`, `-busy`. Colours are roles: light, both dark spellings
  and the six themes follow (measured: text 4.5:1, handle and focus ring 3:1).
- **Words** come from `kanban.*` (server) and `js.kanban_*` (script) in `lang/`; the script's reach the
  page through `Lang::jsConfig()`.
- **Warnings** (`E_USER_WARNING`, the board still renders): an empty or duplicate column key (left out),
  an empty or duplicate card id (left out), a card without a title (its id is shown), a card in a column
  the board does not have (the first column), no `column()` at all (the columns are made from the
  cards), an option it does not know or of the wrong type (a `limit` that is no whole number from 1, an
  unknown colour — gray, a `due` that is no day, a script URL), an unknown `wipLimits()` mode (`'warn'`),
  a `today()` that is no day, a `moveUrl()` option it does not know or a header or field that is not
  name => text.
- **Do not** put the board inside a `<form>` (every card that may move has a form of its own), write the
  handle or the roles yourself, or trust the page's limits on the server — the page can be old, another
  person faster.

### FileManager (`GridKit\FileManager`, since 1.125.0)

A media library for a CMS and a drawer for invoice receipts: folders with a breadcrumb, thumbnails or a
list, search, a filter by type, the order, a selection with actions, a details panel — and an upload of
many files at once. **The page supplies the listing** (`files()`, `folders()`) and the URLs the actions
post to; the component never reads a directory and never deletes, renames or moves a file — it renders,
and posts. The one thing it does with files is **check an upload on the server**: `FileManager::receive()`
(below). `render()` prints; `html()` returns the same string.

Everything works without script: the folders and the names are links (`?folder=`, `?file=`), the toolbar
is a GET form, the upload a `multipart/form-data` form posting `files[]` to `uploadUrl()`, the actions a
POST form with a checkbox per file. **`js/file-manager.js`** — a file of its own like `js/tree.js`, loaded
with `defer` after `gridkit.js` (`skeleton.php` and the demo do it; a page of your own adds
`<script src=".../js/file-manager.js" defer></script>`) — filters and sorts in place, uploads each file with
a progress bar, opens the details beside the list and posts the actions with `fetch`. Nothing to call.

```php
$folderRows = [
    ['id' => 1, 'name' => 'Receipts', 'parent' => null],
    ['id' => 2, 'name' => '2026', 'parent' => 1, 'count' => 14],
];
$fileRows = [
    ['id' => 31, 'filename' => 'Beach.jpg', 'size' => 482113, 'mime' => 'image/jpeg', 'created_at' => '2026-10-02 09:12',
     'url' => '/media/31/Beach.jpg', 'thumb' => '/media/31/thumb.jpg', 'folder' => null, 'width' => 2400, 'height' => 1600],
    ['id' => 32, 'filename' => 'Invoice 2026-118.pdf', 'size' => 88210, 'mime' => 'application/pdf', 'created_at' => 1790000000,
     'url' => '/media/32/Invoice.pdf', 'folder' => 2],
];
$fm = (new FileManager('media'))
    ->label('Media')                                    // the accessible name; the top of the breadcrumb
    ->folders($folderRows)                              // id, name, parent, url, count
    ->files($fileRows, ['name' => 'filename', 'modified' => 'created_at', 'thumbUrl' => 'thumb'])
    ->path($_GET['folder'] ?? null)                     // the folder on screen; the breadcrumb follows
    ->fromQuery()                                       // view, sort, type, q, folder and file of the request
    ->uploadUrl('/admin/media/upload')
    ->accept(['image/*', 'application/pdf'])->maxSize('10M')->maxFiles(20)
    ->actions(['delete' => '/admin/media/delete', 'move' => '/admin/media/move',
               'rename' => '/admin/media/rename', 'folder' => '/admin/media/folder', 'download' => '/admin/media/zip'])
    ->csrf('the-csrf-token-of-the-page');
$fm->render();
```

**Rows.** `->files($rows, $map)` takes arrays or objects; `$map` renames the page's fields to the
manager's, every field is read under its own name otherwise:

| Field | |
|---|---|
| `id` | required; text or a number, unique (a second row with the same id is left out) |
| `name` | required; what is shown — the name the person gave the file, not the stored one |
| `size` | bytes; shown as "482 KB", "1.4 MB" |
| `mime` | the type (`image/png`); without it the extension of `name` decides. It chooses the kind (image, document, other), the symbol and the words ("PDF document", "PNG image") |
| `modified` | a Unix timestamp, a date string or a `DateTime`; shown in `files.date_format` |
| `url` | where the file is opened: the preview in the details, "Open", "Copy URL"; an image without `thumbUrl` uses it as its thumbnail |
| `thumbUrl` | a small picture for the tile; without one a symbol and the extension are shown |
| `folder` | the id of the folder it lies in (`null` or `''`: the top) |
| `width`, `height` | of an image, in pixels; shown in the details (the script measures an image without them) |

A `javascript:`, `vbscript:` or `data:` URL is left out with a warning — except a picture: a `data:` URL
of a PNG, JPEG, GIF or WebP is taken for `url` and `thumbUrl`, and an SVG one for `thumbUrl` (only ever
an `<img>`, where no script runs).

`->folders($rows, $map)` — `id`, `name`, `parent` (the id of its folder; `null` or `''` the top), `url`
(where it leads; default `?folder=<id>` on this page), `count` (shown as "14 items"). The folders of the
current folder are listed first, by name. Without `folders()` and `path()` every file is listed.

**Methods**

| Method | |
|---|---|
| `->files($rows, $map)` / `->folders($rows, $map)` | the listing (above); call again to add more |
| `->path($folderId)` | the folder on screen (`null`: the top); its path through `folders()` is the breadcrumb. An id `folders()` does not have shows the top, with a warning |
| `->breadcrumb([['name' => 'Media', 'url' => '/media'], ['name' => 'Receipts', 'url' => '']])` | the breadcrumb given as it is (each crumb `name` or `label`, `url` or `href`), for folders not all in `folders()` |
| `->view('grid' \| 'list')` | thumbnails (default) or a row per file with type, size and date |
| `->sort('name' \| 'date' \| 'size' \| 'type', 'asc' \| 'desc')` | the order of the files; default name, ascending |
| `->filter('all' \| 'image' \| 'document' \| 'other')` | only one kind |
| `->search('invoice')` | only names that hold this (case, accents and umlauts ignored) |
| `->fromQuery($get = null)` | view, sort (`name-asc` … `type-desc`), type, q, folder and file from the request (default `$_GET`). A value it does not know is ignored without a warning — the request is the visitor's |
| `->param('media')` | the parameters under one name: `?media[folder]=2&media[view]=list` — for two managers on a page, or a page whose own parameters are called `sort` or `q` |
| `->anchor('files')` | every link and the toolbar end with `#files` (a section or a tab of the page) |
| `->keep(['page' => 'media'])` | parameters of the page every link and the toolbar carry along |
| `->label('Media')` | the accessible name; default "Files" |
| `->select('none' \| 'single' \| 'multi')` | checkboxes (default), a radio button per file, or none |
| `->selected([31, 32])` | ticked when the page opens |
| `->open(31)` | the details of a file are open when the page opens (`fromQuery()` reads `?file=31`) |
| `->actions([...])` | what the person can do (below) |
| `->uploadUrl('/admin/media/upload')` | where uploads are posted; without it there is no upload |
| `->accept(['image/*', 'application/pdf', '.csv'])` | MIME types, families or extensions; default JPEG, PNG, GIF, WebP and PDF. **`image/*` does not take SVG** — name `image/svg+xml` for that. A type a browser or a server runs (`text/html`, `application/x-php`, `.php`) is never taken |
| `->maxSize('10M')` | bytes, or `'512K'`, `'10M'`, `'2 MB'`; 1 KB to 2 GB, default 10 MB |
| `->maxFiles(20)` | files per upload, 1 to 1000 |
| `->parallel(3)` | files the script sends at the same time, 1 to 6 |
| `->csrf($token, '_token')` | a hidden field in every form and the `X-CSRF-Token` header of every request of the script |
| `->hidden(['tenant' => '7'])` | hidden fields posted with every upload and action |
| `->rules()` | `['accept' => …, 'maxSize' => …, 'maxFiles' => …]` — hand it to `receive()` so the server checks what the browser was told |
| `->render()` / `->html()` | prints / returns |
| `FileManager::receive($_FILES['files'], $opts)` | checks an upload (below) |
| `FileManager::check($path, $name, $opts)` | the same checks for a file already on the server |

**Actions.** `->actions()` takes `delete`, `move`, `download` (for the ticked files), `rename` (in the
details) and `folder` (a new folder in the current one). Each is a URL the manager POSTs to, or `true`:
then only the event `gk:filesaction` fires and the page does the rest (`false` removes one). Every action
posts the same fields, with and without script:

| Field | |
|---|---|
| `action` | `delete`, `move`, `rename`, `folder` or `download` |
| `ids[]` | the ticked files (rename: the one in the details) |
| `target` | move: the id of the folder chosen in "Move to" (`''` the top) |
| `name` | rename: the new name; folder: the name of the new folder |
| `parent` | folder: the folder it is created in |
| `folder` | the folder on screen |
| the `csrf()` field and `hidden()` | |

With the script the request carries `X-Requested-With: XMLHttpRequest`, `Accept: application/json` and
`X-CSRF-Token`, and expects JSON: `{"ok": true}` — and for rename optionally `"file": {…}` (the row as
it is now), for folder `"folder": {"id": …, "name": …, "url": …}` so it appears at once — or
`{"ok": false, "message": "Shown to the person."}`. Deleted and moved files leave the listing, a renamed
one is drawn again, the answer's `message` (or the manager's own sentence) is announced and shown in a
toast. A download is not fetched: the form posts to its URL and the browser receives what the page sends
(a ZIP). Without the script every action is a plain POST — answer with a redirect back (PRG).

```php
// POST /admin/media/delete — with or without script
$ids = array_map('intval', (array) ($_POST['ids'] ?? []));
// … check the token and that these files belong to this person, delete them …
$wantsJson = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
if ($wantsJson) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);                 // or ['ok' => false, 'message' => 'The contract is kept for ten years.']
}
// else: header('Location: /admin/media?folder=' . urlencode((string) ($_POST['folder'] ?? '')), true, 303);
```

**Upload, in the browser.** Files chosen with "Choose files", **dropped anywhere on the manager**, or
**pasted** (a picture from the clipboard, while the focus is in the manager — or on the page, when it has
only one manager; named "Pasted picture 20261104-101500.png") are checked first: the type against
`accept()` (by the browser's type, or the extension when it has none), the size against `maxSize()`, an
empty file, and the count against `maxFiles()` (counting what is still waiting) — a refused file says why
in its row ("Big.pdf is too large (12 MB); at most 10 MB per file."). Each file is then sent on its own,
`parallel()` at a time: a `POST` to `uploadUrl()` with `files[]` (one file), `folder`, the `csrf()` field
and `hidden()`, the headers above, through `XMLHttpRequest` for its progress. Each row has a picture
(an object URL of the file itself, revoked once drawn), the name, a `<progress>` bar, a symbol **and** a
word for its state — waiting, "Uploading, 45 %", uploaded, failed with the reason, cancelled — and a
button: cancel while it waits or is sent, try again after a failure of the line or a 5xx, remove from the
list. **The server answers** `{"ok": true, "file": {…}}` — the row as `files()` takes it (`id` and `name`
required; `folder` defaults to the folder on screen) — and the file appears in the listing, in its
place in the order, without a reload; or `{"ok": false, "message": "…"}`, shown in the row. Anything
else is "The upload failed (HTTP 500)." or "The server sent an answer that is not one an upload expects."
A live region says each file taken or refused, and at the end "3 of 4 files uploaded."

**Upload, on the server — `FileManager::receive()`.** Nothing an upload says is believed: not the name,
not the type, not the size.

```php
// POST /admin/media/upload — check the CSRF token first. $fm: the manager the page drew, built the same way.
$fm = (new FileManager('media'))->accept(['image/*', 'application/pdf'])->maxSize('10M')->maxFiles(20);
$results = FileManager::receive($_FILES['files'] ?? [], $fm->rules() + [
    'target' => '/srv/app/uploads',                  // outside the web root; without it nothing is moved
]);
foreach ($results as $r) {
    if ($r['ok']) {
        // $r['path']: /srv/app/uploads/9f86d081884c7d659a2feaa0c55ad015.jpg — the stored name is random
        // $r['name']: 'Beach.jpg' (the client's name, cleaned — store it in your table, never on the disk)
        // $r['mime'], $r['size'], $r['width'], $r['height'], $r['sha256'], $r['kind']
    } else {
        // $r['error']: 'type', 'mismatch', 'size', 'script', 'dimensions', …; $r['message']: the words
    }
}
$r = $results[0] ?? null;
header('Content-Type: application/json');
echo json_encode($r === null ? ['ok' => false, 'message' => 'No file was chosen.']
    : ($r['ok'] ? ['ok' => true, 'file' => ['id' => 7, 'name' => $r['name'], 'size' => $r['size'], 'mime' => $r['mime'],
                                            'url' => '/media/7', 'modified' => time()]]
               : ['ok' => false, 'message' => $r['message']]));
```

What it checks, in this order — a file that fails one is refused with a sentence that says which:

1. **One per entry, any shape** of `$_FILES` (one file, `files[]`, nested — each with `name`,
   `tmp_name`, `error`, `size`); an empty slot is skipped.
   More than `maxFiles` — the rest is refused (`count`).
2. **The upload's error code**, in words: too large for `upload_max_filesize` (`ini_size`) or the form
   (`size`), arrived in part (`partial`), the server's own trouble (`server`).
3. **Only a file PHP received in this request** (`is_uploaded_file`) — a path smuggled into the array
   is refused (`not_uploaded`).
4. **The name**: a NUL byte, a leading dot (`.htaccess`, `.user.ini`) or an empty name — refused
   (`name`); a part that a server or Windows runs **anywhere** in the name — `invoice.php.jpg`,
   `x.phtml.png`, `.phar`, `.cgi`, `.jsp`, `.exe`, `.html`, `.htaccess` … — refused (`name`). Trailing dots
   and spaces are dropped first (Windows does: `x.php.` is `x.php`); the path a client sends
   (`C:\fakepath\…`, `../../`) is dropped.
5. **The size on disk**, not the one claimed: 0 bytes (`empty`), more than `maxSize` (`size`).
6. **The type by content** (`finfo`): a type a browser or a server runs (HTML, XML, PHP, JavaScript,
   shell, executables, Java archives) is never taken; it must be one `accept` names (`type`), and the
   name's extension must be one of that type's — a PNG named `.jpg`, a text named `.png` (`mismatch`).
   Without the `fileinfo` extension nothing is taken (a warning says why).
7. **Code where none belongs**: `<?php` in any file; `<?=`, `<script`, `<html`, `<iframe`, `<svg` … in a
   picture or a text file — a polyglot (`script`).
8. **A picture must be one**: `getimagesize()` reads its size (`unreadable`), and
   it is at most `maxWidth` × `maxHeight` (default 20,000 each) and `maxPixels` (default 50 million) — a
   100-byte PNG that claims 50,000 × 50,000 pixels would take 10 GB to open (`dimensions`).
9. **An SVG** is taken only when `accept` names `image/svg+xml`, and only without a DOCTYPE or entity,
   script, `foreignObject`, `iframe`, `embed`, `object`, animation elements, an `on…` attribute, a link
   to `javascript:`, `vbscript:` or `data:` (other than a raster picture), or a style that imports or
   runs (`script`) — refused, not cleaned: ask for a PNG instead.
10. **The stored name** is 32 random hex digits and the extension the name has, checked against the type (a JPEG always `.jpg`) —
    the client's name never reaches the disk.
11. **Storing**: only with `target` — an existing, writable directory, and **not inside the web root**
    (`$_SERVER['DOCUMENT_ROOT']`, or `webRoot`) unless `'public' => true` says so; otherwise a warning and
    nothing is stored (`store`). The file is moved with `move_uploaded_file()` and made `0640` (`0644`
    when public). Without `target` nothing is moved: `tmp` is the temporary file, which PHP deletes when
    the request ends.

| Option | |
|---|---|
| `accept`, `maxSize`, `maxFiles` | as the manager's — pass `$fm->rules()` |
| `maxWidth`, `maxHeight`, `maxPixels` | the size of a picture in pixels (above) |
| `target` | the directory to move accepted files into |
| `public` | `true`: `target` may lie inside the web root (serve it with `X-Content-Type-Options: nosniff`) |
| `webRoot` | the web root to compare with; default `$_SERVER['DOCUMENT_ROOT']` |

A result: `ok`, `index` (the entry in `$_FILES`), `name` (the client's, cleaned, for display), `size`,
`mime`, `ext`, `kind`, `width`, `height`, `stored`, `path` (when moved), `tmp` (when not), `sha256`, `error`
(`null`, or `count`, `ini_size`, `size`, `partial`, `server`, `not_uploaded`, `name`, `empty`,
`unreadable`, `type`, `mismatch`, `script`, `dimensions`, `store`), `message`. An empty list: nothing was
chosen. When a request is larger than `post_max_size`, PHP drops it whole — `$_POST` and `$_FILES` are
empty while `CONTENT_LENGTH` is not; say so yourself.

`FileManager::check($path, $name, $opts)` runs the same checks on a file that was **not** uploaded in this
request (an import directory, a mail attachment) and returns one result; with `target` it **copies** the
file there. Only `receive()` trusts a file as an upload.

**Serving.** Store outside the web root and send files through a script with the stored `mime` as
`Content-Type`, `X-Content-Type-Options: nosniff`, and `Content-Disposition: attachment` for anything that
is not a picture or a PDF. Check the CSRF token and the person's right to the folder before
`receive()` — the component does not know your users.

**Keys and the screen reader.** In the listing: arrow keys move between the files (up and down by a row
of tiles), Home/End, Space ticks, Shift+arrow and Shift+click tick a range, Ctrl+A (Cmd+A) ticks all,
Enter on a box opens the details, Delete deletes the ticked files (after a confirmation), Escape lets go
of the selection or closes the details and gives the focus back. Every box is named "Select Beach.jpg";
a folder link says "Folder Receipts"; the count of ticked files is in the bar; filtering says "3 of 12
files shown"; uploads, refusals and actions are said in a polite live region. A ticked file has its tick,
an upload a symbol and a word — **never the colour alone**.

- **Phone** (600px and narrower): two columns of tiles; the details are a sheet over the whole screen
  (`role="dialog"`, `aria-modal`, Tab kept inside, Escape and the close button give the focus back); the
  toolbar and the bulk bar one field per line, 16px; every target 44px (with a finger at any width too);
  nothing wider than the screen.
- **Classes** (all `gk-files-*`, in `css/blocks.json` as "File manager"): `gk-files`, `-js` (the script
  runs), `-view-grid`, `-view-list`, `-top`, `-crumbs`, `-newfolder`, `-field`, `-toolbar`, `-search`,
  `-views`, `-viewbtn`, `-apply`, `-upload`, `-drop`, `-drop-icon`, `-drop-text`, `-pick`, `-hint`,
  `-send`, `-dragover`, `-queue`, `-job`, `-job-thumb`, `-job-main`, `-job-name`, `-progress`,
  `-job-state`, `-job-actions`, `-job-btn`, `-main`, `-body`, `-bulk`, `-all`, `-count`, `-move`, `-act`,
  `-summary`, `-head`, `-items`, `-item`, `-folder`, `-check`, `-thumb`, `-ext`, `-name`, `-type`,
  `-size`, `-date`, `-meta`, `-selected`, `-current`, `-empty`, `-details`, `-details-head`,
  `-details-title`, `-details-close`, `-details-body`, `-preview`, `-props`, `-url`, `-details-actions`,
  `-rename`, `-sheet-open` (on `<html>` while the sheet is open). Colours are roles: light, both dark
  spellings and the six themes follow.
- **Words** come from `files.*` in `lang/` and reach the script inside the manager's own config — no
  `Lang::jsConfig()` needed. `files.date_format` is the date's shape (`Y-m-d H:i`, `d.m.Y H:i`).
- **Warnings** (`E_USER_WARNING`, the manager still renders): a row without an id or a name, an id twice,
  a map naming a field it does not know, a `javascript:` URL, an unknown view, sort, type, selection mode
  or action, an action that is neither a URL nor `true`, an `accept` entry that is not a type or names one
  that runs, a size or count out of range, a field name of `csrf()` or `hidden()` that clashes, a
  `path()` folder it does not have. `receive()` warns about an unknown option, a `target` that is not a
  writable directory or lies in the web root, and a missing `fileinfo`.
- **Do not** put the manager inside another `<form>`, trust `$_FILES[...]['type']` or the name, write the
  client's name to the disk, store uploads in the web root, or answer an upload with HTML when the script
  asked for JSON.

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

**The six themes (since 1.114.0).** The keys are what they always were; the colours were
refined. A theme is an accent hue and the neutral it is paired with — every other value is
derived in OKLCH at the same lightness steps, so all six have the same contrast:

| Key | Accent (light / dark) | Neutrals | Character |
|---|---|---|---|
| `indigo` (default) | `#4e5ad7` / `#a1b2ff` | cool blue-grey | deep ink indigo |
| `ocean` | `#00769f` / `#7fc1e3` | blue-grey | deep cerulean |
| `forest` | `#0a7f60` / `#87c7ae` | sage | jade |
| `rose` | `#b13276` / `#f595c0` | mauve | berry — kept away from the red of errors |
| `amber` | `#a55115` / `#e8a681` | sand | copper |
| `slate` | `#424852` / `#b3b8c0` | graphite | monochrome: a near-black accent (L 0.40) |

White text on every light accent: 4.98:1 (forest) to 9.2:1 (slate); dark text on every dark
accent 8:1 or more. `Theme::available()` carries these light accents as the swatch colours.

**The switcher.** `Theme::switcher()` (and the *Appearance* part of `Header::user()`) shows six
swatches — the chosen one with a ring in its colour, a check mark and `aria-pressed`, never colour
alone — and one mode group. With `js/gridkit.js` 1.114.0 the group is **light | dark | auto**;
*auto* follows the system setting (`prefers-color-scheme`) and is stored per browser beside the
mode (`gk-mode-auto`, in the `GK.theme` scope). From code: `GK.theme.setMode('light' | 'dark' |
'auto')`; `GK.theme.toggleMode()` still toggles and ends *auto*. A copy of `gridkit.js` without the
1.114.0 block keeps the plain light/dark toggle — the auto button stays hidden. The markup since
1.114.0: the swatches sit in `.gk-theme-colors` (`role="group"`), each dot is `type="button"` and
carries its colour as `--gk-swatch`; the toggle and the auto button (`.gk-mode-auto`, rendered
`hidden`) sit in `.gk-mode-group` (`role="group"`). The old classes and attributes are unchanged.

### Colour system and roles

GridKit's colour is a set of **roles** (`--gk-*` custom properties), never literals: write a role
and a page follows the theme and both dark spellings (`[data-gk-mode="dark"]`, `.gk-dark`) by
itself. The principles behind the values (1.114.0): the neutrals carry the interface and the
accent is a signal (the one primary action, the selection, focus, links); neutrals are tinted
towards their theme, under 0.02 chroma; each role sits at a fixed OKLCH lightness; dark mode is
its own ladder of near-black with the theme's hue, layered upward; the semantic colours never
follow the theme.

| Role | Use it for | Light | Dark |
|---|---|---|---|
| `--gk-surface` | cards, the header, fields in focus, menus' paper | `#ffffff` | `#191b21` (L 0.222) |
| `--gk-surface-container` | the page under the cards, a muted band | `#f4f5f8` (L 0.970) | `#111218` (L 0.185) |
| `--gk-surface-container-low` | a subtle fill: card footer, resting field, stripe | `#f9fafc` | `#14161c` (recessed) |
| `--gk-surface-container-high` | hover on a surface, a track | `#ecedf2` | `#22242b` (raised) |
| `--gk-surface-container-highest` | pressed, the strongest neutral fill, menus in dark | `#e2e4e9` | `#2b2d36` |
| `--gk-on-surface` | text | `#181a22` (17:1) | `#ebecf1` (14.6:1) |
| `--gk-on-surface-variant` | secondary text, icons | `#565965` (7:1) | `#a4a7b2` (7.2:1) |
| `--gk-outline-variant` | hairlines: card edges, dividers | `#e4e5ea` | white at 8 % |
| `--gk-outline` | a control's edge | `#9b9ea9` | `#50525c` |
| `--gk-primary` / `--gk-on-primary` | the one primary action, selection, focus | accent / white | light accent / dark text |
| `--gk-primary-text` | the accent as text on a surface (links, chosen tab) | one step darker | = the accent, a step lower |
| `--gk-primary-container` / `--gk-on-primary-container` | a tonal fill: chosen segment, count, hover of the grip | L 0.95 / L 0.36 | L 0.36 / L 0.92 |
| `--gk-success` `--gk-warning` `--gk-error` `--gk-info` | dots, borders, icons — not text | emerald, amber, red, sky | lighter steps |
| `--gk-*-text`, `--gk-*-fill` | the semantic colour as text / as a filled button with white text | 4.5:1 and more | |
| `--gk-elev-1` … `-4` | shadow steps: card, raised card, menu, dialog | soft, two layers | deeper |

The ladder runs the same way in both modes: cards are lighter than the page, a hover is a step
away from its surface, a field is a step recessed. Prefer the role to the older alias
(`--gk-bg`, `--gk-text-muted`, …) in new code; since 1.114.0 the aliases resolve to their role in
dark mode too. The error role is a true red since 1.114.0 (`#dd2d34`, white 4.67:1), so a badge
or a bulk-delete button in it reads; the rose theme's accent stays clearly apart from it.

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

// Tree (since 1.116.0) — GridKit\Tree and js/tree.js, see "Tree". First argument: the tree's id, element or selector.
GK.tree.expand('pages', '7');              // opens node 7 and the path to it; no id: every loaded parent. A Promise.
GK.tree.collapse('pages');                 // every parent; with an id, one node
GK.tree.select('cats', ['books', 'music']); // ticks (multi) or selects (single); false as third argument unticks
GK.tree.selected('cats');                  // ['books', 'novels', …]
GK.tree.reload('pages', '5');              // fetches the children of 5 again (lazyUrl)
document.addEventListener('gk:treemove', e => { if (e.detail.parent === 'legal') e.preventDefault(); });

// Charts (since 1.121.0) — GridKit\Chart and js/chart.js, see "Chart". First argument: the chart's id, element or selector.
GK.chart.update('revenue', { series: [{ name: '2026', values: [9100, 8800, 10900] }] });  // any key of Chart::config()
GK.chart.data('revenue');                  // the config as it is now (a copy)
GK.chart.toggle('revenue', 1, false);      // switches series 1 off; returns whether it is shown
GK.chart.html(config);                     // the markup Chart::html() writes for a config, byte for byte
document.addEventListener('gk:chartselect', e => { /* e.detail: { chart, index, label, series, values } */ });
document.addEventListener('gk:charttoggle', e => { /* e.detail: { chart, series, name, visible } */ });

// Command palette (since 1.122.0) — GridKit\CommandPalette and js/command-palette.js, see "CommandPalette".
GK.palette.open('inv');                    // opens it, with a query typed in; no argument: empty. Returns the <dialog>.
GK.palette.close();
const off = GK.palette.register({ group: 'Tools', items: () => [{ label: 'Reload', action: 'reload' }] });  // off() takes it away
document.addEventListener('gk:paletteselect', e => { if (e.detail.item.kind === 'record') { /* cancelable */ } });
document.addEventListener('gk:paletteaction', e => { if (e.detail.action === 'reload') location.reload(); });

// Filter builder (since 1.123.0) — GridKit\FilterBuilder and js/filter-builder.js, see "FilterBuilder". First argument: the form, its id or the name.
GK.filterBuilder.get('f');                 // { join: 'and', rules: [{ field: 'status', op: 'is', value: 'open' }] } — the checked tree
GK.filterBuilder.set('f', '["and",["amount","gt",100]]');   // either shape or its JSON; drawn at once, nothing sent
GK.filterBuilder.reset('f');               // back to value(); GK.filterBuilder.apply('f') sends it like the button
GK.filterBuilder.json('f');                // '["and",["amount","gt",100]]' — the URL parameter
document.addEventListener('gk:filterchange', e => { e.preventDefault(); reloadTable(e.detail.json); });  // cancelable: filter live

// Kanban (since 1.124.0) — GridKit\Kanban and js/kanban.js, see "Kanban". First argument: the board's id, element or selector.
GK.kanban.move('tickets', 'T-101', 'done', 0);           // like a person's move: the veto, the save; a Promise of { ok, reverted }
GK.kanban.move('tickets', 'T-101', 'done', null, { save: false });  // only put there (the server knows it): no event, no request
GK.kanban.cards('tickets', 'done');                      // ['T-108', 'T-101']; without a column { new: [...], done: [...] }
GK.kanban.columns('tickets');                            // [{ key, label, count, limit, over }, …]
GK.kanban.refresh('tickets');                            // after the page added a card itself: handles, counts, limits
document.addEventListener('gk:kanbanmove', e => { if (e.detail.to === 'done' && !mayClose(e.detail.id)) e.preventDefault(); });
document.addEventListener('gk:kanbanmoved', e => { /* e.detail: { board, id, from, to, index, fromIndex, ok, reverted } */ });

// File manager (since 1.125.0) — GridKit\FileManager and js/file-manager.js, see "FileManager". First argument: the id, element or selector.
GK.files.upload('media', fileInput.files);  // queues files as if dropped: checked here, sent with progress; returns [{ name, state, message }]
GK.files.add('media', { id: 7, name: 'Logo.png', size: 4096, mime: 'image/png', url: '/media/7' });  // a row as files() takes it
GK.files.remove('media', [7, 8]);
GK.files.select('media', [7], true);        // ticks (false unticks); returns the ticked ids
GK.files.selected('media');                 // ['7', '8']
GK.files.view('media', 'list');             // 'grid' or 'list'; returns the view
GK.files.open('media', 7);                  // the details of a file; GK.files.close('media')
document.addEventListener('gk:filesuploaded', e => { /* e.detail: { manager, file, answer } */ });
document.addEventListener('gk:filesaction', e => { if (e.detail.action === 'download') { e.preventDefault(); /* yours */ } });
// Also: gk:filesselect { ids }, gk:filesopen { id, file }, gk:filesupload { file, name } (cancelable),
// gk:fileserror { name, message }, gk:filesdone { action, ids, ok, answer }.

// Inline editing (since 1.110.0) — Table::editable(), see "Table". Cancelable; respondWith() answers.
document.addEventListener('gk:celledit', e => { /* e.detail: { tableId, id, column, value, oldValue } */ });

// Dates (since 1.120.0) — js/calendar.js, see "DateRange" and "Calendar".
GK.dateRange.get('period');                    // { from: '2026-11-01', to: '2026-11-30', preset: 'this-month' }
GK.dateRange.set('period', 'last-month');      // or { from, to } (custom); fires no event
GK.dateRange.presetRange('this-quarter', '2026-11-04');   // { from: '2026-10-01', to: '2026-12-31' }
GK.dateRange.text('2026-10-01', '2026-10-31', 'period');  // 'Oct 1 – 31, 2026', in the picker's words
document.addEventListener('gk:daterange', e => { /* e.detail: { name, from, to, preset, text } */ });
GK.calendar.month('appointments');            // '2026-11'
GK.calendar.focus('appointments', '2026-11-12');
GK.calendar.go('appointments', '2027-01');    // loads that month (gk:calendarnav can refuse it)
GK.calendar.events('appointments', '2026-11-04');   // [{ id, title, href, start, end }]
document.addEventListener('gk:calendarselect', e => { /* { calendar, date, event, events }, cancelable */ });
document.addEventListener('gk:calendarnav', e => { /* { calendar, month, url, date }, cancelable */ });

// Line items (since 1.117.0) — see "LineItems". The same totals as LineItems::totals(), to the cent.
document.addEventListener('gk:lineitemschange', e => { /* e.detail: { items, totals } */ });
GK.lineItems.totals(document.querySelector('.gk-lines'));
GK.lineItems.add(document.querySelector('.gk-lines'), { description: 'Travel', qty: 1, price: '40', vat: 20 });

// Import (since 1.119.0) — GridKit\Import and js/import.js, see "Import". First argument: the importer's name, form or selector.
GK.import.read('customers', file);             // a File, Blob, bytes or text, as if chosen; a Promise of the result
GK.import.map('customers', { 'E-Mail': 'email' }); // set the mapping (column name or index => field key); no 2nd argument reads it
GK.import.result('customers');                 // { rows, errors, total, valid, invalid, columns, mapping, missing, notes, meta, error }
document.addEventListener('gk:importready', e => { /* e.detail.result — after every change */ });

// Tabs (since 1.111.0) — see "Tabs". A row by its .gk-tabs element or id; hand-written rows too.
GK.tabs.select('settings', 'bank');            // as a click (gk:tabchange can refuse it); { focus, force }
GK.tabs.current('settings');                   // the key of the current tab
GK.tabs.badge('settings', 'logo', 'Error', { tone: 'error' });   // null takes it away
GK.tabs.count('settings', 'log', 12);          // null takes it away
GK.tabs.reload('settings', 'log');             // a lazy panel, fetched again; a Promise of true/false
document.addEventListener('gk:tabchange', e => { /* cancelable; e.detail: { tabsId, from, to, panel, source } */ });
document.addEventListener('gk:tabshown', e => { /* after the switch */ });
document.addEventListener('gk:tabload', e => { /* a lazy panel: { tabsId, key, panel, ok, status } */ });

// Tooltips (since 1.112.0) — see "Tooltips". Delegated: new content needs no call.
GK.tooltip.show(el);  GK.tooltip.hide();  GK.tooltip.update();

// Tabs in a form (since 1.113.0) — Form::tab(), see "Form". Nothing to call for the usual case; after a
// script changed what is valid (setCustomValidity), count the error badges of the tabs again:
GK.formTabs.refresh(document.getElementById('customer'));
GK.formTabs.init(container);     // inserted markup: GK.initContent(container) does this too
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
inside the live container. Same look as `GK.rowPager`
(`.gk-rowpager` / `.gk-pg`), and since 1.118.0 as the pager of
`Table::paginate()`: one bar for every pager in GridKit. This one is for a list
you page yourself — its controls are links (`?page=N`, no script needed), its
count reads "238 invoices · Page 5 of 12" because it is handed pages, not rows
(a table's says "101–125 of 238"). A `Table` with `paginate()` draws its own.

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
| `gk-tab-nav-overflow` `gk-tab-arrow` `gk-tab-arrow-start` `gk-tab-arrow-end` | A line, pill or text row wider than its box: the mark and the two round arrows `GK.tabsOverflow` puts on and in it — never written by hand (1.111.0) |
| `gk-tab-loading` `gk-tab-fallback` | A lazy panel: the skeleton while it loads, the link a page without the 1.111.0 script shows (1.111.0) |
| `gk-tab-nav-linie` `gk-tab-nav-kapsel` `gk-tab-nav-text` | Tab row styles beside `gk-tab-nav-karte`: line with a stroke under the chosen tab, pill with the chosen tab filled, words only (1.103.0) |
| `gk-tab-nav-links` `gk-tab-nav-rechts` `gk-tab-nav-unten` `gk-tab-nav-fest` | Tab row alignment: a side column from 601px (the horizontal row below), the row after the panels, a bar that sticks to the lower edge (1.103.0) |
| `gk-tab-nav-sm` `gk-tab-nav-lg` `gk-tab-nav-fluid` `gk-tab-nav-icon-oben` `gk-tab-nav-nur-icon` `gk-tab-nav-neutral` | Tab row options: size, full width, symbol above the word, symbol only (`aria-label` + `title`), the chosen tab in the text colour (1.103.0) |
| `gk-tabs-nav` `gk-tab` | Tabs, generated nav (state class: `gk-tab-active`) |
| `gk-accordion` `gk-accordion-item` `gk-accordion-trigger` `gk-accordion-content` `gk-accordion-body` | Accordion (state class: `open` on the item) |
| `gk-gallery` `gk-gallery-item` `gk-gallery-overlay` | Image gallery |
| `gk-picker` `gk-picker-grid` `gk-picker-item` `gk-picker-media` `gk-picker-input` `gk-picker-caption` | Image picker (since 1.94.0): container, grid, one tile, its picture area, the radio drawn as that area, the title row |
| `gk-card-elevated` `gk-card-flat` | Card with a shadow / with a hairline border instead |
| `gk-card-image` | A picture flush with the top of a card (rounds the two upper corners) |
| `gk-card-title` `gk-card-actions` | Heading inside a card (since 1.114.0 semibold in the text colour, in the case it is written in — it was the accent in capitals) / a right-aligned row of buttons in its header |
| `gk-card-header` `gk-card-body` `gk-card-footer` | A card made of parts: since 1.114.0 a card with a `gk-card-body` lets its parts do the padding — header and footer lines run edge to edge, the footer is a quiet band, a `gk-card-image` sits flush. A card without a body keeps its own padding (20px 24px) |
| `gk-card-link` | The whole card is a link: under the pointer one step of elevation and a firmer edge, 1px up (none with reduced motion); a focus ring in the accent |
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
| `gk-rowpager` `gk-rowpager-info` `gk-rowpager-count` `gk-rowpager-nav` `gk-pg` `gk-pg-active` `gk-pg-icon` `gk-pg-off` `gk-pg-gap` | The one pager bar — `Pagination`, `Table->paginate()` (since 1.118.0, `<nav class="gk-pagination gk-rowpager">` inside the card, a line above it) and `GK.rowPager`: the count on the left, then the controls; the current page, an arrow, an arrow that leads nowhere, the ellipsis |
| `gk-pagination` `gk-page-btn` | The landmark class of a table's pager (an older gridkit.js removes it before it draws its own, which then wears the `.gk-pg` boxes), and the hand-written pager of 0.9 — write the bar above instead |
| `gk-col-p2` `gk-col-p3` `gk-col-p4` `gk-col-p5` | On th and td of a list column (`'priority' => n`): gone below 560 / 720 / 900 / 1040px of LIST width |
| `gk-cell-who` `gk-cell-who-text` `gk-cell-sub-wrap` | The who cell: avatar, name, a label that moves under the name, the address underneath; a second line that breaks instead of being shortened |
| `gk-avatar-tone-1` … `gk-avatar-tone-5` | Five quiet role pairs for initials, told apart side by side |
| `gk-dot` `gk-dot-success` `gk-dot-warning` `gk-dot-danger` `gk-dot-primary` `gk-dot-muted` `gk-dot-outline` `gk-dot-pulse` | Status dot beside its words; outline = empty ring, pulse = running (still under reduced motion) |
| `gk-setting` `gk-setting-text` `gk-setting-title` `gk-setting-hint` `gk-setting-control` `gk-setting-danger` | A settings row: title and one sentence, the switch on the right |
| `gk-field-static` `gk-field-static-label` `gk-field-static-value` | A read-only field (reason under it: `gk-field-hint`) |
| `gk-stat-tiles` `gk-stat-tile` `gk-stat-tile-value` `gk-stat-tile-label` `gk-stat-tile-sub` `gk-stat-tile-warning` `gk-stat-tile-danger` | Compact key figures (`StatCards->compact()`); a tile may be a `<button>` or `<a>` |
| `gk-sheet-meta` | A line under the side sheet's title |
| `gk-swatch` `gk-swatch-1` … `gk-swatch-5`, `gk-series-fill-1` … `-5`, `gk-series-stroke-1` … `-5` | Chart colours from `--gk-series-1` … `-5`: legend mark, SVG fill, SVG stroke |
| `gk-chart` `gk-chart-*` (since 1.121.0) | Written by `GridKit\Chart`, never by hand — see "Chart". `--gk-chart-1` … `5` are the series colours at 3:1 on every ground; `gk-chart-c1` … `c5` set them on a mark or a legend key |
| `gk-btn-touch` | A button at least `--gk-target-min` (44px) in both directions, type unchanged |
| `gk-message-actions` | Actions on the right of a `.gk-message` |
| `gk-message-sm` | A small message (since 1.95.0): denser than `.gk-message-compact` and with no margin of its own; the tone comes from `-info` `-success` `-warning` `-error` |
| `gk-announce` | A live region that is always on the page (since 1.93.0): empty it has no box but stays in the accessibility tree; `GK.announce()` writes its words |
| `gk-timeline` `gk-timeline-step` `gk-timeline-marker` `gk-timeline-body` `gk-timeline-head` `gk-timeline-title` `gk-timeline-state` `gk-timeline-time` `gk-timeline-chips` `gk-timeline-detail` `gk-timeline-error` | A log of steps (since 1.96.0): `<ol role="list">`, one `<li>` per step, a symbol and a word for the state, title, time, chips, a detail and an error block |
| `gk-timeline-ok` `gk-timeline-failed` `gk-timeline-running` `gk-timeline-waiting` `gk-timeline-skipped` | The state of a step, on its `<li>`: check, cross, a turning ring (still under reduced motion), an empty ring, a dash |
| `gk-video-frame` `gk-video-frame-media` `gk-video-frame-caption` | A 9:16 frame for a video or picture (since 1.96.0): never wider than the column or taller than 75% of the window, the picture shown whole, no autoplay |
| `gk-video-frame-safe` `gk-video-frame-busy` `gk-video-frame-error` | On the frame: guide lines for the zones a platform covers (top 10%, bottom 20%, no click caught) / nothing to show yet / it broke — the last two reuse `.gk-empty` |
| `gk-sort-griff` `gk-sort-aktiv` | The handle the script puts in an entry (since 1.97.0) and the mark on a container that has handles (two entries or more). Since 1.114.0 a quiet grip (subtle surface, hairline); under the pointer the tonal accent, lifted with the keyboard the accent fill, and the entry whose grip is under the pointer or in focus gets a thin accent ring |
| `gk-sort-zieht` `gk-sort-frei` `gk-sort-platz` | The entry in the air (accent outline, the surface, the highest elevation), the entry flying with the pointer (fixed), and the dashed gap it left (in a share of the accent) |
| `gk-theme-switcher` `gk-theme-colors` `gk-theme-dot` `gk-theme-active` | The theme switcher: its swatches (`--gk-swatch`, the chosen one with ring and check) |
| `gk-mode-group` `gk-mode-toggle` `gk-mode-auto` `gk-mode-light` `gk-mode-dark` | The mode group of the switcher (1.114.0): the light/dark toggle and the auto button the script shows (light, dark, auto as one track with one thumb) |
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
        <button type="button" class="gk-tab-btn gk-active" data-tab="overview">Overview</button>
        <button type="button" class="gk-tab-btn" data-tab="details">Details</button>
    </div>
    <div class="gk-tab-panel gk-active" data-tab="overview">…</div>
    <div class="gk-tab-panel" data-tab="details">…</div>
</div>
```

The `data-tab` values must match between a button and its panel, and exactly
one of each starts with `gk-active`. `type="button"`: a tab inside a `<form>`
must not submit it. **`GridKit\Tabs` writes this markup for you** (since
1.111.0) — every style, alignment and option of this section as a call, plus deep
links, remembered tabs and lazy panels; see *Tabs from PHP* at the end of this section.

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
- **Tabs inside a form** (since 1.113.0): use `Form::tab()` (see **Form**), not this markup by hand — it
  opens the tab of a field the browser or the server rejects and puts the error badge on every such tab.
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
  is at least 44px high; a row too wide for its box scrolls sideways, with an arrow at each side that has
  more since 1.111.0 (the card style becomes the grid).
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

**Tabs from PHP: `GridKit\Tabs`** (since 1.111.0) — writes exactly the authored markup above (and the
link row), so every style, alignment and option of this section is a call and a page writes no tab
markup by hand. It **prints**, like `Table`: `->render()`; `->html()` returns the same markup as a string.

```php
$address = '<p>Company name and address.</p>';
(new Tabs('settings'))
    ->style('karte')                    // karte | linie | kapsel | text — without it, the classic row
    ->label('Settings')                 // aria-label of the row
    ->tab('general', 'General', ['icon' => 'tune', 'content' => $address])
    ->tab('bank', 'Bank', ['content' => function () { echo '<p>IBAN and BIC.</p>'; }])
    ->tab('logo', 'Logo', ['badge' => ['text' => 'Error', 'tone' => 'error']])
    ->tab('log', 'Log', ['count' => 12, 'url' => 'panels/log.php'])   // fetched when first opened
    ->tab('api', 'API', ['disabled' => true])
    ->hash()                            // #settings=bank in the address, read on load
    ->remember()                        // the last tab per row, in the browser
    ->param('tab')                      // ?tab=bank opens Bank on the server, without any script
    ->render();

// A row of links between pages: every tab has an 'href'; active() marks the current page.
(new Tabs('areas'))->style('linie')->label('Areas of this page')->active('results')
    ->tab('edit', 'Edit', ['href' => '/page/7/edit', 'icon' => 'edit'])
    ->tab('results', 'Results', ['href' => '/page/7/results', 'icon' => 'bar_chart'])
    ->render();
```

| Call | Writes | Note |
|---|---|---|
| `new Tabs(string $id)` | `<div class="gk-tabs" id="…">` | one word: the element id, the name in the address, the storage key |
| `->tab(string $key, string $label, array $opts = [])` | a `.gk-tab-btn[data-tab]` and its `.gk-tab-panel` | `$key` pairs tab and panel; options below |
| `->style('karte' \| 'linie' \| 'kapsel' \| 'text')` | `gk-tab-nav-karte` … | without a style: the classic row |
| `->align('top' \| 'links' \| 'rechts' \| 'unten' \| 'fest')` | `gk-tab-nav-links` …; `fest` writes `-unten -fest` | `unten` and `fest` put the row after the panels |
| `->size('sm' \| 'md' \| 'lg')` | `gk-tab-nav-sm`, `-lg` | `md` is the default |
| `->fluid()`, `->iconTop()`, `->neutral()` | `gk-tab-nav-fluid`, `-icon-oben`, `-neutral` | |
| `->iconOnly()` | `gk-tab-nav-nur-icon` | each tab gets `aria-label` (its word, badge and count) and `title` |
| `->secondary()` | `gk-tab-nav-secondary` | the second row of the classic row, or of `style('karte')` in a surface or a window |
| `->fill()` | `gk-tab-nav-fill` | the classic row as the main row of a page; never with a style |
| `->oneRow()`, `->flat()` | `gk-tab-nav-einzeilig`, `-flach` | `style('karte')` on a phone (1.104.0) |
| `->surface(bool $on = true)` | `gk-tab-flaeche` on the panels | on by default with `style('karte')`, off for its `secondary()` row |
| `->active(string $key)` | `gk-active` | the page's decision (the tab with the error): it wins over the address and the memory |
| `->label(string $label)` | `aria-label` on the row | |
| `->hash()`, `->remember()`, `->param(string $name = 'tab')` | `data-gk-tabs-hash`, `-remember`, `-param` | deep links and memory, below |
| `->render()`, `->html()` | | prints, returns |

| Option of `->tab()` | |
|---|---|
| `icon` | a Material icon name before the word (`gk-tab-icon`) |
| `badge` | text, or `['text' => 'Error', 'tone' => 'error']` — only for a deviation from the default |
| `badgeError` | `true`: the red badge (`gk-tab-badge-error`); the word stays |
| `count` | a number of things behind the tab (`gk-tab-count`); `0` is shown, leave the key out for none |
| `disabled` | dimmed, stepped over by the arrow keys, never the active tab |
| `title` | a tooltip; with `iconOnly()` it is the word unless you give one |
| `content` | the panel's HTML — **trusted markup, not escaped** — or a callable that echoes (or returns) it |
| `url` | a lazy panel, fetched when its tab is first opened (below) |
| `reload` | with `url`: fetched again every time the tab is chosen |
| `href` | the tab is a link to a page: the row becomes `<nav class="gk-tab-nav">` with `<a class="gk-tab-btn" data-tab="…">`, the current page (`active()` or the parameter) with `aria-current="page"`. Every tab needs one: a row is links OR panels |

- **Escaped**: the id, keys, words, icons, badges, counts, titles and addresses. Not `content` — it is
  markup, like a `Form` `html` field; escape data inside it yourself. `url` and `href` take a path or an
  http(s) address: `javascript:`, `data:` and every other scheme are refused.
- **A wrong value warns and falls back, it never throws**: an `E_USER_WARNING` ("GridKit: Tabs 'settings':
  …") and the nearest correct markup. An unknown style, alignment or size is left out; an alignment, size
  or option of 1.103.0 without a style gets `style('linie')`; a duplicate or empty key keeps the first tab;
  an `active()` that names no tab, or a disabled one, falls back to the parameter, then the first tab; tabs
  with `href` mixed with panel tabs are left out; `secondary()` with a line, pill or text style or at the
  bottom, `fill()` with a style, `oneRow()`/`flat()`/`surface()` without the card style are left out.
- **The active tab on the server**: `->active()`, else the parameter of `->param()` (an unknown, disabled or
  array value is passed over in silence — a hand-typed address is not the page's bug), else the first tab
  that is not disabled. A row of links marks no page current without `active()` or the parameter.

**Deep links and memory** (opt-in):

- `->hash()` — the chosen tab stays in the address as `#settings=bank`. Several rows and the page's own
  fragment share it, joined by `&` (`#top&settings=bank&inner=x`); each row rewrites only its own pair. A
  switch updates it with `replaceState`: it is no navigation, so Back leaves the page as before. On load
  the pair selects the tab — of a nested row too, and the outer panel around it is opened; a link to
  `#settings=bank` and Back/Forward between such links switch it (`hashchange`). A key no tab can take is
  replaced by the tab shown. A page that reads its fragment itself reads the part before the first `&`.
- `->param('tab')` — the server reads the active tab from `$_GET['tab']`: a link opens a tab with no script
  at all. While the address carries the parameter a switch rewrites it in place; it never adds one.
- `->remember()` — the last tab per row in `localStorage` (`gk-tab-<id>`, in the scope of `GK.theme`: the
  next person at a shared computer does not inherit it). Storage that is missing or throws just forgets.
- **On load**: `->active()` (the page decides; nothing overrides it), then the fragment, the parameter,
  the memory, the first tab. Hand-written rows take the attributes `data-gk-tabs-hash`,
  `data-gk-tabs-remember` and `data-gk-tabs-param="tab"` on a `.gk-tabs` with an `id`.

**Lazy panels** (`'url' => 'panels/log.php'`; by hand `data-gk-tab-url="…"` and `data-gk-tab-reload` on the
`.gk-tab-panel`): fetched the first time the tab is opened — a GET to the same origin with
`X-Requested-With: XMLHttpRequest` — with a skeleton meanwhile (`gk-tab-loading`, lines of `gk-skeleton`,
`aria-busy="true"` on the panel), and kept. `GK.initContent()` runs on the answer, so tables, selects,
forms and tabs inside work; a lazy panel inside a loaded one loads too. Scripts in the answer do not run.
`'reload' => true` fetches on every choice and keeps the old content, dimmed, until the new one is in.
A failure — an error status, no network, a whole page (a login page after the session ended), another
origin — shows the table's failure state: "This tab could not be loaded." with "Try again" (`gk-empty`,
`role="alert"`), never the server's own answer. The endpoint answers the fragment to an AJAX request.
Until the tab is opened the panel holds a link to the address (`gk-tab-fallback`, "Open “Log”"): what a
page whose gridkit.js is older than 1.111.0 shows instead of a skeleton that never ends — so the endpoint
may answer a page of its own when it is opened directly.

**Events** — on the `.gk-tabs`; they bubble, so a listener on an outer row hears the rows nested in it:

```javascript
document.getElementById('settings').addEventListener('gk:tabchange', function (e) {
  // e.detail: { tabsId, from, to, panel, source } — source "user" (click or key), "api", "address"
  if (e.target !== this) return;             // a nested row
  if (form.dirty) e.preventDefault();        // the tab stays: unsaved changes in this panel
});
document.addEventListener('gk:tabshown', e => { /* after the switch; same detail */ });
document.addEventListener('gk:tabload', e => { /* a lazy panel: { tabsId, key, panel, ok, status } */ });
```

Every switch fires them — a click, the arrow keys, `GK.tabs.select()`, the address; the tab a page opens
with fires none. A refused switch keeps the tab, its panel, `aria-selected` and the address, and after a
key the focus goes back to the tab that stays. To switch once the person has confirmed (say in
`GK.confirm()`), call `GK.tabs.select(row, key, { force: true })`: the event is then not cancelable.

**JS API** — the target is the `.gk-tabs` (or any element in it) or its id; it works on hand-written
authored markup as well as on `Tabs` output:

```javascript
GK.tabs.select('settings', 'bank');                  // as a click; false when refused, unknown or disabled
GK.tabs.select('settings', 'bank', { focus: true, force: true });
GK.tabs.current('settings');                         // 'bank' — of a row of links: the aria-current one
GK.tabs.badge('settings', 'logo', 'Error', { tone: 'error' });   // null or '' takes it away
GK.tabs.count('settings', 'log', 12);                // null takes it away; 0 is a count
GK.tabs.reload('settings', 'log').then(ok => {});    // a lazy panel, now; true once the new content is in
```

`GK.tabs.*` and the events serve the authored shape (`.gk-tabs`); the generated shape (`[data-gk-tabs]`)
keeps what it had. `badge()` and `count()` also take a row of links (`Tabs` writes `data-tab` on its links).

**A line, pill or text row wider than its box** (since 1.111.0) still scrolls sideways, and a round arrow
shows at each side that has more (`gk-tab-arrow`, `gk-tab-arrow-start`, `gk-tab-arrow-end`); a click pages
the row. `GK.tabsOverflow` measures the row by its own width, like `GK.tabsFit`, and puts
`gk-tab-nav-overflow` on it — never set it by hand. Scroll arrows, not a "More" menu: every tab stays a
tab of the one tablist, the arrow keys reach all of them and bring each into view, a screen reader hears
"tab 5 of 8"; the arrows are pointer aids, `aria-hidden` and outside the tab order. The chosen tab is
scrolled into view on load and on every switch. The card style keeps its grid; without the script the
row scrolls as before.

**With an older gridkit.js** (the PawBot dashboard keeps its own copy): `Tabs` output is the hand-written
markup, so its tabs switch with roles and arrow keys as always; the address, the memory, the events, the
JS API and the arrows do nothing, and a lazy panel shows its link.

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

One engine (since 1.112.0) draws every tooltip GridKit has. It places the bubble on the
preferred side, flips it to the opposite side when it does not fit (then tries the other
two), shifts it along the edge to stay inside the window — and inside the visible part of a
box that scrolls — and keeps the arrow pointing at the trigger after the shift. It opens on
hover after a delay and at once on keyboard focus (`:focus-visible`: a mouse click on a button
is not a request), on a tap on touch, and closes on Escape, blur, the pointer leaving (with
100 ms of grace, so the pointer can move onto the bubble and rest there), the trigger
scrolling out of sight and a tap elsewhere. One tooltip at a time.

Five forms, from least to most:

```html
<!-- 1. Any title attribute. GK.tip moves it to data-gk-tip on the first hover or focus
        (the browser's own popup stops competing) and shows it in the engine's bubble;
        \n breaks the line. Opt out with data-gk-tip-off on the element or an ancestor. -->
<button title="Save the current draft">Save</button>

<!-- 2. Plain. Without the script this is the CSS-only tooltip it always was. -->
<span data-gk-tooltip="Net, before VAT" data-gk-tooltip-pos="bottom">Net</span>

<!-- 3. Rich: real HTML in the bubble. Give the element an id: it describes the trigger. -->
<span data-gk-tooltip-rich="#vat-help">VAT</span>
<div id="vat-help">Reduced rate — <strong>10&nbsp;%</strong> for food and books.</div>

<!-- 4. Toggletip: opens on a click, Enter or Space — not on hover — and stays open. -->
<button type="button" data-gk-toggletip="Counted from the invoice date.">Due</button>
<button type="button" data-gk-toggletip="#vat-rules">VAT rules</button>
<div id="vat-rules" hidden>See <a href="/vat">the rules</a> or <button type="button">ask</button>.</div>

<!-- 5. The help icon: a toggletip on a ready-made "?" button (Tooltip::help() writes it). -->
<button type="button" class="gk-help" data-gk-toggletip="On your bank card." aria-label="More about IBAN">…</button>
```

**Options** — attributes on the trigger, the same for every form (each one optional):

| Attribute | PHP option | Values | Default |
|---|---|---|---|
| `data-gk-tooltip-pos` | `pos` | `top`, `bottom`, `left`, `right`, `auto` (above or below, whichever has more room, then the sides) | `top` |
| `data-gk-tooltip-tone` | `tone` | `neutral` (slate), `info`, `success`, `warning`, `danger`, `light` — text at 4.5:1 or more on its ground in light and dark mode, under every theme | slate; a toggletip is a surface card |
| `data-gk-tooltip-max` | `max` | the widest it gets: pixels (`240`) or a length (`20rem`) | 360px (toggletip 320px) |
| `data-gk-tooltip-kbd` | `kbd` | a keyboard hint shown as `<kbd>` after the text (`Ctrl+S`); a screen reader hears "Save (Ctrl+S)" | — |
| `data-gk-tooltip-delay` | `delay` | ms before it opens on hover, 0–10000; moving on from a tooltip just shown opens the next at once | 300 |
| `data-gk-tooltip-wrap` | `wrap` | several lines, at most 280px | one line in the CSS-only form |

**PHP — `GridKit\Tooltip`.** Every helper returns a string — echo it. `Tooltip::attrs()`,
`Tooltip::rich()` and `Tooltip::toggletip()` return attributes with a leading space;
`Tooltip::help()` returns the icon button. The text is escaped. The first argument of
`attrs()`, `toggletip()` and `help()` is the text, or an array that carries it under
`'text'` with its options beside it — the shape the component options take. A bad option
(`'pos' => 'middle'`, an unknown key) is an `E_USER_WARNING` and is left out; the tooltip
still shows. An empty text writes nothing.

```php
echo '<span' . Tooltip::attrs('Net, before VAT', ['pos' => 'bottom', 'tone' => 'info']) . '>Net</span>';
echo '<button type="button"' . Tooltip::attrs('Save', ['kbd' => 'Ctrl+S', 'delay' => 0]) . '>Save</button>';
echo '<span' . Tooltip::rich('#vat-help', ['max' => 320]) . '>VAT</span>';
echo '<button type="button"' . Tooltip::toggletip('Counted from the invoice date.') . '>Due</button>';
echo 'IBAN ' . Tooltip::help('On your bank card.', ['label' => 'IBAN']);   // "?" named "More about IBAN"
echo Tooltip::help('#vat-rules', ['icon' => 'info']);                        // an "i"; the content is #vat-rules

// The component options — a description, never the name, and never with a title beside it.
echo Button::icon('delete', ['tooltip' => ['text' => 'Deletes the invoice for good', 'tone' => 'danger']]);
(new Table('invoices'))->setData($rows)
    ->column('name', 'Customer', ['tooltip' => 'As on the contract'])   // a help icon in the header
    ->button('edit', ['icon' => 'edit', 'tooltip' => 'Edit this customer'])
    ->render();
(new Form('bank'))->field('iban', 'IBAN', 'text', ['help' => 'On your bank card.'])->render();
```

`help()` takes `label` (what it explains; its name is "More about {label}" from the language
file, "More information" without) and `icon` (`help`, a question mark, or `info`, an i)
besides `pos`, `tone`, `max` and `kbd`. `rich()` takes the id of the element and `pos`,
`tone`, `max`, `delay`.

**On the components** (additions — nothing changes for code that does not use them):

- `Button::render($label, ['tooltip' => 'Text' | ['text' => …, 'pos' => …, 'tone' => …, 'kbd' => …]])`
  — the GridKit tooltip instead of a `title`. An icon-only button is still named by `aria`,
  `title` or its icon; the tooltip is its description. With `tooltip` no `title` is written,
  even when one is given (it still names the button): two popups would compete.
- Table column `'tooltip'` — a `.gk-help` icon beside the header label, a button of its own
  next to the sort button (inside it, a click would sort). Table row button `'tooltip'` —
  its attributes instead of the browser `title`. Both come back after a sort of a
  `setData()` table, exactly as the server wrote them.
- Form field `'help'` — a `.gk-help` icon beside the label that opens the text on a click.
  The label and the icon stand in a `.gk-label-row`; the icon is never inside the `<label>`,
  so the field's name stays its label. Without a visible label (a checkbox) it follows the
  control. `'hint'` keeps what it does: the sentence of a settings row and of an upload zone.

**JavaScript.** Everything is delegated: content that arrives later works without a call.
`GK.initContent(root)` (and so a modal, a live table, AJAX navigation) also joins new
triggers to their text for a screen reader before their first focus.

```javascript
GK.tooltip.show(el);    // open the tooltip (or toggletip) of el now, without a delay
GK.tooltip.hide();      // close what is open
GK.tooltip.update();    // place it again — after its text or the layout around it changed
GK.tooltip.init(root);  // bind the triggers in root (GK.initContent calls it)
GK.tip.show(el);        // the same for an element with a title; GK.tip.hide() closes it
```

**What a screen reader gets.** A plain or title tooltip describes its trigger through
`aria-describedby` (a hidden copy of the text — an `::after` is not in the accessibility
tree). A trigger that cannot take the focus gets `tabindex="0"`; one that wraps a control
describes that control. A title on a control that has no name of its own (an icon button)
becomes its `aria-label`, as before; on one that has a name it becomes its description —
it used to replace the visible name.

**The toggletip** is a live region (`role="status"`) right after its trigger. Opening it
reads its text out and the focus **stays on the trigger** (`aria-expanded` says it is
open): a help text is read, not operated, and moving the focus into a popup would take a
person out of the form they were filling in. Tab walks from the trigger into its links and
buttons, because they come next in the document. It closes on Escape (the focus comes
back), a click outside, a second click, or the focus moving on past it. `'#id'` content
moves into the bubble and back, hidden, when it closes; a `<template>` is copied.

**Escape** closes a toggletip, or a tooltip the keyboard is on, and claims the key: a modal
or sheet around it stays open, and the next Escape closes that. A tooltip the mouse merely
rests on closes without taking the key.

**Touch.** A tap on a trigger that is only a trigger (a word, an icon that is not a control)
shows its tooltip; a second tap, or a tap elsewhere, hides it. A tap on a button or link
with a tooltip is its click, untouched. The `.gk-help` icon is 24px to look at and 44px to
a finger.

**Without the script** — or with an older copy of `js/gridkit.js`, as the PawBot dashboard
keeps one — the CSS-only tooltip and the old `GK.tip` work exactly as before, and a text
toggletip or help icon shows its text as the CSS-only tooltip on hover. The engine marks
`<html data-gk-tt>`; only then does the stylesheet drop the `::after`, so nothing shows twice.
`prefers-reduced-motion` turns the fade off.

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
