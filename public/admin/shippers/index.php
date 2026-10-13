<?php
require_once '/var/www/src/config/session.php';
require_once '/var/www/src/config/database.php';

function e($value)
{
  return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['shipper_csrf'])) {
  $_SESSION['shipper_csrf'] = bin2hex(random_bytes(32));
}

$keyword = trim($_GET['keyword'] ?? '');
$message = $_SESSION['shipper_message'] ?? '';
unset($_SESSION['shipper_message']);

if ($keyword !== '') {
  $stmt = $conn->prepare(
    "SELECT ShipperID, ShipperName, Phone
         FROM shippers
         WHERE ShipperName LIKE ? OR Phone LIKE ?
         ORDER BY ShipperID ASC"
  );

  $search = '%' . $keyword . '%';
  $stmt->bind_param('ss', $search, $search);
  $stmt->execute();
  $shippers = $stmt->get_result();
} else {
  $shippers = $conn->query(
    "SELECT ShipperID, ShipperName, Phone
         FROM shippers
         ORDER BY ShipperID ASC"
  );
}

if (!$shippers) {
  die('Không thể tải danh sách đơn vị vận chuyển.');
}
require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';
?>
<!DOCTYPE html>
<html lang="vi">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Quản lý đơn vị vận chuyển</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet">
</head>

<body class="bg-light">
  <div class="container-fluid py-4 px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h2>Quản lý đơn vị vận chuyển</h2>
        <p class="text-muted mb-0">Danh sách các đơn vị vận chuyển</p>
      </div>
      <div>
        <a href="./create.php" class="btn btn-primary">+ Thêm shipper</a>
      </div>
    </div>

    <?php if ($message !== ''): ?>
      <div class="alert alert-info alert-dismissible fade show">
        <?= e($message) ?>
        <button type="button" class="btn-close"
          data-bs-dismiss="alert" aria-label="Đóng"></button>
      </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <form method="GET" action="index.php" class="row g-2 mb-4">
          <div class="col-md-5">
            <input type="text" name="keyword" class="form-control"
              placeholder="Tìm theo tên hoặc số điện thoại..."
              value="<?= e($keyword) ?>">
          </div>
          <div class="col-auto">
            <button type="submit" class="btn btn-primary">Tìm kiếm</button>
            <a href="index.php" class="btn btn-outline-secondary">Làm mới</a>
          </div>
        </form>

        <div class="table-responsive">
          <table class="table table-bordered table-hover align-middle">
            <thead class="table-dark">
              <tr>
                <th>Mã shipper</th>
                <th>Tên đơn vị vận chuyển</th>
                <th>Số điện thoại</th>
                <th style="min-width: 160px;">Thao tác</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($shippers->num_rows > 0): ?>
                <?php while ($shipper = $shippers->fetch_assoc()): ?>
                  <tr>
                    <td><?= (int) $shipper['ShipperID'] ?></td>
                    <td><?= e($shipper['ShipperName']) ?></td>
                    <td><?= e($shipper['Phone'] ?? '') ?></td>
                    <td>
                      <a href="edit.php?id=<?= (int) $shipper['ShipperID'] ?>"
                        class="btn btn-sm btn-warning">Sửa</a>

                      <form method="POST" action="delete.php"
                        class="d-inline"
                        onsubmit="return confirm('Bạn có chắc muốn xóa shipper này?');">
                        <input type="hidden" name="shipper_id"
                          value="<?= (int) $shipper['ShipperID'] ?>">
                        <input type="hidden" name="csrf_token"
                          value="<?= e($_SESSION['shipper_csrf']) ?>">
                        <button type="submit" class="btn btn-sm btn-danger">
                          Xóa
                        </button>
                      </form>
                    </td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr>
                  <td colspan="4" class="text-center py-4">
                    Không tìm thấy đơn vị vận chuyển.
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
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