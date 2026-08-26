<?php
/**
 * GridKit Auth demo — the login page.
 *
 * Shows Auth::renderLogin() and Auth::login() together.
 * Credentials: demo / demo
 */
require_once __DIR__ . "/../autoload.php";
use GridKit\{Auth, Lang, Theme};

Lang::set($_GET['lang'] ?? $_COOKIE['gk_lang'] ?? 'en');

// The demo keeps its user file beside the code. In anything real it belongs
// outside the document root — demo/.htaccess only papers over it here.
Auth::setUsersFile(__DIR__ . "/demo-users.conf");

// Pick up the theme, from the session or cookie if one is set
Theme::set("indigo", "dark");

// Log out
if (isset($_GET["logout"])) {
    Auth::logout("login.php");
}

// Login attempt
$error = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (Auth::login($_POST["username"] ?? "", $_POST["password"] ?? "")) {
        $redirect = $_SESSION["gk_intended"] ?? "index.php";
        header("Location: " . $redirect);
        exit;
    }
    $error = Lang::t('auth.failed');
}

// Render the login page
Auth::renderLogin([
    "error"  => $error,
    "title"  => "GridKit Demo",
    "icon"   => "grid_view",
]);
