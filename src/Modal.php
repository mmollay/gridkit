<?php
namespace GridKit;

class Modal
{
    /**
     * @deprecated since 1.42.0 — emits nothing.
     *
     * This used to print an empty, hidden modal shell, and every layout was
     * told to call it once per page. Nothing ever read it: GK.modal.open()
     * builds its own overlay with document.createElement and appends it to
     * the body (js/gridkit.js, _createOverlay), so the server-rendered shell
     * sat in the DOM with an empty title and an empty body for the life of
     * the page — including a close button that a screen reader announced as
     * the multiplication sign.
     *
     * Kept as a no-op rather than removed, so the layouts that already call
     * it keep working untouched.
     */
    public static function container(): void
    {
    }
}
