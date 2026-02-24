# Changelog - GridKit

Alle Änderungen an diesem Projekt werden hier dokumentiert.
Format basierend auf [Keep a Changelog](https://keepachangelog.com/de/1.0.0/).

---

## [Unreleased]
- Weitere Komponenten geplant (Flash Messages/Alerts, Standalone Select)
- Dokumentation vervollständigen

## [0.9.14] - 2026-02-24

### Added
- `--gk-content-max-width: 1400px` — Zentrales Layout-Token für maximale Content-Breite

---

## [0.9.11] - 2026-02-23

### Fixed
- **`gk-form-actions`** – Duplikat entfernt, `align-items: center` ergänzt → Buttons verschiedener Höhe/Typen (a, button) stehen auf gleicher Linie

### Added
- **`Form::card()`** – optionaler gk-card Wrapper um das gerenderte Formular (`->card()` in der Form-Chain)

---

## [0.9.10] - 2026-02-23

### Fixed
- **Demo** – Tabellen-Labels (size/variant) waren als `h4` direkt an die Spaltenköpfe geklebt. Jetzt als dedizierte Caption-Bar mit Border-Bottom und grauem Hintergrund (klar vom Table-Content getrennt)

---

## [0.9.9] - 2026-02-23

### Fixed
- **`gk-table-wrap`** – `box-shadow` entfernt, stattdessen `border: 1px solid var(--gk-outline-variant)` wie `gk-card` → konsistentes Aussehen, kein "schwebender" Tabelleneffekt mehr

---

## [0.9.8] - 2026-02-23

### Added
- **Tabs** (`gk-tabs`, `gk-tab-nav`, `gk-tab-btn`, `gk-tab-panel`) – CSS + JS Tab-System, data-tab Attribute, Dark Mode ready

---

## [0.9.7] - 2026-02-23

### Changed
- **`--gk-surface-dim`** Light Mode: `#ddd8e4` → `#e8edf2` — neutrales Slate-Grau statt lila M3-Tönung

---

## [0.9.6] - 2026-02-23

### Added
- **AJAX Pagination** – `[data-gk-ajax-table="id"]` wrapper aktiviert seitenloses Blättern ohne Full-Reload. Funktioniert automatisch für alle `a.gk-page-btn` Links innerhalb des Wrappers. Fallback auf normalen Link bei Fetch-Fehler.

---

## [0.9.5] - 2026-02-23

### Fixed
- **Pagination `gk-page-btn`** – `display: inline-flex; align-items/justify-content: center` ergänzt, Buttons werden nicht mehr zu großen Kreisen

---

## [0.9.4] - 2026-02-23

### Added
- **Table `format => 'html'`** – Spalten können vorgerendertes HTML ausgeben (für Chips, Badges, etc.)

---

## [0.9.3] - 2026-02-23

### Fixed
- **Labels Dark Mode** – `.gk-label-green/orange/red/gray/blue` jetzt mit Dark Mode Overrides (invertierte Farben)
- **gk-page-header** – `flex-wrap: wrap` entfernt, Button bleibt rechts statt in neue Zeile zu wrappen

---

## [0.9.2] - 2026-02-23

### Added
- **Utility-Klassen** – vollständige Migrationsbasis für Panel-Apps:
  - `.gk-page-header` – Seitentitel + Actions-Row (flex, space-between, dark-mode-aware)
  - `.gk-section-title` – Abschnittsüberschriften
  - `.gk-spacer`, `.gk-spacer-sm`, `.gk-spacer-md`, `.gk-spacer-lg`, `.gk-spacer-xl` – Abstände
  - `.gk-text-muted` – gedämpfte Textfarbe via `--gk-on-surface-variant`
  - `.gk-grid`, `.gk-grid-2`, `.gk-grid-4` – responsive Grid-Layouts
  - `.gk-form-page`, `.gk-form-actions` – Formular-Seitenlayout

---

## [0.9.1] - 2026-02-21

### Fixed
- Dark Mode Demo-Cards und standalone Table-Borders korrigiert
- Code-Blöcke neben Beispielen in Demo

---

## [0.9.0] - 2026-02-20

Erster stabiler Stand. Alle Kern-Komponenten vorhanden und getestet.

### Komponenten
- **Table** – Sortierung, Filterung, Suche, Pagination, Status-Labels, Actions
- **Form** – Inputs, Select, Textarea, Validierung, Grid-Layout
- **Modal** – Öffnen/Schließen, Overlay, Body-Scroll-Lock
- **StatCards** – Kennzahl-Karten mit Trend-Indikatoren
- **FilterChips** – Aktiv/Inaktiv Toggle Chips
- **YearFilter** – Jahres-Navigation mit Pfeil-Controls
- **Formatter** – Zahlen, Währung, Datum, Status als Hilfsfunktionen (JS)
- **Toast** – Kurze Benachrichtigungen (success/error/warning/info)
- **Confirm** – Bestätigungs-Dialog
- **Buttons** – Primary, Secondary, Danger, Ghost, Icon
- **Header** – Fixed Header mit Menü-Toggle, Suche, Actions

### Design-System
- 6 Themes: `indigo`, `ocean`, `forest`, `rose`, `amber`, `slate`
- Light & Dark Mode mit vollständigen CSS Custom Properties
- Sidebar: neutral `#f4f5f7` für alle Themes, Theme-Identität über Active-State
- Dark Mode Sidebar: pro Theme eigenes sehr dunkles Hintergrund-BG
- M3-inspirierte Farbpalette (surface, on-surface, primary-container etc.)

### Technisches
- Zero Dependencies – reines CSS + Vanilla JS
- `gridkit.css` + `themes.css` + `gridkit.js`
- PHP-Komponenten: `Sidebar.php`, `Header.php`, `Layout.php`, `Theme.php`
- `skeleton.php` als Startpunkt für neue Projekte
- Live Demo: [gridkit.ssi.at/demo/](https://gridkit.ssi.at/demo/)

---

*Ältere Entwicklungsversionen (0.1–0.7) archiviert und entfernt.*

## [0.9.13] - 2026-02-23
### Added
- gk-modal-large: max-width 860px für große Modals (z.B. PDF-Vorschau)
