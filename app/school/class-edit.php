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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $session->assertCsrf(isset($_POST['csrfToken']) ? (string) $_POST['csrfToken'] : null);
}

$flash = null;
$error = null;
$row   = [
    'id'           => '',
    'name'         => '',
    'gradeLevel'   => '',
    'academicYear' => $context['school']['academicYear'] ?? '2025 - 2026',
    'status'       => 'active',
];

if ($action === 'archive' && $isEdit) {
    try {
        $service->archiveClass($userId, $classId);
        header('Location: ./classes.php?msg=archived');
        exit;
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }
}

if ($action === 'delete' && $isEdit) {
    try {
        $service->deleteClass($userId, $classId);
        header('Location: ./classes.php?msg=deleted');
        exit;
    } catch (\TalentHub\Http\ApiException $e) {
        $error = $e->getMessage();
    } catch (\Throwable $e) {
        $error = 'Đã xảy ra lỗi: ' . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['create', 'update'], true)) {
    try {
        if ($action === 'create') {
            $service->createClass($userId, [
                'name' => $_POST['name'] ?? '',
                'gradeLevel' => $_POST['gradeLevel'] ?? '',
                'academicYear' => $_POST['academicYear'] ?? '',
                'status' => $_POST['status'] ?? 'active',
            ]);
            header('Location: ./classes.php?msg=created');
            exit;
        }
        if ($action === 'update') {
            $service->updateClass($userId, $classId, [
                'name' => $_POST['name'] ?? '',
                'gradeLevel' => $_POST['gradeLevel'] ?? '',
                'academicYear' => $_POST['academicYear'] ?? '',
                'status' => $_POST['status'] ?? 'active',
            ]);
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
        $row = ['id' => $classId, 'name' => '', 'gradeLevel' => '', 'academicYear' => '', 'status' => 'active'];
    }
}

$schoolInfo = [
    'name'          => $context['school']['name'],
    'logo_initials' => mb_substr($context['school']['name'], 0, 2),
    'level'         => $context['school']['level'] ?? 'Đại học / Cao đẳng',
    'district'      => $context['school']['address'] ?? '',
    'academic_year' => $context['school']['academicYear'] ?? '',
];

$currentRoute = '/app/school/classes.php';
$pageTitle    = $isEdit ? 'Chỉnh sửa lớp ' . ($row['name'] ?? '') : 'Thêm lớp mới';

// Grade-level options based on school tier.
$schoolRow    = $context['school'];
$tier         = $service->detectSchoolTier($schoolRow);
$gradeOptions = $service->gradeOptionsForSchool($schoolRow);

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
            <div style="display: flex; gap: 0.5rem; align-items: center;">
                <form method="post" data-confirm="Lưu trữ lớp này? Học sinh vẫn giữ hồ sơ nhưng sẽ không hiển thị ở dashboard.">
                    <input type="hidden" name="csrfToken" value="<?= htmlspecialchars($session->csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="action" value="archive">
                    <button type="submit" class="btn btn-outline" style="border-color:#FCD34D;color:#D97706;">Lưu trữ lớp</button>
                </form>
                <form method="post" data-confirm="Bạn có chắc chắn muốn xóa lớp này? Chỉ có thể xóa khi lớp chưa có sinh viên hoặc dữ liệu liên quan.">
                    <input type="hidden" name="csrfToken" value="<?= htmlspecialchars($session->csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="action" value="delete">
                    <button type="submit" class="btn btn-outline" style="border-color:#FCA5A5;color:#B91C1C;">Xóa lớp</button>
                </form>
            </div>
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
                <input type="text" name="name" maxlength="100" required value="<?= htmlspecialchars((string) $row['name']); ?>" placeholder="<?= $tier === 'college' ? 'Vd: K1, K2-CNTT…' : '10A, 11B1...'; ?>">
            </label>
            <label class="school-form__field">
                <span><?= $tier === 'college' ? 'Khóa' : 'Khối'; ?> <em>*</em></span>
                <?php if ($tier === 'college'): ?>
                    <?php
                        $collegeValue = (string) $row['gradeLevel'];
                        if ($collegeValue !== '' && preg_match('/^\d+$/', $collegeValue)) {
                            $collegeValue = 'Năm ' . $collegeValue;
                        }
                    ?>
                    <input type="text" name="gradeLevel" maxlength="50" required
                           value="<?= htmlspecialchars($collegeValue); ?>"
                           placeholder="K1" autocomplete="off" />
                <?php else: ?>
                    <select name="gradeLevel" class="typeui-select" required>
                        <?php foreach ($gradeOptions as $g): ?>
                            <?php
                                $optVal   = (string) $g;
                                $optLabel = 'Khối ' . $optVal;
                                $isSelected = ((string) $row['gradeLevel'] === $optVal);
                            ?>
                            <option value="<?= htmlspecialchars($optVal); ?>" <?= $isSelected ? 'selected' : ''; ?>><?= htmlspecialchars($optLabel); ?></option>
                        <?php endforeach; ?>
                        <?php if ($row['gradeLevel'] !== '' && !in_array((string) $row['gradeLevel'], array_map('strval', $gradeOptions), true)): ?>
                            <option value="<?= htmlspecialchars((string) $row['gradeLevel']); ?>" selected><?= htmlspecialchars((string) $row['gradeLevel']); ?></option>
                        <?php endif; ?>
                    </select>
                <?php endif; ?>
            </label>
            <label class="school-form__field">
                <span>Niên khóa <em>*</em></span>
                <input type="text" name="academicYear" maxlength="20" required value="<?= htmlspecialchars((string) $row['academicYear']); ?>" placeholder="2025 - 2026" onfocus="this.select()">
            </label>
            <label class="school-form__field">
                <span>Trạng thái</span>
                <select name="status" class="typeui-select typeui-select--status">
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
