<?php
require_once __DIR__ . '/../includes/init.php';
requireLogin();

$errors = [];
$uid    = (int) $_SESSION['user_id'];
$labels = subjectLabels($SUBJECT_CATEGORIES);

// Prefill name/email from the database.
$stmt = getPDO()->prepare(
    'SELECT u.email, COALESCE(p.full_name, u.username) AS name
       FROM users u LEFT JOIN user_profiles p ON p.user_id = u.id WHERE u.id = :id'
);
$stmt->execute([':id' => $uid]);
$me   = $stmt->fetch();
$form = ['name' => $me['name'], 'email' => $me['email'], 'subject' => '', 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfVerify($_POST['csrf_token'] ?? null)) {
        $errors['form'] = 'Invalid or expired form token.';
    } else {
        [$form, $errors] = parseForm($_POST, [
            'name'    => ['label' => 'Name', 'required' => true, 'max' => 80],
            'email'   => ['label' => 'Email', 'required' => true, 'email' => true, 'max' => 120],
            'subject' => ['label' => 'Subject', 'required' => true, 'in' => $labels],
            'message' => ['label' => 'Message', 'required' => true, 'min' => 10, 'max' => 2000],
        ]);

        if (!$errors) {
            $ins = getPDO()->prepare(
                'INSERT INTO contact_submissions (user_id, name, email, subject, message)
                 VALUES (:uid, :n, :e, :s, :m)'
            );
            $ins->execute([
                ':uid' => $uid, ':n' => $form['name'], ':e' => strtolower($form['email']),
                ':s' => $form['subject'], ':m' => $form['message'],
            ]);
            logEvent('contact submission created', $uid);
            setFlash('success', 'Thanks! Your message has been saved.');
            redirect('/pages/submissions.php');
        }
    }
}

$pageTitle = 'Contact Us';
$pageDescription = 'Send us a message using the Contact Us form.';
include __DIR__ . '/../includes/header.php';
?>
<h1>Contact us</h1>
<?php if (isset($errors['form'])): ?><div class="alert alert-error"><?= e($errors['form']) ?></div><?php endif; ?>
<form method="post" action="" novalidate class="card form">
    <?= csrfField() ?>
    <label for="name">Name</label>
    <input type="text" id="name" name="name" value="<?= e($form['name']) ?>" required maxlength="80">
    <?php if (isset($errors['name'])): ?><p class="error"><?= e($errors['name']) ?></p><?php endif; ?>

    <label for="email">Email</label>
    <input type="email" id="email" name="email" value="<?= e($form['email']) ?>" required maxlength="120">
    <?php if (isset($errors['email'])): ?><p class="error"><?= e($errors['email']) ?></p><?php endif; ?>

    <label for="subject">Subject</label>
    <select id="subject" name="subject" required>
        <option value="">-- Choose a subject --</option>
        <?php foreach ($SUBJECT_CATEGORIES as $cat): ?>
            <option value="<?= e($cat['label']) ?>" <?= $form['subject'] === $cat['label'] ? 'selected' : '' ?>>
                <?= e($cat['label']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <?php if (isset($errors['subject'])): ?><p class="error"><?= e($errors['subject']) ?></p><?php endif; ?>

    <label for="message">Message</label>
    <textarea id="message" name="message" rows="6" required minlength="10" maxlength="2000"><?= e($form['message']) ?></textarea>
    <?php if (isset($errors['message'])): ?><p class="error"><?= e($errors['message']) ?></p><?php endif; ?>

    <button type="submit" class="btn">Send message</button>
</form>
<?php include __DIR__ . '/../includes/footer.php'; ?>
