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
  $sql = "
        SELECT ShipperID, ShipperName, Phone
        FROM shippers
        WHERE ShipperName LIKE ?
           OR Phone LIKE ?
        ORDER BY ShipperID ASC
    ";

  $stmt = $conn->prepare($sql);
  $search = '%' . $keyword . '%';
  $stmt->bind_param('ss', $search, $search);
  $stmt->execute();
  $shippers = $stmt->get_result();
} else {
  $shippers = $conn->query("
        SELECT ShipperID, ShipperName, Phone
        FROM shippers
        ORDER BY ShipperID ASC
    ");
}

if (!$shippers) {
  die('Không thể tải danh sách đơn vị vận chuyển.');
}

require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';
?>

<div class="container mt-4 mb-5">

  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">Quản lý đơn vị vận chuyển</h2>

    <a href="/admin/shipper/create.php" class="btn btn-primary">
      Thêm đơn vị vận chuyển
    </a>
  </div>

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

  <div class="card border-0 shadow-sm">
    <div class="card-body">

      <form method="GET" action="/admin/shipper/" class="row g-2 mb-4">
        <div class="col-md-6 col-lg-5">
          <input
            type="text"
            name="keyword"
            class="form-control"
            placeholder="Tìm theo tên hoặc số điện thoại..."
            value="<?= e($keyword) ?>">
        </div>

        <div class="col-auto">
          <button type="submit" class="btn btn-primary">
            Tìm kiếm
          </button>

          <a href="/admin/shipper/" class="btn btn-outline-secondary">
            Làm mới
          </a>
        </div>
      </form>

      <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle mb-0">
          <thead class="table-dark">
            <tr>
              <th style="width: 120px;">Mã shipper</th>
              <th>Tên đơn vị vận chuyển</th>
              <th>Số điện thoại</th>
              <th style="width: 180px;">Thao tác</th>
            </tr>
          </thead>

          <tbody>
            <?php if ($shippers->num_rows > 0): ?>
              <?php while ($shipper = $shippers->fetch_assoc()): ?>
                <tr>
                  <td>
                    <?= (int) $shipper['ShipperID'] ?>
                  </td>

                  <td>
                    <?= e($shipper['ShipperName']) ?>
                  </td>

                  <td>
                    <?= e($shipper['Phone'] ?? '') ?>
                  </td>

                  <td>
                    <a
                      href="/admin/shipper/edit.php?id=<?= (int) $shipper['ShipperID'] ?>"
                      class="btn btn-sm btn-warning">
                      Sửa
                    </a>

                    <form
                      method="POST"
                      action="/admin/shipper/delete.php"
                      class="d-inline"
                      onsubmit="return confirm('Bạn có chắc muốn xóa đơn vị vận chuyển này?');">

                      <input
                        type="hidden"
                        name="shipper_id"
                        value="<?= (int) $shipper['ShipperID'] ?>">

                      <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($_SESSION['shipper_csrf']) ?>">

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
                <td colspan="4" class="text-center py-4 text-muted">
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

<?php
if (isset($stmt)) {
  $stmt->close();
}

$shippers->free();

require_once '/var/www/src/includes/admin/footer.php';

$conn->close();
?>