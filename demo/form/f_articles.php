<?php
require_once __DIR__ . '/../../autoload.php';
use GridKit\Form;

$id = $_POST['id'] ?? '';

$form = new Form('article_form');
$form->action('#')
    ->ajax()
    ->hidden('article_id', $id)
    ->row()
        ->field('article_number', 'Artikelnr.', 'text', ['required' => true, 'width' => 8])
        ->field('name', 'Bezeichnung', 'text', ['required' => true, 'width' => 8])
    ->endRow()
    ->field('description', 'Beschreibung', 'textarea', ['rows' => 3])
    ->row()
        ->field('unit', 'Einheit', 'select', [
            'options' => ['Stk' => 'Stück', 'h' => 'Stunde', 'psch' => 'Pauschal'],
            'width' => 5
        ])
        ->field('net_price', 'Netto-Preis', 'number', ['step' => '0.01', 'width' => 5])
        ->field('tax_rate', 'MwSt %', 'select', [
            'options' => ['20' => '20%', '10' => '10%', '0' => '0%'],
            'width' => 6
        ])
    ->endRow()
    ->field('is_active', 'Aktiv', 'toggle')
    ->submit('Speichern')
    ->render();
