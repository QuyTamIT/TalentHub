<?php
declare(strict_types=1);

require __DIR__ . '/includes/student-data.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/activity-data.php';

$pageTitle = 'Lịch sử hoạt động';
$currentRoute = '/app/learner/activities.php';
$activityNavigationActive = 'history';
$historyStatuses = ['attended', 'no_show'];
$activityHistory = learner_activity_attendance_history(learner_current_student_id());
$attendedCount = count(array_filter($activityHistory, static fn (array $item): bool => ($item['status'] ?? '') === 'attended'));
$noShowCount = count($activityHistory) - $attendedCount;
$experienceHours = array_reduce($activityHistory, static fn (float $sum, array $item): float => $sum + (($item['status'] ?? '') === 'attended' ? (float) ($item['experience_hours'] ?? 0) : 0.0), 0.0);
$currentMonth = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m');
$resolvedTimestamp = static function (array $item): ?DateTimeImmutable {
    foreach (['attendance_resolved_at', 'checked_in_at', 'end_at', 'updated_at'] as $field) {
        $value = trim((string) ($item[$field] ?? ''));
        if ($value === '') continue;
        try { return new DateTimeImmutable($value, new DateTimeZone('UTC')); } catch (Throwable) { continue; }
    }
    return null;
};
$monthCount = count(array_filter($activityHistory, static fn (array $item): bool => $resolvedTimestamp($item)?->format('Y-m') === $currentMonth));
$attendanceRate = count($activityHistory) === 0 ? 0 : (int) round(($attendedCount / count($activityHistory)) * 100);
$historyGroups = [];
foreach ($activityHistory as $item) {
    $key = $resolvedTimestamp($item)?->format('Y-m') ?? 'unknown';
    $historyGroups[$key][] = $item;
}
$formatHours = static fn (float $hours): string => rtrim(rtrim(number_format($hours, 1, ',', '.'), '0'), ',');
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="description" content="Xem lại lịch sử hoạt động đã được xác nhận trên TalentHub.">
    <title>Lịch sử hoạt động | TalentHub</title>
    <link rel="stylesheet" href="../../assets/css/home.css">
    <link rel="stylesheet" href="../../assets/css/global.css">
    <link rel="stylesheet" href="../../assets/css/brand-component.css">
    <link rel="stylesheet" href="../../assets/css/polish.css">
    <link rel="stylesheet" href="../../assets/css/learner.css">
    <link rel="stylesheet" href="assets/activities/activities.css">
    <link rel="stylesheet" href="../../assets/css/typeui-selects.css">
</head>
<body class="learner-app learner-page-activity-history">
<div class="learner-layout">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="learner-main">
        <?php include __DIR__ . '/includes/header.php'; ?>
        <main class="learner-content" id="main-content" data-activity-history-page>
            <div class="learner-activities-shell">
                <nav class="learner-breadcrumbs" aria-label="Đường dẫn"><a href="index.php">Trang chủ</a><span aria-hidden="true">/</span><a href="activities.php">Hoạt động</a><span aria-hidden="true">/</span><span aria-current="page">Lịch sử</span></nav>
                <section class="learner-activity-history-hero" aria-labelledby="learner-activity-history-title">
                    <div><p>NHÌN LẠI HÀNH TRÌNH</p><h1 id="learner-activity-history-title">Lịch sử hoạt động</h1><span>Mỗi trải nghiệm là một dấu mốc giúp bạn hiểu rõ thế mạnh và hướng đi của mình.</span></div>
                    <img src="assets/activities/illustrations/hero-history.svg" alt="Minh họa dòng thời gian và thành tích hoạt động">
                </section>
                <?php include __DIR__ . '/includes/activity-navigation.php'; ?>

                <section class="learner-activity-history-kpis" aria-label="Tổng quan lịch sử">
                    <div><span><?= learner_icon('check', 21); ?></span><strong data-history-kpi="attended"><?= $attendedCount; ?></strong><small>Đã tham gia</small></div>
                    <div><span><?= learner_icon('x', 21); ?></span><strong data-history-kpi="no-show"><?= $noShowCount; ?></strong><small>Không tham gia</small></div>
                    <div><span><?= learner_icon('clock', 21); ?></span><strong data-history-kpi="hours"><?= learner_escape($formatHours($experienceHours)); ?></strong><small>Giờ trải nghiệm</small></div>
                    <div><span><?= learner_icon('calendar', 21); ?></span><strong data-history-kpi="month"><?= $monthCount; ?></strong><small>Hoạt động tháng này</small></div>
                </section>

                <section class="learner-activity-history-toolbar" aria-label="Lọc lịch sử">
                    <div class="learner-filter-list">
                        <?php foreach (['all' => 'Tất cả', 'attended' => 'Đã tham gia', 'no_show' => 'Không tham gia'] as $status => $label): ?>
                            <button type="button" class="learner-filter-button" data-history-filter="<?= $status; ?>" aria-pressed="<?= $status === 'all' ? 'true' : 'false'; ?>"><?= $label; ?></button>
                        <?php endforeach; ?>
                    </div>
                    <label>Khoảng thời gian<select class="typeui-select typeui-select--compact typeui-select--inline" data-history-period><option value="all">Toàn bộ</option><option value="30d">30 ngày qua</option><option value="90d">3 tháng qua</option><option value="365d">12 tháng qua</option></select></label>
                </section>

                <div class="learner-activity-history-layout">
                    <div class="learner-activity-history-timeline">
                        <?php foreach ($historyGroups as $month => $items): ?>
                            <?php $monthDate = $month === 'unknown' ? null : DateTimeImmutable::createFromFormat('!Y-m', $month, new DateTimeZone('UTC')); ?>
                            <section data-history-month-group>
                                <h2><?= $monthDate ? 'Tháng ' . $monthDate->format('m/Y') : 'Chưa xác định thời gian'; ?></h2>
                                <?php foreach ($items as $historyItem): ?>
                                    <?php
                                    $status = (string) ($historyItem['status'] ?? '');
                                    $date = $resolvedTimestamp($historyItem);
                                    $isNoShow = $status === 'no_show';
                                    $checkedIn = !$isNoShow ? trim((string) ($historyItem['checked_in_at'] ?? '')) : '';
                                    $hours = !$isNoShow ? (float) ($historyItem['experience_hours'] ?? 0) : 0.0;
                                    ?>
                                    <article class="learner-activity-history-card learner-activity-history-card--<?= learner_escape($status); ?>" data-history-card data-status="<?= learner_escape($status); ?>" data-history-timestamp="<?= learner_escape($date?->format(DATE_ATOM) ?? ''); ?>">
                                        <span class="learner-activity-history-card__marker" aria-hidden="true"></span>
                                        <div class="learner-activity-history-card__header"><span><?= learner_escape($historyItem['filter_category'] ?? $historyItem['category'] ?? 'Hoạt động'); ?></span><strong><?= $isNoShow ? 'Không tham gia' : 'Đã tham gia'; ?></strong></div>
                                        <h3><?= learner_escape($historyItem['title'] ?? 'Hoạt động TalentHub'); ?></h3>
                                        <p><?= learner_escape($historyItem['school_name'] ?? $historyItem['organizer_name'] ?? 'Đơn vị tổ chức'); ?></p>
                                        <div class="learner-activity-history-card__meta">
                                            <span><?= learner_icon('calendar', 17); ?> <?= learner_escape($date?->format('d/m/Y · H:i') ?? 'Chưa cập nhật'); ?></span>
                                            <span><?= learner_icon('check', 17); ?> <?= $checkedIn !== '' ? 'Check-in đã xác nhận ' . learner_escape((new DateTimeImmutable($checkedIn))->format('d/m/Y · H:i')) : ($isNoShow ? 'Không có check-in' : 'Chưa có check-in xác nhận'); ?></span>
                                            <span><?= learner_icon('clock', 17); ?> <?= learner_escape($formatHours($hours)); ?> giờ trải nghiệm</span>
                                            <?php if ($isNoShow && !empty($historyItem['attendance_resolved_at'])): ?><span>Đối soát <?= learner_escape((new DateTimeImmutable((string) $historyItem['attendance_resolved_at']))->format('d/m/Y · H:i')); ?></span><?php endif; ?>
                                        </div>
                                        <a href="activity-detail.php?id=<?= learner_escape(rawurlencode((string) ($historyItem['activity_id'] ?? ''))); ?>">Xem chi tiết <?= learner_icon('arrow-right', 17); ?></a>
                                    </article>
                                <?php endforeach; ?>
                            </section>
                        <?php endforeach; ?>
                        <section class="learner-activity-history-empty" data-history-filter-empty <?= $activityHistory === [] ? '' : 'hidden'; ?> role="status">
                            <?= learner_icon('calendar', 30); ?><h2>Chưa có lịch sử hoạt động</h2><p>Các hoạt động đã hoàn tất đối soát sẽ xuất hiện tại đây.</p><a href="activities.php">Khám phá hoạt động</a>
                        </section>
                    </div>
                    <aside class="learner-activity-history-summary" aria-labelledby="history-summary-title">
                        <h2 id="history-summary-title">Tổng quan hoạt động</h2>
                        <div class="learner-activity-history-donut" style="--attendance-rate: <?= $attendanceRate; ?>" role="img" aria-label="Tỷ lệ tham gia <?= $attendanceRate; ?> phần trăm"><span><strong><?= $attendanceRate; ?>%</strong>Tỷ lệ tham gia</span></div>
                        <dl><div><dt><span class="is-attended"></span>Đã tham gia</dt><dd><?= $attendedCount; ?></dd></div><div><dt><span class="is-no-show"></span>Không tham gia</dt><dd><?= $noShowCount; ?></dd></div><div><dt>Tổng hoạt động</dt><dd><?= count($activityHistory); ?></dd></div></dl>
                    </aside>
                </div>
            </div>
        </main>
    </div>
</div>
<script src="../../assets/js/learner.js"></script>
<script src="../../assets/js/learner-activities.js"></script>
</body>
</html>
