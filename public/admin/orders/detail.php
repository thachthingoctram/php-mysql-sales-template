<?php
require_once '/var/www/src/config/database.php';

$orderID = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$orderID || $orderID <= 0) {
  die('Mã đơn hàng không hợp lệ.');
}

$errorMessage = '';
$successMessage = '';

/**
 * Lấy thông tin đơn hàng.
 */
function getOrder($conn, $orderID)
{
  $sql = "
        SELECT
            o.OrderID,
            o.OrderDate,
            o.TotalAmount,
            o.Status,
            c.CustomerName
        FROM orders AS o
        INNER JOIN customers AS c
            ON o.CustomerID = c.CustomerID
        WHERE o.OrderID = ?
    ";

  $stmt = $conn->prepare($sql);
  $stmt->bind_param('i', $orderID);
  $stmt->execute();

  $result = $stmt->get_result();
  $order = $result->fetch_assoc();

  $stmt->close();

  return $order;
}

/**
 * Lấy chi tiết sản phẩm của đơn hàng.
 */
function getOrderDetails($conn, $orderID)
{
  $sql = "
        SELECT
            od.OrderDetailID,
            od.ProductID,
            od.Quantity,
            od.UnitPrice,
            p.ProductCode,
            p.ProductName
        FROM orderdetail AS od
        INNER JOIN products AS p
            ON od.ProductID = p.ProductID
        WHERE od.OrderID = ?
        ORDER BY od.OrderDetailID
    ";

  $stmt = $conn->prepare($sql);
  $stmt->bind_param('i', $orderID);
  $stmt->execute();

  $result = $stmt->get_result();
  $items = [];

  while ($row = $result->fetch_assoc()) {
    $items[] = $row;
  }

  $stmt->close();

  return $items;
}

$order = getOrder($conn, $orderID);

if (!$order) {
  die('Không tìm thấy đơn hàng.');
}

/**
 * Cập nhật trạng thái đơn hàng.
 * Khi hủy đơn, hoàn lại số lượng sản phẩm vào kho.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $newStatus = trim($_POST['status'] ?? '');

  // Những trạng thái được xem là đã hủy.
  $cancelledStatuses = ['Cancelled', 'Canceled', 'Đã hủy'];

  // Những trạng thái được xem là đã hoàn tất.
  $completedStatuses = ['Completed', 'Hoàn thành', 'Đã hoàn thành'];

  $currentStatus = trim((string) $order['Status']);

  if ($newStatus === '') {
    $errorMessage = 'Vui lòng chọn trạng thái đơn hàng.';
  } elseif (
    in_array($currentStatus, $cancelledStatuses, true)
    && !in_array($newStatus, $cancelledStatuses, true)
  ) {
    $errorMessage = 'Đơn hàng đã hủy, không thể chuyển sang trạng thái khác.';
  } elseif (
    in_array($currentStatus, $completedStatuses, true)
    && !in_array($newStatus, $completedStatuses, true)
  ) {
    $errorMessage = 'Đơn hàng đã hoàn thành, không thể thay đổi trạng thái.';
  } elseif ($newStatus === $currentStatus) {
    $successMessage = 'Trạng thái đơn hàng không thay đổi.';
  } else {
    try {
      $conn->begin_transaction();

      // Chỉ hoàn kho khi chuyển từ trạng thái chưa hủy sang đã hủy.
      $isCancelling = in_array($newStatus, $cancelledStatuses, true)
        && !in_array($currentStatus, $cancelledStatuses, true);

      if ($isCancelling) {
        $sqlStock = "
                    SELECT ProductID, Quantity
                    FROM orderdetail
                    WHERE OrderID = ?
                ";

        $stmtStock = $conn->prepare($sqlStock);
        $stmtStock->bind_param('i', $orderID);
        $stmtStock->execute();

        $stockResult = $stmtStock->get_result();

        $sqlUpdateStock = "
                    UPDATE products
                    SET StockQuantity = StockQuantity + ?
                    WHERE ProductID = ?
                ";

        $stmtUpdateStock = $conn->prepare($sqlUpdateStock);

        while ($item = $stockResult->fetch_assoc()) {
          $quantity = (int) $item['Quantity'];
          $productID = (int) $item['ProductID'];

          $stmtUpdateStock->bind_param(
            'ii',
            $quantity,
            $productID
          );

          $stmtUpdateStock->execute();
        }

        $stmtUpdateStock->close();
        $stmtStock->close();
      }

      $sqlUpdateStatus = "
                UPDATE orders
                SET Status = ?
                WHERE OrderID = ?
            ";

      $stmtUpdateStatus = $conn->prepare($sqlUpdateStatus);
      $stmtUpdateStatus->bind_param(
        'si',
        $newStatus,
        $orderID
      );
      $stmtUpdateStatus->execute();
      $stmtUpdateStatus->close();

      $conn->commit();

      // Chuyển hướng để tránh gửi lại biểu mẫu khi tải lại trang.
      header(
        'Location: detail.php?id=' . $orderID . '&updated=1'
      );
      exit;
    } catch (Throwable $e) {
      $conn->rollback();
      $errorMessage = 'Không thể cập nhật đơn hàng. Vui lòng thử lại.';
    }
  }
}

if (isset($_GET['updated'])) {
  $successMessage = 'Cập nhật trạng thái đơn hàng thành công.';
}

// Tải lại dữ liệu sau khi xử lý.
$order = getOrder($conn, $orderID);
$items = getOrderDetails($conn, $orderID);

// Tính tổng tiền dựa trên chi tiết sản phẩm.
$calculatedTotal = 0;

foreach ($items as $item) {
  $unitPrice = (float) $item['UnitPrice'];
  $quantity = (int) $item['Quantity'];

  $calculatedTotal += $unitPrice * $quantity;
}

// Dùng tổng tính từ chi tiết để hiển thị chính xác,
// kể cả khi orders.TotalAmount đang bằng 0.
$displayTotal = $calculatedTotal;

require_once '/var/www/src/includes/admin/header.php';
?>

<div class="container-fluid py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h2>Chi tiết đơn hàng #<?= (int) $order['OrderID'] ?></h2>
      <p class="text-muted mb-0">
        Thông tin đơn hàng và các sản phẩm đã đặt
      </p>
    </div>

    <a href="index.php" class="btn btn-outline-secondary">
      Quay lại danh sách
    </a>
  </div>

  <?php if ($errorMessage !== ''): ?>
    <div class="alert alert-danger">
      <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?>
    </div>
  <?php endif; ?>

  <?php if ($successMessage !== ''): ?>
    <div class="alert alert-success">
      <?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?>
    </div>
  <?php endif; ?>

  <div class="card mb-4">
    <div class="card-header">
      <h5 class="mb-0">Thông tin đơn hàng</h5>
    </div>

    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-6">
          <strong>Mã đơn hàng:</strong>
          #<?= (int) $order['OrderID'] ?>
        </div>

        <div class="col-md-6">
          <strong>Khách hàng:</strong>
          <?= htmlspecialchars(
            $order['CustomerName'] ?? '',
            ENT_QUOTES,
            'UTF-8'
          ) ?>
        </div>

        <div class="col-md-6">
          <strong>Ngày đặt:</strong>
          <?= htmlspecialchars(
            (string) $order['OrderDate'],
            ENT_QUOTES,
            'UTF-8'
          ) ?>
        </div>

        <div class="col-md-6">
          <strong>Trạng thái:</strong>
          <span class="badge bg-info text-dark">
            <?= htmlspecialchars(
              (string) $order['Status'],
              ENT_QUOTES,
              'UTF-8'
            ) ?>
          </span>
        </div>

        <div class="col-12">
          <strong>Tổng tiền:</strong>
          <span class="text-danger fw-bold fs-4">
            <?= number_format($displayTotal, 0, ',', '.') ?> đ
          </span>
        </div>
      </div>

      <hr>

      <form method="POST"
        action="detail.php?id=<?= (int) $orderID ?>"
        class="row g-3 align-items-end">

        <div class="col-md-6">
          <label for="status" class="form-label">
            Cập nhật trạng thái
          </label>

          <input
            type="text"
            name="status"
            id="status"
            class="form-control"
            value="<?= htmlspecialchars(
                      (string) $order['Status'],
                      ENT_QUOTES,
                      'UTF-8'
                    ) ?>"
            required>

          <small class="text-muted">
            Nhập đúng tên trạng thái mà hệ thống đang sử dụng.
          </small>
        </div>

        <div class="col-md-3">
          <button type="submit" class="btn btn-primary">
            Cập nhật trạng thái
          </button>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h5 class="mb-0">Danh sách sản phẩm trong đơn hàng</h5>
    </div>

    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>STT</th>
              <th>Mã sản phẩm</th>
              <th>Tên sản phẩm</th>
              <th class="text-end">Đơn giá</th>
              <th class="text-center">Số lượng</th>
              <th class="text-end">Thành tiền</th>
            </tr>
          </thead>

          <tbody>
            <?php if (empty($items)): ?>
              <tr>
                <td colspan="6" class="text-center text-muted">
                  Đơn hàng chưa có sản phẩm.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($items as $index => $item): ?>
                <?php
                $unitPrice = (float) $item['UnitPrice'];
                $quantity = (int) $item['Quantity'];
                $subtotal = $unitPrice * $quantity;
                ?>

                <tr>
                  <td><?= $index + 1 ?></td>

                  <td>
                    <?= htmlspecialchars(
                      (string) $item['ProductCode'],
                      ENT_QUOTES,
                      'UTF-8'
                    ) ?>
                  </td>

                  <td>
                    <?= htmlspecialchars(
                      (string) $item['ProductName'],
                      ENT_QUOTES,
                      'UTF-8'
                    ) ?>
                  </td>

                  <td class="text-end">
                    <?= number_format(
                      $unitPrice,
                      0,
                      ',',
                      '.'
                    ) ?> đ
                  </td>

                  <td class="text-center">
                    <?= $quantity ?>
                  </td>

                  <td class="text-end">
                    <?= number_format(
                      $subtotal,
                      0,
                      ',',
                      '.'
                    ) ?> đ
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>

          <tfoot>
            <tr>
              <th colspan="5" class="text-end">
                Tổng tiền đơn hàng:
              </th>

              <th class="text-end text-danger">
                <?= number_format(
                  $displayTotal,
                  0,
                  ',',
                  '.'
                ) ?> đ
              </th>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>
</div>

<?php
require_once '/var/www/src/includes/admin/footer.php';
$conn->close();
?>