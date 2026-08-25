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
$session = $context['session'];

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
        $session->assertCsrf(isset($_POST['csrfToken']) ? (string) $_POST['csrfToken'] : null);
        if ($action === 'create') {
            $result = $service->createStudent($userId, $_POST);
            $message = ($result['deliveryStatus'] ?? 'failed') === 'sent' ? 'invited' : 'invite_delivery_failed';
            header('Location: ./students.php?msg=' . $message);
            exit;
        }
        if ($action === 'update' && $isEdit) {
            $service->updateStudent($userId, $studentId, $_POST);
            header('Location: ./students.php?msg=updated');
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
                <a href="./students.php">← Quay lại danh sách học sinh</a>
            </p>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="school-flash school-flash--error" role="alert"><?= htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="post" class="school-form" novalidate>
        <input type="hidden" name="csrfToken" value="<?= htmlspecialchars($session->csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="action" value="<?= $isEdit ? 'update' : 'create'; ?>">

        <div class="school-form__grid school-form__grid--2col">
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
            <a href="./students.php" class="btn btn-outline">Huỷ</a>
            <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Cập nhật' : 'Tạo học sinh'; ?></button>
        </div>
    </form>
</div>
<?php
$pageBody = ob_get_clean();

$extraStyles = '';

require __DIR__ . '/includes/layout.php';
