<?php
/**
 * `declare(strict_types=1)` in every class — and the values it must not break.
 *
 * Under strict_types a scalar of the wrong type is no longer converted at the call of an
 * internal function; it is a TypeError. A library that renders what a database hands it has
 * to say where it converts: a date column holds a timestamp (an int), a multiselect holds the
 * one id that is selected (a number), and an ActionGroup label may be a count. Each of these
 * worked before the declaration was added to Form, Table and ActionGroup and has to keep
 * working. Found by rendering 9,699 combinations before and after (see CHANGELOG 1.104.3).
 *
 * What is NOT kept: an option the documentation calls a string and somebody passes as a
 * number (an `icon`, a `currency`, a `dateFormat`, a field name smuggled in through the options)
 * is a TypeError now. That is a programming error, found the first time the page is opened.
 */

declare(strict_types=1);

use GridKit\{ActionGroup, Form, Lang, Table};

/** @return array<string,callable> */
return [

'every class of src/ declares strict_types' => function (): void {
    foreach (glob(__DIR__ . '/../src/*.php') ?: [] as $file) {
        $head = substr((string) file_get_contents($file), 0, 600);
        T::ok(str_contains($head, 'declare(strict_types=1);'), basename($file) . ' does not declare strict_types=1');
    }
},

'a date or datetime column renders what the database may hand it' => function (): void {
    Lang::set('en');
    // An int timestamp, a float, a bool, a numeric string, text that is no date, an empty value.
    foreach ([1700000000, 1.5, true, '1700000000', 'not a date', '', null, '2026-11-02 14:30:00'] as $i => $value) {
        foreach (['date', 'datetime'] as $format) {
            try {
                $html = T::capture(fn() => (new Table('t'))->setData([['d' => $value]])
                    ->column('d', 'D', ['format' => $format])->render());
                T::contains($html, 'data-label="D"', "the $format cell for case #$i has not been rendered");
            } catch (\Throwable $e) {
                T::ok(false, "a $format column with the value " . var_export($value, true) . ' threw ' . get_class($e) . ': ' . $e->getMessage());
            }
        }
    }
    $real = T::capture(fn() => (new Table('t'))->setData([['d' => '2026-11-02']])->column('d', 'D', ['format' => 'date'])->render());
    T::contains($real, 'Nov 2, 2026', 'a real date is no longer formatted as one');
},

'a multiselect with a single number as its value marks that option' => function (): void {
    Lang::set('en');
    $options = [5 => 'Five', 6 => 'Six', 7 => 'Seven'];
    foreach ([5, '5', '5,6', [5, 6]] as $value) {
        try {
            $html = T::capture(fn() => (new Form('f'))->field('ids', 'Ids', 'multiselect', ['options' => $options, 'value' => $value])->render());
            T::contains($html, 'class="gk-chip-selected" data-value="5"', 'the value ' . json_encode($value) . ' does not select option 5');
        } catch (\Throwable $e) {
            T::ok(false, 'a multiselect with the value ' . json_encode($value) . ' threw ' . get_class($e) . ': ' . $e->getMessage());
        }
    }
    $one = T::capture(fn() => (new Form('f'))->field('ids', 'Ids', 'multiselect', ['options' => $options, 'value' => 6])->render());
    T::notContains($one, 'class="gk-chip-selected" data-value="5"', 'the value 6 selects option 5 as well');
},

'an action whose label is a number keeps its label' => function (): void {
    Lang::set('en');
    try {
        $html = ActionGroup::html([['label' => 5, 'icon' => 'edit'], ['label' => 1.5], ['label' => true]]);
        T::contains($html, '>5<', 'the label 5 is gone');
        T::contains($html, '>1.5<', 'the label 1.5 is gone');
    } catch (\Throwable $e) {
        T::ok(false, 'a numeric label threw ' . get_class($e) . ': ' . $e->getMessage());
    }
},

];
