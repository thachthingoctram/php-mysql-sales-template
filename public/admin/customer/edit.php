<?php
require_once '/var/www/src/config/session.php';
require_once '/var/www/src/config/database.php';

function e($value)
{
  return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['customer_csrf'])) {
  $_SESSION['customer_csrf'] = bin2hex(random_bytes(32));
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
  $_SESSION['customer_message'] = 'Mã khách hàng không hợp lệ.';
  header('Location: index.php');
  exit;
}

// Lấy thông tin khách hàng
$stmt = $conn->prepare(
  'SELECT CustomerID, CustomerName, ContactName,Phone, Email, Address,
            City, PostalCode, Country
     FROM customers
     WHERE CustomerID = ?'
);
$stmt->bind_param('i', $id);
$stmt->execute();

$customer = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$customer) {
  $_SESSION['customer_message'] = 'Không tìm thấy khách hàng.';
  header('Location: index.php');
  exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = $_POST['csrf_token'] ?? '';

  if (
    !is_string($token) ||
    !hash_equals($_SESSION['customer_csrf'], $token)
  ) {
    $error = 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.';
  } else {
    $customerName = trim($_POST['CustomerName'] ?? '');
    $contactName  = trim($_POST['ContactName'] ?? '');
    $phone        = trim($_POST['Phone']);
    $email        = trim($_POST['Email']);
    $address      = trim($_POST['Address'] ?? '');
    $city         = trim($_POST['City'] ?? '');
    $postalCode   = trim($_POST['PostalCode'] ?? '');
    $country      = trim($_POST['Country'] ?? '');

    if ($customerName === '') {
      $error = 'Tên khách hàng không được để trống.';
    } else {
      $stmt = $conn->prepare(
        'UPDATE customers
                 SET CustomerName = ?,
                     ContactName = ?,
                     Phone = ?,
                     Email = ?,
                     Address = ?,
                     City = ?,
                     PostalCode = ?,
                     Country = ?
                 WHERE CustomerID = ?'
      );

      $stmt->bind_param(
        'ssssssssi',
        $customerName,
        $contactName,
        $phone,
        $email,
        $address,
        $city,
        $postalCode,
        $country,
        $id
      );

      if ($stmt->execute()) {
        $_SESSION['customer_message'] =
          'Cập nhật khách hàng thành công.';
        $stmt->close();

        header('Location: index.php');
        exit;
      }

      $error = 'Không thể cập nhật khách hàng. Vui lòng thử lại.';
      $stmt->close();
    }

    // Giữ dữ liệu đã nhập nếu có lỗi
    $customer['CustomerName'] = $customerName;
    $customer['ContactName'] = $contactName;
    $customer['Phone'] = $email;
    $customer['Email'] = $email;
    $customer['Address'] = $address;
    $customer['City'] = $city;
    $customer['PostalCode'] = $postalCode;
    $customer['Country'] = $country;
  }
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sửa khách hàng</title>
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet">
</head>

<body class="bg-light">
  <div class="container py-4">
    <div class="card shadow-sm mx-auto" style="max-width: 800px;">
      <div class="card-header bg-primary text-white">
        <h4 class="mb-0">Sửa thông tin khách hàng</h4>
      </div>

      <div class="card-body">
        <?php if ($error !== ''): ?>
          <div class="alert alert-danger">
            <?= e($error) ?>
          </div>
        <?php endif; ?>

        <form method="POST"
          action="edit.php?id=<?= (int)$id ?>">
          <input
            type="hidden"
            name="csrf_token"
            value="<?= e($_SESSION['customer_csrf']) ?>">

          <div class="mb-3">
            <label class="form-label">Mã khách hàng</label>
            <input
              class="form-control"
              value="<?= (int)$customer['CustomerID'] ?>"
              disabled>
          </div>

          <?php
          $fields = [
            'CustomerName' => 'Tên khách hàng',
            'ContactName'  => 'Tên người liên hệ',
            'Phone'        => 'Điện thoại',
            'Email'        => 'Email',
            'Address'      => 'Địa chỉ',
            'City'         => 'Thành phố',
            'PostalCode'   => 'Mã bưu điện',
            'Country'      => 'Quốc gia'
          ];

          foreach ($fields as $field => $label):
          ?>
            <div class="mb-3">
              <label class="form-label">
                <?= e($label) ?>
                <?php if ($field === 'CustomerName'): ?>
                  <span class="text-danger">*</span>
                <?php endif; ?>
              </label>

              <input
                type="text"
                name="<?= e($field) ?>"
                class="form-control"
                value="<?= e(
                          $_POST[$field]
                            ?? $customer[$field]
                            ?? ''
                        ) ?>"
                <?= $field === 'CustomerName'
                  ? 'required' : '' ?>>
            </div>
          <?php endforeach; ?>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">
              Lưu thay đổi
            </button>

            <a href="index.php" class="btn btn-secondary">
              Quay lại
            </a>
          </div>
        </form>
      </div>
    </div>
  </div>
</body>

</html>