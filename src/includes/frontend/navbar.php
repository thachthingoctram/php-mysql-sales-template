<?php

$cartCount = array_sum(
  $_SESSION['cart'] ?? []
);
$isLoggedIn = isset($_SESSION['customer_id']);

$customerName =
  $_SESSION['customer_name'] ?? '';

?>

<nav class="navbar navbar-dark bg-dark">

  <div class="container">

    <a
      class="navbar-brand"
      href="/">
      Sales Store
    </a>

    <div class="d-flex align-items-center gap-3">

      <a
        class="nav-link text-white"
        href="/">
        Trang chủ
      </a>

      <a
        class="nav-link text-white"
        href="/products.php">
        Sản phẩm
      </a>

      <div class="d-flex gap-2">

        <a
          class="btn btn-outline-light btn-sm"
          href="/cart.php">
          Giỏ hàng (<?= (int) $cartCount ?>)
        </a>
        <?php if ($isLoggedIn): ?>

          <span class="text-light">
            <?= htmlspecialchars($customerName) ?>
          </span>

          <a
            class="btn btn-outline-light btn-sm"
            href="/logout.php">
            Đăng xuất
          </a>

        <?php else: ?>

          <a
            class="btn btn-outline-light btn-sm"
            href="/register.php">
            Đăng ký
          </a>

          <a
            class="btn btn-outline-light btn-sm"
            href="/login.php">
            Đăng nhập
          </a>

        <?php endif; ?>
        <a
          class="btn btn-outline-light btn-sm"
          href="/admin/">
          Quản trị
        </a>

      </div>

    </div>

  </div>

</nav>