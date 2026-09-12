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
$session = $context['session'];

$showArchived = !empty($_GET['archived']);
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;
    if ($action === 'delete') {
        $classId = isset($_POST['classId']) ? (string) $_POST['classId'] : '';
        try {
            $session->assertCsrf(isset($_POST['csrfToken']) ? (string) $_POST['csrfToken'] : null);
            $service->deleteClass($userId, $classId);
            header('Location: ./classes.php?msg=deleted' . ($showArchived ? '&archived=1' : ''));
            exit;
        } catch (\TalentHub\Http\ApiException $e) {
            $error = $e->getMessage();
        } catch (\Throwable $e) {
            $error = 'Đã xảy ra lỗi: ' . $e->getMessage();
        }
    }
}

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

<?php if (!empty($error)): ?>
    <div class="school-flash school-flash--error" role="alert" style="margin-bottom: 1.5rem; background: #FEF2F2; border: 1px solid #FCA5A5; color: #991B1B; border-radius: var(--radius-sm); padding: 0.875rem 1.25rem;">
        <?= htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<div style="display: flex; justify-content: flex-end; margin-bottom: 1rem;">
    <a href="?<?= $showArchived ? '' : 'archived=1'; ?>" class="btn btn-sm btn-outline">
        <?= $showArchived ? 'Chỉ lớp đang hoạt động' : 'Hiển thị cả lớp lưu trữ'; ?>
    </a>
</div>

<?php if (!empty($gradeStats)): ?>
<div class="school-grade-grid" style="grid-template-columns: repeat(3, 1fr); gap: 1.25rem; margin-bottom: 1.75rem;">
    <?php foreach ($gradeStats as $stat): ?>
        <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 1.25rem;">
            <div class="school-flex-between" style="margin-bottom: 1rem;">
                <h3 style="font-size: 1.125rem; font-weight: 700; color: var(--text-primary); margin: 0;">
                    <?= htmlspecialchars($stat['name']) ?>
                </h3>
                <span class="school-badge school-badge--info">
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
<?php endif; ?>

<?php foreach ($grades as $gradeName => $gradeClasses): ?>
    <div style="margin-bottom: 2rem;">
        <h3 class="school-flex-center" style="font-size: 1rem; font-weight: 600; color: var(--text-primary); margin-bottom: 1rem;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2" aria-hidden="true">
                <path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>
                <path d="M6 12v5c3 3 9 3 12 0v-5"></path>
            </svg>
            <?= htmlspecialchars($gradeName) ?>
            <span style="font-size: 0.8125rem; font-weight: 500; color: var(--text-muted);">(<?= count($gradeClasses) ?> lớp)</span>
        </h3>
        <div class="school-class-grid">
            <?php foreach ($gradeClasses as $class): ?>
                <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 1.25rem; transition: all 0.2s;">
                    <div class="school-flex-between" style="margin-bottom: 1rem;">
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
                    <div class="school-flex-between" style="margin-bottom: 0.75rem;">
                        <div>
                            <span style="font-size: 1.5rem; font-weight: 700; color: var(--text-primary);"><?= $class['students'] ?></span>
                            <span style="font-size: 0.8125rem; color: var(--text-muted); margin-left: 0.25rem;">sinh viên</span>
                        </div>
                        <div style="text-align: right;">
                            <span style="font-size: 1rem; font-weight: 700; color: #2563EB;"><?= $class['completion'] ?>%</span>
                            <span style="font-size: 0.75rem; color: var(--text-muted);"> hồ sơ</span>
                        </div>
                    </div>
                    <div class="school-progress-track" style="margin-bottom: 1rem;">
                        <div class="school-progress-fill" style="width: <?= $class['completion'] ?>%; background: <?= $class['completion'] >= 80 ? '#22C55E' : ($class['completion'] >= 70 ? '#F59E0B' : '#EF4444'); ?>;"></div>
                    </div>
                    <div class="school-flex-center" style="gap: 0.5rem;">
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
                        <button type="button"
                                class="btn btn-sm btn-outline js-delete-class-btn"
                                style="border-color:#FCA5A5;color:#B91C1C;text-decoration:none;display:inline-flex;align-items:center;gap:0.25rem;"
                                data-class-id="<?= htmlspecialchars($class['id'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-class-name="<?= htmlspecialchars($class['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                title="Xóa lớp">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <polyline points="3 6 5 6 21 6"></polyline>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                <line x1="10" y1="11" x2="10" y2="17"></line>
                                <line x1="14" y1="11" x2="14" y2="17"></line>
                            </svg>
                            Xóa
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endforeach; ?>

<?php if ($classes === []): ?>
    <div class="school-section-box school-empty-state">
        <p style="color: var(--text-muted); margin-bottom:1rem;">Trường chưa có lớp học nào.</p>
        <a href="./class-edit.php" class="btn btn-primary">Tạo lớp đầu tiên</a>
    </div>
<?php endif; ?>
<!-- Modal Xác nhận Xóa Lớp (Custom Animated Modal) -->
<div id="delete-class-modal" class="school-modal-backdrop" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="delete-modal-title">
    <div class="school-modal-card" role="document">
        <div class="school-modal-header">
            <div class="school-modal-icon-circle">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#DC2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="3 6 5 6 21 6"></polyline>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    <line x1="10" y1="11" x2="10" y2="17"></line>
                    <line x1="14" y1="11" x2="14" y2="17"></line>
                </svg>
            </div>
            <div>
                <h3 id="delete-modal-title" class="school-modal-title">Xóa lớp học?</h3>
                <p class="school-modal-subtitle">Thao tác này sẽ xóa lớp vĩnh viễn khỏi danh sách đào tạo.</p>
            </div>
        </div>

        <div class="school-modal-body">
            <p class="school-modal-desc">
                Bạn có chắc chắn muốn xóa lớp <strong id="delete-modal-class-name" class="school-modal-target-name">—</strong>?
            </p>
            <div class="school-modal-note">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#B45309" stroke-width="2" aria-hidden="true">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <span>Chỉ có thể xóa khi lớp chưa có sinh viên và không có dữ liệu đánh giá liên quan.</span>
            </div>
        </div>

        <form method="post" action="./classes.php" id="delete-class-form" style="margin: 0;">
            <input type="hidden" name="csrfToken" value="<?= htmlspecialchars($session->csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="classId" id="delete-modal-class-id" value="">
            <div class="school-modal-actions">
                <button type="button" class="btn btn-outline js-close-delete-modal">Hủy</button>
                <button type="submit" class="btn btn-danger-modal">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    </svg>
                    Xóa lớp
                </button>
            </div>
        </form>
    </div>
</div>

<?php
$pageBody = ob_get_clean();

$extraStyles = <<<'CSS'
<style>
.school-modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.52);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1050;
    padding: 1rem;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.22s cubic-bezier(0.16, 1, 0.3, 1), visibility 0.22s;
}
.school-modal-backdrop.is-open {
    opacity: 1;
    visibility: visible;
}
.school-modal-card {
    background: var(--surface, #ffffff);
    color: var(--text-primary, #0F172A);
    border: 1px solid var(--border, #E2E8F0);
    border-radius: 16px;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.12), 0 8px 10px -6px rgba(0, 0, 0, 0.06);
    width: 100%;
    max-width: 440px;
    padding: 1.5rem 1.75rem;
    transform: scale(0.92) translateY(6px);
    opacity: 0;
    transition: transform 0.22s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.22s cubic-bezier(0.16, 1, 0.3, 1);
}
.school-modal-backdrop.is-open .school-modal-card {
    transform: scale(1) translateY(0);
    opacity: 1;
}
.school-modal-header {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    margin-bottom: 1rem;
}
.school-modal-icon-circle {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: #FEE2E2;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.school-modal-title {
    font-size: 1.1875rem;
    font-weight: 700;
    color: var(--text-primary, #0F172A);
    margin: 0 0 0.25rem 0;
    line-height: 1.3;
}
.school-modal-subtitle {
    font-size: 0.8125rem;
    color: var(--text-secondary, #64748B);
    margin: 0;
}
.school-modal-body {
    margin-bottom: 1.5rem;
}
.school-modal-desc {
    font-size: 0.9375rem;
    color: var(--text-primary, #1E293B);
    line-height: 1.5;
    margin: 0 0 0.875rem 0;
}
.school-modal-target-name {
    color: #DC2626;
    background: #FEF2F2;
    padding: 0.125rem 0.5rem;
    border-radius: 6px;
    font-weight: 700;
}
.school-modal-note {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    background: #FFFBEB;
    border: 1px solid #FDE68A;
    border-radius: 8px;
    padding: 0.625rem 0.75rem;
    font-size: 0.8125rem;
    color: #92400E;
    line-height: 1.4;
}
.school-modal-note svg {
    flex-shrink: 0;
    margin-top: 1px;
}
.school-modal-actions {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 0.75rem;
}
.btn-danger-modal {
    background: #DC2626;
    color: #ffffff;
    border: 1px solid #DC2626;
    border-radius: var(--radius-sm, 8px);
    padding: 0.5rem 1.125rem;
    font-size: 0.875rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    cursor: pointer;
    transition: background 0.15s ease, transform 0.1s ease;
}
.btn-danger-modal:hover {
    background: #B91C1C;
    border-color: #B91C1C;
}
.btn-danger-modal:active {
    transform: translateY(1px);
}
</style>
CSS;

$extraScripts = <<<'HTML'
<script>
document.addEventListener('DOMContentLoaded', function() {
    const params = new URLSearchParams(window.location.search);
    if (params.has('msg')) {
        const map = {
            created: 'Đã tạo lớp mới.',
            updated: 'Đã cập nhật lớp.',
            archived: 'Đã lưu trữ lớp.',
            deleted: 'Đã xóa lớp thành công.'
        };
        const key = params.get('msg');
        if (map[key]) showSchoolToast(map[key]);
    }

    const modal = document.getElementById('delete-class-modal');
    const modalClassName = document.getElementById('delete-modal-class-name');
    const modalClassId = document.getElementById('delete-modal-class-id');
    let triggerElement = null;

    function openDeleteModal(classId, className, triggerBtn) {
        if (!modal) return;
        triggerElement = triggerBtn;
        if (modalClassId) modalClassId.value = classId;
        if (modalClassName) modalClassName.textContent = className;
        modal.setAttribute('aria-hidden', 'false');
        modal.classList.add('is-open');
        document.body.style.overflow = 'hidden';
        const cancelBtn = modal.querySelector('.js-close-delete-modal');
        if (cancelBtn) cancelBtn.focus();
    }

    function closeDeleteModal() {
        if (!modal || !modal.classList.contains('is-open')) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        if (triggerElement) {
            triggerElement.focus();
            triggerElement = null;
        }
    }

    document.querySelectorAll('.js-delete-class-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const id = btn.getAttribute('data-class-id');
            const name = btn.getAttribute('data-class-name') || '';
            openDeleteModal(id, name, btn);
        });
    });

    document.querySelectorAll('.js-close-delete-modal').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            closeDeleteModal();
        });
    });

    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeDeleteModal();
            }
        });
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal && modal.classList.contains('is-open')) {
            closeDeleteModal();
        }
    });
});
</script>
HTML;

require __DIR__ . '/includes/layout.php';
