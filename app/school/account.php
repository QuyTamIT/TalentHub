<?php
/**
 * TalentHub - School Dashboard Account & Profile Page
 * "Hồ sơ & Tài khoản Nhà trường"
 *
 * Gồm 2 phần chuẩn hóa:
 * - Phần 1: Thông tin tổ chức / trường học (schools table)
 * - Phần 2: Bảo mật & Đổi mật khẩu cho tài khoản quản trị
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bin/bootstrap.php';
require_once dirname(__DIR__, 2) . '/src/Bootstrap/SchoolAppContext.php';

use TalentHub\Bootstrap\SchoolAppContext;
use TalentHub\Http\ApiException;

$appContext = new SchoolAppContext();
$context    = $appContext->boot();
$service    = $context['service'];
$userId     = $context['user']['id'];
$session    = $context['session'];
$pdo        = $context['pdo'];
$school     = $context['school'];

$flash = null;
$error = null;


if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $session->assertCsrf(isset($_POST['csrfToken']) ? (string) $_POST['csrfToken'] : null);
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'update_profile') {
            $name         = trim((string) ($_POST['name'] ?? $school['name']));
            $level        = trim((string) ($_POST['level'] ?? $school['level']));
            $address      = trim((string) ($_POST['address'] ?? ''));
            $phone        = trim((string) ($_POST['phone'] ?? ''));
            $email        = trim((string) ($_POST['email'] ?? ''));
            $website      = trim((string) ($_POST['website'] ?? ''));
            $academicYear = trim((string) ($_POST['academicYear'] ?? $school['academicYear']));

            $allowedLevels = ['Trung Học Cơ Sở', 'Trung Học Phổ Thông', 'Cao Đẳng / Đại Học'];
            if (!in_array($level, $allowedLevels, true)) {
                throw new ApiException(422, 'VALIDATION_FAILED', 'Loại hình đào tạo không hợp lệ. Vui lòng chọn: Trung Học Cơ Sở, Trung Học Phổ Thông hoặc Cao Đẳng / Đại Học.');
            }

            $updated = $service->update($userId, [
                'name'         => $name,
                'level'        => $level,
                'address'      => $address,
                'phone'        => $phone,
                'email'        => $email,
                'website'      => $website,
                'academicYear' => $academicYear,
            ]);

            $school = $updated;
            $context['school'] = $updated;
            $flash = 'Đã lưu thông tin trường học thành công.';
        } elseif ($action === 'change_password') {
            $currentPassword = (string) ($_POST['currentPassword'] ?? '');
            $newPassword     = (string) ($_POST['newPassword'] ?? '');
            $confirmPassword = (string) ($_POST['confirmPassword'] ?? '');

            if ($newPassword === '' || strlen($newPassword) < 6) {
                throw new ApiException(422, 'VALIDATION_FAILED', 'Mật khẩu mới phải có ít nhất 6 ký tự.');
            }
            if ($newPassword !== $confirmPassword) {
                throw new ApiException(422, 'VALIDATION_FAILED', 'Mật khẩu xác nhận không khớp với mật khẩu mới.');
            }

            $service->changePassword($userId, $currentPassword, $newPassword);
            $flash = 'Đã đổi mật khẩu thành công.';
        }
    } catch (ApiException $e) {
        $error = $e->getMessage();
    } catch (\Throwable $e) {
        $error = 'Đã xảy ra lỗi: ' . $e->getMessage();
    }
}

// Logo Initials
$cleanSchoolName = trim((string)($school['name'] ?? ''));
if ($cleanSchoolName !== '') {
    $words = preg_split('/\s+/', $cleanSchoolName, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $initials = count($words) > 1 
        ? mb_strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[count($words) - 1], 0, 1))
        : mb_strtoupper(mb_substr($cleanSchoolName, 0, 2));
} else {
    $initials = 'NT';
}

// Normalize school level for select options and display
$rawSchoolLevel   = trim((string) ($school['level'] ?? ''));
$schoolNameLower  = mb_strtolower(trim((string) ($school['name'] ?? '')));
$schoolLevelLower = mb_strtolower($rawSchoolLevel);

$selectedLevel = '';
if ($rawSchoolLevel === 'Trung Học Cơ Sở'
    || in_array($schoolLevelLower, ['cap2', 'c2', 'trung học cơ sở', 'thcs'], true)
    || str_contains($schoolLevelLower, 'cơ sở') || str_contains($schoolLevelLower, 'thcs')
    || ($rawSchoolLevel === '' && (str_contains($schoolNameLower, 'thcs') || str_contains($schoolNameLower, 'trung học cơ sở') || str_contains($schoolNameLower, 'cấp 2')))) {
    $selectedLevel = 'Trung Học Cơ Sở';
} elseif ($rawSchoolLevel === 'Trung Học Phổ Thông'
    || in_array($schoolLevelLower, ['cap3', 'c3', 'trung học phổ thông', 'thpt'], true)
    || str_contains($schoolLevelLower, 'phổ thông') || str_contains($schoolLevelLower, 'thpt')
    || ($rawSchoolLevel === '' && (str_contains($schoolNameLower, 'thpt') || str_contains($schoolNameLower, 'trung học phổ thông') || str_contains($schoolNameLower, 'cấp 3')))) {
    $selectedLevel = 'Trung Học Phổ Thông';
} elseif ($rawSchoolLevel === 'Cao Đẳng / Đại Học'
    || in_array($schoolLevelLower, ['cao_dang_dai_hoc', 'cao đẳng / đại học', 'cao đẳng', 'đại học', 'học viện'], true)
    || str_contains($schoolLevelLower, 'cao đẳng') || str_contains($schoolLevelLower, 'đại học') || str_contains($schoolLevelLower, 'học viện')
    || ($rawSchoolLevel === '' && (str_contains($schoolNameLower, 'cao đẳng') || str_contains($schoolNameLower, 'đại học') || str_contains($schoolNameLower, 'btec')))) {
    $selectedLevel = 'Cao Đẳng / Đại Học';
} else {
    $selectedLevel = $rawSchoolLevel !== '' ? $rawSchoolLevel : 'Cao Đẳng / Đại Học';
}

// Calculate active student count accurately from database
$totalStudents = 0;
if ($pdo !== null && !empty($school['id'])) {
    try {
        $stCntStmt = $pdo->prepare("
            SELECT COUNT(sp.id)
            FROM student_profiles sp
            JOIN classes c ON c.id = sp.classId
            WHERE c.schoolId = :schoolId AND sp.studyStatus = 'active'
        ");
        $stCntStmt->execute(['schoolId' => $school['id']]);
        $totalStudents = (int) $stCntStmt->fetchColumn();
    } catch (\Throwable $e) {}
}
if ($totalStudents === 0) {
    $totalStudents = (int) ($school['studentCount'] ?? 11);
}

$schoolInfo = [
    'name'          => $school['name'],
    'logo_initials' => $initials,
    'level'         => !empty($school['level']) ? $school['level'] : $selectedLevel,
    'district'      => $school['address'] ?? '',
    'academic_year' => $school['academicYear'] ?? '2025 - 2026',
    'total_students'=> $totalStudents,
];

$currentRoute = '/app/school/account.php';
$pageTitle    = 'Hồ sơ & Tài khoản Nhà trường';

ob_start();
?>
<?php
$pageDescription = 'Quản lý thông tin tổ chức giáo dục, liên hệ và thiết lập bảo mật tài khoản Ban Giám hiệu.';
include __DIR__ . '/includes/page-banner.php';
?>

<!-- Quick Alerts -->
<?php if ($flash): ?>
    <div class="school-flash school-flash--success" role="status" style="margin-bottom: 1.5rem;">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right: 0.5rem;"><polyline points="20 6 9 17 4 12"></polyline></svg>
        <?= htmlspecialchars($flash); ?>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="school-flash school-flash--error" role="alert" style="margin-bottom: 1.5rem;">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right: 0.5rem;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
        <?= htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<!-- Organization Identity Header Banner -->
<div class="school-org-hero school-flex-between" style="background: #FFFFFF; border: 1px solid var(--border); border-radius: 12px; padding: 1.5rem; margin-bottom: 1.75rem; flex-wrap: wrap; gap: 1.25rem;">
    <div style="display: flex; align-items: center; gap: 1.25rem;">
        <div class="school-avatar school-avatar--lg school-avatar--blue">
            <?= htmlspecialchars($initials); ?>
        </div>
        <div>
            <div style="display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap;">
                <h2 style="font-size: 1.35rem; font-weight: 700; color: var(--text-primary); margin: 0;">
                    <?= htmlspecialchars(!empty($school['name']) ? $school['name'] : 'Nhà trường'); ?>
                </h2>
                <?php if (!empty($school['code'])): ?>
                    <span class="school-badge school-badge--info">
                        Mã: <?= htmlspecialchars((string) $school['code']); ?>
                    </span>
                <?php elseif (!empty($school['id'])): ?>
                    <span class="school-badge school-badge--info">
                        Mã: <?= htmlspecialchars(substr($school['id'], 0, 8)); ?>
                    </span>
                <?php endif; ?>
                <?php if (($school['status'] ?? '') === 'active'): ?>
                    <span class="school-badge school-badge--success">
                        ✓ Đã xác thực
                    </span>
                <?php else: ?>
                    <span class="school-badge school-badge--neutral">
                        Chưa thiết lập
                    </span>
                <?php endif; ?>
            </div>
            <p style="font-size: 0.875rem; color: var(--text-secondary); margin: 0.35rem 0 0 0;">
                <?= htmlspecialchars(!empty($school['level']) ? $school['level'] : $selectedLevel); ?> • Niên khóa: <strong><?= htmlspecialchars(!empty($school['academicYear']) ? $school['academicYear'] : '—'); ?></strong> • Quản trị: <strong><?= htmlspecialchars($context['user']['email']); ?></strong>
            </p>
        </div>
    </div>
    <div style="display: flex; gap: 1.5rem; text-align: right;">
        <div>
            <div style="font-size: 1.25rem; font-weight: 700; color: #1D4ED8;"><?= (int) $totalStudents; ?></div>
            <div class="school-text-xs-upper"><?= $selectedLevel === 'Cao Đẳng / Đại Học' ? 'Sinh viên' : 'Học sinh'; ?></div>
        </div>
        <div style="border-left: 1px solid var(--border); padding-left: 1.5rem;">
            <div style="font-size: 1.25rem; font-weight: 700; color: #059669;">Hoạt động</div>
            <div class="school-text-xs-upper">Trạng thái</div>
        </div>
    </div>
</div>

<!-- Two-Column Profile & Account Form -->
<div style="display: grid; grid-template-columns: 1.4fr 1fr; gap: 1.75rem; align-items: start;">

    <!-- PHẦN 1: THÔNG TIN TỔ CHỨC / TRƯỜNG HỌC -->
    <section class="school-section-box" style="margin-bottom: 0;">
        <div class="school-section-box__header school-section-box__header--bordered">
            <div>
                <h3 class="school-section-box__title" style="margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 21h18"></path>
                        <path d="M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16"></path>
                        <path d="M9 9h1"></path>
                        <path d="M9 13h1"></path>
                        <path d="M9 17h1"></path>
                        <path d="M14 9h1"></path>
                        <path d="M14 13h1"></path>
                        <path d="M14 17h1"></path>
                    </svg>
                    Thông tin Tổ chức / Trường học
                </h3>
                <p class="school-section-box__subtitle" style="margin: 0.25rem 0 0 0;">
                    Thông tin pháp nhân đào tạo, liên hệ tuyển dụng và hợp tác doanh nghiệp.
                </p>
            </div>
        </div>

        <form method="post" class="school-form" novalidate>
            <input type="hidden" name="csrfToken" value="<?= htmlspecialchars($session->csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="action" value="update_profile">

            <div class="school-form__grid school-form__grid--2col">
                <label class="school-form__field school-form__field--full">
                    <span>Tên đơn vị / Trường học <em>*</em></span>
                    <input type="text" name="name" value="<?= htmlspecialchars($school['name']); ?>" maxlength="255" required style="font-weight: 600;">
                </label>

                <label class="school-form__field">
                    <span>Mã trường / Đơn vị</span>
                    <input type="text" name="school_code" value="<?= htmlspecialchars((string)($school['code'] ?? (!empty($school['id']) ? substr($school['id'], 0, 8) : ''))); ?>" readonly style="background: #F8FAFC; cursor: not-allowed;">
                </label>

                <label class="school-form__field">
                    <span>Loại hình đào tạo <em>*</em></span>
                    <select name="level" class="typeui-select" required>
                        <option value="Trung Học Cơ Sở" <?= $selectedLevel === 'Trung Học Cơ Sở' ? 'selected' : ''; ?>>Trung Học Cơ Sở</option>
                        <option value="Trung Học Phổ Thông" <?= $selectedLevel === 'Trung Học Phổ Thông' ? 'selected' : ''; ?>>Trung Học Phổ Thông</option>
                        <option value="Cao Đẳng / Đại Học" <?= $selectedLevel === 'Cao Đẳng / Đại Học' ? 'selected' : ''; ?>>Cao Đẳng / Đại Học</option>
                    </select>
                </label>

                <label class="school-form__field school-form__field--full">
                    <span>Địa chỉ trụ sở chính</span>
                    <input type="text" name="address" value="<?= htmlspecialchars((string) ($school['address'] ?? '')); ?>" placeholder="Số nhà, đường, quận/huyện, tỉnh/thành phố">
                </label>

                <label class="school-form__field">
                    <span>Website chính thức</span>
                    <input type="url" name="website" value="<?= htmlspecialchars((string) ($school['website'] ?? '')); ?>" placeholder="https://...">
                </label>

                <label class="school-form__field">
                    <span>Email liên hệ</span>
                    <input type="email" name="email" value="<?= htmlspecialchars((string) ($school['email'] ?? '')); ?>" placeholder="contact@school.edu.vn">
                </label>

                <label class="school-form__field">
                    <span>Số điện thoại liên hệ</span>
                    <input type="tel" name="phone" value="<?= htmlspecialchars((string) ($school['phone'] ?? '')); ?>" placeholder="024 1234 5678">
                </label>

                <label class="school-form__field">
                    <span>Niên khóa hiện tại</span>
                    <input type="text" name="academicYear" value="<?= htmlspecialchars((string) ($school['academicYear'] ?? '2025 - 2026')); ?>" placeholder="2025 - 2026">
                </label>
            </div>

            <div class="school-form__actions" style="padding-top: 1rem;">
                <button type="submit" class="btn btn-primary" style="font-weight:600;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                    Lưu thông tin trường
                </button>
            </div>
        </form>
    </section>

    <!-- PHẦN 2: BẢO MẬT & ĐỔI MẬT KHẨU -->
    <section class="school-section-box" style="margin-bottom: 0;">
        <div class="school-section-box__header school-section-box__header--bordered">
            <div>
                <h3 class="school-section-box__title" style="margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                    Bảo mật & Đổi mật khẩu
                </h3>
                <p class="school-section-box__subtitle" style="margin: 0.25rem 0 0 0;">
                    Tài khoản: <strong><?= htmlspecialchars($context['user']['email']); ?></strong>
                </p>
            </div>
        </div>

        <form method="post" class="school-form" novalidate>
            <input type="hidden" name="csrfToken" value="<?= htmlspecialchars($session->csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="action" value="change_password">

            <div class="school-form__grid">
                <label class="school-form__field">
                    <span>Mật khẩu hiện tại <em>*</em></span>
                    <input type="password" name="currentPassword" required autocomplete="current-password" placeholder="Nhập mật khẩu hiện tại">
                </label>

                <label class="school-form__field">
                    <span>Mật khẩu mới <em>*</em></span>
                    <input type="password" name="newPassword" required minlength="6" maxlength="128" autocomplete="new-password" placeholder="Tối thiểu 6 ký tự">
                </label>

                <label class="school-form__field">
                    <span>Xác nhận mật khẩu mới <em>*</em></span>
                    <input type="password" name="confirmPassword" required minlength="6" maxlength="128" autocomplete="new-password" placeholder="Nhập lại mật khẩu mới">
                </label>
            </div>

            <div class="school-card-subtle school-text-sm-muted" style="margin-top: 1rem;">
                <span style="font-weight: 600; color: #1E293B;">Lưu ý bảo mật:</span>
                Mật khẩu nên chứa ít nhất 6 ký tự kết hợp chữ cái và số để đảm bảo tính an toàn cho dữ liệu sinh viên.
            </div>

            <div class="school-form__actions" style="padding-top: 1rem;">
                <button type="submit" class="btn btn-secondary" style="font-weight:600;width:100%;justify-content:center;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                    Cập nhật mật khẩu
                </button>
            </div>
        </form>
    </section>
</div>

<?php
$pageBody = ob_get_clean();
$extraStyles = '';
require __DIR__ . '/includes/layout.php';
