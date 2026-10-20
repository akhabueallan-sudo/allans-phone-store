<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$s = db()->prepare('SELECT o.id, o.total, o.status, o.created_at, COALESCE(SUM(oi.quantity),0) AS items
                    FROM orders o LEFT JOIN order_items oi ON oi.order_id = o.id
                    WHERE o.user_id = ? GROUP BY o.id ORDER BY o.created_at DESC, o.id DESC');
$s->execute([user_id()]);
$orders = $s->fetchAll();

$pageTitle = 'My orders';
require __DIR__ . '/includes/header.php';
?>
<section class="wrap">
  <h1>My orders</h1>
  <?php if (!$orders): ?>
    <div class="empty"><h2>No orders yet</h2><p>When you place an order, it will show up here.</p><a class="btn btn-primary" href="index.php#phones">Find a phone</a></div>
  <?php else: ?>
    <div class="table-wrap">
    <table class="table">
      <thead><tr><th scope="col">Order</th><th scope="col">Placed</th><th scope="col">Items</th><th scope="col">Total</th><th scope="col">Status</th><th scope="col"><span class="sr">Details</span></th></tr></thead>
      <tbody>
      <?php foreach ($orders as $o): ?>
        <tr>
          <th scope="row">#<?= str_pad((string)$o['id'], 5, '0', STR_PAD_LEFT) ?></th>
          <td><?= e(date('j M Y, H:i', strtotime($o['created_at']))) ?></td>
          <td><?= (int)$o['items'] ?></td>
          <td><?= money($o['total']) ?></td>
          <td><span class="pill"><?= e(ucfirst($o['status'])) ?></span></td>
          <td><a href="order.php?id=<?= (int)$o['id'] ?>">View</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
