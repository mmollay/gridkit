# GridKit 1.93.0 — JavaScript

Generated from GRIDKIT_SKILL.md. Rules first: see ../SKILL.md.

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
