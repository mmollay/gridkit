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
    private string $cancelLabel = '';
    private string $cancelHref = '';

    public function __construct(string $id)
    {
        $this->id = $id;
    }

    public function action(string $url): static { $this->action = $url; return $this; }
    public function ajax(): static { $this->isAjax = true; return $this; }
    public function card(bool $wrap = true): static { $this->wrapCard = $wrap; return $this; }
    public function cancel(string $label, string $href): static { $this->cancelLabel = $label; $this->cancelHref = $href; return $this; }
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

        if ($this->submitLabel || $this->cancelLabel) {
            echo '<div class="gk-form-actions">';
            if ($this->cancelLabel) {
                echo Button::render($this->cancelLabel, ['variant' => 'outlined', 'color' => 'neutral', 'href' => $this->cancelHref]);
            }
            if ($this->submitLabel) {
                echo Button::render($this->submitLabel, ['variant' => 'filled', 'color' => 'success', 'type' => 'submit', 'icon' => 'save']);
            }
            echo '</div>';
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
                if (empty($f['native'])) {
                    // Default: GridKit styled select (gk-select-search)
                    $options = $f['options'] ?? [];
                    $placeholder = $f['placeholder'] ?? 'Auswählen…';
                    $displayValue = isset($options[$value]) ? $options[$value] : $placeholder;
                    $disabled = !empty($f['disabled']) ? ' gk-select-disabled' : '';
                    echo "<div class=\"gk-select-search{$disabled}\" data-gk-select-search" . (!empty($f['disabled']) ? ' data-disabled' : '') . ">";
                    echo "<input type=\"hidden\" name=\"{$e($name)}\" id=\"{$e($name)}\" value=\"{$e($value)}\">";
                    echo '<div class="gk-select-display" tabindex="0">';
                    echo '<span class="gk-select-value">' . $e($displayValue) . '</span>';
                    echo '<span class="material-icons gk-select-arrow">expand_more</span>';
                    echo '</div>';
                    echo '<div class="gk-select-dropdown">';
                    if (count($options) > 6 || !empty($f['searchable'])) {
                        echo '<div class="gk-select-search-input"><span class="material-icons">search</span>';
                        echo "<input type=\"text\" placeholder=\"{$e($placeholder)}\" autocomplete=\"off\">";
                        echo '</div>';
                    }
                    echo '<div class="gk-select-options">';
                    foreach ($options as $k => $v) {
                        $sel = (string)$k === (string)$value ? ' selected' : '';
                        echo "<div class=\"gk-select-option{$sel}\" data-value=\"{$e($k)}\">{$e($v)}</div>";
                    }
                    echo '</div></div></div>';
                } else {
                    // native: true → plain <select> (z.B. für Toolbar-Filter)
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
                $multiple    = !empty($f['multiple']) ? ' multiple' : '';
                $maxSize     = $f['maxSize'] ?? '';
                $hint        = $f['hint'] ?? ($maxSize ? 'Max. ' . $maxSize : '');
                $icon        = $e($f['icon'] ?? 'cloud_upload');
                $label       = $e($f['label_text'] ?? ($multiple ? 'Dateien hierher ziehen oder klicken' : 'Datei hierher ziehen oder klicken'));
                // accept: array ['pdf','jpg',...] oder string '.pdf,.jpg,...'
                $acceptRaw   = $f['accept'] ?? [];
                if (is_array($acceptRaw)) {
                    $acceptStr = implode(',', array_map(
                        fn($ext) => str_starts_with($ext, '.') ? $ext : '.' . $ext,
                        $acceptRaw
                    ));
                } else {
                    $acceptStr = (string) $acceptRaw;
                }
                $acceptAttr  = $acceptStr !== '' ? " accept=\"{$e($acceptStr)}\"" : '';
                $minSize     = $f['minSize']      ?? '';
                $maxTotalSize= $f['maxTotalSize'] ?? '';
                $maxFiles    = isset($f['maxFiles']) ? (int)$f['maxFiles'] : 0;
                $withPreview = !empty($f['preview']);
                $dataAttrs   = ' data-gk-upload';
                if ($multiple)       $dataAttrs .= ' data-gk-multiple';
                if ($acceptStr)      $dataAttrs .= " data-gk-accept=\"{$e($acceptStr)}\"";
                if ($maxSize)        $dataAttrs .= " data-gk-max-size=\"{$e($maxSize)}\"";
                if ($minSize)        $dataAttrs .= " data-gk-min-size=\"{$e($minSize)}\"";
                if ($maxTotalSize)   $dataAttrs .= " data-gk-max-total-size=\"{$e($maxTotalSize)}\"";
                if ($maxFiles > 0)   $dataAttrs .= " data-gk-max-files=\"{$maxFiles}\"";
                if ($withPreview)    $dataAttrs .= ' data-gk-preview';
                echo "<div class=\"gk-upload-zone\"{$dataAttrs}>";
                echo "<input type=\"file\" name=\"{$e($name)}[]\" id=\"{$e($name)}\" class=\"gk-upload-input\"{$acceptAttr}{$multiple}{$req}>";
                echo "<div class=\"gk-upload-content gk-upload-idle\">";
                echo "<span class=\"material-icons gk-upload-icon\">{$icon}</span>";
                echo "<span class=\"gk-upload-text\">{$label}</span>";
                if ($hint) echo "<span class=\"gk-upload-hint\">{$e($hint)}</span>";
                echo "</div>";
                echo "<div class=\"gk-upload-progress\" style=\"display:none;flex-direction:column;align-items:center;gap:6px;pointer-events:none;\">";
                echo "<span class=\"material-icons gk-spin\" style=\"font-size:32px;color:var(--gk-primary);\">sync</span>";
                echo "<span class=\"gk-upload-text gk-upload-progress-label\">Wird hochgeladen…</span>";
                echo "</div>";
                echo "</div>";
                break;

            case 'color':
                $colorId  = 'gk-color-' . $name . '-' . substr(md5($name . microtime()), 0, 6);
                $hexId    = $colorId . '-hex';
                $colorVal = $value ?: '#6750a4';
                echo "<div class=\"gk-color-wrap\" id=\"{$colorId}-wrap\">";
                echo "<div class=\"gk-color-swatch\">";
                echo "<input type=\"color\" id=\"{$colorId}\" value=\"{$e($colorVal)}\" name=\"{$e($name)}\">";
                echo "</div>";
                echo "<input type=\"text\" id=\"{$hexId}\" class=\"gk-color-hex\" maxlength=\"7\" value=\"" . strtoupper($e($colorVal)) . "\" placeholder=\"#RRGGBB\" pattern=\"#[0-9A-Fa-f]{6}\">";
                echo "</div>";
                echo "<script>(function(){";
                echo "var sw=document.getElementById('{$colorId}');";
                echo "var hex=document.getElementById('{$hexId}');";
                echo "if(!sw||!hex)return;";
                echo "sw.addEventListener('input',function(){hex.value=sw.value.toUpperCase();});";
                echo "hex.addEventListener('input',function(){var v=hex.value;if(/^#[0-9A-Fa-f]{6}$/.test(v))sw.value=v;});";
                echo "hex.addEventListener('blur',function(){if(!/^#[0-9A-Fa-f]{6}$/.test(hex.value))hex.value=sw.value.toUpperCase();});";
                echo "})();</script>";
                break;

            case 'richtext':
                $editorId = 'gk-editor-' . $name . '-' . substr(md5($name . microtime()), 0, 6);
                $preset   = $f['preset'] ?? 'full'; // 'basic' | 'full'
                if ($preset === 'basic') {
                    $ckPlugins  = "CK.Essentials,CK.Paragraph,CK.Bold,CK.Italic,CK.Underline,CK.Strikethrough,CK.Link,CK.List,CK.Undo";
                    $ckToolbar  = "'bold','italic','underline','strikethrough','|','link','|','bulletedList','numberedList','|','undo','redo'";
                } else {
                    $ckPlugins  = "CK.Essentials,CK.Paragraph,CK.Heading,CK.Bold,CK.Italic,CK.Underline,CK.Strikethrough,CK.Link,CK.BlockQuote,CK.List,CK.Table,CK.TableToolbar,CK.TableProperties,CK.TableCellProperties,CK.Alignment,CK.Undo,CK.SourceEditing";
                    $ckToolbar  = "'heading','|','bold','italic','underline','strikethrough','|','link','blockQuote','|','bulletedList','numberedList','|','insertTable','|','alignment','|','undo','redo','|','sourceEditing'";
                }
                echo "<div class=\"gk-richtext-wrap\">";
                echo "<div id=\"{$editorId}\"></div>";
                echo "</div>";
                echo "<input type=\"hidden\" name=\"{$e($name)}\" id=\"{$editorId}-hidden\" value=\"{$e($value)}\">";
                // Lazy-init via IntersectionObserver — works inside hidden tabs
                $jsonValue = json_encode($value ?? '');
                echo "<script>(function(){";
                echo "var _id='{$editorId}';";
                echo "var _init=false;";
                echo "var _start=function(){";
                echo "if(_init)return;_init=true;";
                echo "var CK=window.CKEDITOR||{};var CE=CK.ClassicEditor;if(!CE)return;";
                echo "var p=[{$ckPlugins}].filter(Boolean);";
                echo "CE.create(document.getElementById(_id),{licenseKey:'GPL',plugins:p,toolbar:[{$ckToolbar}],language:'de'})";
                echo ".then(function(editor){";
                echo "var initial={$jsonValue};if(initial)editor.setData(initial);";
                echo "var h=document.getElementById(_id+'-hidden');";
                echo "if(h)h.value=editor.getData();";
                echo "editor.model.document.on('change:data',function(){if(h)h.value=editor.getData();});";
                echo "var frm=document.getElementById(_id);if(frm)frm=frm.closest('form');";
                echo "if(frm)frm.addEventListener('submit',function(){if(h)h.value=editor.getData();});";
                echo "}).catch(console.error);};";
                // Use IntersectionObserver to detect when element enters viewport / becomes visible
                echo "var _el=document.getElementById(_id);";
                echo "if(!_el){return;}";
                echo "if(typeof IntersectionObserver!=='undefined'){";
                echo "var _obs=new IntersectionObserver(function(entries){if(entries[0].isIntersecting){_start();_obs.disconnect();}},{threshold:0});";
                echo "_obs.observe(_el);";
                echo "}else{document.addEventListener('DOMContentLoaded',_start);}";
                echo "})();</script>";
                break;

            default: // text, number, email, tel, url, password, date, time, datetime
                $htmlType  = $type === 'datetime' ? 'datetime-local' : $type;
                $clearable = !empty($f['clearable']);
                $extra = '';
                if (isset($f['step']))        $extra .= " step=\"{$e($f['step'])}\"";
                if (isset($f['placeholder'])) $extra .= " placeholder=\"{$e($f['placeholder'])}\"";
                if ($clearable) {
                    $hasVal = $value !== '' && $value !== null ? '' : ' style="display:none"';
                    echo "<div class=\"gk-input-clearable\">";
                    echo "<input type=\"{$e($htmlType)}\" name=\"{$e($name)}\" id=\"{$e($name)}\" value=\"{$e($value)}\" class=\"gk-input\"{$req}{$extra} oninput=\"this.nextElementSibling.style.display=this.value?'':'none'\">";
                    echo "<button type=\"button\" class=\"gk-input-clear\" title=\"Leeren\"{$hasVal} onclick=\"this.previousElementSibling.value='';this.style.display='none';\"><span class=\"material-icons\">delete</span></button>";
                    echo "</div>";
                } else {
                    echo "<input type=\"{$e($htmlType)}\" name=\"{$e($name)}\" id=\"{$e($name)}\" value=\"{$e($value)}\" class=\"gk-input\"{$req}{$extra}>";
                }
        }

        echo '<div class="gk-field-error" data-gk-error="' . $e($name) . '"></div>';
        echo '</div></div>';
    }
}
