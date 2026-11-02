<?php
require_once '/var/www/src/config/session.php';
require_once '/var/www/src/config/database.php';

$pageTitle = 'Dashboard Admin';

function e($value): string
{
  return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function money($value): string
{
  return number_format((float)$value, 0, ',', '.') . ' đ';
}

function getCount(mysqli $conn, string $sql): int
{
  $result = $conn->query($sql);

  if (!$result) {
    error_log('Dashboard count query error: ' . $conn->error);
    return 0;
  }

  $row = $result->fetch_row();
  return (int)($row[0] ?? 0);
}

/* =========================
   THỐNG KÊ TỔNG QUAN
========================= */
$totalProducts = getCount($conn, "SELECT COUNT(*) FROM products");
$activeProducts = getCount($conn, "SELECT COUNT(*) FROM products WHERE IsActive = 1");
$totalCustomers = getCount($conn, "SELECT COUNT(*) FROM customers");
$totalOrders = getCount($conn, "SELECT COUNT(*) FROM orders");
$newOrders = getCount($conn, "SELECT COUNT(*) FROM orders WHERE Status = 'Pending'");

/* =========================
   TỔNG DOANH THU
   Tính theo tất cả trạng thái
========================= */
$sqlTotalRevenue = "
    SELECT COALESCE(SUM(od.Quantity * od.UnitPrice), 0) AS TotalRevenue
    FROM orders o
    LEFT JOIN orderdetail od ON od.OrderID = o.OrderID
";

$resultTotalRevenue = $conn->query($sqlTotalRevenue);
$totalRevenue = 0;

if ($resultTotalRevenue) {
  $row = $resultTotalRevenue->fetch_assoc();
  $totalRevenue = (float)($row['TotalRevenue'] ?? 0);
} else {
  error_log('Dashboard total revenue query error: ' . $conn->error);
}

/* =========================
   DOANH THU HÔM NAY VÀ THÁNG NÀY
========================= */
$sqlRevenue = "
    SELECT
        COALESCE(SUM(
            CASE
                WHEN DATE(o.OrderDate) = CURDATE()
                THEN od.Quantity * od.UnitPrice
                ELSE 0
            END
        ), 0) AS TodayRevenue,

        COALESCE(SUM(
            CASE
                WHEN YEAR(o.OrderDate) = YEAR(CURDATE())
                 AND MONTH(o.OrderDate) = MONTH(CURDATE())
                THEN od.Quantity * od.UnitPrice
                ELSE 0
            END
        ), 0) AS MonthRevenue
    FROM orders o
    LEFT JOIN orderdetail od ON od.OrderID = o.OrderID
";

$resultRevenue = $conn->query($sqlRevenue);

$todayRevenue = 0;
$monthRevenue = 0;

if ($resultRevenue) {
  $revenue = $resultRevenue->fetch_assoc();
  $todayRevenue = (float)($revenue['TodayRevenue'] ?? 0);
  $monthRevenue = (float)($revenue['MonthRevenue'] ?? 0);
}

/* =========================
   ĐƠN HÀNG GẦN ĐÂY
========================= */
$sqlRecentOrders = "
    SELECT
        o.OrderID,
        o.OrderDate,
        o.Status,
        c.CustomerName,
        c.Phone,
        COALESCE(SUM(od.Quantity * od.UnitPrice), 0) AS OrderTotal
    FROM orders o
    LEFT JOIN customers c ON c.CustomerID = o.CustomerID
    LEFT JOIN orderdetail od ON od.OrderID = o.OrderID
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

if (!$recentOrders) {
  error_log('Dashboard recent orders query error: ' . $conn->error);
}

/* =========================
   SẢN PHẨM SẮP HẾT HÀNG
========================= */
$sqlLowStock = "
    SELECT ProductID, ProductCode, ProductName, StockQuantity
    FROM products
    WHERE IsActive = 1 AND StockQuantity <= 5
    ORDER BY StockQuantity ASC
    LIMIT 6
";

$lowStockProducts = $conn->query($sqlLowStock);

if (!$lowStockProducts) {
  error_log('Dashboard low stock query error: ' . $conn->error);
}

/* =========================
   BIỂU ĐỒ DOANH THU 7 NGÀY
/* Doanh thu 7 ngày gần nhất */
$sqlChart = "
    SELECT
        DATE(o.OrderDate) AS RevenueDate,
        SUM(od.Quantity * od.UnitPrice) AS Revenue
    FROM orders o
    INNER JOIN orderdetail od ON od.OrderID = o.OrderID
    WHERE o.OrderDate >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
      AND o.OrderDate < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
    GROUP BY DATE(o.OrderDate)
    ORDER BY RevenueDate
";

$chartResult = $conn->query($sqlChart);
$revenueByDate = [];

if ($chartResult) {
  while ($row = $chartResult->fetch_assoc()) {
    $revenueByDate[$row['RevenueDate']] = (float)$row['Revenue'];
  }
} else {
  error_log('Dashboard chart query error: ' . $conn->error);
}

$chartLabels = [];
$chartValues = [];

for ($i = 6; $i >= 0; $i--) {
  $date = date('Y-m-d', strtotime("-{$i} days"));
  $chartLabels[] = date('d/m', strtotime($date));
  $chartValues[] = $revenueByDate[$date] ?? 0;
}

/* =========================
   BIỂU ĐỒ TRẠNG THÁI ĐƠN HÀNG
========================= */
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

$statusValues = [];

foreach ($statusLabels as $status => $label) {
  $escapedStatus = $conn->real_escape_string($status);

  $statusValues[] = getCount(
    $conn,
    "SELECT COUNT(*) FROM orders WHERE Status = '{$escapedStatus}'"
  );
}

$statusChartLabels = array_values($statusLabels);

/* Header chỉ mở HTML và tải Bootstrap; không include navbar.php */
require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';
?>

<link rel="stylesheet"
  href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
  .dashboard-content {
    min-height: 100vh;
    padding: 28px;
    background: linear-gradient(135deg, #eaf4ff 0%, #f4f8ff 50%, #e6f0ff 100%);
    color: #1e293b;
  }

  .dashboard-content h2,
  .dashboard-content h5 {
    letter-spacing: -0.3px;
  }

  .dashboard-content .stat-card,
  .dashboard-content .dashboard-panel {
    border: 1px solid #edf0f5;
    border-radius: 16px;
    background: #fff;
  }

  .dashboard-content .stat-card {
    transition: transform .2s ease, box-shadow .2s ease;
  }

  .dashboard-content .stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 25px rgba(15, 23, 42, .08);
  }

  .dashboard-content .stat-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 48px;
    height: 48px;
    border-radius: 14px;
    font-size: 22px;
  }

  .dashboard-content .dashboard-panel {
    overflow: hidden;
  }

  .dashboard-content .table {
    margin-bottom: 0;
  }

  .dashboard-content .table thead th {
    padding: 14px 16px;
    color: #64748b;
    background: #f8fafc;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    white-space: nowrap;
    border-bottom: 1px solid #edf0f5;
  }

  .dashboard-content .table tbody td {
    padding: 16px;
    border-color: #f1f5f9;
    vertical-align: middle;
  }

  .dashboard-content .table tbody tr:hover {
    background: #f8fafc;
  }

  .dashboard-content .btn-outline-primary {
    border-radius: 9px;
  }

  .dashboard-content .text-secondary {
    color: #64748b !important;
  }

  .dashboard-content .chart-container {
    position: relative;
    height: 300px;
    width: 100%;
  }

  @media (max-width: 768px) {
    .dashboard-content {
      padding: 16px;
    }

    .dashboard-content .chart-container {
      height: 260px;
    }
  }
</style>

<div class="container-fluid dashboard-content">

  <!-- Tiêu đề -->
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
      <h2 class="fw-bold text-dark mb-1">Dashboard</h2>
      <p class="text-secondary mb-0">
        Tổng quan hoạt động kinh doanh của cửa hàng
      </p>
    </div>

    <span class="badge bg-white text-secondary border p-2">
      <i class="bi bi-calendar3 me-1"></i>
      <?= e(date('d/m/Y')) ?>
    </span>
  </div>

  <!-- Thẻ thống kê -->
  <div class="row g-4 mb-4">

    <div class="col-12 col-sm-6 col-xl-3">
      <div class="card stat-card shadow-sm h-100">
        <div class="card-body p-4">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <span class="text-secondary">Tổng sản phẩm</span>
            <div class="stat-icon bg-primary bg-opacity-10 text-primary">
              <i class="bi bi-box-seam"></i>
            </div>
          </div>
          <h3 class="fw-bold mb-1"><?= number_format($totalProducts) ?></h3>
          <small class="text-secondary">
            <?= number_format($activeProducts) ?> sản phẩm đang hoạt động
          </small>
        </div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
      <div class="card stat-card shadow-sm h-100">
        <div class="card-body p-4">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <span class="text-secondary">Đơn hàng</span>
            <div class="stat-icon bg-success bg-opacity-10 text-success">
              <i class="bi bi-receipt"></i>
            </div>
          </div>
          <h3 class="fw-bold mb-1"><?= number_format($totalOrders) ?></h3>
          <small class="text-secondary">
            <?= number_format($newOrders) ?> đơn đang chờ xử lý
          </small>
        </div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
      <div class="card stat-card shadow-sm h-100">
        <div class="card-body p-4">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <span class="text-secondary">Khách hàng</span>
            <div class="stat-icon bg-warning bg-opacity-10 text-warning">
              <i class="bi bi-people"></i>
            </div>
          </div>
          <h3 class="fw-bold mb-1"><?= number_format($totalCustomers) ?></h3>
          <small class="text-secondary">Khách hàng trong hệ thống</small>
        </div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
      <div class="card stat-card shadow-sm h-100">
        <div class="card-body p-4">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <span class="text-secondary">Tổng giá trị đơn hàng</span>
            <div class="stat-icon bg-info bg-opacity-10 text-info">
              <i class="bi bi-currency-dollar"></i>
            </div>
          </div>
          <h3 class="fw-bold mb-1"><?= money($totalRevenue) ?></h3>
          <small class="text-secondary">Tất cả trạng thái đơn hàng</small>
        </div>
      </div>
    </div>

  </div>

  <!-- Biểu đồ -->
  <div class="row g-4 mb-4">

    <div class="col-12 col-xl-8">
      <div class="card dashboard-panel shadow-sm h-100">
        <div class="card-body p-4">
          <h5 class="fw-bold mb-1">Doanh thu</h5>
          <p class="text-secondary small mb-4">
            Tổng giá trị đơn hàng theo ngày trong 7 ngày gần nhất
          </p>
          <div class="chart-container">
            <canvas id="revenueChart"></canvas>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-xl-4">
      <div class="card dashboard-panel shadow-sm h-100">
        <div class="card-body p-4">
          <h5 class="fw-bold mb-1">Trạng thái đơn hàng</h5>
          <p class="text-secondary small mb-4">
            Thống kê số lượng đơn theo trạng thái
          </p>
          <div class="chart-container">
            <canvas id="orderStatusChart"></canvas>
          </div>
        </div>
      </div>
    </div>

  </div>

  <!-- Đơn hàng gần đây -->
  <div class="card dashboard-panel shadow-sm mb-4">
    <div class="card-body p-4">
      <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <div>
          <h5 class="fw-bold mb-1">Đơn hàng gần đây</h5>
          <p class="text-secondary small mb-0">
            Theo dõi các đơn hàng mới nhất
          </p>
        </div>

        <a href="/admin/orders/" class="btn btn-outline-primary btn-sm">
          Xem tất cả
        </a>
      </div>

      <div class="table-responsive">
        <table class="table align-middle table-hover">
          <thead>
            <tr>
              <th>Mã đơn</th>
              <th>Khách hàng</th>
              <th>Ngày đặt</th>
              <th>Tổng tiền</th>
              <th>Trạng thái</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($recentOrders && $recentOrders->num_rows > 0): ?>
              <?php while ($order = $recentOrders->fetch_assoc()): ?>
                <?php
                $status = (string)($order['Status'] ?? '');
                $statusLabel = $statusLabels[$status] ?? $status;
                $statusClass = $statusClasses[$status] ?? 'secondary';
                ?>
                <tr>
                  <td class="fw-semibold">
                    #<?= e($order['OrderID']) ?>
                  </td>
                  <td>
                    <div class="fw-semibold">
                      <?= e($order['CustomerName'] ?? 'Khách hàng') ?>
                    </div>
                    <small class="text-secondary">
                      <?= e($order['Phone'] ?? '') ?>
                    </small>
                  </td>
                  <td><?= e($order['OrderDate'] ?? '') ?></td>
                  <td class="fw-semibold">
                    <?= money($order['OrderTotal'] ?? 0) ?>
                  </td>
                  <td>
                    <span class="badge bg-primary bg-opacity-10 text-primary">
                      <?= e($statusLabel) ?>
                    </span>
                  </td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr>
                <td colspan="5" class="text-center text-secondary py-4">
                  Chưa có dữ liệu đơn hàng.
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Sản phẩm sắp hết hàng -->
  <div class="card dashboard-panel shadow-sm">
    <div class="card-body p-4">
      <h5 class="fw-bold mb-1">Sản phẩm sắp hết hàng</h5>
      <p class="text-secondary small mb-3">
        Các sản phẩm cần được kiểm tra và bổ sung tồn kho
      </p>

      <div class="table-responsive">
        <table class="table align-middle table-hover">
          <thead>
            <tr>
              <th>Mã sản phẩm</th>
              <th>Tên sản phẩm</th>
              <th>Số lượng tồn</th>
              <th>Tình trạng</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($lowStockProducts && $lowStockProducts->num_rows > 0): ?>
              <?php while ($product = $lowStockProducts->fetch_assoc()): ?>
                <tr>
                  <td><?= e($product['ProductCode'] ?? '') ?></td>
                  <td class="fw-medium">
                    <?= e($product['ProductName'] ?? '') ?>
                  </td>
                  <td><?= number_format((int)($product['StockQuantity'] ?? 0)) ?></td>
                  <td>
                    <span class="badge bg-danger bg-opacity-10 text-danger">
                      Sắp hết hàng
                    </span>
                  </td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr>
                <td colspan="4" class="text-center text-secondary py-4">
                  Không có sản phẩm sắp hết hàng.
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    const revenueLabels = <?= json_encode($chartLabels, JSON_UNESCAPED_UNICODE) ?>;
    const revenueData = <?= json_encode(array_map(
                          fn($value) => round($value / 1000000, 2),
                          $chartValues
                        ), JSON_UNESCAPED_UNICODE) ?>;

    const orderStatusLabels = <?= json_encode(
                                array_values($statusLabels),
                                JSON_UNESCAPED_UNICODE
                              ) ?>;
    const orderStatusData = <?= json_encode($statusValues, JSON_UNESCAPED_UNICODE) ?>;
    if (typeof Chart === 'undefined') {
      console.error('Không tải được Chart.js. Hãy kiểm tra kết nối mạng.');
      return;
    }

    const revenueCanvas = document.getElementById('revenueChart');

    if (revenueCanvas) {
      new Chart(revenueCanvas, {
        type: 'line',
        data: {
          labels: revenueLabels,
          datasets: [{
            label: 'Doanh thu',
            data: revenueData,
            borderColor: '#3B82F6',
            backgroundColor: 'rgba(59, 130, 246, 0.12)',
            fill: true,
            tension: 0.35,
            borderWidth: 3,
            pointRadius: 4,
            pointHoverRadius: 6,
            pointBackgroundColor: '#3B82F6',
            spanGaps: true
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: {
            y: {
              beginAtZero: true,
              min: 0,
              max: 100,
              ticks: {
                stepSize: 10,
                callback: function(value) {
                  return value === 0 ? '0' : value + ' triệu';
                }
              }
            }
          },

        }
      });
    }

    const statusCanvas = document.getElementById('orderStatusChart');

    if (statusCanvas) {
      new Chart(statusCanvas, {
        type: 'doughnut',
        data: {
          labels: orderStatusLabels,
          datasets: [{
            data: orderStatusData,
            backgroundColor: [
              '#3B82F6',
              '#F59E0B',
              '#10B981',
              '#EF4444',
              '#8B5CF6'
            ],
            borderColor: '#ffffff',
            borderWidth: 3,
            hoverOffset: 6
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          cutout: '65%',
          plugins: {
            legend: {
              position: 'bottom'
            },
            tooltip: {
              callbacks: {
                label: function(context) {
                  return context.label + ': ' + context.parsed + ' đơn';
                }
              }
            }
          }
        }
      });
    }

    console.log('Dashboard chart data:', {
      revenueLabels,
      revenueData,
      orderStatusLabels,
      orderStatusData
    });
  });
</script>

<?php require_once '/var/www/src/includes/admin/footer.php'; ?>