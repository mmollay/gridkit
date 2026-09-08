<?php
/**
 * Output escaping.
 *
 * GridKit renders values that come from a database, and those values came from
 * a user at some point. Every place a payload can reach the page — cell data,
 * column labels, search values, select options, breadcrumbs — is checked here.
 */

declare(strict_types=1);

use GridKit\{Lang, Table, Form, Select, TableHeader, Header, PageSize, StatCards, Button,
            Sidebar, FilterChips, YearFilter, ActionGroup, Icon, Pagination};

const XSS = '<script>alert(1)</script>';
const ATTR_BREAK = '" onmouseover="alert(1)';

/** Fails if the raw payload survived into the markup. */
function assertEscaped(string $html, string $where): void
{
    T::notContains($html, '<script>alert(1)</script>', "$where: script tag survived");
    T::notContains($html, ' onmouseover="alert(1)', "$where: attribute break survived");
}

/** @return array<string,callable> */
return [

'table cell data is escaped' => function (): void {
    Lang::set('en');
    $html = T::capture(fn() => (new Table('t'))
        ->setData([['id' => 1, 'name' => XSS, 'note' => ATTR_BREAK]])
        ->column('name', 'Product')
        ->column('note', 'Note')
        ->render());
    assertEscaped($html, 'Table cell');
    T::contains($html, '&lt;script&gt;', 'payload is present, but escaped');
},

'table column labels are escaped' => function (): void {
    $html = T::capture(fn() => (new Table('t'))
        ->setData([['id' => 1, 'a' => 'x']])
        ->column('a', XSS)
        ->render());
    assertEscaped($html, 'Table column label');
},

'search values are escaped' => function (): void {
    $html = T::capture(fn() => TableHeader::make('t')
        ->search('q', ATTR_BREAK, XSS)
        ->render());
    assertEscaped($html, 'TableHeader search');
},

'select options are escaped' => function (): void {
    $html = Select::searchable('c', ['a' => XSS], ['placeholder' => ATTR_BREAK]);
    assertEscaped($html, 'Select option');
},

'form field labels and values are escaped' => function (): void {
    $html = T::capture(fn() => (new Form('f'))
        ->field('a', XSS, 'text', ['value' => ATTR_BREAK])
        ->render());
    assertEscaped($html, 'Form field');
},

'header title, breadcrumb and user are escaped' => function (): void {
    $html = (new Header())
        ->title(XSS)
        ->breadcrumb([XSS => ATTR_BREAK])
        ->user(XSS)
        ->render();
    assertEscaped($html, 'Header');
},

'stat card labels and values are escaped' => function (): void {
    $html = T::capture(fn() => (new StatCards())->card(XSS, ATTR_BREAK)->render());
    assertEscaped($html, 'StatCards');
},

'button labels are escaped' => function (): void {
    $html = Button::render(XSS, ['href' => ATTR_BREAK]);
    assertEscaped($html, 'Button');
},

'pagination base url is escaped' => function (): void {
    $html = T::capture(fn() => \GridKit\Pagination::render([
        'page' => 2, 'totalPages' => 3, 'total' => 30, 'baseUrl' => ATTR_BREAK,
    ]));
    assertEscaped($html, 'Pagination baseUrl');
},

'a sort header escapes its label, its url and its extra class' => function (): void {
    // extra_class was the one value in SortLink that reached an attribute
    // unescaped. It is meant to hold a CSS class, but it is caller-supplied
    // like the label and the base url beside it, both of which are escaped.
    $html = \GridKit\SortLink::header('name', XSS, [
        'base_url'    => ATTR_BREAK,
        'extra_class' => ATTR_BREAK,
        'preserve'    => ['q' => ATTR_BREAK],
    ]);
    assertEscaped($html, 'SortLink');
},

'title(raw: true) is the documented, deliberate exception' => function (): void {
    $html = (new Header())->title('<em>ok</em>', true)->render();
    T::contains($html, '<em>ok</em>', 'raw mode must pass markup through unchanged');
},

/**
 * The components below were never in this file. They were checked by hand
 * against both payloads and every one of them held — so these assertions do
 * not fix anything; they stop it from being undone. Navigation in particular
 * is where a label comes straight out of a database row.
 */
'the sidebar escapes labels, links, groups, badges and submenus' => function (): void {
    Lang::set('en');
    assertEscaped(T::capture(fn() => (new Sidebar('s'))->item(XSS, '#')->render()),
        'sidebar item label');
    assertEscaped(T::capture(fn() => (new Sidebar('s'))->item('A', ATTR_BREAK)->render()),
        'sidebar item href');
    assertEscaped(T::capture(fn() => (new Sidebar('s'))->group(XSS)->item('A', '#')->render()),
        'sidebar group label');
    assertEscaped(T::capture(fn() => (new Sidebar('s'))->item('A', '#', '', ['badge' => XSS])->render()),
        'sidebar badge');
    assertEscaped(T::capture(fn() => (new Sidebar('s'))
        ->item('A', '#', '', ['children' => [['label' => XSS, 'href' => ATTR_BREAK]]])->render()),
        'sidebar submenu entry');
},

'filter chips and the year filter escape what they are given' => function (): void {
    Lang::set('en');
    assertEscaped(T::capture(fn() => (new FilterChips('f'))->chip('v', XSS)->render()),
        'chip label');
    assertEscaped(T::capture(fn() => (new FilterChips('f'))->chip(ATTR_BREAK, 'L')->render()),
        'chip value');
    assertEscaped(T::capture(fn() => (new FilterChips('f'))->baseUrl(ATTR_BREAK)->chip('v', 'L')->render()),
        'chip base url');
    assertEscaped(T::capture(fn() => (new FilterChips('f'))->preserve(['q' => ATTR_BREAK])->chip('v', 'L')->render()),
        'chip preserved parameter');
    assertEscaped(T::capture(fn() => (new YearFilter('y'))->baseUrl(ATTR_BREAK)->range(2024, 2026)->render()),
        'year filter base url');
    assertEscaped(T::capture(fn() => (new YearFilter('y'))->preserve(['q' => ATTR_BREAK])->range(2024, 2026)->render()),
        'year filter preserved parameter');
},

'row actions, icons and the pager label escape their input' => function (): void {
    Lang::set('en');
    assertEscaped(T::capture(fn() => ActionGroup::render([
        ['label' => XSS, 'onclick' => 'x()', 'title' => ATTR_BREAK],
    ])), 'action group label and title');
    assertEscaped(Icon::svg(XSS, 16), 'icon name');
    assertEscaped(Pagination::build([
        'page' => 1, 'totalPages' => 3, 'total' => 9, 'label' => XSS, 'baseUrl' => '/x',
    ]), 'pager entry label');
},

/**
 * The chip's remove button takes its accessible name from the option label —
 * added in 1.70.0, and an aria-label is an attribute like any other.
 */
'the multi-select chip escapes the name it gives its remove button' => function (): void {
    Lang::set('en');
    $html = T::capture(fn() => (new Form('f'))
        ->field('t', 'T', 'multiselect', ['options' => ['a' => ATTR_BREAK], 'value' => 'a'])
        ->render());
    assertEscaped($html, 'multiselect chip');
    T::contains($html, 'aria-label="Remove', 'the button is still named');
},

];
