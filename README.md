# GridKit

Schlankes PHP-Framework für einheitliche Tabellen und Formulare. Null Abhängigkeiten.

## Warum GridKit?

- **2 Klassen, 1 CSS-File.** `Table` + `Form`. Fertig.
- **Kein jQuery, kein Bootstrap, kein Fomantic UI.**
- **847 Zeilen** gesamt (PHP + CSS + JS).
- **Themes** über CSS Custom Properties.

## Installation

```bash
git clone git@github.com:mmollay/gridkit.git
```

In deinem Projekt:
```php
require_once '/path/to/gridkit/autoload.php';
```

## Quick Start

### Tabelle

```php
use GridKit\Table;

$table = new Table('articles');
$table->query($db, "SELECT * FROM articles ORDER BY name")
    ->search(['name', 'article_number'])
    ->column('article_number', 'Artikelnr.', ['width' => '110px', 'sortable' => true])
    ->column('name', 'Bezeichnung', ['sortable' => true])
    ->column('net_price', 'Netto', ['format' => 'currency', 'align' => 'right'])
    ->column('is_active', 'Status', ['format' => 'label'])
    ->button('edit', ['icon' => 'pencil', 'modal' => 'edit_form', 'params' => ['id' => 'article_id']])
    ->modal('edit_form', 'Bearbeiten', 'form/edit.php', ['size' => 'medium'])
    ->newButton('Neuer Artikel', ['modal' => 'edit_form'])
    ->paginate(25)
    ->render();
```

### Formular

```php
use GridKit\Form;

$form = new Form('my_form');
$form->action('save/process.php')
    ->ajax()
    ->hidden('id', $data['id'] ?? '')
    ->row()
        ->field('name', 'Name', 'text', ['required' => true, 'width' => 8])
        ->field('email', 'E-Mail', 'email', ['width' => 8])
    ->endRow()
    ->field('notes', 'Notizen', 'textarea', ['rows' => 3])
    ->field('active', 'Aktiv', 'toggle')
    ->submit('Speichern')
    ->render();
```

### Zusammenspiel

1. Table rendert Button → öffnet Modal
2. Modal lädt Form per AJAX
3. Form submittet per AJAX → `{"ok": true}`
4. Modal schließt → Table refresht automatisch

## Formatter

| Format | Ausgabe | Beispiel |
|--------|---------|---------|
| `currency` | `1.234,56 €` | `['format' => 'currency']` |
| `percent` | `20%` | `['format' => 'percent']` |
| `date` | `13.02.2026` | `['format' => 'date']` |
| `datetime` | `13.02.2026 08:30` | `['format' => 'datetime']` |
| `boolean` | ✓ / ✗ | `['format' => 'boolean']` |
| `label` | Farbiges Label | `['format' => 'label']` |
| `email` | Klickbarer Link | `['format' => 'email']` |

### Label Auto-Mapping

- **Grün:** aktiv, bezahlt, paid, ja, yes, 1, true, gesendet, delivered
- **Orange:** offen, pending, entwurf, draft, warnung
- **Rot:** storniert, cancelled, überfällig, overdue, fehler, error
- **Grau:** inaktiv, 0, false, nein, no

Eigenes Mapping: `['format' => 'label', 'labels' => ['custom' => 'blue']]`

## Theming

GridKit nutzt CSS Custom Properties. Theme wechseln = ein CSS-File einbinden:

```html
<link rel="stylesheet" href="gridkit/css/gridkit.css">
<link rel="stylesheet" href="gridkit/css/themes/dark.css"> <!-- optional -->
```

Eigenes Theme erstellen – nur Variables überschreiben:

```css
.gk-root {
    --gk-primary: #8b5cf6;
    --gk-bg: #1a1a2e;
    --gk-text: #e5e7eb;
    --gk-border: #374151;
}
```

## Anforderungen

- PHP 8.3+
- MySQLi (für DB-Queries)
- Moderner Browser (CSS Custom Properties, Fetch API)

## Struktur

```
gridkit/
├── autoload.php           # PSR-4 Autoloader
├── src/
│   ├── Table.php          # Tabellen-Klasse
│   ├── Form.php           # Formular-Klasse
│   └── Modal.php          # Modal-Container
├── css/
│   ├── gridkit.css        # Core Styles
│   └── themes/
│       └── default.css    # Default Theme
├── js/
│   └── gridkit.js         # Vanilla JS
└── demo/
    └── index.php          # Live Demo
```

## Lizenz

MIT
