<?php
namespace GridKit;

/**
 * ActionGroup — container for action buttons in table columns.
 *
 * Usage:
 *
 *   ActionGroup::render([
 *       ['icon' => 'edit',   'onclick' => "editRow($id)",   'title' => 'Bearbeiten'],
 *       ['icon' => 'delete', 'onclick' => "delRow($id)",    'title' => 'Löschen', 'color' => 'danger'],
 *       ['icon' => 'send',   'label' => 'Mahnen',           'color' => 'warning', 'variant' => 'filled', 'pill' => true,
 *        'onclick' => "remind($id)", 'showIf' => $isOverdue],
 *   ]);
 *
 * Per action item (all optional except either `icon` or `label`):
 *   - icon       Material Icon name
 *   - label      Button text (if absent: icon-only)
 *   - href       instead of onclick — renders as <a>
 *   - onclick    JS code
 *   - title      Tooltip
 *   - variant    filled | outlined | tonal | text (default: text if icon only, otherwise outlined)
 *   - color      primary | success | danger | warning | neutral (default: neutral)
 *   - size       xs | sm | md | lg (default: sm for table actions)
 *   - pill       true → border-radius:999px (badge style)
 *   - disabled   true → disabled class + disabled attribute
 *   - showIf     falsy → item is not rendered (for conditional actions)
 *
 * All CSS classes come from the existing gk-btn system — nothing home-grown.
 */
class ActionGroup
{
    public static function render(array $actions, array $opts = []): void
    {
        echo self::html($actions, $opts);
    }

    public static function html(array $actions, array $opts = []): string
    {
        $extraClass = isset($opts['class']) ? ' ' . $opts['class'] : '';
        $out = '<div class="gk-action-group' . $extraClass . '">';
        foreach ($actions as $a) {
            if (array_key_exists('showIf', $a) && !$a['showIf']) continue;
            $out .= Button::render($a['label'] ?? '', self::mapOpts($a));
        }
        $out .= '</div>';
        return $out;
    }

    private static function mapOpts(array $a): array
    {
        $hasLabel = !empty($a['label']);
        $hasIcon  = !empty($a['icon']);
        $size     = $a['size']    ?? 'sm'; // v1.17.0: default sm (= 26x26 with 16px SVG icon, like Table::button)
        $variant  = $a['variant'] ?? (!$hasLabel ? 'text' : 'outlined');
        $color    = $a['color']   ?? 'neutral';

        $opts = [
            'variant' => $variant,
            'color'   => $color,
            'size'    => $size,
        ];
        if ($hasIcon)             $opts['icon']     = $a['icon'];
        if (!empty($a['href']))   $opts['href']     = $a['href'];
        if (!empty($a['onclick']))$opts['onclick']  = $a['onclick'];
        if (!empty($a['title']))  $opts['title']    = $a['title'];
        if (!empty($a['disabled']))$opts['disabled']= true;

        // Pill modifier (border-radius:999px)
        if (!empty($a['pill'])) {
            $opts['class'] = trim(($opts['class'] ?? '') . ' gk-btn-pill');
        }
        if (!empty($a['class'])) {
            $opts['class'] = trim(($opts['class'] ?? '') . ' ' . $a['class']);
        }

        return $opts;
    }
}
