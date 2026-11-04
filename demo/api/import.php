<?php
/**
 * The steps after the first of the demo's importer (GridKit\Import, 1.119.0).
 *
 * Without script the page posts the file here and this page shows step two — the mapping and the
 * preview — which posts to itself until the person imports or cancels. With script the browser has
 * shown step two already, and the form posts the file and the mapping here once. Either way this
 * page reads the file and checks every row again: no row is taken from the browser.
 *
 * A real page checks its CSRF token before handle() and stores $import->result()['rows'] when
 * step() says 'done'. The demo stores nothing and keeps nothing (storage(false) in _import.php):
 * it shows what it would store.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../autoload.php';
require_once __DIR__ . '/../_import.php';

use GridKit\{Lang, Layout, Table};

$lang = $_GET['lang'] ?? $_COOKIE['gk_lang'] ?? 'en';
Lang::set(in_array($lang, ['en', 'de'], true) ? $lang : 'en');

$name = isset($_POST['customers-server']) ? 'customers-server' : 'customers';
$import = demoImporter($name)->action('import.php');
if (($_POST['sample'] ?? '') === '1') {
    $import->fromString((string) file_get_contents(__DIR__ . '/../media/kunden.csv'), 'kunden.csv');
} else {
    $import->handle();
}
$e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="<?= $e(Lang::locale()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Import customers — GridKit Demo</title>
    <link rel="stylesheet" href="<?= Layout::asset('../../css/gridkit.css') ?>">
    <link rel="stylesheet" href="<?= Layout::asset('../../css/themes.css') ?>">
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <style>body { margin: 0; background: var(--gk-surface-container); } main { max-width: 1100px; margin: 0 auto; padding: 24px 16px 48px; }
        .demo-card { background: var(--gk-surface); border: 1px solid var(--gk-outline-variant); border-radius: 12px; padding: 20px; }
        h1 { font-size: 22px; margin: 8px 0 16px; } h2 { font-size: 17px; margin: 28px 0 8px; }</style>
    <?= Lang::jsConfig() ?>
</head>
<?= Layout::bodyTag('gk-root') ?>
<main>
    <p><a href="../#import">← Back to the demo</a></p>
    <h1>Import customers</h1>
    <div class="demo-card">
        <?php $import->render(); ?>
    </div>
    <?php if ($import->step() === 'done'):
        $r = $import->result();
        $rows = [];
        foreach ($r['rows'] as $row => $values) {
            $line = ['row' => $row];
            foreach ($values as $key => $value) {
                $line[$key] = is_bool($value) ? ($value ? 'yes' : 'no') : (string) ($value ?? '');
            }
            $rows[] = $line;
        }
        ?>
        <h2>What the server would store</h2>
        <p>The rows of <code>$import->result()['rows']</code>, read and checked here again. Nothing is stored in this demo.</p>
        <?php
        $table = (new Table('imported'))->setData($rows)->caption('Imported rows')->column('row', 'Row', ['format' => 'number']);
        foreach (['customer_no' => 'Customer no.', 'first_name' => 'First name', 'last_name' => 'Last name', 'email' => 'E-mail',
                  'country' => 'Country', 'birthday' => 'Birthday', 'revenue' => 'Revenue', 'newsletter' => 'Newsletter'] as $key => $label) {
            if (in_array($key, $r['mapping'], true)) $table->column($key, $label);
        }
        $table->paginate(25)->render();
        ?>
    <?php endif; ?>
</main>
<script src="<?= Layout::asset('../../js/gridkit.js') ?>"></script>
<script src="<?= Layout::asset('../../js/import.js') ?>" defer></script>
</body>
</html>
