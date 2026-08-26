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
     *   - placeholder: string   Display label when nothing selected (default: translated)
     *   - searchPlaceholder: string  Placeholder for search input (default: translated)
     *   - id: string            HTML id (default: $name)
     *   - class: string         Extra CSS classes on wrapper
     *   - required: bool        Adds required attribute
     */
    public static function searchable(string $name, array $options, array $opts = []): string
    {
        $id          = $opts['id']                ?? $name;
        $selected    = $opts['selected']          ?? '';
        $placeholder = $opts['placeholder']       ?? Lang::t('select.placeholder');
        $searchPh    = $opts['searchPlaceholder'] ?? Lang::t('select.search');
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

        // The widget is a <div> with tabindex="0": the markup claims it is a
        // control, so it has to answer like one. Without a name it announced
        // as "group" with nothing in it; role and aria-expanded tell a screen
        // reader what it does and whether it is open.
        $ariaName  = $opts['aria'] ?? $opts['label'] ?? $placeholder ?? '';
        $labelAttr = $ariaName !== ''
            ? ' aria-label="' . htmlspecialchars((string) $ariaName, ENT_QUOTES, 'UTF-8') . '"'
            : '';

        return <<<HTML
<div class="{$wrapClass}" data-gk-select-search>
    <input type="text" class="gk-select-value-input" tabindex="-1" aria-hidden="true" id="{$idAttr}" name="{$nameAttr}" value="{$valAttr}"{$reqAttr}>
    <div class="gk-select-display" tabindex="0" role="combobox" aria-expanded="false" aria-haspopup="listbox" aria-controls="{$idAttr}-list"{$labelAttr}>
        <span class="gk-select-value">{$dispLabel}</span>
        <span class="material-icons gk-select-arrow" aria-hidden="true">expand_more</span>
    </div>
    <div class="gk-select-dropdown">
        <div class="gk-select-search-input">
            <span class="material-icons" aria-hidden="true">search</span>
            <input type="text" placeholder="{$searchPh2}" autocomplete="off">
        </div>
        <div class="gk-select-options" id="{$idAttr}-list" role="listbox">{$optionsHtml}</div>
    </div>
</div>
HTML;
    }
}
