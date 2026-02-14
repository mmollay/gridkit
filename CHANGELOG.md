# Changelog - GridKit

Alle Änderungen an diesem Projekt werden hier dokumentiert.
Format basierend auf [Keep a Changelog](https://keepachangelog.com/de/1.0.0/).

## [1.1.0] - 2026-02-14

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
