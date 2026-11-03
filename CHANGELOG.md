# Changelog — GridKit

All notable changes to this project are documented here.
Format based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

> Entries up to 1.27.3 are in German — they are the historical record and are
> left as written. From 1.28.0 onwards the changelog is in English.

---
## [1.114.0] - 2026-11-03

A refined colour system: calmer, tinted neutrals, a deeper accent per theme, a dark mode of its own instead of a GitHub copy, cards that sit like paper on the page — and a theme switcher with a clear choice and a light | dark | auto group.

### Changed
- **One colour system, derived in OKLCH.** A theme is an accent hue and the neutral it is paired with (Radix pairs slate, sage, mauve, sand with their accents); every other value is derived at fixed lightness steps, so all six themes have the same contrast. The neutrals carry the interface and are tinted towards their theme (chroma under 0.02); the accent marks the primary action, the selection, focus and links. Role names are unchanged — only values moved.
- **The six themes, refined, same keys.** `indigo` a deep ink indigo (`#4e5ad7`, was `#6366f1`), `ocean` deep cerulean, `forest` jade, `rose` berry (kept away from the red of errors), `amber` copper, `slate` graphite — a near-black accent (L 0.40). White text on every light accent 4.98:1 (forest) to 9.2:1 (slate). `Theme::available()` carries the real accents, so a swatch shows the colour the buttons will have.
- **Light mode.** Page `#f4f5f8`, cards white with a hairline `#e4e5ea` and a softer two-layer shadow; text `#181a22`, muted text `#565965` (7:1); the sidebar is a neutral surface instead of a tint of the accent. The `--gk-elev-*` and `--gk-shadow*` steps are softer and tinted with the neutral.
- **Dark mode is its own ladder.** Near-black with the theme's hue, layered upward: page L 0.185, card L 0.222, hover L 0.262, menus 0.262–0.300 (indigo: `#111218` / `#191b21` / `#22242b`). The accent turns light (L 0.78) and carries dark text. `themes.css` used to put a second dark palette (slate `#1e293b`) over `[data-gk-mode="dark"]` only, so `.gk-dark` and the attribute looked different; now one ladder for both spellings, with or without `themes.css`. The four surface aliases (`--gk-bg`, `--gk-bg-muted`, `--gk-bg-subtle`, `--gk-bg-hover`) follow their role in dark mode too.
- **Dark component rules read the ladder.** 55 literals from GitHub's palette (`#0d1117`, `#161b22`, `#21262d`, `#2d333b`, `#262d38`, `#1c2128`, `#484f58`) and the indigo `rgba(99, 102, 241, …)` in tonal buttons, uploads, avatars, selectable rows and `.gk-bg-primary-soft` became roles — under every theme, not only indigo. Fields are a recessed step, menus and toasts a raised one.
- **Semantic colours harmonised.** The error role is a true red (`#dd2d34`, was rose `#f43f5e`): white on it 4.67:1, so a badge and the bulk-delete button read; error, danger fill and danger text are one family. Calmer containers for success, warning, error and info at one lightness; info's container follows its sky hue. Success and warning roles are unchanged.
- **Cards (`.gk-card`).** Radius and shadow are tokens; a card with a `gk-card-body` lets its parts do the padding — header and footer lines run edge to edge, the footer is a quiet band, a `gk-card-image` sits flush. `gk-card-title` is semibold in the text colour, in the case it is written in (was the accent in capitals). `gk-card-elevated` keeps a transparent edge, `gk-card-link` lifts 1px with one step of elevation and a firmer edge, and has a focus ring. Dark cards carry a faint light edge along the top.
- **Sortable containers.** The grip is quiet (subtle surface, hairline) and turns to the tonal accent under the pointer and to the accent fill when lifted with the keyboard; the entry whose grip is under the pointer or in focus shows a thin accent ring; the carried entry has the highest elevation and the surface under it; the gap is a dashed slot in a share of the accent. The demo's entries are cards.
- **The theme switcher.** Swatches are the real accents; the chosen one has a ring in its colour, a check mark and `aria-pressed` — never colour alone. The mode is one group, `gk-mode-group`: with `js/gridkit.js` it is light | dark | auto (`gk-mode-auto`, follows `prefers-color-scheme` and keeps following it; stored as `gk-mode-auto` in the `GK.theme` scope). `GK.theme.setMode('light' | 'dark' | 'auto')`. A copy of `gridkit.js` without the 1.114.0 block keeps the plain toggle (the auto button is rendered `hidden`). Markup: both groups carry `role="group"` and a name, every button `type="button"`, a swatch its colour as `--gk-swatch`; old classes and attributes unchanged.

### Tests
- `ci/farben.js`: a fourth section measures the switcher open in the user menu in every theme and mode — ring of the chosen swatch, check mark, focus ring and the three glyphs of the mode group, each ≥ 3:1 (weakest 4.98:1); the four surface aliases moved from "held back" to the asserted pairs; the dark ground it proves is one value now.
- `tests/a11y.test.php`: the switcher's named groups, the hidden auto button, `type="button"`, the swatch colour.
- Updated because their purpose was a value that changed on purpose: `tests/dark-inputs.test.php` (a dark field is `var(--gk-surface-container-low)`, was `#0d1117`), `tests/ortszeile.test.php` (the hash of a header whose user menu carries the new switcher), `ci/reiter-bestand.json` (rewritten after the comparison showed colour values only). Updated to keep their relation: `tests/message-sm.test.php` and `tests/admin-blocks.test.php` read the dark grounds from the one ladder; `tests/contrast.test.php` asserts the four surface aliases and knows `--gk-swatch` as a hook; `tests/table-inline-edit.test.php` reads its block up to the next release's block.

---
## [1.113.0] - 2026-11-03

A form splits into tabs with `Form::tab()`, and nothing in a hidden tab gets lost: a field the browser rejects opens its tab, the server's errors open theirs, and every tab with something wrong carries an error badge with the count. The user's tab survives a failed save and, with `tabsRemember()`, the next visit.

### Added
- **`Form::tab($key, $label, $opts)` and `endTabs()`.** Fields after `tab()` go into its panel; fields before the first tab stand above the tabs, fields after `endTabs()` below them. Options `icon`, `badge`, `count`, `disabled`. The markup is the authored tab shape (each button `type="button"`); every panel is in the page and posted. `tabsStyle('karte'|'linie'|'kapsel'|'text')`, `tabsSecondary()` for a form in a modal, a side sheet or a page tab, `activeTab()`.
- **Server errors pick the tab** (no script needed): the first tab with an error opens and every tab with errors carries `gk-tab-badge gk-tab-badge-error` ("2 errors", `form.tab_errors_one` / `form.tab_errors`, en and de).
- **The hidden field `_gk_tab` (`Form::TAB_FIELD`)** posts the tab on screen; `values($_POST)` hands it back, so a failed save reopens where the user was. `tabsRemember()` keeps it per form id in localStorage when the page chose no tab.
- **`js/gridkit.js` (one block at the end):** a submit the browser blocks for a field in a hidden tab opens that tab first, so the browser focuses the field and shows its own message (first rejected field, one switch per submit; for a submit button, Enter, `requestSubmit()` and `reportValidity()`, never for a script's `checkValidity()`); live error badges; AJAX errors and an empty required rich-text field in a hidden tab open it; `GK.formTabs.refresh(form)`, `GK.formTabs.init(container)`.

### Fixed
- **The required rich-text check** called `_t` outside the closure that defines it and threw before its toast; the toast ("Body is required.") shows again.

### Notes
- `js/gridkit.js`: the 1.113.0 block is new, and the rich-text check changed by one line. A copy without the block (the PawBot dashboard's) still switches the tabs and shows the server's tab and badges; the jump, the live badges, remembering and following the user's clicks into `_gk_tab` are missing.
- `css/gridkit.css`: one rule, 16px between the tabs and the fields after `endTabs()`; no new class.

### Demo
- Form → "Tabs in a form": a customer form with three tabs and a required field in the second, and the same form shown again with server errors.

### Tests
- `tests/form-tabs.test.php` (115 assertions) and `ci/browser-form-tabs.js` (88 cases, light and both dark spellings, 390px, modal, nested page tabs, AJAX, no script, older script). Each of 43 rules taken out turns a case red. `tests/golden-form` unchanged.

---
## [1.112.0] - 2026-11-03

One tooltip engine now draws every GridKit tooltip — title, plain, rich and the new toggletip — placing, flipping and announcing them, with a PHP API and tooltip options on Button, Table and Form.

### Added
- **One engine** (js block "1.112.0"): preferred side, flip to the opposite side, shift inside the window and inside a scrolling box, an arrow that keeps pointing at the trigger; hover delay (300 ms, `data-gk-tooltip-delay`), keyboard focus (`:focus-visible`), a tap on touch; closes on Escape, blur, scrolling away, the pointer leaving (100 ms grace, the bubble is hoverable); one at a time; `aria-describedby` for plain and title tooltips; `prefers-reduced-motion`.
- **Options**: `data-gk-tooltip-pos="auto"`, `-tone` (neutral, info, success, warning, danger, light — 4.5:1 in light and both dark spellings, every theme), `-max`, `-kbd`, `-wrap`.
- **Toggletip** `data-gk-toggletip="text|#id"` and the help icon `.gk-help` (24px, 44px to a finger): opens on click/Enter/Space, a live region after its trigger, the focus stays on the trigger.
- **`GridKit\Tooltip`**: `attrs()`, `rich()`, `toggletip()`, `help()`; bad options are warnings, never a `TypeError`.
- **Component options**: `Button` `'tooltip'`; `Table` column `'tooltip'` (help icon in the header) and row button `'tooltip'` (instead of `title`), both kept after a sort; `Form` field `'help'` (help icon beside the label, `.gk-label-row`). Classes `gk-help`, `gk-label-row`, `gk-tip-arrow`, `gk-toggletip`. Strings `tooltip.help`, `tooltip.help_about` (en, de).
- **JS API**: `GK.tooltip.show(el)`, `hide()`, `update()`, `init(root)`; `GK.tip` runs on the same engine.

### Changed
- `GK.tip`: a title on a control with a name of its own becomes its description, no longer its name. Title tooltips also open on keyboard focus and a tap, and close on Escape rather than on every key.
- README: Tooltip is named among the infrastructure classes (24 classes in `src/`). `tests/js`: `Tooltip::rich()` writes `data-gk-tooltip-rich`, so two hooks are left that only a page author writes.

### Notes
- Without the 1.112.0 script (PawBot's copy) the CSS-only tooltip and `GK.tip` work as before; the script marks `<html data-gk-tt>` and only then the CSS drops its `::after`. No line outside the two new blocks changed in `js/gridkit.js` and `css/gridkit.css`.
- A disabled button cannot show a tooltip; wrap it in a span.

### Demo
- The Tooltip section rewritten: positions, tones, keyboard hint, rich tooltips, toggletips, help icons, the PHP API beside each example; the table shows a column tooltip (VAT) and a row button tooltip.

### Tests
- `tests/tooltip.test.php` (160 assertions); `ci/browser-tooltip.js` + fixture (333 checks: edges, scroll boxes, delays, keyboard, accessibility tree, touch, reduced motion, contrast of every tone, the script before 1.112.0); 42 counter-checks, all red.

---
## [1.111.0] - 2026-11-03

Tabs on a new level: `GridKit\Tabs` writes every style, alignment and option of a tab row from PHP, and tabs gain deep links, remembered tabs, lazy panels, a cancelable `gk:tabchange` and a JS API (`GK.tabs.*`). A line, pill or text row wider than its box shows an arrow where more tabs are.

### Added
- **`GridKit\Tabs`, the eighteenth component.** `->tab($key, $label, $opts)` (`icon`, `badge`, `badgeError`, `count`, `disabled`, `title`, `content`, `url`, `reload`, `href`), `->style()`, `->align()`, `->size()`, `->fluid()`, `->iconTop()`, `->iconOnly()`, `->secondary()`, `->neutral()`, `->fill()`, `->oneRow()`, `->flat()`, `->surface()`, `->active()`, `->label()`, `->hash()`, `->param()`, `->remember()`; `->render()` prints, `->html()` returns. It writes exactly the authored markup the skill documents (plus the row's `id`); with `href` on every tab, a `<nav>` of links with `aria-current="page"`. A wrong value warns (`E_USER_WARNING`) and falls back, never throws; all but `content` is escaped; `javascript:`/`data:` addresses are refused. Active on the server: `active()`, else the parameter, else the first tab that is not disabled.
- **Deep links and memory** (opt-in): `hash()` keeps `#<id>=<key>` beside other parts of the fragment (replaceState — Back leaves the page; hashchange and Back/Forward between links switch; a nested row opens its outer panel; a key no tab can take is corrected); `param('tab')` opens a tab without script and is rewritten in place; `remember()` uses `localStorage` (`gk-tab-<id>`, `GK.theme` scope). On load: `active()`, fragment, parameter, memory, first tab.
- **Lazy panels**: `'url'` fetches on first view (same origin, `X-Requested-With`), skeleton `gk-tab-loading` with `aria-busy`, kept, `GK.initContent()` on the answer; `'reload' => true` fetches every view, old content dimmed meanwhile; failures show the table's failure state with "Try again", never the server's answer; until opened the panel holds a link (`gk-tab-fallback`), which is what an older gridkit.js shows.
- **Events**: `gk:tabchange` (cancelable; `{ tabsId, from, to, panel, source }`), `gk:tabshown`, `gk:tabload` — for click, keys, `GK.tabs.select()` and the address; a refused key switch hands the focus back.
- **JS API**: `GK.tabs.select()`, `current()`, `badge()`, `count()`, `reload()` — on `Tabs` output and hand-written markup.
- **A row wider than its box** (line, pill, text) shows round arrows at the sides that have more (`gk-tab-arrow`, `gk-tab-arrow-start`, `gk-tab-arrow-end`); `GK.tabsOverflow` measures the row and sets `gk-tab-nav-overflow`. The chosen tab is scrolled into view; the card style keeps its grid.
- Strings `tabs.open`, `js.tab_loading`, `js.tab_load_error` (en, de).

### Changed
- The skill's authored tab markup writes `type="button"`. README and landing page count eighteen components.

### Fixed
- Demo: the sortable list keeps its distance from the sortable grid again (`.demo-sort-grid ~ .demo-sort-list`; the live region `js/sortierbar.js` adds after each container broke the `+` selector).

### Notes
- `js/gridkit.js` and `css/gridkit.css`: one block each at the end, no existing function or rule changed. A copy without the block (PawBot) shows `Tabs` output as hand-written tabs; a lazy panel shows its link.

### Demo
- Tabs from PHP (each beside its code), deep links and memory, lazy panels (`demo/api/tab-panel.php`), a refused switch, the JS API, a row wider than its box; the builder shows the `Tabs` call. The demo reads its section from the part of the fragment without `=`, so `#tabs-seite`-style anchors no longer empty the page.

### Tests
- `tests/tabs-api.test.php` (212 assertions); `ci/browser-tabs.js` + fixture (153 cases, light and both dark spellings, 390px, the script before 1.111.0); 61 counter-checks, 60 red, 1 equivalent.

---
## [1.110.5] - 2026-11-03

A group of outlined buttons is closed on the right again, and a page title no longer sits on the pills of the line "you are here" beneath it.

### Fixed
- **`Button::group()` of outlined buttons stood open on the right.** In `.gk-btn-group` every button gives up its right edge so that two edges never stand side by side; the last one has no neighbour, and without its edge the group's rounded corners ran out into nothing (the demo's "Button Group": "Left · Center · Right", "Undo · Redo"). The last outlined button keeps its right edge, as wide as its other edges. Filled, tonal and text buttons, which draw no edge, and a bordered group (`.gk-btn-group-bordered`), which draws its own frame, are unchanged.
- **The header title stood on the line "you are here"** (`Header::title()` with `ortszeile()`, 1.100.0): the title and the line are one column, and the column had no gap, so the title's descenders touched the outline of the pills. 6px between them now. On a phone, where the title column steps aside and the line takes a row of its own, nothing changes.

### Tests
- `tests/button-group.test.php`: the last outlined button of a group keeps an edge as wide as a button's; the rule comes after the one that takes the right edge, and a filled button at the end still carries none.
- `tests/ortszeile.test.php`: the gap between the title and the line.
- Both measured in a browser before and after, light and dark, 1280px and 390px (the right edge 0px → the width of the others; the gap 0 → 6px at 1280px, unchanged at 390px). Taking either rule out turns its test red.
- `tests/blocks.test.php`: a block outside the window of the marks is checked by computation, every block, instead of by name — naming the line "you are here" as out for good failed as soon as this release touched it. Whole suite: 9,865 assertions.

---
## [1.110.4] - 2026-11-03

The demo now says where something is new: beside each section in the sidebar ("New" or "Update", with which components and since when), and in a list by component at the head of the changelog. This release also carries the code of 1.110.3 to Composer under a version Packagist can publish — use 1.110.4, not 1.110.3.

### Changed
- **The sidebar of the demo marks the sections with news.** A section that shows a block new or changed in the last two minors (the window of the marks on its headings) carries "New" (green) or "Update" (blue) after its name; the popup and a screen reader name the components and the minor ("Changed in 1.110: Table"). Collapsed, the sidebar shows a dot on the symbol. Computed from `css/blocks.json` and `CHANGELOG.md` like the marks (`gkSectionNews()`, `gkNavNews()` in `demo/_whatsnew.php`); `src/Sidebar.php` is not touched (the dashboard keeps its own copy).
- **"New & changed by component"** opens the changelog section: every block new or changed in the last six minors, newest first, with "New" or "Update", the exact version and a link to the section of the demo that shows it (`gkComponentNews()`, `componentNews()`). German on `?lang=de`.
- `css/blocks.json`: "Themes and dark mode" is marked as changed in 1.110.3, where `Theme::density()` came to reach the padding of a table.

### Notes
- **Packagist and 1.110.3.** The tag `v1.110.3` was first pushed on the commit of 1.105.0 (`d2bb6a6`) and moved to the release (`86d339a`) minutes later. Packagist had already published the first one, and a published version never changes there: **`mmollay/gridkit` 1.110.3 from Composer is the code of 1.105.0.** 1.110.4 is the code of 1.110.3 and the demo change above. A project that installed 1.110.3 by Composer should `composer require mmollay/gridkit:^1.110.4`. The git tag `v1.110.3` may be set back to `d2bb6a6`, as Packagist asks, so that git and Packagist say the same.

### Tests
- `tests/demo-news.test.php` (95 assertions): every section the sidebar marks carries the same mark on a heading, each exactly once, and no section without news is marked; the mark's word, kind, popup and the text for a screen reader, English and German; the list holds exactly the blocks new or changed in its span, newest first, each with its version and its link; the demo uses both.
- Whole suite: 9,785 assertions.

---
## [1.110.3] - 2026-11-03

`Theme::density()` now sets the padding of every table, not only the corners of its labels, and the demo page no longer scrolls sideways at any width. A table in the box of `stickyColumn()` keeps everything it holds inside that box.

### Fixed
- **`Theme::density('konsole' | 'weit')` reaches the padding of a table.** Its steps are tokens in `css/themes.css` (`--gk-table-pad-y`, `--gk-table-pad-x`, `--gk-label-radius`), but the sizes (`.gk-table-sm`, `-md`, `-lg`) and the padding variants (`padded`, `compact`, `very-compact`, `xs`) set their padding in pixels, at the weight of the token rule and after it: on a page set to `konsole` a table drawn by `Table` got its 4px labels and kept every pixel of its padding. The rules of 1.109.0 that write those paddings against the tokens for a table with `densityToggle()` now hold under a page with a step as well (a second selector in each `:where()`, so their weight is unchanged), and so do the list's row and the phone's paddings and card. A table's own step (`densityToggle()`) still wins over the page's; a page without a step, or without `themes.css`, is unchanged.
- **`stickyColumn()` without `stickyHead()` pushed the page sideways.** The box a table scrolls in was not positioned, so an absolutely positioned descendant whose cell does not stick — the screen-reader name of the empty header cell over the right buttons — was placed against the wrap or the page, outside the box's clipping. On the demo page that made a 1024px window scroll 86px sideways. The box is `position: relative` now.

### Demo
- **No sideways scroll from 320px to 1600px**, measured every 10px. Before, a 1280px window beside the sidebar scrolled 18px (57px before 1.110.1), a 900px window 110px, 1100px 11px, 769px 8px and 320px 4px. The article list, the invoice list and the selection example are wider than the content beside the sidebar at some of those widths and now use `stickyColumn()`: they scroll in a box of their own with the first column in front, and do not touch the page. The grid of the three sizes asks for at least `min(300px, 100%)` per table instead of 240px, which `size('lg')` did not fit in.
- The demo was checked in a browser at 1280px and 390px, light and dark: no script error, the four sections of 1.107.0 to 1.110.0 (sticky head and column, columns to show and hide, row spacing, editing in the cell) render, and "What is new" lists the newest releases.

### Tests
- `ci/browser-density.js` (254 cases, 2 new): on a `konsole` page with `themes.css` every size and variant without the control measures, cell for cell, as the same table set to Compact by its control; a table without the control takes 8px 12px there (it kept 12px 16px, which the case of 1.109.0 had written down as intended), and without `themes.css` it keeps the default. Without the change both new cases turn red.
- `tests/density-toggle.test.php`: the selectors of the rules that write the paddings against the tokens carry the page's step as well.
- `ci/browser-sticky.js` (419 cases, 2 new): whatever is positioned in a box is placed against the box (red without `position: relative`). `tests/sticky.test.php` holds the rule.
- Whole suite: 9,683 assertions; every browser check of the table green.

---
## [1.110.2] - 2026-11-03

Housekeeping: `Table::render()` and `Table::renderButtons()`, the two longest methods left in the table, are cut into parts. Nothing a page can see changes — no class, no option, no markup, byte for byte — and a recording of 30,819 renderings holds that to account. One fault found on the way is fixed: a row button with a number for a name no longer stops the table.

### Changed
- **`Table::render()`** from 191 to 30 lines; it reads as the order of what it writes: `wrapOpenTag()` (with `wrapClasses()`), `renderDataBlock()`, `renderToolbar()` with `renderSearchField()`, `renderFilter()`, `renderCsvButton()` and `renderNewButton()`, `renderBulkBar()`, `renderInner()`, `renderModalTemplates()`. `columnMenu()` is still asked outside the toolbar: without one, it is what warns that a `'hidden'` column shows.
- **`Table::renderButtons()`** from 146 to 6 lines: `buttonShows()` (`showIf`, `hideIf`), `renderButton()` for one button, and `buttonColor()`, `buttonConfirm()`, `buttonName()`, `buttonHref()` and `buttonOnclick()`. The `data-gk-params` attribute is built once instead of three times, and the four near-identical tag writes are one. The button name is passed as `int|string`: a button named `'1'` has the key 1. Every expression that can warn or throw runs in the order it did before. The longest method of the class is now `renderButton()`, 70 lines, about 30 of them comments.

### Fixed
- **A row button named with a number** (`->button('3', ['icon' => 'edit'])`, no `aria` and no `title`) threw a `TypeError` and the table did not render: its fallback name went through `str_replace()`, which under `strict_types` (1.104.3) refuses the int key PHP makes of `'3'`. The name is cast, as `renderBulkActions()` already did; the button is named "3".

### Tests
- `tests/golden-table` and `tests/golden-form` pass unrecorded. A recording of 30,819 renderings was made before the change and compared after every step: every option of `Table` alone and in pairs, `setData()`, `rows()`, empty, the AJAX fragment, sort, page, search, filter and hostile parameters, English and German, and hostile values in every button and toolbar option, alone and two at a time. It compares the markup, every warning in order with the byte offset at which it was raised, and every exception. The recording is identical. Two deliberate slips, evaluating a button's icon earlier and asking for the column menu only with a toolbar, each turned it red.
- `tests/strict-types.test.php`: a numeric button name renders and is named after its number; without the cast it throws.
- Whole suite: 9,682 assertions. The browser checks give the numbers they gave before (140, 29, 271, 417, 65, 252, 96, demo coverage ok). The demo page renders byte for byte as before in both languages, apart from the random ids.

---
## [1.110.1] - 2026-11-03

A table with buttons on the left had every heading one column before its cells; each heading now stands over its own column, as the server writes it and after a sort. And `columnToggle()` hides its columns in the box of `stickyHead()` and `stickyColumn()` too.

### Fixed
- **The header of a table with `'position' => 'left'` buttons.** `Table::renderTableHead()` wrote the empty cell over the left buttons at the end of the header row, beside the one over the right buttons, while every row writes its left buttons before the first column; `renderStatic()`, which redraws a `setData()` table after a sort, a search or a page, did the same. So "Name" stood over the buttons, "City" over the names, and so on — in the demo's invoice list, for one. The cell now stands after the checkbox and before the first column, in PHP and in the browser. `tests/golden/table.json` is re-recorded for the three cases with left buttons (`table/buttons`, `table/buttons-selectable`, `table/group-labels`); the diff is that one cell moving, nothing else.
- **`columnToggle()` in the sticky box (1.108.0 beside 1.107.0).** The hiding rules and the script looked for the table as a child of the wrap; `stickyColumn()` and `stickyHead('…')` put it in `.gk-table-sticky-box`, and a hidden column showed. The 60 rules exist once more for a table in the box, and the script finds the table there. `columnToggle()` read the header's places apart from the rows' because of the fault above (`data-gk-col-head`); the attribute stays, with the same number as `data-gk-col-pos`.

### Notes
- **`js/gridkit.js`: two lines in `renderStatic()`**, the cell over the left buttons written before the columns instead of after them. A copy of the file without them, such as the PawBot dashboard's, now draws the header correctly on the server and wrongly after the first sort: port the two lines (search for "1.110.1" in `renderStatic`) to keep the two in step.

### Tests
- `ci/browser.js` (140 cases, 2 new): a `setData()` table with a checkbox and buttons on both sides; every heading over its own cells as the server writes it, and after a sort. Each half of the fix taken out alone turns its case red.
- `ci/browser-column-toggle.js` (65 cases, 4 new): a table in the sticky box with a hidden column — hidden in the header and the rows, every row as wide as the header, a column unticked, and hidden on the first paint without a script. Without the new rules the last case turns red, without the script's change the two before it.
- `tests/column-toggle.test.php`: the header's place is the rows' place; the 60 rules for the sticky box are there.
- A table with all four features of 1.107.0 to 1.110.0 at once (a checkbox, buttons on both sides, a sticky head and column in a box, the column menu with a hidden column, the density toggle, inline editing) was driven in a browser: the hidden column hidden, editing after a sort saved, the density set, no script error.

---
## [1.110.0] - 2026-11-03

A table cell can be changed where it stands: `Table::editable()` and a column's `'edit'` turn it into a field on a click or on Enter, save it on Enter or when the field is left, fire `gk:celledit`, and post the change when the table names a URL. A table without an editable column renders exactly as before.

### Added
- **`Table::editable($key = 'id', $url = '', $opts = [])`**: inline editing. `$key` names the record (written as `data-gk-row-id`, posted as `id`; with `selectable()` under another key the selection's key is used and GridKit says so); `$url` is where a change is POSTed; option `headers` adds request headers (a CSRF token). Every editable cell is in the tab order and points at a hidden sentence that says it can be edited; Enter or F2 opens it, Enter and leaving the field save, Escape restores, Tab moves to the next editable cell. The field holds the raw value (`1200.5`, not `€1,200.50`): a `setData()` table has it in its data block, a `rows()`/`query()` table writes it as `data-gk-raw` on editable cells.
- **Column options `'edit'`** (`'text'`, `'number'`, `'select'`; `false` is read-only) and **`'options'`** (value => text for a select; a `label` column offers the keys of its `labels` without it). Refused with a warning, the column staying read-only: `'format' => 'html'` and `'email'`, a column with `'href'`, the column of `rowLink()`, an unknown type, a select with nothing to choose from, `'edit'` without `editable()`.
- **`gk:celledit`** on the table, cancelable, with `{ tableId, id, column, value, oldValue }`; `preventDefault()` restores the old value, `event.detail.respondWith(promise)` answers in place of the URL. The request is a JSON POST of `{ id, column, value }`; `{ ok: true, value?, display? }` saves, anything else (another status, `{ ok: false, error }`, no JSON, no network) restores the old value and shows the reason as text. While saving the cell is `aria-busy` and cannot be opened again. A saved value is formatted the way the column formats it (by the code that redraws a sorted table); a `setData()` table keeps it for the next sort, search, page and CSV export.
- New strings: `table.edit_hint`, `js.edit_error`, `js.edit_number`, `js.edit_saved` (en, de).

### Demo
- "Editing in the cell" above "Mobile-Responsive": text, number and select columns; the page has no server, so the listener answers with `respondWith()` after a moment and refuses a negative price.

### Tests
- `tests/table-inline-edit.test.php` (131 assertions): the markup of editable cells and rows in a fixed order, raw values, escaping, the AJAX fragment, select options, every refusal, and that a table without an editable column is byte for byte the same.
- `ci/browser-inline-edit.js` (96 cases) with `ci/browser-inline-edit-fixture.php`: pointer and keyboard, the event and its veto, `respondWith()`, the request and every kind of answer (answered by `page.route()`), the rebuild after a sort, a page and a search (the same ids and attributes as PHP writes), a server table, a selection, a row link, no column moving when a field opens, a card at 390px, both dark spellings, forced colours. Each of 79 rules of the script, the stylesheet and `Table` was taken out in turn and a check turned red.
- Whole suite: 9,615 assertions; every browser check of 1.107.0 to 1.109.0 as before, with the four blocks side by side in `js/gridkit.js` (each wraps `GK.table.renderStatic` or `GK.table.init` in turn; the wrappers chain).

### Notes
- **`js/gridkit.js`: one block at the end, no line elsewhere.** It wraps `GK.table.renderStatic` and `GK.table.reload` and changes neither. A copy without it, such as the PawBot dashboard's, shows an editable table read-only.
- **No new class:** an editable cell is `td[data-gk-edit]`, an open one `td[data-gk-editing]`, a saving one `td[aria-busy="true"]` — a dotted line under the value, solid on hover, a focus ring.
- A date column redrawn in the browser after a save uses the browser's format, as a sorted table always has; a server can send `display` to keep its own.

---
## [1.109.0] - 2026-11-03

A table can carry Compact · Normal · Roomy in its toolbar, and the person at the screen chooses how tightly it is set; the choice stays with the table in this browser. It uses the steps of `Theme::density()`, needs `gridkit.css` only, and a table without the option is unchanged.

### Added
- **`Table::densityToggle()`**: a segmented control (`.gk-segmented`, three `aria-pressed` buttons in a labelled group) in the toolbar, after the search, the filters and the CSV button. Compact is `konsole` (8px / 12px, labels with a 4px radius), Roomy is `weit` (16px / 20px), Normal the default (12px / 16px). The script writes the step on the table's wrap as `data-gk-density` and remembers it per table id in `localStorage` (`gk-density-<id>`, in the namespace of `GK.theme`); where storage is blocked it holds for the page view. It survives sorting, searching, paging and the AJAX reload; a table handed to `GK.initContent()` later gets it too. New strings: `table.density`, `table.density_compact`, `table.density_normal`, `table.density_roomy` (en, de).
- **Every kind of table takes the step.** Each size keeps its distance from `md` (`sm` 6px tighter, `lg` 4px roomier, `xs` with a 2px floor), and so do the variants `compact`, `padded` and `very-compact`. A `size('list')` row goes from 64px to 52px or 76px and its columns stay. On a phone the scroll-mode cells and the compact card (its padding, the gap between cards, the space round a field) move by the same step; the head of a card keeps its spacing and a card with a link its 40px. A row link stays at least 44px and the row buttons keep their size.

### Notes
- **Normal is GridKit's default rhythm, not the page's.** The control starts at the page's own step: on a page with `Theme::density('konsole')` it starts at Compact, and Normal is roomier than the rest of the page. Choosing the page's own step forgets the stored choice, so the table follows the page again. On a page without a density, Normal leaves no attribute on the wrap: the table is then exactly the table without the toggle.
- **`themes.css` is optional.** The steps are in `gridkit.css` as tokens on the wrap (block "1.109.0 — density toggle") with the numbers of `themes.css`, which `tests/density-toggle.test.php` holds them to. Nothing in the block matches without `data-gk-density` on a `.gk-table-wrap`, so `Theme::density()` and `themes.css` behave as before.
- **Found on the way, left as it is:** `.gk-table-sm/-md/-lg td` set their padding in pixels at the weight of the token rule and after it, so the page-wide `Theme::density()` has never changed the padding of a table drawn by `Table`, only its label radius. The toggle's steps do reach the padding.
- **Without JavaScript** the control is not shown (it is rendered `hidden`); a copy of `js/gridkit.js` without the 1.109.0 block, such as the PawBot dashboard's, leaves it hidden and the table as it was. `js/gridkit.js` gets one block at the end that wraps `GK.table.init`. No new class, and no change to the markup of a table without the option (golden tests unchanged).

### Demo
- "Row spacing, chosen at the screen" in the table section, above Mobile-Responsive.

### Tests
- `tests/density-toggle.test.php` (120 assertions): the markup, the labels and their escaping; with the option a table renders as without it plus the control (five kinds of table), and the AJAX fragment is unchanged; the steps against `themes.css` and `:root`; every padding written against the tokens adds up at Normal to the pixels of the rule it stands for (14 size and variant rules, the phone, the card); the list row; every rule of the block needs the attribute on the wrap; the script is one block after 1.105.0 and touches `localStorage` only inside `try`.
- `ci/browser-density.js` with `ci/browser-density-fixture.php` (252 cases): thirteen tables, each with the control and without it. At Normal each measures as its twin, cell by cell; every step per size and variant, with and without `themes.css`; a `konsole` and a `weit` page, a reload, the namespace of `GK.theme`, a stored value that is not a step, sort, search, AJAX reload, `GK.initContent()`, the keyboard, contrast in light and both dark spellings, `localStorage` that throws, 390px, no JavaScript, the older script, a script added late. Each of 46 rules and branches was taken out in turn and the run turned red.
- Whole suite: 9,451 assertions; every browser check of 1.107.0 and 1.108.0 as before, with the three blocks side by side in `js/gridkit.js` and `css/gridkit.css`.
- `tests/column-toggle.test.php` and `tests/sticky.test.php` read their block of the stylesheet and the script up to the heading of the next block, not to the end of the file: with 1.109.0 after it, the column toggle counted the storage calls of the density toggle as its own.

---
## [1.108.0] - 2026-11-03

A table can let each user choose its columns: `columnToggle()` puts a "Columns" button in the toolbar whose menu hides and shows them, and the browser remembers the choice per table. A hidden column stays hidden through a sort, a search, a page change and an AJAX reload, a column can start hidden or be kept on screen, and the CSV holds what the table shows. A table that does not call it is written byte for byte as before.

### Added
- **`Table::columnToggle()`**: a "Columns" button in the toolbar ("Spalten" in German) that opens a menu with one checkbox per column. Unticking one hides the column — its header, its cells and a footer cell that stands alone over it; group headings, the empty state and a footer cell over several columns get narrower. The choice is remembered in `localStorage` (`gk-cols-<id>`) as what differs from the page's defaults, so a column added or a default changed later still reaches a returning user; storage that is missing or throws remembers nothing and breaks nothing. The last column shown cannot be unticked.
- **Column option `'hidden' => true`**: the column starts hidden (a stored choice wins). It is hidden in the server's markup, so nothing flashes before the script runs.
- **Column option `'toggle' => false`**: the column cannot be hidden. It stays in the menu, ticked and disabled, so the list still reads as the table's columns. The column that carries `rowLink()`'s control is kept the same way: hidden, the row would lose the one control the keyboard reaches.
- **The menu is a native popover** opened by `popovertarget`, in the top layer, so no card with `overflow: hidden` cuts it off; Escape and a click elsewhere close it. The script puts it under its button and inside the window, moves the focus into it and back to the button, keeps `aria-expanded` in step, claims Escape (a modal the table stands in stays open), closes the menu when the focus leaves it, and moves between the boxes with the arrows, Home and End.
- **The CSV holds what the table shows.** A `setData()` table leaves hidden columns out of the file of `exportCsv()`, as it leaves out `'export' => false`. A server table's export button adds `?gk_hidden=email,role`, and `Table::sendCsv()` leaves those keys out of the `$columns` it is given (a key it does not have changes nothing; `Table::csv()` does not read the request). Search and sort do not change: the search looks at its keys whether their columns show or not.
- New string `table.columns` (en "Columns", de "Spalten"); new classes `gk-col-toggle`, `gk-col-toggle-menu` and `gk-col-hidden` (block "Column toggle").

### How it works
- **The hiding is CSS, keyed on the checkboxes.** An unticked box hides the cells at its place in a row (`data-gk-col-pos`), its header (`data-gk-col-head`) and the footer cell over it (`data-gk-col-foot`), through `:has()` and `:nth-child()` rules for twenty places. The rows are written exactly as before, so `renderStatic()` and the AJAX reload redraw them and the column stays hidden without anything re-applied; the toolbar that holds the menu survives both. The header has a place of its own because `Table` writes the header cell of the left buttons at the end of the header row, while a row writes its left buttons before the first column.
- **The script** — one block at the end of `js/gridkit.js`, no line in an existing function — restores and stores the choice and, after every redraw (a `MutationObserver` on the table, before the next paint), marks hidden cells `.gk-col-hidden` (which also hides a column past the twentieth place) and narrows what spans the row (`data-gk-span` keeps the original colspan). It wraps `GK.table.init` (tables that arrive through `GK.initContent()`) and `GK.tableCsv`, and takes the export click of a server table in the capture phase.
- **On a phone** a hidden column is left out of the card. When it was the card's title, the next column that shows and is not muted takes the title, and a card whose visible fields are all muted takes the first of them: the title rules of 1.106.0 once more, passing over `.gk-col-hidden`.
- **Without the 1.108.0 script** (the PawBot dashboard's copy, or no script at all) the menu still opens and its checkboxes still hide and show columns; nothing is remembered, group headings keep their full colspan (drawn without a gap), and a card whose title column is hidden has no title line.
- Warnings (`E_USER_WARNING`, and the column shows): `'hidden'` or `'toggle'` that is not `true` or `false`; `'hidden'` without `columnToggle()`, on a `'toggle' => false` column or on the `rowLink()` column; `columnToggle()` with `toolbar(false)`.

### Demo
- "Columns to show and hide", above "Mobile-Responsive": the article list with the unit and the VAT hidden, the article number fixed, and CSV export.

### Tests
- `tests/column-toggle.test.php` (153 assertions): the markup of the button and the menu; the places past a checkbox and left buttons, the header's own place, footer cells over one column and over several, a colspan of 0; escaping of labels, keys and ids, a safe and distinct menu id; German; a table without the menu byte for byte as before (`columnToggle(false)` too), the rows of a table with the menu unchanged, its AJAX fragment identical with or without a hidden column; every warning; `sendCsv()` with `gk_hidden`; the rules, the card rules, both dark spellings; the script as one block at the end with its storage guarded.
- `ci/browser-column-toggle.js` with `ci/browser-column-toggle-fixture.php` (61 cases), see `ci/README.md`. Each rule of the CSS block and each branch of the script and of the PHP was taken out in turn (61 mutations) and the run or the PHP test turned red. On the way they found a fault: a menu at the right edge was measured while squeezed by its own previous position, and stayed against the window's edge instead of moving left.
- Whole suite: 9,315 assertions (9,132 at 1.107.0). `ci/browser.js` (138), `ci/browser-1105.js` (29), `ci/browser-karten.js` (271), `ci/farben.js` and `ci/demo-coverage.js` (609 of 783 classes shown) as before.

### Notes
- Found on the way and left as it is: with left buttons, the header of every column stands one place before its cells — `Table::renderTableHead()` and `renderStatic()` write the header cell of the left buttons at the end of the header row. `tests/golden/table.json` pins that markup; it wants a release of its own.

---
## [1.107.0] - 2026-11-03

A long table keeps its header row in view while its rows scroll, and a wide one its first column while it scrolls sideways. Two methods, `Table::stickyHead()` and `Table::stickyColumn()`; a table without them renders exactly as before, and in card mode on a phone nothing changes.

### Added
- **`Table::stickyHead($maxHeight = '')`.** Without an argument the header row sticks to the top of the page (or of whatever scrolls around the table): `.gk-table-wrap` keeps `overflow: visible`, so the page is the scroller. Under a fixed or sticky GridKit header it stops at 56px (`--gk-sheet-top`); `--gk-table-sticky-top` on the wrap or above sets another offset. With a CSS length (`'70vh'`, `'480px'`; a number with `px`, `vh`, `dvh`, `svh`, `lvh`, `rem`, `em` or `%`) the table scrolls in a box of at most that height and the head sticks to the top of the box. Anything else is refused with a warning (`E_USER_WARNING`) and never reaches the page; the head then sticks to the page.
- **`Table::stickyColumn()`.** The first column stays in view while the table scrolls sideways, and so do the cells in front of it: the checkbox of `selectable()` and the buttons with `'position' => 'left'`. The table gets a box that scrolls sideways; with `stickyHead()` as well the head can no longer stick to the page (the box is a scroller in both directions), so the box gets the height given to `stickyHead()`, or 70vh. A hairline marks the edge of the column while the box is scrolled. Footer cells stick under their columns while every cell before them is one column wide; group headings and the empty state span the row and do not stick, and the empty state stays in the middle of the box instead of the middle of a wide table.
- **Markup:** the wrap carries `gk-table-sticky-head` and `gk-table-sticky-col`; the box, `gk-table-sticky-box`, stands around the `<table>` only (the pager stays below it, in view) and carries `data-gk-sticky-col` (how many cells of a row stick) and its height as `--gk-table-sticky-height`. It is a region named by `caption()`, or "Scrollable table" (`table.scroll_region`, English and German), with a tab stop, so the keyboard scrolls it.
- **Script** (one block at the end of `js/gridkit.js`): it measures the cells in front of the sticky column and writes their offsets (`--gk-table-sticky-left-2`, `-3`) on load, after every rebuild or reload and when a cell changes its width (ResizeObserver); marks a box scrolled sideways; keeps a box's scroll position through a sort, a search, a page change and an AJAX reload; and puts a `setData()` table back into its box after `GK.table.renderStatic()` rebuilt it (wrapped, not changed). A copy of the file without the block still renders a sticky table: the head and the first cell stick, the cell after a checkbox at 36px, a column after left buttons stays put.

### Changed
- A sticky table draws its borders separate (`border-spacing: 0`), not collapsed. A collapsed border stays where its cell was: measured in Chromium, the bottom 5px of the first row showed above a bordered head, and in dark mode every row line under a sticky cell showed what scrolled beneath it. At rest the table looks as before: in light mode pixel for pixel, in dark mode every cell within 2px (collapsed translucent lines made the plain dark table 2px taller). Sticky cells take the surface of the table and the colour of their row — selected, striped, hovered, focused, the dark even rows, inverted, the row of an open side sheet — so nothing shows through them. In a box the last row's first cell is square instead of rounded to the corner of the wrap (the box clips to that corner itself), or its corner would show what scrolls under it.

### Demo
- "Sticky head and column" in the table section: fifteen articles with a checkbox, ten columns, a box of 320px.

### Tests
- `tests/sticky.test.php` (191 assertions): the markup of every combination, the cells counted, the box only around the table and in the AJAX fragment, every accepted length and the refused ones (warning, nothing on the page, the value not quoted raw), the caption as the region's name, and that a sticky table is the plain one plus its classes and its box, byte for byte; the rules and the script block.
- `ci/browser-sticky.js` with `ci/browser-sticky-fixture.php` (417 cases, light and dark): where the head and the column cells stand after scrolling, on the page, under a fixed header, in a box; nothing shows through a sticky cell in any row state (everything that does not stick painted magenta, no magenta pixel in a screenshot of the sticky part, scrolled 300px and to the far end); a sorted, searched and paged `setData()` table and an AJAX reload keep the box, its offsets and its scroll position; a cell that grows, a table added later, a script added after the page was ready; Tab and the arrow keys; at rest a sticky table looks like its twin without the options; card mode at 390px identical to its twin; `mobile('scroll')` and a list; a `gridkit.js` without the block. Each rule and branch of the implementation was taken out in turn and the run turned red (79 counter-checks: 50 rules of the stylesheet, 13 branches of the script, 16 of Table.php).
- `tests/contrast.test.php`: `--gk-table-sticky-top` is a hook a page sets, like `--gk-main-max`.
- `tests/blocks.test.php` needed no edit for the new window (1.107 and 1.106): it finds the blocks the window marks since 1.106.0. The table is marked as changed in 1.107 by its three new classes.

---
## [1.106.0] - 2026-11-03

On a phone a table card is half as tall: its first line is a head with the title beside the row's buttons, and the other fields follow without rules. An invoice of six fields with buttons on both sides was 386px tall, one and a half cards on a screen; it is 196px now. CSS only: no class, no option and no markup changes, and the client rebuild of a table is untouched.

### Changed
- **Card mode (`mobile('card')`, the default below 768px) is compact.** The row is a grid of four tracks: checkbox, left buttons, title, right buttons; a track without a cell is 0px wide. Every other field spans the card below the head, label and value on one line, without the rule that stood under each of them. The head ends in one rule; a card that is nothing but its head has none.
- **The title** is the first column that is neither `'muted'` nor `'hideOnMobile'` — an article number steps back for the name — and is shown without its column label. On a row with `rowLink()` it is the cell that carries the link (as before, without a label). When every column is muted, the first one is the title. A long title wraps instead of running under the buttons; a second line (`'sub'`) stands under it, left-aligned.
- **The buttons** moved from a line of their own at the bottom of the card into the head; the checkbox of `selectable()` from the right to the left. Group headings, the summary row, the empty state and the footer are not cards of fields and keep their layout. A card with a link keeps 40px on the right for its chevron.

### Fixed
- In card mode the checkbox cell carried an empty column label with 12px of gap before the box, and the browser's 4px margin on the box: the checkbox sat 16px off the edge the labels start at. Neither is there now.

### Demo
- The card example marks its article number `'muted'` and has a button, so it shows the title and the head; the intro of the section says how the title is chosen.

### Tests
- `ci/browser-karten.js` with `ci/browser-karten-fixture.php` (271 cases): eleven tables in card mode at 390px, light and dark, measured box by box — see `ci/README.md`. Each rule of the block was taken out in turn (the grid, the track of the left buttons, the title of a row with a link, the column of the title that must not wrap, the fallback when every column is muted, the head without a rule, the padding of the checkbox) and the run turned red.
- `tests/mobile.test.php`: the rules of the compact card are in the 768px block (10 assertions).
- `tests/blocks.test.php`: the window of the demo's marks is 1.106 and 1.105 now. `css/blocks.json` marks the table and the row link as changed in 1.106.0, so their headings say "Changed in 1.106"; the tabs and the line "you are here" (1.104) fell out. The case "a block that got a new class in the newest minor is marked as changed" no longer depends on a block of the window having one: it builds a blocks file of its own.

---
## [1.105.1] - 2026-11-03

In dark mode the table search has its magnifier again, and every filter, page-size select and form select has its arrow. Only the background colour of these fields was meant to change in dark mode, and the rule took their icon with it.

### Fixed
- **Dark mode removed the icon of 24 fields in the demo.** The magnifier of `.gk-search` and the arrow of `.gk-filter` (select, date and number), `.gk-pagesize-select` and `select.gk-input` are background images. Two dark-mode rules, `[data-gk-mode="dark"] input, select, textarea` (and `.gk-dark …`) and `[data-gk-mode="dark"] .gk-input`, wrote `background: #0d1117`. The shorthand also sets `background-image` to `none`, and both selectors outweigh the single class that draws the icon. Both rules write `background-color: #0d1117` now; the colour of the fields does not change. Compared in a browser over all 6811 elements of the demo, light against dark: before, 24 fields had an image in light mode and none in dark; now none. The only remaining differences are the range slider and `gk-skeleton`, whose gradients use colour tokens that differ between the modes, as before.

### Tests
- `tests/dark-inputs.test.php` (18 assertions): the three classes still draw their icon; no dark rule that reaches a bare `input`, `select` or `textarea`, or `.gk-input`, `.gk-search`, `.gk-filter` or `.gk-pagesize-select`, writes the `background` shorthand without `background-image`; both rules keep `#0d1117`. Putting the shorthand back in either rule turns it red.

---
## [1.105.0] - 2026-11-02

Four things an admin page keeps writing by hand are methods now: fill a form from a record, show a failed save's messages, put your own actions in the bulk bar, download a table as CSV, and show a field only while another has a value. The demo shows what it did not show, the skill names every option the code reads, and the examples use the new calls. Nothing that was there changes: every call below is an addition.

### Added
- **`Form::values($record)`** fills every field whose name is a key of the array (keys without a field are ignored, so a whole database row can be passed). A field's own `'value'` wins, and a second call adds to the first. It replaces `'value' => $row['x'] ?? ''` on each field: `examples/invoices/form.php` carried it eight times.
- **`Form::errors($messages)`** puts each message where the field's own `'error'` option would (under the field, `aria-invalid`, announced); a field's own non-empty `'error'` wins. A message whose key names no field (`'_form'`, say) is shown once in a message above the form. This is how a POST is shown again after a failed save; an AJAX form still gets its errors from the server's JSON.
- **`Table::bulkAction($name, $opts)`**: a button in the bulk bar beside delete. It fires `gk:bulkaction` on the table with `{ action, ids, tableId }`, the shape `gk:bulkdelete` has, and the application decides what it does. Options `label`, `icon`, `confirm` (`true` or the question to ask) and `color` (`'danger'` makes the question a danger one). It turns `selectable()` on when it is not on yet.
- **`Table::exportCsv($filename, $opts)`**: a toolbar button that downloads the table as CSV. A `setData()` table builds the file in the browser from the rows it holds, after the search and the filters and in the order they are sorted in; a column with `'export' => false` is left out. A `rows()` or `query()` table reloads the page with `?gk_export=csv`, which the page answers with **`Table::wantsCsv()`** and **`Table::sendCsv($rows, $columns, $filename)`**; **`Table::csv()`** returns the text. RFC 4180 with CRLF, UTF-8 with a byte-order mark, a delimiter of `','` or `';'` (German Excel), and a cell a spreadsheet would read as a formula (`=`, `+`, `-`, `@`, a tab or a carriage return first, and not a plain number) gets a leading apostrophe. The file name is made safe for the header it goes into. New strings: `table.export_csv`, `table.confirm_action` (en, de).
- **`'showIf'` on a form field**: `['field' => 'kind', 'equals' => 'company']`, with exactly one of `equals`, `not`, `in` (a list) or `filled` (`true`/`false`). A field that is not shown is hidden and disabled: not posted, `required` blocks nothing, no focus. A condition on a field that is itself hidden counts as empty, so a chain folds away together; what was disabled before stays disabled. Forms that arrive later (a modal) are evaluated through `GK.initContent`. An unusable condition raises `E_USER_WARNING` and the field stays visible.
- **`ci/demo-coverage.js`** opens the demo, visits every section, triggers what opens on a click and fails when a class of `css/blocks.json` is neither on the page nor listed, with a reason, in `ci/demo-coverage-allow.json`. 606 of the 780 classes are shown; the 174 that are not are state classes, opt-in markup and single modifiers, each group with its reason.

### Changed
- **The demo shows what it did not show** (405 of 780 classes in the DOM before, 606 now): `selectable()` with its bulk bar, `footer()`, `loadTime()`, `toolbarHtml()` and a link with a second line in a cell; `ActionGroup`, which was nowhere on the page; the Button options `xs`, `iconPosition`, `fullWidth`, `data` and `attrs`; an overview of all 139 utility classes generated from `css/blocks.json`; card variants; the page header; chip role names and icons; `Pagination`, `PageSize` and `gk-skeleton`; KPI colour roles and trends; and the four additions above.
- **`demo/index.php` is one file per section.** 3,726 lines became 627 plus thirteen files in `demo/sections/`. The page is unchanged: rendered before and after, in English and German, it is byte for byte the same apart from the random ids, the `?v=` file times and the clock in a date field.
- **`GRIDKIT_SKILL.md` names every option the code reads.** 24 keys were written down nowhere (`clearable`, `maxFiles`, `minSize`, `maxSize`, `maxTotalSize`, `accept`, `label_text`, `labelField`, `valueField`, `subtextField`, `minChars`, `searchParam`, `uploadHeaders`, `hideOnMobile`, `maxWidth`, `blankZero`, `dateFormat`, `hideIf`, `iconPosition`, `fullWidth`, `iconStyle`, `data`, `attrs`, `listId`, `invalid`); the skill has a column-options table now.
- **`examples/invoices`** fills its edit form with `values()` and offers `exportCsv()` on the list (German gets a semicolon).
- `SPEC.md` no longer lists export, bulk actions, drag and drop sorting and file upload as out of scope without saying since when.

### Tests
- `tests/form-values-errors` (57 assertions): the markup of a form filled by `values()` is byte for byte the one whose fields carry the same `'value'` options, for 17 field types; the same for `errors()`.
- `tests/form-show-if` (82): the attribute, the four tests, text values, escaping, every field type, eight kinds of unusable condition.
- `tests/table-bulk-csv` (52): the bulk bar markup, the export button, the file name, the CSV text (RFC 4180, formulas, numbers, delimiters, `export => false`).
- `tests/options-documented`: every option key `src/` reads is named in the skill. A new undocumented option fails there.
- `ci/browser-1105.js` (29 cases) in a real browser: the event of a bulk action with and without a question; the CSV file the browser builds compared byte for byte with what `Table::csv()` writes for the same rows (whole table, after a search, after a sort, a semicolon table); the reload of a server table; conditional fields (select, radio, switch, a chain, injected markup, what is posted, validity). Each rule of both implementations was mutated in turn and the cases turned red.
- Whole suite: 8,859 assertions (8,372 at 1.104.3). `ci/browser*.js` as before (138, 83, 116, 293); `ci/browser-sortierbar.js` S17 still fails in the cloud sandbox as it did before.

### Notes
- **`js/gridkit.js`: one block at the end, one line elsewhere.** The three scripted additions are a single block at the end of the file (`1.105.0 — additions that need script`), plus one line in `renderStatic` that keeps the rows the search left (`wrap._gkMatching`). A copy of the file without them, such as the PawBot dashboard's, renders the same pages: the bulk bar shows only its delete button, the CSV button does nothing, every conditional field is visible.

---
## [1.104.4] - 2026-11-02

One hand-typed link could hold a PHP worker: `?gk_page=99999999999999999999` on a table with `paginate()`. The page a table may be asked for is capped now, and a page far past the end shows the empty state as before.

### Fixed
- **`Table`: `gk_page` without an upper bound.** The number is `PHP_INT_MAX` after the cast. On a table with `rows()` or `query()` the pager counts from the page to the page + 2, and past `PHP_INT_MAX` the counter stops moving, so the request never ended (until the server's time limit). On a table with `setData()` the offset overflowed to a float and `array_slice()` threw a `TypeError`, a server error. The page is capped at 1,000,000 (`Table::MAX_PAGE`); nothing below that changes, and a page past the last one still shows the empty state.

### Tests
- `tests/hostile-params.test.php` (24 assertions) renders `Table` (static and server-driven), `FilterChips`, `PageSize`, `YearFilter` and `Pagination` once for every pair of ten query parameters and nine hostile values (array, nested array, a number beyond `PHP_INT_MAX`, `PHP_INT_MAX`, negative, zero, a float, a script tag, a null byte), each component in a child process with a time limit so a relapse fails the test instead of stopping the suite. Without the cap it fails with the hang and the `TypeError`; with it the other four components were clean already.

---
## [1.104.3] - 2026-11-02

`Form` and `Table` declare `strict_types=1` now, the last two classes of `src/` that did not. A date column that holds a timestamp and a multiselect that holds one number keep working, and nothing a page can see changes.

### Changed
- **`declare(strict_types=1)`** in `Form` and `Table`. Every class in `src/` has it now, and a test says so. Where the declaration would have turned a value that a database really hands over into a `TypeError`, the conversion PHP used to do silently is written out — for scalars only, so an array or an object still fails as it did:
  - `Table`, `format => date` and `datetime`: `strtotime()` takes a string and `date()` an int. A timestamp (an int) or a float reaches `strtotime()` as the string it used to become, and a text that is no date (`strtotime()` answers `false`) reaches `date()` as 0, as before.
  - `Form`, `multiselect`: `explode()` takes a string. A value that is one number, a selected id, is turned into its string first.
- This is the follow-up the notes of 1.104.2 left open. Rendering 9,699 combinations before and after (every format, option and field type with values of eleven kinds) showed 276 that behaved differently under the declaration; after the two changes above 48 are left, all of them an option the documentation calls a string and that somebody passed as a number or a bool (see Notes). One more combination differs only because its output is the current time (`'x'` is a time zone letter to `strtotime()`), and renders the same when both are asked in the same minute.

### Tests
- `tests/strict-types.test.php` (46 assertions): every class of `src/` declares `strict_types=1`; a `date` and a `datetime` column with an int, a float, a bool, a numeric string, a text that is no date, an empty value and null; a multiselect with `5`, `'5'`, `'5,6'` and `[5, 6]`; an action whose label is a number. Each of the casts was taken out in turn and the test turned red, as did taking the declaration out of `Table`.
- Whole suite: 8369 assertions (8323 before). The browser checks give the numbers they gave before, `ci/browser-sortierbar.js` S17 included. The whole demo page (613 KB) renders byte for byte as in 1.104.2, apart from the random ids and the `?v=` file times.

### Notes
- **Stricter on purpose:** an option that the documentation calls a string and that is passed as a number or a bool — a row button's `icon`, a column's `currency` or `dateFormat`, a field name given a second time through the options — is a `TypeError` now where PHP used to convert it. That is a programming error, found the first time the page is opened; none of it is a value a database holds.

---
## [1.104.2] - 2026-11-02

Housekeeping: the two longest methods of the form and the table are cut into parts, and the notes for coding sessions say what is true again. Nothing a page can see changes — no class, no option, no markup, byte for byte, and a recording of 150 renderings holds that to account.

### Changed
- **`Form::renderField()`** from 598 to 121 lines (most of it the comments of the shared preamble): one private method per field type — `renderTextarea`, `renderSelect`, `renderMultiselect`, `renderAjaxSelect`, `renderToggle`, `renderCheckbox`, `renderRadio`, `renderRange`, `renderFile`, `renderColor`, `renderRichtext`, `renderInput` — and the switch only dispatches. What the preamble works out is handed on as one array. The rich-text script, by far the longest part, is split three ways (the alignment buttons, the picture options, the initial value). No method of the class is longer than 66 lines now.
- **`Table::renderInner()`** from 295 to 39 lines: `renderTableHead()`, `renderTableBody()`, `renderTableFoot()` and `renderPager()`, with `renderHeadCell()`, `renderRow()` and `renderCell()` below them. The column key is passed as `int|string`, never as `string`: a numeric column key is an int in a PHP array and `$this->sortCol === $key` depends on that.
- **`declare(strict_types=1)`** in `Modal`, `ActionGroup` and `BelegModal`, the three small classes that lacked it. `ActionGroup` casts a scalar label before it reaches `Button::render(string …)`, so a label that is a number keeps working; an array still fails as before. Rendering `ActionGroup` with values of eleven kinds in each of twelve options, before and after, gave the same output or the same exception in all 145 cases.
- **`.claude/CLAUDE.md`** replaces `.CLAUDE.md`, a blueprint of version 1.1.0 that described `app/`, `core/` and `themes/`, none of which exist; its leading dot kept it off the website and also meant no coding session ever loaded it. The new file says what is true now (this directory is the website, how the tests and the browser checks run, the release order, the generated `skill/`, the three files the dashboard keeps its own versions of). It stays in a dot-directory, which `.htaccess` answers 403 for. `/.claude` is `export-ignore`, `.claude/settings.local.json` is git-ignored.

### Tests
- `tests/golden-form.test.php` (92 cases) and `tests/golden-table.test.php` (58 cases) render every field type and a table in all its variants (sorting, selection, row buttons, every cell format, grouping, `rowLink`, the pager at its edges, empty states, toolbar, footer, load time, the data block, the AJAX fragment) and compare the markup with `tests/golden/form.json` and `tests/golden/table.json`. Each case is rendered twice and must agree; the six hex digits that colour and rich-text ids append are normalised. A deliberate change of markup is recorded with `GK_UPDATE_GOLDEN=1 php tests/run.php golden-form` (or `golden-table`) and the diff of the JSON is read before it is committed. Recorded on the old code, then the methods were moved: the files did not change. Checked by changing one class name in five places of the form and eleven of the table — each turned the test red.
- Whole suite: 8323 assertions (7958 before). The browser checks give the numbers they gave before (138, 83, 116, 293); `ci/browser-sortierbar.js` S17 (390 px, sideways overflow of 50 px) fails in the cloud sandbox as it did before this release. The whole demo page (613 KB) renders byte for byte as before, apart from the random ids and the `?v=` file times.

### Notes
- **Why `Form` and `Table` still lack `strict_types`.** Rendering 9,699 combinations (every format, option and field type with values of every kind) with and without it shows what would change: a `date` or `datetime` column whose value is an int or a float (`strtotime()` gets a number) and a `multiselect` whose value is a single number (`explode()`) would throw a `TypeError` where they work today, and, unlikely in practice, a field name, an icon or a `currency` that is not a string. None of that is in the tests. A cast keeps today's behaviour; adding it is a separate decision.
- **Found on the way, not changed:** a `date` column with an int timestamp as its value shows 1970 — `strtotime("1700000000")` is `false`, and `date()` turns that into 0.

---
## [1.104.1] - 2026-11-02

The demo opens with an example instead of the changelog. The "What's new" box stood open above the first example on every visit — most of the first screen on a laptop — so the table the demo is about began below the fold. Demo only: no class, no token and no rule of the stylesheet changes.

### Changed
- **The "What's new" box folds once it has been seen** — it is open on a first visit and whenever the newest release is one this browser has not shown yet; on every later visit it starts folded and its summary line still names the three newest versions. The newest release is remembered in `localStorage` (`gk-demo-news-seen`). Without storage, in a private window for instance, the box stays open as before. On a phone it starts folded as before.
- **The cards of the box keep their own height** (`align-items: start` on `.demo-news-grid`) — the three cards were stretched to the tallest one, so the two shorter ones stood half empty. The "Examples" link now follows the last line of its own card instead of sitting at the bottom of the row.

### Tests
- `tests/blocks.test.php`: the demo looks up and remembers the newest release, folds a box whose release it has shown, guards the storage calls with `try`/`catch`, and the grid of the box does not stretch its cards.
- `tests/blocks.test.php`: the German jump ("Beispiele: Tabs") is looked for in the list of all releases instead of the newest three. It belonged to 1.103.0 and went red as soon as a new release pushed that card out of the box — the test depended on which releases are newest, which its own title says the box must not.

---
## [1.104.0] - 2026-10-31

Karteireiter on the phone can stand flat and in one row. Martin, 31.10.2026, with a photo from his iPhone: "Please adapt the panel to the smartphone. There is room for improvement." The Karteireiter of 1.101.0 turn into a grid on a phone — the calm second row of three tabs took two rows (94 px) and four main tabs a 2 × 2 grid of 118 px, before any content. Two classes, opt-in: without them every tab row stays as it was.

### Added
- **`gk-tab-nav-einzeilig`** (with `gk-tab-nav-karte`) — up to 600 px ONE row of equally wide cells, however many tabs there are, instead of two columns and the rows below them. Long words wrap at word boundaries inside the cell. No effect above 600 px.
- **`gk-tab-nav-flach`** (with `gk-tab-nav-karte`) — up to 600 px the icon stands BESIDE the word and the cell is as high as a tap target (44 px) instead of the icon above the word (about 56 px). Four tabs in the 2 × 2 grid need 94 instead of 118 px. No effect above 600 px. The icon keeps the width of its picture (`--gk-icon-md`, the rest cut off), so a missing icon font cannot push the word out of the cell.

- **`gk-ortszeile-schlicht`** (`Header::ortszeile(['schlicht' => true])`) — up to 768 px the steps of the "you are here" line are plain links without the pill outline; the 44 px target, the focus ring and the hover surface stay. One class weak (`:where`), no effect above 768 px.

### Tests
- `tests/ortszeile.test.php`: the option adds the class, the rule sits in `@media (max-width: 768px)` and stays one class weak.
- `tests/reiter.test.php`: both classes exist only inside `@media (max-width: 600px)`, are exactly as strong as the grid rules before them (three classes, later in the sheet) and set what the entry says.
- `css/blocks.json`: both classes under the block `tabs`, version 1.104.0.

---
## [1.103.1] - 2026-10-29

Calmer tab edges and a tidier "What's new" box in the demo. The edge of the Karteireiter, the track of the line tabs and the frame of the panel (`gk-tab-flaeche`) had been drawn a tone too hard — now they are one family with the edge of a card. No new class, no new token; what a tab does and how it is built stays as it was.

### Changed
- **Calmer tab edge** (`--gk-tab-kante`): from `color-mix(in oklab, var(--gk-outline) 70%, var(--gk-on-surface))` (30% near-black mixed in) to `color-mix(in oklab, var(--gk-outline-variant) 50%, var(--gk-outline))` — the middle between the hairline of cards, tables, inputs and dialogs and the edge of a control. Against the surface it measures 1.75:1 (light) and 1.55:1 (dark) instead of 4.67:1 and 3.55:1; for comparison the card hairline is 1.23:1 / 1.27:1 and the control edge 2.56:1 / 1.93:1. Both places that set it (the surface block and the block of the three flat rows) carry the same value; a test fails on any other mix, a hex or an `rgb()`.
- **Recognition without a hard edge** — the chosen tab is told by its word (≥ 4.5:1), its fill (≥ 3:1) and its stroke or fill (≥ 3:1). `ci/farben.js` now measures that as nine recognition marks, and its frame lines (seven, among them the track of both line looks) must lie between the card hairline and the control edge. The weakest recognition mark is 4.08:1 (light, "line on · stroke"), in dark at least 5.18:1.
- **Snapshot re-set on purpose** (`ci/reiter-bestand.json`), and only for this: 77 values, all of them `border-{top,right,bottom,left}-color` of the shapes `alt-karte`, `alt-karte-secondary` and `alt-links`. Every other rule of the old forms stays byte for byte; `ci/browser-reiter.js` (116 of 116) and `ci/browser-reiter-varianten.js` (293 of 293) hold.
- **Skill document** (`GRIDKIT_SKILL.md`): the contrast rule of the tab section describes the new edge.

### Added
- **"What's new" box in the demo, ordered by version** — one card per version: version, date, a one-sentence summary, the entries grouped as Added / Changed / Fixed with a count and the first lines of each, the new classes from `css/blocks.json` as small labels, and a jump to the demo section that shows them. The newest three are open, older ones are single rows in a native `<details>`, "All versions in the changelog" leads to the full text. Tests, notes and other headings stay out of the short view. Built from existing parts (`gk-card`, `gk-label`, `gk-disclosure`); the source stays `CHANGELOG.md` and `css/blocks.json`, nothing is written twice. On a phone the box starts folded as before. Checked at 390 px and 1440 px, light and dark, in three themes (weakest text 5.18:1), with the keyboard (summary and links are native).

### Tests
- `tests/reiter.test.php`: the new edge as expected value, plus a watcher (exactly two declarations, equal, no `on-surface`, hex or `rgb()`).
- `tests/blocks.test.php`: the old "news box" test replaced by seven — parser on a fixture changelog, section lookup, the box (three open cards, twelve rows in `<details>`, no inline style, no script), the cards of 1.103.0 and 1.102.0 with counts, classes and jump, the German wording, the demo's call and CSS rules, and a new entry that becomes the first card.
- Whole suite: 7912 assertions (the 13 fewer than before the version bump are the test "a class new in this version is named in its entry", which has no class to check in a release without a new class). Each change was also broken on purpose and the tests had to turn red (edge back to the old mix, a hex literal, an edge harder than the control edge, a too faint stroke, a changed gap in an old form; a fourth open card, code blocks counted, the whole paragraph instead of the first sentence, an inline style, the last instead of the first section, a changed German word, a missing CSS rule, labels without a limit). One hole was found and closed this way: an edge harder than the control edge got through until the track of the line tabs was measured as well.

---
## [1.103.0] - 2026-10-29

Tab variants: four looks, five places, a handful of options — every one a class on the one row
`nav.gk-tab-nav`, every one combinable. The Karteireiter (`gk-tab-nav-karte`) stays what it is; the
old unstyled row (no modifier) keeps its look byte for byte (a test compares the rules of every older
tab class with the 1.102.0 stylesheet), and nothing changes for a system that orders none of the new
classes. One script (`GK.tabsMarkup`) serves all of them.

### Added
- **Looks** (one per row): `gk-tab-nav-karte` (exists), `gk-tab-nav-linie` — underline tabs: a 1px
  track under the row, the chosen tab a 3px line in `--gk-tab-akzent` and bold `--gk-primary-text`;
  `gk-tab-nav-kapsel` — the chosen tab is a filled capsule (`--gk-primary-text` as fill,
  `--gk-surface` as text: white on `--gk-primary` is only 4.47:1 in the base palette), corners from
  `--gk-tab-radius`; `gk-tab-nav-text` — words only, the chosen one bold with a thin line. In line and text the corners on
  the side of the stroke are straight (the stroke does not curve at its ends), the other two keep the
  token radius.
- **Places**: `gk-tab-nav-links` and `gk-tab-nav-rechts` — a side row (vertical; the wrapper
  `.gk-tabs` becomes a two-column grid through `:has(> nav)`, only its direct child, so nested tabs stay
  untouched; the column is as wide as its words up to `--gk-tab-seitenbreite`, 14rem). Side rows
  exist from 601px; on a phone they fall back to the horizontal row (Karteireiter: the phone grid).
  `gk-tab-nav-unten` — the row under the panels (put it last in the markup; the Karteireiter hangs under
  its surface, mirrored, with the 3px line at the foot of the chosen tab). `gk-tab-nav-fest` — a bar stuck to the lower edge (`position: sticky`, icon
  above text, 56px high, `env(safe-area-inset-bottom)` as padding), meant for up to five tabs, combined
  with `gk-tab-nav-unten`.
- **Options**: `gk-tab-nav-sm` and `gk-tab-nav-lg` (size), `gk-tab-nav-fluid` (the row full width, tabs
  of equal share), `gk-tab-nav-icon-oben` (icon above the word), `gk-tab-nav-nur-icon` (icon only — the
  word stays for screen readers; give every tab `aria-label` and `title`), `gk-tab-nav-neutral` (the
  grey role instead of the primary). A count (`gk-tab-count`) and a badge (`gk-tab-badge`,
  `gk-tab-badge-error`) work in every look. A disabled tab: `disabled` on a button, `aria-disabled="true"`
  on a link — greyed, never focused by the arrows, a click does nothing.
- Tokens (set on the row or an ancestor): `--gk-tab-kante`, `--gk-tab-akzent`, `--gk-tab-akzent-text`,
  `--gk-tab-akzent-auf`, `--gk-tab-pad-y`, `--gk-tab-pad-x`, `--gk-tab-font`, `--gk-tab-linie`,
  `--gk-tab-sx`/`--gk-tab-sy` (on which side the line lies), and in `:root` `--gk-tab-seitenbreite`.
  Light and dark share the same classes; the line, the fill and every text are measured at ≥ 3:1 and
  ≥ 4.5:1; focus ring on every tab; every target ≥ 44px on a phone.
- Keyboard and roles: `GK.tabsMarkup` sets `aria-orientation` from the computed direction of the row
  (kept up to date when the window changes) and knows Arrow Up/Down on a vertical row; disabled tabs are
  skipped by the arrows and by Home/End.
- Demo, topic "Tabs": a builder at the top (look, place, size, width, icon, count or label — a live
  preview, the markup beside it and a copy button with a fallback), sections per look
  (`#tabs-stile`), place (`#tabs-ausrichtung`), option (`#tabs-optionen`) and "Combinations"
  (`#tabs-kombinationen`): outer Karteireiter with line tabs inside, side row with line tabs in the
  panel, fixed bottom bar with Karteireiter on top, capsules above Karteireiter.
- `GRIDKIT_SKILL.md`: the section "Tab variants" (table look × place × option, the tokens, what is not
  supported) — the installable skill is rebuilt.

### Changed
- `GK.tabsFit` leaves side rows alone (they never become the grid); a Karteireiter side row stretches
  its surface to the height of the tabs, so the surface is never shorter than the row.
- `tests/blocks.test.php`: the window of the blocks moved to 1.103/1.102.

### Notes
- Link tabs keep their place (`GK.tabsScroll`, 1.102.0) — keep a row of link tabs OUTSIDE a `.gk-tabs`
  wrapper: `GK.tabsMarkup` turns every link row inside a panel of such a wrapper into switch tabs.
- Not supported: `gk-tab-nav-secondary` with `-unten`; `position: fixed` for the bar (it is sticky).
- Looks without a box (line, capsule, text) scroll sideways when their words are wider than the box;
  only the Karteireiter turns into the grid.

### Tests
- `tests/reiter.test.php` (+ the whole suite: 7984 assertions): every new class is in the sheet and
  the older ones are byte-equal to 1.102.0; tokens instead of colour literals; which side the line lies;
  side rows only from 601px, 44px on a phone; the fixed bar (sticky, safe area, icon-only stays readable);
  disabled; `aria-orientation`, arrows, disabled skipped.
- `ci/browser-reiter-varianten.js`: 293 checks in Chromium — every look × every place × the options,
  light and dark, roles `tablist`/`tab`/`tabpanel`, arrows/Home/End/Up/Down, the phone fall-back, the
  fixed bar with the safe area, no overflow, contrast, and the four combinations (click, keyboard,
  `aria-selected`, only the own panel switches, link and button tabs mixed). Red against 1.102.0:
  147 of 293 checks fail there. The old forms (plain row, `-fill`, `-secondary`, Karteireiter, its second
  row, link tabs, and the oldest `gk-tabs-nav` / `gk-tab`) are compared by computed style — layout, borders
  and their colours, fill, text, shadow, cursor — in three states against `ci/reiter-bestand.json`, made
  from the 1.102.0 sheet: unchanged.
- `ci/browser-reiter.js`: 116 checks (unchanged). `ci/farben.js`: exit 0, every edge ≥ 3:1 and 229 texts (211 + 18 new) ≥ 4.5:1 per theme and mode; the new looks (line,
  capsule, text, also neutral: chosen, other, count) are in its 229 texts, all ≥ 4.5:1.
  `ci/browser.js` 138, `browser-picker.js` 83, `browser-timeline-video.js` 53;
  `browser-sortierbar.js`: 62 of 63 hold, the one known old case S17 (390px) remains.
- Counter-proofs: 27 breaks in two runs (the side of the line per place, safe area, sticky, the 601px
  threshold, the capsule fill and its text colour, the icon-only hiding, 44px on a phone, the 56px bar,
  the line colour and track, a changed old rule, the column direction of a side row, `aria-orientation`,
  its update on resize, Arrow Up/Down, stepping over disabled tabs, Home, the click guard on
  `aria-disabled`, `GK.tabsFit` leaving side rows alone, `--gk-tab-seitenbreite`, the stretched surface
  of a Karteireiter side row; the straight corners of line and text, three breaks). 23 turned a test red at once; 4 got through (the width cap of the side
  row, the stretched surface, the click guard, and a changed rule of the oldest `gk-tab-active` — the
  signature of the old forms did not read border colours). Each got its check, and all four turned red
  on the second run; `git diff` was empty after every run.

---
## [1.102.0] - 2026-10-29

Karteireiter, second round: a row that does not fit its box, an error badge, cells of equal height,
sharper corners, quiet line tabs for the second row, and link tabs that keep their place on the page.
Only classes new in 1.101.0 change their look (`gk-tab-nav-karte`, `gk-tab-flaeche`, `gk-tab-badge`,
`gk-tab-count`) — every older tab class is untouched; a row that fits stays the same single line, and
without the script nothing but the look of the new classes changes.

### Added
- `gk-tab-nav-raster` — the phone grid, at any window width: put on a `.gk-tab-nav-karte` row by
  `GK.tabsFit` when the row does not fit the box it stands in (six tabs beside a side column are too
  wide at a 1280px window and fit at 1440px — no fixed width suits every set of words), taken off
  again when it fits. Not set by hand. Columns: as many as fit, at least two (also in a box under 306px).
- `GK.tabsFit` (`.fit(nav)`, `.init(root)`) — measures every `.gk-tab-nav-karte` as a row (class off,
  its own overflow decides, class on) at start, when the row or one of its tabs changes size, and when
  a hidden row is shown. Called from `GK.initContent()`, so content loaded later is covered too.
- `gk-tab-badge-error` — the error variant of `gk-tab-badge`: `--gk-error-container` on
  `--gk-on-error-container` (the roles of `gk-label-red`), the word "Error" stays — colour alone never
  says it. Replaces a local `.gk-tab-badge.<system>-error` rule.
- Demo, topic "Tabs": an error badge on a tab and the example "A row that does not fit its box" (six
  tabs in boxes of 700, 560 and 340px).

- `--gk-tab-radius` (4px, in `:root`) — the one corner token of the Karteireiter: tab corners, corners
  of `gk-tab-flaeche` and the phone cells read it (before: `--gk-radius-sm` 8px and `--gk-radius` 12px,
  which made the tabs look bubbly). Buttons, cards and inputs keep their radii. An ancestor can set it.
- `GK.tabsScroll` — link tabs keep their place: a click on `a.gk-tab-btn` in a `.gk-tab-nav` notes the
  target, the row (its `aria-label`, else its order) and where it stands in the window
  (`sessionStorage`, every access guarded); the page it lands on scrolls so the row stands there
  again. Left alone: a `#` target, another origin, `target`/`download`, Ctrl/Shift/Meta/Alt or the
  middle button, an address with a `#` anchor, a note older than 15 s, back/forward/reload, a row that
  is missing. `data-gk-tab-scroll="off"` on the row or an ancestor switches it off.

### Changed
- `gk-tab-nav-karte gk-tab-nav-secondary` (the second row, also tabs inside a window) is now quiet line
  tabs instead of boxes: no fill or frame at rest, a 1px line under the row, the chosen tab a 3px
  accent line (`--gk-primary`) and bold `--gk-primary-text`, hover only a faint tint
  (`--gk-state-hover`), focus ring inside the tab. On a phone the cells keep the line (1px, chosen 3px).
  Markup is unchanged; contrast is measured as before (line ≥ 3:1, text ≥ 4.5:1, light and dark).
- Karteireiter corners: 4px on top of the tab, the surface `0 4 4 4` (before: 8px/12px), a clean
  corner where tab and surface meet.

### Fixed
- Phone grid (up to 600px): cells with a badge or count were taller than their neighbours, because the
  row aligned its cells at the bottom (`align-items: flex-end`). In the grid they now stretch and the
  rows are equal in height (`grid-auto-rows: 1fr`); the single-line row is untouched.

### Tests
- `tests/reiter.test.php` (+ the whole suite: 7828 assertions): the grid and its equal-height cells, the
  error badge pair, `GK.tabsFit`, the one corner token (every Karteireiter rule reads it), the line tabs
  of the second row, `GK.tabsScroll` (guarded storage, every exception, the opt-out, started once).
- `ci/browser-reiter.js`: 116 checks in Chromium, among them the window width 1138/900/700/560/340px
  (light and dark), corners, line tabs, focus ring, hover tint, the phone grid, and — new — the place of
  a link tab across two pages with a different head height (±2px), no memory for a `#` target, another
  origin, Ctrl/Shift/middle click, `data-gk-tab-scroll="off"`, a blocked `sessionStorage`, an old memory.
- `ci/farben.js`: all tab edges ≥ 3:1 and every text ≥ 4.5:1 in the six themes, light and dark (the
  error badge included); `tests/blocks.test.php` moved its window to 1.102/1.101.
- Counter-proofs: every guard of the above was broken on purpose (35 breaks) and the test turned red.

---
## [1.101.0] - 2026-10-29

Tabs that do not get lost: the "Karteireiter" shape, ordered with a class. Every existing tab class
and its look stay exactly as they were for every system that does not order the new shape.

### Added
- `gk-tab-nav-karte` — framed tabs on a surface: the chosen tab grows into the surface (taller, bold,
  3px accent on top), the others lie grey behind it. Works on `.gk-tabs` (buttons) and on link rows
  (`aria-current="page"`).
- `gk-tab-nav-karte gk-tab-nav-secondary` — the quiet second row (on a line, only the chosen tab
  framed); also the shape for tabs inside a window (the window is the surface).
- `gk-tab-flaeche` — the surface under a Karteireiter row (panel or wrapper after a link row).
- `gk-tab-badge` (a deviation such as "Customised") and `gk-tab-count` (a number); the count on the
  chosen tab uses `--gk-primary-container` (on-primary on primary measured 4.47:1).
- Tokens `--gk-tab-kante` (edge, outline mixed with 30% text colour, ≥ 3:1) and `--gk-tab-flaeche`,
  set on the Karteireiter classes only, not on `:root`.
- Phone grid: up to 600px a Karteireiter row is a grid (`auto-fit`, 150px), up to 480px two columns;
  every tab visible, framed 44px cells, nothing scrolls or is cut, no script.
- `--gk-steps-vorlauf` — steps up to 480px draw a 12px line before each step instead of a 24px line
  after it; three steps fit in 340px and a wrapped band leaves no line hanging at a row end.
- Demo: own topic "Tabs" (page tabs, sub tabs, nested, in a window, segment, light and dark); the old
  examples under Navigation are named "previous shape" and link to it.
- `ci/browser-reiter.js` (nesting, keys, backward look at 1440, phone grid at 390, steps at 340 and
  190px); `ci/farben.js` measures the Karteireiter texts and seven edges (3:1) in all themes.

### Fixed
- Nested `.gk-tabs`: a click on an outer tab reset the inner row (the handler took every button and
  panel below the wrapper). `GK.tabsMarkup.own()` / `panel()` now take only a wrapper's own row and
  panels — init, click and arrow keys.

### Removed
- The proposal files of the branch `reiter-vorschlag` (`css/vorschlag-reiter.css`,
  `js/vorschlag-reiter.js`, `demo/reiter.php`, `demo/reiter-demo.css`) — never released.

---
## [1.100.0] - 2026-10-28

This is the work once numbered 1.98.0 on the branch `ortszeile`. It was never released, and 1.99.0
(the tab and step blocks) went out first, so it comes now as 1.100.0, on top of 1.99.0.

### Added — the "you are here" line: `Header::ortszeile()`
The SSI Panel's navigation was reworked (design B, chosen 28.10.): a person in a nested page must see
at once where they are, and be one tap from the dashboard and from the module. A line like that is
wanted by every system built on GridKit, so it lives here and the Panel only uses it.

```php
echo (new Header())
    ->title('Invoice INV-2026-001')
    ->ortszeile([
        'dashboard' => ['label' => 'Dashboard', 'href' => '/', 'icon' => 'home'],   // the defaults
        'modul'     => ['label' => 'Billing', 'href' => '/faktura', 'icon' => 'receipt_long'],
        'stufen'    => ['Invoices' => '/faktura/rechnungen'],                       // optional steps
        'aktuell'   => 'Invoice INV-2026-001',                                      // the page, no link
    ])
    ->sticky()->render();
```

- **`Header::ortszeile(array $o)`** (new). `<nav class="gk-ortszeile" aria-label="Du bist hier">` around an
  ordered list: the dashboard and the module as links (word + symbol), the steps in between, and the
  page last — **not a link**, `aria-current="page"`, its full title in `title`. On the dashboard itself
  leave `modul` out: "Dashboard" is then the current page and no link. A step with an empty URL is plain
  text. `label` renames the landmark; the default comes from the language file (`ortszeile.aria`).
- **Classes:** `gk-ortszeile`, `gk-ortszeile-link`, `gk-ortszeile-current`, `gk-ortszeile-text` (own block
  "Ortszeile" in `css/blocks.json`). Only the title of the current page is shortened, at its end, with an
  ellipsis; the anchors and the steps never are. Targets are 32px high at a desk and
  `var(--gk-target-min)` (44px) on a phone; nothing is hidden on a phone, the line wraps. Colours are
  tokens (`--gk-primary-text`, `--gk-on-surface`, `--gk-outline`), so light and dark follow by themselves.
  The separators are drawn by CSS and are not read out.
- **On a phone the line takes a row of its own** under the menu button and the user menu, the whole
  width (`.gk-header:has(.gk-ortszeile)` wraps; the two wrappers `.gk-header-left` and
  `.gk-header-title` step aside with `display: contents`; the line has `order: 1`). A page after two or
  more steps takes a row of its own again, so its title is read whole. Measured at 390px: the header is
  105px (dashboard), 137px (module), 181px (deep page, long title) high; at 1440px it is one row, 61px.
  The first try let the steps stack in a column inside the old one-row header (208 – 252px); that is why
  the line has its own row now.
- **One row: `'einzeilig' => true`** (class `gk-ortszeile-einzeilig`). For a header of fixed height (the
  SSI Panel's is 98px on a phone), which a wrapping line would push open. The line never wraps; the two
  anchors keep their size, the steps in between and then the page shorten at their end with an
  ellipsis, and every link that can be shortened carries its full text in `title` (the steps, and the
  dashboard, see below; the module anchor never shortens, so it gets no tooltip that repeats it). On a phone the page stays in the row and
  the pills are narrower (10px padding instead of 12px) — the 44px target height stays. With more after
  it, the dashboard anchor is its house alone on a phone (a 44px square); the word stays for a screen
  reader (hidden like `.gk-sr-only`) and in `title`. Measured at 390px in the Panel: without that,
  "Dashboard › Faktura › Rechnungen › Rechnung …" left "Re…" for the last two.
- **`GK.navigate` takes the new page's line into the header.** The line stands in the header, outside
  the swapped `[data-gk-content]`; after a page change without a full load it kept naming the page one
  came from. `_render` now replaces `header .gk-ortszeile` with the one of the loaded page (when both
  exist). Before, the Panel copied it over itself from a `<template>`.
- **Put it in a `->sticky()` header**, in the content column of a layout with the sidebar first (or on a
  page without a sidebar). The fixed header has a fixed height and the page below it a fixed offset
  (56px): a line that wraps would cover the first rows. In the header-first layout a sticky header in
  the content column leaves the fixed sidebar starting at `top: 56px`, under a gap — **not solved here**.
- **One trail, not two.** When `ortszeile()` and `breadcrumb()` are both given, the line wins.
- **`Header::breadcrumb()`** keeps its markup and gains `aria-label` (`breadcrumb.aria`: "Brotkrume" /
  "Breadcrumb") on the `<nav>` and `aria-current="page"` on the last step. The home symbol loses its
  inline style and becomes `gk-breadcrumb-home` (new class). These are the only differences from 1.97.0
  — a test removes exactly those attributes and compares the rest byte for byte.
### Added — a linked sidebar brand, a close button without a brand, a menu button with its word, a skip link
Small parts the new navigation needs, each an option; nothing changes for a page that does not ask.

- **`Sidebar::brand($title, $icon, $version, $href = '')`** — with a fourth argument the symbol and the
  name are one link (`<a class="gk-sidebar-brand-link">`); the Panel no longer needs to patch the text in
  afterwards. Without it the output is byte-for-byte the old one. New class `gk-sidebar-brand-link`.
- **`Sidebar::mobileClose(bool $enabled = true)`** — a sidebar with **no brand** keeps its close button on a
  phone: a bar of its own (`gk-sidebar-brand gk-sidebar-brand-bare`) with the button and the word
  "Schließen" / "Close" (`gk-sidebar-close-label`, language key `sidebar.close_short`). Not drawn at a
  desk. With a brand there is one close button, as before. New classes `gk-sidebar-brand-bare`,
  `gk-sidebar-close-label`.
- **`Sidebar::elementId(string $id)`** — writes `id` on the `<aside>`, so that a button can point at it
  with `aria-controls`. Without the call there is no `id`, as before.
- **`Header::sidebarToggle(bool $enabled = true, array $opts = [])`** — `label` (`true` = "Menü" / "Menu",
  or a word of your own; default `false` = the symbol alone, as before) and `controls` (the sidebar's id
  → `aria-controls`). With a word the button has **no** `aria-label`: the visible word is its name, so
  what a voice-control user says is what they see (WCAG 2.5.3); "Navigation öffnen" stays as the
  tooltip. New classes `gk-header-menu-toggle-word`, `gk-header-menu-label`. `aria-expanded` was
  already kept up to date by `GK.sidebar` and is untouched.
- **`Layout::skipLink(string $target = '#main', string $label = '')`** — "Zum Inhalt" / "Skip to
  content" (`layout.skip`): `<a class="gk-skip-link">`, out of sight above the page until the keyboard
  reaches it. Print it first in `<body>`; the target is `<main id="main" tabindex="-1">`. Opt-in: no
  existing page changes. New class `gk-skip-link`.
- New language keys in `lang/de.php` and `lang/en.php`: `ortszeile.aria`, `ortszeile.dashboard`,
  `breadcrumb.aria`, `header.menu`, `layout.skip`, `sidebar.close_short`.


### Fixed — found while measuring the line in a browser
- **Sidebar-first layout on a phone: the open menu lay under its own overlay.** `.gk-sidebar` had
  `z-index: 300` there and `.gk-sidebar-overlay` 999, so the menu was greyed and a tap on a link shut
  the menu instead of following the link. It is `1000` on a phone now, as in the header-first layout.
  This is a bug of 1.97.0 and earlier, not of the new line; it is shipped here because it was found
  here. Only the attribute form `data-gk-layout="sidebar-first"` is changed (the only one the phone
  block knows); the class form `gk-layout-sidebar-first` has no phone rules at all and is left alone.
- **A sticky header in `.gk-with-sidebar` stood under a blank 56px band.** The padding is for the
  fixed header; `.gk-with-sidebar:has(> .gk-header-sticky)` now has `padding-top: 0`, before the
  layout rules so that they do not win. A page that has a fixed header is not touched. **This is the one
  visible difference for an existing page**, and only for one that put a sticky header straight into the
  content wrapper.

### Changed — the shut menu leaves the tab order, and the order in the source
- **The shut menu leaves the tab order.** On a phone the sidebar was only moved off the screen: all its
  links could still be reached by Tab and were read out. `visibility: hidden` now takes it out of both
  when it is shut (after the slide, `visibility 0s linear .25s`) and `.open` makes it visible at once.
  A rule in the stylesheet instead of `inert` set by script, so `js/gridkit.js` — which the PawBot
  dashboard has extended — is not touched.
- **Order in the source.** The sidebar is `position: fixed`, and its only sibling coupling in the
  stylesheet is the sidebar-first layout (`.gk-sidebar.collapsed + .gk-header-fixed`). In the header-first
  layout the header may therefore stand in the source before the `<aside>` — brand, anchors and the
  header's links first, then the menu — with no change to the look. That is a convention for the page
  (and is now documented and tested), not a change of the code: an existing page that has the sidebar first
  keeps it, and the sidebar-first layout needs it there.

**Compatibility.** Every call that is in use today gives the same output as before, except the two
attributes and the home class of the breadcrumb named above (`tests/ortszeile.test.php` holds the
hashes of the old output for `Header`, `Sidebar` with a brand, without, and collapsed). A stylesheet
selector `.gk-breadcrumb a` still matches the home link.

**Measured** (Chromium, 390 and 1440px, light and dark): no horizontal overflow; links 44px high on a
phone and 32px at a desk; every step visible; contrast of a link 6.05:1 (light) and 5.71:1 (dark), of
the current page 16.3:1 and 11.9:1; with the menu shut, Tab goes skip link → menu button → the line's
links → user menu → content and reaches nothing of the sidebar; with the menu open and the header
before the `<aside>` in the source it goes on into the sidebar (brand, close button, items). With the
`<aside>` first it does not, until the tab order has wrapped round. Header, sidebar and content stand at
the same places whichever comes first in the source (fixed header, header-first layout).

**Not checked.** Focus moving into the menu when it opens and back to the button when it shuts, and
closing with Esc, are not built (they need script in `gridkit.js`). A **fixed** header with a line that
wraps on a phone covers the first rows of the page (the offset is fixed) — hence "sticky". Header-first
layout with a sticky header in the content column: see above. Nothing was tried in a real screen reader,
nor inside the Panel. The Panel's own `r-teilmenge.sh` was not run.

**For the PawBot dashboard:** `src/Sidebar.php` there is its own extension and must not be overwritten.
Carry over by hand: the `$href` parameter of `brand()` with the `gk-sidebar-brand-link` wrapper,
`mobileClose()` with the bare bar, `elementId()` and the `id` attribute. `Auth.php` and `js/gridkit.js`
are untouched by this release.

**Rollback:** the branch `ortszeile` is the whole change; set `VERSION` back to 1.97.0 and drop the
branch. No data, no schema.
## [1.99.0] - 2026-10-28

### Added — tabs as links, a segmented button, steps, a disclosure, a to-do in a timeline
The head of the SSI Panel's landing pages was built out of local parts: register tabs with their own
backgrounds, a pill group for a second row, single chips for a period, a card with a 6px edge for the
status and numbered cards for what is still open (Martin, 28.10.2026: "ugly tabs, a strange card with a
thick edge, terrible pill group and chips, no inline home constructions"). Each of them is something
another SSI system needs, so GridKit learns them. Roles only, no dark rule; every rule that sets
`display` is wrapped in `:where()`, so a consumer's hiding class still wins.

- **Tabs as links:** `a.gk-tab-btn[aria-current="page"]` looks like `.gk-active` (a `<nav>` of pages,
  no tablist, no script); `.gk-tab-icon` (a symbol before the word); `.gk-tab-nav-fill` (44px; up to
  600px the tabs share the width, the symbol over the word, each as wide as its word);
  `.gk-tab-nav-secondary` (a quieter second row: no rule of its own, 13px, the text colour); a visible
  focus ring on every tab.
- **Segmented button:** `.gk-segmented` / `.gk-segmented-item` — one choice of 2–5, joined; the chosen
  one (`aria-current` other than "false", `aria-pressed="true"` or `.gk-active`) is tinted AND ticked;
  44px on a touch screen; a row too wide scrolls. `FilterChips::segmented($label)` draws the same links
  in it (labelled `role="group"`; an icon is drawn, a count is not).
- **Steps:** `.gk-steps`, `.gk-steps-item`, `.gk-steps-done` (a tick on a disc), `.gk-steps-current` (a dot in a ring, bold,
  `aria-current="step"`), open (an empty ring).
- **Timeline:** `gk-timeline-todo` (open, next: a ring and a word in the primary colour),
  `.gk-timeline-actions` (a button beside the sentence of its step, under it up to 480px),
  `.gk-timeline-compact` (20px markers, a regular title — a short list inside a message).
- **Disclosure:** `.gk-disclosure` — a quiet native `<details>`: link-coloured summary, an arrow that
  turns, 44px, a focus ring.

### Changed
- **44px:** a tab of `.gk-tab-nav-secondary` is 44px tall; a `.gk-segmented-item` also on a phone-wide screen
  (`max-width: 600px`), not only with `pointer: coarse` — the Panel's browser tests measured 36px at 390px.
- **Focus ring in a toned message:** a `.gk-btn` in `.gk-message-info|success|warning|error` shows its focus
  ring in the text colour of the message (`--gk-on-*-container`), not in the violet of the primary colour.
- **Actions of a message on a phone (≤480px):** `.gk-message-actions` stand under the text, one under the other,
  as wide as the message and flush with it.
- **A symbol in a button inside a message** keeps the size of its button (18px, xs 14, sm 16, lg 22 —
  the sizes `GridKit\Button` writes); `.gk-message .material-icons` gave it the 24px of the message symbol.
- **An outlined button of a to-do step inside a message** stands on `--gk-surface` like one in
  `.gk-message-actions`: a warning-coloured one on the warning tint measured 4.35:1 (`ci/farben.js` now
  checks it in the warning message).
- The active tab (`.gk-tab-btn.gk-active`) is in `--gk-primary-text` and weight 600: `--gk-primary`
  measured 4.47:1 on white, and colour alone told it apart.
- A message with actions keeps its symbol at the top (it slid to the middle of a tall one), and a
  symbol inside a `.gk-btn` keeps its button's colour (`.gk-message-warning .material-icons` painted
  it orange).

Tests: `tests/reiter.test.php` (suite 7254 -> 7409 assertions); `ci/farben.js` measures 34 new texts
(201 in all) in six themes and without one, light / dark / dark class — weakest new one: an open step in a
warning band, dark, 4.63:1. Counter-checks: 16 mutations of the stylesheet, one of FilterChips and one of
a colour, each turns its check red. The demo shows them under "Tabs" and "FilterChips".

---
## [1.97.0] - 2026-10-27

### Added — a sortable container: `data-gk-sortierbar`, in its own file `js/sortierbar.js`
Cards or rows a person puts in order by hand, and the server remembers it. The SSI Panel's overview of
websites needs it first (the site that matters most goes to the front); every admin with a list that
has an order of its own needs the same. GridKit had sorting by column in the table and nothing that
moves an entry; two pages of the Panel's invoicing had built their own dragging, with inline styles.
The script is a file of its own, so `js/gridkit.js` is untouched and the dashboard's copy of it is
not in the way. A page loads it after `gridkit.js` (`skeleton.php` and the demo do):
`<script src=".../js/sortierbar.js" defer></script>`. There is no global and no call to make: it finds
every `[data-gk-sortierbar]`, also one that is written into the page later.

```html
<div data-gk-sortierbar data-gk-sortierbar-url="/websites/reihenfolge" data-gk-sortierbar-csrf="TOKEN">
  <article data-gk-sort-id="12"><h3>survivaltraining.at</h3> ...</article>
  <article data-gk-sort-id="7" data-gk-sort-titel="Manifestor">...</article>
</div>
```

- **The handle is made by the script**: a `<button type="button" class="gk-sort-griff">` with the symbol
  `drag_indicator` and the name "Move: <title>" (from `data-gk-sort-titel`, else the first heading), in the top
  right corner of each entry — only while the container (`gk-sort-aktiv`) has two entries or more. Only the
  handle drags: links and buttons in the entry keep working, and the rest of the entry still scrolls the
  page on a phone (`touch-action: none` is on the handle alone). `--gk-sort-griff-oben` and
  `--gk-sort-griff-rand` move it.
- **Mouse, pen and finger** through pointer events: the entry flies (`gk-sort-zieht`, `gk-sort-frei`) over a
  dashed gap (`gk-sort-platz`); near the window's edge the page scrolls. **Keyboard**: Space lifts, the arrow
  keys move (left/right and up/down in a grid, Home and End, reversed in `rtl`), Space puts down, Escape gives
  back; the focus stays on the handle; a polite live region says "Position 2 of 5". Under
  `prefers-reduced-motion` nothing glides.
- **Saving** only with `data-gk-sortierbar-url`: `POST` as JSON `{"ids":["12","7",...]}` (all ids, as text, in the new
  order) with `credentials: same-origin` and the token in `X-CSRF-Token` (or the header named in
  `data-gk-sortierbar-csrf-header`). A second drop cancels the request still on its way. "Order saved" shows
  for a moment (`gk-sort-ok`, `gk-sort-meldungen`); on a refusal, a bad status or no network the entry goes back
  to the last saved place and a sentence beside a symbol says so (`gk-sort-fehler`, `role="alert"`).
- **Event** `gk:sortiert` (bubbles; `detail.ids`, `detail.behaelter`, and `detail.zurueck` when the order went
  back) after every change, with or without an address.
- Words in `lang/` as `js.sortierbar_*` (English and German), delivered by `Lang::jsConfig()`.
- New classes: `gk-sort-griff`, `gk-sort-aktiv`, `gk-sort-zieht`, `gk-sort-frei`, `gk-sort-platz`,
  `gk-sort-meldungen`, `gk-sort-ok`, `gk-sort-fehler`. Block "Sortable container" in `css/blocks.json`, a
  section in the demo, the skill and the spec, a test (`tests/sortierbar.test.php`) and a browser test
  (`ci/browser-sortierbar.js`, fixture `--sortierbar`).

### Wish, not done here
The two hand-made drag lists in the Panel (`views/faktura/invoice-edit.php`, `views/faktura/recurring.php`)
can move to this block, and lose their inline styles with it.

---
## [1.96.0] - 2026-10-17

### Added — a timeline: `.gk-timeline`
A log of steps, one under the other: what ran, in which order, how each step ended. The SSI Panel's
"Shorts" module makes a short video in several steps and has to show the person who approves it where
the time and the money went and where a step broke; any admin that runs a pipeline needs the same list.
Markup and classes only — the page that has the steps writes them, so there is no PHP class and no script.

```html
<ol class="gk-timeline" role="list" aria-label="Steps of the run">
  <li class="gk-timeline-step gk-timeline-failed">
    <span class="gk-timeline-marker" aria-hidden="true"></span>
    <div class="gk-timeline-body">
      <div class="gk-timeline-head">
        <span class="gk-timeline-title">Make the pictures</span>
        <span class="gk-timeline-state">Failed</span>
        <span class="gk-timeline-time"><time datetime="2026-10-17T09:12:08">09:12:08</time> · 31 s</span>
      </div>
      <div class="gk-timeline-chips"><span class="gk-label gk-label-gray">Image service</span></div>
      <p class="gk-timeline-error">The image service did not answer after 3 tries.</p>
    </div>
  </li>
</ol>
```

- **A real ordered list**, named and with `role="list"`: the numbers and bullets are off (the markers
  and the line draw the sequence), and a screen reader that sees `list-style: none` drops the list
  unless the role says it is one.
- **Five states, each a shape AND a word — never colour alone.** `gk-timeline-ok` (a check),
  `gk-timeline-failed` (a cross), `gk-timeline-running` (a ring with a gap, turning), `gk-timeline-waiting`
  (an empty dashed ring with a dot), `gk-timeline-skipped` (a dash) on the `<li>`; the word stands
  beside the title in `.gk-timeline-state`; the marker is empty and `aria-hidden`, CSS draws it. The
  symbols are lines and borders in the colour of the text, not fills, so `forced-colors` keeps them.
  `aria-current="step"` goes on the running step.
- **Parts:** `.gk-timeline-head` (title, state word and time on a row; on a phone the time goes under
  them), `.gk-timeline-time`, `.gk-timeline-chips` (the `.gk-label` GridKit has — cost, provider — allowed
  to wrap), `.gk-timeline-detail`, and `.gk-timeline-error`: the message of a failed step as a tinted block
  with a 4px edge in the error container pair. A title, detail, message or chip with an address in it breaks
  inside the column from 320px up.
- **Roles only, no dark rule**, so light, `[data-gk-mode="dark"]` and `.gk-dark` follow from the tokens. The
  word of a tone uses the text-safe variants (`--gk-success-text`, `--gk-danger-text`, `--gk-primary-text`),
  the marker the container pair of its tone.
- **The one thing that moves** is the ring of the running step (`gk-spin`, reused); it stands still under
  `prefers-reduced-motion`.

Text measured in a browser in all six themes and without one, light and both dark spellings, on a card
and on the page, weakest value (light / dark / dark class): title, word, time and detail 5.36 / 5.62 / 5.62,
the symbol on its disc 6.50 / 4.95 / 4.95 (the skipped dash and the gray chip), the error message
7.97 / 7.97 / 7.97 (WCAG AA asks 4.5:1).

### Added — a portrait video frame: `.gk-video-frame`
A frame for a 9:16 video or picture — a short, a story, a phone screenshot. A bare `<video>` of a vertical
file is as wide as its column and three screens tall.

```html
<figure class="gk-video-frame gk-video-frame-safe">
  <div class="gk-video-frame-media">
    <video controls playsinline preload="metadata" aria-label="Version 3 of the short" src="/v/3.mp4"></video>
  </div>
  <figcaption class="gk-video-frame-caption">Version 3 · 24 s</figcaption>
</figure>
```

- **The shape and its limits.** `.gk-video-frame-media` is `aspect-ratio: 9 / 16`, rounded and cut at the
  corners; the `<video>` or `<img>` lies IN it (absolute — a portrait file in the flow gives an aspect-ratio
  box a height of its own) and is shown whole (`object-fit: contain`, on black). The height is limited by
  `--gk-video-frame-max-h` — 75% of the window, `75dvh` with `75vh` behind it — and the frame takes its WIDTH
  from that limit, `min(100%, limit × 9/16)`: with `width: 100%` and a `max-height` alone, a limit that bites
  leaves a wide, flat box. Never wider than its column, 9:16 at every width and window height.
- **No autoplay.** The documented markup has `controls` and `playsinline` and a name; the stylesheet has
  nothing to do with playing.
- **Safe zones: `gk-video-frame-safe`.** Guide lines over the picture for the zones a platform covers with
  its own buttons — the top 10% and the bottom 20% — as hatching with a dashed edge. They take no room and
  never catch a click (the video's controls lie in the bottom zone); the two numbers are
  `--gk-video-safe-top` and `--gk-video-safe-bottom`, because YouTube Shorts and Instagram Reels differ.
- **Nothing to show yet, and it broke: `gk-video-frame-busy`, `gk-video-frame-error`.** `.gk-empty` is
  reused, filling the box (its 56px of padding would not fit a frame 150px wide); the busy symbol pulses
  (`gk-dot-pulse`, reused) and stands still under reduced motion; the failed state is in the error colour. A
  box with nothing in it has a dashed edge, so it shows on a page of the same grey. A message too long for a
  small frame scrolls inside the box.
- Colours are roles, apart from the black behind a video and the guide lines. Weakest text value of the empty
  states and the caption, six themes, both modes (light / dark / dark class): 5.52 / 5.62 / 5.62.

### Tests
- `tests/timeline.test.php` (new) and `tests/video-frame.test.php` (new): the rules of each block as written
  above, read from the stylesheet — five different symbols, the word beside the title, roles only and no dark
  rule, the ring and the pulse the only motion and both stopped under reduced motion, long words, the width
  formula, the safe zones on request and click-through, `.gk-empty` reused, its place before the last block
  — the text of every state computed from the tokens, light and dark, and the documented markup (list role,
  hidden marker, a word in every step; a figure, `controls`, `playsinline`, no `autoplay`).
  `tests/css-helpers.php` holds the small readers both use. 279 and 148 assertions; the suite 7022
  (6489 before).
- `ci/browser-timeline-video.js` (new, 53 cases, with the fixture mode `--timeline-video`): the blocks in a browser —
  the accessibility tree, five distinct symbols centred in their discs, the line, the animation and its
  stopping, the error block, both dark spellings, forced colours, the keyboard, 360 and 320px; the frame's
  9:16 at every column width and window height, the picture in the box, the zones' height, a click on the
  play button through the bottom zone, the empty states, a phone.
- `ci/farben.js` (+62 texts, 167 in all): every text of both blocks (title, word, time, detail, chip, symbol,
  message; title, sentence, symbol, caption) against the ground it stands on, on a card and on the page, in every
  theme and mode.
- `tests/blocks.test.php`: the marks window moved to 1.96 / 1.95 — the timeline and the frame are the blocks
  new in 1.96, the message the one changed in 1.95, the picker (1.94) has fallen out of the window.
  `css/blocks.json`: the blocks "Timeline" and "Portrait video frame"; the demo shows both (a sample video of
  9 KB and a picture in `demo/media/`); SPEC.md, the README and the skill describe them.
- Counter-checks (`scripts/gegenprobe.php` of the Panel): 74 mutations of `css/gridkit.css`, each turns the named
  check red — 30 in the suite, 33 in `ci/browser-timeline-video.js`, 11 in `ci/farben.js`; the order mutation
  (both blocks moved behind the last block) was done by hand. Three mutations first stayed green and changed
  things: a disc that ignored the mode (the check now compares the disc, not only its symbol), a cross whose
  second stroke was `display: none` (the stroke has to be drawn), and a `min-width: 0` on the body that proved
  dead (the grid track `minmax(0, 1fr)` does that work; the declaration, and two like it on the title and the
  frame, are gone). Several runs of the browser script failed on a loaded server with a timeout in
  `scrollIntoViewIfNeeded`; the script now waits for animation frames and for the loaded video instead of fixed
  pauses, and its default timeout is 20 s.

---
## [1.95.0] - 2026-10-17

### Added — a small message: `.gk-message-sm`
The SSI Panel put a privacy sentence above its chat as a `.gk-field-hint`: 12px grey text that read
like body copy. A `.gk-message` has the look such a sentence needs — tinted ground, border, a symbol in
the signal colour, `.gk-message-header` as the title — but it is too heavy for that place: padding
14px 20px and a 16px margin underneath. `.gk-message-sm` keeps the look and shrinks the box:

```html
<div class="gk-message gk-message-info gk-message-sm">
  <span class="material-icons" aria-hidden="true">lock</span>
  <div class="gk-message-content">The conversation is stored on our server and never passed on.</div>
</div>
```

- **The box:** padding `10px 14px`, gap `10px`, text `--gk-text-label` (13px) over a line height of
  1.45, the symbol one step down (`--gk-icon-sm`, 18px), the title 2px above its text, and **no margin**
  (`margin: 0`) — the surroundings set the spacing; a `gk-mb-*` on it still wins. The text may wrap:
  `min-width: 0` on `.gk-message-content` and `overflow-wrap: anywhere`, so a long address breaks
  instead of pushing out of the box.
- **No colour of its own.** It takes the tone from `.gk-message-info`, `-success`, `-warning`,
  `-error` or none, light and dark, exactly as the message does — there is no dark rule for it.
- **Its place in the file is part of the rule.** After the phone rules, because `.gk-message` gets
  padding `12px 14px` on a phone at the same weight and the later rule wins (on a phone the small one
  stays at 10px); before the margin utilities, for the same reason the other way round.
- **`.gk-message-compact` is not this, and is unchanged.** It already existed (padding `10px 16px`,
  13px) and keeps the 16px margin of `.gk-message`; some twenty Panel views use it and count on that
  margin, so taking it away would have moved all of them. The small message is a class of its own.

Text on the tinted band, measured in a browser in all six themes and without one, light and both dark
spellings (`[data-gk-mode="dark"]`, `.gk-dark`), with and without `themes.css` — weakest value per
tone, light / dark / dark class: neutral 16.30 / 14.03 / 14.64, info 7.40 / 5.66 / 6.76, success
8.31 / 7.92 / 9.45, warning 6.11 / 8.24 / 9.76, error 7.91 / 6.93 / 8.12 (WCAG AA asks 4.5:1). The
message without `-sm` is identical to the digit — the same colours — and had not been measured at
all before.

### Tests
- `tests/message-sm.test.php` (new): the box as written above and denser than `.gk-message` and
  `.gk-message-compact`, read from their rules; the text size a token between 12px and the body
  size; the rule after the phone rule of `.gk-message` and before `.gk-mb-0`; no colour, background
  or border in any rule of `.gk-message-sm` and no dark rule of its own; `.gk-message` and
  `.gk-message-compact` as they were; the dark text of every tone on its tint over each dark ground a
  message sits on, computed, at least 4.5:1.
- `ci/farben.js`: the neutral small message in the palette check, and Text and Title of every tone —
  with and without `-sm` — against the band that really lies under them, in every theme and mode.
- `ci/browser.js`: the small message in a browser — box, symbol, title, a long address that breaks,
  `gk-mb-3` that still wins, the empty announcement that still takes no room, phone width and dark
  mode.
- `tests/blocks.test.php`: the marks speak of the last two minors, and those moved to 1.95 and 1.94 —
  the picker is the block new in 1.94, the message the one changed in 1.95, and the region (1.93) is
  now the block that has fallen out of the window and carries no mark.
- `css/blocks.json`: `gk-message-sm` in the Message block; the demo shows it; SPEC.md and the skill
  describe it.

---
## [1.94.0] - 2026-10-12

### Added — the image picker: choose one image out of many, in a compact grid
`GridKit\ImagePicker` and `GK.picker`. A list of images with a full row each is fine for three and
unusable for thirty; the SSI Panel built that list by hand — preview left, title, a loose button
right — and with ten images a header-picture choice filled the screen (a phone: 4 700 px for
thirty). The picker is a grid of 16:9 tiles, about 150 px wide, two to a row on a phone, that works
like a radio group, shows the first two rows with a "Show all 30" button and opens the viewer on a
large version of the picture it was given.

```php
\GridKit\ImagePicker::render([
    ['value' => 'a.webp', 'title' => 'Fireplace at dusk', 'thumb' => '/t/a.webp', 'full' => '/g/a.webp',
     'meta' => 'Own image', 'checked' => true],
    ['value' => 'b.webp', 'title' => 'Mountain road', 'thumb' => '/t/b.webp', 'full' => '/g/b.webp',
     'meta' => 'AI generated', 'badge' => 'AI', 'actions' => [['name' => 'remove', 'label' => 'Remove']]],
    ['value' => 'c.webp', 'title' => 'Not released yet', 'note' => 'Waiting for approval', 'disabled' => true],
], ['name' => 'cover', 'label' => 'Header image']);
```

- **Native radios underneath.** One `<input type="radio">` per image, drawn by its own box-shadow over
  the picture, so a click or a tap lands on it, the form submits its value and Tab, the arrow keys
  and a screen reader work without a line of script. Picking is the radio's own `change` event: a
  page that listened to the old list needs nothing new. The radio is named by the tile's title
  (`aria-labelledby`, the title is a `<label for>`, so a click on the words picks too) and described
  by what the caller says about the picture (`meta`, `note`).
- **The state is never colour alone.** A picked tile has a check mark (a shape), a 3 px primary
  ring with a white inner ring that shows on a dark and on a light picture (a size) and a bold
  title; the others have a 1 px line. Keyboard focus is a 3 px primary outline with an offset.
  Disabled tiles are dimmed and say why (`note`).
- **Per image:** `value`, `title`, `thumb` (the SMALL picture for the tile), `full` (the LARGE one the
  viewer loads when it opens; omitted, the tile has no zoom button and a placeholder), `alt`,
  `meta` (where it comes from), `note`, `badge` (a short word on the picture), `checked`,
  `disabled`, `actions` and `data`. Options: `name`, `id`, `label`, `rows` (default 2, 0 = never
  fold), `status`, `class`, `labels`. `html()` returns the markup, `render()` prints it,
  `tileHtml()` writes one tile.
- **Fold by rows.** Only the first `rows` rows show, then `Show all N` / `Show fewer`
  (`aria-expanded`). How many tiles that is comes from the columns the grid resolved to
  (`gridTemplateColumns`) — nothing is laid out first to be measured — and again when the width
  changes. The picked tile and the one holding the focus never fold away.
- **A roving tab stop for the magnifiers.** The zoom button of ONE tile — the one with the focus, else
  the picked one — is in the tab order; the others are `tabindex="-1"`; the arrow keys move between
  tiles and the viewer reaches every picture. A caption action ("Remove") stays in the tab order on
  EVERY tile: the focused tile is always the picked one, and an application will not let the picked
  image be removed, so rationing the actions left every other image's button out of the keyboard's
  reach (found in review, with the first draft that rationed both).
- **Actions are events.** A caption button (`remove`, …) fires `gk:pickeraction`
  (`detail: { action, value, item, picker }`, bubbles) and does nothing else: the library never
  deletes. The application decides and asks "Really?" (`GK.confirm`), then calls
  `GK.picker.remove(picker, value)`.
- **Script:** `GK.picker.init(root)` (also from `GK.initContent`), `.add(picker, item, { select, focus })`
  (an upload that arrived: appended, picked as a click would — one `change` — and focused),
  `.remove(picker, value)`, `.refresh(picker)` (after a script changed which radio is checked) and
  `.tileHtml(item, opts)`, which writes the same markup PHP does; `ci/browser-picker.js` compares the
  two. Words: `picker.*` in both languages, exported to the browser as `picker_*`, and overridable
  per picker (`labels`), so an application can say "Show all 30 images".
- **Icons.** `zoom_in` and `image` join the inline SVG set, in PHP and in `GK.table.iconSvg` (the
  parity test holds the two together): a tile has no icon-font ligature that could show as a word.
- **Classes (the block "Image picker" in `css/blocks.json`):** `gk-picker` (the container), `gk-picker-status`,
  `gk-picker-grid`, `gk-picker-item` (one tile), `gk-picker-media` (the 16:9 picture area),
  `gk-picker-img`, `gk-picker-ph` (the placeholder without a picture), `gk-picker-input` (the radio,
  drawn as the picture area), `gk-picker-check`, `gk-picker-badge`, `gk-picker-zoom`,
  `gk-picker-caption`, `gk-picker-title`, `gk-picker-action`, `gk-picker-note` and `gk-picker-more`.
  One class each, nothing nested deeper than a sibling combinator and no `!important`: an
  application restyles a tile with one class of its own.
- **Touch and motion.** The zoom button is a `--gk-target-min` box with a 30 px chip drawn in it,
  and the caption action grows from 32 to `--gk-target-min` on `(pointer: coarse)`; the action
  sits in the caption row, below the picture, so a tap that picks never reaches it. With
  `prefers-reduced-motion` the ring does not ease.

### Changed — the lightbox can show more, and pick
`GK.lightbox.show(entries, index, from)` opens the viewer on a list the caller built (`src`,
`caption`, `meta`, and for a picker `choose()`, `selected()`, `disabled`, `returnTo()`); the gallery's
own tiles still come in through the click handler and are the same thing with `src` and `caption`.
- A second line, `.gk-lightbox-meta`, under the caption (where the picture comes from), and a row,
  `.gk-lightbox-actions`, with **Select this image** and a visible **Close** — only when an entry
  can be picked; a plain gallery shows no row. On the picture that is already picked the button
  says "Selected" and stays pressed; on one that cannot be picked it is disabled — and the focus
  moves to "Close" instead of falling onto the page behind. White on the dark overlay in every theme
  (the theme's primary measured 4.47:1 under white in the default light palette).
- Closing gives the focus back to the zoom button of the picture last shown (after paging from 3 to
  8 the place a person knows is 8), or to the radio that took the pick, falling back to the opener
  when the target is not on screen (a folded tile). The one `_gkRestoreFocus` is used, as before; a
  target other than the opener is scrolled into view (`block: "nearest"`) — after paging from 1 to
  29 the ring was on a button below the fold. The dialog is named by the picture it shows
  (`aria-label`). `GK.lightbox.refresh()` redraws the buttons when something changed behind an open
  viewer; a picture that arrives meanwhile (`GK.picker.add` with `focus`) does not take the focus out
  from under the dialog.
- New lightbox classes: `gk-lightbox-stage`, `gk-lightbox-foot`, `gk-lightbox-meta`,
  `gk-lightbox-actions`, `gk-lightbox-choose`, `gk-lightbox-dismiss` and `gk-lightbox-pick`.
- The picture lives in a stage (`.gk-lightbox-stage`) that takes the height the foot (caption,
  meta, buttons) leaves, instead of a fixed `85vh`: on a phone held sideways the caption no longer
  covers the picture, and a viewer with a row of buttons has room. A long caption wraps instead of
  running out of the screen. In a picker's viewer (`.gk-lightbox-pick`) a small picture fills the
  stage — a 640 px upload is shown as large as the screen allows — and its shadow follows the
  picture (a filter); a plain gallery keeps showing natural size. Clicking the dark area around the
  picture still closes it. Buttons draw a white 3 px focus ring on the dark overlay.
- **What changes for a plain gallery (every application that uses `.gk-gallery-item[data-lightbox]`):**
  behaviour is the same (open, ← →, Escape, the dark area, the focus back, no extra buttons), the
  layout is not. The caption is no longer a one-line pill laid over the picture's lower edge but
  stands under it and wraps; a large picture is bigger (1378×689 where it was 1268×634 at
  1440×900) and on a phone held sideways it no longer sits under the caption. Measured against 1.93.4
  at 1440×900, 390×844 and 667×375.
- On a screen under 480 px high (a phone held sideways, a window at 200 % zoom) caption and buttons
  share one line and the origin line is dropped, so the picture keeps its room (174 px of 375 became
  at least 200).
- `prefers-reduced-motion`: the viewer's entrance is switched off in its own block.

### Added — the demo has a Gallery section
A "Gallery" entry in the sidebar, with the plain gallery (moved from "Navigation"), the picker with
six, thirty, one and no images, a form that shows its value, a log of the events it hears, "Add a
new image" (`GK.picker.add`), a remove button that asks first, the keyboard in a table and the
script. The examples are `showcase()` listings: the code shown is the code that ran.

### Changed — the Releases page is fed by a workflow
`ci/github-release.yml` (to be switched on the way `github-actions.yml` is — below) creates the GitHub Release for every pushed `v*` tag (title
"GridKit <version>", the notes of that version from `ci/release-notes.sh`, a "Live demo" line) and
has a button, "Run workflow" with `since` (default 1.54.0), for ONE catch-up Release for the tag of
the current `VERSION`: the newest version in full and every version since as the titles of its
changes. 58 tags (v1.55.0 … v1.93.4) had no Release object; the page ended at v1.54.0. Idempotent: a
tag whose Release exists is left alone, the catch-up replaces the notes of an existing Release
instead of adding another; the tag must equal `VERSION`; `--verify-tag` never creates a tag. One
concurrency group for every start, the mode decided by the event (the button pressed on a tag is
still the catch-up), `since` matched with `[[ =~ ]]` (a second line used to pass `grep -E`), no
`--latest` (GitHub picks the highest version; an old hotfix tag must not become "Latest").
`ci/release-notes.sh --since` fell over GitHub's 125 000-character limit (1.54.0 → 1.93.4 is
150 000): past 120 000 bytes it now prints the newest version in full and the titles of the rest.
It sits in `ci/`, not in `.github/workflows/`: the token this repository is pushed with has no
`workflow` scope, `.gitignore` keeps that folder out of commits (a tracked workflow once blocked
every push for three days) and a test now fails if one is tracked. `ci/README.md` has the commands
to switch it on. It cannot be run from a checkout, so it is held to what it says
(`tests/package.test.php`) and its YAML was parsed.

### Changed
- `README.md`, the landing page and `GRIDKIT_SKILL.md`: seventeen components, twenty-two classes in
  `src/`; the skill has a section "Image picker — ImagePicker" and the component and class tables a
  row for it. `css/blocks.json`: block "Image picker" and the new lightbox classes, 1.94.0.

### Tests
- `tests/picker.test.php` (new, 174 assertions): the markup (one radio per image in one group, named
  by its title, described by its origin, `alt=""`, lazy, inline SVG icons, the zoom button on the
  picture and the action in the caption row, no zoom without a large version), escaping of every
  value that comes from the caller (script tag, attribute break, a `data` name cut to letters, an
  `id` stripped), no inline style or handler, options and words as data attributes, the status
  line, ids unique across two pickers, `tileHtml` is what `html` is made of, the stylesheet
  (check mark on `:checked`, 3 px ring, 3 px focus ring, `[hidden]` really hides, finger-sized
  targets, `pointer: coarse`, 16:9, no `!important`, reduced motion), the script's contract (events,
  fold by `gridTemplateColumns`, roving tab stop, the viewer's `choose`/`returnTo`), the guide, the
  demo and the words in both languages.
- `ci/browser-picker.js` (new, 83 cases in Chromium, outside the suite like `ci/browser.js`) on
  `ci/browser-fixture.php --picker`: 1, 3, 12 and 30 images at 850 px, 390 and 360 px (tiles per row,
  rows shown, "Show all", the picked tile beyond the fold, no sideways scroll); picking with the
  mouse and the keyboard (arrows skip a disabled tile, a 3 px ring, one `change`); the tab order;
  the viewer (opens on the large version, ←/→, Tab trap, Escape, focus back to the right zoom
  button, paging to a folded tile, "Select this image", "Selected" on the picked one, a click on the
  dark area, a small picture enlarged); `gk:pickeraction` and `GK.picker.remove`; `GK.picker.add`
  produces the markup PHP writes; two pickers stay apart; a plain gallery is as it was; a touch
  screen gets 44 px targets; dark contrast; reduced motion; a portrait picture is cropped to 16:9,
  not squeezed (found while writing it: an in-flow `<img>` gave the box its own height).
- Found by an independent review of the first commit and fixed before release (each with a case in
  `ci/browser-picker.js`, 83 cases now): a caption action out of the keyboard's reach; the focus ring
  below the fold after paging far; the focus dropped on the page when "Select this image" disables
  itself under it; an upload that arrives under an open viewer taking the focus (and the viewer's
  button not noticing the new pick); the JS side of the escaping — `GK.picker.add`, the viewer, the
  status line, the dialog name — had no test that would fail (hostile text in every field, `$&` and
  `$$` in a title, a `data` value, an action name); `String.replace` with a title as the replacement
  (`$&` read as a pattern, in the status line and in the application's question); the id prefix and
  an icon name written raw by the script where PHP cuts and escapes them; the pick button's contrast;
  a stale "show all" that unfolded pictures added later; the status line naming the first of several
  `checked` where the browser keeps the last.
- Found by that run and fixed before release: the pointer's 2 px hover ring beat the 3 px picked
  ring (`:hover` outranked `:checked`: a picked tile looked less picked under the mouse); the
  portrait tile; a title measured against black in a fixture without a page background.
- Gegenproben (`scripts/gegenprobe.php`, each: break it, the test goes red, put it back byte for
  byte, green): the check mark on `:checked`, the roving tab stop, the focus return, Escape, the
  fold, the picked tile staying visible, the viewer's pick, the Tab trap, the escaping of the title,
  the `gk:pickeraction` event, the hover ring that must not thin a picked tile and the focus after a
  pick. (A guard of the picker's own for `[hidden]` was written first and taken out again: the
  library-wide rule for every `gk-` class — 1.92 — already covers a folded tile, and a Gegenprobe
  on the guard stayed green.)

## [1.93.4] - 2026-09-28

### Fixed — active chips keep their colour role in dark mode
In dark mode `[data-gk-mode="dark"] .gk-chip.gk-chip-active` (0,3,0) came after every chip colour
(`.gk-chip-danger.gk-chip-active`, 0,2,0) and outranked it, so every active chip wore the tonal
container of the chip without a colour: wherever filter chips carry colours (on the SSI Panel,
receipts, expenses, banking, the chart of accounts and the website monitor), a danger, success,
warning and neutral chip looked alike (found in round 29; measured in 1.93.3: one ground per theme
for every active chip, danger to neutral).
The chip's dark rules now stand with the chip, before its colours, and each colour names both dark
spellings (`[data-gk-mode="dark"]` and `.gk-dark`) in its own selector list, at the same 0,3,0 —
one rule per colour for both modes, nothing more specific than before. Danger and success write
their white as a literal, as the filled buttons do: `--gk-on-primary` is dark in dark mode, and dark
text on the danger fill read 2.3:1 there. In dark mode neutral carries light text, so its count takes
the darker tint again; warning keeps the lighter one, as in light.

Dark, all six themes and without one, both spellings, at rest and under the pointer: danger/red
`#dc2626` label 4.83:1, count 6.87:1; success/green `#047857` 5.48 / 7.60; warning/orange `#fbbf24`
8.79 / 9.69; neutral 9.26-10.78 / 10.99-12.42 (was: every one of them on the tonal container,
5.99-8.21 / 7.79-9.97). Primary and blue are the chip without a colour in light and stay it in
dark; the chip without a colour, the inactive chip and every light value are unchanged: 6912
computed values in 24 combinations of theme and mode, with and without `themes.css`, at rest, on
hover and focused, differ only on the active colour chips in dark mode. The focus ring is the same.

### Tests
- `tests/contrast.test.php`: an active chip with a colour role wears, in both dark spellings and
  under the pointer, the background and border it wears in light; label and count at least 4.5:1,
  the count never weaker than the label; no colour, danger, success, warning and neutral each have a
  ground of their own; primary, blue and the chip without a colour stay on the tonal container. The
  count test picks its rules by the chips they reach, no longer from a written-out list, which knew
  only the selectors of its day.
- `ci/farben.js`: every colour role and alias, at rest and under the pointer, in all six themes and
  without one, light and both dark spellings: the ground is the colour of its role and never that of
  the chip without one, the label reads at 4.5:1, and the count never reads weaker than the label.
- `tests/contrast.test.php`: "dark component rules take their TEXT colour from a role" read the
  stylesheet only from the marker of the dark component section on, and the chip's dark rules now stand before
  it (a literal written into them stayed green; review of this release). It now reads every rule
  whose selector list names `[data-gk-mode="dark"]` or `.gk-dark`, wherever it stands, and checks
  that every dark selector of the file sits in a rule it read. Two literals are allowed for one rule
  each, bound to selector and value: `#1f2937` on the active warning chip (no role carries dark text
  on amber in both modes) and the white veil `rgba(255, 255, 255, 0.78)` of the dark sidebar, which
  had stood outside the section all along.

## [1.93.3] - 2026-09-28

### Fixed — the count in an active chip is readable
`.gk-chip-count` lays a tint over its chip. On an active chip that tint was 20 % white under white
text, so it took away what the accent had: the count measured 3.19–3.90:1 in light mode and
3.68–4.67:1 in dark, in every theme, on every page with `FilterChips` (SSI Panel, round 28). The tint
now moves away from the text: `rgba(0, 0, 0, 0.2)` under light text, and the old white tint only
under the dark text of `warning`/`orange` and `neutral` (a dark tint would put warning at 4.43:1).
In dark mode every active chip carries light text (the dark chip rule outranks the chip colours), so
every count takes the dark tint there. Now 6.37–7.89:1 light and 7.79–9.97:1 dark. Nothing else
about a chip moved: label, ground, border and focus ring measure identical at rest, on hover and
focused, and the count of an inactive chip is untouched.

### Changed — the hover of a filled primary, success, warning or danger button is stated once
Two rules painted it: `.gk-btn-filled.gk-btn-X:hover` and the default variant
`.gk-btn.gk-btn-X:not(.gk-btn-outlined):not(.gk-btn-text):not(.gk-btn-tonal):hover`, which also
matches a button carrying both `.gk-btn-filled` and `.gk-btn` and wins on specificity. Both named
the same colour, so a change to the filled rule changed nothing on screen (noted in 1.93.1).
Background and border now stand in ONE selector list per role, where the filled rule stood; the
shadow stays on `.gk-btn-filled` alone, which is also the only selector that reaches a button with
`gk-btn-filled` and no `gk-btn`. Primary had the same two rules with the same colour and joined the
same way. Nothing looks different: 136080 computed values (every variant and role, with and without
`gk-btn`, plain, small, disabled and inside a message, at rest, on hover, focused and pressed, seven
theme settings, light and both dark spellings) are identical to 1.93.2.

Neutral is left as it is, on purpose: its two hover rules name DIFFERENT colours
(`.gk-btn-filled.gk-btn-neutral:hover` darkens the secondary colour, the default variant mixes in
18 % of the text colour), so a neutral button looks different under the pointer depending on whether
it carries `gk-btn`. Every `Button::render` button takes the default-variant colour. Which one is
right is a design decision; choosing it changes what one of the two spellings looks like, so it
waits for a round of its own with a measurement before and after. A comment at the rule says so.

### Tests
- `tests/contrast.test.php`: the count of every active chip colour, at rest and under the pointer,
  light and both dark spellings, followed through the rules of the stylesheet — at least 4.5:1 and
  never weaker than its label. And exactly one rule paints the hover fill of each filled primary,
  success, warning and danger button, with the shadow left on the filled rule. The rules are picked
  by the buttons they reach — every spelling of a filled button, light and both dark spellings — not
  by the colour they set: picked by colour, a second hover rule with another colour went uncounted
  and the suite stayed green (review of this release). The cascade over every rule that reaches the
  button under the pointer has to end on the hover token, for background and border, shorthand and
  longhand alike.
- `ci/farben.js`: the count of the resting, the active, the hovered and each coloured active chip,
  in all six themes and without one, light and both dark spellings.
- `ci/browser.js`: buttons with `gk-btn-filled` and no `gk-btn` are measured too; the border of a
  filled button is its fill at rest and on hover; filled primary, success and danger keep their hover
  shadow; and the fill under the pointer is what the role's hover token computes to, in every
  spelling, light and dark, so whichever rule wins has to be the one that names the token.

## [1.93.2] - 2026-09-27

### Fixed — a disabled button can be enabled by a script
`Button::render(…, ['disabled' => true])` gave the `<button>` both the `disabled` attribute **and** a `disabled`
class. The class rule (`opacity: .45; pointer-events: none`) survived `button.disabled = false`, so a button a page
had enabled stayed grey and unclickable until a reload (Panel „Meine Webseite": „Veröffentlichen" after a change,
27.09.2026). The class is no longer set; the element is always a `<button disabled>` and `.gk-btn:disabled` styles
it. The rule `.gk-btn.disabled` stays for pages that set the class themselves.

## [1.93.1] - 2026-09-26

### Fixed — filled buttons answer the pointer and stay readable on hover

- **Primary, dark mode:** the hover changed the button by 1.03:1 — nobody could
  see it. `--gk-primary-hover` is derived darker (`l - 0.06`, DERIVED STATE
  COLORS) and a dark-mode rule put `filter: brightness(1.1)` on top, so the two
  cancelled out. The filter is gone: 1.24:1 in dark, 1.30:1 in light.
- **Success, warning, danger:** the hover of a filled button was the light ROLE
  tone (`--gk-success-hover` …), not a step from the dark FILL — the button got
  lighter under the pointer and white text dropped to 2.0–3.1:1 (danger in light
  barely changed at all, 1.03:1). New tokens `--gk-success-fill-hover`,
  `--gk-warning-fill-hover`, `--gk-danger-fill-hover`, derived one step darker
  than the fill, with literal fallbacks. The role hovers stay as they are for
  tonal and outlined buttons. Note: `.gk-btn.gk-btn-success:not(…):hover` also
  matches `.gk-btn-filled` buttons and wins on specificity; both rules now name
  the same colour.

Found by the SSI Panel reviews of rc965 and round 18. `ci/browser.js` measures
every filled button in both modes as painted (background through the button's
own filter on a canvas, because a computed background never shows a filter):
a visible step of at least 1.1:1 and text of at least 4.5:1 on the hover colour.

### Tests

- `Table::percent('-0')` and `percent(-0.0)` show "0 %" — handled since 1.81, but
  no case held it: removing the line kept the suite green.

---
## [1.93.0] - 2026-09-24

Two requests from the people who use GridKit most. Martin asked to see what has
changed without reading the whole changelog: marks in the demo, the way
Fomantic UI marks what is new, and the news of each version in one place. And
Vespera's admin said "Saved." to the eye only: its status line was written
`hidden` until the first message and shown in the same step its text arrived,
so the live region came into being at the moment it spoke — VoiceOver and NVDA
often read nothing. Written visible and empty instead, a `.gk-message` drew an
empty box. Both are fixed at the source, once.

### Added — an announcement that is heard: `.gk-announce`, `GK.announce()`

```html
<div class="gk-message gk-message-compact gk-announce" id="saved" role="status" aria-live="polite" aria-atomic="true"></div>
```

```js
GK.announce('saved', 'Saved.', 'success');   // tone: info, success, warning, error
GK.announce('saved', '');                    // empty again
```

The region is on the page from the start and never hidden, so a screen reader
knows it before anything is said in it. Empty, it has no box — not
`display: none` or `visibility: hidden`, which would take it out of the
accessibility tree again, but the clip of `.gk-sr-only`: no border, no padding
and no gap in a flex column. Words bring the box back. `GK.announce()` changes
only the words (as text, never HTML) and the tone of a `.gk-message`; the same
words twice are no change and nothing is read, so a repeat empties the region
and writes the words back 150 ms later. It takes an element, an id or a
selector, adds `role="status"`, `aria-live` and `aria-atomic` when the markup
forgot them, and takes `hidden` off — the next message is heard, this one may
not be. `GK.init()` does the same for every `.gk-announce` on the page before
anything is announced. `GK.melde()` is the same function under the name
Vespera's pages already call.

### Added — what is new, and since when: `css/blocks.json`

Every `gk-` class of both stylesheets, in the block the demo shows it as, with
the release that first shipped it — 679 classes in 55 blocks, read once out of
the git history: the first commit whose stylesheet has the class, counted from
the last time it appeared, with the version of that commit (the tag before
1.0.0, when VERSION was still renumbered). A block's "since" is its oldest
class. "changed" is the last release that changed a block without adding a
class to it, kept by hand from 1.92.0 on; a new class marks its block changed
by itself.

A JSON file beside the stylesheet, not a PHP array or comments in the CSS:
PHP, the demo, a test, an agent and a consuming project all read it without
running anything; a comment beside a rule would be one of many rules a class
spreads over, and a class list inside PHP could not be read by anything else.
It ships with the package, because a project with GridKit in `vendor/` is who
asks "does my copy have this block?".

### Added — marks in the demo, and the news at the top

Each block's heading in the demo says "since 1.0", and a block that is new or
changed in the last two minors carries a mark: "New in 1.92" in the green
label, "Changed in 1.92" in the blue one — GridKit's own labels, no new colour
(4.5:1 or better in both modes and every theme, measured in `ci/farben.js`). At
the top of the demo a box shows the last two minors: the blocks each brought or
changed, from the index, and the headings of their changelog entries, read out
of this file — the demo writes no second text. A demo in German says "Neu in"
and "Geändert in". The admin blocks of 1.92 each have their own heading now, so
each carries its own mark, and the hand-written "v1.10" chip beside TableHeader
is gone.

### Fixed — a line break is not empty, one heading says "since" once

A review of this release before it shipped found four things, fixed in it.

- **A region written with a line break between its tags drew the empty box.**
  "Empty" is CSS `:empty`, and that matches no child node at all — the line
  break a PHP or TypeScript template writes before `</div>` is a text node. The
  box this block exists to avoid came back, 22 px high, and `GK.init()` left it,
  because it only looked at an empty `textContent`. Init now empties a region
  that holds nothing but white space; the skill and the spec say to write
  nothing between the tags, because a page without the script, or before it
  runs, still shows the box.
- **"since 1.91 since 1.91 Changed in 1.92".** A heading that shows two blocks
  called `since()` twice, and said so twice, to the eye and to a screen reader.
  `since('row-link', 'sheet')` now takes several blocks and says "since" once,
  for the oldest, with each mark once ("New in" wins over "Changed in" for the
  same release). The marks lost their left margin, which indented them when
  they wrapped onto a line of their own on a phone; a space between the marks
  makes the heading read "1.91 Changed", not "1.91Changed".
- **20 classes were dated 1.27.2 that are older.** They first reached git with
  the commit that rescued the live 1.27.2 from the server, so the history
  dated them there. The changelog names where they came from: the global
  search (`gk-search-*`) in 1.24.0, table groups and the number format
  (`gk-table-group*`, `gk-num*`, `gk-td-num`) in 1.25.0, `gk-cell-sub` in
  1.27.1. Two it does not name (`gk-rowpager-count`, `gk-code`) stay at 1.27.2.
- **The suite was red on the commit.** `demo/_whatsnew.php` is a partial, and a
  leading underscore on a tracked file reads as a probe script left behind; the
  number below was counted before the file was tracked. It is on the list of
  partials now.

### Tests

- `tests/blocks.test.php` (new, 12 tests, 1889 assertions): the index against
  both stylesheets in both directions (a class in neither, a class in two
  blocks), its versions against VERSION and the changelog, a class new in this
  version named in this entry, VERSION's heading as the first the demo reads,
  the marks (own labels, German too) and the news box as the demo renders them,
  every new block marked in the demo, the announcement's markup, rule and
  script. Confirmed by breaking what each covers — twelve breaks, twelve reds:
  a new class and a missing one, a stale one, VERSION 1.94.0, a class dated
  after VERSION, `display: none` on the empty region, no repeat, the demo
  region written `hidden`, the new block unmarked, a mark in a colour of its
  own, a heading left out of the box, the new class missing from this entry.
  The fixes above, seven more: two `since()` side by side again, a key named
  twice counted twice, "since" the newest block's instead of the oldest's,
  "Changed in" beside "New in" for the same release, init leaving white space
  in a region, the skill without the line-break rule, the partial off the list
  — each red.
- `ci/browser.js`: 9 cases on a new fixture (`browser-fixture.php --announce`),
  125 in all. The accessibility tree over the browser's protocol: empty, the
  region is a status in the tree and adds neither a box nor a second gap to a
  flex column; init repairs a hidden and a bare region; words bring the box and
  the tone and the tree reads them; a repeat is emptied and written back; words
  are text; emptied, box and tone go and the region stays; a region holding
  only a line break is emptied by init and draws no box (red without the fix). The fault itself is
  a case too: an empty `.gk-message` without the class draws a box, a hidden
  one is out of the tree. Five breaks (`display: none`, no clip, no repeat, no
  init, `innerHTML`) turned the matching cases red.
- `ci/farben.js`: the marks and "since" on a card and on the page, every theme,
  three spellings — weakest: "New" 8.57:1 light, 6.78:1 dark; "Changed" 7.15 /
  6.14; "since" 6.92 / 5.62 (`.gk-dark`). The same on the demo page itself, over
  every mark it renders: 8.57 / 6.78, 7.15 / 6.14, 6.92 / 5.71.
- `php tests/run.php`, run on the commit: 5559 assertions, none red.

---
## [1.92.0] - 2026-09-24

Vespera's admin — users, usage, modules and the situation page — is built with
GridKit 1.91 and still carried about 465 rules of CSS of its own, around 100 of
them overriding GridKit. The aim was an admin with no stylesheet at all. Going
through those rules one by one, each was either a block GridKit did not have or
a GridKit fault the page had worked around: four were faults, fifteen were
missing blocks. They are below, each named once, in roles only, so the same rule
is right in light and dark mode and under every theme. Six more candidates were
not built because a block that exists already does the job: filter segments (the
filters go), a status line (a `.gk-message`, now that `hidden` hides it), a
plain list (`.gk-table-minimal` with `tr.gk-row-link`), pill and count labels
(the density's own labels stay), a meta list (text with " · "), and a plain
sheet header or body stack (the standard header; `.gk-flex-col .gk-gap-xl`).

### Fixed — `.gk-main` had no rule

The skill and `skeleton.php` have shown `<main class="gk-main">` as the content
area with "padding and max-width" since 1.36.0, and no rule gave it either: the
last one naming the class went in 28538aa. Every page wrote the padding itself.
It is a rule now: 24px 28px 48px, 16px at the edge of a phone, at most
`--gk-main-max` (1680px, a hook the page may set). A test now reads every class
name the skill and the skeleton mention and fails on one no stylesheet rule has
— comments stripped, so talking about a class does not count as styling it.

### Fixed — the `hidden` attribute did not hide a GridKit block

The browser's own `[hidden] { display: none }` loses to any author rule that sets
a display, and most blocks set one: `<div class="gk-message" hidden>` stood on the
page, because a message is flex. The sheet, the modal overlay and the folded
table rows each had a guard of their own; a page hiding anything else wrote one
too (Vespera's admin had three). One rule for every element with a `gk-` class,
`:where()` for zero specificity and `!important` so that it wins the hidden state
and nothing else.

### Fixed — the docked sheet's close button was 40px

On a phone it was 44, docked 40, and a page that asks 44 of every target wrote
its own override. The new token `--gk-target-min: 44px` sizes it in both places;
a negative margin keeps the header as tall as the 40px button made it, so the
title does not move.

### Fixed — the sidebar wrote inline script

`Sidebar` and `Header` wrote `onclick="GK.sidebar.toggle()"` and friends — refused
by a Content-Security-Policy without `'unsafe-inline'`, and copied by every page
that built its own shell. They write `data-gk-sidebar-action="toggle|close|collapse"`
now and one delegated listener calls `GK.sidebar.*`, which stays the API. The
menu button said `aria-expanded="false"` from the day it was written and nothing
ever changed it; it follows the sidebar now. And a switch showed no focus at all
— the input is invisible and the slider is what the eye sees — so the slider
carries the focus ring.

### Added — a list as a class: `.gk-table-list`, `->size('list')`

The row the six rules of 1.91 were drawn from, which every list wrote by hand:
64px rows, the name at 15px semibold over the address at 13px, a group heading
without a bar, the summary row set in with a radius. A list neither turns into
cards nor scrolls sideways on a phone — `->mobile()` on it is refused with a
warning. The wrap is a size container, and only this wrap: `container-type` on
every `.gk-table-wrap` would change how tables in a flex row are sized.

### Added — columns that give way by the list's width: `.gk-col-p2` … `-p5`

Which columns fit depends on how wide the LIST is, not the window — the sidebar
is wide, collapsed or gone, and a docked sheet takes 440px. `'priority' => 2…5`
on a `Table` column puts the class on its header and every cell, on the server
and in the client rebuild (a new parity case, confirmed by breaking the client
helper). p5 goes below 1040px of list, p4 below 900, p3 below 720, p2 below 560;
a column without one never goes; anything outside 2…5 warns. The widths are the
list as it stands, border included: a container measures inside its border, and
written as `max-width: 899px` a list exactly 900px wide lost its column — the
browser case caught it.

### Added — the who cell: `.gk-cell-who`, `.gk-cell-sub-wrap`

Rule 3 as a finished cell: avatar, name, a label right after it, the address
underneath. A label that does not fit beside the name moves under it instead of
shortening it; the name is shortened only when it does not fit on its own.
`.gk-cell-sub-wrap` breaks a second line instead of ending it in an ellipsis — of
two people with the same name, "anna@exa…" left nothing to tell them apart.

### Added — the small parts

- `.gk-header-meta`: a quiet line on the baseline beside the header title; it
  gives way first (ellipsis before the user menu is pushed off) and is gone at
  768px and below.
- `.gk-avatar-tone-1` … `-5`: five container role pairs for initials, told apart
  side by side, after the dark `.gk-avatar` rule so that they beat it.
- `.gk-dot` with `-success`, `-warning`, `-danger`, `-primary`, `-muted`,
  `-outline` (an empty ring, told apart by shape) and `-pulse` (still under
  `prefers-reduced-motion`) — four hand-built dots in one admin became one block.
- `.gk-setting`: the settings row of rule 1 — title, one sentence, the switch on
  the right; `-danger` for a switch that locks someone out. A Form `'toggle'` with
  a `'hint'` writes it, the input a `role="switch"` described by the sentence.
- `.gk-field-static`: a value that is only read in the sheet, with its reason.
- `.gk-stat-tiles` / `.gk-stat-tile`: a figure over its word for a sheet or a
  card; `-warning` and `-danger` for the one that needs acting on; also a
  `<button>`, an `<a>` or a `<dl>` (the word as `<dt>` first, the figure still on
  top). `StatCards->compact()` writes them from the same `card()`s. The figures of
  a row stand on one line.
- `.gk-sheet-meta`: a line under the sheet title; the header becomes a grid and
  the close button keeps its corner.
- `--gk-series-1` … `-5` with `.gk-swatch-N`, `.gk-series-fill-N` and
  `.gk-series-stroke-N`: chart colours, the same in every theme, stepped for the
  dark ground. The usage chart carried ten fixed hex colours, the same in dark
  mode. Slots 3 to 5 sit under 3:1 on white; a chart using them shows its values
  as text too (documented).
- `.gk-btn-touch`: a 44px target without the larger type of `.gk-btn-lg` — one
  admin wrote its own 44px on seven buttons.
- `.gk-message-actions`: a place for buttons on the right of a message; the
  status band of a page is a message with a button.
- `.gk-show-mobile`: the counterpart of `.gk-hide-mobile`.
- `.gk-choice`: a radio or checkbox as a bordered answer tile with a letter; the
  tile carries the state and the focus ring (`:has()`; without it the native
  control stays visible). Form field type `'choice'`, with `'multiple'` for
  checkboxes — which a browser cannot require, so GridKit warns and draws no star.

### Fixed — found in review, before the release

Two reviewers went over Vespera's admin built on this version, in a browser at
1440 and 402px, light and dark. Four findings were GridKit's; a fifth came up
when the fixes were measured again:

- **A setting was squeezed in a sheet.** `.gk-setting` sets `min-height: 44px`,
  and a min-height replaces a flex item's automatic minimum — its content. As a
  child of a sheet body in a column that scrolls, the "Delete account" row
  shrank to 44px for 91px of content, its title on the border and its sentence
  outside it. `.gk-setting` and `.gk-choice` (min-height 52px, the same shape)
  do not shrink now.
- **"Check now" in a warning read at 4.35:1.** An outlined button is
  transparent, so in `.gk-message-actions` it stood on the tinted band and not
  on the surface its text colour was chosen for — in every theme's light mode.
  It carries `--gk-surface` there now, hovered too; `ci/farben.js` did not
  measure the documented example and does now, for every message colour.
- **The empty value was all but gone in dark mode.** `.gk-num-empty` had a dark
  rule of its own with `--gk-outline`: 2.1:1 on a table, 1.6 in the row of the
  open sheet. The dash is text; one rule with `--gk-text-muted` follows the mode.
- **The hidden guard took something away.** With a bare `!important`, a block a
  script shows with `el.style.display = "block"` while `hidden` stays on it —
  visible before — would have vanished. The guard leaves an inline display alone
  (`:not([style*="display"])`); it only adds. `[hidden]` is written last in the
  selector: happy-dom, the DOM many test suites run on, drops an attribute that
  is followed by `:not()` and then hid every `gk-` block of a page.
- **The summary row under the pointer.** `.gk-table-list .gk-table-more-toggle`
  lifted its ground with an 8 % veil on hover; in dark mode its muted sentence
  then read 4.2–4.4:1. It takes the state role now, `--gk-state-hover` (6 %),
  like every other row: 4.6:1 in Vespera's dark admin, at least 4.5 in every
  theme and spelling.

The docs say two more things a page gets wrong without them: a header written by
hand keeps `.gk-header-user` beside `.gk-header-actions`, not inside it (on a
phone that row scrolls sideways and cut Vespera's user menu off), and an address
in `.gk-cell-sub-wrap` breaks where it should with `<wbr>` after the `@` and
before each dot.

### Tests

- `tests/admin-blocks.test.php` (new, 20 tests): the markup PHP writes and the
  contracts between stylesheet, script and documentation for every block above.
- `ci/browser.js`: 34 cases on a new fixture page (`browser-fixture.php --admin`),
  116 in all — the list at 500, 700, 900 and 1100px with nothing scrolling
  sideways, the hidden message, the sidebar by attribute, the keyboard focus on a
  switch and an answer tile, the header line at 1440, 800 and 390px. Confirmed by
  breaking the hidden guard, `.gk-main`, the delegation and the query widths: six
  cases went red. From the review: a sheet longer than the screen keeps its
  setting and choice whole, a block shown by an inline display stays shown, and
  on a phone the user menu opens whole beside a header action — each confirmed
  by breaking it (no flex-shrink, the bare guard, the menu inside the actions):
  those three went red.
- `ci/farben.js`: a third section measures the new colours on real elements in
  seven themes and three modes, with and without `themes.css` — every text
  against the ground it really stands on, and the series colours for contrast,
  constancy across themes and distance between neighbours under simulated
  protanopia and deuteranopia (ΔE 9.1 light, 8.4 dark; 19.6 and 19.3 for normal
  vision). The first run reported 1.22:1 that nobody ever sees: a tile with a
  transition was measured mid-way after the mode switch; transitions are
  finished before measuring now. White on the base palette's `#6366f1` for the
  chosen letter measures 4.47:1 — the filled primary button's pair, reported as
  such and not faulted; every theme clears 4.5. Added from the review: an
  outlined button in each message colour, the empty value in a row and in the
  row of the open sheet — breaking the two fixes gave 4.35:1 and 2.1:1 again —
  and the summary row at rest and under the pointer (`:hover` forced through
  the browser's protocol); with the 8 % veil it measured 4.24:1.
  Muted text in the open row (1.91.0) measures 4.46–4.48:1 in one spelling only,
  `.gk-dark` WITH themes.css, where the dark surfaces stay gridkit.css's (the
  themes.css surface ladder answers `data-gk-mode` only). The address has stood
  there since 1.91; the dash now reads exactly as it does. Reported, not faulted.
- `php tests/run.php`: 3659 assertions.

---
## [1.91.0] - 2026-09-23

Two admin lists built with GridKit were turned down on sight: "is that really
GridKit?" A user list of 21 rows had nine columns, two selects, a switch AND a
label saying the same thing in every row — 42 controls, rows 100px tall — bold
running text wrapped over seven lines in a narrow column, and its actions
scrolled off the right at 1280px. Nothing in GridKit had stopped any of it,
because the building blocks for the better list did not exist. The approved
redesign needed three; they are here, and the rules behind them are in the
skill, each pointing at the block that implements it.

### Added — a row that opens something: `->rowLink()`, `tr.gk-row-link`

```php
->rowLink('/users/{id}')                   // a page
->rowLink(['sheet' => 'user-sheet'])       // a side sheet on this page
```

Reading belongs in the row, changing in what the row opens — so the whole row
has to be the way in. The row itself is deliberately NOT made a control:
`role="link"` or a `tabindex` on a `<tr>` replaces its row role, and a screen
reader then hears neither a row nor a link. It carries one real control in its
main cell instead, `.gk-row-target` — an `<a>` or a `<button data-gk-sheet>` —
and a click anywhere else in the row is forwarded to it with its button and
modifier keys. Ctrl-click and a middle click on a cell of a link row open a new
tab, measured in Chromium, as they would on the link. Without JavaScript the
link still works.

What the row leaves alone: a click on a control of its own (a checkbox, a row
button, a cell link), in the checkbox or action column, and the end of a text
selection — someone copying an address out of a row must not be taken to
another page. A row is at least 44px tall whatever the size or density: a
one-line row of a `size('sm')` table measured 31px, the same row as a row link
44. With `:has()` the focus ring goes round the whole row, in card mode round
the card, and the name cell becomes the card's title instead of a value
right-aligned under a "USER" label.

`href` has the allow list and the encoding of a row button's `href`. A sheet
row posts its `params` (always with the row's `id`) to `url` and fills the
sheet with the answer, or hands them to `gk:sheetopen`. A row whose main cell
shows nothing, or whose target is refused, stays a plain row — a link without a
name is a dead focus stop. `'format' => 'html'` and `'email'` are refused for
the main cell (each writes a link of its own, and a control inside a control
reaches nobody), and the cell's own `href` gives way to the row's; each says so
with a warning. The client rebuild of a `setData()` table writes the same row:
two new parity cases report exactly the four benign shapes every other case
does.

### Added — the side sheet: `.gk-sheet`, `GK.sheet.open()` / `close()`

Where a record is read in full and changed. On a wide screen it docks at the
right, 440px, and the list beside it stays usable: a **non-modal** dialog, no
`aria-modal`, no trap. Docked, it is part of the page and lies under the header
(z-index 150, the header 200 or 1100): the first build put it over the header at
1200, and the user menu then opened under the sheet — all ten of its targets,
Profile to the dark-mode switch, hit-tested into the sheet in each of the three
setups GridKit ships (header-first, sidebar-first, sticky), while the keyboard
still walked through them. Below a fixed or sticky header it starts where the
header ends. Below 769px it covers the screen, the header included, and **is**
modal — `aria-modal`, the focus held inside, the page behind held still. Crossing the
breakpoint while it is open switches the behaviour along. The scroll lock is CSS
keyed to the sheet's own `hidden` attribute (`:has()`), not a script writing
`body.style.overflow` — the lightbox and the document modal already do that,
and two owners of one inline style unlock each other.

It follows the static modal: role and name filled in, a bare `&times;` named,
the shared trap, focus given back to the opener. The focus starts on the title,
not on the first control: a sheet opens on every row click, and the modal's way
drew a focus ring on the close button each time a mouse opened it. Escape is
heard on the sheet itself, so it closes the sheet only while the focus is in it;
a confirm opened from the sheet answers first — including in the 50ms before
`GK.confirm` takes the focus, where the first version of this closed both. One
sheet at a time; the row it shows carries `aria-current`. An AJAX form inside
closes the sheet on `{ok: true}`, not whatever modal is on top of the stack.
Opened from inside a modal it lies above it. Without JavaScript the markup is a
docked panel all the same.

### Added — one row standing for many: `tr.gk-table-more`

Thirteen chart profiles stood in the user list as if they were thirteen users.
A `.gk-table-more-toggle` fills its row and shows and hides whatever its
`aria-controls` names, usually a `<tbody hidden>` right after it. Its label does
not flip between "show" and "hide": `aria-expanded` carries the state for a
screen reader and the chevron for the eye, and a label that changes as well says
it twice — rule 1 of the list below, applied to itself.

### Added — six rules for lists, in the skill

`## Six rules for lists` in `GRIDKIT_SKILL.md` (and in the installable skill's
`SKILL.md`, where the must-read rules are), plus a table in `SPEC.md`: one state,
one sign; read in the row, change in the sheet; who in one cell with two lines;
colour only for what needs acting on; group instead of scrolling; running text
never bold, never narrow. Each rule names its building block, and
`tests/lists.test.php` checks that every block named is a class, method or call
that exists. The demo's Table section shows a list that follows all six.

### Fixed — group rows on a phone were empty-labelled cards

Card mode, the default on a phone, turned every `groupBy()` row into a card of
its own: padded, shadowed, and an empty column label taking 40% of it, so
"With access 3" stood right-aligned in a box — measured at 390px. A group row
is a heading between the cards now. The rows a summary row folds stay folded
there as well: card mode sets `display: block` on every tbody and tr, and the
browser's own `[hidden]` rule loses to that.

### Fixed — Shift+Tab from an element outside the tab order left the dialog

The shared focus trap checked only the first and the last control, so from
anything focused inside but not in the tab order, Shift+Tab walked out. It wraps
now when no control lies that way; otherwise the browser's own order stands.
The modal and the lightbox use the same trap.

### Changed — one place per side fills a link target

Row buttons, linked cells and now row links each fill a `{field}` template —
written out three times in PHP and three times in the client, which have to
agree byte for byte — and buttons and row links both map a row's params. One
helper per side now
(`Table::fillTarget()` / `rowParams()`, `_gkFillTarget()` / `_gkRowParams()`),
and a test holds each at one copy. The side sheet's close button shares the
modal close button's rule instead of repeating it. Nothing a page renders
changes: the parity run and the browser cases are the same before and after.

### Tests

`tests/lists.test.php` (new), and 35 cases in `ci/browser.js` — 82 in all.
Eleven of those, every guard a later change could drop without a visible
symptom (the layer check on Escape, the trap, the selection and own-control
checks of a row, the scroll lock, the folded rows in card mode, the lift above
a modal …), and eight suite checks were confirmed by breaking the code they
cover on purpose. So were the layer cases: on a page with GridKit's own header
(`browser-fixture.php --header`) they hit-test the user menu over an open sheet
in every layout, and fail at 1200 in all three — and at 1050, between the
sidebar and the header-first header, still in two.
---
## [1.90.1] - 2026-09-23

### Security — the working copy served what a session had left in it

This directory is the document root of gridkit.at, and the SSI Panel mounts the same
directory at `/gridkit/`. On 23.09.2026 both answered 200 for `INTERNAL-DEPLOY.md` — the
note that describes the servers, git-ignored for exactly that reason — and for
`.claude/settings.local.json`. Being left out of the repository says nothing about being
left out of the site when the working copy is what the web server reads.

`.htaccess` now refuses every path segment that starts with a dot (`.claude/`,
`.playwright-mcp/`, `.CLAUDE.md`; `.well-known/` stays reachable for certificate renewal)
and `INTERNAL-DEPLOY.md` by name. The dot rule is deliberately not anchored at the root,
because inside the Panel the path begins with `/gridkit/`. A test in `landing.test.php`
holds both rules and runs the pattern against the paths that must and must not pass;
removing either rule turns it red.

The Panel itself now serves only `css/`, `js/`, `assets/` and `VERSION` from `/gridkit/`
(SSI Panel rc952); the demo, the sources and the tests stay on gridkit.at.

---
## [1.90.0] - 2026-09-22

Thirteenth round of the SSI Panel's self-maintenance loop.

### Fixed — under a theme, two palettes were running side by side in dark mode

The dark component rules carried GridKit's own palette as literals right next to
role-based declarations, often in the same rule block: a search field took its background
from one palette (`#0d1117`) and its text from the other (`var(--gk-on-surface)`). Under a
theme the literals belong to no theme value at all, so a filter's grey sat a shade away
from the stat label beside it.

Measured under a theme with the new second section of `ci/farben.js`: **77 elements on 33
spots wore a colour that belonged to no theme value.** The sixteen TEXT colours now read
their role, which brings it to 40 on 15 — and every one of those is a background.
`.gk-avatar` was among the sixteen in spirit: it carried `--gk-primary`'s own dark value
as a literal, so the monogram stayed indigo under ocean, forest, rose and amber.

Without `themes.css` nothing moves: each literal equalled the role it now reads. With
`themes.css`, muted text gains contrast on the grounds it sits on — a filter from 6.15:1
to 7.38:1, a table head from 5.62:1 to 6.75:1. On a panel page in dark mode, 910 of 3286
elements change and **not one of them is a background**.

### Fixed — the header changed colour the moment it stuck

`.gk-header` is `var(--gk-surface)`; `.gk-header-sticky` was `rgba(13,17,23,0.92)`,
GridKit's own near-black. Under a theme the two were measurably different — rgb(30,41,59)
against rgb(14,19,26) — so scrolling made the header jump. Both the light and the dark rule
now mix their own surface; the light one moves nothing today, because the light surface IS
white, but it carried the same defect.

### Fixed — the placeholder was unreadable in dark mode and short of AA in light

Two rules became one. The muted role already switches with the mode, so a dark override was
never needed — and each side was broken in its own way: light was `#6e7781`, **4.27:1** on
the resting field, and dark was `var(--gk-outline)` at **2.28:1**, which means dark mode had
simply never had a readable placeholder.

Now 85% of the muted role over whatever ground the field has. Measured in a browser, every
state clears AA: light 4.93 resting and 5.16 focused; dark 5.62 resting, 4.56 focused — the
field moves to `var(--gk-surface)` then, which is where 75% would have failed at 3.90 — 4.96
read-only, and 4.73 throughout without `themes.css`.

`.gk-search` and `.gk-filter` were never named in the rule and carried the browser default.
In the panel the table search is the placeholder a user sees most often; it was 4.11:1 and a
different colour from every field beside it.

### Still literals, deliberately — the 38 backgrounds

They cannot follow their role until the surface ladder in `themes.css` is settled: there dark
`--gk-surface` and `--gk-surface-container` are the same colour, so a modal, a card and the
page ground would collapse into one. `ci/farben.js` measures and reports them without failing.

### Added — component measurement in `ci/farben.js`

A second section renders a probe page carrying exactly the components whose dark rules set a
colour — not the general fixture, which is missing thirteen of them, where "nothing found"
would only mean "nothing looked for". Every probe must be found or the case is red. It runs
four configurations (with `themes.css`, without, `.gk-dark`, and light), proves the theme
attribute actually bites by comparing two themes' accents, and measures the placeholder in
four field states.

### Changed — the documented browser requirement

The README promised only "custom properties" while the hover and focus-ring tokens have
depended on `color-mix()` for a long while, at 35 places. It now says Baseline 2023.
---
## [1.89.0] - 2026-09-22

Twelfth round of the SSI Panel's self-maintenance loop.

### Fixed — in dark mode, the text aliases had stopped meaning what their role means

GridKit carries two names for the same colour: a role (`--gk-on-surface-variant`) and
an older alias (`--gk-text-muted`). In the light block the alias IS the role, so the
two can never drift. The dark block wrote literals instead, and `themes.css` moves the
roles underneath them. Two names for one colour then pointed at two different colours,
and which one a component got depended on which name its author had happened to type.

`--gk-text`, `--gk-text-muted`, `--gk-text-subtle` and `--gk-border` now derive from
their role, exactly as the light block does. **Without `themes.css` nothing moves**:
every literal that was replaced equalled the role it now reads, checked value by value.
With `themes.css`, muted text goes from #8b949e to #94a3b8 — on the brightest dark
ground in the file (`.gk-toast`, #2d333b) that lifts it from 4.14:1 to 4.97:1 and
repairs an AA violation; against the themed page ground, from 4.76:1 to 5.71:1. Normal
text moves from 12.38:1 to 11.87:1, `--gk-border` not at all. **No background changes**:
across a full component fixture and two panel pages only text colours moved.

This is the same fault 1.80.1 fixed for `--gk-surface-variant` and `--gk-text-secondary`;
its warning comment sat directly below these lines and they were left behind.

### Deliberately NOT changed — the four surface aliases

`--gk-bg`, `--gk-bg-muted`, `--gk-bg-subtle` and `--gk-bg-hover` stay literals for now.
In `themes.css` the dark `--gk-surface` and `--gk-surface-container` are the **same**
colour (#1e293b, lines 140 and 144), while this file keeps them a step apart
(#0d1117 / #161b22). Deriving them would make `--gk-bg` and `--gk-bg-muted` identical,
and every muted surface lying on a plain one would lose its edge — a checklist's header
strip, footer and row hover, and the hover state of a dropdown, where you could no
longer see which entry you were about to click. That is a palette decision for the
whole family of systems, not a tidy-up, so the ladder gets its own round. Until then,
prefer the role (`--gk-surface-container`) over the alias in new code.

### Added — `ci/farben.js`

Resolves each alias and its role in a real browser — six themes, light mode and both
spellings of dark mode (`[data-gk-mode="dark"]` and `.gk-dark`) — and reports any pair
that disagrees plus the WCAG ratio of normal and muted text against the ground. Alpha
is part of the comparison: `--gk-border` is the one pair with transparency, and an
opaque `#ffffff` would otherwise have slipped through the RGB channels unnoticed. The
four deferred pairs are measured and reported without failing. Like `ci/browser.js` and
`ci/parity.php` it stays outside the dependency-free suite; run it when touching a
colour variable in either file.

---
## [1.88.0] - 2026-09-21

Tenth round of the SSI Panel's self-maintenance loop.

### Fixed — tapping a field zoomed the whole page in, on every iPhone

Safari on iOS zooms the page into any input whose font is smaller than 16px, the
moment it is tapped, and leaves it zoomed. Everything in GridKit is 14px, so every
field a user types into did it — the table search, every filter, form fields, the
search inside a searchable select, a multi-select, an AJAX select, the hex field
of a colour picker, the compact form of an editor (13px at a higher specificity),
and the login screen, which carries its own style block. All of them are 16px
below 768px now.

The rule sits at the very end of the stylesheet, in a media query of its own: a
media query adds no specificity, so a component rule further down the file wins —
which is what happened to three of those fields on the first attempt. Fields keep
their height except `.gk-search` and `.gk-filter`, which have none of their own
and grow by 1–2px with the type; nothing wraps differently and nothing scrolls
sideways, measured at 390px.

The SSI Panel had been overriding this in its own stylesheet for exactly this
reason — the other systems using GridKit had not.

### Added — `.gk-page-header-actions`

The skill has called `.gk-page-header` "page title + action area" since 1.9, but
the class for that area existed in no stylesheet. The SSI Panel wrote it itself
and uses it in 41 views; ssi-core uses it in 22 views and defines it in exactly
one of them, so in the other 21 it did nothing at all.

It is the Panel's base rule, word for word — minus `align-items: center`. That
one property was left out on purpose: this rule stands outside every media query
and therefore beats an equally specific rule that a host application loads BEFORE
gridkit.css, which is what the Panel does with its own mobile layout for this
class. With `align-items` in it, the Panel's page header went from full width and
left-aligned to centred and 291px, across 41 views. Measured at 390px, not
reasoned about.

Still open, and a question rather than a change: the minimum tap sizes. At 390px
a small button is 27px tall, a chip 31px and a pager key 34px — all under every
touch guideline. The Panel overrides the first two, but not the pager. Raising
them in GridKit changes the look of every system: at 44px only seven pager keys
fit across a phone where nine fit today, and GridKit already has a 40px rule for
row actions that 44 would contradict. That wants a decision, not a commit.

---
## [1.87.0] - 2026-09-21

Ninth round of the SSI Panel's self-maintenance loop.

### Added — a column can link its value and carry a second line

```php
->column('name', 'Customer', ['href' => '/customers/{id}', 'sub' => 'city'])
```

The two commonest cell shapes — "a name that links somewhere" and "a name with a
quiet line under it" — could not be expressed at all. Such a cell is built as a
concatenated HTML string with its own escaping and passed through as
`'format' => 'html'`: a column that is then neither sortable nor searchable
without a second key, and an escaping decision made by hand every time. The SSI
Panel has 62 of those `'html'` columns; about a dozen of them are the two shapes
here, the rest are chips, progress bars and conditional colour, which this does
not touch.

`href` wraps the formatted value in `<a class="gk-cell-link">` — `{field}` from
the row, URL-encoded, with the same allow list of targets as a row button, so a
value can never become a scheme. An empty value gets no link: a link with no
text cannot be reached by keyboard and reads as nothing. `sub` names a second
field and renders it as text under the value, always text whatever the cell's
format is, and left out when empty. A column named in `search()` searches its
second line as well — a row has to be findable by the text standing right there
under its name.

Both keep the raw value, so sorting and searching keep working.

What it is not: `{field}` is a path segment, never a whole target — a stored
`https://…` cannot be linked this way, because the scheme has to be in the
template. There is no `target`. `sub` truncates with an ellipsis and carries no
`title`. And `href` together with `'format' => 'html'` is refused with a warning
rather than nesting one anchor inside another — the caller's own links would
take the visible text away from the safe one.

Three older differences between the two renderers came out while testing this:
a boolean row value rendered as "true"/"false" in the browser and "1"/"" on the
server, the client wrote the cell classes in a different order, and it never
wrote `gk-td-muted` at all — so a muted column changed colour on the first sort.

---
## [1.86.0] - 2026-09-21

Eighth round of the SSI Panel's self-maintenance loop.

### Added — a live table updates what sits outside it, like the AJAX table already did

`<template data-gk-replace="a-css-selector">` in the fresh markup replaces an
element OUTSIDE the container — the summary cards above a list, a status select
beside it, the pager below. `GK.table` has done this since 1.11.4 —
where it was put by a release whose own entry says "liveTable Out-of-Band
Updates", which is exactly the table that could not do it. `GK.liveTable`
could not, although the skill described it for both. Seven views in the SSI
Panel depend on it, so the Panel carried the loop in its own layout — where no
other system using GridKit could benefit from it.

It now happens before `gk-live-reloaded` fires, so a page's own listener sees
the finished state, and the widgets that came with the new markup are bound
again: searchable selects, multi-selects, tooltips and every
`data-gk-live-input` (those sit outside the container by definition, so the
re-bind is document-wide). Both paths share one helper now, `_gkApplyReplacements`.

Put one root element in each template. Both paths write the template's whole
content, so two roots would work — but the Panel's own loop, which this
replaces, took only the first, and nothing should change silently for the seven
views that used it. All of them have one root; that was measured, not assumed.

A template that cannot be applied costs that one replacement and nothing else:
this runs inside the reload's promise chain, where a throw used to swallow the
event, the URL update and the re-binding along with it. A template with no
element in it leaves its target alone rather than deleting it — the loop this
replaces did the same, and an emptied target only comes back on a full page
load. One pointing INSIDE the markup being swapped is skipped with a warning:
it would detach the very node the caller still holds. And the focus survives —
a control that had it is found again by its id, because a page filters by
keyboard through exactly such a replaced select.

---
## [1.85.0] - 2026-09-21

Seventh round of the SSI Panel's self-maintenance loop: the three differences
1.84.0 left behind, so a sorted table is the same table.

### Fixed — a status label changed colour, and sometimes its text, on the first sort

The colour table lived twice: once in PHP and once, shorter, in JavaScript. The
copy knew no `blue` and about half the words, so `active` was green until the
first sort and grey afterwards. It also could not read the
`['color' => …, 'text' => …]` form of a `labels` entry at all — a column that
shows "bezahlt" for a stored `paid` fell back to the stored value.

There is one table now. It travels in the data block, and only for a table that
actually shows labels, so nothing else grew.

### Fixed — a rebuilt row lost the checkbox value, and eight icons it never had

`<input type="checkbox" value="7">` is what the server writes for a selectable
row; the client wrote no value, so page code reading the checked boxes out of
the DOM got empty strings after a sort.

The client's copy of the icon set was also short: fourteen of the twenty-two
kinds PHP draws, and none of them with `stroke-linecap` / `stroke-linejoin`. A
button with `refresh`, `send`, `check`, `close`, `arrow_back`, `lock_open`,
`attach_file` or `link_off` turned into a Material Icons span — the bare word,
where that font is not loaded — the moment the table was sorted, and the rest
grew square corners. All thirty-six names now render byte for byte what
`GridKit\Icon::svg()` renders, and `tests/js.test.php` compares the two lists,
in both directions, so the next gap is caught where it is made.

### Fixed — four ways the shared lookup went wrong

The table is one now, but the key it is looked up by was not:

- `strtolower()` has been ASCII-only since PHP 8.2, so `Überfällig` stayed
  upper-case on the server and was folded in the browser: grey before the first
  sort, red after it. Both sides fold the same way now, and a non-breaking space
  counts as a space on both (PHP's `trim()` does not know it).
- `'text' => null` in a `labels` entry meant "no text of its own" on the server
  and "empty label" in the browser.
- `'color' => false` produced the class `gk-label-` and a label with no styling
  at all (`??` where `?:` was meant).

A parity run now reports two shapes per side on every case (three on `sortable`)
— all of them on the harness's benign list, which gained two entries during this
round's acceptance: the pager wraps its page numbers in a `<span>` on the server
only, and a disabled "Previous" button carries a different `data-gk-page` on
each side. Both are older than this release and neither is ever read.

---
## [1.84.0] - 2026-09-21

Sixth round of the SSI Panel's self-maintenance loop.

### Added — a row button can be a real link

`->button('open', ['icon' => 'visibility', 'href' => '/users/{id}'])` renders an
`<a class="gk-btn …">` instead of a `<button>`. Middle click, "open in new tab"
and the browser's status bar work again — twelve SSI Panel views navigate from a
hand-written click listener on the table instead, where none of that works.

`{field}` is replaced from the row and URL-encoded exactly as `rawurlencode()`
does it, on both sides: `encodeURIComponent` leaves `!'()*` alone, so the client
escapes those five itself, and a lone surrogate (which makes it throw) drops the
link instead of taking the table down with it. `modal` and `onclick` keep what
they had — adding `href` to such a button does nothing rather than silently
replacing its behaviour.

**What may be linked to is a list of what is allowed, not of what is forbidden.**
A deny list loses: `java<TAB>script:` walked through one and the browser ran it,
because it strips control characters before reading the scheme. So every
character up to 0x20 goes first, and then only a relative path, a fragment, a
query or a spelt-out http/https/mailto/tel is kept. A leading `//` is refused too
— it leaves our own origin without naming a scheme. A row's own value can never
become a scheme in the first place, because it is encoded.

**A target with a `confirm` is not a link at all.** A middle click, a Ctrl-click
and Enter each take the browser's native path, and a page whose JavaScript never
loaded would follow the link without ever asking. Such a button keeps its target
in `data-gk-href` and goes only after the question is answered — and it carries
no `data-gk-action`, because going somewhere is not a row action.

### Fixed — three ways a row button changed on the first sort

Found by the parity harness while adding the above. All three are old:

- The client dropped the row's own id from `data-gk-params`. The server has always
  sent it "unless the caller mapped one itself", so after a sort an edit button
  opened its modal as if it were a new record.
- The client put `gk-btn-sm` on buttons with text, the server never did: every
  labelled row button shrank on the first sort.
- The client read only `class` for the colour, the server reads `color` first —
  so a button declared the documented way turned grey on the first sort.

### Fixed — a row value with an apostrophe broke out of its attribute

`_gkEsc()` escaped `<`, `>` and `&` but not quotes, and every attribute the
client writes is quoted. A row value holding an apostrophe closed
`data-gk-params='…'`, and whatever followed became attributes of the element —
`onmouseover` among them, executing on the first hover after a sort. The server
has always escaped both quotes. The hole is older than this release, but the
first fix above made it reachable from *every* row button rather than only from
mapped parameters, which is how it was found.

### Known differences that remain

A parity run now reports 2–3 shapes per side and per case, down from as many as
12 on the `buttons` case. Three of what is left are real and older than this
release, and none of them is on the harness's benign list yet: a `label` column
loses its colour on the first sort (the client's colour table is shorter than the
server's and knows no `blue`, and it cannot read the `['color' => …, 'text' => …]`
form at all), button icons lose `stroke-linecap` and `stroke-linejoin`, and a
selectable row's checkbox loses its `value`.

---
## [1.83.0] - 2026-09-21

Fifth round of the SSI Panel's self-maintenance loop.

### Added — `GK.modal.show()` / `GK.modal.hide()` for a modal that is already on the page

`GK.modal.open()` builds its overlay and fetches the body from a URL. For the far
more common case — the overlay's markup already stands in the page — GridKit
offered nothing but the CSS classes, so every page wrote opening, closing,
Escape, the backdrop click, the focus trap and the focus hand-back itself. The
SSI Panel alone had 83 such overlays: none with a dialog role, none with a focus
trap, most hidden with an inline style. Keyboard and screen reader users ended up
behind the overlay.

```javascript
GK.modal.show('#reg-overlay', { focus: '#reg-name', onClose: () => reload() });
GK.modal.hide('#reg-overlay');   // or hide() for the topmost one
```

It brings the dialog role and a name (through `upgradeStatic`), `aria-modal`
— set on the way in and taken off again on the way out, because it is only true
while the dialog holds the focus — the focus trap, Escape through the one
document listener including its deference to a confirm or an open list above it,
the backdrop click, every close button that belongs to this overlay, and the
focus handed back to whatever opened it, as long as the focus was still inside
when it closed. An overlay it opened carries the class `gk-modal-open`: the hook
for a page's own CSS that has to know whether a modal is up. Hiding uses the
`hidden` attribute (`.gk-modal-overlay[hidden]{display:none}` is new — with the
class in the selector, because the browser's own `[hidden]` rule loses against
any author rule), so a page needs no CSS of its own; `show()` also clears an
inline `display` the page may have left.

What it refuses, rather than half-doing: an overlay `open()` built (that one
belongs to `close()`, which has to remove it), and an overlay the page hides
with a class of its own — `show()` cannot clear that, and a dialog nobody can
see must not hold the focus. Both come back as `null`, the second with a console
warning naming the overlay. `hide()` on a dynamic modal does what `close()` does.

The `display:flex` rule deliberately stays on the bare class. Written as
`.gk-modal-overlay:not([hidden])` it would outrank a consuming page's own hiding
class — four modals on the SSI Panel's receipts page (`.bl-hidden`) would have
torn open the moment this shipped.

`close()` now asks whether an overlay is a static one before removing it — the
one change to an existing path. An overlay that belongs to the page is hidden,
never taken out of the DOM, or the first Escape would have made it unopenable.
Overlays built by `open()` are untouched.

Sixteen cases in `ci/browser.js` cover it in a real browser: opening, the trap,
Escape with the overlay surviving, opening again, the close button, the backdrop,
a `GK.confirm` on top answering Escape for itself while the modal stays, a modal
inside a modal, a dynamic one stacked over a static one, `onClose` running
exactly once, and both refusals. Every one of them was checked by breaking the
code it covers on purpose. Two guards are deliberately NOT covered — removing
either changes nothing a user could see, because a second guard catches it.

---
## [1.82.0] - 2026-09-21

Fourth round of the SSI Panel's self-maintenance loop.

### Fixed — a page reached through the sidebar arrived with dead widgets

AJAX navigation re-bound tables, tooltips and hand-written modals, nothing else. On a page reached
through the sidebar a searchable select did not open, an AJAX form posted natively
and showed its JSON as a page, the client-side pager was missing — until a reload.
`GK.initContent(root)` is now the one list of widget inits, used on page load, for
a modal's body and after every swap; every init in it is idempotent. The
`gk-ajax-nav` event the skill now describes fires for the first time.

### Fixed — the first sort of a static table unwrapped its cells and lost its totals row

The client-side rebuild wrote a bare `<table class="gk-table">` and no `<tfoot>`.
`nowrap()` and `footer()` travel in the data block now and are written again.

### Fixed — a form loaded into a modal got a hand-picked subset of the widgets

The modal body ran its own list — no tabs, no row pager, no accordion, no
static-modal upgrade. It goes through `GK.initContent(body)` now, and that list
binds tables and tooltips too, so page code that inserts a table and calls
`GK.initContent(el)` gets sort, search and paging like the skill says.

### Fixed — the rebuild forgot `loadTime()`, a column's `nowrap` and the wrap guard of number cells

The client-side rebuild now writes the `loadTime()` meta cell ("2 entries · 38
ms", or the time in the columns the footer cells leave), `white-space:nowrap` on
a column with `nowrap` and on every number or currency cell — the same three
things `Table::render()` writes. `_gkNumber()` no longer writes "-0,00" for a
value that rounds to zero; `number_format()` has not since PHP 8.0.

### Changed — a live table with a remembered filter no longer fires `gk-ajax-nav`

`restoreSession()` answers a sidebar navigation with a full load of its own.
The event stayed silent there until 1.82.0 because navigation never bound live
tables; now that it does, page code would have bound things on a page about to
be replaced. `GK.liveTable._redirecting` tells navigation to stop. The double
load itself stays — it is what a first visit does too.

### Changed — `percent` shows the same figure in a cell as on a card

A `percent` column cut to a whole number and wrote no space — "12%" under a card
saying "12,5 %". `Table::percent()` is the one rule for both, and the client
follows it: the digits as given, the locale's decimal sign, `'decimals'` when
asked, a space before the sign. Nothing, a placeholder without a digit ("–")
and a value that already ends in % come back as they are; text with a digit
that is not a number ("12,5") only gets the sign. **Visible** in every percent
column of every system: "12%" becomes "12 %", "12.5" is no longer cut to "12",
an empty cell no longer reads "0%" — it stays empty — and a placeholder ("–")
is no longer cut to "0%".

---
## [1.81.0] - 2026-09-20


Third round of the SSI Panel's self-maintenance loop. For the first time the work
was done in a separate working tree and entered the served source in one step —
in round two an unfinished state was live for an hour under the old cache key.

### Added — `Table::caption()`

What a table is a table of, for screen readers: `<caption class="gk-sr-only">`.
Two tables on one page were indistinguishable — "table, four columns", twice. The
caption travels in the data block, so the client-side rebuild writes it again.

### Added — `GK.modal.upgradeStatic()`: hand-written modals get what `GK.modal` has

`GK.modal.open()` builds `role="dialog"`, a name and a named close button. Pages
that write `<div class="gk-modal-overlay"><div class="gk-modal">` themselves got
none of it: the close button — usually a bare `&times;` — was read as
"multiplication sign", and nothing said a dialog had opened. The SSI Panel alone
has 87 such overlays and 56 unnamed close buttons. They are now given
`role="dialog"` and `aria-labelledby` from a heading inside them — any heading,
pages put theirs in headers of their own making — and the close button an
`aria-label`, on page load, after AJAX navigation and after a live reload. Only
what the page left out: a dialog with no heading stays the neutral `div` it was
rather than become a nameless dialog, and a close button that says something
keeps its words. No `aria-modal`: these dialogs do not take the focus. An overlay
a page builds in JavaScript is not seen — call `GK.modal.upgradeStatic(overlay)`
after inserting it.

### Fixed — a page reached through the sidebar arrived without its own stylesheet

AJAX navigation swaps `[data-gk-content]` and the title, nothing else. A page that
links a stylesheet of its own in `<head>` was unstyled when reached through the
sidebar and fine after a reload — the kind of fault nobody can reproduce on
request. The SSI Panel has 24 such pages. Stylesheets and `<style>` blocks from the
new page's head are now added and awaited (1.5 s at most) before the content is
swapped, and removed again on the next navigation. Nothing else in `<head>` is
touched: editors and maps inject styles of their own there. A stylesheet is
recognised by its path, not its cache key — a file saved while a tab is open does
not arrive as a second copy behind the stylesheets it is meant to precede.

### Fixed — a header stands where its column stands

Number and currency cells carry `.gk-td-num` and were meant to be right-aligned;
the rule lost to `.gk-table td { text-align: left }`. Fixing the cell alone would
have put every figure on the right and its heading on the left, because the header
never received an alignment at all — not from the format, not from `'align'`.
Both now follow the same rule in PHP (`Table::headerAlignClass`) and in the
client-side rebuild (`thAlignClass`), and a sortable header moves its label with
`justify-content`, since `text-align` does not reach an inline-flex button.
**Visible:** headings of right-aligned and centred columns move over their cells;
number and currency columns without an `'align'` are right-aligned.

### Fixed — a key figure and the column under it disagreed

`StatCards` cut a `number` off with `(int)` where `Table` rounds — 1999.9 read
"1.999" on the card and "2.000" below it — and knew no `'decimals'`. `number` now
rounds and takes `'decimals'`; whole numbers are unchanged.

A `percent` on a card was printed with a dot under every locale, although the
skill says all three formats follow it; it now uses the locale's decimal sign
(numeric values only) and takes `'decimals'`. **Still different:** a `percent`
column in `Table` cuts to a whole number and writes no space — "12%" under a card
saying "12,5 %". Aligning the two changes every percent column in every system
and is left for a release of its own.

### Tests

- `ci/browser.js` drives real component markup in headless Chromium: sixteen cases —
  which layer Escape closes, what a hand-written modal is given, that headers, caption
  and checkbox names survive a client-side sort, and that navigation brings styles along. Outside the suite, like
  `ci/parity.php`; the suite still has no dependencies.
- The check "the skill names every public method" accepted any `->name(` anywhere
  in the document, so `FilterChips::current()` shipped undocumented in 1.80.0
  behind `YearFilter`'s. A chained call now counts only under a heading that names
  the class. And "VERSION has an entry in the changelog" was satisfied by any older
  entry; the newest one has to match. Four methods were covered by a namesake alone and are documented:
  `Button::group()`, `Form::card()`, `PageSize::selectClass()`, `Table::rows()`.

---
## [1.80.3] - 2026-09-20

Second round of the SSI Panel's self-maintenance loop. Found by read-only
reviewers, each finding attacked by a second one, the finished diff reviewed by
three more before release. The Escape cases were run in a real browser.

### Fixed — Escape closed the modal underneath whatever it was meant for

The modal's handler asked one thing: "is a modal open?" Escape in a searchable
select closed the list **and** the modal with everything typed into it; Escape
on a `GK.confirm` over a modal answered "cancel" and took the modal along; so
did the lightbox, the receipt overlay, the header dropdown and the quick search.
The handler now steps aside when the key was already claimed
(`defaultPrevented`) or another layer lies on top.

- The header dropdown claims the key when it really closed a menu — its listener
  is older than the modal's, so a selector could never have seen it open.
- The AJAX select looked at Escape only after asking for its options, so a list
  showing "loading" or "no results" never reached that branch. Its open state is
  a flag now: the list is hidden by CSS until first use, `style.display` is `""`
  then, and a guard reading the inline style would have let a never-opened list
  swallow the first Escape.
- The multi select had no key handling at all.
- The quick search heard Escape only while its input had the focus.

### Fixed — an AJAX form said nothing when it failed

`{ok: false, error: '…'}` changed nothing on screen: the button came back, and
that was all. The skill has promised a toast for `{ok: true, message: '…'}`
since 1.10 and no code showed one; a non-JSON answer raised a native `alert()`.
All three are toasts now. `GK.modal.open` checks the status, so the body of a
500 no longer appears where the form belongs. No form on the SSI servers uses
`Form::ajax()` yet — a correction of behaviour and documentation, not a rescue.

### Fixed — state that only a colour announced

- The active chip of `FilterChips` and `YearFilter` carries `aria-current="true"`.
- `SortLink` says the direction in words (`.gk-sr-only`); the arrow is
  `aria-hidden` and was the only thing that did.
- Every row checkbox of a selectable table is named, and the client-side rebuild
  keeps the names and `scope="col"` — it used to drop them on the first sort.
- The filter field inside a searchable select and the hex field of a colour input
  had a placeholder for a name. An error on a range field is announced.
- New language keys: `table.select_row`, `table.sorted_asc`, `table.sorted_desc`,
  `js.select_all`, `js.select_row`.

### Changed — coloured key figures use the text variants of their colours

`StatCards` painted figures in the fill colours: success 2.54:1 and warning
2.15:1 on the white card, under 3:1 even for large type. They now use
`--gk-success-text`, `--gk-warning-text` and `--gk-danger-text` (5.0:1 and
better). **Visible:** green, amber and red figures are a shade darker. In dark
mode a later rule of equal specificity had turned every figure white, so a red
figure stopped being red exactly where it mattered; that rule is gone and the
figures are 9:1 and better on a dark card.

### Fixed — `GK.liveTable` inserted a whole page where a list belongs

A response that starts as a whole document is refused in both modes: in self
mode when the container is missing from it, in partial mode always — with a
console hint naming the endpoint, because it usually means the controller has
no partial branch. The old rows stay; the failure is reported like any other.

### Docs

`GK.confirm`, the outcomes of an AJAX form, self mode (`data-gk-live-self`) and
what a live table does when a request fails are in the skill now. Four German
leftovers in the English document are gone.

---
## [1.80.2] - 2026-09-20

Three reviewers were set on 1.80.1 the hour it shipped. What they found:

### Fixed — the dark values of 1.80.1 were right for the bare stylesheet only

`--gk-surface-variant` was written as `#21262d`. `themes.css` moves the dark
surfaces to `#1e293b`, and the literal then sat 1.04:1 on its own ground — the
hovered page number that 1.80.1 set out to fix stayed invisible under every
theme. Both new tokens are now derived from their roles in the dark block
(`--gk-surface-container-high`, `--gk-on-surface-variant`): 1.10:1 on the page,
1.30:1 on a card, and they follow whatever theme is loaded.

Also wrong in the 1.80.1 notes: "nothing moves in the light scheme" holds for
GridKit's own rules. A consumer that read the tokens with a fallback of its own
now gets GridKit's value instead.

### Fixed — `FilterChips::current()` left the row dark for a value no chip carries

`?status=nonsense`, or `?status=` on a row without an "All" chip: the controller
falls back to its default, the row lit nothing. The default now also applies when
the URL names a value that is not among the chips. The skill told every page to
read the parameter with `($_GET['status'] ?? '') !== ''` — on a page with
`current()` that filters by the default while the All chip is lit; the exception
is documented.

### Fixed — `GK.liveTable` still inserted a login page that arrived with status 200

A backend that answers an expired session with a redirect hands `fetch` a login
page and `ok: true`. A response that was redirected to another path is now
treated like a 401: the page reloads.

### Fixed — the scratch cleanup of the tests followed a link given with a trailing slash

`is_link('link/')` is false and `is_dir('link/')` is true. No caller passes such a
path today; the function promises "never", so it trims first.

---
## [1.80.1] - 2026-09-20

First round of the SSI Panel's self-maintenance loop: five read-only reviewers,
each finding attacked by a second one before anything was changed. Every fix
below came with the test that shows the fault.

### Fixed — 1.80.0 shipped with a red suite

The release touched `VERSION`, the changelog and one class, and not the header of
`GRIDKIT_SKILL.md` nor the generated `skill/`. `ci/build-skill.sh` now stamps the
header from `VERSION` itself, so it is the one step a release has to remember.
`FilterChips::current()` was missing from the document as well; the check that
should have caught it is satisfied by any `->current(` on the page, and
`YearFilter` has one.

### Fixed — `FilterChips::current()` made the "All" chip impossible to select

It treated an empty value like an absent parameter. The "All" chip links to
`?status=` on purpose, so on a page with a default the default stayed lit
whatever was clicked. It now applies only while the parameter is absent. The
method had shipped without a test.

### Fixed — one hand-typed link turned any page with a table into a server error

`?gk_sort[]=x` arrives as an array and was assigned to a string property:
`TypeError`, 500. The same for `gk_search` and for the parameter of a chip row;
`gk_filter_<column>` became the word "Array" and was bound into the query.
Parameters are now read as text or not at all.

### Fixed — `Table::footer()` wrote its cells unescaped

The documentation calls a footer cell "a plain string"; text and `align` both
went out raw. **Behaviour change:** markup or entities in a footer cell are now
shown as text. No caller on the SSI servers relied on it.

### Fixed — one invalid byte silenced a whole static table

`json_encode()` answers `false` for malformed UTF-8. The data block came out
empty, the client had no rows, and sort, search and paging went dead without a
word; the cell itself rendered empty. Both now substitute U+FFFD. The block is
also encoded with `JSON_HEX_TAG` — `<!--<script` inside a cell could otherwise
swallow the rest of the page.

### Fixed — `GK.liveTable` put error pages where the rows had been

It wrote whatever came back into the container: the 401 JSON of an expired
session as raw text, a 500 or a firewall's 403 page inside the list; a network
failure did nothing, and an older answer could overwrite a newer one.
`loadUrl()` and `reload()` now share one request path with the rules
`GK.table.reload` has had for a long time: status checked, one run counter per
container, the old rows stay, a toast and `gk-table-error` report the failure,
and a 401 reloads the page so the user reaches the login.

### Fixed — stylesheet

- `--gk-surface-variant` and `--gk-text-secondary` were read in a dozen places
  and defined in none, so the light fallback always won: a hovered page number
  in dark mode was 1.08:1. Both are defined now, with explicit dark values. The
  light values equal the old fallbacks — nothing moves in the light scheme.
- `.gk-text-right` was recommended by the skill and by `SortLink` and had never
  existed. Inside a table it, `.gk-text-center` and `.gk-actions-right` now also
  beat `.gk-table td { text-align: left }`.
- Two dark rules of the quick search waited for `data-theme`, which nothing
  sets. The marked part of a hit was 1.02:1 in dark mode.
- The current page number turned white-on-grey under the pointer.

### Fixed — every test run left directories behind in the temp folder

`scratch()` has promised "removed when the test ends" since 1.44.0 and removed
nothing: 1,380 leftovers and 340 MB on one server. They are removed now —
link-safe, because one of them holds a symlink to the live source tree and a
loop built on `is_dir()` would have walked into it.

---
## [1.80.0] - 2026-09-16

### Added — `FilterChips::current()`, for pages whose default filter is not "all"

A page that opens on a filter — "show the disturbed sites", "show open items" —
was reached without a query parameter, so `FilterChips` read an empty string out
of the URL and lit no chip at all. The list was filtered, nothing said which
filter it was, and the row of chips read as decoration rather than as the
control it is.

`current('gestoert')` names the filter the page falls back to. It only applies
when the URL carries no value of its own, so a click on any chip still wins.

```php
(new FilterChips('ww-filter', 'zeigen'))
    ->baseUrl('/monitoring/webseiten')
    ->current('gestoert')                    // what the page shows without a parameter
    ->chip('gestoert', 'Gestört', ['count' => 53])
    ->chip('alle', 'Alle', ['count' => 213])
    ->render();
```

---
## [1.79.1] - 2026-09-09

### Fixed — the site answered under two hostnames with identical pages

`www.gridkit.at` and `gridkit.at` both returned 200, byte for byte the same
document. To a search engine that is two sites carrying the same content, and
the two split whatever standing either would have had — on a domain Googlebot
had visited nine times in total. The canonical tag named the right one, but a
canonical is a hint a crawler weighs against other signals; a 301 is not.

`www` now redirects permanently. The rule names that one host on purpose:
`gridkit.ssi.at` serves the same directory, already redirects from its own
vhost, and a broader rule would have caught hosts nobody had checked.

The homepage also named itself without its trailing slash — `https://gridkit.at`
as the canonical of a page served at `https://gridkit.at/`. The same document
by every rule a browser follows, and one more thing to reconcile for a crawler
that has nothing else to go on yet.

---
## [1.79.0] - 2026-09-09

### Fixed — the landing page was unreadable to anyone who pressed its own dark button

The dark palette sat behind `@media (prefers-color-scheme: dark)` and nothing
else — that is, behind the visitor's **system** setting. The page also carries
a moon button, and that button sets `data-gk-mode="dark"` on the body, which no
media query can see. Press it on a machine set to light and the ground went
dark while every text colour stayed where it was:

| | before | after |
|---|---|---|
| hero subline | **1.93:1** | 6.96:1 |
| section subtitles | **2.36:1** | 5.71:1 |
| body text | **1.93:1** | 6.96:1 |

4.5:1 is the requirement. A page nobody can read, reached by pressing the
button the page offers.

Worth naming why it happened here and nowhere else: `css/gridkit.css` and
`css/themes.css` use `data-gk-mode` for all 118 of their dark rules, and so
does the demo. The landing page — the one advertising that library — was the
only file that chose the other mechanism, while its own toggle spoke the
library's language.

There are three states, not two: system-dark with no choice made, an explicit
dark choice, and an explicit light choice that must win even on a dark machine.
All three are now covered and all three were measured. The palette is written
once in PHP and applied to both selectors, so the pair cannot drift.

Found while measuring the contrast of a section added minutes earlier — the
first reading looked like a broken measurement rather than a broken page.

### Added — a FAQ, written in the words people actually search for

"GridKit" is a contested name: a power-grid extraction toolkit, a WordPress
gallery plugin and a GitHub organisation all have it, and all of them outrank a
three-week-old domain. A search for the name will not arrive here. A search for
the task might — "php admin dashboard without a javascript framework", "php
sortable table with pagination", "php admin panel without npm" — and the page
had been describing itself in its own vocabulary: agent-ready, zero-dependency,
Material Design 3. None of which anybody types.

Ten questions with honest answers, and the title and description rewritten to
lead with what the thing does rather than what it is called.

The questions live in one PHP array that renders both the visible section and
the `FAQPage` structured data. Two copies is the usual arrangement and the
usual mistake: search engines compare the markup against the text and distrust
the pair when they drift, which is what a hand-kept second copy eventually
does.

### Note — none of this is why it is not indexed

Googlebot has made **nine** requests to gridkit.at, across every log this
server still holds. Applebot made 246. The site is not indexed because almost
nothing links to it, and no amount of markup fixes that.

---
## [1.78.0] - 2026-09-09

### Fixed — a rich-text field gave up on an editor that was still loading

The generated bootstrap set its one-shot flag **before** checking whether
CKEditor was there:

```js
if (_init) return; _init = true;
var CE = (window.CKEDITOR || {}).ClassicEditor; if (!CE) return;
```

A field that came into view while the bundle was still on its way therefore
marked itself done and returned, and could never run again. The textarea stayed
a textarea, and nothing was logged anywhere. It never showed up in practice
because the one page that uses this loaded CKEditor from a blocking `<script>`
in the head — which is exactly the cost the fix makes avoidable. The flag is
set only once the editor is really there, and a missing editor is now waited
for within a bound: every 150 ms for twenty seconds, then it stops asking and
leaves a usable textarea behind.

A bounded wait rather than an event, because there is no event to agree on:
CKEditor is supplied by the page, in whatever way that page chooses.

### Fixed — the demo blocked first paint on 1.66 MB of somebody else's editor

1446 KB of script and 217 KB of stylesheet, in the head, on every visit — for
one card, in one of eleven sections, ten of which are hidden when the page
opens. On the page whose job is to show what a framework advertising *zero
dependencies* looks like.

The paragraph beside that card has always said "Initialization via
IntersectionObserver". That was true of the initialisation and never of the
download. Now both wait for a rich-text field to actually come into view.

Verified in a browser: nothing CKEditor-shaped is requested on load, and
`window.CKEDITOR` is `undefined`; showing the section fetches both files
(408 KB over the wire) and the editor builds itself.

### Note — the demo's hidden sections are deliberate

Ten of eleven sections are `display: none` at load, holding 195 KB of the
page's 280 KB. That is not the same fault as the landing page's: those sections
**are** the thing being demonstrated, and switching between them without a
round trip is the point. 49 KB over the wire is a fair price for that, and it
was left alone.

---
## [1.77.0] - 2026-09-09

### Fixed — the landing page carried the whole manual, twice, on every visit

`GRIDKIT_SKILL.md` was delivered inline in two forms at once: 111 KB of
rendered HTML inside a collapsed toggle nobody had opened, and another 68 KB of
the same text in a hidden `<textarea>` so that one copy button had something to
read. Together that was **71 % of a 254 KB page** — a 67 KB manual sent ahead
of the hero to every visitor, almost none of whom will ask for it.

It cost more than bytes. The document brought 44 of its own headings into the
landing page's outline, which is what a screen reader walks and what a search
engine indexes: the page read as the manual with a landing page attached, and
it competed with `/skill`, which exists to serve exactly that document.

Both are fetched on demand now — the rendered form from a new `?skill-html=1`
endpoint, the raw text from `/skill/`, which already had a route. Nothing about
the page changes for someone who does press the button, except that the
document arrives when they ask instead of before.

| | before | after |
|---|---|---|
| HTML | 254 KB | **74 KB** |
| transferred (gzip) | 56 KB | **17 KB** |
| headings in the outline | 40 | **27**, all its own |

The toggle also gained `aria-expanded` and `aria-controls`. It is a disclosure
button, and it had been announcing nothing — the same gap this library spent
1.68.0 to 1.72.0 removing from its own components, sitting on its own front
page the whole time.

Verified in a browser: the preview ships empty, arrives on the first open,
is not fetched again on the second, and the copy button puts the same 69,913
characters on the clipboard that the hidden field used to hold.

---
## [1.76.0] - 2026-09-09

### Changed — the select widget has one renderer

`Form`'s `select` field built the same markup `Select::searchable()` builds, a
second time, in a second file. The two drifted twice and each time it was a
screen reader that found it: 1.70.0 fixed options with no `role="option"` on
one side and a combobox with no `aria-controls` on the other. Whatever a form
field needs on top — the visible label, the error, the disabled state, the
filter box appearing only once the list is long — is an option on the shared
function now rather than a reason to write the markup again.

The output is unchanged. That was established before the change, not asserted
after it: twelve configurations were rendered and kept — with and without a
value, few and many options, forced search, required, disabled, with an error,
with an overridden name, without a label, with a custom placeholder, with a
payload in the option text, with `tags[]` as the field name — and compared
afterwards with attribute order normalised. All twelve identical, and the
comparator was shown to notice a single flipped `aria-selected` before that
result was believed.

### Fixed — the AJAX select had every gap the static one had, and one of its own

It is deliberately **not** folded into the shared function: its search box sits
inside the display rather than in the dropdown, it has a clear button and a
loading row, and it has no options until something is typed. Sharing one
function would have meant a switch at every second line. Sharing the fixes is
the part that matters:

- The combobox promised a listbox and never named it; the container it meant
  carried no role; the results the browser builds were plain `<div>`s in it.
- The clear button was a bare `&times;` with no name — announced as "times",
  the third button in this library with that exact fault.
- `aria-expanded` was written once by PHP and never touched again while **six**
  places changed the dropdown's visibility, so it said "closed" with a list of
  results on screen. One `setOpen()` now, and the test counts the direct
  assignments to make sure it stays one.
- Arrow keys moved a grey bar and nothing else. The active result is announced
  with `aria-activedescendant`, the way the global search has always done it.

### Fixed — a language file could define the same key twice and say nothing

PHP keeps the last value and issues no warning, so a second
`'form.clear' => …` further down does not fail, does not warn, and does not
apply: the text that wins is the one already there. This happened while writing
this very release — the key existed, the new line was silently discarded, and
the only reason it surfaced is that the suite's assertion count failed to move
when it should have. Both catalogues are now read as text and checked for
repeats.

### Fixed — the test suite depended on where the repository was checked out

`auth/the asset path is worked out, not guessed` set the document root to
`sys_get_temp_dir()` and assumed GridKit could not be underneath it. Check the
repository out under `/tmp` — a throwaway `git worktree` is enough — and the
fallback branch never runs, the derived path comes back, and the suite fails on
a machine where nothing is wrong. The document root is now *chosen* from
candidates that provably do not contain the checkout, and the test asserts one
was found. Verified by running the suite from a `/tmp` worktree, which is what
exposed it.

---
## [1.75.2] - 2026-09-09

### Fixed — six stylesheet rules that could never apply

Two of them stated a value the file then contradicted three lines later:

- `.gk-modal-large` was declared twice, `max-width: 860px` and then `900px`.
- `.gk-field` was declared twice, `margin-bottom: 20px` and then `16px`.

Both come from changing a value by adding a rule instead of editing the one
already there, which leaves the stylesheet stating two answers where only the
second is true. Anyone reading the first one is reading a fact about this
library that is not so.

The other four were redundant rather than contradictory: a second
`margin: 0 auto` on the collapsed sidebar's collapse button that the rule three
lines above already set, an exact duplicate of `display: none` for the mobile
close button, and five declarations on the collapsed sidebar label — opacity,
width, overflow, padding, margin — sitting under a later rule that sets
`display: none !important` on the same selector. None of the five could apply,
and reading them suggested the label folds away when it simply stops existing.

Rendering is unchanged, and that was measured rather than assumed: computed
styles for every affected element were read in a browser before and after, in
both the collapsed and the expanded state. The two values that do differ —
`opacity` and `width` on the collapsed label — are on an element that generates
no box.

The file got very slightly **larger**. The removed rules were shorter than the
comments explaining why they are gone.

### Added — a check for rules that cannot apply

For any selector that opens a top-level rule more than once, a later rule may
not set every property an earlier one sets. Duplicates as such are fine and
there are a dozen deliberate ones here, each adding something the first did not
and each carrying a comment; what this forbids is the pointless kind, so it
needs no list of exceptions to keep current.

It reads only single-selector rules opened at column 0 — an indented rule, a
comma-separated list or one inside a media query is invisible to it. That is a
real limit, stated in the test, and it is enough for the shape that actually
occurred. Verified from both sides: putting the dead `.gk-field` rule back
fails it, and adding a duplicate that contributes a new property does not.

---
## [1.75.1] - 2026-09-09

### Fixed — two more places in the global search that parsed what they were given

A sweep for the fault behind 1.75.0 — a value reaching `innerHTML` — found two
more in `GK.search`:

- The overlay's placeholder went straight into an attribute. One double quote
  in it ended the attribute and everything after became markup. The module has
  had its own `esc()` all along, four methods further down.
- `showNotice()` pasted its argument as HTML. That was safe only because every
  caller happened to pass a config string — until someone fills `error` from
  what the search endpoint replied. One caller genuinely wanted an element, the
  loading spinner, so it has its own `showSpinner()` rather than a shared door
  left open on its behalf.

The same sweep confirmed the modal title is already set with `textContent`, and
that the remaining two `innerHTML` blocks interpolate only a counter and the
translation catalogue.

### Added — escaping tests for six components that had none

`tests/escaping.test.php` covered tables, forms, headers, stat cards, buttons
and the pager URL. It did not cover Sidebar, FilterChips, YearFilter,
ActionGroup, Icon or the pager's entry label — and navigation is exactly where
a label arrives straight out of a database row.

All of them were probed by hand with both payloads first, and every one held.
These assertions fix nothing; they stop it from being undone. Two were checked
by removing the escaping and watching them fail.

Worth recording: the first probe reported everything tight while injecting
empty strings, because the payload variables were out of scope. PHP's undefined
variable warning is the only reason that was noticed.

---
## [1.75.0] - 2026-09-09

### Fixed — a toast and a confirmation put their message through the HTML parser

`GK.toast.error(err.message)` is the obvious way to use a toast, and the
message was pasted into `innerHTML` — so whatever the server said was handed to
the HTML parser and run. The same in `GK.confirm(...)`, which is exactly where
a record's name ends up: `GK.confirm('Delete ' + row.name + '?')`.

Both set their text as text now. Nothing in the documentation has ever offered
markup here — every example in it is a sentence — and nothing in this
repository passed any. **If you were passing HTML on purpose, it will now show
as the characters you typed.**

Checked in a browser with `<img src=x onerror=…>` as the message: it appears as
that literal string and the handler does not run.

### Fixed — the confirmation dialog leaked a listener on every call

The Escape handler was added to `document` on every `GK.confirm()` and removed
only inside the Escape branch. Answer with a button — which is the usual answer
— and it stayed registered, holding its closure and the detached overlay, one
more on every confirmation for as long as the page lived. There is one exit
now and all four ways out go through it.

### Fixed — a toast was announced to nobody

No live region at all, so the library's entire feedback channel — "Saved.",
"Error while saving." — reached a screen reader as silence. The toast area is a
polite status region now. Its icon was not `aria-hidden`, so the ligature
"check_circle" was read out as a word, and its close button had no name: a
button announced as "times".

### Fixed — the confirmation dialog was a box on top of the page

No role, no `aria-modal`, no accessible name. Focus was placed on OK and then
Tab walked straight out into the page behind it, and closing gave focus back to
nobody. It is a named dialog now, Tab stays inside it, and focus returns to
whatever opened it — through the same helpers the modal and the lightbox use.

Fixing it moved `_gkRestoreFocus` from two call sites to three, which failed
the test that counted them. That was the test being right, not wrong: the count
became a floor with the reason written next to it.

---
## [1.74.0] - 2026-09-09

### Added — the CSS that existed but could not be found

An audit of every `.gk-*` rule against everything that could reference one —
the components, the browser code, the demo and the documentation — turned up a
body of working, styled classes that GridKit itself never writes and no
document mentions. They are not dead: they are for the page author, and the
page author had no way to learn they exist.

Newly written down: the sixteenths width scale `gk-w-{1..16}` (only `full` and
`auto` had been listed), `gk-spacer` and its sizes, `gk-grid` with `gk-grid-2`
/ `gk-grid-4`, the card variants (`elevated`, `flat`, `image`, `title`,
`actions`), `gk-field-hint` and `gk-field-has-error`, the checkbox and radio
labels, `gk-form-page` / `gk-form-wide`, the toolbar row and its two halves,
`gk-accordion-flush`, the rich-text field's own parts, `gk-skeleton`, and
`gk-not-clickable` / `gk-disabled` / `gk-nowrap`.

Nothing was deleted. These ship in the stylesheet and somebody may already be
using them; removing a class from a released stylesheet breaks a page that
looked fine yesterday.

### Added — the numbers in the utility table are checked against the stylesheet

The table has always stated scales — "4/6/8/12/16/20 px" — and nothing had ever
compared them with the CSS they describe. A scale is exactly what gets nudged
by someone who never opens the documentation, and a table of wrong pixel values
is worse than no table, because it is believed.

The test reads the claims out of the document rather than restating them, so it
cannot drift from what it checks, and it asserts it found at least three scales
— a pattern that stops matching would otherwise check nothing and report green.
Both directions were verified by breaking them on purpose: a changed rule, and
a changed number in the table.

It found a wrong number immediately, in a row added by hand minutes earlier:
`gk-spacer-md` documented as 16 px where the rule says 20.

---
## [1.73.1] - 2026-09-09

### Fixed — the website's Apache configuration shipped inside the package

`.htaccess` begins with the words "GridKit — the public site", and that is what
it is: a cache policy for the whole directory it sits in, a rewrite for
`/skill`, and `RedirectMatch 403` for `/tests`, `/ci` and `/.design`. Those
paths belong to whoever's site is serving the file. Delivered inside a Composer
package, into a directory Apache reads, they become that project's rules —
GridKit quietly answering 403 for a `/tests` route that is not GridKit's.

`index.php`, `sitemap.xml`, `robots.txt` and `favicon.ico` had all been given
`export-ignore` for exactly this reason. `.htaccess` and `llms.txt` were
missed. They are excluded now; both stay in the repository, so the live site
and a git clone are unchanged.

The package is 40 files. It was 42.

### Added — a check that the package stays the library

Every tracked top-level entry must either be on a short list of what the
library *is*, or carry `export-ignore`. A new file at the repo root therefore
cannot reach `vendor/` without someone naming it deliberately.

It reads `.gitattributes` rather than running `git archive`, because the answer
is a decision recorded in a file and reading a file needs no `tar`. Worth
noting for anyone tempted to simplify it: `git check-attr` is **not** a
substitute. It matches path patterns, while `archive` prunes an entire
directory when the directory itself is ignored — ask `check-attr` about
`tests/foo.php` and it says "unspecified" for a file that has never shipped.
That mistake was made while writing this and caught by comparing its answer
against a real archive.

The test also asserts it parsed more than five ignore rules, because a broken
pattern would otherwise find nothing and report all clear. Verified by breaking
the pattern on purpose.

---
## [1.73.0] - 2026-09-08

### Fixed — the gallery could stay empty

A tile marked `data-lazy` carries its picture in `data-src` and gets a real
`src` only when it nears the viewport, so the observer is not an optimisation
the page can do without: a tile nobody observes never loads its image at all.
The tiles were collected at parse time, one block below the accordion — the
same fault as 1.71.0 but with a visible consequence rather than a quiet one.
With the script in `<head>` the gallery rendered empty frames. It runs when the
document is ready now, and again from `GK.init()` for galleries that arrive
later.

### Fixed — the rich tooltip existed for pointers and for nobody else

`data-gk-tooltip-rich` bound `mouseenter` and `mouseleave` and nothing else: no
keyboard could ever see it, and nothing joined the trigger to its text, so a
screen reader was never told a description was there. It opens on focus as well
as hover, the trigger is joined to the popup with `aria-describedby`, the
trigger becomes focusable when it is not already, and **Escape** dismisses it —
content shown on hover or focus has to be dismissible without moving the
pointer away from it.

### Added — Tabs, Accordion, Gallery/Lightbox and Tooltips are documented

None of them appeared anywhere in `GRIDKIT_SKILL.md`. Not in the component
table, not in the CSS reference, not in the JavaScript API. Tabs and the rich
tooltip were shipped, demonstrated on the landing page and announced in the
changelog, while the one file an agent reads to learn this library did not know
they existed — so an agent asked for tabs would invent markup nothing here
binds to.

Each section states the markup contract, including the parts that bite: the
`data-tab` values must pair up, `.gk-accordion-content` clips at 500 px, and a
`data-lazy` tile puts its picture in `data-src` rather than `src`.

### Added — a test that finds the next undocumented widget by itself

Most `data-gk-*` hooks are written by GridKit's own PHP; nobody hand-types
`data-gk-select-search`. The interesting ones are those **no** component
writes, because then the page author is the only possible source — and if the
skill document does not show them, the feature might as well not exist. The
test derives that set rather than keeping a list somebody must remember to
extend, so a widget added tomorrow with undocumented markup fails on its own.

When it was written, three hooks qualified and none was documented.

It also asserts that it found at least three, because a scan that silently
matches nothing reports green: verified by breaking the pattern on purpose and
watching the count assertion catch it.

---
## [1.72.0] - 2026-09-08

### Fixed — the lightbox could not be opened by keyboard at all

A gallery tile is a `<div>`: no tab stop, nothing to activate. Only a mouse
ever reached the lightbox. The markup is written by the page author, so the
tile is made operable by the library rather than demanded of them — `role`, a
tab stop, and a name taken from the caption it already carries, with Enter and
Space opening it the way a button does.

### Fixed — the lightbox was a dialog that was not built as one

- No `role`, no `aria-modal`: a div lying on the page, with everything behind
  it still readable.
- No focus handling whatsoever. Opening a picture left focus on the page
  underneath, so Tab walked through controls hidden behind the image —
  invisible and unclickable, with no way back but Escape.
- The three buttons had no accessible name, and their icon spans were not
  `aria-hidden`, so what was announced was the ligature text: "close",
  "chevron_left", "chevron_right".
- The `<img>` had no `alt`. The one thing the dialog exists to show was the one
  thing not described. `showLb()` and `navigate()` each carried their own copy
  of the four lines that put a picture on screen, which is how it came to be
  missing from both; there is one `render()` now.

Caption and counter are `aria-live="polite"`, so paging through announces where
you are.

### Changed — one focus trap in the library, not one per overlay

`_focusable`, `_trap` and `_focusInto` were methods on `GK.modal`, written when
the modal was the only overlay here. It is not. Rather than a second copy for
the lightbox — which would have drifted, the failure this project keeps finding
in itself — they are module-level functions and `GK.modal` delegates to them.

Returning focus to whatever opened an overlay was written out twice as well,
and the two copies **already differed**: `opener.isConnected` in the modal,
`document.contains(opener)` in the new lightbox code. One `_gkRestoreFocus()`
now, called by both.

That duplication also produced a test that could not fail: the first version
asserted `opener.focus({ preventScroll: true })`, a string the modal's copy
contained too, so it would have passed with the lightbox's focus handling
removed entirely. Caught by reverting it and watching the suite stay green. The
assertion is now named for the lightbox alone, and a second one counts the
shared helper's call sites.

Verified in a browser: a tile takes focus, Enter opens the dialog, focus lands
inside it, Tab wraps at the last control instead of leaving, Escape closes and
focus returns to the tile — and the modal still works through the same helpers.

---
## [1.71.0] - 2026-09-08

### Fixed — a closed accordion panel was closed for the eye only

`max-height: 0; overflow: hidden` does exactly one thing: it stops you seeing
the panel. The text stayed in the accessibility tree, so a screen reader read
every panel whether open or shut and the accordion did nothing whatsoever for
it. Worse, anything focusable inside a closed panel stayed in the tab order —
Tab moved focus into a zero-height overflow box, where the focus ring cannot be
seen and the page appears to have swallowed the keyboard.

`visibility` now travels with the max-height, which takes closed content out of
both the tree and the tab order. The movement is unchanged: closing waits out
the max-height transition before going hidden, opening switches to visible at
once, so nothing blinks.

### Fixed — the trigger said neither whether it was open nor what it opens

The fourth disclosure widget in this library whose state was a CSS class and
nothing else, after the sidebar groups (1.68.0), the theme picker (1.68.1) and
both tab systems (1.69.0). The trigger now carries `aria-expanded` and
`aria-controls`, and the panel is a `region` named by its trigger.

One function sets open/closed, which matters here more than elsewhere: in
single-open mode the items that close are closed as a *side effect* of opening
another. That second path is exactly the one a fix written at the click site
forgets, and the test names it.

### Fixed — the accordion bound at parse time and could bind nothing

It ran one line below the block that defers `GK.init()` to `DOMContentLoaded`.
With the script in `<head>` it queried a document that had no accordions in it
yet, bound nothing, and said nothing. Markup arriving later — a live reload, a
modal — got nothing either. It is `GK.accordion.init()` now, started through
the library's own `_gkReady` helper and called again from `GK.init()`, and it
is idempotent so both is fine.

Writing that guard out by hand instead of using `_gkReady` was caught by an
existing test that exists for this precise mistake. Verified in a browser
afterwards: a link placed in a closed panel cannot be focused, becomes
focusable when the panel opens, and the panel closed as a side effect in
single-open mode reports `aria-expanded="false"`.

---
## [1.70.0] - 2026-09-08

### Fixed — the select was announced as an empty listbox

The searchable select is where a form spends most of its time, and it did not
survive being read aloud.

`Select::searchable()` put `role="listbox"` on the options container and left
the options themselves as plain `<div>`s. That is worse than carrying no role
at all: a listbox IS announced, and one with no options inside is announced as
having none — so the entire list vanished. Which option was current lived in
the `selected` class, a tick and a tint.

`Form`'s select — the path every doc and the demo actually use — had the
mirror-image fault. Its combobox promised `aria-haspopup="listbox"` and never
said which element that listbox was, and the container it would have pointed at
carried no role either. One widget, two renderers, drifted apart in opposite
directions; the same divergence the parity harness exists to catch, in a place
that harness does not reach.

Both render the full pairing now: `role="listbox"` with an id, `aria-controls`
on the combobox naming it, and `role="option"` with `aria-selected` on every
entry. The multi-select adds `aria-multiselectable="true"`, which is simply
true of it.

`gridkit.js` moves `aria-selected` with the class in both variants. Without
that half, choosing an option would have left the listbox reporting whatever
the page loaded with — the same lie 1.68.1 removed from the theme picker,
which is three components in a row now where state was a class and nothing
else.

### Fixed — every chip in a multi-select had the same nameless remove button

`<button class="gk-chip-remove">&times;</button>` — no accessible name at all.
A screen reader met a run of identical unlabelled buttons with no way to tell
which chip any of them would drop. Each now says what it removes, from a new
`select.remove` string ("Remove {label}" / "{label} entfernen"). A locale that
does not ship the key falls back to English, as every other string does.

Verified in a browser on the live demo: the combobox resolves through
`aria-controls` to a real listbox, every entry carries the option role,
choosing one moves `aria-selected` with zero class/attribute mismatches, and
the chips read "Remove Webdesign" and "Remove SEO".

---
## [1.69.0] - 2026-09-08

### Fixed — tabs were a row of buttons above unrelated text

Two tab systems ship in `gridkit.js`, and neither said anything to assistive
technology:

- `GK.tabs` — `[data-gk-tabs]` with `[data-gk-tabpanel]` children, which builds
  its own nav from the panels.
- The authored-markup one the demo teaches:
  `.gk-tabs > .gk-tab-nav > .gk-tab-btn[data-tab]` with matching
  `.gk-tab-panel[data-tab]`.

Both produced buttons whose only mark of being current was `gk-tab-active` or
`gk-active` — a colour and an underline. There was no `tablist`, no `tab` role,
no `aria-selected`, nothing tying a button to the panel it reveals, and the
arrow keys, which is how a tablist is actually operated, did nothing. Six tabs
also cost six presses to walk past, because every button was its own tab stop.

Both now build the full structure: `role="tablist"` on the nav, `role="tab"`
with `aria-selected` and `aria-controls` on each button, `role="tabpanel"` with
`aria-labelledby` on each panel, and a roving tabindex so the set is one tab
stop with Left/Right/Home/End moving inside it.

The generated variant builds the right markup directly. The authored variant
cannot — the page author writes that HTML — so `GK.tabsMarkup.init()` puts the
roles on afterwards, conservatively: it supplies an id only where there is
none, and the click handler updates `aria-selected` only on buttons that
actually carry `role="tab"`. Markup inserted later and never passed through
`init()` therefore behaves exactly as it did before rather than ending up with
half a set of attributes.

Checked in a browser on the live demo, which uses the authored variant: roles
and links present, click and arrow keys moving selection, focus and panels
together, and Right from the last tab wrapping to the first. The first attempt
measured nothing, because that demo section is `display:none` until its
navigation entry is chosen and a hidden element cannot take focus.

The test counts these attributes and expects **2** of each — one per system.
Adding the roles to one and forgetting the other fails it; that was verified by
stripping the authored variant alone.

---
## [1.68.1] - 2026-09-08

### Fixed — the theme picker pointed at the wrong colour

`Theme::switcher()` renders `aria-pressed="true"` on the swatch that is on.
Nothing in the browser ever moved it again. `GK.theme.set()` (the click) and
`GK.theme.restore()` (page load) each carried their own copy of the same loop,
and both copies toggled the `gk-theme-active` class alone — so from the first
colour change onwards the picker announced a different colour than the one in
effect, and for anyone with a stored preference it was already wrong on every
page load, before they had touched anything.

Two copies of one decision is how the attribute came to be missing from both.
There is one copy now, `_mark()`, and the test asserts there is only one.

### Fixed — the light/dark button announced no state at all

Both glyphs sit in `gk-mode-toggle` at once and CSS shows one of them; both are
`aria-hidden`, correctly, or the pair reads as "light_modedark_mode". That left
the single control that switches mode with a name and no state. Dark is now its
pressed state, rendered by PHP and kept in step by `_markMode()`.

Verified in a browser against the live demo, not only in the source: clicking a
swatch moves class and attribute together, and a group closed before a reload
comes back as `aria-expanded="false"` — the 1.68.0 restore path, confirmed end
to end.

---
## [1.68.0] - 2026-09-08

### Fixed — the standalone pager never said which page you were on

`Pagination.php` opens with the claim that it is "ONE uniform pager for the
whole system", replacing every bespoke variant. Every *other* pager kept that
promise: `Table.php` marks the active page with `aria-current="page"`, so does
the client-side pager in `gridkit.js`, and so do both levels of `Sidebar`. This
file marked it with the `gk-pg-active` class and nothing else — a colour. A
screen reader read the same row of digits on page 1 as on page 7, in the one
component written specifically so that no pager would behave differently from
another.

### Fixed — a collapsible sidebar group announced no state

`gk-sidebar-group-toggle` is a real `<button>` that opens and closes a submenu,
and its state existed only as a CSS class — on the server and in the browser
alike. It reached the eye as a rotated chevron and reached assistive technology
not at all: the button never said whether it was open, and never named what it
controls. It now carries `aria-expanded` and `aria-controls`, and `gridkit.js`
moves the attribute with the class.

That second half is the part worth stating. The browser remembers which groups
a visitor closed, and the restore path ran before this change on the class
alone. Had only the server been fixed, a remembered-closed group would have
rendered a button claiming `aria-expanded="true"` above a shut submenu — an
attribute that lies is worse than one that is missing, because nothing about
the page looks wrong. The test asserts both branches for exactly that reason,
and was checked by reverting the restore path alone.

### Fixed — the test suite printed a deprecation notice on every run

`ReflectionMethod::setAccessible()` has done nothing since PHP 8.1 and is
deprecated in 8.5. `tests/ajax.test.php` had already dropped it and left a note
saying why; the identical call in `tests/fields.test.php` was missed, so every
run of the suite emitted two notices that had nothing to do with the code under
test.

---
## [1.67.3] - 2026-09-02

### Fixed — the picture toolbar was empty, so nothing could be aligned

A `resizeImage:<n>` entry used as a *toolbar button* must carry an `icon`.
Without one CKEditor throws `imageresizebuttons-missing-icon` while building
the balloon toolbar — and that exception takes the **whole** toolbar with it,
including the four alignment buttons next to it. Selecting a picture showed an
empty balloon, so text wrap was unreachable even though the underlying code
was correct (measured in a browser, 02.09.).

The sizes now sit in the `resizeImage` **dropdown**, which needs no icons, and
the list gained 25 % and 33 % — a picture has to be narrow enough for text to
fit beside it.

### Fixed — aligning a picture discarded the size just chosen

The wrap buttons read the width only from their own styles, never from
CKEditor's `resizedWidth`. Picking 50 % and then „left" fell back to no width.
Both sources are read now.

### Changed — „left" and „right" set a starting width

A picture at natural size fills the line; floating it changes nothing visible.
The two wrap buttons now set 33 % when no width has been chosen yet, so one
click does what the label promises. An existing width is kept.

---
## [1.67.2] - 2026-09-02

### Fixed — pictures already in the text stayed blocks

1.67.1 forced *new* pictures inline. Anything already saved as
`<figure class="image">` stayed a block on load, so the alignment buttons still
had nothing to act on — which is what the reporter kept seeing.

Existing content is now unwrapped before it reaches the editor: the figure's
width moves onto the `<img>` (on the figure it does nothing in a mail, and the
picture would arrive full size), `width`/`height`/`aspect-ratio` are dropped
because they fight a percentage width, and a `<figcaption>` becomes the
paragraph after the picture.

Only runs when `upload` is set — a field without pictures is untouched.

---
## [1.67.1] - 2026-09-02

### Fixed — the alignment buttons did nothing on an uploaded picture

1.67.0 added them, and they worked on a picture that was already in the text —
but not on one just uploaded. Uploading put the picture in as a **block**, and
the buttons work on the `<img>`, so they had nothing to act on. The balloon
toolbar did not even appear.

`Image` pulls in `ImageBlock` whether you list it or not — `Image.requires`
is `[ImageBlock, ImageInline]`, so leaving it out is not an option. Dropped
into an empty paragraph, the block wins.

Fixed with `image.insert.type: 'inline'`: a picture now always lands inside the
paragraph. That is also what the whole feature is built around — a block
picture is a `<figure class="image">`, which is exactly the markup that does
nothing in an e-mail.

Measured end to end: insert → `<p><img src="…"></p>`, then *right* →
`<p><img style="float:right;margin:0 0 8px 16px" align="right" src="…"></p>`.

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
