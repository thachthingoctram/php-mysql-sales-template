<?php
require_once '/var/www/src/config/session.php';
require_once '/var/www/src/config/database.php';

$pageTitle = 'Dashboard Admin';

// Đồng bộ múi giờ Việt Nam để "hôm nay", "tháng này" tính đúng
// (MySQL trong Docker mặc định chạy giờ UTC).
$conn->query("SET time_zone = '+07:00'");

function e($value): string
{
  return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function money($value): string
{
  return number_format((float)$value, 0, ',', '.') . ' đ';
}

function dateVN(?string $value): string
{
  if (!$value) {
    return '';
  }

  $time = strtotime($value);
  return $time ? date('d/m/Y', $time) : e($value);
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
   NGÀY HIỆN TẠI (theo MySQL)
========================= */
$todayResult = $conn->query('SELECT CURDATE()');
$today = $todayResult ? (string)$todayResult->fetch_row()[0] : date('Y-m-d');

/* =========================
   THỐNG KÊ TỔNG QUAN
========================= */
$totalProducts = getCount($conn, "SELECT COUNT(*) FROM products");
$activeProducts = getCount($conn, "SELECT COUNT(*) FROM products WHERE IsActive = 1");
$totalCustomers = getCount($conn, "SELECT COUNT(*) FROM customers");
$totalOrders = getCount($conn, "SELECT COUNT(*) FROM orders");
$newOrders = getCount($conn, "SELECT COUNT(*) FROM orders WHERE Status = 'Pending'");

/* =========================
   DOANH THU
   Không tính đơn đã hủy (đơn hủy không tạo ra doanh thu).
   Trang chi tiết đơn hàng coi 3 giá trị dưới đây là "đã hủy".
========================= */
$notCancelled = "COALESCE(o.Status, '') NOT IN ('Cancelled', 'Canceled', 'Đã hủy')";

$sqlRevenue = "
    SELECT
        COALESCE(SUM(od.Quantity * od.UnitPrice), 0) AS TotalRevenue,

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
    INNER JOIN orderdetail od ON od.OrderID = o.OrderID
    WHERE {$notCancelled}
";

$totalRevenue = 0;
$todayRevenue = 0;
$monthRevenue = 0;

$resultRevenue = $conn->query($sqlRevenue);

if ($resultRevenue) {
  $revenue = $resultRevenue->fetch_assoc();
  $totalRevenue = (float)($revenue['TotalRevenue'] ?? 0);
  $todayRevenue = (float)($revenue['TodayRevenue'] ?? 0);
  $monthRevenue = (float)($revenue['MonthRevenue'] ?? 0);
} else {
  error_log('Dashboard revenue query error: ' . $conn->error);
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
    ORDER BY StockQuantity ASC, ProductName ASC
    LIMIT 6
";

$lowStockProducts = $conn->query($sqlLowStock);

if (!$lowStockProducts) {
  error_log('Dashboard low stock query error: ' . $conn->error);
}

/* =========================
   BIỂU ĐỒ DOANH THU 7 NGÀY GẦN NHẤT
========================= */
$sqlChart = "
    SELECT
        DATE(o.OrderDate) AS RevenueDate,
        SUM(od.Quantity * od.UnitPrice) AS Revenue
    FROM orders o
    INNER JOIN orderdetail od ON od.OrderID = o.OrderID
    WHERE o.OrderDate >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
      AND o.OrderDate < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
      AND {$notCancelled}
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
  $date = date('Y-m-d', strtotime("{$today} -{$i} days"));
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

// Class badge và màu biểu đồ cùng key với $statusLabels
// để màu trên biểu đồ luôn khớp với màu badge trong bảng.
$statusClasses = [
  'Pending'   => 'st-pending',
  'Confirmed' => 'st-confirmed',
  'Shipping'  => 'st-shipping',
  'Completed' => 'st-completed',
  'Cancelled' => 'st-cancelled'
];

$statusColors = [
  'Pending'   => '#D97706',
  'Confirmed' => '#2563EB',
  'Shipping'  => '#7C3AED',
  'Completed' => '#059669',
  'Cancelled' => '#DC2626'
];

// Một truy vấn GROUP BY thay vì 5 truy vấn COUNT riêng lẻ.
$statusCounts = [];
$statusResult = $conn->query("SELECT Status, COUNT(*) AS Total FROM orders GROUP BY Status");

if ($statusResult) {
  while ($row = $statusResult->fetch_assoc()) {
    $statusCounts[(string)$row['Status']] = (int)$row['Total'];
  }
} else {
  error_log('Dashboard status query error: ' . $conn->error);
}

$statusValues = [];

foreach ($statusLabels as $status => $label) {
  $statusValues[] = $statusCounts[$status] ?? 0;
}

/* Header chỉ mở HTML và tải Bootstrap; navbar được include ngay sau đó */
require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>

<style>
  .db {
    --db-ink: #16212B;
    --db-muted: #5F6B78;
    --db-line: #E3E8ED;
    --db-surface: #FFFFFF;
    --db-brand: #0E7C66;
    --db-brand-dark: #0A5F4F;
    color: var(--db-ink);
    font-variant-numeric: tabular-nums;
  }

  /* Tiêu đề trang */
  .db-title {
    font-size: 1.65rem;
    font-weight: 700;
    letter-spacing: -.02em;
    margin: 0 0 .25rem;
  }

  .db-subtitle {
    color: var(--db-muted);
    margin: 0;
  }

  .db-date {
    display: inline-flex;
    align-items: center;
    gap: .45rem;
    padding: .5rem .85rem;
    border: 1px solid var(--db-line);
    border-radius: 999px;
    background: var(--db-surface);
    color: var(--db-muted);
    font-size: .875rem;
    font-weight: 500;
  }

  /* Khung chung của thẻ / panel */
  .db-card {
    height: 100%;
    border: 1px solid var(--db-line);
    border-radius: 14px;
    background: var(--db-surface);
    box-shadow: 0 1px 2px rgba(22, 33, 43, .04);
  }

  .db-card-body {
    padding: 1.4rem 1.5rem;
  }

  /* Thẻ thống kê */
  .db-stat-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1rem;
    color: var(--db-muted);
    font-size: .9rem;
    font-weight: 500;
  }

  .db-stat-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 42px;
    height: 42px;
    border-radius: 12px;
    font-size: 1.2rem;
  }

  .db-stat-value {
    margin: 0 0 .3rem;
    font-size: 1.85rem;
    font-weight: 700;
    letter-spacing: -.02em;
    line-height: 1.15;
  }

  .db-stat-note {
    display: block;
    color: var(--db-muted);
    font-size: .83rem;
  }

  .tone-blue   { background: #E5EEFF; color: #2150C8; }
  .tone-green  { background: #DDF3EA; color: #0B7A55; }
  .tone-amber  { background: #FDEFD2; color: #A5620A; }

  /* Thẻ doanh thu: điểm nhấn duy nhất của trang */
  .db-stat-featured {
    border-color: var(--db-brand-dark);
    background: var(--db-brand);
    color: #fff;
  }

  .db-stat-featured .db-stat-head,
  .db-stat-featured .db-stat-note {
    color: rgba(255, 255, 255, .82);
  }

  .db-stat-featured .db-stat-icon {
    background: rgba(255, 255, 255, .16);
    color: #fff;
  }

  .db-stat-featured .db-stat-split {
    margin-top: .85rem;
    padding-top: .75rem;
    border-top: 1px solid rgba(255, 255, 255, .22);
  }

  .db-stat-featured .db-stat-split span {
    display: flex;
    justify-content: space-between;
    gap: .5rem;
    font-size: .83rem;
    color: rgba(255, 255, 255, .9);
  }

  /* Tiêu đề panel */
  .db-panel-title {
    margin: 0 0 .2rem;
    font-size: 1.05rem;
    font-weight: 700;
    letter-spacing: -.01em;
  }

  .db-panel-desc {
    margin: 0;
    color: var(--db-muted);
    font-size: .85rem;
  }

  .db-chart {
    position: relative;
    height: 300px;
    margin-top: 1.25rem;
  }

  .db-empty {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: var(--db-muted);
    font-size: .9rem;
    text-align: center;
  }

  /* Bảng */
  .db .table {
    margin-bottom: 0;
    --bs-table-hover-bg: #F6F8FA;
  }

  .db .table thead th {
    padding: .8rem 1rem;
    border-bottom: 1px solid var(--db-line);
    background: #F6F8FA;
    color: var(--db-muted);
    font-size: .8rem;
    font-weight: 600;
    white-space: nowrap;
  }

  .db .table tbody td {
    padding: .95rem 1rem;
    border-color: #EEF1F4;
    vertical-align: middle;
  }

  .db .table-wrap {
    margin: 1.1rem -1.5rem -1.4rem;
    overflow-x: auto;
  }

  .db .table th:first-child,
  .db .table td:first-child {
    padding-left: 1.5rem;
  }

  .db .table th:last-child,
  .db .table td:last-child {
    padding-right: 1.5rem;
  }

  .db .table .num {
    text-align: right;
  }

  .db a.db-link {
    color: var(--db-ink);
    font-weight: 600;
    text-decoration: none;
  }

  .db a.db-link:hover,
  .db a.db-link:focus-visible {
    color: var(--db-brand);
    text-decoration: underline;
  }

  .db .btn-db {
    padding: .4rem .85rem;
    border: 1px solid var(--db-line);
    border-radius: 8px;
    background: var(--db-surface);
    color: var(--db-ink);
    font-size: .85rem;
    font-weight: 600;
  }

  .db .btn-db:hover {
    border-color: var(--db-brand);
    color: var(--db-brand);
  }

  /* Badge trạng thái */
  .db-badge {
    display: inline-block;
    padding: .3rem .7rem;
    border-radius: 999px;
    font-size: .78rem;
    font-weight: 600;
    white-space: nowrap;
  }

  .st-pending   { background: #FEF3C7; color: #92400E; }
  .st-confirmed { background: #DBEAFE; color: #1E40AF; }
  .st-shipping  { background: #EDE9FE; color: #5B21B6; }
  .st-completed { background: #D1FAE5; color: #065F46; }
  .st-cancelled { background: #FEE2E2; color: #991B1B; }
  .st-default   { background: #E8ECF0; color: #3B4754; }

  .db-muted {
    color: var(--db-muted);
  }

  @media (max-width: 768px) {
    .db-title {
      font-size: 1.4rem;
    }

    .db-chart {
      height: 260px;
    }

    .db-card-body {
      padding: 1.15rem;
    }

    .db .table-wrap {
      margin: 1rem -1.15rem -1.15rem;
    }
  }
</style>

<main class="container db py-4 py-lg-5">

  <!-- Tiêu đề -->
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
      <h1 class="db-title">Dashboard</h1>
      <p class="db-subtitle">
        Tổng quan hoạt động kinh doanh của cửa hàng
      </p>
    </div>

    <span class="db-date">
      <i class="bi bi-calendar3"></i>
      <?= e(date('d/m/Y', strtotime($today))) ?>
    </span>
  </div>

  <!-- Thẻ thống kê -->
  <div class="row g-3 g-lg-4 mb-4">

    <div class="col-12 col-sm-6 col-xl-3">
      <div class="db-card">
        <div class="db-card-body">
          <div class="db-stat-head">
            <span>Tổng sản phẩm</span>
            <div class="db-stat-icon tone-blue">
              <i class="bi bi-box-seam"></i>
            </div>
          </div>
          <p class="db-stat-value"><?= number_format($totalProducts) ?></p>
          <span class="db-stat-note">
            <?= number_format($activeProducts) ?> sản phẩm đang hoạt động
          </span>
        </div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
      <div class="db-card">
        <div class="db-card-body">
          <div class="db-stat-head">
            <span>Đơn hàng</span>
            <div class="db-stat-icon tone-green">
              <i class="bi bi-receipt"></i>
            </div>
          </div>
          <p class="db-stat-value"><?= number_format($totalOrders) ?></p>
          <span class="db-stat-note">
            <?= number_format($newOrders) ?> đơn đang chờ xử lý
          </span>
        </div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
      <div class="db-card">
        <div class="db-card-body">
          <div class="db-stat-head">
            <span>Khách hàng</span>
            <div class="db-stat-icon tone-amber">
              <i class="bi bi-people"></i>
            </div>
          </div>
          <p class="db-stat-value"><?= number_format($totalCustomers) ?></p>
          <span class="db-stat-note">Khách hàng trong hệ thống</span>
        </div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
      <div class="db-card db-stat-featured">
        <div class="db-card-body">
          <div class="db-stat-head">
            <span>Tổng giá trị đơn hàng</span>
            <div class="db-stat-icon">
              <i class="bi bi-cash-stack"></i>
            </div>
          </div>
          <p class="db-stat-value"><?= money($totalRevenue) ?></p>
          <span class="db-stat-note">Không tính đơn đã hủy</span>
          <div class="db-stat-split">
            <span><span>Hôm nay</span> <strong><?= money($todayRevenue) ?></strong></span>
            <span><span>Tháng này</span> <strong><?= money($monthRevenue) ?></strong></span>
          </div>
        </div>
      </div>
    </div>

  </div>

  <!-- Biểu đồ -->
  <div class="row g-3 g-lg-4 mb-4">

    <div class="col-12 col-xl-8">
      <div class="db-card">
        <div class="db-card-body">
          <h2 class="db-panel-title">Doanh thu</h2>
          <p class="db-panel-desc">
            Tổng giá trị đơn hàng (không tính đơn đã hủy) theo ngày trong 7 ngày gần nhất
          </p>
          <div class="db-chart">
            <canvas id="revenueChart"></canvas>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-xl-4">
      <div class="db-card">
        <div class="db-card-body">
          <h2 class="db-panel-title">Trạng thái đơn hàng</h2>
          <p class="db-panel-desc">
            Thống kê số lượng đơn theo trạng thái
          </p>
          <div class="db-chart">
            <canvas id="orderStatusChart"></canvas>
          </div>
        </div>
      </div>
    </div>

  </div>

  <!-- Đơn hàng gần đây -->
  <div class="db-card mb-4">
    <div class="db-card-body">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
          <h2 class="db-panel-title">Đơn hàng gần đây</h2>
          <p class="db-panel-desc">Theo dõi các đơn hàng mới nhất</p>
        </div>

        <a href="/admin/orders/" class="btn btn-db">Xem tất cả</a>
      </div>

      <div class="table-wrap">
        <table class="table align-middle table-hover">
          <thead>
            <tr>
              <th>Mã đơn</th>
              <th>Khách hàng</th>
              <th>Ngày đặt</th>
              <th class="num">Tổng tiền</th>
              <th>Trạng thái</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($recentOrders && $recentOrders->num_rows > 0): ?>
              <?php while ($order = $recentOrders->fetch_assoc()): ?>
                <?php
                $status = (string)($order['Status'] ?? '');
                $statusLabel = $statusLabels[$status] ?? $status;
                $statusClass = $statusClasses[$status] ?? 'st-default';
                ?>
                <tr>
                  <td>
                    <a class="db-link"
                      href="/admin/orders/detail.php?id=<?= (int)$order['OrderID'] ?>">
                      #<?= e($order['OrderID']) ?>
                    </a>
                  </td>
                  <td>
                    <div class="fw-semibold">
                      <?= e($order['CustomerName'] ?? 'Khách hàng') ?>
                    </div>
                    <small class="db-muted">
                      <?= e($order['Phone'] ?? '') ?>
                    </small>
                  </td>
                  <td><?= dateVN($order['OrderDate'] ?? '') ?></td>
                  <td class="num fw-semibold">
                    <?= money($order['OrderTotal'] ?? 0) ?>
                  </td>
                  <td>
                    <span class="db-badge <?= e($statusClass) ?>">
                      <?= e($statusLabel !== '' ? $statusLabel : 'Chưa xác định') ?>
                    </span>
                  </td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr>
                <td colspan="5" class="text-center db-muted py-4">
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
  <div class="db-card">
    <div class="db-card-body">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
          <h2 class="db-panel-title">Sản phẩm sắp hết hàng</h2>
          <p class="db-panel-desc">
            Các sản phẩm cần được kiểm tra và bổ sung tồn kho
          </p>
        </div>

        <a href="/admin/products/" class="btn btn-db">Xem tất cả</a>
      </div>

      <div class="table-wrap">
        <table class="table align-middle table-hover">
          <thead>
            <tr>
              <th>Mã sản phẩm</th>
              <th>Tên sản phẩm</th>
              <th class="num">Số lượng tồn</th>
              <th>Tình trạng</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($lowStockProducts && $lowStockProducts->num_rows > 0): ?>
              <?php while ($product = $lowStockProducts->fetch_assoc()): ?>
                <?php $stock = (int)($product['StockQuantity'] ?? 0); ?>
                <tr>
                  <td class="db-muted"><?= e($product['ProductCode'] ?? '') ?></td>
                  <td>
                    <a class="db-link"
                      href="/admin/products/edit.php?id=<?= (int)$product['ProductID'] ?>">
                      <?= e($product['ProductName'] ?? '') ?>
                    </a>
                  </td>
                  <td class="num fw-semibold"><?= number_format($stock) ?></td>
                  <td>
                    <?php if ($stock <= 0): ?>
                      <span class="db-badge st-cancelled">Hết hàng</span>
                    <?php else: ?>
                      <span class="db-badge st-pending">Sắp hết hàng</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr>
                <td colspan="4" class="text-center db-muted py-4">
                  Không có sản phẩm sắp hết hàng.
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</main>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    if (typeof Chart === 'undefined') {
      console.error('Không tải được Chart.js. Hãy kiểm tra kết nối mạng.');
      return;
    }

    const revenueLabels = <?= json_encode($chartLabels, JSON_UNESCAPED_UNICODE) ?>;
    const revenueRaw = <?= json_encode($chartValues) ?>;
    const revenueData = revenueRaw.map(v => Math.round(v / 10000) / 100); // triệu đồng

    const statusLabels = <?= json_encode(array_values($statusLabels), JSON_UNESCAPED_UNICODE) ?>;
    const statusData = <?= json_encode($statusValues) ?>;
    const statusColors = <?= json_encode(array_values($statusColors)) ?>;

    const ink = '#16212B';
    const muted = '#5F6B78';
    const grid = '#E9EDF1';
    const brand = '#0E7C66';
    const vnd = new Intl.NumberFormat('vi-VN');

    Chart.defaults.font.family = "'Be Vietnam Pro', system-ui, sans-serif";
    Chart.defaults.color = muted;

    const tooltipStyle = {
      backgroundColor: ink,
      padding: 10,
      cornerRadius: 8,
      titleFont: {
        weight: '600'
      },
      displayColors: false
    };

    /* ----- Doanh thu 7 ngày ----- */
    const revenueCanvas = document.getElementById('revenueChart');

    if (revenueCanvas) {
      const ctx = revenueCanvas.getContext('2d');
      const fill = ctx.createLinearGradient(0, 0, 0, 300);
      fill.addColorStop(0, 'rgba(14, 124, 102, 0.22)');
      fill.addColorStop(1, 'rgba(14, 124, 102, 0)');

      new Chart(revenueCanvas, {
        type: 'line',
        data: {
          labels: revenueLabels,
          datasets: [{
            label: 'Doanh thu',
            data: revenueData,
            borderColor: brand,
            backgroundColor: fill,
            fill: true,
            tension: 0.35,
            borderWidth: 2.5,
            pointRadius: 3.5,
            pointHoverRadius: 6,
            pointBackgroundColor: '#fff',
            pointBorderColor: brand,
            pointBorderWidth: 2
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          interaction: {
            mode: 'index',
            intersect: false
          },
          plugins: {
            legend: {
              display: false
            },
            tooltip: {
              ...tooltipStyle,
              callbacks: {
                label: ctx => vnd.format(revenueRaw[ctx.dataIndex]) + ' đ'
              }
            }
          },
          scales: {
            x: {
              grid: {
                display: false
              },
              border: {
                display: false
              }
            },
            y: {
              beginAtZero: true,
              grid: {
                color: grid
              },
              border: {
                display: false
              },
              ticks: {
                maxTicksLimit: 6,
                callback: value => value === 0 ? '0' : value + ' tr'
              }
            }
          }
        }
      });
    }

    /* ----- Trạng thái đơn hàng ----- */
    const statusCanvas = document.getElementById('orderStatusChart');

    if (statusCanvas) {
      const total = statusData.reduce((a, b) => a + b, 0);

      if (total === 0) {
        statusCanvas.parentElement.innerHTML =
          '<div class="db-empty">Chưa có đơn hàng để thống kê.</div>';
        return;
      }

      new Chart(statusCanvas, {
        type: 'doughnut',
        data: {
          labels: statusLabels,
          datasets: [{
            data: statusData,
            backgroundColor: statusColors,
            borderColor: '#ffffff',
            borderWidth: 3,
            hoverOffset: 4
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          cutout: '68%',
          plugins: {
            legend: {
              position: 'bottom',
              labels: {
                usePointStyle: true,
                pointStyle: 'circle',
                boxWidth: 8,
                padding: 14
              }
            },
            tooltip: {
              ...tooltipStyle,
              callbacks: {
                label: ctx => ctx.label + ': ' + ctx.parsed + ' đơn'
              }
            }
          }
        }
      });
    }
  });
</script>

<?php require_once '/var/www/src/includes/admin/footer.php'; ?>
