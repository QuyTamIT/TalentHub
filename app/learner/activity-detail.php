<?php
require __DIR__ . '/includes/student-data.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/activity-data.php';

$activity = learner_activity_find($_GET['id'] ?? '');
$pageTitle = 'Chi tiết hoạt động';
$currentRoute = '/app/learner/activities.php';
$learnerDataSource = learner_safe_runtime_diagnostics()['source'];
$allowsLocalDemoMutation = $learnerDataSource === 'mock';
$boot = $activity ? [
    'source' => $learnerDataSource,
    'student_id' => learner_current_student_id(),
    'csrf_token' => (string) ($GLOBALS['learner_page_context']['csrfToken'] ?? ''),
    'activity' => $activity,
    'catalog' => learner_activity_catalog(),
    'registrations' => learner_activity_registration_history(learner_current_student_id()),
] : null;
$activityStatusLabels = [
    'published' => 'Đang mở',
    'active' => 'Đang diễn ra',
    'ongoing' => 'Đang diễn ra',
    'completed' => 'Đã hoàn thành',
    'cancelled' => 'Đã hủy',
    'closed' => 'Đã đóng',
];
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= learner_escape($activity['title'] ?? 'Không tìm thấy') ?> | TalentHub</title>
<link rel="stylesheet" href="../../assets/css/home.css"><link rel="stylesheet" href="../../assets/css/learner.css">
</head>
<body class="learner-app learner-page-activity-detail"><div class="learner-layout"><?php include __DIR__ . '/includes/sidebar.php'; ?><div class="learner-main"><?php include __DIR__ . '/includes/header.php'; ?><main class="learner-content" id="main-content" data-activity-detail-page>
<nav class="learner-breadcrumbs"><a href="activities.php">Hoạt động</a><span>/</span><span><?= learner_escape($activity['title'] ?? 'Không tìm thấy') ?></span></nav>
<?php if (!$activity): ?>
<section class="learner-card learner-not-found"><h1>Không tìm thấy hoạt động</h1><a class="learner-btn learner-btn--primary" href="activities.php">Quay lại</a></section>
<?php else: ?>
<section class="learner-card learner-activity-detail-hero"><div><span class="learner-badge learner-badge--<?= learner_escape($activity['tone']) ?>"><?= learner_escape($activity['category']) ?></span><h1><?= learner_escape($activity['title']) ?></h1><p><?= learner_escape($activity['summary']) ?></p><div class="learner-meta-list learner-meta-list--inline"><span><?= learner_icon('calendar', 17) ?> <?= learner_escape((new DateTimeImmutable($activity['start_at']))->format('d/m/Y · H:i')) ?></span><?php if ($activity['has_location']): ?><span><?= learner_icon('map-pin', 17) ?> <?= learner_escape($activity['location']) ?></span><?php endif; ?><span><?= learner_icon('users', 17) ?> <?= learner_escape($activity['participants'] . '/' . $activity['capacity']) ?></span><span><?= learner_icon('info', 17) ?> <?= learner_escape($activityStatusLabels[$activity['status']] ?? $activity['status']) ?></span></div></div><a class="learner-btn learner-btn--outline" href="my-activities.php">Hoạt động của tôi</a></section>
<div class="learner-activity-detail-layout"><div class="learner-activity-detail-main"><?php if ($activity['has_description'] || $activity['has_skills'] || $activity['has_requirements'] || $activity['has_benefits']): ?><section class="learner-card learner-content-section"><h2>Thông tin hoạt động</h2><?php if ($activity['has_description']): ?><p><?= learner_escape($activity['description']) ?></p><?php endif; ?><?php if ($activity['has_skills']): ?><h3>Kỹ năng phát triển</h3><div class="learner-chip-list learner-chip-list--large"><?php foreach ($activity['skills'] as $skill): ?><?php if (trim((string) $skill) !== ''): ?><span><?= learner_escape($skill) ?></span><?php endif; ?><?php endforeach; ?></div><?php endif; ?><?php if ($activity['has_requirements']): ?><h3>Điều kiện tham gia</h3><ul class="learner-check-list"><?php foreach ($activity['requirements'] as $requirement): ?><?php if (trim((string) $requirement) !== ''): ?><li><?= learner_icon('check', 16) ?> <?= learner_escape($requirement) ?></li><?php endif; ?><?php endforeach; ?></ul><?php endif; ?><?php if ($activity['has_benefits']): ?><h3>Quyền lợi</h3><ul class="learner-check-list"><?php foreach ($activity['benefits'] as $benefit): ?><?php if (trim((string) $benefit) !== ''): ?><li><?= learner_icon('sparkles', 16) ?> <?= learner_escape($benefit) ?></li><?php endif; ?><?php endforeach; ?></ul><?php endif; ?></section><?php endif; ?></div>
<aside class="learner-card learner-activity-register-card"><h2>Đăng ký tham gia</h2><div class="learner-activity-capacity"><span>Còn <?= max(0, $activity['capacity'] - $activity['participants']) ?> chỗ</span><div class="learner-progress"><span style="--learner-progress:<?= min(100, round($activity['participants'] / $activity['capacity'] * 100)) ?>%"></span></div></div><dl><?php if ($activity['has_format']): ?><div><dt>Hình thức</dt><dd><?= learner_escape($activity['format']) ?></dd></div><?php endif; ?><?php if ($activity['has_cost']): ?><div><dt>Chi phí</dt><dd><?= learner_escape($activity['cost']) ?></dd></div><?php endif; ?><div><dt>Duyệt đăng ký</dt><dd><?= learner_escape($activity['approval_mode'] === 'teacher_review' ? 'Giáo viên duyệt' : 'Tự động') ?></dd></div><div><dt>Hạn đăng ký</dt><dd><?= learner_escape((new DateTimeImmutable($activity['registration_closes_at']))->format('d/m/Y H:i')) ?></dd></div></dl><button class="learner-btn learner-btn--primary learner-btn--block" type="button" data-register-current disabled>Đang kiểm tra trạng thái đăng ký</button><p class="learner-registration-message" role="status" data-registration-message data-tone="outline">Đang kiểm tra lý do trạng thái đăng ký.</p><div class="learner-data-note"><?= learner_icon('info', 16) ?><p><?= $allowsLocalDemoMutation ? 'Chế độ demo: thay đổi chỉ được lưu cục bộ trên trình duyệt.' : 'Dữ liệu đăng ký từ máy chủ là nguồn chính thức.' ?></p></div></aside></div>
<?php endif; ?></main></div></div>
<?php if ($boot): ?><script id="learner-activities-boot" type="application/json"><?= json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script><?php endif; ?><script src="../../assets/js/learner-api.js"></script><script src="../../assets/js/learner.js"></script><script src="../../assets/js/learner-activities.js"></script></body></html>
