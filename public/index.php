<?php

require_once '/var/www/src/config/session.php';
require_once '/var/www/src/config/database.php';

$pageTitle = 'Trang chủ';

function e($value): string
{
  return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Chuẩn hóa đường dẫn ảnh.
 * Database có thể lưu "/uploads/products/a.jpg" hoặc chỉ "a.jpg".
 */
function productImageUrl(?string $file): string
{
  $file = (string)$file;

  if ($file === '') {
    return '';
  }

  if (strpos($file, '/uploads/products/') !== 0) {
    $file = '/uploads/products/' . ltrim($file, '/');
  }

  return $file;
}

require_once '/var/www/src/includes/frontend/header.php';
require_once '/var/www/src/includes/frontend/navbar.php';


/*
|--------------------------------------------------------------------------
| Lấy danh mục sản phẩm (kèm số sản phẩm đang bán)
|--------------------------------------------------------------------------
*/

$categories = [];

$categorySql = "
    SELECT
        c.CategoryID,
        c.CategoryName,
        c.Description,
        (
            SELECT COUNT(*)
            FROM products p
            WHERE p.CategoryID = c.CategoryID
              AND p.IsActive = 1
        ) AS ProductCount
    FROM categories c
    ORDER BY c.CategoryName ASC
";

$categoryResult = $conn->query($categorySql);

if ($categoryResult) {
  while ($row = $categoryResult->fetch_assoc()) {
    $categories[] = $row;
  }
} else {
  error_log('Home categories query error: ' . $conn->error);
}


/*
|--------------------------------------------------------------------------
| Lấy sản phẩm mới nhất
|
| Ảnh lấy bằng truy vấn con để mỗi sản phẩm chỉ xuất hiện 1 lần,
| ưu tiên ảnh chính; nếu chưa đặt ảnh chính thì lấy ảnh đầu tiên.
|--------------------------------------------------------------------------
*/

$products = [];

$productSql = "
    SELECT
        p.ProductID,
        p.ProductCode,
        p.ProductName,
        p.Description,
        p.Unit,
        p.Price,
        p.StockQuantity,
        p.CategoryID,
        c.CategoryName,
        (
            SELECT pi.ImageFile
            FROM product_images pi
            WHERE pi.ProductID = p.ProductID
            ORDER BY pi.IsPrimary DESC, pi.SortOrder ASC, pi.ProductImageID ASC
            LIMIT 1
        ) AS ImageFile
    FROM products p
    LEFT JOIN categories c
        ON p.CategoryID = c.CategoryID
    WHERE p.IsActive = 1
    ORDER BY p.ProductID DESC
    LIMIT 8
";

$productResult = $conn->query($productSql);

if ($productResult) {
  while ($row = $productResult->fetch_assoc()) {
    $products[] = $row;
  }
} else {
  error_log('Home products query error: ' . $conn->error);
}

// Ảnh thật của sản phẩm để minh họa ở banner (tối đa 3).
$heroImages = [];

foreach ($products as $product) {
  if (!empty($product['ImageFile']) && count($heroImages) < 3) {
    $heroImages[] = [
      'src' => productImageUrl($product['ImageFile']),
      'alt' => $product['ProductName'],
    ];
  }
}

?>

<link
  href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap"
  rel="stylesheet">
<link
  href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
  rel="stylesheet">

<style>
  :root {
    --brand: #0E7C66;
    --brand-dark: #0A5F4F;
    --brand-soft: #E3F3EE;
    --ink: #16212B;
    --muted: #5F6B78;
    --line: #E3E8ED;
    --surface: #FFFFFF;
    --page: #F3F5F7;
    --hero: #12302B;
    --accent: #F2B84B;
  }

  body {
    background: var(--page);
    color: var(--ink);
    font-family: 'Be Vietnam Pro', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
  }

  .home-page {
    padding-top: 1.75rem;
    padding-bottom: 3rem;
  }

  /* =========================================================
     BANNER
  ========================================================= */

  .hero {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 2rem;
    margin-bottom: 2rem;
    padding: 3rem 3.5rem;
    min-height: 320px;
    border-radius: 20px;
    background: var(--hero);
    color: #fff;
    overflow: hidden;
  }

  .hero-content {
    max-width: 540px;
  }

  .hero-label {
    display: inline-block;
    margin-bottom: 1.1rem;
    padding: .4rem .9rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, .12);
    color: #CFEBE3;
    font-size: .85rem;
    font-weight: 600;
  }

  .hero-content h1 {
    margin: 0 0 1rem;
    font-size: 2.6rem;
    font-weight: 800;
    letter-spacing: -.025em;
    line-height: 1.2;
  }

  .hero-content p {
    margin: 0 0 1.75rem;
    color: #BFD6D0;
    font-size: 1.02rem;
    line-height: 1.7;
  }

  .hero-button {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    padding: .8rem 1.5rem;
    border-radius: 10px;
    background: var(--accent);
    color: #2B2108;
    font-weight: 700;
    text-decoration: none;
    transition: background .2s ease;
  }

  .hero-button:hover,
  .hero-button:focus-visible {
    background: #F8CC78;
    color: #2B2108;
  }

  /* Ảnh sản phẩm / biểu tượng ở banner */

  .hero-visual {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-shrink: 0;
  }

  .hero-tile {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 130px;
    height: 150px;
    border-radius: 18px;
    background: #fff;
    overflow: hidden;
  }

  .hero-tile img {
    width: 100%;
    height: 100%;
    padding: .75rem;
    object-fit: contain;
  }

  .hero-tile.is-icon {
    background: rgba(255, 255, 255, .1);
    border: 1px solid rgba(255, 255, 255, .2);
    color: #fff;
    font-size: 2.6rem;
  }

  .hero-tile:nth-child(2) {
    transform: translateY(-1.25rem);
  }

  .hero-tile:nth-child(3) {
    transform: translateY(.75rem);
  }

  /* =========================================================
     NỘI DUNG CHÍNH
  ========================================================= */

  .home-content {
    display: grid;
    grid-template-columns: 250px minmax(0, 1fr);
    gap: 2rem;
    align-items: start;
  }

  /* Danh mục */

  .category-box {
    padding: 1.4rem;
    border: 1px solid var(--line);
    border-radius: 16px;
    background: var(--surface);
  }

  .category-title {
    margin-bottom: 1rem;
    font-size: 1.05rem;
    font-weight: 700;
  }

  .category-list {
    margin: 0;
    padding: 0;
    list-style: none;
  }

  .category-list li+li {
    margin-top: .25rem;
  }

  .category-list a {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .5rem;
    padding: .65rem .8rem;
    border-radius: 10px;
    color: var(--ink);
    font-size: .94rem;
    font-weight: 500;
    text-decoration: none;
    transition: background .2s ease, color .2s ease;
  }

  .category-list a:hover,
  .category-list a:focus-visible {
    background: var(--brand-soft);
    color: var(--brand-dark);
  }

  .category-count {
    min-width: 1.7rem;
    padding: .1rem .5rem;
    border-radius: 999px;
    background: #EEF1F4;
    color: var(--muted);
    font-size: .76rem;
    font-weight: 600;
    text-align: center;
  }

  /* Tiêu đề khu sản phẩm */

  .section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.25rem;
  }

  .section-header h2 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 700;
    letter-spacing: -.02em;
  }

  .view-all {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    color: var(--brand);
    font-weight: 600;
    text-decoration: none;
  }

  .view-all:hover,
  .view-all:focus-visible {
    color: var(--brand-dark);
    text-decoration: underline;
  }

  /* =========================================================
     LƯỚI SẢN PHẨM
  ========================================================= */

  .product-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 1.1rem;
  }

  .product-card {
    display: flex;
    flex-direction: column;
    border: 1px solid var(--line);
    border-radius: 16px;
    background: var(--surface);
    overflow: hidden;
    transition: border-color .2s ease;
  }

  .product-card:hover {
    border-color: #B9C6CF;
  }

  .product-image {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    height: 200px;
    background: #F6F8FA;
    overflow: hidden;
  }

  .product-image img {
    width: 100%;
    height: 100%;
    padding: 1rem;
    object-fit: contain;
    transition: transform .3s ease;
  }

  .product-card:hover .product-image img {
    transform: scale(1.05);
  }

  .no-image {
    color: #9AA5B1;
    font-size: .85rem;
  }

  .product-flag {
    position: absolute;
    top: .7rem;
    left: .7rem;
    padding: .25rem .65rem;
    border-radius: 999px;
    font-size: .74rem;
    font-weight: 600;
  }

  .flag-out {
    background: #FEE2E2;
    color: #991B1B;
  }

  .flag-low {
    background: #FEF3C7;
    color: #92400E;
  }

  .product-info {
    display: flex;
    flex: 1;
    flex-direction: column;
    padding: 1rem 1.05rem 1.1rem;
  }

  .product-category {
    margin-bottom: .35rem;
    color: var(--muted);
    font-size: .8rem;
  }

  .product-name {
    display: -webkit-box;
    min-height: 2.8em;
    margin: 0 0 .7rem;
    overflow: hidden;
    font-size: 1rem;
    font-weight: 600;
    line-height: 1.4;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
  }

  .product-name a {
    color: var(--ink);
    text-decoration: none;
  }

  .product-name a:hover,
  .product-name a:focus-visible {
    color: var(--brand);
  }

  .product-price {
    margin-bottom: .3rem;
    font-size: 1.2rem;
    font-weight: 700;
    letter-spacing: -.01em;
    font-variant-numeric: tabular-nums;
  }

  .product-stock {
    margin-bottom: 1rem;
    color: var(--muted);
    font-size: .82rem;
  }

  .btn-detail {
    display: block;
    margin-top: auto;
    padding: .6rem;
    border: 1px solid var(--brand);
    border-radius: 10px;
    background: transparent;
    color: var(--brand);
    font-weight: 600;
    text-align: center;
    text-decoration: none;
    transition: background .2s ease, color .2s ease;
  }

  .btn-detail:hover,
  .btn-detail:focus-visible {
    background: var(--brand);
    color: #fff;
  }

  .empty-products {
    padding: 3rem;
    border: 1px dashed var(--line);
    border-radius: 16px;
    background: var(--surface);
    color: var(--muted);
    text-align: center;
  }

  /* =========================================================
     RESPONSIVE
  ========================================================= */

  @media (max-width: 1200px) {
    .product-grid {
      grid-template-columns: repeat(3, minmax(0, 1fr));
    }
  }

  @media (max-width: 992px) {
    .hero {
      padding: 2.25rem;
    }

    .hero-visual {
      display: none;
    }

    .home-content {
      grid-template-columns: 210px minmax(0, 1fr);
      gap: 1.5rem;
    }

    .product-grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
  }

  @media (max-width: 768px) {
    .home-content {
      grid-template-columns: 1fr;
    }

    .category-list {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: .25rem;
    }

    .category-list li+li {
      margin-top: 0;
    }

    .hero {
      min-height: 230px;
      padding: 1.75rem;
    }

    .hero-content h1 {
      font-size: 1.9rem;
    }
  }

  @media (max-width: 480px) {

    .product-grid,
    .category-list {
      grid-template-columns: 1fr;
    }

    .hero-content h1 {
      font-size: 1.65rem;
    }
  }
</style>


<main class="container home-page">

  <!-- =====================================================
         BANNER
    ====================================================== -->

  <section class="hero">

    <div class="hero-content">

      <span class="hero-label">
        Công nghệ hiện đại
      </span>

      <h1>
        Khám phá thế giới<br>
        thiết bị điện tử
      </h1>

      <p>
        Laptop, điện thoại, máy tính và phụ kiện công nghệ.
        Đa dạng sản phẩm, đáp ứng mọi nhu cầu của bạn.
      </p>

      <a href="/products.php" class="hero-button">
        Khám phá ngay
        <i class="bi bi-arrow-right"></i>
      </a>

    </div>

    <div class="hero-visual" aria-hidden="true">
      <?php if (!empty($heroImages)): ?>
        <?php foreach ($heroImages as $image): ?>
          <div class="hero-tile">
            <img src="<?= e($image['src']) ?>" alt="" loading="lazy">
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="hero-tile is-icon"><i class="bi bi-laptop"></i></div>
        <div class="hero-tile is-icon"><i class="bi bi-phone"></i></div>
        <div class="hero-tile is-icon"><i class="bi bi-headphones"></i></div>
      <?php endif; ?>
    </div>

  </section>

  <!-- =====================================================
         DANH MỤC + SẢN PHẨM
    ====================================================== -->

  <div class="home-content">


    <!-- DANH MỤC -->

    <aside class="category-box">

      <div class="category-title">
        Danh mục sản phẩm
      </div>

      <ul class="category-list">

        <?php if (!empty($categories)): ?>

          <?php foreach ($categories as $category): ?>

            <li>
              <a
                href="/products.php?category=<?= urlencode($category['CategoryID']) ?>"
                title="<?= e($category['Description'] ?? '') ?>">
                <span><?= e($category['CategoryName']) ?></span>
                <span class="category-count"><?= (int)$category['ProductCount'] ?></span>
              </a>
            </li>

          <?php endforeach; ?>

        <?php else: ?>

          <li>
            <span class="text-secondary">
              Chưa có danh mục
            </span>
          </li>

        <?php endif; ?>

      </ul>

    </aside>


    <!-- DANH SÁCH SẢN PHẨM -->

    <section class="products-section">

      <div class="section-header">

        <h2>
          Sản phẩm mới nhất
        </h2>

        <a href="/products.php" class="view-all">
          Xem tất cả
          <i class="bi bi-arrow-right"></i>
        </a>

      </div>


      <?php if (!empty($products)): ?>

        <div class="product-grid">

          <?php foreach ($products as $product): ?>

            <?php
            $stock = (int)$product['StockQuantity'];
            $detailUrl = '/product-detail.php?id=' . (int)$product['ProductID'];
            $imageUrl = productImageUrl($product['ImageFile'] ?? '');
            ?>

            <article class="product-card">

              <!-- HÌNH -->

              <div class="product-image">

                <?php if ($imageUrl !== ''): ?>

                  <img
                    src="<?= e($imageUrl) ?>"
                    alt="<?= e($product['ProductName']) ?>"
                    loading="lazy">

                <?php else: ?>

                  <div class="no-image">
                    Chưa có hình ảnh
                  </div>

                <?php endif; ?>

                <?php if ($stock <= 0): ?>
                  <span class="product-flag flag-out">Hết hàng</span>
                <?php elseif ($stock <= 5): ?>
                  <span class="product-flag flag-low">Sắp hết hàng</span>
                <?php endif; ?>

              </div>


              <!-- THÔNG TIN -->

              <div class="product-info">

                <?php if (!empty($product['CategoryName'])): ?>
                  <div class="product-category">
                    <?= e($product['CategoryName']) ?>
                  </div>
                <?php endif; ?>

                <h3 class="product-name">
                  <a href="<?= e($detailUrl) ?>">
                    <?= e($product['ProductName']) ?>
                  </a>
                </h3>

                <div class="product-price">
                  <?= number_format((float)$product['Price'], 0, ',', '.') ?> đ
                </div>

                <div class="product-stock">
                  <?php if ($stock > 0): ?>
                    Còn <?= $stock ?> sản phẩm
                  <?php else: ?>
                    Tạm thời hết hàng
                  <?php endif; ?>
                </div>

                <a href="<?= e($detailUrl) ?>" class="btn-detail">
                  Xem sản phẩm
                </a>

              </div>

            </article>

          <?php endforeach; ?>

        </div>

      <?php else: ?>

        <div class="empty-products">
          Chưa có sản phẩm để hiển thị.
        </div>

      <?php endif; ?>

    </section>

  </div>

</main>


<?php

require_once '/var/www/src/includes/frontend/footer.php';

?>