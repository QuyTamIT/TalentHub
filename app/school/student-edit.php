<?php
/**
 * TalentHub - School Dashboard Student Edit Page
 * Tạo / chỉnh sửa / chuyển lớp một học sinh.
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

$studentId = isset($_GET['id']) ? (string) $_GET['id'] : null;
$isEdit    = $studentId !== null && Uuid::isValid($studentId);

$flash = null;
$error = null;

$row = [
    'id'          => '',
    'userId'      => '',
    'email'       => '',
    'fullName'    => '',
    'classId'     => '',
    'phone'       => '',
    'dateOfBirth' => '',
    'studyStatus' => 'active',
];

if ($isEdit) {
    try {
        $row = array_merge($row, $service->getStudent($userId, $studentId));
    } catch (ApiException $e) {
        $error = $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'create') {
            $service->createStudent($userId, $_POST);
            header('Location: /app/school/students.php?msg=created');
            exit;
        }
        if ($action === 'update' && $isEdit) {
            $service->updateStudent($userId, $studentId, $_POST);
            header('Location: /app/school/students.php?msg=updated');
            exit;
        }
    } catch (ApiException $e) {
        $error = $e->getMessage();
    } catch (\Throwable $e) {
        $error = 'Đã xảy ra lỗi: ' . $e->getMessage();
    }
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
$pageTitle    = $isEdit ? 'Chỉnh sửa học sinh' : 'Thêm học sinh';

ob_start();
?>
<div class="school-section-box" style="margin-bottom: 1.5rem;">
    <div style="display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h2 style="font-size: 1.25rem; font-weight: 700; margin: 0;">
                <?= htmlspecialchars($pageTitle); ?>
            </h2>
            <p style="font-size: 0.875rem; color: var(--text-secondary); margin: 0.25rem 0 0;">
                <a href="/app/school/students.php">← Quay lại danh sách học sinh</a>
            </p>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="school-flash school-flash--error" role="alert"><?= htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="post" class="school-form" novalidate>
        <input type="hidden" name="action" value="<?= $isEdit ? 'update' : 'create'; ?>">

        <div class="school-form__grid">
            <label class="school-form__field">
                <span>Họ và tên <em>*</em></span>
                <input type="text" name="fullName" maxlength="150" required value="<?= htmlspecialchars((string) $row['fullName']); ?>">
            </label>
            <label class="school-form__field">
                <span>Email <em>*</em></span>
                <input type="email" name="email" maxlength="255" required value="<?= htmlspecialchars((string) $row['email']); ?>" <?= $isEdit ? 'readonly' : ''; ?>>
            </label>
            <label class="school-form__field">
                <span>Lớp <em>*</em></span>
                <select name="classId" required>
                    <option value="">-- Chọn lớp --</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?= htmlspecialchars($c['id']); ?>" <?= ($row['classId'] ?? '') === $c['id'] ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($c['name']); ?> - <?= htmlspecialchars($c['grade']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="school-form__field">
                <span>Số điện thoại <em>*</em></span>
                <input type="text" name="phone" maxlength="30" required value="<?= htmlspecialchars((string) $row['phone']); ?>">
            </label>
            <label class="school-form__field">
                <span>Ngày sinh</span>
                <input type="date" name="dateOfBirth" value="<?= htmlspecialchars((string) $row['dateOfBirth']); ?>">
            </label>
            <?php if ($isEdit): ?>
            <label class="school-form__field">
                <span>Trạng thái học tập</span>
                <select name="studyStatus">
                    <option value="active" <?= ($row['studyStatus'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Đang học</option>
                    <option value="inactive" <?= ($row['studyStatus'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Tạm nghỉ</option>
                    <option value="graduated" <?= ($row['studyStatus'] ?? '') === 'graduated' ? 'selected' : ''; ?>>Đã tốt nghiệp</option>
                    <option value="transferred" <?= ($row['studyStatus'] ?? '') === 'transferred' ? 'selected' : ''; ?>>Chuyển trường</option>
                </select>
            </label>
            <?php else: ?>
                <input type="hidden" name="studyStatus" value="active">
            <?php endif; ?>
        </div>

        <div class="school-form__actions">
            <a href="/app/school/students.php" class="btn btn-outline">Huỷ</a>
            <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Cập nhật' : 'Tạo học sinh'; ?></button>
        </div>
    </form>
</div>
<?php
$pageBody = ob_get_clean();

$extraStyles = <<<'HTML'
<style>
.school-form__grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; margin-top: 1rem; }
.school-form__field { display: flex; flex-direction: column; gap: 0.375rem; font-size: 0.875rem; color: var(--text-secondary); }
.school-form__field input,
.school-form__field select { width: 100%; padding: 0.625rem 0.75rem; border-radius: var(--radius-sm); border: 1px solid var(--border); background: var(--surface); color: var(--text-primary); font-size: 0.9375rem; }
.school-form__field input:focus,
.school-form__field select:focus { outline: 2px solid #2563EB; outline-offset: 1px; }
.school-form__field em { color: #DC2626; font-style: normal; margin-left: 2px; }
.school-form__actions { display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem; }
.school-flash { padding: 0.75rem 1rem; border-radius: var(--radius-sm); margin-top: 1rem; font-size: 0.875rem; }
.school-flash--error { background: #FEF2F2; color: #B91C1C; border: 1px solid #FCA5A5; }
@media (max-width: 720px) { .school-form__grid { grid-template-columns: 1fr; } }
</style>
HTML;

require __DIR__ . '/includes/layout.php';