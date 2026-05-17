<?php
declare(strict_types=1);

namespace GridKit;

/**
 * Icon — zentrales SVG-Icon-Set für GRIDKit (since v1.17.0).
 *
 * Wird von Table::button() und Button::icon()/render() genutzt, damit Icons
 * überall einheitlich als Outline-SVG (stroke 2px, currentColor) gerendert
 * werden — nicht als gefüllte Material-Icons-Font.
 *
 * Fällt auf Material-Icons-Font zurück wenn der Name nicht im Mapping ist.
 */
class Icon
{
    /** True wenn für diesen Namen ein SVG verfügbar ist. */
    public static function has(string $name): bool
    {
        return self::svg($name, 16, /* fallback */ false) !== '';
    }

    /**
     * Rendert das Icon als SVG (falls im Mapping) oder als Material-Icons-Span.
     *
     * @param string $name      Icon-Name (z.B. 'edit', 'delete', 'add')
     * @param int    $size      Pixel-Größe (default 16)
     * @param bool   $fallback  Wenn kein SVG: Material-Icons-Span. Sonst leerer String.
     */
    public static function svg(string $name, int $size = 16, bool $fallback = true): string
    {
        $s = 'viewBox="0 0 24 24" width="' . $size . '" height="' . $size
           . '" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"';

        $svg = match ($name) {
            'pencil', 'edit'
                => '<svg ' . $s . '><path d="M17 3a2.85 2.85 0 0 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>',
            'trash', 'delete'
                => '<svg ' . $s . '><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6h14Z"/></svg>',
            'plus', 'add'
                => '<svg ' . $s . '><path d="M12 5v14M5 12h14"/></svg>',
            'check'
                => '<svg ' . $s . '><polyline points="20 6 9 17 4 12"/></svg>',
            'close', 'x'
                => '<svg ' . $s . '><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
            'eye', 'visibility'
                => '<svg ' . $s . '><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>',
            'download'
                => '<svg ' . $s . '><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>',
            'upload'
                => '<svg ' . $s . '><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"/></svg>',
            'copy', 'content_copy'
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
            default => '',
        };

        if ($svg !== '') return $svg;
        return $fallback
            ? '<span class="material-icons" style="font-size:' . $size . 'px">' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</span>'
            : '';
    }
}
