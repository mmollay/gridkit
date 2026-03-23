# GRIDKit – Agent Skill

> **Version:** 1.2.3 | **License:** MIT | **Repository:** https://github.com/mmollay/gridkit

## Purpose

You are building a web application using **GRIDKit**, a lightweight PHP component framework for admin dashboards and data-driven interfaces. This skill teaches you how to use GRIDKit optimally.

## Architecture Overview

- **Stack:** PHP 8.2+, Vanilla JS, CSS (Material Design 3)
- **Zero Dependencies:** 1 CSS file + 1 JS file, no build process
- **Pattern:** Component-Driven, AJAX-First
- **Core Principle:** One Entity = One Complete CRUD Application

## Installation

```bash
git clone https://github.com/mmollay/gridkit.git
```

```php
require_once '/path/to/gridkit/autoload.php';
```

## Available Components

| Component | Class | Purpose |
|-----------|-------|---------|
| Table | `GridKit\Table` | Data tables with search, sort, pagination, AJAX reload |
| Form | `GridKit\Form` | Grid-based forms (16-column), all field types, AJAX submit |
| Header | `GridKit\Header` | Fixed/sticky header with user menu, search, theme switcher |
| Sidebar | `GridKit\Sidebar` | Navigation with groups, badges, collapse, mobile overlay |
| Modal | `GridKit\Modal` | Dialog overlays, stackable, form-ready |
| Button | `GridKit\Button` | Filled/Outlined/Text/Tonal, icons, sizes |
| Auth | `GridKit\Auth` | Session auth, bcrypt, remember-me |
| Theme | `GridKit\Theme` | 6 themes (indigo/ocean/forest/rose/amber/slate), light/dark |
| Layout | `GridKit\Layout` | Layout modes (sidebar-first, header-first) |
| StatCards | `GridKit\StatCards` | KPI cards with trend indicators |
| FilterChips | `GridKit\FilterChips` | Filter chip buttons |
| YearFilter | `GridKit\YearFilter` | Year navigation filter |
| Lang | `GridKit\Lang` | i18n / multilingual support |

## Page Skeleton

Every GRIDKit page follows this structure:

```php
<?php
require_once '/path/to/gridkit/autoload.php';
use GridKit\Table;
use GridKit\Form;
use GridKit\Modal;
use GridKit\Sidebar;
use GridKit\Header;
use GridKit\Theme;
use GridKit\Layout;
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My App</title>
    <link rel="stylesheet" href="/gridkit/css/gridkit.css">
    <link rel="stylesheet" href="/gridkit/css/themes.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
</head>
<?= Layout::bodyTag('gk-root') ?>

<?php
// Sidebar
$sidebar = new Sidebar('main');
$sidebar->brand('My App', 'dashboard')
    ->group('Main')
    ->item('Dashboard', '/', 'home', ['active' => true])
    ->item('Users', '/users', 'people')
    ->render();
?>

<div class="gk-with-sidebar">
    <?php
    // Header
    $header = new Header();
    echo $header->title('Dashboard')
        ->sidebarToggle(true)
        ->fixed(true)
        ->render();
    ?>

    <main class="gk-main">
        <!-- Your content here -->
    </main>
</div>

<?= Modal::container() ?>
<?= Theme::switcher() ?>
<script src="/gridkit/js/gridkit.js"></script>
</body>
</html>
```

## Component Patterns

### Table – Data Display

```php
$table = new Table('users');
$table->query($db, "SELECT * FROM users ORDER BY name")
    ->search(['name', 'email'])
    ->column('name', 'Name', ['sortable' => true])
    ->column('email', 'Email', ['sortable' => true])
    ->column('role', 'Role', ['format' => 'label'])
    ->column('created_at', 'Created', ['format' => 'date'])
    ->button('edit', [
        'icon' => 'edit',
        'modal' => 'edit_user',
        'params' => ['id' => 'user_id']
    ])
    ->button('delete', [
        'icon' => 'delete',
        'modal' => 'delete_confirm',
        'params' => ['id' => 'user_id'],
        'color' => 'error'
    ])
    ->modal('edit_user', 'Edit User', 'forms/user_edit.php', ['size' => 'medium'])
    ->modal('delete_confirm', 'Delete', 'forms/delete.php', ['size' => 'small'])
    ->newButton('New User', ['modal' => 'edit_user'])
    ->paginate(25)
    ->render();
```

**Column Formats:** `currency`, `percent`, `date`, `datetime`, `boolean`, `label`, `email`

**Table Variants:** Default, Bordered, Striped, Celled, Compact, Selectable

### Form – Data Input

```php
$form = new Form('user_form');
$form->action('api/save_user.php')
    ->method('POST')
    ->row()
        ->field('first_name', 'First Name', 'text', ['width' => 8, 'required' => true])
        ->field('last_name', 'Last Name', 'text', ['width' => 8, 'required' => true])
    ->endRow()
    ->row()
        ->field('email', 'Email', 'email', ['width' => 8])
        ->field('role', 'Role', 'select', [
            'width' => 8,
            'options' => ['admin' => 'Admin', 'editor' => 'Editor', 'viewer' => 'Viewer']
        ])
    ->endRow()
    ->field('bio', 'Biography', 'textarea', ['width' => 16])
    ->field('is_active', 'Active', 'toggle')
    ->submit('Save User')
    ->render();
```

**Field Types:** `text`, `textarea`, `select`, `searchable-select`, `number`, `date`, `time`, `email`, `tel`, `url`, `toggle`, `checkbox`, `radio`, `file`, `hidden`, `color`, `slider`, `richtext`

**Grid:** 16-column grid. Field `width` = number of columns (1–16).

### Form AJAX Response

Forms submit via AJAX. The endpoint must return JSON:

```php
// Success
echo json_encode(['ok' => true]);

// With message
echo json_encode(['ok' => true, 'message' => 'Saved successfully']);

// Validation errors
echo json_encode([
    'ok' => false,
    'errors' => [
        'email' => 'Email already exists',
        'name' => 'Name is required'
    ]
]);
```

### StatCards – KPI Display

```php
$cards = new StatCards();
$cards->card('Total Users', $userCount, ['icon' => 'people', 'trend' => '+12%', 'color' => 'primary'])
    ->card('Revenue', '€' . number_format($revenue, 2), ['icon' => 'payments', 'trend' => '+5.3%'])
    ->card('Orders', $orderCount, ['icon' => 'shopping_cart', 'color' => 'success'])
    ->render();
```

### Modal – Dialogs

```php
// Register modals (once per page)
$table->modal('my_modal', 'Modal Title', 'path/to/content.php', [
    'size' => 'medium'  // small, medium, large, fullscreen
]);

// Or standalone
Modal::container();  // Required once in page HTML
```

### Theme Configuration

```php
// Set default theme and mode
Theme::set('indigo', 'light');  // or 'dark'

// Available themes: indigo, ocean, forest, rose, amber, slate
// Render theme switcher UI
Theme::switcher();
```

## CSS Classes Reference

| Class | Purpose |
|-------|---------|
| `gk-root` | Root container |
| `gk-with-sidebar` | Content area next to sidebar |
| `gk-main` | Main content wrapper |
| `gk-table-wrap` | Table container |
| `gk-form` | Form container |
| `gk-btn` | Button base |
| `gk-btn--filled` | Filled button |
| `gk-btn--outlined` | Outlined button |
| `gk-btn--text` | Text button |
| `gk-sidebar` | Sidebar container |
| `gk-header` | Header container |
| `gk-modal` | Modal overlay |
| `gk-card` | Card container |
| `gk-dark` | Dark mode class |

## JavaScript API

```javascript
// Modal
GK.modal.open('modal-id');
GK.modal.close('modal-id');

// Table refresh (after save/delete)
GK.table.refresh('table-id');

// Toast notifications
GK.toast('Message', 'success');  // success, error, warning, info

// Confirm dialog
GK.confirm('Are you sure?', () => { /* on confirm */ });
```

## Internationalization (Lang)

GRIDKit has built-in i18n support. Default language is English, German included.

```php
use GridKit\Lang;

// Set locale (call before rendering any components)
Lang::set('de');         // Switch to German
Lang::set('en');         // Switch to English (default)

// Translations auto-load from gridkit/lang/*.php
// Custom translations:
Lang::load('fr', [
    'table.search'  => 'Rechercher…',
    'table.empty'   => 'Aucune entrée trouvée',
    'auth.login'    => 'Se connecter',
]);
```

**For JavaScript strings**, output `Lang::jsConfig()` in your `<head>`:

```php
<head>
    <?= Lang::jsConfig() ?>
</head>
```

**Adding a new language:** Create `lang/fr.php` returning an array of translations. See `lang/en.php` for all available keys.

**Translation keys** follow the pattern `component.key` (e.g. `table.search`, `form.select`, `auth.login`). JavaScript keys are prefixed with `js.` (e.g. `js.confirm_title`).

## Best Practices

1. **Always use `Modal::container()`** once at the end of your page body
2. **Tables refresh automatically** after modal form submissions return `{"ok": true}`
3. **Use the 16-column grid** for form layouts — it ensures responsive behavior
4. **Set `['sortable' => true]`** on columns that users will want to sort
5. **Use format helpers** (`currency`, `date`, `label`) instead of manual formatting
6. **Include `Theme::switcher()`** for user-facing apps
7. **Use `Layout::bodyTag()`** instead of a plain `<body>` tag
8. **Place `gridkit.js` at the end** of the body, after all components are rendered

## File Structure for a GRIDKit Project

```
my-project/
├── index.php              # Main page (based on skeleton.php)
├── api/
│   ├── save_user.php      # Form endpoints (return JSON)
│   └── delete_user.php
├── forms/
│   ├── user_edit.php      # Modal form content
│   └── user_delete.php
└── gridkit/               # GRIDKit framework (git clone)
    ├── autoload.php
    ├── src/
    ├── css/
    └── js/
```

## Common Recipes

### Full CRUD Page

```php
// 1. Table with edit/delete buttons
$table = new Table('items');
$table->query($db, "SELECT * FROM items")
    ->search(['name'])
    ->column('name', 'Name', ['sortable' => true])
    ->column('price', 'Price', ['format' => 'currency', 'sortable' => true])
    ->button('edit', ['icon' => 'edit', 'modal' => 'edit_item', 'params' => ['id' => 'item_id']])
    ->button('delete', ['icon' => 'delete', 'modal' => 'del_item', 'params' => ['id' => 'item_id'], 'color' => 'error'])
    ->modal('edit_item', 'Edit', 'forms/item_edit.php', ['size' => 'medium'])
    ->modal('del_item', 'Delete', 'forms/item_delete.php', ['size' => 'small'])
    ->newButton('New Item', ['modal' => 'edit_item'])
    ->paginate(25)
    ->render();
```

### Dashboard with Stats + Table

```php
$cards = new StatCards();
$cards->card('Total', $total, ['icon' => 'inventory'])
    ->card('Active', $active, ['icon' => 'check_circle', 'color' => 'success'])
    ->card('Revenue', '€' . $revenue, ['icon' => 'payments'])
    ->render();

$table = new Table('recent');
$table->query($db, "SELECT * FROM orders ORDER BY created_at DESC LIMIT 10")
    ->column('order_no', '#')
    ->column('customer', 'Customer')
    ->column('total', 'Total', ['format' => 'currency'])
    ->column('created_at', 'Date', ['format' => 'datetime'])
    ->render();
```

### Login Page

```php
use GridKit\Auth;

Auth::setUsersFile(__DIR__ . '/users.conf');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (Auth::login($_POST['username'], $_POST['password'])) {
        header('Location: /dashboard');
        exit;
    }
}

if (!Auth::check()) {
    Auth::renderLogin(['title' => 'My App']);
    exit;
}
```
