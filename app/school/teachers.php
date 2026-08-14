<?php
/**
 * TalentHub - School Dashboard Teachers Page
 * Quản lý giáo viên của nhà trường + mời giáo viên mới.
 */
declare(strict_types=1);

require dirname(__DIR__, 2) . '/bin/bootstrap.php';
require dirname(__DIR__, 2) . '/src/Bootstrap/SchoolAppContext.php';

use TalentHub\Bootstrap\SchoolAppContext;
use TalentHub\Http\ApiException;
use TalentHub\Support\Uuid;

$context = (new SchoolAppContext())->boot();
$service = $context['service'];
$userId  = $context['user']['id'];

$flash = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'invite') {
            $result = $service->inviteTeacher($userId, $_POST);
            $flash = sprintf(
                'Đã mời giáo viên. Mật khẩu tạm: %s (hãy gửi cho giáo viên để họ đăng nhập lần đầu).',
                $result['generatedPassword'] ?? '—'
            );
        } elseif ($action === 'toggle_admin' && !empty($_POST['profileId']) && Uuid::isValid($_POST['profileId'])) {
            $service->setTeacherAdmin($userId, $_POST['profileId'], !empty($_POST['isAdmin']));
            $flash = 'Đã cập nhật vai trò giáo viên.';
        } elseif ($action === 'toggle_active' && !empty($_POST['profileId']) && Uuid::isValid($_POST['profileId'])) {
            $service->setTeacherActive($userId, $_POST['profileId'], !empty($_POST['isActive']));
            $flash = 'Đã cập nhật trạng thái giáo viên.';
        }
    } catch (ApiException $e) {
        $error = $e->getMessage();
    } catch (\Throwable $e) {
        $error = 'Đã xảy ra lỗi: ' . $e->getMessage();
    }
}

$perPage = max(10, min(100, (int) ($_GET['perPage'] ?? 25)));
$page    = max(1, (int) ($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;
$teachers = $service->teachers($userId, $perPage, $offset);

$schoolInfo = [
    'name'          => $context['school']['name'],
    'logo_initials' => mb_substr($context['school']['name'], 0, 2),
    'level'         => $context['school']['level'] ?? 'Trung học',
    'district'      => $context['school']['address'] ?? '',
    'academic_year' => $context['school']['academicYear'] ?? '',
];

$currentRoute = '/app/school/teachers.php';
$pageTitle    = 'Giáo viên';

ob_start();
?>
<div class="school-section-box" style="margin-bottom: 1.5rem;">
    <div style="display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.25rem;">
                Giáo viên của trường
            </h2>
            <p style="font-size: 0.875rem; color: var(--text-secondary); margin: 0;">
                <?= count($teachers); ?> giáo viên đang thuộc trường.
            </p>
        </div>
    </div>
</div>

<?php if ($flash): ?>
    <div class="school-flash school-flash--success"><?= htmlspecialchars($flash); ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="school-flash school-flash--error"><?= htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="school-grid-2col">
    <div class="school-section-box">
        <div class="school-section-box__header">
            <h3 class="school-section-box__title">Mời giáo viên mới</h3>
        </div>
        <form method="post" class="school-form" novalidate>
            <input type="hidden" name="action" value="invite">
            <div class="school-form__grid" style="grid-template-columns: 1fr;">
                <label class="school-form__field">
                    <span>Họ và tên <em>*</em></span>
                    <input type="text" name="fullName" maxlength="150" required placeholder="Nguyễn Văn A">
                </label>
                <label class="school-form__field">
                    <span>Email <em>*</em></span>
                    <input type="email" name="email" maxlength="255" required placeholder="gv.a@talenthub.vn">
                </label>
                <label class="school-form__field">
                    <span style="display:flex; align-items:center; gap: 0.5rem;">
                        <input type="checkbox" name="isSchoolAdmin" value="1">
                        Cấp quyền quản trị trường
                    </span>
                </label>
            </div>
            <div class="school-form__actions">
                <button type="submit" class="btn btn-primary">Mời giáo viên</button>
            </div>
        </form>
    </div>

    <div class="school-section-box">
        <div class="school-section-box__header">
            <h3 class="school-section-box__title">Danh sách giáo viên</h3>
        </div>
        <?php if ($teachers === []): ?>
            <p style="color: var(--text-muted);">Trường chưa có giáo viên nào.</p>
        <?php else: ?>
            <table class="school-class-table">
                <thead>
                    <tr>
                        <th>Giáo viên</th>
                        <th>Email</th>
                        <th>Chuyên môn</th>
                        <th>Vai trò</th>
                        <th style="text-align: right;">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($teachers as $t): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($t['fullName']); ?></strong>
                                <div style="font-size: 0.75rem; color: var(--text-muted);">
                                    <?= htmlspecialchars($t['userStatus']); ?>
                                </div>
                            </td>
                            <td><span style="font-size: 0.875rem; color: var(--text-secondary);"><?= htmlspecialchars($t['email']); ?></span></td>
                            <td><?= htmlspecialchars((string) ($t['specialization'] ?? '—')); ?></td>
                            <td>
                                <?php if ($t['isSchoolAdmin']): ?>
                                    <span class="school-class-badge school-class-badge--success">Quản trị</span>
                                <?php else: ?>
                                    <span class="school-class-badge school-class-badge--neutral">Giáo viên</span>
                                <?php endif; ?>
                                <?php if ($t['userStatus'] !== 'active'): ?>
                                    <span class="school-class-badge school-class-badge--warning">Vô hiệu</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <div style="display: flex; gap: 0.375rem; justify-content: flex-end;">
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="action" value="toggle_admin">
                                        <input type="hidden" name="profileId" value="<?= htmlspecialchars($t['id']); ?>">
                                        <input type="hidden" name="isAdmin" value="<?= $t['isSchoolAdmin'] ? '0' : '1'; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline">
                                            <?= $t['isSchoolAdmin'] ? 'Bỏ quản trị' : 'Cấp quản trị'; ?>
                                        </button>
                                    </form>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="action" value="toggle_active">
                                        <input type="hidden" name="profileId" value="<?= htmlspecialchars($t['id']); ?>">
                                        <input type="hidden" name="isActive" value="<?= $t['userStatus'] === 'active' ? '0' : '1'; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline" data-confirm="Đổi trạng thái giáo viên này?">
                                            <?= $t['userStatus'] === 'active' ? 'Vô hiệu hoá' : 'Kích hoạt'; ?>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <nav class="school-pagination" aria-label="Phân trang">
                <?php if ($page > 1): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="btn btn-sm btn-outline">‹ Trước</a>
                <?php endif; ?>
                <span class="school-pagination__info">Trang <?= $page; ?> · <?= $perPage; ?> / trang</span>
                <?php if (count($teachers) === $perPage): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="btn btn-sm btn-outline">Sau ›</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    </div>
</div>
<?php
$pageBody = ob_get_clean();

$extraStyles = <<<'HTML'
<style>
.school-grid-2col { display: grid; grid-template-columns: minmax(0, 320px) minmax(0, 1fr); gap: 1.5rem; }
.school-form__grid { display: grid; gap: 1rem; margin-top: 1rem; }
.school-form__field { display: flex; flex-direction: column; gap: 0.375rem; font-size: 0.875rem; color: var(--text-secondary); }
.school-form__field input,
.school-form__field select { width: 100%; padding: 0.625rem 0.75rem; border-radius: var(--radius-sm); border: 1px solid var(--border); background: var(--surface); color: var(--text-primary); font-size: 0.9375rem; }
.school-form__field input:focus { outline: 2px solid #2563EB; outline-offset: 1px; }
.school-form__field em { color: #DC2626; font-style: normal; margin-left: 2px; }
.school-form__actions { display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem; }
.school-flash { padding: 0.75rem 1rem; border-radius: var(--radius-sm); margin-bottom: 1rem; font-size: 0.875rem; }
.school-flash--success { background: #ECFDF5; color: #047857; border: 1px solid #6EE7B7; }
.school-flash--error { background: #FEF2F2; color: #B91C1C; border: 1px solid #FCA5A5; }
.school-pagination { display: flex; align-items: center; gap: 0.75rem; margin-top: 1rem; justify-content: flex-end; }
.school-pagination__info { font-size: 0.8125rem; color: var(--text-muted); }
@media (max-width: 900px) { .school-grid-2col { grid-template-columns: 1fr; } }
</style>
HTML;

require __DIR__ . '/includes/layout.php';