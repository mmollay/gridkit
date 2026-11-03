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
