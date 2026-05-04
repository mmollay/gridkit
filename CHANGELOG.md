# Changelog - GridKit

Alle Änderungen an diesem Projekt werden hier dokumentiert.
Format basierend auf [Keep a Changelog](https://keepachangelog.com/de/1.0.0/).

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
