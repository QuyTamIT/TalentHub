<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bin/bootstrap.php';
require_once dirname(__DIR__, 2) . '/src/Bootstrap/SchoolAppContext.php';

use TalentHub\Bootstrap\SchoolAppContext;
use TalentHub\Http\ApiException;

$context = (new SchoolAppContext())->boot();
$service = $context['service'];
$session = $context['session'];
$userId = (string) $context['user']['id'];
$error = null;
$flash = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
              || isset($_POST['ajax']) 
              || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));
    
    $session->assertCsrf(isset($_POST['csrfToken']) ? (string) $_POST['csrfToken'] : null);
    try {
        $appId = (string) ($_POST['applicationId'] ?? '');
        $mentorId = (string) ($_POST['mentorTeacherId'] ?? '');
        $res = $service->assignInternshipMentor($userId, $appId, $mentorId);
        
        $mentorName = $res['mentorName'] ?? '';
        $msg = !empty($mentorName) ? "Đã phân công mentor: {$mentorName} thành công." : "Đã phân công mentor thành công.";
        if (empty($mentorId)) {
            $msg = "Đã hủy phân công mentor cho sinh viên.";
        }

        if ($isAjax) {
            @header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => true,
                'message' => $msg,
                'mentorName' => $mentorName,
                'mentorTeacherId' => $mentorId,
                'applicationId' => $appId
            ]);
            if (!defined('TEST_MODE')) { exit; }
            return;
        }
        $flash = $msg;
    } catch (\Throwable $exception) {
        if ($isAjax) {
            @header('Content-Type: application/json; charset=utf-8');
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => $exception->getMessage(),
            ]);
            if (!defined('TEST_MODE')) { exit; }
            return;
        }
        $error = $exception->getMessage();
    }
}

$oversight = $service->internshipOversight($userId);
$teachers = $service->teachers($userId, 100, 0);

// Filter and prioritize active named teachers
usort($teachers, function($a, $b) {
    if (($a['fullName'] ?? '') === 'ThS. Nguyễn Văn Hùng') return -1;
    if (($b['fullName'] ?? '') === 'ThS. Nguyễn Văn Hùng') return 1;
    return strcmp((string)($a['fullName'] ?? ''), (string)($b['fullName'] ?? ''));
});

$schoolInfo = [
    'name' => $context['school']['name'],
    'logo_initials' => mb_substr($context['school']['name'], 0, 2),
    'level' => $context['school']['level'] ?? '',
    'district' => $context['school']['address'] ?? '',
    'academic_year' => $context['school']['academicYear'] ?? '',
];
$currentRoute = '/app/school/internships.php';
$pageTitle = 'Giám sát thực tập';
$labels = [
    'submitted' => 'Đã nộp', 
    'reviewing' => 'Đang xét', 
    'interview' => 'Phỏng vấn', 
    'accepted' => 'Đã nhận', 
    'declined' => 'Từ chối', 
    'withdrawn' => 'Đã rút'
];
$badgeClasses = [
    'submitted' => 'school-badge--info',
    'reviewing' => 'school-badge--warning',
    'interview' => 'school-badge--purple',
    'accepted'  => 'school-badge--success',
    'declined'  => 'school-badge--danger',
    'withdrawn' => 'school-badge--muted',
];

ob_start();
?>
<?php $pageDescription = 'Theo dõi tiến trình tiếp nhận thực tập của sinh viên và phân công Giảng viên / Mentor hướng dẫn.'; include __DIR__ . '/includes/page-banner.php'; ?>

<?php if ($flash): ?><div class="school-flash school-flash--success" id="server-flash-success"><?= htmlspecialchars($flash); ?></div><?php endif; ?>
<?php if ($error): ?><div class="school-flash school-flash--error" id="server-flash-error"><?= htmlspecialchars($error); ?></div><?php endif; ?>

<!-- Client-side Toast notification container -->
<div id="school-toast-msg" style="display:none; margin-bottom: 1rem; padding: 0.85rem 1.25rem; border-radius: 8px; font-weight: 600; font-size: 0.95rem; transition: all 0.3s ease;"></div>

<div class="school-section-box" style="margin-bottom:1.5rem; background: #FFFFFF; border-radius: 12px; border: 1px solid #E2E8F0; padding: 1.25rem 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
    <div style="display:flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h3 style="font-size: 1.1rem; font-weight: 700; color: #0F172A; margin: 0 0 0.25rem 0;">Thống kê tiến trình thực tập</h3>
            <p style="font-size: 0.875rem; color: #64748B; margin: 0;">Tổng hợp trạng thái hồ sơ của sinh viên toàn trường</p>
        </div>
        <div style="display:flex; gap:0.75rem; flex-wrap:wrap">
            <?php foreach ($oversight['summary'] as $status => $count): ?>
                <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 0.4rem 0.85rem; display: flex; align-items: center; gap: 0.5rem;">
                    <span style="font-size: 0.85rem; color: #64748B;"><?= htmlspecialchars($labels[$status] ?? $status); ?>:</span>
                    <strong style="font-size: 0.95rem; color: #0F172A;"><?= (int) $count; ?></strong>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="school-section-box" style="background: #FFFFFF; border-radius: 12px; border: 1px solid #E2E8F0; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
        <h3 style="font-size: 1.05rem; font-weight: 700; color: #0F172A; margin: 0;">Danh sách sinh viên & Phân công Mentor</h3>
        <span style="font-size: 0.875rem; color: #64748B;">Tổng số: <?= count($oversight['items']); ?> đơn</span>
    </div>

    <?php if ($oversight['items'] === []): ?>
        <div style="text-align: center; padding: 3rem 1rem;">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#94A3B8" stroke-width="1.5" style="margin-bottom: 0.75rem;">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>
            <p style="color: #64748B; font-size: 0.95rem; margin: 0;">Chưa có đơn thực tập nào thuộc trường.</p>
        </div>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table class="school-class-table" style="width: 100%; border-collapse: separate; border-spacing: 0;">
                <thead>
                    <tr style="background: #F8FAFC;">
                        <th style="padding: 0.85rem 1rem; border-bottom: 2px solid #E2E8F0; text-align: left; font-size: 0.85rem; font-weight: 700; color: #475569;">Học sinh / Sinh viên</th>
                        <th style="padding: 0.85rem 1rem; border-bottom: 2px solid #E2E8F0; text-align: left; font-size: 0.85rem; font-weight: 700; color: #475569;">Vị trí tuyển dụng</th>
                        <th style="padding: 0.85rem 1rem; border-bottom: 2px solid #E2E8F0; text-align: left; font-size: 0.85rem; font-weight: 700; color: #475569;">Doanh nghiệp</th>
                        <th style="padding: 0.85rem 1rem; border-bottom: 2px solid #E2E8F0; text-align: left; font-size: 0.85rem; font-weight: 700; color: #475569;">Trạng thái</th>
                        <th style="padding: 0.85rem 1rem; border-bottom: 2px solid #E2E8F0; text-align: left; font-size: 0.85rem; font-weight: 700; color: #475569; min-width: 240px;">Giáo viên hướng dẫn (Mentor)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($oversight['items'] as $item): ?>
                        <tr style="border-bottom: 1px solid #F1F5F9; transition: background 0.2s ease;">
                            <td style="padding: 1rem; vertical-align: middle;">
                                <div style="font-weight: 700; color: #0F172A; font-size: 0.95rem;"><?= htmlspecialchars((string) $item['studentName']); ?></div>
                            </td>
                            <td style="padding: 1rem; vertical-align: middle; color: #334155; font-size: 0.9rem;">
                                <?= htmlspecialchars((string) $item['postTitle']); ?>
                            </td>
                            <td style="padding: 1rem; vertical-align: middle; color: #334155; font-size: 0.9rem; font-weight: 600;">
                                <?= htmlspecialchars((string) $item['enterpriseName']); ?>
                            </td>
                            <td style="padding: 1rem; vertical-align: middle;">
                                <?php 
                                    $st = (string) $item['status'];
                                    $badgeStyle = 'background: #EFF6FF; color: #1D4ED8; border: 1px solid #BFDBFE;';
                                    if ($st === 'accepted') {
                                        $badgeStyle = 'background: #DCFCE7; color: #15803D; border: 1px solid #BBF7D0;';
                                    } elseif ($st === 'interview') {
                                        $badgeStyle = 'background: #F3E8FF; color: #7E22CE; border: 1px solid #E9D5FF;';
                                    } elseif ($st === 'reviewing') {
                                        $badgeStyle = 'background: #FEF3C7; color: #B45309; border: 1px solid #FDE68A;';
                                    } elseif ($st === 'declined') {
                                        $badgeStyle = 'background: #FEE2E2; color: #B91C1C; border: 1px solid #FECACA;';
                                    }
                                ?>
                                <span style="display: inline-block; padding: 0.3rem 0.65rem; border-radius: 9999px; font-size: 0.8rem; font-weight: 700; <?= $badgeStyle ?>">
                                    ● <?= htmlspecialchars($labels[$st] ?? $st); ?>
                                </span>
                            </td>
                            <td style="padding: 1rem; vertical-align: middle;">
                                <?php if (in_array($item['status'], ['interview', 'accepted'], true)): ?>
                                    <form method="post" class="mentor-assign-form" style="margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                                        <input type="hidden" name="csrfToken" value="<?= htmlspecialchars($session->csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="applicationId" value="<?= htmlspecialchars((string) $item['id']); ?>">
                                        <div style="position: relative; width: 100%;">
                                            <select name="mentorTeacherId" class="school-mentor-select" data-app-id="<?= htmlspecialchars((string) $item['id']); ?>" style="width: 100%; padding: 0.5rem 0.75rem; border: 1.5px solid #CBD5E1; border-radius: 8px; font-size: 0.875rem; font-weight: 600; color: #0F172A; background-color: #FFFFFF; cursor: pointer; transition: all 0.2s ease;">
                                                <option value="">-- Chưa phân công mentor --</option>
                                                <?php foreach ($teachers as $teacher): ?>
                                                    <?php 
                                                        $spec = !empty($teacher['specialization']) ? " - " . $teacher['specialization'] : '';
                                                        $isSelected = ($item['mentorTeacherId'] ?? '') === $teacher['id'];
                                                    ?>
                                                    <option value="<?= htmlspecialchars((string) $teacher['id']); ?>" <?= $isSelected ? 'selected' : ''; ?>>
                                                        <?= htmlspecialchars((string) $teacher['fullName'] . $spec); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </form>
                                <?php else: ?>
                                    <span style="color: #94A3B8; font-size: 0.875rem; font-style: italic;">
                                        <?= htmlspecialchars((string) ($item['mentorName'] ?? 'Chưa thể gán')); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const toastBox = document.getElementById('school-toast-msg');
    
    function showToast(msg, isSuccess = true) {
        if (!toastBox) return;
        toastBox.style.display = 'block';
        toastBox.style.background = isSuccess ? '#DCFCE7' : '#FEE2E2';
        toastBox.style.color = isSuccess ? '#15803D' : '#B91C1C';
        toastBox.style.border = isSuccess ? '1px solid #86EFAC' : '1px solid #FCA5A5';
        toastBox.textContent = msg;
        
        // Hide existing server flash messages
        const sSuccess = document.getElementById('server-flash-success');
        const sError = document.getElementById('server-flash-error');
        if (sSuccess) sSuccess.style.display = 'none';
        if (sError) sError.style.display = 'none';

        setTimeout(() => {
            toastBox.style.display = 'none';
        }, 4000);
    }

    const mentorSelects = document.querySelectorAll('.school-mentor-select');
    mentorSelects.forEach(function(select) {
        select.addEventListener('change', function(e) {
            const form = select.closest('form');
            if (!form) return;

            const formData = new FormData(form);
            formData.append('ajax', '1');

            select.disabled = true;
            select.style.opacity = '0.6';

            fetch('/app/school/internships.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(res => res.json())
            .then(data => {
                select.disabled = false;
                select.style.opacity = '1';
                if (data.success) {
                    showToast(data.message || 'Đã phân công mentor thành công!', true);
                } else {
                    showToast(data.message || 'Lỗi khi phân công mentor.', false);
                }
            })
            .catch(err => {
                select.disabled = false;
                select.style.opacity = '1';
                console.error('Mentor assignment error:', err);
                // Fallback to normal form submit if fetch fails
                form.submit();
            });
        });
    });
});
</script>

<?php
$pageBody = ob_get_clean();
$extraStyles = '';
require __DIR__ . '/includes/layout.php';
