<?php

require_once '/var/www/src/config/session.php';
require_once '/var/www/src/config/database.php';

$pageTitle = 'Đăng ký tài khoản';

$customerName = '';
$phone = '';
$email = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  $customerName =
    trim($_POST['customer_name'] ?? '');

  $phone =
    trim($_POST['phone'] ?? '');

  $email =
    trim($_POST['email'] ?? '');

  $password =
    $_POST['password'] ?? '';

  $confirmPassword =
    $_POST['confirm_password'] ?? '';

  if (
    $customerName === ''
    || $email === ''
    || $password === ''
    || $confirmPassword === ''
  ) {

    $errorMessage =
      'Vui lòng nhập đầy đủ thông tin bắt buộc.';
  } elseif (!filter_var(
    $email,
    FILTER_VALIDATE_EMAIL
  )) {

    $errorMessage =
      'Email không hợp lệ.';
  } elseif (strlen($password) < 6) {

    $errorMessage =
      'Mật khẩu phải có ít nhất 6 ký tự.';
  } elseif ($password !== $confirmPassword) {

    $errorMessage =
      'Mật khẩu xác nhận không khớp.';
  } else {

    $sqlCheck = "
            SELECT CustomerID
            FROM customers
            WHERE Email = ?
        ";

    $stmtCheck =
      $conn->prepare($sqlCheck);

    $stmtCheck->bind_param(
      's',
      $email
    );

    $stmtCheck->execute();

    $resultCheck =
      $stmtCheck->get_result();

    $existingCustomer =
      $resultCheck->fetch_assoc();

    $resultCheck->free();
    $stmtCheck->close();

    if ($existingCustomer) {

      $errorMessage =
        'Email này đã được sử dụng.';
    } else {

      $passwordHash =
        password_hash(
          $password,
          PASSWORD_DEFAULT
        );

      $sqlInsert = "
                INSERT INTO customers (
                    CustomerName,
                    Phone,
                    Email,
                    PasswordHash
                )
                VALUES (?, ?, ?, ?)
            ";

      $stmtInsert =
        $conn->prepare($sqlInsert);

      $stmtInsert->bind_param(
        'ssss',
        $customerName,
        $phone,
        $email,
        $passwordHash
      );

      $stmtInsert->execute();
      $stmtInsert->close();

      header(
        'Location: /login.php?registered=1'
      );
      exit;
    }
  }
}

require_once '/var/www/src/includes/frontend/header.php';
require_once '/var/www/src/includes/frontend/navbar.php';

?>

<div class="container py-4">

  <div class="row justify-content-center">

    <div class="col-md-7 col-lg-5">

      <h1 class="h3 mb-4">
        Đăng ký tài khoản
      </h1>

      <?php if ($errorMessage !== ''): ?>

        <div class="alert alert-danger">
          <?= htmlspecialchars($errorMessage) ?>
        </div>

      <?php endif; ?>

      <form method="post">

        <div class="mb-3">
          <label
            for="customer_name"
            class="form-label">
            Họ và tên
          </label>

          <input
            type="text"
            class="form-control"
            id="customer_name"
            name="customer_name"
            value="<?= htmlspecialchars(
                      $customerName
                    ) ?>"
            required>
        </div>

        <div class="mb-3">
          <label
            for="phone"
            class="form-label">
            Số điện thoại
          </label>

          <input
            type="text"
            class="form-control"
            id="phone"
            name="phone"
            value="<?= htmlspecialchars($phone) ?>">
        </div>

        <div class="mb-3">
          <label
            for="email"
            class="form-label">
            Email
          </label>

          <input
            type="email"
            class="form-control"
            id="email"
            name="email"
            value="<?= htmlspecialchars($email) ?>"
            required>
        </div>

        <div class="mb-3">
          <label
            for="password"
            class="form-label">
            Mật khẩu
          </label>

          <input
            type="password"
            class="form-control"
            id="password"
            name="password"
            required>
        </div>

        <div class="mb-3">
          <label
            for="confirm_password"
            class="form-label">
            Xác nhận mật khẩu
          </label>

          <input
            type="password"
            class="form-control"
            id="confirm_password"
            name="confirm_password"
            required>
        </div>

        <button
          type="submit"
          class="btn btn-primary">
          Đăng ký
        </button>

      </form>

    </div>

  </div>

</div>

<?php

require_once '/var/www/src/includes/frontend/footer.php';

$conn->close();
