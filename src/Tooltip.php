<?php
declare(strict_types=1);

namespace GridKit;

/**
 * Tooltip — the attributes and the help icon of GridKit's tooltips (since 1.112.0).
 *
 * Tooltips were markup only: `data-gk-tooltip="…"` typed by hand, with its options as more
 * attributes, escaped by whoever remembered to. These helpers return the same attributes,
 * escaped and checked, so a template writes `<span<?= Tooltip::attrs('Net, before VAT') ?>>`.
 * Button, Table and Form use them for their 'tooltip' and 'help' options.
 *
 *   Tooltip::attrs('Saves the draft', ['kbd' => 'Ctrl+S', 'pos' => 'bottom'])
 *       → ' data-gk-tooltip="Saves the draft" data-gk-tooltip-pos="bottom" data-gk-tooltip-kbd="Ctrl+S"'
 *   Tooltip::rich('#vat-help')                → ' data-gk-tooltip-rich="#vat-help"'
 *   Tooltip::toggletip('Opens on a click')    → ' data-gk-toggletip="Opens on a click" data-gk-tooltip="…"'
 *   Tooltip::help('Net amount before VAT', ['label' => 'Net'])
 *       → '<button type="button" class="gk-help" … aria-label="More about Net">…</button>'
 *
 * The first argument of attrs(), toggletip() and help() is the text, or an array that carries
 * the text under 'text' and its options beside it — the shape a component option takes:
 * `'tooltip' => 'Text'` or `'tooltip' => ['text' => 'Text', 'pos' => 'bottom']`.
 *
 * Options (all optional):
 *   pos    'top' (default), 'bottom', 'left', 'right' or 'auto' — the preferred side; the
 *          script flips and shifts the bubble when it does not fit
 *   tone   'neutral', 'info', 'success', 'warning', 'danger' or 'light'
 *   max    the widest the bubble gets: pixels as an int (240) or a CSS length ('20rem')
 *   kbd    a keyboard hint shown as <kbd> after the text ('Ctrl+S')
 *   delay  milliseconds before it opens on hover (0–10000; default 300)
 *   wrap   true: a multi-line bubble (the CSS-only form keeps it on one line otherwise)
 *   label  help() only: what the icon is about — "More about {label}" is its name
 *   icon   help() only: 'help' (a question mark, default) or 'info' (an i)
 *
 * A bad option is a warning (E_USER_WARNING) and is left out — the tooltip still shows,
 * the way a column with a bad 'priority' still shows. An empty text renders nothing.
 */
class Tooltip
{
    /** The sides a tooltip can prefer. 'auto': above or below, whichever has more room. */
    public const POSITIONS = ['top', 'bottom', 'left', 'right', 'auto'];

    /** The colour pairs, each measured at 4.5:1 or more in light and dark mode. */
    public const TONES = ['neutral', 'info', 'success', 'warning', 'danger', 'light'];

    /** What each helper takes; anything else is a warning. */
    private const TAKES = [
        'attrs'     => ['text', 'pos', 'tone', 'max', 'kbd', 'delay', 'wrap'],
        'rich'      => ['pos', 'tone', 'max', 'delay'],
        'toggletip' => ['text', 'pos', 'tone', 'max', 'kbd'],
        'help'      => ['text', 'pos', 'tone', 'max', 'kbd', 'label', 'icon'],
    ];

    /**
     * A plain tooltip on any element: hover after a delay, keyboard focus, a tap on touch.
     * Returns the attributes with a leading space, or '' when there is no text.
     *
     * Without js/gridkit.js 1.112.0 these are the CSS-only tooltip of old (hover, above the
     * element); with it the engine places, flips and announces them.
     */
    public static function attrs(mixed $tooltip, array $opts = []): string
    {
        [$text, $o] = self::split($tooltip, $opts, 'attrs');
        if ($text === '') return '';
        return ' data-gk-tooltip="' . self::e($text) . '"' . self::options($o, 'attrs');
    }

    /**
     * A rich tooltip: the element with that id is the bubble, HTML and all, and it describes
     * the trigger (aria-describedby). '#vat-help' and 'vat-help' are the same.
     */
    public static function rich(string $id, array $opts = []): string
    {
        $id = ltrim(trim($id), '#');
        if ($id === '' || !preg_match('/^[A-Za-z][\w:.-]*$/', $id)) {
            trigger_error("GridKit: Tooltip::rich() needs the id of the element that holds the tooltip, got '"
                . $id . "'. No tooltip is written.", E_USER_WARNING);
            return '';
        }
        self::check($opts, 'rich');
        return ' data-gk-tooltip-rich="#' . self::e($id) . '"' . self::options($opts, 'rich');
    }

    /**
     * A toggletip: opens on a click, Enter or Space (not on hover), stays open, may hold links
     * and buttons, closes on Escape, a click outside or a second click. Its content is a text,
     * or '#id' of an element on the page (hidden until it is shown). The text is read out by a
     * screen reader when it opens; the focus stays on the trigger.
     *
     * A text toggletip also carries data-gk-tooltip: on a page without the 1.112.0 script it is
     * the CSS-only tooltip on hover, not a button that does nothing.
     */
    public static function toggletip(mixed $content, array $opts = []): string
    {
        [$text, $o] = self::split($content, $opts, 'toggletip');
        if ($text === '') return '';
        $fallback = self::isRef($text) ? '' : ' data-gk-tooltip="' . self::e($text) . '"';
        return ' data-gk-toggletip="' . self::e($text) . '"' . $fallback . self::options($o, 'toggletip');
    }

    /**
     * The help icon: a 24px button (44px to a finger) with a question mark that opens its
     * toggletip. Its name comes from the language file — "More about {label}" with a 'label',
     * "More information" without — so a page with five of them does not read five identical
     * buttons. Returns '' when there is no text.
     */
    public static function help(mixed $content, array $opts = []): string
    {
        [$text, $o] = self::split($content, $opts, 'help');
        if ($text === '') return '';

        $label = isset($o['label']) && is_scalar($o['label']) ? trim((string) $o['label']) : '';
        $name = $label !== '' ? Lang::t('tooltip.help_about', ['label' => $label]) : Lang::t('tooltip.help');

        $icon = $o['icon'] ?? 'help';
        if (!in_array($icon, ['help', 'info'], true)) {
            self::warn("'icon' => " . self::shown($icon) . " — it takes 'help' or 'info'. The question mark is shown.");
            $icon = 'help';
        }
        // Unknown keys were reported by split(); only what a toggletip takes goes on, so
        // nothing is reported twice.
        $o = array_intersect_key($o, array_flip(self::TAKES['toggletip']));

        return '<button type="button" class="gk-help"' . self::toggletip(['text' => $text] + $o)
             . ' aria-label="' . self::e($name) . '">' . self::glyph($icon) . '</button>';
    }

    // ── internals ─────────────────────────────────────────────────────────

    /**
     * The text and the options of a call: a string with its options, or an array that carries
     * both. The array's own keys win over the second argument, which supplies defaults (a
     * Table column hands its label in there). A number is its digits; anything else — true, an
     * object — is a warning and no tooltip, not a TypeError from inside a component's render.
     *
     * @return array{0: string, 1: array}
     */
    private static function split(mixed $value, array $opts, string $what): array
    {
        if (!is_array($value) && !is_string($value) && !is_int($value) && !is_float($value)) {
            self::warn('a text of ' . self::shown($value) . ' — it takes a string, or an array with \'text\'. No tooltip is written.');
            $value = '';
        }
        if (is_array($value)) {
            $opts = $value + $opts;
            $text = $opts['text'] ?? null;
            if ($text !== null && !is_string($text) && !is_int($text) && !is_float($text)) {
                self::warn("'text' => " . self::shown($text) . ' — it takes a string. No tooltip is written.');
                $text = '';
            }
            if ($text === null) {
                self::warn('an array without \'text\' — the text goes under \'text\'. No tooltip is written.');
                $text = '';
            }
        } else {
            $text = $value;
        }
        unset($opts['text']);
        self::check($opts, $what);
        return [trim((string) $text), $opts];
    }

    /** Unknown keys: a warning each, so 'position' or 'color' does not vanish without a word. */
    private static function check(array $opts, string $what): void
    {
        foreach (array_keys($opts) as $key) {
            if (!in_array($key, self::TAKES[$what], true)) {
                self::warn("Tooltip::$what() has no option '$key' — it takes "
                    . implode(', ', self::TAKES[$what]) . '. The option is ignored.');
            }
        }
    }

    /** The option attributes, in one fixed order; each bad value is a warning and left out. */
    private static function options(array $o, string $what): string
    {
        $out = '';
        $takes = self::TAKES[$what];

        if (array_key_exists('pos', $o) && in_array('pos', $takes, true)) {
            $pos = $o['pos'];
            if (is_string($pos) && in_array($pos, self::POSITIONS, true)) {
                $out .= ' data-gk-tooltip-pos="' . $pos . '"';
            } else {
                self::warn("'pos' => " . self::shown($pos) . ' — it takes ' . implode(', ', self::POSITIONS) . '. It opens on top.');
            }
        }
        if (array_key_exists('tone', $o) && in_array('tone', $takes, true)) {
            $tone = $o['tone'];
            if (is_string($tone) && in_array($tone, self::TONES, true)) {
                $out .= ' data-gk-tooltip-tone="' . $tone . '"';
            } else {
                self::warn("'tone' => " . self::shown($tone) . ' — it takes ' . implode(', ', self::TONES) . '. The default colours are used.');
            }
        }
        if (array_key_exists('max', $o) && in_array('max', $takes, true)) {
            $max = $o['max'];
            // An int is pixels; a string is a CSS length in a unit that means something for a
            // bubble fixed to the window. Anything else — a negative, a calc(), a "wide" — is
            // dropped: it would reach a style property the script sets.
            if (is_int($max) && $max >= 40 && $max <= 2000) {
                $out .= ' data-gk-tooltip-max="' . $max . '"';
            } elseif (is_string($max) && preg_match('/^\d+(\.\d+)?(px|rem|em|ch|vw|%)$/', $max)) {
                $out .= ' data-gk-tooltip-max="' . $max . '"';
            } else {
                self::warn("'max' => " . self::shown($max) . " — it takes pixels from 40 to 2000 or a length like '20rem'. The default width is used.");
            }
        }
        if (array_key_exists('kbd', $o) && in_array('kbd', $takes, true)) {
            $kbd = $o['kbd'];
            if (is_string($kbd) && trim($kbd) !== '') {
                $out .= ' data-gk-tooltip-kbd="' . self::e(trim($kbd)) . '"';
            } elseif ($kbd !== null && $kbd !== '') {
                self::warn("'kbd' => " . self::shown($kbd) . " — it takes the keys as text, like 'Ctrl+S'. No hint is shown.");
            }
        }
        if (array_key_exists('delay', $o) && in_array('delay', $takes, true)) {
            $delay = $o['delay'];
            if (is_int($delay) && $delay >= 0 && $delay <= 10000) {
                $out .= ' data-gk-tooltip-delay="' . $delay . '"';
            } else {
                self::warn("'delay' => " . self::shown($delay) . ' — it takes milliseconds from 0 to 10000. It opens after 300.');
            }
        }
        if (array_key_exists('wrap', $o) && in_array('wrap', $takes, true)) {
            if ($o['wrap'] === true) {
                $out .= ' data-gk-tooltip-wrap';
            } elseif ($o['wrap'] !== false) {
                self::warn("'wrap' => " . self::shown($o['wrap']) . ' — it takes true or false. The text stays on one line.');
            }
        }
        return $out;
    }

    /** '#id' that names an element, not a text that happens to start with '#'. */
    private static function isRef(string $text): bool
    {
        return (bool) preg_match('/^#[A-Za-z][\w:.-]*$/', $text);
    }

    /**
     * The icon of help(): an outline circle with a question mark or an i, drawn in the text
     * colour. An SVG and not a font ligature, so it shows before (or without) a web font —
     * aria-hidden, the button's aria-label is its name.
     */
    private static function glyph(string $icon): string
    {
        $mark = $icon === 'info'
            ? '<path d="M12 16v-4"/><path d="M12 8h.01"/>'
            : '<path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/>';
        return '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"'
             . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
             . '<circle cx="12" cy="12" r="10"/>' . $mark . '</svg>';
    }

    private static function warn(string $message): void
    {
        trigger_error('GridKit: ' . (str_starts_with($message, 'Tooltip::') ? $message : 'a tooltip has ' . $message), E_USER_WARNING);
    }

    private static function shown(mixed $value): string
    {
        return is_scalar($value) || $value === null ? var_export($value, true) : get_debug_type($value);
    }

    private static function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}
