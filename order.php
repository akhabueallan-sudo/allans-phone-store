<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$id = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$order = null;
if ($id) {
    // "AND user_id = ?" is the access check: changing the id in the URL to someone else's order finds nothing.
    $s = db()->prepare('SELECT * FROM orders WHERE id = ? AND user_id = ?');
    $s->execute([$id, user_id()]);
    $order = $s->fetch() ?: null;
}
if (!$order) {
    http_response_code(404);
    $pageTitle = 'Order not found';
    require __DIR__ . '/includes/header.php';
    echo '<section class="wrap narrow"><div class="empty"><h1>We could not find that order</h1><p>Check your order history for the right one.</p><a class="btn btn-primary" href="orders.php">My orders</a></div></section>';
    require __DIR__ . '/includes/footer.php';
    exit;
}
$i = db()->prepare('SELECT product_id, product_name, unit_price, quantity FROM order_items WHERE order_id = ? ORDER BY id');
$i->execute([$order['id']]);
$items = $i->fetchAll();
$placed = isset($_GET['placed']);
$ref = '#' . str_pad((string)$order['id'], 5, '0', STR_PAD_LEFT);

$pageTitle = 'Order ' . $ref;
require __DIR__ . '/includes/header.php';
?>
<section class="wrap narrow">
  <?php if ($placed): ?>
    <div class="confirm">
      <span class="tick" aria-hidden="true">&#10003;</span>
      <h1>Order <?= e($ref) ?> is placed</h1>
      <p>Thank you, <?= e($order['shipping_name']) ?>. Your phones are on the way and you can pay on delivery.</p>
    </div>
  <?php else: ?>
    <h1>Order <?= e($ref) ?></h1>
  <?php endif; ?>
  <div class="panel">
    <p class="muted">Placed <?= e(date('j F Y, H:i', strtotime($order['created_at']))) ?> &middot; <span class="pill"><?= e(ucfirst($order['status'])) ?></span></p>
    <ul class="mini-list tight">
      <?php foreach ($items as $it): ?>
        <li><a href="product.php?id=<?= (int)$it['product_id'] ?>"><?= e($it['product_name']) ?> &times; <?= (int)$it['quantity'] ?></a><b><?= money((float)$it['unit_price'] * (int)$it['quantity']) ?></b></li>
      <?php endforeach; ?>
    </ul>
    <dl class="sum"><div class="grand"><dt>Total</dt><dd><?= money($order['total']) ?></dd></div></dl>
    <h2 class="h3">Delivery to</h2>
    <p><?= e($order['shipping_name']) ?><br><?= nl2br(e($order['shipping_address'])) ?><br><?= e($order['shipping_phone']) ?></p>
  </div>
  <p class="actions-row"><a class="btn btn-ghost" href="orders.php">All my orders</a> <a class="btn btn-primary" href="index.php#phones">Keep shopping</a></p>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
