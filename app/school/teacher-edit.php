<?php
/**
 * TalentHub - School Dashboard Teacher Edit Page
 * Xem hồ sơ + cập nhật chuyên môn / số điện thoại của giáo viên.
 *
 * Lưu ý: tạo / mời giáo viên và toggle vai trò được xử lý tại teachers.php.
 * Trang này chỉ dùng để xem và cập nhật các trường profile.
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

$profileId = isset($_GET['id']) ? (string) $_GET['id'] : null;
if ($profileId === null || !Uuid::isValid($profileId)) {
    header('Location: ./teachers.php');
    exit;
}

$error = null;
$flash = null;
$row   = null;

try {
    $row = $service->getTeacher($userId, $profileId);
} catch (ApiException $e) {
    $error = $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $session->assertCsrf(isset($_POST['csrfToken']) ? (string) $_POST['csrfToken'] : null);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $row) {
    try {
        $row = $service->updateTeacherProfile($userId, $profileId, [
            'specialization' => $_POST['specialization'] ?? '',
            'phone' => $_POST['phone'] ?? '',
            'bio' => $_POST['bio'] ?? '',
        ]);
        $flash = 'Đã cập nhật hồ sơ giáo viên.';
    } catch (\Throwable $e) {
        $error = 'Đã xảy ra lỗi: ' . $e->getMessage();
    }
}

$schoolInfo = [
    'name'          => $context['school']['name'],
    'logo_initials' => mb_substr($context['school']['name'], 0, 2),
    'level'         => $context['school']['level'] ?? 'Trung học',
    'district'      => $context['school']['address'] ?? '',
    'academic_year' => $context['school']['academicYear'] ?? '',
];

$currentRoute = '/app/school/teachers.php';
$pageTitle    = 'Hồ sơ giáo viên';

ob_start();
?>
<div class="school-section-box" style="margin-bottom: 1.5rem;">
    <p style="font-size: 0.875rem; color: var(--text-secondary); margin: 0 0 1rem;">
        <a href="./teachers.php">← Quay lại danh sách giáo viên</a>
    </p>

    <?php if ($error): ?>
        <div class="school-flash school-flash--error"><?= htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($flash): ?>
        <div class="school-flash school-flash--success"><?= htmlspecialchars($flash); ?></div>
    <?php endif; ?>

    <?php if ($row): ?>
        <h2 style="font-size: 1.25rem; font-weight: 700; margin: 0 0 0.25rem;">
            <?= htmlspecialchars($row['fullName']); ?>
        </h2>
        <p style="font-size: 0.875rem; color: var(--text-secondary); margin: 0 0 1.25rem;">
            <?= htmlspecialchars($row['email']); ?>
            <?php if ($row['isSchoolAdmin']): ?>
                · <span style="color:#047857;">Quản trị viên trường</span>
            <?php endif; ?>
        </p>

        <form method="post" class="school-form" novalidate>
            <input type="hidden" name="csrfToken" value="<?= htmlspecialchars($session->csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
            <div class="school-form__grid school-form__grid--2col">
                <label class="school-form__field">
                    <span>Số điện thoại</span>
                    <input type="text" name="phone" maxlength="30" value="<?= htmlspecialchars((string) ($row['phone'] ?? '')); ?>">
                </label>
                <label class="school-form__field">
                    <span>Chuyên môn</span>
                    <input type="text" name="specialization" maxlength="150" value="<?= htmlspecialchars((string) ($row['specialization'] ?? '')); ?>">
                </label>
                <label class="school-form__field school-form__field--full">
                    <span>Tiểu sử</span>
                    <textarea name="bio" rows="4" maxlength="1000"><?= htmlspecialchars((string) ($row['bio'] ?? '')); ?></textarea>
                </label>
            </div>

            <div class="school-form__actions">
                <a href="./teachers.php" class="btn btn-outline">Huỷ</a>
                <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
            </div>
        </form>
    <?php endif; ?>
</div>
<?php
$pageBody = ob_get_clean();

$extraStyles = '';

require __DIR__ . '/includes/layout.php';
