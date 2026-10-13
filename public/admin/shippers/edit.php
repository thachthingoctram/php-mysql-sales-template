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

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
  header('Location: /admin/shippers/');
  exit;
}

$stmt = $conn->prepare(
  "SELECT ShipperName, Phone FROM shippers WHERE ShipperID = ?"
);
$stmt->bind_param('i', $id);
$stmt->execute();
$shipper = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$shipper) {
  $_SESSION['shipper_message'] = 'Không tìm thấy đơn vị vận chuyển.';
  header('Location: /admin/shippers/');
  exit;
}

$name = $shipper['ShipperName'];
$phone = $shipper['Phone'] ?? '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = trim($_POST['shipper_name'] ?? '');
  $phone = trim($_POST['phone'] ?? '');
  $token = $_POST['csrf_token'] ?? '';

  if (!hash_equals($_SESSION['shipper_csrf'], $token)) {
    $error = 'Yêu cầu không hợp lệ. Vui lòng tải lại trang.';
  } elseif ($name === '') {
    $error = 'Vui lòng nhập tên đơn vị vận chuyển.';
  } elseif (mb_strlen($name) > 100) {
    $error = 'Tên đơn vị vận chuyển không được vượt quá 100 ký tự.';
  } elseif (mb_strlen($phone) > 20) {
    $error = 'Số điện thoại không được vượt quá 20 ký tự.';
  } else {
    $stmt = $conn->prepare(
      "UPDATE shippers SET ShipperName = ?, Phone = ? WHERE ShipperID = ?"
    );
    $stmt->bind_param('ssi', $name, $phone, $id);

    if ($stmt->execute()) {
      $stmt->close();
      $_SESSION['shipper_message'] = 'Cập nhật đơn vị vận chuyển thành công.';
      header('Location: /admin/shippers/');
      exit;
    }

    $error = 'Không thể cập nhật đơn vị vận chuyển.';
    $stmt->close();
  }
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sửa shipper</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet">
</head>

<body class="bg-light">
  <div class="container py-5">
    <div class="card border-0 shadow-sm mx-auto" style="max-width: 650px;">
      <div class="card-body p-4">
        <h3 class="mb-4">Sửa đơn vị vận chuyển</h3>

        <?php if ($error !== ''): ?>
          <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST">
          <input type="hidden" name="csrf_token"
            value="<?= e($_SESSION['shipper_csrf']) ?>">

          <div class="mb-3">
            <label class="form-label">Mã shipper</label>
            <input type="text" class="form-control"
              value="<?= (int) $id ?>" disabled>
          </div>

          <div class="mb-3">
            <label class="form-label">Tên đơn vị vận chuyển *</label>
            <input type="text" name="shipper_name" class="form-control"
              maxlength="100" required value="<?= e($name) ?>">
          </div>

          <div class="mb-3">
            <label class="form-label">Số điện thoại</label>
            <input type="text" name="phone" class="form-control"
              maxlength="20" value="<?= e($phone) ?>">
          </div>

          <button type="submit" class="btn btn-primary">Cập nhật</button>
          <a href="index.php" class="btn btn-outline-secondary">Hủy</a>
        </form>
      </div>
    </div>
  </div>
</body>

</html>
<?php $conn->close(); ?>