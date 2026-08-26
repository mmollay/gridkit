# Changelog — GridKit

All notable changes to this project are documented here.
Format based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

> Entries up to 1.27.3 are in German — they are the historical record and are
> left as written. From 1.28.0 onwards the changelog is in English.

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
