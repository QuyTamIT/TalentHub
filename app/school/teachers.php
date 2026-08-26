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
$session = $context['session'];

$flash = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $session->assertCsrf(isset($_POST['csrfToken']) ? (string) $_POST['csrfToken'] : null);
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'invite') {
            $result = $service->inviteTeacher($userId, [
                'fullName' => $_POST['fullName'] ?? '',
                'email' => $_POST['email'] ?? '',
                'isSchoolAdmin' => $_POST['isSchoolAdmin'] ?? null,
            ]);
            $invitationUrl = app_href((string) $result['invitationUrl']);
            $flash = 'Đã tạo tài khoản chờ kích hoạt. Gửi liên kết dùng một lần này cho giáo viên trước '
                . htmlspecialchars((string) $result['expiresAt'], ENT_QUOTES, 'UTF-8') . ': '
                . '<a href="' . htmlspecialchars($invitationUrl, ENT_QUOTES, 'UTF-8') . '">Mở lời mời</a>';
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
<?php
$pageDescription = 'Mời giáo viên mới và quản lý hồ sơ giáo viên trong trường.';
include __DIR__ . '/includes/page-banner.php';
?>

<?php if ($flash): ?>
    <div class="school-flash school-flash--success"><?= $flash; ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="school-flash school-flash--error"><?= htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="school-grid-2col school-grid-2col--teachers">
    <div class="school-section-box">
        <div class="school-section-box__header">
            <h3 class="school-section-box__title">Mời giáo viên mới</h3>
        </div>
        <form method="post" class="school-form" novalidate>
            <input type="hidden" name="csrfToken" value="<?= htmlspecialchars($session->csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
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
                <label class="school-form__field school-form__field--checkbox">
                    <input type="checkbox" name="isSchoolAdmin" value="1">
                    <span>Cấp quyền quản trị trường</span>
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
                                        <input type="hidden" name="csrfToken" value="<?= htmlspecialchars($session->csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="action" value="toggle_admin">
                                        <input type="hidden" name="profileId" value="<?= htmlspecialchars($t['id']); ?>">
                                        <input type="hidden" name="isAdmin" value="<?= $t['isSchoolAdmin'] ? '0' : '1'; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline">
                                            <?= $t['isSchoolAdmin'] ? 'Bỏ quản trị' : 'Cấp quản trị'; ?>
                                        </button>
                                    </form>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="csrfToken" value="<?= htmlspecialchars($session->csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
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
.school-grid-2col.school-grid-2col--teachers { grid-template-columns: minmax(0, 320px) minmax(0, 1fr); }
@media (max-width: 900px) { .school-grid-2col--teachers { grid-template-columns: 1fr; } }
</style>
HTML;

require __DIR__ . '/includes/layout.php';
