<?php
namespace GridKit;

/**
 * BelegModal — global PDF / receipt (Beleg) preview modal.
 *
 * Usage:
 *
 *   // Once in the layout (e.g. layouts/panel.php) — before </body>:
 *   \GridKit\BelegModal::container();
 *
 *   // JS API, available everywhere:
 *   GK.belegModal.open('/invoices/123/pdf');
 *   GK.belegModal.open(url, { title: 'Rechnung 123', autoPrint: true });
 *   GK.belegModal.open(url, {
 *       unlinkExpenseId: 456,
 *       onUnlink: function() { location.reload(); }
 *   });
 *   GK.belegModal.close();
 *
 * Behaviour:
 *   - Desktop: the iframe loads the URL inline (browser PDF viewer or HTML preview).
 *   - Mobile (<= 768px): iframe hidden, the Lang::t('doc.open_pdf') button opens the native viewer.
 *   - Download + open in a new tab: both are reliable (target=_blank).
 *   - ESC closes the modal.
 *   - autoPrint: prints the iframe as soon as it has loaded.
 *   - unlinkExpenseId: shows the Lang::t('doc.unlink') button.
 *     Where it POSTs is yours to say — set GK.belegModal.unlinkUrl once, or
 *     pass unlinkUrl per call. With neither, the button fires a cancelable
 *     `gk:belegunlink` event and your code decides what detaching means.
 */
class BelegModal
{
    /**
     * Renders the modal container. Once per page (typically in the layout).
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
                    <span class="material-icons" aria-hidden="true">link_off</span>
                    <span class="gk-beleg-btn-label">{$t('doc.unlink')}</span>
                </button>
                <a data-gk-beleg-download href="#" download target="_blank" rel="noopener"
                   class="gk-btn gk-btn-outlined gk-btn-neutral gk-btn-sm" title="{$t('doc.download_title')}">
                    <span class="material-icons" aria-hidden="true">download</span>
                    <span class="gk-beleg-btn-label">{$t('doc.download')}</span>
                </a>
                <a data-gk-beleg-open href="#" target="_blank" rel="noopener"
                   class="gk-btn gk-btn-outlined gk-btn-primary gk-btn-sm" title="{$t('doc.open_tab_title')}">
                    <span class="material-icons" aria-hidden="true">open_in_new</span>
                    <span class="gk-beleg-btn-label">{$t('doc.open_tab')}</span>
                </a>
                <button type="button" data-gk-beleg-close
                        class="gk-btn gk-btn-text gk-btn-neutral gk-btn-sm gk-beleg-modal-close-btn"
                        title="{$t('doc.close')}">
                    <span class="material-icons" aria-hidden="true">close</span>
                </button>
            </div>
        </div>
        <div class="gk-modal-body gk-beleg-modal-body">
            <iframe data-gk-beleg-frame class="gk-beleg-modal-frame" src="about:blank" title="{$t('doc.preview')}"></iframe>
            <div class="gk-beleg-modal-mobile">
                <span class="material-icons gk-beleg-mobile-icon" aria-hidden="true">picture_as_pdf</span>
                <p>{$t('doc.mobile_hint')}</p>
                <a data-gk-beleg-mobile-open href="#" target="_blank" rel="noopener"
                   class="gk-btn gk-btn-filled gk-btn-primary">
                    <span class="material-icons" aria-hidden="true">open_in_new</span>
                    <span>{$t('doc.open_pdf')}</span>
                </a>
            </div>
        </div>
    </div>
</div>
HTML;
    }
}
