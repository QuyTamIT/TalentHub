<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$page = (string) file_get_contents($root . '/app/learner/activities.php');
$cssPath = $root . '/app/learner/assets/activities/activities.css';
$css = is_file($cssPath) ? (string) file_get_contents($cssPath) : '';
$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$assert(str_contains($page, '$activityCatalog = learner_activity_catalog();'), 'Discovery uses only the scoped catalog helper.');
$assert(!str_contains($page, 'activity()->all()') && !str_contains($page, 'learner_activity_repository()->all()'), 'Discovery never reads the global catalog.');
$assert(str_contains($page, 'TRẢI NGHIỆM ĐỂ TRƯỞNG THÀNH'), 'Discovery hero contains the approved eyebrow.');
$assert(str_contains($page, 'Khám phá hoạt động'), 'Discovery hero contains the approved title.');
$assert(str_contains($page, 'illustrations/hero-discover.svg'), 'Discovery uses the approved local hero illustration.');
$assert(str_contains($page, 'count($activityCatalog)') && str_contains($page, '$participantCount') && str_contains($page, '$newActivityCount'), 'Hero KPI values are derived from scoped data.');

foreach (['Tìm hoạt động, kỹ năng, trường...', 'Thời gian', 'Tất cả', 'Kỹ thuật', 'Kinh doanh', 'Sáng tạo', 'Cộng đồng', 'Chỉ hiển thị hoạt động còn hạn và còn chỗ'] as $copy) {
    $assert(str_contains($page, $copy), "Discovery contains {$copy}.");
}
foreach (['cover_image_url', 'cover_image_alt', 'title', 'school_name', 'start_at', 'location_name', 'registration_closes_at', 'participants', 'capacity'] as $field) {
    $assert(str_contains($page, "\$activity['{$field}']"), "Discovery renders {$field} from the scoped record.");
}
$assert(str_contains($page, 'data-activity-card') && str_contains($page, 'data-activity-search'), 'Cards expose only escaped filter attributes.');
$assert(str_contains($page, 'rawurlencode((string) $activity[\'id\'])'), 'Detail CTA encodes the canonical UUID.');
$assert(str_contains($page, 'learner_escape($activity[\'cover_image_alt\'])'), 'Cover alt text is escaped.');
$assert(str_contains($page, 'Không có hoạt động đang mở'), 'Server-empty state is distinct.');
$assert(str_contains($page, 'Không tìm thấy kết quả'), 'Client-filter empty state is distinct.');
$assert(!str_contains($page, 'learner-activities-boot'), 'Discovery has no JSON payload containing filtered-out activities.');
$assert(str_contains($page, '../../assets/js/learner-activities.js'), 'Discovery loads only its scoped interaction script.');

$assert(is_file($cssPath), 'Scoped Phase 6 activity stylesheet exists.');
foreach (['#F97316', '#EA580C', '#FFF7ED', '#2563EB', '#EFF6FF', '#16A34A', '#F8FAFC', '#FFFFFF', '#0F172A', '#64748B', '#E2E8F0', "'Be Vietnam Pro'", '@media (max-width: 1024px)', '@media (max-width: 768px)', '@media (max-width: 390px)', 'prefers-reduced-motion', ':focus-visible'] as $marker) {
    $assert(str_contains($css, $marker), "Activity stylesheet contains {$marker}.");
}
$media768Start = strpos($css, '@media (max-width: 768px)');
$media390Start = strpos($css, '@media (max-width: 390px)');
$reducedMotionStart = strpos($css, '@media (prefers-reduced-motion: reduce)');
$media768 = $media768Start !== false && $media390Start !== false
    ? substr($css, $media768Start, $media390Start - $media768Start)
    : '';
$media390 = $media390Start !== false && $reducedMotionStart !== false
    ? substr($css, $media390Start, $reducedMotionStart - $media390Start)
    : '';
$assert(
    str_contains($media768, '.learner-activity-discovery-grid')
        && str_contains($media768, 'grid-template-columns: repeat(2, minmax(0, 1fr));'),
    '768px tablet discovery keeps a two-column card grid.'
);
$assert(
    str_contains($media390, '.learner-activity-discovery-grid')
        && str_contains($media390, 'grid-template-columns: 1fr;'),
    '390px mobile discovery uses a one-column card grid.'
);
$assert(!str_contains($css, ':root') && preg_match('/(^|[}\n])\s*body\b/m', $css) !== 1, 'Activity stylesheet does not reset root or body.');
$selectorGroups = [];
preg_match_all('/(?:^|})\s*([^{}]+)\{/m', $css, $selectorGroups);
foreach ($selectorGroups[1] ?? [] as $selectorGroup) {
    $selectorGroup = trim($selectorGroup);
    if ($selectorGroup === '' || str_starts_with($selectorGroup, '@')) {
        continue;
    }
    foreach (explode(',', $selectorGroup) as $selector) {
        $assert(str_starts_with(trim($selector), '.learner-activities-shell'), "Unscoped activity selector: {$selector}");
    }
}

if ($failures !== []) {
    fwrite(STDERR, "learner_activity_discovery_page_test: RED\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "learner_activity_discovery_page_test: OK\n";
