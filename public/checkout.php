<?php

require_once '/var/www/src/config/session.php';
require_once '/var/www/src/config/database.php';

$pageTitle = 'Đặt hàng';

$cart = $_SESSION['cart'] ?? [];

if (empty($cart)) {
  header('Location: /cart.php');
  exit;
}

/*
|--------------------------------------------------------------------------
| Lấy thông tin sản phẩm trong giỏ
|--------------------------------------------------------------------------
*/

$cartItems = [];
$totalAmount = 0;

$sql = "
    SELECT
        p.ProductID,
        p.ProductCode,
        p.ProductName,
        p.Price,
        p.StockQuantity
    FROM products p
    WHERE p.ProductID = ?
      AND p.IsActive = 1
";

$stmt = $conn->prepare($sql);

foreach ($cart as $productID => $quantity) {

  $productID = (int) $productID;
  $quantity = (int) $quantity;

  if ($productID <= 0 || $quantity <= 0) {
    continue;
  }

  $stmt->bind_param('i', $productID);
  $stmt->execute();

  $result = $stmt->get_result();
  $product = $result->fetch_assoc();

  $result->free();

  if (!$product) {
    continue;
  }

  $subtotal =
    (float) $product['Price'] * $quantity;

  $product['Quantity'] = $quantity;
  $product['Subtotal'] = $subtotal;

  $cartItems[] = $product;

  $totalAmount += $subtotal;
}

$stmt->close();

if (empty($cartItems)) {
  $_SESSION['cart'] = [];

  header('Location: /cart.php');
  exit;
}

/*
|--------------------------------------------------------------------------
| Dữ liệu form
|--------------------------------------------------------------------------
*/

$customerName = '';
$phone = '';
$address = '';

$errorMessage = '';

/*
|--------------------------------------------------------------------------
| Xử lý đặt hàng
|--------------------------------------------------------------------------
*/

if (
  $_SERVER['REQUEST_METHOD'] === 'POST'
  && isset($_POST['place_order'])
) {

  $customerName = trim(
    $_POST['customer_name'] ?? ''
  );

  $phone = trim(
    $_POST['phone'] ?? ''
  );

  $address = trim(
    $_POST['address'] ?? ''
  );

  /*
    |--------------------------------------------------------------------------
    | Kiểm tra dữ liệu
    |--------------------------------------------------------------------------
    */

  if ($customerName === '') {

    $errorMessage =
      'Vui lòng nhập họ tên.';
  } elseif ($phone === '') {

    $errorMessage =
      'Vui lòng nhập số điện thoại.';
  } elseif ($address === '') {

    $errorMessage =
      'Vui lòng nhập địa chỉ.';
  } else {

    try {

      /*
            |--------------------------------------------------------------------------
            | Bắt đầu transaction
            |--------------------------------------------------------------------------
            */

      $conn->begin_transaction();

      /*
            |--------------------------------------------------------------------------
            | Kiểm tra lại tồn kho
            |--------------------------------------------------------------------------
            */

      $orderItems = [];
      $orderTotal = 0;

      $sqlProduct = "
                SELECT
                    ProductID,
                    ProductName,
                    Price,
                    StockQuantity
                FROM products
                WHERE ProductID = ?
                  AND IsActive = 1
                FOR UPDATE
            ";

      $stmtProduct =
        $conn->prepare($sqlProduct);

      foreach ($cart as $productID => $quantity) {

        $productID = (int) $productID;
        $quantity = (int) $quantity;

        if (
          $productID <= 0
          || $quantity <= 0
        ) {
          throw new Exception(
            'Dữ liệu giỏ hàng không hợp lệ.'
          );
        }

        $stmtProduct->bind_param(
          'i',
          $productID
        );

        $stmtProduct->execute();

        $result =
          $stmtProduct->get_result();

        $product =
          $result->fetch_assoc();

        $result->free();

        if (!$product) {

          throw new Exception(
            'Sản phẩm không còn tồn tại.'
          );
        }

        $stockQuantity =
          (int) $product['StockQuantity'];

        if ($quantity > $stockQuantity) {

          throw new Exception(
            'Sản phẩm "'
              . $product['ProductName']
              . '" không đủ tồn kho.'
          );
        }

        $unitPrice =
          (float) $product['Price'];

        $subtotal =
          $unitPrice * $quantity;

        $orderTotal += $subtotal;

        $orderItems[] = [
          'ProductID' => $productID,
          'Quantity' => $quantity,
          'UnitPrice' => $unitPrice
        ];
      }

      $stmtProduct->close();

      /*
            |--------------------------------------------------------------------------
            | Tạo customer
            |--------------------------------------------------------------------------
            */

      $sqlCustomer = "
                INSERT INTO customers
                (
                    CustomerName,
                    Address,
                    Phone
                )
                VALUES (?, ?, ?)
            ";

      $stmtCustomer =
        $conn->prepare($sqlCustomer);

      $stmtCustomer->bind_param(
        'sss',
        $customerName,
        $address,
        $phone
      );

      $stmtCustomer->execute();

      $customerID =
        $conn->insert_id;

      $stmtCustomer->close();

      /*
            |--------------------------------------------------------------------------
            | Tạo order
            |--------------------------------------------------------------------------
            */

      $status = 'Pending';

      $sqlOrder = "
                INSERT INTO orders
                (
                    TotalAmount,
                    Status,
                    CustomerID
                )
                VALUES (?, ?, ?)
            ";

      $stmtOrder =
        $conn->prepare($sqlOrder);

      $stmtOrder->bind_param(
        'dsi',
        $orderTotal,
        $status,
        $customerID
      );

      $stmtOrder->execute();

      $orderID =
        $conn->insert_id;

      $stmtOrder->close();

      /*
            |--------------------------------------------------------------------------
            | Tạo orderdetail
            |--------------------------------------------------------------------------
            */

      $sqlDetail = "
                INSERT INTO orderdetail
                (
                    Quantity,
                    UnitPrice,
                    OrderID,
                    ProductID
                )
                VALUES (?, ?, ?, ?)
            ";

      $stmtDetail =
        $conn->prepare($sqlDetail);

      /*
            |--------------------------------------------------------------------------
            | Trừ tồn kho
            |--------------------------------------------------------------------------
            */

      $sqlStock = "
                UPDATE products
                SET StockQuantity =
                    StockQuantity - ?
                WHERE ProductID = ?
            ";

      $stmtStock =
        $conn->prepare($sqlStock);

      foreach ($orderItems as $item) {

        $productID =
          (int) $item['ProductID'];

        $quantity =
          (int) $item['Quantity'];

        $unitPrice =
          (float) $item['UnitPrice'];

        /*
                | Lưu chi tiết đơn hàng
                */

        $stmtDetail->bind_param(
          'idii',
          $quantity,
          $unitPrice,
          $orderID,
          $productID
        );

        $stmtDetail->execute();

        /*
                | Trừ tồn kho
                */

        $stmtStock->bind_param(
          'ii',
          $quantity,
          $productID
        );

        $stmtStock->execute();
      }

      $stmtDetail->close();
      $stmtStock->close();

      /*
            |--------------------------------------------------------------------------
            | Commit
            |--------------------------------------------------------------------------
            */

      $conn->commit();

      /*
            |--------------------------------------------------------------------------
            | Chỉ xóa cart sau khi commit thành công
            |--------------------------------------------------------------------------
            */

      $_SESSION['cart'] = [];

      /*
            |--------------------------------------------------------------------------
            | Chuyển sang trang thành công
            |--------------------------------------------------------------------------
            */

      header(
        'Location: /order-success.php?id='
          . $orderID
      );

      exit;
    } catch (Throwable $e) {

      /*
            |--------------------------------------------------------------------------
            | Có lỗi → rollback
            |--------------------------------------------------------------------------
            */

      $conn->rollback();

      $errorMessage =
        $e->getMessage();
    }
  }
}

/*
|--------------------------------------------------------------------------
| Frontend
|--------------------------------------------------------------------------
*/

require_once '/var/www/src/includes/frontend/header.php';
require_once '/var/www/src/includes/frontend/navbar.php';

?>

<main class="container py-5">

  <h1 class="mb-4">
    Đặt hàng
  </h1>

  <?php if ($errorMessage !== ''): ?>

    <div class="alert alert-danger">

      <?= htmlspecialchars(
        $errorMessage
      ) ?>

    </div>

  <?php endif; ?>


  <div class="row g-4">

    <!-- Thông tin khách hàng -->

    <div class="col-lg-7">

      <div class="card shadow-sm">

        <div class="card-body">

          <h2 class="h5 mb-4">
            Thông tin khách hàng
          </h2>

          <form method="post">

            <div class="mb-3">

              <label
                for="customer_name"
                class="form-label">
                Họ tên
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
                value="<?= htmlspecialchars(
                          $phone
                        ) ?>"
                required>

            </div>


            <div class="mb-3">

              <label
                for="address"
                class="form-label">
                Địa chỉ
              </label>

              <textarea
                class="form-control"
                id="address"
                name="address"
                rows="4"
                required><?= htmlspecialchars(
                            $address
                          ) ?></textarea>

            </div>


            <button
              type="submit"
              name="place_order"
              class="btn btn-success">
              Xác nhận đặt hàng
            </button>

          </form>

        </div>

      </div>

    </div>


    <!-- Tóm tắt đơn hàng -->

    <div class="col-lg-5">

      <div class="card shadow-sm">

        <div class="card-body">

          <h2 class="h5 mb-4">
            Đơn hàng của bạn
          </h2>


          <?php foreach (
            $cartItems as $item
          ): ?>

            <div
              class="d-flex
                                   justify-content-between
                                   border-bottom
                                   py-3">

              <div>

                <div class="fw-semibold">

                  <?= htmlspecialchars(
                    $item['ProductName']
                  ) ?>

                </div>

                <div class="small text-muted">

                  <?= (int) $item['Quantity'] ?>

                  ×

                  <?= number_format(
                    (float) $item['Price'],
                    0,
                    ',',
                    '.'
                  ) ?>

                  đ

                </div>

              </div>

              <div class="fw-semibold">

                <?= number_format(
                  (float) $item['Subtotal'],
                  0,
                  ',',
                  '.'
                ) ?>

                đ

              </div>

            </div>

          <?php endforeach; ?>


          <div
            class="d-flex
                               justify-content-between
                               fw-bold
                               fs-5
                               mt-3">

            <span>
              Tổng cộng
            </span>

            <span>

              <?= number_format(
                $totalAmount,
                0,
                ',',
                '.'
              ) ?>

              đ

            </span>

          </div>


          <a
            href="/cart.php"
            class="btn btn-outline-secondary w-100 mt-3">
            Quay lại giỏ hàng
          </a>

        </div>

      </div>

    </div>

  </div>

</main>

<?php

require_once '/var/www/src/includes/frontend/footer.php';

?>