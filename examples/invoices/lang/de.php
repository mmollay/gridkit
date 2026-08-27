<?php
/**
 * Strings that belong to this example, not to GridKit.
 * Lang::loadDir() merges them into the same catalogue, so Lang::t()
 * answers for both — and {placeholder} substitution comes free.
 */
return [
    'app.app'                => 'Rechnungen',
    'app.subtitle'           => 'Eine vollständige GridKit-Anwendung — fünf Dateien, keine Datenbank.',
    'app.new'                => 'Neue Rechnung',
    'app.edit'               => 'Rechnung bearbeiten',
    'app.delete'             => 'Rechnung löschen',
    'app.delete_ask'         => 'Rechnung {number} löschen?',
    'app.delete_note'        => 'Das lässt sich nicht rückgängig machen.',
    'app.cancel'             => 'Abbrechen',
    'app.save'               => 'Speichern',
    'app.number'             => 'Rechnungsnr.',
    'app.customer'           => 'Kunde',
    'app.issued'             => 'Ausgestellt',
    'app.due'                => 'Fällig',
    'app.amount'             => 'Betrag',
    'app.status'             => 'Status',
    'app.notes'              => 'Notizen',
    'app.stat_count'         => 'Rechnungen',
    'app.stat_total'         => 'Gesamt',
    'app.stat_open'          => 'Offen',
    'app.stat_overdue'       => 'Überfällig',
    'app.reset'              => 'Beispieldaten zurücksetzen',
    'app.reset_done'         => 'Beispieldaten wiederhergestellt.',
    'app.required'           => 'Pflichtfeld.',
    'app.not_a_number'       => 'Bitte einen Betrag eingeben.',
    'app.st_draft'           => 'Entwurf',
    'app.st_sent'            => 'versendet',
    'app.st_paid'            => 'bezahlt',
    'app.st_overdue'         => 'überfällig',
    'app.empty'              => 'Noch keine Rechnungen',
    'app.empty_hint'         => 'Lege die erste an — sie liegt in deiner Sitzung.',
];
