<?php
/**
 * TalentHub - School Dashboard Students Page
 * Danh sách học sinh của nhà trường + lọc theo lớp + phân trang.
 */
declare(strict_types=1);

require dirname(__DIR__, 2) . '/bin/bootstrap.php';
require dirname(__DIR__, 2) . '/src/Bootstrap/SchoolAppContext.php';

use TalentHub\Bootstrap\SchoolAppContext;
use TalentHub\Support\Uuid;

$context = (new SchoolAppContext())->boot();
$service = $context['service'];
$userId  = $context['user']['id'];

$classFilter = $_GET['classId'] ?? null;
if ($classFilter !== null && !Uuid::isValid($classFilter)) {
    $classFilter = null;
}

$perPage = max(10, min(100, (int) ($_GET['perPage'] ?? 25)));
$page    = max(1, (int) ($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

// Khi không filter lớp, dùng phân trang server-side.
// Khi có filter lớp, lấy tất cả rồi filter client-side (vì class thường < 50 hs).
if ($classFilter === null) {
    $students = $service->students($userId, $perPage, $offset);
    $totalApprox = ($page * $perPage) + (count($students) === $perPage ? 1 : 0);
} else {
    $allStudents = $service->students($userId, 1000);
    $students = array_values(array_filter(
        $allStudents,
        static fn(array $s) => $s['classId'] === $classFilter
    ));
    $totalApprox = count($students);
}

$classes = $service->classesWithArchived($userId);

$schoolInfo = [
    'name'          => $context['school']['name'],
    'logo_initials' => mb_substr($context['school']['name'], 0, 2),
    'level'         => $context['school']['level'] ?? 'Trung học',
    'district'      => $context['school']['address'] ?? '',
    'academic_year' => $context['school']['academicYear'] ?? '',
];

$currentRoute = '/app/school/students.php';
$pageTitle    = 'Học sinh';

$baseQuery = http_build_query(array_filter([
    'classId' => $classFilter,
    'perPage' => $perPage !== 25 ? $perPage : null,
]));

ob_start();
?>
<div class="school-section-box" style="margin-bottom: 1.5rem;">
    <div style="display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.25rem;">
                Danh sách học sinh
            </h2>
            <p style="font-size: 0.875rem; color: var(--text-secondary); margin: 0;">
                <?= count($students); ?> học sinh
                <?= $classFilter ? '(trong lớp đã chọn)' : '(trang ' . $page . ')'; ?>
            </p>
        </div>
        <div style="display: flex; gap: 0.5rem; align-items: center;">
            <form method="get" style="display:flex; gap: 0.5rem; align-items: center;">
                <select name="classId" onchange="this.form.submit()" class="school-inline-select" aria-label="Lọc theo lớp">
                    <option value="">Tất cả lớp</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?= htmlspecialchars($c['id']); ?>" <?= $classFilter === $c['id'] ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($c['name']); ?> - <?= htmlspecialchars($c['grade']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($page > 1): ?>
                    <input type="hidden" name="page" value="<?= $page; ?>">
                <?php endif; ?>
                <input type="hidden" name="perPage" value="<?= $perPage; ?>">
            </form>
            <a href="/app/school/student-edit.php" class="btn btn-primary btn-sm">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                Thêm học sinh
            </a>
        </div>
    </div>
</div>

<div class="school-section-box">
    <?php if ($students === []): ?>
        <p style="text-align: center; color: var(--text-muted); padding: 2rem 0;">
            Chưa có học sinh nào trong lớp đã chọn.
        </p>
    <?php else: ?>
        <table class="school-class-table">
            <thead>
                <tr>
                    <th>Họ và tên</th>
                    <th>Email</th>
                    <th>Lớp</th>
                    <th>SĐT</th>
                    <th>Trạng thái</th>
                    <th style="text-align: right;">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $s): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($s['fullName']); ?></strong></td>
                        <td><span style="font-size: 0.875rem; color: var(--text-secondary);"><?= htmlspecialchars($s['email']); ?></span></td>
                        <td>Lớp <?= htmlspecialchars($s['className']); ?> (Khối <?= (int) $s['gradeLevel']; ?>)</td>
                        <td><?= htmlspecialchars($s['phone']); ?></td>
                        <td>
                            <span class="school-class-badge school-class-badge--<?= $s['studyStatus'] === 'active' ? 'success' : 'warning'; ?>">
                                <?= htmlspecialchars($s['studyStatus']); ?>
                            </span>
                        </td>
                        <td style="text-align: right;">
                            <a href="/app/school/student-edit.php?id=<?= urlencode($s['id']); ?>" class="btn btn-sm btn-outline" style="text-decoration:none;">Sửa</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($classFilter === null): ?>
            <nav class="school-pagination" aria-label="Phân trang">
                <?php if ($page > 1): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="btn btn-sm btn-outline">‹ Trước</a>
                <?php endif; ?>
                <span class="school-pagination__info">Trang <?= $page; ?> · <?= $perPage; ?> / trang</span>
                <?php if (count($students) === $perPage): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="btn btn-sm btn-outline">Sau ›</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php
$pageBody = ob_get_clean();

$extraStyles = <<<'HTML'
<style>
.school-inline-select { padding: 0.5rem 0.75rem; border-radius: var(--radius-sm); border: 1px solid var(--border); background: var(--surface); font-size: 0.875rem; }
.school-pagination { display: flex; align-items: center; gap: 0.75rem; margin-top: 1rem; justify-content: flex-end; }
.school-pagination__info { font-size: 0.8125rem; color: var(--text-muted); }
</style>
HTML;

$extraScripts = <<<'HTML'
<script>
document.addEventListener('DOMContentLoaded', function() {
    const params = new URLSearchParams(window.location.search);
    if (params.has('msg')) {
        const map = { created: 'Đã thêm học sinh.', updated: 'Đã cập nhật học sinh.' };
        const key = params.get('msg');
        if (map[key]) showSchoolToast(map[key]);
    }
});
</script>
HTML;

require __DIR__ . '/includes/layout.php';