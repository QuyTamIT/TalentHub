<?php

declare(strict_types=1);

// Tính năng "An toàn sinh viên" đã bị gỡ bỏ theo yêu cầu của khách hàng.
// Chuyển hướng người dùng về trang chủ School Dashboard để tránh lỗi truy cập trực tiếp.

header('Location: /app/school/');
exit;
