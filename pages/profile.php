<?php
require_once __DIR__ . '/../includes/init.php';
requireLogin();

$pdo    = getPDO();
$uid    = (int) $_SESSION['user_id'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfVerify($_POST['csrf_token'] ?? null)) {
        $errors['form'] = 'Invalid or expired form token.';
    } else {
        [$data, $errors] = parseForm($_POST, [
            'email'     => ['label' => 'Email', 'required' => true, 'email' => true, 'max' => 120],
            'full_name' => ['label' => 'Full name', 'max' => 80],
            'phone'     => ['label' => 'Phone', 'max' => 20, 'regex' => '/^[0-9+\-\s]{7,20}$/',
                            'regex_message' => 'Phone may contain digits, spaces, + and - only.'],
            'bio'       => ['label' => 'Bio', 'max' => 500],
        ]);

        if (!$errors) {
            try {
                $pdo->beginTransaction();
                $pdo->prepare('UPDATE users SET email = :e WHERE id = :id')
                    ->execute([':e' => strtolower($data['email']), ':id' => $uid]);
                $pdo->prepare(
                    'INSERT INTO user_profiles (user_id, full_name, phone, bio)
                     VALUES (:id, :n, :p, :b)
                     ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), phone = VALUES(phone), bio = VALUES(bio)'
                )->execute([
                    ':id' => $uid,
                    ':n'  => $data['full_name'] !== '' ? $data['full_name'] : null,
                    ':p'  => $data['phone'] !== '' ? $data['phone'] : null,
                    ':b'  => $data['bio'] !== '' ? $data['bio'] : null,
                ]);
                $pdo->commit();
                logEvent('profile updated', $uid);
                setFlash('success', 'Profile updated.');
                redirect('/pages/profile.php');
            } catch (PDOException $ex) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors['form'] = $ex->getCode() === '23000'
                    ? 'That email is already used by another account.'
                    : 'Could not update profile.';
            }
        }
    }
}

$stmt = $pdo->prepare(
    'SELECT u.username, u.email, u.created_at, u.last_login, p.full_name, p.phone, p.bio
       FROM users u LEFT JOIN user_profiles p ON p.user_id = u.id
      WHERE u.id = :id'
);
$stmt->execute([':id' => $uid]);
$profile = $stmt->fetch();
$val = fn(string $k) => $_SERVER['REQUEST_METHOD'] === 'POST' && isset($data[$k]) ? $data[$k] : ($profile[$k] ?? '');

$pageTitle = 'Profile';
$pageDescription = 'View and update your account profile.';
include __DIR__ . '/../includes/header.php';
?>
<h1>Your profile</h1>
<?php if (isset($errors['form'])): ?><div class="alert alert-error"><?= e($errors['form']) ?></div><?php endif; ?>

<section class="card">
    <dl class="details">
        <dt>Username</dt><dd><?= e($profile['username']) ?></dd>
        <dt>Member since</dt><dd><?= e($profile['created_at']) ?></dd>
        <dt>Last login</dt><dd><?= e($profile['last_login'] ?? 'First session') ?></dd>
    </dl>
</section>

<form method="post" action="" novalidate class="card form">
    <h2>Update profile</h2>
    <?= csrfField() ?>
    <label for="email">Email</label>
    <input type="email" id="email" name="email" value="<?= e($val('email')) ?>" required maxlength="120">
    <?php if (isset($errors['email'])): ?><p class="error"><?= e($errors['email']) ?></p><?php endif; ?>

    <label for="full_name">Full name</label>
    <input type="text" id="full_name" name="full_name" value="<?= e($val('full_name')) ?>" maxlength="80">
    <?php if (isset($errors['full_name'])): ?><p class="error"><?= e($errors['full_name']) ?></p><?php endif; ?>

    <label for="phone">Phone</label>
    <input type="text" id="phone" name="phone" value="<?= e($val('phone')) ?>" maxlength="20">
    <?php if (isset($errors['phone'])): ?><p class="error"><?= e($errors['phone']) ?></p><?php endif; ?>

    <label for="bio">Bio</label>
    <textarea id="bio" name="bio" rows="4" maxlength="500"><?= e($val('bio')) ?></textarea>
    <?php if (isset($errors['bio'])): ?><p class="error"><?= e($errors['bio']) ?></p><?php endif; ?>

    <button type="submit" class="btn">Save changes</button>
</form>
<?php include __DIR__ . '/../includes/footer.php'; ?>
