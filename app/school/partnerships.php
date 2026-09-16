<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bin/bootstrap.php';
require_once dirname(__DIR__, 2) . '/src/Bootstrap/SchoolAppContext.php';

use TalentHub\Bootstrap\SchoolAppContext;
use TalentHub\Http\ApiException;
use TalentHub\Support\Uuid;

$context = (new SchoolAppContext())->boot();
$service = $context['partnerships'];
$session = $context['session'];
$userId = (string) $context['user']['id'];
$error = null;
$flash = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $session->assertCsrf(isset($_POST['csrfToken']) ? (string) $_POST['csrfToken'] : null);
    try {
        $context['permissions']->require($userId, 'partnership.review_own_school');
        $action = (string) ($_POST['action'] ?? 'review');
        if ($action === 'request_partnership') {
            $enterpriseId = trim((string) ($_POST['enterpriseId'] ?? ''));
            if (!Uuid::isValid($enterpriseId)) {
                throw new ApiException(422, 'VALIDATION_FAILED', 'Mã doanh nghiệp không hợp lệ.');
            }
            $service->requestPartnershipFromSchool($userId, ['enterpriseId' => $enterpriseId]);
            $flash = 'Đã thêm đối tác. Quan hệ hợp tác có hiệu lực ngay.';
        } else {
            $partnershipId = trim((string) ($_POST['partnershipId'] ?? $_POST['relationshipId'] ?? $_POST['id'] ?? ''));
            $status = trim((string) ($_POST['status'] ?? ''));
            if (!Uuid::isValid($partnershipId)) {
                throw new ApiException(422, 'VALIDATION_FAILED', 'Mã quan hệ đối tác không hợp lệ.');
            }
            $service->reviewPartnership($userId, $partnershipId, ['status' => $status]);
            $flash = match ($status) {
                'approved' => 'Đang hợp tác với doanh nghiệp.',
                'rejected' => 'Đã từ chối yêu cầu hợp tác.',
                'suspended' => 'Đã hủy hợp tác với doanh nghiệp.',
                default => 'Đã cập nhật quan hệ đối tác.',
            };
        }
    } catch (ApiException $exception) {
        $error = $exception->getMessage();
    } catch (\Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$statusFilter = isset($_GET['status']) ? trim((string) $_GET['status']) : null;
if (!in_array($statusFilter, [null, '', 'pending', 'approved', 'rejected', 'suspended'], true)) {
    $statusFilter = null;
}
$partnerships = [];
if (!empty($context['school']['id'])) {
    try {
        $partnerships = $service->listSchoolPartnerships($userId, $statusFilter)['items'];
    } catch (\Throwable) {
        $partnerships = [];
    }
}
$schoolInfo = [
    'name' => $context['school']['name'],
    'logo_initials' => mb_substr($context['school']['name'], 0, 2),
    'level' => $context['school']['level'] ?? '',
    'district' => $context['school']['address'] ?? '',
    'academic_year' => $context['school']['academicYear'] ?? '',
];
$currentRoute = '/app/school/partnerships.php';
$pageTitle = 'Đối tác doanh nghiệp';
$labels = ['pending' => 'Chờ duyệt', 'approved' => 'Đang hợp tác', 'rejected' => 'Đã từ chối', 'suspended' => 'Đã hủy hợp tác'];

$pdo = $context['pdo'] ?? null;
$availableEnterprises = [];
if ($pdo instanceof PDO) {
    try {
        $entStmt = $pdo->prepare("SELECT e.id, e.name FROM enterprises e
            WHERE e.status = 'active' AND e.verificationStatus IN ('verified', 'approved')
              AND NOT EXISTS (SELECT 1 FROM school_enterprise_partnerships sep
                  WHERE sep.enterpriseId = e.id AND sep.schoolId = :schoolId AND sep.status = 'approved')
            ORDER BY e.name ASC");
        $entStmt->execute(['schoolId' => (string) $context['school']['id']]);
        $availableEnterprises = $entStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (\Throwable) {}
}

ob_start();
?>
<?php $pageDescription = 'Thêm doanh nghiệp đã được Admin duyệt làm đối tác để kết nối cơ hội thực tập cho sinh viên.'; include __DIR__ . '/includes/page-banner.php'; ?>
<?php if ($flash): ?><div class="school-flash school-flash--success"><?= htmlspecialchars($flash); ?></div><?php endif; ?>
<?php if ($error): ?><div class="school-flash school-flash--error"><?= htmlspecialchars($error); ?></div><?php endif; ?>
<div class="school-section-box">
    <div class="school-section-box__header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <h2 class="school-section-box__title" style="margin: 0;">Quan hệ hợp tác</h2>
        <div style="display: flex; gap: 0.75rem; align-items: center;">
            <div class="school-status-dropdown" id="statusFilterDropdownContainer">
                <button type="button" class="school-status-dropdown__toggle" aria-haspopup="menu" aria-expanded="false" aria-controls="statusFilterMenu">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#6B7280" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
                    <span><?= $statusFilter ? htmlspecialchars($labels[$statusFilter]) : 'Tất cả trạng thái' ?></span>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6B7280" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                </button>
                <ul id="statusFilterMenu" class="school-status-dropdown__menu" role="menu" hidden>
                    <li role="none"><a href="?status=" role="menuitem" class="school-status-dropdown__item<?= $statusFilter === null ? ' is-selected' : ''; ?>">Tất cả trạng thái</a></li>
                    <?php foreach ($labels as $value => $label): ?>
                        <li role="none"><a href="?status=<?= htmlspecialchars($value); ?>" role="menuitem" class="school-status-dropdown__item<?= $statusFilter === $value ? ' is-selected' : ''; ?>"><?= htmlspecialchars($label); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <button type="button" class="btn btn-primary btn-sm" onclick="openAddPartnerModal()">+ Thêm đối tác</button>
        </div>
    </div>
    <?php if ($partnerships === []): ?>
        <p><?= $statusFilter ? 'Chưa có quan hệ hợp tác phù hợp.' : 'Chưa có đối tác doanh nghiệp. Chọn Thêm đối tác để bắt đầu hợp tác.'; ?></p>
    <?php else: ?>
        <table class="school-class-table"><thead><tr><th>Doanh nghiệp</th><th>Ngành</th><th>Trạng thái</th><th>Cập nhật</th><th style="text-align:right">Thao tác</th></tr></thead><tbody>
        <?php foreach ($partnerships as $item): ?><tr>
            <td><strong><?= htmlspecialchars((string) $item['enterpriseName']); ?></strong><?php if (!empty($item['website'])): ?><br><a href="<?= htmlspecialchars((string) $item['website']); ?>" rel="noopener" target="_blank">Website</a><?php endif; ?></td>
            <td><?= htmlspecialchars((string) ($item['industry'] ?? '—')); ?></td>
            <?php
            $badgeClass = match($item['status']) {
                'approved' => 'school-badge school-badge--success',
                'pending' => 'school-badge school-badge--warning',
                'rejected' => 'school-badge school-badge--danger',
                default => 'school-badge school-badge--muted',
            };
            ?>
            <td><span class="<?= $badgeClass ?>"><?= htmlspecialchars($labels[(string) $item['status']] ?? (string) $item['status']); ?></span></td>
            <td><?= htmlspecialchars((string) $item['updatedAt']); ?> UTC</td>
            <td style="text-align:right"><div style="display:flex;gap:.4rem;justify-content:flex-end">
                <?php foreach ((($item['status'] ?? '') === 'pending' ? ['approved' => 'Chấp thuận', 'rejected' => 'Từ chối'] : (($item['status'] ?? '') === 'approved' ? ['suspended' => 'Hủy hợp tác'] : [])) as $status => $label): ?>
                <form method="post"><input type="hidden" name="csrfToken" value="<?= htmlspecialchars($session->csrfToken(), ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="partnershipId" value="<?= htmlspecialchars((string) $item['id']); ?>"><input type="hidden" name="status" value="<?= $status; ?>"><button class="btn btn-sm btn-outline" type="submit" data-confirm="Xác nhận cập nhật quan hệ đối tác?"><?= htmlspecialchars($label); ?></button></form>
                <?php endforeach; ?>
            </div></td>
        </tr><?php endforeach; ?>
        </tbody></table>
    <?php endif; ?>
</div>

<!-- Thêm đối tác doanh nghiệp -->
<div id="addPartnerModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 50; align-items: center; justify-content: center;">
    <div style="background: #fff; width: 100%; max-width: 500px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); padding: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <h3 style="margin: 0; font-size: 1.25rem; font-weight: 600; color: #1E293B;">Thêm đối tác doanh nghiệp mới</h3>
            <button type="button" onclick="closeAddPartnerModal()" style="background: none; border: none; cursor: pointer; color: #64748B;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
        </div>
        
        <form method="post">
            <input type="hidden" name="csrfToken" value="<?= htmlspecialchars($session->csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="action" value="request_partnership">
            
            <div style="margin-bottom: 3rem;">
                <label for="partnerEnterpriseId" style="display: block; font-size: 0.9rem; font-weight: 500; color: #475569; margin-bottom: 0.5rem;">Doanh nghiệp đã được Admin duyệt</label>
                <select id="partnerEnterpriseId" name="enterpriseId" class="typeui-select" required>
                    <?php if ($availableEnterprises === []): ?>
                        <option value="">-- Không còn doanh nghiệp nào khả dụng --</option>
                    <?php else: ?>
                        <option value="">-- Chọn doanh nghiệp khả dụng --</option>
                        <?php foreach ($availableEnterprises as $ent): ?>
                            <option value="<?= htmlspecialchars((string) $ent['id']); ?>"><?= htmlspecialchars((string) $ent['name']); ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
                <p>Quan hệ hợp tác có hiệu lực ngay khi bạn xác nhận thêm đối tác.</p>
            </div>
            
            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; padding-top: 1rem; border-top: 1px solid #E2E8F0;">
                <button type="button" class="btn btn-outline" onclick="closeAddPartnerModal()">Hủy</button>
                <button type="submit" class="btn" <?= $availableEnterprises === [] ? 'disabled' : ''; ?> style="background-color: #C2410C; border-color: #C2410C; color: #fff;">Xác nhận thêm đối tác</button>
            </div>
        </form>
    </div>
</div>

<?php
$pageBody = ob_get_clean();
$extraStyles = '';
$extraScripts = <<<'HTML'
<script>
function openAddPartnerModal() {
    document.getElementById('addPartnerModal').style.display = 'flex';
}
function closeAddPartnerModal() {
    document.getElementById('addPartnerModal').style.display = 'none';
}
const container = document.getElementById('statusFilterDropdownContainer');
const menu = document.getElementById('statusFilterMenu');
const toggle = container?.querySelector('.school-status-dropdown__toggle');
if (container && menu && toggle) {
    toggle.addEventListener('click', function () {
        const isOpen = !menu.hidden;
        menu.hidden = isOpen;
        toggle.setAttribute('aria-expanded', String(!isOpen));
    });
}
// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    if (container && menu && !container.contains(event.target)) {
        menu.hidden = true;
        toggle?.setAttribute('aria-expanded', 'false');
    }
});
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        if (menu && !menu.hidden) {
            menu.hidden = true;
            toggle?.setAttribute('aria-expanded', 'false');
            toggle?.focus();
        }
        const modal = document.getElementById('addPartnerModal');
        if (modal && modal.style.display !== 'none') {
            closeAddPartnerModal();
        }
    }
});
const modal = document.getElementById('addPartnerModal');
if (modal) {
    modal.addEventListener('click', function(event) {
        if (event.target === modal) {
            closeAddPartnerModal();
        }
    });
}
</script>
HTML;
require __DIR__ . '/includes/layout.php';
