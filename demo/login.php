<?php
/**
 * GRIDKit Auth Demo — Login Page
 *
 * Shows how to use Auth::renderLogin() and Auth::login()
 * Credentials: demo / demo
 */
require_once __DIR__ . '/../autoload.php';
use GridKit\{Theme, Auth};

// Demo-spezifisches Users-File (außerhalb Webroot in Prod!)
Auth::setUsersFile(__DIR__ . '/demo-users.conf');

// Theme übernehmen (aus Session/Cookie falls vorhanden)
Theme::set('indigo', 'dark');

// Logout behandeln
if (isset(['logout'])) {
    Auth::logout('login.php');
}

// Login-Versuch
 = '';
if (['REQUEST_METHOD'] === 'POST') {
    if (Auth::login(['username'] ?? '', ['password'] ?? '')) {
         = ['gk_intended'] ?? 'index.php';
        header('Location: ' . );
        exit;
    }
     = 'Falscher Benutzername oder Passwort.';
}

// Login-Seite rendern
Auth::renderLogin([
    'error'  => ,
    'title'  => 'GRIDKit Demo',
    'icon'   => 'grid_view',
]);
