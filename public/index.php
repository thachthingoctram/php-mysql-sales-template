<?php

require_once '/var/www/src/config/session.php';

$pageTitle = 'Trang chủ';

require_once '/var/www/src/includes/frontend/header.php';
require_once '/var/www/src/includes/frontend/navbar.php';
?>

<main class="container py-5">

  <div class="text-center">

    <h1>Chào mừng đến với Sales Store</h1>

    <p class="text-muted">
      Khám phá các sản phẩm hiện có tại cửa hàng.
    </p>

    <a
      href="/products.php"
      class="btn btn-primary">
      Xem sản phẩm
    </a>

  </div>

</main>

<?php
require_once '/var/www/src/includes/frontend/footer.php';
