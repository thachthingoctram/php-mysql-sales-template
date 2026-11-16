<!DOCTYPE html>
<html lang="vi">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <title><?= htmlspecialchars($pageTitle ?? 'Quản lý bán hàng') ?></title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link
    href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&display=swap"
    rel="stylesheet">

  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
    rel="stylesheet">

  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    rel="stylesheet">

  <script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js">
  </script>

  <style>
    body {
      background: #F3F5F7;
      color: #16212B;
      font-family: 'Be Vietnam Pro', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
    }

    /* Thanh điều hướng quản trị */
    .admin-nav {
      position: sticky;
      top: 0;
      z-index: 1020;
      padding: .6rem 0;
      background: #12302B;
      border-bottom: 1px solid rgba(255, 255, 255, .08);
    }

    .admin-nav .navbar-brand {
      display: flex;
      align-items: center;
      gap: .6rem;
      font-weight: 700;
      letter-spacing: -.01em;
    }

    .admin-nav .brand-mark {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 34px;
      height: 34px;
      border-radius: 10px;
      background: #0E7C66;
      font-size: 1rem;
    }

    .admin-nav .nav-link {
      padding: .5rem .85rem;
      border-radius: 8px;
      color: rgba(255, 255, 255, .72);
      font-size: .92rem;
      font-weight: 500;
    }

    .admin-nav .nav-link:hover,
    .admin-nav .nav-link:focus-visible {
      background: rgba(255, 255, 255, .08);
      color: #fff;
    }

    .admin-nav .nav-link.active {
      background: rgba(255, 255, 255, .14);
      color: #fff;
    }

    .admin-nav .navbar-toggler {
      border-color: rgba(255, 255, 255, .25);
    }

    @media (max-width: 991.98px) {
      .admin-nav .navbar-nav {
        padding-top: .75rem;
      }
    }

    /* Chân trang */
    .admin-footer {
      margin-top: 2.5rem;
      padding: 1.5rem 0;
      border-top: 1px solid #E3E8ED;
      color: #5F6B78;
      font-size: .85rem;
      text-align: center;
    }
  </style>
</head>

<body>
