<?php
declare(strict_types=1);
namespace GridKit;

/**
 * GridKit Auth — Session-based authentication with optional Remember Me
 *
 * Usage:
 *   Auth::protect();
 *
 *   // Login (with remember me):
 *   Auth::login($user, $pass, remember: (bool)($_POST['remember'] ?? false));
 *
 *   Auth::logout();
 *   Auth::user();   // current username or null
 */
class Auth {
    private static string $usersFile  = '/etc/gridkit-users.conf';
    private static string $tokenDir   = '/var/lib/gridkit/tokens';
    private static string $sessionKey = 'gk_user';
    private static int    $rememberDays = 30;

    public static function setUsersFile(string $path): void {
        self::$usersFile = $path;
    }

    /** Guard a page — redirects to login if not authenticated */
    public static function protect(string $loginUrl = 'login.php'): void {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!self::check() && !self::checkRememberCookie()) {
            $_SESSION['gk_intended'] = $_SERVER['REQUEST_URI'];
            header('Location: ' . $loginUrl);
            exit;
        }
    }

    /** Attempt login. Pass remember=true to set persistent cookie. */
    public static function login(string $username, string $password, bool $remember = false): bool {
        if (!self::verify($username, $password)) return false;
        if (session_status() === PHP_SESSION_NONE) session_start();
        session_regenerate_id(true);
        $_SESSION[self::$sessionKey] = $username;
        if ($remember) self::setRememberCookie($username);
        return true;
    }

    /** Logout — clears session and remember cookie */
    public static function logout(string $redirect = 'login.php'): void {
        self::clearRememberCookie();
        if (session_status() === PHP_SESSION_NONE) session_start();
        session_destroy();
        header('Location: ' . $redirect);
        exit;
    }

    public static function check(): bool {
        if (session_status() === PHP_SESSION_NONE) session_start();
        return !empty($_SESSION[self::$sessionKey]);
    }

    public static function user(): ?string {
        if (session_status() === PHP_SESSION_NONE) session_start();
        return $_SESSION[self::$sessionKey] ?? null;
    }

    public static function hashPassword(string $password): string {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /**
     * Where GridKit's own css/ and js/ sit, as a URL path.
     *
     * The default used to be the literal `gridkit/css`, which is right for
     * exactly one directory layout and wrong for every other — the demo's own
     * login page asked for `demo/gridkit/css/gridkit.css` and got a 404, so it
     * rendered unstyled. When GridKit lives under the document root the path
     * can simply be worked out; `cssPath` / `jsPath` override it either way.
     */
    private static function assetBase(): ?string
    {
        // Check the raw value first: realpath('') returns the working
        // directory, which is not the document root and would send a CLI run
        // down the wrong branch.
        $raw = (string) ($_SERVER['DOCUMENT_ROOT'] ?? '');
        if ($raw === '') return null;

        $docRoot = realpath($raw);
        $gridkit = dirname(__DIR__);

        if ($docRoot !== false && str_starts_with($gridkit, $docRoot)) {
            // '' when GridKit *is* the document root, '/gridkit' when it sits
            // one level down. Either way the result has to stay absolute: the
            // login page may be at /demo/login.php, where a relative 'css/'
            // would resolve to /demo/css/.
            $tail = str_replace('\\', '/', substr($gridkit, strlen($docRoot)));
            return '/' . trim($tail, '/');
        }

        // Not under the document root, so there is nothing to work out.
        return null;
    }

    /**
     * Render a complete standalone login page.
     *
     * Every attribute here is quoted. It used not to be, and
     * `htmlspecialchars()` escapes quotes but not spaces — so a value
     * containing a space broke out of an unquoted attribute and became new
     * ones. `renderLogin(['action' => $_SERVER['REQUEST_URI']])` is an
     * ordinary thing to write, and REQUEST_URI is whatever the visitor typed.
     *
     * The labels used to sit in a heredoc as `<?= Lang::t('auth.username') ?>`.
     * A heredoc does not evaluate PHP, so those tags were sent to the browser
     * verbatim and every label on the form was invisible.
     *
     * @param array{
     *   error?: string, title?: string, subtitle?: string, icon?: string,
     *   action?: string, cssPath?: string, jsPath?: string, footer?: string,
     * } $options
     */
    public static function renderLogin(array $options = []): void
    {
        $e = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

        $error    = $e((string) ($options['error']    ?? ''));
        $title    = $e((string) ($options['title']    ?? Lang::t('auth.login')));
        $subtitle = $e((string) ($options['subtitle'] ?? Lang::t('auth.subtitle')));
        $icon     = $e((string) ($options['icon']     ?? 'lock'));
        $action   = $e((string) ($options['action']   ?? ''));
        $footer   = $e((string) ($options['footer']   ?? 'GridKit Auth'));

        // Where the assets live is the caller's business — the old hardcoded
        // `gridkit/css/…` only worked for one directory layout.
        $base    = self::assetBase();
        $prefix  = $base !== null ? rtrim($base, '/') . '/' : '';
        $cssPath = rtrim((string) ($options['cssPath'] ?? $prefix . 'css'), '/');
        $jsPath  = rtrim((string) ($options['jsPath']  ?? $prefix . 'js'), '/');

        $locale     = $e(Lang::locale());
        $themeAttr  = class_exists(Theme::class)  ? Theme::attributes() : '';
        $layoutAttr = 'data-gk-layout="'
                    . $e(class_exists(Layout::class) ? Layout::getMode() : 'header-first')
                    . '"';

        $lUser     = $e(Lang::t('auth.username'));
        $lPass     = $e(Lang::t('auth.password'));
        $lRemember = $e(Lang::t('auth.remember'));
        $lLogin    = $e(Lang::t('auth.login'));

        $errorHtml = $error === '' ? '' :
            '<div class="gk-login-error">'
            . '<span class="material-icons" style="font-size:16px">error_outline</span>'
            . $error . '</div>';

        echo <<<HTML
<!DOCTYPE html>
<html lang="{$locale}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
    <link rel="stylesheet" href="{$cssPath}/gridkit.css">
    <link rel="stylesheet" href="{$cssPath}/themes.css">
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
        .gk-login-header { text-align: center; margin-bottom: 32px; }
        .gk-login-icon {
            width: 64px; height: 64px;
            border-radius: 16px;
            background: var(--gk-primary-container, #e0e7ff);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 16px;
        }
        .gk-login-icon .material-icons { font-size: 32px; color: var(--gk-primary, #6366f1); }
        .gk-login-title {
            font-size: 22px; font-weight: 600;
            color: var(--gk-on-surface, #1f2937);
            margin: 0 0 4px;
        }
        .gk-login-subtitle { font-size: 13px; color: var(--gk-on-surface-variant, #6b7280); margin: 0; }
        .gk-login-field { margin-bottom: 16px; }
        .gk-login-field label {
            display: block; font-size: 13px; font-weight: 500;
            color: var(--gk-on-surface-variant, #4b5563); margin-bottom: 6px;
        }
        .gk-login-field input[type="text"],
        .gk-login-field input[type="password"] {
            width: 100%; padding: 10px 14px;
            border: 1.5px solid var(--gk-outline-variant, #d1d5db);
            border-radius: 8px; font-size: 14px;
            background: var(--gk-surface-container-lowest, #fff);
            color: var(--gk-on-surface, #1f2937);
            outline: none; transition: border-color .15s; box-sizing: border-box;
        }
        .gk-login-field input:focus { border-color: var(--gk-primary, #6366f1); }
        .gk-login-field input:focus-visible {
            outline: 2px solid var(--gk-primary, #6366f1);
            outline-offset: 1px;
        }
        [data-gk-mode="dark"] .gk-login-field input[type="text"],
        [data-gk-mode="dark"] .gk-login-field input[type="password"] {
            background: var(--gk-surface-container, #0f172a);
            border-color: var(--gk-outline-variant, #334155);
        }
        .gk-login-remember {
            display: flex; align-items: center; gap: 8px;
            font-size: 13px; color: var(--gk-on-surface-variant, #6b7280);
            cursor: pointer; margin-bottom: 20px; user-select: none;
        }
        .gk-login-remember input[type="checkbox"] {
            width: 16px; height: 16px; accent-color: var(--gk-primary, #6366f1);
            cursor: pointer; flex-shrink: 0;
        }
        .gk-login-error {
            background: rgba(244,63,94,0.1); border: 1px solid rgba(244,63,94,0.3);
            border-radius: 8px; padding: 10px 14px; font-size: 13px; color: #f43f5e;
            margin-bottom: 16px; display: flex; align-items: center; gap: 8px;
        }
        .gk-login-btn {
            width: 100%; padding: 11px;
            background: var(--gk-primary, #6366f1);
            color: #fff; border: none; border-radius: 8px;
            font-size: 15px; font-weight: 500; cursor: pointer;
            transition: background .15s, box-shadow .15s;
        }
        .gk-login-btn:hover {
            background: var(--gk-primary-hover, #4f46e5);
            box-shadow: 0 2px 8px rgba(99,102,241,0.35);
        }
        .gk-login-footer {
            text-align: center; margin-top: 20px;
            font-size: 12px; color: var(--gk-on-surface-variant, #9ca3af);
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
            <p class="gk-login-subtitle">{$subtitle}</p>
        </div>

        <form method="post" action="{$action}">
            {$errorHtml}
            <div class="gk-login-field">
                <label for="gk-username">{$lUser}</label>
                <input type="text" id="gk-username" name="username"
                       autocomplete="username" autofocus required>
            </div>
            <div class="gk-login-field">
                <label for="gk-password">{$lPass}</label>
                <input type="password" id="gk-password" name="password"
                       autocomplete="current-password" required>
            </div>
            <label class="gk-login-remember">
                <input type="checkbox" name="remember" value="1">
                {$lRemember}
            </label>
            <button type="submit" class="gk-login-btn">{$lLogin}</button>
        </form>
        <p class="gk-login-footer">{$footer}</p>
    </div>
</div>

<script src="{$jsPath}/gridkit.js"></script>
</body>
</html>
HTML;
    }

    // ─── Remember-Me Cookie ───────────────────────────────────────────────────

    private static function checkRememberCookie(): bool {
        $cookie = $_COOKIE['gk_remember'] ?? '';
        if (!str_contains($cookie, ':')) return false;

        [$username, $token] = explode(':', $cookie, 2);
        if (!$username || !$token) return false;

        $file = self::$tokenDir . '/' . hash('sha256', $token);
        if (!file_exists($file)) return false;

        $lines  = explode("\n", file_get_contents($file));
        $stored = $lines[0] ?? '';
        $expiry = (int)($lines[1] ?? 0);

        if ($stored !== $username || time() > $expiry) {
            @unlink($file);
            return false;
        }

        // Valid — start session
        if (session_status() === PHP_SESSION_NONE) session_start();
        session_regenerate_id(true);
        $_SESSION[self::$sessionKey] = $username;

        // Rotate token for security
        @unlink($file);
        self::setRememberCookie($username);

        return true;
    }

    private static function setRememberCookie(string $username): void {
        if (!is_dir(self::$tokenDir)) {
            mkdir(self::$tokenDir, 0700, true);
        }

        $token  = bin2hex(random_bytes(32));
        $expiry = time() + (self::$rememberDays * 86400);

        file_put_contents(
            self::$tokenDir . '/' . hash('sha256', $token),
            $username . "
" . $expiry
        );

        setcookie('gk_remember', $username . ':' . $token, [
            'expires'  => $expiry,
            'path'     => '/',
            'httponly' => true,
            'secure'   => !empty($_SERVER['HTTPS']),
            'samesite' => 'Strict',
        ]);
    }

    private static function clearRememberCookie(): void {
        $cookie = $_COOKIE['gk_remember'] ?? '';
        if (str_contains($cookie, ':')) {
            [, $token] = explode(':', $cookie, 2);
            @unlink(self::$tokenDir . '/' . hash('sha256', $token));
        }
        setcookie('gk_remember', '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'httponly' => true,
            'secure'   => !empty($_SERVER['HTTPS']),
            'samesite' => 'Strict',
        ]);
    }

    // ─── Intern ───────────────────────────────────────────────────────────────

    /**
     * A bcrypt hash at the same cost as a real one, of a value nobody knows.
     *
     * It exists so that an attempt against an unknown username costs the same
     * as one against a known username. Returning early made the difference
     * ~234 ms against ~0 ms — enough to enumerate every account on the system
     * from outside, with nothing but a stopwatch.
     */
    private const DUMMY_HASH = '$2y$12$HIwMpLQ3OCG/g9/xtH2jn.35.n6oBQUHScsY/ALS8nOcF1pw2omXu';

    private static function verify(string $username, string $password): bool {
        $hash = null;

        if (file_exists(self::$usersFile)) {
            foreach (file(self::$usersFile) as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) continue;
                $parts = explode(':', $line, 2);
                if (count($parts) < 2) continue;
                [$user, $stored] = $parts;
                if ($user === $username) {
                    $hash = trim($stored);
                    break;
                }
            }
        }

        // Always verify something, even when the name is unknown.
        $matches = password_verify($password, $hash ?? self::DUMMY_HASH);

        return $hash !== null && $matches;
    }
}
