# Changelog - GridKit

Alle Änderungen an diesem Projekt werden hier dokumentiert.
Format basierend auf [Keep a Changelog](https://keepachangelog.com/de/1.0.0/).

---
## [1.4.11] - 2026-04-10 — Form Compact Density

### Added
- **Form Compact Mode**: Neue CSS-Klasse `.gk-form-compact` für kompaktere Formulare. Reduziert Input-Höhe (44px→34px), Padding, Margins und Font-Sizes. Anwendung: `<form class="gk-form-compact">` oder als Wrapper-Klasse auf beliebigem Container.

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
