<nav aria-label="Main navigation">
<?php if (isLoggedIn()): ?>
    <a href="<?= e(url('/pages/dashboard.php')) ?>">Dashboard</a>
    <a href="<?= e(url('/pages/profile.php')) ?>">Profile</a>
    <a href="<?= e(url('/pages/contact.php')) ?>">Contact Us</a>
    <a href="<?= e(url('/pages/submissions.php')) ?>">Submissions</a>
    <a href="<?= e(url('/pages/hub.php')) ?>">Info Hub</a>
    <a href="<?= e(url('/logout.php')) ?>" class="btn-link">Logout (<?= e($_SESSION['username'] ?? '') ?>)</a>
<?php else: ?>
    <a href="<?= e(url('/login.php')) ?>">Login</a>
    <a href="<?= e(url('/register.php')) ?>">Register</a>
<?php endif; ?>
</nav>
