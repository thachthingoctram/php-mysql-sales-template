<?php

require_once '/var/www/src/config/session.php';
require_once '/var/www/src/config/database.php';

$pageTitle = 'Trang chủ';

require_once '/var/www/src/includes/frontend/header.php';
require_once '/var/www/src/includes/frontend/navbar.php';


/*
|--------------------------------------------------------------------------
| Lấy danh mục sản phẩm
|--------------------------------------------------------------------------
*/

$categories = [];

$categorySql = "
    SELECT
        CategoryID,
        CategoryName,
        Description
    FROM categories
    ORDER BY CategoryName ASC
";

$categoryResult = $conn->query($categorySql);

if ($categoryResult) {
  while ($row = $categoryResult->fetch_assoc()) {
    $categories[] = $row;
  }
}


/*
|--------------------------------------------------------------------------
| Lấy sản phẩm
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
        pi.ImageFile
    FROM products p

    LEFT JOIN categories c
        ON p.CategoryID = c.CategoryID

    LEFT JOIN product_images pi
        ON p.ProductID = pi.ProductID
        AND pi.IsPrimary = 1

    WHERE p.IsActive = 1

    ORDER BY p.ProductID DESC

    LIMIT 8
";

$productResult = $conn->query($productSql);

if ($productResult) {
  while ($row = $productResult->fetch_assoc()) {
    $products[] = $row;
  }
}

?>

<style>
  /* =========================================================
   TRANG CHỦ
========================================================= */

  .home-page {
    width: 100%;
    min-height: calc(100vh - 120px);
    background: #f5f6f8;
    padding: 30px 50px 50px;
  }


  /* =========================================================
   BANNER
========================================================= */

  .hero {
    width: 100%;
    min-height: 280px;

    border-radius: 16px;

    background: linear-gradient(135deg,
        #2563eb 0%,
        #1d4ed8 50%,
        #1e40af 100%);

    color: white;

    display: flex;
    align-items: center;
    justify-content: space-between;

    padding: 45px 60px;
    margin-bottom: 30px;

    overflow: hidden;
  }

  .hero-content {
    max-width: 650px;
  }

  .hero-content h1 {
    font-size: 42px;
    font-weight: 700;
    margin-bottom: 15px;
  }

  .hero-content p {
    font-size: 18px;
    line-height: 1.6;
    margin-bottom: 25px;
    opacity: 0.95;
  }

  .hero-button {
    display: inline-block;

    background: white;
    color: #1d4ed8;

    padding: 12px 25px;

    border-radius: 8px;

    text-decoration: none;

    font-weight: 600;

    transition: 0.2s;
  }

  .hero-button:hover {
    background: #f1f5f9;
    color: #1e40af;
  }

  .hero-icon {
    font-size: 130px;
    opacity: 0.18;
    margin-right: 60px;
  }


  /* =========================================================
   NỘI DUNG CHÍNH
========================================================= */

  .home-content {
    display: grid;
    grid-template-columns: 250px 1fr;
    gap: 30px;
  }


  /* =========================================================
   DANH MỤC
========================================================= */

  .category-box {
    background: white;

    border-radius: 14px;

    padding: 22px;

    height: fit-content;

    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  }

  .category-title {
    font-size: 20px;
    font-weight: 700;

    margin-bottom: 18px;

    color: #1f2937;
  }

  .category-list {
    list-style: none;

    padding: 0;
    margin: 0;
  }

  .category-list li {
    margin-bottom: 8px;
  }

  .category-list a {
    display: block;

    padding: 11px 13px;

    border-radius: 8px;

    color: #374151;

    text-decoration: none;

    transition: 0.2s;
  }

  .category-list a:hover {
    background: #eff6ff;
    color: #2563eb;
  }


  /* =========================================================
   SẢN PHẨM
========================================================= */

  .products-section {
    min-width: 0;
  }

  .section-header {
    display: flex;

    justify-content: space-between;
    align-items: center;

    margin-bottom: 20px;
  }

  .section-header h2 {
    margin: 0;

    font-size: 26px;
    font-weight: 700;

    color: #1f2937;
  }

  .view-all {
    color: #2563eb;

    text-decoration: none;

    font-weight: 600;
  }

  .view-all:hover {
    text-decoration: underline;
  }


  /* =========================================================
   PRODUCT GRID
========================================================= */

  .product-grid {
    display: grid;

    grid-template-columns: repeat(4, minmax(0, 1fr));

    gap: 20px;
  }


  /* =========================================================
   PRODUCT CARD
========================================================= */

  .product-card {
    background: white;

    border-radius: 14px;

    overflow: hidden;

    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);

    transition: 0.25s;

    display: flex;
    flex-direction: column;
  }

  .product-card:hover {
    transform: translateY(-4px);

    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.10);
  }


  /* =========================================================
   HÌNH SẢN PHẨM
========================================================= */

  .product-image {
    width: 100%;
    height: 210px;

    background: #f8fafc;

    display: flex;

    justify-content: center;
    align-items: center;

    overflow: hidden;
  }

  .product-image img {
    width: 100%;
    height: 100%;

    object-fit: contain;

    padding: 15px;

    transition: 0.25s;
  }

  .product-card:hover .product-image img {
    transform: scale(1.05);
  }

  .no-image {
    color: #9ca3af;
    font-size: 14px;
  }


  /* =========================================================
   THÔNG TIN SẢN PHẨM
========================================================= */

  .product-info {
    padding: 17px;

    display: flex;
    flex-direction: column;

    flex: 1;
  }

  .product-category {
    font-size: 13px;

    color: #6b7280;

    margin-bottom: 7px;
  }

  .product-name {
    font-size: 17px;

    font-weight: 600;

    color: #1f2937;

    margin: 0 0 10px;

    display: -webkit-box;

    -webkit-line-clamp: 2;

    -webkit-box-orient: vertical;

    overflow: hidden;

    min-height: 48px;
  }

  .product-price {
    font-size: 19px;

    font-weight: 700;

    color: #dc2626;

    margin-bottom: 8px;
  }

  .product-stock {
    font-size: 13px;

    color: #6b7280;

    margin-bottom: 15px;
  }

  .btn-detail {
    display: block;

    width: 100%;

    text-align: center;

    background: #2563eb;

    color: white;

    padding: 10px;

    border-radius: 8px;

    text-decoration: none;

    font-weight: 600;

    margin-top: auto;

    transition: 0.2s;
  }

  .btn-detail:hover {
    background: #1d4ed8;

    color: white;
  }


  /* =========================================================
   KHÔNG CÓ SẢN PHẨM
========================================================= */

  .empty-products {
    background: white;

    border-radius: 14px;

    padding: 50px;

    text-align: center;

    color: #6b7280;
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

    .home-page {
      padding: 25px;
    }

    .home-content {
      grid-template-columns: 210px 1fr;
    }

    .product-grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .hero {
      padding: 35px;
    }

    .hero-icon {
      display: none;
    }

  }

  @media (max-width: 768px) {

    .home-page {
      padding: 20px 15px;
    }

    .home-content {
      grid-template-columns: 1fr;
    }

    .category-box {
      width: 100%;
    }

    .category-list {
      display: grid;

      grid-template-columns: repeat(2, 1fr);

      gap: 5px;
    }

    .category-list li {
      margin: 0;
    }

    .hero {
      min-height: 230px;

      padding: 30px;
    }

    .hero-content h1 {
      font-size: 30px;
    }

    .hero-content p {
      font-size: 16px;
    }

  }

  @media (max-width: 480px) {

    .product-grid {
      grid-template-columns: 1fr;
    }

    .category-list {
      grid-template-columns: 1fr;
    }

    .hero-content h1 {
      font-size: 26px;
    }

  }
</style>


<main class="home-page">


  <!-- =====================================================
         BANNER
    ====================================================== -->

  <section class="hero">

    <div class="hero-content">

      <h1>
        Chào mừng đến với Sales Store
      </h1>

      <p>
        Khám phá các sản phẩm chất lượng với nhiều lựa chọn
        phù hợp với nhu cầu của bạn.
      </p>

      <a href="/products.php" class="hero-button">
        Xem tất cả sản phẩm
      </a>

    </div>

    <div class="hero-icon">
      🛍
    </div>

  </section>


  <!-- =====================================================
         DANH MỤC + SẢN PHẨM
    ====================================================== -->

  <div class="home-content">


    <!-- =================================================
             DANH MỤC
        ================================================== -->

    <aside class="category-box">

      <div class="category-title">
        Danh mục sản phẩm
      </div>

      <ul class="category-list">

        <?php if (!empty($categories)): ?>

          <?php foreach ($categories as $category): ?>

            <li>

              <a
                href="/products.php?category=<?= urlencode($category['CategoryID']) ?>">

                <?= htmlspecialchars($category['CategoryName']) ?>

              </a>

            </li>

          <?php endforeach; ?>

        <?php else: ?>

          <li>
            <span style="color:#9ca3af;">
              Chưa có danh mục
            </span>
          </li>

        <?php endif; ?>

      </ul>

    </aside>


    <!-- =================================================
             DANH SÁCH SẢN PHẨM
        ================================================== -->

    <section class="products-section">

      <div class="section-header">

        <h2>
          Sản phẩm nổi bật
        </h2>

        <a
          href="/products.php"
          class="view-all">
          Xem tất cả →
        </a>

      </div>


      <?php if (!empty($products)): ?>

        <div class="product-grid">

          <?php foreach ($products as $product): ?>

            <article class="product-card">


              <!-- HÌNH -->

              <div class="product-image">

                <?php if (!empty($product['ImageFile'])): ?>

                  <?php

                  $imageFile = $product['ImageFile'];

                  /*
                                     * Nếu database lưu:
                                     * /uploads/products/iphone.jpg
                                     *
                                     * thì dùng trực tiếp.
                                     *
                                     * Nếu database chỉ lưu:
                                     * iphone.jpg
                                     *
                                     * thì thêm:
                                     * /uploads/products/
                                     */

                  if (
                    strpos(
                      $imageFile,
                      '/uploads/products/'
                    ) !== 0
                  ) {

                    $imageFile =
                      '/uploads/products/' .
                      ltrim($imageFile, '/');
                  }

                  ?>

                  <img
                    src="<?= htmlspecialchars($imageFile) ?>"
                    alt="<?= htmlspecialchars($product['ProductName']) ?>"
                    loading="lazy">

                <?php else: ?>

                  <div class="no-image">
                    Chưa có hình ảnh
                  </div>

                <?php endif; ?>

              </div>


              <!-- THÔNG TIN -->

              <div class="product-info">


                <?php if (!empty($product['CategoryName'])): ?>

                  <div class="product-category">

                    <?= htmlspecialchars(
                      $product['CategoryName']
                    ) ?>

                  </div>

                <?php endif; ?>


                <h3 class="product-name">

                  <?= htmlspecialchars(
                    $product['ProductName']
                  ) ?>

                </h3>


                <div class="product-price">

                  <?= number_format(
                    (float)$product['Price'],
                    0,
                    ',',
                    '.'
                  ) ?>

                  đ

                </div>


                <div class="product-stock">

                  <?php if (
                    (int)$product['StockQuantity'] > 0
                  ): ?>

                    Còn
                    <?= (int)$product['StockQuantity'] ?>
                    sản phẩm

                  <?php else: ?>

                    Hết hàng

                  <?php endif; ?>

                </div>


                <a
                  href="/product-detail.php?id=<?= (int)$product['ProductID'] ?>"
                  class="btn-detail">
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