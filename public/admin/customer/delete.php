<?php
require_once '/var/www/src/config/session.php';
require_once '/var/www/src/config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: /admin/customer/');
  exit;
}

$customerID = isset($_POST['customer_id'])
  ? (int) $_POST['customer_id']
  : 0;

$csrfToken = $_POST['csrf_token'] ?? '';
$sessionToken = $_SESSION['customer_csrf'] ?? '';

if (
  $customerID <= 0 ||
  $csrfToken === '' ||
  $sessionToken === '' ||
  !hash_equals($sessionToken, $csrfToken)
) {
  $_SESSION['customer_message'] = 'Yêu cầu xóa không hợp lệ.';
  header('Location: /admin/customer/');
  exit;
}

$sql = "DELETE FROM customers WHERE CustomerID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $customerID);

if ($stmt->execute()) {
  if ($stmt->affected_rows > 0) {
    $_SESSION['customer_message'] = 'Xóa khách hàng thành công.';
  } else {
    $_SESSION['customer_message'] = 'Không tìm thấy khách hàng cần xóa.';
  }

  $stmt->close();
  $conn->close();

  header('Location: /admin/customer/');
  exit;
}

$error = $stmt->error;

$stmt->close();
$conn->close();

$_SESSION['customer_message'] =
  'Không thể xóa khách hàng. Có thể khách hàng đang có đơn hàng liên quan.';

error_log('Lỗi xóa khách hàng: ' . $error);

header('Location: /admin/customer/');
exit;
