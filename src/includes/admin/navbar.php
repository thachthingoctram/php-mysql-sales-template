<?php
// Đánh dấu mục menu đang mở dựa trên đường dẫn hiện tại.
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';

$isActive = function (string $prefix) use ($currentPath): string {
  if ($prefix === '/admin/index.php') {
    $isHome = in_array($currentPath, ['/admin', '/admin/', '/admin/index.php'], true);
    return $isHome ? ' active" aria-current="page' : '';
  }

  return str_starts_with($currentPath, $prefix)
    ? ' active" aria-current="page'
    : '';
};
?>
<nav class="navbar navbar-expand-lg navbar-dark admin-nav">
  <div class="container">
    <a class="navbar-brand" href="/">
      <span class="brand-mark"><i class="bi bi-shop"></i></span>
      Sales Management
    </a>

    <button
      class="navbar-toggler"
      type="button"
      data-bs-toggle="collapse"
      data-bs-target="#mainNavbar"
      aria-controls="mainNavbar"
      aria-expanded="false"
      aria-label="Mở menu">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="mainNavbar">
      <ul class="navbar-nav ms-lg-3 gap-lg-1">

        <li class="nav-item">
          <a class="nav-link<?= $isActive('/admin/index.php') ?>" href="/admin/index.php">Trang chủ</a>
        </li>

        <li class="nav-item">
          <a class="nav-link<?= $isActive('/admin/categories') ?>" href="/admin/categories/">
            Danh mục
          </a>
        </li>

        <li class="nav-item">
          <a class="nav-link<?= $isActive('/admin/products') ?>" href="/admin/products/">
            Sản phẩm
          </a>
        </li>

        <li class="nav-item">
          <a class="nav-link<?= $isActive('/admin/customer') ?>" href="/admin/customer/">
            Quản lý khách hàng
          </a>
        </li>

        <li class="nav-item">
          <a class="nav-link<?= $isActive('/admin/orders') ?>" href="/admin/orders/">
            Đơn hàng
          </a>
        </li>

        <li class="nav-item">
          <a class="nav-link<?= $isActive('/admin/shippers') ?>" href="/admin/shippers/">
            Vận chuyển
          </a>
        </li>

      </ul>
    </div>
  </div>
</nav>
