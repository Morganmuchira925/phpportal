<?php
require_once __DIR__ . '/includes/init.php';

if (isLoggedIn()) {
    logEvent('logout', (int) $_SESSION['user_id']);
}

// 1. Destroy session data.
$_SESSION = [];
// 2. Clear the session cookie.
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();
// 3. Redirect to login (new session needed for the flash message).
session_start();
setFlash('success', 'You have been logged out.');
redirect('/login.php');
