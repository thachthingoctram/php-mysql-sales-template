<?php

require_once '/var/www/src/config/session.php';
require_once '/var/www/src/config/database.php';

$pageTitle = 'Sản phẩm';

/*
|--------------------------------------------------------------------------
| Nhận tham số tìm kiếm và lọc
|--------------------------------------------------------------------------
*/

$search = trim($_GET['search'] ?? '');

$categoryID = isset($_GET['category'])
    ? (int) $_GET['category']
    : 0;

$page = isset($_GET['page'])
    ? (int) $_GET['page']
    : 1;

if ($page < 1) {
    $page = 1;
}

/*
|--------------------------------------------------------------------------
| Số sản phẩm trên một trang
|--------------------------------------------------------------------------
*/

$perPage = 8;

/*
|--------------------------------------------------------------------------
| Lấy danh sách danh mục
|--------------------------------------------------------------------------
*/

$categories = [];

$sqlCategories = "
    SELECT
        CategoryID,
        CategoryName
    FROM categories
    ORDER BY CategoryName ASC
";

$resultCategories = $conn->query($sqlCategories);

if ($resultCategories) {

    while ($row = $resultCategories->fetch_assoc()) {
        $categories[] = $row;
    }

    $resultCategories->free();
}

/*
|--------------------------------------------------------------------------
| Xây dựng điều kiện tìm kiếm
|--------------------------------------------------------------------------
*/

$where = [
    "p.IsActive = 1"
];

$params = [];
$types = '';

/*
|--------------------------------------------------------------------------
| Tìm kiếm theo tên hoặc mã sản phẩm
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $where[] = "
        (
            p.ProductName LIKE ?
            OR p.ProductCode LIKE ?
        )
    ";

    $searchValue = '%' . $search . '%';

    $params[] = $searchValue;
    $params[] = $searchValue;

    $types .= 'ss';
}

/*
|--------------------------------------------------------------------------
| Lọc theo danh mục
|--------------------------------------------------------------------------
*/

if ($categoryID > 0) {

    $where[] = "p.CategoryID = ?";

    $params[] = $categoryID;

    $types .= 'i';
}

$whereSQL = implode(' AND ', $where);

/*
|--------------------------------------------------------------------------
| Đếm tổng số sản phẩm
|--------------------------------------------------------------------------
*/

$sqlCount = "
    SELECT COUNT(*) AS TotalProducts
    FROM products p
    WHERE {$whereSQL}
";

$stmtCount = $conn->prepare($sqlCount);

if (!empty($params)) {
    $stmtCount->bind_param($types, ...$params);
}

$stmtCount->execute();

$resultCount = $stmtCount->get_result();

$countRow = $resultCount->fetch_assoc();

$resultCount->free();
$stmtCount->close();

$totalProducts = (int) ($countRow['TotalProducts'] ?? 0);

/*
|--------------------------------------------------------------------------
| Tính phân trang
|--------------------------------------------------------------------------
*/

$totalPages = (int) ceil($totalProducts / $perPage);

if ($totalPages > 0 && $page > $totalPages) {
    $page = $totalPages;
}

$offset = ($page - 1) * $perPage;

/*
|--------------------------------------------------------------------------
| Lấy danh sách sản phẩm
|--------------------------------------------------------------------------
*/

$products = [];

$sqlProducts = "
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
              AND pi.IsPrimary = 1
            ORDER BY pi.SortOrder ASC
            LIMIT 1
        ) AS ImageFile

    FROM products p

    LEFT JOIN categories c
        ON c.CategoryID = p.CategoryID

    WHERE {$whereSQL}

    ORDER BY p.ProductID DESC

    LIMIT ? OFFSET ?
";

$stmtProducts = $conn->prepare($sqlProducts);

/*
|--------------------------------------------------------------------------
| Bind tham số
|--------------------------------------------------------------------------
|
| Nếu có tìm kiếm/lọc:
|   $types = ss hoặc ssi...
|
| Cuối cùng thêm:
|   LIMIT = integer
|   OFFSET = integer
|--------------------------------------------------------------------------
*/

$productTypes = $types . 'ii';

$productParams = $params;

$productParams[] = $perPage;
$productParams[] = $offset;

$stmtProducts->bind_param(
    $productTypes,
    ...$productParams
);

$stmtProducts->execute();

$resultProducts = $stmtProducts->get_result();

while ($row = $resultProducts->fetch_assoc()) {
    $products[] = $row;
}

$resultProducts->free();
$stmtProducts->close();

/*
|--------------------------------------------------------------------------
| Hàm tạo URL phân trang
|--------------------------------------------------------------------------
*/

function buildProductUrl($page)
{
    $query = [];

    if (isset($_GET['search']) && trim($_GET['search']) !== '') {
        $query['search'] = trim($_GET['search']);
    }

    if (
        isset($_GET['category'])
        && (int) $_GET['category'] > 0
    ) {
        $query['category'] = (int) $_GET['category'];
    }

    $query['page'] = $page;

    return '/products.php?' . http_build_query($query);
}

/*
|--------------------------------------------------------------------------
| Frontend
|--------------------------------------------------------------------------
*/

require_once '/var/www/src/includes/frontend/header.php';
require_once '/var/www/src/includes/frontend/navbar.php';

?>

<main class="container py-5">

    <!-- Tiêu đề -->

    <div class="mb-4">

        <h1 class="mb-2">
            Sản phẩm
        </h1>

        <p class="text-muted mb-0">
            Danh sách sản phẩm đang được kinh doanh.
        </p>

    </div>


    <!-- Tìm kiếm và lọc -->

    <div class="card shadow-sm mb-4">

        <div class="card-body">

            <form
                method="get"
                action="/products.php"
            >

                <div class="row g-3 align-items-end">

                    <!-- Tìm kiếm -->

                    <div class="col-md-6">

                        <label
                            for="search"
                            class="form-label"
                        >
                            Tìm kiếm
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            id="search"
                            name="search"
                            value="<?= htmlspecialchars($search) ?>"
                            placeholder="Tên hoặc mã sản phẩm"
                        >

                    </div>


                    <!-- Danh mục -->

                    <div class="col-md-4">

                        <label
                            for="category"
                            class="form-label"
                        >
                            Danh mục
                        </label>

                        <select
                            class="form-select"
                            id="category"
                            name="category"
                        >

                            <option value="0">
                                Tất cả danh mục
                            </option>

                            <?php foreach ($categories as $category): ?>

                                <option
                                    value="<?= (int) $category['CategoryID'] ?>"
                                    <?= (
                                        $categoryID
                                        === (int) $category['CategoryID']
                                    )
                                        ? 'selected'
                                        : ''
                                    ?>
                                >

                                    <?= htmlspecialchars(
                                        $category['CategoryName']
                                    ) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- Nút tìm kiếm -->

                    <div class="col-md-2">

                        <button
                            type="submit"
                            class="btn btn-primary w-100"
                        >
                            Tìm kiếm
                        </button>

                    </div>

                </div>

            </form>

        </div>

    </div>


    <!-- Thông tin kết quả -->

    <div
        class="d-flex
               justify-content-between
               align-items-center
               mb-3"
    >

        <div class="text-muted">

            <?php if ($totalProducts > 0): ?>

                Tìm thấy
                <strong>
                    <?= $totalProducts ?>
                </strong>
                sản phẩm.

            <?php else: ?>

                Không tìm thấy sản phẩm.

            <?php endif; ?>

        </div>

    </div>


    <!-- Danh sách sản phẩm -->

    <?php if (empty($products)): ?>

        <div class="alert alert-info">

            Không có sản phẩm phù hợp với điều kiện tìm kiếm.

        </div>

    <?php else: ?>

        <div class="row g-4">

            <?php foreach ($products as $product): ?>

                <div class="col-12 col-sm-6 col-lg-3">

                    <div class="card h-100 shadow-sm">

                        <!-- Hình ảnh -->

                        <div
                            class="p-3
                                   d-flex
                                   align-items-center
                                   justify-content-center"
                            style="
                                height: 220px;
                                background: #f8f9fa;
                            "
                        >

                            <?php if (!empty($product['ImageFile'])): ?>

                                <img
                                    src="/uploads/products/<?= htmlspecialchars(
                                        $product['ImageFile']
                                    ) ?>"
                                    alt="<?= htmlspecialchars(
                                        $product['ProductName']
                                    ) ?>"
                                    class="img-fluid"
                                    style="
                                        max-height: 190px;
                                        object-fit: contain;
                                    "
                                >

                            <?php else: ?>

                                <div class="text-muted">
                                    Chưa có hình ảnh
                                </div>

                            <?php endif; ?>

                        </div>


                        <!-- Thông tin -->

                        <div class="card-body d-flex flex-column">

                            <!-- Mã sản phẩm -->

                            <div class="small text-muted mb-1">

                                <?= htmlspecialchars(
                                    $product['ProductCode']
                                ) ?>

                            </div>


                            <!-- Tên -->

                            <h2 class="h5">

                                <?= htmlspecialchars(
                                    $product['ProductName']
                                ) ?>

                            </h2>


                            <!-- Danh mục -->

                            <?php if (!empty($product['CategoryName'])): ?>

                                <div class="mb-2">

                                    <span class="badge bg-secondary">

                                        <?= htmlspecialchars(
                                            $product['CategoryName']
                                        ) ?>

                                    </span>

                                </div>

                            <?php endif; ?>


                            <!-- Mô tả -->

                            <?php if (!empty($product['Description'])): ?>

                                <p class="text-muted small">

                                    <?= htmlspecialchars(
                                        mb_strimwidth(
                                            $product['Description'],
                                            0,
                                            100,
                                            '...'
                                        )
                                    ) ?>

                                </p>

                            <?php endif; ?>


                            <!-- Giá -->

                            <div class="mt-auto">

                                <div class="fs-5 fw-bold text-primary">

                                    <?= number_format(
                                        (float) $product['Price'],
                                        0,
                                        ',',
                                        '.'
                                    ) ?>

                                    đ

                                </div>


                                <!-- Đơn vị -->

                                <?php if (!empty($product['Unit'])): ?>

                                    <div class="small text-muted">

                                        Đơn vị:
                                        <?= htmlspecialchars(
                                            $product['Unit']
                                        ) ?>

                                    </div>

                                <?php endif; ?>


                                <!-- Tồn kho -->

                                <div class="small mt-1">

                                    <?php if (
                                        (int) $product['StockQuantity'] > 0
                                    ): ?>

                                        <span class="text-success">

                                            Còn
                                            <?= (int) $product['StockQuantity'] ?>
                                            sản phẩm

                                        </span>

                                    <?php else: ?>

                                        <span class="text-danger">

                                            Hết hàng

                                        </span>

                                    <?php endif; ?>

                                </div>


                                <!-- Xem chi tiết -->

                                <a
                                    href="/product-detail.php?id=<?= (int) $product['ProductID'] ?>"
                                    class="btn btn-primary w-100 mt-3"
                                >
                                    Xem chi tiết
                                </a>

                            </div>

                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>


        <!-- Phân trang -->

        <?php if ($totalPages > 1): ?>

            <nav
                aria-label="Phân trang sản phẩm"
                class="mt-5"
            >

                <ul
                    class="pagination
                           justify-content-center"
                >

                    <!-- Trang trước -->

                    <li
                        class="page-item
                            <?= $page <= 1
                                ? 'disabled'
                                : '' ?>"
                    >

                        <?php if ($page > 1): ?>

                            <a
                                class="page-link"
                                href="<?= htmlspecialchars(
                                    buildProductUrl($page - 1)
                                ) ?>"
                            >
                                Trước
                            </a>

                        <?php else: ?>

                            <span class="page-link">
                                Trước
                            </span>

                        <?php endif; ?>

                    </li>


                    <!-- Các trang -->

                    <?php for (
                        $i = 1;
                        $i <= $totalPages;
                        $i++
                    ): ?>

                        <li
                            class="page-item
                                <?= $i === $page
                                    ? 'active'
                                    : '' ?>"
                        >

                            <a
                                class="page-link"
                                href="<?= htmlspecialchars(
                                    buildProductUrl($i)
                                ) ?>"
                            >

                                <?= $i ?>

                            </a>

                        </li>

                    <?php endfor; ?>


                    <!-- Trang sau -->

                    <li
                        class="page-item
                            <?= $page >= $totalPages
                                ? 'disabled'
                                : '' ?>"
                    >

                        <?php if ($page < $totalPages): ?>

                            <a
                                class="page-link"
                                href="<?= htmlspecialchars(
                                    buildProductUrl($page + 1)
                                ) ?>"
                            >
                                Sau
                            </a>

                        <?php else: ?>

                            <span class="page-link">
                                Sau
                            </span>

                        <?php endif; ?>

                    </li>

                </ul>

            </nav>

        <?php endif; ?>

    <?php endif; ?>

</main>

<?php

require_once '/var/www/src/includes/frontend/footer.php';

?>