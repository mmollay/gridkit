<?php

/**
 * A complete GridKit application: list, create, edit, delete.
 *
 * Run it with nothing installed:
 *
 *     php -S localhost:8000 -t /path/to/gridkit
 *     open http://localhost:8000/examples/invoices/
 *
 * Data lives in your session, so nothing here needs a database. What the
 * table asks for — search, filter, sort, page — is answered in store.php,
 * in the four places where you would otherwise write SQL.
 */

declare(strict_types=1);

require_once __DIR__ . '/store.php';

use GridKit\{Button, Layout, Modal, StatCards, Table, Theme};

/* ── Actions that are not form submissions ─────────────────────────────── */

if (isset($_GET['reset'])) {
    unset($_SESSION['invoices']);
    header('Location: ?lang=' . urlencode($lang) . '&reset_done=1');
    exit;
}

/* ── The table ─────────────────────────────────────────────────────────── */

$result = queryInvoices();

$table = (new Table('invoices'))
    // rows(), not setData(): the browser gets one page, the server keeps the
    // rest. Search, sort, filter and paging therefore go back to the server —
    // which is the point, and what the AJAX branch further down answers.
    ->rows($result['rows'], $result['total'])
    ->search(['number', 'customer'])
    ->column('number',   t('number'),   ['width' => '140px', 'sortable' => true])
    ->column('customer', t('customer'), ['sortable' => true])
    ->column('issued',   t('issued'),   ['format' => 'date', 'width' => '120px', 'sortable' => true, 'muted' => true])
    ->column('due',      t('due'),      ['format' => 'date', 'width' => '120px', 'sortable' => true])
    ->column('amount',   t('amount'),   ['format' => 'currency', 'align' => 'right', 'sortable' => true])
    // The stored value stays 'paid'; the cell shows whatever this language
    // calls it. Colours are pinned so 'sent' does not fall through to grey.
    ->column('status', t('status'), ['format' => 'label', 'width' => '130px', 'labels' => [
        'draft'   => ['color' => 'gray',   'text' => t('st_draft')],
        'sent'    => ['color' => 'blue',   'text' => t('st_sent')],
        'paid'    => ['color' => 'green',  'text' => t('st_paid')],
        'overdue' => ['color' => 'red',    'text' => t('st_overdue')],
    ]])
    ->filter('status', 'select', ['options' => statuses()])
    ->button('edit',   ['icon' => 'edit',   'modal' => 'invoice_form', 'title' => t('edit')])
    // A modal, because the dialog names the invoice it is about. The shorter
    // route is ['confirm' => true], which asks with a standard dialog and then
    // fires gk:rowaction for the application to handle.
    ->button('delete', ['icon' => 'delete', 'modal' => 'invoice_delete', 'title' => t('delete'), 'class' => 'danger'])
    // Two declarations of the same form, so the modal is headed "New invoice"
    // when creating and "Edit invoice" when editing.
    ->modal('invoice_form',   t('edit'),   'form.php',   ['size' => 'medium'])
    ->modal('invoice_new',    t('new'),    'form.php',   ['size' => 'medium'])
    ->modal('invoice_delete', t('delete'), 'delete.php', ['size' => 'small'])
    ->newButton(t('new'), ['modal' => 'invoice_new'])
    ->paginate(8)
    ->emptyState(t('empty'), ['hint' => t('empty_hint'), 'icon' => 'receipt_long']);

/** The cards above the table. Rendered twice: on the page, and into the
 *  out-of-band template below, so a reload keeps them in step with the list. */
function renderStats(): void
{
    $totals = invoiceTotals();
    (new StatCards('invoice-stats'))
        ->card(t('stat_count'),   $totals['count'],   ['format' => 'number',   'icon' => 'receipt_long', 'color' => 'blue'])
        ->card(t('stat_total'),   $totals['total'],   ['format' => 'currency', 'icon' => 'payments',     'color' => 'green'])
        ->card(t('stat_open'),    $totals['open'],    ['format' => 'currency', 'icon' => 'schedule',     'color' => 'orange'])
        ->card(t('stat_overdue'), $totals['overdue'], ['format' => 'currency', 'icon' => 'warning',      'color' => 'red'])
        ->render();
}

/* ── The AJAX branch ───────────────────────────────────────────────────── */

/**
 * When the browser reloads just this table, the response must be the table
 * fragment and nothing else — it is injected straight into the wrapper. Without
 * this the sidebar, the header and every script tag would end up inside the
 * table. Anything else the reload should refresh goes in a <template> next to
 * it, addressed by a CSS selector.
 */
if (Table::isAjaxReload('invoices')) {
    $table->render();
    echo '<template data-gk-replace="[data-gk-stats=invoice-stats]">';
    renderStats();
    echo '</template>';
    exit;
}

?><!doctype html>
<html lang="<?= e($lang) ?>" <?= Theme::attributes() ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(t('app')) ?> — GridKit</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
<link rel="stylesheet" href="<?= Layout::asset('../../css/gridkit.css') ?>">
<link rel="stylesheet" href="<?= Layout::asset('../../css/themes.css') ?>">
<?= GridKit\Lang::jsConfig() ?>
<style>
  body { padding: 0; }
  .app { max-width: 1180px; margin: 0 auto; padding: 40px 24px 80px; }
  .app-head { display: flex; flex-wrap: wrap; align-items: flex-end;
              justify-content: space-between; gap: 16px; margin-bottom: 8px; }
  .app-head h1 { margin: 0; font-size: var(--gk-text-headline, 26px); font-weight: 600; }
  .app-head p  { margin: 6px 0 0; color: var(--gk-on-surface-variant); font-size: 14px; }
  .app-tools   { display: flex; align-items: center; gap: 8px; }
  .stats       { margin: 28px 0 20px; }
  .flash { background: var(--gk-success-container, #d1fae5);
           color: var(--gk-on-surface); border-radius: var(--gk-radius, 10px);
           padding: 10px 16px; font-size: 14px; margin-bottom: 20px; }
  .source { margin-top: 40px; font-size: 13.5px; color: var(--gk-on-surface-variant); }
  .source code { font-size: 12.5px; }
</style>
</head>
<?= Theme::bodyTag('gk-root') ?>

<div class="app">

  <div class="app-head">
    <div>
      <h1><?= e(t('app')) ?></h1>
      <p><?= e(t('subtitle')) ?></p>
    </div>
    <div class="app-tools">
      <?= Theme::switcher() ?>
      <a class="gk-btn gk-btn-text gk-btn-primary gk-btn-sm"
         href="?lang=<?= $lang === 'en' ? 'de' : 'en' ?>"><?= $lang === 'en' ? 'DE' : 'EN' ?></a>
      <?= Button::render(t('reset'), [
            'variant' => 'outlined', 'color' => 'neutral', 'size' => 'sm',
            'icon' => 'restart_alt', 'href' => '?lang=' . urlencode($lang) . '&reset=1',
          ]) ?>
    </div>
  </div>

  <?php if (isset($_GET['reset_done'])): ?>
    <p class="flash"><?= e(t('reset_done')) ?></p>
  <?php endif; ?>

  <div class="stats"><?php renderStats(); ?></div>

  <?php $table->render(); ?>

  <p class="source">
    Source: <code>examples/invoices/</code> — <code>index.php</code> (this page),
    <code>store.php</code> (data + query), <code>form.php</code>,
    <code>save.php</code>, <code>delete.php</code>.
  </p>

</div>

<?php Modal::container(); ?>
<script src="<?= Layout::asset('../../js/gridkit.js') ?>"></script>
</body>
</html>
