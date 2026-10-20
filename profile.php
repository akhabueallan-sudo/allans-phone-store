<?php
require_once __DIR__ . '/includes/products.php';
require_login();
$uid = user_id();           // always taken from the session, never from the URL or form

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_or_die();
    $action = $_POST['action'] ?? '';
    if ($action === 'name') {
        $name = trim((string)($_POST['name'] ?? ''));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 80) {
            flash('error', 'Enter a name between 2 and 80 characters.');
        } else {
            db()->prepare('UPDATE users SET name = ? WHERE id = ?')->execute([$name, $uid]);
            $_SESSION['user_name'] = $name;
            flash('success', 'Your name has been updated.');
        }
    } elseif ($action === 'password') {
        $cur = (string)($_POST['current'] ?? ''); $new = (string)($_POST['new'] ?? ''); $new2 = (string)($_POST['new2'] ?? '');
        $row = db()->prepare('SELECT password_hash FROM users WHERE id = ?'); $row->execute([$uid]);
        if (!password_verify($cur, (string)$row->fetchColumn())) flash('error', 'Your current password is not correct.');
        elseif (strlen($new) < 8 || !preg_match('/[A-Za-z]/', $new) || !preg_match('/\d/', $new)) flash('error', 'Use at least 8 characters, with at least one letter and one number.');
        elseif ($new !== $new2) flash('error', 'The two new passwords do not match.');
        else {
            db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $uid]);
            session_regenerate_id(true);
            flash('success', 'Your password has been changed.');
        }
    }
    redirect('profile.php');
}

$u = db()->prepare('SELECT name, email, created_at FROM users WHERE id = ?');
$u->execute([$uid]);
$user = $u->fetch();
if (!$user) { logout_user(); redirect('login.php'); }

$count = function (string $sql) use ($uid): int { $s = db()->prepare($sql); $s->execute([$uid]); return (int)$s->fetchColumn(); };
$orders  = $count('SELECT COUNT(*) FROM orders WHERE user_id = ?');
$likes   = $count('SELECT COUNT(*) FROM product_likes WHERE user_id = ?');
$ratings = $count('SELECT COUNT(*) FROM product_ratings WHERE user_id = ?');

$liked = db()->prepare('SELECT p.id, p.name, p.image, p.price FROM product_likes l JOIN products p ON p.id = l.product_id WHERE l.user_id = ? ORDER BY l.created_at DESC');
$liked->execute([$uid]);
$likedList = $liked->fetchAll();

$pageTitle = 'My profile';
require __DIR__ . '/includes/header.php';
?>
<section class="wrap">
  <h1>My profile</h1>
  <div class="profile-grid">
    <div class="panel">
      <h2 class="h3"><?= e($user['name']) ?></h2>
      <p class="muted"><?= e($user['email']) ?><br>Member since <?= e(date('j F Y', strtotime($user['created_at']))) ?></p>
      <ul class="stat-row">
        <li><b><?= $orders ?></b><a href="orders.php">Orders</a></li>
        <li><b><?= $likes ?></b><span>Liked</span></li>
        <li><b><?= $ratings ?></b><span>Ratings</span></li>
      </ul>
      <form method="post" class="stack">
        <?= csrf_field() ?><input type="hidden" name="action" value="name">
        <label class="field"><span class="label">Display name</span><input name="name" value="<?= e($user['name']) ?>" required maxlength="80"></label>
        <button class="btn btn-ghost" type="submit">Save name</button>
      </form>
    </div>
    <div class="panel">
      <h2 class="h3">Change password</h2>
      <form method="post" class="stack">
        <?= csrf_field() ?><input type="hidden" name="action" value="password">
        <label class="field"><span class="label">Current password</span><input type="password" name="current" autocomplete="current-password" required></label>
        <label class="field"><span class="label">New password</span><input type="password" name="new" autocomplete="new-password" required><span class="hint">At least 8 characters, with a letter and a number.</span></label>
        <label class="field"><span class="label">Confirm new password</span><input type="password" name="new2" autocomplete="new-password" required></label>
        <button class="btn btn-ghost" type="submit">Change password</button>
      </form>
    </div>
  </div>

  <h2>Phones you liked</h2>
  <?php if (!$likedList): ?>
    <div class="empty small"><p>You haven't liked any phones yet. Tap the heart on a phone to save it here.</p><a class="btn btn-ghost" href="index.php#phones">Browse phones</a></div>
  <?php else: ?>
    <ul class="mini-list">
      <?php foreach ($likedList as $l): ?>
        <li><a href="product.php?id=<?= (int)$l['id'] ?>"><img src="<?= e($l['image']) ?>" alt="" width="48" height="63"><span><?= e($l['name']) ?></span></a><b><?= money($l['price']) ?></b></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
