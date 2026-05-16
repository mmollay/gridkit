<?php
namespace GridKit;

/**
 * ActionGroup — Container für Action-Buttons in Tabellen-Spalten.
 *
 * Verwendung:
 *
 *   ActionGroup::render([
 *       ['icon' => 'edit',   'onclick' => "editRow($id)",   'title' => 'Bearbeiten'],
 *       ['icon' => 'delete', 'onclick' => "delRow($id)",    'title' => 'Löschen', 'color' => 'danger'],
 *       ['icon' => 'send',   'label' => 'Mahnen',           'color' => 'warning', 'variant' => 'filled', 'pill' => true,
 *        'onclick' => "remind($id)", 'showIf' => $isOverdue],
 *   ]);
 *
 * Pro Action-Item (alle optional ausser entweder `icon` oder `label`):
 *   - icon       Material-Icon-Name
 *   - label      Button-Text (wenn fehlt: icon-only)
 *   - href       statt onclick — rendert als <a>
 *   - onclick    JS-Code
 *   - title      Tooltip
 *   - variant    filled | outlined | tonal | text (default: text wenn nur icon, sonst outlined)
 *   - color      primary | success | danger | warning | neutral (default: neutral)
 *   - size       xs | sm | md | lg (default: xs für Tabellen-Aktionen)
 *   - pill       true → border-radius:999px (Badge-Style)
 *   - disabled   true → disabled-Klasse + disabled-Attribut
 *   - showIf     falsy → Item wird nicht gerendert (für conditional actions)
 *
 * Alle CSS-Klassen kommen aus dem bestehenden gk-btn-System — kein Eigenbau.
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
        $size     = $a['size']    ?? 'xs';
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

        // Pill-Modifier (border-radius:999px)
        if (!empty($a['pill'])) {
            $opts['class'] = trim(($opts['class'] ?? '') . ' gk-btn-pill');
        }
        if (!empty($a['class'])) {
            $opts['class'] = trim(($opts['class'] ?? '') . ' ' . $a['class']);
        }

        return $opts;
    }
}
