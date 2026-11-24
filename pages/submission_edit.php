<?php
require_once __DIR__ . '/../includes/init.php';
requireLogin();

$pdo    = getPDO();
$uid    = (int) $_SESSION['user_id'];
$labels = subjectLabels($SUBJECT_CATEGORIES);
$id     = filter_var($_GET['id'] ?? $_POST['id'] ?? null, FILTER_VALIDATE_INT);
$errors = [];

if (!$id) {
    setFlash('error', 'Invalid submission.');
    redirect('/pages/submissions.php');
}

$stmt = $pdo->prepare(
    'SELECT id, name, email, subject, message FROM contact_submissions WHERE id = :id AND user_id = :uid'
);
$stmt->execute([':id' => $id, ':uid' => $uid]);
$row = $stmt->fetch();
if (!$row) {
    setFlash('error', 'Submission not found.');
    redirect('/pages/submissions.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfVerify($_POST['csrf_token'] ?? null)) {
        $errors['form'] = 'Invalid or expired form token.';
    } else {
        [$row2, $errors] = parseForm($_POST, [
            'name'    => ['label' => 'Name', 'required' => true, 'max' => 80],
            'email'   => ['label' => 'Email', 'required' => true, 'email' => true, 'max' => 120],
            'subject' => ['label' => 'Subject', 'required' => true, 'in' => $labels],
            'message' => ['label' => 'Message', 'required' => true, 'min' => 10, 'max' => 2000],
        ]);
        $row = array_merge($row, $row2);

        if (!$errors) {
            // Conditional UPDATE: only the owner's record.
            $upd = $pdo->prepare(
                'UPDATE contact_submissions SET name = :n, email = :e, subject = :s, message = :m
                  WHERE id = :id AND user_id = :uid'
            );
            $upd->execute([':n' => $row['name'], ':e' => strtolower($row['email']), ':s' => $row['subject'],
                           ':m' => $row['message'], ':id' => $id, ':uid' => $uid]);
            logEvent('submission #' . $id . ' updated', $uid);
            setFlash('success', 'Submission updated.');
            redirect('/pages/submissions.php');
        }
    }
}

$pageTitle = 'Edit Submission';
$pageDescription = 'Edit one of your Contact Us submissions.';
include __DIR__ . '/../includes/header.php';
?>
<h1>Edit submission #<?= (int) $id ?></h1>
<?php if (isset($errors['form'])): ?><div class="alert alert-error"><?= e($errors['form']) ?></div><?php endif; ?>
<form method="post" action="" novalidate class="card form">
    <?= csrfField() ?>
    <input type="hidden" name="id" value="<?= (int) $id ?>">

    <label for="name">Name</label>
    <input type="text" id="name" name="name" value="<?= e($row['name']) ?>" required maxlength="80">
    <?php if (isset($errors['name'])): ?><p class="error"><?= e($errors['name']) ?></p><?php endif; ?>

    <label for="email">Email</label>
    <input type="email" id="email" name="email" value="<?= e($row['email']) ?>" required maxlength="120">
    <?php if (isset($errors['email'])): ?><p class="error"><?= e($errors['email']) ?></p><?php endif; ?>

    <label for="subject">Subject</label>
    <select id="subject" name="subject" required>
        <?php foreach ($labels as $label): ?>
            <option value="<?= e($label) ?>" <?= $row['subject'] === $label ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <?php if (isset($errors['subject'])): ?><p class="error"><?= e($errors['subject']) ?></p><?php endif; ?>

    <label for="message">Message</label>
    <textarea id="message" name="message" rows="6" required maxlength="2000"><?= e($row['message']) ?></textarea>
    <?php if (isset($errors['message'])): ?><p class="error"><?= e($errors['message']) ?></p><?php endif; ?>

    <button type="submit" class="btn">Update</button>
    <a href="<?= e(url('/pages/submissions.php')) ?>" class="btn btn-secondary">Cancel</a>
</form>
<?php include __DIR__ . '/../includes/footer.php'; ?>
