# GridKit 1.120.0 — components

Generated from GRIDKIT_SKILL.md. Rules first: see ../SKILL.md.

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
| Tree | `GridKit\Tree` / `js/tree.js` / `GK.tree` | Page trees, categories, folders: a nested list that works without script, made a WAI-ARIA tree view — keys, type-ahead, open/closed remembered, single or multi selection with tri-state parents, a filter, children loaded on demand, moving by drag and drop or keyboard with a save URL and a way back (since 1.116.0) |
| Calendar | `GridKit\Calendar` / `js/calendar.js` / `GK.calendar` | A month of appointments, deadlines and campaigns: a table of days with events as links, bars across days, "+3 more", week numbers, months as links (`?month=2026-12`), an agenda when narrower than 600px, arrow keys / Home / End / PageUp / PageDown with the script (since 1.120.0) |
| Side sheet (JS) | `.gk-sheet` / `GK.sheet` | A panel a row opens: docked right beside the list, full screen and modal on a phone (since 1.91.0) |
| Icon | `GridKit\Icon` | Inline SVG icons with a Material Icons fallback — `Icon::svg($name, $px)`: the 2nd argument is an **int** pixel size (default 16), not an options array |
