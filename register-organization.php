<?php
declare(strict_types=1);

require __DIR__ . '/bin/bootstrap.php';

use TalentHub\Auth\Service\OrganizationRegistrationService;
use TalentHub\Auth\Session\SessionManager;
use TalentHub\Database\Connection;

$requestedType = $forcedOrganizationType ?? ($_GET['type'] ?? $_POST['type'] ?? '');
$type = in_array($requestedType, ['school', 'enterprise'], true)
    ? (string) $requestedType
    : 'enterprise';
$values = [
    'organizationName' => '',
    'fullName' => '',
    'email' => '',
    'phone' => '',
    'address' => '',
];
$error = null;

$session = new SessionManager(require __DIR__ . '/config/session.php');
$session->start();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    foreach (array_keys($values) as $field) {
        $values[$field] = trim((string) ($_POST[$field] ?? ''));
    }

    $password = (string) ($_POST['password'] ?? '');

    if (!hash_equals($password, (string) ($_POST['passwordConfirmation'] ?? ''))) {
        $error = 'Mật khẩu nhập lại chưa khớp.';
    } else {
        try {
            $result = (new OrganizationRegistrationService(
                (new Connection(require __DIR__ . '/config/database.php'))->connect()
            ))->register([
                ...$values,
                'type' => $type,
                'password' => $password,
            ]);
            $_SESSION['authFlash'] = [
                'type' => 'registered-pending',
                'email' => $result['email'],
            ];
            header('Location: ./login.php');
            exit;
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }
    }
}

function oe(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$label = $type === 'school' ? 'Nhà trường' : 'Doanh nghiệp';
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Gửi yêu cầu đăng ký tổ chức trên TalentHub.">
    <link rel="icon" href="./assets/images/logo.svg" type="image/svg+xml">
    <title>Đăng ký <?= oe($label) ?> | TalentHub</title>
    <link rel="stylesheet" href="assets/css/home.css">
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body class="auth-page auth-page--register" data-submit-label="Gửi yêu cầu đăng ký">
<a class="skip-link" href="#main-content">Bỏ qua đến nội dung chính</a>
<main class="auth-layout" id="main-content">
    <section class="auth-brand" aria-labelledby="organization-brand-title">
        <a class="auth-brand__logo" href="./index.php" aria-label="TalentHub - Về trang chủ">
            <img src="./assets/images/logo.svg" alt="TalentHub" width="200" height="40">
        </a>
        <div class="auth-brand__content">
            <p class="auth-eyebrow">Đăng ký có xác minh</p>
            <h1 id="organization-brand-title">Đăng ký <?= oe($label) ?> trên TalentHub</h1>
            <p>Bước này chỉ gửi yêu cầu đến quản trị viên; tài khoản sẽ được tạo sau khi hồ sơ được duyệt.</p>
            <div class="auth-data-summary" aria-label="Quy trình duyệt hồ sơ">
                <p><strong>1. Gửi yêu cầu</strong><span>Thông tin tổ chức và người đại diện được lưu an toàn.</span></p>
                <p><strong>2. Xác minh hồ sơ</strong><span>Quản trị viên sẽ xử lý yêu cầu trong tối đa 3 ngày.</span></p>
                <p><strong>3. Tạo tài khoản</strong><span>Tài khoản và tổ chức chỉ được tạo sau khi duyệt.</span></p>
            </div>
        </div>
        <p class="auth-brand__footer">Yêu cầu chưa xử lý sẽ tự động bị xóa sau 3 ngày.</p>
    </section>

    <section class="auth-panel" aria-labelledby="register-title">
        <div class="auth-panel__inner auth-panel__inner--wide">
            <a class="auth-mobile-logo" href="./index.php" aria-label="TalentHub - Về trang chủ">
                <img src="./assets/images/logo.svg" alt="TalentHub" width="180" height="36">
            </a>

            <div class="auth-heading">
                <div class="auth-heading__row">
                    <div>
                        <p class="auth-kicker">Yêu cầu đăng ký tổ chức</p>
                        <h2 id="register-title">Đăng ký <?= oe($label) ?></h2>
                    </div>
                    <span class="auth-role-badge"><?= oe($label) ?></span>
                </div>
                <p>Điền đầy đủ thông tin để gửi yêu cầu xác minh đến quản trị viên.</p>
            </div>

            <?php if (!isset($forcedOrganizationType)): ?>
                <nav class="org-type-switch" aria-label="Loại tổ chức">
                    <a class="<?= $type === 'enterprise' ? 'is-active' : '' ?>" href="?type=enterprise" <?= $type === 'enterprise' ? 'aria-current="page"' : '' ?>>Doanh nghiệp</a>
                    <a class="<?= $type === 'school' ? 'is-active' : '' ?>" href="?type=school" <?= $type === 'school' ? 'aria-current="page"' : '' ?>>Nhà trường</a>
                </nav>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="auth-alert auth-alert--error" role="alert" tabindex="-1" data-error-summary><?= oe($error) ?></div>
            <?php endif; ?>

            <form class="auth-form" method="post" data-auth-form>
                <input type="hidden" name="type" value="<?= oe($type) ?>">
                <div class="auth-form-grid">
                    <div class="auth-field auth-field--full">
                        <label for="organizationName">Tên <?= oe($label) ?></label>
                        <input id="organizationName" name="organizationName" value="<?= oe($values['organizationName']) ?>" autocomplete="organization" minlength="2" maxlength="255" required>
                    </div>
                    <div class="auth-field">
                        <label for="fullName">Người đại diện</label>
                        <input id="fullName" name="fullName" value="<?= oe($values['fullName']) ?>" autocomplete="name" minlength="2" maxlength="150" required>
                    </div>
                    <div class="auth-field">
                        <label for="email">Email công việc</label>
                        <input id="email" name="email" type="email" value="<?= oe($values['email']) ?>" autocomplete="email" maxlength="255" required>
                    </div>
                    <div class="auth-field">
                        <label for="phone">Số điện thoại</label>
                        <input id="phone" name="phone" type="tel" value="<?= oe($values['phone']) ?>" autocomplete="tel" minlength="6" maxlength="30" required>
                    </div>
                    <div class="auth-field auth-field--full">
                        <label for="address">Địa chỉ</label>
                        <input id="address" name="address" value="<?= oe($values['address']) ?>" autocomplete="street-address" minlength="5" maxlength="500" required>
                    </div>
                    <div class="auth-field">
                        <label for="password">Mật khẩu dự kiến</label>
                        <div class="auth-password">
                            <input id="password" name="password" type="password" autocomplete="new-password" minlength="12" maxlength="255" required>
                            <button type="button" class="auth-password__toggle" data-password-toggle aria-controls="password" aria-pressed="false">Hiện</button>
                        </div>
                        <span class="auth-field__hint">Mật khẩu được mã hóa và chỉ dùng để tạo tài khoản sau khi duyệt.</span>
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

                <button class="auth-submit" type="submit" data-submit>
                    <span>Gửi yêu cầu đăng ký</span>
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
