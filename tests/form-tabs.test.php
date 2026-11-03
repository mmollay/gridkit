<?php
/**
 * Form::tab() (1.113.0): tabs inside a form.
 *
 * The markup is checked here — the frame byte for byte, the grouping of fields into panels, the tab the
 * server's errors open, the badges, the hidden _gk_tab field and what values() hands back through it, and the
 * warnings for what cannot be used. What the script does with it (the jump to a rejected field in a hidden
 * tab, the live badges, remembering) is checked in a browser: ci/browser-form-tabs.js.
 *
 * A field's own markup is never written out here: it is taken from the same field in a form without tabs, so
 * this file says "the tabs put the fields where they belong and add nothing to them", and a change to how a
 * label renders is the business of tests/golden-form, not of this file.
 */

declare(strict_types=1);

use GridKit\{Form, Lang};

/** @return array{0:string,1:list<string>} the markup and the warnings GridKit raised, building and rendering */
function ftbRender(callable $build): array
{
    Lang::set('en');
    $notes = [];
    set_error_handler(function (int $no, string $msg) use (&$notes): bool {
        if ($no !== E_USER_WARNING) return false;
        $notes[] = $msg;
        return true;
    });
    try {
        $form = $build();
        $html = T::capture(fn() => $form->render());
    } finally {
        restore_error_handler();
    }
    return [$html, $notes];
}

/** The markup of one field, as a form without tabs renders it (the <form> around it taken off). */
function ftbField(string $name, string $label, string $type = 'text', array $opts = []): string
{
    $html = T::capture(fn() => (new Form('x'))->field($name, $label, $type, $opts)->render());
    return (string) preg_replace('~^<form[^>]*>|</form>$~', '', $html);
}

/** The keys of the tabs, the active one and the panels, read back out of the markup. */
function ftbTabs(string $html): array
{
    preg_match_all('~<button type="button" class="gk-tab-btn( gk-active)?" data-tab="([^"]*)"~', $html, $b, PREG_SET_ORDER);
    preg_match_all('~<div class="gk-tab-panel[^"]*" data-tab="([^"]*)"~', $html, $p);
    preg_match('~name="_gk_tab" value="([^"]*)"~', $html, $v);
    $active = array_values(array_filter($b, fn($m) => $m[1] !== ''));
    return [
        'tabs'   => array_map(fn($m) => html_entity_decode($m[2], ENT_QUOTES), $b),
        'active' => isset($active[0]) ? html_entity_decode($active[0][2], ENT_QUOTES) : null,
        'panels' => array_map(fn($k) => html_entity_decode($k, ENT_QUOTES), $p[1]),
        'field'  => isset($v[1]) ? html_entity_decode($v[1], ENT_QUOTES) : null,
        'chosen' => str_contains($html, ' data-gk-tab-chosen'),
    ];
}

/** A customer form: a header field, three tabs, a footer field, a submit. */
function ftbCustomer(): Form
{
    return (new Form('cust'))
        ->field('number', 'Number', 'text')
        ->tab('general', 'General')
            ->field('name', 'Name', 'text', ['required' => true])
        ->tab('address', 'Address')
            ->field('city', 'City', 'text', ['required' => true])
            ->field('zip', 'Postcode', 'text')
        ->tab('notes', 'Notes')
            ->field('note', 'Note', 'textarea')
        ->endTabs()
        ->field('confirm', 'Checked', 'checkbox')
        ->submit('Save');
}

/** @return array<string,callable> */
return [

'the frame is the authored tab markup, byte for byte, with the hidden tab field before it' => function (): void {
    [$html, $notes] = ftbRender(fn() => (new Form('f'))
        ->field('top', 'Top', 'text')
        ->tab('a', 'A', ['icon' => 'person'])
            ->field('x', 'X', 'text')
        ->tab('b', 'B', ['badge' => 'New', 'count' => 3, 'disabled' => true])
        ->endTabs()
        ->field('z', 'Z', 'text'));
    T::eq($notes, [], 'a usable form raised a warning');
    $expected = '<form class="gk-form" id="f" method="post">'
        . ftbField('top', 'Top')
        . '<input type="hidden" name="_gk_tab" value="a" data-gk-form-tab data-gk-tab-error="{n} error" data-gk-tab-errors="{n} errors">'
        . '<div class="gk-tabs"><div class="gk-tab-nav gk-tab-nav-karte">'
        . '<button type="button" class="gk-tab-btn gk-active" data-tab="a"><span class="material-icons gk-tab-icon" aria-hidden="true">person</span><span>A</span></button>'
        . '<button type="button" class="gk-tab-btn" data-tab="b" disabled><span>B</span><span class="gk-tab-badge">New</span><span class="gk-tab-count">3</span></button>'
        . '</div>'
        . '<div class="gk-tab-panel gk-tab-flaeche gk-active" data-tab="a">' . ftbField('x', 'X') . '</div>'
        . '<div class="gk-tab-panel gk-tab-flaeche" data-tab="b"></div>'
        . '</div>'
        . ftbField('z', 'Z')
        . '</form>';
    T::eq($html, $expected, 'the markup of a form with tabs');
},

'fields go into the tab they follow; before the first tab above, after endTabs() below' => function (): void {
    [$html, $notes] = ftbRender(fn() => ftbCustomer());
    T::eq($notes, [], 'a usable form raised a warning');
    $at = fn(string $needle): int => (int) strpos($html, $needle);
    T::ok($at('name="number"') < $at('data-gk-form-tab'), 'a field before the first tab() is not above the tabs');
    T::ok($at('name="confirm"') > $at('data-tab="notes">'), 'a field after endTabs() is not below the tabs');
    preg_match_all('~<div class="gk-tab-panel[^"]*" data-tab="(\w+)">~', $html, $m, PREG_OFFSET_CAPTURE);
    $starts = array_column($m[1], 1);
    T::eq(array_column($m[1], 0), ['general', 'address', 'notes'], 'one panel per tab, in order');
    T::ok($at('name="name"') > $starts[0] && $at('name="name"') < $starts[1], 'name is not in the panel of general');
    T::ok($at('name="city"') > $starts[1] && $at('name="zip"') < $starts[2], 'city and zip are not in the panel of address');
    T::ok($at('name="note"') > $starts[2], 'note is not in the panel of notes');
    // Everything of the form between the start of the last panel and the end of the tabs is that panel's.
    T::contains($html, ftbField('note', 'Note', 'textarea') . '</div></div>' . ftbField('confirm', 'Checked', 'checkbox'),
        'the last panel and the tabs do not end right after its field, before the footer field');
    T::ok($at('gk-form-actions') > $at('name="confirm"'), 'the actions are not below everything');
},

'without endTabs() the tabs run to the end, the actions stay below them' => function (): void {
    [$html] = ftbRender(fn() => (new Form('f'))->tab('a', 'A')->field('x', 'X', 'text')->tab('b', 'B')->field('y', 'Y', 'text')->submit('Save'));
    T::contains($html, ftbField('y', 'Y') . '</div></div><div class="gk-form-actions">', 'the tabs are not closed before the actions');
    T::eq(substr_count($html, '<div class="gk-tabs">'), 1, 'not one set of tabs');
},

'every button is type="button", every panel is rendered and nothing in a hidden panel is disabled' => function (): void {
    [$html] = ftbRender(fn() => ftbCustomer());
    // A <button> in a <form> without a type is a submit button: a click on a tab sent the form.
    preg_match_all('~<button[^>]*class="gk-tab-btn~', $html, $m);
    T::eq(count($m[0]), 3, 'not three tab buttons');
    foreach ($m[0] as $tag) T::contains($tag, 'type="button"', 'a tab button that would submit the form');
    T::eq(substr_count($html, 'class="gk-tab-panel'), 3, 'a panel is missing');
    T::notContains($html, ' disabled', 'a hidden panel or one of its fields is disabled — it would not be posted');
    T::eq(substr_count($html, ' hidden'), 0, 'a panel is hidden by attribute rather than by its class');
},

'icon, badge, count and label are escaped' => function (): void {
    [$html, $notes] = ftbRender(fn() => (new Form('f'))
        ->tab('k"<x>', 'L "<b>&', ['icon' => 'a"<i>', 'badge' => 'B"<i>', 'count' => '7'])->field('x', 'X', 'text'));
    T::eq($notes, [], 'escaping raised a warning');
    T::notContains($html, '<b>', 'the label wrote a tag');
    T::notContains($html, '<i>', 'the icon or the badge wrote a tag');
    T::contains($html, 'data-tab="k&quot;&lt;x&gt;"', 'the key is not escaped in data-tab');
    T::contains($html, 'name="_gk_tab" value="k&quot;&lt;x&gt;"', 'the key is not escaped in the hidden field');
    T::contains($html, '<span>L &quot;&lt;b&gt;&amp;</span>', 'the label is not escaped');
    T::contains($html, '<span class="gk-tab-badge">B&quot;&lt;i&gt;</span>', 'the badge is not escaped');
    T::contains($html, '<span class="gk-tab-count">7</span>', 'a count given as a string of digits is not shown');
},

'the style of the row, and the second-row shape for a window' => function (): void {
    $nav = function (callable $tweak): array {
        [$html, $notes] = ftbRender(fn() => $tweak((new Form('f'))->tab('a', 'A')->field('x', 'X', 'text')));
        preg_match('~<div class="(gk-tab-nav[^"]*)">~', $html, $m);
        return [$m[1] ?? '', substr_count($html, 'gk-tab-flaeche'), $notes];
    };
    T::eq($nav(fn(Form $f) => $f), ['gk-tab-nav gk-tab-nav-karte', 1, []], 'the default is not the card style with its surface');
    T::eq($nav(fn(Form $f) => $f->tabsSecondary()), ['gk-tab-nav gk-tab-nav-karte gk-tab-nav-secondary', 0, []], 'tabsSecondary() is not the second row without a surface');
    T::eq($nav(fn(Form $f) => $f->tabsSecondary()->tabsSecondary(false)), ['gk-tab-nav gk-tab-nav-karte', 1, []], 'tabsSecondary(false) does not switch it off');
    foreach (['linie', 'kapsel', 'text'] as $style) {
        T::eq($nav(fn(Form $f) => $f->tabsStyle($style)), ["gk-tab-nav gk-tab-nav-$style", 0, []], "tabsStyle('$style')");
    }
    [$class, $surface, $notes] = $nav(fn(Form $f) => $f->tabsStyle('linie')->tabsSecondary());
    T::eq([$class, $surface], ['gk-tab-nav gk-tab-nav-linie', 0], 'tabsSecondary() with another style is not ignored');
    T::ok(count($notes) === 1 && str_contains($notes[0], 'tabsSecondary()'), 'tabsSecondary() with another style raised no warning');
    [$class, $surface, $notes] = $nav(fn(Form $f) => $f->tabsStyle('cards'));
    T::eq([$class, $surface], ['gk-tab-nav gk-tab-nav-karte', 1], 'an unknown style does not fall back to the card style');
    T::ok(count($notes) === 1 && str_contains($notes[0], "tabsStyle('cards')"), 'an unknown style raised no warning');
},

'the server\'s errors open the first tab that has one, and every such tab carries the count' => function (): void {
    [$html] = ftbRender(fn() => ftbCustomer()->errors(['city' => 'Required', 'zip' => 'Not a postcode', 'note' => 'Too long']));
    $t = ftbTabs($html);
    T::eq($t['active'], 'address', 'the first tab with an error is not the active one');
    T::eq($t['field'], 'address', 'the hidden tab field does not say so');
    T::ok($t['chosen'], 'a tab opened by an error is not marked as chosen (a remembered one would override it)');
    T::contains($html, 'class="gk-tab-panel gk-tab-flaeche gk-active" data-tab="address"', 'the panel of the error tab is not the visible one');
    T::contains($html, '<span>Address</span><span class="gk-tab-badge gk-tab-badge-error">2 errors</span></button>', 'two errors are not counted on their tab');
    T::contains($html, '<span>Notes</span><span class="gk-tab-badge gk-tab-badge-error">1 error</span></button>', 'one error is not "1 error"');
    T::contains($html, '<span>General</span></button>', 'a tab without an error carries a badge');
    T::eq(substr_count($html, 'gk-tab-badge-error'), 2, 'not two error badges');
},

'what counts as an error of a tab' => function (): void {
    // A field's own 'error' counts; a message for the header, for a field-less key or a blank one does not.
    [$html] = ftbRender(fn() => (new Form('f'))
        ->field('top', 'Top', 'text')
        ->tab('a', 'A')->field('x', 'X', 'text')
        ->tab('b', 'B')->field('y', 'Y', 'text', ['error' => 'Own message'])->field('w', 'W', 'text')
        ->hidden('h', '1')
        ->errors(['top' => 'Header', '_form' => 'Not saved', 'w' => '   ', 'h' => 'ignored']));
    $t = ftbTabs($html);
    T::eq($t['active'], 'b', "a field's own 'error' does not open its tab");
    T::contains($html, '<span>B</span><span class="gk-tab-badge gk-tab-badge-error">1 error</span>', "a blank message or a hidden input counted, or the field's own did not");
    T::contains($html, '<span>A</span></button>', 'an error above the tabs put a badge on a tab');
    // Errors only above the tabs: no tab is chosen for them, the field is in sight anyway.
    [$html] = ftbRender(fn() => (new Form('f'))->field('top', 'Top', 'text')->tab('a', 'A')->tab('b', 'B')->errors(['top' => 'Header']));
    T::eq([ftbTabs($html)['active'], ftbTabs($html)['chosen']], ['a', false], 'an error above the tabs chose a tab');
},

'the badge is in the page\'s language, and the script gets the same words' => function (): void {
    Lang::set('de');
    try {
        $html = T::capture(fn() => ftbCustomer()->errors(['city' => 'Pflicht', 'zip' => 'Falsch'])->render());
    } finally {
        Lang::set('en');
    }
    T::contains($html, '<span class="gk-tab-badge gk-tab-badge-error">2 Fehler</span>', 'the German badge');
    T::contains($html, 'data-gk-tab-error="{n} Fehler" data-gk-tab-errors="{n} Fehler"', 'the script does not get the German words');
},

'the _gk_tab round trip: posted back through values(), it opens the same tab' => function (): void {
    T::eq(Form::TAB_FIELD, '_gk_tab', 'the name of the hidden tab field');
    [$html] = ftbRender(fn() => ftbCustomer());
    $t = ftbTabs($html);
    T::eq([$t['active'], $t['field'], $t['chosen']], ['general', 'general', false], 'a fresh form does not start on its first tab, unchosen');
    // The posted form, shown again: the value of the hidden field opens its tab.
    parse_str('name=Ada&city=Wien&' . Form::TAB_FIELD . '=' . $t['field'], $post);
    $post[Form::TAB_FIELD] = 'notes';
    [$html] = ftbRender(fn() => ftbCustomer()->values($post));
    $t = ftbTabs($html);
    T::eq([$t['active'], $t['field'], $t['chosen']], ['notes', 'notes', true], 'the posted tab does not open');
    T::contains($html, 'name="name" id="name" value="Ada"', 'values() no longer fills the fields beside it');
    // Errors beat the posted tab; the posted tab beats activeTab(); activeTab() beats the first.
    [$html] = ftbRender(fn() => ftbCustomer()->values($post)->errors(['name' => 'Taken']));
    T::eq(ftbTabs($html)['active'], 'general', 'the posted tab won over an error');
    [$html] = ftbRender(fn() => ftbCustomer()->activeTab('address')->values($post));
    T::eq(ftbTabs($html)['active'], 'notes', 'activeTab() won over the posted tab');
    [$html] = ftbRender(fn() => ftbCustomer()->activeTab('address'));
    T::eq([ftbTabs($html)['active'], ftbTabs($html)['chosen']], ['address', true], 'activeTab() does not open its tab');
    [$html] = ftbRender(fn() => ftbCustomer()->activeTab('address')->activeTab(''));
    T::eq(ftbTabs($html)['active'], 'general', "activeTab('') is not 'no choice'");
},

'a posted tab is the browser\'s word: what names no open tab is ignored, without a warning' => function (): void {
    foreach ([['nope'], [['notes']], [42], [''], ['locked']] as [$posted]) {
        [$html, $notes] = ftbRender(fn() => (new Form('f'))
            ->tab('general', 'General')->field('x', 'X', 'text')
            ->tab('locked', 'Locked', ['disabled' => true])
            ->tab('notes', 'Notes')
            ->values([Form::TAB_FIELD => $posted]));
        T::eq([ftbTabs($html)['active'], $notes], ['general', []], 'posted ' . json_encode($posted));
    }
},

'a numeric key is a key like any other' => function (): void {
    [$html] = ftbRender(fn() => (new Form('f'))->tab('1', 'One')->tab('2', 'Two')->values([Form::TAB_FIELD => '2']));
    T::eq(ftbTabs($html), ['tabs' => ['1', '2'], 'active' => '2', 'panels' => ['1', '2'], 'field' => '2', 'chosen' => true], 'numeric keys');
},

'a disabled tab is never the start, but an error opens it' => function (): void {
    $form = fn() => (new Form('f'))
        ->tab('locked', 'Locked', ['disabled' => true])->field('x', 'X', 'text')
        ->tab('open', 'Open')->field('y', 'Y', 'text');
    [$html] = ftbRender($form);
    T::eq(ftbTabs($html)['active'], 'open', 'a disabled first tab was the start');
    [$html, $notes] = ftbRender(fn() => $form()->activeTab('locked'));
    T::eq(ftbTabs($html)['active'], 'open', 'activeTab() opened a disabled tab');
    T::ok(count($notes) === 1 && str_contains($notes[0], "activeTab('locked')"), 'activeTab() on a disabled tab raised no warning');
    [$html] = ftbRender(fn() => $form()->errors(['x' => 'Bad']));
    T::eq(ftbTabs($html)['active'], 'locked', 'an error in a disabled tab did not open it — nobody could reach the message');
    [$html] = ftbRender(fn() => (new Form('f'))->tab('a', 'A', ['disabled' => true])->tab('b', 'B', ['disabled' => true]));
    T::eq(ftbTabs($html)['active'], 'a', 'with every tab disabled, the first is not the one shown');
},

'remember and chosen are written for the script' => function (): void {
    [$html] = ftbRender(fn() => ftbCustomer()->tabsRemember());
    T::contains($html, ' data-gk-tab-remember>', 'tabsRemember() is not written');
    T::notContains($html, 'data-gk-tab-chosen', 'a form that chose nothing says it did');
    [$html] = ftbRender(fn() => ftbCustomer()->tabsRemember()->tabsRemember(false));
    T::notContains($html, 'data-gk-tab-remember', 'tabsRemember(false) does not switch it off');
},

'showIf reaches across tabs' => function (): void {
    [$html, $notes] = ftbRender(fn() => (new Form('f'))
        ->tab('a', 'A')->field('kind', 'Kind', 'select', ['native' => true, 'options' => ['p' => 'P', 'c' => 'C']])
        ->tab('b', 'B')->field('company', 'Company', 'text', ['showIf' => ['field' => 'kind', 'equals' => 'c']]));
    T::eq($notes, [], 'a condition on a field in another tab raised a warning');
    T::contains($html, 'data-gk-show-if="{&quot;field&quot;:&quot;kind&quot;,&quot;equals&quot;:&quot;c&quot;}"', 'the condition is gone');
},

'what cannot be used is reported and has a fallback' => function (): void {
    $warn = function (callable $build, string $needle): array {
        [$html, $notes] = ftbRender($build);
        T::ok(count(array_filter($notes, fn($n) => str_contains($n, $needle))) === 1, "no single warning containing «{$needle}»: " . json_encode($notes));
        return [$html, ftbTabs($html)];
    };
    // Unknown options, and options of the wrong kind: left out.
    [$html] = $warn(fn() => (new Form('f'))->tab('a', 'A', ['icons' => 'x', 'colour' => 'red']), 'unknown options icons, colour');
    [$html] = $warn(fn() => (new Form('f'))->tab('a', 'A', ['icon' => ['x']]), "'icon' that is not text");
    T::notContains($html, 'gk-tab-icon', 'an icon that is not text was rendered');
    [$html] = $warn(fn() => (new Form('f'))->tab('a', 'A', ['badge' => true]), "'badge' that is not text");
    T::notContains($html, 'class="gk-tab-badge"', 'a badge that is not text was rendered');
    foreach (['three', -1, 2.5, '1e3'] as $bad) {
        [$html] = $warn(fn() => (new Form('f'))->tab('a', 'A', ['count' => $bad]), "'count' that is not a whole number");
        T::notContains($html, 'gk-tab-count', 'the count ' . json_encode($bad) . ' was rendered');
    }
    // A duplicate key is renamed: two panels under one key would be one tab, the second unreachable.
    [, $t] = $warn(fn() => (new Form('f'))->tab('a', 'A')->tab('a', 'Again')->tab('a', 'Third'), "two tabs with the key 'a'; the second is renamed 'a-3'");
    T::eq($t['tabs'], ['a', 'a-2', 'a-3'], 'duplicate keys are not renamed');
    T::eq($t['panels'], ['a', 'a-2', 'a-3'], 'the panels do not follow the renamed keys');
    [, $t] = $warn(fn() => (new Form('f'))->tab(' ', 'Blank'), 'a tab without a key');
    T::eq($t['tabs'], ['tab-1'], 'an empty key is not given one');
    // A tab inside a row closes the row first; so does endTabs().
    [$html] = $warn(fn() => (new Form('f'))->tab('a', 'A')->row()->field('x', 'X', 'text', ['width' => 8])->tab('b', 'B')->field('y', 'Y', 'text'), "tab('b') inside a row()");
    T::contains($html, ftbField('x', 'X', 'text', ['width' => 8]) . '</div></div><div class="gk-tab-panel', 'the row is not closed before the next panel');
    [$html] = $warn(fn() => (new Form('f'))->tab('a', 'A')->row()->field('x', 'X', 'text', ['width' => 8])->endTabs()->field('y', 'Y', 'text'), 'endTabs() inside a row()');
    T::contains($html, ftbField('x', 'X', 'text', ['width' => 8]) . '</div></div></div>' . ftbField('y', 'Y'), 'the row, the panel and the tabs are not closed in order');
    // endTabs() with nothing open, a second set of tabs: ignored, the fields stay visible.
    [$html] = $warn(fn() => (new Form('f'))->endTabs()->field('x', 'X', 'text'), 'endTabs() without an open tab()');
    T::eq($html, T::capture(fn() => (new Form('f'))->field('x', 'X', 'text')->render()), 'a stray endTabs() changed the markup');
    [$html, $t] = $warn(fn() => (new Form('f'))->tab('a', 'A')->endTabs()->tab('b', 'B')->field('x', 'X', 'text'), "tab('b') after endTabs()");
    T::eq($t['tabs'], ['a'], 'a second set of tabs was started');
    T::contains($html, '</div></div>' . ftbField('x', 'X') . '</form>', 'the fields of the ignored tab are not below the tabs');
    $warn(fn() => (new Form('f'))->tab('a', 'A')->tab('b', 'B')->endTabs()->endTabs(), 'endTabs() without an open tab()');
    // activeTab() that names nothing.
    [, $t] = $warn(fn() => (new Form('f'))->tab('a', 'A')->tab('b', 'B')->activeTab('c'), "activeTab('c') names no tab");
    T::eq($t['active'], 'a', 'an unknown activeTab() does not fall back to the first tab');
    [$html] = $warn(fn() => (new Form('f'))->field('x', 'X', 'text')->activeTab('c'), "activeTab('c') but the form has no tab()");
    T::eq($html, T::capture(fn() => (new Form('f'))->field('x', 'X', 'text')->render()), 'activeTab() changed a form without tabs');
},

'a form without tab() is byte for byte what it was' => function (): void {
    // tests/golden-form holds every field type; here the new methods, called without a tab, change nothing.
    $plain = fn() => (new Form('f'))->field('a', 'A', 'text', ['required' => true])->row()->field('b', 'B', 'text', ['width' => 8])->endRow()
        ->errors(['a' => 'Required', '_form' => 'Not saved'])->values(['b' => 'x', '_gk_tab' => 'a'])->submit('Save');
    [$before] = ftbRender($plain);
    [$after, $notes] = ftbRender(fn() => $plain()->tabsStyle('linie')->tabsSecondary()->tabsRemember());
    T::eq($notes, [], 'a form without tabs warned about tab settings');
    T::eq($after, $before, 'tab settings changed a form without tabs');
    T::notContains($after, '_gk_tab', 'a form without tabs carries the hidden tab field');
    T::notContains($after, 'gk-tab', 'a form without tabs carries tab markup');
},

'rendering twice gives the same markup' => function (): void {
    $form = ftbCustomer()->errors(['city' => 'Required']);
    Lang::set('en');
    $one = T::capture(fn() => $form->render());
    $two = T::capture(fn() => $form->render());
    T::eq($two, $one, 'the second render differs');
    T::eq(substr_count($one, '<div class="gk-tabs">'), 1, 'not one set of tabs');
    T::eq(substr_count($one, '<div'), substr_count($one, '</div>'), 'the divs are not balanced');
},

];
