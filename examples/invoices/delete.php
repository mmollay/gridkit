<?php

/**
 * Two jobs behind one file.
 *
 * A GET-shaped modal load (GridKit POSTs the row id) shows the confirmation.
 * The confirm button posts back with `confirm=1`, which does the deletion and
 * answers with JSON, exactly like save.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/store.php';

$id      = (int) ($_POST['id'] ?? 0);
$invoice = findInvoice($id);

if (!empty($_POST['confirm'])) {
    saveAll(array_filter(
        allInvoices(),
        static fn(array $row): bool => (int) $row['id'] !== $id
    ));
    respond(['ok' => true]);
}

if (!$invoice) {
    echo '<p>' . e(t('empty')) . '</p>';
    return;
}

?>
<form action="delete.php" method="post" data-gk-ajax>
  <input type="hidden" name="id" value="<?= e($invoice['id']) ?>">
  <input type="hidden" name="confirm" value="1">
  <input type="hidden" name="lang" value="<?= e($lang) ?>">

  <p style="margin:0 0 6px;font-size:15px;">
    <?= e(str_replace('{number}', $invoice['number'], t('delete_ask'))) ?>
  </p>
  <p style="margin:0 0 4px;color:var(--gk-on-surface-variant);font-size:13.5px;">
    <?= e($invoice['customer']) ?> · <?= e(t('delete_note')) ?>
  </p>

  <div class="gk-modal-footer">
    <button type="button" class="gk-btn gk-btn-text gk-btn-neutral"
            onclick="GK.modal.close()"><?= e(t('cancel')) ?></button>
    <button type="submit" class="gk-btn gk-btn-filled gk-btn-danger">
      <?= e(t('delete')) ?>
    </button>
  </div>
</form>
