<?php
/**
 * Output escaping.
 *
 * GridKit renders values that come from a database, and those values came from
 * a user at some point. Every place a payload can reach the page — cell data,
 * column labels, search values, select options, breadcrumbs — is checked here.
 */

declare(strict_types=1);

use GridKit\{Lang, Table, Form, Select, TableHeader, Header, PageSize, StatCards, Button};

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

];
