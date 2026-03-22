# GRIDKit

**Agent-ready PHP component framework for admin dashboards.** Zero dependencies, Material Design 3, AJAX-first.

[![Version](https://img.shields.io/badge/version-1.1.1-blue)](https://github.com/mmollay/gridkit/releases/tag/v1.1.1)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.2+-purple)](https://php.net)

## Why GRIDKit?

- **17+ components, 1 CSS file, 1 JS file.** Zero dependencies.
- **Agent-first design** — feed the [Agent Skill](GRIDKIT_SKILL.md) to your AI and it generates complete CRUD apps.
- **6 themes** with light & dark mode via CSS Custom Properties (M3-inspired).
- **AJAX-first** — tables search, sort, filter, paginate without page reloads.
- **No jQuery, no Bootstrap, no npm, no build process.** Clone and go.

## Quick Start

```bash
git clone https://github.com/mmollay/gridkit.git
```

```php
require_once '/path/to/gridkit/autoload.php';
```

Use the skeleton as a starting point:

```bash
cp gridkit/skeleton.php my-app/index.php
```

`skeleton.php` includes: Sidebar, Header (fixed), Theme Switcher, Content Area, Modal Container, JS — all wired up.

## Agent Skill — Let AI Build For You

The [`GRIDKIT_SKILL.md`](GRIDKIT_SKILL.md) file teaches any AI assistant (Claude, GPT, Gemini) how to use GRIDKit. Add it to your agent's project context:

1. Download `GRIDKIT_SKILL.md` from the repository
2. Add it to your AI agent's context or project knowledge
3. Describe what you need: *"Create a user management dashboard"*
4. The agent generates working GRIDKit PHP code — tables, forms, modals, all wired up

## Example — CRUD Table in 12 Lines

```php
use GridKit\Table;

$table = new Table('products');
$table->query($db, "SELECT * FROM products ORDER BY name")
    ->search(['name', 'sku'])
    ->column('name', 'Product', ['sortable' => true])
    ->column('sku', 'SKU', ['width' => '120px'])
    ->column('price', 'Price', ['format' => 'currency', 'sortable' => true])
    ->column('is_active', 'Status', ['format' => 'label'])
    ->button('edit', ['icon' => 'edit', 'modal' => 'edit_product'])
    ->modal('edit_product', 'Edit', 'forms/product.php', ['size' => 'medium'])
    ->newButton('New Product', ['modal' => 'edit_product'])
    ->paginate(25)
    ->render();
```

## Components (17+)

| Component | Description |
|-----------|-------------|
| **Table** | 6 variants, search, sort, pagination, multi-select, mobile card layout |
| **Form** | 16-column grid, 15 field types, AJAX submit, validation |
| **Modal** | Stackable dialogs, form-ready, 3 sizes |
| **Sidebar** | Groups, badges, collapse, mobile overlay |
| **Header** | Fixed, search, user dropdown, theme switcher |
| **StatCards** | KPI display with trends and colors |
| **Cards** | Responsive grid (auto-fill, 2/3/4 columns) |
| **Segment** | Container variants (raised, muted, compact, padded) |
| **Message** | Info/Success/Warning/Error with dismiss |
| **Accordion** | Collapsible sections, single-open mode |
| **Tabs** | Tab navigation with panels |
| **Breadcrumb** | Path navigation with icons |
| **Avatar** | 5 sizes, status dots, groups |
| **Gallery** | Thumbnail grid, lazy loading, masonry |
| **Lightbox** | Fullscreen preview, keyboard navigation |
| **Buttons** | Filled/Outlined/Text/Tonal, FAB, 5 colors |
| **Auth** | Session auth, bcrypt, remember-me, styled login |

## Formatters

| Format | Output | Example |
|--------|--------|---------|
| `currency` | `1,234.56 €` | `['format' => 'currency']` |
| `percent` | `20%` | `['format' => 'percent']` |
| `date` | `13.02.2026` | `['format' => 'date']` |
| `datetime` | `13.02.2026 08:30` | `['format' => 'datetime']` |
| `boolean` | ✓ / ✗ | `['format' => 'boolean']` |
| `label` | Color-coded label | `['format' => 'label']` |
| `email` | Clickable link | `['format' => 'email']` |

## Theming

6 built-in themes: **Indigo**, **Ocean**, **Forest**, **Rose**, **Amber**, **Slate** — each with light & dark mode.

```css
/* Custom theme — just override variables */
.gk-root {
    --gk-primary: #8b5cf6;
    --gk-bg: #1a1a2e;
}
```

## Structure

```
gridkit/
├── autoload.php           # PSR-4 Autoloader
├── skeleton.php           # Starting point for new projects
├── GRIDKIT_SKILL.md       # Agent Skill for AI assistants
├── src/                   # PHP components
├── css/
│   ├── gridkit.css        # Core styles
│   └── themes.css         # All themes + dark mode
├── js/
│   └── gridkit.js         # Vanilla JS (event delegation)
└── demo/
    └── index.php          # Live demo of all components
```

## Requirements

- PHP 8.2+
- MySQLi (for DB queries)
- Modern browser (CSS Custom Properties, Fetch API)

## Links

- **Live Demo:** [gridkit.ssi.at/demo](https://gridkit.ssi.at/demo/)
- **Landing Page:** [gridkit.ssi.at](https://gridkit.ssi.at)
- **Agent Skill:** [GRIDKIT_SKILL.md](GRIDKIT_SKILL.md)

## License

MIT — [Martin Mollay](https://github.com/mmollay)
