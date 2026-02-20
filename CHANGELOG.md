# Changelog - GridKit

Alle Änderungen an diesem Projekt werden hier dokumentiert.
Format basierend auf [Keep a Changelog](https://keepachangelog.com/de/1.0.0/).

## [1.9.3] - 2026-02-20

### Fixed
- **Sidebar Hintergrund** – direktes CSS statt CSS Variable (zuverlässiger)
  - `[data-gk-theme="rose"] .gk-sidebar { background: #fdf2f8; }` etc. mit höherer Spezifität
  - Light Mode: definitiv helle Farben unabhängig von var()-Auflösung
  - Dark Mode: direktes dunkles Hintergrundsetzen per Selektor

## [1.9.2] - 2026-02-20

### Changed
- **Sidebar Light Mode – komplettes Redesign**
  - Sidebar ist jetzt hell und harmonisch statt schwerer dunkler Block
  - Theme-Charakter kommt über Akzent/Active-State, nicht über dunkle Fläche
  - Light Mode Sidebar Bgs: indigo=`#eef2ff` · ocean=`#f0f9ff` · forest=`#f0fdf4` · rose=`#fdf2f8` · amber=`#fffbeb` · slate=`#f8fafc`
  - Text jetzt dunkel (`#374151`), Gruppen-Labels mittelgrau, Icons gedämmtes Grau
  - Active Item: `primary-container` Hintergrund + primäre Akzentfarbe (Left-Border + Icon)
  - Dark Mode bleibt unverändert dunkel (eigene Overrides)

## [1.9.1] - 2026-02-20

### Fixed
- **Dark Mode komplett** – alle Komponenten bekommen jetzt korrekte dunkle Farben
  - `--gk-bg`, `--gk-text`, `--gk-border` etc. im Dark Mode Block explizit hardcoded (var()-Kette war unzuverlässig)
  - StatCards: dunkler Hintergrund + korrekte Text-/Label-Farben
  - Chips (FilterChips, YearFilter): dunkler Hintergrund, Active State mit Primary Container
  - Cards: dunkler Hintergrund
  - Pagination Buttons: dunkler Hintergrund, Active in Primary
  - Header Search, User-Menü, Dropdown: explizit dunkel
  - Confirm Footer: explizit dunkel

## [1.9.0] - 2026-02-20

### Fixed
- **Sidebar Dark Mode** – pro Theme eigene dunkle Sidebar-Bg (tiefer als Content = niedrigere Elevation)
  - indigo=`#0d1225` · ocean=`#071a2d` · forest=`#071a12` · rose=`#1a0818` · amber=`#1a0d06` · slate=`#0d1424`
  - Dark Mode Accent pro Theme korrekt gesetzt (helle Variante der Theme-Farbe)
  - Default Dark Mode Sidebar vereinheitlicht (gleiche Variablen wie Light Mode)
  - Sidebar bekommt subtile rechte Border (`--gk-sidebar-border`) für klare Trennung

## [1.8.0] - 2026-02-20

### Changed
- **Sidebar Redesign** – Klares Kontrast-System für alle Themes
  - Neu: `--gk-sidebar-accent` pro Theme (leuchtende Akzentfarbe auf dunklem Hintergrund)
  - Neu: `--gk-sidebar-icon-muted` – Icons getrennt von Text, gedämmter
  - Active Item: farbiger linker Balken (3px, Accent-Farbe) + Icon in Accent-Farbe
  - Gruppen-Labels: stärker gedimmt (0.38 statt 0.75) – klar sekundär
  - Icon-Farbe: explizite Variable statt opacity-Hack
  - Brand-Icon: nutzt `--gk-sidebar-accent` statt `--gk-primary`
  - Per-Theme Accents: indigo=#a5b4fc · ocean=#38bdf8 · forest=#34d399 · rose=#f472b6 · amber=#fcd34d · slate=#7dd3fc

## [1.7.0] - 2026-02-20

### Added
- **skeleton.php** – Startpunkt für neue Projekte
  - Sidebar, Header (fixed), Theme-Switcher, Content-Bereich, Modal, JS — komplett verdrahtet
  - SPA-Navigation mit `?section=...` als Einstiegsmuster
  - Beispiel-Sektionen: Dashboard (StatCards), Artikel (Table+Form), Platzhalter

### Changed
- README komplett überarbeitet: skeleton, aktuelle Komponentenliste, Struktur aktualisiert

## [1.6.0] - 2026-02-20

### Added
- **Searchable Select** – Select mit Live-Suche
- **MultiSelect** – Mehrfachauswahl mit Chips
- **Ajax Select** – Dynamisches Laden via API

### Fixed
- MultiSelect Placeholder verschwindet korrekt wenn Chips gewählt
- Ajax Select Demo URL korrigiert (demo/api/search.php)
- Form-Inputs kontrastreicher (border #cbd5e1, hover, uppercase Labels)
- Sidebar Text kontrastreicher, Items auf volle Textfarbe, Header fixed in Demo

### Changed
- Layout-Modi header-first / sidebar-first konfigurierbar
- Demo Header auf GridKit Header-Komponente umgestellt
- Theme-System mit Sidebar-Integration, 6 Themes Light+Dark

## [1.5.0] - 2026-02-14

### Added
- Table: nowrap, konfigurierbare Spaltenbreiten
- Button-Komponente in newButton integriert

## [1.4.0] - 2026-02-14

### Added
- **Button-Komponente** – Filled/Outlined/Text/Tonal
  - 5 Farben, 3 Sizes
  - Icon-only, Disabled, Loading, Badge, Group

## [1.3.0] - 2026-02-14

### Added
- **Sidebar** – Vollständige Navigations-Sidebar
  - Subitems, Gruppen-Toggle, Divider
  - Collapsible (Icon-only Modus), localStorage-Persistenz
  - collapsePosition('top'|'bottom')
  - Hover-Tooltips im collapsed Modus
- **Stackbare Modals** – Verschachtelt Modal>Table>Modal>Form
- GK global export fix

## [1.2.0] - 2026-02-13

### Added
- **Toast-System** – M3 Snackbar-Redesign (dark theme, bottom-center, slide-in)
- **Confirm-Dialog** – Ersetzt window.confirm
- **CSS-Tooltips** – Material Icons als Default für Buttons

## [1.1.0] - 2026-02-13

### Added
- **StatCards** – Kennzahlen-Cards fuer Dashboards
  - Fluent Interface: card(label, value, opts)
  - Formate: currency, number, percent
  - Farben, Icons, Links, Highlight
- **FilterChips** – Klickbare Filter-Chips
  - URL-basierte Navigation (kein JS noetig)
  - Counts, Farben, Icons
  - Parameter-Preserve fuer Multi-Filter
- **YearFilter** – Jahres-Navigation
  - years() oder range() API
  - URL-basiert mit Parameter-Preserve
  - Kompakte Chip-Darstellung

### Changed
- CSS: Neue Styles fuer gk-stat-cards, gk-filter-chips, gk-year-filter
- Demo-Seite erweitert mit allen neuen Komponenten
- Dashboard-Zusammenspiel-Demo hinzugefuegt

## [1.0.0] - 2026-02-13

### Added
- **Table** – Fluent Interface für Datentabellen
  - DB-Query oder Array als Datenquelle
  - Live-Search mit Debounce (300ms)
  - Sortierung per Klick auf Spaltenheader
  - Pagination
  - Action-Buttons mit Modal-Anbindung
  - Built-in Formatter: currency, percent, date, datetime, boolean, label, email
  - Label Auto-Mapping (grün/orange/rot/grau)
  - SVG-Icons inline (keine externen Icon-Libs)
- **Form** – Fluent Interface für Formulare
  - Feldtypen: text, number, email, tel, url, password, textarea, select, date, time, datetime, toggle, hidden, checkbox, radio, file
  - Row/Column Grid-Layout (16er Grid)
  - AJAX-Submit mit Error-Handling
  - Required-Felder mit Validierung
- **Modal** – Leichtgewichtiger Modal-Container
  - AJAX Content Loading
  - Keyboard-Support (ESC)
  - Größen: small, medium, large
- **CSS** – Ein File, alles drin
  - CSS Custom Properties für Theming
  - `gk-` Prefix (kein Konflikt mit bestehendem CSS)
  - Responsive Design
  - Default Theme
- **JS** – Vanilla JavaScript, ~170 Zeilen
  - Modal, AJAX, Sort, Search, Pagination, Form Submit
  - Null Abhängigkeiten
