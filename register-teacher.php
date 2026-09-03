<?php
declare(strict_types=1);

require __DIR__ . '/bin/bootstrap.php';

use TalentHub\Auth\Service\TeacherRegistrationService;
use TalentHub\Auth\Session\SessionManager;
use TalentHub\Database\Connection;

$values = [
    'fullName' => '',
    'email' => '',
    'phone' => '',
    'specialization' => '',
    'schoolId' => '',
];
$error = null;
$schools = [];

$session = new SessionManager(require __DIR__ . '/config/session.php');
$session->start();

try {
    $pdo = (new Connection(require __DIR__ . '/config/database.php'))->connect();
    $schools = $pdo
        ->query("SELECT id, name FROM schools WHERE status = 'active' ORDER BY name")
        ->fetchAll();
} catch (Throwable) {
    $error = 'Chưa thể tải danh sách nhà trường. Vui lòng thử lại sau.';
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    foreach (array_keys($values) as $field) {
        $values[$field] = trim((string) ($_POST[$field] ?? ''));
    }

    $password = (string) ($_POST['password'] ?? '');
    $confirmation = (string) ($_POST['passwordConfirmation'] ?? '');

    if (!hash_equals($password, $confirmation)) {
        $error = 'Mật khẩu nhập lại chưa khớp.';
    } elseif (isset($pdo)) {
        try {
            $result = (new TeacherRegistrationService($pdo))->register([
                ...$values,
                'password' => $password,
            ]);
            $_SESSION['authFlash']=['type'=>'registered-pending','email'=>$result['email']];
            header('Location: ./login.php');
            exit;
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }
    }
}

function teacherEscape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Đăng ký hồ sơ giáo viên trên TalentHub.">
    <link rel="icon" href="./assets/images/logo.svg" type="image/svg+xml">
    <title>Đăng ký giáo viên | TalentHub</title>
    <link rel="stylesheet" href="assets/css/home.css">
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="assets/css/auth.css">
    <link rel="stylesheet" href="assets/css/typeui-selects.css">
</head>
<body class="auth-page auth-page--register" data-submit-label="Gửi hồ sơ giáo viên">
<a class="skip-link" href="#main-content">Bỏ qua đến nội dung chính</a>
<main class="auth-layout" id="main-content">
    <section class="auth-brand" aria-labelledby="teacher-brand-title">
        <a class="auth-brand__logo" href="./index.php" aria-label="TalentHub - Về trang chủ">
            <img src="./assets/images/logo.svg" alt="TalentHub" width="200" height="40">
        </a>
        <div class="auth-brand__content">
            <p class="auth-eyebrow">Đồng hành cùng người học</p>
            <h1 id="teacher-brand-title">Tham gia TalentHub với vai trò giáo viên</h1>
            <p>Tạo hồ sơ chuyên môn, kết nối với nhà trường và quản lý hành trình phát triển năng lực của học viên.</p>
            <div class="auth-data-summary" aria-label="Các bước kích hoạt tài khoản">
                <p><strong>Hồ sơ chuyên môn</strong><span>Cung cấp thông tin giảng dạy và lĩnh vực phụ trách.</span></p>
                <p><strong>Liên kết nhà trường</strong><span>Chọn đơn vị đang công tác để gửi yêu cầu xác minh.</span></p>
                <p><strong>Kích hoạt an toàn</strong><span>Tài khoản được mở sau khi hồ sơ được duyệt.</span></p>
            </div>
        </div>
        <p class="auth-brand__footer">TalentHub · Không gian dành cho nhà giáo</p>
    </section>

    <section class="auth-panel" aria-labelledby="register-title">
        <div class="auth-panel__inner auth-panel__inner--wide">
            <a class="auth-mobile-logo" href="./index.php" aria-label="TalentHub - Về trang chủ">
                <img src="./assets/images/logo.svg" alt="TalentHub" width="180" height="36">
            </a>

            <div class="auth-heading">
                <div class="auth-heading__row">
                    <div>
                        <p class="auth-kicker">Hồ sơ mới</p>
                        <h2 id="register-title">Đăng ký giáo viên</h2>
                    </div>
                    <span class="auth-role-badge">Giáo viên</span>
                </div>
                <p>Điền thông tin đang sử dụng tại đơn vị công tác của bạn.</p>
            </div>

            <?php if ($error): ?>
                <div class="auth-alert auth-alert--error" role="alert" tabindex="-1" data-error-summary><?= teacherEscape($error) ?></div>
            <?php endif; ?>

            <form class="auth-form" method="post" data-auth-form>
                <div class="auth-form-grid">
                    <div class="auth-field auth-field--full">
                        <label for="fullName">Họ và tên</label>
                        <input id="fullName" name="fullName" value="<?= teacherEscape($values['fullName']) ?>" autocomplete="name" minlength="2" maxlength="150" required>
                    </div>
                    <div class="auth-field">
                        <label for="email">Email công việc</label>
                        <input id="email" name="email" type="email" value="<?= teacherEscape($values['email']) ?>" autocomplete="email" maxlength="255" required>
                    </div>
                    <div class="auth-field">
                        <label for="phone">Số điện thoại</label>
                        <input id="phone" name="phone" type="tel" value="<?= teacherEscape($values['phone']) ?>" autocomplete="tel" minlength="6" maxlength="30" required>
                    </div>
                    <div class="auth-field">
                        <label for="schoolId">Nhà trường đang công tác</label>
                        <select id="schoolId" name="schoolId" class="typeui-select typeui-select--large" required <?= $schools === [] ? 'disabled' : '' ?>>
                            <option value="">Chọn nhà trường</option>
                            <?php foreach ($schools as $school): ?>
                                <option value="<?= teacherEscape($school['id']) ?>" <?= hash_equals($values['schoolId'], (string) $school['id']) ? 'selected' : '' ?>><?= teacherEscape($school['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="auth-field">
                        <label for="specialization">Chuyên môn giảng dạy</label>
                        <input id="specialization" name="specialization" value="<?= teacherEscape($values['specialization']) ?>" autocomplete="organization-title" minlength="2" maxlength="150" placeholder="Ví dụ: Toán học, Robotics" required>
                    </div>
                    <div class="auth-field">
                        <label for="password">Mật khẩu</label>
                        <div class="auth-password">
                            <input id="password" name="password" type="password" autocomplete="new-password" minlength="12" maxlength="255" required>
                            <button type="button" class="auth-password__toggle" data-password-toggle aria-controls="password" aria-pressed="false">Hiện</button>
                        </div>
                        <span class="auth-field__hint">Tối thiểu 12 ký tự.</span>
                    </div>
                    <div class="auth-field">
                        <label for="passwordConfirmation">Nhập lại mật khẩu</label>
                        <div class="auth-password">
                            <input id="passwordConfirmation" name="passwordConfirmation" type="password" autocomplete="new-password" minlength="12" maxlength="255" required data-password-confirm>
                            <button type="button" class="auth-password__toggle" data-password-toggle aria-controls="passwordConfirmation" aria-pressed="false">Hiện</button>
                        </div>
                        <span class="auth-field__error" data-password-match></span>
                    </div>
                </div>

                <button class="auth-submit" type="submit" data-submit <?= $schools === [] ? 'disabled' : '' ?>>
                    <span>Gửi hồ sơ giáo viên</span>
                    <span aria-hidden="true">→</span>
                </button>
            </form>

            <p class="auth-switch">Muốn chọn vai trò khác? <a href="./role-selection.php">Quay lại chọn vai trò</a></p>
            <p class="auth-switch">Đã có tài khoản? <a href="./login.php">Đăng nhập</a></p>
        </div>
    </section>
</main>
<script src="assets/js/auth.js" defer></script>
</body>
</html>
