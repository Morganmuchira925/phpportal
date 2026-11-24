<?php
require_once __DIR__ . '/includes/init.php';

if (isLoggedIn()) {
    redirect('/pages/dashboard.php');
}

$errors = [];
$old    = ['username' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfVerify($_POST['csrf_token'] ?? null)) {
        $errors['form'] = 'Invalid or expired form token. Please try again.';
    } else {
        [$data, $errors] = parseForm($_POST, [
            'username' => ['label' => 'Username', 'required' => true, 'min' => 3, 'max' => 30,
                           'regex' => '/^[A-Za-z0-9_]+$/',
                           'regex_message' => 'Username may contain letters, numbers and underscores only.'],
            'email'    => ['label' => 'Email', 'required' => true, 'email' => true, 'max' => 120],
            'password' => ['label' => 'Password', 'required' => true, 'min' => 8, 'max' => 72, 'raw' => true],
            'confirm'  => ['label' => 'Password confirmation', 'required' => true, 'match' => 'password', 'raw' => true],
        ]);
        $old = ['username' => $data['username'], 'email' => $data['email']];

        if (!$errors) {
            $pdo = getPDO();
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare(
                    'INSERT INTO users (username, email, password_hash) VALUES (:u, :e, :p)'
                );
                $stmt->execute([
                    ':u' => $data['username'],
                    ':e' => strtolower($data['email']),
                    ':p' => password_hash($data['password'], PASSWORD_DEFAULT),
                ]);
                $newId = (int) $pdo->lastInsertId();
                $pdo->prepare('INSERT INTO user_profiles (user_id) VALUES (:id)')->execute([':id' => $newId]);
                $pdo->commit();

                logEvent('register', $newId);
                setFlash('success', 'Account created. You can now log in.');
                redirect('/login.php');
            } catch (PDOException $ex) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($ex->getCode() === '23000') {   // duplicate key
                    $errors['form'] = 'That username or email is already registered.';
                } else {
                    error_log($ex->getMessage());
                    $errors['form'] = 'Registration failed. Please try again later.';
                }
            }
        }
    }
}

$pageTitle = 'Register';
$pageDescription = 'Create an account for the Internet Programming Portal.';
include __DIR__ . '/includes/header.php';
?>
<h1>Create an account</h1>
<?php if (isset($errors['form'])): ?><div class="alert alert-error"><?= e($errors['form']) ?></div><?php endif; ?>
<form method="post" action="" novalidate class="card form">
    <?= csrfField() ?>
    <label for="username">Username</label>
    <input type="text" id="username" name="username" value="<?= e($old['username']) ?>" required maxlength="30">
    <?php if (isset($errors['username'])): ?><p class="error"><?= e($errors['username']) ?></p><?php endif; ?>

    <label for="email">Email</label>
    <input type="email" id="email" name="email" value="<?= e($old['email']) ?>" required maxlength="120">
    <?php if (isset($errors['email'])): ?><p class="error"><?= e($errors['email']) ?></p><?php endif; ?>

    <label for="password">Password (min 8 characters)</label>
    <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
    <?php if (isset($errors['password'])): ?><p class="error"><?= e($errors['password']) ?></p><?php endif; ?>

    <label for="confirm">Confirm password</label>
    <input type="password" id="confirm" name="confirm" required autocomplete="new-password">
    <?php if (isset($errors['confirm'])): ?><p class="error"><?= e($errors['confirm']) ?></p><?php endif; ?>

    <button type="submit" class="btn">Register</button>
    <p class="muted">Already registered? <a href="<?= e(url('/login.php')) ?>">Log in</a></p>
</form>
<?php include __DIR__ . '/includes/footer.php'; ?>
