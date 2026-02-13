<?php
namespace GridKit;

class Modal
{
    public static function container(): void
    {
        echo '<div class="gk-modal-overlay" data-gk-modal-overlay style="display:none">
            <div class="gk-modal" data-gk-modal-container>
                <div class="gk-modal-header">
                    <h3 class="gk-modal-title" data-gk-modal-title-el></h3>
                    <button class="gk-modal-close" data-gk-modal-close>&times;</button>
                </div>
                <div class="gk-modal-body" data-gk-modal-body></div>
            </div>
        </div>';
    }
}
