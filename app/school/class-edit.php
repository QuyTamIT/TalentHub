<?php
/**
 * TalentHub - School Dashboard Class Edit Page
 * Tạo / chỉnh sửa / lưu trữ một lớp học.
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

$classId = isset($_GET['id']) ? (string) $_GET['id'] : null;
$isEdit  = $classId !== null && Uuid::isValid($classId ?? '');

$action = $_POST['action'] ?? null;

$flash = null;
$error = null;
$row   = [
    'id'           => '',
    'name'         => '',
    'gradeLevel'   => 10,
    'academicYear' => $context['school']['academicYear'] ?? '2025 - 2026',
    'status'       => 'active',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'archive' && $isEdit) {
    try {
        $session->assertCsrf(isset($_POST['csrfToken']) ? (string) $_POST['csrfToken'] : null);
        $service->archiveClass($userId, $classId);
        header('Location: ./classes.php?msg=archived');
        exit;
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['create', 'update'], true)) {
    try {
        $session->assertCsrf(isset($_POST['csrfToken']) ? (string) $_POST['csrfToken'] : null);
        if ($action === 'create') {
            $newId = $service->createClass($userId, $_POST)['id'] ?? null;
            header('Location: ./classes.php?msg=created');
            exit;
        }
        if ($action === 'update') {
            $service->updateClass($userId, $classId, $_POST);
            header('Location: ./classes.php?msg=updated');
            exit;
        }
    } catch (ApiException $e) {
        $error = $e->getMessage();
    } catch (\Throwable $e) {
        $error = 'Đã xảy ra lỗi: ' . $e->getMessage();
    }
}

if ($isEdit) {
    try {
        $row = $service->getClass($userId, $classId);
    } catch (ApiException $e) {
        $error = $e->getMessage();
        $row = ['id' => $classId, 'name' => '', 'gradeLevel' => 10, 'academicYear' => '', 'status' => 'active'];
    }
}

$schoolInfo = [
    'name'          => $context['school']['name'],
    'logo_initials' => mb_substr($context['school']['name'], 0, 2),
    'level'         => $context['school']['level'] ?? 'Trung học',
    'district'      => $context['school']['address'] ?? '',
    'academic_year' => $context['school']['academicYear'] ?? '',
];

$currentRoute = '/app/school/classes.php';
$pageTitle    = $isEdit ? 'Chỉnh sửa lớp ' . ($row['name'] ?? '') : 'Thêm lớp mới';

ob_start();
?>
<div class="school-section-box" style="margin-bottom: 1.5rem;">
    <div style="display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h2 style="font-size: 1.25rem; font-weight: 700; margin: 0;">
                <?= htmlspecialchars($pageTitle); ?>
            </h2>
            <p style="font-size: 0.875rem; color: var(--text-secondary); margin: 0.25rem 0 0;">
                <a href="./classes.php">← Quay lại danh sách lớp</a>
            </p>
        </div>
        <?php if ($isEdit): ?>
            <form method="post" data-confirm="Lưu trữ lớp này? Học sinh vẫn giữ hồ sơ nhưng sẽ không hiển thị ở dashboard.">
                <input type="hidden" name="csrfToken" value="<?= htmlspecialchars($session->csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="archive">
                <button type="submit" class="btn btn-outline" style="border-color:#FCA5A5;color:#B91C1C;">Lưu trữ lớp</button>
            </form>
        <?php endif; ?>
    </div>

    <?php if ($error): ?>
        <div class="school-flash school-flash--error" role="alert"><?= htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="post" class="school-form" novalidate>
        <input type="hidden" name="csrfToken" value="<?= htmlspecialchars($session->csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="action" value="<?= $isEdit ? 'update' : 'create'; ?>">

        <div class="school-form__grid school-form__grid--2col">
            <label class="school-form__field">
                <span>Tên lớp <em>*</em></span>
                <input type="text" name="name" maxlength="100" required value="<?= htmlspecialchars((string) $row['name']); ?>" placeholder="10A, 11B1...">
            </label>
            <label class="school-form__field">
                <span>Khối <em>*</em></span>
                <select name="gradeLevel" required>
                    <?php for ($g = 1; $g <= 12; $g++): ?>
                        <option value="<?= $g; ?>" <?= ((int) $row['gradeLevel'] === $g) ? 'selected' : ''; ?>>Khối <?= $g; ?></option>
                    <?php endfor; ?>
                </select>
            </label>
            <label class="school-form__field">
                <span>Niên khóa <em>*</em></span>
                <input type="text" name="academicYear" maxlength="20" required value="<?= htmlspecialchars((string) $row['academicYear']); ?>" placeholder="2025 - 2026">
            </label>
            <label class="school-form__field">
                <span>Trạng thái</span>
                <select name="status">
                    <option value="active" <?= ($row['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Đang hoạt động</option>
                    <option value="archived" <?= ($row['status'] ?? '') === 'archived' ? 'selected' : ''; ?>>Đã lưu trữ</option>
                </select>
            </label>
        </div>

        <div class="school-form__actions">
            <a href="./classes.php" class="btn btn-outline">Huỷ</a>
            <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Cập nhật lớp' : 'Tạo lớp'; ?></button>
        </div>
    </form>
</div>
<?php
$pageBody = ob_get_clean();

$extraStyles = '';

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
