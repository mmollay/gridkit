<?php
/**
 * 'lockedIf' (1.128.0): a row button that may not act stays in its place, locked, with its reason.
 *
 * The SSI Panel hid a customer's delete with showIf when the customer had invoices: the row had no
 * delete and nothing said why. Held here: the server's markup (lock icon, aria-disabled, the name
 * "<name> — <reason>", the reason as title, nothing it could act with), the catalogue's fallback
 * for a merely truthy field, an empty field leaving the button as it was, and the client
 * re-render carrying the same option. The click that shows the reason is measured in
 * ci/browser-zurueck.js.
 */

declare(strict_types=1);

use GridKit\{Icon, Lang, Table};

/** The markup of a one-row table whose delete is locked by $lock. */
function gkLockRow(mixed $lock, array $extra = []): string
{
    $t = (new Table('lk'))
        ->rows([['id' => 7, 'name' => 'A', 'lock' => $lock]], 1)
        ->column('name', 'Name')
        ->button('delete', ['icon' => 'delete', 'color' => 'danger', 'confirm' => true, 'title' => 'Löschen', 'lockedIf' => 'lock'] + $extra);
    return T::capture(fn () => $t->render());
}

/** @return array<string,callable> */
return [

'a text in the field locks the button and is its reason' => function (): void {
    $h = gkLockRow('hat 3 Rechnungen');
    T::contains($h, 'class="gk-btn gk-btn-icon-only gk-btn-text gk-btn-neutral gk-btn-sm gk-btn-locked"', 'expected in the markup');
    T::contains($h, 'aria-disabled="true"', 'expected in the markup');
    T::contains($h, 'data-gk-locked="hat 3 Rechnungen"', 'expected in the markup');
    T::contains($h, 'aria-label="Löschen — hat 3 Rechnungen"', 'expected in the markup');
    T::contains($h, 'title="hat 3 Rechnungen"', 'expected in the markup');
    T::contains($h, Icon::svg('lock', 16, true), 'expected in the markup');
    T::ok(!str_contains($h, 'data-gk-action="delete"'), 'a locked button fires no row action');
    T::ok(!str_contains($h, 'data-gk-confirm'), 'a locked button asks nothing');
    T::ok(!str_contains($h, 'gk-btn-danger'), 'a locked button is not red');
},

'a merely truthy field says the catalogue\'s "Locked"' => function (): void {
    Lang::set('de');
    try {
        T::contains(gkLockRow(true), 'aria-label="Löschen — Gesperrt"', 'expected in the markup');
        T::contains(gkLockRow(1), 'data-gk-locked="Gesperrt"', 'expected in the markup');
    } finally {
        Lang::set('en');
    }
    T::contains(gkLockRow(true), 'data-gk-locked="Locked"', 'expected in the markup');
},

'an empty field leaves the button as it was' => function (): void {
    foreach (['', null, 0, '0', false] as $v) {
        $h = gkLockRow($v);
        T::ok(!str_contains($h, 'data-gk-locked'), 'not locked for ' . var_export($v, true));
        T::contains($h, 'data-gk-action="delete"', 'expected in the markup');
        T::contains($h, 'data-gk-confirm=', 'expected in the markup');
    }
},

'the reason is escaped' => function (): void {
    $h = gkLockRow('<b>"x" & \'y\'</b>');
    T::ok(!str_contains($h, '<b>"x"'), 'raw markup in the reason');
    T::contains($h, '&lt;b&gt;', 'expected in the markup');
},

'a text button keeps its text beside the lock' => function (): void {
    $t = (new Table('lt'))->rows([['id' => 1, 'lock' => 'nein']], 1)->column('id', 'Nr')
        ->button('send', ['text' => 'Senden', 'icon' => 'mail', 'lockedIf' => 'lock']);
    $h = T::capture(fn () => $t->render());
    T::contains($h, 'gk-btn gk-btn-icon-text gk-btn-text gk-btn-neutral gk-btn-locked', 'expected in the markup');
    T::contains($h, '<span>Senden</span>', 'expected in the markup');
},

'the client re-render and the catalogue know it' => function (): void {
    $js = (string) file_get_contents(__DIR__ . '/../js/gridkit.js');
    T::contains($js, 'bopts.lockedIf', 'expected in the markup');
    T::contains($js, 'case "lock":', 'expected in the markup');
    T::contains($js, '[data-gk-locked]', 'expected in the markup');
    foreach (['de', 'en'] as $l) {
        $cat = require __DIR__ . "/../lang/$l.php";
        T::ok(isset($cat['table.locked'], $cat['js.locked']), "lang/$l.php lacks table.locked / js.locked");
    }
    $css = (string) file_get_contents(__DIR__ . '/../css/gridkit.css');
    T::ok(preg_match('/\.gk-btn\.gk-btn-locked\s*\{[^}]*pointer-events:\s*auto/', $css) === 1, 'the locked button must take the pointer');
},

'Back state: the static table and the RowPager read and write history.state' => function (): void {
    $js = (string) file_get_contents(__DIR__ . '/../js/gridkit.js');
    T::contains($js, 'var _gkBack = {', 'expected in the markup');
    T::contains($js, '_gkBack.read(id)', 'expected in the markup');
    T::contains($js, '_gkBack.write(wrap.dataset.gkTable', 'expected in the markup');
    T::contains($js, '_gkBack.read(backKey)', 'expected in the markup');
    T::ok(!str_contains($js, 'replaceState(null,'), 'replaceState(null, …) wipes what the lists keep');
},

];
