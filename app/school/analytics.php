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
        'students' => $count,
    ];
}

$maxStudents = max(1, max(array_column($monthlyStats, 'students')));

$talentDistribution = $service->verifiedSkillDistribution($userId);

$gradeStats = [];
$grouped = [];
foreach ($classes as $class) {
    $grouped[$class['grade']][] = $class;
}
ksort($grouped);
foreach ($grouped as $grade => $items) {
    $sum = array_sum(array_column($items, 'students'));
    $avg = count($items) > 0
        ? (int) round(array_sum(array_column($items, 'completion')) / count($items))
        : 0;
    $gradeStats[] = [
        'grade'      => $grade,
        'students'   => $sum,
        'completion' => $avg,
    ];
}

$schoolInfo = [
    'name'          => $school['name'],
    'logo_initials' => mb_substr($school['name'], 0, 2),
    'level'         => $school['level'] ?? 'Đại học / Cao đẳng',
    'district'      => $school['address'] ?? '',
    'academic_year' => $school['academicYear'] ?? '',
];

$currentRoute = '/app/school/analytics.php';
$pageTitle = 'Phân tích dữ liệu';

ob_start();
?>
<?php
$pageDescription = 'Tổng quan số liệu theo tháng, phân bố năng khiếu và hoàn thiện theo khối.';
include __DIR__ . '/includes/page-banner.php';
?>

<div class="school-chart-container">
    <div class="school-chart-header">
        <h3 class="school-chart-title">Số lượng học sinh & Hoạt động theo tháng</h3>
    </div>
    <div style="display: flex; align-items: flex-end; gap: 0.5rem; height: 200px; padding: 1rem 0;">
        <?php foreach ($monthlyStats as $stat):
            $height = ($stat['students'] / $maxStudents) * 160; ?>
            <div style="flex: 1; display: flex; flex-direction: column; align-items: center; gap: 0.25rem;">
                <div style="width: 100%; background: linear-gradient(180deg, #3B82F6 0%, #93C5FD 100%); border-radius: 4px 4px 0 0; height: <?= $height; ?>px; min-height: 16px;" title="<?= $stat['students']; ?> học sinh"></div>
                <span style="font-size: 0.6875rem; color: var(--text-muted);"><?= $stat['month']; ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem; margin-bottom: 1.5rem;">
    <div class="school-section-box">
        <div class="school-section-box__header">
            <h3 class="school-section-box__title">Phân bố năng khiếu</h3>
            <p class="school-section-box__subtitle">Theo lĩnh vực</p>
        </div>
        <div style="display: flex; flex-direction: column; gap: 1rem;">
            <?php if ($talentDistribution === []): ?><p>Chưa có kỹ năng đã xác minh.</p><?php endif; ?>
            <?php foreach ($talentDistribution as $talent): ?>
                <div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.375rem;">
                        <span style="font-size: 0.875rem; font-weight: 500;"><?= htmlspecialchars($talent['name']); ?></span>
                        <span style="font-size: 0.875rem; color: var(--text-muted);"><?= $talent['count']; ?> HS (<?= $talent['percentage']; ?>%)</span>
                    </div>
                    <div style="height: 8px; background: var(--background); border-radius: 4px; overflow: hidden;">
                        <div style="height: 100%; width: <?= $talent['percentage']; ?>%; background: linear-gradient(90deg, #3B82F6 0%, #60A5FA 100%); border-radius: 4px;"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="school-section-box">
        <div class="school-section-box__header">
            <h3 class="school-section-box__title">Tổng quan theo khối</h3>
            <p class="school-section-box__subtitle">Số lượng & tỷ lệ hoàn thiện</p>
        </div>
        <div style="display: flex; flex-direction: column; gap: 1.25rem;">
            <?php foreach ($gradeStats as $grade): ?>
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <div style="width: 80px; font-size: 0.875rem; font-weight: 600;"><?= htmlspecialchars($grade['grade']); ?></div>
                    <div style="flex: 1;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem;">
                            <span style="font-size: 0.8125rem; color: var(--text-secondary);"><?= $grade['students']; ?> học sinh</span>
                            <span style="font-size: 0.8125rem; font-weight: 600; color: #2563EB;"><?= $grade['completion']; ?>%</span>
                        </div>
                        <div style="height: 6px; background: var(--background); border-radius: 3px; overflow: hidden;">
                            <div style="height: 100%; width: <?= $grade['completion']; ?>%; background: #2563EB; border-radius: 3px;"></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<section class="school-section-box school-ai-insight" data-school-ai-insight aria-labelledby="school-ai-insight-title">
    <div class="school-section-box__header"><h3 class="school-section-box__title" id="school-ai-insight-title">AI giải thích xu hướng năng lực</h3><p class="school-section-box__subtitle">Chỉ dùng dữ liệu tổng hợp của nhóm đủ lớn; không hiển thị hồ sơ cá nhân.</p></div>
    <p data-school-ai-state role="status" aria-live="polite">Đang tải phân tích...</p>
    <div data-school-ai-content hidden><p data-school-ai-summary></p><ul data-school-ai-priorities></ul><div data-school-ai-cohorts></div><small data-school-ai-provenance></small></div>
</section>
<?php
$pageBody = ob_get_clean();

$extraStyles = <<<'HTML'
<style>
@media (max-width: 768px) {
    [style*="grid-template-columns: repeat(2, 1fr)"] { grid-template-columns: 1fr !important; }
}
</style>
HTML;
$extraScripts = '<script src="../../assets/js/school-ai-insights.js" defer></script>';

require __DIR__ . '/includes/layout.php';
