<?php
declare(strict_types=1);

namespace GridKit;

/**
 * ImagePicker — choose one image out of many, in a compact grid (since v1.94.0).
 *
 * A list of images with a full row each is fine for three and unusable for
 * thirty. This is a grid of 16:9 tiles that works like a radio group, with an
 * enlarged view (the library's lightbox) that can look at a picture in detail
 * and pick it. Native radios underneath: the form, the arrow keys and the
 * screen reader work without a line of script; the script adds the fold ("show
 * all 30"), the roving tab stop and the viewer.
 *
 *   ImagePicker::render([
 *       ['value' => 'a.webp', 'title' => 'Fireplace at dusk', 'thumb' => '/t/a.webp', 'full' => '/g/a.webp',
 *        'meta' => 'Own image', 'checked' => true],
 *       ['value' => 'b.webp', 'title' => 'Mountain road', 'thumb' => '/t/b.webp', 'full' => '/g/b.webp',
 *        'meta' => 'AI generated', 'badge' => 'AI', 'actions' => [['name' => 'remove', 'label' => 'Remove']]],
 *       ['value' => 'c.webp', 'title' => 'Not released yet', 'note' => 'Waiting for approval', 'disabled' => true],
 *   ], ['name' => 'cover', 'label' => 'Header image']);
 *
 * Per image:
 *   - value      what the form submits (required)
 *   - title      one line on the tile, cut with an ellipsis; the whole of it is the tooltip and the name of the radio
 *   - thumb      the SMALL picture for the tile (omitted: a placeholder is drawn)
 *   - full       the LARGE picture the viewer loads when it opens (omitted: no zoom button, nothing for the viewer)
 *   - alt        the viewer's caption and the large picture's alt (default: the title)
 *   - meta       where the picture comes from ("AI generated") — a second line in the viewer, the radio's description
 *   - note       a line under the title on the tile ("Waiting for approval"), also in the radio's description
 *   - badge      a short word on the picture ("AI"); the meta says it in full for a screen reader
 *   - checked / disabled
 *   - actions    [['name' => 'remove', 'label' => 'Remove', 'icon' => 'delete']] — buttons in the caption row. The
 *                library only fires the event gk:pickeraction; the application decides (and asks "Really?").
 *   - data       ['x' => 'y'] — data-x="y" on the tile, for the application's own bookkeeping
 *
 * Options: name (required: the radio group), id (prefix of every id), label (names the group for a reader),
 * rows (rows shown before "show all N"; default 2, 0 = never fold), status (false: no "Selected: …" line),
 * class, labels (zoom, choose, more, less, selected, none — default: the active language).
 *
 * html() returns the markup, render() prints it. The pictures are the caller's URLs; sizing them (a 400 px
 * version for the tile, the original for the viewer) is the application's job — the library draws what it is given.
 */
class ImagePicker
{
    private const LABELS = [
        'zoom'     => 'picker.zoom',
        'choose'   => 'js.lightbox_choose',
        'more'     => 'picker.more',
        'less'     => 'picker.less',
        'selected' => 'picker.selected',
        'none'     => 'picker.none',
    ];

    public static function render(array $items, array $opts = []): void
    {
        echo self::html($items, $opts);
    }

    public static function html(array $items, array $opts = []): string
    {
        $o = self::resolve($opts);

        $checked = null;
        foreach ($items as $it) {
            if (!empty($it['checked'])) { $checked = (string) ($it['title'] ?? $it['value'] ?? ''); break; }
        }

        $out = '<div class="gk-picker' . ($o['class'] !== '' ? ' ' . self::e($o['class']) : '') . '" id="' . $o['id'] . '" data-gk-picker'
             . ' data-gk-picker-name="' . self::e($o['name']) . '" data-gk-picker-id="' . $o['id'] . '" data-gk-picker-rows="' . $o['rows'] . '"';
        foreach ($o['labels'] as $k => $v) {
            $out .= ' data-gk-picker-label-' . $k . '="' . self::e($v) . '"';
        }
        $out .= '>';

        if ($o['status']) {
            $out .= '<p class="gk-picker-status" data-gk-picker-status>'
                  . self::e($checked === null ? $o['labels']['none'] : str_replace('{name}', $checked, $o['labels']['selected'])) . '</p>';
        }

        $out .= '<div class="gk-picker-grid" role="group"' . ($o['label'] !== '' ? ' aria-label="' . self::e($o['label']) . '"' : '') . '>';
        foreach (array_values($items) as $i => $it) {
            $out .= self::tileHtml($it, ['index' => $i + 1] + $o);
        }
        $out .= '</div>';

        $out .= '<button type="button" class="gk-btn gk-btn-outlined gk-btn-neutral gk-btn-sm gk-picker-more" data-gk-picker-more-btn aria-expanded="false" hidden></button>';
        return $out . '</div>';
    }

    /** One tile. $opts: name and id as for html(), index (its number in the group, for the ids). */
    public static function tileHtml(array $item, array $opts): string
    {
        $o = self::resolve($opts);
        $n = $o['id'] . '-' . (int) ($opts['index'] ?? 1);
        $value = (string) ($item['value'] ?? '');
        $title = (string) ($item['title'] ?? '') !== '' ? (string) $item['title'] : $value;
        $thumb = (string) ($item['thumb'] ?? '');
        $full  = (string) ($item['full'] ?? '');
        $meta  = (string) ($item['meta'] ?? '');
        $note  = (string) ($item['note'] ?? '');
        $badge = (string) ($item['badge'] ?? '');
        $alt   = (string) ($item['alt'] ?? '') !== '' ? (string) $item['alt'] : $title;

        $describedBy = trim(($meta !== '' ? $n . '-d ' : '') . ($note !== '' ? $n . '-n' : ''));

        $attrs = '';
        if ($full !== '') {
            $attrs .= ' data-lightbox="' . self::e($full) . '" data-caption="' . self::e($alt) . '"'
                    . ($meta !== '' ? ' data-meta="' . self::e($meta) . '"' : '')
                    . ($note !== '' ? ' data-note="' . self::e($note) . '"' : '');
        }
        foreach ((array) ($item['data'] ?? []) as $k => $v) {
            $k = preg_replace('/[^a-z0-9-]/', '', strtolower((string) $k)) ?? '';
            if ($k !== '' && is_scalar($v)) { $attrs .= ' data-' . $k . '="' . self::e((string) $v) . '"'; }
        }

        $h = '<div class="gk-picker-item"' . $attrs . '>'
           . '<div class="gk-picker-media">'
           . '<input class="gk-picker-input" type="radio" id="' . $n . '" name="' . self::e($o['name']) . '" value="' . self::e($value) . '"'
           . ' aria-labelledby="' . $n . '-t"' . ($describedBy !== '' ? ' aria-describedby="' . $describedBy . '"' : '')
           . (!empty($item['checked']) ? ' checked' : '') . (!empty($item['disabled']) ? ' disabled' : '') . '>'
           . ($thumb !== ''
                ? '<img class="gk-picker-img" src="' . self::e($thumb) . '" alt="" loading="lazy" decoding="async">'
                : '<span class="gk-picker-ph" aria-hidden="true">' . Icon::svg('image', 16) . '</span>')
           . '<span class="gk-picker-check" aria-hidden="true">' . Icon::svg('check', 16) . '</span>'
           . ($badge !== '' ? '<span class="gk-picker-badge" aria-hidden="true">' . self::e($badge) . '</span>' : '');
        if ($full !== '') {
            $h .= '<button type="button" class="gk-picker-zoom" data-gk-picker-zoom aria-label="' . self::e($o['labels']['zoom'] . ': ' . $title)
                . '" title="' . self::e($o['labels']['zoom']) . '">' . Icon::svg('zoom_in', 16) . '</button>';
        }
        $h .= '</div>'
            . '<div class="gk-picker-caption">'
            . '<label class="gk-picker-title" id="' . $n . '-t" for="' . $n . '" title="' . self::e($title) . '">' . self::e($title) . '</label>';
        foreach ((array) ($item['actions'] ?? []) as $a) {
            $name = (string) ($a['name'] ?? '');
            $label = (string) ($a['label'] ?? $name);
            $h .= '<button type="button" class="gk-picker-action" data-gk-picker-action="' . self::e($name) . '" aria-label="' . self::e($label . ': ' . $title)
                . '" title="' . self::e($label) . '">' . Icon::svg((string) ($a['icon'] ?? 'delete'), 16) . '</button>';
        }
        $h .= '</div>'
            . ($note !== '' ? '<p class="gk-picker-note" id="' . $n . '-n">' . self::e($note) . '</p>' : '')
            . ($meta !== '' ? '<span class="gk-sr-only" id="' . $n . '-d">' . self::e($meta) . '</span>' : '')
            . '</div>';
        return $h;
    }

    /** @return array{name:string,id:string,rows:int,label:string,class:string,status:bool,labels:array<string,string>} */
    private static function resolve(array $opts): array
    {
        $name = (string) ($opts['name'] ?? '');
        if ($name === '') {
            throw new \InvalidArgumentException('ImagePicker needs a name: the radios of one picker are one group.');
        }
        $id = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($opts['id'] ?? '')) ?? '';
        if ($id === '') {
            $id = 'gk-picker-' . (preg_replace('/[^A-Za-z0-9_-]/', '-', $name) ?? 'x');
        }
        $labels = [];
        foreach (self::LABELS as $k => $key) {
            $given = $opts['labels'][$k] ?? null;
            $labels[$k] = is_string($given) && $given !== '' ? $given : Lang::t($key);
        }
        return [
            'name'   => $name,
            'id'     => $id,
            'rows'   => max(0, (int) ($opts['rows'] ?? 2)),
            'label'  => (string) ($opts['label'] ?? ''),
            'class'  => (string) ($opts['class'] ?? ''),
            'status' => ($opts['status'] ?? true) !== false,
            'labels' => $labels,
        ];
    }

    private static function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}
