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
        $t = static fn(string $k): string
            => htmlspecialchars(Lang::t($k), ENT_QUOTES, 'UTF-8');

        echo <<<HTML
<div class="gk-beleg-modal-overlay" id="gk-beleg-modal" data-gk-beleg-modal>
    <div class="gk-modal gk-beleg-modal-box">
        <div class="gk-modal-header">
            <h3 class="gk-modal-title" data-gk-beleg-title>{$t('doc.title')}</h3>
            <div class="gk-beleg-modal-actions">
                <button type="button" data-gk-beleg-unlink
                        class="gk-btn gk-btn-text gk-btn-danger gk-btn-sm gk-hidden"
                        title="{$t('doc.unlink_title')}">
                    <span class="material-icons">link_off</span>
                    <span class="gk-beleg-btn-label">{$t('doc.unlink')}</span>
                </button>
                <a data-gk-beleg-download href="#" download target="_blank" rel="noopener"
                   class="gk-btn gk-btn-outlined gk-btn-neutral gk-btn-sm" title="{$t('doc.download_title')}">
                    <span class="material-icons">download</span>
                    <span class="gk-beleg-btn-label">{$t('doc.download')}</span>
                </a>
                <a data-gk-beleg-open href="#" target="_blank" rel="noopener"
                   class="gk-btn gk-btn-outlined gk-btn-primary gk-btn-sm" title="{$t('doc.open_tab_title')}">
                    <span class="material-icons">open_in_new</span>
                    <span class="gk-beleg-btn-label">{$t('doc.open_tab')}</span>
                </a>
                <button type="button" data-gk-beleg-close
                        class="gk-btn gk-btn-text gk-btn-neutral gk-btn-sm gk-beleg-modal-close-btn"
                        title="{$t('doc.close')}">
                    <span class="material-icons">close</span>
                </button>
            </div>
        </div>
        <div class="gk-modal-body gk-beleg-modal-body">
            <iframe data-gk-beleg-frame class="gk-beleg-modal-frame" src="about:blank" title="{$t('doc.preview')}"></iframe>
            <div class="gk-beleg-modal-mobile">
                <span class="material-icons gk-beleg-mobile-icon">picture_as_pdf</span>
                <p>{$t('doc.mobile_hint')}</p>
                <a data-gk-beleg-mobile-open href="#" target="_blank" rel="noopener"
                   class="gk-btn gk-btn-filled gk-btn-primary">
                    <span class="material-icons">open_in_new</span>
                    <span>{$t('doc.open_pdf')}</span>
                </a>
            </div>
        </div>
    </div>
</div>
HTML;
    }
}
