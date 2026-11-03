<?php
/**
 * The page ci/browser-form-tabs.js drives (Form::tab(), 1.113.0): forms with tabs — a plain one, one shown
 * again with server errors, one with a posted tab, one that remembers, one with showIf across tabs, one
 * inside a page's own tabs with the same keys, one in a modal, an AJAX one, one with a required rich-text
 * field — and the markup of one more for a form that arrives later. One self-contained HTML document,
 * stylesheet and script inlined, so it loads from file:// with no server.
 *
 *     php ci/browser-form-tabs-fixture.php > /tmp/f.html
 *
 * Field names carry the form's prefix: ids are field names, and one page holds many forms.
 */

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use GridKit\{Form, Lang};

Lang::set('en');
$root = dirname(__DIR__);

/** A customer form: a header field, three tabs (a required field in the second), a footer field. */
function ftCustomer(string $id, string $p): Form
{
    return (new Form($id))->action('about:blank')
        ->field($p . 'number', 'Customer no.', 'text', ['value' => 'C-7'])
        ->tab('general', 'General', ['icon' => 'person'])
            ->field($p . 'name', 'Name', 'text', ['required' => true, 'value' => 'Ada'])
            ->field($p . 'email', 'Email', 'email', ['value' => 'ada@example.org'])
        ->tab('address', 'Address', ['icon' => 'home'])
            ->field($p . 'street', 'Street', 'text')
            ->field($p . 'city', 'City', 'text', ['required' => true])
            ->field($p . 'zip', 'Postcode', 'text', ['required' => true])
        ->tab('notes', 'Notes', ['count' => 2])
            ->field($p . 'note', 'Note', 'textarea')
        ->endTabs()
        ->field($p . 'confirm', 'I checked the record', 'checkbox')
        ->submit('Save');
}

function ftHtml(Form $form): string
{
    ob_start();
    $form->render();
    return (string) ob_get_clean();
}

$forms = [];
$forms['main'] = ftHtml(ftCustomer('f-main', 'm_'));
// Shown again after a failed save: the posted tab is 'notes', the errors are in 'address' — the errors win.
$forms['server'] = ftHtml(ftCustomer('f-server', 's_')
    ->values(['s_name' => 'Ada', '_gk_tab' => 'notes'])
    ->errors(['s_city' => 'Required', 's_zip' => 'Not a postcode']));
// Shown again without errors: the posted tab opens.
$forms['posted'] = ftHtml(ftCustomer('f-posted', 'p_')->values([Form::TAB_FIELD => 'notes']));
$forms['remember'] = ftHtml(ftCustomer('f-remember', 'r_')->tabsRemember());
$forms['chosen'] = ftHtml(ftCustomer('f-chosen', 'c_')->tabsRemember()->activeTab('address'));
$forms['showif'] = ftHtml((new Form('f-showif'))->action('about:blank')
    ->tab('kind', 'Kind')
        ->field('i_kind', 'Kind', 'select', ['native' => true, 'value' => 'person', 'options' => ['person' => 'Person', 'company' => 'Company']])
    ->tab('company', 'Company')
        ->field('i_company', 'Company name', 'text', ['required' => true, 'showIf' => ['field' => 'i_kind', 'equals' => 'company']])
        ->field('i_vat', 'VAT id', 'text')
    ->submit('Save'));
// An error above the tabs, in a form inside a page tab whose key ('general') the form's first tab repeats.
$forms['nested'] = ftHtml(ftCustomer('f-nested', 'n_')->tabsSecondary()->errors(['n_number' => 'Taken']));
// A server error in 'notes', which therefore opens; the modal is hidden while the script starts.
$forms['modal'] = ftHtml(ftCustomer('f-modal', 'd_')->tabsSecondary()->errors(['d_note' => 'Too long']));
$forms['ajax'] = ftHtml(ftCustomer('f-ajax', 'a_')->action('save.php')->ajax());
$forms['rich'] = ftHtml((new Form('f-rich'))->action('about:blank')
    ->tab('head', 'Subject')
        ->field('t_subject', 'Subject', 'text', ['value' => 'Hello'])
    ->tab('body', 'Body')
        ->field('t_body', 'Body', 'richtext', ['required' => true, 'preset' => 'basic'])
    ->submit('Send'));
// A required field in a disabled tab still holds the submit up: its tab opens all the same.
$forms['disabled'] = ftHtml((new Form('f-disabled'))->action('about:blank')
    ->tab('first', 'First')
        ->field('x_first', 'First', 'text', ['value' => 'ok'])
    ->tab('locked', 'Locked', ['disabled' => true])
        ->field('x_locked', 'Locked', 'text', ['required' => true])
    ->tab('last', 'Last')
        ->field('x_last', 'Last', 'text')
    ->tabsRemember()
    ->submit('Save'));
$forms['styles'] = '';
foreach (['linie', 'kapsel', 'text'] as $style) {
    $forms['styles'] .= ftHtml(ftCustomer("f-$style", "$style" . '_')->tabsStyle($style)->errors(["{$style}_city" => 'Required']));
}
$late = ftHtml(ftCustomer('f-late', 'l_')->tabsRemember());

$head = '<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>1.113.0</title><style>'
      . file_get_contents($root . '/css/gridkit.css') . '</style></head>';
$script = '<script>' . file_get_contents($root . '/js/gridkit.js') . '</script>';

$section = static fn(string $id, string $title, string $body): string =>
    '<section id="s-' . $id . '" style="margin:0 0 32px"><h2 style="font-size:16px">' . $title . '</h2>' . $body . '</section>';

$nested = '<div class="gk-tabs" id="outer">'
        . '<div class="gk-tab-nav gk-tab-nav-karte">'
        . '<button type="button" class="gk-tab-btn gk-active" data-tab="general">Outer general</button>'
        . '<button type="button" class="gk-tab-btn" data-tab="address">Outer address</button>'
        . '</div>'
        . '<div class="gk-tab-panel gk-tab-flaeche gk-active" data-tab="general">' . $forms['nested'] . '</div>'
        . '<div class="gk-tab-panel gk-tab-flaeche" data-tab="address"><p id="outer-address">The outer address panel.</p></div>'
        . '</div>';

$modal = '<button type="button" id="open-modal">Open the window</button>'
       . '<div class="gk-modal-overlay" id="modal" hidden><div class="gk-modal gk-modal-large">'
       . '<div class="gk-modal-header"><h3 class="gk-modal-title">Customer</h3><button type="button" class="gk-modal-close">&times;</button></div>'
       . '<div class="gk-modal-body">' . $forms['modal'] . '</div></div></div>';

echo '<!DOCTYPE html><html lang="en">' . $head . '<body class="gk-root">' . Lang::jsConfig()
   . '<main style="padding:16px;max-width:900px">'
   . $section('main', 'Plain', $forms['main'])
   . $section('server', 'Server errors', $forms['server'])
   . $section('posted', 'Posted tab', $forms['posted'])
   . $section('remember', 'Remember', $forms['remember'])
   . $section('chosen', 'Remember, but the page chose', $forms['chosen'])
   . $section('showif', 'showIf across tabs', $forms['showif'])
   . $section('nested', 'Inside the page\'s own tabs', $nested)
   . $section('modal', 'In a modal', $modal)
   . $section('ajax', 'AJAX', $forms['ajax'])
   . $section('rich', 'Rich text', $forms['rich'])
   . $section('disabled', 'A disabled tab', $forms['disabled'])
   . $section('styles', 'Styles', $forms['styles'])
   . '<template id="late">' . $late . '</template><div id="late-box"></div>'
   . '</main>'
   . $script . '</body></html>';
