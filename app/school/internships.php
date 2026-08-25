<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/bin/bootstrap.php';
require dirname(__DIR__, 2) . '/src/Bootstrap/SchoolAppContext.php';

use TalentHub\Bootstrap\SchoolAppContext;
use TalentHub\Http\ApiException;

$context = (new SchoolAppContext())->boot();
$service = $context['service'];
$session = $context['session'];
$userId = $context['user']['id'];
$status = isset($_GET['status']) && $_GET['status'] !== '' ? (string) $_GET['status'] : null;
$flash = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $session->assertCsrf(isset($_POST['csrfToken']) ? (string) $_POST['csrfToken'] : null);
        $service->assignInternshipMentor($userId, (string) ($_POST['applicationId'] ?? ''), (string) ($_POST['mentorTeacherId'] ?? ''));
        $flash = 'Đã gán mentor cùng trường cho học sinh.';
    } catch (ApiException $exception) {
        $error = $exception->getMessage();
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

try {
    $applications = $service->internshipApplications($userId, $status);
} catch (ApiException $exception) {
    $applications = [];
    $error = $exception->getMessage();
}
$teachers = $service->teachers($userId, 200, 0);
$schoolInfo = [
    'name' => $context['school']['name'],
    'logo_initials' => mb_substr($context['school']['name'], 0, 2),
    'level' => $context['school']['level'] ?? 'Trung học',
    'district' => $context['school']['address'] ?? '',
    'academic_year' => $context['school']['academicYear'] ?? '',
];
$currentRoute = '/app/school/internships.php';
$pageTitle = 'Theo dõi thực tập';

ob_start();
$pageDescription = 'Ứng tuyển của học sinh thuộc trường; nhà trường chỉ gán mentor, không thay đổi quyết định tuyển dụng.';
include __DIR__ . '/includes/page-banner.php';
?>
<?php if ($flash !== null): ?><div class="school-flash school-flash--success"><?= htmlspecialchars($flash); ?></div><?php endif; ?>
<?php if ($error !== null): ?><div class="school-flash school-flash--error"><?= htmlspecialchars($error); ?></div><?php endif; ?>

<div class="school-section-box">
    <form method="get" style="display:flex;gap:.75rem;margin-bottom:1rem;">
        <select name="status" class="school-inline-select">
            <option value="">Tất cả trạng thái</option>
            <?php foreach (['submitted','reviewing','interview','accepted','declined','withdrawn'] as $item): ?>
                <option value="<?= $item; ?>" <?= $status === $item ? 'selected' : ''; ?>><?= htmlspecialchars($item); ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-outline" type="submit">Lọc</button>
    </form>

    <?php if ($applications === []): ?>
        <p>Chưa có ứng tuyển phù hợp.</p>
    <?php else: ?>
        <table class="school-class-table">
            <thead><tr><th>Học sinh</th><th>Vị trí</th><th>Doanh nghiệp</th><th>Trạng thái</th><th>Mentor</th></tr></thead>
            <tbody>
            <?php foreach ($applications as $application): ?>
                <tr>
                    <td><?= htmlspecialchars((string) $application['studentName']); ?></td>
                    <td><?= htmlspecialchars((string) $application['postTitle']); ?></td>
                    <td><?= htmlspecialchars((string) $application['enterpriseName']); ?></td>
                    <td><span class="school-class-badge school-class-badge--neutral"><?= htmlspecialchars((string) $application['status']); ?></span></td>
                    <td>
                        <?php if (in_array($application['status'], ['interview', 'accepted'], true)): ?>
                            <form method="post" style="display:flex;gap:.5rem;align-items:center;">
                                <input type="hidden" name="csrfToken" value="<?= htmlspecialchars($session->csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="applicationId" value="<?= htmlspecialchars((string) $application['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                <select name="mentorTeacherId" required class="school-inline-select">
                                    <option value="">Chọn mentor</option>
                                    <?php foreach ($teachers as $teacher): ?>
                                        <?php if ($teacher['userStatus'] === 'active'): ?>
                                            <option value="<?= htmlspecialchars($teacher['id'], ENT_QUOTES, 'UTF-8'); ?>" <?= ($application['mentorTeacherId'] ?? null) === $teacher['id'] ? 'selected' : ''; ?>><?= htmlspecialchars($teacher['fullName']); ?></option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                                <button class="btn btn-sm btn-outline" type="submit">Lưu</button>
                            </form>
                        <?php else: ?>
                            <?= htmlspecialchars((string) ($application['mentorName'] ?? 'Chưa gán')); ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php
$pageBody = ob_get_clean();
$extraStyles = '<style>.school-inline-select{padding:.5rem;border:1px solid var(--border);border-radius:6px;background:var(--surface)}</style>';
require __DIR__ . '/includes/layout.php';
