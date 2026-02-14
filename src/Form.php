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

    public function __construct(string $id)
    {
        $this->id = $id;
    }

    public function action(string $url): static { $this->action = $url; return $this; }
    public function ajax(): static { $this->isAjax = true; return $this; }
    public function submit(string $label): static { $this->submitLabel = $label; return $this; }

    public function hidden(string $name, mixed $value): static
    {
        $this->fields[] = ['type' => 'hidden', 'name' => $name, 'value' => $value];
        return $this;
    }

    public function row(): static
    {
        $this->fields[] = ['type' => '_row_start'];
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
        $attrs = 'class="gk-root gk-form" id="' . $e($this->id) . '"';
        if ($this->action) $attrs .= ' action="' . $e($this->action) . '"';
        if ($this->isAjax) $attrs .= ' data-gk-ajax';

        echo "<form {$attrs}>";

        foreach ($this->fields as $f) {
            match ($f['type']) {
                'hidden' => printf('<input type="hidden" name="%s" value="%s">', $e($f['name']), $e($f['value'])),
                '_row_start' => print('<div class="gk-row">'),
                '_row_end' => print('</div>'),
                default => $this->renderField($f),
            };
        }

        if ($this->submitLabel) {
            echo '<div class="gk-form-actions">' . Button::render($this->submitLabel, ['variant' => 'filled', 'color' => 'success', 'type' => 'submit', 'icon' => 'save']) . '</div>';
        }

        echo '</form>';
    }

    private function renderField(array $f): void
    {
        $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        $name = $f['name'];
        $label = $f['label'] ?? '';
        $type = $f['type'];
        $value = $e($f['value'] ?? '');
        $req = ($f['required'] ?? false) ? ' required' : '';
        $width = $f['width'] ?? 16;
        $cls = "gk-field gk-w-{$width}";

        echo "<div class=\"{$cls}\">";
        if ($label && $type !== 'checkbox') {
            echo '<label class="gk-label-text" for="' . $e($name) . '">' . $e($label);
            if ($req) echo ' <span class="gk-required">*</span>';
            echo '</label>';
        }
        echo '<div class="gk-input-wrap">';

        switch ($type) {
            case 'textarea':
                $rows = $f['rows'] ?? 3;
                echo "<textarea name=\"{$e($name)}\" id=\"{$e($name)}\" rows=\"{$rows}\" class=\"gk-input\"{$req}>{$value}</textarea>";
                break;

            case 'select':
                echo "<select name=\"{$e($name)}\" id=\"{$e($name)}\" class=\"gk-input\"{$req}>";
                foreach ($f['options'] ?? [] as $k => $v) {
                    $sel = (string)$k === (string)($f['value'] ?? '') ? ' selected' : '';
                    echo "<option value=\"{$e($k)}\"{$sel}>{$e($v)}</option>";
                }
                echo '</select>';
                break;

            case 'toggle':
                echo "<label class=\"gk-toggle\"><input type=\"checkbox\" name=\"{$e($name)}\" id=\"{$e($name)}\" value=\"1\"" . ($value ? ' checked' : '') . "><span class=\"gk-toggle-slider\"></span></label>";
                break;

            case 'checkbox':
                echo "<label class=\"gk-checkbox-label\"><input type=\"checkbox\" name=\"{$e($name)}\" id=\"{$e($name)}\" value=\"1\"{$req}" . ($value ? ' checked' : '') . "> {$e($label)}</label>";
                break;

            case 'radio':
                foreach ($f['options'] ?? [] as $k => $v) {
                    $chk = (string)$k === (string)($f['value'] ?? '') ? ' checked' : '';
                    echo "<label class=\"gk-radio-label\"><input type=\"radio\" name=\"{$e($name)}\" value=\"{$e($k)}\"{$chk}> {$e($v)}</label>";
                }
                break;

            case 'file':
                echo "<input type=\"file\" name=\"{$e($name)}\" id=\"{$e($name)}\" class=\"gk-input\"{$req}>";
                break;

            default: // text, number, email, tel, url, password, date, time, datetime
                $htmlType = $type === 'datetime' ? 'datetime-local' : $type;
                $extra = '';
                if (isset($f['step'])) $extra .= " step=\"{$e($f['step'])}\"";
                if (isset($f['placeholder'])) $extra .= " placeholder=\"{$e($f['placeholder'])}\"";
                echo "<input type=\"{$e($htmlType)}\" name=\"{$e($name)}\" id=\"{$e($name)}\" value=\"{$value}\" class=\"gk-input\"{$req}{$extra}>";
        }

        echo '<div class="gk-field-error" data-gk-error="' . $e($name) . '"></div>';
        echo '</div></div>';
    }
}
