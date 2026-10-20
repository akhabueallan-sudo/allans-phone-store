<?php
require_once __DIR__ . '/includes/bootstrap.php';
$next = safe_next($_GET['next'] ?? $_POST['next'] ?? null);
if (is_logged_in()) redirect($next);

$error = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $pass  = (string)($_POST['password'] ?? '');
    if (!csrf_valid()) {
        $error = 'Your session expired. Please try again.';
    } elseif ($email === '' || $pass === '') {
        $error = 'Enter your email and password.';
    } else {
        $s = db()->prepare('SELECT id, name, password_hash FROM users WHERE email = ?');
        $s->execute([$email]);
        $u = $s->fetch();
        // Same message for "no such email" and "wrong password" so attackers cannot probe for accounts.
        if (!$u || !password_verify($pass, $u['password_hash'])) {
            $error = 'That email and password do not match. Check both and try again.';
        } else {
            if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
                db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($pass, PASSWORD_DEFAULT), $u['id']]);
            }
            login_user($u);
            flash('success', 'Signed in. Welcome back, ' . $u['name'] . '.');
            redirect($next);
        }
    }
}

$pageTitle = 'Sign in';
require __DIR__ . '/includes/header.php';
?>
<section class="wrap narrow">
  <div class="panel">
    <h1>Sign in</h1>
    <p class="muted">Welcome back. Your cart will be waiting.</p>
    <?php if ($error): ?><p class="flash flash-error" role="alert"><?= e($error) ?></p><?php endif; ?>
    <form method="post" novalidate>
      <?= csrf_field() ?><input type="hidden" name="next" value="<?= e($next) ?>">
      <label class="field"><span class="label">Email address</span>
        <input type="email" name="email" autocomplete="email" value="<?= e($email) ?>" required autofocus></label>
      <label class="field"><span class="label">Password</span>
        <input type="password" name="password" autocomplete="current-password" required></label>
      <button class="btn btn-primary block" type="submit">Sign in</button>
    </form>
    <p class="alt">New here? <a href="register.php?next=<?= urlencode($next) ?>">Create an account</a></p>
    <p class="hint demo-hint">Demo account: demo@allansphones.test / Demo@1234</p>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
