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
    $session->assertCsrf(isset($_POST['csrfToken']) ? (string) $_POST['csrfToken'] : null);
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'create') {
            $created = $service->createStudent($userId, [
                'fullName' => $_POST['fullName'] ?? '',
                'email' => $_POST['email'] ?? '',
                'classId' => $_POST['classId'] ?? '',
                'phone' => $_POST['phone'] ?? '',
                'dateOfBirth' => $_POST['dateOfBirth'] ?? '',
            ]);
            $invitationUrl = app_href((string) $created['invitationUrl']);
            $flash = 'Đã tạo sinh viên ở trạng thái chờ kích hoạt. Gửi liên kết dùng một lần trước '
                . htmlspecialchars((string) $created['expiresAt'], ENT_QUOTES, 'UTF-8') . ': '
                . '<a href="' . htmlspecialchars($invitationUrl, ENT_QUOTES, 'UTF-8') . '">Mở lời mời</a>';
        }
        if ($action === 'update' && $isEdit) {
            $service->updateStudent($userId, $studentId, [
                'classId' => $_POST['classId'] ?? '',
                'phone' => $_POST['phone'] ?? '',
                'dateOfBirth' => $_POST['dateOfBirth'] ?? '',
                'studyStatus' => $_POST['studyStatus'] ?? 'active',
            ]);
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
    'level'         => $context['school']['level'] ?? 'Đại học / Cao đẳng',
    'district'      => $context['school']['address'] ?? '',
    'academic_year' => $context['school']['academicYear'] ?? '',
];

$currentRoute = '/app/school/students.php';
$pageTitle    = $isEdit ? 'Chỉnh sửa sinh viên' : 'Thêm sinh viên';

ob_start();
?>
<div class="school-section-box" style="margin-bottom: 1.5rem;">
    <div style="display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h2 style="font-size: 1.25rem; font-weight: 700; margin: 0;">
                <?= htmlspecialchars($pageTitle); ?>
            </h2>
            <p style="font-size: 0.875rem; color: var(--text-secondary); margin: 0.25rem 0 0;">
                <a href="./students.php">← Quay lại danh sách sinh viên</a>
            </p>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="school-flash school-flash--error" role="alert"><?= htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($flash): ?>
        <div class="school-flash school-flash--success" role="status"><?= $flash; ?></div>
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
                <span>Lớp / Chuyên ngành <em>*</em></span>
                <select name="classId" class="typeui-select" required>
                    <option value="">-- Chọn lớp / chuyên ngành --</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?= htmlspecialchars($c['id']); ?>" <?= ($row['classId'] ?? '') === $c['id'] ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($c['name']); ?> (<?= htmlspecialchars($c['grade']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="school-form__field">
                <span>Số điện thoại</span>
                <input type="tel" name="phone" maxlength="30" value="<?= htmlspecialchars((string) $row['phone']); ?>">
            </label>
            <label class="school-form__field">
                <span>Ngày sinh</span>
                <input type="date" name="dateOfBirth" value="<?= htmlspecialchars((string) $row['dateOfBirth']); ?>">
            </label>
            <?php if ($isEdit): ?>
                <label class="school-form__field">
                    <span>Trạng thái học tập</span>
                    <select name="studyStatus" class="typeui-select typeui-select--status">
                        <option value="active" <?= ($row['studyStatus'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Đang theo học (active)</option>
                        <option value="suspended" <?= ($row['studyStatus'] ?? '') === 'suspended' ? 'selected' : ''; ?>>Tạm đình chỉ (suspended)</option>
                        <option value="graduated" <?= ($row['studyStatus'] ?? '') === 'graduated' ? 'selected' : ''; ?>>Đã tốt nghiệp (graduated)</option>
                        <option value="transferred" <?= ($row['studyStatus'] ?? '') === 'transferred' ? 'selected' : ''; ?>>Chuyển trường (transferred)</option>
                    </select>
                </label>
            <?php else: ?>
                <input type="hidden" name="studyStatus" value="active">
            <?php endif; ?>
        </div>

        <div class="school-form__actions">
            <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Cập nhật' : 'Tạo sinh viên'; ?></button>
            <a href="./students.php" class="btn btn-outline">Hủy</a>
        </div>
    </form>
</div>
<?php
$pageBody = ob_get_clean();

$extraStyles = '';

require __DIR__ . '/includes/layout.php';
