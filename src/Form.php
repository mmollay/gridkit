<?php
declare(strict_types=1);

namespace GridKit;

use GridKit\Button;

class Form
{
    /** Types with rendering of their own, beyond a plain <input>. */
    private const FIELD_TYPES = [
        'textarea', 'select', 'multiselect', 'ajaxselect', 'checkbox', 'toggle',
        'radio', 'file', 'richtext', 'color', 'range', 'choice',
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
    /** @var array<string,mixed> */
    private array $values = [];
    /** @var array<string,string> */
    private array $errors = [];
    /** @var array<string,true> the names of the fields of this form, for showIf */
    private array $names = [];

    /**
     * The hidden field that carries the chosen tab of a form with tabs (1.113.0). It is posted with
     * the form, so `->values($_POST)` hands it back and the form opens on the same tab again.
     */
    public const TAB_FIELD = '_gk_tab';
    /** Tab styles a form takes, by the class they put on the row (see `### Tabs` in the skill). */
    private const TAB_STYLES = ['karte', 'linie', 'kapsel', 'text'];
    /** @var array<string,true> the keys of the tabs added so far, to catch a duplicate */
    private array $tabKeys = [];
    /** 'none' before the first tab(), 'open' while fields go into a tab, 'closed' after endTabs() */
    private string $tabPhase = 'none';
    private string $tabStyle = 'karte';
    private bool $tabSecondary = false;
    private bool $tabRemember = false;
    private ?string $startTab = null;
    /** While render() runs: whether a panel is open and the plan of the tabs (null without tabs). */
    private bool $panelOpen = false;
    private ?array $tabPlan = null;

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

    /**
     * Fill the fields from a record, by field name: `->values($invoice)`.
     *
     * Every field whose name is a key of the array takes that value as its `value`; keys with no field
     * are ignored, so a whole database row can be passed. A field's own `'value'` option wins, and
     * calling it again adds to what was given before. Replaces `'value' => $row['x'] ?? ''` on each field.
     *
     * @param array<string,mixed> $values
     */
    public function values(array $values): static
    {
        $this->values = $values + $this->values;
        return $this;
    }

    /**
     * Show validation messages by field name: `->errors(['email' => 'Not an address'])`.
     *
     * Each message goes where the field's own `'error'` option would put it (red under the field,
     * `aria-invalid`, announced). A field's own non-empty `'error'` wins. A message whose key names no
     * field — `'_form'` or any other — is shown in one message above the form. This is the way to show
     * the errors of a POST that is redisplayed; an AJAX form gets them from the server's JSON as before.
     *
     * @param array<string,string> $errors
     */
    public function errors(array $errors): static
    {
        $this->errors = $errors + $this->errors;
        return $this;
    }

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

        [$fields, $unmatched] = $this->withValuesAndErrors();
        $this->names = [];
        foreach ($fields as $f) if (isset($f['name']) && is_string($f['name'])) $this->names[$f['name']] = true;
        if ($unmatched) echo $this->renderFormErrors($unmatched, $e);
        $this->tabPlan = $this->planTabs($fields);
        $this->panelOpen = false;

        foreach ($fields as $f) {
            match ($f['type']) {
                // `->hidden()` always sets a value; `->field($n, $l, 'hidden')`
                // reaches the same arm and need not.
                'hidden' => printf('<input type="hidden" name="%s" value="%s">',
                    $e($f['name']), $e($f['value'] ?? '')),
                '_row_start' => $this->renderRowStart($f),
                '_row_end' => print('</div>'),
                '_tab' => $this->renderTabPanel($f, $e),
                '_tabs_end' => $this->renderTabsEnd(),
                default => $this->renderField($f),
            };
        }
        $this->renderTabsEnd();

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

    /**
     * `data-gk-show-if` for a field with a 'showIf' option (1.105.0): the field shows only while another
     * field of the form has a given value. `['field' => 'kind', 'equals' => 'company']`; the test is one of
     * 'equals', 'not' (a value), 'in' (a list of values) or 'filled' (true: anything entered, false: empty).
     * An unusable condition is reported and ignored — the field stays visible, which is the safe side.
     *
     * @param array<string,mixed> $f
     */
    private function showIfAttribute(array $f, \Closure $e): string
    {
        if (!array_key_exists('showIf', $f)) return '';
        $c = $f['showIf'];
        $tests = ['equals', 'not', 'in', 'filled'];
        $problem = null;
        if (!is_array($c)) {
            $problem = "it is not an array";
        } elseif (!isset($c['field']) || !is_string($c['field']) || $c['field'] === '') {
            $problem = "it names no 'field'";
        } elseif (!isset($this->names[$c['field']])) {
            $problem = "no field '{$c['field']}' in this form";
        } elseif (array_diff(array_keys($c), ['field', ...$tests]) !== []) {
            $problem = "unknown keys " . implode(', ', array_diff(array_keys($c), ['field', ...$tests]));
        } elseif (count(array_intersect(array_keys($c), $tests)) !== 1) {
            $problem = "it needs exactly one of 'equals', 'not', 'in' or 'filled'";
        } elseif (isset($c['in']) && !is_array($c['in'])) {
            $problem = "'in' is not a list";
        }
        if ($problem !== null) {
            trigger_error("GridKit: field '" . ($f['name'] ?? '') . "' has an unusable 'showIf' ($problem). The field stays visible.", E_USER_WARNING);
            return '';
        }
        // Text on both sides: the browser compares what a control holds, and that is always text.
        foreach (['equals', 'not'] as $k) if (isset($c[$k])) $c[$k] = (string) $c[$k];
        if (isset($c['in'])) $c['in'] = array_map('strval', array_values($c['in']));
        if (isset($c['filled'])) $c['filled'] = (bool) $c['filled'];
        $json = json_encode($c, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $json === false ? '' : ' data-gk-show-if="' . $e($json) . '"';
    }

    /**
     * The fields with what values() and errors() gave them, and the error messages no field took.
     * A field's own 'value' and its own non-empty 'error' stay as they are.
     *
     * @return array{0: list<array<string,mixed>>, 1: list<string>}
     */
    private function withValuesAndErrors(): array
    {
        $fields = [];
        $taken = [];
        foreach ($this->fields as $f) {
            $name = isset($f['name']) && is_string($f['name']) ? $f['name'] : null;
            if ($name !== null) {
                if (!array_key_exists('value', $f) && array_key_exists($name, $this->values)) {
                    $f['value'] = $this->values[$name];
                }
                if (array_key_exists($name, $this->errors)) {
                    $taken[$name] = true;
                    if (trim((string) ($f['error'] ?? '')) === '') $f['error'] = (string) $this->errors[$name];
                }
            }
            $fields[] = $f;
        }
        $unmatched = [];
        foreach ($this->errors as $key => $message) {
            if (!isset($taken[(string) $key]) && trim((string) $message) !== '') $unmatched[] = (string) $message;
        }
        return [$fields, $unmatched];
    }

    /** One message above the form for the errors that belong to no field. */
    private function renderFormErrors(array $messages, \Closure $e): string
    {
        $body = count($messages) === 1
            ? $e($messages[0])
            : '<ul class="gk-message-list"><li>' . implode('</li><li>', array_map($e, $messages)) . '</li></ul>';
        return '<div class="gk-message gk-message-error" role="alert"><span class="material-icons" aria-hidden="true">error</span>'
             . '<div class="gk-message-content">' . $body . '</div></div>';
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

    /**
     * One field: its column, its label, the control of its type, the error slot.
     *
     * What every type needs from the lines before the switch is handed on in `$x`,
     * so each control is a method of its own and this one stays short.
     */
    private function renderField(array $f): void
    {
        // ENT_SUBSTITUTE (1.122.1): invalid UTF-8 in a value becomes U+FFFD instead of emptying the
        // whole attribute, as the line items editor has always written it.
        $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $name = $f['name'];
        $label = $f['label'] ?? '';
        $type = $f['type'];
        $value = $f['value'] ?? '';
        $isRequired = (bool) ($f['required'] ?? false);
        // A browser can require one radio of a group, but not one checkbox of
        // several: required on each would demand all of them. A star that
        // nothing enforces is a promise the form breaks, so it is not drawn.
        if ($isRequired && $type === 'choice' && !empty($f['multiple'])) {
            trigger_error("GridKit: field '$name' is a 'choice' with 'multiple' — a browser cannot require one of several checkboxes, so it shows no star; check it on the server.", E_USER_WARNING);
            $isRequired = false;
        }

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

        echo "<div class=\"{$cls}\"{$colStyle}{$this->showIfAttribute($f, $e)}>";

        // A toggle with a 'hint' is a settings row (.gk-setting, 1.92.0): its
        // title and its sentence stand beside the switch, not a label above it.
        $setting = $type === 'toggle' && trim((string) ($f['hint'] ?? '')) !== '';

        // Label (not for checkbox which has label integrated)
        $showLabel = $label && !in_array($type, ['checkbox']) && !$setting;

        // These types render no labelable element the label can point at. The
        // value carrier is hidden from the accessibility tree, or the control
        // is a group of them — so `for` either dangled or, worse, resolved to
        // a 1x1 aria-hidden input, and clicking the label focused nothing you
        // could see. They are named through aria-labelledby instead.
        $composite = in_array($type, ['radio', 'choice', 'multiselect', 'ajaxselect', 'richtext', 'color', 'lineitems', 'daterange'], true)
                  || ($type === 'select' && empty($f['native']));
        $labelId   = $e($name) . '-label';

        // 'help' (1.112.0): a help icon that opens its text on a click (Tooltip::help()). It
        // stands BESIDE the label, never inside it — inside, its name would become part of the
        // field's name ("Email More about Email"). Without a visible label it follows the
        // control. A field without 'help' renders exactly as before.
        $help = isset($f['help']) && $f['help'] !== '' && $f['help'] !== []
            ? Tooltip::help($f['help'], ['label' => (string) $label])
            : '';

        if ($showLabel) {
            if ($help !== '') echo '<div class="gk-label-row">';
            echo '<label class="gk-label-text" id="' . $labelId . '"'
               . ($composite ? '' : ' for="' . $e($name) . '"') . '>' . $e($label);
            // aria-hidden: the input already carries `required`, which is what
            // announces the state. Without this the label reads "Email address
            // star", and the asterisk is decoration doing the same job twice.
            if ($isRequired) echo ' <span class="gk-required" aria-hidden="true">*</span>';
            echo '</label>';
            if ($help !== '') echo $help . '</div>';
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

        $x = compact('name', 'label', 'type', 'value', 'isRequired', 'req', 'describe', 'composedBy',
                     'labelId', 'showLabel', 'errorId', 'hasError', 'setting');

        switch ($type) {
            case 'textarea':    $this->renderTextarea($f, $e, $x); break;
            case 'select':      $this->renderSelect($f, $e, $x); break;
            case 'multiselect': $this->renderMultiselect($f, $e, $x); break;
            case 'ajaxselect':  $this->renderAjaxSelect($f, $e, $x); break;
            case 'toggle':      $this->renderToggle($f, $e, $x); break;
            case 'choice':
                $this->renderChoice($f, $e, $showLabel && !isset($f['aria']) ? $labelId : null, $isRequired, $describe);
                break;
            case 'checkbox':    $this->renderCheckbox($f, $e, $x); break;
            case 'radio':       $this->renderRadio($f, $e, $x); break;
            case 'range':       $this->renderRange($f, $e, $x); break;
            case 'file':        $this->renderFile($f, $e, $x); break;
            case 'color':       $this->renderColor($f, $e, $x); break;
            case 'richtext':    $this->renderRichtext($f, $e, $x); break;
            // 1.117.0: the editor of GridKit\LineItems, given as 'lines', named by this label.
            case 'lineitems':   echo LineItems::forForm($f['lines'] ?? null, $name, $value); break;
            // 1.120.0: the period picker of GridKit\DateRange, named by this label; its options are the field's.
            case 'daterange':   echo DateRange::forForm($f, $name, $value, $hasError); break;
            default:            $this->renderInput($f, $e, $x); // text, number, email, tel, url, password, date, time, datetime
        }
        if (!$showLabel && $help !== '') echo $help;

        // role=alert so an error the client writes after submit is announced;
        // without it the message appears silently for anyone not looking.
        echo '<div class="gk-field-error" id="' . $errorId . '" role="alert"'
           . ' data-gk-error="' . $e($name) . '">' . $e($f['error'] ?? '') . '</div>';
        echo '</div></div>';
    }

    /** A 'textarea' field. */
    private function renderTextarea(array $f, \Closure $e, array $x): void
    {
        ['name' => $name, 'value' => $value, 'req' => $req] = $x;
        $rows = (int) ($f['rows'] ?? 3);
        echo "<textarea name=\"{$e($name)}\" id=\"{$e($name)}\" rows=\"{$rows}\" class=\"gk-input\"{$req}>{$e($value)}</textarea>";
    }

    /** A 'select' field: the searchable widget from Select, or a native <select>. */
    private function renderSelect(array $f, \Closure $e, array $x): void
    {
        ['name' => $name, 'label' => $label, 'value' => $value, 'isRequired' => $isRequired, 'req' => $req, 'showLabel' => $showLabel, 'labelId' => $labelId, 'errorId' => $errorId, 'hasError' => $hasError] = $x;
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
    }

    /** A 'multiselect' field. */
    private function renderMultiselect(array $f, \Closure $e, array $x): void
    {
        ['name' => $name, 'label' => $label, 'value' => $value, 'req' => $req, 'showLabel' => $showLabel, 'labelId' => $labelId] = $x;
        $options = $f['options'] ?? [];
        // A single selected id from the database is a number, not "5": explode() takes a string.
        $selectedValues = is_array($value) ? $value : ($value ? explode(',', is_scalar($value) ? (string) $value : $value) : []);
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
    }

    /** An 'ajaxselect' field: options fetched from a URL. */
    private function renderAjaxSelect(array $f, \Closure $e, array $x): void
    {
        ['name' => $name, 'value' => $value, 'req' => $req, 'composedBy' => $composedBy] = $x;
        echo self::ajaxSelectHtml(
            $e, $f,
            value: $value,
            shown: $f['displayValue'] ?? '',
            placeholder: $f['placeholder'] ?? Lang::t('form.search'),
            idBase: $name,
            // Only nothing chosen hides the clear button: a chosen '0' or 0 is a value (1.122.1; it
            // used to be any falsy value).
            hideClear: !is_scalar($value) || (string) $value === '',
            hidden: ' name="' . $e($name) . '"',
            hiddenTail: $req,
            combo: $composedBy,
            // The search box names itself by the label like the combobox does, not by its placeholder
            // alone (1.122.1) — without the error pointer and aria-invalid: those stay on the combobox.
            searchTail: (string) preg_replace('/ aria-(?:describedby|invalid)="[^"]*"/', '', $composedBy),
        );
    }

    /**
     * The markup GK.ajaxSelect binds, written in this one place: the 'ajaxselect' field above and
     * the 'lookup' column of LineItems (1.117.0) both render it from here, so the two cannot
     * drift apart. js/gridkit.js reads its classes and data-* attributes.
     *
     * $opts holds the options both share: url, labelField, valueField, subtextField, minChars
     * and searchParam (their defaults as documented for the field). $value is what the hidden
     * input posts, $shown the words in the search box; $idBase names the listbox
     * (<idBase>-list); $hideClear: nothing is chosen yet, the clear button starts hidden.
     *
     * What differs between the callers arrives as finished attribute strings, each '' or
     * starting with a space, at the place each caller has always written it: $hidden before the
     * posted value and $hiddenTail after it, $combo on the combobox, $searchBox before the
     * placeholder of the search box and $searchTail after it. Each caller escapes with its own
     * $e, so the bytes stay its own.
     *
     * @internal Not part of the API: its parameters follow its two callers and change with them.
     * @param array<string,mixed> $opts
     */
    public static function ajaxSelectHtml(
        \Closure $e,
        array $opts,
        mixed $value,
        mixed $shown,
        mixed $placeholder,
        string $idBase,
        bool $hideClear,
        string $hidden = '',
        string $hiddenTail = '',
        string $combo = '',
        string $searchBox = '',
        string $searchTail = '',
    ): string {
        /*
         * The same three gaps the static select had, in the variant
         * that fetches its options: the combobox promised a listbox
         * and never said which element, the container it meant carried
         * no role, and aria-expanded was written here once while six
         * places in gridkit.js change the dropdown's visibility.
         */
        $listId = $e(preg_replace('/[^A-Za-z0-9_-]/', '-', $idBase) . '-list');
        // Was a bare &times; with no name — a button announced as
        // "times", exactly like the chip and the toast close button.
        $clearLabel = $e(Lang::t('form.clear'));
        return '<div class="gk-ajax-select" data-gk-ajax-select data-url="' . $e($opts['url'] ?? '') . '"'
             . ' data-label-field="' . $e($opts['labelField'] ?? 'name') . '" data-value-field="' . $e($opts['valueField'] ?? 'id') . '"'
             . ' data-subtext-field="' . $e($opts['subtextField'] ?? '') . '" data-min-chars="' . $e($opts['minChars'] ?? 2) . '"'
             . ' data-search-param="' . $e($opts['searchParam'] ?? 'q') . '">'
             . '<input type="text" class="gk-select-value-input" tabindex="-1" aria-hidden="true"' . $hidden
             . ' value="' . $e($value) . '"' . $hiddenTail . '>'
             . '<div class="gk-select-display" tabindex="0" role="combobox" aria-haspopup="listbox"'
             . ' aria-expanded="false" aria-controls="' . $listId . '"' . $combo . '>'
             . '<span class="material-icons gk-select-icon" aria-hidden="true">search</span>'
             . '<input type="text" class="gk-ajax-search-input" value="' . $e($shown) . '"' . $searchBox
             . ' placeholder="' . $e($placeholder) . '" autocomplete="off"' . $searchTail . '>'
             . '<button type="button" class="gk-ajax-clear" aria-label="' . $clearLabel . '" title="' . $clearLabel . '"'
             . ($hideClear ? ' style="display:none;"' : '') . '><span aria-hidden="true">&times;</span></button>'
             . '</div>'
             . '<div class="gk-select-dropdown"><div class="gk-select-options" id="' . $listId . '" role="listbox"></div>'
             . '<div class="gk-select-loading" style="display:none;"><span class="material-icons gk-spin" aria-hidden="true">sync</span> '
             . $e(Lang::t('form.loading')) . '</div>'
             . '</div></div>';
    }

    /** A 'toggle' field: a switch, or a settings row when it has a 'hint'. */
    private function renderToggle(array $f, \Closure $e, array $x): void
    {
        ['name' => $name, 'value' => $value, 'req' => $req, 'isRequired' => $isRequired, 'hasError' => $hasError, 'setting' => $setting] = $x;
        $checked = !empty($f['checked']) || !empty($value) ? ' checked' : '';
        if ($setting) {
            $this->renderSetting($f, $e, $checked, $isRequired, $hasError);
            return;
        }
        // The same control the checkbox branch renders, in a different
        // skin — and that branch has always passed {$req} through.
        echo "<label class=\"gk-toggle\"><input type=\"checkbox\" name=\"{$e($name)}\" id=\"{$e($name)}\" value=\"1\"{$req}{$checked}><span class=\"gk-toggle-slider\"></span></label>";
    }

    /** A 'checkbox' field. */
    private function renderCheckbox(array $f, \Closure $e, array $x): void
    {
        ['name' => $name, 'label' => $label, 'value' => $value, 'req' => $req] = $x;
        $checked = !empty($f['checked']) || !empty($value) ? ' checked' : '';
        echo "<label class=\"gk-checkbox-wrap\"><input type=\"checkbox\" name=\"{$e($name)}\" id=\"{$e($name)}\" value=\"1\"{$req}{$checked}><span class=\"gk-checkbox-custom\"></span><span class=\"gk-checkbox-text\">{$e($label)}</span></label>";
    }

    /** A 'radio' group. */
    private function renderRadio(array $f, \Closure $e, array $x): void
    {
        ['name' => $name, 'label' => $label, 'value' => $value, 'req' => $req, 'showLabel' => $showLabel, 'labelId' => $labelId] = $x;
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
    }

    /** A 'range' slider with its output. */
    private function renderRange(array $f, \Closure $e, array $x): void
    {
        ['name' => $name, 'value' => $value, 'describe' => $describe] = $x;
        $min = $f['min'] ?? 0;
        $max = $f['max'] ?? 100;
        $step = $f['step'] ?? 1;
        $val = $value !== '' ? $value : $min;
        echo "<div class=\"gk-range-wrap\">";
        echo "<input type=\"range\" name=\"{$e($name)}\" id=\"{$e($name)}\" class=\"gk-range\" min=\"{$e($min)}\" max=\"{$e($max)}\" step=\"{$e($step)}\" value=\"{$e($val)}\"{$describe}>";
        echo "<output class=\"gk-range-value\" for=\"{$e($name)}\">{$e($val)}</output>";
        echo "</div>";
    }

    /** A 'file' field: the upload zone. */
    private function renderFile(array $f, \Closure $e, array $x): void
    {
        ['name' => $name, 'req' => $req] = $x;
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
    }

    /** A 'color' field: swatch, hex box and the script that keeps them equal. */
    private function renderColor(array $f, \Closure $e, array $x): void
    {
        ['name' => $name, 'value' => $value, 'composedBy' => $composedBy] = $x;
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
        echo "<input type=\"text\" id=\"{$hexId}\" class=\"gk-color-hex\" maxlength=\"7\" value=\"" . strtoupper($e($colorVal)) . "\" placeholder=\"#RRGGBB\" pattern=\"#[0-9A-Fa-f]{6}\"{$composedBy}>";
        echo "</div>";
        echo "<script>(function(){";
        echo "var sw=document.getElementById('{$colorId}');";
        echo "var hex=document.getElementById('{$hexId}');";
        echo "if(!sw||!hex)return;";
        echo "sw.addEventListener('input',function(){hex.value=sw.value.toUpperCase();});";
        echo "hex.addEventListener('input',function(){var v=hex.value;if(/^#[0-9A-Fa-f]{6}$/.test(v))sw.value=v;});";
        echo "hex.addEventListener('blur',function(){if(!/^#[0-9A-Fa-f]{6}$/.test(hex.value))hex.value=sw.value.toUpperCase();});";
        echo "})();</script>";
    }

    /** A 'richtext' field: the CKEditor mount point, the hidden value carrier and the script that starts it. */
    private function renderRichtext(array $f, \Closure $e, array $x): void
    {
        ['name' => $name, 'label' => $label, 'value' => $value, 'isRequired' => $isRequired, 'composedBy' => $composedBy] = $x;
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
        $this->renderRichtextScript($f, $editorId, $uploadUrl, $uploadHeaders, $ckPlugins, $ckToolbar, $value);
    }

    /** The script that creates the editor when its field comes into view. */
    private function renderRichtextScript(array $f, string $editorId, string $uploadUrl, array $uploadHeaders,
                                          string $ckPlugins, string $ckToolbar, mixed $value): void
    {
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
            $this->renderRichtextAlign();
        }
        echo "var p=[{$ckPlugins}].filter(Boolean);";
        // The editor's own language was pinned to 'de' for every user,
        // which put lang="de" on English content — wrong for a screen
        // reader, and wrong for the toolbar as soon as a translation
        // file for that locale is loaded.
        $ckLang = json_encode($f['language'] ?? Lang::locale());

        $bildOpts = $this->richtextImageOptions($uploadUrl, $uploadHeaders);
        echo "CE.create(document.getElementById(_id),{licenseKey:'GPL',plugins:p,toolbar:[{$ckToolbar}],language:{$ckLang}{$bildOpts}})";
        echo ".then(function(editor){";
        $this->renderRichtextInitial($uploadUrl, $jsonValue);
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
    }

    /** The script part that adds the buttons for aligning a picture (float and align, which a mail survives). */
    private function renderRichtextAlign(): void
    {
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

    /** The CKEditor options for pictures, as the JavaScript text that follows `language:…` in CE.create(). */
    private function richtextImageOptions(string $uploadUrl, array $uploadHeaders): string
    {
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
        return $bildOpts;
    }

    /** The script part that puts the saved value into the editor, picture blocks turned into inline pictures. */
    private function renderRichtextInitial(string $uploadUrl, string|false $jsonValue): void
    {
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
    }

    /** Every other type: an <input type="…">, with a clear button when 'clearable'. */
    private function renderInput(array $f, \Closure $e, array $x): void
    {
        ['type' => $type, 'name' => $name, 'value' => $value, 'req' => $req] = $x;
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

    /**
     * A 'toggle' with a 'hint': a settings row (.gk-setting). The title is a
     * real <label for> — a click on the words switches, as on the switch — and
     * the sentence describes the control, then the error slot does. The input
     * is a switch to a screen reader (role="switch"), as it is to the eye.
     * 'danger' => true marks a switch that locks someone out.
     */
    private function renderSetting(array $f, \Closure $e, string $checked, bool $isRequired, bool $hasError): void
    {
        $name  = (string) $f['name'];
        $id    = $e($name);
        $cls   = 'gk-setting' . (!empty($f['danger']) ? ' gk-setting-danger' : '');
        $star  = $isRequired ? ' <span class="gk-required" aria-hidden="true">*</span>' : '';
        echo '<div class="' . $cls . '">'
           . '<div class="gk-setting-text">'
           . '<label class="gk-setting-title" id="' . $id . '-label" for="' . $id . '">' . $e($f['label'] ?? '') . $star . '</label>'
           . '<span class="gk-setting-hint" id="' . $id . '-hint">' . $e($f['hint']) . '</span>'
           . '</div>'
           . '<label class="gk-toggle gk-setting-control">'
           . '<input type="checkbox" role="switch" name="' . $id . '" id="' . $id . '" value="1"'
           . ($isRequired ? ' required' : '')
           . ' aria-describedby="' . $id . '-hint ' . $id . '-error"'
           . ($hasError ? ' aria-invalid="true"' : '')
           . $checked . '>'
           . '<span class="gk-toggle-slider"></span></label>'
           . '</div>';
    }

    /**
     * A 'choice' field: its options as bordered tiles with a letter
     * (.gk-choice), for a decision between a few answers. Radios by default;
     * 'multiple' => true makes them checkboxes, posted as name[]. An option
     * is a label, or ['title' => …, 'hint' => …, 'mark' => …]; the mark is
     * A, B, C … by position unless given. Each input is named by its mark
     * and title and described by its hint, so a screen reader hears
     * "A, Fix it now" and then the sentence — not all three run together.
     */
    private function renderChoice(array $f, \Closure $e, ?string $labelId, bool $isRequired, string $describe): void
    {
        $name     = (string) $f['name'];
        $multiple = !empty($f['multiple']);
        $slug     = self::slug($name);
        $value    = $f['value'] ?? '';
        $chosen   = $multiple
            ? array_map('strval', is_array($value) ? $value : array_filter(array_map('trim', explode(',', (string) $value)), 'strlen'))
            : [(string) $value];
        $group = $labelId !== null
            ? ' aria-labelledby="' . $labelId . '"'
            : ' aria-label="' . $e($f['aria'] ?? ($f['label'] ?? '')) . '"';
        // A group of checkboxes cannot be required natively (required on each
        // would demand all of them), so it is a group, not a radiogroup, and
        // the requirement is left to the server — see the skill.
        echo '<div class="gk-flex gk-flex-col gk-gap-sm" role="' . ($multiple ? 'group' : 'radiogroup') . '"' . $group . $describe . '>';
        $i = 0;
        foreach ((array) ($f['options'] ?? []) as $k => $opt) {
            $title = is_array($opt) ? (string) ($opt['title'] ?? $k) : (string) $opt;
            $hint  = is_array($opt) ? (string) ($opt['hint'] ?? '') : '';
            $mark  = is_array($opt) && isset($opt['mark']) ? (string) $opt['mark']
                   : ($i < 26 ? chr(65 + $i) : (string) ($i + 1));
            $oid   = $slug . '-c' . $i;
            $on    = in_array((string) $k, $chosen, true) ? ' checked' : '';
            echo '<label class="gk-choice">'
               . '<input type="' . ($multiple ? 'checkbox' : 'radio') . '" name="' . $e($name) . ($multiple ? '[]' : '') . '"'
               . ' value="' . $e((string) $k) . '"'
               . ($isRequired && !$multiple ? ' required' : '')
               . ' aria-labelledby="' . $oid . '-m ' . $oid . '-t"'
               . ($hint !== '' ? ' aria-describedby="' . $oid . '-h"' : '')
               . $on . '>'
               . '<span class="gk-choice-mark" id="' . $oid . '-m">' . $e($mark) . '</span>'
               . '<span class="gk-choice-text">'
               . '<span class="gk-choice-title" id="' . $oid . '-t">' . $e($title) . '</span>'
               . ($hint !== '' ? '<span class="gk-choice-hint" id="' . $oid . '-h">' . $e($hint) . '</span>' : '')
               . '</span></label>';
            $i++;
        }
        echo '</div>';
    }

    /* ── Tabs in a form (1.113.0) ────────────────────────────────────────────────────────────
     *
     * A long form (customer, invoice, rights) was split into tabs by hand, and then a required
     * field in a hidden tab blocked the submit with a browser message nobody could see, and a
     * message from errors() marked a field nobody could find. Here the form knows its tabs: the
     * fields after tab() go into its panel, the server's errors pick the tab that opens and put a
     * badge on every tab they are in, and js/gridkit.js (1.113.0 block) opens the tab of the first
     * field the browser rejects.
     *
     * The markup is the authored shape of `### Tabs` — `.gk-tabs > .gk-tab-nav > .gk-tab-btn[data-tab]`
     * and `.gk-tab-panel[data-tab]` — so the existing tab script switches it, and a later Tabs
     * component can render the same bytes. Two things differ from a tab row outside a form, and both
     * are required: each button is `type="button"` (a <button> in a <form> submits it), and a hidden
     * input before the row carries the chosen tab and what the script needs to know about the form.
     * Every panel stays in the DOM and is posted; a hidden panel is display:none, never disabled.
     */

    /**
     * Start a tab: every field added after this, up to the next tab() or endTabs(), goes into its
     * panel. Fields before the first tab() stand above the tabs. Options: `icon`, `badge`, `count`,
     * `disabled`. One set of tabs per form.
     *
     * @param array<string,mixed> $opts
     */
    public function tab(string $key, string $label, array $opts = []): static
    {
        if ($this->tabPhase === 'closed') {
            trigger_error("GridKit: form '{$this->id}' has one set of tabs; tab('$key') after endTabs() is ignored and its fields stand below the tabs.", E_USER_WARNING);
            return $this;
        }
        if ($this->inRow) {
            trigger_error("GridKit: form '{$this->id}': tab('$key') inside a row() — the row is closed first.", E_USER_WARNING);
            $this->endRow();
        }
        if (trim($key) === '') {
            $key = 'tab-' . (count($this->tabKeys) + 1);
            trigger_error("GridKit: form '{$this->id}': a tab without a key is given the key '$key'.", E_USER_WARNING);
        }
        if (isset($this->tabKeys[$key])) {
            // Two panels under one key would be one tab: the second could never be opened.
            $n = 2;
            while (isset($this->tabKeys["$key-$n"])) $n++;
            trigger_error("GridKit: form '{$this->id}' has two tabs with the key '$key'; the second is renamed '$key-$n'.", E_USER_WARNING);
            $key = "$key-$n";
        }
        $this->tabKeys[$key] = true;
        $this->tabPhase = 'open';
        $this->fields[] = ['type' => '_tab', 'key' => $key, 'label' => $label] + $this->tabOptions($key, $opts);
        return $this;
    }

    /** End the tabs: the fields added after this stand below them. Without it the tabs run to the end. */
    public function endTabs(): static
    {
        if ($this->tabPhase !== 'open') {
            trigger_error("GridKit: form '{$this->id}': endTabs() without an open tab() is ignored.", E_USER_WARNING);
            return $this;
        }
        if ($this->inRow) {
            trigger_error("GridKit: form '{$this->id}': endTabs() inside a row() — the row is closed first.", E_USER_WARNING);
            $this->endRow();
        }
        $this->fields[] = ['type' => '_tabs_end'];
        $this->tabPhase = 'closed';
        return $this;
    }

    /** The style of the tab row: 'karte' (the default, page tabs on their surface), 'linie', 'kapsel' or 'text'. */
    public function tabsStyle(string $style): static
    {
        if (!in_array($style, self::TAB_STYLES, true)) {
            trigger_error("GridKit: form '{$this->id}': unknown tabsStyle('$style'); one of " . implode(', ', self::TAB_STYLES) . ". 'karte' is used.", E_USER_WARNING);
            $style = 'karte';
        }
        $this->tabStyle = $style;
        return $this;
    }

    /**
     * The second-row shape (`gk-tab-nav-karte gk-tab-nav-secondary`, panels without a surface of their
     * own): for a form in a modal, a side sheet or a panel of the page's own tabs, where the window or
     * the outer panel already is the surface. The form cannot see where it is rendered, so the page says.
     */
    public function tabsSecondary(bool $on = true): static
    {
        $this->tabSecondary = $on;
        return $this;
    }

    /** Remember the last tab per form id in the browser (localStorage) and open it next time, unless the page chose one. */
    public function tabsRemember(bool $on = true): static
    {
        $this->tabRemember = $on;
        return $this;
    }

    /**
     * The tab the form starts on, instead of the first: `->activeTab($_GET['tab'] ?? '')`. A tab with an
     * error wins over it, and so does the tab posted back in TAB_FIELD; an empty key is no choice.
     */
    public function activeTab(string $key): static
    {
        $this->startTab = $key === '' ? null : $key;
        return $this;
    }

    /**
     * The options of one tab, checked: what is unusable is reported and left out, never guessed at.
     *
     * @param array<string,mixed> $o
     * @return array{icon: string, badge: string, count: ?string, disabled: bool}
     */
    private function tabOptions(string $key, array $o): array
    {
        $out = ['icon' => '', 'badge' => '', 'count' => null, 'disabled' => !empty($o['disabled'])];
        $unknown = array_diff(array_keys($o), ['icon', 'badge', 'count', 'disabled']);
        if ($unknown) {
            trigger_error("GridKit: tab '$key' has unknown options " . implode(', ', $unknown) . '; they are ignored.', E_USER_WARNING);
        }
        foreach (['icon', 'badge'] as $k) {
            if (!isset($o[$k])) continue;
            if (is_string($o[$k]) || is_int($o[$k])) $out[$k] = trim((string) $o[$k]);
            else trigger_error("GridKit: tab '$key' has a '$k' that is not text; it is ignored.", E_USER_WARNING);
        }
        if (isset($o['count'])) {
            // A count is a number of things behind the tab (the skill), not a status word.
            if ((is_int($o['count']) && $o['count'] >= 0) || (is_string($o['count']) && ctype_digit($o['count']))) $out['count'] = (string) $o['count'];
            else trigger_error("GridKit: tab '$key' has a 'count' that is not a whole number; it is ignored.", E_USER_WARNING);
        }
        return $out;
    }

    /**
     * Which tab opens, and what each tab carries — worked out before the first byte of the row,
     * because the row stands above the panels whose fields decide it.
     *
     * The first tab with an error opens (a message in a closed tab is the problem this solves), even a
     * disabled one; else the tab posted back in TAB_FIELD (the user's place after a failed save);
     * else activeTab(); else the first tab that is not disabled. Only the first is the form's own
     * guess — the others are marked as chosen, so a remembered tab (tabsRemember) does not override them.
     *
     * @param list<array<string,mixed>> $fields
     * @return array{tabs: array<string, array<string,mixed>>, active: string, chosen: bool}|null
     */
    private function planTabs(array $fields): ?array
    {
        $tabs = [];
        $current = null;
        foreach ($fields as $f) {
            if ($f['type'] === '_tab') {
                $current = (string) $f['key'];
                $tabs[$current] = $f + ['errors' => 0];
                continue;
            }
            if ($f['type'] === '_tabs_end') { $current = null; continue; }
            // A field that renders an error slot and has a message in it. A hidden input shows none.
            if ($current !== null && isset($f['name']) && !in_array($f['type'], ['hidden', '_row_start', '_row_end'], true)
                && trim((string) ($f['error'] ?? '')) !== '') {
                $tabs[$current]['errors']++;
            }
        }
        if ($tabs === []) {
            if ($this->startTab !== null) {
                trigger_error("GridKit: form '{$this->id}': activeTab('{$this->startTab}') but the form has no tab().", E_USER_WARNING);
            }
            return null;
        }
        $open = static fn(string $k): bool => isset($tabs[$k]) && empty($tabs[$k]['disabled']);
        $active = null;
        foreach ($tabs as $k => $t) {
            if ($t['errors'] > 0) { $active = (string) $k; break; }
        }
        $posted = $this->values[self::TAB_FIELD] ?? null;
        // What was posted is the browser's word, not the page's: anything that names no open tab is
        // ignored without a warning, or every hand-edited request would write one into the log.
        if ($active === null && is_string($posted) && $open($posted)) $active = $posted;
        if ($active === null && $this->startTab !== null) {
            if ($open($this->startTab)) $active = $this->startTab;
            else trigger_error("GridKit: form '{$this->id}': activeTab('{$this->startTab}') names no tab that can be opened; the first is.", E_USER_WARNING);
        }
        $chosen = $active !== null;
        if ($active === null) {
            foreach ($tabs as $k => $t) {
                if ($open((string) $k)) { $active = (string) $k; break; }
            }
            $active ??= (string) array_key_first($tabs);
        }
        return ['tabs' => $tabs, 'active' => $active, 'chosen' => $chosen];
    }

    /** The classes of the row: a style, and for the card style the second-row shape when asked for. */
    private function tabNavClass(): string
    {
        if ($this->tabSecondary && $this->tabStyle !== 'karte') {
            trigger_error("GridKit: form '{$this->id}': tabsSecondary() is the second row of the 'karte' style; with '{$this->tabStyle}' it is ignored.", E_USER_WARNING);
        }
        return 'gk-tab-nav gk-tab-nav-' . $this->tabStyle
             . ($this->tabSecondary && $this->tabStyle === 'karte' ? ' gk-tab-nav-secondary' : '');
    }

    /** "1 error", "3 errors" — the word stays, colour alone never says it. */
    private static function tabErrorText(int $n): string
    {
        return Lang::t($n === 1 ? 'form.tab_errors_one' : 'form.tab_errors', ['n' => $n]);
    }

    /**
     * The hidden input and the row, before the first panel. The input carries the chosen tab (posted
     * as TAB_FIELD) and, for the script, the wording of the error badge in the page's language and
     * whether to remember the tab — the row itself stays the plain authored shape.
     */
    private function renderTabsStart(\Closure $e): void
    {
        $plan = $this->tabPlan;
        echo '<input type="hidden" name="' . self::TAB_FIELD . '" value="' . $e($plan['active']) . '" data-gk-form-tab'
           . ' data-gk-tab-error="' . $e(Lang::t('form.tab_errors_one', ['n' => '{n}'])) . '"'
           . ' data-gk-tab-errors="' . $e(Lang::t('form.tab_errors', ['n' => '{n}'])) . '"'
           . ($this->tabRemember ? ' data-gk-tab-remember' : '')
           . ($plan['chosen'] ? ' data-gk-tab-chosen' : '') . '>';
        echo '<div class="gk-tabs"><div class="' . $this->tabNavClass() . '">';
        foreach ($plan['tabs'] as $key => $t) {
            echo '<button type="button" class="gk-tab-btn' . ((string) $key === $plan['active'] ? ' gk-active' : '') . '"'
               . ' data-tab="' . $e($key) . '"' . ($t['disabled'] ? ' disabled' : '') . '>'
               . ($t['icon'] !== '' ? '<span class="material-icons gk-tab-icon" aria-hidden="true">' . $e($t['icon']) . '</span>' : '')
               . '<span>' . $e($t['label']) . '</span>'
               . ($t['badge'] !== '' ? '<span class="gk-tab-badge">' . $e($t['badge']) . '</span>' : '')
               . ($t['count'] !== null ? '<span class="gk-tab-count">' . $e($t['count']) . '</span>' : '')
               . ($t['errors'] > 0 ? '<span class="gk-tab-badge gk-tab-badge-error">' . $e(self::tabErrorText($t['errors'])) . '</span>' : '')
               . '</button>';
        }
        echo '</div>';
    }

    /** A tab's panel: the row first when it is the first, else the end of the panel before. */
    private function renderTabPanel(array $f, \Closure $e): void
    {
        if ($this->tabPlan === null) return;
        if ($this->panelOpen) echo '</div>';
        else $this->renderTabsStart($e);
        $key = (string) $f['key'];
        $surface = $this->tabStyle === 'karte' && !$this->tabSecondary ? ' gk-tab-flaeche' : '';
        echo '<div class="gk-tab-panel' . $surface . ($key === $this->tabPlan['active'] ? ' gk-active' : '') . '" data-tab="' . $e($key) . '">';
        $this->panelOpen = true;
    }

    /** The end of the last panel and of the tabs; nothing when none is open. */
    private function renderTabsEnd(): void
    {
        if (!$this->panelOpen) return;
        echo '</div></div>';
        $this->panelOpen = false;
    }
}
