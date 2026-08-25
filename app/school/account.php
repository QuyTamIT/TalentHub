<?php
/**
 * TalentHub - School Dashboard Account Page
 * Đổi mật khẩu của tài khoản school admin hiện tại.
 */
declare(strict_types=1);

require dirname(__DIR__, 2) . '/bin/bootstrap.php';
require dirname(__DIR__, 2) . '/src/Bootstrap/SchoolAppContext.php';

use TalentHub\Bootstrap\SchoolAppContext;
use TalentHub\Http\ApiException;

$context = (new SchoolAppContext())->boot();
$service = $context['service'];
$userId  = $context['user']['id'];
$session = $context['session'];

$flash = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $session->assertCsrf(isset($_POST['csrfToken']) ? (string) $_POST['csrfToken'] : null);
        $service->changePassword(
            $userId,
            (string) ($_POST['currentPassword'] ?? ''),
            (string) ($_POST['newPassword'] ?? ''),
        );
        $flash = 'Đã đổi mật khẩu thành công.';
    } catch (ApiException $e) {
        $error = $e->getMessage();
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

$currentRoute = '/app/school/account.php';
$pageTitle    = 'Tài khoản';

ob_start();
?>
<?php
$pageDescription = 'Đổi mật khẩu cho tài khoản quản trị đang đăng nhập.';
include __DIR__ . '/includes/page-banner.php';
?>
<div class="school-section-box" style="max-width: 520px;">
    <div class="school-section-box__header">
        <div>
            <h2 style="font-size: 1.25rem; font-weight: 700; margin: 0;">Đổi mật khẩu</h2>
            <p style="font-size: 0.875rem; color: var(--text-secondary); margin: 0.25rem 0 0;">
                Đang đăng nhập với tài khoản: <?= htmlspecialchars($context['user']['email']); ?>
            </p>
        </div>
    </div>

    <?php if ($flash): ?>
        <div class="school-flash school-flash--success"><?= htmlspecialchars($flash); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="school-flash school-flash--error"><?= htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="post" class="school-form" novalidate>
        <input type="hidden" name="csrfToken" value="<?= htmlspecialchars($session->csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
        <div class="school-form__grid" style="grid-template-columns: 1fr;">
            <label class="school-form__field">
                <span>Mật khẩu hiện tại <em>*</em></span>
                <input type="password" name="currentPassword" required minlength="6" autocomplete="current-password">
            </label>
            <label class="school-form__field">
                <span>Mật khẩu mới <em>*</em></span>
                <input type="password" name="newPassword" required minlength="8" maxlength="128" autocomplete="new-password">
            </label>
            <p style="font-size: 0.8125rem; color: var(--text-muted); margin: 0;">
                Mật khẩu mới phải có ít nhất 8 ký tự và tối đa 128 ký tự.
            </p>
        </div>
        <div class="school-form__actions">
            <button type="submit" class="btn btn-primary">Cập nhật mật khẩu</button>
        </div>
    </form>
</div>
<?php
$pageBody = ob_get_clean();

$extraStyles = '';

require __DIR__ . '/includes/layout.php';
