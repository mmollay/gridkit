<?php

/**
 * The invoice form, loaded into a modal.
 *
 * GridKit POSTs here with whatever the row button carried — for an edit that
 * is the row's `id`, for the New button nothing at all. The response is plain
 * HTML that goes straight into the modal body.
 */

declare(strict_types=1);

require_once __DIR__ . '/store.php';

use GridKit\Form;

$id      = (int) ($_POST['id'] ?? 0);
$invoice = $id ? findInvoice($id) : null;

$form = new Form('invoice_form');
$form->action('save.php')
    ->ajax()
    ->card(false)
    ->hidden('id', $invoice['id'] ?? '')
    ->hidden('lang', $lang)
    ->row()
        ->field('number', t('number'), 'text', [
            'required' => true, 'width' => 6,
            'value' => $invoice['number'] ?? nextNumber(),
        ])
        ->field('status', t('status'), 'select', [
            'width' => 5, 'options' => statuses(),
            'value' => $invoice['status'] ?? 'draft',
        ])
        ->field('amount', t('amount'), 'text', [
            'required' => true, 'width' => 5,
            'value' => $invoice['amount'] ?? '',
        ])
    ->endRow()
    ->field('customer', t('customer'), 'text', [
        'required' => true,
        'value' => $invoice['customer'] ?? '',
    ])
    ->row()
        ->field('issued', t('issued'), 'date', [
            'width' => 8, 'value' => $invoice['issued'] ?? date('Y-m-d'),
        ])
        ->field('due', t('due'), 'date', [
            'width' => 8, 'value' => $invoice['due'] ?? date('Y-m-d', strtotime('+30 days')),
        ])
    ->endRow()
    ->field('notes', t('notes'), 'textarea', [
        'rows' => 2, 'value' => $invoice['notes'] ?? '',
    ])
    ->submit(t('save'))
    ->render();
