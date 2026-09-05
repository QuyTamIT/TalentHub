<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$page = (string) file_get_contents($root . '/app/learner/activity-detail.php');
$css = (string) file_get_contents($root . '/app/learner/assets/activities/activities.css');
$javascript = (string) file_get_contents($root . '/assets/js/learner-activities.js');
$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$assert(str_contains($page, 'learner_activity_find('), 'Detail uses learner_activity_find().');
$assert(!str_contains($page, 'activity()->all()') && !str_contains($page, 'findById('), 'Detail route has no unscoped repository read.');
$assert(str_contains($page, "\$activityNavigationActive = 'discover'"), 'Detail keeps Discover active.');
$assert(str_contains($page, "includes/activity-navigation.php"), 'Detail includes shared activity navigation.');
$assert(str_contains($page, 'learner-activity-detail-grid'), 'Detail has the two-column module layout.');
$assert(str_contains($page, 'learner-activity-detail-hero__cover'), 'Detail hero renders a local cover/illustration region.');
$fallbackCover = 'assets/activities/illustrations/hero-detail.svg';
$assert(str_contains($page, "\$coverImage = '{$fallbackCover}'"), 'Missing or rejected cover uses the approved detail illustration path.');
$assert(!str_contains($page, "\$coverImage = 'assets/activities/hero-detail.svg'"), 'Detail no longer references the invalid legacy fallback path.');
$assert(is_file($root . '/app/learner/' . $fallbackCover), 'Approved detail fallback illustration exists on disk.');
$assert(str_contains($page, 'learner-activity-detail-info-strip'), 'Detail renders the four-item information strip.');
foreach (['Giới thiệu hoạt động', 'Bạn sẽ trải nghiệm', 'Kỹ năng phát triển', 'Điều kiện tham gia', 'Quyền lợi &amp; cơ hội', 'Thông tin khác', 'Đăng ký tham gia', 'Thông tin liên hệ'] as $copy) {
    $assert(str_contains($page, $copy), "Detail renders {$copy}.");
}
foreach (['school_name', 'responsible_teacher_name', 'location_name', 'location_address', 'delivery_mode_label', 'organizer_name', 'organizer_email', 'organizer_phone', 'target_audience', 'confirmed_hours', 'fee_label', 'certificate_label', 'registration_opens_at', 'registration_closes_at', 'cancellation_closes_at', 'cover_image_url', 'cover_image_alt'] as $field) {
    $assert(str_contains($page, "\$activity['{$field}']"), "Detail consumes {$field} from the scoped read model.");
}
$assert(str_contains($page, '<progress') && str_contains($page, 'aria-label='), 'Registration capacity uses a semantic labelled progress element.');
$assert(
    preg_match('/learner-activity-register-facts.*?<dt>Chi phí<\/dt>.*?<dt>Giờ trải nghiệm<\/dt>/s', $page) === 1,
    'Registration sidebar repeats the real fee and confirmed experience hours.'
);
$assert(str_contains($page, 'data-registration-message') && str_contains($page, 'aria-live="polite"'), 'Registration feedback is a polite live region.');
$assert(str_contains($page, 'Không tìm thấy hoạt động'), 'Invalid or foreign detail has a safe not-found state.');
$assert(!str_contains($page, 'Không tham gia'), 'Phase 7 does not add the Phase 8 no-show UI label.');
$assert(preg_match('/<\?=\s*\$activity\[/m', $page) !== 1, 'Activity values are never emitted without an escaping/formatting boundary.');
$assert(!preg_match('/innerHTML|outerHTML|insertAdjacentHTML/', $javascript), 'Detail interactions never parse server values as HTML.');

foreach (['.learner-activities-shell .learner-activity-detail-grid', '.learner-activities-shell .learner-activity-detail-sidebar', '.learner-activities-shell .learner-activity-detail-info-strip', '@media (max-width: 1024px)', '@media (max-width: 768px)', '@media (max-width: 390px)'] as $marker) {
    $assert(str_contains($css, $marker), "Activity stylesheet contains {$marker}.");
}
$assert(!str_contains($css, ':root') && preg_match('/(^|[}\n])\s*body\b/m', $css) !== 1, 'Activity CSS does not reset root/body.');

if ($failures !== []) {
    fwrite(STDERR, "learner_activity_detail_page_test: RED\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "learner_activity_detail_page_test: OK\n";
