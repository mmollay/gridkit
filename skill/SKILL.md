---
name: gridkit
description: >-
  Build admin UI in PHP with GridKit — tables with search, sort, filter and
  paging over AJAX, forms on a 16-column grid, sidebars, headers, modals, six
  themes and dark mode. Use when writing or changing PHP that renders an admin
  page, dashboard, CRUD list or data table with GridKit, or when a project has
  GridKit in composer.json.
---

# GridKit 1.93.0

PHP components for admin dashboards. Zero dependencies, no build step,
PHP 8.2+. A checkout is a working install.

**Read this file first.** It holds the rules that are not guessable from the
API — the ones every agent given only the reference has tripped over. Then open
a reference below for the component you need, rather than all of them.

| Reference | What is in it |
|---|---|
| [reference/components.md](reference/components.md) | Every component, every option, every public method |
| [reference/javascript.md](reference/javascript.md) | `GK.*` — modals, toasts, live tables, global search |
| [reference/css.md](reference/css.md) | Class names and the utility classes |

The same content in one file, for pasting into a context that has no file
access: <https://gridkit.ssi.at/skill>

---

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
