<?php
$id = $_POST['id'] ?? '';
?>
<div style="text-align:center; padding: 20px 0;">
    <p style="font-size:15px; margin-bottom:16px;">Diesen Eintrag wirklich loeschen?</p>
    <div style="display:flex; gap:8px; justify-content:center;">
        <button class="gk-btn" onclick="GK.modal.close()">Abbrechen</button>
        <button class="gk-btn gk-btn-danger" onclick="GK.modal.close()">Loeschen</button>
    </div>
</div>
