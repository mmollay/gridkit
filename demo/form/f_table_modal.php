<?php
require_once __DIR__ . '/../../autoload.php';
use GridKit\Table;

$customers = [
    ['id' => 1, 'name' => 'Mustermann GmbH', 'email' => 'info@mustermann.at', 'city' => 'Wien', 'status' => 'aktiv'],
    ['id' => 2, 'name' => 'Technik AG', 'email' => 'office@technik.at', 'city' => 'Graz', 'status' => 'aktiv'],
    ['id' => 3, 'name' => 'Design Studio', 'email' => 'hello@design.at', 'city' => 'Linz', 'status' => 'inaktiv'],
    ['id' => 4, 'name' => 'Web Solutions', 'email' => 'mail@websol.at', 'city' => 'Salzburg', 'status' => 'aktiv'],
];

$table = new Table('modal-customers');
$table->setData($customers)
    ->search(['name', 'email'])
    ->column('name', 'Kunde', ['sortable' => true])
    ->column('email', 'E-Mail', ['format' => 'email'])
    ->column('city', 'Stadt')
    ->column('status', 'Status', ['format' => 'label'])
    ->button('edit', ['icon' => 'edit', 'title' => 'Bearbeiten', 'position' => 'left', 'modal' => 'nested_edit', 'params' => ['id' => 'id']])
    ->modal('nested_edit', 'Kunde bearbeiten', 'f_customer_edit.php', ['size' => 'medium'])
    ->newButton('Neuer Kunde', ['modal' => 'nested_edit'])
    ->paginate(false)
    ->render();

?>
