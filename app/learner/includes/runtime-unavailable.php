<?php
declare(strict_types=1);

http_response_code(503);
header('Retry-After: 30');
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dịch vụ tạm thời gián đoạn | TalentHub</title>
    <link rel="stylesheet" href="/assets/css/home.css">
    <link rel="stylesheet" href="/assets/css/global.css">
    <link rel="stylesheet" href="assets/css/brand-component.css">
    <link rel="stylesheet" href="assets/css/polish.css">
    <link rel="stylesheet" href="/assets/css/learner.css">
</head>
<body class="learner-app">
<a class="skip-link" href="#main-content">Bỏ qua đến nội dung chính</a>
<main class="learner-content" id="main-content">
    <section class="learner-card learner-not-found" role="alert">
        <h1>Dịch vụ dữ liệu tạm thời không khả dụng</h1>
        <p>TalentHub chưa thể tải dữ liệu học viên. Vui lòng thử lại sau.</p>
        <a class="learner-btn learner-btn--primary" href="/app/learner/index.php">Thử lại</a>
    </section>
</main>
</body>
</html>
