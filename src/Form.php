<?php
namespace GridKit;

use GridKit\Button;

class Form
{
    /** Types with rendering of their own, beyond a plain <input>. */
    private const FIELD_TYPES = [
        'textarea', 'select', 'multiselect', 'ajaxselect', 'checkbox', 'toggle',
        'radio', 'file', 'richtext', 'color', 'range',
    ];

    /** Everything HTML accepts as an <input type>. Anything else is a typo. */
    private const HTML_INPUT_TYPES = [
        'button', 'checkbox', 'color', 'date', 'datetime-local', 'email', 'file',
        'hidden', 'image', 'month', 'number', 'password', 'radio', 'range',
        'reset', 'search', 'submit', 'tel', 'text', 'time', 'url', 'week',
    ];

    private string $id;
    private string $action = '';
    private string $method = 'post';
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
    public function method(string $method): static { $this->method = strtolower($method); return $this; }
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
        $attrs = 'class="gk-form" id="' . $e($this->id) . '" method="' . $e($this->method) . '"';
        if ($this->action) $attrs .= ' action="' . $e($this->action) . '"';
        if ($this->isAjax) $attrs .= ' data-gk-ajax';

        if ($this->wrapCard) echo '<div class="gk-card" style="padding:24px;">';
        echo "<form {$attrs}>";

        foreach ($this->fields as $f) {
            match ($f['type']) {
                // `->hidden()` always sets a value; `->field($n, $l, 'hidden')`
                // reaches the same arm and need not.
                'hidden' => printf('<input type="hidden" name="%s" value="%s">',
                    $e($f['name']), $e($f['value'] ?? '')),
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
                // Main action in Primary. In this system green is the success role — the
                // colour for "it worked". Attaching it to the submit button uses it up:
                // if everything is green, green no longer means anything.
                echo Button::render($this->submitLabel, ['variant' => 'filled', 'color' => 'primary', 'type' => 'submit', 'icon' => 'save']);
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

    /**
     * A field name reduced to what is safe in a DOM id and in a JS string
     * literal at the same time. Escaping is not enough here: the id has to be
     * byte-identical in both places, and htmlspecialchars would make them
     * differ.
     */
    private static function slug(string $name): string
    {
        return preg_replace('/[^A-Za-z0-9_-]/', '', $name) ?? '';
    }

    private function renderField(array $f): void
    {
        $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        $name = $f['name'];
        $label = $f['label'] ?? '';
        $type = $f['type'];
        $value = $f['value'] ?? '';
        $isRequired = (bool) ($f['required'] ?? false);

        /*
         * The error text under a field was rendered, styled and never announced.
         * There was no aria-describedby anywhere in this class or in gridkit.js,
         * so a screen reader read "Email address, required, edit text" and
         * stopped — the reason the form had rejected the entry was visible only
         * to people who could see it. WCAG 3.3.1 asks that an error be
         * identified in text to the user, not to some users.
         *
         * describedby points at the error container unconditionally, because
         * the container is always rendered and the client fills it later; an
         * empty target is silently skipped. aria-invalid marks the state so the
         * error is announced as an error rather than as trailing prose.
         */
        $errorId  = $e($name) . '-error';
        $hasError = trim((string) ($f['error'] ?? '')) !== '';
        $describe = ' aria-describedby="' . $errorId . '"'
                  . ($hasError ? ' aria-invalid="true"' : '');

        // Carries the describedby to every control that appends it, which is
        // all of them — the alternative was the same two attributes repeated at
        // a dozen echo sites, one of which would have been missed.
        $req = ($isRequired ? ' required' : '') . $describe;
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

        // These types render no labelable element the label can point at. The
        // value carrier is hidden from the accessibility tree, or the control
        // is a group of them — so `for` either dangled or, worse, resolved to
        // a 1x1 aria-hidden input, and clicking the label focused nothing you
        // could see. They are named through aria-labelledby instead.
        $composite = in_array($type, ['radio', 'multiselect', 'ajaxselect', 'richtext', 'color'], true)
                  || ($type === 'select' && empty($f['native']));
        $labelId   = $e($name) . '-label';

        if ($showLabel) {
            echo '<label class="gk-label-text" id="' . $labelId . '"'
               . ($composite ? '' : ' for="' . $e($name) . '"') . '>' . $e($label);
            // aria-hidden: the input already carries `required`, which is what
            // announces the state. Without this the label reads "Email address
            // star", and the asterisk is decoration doing the same job twice.
            if ($isRequired) echo ' <span class="gk-required" aria-hidden="true">*</span>';
            echo '</label>';
        }

        // What names a composite widget: its visible label when there is one.
        $composedBy = ($showLabel && !isset($f['aria']))
            ? ' aria-labelledby="' . $labelId . '"'
            : (isset($f['aria']) ? ' aria-label="' . $e((string) $f['aria']) . '"' : '');
        // A composite widget is named here rather than by `for`, and it is the
        // visible control — so the error has to reach it here too. The hidden
        // value carrier that $req lands on is aria-hidden and announces nothing.
        $composedBy .= $describe;

        echo '<div class="gk-input-wrap">';

        switch ($type) {
            case 'textarea':
                $rows = (int) ($f['rows'] ?? 3);
                echo "<textarea name=\"{$e($name)}\" id=\"{$e($name)}\" rows=\"{$rows}\" class=\"gk-input\"{$req}>{$e($value)}</textarea>";
                break;

            case 'select':
                if (empty($f['native'])) {
                    /*
                     * One renderer, in Select. The same markup used to be built
                     * a second time right here, and the two copies drifted twice
                     * without anyone noticing: the options lost role="option" on
                     * one side, the combobox lost aria-controls on the other,
                     * and each was found only when a screen reader met it. What
                     * a form field needs on top — the visible label, the error,
                     * the disabled state, the filter box only once the list is
                     * long — is passed in rather than written out again.
                     */
                    $placeholder = $f['placeholder'] ?? Lang::t('form.select');
                    echo Select::searchable($name, $f['options'] ?? [], [
                        'id'                => $name,
                        'selected'          => $value,
                        'placeholder'       => $placeholder,
                        'searchPlaceholder' => $placeholder,
                        'required'          => $isRequired,
                        'disabled'          => !empty($f['disabled']),
                        'search'            => !empty($f['searchable']) ? true : 'auto',
                        // A label a person can read beats a duplicate string,
                        // so the visible one wins when there is one.
                        'labelledby'        => ($showLabel && !isset($f['aria'])) ? $labelId : null,
                        'aria'              => $f['aria'] ?? ($label !== '' ? $label : $placeholder),
                        'describedby'       => $errorId,
                        'invalid'           => $hasError,
                    ]);
                } else {
                    // native: true → plain <select> (e.g. for toolbar filters)
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
                $placeholder = $f['placeholder'] ?? Lang::t('form.search');
                $searchable = !empty($f['searchable']);
                echo '<div class="gk-multiselect" data-gk-multiselect>';
                // Same reason as the select above: required on a hidden input
                // is inert, so the star was the only sign the field mattered.
                echo "<input type=\"text\" class=\"gk-select-value-input\" tabindex=\"-1\" aria-hidden=\"true\" name=\"{$e($name)}\" value=\"{$e(implode(',', $selectedValues))}\"{$req}>";
                $listId = preg_replace('/[^A-Za-z0-9_-]/', '-', $name) . '-list';
                echo '<div class="gk-multiselect-display" tabindex="0" role="combobox" aria-haspopup="listbox"'
                   . ' aria-expanded="false" aria-controls="' . $e($listId) . '"'
                   . (($showLabel && !isset($f['aria'])) ? ' aria-labelledby="' . $labelId . '"' : ' aria-label="' . $e($f['aria'] ?? $label ?: $placeholder) . '"') . '>';
                echo '<div class="gk-multiselect-chips">';
                foreach ($selectedValues as $sv) {
                    if (isset($options[$sv])) {
                        // The remove button was a bare &times; and nothing
                        // else — no name at all — so what a screen reader met
                        // was a run of identical unlabelled buttons with no way
                        // to tell which chip each one drops.
                        $rm = $e(Lang::t('select.remove', ['label' => $options[$sv]]));
                        echo "<span class=\"gk-chip-selected\" data-value=\"{$e($sv)}\">{$e($options[$sv])} "
                           . "<button type=\"button\" class=\"gk-chip-remove\" aria-label=\"{$rm}\" title=\"{$rm}\">"
                           . "<span aria-hidden=\"true\">&times;</span></button></span>";
                    }
                }
                if ($searchable) {
                    echo "<input type=\"text\" class=\"gk-multiselect-input\" placeholder=\"{$e($placeholder)}\" autocomplete=\"off\">";
                }
                echo '</div>';
                echo '<span class="material-icons gk-select-arrow" aria-hidden="true">expand_more</span>';
                echo '</div>';
                echo '<div class="gk-select-dropdown"><div class="gk-select-options" id="' . $e($listId) . '"'
                   . ' role="listbox" aria-multiselectable="true">';
                foreach ($options as $k => $v) {
                    $isSel   = in_array((string)$k, array_map('strval', $selectedValues), true);
                    $sel     = $isSel ? ' selected' : '';
                    $ariaSel = $isSel ? 'true' : 'false';
                    $check = $isSel ? '<span class="material-icons" style="font-size:16px;" aria-hidden="true">check</span> ' : '';
                    echo "<div class=\"gk-select-option{$sel}\" role=\"option\" aria-selected=\"{$ariaSel}\" data-value=\"{$e($k)}\">{$check}{$e($v)}</div>";
                }
                echo '</div></div></div>';
                break;

            case 'ajaxselect':
                $url = $f['url'] ?? '';
                $displayValue = $f['displayValue'] ?? '';
                $placeholder = $f['placeholder'] ?? Lang::t('form.search');
                $labelField = $f['labelField'] ?? 'name';
                $valueField = $f['valueField'] ?? 'id';
                $subtextField = $f['subtextField'] ?? '';
                $minChars = $f['minChars'] ?? 2;
                $searchParam = $f['searchParam'] ?? 'q';
                echo "<div class=\"gk-ajax-select\" data-gk-ajax-select data-url=\"{$e($url)}\" data-label-field=\"{$e($labelField)}\" data-value-field=\"{$e($valueField)}\" data-subtext-field=\"{$e($subtextField)}\" data-min-chars=\"{$e($minChars)}\" data-search-param=\"{$e($searchParam)}\">";
                echo "<input type=\"text\" class=\"gk-select-value-input\" tabindex=\"-1\" aria-hidden=\"true\" name=\"{$e($name)}\" value=\"{$e($value)}\"{$req}>";
                /*
                 * The same three gaps the static select had, in the variant
                 * that fetches its options: the combobox promised a listbox
                 * and never said which element, the container it meant carried
                 * no role, and aria-expanded was written here once while six
                 * places in gridkit.js change the dropdown's visibility.
                 */
                $listId = preg_replace('/[^A-Za-z0-9_-]/', '-', $name) . '-list';
                echo '<div class="gk-select-display" tabindex="0" role="combobox" aria-haspopup="listbox"'
                   . ' aria-expanded="false" aria-controls="' . $e($listId) . '"' . $composedBy . '>';
                echo '<span class="material-icons gk-select-icon" aria-hidden="true">search</span>';
                $clearStyle = $value ? '' : ' style="display:none;"';
                echo "<input type=\"text\" class=\"gk-ajax-search-input\" value=\"{$e($displayValue)}\" placeholder=\"{$e($placeholder)}\" autocomplete=\"off\">";
                // Was a bare &times; with no name — a button announced as
                // "times", exactly like the chip and the toast close button.
                $clearLabel = $e(Lang::t('form.clear'));
                echo "<button type=\"button\" class=\"gk-ajax-clear\" aria-label=\"{$clearLabel}\" title=\"{$clearLabel}\"{$clearStyle}>"
                   . "<span aria-hidden=\"true\">&times;</span></button>";
                echo '</div>';
                echo '<div class="gk-select-dropdown"><div class="gk-select-options" id="' . $e($listId) . '" role="listbox"></div>';
                echo '<div class="gk-select-loading" style="display:none;"><span class="material-icons gk-spin" aria-hidden="true">sync</span> ' . $e(Lang::t('form.loading')) . '</div>';
                echo '</div></div>';
                break;

            case 'toggle':
                $checked = !empty($f['checked']) || !empty($value) ? ' checked' : '';
                // The same control the checkbox branch renders, in a different
                // skin — and that branch has always passed {$req} through.
                echo "<label class=\"gk-toggle\"><input type=\"checkbox\" name=\"{$e($name)}\" id=\"{$e($name)}\" value=\"1\"{$req}{$checked}><span class=\"gk-toggle-slider\"></span></label>";
                break;

            case 'checkbox':
                $checked = !empty($f['checked']) || !empty($value) ? ' checked' : '';
                echo "<label class=\"gk-checkbox-wrap\"><input type=\"checkbox\" name=\"{$e($name)}\" id=\"{$e($name)}\" value=\"1\"{$req}{$checked}><span class=\"gk-checkbox-custom\"></span><span class=\"gk-checkbox-text\">{$e($label)}</span></label>";
                break;

            case 'radio':
                $options = $f['options'] ?? [];
                $isInline = !empty($f['inline']);
                $dirClass = $isInline ? 'gk-radio-group-inline' : 'gk-radio-group';
                // A radio group is named by its own heading, not by each
                // button, so the group gets a role and the label's text.
                $groupName = ($showLabel && !isset($f['aria']))
                    ? " aria-labelledby=\"{$labelId}\""
                    : ' aria-label="' . $e($f['aria'] ?? $label) . '"';
                echo "<div class=\"{$dirClass}\" role=\"radiogroup\"{$groupName}>";
                foreach ($options as $k => $v) {
                    $chk = (string)$k === (string)$value ? ' checked' : '';
                    // required on ONE member constrains the whole group — the
                    // browser then asks for a choice before submitting. It was
                    // on none of them, so the star beside the label was the
                    // only thing saying the field mattered.
                    echo "<label class=\"gk-radio-wrap\"><input type=\"radio\" name=\"{$e($name)}\" value=\"{$e($k)}\"{$req}{$chk}><span class=\"gk-radio-custom\"></span><span class=\"gk-radio-text\">{$e($v)}</span></label>";
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
                $label       = $e($f['label_text'] ?? ($multiple ? Lang::t('form.upload_multiple') : Lang::t('form.upload_single')));
                // accept: array ['pdf','jpg',...] or string '.pdf,.jpg,...'
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
                echo "<span class=\"material-icons gk-upload-icon\" aria-hidden=\"true\">{$icon}</span>";
                echo "<span class=\"gk-upload-text\">{$label}</span>";
                if ($hint) echo "<span class=\"gk-upload-hint\">{$e($hint)}</span>";
                echo "</div>";
                echo "<div class=\"gk-upload-progress\" style=\"display:none;flex-direction:column;align-items:center;gap:6px;pointer-events:none;\">";
                echo "<span class=\"material-icons gk-spin\" style=\"font-size:32px;color:var(--gk-primary);\" aria-hidden=\"true\">sync</span>";
                echo "<span class=\"gk-upload-text gk-upload-progress-label\">" . $e(Lang::t('form.uploading')) . "</span>";
                echo "</div>";
                echo "</div>";
                break;

            case 'color':
                // The field name goes into an id that is then written into an
                // inline <script> as a single-quoted JS string. htmlspecialchars
                // would make the two spellings differ; slugging keeps them
                // byte-identical AND keeps quotes and spaces out of an id that
                // CSS selectors elsewhere rely on. A name carrying a quote used
                // to close the attribute and land a real onmouseover handler on
                // the page. The md5 suffix already supplies uniqueness.
                $colorId  = 'gk-color-' . self::slug($name) . '-' . substr(md5($name . microtime()), 0, 6);
                $hexId    = $colorId . '-hex';
                $colorVal = $value ?: '#6750a4';
                echo "<div class=\"gk-color-wrap\" id=\"{$colorId}-wrap\">";
                echo "<div class=\"gk-color-swatch\">";
                echo "<input type=\"color\" id=\"{$colorId}\" value=\"{$e($colorVal)}\" name=\"{$e($name)}\"{$composedBy}>";
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
                $editorId = 'gk-editor-' . self::slug($name) . '-' . substr(md5($name . microtime()), 0, 6);
                $preset   = $f['preset'] ?? 'full'; // 'basic' | 'full'

                // 'upload' => '/some/endpoint' turns on pictures. Off by default:
                // an editor that offers an upload button with nowhere to put the
                // file is worse than one without the button.
                //
                // The endpoint takes a POST with the file under `upload` and answers
                // { "url": "https://…" } — or { "error": { "message": "…" } }. That is
                // CKEditor's SimpleUploadAdapter contract, not ours.
                $uploadUrl = trim((string) ($f['upload'] ?? ''));

                // Extra headers for the upload request — in practice a CSRF token,
                // because an upload endpoint that any other site can post to on a
                // logged-in user's behalf is a hole. CKEditor sends the file as a
                // plain multipart POST; without this there is no way to carry one.
                $uploadHeaders = is_array($f['uploadHeaders'] ?? null) ? $f['uploadHeaders'] : [];

                // Deliberately ImageInline WITHOUT ImageBlock and WITHOUT ImageCaption.
                // Those two wrap every picture in <figure class="image"> and style it
                // through a stylesheet — which is right on a web page and wrong in an
                // e-mail, where there is no stylesheet and Outlook does not lay out
                // <figure> reliably. ImageInline writes a plain <img> inside the
                // paragraph, and ImageResize writes the size as style="width:42%"
                // directly on the tag. Both survive the trip into a mail client.
                // Measured, not assumed — see CHANGELOG 1.66.0.
                // GeneralHtmlSupport ist hier kein Luxus: ohne es wirft CKEditor
                // float und margin aus dem style-Attribut, und genau die beiden
                // lassen den Text um ein Bild fliessen. Gemessen — 'float:left;
                // margin:0 14px 8px 0;width:40%' kam als blosses 'width:40%'
                // zurueck. Mit GHS ueberlebt beides, ebenso das alte align-Attribut,
                // das Outlook als einziges zuverlaessig versteht.
                $bildPlugins = 'CK.Image,CK.ImageInline,CK.ImageToolbar,CK.ImageResize,'
                             . 'CK.ImageTextAlternative,CK.ImageUpload,CK.SimpleUploadAdapter,'
                             . 'CK.AutoImage,CK.LinkImage,CK.GeneralHtmlSupport,GkBildUmfluss';
                $bildToolbar = "'uploadImage','|'";

                if ($preset === 'basic') {
                    $ckPlugins  = "CK.Essentials,CK.Paragraph,CK.Bold,CK.Italic,CK.Underline,CK.Strikethrough,CK.Link,CK.List,CK.Undo";
                    $ckToolbar  = "'bold','italic','underline','strikethrough','|','link','|','bulletedList','numberedList','|','undo','redo'";
                    if ($uploadUrl !== '') {
                        $ckPlugins .= ',' . $bildPlugins;
                        $ckToolbar  = $bildToolbar . ',' . $ckToolbar;
                    }
                } else {
                    $ckPlugins  = "CK.Essentials,CK.Paragraph,CK.Heading,CK.Bold,CK.Italic,CK.Underline,CK.Strikethrough,CK.Link,CK.BlockQuote,CK.List,CK.Table,CK.TableToolbar,CK.TableProperties,CK.TableCellProperties,CK.Alignment,CK.Undo,CK.SourceEditing";
                    $ckToolbar  = "'heading','|','bold','italic','underline','strikethrough','|','link','blockQuote','|','bulletedList','numberedList','|','insertTable','|','alignment','|','undo','redo','|','sourceEditing'";
                    if ($uploadUrl !== '') {
                        $ckPlugins .= ',' . $bildPlugins;
                        $ckToolbar  = $bildToolbar . ',' . $ckToolbar;
                    }
                }
                echo "<div class=\"gk-richtext-wrap\" role=\"group\"{$composedBy}>";
                echo "<div id=\"{$editorId}\"></div>";
                echo "</div>";
                // CKEditor writes into this hidden input and hides everything
                // around it, so the browser cannot validate or focus it — the
                // one field type where `required` has to be checked in script.
                // data-gk-required-rich marks it; gridkit.js blocks the submit.
                $richReq = $isRequired ? " data-gk-required-rich=\"{$e($label)}\"" : '';
                echo "<input type=\"hidden\" name=\"{$e($name)}\" id=\"{$editorId}-hidden\" value=\"{$e($value)}\"{$richReq}>";
                // Lazy-init via IntersectionObserver — works inside hidden tabs
                $jsonValue = json_encode($value ?? '');
                echo "<script>(function(){";
                echo "var _id='{$editorId}';";
                echo "var _init=false;var _warten=0;";
                echo "var _start=function(){";
                /*
                 * The one-shot flag used to be set BEFORE the check for
                 * CKEditor, so a field that came into view while the editor
                 * was still loading marked itself done and returned — and
                 * could never run again. The textarea stayed a textarea, with
                 * no error anywhere. It never showed because the page that
                 * uses this loads CKEditor with a blocking <script> in the
                 * head, which is exactly the cost this makes avoidable: with
                 * the flag set only once the editor is actually there, the
                 * bundle may arrive late, deferred, or be injected on demand.
                 *
                 * A bounded wait rather than an event, because there is no
                 * event to agree on: CKEditor is supplied by the page, in
                 * whatever way that page chooses. Twenty seconds, then it
                 * stops asking and leaves a usable textarea behind.
                 */
                echo "if(_init)return;";
                echo "var CK=window.CKEDITOR||{};var CE=CK.ClassicEditor;";
                echo "if(!CE){if(_warten++<140){setTimeout(_start,150);}return;}";
                echo "_init=true;";
                if ($uploadUrl !== '') {
                    // Ausrichtung, die eine E-Mail uebersteht.
                    //
                    // CKEditors eigenes ImageStyle schreibt Klassen
                    // (class="image-style-align-left") und gestaltet sie ueber ein
                    // Stylesheet — in einer Mail laedt keines, die Klasse bleibt
                    // wirkungslos. Deshalb eigene Knoepfe, die BEIDES an den Tag
                    // schreiben: float fuer moderne Programme, align fuer Outlook,
                    // das float bis heute ignoriert.
                    //
                    // Die eingestellte Breite wird uebernommen, sonst verloere ein
                    // Klick auf 'links' die vorher gezogene Groesse.
                    echo "var GkAusricht={";
                    echo "gkBildLinks:{l:" . json_encode(Lang::t('image.align_left')) . ",a:{align:'left'},s:{'float':'left',margin:'0 16px 8px 0'},fliess:1},";
                    echo "gkBildRechts:{l:" . json_encode(Lang::t('image.align_right')) . ",a:{align:'right'},s:{'float':'right',margin:'0 0 8px 16px'},fliess:1},";
                    echo "gkBildMitte:{l:" . json_encode(Lang::t('image.align_center')) . ",a:{},s:{display:'block',margin:'8px auto'}},";
                    echo "gkBildOhne:{l:" . json_encode(Lang::t('image.align_none')) . ",a:{},s:{}}";
                    echo "};";
                    echo "function GkBildUmfluss(ed){";
                    echo "Object.keys(GkAusricht).forEach(function(n){";
                    echo "var d=GkAusricht[n];";
                    echo "ed.ui.componentFactory.add(n,function(loc){";
                    echo "var b=new CK.ButtonView(loc);";
                    echo "b.set({label:d.l,tooltip:true,withText:true});";
                    echo "b.on('execute',function(){";
                    echo "ed.model.change(function(w){";
                    echo "var el=ed.model.document.selection.getSelectedElement();if(!el)return;";
                    echo "var vorher=el.getAttribute('htmlImgAttributes')||{};";
                    // Die Breite steht entweder in unseren eigenen Stilen (frueher
                    // ausgerichtet) oder in CKEditors resizedWidth (Groessenmenue).
                    // Nur die erste zu lesen hiesse, dass ein Klick auf 'links' die
                    // eben gewaehlten 50 % wegwirft.
                    echo "var breite=(vorher.styles||{}).width||el.getAttribute('resizedWidth');";
                    // Ein Bild in Originalgroesse fuellt die Zeile — daneben passt kein
                    // Text, der Umfluss bliebe wirkungslos. Wer ihn waehlt, bekommt
                    // deshalb eine Startbreite; eine schon gewaehlte bleibt erhalten.
                    echo "if(d.fliess&&!breite)breite='33%';";
                    echo "var s={};Object.keys(d.s).forEach(function(k){s[k]=d.s[k];});";
                    echo "if(breite)s.width=breite;";
                    echo "w.setAttribute('htmlImgAttributes',{attributes:d.a,styles:s},el);";
                    echo "});ed.editing.view.focus();});";
                    echo "return b;});});}";
                }
                echo "var p=[{$ckPlugins}].filter(Boolean);";
                // The editor's own language was pinned to 'de' for every user,
                // which put lang="de" on English content — wrong for a screen
                // reader, and wrong for the toolbar as soon as a translation
                // file for that locale is loaded.
                $ckLang = json_encode($f['language'] ?? Lang::locale());

                // Sizes in percent, not pixels: a mail is read on a phone as often
                // as on a desktop, and a fixed pixel width overflows the one to fit
                // the other. The toolbar over a selected picture offers alt text
                // (which is what a reader sees while the picture loads, or instead
                // of it when the client blocks images) and the size handles.
                $bildOpts = '';
                if ($uploadUrl !== '') {
                    $bildOpts = ',simpleUpload:{uploadUrl:' . json_encode($uploadUrl)
                              . ',withCredentials:true'
                              . ($uploadHeaders !== [] ? ',headers:' . json_encode($uploadHeaders) : '')
                              . '}'
                              . ',htmlSupport:{allow:[{name:\'img\',styles:true,attributes:true,classes:true}]}'
                              . ',image:{insert:{type:\'inline\'}'
                              . ',resizeUnit:\'%\''
                              // Die Groessen kommen als AUSKLAPPMENUE in die Leiste, nicht als
                              // einzelne Knoepfe: ein resizeImage:<n> als Knopf verlangt zwingend
                              // ein Icon, sonst wirft CKEditor imageresizebuttons-missing-icon —
                              // und die Ausnahme reisst die GANZE Bildleiste mit, samt der
                              // Ausrichtungsknoepfe daneben. Genau daran scheiterte der Umfluss.
                              . ',resizeOptions:[{name:\'resizeImage:original\',value:null,label:\'Original\'}'
                              . ',{name:\'resizeImage:25\',value:\'25\',label:\'25 %\'}'
                              . ',{name:\'resizeImage:33\',value:\'33\',label:\'33 %\'}'
                              . ',{name:\'resizeImage:50\',value:\'50\',label:\'50 %\'}'
                              . ',{name:\'resizeImage:75\',value:\'75\',label:\'75 %\'}]'
                              . ',toolbar:[\'gkBildLinks\',\'gkBildMitte\',\'gkBildRechts\',\'gkBildOhne\',\'|\','
                              . '\'resizeImage\',\'|\','
                              . '\'imageTextAlternative\']}';
                }
                echo "CE.create(document.getElementById(_id),{licenseKey:'GPL',plugins:p,toolbar:[{$ckToolbar}],language:{$ckLang}{$bildOpts}})";
                echo ".then(function(editor){";
                // Block-Bilder aus Alt-Inhalten in Absatz-Bilder umwandeln.
                // insert.type='inline' wirkt nur beim EINFUEGEN; was schon als
                // <figure class="image"> im Text steht, bliebe ein Block — und
                // dort greifen die Ausrichtungsknoepfe nicht, weil sie am <img>
                // arbeiten. Die Breite der figure wandert dabei an das Bild:
                // dort steht sie in einer Mail richtig, an der figure waere sie
                // wirkungslos.
                if ($uploadUrl !== '') {
                    echo "function gkEntblocken(h){";
                    echo "if(!h||h.indexOf('<figure')===-1)return h;";
                    echo "var d=document.createElement('div');d.innerHTML=h;";
                    echo "d.querySelectorAll('figure.image').forEach(function(f){";
                    echo "var i=f.querySelector('img');if(!i){f.remove();return;}";
                    echo "var b=f.style.width;if(b&&!i.style.width)i.style.width=b;";
                    echo "i.removeAttribute('width');i.removeAttribute('height');";
                    echo "i.style.removeProperty('aspect-ratio');";
                    echo "var p=document.createElement('p');";
                    echo "var c=f.querySelector('figcaption');";
                    echo "p.appendChild(i);f.replaceWith(p);";
                    echo "if(c&&c.textContent.trim()){var pc=document.createElement('p');";
                    echo "pc.textContent=c.textContent.trim();p.after(pc);}});";
                    echo "return d.innerHTML;}";
                    echo "var initial=gkEntblocken({$jsonValue});if(initial)editor.setData(initial);";
                } else {
                    echo "var initial={$jsonValue};if(initial)editor.setData(initial);";
                }
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

                // The passthrough is deliberate — it covers `month`, `week`,
                // `search` and anything else HTML grows. But it also swallowed
                // typos: `'searchable-select'` was documented for years and
                // rendered `<input type="searchable-select">`, which every
                // browser shows as a plain text box. Silent, and wrong.
                if (!in_array($htmlType, self::HTML_INPUT_TYPES, true)) {
                    trigger_error(
                        "GridKit\\Form: unknown field type '{$type}'. It will render as a plain"
                        . " text input. Did you mean one of: "
                        . implode(', ', self::FIELD_TYPES) . '?',
                        E_USER_WARNING
                    );
                }
                $clearable = !empty($f['clearable']);
                $extra = '';
                if (isset($f['min']))         $extra .= " min=\"{$e($f['min'])}\"";
                if (isset($f['max']))         $extra .= " max=\"{$e($f['max'])}\"";
                // Date fields: cap max at 4-digit years (browsers otherwise allow 6+)
                if (in_array($type, ['date', 'datetime'], true) && !isset($f['max'])) {
                    $extra .= ' max="9999-12-31"';
                }
                if (isset($f['step']))        $extra .= " step=\"{$e($f['step'])}\"";
                if (isset($f['placeholder'])) $extra .= " placeholder=\"{$e($f['placeholder'])}\"";
                if ($clearable) {
                    $hasVal = $value !== '' && $value !== null ? '' : ' style="display:none"';
                    echo "<div class=\"gk-input-clearable\">";
                    echo "<input type=\"{$e($htmlType)}\" name=\"{$e($name)}\" id=\"{$e($name)}\" value=\"{$e($value)}\" class=\"gk-input\"{$req}{$extra} oninput=\"this.nextElementSibling.style.display=this.value?'':'none'\">";
                    // The glyph is `delete`, but the button clears the field — a screen
                    // reader announced "delete" for a control that deletes nothing.
                    // aria-label carries the real name; title alone would not
                    // survive GK.tip, which strips it on the first hover.
                    $clearLabel = $e(Lang::t('form.clear'));
                    echo "<button type=\"button\" class=\"gk-input-clear\" aria-label=\"{$clearLabel}\" title=\"{$clearLabel}\"{$hasVal} onclick=\"this.previousElementSibling.value='';this.style.display='none';\"><span class=\"material-icons\" aria-hidden=\"true\">delete</span></button>";
                    echo "</div>";
                } else {
                    echo "<input type=\"{$e($htmlType)}\" name=\"{$e($name)}\" id=\"{$e($name)}\" value=\"{$e($value)}\" class=\"gk-input\"{$req}{$extra}>";
                }
        }

        // role=alert so an error the client writes after submit is announced;
            // without it the message appears silently for anyone not looking.
            echo '<div class="gk-field-error" id="' . $errorId . '" role="alert"'
               . ' data-gk-error="' . $e($name) . '">' . $e($f['error'] ?? '') . '</div>';
        echo '</div></div>';
    }
}
