<?php
namespace GridKit;

/**
 * BelegModal — Globaler PDF-/Beleg-Vorschau-Modal.
 *
 * Verwendung:
 *
 *   // Einmalig im Layout (z.B. layouts/panel.php) — bevor </body>:
 *   \GridKit\BelegModal::container();
 *
 *   // JS-API überall:
 *   GK.belegModal.open('/faktura/invoices/123/pdf');
 *   GK.belegModal.open(url, { title: 'Rechnung 123', autoPrint: true });
 *   GK.belegModal.open(url, {
 *       unlinkExpenseId: 456,
 *       onUnlink: function() { location.reload(); }
 *   });
 *   GK.belegModal.close();
 *
 * Verhalten:
 *   - Desktop: iframe lädt URL inline (Browser-PDF-Viewer oder HTML-Vorschau).
 *   - Mobile (<= 768px): iframe versteckt, „PDF öffnen"-Button öffnet nativen Viewer.
 *   - Download + In neuem Tab: beide stabil (target=_blank).
 *   - ESC schliesst.
 *   - autoPrint: druckt das iframe sobald geladen.
 *   - unlinkExpenseId: zeigt „Verknüpfung trennen"-Button (POST an /faktura/api/beleg/unlink).
 */
class BelegModal
{
    /**
     * Rendert den Modal-Container. Einmal pro Page (typischerweise im Layout).
     */
    public static function container(): void
    {
        echo <<<'HTML'
<div class="gk-beleg-modal-overlay" id="gk-beleg-modal" data-gk-beleg-modal>
    <div class="gk-modal gk-beleg-modal-box">
        <div class="gk-modal-header">
            <h3 class="gk-modal-title" data-gk-beleg-title>Beleg</h3>
            <div class="gk-beleg-modal-actions">
                <button type="button" data-gk-beleg-unlink
                        class="gk-btn gk-btn-text gk-btn-danger gk-btn-sm gk-hidden"
                        title="Beleg-Verknüpfung trennen">
                    <span class="material-icons">link_off</span>
                    <span class="gk-beleg-btn-label">Verknüpfung trennen</span>
                </button>
                <a data-gk-beleg-download href="#" download target="_blank" rel="noopener"
                   class="gk-btn gk-btn-outlined gk-btn-neutral gk-btn-sm" title="Herunterladen">
                    <span class="material-icons">download</span>
                    <span class="gk-beleg-btn-label">Download</span>
                </a>
                <a data-gk-beleg-open href="#" target="_blank" rel="noopener"
                   class="gk-btn gk-btn-outlined gk-btn-primary gk-btn-sm" title="In neuem Tab öffnen">
                    <span class="material-icons">open_in_new</span>
                    <span class="gk-beleg-btn-label">In neuem Tab</span>
                </a>
                <button type="button" data-gk-beleg-close
                        class="gk-btn gk-btn-text gk-btn-neutral gk-btn-sm gk-beleg-modal-close-btn"
                        title="Schließen">
                    <span class="material-icons">close</span>
                </button>
            </div>
        </div>
        <div class="gk-modal-body gk-beleg-modal-body">
            <iframe data-gk-beleg-frame class="gk-beleg-modal-frame" src="about:blank" title="Beleg-Vorschau"></iframe>
            <div class="gk-beleg-modal-mobile">
                <span class="material-icons gk-beleg-mobile-icon">picture_as_pdf</span>
                <p>Auf dem Handy lässt sich das PDF im nativen Viewer besser lesen.</p>
                <a data-gk-beleg-mobile-open href="#" target="_blank" rel="noopener"
                   class="gk-btn gk-btn-filled gk-btn-primary">
                    <span class="material-icons">open_in_new</span>
                    <span>PDF öffnen</span>
                </a>
            </div>
        </div>
    </div>
</div>
HTML;
    }
}
