<?php
require_once '/var/www/src/config/session.php';
require_once '/var/www/src/config/database.php';

function e($value)
{
  return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
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


<!DOCTYPE html>
<html lang="vi">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <title>Quản lý khách hàng</title>

  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet">
</head>

<body class="bg-light">

  <div class="container-fluid py-4 px-4">

    <!-- Tiêu đề -->
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h2 class="mb-1">Quản lý khách hàng</h2>
        <p class="text-muted mb-0">
          Danh sách khách hàng đã đăng ký tài khoản và thông tin liên hệ
        </p>
      </div>
    </div>

    <!-- Thông báo -->
    <?php if ($message !== ''): ?>
      <div class="alert alert-info alert-dismissible fade show">
        <?= e($message) ?>

        <button
          type="button"
          class="btn-close"
          data-bs-dismiss="alert"
          aria-label="Đóng">
        </button>
      </div>
    <?php endif; ?>

    <!-- Danh sách -->
    <div class="card border-0 shadow-sm">
      <div class="card-body">

        <!-- Tìm kiếm -->
        <form method="GET" action="index.php" class="row g-2 mb-4">
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
          <table class="table table-bordered table-hover align-middle">

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
                <th style="min-width: 150px;">Thao tác</th>
              </tr>
            </thead>

            <tbody>
              <?php if ($customers->num_rows > 0): ?>

                <?php while ($customer = $customers->fetch_assoc()): ?>
                  <tr>
                    <td><?= e($customer['CustomerID']) ?></td>

                    <td><?= e($customer['CustomerName']) ?></td>

                    <td><?= e($customer['Phone'] ?? '') ?></td>

                    <td><?= e($customer['Email'] ?? '') ?></td>

                    <td><?= e($customer['ContactName'] ?? '') ?></td>

                    <td><?= e($customer['Address'] ?? '') ?></td>

                    <td><?= e($customer['City'] ?? '') ?></td>

                    <td><?= e($customer['PostalCode'] ?? '') ?></td>

                    <td><?= e($customer['Country'] ?? '') ?></td>

                    <td>
                      <!-- Sửa -->
                      <a
                        href="edit.php?id=<?= (int) $customer['CustomerID'] ?>"
                        class="btn btn-sm btn-warning">
                        Sửa
                      </a>

                      <!-- Xóa: giữ nguyên tên trường của form hiện tại -->
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
                  <td colspan="10" class="text-center py-4">
                    Không tìm thấy khách hàng.
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>

          </table>
        </div>

        <p class="text-muted small mb-0">
          Mật khẩu không được hiển thị trong danh sách khách hàng.
        </p>

      </div>
    </div>

  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>

<?php
if (isset($stmt)) {
  $stmt->close();
}
require_once '/var/www/src/includes/admin/footer.php';
$conn->close();
?>