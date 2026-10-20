<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();
$uid = user_id();

/** Reads the cart with row locks. Everything (price, stock) comes from MySQL, never from the browser. */
function locked_cart(PDO $pdo, int $uid): array {
    $s = $pdo->prepare('SELECT ci.product_id, ci.quantity, p.name, p.price, p.stock
                        FROM cart_items ci JOIN products p ON p.id = ci.product_id
                        WHERE ci.user_id = ? ORDER BY p.id FOR UPDATE');
    $s->execute([$uid]);
    return $s->fetchAll();
}

$errors = [];
$old = ['name' => user_name(), 'phone' => '', 'address' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_or_die();
    $old = [
        'name'    => trim((string)($_POST['name'] ?? '')),
        'phone'   => trim((string)($_POST['phone'] ?? '')),
        'address' => trim((string)($_POST['address'] ?? '')),
    ];
    if (mb_strlen($old['name']) < 2 || mb_strlen($old['name']) > 80) $errors['name'] = 'Enter the name for the delivery.';
    if (!preg_match('/^[0-9+()\s\-]{7,30}$/', $old['phone'])) $errors['phone'] = 'Enter a phone number with 7 to 30 digits.';
    if (mb_strlen($old['address']) < 8 || mb_strlen($old['address']) > 255) $errors['address'] = 'Enter a full delivery address (8 to 255 characters).';

    if (!$errors) {
        $pdo = db();
        try {
            $pdo->beginTransaction();                       // everything below succeeds together or not at all
            $lines = locked_cart($pdo, $uid);
            if (!$lines) throw new RuntimeException('Your cart is empty.');

            $cents = 0;
            foreach ($lines as $l) {
                if ((int)$l['stock'] < (int)$l['quantity']) {
                    throw new RuntimeException($l['stock'] > 0
                        ? 'Only ' . (int)$l['stock'] . ' of ' . $l['name'] . ' left in stock. Lower the quantity in your cart.'
                        : $l['name'] . ' just went out of stock. Remove it from your cart to continue.');
                }
                $cents += (int)round((float)$l['price'] * 100) * (int)$l['quantity'];   // server-side price
            }

            $o = $pdo->prepare('INSERT INTO orders (user_id, total, shipping_name, shipping_phone, shipping_address) VALUES (?, ?, ?, ?, ?)');
            $o->execute([$uid, number_format($cents / 100, 2, '.', ''), $old['name'], $old['phone'], $old['address']]);
            $orderId = (int)$pdo->lastInsertId();

            $oi  = $pdo->prepare('INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity) VALUES (?, ?, ?, ?, ?)');
            // The "AND stock >= ?" guard means stock can never go negative, even if two buyers check out at once.
            $dec = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?');
            foreach ($lines as $l) {
                $oi->execute([$orderId, $l['product_id'], $l['name'], $l['price'], $l['quantity']]);
                $dec->execute([$l['quantity'], $l['product_id'], $l['quantity']]);
                if ($dec->rowCount() !== 1) throw new RuntimeException('Stock changed while you were checking out. Please review your cart.');
            }
            $pdo->prepare('DELETE FROM cart_items WHERE user_id = ?')->execute([$uid]);   // only after everything above worked
            $pdo->commit();
            redirect('order.php?id=' . $orderId . '&placed=1');
        } catch (RuntimeException $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash('error', $ex->getMessage());
            redirect('cart.php');
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log($ex->getMessage());
            flash('error', 'We could not place your order, and you were not charged. Please try again.');
            redirect('checkout.php');
        }
    }
}

$s = db()->prepare('SELECT ci.quantity, p.name, p.price, p.image FROM cart_items ci JOIN products p ON p.id = ci.product_id WHERE ci.user_id = ? ORDER BY ci.added_at, ci.id');
$s->execute([$uid]);
$lines = $s->fetchAll();
if (!$lines) { flash('info', 'Your cart is empty. Add a phone to check out.'); redirect('cart.php'); }
$total = 0; foreach ($lines as $l) $total += (float)$l['price'] * (int)$l['quantity'];

$pageTitle = 'Checkout';
require __DIR__ . '/includes/header.php';
?>
<section class="wrap">
  <h1>Checkout</h1>
  <p class="flash flash-info">This is a demo store. No payment is taken and no real order is created.</p>
  <div class="cart-grid">
    <form method="post" class="panel stack" novalidate>
      <h2 class="h3">Delivery details</h2>
      <?= csrf_field() ?>
      <label class="field"><span class="label">Full name</span>
        <input name="name" value="<?= e($old['name']) ?>" autocomplete="name" required <?= isset($errors['name'])?'aria-invalid="true"':'' ?>>
        <?php if (isset($errors['name'])): ?><span class="err"><?= e($errors['name']) ?></span><?php endif; ?></label>
      <label class="field"><span class="label">Phone number</span>
        <input type="tel" name="phone" value="<?= e($old['phone']) ?>" autocomplete="tel" required <?= isset($errors['phone'])?'aria-invalid="true"':'' ?>>
        <?php if (isset($errors['phone'])): ?><span class="err"><?= e($errors['phone']) ?></span><?php endif; ?></label>
      <label class="field"><span class="label">Delivery address</span>
        <textarea name="address" rows="3" autocomplete="street-address" required <?= isset($errors['address'])?'aria-invalid="true"':'' ?>><?= e($old['address']) ?></textarea>
        <?php if (isset($errors['address'])): ?><span class="err"><?= e($errors['address']) ?></span><?php endif; ?></label>
      <p class="hint">Payment: pay on delivery (simulated).</p>
      <button class="btn btn-primary" type="submit">Place order</button>
    </form>
    <aside class="panel summary-card">
      <h2 class="h3">Your order</h2>
      <ul class="mini-list tight">
        <?php foreach ($lines as $l): ?>
          <li><span><img src="<?= e($l['image']) ?>" alt="" width="40" height="53"><?= e($l['name']) ?> &times; <?= (int)$l['quantity'] ?></span><b><?= money((float)$l['price'] * (int)$l['quantity']) ?></b></li>
        <?php endforeach; ?>
      </ul>
      <dl class="sum"><div class="grand"><dt>Total</dt><dd><?= money($total) ?></dd></div></dl>
      <p class="hint">Prices and stock are checked again when you place the order.</p>
      <a class="btn btn-quiet block" href="cart.php">Back to cart</a>
    </aside>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
