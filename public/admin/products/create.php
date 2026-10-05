<?php

$pageTitle = 'Thêm sản phẩm';

require_once '/var/www/src/config/database.php';

$error = '';
$success = '';

/**
 * Lấy danh sách danh mục
 */
$sqlCategories = "
    SELECT CategoryID, CategoryName
    FROM categories
    ORDER BY CategoryName
";

$categories = $conn->query($sqlCategories);

/**
 * Lấy danh sách nhà cung cấp
 */
$sqlSuppliers = "
    SELECT SupplierID, SupplierName
    FROM suppliers
    ORDER BY SupplierName
";

$suppliers = $conn->query($sqlSuppliers);

/**
 * Xử lý khi submit form
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  $productCode = trim($_POST['product_code'] ?? '');
  $productName = trim($_POST['product_name'] ?? '');
  $description = trim($_POST['description'] ?? '');
  $unit = trim($_POST['unit'] ?? '');

  $price = (float) ($_POST['price'] ?? 0);
  $stockQuantity = (int) ($_POST['stock_quantity'] ?? 0);

  $categoryID = (int) ($_POST['category_id'] ?? 0);
  $supplierID = (int) ($_POST['supplier_id'] ?? 0);

  $isActive = isset($_POST['is_active']) ? 1 : 0;

  /**
   * Nhận nhiều ảnh
   */
  $files = $_FILES['product_images'] ?? null;

  /**
   * Kiểm tra dữ liệu
   */
  if ($productCode === '') {

    $error = 'Mã sản phẩm không được để trống.';
  } elseif ($productName === '') {

    $error = 'Tên sản phẩm không được để trống.';
  } elseif ($price < 0) {

    $error = 'Giá sản phẩm không hợp lệ.';
  } elseif ($stockQuantity < 0) {

    $error = 'Số lượng tồn kho không hợp lệ.';
  } elseif ($categoryID <= 0) {

    $error = 'Vui lòng chọn danh mục.';
  } elseif ($supplierID <= 0) {

    $error = 'Vui lòng chọn nhà cung cấp.';
  } elseif (
    !$files
    || !isset($files['name'])
    || !is_array($files['name'])
  ) {

    $error = 'Vui lòng chọn ảnh sản phẩm.';
  } else {

    $fileCount = count($files['name']);

    /**
     * Chỉ cho phép từ 1 đến 4 ảnh
     */
    if ($fileCount < 1 || $fileCount > 4) {

      $error = 'Vui lòng chọn từ 1 đến 4 ảnh.';
    } else {

      $finfo = new finfo(FILEINFO_MIME_TYPE);

      $extensionMap = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp'
      ];

      $validFiles = [];
      $hasError = false;

      /**
       * Kiểm tra từng ảnh
       */
      for ($i = 0; $i < $fileCount; $i++) {

        $fileName = $files['name'][$i] ?? '';
        $tmpName = $files['tmp_name'][$i] ?? '';
        $fileError = $files['error'][$i] ?? UPLOAD_ERR_NO_FILE;
        $fileSize = $files['size'][$i] ?? 0;

        /**
         * Kiểm tra lỗi upload
         */
        if ($fileError !== UPLOAD_ERR_OK) {

          $error =
            'Ảnh thứ '
            . ($i + 1)
            . ' upload không thành công.';

          $hasError = true;
          break;
        }

        /**
         * Kiểm tra dung lượng
         */
        if ($fileSize > 2 * 1024 * 1024) {

          $error =
            'Ảnh thứ '
            . ($i + 1)
            . ' vượt quá 2 MB.';

          $hasError = true;
          break;
        }

        /**
         * Kiểm tra MIME thật
         */
        $mimeType = $finfo->file($tmpName);

        if (!isset($extensionMap[$mimeType])) {

          $error =
            'Ảnh thứ '
            . ($i + 1)
            . ' không phải JPG, PNG hoặc WebP.';

          $hasError = true;
          break;
        }

        /**
         * Lưu thông tin file hợp lệ
         */
        $validFiles[] = [
          'tmp_name' => $tmpName,
          'extension' => $extensionMap[$mimeType]
        ];
      }

      /**
       * Nếu tất cả ảnh hợp lệ thì bắt đầu lưu
       */
      if (!$hasError) {

        $savedFiles = [];

        try {

          /**
           * Bắt đầu transaction
           */
          $conn->begin_transaction();

          /**
           * 1. Thêm sản phẩm
           */
          $sql = "
                        INSERT INTO products
                        (
                            ProductCode,
                            ProductName,
                            Description,
                            Unit,
                            Price,
                            StockQuantity,
                            IsActive,
                            SupplierID,
                            CategoryID
                        )
                        VALUES
                        (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ";

          $stmt = $conn->prepare($sql);

          $stmt->bind_param(
            'ssssdiiii',
            $productCode,
            $productName,
            $description,
            $unit,
            $price,
            $stockQuantity,
            $isActive,
            $supplierID,
            $categoryID
          );

          $stmt->execute();

          /**
           * 2. Lấy ProductID
           */
          $productID = $conn->insert_id;

          $stmt->close();

          /**
           * 3. Chuẩn bị INSERT ảnh
           */
          $sqlImage = "
                        INSERT INTO product_images
                        (
                            ProductID,
                            ImageFile,
                            AltText,
                            IsPrimary,
                            SortOrder
                        )
                        VALUES
                        (?, ?, ?, ?, ?)
                    ";

          $stmtImage = $conn->prepare($sqlImage);

          /**
           * 4. Lưu từng ảnh
           */
          foreach ($validFiles as $index => $validFile) {

            $extension = $validFile['extension'];

            /**
             * Tạo tên file mới
             */
            $newFileName =
              'product-'
              . bin2hex(random_bytes(8))
              . '.'
              . $extension;

            /**
             * Đường dẫn lưu file
             */
            $destination =
              '/var/www/html/uploads/products/'
              . $newFileName;

            /**
             * Di chuyển file
             */
            if (!move_uploaded_file(
              $validFile['tmp_name'],
              $destination
            )) {

              throw new Exception(
                'Không thể lưu ảnh thứ '
                  . ($index + 1)
              );
            }

            /**
             * Ghi nhận file đã lưu
             */
            $savedFiles[] = $destination;

            /**
             * Ảnh đầu tiên là ảnh chính
             */
            $isPrimary = ($index === 0) ? 1 : 0;

            /**
             * Thứ tự ảnh
             */
            $sortOrder = $index + 1;

            /**
             * Alt text
             */
            $altText =
              $productName
              . ' - ảnh '
              . ($index + 1);

            /**
             * INSERT product_images
             */
            $stmtImage->bind_param(
              'issii',
              $productID,
              $newFileName,
              $altText,
              $isPrimary,
              $sortOrder
            );

            $stmtImage->execute();
          }

          $stmtImage->close();

          /**
           * 5. Hoàn tất transaction
           */
          $conn->commit();

          /**
           * Chuyển về danh sách sản phẩm
           */
          header('Location: /admin/products/');
          exit;
        } catch (Throwable $e) {

          /**
           * Rollback database
           */
          $conn->rollback();

          /**
           * Xóa các file đã lưu
           */
          foreach ($savedFiles as $savedFile) {

            if (file_exists($savedFile)) {
              unlink($savedFile);
            }
          }

          /**
           * Xử lý lỗi trùng mã sản phẩm
           */
          if (
            $e instanceof mysqli_sql_exception
            && $e->getCode() === 1062
          ) {

            $error =
              'Mã sản phẩm đã tồn tại. '
              . 'Vui lòng nhập mã khác.';
          } else {

            $error =
              'Không thể thêm sản phẩm: '
              . $e->getMessage();
          }
        }
      }
    }
  }
}

/**
 * Giao diện
 */
require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';
?>

<div class="container mt-4">

  <h2 class="mb-4">
    Thêm sản phẩm
  </h2>

  <?php if ($error !== ''): ?>

    <div class="alert alert-danger">
      <?= htmlspecialchars($error) ?>
    </div>

  <?php endif; ?>

  <form
    method="post"
    enctype="multipart/form-data">

    <!-- Mã sản phẩm -->
    <div class="mb-3">

      <label
        for="productCode"
        class="form-label">
        Mã sản phẩm
      </label>

      <input
        type="text"
        class="form-control"
        id="productCode"
        name="product_code"
        value="<?= htmlspecialchars(
                  $_POST['product_code'] ?? ''
                ) ?>"
        required>

    </div>

    <!-- Tên sản phẩm -->
    <div class="mb-3">

      <label
        for="productName"
        class="form-label">
        Tên sản phẩm
      </label>

      <input
        type="text"
        class="form-control"
        id="productName"
        name="product_name"
        value="<?= htmlspecialchars(
                  $_POST['product_name'] ?? ''
                ) ?>"
        required>

    </div>

    <!-- Mô tả -->
    <div class="mb-3">

      <label
        for="description"
        class="form-label">
        Mô tả
      </label>

      <textarea
        class="form-control"
        id="description"
        name="description"
        rows="3"><?= htmlspecialchars(
                    $_POST['description'] ?? ''
                  ) ?></textarea>

    </div>

    <!-- Đơn vị -->
    <div class="mb-3">

      <label
        for="unit"
        class="form-label">
        Đơn vị
      </label>

      <input
        type="text"
        class="form-control"
        id="unit"
        name="unit"
        value="<?= htmlspecialchars(
                  $_POST['unit'] ?? ''
                ) ?>">

    </div>

    <!-- Giá -->
    <div class="mb-3">

      <label
        for="price"
        class="form-label">
        Giá
      </label>

      <input
        type="number"
        class="form-control"
        id="price"
        name="price"
        min="0"
        step="0.01"
        value="<?= htmlspecialchars(
                  $_POST['price'] ?? ''
                ) ?>"
        required>

    </div>

    <!-- Số lượng tồn kho -->
    <div class="mb-3">

      <label
        for="stockQuantity"
        class="form-label">
        Số lượng tồn kho
      </label>

      <input
        type="number"
        class="form-control"
        id="stockQuantity"
        name="stock_quantity"
        min="0"
        value="<?= htmlspecialchars(
                  $_POST['stock_quantity'] ?? '0'
                ) ?>"
        required>

    </div>

    <!-- Danh mục -->
    <div class="mb-3">

      <label
        for="categoryID"
        class="form-label">
        Danh mục
      </label>

      <select
        class="form-select"
        id="categoryID"
        name="category_id"
        required>

        <option value="">
          -- Chọn danh mục --
        </option>

        <?php while (
          $category = $categories->fetch_assoc()
        ): ?>

          <option
            value="<?= $category['CategoryID'] ?>"
            <?= (
              (int) (
                $_POST['category_id'] ?? 0
              )
              ===
              (int) $category['CategoryID']
            )
              ? 'selected'
              : ''
            ?>>
            <?= htmlspecialchars(
              $category['CategoryName']
            ) ?>
          </option>

        <?php endwhile; ?>

      </select>

    </div>

    <!-- Nhà cung cấp -->
    <div class="mb-3">

      <label
        for="supplierID"
        class="form-label">
        Nhà cung cấp
      </label>

      <select
        class="form-select"
        id="supplierID"
        name="supplier_id"
        required>

        <option value="">
          -- Chọn nhà cung cấp --
        </option>

        <?php while (
          $supplier = $suppliers->fetch_assoc()
        ): ?>

          <option
            value="<?= $supplier['SupplierID'] ?>"
            <?= (
              (int) (
                $_POST['supplier_id'] ?? 0
              )
              ===
              (int) $supplier['SupplierID']
            )
              ? 'selected'
              : ''
            ?>>
            <?= htmlspecialchars(
              $supplier['SupplierName']
            ) ?>
          </option>

        <?php endwhile; ?>

      </select>

    </div>

    <!-- Hình ảnh sản phẩm -->
    <div class="mb-3">

      <label
        for="productImages"
        class="form-label">
        Hình ảnh sản phẩm
      </label>

      <input
        type="file"
        class="form-control"
        id="productImages"
        name="product_images[]"
        accept="image/jpeg,image/png,image/webp"
        multiple
        required>

      <div class="form-text">
        Chọn từ 1 đến 4 ảnh.
        Chấp nhận JPG, PNG hoặc WebP.
        Mỗi ảnh tối đa 2 MB.
        Ảnh đầu tiên là ảnh chính.
      </div>

    </div>

    <!-- Trạng thái -->
    <div class="mb-3 form-check">

      <input
        type="checkbox"
        class="form-check-input"
        id="isActive"
        name="is_active"
        value="1"
        <?= (
          isset($_POST['is_active'])
          || $_SERVER['REQUEST_METHOD'] !== 'POST'
        )
          ? 'checked'
          : ''
        ?>>

      <label
        class="form-check-label"
        for="isActive">
        Đang hoạt động
      </label>

    </div>

    <!-- Nút -->
    <button
      type="submit"
      class="btn btn-primary">
      Lưu
    </button>

    <a
      href="/admin/products/"
      class="btn btn-secondary">
      Hủy
    </a>

  </form>

</div>

<?php

require_once '/var/www/src/includes/admin/footer.php';

$conn->close();

?>