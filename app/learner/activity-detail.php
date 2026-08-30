<?php

declare(strict_types=1);

if (!function_exists('learner_activity_cover_or_fallback')) {
    function learner_activity_cover_or_fallback(mixed $value, string $fallback): string
    {
        $candidate = trim((string) $value);
        if ($candidate === '' || str_contains($candidate, '..')) return $fallback;
        return preg_match('#\A(?:/app/learner/)?assets/activities/[a-z0-9/_-]+\.(?:webp|png|jpe?g|svg)\z#i', $candidate) === 1
            ? $candidate
            : $fallback;
    }
}

if (!function_exists('learner_activity_detail_boot_payload')) {
    function learner_activity_detail_boot_payload(
        ?array $activity,
        string $source,
        string $studentId,
        string $csrfToken,
        array $catalog,
        array $registrations,
        ?array $currentRegistration,
    ): ?array {
        if ($activity === null) return null;

        $isMock = $source === 'mock';
        $currentBelongsToActivity = $currentRegistration !== null
            && (string) ($currentRegistration['activity_id'] ?? '') === (string) ($activity['id'] ?? '');

        return [
            'source' => $source,
            'student_id' => $studentId,
            'csrf_token' => $csrfToken,
            'activity' => $activity,
            'catalog' => $isMock ? array_values($catalog) : [$activity],
            'registrations' => $isMock
                ? array_values($registrations)
                : ($currentBelongsToActivity ? [$currentRegistration] : []),
        ];
    }
}

if (($GLOBALS['__TALENTHUB_ACTIVITY_DETAIL_CONTRACT_ONLY__'] ?? false) === true) return;

require __DIR__ . '/includes/student-data.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/activity-data.php';

$routeId = is_string($_GET['id'] ?? null) ? trim((string) $_GET['id']) : '';
$activity = learner_activity_find($routeId);
$pageTitle = 'Chi tiết hoạt động';
$currentRoute = '/app/learner/activities.php';
$activityNavigationActive = 'discover';
$learnerDataSource = learner_safe_runtime_diagnostics()['source'];
$allowsLocalDemoMutation = $learnerDataSource === 'mock';
$registrations = $activity === null
    ? []
    : learner_activity_registration_history(learner_current_student_id());
$currentRegistration = null;
if ($activity !== null) {
    foreach ($registrations as $registration) {
        if ((string) ($registration['activity_id'] ?? '') === (string) $activity['id']) {
            $currentRegistration = $registration;
            break;
        }
    }
}
$boot = learner_activity_detail_boot_payload(
    $activity,
    $learnerDataSource,
    learner_current_student_id(),
    (string) ($GLOBALS['learner_page_context']['csrfToken'] ?? ''),
    $allowsLocalDemoMutation ? learner_activity_catalog() : [],
    $registrations,
    $currentRegistration,
);
$formatDateTime = static function (mixed $value, string $format): string {
    if (!is_string($value) || trim($value) === '') return 'Chưa cập nhật';
    try {
        return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'))->format($format);
    } catch (Throwable) {
        return 'Chưa cập nhật';
    }
};
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= learner_escape($activity['title'] ?? 'Không tìm thấy') ?> | TalentHub</title>
    <link rel="stylesheet" href="../../assets/css/home.css">
    <link rel="stylesheet" href="../../assets/css/learner.css">
    <link rel="stylesheet" href="assets/activities/activities.css">
</head>
<body class="learner-app learner-page-activity-detail">
<div class="learner-layout">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="learner-main">
        <?php include __DIR__ . '/includes/header.php'; ?>
        <main class="learner-content" id="main-content" data-activity-detail-page>
            <div class="learner-activities-shell">
                <nav class="learner-breadcrumbs" aria-label="Điều hướng trang">
                    <a href="activities.php">Hoạt động</a><span aria-hidden="true">/</span>
                    <span aria-current="page"><?= learner_escape($activity['title'] ?? 'Không tìm thấy') ?></span>
                </nav>
                <?php include __DIR__ . '/includes/activity-navigation.php'; ?>

                <?php if ($activity === null): ?>
                    <section class="learner-card learner-activity-detail-not-found">
                        <div class="learner-activity-detail-not-found__icon"><?= learner_icon('compass', 28) ?></div>
                        <h1>Không tìm thấy hoạt động</h1>
                        <p>Hoạt động không tồn tại hoặc không thuộc phạm vi trường của bạn.</p>
                        <a class="learner-btn learner-btn--primary" href="activities.php">Quay lại khám phá</a>
                    </section>
                <?php else: ?>
                    <?php
                    $availability = is_array($activity['availability'] ?? null)
                        ? $activity['availability']
                        : ['code' => 'unavailable', 'label' => 'Không nhận đăng ký', 'explanation' => 'Hoạt động hiện không nhận đăng ký.'];
                    $displayCategory = trim((string) $activity['display_category']) ?: 'Chưa phân loại';
                    $schoolName = trim((string) $activity['school_name']) ?: 'Trường của bạn';
                    $teacherName = trim((string) $activity['responsible_teacher_name']) ?: 'Liên hệ đơn vị tổ chức';
                    $organizerName = trim((string) $activity['organizer_name']) ?: $schoolName;
                    $locationName = $activity['has_location'] ? (string) $activity['location_name'] : 'Chưa cập nhật';
                    $deliveryMode = $activity['has_format'] ? (string) $activity['delivery_mode_label'] : 'Chưa cập nhật';
                    $feeLabel = $activity['has_cost'] ? (string) $activity['fee_label'] : 'Chưa cập nhật';
                    $tz = new DateTimeZone('Asia/Ho_Chi_Minh');
                    $dtStart = null;
                    $dtEnd = null;
                    if (!empty($activity['start_at'])) {
                        try { $dtStart = (new DateTimeImmutable((string) $activity['start_at']))->setTimezone($tz); } catch (Throwable) {}
                    }
                    if (!empty($activity['end_at'])) {
                        try { $dtEnd = (new DateTimeImmutable((string) $activity['end_at']))->setTimezone($tz); } catch (Throwable) {}
                    }
                    if ($dtStart && $dtEnd && $dtStart > $dtEnd) {
                        $tmp = $dtStart;
                        $dtStart = $dtEnd;
                        $dtEnd = $tmp;
                    }
                    $startDate = $dtStart ? $dtStart->format('d/m/Y') : 'Chưa cập nhật';
                    $endDate = $dtEnd ? $dtEnd->format('d/m/Y') : $startDate;
                    $startTime = $dtStart ? $dtStart->format('H:i') : '00:00';
                    $endTime = $dtEnd ? $dtEnd->format('H:i') : $startTime;
                    $timeRangeDisplay = $startTime . ' – ' . $endTime;
                    if ($dtStart && $dtEnd && $startDate !== $endDate) {
                        $timeRangeDisplay = $startDate . ' ' . $startTime . ' – ' . $endDate . ' ' . $endTime;
                    }

                    $registrationOpens = !empty($activity['registration_opens_at']) ? $formatDateTime($activity['registration_opens_at'], 'd/m/Y H:i') : 'Ngay khi công bố';
                    $registrationCloses = !empty($activity['registration_deadline']) ? date('d/m/Y H:i', strtotime($activity['registration_deadline'])) : 'Chưa thiết lập';
                    $cancellationCloses = !empty($activity['cancel_deadline']) ? date('d/m/Y H:i', strtotime($activity['cancel_deadline'])) : 'Chưa thiết lập';
                    $capacity = max(1, (int) $activity['capacity']);
                    $participants = max(0, min($capacity, (int) $activity['participants']));
                    $remaining = max(0, (int) $activity['remaining']);
                    $capacityPercent = min(100, (int) round(($participants / $capacity) * 100));
                    $approvalLabel = (string) $activity['approval_mode'] === 'teacher_review' ? 'Giáo viên duyệt' : 'Duyệt tự động';
                    $hoursLabel = is_numeric($activity['confirmed_hours']) ? rtrim(rtrim(number_format((float) $activity['confirmed_hours'], 1, ',', '.'), '0'), ',') . ' giờ' : 'Chưa cập nhật';
                    $coverImage = 'assets/activities/illustrations/hero-detail.svg';
                    $coverImage = learner_activity_cover_or_fallback(
                        $activity['cover_image_url'] ?? '',
                        $coverImage,
                    );
                    $coverAlt = trim((string) $activity['cover_image_alt']) ?: 'Minh họa cho hoạt động ' . (string) $activity['title'];
                    $registrationStatus = (string) ($currentRegistration['status'] ?? '');
                    $registrationLabels = [
                        'approved' => 'Đã đăng ký', 'registered' => 'Đã đăng ký', 'pending' => 'Chờ duyệt',
                        'waitlisted' => 'Danh sách chờ', 'rejected' => 'Không được duyệt', 'cancelled' => 'Đã hủy',
                        'attended' => 'Đã tham gia', 'checked_in' => 'Đã check-in',
                    ];
                    $isCurrentlyRegistered = in_array($registrationStatus, ['approved', 'registered', 'pending', 'waitlisted', 'attended', 'checked_in'], true);
                    $isOpen = (string) ($availability['code'] ?? '') === 'open';
                    $ctaLabel = $isCurrentlyRegistered
                        ? ($registrationLabels[$registrationStatus] ?? 'Đã đăng ký')
                        : ($isOpen ? 'Đăng ký tham gia' : (string) ($availability['label'] ?? 'Không thể đăng ký'));
                    $ctaDisabled = $isCurrentlyRegistered || !$isOpen;
                    ?>
                    <div class="learner-activity-detail-grid">
                        <div class="learner-activity-detail-content">
                            <section class="learner-activity-detail-hero" aria-labelledby="activity-detail-title">
                                <div class="learner-activity-detail-hero__content">
                                    <div class="learner-activity-detail-hero__badges">
                                        <span class="learner-badge learner-badge--<?= learner_escape((string) $activity['tone']) ?>"><?= learner_escape($displayCategory) ?></span>
                                        <span class="learner-activity-detail-status" data-tone="<?= learner_escape((string) $availability['code']) ?>"><?= learner_escape((string) $availability['label']) ?></span>
                                    </div>
                                    <h1 id="activity-detail-title"><?= learner_escape((string) $activity['title']) ?></h1>
                                    <p class="learner-activity-detail-hero__school"><?= learner_icon('building', 18) ?> <span><?= learner_escape($schoolName) ?></span></p>
                                    <?php if ($activity['has_summary']): ?><p class="learner-activity-detail-hero__summary"><?= learner_escape((string) $activity['summary']) ?></p><?php endif; ?>
                                    <div class="learner-activity-detail-hero__meta">
                                        <span><?= learner_icon('calendar', 17) ?> <?= learner_escape($startDate) ?></span>
                                        <span><?= learner_icon('clock', 17) ?> <?= learner_escape($timeRangeDisplay) ?></span>
                                        <span><?= learner_icon('map-pin', 17) ?> <?= learner_escape($locationName) ?></span>
                                    </div>
                                </div>
                                <div class="learner-activity-detail-hero__cover">
                                    <img src="<?= learner_escape($coverImage) ?>" alt="<?= learner_escape($coverAlt) ?>" width="720" height="420">
                                </div>
                            </section>

                            <article class="learner-card learner-activity-detail-panel">
                                <div class="learner-activity-detail-info-strip" aria-label="Thông tin nhanh">
                                    <div><span><?= learner_icon('calendar', 18) ?></span><p>Ngày tổ chức<strong><?= learner_escape($startDate) ?></strong></p></div>
                                    <div><span><?= learner_icon('clock', 18) ?></span><p>Thời gian<strong><?= learner_escape($timeRangeDisplay) ?></strong></p></div>
                                    <div><span><?= learner_icon('map-pin', 18) ?></span><p>Địa điểm<strong><?= learner_escape($locationName) ?></strong></p></div>
                                    <div><span><?= learner_icon('users', 18) ?></span><p>Số lượng<strong data-activity-count><?= learner_escape($participants . '/' . $capacity . ' học sinh') ?></strong></p></div>
                                </div>

                                <?php if ($activity['has_description']): ?>
                                    <section class="learner-activity-detail-section">
                                        <h2>Giới thiệu hoạt động</h2>
                                        <p class="learner-activity-detail-description"><?= nl2br(learner_escape((string) $activity['description'])) ?></p>
                                    </section>
                                <?php endif; ?>

                                <?php if ($activity['has_experience_highlights']): ?>
                                    <section class="learner-activity-detail-section">
                                        <h2>Bạn sẽ trải nghiệm</h2>
                                        <div class="learner-activity-detail-experiences">
                                            <?php foreach ($activity['experience_highlights'] as $index => $experience): ?>
                                                <div><span><?= learner_icon(['lightbulb', 'users', 'sparkles'][$index % 3], 20) ?></span><p><?= learner_escape((string) $experience) ?></p></div>
                                            <?php endforeach; ?>
                                        </div>
                                    </section>
                                <?php endif; ?>

                                <?php if ($activity['has_skills']): ?>
                                    <section class="learner-activity-detail-section">
                                        <h2>Kỹ năng phát triển</h2>
                                        <div class="learner-activity-detail-skills">
                                            <?php foreach ($activity['skills'] as $skill): ?><span><?= learner_escape((string) $skill) ?></span><?php endforeach; ?>
                                        </div>
                                    </section>
                                <?php endif; ?>

                                <?php if ($activity['has_requirements'] || $activity['has_benefits']): ?>
                                    <div class="learner-activity-detail-checklists">
                                        <?php if ($activity['has_requirements']): ?>
                                            <section class="learner-activity-detail-section">
                                                <h2>Điều kiện tham gia</h2>
                                                <ul><?php foreach ($activity['requirements'] as $requirement): ?><li><?= learner_icon('check', 17) ?><span><?= learner_escape((string) $requirement) ?></span></li><?php endforeach; ?></ul>
                                            </section>
                                        <?php endif; ?>
                                        <?php if ($activity['has_benefits']): ?>
                                            <section class="learner-activity-detail-section">
                                                <h2>Quyền lợi &amp; cơ hội</h2>
                                                <ul><?php foreach ($activity['benefits'] as $benefit): ?><li><?= learner_icon('sparkles', 17) ?><span><?= learner_escape((string) $benefit) ?></span></li><?php endforeach; ?></ul>
                                            </section>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <section class="learner-activity-detail-section">
                                    <h2>Thông tin khác</h2>
                                    <dl class="learner-activity-detail-other-info">
                                        <div><dt>Hình thức</dt><dd><?= learner_escape($deliveryMode) ?></dd></div>
                                        <div><dt>Đối tượng</dt><dd><?= learner_escape((string) $activity['target_audience'] ?: 'Học sinh trong trường') ?></dd></div>
                                        <div><dt>Giờ trải nghiệm</dt><dd><?= learner_escape($hoursLabel) ?></dd></div>
                                        <div><dt>Chi phí</dt><dd><?= learner_escape($feeLabel) ?></dd></div>
                                        <div><dt>Chứng nhận</dt><dd><?= learner_escape((string) $activity['certificate_label'] ?: 'Theo chính sách hoạt động') ?></dd></div>
                                        <?php if ($activity['has_location_address']): ?><div><dt>Địa chỉ</dt><dd><?= learner_escape((string) $activity['location_address']) ?></dd></div><?php endif; ?>
                                        <?php if ((string) $activity['online_meeting_url'] !== ''): ?><div><dt>Phòng trực tuyến</dt><dd><a href="<?= learner_escape((string) $activity['online_meeting_url']) ?>" target="_blank" rel="noopener noreferrer">Mở liên kết an toàn</a></dd></div><?php endif; ?>
                                    </dl>
                                </section>
                            </article>
                        </div>

                        <aside class="learner-activity-detail-sidebar" aria-label="Đăng ký và liên hệ">
                            <section class="learner-card learner-activity-register-card">
                                <h2>Đăng ký tham gia</h2>
                                <div class="learner-activity-capacity">
                                    <div><strong data-activity-remaining><?= learner_escape((string) $remaining) ?></strong><span>chỗ còn lại</span></div>
                                    <span data-activity-participants><?= learner_escape($participants . '/' . $capacity) ?> đã đăng ký</span>
                                </div>
                                <progress data-activity-capacity-progress value="<?= learner_escape((string) $capacityPercent) ?>" max="100" aria-label="<?= learner_escape('Đã sử dụng ' . $capacityPercent . '% số chỗ') ?>"><?= learner_escape((string) $capacityPercent) ?>%</progress>
                                <dl class="learner-activity-register-facts">
                                    <div><dt>Mở đăng ký</dt><dd><?= learner_escape($registrationOpens) ?></dd></div>
                                    <div><dt>Hạn đăng ký</dt><dd><?= learner_escape($registrationCloses) ?></dd></div>
                                    <div><dt>Hạn hủy</dt><dd><?= learner_escape($cancellationCloses) ?></dd></div>
                                    <div><dt>Hình thức duyệt</dt><dd><?= learner_escape($approvalLabel) ?></dd></div>
                                    <div><dt>Chi phí</dt><dd><?= learner_escape($feeLabel) ?></dd></div>
                                    <div><dt>Giờ trải nghiệm</dt><dd><?= learner_escape($hoursLabel) ?></dd></div>
                                </dl>
                                <style>
                                .fade-in { animation: fadeIn 0.4s ease-in forwards; }
                                .fade-out { animation: fadeOut 0.3s ease-out forwards; }
                                @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
                                @keyframes fadeOut { from { opacity: 1; transform: translateY(0); } to { opacity: 0; transform: translateY(-10px); } }
                                </style>
                                
                                <div id="registration-action-container" style="display: <?= $isCurrentlyRegistered ? 'none' : 'block' ?>;">
                                    <button id="btn-register-custom" class="learner-btn learner-btn--primary learner-btn--block" type="button" <?= $ctaDisabled ? 'disabled' : '' ?> onclick="registerTicket('<?= $activity['id'] ?>')"><?= learner_escape($ctaLabel) ?></button>
                                    <p class="learner-registration-message" role="status" aria-live="polite" data-tone="outline"><?= learner_escape((string) $availability['explanation']) ?></p>
                                </div>

                                <div id="success-banner" style="display: <?= $isCurrentlyRegistered ? 'block' : 'none' ?>; background-color: #ecfdf5; border: 1px solid #10b981; border-radius: 12px; padding: 16px; margin-top: 16px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
                                    <div style="display: flex; align-items: flex-start; gap: 12px;">
                                        <div style="color: #10b981; flex-shrink: 0; display: flex; align-items: center; justify-content: center;">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 24px; height: 24px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        </div>
                                        <div style="flex-grow: 1;">
                                            <h4 style="margin: 0 0 4px 0; font-size: 15px; font-weight: 600; color: #065f46;">Đăng ký thành công!</h4>
                                            <p style="margin: 0 0 12px 0; font-size: 13px; color: #047857;">Hệ thống đã giữ chỗ. Hãy kiểm tra thông báo để nhận mã QR check-in.</p>
                                            <button type="button" id="btn-cancel-ticket" onclick="cancelTicket('<?= $activity['id'] ?>')" style="background-color: transparent; border: 1px solid #fecaca; color: #dc2626; padding: 6px 12px; border-radius: 6px; font-size: 13px; font-weight: 500; cursor: pointer; transition: all 0.2s ease;">Hủy vé</button>
                                        </div>
                                    </div>
                                </div>

                                <div id="cancel-banner" style="display: none; background-color: #fef3c7; border: 1px solid #f59e0b; border-radius: 12px; padding: 16px; margin-top: 16px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
                                    <div style="display: flex; align-items: flex-start; gap: 12px;">
                                        <div style="color: #f59e0b; flex-shrink: 0; display: flex; align-items: center; justify-content: center;">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 24px; height: 24px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        </div>
                                        <div style="flex-grow: 1;">
                                            <h4 style="margin: 0 0 4px 0; font-size: 15px; font-weight: 600; color: #92400e;">Đã hủy vé thành công</h4>
                                            <p style="margin: 0; font-size: 13px; color: #b45309;">Bạn đã rút tên khỏi danh sách. Có thể đăng ký lại bất kỳ lúc nào.</p>
                                        </div>
                                    </div>
                                </div>

                                <script>
                                function showElementWithFade(id) {
                                    const el = document.getElementById(id);
                                    if (!el) return;
                                    el.style.display = 'block';
                                    el.classList.remove('fade-out');
                                    el.classList.add('fade-in');
                                }

                                function hideElementWithFade(id, hideCompletely = true) {
                                    const el = document.getElementById(id);
                                    if (!el) return;
                                    el.classList.remove('fade-in');
                                    el.classList.add('fade-out');
                                    if (hideCompletely) {
                                        setTimeout(() => { el.style.display = 'none'; }, 300);
                                    }
                                }

                                function registerTicket(activityId) {
                                    const btn = document.getElementById('btn-register-custom');
                                    if (btn) btn.disabled = true;
                                    
                                    fetch('/app/learner/api/v1/activity-registrations.php', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'X-CSRF-Token': <?= json_encode((string) ($GLOBALS['learner_page_context']['csrfToken'] ?? '')) ?>
                                        },
                                        body: JSON.stringify({ action: 'register', activityId: activityId })
                                    })
                                    .then(res => res.json())
                                    .then(data => {
                                        if (btn) btn.disabled = false;
                                        if (data.status === 'success' || data.status === 200 || data.status === 201 || (data.payload && data.payload.data)) {
                                            hideElementWithFade('registration-action-container');
                                            hideElementWithFade('cancel-banner');
                                            setTimeout(() => { showElementWithFade('success-banner'); }, 300);
                                        } else {
                                            alert(data.message || (data.payload && data.payload.error && data.payload.error.message) || 'Có lỗi xảy ra.');
                                        }
                                    })
                                    .catch(() => {
                                        if (btn) btn.disabled = false;
                                        alert('Có lỗi xảy ra, vui lòng thử lại sau.');
                                    });
                                }

                                function cancelTicket(activityId) {
                                    const btn = document.getElementById('btn-cancel-ticket');
                                    if (btn) btn.disabled = true;
                                    
                                    if (!confirm('Bạn có chắc chắn muốn hủy vé tham gia hoạt động này?')) {
                                        if (btn) btn.disabled = false;
                                        return;
                                    }
                                    
                                    fetch('/app/learner/api/v1/activity-registrations.php', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'X-CSRF-Token': <?= json_encode((string) ($GLOBALS['learner_page_context']['csrfToken'] ?? '')) ?>
                                        },
                                        body: JSON.stringify({ action: 'cancel_ticket', activityId: activityId })
                                    })
                                    .then(res => res.json())
                                    .then(data => {
                                        if (btn) btn.disabled = false;
                                        if (data.status === 'success' || data.status === 200) {
                                            hideElementWithFade('success-banner');
                                            setTimeout(() => {
                                                showElementWithFade('cancel-banner');
                                                showElementWithFade('registration-action-container');
                                            }, 300);
                                        } else {
                                            alert(data.message || (data.payload && data.payload.error && data.payload.error.message) || 'Có lỗi xảy ra.');
                                        }
                                    })
                                    .catch(() => {
                                        if (btn) btn.disabled = false;
                                        alert('Có lỗi xảy ra, vui lòng thử lại sau.');
                                    });
                                }
                                </script>
                                <div class="learner-data-note"><?= learner_icon('info', 16) ?><p><?= $allowsLocalDemoMutation ? 'Chế độ demo: thay đổi chỉ được lưu cục bộ trên trình duyệt.' : 'Dữ liệu đăng ký từ máy chủ là nguồn chính thức.' ?></p></div>
                            </section>

                            <section class="learner-card learner-activity-contact-card">
                                <span class="learner-activity-contact-card__icon"><?= learner_icon('building', 22) ?></span>
                                <div><p>Thông tin liên hệ</p><h2><?= learner_escape($organizerName) ?></h2></div>
                                <dl>
                                    <div><dt>Người phụ trách</dt><dd><?= learner_escape($teacherName) ?></dd></div>
                                    <?php if (trim((string) $activity['organizer_contact']) !== ''): ?><div><dt>Đầu mối</dt><dd><?= learner_escape((string) $activity['organizer_contact']) ?></dd></div><?php endif; ?>
                                    <?php if ($activity['has_organizer_email']): ?><div><dt>Email</dt><dd><a href="mailto:<?= learner_escape((string) $activity['organizer_email']) ?>"><?= learner_escape((string) $activity['organizer_email']) ?></a></dd></div><?php endif; ?>
                                    <?php if ($activity['has_organizer_phone']): ?><div><dt>Điện thoại</dt><dd><?= learner_escape((string) $activity['organizer_phone']) ?></dd></div><?php endif; ?>
                                </dl>
                                <?php if (!$activity['has_contact']): ?><p class="learner-activity-contact-card__fallback">Liên hệ đơn vị tổ chức</p><?php endif; ?>
                            </section>
                        </aside>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>
<?php if ($boot !== null): ?>
    <script id="learner-activities-boot" type="application/json"><?= json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<?php endif; ?>
<script src="../../assets/js/learner-api.js"></script>
<script src="../../assets/js/learner.js"></script>
<script src="../../assets/js/learner-activities.js"></script>
</body>
</html>
