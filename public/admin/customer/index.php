<?php
require_once '/var/www/src/config/session.php';
require_once '/var/www/src/config/database.php';

function e($value)
{
  return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$keyword = trim($_GET['keyword'] ?? '');
$message = $_SESSION['customer_message'] ?? '';
unset($_SESSION['customer_message']);

// Lấy danh sách khách hàng
if ($keyword !== '') {
  $sql = "
        SELECT
            CustomerID,
            CustomerName,
            Phone,
            Email,
            ContactName,
            Address,
            City,
            PostalCode,
            Country
        FROM customers
        WHERE CustomerName LIKE ?
            OR Phone LIKE ?
            OR Email LIKE ?
            OR ContactName LIKE ?
            OR City LIKE ?
        ORDER BY CustomerID ASC
    ";

  $stmt = $conn->prepare($sql);
  $search = '%' . $keyword . '%';

  $stmt->bind_param(
    'sssss',
    $search,
    $search,
    $search,
    $search,
    $search
  );

  $stmt->execute();
  $customers = $stmt->get_result();
} else {
  $sql = "
        SELECT
            CustomerID,
            CustomerName,
            Phone,
            Email,
            ContactName,
            Address,
            City,
            PostalCode,
            Country
        FROM customers
        ORDER BY CustomerID ASC
    ";

  $customers = $conn->query($sql);
}

if (!$customers) {
  die('Không thể tải danh sách khách hàng.');
}

require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';
?>

<div class="container py-4">

  <!-- Tiêu đề giống trang quản lý sản phẩm -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">Quản lý khách hàng</h2>
  </div>

  <!-- Thông báo -->
  <?php if ($message !== ''): ?>
    <div class="alert alert-info alert-dismissible fade show" role="alert">
      <?= e($message) ?>

      <button
        type="button"
        class="btn-close"
        data-bs-dismiss="alert"
        aria-label="Đóng">
      </button>
    </div>
  <?php endif; ?>

  <!-- Thanh tìm kiếm -->
  <form method="GET" action="index.php" class="row g-2 mb-3">
    <div class="col-md-6 col-lg-5">
      <input
        type="text"
        name="keyword"
        class="form-control"
        placeholder="Tìm theo tên, số điện thoại, email hoặc thành phố..."
        value="<?= e($keyword) ?>">
    </div>

    <div class="col-auto">
      <button type="submit" class="btn btn-primary">
        Tìm kiếm
      </button>

      <a href="index.php" class="btn btn-outline-secondary">
        Làm mới
      </a>
    </div>
  </form>

  <!-- Bảng khách hàng -->
  <div class="table-responsive">
    <table class="table table-bordered table-striped align-middle">
      <thead class="table-dark">
        <tr>
          <th>Mã KH</th>
          <th>Tên khách hàng</th>
          <th>Số điện thoại</th>
          <th>Email</th>
          <th>Người liên hệ</th>
          <th>Địa chỉ</th>
          <th>Thành phố</th>
          <th>Mã bưu điện</th>
          <th>Quốc gia</th>
          <th style="min-width: 130px;">Thao tác</th>
        </tr>
      </thead>

      <tbody>
        <?php if ($customers->num_rows > 0): ?>
          <?php while ($customer = $customers->fetch_assoc()): ?>
            <tr>
              <td><?= (int) $customer['CustomerID'] ?></td>

              <td><?= e($customer['CustomerName']) ?></td>

              <td><?= e($customer['Phone']) ?></td>

              <td><?= e($customer['Email']) ?></td>

              <td><?= e($customer['ContactName']) ?></td>

              <td><?= e($customer['Address']) ?></td>

              <td><?= e($customer['City']) ?></td>

              <td><?= e($customer['PostalCode']) ?></td>

              <td><?= e($customer['Country']) ?></td>

              <td>
                <a
                  href="edit.php?id=<?= (int) $customer['CustomerID'] ?>"
                  class="btn btn-sm btn-warning">
                  Sửa
                </a>

                <form
                  method="POST"
                  action="delete.php"
                  class="d-inline"
                  onsubmit="return confirm('Bạn có chắc muốn xóa khách hàng này?');">

                  <input
                    type="hidden"
                    name="customer_id"
                    value="<?= (int) $customer['CustomerID'] ?>">

                  <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($_SESSION['customer_csrf'] ?? '') ?>">

                  <button
                    type="submit"
                    class="btn btn-sm btn-danger">
                    Xóa
                  </button>
                </form>
              </td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr>
            <td colspan="10" class="text-center">
              Không tìm thấy khách hàng.
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <p class="text-muted small">
    Mật khẩu không được hiển thị trong danh sách khách hàng.
  </p>
</div>

<?php
$customers->free();

if (isset($stmt)) {
  $stmt->close();
}

require_once '/var/www/src/includes/admin/footer.php';

$conn->close();
?>