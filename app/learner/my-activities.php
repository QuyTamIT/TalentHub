<?php
require __DIR__ . '/includes/student-data.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/activity-data.php';

$pageTitle = 'Hoạt động của tôi';
$currentRoute = '/app/learner/activities.php';
$boot = [
    'student_id' => learner_current_student_id(),
    'catalog' => learner_activity_catalog(),
    'mock_registrations' => learner_activity_registration_history(learner_current_student_id()),
];
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Hoạt động của tôi | TalentHub</title>
    <link rel="stylesheet" href="../../assets/css/home.css">
    <link rel="stylesheet" href="../../assets/css/learner.css">
</head>
<body class="learner-app learner-page-my-activities">
    <div class="learner-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        <div class="learner-main">
            <?php include __DIR__ . '/includes/header.php'; ?>
            <main class="learner-content" id="main-content" data-my-activities-page>
                <?php
                $learnerPageBanner = [
                    'id' => 'learner-my-activities-page-title',
                    'eyebrow' => 'Hành trình trải nghiệm',
                    'title' => 'Hoạt động của tôi',
                    'description' => 'Theo dõi đăng ký, check-in, giờ trải nghiệm và phản hồi.',
                    'icon' => 'calendar',
                ];
                include __DIR__ . '/includes/page-banner.php';
                ?>
                <div class="learner-my-activities-heading learner-my-activities-heading--actions">
                    <a class="learner-btn learner-btn--primary" href="activities.php">Khám phá thêm</a>
                </div>
                <div class="learner-filter-list learner-registration-filters">
                    <?php foreach (['all' => 'Tất cả', 'registered' => 'Đã đăng ký', 'pending' => 'Chờ duyệt', 'waitlisted' => 'Danh sách chờ', 'completed' => 'Hoàn thành', 'cancelled' => 'Đã hủy'] as $id => $label): ?>
                        <button class="learner-filter-button" type="button" data-registration-filter="<?= $id; ?>" aria-pressed="<?= $id === 'all' ? 'true' : 'false'; ?>">
                            <?= $label; ?>
                        </button>
                    <?php endforeach; ?>
                </div>
                <section class="learner-my-activities-list" data-my-registration-list aria-live="polite"></section>
            </main>
        </div>
    </div>
    <script id="learner-activities-boot" type="application/json"><?= json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP); ?></script>
    <script src="../../assets/js/learner-api.js"></script>
    <script src="../../assets/js/learner.js"></script>
    <script src="../../assets/js/learner-activities.js"></script>
</body>
</html>
