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
$rawTeachers = $service->teachers($userId, 100, 0);

// Filter out generic admin accounts like "Ban Giám hiệu" from teacher list so only real mentors appear
$uniqueTeachers = [];
$seenTeacherNames = [];
foreach ($rawTeachers as $t) {
    $tName = trim((string) ($t['fullName'] ?? ''));
    if ($tName === '' || isset($seenTeacherNames[$tName]) || str_contains($tName, 'Ban Giám hiệu') || str_contains($tName, 'FPT Software')) {
        continue;
    }
    $seenTeacherNames[$tName] = true;
    $uniqueTeachers[] = $t;
}
$teachers = $uniqueTeachers;

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
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.25rem;">
        <div>
            <h3 style="font-size: 1.05rem; font-weight: 700; color: #0F172A; margin: 0;">Danh sách sinh viên & Phân công Mentor</h3>
            <span style="font-size: 0.875rem; color: #64748B;">Tổng số: <?= count($oversight['items']); ?> đơn</span>
        </div>
        <a href="javascript:void(0)" onclick="openGuideModal()" style="display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.4rem 0.85rem; border-radius: 8px; font-size: 0.875rem; font-weight: 600; color: #0369A1; background: #E0F2FE; border: 1px solid #BAE6FD; text-decoration: none; cursor: pointer; transition: background 0.2s;">
            📖 Hướng dẫn quy trình thực tập
        </a>
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
                        <th style="padding: 0.85rem 1rem; border-bottom: 2px solid #E2E8F0; text-align: right; font-size: 0.85rem; font-weight: 700; color: #475569; white-space: nowrap;">Thao tác</th>
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
                                            <select name="mentorTeacherId" class="school-mentor-select typeui-select typeui-select--compact" data-app-id="<?= htmlspecialchars((string) $item['id']); ?>">
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
                            <td style="padding: 1rem; vertical-align: middle; text-align: right; white-space: nowrap;">
                                <div style="display: flex; gap: 0.4rem; justify-content: flex-end;">
                                    <a href="javascript:void(0)" onclick="openInternshipDetail('<?= htmlspecialchars((string) $item['id'], ENT_QUOTES) ?>', '<?= htmlspecialchars((string) $item['studentName'], ENT_QUOTES) ?>', '<?= htmlspecialchars((string) $item['postTitle'], ENT_QUOTES) ?>', '<?= htmlspecialchars((string) $item['enterpriseName'], ENT_QUOTES) ?>', '<?= htmlspecialchars($labels[(string)$item['status']] ?? (string)$item['status'], ENT_QUOTES) ?>', '<?= htmlspecialchars((string) ($item['mentorName'] ?? 'Chưa phân công'), ENT_QUOTES) ?>', '<?= htmlspecialchars(date('d/m/Y', strtotime((string)$item['appliedAt'])), ENT_QUOTES) ?>')" class="btn btn-sm" style="display: inline-flex; align-items: center; padding: 0.35rem 0.75rem; font-weight: 600; font-size: 0.8rem; border-radius: 6px; background: #FFF7ED; color: #EA580C; border: 1px solid #FED7AA; text-decoration: none;">Chi tiết</a>
                                    <a href="javascript:void(0)" onclick="openInternshipUpdate('<?= htmlspecialchars((string) $item['id'], ENT_QUOTES) ?>', '<?= htmlspecialchars((string) $item['studentName'], ENT_QUOTES) ?>', '<?= htmlspecialchars((string) $item['status'], ENT_QUOTES) ?>', '<?= htmlspecialchars((string) ($item['mentorTeacherId'] ?? ''), ENT_QUOTES) ?>')" class="btn btn-sm" style="display: inline-flex; align-items: center; padding: 0.35rem 0.75rem; font-weight: 600; font-size: 0.8rem; border-radius: 6px; background: #FFFFFF; color: #EA580C; border: 1px solid #EA580C; text-decoration: none;">Cập nhật</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Custom Professional Guide Modal -->
<style>
@keyframes modalFadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}
@keyframes modalSlideUp {
    from { opacity: 0; transform: translateY(20px) scale(0.98); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}
.guide-modal-overlay {
    position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px);
    display: none; justify-content: center; align-items: center; z-index: 9999;
    animation: modalFadeIn 0.3s ease; padding: 1rem;
}
.guide-modal-box {
    background: #FFFFFF; border-radius: 16px; width: 100%; max-width: 500px;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    animation: modalSlideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1); overflow: hidden;
}
.guide-modal-header {
    background: linear-gradient(135deg, #0284C7 0%, #0369A1 100%);
    padding: 1.5rem; display: flex; align-items: center; justify-content: space-between;
}
.guide-modal-step {
    display: flex; gap: 1rem; padding: 1.25rem 0; border-bottom: 1px solid #F1F5F9;
}
.guide-modal-step:last-child { border-bottom: none; }
.guide-modal-number {
    width: 32px; height: 32px; border-radius: 50%; background: #E0F2FE; color: #0284C7;
    display: flex; align-items: center; justify-content: center; font-weight: 700; flex-shrink: 0;
}
</style>

<div class="guide-modal-overlay" id="guideModalOverlay" onclick="if(event.target === this) closeGuideModal()">
    <div class="guide-modal-box">
        <div class="guide-modal-header">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <div style="background: rgba(255,255,255,0.2); border-radius: 8px; padding: 0.5rem; display: flex;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path></svg>
                </div>
                <div>
                    <h3 style="margin: 0; color: #FFFFFF; font-size: 1.25rem; font-weight: 700;">Hướng dẫn quy trình</h3>
                    <p style="margin: 0.2rem 0 0 0; color: #BAE6FD; font-size: 0.85rem;">Các bước quản lý thực tập sinh</p>
                </div>
            </div>
            <button type="button" onclick="closeGuideModal()" style="background: transparent; border: none; color: #FFFFFF; font-size: 1.5rem; cursor: pointer; opacity: 0.8; transition: opacity 0.2s; padding: 0;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.8'" aria-label="Đóng">×</button>
        </div>
        <div style="padding: 1.5rem;">
            <div class="guide-modal-step">
                <div class="guide-modal-number">1</div>
                <div>
                    <h4 style="margin: 0 0 0.25rem 0; font-size: 1rem; color: #0F172A; font-weight: 700;">Tiếp nhận & Duyệt hồ sơ</h4>
                    <p style="margin: 0; font-size: 0.875rem; color: #475569; line-height: 1.5;">Sinh viên sẽ nộp CV/hồ sơ vào hệ thống. Nhà trường có nhiệm vụ kiểm tra và cập nhật trạng thái (Phỏng vấn / Đã nhận / Từ chối).</p>
                </div>
            </div>
            <div class="guide-modal-step">
                <div class="guide-modal-number">2</div>
                <div>
                    <h4 style="margin: 0 0 0.25rem 0; font-size: 1rem; color: #0F172A; font-weight: 700;">Phân công Mentor (Giảng viên)</h4>
                    <p style="margin: 0; font-size: 0.875rem; color: #475569; line-height: 1.5;">Mỗi sinh viên khi bắt đầu đi thực tập (trạng thái "Đã nhận") cần được gán cho một Giảng viên hướng dẫn (Mentor) để theo dõi.</p>
                </div>
            </div>
            <div class="guide-modal-step">
                <div class="guide-modal-number">3</div>
                <div>
                    <h4 style="margin: 0 0 0.25rem 0; font-size: 1rem; color: #0F172A; font-weight: 700;">Giám sát & Đánh giá</h4>
                    <p style="margin: 0; font-size: 0.875rem; color: #475569; line-height: 1.5;">Sử dụng nút "Chi tiết" hoặc "Cập nhật" để xem lịch sử tiến độ, số điểm năng lực và nhật ký thực tập của sinh viên.</p>
                </div>
            </div>
        </div>
        <div style="background: #F8FAFC; padding: 1.25rem 1.5rem; border-top: 1px solid #E2E8F0; text-align: right;">
            <button type="button" onclick="closeGuideModal()" class="btn btn-primary" style="background: #0284C7; color: white; border: none; padding: 0.6rem 1.5rem; border-radius: 8px; font-weight: 600; cursor: pointer; transition: background 0.2s;" onmouseover="this.style.background='#0369A1'" onmouseout="this.style.background='#0284C7'">Đã hiểu</button>
        </div>
    </div>
</div>

<!-- Internship Detail Modal -->
<div class="guide-modal-overlay" id="detailModalOverlay" onclick="if(event.target === this) closeInternshipDetail()" style="padding: 2rem 1rem;">
    <div class="guide-modal-box" style="max-width: 800px; max-height: 90vh; display: flex; flex-direction: column;">
        <div class="guide-modal-header" style="background: linear-gradient(135deg, #0F172A 0%, #1E293B 100%); flex-shrink: 0;">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <div style="background: rgba(255,255,255,0.2); border-radius: 8px; padding: 0.5rem; display: flex;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                </div>
                <div>
                    <h3 style="margin: 0; color: #FFFFFF; font-size: 1.25rem; font-weight: 700;">Hồ sơ thực tập sinh</h3>
                    <p style="margin: 0.2rem 0 0 0; color: #CBD5E1; font-size: 0.85rem;" id="detailStudentName">Tên sinh viên</p>
                </div>
            </div>
            <button type="button" onclick="closeInternshipDetail()" style="background: transparent; border: none; color: #FFFFFF; font-size: 1.5rem; cursor: pointer; opacity: 0.8; transition: opacity 0.2s; padding: 0;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.8'" aria-label="Đóng">×</button>
        </div>
        <div style="padding: 1.5rem; overflow-y: auto; flex-grow: 1;">
            <div style="display: grid; grid-template-columns: 2fr 1.2fr; gap: 1.5rem;">
                <!-- Main Info Column -->
                <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                    
                    <!-- 1. Thông tin liên hệ sinh viên -->
                    <div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 12px; padding: 1.25rem; box-shadow: 0 1px 2px rgba(0,0,0,0.02);">
                        <h4 style="margin: 0 0 1rem 0; font-size: 0.95rem; color: #0F172A; font-weight: 700; border-bottom: 1px solid #F1F5F9; padding-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#64748B" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                            Thông tin liên hệ
                        </h4>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div>
                                <div style="font-size: 0.8rem; color: #64748B; margin-bottom: 0.2rem;">Mã sinh viên</div>
                                <div style="font-size: 0.95rem; color: #1E293B; font-weight: 600;">BTEC-AI-2026A</div>
                            </div>
                            <div>
                                <div style="font-size: 0.8rem; color: #64748B; margin-bottom: 0.2rem;">Số điện thoại</div>
                                <div style="font-size: 0.95rem; color: #1E293B; font-weight: 600;">090.123.4567</div>
                            </div>
                            <div style="grid-column: span 2;">
                                <div style="font-size: 0.8rem; color: #64748B; margin-bottom: 0.2rem;">Email</div>
                                <div style="font-size: 0.95rem; color: #1E293B; font-weight: 600;">student.btec@fpt.edu.vn</div>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Thông tin đợt tuyển dụng -->
                    <div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 12px; padding: 1.25rem; box-shadow: 0 1px 2px rgba(0,0,0,0.02);">
                        <h4 style="margin: 0 0 1rem 0; font-size: 0.95rem; color: #0F172A; font-weight: 700; border-bottom: 1px solid #F1F5F9; padding-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#64748B" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                            Thông tin tuyển dụng
                        </h4>
                        <div style="display: grid; gap: 1rem;">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                <div>
                                    <div style="font-size: 0.8rem; color: #64748B; margin-bottom: 0.2rem;">Vị trí ứng tuyển</div>
                                    <div style="font-size: 0.95rem; color: #0284C7; font-weight: 600;" id="detailPostTitle">...</div>
                                </div>
                                <div>
                                    <div style="font-size: 0.8rem; color: #64748B; margin-bottom: 0.2rem;">Doanh nghiệp</div>
                                    <div style="font-size: 0.95rem; color: #1E293B; font-weight: 600;" id="detailEnterprise">...</div>
                                </div>
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; border-top: 1px dashed #E2E8F0; padding-top: 1rem;">
                                <div>
                                    <div style="font-size: 0.8rem; color: #64748B; margin-bottom: 0.2rem;">Ngày nộp đơn</div>
                                    <div style="font-size: 0.95rem; color: #1E293B; font-weight: 600;" id="detailAppliedDate">...</div>
                                </div>
                                <div>
                                    <div style="font-size: 0.8rem; color: #64748B; margin-bottom: 0.2rem;">Mức phụ cấp (dự kiến)</div>
                                    <div style="font-size: 0.95rem; color: #10B981; font-weight: 700;">5.000.000 VNĐ/Tháng</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 3. Tài liệu minh chứng -->
                    <div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 12px; padding: 1.25rem; box-shadow: 0 1px 2px rgba(0,0,0,0.02);">
                        <h4 style="margin: 0 0 1rem 0; font-size: 0.95rem; color: #0F172A; font-weight: 700; border-bottom: 1px solid #F1F5F9; padding-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#64748B" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path></svg>
                            Tài liệu đính kèm (CV / Portfolio)
                        </h4>
                        <div style="display: flex; align-items: center; justify-content: space-between; background: #F8FAFC; border: 1px dashed #CBD5E1; padding: 0.75rem 1rem; border-radius: 8px;">
                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                <div style="background: #FEE2E2; color: #EF4444; padding: 0.4rem; border-radius: 6px;">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><text x="9" y="18" font-size="6" font-family="sans-serif" font-weight="bold">PDF</text></svg>
                                </div>
                                <div>
                                    <div style="font-size: 0.9rem; font-weight: 600; color: #1E293B;">CV_LeQuyTam_AI_Intern.pdf</div>
                                    <div style="font-size: 0.75rem; color: #64748B;">2.4 MB • Tải lên lúc 08:30</div>
                                </div>
                            </div>
                            <div style="display: flex; gap: 0.5rem;">
                                <button type="button" style="background: white; border: 1px solid #E2E8F0; color: #475569; padding: 0.35rem 0.75rem; border-radius: 6px; font-size: 0.8rem; font-weight: 600; cursor: pointer;">Xem trước</button>
                                <button type="button" style="background: #F0F9FF; border: 1px solid #BAE6FD; color: #0369A1; padding: 0.35rem 0.75rem; border-radius: 6px; font-size: 0.8rem; font-weight: 600; cursor: pointer;">Tải xuống</button>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Sidebar Column (Status & Timeline) -->
                <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                    
                    <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 12px; padding: 1.25rem; box-shadow: 0 1px 2px rgba(0,0,0,0.02);">
                        <div style="font-size: 0.8rem; color: #64748B; font-weight: 600; margin-bottom: 0.4rem; text-transform: uppercase;">Trạng thái hiện tại</div>
                        <div style="font-size: 1.1rem; color: #EA580C; font-weight: 800; margin-bottom: 1rem;" id="detailStatus">...</div>
                        
                        <div style="font-size: 0.8rem; color: #64748B; font-weight: 600; margin-bottom: 0.4rem; text-transform: uppercase;">Mentor (GVHD)</div>
                        <div style="font-size: 0.95rem; color: #1E293B; font-weight: 600; background: #FFFFFF; padding: 0.5rem 0.75rem; border: 1px solid #CBD5E1; border-radius: 6px;" id="detailMentor">...</div>
                    </div>

                    <!-- 4. Lịch sử tiến trình (Timeline) -->
                    <div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 12px; padding: 1.25rem; box-shadow: 0 1px 2px rgba(0,0,0,0.02); flex-grow: 1;">
                        <h4 style="margin: 0 0 1.25rem 0; font-size: 0.95rem; color: #0F172A; font-weight: 700; border-bottom: 1px solid #F1F5F9; padding-bottom: 0.5rem;">
                            Nhật ký xét duyệt
                        </h4>
                        
                        <!-- Timeline CSS injected here to keep it contained -->
                        <style>
                            .intern-timeline { position: relative; padding-left: 1.5rem; }
                            .intern-timeline::before { content: ''; position: absolute; left: 0.35rem; top: 0.25rem; bottom: 0; width: 2px; background: #E2E8F0; }
                            .intern-timeline-item { position: relative; margin-bottom: 1.25rem; }
                            .intern-timeline-item:last-child { margin-bottom: 0; }
                            .intern-timeline-dot { position: absolute; left: -1.5rem; top: 0.25rem; width: 0.75rem; height: 0.75rem; border-radius: 50%; background: #CBD5E1; border: 2px solid #FFFFFF; box-shadow: 0 0 0 1px #E2E8F0; }
                            .intern-timeline-dot.active { background: #10B981; box-shadow: 0 0 0 2px #A7F3D0; }
                            .intern-timeline-dot.current { background: #3B82F6; box-shadow: 0 0 0 2px #BFDBFE; }
                            .intern-timeline-date { font-size: 0.75rem; color: #64748B; margin-bottom: 0.1rem; }
                            .intern-timeline-content { font-size: 0.85rem; color: #1E293B; font-weight: 600; line-height: 1.4; }
                        </style>
                        
                        <div class="intern-timeline">
                            <div class="intern-timeline-item">
                                <div class="intern-timeline-dot active"></div>
                                <div class="intern-timeline-date" id="timelineAppliedDate">01/08/2026</div>
                                <div class="intern-timeline-content">Đã nộp đơn ứng tuyển</div>
                            </div>
                            <div class="intern-timeline-item">
                                <div class="intern-timeline-dot active"></div>
                                <div class="intern-timeline-date">05/08/2026</div>
                                <div class="intern-timeline-content">Doanh nghiệp phỏng vấn</div>
                            </div>
                            <div class="intern-timeline-item">
                                <div class="intern-timeline-dot current"></div>
                                <div class="intern-timeline-date">10/08/2026</div>
                                <div class="intern-timeline-content">Đã nhận vào thực tập</div>
                            </div>
                            <div class="intern-timeline-item" style="opacity: 0.5;">
                                <div class="intern-timeline-dot"></div>
                                <div class="intern-timeline-date">Sắp tới</div>
                                <div class="intern-timeline-content">Đánh giá giữa kỳ</div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
        <div style="background: #F8FAFC; padding: 1rem 1.5rem; border-top: 1px solid #E2E8F0; text-align: right; flex-shrink: 0;">
            <button type="button" onclick="closeInternshipDetail()" class="btn btn-primary" style="background: #0F172A; color: white; border: none; padding: 0.6rem 1.75rem; border-radius: 8px; font-weight: 600; cursor: pointer; transition: background 0.2s;" onmouseover="this.style.background='#1E293B'" onmouseout="this.style.background='#0F172A'">Đóng</button>
        </div>
    </div>
</div>

<!-- Internship Update Modal -->
<div class="guide-modal-overlay" id="updateModalOverlay" onclick="if(event.target === this) closeInternshipUpdate()">
    <div class="guide-modal-box">
        <div class="guide-modal-header" style="background: linear-gradient(135deg, #EA580C 0%, #C2410C 100%);">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <div style="background: rgba(255,255,255,0.2); border-radius: 8px; padding: 0.5rem; display: flex;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                </div>
                <div>
                    <h3 style="margin: 0; color: #FFFFFF; font-size: 1.25rem; font-weight: 700;">Cập nhật hồ sơ</h3>
                    <p style="margin: 0.2rem 0 0 0; color: #FFEDD5; font-size: 0.85rem;" id="updateStudentName">Tên sinh viên</p>
                </div>
            </div>
            <button type="button" onclick="closeInternshipUpdate()" style="background: transparent; border: none; color: #FFFFFF; font-size: 1.5rem; cursor: pointer; opacity: 0.8; transition: opacity 0.2s; padding: 0;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.8'" aria-label="Đóng">×</button>
        </div>
        <form method="post" id="updateInternshipForm">
            <input type="hidden" name="csrfToken" value="<?= htmlspecialchars($session->csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="applicationId" id="updateAppId">
            <input type="hidden" name="form_action" value="update_internship_status">
            
            <div style="padding: 1.5rem; display: grid; gap: 1.25rem;">
                <div>
                    <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #334155; margin-bottom: 0.5rem;">Trạng thái đợt thực tập</label>
                    <select name="status" id="updateStatus" class="typeui-select typeui-select--status">
                        <?php foreach ($labels as $key => $label): ?>
                            <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #334155; margin-bottom: 0.5rem;">Giảng viên hướng dẫn (Mentor)</label>
                    <select name="mentorTeacherId" id="updateMentorId" class="typeui-select">
                        <option value="">-- Chưa phân công mentor --</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <?php $spec = !empty($teacher['specialization']) ? " - " . $teacher['specialization'] : ''; ?>
                            <option value="<?= htmlspecialchars((string) $teacher['id']); ?>">
                                <?= htmlspecialchars((string) $teacher['fullName'] . $spec); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div style="background: #F8FAFC; padding: 1.25rem 1.5rem; border-top: 1px solid #E2E8F0; text-align: right; display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" onclick="closeInternshipUpdate()" style="background: #FFFFFF; color: #475569; border: 1px solid #CBD5E1; padding: 0.6rem 1.5rem; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.background='#F1F5F9'" onmouseout="this.style.background='#FFFFFF'">Hủy</button>
                <button type="submit" class="btn btn-primary" style="background: #EA580C; color: white; border: none; padding: 0.6rem 1.5rem; border-radius: 8px; font-weight: 600; cursor: pointer; transition: background 0.2s;" onmouseover="this.style.background='#C2410C'" onmouseout="this.style.background='#EA580C'">Lưu cập nhật</button>
            </div>
        </form>
    </div>
</div>

<script>
function openGuideModal() { document.getElementById('guideModalOverlay').style.display = 'flex'; }
function closeGuideModal() { document.getElementById('guideModalOverlay').style.display = 'none'; }

function openInternshipDetail(id, studentName, postTitle, enterpriseName, status, mentorName, appliedAt) {
    document.getElementById('detailModalOverlay').style.display = 'flex';
    document.getElementById('detailStudentName').textContent = studentName;
    document.getElementById('detailPostTitle').textContent = postTitle;
    document.getElementById('detailEnterprise').textContent = enterpriseName;
    document.getElementById('detailStatus').textContent = status;
    document.getElementById('detailMentor').textContent = mentorName;
    document.getElementById('detailAppliedDate').textContent = appliedAt || '01/08/2026';
    document.getElementById('timelineAppliedDate').textContent = appliedAt || '01/08/2026';
}
function closeInternshipDetail() { document.getElementById('detailModalOverlay').style.display = 'none'; }

function openInternshipUpdate(id, studentName, status, mentorTeacherId) {
    document.getElementById('updateModalOverlay').style.display = 'flex';
    document.getElementById('updateStudentName').textContent = studentName;
    document.getElementById('updateAppId').value = id;
    document.getElementById('updateStatus').value = status || 'submitted';
    document.getElementById('updateMentorId').value = mentorTeacherId || '';
}
function closeInternshipUpdate() { document.getElementById('updateModalOverlay').style.display = 'none'; }

document.addEventListener('keydown', function(e) {
    if(e.key === 'Escape') {
        closeGuideModal();
        closeInternshipDetail();
        closeInternshipUpdate();
    }
});

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
