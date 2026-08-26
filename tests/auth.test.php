<?php
/**
 * Authentication.
 *
 * `Auth` handles sessions, passwords and a remember-me cookie, and until 1.35
 * no test touched it. Three things were wrong at once:
 *
 *  - `renderLogin()` built its markup with unquoted attributes.
 *    `htmlspecialchars()` escapes quotes but not spaces, so a value containing
 *    a space broke out and became new attributes. `['action' =>
 *    $_SERVER['REQUEST_URI']]` is an ordinary thing to write.
 *  - Its labels sat in a heredoc as `<?= Lang::t('auth.username') ?>`. A
 *    heredoc does not evaluate PHP, so those went to the browser verbatim and
 *    every label on the form was invisible.
 *  - `verify()` returned before hashing when the username was unknown, so a
 *    login attempt took ~234 ms for an existing account and ~0 ms for one that
 *    did not exist. Every username on the system was readable with a stopwatch.
 */

declare(strict_types=1);

use GridKit\{Auth, Lang};

/** Call the private verifier without a session. */
function verifyCredentials(string $user, string $password): bool
{
    return (bool) (new \ReflectionMethod(Auth::class, 'verify'))->invoke(null, $user, $password);
}

/** A users file, in the format Auth reads. */
function withUsersFile(array $lines, callable $fn): void
{
    $path = tempnam(sys_get_temp_dir(), 'gk-users-');
    file_put_contents($path, implode("\n", $lines) . "\n");
    Auth::setUsersFile($path);
    try {
        $fn();
    } finally {
        @unlink($path);
    }
}

/** @return array<string,callable> */
return [

'the login page quotes every attribute' => function (): void {
    Lang::set('en');

    // A space is all it takes to break out of an unquoted attribute.
    $payload = 'x onfocus=alert(1) autofocus';
    $html = T::capture(fn() => Auth::renderLogin([
        'action' => $payload, 'title' => $payload, 'icon' => $payload,
    ]));

    // The payload must survive only as a value, never as markup.
    T::contains($html, 'action="x onfocus=alert(1) autofocus"', 'the value is quoted');
    T::ok(!preg_match('/<(form|body|html|input|span|h1)[^>]*\s(on\w+|autofocus)=(?![^>]*")/i', $html),
        'no attribute was injected outside a quoted value');

    // Every attribute in the document must be quoted.
    preg_match_all('/<[a-z][^>]*>/i', $html, $tags);
    foreach ($tags[0] as $tag) {
        T::ok(!preg_match('/\s[a-z-]+=(?![">\s])(?![^\s>]*")[^\s">]*\s+[a-z-]+=/i', $tag),
            'unquoted attribute in: ' . substr($tag, 0, 70));
    }
},

'the login page renders its labels instead of PHP source' => function (): void {
    Lang::set('en');
    $html = T::capture(fn() => Auth::renderLogin());

    T::ok(!str_contains($html, '<?='), 'a heredoc does not run PHP — the tags used to be shipped as text');
    T::contains($html, 'Username', 'the username label');
    T::contains($html, 'Password', 'the password label');
    T::contains($html, 'Stay logged in', 'the remember label');
    T::contains($html, 'Sign in', 'the button');

    Lang::set('de');
    $de = T::capture(fn() => Auth::renderLogin());
    T::contains($de, 'Benutzername', 'and the same in german');
    T::contains($de, 'Anmelden', 'including the button');
    Lang::set('en');
},

'the login page reports a valid layout and language' => function (): void {
    Lang::set('en');
    $html = T::capture(fn() => Auth::renderLogin());

    // The attribute used to read: data-gk-layout= . Layout::getMode() .
    T::ok(!str_contains($html, 'Layout::getMode'), 'the concatenation used to be inside the string');
    T::ok((bool) preg_match('/<body[^>]*data-gk-layout="[a-z-]+"/', $html), 'a real layout attribute');
    T::contains($html, '<html lang="en">', 'the document declares its language');
},

'an error message is escaped, not rendered' => function (): void {
    Lang::set('en');
    $html = T::capture(fn() => Auth::renderLogin(['error' => '<script>alert(1)</script>']));
    T::notContains($html, '<script>alert(1)</script>', 'the payload is escaped');
    T::contains($html, '&lt;script&gt;', 'and shown as text');
},

'asset paths are the caller\'s to choose' => function (): void {
    Lang::set('en');
    $html = T::capture(fn() => Auth::renderLogin(['cssPath' => '/assets/css', 'jsPath' => '/assets/js']));
    T::contains($html, '/assets/css/gridkit.css', 'the stylesheet path is used');
    T::contains($html, '/assets/js/gridkit.js', 'and the script path');
},

'passwords are bcrypt at cost 12' => function (): void {
    $hash = Auth::hashPassword('correct horse');
    T::ok(str_starts_with($hash, '$2y$12$'), "bcrypt cost 12, got: " . substr($hash, 0, 7));
    T::ok(password_verify('correct horse', $hash), 'and it verifies');
    T::ok(!password_verify('correct hors', $hash), 'a near miss does not');
    T::ok(Auth::hashPassword('same') !== Auth::hashPassword('same'), 'salted, so two hashes differ');
},

'the users file is read the way it is documented' => function (): void {
    $hash = Auth::hashPassword('s3cret');

    withUsersFile([
        '# a comment',
        '',
        'alice:' . $hash,
        'malformed-line-without-a-colon',
        'bob:' . Auth::hashPassword('other'),
    ], function () {
        T::ok(verifyCredentials('alice', 's3cret'), 'the right password passes');
        T::ok(!verifyCredentials('alice', 'wrong'), 'the wrong one does not');
        T::ok(!verifyCredentials('nobody', 's3cret'), 'an unknown user does not');
        T::ok(verifyCredentials('bob', 'other'), 'a later line is reached');
        T::ok(!verifyCredentials('# a comment', ''), 'comments are not users');
        T::ok(!verifyCredentials('', 's3cret'), 'an empty username is not a user');
    });
},

'an unknown username costs the same as a known one' => function (): void {
    withUsersFile(['alice:' . Auth::hashPassword('s3cret')], function () {
        $time = static function (string $user): float {
            $start = microtime(true);
            verifyCredentials($user, 'definitely-wrong');
            return (microtime(true) - $start) * 1000;
        };

        $known   = $time('alice');
        $unknown = $time('nobody');

        // One bcrypt at cost 12 is on the order of 100 ms; skipping it is
        // under a millisecond. The bar is deliberately low — this asserts that
        // the work happens at all, not that the two are identical.
        T::ok($unknown > 20.0, sprintf(
            'an unknown user must still cost a hash: known %.1f ms, unknown %.1f ms', $known, $unknown));
    });
},

];
