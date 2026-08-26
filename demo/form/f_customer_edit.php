<?php
require_once __DIR__ . '/../../autoload.php';
use GridKit\Form;

$id = $_POST['id'] ?? '';

$form = new Form('customer_form');
$form->action('#')
    ->ajax()
    ->hidden('customer_id', $id)
    ->row()
        ->field('company', 'Company', 'text', ['required' => true, 'width' => 8])
        ->field('contact', 'Contact', 'text', ['width' => 8])
    ->endRow()
    ->field('email', 'Email', 'email', ['required' => true])
    ->row()
        ->field('street', 'Street', 'text', ['width' => 8])
        ->field('zip', 'ZIP', 'text', ['width' => 4])
        ->field('city', 'City', 'text', ['width' => 4])
    ->endRow()
    ->field('notes', 'Notes', 'textarea', ['rows' => 2])
    ->field('active', 'Active', 'toggle')
    ->submit('Save')
    ->render();
?>
