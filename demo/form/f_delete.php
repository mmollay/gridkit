<?php
require_once __DIR__ . '/../../autoload.php';

use GridKit\Lang;

$id = $_POST['id'] ?? '';
?>
<p style="margin:0 0 6px;font-size:15px;">
    <?= htmlspecialchars(Lang::t('table.confirm_delete'), ENT_QUOTES, 'UTF-8') ?>
</p>
<p style="margin:0;color:var(--gk-on-surface-variant);font-size:13.5px;">
    <?= htmlspecialchars(Lang::t('table.delete_note'), ENT_QUOTES, 'UTF-8') ?>
</p>

<div class="gk-modal-footer">
    <button type="button" class="gk-btn gk-btn-text gk-btn-neutral" onclick="GK.modal.close()">
        <?= htmlspecialchars(Lang::t('form.cancel'), ENT_QUOTES, 'UTF-8') ?>
    </button>
    <button type="button" class="gk-btn gk-btn-filled gk-btn-danger" onclick="GK.modal.close()">
        <?= htmlspecialchars(Lang::t('table.delete'), ENT_QUOTES, 'UTF-8') ?>
    </button>
</div>
