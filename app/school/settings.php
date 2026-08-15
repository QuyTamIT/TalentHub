<?php
/**
 * TalentHub - School Dashboard Settings Page
 * Cập nhật hồ sơ nhà trường (logo, địa chỉ, level, niên khóa, ...).
 */
declare(strict_types=1);

require dirname(__DIR__, 2) . '/bin/bootstrap.php';
require dirname(__DIR__, 2) . '/src/Bootstrap/SchoolAppContext.php';

use TalentHub\Bootstrap\SchoolAppContext;
use TalentHub\Http\ApiException;

$context = (new SchoolAppContext())->boot();
$service = $context['service'];
$userId  = $context['user']['id'];

$schoolInfo = [
    'name'          => $context['school']['name'],
    'logo_initials' => mb_substr($context['school']['name'], 0, 2),
    'level'         => $context['school']['level'] ?? 'Trung học',
    'district'      => $context['school']['address'] ?? '',
    'academic_year' => $context['school']['academicYear'] ?? '',
];

$currentRoute = '/app/school/settings.php';
$pageTitle    = 'Cài đặt nhà trường';

$flash = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_FILES['logoFile']) && is_array($_FILES['logoFile']) && ($_FILES['logoFile']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['logoFile'];
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new \TalentHub\Http\ApiException(422, 'VALIDATION_FAILED', 'Upload logo thất bại (mã ' . (int) $file['error'] . ').');
            }
            $contents = file_get_contents((string) $file['tmp_name']);
            $mime = (string) (mime_content_type((string) $file['tmp_name']) ?: '');
            if ($contents === false || $mime === '') {
                throw new \TalentHub\Http\ApiException(422, 'VALIDATION_FAILED', 'Không đọc được tệp logo.');
            }
            $service->uploadLogo($userId, [
                'mime'     => $mime,
                'contents' => $contents,
            ]);
            $flash = 'Đã cập nhật logo nhà trường.';
        } else {
            $updated = $service->update($userId, $_POST);
            $context['school'] = $updated;
            $flash = 'Đã cập nhật hồ sơ nhà trường.';
        }
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
$school = $context['school'];

ob_start();
?>
<?php
$pageDescription = 'Cập nhật hồ sơ nhà trường: tên, địa chỉ, cấp học, niên khóa, liên hệ.';
include __DIR__ . '/includes/page-banner.php';
?>
<div class="school-section-box" style="margin-bottom: 1.5rem;">
    <?php if ($flash): ?>
        <div class="school-flash school-flash--success" role="status"><?= htmlspecialchars($flash); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="school-flash school-flash--error" role="alert"><?= htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="post" class="school-form" enctype="multipart/form-data" novalidate>
        <div class="school-form__grid">
            <label class="school-form__field">
                <span>Tên trường <em>*</em></span>
                <input type="text" name="name" value="<?= htmlspecialchars($school['name']); ?>" maxlength="255" required>
            </label>
            <label class="school-form__field">
                <span>Cấp học</span>
                <input type="text" name="level" value="<?= htmlspecialchars($school['level'] ?? ''); ?>" maxlength="100" placeholder="Tiểu học, THCS, THPT...">
            </label>
            <label class="school-form__field">
                <span>Niên khóa <em>*</em></span>
                <input type="text" name="academicYear" value="<?= htmlspecialchars($school['academicYear']); ?>" maxlength="20" placeholder="2025 - 2026" required>
            </label>
            <label class="school-form__field">
                <span>Số điện thoại</span>
                <input type="text" name="phone" value="<?= htmlspecialchars($school['phone'] ?? ''); ?>" maxlength="30">
            </label>
            <label class="school-form__field">
                <span>Email liên hệ</span>
                <input type="email" name="email" value="<?= htmlspecialchars($school['email'] ?? ''); ?>" maxlength="255">
            </label>
            <label class="school-form__field">
                <span>Website</span>
                <input type="url" name="website" value="<?= htmlspecialchars($school['website'] ?? ''); ?>" maxlength="500" placeholder="https://...">
            </label>
            <label class="school-form__field school-form__field--full">
                <span>Địa chỉ</span>
                <input type="text" name="address" value="<?= htmlspecialchars($school['address'] ?? ''); ?>" maxlength="500">
            </label>
<?php if ($school['logoUrl']): ?>
                <div style="margin-bottom: 0.75rem;">
                    <img src="<?= htmlspecialchars($school['logoUrl']); ?>" alt="Logo hiện tại" style="max-height: 96px; border-radius: 8px; border: 1px solid var(--border);">
                </div>
                <?php endif; ?>
                <label class="school-form__field school-form__field--full">
                    <span>Upload logo mới (PNG/JPEG/WebP, &lt; 3MB)</span>
                    <input type="file" name="logoFile" accept="image/png,image/jpeg,image/webp">
                </label>
                <label class="school-form__field school-form__field--full">
                    <span>Hoặc dán URL logo</span>
                    <input type="url" name="logoUrl" value="<?= htmlspecialchars($school['logoUrl'] ?? ''); ?>" maxlength="500" placeholder="/assets/img/schools/logo.png">
                </label>
            </div>

            <div class="school-form__actions">
                <a href="/app/school/" class="btn btn-outline">Huỷ</a>
                <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
            </div>
        </form>
    </div>
<?php
$pageBody = ob_get_clean();

$extraStyles = <<<HTML
<style>
.school-form__grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; margin-top: 1rem; }
.school-form__field { display: flex; flex-direction: column; gap: 0.375rem; font-size: 0.875rem; color: var(--text-secondary); }
.school-form__field--full { grid-column: 1 / -1; }
.school-form__field input { width: 100%; padding: 0.625rem 0.75rem; border-radius: var(--radius-sm); border: 1px solid var(--border); background: var(--surface); color: var(--text-primary); font-size: 0.9375rem; }
.school-form__field input:focus { outline: 2px solid #2563EB; outline-offset: 1px; }
.school-form__field em { color: #DC2626; font-style: normal; margin-left: 2px; }
.school-form__actions { display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem; }
.school-flash { padding: 0.75rem 1rem; border-radius: var(--radius-sm); margin-top: 1rem; font-size: 0.875rem; }
.school-flash--success { background: #ECFDF5; color: #047857; border: 1px solid #6EE7B7; }
.school-flash--error { background: #FEF2F2; color: #B91C1C; border: 1px solid #FCA5A5; }
@media (max-width: 720px) { .school-form__grid { grid-template-columns: 1fr; } }
</style>
HTML;

$extraScripts = <<<HTML
<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($flash): ?>
        showSchoolToast(<?= json_encode($flash); ?>);
    <?php endif; ?>
    <?php if ($error): ?>
        showSchoolToast(<?= json_encode($error); ?>, 'error');
    <?php endif; ?>
});
</script>
HTML;

require __DIR__ . '/includes/layout.php';
