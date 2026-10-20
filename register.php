<?php
require_once __DIR__ . '/includes/bootstrap.php';
if (is_logged_in()) redirect('index.php');

$next = safe_next($_GET['next'] ?? $_POST['next'] ?? null);
$errors = [];
$old = ['name' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        $errors['form'] = 'Your session expired. Please try again.';
    } else {
        $name  = trim((string)($_POST['name'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $pass  = (string)($_POST['password'] ?? '');
        $pass2 = (string)($_POST['password2'] ?? '');
        $old = ['name' => $name, 'email' => $email];

        if (mb_strlen($name) < 2 || mb_strlen($name) > 80) $errors['name'] = 'Enter your name (2 to 80 characters).';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) $errors['email'] = 'Enter a valid email address, like name@example.com.';
        if (strlen($pass) < 8 || !preg_match('/[A-Za-z]/', $pass) || !preg_match('/\d/', $pass))
            $errors['password'] = 'Use at least 8 characters, with at least one letter and one number.';
        if ($pass !== $pass2) $errors['password2'] = 'The two passwords do not match.';

        if (!$errors) {
            $chk = db()->prepare('SELECT 1 FROM users WHERE email = ?');
            $chk->execute([$email]);
            if ($chk->fetchColumn()) {
                $errors['email'] = 'An account with this email already exists. Try signing in instead.';
            } else {
                try {
                    $ins = db()->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)');
                    $ins->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT)]);
                    login_user(['id' => (int)db()->lastInsertId(), 'name' => $name]);
                    flash('success', 'Welcome to Allan\'s Phone Store, ' . $name . '.');
                    redirect($next);
                } catch (PDOException $ex) {
                    // 23000 = UNIQUE violation: someone registered this email a moment ago (race condition).
                    if ($ex->getCode() === '23000') $errors['email'] = 'An account with this email already exists. Try signing in instead.';
                    else { error_log($ex->getMessage()); $errors['form'] = 'We could not create your account. Please try again.'; }
                }
            }
        }
    }
}

$pageTitle = 'Create account';
require __DIR__ . '/includes/header.php';
?>
<section class="wrap narrow">
  <div class="panel">
    <h1>Create your account</h1>
    <p class="muted">Save a cart, like phones and track your orders.</p>
    <?php if (isset($errors['form'])): ?><p class="flash flash-error" role="alert"><?= e($errors['form']) ?></p><?php endif; ?>
    <form method="post" novalidate>
      <?= csrf_field() ?><input type="hidden" name="next" value="<?= e($next) ?>">
      <label class="field"><span class="label">Full name</span>
        <input name="name" autocomplete="name" value="<?= e($old['name']) ?>" required <?= isset($errors['name'])?'aria-invalid="true" aria-describedby="e-name"':'' ?>>
        <?php if (isset($errors['name'])): ?><span class="err" id="e-name"><?= e($errors['name']) ?></span><?php endif; ?></label>
      <label class="field"><span class="label">Email address</span>
        <input type="email" name="email" autocomplete="email" value="<?= e($old['email']) ?>" required <?= isset($errors['email'])?'aria-invalid="true" aria-describedby="e-email"':'' ?>>
        <?php if (isset($errors['email'])): ?><span class="err" id="e-email"><?= e($errors['email']) ?></span><?php endif; ?></label>
      <label class="field"><span class="label">Password</span>
        <input type="password" name="password" autocomplete="new-password" required <?= isset($errors['password'])?'aria-invalid="true" aria-describedby="e-pass"':'' ?>>
        <span class="hint">At least 8 characters, with a letter and a number.</span>
        <?php if (isset($errors['password'])): ?><span class="err" id="e-pass"><?= e($errors['password']) ?></span><?php endif; ?></label>
      <label class="field"><span class="label">Confirm password</span>
        <input type="password" name="password2" autocomplete="new-password" required <?= isset($errors['password2'])?'aria-invalid="true" aria-describedby="e-pass2"':'' ?>>
        <?php if (isset($errors['password2'])): ?><span class="err" id="e-pass2"><?= e($errors['password2']) ?></span><?php endif; ?></label>
      <button class="btn btn-primary block" type="submit">Create account</button>
    </form>
    <p class="alt">Already have an account? <a href="login.php?next=<?= urlencode($next) ?>">Sign in</a></p>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
