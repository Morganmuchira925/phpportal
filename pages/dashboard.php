<?php
require_once __DIR__ . '/../includes/init.php';
requireLogin();

$pdo = getPDO();
$uid = (int) $_SESSION['user_id'];

// Aggregate queries (COUNT, MAX, NOW, CONCAT).
$totalUsers = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$totalSubs  = (int) $pdo->query('SELECT COUNT(*) FROM contact_submissions')->fetchColumn();
$latestSub  = $pdo->query('SELECT MAX(created_at) FROM contact_submissions')->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT CONCAT('@', username) AS handle, NOW() AS server_time,
            (SELECT COUNT(*) FROM contact_submissions WHERE user_id = :uid1) AS my_subs
       FROM users WHERE id = :uid2"
);
$stmt->execute([':uid1' => $uid, ':uid2' => $uid]);
$me = $stmt->fetch();

// switch + operators: greeting by hour.
$hour = (int) date('G');
switch (true) {
    case $hour < 12: $greeting = 'Good morning'; break;
    case $hour < 18: $greeting = 'Good afternoon'; break;
    default:         $greeting = 'Good evening';
}

$pageTitle = 'Dashboard';
$pageDescription = 'Your dashboard: summary statistics for users and submissions.';
include __DIR__ . '/../includes/header.php';
?>
<h1><?= e($greeting) ?>, <?= e($_SESSION['username']) ?>!</h1>
<p class="muted">Today is <?= e(date('l, d F Y')) ?> &middot; Server time <?= e($me['server_time'] ?? '') ?></p>

<section class="stats" aria-label="Summary statistics">
    <div class="stat"><span class="num"><?= $totalUsers ?></span><span>Registered users</span></div>
    <div class="stat"><span class="num"><?= $totalSubs ?></span><span>Total submissions</span></div>
    <div class="stat"><span class="num"><?= (int) $me['my_subs'] ?></span><span>Your submissions</span></div>
    <div class="stat"><span class="num small"><?= e($latestSub ?: 'None yet') ?></span><span>Latest message</span></div>
</section>

<section class="card">
    <h2>Quick links</h2>
    <ul>
        <?php
        $links = [
            '/pages/profile.php'     => 'Update your profile',
            '/pages/contact.php'     => 'Send a message',
            '/pages/submissions.php' => 'Manage your submissions',
            '/pages/hub.php'         => 'Open the Information Hub',
        ];
        foreach ($links as $href => $label): ?>
            <li><a href="<?= e(url($href)) ?>"><?= e($label) ?></a></li>
        <?php endforeach; ?>
    </ul>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
