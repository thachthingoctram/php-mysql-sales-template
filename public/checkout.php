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
 * Xác định trạng thái đăng nhập
 */
$isLoggedIn = isset($_SESSION['customer_id']);

$customerID = null;
$customerName = '';
$phone = '';
$address = '';
$errorMessage = '';

/*
 * Nếu khách đã đăng nhập,
 * đọc thông tin khách hàng từ database
 */
if ($isLoggedIn) {

  $customerID = (int) $_SESSION['customer_id'];

  $sqlCustomer = "
        SELECT
            CustomerID,
            CustomerName,
            Address
        FROM customers
        WHERE CustomerID = ?
    ";

  $stmtCustomer = $conn->prepare($sqlCustomer);

  if (!$stmtCustomer) {
    die('Lỗi chuẩn bị truy vấn khách hàng.');
  }

  $stmtCustomer->bind_param(
    'i',
    $customerID
  );

  $stmtCustomer->execute();

  $customerResult = $stmtCustomer->get_result();
  $customer = $customerResult->fetch_assoc();

  $customerResult->free();
  $stmtCustomer->close();

  if (!$customer) {

    unset(
      $_SESSION['customer_id'],
      $_SESSION['customer_name']
    );

    header('Location: /login.php');
    exit;
  }

  $customerName = $customer['CustomerName'];
  $address = $customer['Address'] ?? '';
}


/*
 * Lấy sản phẩm trong giỏ hàng
 * và đọc lại giá từ MySQL
 */
function getCartItems($conn, $cart)
{
  $items = [];
  $total = 0;

  $sql = "
        SELECT
            p.ProductID,
            p.ProductCode,
            p.ProductName,
            p.Price,
            p.StockQuantity,
            (
                SELECT pi.ImageFile
                FROM product_images pi
                WHERE pi.ProductID = p.ProductID
                  AND pi.IsPrimary = 1
                LIMIT 1
            ) AS ImageFile
        FROM products p
        WHERE p.ProductID = ?
          AND p.IsActive = 1
    ";

  $stmt = $conn->prepare($sql);

  if (!$stmt) {
    return [
      'items' => [],
      'total' => 0
    ];
  }

  foreach ($cart as $productID => $quantity) {

    $productID = (int) $productID;
    $quantity = (int) $quantity;

    if ($productID <= 0 || $quantity <= 0) {
      continue;
    }

    $stmt->bind_param(
      'i',
      $productID
    );

    $stmt->execute();

    $result = $stmt->get_result();
    $product = $result->fetch_assoc();

    $result->free();

    if (!$product) {
      continue;
    }

    $product['Quantity'] = $quantity;

    $product['Subtotal'] =
      (float) $product['Price']
      * $quantity;

    $total += $product['Subtotal'];

    $items[] = $product;
  }

  $stmt->close();

  return [
    'items' => $items,
    'total' => $total
  ];
}


$cartData = getCartItems(
  $conn,
  $cart
);

$cartItems = $cartData['items'];
$total = $cartData['total'];


/*
 * Nếu giỏ không còn sản phẩm hợp lệ
 */
if (empty($cartItems)) {

  header('Location: /cart.php');
  exit;
}


/*
 * Xử lý xác nhận đặt hàng
 */
if (
  $_SERVER['REQUEST_METHOD'] === 'POST'
  && isset($_POST['place_order'])
) {

  /*
     * Khách đã đăng nhập:
     * không cho gửi CustomerName để xác định tài khoản.
     * CustomerID lấy từ Session.
     */
  if ($isLoggedIn) {

    $phone =
      trim($_POST['phone'] ?? '');

    $address =
      trim($_POST['address'] ?? '');
  } else {

    /*
         * Khách vãng lai
         */
    $customerName =
      trim($_POST['customer_name'] ?? '');

    $phone =
      trim($_POST['phone'] ?? '');

    $address =
      trim($_POST['address'] ?? '');
  }


  /*
     * Kiểm tra dữ liệu
     */
  if (!$isLoggedIn && $customerName === '') {

    $errorMessage =
      'Vui lòng nhập họ và tên.';
  } elseif ($phone === '') {

    $errorMessage =
      'Vui lòng nhập số điện thoại.';
  } elseif ($address === '') {

    $errorMessage =
      'Vui lòng nhập địa chỉ.';
  } else {

    try {

      /*
             * Bắt đầu transaction
             */
      $conn->begin_transaction();


      /*
             * 1. Kiểm tra lại sản phẩm,
             * giá và tồn kho.
             *
             * FOR UPDATE khóa dòng sản phẩm
             * trong transaction.
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

      if (!$stmtProduct) {
        throw new Exception(
          'Không thể kiểm tra sản phẩm.'
        );
      }


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

        $productResult =
          $stmtProduct->get_result();

        $product =
          $productResult->fetch_assoc();

        $productResult->free();


        /*
                 * Sản phẩm không còn tồn tại
                 * hoặc đã bị ngừng bán
                 */
        if (!$product) {

          throw new Exception(
            'Có sản phẩm không còn khả dụng.'
          );
        }


        /*
                 * Kiểm tra tồn kho
                 */
        if (
          $quantity
          > (int) $product['StockQuantity']
        ) {

          throw new Exception(
            'Sản phẩm "'
              . $product['ProductName']
              . '" không đủ số lượng tồn kho.'
          );
        }


        /*
                 * Lấy giá hiện tại từ MySQL
                 */
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
             * 2. Xác định khách hàng
             */
      if ($isLoggedIn) {

        /*
                 * Kiểm tra lại CustomerID
                 * trong transaction.
                 */
        $sqlAccount = "
                    SELECT CustomerID
                    FROM customers
                    WHERE CustomerID = ?
                    FOR UPDATE
                ";

        $stmtAccount =
          $conn->prepare($sqlAccount);

        if (!$stmtAccount) {
          throw new Exception(
            'Không thể kiểm tra tài khoản khách hàng.'
          );
        }

        $stmtAccount->bind_param(
          'i',
          $customerID
        );

        $stmtAccount->execute();

        $accountResult =
          $stmtAccount->get_result();

        $account =
          $accountResult->fetch_assoc();

        $accountResult->free();
        $stmtAccount->close();


        if (!$account) {

          throw new Exception(
            'Tài khoản khách hàng không còn hợp lệ.'
          );
        }


        /*
                 * Cập nhật địa chỉ khách hàng
                 */
        $sqlUpdateCustomer = "
                    UPDATE customers
                    SET
                        Address = ?
                    WHERE CustomerID = ?
                ";

        $stmtUpdateCustomer =
          $conn->prepare(
            $sqlUpdateCustomer
          );

        if (!$stmtUpdateCustomer) {
          throw new Exception(
            'Không thể cập nhật thông tin khách hàng.'
          );
        }

        $stmtUpdateCustomer->bind_param(
          'si',
          $address,
          $customerID
        );

        $stmtUpdateCustomer->execute();
        $stmtUpdateCustomer->close();
      } else {

        /*
                 * Khách chưa đăng nhập:
                 * tạo Customer mới.
                 */
        $sqlCustomer = "
                    INSERT INTO customers (
                        CustomerName,
                        Address
                    )
                    VALUES (?, ?)
                ";

        $stmtCustomer =
          $conn->prepare($sqlCustomer);

        if (!$stmtCustomer) {
          throw new Exception(
            'Không thể tạo khách hàng.'
          );
        }

        $stmtCustomer->bind_param(
          'ss',
          $customerName,
          $address
        );

        $stmtCustomer->execute();

        $customerID =
          $conn->insert_id;

        $stmtCustomer->close();
      }


      /*
             * 3. Tạo Order
             *
             * Phù hợp với cấu trúc orders hiện tại:
             * OrderID
             * OrderDate
             * CustomerID
             * EmployeeID
             * ShipperID
             */
      $sqlOrder = "
                INSERT INTO orders (
                    OrderDate,
                    CustomerID,
                    EmployeeID,
                    ShipperID
                )
                VALUES (
                    NOW(),
                    ?,
                    NULL,
                    NULL
                )
            ";

      $stmtOrder =
        $conn->prepare($sqlOrder);

      if (!$stmtOrder) {
        throw new Exception(
          'Không thể tạo đơn hàng.'
        );
      }

      $stmtOrder->bind_param(
        'i',
        $customerID
      );

      $stmtOrder->execute();

      $orderID =
        $conn->insert_id;

      $stmtOrder->close();


      /*
             * 4. Tạo OrderDetail
             */
      $sqlDetail = "
                INSERT INTO orderdetail (
                    Quantity,
                    UnitPrice,
                    OrderID,
                    ProductID
                )
                VALUES (?, ?, ?, ?)
            ";

      $stmtDetail =
        $conn->prepare($sqlDetail);

      if (!$stmtDetail) {
        throw new Exception(
          'Không thể tạo chi tiết đơn hàng.'
        );
      }


      /*
             * 5. Trừ tồn kho
             */
      $sqlStock = "
                UPDATE products
                SET StockQuantity =
                    StockQuantity - ?
                WHERE ProductID = ?
            ";

      $stmtStock =
        $conn->prepare($sqlStock);

      if (!$stmtStock) {
        throw new Exception(
          'Không thể cập nhật tồn kho.'
        );
      }


      foreach ($orderItems as $item) {

        $quantity =
          (int) $item['Quantity'];

        $unitPrice =
          (float) $item['UnitPrice'];

        $productID =
          (int) $item['ProductID'];


        /*
                 * Lưu chi tiết đơn hàng
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
                 * Cập nhật tồn kho
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
             * 6. Commit transaction
             */
      $conn->commit();


      /*
             * Chỉ xóa giỏ hàng
             * sau khi commit thành công.
             */
      $_SESSION['cart'] = [];


      /*
             * Chuyển sang trang xác nhận
             */
      header(
        'Location: /order-success.php?id='
          . $orderID
      );

      exit;
    } catch (Throwable $e) {

      /*
             * Có lỗi:
             * rollback toàn bộ transaction.
             */
      $conn->rollback();

      $errorMessage =
        $e->getMessage();
    }
  }
}


require_once
  '/var/www/src/includes/frontend/header.php';

require_once
  '/var/www/src/includes/frontend/navbar.php';

?>

<div class="container py-4">

  <h1 class="h3 mb-4">
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


    <!-- =========================
             THÔNG TIN KHÁCH HÀNG
             ========================= -->

    <div class="col-lg-7">

      <div class="card shadow-sm">

        <div class="card-body">

          <h2 class="h5 mb-3">
            Thông tin khách hàng
          </h2>


          <form method="post">


            <?php if ($isLoggedIn): ?>

              <!-- Khách đã đăng nhập -->

              <div class="mb-3">

                <label class="form-label">
                  Họ và tên
                </label>

                <input
                  type="text"
                  class="form-control"
                  value="<?= htmlspecialchars(
                            $customerName
                          ) ?>"
                  readonly>

              </div>


            <?php else: ?>

              <!-- Khách vãng lai -->

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

            <?php endif; ?>


            <!-- Số điện thoại -->

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


            <!-- Địa chỉ -->

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
                rows="3"
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


    <!-- =========================
             ĐƠN HÀNG
             ========================= -->

    <div class="col-lg-5">

      <div class="card shadow-sm">

        <div class="card-body">

          <h2 class="h5 mb-3">
            Đơn hàng của bạn
          </h2>


          <?php foreach ($cartItems as $item): ?>

            <div
              class="d-flex
                                   justify-content-between
                                   border-bottom
                                   py-2">

              <div>

                <strong>
                  <?= htmlspecialchars(
                    $item['ProductName']
                  ) ?>
                </strong>

                <div class="small text-muted">

                  <?= (int)
                  $item['Quantity'] ?>

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


              <div>

                <?= number_format(
                  (float)
                  $item['Subtotal'],
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
                               pt-3">

            <span>
              Tổng cộng
            </span>

            <span>

              <?= number_format(
                (float) $total,
                0,
                ',',
                '.'
              ) ?>

              đ

            </span>

          </div>


          <a
            href="/cart.php"
            class="btn btn-outline-secondary mt-3">
            Quay lại giỏ hàng
          </a>


        </div>

      </div>

    </div>

  </div>

</div>


<?php

require_once
  '/var/www/src/includes/frontend/footer.php';

?>