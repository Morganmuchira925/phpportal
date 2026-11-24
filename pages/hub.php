<?php
require_once __DIR__ . '/../includes/init.php';
requireLogin();

$pdo = getPDO();
$uid = (int) $_SESSION['user_id'];

/* ---------- Theme preference (cookie, POST) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'theme') {
    if (csrfVerify($_POST['csrf_token'] ?? null) && in_array($_POST['theme'] ?? '', $ALLOWED_THEMES, true)) {
        setcookie('theme', $_POST['theme'], [
            'expires' => time() + 365 * 86400, 'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']), 'httponly' => true, 'samesite' => 'Lax',
        ]);
        setFlash('success', 'Theme saved.');
    }
    redirect('/pages/hub.php');
}

/* ---------- String tools (GET) ---------- */
$input  = sanitizeInput((string) ($_GET['text'] ?? ''));
$input  = mb_substr($input, 0, 200);
$tools  = [];
if ($input !== '') {
    $words = explode(' ', preg_replace('/\s+/', ' ', $input));
    $tools = [
        'Length (strlen)'            => strlen($input),
        'Word count (count)'         => count($words),
        'Uppercase (strtoupper)'     => strtoupper($input),
        'Title case (ucwords)'       => ucwords(strtolower($input)),
        'First 10 chars (substr)'    => substr($input, 0, 10),
        'Spaces to dashes (str_replace)' => str_replace(' ', '-', $input),
        'Words reversed (implode)'   => implode(' ', array_reverse($words)),
        'Sorted words (sort)'        => (function (array $w) { sort($w); return implode(', ', $w); })($words),
        'Words longer than 3 (array_filter)' => implode(', ', array_filter($words, fn($w) => strlen($w) > 3)),
    ];
}

/* ---------- Reports: JOIN + GROUP BY + ORDER BY ... LIMIT ---------- */
$perUser = $pdo->query(
    'SELECT u.username, COUNT(s.id) AS total, MAX(s.created_at) AS latest
       FROM users u LEFT JOIN contact_submissions s ON s.user_id = u.id
      GROUP BY u.id, u.username
      ORDER BY total DESC, u.username ASC
      LIMIT 5'
)->fetchAll();

$perSubject = $pdo->query(
    'SELECT subject, COUNT(*) AS total FROM contact_submissions GROUP BY subject ORDER BY total DESC'
)->fetchAll();

$activity = $pdo->query(
    'SELECT a.created_at, COALESCE(u.username, \'guest\') AS username, a.action
       FROM activity_log a LEFT JOIN users u ON u.id = a.user_id
      ORDER BY a.created_at DESC, a.id DESC LIMIT 8'
)->fetchAll();

/* ---------- Tail of the log file (file handling) ---------- */
$logLines = [];
if (is_readable(LOG_FILE)) {
    $logLines = array_slice(file(LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES), -8);
}

$currentTheme = in_array($_COOKIE['theme'] ?? '', $ALLOWED_THEMES, true) ? $_COOKIE['theme'] : 'light';

$pageTitle = 'Information Hub';
$pageDescription = 'Theme settings, string tools and database reports using JOIN and GROUP BY.';
include __DIR__ . '/../includes/header.php';
?>
<h1>Information hub</h1>

<section class="card">
    <h2>Theme preference (cookie)</h2>
    <form method="post" action="" class="inline-form">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="theme">
        <label for="theme">Theme</label>
        <select id="theme" name="theme">
            <?php foreach ($ALLOWED_THEMES as $t): ?>
                <option value="<?= e($t) ?>" <?= $t === $currentTheme ? 'selected' : '' ?>><?= e(ucfirst($t)) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn">Save</button>
    </form>
</section>

<section class="card">
    <h2>String tools (GET form)</h2>
    <form method="get" action="" class="inline-form">
        <label for="text">Text</label>
        <input type="text" id="text" name="text" value="<?= e($input) ?>" maxlength="200" placeholder="Type a sentence">
        <button type="submit" class="btn">Process</button>
    </form>
    <?php if ($tools): ?>
    <table>
        <tbody>
        <?php foreach ($tools as $label => $result): ?>
            <tr><th scope="row"><?= e($label) ?></th><td><?= e((string) $result) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</section>

<section class="card">
    <h2>Top contributors (JOIN + GROUP BY + LIMIT)</h2>
    <?= generateTable($perUser, ['username' => 'User', 'total' => 'Submissions', 'latest' => 'Latest']) ?>
</section>

<section class="card">
    <h2>Submissions by subject (GROUP BY)</h2>
    <?= generateTable($perSubject, ['subject' => 'Subject', 'total' => 'Count']) ?>
</section>

<section class="card">
    <h2>Recent activity (database)</h2>
    <?= generateTable($activity, ['created_at' => 'When', 'username' => 'User', 'action' => 'Action']) ?>
    <h3>Log file tail (logs/activity.log)</h3>
    <?php if ($logLines): ?>
        <pre><?php foreach ($logLines as $line) { echo e($line) . "\n"; } ?></pre>
    <?php else: ?>
        <p class="muted">The log file is empty.</p>
    <?php endif; ?>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
