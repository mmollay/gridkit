<?php
declare(strict_types=1);

namespace GridKit;

/**
 * Icon — central SVG icon set for GridKit (since v1.17.0).
 *
 * Used by Table::button() and Button::icon()/render() so that icons are
 * rendered uniformly everywhere as outline SVG (stroke 2px, currentColor)
 * — not as the filled Material Icons font.
 *
 * Falls back to the Material Icons font when the name is not in the mapping.
 */
class Icon
{
    /** Font-fallback sizes that have a class gk-icon-s<px> in css/gridkit.css. */
    public const SIZES = [12, 14, 16, 18, 20, 24, 28, 32, 36, 40, 48];

    /** True when an SVG is available for this name. */
    public static function has(string $name): bool
    {
        return self::svg($name, 16, /* fallback */ false) !== '';
    }

    /**
     * Renders the icon as SVG (if present in the mapping) or as a Material Icons span.
     *
     * @param string $name      Icon name (e.g. 'edit', 'delete', 'add')
     * @param int    $size      Pixel size (default 16)
     * @param bool   $fallback  When true and no SVG exists: a Material Icons span. When false: an empty string.
     */
    public static function svg(string $name, int $size = 16, bool $fallback = true): string
    {
        $s = 'viewBox="0 0 24 24" width="' . $size . '" height="' . $size
           . '" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"';

        $svg = match ($name) {
            'pencil', 'edit'
                => '<svg ' . $s . '><path d="M17 3a2.85 2.85 0 0 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>',
            'trash', 'delete', 'delete_outline'
                => '<svg ' . $s . '><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6h14Z"/></svg>',
            'plus', 'add'
                => '<svg ' . $s . '><path d="M12 5v14M5 12h14"/></svg>',
            'check'
                => '<svg ' . $s . '><polyline points="20 6 9 17 4 12"/></svg>',
            'lock'
                => '<svg ' . $s . '><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>',
            'close', 'x'
                => '<svg ' . $s . '><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
            'eye', 'visibility'
                => '<svg ' . $s . '><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>',
            'download'
                => '<svg ' . $s . '><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>',
            'upload'
                => '<svg ' . $s . '><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"/></svg>',
            'copy', 'content_copy', 'file_copy'
                => '<svg ' . $s . '><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>',
            'mail', 'email'
                => '<svg ' . $s . '><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>',
            'search'
                => '<svg ' . $s . '><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>',
            'settings'
                => '<svg ' . $s . '><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
            'open_in_new', 'external'
                => '<svg ' . $s . '><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15,3 21,3 21,9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>',
            'auto_awesome', 'generate', 'wand'
                => '<svg ' . $s . '><path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/><path d="M5 3v4M19 17v4M3 5h4M17 19h4"/></svg>',
            'login', 'impersonate'
                => '<svg ' . $s . '><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10,17 15,12 10,7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>',
            'print'
                => '<svg ' . $s . '><polyline points="6,9 6,2 18,2 18,9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>',
            'arrow_back', 'arrow_left'
                => '<svg ' . $s . '><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>',
            'send'
                => '<svg ' . $s . '><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>',
            'lock_open', 'unlock'
                => '<svg ' . $s . '><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/></svg>',
            'attach_file', 'paperclip'
                => '<svg ' . $s . '><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>',
            'link_off'
                => '<svg ' . $s . '><path d="M9 17H7A5 5 0 0 1 7 7"/><path d="M15 7h2a5 5 0 0 1 4 8"/><line x1="8" y1="12" x2="12" y2="12"/><line x1="2" y1="2" x2="22" y2="22"/></svg>',
            'refresh'
                => '<svg ' . $s . '><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>',
            'zoom_in', 'zoom'
                => '<svg ' . $s . '><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/></svg>',
            'image', 'photo'
                => '<svg ' . $s . '><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>',
            'account_balance_wallet', 'wallet'
                => '<svg ' . $s . '><path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/><path d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4"/></svg>',
            'account_tree', 'network'
                => '<svg ' . $s . '><rect x="16" y="16" width="6" height="6" rx="1"/><rect x="2" y="16" width="6" height="6" rx="1"/><rect x="9" y="2" width="6" height="6" rx="1"/><path d="M5 16v-3a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v3M12 12V8"/></svg>',
            'add_circle', 'plus_circle'
                => '<svg ' . $s . '><circle cx="12" cy="12" r="10"/><path d="M12 8v8M8 12h8"/></svg>',
            'analytics', 'bar_chart'
                => '<svg ' . $s . '><path d="M18 20V10M12 20V4M6 20v-6"/></svg>',
            'archive'
                => '<svg ' . $s . '><rect x="2" y="3" width="20" height="5" rx="1"/><path d="M4 8v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8M10 12h4"/></svg>',
            'unarchive'
                => '<svg ' . $s . '><rect x="2" y="3" width="20" height="5" rx="1"/><path d="M4 8v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8M12 18v-6M9 15l3-3 3 3"/></svg>',
            'autorenew', 'sync'
                => '<svg ' . $s . '><path d="M21 12a9 9 0 0 1-15.5 6.2L3 16M3 12a9 9 0 0 1 15.5-6.2L21 8"/><path d="M21 3v5h-5M3 21v-5h5"/></svg>',
            'balance', 'scale'
                => '<svg ' . $s . '><path d="M12 3v18M7 21h10M3 7h2c2 0 5-1 7-2 2 1 5 2 7 2h2"/><path d="m2 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1ZM16 16l3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"/></svg>',
            'cancel', 'x_circle'
                => '<svg ' . $s . '><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6M9 9l6 6"/></svg>',
            'check_circle', 'task_alt'
                => '<svg ' . $s . '><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
            'construction', 'build'
                => '<svg ' . $s . '><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>',
            'event_available'
                => '<svg ' . $s . '><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/><polyline points="9 16 11 18 15 14"/></svg>',
            'event_busy'
                => '<svg ' . $s . '><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M10 14l4 4M14 14l-4 4"/></svg>',
            'groups', 'people', 'users'
                => '<svg ' . $s . '><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
            'help_outline', 'help'
                => '<svg ' . $s . '><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3M12 17h.01"/></svg>',
            'history'
                => '<svg ' . $s . '><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5M12 7v5l4 2"/></svg>',
            'how_to_reg', 'user_check'
                => '<svg ' . $s . '><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><polyline points="17 11 19 13 23 9"/></svg>',
            'info'
                => '<svg ' . $s . '><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>',
            'insights', 'trending_up'
                => '<svg ' . $s . '><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>',
            'link'
                => '<svg ' . $s . '><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>',
            'mark_email_read', 'mail_check'
                => '<svg ' . $s . '><path d="M22 13V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v12c0 1.1.9 2 2 2h8"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7M16 19l2 2 4-4"/></svg>',
            'unsubscribe', 'mail_x'
                => '<svg ' . $s . '><path d="M22 13V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v12c0 1.1.9 2 2 2h9"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7M17 17l4 4M21 17l-4 4"/></svg>',
            'more_horiz', 'more'
                => '<svg ' . $s . '><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>',
            'movie_creation', 'movie', 'film'
                => '<svg ' . $s . '><rect x="2" y="2" width="20" height="20" rx="2.18"/><path d="M7 2v20M17 2v20M2 12h20M2 7h5M2 17h5M17 17h5M17 7h5"/></svg>',
            'music_note', 'music'
                => '<svg ' . $s . '><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>',
            'notification_important', 'notifications', 'bell'
                => '<svg ' . $s . '><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 0 1-3.46 0"/></svg>',
            'payments', 'banknote'
                => '<svg ' . $s . '><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/></svg>',
            'picture_as_pdf', 'description', 'file_text'
                => '<svg ' . $s . '><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><path d="M16 13H8M16 17H8M10 9H8"/></svg>',
            'play_circle'
                => '<svg ' . $s . '><circle cx="12" cy="12" r="10"/><polygon points="10 8 16 12 10 16 10 8"/></svg>',
            'receipt_long', 'receipt'
                => '<svg ' . $s . '><path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8M12 17.5v-11"/></svg>',
            'record_voice_over', 'mic'
                => '<svg ' . $s . '><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2M12 19v4M8 23h8"/></svg>',
            'restart_alt', 'rotate_ccw'
                => '<svg ' . $s . '><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>',
            'schedule', 'clock'
                => '<svg ' . $s . '><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
            'skip_next'
                => '<svg ' . $s . '><polygon points="5 4 15 12 5 20 5 4"/><path d="M19 5v14"/></svg>',
            'smart_toy', 'bot'
                => '<svg ' . $s . '><rect x="3" y="11" width="18" height="10" rx="2"/><circle cx="12" cy="5" r="2"/><path d="M12 7v4M8 16h.01M16 16h.01"/></svg>',
            'swap_horiz'
                => '<svg ' . $s . '><path d="M8 3 4 7l4 4M4 7h16M16 21l4-4-4-4M20 17H4"/></svg>',
            'tune', 'sliders'
                => '<svg ' . $s . '><path d="M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M1 14h6M9 8h6M17 16h6"/></svg>',
            'undo'
                => '<svg ' . $s . '><polyline points="9 14 4 9 9 4"/><path d="M20 20v-7a4 4 0 0 0-4-4H4"/></svg>',
            'visibility_off', 'eye_off'
                => '<svg ' . $s . '><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><path d="M1 1l22 22"/></svg>',
            'web', 'language', 'public', 'globe'
                => '<svg ' . $s . '><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>',
            default => '',
        };

        if ($svg !== '') return $svg;
        if (!$fallback) return '';
        // The size is a class (gk-icon-s<px>, css/gridkit.css) — no inline style (1.129.0). A size
        // outside SIZES still gets its value, as before.
        $sizing = in_array($size, self::SIZES, true) ? ' gk-icon-s' . $size . '"' : '" style="font-size:' . $size . 'px"';
        return '<span class="material-icons' . $sizing . ' aria-hidden="true">' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</span>';
    }
}
