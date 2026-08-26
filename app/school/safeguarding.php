<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/bin/bootstrap.php';
require dirname(__DIR__, 2) . '/src/Bootstrap/SchoolAppContext.php';

use TalentHub\Bootstrap\SchoolAppContext;
use TalentHub\Http\ApiException;

$context = (new SchoolAppContext())->boot();
$service = $context['safeguarding'];
$session = $context['session'];
$userId = (string) $context['user']['id'];
$error = null;
$flash = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $session->assertCsrf(isset($_POST['csrfToken']) ? (string) $_POST['csrfToken'] : null);
    try {
        $action = (string) ($_POST['action'] ?? 'policy');
        if ($action === 'policy') {
            $service->updatePolicy($userId, [
                'minimumDirectContactAge' => $_POST['minimumDirectContactAge'] ?? 18,
                'guardianConsentRequired' => $_POST['guardianConsentRequired'] ?? null,
                'schoolApprovalRequired' => $_POST['schoolApprovalRequired'] ?? null,
            ]);
            $flash = 'Đã cập nhật chính sách an toàn học sinh.';
        } elseif ($action === 'approve') {
            $service->approve($userId, (string) ($_POST['studentId'] ?? ''), (string) ($_POST['enterpriseId'] ?? ''), (string) ($_POST['expiresAt'] ?? ''));
            $flash = 'Đã phê duyệt phạm vi học sinh – doanh nghiệp.';
        } elseif ($action === 'revoke') {
            $service->revokeApproval($userId, (string) ($_POST['approvalId'] ?? ''));
            $flash = 'Đã thu hồi phê duyệt.';
        }
    } catch (ApiException|RuntimeException $exception) {
        $error = $exception->getMessage();
    }
}

$policy = $service->policy($userId);
$approvals = $service->approvals($userId);
$students = $context['service']->students($userId, 100, 0);
$partners = $context['partnerships']->listSchoolPartnerships($userId, 'approved')['items'];
$schoolInfo = ['name' => $context['school']['name'], 'logo_initials' => mb_substr($context['school']['name'], 0, 2), 'level' => $context['school']['level'] ?? '', 'district' => $context['school']['address'] ?? '', 'academic_year' => $context['school']['academicYear'] ?? ''];
$currentRoute = '/app/school/safeguarding.php';
$pageTitle = 'An toàn học sinh';
ob_start();
?>
<?php $pageDescription = 'Quản lý ngưỡng liên hệ, yêu cầu consent và phê duyệt có thời hạn theo từng doanh nghiệp.'; include __DIR__ . '/includes/page-banner.php'; ?>
<?php if ($flash): ?><div class="school-flash school-flash--success"><?= htmlspecialchars($flash); ?></div><?php endif; ?>
<?php if ($error): ?><div class="school-flash school-flash--error"><?= htmlspecialchars($error); ?></div><?php endif; ?>
<div class="school-grid-2col">
<section class="school-section-box"><h2 class="school-section-box__title">Chính sách trường</h2><form method="post" class="school-form"><input type="hidden" name="csrfToken" value="<?= htmlspecialchars($session->csrfToken(), ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="action" value="policy"><label class="school-form__field"><span>Tuổi tối thiểu để liên hệ trực tiếp</span><input type="number" min="13" max="25" name="minimumDirectContactAge" value="<?= (int) $policy['minimumDirectContactAge']; ?>" required></label><label class="school-form__field school-form__field--checkbox"><input type="checkbox" name="guardianConsentRequired" value="1" <?= $policy['guardianConsentRequired'] ? 'checked' : ''; ?>><span>Yêu cầu consent phụ huynh với học sinh dưới ngưỡng tuổi</span></label><label class="school-form__field school-form__field--checkbox"><input type="checkbox" name="schoolApprovalRequired" value="1" <?= $policy['schoolApprovalRequired'] ? 'checked' : ''; ?>><span>Yêu cầu phê duyệt của trường</span></label><div class="school-form__actions"><button class="btn btn-primary">Lưu chính sách</button></div></form></section>
<section class="school-section-box"><h2 class="school-section-box__title">Phê duyệt mới</h2><form method="post" class="school-form"><input type="hidden" name="csrfToken" value="<?= htmlspecialchars($session->csrfToken(), ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="action" value="approve"><label class="school-form__field"><span>Học sinh</span><select name="studentId" required><option value="">Chọn học sinh</option><?php foreach ($students as $student): ?><option value="<?= htmlspecialchars((string) $student['id']); ?>"><?= htmlspecialchars((string) $student['fullName']); ?> · <?= htmlspecialchars((string) $student['className']); ?></option><?php endforeach; ?></select></label><label class="school-form__field"><span>Doanh nghiệp đối tác</span><select name="enterpriseId" required><option value="">Chọn doanh nghiệp</option><?php foreach ($partners as $partner): ?><option value="<?= htmlspecialchars((string) $partner['enterpriseId']); ?>"><?= htmlspecialchars((string) $partner['enterpriseName']); ?></option><?php endforeach; ?></select></label><label class="school-form__field"><span>Hiệu lực đến</span><input type="date" name="expiresAt" min="<?= gmdate('Y-m-d', time() + 86400); ?>" max="<?= gmdate('Y-m-d', time() + 365 * 86400); ?>" required></label><div class="school-form__actions"><button class="btn btn-primary">Phê duyệt</button></div></form></section>
</div>
<section class="school-section-box" style="margin-top:1rem"><h2 class="school-section-box__title">Lịch sử phê duyệt</h2><?php if ($approvals === []): ?><p>Chưa có phê duyệt.</p><?php else: ?><table class="school-class-table"><thead><tr><th>Học sinh</th><th>Doanh nghiệp</th><th>Hết hạn</th><th>Trạng thái</th><th></th></tr></thead><tbody><?php foreach ($approvals as $approval): ?><tr><td><?= htmlspecialchars((string) $approval['studentName']); ?></td><td><?= htmlspecialchars((string) $approval['enterpriseName']); ?></td><td><?= htmlspecialchars((string) $approval['expiresAt']); ?> UTC</td><td><?= $approval['revokedAt'] === null ? 'Đang hiệu lực' : 'Đã thu hồi'; ?></td><td><?php if ($approval['revokedAt'] === null): ?><form method="post"><input type="hidden" name="csrfToken" value="<?= htmlspecialchars($session->csrfToken(), ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="action" value="revoke"><input type="hidden" name="approvalId" value="<?= htmlspecialchars((string) $approval['id']); ?>"><button class="btn btn-sm btn-outline" data-confirm="Thu hồi phê duyệt này?">Thu hồi</button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></section>
<?php
$pageBody = ob_get_clean();
$extraStyles = '';
require __DIR__ . '/includes/layout.php';
