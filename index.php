<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/products.php';

$q    = trim((string)($_GET['q'] ?? ''));
$cat  = (string)($_GET['category'] ?? '');
$sort = (string)($_GET['sort'] ?? 'newest');
$sorts = [
    'newest'     => 'p.id ASC',
    'price_asc'  => 'p.price ASC, p.name ASC',
    'price_desc' => 'p.price DESC, p.name ASC',
    'rating'     => 'avg_rating IS NULL, avg_rating DESC, rating_count DESC',
    'likes'      => 'like_count DESC, p.name ASC',
];
if (!isset($sorts[$sort])) $sort = 'newest';

$categories = db()->query('SELECT id, name, slug FROM categories ORDER BY id')->fetchAll();
if ($cat !== '' && !in_array($cat, array_column($categories, 'slug'), true)) $cat = '';

$sql = product_base_sql() . ' WHERE 1=1';
$params = [':uid' => user_id()];
if ($q !== '') {
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
    $sql .= ' AND (p.name LIKE :q1 OR p.brand LIKE :q2)';
    $params[':q1'] = $like;
    $params[':q2'] = $like;
}
if ($cat !== '') { $sql .= ' AND c.slug = :cat'; $params[':cat'] = $cat; }
$sql .= ' ORDER BY ' . $sorts[$sort];
$stmt = db()->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$hero = db()->query("SELECT name, image FROM products WHERE category_id = (SELECT id FROM categories WHERE slug='flagship') ORDER BY id LIMIT 3")->fetchAll();

function shop_url(array $over, array $base): string {
    $p = array_filter(array_merge($base, $over), fn($v) => $v !== '' && $v !== null && $v !== 'newest');
    return 'index.php' . ($p ? '?' . http_build_query($p) : '') . '#phones';
}
$base = ['q' => $q, 'category' => $cat, 'sort' => $sort];

require __DIR__ . '/includes/header.php';
?>
<section class="hero">
  <div class="wrap hero-grid">
    <div class="hero-copy">
      <h1>Phones worth carrying.</h1>
      <p>Twenty-four handsets from budget to foldable. Read ratings from people who bought them, put your pick in the cart and check out in a minute.</p>
      <a class="btn btn-primary" href="#phones">Browse phones</a>
    </div>
    <div class="hero-stack" aria-hidden="true">
      <?php foreach ($hero as $i => $h): ?>
        <img class="hero-phone hp-<?= $i ?>" src="<?= e($h['image']) ?>" alt="" width="320" height="420">
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="wrap shop" id="phones">
  <form class="filters" method="get" action="index.php#phones" role="search">
    <label class="field grow">
      <span class="label">Search phones</span>
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="Try &quot;Nimbus&quot; or &quot;Fold&quot;" maxlength="60">
    </label>
    <label class="field">
      <span class="label">Sort by</span>
      <select name="sort" data-autosubmit>
        <option value="newest" <?= $sort==='newest'?'selected':'' ?>>Featured</option>
        <option value="price_asc" <?= $sort==='price_asc'?'selected':'' ?>>Price: low to high</option>
        <option value="price_desc" <?= $sort==='price_desc'?'selected':'' ?>>Price: high to low</option>
        <option value="rating" <?= $sort==='rating'?'selected':'' ?>>Top rated</option>
        <option value="likes" <?= $sort==='likes'?'selected':'' ?>>Most liked</option>
      </select>
    </label>
    <?php if ($cat !== ''): ?><input type="hidden" name="category" value="<?= e($cat) ?>"><?php endif; ?>
    <button class="btn btn-primary" type="submit">Search</button>
  </form>

  <nav class="chips" aria-label="Categories">
    <a class="chip <?= $cat===''?'active':'' ?>" href="<?= e(shop_url(['category'=>''], $base)) ?>" <?= $cat===''?'aria-current="true"':'' ?>>All phones</a>
    <?php foreach ($categories as $c): ?>
      <a class="chip <?= $cat===$c['slug']?'active':'' ?>" href="<?= e(shop_url(['category'=>$c['slug']], $base)) ?>" <?= $cat===$c['slug']?'aria-current="true"':'' ?>><?= e($c['name']) ?></a>
    <?php endforeach; ?>
  </nav>

  <p class="result-count" role="status"><?= count($products) ?> <?= count($products)===1?'phone':'phones' ?><?= $q!=='' ? ' matching "' . e($q) . '"' : '' ?></p>

  <?php if (!$products): ?>
    <div class="empty">
      <h2>No phones match that search</h2>
      <p>Check the spelling, or try a brand name like Aurex, Veltra or Korra.</p>
      <a class="btn btn-ghost" href="index.php#phones">Clear filters</a>
    </div>
  <?php else: ?>
    <div class="grid">
      <?php foreach ($products as $p) render_product_card($p); ?>
    </div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
