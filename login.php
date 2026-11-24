<?php
require_once __DIR__ . '/includes/init.php';

if (isLoggedIn()) {
    redirect('/pages/dashboard.php');
}

$error = '';
// "Last login" convenience: prefill the username from a NON-sensitive cookie.
$prefill = isset($_COOKIE[REMEMBER_COOKIE]) ? sanitizeInput((string) $_COOKIE[REMEMBER_COOKIE]) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfVerify($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid or expired form token. Please try again.';
    } else {
        [$data, $errs] = parseForm($_POST, [
            'identifier' => ['label' => 'Username or email', 'required' => true, 'max' => 120],
            'password'   => ['label' => 'Password', 'required' => true, 'max' => 72, 'raw' => true],
        ]);
        $prefill = $data['identifier'];

        if ($errs) {
            $error = implode(' ', $errs);
        } else {
            $stmt = getPDO()->prepare(
                'SELECT id, username, password_hash FROM users WHERE username = :a OR email = :b LIMIT 1'
            );
            $stmt->execute([':a' => $data['identifier'], ':b' => strtolower($data['identifier'])]);
            $user = $stmt->fetch();

            if ($user && password_verify($data['password'], $user['password_hash'])) {
                session_regenerate_id(true);                 // prevent session fixation
                $_SESSION['user_id']  = (int) $user['id'];
                $_SESSION['username'] = $user['username'];

                getPDO()->prepare('UPDATE users SET last_login = NOW() WHERE id = :id')
                        ->execute([':id' => $user['id']]);

                // Remember Me: store ONLY the username (non-sensitive) in a cookie.
                if (!empty($_POST['remember'])) {
                    setcookie(REMEMBER_COOKIE, $user['username'], [
                        'expires'  => time() + REMEMBER_DAYS * 86400,
                        'path'     => '/',
                        'secure'   => !empty($_SERVER['HTTPS']),
                        'httponly' => true,
                        'samesite' => 'Lax',
                    ]);
                } else {
                    setcookie(REMEMBER_COOKIE, '', time() - 3600, '/');
                }

                logEvent('login', (int) $user['id']);
                redirect('/pages/dashboard.php');
            }
            logEvent('failed login for "' . substr($data['identifier'], 0, 40) . '"');
            $error = 'Invalid username/email or password.';   // generic message
        }
    }
}

$pageTitle = 'Login';
$pageDescription = 'Log in to the Internet Programming Portal.';
include __DIR__ . '/includes/header.php';
?>
<h1>Log in</h1>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<form method="post" action="" novalidate class="card form">
    <?= csrfField() ?>
    <label for="identifier">Username or email</label>
    <input type="text" id="identifier" name="identifier" value="<?= e($prefill) ?>" required autocomplete="username">

    <label for="password">Password</label>
    <input type="password" id="password" name="password" required autocomplete="current-password">

    <label class="inline"><input type="checkbox" name="remember" value="1" <?= $prefill !== '' ? 'checked' : '' ?>> Remember my username</label>

    <button type="submit" class="btn">Log in</button>
    <p class="muted">No account? <a href="<?= e(url('/register.php')) ?>">Register</a></p>
</form>
<?php include __DIR__ . '/includes/footer.php'; ?>
