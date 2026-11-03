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
