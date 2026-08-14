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

$flash = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
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

$extraStyles = <<<'HTML'
<style>
.school-form__grid { display: grid; gap: 1rem; margin-top: 1rem; }
.school-form__field { display: flex; flex-direction: column; gap: 0.375rem; font-size: 0.875rem; color: var(--text-secondary); }
.school-form__field input { width: 100%; padding: 0.625rem 0.75rem; border-radius: var(--radius-sm); border: 1px solid var(--border); background: var(--surface); color: var(--text-primary); font-size: 0.9375rem; }
.school-form__field input:focus { outline: 2px solid #2563EB; outline-offset: 1px; }
.school-form__field em { color: #DC2626; font-style: normal; margin-left: 2px; }
.school-form__actions { display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem; }
.school-flash { padding: 0.75rem 1rem; border-radius: var(--radius-sm); margin-top: 1rem; font-size: 0.875rem; }
.school-flash--success { background: #ECFDF5; color: #047857; border: 1px solid #6EE7B7; }
.school-flash--error { background: #FEF2F2; color: #B91C1C; border: 1px solid #FCA5A5; }
</style>
HTML;

require __DIR__ . '/includes/layout.php';