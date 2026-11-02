<?php
use GridKit\Table;
use GridKit\Form;
use GridKit\Modal;
use GridKit\StatCards;
use GridKit\Sidebar;
use GridKit\FilterChips;
use GridKit\YearFilter;
use GridKit\TableHeader;
use GridKit\Header;
use GridKit\Button;
use GridKit\Theme;
use GridKit\Layout;
use GridKit\Lang;
?>
<!-- ===== FORM (merged: form + upload + color) ===== -->
<div class="demo-section" data-section="form">
    <h2>Form <?= since('form') ?></h2>

    <div class="demo-card">
        <h3 style="margin:0 0 12px; font-size:15px; color:var(--gk-on-surface, #374151);">Grid Layout (16 columns)</h3>
        <?php
        showcase(function () {
                    $form = new Form('article_form');
                    $form->action('save/process_article.php')
                        ->ajax()
                        ->hidden('article_id', '')
                        ->row()
                            ->field('article_number', 'Article No.', 'text', ['required' => true, 'width' => 8])
                            ->field('name', 'Description', 'text', ['required' => true, 'width' => 8])
                        ->endRow()
                        ->field('description', 'Description', 'textarea', ['rows' => 3])
                        ->row()
                            ->field('unit', 'Unit', 'select', ['options' => ['Stk' => 'Piece', 'h' => 'Hour', 'psch' => 'Flat rate'], 'width' => 5])
                            ->field('net_price', 'Net price', 'number', ['step' => '0.01', 'width' => 5])
                            ->field('tax_rate', 'VAT %', 'select', ['options' => ['20' => '20%', '10' => '10%', '0' => '0%'], 'width' => 6])
                        ->endRow()
                        ->field('is_active', 'Active', 'toggle', ['inline' => true])
                        ->submit('Save')
                        ->render();
        });
        ?>
    </div>

    <div class="demo-card">
        <h3 style="margin:0 0 12px; font-size:15px; color:var(--gk-on-surface, #374151);">Checkbox &amp; Radio <?= since('checkbox-radio') ?></h3>
        <?php
        $form2 = new Form('checkbox_radio_form');
        $form2->field('agree', 'Accept terms', 'checkbox', ['checked' => true])
            ->field('newsletter', 'Subscribe to newsletter', 'checkbox')
            ->field('payment', 'Payment method', 'radio', [
                'options' => ['card' => 'Credit card', 'bank' => 'Bank transfer', 'paypal' => 'PayPal'],
                'value' => 'card',
                'inline' => true,
            ])
            ->field('priority', 'Priority', 'radio', [
                'options' => ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High'],
                'value' => 'medium',
            ])
            ->render();
        ?>
    </div>

    <div class="demo-card">
        <h3 style="margin:0 0 12px; font-size:15px; color:var(--gk-on-surface, #374151);">Toggle &amp; Slider <?= since('toggle-range') ?></h3>
        <?php
        $form3 = new Form('toggle_slider_form');
        $form3->field('dark_mode', 'Dark Mode', 'toggle', ['inline' => true])
            ->field('notifications', 'Notifications', 'toggle', ['inline' => true, 'checked' => true])
            ->field('volume', 'Volume', 'range', ['min' => 0, 'max' => 100, 'step' => 1, 'value' => 50])
            ->field('brightness', 'Brightness', 'range', ['min' => 0, 'max' => 100, 'step' => 5, 'value' => 75])
            ->render();
        ?>
    </div>

    <div class="demo-card">
        <h3 style="margin:0 0 12px; font-size:15px; color:var(--gk-on-surface, #374151);">File Upload <?= since('upload') ?></h3>
        <?php
        $form4 = new Form('upload_form');
        $form4->field('document', 'Document', 'file', ['accept' => '.pdf,.doc,.docx', 'multiple' => true, 'maxSize' => '10MB'])
            ->render();
        ?>
    </div>

    <div class="demo-card">
        <h3 style="margin:0 0 8px; font-size:15px; color:var(--gk-on-surface, #374151);">RichText Editor (CKEditor 5) <?= since('richtext') ?></h3>
        <p class="demo-intro">Local vendor bundle (<code>assets/ckeditor5/</code>), no CDN. Initialization via <code>IntersectionObserver</code> — works in tabs and modals too.</p>
        <?php
        $form5 = new Form('richtext_form');
        $form5->field('content_basic', 'Content (Basic)', 'richtext', ['preset' => 'basic'])
            ->field('content_full', 'Content (Full)', 'richtext', ['preset' => 'full'])
            ->render();
        ?>
    </div>

    <div class="demo-card">
        <h3 style="margin:0 0 8px; font-size:15px; color:var(--gk-on-surface, #374151);">RichText with pictures</h3>
        <p class="demo-intro">
            Add <code>'upload' =&gt; '/your/endpoint'</code> and the editor gains a picture button,
            drag &amp; drop, paste, size handles and alt text. Without it nothing changes — an upload
            button with nowhere to put the file is worse than no button.
            <br><br>
            Pictures render as a plain <code>&lt;img&gt;</code> inside the paragraph, carrying their size
            as an inline <code>style</code> — deliberately <em>not</em> as <code>&lt;figure class="image"&gt;</code>,
            so the same content survives being sent as an e-mail, where no stylesheet loads and Outlook
            does not lay out <code>&lt;figure&gt;</code> reliably.
            <br><br>
            <strong>Try it:</strong> click the picture below to get the size handles and the alt-text button.
            The upload button works too, but this public demo stores nothing and says so.
        </p>
        <?php
        $demoBild = '<p>Text before the picture. A caption can simply be written around it.</p>'
                  . '<p><img style="width:60%;" src="/demo-table-light.png" alt="A GridKit table, light theme"></p>'
                  . '<p>And the text continues underneath.</p>';
        (new Form('richtext_images_form'))
            ->field('content_images', 'Newsletter body', 'richtext', [
                'preset' => 'full',
                'upload' => '/demo/api/upload-demo.php',
                'value'  => $demoBild,
            ])
            ->render();
        ?>
    </div>

    <div class="demo-card">
        <h3 style="margin:0 0 8px; font-size:15px; color:var(--gk-on-surface, #374151);">Clearable date/time fields</h3>
        <p class="demo-intro">Trash icon only appears when a value is set — disappears automatically after clearing.</p>
        <?php
        $formClearable = new Form('clearable_form');
        $formClearable
            ->field('datum',  'Date',     'date',     ['value' => date('Y-m-d'),      'clearable' => true])
            ->field('zeit',   'Time',     'time',     ['value' => '09:00',            'clearable' => true])
            ->field('termin', 'Appointment', 'datetime', ['value' => date('Y-m-d\TH:i'), 'clearable' => true])
            ->render();
        ?>
    </div>

    <div class="demo-card">
        <h3 style="margin:0 0 8px; font-size:15px; color:var(--gk-on-surface, #374151);">New field types</h3>
        <p class="demo-intro">Uniform height of 44px for all input types. No browser spinner for <code>number</code>.</p>
        <?php
        $formNew = new Form('new_fields_form');
        $formNew
            ->field('farbe', 'Color',  'color',  ['value' => '#6750a4'])
            ->field('monat', 'Month',  'month',  ['value' => date('Y-m')])
            ->field('kw',    'Week',   'week',   ['value' => date('Y-\WW')])
            ->field('preis', 'Price',  'number', ['placeholder' => '0.00'])
            ->render();
        ?>
    </div>

    <div class="demo-card">
        <h3 style="margin:0 0 12px; font-size:15px; color:var(--gk-on-surface, #374151);">Select extensions <?= since('select') ?></h3>
        <p style="margin:0 0 12px; font-size:13px; color:var(--gk-on-surface-variant, #6b7280);"><strong>Searchable Select</strong> – Country selection with search field</p>
        <?php
        $formSearch = new Form('searchable_select_demo');
        $formSearch->field('country', 'Country', 'select', [
                'options' => [
                    'AT' => 'Austria', 'DE' => 'Germany', 'CH' => 'Switzerland',
                    'IT' => 'Italy', 'FR' => 'France', 'ES' => 'Spain',
                    'GB' => 'United Kingdom', 'NL' => 'Netherlands', 'BE' => 'Belgium',
                    'PL' => 'Poland', 'CZ' => 'Czech Republic', 'HU' => 'Hungary',
                    'SK' => 'Slovakia', 'SI' => 'Slovenia', 'HR' => 'Croatia',
                    'SE' => 'Sweden', 'NO' => 'Norway', 'DK' => 'Denmark',
                    'FI' => 'Finland', 'PT' => 'Portugal',
                ],
                'searchable' => true, 'placeholder' => 'Search country...', 'value' => 'AT'
            ])->render();
        ?>
        <hr style="border:none; border-top:1px solid var(--gk-outline-variant); margin:20px 0;">
        <p style="margin:0 0 12px; font-size:13px; color:var(--gk-on-surface-variant, #6b7280);"><strong>Multi-Select</strong> – Select multiple categories (with chips)</p>
        <?php
        $formMulti = new Form('multiselect_demo');
        $formMulti->field('tags', 'Categories', 'multiselect', [
                'options' => ['web' => 'Webdesign', 'seo' => 'SEO', 'hosting' => 'Hosting', 'dev' => 'Development', 'support' => 'Support', 'beratung' => 'Consulting'],
                'value' => ['web', 'seo'], 'placeholder' => 'Search categories...', 'searchable' => true,
            ])->render();
        ?>
        <hr style="border:none; border-top:1px solid var(--gk-outline-variant); margin:20px 0;">
        <p style="margin:0 0 12px; font-size:13px; color:var(--gk-on-surface-variant, #6b7280);"><strong>Ajax Select</strong> – Customer search via API (min. 2 characters)</p>
        <?php
        $formAjax = new Form('ajax_select_demo');
        $formAjax->field('customer_id', 'Customer', 'ajaxselect', [
                'url' => 'api/search.php'   /* page-relative: the demo already lives in /demo/ */, 'value' => '', 'displayValue' => '', 'placeholder' => 'Search customer...',
                'labelField' => 'name', 'valueField' => 'id', 'subtextField' => 'city', 'minChars' => 2, 'searchParam' => 'q',
            ])->render();
        ?>
    </div>

    <div class="demo-code"><pre>// Grid Layout (16 columns)
$form->row()
    ->field('name', 'Name', 'text', ['width' => 8])
    ->field('email', 'E-Mail', 'email', ['width' => 8])
->endRow()
->field('agree', 'Accept terms', 'checkbox', ['checked' => true])
->field('payment', 'Payment method', 'radio', [
    'options' => ['card' => 'Credit card', 'bank' => 'Bank transfer'],
    'value' => 'card', 'inline' => true
])
->field('active', 'Active', 'toggle', ['inline' => true])
->field('volume', 'Volume', 'range', ['min' => 0, 'max' => 100, 'value' => 50])
->field('doc', 'Document', 'file', ['accept' => '.pdf', 'multiple' => true, 'maxSize' => '10MB'])
->field('content', 'Content', 'richtext', ['preset' => 'full'])
->field('datum',  'Date',    'date',     ['value' => date('Y-m-d'),      'clearable' => true])
->field('farbe', 'Color',  'color',  ['value' => '#6750a4'])
->field('preis', 'Price',  'number', ['placeholder' => '0.00'])</pre></div>

    <hr style="border:none;border-top:1px solid var(--gk-outline-variant);margin:40px 0">

    <h3>File Upload (extended)</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-bottom:24px;">
        <div class="demo-card" style="text-align:center;"><span class="material-icons" style="font-size:32px;color:var(--gk-primary);display:block;margin-bottom:8px;" aria-hidden="true">drag_indicator</span><strong>Drag &amp; Drop</strong><p class="demo-intro" style="margin:8px 0 0;">Select files via drag &amp; drop or click</p></div>
        <div class="demo-card" style="text-align:center;"><span class="material-icons" style="font-size:32px;color:var(--gk-primary);display:block;margin-bottom:8px;" aria-hidden="true">verified</span><strong>Validation</strong><p class="demo-intro" style="margin:8px 0 0;">Type, size (min/max), count and total size</p></div>
        <div class="demo-card" style="text-align:center;"><span class="material-icons" style="font-size:32px;color:var(--gk-primary);display:block;margin-bottom:8px;" aria-hidden="true">image</span><strong>Preview</strong><p class="demo-intro" style="margin:8px 0 0;">Image thumbnails directly in the upload zone</p></div>
        <div class="demo-card" style="text-align:center;"><span class="material-icons" style="font-size:32px;color:var(--gk-primary);display:block;margin-bottom:8px;" aria-hidden="true">list_alt</span><strong>Queue UI</strong><p class="demo-intro" style="margin:8px 0 0;">Progress indicator, states, errors per file</p></div>
    </div>

    <div class="demo-card">
        <h3 style="margin:0 0 8px;font-size:15px;color:var(--gk-on-surface, #374151);">Variant 1 — Simple</h3>
        <p class="demo-intro">Document upload with type filter and size limit.</p>
        <?php (new Form('up-simple'))->field('doc', 'Document', 'file', ['accept' => ['pdf', 'doc', 'docx'], 'maxSize' => '10 MB', 'hint' => 'PDF or Word, max. 10 MB'])->render(); ?>
    </div>

    <div class="demo-card">
        <h3 style="margin:0 0 8px;font-size:15px;color:var(--gk-on-surface, #374151);">Variant 2 — Images with preview</h3>
        <p class="demo-intro">Multi-upload with image thumbnails directly in the zone.</p>
        <?php (new Form('up-images'))->field('fotos', 'Photos', 'file', ['multiple' => true, 'preview' => true, 'accept' => ['jpg', 'jpeg', 'png', 'gif', 'webp'], 'maxSize' => '8 MB', 'maxFiles' => 6])->render(); ?>
    </div>

    <div class="demo-card">
        <h3 style="margin:0 0 8px;font-size:15px;color:var(--gk-on-surface, #374151);">Variant 3 — Full configuration</h3>
        <p class="demo-intro">All options combined: types, min/max size, total limit, file count.</p>
        <?php (new Form('up-full'))->field('attachments', 'Attachments', 'file', ['multiple' => true, 'preview' => true, 'accept' => ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'zip', 'doc', 'docx', 'txt'], 'minSize' => '1 KB', 'maxSize' => '10 MB', 'maxTotalSize' => '50 MB', 'maxFiles' => 10, 'hint' => 'Max. 10 MB/file · 50 MB total · 10 files'])->render(); ?>
    </div>

    <div class="demo-card">
        <h3 style="margin:0 0 8px;font-size:15px;color:var(--gk-on-surface, #374151);">Queue states live</h3>
        <p class="demo-intro">Manually cycle queue items through states: pending → uploading → done / error.</p>
        <div class="demo-btn-row" style="margin-bottom:16px;">
            <button class="gk-btn gk-btn-primary" id="btn-queue-sim"><span class="material-icons" style="font-size:16px;" aria-hidden="true">add_circle</span> Simulate file</button>
            <button class="gk-btn gk-btn-filled gk-btn-danger" id="btn-queue-err"><span class="material-icons" style="font-size:16px;" aria-hidden="true">error_outline</span> Simulate error</button>
        </div>
        <div id="queue-demo-list" style="display:flex;flex-direction:column;gap:8px;"></div>
    </div>

    <div class="demo-card">
        <h3 style="margin:0 0 8px;font-size:15px;color:var(--gk-on-surface, #374151);">Validation error demo</h3>
        <p class="demo-intro">Zone with strict limits — PDF only, max. 100 KB.</p>
        <?php (new Form('up-validate'))->field('strict_file', 'Strict (PDF, max. 100 KB)', 'file', ['accept' => ['pdf'], 'maxSize' => '100 KB', 'hint' => 'PDF only, max. 100 KB — other files will be rejected'])->render(); ?>
    </div>

    <hr style="border:none;border-top:1px solid var(--gk-outline-variant);margin:40px 0">

    <h3>Color Picker <?= since('color') ?></h3>
    <p class="demo-intro">Styled native color input — color swatch on the left (clickable) + hex field on the right. Swatch and hex value sync automatically, validated as <code>#RRGGBB</code>.</p>

    <div class="demo-card">
        <h3 style="margin:0 0 8px;font-size:15px;color:var(--gk-on-surface, #374151);">Live Demo</h3>
        <?php
        (new Form('color-demo'))
            ->field('primary',   'Primary color',   'color', ['value' => '#6750a4'])
            ->field('secondary', 'Secondary color', 'color', ['value' => '#2563eb'])
            ->field('accent',    'Accent color',   'color', ['value' => '#16a34a'])
            ->render();
        ?>
    </div>

    <div class="demo-card">
        <h3 style="margin:0 0 12px;font-size:15px;color:var(--gk-on-surface, #374151);">Behavior</h3>
        <ul style="margin:0;padding-left:20px;font-size:14px;line-height:1.8;color:var(--gk-on-surface-variant);">
            <li>Clicking the <strong>color swatch</strong> opens the native browser color picker</li>
            <li>The <strong>hex text field</strong> shows the current value and is directly editable</li>
            <li>Both fields synchronize in real time</li>
            <li>Validation: only valid <code>#RRGGBB</code> values are accepted</li>
            <li>Uniform <strong>44px height</strong> like all other GridKit inputs</li>
        </ul>
    </div>

    <!-- Form Density -->
    <div class="demo-card">
        <h3 style="margin:0 0 16px;font-size:15px;color:var(--gk-on-surface);">Form Density</h3>
        <p style="font-size:13px;color:var(--gk-on-surface-variant);margin:0 0 16px;">
            Wrap any form in <code>.gk-form-compact</code> for reduced spacing. All elements — inputs, selects, toggles, checkboxes — scale down proportionally.
        </p>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:24px;">
            <!-- Normal -->
            <div>
                <div style="font-weight:600;font-size:12px;text-transform:uppercase;letter-spacing:0.05em;color:var(--gk-on-surface-variant);margin-bottom:12px;">Normal (default)</div>
                <div style="border:1px solid var(--gk-outline-variant);border-radius:12px;padding:20px;">
                    <div class="gk-field">
                        <label class="gk-label-text">Firmenname</label>
                        <input type="text" class="gk-input" value="SSI GmbH">
                    </div>
                    <div class="gk-field">
                        <label class="gk-label-text">Kategorie</label>
                        <select class="gk-input">
                            <option>Technologie</option>
                            <option>Handel</option>
                            <option>Dienstleistung</option>
                        </select>
                    </div>
                    <div class="gk-field">
                        <label class="gk-label-text">Beschreibung</label>
                        <textarea class="gk-input" rows="2">Kurzbeschreibung...</textarea>
                    </div>
                    <div class="gk-field">
                        <div class="gk-field-inline">
                            <label class="gk-label-text" style="margin-bottom:0;">Aktiv</label>
                            <label class="gk-toggle"><input type="checkbox" checked><span class="gk-toggle-slider"></span></label>
                        </div>
                    </div>
                    <div class="gk-field">
                        <label class="gk-checkbox-wrap">
                            <input type="checkbox" checked>
                            <span class="gk-checkbox-custom"></span>
                            <span class="gk-checkbox-text">AGB akzeptiert</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Compact -->
            <div>
                <div style="font-weight:600;font-size:12px;text-transform:uppercase;letter-spacing:0.05em;color:var(--gk-on-surface-variant);margin-bottom:12px;">Compact <code style="font-size:11px;">.gk-form-compact</code></div>
                <div class="gk-form-compact" style="border:1px solid var(--gk-outline-variant);border-radius:12px;padding:20px;">
                    <div class="gk-field">
                        <label class="gk-label-text">Firmenname</label>
                        <input type="text" class="gk-input" value="SSI GmbH">
                    </div>
                    <div class="gk-field">
                        <label class="gk-label-text">Kategorie</label>
                        <select class="gk-input">
                            <option>Technologie</option>
                            <option>Handel</option>
                            <option>Dienstleistung</option>
                        </select>
                    </div>
                    <div class="gk-field">
                        <label class="gk-label-text">Beschreibung</label>
                        <textarea class="gk-input" rows="2">Kurzbeschreibung...</textarea>
                    </div>
                    <div class="gk-field">
                        <div class="gk-field-inline">
                            <label class="gk-label-text" style="margin-bottom:0;">Aktiv</label>
                            <label class="gk-toggle"><input type="checkbox" checked><span class="gk-toggle-slider"></span></label>
                        </div>
                    </div>
                    <div class="gk-field">
                        <label class="gk-checkbox-wrap">
                            <input type="checkbox" checked>
                            <span class="gk-checkbox-custom"></span>
                            <span class="gk-checkbox-text">AGB akzeptiert</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="demo-code">
        <pre>&lt;!-- Normal (default) --&gt;
&lt;form&gt;
  &lt;div class="gk-field"&gt;...&lt;/div&gt;
&lt;/form&gt;

&lt;!-- Compact --&gt;
&lt;form class="gk-form-compact"&gt;
  &lt;div class="gk-field"&gt;...&lt;/div&gt;
&lt;/form&gt;

&lt;!-- Also works as wrapper --&gt;
&lt;div class="gk-form-compact"&gt;
  &lt;div class="gk-card"&gt;
    &lt;div class="gk-field"&gt;...&lt;/div&gt;
  &lt;/div&gt;
&lt;/div&gt;</pre>
    </div>

    <h3 class="demo-h3" id="form-values">Fill from a record, show errors</h3>
    <p class="demo-intro"><code>values()</code> fills every field whose name is a key of the record (a field's own <code>'value'</code> wins), <code>errors()</code> puts each message under its field, and a message that names no field is shown once above the form. Together they are an edit form shown again after a failed save.</p>
        <?php
        showcase(function () {
            $invoice = ['number' => 'INV-2026-007', 'customer' => '', 'status' => 'draft', 'amount' => '12,5', 'ignored' => 'a key without a field'];
            (new Form('demo-values'))
                ->field('number', 'Number', 'text', ['width' => 8, 'required' => true])
                ->field('status', 'Status', 'select', ['width' => 8, 'options' => ['draft' => 'Draft', 'paid' => 'Paid']])
                ->field('customer', 'Customer', 'text', ['required' => true])
                ->field('amount', 'Amount', 'text')
                ->values($invoice)
                ->errors(['customer' => 'Required', 'amount' => 'Not a number', '_form' => 'The invoice was not saved.'])
                ->render();
        });
        ?>
</div>

