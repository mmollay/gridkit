<?php
declare(strict_types=1);

namespace GridKit;

/**
 * Select — searchable dropdown helper.
 *
 * Renders a searchable select (gk-select-search) from a flat options array.
 * Init via GK.selectSearch.init() (auto-bound on page-load if no `id` collision).
 */
class Select
{
    /**
     * Searchable select dropdown with filter input.
     *
     * @param string $name      Name of the hidden input (also used as id if no `id` opt)
     * @param array  $options   Either ['value' => 'label', ...] OR list of ['value' => x, 'label' => y]
     * @param array  $opts      Options:
     *   - selected: mixed       Pre-selected value
     *   - placeholder: string   Display label when nothing selected (default: '— Wählen —')
     *   - searchPlaceholder: string  Placeholder for search input (default: 'Suchen…')
     *   - id: string            HTML id (default: $name)
     *   - class: string         Extra CSS classes on wrapper
     *   - required: bool        Adds required attribute
     */
    public static function searchable(string $name, array $options, array $opts = []): string
    {
        $id          = $opts['id']                ?? $name;
        $selected    = $opts['selected']          ?? '';
        $placeholder = $opts['placeholder']       ?? '— Wählen —';
        $searchPh    = $opts['searchPlaceholder'] ?? 'Suchen…';
        $extraClass  = $opts['class']             ?? '';
        $required    = !empty($opts['required']);

        $selectedStr = (string) $selected;
        $displayLabel = $placeholder;
        $optionsHtml = '';

        // Normalise options to [{value, label}, ...]
        $normalised = [];
        foreach ($options as $k => $v) {
            if (is_array($v) && isset($v['value'], $v['label'])) {
                $normalised[] = ['value' => (string) $v['value'], 'label' => (string) $v['label']];
            } else {
                $normalised[] = ['value' => (string) $k, 'label' => (string) $v];
            }
        }

        foreach ($normalised as $opt) {
            $isSel = $selectedStr !== '' && $opt['value'] === $selectedStr;
            if ($isSel) $displayLabel = $opt['label'];
            $cls = 'gk-select-option' . ($isSel ? ' selected' : '');
            $optionsHtml .= sprintf(
                '<div class="%s" data-value="%s">%s</div>',
                $cls,
                htmlspecialchars($opt['value'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($opt['label'], ENT_QUOTES, 'UTF-8'),
            );
        }

        $wrapClass = trim('gk-select-search ' . $extraClass);
        $reqAttr   = $required ? ' required' : '';
        $idAttr    = htmlspecialchars($id,       ENT_QUOTES, 'UTF-8');
        $nameAttr  = htmlspecialchars($name,     ENT_QUOTES, 'UTF-8');
        $valAttr   = htmlspecialchars($selectedStr, ENT_QUOTES, 'UTF-8');
        $dispLabel = htmlspecialchars($displayLabel, ENT_QUOTES, 'UTF-8');
        $searchPh2 = htmlspecialchars($searchPh, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<div class="{$wrapClass}" data-gk-select-search>
    <input type="hidden" id="{$idAttr}" name="{$nameAttr}" value="{$valAttr}"{$reqAttr}>
    <div class="gk-select-display" tabindex="0">
        <span class="gk-select-value">{$dispLabel}</span>
        <span class="material-icons gk-select-arrow">expand_more</span>
    </div>
    <div class="gk-select-dropdown">
        <div class="gk-select-search-input">
            <span class="material-icons">search</span>
            <input type="text" placeholder="{$searchPh2}" autocomplete="off">
        </div>
        <div class="gk-select-options">{$optionsHtml}</div>
    </div>
</div>
HTML;
    }
}
