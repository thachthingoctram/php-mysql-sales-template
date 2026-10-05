<?php

require_once '/var/www/src/config/session.php';
require_once '/var/www/src/config/database.php';

/*
|--------------------------------------------------------------------------
| Lấy danh sách danh mục
|--------------------------------------------------------------------------
*/

$sqlCategories = "
    SELECT
        CategoryID,
        CategoryName
    FROM categories
    ORDER BY CategoryName
";

$categoryResult = $conn->query($sqlCategories);

$categories = [];

if ($categoryResult) {

    while ($category = $categoryResult->fetch_assoc()) {
        $categories[] = $category;
    }

    $categoryResult->free();
}

/*
|--------------------------------------------------------------------------
| Nhận tham số tìm kiếm
|--------------------------------------------------------------------------
*/

$categoryID = isset($_GET['category'])
    ? (int) $_GET['category']
    : 0;

$keyword = isset($_GET['keyword'])
    ? trim($_GET['keyword'])
    : '';

$page = isset($_GET['page'])
    ? (int) $_GET['page']
    : 1;

if ($page < 1) {
    $page = 1;
}

$itemsPerPage = 6;

$searchKeyword = '%' . $keyword . '%';

/*
|--------------------------------------------------------------------------
| Đếm tổng số sản phẩm
|--------------------------------------------------------------------------
*/

$sqlCount = "
    SELECT
        COUNT(*) AS TotalProducts
    FROM products p
    INNER JOIN categories c
        ON p.CategoryID = c.CategoryID
    WHERE p.IsActive = 1
";

$countTypes = '';
$countParams = [];

if ($categoryID > 0) {

    $sqlCount .= "
        AND p.CategoryID = ?
    ";

    $countTypes .= 'i';
    $countParams[] = $categoryID;
}

if ($keyword !== '') {

    $sqlCount .= "
        AND (
            p.ProductName LIKE ?
            OR p.ProductCode LIKE ?
        )
    ";

    $countTypes .= 'ss';
    $countParams[] = $searchKeyword;
    $countParams[] = $searchKeyword;
}

$stmtCount = $conn->prepare($sqlCount);

if (!$stmtCount) {
    die('Lỗi chuẩn bị truy vấn đếm sản phẩm: ' . $conn->error);
}

if (!empty($countParams)) {
    $stmtCount->bind_param(
        $countTypes,
        ...$countParams
    );
}

$stmtCount->execute();

$countResult = $stmtCount->get_result();

$countRow = $countResult->fetch_assoc();

$totalProducts = (int) ($countRow['TotalProducts'] ?? 0);

$countResult->free();
$stmtCount->close();

/*
|--------------------------------------------------------------------------
| Tính phân trang
|--------------------------------------------------------------------------
*/

$totalPages = (int) ceil(
    $totalProducts / $itemsPerPage
);

if ($totalPages > 0 && $page > $totalPages) {
    $page = $totalPages;
}

$offset = ($page - 1) * $itemsPerPage;

/*
|--------------------------------------------------------------------------
| Lấy danh sách sản phẩm
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        p.ProductID,
        p.ProductCode,
        p.ProductName,
        p.Price,
        p.StockQuantity,
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

    INNER JOIN categories c
        ON p.CategoryID = c.CategoryID

    WHERE p.IsActive = 1
";

$types = '';
$params = [];

if ($categoryID > 0) {

    $sql .= "
        AND p.CategoryID = ?
    ";

    $types .= 'i';
    $params[] = $categoryID;
}

if ($keyword !== '') {

    $sql .= "
        AND (
            p.ProductName LIKE ?
            OR p.ProductCode LIKE ?
        )
    ";

    $types .= 'ss';
    $params[] = $searchKeyword;
    $params[] = $searchKeyword;
}

$sql .= "
    ORDER BY p.ProductID DESC
    LIMIT ? OFFSET ?
";

$types .= 'ii';
$params[] = $itemsPerPage;
$params[] = $offset;

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die('Lỗi chuẩn bị truy vấn sản phẩm: ' . $conn->error);
}

$stmt->bind_param(
    $types,
    ...$params
);

$stmt->execute();

$result = $stmt->get_result();

/*
|--------------------------------------------------------------------------
| Tiêu đề trang
|--------------------------------------------------------------------------
*/

$pageTitle = 'Sản phẩm';

require_once '/var/www/src/includes/frontend/header.php';
require_once '/var/www/src/includes/frontend/navbar.php';

?>

<main class="container py-5">

    <!-- Tiêu đề -->

    <div class="mb-4">

        <h1>Sản phẩm</h1>

        <p class="text-muted">
            Khám phá các sản phẩm hiện có tại cửa hàng.
        </p>

    </div>

    <!-- Tìm kiếm và lọc -->

    <form
        method="get"
        action="/products.php"
        class="row g-3 mb-4"
    >

        <div class="col-md-6 col-lg-4">

            <label
                for="keyword"
                class="form-label"
            >
                Tìm sản phẩm
            </label>

            <input
                type="text"
                name="keyword"
                id="keyword"
                class="form-control"
                value="<?= htmlspecialchars($keyword) ?>"
                placeholder="Nhập tên hoặc mã sản phẩm"
            >

        </div>

        <div class="col-md-6 col-lg-4">

            <label
                for="category"
                class="form-label"
            >
                Danh mục
            </label>

            <select
                name="category"
                id="category"
                class="form-select"
            >

                <option value="0">
                    Tất cả danh mục
                </option>

                <?php foreach ($categories as $category): ?>

                    <option
                        value="<?= (int) $category['CategoryID'] ?>"
                        <?=
                            $categoryID ===
                            (int) $category['CategoryID']
                                ? 'selected'
                                : ''
                        ?>
                    >
                        <?=
                            htmlspecialchars(
                                $category['CategoryName']
                            )
                        ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </div>

        <div class="col-md-auto align-self-end">

            <button
                type="submit"
                class="btn btn-primary"
            >
                Tìm kiếm
            </button>

            <a
                href="/products.php"
                class="btn btn-outline-secondary"
            >
                Xóa bộ lọc
            </a>

        </div>

    </form>

    <!-- Tổng số kết quả -->

    <p class="text-muted">

        Tìm thấy

        <strong>
            <?= $totalProducts ?>
        </strong>

        sản phẩm.

    </p>

    <!-- Danh sách sản phẩm -->

    <?php if ($result->num_rows > 0): ?>

        <div class="row g-4">

            <?php while ($product = $result->fetch_assoc()): ?>

                <div class="col-md-6 col-lg-4">

                    <div class="card h-100">

                        <!-- Hình ảnh -->

                        <?php if (!empty($product['ImageFile'])): ?>

                            <div
                                class="bg-light d-flex
                                       align-items-center
                                       justify-content-center
                                       p-3"
                                style="height: 260px;"
                            >

                                <img
                                    src="/uploads/products/<?=
                                        htmlspecialchars(
                                            $product['ImageFile']
                                        )
                                    ?>"
                                    alt="<?=
                                        htmlspecialchars(
                                            $product['ProductName']
                                        )
                                    ?>"
                                    style="
                                        width: 100%;
                                        height: 100%;
                                        object-fit: contain;
                                    "
                                >

                            </div>

                        <?php else: ?>

                            <div
                                class="bg-light d-flex
                                       align-items-center
                                       justify-content-center
                                       text-muted"
                                style="height: 260px;"
                            >
                                Chưa có hình ảnh
                            </div>

                        <?php endif; ?>

                        <!-- Thông tin -->

                        <div class="card-body d-flex flex-column">

                            <p class="text-muted small mb-1">

                                <?=
                                    htmlspecialchars(
                                        $product['CategoryName']
                                    )
                                ?>

                            </p>

                            <h5 class="card-title">

                                <?=
                                    htmlspecialchars(
                                        $product['ProductName']
                                    )
                                ?>

                            </h5>

                            <p class="text-muted small">

                                Mã sản phẩm:

                                <?=
                                    htmlspecialchars(
                                        $product['ProductCode']
                                    )
                                ?>

                            </p>

                            <p class="fw-bold fs-5 mb-2">

                                <?=
                                    number_format(
                                        (float) $product['Price'],
                                        0,
                                        ',',
                                        '.'
                                    )
                                ?> đ

                            </p>

                            <p class="small mb-3">

                                <?php if ((int) $product['StockQuantity'] > 0): ?>

                                    <span class="text-success">
                                        Còn hàng:
                                        <?= (int) $product['StockQuantity'] ?>
                                    </span>

                                <?php else: ?>

                                    <span class="text-danger">
                                        Hết hàng
                                    </span>

                                <?php endif; ?>

                            </p>

                            <a
                                href="/product-detail.php?id=<?=
                                    (int) $product['ProductID']
                                ?>"
                                class="btn btn-outline-primary mt-auto"
                            >
                                Xem chi tiết
                            </a>

                        </div>

                    </div>

                </div>

            <?php endwhile; ?>

        </div>

    <?php else: ?>

        <div class="alert alert-info">

            Không tìm thấy sản phẩm phù hợp.

        </div>

    <?php endif; ?>

    <!-- PHÂN TRANG -->

    <?php if ($totalPages > 1): ?>

        <nav
            class="mt-5"
            aria-label="Phân trang sản phẩm"
        >

            <ul
                class="pagination
                       justify-content-center
                       flex-wrap"
            >

                <!-- Trang trước -->

                <?php

                $previousQuery = http_build_query([
                    'keyword' => $keyword,
                    'category' => $categoryID,
                    'page' => max(1, $page - 1)
                ]);

                ?>

                <li
                    class="page-item <?=
                        $page <= 1
                            ? 'disabled'
                            : ''
                    ?>"
                >

                    <a
                        class="page-link"
                        href="/products.php?<?= $previousQuery ?>"
                    >
                        &laquo; Trước
                    </a>

                </li>

                <!-- Các trang -->

                <?php for (
                    $pageNumber = 1;
                    $pageNumber <= $totalPages;
                    $pageNumber++
                ): ?>

                    <?php

                    $query = http_build_query([
                        'keyword' => $keyword,
                        'category' => $categoryID,
                        'page' => $pageNumber
                    ]);

                    ?>

                    <li
                        class="page-item <?=
                            $pageNumber === $page
                                ? 'active'
                                : ''
                        ?>"
                    >

                        <a
                            class="page-link"
                            href="/products.php?<?= $query ?>"
                        >
                            <?= $pageNumber ?>
                        </a>

                    </li>

                <?php endfor; ?>

                <!-- Trang sau -->

                <?php

                $nextQuery = http_build_query([
                    'keyword' => $keyword,
                    'category' => $categoryID,
                    'page' => min(
                        $totalPages,
                        $page + 1
                    )
                ]);

                ?>

                <li
                    class="page-item <?=
                        $page >= $totalPages
                            ? 'disabled'
                            : ''
                    ?>"
                >

                    <a
                        class="page-link"
                        href="/products.php?<?= $nextQuery ?>"
                    >
                        Sau &raquo;
                    </a>

                </li>

            </ul>

        </nav>

    <?php endif; ?>

</main>

<?php

$result->free();
$stmt->close();

require_once '/var/www/src/includes/frontend/footer.php';