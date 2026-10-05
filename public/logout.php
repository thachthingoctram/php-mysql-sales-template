<?php

require_once '/var/www/src/config/session.php';

/*
 * Chỉ xóa dữ liệu xác thực khách hàng.
 * Session còn được sử dụng cho giỏ hàng.
 */
unset(
  $_SESSION['customer_id'],
  $_SESSION['customer_name']
);

session_regenerate_id(true);

header('Location: /');
exit;
