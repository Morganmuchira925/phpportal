<?php
require_once __DIR__ . '/includes/init.php';
$pageTitle = 'Welcome';
$pageDescription = 'Register or log in to the Internet Programming Portal to send messages, manage submissions and view reports.';
include __DIR__ . '/includes/header.php';
?>
<section class="hero">
    <h1>Welcome to the <?= e(APP_NAME) ?></h1>
    <p>A secure, database-driven PHP and MySQL application with authentication, full CRUD and reporting.</p>
    <p>
    <?php if (isLoggedIn()): ?>
        <a class="btn" href="<?= e(url('/pages/dashboard.php')) ?>">Go to Dashboard</a>
    <?php else: ?>
        <a class="btn" href="<?= e(url('/register.php')) ?>">Create an account</a>
        <a class="btn btn-secondary" href="<?= e(url('/login.php')) ?>">Log in</a>
    <?php endif; ?>
    </p>
</section>

<section class="features-grid" aria-label="Portal features">
    <div class="card feature-card">
        <div class="feature-icon" aria-hidden="true">🔒</div>
        <h3>Secure Authentication</h3>
        <p class="muted">Role-ready user accounts with bcrypt hashing, CSRF tokens, session fixation defense, and secure cookie storage.</p>
    </div>
    <div class="card feature-card">
        <div class="feature-icon" aria-hidden="true">📁</div>
        <h3>Submissions &amp; CRUD</h3>
        <p class="muted">Full CRUD directory with whitelisted sorting, server-side pagination, strict ownership access control, and search.</p>
    </div>
    <div class="card feature-card">
        <div class="feature-icon" aria-hidden="true">📊</div>
        <h3>Reports &amp; Data Tools</h3>
        <p class="muted">Aggregate SQL reporting, string manipulation suite, CSV streaming import/export, and automated activity logging.</p>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
