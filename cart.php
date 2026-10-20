<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();
$uid = user_id();

/** Cart lines for the signed-in user only. Prices come from the products table. */
function cart_lines(int $uid): array {
    $s = db()->prepare('SELECT ci.product_id, ci.quantity, p.name, p.price, p.image, p.stock
                        FROM cart_items ci JOIN products p ON p.id = ci.product_id
                        WHERE ci.user_id = ? ORDER BY ci.added_at, ci.id');
    $s->execute([$uid]);
    return $s->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_or_die();
    $pid = filter_var($_POST['product_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $action = $_POST['action'] ?? '';
    if ($pid) {
        // Scoped by user_id: you can only ever change your own rows, whatever product_id is sent.
        $s = db()->prepare('SELECT ci.quantity, p.stock, p.name FROM cart_items ci JOIN products p ON p.id = ci.product_id WHERE ci.user_id = ? AND ci.product_id = ?');
        $s->execute([$uid, $pid]);
        $row = $s->fetch();
        if ($row) {
            $qty = (int)$row['quantity'];
            if ($action === 'inc') {
                if ($qty + 1 > (int)$row['stock']) flash('error', 'Only ' . (int)$row['stock'] . ' of ' . $row['name'] . ' in stock.');
                else db()->prepare('UPDATE cart_items SET quantity = quantity + 1 WHERE user_id = ? AND product_id = ?')->execute([$uid, $pid]);
            } elseif ($action === 'dec') {
                if ($qty <= 1) flash('info', 'Quantity cannot go below 1. Use Remove to take it out of your cart.');
                else db()->prepare('UPDATE cart_items SET quantity = quantity - 1 WHERE user_id = ? AND product_id = ? AND quantity > 1')->execute([$uid, $pid]);
            } elseif ($action === 'remove') {
                db()->prepare('DELETE FROM cart_items WHERE user_id = ? AND product_id = ?')->execute([$uid, $pid]);
                flash('success', $row['name'] . ' removed from your cart.');
            }
        }
    }
    redirect('cart.php');
}

$lines = cart_lines($uid);
$total = 0; $problem = false;
foreach ($lines as &$l) {
    $l['line_total'] = round((float)$l['price'] * (int)$l['quantity'], 2);
    $l['short'] = (int)$l['quantity'] > (int)$l['stock'];
    $problem = $problem || $l['short'];
    $total += $l['line_total'];
}
unset($l);

$pageTitle = 'My cart';
require __DIR__ . '/includes/header.php';
?>
<section class="wrap">
  <h1>My cart</h1>
  <?php if (!$lines): ?>
    <div class="empty">
      <h2>Your cart is empty</h2>
      <p>Phones you add will wait here, even if you sign out and back in.</p>
      <a class="btn btn-primary" href="index.php#phones">Find a phone</a>
    </div>
  <?php else: ?>
    <div class="cart-grid">
      <ul class="cart-list">
        <?php foreach ($lines as $l): ?>
          <li class="cart-line">
            <a href="product.php?id=<?= (int)$l['product_id'] ?>"><img src="<?= e($l['image']) ?>" alt="" width="72" height="95"></a>
            <div class="cl-info">
              <a class="cl-name" href="product.php?id=<?= (int)$l['product_id'] ?>"><?= e($l['name']) ?></a>
              <span class="muted"><?= money($l['price']) ?> each</span>
              <?php if ($l['short']): ?><span class="err">Only <?= (int)$l['stock'] ?> in stock. Lower the quantity to check out.</span><?php endif; ?>
            </div>
            <div class="qty-ctl" role="group" aria-label="Quantity for <?= e($l['name']) ?>">
              <form method="post"><?= csrf_field() ?><input type="hidden" name="product_id" value="<?= (int)$l['product_id'] ?>"><input type="hidden" name="action" value="dec">
                <button class="qbtn" aria-label="Decrease quantity" <?= (int)$l['quantity'] <= 1 ? 'disabled' : '' ?>>&minus;</button></form>
              <output class="qval" aria-live="polite"><?= (int)$l['quantity'] ?></output>
              <form method="post"><?= csrf_field() ?><input type="hidden" name="product_id" value="<?= (int)$l['product_id'] ?>"><input type="hidden" name="action" value="inc">
                <button class="qbtn" aria-label="Increase quantity" <?= (int)$l['quantity'] >= (int)$l['stock'] ? 'disabled' : '' ?>>+</button></form>
            </div>
            <b class="cl-total"><?= money($l['line_total']) ?></b>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="product_id" value="<?= (int)$l['product_id'] ?>"><input type="hidden" name="action" value="remove">
              <button class="link-btn" aria-label="Remove <?= e($l['name']) ?>">Remove</button></form>
          </li>
        <?php endforeach; ?>
      </ul>
      <aside class="panel summary-card">
        <h2 class="h3">Order summary</h2>
        <dl class="sum"><div><dt>Items</dt><dd><?= array_sum(array_column($lines, 'quantity')) ?></dd></div>
          <div><dt>Delivery</dt><dd>Free</dd></div>
          <div class="grand"><dt>Total</dt><dd><?= money($total) ?></dd></div></dl>
        <?php if ($problem): ?>
          <button class="btn btn-primary block" disabled>Fix quantities to continue</button>
        <?php else: ?>
          <a class="btn btn-primary block" href="checkout.php">Go to checkout</a>
        <?php endif; ?>
        <a class="btn btn-quiet block" href="index.php#phones">Keep shopping</a>
      </aside>
    </div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
