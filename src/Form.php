<?php
namespace GridKit;

use GridKit\Button;

class Form
{
    private string $id;
    private string $action = '';
    private bool $isAjax = false;
    private array $fields = [];
    private bool $inRow = false;
    private string $submitLabel = '';
    private bool $wrapCard = false;

    public function __construct(string $id)
    {
        $this->id = $id;
    }

    public function action(string $url): static { $this->action = $url; return $this; }
    public function ajax(): static { $this->isAjax = true; return $this; }
    public function card(bool $wrap = true): static { $this->wrapCard = $wrap; return $this; }
    public function submit(string $label): static { $this->submitLabel = $label; return $this; }

    public function hidden(string $name, mixed $value): static
    {
        $this->fields[] = ['type' => 'hidden', 'name' => $name, 'value' => $value];
        return $this;
    }

    public function row(int $gap = 16): static
    {
        $this->fields[] = ['type' => '_row_start', 'gap' => $gap];
        $this->inRow = true;
        return $this;
    }

    public function endRow(): static
    {
        $this->fields[] = ['type' => '_row_end'];
        $this->inRow = false;
        return $this;
    }

    public function field(string $name, string $label, string $type, array $opts = []): static
    {
        $this->fields[] = ['type' => $type, 'name' => $name, 'label' => $label, ...$opts];
        return $this;
    }

    public function render(): void
    {
        $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        $attrs = 'class="gk-form" id="' . $e($this->id) . '"';
        if ($this->action) $attrs .= ' action="' . $e($this->action) . '"';
        if ($this->isAjax) $attrs .= ' data-gk-ajax';

        if ($this->wrapCard) echo '<div class="gk-card" style="padding:24px;">';
        echo "<form {$attrs}>";

        foreach ($this->fields as $f) {
            match ($f['type']) {
                'hidden' => printf('<input type="hidden" name="%s" value="%s">', $e($f['name']), $e($f['value'])),
                '_row_start' => $this->renderRowStart($f),
                '_row_end' => print('</div>'),
                default => $this->renderField($f),
            };
        }

        if ($this->submitLabel) {
            echo '<div class="gk-form-actions">' . Button::render($this->submitLabel, ['variant' => 'filled', 'color' => 'success', 'type' => 'submit', 'icon' => 'save']) . '</div>';
        }

        echo '</form>';
        if ($this->wrapCard) echo '</div>';
    }

    private function renderRowStart(array $f): void
    {
        $gap = $f['gap'] ?? 16;
        $style = $gap !== 16 ? " style=\"gap:{$gap}px\"" : '';
        echo "<div class=\"gk-form-row\"{$style}>";
    }

    private function renderField(array $f): void
    {
        $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        $name = $f['name'];
        $label = $f['label'] ?? '';
        $type = $f['type'];
        $value = $f['value'] ?? '';
        $req = ($f['required'] ?? false) ? ' required' : '';
        $inline = !empty($f['inline']);
        $width = $f['width'] ?? 16;

        // Build column class
        $colClass = '';
        $colStyle = '';
        if (is_numeric($width)) {
            $colClass = "gk-form-col-{$width}";
        } elseif ($width === 'auto') {
            $colClass = 'gk-form-col-auto';
        } elseif (is_string($width) && preg_match('/^\d+px$/', $width)) {
            $colStyle = " style=\"grid-column:span 1;width:{$width}\"";
            $colClass = '';
        }

        $inlineClass = $inline ? ' gk-field-inline' : '';
        $cls = "gk-field {$colClass}{$inlineClass}";

        echo "<div class=\"{$cls}\"{$colStyle}>";

        // Label (not for checkbox which has label integrated)
        $showLabel = $label && !in_array($type, ['checkbox']);
        if ($showLabel) {
            echo '<label class="gk-label-text" for="' . $e($name) . '">' . $e($label);
            if ($req) echo ' <span class="gk-required">*</span>';
            echo '</label>';
        }

        echo '<div class="gk-input-wrap">';

        switch ($type) {
            case 'textarea':
                $rows = $f['rows'] ?? 3;
                echo "<textarea name=\"{$e($name)}\" id=\"{$e($name)}\" rows=\"{$rows}\" class=\"gk-input\"{$req}>{$e($value)}</textarea>";
                break;

            case 'select':
                if (!empty($f['searchable'])) {
                    $options = $f['options'] ?? [];
                    $placeholder = $f['placeholder'] ?? 'Suchen...';
                    $displayValue = isset($options[$value]) ? $options[$value] : ($placeholder);
                    echo '<div class="gk-select-search" data-gk-select-search>';
                    echo "<input type=\"hidden\" name=\"{$e($name)}\" value=\"{$e($value)}\">";
                    echo '<div class="gk-select-display" tabindex="0">';
                    echo '<span class="gk-select-value">' . $e($displayValue) . '</span>';
                    echo '<span class="material-icons gk-select-arrow">expand_more</span>';
                    echo '</div>';
                    echo '<div class="gk-select-dropdown">';
                    echo '<div class="gk-select-search-input"><span class="material-icons">search</span>';
                    echo "<input type=\"text\" placeholder=\"{$e($placeholder)}\" autocomplete=\"off\">";
                    echo '</div><div class="gk-select-options">';
                    foreach ($options as $k => $v) {
                        $sel = (string)$k === (string)$value ? ' selected' : '';
                        echo "<div class=\"gk-select-option{$sel}\" data-value=\"{$e($k)}\">{$e($v)}</div>";
                    }
                    echo '</div></div></div>';
                } else {
                    echo "<select name=\"{$e($name)}\" id=\"{$e($name)}\" class=\"gk-input\"{$req}>";
                    foreach ($f['options'] ?? [] as $k => $v) {
                        $sel = (string)$k === (string)$value ? ' selected' : '';
                        echo "<option value=\"{$e($k)}\"{$sel}>{$e($v)}</option>";
                    }
                    echo '</select>';
                }
                break;

            case 'multiselect':
                $options = $f['options'] ?? [];
                $selectedValues = is_array($value) ? $value : ($value ? explode(',', $value) : []);
                $placeholder = $f['placeholder'] ?? 'Suchen...';
                $searchable = !empty($f['searchable']);
                echo '<div class="gk-multiselect" data-gk-multiselect>';
                echo "<input type=\"hidden\" name=\"{$e($name)}\" value=\"{$e(implode(',', $selectedValues))}\">";
                echo '<div class="gk-multiselect-display" tabindex="0">';
                echo '<div class="gk-multiselect-chips">';
                foreach ($selectedValues as $sv) {
                    if (isset($options[$sv])) {
                        echo "<span class=\"gk-chip-selected\" data-value=\"{$e($sv)}\">{$e($options[$sv])} <button type=\"button\" class=\"gk-chip-remove\">&times;</button></span>";
                    }
                }
                if ($searchable) {
                    echo "<input type=\"text\" class=\"gk-multiselect-input\" placeholder=\"{$e($placeholder)}\" autocomplete=\"off\">";
                }
                echo '</div>';
                echo '<span class="material-icons gk-select-arrow">expand_more</span>';
                echo '</div>';
                echo '<div class="gk-select-dropdown"><div class="gk-select-options">';
                foreach ($options as $k => $v) {
                    $sel = in_array((string)$k, array_map('strval', $selectedValues)) ? ' selected' : '';
                    $check = $sel ? '<span class="material-icons" style="font-size:16px;">check</span> ' : '';
                    echo "<div class=\"gk-select-option{$sel}\" data-value=\"{$e($k)}\">{$check}{$e($v)}</div>";
                }
                echo '</div></div></div>';
                break;

            case 'ajaxselect':
                $url = $f['url'] ?? '';
                $displayValue = $f['displayValue'] ?? '';
                $placeholder = $f['placeholder'] ?? 'Suchen...';
                $labelField = $f['labelField'] ?? 'name';
                $valueField = $f['valueField'] ?? 'id';
                $subtextField = $f['subtextField'] ?? '';
                $minChars = $f['minChars'] ?? 2;
                $searchParam = $f['searchParam'] ?? 'q';
                echo "<div class=\"gk-ajax-select\" data-gk-ajax-select data-url=\"{$e($url)}\" data-label-field=\"{$e($labelField)}\" data-value-field=\"{$e($valueField)}\" data-subtext-field=\"{$e($subtextField)}\" data-min-chars=\"{$e($minChars)}\" data-search-param=\"{$e($searchParam)}\">";
                echo "<input type=\"hidden\" name=\"{$e($name)}\" value=\"{$e($value)}\">";
                echo '<div class="gk-select-display" tabindex="0">';
                echo '<span class="material-icons gk-select-icon">search</span>';
                $clearStyle = $value ? '' : ' style="display:none;"';
                echo "<input type=\"text\" class=\"gk-ajax-search-input\" value=\"{$e($displayValue)}\" placeholder=\"{$e($placeholder)}\" autocomplete=\"off\">";
                echo "<button type=\"button\" class=\"gk-ajax-clear\"{$clearStyle}>&times;</button>";
                echo '</div>';
                echo '<div class="gk-select-dropdown"><div class="gk-select-options"></div>';
                echo '<div class="gk-select-loading" style="display:none;"><span class="material-icons gk-spin">sync</span> Suche...</div>';
                echo '</div></div>';
                break;

            case 'toggle':
                $checked = !empty($f['checked']) || !empty($value) ? ' checked' : '';
                echo "<label class=\"gk-toggle\"><input type=\"checkbox\" name=\"{$e($name)}\" id=\"{$e($name)}\" value=\"1\"{$checked}><span class=\"gk-toggle-slider\"></span></label>";
                break;

            case 'checkbox':
                $checked = !empty($f['checked']) || !empty($value) ? ' checked' : '';
                echo "<label class=\"gk-checkbox-wrap\"><input type=\"checkbox\" name=\"{$e($name)}\" id=\"{$e($name)}\" value=\"1\"{$req}{$checked}><span class=\"gk-checkbox-custom\"></span><span class=\"gk-checkbox-text\">{$e($label)}</span></label>";
                break;

            case 'radio':
                $options = $f['options'] ?? [];
                $isInline = !empty($f['inline']);
                $dirClass = $isInline ? 'gk-radio-group-inline' : 'gk-radio-group';
                echo "<div class=\"{$dirClass}\">";
                foreach ($options as $k => $v) {
                    $chk = (string)$k === (string)$value ? ' checked' : '';
                    echo "<label class=\"gk-radio-wrap\"><input type=\"radio\" name=\"{$e($name)}\" value=\"{$e($k)}\"{$chk}><span class=\"gk-radio-custom\"></span><span class=\"gk-radio-text\">{$e($v)}</span></label>";
                }
                echo '</div>';
                break;

            case 'range':
                $min = $f['min'] ?? 0;
                $max = $f['max'] ?? 100;
                $step = $f['step'] ?? 1;
                $val = $value !== '' ? $value : $min;
                echo "<div class=\"gk-range-wrap\">";
                echo "<input type=\"range\" name=\"{$e($name)}\" id=\"{$e($name)}\" class=\"gk-range\" min=\"{$e($min)}\" max=\"{$e($max)}\" step=\"{$e($step)}\" value=\"{$e($val)}\">";
                echo "<output class=\"gk-range-value\" for=\"{$e($name)}\">{$e($val)}</output>";
                echo "</div>";
                break;

            case 'file':
                $accept = isset($f['accept']) ? " accept=\"{$e($f['accept'])}\"" : '';
                $multiple = !empty($f['multiple']) ? ' multiple' : '';
                $maxSize = $f['maxSize'] ?? '';
                echo "<div class=\"gk-upload-zone\" data-max-size=\"{$e($maxSize)}\">";
                echo "<input type=\"file\" name=\"{$e($name)}\" id=\"{$e($name)}\" class=\"gk-upload-input\"{$accept}{$multiple}{$req}>";
                echo "<div class=\"gk-upload-content\">";
                echo "<span class=\"material-icons gk-upload-icon\">cloud_upload</span>";
                echo "<span class=\"gk-upload-text\">Datei hierher ziehen oder klicken</span>";
                if ($maxSize) echo "<span class=\"gk-upload-hint\">Max. {$e($maxSize)}</span>";
                echo "</div></div>";
                break;

            case 'richtext':
                $toolbar = $f['toolbar'] ?? 'basic';
                $basicBtns = [
                    ['cmd' => 'bold', 'icon' => 'format_bold'],
                    ['cmd' => 'italic', 'icon' => 'format_italic'],
                    ['cmd' => 'underline', 'icon' => 'format_underlined'],
                    ['cmd' => 'createLink', 'icon' => 'link', 'prompt' => 'URL eingeben:'],
                    ['cmd' => 'insertUnorderedList', 'icon' => 'format_list_bulleted'],
                    ['cmd' => 'insertOrderedList', 'icon' => 'format_list_numbered'],
                ];
                $fullBtns = [
                    ['cmd' => 'formatBlock', 'icon' => 'title', 'val' => 'h2', 'title' => 'H2'],
                    ['cmd' => 'formatBlock', 'icon' => 'text_fields', 'val' => 'h3', 'title' => 'H3'],
                    ['cmd' => 'formatBlock', 'icon' => 'format_quote', 'val' => 'blockquote'],
                    ['cmd' => 'formatBlock', 'icon' => 'code', 'val' => 'pre'],
                    ['cmd' => 'insertImage', 'icon' => 'image', 'prompt' => 'Bild-URL:'],
                    ['cmd' => 'insertHorizontalRule', 'icon' => 'horizontal_rule'],
                ];
                $btns = $toolbar === 'full' ? array_merge($basicBtns, $fullBtns) : $basicBtns;

                echo "<div class=\"gk-richtext\" data-field=\"{$e($name)}\">";
                echo "<div class=\"gk-richtext-toolbar\">";
                foreach ($btns as $b) {
                    $dataAttrs = "data-cmd=\"{$e($b['cmd'])}\"";
                    if (isset($b['val'])) $dataAttrs .= " data-val=\"{$e($b['val'])}\"";
                    if (isset($b['prompt'])) $dataAttrs .= " data-prompt=\"{$e($b['prompt'])}\"";
                    $title = $b['title'] ?? $b['cmd'];
                    echo "<button type=\"button\" class=\"gk-richtext-btn\" {$dataAttrs} title=\"{$e($title)}\"><span class=\"material-icons\">{$b['icon']}</span></button>";
                }
                echo "</div>";
                echo "<div class=\"gk-richtext-content\" contenteditable=\"true\">{$value}</div>";
                echo "<input type=\"hidden\" name=\"{$e($name)}\" id=\"{$e($name)}\" value=\"{$e($value)}\">";
                echo "</div>";
                break;

            default: // text, number, email, tel, url, password, date, time, datetime
                $htmlType = $type === 'datetime' ? 'datetime-local' : $type;
                $extra = '';
                if (isset($f['step'])) $extra .= " step=\"{$e($f['step'])}\"";
                if (isset($f['placeholder'])) $extra .= " placeholder=\"{$e($f['placeholder'])}\"";
                echo "<input type=\"{$e($htmlType)}\" name=\"{$e($name)}\" id=\"{$e($name)}\" value=\"{$e($value)}\" class=\"gk-input\"{$req}{$extra}>";
        }

        echo '<div class="gk-field-error" data-gk-error="' . $e($name) . '"></div>';
        echo '</div></div>';
    }
}
