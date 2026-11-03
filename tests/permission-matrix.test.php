<?php
/**
 * PermissionMatrix (1.115.0): roles × permissions for user and rights management.
 *
 * What the browser does with it (inheritance live, requires, bulk, filter, keyboard, phone, saving as
 * you go, contrast) is driven in ci/browser-permission-matrix.js. Held here: the markup and its
 * accessible names, the posted shape — rendered, parsed out of the HTML and read back through PHP's
 * own parser — fromPost() against hostile input, read() and `requires`, inheritance and locked roles
 * on the server side, every refusal and its warning, and the contracts of the CSS and script blocks,
 * the demo and the skill.
 */

declare(strict_types=1);

use GridKit\{Lang, PermissionMatrix};

/** The matrix most cases use: a locked role, a parent with two heirs (one of them two levels down), requires chains. */
function permMatrix(string $id = 'pm'): PermissionMatrix
{
    return (new PermissionMatrix($id))
        ->role('admin', 'Admin', ['locked' => true])
        ->role('acc', 'Accounting', ['color' => 'green', 'description' => 'Money'])
        ->role('editor', 'Editor', ['parent' => 'guest'])
        ->role('junior', 'Junior', ['parent' => 'editor'])
        ->role('guest', 'Guest')
        ->permission('dash', 'Dashboard')
        ->group('inv', 'Invoices', ['description' => 'Bills'])
        ->permission('inv.view', 'View invoices')
        ->permission('inv.edit', 'Edit invoices', ['requires' => 'inv.view'])
        ->permission('inv.delete', 'Delete invoices', ['requires' => ['inv.edit'], 'danger' => true, 'description' => 'Seven years'])
        ->group('cms', 'CMS', ['collapsed' => true])
        ->permission('cms.view', 'View pages')
        ->grants(['guest' => ['inv.view'], 'acc' => ['inv.view', 'inv.edit'], 'editor' => ['cms.view']])
        ->grant('junior', 'dash');
}

/** Render (or run) and keep the warnings. @return array{0:string,1:list<string>} */
function permRun(callable $fn): array
{
    $warnings = [];
    set_error_handler(static function (int $n, string $m) use (&$warnings): bool {
        $warnings[] = $m;
        return true;
    });
    try {
        $html = T::capture($fn);
    } finally {
        restore_error_handler();
    }
    return [$html, $warnings];
}

/** What a browser posts for the form as rendered: hidden fields, then ticked enabled checkboxes, in document order. */
function permPostOf(string $html, array $tick = [], array $untick = []): array
{
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8"?><form>' . $html . '</form>');
    libxml_clear_errors();
    $pairs = [];
    foreach ($doc->getElementsByTagName('input') as $in) {
        /** @var DOMElement $in */
        $name = $in->getAttribute('name');
        if ($name === '' || $in->hasAttribute('disabled')) continue;
        $type = $in->getAttribute('type');
        $checked = $in->hasAttribute('checked');
        if (in_array($name, $tick, true)) $checked = true;
        if (in_array($name, $untick, true)) $checked = false;
        if ($type === 'hidden' || ($type === 'checkbox' && $checked)) {
            $pairs[] = rawurlencode($name) . '=' . rawurlencode($in->getAttribute('value'));
        }
    }
    parse_str(implode('&', $pairs), $post);
    return $post;
}

/** @return array<string,callable> */
return [

// ── markup ────────────────────────────────────────────────────────────────

'a role is a column header, a permission a row header, a cell a named checkbox' => function (): void {
    Lang::set('en');
    [$html, $w] = permRun(fn () => permMatrix()->caption('Rights')->render());
    T::eq($w, [], 'a warning without a reason');
    T::contains($html, '<div class="gk-perm" id="pm" data-gk-perm="pm"', 'the wrap');
    T::contains($html, '<table class="gk-table gk-perm-table" id="pm-table">', 'a real table, with the table look');
    T::contains($html, '<caption id="pm-caption" class="gk-sr-only">Rights</caption>', 'the caption');
    T::contains($html, 'role="region" tabindex="0" aria-labelledby="pm-caption"', 'the scroll box is a named region with a tab stop');
    T::contains($html, '<th scope="col" class="gk-perm-role" data-gk-perm-role="acc">', 'a role header with scope');
    T::contains($html, '<th scope="row" class="gk-perm-name"><span class="gk-perm-label">View invoices</span>', 'a permission row header');
    T::contains($html, '<th scope="rowgroup" class="gk-perm-group-head">', 'a group heading with scope rowgroup');
    T::contains($html, 'aria-label="Accounting: Edit invoices"', 'a cell is named "Role: Permission"');
    T::contains($html, '<span class="gk-label gk-label-green">Accounting</span>', 'a role colour is a GridKit label');
    T::contains($html, '<span class="gk-perm-role-desc" title="Money">Money</span>', 'the role description');
    T::contains($html, '<span class="gk-label gk-label-red gk-perm-risk">Risky</span>', 'a risky permission says so in words');
    T::contains($html, '<span class="gk-perm-needs">Needs: Edit invoices</span>', 'what a permission needs');
    T::contains($html, '<span class="gk-perm-desc">Seven years</span>', 'a permission description');
    T::contains($html, ' data-gk-perm-start-folded>', 'a collapsed group says so for the script');
    T::contains($html, 'data-gk-perm-role="acc"><span class="gk-perm-role-name"><span class="gk-label gk-label-green">Accounting</span></span><span class="gk-perm-role-desc" title="Money">Money</span><span class="gk-perm-role-foot"><input type="checkbox" class="gk-perm-bulk" data-gk-perm-bulk="role" data-gk-perm-js hidden aria-label="Accounting: all permissions shown"><span class="gk-perm-count" data-gk-perm-count>2 of 5</span>',
        'the head of a role: name, description, the column toggle (script only) and its count');
    T::contains($html, 'data-gk-perm-role="junior"><span class="gk-perm-role-name">Junior</span><span class="gk-perm-role-note">Inherits Editor</span><span class="gk-perm-role-foot"><input type="checkbox" class="gk-perm-bulk" data-gk-perm-bulk="role" data-gk-perm-js hidden aria-label="Junior: all permissions shown"><span class="gk-perm-count" data-gk-perm-count>3 of 5</span>',
        'an heir counts what it inherits (dash its own, inv.view from Guest, cms.view from Editor)');
},

'every cell the form can change posts a hidden 0 and its checkbox 1' => function (): void {
    Lang::set('en');
    [$html] = permRun(fn () => permMatrix()->render());
    T::contains($html, '<input type="hidden" name="perm[acc][inv.edit]" value="0"><input type="checkbox" class="gk-perm-check" id="pm-c',
        'the hidden 0 comes first, the checkbox after it');
    T::ok(preg_match('/name="perm\[acc\]\[inv\.edit\]" value="1" checked data-gk-perm-was="1"/', $html) === 1, 'a granted cell is ticked and remembers it');
    T::ok(preg_match('/name="perm\[guest\]\[dash\]" value="1" data-gk-perm-was="0"/', $html) === 1, 'a cell not granted is unticked');
    T::eq(substr_count($html, 'name="perm[admin]'), 0, 'a locked role posts nothing');
    T::ok(preg_match('/<td class="gk-perm-cell gk-perm-locked" data-gk-perm-role="admin"><input type="checkbox" class="gk-perm-check" id="[^"]+" checked disabled aria-label="Admin: Delete invoices" aria-describedby="pm-d-locked-0">/', $html) === 1,
        'a locked cell: ticked, disabled, described');
    T::contains($html, '<span id="pm-d-locked-0">Admin always has every permission; it cannot be changed here.</span>', 'the locked description');
},

'an inherited cell is ticked, disabled, nameless, says via whom, and posts 0' => function (): void {
    Lang::set('en');
    [$html] = permRun(fn () => permMatrix()->render());
    // Editor inherits inv.view from Guest; Junior inherits it through Editor from Guest, and cms.view from Editor.
    T::ok(preg_match('/<td class="gk-perm-cell gk-perm-inherited" data-gk-perm-role="editor"><input type="hidden" name="perm\[editor\]\[inv\.view\]" value="0"><input type="checkbox" class="gk-perm-check" id="[^"]+" checked disabled data-gk-perm-was="0" aria-label="Editor: View invoices" aria-describedby="pm-d-inh-4"><span class="gk-perm-note" aria-hidden="true">via Guest<\/span><\/td>/', $html) === 1,
        'Editor: View invoices is inherited from Guest');
    T::ok(preg_match('/data-gk-perm-role="junior"><input type="hidden" name="perm\[junior\]\[cms\.view\]" value="0"><input type="checkbox"[^>]*checked disabled[^>]*><span class="gk-perm-note" aria-hidden="true">via Editor/', $html) === 1,
        'Junior inherits from its parent, the nearest one that has it');
    T::ok(preg_match('/data-gk-perm-role="junior"><input type="hidden" name="perm\[junior\]\[inv\.view\]" value="0"><input type="checkbox"[^>]*checked disabled[^>]*><span class="gk-perm-note" aria-hidden="true">via Guest/', $html) === 1,
        '… and through the chain from Guest');
    T::contains($html, '<span id="pm-d-inh-4" data-gk-perm-inh="guest">Inherited from Guest — change it in the Guest column.</span>', 'the inherited description');
    T::contains($html, '<span class="gk-perm-role-note">Inherits Guest</span>', 'the heir\'s header says so');

    // An heir with its own grant of what the parent also has: shown as its own, removable.
    [$own] = permRun(fn () => permMatrix('o')->grant('editor', 'inv.view')->render());
    T::ok(preg_match('/<td class="gk-perm-cell" data-gk-perm-role="editor"><input type="hidden" name="perm\[editor\]\[inv\.view\]" value="0"><input type="checkbox" class="gk-perm-check" id="[^"]+" name="perm\[editor\]\[inv\.view\]" value="1" checked/', $own) === 1,
        'an own grant is the role\'s own, even when the parent has it too');
},

'a parent that is locked hands everything down' => function (): void {
    Lang::set('en');
    [$html, $w] = permRun(fn () => (new PermissionMatrix('l'))
        ->role('root', 'Root', ['locked' => true])->role('deputy', 'Deputy', ['parent' => 'root'])
        ->permission('a', 'A')->permission('b', 'B')->render());
    T::eq($w, [], 'no warning');
    T::eq(substr_count($html, '<span class="gk-perm-note" aria-hidden="true">via Root</span>'), 2, 'every cell of the heir is inherited from the locked role');
    T::contains($html, '<span class="gk-perm-count" data-gk-perm-count>2 of 2</span>', 'and counted');
},

'what needs the script is hidden without it; the plain twin of a fold button is not' => function (): void {
    Lang::set('en');
    [$html] = permRun(fn () => permMatrix()->render());
    T::contains($html, '<div class="gk-toolbar gk-perm-toolbar" data-gk-perm-js hidden>', 'the toolbar');
    T::contains($html, '<div class="gk-perm-picker" data-gk-perm-js hidden>', 'the phone\'s role select');
    T::contains($html, '<th scope="col" class="gk-perm-all" data-gk-perm-js hidden>All roles</th>', 'the All roles column');
    T::contains($html, '<button type="button" class="gk-perm-fold" aria-expanded="true" data-gk-perm-js hidden>', 'the fold button');
    T::contains($html, '<span class="gk-perm-group-plain" data-gk-perm-nojs>', 'the plain group name');
    T::ok(preg_match_all('/<input type="checkbox" class="gk-perm-bulk"[^>]*>/', $html, $m) > 0, 'there are bulk toggles');
    foreach ($m[0] as $tag) {
        T::notContains($tag, 'name=', 'a bulk toggle never posts');
    }
    T::contains($html, '<p class="gk-perm-empty" data-gk-perm-empty hidden>No permission matches this filter.</p>', 'the empty line');
    T::contains($html, '<div class="gk-sr-only" role="status" aria-live="polite" data-gk-perm-status></div>', 'the live region');
},

'read-only: nothing posts, nothing to toggle in bulk, no count of changes' => function (): void {
    Lang::set('en');
    [$html, $w] = permRun(fn () => permMatrix()->readonly()->render());
    T::eq($w, [], 'no warning');
    T::eq(substr_count($html, ' name='), 0, 'no field');
    T::eq(substr_count($html, 'gk-perm-bulk"'), 0, 'no bulk toggle');
    T::notContains($html, 'data-gk-perm-changes', 'no count of changes');
    T::notContains($html, 'data-gk-perm-reset', 'no Reset');
    T::contains($html, ' data-gk-perm-readonly ', 'the script is told');
    T::ok(preg_match('/aria-label="Editor: View invoices" aria-describedby="pm-d-inh-4"/', $html) === 1, 'an inherited cell still says so');
    T::eq(permMatrix()->readonly()->read(['acc' => ['dash' => '1']]), [], 'read() of a read-only matrix reads nothing');
},

'saveUrl() goes into the config, with its headers, and the matrix has no Reset' => function (): void {
    Lang::set('en');
    [$html, $w] = permRun(fn () => permMatrix()->saveUrl('/api/perm.php?x=1&y=2', ['headers' => ['X-CSRF-Token' => 'a"b']])->render());
    T::eq($w, [], 'no warning');
    preg_match('/data-gk-perm-config="([^"]*)"/', $html, $m);
    $cfg = json_decode(html_entity_decode($m[1] ?? '', ENT_QUOTES, 'UTF-8'), true);
    T::eq($cfg['save'] ?? null, '/api/perm.php?x=1&y=2', 'the URL');
    T::eq($cfg['headers'] ?? null, ['X-CSRF-Token' => 'a"b'], 'the headers');
    T::eq($cfg['name'] ?? null, 'perm', 'the field name');
    T::eq(array_column($cfg['roles'] ?? [], 'parent', 'key'), ['admin' => null, 'acc' => null, 'editor' => 'guest', 'junior' => 'editor', 'guest' => null], 'the parents');
    T::eq(array_column($cfg['perms'] ?? [], 'requires', 'key')['inv.delete'] ?? null, ['inv.edit'], 'what a permission requires');
    T::eq($cfg['i18n']['changes'] ?? null, '{n} changes', 'the script\'s words');
    T::notContains($html, 'data-gk-perm-reset', 'no Reset: every change is saved as it is made');
    T::contains($html, 'data-gk-perm-changes', 'the line that says Saving / Saved');
},

'the names and the config are escaped' => function (): void {
    Lang::set('en');
    $evil = '"><script>alert(1)</script>';
    [$html, $w] = permRun(fn () => (new PermissionMatrix('x"y'))
        ->caption($evil)
        ->role('r"1', $evil, ['description' => $evil, 'icon' => '"><b>'])
        ->role("r'2", "O'Brien", ['parent' => 'r"1'])
        ->group('g<', $evil, ['description' => $evil])
        ->permission('p&1', $evil, ['description' => $evil])
        ->permission('p2', 'Two', ['requires' => 'p&1'])
        ->grants(['r"1' => ['p&1']])
        ->render());
    T::eq($w, [], 'no warning');
    T::notContains($html, '<script>', 'no markup from a label');
    T::notContains($html, '<b>', 'no markup from an icon name');
    T::contains($html, 'data-gk-perm="x&quot;y"', 'the id in an attribute');
    T::contains($html, 'name="perm[r&#039;2][p&amp;1]"', 'a key in a field name');
    T::contains($html, 'aria-label="O&#039;Brien: &quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"', 'a label in aria-label');
    T::contains($html, '<span class="gk-perm-note" aria-hidden="true">via &quot;&gt;&lt;script&gt;', 'the inherited note');
    preg_match('/data-gk-perm-config="([^"]*)"/', $html, $m);
    T::ok(is_array(json_decode(html_entity_decode($m[1] ?? '', ENT_QUOTES, 'UTF-8'), true)), 'the config is still JSON once unescaped');
    // The keys reach the browser as typed: the round trip below proves PHP reads them back.
    $post = permPostOf($html);
    T::eq($post['perm']['r"1']['p&1'] ?? null, '1', 'a key with quotes and an ampersand comes back as itself');
},

// ── the posted shape ──────────────────────────────────────────────────────

'what the form posts reads back as what it was given (round trip)' => function (): void {
    Lang::set('en');
    $m = permMatrix();
    [$html] = permRun(fn () => $m->render());
    $post = permPostOf($html);
    T::eq(array_keys($post['perm']), ['acc', 'editor', 'junior', 'guest'], 'every editable role, no locked one');
    T::eq(count($post['perm']['acc']), 5, 'every permission of a role');
    $read = $m->read($post['perm']);
    $granted = array_map(static fn (array $c): array => array_keys(array_filter($c)), $read);
    T::eq($granted, ['acc' => ['inv.view', 'inv.edit'], 'editor' => ['cms.view'], 'junior' => ['dash'], 'guest' => ['inv.view']],
        'read() gives back exactly the own grants');
    T::eq($read['editor']['inv.view'], false, 'an inherited cell reads as not the heir\'s own');
    T::eq(PermissionMatrix::fromPost($post['perm'], ['acc', 'editor', 'junior', 'guest'], ['dash', 'inv.view', 'inv.edit', 'inv.delete', 'cms.view']),
        array_map(static fn (array $c): array => array_diff_key($c, []), $read), 'fromPost() gives the same cells');
},

'ticked and unticked come back as true and false; a cell not on the page does not come back' => function (): void {
    Lang::set('en');
    $m = permMatrix();
    [$html] = permRun(fn () => $m->render());
    $post = permPostOf($html, ['perm[guest][dash]'], ['perm[acc][inv.edit]']);
    $map = PermissionMatrix::fromPost($post['perm'], ['acc', 'guest'], ['dash', 'inv.edit']);
    T::eq($map, ['acc' => ['dash' => false, 'inv.edit' => false], 'guest' => ['dash' => true, 'inv.edit' => false]],
        'only the roles and permissions asked for, each a boolean');
    // A form that showed one module: the other module's cells are not in the post, so not in the result.
    $partial = PermissionMatrix::fromPost(['acc' => ['inv.view' => '1']], ['acc'], ['inv.view', 'inv.edit', 'dash']);
    T::eq($partial, ['acc' => ['inv.view' => true]], 'a cell that was not posted is left out, not read as unticked');
},

'fromPost() takes nothing it was not asked for' => function (): void {
    $roles = ['acc', 'guest', '7'];
    $perms = ['a', 'b', '0'];
    T::eq(PermissionMatrix::fromPost(null, $roles, $perms), [], 'null');
    T::eq(PermissionMatrix::fromPost('acc', $roles, $perms), [], 'a string');
    T::eq(PermissionMatrix::fromPost(['acc' => 'a'], $roles, $perms), [], 'a role that is not an array');
    T::eq(PermissionMatrix::fromPost(['admin' => ['a' => '1'], 'acc' => ['zz' => '1']], $roles, $perms), [], 'unknown role, unknown permission');
    T::eq(PermissionMatrix::fromPost(['acc' => ['a' => ['1'], 'b' => 'yes']], $roles, $perms), [], 'an array or a word as a value');
    T::eq(PermissionMatrix::fromPost(['acc' => ['a' => 2, 'b' => null]], $roles, $perms), [], 'a 2 or a null');
    T::eq(PermissionMatrix::fromPost(['acc' => ['a' => '1', 'b' => '0'], 'guest' => ['a' => true, 'b' => false]], $roles, $perms),
        ['acc' => ['a' => true, 'b' => false], 'guest' => ['a' => true, 'b' => false]], '"1", "0", true and false');
    T::eq(PermissionMatrix::fromPost(['acc' => ['a' => 1, 'b' => 0], 'guest' => ['a' => '']], $roles, $perms),
        ['acc' => ['a' => true, 'b' => false], 'guest' => ['a' => false]], '1, 0 and an empty string');
    // PHP turns "7" and "0" into integer keys; they still match.
    parse_str('perm[7][0]=1', $post);
    T::eq(PermissionMatrix::fromPost($post['perm'], $roles, $perms), ['7' => ['0' => true]], 'numeric keys');
    T::eq(PermissionMatrix::fromPost(['acc' => ['a' => '1']], [['acc']], [new stdClass()]), [], 'lists that are not lists of keys');
    // A JSON body of saveUrl() is the same shape.
    $json = json_decode('{"matrix":"pm","changes":{"acc":{"a":true,"b":false},"evil":{"a":true}}}', true);
    T::eq(PermissionMatrix::fromPost($json['changes'], $roles, $perms), ['acc' => ['a' => true, 'b' => false]], 'the changes of a JSON request');
},

'read() adds what a granted permission needs, unless the role inherits it; it skips locked roles' => function (): void {
    $m = permMatrix();
    $read = $m->read([
        'acc'    => ['inv.delete' => '1', 'inv.edit' => '0', 'inv.view' => '0'],
        'editor' => ['inv.edit' => '1', 'inv.view' => '0'],
        'admin'  => ['dash' => '0'],
        'nobody' => ['dash' => '1'],
    ]);
    T::eq($read['acc'], ['inv.delete' => true, 'inv.edit' => true, 'inv.view' => true], 'the chain inv.delete → inv.edit → inv.view');
    T::eq($read['editor'], ['inv.edit' => true, 'inv.view' => false], 'Editor inherits inv.view from Guest (stored), so it is not added');
    T::ok(!isset($read['admin']) && !isset($read['nobody']), 'a locked role and an unknown one are not read');
    // When the post takes inv.view from the parent too, the heir needs its own.
    $read = $m->read(['guest' => ['inv.view' => '0'], 'editor' => ['inv.edit' => '1', 'inv.view' => '0']]);
    T::eq($read['editor']['inv.view'] ?? null, true, 'the parent lost it in the same post: the heir gets its own');
},

'keys PHP turns into integers ("7", "0") work everywhere' => function (): void {
    Lang::set('en');
    $m = (new PermissionMatrix('num'))
        ->role('1', 'Root', ['locked' => true])
        ->role('7', 'Seven', ['parent' => '9'])
        ->role('9', 'Nine')
        ->group('5', 'Five')
        ->permission('0', 'Zero')
        ->permission('8', 'Eight', ['requires' => '0'])
        ->grants(['9' => ['0']]);
    [$html, $w] = permRun(fn () => $m->render());
    T::eq($w, [], 'no warning');
    T::ok(preg_match('/data-gk-perm-role="7"><input type="hidden" name="perm\[7\]\[0\]" value="0"><input type="checkbox"[^>]*checked disabled[^>]*aria-describedby="num-d-inh-2"><span class="gk-perm-note" aria-hidden="true">via Nine/', $html) === 1,
        'Seven inherits Zero from Nine, described by Nine\'s text');
    T::contains($html, '<span id="num-d-inh-2" data-gk-perm-inh="9">', '… which has that id');
    T::contains($html, 'aria-describedby="num-d-locked-0"', 'the locked role\'s description');
    preg_match('/data-gk-perm-config="([^"]*)"/', $html, $c);
    $cfg = json_decode(html_entity_decode($c[1] ?? '', ENT_QUOTES, 'UTF-8'), true);
    T::eq(array_column($cfg['roles'] ?? [], 'key'), ['1', '7', '9'], 'the script gets the keys as strings');
    T::eq($m->read(['7' => ['8' => '1', '0' => '0'], '9' => ['0' => '0']]), ['7' => ['8' => true, '0' => true], '9' => ['0' => false]],
        'read() with numeric keys, requires made whole');
},

'read() survives a requires loop' => function (): void {
    [, $w] = permRun(function (): void {
        $m = (new PermissionMatrix('loop'))->role('r', 'R')
            ->permission('a', 'A', ['requires' => 'b'])
            ->permission('b', 'B', ['requires' => 'a'])
            ->permission('c', 'C', ['requires' => ['c']]);
        T::eq($m->read(['r' => ['a' => '1', 'b' => '0', 'c' => '1']]), ['r' => ['a' => true, 'b' => true, 'c' => true]], 'a needs b needs a: both, once');
        $m->render();
    });
    T::eq($w, [], 'a permission requiring itself is no warning (it is simply left out)');
},

// ── refusals ──────────────────────────────────────────────────────────────

'every unusable option is refused with a warning, and the matrix still renders' => function (): void {
    Lang::set('en');
    $cases = [
        'bad color'    => [fn () => (new PermissionMatrix('a'))->role('r', 'R', ['color' => 'pink']), "'color' => \"pink\""],
        'locked "yes"' => [fn () => (new PermissionMatrix('a'))->role('r', 'R', ['locked' => 'yes']), "'locked' => \"yes\""],
        'bracket key'  => [fn () => (new PermissionMatrix('a'))->role('r]x', 'R'), 'role key "r]x" cannot be a field name'],
        'empty key'    => [fn () => (new PermissionMatrix('a'))->permission('', 'P'), 'permission key "" cannot'],
        'spaced key'   => [fn () => (new PermissionMatrix('a'))->group(' g', 'G'), 'group key " g" cannot'],
        'dup role'     => [fn () => (new PermissionMatrix('a'))->role('r', 'R')->role('r', 'R2'), "role 'r' is declared twice"],
        'dup perm'     => [fn () => (new PermissionMatrix('a'))->permission('p', 'P')->permission('p', 'P2'), "permission 'p' is declared twice"],
        'dup group'    => [fn () => (new PermissionMatrix('a'))->group('g', 'G')->group('g', 'G2'), "group 'g' is declared twice"],
        'requires int' => [fn () => (new PermissionMatrix('a'))->permission('p', 'P', ['requires' => 5]), "'requires' => 5"],
        'bad name'     => [fn () => (new PermissionMatrix('a'))->name('perm.x'), 'name() takes a form field name'],
        'bad url'      => [fn () => (new PermissionMatrix('a'))->saveUrl('javascript:alert(1)'), 'saveUrl() takes a relative or http(s) URL'],
        'proto url'    => [fn () => (new PermissionMatrix('a'))->saveUrl('//evil.example/x'), 'saveUrl() takes'],
        'bad header'   => [fn () => (new PermissionMatrix('a'))->saveUrl('/x', ['headers' => ["X-A\nB" => '1', 'X-B' => "a\r\nb"]]), 'header "X-A\nB" was left out'],
        'bad height'   => [fn () => (new PermissionMatrix('a'))->height('100px;background:red'), 'height() takes a CSS length'],
        'zero height'  => [fn () => (new PermissionMatrix('a'))->height('0px'), 'height() takes a CSS length'],
        'grants shape' => [fn () => (new PermissionMatrix('a'))->grants(['r' => 'p']), 'grants() takes role => list of permissions'],
    ];
    foreach ($cases as $label => [$build, $expect]) {
        [$html, $w] = permRun(function () use ($build): void { $build()->render(); });
        T::ok(count($w) >= 1 && str_contains($w[0], $expect), "$label: expected a warning with $expect, got " . json_encode($w));
        T::contains($html, 'class="gk-perm"', "$label: the matrix still renders");
    }
},

'what a refusal leaves behind is the documented fallback' => function (): void {
    Lang::set('en');
    [$html] = permRun(fn () => (new PermissionMatrix('a'))->role('r', 'R', ['color' => 'pink'])->role('x]', 'X')
        ->permission('p', 'P')->name('a.b')->height('9999px;x')->saveUrl('javascript:x')->render());
    T::contains($html, '<span class="gk-perm-role-name">R</span>', 'a refused colour: the name stays plain');
    T::notContains($html, 'data-gk-perm-role="x]"', 'a refused key: the role is left out');
    T::contains($html, 'name="perm[r][p]"', 'a refused name: perm');
    T::notContains($html, '--gk-perm-height', 'a refused height: the default');
    T::contains($html, '&quot;save&quot;:null', 'a refused URL: nothing is sent');
    [$nested, $w] = permRun(fn () => (new PermissionMatrix('n'))->name('user[rights]')->role('r', 'R')->permission('p', 'P')->render());
    T::eq($w, [], 'a nested name is accepted');
    T::contains($nested, 'name="user[rights][r][p]"', '… and used');
    $post = permPostOf($nested);
    T::eq($post['user']['rights']['r']['p'] ?? null, '0', '… and read back by PHP as $_POST[\'user\'][\'rights\']');
},

'a cross-reference that goes nowhere is reported at render, and left out' => function (): void {
    Lang::set('en');
    [$html, $w] = permRun(fn () => (new PermissionMatrix('x'))
        ->role('a', 'A', ['parent' => 'ghost'])
        ->role('b', 'B', ['parent' => 'c'])
        ->role('c', 'C', ['parent' => 'b'])
        ->permission('p', 'P', ['requires' => ['q', 'nope']])
        ->permission('q', 'Q')
        ->grants(['a' => ['p', 'zzz'], 'who' => ['p']])
        ->render());
    $all = implode("\n", $w);
    T::contains($all, "role 'a' has 'parent' => \"ghost\" — no such role. It inherits nothing.", 'an unknown parent');
    T::contains($all, "role 'b' would inherit from itself (parent chain b → c → b). It inherits nothing.", 'a cycle');
    T::contains($all, "permission 'p' requires \"nope\" — no such permission.", 'an unknown requirement');
    T::contains($all, "grants 'a' a permission it does not have, \"zzz\"", 'an unknown granted permission');
    T::contains($all, 'grants a role it does not have, "who"', 'an unknown granted role');
    T::notContains($html, 'Inherits Ghost', 'no inheritance from a role that is not there');
    // The loop is broken where it was found: b inherits nothing, c keeps b as its parent.
    T::ok(preg_match('/data-gk-perm-role="b"><span class="gk-perm-role-name">B<\/span><span class="gk-perm-role-foot">/', $html) === 1, 'b inherits nothing');
    T::contains($html, '<span class="gk-perm-role-note">Inherits B</span>', 'c still inherits b');
    T::contains($html, '<span class="gk-perm-needs">Needs: Q</span>', 'the known requirement stays');
},

'a matrix that would post more fields than PHP reads says so' => function (): void {
    $old = ini_get('max_input_vars');
    // max_input_vars is PHP_INI_PERDIR: the test can only read it. Build one past the real limit.
    $limit = (int) $old;
    $m = (new PermissionMatrix('big'))->role('a', 'A')->role('b', 'B');
    $n = intdiv($limit, 4) + 1;
    for ($i = 0; $i < $n; $i++) $m->permission("p$i", "P$i");
    [, $w] = permRun(fn () => $m->render());
    T::ok(count($w) === 1 && str_contains($w[0], 'more than max_input_vars'), 'the warning: ' . json_encode($w));
    [, $none] = permRun(fn () => (clone $m)->readonly()->render());
    T::eq($none, [], 'a read-only matrix posts nothing, so no warning');
},

// ── language ──────────────────────────────────────────────────────────────

'English stays English, German is German' => function (): void {
    Lang::set('en');
    [$en] = permRun(fn () => permMatrix()->render());
    foreach (['Berechtigung', 'Rolle', 'Erbt', 'über ', 'Riskant', 'Braucht', 'Gesperrt', 'Alle Rollen', 'Legende'] as $word) {
        T::notContains($en, $word, 'German in the English matrix');
    }
    Lang::set('de');
    [$de] = permRun(fn () => permMatrix()->render());
    Lang::set('en');
    T::contains($de, 'Berechtigungen filtern', 'the filter');
    T::contains($de, '>über Guest<', 'the inherited note');
    T::contains($de, 'Erbt von Guest', 'the heir\'s header');
    T::contains($de, '>Riskant<', 'risky');
    T::contains($de, 'Braucht: Edit invoices', 'needs');
    T::contains($de, '&quot;changes&quot;:&quot;{n} Änderungen&quot;', 'the script\'s words');
},

// ── contracts: skill, CSS, script, demo ───────────────────────────────────

'every option key and public method is named in the skill' => function (): void {
    $skill = (string) file_get_contents(__DIR__ . '/../GRIDKIT_SKILL.md');
    $src = (string) file_get_contents(__DIR__ . '/../src/PermissionMatrix.php');
    preg_match_all('/\$opts\[[\'"]([A-Za-z_]+)[\'"]\]/', $src, $m);
    T::ok(count(array_unique($m[1])) >= 9, 'the option keys were found');
    foreach (array_unique($m[1]) as $key) {
        T::ok(preg_match('/`' . preg_quote($key, '/') . '`/', $skill) === 1, "PermissionMatrix reads `$key` and the skill never names it");
    }
    $ref = new ReflectionClass(PermissionMatrix::class);
    preg_match('/^### PermissionMatrix\b.*?(?=^### |\z)/ms', $skill, $sec);
    T::ok(($sec[0] ?? '') !== '', 'the skill has a ### PermissionMatrix section');
    foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        if ($method->name === '__construct') continue;
        T::ok(str_contains($sec[0] ?? '', '->' . $method->name . '(') || str_contains($sec[0] ?? '', 'PermissionMatrix::' . $method->name),
            "PermissionMatrix::{$method->name}() is not in its section of the skill");
    }
    foreach (['gk:permchange', 'GK.permMatrix.changes', 'GK.permMatrix.reset', 'GK.permMatrix.commit', 'perm[role][permission]'] as $word) {
        T::contains($sec[0] ?? '', $word, 'the skill section explains');
    }
    T::contains($skill, '| PermissionMatrix | `GridKit\PermissionMatrix`', 'the component overview names it');
},

'the CSS is one block at the end, in both dark spellings, and every class is in the index as 1.115.0' => function (): void {
    $css = (string) file_get_contents(__DIR__ . '/../css/gridkit.css');
    $at = strpos($css, '/* === 1.115.0 — permission matrix === */');
    T::ok($at !== false, 'the block heading');
    $block = substr($css, (int) $at);
    T::ok(!str_contains(substr($block, 10), '/* === '), 'nothing after the block');
    T::contains($block, '[data-gk-mode="dark"] .gk-perm', 'dark by attribute');
    T::contains($block, '.gk-dark .gk-perm', 'dark by class');
    T::ok(substr_count(substr($css, 0, (int) $at), '.gk-perm') === 0, 'no gk-perm rule before the block');
    preg_match_all('/\.(gk-perm[a-z0-9-]*)/', (string) preg_replace('~/\*.*?\*/~s', '', $block), $m);
    $json = json_decode((string) file_get_contents(__DIR__ . '/../css/blocks.json'), true);
    $listed = $json['blocks']['permission-matrix']['classes'] ?? [];
    T::eq($json['blocks']['permission-matrix']['name'] ?? '', 'Permission matrix', 'the block\'s name');
    foreach (array_unique($m[1]) as $class) {
        T::eq($listed[$class] ?? null, '1.115.0', "$class in css/blocks.json");
    }
},

'the script is one block at the end of gridkit.js and binds what PHP writes' => function (): void {
    $js = (string) file_get_contents(__DIR__ . '/../js/gridkit.js');
    $at = strpos($js, '// === 1.115.0 — permission matrix (GridKit\PermissionMatrix) ===');
    T::ok($at !== false, 'the block heading');
    $block = substr($js, (int) $at);
    T::ok(!preg_match('~\n// === ~', substr($block, 5)), 'nothing after the block');
    T::ok(substr_count(substr($js, 0, (int) $at), 'permMatrix') === 0, 'no earlier function was changed to know about it');
    foreach (['data-gk-perm-config', 'data-gk-perm-js', 'data-gk-perm-nojs', 'data-gk-perm-search', 'data-gk-perm-pick',
              'data-gk-perm-reset', 'data-gk-perm-foldall', 'data-gk-perm-status', 'data-gk-perm-was', 'data-gk-perm-start-folded',
              'gk:permchange', 'GK.permMatrix'] as $hook) {
        T::contains($block, $hook, 'the script reads');
    }
},

'the demo shows it: a section, the sidebar item after Tooltip, the server read-back' => function (): void {
    $index = (string) file_get_contents(__DIR__ . '/../demo/index.php');
    $section = (string) file_get_contents(__DIR__ . '/../demo/sections/permissions.php');
    T::contains($section, 'data-section="permissions"', 'the section');
    T::contains($section, "since('permission-matrix')", 'its since mark');
    T::contains($index, "->item('Tooltip', '#tooltip', 'chat_bubble')\n    ->item('Permissions', '#permissions', 'admin_panel_settings')", 'the sidebar item after Tooltip');
    T::contains($index, "<?php require __DIR__ . '/sections/permissions.php'; ?>", 'the section is required');
    T::contains($section, '->read($_POST[\'perm\'])', 'the read-back runs');
    T::contains($section, 'PermissionMatrix::fromPost(', 'and fromPost() is shown');
    T::contains($section, '->saveUrl(', 'and saving as you go');
},

];
