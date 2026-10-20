<?php

$pageTitle = 'Quản lý đơn hàng';

require_once '/var/www/src/config/database.php';

// Lấy danh sách đơn hàng và tính tổng tiền từ chi tiết đơn hàng.
$sql = "
    SELECT
        o.OrderID,
        o.OrderDate,
        COALESCE(
            (
                SELECT SUM(od.Quantity * od.UnitPrice)
                FROM orderdetail AS od
                WHERE od.OrderID = o.OrderID
            ),
            0
        ) AS TotalAmount,
        o.Status,
        c.CustomerName,
        c.Phone
    FROM orders AS o
    JOIN customers AS c
        ON o.CustomerID = c.CustomerID
    ORDER BY o.OrderID DESC
";

$result = $conn->query($sql);

if (!$result) {
  die('Không thể tải danh sách đơn hàng: ' . $conn->error);
}

require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';

?>

<div class="container mt-4">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2>Quản lý đơn hàng</h2>
  </div>

  <div class="table-responsive">
    <table class="table table-bordered table-striped align-middle">

      <thead class="table-dark">
        <tr>
          <th>Mã đơn</th>
          <th>Ngày đặt</th>
          <th>Khách hàng</th>
          <th>Điện thoại</th>
          <th class="text-end">Tổng tiền</th>
          <th>Trạng thái</th>
          <th>Thao tác</th>
        </tr>
      </thead>

      <tbody>
        <?php if ($result->num_rows > 0): ?>

          <?php while ($order = $result->fetch_assoc()): ?>
            <tr>

              <td>
                #<?= (int) $order['OrderID'] ?>
              </td>

              <td>
                <?= htmlspecialchars(
                  $order['OrderDate'] ?? '',
                  ENT_QUOTES,
                  'UTF-8'
                ) ?>
              </td>

              <td>
                <?= htmlspecialchars(
                  $order['CustomerName'] ?? '',
                  ENT_QUOTES,
                  'UTF-8'
                ) ?>
              </td>

              <td>
                <?= htmlspecialchars(
                  $order['Phone'] ?? '',
                  ENT_QUOTES,
                  'UTF-8'
                ) ?>
              </td>

              <td class="text-end">
                <?= number_format(
                  (float) $order['TotalAmount'],
                  0,
                  ',',
                  '.'
                ) ?> đ
              </td>

              <td>
                <?php
                $status = $order['Status'];

                $badgeClass = match ($status) {
                  'Pending'   => 'bg-warning text-dark',
                  'Confirmed' => 'bg-primary',
                  'Shipping'  => 'bg-info text-dark',
                  'Completed' => 'bg-success',
                  'Cancelled' => 'bg-secondary',
                  default     => 'bg-secondary'
                };
                ?>

                <span class="badge <?= $badgeClass ?>">
                  <?= htmlspecialchars(
                    $status ?? '',
                    ENT_QUOTES,
                    'UTF-8'
                  ) ?>
                </span>
              </td>

              <td>
                <a
                  href="/admin/orders/detail.php?id=<?= (int) $order['OrderID'] ?>"
                  class="btn btn-sm btn-primary">
                  Xem
                </a>
              </td>

            </tr>
          <?php endwhile; ?>

        <?php else: ?>

          <tr>
            <td colspan="7" class="text-center text-muted">
              Chưa có đơn hàng nào.
            </td>
          </tr>

        <?php endif; ?>
      </tbody>

    </table>
  </div>

</div>

<?php

$result->free();

require_once '/var/www/src/includes/admin/footer.php';

$conn->close();
