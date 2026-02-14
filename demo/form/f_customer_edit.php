<?php
require_once __DIR__ . '/../../autoload.php';
use GridKit\Form;

$id = $_POST['id'] ?? '';

$form = new Form('customer_form');
$form->action('#')
    ->ajax()
    ->hidden('customer_id', $id)
    ->row()
        ->field('company', 'Firma', 'text', ['required' => true, 'width' => 8])
        ->field('contact', 'Ansprechpartner', 'text', ['width' => 8])
    ->endRow()
    ->field('email', 'E-Mail', 'email', ['required' => true])
    ->row()
        ->field('street', 'Strasse', 'text', ['width' => 8])
        ->field('zip', 'PLZ', 'text', ['width' => 4])
        ->field('city', 'Stadt', 'text', ['width' => 4])
    ->endRow()
    ->field('notes', 'Notizen', 'textarea', ['rows' => 2])
    ->field('active', 'Aktiv', 'toggle')
    ->submit('Speichern')
    ->render();
?>
