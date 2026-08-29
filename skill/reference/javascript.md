# GridKit 1.61.0 — JavaScript

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

// Table refresh (after save/delete in server-side mode).
// Returns false when no table with that id is on the page.
GK.table.refresh('table-id');
GK.table.refreshAll();          // every table on the page
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
