<?php
/**
 * The importer of the demo (GridKit\Import, 1.119.0), in one place: sections/import.php draws
 * its first step (and a second one the server draws on its own), api/import.php answers the
 * steps after it. Both build it here, so the server checks with the very fields the page shows.
 *
 * A public site: small files only, and storage(false) — nothing an anonymous visitor uploads is
 * kept on this server; the file travels in the form between the steps and is read and checked
 * again on every one of them.
 */

declare(strict_types=1);

use GridKit\Import;

function demoImporter(string $name = 'customers'): Import
{
    return (new Import($name))
        ->label('Import customers')
        ->field('customer_no', 'Customer no.', ['required' => true, 'unique' => true, 'aliases' => ['Kundennummer', 'Kd-Nr', 'Number']])
        ->field('first_name', 'First name', ['aliases' => ['Vorname', 'Given name']])
        ->field('last_name', 'Last name', ['required' => true, 'aliases' => ['Nachname', 'Surname']])
        ->field('email', 'E-mail', ['type' => 'email', 'required' => true, 'unique' => true, 'aliases' => ['E-Mail', 'Mail', 'E-Mail-Adresse']])
        ->field('country', 'Country', ['type' => 'select', 'options' => ['AT' => 'Austria', 'DE' => 'Germany', 'CH' => 'Switzerland'], 'aliases' => ['Land']])
        ->field('birthday', 'Birthday', ['type' => 'date', 'aliases' => ['Geburtsdatum', 'Date of birth']])
        ->field('revenue', 'Revenue', ['type' => 'money', 'aliases' => ['Umsatz']])
        ->field('newsletter', 'Newsletter', ['type' => 'bool'])
        ->locale('de')                 // German Excel writes 1.234,50 — and 1.500 means fifteen hundred
        ->limits(2000, 512 * 1024)     // a public demo takes small files
        ->storage(false);              // and keeps none of them: the file travels in the form
}
