<?php
/**
 * GRIDKit Auth Demo — Login Page
 *
 * Shows how to use Auth::renderLogin() and Auth::login()
 * Credentials: demo / demo
 */
require_once __DIR__ . "/../autoload.php";
use GridKit\{Theme, Auth};

// Demo-spezifisches Users-File (außerhalb Webroot in Prod!)
Auth::setUsersFile(__DIR__ . "/demo-users.conf");

// Theme übernehmen (aus Session/Cookie falls vorhanden)
Theme::set("indigo", "dark");

// Logout behandeln
if (isset($_GET["logout"])) {
    Auth::logout("login.php");
}

// Login-Versuch
$error = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (Auth::login($_POST["username"] ?? "", $_POST["password"] ?? "")) {
        $redirect = $_SESSION["gk_intended"] ?? "index.php";
        header("Location: " . $redirect);
        exit;
    }
    $error = "Falscher Benutzername oder Passwort.";
}

// Login-Seite rendern
Auth::renderLogin([
    "error"  => $error,
    "title"  => "GRIDKit Demo",
    "icon"   => "grid_view",
]);
