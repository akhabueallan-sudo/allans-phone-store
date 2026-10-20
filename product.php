<?php
require_once __DIR__ . '/includes/products.php';

$id = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) { http_response_code(404); }

function load_product(int $id): ?array {
    $s = db()->prepare(product_base_sql() . ' WHERE p.id = :id');
    $s->execute([':uid' => user_id(), ':id' => $id]);
    return $s->fetch() ?: null;
}
$product = $id ? load_product($id) : null;

/* ---- submit or change a rating ---- */
if ($product && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    require_csrf_or_die();
    $rating  = filter_var($_POST['rating'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5]]);
    $comment = trim((string)($_POST['comment'] ?? ''));
    if ($rating === false || $rating === null) {
        flash('error', 'Choose a rating from 1 to 5 stars.');
    } elseif (mb_strlen($comment) > 300) {
        flash('error', 'Keep your review under 300 characters.');
    } else {
        // UNIQUE (user_id, product_id) means this inserts the first time and updates after that.
        $s = db()->prepare('INSERT INTO product_ratings (user_id, product_id, rating, comment) VALUES (:u, :p, :r, :c)
                            ON DUPLICATE KEY UPDATE rating = :r2, comment = :c2');
        $cm = $comment === '' ? null : $comment;
        $s->execute([':u' => user_id(), ':p' => $id, ':r' => $rating, ':c' => $cm, ':r2' => $rating, ':c2' => $cm]);
        flash('success', 'Thanks, your rating is saved.');
    }
    redirect('product.php?id=' . $id . '#reviews');
}

if (!$product) {
    http_response_code(404);
    $pageTitle = 'Phone not found';
    require __DIR__ . '/includes/header.php';
    echo '<section class="wrap narrow"><div class="empty"><h1>We could not find that phone</h1><p>It may have been removed, or the link is wrong.</p><a class="btn btn-primary" href="index.php#phones">Back to all phones</a></div></section>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$specs = [];
foreach (explode(';', $product['specs']) as $line) {
    $kv = explode('=', $line, 2);
    if (count($kv) === 2) $specs[trim($kv[0])] = trim($kv[1]);
}

$dist = array_fill(1, 5, 0);
$d = db()->prepare('SELECT rating, COUNT(*) n FROM product_ratings WHERE product_id = ? GROUP BY rating');
$d->execute([$id]);
foreach ($d->fetchAll() as $row) $dist[(int)$row['rating']] = (int)$row['n'];

$mine = null;
if (is_logged_in()) {
    $m = db()->prepare('SELECT rating, comment FROM product_ratings WHERE user_id = ? AND product_id = ?');
    $m->execute([user_id(), $id]);
    $mine = $m->fetch() ?: null;
}
$r = db()->prepare('SELECT r.rating, r.comment, r.updated_at, u.name FROM product_ratings r JOIN users u ON u.id = r.user_id
                    WHERE r.product_id = ? ORDER BY r.updated_at DESC LIMIT 20');
$r->execute([$id]);
$reviews = $r->fetchAll();

$inCart = 0;
if (is_logged_in()) {
    $c = db()->prepare('SELECT quantity FROM cart_items WHERE user_id = ? AND product_id = ?');
    $c->execute([user_id(), $id]);
    $inCart = (int)$c->fetchColumn();
}
$maxQty = max(0, (int)$product['stock'] - $inCart);

$pageTitle = $product['name'];
require __DIR__ . '/includes/header.php';
?>
<section class="wrap detail">
  <p class="crumbs"><a href="index.php#phones">All phones</a> / <a href="index.php?category=<?= e($product['category_slug']) ?>#phones"><?= e($product['category_name']) ?></a></p>
  <div class="detail-grid">
    <div class="detail-media"><img src="<?= e($product['image']) ?>" alt="<?= e($product['name']) ?>" width="320" height="420"></div>
    <div class="detail-info">
      <p class="card-cat"><?= e($product['brand']) ?> &middot; <?= e($product['category_name']) ?></p>
      <h1><?= e($product['name']) ?></h1>
      <div class="rating-line big">
        <?= stars_html($product['avg_rating'] === null ? null : (float)$product['avg_rating']) ?>
        <a href="#reviews" class="rating-text"><?= e(rating_label($product)) ?></a>
        <?= like_button($product, 'with-text') ?>
      </div>
      <p class="detail-price"><?= money($product['price']) ?></p>
      <p class="detail-desc"><?= e($product['description']) ?></p>
      <p><?= stock_note($product) ?><?= $inCart ? ' <span class="muted">You have ' . $inCart . ' in your cart.</span>' : '' ?></p>

      <div class="buy-row">
        <label class="field qty">
          <span class="label">Quantity</span>
          <input type="number" id="buy-qty" min="1" max="<?= max(1, $maxQty) ?>" value="1" <?= $maxQty < 1 ? 'disabled' : '' ?>>
        </label>
        <button type="button" class="btn btn-primary" data-add-to-cart data-product-id="<?= (int)$product['id'] ?>" data-qty-input="#buy-qty" <?= $maxQty < 1 ? 'disabled' : '' ?>>
          <?= (int)$product['stock'] === 0 ? 'Out of stock' : ($maxQty < 1 ? 'All available units are in your cart' : 'Add to cart') ?>
        </button>
      </div>

      <h2 class="h3">Specifications</h2>
      <dl class="specs">
        <?php foreach ($specs as $k => $v): ?><div><dt><?= e($k) ?></dt><dd><?= e($v) ?></dd></div><?php endforeach; ?>
      </dl>
    </div>
  </div>

  <section class="reviews" id="reviews" aria-labelledby="rev-h">
    <h2 id="rev-h">Ratings and reviews</h2>
    <div class="reviews-grid">
      <div class="summary">
        <?php if ((int)$product['rating_count'] === 0): ?>
          <p class="big-num">No ratings yet</p>
          <p class="muted">Be the first to rate this phone.</p>
        <?php else: ?>
          <p class="big-num"><?= number_format((float)$product['avg_rating'], 1) ?><span> / 5</span></p>
          <?= stars_html((float)$product['avg_rating']) ?>
          <p class="muted"><?= (int)$product['rating_count'] ?> <?= (int)$product['rating_count']===1?'rating':'ratings' ?></p>
          <ul class="bars">
          <?php for ($s = 5; $s >= 1; $s--): $pct = $product['rating_count'] ? round($dist[$s] * 100 / $product['rating_count']) : 0; ?>
            <li><span><?= $s ?> star</span><span class="bar"><i style="width:<?= $pct ?>%"></i></span><span class="n"><?= $dist[$s] ?></span></li>
          <?php endfor; ?>
          </ul>
        <?php endif; ?>
      </div>

      <div class="rate-box">
        <?php if (!is_logged_in()): ?>
          <h3>Rate this phone</h3>
          <p>Sign in to leave a rating.</p>
          <a class="btn btn-primary" href="login.php?next=<?= urlencode('product.php?id=' . $id) ?>">Sign in to rate</a>
        <?php else: ?>
          <h3><?= $mine ? 'Change your rating' : 'Rate this phone' ?></h3>
          <form method="post" action="product.php?id=<?= $id ?>#reviews">
            <?= csrf_field() ?>
            <fieldset class="star-input">
              <legend class="label">Your rating</legend>
              <?php for ($s = 5; $s >= 1; $s--): ?>
                <input type="radio" id="rate<?= $s ?>" name="rating" value="<?= $s ?>" <?= ($mine && (int)$mine['rating'] === $s) ? 'checked' : '' ?> required>
                <label for="rate<?= $s ?>" title="<?= $s ?> star<?= $s>1?'s':'' ?>"><?= icon('star', 28) ?><span class="sr"><?= $s ?> star<?= $s>1?'s':'' ?></span></label>
              <?php endfor; ?>
            </fieldset>
            <label class="field">
              <span class="label">Short review (optional)</span>
              <textarea name="comment" rows="3" maxlength="300" placeholder="What did you like or dislike?"><?= e($mine['comment'] ?? '') ?></textarea>
            </label>
            <button class="btn btn-primary" type="submit"><?= $mine ? 'Update rating' : 'Save rating' ?></button>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($reviews): ?>
    <ul class="review-list">
      <?php foreach ($reviews as $rv): ?>
        <li>
          <div class="rv-head"><b><?= e($rv['name']) ?></b> <?= stars_html((float)$rv['rating']) ?><span class="sr"><?= (int)$rv['rating'] ?> out of 5</span>
            <time class="muted" datetime="<?= e($rv['updated_at']) ?>"><?= e(date('j M Y', strtotime($rv['updated_at']))) ?></time></div>
          <?php if ($rv['comment']): ?><p><?= e($rv['comment']) ?></p><?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </section>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
