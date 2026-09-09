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
     * This is the ONLY renderer for the widget. Form's `select` field used to
     * build the same markup a second time, and the two drifted twice in ways
     * nobody saw until a screen reader met them: the options carried no
     * `role="option"` on one side, the combobox no `aria-controls` on the
     * other. Whatever this needs in order to serve a form field is an option
     * here rather than a reason to write it out again.
     *
     * @param string $name    Name of the value input (also its id, unless `id`)
     * @param array  $options Either ['value' => 'label', …] OR a list of
     *                        ['value' => x, 'label' => y]
     * @param array  $opts    Options:
     *   - selected: mixed       Pre-selected value
     *   - placeholder: string   Display label when nothing is selected
     *   - searchPlaceholder: string  Placeholder in the filter box
     *   - id: string            HTML id (default: $name)
     *   - class: string         Extra CSS classes on the wrapper
     *   - required: bool        Adds `required` to the value input
     *   - disabled: bool        Dims it and marks it inert for the browser code
     *   - search: bool|'auto'   Show the filter box. 'auto' shows it from seven
     *                           options on — below that it costs more room than
     *                           it saves. Default true.
     *   - aria: string          Accessible name, when there is no visible label
     *   - labelledby: string    Id of a visible label. Wins over `aria`: a name
     *                           a person can also read beats a duplicate string
     *   - describedby: string   Id of an error or hint element
     *   - invalid: bool         Marks it aria-invalid
     */
    public static function searchable(string $name, array $options, array $opts = []): string
    {
        $id          = (string) ($opts['id'] ?? $name);
        $selected    = $opts['selected'] ?? '';
        $placeholder = $opts['placeholder'] ?? Lang::t('select.placeholder');
        $searchPh    = $opts['searchPlaceholder'] ?? Lang::t('select.search');
        $extraClass  = (string) ($opts['class'] ?? '');
        $required    = !empty($opts['required']);
        $disabled    = !empty($opts['disabled']);
        $search      = $opts['search'] ?? true;

        $e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

        // Normalise options to [{value, label}, ...]
        $normalised = [];
        foreach ($options as $k => $v) {
            if (is_array($v) && isset($v['value'], $v['label'])) {
                $normalised[] = ['value' => (string) $v['value'], 'label' => (string) $v['label']];
            } else {
                $normalised[] = ['value' => (string) $k, 'label' => (string) $v];
            }
        }

        $selectedStr  = (string) $selected;
        $displayLabel = $placeholder;
        $optionsHtml  = '';
        foreach ($normalised as $opt) {
            $isSel = $selectedStr !== '' && $opt['value'] === $selectedStr;
            if ($isSel) $displayLabel = $opt['label'];
            // The container below declares role="listbox". A listbox whose
            // children are plain divs is worse than one with no role at all:
            // it is announced, and announced as empty, so the options simply
            // are not there.
            $optionsHtml .= '<div class="gk-select-option' . ($isSel ? ' selected' : '') . '"'
                . ' role="option" aria-selected="' . ($isSel ? 'true' : 'false') . '"'
                . ' data-value="' . $e($opt['value']) . '">' . $e($opt['label']) . '</div>';
        }

        // A name may contain characters an id may not — `tags[]` is a normal
        // field name — so the list id is derived, not copied.
        $listId = (string) ($opts['listId'] ?? preg_replace('/[^A-Za-z0-9_-]/', '-', $id) . '-list');

        $wrapClass = 'gk-select-search'
            . ($disabled ? ' gk-select-disabled' : '')
            . ($extraClass !== '' ? ' ' . $extraClass : '');

        // The widget is a <div> with tabindex="0": the markup claims it is a
        // control, so it has to answer like one. Without a name it announced
        // as "group" with nothing in it.
        if (!empty($opts['labelledby'])) {
            $labelAttr = ' aria-labelledby="' . $e($opts['labelledby']) . '"';
        } else {
            $ariaName = $opts['aria'] ?? $opts['label'] ?? $placeholder ?? '';
            $labelAttr = $ariaName !== '' ? ' aria-label="' . $e($ariaName) . '"' : '';
        }

        $describe = !empty($opts['describedby'])
            ? ' aria-describedby="' . $e($opts['describedby']) . '"'
            : '';
        $describe .= !empty($opts['invalid']) ? ' aria-invalid="true"' : '';

        $showSearch = $search === 'auto' ? count($normalised) > 6 : (bool) $search;
        $searchBox = $showSearch
            ? '<div class="gk-select-search-input"><span class="material-icons" aria-hidden="true">search</span>'
              . '<input type="text" placeholder="' . $e($searchPh) . '" autocomplete="off"></div>'
            : '';

        return '<div class="' . $wrapClass . '" data-gk-select-search' . ($disabled ? ' data-disabled' : '') . '>'
            // The value carrier must be a control the browser will validate.
            // type="hidden" is barred from constraint validation, so
            // ['required' => true] printed the red star beside the label and
            // did nothing else — the form submitted empty.
            . '<input type="text" class="gk-select-value-input" tabindex="-1" aria-hidden="true"'
            . ' name="' . $e($name) . '" id="' . $e($id) . '" value="' . $e($selectedStr) . '"'
            . ($required ? ' required' : '') . $describe . '>'
            // $describe here as well: this is the element a keyboard lands on,
            // so it is the one that has to carry the error. The value carrier
            // behind it is aria-hidden.
            . '<div class="gk-select-display" tabindex="0" role="combobox" aria-haspopup="listbox"'
            . ' aria-expanded="false" aria-controls="' . $e($listId) . '"' . $labelAttr . $describe . '>'
            . '<span class="gk-select-value">' . $e($displayLabel) . '</span>'
            . '<span class="material-icons gk-select-arrow" aria-hidden="true">expand_more</span>'
            . '</div>'
            . '<div class="gk-select-dropdown">' . $searchBox
            . '<div class="gk-select-options" id="' . $e($listId) . '" role="listbox">' . $optionsHtml . '</div>'
            . '</div></div>';
    }
}
