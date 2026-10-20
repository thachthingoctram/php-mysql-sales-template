<?php
require_once '/var/www/src/config/session.php';
require_once '/var/www/src/config/database.php';

$pageTitle = 'Đặt hàng thành công';

$orderID = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($orderID <= 0) {
    header('Location: /');
    exit;
}

// Lấy thông tin đơn hàng và tính tổng tiền từ chi tiết đơn hàng.
$sqlOrder = "
    SELECT
        o.OrderID,
        o.OrderDate,
        COALESCE(
            (
                SELECT SUM(od.Quantity * od.UnitPrice)
                FROM orderdetail od
                WHERE od.OrderID = o.OrderID
            ),
            0
        ) AS TotalAmount,
        o.Status,
        c.CustomerName,
        c.Phone,
        c.Address
    FROM orders o
    JOIN customers c
        ON o.CustomerID = c.CustomerID
    WHERE o.OrderID = ?
";

$stmtOrder = $conn->prepare($sqlOrder);

if (!$stmtOrder) {
    die('Không thể truy vấn thông tin đơn hàng.');
}

$stmtOrder->bind_param('i', $orderID);
$stmtOrder->execute();

$orderResult = $stmtOrder->get_result();
$order = $orderResult->fetch_assoc();

$orderResult->free();
$stmtOrder->close();

if (!$order) {
    header('Location: /');
    exit;
}

// Lấy danh sách sản phẩm trong đơn hàng.
$sqlDetail = "
    SELECT
        od.Quantity,
        od.UnitPrice,
        p.ProductCode,
        p.ProductName
    FROM orderdetail od
    JOIN products p
        ON od.ProductID = p.ProductID
    WHERE od.OrderID = ?
    ORDER BY od.OrderDetailID
";

$stmtDetail = $conn->prepare($sqlDetail);

if (!$stmtDetail) {
    die('Không thể truy vấn chi tiết đơn hàng.');
}

$stmtDetail->bind_param('i', $orderID);
$stmtDetail->execute();

$detailResult = $stmtDetail->get_result();

$orderItems = [];
$calculatedTotal = 0;

while ($row = $detailResult->fetch_assoc()) {
    $row['Quantity'] = (int) $row['Quantity'];
    $row['UnitPrice'] = (float) $row['UnitPrice'];

    $row['Subtotal'] =
        $row['UnitPrice'] * $row['Quantity'];

    $calculatedTotal += $row['Subtotal'];

    $orderItems[] = $row;
}

$detailResult->free();
$stmtDetail->close();

// Dùng tổng tiền tính từ chi tiết đơn hàng để hiển thị.
$order['TotalAmount'] = $calculatedTotal;

require_once '/var/www/src/includes/frontend/header.php';
require_once '/var/www/src/includes/frontend/navbar.php';
?>

<div class="container py-4">

    <div class="alert alert-success">
        <h1 class="h4 mb-2">Đặt hàng thành công</h1>

        <p class="mb-0">
            Mã đơn hàng của bạn là
            <strong>#<?= (int) $order['OrderID'] ?></strong>.
        </p>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h5 mb-3">Thông tin đơn hàng</h2>

            <p>
                <strong>Khách hàng:</strong>
                <?= htmlspecialchars(
                    $order['CustomerName'] ?? '',
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </p>

            <p>
                <strong>Số điện thoại:</strong>
                <?= htmlspecialchars(
                    $order['Phone'] ?? '',
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </p>

            <p>
                <strong>Địa chỉ:</strong>
                <?= htmlspecialchars(
                    $order['Address'] ?? '',
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </p>

            <p>
                <strong>Ngày đặt:</strong>
                <?= htmlspecialchars(
                    $order['OrderDate'] ?? '',
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </p>

            <p class="mb-0">
                <strong>Trạng thái:</strong>
                <?= htmlspecialchars(
                    $order['Status'] ?? '',
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </p>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <h2 class="h5 mb-3">Chi tiết sản phẩm</h2>

            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Mã SP</th>
                            <th>Sản phẩm</th>
                            <th class="text-end">Đơn giá</th>
                            <th class="text-center">Số lượng</th>
                            <th class="text-end">Thành tiền</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (empty($orderItems)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted">
                                    Đơn hàng chưa có sản phẩm.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($orderItems as $item): ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars(
                                            $item['ProductCode'] ?? '',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $item['ProductName'] ?? '',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </td>

                                    <td class="text-end">
                                        <?= number_format(
                                            $item['UnitPrice'],
                                            0,
                                            ',',
                                            '.'
                                        ) ?> đ
                                    </td>

                                    <td class="text-center">
                                        <?= $item['Quantity'] ?>
                                    </td>

                                    <td class="text-end">
                                        <?= number_format(
                                            $item['Subtotal'],
                                            0,
                                            ',',
                                            '.'
                                        ) ?> đ
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>

                    <tfoot>
                        <tr>
                            <th colspan="4" class="text-end">
                                Tổng cộng
                            </th>

                            <th class="text-end">
                                <?= number_format(
                                    $order['TotalAmount'],
                                    0,
                                    ',',
                                    '.'
                                ) ?> đ
                            </th>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <a href="/products.php" class="btn btn-primary">
                Tiếp tục mua hàng
            </a>
        </div>
    </div>

</div>

<?php
require_once '/var/www/src/includes/frontend/footer.php';
?>
