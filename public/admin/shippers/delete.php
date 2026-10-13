<?php
require_once '/var/www/src/config/session.php';
require_once '/var/www/src/config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: /admin/shippers/');
  exit;
}

$id = filter_input(INPUT_POST, 'shipper_id', FILTER_VALIDATE_INT);
$token = $_POST['csrf_token'] ?? '';

if (
  !$id || $id < 1 ||
  empty($_SESSION['shipper_csrf']) ||
  !hash_equals($_SESSION['shipper_csrf'], $token)
) {
  $_SESSION['shipper_message'] = 'Yêu cầu không hợp lệ.';
  header('Location: /admin/shippers/');
  exit;
}

$stmt = $conn->prepare(
  "DELETE FROM shippers WHERE ShipperID = ?"
);
$stmt->bind_param('i', $id);

try {
  if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
      $_SESSION['shipper_message'] = 'Xóa đơn vị vận chuyển thành công.';
    } else {
      $_SESSION['shipper_message'] = 'Không tìm thấy đơn vị vận chuyển.';
    }
  } else {
    $_SESSION['shipper_message'] = 'Không thể xóa đơn vị vận chuyển.';
  }
} catch (mysqli_sql_exception $exception) {
  if ($exception->getCode() === 1451) {
    $_SESSION['shipper_message'] =
      'Không thể xóa shipper vì đang được sử dụng trong đơn hàng.';
  } else {
    $_SESSION['shipper_message'] = 'Đã xảy ra lỗi khi xóa shipper.';
  }
}

$stmt->close();
$conn->close();

header('Location: /admin/shippers/');
exit;
