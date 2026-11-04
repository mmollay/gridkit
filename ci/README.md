# Continuous integration

`github-actions.yml` runs `php tests/run.php` on PHP 8.2, 8.3 and 8.4, plus one
job with `mbstring` switched off — the configuration GridKit documents as
supported.

It is not at `.github/workflows/` because the token this repository is pushed
with has no `workflow` scope, and GitHub refuses a push that creates or edits a
workflow file without it. So the file ships here instead, one move away from
being live.

**To turn CI on**, from a clone with normal push rights:

```bash
mkdir -p .github/workflows
git mv ci/github-actions.yml .github/workflows/ci.yml
git commit -m "ci: enable the workflow"
git push
```

The first run starts on that push. Nothing else needs configuring — the suite
has no dependencies, so there is no cache, no lockfile and no install step.

## Until then: the same matrix, locally

```bash
bash ci/matrix.sh
```

It runs the suite on every PHP the machine has and says which of them had
mbstring, because README.md promises 8.2+ and says mbstring is used when
present but never required. Both halves of that claim went unverified until
1.50.0 — everything had only ever run on one interpreter.

```
  ok   PHP 8.2.33   with mbstring     ok — 2098 assertions passed
  ok   PHP 8.4.24   with mbstring     ok — 2098 assertions passed
  ok   PHP 8.5.9    without mbstring  ok — 2098 assertions passed
```

## The Releases page

Fifty-eight tags (v1.55.0 … v1.93.4) had no Release object: Packagist and the
tags were current while GitHub's Releases page, which is what most people look
at first, ended at v1.54.0. A tag is not a release — GitHub keeps them apart.

`ci/github-release.yml` closes that gap and keeps it closed once it is switched on
(below):

- **Push a tag `v<VERSION>`** — the workflow checks the tag against `VERSION`,
  cuts the notes of that version from `CHANGELOG.md`
  (`bash ci/release-notes.sh <version>`), adds the line "Live demo:
  https://gridkit.at/demo/" and creates the Release "GridKit <version>". If the
  Release exists already, it does nothing.
- **Run workflow** (Actions → Release → Run workflow, input `since`, default
  `1.54.0`) — ONE catch-up Release for the tag of the current `VERSION`: the
  notes of the newest version in full and every version since `since` as a list
  of the titles of its changes (`bash ci/release-notes.sh --since 1.54.0`).
  GitHub refuses a Release text over 125 000 characters and the whole of 1.54.0
  → 1.93.4 is 150 000, so the script falls back to titles once the full text
  passes 120 000 bytes. The tag has to be on GitHub already; run it again and it
  replaces the notes of that Release instead of adding another.

The workflow needs `permissions: contents: write` and nothing else (the job's
own `GITHUB_TOKEN`).

**Switching it on** is the same step as for `github-actions.yml` below, for the
same reason: GitHub refuses a push that creates a file under
`.github/workflows/` unless the token has the `workflow` scope, and
`.gitignore` keeps that folder out of commits made from this machine (a tracked
workflow once blocked every push for three days). So the file is committed here
and moved there by whoever has the rights:

```bash
mkdir -p .github/workflows
cp ci/github-release.yml .github/workflows/release.yml
git add -f .github/workflows/release.yml
git commit -m "ci: create GitHub Releases from tags"
git push                      # with a token that has the `workflow` scope
git tag v1.94.0 && git push origin v1.94.0     # the Release for this version
```

Or: GitHub → Add file → Create new file → `.github/workflows/release.yml`, paste
`ci/github-release.yml`. Then, once: Actions → Release → Run workflow (`since`
1.54.0) for the catch-up Release, which replaces the notes of the Release the
tag push made. What to look for: the Releases page lists "GridKit 1.94.0" as
the latest, with the notes of 1.94.0 in full, the titles of 1.93.4 … 1.55.0
below them and the line "Live demo: https://gridkit.at/demo/".

To print a body by hand — for a Release made in the web form instead:

```bash
bash ci/release-notes.sh              # the current VERSION
bash ci/release-notes.sh 1.42.0       # any released version
bash ci/release-notes.sh --since 1.54.0   # the catch-up text
```

It refuses a version `CHANGELOG.md` does not know, rather than printing the
whole file — which is what its first version did.

## Behaviour in a real browser

`php tests/run.php` executes no JavaScript; every check of `js/gridkit.js` in it
compares strings. `ci/browser.js` drives real component markup in headless
Chromium instead — which layer Escape closes, what the client-side rebuild of a
table keeps, what a hand-written modal is given:

```bash
node ci/browser.js                     # needs the "playwright" package
GK_PLAYWRIGHT=/path/to/node_modules/playwright GK_PHP=php8.2 node ci/browser.js
```

Like `ci/parity.php` it stays outside the suite: GridKit has no dependencies,
and the suite keeps that promise. Run it when touching `js/gridkit.js`.

Two blocks that are CSS and markup only have a script of their own, on a page of
their own (`ci/browser-fixture.php --timeline-video`), because a stylesheet is
tested by looking at boxes: `ci/browser-timeline-video.js` measures the timeline
(`.gk-timeline`: the five symbols and where they sit, the line, the ring and its
stopping under reduced motion, long words on a phone, both dark spellings, forced
colours, the keyboard) and the portrait video frame (`.gk-video-frame`: 9:16 at every
column width and window height, the limit, the picture in the box, the safe zones and
a click through them, the empty states). It runs in about fifteen seconds, which makes
the counter-checks of a rule practical:

```bash
GK_PLAYWRIGHT=/path/to/node_modules/playwright GK_PHP=php8.2 node ci/browser-timeline-video.js
GK_SHOTS=/some/dir node ci/browser-timeline-video.js     # also saves pictures to look at
```

The sortable container (`data-gk-sortierbar`, `js/sortierbar.js`, 1.97.0) has one as
well, `ci/browser-sortierbar.js` on `ci/browser-fixture.php --sortierbar`, because what it
does only shows in a browser: it pulls a row with the mouse, a card across a grid and a
row with a finger (touch events through the DevTools protocol), moves it with the keys
(space, arrows, Home, End, Escape; a three-column grid and a right-to-left one), and
holds the pull at the window edge so that the page scrolls. A small local server plays
the address the order is saved to: it answers ok, 500, `{"ok":false}`, nothing at all,
or slowly, so that the save, the failure that puts the row back and the cancelled earlier
request are seen. It also measures the handle (44 × 44 px, the name, the corner), the
contrast of handle and lines in light, dark and the dark class, the phone width and
reduced motion. About two minutes; the glyphs of the icon font are not on the page unless
`GK_ICONFONT` names a Material Icons file (`.woff2`), which only matters for the pictures.

```bash
GK_PLAYWRIGHT=/path/to/node_modules/playwright GK_PHP=php8.2 node ci/browser-sortierbar.js
GK_SHOTS=/some/dir GK_ICONFONT=/some/MaterialIcons.woff2 node ci/browser-sortierbar.js
```

The compact card of a table on a phone (1.106.0) is CSS only, so its rules are held in
`tests/mobile.test.php` and what they do is measured by `ci/browser-karten.js` on
`ci/browser-karten-fixture.php`: eleven tables in card mode at 390px, light and dark — a
link on the row and on another column, buttons on both sides, a checkbox, a muted or
hidden first column, every column muted, one column, groups with a footer, the empty
state, the invoice list of the demo. Per table: every card a grid, nothing out of the card
sideways, the expected title without a label, every field labelled and below the head, the
cells of the head side by side without overlapping and ending in one line, the head and
the fields on one left edge, a field one line tall; the order of the head; the invoice
card at most 210px (386 before); 40px for the chevron; at 1200px a table row again.
Each rule of the block was taken out in turn and the run turned red. About twenty seconds.

```bash
GK_PLAYWRIGHT=/path/to/node_modules/playwright GK_PHP=php8.2 node ci/browser-karten.js
```

`Table::stickyHead()` and `Table::stickyColumn()` (1.107.0) are measured by
`ci/browser-sticky.js` on `ci/browser-sticky-fixture.php`, light and dark: the header cells
at the top of the window, under a fixed GridKit header, at `--gk-table-sticky-top` and at the
top of their box; the first n cells of every row (checkbox, left buttons, first column) at
their measured offsets after scrolling sideways, the line at the edge only while scrolled, the
corner above head and column, the footer cells under their columns. Opacity is a picture:
every cell that does not stick is painted magenta, everything in it too (a row button is
`position: relative`), the box is scrolled — 300px and as far as it goes — and the sticky part
of a screenshot may not hold one magenta pixel: plain, selected, hovered, focused, striped,
inverted, minimal, bordered, the dark rows, the row of an open side sheet. A `setData()` table
sorted, searched and paged, and a server table reloaded through a stubbed `fetch` with the
fragment PHP writes, keep their box, offsets and scroll position; a cell that grows moves the
offsets (ResizeObserver), a table added later is bound by `GK.initContent()`, a `gridkit.js`
added after the page was ready binds them too; Tab reaches the box and the arrow keys scroll
it; at rest a sticky table is its twin without the options, pixel for pixel in light mode; at
390px card mode is its twin cell for cell, `mobile('scroll')` and a list keep sticking; a
`gridkit.js` without the 1.107.0 block renders without an error. Each rule and branch was
taken out in turn and the run turned red. About forty seconds.

```bash
GK_PLAYWRIGHT=/path/to/node_modules/playwright GK_PHP=php8.2 node ci/browser-sticky.js
```

The column menu of `Table::columnToggle()` (1.108.0) hides columns through CSS keyed on its
checkboxes and lets the script remember, place and narrow; `tests/column-toggle.test.php`
holds the markup and the rules, `ci/browser-column-toggle.js` on
`ci/browser-column-toggle-fixture.php` measures what they do. One invariant runs through it:
every row spans as many columns as the header shows. The cases: a column hidden and shown
(header, cells, a footer cell over one column, group headings, a footer cell over several and
the empty state narrowed), `'hidden'` and `'toggle' => false`, the row-link column, a checkbox
and left buttons before the first column, twenty-two columns, the memory across a reload, the
redraw after a sort, a search and an AJAX reload (the stub answers with the server's own
fragment), the keyboard (Enter, the arrows, Home, End, Escape claimed with the focus back,
Tab out), a click elsewhere, the menu under its button and inside the window, the CSV of a
static table and the export address of a server one, the last column that cannot be
unticked, a table handed to `GK.initContent()` and a script added after the load, the
compact card at 390px in light and dark (the next column takes a hidden title), storage that
throws, the script of 1.107 and no script at all. Each rule and branch was taken out in turn
and the run (or `tests/column-toggle`) turned red. About thirty seconds.

```bash
GK_PLAYWRIGHT=/path/to/node_modules/playwright GK_PHP=php8.2 node ci/browser-column-toggle.js
```

`Table::densityToggle()` (1.109.0) is measured by `ci/browser-density.js` on
`ci/browser-density-fixture.php`: thirteen tables (md with a selection and a footer, sm,
lg, xs, a list, a row link, the variants compact, padded and very-compact, groups, scroll
mode, a server table), each with the control and again without it. At Normal a table
measures as its twin cell by cell; each step gives the expected padding per size and
variant, a list row of 52, 64 or 76px, a row link of at least 44px, row buttons of the same
size inside their cells, and the same numbers without `css/themes.css`. Then: a page that
is `konsole` itself (the control starts at Compact, Normal is the default rhythm), a
reload, the key in the namespace of `GK.theme`, a value under the key that is not a step,
a sort, a search and an AJAX reload (a stubbed `fetch`), a table handed to
`GK.initContent()` later, the keyboard, the contrast of the control in light and in both
dark spellings, `localStorage` that throws, 390px (cards, scroll mode, a list, the
control's 44px), no JavaScript, the copy of `js/gridkit.js` without the 1.109.0 block
(`--old-js`) and a script added after the document was ready (`--late`). `GK_CSS` and
`GK_JS` hand the fixture other files: each rule and branch of the block was taken out in
turn that way and the run turned red. About fifteen seconds.

```bash
GK_PLAYWRIGHT=/path/to/node_modules/playwright GK_PHP=php8.2 node ci/browser-density.js
```

Inline editing (`Table::editable()`, 1.110.0) is checked by `ci/browser-inline-edit.js` on
`ci/browser-inline-edit-fixture.php`: six tables (one that posts, one that pages and
searches, one with a selection, one with a row link, one that only fires the event inside a
form, a server table). The POSTs go to `http://gk.test/cell` and `page.route()` answers them —
ok, ok with a stored value, ok with display markup, a refusal with a reason (200 and 422),
500, a body that is not JSON, no network, or held until released — so no server is needed.
It opens cells with the pointer, Enter and F2, closes them with Enter, Escape, Tab and by
leaving, checks the event, its veto and `respondWith()`, the request and its headers, the
rebuild after a sort, a page and a search (row ids and cell attributes as PHP writes them,
attribute for attribute), the layout (no column moves when a field opens), a card at 390px,
both dark spellings and forced colours. Each rule of the script, the stylesheet and
`Table` was taken out in turn and a check turned red. About forty seconds.

```bash
GK_PLAYWRIGHT=/path/to/node_modules/playwright GK_PHP=php8.2 node ci/browser-inline-edit.js
```

Tabs 1.111.0 (`GridKit\Tabs`, `GK.tabs.*`) are checked by `ci/browser-tabs.js` on
`ci/browser-tabs-fixture.php`: rows of `Tabs` and of hand-written markup, served from
`http://gk.test/` by `page.route()`, which also answers the lazy panels — ok, a 500, no
network (then ok, for the retry), a whole page, slow, and another origin that must never be
asked. It checks the address in and out (`#<id>=<key>` beside other parts of the fragment),
Back, nested rows, the memory and a storage that throws, the parameter and `active()`, lazy
panels with `GK.initContent()` inside, `gk:tabchange`/`gk:tabshown`/`gk:tabload` and a
refused switch by click, key, API and address, the JS API on hand-written markup, a row
wider than its box (by its own width, arrows, keyboard, smooth and reduced motion), a modal,
injected markup, 390px, light and both dark spellings, and the page with the script cut at
its 1.111.0 block (`php ci/browser-tabs-fixture.php old`). About twenty seconds.

```bash
GK_PLAYWRIGHT=/path/to/node_modules/playwright GK_PHP=php8.2 node ci/browser-tabs.js
```

The tooltip engine (1.112.0) is checked by `ci/browser-tooltip.js` on
`ci/browser-tooltip-fixture.php`: triggers at the four window edges and corners (flip, shift,
`auto`, the arrow on the trigger), in a box that scrolls (its visible part is the room; a small
scroll moves the bubble, scrolling away closes it) and in a transformed box; the hover delay
(default, 0, 800), the grace on leaving, the pointer resting on the bubble, keyboard focus,
Escape (claimed, so a modal around it stays), blur, one at a time; names and descriptions read
from Chrome's accessibility tree; the toggletip by mouse and keyboard (Enter, Space, Tab into its
link and button, Escape back to the trigger, a click outside); the help icon's 24px and 44px;
Button, a `setData()` table before and after a sort, and Form with their options; a modal;
`GK.initContent()`; a phone with a finger; reduced motion; the contrast of every tone in light
mode and both dark spellings under every theme; and the page without the script, with the copy
of `js/gridkit.js` that has no 1.112.0 block and with the script added late. Each rule of the
block was taken out in turn and a check turned red. About forty seconds.

```bash
GK_PLAYWRIGHT=/path/to/node_modules/playwright GK_PHP=php8.2 node ci/browser-tooltip.js
```

The permission matrix (`PermissionMatrix`, 1.115.0) is driven by `ci/browser-permission-matrix.js`
on `ci/browser-permission-matrix-fixture.php`: a matrix in a form with a locked role, two heirs
of one parent and a two-level chain, `requires` chains, risky rights, a folded group and rows
without a group; one that saves as you go (fetch is stubbed: ok, a refusal with a reason, a
broken answer) and a read-only one. It toggles cells and the bulk toggles of a row, a column, a
group and all roles, checks inheritance (live, not removable, the heir's own grant kept),
`requires` both ways and through the heirs, the filter, folding, the count of changes, Reset,
the form's own reset, `commit()`, the event and its veto, the grid's keys, a phone at 390px, and
the form as posted with and without the script — handed to PHP (`--read`) and read back with
`read()` and `fromPost()`. Then 450 measurements of contrast and shape in the six themes, light
and both dark spellings. Each rule of the script, the stylesheet and `PermissionMatrix` was
taken out in turn and a check turned red. About twenty seconds.

```bash
GK_PLAYWRIGHT=/path/to/node_modules/playwright GK_PHP=php8.2 node ci/browser-permission-matrix.js
```

The tree view (`GridKit\Tree` and `js/tree.js`, 1.116.0) is checked by `ci/browser-tree.js` on
`ci/browser-tree-fixture.php`: a CMS page tree (single selection, links, moving, lazy branches,
a filter), categories with checkboxes inside a form, folders that move without a URL, and a chain
eleven levels deep. The lazy branches (`GET http://gk.test/children?parent=…`, answered with what
`Tree::renderBranch()` wrote into the fixture) and the moves (`POST http://gk.test/move`) are
answered by `page.route()` — ok, 500, `{"ok":false}`, no network, or held until released. It
checks the ARIA tree the script builds, every key of the WAI pattern and type-ahead, what is
remembered across a reload, a branch that loads, fails, is tried again and is empty, single and
multiple selection with tri-state parents and what the form posts, the filter with the ancestors
of a match, drag and drop (before, after, into, first child, refused into its own branch and into
a disabled node), moving by keyboard, the way back after a failed save (and of the moves queued
behind it), the event veto, the API, 390px, contrast in 21 looks and the page without script.
`GK_VERBOSE=1` prints each case as it runs. About a minute.

```bash
GK_PLAYWRIGHT=/path/to/node_modules/playwright GK_PHP=php8.2 node ci/browser-tree.js
```

Line items (`GridKit\LineItems`, 1.117.0) are checked by `ci/browser-line-items.js` on
`ci/browser-line-items-fixture.php`: nine editors (an invoice in a form, three of 60 random
lines — net and line rounding, net and total rounding, gross —, limits, empty, read-only, an
article search, a `Form` field). The article search asks `http://gk.test/articles` and the
forms post to `http://gk.test/…`, both answered by `page.route()`. The point is that the
browser and PHP arrive at the same figures: the totals of every editor against
`LineItems::totals()`, lines typed by hand in every shape against `parse()` of the very POST the
browser builds (`php ci/browser-line-items-fixture.php --parse <editor>` reads it), 300 random
sets against `totals()`, 244 inputs against `parseNumber()`. Then add, Enter, duplicate,
delete and Undo, the order by keyboard and mouse, validation, the article search, an AJAX
Form's errors, read-only, a phone at 390px, the POST without script and contrast in light,
dark and `.gk-dark` with the six themes. `GK_SEED=…` repeats a run. About forty seconds.

```bash
GK_PLAYWRIGHT=/path/to/node_modules/playwright GK_PHP=php8.2 node ci/browser-line-items.js
```

One pager (1.118.0): the pager of `Table::paginate()` is the bar of `GridKit\Pagination`, and
`ci/browser-pager.js` on `ci/browser-pager-fixture.php` measures the two side by side: every
state of a control (a page, the current page, an arrow, an arrow that leads nowhere), the
count, the ellipsis and the bar compared property by property in light, dark and `.gk-dark`,
under no theme and each of the six, at 1280px and 390px; the bar right under the rows and
closing the card; contrast (4.5:1 for the count and the numbers, 3:1 for the current page's box
and the focus ring). Then a `setData()` table paged in the browser — its rows, its count, the
status line, and the pager the script draws compared byte for byte with the one Table.php writes
for that page, in English and German, and with strings that hold `& " ' < >` and no thousands
separator — a search back to page 1, a server table through its AJAX reload (answered from the
fragments Table.php renders), the pager receding while it loads, Pagination's links, Enter and
Space, the focus kept on the control (after a reload once, never taken from another table or
from a field it moved to), the names in Chrome's accessibility tree. And the same page with
js/gridkit.js without its 1.118.0 block, the script of 1.117.0 the PawBot dashboard keeps
(`GK_OLD_JS=<file>` runs it on any other copy): the new markup pages with it, client and
server, first and last included, and the pager it draws after a rebuild is the only one and
wears the same boxes, in a narrow card too. About twenty-five seconds.

```bash
GK_PLAYWRIGHT=/path/to/node_modules/playwright GK_PHP=php8.2 node ci/browser-pager.js
GK_OLD_JS=/path/to/dashboard/js/gridkit.js node ci/browser-pager.js   # the dashboard's own copy, or any other
```

The CSV import (`GridKit\Import` and `js/import.js`, 1.119.0) is checked by `ci/browser-import.js`
on `ci/browser-import-fixture.php`, which runs behind PHP's own server so the forms really post:
the script starts `php -S` on a free port with a temporary directory for the kept files
(`GK_URL=http://127.0.0.1:8140/` uses a server already running the fixture instead). First the
point of the whole thing — the browser reads and checks as the server does: 196 files (written by
hand for each rule, 160 drawn at random from a fixed seed: every separator, quoting, line ending,
encoding — Windows-1252, UTF-8 with and without a byte order mark, UTF-16 LE and BE — and every
value shape) go through `GK.import.parse()` and `GK.import.read()` and must come out as
`Import::parseCsv()` and `result()` say (rows, every message, counts, mapping, notes, meta), and the
panel the script draws must be the one `Import.php` draws, byte for byte. Then the flow with the
script: a file chosen (no request), dropped (the file field takes it) and pasted; the mapping
changed (a field taken from another column, the summary changing in place, the focus kept); the
separator, the encoding (UTF-8 for a Windows-1252 file is said, the file stays) and the header row;
a refused file in words; Cancel; a step 2 drawn by the server taken over unchanged; and Import,
after which the server, reading the posted file and mapping again, hands over the rows the browser
showed. The same without script: upload, the file waiting under a token (0600, gone after the
import and after Cancel), Check again with a field chosen twice, Import; the file carried in the
form with `storage(false)`. Then 390px (no sideways scroll, the preview scrolling in its box),
Chrome's accessibility tree, the keyboard, and contrast in 21 looks — no theme and the six × light,
`data-gk-mode="dark"`, `.gk-dark`: text 4.5:1, edges, bars and symbols 3:1. `GK_SHOTS=<dir>` writes
screenshots (with `GK_ICONFONT=<material-icons.woff2>` for the symbols). About forty seconds.

```bash
GK_PLAYWRIGHT=/path/to/node_modules/playwright GK_PHP=php8.2 node ci/browser-import.js
```

Dates (1.120.0): `GridKit\DateRange` and `GridKit\Calendar` with `js/calendar.js` are checked by
`ci/browser-dates.js` on `ci/browser-dates-fixture.php` — a period picker in a filter bar (its own
GET form, a kept parameter), one as a `Form` field, one with every preset, one in German, a month
of appointments (bars across days, two busy days, an event without a link, week numbers, a day
link), the same month in a 380px column and an empty one. Every page is the fixture, served from
`http://gk.test/` by `page.route()`, so a month link loads it again in the same origin and the focus
can follow; the forms post to `http://gk.test/…`, and what they send is read back by
`DateRange::fromRequest()` (`php ci/browser-dates-fixture.php --read '<query>' --name=<name>`). It
checks that the browser counts all 240 preset ranges and writes all 142 periods in words exactly as
PHP does (`--parity`: the edges of months, quarters and years, leap years, clock changes, the years 1
and 9999, English and German); a preset filling the dates, typing switching to Custom, each date
bounding the other, a filter sending a preset at once and typed dates on Apply, `name[shown]` left
out with the script and decisive without it; the grid roles and the one tab stop, every key (arrows,
Home/End, PageUp/PageDown and Shift, Enter, Space, Tab into a day and out, Escape), a month loaded
past the edge with the focus on the same day, the veto of `gk:calendarnav` and `gk:calendarselect`,
"+n more" in the window and one at a time, the API, a bar straight and joined across its week, the
names in Chrome's accessibility tree, the agenda in a narrow column and at 390px with nothing
sideways, the page without script, and contrast in 21 looks (plain and six themes × light,
`data-gk-mode="dark"` and `.gk-dark`). About ten seconds.

```bash
GK_PLAYWRIGHT=/path/to/node_modules/playwright GK_PHP=php8.2 node ci/browser-dates.js
GK_SHOTS=/some/dir node ci/browser-dates.js   # screenshots: the calendar light and dark, the filter, 390px
```

Charts (`GridKit\Chart` and `js/chart.js`, 1.121.0) are checked by `ci/browser-chart.js` on
`ci/browser-chart-fixture.php`. The script draws a chart again — at the width of its box, after
`GK.chart.update()`, after a series is switched off — with the code of `src/Chart.php` ported line
by line, so the first question is whether the two agree: `GK.chart.html(config)` against
`Chart::html()` for every chart of the page and 400 random ones (every type, gaps, zeros,
negative values, hidden series, the three formats, both locales, odd widths and heights, `& " ' <
>` and emoji in the words), byte for byte, in English and German; and the SVG on the page after
fitting to 480 and 358px, after an update with new values, another type, a donut of one part and
hostile strings, and after a click in the legend, against the `<template>` with what Chart.php
writes for that state. Then the look in light, `[data-gk-mode="dark"]` and `.gk-dark`, without a
theme and in each of the six: every series colour 3:1 against the card, the page, a hover row and
the tooltip, the axis, the legend, a switched-off name, the tooltip's words, the table and the
link 4.5:1, the focus ring 3:1. At 390px: no page scrolls sideways, every chart drawn at its box's
width with 12px text, the legend wrapping, the table scrolling in its box; without the script the
server's chart (an image with a title and a summary, the table, no switches) keeps 560px in a
narrow box and scrolls inside it. The keys (one tab stop, the arrows, Home, End, Enter and Space
as `gk:chartselect`, Escape, the live region, the legend's switches with Space, a donut's parts),
the pointer (the tooltip of every series, the crosshair on the point, the way to the tooltip,
leaving, a click on a bar, a donut's slice, markup in a label as text), reduced motion, print,
forced colours, and the script without `js/gridkit.js`. Each rule and branch taken out in turn
turned it (or `tests/chart`) red. About twenty seconds; `GK_SHOTS=<dir>` saves the pages.

```bash
GK_PLAYWRIGHT=/path/to/node_modules/playwright GK_PHP=php8.2 node ci/browser-chart.js
GK_SHOTS=/some/dir node ci/browser-chart.js
```

The command palette (`GridKit\CommandPalette` and `js/command-palette.js`, 1.122.0) is checked by
`ci/browser-palette.js` on `ci/browser-palette-fixture.php`: an admin shell with a sidebar
(groups, a badge, a submenu, the current page), the trigger in the header, links, commands, an
umlaut and a hostile label, two search URLs — `http://gk.test/search` on the page's origin and
`http://other.test/search` on another —, a field, a rich-text editor, `GK.search` on Ctrl+K as
well and a modal. `page.route()` serves the page as `http://gk.test/fixture.html` and answers the
records: found, held until released, failed, empty, a flat list, hostile. It checks the key on
Windows and Linux (Ctrl+K; Meta+K and Ctrl+Shift+K do nothing) and on a Mac (Cmd+K, `⌘K` on the
trigger, Cmd+Enter), `/`, the trigger, `GK.palette.open()`, that the palette owns its key (the
global search stays shut) but leaves it to a rich-text editor; what is offered with no query
(the sidebar's groups, labels, hints, symbols, the limit per group), typing and the ranking
(start, start of a word, inside a word, letters in order word by word), umlauts and ß both ways,
keywords, the hint, the marks; the keys of the listbox (round at the ends, Home, End, Page Up and
Down), Tab kept inside; Enter on a command, a link and a sidebar entry (a click on that link),
Ctrl+Enter and Ctrl+click into a new tab, the pointer, the veil; the veto of `gk:paletteselect`;
the records — minimum length, debounce, the headers, the order, a slow answer overtaken and
aborted, closing that aborts, a failure and Try again, no records, a hostile answer, the other
origin never asked; the recent list (newest first, its limit, a careful read of what is stored,
`GK.theme`'s scope, storage that throws); Escape over a modal; 390px as a full-screen sheet
without a sideways scroll; contrast in 18 looks; the page without script, with a fallback link,
and without a palette from the server (`GK.palette.open()` builds one; `register()`). Each new
rule, branch and option of the script, the stylesheet and `CommandPalette.php` was taken out in
turn (a bounded sample) and a check turned red. About twenty-five seconds.

```bash
GK_PLAYWRIGHT=/path/to/node_modules/playwright GK_PHP=php8.2 node ci/browser-palette.js
GK_SHOTS=/some/dir GK_ICONFONT=/path/MaterialIcons.woff2 node ci/browser-palette.js   # with pictures
```

The filter builder (`GridKit\FilterBuilder` and `js/filter-builder.js`, 1.123.0) is checked by
`ci/browser-filter-builder.js` on `ci/browser-filter-builder-fixture.php`: an invoice list with a
builder of every field type, three saved views and Save view to `http://gk.test/save`, a small
builder that keeps its views in the browser, and the rows that pass (`matches()`). `page.route()`
runs the fixture with the query string of every request, so the form without script really goes
round the server: “Condition” and “Group” as submit buttons, a changed field and its operators,
an empty value said in words, Enter in a value applying (the hidden default button, not the first
remove button), duplicate, remove, Reset, a view as a link. With the script: the group the script
draws is the server's attribute for attribute, and for ten states — odd and hostile ones too — the
script's checked tree, summary and markup are PHP's (`window.GK_TEST_PARITY`); rows added,
duplicated and removed without a request and the focus where it belongs; the operators following
the field and the value fields the operator; a wrong value marked when the field is left and
unmarked as it is typed right; Apply with an incomplete condition stopped, `gk:filterchange`
vetoed, the compact JSON sent and read back; back from the next page; `GK.filterBuilder`; Save view
posted, asked for a name, and kept in `localStorage` across a reload; the keyboard alone; hostile
labels; German numbers; 390px without a sideways scroll, with 44px targets and the buttons on the
line of the joining word, and 320px three levels deep; contrast in 18 looks. Each new rule and branch
of `FilterBuilder.php`, the script and the stylesheet was taken out in turn (a bounded sample of 60)
and a check turned red. About twenty seconds.

```bash
GK_PLAYWRIGHT=/path/to/node_modules/playwright GK_PHP=php8.2 node ci/browser-filter-builder.js
GK_SHOTS=/some/dir GK_ICONFONT=/path/MaterialIcons.woff2 node ci/browser-filter-builder.js   # with pictures
```

The kanban board (`GridKit\Kanban` and `js/kanban.js`, 1.124.0) is checked by `ci/browser-kanban.js`
on `ci/browser-kanban-fixture.php`: a ticket board that saves to `http://gk.test/move` (headers,
hidden fields, a limit of two in `'block'`, a locked card, links, every card option), a compact board
that moves without a URL with a limit that only warns, and a board of eight columns wider than any
screen; `page.route()` answers the moves — saved, held until released, 500 with a message,
`{"ok":false}`, no network. It checks the handles and what a screen reader finds (the region, a
heading and a counted list per column); the mouse by the handle and by the body of a card (the copy
under the pointer, the drop line, the framed column, all gone after the release, a link and a small
move are no drag); the request (`{id, column, index}`, the headers) and both events with their
detail; the counts, the over-limit mark and its words, a full column refused by a drop, by the arrow
keys and in every "Move to" list; Escape during a drag; the keyboard (Space and Enter lift, Up, Down,
Left, Right, Home, End, the edges, Escape, leaving the handle, the focus kept) and every spoken
sentence; the veto of `gk:kanbanmove` (keyboard and drop); the way back after a 500, a refusal while a
second move waits (both back, the second never sent, an event for each), no network, the toast; the
"Move to" form sent as JSON; the board scrolling sideways under a card held at its edge; `GK.kanban`;
a finger on the handle (it drags) and on the body (it does not); 44px targets on a touch screen;
390px as lanes that snap, and no sideways page scroll from 320 to 1600px; contrast in 21 looks; the
page without script and with an old `gridkit.js` that has no `kanban.js` (the form posts, `id`,
`_token`, `column`). Each new rule and branch of the script, the stylesheet and `Kanban.php` was
taken out in turn (a bounded sample) and a check turned red. About twenty-five seconds.

```bash
GK_PLAYWRIGHT=/path/to/node_modules/playwright GK_PHP=php8.2 node ci/browser-kanban.js
GK_SHOTS=/some/dir node ci/browser-kanban.js
```

The file manager (`GridKit\FileManager` and `js/file-manager.js`, 1.125.0) is checked by
`ci/browser-files.js` on `ci/browser-files-fixture.php`: a media library with two folders, eight files
(one with a hostile name, two in a folder), every action, an upload limited to 64 KB and four files, two
at a time, a token and a hidden field; and a receipt drawer with single selection in a list under its own
parameters. `page.route()` serves the page as `http://gk.test/fixture.html` — drawn by PHP again for
every query, so the links and the toolbar of the page without script are answered by the server — and
answers `POST /upload` (taken, held until released, refused, a 500 once, an answer that is no JSON, a
hostile name) and `POST /act` (done or refused). It checks the toolbar acting at once and the state in
the address (defaults left out, a second manager under its own name); the selection by keyboard (arrows,
down by a row of tiles, Home, End, Space, Shift+arrow, Ctrl+A, Escape, Delete with a confirmation) and by
pointer (the picture, Shift+click, "Select all" with its mixed state); the details (Enter, a click on a
name, dimensions measured, Copy URL, rename with its fields and headers, the focus given back); delete
confirmed and refused, move, a new folder, an action left to the page and a veto; the upload — one
request per file with the token, the hidden field and the folder, the new file drawn like the server's,
symbol and word for each state, a full bar when done, the count in the live region, object URLs given
back, the checks in the browser (type, size, empty, SVG for `image/*`, count), two at a time, the percent
while sending, cancel while sending and while waiting, retry after a cancel and after a 500, no retry after
a refusal, an answer that is no answer, a hostile name; drop and paste (not into the search field), a veto
of `gk:filesupload`, the API; 390px (two columns, 16px fields, every target 44px, the details as a sheet
with Tab kept inside, no sideways scroll in grid, list and queue); contrast of 19 parts in 18 looks; and
the page without script (the forms post: the files at once, the toolbar as GET, a folder and a name as
links drawn by the server, Delete with its form). Each new rule, branch and option of the script, the
stylesheet and `FileManager.php` was taken out in turn (a bounded sample) and a check turned red. About
ninety seconds.

```bash
GK_PLAYWRIGHT=/path/to/node_modules/playwright GK_PHP=php8.2 node ci/browser-files.js
GK_SHOTS=/some/dir GK_ICONFONT=/path/MaterialIcons.woff2 node ci/browser-files.js   # with pictures
```

## Colour variables that resolve across two files

A theme lives in `css/themes.css`, the roles and their older aliases in
`css/gridkit.css`. Whether `--gk-text-muted` still means what
`--gk-on-surface-variant` means is therefore a question no string comparison can
answer — the chain only resolves in a browser, with both files loaded.
`ci/farben.js` resolves every pair across six themes, in light mode and in both
spellings of dark mode, and reports the WCAG ratio of normal and muted text
against the ground:

```bash
node ci/farben.js                      # needs the "playwright" package
GK_PLAYWRIGHT=/path/to/node_modules/playwright node ci/farben.js
```

It found the fault 1.89.0 fixes: in dark mode the aliases were literals, and
`themes.css` had moved the roles underneath them. Four pairs are asserted; the
four surface aliases stand under `ZURUECKGESTELLT` — measured and reported, but
not failed, until the surface ladder in `themes.css` is decided. Run it when
touching a colour variable in either file.

Like `ci/browser.js` and `ci/parity.php` it stays outside the suite.

## Automatic delivery

`gridkit-deploy.sh` brings `main` to the live checkout on the server — the document root of
gridkit.at — without anyone logging in. pawbot's cron starts it every minute. It is quiet while
nothing is new; for a new commit on `origin/main` it runs `php tests/run.php` on that commit in a
worktree next to the live checkout (`/home/pawbot/projects/gridkit-deploy-check`, never in the
website itself), and only when the suite is green it moves the website forward (`--ff-only`)
and tags `v<VERSION>` if that tag does not exist yet. Packagist takes the tag from there.

- A red commit is not delivered, tried once (not every minute) and reported in the log; the
  next commit is tried again. The output of the suite is in `~/.gridkit-deploy/tests.log`.
- A file changed by hand in the website stops the delivery instead of being overwritten.
- An existing tag is never moved: Packagist keeps the first commit a version pointed at
  (1.110.3, 2026-11-03). When one merge carries several versions, only the last is tagged;
  earlier ones can be tagged by hand.
- No workflow file and no GitHub setting is involved (see above: the push token cannot carry
  workflow files). Merging to `main` IS the release.
- The server's command-line PHP needs `dom` and `mbstring` (the suite reads HTML with
  `DOMDocument`): `apt install php8.2-xml php8.2-mbstring`. Without them the script says so and
  delivers nothing (server6, 2026-11-04: `Class "DOMDocument" not found`).
- Started by hand it says what it does and why it does nothing, and tries a red commit once more;
  from cron and systemd it is quiet unless it delivers or fails.

**Switching it on**, once, as root on the server:

```bash
mkdir -p /home/pawbot/bin
curl -fsSL https://raw.githubusercontent.com/mmollay/gridkit/main/ci/gridkit-deploy.sh -o /home/pawbot/bin/gridkit-deploy.sh
chown -R pawbot:pawbot /home/pawbot/bin && chmod 750 /home/pawbot/bin/gridkit-deploy.sh
sudo -u pawbot /home/pawbot/bin/gridkit-deploy.sh; echo "exit $?"
(sudo -u pawbot crontab -l 2>/dev/null; echo '* * * * * /home/pawbot/bin/gridkit-deploy.sh >> /home/pawbot/gridkit-deploy.log 2>&1') | sudo -u pawbot crontab -
```

The copy in `/home/pawbot/bin` is deliberate: the script that runs on the server does not change
with what is merged. After a change to `ci/gridkit-deploy.sh`, copy it again. Its behaviour is
held by `tests/deploy.test.php`, which runs it against a stand-in origin and website.

### At once, by GitHub webhook

Cron asks every minute whether something is new. The webhook is told: GitHub posts each push to
`https://gridkit.at/hooks/github.php`. That page checks the signature (HMAC-SHA256 with the
webhook's secret) and, for a push to `main`, writes one line to `/var/lib/gridkit-deploy/trigger`
— nothing else: no git, no shell, nothing runs as the web server's user. A systemd path unit
watches that file and starts the same `gridkit-deploy.sh` as pawbot (`ci/gridkit-deploy.path`,
`ci/gridkit-deploy.service`). The logic is `ci/deploy-hook.php` (behind the 403 of `ci/`), held
by `tests/deploy-hook.test.php`. No workflow file and no token scope is involved: a webhook is a
setting of the repository on GitHub.

On the server, as root (`WEB` is the user PHP runs as for gridkit.at — `www-data`, or the site
user of the panel; `ps -eo user,args | grep -E 'php-fpm: pool|apache2' | sort -u` shows it):

```bash
WEB=www-data
install -d -m 750 -o root -g "$WEB" /etc/gridkit-deploy
openssl rand -hex 32 > /etc/gridkit-deploy/hook-secret
chgrp "$WEB" /etc/gridkit-deploy/hook-secret && chmod 640 /etc/gridkit-deploy/hook-secret
install -d -m 770 -o pawbot -g "$WEB" /var/lib/gridkit-deploy
cp /home/pawbot/projects/gridkit/ci/gridkit-deploy.path /home/pawbot/projects/gridkit/ci/gridkit-deploy.service /etc/systemd/system/
systemctl daemon-reload && systemctl enable --now gridkit-deploy.path
cat /etc/gridkit-deploy/hook-secret     # for GitHub, next step
```

On GitHub: Settings → Webhooks → Add webhook — Payload URL
`https://gridkit.at/hooks/github.php`, Content type `application/json`, Secret: the line printed
above, "Just the push event", Active. GitHub sends a ping at once; Recent Deliveries shows
`200 pong`. A push to `main` answers `202 queued`, and the log says `delivered` seconds later.
Keep the cron line as a safety net, hourly: `0 * * * *` instead of `* * * * *` — a delivery that
was missed (server down, a webhook lost) then still arrives.

## PawBot sync

The PawBot dashboard keeps its own `js/gridkit.js` (PawBot extensions), and its sync never
overwrites it. Since 1.107.0 every release adds its script as ONE block at the end of
`js/gridkit.js`, headed `// === <version> — <name> ===`, so the copy is brought up to date by
adding the blocks it lacks:

```bash
php ci/pawbot-sync.php /path/to/dashboard/js/gridkit.js            # report: ok, MISSING, DIFFERS, OWN, BY HAND
php ci/pawbot-sync.php /path/to/dashboard/js/gridkit.js --apply    # append the missing blocks (backup first)
php ci/pawbot-sync.php /path/to/dashboard/js/gridkit.js --update   # also replace blocks a later release changed
```

- A block counts as there when its text stands in the copy as whole lines; the dashboard's own
  code may follow it or sit between blocks, and stays where it is.
- `--update` replaces a block only where the copy holds one of GridKit's earlier texts of it, taken
  from the git history of `js/gridkit.js` (a shallow clone knows little of it: `git fetch
  --unshallow`). Any other text is the dashboard's own (`OWN`) and is left to a person.
- Two changes since 1.107.0 sit inside the old code, not in blocks (1.110.1: the header cell over
  left row buttons in `renderStatic`; 1.113.0: `_tOr("field_required", …)` in the rich-text
  check). They are reported as `BY HAND` with what to change, never patched.
- Files of their own (`js/tree.js`, `js/sortierbar.js`, and those of later components) are copied
  as they are. `tests/pawbot-sync.test.php` holds the rules above.
