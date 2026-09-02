# Changelog — GridKit

All notable changes to this project are documented here.
Format based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

> Entries up to 1.27.3 are in German — they are the historical record and are
> left as written. From 1.28.0 onwards the changelog is in English.

---
## [1.67.0] - 2026-09-02

### Added — alignment that survives being e-mailed

Four buttons on a selected picture: **left** and **right** (the text flows
beside it), **own line, centred**, and **clear**. They write both of these onto
the tag:

```html
<img style="float:left;margin:0 16px 8px 0;width:40%" align="left" src="…">
```

`float` for every modern client, `align` for Outlook, which still ignores
`float` after twenty years. Belt and braces, because there is no stylesheet in
a mail to fall back on. A size already set with the resize handles is carried
over — clicking *left* no longer throws it away.

### Why not CKEditor's own ImageStyle

Measured against the bundled build before choosing:

| approach | output |
|---|---|
| `ImageStyle` | `<img class="image-style-align-left">` |
| inline `float` without GHS | `style="width:40%"` — **float and margin dropped** |
| inline `float` **with** GHS | `style="float:left;margin:0 16px 8px 0;width:40%"` |

`ImageStyle` styles from a stylesheet, so the class does nothing in a mail. And
CKEditor strips `float`/`margin` from `style` unless `GeneralHtmlSupport` allows
them — which is why it is now part of the picture plugin set, scoped to `img`.

### Added

`uploadHeaders` on a `richtext` field: extra headers for the upload request, in
practice a CSRF token. CKEditor sends the file as a plain multipart POST, so
without this there is no way to carry one, and an upload endpoint any other
site can post to on a logged-in user's behalf is a hole.

Four new language keys (`image.align_left` and siblings), German and English.

---
## [1.66.0] - 2026-09-02

### Added — pictures in `richtext`, shaped so they survive being e-mailed

`['type' => 'richtext', 'upload' => '/your/endpoint']` turns on an upload
button, drag & drop, paste-from-clipboard, size handles and alt text. Without
`upload` nothing changes — an upload button with nowhere to put the file is
worse than no button.

The endpoint takes a POST with the file under `upload` and answers
`{"url":"https://…"}` or `{"error":{"message":"…"}}`. That is CKEditor's
SimpleUploadAdapter contract. **Return an absolute URL** — a relative one looks
right on the page and breaks the moment the content is sent as mail.

### Why no `<figure>`, and why percent

`ImageBlock` and `ImageCaption` are deliberately left out. They wrap every
picture in `<figure class="image">` and style it from a stylesheet — correct on
a web page, wrong in an e-mail, where there is no stylesheet to load and
Outlook does not lay out `<figure>` reliably. Measured against the bundled
CKEditor before choosing:

| plugins | output |
|---|---|
| `ImageInline` | `<p>text <img src="…"> text</p>` |
| `ImageBlock` + `ImageCaption` | `<figure class="image">…</figure>` |
| `ImageResize` | writes `style="width:42%"` onto the tag |

So the shipped set is `ImageInline` + `ImageResize` + toolbar + alt text +
upload: a plain `<img>` in the paragraph, carrying its own size as an inline
style. Sizes are in percent rather than pixels because the same mail is read on
a phone and on a desktop, and a fixed pixel width overflows one to fit the
other.

### Fixed

`GRIDKIT_SKILL.md` was still stamped 1.65.0 after the 1.65.1 release. The
suite caught it here.

---
## [1.65.1] - 2026-08-31

### Fixed — a page that forgets `Theme::attributes()` no longer loses every
input outline and every button fill

The base colour tokens have always lived only inside `.gk-root` or
`[data-gk-theme]` — never on bare `:root`. A page that sets neither gets every
custom property as an empty string; a bare `var(--gk-outline-variant)` inside
a shorthand (`border: 1px solid var(--gk-outline-variant)`) then invalidates
the whole shorthand, not just the missing value, so the browser drops border
**and** colour together. Silently — no console error names the missing
variable.

Found on `panel.ssi.at/login`, broken for six months: a rename on 01.03.2026
stripped `.gk-root` off the login `<form>`, mistaking it for GridKit's old,
unrelated grid-system class of the same name. Nobody saw it — a 30-day
remember-me cookie means almost nobody visits `/login`.

`:root` now joins `.gk-root, [data-gk-theme]` as a third path to the same
default (indigo, light) values — a safety net, not a replacement for calling
`Theme::attributes()`. A page that DOES set `[data-gk-theme="ocean"]` still
gets ocean: `themes.css` loads after this file and wins by cascade order, not
by `:root` losing on specificity.

2331 assertions pass.

---
## [1.65.0] - 2026-08-30

### Added — density as a variant, not a decision made for everyone

A table's rhythm is now three tokens instead of numbers scattered through the
rules: `--gk-table-pad-y`, `--gk-table-pad-x`, `--gk-label-radius`. **The
defaults are exactly what tables looked like before, so nothing moves** until
something redefines them.

Two variants do, via `data-gk-density` on `<body>` — set it with
`Theme::density('konsole')`, or leave it alone for the default:

| | rows | status pills |
|---|---|---|
| default | 12px / 16px | fully round |
| `konsole` | 8px / 12px | 4px radius |
| `weit` | 16px / 20px | fully round |

`konsole` fits roughly a third more rows on the same screen — what someone who
works in the tool all day wants, and exactly wrong for someone who opens one
page a week. That is a decision per installation, which is why it is a variant
and not the new default.

It names no colour, so it composes with any theme and with either mode. An
unknown value is ignored rather than stamped onto the page.

---
## [1.64.0] - 2026-08-30

### Changed — the project lives at gridkit.at

`gridkit.ssi.at` read as a subfolder of a company rather than a thing with its
own identity, which matters for a library someone is deciding whether to depend
on. Every reference moved: `composer.json`, the README, `llms.txt`, the
sitemap, `robots.txt`, `Layout`, the skill document and the announcement drafts.

`CHANGELOG.md` is deliberately untouched. It is a record of what was true when
it was written, and rewriting history to match the present is how a changelog
stops being worth reading.

The old name keeps working — a permanent redirect, path for path, not a
switch-off. It is in two emails already sent, in the Packagist metadata of
every release up to 1.62, and in whatever else has quietly linked it. R=301
also transfers what authority the subdomain had rather than starting the new
name at zero.

Timing: this is the cheapest it will ever be. A search for the project today
returns Packagist and not the site, so there is almost nothing to migrate.

### Added — the demo can be found and shared

It had a description and nothing else. No canonical, so `?lang=de`, `?lang=en`
and every cache-busting query a visitor arrived with counted as separate pages
competing with one another. No Open Graph, so sharing the link — the one page
that actually shows what GridKit does — produced a bare URL with no title and
no picture.

It now carries a canonical that follows the active language, `hreflang`
alternates for both plus `x-default`, a full Open Graph and Twitter card, and
`robots`. The title lost its version number: a title that changes every release
gives a search engine a moving target for one URL.

The landing page gained `twitter:image`. Twitter falls back to `og:image` when
it is absent, but not every client does, and those showed a card with no
picture.

---
## [1.63.0] - 2026-08-29

### Added — `ci/parity.php`, a harness for the bug this project keeps having

Three releases in a row fixed the same shape of defect: the table is rendered
by PHP and again by JavaScript, and the two had drifted. Hardcoded `de-DE` in
1.55.0, missing `scope` and `aria-sort` in 1.59.0, an unwrapped confirmation in
1.62.1. None was visible in the source of either side. Each appears only when
you render once, change something, and render again.

So the harness does that. It renders six tables server-side — plain, sortable,
formatted, with row buttons, selectable, filtered — captures each one's markup
**before gridkit.js loads**, forces a client rebuild, and compares the element
and attribute vocabulary of the two.

It is not part of `php tests/run.php`. The suite runs on plain PHP with no
dependencies, and requiring a browser would break that promise for everyone who
clones the repo. Run it when touching either renderer.

**Two mistakes in the harness itself, both instructive.** The first version
compared position by position, so one element added by the rebuild shifted
everything after it and reported a whole table as divergent. The second — worse
— snapshotted inside the comparison, which runs after load, by which time the
library has already re-rendered every `setData()` table. It compared the client
against itself and reported perfect agreement while a button in the DOM carried
a raw, unwrapped `onclick`. Both were caught by deliberately reintroducing a
known bug and checking the harness noticed. It did not, until fixed.

### Fixed — the two pagers announced themselves differently

Found by the harness on its first honest run. The server named its controls
from `pagination.*` and the client from a separate `js.*` pair, so a screen
reader heard "Previous" and "Page 1 of 3" on load, then "Previous page" and
"Page 1" after the first sort. `Lang::jsConfig()` now exports `pagination.*`
alongside `js.*`, `action.*` and `format.*`, and the client reads it. The
divergence count across the six cases fell from 121 to 61, and what remains is
documented in the harness as benign.

### Fixed — `immutable` caching on unstamped assets

Yesterday's cache fix gave every CSS and JS file a year of `immutable`. That is
safe for `Layout::asset()`, which appends `?v=<mtime>` so a changed file is a
changed URL — and unsafe for anything linking `/js/gridkit.js` bare, which
would then keep last year's script through every deployment. It bit this
project's own harness within the hour: the fixture linked the script unstamped,
was served a copy from before that morning's fixes, and reported a regression
that had been fixed hours earlier. `immutable` now applies only to a URL that
carries a `v=` parameter; everything else gets an hour.

---
## [1.62.1] - 2026-08-29

### Fixed — a delete that stopped asking

A row button given both `onclick` and `confirm` asked before acting on the
server-rendered page, and from the first sort, filter or page change onwards
acted without asking. Anyone who trusted the prompt lost a record to a
mis-click.

`Table::renderButtons` wraps the handler in `GK.confirm(...)`, with a comment
explaining why: an inline handler runs before any delegated listener could stop
it, so the `data-gk-confirm` attribute the delegated path reads cannot hold it
back. The client renderer draws the same button and set the handler raw.

That is the third instance of one defect: the same output rendered in two
places, drifting apart. The hardcoded `de-DE` in 1.55.0, the table header
attributes in 1.59.0, this. Each was invisible in the source of either side —
they only appear when you render one, change something, and render again.

The test pins both sides, so a change to either is caught. Verified by
reverting the fix and watching it fail.

---
## [1.62.0] - 2026-08-29

Two review passes, one over the PHP and one over the rendered pages. Both found
real defects; the visual pass found them by measuring in a browser, where
reading the CSS would not have.

### Fixed — the active page button was invisible

The client-side pager ADDED `gk-btn-filled gk-btn-primary` on top of
`gk-btn-text gk-btn-neutral` rather than replacing it. Two variants on one
button fight in the cascade: the text variant, declared later, won the colour;
the filled variant won the background. Slate on grey, **1.19:1**, with square
corners where the server renders a pill. It now emits one variant, matching
what `Pagination` sends: **9.56:1**.

### Fixed — a table header that outranked its own inverted variant

`.gk-table thead th` has specificity 0,1,2 and `.gk-table-inverted th` has
0,1,1, so the light-mode text colour won while the dark background still
applied: **2.36:1**. Now **6.96:1**.

### Fixed — fill colours used as text

`.gk-bool-yes` drew its tick in `--gk-success` at **2.54:1** and `.gk-required`
its asterisk in `--gk-danger` at **3.67:1**. Both now use the `-text` variant
the rest of the library already uses for this, reaching 5.87:1 and 5.67:1.

### Fixed — white on primary, in dark mode, in every theme

Four inline styles in the demo paired `background: var(--gk-primary)` with a
hardcoded `color: #fff`. In dark mode the primary flips light and the pairing
gives **1.78–1.97:1** — in all six themes. `var(--gk-on-primary)` is identical
to white in light mode, so nothing changes there, and dark mode goes to
7.57–7.86:1.

### Fixed — header actions painted over the avatar

The mobile rule shrank `.gk-header-right` but not its children, so at 320px a
33px box held 63px of buttons and overlapped the user menu by 17px. It scrolls
now instead of colliding.

### Fixed — the landing page's terminal demo never ran

`runDemo()` read the implicit global `event`, which is undefined when an
IntersectionObserver calls it. It threw before printing its first line, and the
`observer.disconnect()` after it never ran. Zero lines became thirteen, and the
console is clean.

### Fixed — muted text on the landing page at 2.45:1

`--gk-text-muted: #94a3b8` in six places on white. Now `#64748b`, which is what
the same palette already uses in dark mode. `.skill-desc` was worse at
**1.93:1** — a light-mode token used inside the dark agent section.

### Fixed — two horizontal overflows at 320px

`minmax(280px, 1fr)` forces a track wider than its container; a bare
`<table class="gk-table">` had no scrolling ancestor. Nine and sixty pixels of
body scroll respectively, both gone.

### A note on measuring

The first contrast sweep reported 61 failures that were not real: the numbers
were read mid-transition, with `oklab()` intermediates. Disabling transitions
was required to get truthful values. The same trap caught the verification of
this changelog — `getComputedStyle` returns `oklch()` for these themes, which a
regex-based luminance parser silently mangles. Resolving the colour through a
canvas pixel is the reliable way.

---
## [1.61.0] - 2026-08-29

### Added — the demo shows the code that produced each example

Fomantic UI, Bootstrap and Tailwind UI all put the source under the example.
For GridKit it earns more than it does for them, because the claim being made
is "fifteen lines of PHP and you have this table" — and until the reader can
see the fifteen lines, that is something to take on faith.

`showcase()` wraps an example in a closure, then reads the closure's body back
out of the file using the line numbers PHP itself reports for it. What a reader
sees is the code that just ran, by construction. The `use (...)` clause is
dropped — it is plumbing for the demo page, not part of what anyone would
write — and the body is dedented to column zero.

This replaces the pattern the demo had been using: hand-written blocks beside
the examples, like `$table->size('sm');  // compact`. Those are a second copy
of the truth, and the copy is the one that rots. Two examples are converted so
far — the full-featured table and the 16-column form — because those are the
two where the claim needs the evidence. A list of avatar sizes does not.

The disclosure is a `<details>`: keyboard-operable and announced as an
expandable section with no ARIA of its own, which a div with a click handler is
not. The copy button takes its colours from the theme tokens; the first version
was styled white-on-dark and turned out invisible, because the demo's code
blocks render light in light mode.

### Fixed — the site told every browser to cache it for a month

Something server-wide on this host sends `Cache-Control: max-age=2592000` on
everything, HTML included — it is in no active Apache config that could be
found, and it applies to the other sites on the machine too. On a site that
changes daily the effect is that a returning visitor sees the copy they first
loaded, for thirty days. It is why the landing page still showed v1.54.0 in a
browser while the server had 1.60.1 on disk, and why every check during this
work needed a cache-busting query to tell the truth.

`.htaccess` now overrides it for GridKit only: HTML revalidates on every
request, while assets keep a year — every asset URL already carries `?v=<mtime>`,
so a changed file is a changed URL.

---
## [1.60.1] - 2026-08-29

### Fixed — the status dot was a crescent

`.gk-avatar` carried `border-radius: 50%` together with `overflow: hidden`, and
`.gk-avatar-status` is absolutely positioned at `bottom: 0; right: 0` — the
corner of the square the avatar occupies, which on a circle lies outside the
shape. The round clip sliced the badge into a crescent: a small leaf stuck to
the avatar rather than a status dot.

The clip only ever existed to round a photograph, so the photograph now rounds
itself (`border-radius: inherit` on `.gk-avatar img`) and the avatar no longer
clips anything. The badge renders whole, in all four states.

Checked while there and deliberately not changed: the stacked avatar group
looks sliced at a glance, but that is the overlap doing its job — 40px circles
at `-8px` with a white ring, each covering the edge of the next.

---
## [1.60.0] - 2026-08-28

Sidebar and header. The worst of the three rounds, because what it found was
not a degraded experience — it was a control that could not be operated at all.

### Fixed — the user menu could not be opened without a mouse

The header's user menu, the one containing **Sign out**, is a `div` with
`role="button"` and `tabindex="0"`. Only `click` was handled, and a `div` does
not synthesise a click from Enter or Space the way a real button does. So it
took focus, announced itself as a button, and then did nothing. There was no
way to reach Sign out from the keyboard.

`aria-expanded` made it worse rather than better: it was written once as
`"false"` and never updated, so it stated the opposite of the truth for as long
as the menu was open. An attribute that lies is worse than one that is missing.

Enter and Space now open it, `aria-expanded` follows the actual state, Escape
closes it and returns focus to the trigger, and a click on an item inside the
open menu no longer counts as a toggle — which had closed and immediately
reopened it.

### Fixed — the sidebar never said which page you were on

The active item carried an `active` class and nothing else. On screen that is a
colour; to a screen reader every page presented the same undifferentiated list
of links. It carries `aria-current="page"` now.

### Fixed — two navigations with the same name

The sidebar's `<nav>` was unnamed, and since 1.59.0 the table's pager is a
`<nav>` too. A screen reader listing landmarks read "navigation, navigation".
Both are named now.

### Fixed — a badge that was a number with no noun

`Invoices 3` — three of what? The eye reads the count from context. A screen
reader gets a bare digit stuck to the end of a link name. A visually hidden
word after the number turns it back into a sentence.

### Checked and found sound

The theme swatches already carried `aria-pressed` and proper labels. The header
is a real `<header>` with an `<h1>`, and its menu toggle was correctly named
and already reported `aria-expanded`. Sidebar icons were already `aria-hidden`.
Not everything was broken.

---
## [1.59.0] - 2026-08-28

The table, audited. And a pattern worth naming: the client renderer is a second
implementation of the server's markup, and it had drifted in every place it was
checked.

### Fixed — the table never said anything had changed

Sorting, filtering and paging replace the rows in place. On screen that is
self-evident. To a screen reader the table silently became different data: no
way to tell whether the filter had worked, how many rows matched, or which page
this now was. Each table now renders a visually hidden `role="status"` region
and the client writes into it after every rebuild — "12 entries, page 2 of 2",
or "No matches".

### Fixed — headers that did not say they were headers

No `<th>` carried `scope="col"`. On a table with a checkbox column and an
action column, that leaves a cell associated with the wrong header or with
none. Every header now has it, including the two empty ones, which also gained
a hidden label rather than staying nameless.

### Fixed — a pagination button named after its glyph

`Button::icon('chevron_left')` with no label falls back to the icon ligature,
so the previous-page button announced itself as **"Chevron left"**.
`Pagination.php` had always passed the proper label; the table's own pager
never did. Page buttons were bare digits — "3, button" — and the page you were
on was marked by colour alone. The pager is now a `<nav>` with a name, its
arrows are named from the catalogue, its pages read "Page 3", and the current
one carries `aria-current="page"`.

### Fixed — the client undid all of it on the first redraw

Every fix above landed in `Table.php`, and the first sort or filter threw it
away: `renderStatic` rebuilds the thead and the pager in JavaScript, and its
version had none of it. This is the same defect as the hardcoded `de-DE` in
1.55.0 — two implementations of one output, drifting. Both now emit the same
markup, and the browser test walks load → search → no-match → page 2 checking
that the attributes survive each rebuild.

### Added — `Button` accepts `attrs`

`aria-current` had nowhere to go. `attrs` takes `aria-*` plus a short
allowlist; anything else is dropped, because this renders buttons from
caller-supplied data and an unrestricted attribute name is an `onclick` waiting
to happen.

### Fixed — the escape helper existed once, in the wrong scope

`renderStatic` declared `const e` as a local, so `_staticPager` threw
`ReferenceError: e is not defined` the moment it tried to escape a label — a
bug this release introduced and the browser caught within a minute. Hoisted to
`_gkEsc` at module scope, with the local name now pointing at it, so there is
one implementation rather than two.

---
## [1.58.0] - 2026-08-28

Forms and modals, audited end to end. Both had the same shape of defect: the
information was on the screen and reached nobody who could not see it.

### Fixed — error messages announced to nobody

A field's error was rendered, styled, and never associated with the field.
There was no `aria-describedby` in `Form.php` or in `gridkit.js` — not one, in
either file. A screen reader read "Email address, required, edit text" and
stopped. The reason the form had rejected the entry was available only to
people who could see it, which is exactly what WCAG 3.3.1 exists to prevent.

Every control now carries `aria-describedby` pointing at its message container,
which now has the matching `id` and `role="alert"` so a message the client
writes after submit is announced rather than appearing silently. `aria-invalid`
marks the state, and only when there is an actual error — `required` is a
state, not a failure, and marking an untouched field invalid would be a lie.

The attributes are built once, next to `required`, and ride along to every
control. The alternative was the same two attributes repeated at a dozen echo
sites, one of which would have been missed. Composite widgets — the custom
select, the colour input, the rich-text frame — name themselves through
`aria-labelledby` rather than `for`, so they receive it there instead; the
hidden value carrier they wrap announces nothing.

On the client, `aria-invalid` is now cleared on the next submit. It never was,
so a field that failed once stayed marked invalid for the life of the page,
telling a screen reader it was still wrong long after the user had fixed it.
The first failed field is also focused, instead of leaving the caret wherever
it was while the messages appeared somewhere below.

### Fixed — the required asterisk was read aloud

`<span class="gk-required">*</span>` sat inside the label, so the field
announced as "Email address star". The input already carries `required`, which
is what conveys the state; the asterisk is decoration and is now
`aria-hidden`.

### Fixed — a modal was a div lying on top of the page

No `role`, no `aria-modal`, no name, and focus left on the button that opened
it — which the overlay covers. A keyboard user pressed Tab and walked the page
underneath: controls they could neither see nor click, with Escape the only way
back. A screen reader's virtual cursor read straight past the dialog into the
content behind it.

The dialog now has `role="dialog"`, `aria-modal="true"` and an
`aria-labelledby` pointing at its title (generated per overlay, since modals
stack). Focus moves inside on open — to the close button while the body is
still loading, then to the first real control once one exists — is trapped by
Tab and Shift+Tab, and is handed back to the opener on close, provided that
element still exists; a row button whose table has since reloaded does not.

Verified in a browser with real key presses: Tab does not escape across ten
presses, Escape closes, focus returns to the exact button that opened it.

### Verified and left alone — the sortable header's keyboard handler

1.56.0 made sortable headers real buttons, which answer Enter and Space
natively. The existing keydown handler could have made that fire twice. It does
not: `preventDefault()` suppresses the native activation, so exactly one click
reaches the sort. Measured with a real key press rather than reasoned about.
The comment above it, which still described `role="button"` on the `th`, was
rewritten to describe the code that is there.

---
## [1.57.0] - 2026-08-28

### Fixed — an unticked checkbox nobody could see

`--gk-neutral-400` is `#94a3b8`, which measures **2.56:1** on white. WCAG asks
4.5:1 of body text and 3:1 of a control's visible boundary. That colour drew:

- the caption under an upload zone, and the icon inside it
- the placeholder in an empty rich-text field
- **the border of every unticked checkbox and every unselected radio**

The last one is the serious one. When nothing is ticked, that border *is* the
control — there is nothing else to see. Below 3:1 a person with low vision is
looking at a blank space where a checkbox should be. All five now use
`--gk-neutral-500` (`#64748b`, 4.76:1). Dark mode already measured 4.76:1 and
is unchanged.

### Fixed — the demo told screen readers the wrong language

`<html lang="en">` was a constant while `Lang::set()` switched the content. At
`?lang=de` the page served German and declared English, so a screen reader
applied English pronunciation to German text. It now follows `Lang::locale()`.

### Added — contrast tests that compute rather than pin

Every existing test in `contrast.test.php` asserts that a particular value is
present, which catches a change but cannot tell a good one from a bad one. Two
new tests implement the WCAG relative-luminance formula and judge the colour on
whether it reads. Verified by reverting the fix and watching them fail.

### Not changed — touch targets

Measured at 390px: icon buttons 40px, sidebar items 38px, text buttons 26px.
Against WCAG 2.2 SC 2.5.8, which asks **24×24** at AA, all of them pass; inline
text links are explicitly exempt. The 44px figure is AAA. Inflating every
control to 44px would cost GridKit the density it exists for, to meet a level
it was never claiming.

---
## [1.56.0] - 2026-08-27

The W3C validator, pointed at the landing page, found a bug in the table.

### Fixed — a sortable column header was not a column header

Every sortable `<th>` rendered as:

```html
<th class="gk-sortable" tabindex="0" role="button" aria-sort="ascending">
```

`role="button"` replaces the implicit `columnheader` role, so the cell stopped
being announced as a column header — a screen reader user lost the column the
data belonged to. And `aria-sort` is only defined on a header, so putting it on
the same element made it invalid. Two errors from one line, three times over per
table. The validator reported six; neither the test suite nor any manual pass
had caught it in the year the code shipped.

The header now keeps `aria-sort` and its columnheader role, and the control is a
real `<button>` inside it — a tab stop by itself, with a focus ring, no
`tabindex` needed. The sort arrow moved onto the button, since on the `<th>` it
sat outside the control: the one part of the header that looks most like "click
to sort" was the one part that did nothing.

The client-side renderer had the mirror-image bug — it emitted neither
`role` nor `aria-sort`, so a table redrawn in the browser reported no sort state
at all and could not be sorted without a mouse. Both sides now render the same
shape.

**The two tests covering this asserted the bug.** They required `tabindex="0"`
and `role="button"` on the `<th>`, matching what the code did rather than what
it should do, so the suite stayed green throughout. They now assert the
structure and fail if a `role` or `tabindex` reappears on the header.

### Fixed — landing page markup

Twelve `<h4>` component cards directly under an `<h2>`, skipping a level; a
redundant `role="navigation"` on `<nav>` and `role="contentinfo"` on `<footer>`;
a section with no heading, now carrying a visually hidden one. The page
validates with no errors and no warnings.

---
## [1.55.0] - 2026-08-27

The landing page of a component library now contains a component.

### Fixed — the browser formatted numbers in German whatever the locale

`formatVal()` in `gridkit.js` built currency and number cells with
`toLocaleString("de-DE")`, hardcoded. The server builds the same cells from
`format.decimal`, `format.thousands` and `format.currency` in the catalogue.
The two agreed only in German. In English a table rendered `€1,200.00` on the
server and redrew it as `1.200,00 €` on the first sort, filter or page change —
silently, since nothing errors.

`Lang::jsConfig()` exported `js.*` and `action.*` but not `format.*`, so the
browser had no way to know better. It now exports all three, for the reason the
method already gives for `action.*`: one catalogue serving both sides beats a
second copy drifting away from the first. `_gkNumber()` and `_gkCurrency()`
build from those strings, and the currency template carries the symbol and the
side it sits on, so `€{value}` and `{value} €` both come out right.

Dates are a known gap, marked in the source: `format.date` is a PHP format
string JavaScript cannot consume, and the token needing month names has none in
the catalogue. A date column redrawn in the browser still comes back German.

### Changed — the product shot is the product

The image under the hero was a PNG with `v1.29.0` baked into it, sitting under a
header that reads v1.54.0, and it aged further with every release. It is now a
real `Table`: twelve rows, searchable, sortable, filterable, paginated, in the
mobile card layout below 640px.

That change surfaced the bug above, and two more things the page had been
getting away with: it linked neither `css/gridkit.css` nor `js/gridkit.js` —
the strings appear on the page, but inside the skill document it prints, not in
a tag — and it never called `Lang::jsConfig()`. A grep for the filename found
all three and reported them present. Only the browser disagreed.

The table is scoped with `data-gk-theme` rather than `class="gk-root"`, whose
`min-height: 100vh` would stretch the frame to the height of the window, and
the frame uses `overflow: clip` rather than `hidden`, which would have cut off
the status filter's dropdown.

---
## [1.54.0] - 2026-08-27

Three ways to hand the API to an assistant, where there was one.

### Added — the installable skill

`skill/` is `GRIDKIT_SKILL.md` split so an assistant reads the rules first —
which calls print and which return a string, how `Layout::asset()` resolves,
which components drop your filters unless told to keep them — and fetches one
reference when it needs one. Sixty-one kilobytes is a lot of context to spend on
a question about one method.

```
skill/SKILL.md                203 lines   the rules, with frontmatter
skill/reference/components.md 1044 lines  every component, every option
skill/reference/javascript.md  121 lines  GK.*, live tables, global search
skill/reference/css.md          30 lines  class names, utility classes
```

Generated by `ci/build-skill.sh`, never edited by hand: one source, two shapes,
and a test that fails if they drift. The split is lossless — all 39 headings,
all 46 runnable examples, all 128 public methods.

Install: `cp -r skill ~/.claude/skills/gridkit`.

### Added — a stable address, and llms.txt

`https://gridkit.ssi.at/skill` serves the whole document as `text/markdown`,
with CORS open so an assistant can fetch it. `llms.txt` at the site root points
there, which is the convention a site uses to tell a model what it needs to
know. Both through `.htaccess`, which also stops serving `composer.json`,
`tests/` and `ci/` — none of which are part of the site.

### Fixed — a commit that would have blocked every push

The 1.53.0 commit carried `.github/workflows/ci.yml`. The token this repository
is pushed with has no `workflow` scope, so that commit could never leave the
machine and everything after it was stuck behind it — the local branch sat one
ahead of the remote with no error to explain why. Recommitted without it; the
workflow still ships as `ci/github-actions.yml`.

**2,199 assertions.**

---
## [1.53.0] - 2026-08-27

Two small things in the table, both of which had been quietly costing
readability. Neither changes any markup — every existing page gets them.

### Changed — digits line up

**`font-variant-numeric: tabular-nums` on table cells.** Proportional digits
are narrower for a 1 than for a 0, so a column of amounts or dates wanders a
little in every row. In prose that is right; in a column of figures it is
noise. Only digits are affected — text in the same cell keeps its normal
spacing.

Visible immediately in any table with a date, a count or an amount.

### Changed — the header reads as the header

**The header rule is one step darker than the row rules**
(`--gk-surface-container-highest` instead of `--gk-outline-variant`).

1.44.0 removed the grey bar behind `thead` for good reasons: caps, size and
weight already announce a header, and a filled bar says the same thing a second
time. But the line that was left to do the separating alone was the *same*
hairline that separates the rows — so it separated nothing in particular, and
in a long table the head floated among the rows.

One step darker is enough. The token carries its own dark-mode value, so this
holds in both modes without an exception rule.

### Unchanged on purpose

The pill radius, the row padding and the zebra decision stay as they are. They
are taste, not craft, and taste belongs in a theme — not in a patch release
that every consuming system picks up without asking.

---
## [1.52.0] - 2026-08-27

Packagist was updated, so `composer require mmollay/gridkit` finally installs
the current version rather than v1.4.0 from March. That made a test possible
that had never been run: download the archive Packagist really serves, unpack
it, write a page against it the way the README says, and use it.

It found a bug in about two minutes.

### Fixed — a delete button that stopped asking

**`data-gk-confirm` was not emitted by the client re-render.** The server puts
it on the button; the browser rebuilds that button on every sort, search and
page change of a `setData()` table, and rebuilt it without the attribute. So a
delete button asked for confirmation once, on first load, and then never again
— the click went straight through to `gk:rowaction`.

Measured in a browser against the real package:

| | `data-gk-confirm` | click asks? |
|---|---|---|
| on load | "Delete this entry?" | yes |
| after a sort | *missing* | **no** |

1.32.0 fixed exactly this on the server side, where the delete button in the
README's own example was deleting without asking. The client path kept it.

The re-render now emits it, using `js.confirm_delete_row` — the single-row
wording that matches `table.confirm_delete`, not the bulk "Really delete
entries?" that `js.confirm_delete` carries.

### Verified — the whole thing, from Packagist

Not a simulation this time. The zipball Packagist points at, unpacked into
`vendor/mmollay/gridkit/`, with a page written from the README: sidebar, fixed
header, stat cards with trends, a searchable sortable table with row actions and
client-side paging, in English and German.

All three assets 200. No PHP notice, no console error. Page two slices in the
browser with no network request. Search filters. The status labels colour
correctly. The delete button announces itself as "Delete" / "Löschen".

**2,184 assertions.**

---
## [1.51.0] - 2026-08-27

Round twenty-three asked whether twenty-three rounds of edits still hold
together, and pointed five auditors at the demo — the shop window, audited for
its claims in 1.44.0 but never for its markup.

### Fixed — nine of eleven demo sections were under the sidebar

**One extra `</div>` closed `.gk-with-sidebar` right after the Form section.**
Everything after it — Cards, Layout, Navigation, Search, Feedback, UI,
Examples, Tooltip, Changelog — was a child of `<body>`, so the left 260px of
each sat beneath the fixed sidebar. Not merely clipped: `elementFromPoint(150,
300)` returned a sidebar link, so that strip swallowed clicks meant for the
demo's own controls.

CHANGELOG 1.36.0 describes this exact failure as the thing the wrapper
prevents. The demo has had it ever since.

It survived because counting tags cannot see it: the stray close consumes the
wrapper, which demotes the intended closer at the end of the file to a trailing
stray, and browsers discard that silently. The div count nets to zero. Only an
ancestry assertion catches it, and there is one now.

### Fixed — the demo at 390px

- **Three fixed-column grids** gave each child 101px on a phone — the "Sizes"
  sample rendered three tables at 101px each, labels printing over values. They
  wrap now.
- **An invisible tooltip widened the page by 58px.**
  `[data-gk-tooltip]::after` is `position: absolute` with `white-space: nowrap`
  and `opacity: 0` — laid out while invisible, so a long one pushes the
  document wider with nothing visible anywhere near the edge. That is why
  several mobile passes went straight past it. The left and right variants
  reach out with `left`/`right: 100%` and added their own. On a narrow screen
  the text now wraps under a cap and the side variants move above their
  element; the desktop look is untouched.

  The first attempt at that fix did nothing, because the override sat before
  the rules it meant to beat and lost on source order. So did the first version
  of the test for it, which compared the block against itself — the block
  contains the very selector it was searching for.

### Added

- A test that every `[data-section]` in the rendered demo has `.gk-with-sidebar`
  as an ancestor. Checked against the stray tag put back: nine failures.
- A test that the narrow-screen tooltip cap exists **and comes after** the rules
  it overrides.
- **A Composer install, end to end.** The README has offered
  `composer require mmollay/gridkit` from the start and nobody had ever run one.
  The test builds the archive Packagist would ship, lays it out as Composer
  would, generates the autoloader Composer would generate, and checks that the
  documented asset path resolves to the file that is really there — the one
  thing a plain clone never exercises. It passes.

**2,174 assertions**, on three interpreters.

---
## [1.50.0] - 2026-08-27

Two claims on the README's requirements line had never been checked, in fifty
releases: **PHP 8.2+**, and that `mbstring` is used when present but never
required. Every test run in this repository's history had used one interpreter.

This machine has three. So:

```
  ok   PHP 8.2.33   with mbstring     ok — 2098 assertions passed
  ok   PHP 8.4.24   with mbstring     ok — 2098 assertions passed
  ok   PHP 8.5.9    without mbstring  ok — 2098 assertions passed
```

Both claims hold — and the second turns out to have been under test the whole
time without anyone noticing: the default `php` here is 8.5, which has no
mbstring, so every run in this session already proved it. The demo, the example,
the landing page and the skeleton all serve cleanly on 8.2 as well, AJAX
fragment included.

That is a rare entry in this changelog: a documented claim that was simply true.

### Added

- **`ci/matrix.sh`** — what the CI workflow would do, for a machine where CI
  does not run. It finds every PHP on the host, runs the suite on each, and
  reports which of them had mbstring. One command, no arguments.
- A check that the script exists and does those two things, so the matrix
  cannot quietly stop covering what it claims to cover.

**2,103 assertions**, on three interpreters.

---
## [1.49.0] - 2026-08-27

Round four of the skill-file test — five new tasks, aimed at the API 1.48.0
documented for the first time.

| round | tasks | first try | confirmed gaps |
|---|---|---|---|
| one | set A | 0 / 5 | 30 |
| two | set A again | 4 / 5 | 17 |
| three | set B | 0 / 5 | 31 |
| four | set C | **1 / 5** | **17** |

On new ground: 0 → 1 of 5, and the gaps halved. Modest, and real. One agent
wrote its page straight through and changed nothing.

### Fixed — a claim I made in 1.48.0 that was false

**`Lang::jsConfig()` did not carry the strings `loadDir()` registered, and for a
locale GridKit does not ship it emitted `window.GK_LANG=[]`.** 1.48.0 said the
opposite — that a hand-rolled `$t()` closure "costs you the browser-side
catalogue that `Lang::jsConfig()` ships". It shipped nothing of the sort: a page
in French got an empty array, which wiped GridKit's own browser strings too, so
`GK.t('no_entries')` came back as `no_entries` while the server side quietly
read English. And `[]` is truthy, so the obvious `window.GK_LANG || {}` guard
did not help either.

`jsConfig()` now uses the same fallback order `t()` uses — English as the floor,
the active locale overriding key by key — and forces an object, so an empty
payload is `{}`.

### Fixed — a header 260px too wide

**In `sidebar-first`, a fixed header ran past the right edge of the screen and
took the user menu with it.** `.gk-header` carries `width: 100%`, and on a
`position: fixed` element an explicit width beats the `right` offset — so the
header started at `left: 260px` and was still a whole viewport wide. Measured in
a browser at 780px: the header ended at 1040, and the user menu — which holds
the only theme switcher on that layout — sat at x=1016, off screen and
unclickable. PHP says nothing, the console says nothing.

### Added — to the skill file

- **One place that names the filter trap.** `Pagination`, `PageSize`,
  `FilterChips` and `YearFilter` each rebuild the URL from their own parameter
  and drop everything else unless told to `preserve()` it. Four sections
  mentioned it in passing; it now has a heading of its own, because an agent
  following the file literally shipped a report where changing the row count
  reset the year, the status and the search at once.
- `->footer()` takes an array per cell — `['text' => …, 'align' => 'right',
  'bold' => true, 'colspan' => 2]`. Only the plain-string form was shown, so a
  currency total could not be lined up under its own column.

### Not changed

Two reported gaps did not survive checking: `->loadTime()` does print in the
toolbar (`38 ms` is there), and `GK.t()` is in the file.

**2,098 assertions.** The skill file is 1,363 lines.

---
## [1.48.0] - 2026-08-27

Three rounds of the agent test gave the same verdict from two directions: the
components with a real section were right first time in every run, and every
failure landed on one documented as a table row. So this round stopped guessing
at individual gaps and measured the thing itself.

**Public methods the skill file never named: 43 of 128.** Now: 1.

| class | before | after |
|---|---|---|
| `Layout` | 1 of 6 | 6 of 6 |
| `Lang` | 3 of 7 | 7 of 7 |
| `YearFilter` | 5 of 9 | 9 of 9 |
| `Table` | 14 of 24 | 24 of 24 |
| everything else | 12 classes short | complete |

The one left out is `Pagination::fromPaginatorHtml()`, the string twin of a call
that is itself an adapter for an object GridKit does not ship.

### The one that matters

**`Lang::loadDir()` was undocumented, and it is exactly what three agents said
did not exist.** Round two's bilingual agent wrote that the file "never
documents how to register application translation strings at all, which is the
single most important thing a bilingual page needs" and supplied its own
`$S['en']` array. It was right that the file did not say — and 1.45.0 then went
and *documented that workaround* as the recommended pattern, because I had not
found `loadDir()` either.

`Lang::loadDir(__DIR__ . '/lang')` loads every `en.php` / `de.php` in a
directory and **merges** them with GridKit's own strings. So an application's
translations live in the same catalogue, keep `{placeholder}` substitution, and
ship to the browser through `Lang::jsConfig()` — none of which a private `$t()`
closure gives you.

### Fixed — in the skeleton

**`Theme::bodyTag()` does not emit the layout attribute.** The documented page
skeleton used it, so a page calling `Layout::mode('sidebar-first')` set the mode
and never applied it — `data-gk-layout` simply was not on the `<body>`.
`Layout::bodyTag()` emits both sets and is what the skeleton, the examples and
the echo/return table now name. `skeleton.php` and the demo already had it
right; `examples/invoices/` did not.

### Added

- Documented for the first time: `Layout::mode/getMode/attributes/version/bodyTag`,
  `Lang::load/loadFile/loadDir/locale`, `Table::toolbar/searchable/size/variant/
  nowrap/footer/emptyState`, `YearFilter::years/mode/allOption/selectClass`,
  `Form::cancel/hidden`, `Button::fab`, `Sidebar::headerOffset`,
  `Theme::available`, `Icon::has`, `ActionGroup::html`.
- A check that keeps it that way: every public method of every component must
  be named in the skill file.

**2,020 assertions.** The skill file is 1,298 lines, from 814 four rounds ago.

---
## [1.47.0] - 2026-08-27

Round three of the skill-file test, and the honest result: **five different
tasks, 0 of 5 on the first try.**

| round | tasks | first try | confirmed gaps |
|---|---|---|---|
| one | dashboard, crud, forms, bilingual, live | 0 / 5 | 30 |
| two | the same five again | **4 / 5** | 17 |
| three | auth, hand-built table, global search, SPA shell, utility page | **0 / 5** | 31 |

So round two measured what it measured: those five holes, closed. It did not
make the file generally sound. On ground it had not been tested against, it
fails as badly as it did at the start. That is worth stating plainly, because
the 4/5 read like progress on the file and was progress on five tasks.

The agents' own words are consistent about where it holds: `Table`, `Form`,
`Header`, `Sidebar`, `StatCards`, `Theme` and `Lang` were right first time in
every run. Everything below is what the third set walked into.

### Fixed — in the source

- **Changing rows per page threw away every other filter.** `PageSize::preserve()`
  takes a list of parameter *names* and reads their values from `$_GET`;
  `Pagination` hands down a name => value *map*. The list form used each value
  as a name, nothing matched, and the select shipped `data-preserve="{}"` — so
  on a report with a year filter and a sort, picking "50 per page" reset both.
  Silently. `preserve()` accepts either shape now, and `Pagination` forwards its
  own `baseUrl` and `params`, so the select keeps exactly what the page links
  keep.

- **A table's own search box opened the global search overlay.** `Table::search()`
  renders `<input class="gk-search" data-gk-search>` — the same attribute
  `GK.search` binds as its overlay trigger. Put a table and the quick-search on
  one page, which is an ordinary admin page, and clicking the table's filter box
  covered it with the overlay. Removing the attribute to fix that killed the
  table's own filtering instead. The overlay now ignores any trigger inside a
  table or a toolbar.

### Fixed — in the skill file

- **`Pagination::fromPaginator()` cannot be called.** It duck-types an object
  with `currentPage`/`totalPages`/`total`, and GridKit ships no such class — the
  file's two examples handed it an array, which is a `TypeError`. Worse, the
  wrong object prints an empty hidden `<nav>` and says nothing.
  `Pagination::render(array $o)` is documented as the form to use now, with
  every key it reads; `fromPaginator` is described for what it is, an adapter.
- **`PageSize::render()` sat in the "these PRINT" table as though it were
  static.** It is an instance method behind `PageSize::make()`. Its fluent API
  (`current`/`options`/`baseUrl`/`preserve`/`live`) was documented nowhere, on
  the very page the pagination section is written for.
- `Icon::svg($name, $px)` takes an int pixel size, not an options array.

**1,676 assertions.**

---
## [1.46.0] - 2026-08-27

The same five tasks, the same rules, fresh agents, against the skill file as
1.45.0 left it. This is the measurement 1.45.0 was worth making.

| | round one | round two |
|---|---|---|
| worked on the first try | **0 / 5** | **4 / 5** |
| gaps reported | 33 | 18 |
| gaps confirmed | 30 | 17 |

Four of the five agents named the echo-versus-return table at the top as the
thing that saved them, unprompted. One wrote its page straight through, ran it
once, and changed nothing.

### Fixed — in the source

- **`Button`'s `form` option could not submit anything.** A button carrying
  `form="…"` renders `type="button"` by default, and a `type="button"` has no
  default action — so the attribute sat there inert and the click produced no
  request at all, silently. This is the option 1.45.0 added to the
  documentation without checking it. Setting `form` now makes the button a
  submit button unless the caller names a `type` outright.

- **A `setData()` table ignored the columns `search()` was given.** The keys
  never reached the browser, so the client fell back to searching every rendered
  column: a declared key that is not itself a column was never searched, and the
  markup of an HTML column matched instead. Measured on a probe — searching for
  a value present only in a declared, unrendered key returned "No matches".

### Fixed — in the skill file

- **`Header` had no section at all** — the only component without one, and the
  reason an agent shipped a page with two theme switchers: `->user()` renders
  one of its own, and nothing said so. There is a section now, with a table of
  which of the three you actually want.
- **`Sidebar` and `Select` had none either.** Both have one now, with every
  option each class reads.
- **`->ajax()` is the opt-in for an AJAX form**, and the section titled "Form
  (AJAX)" never mentioned it. Without it the form submits natively and
  navigates to the endpoint, where the documented JSON response is shown to the
  user as raw text.
- `Button`'s `type` option was undocumented.
- `TableHeader::advanced()` was documented with a German literal as its default
  summary; the library translates it, so the file was pessimistic about its own
  behaviour.
- An out-of-band `<template data-gk-replace>` replaces the matched element
  **whole**, so the template body must re-emit an element the selector matches.
  Emitting only the inner cards deletes the target on the first reload — no
  error, and the stats keep their first-load values for ever.
- `textarea` and `richtext` drop `placeholder` silently; `rows` is `textarea`'s
  only extra option.

### Added

- A check that every component the README lists has a section in the skill
  file. Four did not.

**1,664 assertions.** The skill file is 1,064 lines, from 814 two rounds ago.

---
## [1.45.0] - 2026-08-27

Round sixteen put the project's own headline claim on trial. README.md says:

> The agent writes correct GridKit PHP on the first try, because the whole
> surface fits in one file it can actually read.

Five agents were given a page to build and one source of knowledge —
`GRIDKIT_SKILL.md`, with the source tree explicitly off limits. **None of them
got a working first draft.** Thirty-three gaps reported, thirty confirmed by an
adversarial verifier that reproduced each one.

Every one of the five eventually shipped a page that runs clean, so the file is
close. But the failure mode it produced is the worst kind: **exit 0, no warning,
and a page with a piece silently missing.**

### Fixed — the rule nobody had written down

Half of GridKit prints and half returns a string, and nothing said so. Called as
a statement, `Theme::switcher()` renders nothing at all; three of the five
agents lost their theme switcher exactly that way and only found out by reading
the HTML. `Header::render()` is the trap inside the trap — every other component
you build with `new` prints, and that one does not, so a fourth agent's filter
chips landed in front of the doctype.

The skill file now opens with the rule and the full list of which calls do
which, before anything else.

### Fixed — a documented feature that had never worked

**`setData()` did not page.** The file has always said it "searches, sorts and
pages in JavaScript". Search and sort did. Paging did not: the server emitted
every row plus a pager, the client re-render removed that pager and never
rebuilt it, and the page buttons fired an AJAX reload — for a table whose rows
were already all in the browser, from a page that usually had no handler for it.
The agent asked to build a list shipped a table showing all 25 rows on page one
under a pager that led nowhere, and `php list.php` exited 0.

Now: the server renders one page, the payload carries every row and the page
size, the client slices, and the pager is rebuilt on every render. Searching or
filtering returns to page one. Verified in a browser — paging a static table
makes no network request at all.

### Fixed — in the source

- **A form field's `error` option was accepted and dropped.** `Form` emitted an
  empty `<div class="gk-field-error">` and put nothing in it, so the classic
  POST-redisplay had nowhere to put a message. The container was there the whole
  time; nothing filled it.
- **`Header::user()` injected an untranslated `Design` label** into the user
  menu — byte-identical in both locales, and the only German-flavoured string
  that survived an English page. It goes through `Lang` now.

### Fixed — in the skill file

- `Layout::asset()` stamps a path, it does not resolve one. Four of the five
  agents wrote a page outside the GridKit directory and got dead stylesheet
  links with a cache-buster on them, so the URL looked right. Now spelled out,
  with the three layouts that actually occur.
- `Modal::container()` was still in the page skeleton. It has emitted nothing
  since 1.42.0.
- **TableHeader was labelled "Required for every table page".** It is not, and
  its `search()` competes with `Table::search()`: two boxes, one of them inert.
  There is a table now saying which to use when.
- **`Lang` is not a catalogue for your application.** The status-label sample
  showed `Lang::t('paid')`, which returns the string `paid` in every locale
  because there is no such key — it would have stamped an English token into a
  German column. There is now a section on translating your own strings.
- The `labels` colours are a fixed list of six. `primary` and `success` are
  Button vocabulary and render an unstyled label here.
- `StatCards`' `trend` is printed verbatim: pass `'+12%'`, not a float, or the
  card reads `-8`.
- `Button`'s options were half-listed — `shape`, `aria`, `title`, `disabled` and
  `form` were all missing, and the sample called a returning method as a
  statement.
- The last German example strings are gone from the English file.

### Added

- Three checks in `tests/skill.test.php` and `tests/contracts.test.php`: the
  file must state the echo/return rule and name every returning call, must not
  contradict it in its own examples, and a static table must page.

**1,639 assertions.**

---
## [1.44.0] - 2026-08-27

Round fifteen turned outward: the demo, the landing page, the worked example,
and the first fifteen minutes of somebody who has never seen this repo. Five
auditors, an adversarial verifier per finding. Fifteen findings, none refuted.

The worst of them is that after fourteen rounds of fixing the inside of the
library, **the front door was shut the whole time**.

### Fixed — the install path in the README did not work

- **`cp gridkit/skeleton.php my-app/index.php` ended in an uncaught Error on
  the copied file's first line.** `require_once __DIR__ . '/autoload.php'` is
  relative to the copy, so it looked for GridKit inside the new app directory.
  Zero bytes of output, a stack trace in the log. The file's own header warned
  about the *asset* paths, which merely 404, and said nothing about the require,
  which is the line that kills it. The skeleton now looks in the four places
  GridKit is normally installed — beside itself, one directory up, `vendor/`,
  and a sibling clone — derives the asset prefix from whichever it found, and
  when it finds none says so in a sentence naming the line to change.

- **The skeleton's "New product" button opened a modal containing the GridKit
  marketing landing page.** It fetched `forms/product.php`, which the repo does
  not ship, and PHP's built-in server — the server the README tells you to run
  — falls back to the root `index.php` for any unmatched path. 138 KB of
  marketing copy inside a dialog headed "Edit product". The skeleton answers
  its own modal now and stays one file, which is the point of a skeleton.

- **`composer require` was offered with no way to reach the CSS or JS.**
  `Layout::asset()` stamps the path it is handed; it does not resolve one. So
  the documented call emitted `css/gridkit.css` while the files sat in
  `vendor/mmollay/gridkit/css/`, and the page rendered unstyled with nothing to
  go on. The README now says where they are and what to hand `asset()`.

- **The landing page's "Get Started in 30 Seconds" copied the same broken
  command**, and left out both the `mkdir` that `cp` needs and the server that
  makes "open in browser" possible.

Walked end to end from a fresh clone afterwards: page 200, stylesheet 200,
script 200, modal 200, no errors. Before: 500, 404, 404, 500.

### Fixed — the example lost money

- **A German-formatted amount without cents was stored as a thousandth of
  itself.** Typing `12.750` — twelve thousand seven hundred and fifty — saved
  `12,75 €`, and the server answered `{"ok":true}`. The rule kept a lone dot as
  a decimal point regardless of locale. Both separators present now means the
  last one is the decimal point; a lone separator is only ambiguous in front of
  exactly three digits, and that single case is decided by the language the
  form was filled in. Fourteen cases across both locales, checked.

### Fixed — my own regression from 1.43.0

- **Multi-select and AJAX-select threw on every page load, and took the rest of
  the library down with them.** 1.43.0 made the value carrier a real control so
  a required field could be validated — and updated one of the three places
  that look for it. The other two read `null.value`, and because this runs
  inside `GK.init()` the uncaught TypeError aborted every binder after it.

### Fixed — the demo taught things that are not true

- **Three of the six format annotations named output the component never
  produces.** `// Yes / No` is unreachable under any configuration; the boolean
  format emits ✓ and –. The currency and date annotations quietly assumed
  options the sample does not pass.
- **A block headed `// Combinable:` showed two `variant()` calls.** `variant()`
  is one slot: the second replaces the first, so `celled` was silently
  discarded and the reader got a padded table.
- **The AJAX-select demo requested `/demo/demo/api/search.php`** — a stray
  prefix on a page that already lives in `/demo/`. The dropdown said
  "Searching…" for ever.
- **The search sample documented the German response contract**, which 1.39.0
  replaced, in German, on the English page.
- **The tooltip section forced 110 px of horizontal scroll at 390 px.**

### Fixed — claims that were not true

- The landing page put the theme contrast between 4.3:1 and 5.3:1; three of the
  six themes fall outside that. The measured range is 4.69:1 to 5.73:1, which
  is what README.md has said since 1.38.0.
- `og:image` pointed at `/og-image.png`, which 404s, so every shared link
  rendered without a preview. The file that exists is `docs/social-preview.png`.
- The README described the invoice example as "about 300 lines". It is 673.
- The example's empty state told visitors there were no invoices when a search
  or filter had simply matched nothing — the table has its own wording for
  that, and offers the way back.

### Added

- `tests/firstrun.test.php` — walks the documented install path instead of
  trusting it: the skeleton runs where it lives, runs where the README says to
  copy it, explains itself when GridKit is nowhere near, answers its own modal,
  and the README's numbers match the files. Fifteen rounds of tests that called
  the classes directly never once opened the front door.

**1,595 assertions.**

---
## [1.43.0] - 2026-08-27

Round fourteen, and the first finding is the previous round's. Five auditors
over Form, Auth and the parts of Table nobody had walked, then an adversarial
verifier per finding whose job was to refute it by running it. Fourteen
findings; none were refuted.

### Fixed — two things 1.42.0 got wrong

- **The required-select fix landed on a code path nobody calls.** 1.42.0
  announced "a required searchable select submitted empty" as fixed, and it was
  — in `Select::searchable()`, which outside these tests nothing calls. Every
  document, the demo and SPEC.md build a select through
  `Form::field($name, $label, 'select', …)`, and that copy still emitted its
  value carrier as `<input type="hidden">` with no `required` at all, under a
  label wearing the red asterisk that promises the field is required. Verified
  in a browser: `willValidate` false, and clicking Save posted `{"country":""}`.
  Fixed now on the path people actually use, and on `multiselect`,
  `ajaxselect`, `toggle` and `radio` besides — `required` was inert on eight of
  the eleven field types. `richtext` cannot be validated by the browser at all
  (CKEditor hides its carrier), so it is checked in script instead of wearing a
  star that means nothing.

- **The CSS half of that fix was pasted into the middle of another rule.** The
  new block went between the selector and the brace of the toolbar rule below
  it. It stayed valid CSS and shipped, and two things broke at once: the value
  carrier was hidden only inside a toolbar — a visible 177 px text input in
  every plain form using a searchable select — and the toolbar's 34 px height
  escaped onto every searchable select on every page. A test now fails on any
  comment spliced between a selector and its own brace.

### Fixed — security

- **Any anonymous visitor could take every guarded page down with one cookie.**
  A cookie sent as `gk_remember[]` arrives as an array, and `str_contains()` on
  an array under `strict_types` is a fatal `TypeError`. So every
  `Auth::protect()` page answered 500 — and stayed that way, because logout
  crashed on the same line and could never clear the cookie. Guarded on both
  sides, deliberately asymmetrically: the clearing path must not return early,
  or the lockout would survive the fix.

- **A field name could become a real event handler.** It reaches ids that are
  then written into an inline `<script>` as a single-quoted JS string, so
  escaping is the wrong tool — the two spellings have to stay byte-identical.
  A name carrying a quote closed the attribute and landed a working
  `onmouseover` on the page. The name is slugged for id use now; the md5 suffix
  already supplied the uniqueness.

- **`renderLogin()` wrote its asset paths into `href` and `src` unescaped**, so
  a caller-supplied path could add attributes of its own — on a login page.

- **`Auth::check()` and `Auth::user()` ignored the remember-me cookie that
  `Auth::protect()` accepted.** A returning visitor was let through the guard
  while the page rendered as though nobody were logged in. Identity is resolved
  in one place now.

### Fixed — things that broke on the second interaction

- **A form loaded into a table modal was inert.** Modal content arrives after
  `DOMContentLoaded`, so the widget binders never ran on it: in the repo's own
  invoice example, editing an invoice and trying to change Status did nothing
  at all — the dropdown would not open by mouse or keyboard, and the form
  posted back the value it was loaded with. This is the flow the README leads
  with.

- **A table with `toolbar(false)` deleted itself on the first sort.** The
  reload path assumed a `.gk-toolbar` was there and threw after it had already
  removed the old rows, leaving an empty wrapper — and because the error is not
  a transport error, the "Try again" fallback was skipped. `renderStatic()` has
  guarded this all along, twelve hundred lines up.

- **The bulk bar was swept away by the first AJAX reload.** It sits beside the
  toolbar rather than inside the table, so paging or filtering removed it for
  good while selection went on working internally with nothing on screen to act
  on it.

- **`selectable('uuid')` was ignored by the client re-render.** The key never
  shipped in the embedded payload, so the client fell back to `"id"` and every
  row id went empty on the first sort — the whole selection collapsing to one
  blank entry.

- **The submit button read "saveSave" after the first submit.** The busy state
  saved `textContent`, which flattens the Material Icons glyph and the label
  into one string, and restored that. Permanently, from the first submit on.

- **Six field types had a `<label for>` pointing at nothing** — the value
  carrier is hidden from the accessibility tree, or the control is a group. The
  label named nothing and clicking it focused nothing. They are named through
  `aria-labelledby` now, by the visible label a person can also read.

### Added

- `tests/fields.test.php` — the general questions for a field: does `required`
  reach something the browser can enforce, does every label resolve, does every
  widget have a name, can a hostile field name become an attribute. Each was
  checked against its own reintroduced bug.

**1,577 assertions.**

---
## [1.42.0] - 2026-08-27

Two passes at once. The accessibility of what every component emits, measured
in the tree a screen reader actually reads; and an audit of the classes that
had never had a systematic one — five auditors, then an adversarial verifier
per finding whose job was to refute it by running it. Thirteen findings
survived that; two did not, and are not here.

### Fixed — what a screen reader was told

- **81 controls on the demo page had no accessible name at all**, 66 of them
  icon-only buttons — including the delete button on every table row, which is
  the example the README leads with. It announced as "button" and stopped.
  Row buttons, icon buttons, the theme switcher, the pager and the form's clear
  button now carry translated names, from a new `action.*` catalogue that
  `Lang::jsConfig()` also hands to the client so a static table and a live one
  sound identical.

- **179 icon glyphs were read aloud as if they were labels.** A Material Icons
  span contains the ligature — `delete`, `light_mode` — not words meant for a
  person. The mode toggle announced as "light_modedark_mode". Every decorative
  glyph the library emits is now `aria-hidden`, which is also what exposed the
  buttons that had never had a real name: hiding the ligature made the gap
  visible.

- **`GK.tip` was removing the accessible name.** It moves every `title` into
  `data-gk-tip` on the first hover and deleted the attribute — and for 55
  controls across the demo and the example application, `title` was the only
  name they had. They went silent as the pointer crossed them, which is why
  nothing static ever caught it. The name is handed to `aria-label` first now.

- **The clear button in a form was announced as "delete"** — its glyph is
  `delete`, and it deletes nothing; it empties a text field.

- **A column formatted as an address emitted `<a href="mailto:"></a>`** for
  every row without one: a focusable, nameless link that opens a blank
  composer. The date formats two lines above it have always guarded this.

### Fixed — promises the classes did not keep

- **`GK.modal.open(title, html)` is documented three times in
  `GRIDKIT_SKILL.md`** — including as Common Pitfall #5, which taught the
  failure as though it were the fix. The real signature is
  `open(title, url, params, size)` and it POSTs the second argument: passing
  markup requests it as a path, so the modal filled with the server's 404 page,
  `<style>` and all. The demo had it right all along; only the file agents read
  was wrong. Second round running that this file has taught a call that does
  not work.

- **`->group('2024')` killed the page.** Group labels are array keys, so PHP
  casts a decimal string to `int`, and under `strict_types` the escaper's
  string parameter made that a fatal `TypeError` — thrown mid-output, leaving
  `<aside>` and `<nav>` unclosed and nothing after them rendered. Any year, id
  or number used as a heading did it.

- **A required searchable select submitted empty.** `['required' => true]` was
  accepted, stored and emitted — onto an `<input type="hidden">`, which the
  HTML standard bars from constraint validation. The attribute was inert. The
  value carrier is a real control now, hidden by opacity rather than
  `display:none` so the browser can still anchor its message, and deliberately
  not `readonly`, which would disable validation just as effectively. Verified
  in a browser: `willValidate` true, submission blocked, and valid once a
  choice is made.

- **The searchable select was in the tab order and answered no key.** The
  markup gives the display `tabindex="0"` — a promise that it can be operated
  — and only a click was ever bound. It also had no role and no name, so it
  announced as an empty group. Enter, Space and ArrowDown open it, Escape
  closes it and returns focus, and `aria-expanded` follows every path.

- **The header user menu could not be opened by keyboard.** A plain `<div>`
  with a click handler, and the only route to Profile, Settings and Sign out.

- **`StatCards`' `trend` was never implemented.** README.md sells the component
  on it — "KPI tiles with trend" — the landing page shows the call, the
  changelog announced it in 2024, and `render()` never read the option. A test
  passed `'trend' => '+8%'` and asserted only that the markup was non-empty.

- **`Layout::asset()` gave the release version, never the file's timestamp, for
  exactly the path form the docs teach.** The test was "did `preg_replace`
  change the string?" — true for `../css/gridkit.css`, false for the bare
  `css/gridkit.css` that `skeleton.php` and `GRIDKIT_SKILL.md` both use. So the
  one spelling everybody copies was the one that never busted its own cache,
  which is what the comment above it exists to prevent.

- **Two submenus with the same label shared a DOM id.** The id was
  `md5(label)`, so "Monthly" under Sales and under Purchases collided, and
  since `getElementById` returns the first match, clicking the second toggle
  opened the first submenu.

- **`FilterChips`' `color` did nothing for six of the nine values in use.**
  The class passes the value straight through as `.gk-chip-x`; three rules
  existed. The demo ships green, orange, red and blue and the skill file
  teaches primary — all of them rendered as an unstyled chip.

- **An unrecognised string in a header menu became an empty link.** Anything
  that was not exactly `'divider'` fell through to the anchor branch and
  emitted `<a class="gk-dropdown-item" href="#"></a>` — a nameless tab stop in
  the menu. The demo's own header had one.

- **`Header::search()` and `YearFilter::mode('dropdown')` had no accessible
  name** — a placeholder is not one, and the year select had nothing at all,
  while every other filter in the library was fixed in 1.32.0.

### Changed

- **`Modal::container()` emits nothing.** It printed an empty hidden shell that
  every layout was told to call once per page, and nothing ever read it:
  `GK.modal.open()` builds its own overlay with `createElement`. It sat in the
  DOM of every page for the life of the page, close button included — one a
  screen reader announced as the multiplication sign. Kept as a no-op so
  existing layouts keep working. `tests/render.test.php` had been asserting
  that this markup was present, which is how it stayed.

### Added

- `tests/names.test.php` and `tests/contracts.test.php` — the general
  questions, not the specific bugs. Does every control a component emits have
  an accessible name? Is any control named by `title` alone, which `GK.tip`
  will take away? Is any glyph exposed as a label? And for each documented
  option: does the class read it?

  `tests/a11y.test.php` has existed since 1.32.0 and checks exactly the
  controls that round touched. It never asked the general question, which is
  the same way the contrast pass in 1.38.0 measured only filled buttons. Both
  files ask it now.

**1,518 assertions.**

---
## [1.41.0] - 2026-08-26

The JavaScript — 3,233 lines, the last large file without a systematic pass.
The method was the same one that has worked for twelve rounds: take what the
documentation promises, call it exactly as written, and watch.

### Fixed

- **`GK.table.refresh('table-id')` did not exist.** `GRIDKIT_SKILL.md` has
  documented it since 1.10 under the heading *"Table refresh (after save/delete
  in server-side mode)"* — the single most common thing anyone does after
  saving a modal. `GK.table` had `init`, `reload(wrapElement, …)` and
  `refreshAll()`; nothing named `refresh`. Every agent that followed the
  document wrote the documented call and got a `TypeError`. That file exists so
  assistants write correct GridKit code on the first try, which makes a
  documented method that was never implemented the most expensive kind of bug
  this project can carry. `refresh(id)` now exists, picks the static or live
  path the way `refreshAll()` always has, and returns `false` when no table on
  the page carries that id.

- **`GK.belegModal` POSTed to the author's own application.**
  `fetch("/faktura/api/beleg/unlink", …)` — a route from a private invoicing
  system, hardcoded inside a general-purpose library and reached through a
  button the class documents as a feature. On anyone else's site that is a 404,
  and the `.json()` behind it rejected with nothing catching it, so the detach
  button did nothing and said nothing. The endpoint is now yours to name
  (`GK.belegModal.unlinkUrl`, or `unlinkUrl` per call); with neither set the
  button fires a cancelable `gk:belegunlink` event and your code decides what
  detaching means — the pattern `gk:rowaction` already uses. The failure path
  now reports instead of vanishing.
  **If you relied on the old path, set it once:**
  `GK.belegModal.unlinkUrl = "/faktura/api/beleg/unlink";`

- **The tooltip was silently dead whenever the script loaded late.**
  `GK.tip` and `GK.tooltip` bootstrapped through a bare
  `addEventListener("DOMContentLoaded", …)`, which never fires for a script
  loaded with `async`, injected after load, or arriving inside an AJAX
  fragment. The main bootstrap has always guarded this with a `readyState`
  check; these two never did. Verified in a browser both ways on the same page:
  with `async`, the old code left `title` untouched on hover while the rest of
  `GK` worked perfectly — which is exactly why it went unnoticed.

- **`data-gk-multiple` was written by `Form.php` and read by nobody.** The
  `<input>` carries the native `multiple` attribute, so the file *picker*
  respected the limit — but a drop never goes through the input, and the drop
  handler read every constraint except this one. Five files dropped on a
  single-file field were all accepted. Drag & drop is on the README's feature
  list.

- **"Too large" was the one upload error not translated.** Every other branch
  of the validator calls `_t()`; the most common failure of all concatenated
  English by hand, so a German user rejecting a 20 MB photo got
  *"foto.jpg: too large (20 MB, max. 5MB)"* between two German messages. Added
  `js.too_large` and `js.one_file_only` to both locales.

### Changed

- **`GK.search`'s default endpoint was `/api/suche`.** 1.39.0 anglicised the
  response contract and the demo endpoint but left the default URL — so the
  documented English library still shipped a German route as the address it
  calls when you configure nothing. Now `/api/search`.

- **The last German identifiers in the JavaScript are gone** — `_tOderText`,
  `kombi`, `taste`, `ersatz`, and a source comment that still documented the
  German response shape as *the* contract nine hundred lines after 1.39.0
  documented the English one. The compat reads (`d.groups || d.gruppen`) and
  the `gk-search-treffer` class names stay: both are contracts with code that
  already exists.

- **`GRIDKIT_SKILL.md` was stamped `1.28.0`** while `VERSION` said `1.40.0` —
  twelve releases of drift in the file an agent reads to learn what version it
  is working against. Its German example strings are gone too: an agent that
  copies `GK.toast.success('Gespeichert!')` writes German into an English
  application.

- **The author's own application paths are out of the doc comments.**
  `/faktura/expenses`, `/faktura/invoices` and friends appeared as examples in
  five classes. They now read as a library's examples rather than one site's.

### Added

- `tests/js.test.php` — 170 assertions across the seams where these files drift
  apart silently. The general forms, not the specific bugs: every `GK.x.y()`
  the skill document shows must exist in `gridkit.js`; every `_t("key")` the
  JavaScript asks for must exist in both locales; every behavioural `data-gk-*`
  the PHP writes must be read by something; no `fetch()` of a fixed application
  path. Each was checked against its own reintroduced bug.

**1,272 assertions.**

---
## [1.40.0] - 2026-08-26

The stylesheet — 6,765 lines, the last large file without a systematic pass.

The method was to compare what the code *emits* against what the stylesheet
*defines*, in both directions, and then to measure every button variant rather
than the handful the earlier contrast probe happened to include.

### Fixed

- **`.gk-spin` never spun.** `Form.php` puts it on a Material Icons `sync`
  glyph — for the AJAX select's loading line and the upload indicator. The
  `@keyframes gk-spin` existed (twice, identically) and nothing ever applied
  them to the class, so both "loading" icons sat perfectly still. A spinner that
  does not move reads as a hang.
- **A live table gave no sign it was working.** `GK.liveTable` adds
  `.gk-live-loading` around its fetch and removes it after; the stylesheet had
  no rule for it. Live tables now get the same loading bar and receding content
  as the plain table.
- **Text and outlined buttons in the semantic colours failed contrast** —
  measured, not guessed: success 2.54:1, warning 3.19:1, danger 3.67:1 on white.
  `--gk-warning-text` was introduced precisely to fix this and only reached
  3.19:1; `--gk-success-text` was defined and **never used anywhere**. The
  earlier contrast sweep missed all of this because its probe only carried
  *filled* buttons. All three text roles are text-safe now (5.02, 5.48, 5.67:1)
  and the outlined and text variants use them.
- **The colour derivation ran in dark mode too**, where the role colours are
  already the light end of the scale — darkening them by the same step landed
  them mid-range, the worst place against a dark ground: 3.45–3.97:1. Dark mode
  keeps its own literals now, which read at 7.6–8.8:1.
- **Five themes set `--gk-secondary` without `--gk-on-secondary`.** In light
  mode the base white paired with their grey by luck. In dark mode the base
  block had already set a *dark* on-secondary to pair with its own *light*
  secondary — so a themed dark page put dark text on mid grey at 2.73:1. Each
  theme now sets both halves of the pair.
- `@keyframes gk-spin` was defined twice, identically.

### Verified

240 combinations — 20 button variants across six themes and both modes — every
one at or above 4.5:1, worst case 4.61:1.

### Added

- Five more checks in `tests/contrast.test.php`, including one that counts
  `--gk-secondary` against `--gk-on-secondary` in `themes.css`, so a role can no
  longer be overridden without its text colour. 1102 assertions in total.

### Not changed

Around twenty other places use a role colour directly as a text colour —
validation hints, pill text, status icons. Most sit on tinted backgrounds where
the role colour is right, and each needs looking at on its own; they are
recorded here rather than swept.

---
## [1.39.1] - 2026-08-26

The landing page — the second thing a visitor sees.

### Fixed

- **The product screenshot had been a 404 since 1.39.0's predecessor.** When the
  screenshots were renamed from German filenames in 1.30.0, every reference was
  updated except this one, which still asked for
  `docs/screenshots/tabelle-hell.png`. The comment above it reads *"a UI library
  has to show what it looks like"*.
- **Both images declared the wrong size** — `2800×1760` for a `1400×900` file.
  A wrong intrinsic size reserves the wrong box, so the page jumps when the
  image arrives.
- **The page disagreed with itself about how many components there are.** Its
  hero stat says 16; the meta description, the `og:` and `twitter:` tags and two
  body sentences said 12. `src/` holds 21 classes — 16 components plus five
  infrastructure ones. 16 everywhere now.
- **The name was spelled `GRIDKit`** in `<title>`, `og:title`, `og:site_name`
  and `twitter:title` — precisely the strings a search result and a shared link
  display — and in nine other files. It is GridKit.
- **The skill table was unreadable on a phone.** A fixed 140 px label column
  left the value about 190 px at a 390 px viewport, and `.skill-table` clips, so
  the right half of every row was cut off. Rows stack below 768 px now.

### Added

- `tests/landing.test.php` — every referenced image must exist, declared
  dimensions must match the files, the component count must agree with `src/`,
  and the name must be spelled one way. 1088 assertions in total.

---
## [1.39.0] - 2026-08-26

The demo — the project's most-visited page, and the first thing anyone tries.

### Fixed

- **`GK.search` required German JSON keys from your endpoint.** An
  English-facing library asking an English-speaking developer to return
  `gruppen`, `titel`, `treffer`, `untertitel` and `betrag`. The documented shape
  is English now — `groups`, `title`, `items`, `subtitle`, `amount` — and the
  German names are still accepted, so an endpoint written against the old
  contract keeps working. The widget's own internals were German throughout
  (`liste`, `treffer`, `aktiv`, `zeige`, `hervor`, `markiere`); they are not any
  more.
- **A whole demo section had no navigation entry.** Eleven sections, ten links:
  the Search section was unreachable unless you knew the anchor. It is the only
  place `GK.search` is shown at all, and `GK.search` was not in the agent skill
  either. Both fixed.
- **The demo's search endpoint needed `mbstring`**, which GridKit documents as
  optional — `mb_strtolower` and `mb_strpos`, unguarded. It fataled on a host
  without the extension.
- **The demo loaded its images from picsum.photos and i.pravatar.cc.** A shop
  window for "no dependencies" that breaks when a third party is slow, and that
  sends every visitor's IP to two of them. 23 placeholders are generated as
  inline SVG now — no requests, no files. The only external request left is the
  Material Icons font, which is documented.
- The GK.tip card was German end to end — heading, description and every
  example label. So were four `GK.modal.open('Titel', …)` samples, the gallery
  markup sample, and the Search section's own heading.

### Added

- `GK.search` documented in `GRIDKIT_SKILL.md`, with the response contract.
- `tests/demo.test.php` — including a check that no demo section is left without
  a link, and that no third-party image host appears in the markup.
  1063 assertions in total.

---
## [1.38.0] - 2026-08-26

"Themes, dark mode" is in the README's opening sentence. Nothing had ever
measured either. A probe page carrying every component, driven through six
themes in both modes, failed in **twelve of twelve** combinations.

### Fixed

- **The client threw away the theme the server set.** `GK.theme.restore()`
  wrote `theme || ""` and `mode || ""`, so an empty `localStorage` — every first
  visit, every private window, every user who had never picked a theme — blanked
  both attributes. `Theme::set('indigo', 'dark')` in PHP was therefore ignored
  for anyone who had not been to the site before: it opened in light mode. A
  stored preference still wins; nothing stored now leaves the server's choice
  alone. This is why the probe measured light-mode surfaces on a page that had
  asked for dark.
- **The active pagination button was white on white in dark mode**, 1.78–1.97:1
  across every theme. `color: #fff` was hardcoded beside
  `background: var(--gk-primary)` — and in dark mode the primary is the *light*
  end of the scale. The role colour flips with the mode; the literal did not.
  The same mistake sat in a second rule, in the dark-mode block itself.
- **Filled `success`, `warning` and `danger` buttons failed in light mode** —
  2.54:1, 2.15:1 and 3.67:1 with white text. The role colours are right for
  pills, borders and icons and are unchanged; the buttons now take
  `--gk-success-fill` (5.48:1), `--gk-warning-fill` (5.02:1) and
  `--gk-danger-fill` (4.83:1).
- **`--gk-outline` was being used as a text colour** by the breadcrumb separator
  and the select arrow: 1.45:1 on white, 1.93:1 on the dark surface. It is a
  1px-line colour. They take `--gk-on-surface-variant` now.
- **The sidebar's muted text** measured 2.27:1 in light and 3.40:1 in dark —
  group labels, the version and the collapse label all under half of what they
  needed. Now 5.35:1 and 5.48:1.
- **Text and outlined primary buttons** measured 3.97:1 in forest, 4.20:1 in
  ocean and 4.41:1 in slate. `--gk-primary` is pinned so that white carries *on*
  it; as text on white the same colour is the inverse case and needs its own
  value, exactly as `--gk-warning-text` and `--gk-success-text` already did.
  Added `--gk-primary-text`.

### Changed

- **`--gk-l-primary`: 0.55 → 0.53.** At 0.55, white on forest's accent measured
  4.35:1 — under the 4.5:1 AA asks of body text, and "4.3:1" was documented in
  the README as the floor. 0.53 clears it on all six with room: forest 4.69,
  ocean 4.98, slate 5.28, indigo 5.51, amber 5.52, rose 5.73. The difference is
  not visible side by side; the README's claim is now stronger and true.
- `docs/screenshots/themes.png` retaken.

### Verified

Twelve of twelve theme/mode combinations pass, measured element by element:
every text node's colour composited against its real backdrop through the
ancestor chain, 4.5:1 for text and 3:1 for icons. The measurement itself needed
two corrections along the way — `getComputedStyle` returns `oklch()` verbatim,
which no `rgb()` regex matches, and colour transitions have to be switched off
or you read a value mid-animation.

### Added

- `tests/contrast.test.php` — pins each value the measurement settled on.
  1033 assertions in total.

---
## [1.37.0] - 2026-08-26

"A mobile layout you didn't have to think about" is the third line of the
README. Nothing had ever opened a page at 390 px.

### Fixed

- **Every table card was 540 px wide on a 390 px screen.** Two rules collided.
  `.gk-table-wrap .gk-table { min-width: 540px }` keeps columns readable while
  the wrapper scrolls sideways — the point of the scroll mode — and it applied
  to card mode as well, so the values sat off-screen behind a horizontal
  scrollbar. The sideways scrolling that card mode exists to avoid was back.
  The minimum now applies to `:not(.gk-table-mobile-card)`.
- **Card cells kept the column widths meant for the table.** `Table` writes
  `width`, `min-width` and `max-width` as inline styles on every `<td>`. As a
  card row, a column declared `'width' => '140px'` squeezed its label and its
  value into a strip — on the invoice example "Invoice no." overlapped
  "INV-2026-001". Card mode resets all three; an inline style can only be
  reached with `!important`, which is why it appears here.
- **Row actions stacked into a column.** The generic mobile rule turns any
  `.gk-btn-group` vertical, which suits a segmented group of labelled buttons.
  On a table row it made two icons a 51 px tall column inside every card.
- **Row action buttons were 26 × 25 px** — under every touch-target guideline.
  At least 40 × 40 on touch layouts now.
- **The header pushed its own controls off the screen.** `.gk-header > *` is
  `flex-shrink: 0` by design, so whatever does not fit leaves the viewport
  rather than wrapping — silently, because nothing scrolls to bring it back. On
  a 390 px phone the New button, the theme switcher and the light/dark toggle
  all sat beyond the right edge, at x = 512. The right-hand group may shrink
  now, and the six accent swatches — a preference set once — step aside on
  phones. The light/dark toggle, used daily, stays.
- The invoice example's own toolbar could not wrap, so the page scrolled
  sideways at 390 px. A showcase should not do that.

### Verified

At 390 × 844: no horizontal page scroll, the sidebar slides off and the burger
brings it back, the table reads as one card per row with label and value on one
line, actions sit side by side at 40 px, and every header control is inside the
viewport.

### Added

- `tests/mobile.test.php` — each assertion stands for one of the defects above.
  1013 assertions in total.

---
## [1.36.0] - 2026-08-26

The components that are neither `Table` nor `Form`, driven in a browser.

### Fixed

- **The wrapper a sidebar layout needs was documented nowhere.** A sidebar is
  `position: fixed`, so the content beside it belongs inside
  `<div class="gk-with-sidebar">`. That class appeared only inside
  `skeleton.php` — not in the skill, not in the README. Leave it out and the
  whole page renders *underneath* the sidebar: nothing errors, nothing warns,
  the left 260 pixels of every component are simply covered. I built a probe
  page from the documentation and hit exactly that. The **Page skeleton**
  section now shows the sidebar layout with both wrappers.
- **`YearFilter::allOption()` did nothing in chip mode**, which is the default.
  It was honoured in `dropdown` mode only — but it still moved the default year
  to its value, so no chip was active and there was no control to get back to
  "all years". The filter sat in a state the interface could neither show nor
  leave.
- `YearFilter` produced `??year=2026` when given `baseUrl('?')`, which is a
  reasonable way to write "this page".

### Changed

- **`skeleton.php` rewritten in English.** The README tells people to copy this
  file as their starting point and it ships inside the Composer package — and
  it was German throughout, down to the variable names and array keys
  (`$artikel`, `'nr'`, `'preis'`), with `<html lang="de">` and asset paths
  hardcoded to `/gridkit/…`. It now sets the locale from the query string,
  names the three ways to feed a table, and its paths are relative to where it
  actually sits.

### Verified

Sidebar (collapse moves the content from 260 px to 60 px), Header, Select,
FilterChips, YearFilter, TableHeader, PageSize, Pagination, ActionGroup, Icon,
Modal, `GK.confirm`, `BelegModal` (opens, loads its iframe, closes on Esc) and
the theme switcher (which carries through to `--gk-primary`) were each exercised
in a browser rather than only rendered.

### Added

- `tests/components.test.php` — 41 assertions, including that the starter file
  stays English and keeps its layout wrappers. 1001 assertions in total.

---
## [1.35.1] - 2026-08-26

### Fixed

- **The login page loaded its stylesheet from a path that does not exist.**
  The default was the literal `gridkit/css`, which is right for exactly one
  directory layout. From `/demo/login.php` it resolved to
  `/demo/gridkit/css/gridkit.css` — a 404 — so the project's own login page
  rendered unstyled. `Auth` now works the path out from `DOCUMENT_ROOT` and
  keeps it absolute, because the page may sit in a subdirectory where a
  relative `css/` would point at the wrong place. `cssPath` and `jsPath` still
  override it, and a page outside the document root falls back to relative.

---
## [1.35.0] - 2026-08-26

`GridKit\Auth` — sessions, passwords and a remember-me cookie — had never been
touched by a test. Three things were wrong at once, and two of them were
security problems.

### Security

- **`renderLogin()` built its markup with unquoted attributes.**
  `htmlspecialchars()` escapes quotes but it does not escape spaces, so any
  value containing one broke out of its attribute and became new ones:

  ```
  <form method=post action=x onfocus=alert(1) autofocus>
  ```

  `renderLogin(['action' => $_SERVER['REQUEST_URI']])` is an ordinary thing to
  write, and `REQUEST_URI` is whatever the visitor typed. `title` and `icon`
  were injectable the same way. Every attribute is quoted now, and a test walks
  the rendered document asserting it.

- **An unknown username cost nothing to reject.** `verify()` returned before
  hashing when no line matched, so a login attempt took ~234 ms against an
  existing account and ~0 ms against one that did not exist. Every username on
  the system was readable from outside with a stopwatch. A verification now
  always runs, against a dummy hash of the same cost when the name is unknown —
  measured afterwards at 236 ms against 227 ms, and what is left of that comes
  from reading the file, not from the crypto.

### Fixed

- **Every label on the login form was invisible.** They sat in a heredoc as
  `<?= Lang::t('auth.username') ?>`, and a heredoc does not evaluate PHP — so
  those tags went to the browser as text. The `auth.*` translations existed all
  along and were never reached. This also explains the demo's login page
  answering **403**: the response contained literal `<?=` tags, which reads to a
  web application firewall like leaked source. It answers 200 now.
- `data-gk-layout` rendered as the literal string
  `data-gk-layout= . Layout::getMode() . ` — the concatenation was inside the
  quotes.
- The stylesheet and script paths were hardcoded to `gridkit/css/...`, which
  only worked for one directory layout. `cssPath` and `jsPath` are options now,
  and so are `subtitle` and `footer`.
- `demo/login.php` was German and keeps its user file beside the code. It is
  English now, its message goes through `Lang::t()`, and `demo/.htaccess`
  denies `*.conf` so a clone does not serve bcrypt hashes to anyone who asks.

### Added

- `tests/auth.test.php` — 56 assertions. Both security fixes were verified
  against deliberately reintroduced regressions: the timing test fails at
  "known 233.1 ms, unknown 0.1 ms", and the escaping test fails the moment a
  quote is removed. 955 assertions in total.

---
## [1.34.0] - 2026-08-26

Every form field type, rendered and then actually used in a browser.

### Fixed

- **`'searchable-select'` was documented and does not exist.** `Form` handles
  eleven types itself and passes everything else through as
  `<input type="…">` — which is deliberate, because it covers `month`, `week`
  and whatever HTML adds next. It also meant the documented type rendered
  `<input type="searchable-select">`, which every browser shows as a plain text
  box, without a word of complaint. The searchable select is plain `'select'`.
  An unknown type now raises an `E_USER_WARNING` naming the type and listing the
  real ones; valid HTML types still pass in silence.
- **Four types were implemented and undocumented**: `multiselect`,
  `ajaxselect`, `color`, `range`.
- **The rich text editor was pinned to German** for every user —
  `language:'de'`, hardcoded. That put `lang="de"` around English content,
  which is wrong for a screen reader and wrong for the toolbar as soon as a
  translation for that locale is loaded. It follows `Lang::locale()` now, and a
  field may pin its own with `['language' => 'fr']`.
- **`->field($name, $label, 'hidden')` raised a PHP warning.** It reaches the
  same branch as `->hidden()`, which always sets a value, and read a key that
  was never there. On a host with `display_errors` on, that warning lands in
  the page.
- The skill's `Form` example was still German — `Vorname`, `Nachname`, `Rolle`,
  `Aktiv`, `Speichern`.

### Verified

All eleven types were rendered in a browser and exercised: the searchable
select carries its value, multiselect shows removable chips, the toggle and
colour swatch reflect their state, radio marks the selected option, the range
shows a live value, the file field takes a drop, and the rich text editor
starts on scroll, loads its content and keeps the hidden input in step.

### Added

- `tests/form.test.php` — 47 assertions across every type, including that each
  one renders the `data-gk-error` slot the AJAX handler writes into. A field
  without one swallows its server-side error message. 891 assertions in total.

---
## [1.33.0] - 2026-08-26

Running the agent skill's own 22 examples. It is the file GridKit is advertised
on — one document that teaches an assistant the whole API — and nothing had
ever executed the code in it.

### Fixed

- **`'color' => 'danger'` on a row button did nothing.** It appears in the
  skill's own Table example, on the delete button. `Table` read `'class'` and
  only `'class'`, so the button rendered grey. Every other component in GridKit
  — `Button::render()`, `ActionGroup` items, `StatCards` — names this option
  `color`; the row button was the odd one out. Both names work now.
- **The skill's only example of the `use` statements sat inside an SSI Panel
  view**, complete with `$this->layout('layouts/panel')` and `$this->start()`.
  An assistant copying the one place the imports appear produced code that
  needs a template engine which exists in a single private codebase — and the
  list covered 5 of 21 classes. There is now a standalone **Page skeleton**
  section: a complete page in plain PHP, the full import list, and a pointer to
  `examples/invoices/`. The SSI Panel variant is kept, below, as what it is.
- **Nine more German lines in the skill** — `Titel`, `Inhalt`, `Ausgaben`,
  `Geschwister`, `seit`, and the AJAX navigation notes. The previous sweep
  searched for umlauts, which none of these have.
- The Table section put a runnable example and a MySQL-only one in the same
  code fence, so neither could be checked on its own. Split, and the
  placeholder `$addBtn` replaced with the call it stands for.

### Added

- **`tests/skill.test.php`** — every runnable example in `GRIDKIT_SKILL.md` is
  executed in its own process, with each PHP diagnostic promoted to a failure.
  14 run; the 8 that cannot are listed by name and reason rather than skipped
  quietly. Verified against a deliberately broken example.
- 849 assertions in total.

---
## [1.32.0] - 2026-08-26

Running the README's own example, and then using the table without a mouse.

### Fixed

- **`'confirm' => true` did nothing.** It appears in the README's headline
  example, on the delete button, and was read by no code at all — so that
  button deleted without asking. Worse, having neither `onclick` nor `modal` it
  did not delete either: it rendered, and clicking it was silent. Both halves
  are now real:
  - `confirm` gates the button. `true` takes the translated default message, a
    string is used as-is. An `onclick` is wrapped so it runs only on
    confirmation — an inline handler fires before any delegated listener could
    stop it, so wrapping is the only way. A `modal` opens only after.
  - A button with neither `modal` nor `onclick` fires **`gk:rowaction`** on the
    table, carrying `{ action, params, tableId }` — the same shape
    `gk:bulkdelete` already used. The application decides what deleting means.
- **The table could not be sorted without a mouse.** Sortable headers were
  plain `<th>` elements with a click handler: not in the tab order, nothing
  announcing them as controls, nothing reporting the direction. They now carry
  `tabindex="0"`, `role="button"` and `aria-sort`, and answer to Enter and
  Space. Space no longer scrolls the page instead.
- **The search box and the filter dropdown had no accessible name.** A
  placeholder is not one — it is not reliably announced and it disappears as
  soon as anything is typed. The search box is labelled from the translation;
  the filter takes the column's own label (`'label' => …` overrides it), so a
  screen reader says "Status" rather than nothing.

### Added

- `tests/a11y.test.php`, and tests for `confirm` — 819 assertions in total.

---
## [1.31.0] - 2026-08-26

An example application, and the four defects it uncovered on the way.

The demo has always been a component gallery: it shows what exists, not what it
is for. Building a real application against GridKit turned out to be the best
test the framework has had — every one of the fixes below was found by trying to
use the documented feature and watching it not work.

### Added

- **[`examples/invoices/`](examples/invoices/) — a complete CRUD application.**
  List, create, edit, delete; search, filter, sort and paging answered by the
  server over AJAX; English and German. About 300 lines across five files, with
  no database and no build step — `php -S localhost:8000` and it runs.
- **`Table::rows($page, $total)`** — the missing third data source. `query()`
  speaks mysqli and nothing else, and `setData()` hands the whole set to the
  browser. Anyone on PDO, SQLite, Postgres or an HTTP API had no way to use the
  server-side table at all. `rows()` takes one page plus the total and keeps the
  table server-driven.
- **`Table::isAjaxReload($id)`** — names the contract that was previously
  invisible. A table reload injects the response body straight into the table's
  wrapper, so the page has to end the request after rendering the fragment.
  Nothing said so, and nothing enforced it: following the README's `query()`
  example gave you a table that swallowed the whole page on the first keystroke.
- **Status labels can carry their own text.** `'labels' => ['paid' => ['color'
  => 'green', 'text' => 'bezahlt']]` — the stored value stays `paid`, the cell
  reads what the locale calls it. Previously `labels` mapped to a colour only
  and the cell always showed the raw value, so a status column could not be
  translated at all. The old colour-only form still works.

### Fixed

- **A filter dropdown never reached the query.** `->filter('status', …)` on a
  `query()` table rendered the dropdown, put `gk_filter_status` in the URL, and
  was then ignored — the list simply did not change. Filters now become bound
  `AND` clauses alongside the search. Static tables were unaffected, which is
  why it went unnoticed: the demo has no server-side table.
- **The filter dropdown lost its value on a full page load.** A shared link or a
  plain reload showed "All" while the table below it was still filtered.
- **The built-in pager printed every page.** 10,000 rows at 25 a page meant 400
  buttons in the DOM, rebuilt on every reload — at exactly the size the
  server-side path exists for. Now windowed, like `Pagination` already was.
- **A row button did not carry its row's id** unless you remembered
  `'params' => ['id' => 'id']`, and forgetting it failed silently: the edit
  modal opened as if it were a new record. The id is sent by default.
- **`StatCards` formatted numbers in German** regardless of locale, so a card
  and the column beneath it disagreed. Same locale keys as `Table` now.
- The demo's modal forms were German — reachable from the English demo.

### Changed

- `GRIDKIT_SKILL.md`: the last 42 German lines translated, and the new APIs
  documented. `Table.php`'s WHERE building was extracted into `buildWhere()` so
  the filter path can be checked without a live database.
- 9 more tests (`tests/ajax.test.php`), 800 assertions in total.

---
## [1.30.0] - 2026-08-26

The last places where GridKit assumed its user was German, and a shop window
that finally shows what an English visitor would actually see.

### Fixed

- **Currency, dates and numbers ignored the locale.** `['format' => 'currency']`
  emitted `1.200,00 €` for everyone; `['format' => 'date']` emitted `d.m.Y`;
  `['format' => 'number']` grouped with German separators. An English page
  therefore showed German money and a date format that an American reader would
  misread by ten months. All four now resolve through `Lang::t()`:
  - `format.currency` — `€{value}` (en) / `{value} €` (de)
  - `format.date` — `M j, Y` (en) / `d.m.Y` (de). The English format is
    deliberately not `m/d/Y`: `03/09` is genuinely ambiguous across the
    Atlantic, `Mar 9` is not.
  - `format.datetime`, `format.decimal`, `format.thousands`
- A column can override either with `['currency' => '${value}']` or
  `['dateFormat' => 'Y-m-d']`, so a non-EUR project is one option away.
- Two more `number_format(…, ',', '.')` calls in `Table` that the previous pass
  had missed, and the German identifiers `$leer` / `$stellen`.

### Changed

- **The demo's sample data now follows the locale.** The English demo used to
  list `Webdesign Paket S` and `SEO Beratung` at `1.200,00 €`. This is the
  first thing a visitor sees, and it said "German-market tool" before the
  README got a word in.
- **All README screenshots retaken in English**, and renamed from
  `tabelle-hell.png` / `formular.png` to `table-light.png` / `form.png`.
  The theme comparison was rebuilt from real GridKit markup rather than
  hand-drawn, so it now shows what the six themes actually render — including
  that the status colours stay constant across all six, which is the point of
  keeping semantic roles out of the theme blocks.
- **Added `docs/social-preview.png`** (1280×640) for the repository's social
  card. Without one, every link posted to Reddit, Hacker News or X shows
  GitHub's grey default instead of the product.

### Added

- Tests for all of the above: both locales' currency, date and number output,
  and the per-column overrides.

---
## [1.29.0] - 2026-08-26

Making the repository something a stranger can actually rely on: a test suite,
CI, and the last of the German that was hiding where a search for translation
calls could not see it.

### Fixed

- **German shipped to English users through default parameter values.** PHP
  requires default parameters to be constant, so `Lang::t()` cannot appear in a
  signature. The workaround that had been used instead was to write the German
  text as the default — which renders for every user regardless of locale.
  Affected: `TableHeader::search()` (`'Suche…'`), `TableHeader::advanced()`
  (`'Erweiterte Filter'`), `TableHeader::reset()` (`'Filter zurücksetzen'`),
  `YearFilter::allOption()` (`'Alle Jahre'`), `PageSize::$label` (`'Zeilen'`),
  `Select::searchable()` (`'— Wählen —'`, `'Suchen…'`), and seven strings in
  `Pagination` (`'Einträge'`, `'Seite X von Y'`, and the five navigation
  titles). Defaults are now empty and resolve through `Lang::t()` at render
  time; an explicitly passed label still wins.
- **Thousands separators were German for everyone.** `number_format($n, 0, ',',
  '.')` was hardcoded in `Pagination` and `Table`, so an English page showed
  `1.234.567`. Now driven by `format.decimal` / `format.thousands`.
- **The reset button showed a translated tooltip over untranslated text** — the
  visible word was a hardcoded English `Reset`, the `title` attribute the German
  label. Both now come from the same key.
- **`SortLink` was missing from `GRIDKIT_SKILL.md`.** The skill file is the
  headline "agent-ready" feature; a component absent from it cannot be used by
  an agent. `Select` and `Icon` were missing from the component index too.
- **`composer.json` described `ext-mbstring` in German**, which Composer prints
  to every user installing the package.

### Added

- **A test suite** — `php tests/run.php`, 715 assertions across four files. No
  Composer and no PHPUnit: a suite that needed a package manager would
  contradict the zero-dependency promise. The runner is about sixty lines.
  - `lang` — catalogue parity, placeholder parity, and the check that matters:
    every component rendered under `en` and failed on a German word. This is
    what catches the default-parameter class of bug, which is invisible to a
    grep for `Lang::t`.
  - `render` — all 21 classes autoload and render, with every PHP notice,
    warning and deprecation promoted to a failure. `display_errors` is off on
    most production hosts, so a warning mid-render shows up as a silently
    truncated page rather than an error.
  - `escaping` — XSS payloads through cell data, column labels, search values,
    select options, form fields, breadcrumbs, stat cards and pagination URLs.
  - `package` — VERSION/changelog agreement, asset integrity, no remote
    `@import`, no German left in a public signature.
- **CI** (`.github/workflows/ci.yml`) — the suite on PHP 8.2, 8.3 and 8.4, plus
  a job with `mbstring` disabled, because "runs without mbstring" had been a
  claim in the README and nothing more. A second job asserts that `demo/`,
  `assets/` and `docs/` stay out of the Composer package and that it stays
  under 1.5 MB.
- `SECURITY.md`, `CODE_OF_CONDUCT.md` and a pull request template.
- `SortLink` reference section in `GRIDKIT_SKILL.md`.
- 14 translation keys in both catalogues (85 each, in parity).

### Changed

- **CKEditor moved from `vendor/` to `assets/`.** `vendor/` belongs to Composer;
  running `composer install` inside a GridKit clone wrote into the same
  directory. `/vendor/` is now gitignored.
- `skeleton.php` is no longer excluded from the Composer package — it is the
  shortest path from `composer require` to a page that renders.
- Documentation comments and the component index in `GRIDKIT_SKILL.md`
  translated to English.

---
## [1.28.0] - 2026-08-26

A design overhaul in three stages. The M3 role layer was already complete, but
roughly 480 hardcoded colour values overruled it. This release connects the two
and adds the four layers the system was missing: typography, motion, elevation,
focus.

The PHP API does not change. Existing class names and the legacy alias tokens
(`--gk-primary-500`, `--gk-neutral-*`, …) keep working.

### Fixed

- **The six themes had no effect in light mode.** Nine components were probed —
  filled, outlined, text and tonal buttons, table header, borders, search field,
  sidebar — and not one reacted to a theme switch, even though `--gk-primary`
  changed correctly. Switching indigo → forest now changes 488 properties across
  272 elements, measured on the demo page; it was 151 across 116.
- **Dark mode: every second table row was unreadable.** Contrast 1.16:1 against
  the 4.5:1 WCAG AA requires. The cause was a specificity mismatch — line 469
  painted the zebra fill on `> td`, the dark-mode patch on `tr`, and the cell sat
  on top. Now 13.20:1.
- **White text on the primary surface carried 2.77:1 in the ocean theme**, well
  below the WCAG threshold. Across all six themes the range was 2.77–7.58:1; it
  is now 4.35–5.26:1 (7.57–7.79:1 in dark mode).
- **Success messages were purple in the forest theme.** `--gk-success` was wired
  to `--gk-tertiary`, and forest sets tertiary to `#8b5cf6`. Semantic roles no
  longer follow the theme accent.
- **An empty `data-gk-theme` collapsed the primary colour entirely.** No theme
  block matched, `--gk-hue` was undefined, `oklch()` became invalid and filled
  buttons rendered with no background at all. The hue now falls back to indigo.
- **The dark sidebar looked the same in every theme.** Seven rules forced it to
  `#010409` and overrode the per-theme values from `themes.css`.
- **`transition: all` is gone** (13 occurrences). It animates width, height and
  position too — the most common cause of layouts that jump when something opens.
- **`Table::renderLabel()` only knew German status words.** `active` and
  `inactive` both fell through to grey, so a status column's most important
  distinction carried no colour at all.
- **A failed AJAX reload was silent.** `reload()` had no `catch`; the table kept
  showing stale data. It now shows a message with a retry action and fires a
  `gk-table-error` event.
- **Icon sizes never took effect** where GridKit sets them with a single class.
  The Material Icons stylesheet is usually included after `gridkit.css` and sets
  `.material-icons { font-size: 24px }`; at equal specificity, order wins.
  Demonstrably affected: `.gk-sidebar-icon` (set to 20px, rendered at 24) and
  `.gk-select-arrow` (18 → 24). Twelve classes now win.
- **`mbstring` was a silent hard requirement.** `Header::render()` called
  `mb_strtoupper()` for avatar initials. The extension is optional and missing on
  lean PHP builds, where the page died with a fatal error mid-render.
- **Demo: the page scrolled sideways on a phone.** `repeat(3, 1fr)` without
  `minmax(0, …)` let a table inflate its grid track to 542px. Document width at a
  390px viewport: 1690px → 407px.

### Added

- **State roles.** `--gk-primary-hover` and siblings are derived from their role
  instead of being fixed values, plus state layers (`--gk-state-hover`,
  `--gk-state-primary`, …) and a focus ring as a token.
- **Role pairs for warning and info** (`--gk-warning-container`,
  `--gk-on-info-container`, …) which did not exist before, so labels and messages
  had to carry their own literals.
- **`--gk-warning-text` / `--gk-success-text`** — the same colour, darker. As a
  text colour on a light surface, `--gk-warning` breaks contrast (`#f59e0b` on
  white is 2.15:1).
- **Type scale:** seven roles from `--gk-text-display` to `--gk-text-overline`,
  plus three line heights.
- **A separate icon scale** (`--gk-icon-xs` … `--gk-icon-3xl`). Material Icons are
  sized through `font-size` but are not type; both scales previously shared one
  indistinguishable range of numbers.
- **Motion tokens:** three durations, two curves, two ready-made bundles
  (`--gk-transition-state`, `--gk-transition-move`).
- **Four elevation levels** (`--gk-elev-1` … `-4`), each two-layered; deeper in
  dark mode, where shadows barely carry.
- **A theme is one hue.** `themes.css` derives each theme from `--gk-theme-hue`;
  a seventh theme costs one line instead of twenty. The literals remain as an
  `@supports` fallback.
- **A real empty state.** Icon, statement, explanation — and a "Reset filters"
  action when a search or filter is active. The table works out by itself whether
  there is no data at all or only the current filter matches nothing. Configurable
  through `Table::emptyState()`.
- **Disabled states.** There was exactly one rule (`.gk-btn:disabled`); a disabled
  input looked editable and you found out by clicking. `readonly` stays
  deliberately distinct from `disabled`.
- **A visible loading state.** GridKit advertises "AJAX-first" and gave no
  feedback at all — content simply jumped. Rows now stay and recede, a bar shows
  the work, `aria-busy` says the same to screen readers.
- **`.gk-skeleton`** as a placeholder for first paint.
- **New column option `['muted' => true]`.** An article or document number is a
  reference, not content; at full text colour it competes with the description
  next to it.
- **`Layout::asset()`** appends the file's modification timestamp to CSS and JS
  paths. Without it, CDNs and browsers keep serving the old file after an update —
  a `themes.css` from March sat in the cache while the site already reported
  1.28.0, and the theme switcher looked broken as a result.
- **Pagination and the document modal are translatable.** `Pagination` shipped a
  German `aria-label`; `BelegModal` shipped eleven German strings. Both now go
  through `Lang`, in English and German.

### Refined

The visible half — the table:

- **Status pills carry meaning again**, each with a dot so the distinction also
  survives colour blindness and greyscale printing.
- **Row actions rest until the row is meant.** Deleting is the rarest action and
  the only irreversible one — and it was the only one wearing a signal colour on
  every row. Nothing is hidden, only desaturated, and it returns on hover or
  keyboard focus. Guarded by `@media (hover: hover)` so touch devices are not left
  with permanently greyed controls.
- **One separator system instead of three.** The zebra fill is gone
  (`.gk-table-striped` brings it back), so the hover surface carries alone and is
  legible for the first time. The active row gets an edge in primary.
- **Amounts in semibold tabular figures**, so the column reads as a block.
- **The table header lost its own fill.** Caps, size and weight already mark it as
  a header; the grey bar was a second statement for the same thing.
- **Form labels moved out of all-caps.** 13px sentence case, not 12px caps with
  letter-spacing. Caps stay where they carry: table headers and section marks.
- **The submit button is primary, not green.** Green is the success role — if
  everything is green, green means nothing.
- **Elevation is assigned, not just defined**: card on `--gk-elev-1`, dropdowns on
  `-2`, modal on `-4`. Dropdown and modal previously sat at the same height.

### Changed

Visible; worth a look before updating:

- **Theme colours shift.** The derivation fixes lightness at 0.55 and varies only
  the hue. Rose and amber become calmer, ocean and slate deeper. To keep the old
  values, remove the `@supports` block at the end of `themes.css`.
- **Tables have no zebra striping by default.** `gk-table-striped` restores it.
- **`prefers-reduced-motion`** covered two rules of the search overlay and now
  covers everything.
- **Focus rings are visible** rather than guessable at 10% opacity, and sit on
  `:focus-visible` throughout instead of `:focus`.
- **Source comments are in English.** The project is open source; German comments
  in the source were a barrier.

### Known gaps

- The dark-mode block is only partly cleaned up: 14 of 120 rules were provably
  inert and were removed, 71 colour literals remain there.
- Four font sizes (10, 15, 17, 36px) are still literals. Unifying them would move
  sizes visibly — a design decision, not cleanup.
- Skeleton placeholders exist as a class but no component sets them yet.
- Secondary rows (status *inactive*, say) do not yet recede in text colour; that
  requires the table to know a row's state, not just a cell's.

---
## [1.27.3] - 2026-08-26

### Behoben
- **Demo: „Form Density"-Karte klebte auf jeder Ansicht.** Das schließende
  `</div>` der Sektion `form` stand eine Karte zu früh, dadurch hing die
  Karte als Geschwister neben den Sektionen statt darin. `showSection()`
  blendet nur `.demo-section` aus — die Karte blieb deshalb auch bei
  `#changelog`, `#table` usw. sichtbar und schob den Inhalt nach unten.

---
## [1.27.2] - 2026-08-23

### Behoben
- **`Button` reicht das `form`-Attribut durch.** Ein Submit-Button außerhalb
  des Formulars (`'form' => 'form-id'`) war bisher stumm — die Option wurde
  verworfen, der Klick hatte keinerlei Wirkung.

## [1.27.1] - 2026-08-17

### Behoben
- **Tabellen-Zweitzeilen weiten die Spalte nicht mehr.** Neue Klasse `.gk-cell-sub`
  (Betreff, Konto, Nummer) kürzt mit Auslassungspunkten. Zellen in
  `.gk-table-scroll` dürfen schmaler werden als ihr Inhalt.

## [1.27.0] - 2026-08-17

### Neu
- **`Pagination` sitzt unter der Tabelle**, nicht in der Karte: `data-gk-pager`
  und optionales `live` / `pageSize`. PageSize (25/50/100) gehört in die
  Pager-Leiste, nicht in den Tabellen-Fuß.
- **`Pagination::build()` / `fromPaginatorHtml()`** — HTML für
  `<template data-gk-replace="[data-gk-pager=ID]">`, damit der Pager nach
  Live-Filter mitwechselt, obwohl er ausserhalb des Live-Containers steht.
- **`GK.liveTable.hoistPager`** hebt einen Pager, der noch im Live-Container
  steckt, hinter `.gk-table-wrap`. Klicks auf den gehobenen Pager laufen
  weiter per AJAX (`data-gk-live-pager`).

### Geändert
- Eine Seite ohne Blättern gibt einen versteckten Platzhalter aus — ein
  Live-Replace kann den vorherigen Pager so zuverlässig entfernen.

## [1.26.1] - 2026-08-17

### Behoben
- **Content-Spalte neben der Sidebar** kann wieder schmaler werden als der
  Inhalt (`min-width: 0`). Eine breite Tabelle (z. B. Revolut-Buchungen)
  läuft nicht mehr bis an den Fensterrand.
- Mehrfachauswahl-Cursor gilt für jedes `[data-gk-selectable]`, nicht nur
  für `.gk-table-wrap`.

## [1.26.0] - 2026-08-17

### Neu
- **`gk:selectionchange`** — nach jeder Änderung der Mehrfachauswahl
  (`ids`, `tableId`, `count`). Seiten können eigene Bulk-Aktionen
  (Umbuchen, Bestätigen …) an die GRIDKit-Auswahl hängen, ohne eigene
  Checkbox-Logik.
- **Shift-Klick** wählt den Bereich zwischen zwei Zeilen.
- Nach `gk-live-reloaded` wird `GK.table.init` erneut aufgerufen, damit
  eine per AJAX getauschte Tabelle wieder auswählbar ist.

## [1.25.1] - 2026-08-17

### Behoben
- **Mehrfachauswahl: „Alle“ gilt nur für sichtbare Zeilen.** Filtert eine
  Tabelle lokal (Suche/Status), wählt die Kopf-Checkbox nicht mehr die
  ausgeblendeten Zeilen mit aus.

## [1.25.0] - 2026-08-17

### Neu
- **`Table::groupBy($spalte, $labels)`** — Gruppenzeilen, sobald sich der
  Wert ändert. Die Zeilen müssen nach dieser Spalte sortiert ankommen.
- **Spaltenformat `number`** — ganze Zahlen, rechtsbündig, tabellarisch.
  0 und leer werden zum Gedankenstrich (`blankZero` abschaltbar, `decimals`
  für Nachkommastellen).
- **Tabellen-Knopf `onclick`** — Roh-JS mit `{feld}` aus der Zeile,
  z. B. `'onclick' => 'oeffnen({id})'`.

## [1.24.3] - 2026-07-31

### Behoben
- **Farbprofil war browserweit statt benutzerbezogen.** `GK.theme` legte Farbe und
  Hell/Dunkel unter den festen Schlüsseln `gk-theme`/`gk-mode` im localStorage ab.
  Wer sich am selben Rechner als jemand anderes anmeldete, erbte die Einstellung
  des vorigen Benutzers. Neu: `GK.theme.init({ scope: 'u42' })` legt einen
  Namensraum an (`gk-theme:u42`). Ohne `scope` bleibt das Verhalten unverändert.
  `restore()` setzt jetzt ausserdem zurück, wenn im Namensraum nichts gespeichert
  ist — sonst bliebe die zuletzt gesetzte Farbe am `<body>` stehen.
  Der Namensraum kann auch über `window.GK_THEME_SCOPE` gesetzt werden (wie
  `window.GK_LANG`), weil GRIDKit das Profil beim Laden selbst wiederherstellt.

## [1.24.2] - 2026-07-31

### Behoben
- **`GK.search` zeigte Schlüssel statt Text.** `_t()` gibt bei fehlender Übersetzung
  den Schlüssel zurück, deshalb griff das Muster `_t(key) || "Ersatztext"` nie —
  im Overlay stand wörtlich `search_error`. Neuer Helfer `_tOderText(key, ersatz)`
  nimmt den Ersatztext, sobald keine echte Übersetzung vorliegt. Betrifft
  Platzhalter, Hinweis, Leermeldung und Fehlermeldung.

## [1.24.1] - 2026-07-30

### Behoben
- **`GK.search` startete nie.** `init()` brach mit `ReferenceError: _t is not defined`
  ab: Die Komponente steht unterhalb der IIFE, das private `_t` ist dort nicht
  erreichbar. Der Fehler trat vor dem Registrieren der Horcher auf — der Auslöser
  mit `data-gk-search` und das Tastenkürzel blieben beide wirkungslos, ohne dass
  im Bedienelement etwas darauf hindeutete.

### Neu
- **`GK.t(key, params)`** — die Übersetzungsfunktion ist jetzt öffentlich und damit
  auch für Komponenten ausserhalb der Kapsel nutzbar (tooltip, search, künftige).

## [1.24.0] - 2026-07-29

### Neu
- **Suche (`GK.search`)** — systemweite Schnellsuche als wiederverwendbares Bedienelement.
  GRIDKit liefert nur das Bedienelement; WAS gefunden wird, bestimmt das jeweilige
  System über die konfigurierte Adresse.

  ```js
  GK.search.init({ url: '/api/suche', hotkey: 'ctrl+k', minLength: 2 });
  ```

  Antwort des Servers:
  ```json
  { "gruppen": [ { "titel": "Buchungen",
                   "treffer": [ { "titel": "…", "untertitel": "…",
                                  "betrag": "8,86 €", "url": "/…", "icon": "receipt" } ] } ] }
  ```

  - Öffnet mit Strg+K / Cmd+K oder über ein Element mit `data-gk-search`
  - Tastatur: Pfeiltasten wählen, Enter öffnet, Escape schließt, Tab bleibt gefangen
  - Abfrage entprellt (200 ms); eine laufende Abfrage wird abgebrochen, damit keine
    veraltete Antwort die neuere überholt
  - Suchbegriff im Treffer hervorgehoben — die Hervorhebung arbeitet auf dem bereits
    escapten Text, nie auf dem rohen
  - Zustände: Hinweis, lädt, keine Treffer, Fehler
  - Barrierefrei: `role="combobox"`/`listbox`, `aria-activedescendant`, Fokus kehrt
    beim Schließen zum auslösenden Element zurück
  - Nutzt die vorhandene `.gk-search`-CSS weiter statt ein zweites Eingabefeld zu erfinden
  - Hell und dunkel; respektiert `prefers-reduced-motion`

## [1.23.0] - 2026-07-24
### Added
- **GK.tip — globales Titel-Tooltip**: Alle `title`-Attribute werden beim Hover
  automatisch zu gestylten GK-Popups aufgewertet (300 ms Delay, Position über dem
  Element mit Viewport-Clamp, `\n` = Zeilenumbruch, Ausblenden bei Scroll/Klick/
  Taste). Der `title` wandert beim ersten Hover nach `data-gk-tip` (unterdrückt
  das native Browser-Popup). Opt-out per `data-gk-tip-off` am Element oder Vorfahren.
### Changed
- CSS-only Button-Tooltips (`.gk-btn[title]::after/::before`) entfernt — GK.tip
  übernimmt einheitlich; kein Doppel-Tooltip mehr.

---
## [1.22.17] - 2026-07-22

### Fixed
- **Endlose Ladeleiste nach Anker-Klick** — ein Hash-Sprung feuert `popstate`; der
  Handler lud daraufhin die ganze Seite per AJAX nach. Jetzt merkt sich `GK.navigate`
  pathname+search (`_lastPath`) und ignoriert popstate-Ereignisse, die nur das
  Fragment ändern. Folgefix zu 1.22.16.

---
## [1.22.16] - 2026-07-22

### Fixed
- **Sidebar-Anker-Links** — `GK.navigate` fing auch Nav-Links mit Fragment auf die
  aktuelle Seite ab (z.B. `/faktura/steuerberater#chat`): AJAX-Reload statt Scroll,
  Fragment verworfen → „Klick ohne Wirkung". Same-Page-Anker werden jetzt nativ
  vom Browser gescrollt; bei Seitenwechseln MIT Fragment scrollt `_render` nach
  dem Content-Swap zum Ziel-Anker statt an den Seitenanfang.

---
## [1.22.15] - 2026-06-19

### Fixed
- **BelegModal:** `.gk-beleg-modal-frame` bekommt `background:#fff`. HTML-Belege (z.B. PayPal-Quittungen)
  haben oft einen schmalen, transparenten Body — dahinter schlug bisher der graue `--gk-surface-container`
  durch. Weißer iframe-Hintergrund lässt HTML- wie PDF-Belege sauber auf Weiß erscheinen.

---
## [1.22.14] - 2026-06-19

### Fixed
- **Label-Umbruch** — `.gk-label` hatte kein `white-space`; lange Labels (z.B.
  "Überfällig · 17 T.") brachen in schmalen Tabellenspalten zweizeilig um und verzerrten
  die Zeilenhöhe. Jetzt `white-space: nowrap` + `vertical-align: middle` → saubere,
  einzeilige Pills in allen SSI-Systemen.

---
## [1.22.13] - 2026-06-15

### Fixed
- **Rowpager-Abstand** — `.gk-rowpager` (Client-Pagination via `data-gk-rows`) sitzt als
  Geschwister unter `.gk-table-wrap` und hatte keinen horizontalen Innenabstand. Zähler
  „1–15 von 33" und die Blätter-Pfeile klebten dadurch direkt am Karten-/Containerrand.
  Jetzt `padding: 0 12px` (+ leicht angepasste vertikale Marge) für saubere Fußzeile.

---
## [1.22.12] - 2026-06-14

### Fixed
- **BelegModal z-index** — `.gk-beleg-modal-overlay` lag bei `z-index: 1000`, also
  UNTER Standard-Modals (`.gk-modal-overlay` = 9999). Wurde die PDF-/Beleg-Vorschau
  aus einem offenen Modal heraus geöffnet (z.B. Konto-Detail), lud das PDF unsichtbar
  HINTER dem Modal ("im Hintergrund geladen"). Jetzt `z-index: 10000` (über Modals,
  unter `.gk-confirm-overlay` 10001 und `.gk-lightbox` 10002). Betrifft alle SSI-Systeme,
  die BelegModal aus Modals heraus aufrufen.

---
## [1.22.11] - 2026-06-13

### Added
- **`gk-table-card`** — Titel + Tabelle als EINE geschlossene Karte. Wrappt eine
  `gk-table-title` (Karten-Header) + eine `gk-table-wrap` (Tabelle bündig darin);
  die Karte liefert Rahmen/Radius/Schatten, die Tabelle füllt sie randvoll (kein
  Doppelrahmen, kein verschwendetes Padding, saubere Ecken via overflow-clip).
  Für Multi-Tabellen-Seiten, deren Sektionen als zusammengehörige Karten
  erscheinen sollen. (overflow:hidden → keine eigenen JS-Dropdowns darin; native
  <select> unkritisch.)

---
## [1.22.10] - 2026-06-13

### Changed
- **Tabellen in Karten wieder mit RUNDEN Ecken** — einheitlich mit eigenständigen
  Tabellen. 1.22.6/1.22.7 hatten in-Karte-Tabellen eckig gemacht (radius 0), um
  Doppel-Rahmen/Eck-Schimmer zu vermeiden; seit dem Hintergrund-auf-Zelle-Fix
  (1.22.9) ist der Schimmer weg, daher können in-Karte-Tabellen wieder rund sein.
  Es bleibt nur `box-shadow:none` (die Karte liefert den Schatten). Damit haben ALLE
  Tabellen systemweit dieselbe runde Optik.

---
## [1.22.9] - 2026-06-13

### Fixed
- **Graue letzte Zeile: runde Ecken fehlten.** Zebra-/Hover-Hintergrund lag auf der
  Zeile (`tr`) — vom `border-radius` der Eck-Zellen NICHT beschnitten, daher ragte
  eine graue letzte Zeile als eckiger Block über die runden Wrap-Ecken (nur bei
  grauer letzter Zeile sichtbar). Hintergrund jetzt auf der Zelle (`td`).
- **Lose Linie unter Summenzeilen (tfoot).** Die Trennlinie wurde nur bei der
  letzten `tbody`-Zeile entfernt, nicht bei `tfoot` — so klebte eine 1px-Linie
  knapp über dem runden Wrap-Rand. Jetzt wird die letzte SICHTBARE Zeile (tbody
  ohne tfoot bzw. die tfoot-Zeile) linienfrei gestellt; bei tfoot bleibt die
  tbody-Trennlinie als Abgrenzung zur Summe erhalten.

---
## [1.22.8] - 2026-06-13

### Added
- **`gk-table-title` — Sektions-Titel über einer eigenständigen Tabelle.** Für
  Seiten mit mehreren Tabellen, die je eine Überschrift brauchen, ohne die Tabelle
  in eine zweite `gk-card` zu packen (das doppelte den Rahmen und verschwendete
  Padding). Flex-Titelzeile (Icon + h3 + Subtitle + rechtsbündige Aktionen via
  `gk-table-title-actions`), randlos; die `gk-table-wrap` bleibt die geschlossene
  Karte. `gk-table-section` als optionaler Abstandshalter zwischen gestapelten
  Sektionen.

---
## [1.22.7] - 2026-06-13

### Changed
- **Tabelle in Karte: dezenter eckiger Rahmen statt gar keinem.** 1.22.6 entfernte
  den Wrap-Rahmen komplett — die Tabelle wirkte dadurch etwas "verloren". Jetzt
  behält der in einer Karte liegende `gk-table-wrap` einen leichten, ECKIGEN
  Rahmen (radius 0): erdet die Tabelle, doppelt aber nicht die Karten-Rundung und
  erzeugt weiterhin keinen Eck-Schimmer (kein Radius-Mismatch).

---
## [1.22.6] - 2026-06-13

### Fixed
- **Tabelle in einer Karte: doppelter Innenrahmen + Eck-Schimmer entfernt.** Liegt
  ein `.gk-table-wrap` innerhalb einer `.gk-card`, liefert die Karte bereits
  Rahmen/Radius/Schatten — der Wrap zog bisher einen zweiten, der wie eine lose
  Linie am Tabellenrand wirkte. Zusätzlich entstand an den gerundeten Ecken ein
  1px-Schimmer (Radius-Mismatch 11/12 px bei `overflow:visible`), der nur auf
  grauem Tabellenkopf/Zebra-Streifen sichtbar war. In Karten ist der Wrap jetzt
  ein reiner Layout-Container (kein eigener Rahmen/Radius/Schatten); `overflow`
  bleibt unverändert, Toolbar-Dropdowns ragen weiterhin heraus.

---
## [1.22.5] - 2026-06-12

### Fixed
- **`GK.liveTable.reload()` akzeptiert jetzt Element ODER ID-String.** Mehrere Views
  riefen `reload('exp-live')` mit String auf → `container.dataset is undefined`
  (z.B. beim Bar-Verbuchen einer Ausgabe). Strings werden per getElementById
  aufgelöst; ohne auffindbaren Live-Container erfolgt ein sauberer Voll-Reload
  statt eines JS-Fehlers.

## [1.22.4] - 2026-06-11

### Fixed
- **Card-Header: Icon und Überschrift in einer Linie.** `.gk-card-header` ist jetzt
  Flex (align-items center, gap 10px); `h3` darin erbt die Header-Typo statt
  Browser-Defaults (eigene Margins/Größe), Material-Icons bekommen 20px/secondary.
- **Neu: `.gk-card-actions`** — Aktions-Buttons rechtsbündig im Card-Header
  (margin-left auto). Wurde in Views bereits verwendet, existierte aber nicht.

## [1.22.3] - 2026-06-11

### Added
- **Modal: `gk-modal-footer`** — offizielle Aktionsleiste am Modal-Ende (Padding 16/24,
  Border-Top, rechtsbündig). Bisher fehlte eine Footer-Komponente; Views nutzten
  `gk-form-actions` direkt im Modal — die hat kein Seiten-/Bottom-Padding (für den
  Einsatz IN Formularen gedacht), wodurch Buttons an der Modal-Kante klebten.

### Fixed
- Kompatibilitäts-Regel `.gk-modal > .gk-form-actions`: bestehende Modale mit
  `gk-form-actions` als Footer bekommen automatisch korrektes Padding (kein
  View-Umbau nötig).

## [1.22.2] - 2026-06-03 — FilterChips: 'Alle' setzt Param explizit (leer)
### Fixed
- Der Chip mit leerem Wert ('Alle') rendert jetzt `?param=` statt der nackten URL.
  Sonst wertete GK.liveTable.restoreSession den Klick als 'frischen Aufruf' und
  sprang auf den zuletzt gemerkten Filter zurück (Bug: 'Alle' springt auf 'Vorschläge').

---
## [1.22.1] - 2026-06-03 — Tabellen-Unterecken runden zuverlässig
### Fixed
- Untere Tabellen-Ecken (`tbody`/`tfoot` letzte Zeile) nutzen jetzt einen
  Nachfahren-Selektor statt `>` — die Rundung greift damit auch, wenn die Tabelle
  in einem Zwischen-Container (z.B. Live-/AJAX-Wrapper) statt direkt im
  `.gk-table-wrap` liegt.
- Neu: `tfoot tr:last-child` (z.B. Summenzeile) bekommt die unteren Eck-Radien,
  damit ein Summen-`tfoot` zum gerundeten `.gk-table-wrap` passt.

---
## [1.22.0] - 2026-06-03 — GridKit\Pagination: EINE Server-Pagination

Neue PHP-Komponente `GridKit\Pagination` rendert die gefensterte Server-Pagination
(« Erste · ‹ Zurück · 1 … aktuell±2 … letzte · Weiter › · Letzte » + Info) und nutzt
dieselben CSS-Klassen wie der Client-`GK.rowPager` (`.gk-rowpager`/`.gk-pg`) — kein
eigenes/inline CSS. Ersetzt alle bespoke Pager (.bl-pager/.ssi-pagination/.gk-page-btn/
.gk-pagination). `Pagination::render([...])` oder `Pagination::fromPaginator($p, [...])`.

---
## [1.21.1] - 2026-06-03 — RowPager filter-fähig

`GK.rowPager` akzeptiert jetzt `data-gk-search="#input"`: die Suche filtert die
Tabellen-Zeilen (Volltext) UND paginiert die Treffer — ersetzt bespoke
client-seitige Filter (bkrFilter/filterRows/bkqFilter/aud-search) durch EIN Muster.

---
## [1.21.0] - 2026-06-03 — Tabs + RowPager (Client-Pagination für bestehende Tabellen)

### `GK.tabs` — einfache Tab-Navigation
`<div data-gk-tabs>` mit `<div data-gk-tabpanel="key" data-gk-tab-title="…">`-Panels;
Nav-Buttons werden automatisch erzeugt, erstes Panel aktiv. Kein JS-Aufruf nötig
(Auto-Init via GK.init). CSS: `.gk-tabs-nav` / `.gk-tab`.

### `GK.rowPager` — Pagination für bereits gerenderte Tabellen
`<table data-gk-rows="25">` → Zeilen werden client-seitig seitenweise eingeblendet,
gefensterter Pager (1 … aktuell±2 … letzte) mit Info „1–25 von N". Keine Server-/
Daten-Änderung nötig — ideal für lange server-gerenderte Tabellen. CSS `.gk-rowpager`,
wiederverwendbare `.gk-pg`-Button-Klassen.

---
## [1.20.0] - 2026-06-03 — PageSize: Zeilen-pro-Seite-Dropdown

### Neue Komponente `GridKit\PageSize`

Wiederverwendbares Dropdown zur Wahl der Zeilenanzahl pro Seite (25 / 50 / 100 / …) —
für jede Tabelle einsetzbar, statt fester Seitengröße im Controller.

- Zwei Modi:
  - `live('<containerId>')`: rendert ein `<select data-gk-live-input>` — `GK.liveTable`
    übernimmt Reload und Reset auf Seite 1 automatisch (collectParams löscht `page`).
  - sonst (navigate): `onchange` navigiert per Voll-Reload und erhält andere Filter
    (`preserve([...])`, `baseUrl(...)`).
- `->resolve($default)` liest den Parameter im Controller gegen die Optionen-Whitelist.
- CSS: `.gk-pagesize` / `.gk-pagesize-select` (kompakt, an `.gk-filter` angelehnt).

```php
// Live-Tabelle (AJAX):
PageSize::make('per_page')->current($perPage)->live('exp-live')->render();
// Klassische Seite:
PageSize::make('per_page')->current($perPage)->baseUrl('/faktura/expenses')
    ->preserve(['q','status'])->render();
```

---
## [1.19.0] - 2026-06-01 — Auswahl-Checkboxen im Client-Modus (renderStatic)

### GK.table: data-gk-selectable funktioniert jetzt auch mit data-gk-static

Bisher rendert `renderStatic()` (Client-Daten via `<script data-gk-data>`) keine
Auswahl-Spalte — Multi-Select/Bulk-Bar gab es nur bei server-gerenderten Tabellen
(Table.php). Neu:

- `renderStatic` erzeugt bei `data-gk-selectable` eine `gk-cb-col`-Checkbox-Spalte
  (Header mit `data-gk-select-all`) und `tr[data-gk-row-id]` je Zeile. Das Zeilen-ID-
  Feld kommt aus `data.rowId` (Default `"id"`).
- Die Auswahl wird am Container gehalten (`wrap._gkSelected`) und nach jedem
  Re-Render (Sort/Suche/Filter) wiederhergestellt; `wrap._gkUpdateBar()` spiegelt
  Checkbox-/Highlight-/Bulk-Bar-Zustand.
- Select-all-Handler delegiert auf den Container (überlebt das Neu-Rendern der thead).

Additiv und rückwärtskompatibel — server-gerenderte selektierbare Tabellen unverändert.
Ermöglicht voll client-seitige, sortier-/such-/auswählbare Tabellen (z.B. Detail-Modals).

---
## [1.18.0] - 2026-05-31 — Live-Suche Self-Modus (data-gk-live-self)

### GK.liveTable: Self-Modus für Listen ohne Partial-Endpoint

Neues additives Attribut `data-gk-live-self` am `data-gk-live-table`-Container.
Eine Liste wird damit live durchsuchbar (AJAX, kein Voll-Reload, Cursor bleibt im
Suchfeld), OHNE dass der Controller einen eigenen Partial-Zweig braucht: Die
Antwort ist die ganze Seite, GK schneidet per DOMParser den gleichnamigen
Container heraus und tauscht nur dessen Inhalt. Der Such-Input liegt außerhalb des
Containers (via `data-gk-live-input`), wird also nie ersetzt → Fokus/Cursor bleibt.

```html
<input data-gk-live-input="my-tbl" name="q">          <!-- außerhalb! -->
<div id="my-tbl" data-gk-live-table="/liste" data-gk-live-self>…Tabelle…</div>
```

- Bestehende `data-gk-live-table`-Seiten (Partial-Endpoint) bleiben unverändert —
  Self-Modus ist rein opt-in über das zusätzliche Attribut.
- Neuer Helfer `GK.liveTable.applyHtml()` kapselt das Container-Extrahieren.

---
## [1.17.5] - 2026-05-30 — Modal-Close-Button: größeres Hit-Target, saubere Ausrichtung

### Changed

`.gk-modal-close` war mit `padding:4px 8px` sehr knapp und klebte optisch an der
Header-Kante. Jetzt: 36×36px quadratisches Hit-Target (Touch-freundlich), vertikal
zentriert, optisch an die Header-Innenkante ausgerichtet (negativer Rand), Fokus-Ring
für Tastaturbedienung, und `.material-icons`-Glyph wird sauber zentriert. Gilt
GridKit-weit für alle `gk-modal-close`-Buttons.

---
## [1.17.4] - 2026-05-30 — BelegModal-Close nach AJAX-Navigation

### Fixed

Der BelegModal-Container liegt innerhalb `[data-gk-content]` und wird bei
AJAX-Navigation per `innerHTML`-Swap neu gerendert — dabei ging der
Event-Listener des Schließen-Buttons verloren (Download / „In neuem Tab"
funktionierten weiter, da reine `<a href>`). Behoben:
- `GK.navigate()` ruft nach dem Content-Swap nun `GK.belegModal._init()` auf.
- `_init()` ist jetzt idempotent: Close-Button via `onclick` (kein Stapeln),
  Click-outside einmalig über `dataset.gkBelegBound`, ESC global nur einmal
  gebunden (`_escBound`). Mehrfaches `_init` nach jedem Swap ist damit sicher.

---
## [1.17.3] - 2026-05-18 — BelegModal-Close-Button funktioniert wieder

### Fixed

`GK.belegModal._init()` wurde nur im `if (readyState === "loading")`-Zweig
aufgerufen — nicht im `else`. Mit `<script defer>` im `<head>` (Standard
Panel-Setup) ist der Status beim Script-Run aber bereits `"interactive"`,
also lief immer der else-Zweig. Resultat: Close-Button (X), Click-Outside
und ESC am PDF-Modal hatten keine Handler — der Modal liess sich nicht
schliessen, nur ueber Hard-Reload.

Fix: `_init()` auch im else-Zweig aufrufen. Bug existiert seit v1.15.0.

---
## [1.17.2] - 2026-05-17 — Toolbar-gk-select-search wirklich 34px

### Fixed

rc344+rc345+v1.17.1: padding und min-height waren richtig, aber das
generische `.gk-select-display { height: 44px; }` hat trotzdem 44px
erzwungen. Jetzt mit explizitem `height: 34px` ueberschrieben.

---
## [1.17.1] - 2026-05-17 — gk-select-search in Toolbar passt zu gk-search/gk-filter

### Fixed

`.gk-select-search` rendete in einer `.gk-toolbar` mit 44px Höhe waehrend
`gk-search` (34px) und `gk-filter` (32px) deutlich niedriger sind — User-
Beschwerde: "Felder haben unterschiedliche Höhen".

Neu in gridkit.css:

```css
.gk-toolbar .gk-select-search .gk-select-display {
  padding: 6px 12px;
  min-height: 34px;
  line-height: 1.4;
  border-radius: var(--gk-radius-sm);
}
.gk-toolbar .gk-select-search .gk-select-arrow { font-size: 18px; }
```

Nur innerhalb von Toolbars — Form-Felder bleiben unveraendert 44px.

---
## [1.17.0] - 2026-05-17 — Outline-SVG-Icons + einheitlicher Tabellen-Stil

### Added — `GridKit\Icon::svg($name, $size)` zentrales Outline-Icon-Set

Bisher rendete `Table::button('edit', …)` Outline-SVG, aber
`Button::icon('edit', …)` Material-Icons-Font (gefüllte Glyphen). Resultat:
Edit-Stift und Delete-Mülleimer sahen je nach Aufruf-Weg unterschiedlich aus.

Neue Klasse `GridKit\Icon` zentralisiert das SVG-Mapping (~22 Icons inkl.
edit, delete, add, check, close, eye, download, upload, copy, mail, search,
settings, open_in_new, auto_awesome, login, print, arrow_back, send,
lock_open, attach_file, link_off, refresh).

### Changed — `Button::render/icon` rendert SVG-Outline (default)

`Button::render('label', ['icon' => 'edit'])` und `Button::icon('edit')`
rendern jetzt **Outline-SVG** wenn das Icon im `Icon::svg`-Mapping ist —
sonst Fallback auf Material-Icons-Font.

**Opt-out:** `['iconStyle' => 'material']` erzwingt das alte Verhalten.

Damit sieht z.B. der Edit-Button in einer Faktura/Invoices-Tabelle (über
`ActionGroup` / `Button::icon`) jetzt identisch aus wie in einer
Articles-Tabelle (über `Table::button`).

### Changed — `.gk-table-compact` auf Hybrid-Look

`.gk-table-compact th, .gk-table-compact td` neu:
- `padding: 8px 12px` (vorher 6px 12px)
- `font-size: 14px` (vorher 13px)

Lesbare Schrift wie articles, aber kompakteres Vertical-Padding. User-Wunsch
für Panel-Tabellen.

### Changed — `ActionGroup` Default-Size auf `sm`

`ActionGroup::render([['icon' => 'edit', ...]])` rendert Buttons jetzt mit
`size: 'sm'` (= 26×26 mit 16px-Icon) statt `xs` (= 26×26 mit 14px-Icon).
Damit haben Action-Spalten überall die gleiche Button-Größe wie
`Table::button()` in articles. Opt-in zum alten Verhalten: `'size' => 'xs'`.

### Migration

- Tabellen mit `gk-table-compact` werden sichtbar geräumiger (lesbarer)
- Edit/Delete-Icons in `Button::icon` / `ActionGroup` werden zu Outlines
- Wer Material-Icons explizit will: `['iconStyle' => 'material']`

---
## [1.16.0] - 2026-05-16 — ActionGroup + gk-btn-xs + gk-btn-pill

### Added — Action-Spalten-Komponente für Tabellen

Wiederkehrendes Pattern in Consumer-Projekten (SSI Panel):
Tabellen haben eine „Aktionen"-Spalte mit 2–4 kleinen Buttons (Edit, Löschen,
Verbuchen, Mahnen, …). Jedes Projekt hat eigene `.xx-btn-icon`, `.xx-btn-match`,
`.xx-btn-paid-sm` Custom-Klassen erfunden. GRIDKit liefert das jetzt zentral.

**Neue CSS-Klassen:**
- `.gk-action-group` — `inline-flex; gap:4px; flex-wrap:nowrap` Container
- `.gk-btn-xs` — extra-kleine Buttons (padding 3px 8px, font 11px, radius 6px).
  Icon-only-Variante: 26×26 px
- `.gk-btn-pill` — `border-radius:999px` Modifier (Badge-/Pill-Style)

**Neue PHP-Klasse `GridKit\ActionGroup`:**
```php
\GridKit\ActionGroup::render([
    ['icon' => 'edit',   'onclick' => "edit($id)",  'title' => 'Bearbeiten'],
    ['icon' => 'delete', 'onclick' => "del($id)",   'title' => 'Löschen',
     'color' => 'danger'],
    ['icon' => 'send',   'label' => 'Mahnen',       'color' => 'warning',
     'variant' => 'filled', 'pill' => true,
     'showIf' => $isOverdue, 'onclick' => "remind($id)"],
]);
```

Item-Optionen: `icon`, `label`, `href`, `onclick`, `title`, `variant` (default:
`text` wenn nur Icon, sonst `outlined`), `color` (default: `neutral`), `size`
(default: `xs`), `pill`, `disabled`, `showIf` (falsy → wird übersprungen),
`class`.

Vorteile gegenüber Eigenbau:
- Konsistente Größen, Abstände, Hover-States über alle Tabellen.
- `showIf` macht conditional rendering deklarativ.
- Pill-Modifier (für „Stufe 1/2/3"-Mahn-Badges etc.) ist nur eine Option,
  nicht eine eigene Klasse.

### Migration

Consumer-Projekte mit eigenen `*-btn-*` Klassen (z.B. SSI Panel `.ssi-btn-match`,
`.ssi-btn-paid-sm`, `.ssi-btn-remind`) können diese 1:1 durch `gk-btn`-Kombinationen
ersetzen:

| Eigenbau | GRIDKit |
|---|---|
| `.ssi-btn-match` (filled green mini) | `gk-btn gk-btn-xs gk-btn-filled gk-btn-success gk-btn-icon-only` |
| `.ssi-btn-paid-sm` (outlined green) | `gk-btn gk-btn-xs gk-btn-outlined gk-btn-success gk-btn-icon-only` |
| `.ssi-btn-remind` (filled warning) | `gk-btn gk-btn-sm gk-btn-filled gk-btn-warning` |
| `.ssi-btn-remind-badge` (pill) | `gk-btn gk-btn-xs gk-btn-filled gk-btn-warning gk-btn-pill` |
| `<div style="flex…">` Container | `<div class="gk-action-group">` |

---
## [1.15.0] - 2026-05-16 — BelegModal: globaler PDF-/Dokument-Vorschau-Modal

### Added — `GridKit\BelegModal` Komponente

Neuer Modal für PDF-/Beleg-Vorschauen mit iframe + Mobile-Fallback.
Ersetzt das Pattern, `window.open(pdfUrl, '_blank')` zu nutzen — eliminiert
zerstreute „neuer Tab"-Aufrufe zugunsten einer konsistenten Inline-Vorschau.

**PHP-API:**
```php
// Einmal pro Page (typischerweise im Layout vor </body>):
\GridKit\BelegModal::container();
```

**JS-API:**
```javascript
GK.belegModal.open(url);
GK.belegModal.open(url, { title: 'Rechnung 123' });
GK.belegModal.open(url, { autoPrint: true });            // druckt iframe sobald geladen
GK.belegModal.open(url, {
    unlinkExpenseId: 456,                                 // zeigt „Verknüpfung trennen"-Button
    onUnlink: function() { location.reload(); }           //   POST an /faktura/api/beleg/unlink
});
GK.belegModal.close();
```

**Verhalten:**
- Desktop: iframe lädt URL inline (Browser-PDF-Viewer).
- Mobile (≤ 768px): iframe versteckt, „PDF öffnen"-Button öffnet nativen Viewer.
- ESC schliesst, Click-outside schliesst.
- Optional `autoPrint`: druckt das iframe nach load (für Print-Workflows).
- Optional `unlinkExpenseId`: blendet „Verknüpfung trennen"-Button ein, ruft
  `/faktura/api/beleg/unlink` (faktura-spezifisch, aber konfigurierbar via
  Callback).
- Falls Container fehlt: Console-Warning + Fallback auf `window.open(url)`.

**Markup:** Alle Selektoren via `data-gk-beleg-*` Attribute, keine festen IDs
ausser dem Container (`#gk-beleg-modal`). Wiederholte Aufrufe arbeiten am
gleichen Container — keine doppelten Overlays.

**Migration aus „eigenem Beleg-Modal":**
- `openBelegModal(url, title, opts)` und `closeBelegModal()` sind als
  `window.*`-Aliases auf `GK.belegModal.*` verfügbar — bestehender Code
  funktioniert unverändert.
- Wer das alte Partial `_partials/beleg-modal.php` (SSI Panel) selbst includiert
  hat, ersetzt es durch `\GridKit\BelegModal::container()`.

### Sync zu PawBot Dashboard etc.

`src/BelegModal.php`, CSS-Block in `gridkit.css`, JS-Block in `gridkit.js` —
alle drei via normalem rsync mitnehmen. Keine PawBot-spezifische Anpassung.

---
## [1.14.0] - 2026-05-16 — Utility-Klassen für Spacing, Layout, Typography, Farben

### Added — Tailwind-ähnliche Utility-Klassen (additiv, keine Breaking-Changes)

Motivation: Audit der Consumer-Projekte (SSI Panel) ergab > 2 800 Inline-Style-
Vorkommen in Views. Statt jeder Komponente eine eigene Mini-Style-Suppe zu
geben, bekommt GRIDKit jetzt einen kompakten Utility-Layer. Ziel: 70–80 % der
heutigen Inline-Styles werden durch Klassen ersetzbar.

**Spacing-Skala (MD3 8px-Grid mit halben Schritten):**
- `0`=0, `1`=4px, `2`=8px, `3`=12px, `4`=16px, `5`=20px, `6`=24px

**Neue Klassen (~120):**

| Kategorie | Klassen |
|---|---|
| Display | `gk-hidden`, `gk-block`, `gk-inline`, `gk-inline-block` |
| Flex | `gk-flex`, `gk-inline-flex`, `gk-flex-col`, `gk-flex-wrap`, `gk-flex-1`, `gk-flex-center`, `gk-flex-between` |
| Items / Justify | `gk-items-{start,center,end,baseline}`, `gk-justify-{start,center,end,between}` |
| Gap | `gk-gap-{xs,sm,md,lg,xl,2xl}` (4/6/8/12/16/20 px) |
| Margin | `gk-m-{0..6}`, `gk-mt-{0..6}`, `gk-mb-{0..6}`, `gk-ml-{0..4,auto}`, `gk-mr-{0..4,auto}`, `gk-mx-auto` |
| Padding | `gk-p-{0..6}`, `gk-px-{0..6}`, `gk-py-{0..6}` |
| Font-Size | `gk-fs-{xs,sm,md,base,lg,xl,2xl}` (11/12/13/14/16/18/20 px) |
| Font-Weight | `gk-fw-{normal,medium,semibold,bold}` (400/500/600/700) |
| Text-Align | `gk-text-{left,center}` (`gk-text-right` existierte bereits) |
| Text-Color | `gk-text-{primary,success,danger,warning,on-surface}` (`gk-text-muted` existierte) |
| Background | `gk-bg-{surface,muted,primary-soft,success-soft,danger-soft,warning-soft}` |
| Border-Radius | `gk-rounded-{none,sm,md,lg,xl,full}` (0/6/8/10/14/999 px) |
| Width/Height | `gk-w-{full,auto}`, `gk-h-{full,auto}` |
| Misc | `gk-clickable`, `gk-not-clickable`, `gk-overflow-{x,y}-auto`, `gk-font-mono`, `gk-no-decoration`, `gk-truncate`, `gk-break-word` |

### Usage

```html
<!-- Vorher: Inline-Styles -->
<div style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--gk-text-muted)">
  <span>Label</span><span>Value</span>
</div>

<!-- Nachher: Utility-Klassen -->
<div class="gk-flex-center gk-gap-md gk-fs-md gk-text-muted">
  <span>Label</span><span>Value</span>
</div>
```

### Migration

- Additive Klassen — bestehender Code laeuft unveraendert weiter.
- Consumer-Projekte koennen schrittweise migrieren (Audit-Score messen).

---
## [1.13.1] - 2026-05-15 — SortLink: konfigurierbare URL-Parameter-Namen

### Added
- `SortLink::header($key, $label, $opts)` akzeptiert jetzt `sort_param` und
  `dir_param`-Optionen — Default bleibt `sort` und `dir`, lässt sich aber
  überschreiben wenn der Param-Name kollidiert.
- `SortLink::context()` akzeptiert ein 5. Argument `$opts` für dieselben
  Overrides.

**Use-Case:** Banking-Automatik im Panel benutzt `?dir=income/expense` für
einen Direction-Filter — Sort braucht dort `sdir` statt `dir`:

```php
$sl = SortLink::context($base, $sort, $sdir, $preserve, ['dir_param' => 'sdir']);
```

---
## [1.13.0] - 2026-05-15 — SortLink — server-seitige Sort-Header für Tabellen

### Added
- **`GridKit\SortLink::header($key, $label, $opts)`** — rendert einen sortierbaren
  `<a class="gk-sort-link">` mit Material-Icon (`gk-sort-icon`), toggelt
  `sort` + `dir` URL-Parameter und erhält alle Filter (`preserve`-Array).
- **`SortLink::context($baseUrl, $currentSort, $currentDir, $preserve)`** — Closure-
  Helper für mehrere Spalten in einer Tabelle. Bindet den Context einmal,
  pro Spalte nur noch `$sl('key', 'Label')`.

### Hintergrund
Vor v1.13.0 hatten viele Faktura-Listen (`expenses`, `accounts`, `banking-*`)
**keine sortierbaren Spalten** — User konnten nicht nach Datum/Betrag/Name
sortieren. Die existierende Sortier-Logik war in 4 Views als `$sortLink`-
Closure dupliziert. SortLink konsolidiert das.

**Beispiel:**
```php
$sl = GridKit\SortLink::context('/faktura/expenses', $sort, $dir, [
    'q' => $q, 'year' => $year, 'status' => $status,
]);
echo $sl('expense_date',  'Datum');
echo $sl('gross_amount', 'Brutto', 'gk-text-right');
```

CSS-Styles für `.gk-sort-link` und `.gk-sort-icon.is-active` sind bereits
seit v1.11.0 in `gridkit.css`.

---
## [1.12.0] - 2026-05-14 — Select::searchable() — searchable Dropdown als One-Liner

### Added
- **`GridKit\Select::searchable($name, $options, $opts)`** — neuer PHP-Helper rendert ein gk-select-search Dropdown aus einem flachen Options-Array. Ersetzt das ~25-zeilige HTML-Boilerplate.
- Optionen: `selected`, `placeholder`, `searchPlaceholder`, `id`, `class`, `required`
- Options-Format: `['val' => 'label', ...]` ODER `[['value' => x, 'label' => y], ...]`
- Beispiel: `<?= Select::searchable('account_id', $accountOpts, ['placeholder' => '— Konto wählen —', 'selected' => $current]) ?>`

### Warum
Lange Selects mit nativem `<select>` sind im Faktura-Modul + überall sonst nicht durchsuchbar. Nutzer wollen tippen, nicht scrollen. Bisher musste jedes System den HTML-Block kopieren — jetzt One-Liner.

---
## [1.11.5] - 2026-05-13 — gk-table-wrap: Abgerundete Ecken ohne Toolbar

### Fixed — Tabellenecken bei direktem table-Kind
Wenn eine `gk-table` direkt (ohne vorherige Toolbar) in `gk-table-wrap` liegt,
hatten `thead th` und letzte `tbody td` eckige Ecken (overflow:visible verhindert Clip).
CSS-Selektoren für `:first-child`/`:last-child` setzen jetzt `border-radius` direkt
auf die entsprechenden Zellen — kompatibel mit TableHeader (da dort die Toolbar
das erste Kind ist, greifen die `> .gk-table`-Selektoren nicht).

---
## [1.11.4] - 2026-05-13 — liveTable: Out-of-Band Updates via `<template data-gk-replace>`

### Added — liveTable Out-of-Band Replacement
Nach jedem AJAX-Swap verarbeitet `liveTable` jetzt `<template data-gk-replace="css-selector">`
Elemente in der Serverantwort: Der Inhalt des Templates ersetzt das per Selektor gefundene
Element **außerhalb** des liveTable-Containers. Damit können z.B. StatCards automatisch
aktualisiert werden, wenn ein Filter geändert wird.

**Verwendung im Partial:**
```html
<template data-gk-replace="[data-gk-stats=my-cards]">
  <?php (new StatCards('my-cards'))->card(...)->render(); ?>
</template>
```

---
## [1.11.3] - 2026-05-12 — ajaxSelect: Tastatur-Navigation (Pfeiltasten + Enter)

### Added — ajaxSelect: Tastatur-Navigation
`GK.ajaxSelect` unterstützt jetzt vollständige Tastatur-Bedienung im Ergebnis-Dropdown:
- **ArrowDown / ArrowUp**: Eintrag markieren (visuelles Highlight)
- **Enter**: Markierten Eintrag auswählen
- **Escape**: Dropdown schließen
- `selectOption()` als gemeinsame Funktion für Klick und Tastatur
- `activeIdx` wird bei Schließen/Löschen zurückgesetzt

---
## [1.11.2] - 2026-05-12 — liveTable.restoreSession: Redirect statt AJAX + saveSession on bind

### Fixed — restoreSession: voller Redirect statt AJAX
`liveTable.restoreSession` verwendete bisher AJAX (`loadUrl`), was nur den `#inv-live`-Container
aktualisierte. Dropdowns und Filter-Elemente außerhalb (YearFilter, Month-Select, Pagination)
zeigten dadurch den falschen Stand ("Alle Jahre", "Alle Monate"). Fix: `window.location.replace()`
erzwingt einen vollen PHP-Render, der alle Elemente korrekt befüllt.

### Fixed — bind: Session sofort speichern bei URL-Parametern
Beim direkten Aufruf einer gefilterten URL (z.B. `?year=2025&q=kraus&month=5`) wurde die Session
erst nach einer Benutzeraktion gespeichert. Jetzt speichert `bind()` die Session sofort beim
Laden, wenn URL-Parameter vorhanden sind.

---
## [1.11.1] - 2026-05-04 — gk-card Shadow + gk-section-title Kontrast

### Changed — gk-card: Subtiler Shadow für bessere Tiefenwirkung
`gk-card` nutzt jetzt `var(--gk-surface)` und `var(--gk-outline-variant)` statt
hardcodierter Farben. Dazu ein neuer subtiler Box-Shadow (`0 1px 4px rgba(0,0,0,0.07)`),
damit Cards auf dem Seiten-Hintergrund klar erkennbar sind.

### Changed — gk-form-compact .gk-section-title: Primärfarbe + Uppercase
Section-Titles im Compact-Formular-Modus sind jetzt 11px / Uppercase / Primärfarbe —
analog zu `.gk-card-title`. Damit sind Abschnittsgrenzen auch ohne Trennlinie sofort
erkennbar.

---
## [1.11.0] - 2026-05-04 — YearFilter respektiert allOption + neue gk-sort-icon-Klasse

### Fixed — YearFilter: allOption als Default wenn URL-Param fehlt
Bisher hat der YearFilter beim Aufruf ohne `?year=`-Parameter immer das
**aktuelle Jahr** im Dropdown vorausgewählt — auch wenn `allOption('Alle Jahre')`
gesetzt war. Das war inkonsistent: der Controller filterte „alle Jahre",
aber das Dropdown zeigte das aktuelle Jahr als selektiert.

Jetzt: ohne URL-Param + mit `allOption()` → Default ist **allOption-Wert**
(typisch 0 = „Alle Jahre"). Ohne `allOption()` → weiterhin das aktuelle Jahr.

### Added — `.gk-sort-icon` + `.gk-sort-link` für eigene Sort-Header
Wer eigene Sort-Spalten via `material-icons` rendert (z.B. `unfold_more`,
`arrow_upward`), bekam bisher die Default-Material-Icons-Größe von 24px
in den Tabellenkopf — viel zu groß. Neue Klassen:

- `.gk-sort-icon` — material-icons-Inline (14px, opacity 0.4, hover 0.7)
- `.gk-sort-link` — flex-Container für Label + Icon (kein Underline, color inherit)
- `.gk-sort-icon.is-active` — primary-Farbe + opacity 0.85 für aktive Sortierung

Markup-Beispiel:
```html
<a href="?sort=date&dir=asc" class="gk-sort-link">
  Datum
  <span class="material-icons gk-sort-icon is-active">arrow_upward</span>
</a>
```

---
## [1.10.2] - 2026-04-29 — Selectable: nur Checkbox-Zelle togglet (Scope-Revert v1.10.1)

### Changed
v1.10.1 hatte den Klick-Bereich auf die ganze Zeile ausgeweitet. In der Praxis
ist das verwirrend: ein Klick auf eine Daten-Zelle (z.B. „Tracking" in Newsletter,
oder Beschreibung in Banking) lässt die Bulk-Action-Bar mit „Löschen" aufpoppen,
obwohl der User nur eine Zelle ansehen wollte.

Jetzt: **nur Klicks INNERHALB der Checkbox-Spalte (`td.gk-cb-col`)** togglen
die Auswahl. Die Spalte selbst füllt den Klick-Bereich (volle Zellen-Höhe
dank `<label>`-Wrapper im Anwender-Markup), das reicht für schnelles
Multi-Select ohne Pixel-Treffsicherheit.

CSS: Pointer-Cursor + Hover-Background nur noch auf `td.gk-cb-col`, nicht
mehr auf der ganzen Zeile.

---
## [1.10.1] - 2026-04-27 — Selectable: Row-Click toggelt Checkbox

### Improved
- In `data-gk-selectable`-Tabellen togglet jetzt ein Klick **irgendwo in der Zeile** die Checkbox — nicht mehr nur die kleine Checkbox treffen. Beschleunigt Multi-Select erheblich.
- Klicks auf interaktive Elemente (Buttons, Links, Inputs, Action-Icons, `.ssi-clickable-row`, `[data-gk-action]`) bleiben unberührt — kein Konflikt mit Edit-/Delete-/Detail-Modals.
- Drag-Text-Auswahl wird respektiert (kein Toggle wenn User Text markiert).
- Hover-Highlight + `cursor: pointer` auf selectable-Tabellen-Zeilen zeigen die Klickbarkeit.

---
## [1.10.0] - 2026-04-26 — TableHeader-Komponente

### Added
- **`GridKit\TableHeader`** — neue Komponente für einheitliche Filter-/Such-Leisten über Tabellen. Strukturiert in drei feste Sektionen:
  1. Status-Zeile (volle Breite, typisch FilterChips „Alle / Offen / Bezahlt")
  2. Toolbar (Suche + Filter-Dropdowns inline, optional Reset-Button)
  3. Erweitert (collapsible `<details>` für Datums-/Beträge-/Detail-Filter)
- Inhalte werden via Closures übergeben → Aufruf in der View bleibt kompakt:
  ```php
  TableHeader::make('exp')
    ->status(fn() => $statusChips->render())
    ->search('q', $q, 'Suche…', ['live' => 'exp-live'])
    ->filter(fn() => $yearFilter->render())
    ->filter('<select class="gk-filter">…</select>')
    ->advanced(fn() => $renderDateRange())
    ->reset('/faktura/expenses')
    ->render();
  ```
- CSS-Klassen: `.gk-tableheader`, `.gk-tableheader-status`, `.gk-tableheader-toolbar`, `.gk-tableheader-advanced`, `.gk-tableheader-spacer`. Konsistente Paddings/Borders, nutzt vorhandene `gk-search`/`gk-filter`/`gk-chip`-Styles.
- Ziel: Schluss mit „jede Tabellen-Seite eigene Filter-Anordnung". Ab jetzt eine Convention für alle Tabellen.

---
## [1.9.3] - 2026-04-24 — Table-Wrap overflow:visible (Dropdown-Fix)

### Fixed
- `.gk-table-wrap { overflow: clip }` → `overflow: visible`. Dropdowns innerhalb der Toolbar (gk-select-search, gk-filter-selects) wurden vom Wrap beschnitten wenn sie nach unten über die Tabellen-Grenze hinausragten (v.a. bei kurzen Tabellen). Jetzt rendern sie korrekt drüber.
- Für horizontales Scrollen bei breiten Tabellen steht `.gk-table-scroll`-Container bereit (`overflow-x:auto`, `border-radius:inherit`).

---
## [1.9.2] - 2026-04-24 — GK.liveTable: Event-Detection für Non-Text-Inputs

### Fixed
- `bindInput` verwendete `input` als Event-Name für alle Non-Checkbox-Inputs, aber Hidden-Inputs (z.B. als Wert-Träger in `gk-select-search`) feuern nur `change`. Dadurch reagierten gk-select-search-basierte Filter nicht auf Änderungen.
- Neu: Whitelist für text-like Input-Types (`text`, `search`, `url`, `email`, `tel`, `password`) nutzt `input`-Event; alle anderen (hidden, select, checkbox, radio, date, number, …) nutzen `change`-Event.

---
## [1.9.1] - 2026-04-24 — GK.liveTable: Session-Persistenz

### Added
- **Session-Persistenz**: Filterzustand pro Live-Container wird im `sessionStorage` gespeichert (Key `gkLive:<container-id>`). Beim „frischen" Aufruf derselben Seite (leere URL-Query, z.B. per Sidebar-Klick) wird der gespeicherte Zustand automatisch restauriert — Tabelle lädt gefiltert/sortiert wie der User es zuletzt hatte.
- Nur für die aktuelle Browser-Session (bei Neustart/Tab-Close alles zurück).
- Neue API-Methoden: `GK.liveTable.saveSession(container)`, `GK.liveTable.restoreSession(container)`.

---
## [1.9.0] - 2026-04-24 — GK.liveTable: AJAX-gefilterte Tabellen

### Added
- **Neues Modul `GK.liveTable`** (in `js/gridkit.js`): zentrale Komponente für Live-Such-/Filter-/Sort-/Paginierungs-Tabellen ohne Full-Page-Reload.
  - Container: `<div id="..." data-gk-live-table="/endpoint">...</div>`
  - Inputs (außerhalb des Containers): `<input data-gk-live-input="container-id" name="q">`
  - Beim Tippen: 250 ms Debounce → XHR mit `X-Requested-With: XMLHttpRequest` + `?partial=1` → Container-`innerHTML` wird getauscht, Cursor bleibt.
  - URL wird sofort (ohne Debounce) via `history.replaceState` aktualisiert — andere Navigations-Elemente (YearFilter, Sort-Links) sehen den aktuellen Zustand.
  - Link-Interceptor im Container: interne `<a href>` die auf denselben Endpoint zeigen (Sort-Header, Pagination) laufen automatisch als AJAX-Reload.
  - `patchNavSelects()`: überschreibt `onchange` von `<select data-gk-years>` sodass sie `window.location.search` als Basis nehmen statt Server-gerendertes `data-preserve` (behält aktuelle Suche beim Jahr-Wechsel).
- Controller-Seite muss bei AJAX-Request nur den Container-Inhalt ohne Layout rendern.

### Usage-Beispiel

```html
<!-- Filter bleiben stehen, Cursor im Input verloren? Nein. -->
<input data-gk-live-input="my-tbl" name="q" placeholder="Suche">
<select data-gk-live-input="my-tbl" name="status">...</select>

<div id="my-tbl" data-gk-live-table="/my-list">
    <!-- Tabelle, Sort-Header, Paginierung — alles live swappable -->
</div>
```

---
## [1.8.0] - 2026-04-17 — Table: Tabular-Nums für Währung/Zahlen

### Added / Changed
- **Table**: Spalten mit `format: 'currency'` oder `format: 'number'` bekommen automatisch:
  - `font-variant-numeric: tabular-nums` → gleichbreite Ziffern, Euro-Zeichen stehen zeilenübergreifend untereinander
  - `text-align: right` (wenn nicht anders gesetzt)
  - `white-space: nowrap` (wenn nicht explizit gesetzt)
  
  Damit sehen Beträge in Tabellen sofort typografisch korrekt aus, ohne dass jede View einzeln CSS setzen muss.

---
## [1.7.1] - 2026-04-17 — YearFilter: Fix `URL is not a constructor`

### Fixed
- **YearFilter Dropdown**: `new URL(...)` → `new window.URL(...)` und `window.location.href` statt `window.location`. Behebt `Uncaught TypeError: URL is not a constructor` auf Seiten, die irgendwo ein Element mit `id="URL"` oder `name="URL"` haben (z.B. `/faktura/invoices`) — "named access on Window" überschattet sonst den globalen `URL`-Konstruktor.

---
## [1.7.0] - 2026-04-17 — YearFilter: Toolbar-tauglich + „Alle"-Option

### Added
- **`allOption(string $label = 'Alle Jahre', int $value = 0)`**: Fügt eine „Alle"-Option am Anfang des Dropdowns ein. Der Controller muss den übergebenen Wert (default `0`) als „kein Filter" interpretieren.
- **`selectClass(string $class)`**: Legt die CSS-Klasse des `<select>` fest (default `gk-filter`).

### Changed
- **`mode('dropdown')` rendert jetzt nur das `<select>`** — ohne umgebenden `<div class="gk-year-filter-dropdown">`. Dadurch lässt sich das Dropdown direkt in eine bestehende `.gk-toolbar` (z.B. via `Table::toolbarHtml()`) einspeisen, ohne Layout-Bruch.

### Example
```php
ob_start();
(new YearFilter('cust-year', 'year'))
    ->baseUrl('/faktura/customers')
    ->range(2010, (int)date('Y'))
    ->preserve(['quarter', 'month', 'q'])
    ->mode('dropdown')
    ->allOption('Alle Jahre')
    ->render();
$toolbarHtml = ob_get_clean();

(new Table('customers'))->setData($rows)->toolbarHtml($toolbarHtml)->render();
```

---
## [1.6.0] - 2026-04-17 — YearFilter: Dropdown-Modus

### Added
- **YearFilter `mode('dropdown')`**: Zeigt die Jahre als `<select>`-Dropdown statt als Chip-Liste — nützlich wenn der verfügbare Zeitraum groß ist (z.B. 17 Jahre Firmenhistorie) und die Chip-Leiste zu lang würde.
- Default bleibt `mode('chips')` — bestehende Verwendungen ändern sich nicht.
- Kombinierbar mit `->range($from, $to)` um auch Jahre ohne Daten anzubieten.

### Example
```php
(new YearFilter('cust-year-filter', 'year'))
    ->baseUrl('/faktura/customers')
    ->range(2010, (int)date('Y'))
    ->preserve(['quarter', 'month', 'show', 'q'])
    ->mode('dropdown')
    ->render();
```

---
## [1.5.0] - 2026-04-15 — AJAX Sidebar Navigation (SPA-lite)

### Added
- **AJAX Navigation**: Sidebar-Links laden Content per fetch() ohne Full-Page-Reload
  - Opt-in: `$sidebar->ajaxNav(true)` + `data-gk-content` auf Content-Container
  - Ladebalken (Progress-Bar) am oberen Bildschirmrand
  - Browser Zurück/Vorwärts via pushState/popstate
  - Automatische Re-Initialisierung von GRIDKit-Komponenten
  - Fallback auf normale Navigation bei Fehler
- Sidebar.php: Neue Methode `ajaxNav(bool $enabled)`

---
## [1.4.17] - 2026-04-15 — Form: Date-Felder max-Jahr begrenzen

### Fixed
- **Form date/datetime**: Automatisch `max="9999-12-31"` gesetzt, damit Browser kein 6-stelliges Jahr erlaubt
- **Form**: `min` und `max` Attribute für alle Input-Typen unterstützt (text, number, date etc.)

---
## [1.4.13] - 2026-04-10 — Form Compact: alle Elemente + Demo

### Added
- **Form Compact — vollständig**: `.gk-form-compact` skaliert jetzt alle Formular-Elemente:
  - Inputs: 44px → 34px, Padding/Font reduziert
  - Selects: Höhe 34px, kompakteres Arrow-Positioning
  - Toggles: 48×28px → 38×22px
  - Checkboxen: 20×20px → 16×16px
  - Searchable Selects: Display + Options kompakter
  - Field-Inline: Gap 12px → 8px
- **Demo**: Side-by-side Vergleich Normal vs Compact unter gridkit.ssi.at/demo/#form
- **Doku**: SPEC.md, GRIDKIT_SKILL.md mit Form Density Tabellen

### Changed
- **Input Borders** (global): 1.5px #d0d7de → 1px #dde1e6 (zartere Linien)

---
## [1.4.1] - 2026-03-31 — Table renderStatic Button-Fix + Icons

### Fixed
- **Table renderBtnGroup JS**: Buttons nach renderStatic (Suche/Sort) hatten nur `gk-btn gk-btn-icon` — fehlten `gk-btn-text`, `gk-btn-neutral`, `gk-btn-sm`. Jetzt identisch mit PHP-Renderer (variant=text, color=neutral, size=sm per Default).
- **iconSvg()**: Nur 3 Icons bekannt — unbekannte Icons wurden als roher Text gerendert. Jetzt erweitert: `add`, `visibility`, `download`, `upload`, `copy`, `email`, `search`, `settings`, `open_in_new`, `auto_awesome`, `login`, `print`. Unbekannte Icons fallen auf Material Icons `<span class="material-icons">` zurück statt Text.

---

## [1.4.0] - 2026-03-26

Reconstructed on 2026-08-27 from the tag and its commit. This is the version
Packagist has served since March, and it was the one release with no changelog
entry — found by comparing the thirty tags on GitHub against the hundred and
forty-three entries here.

### Added

- **`showIf` / `hideIf` on a table row button** — the button renders only when
  the named field on that row is truthy (or falsy). A delete button that should
  not appear for a locked record needs no `if` around the whole column.

### Fixed

- The header's `z-index`, which let a table's sticky elements sit over it.

## [1.3.1] - 2026-03-26 — Header + Select-Dropdown Fixes

### Fixed
- Header: auto-height (56px min) statt feste 64px — Title+Breadcrumb passen immer
- Header-Title: flex-column, text-overflow:ellipsis für lange Breadcrumbs
- Breadcrumb im Header: kompakt (12px, kein Margin)
- Select-Dropdown z-index 500 (statt 100) — überlappt Cards/Modals korrekt
- Select-Search Wrapper: position:relative garantiert

---

## [Unreleased]

## [1.3.0] - 2026-03-26 — Tooltip Component

### Added
- CSS-only tooltips via `data-gk-tooltip` attribute (no JS needed)
- 4 positions: top (default), bottom, left, right via `data-gk-tooltip-pos`
- Multiline tooltip support via `data-gk-tooltip-wrap` attribute
- Rich tooltips with HTML content via `data-gk-tooltip-rich` (JS-powered)
- Viewport-aware positioning for rich tooltips
- Dark theme compatible (uses CSS custom properties)
- Demo page section with interactive examples and usage guide

## [1.2.3] - 2026-03-23 — Demo Anatomy Redesign

### Changed
- Skeleton Anatomy section redesigned as visual page blueprint with mini wireframe mockup + API reference cards
- Replaced flat 2-column grid with side-by-side layout: interactive mockup (left) + component cards with icons and code snippets (right)
- Fixed remaining German string "if/elseif-Blöcke" → English
- Added responsive fallback: mockup hidden on mobile, cards stack vertically

## [1.2.2] - 2026-03-23 — Formatted Skill Preview

### Changed
- Landing page Agent Skill preview now renders GRIDKIT_SKILL.md as formatted HTML instead of raw text
- Built-in PHP Markdown renderer: headings, tables, code blocks, lists, blockquotes, inline formatting
- Tables displayed as styled row-layout (not raw Markdown pipes)
- Code blocks with language labels and monospace styling
- Collapsible preview with "Show full document" toggle and fade-out gradient
- Full Dark Mode support for skill preview

## [1.2.1] - 2026-03-22 — Complete JS i18n

### Fixed
- All remaining hardcoded German strings in `gridkit.js` now use `_t()` i18n function
- Translated: table empty state, select "no matches", upload queue status (ready/uploading/uploaded/remove), file size validation errors
- German code comments in JS replaced with English

### Added
- 8 new JS translation keys in `lang/en.php` and `lang/de.php`: `js.no_entries`, `js.no_matches`, `js.too_small`, `js.total_size_exceeded`, `js.ready`, `js.remove`, `js.uploading`, `js.uploaded`

## [1.2.0] - 2026-03-22 — Internationalization (i18n) & English Demo

### Added
- **Lang Component** (`GridKit\Lang`) — Built-in i18n support with `Lang::set()`, `Lang::t()`, `Lang::jsConfig()`
- **Language Files** (`lang/en.php`, `lang/de.php`) — Translation files for all framework strings
- **Language Switcher** in Demo — Toggle between English and German via header button
- Auto-loading of language files in `autoload.php`

### Changed
- **Demo page** fully translated to English (default language)
- All PHP components (Table, Form, Auth, Header, Sidebar) now use `Lang::t()` for UI strings
- JavaScript strings in `gridkit.js` now use `GK_LANG` for i18n
- **GRIDKIT_SKILL.md** updated with Lang component documentation
- Default language changed from German to English

## [1.1.1] - 2026-03-22 — GitHub Release & Landing Page Fix

### Fixed
- Apache DocumentRoot auf GRIDKit-Root umgestellt — Landing Page wird jetzt als Startseite angezeigt
- Landing Page Links von `/gridkit/demo/` auf `/demo/` korrigiert

### Changed
- README.md komplett überarbeitet: Englisch, 17+ Komponenten, Agent-Skill-Positionierung, Badges
- Git Tags v1.0.0 und v1.1.0 erstellt und auf GitHub gepusht

## [1.1.0] - 2026-03-20 — Agent Skill & Landing Page

### Added
- **Landing Page** (`index.php`) — Professionelle Startseite mit SEO-Optimierung, Open Graph, Structured Data (JSON-LD), responsive Design
- **Agent Skill** (`GRIDKIT_SKILL.md`) — Maschinenlesbares Skill-Dokument für KI-Agents (Claude, GPT, Gemini). Enthält komplette Komponenten-Referenz, Code-Patterns und Best Practices
- **Interaktive Demo** — Terminal-Animation auf der Startseite zeigt Agent-Skill in Aktion (4 Szenarien: CRUD Table, Form, Dashboard, Auth)
- **SEO** — `robots.txt`, `sitemap.xml`, Open Graph Tags, Twitter Cards, JSON-LD Structured Data
- **Agent-First Messaging** — Framework positioniert sich als "Agent-Ready" PHP Component Framework

### Changed
- GRIDKit wird international positioniert (Landing Page auf Englisch)

## [1.0.0] - 2026-03-18 — Erster stabiler Release

GRIDKit v1.0.0 — ein vollständiges, produktionsreifes CSS/JS Framework für Admin-Dashboards.
Zero Dependencies, Light & Dark Mode, Mobile-first, 6 Themes.

### Komponenten (17+)
- **Table** — 6 Varianten (Default, Bordered, Striped, Celled, Compact, Selectable, Inverted, Definition), 3 Sizes, Mobile Card/Scroll, Sortierung, Suche, Filter, Pagination, Multi-Select
- **Form** — Grid-Layout (16-Spalten), Input, Textarea, Select, Searchable Select, Checkbox, Radio, Toggle, Slider, Color Picker, File Upload (Drag & Drop), RichText (CKEditor5)
- **Cards** — Responsive Grid (auto-fill, 2/3/4 Spalten), Header/Body/Footer, Meta, Link-Hover
- **StatCards** — KPI-Karten mit Trend-Indikatoren, Farben
- **Segment** — Container (Raised, Muted, Compact, Padded, Basic, Stacked)
- **Message** — Info/Success/Warning/Error, Compact, Dismissible
- **Accordion** — Auf-/zuklappbar, Single-Open Modus
- **Tabs** — Tab-Navigation mit Panels
- **Modal** — Overlay-Dialog, verschachtelbar, Formulare
- **Breadcrumb** — Pfad-Navigation mit Icons
- **Avatar** — 5 Grössen (xs-xl), Status-Dots, Gruppen, Square
- **Gallery** — Thumbnail Grid, Lazy-Loading, Masonry-Variante
- **Lightbox** — Vollbild-Vorschau, Prev/Next, Keyboard, Counter
- **Toast** — Benachrichtigungen (4 Typen)
- **Confirm** — Bestätigungs-Dialog
- **Buttons** — Filled/Outlined/Text/Tonal, 5 Farben, Sizes, Pill/Circle/Square, FAB
- **Sidebar** — Responsive, Gruppen, Badges, Collapse, Mobile-Overlay
- **Header** — Fixed, Suche, User-Dropdown, Theme-Switcher

### Design-System
- 6 Themes: Indigo, Ocean, Forest, Rose, Amber, Slate
- Light Mode: Neutrales Slate-Grau (keine M3-Lila-Tönung)
- Dark Mode: 4-Level Kontrast-System (Page → Panel → Interactive → Elevated)
- Mobile-Optimierung für alle Komponenten
- CSS Custom Properties durchgängig

### Technisch
- Zero Dependencies — 1 CSS + 1 JS, kein Build-Prozess
- PHP-Klassen: Table, Form, Sidebar, Header, Modal, StatCards, FilterChips, YearFilter, Layout, Theme, Auth, Button
- Vanilla JS mit Event-Delegation (funktioniert mit dynamischen Inhalten)
- Demo: gridkit.ssi.at mit allen Komponenten live

## [0.16.0] - 2026-03-18

### Added
- Avatar-Komponente (xs-xl, Status-Dots, Gruppen, Square-Variante)
- Gallery/Thumbnail Grid (responsive, Lazy-Loading, Masonry-Variante)
- Lightbox (Prev/Next, Keyboard-Navigation, Counter, Caption)
- Demo: Avatar-, Gallery- und Lightbox-Beispiele in Cards-Section

## [0.11.0] - 2026-03-18

### Added
- **Segment** (`.gk-segment`) — Visueller Container/Abschnitt inspiriert von Fomantic UI (raised, muted, compact, padded, basic, stacked)
- **Message** (`.gk-message`) — Info/Success/Warning/Error Nachrichten mit Icons, Header, Dismiss-Button
- **Cards Grid** (`.gk-cards`) — Responsives Karten-Grid (auto-fill, 2/3/4 Spalten) mit Header, Body, Footer, Meta
- **Changelog Section** in der Demo — Rendert CHANGELOG.md als gestapelte Segments mit Version-Badges
- Dark Mode Support für alle 3 neuen Komponenten
- Demo: Sidebar-Items für Segment, Message, Cards (Komponenten-Gruppe) und Changelog (INFO-Gruppe)


- Weitere Komponenten geplant (Flash Messages/Alerts, Standalone Select)
- Dokumentation vervollständigen

## [0.10.0] - 2026-03-18

### Changed
- **BREAKING**: Light Mode Surface-Variablen von lila M3-Toenen auf neutrale Slate-Grau-Palette umgestellt
- Light Mode: `--gk-surface-container-low` #F6F8FA, `-high` #EAEEF2, `-highest` #D8DEE4
- Light Mode: Tabellen-Header 11px, uppercase, #F6F8FA Background, #57606A Text
- Light Mode: Tabellen Even-Rows #F6F8FA, Row-Borders #D8DEE4
- Light Mode: Cards und Table-Wrap mit #D0D7DE Border statt outline-variant
- Light Mode: Input Placeholder #6E7781
- Dark Mode: 4-Level Kontrast-System konsequent umgesetzt (Page #0D1117, Panel #161B22, Interactive #21262D, Elevated #2D333B)
- Dark Mode: Sidebar einheitlich #010409 fuer alle Themes (dunkelster Level)
- Dark Mode: Sidebar Border rgba(255,255,255,0.06), Text rgba(255,255,255,0.70)
- Dark Mode: Cards, Stat-Cards, Table-Wrap auf Panel-Level (#161B22) mit rgba(255,255,255,0.10) Border
- Dark Mode: Inputs auf Page-Level (#0D1117), Placeholder #484F58
- Dark Mode: Dropdowns und Tooltips auf Elevated-Level (#2D333B)
- Dark Mode: Tonal-Buttons mit helleren Textfarben fuer besseren Kontrast
- Dark Mode: Labels (green, orange, red, gray, blue) alle mit font-weight: 600
- Upload-Zone Light: #D0D7DE dashed Border
- `--gk-spacing` von 12px auf 16px erhoht (mehr Luft)

### Fixed
- Doppelte Dark-Mode-Tabellen-Definitionen konsolidiert (Component adjustments Block bereinigt)
- Alle hardcodierten var()-Referenzen in Dark Mode durch explizite 4-Level-Farbwerte ersetzt

## [0.9.41] - 2026-03-18

### Changed
- Dark Mode komplett überarbeitet: 3-Level Kontrast-System (Page → Panel → Interactive)
- Page-Background: #0D1117 (dunkelster Level)
- Card/Panel-Background: #161B22 (mittlerer Level, klar abgesetzt)
- Interactive/Rows: #1C2128, Alternating Rows: #1A1F26
- Tabellen-Header: #111318, uppercase, letter-spacing 0.08em, font-weight 600
- Text primary: #E6EDF3 (near-white), Text secondary: #8B949E
- Inputs: dunkelster Background (#0D1117), Borders rgba(255,255,255,0.15)
- Cards/Stat-Cards: Border rgba(255,255,255,0.10), Value-Text #F0F6FC
- Filter-Buttons: Border rgba(255,255,255,0.12), Text #8B949E
- Sidebar: #090D13 (noch dunkler als Page)
- Row-Borders: rgba(255,255,255,0.06) — fein aber sichtbar
- GitHub-inspirierte Farbpalette für maximale Lesbarkeit

## [0.9.40] - 2026-03-18

### Changed
- Dark Mode: Text-Kontrast verbessert (--gk-on-surface-variant #94a3b8 → #b0bec5)
- Dark Mode: --gk-text-muted ebenfalls heller (#94a3b8 → #b0bec5)
- Dark Mode: Tabellen-Header mit stärkerem Hintergrund (surface-container-highest statt -low)
- Dark Mode: Tabellen-Streifen sichtbarer (rgba 0.025/0.05 → 0.06)
- Dark Mode: Borders konsistent via CSS-Variablen statt hardcoded rgba-Werte
- Dark Mode: Hardcoded Farbwerte in Button-Tonals durch CSS-Variablen ersetzt

### Fixed
- Doppelte Dark-Mode-Tabellen-Definitionen konsolidiert

## [0.9.39] - 2026-03-18

### Changed
- Cache-Bust für Theme-Fixes (VERSION bump)

## [0.9.38] - 2026-03-18

### Changed
- Design-Switcher kompakter — Dots 14px, Toggle 26px

## [0.9.37] - 2026-03-18

### Added
- text-transform:none für material-icons in gk-label-text

## [0.9.36] - 2026-03-18

### Changed
- Theme-Dots kleiner (28px → 18px), kompakterer Switcher
- Dark Mode: surface colors + body background korrigiert

### Added
- Umfassende Dark Mode Overrides für alle UI-Komponenten

## [0.9.35] - 2026-03-11

### Added
- **Auth-Klasse** — Session-Login mit `Auth::protect()`, `Auth::login()`, `Auth::logout()`
- **Remember Me** — Cookie-basiert (30 Tage, Token-Rotation)
- **Header Avatar-Menü** — mit Auto Theme-Switcher als Default

### Fixed
- Auth: explode(n) → explode("\n") beim Remember-Me Token
- Auth: Newline-Escape in Token-Datei korrigiert

## [0.9.34] - 2026-03-01

### Added
- `.gk-field-hint` — Hilfetext unter Formularfeldern (font-size:12px, text-secondary)

## [0.9.33] - 2026-03-01

### Fixed
- `gk-row`: Alle `gk-w-1` bis `gk-w-16` mit korrektem `flex: 0 0 calc(...)` — bisher fehlten w-1 bis w-4, w-7, w-9 bis w-16

## [0.9.32] - 2026-03-01

### Added
- Breadcrumb: home-Icon Support

## [0.9.31] - 2026-03-01

### Added
- Header: `title(raw=true)` für HTML-Titel
- Sidebar collapsed: Hamburger-Button perfekt zentriert

## [0.9.30] - 2026-03-01

### Changed
- Sidebar collapse-btn: kein Border, zentriert im collapsed-State

## [0.9.29] - 2026-03-01

### Changed
- Theme-Dots: Dark Mode Fix — sichtbare Ränder, aktiver Dot mit Ring

### Added
- Upload-Zone: Client-seitige Größenvalidierung via `data-gk-max-size`

## [0.9.28] - 2026-03-01

### Added
- Header user-menu: html-Item Typ für eingebettete Komponenten

### Changed
- `Form::field(file)`: accept akzeptiert Array oder String; hint, label_text, icon, multiple Optionen
- `gk-upload-zone`: Data-Attribute, Progress/Idle States, `gk:files` CustomEvent

## [0.9.27] - 2026-03-01

### Changed
- Bulk-Bar: Buttons neu gestylt — Löschen rot, Abbrechen weiss-transparent

## [0.9.26] - 2026-03-01

### Fixed
- initSelectable: closest()-Bug beim Checkbox-Change-Listener gefixt

## [0.9.25] - 2026-03-01

### Added
- Table.selectable(): Multi-Delete mit Checkbox-Spalte, Bulk-Bar und gk:bulkdelete Event

## [0.9.24] - 2026-03-01

### Changed
- gk-form max-width: 960px als Standard

## [0.9.23] - 2026-03-01

### Changed
- CKEditor5 v43 API: Plugins explizit deklariert

## [0.9.22] - 2026-03-01

### Changed
- gk-select-search: searchInput null-safe

## [0.9.21] - 2026-03-01

### Added
- gk-select-search als Default für alle Form-Selects

## [0.9.20] - 2026-02-24

### Added
- **CKEditor5 Integration als gk-richtext** — nutzt CKEditor5 statt execCommand-basiertem Editor
- **`.gk-richtext-wrap`** — CSS-Wrapper mit GridKit-konformer Border/Focus-Gestaltung
- **`/vendor/ckeditor5/`** — Lokale CKEditor5 UMD-Build

## [0.9.15] - 2026-02-24

### Added
- Zentriertes Layout: Sidebar, Header und Content bei Viewports > 1400px via `--gk-content-max-width`

## [0.9.14] - 2026-02-24

### Added
- `--gk-content-max-width: 1400px` — Layout-Token für maximale Content-Breite

## [0.9.13] - 2026-02-23

### Added
- gk-modal-large: max-width 860px für große Modals

## [0.9.11] - 2026-02-23

### Fixed
- `gk-form-actions` — Duplikat entfernt, `align-items: center` ergänzt

### Added
- `Form::card()` — optionaler gk-card Wrapper um Formulare

## [0.9.10] - 2026-02-23

### Fixed
- Demo: Tabellen-Labels als dedizierte Caption-Bar statt direkt an Spaltenköpfe

## [0.9.9] - 2026-02-23

### Fixed
- `gk-table-wrap` — box-shadow entfernt, border wie gk-card

## [0.9.8] - 2026-02-23

### Added
- **Tabs** — CSS + JS Tab-System (gk-tabs, gk-tab-nav, gk-tab-btn, gk-tab-panel)

## [0.9.7] - 2026-02-23

### Changed
- `--gk-surface-dim` Light Mode: `#ddd8e4` → `#e8edf2` — neutrales Slate-Grau

## [0.9.6] - 2026-02-23

### Added
- **AJAX Pagination** — `[data-gk-ajax-table="id"]` für seitenloses Blättern

## [0.9.5] - 2026-02-23

### Fixed
- Pagination `gk-page-btn` — display: inline-flex, korrekte Zentrierung

## [0.9.4] - 2026-02-23

### Added
- Table `format => html` — Spalten mit vorgerendertem HTML

## [0.9.3] - 2026-02-23

### Fixed
- Labels Dark Mode — Overrides für gk-label-green/orange/red/gray/blue
- gk-page-header — flex-wrap entfernt

## [0.9.2] - 2026-02-23

### Added
- **Utility-Klassen** — gk-page-header, gk-section-title, gk-spacer, gk-text-muted, gk-grid, gk-form-page, gk-form-actions

## [0.9.1] - 2026-02-21

### Fixed
- Dark Mode Demo-Cards und Table-Borders korrigiert

## [0.9.0] - 2026-02-20

Erster stabiler Stand. Alle Kern-Komponenten vorhanden und getestet.

### Komponenten
- Table, Form, Modal, StatCards, FilterChips, YearFilter, Formatter, Toast, Confirm, Buttons, Header

### Design-System
- 6 Themes: indigo, ocean, forest, rose, amber, slate
- Light & Dark Mode mit CSS Custom Properties
- M3-inspirierte Farbpalette

### Technisches
- Zero Dependencies — reines CSS + Vanilla JS
- PHP-Komponenten: Sidebar, Header, Layout, Theme
- skeleton.php als Startpunkt

---

*Ältere Entwicklungsversionen (0.1–0.7) archiviert.*
