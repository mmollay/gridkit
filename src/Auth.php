<?php
declare(strict_types=1);
namespace GridKit;

/**
 * GRIDKit Auth — Session-based authentication
 *
 * Usage:
 *   // In any protected page:
 *   Auth::protect();
 *
 *   // Login page:
 *   if (Auth::login($_POST['username'], $_POST['password'])) {
 *       header('Location: index.php'); exit;
 *   }
 *   Auth::renderLogin(['error' => 'Falsches Passwort', 'title' => 'Mein System']);
 *
 *   // Logout:
 *   Auth::logout();
 *
 * Users file (/etc/gridkit-users.conf):
 *   # username:bcrypt_hash
 *   admin:$2y$12$...
 */
class Auth {
    private static string $usersFile = '/etc/gridkit-users.conf';
    private static string $sessionKey = 'gk_user';

    /** Set custom path to users file */
    public static function setUsersFile(string $path): void {
        self::$usersFile = $path;
    }

    /** Guard a page — redirects to login if not authenticated */
    public static function protect(string $loginUrl = 'login.php'): void {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!self::check()) {
            $_SESSION['gk_intended'] = $_SERVER['REQUEST_URI'];
            header('Location: ' . $loginUrl);
            exit;
        }
    }

    /** Attempt login. Returns true on success. */
    public static function login(string $username, string $password): bool {
        if (self::verify($username, $password)) {
            if (session_status() === PHP_SESSION_NONE) session_start();
            session_regenerate_id(true);
            $_SESSION[self::$sessionKey] = $username;
            return true;
        }
        return false;
    }

    /** Logout and redirect */
    public static function logout(string $redirect = 'login.php'): void {
        if (session_status() === PHP_SESSION_NONE) session_start();
        session_destroy();
        header('Location: ' . $redirect);
        exit;
    }

    /** Check if user is authenticated */
    public static function check(): bool {
        if (session_status() === PHP_SESSION_NONE) session_start();
        return !empty($_SESSION[self::$sessionKey]);
    }

    /** Get current username or null */
    public static function user(): ?string {
        if (session_status() === PHP_SESSION_NONE) session_start();
        return $_SESSION[self::$sessionKey] ?? null;
    }

    /** Hash a password for use in users file */
    public static function hashPassword(string $password): string {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /**
     * Render a complete standalone login page (outputs HTML and exits on GET).
     *
     * Options:
     *   error  => string   — Error message to display
     *   title  => string   — App title (default: 'Login')
     *   icon   => string   — Material Icon name (default: 'lock')
     *   action => string   — Form action URL (default: current page)
     */
    public static function renderLogin(array $options = []): void {
        $error  = htmlspecialchars($options['error']  ?? '');
        $title  = htmlspecialchars($options['title']  ?? 'Login');
        $icon   = htmlspecialchars($options['icon']   ?? 'lock');
        $action = htmlspecialchars($options['action'] ?? '');

        $themeAttr  = '';
        $layoutAttr = 'data-gk-layout="header-first"';
        if (class_exists('\GridKit\Theme')) {
            $themeAttr = Theme::attributes();
        }

        echo <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
    <link rel="stylesheet" href="gridkit/css/gridkit.css">
    <link rel="stylesheet" href="gridkit/css/themes.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <style>
        html, body { height: 100%; margin: 0; }
        .gk-login-wrap {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--gk-surface-container, #f0f1f3);
            padding: 24px;
        }
        .gk-login-card {
            background: var(--gk-surface, #fff);
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.12);
            padding: 40px 36px;
            width: 100%;
            max-width: 380px;
        }
        [data-gk-mode="dark"] .gk-login-card {
            background: var(--gk-surface-container-high, #1e293b);
            box-shadow: 0 8px 32px rgba(0,0,0,0.4);
        }
        .gk-login-header {
            text-align: center;
            margin-bottom: 32px;
        }
        .gk-login-icon {
            width: 64px; height: 64px;
            border-radius: 16px;
            background: var(--gk-primary-container, #e0e7ff);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 16px;
        }
        .gk-login-icon .material-icons {
            font-size: 32px;
            color: var(--gk-primary, #6366f1);
        }
        .gk-login-title {
            font-size: 22px;
            font-weight: 600;
            color: var(--gk-on-surface, #1f2937);
            margin: 0 0 4px;
        }
        .gk-login-subtitle {
            font-size: 13px;
            color: var(--gk-on-surface-variant, #6b7280);
            margin: 0;
        }
        .gk-login-field {
            margin-bottom: 16px;
        }
        .gk-login-field label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: var(--gk-on-surface-variant, #4b5563);
            margin-bottom: 6px;
        }
        .gk-login-field input {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid var(--gk-outline-variant, #d1d5db);
            border-radius: 8px;
            font-size: 14px;
            background: var(--gk-surface-container-lowest, #fff);
            color: var(--gk-on-surface, #1f2937);
            outline: none;
            transition: border-color .15s;
            box-sizing: border-box;
        }
        .gk-login-field input:focus {
            border-color: var(--gk-primary, #6366f1);
        }
        [data-gk-mode="dark"] .gk-login-field input {
            background: var(--gk-surface-container, #0f172a);
            border-color: var(--gk-outline-variant, #334155);
        }
        .gk-login-error {
            background: rgba(244, 63, 94, 0.1);
            border: 1px solid rgba(244, 63, 94, 0.3);
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 13px;
            color: #f43f5e;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .gk-login-btn {
            width: 100%;
            padding: 11px;
            background: var(--gk-primary, #6366f1);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            transition: background .15s, box-shadow .15s;
            margin-top: 8px;
        }
        .gk-login-btn:hover {
            background: var(--gk-primary-variant, #4f46e5);
            box-shadow: 0 2px 8px rgba(99,102,241,0.35);
        }
        .gk-login-footer {
            text-align: center;
            margin-top: 20px;
            font-size: 12px;
            color: var(--gk-on-surface-variant, #9ca3af);
        }
    </style>
</head>
<body {$layoutAttr} {$themeAttr} class="gk-root">

<div class="gk-login-wrap">
    <div class="gk-login-card">
        <div class="gk-login-header">
            <div class="gk-login-icon">
                <span class="material-icons">{$icon}</span>
            </div>
            <h1 class="gk-login-title">{$title}</h1>
            <p class="gk-login-subtitle">Bitte melde dich an</p>
        </div>

        <form method="post" action="{$action}">
HTML;
        if ($error) {
            echo '<div class=gk-login-error><span class=material-icons style=font-size:16px>error_outline</span>' . $error . '</div>';
        }
        echo <<<HTML
            <div class="gk-login-field">
                <label for="gk-username">Benutzername</label>
                <input type="text" id="gk-username" name="username"
                       autocomplete="username" autofocus required>
            </div>
            <div class="gk-login-field">
                <label for="gk-password">Passwort</label>
                <input type="password" id="gk-password" name="password"
                       autocomplete="current-password" required>
            </div>
            <button type="submit" class="gk-login-btn">Anmelden</button>
        </form>
        <p class="gk-login-footer">GRIDKit Auth</p>
    </div>
</div>

<script src="gridkit/js/gridkit.js"></script>
</body>
</html>
HTML;
    }

    /** Verify username + password against users file */
    private static function verify(string $username, string $password): bool {
        if (!file_exists(self::$usersFile)) return false;
        foreach (file(self::$usersFile) as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '#')) continue;
            $parts = explode(':', $line, 2);
            if (count($parts) < 2) continue;
            [$user, $hash] = $parts;
            if ($user === $username && password_verify($password, trim($hash))) return true;
        }
        return false;
    }
}
