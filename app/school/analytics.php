<?php
/**
 * TalentHub - School Dashboard Analytics Page
 * Phân tích dữ liệu chi tiết cho Nhà trường (data from DB).
 */
declare(strict_types=1);

require dirname(__DIR__, 2) . '/bin/bootstrap.php';
require dirname(__DIR__, 2) . '/src/Bootstrap/SchoolAppContext.php';

use TalentHub\Bootstrap\SchoolAppContext;

$context = (new SchoolAppContext())->boot();
$school     = $context['school'];
$dashboard  = $context['dashboard'];
$service    = $context['service'];
$userId     = $context['user']['id'];

$metrics = $dashboard['metrics'];
$classes = $service->classes($userId);

$currentAcademicYear = $school['academicYear'] ?? '2025 - 2026';

$analytics = $service->analytics($userId);

// Fill missing months with 0 so chart stays consistent (last 12 months)
$monthlyStats = [];
$today = new DateTime('now');
for ($i = 11; $i >= 0; $i--) {
    $dt = (clone $today)->modify("-{$i} months");
    $monthKey = $dt->format('Y-m');
    $count = 0;
    foreach ($analytics['monthly'] as $row) {
        if ($row['month'] === $monthKey) {
            $count = $row['count'];
            break;
        }
    }
    $monthlyStats[] = [
        'month'    => 'T' . (int) $dt->format('n'),
        'yearMo'   => $monthKey,
        'events'   => $count,
    ];
}

$maxEvents = max(1, max(array_column($monthlyStats, 'events')));
$domainMetrics = $analytics['domainMetrics'];
$metricCards = [
    ['label' => 'Học sinh active', 'value' => number_format((int) $domainMetrics['activeStudents'])],
    ['label' => 'Giáo viên active', 'value' => number_format((int) $domainMetrics['activeTeachers'])],
    ['label' => 'Hoạt động published', 'value' => number_format((int) $domainMetrics['publishedActivities'])],
    ['label' => 'Đăng ký approved', 'value' => number_format((int) $domainMetrics['approvedRegistrations'])],
    ['label' => 'Check-in confirmed', 'value' => number_format((int) $domainMetrics['confirmedCheckins'])],
    ['label' => 'Đánh giá published', 'value' => number_format((int) $domainMetrics['publishedAssessments'])],
    ['label' => 'Kỹ năng verified', 'value' => number_format((int) $domainMetrics['verifiedSkills'])],
    ['label' => 'Đối tác approved', 'value' => number_format((int) $domainMetrics['approvedEnterprisePartners'])],
    ['label' => 'Tin thực tập active', 'value' => number_format((int) $domainMetrics['activeInternshipPosts'])],
    ['label' => 'Ứng tuyển accepted', 'value' => number_format((int) $domainMetrics['acceptedInternshipApplications'])],
    ['label' => 'Dự án đang chạy', 'value' => number_format((int) $domainMetrics['activeProjects'])],
    ['label' => 'Tài trợ đã thanh toán', 'value' => number_format((float) $domainMetrics['paidSponsorshipAmount'], 0, ',', '.') . ' ₫'],
];

$gradeStats = [];
$grouped = [];
foreach ($classes as $class) {
    $grouped[$class['grade']][] = $class;
}
ksort($grouped);
foreach ($grouped as $grade => $items) {
    $sum = array_sum(array_column($items, 'students'));
    $gradeStats[] = [
        'grade'      => $grade,
        'students'   => $sum,
        'classes'    => count($items),
    ];
}

$schoolInfo = [
    'name'          => $school['name'],
    'logo_initials' => mb_substr($school['name'], 0, 2),
    'level'         => $school['level'] ?? 'Trung học',
    'district'      => $school['address'] ?? '',
    'academic_year' => $school['academicYear'] ?? '',
];

$currentRoute = '/app/school/analytics.php';
$pageTitle = 'Phân tích dữ liệu';

ob_start();
?>
<?php
$pageDescription = 'Các metric được tổng hợp trực tiếp từ dữ liệu thuộc trường hiện tại.';
include __DIR__ . '/includes/page-banner.php';
?>

<div class="school-metric-grid">
    <?php foreach ($metricCards as $card): ?>
        <article class="school-kpi-card">
            <div class="school-kpi-card__label"><?= htmlspecialchars($card['label']); ?></div>
            <div class="school-kpi-card__value"><?= htmlspecialchars($card['value']); ?></div>
        </article>
    <?php endforeach; ?>
</div>

<div class="school-chart-container">
    <div class="school-chart-header">
        <h3 class="school-chart-title">Thao tác quản trị theo tháng</h3>
    </div>
    <div style="display: flex; align-items: flex-end; gap: 0.5rem; height: 200px; padding: 1rem 0;">
        <?php foreach ($monthlyStats as $stat):
            $height = ($stat['events'] / $maxEvents) * 160; ?>
            <div style="flex: 1; display: flex; flex-direction: column; align-items: center; gap: 0.25rem;">
                <div style="width: 100%; background: linear-gradient(180deg, #3B82F6 0%, #93C5FD 100%); border-radius: 4px 4px 0 0; height: <?= $height; ?>px; min-height: 2px;" title="<?= $stat['events']; ?> thao tác"></div>
                <span style="font-size: 0.6875rem; color: var(--text-muted);"><?= $stat['month']; ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem; margin-bottom: 1.5rem;">
    <div class="school-section-box">
        <div class="school-section-box__header">
            <h3 class="school-section-box__title">Loại thao tác quản trị</h3>
            <p class="school-section-box__subtitle"><?= number_format((int) $analytics['totalEvents']); ?> audit events</p>
        </div>
        <div style="display: flex; flex-direction: column; gap: 1rem;">
            <?php foreach ($analytics['actions'] as $action): ?>
                <div style="display:flex;justify-content:space-between;gap:1rem;">
                    <span style="font-size:0.875rem;font-weight:500;"><?= htmlspecialchars($action['action']); ?></span>
                    <strong><?= number_format((int) $action['count']); ?></strong>
                </div>
            <?php endforeach; ?>
            <?php if ($analytics['actions'] === []): ?><p>Chưa có thao tác được ghi nhận.</p><?php endif; ?>
        </div>
    </div>

    <div class="school-section-box">
        <div class="school-section-box__header">
            <h3 class="school-section-box__title">Tổng quan theo khối</h3>
            <p class="school-section-box__subtitle">Số lớp và học sinh active</p>
        </div>
        <div style="display: flex; flex-direction: column; gap: 1.25rem;">
            <?php foreach ($gradeStats as $grade): ?>
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <div style="width: 80px; font-size: 0.875rem; font-weight: 600;"><?= htmlspecialchars($grade['grade']); ?></div>
                    <div style="flex:1;display:flex;justify-content:space-between;">
                        <span style="font-size:0.8125rem;color:var(--text-secondary);"><?= number_format((int) $grade['students']); ?> học sinh</span>
                        <span style="font-size:0.8125rem;font-weight:600;color:#2563EB;"><?= number_format((int) $grade['classes']); ?> lớp</span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php
$pageBody = ob_get_clean();

$extraStyles = <<<'HTML'
<style>
.school-metric-grid { display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.5rem; }
@media (max-width: 768px) {
    [style*="grid-template-columns: repeat(2, 1fr)"] { grid-template-columns: 1fr !important; }
    .school-metric-grid { grid-template-columns:repeat(2,1fr); }
}
</style>
HTML;

require __DIR__ . '/includes/layout.php';
