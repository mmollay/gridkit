# GridKit 1.93.0 — components

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

**`->selectable('id')`:** checkbox column, a select-all in the header, and a bulk bar. Deleting fires `gk:bulkdelete` with `{ ids, tableId }` — the application does the deleting. Every change fires `gk:selectionchange` with `{ ids, tableId, count }`, bulk bar or not. Shift-click selects a range. Without the `Table` class: `data-gk-table` + `data-gk-selectable`, rows carrying `data-gk-row-id` and a `td.gk-cb-col`. A row without `data-gk-row-id` cannot be selected. Select-all covers the visible rows only. After a live reload (`gk-live-reloaded`) GridKit re-binds the table.

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
```

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
| Tabs (JS) | `.gk-tabs` / `[data-gk-tabs]` | Tab navigation, two shapes — full tablist semantics and arrow keys since 1.69.0 |
| Accordion (JS) | `.gk-accordion` | Collapsible sections, optional single-open (`data-gk-single`) |
| Tooltips (JS/CSS) | `title` / `data-gk-tooltip` / `data-gk-tooltip-rich` | Hint popups — plain, CSS-only, or with HTML in them |
| Gallery + Lightbox (JS) | `.gk-gallery` / `GK.lightbox` | Image grid with lazy loading and a keyboard-operable viewer |
| Announcement (JS/CSS) | `.gk-announce` / `GK.announce` | The line that says "Saved." where the person works, read by a screen reader; empty it draws no box (since 1.93.0) |
| Side sheet (JS) | `.gk-sheet` / `GK.sheet` | A panel a row opens: docked right beside the list, full screen and modal on a phone (since 1.91.0) |
| Icon | `GridKit\Icon` | Inline SVG icons with a Material Icons fallback — `Icon::svg($name, $px)`: the 2nd argument is an **int** pixel size (default 16), not an options array |
