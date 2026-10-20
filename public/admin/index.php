<?php
require_once '/var/www/src/config/session.php';
require_once '/var/www/src/config/database.php';

$pageTitle = 'Dashboard Admin';

function e($value): string
{
  return htmlspecialchars(
    (string) ($value ?? ''),
    ENT_QUOTES,
    'UTF-8'
  );
}

function getCount(mysqli $conn, string $sql): int
{
  $result = $conn->query($sql);

  if (!$result) {
    return 0;
  }

  $row = $result->fetch_row();

  return (int) ($row[0] ?? 0);
}

function money($value): string
{
  return number_format((float) $value, 0, ',', '.') . ' đ';
}

/*
 * 1. Thống kê tổng quan
 */
$totalProducts = getCount(
  $conn,
  "SELECT COUNT(*) FROM products"
);

$activeProducts = getCount(
  $conn,
  "SELECT COUNT(*) FROM products WHERE IsActive = 1"
);

$totalCustomers = getCount(
  $conn,
  "SELECT COUNT(*) FROM customers"
);

$totalOrders = getCount(
  $conn,
  "SELECT COUNT(*) FROM orders"
);

$newOrders = getCount(
  $conn,
  "SELECT COUNT(*) FROM orders WHERE Status = 'Pending'"
);

$completedOrders = getCount(
  $conn,
  "SELECT COUNT(*) FROM orders WHERE Status = 'Completed'"
);

$cancelledOrders = getCount(
  $conn,
  "SELECT COUNT(*) FROM orders WHERE Status = 'Cancelled'"
);

/*
 * 2. Doanh thu hôm nay và tháng này.
 * Chỉ tính đơn hàng đã hoàn thành.
 * Doanh thu được tính từ chi tiết đơn hàng.
 */
$sqlRevenue = "
    SELECT
        COALESCE(
            SUM(
                CASE
                    WHEN DATE(o.OrderDate) = CURDATE()
                    THEN od.Quantity * od.UnitPrice
                    ELSE 0
                END
            ),
            0
        ) AS TodayRevenue,

        COALESCE(
            SUM(
                CASE
                    WHEN YEAR(o.OrderDate) = YEAR(CURDATE())
                     AND MONTH(o.OrderDate) = MONTH(CURDATE())
                    THEN od.Quantity * od.UnitPrice
                    ELSE 0
                END
            ),
            0
        ) AS MonthRevenue

    FROM orders o
    LEFT JOIN orderdetail od
        ON od.OrderID = o.OrderID
    WHERE o.Status = 'Completed'
";

$revenueResult = $conn->query($sqlRevenue);

$revenue = $revenueResult
  ? $revenueResult->fetch_assoc()
  : ['TodayRevenue' => 0, 'MonthRevenue' => 0];

$todayRevenue = (float) ($revenue['TodayRevenue'] ?? 0);
$monthRevenue = (float) ($revenue['MonthRevenue'] ?? 0);

/*
 * 3. Đơn hàng gần đây
 */
$sqlRecentOrders = "
    SELECT
        o.OrderID,
        o.OrderDate,
        o.Status,
        c.CustomerName,
        c.Phone,
        COALESCE(
            SUM(od.Quantity * od.UnitPrice),
            0
        ) AS OrderTotal

    FROM orders o

    LEFT JOIN customers c
        ON c.CustomerID = o.CustomerID

    LEFT JOIN orderdetail od
        ON od.OrderID = o.OrderID

    GROUP BY
        o.OrderID,
        o.OrderDate,
        o.Status,
        c.CustomerName,
        c.Phone

    ORDER BY o.OrderDate DESC, o.OrderID DESC
    LIMIT 6
";

$recentOrders = $conn->query($sqlRecentOrders);

/*
 * 4. Sản phẩm sắp hết hàng
 */
$sqlLowStock = "
    SELECT
        ProductID,
        ProductCode,
        ProductName,
        StockQuantity
    FROM products
    WHERE IsActive = 1
      AND StockQuantity <= 5
    ORDER BY StockQuantity ASC
    LIMIT 6
";

$lowStockProducts = $conn->query($sqlLowStock);

/*
 * 5. Dữ liệu doanh thu 7 ngày gần nhất
 */
$sqlChart = "
    SELECT
        DATE(o.OrderDate) AS RevenueDate,
        SUM(od.Quantity * od.UnitPrice) AS Revenue

    FROM orders o

    INNER JOIN orderdetail od
        ON od.OrderID = o.OrderID

    WHERE o.Status = 'Completed'
      AND o.OrderDate >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
      AND o.OrderDate < DATE_ADD(CURDATE(), INTERVAL 1 DAY)

    GROUP BY DATE(o.OrderDate)
    ORDER BY RevenueDate ASC
";

$chartResult = $conn->query($sqlChart);

$revenueByDate = [];

if ($chartResult) {
  while ($row = $chartResult->fetch_assoc()) {
    $revenueByDate[$row['RevenueDate']] =
      (float) $row['Revenue'];
  }
}

$chartLabels = [];
$chartValues = [];

for ($i = 6; $i >= 0; $i--) {
  $date = date('Y-m-d', strtotime("-{$i} days"));

  $chartLabels[] = date('d/m', strtotime($date));
  $chartValues[] = $revenueByDate[$date] ?? 0;
}

$maxRevenue = max($chartValues ?: [0]);

/*
 * 6. Trạng thái đơn hàng
 */
$statusLabels = [
  'Pending'   => 'Chờ xử lý',
  'Confirmed' => 'Đã xác nhận',
  'Shipping'  => 'Đang giao',
  'Completed' => 'Hoàn thành',
  'Cancelled' => 'Đã hủy'
];

$statusClasses = [
  'Pending'   => 'pending',
  'Confirmed' => 'confirmed',
  'Shipping'  => 'shipping',
  'Completed' => 'completed',
  'Cancelled' => 'cancelled'
];

require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';
?>

<style>
  .dashboard-page {
    padding-bottom: 32px;
    color: #243047;
  }

  .dashboard-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    margin-bottom: 24px;
  }

  .dashboard-heading h1 {
    font-size: 28px;
    font-weight: 750;
    margin: 0 0 6px;
  }

  .dashboard-heading p {
    color: #748094;
    margin: 0;
  }

  .dashboard-date {
    color: #64748b;
    background: #fff;
    border: 1px solid #e5eaf1;
    padding: 10px 14px;
    border-radius: 10px;
    white-space: nowrap;
  }

  .dashboard-stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 22px;
  }

  .dashboard-card,
  .dashboard-panel {
    background: #fff;
    border: 1px solid #e8edf3;
    border-radius: 15px;
    box-shadow: 0 4px 18px rgba(31, 45, 61, .04);
  }

  .dashboard-card {
    padding: 20px;
  }

  .dashboard-card-label {
    font-size: 14px;
    color: #758197;
    margin-bottom: 12px;
  }

  .dashboard-card-value {
    font-size: 27px;
    font-weight: 750;
    line-height: 1.3;
    overflow-wrap: anywhere;
  }

  .dashboard-card-note {
    color: #758197;
    font-size: 12px;
    margin-top: 9px;
  }

  .dashboard-card.teal {
    border-top: 3px solid #0d9488;
  }

  .dashboard-card.blue {
    border-top: 3px solid #3b82f6;
  }

  .dashboard-card.purple {
    border-top: 3px solid #8b5cf6;
  }

  .dashboard-card.orange {
    border-top: 3px solid #f59e0b;
  }

  .dashboard-main-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.7fr) minmax(280px, 1fr);
    gap: 20px;
    margin-bottom: 22px;
  }

  .dashboard-panel {
    padding: 22px;
    min-width: 0;
  }

  .dashboard-panel h2 {
    font-size: 17px;
    font-weight: 700;
    margin: 0;
  }

  .dashboard-panel-subtitle {
    color: #8290a4;
    font-size: 13px;
    margin-top: 6px;
  }

  .chart {
    height: 230px;
    display: flex;
    align-items: stretch;
    gap: 12px;
    margin-top: 28px;
    padding-top: 10px;
    border-bottom: 1px solid #e7edf4;
  }

  .chart-column {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-end;
    gap: 9px;
  }

  .chart-bar-area {
    flex: 1;
    width: 100%;
    display: flex;
    align-items: flex-end;
    justify-content: center;
    min-height: 0;
  }

  .chart-bar {
    width: min(38px, 75%);
    min-height: 3px;
    border-radius: 7px 7px 0 0;
    background: linear-gradient(180deg, #2dd4bf, #0d9488);
  }

  .chart-label {
    font-size: 12px;
    color: #8290a4;
    padding-bottom: 8px;
  }

  .chart-value {
    font-size: 11px;
    color: #64748b;
    text-align: center;
    overflow-wrap: anywhere;
  }

  .status-list {
    display: grid;
    gap: 18px;
    margin-top: 25px;
  }

  .status-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
  }

  .status-name {
    color: #64748b;
    font-size: 14px;
  }

  .status-number {
    font-weight: 700;
  }

  .dashboard-table-wrap {
    width: 100%;
    overflow-x: auto;
    margin-top: 18px;
  }

  .dashboard-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
  }

  .dashboard-table th {
    color: #8490a3;
    font-weight: 600;
    background: #f8fafc;
    white-space: nowrap;
  }

  .dashboard-table th,
  .dashboard-table td {
    padding: 13px 12px;
    text-align: left;
    border-bottom: 1px solid #edf1f5;
  }

  .dashboard-table tbody tr:last-child td {
    border-bottom: 0;
  }

  .dashboard-table td {
    color: #46536a;
  }

  .dashboard-table strong {
    color: #26344b;
  }

  .dashboard-badge {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 650;
    white-space: nowrap;
  }

  .dashboard-badge.pending {
    background: #fff4d6;
    color: #9a6700;
  }

  .dashboard-badge.confirmed {
    background: #dbeafe;
    color: #1d4ed8;
  }

  .dashboard-badge.shipping {
    background: #ede9fe;
    color: #6d28d9;
  }

  .dashboard-badge.completed {
    background: #dcfce7;
    color: #166534;
  }

  .dashboard-badge.cancelled {
    background: #fee2e2;
    color: #b91c1c;
  }

  .dashboard-shortcuts {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
    margin-bottom: 22px;
  }

  .dashboard-shortcut {
    display: block;
    text-decoration: none;
    color: #344258;
    background: #fff;
    border: 1px solid #e8edf3;
    border-radius: 12px;
    padding: 17px;
    transition: border-color .2s, transform .2s;
  }

  .dashboard-shortcut:hover {
    color: #0f766e;
    border-color: #5eead4;
    transform: translateY(-2px);
  }

  .dashboard-shortcut strong {
    display: block;
    margin-bottom: 5px;
  }

  .dashboard-shortcut span {
    font-size: 12px;
    color: #8490a3;
  }

  .dashboard-empty {
    text-align: center !important;
    color: #94a3b8 !important;
    padding: 24px !important;
  }

  .dashboard-stock-low {
    color: #dc2626 !important;
    font-weight: 700;
  }

  @media (max-width: 1100px) {
    .dashboard-stats {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .dashboard-main-grid {
      grid-template-columns: 1fr;
    }
  }

  @media (max-width: 600px) {
    .dashboard-heading {
      align-items: flex-start;
      flex-direction: column;
    }

    .dashboard-stats {
      grid-template-columns: 1fr 1fr;
      gap: 10px;
    }

    .dashboard-card {
      padding: 14px;
    }

    .dashboard-card-value {
      font-size: 21px;
    }

    .dashboard-panel {
      padding: 15px;
    }

    .dashboard-shortcuts {
      grid-template-columns: 1fr;
    }
  }
</style>

<div class="dashboard-page">

  <div class="dashboard-heading">
    <div>
      <h1>Dashboard Admin</h1>
      <p>Tổng quan hoạt động kinh doanh mỹ phẩm.</p>
    </div>

    <div class="dashboard-date">
      <?= e(date('d/m/Y')) ?>
    </div>
  </div>

  <!-- Thẻ thống kê -->
  <div class="dashboard-stats">

    <div class="dashboard-card teal">
      <div class="dashboard-card-label">Tổng sản phẩm</div>
      <div class="dashboard-card-value">
        <?= number_format($totalProducts) ?>
      </div>
      <div class="dashboard-card-note">
        <?= number_format($activeProducts) ?> sản phẩm đang bán
      </div>
    </div>

    <div class="dashboard-card blue">
      <div class="dashboard-card-label">Tổng đơn hàng</div>
      <div class="dashboard-card-value">
        <?= number_format($totalOrders) ?>
      </div>
      <div class="dashboard-card-note">
        <?= number_format($newOrders) ?> đơn đang chờ xử lý
      </div>
    </div>

    <div class="dashboard-card purple">
      <div class="dashboard-card-label">Khách hàng</div>
      <div class="dashboard-card-value">
        <?= number_format($totalCustomers) ?>
      </div>
      <div class="dashboard-card-note">
        Tổng khách hàng đã lưu
      </div>
    </div>

    <div class="dashboard-card orange">
      <div class="dashboard-card-label">Doanh thu tháng này</div>
      <div class="dashboard-card-value">
        <?= money($monthRevenue) ?>
      </div>
      <div class="dashboard-card-note">
        Hôm nay: <?= money($todayRevenue) ?>
      </div>
    </div>

  </div>

  <!-- Truy cập nhanh -->
  <div class="dashboard-shortcuts">
    <a class="dashboard-shortcut" href="/admin/products/index.php">
      <strong>Quản lý sản phẩm</strong>
      <span>Thêm, sửa và cập nhật tồn kho</span>
    </a>

    <a class="dashboard-shortcut" href="/admin/orders/index.php">
      <strong>Quản lý đơn hàng</strong>
      <span>Xem và xử lý đơn hàng</span>
    </a>

    <a class="dashboard-shortcut" href="/admin/customer/index.php">
      <strong>Quản lý khách hàng</strong>
      <span>Xem và chỉnh sửa thông tin khách hàng</span>
    </a>
  </div>

  <!-- Biểu đồ và trạng thái đơn hàng -->
  <div class="dashboard-main-grid">

    <section class="dashboard-panel">
      <h2>Doanh thu 7 ngày gần nhất</h2>
      <div class="dashboard-panel-subtitle">
        Chỉ tính các đơn hàng đã hoàn thành
      </div>

      <div class="chart">
        <?php foreach ($chartValues as $index => $value): ?>
          <?php
          $height = $maxRevenue > 0
            ? ($value / $maxRevenue) * 100
            : 0;
          ?>
          <div class="chart-column">
            <div class="chart-value">
              <?= $value > 0
                ? e(number_format($value, 0, ',', '.'))
                : '0' ?>
            </div>

            <div class="chart-bar-area">
              <div
                class="chart-bar"
                style="height: <?= (float) $height ?>%">
              </div>
            </div>

            <div class="chart-label">
              <?= e($chartLabels[$index]) ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="dashboard-panel">
      <h2>Tình hình đơn hàng</h2>
      <div class="dashboard-panel-subtitle">
        Tổng số đơn theo từng trạng thái
      </div>

      <div class="status-list">
        <?php foreach ($statusLabels as $status => $label): ?>
          <?php
          $count = getCount(
            $conn,
            "SELECT COUNT(*) FROM orders WHERE Status = '"
              . $conn->real_escape_string($status)
              . "'"
          );
          ?>
          <div class="status-row">
            <span class="status-name">
              <?= e($label) ?>
            </span>
            <span class="status-number">
              <?= number_format($count) ?>
            </span>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

  </div>

  <!-- Đơn hàng gần đây -->
  <section class="dashboard-panel" style="margin-bottom: 22px;">
    <h2>Đơn hàng gần đây</h2>
    <div class="dashboard-panel-subtitle">
      Các đơn hàng mới được ghi nhận trong hệ thống
    </div>

    <div class="dashboard-table-wrap">
      <table class="dashboard-table">
        <thead>
          <tr>
            <th>Mã đơn</th>
            <th>Khách hàng</th>
            <th>Điện thoại</th>
            <th>Ngày đặt</th>
            <th>Tổng tiền</th>
            <th>Trạng thái</th>
          </tr>
        </thead>

        <tbody>
          <?php if ($recentOrders && $recentOrders->num_rows > 0): ?>
            <?php while ($order = $recentOrders->fetch_assoc()): ?>
              <?php
              $status = $order['Status'] ?? '';
              $statusLabel = $statusLabels[$status] ?? $status;
              $statusClass = $statusClasses[$status] ?? '';
              ?>
              <tr>
                <td>
                  <strong>
                    #<?= (int) $order['OrderID'] ?>
                  </strong>
                </td>
                <td><?= e($order['CustomerName'] ?? 'Khách hàng') ?></td>
                <td><?= e($order['Phone'] ?? '') ?></td>
                <td>
                  <?= e(
                    $order['OrderDate']
                      ? date(
                        'd/m/Y',
                        strtotime($order['OrderDate'])
                      )
                      : ''
                  ) ?>
                </td>
                <td>
                  <?= money($order['OrderTotal']) ?>
                </td>
                <td>
                  <span class="dashboard-badge <?= e($statusClass) ?>">
                    <?= e($statusLabel) ?>
                  </span>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="6" class="dashboard-empty">
                Chưa có đơn hàng nào.
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <!-- Sản phẩm sắp hết hàng -->
  <section class="dashboard-panel">
    <h2>Sản phẩm sắp hết hàng</h2>
    <div class="dashboard-panel-subtitle">
      Sản phẩm đang bán có số lượng tồn kho không quá 5
    </div>

    <div class="dashboard-table-wrap">
      <table class="dashboard-table">
        <thead>
          <tr>
            <th>Mã sản phẩm</th>
            <th>Tên sản phẩm</th>
            <th>Tồn kho</th>
          </tr>
        </thead>

        <tbody>
          <?php if ($lowStockProducts && $lowStockProducts->num_rows > 0): ?>
            <?php while ($product = $lowStockProducts->fetch_assoc()): ?>
              <tr>
                <td><?= e($product['ProductCode'] ?? $product['ProductID']) ?></td>
                <td><?= e($product['ProductName']) ?></td>
                <td class="dashboard-stock-low">
                  <?= number_format((int) $product['StockQuantity']) ?>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="3" class="dashboard-empty">
                Không có sản phẩm nào sắp hết hàng.
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

</div>

<?php
require_once '/var/www/src/includes/admin/footer.php';
?>