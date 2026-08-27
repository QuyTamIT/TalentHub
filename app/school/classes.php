<?php
/**
 * TalentHub - School Dashboard Classes Page
 * Quản lý Lớp & Khối cho Nhà trường (data from DB).
 */
declare(strict_types=1);

require dirname(__DIR__, 2) . '/bin/bootstrap.php';
require dirname(__DIR__, 2) . '/src/Bootstrap/SchoolAppContext.php';

use TalentHub\Bootstrap\SchoolAppContext;

$context = (new SchoolAppContext())->boot();
$school  = $context['school'];
$service = $context['service'];
$userId  = $context['user']['id'];

$showArchived = !empty($_GET['archived']);
$classes = $showArchived
    ? $service->classesWithArchived($userId)
    : $service->classes($userId);

$grades = [];
foreach ($classes as $class) {
    $grades[$class['grade']][] = $class;
}
ksort($grades);

$gradeStats = [];
foreach ($grades as $gradeName => $gradeClasses) {
    $studentSum = array_sum(array_column($gradeClasses, 'students'));
    $avgCompletion = count($gradeClasses) > 0
        ? round(array_sum(array_column($gradeClasses, 'completion')) / count($gradeClasses))
        : 0;
    $gradeStats[] = [
        'name'          => $gradeName,
        'classes'       => count($gradeClasses),
        'students'      => $studentSum,
        'avgCompletion' => $avgCompletion,
    ];
}

$totalStudents = array_sum(array_column($classes, 'students'));

$schoolInfo = [
    'name'          => $school['name'],
    'logo_initials' => mb_substr($school['name'], 0, 2),
    'level'         => $school['level'] ?? 'Đại học / Cao đẳng',
    'district'      => $school['address'] ?? '',
    'academic_year' => $school['academicYear'] ?? '',
];

$currentRoute = '/app/school/classes.php';
$pageTitle    = 'Lớp & Chuyên ngành';

ob_start();
?>
<?php
$pageDescription = 'Quản lý các lớp và chuyên ngành đào tạo, xem sĩ số sinh viên và tỷ lệ hoàn thiện hồ sơ.';
$pageActions = '<a href="./class-edit.php" class="btn btn-primary">+ Thêm lớp mới</a>';
include __DIR__ . '/includes/page-banner.php';
?>

<div style="display: flex; justify-content: flex-end; margin-bottom: 1rem;">
    <a href="?<?= $showArchived ? '' : 'archived=1'; ?>" class="btn btn-sm btn-outline">
        <?= $showArchived ? 'Chỉ lớp đang hoạt động' : 'Hiển thị cả lớp lưu trữ'; ?>
    </a>
</div>

<div class="school-grade-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.25rem; margin-bottom: 1.75rem;">
    <?php foreach ($gradeStats as $stat): ?>
        <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 1.25rem;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
                <h3 style="font-size: 1.125rem; font-weight: 700; color: var(--text-primary); margin: 0;">
                    <?= htmlspecialchars($stat['name']) ?>
                </h3>
                <span style="font-size: 0.75rem; font-weight: 600; padding: 0.2rem 0.5rem; border-radius: 4px; background: #EFF6FF; color: #2563EB;">
                    <?= $stat['classes'] ?> lớp
                </span>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: flex-end;">
                <div>
                    <div style="font-size: 1.5rem; font-weight: 700; color: var(--text-primary);"><?= $stat['students'] ?></div>
                    <div style="font-size: 0.8125rem; color: var(--text-muted);">sinh viên</div>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 1.125rem; font-weight: 700; color: #2563EB;"><?= $stat['avgCompletion'] ?>%</div>
                    <div style="font-size: 0.75rem; color: var(--text-muted);">hoàn thiện TB</div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php foreach ($grades as $gradeName => $gradeClasses): ?>
    <div style="margin-bottom: 2rem;">
        <h3 style="font-size: 1rem; font-weight: 600; color: var(--text-primary); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2" aria-hidden="true">
                <path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>
                <path d="M6 12v5c3 3 9 3 12 0v-5"></path>
            </svg>
            <?= htmlspecialchars($gradeName) ?>
            <span style="font-size: 0.8125rem; font-weight: 500; color: var(--text-muted);">(<?= count($gradeClasses) ?> lớp)</span>
        </h3>
        <div class="school-class-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1rem;">
            <?php foreach ($gradeClasses as $class): ?>
                <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 1.25rem; transition: all 0.2s;">
                    <div style="display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 1rem;">
                        <div>
                            <h4 style="font-size: 1.125rem; font-weight: 700; color: var(--text-primary); margin: 0 0 0.25rem 0;">
                                <?= htmlspecialchars($class['name']) ?>
                            </h4>
                            <p style="font-size: 0.8125rem; color: var(--text-secondary); margin: 0;">
                                Niên khóa: <?= htmlspecialchars($class['academicYear']) ?>
                            </p>
                        </div>
                        <span class="school-class-badge school-class-badge--<?= htmlspecialchars($class['status']); ?>">
                            <?= htmlspecialchars($class['statusText']); ?>
                        </span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.75rem;">
                        <div>
                            <span style="font-size: 1.5rem; font-weight: 700; color: var(--text-primary);"><?= $class['students'] ?></span>
                            <span style="font-size: 0.8125rem; color: var(--text-muted); margin-left: 0.25rem;">sinh viên</span>
                        </div>
                        <div style="text-align: right;">
                            <span style="font-size: 1rem; font-weight: 700; color: #2563EB;"><?= $class['completion'] ?>%</span>
                            <span style="font-size: 0.75rem; color: var(--text-muted);"> hồ sơ</span>
                        </div>
                    </div>
                    <div style="height: 6px; background: var(--background); border-radius: 3px; overflow: hidden; margin-bottom: 1rem;">
                        <div style="height: 100%; width: <?= $class['completion'] ?>%; background: <?= $class['completion'] >= 80 ? '#22C55E' : ($class['completion'] >= 70 ? '#F59E0B' : '#EF4444'); ?>; border-radius: 3px;"></div>
                    </div>
                    <div style="display: flex; gap: 0.5rem;">
                        <a href="./students.php?classId=<?= urlencode($class['id']); ?>" class="btn btn-sm btn-outline" style="flex: 1; text-decoration:none;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                            Sinh viên
                        </a>
                        <a href="./class-edit.php?id=<?= urlencode($class['id']); ?>" class="btn btn-sm btn-outline" style="text-decoration:none;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                            </svg>
                            Sửa
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endforeach; ?>

<?php if ($classes === []): ?>
    <div class="school-section-box" style="text-align:center;padding:3rem 1.5rem;">
        <p style="color: var(--text-muted); margin-bottom:1rem;">Trường chưa có lớp học nào.</p>
        <a href="./class-edit.php" class="btn btn-primary">Tạo lớp đầu tiên</a>
    </div>
<?php endif; ?>
<?php
$pageBody = ob_get_clean();

$extraStyles = <<<'HTML'
<style>
@media (max-width: 1024px) { .school-grade-grid { grid-template-columns: repeat(2, 1fr) !important; } }
@media (max-width: 768px) {
    .school-grade-grid { grid-template-columns: 1fr !important; }
    .school-class-grid { grid-template-columns: 1fr !important; }
}
</style>
HTML;

$extraScripts = <<<'HTML'
<script>
document.addEventListener('DOMContentLoaded', function() {
    const params = new URLSearchParams(window.location.search);
    if (params.has('msg')) {
        const map = { created: 'Đã tạo lớp mới.', updated: 'Đã cập nhật lớp.', archived: 'Đã lưu trữ lớp.' };
        const key = params.get('msg');
        if (map[key]) showSchoolToast(map[key]);
    }
});
</script>
HTML;

require __DIR__ . '/includes/layout.php';
