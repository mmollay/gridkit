# GridKit Framework – Spezifikation v1.0

## Überblick

GridKit ist ein schlankes PHP-Framework für einheitliche Tabellen und Formulare.
**Null externe Abhängigkeiten.** Kein jQuery, kein Bootstrap, kein Fomantic UI.

## Zielstruktur

```
gridkit/
├── autoload.php           # PSR-4 Autoloader
├── src/
│   ├── Table.php          # Listen/Tabellen-Klasse
│   ├── Form.php           # Formular-Klasse
│   └── Modal.php          # Leichtgewichtiges Modal
├── css/
│   ├── gridkit.css        # Core-Styles (alles in einem File)
│   └── themes/
│       └── default.css    # Default Theme (CSS Custom Properties)
└── js/
    └── gridkit.js         # Minimal: Modal, AJAX, Sort, Filter, Search, Pagination
```

## PHP API

### Table (Fluent Interface)

```php
use GridKit\Table;

$table = new Table('articles');
$table->query($db, "SELECT * FROM articles ORDER BY name")
    ->search(['article_number', 'name'])           // Volltextsuche über Spalten
    ->column('article_number', 'Artikelnr.', ['width' => '110px', 'sortable' => true])
    ->column('name', 'Bezeichnung', ['sortable' => true])
    ->column('unit', 'Einheit', ['width' => '80px'])
    ->column('net_price', 'Netto', ['format' => 'currency', 'align' => 'right'])
    ->column('tax_rate', 'MwSt', ['format' => 'percent'])
    ->column('is_active', 'Status', ['format' => 'label'])
    ->button('edit', ['icon' => 'pencil', 'modal' => 'edit_form', 'params' => ['id' => 'article_id']])
    ->button('delete', ['icon' => 'trash', 'class' => 'danger', 'modal' => 'delete_form', 'params' => ['id' => 'article_id']])
    ->modal('edit_form', 'Artikel bearbeiten', 'form/f_articles.php', ['size' => 'medium'])
    ->modal('delete_form', 'Artikel löschen', 'form/f_delete.php', ['size' => 'small'])
    ->newButton('Neuer Artikel', ['modal' => 'edit_form'])  // Button über der Tabelle
    ->paginate(25)
    ->render();
```

### Form (Fluent Interface)

```php
use GridKit\Form;

$form = new Form('article_form');
$form->action('save/process_article.php')
    ->ajax()                                        // AJAX-Submit, kein Page-Reload
    ->hidden('article_id', $data['article_id'] ?? '')
    ->row()
        ->field('article_number', 'Artikelnr.', 'text', ['required' => true, 'width' => 8])
        ->field('name', 'Bezeichnung', 'text', ['required' => true, 'width' => 8])
    ->endRow()
    ->field('description', 'Beschreibung', 'textarea', ['rows' => 3])
    ->row()
        ->field('unit', 'Einheit', 'select', ['options' => ['Stk' => 'Stück', 'h' => 'Stunde', 'psch' => 'Pauschal'], 'width' => 5])
        ->field('net_price', 'Netto-Preis', 'number', ['step' => '0.01', 'width' => 5])
        ->field('tax_rate', 'MwSt %', 'select', ['options' => ['20' => '20%', '10' => '10%', '0' => '0%'], 'width' => 6])
    ->endRow()
    ->field('is_active', 'Aktiv', 'toggle')
    ->submit('Speichern')
    ->render();
```

### Zusammenspiel Table ↔ Form

1. Table rendert Button mit `data-modal="edit_form"` und `data-params='{"id":123}'`
2. gridkit.js fängt Click ab, öffnet Modal, lädt Form per AJAX (POST mit params)
3. Form rendert sich, User füllt aus, klickt Submit
4. Form submittet per AJAX an action-URL
5. Response: `{"ok": true}` → Modal schließt, Table refresht sich per AJAX
6. Response: `{"ok": false, "errors": {...}}` → Fehler werden am Feld angezeigt

## Built-in Formatter

Table-Spalten mit `'format'` Option werden automatisch formatiert:

| Format | Darstellung | Beispiel |
|--------|------------|---------|
| `currency` | Rechtsbündig, `1.234,56 €` | `['format' => 'currency']` |
| `percent` | `20%` | `['format' => 'percent']` |
| `date` | `13.02.2026` | `['format' => 'date']` |
| `datetime` | `13.02.2026 08:30` | `['format' => 'datetime']` |
| `boolean` | Grüner Haken / Grauer Strich | `['format' => 'boolean']` |
| `label` | Farbiges Label (auto-mapping) | `['format' => 'label']` |
| `email` | Klickbarer mailto-Link | `['format' => 'email']` |

### Label-Mapping (automatisch)

Werte werden automatisch auf Farben gemappt:
- **Grün:** aktiv, bezahlt, paid, ja, yes, 1, true, gesendet, delivered
- **Orange:** offen, pending, entwurf, draft, warnung
- **Rot:** storniert, cancelled, überfällig, overdue, fehler, error
- **Grau:** inaktiv, 0, false, nein, no

Eigenes Mapping möglich: `['format' => 'label', 'labels' => ['custom' => 'blue']]`

## CSS-Konzept

### gridkit.css – Core (ein File, alles drin)

Enthält:
- Minimaler Reset (nur für GridKit-Elemente, kein globaler Reset)
- Alle Komponenten: `.gk-table`, `.gk-form`, `.gk-modal`, `.gk-btn`, `.gk-label`, `.gk-field`
- Responsive (Mobile-first)
- Alle Styles in `.gk-` Namespace (kein Konflikt mit bestehendem CSS)

### CSS Custom Properties für Theming

```css
.gk-root {
    /* Farben */
    --gk-primary: #2563eb;
    --gk-primary-hover: #1d4ed8;
    --gk-success: #16a34a;
    --gk-warning: #f59e0b;
    --gk-danger: #dc2626;
    --gk-info: #0ea5e9;
    
    /* Oberfläche */
    --gk-bg: #ffffff;
    --gk-bg-subtle: #f9fafb;
    --gk-bg-hover: #f3f4f6;
    --gk-border: #e5e7eb;
    --gk-text: #1f2937;
    --gk-text-muted: #6b7280;
    
    /* Typografie */
    --gk-font: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    --gk-font-size: 14px;
    --gk-font-size-sm: 12px;
    
    /* Spacing & Radius */
    --gk-radius: 6px;
    --gk-radius-sm: 4px;
    --gk-spacing: 12px;
    
    /* Schatten */
    --gk-shadow: 0 1px 3px rgba(0,0,0,0.1);
    --gk-shadow-lg: 0 10px 25px rgba(0,0,0,0.15);
}
```

Theme wechseln = `themes/dark.css` einbinden, das nur die Variables überschreibt.

## Feldtypen Form

Nur was wir brauchen:
- `text`, `number`, `email`, `tel`, `url`, `password`
- `textarea`
- `select` (mit options-Array)
- `date`, `time`, `datetime`
- `toggle` (on/off Switch)
- `hidden`
- `checkbox`, `radio`
- `file`

**KEIN** CKEditor, kein Color Picker, kein Slider, kein Accordion, kein Tab-System.
Das kann bei Bedarf SPÄTER dazu.

## JavaScript (gridkit.js)

Vanilla JS, kein jQuery. Features:

1. **Modal:** Öffnen/Schließen, AJAX Content laden, Keyboard (ESC)
2. **Table AJAX:** Refresh nach Form-Submit, Pagination per AJAX
3. **Search:** Debounced Live-Search (sendet Query an Server)
4. **Sort:** Click auf Header → Reload mit Sort-Params
5. **Form Submit:** AJAX POST, Error-Handling, Loading-State
6. **Confirm-Dialog:** Für Delete-Buttons

Geschätzt: ~300-400 Zeilen JS.

## Constraints

- PHP 8.3 kompatibel
- Namespace: `GridKit\`
- Alle CSS-Klassen: `gk-` Prefix
- Alle data-Attribute: `data-gk-`
- Keine globalen CSS-Resets
- MySQLi als DB-Verbindung (wie bestehendes Panel)
- Prepared Statements für Search/Filter
- HTML: `htmlspecialchars()` überall

## Nicht im Scope v1.0

- Export (CSV/Excel/PDF)
- Bulk-Actions (Checkboxen + Massenaktionen)
- Drag & Drop Sortierung
- Inline-Editing
- File-Upload im Form

Diese Features können in v1.1+ dazu, aber v1.0 muss FUNKTIONIEREN, schlank und fehlerfrei.
