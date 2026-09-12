<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/bin/bootstrap.php';
require dirname(__DIR__, 2) . '/src/Bootstrap/SchoolAppContext.php';

use TalentHub\Bootstrap\SchoolAppContext;
use TalentHub\Http\ApiException;
use TalentHub\Support\Id\RequestId;

$context = (new SchoolAppContext())->boot();
$service = $context['projects'];
$dashboard = $context['service'];
$session = $context['session'];
$userId = (string) $context['user']['id'];
$error = null;
$flash = null;

if (isset($_GET['ajax']) && $_GET['ajax'] === 'members') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $projId = (string) ($_GET['projectId'] ?? '');
        $members = $service->listProjectMembers($userId, $projId);
        echo json_encode(['success' => true, 'members' => $members]);
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $session->assertCsrf(isset($_POST['csrfToken']) ? (string) $_POST['csrfToken'] : null);
    try {
        $action = (string) ($_POST['action'] ?? 'create');
        if ($action === 'create') {
            $rawFundingGoal = $_POST['fundingGoal'] ?? null;
            $fundingGoal = null;
            if ($rawFundingGoal !== null && trim((string) $rawFundingGoal) !== '') {
                $cleanGoal = preg_replace('/[^\d]/', '', (string) $rawFundingGoal);
                $fundingGoal = $cleanGoal !== '' ? $cleanGoal : null;
            }

            // Kiểm tra phân bổ kinh phí nếu có mục tiêu tài trợ và hạng mục phân bổ
            $budgetAmounts = $_POST['budgetAmounts'] ?? [];
            if (is_array($budgetAmounts) && !empty($budgetAmounts) && $fundingGoal !== null && (float) $fundingGoal > 0) {
                $totalBudget = 0.0;
                $hasBudget = false;
                foreach ($budgetAmounts as $amt) {
                    $cleanAmt = preg_replace('/[^\d]/', '', (string) $amt);
                    if ($cleanAmt !== '') {
                        $totalBudget += (float) $cleanAmt;
                        $hasBudget = true;
                    }
                }
                if ($hasBudget && abs($totalBudget - (float) $fundingGoal) > 0.01) {
                    throw new ApiException(
                        422,
                        'VALIDATION_FAILED',
                        'Mục tiêu tài trợ (' . number_format((float) $fundingGoal, 0, ',', '.') . ' VNĐ) phải bằng Tổng ngân sách phân bổ (' . number_format($totalBudget, 0, ',', '.') . ' VNĐ).'
                    );
                }
            }

            $service->createProject($userId, [
                'title'           => $_POST['title'] ?? '',
                'category'        => $_POST['category'] ?? 'general',
                'topic'           => $_POST['topic'] ?? '',
                'mentorTeacherId' => $_POST['mentorTeacherId'] ?? null,
                'authorIds'       => $_POST['authorIds'] ?? [],
                'description'     => $_POST['description'] ?? '',
                'fundingGoal'     => $fundingGoal,
                'startAt'         => $_POST['startAt'] ?? null,
                'endAt'           => $_POST['endAt'] ?? null,
                'status'          => $_POST['status'] ?? 'draft',
            ], RequestId::generate());
            $flash = 'Đã tạo dự án mới thành công.';
        } elseif ($action === 'status') {
            $service->updateProject($userId, (string) ($_POST['projectId'] ?? ''), ['status' => $_POST['status'] ?? 'draft']);
            $flash = 'Đã cập nhật trạng thái dự án.';
        } elseif ($action === 'approve_member') {
            $projectId = (string) ($_POST['projectId'] ?? '');
            $studentId = (string) ($_POST['studentId'] ?? '');
            $service->updateMemberStatus($userId, $projectId, $studentId, 'active');
            $flash = 'Đã phê duyệt sinh viên tham gia dự án thành công.';
            if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => true, 'message' => $flash]);
                exit;
            }
        } elseif ($action === 'reject_member') {
            $projectId = (string) ($_POST['projectId'] ?? '');
            $studentId = (string) ($_POST['studentId'] ?? '');
            $service->updateMemberStatus($userId, $projectId, $studentId, 'rejected');
            $flash = 'Đã từ chối đơn đăng ký tham gia dự án của sinh viên.';
            if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => true, 'message' => $flash]);
                exit;
            }
        }
    } catch (ApiException $exception) {
        $error = $exception->getMessage();
        if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
            http_response_code($exception->status);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => $error]);
            exit;
        }
    } catch (Throwable $exception) {
        $error = 'Không thể cập nhật dự án: ' . $exception->getMessage();
        if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => $error]);
            exit;
        }
    }
}

$projects = $service->listProjects($userId)['items'];
$teachers = $dashboard->teachers($userId, 100, 0);
$students = $dashboard->students($userId, 1000, 0); // Lấy danh sách sinh viên

$schoolInfo = [
    'name' => $context['school']['name'],
    'logo_initials' => mb_substr($context['school']['name'], 0, 2),
    'level' => $context['school']['level'] ?? '',
    'district' => $context['school']['address'] ?? '',
    'academic_year' => $context['school']['academicYear'] ?? ''
];

$currentRoute = '/app/school/projects.php';
$pageTitle = 'Quản lý Dự án';
$statusLabels = [
    'draft' => 'Bản nháp',
    'in_progress' => 'Đang thực hiện',
    'completed' => 'Đã hoàn thành',
    'archived' => 'Lưu trữ'
];

ob_start();
?>
<?php 
$pageDescription = 'Tạo dự án khởi nghiệp/nghiên cứu khoa học, phân công giảng viên hướng dẫn, nhóm tác giả và kêu gọi tài trợ từ doanh nghiệp.';
include __DIR__ . '/includes/page-banner.php'; 
?>

<?php if ($flash): ?>
    <div class="school-flash school-flash--success">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
        <?= htmlspecialchars($flash); ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="school-flash school-flash--error">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
        <?= htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<div class="school-projects-layout">
    <!-- Form Tạo Dự án (UI mới, trực quan hơn) -->
    <section class="school-section-box form-section">
        <div class="school-section-box__header">
            <h2 class="school-section-box__title">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                Tạo Dự Án Mới
            </h2>
        </div>
        
        <form method="post" class="project-form">
            <input type="hidden" name="csrfToken" value="<?= htmlspecialchars($session->csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="action" value="create">
            
            <div class="school-form-group">
                <h3 class="school-form-section-title">1. Thông tin chung</h3>
                <div class="school-form__grid">
                    <label class="school-form__field school-form__field--full">
                        <span>Tên dự án <span style="color:red">*</span></span>
                        <input name="title" maxlength="255" placeholder="Nhập tên dự án sáng tạo / nghiên cứu..." required>
                    </label>
                    <label class="school-form__field">
                        <span>Lĩnh vực / Danh mục</span>
                        <select name="category" class="typeui-select">
                            <option value="Công nghệ thông tin">Công nghệ thông tin</option>
                            <option value="Kinh tế - Quản trị">Kinh tế - Quản trị</option>
                            <option value="Kỹ thuật - Robot">Kỹ thuật - Robot</option>
                            <option value="Nông nghiệp công nghệ cao">Nông nghiệp công nghệ cao</option>
                            <option value="Nghệ thuật & Thiết kế">Nghệ thuật & Thiết kế</option>
                            <option value="general" selected>Khác (General)</option>
                        </select>
                    </label>
                    <label class="school-form__field">
                        <span>Đề tài cụ thể</span>
                        <input name="topic" maxlength="255" placeholder="Ví dụ: Ứng dụng AI vào nông nghiệp...">
                    </label>
                </div>
            </div>

            <div class="school-form-group">
                <h3 class="school-form-section-title">2. Nhân sự tham gia</h3>
                <div class="school-form__grid">
                    <label class="school-form__field">
                        <span>Giảng viên hướng dẫn</span>
                        <select name="mentorTeacherId" class="school-select-enhanced typeui-select">
                            <option value="">-- Chọn giảng viên --</option>
                            <?php foreach ($teachers as $teacher): ?>
                                <option value="<?= htmlspecialchars((string) $teacher['id']); ?>">
                                    <?= htmlspecialchars((string) $teacher['fullName']); ?> 
                                    <?= isset($teacher['email']) ? '('.htmlspecialchars($teacher['email']).')' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="school-form__field">
                        <span>Nhóm tác giả (Sinh viên)</span>
                        <div class="school-author-box">
                            <div class="school-author-search">
                                <input type="text" id="authorSearch" placeholder="Tìm kiếm sinh viên..." onkeyup="filterAuthors()">
                            </div>
                            <div class="school-author-list" id="authorList">
                                <?php foreach ($students as $student): ?>
                                    <label class="school-author-item">
                                        <input type="checkbox" name="authorIds[]" value="<?= htmlspecialchars((string) $student['id']); ?>">
                                        <div class="school-author-info">
                                            <span class="school-author-name"><?= htmlspecialchars((string) $student['fullName']); ?></span>
                                            <span class="school-author-class"><?= htmlspecialchars((string) ($student['className'] ?? '')); ?></span>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </label>
                </div>
            </div>

            <div class="school-form-group">
                <h3 class="school-form-section-title">3. Triển khai & Kêu gọi Tài trợ</h3>
                <div class="school-form__grid">
                    <label class="school-form__field">
                        <span>Mục tiêu tài trợ (VND)</span>
                        <div class="school-input-icon">
                            <span class="school-input-icon__prefix">₫</span>
                            <input name="fundingGoal" id="fundingGoalInput" type="text" inputmode="numeric" autocomplete="off" placeholder="Nhập mục tiêu tài trợ (VD: 10.000.000)" value="<?= htmlspecialchars((string) ($_POST['fundingGoal'] ?? '')) ?>" oninput="handleCurrencyInput(this, event)">
                        </div>
                    </label>
                    <label class="school-form__field">
                        <span>Trạng thái dự án</span>
                        <select name="status" class="typeui-select typeui-select--status">
                            <?php foreach ($statusLabels as $value => $label): ?>
                                <option value="<?= $value; ?>"><?= htmlspecialchars($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="school-form__field">
                        <span>Ngày bắt đầu</span>
                        <input name="startAt" type="date">
                    </label>
                    <label class="school-form__field">
                        <span>Ngày kết thúc dự kiến</span>
                        <input name="endAt" type="date">
                    </label>
                    <label class="school-form__field school-form__field--full">
                        <span>Mô tả dự án & Tầm nhìn</span>
                        <textarea name="description" rows="5" maxlength="5000" placeholder="Trình bày ngắn gọn về mục tiêu, giải pháp và giá trị mang lại của dự án..."></textarea>
                    </label>
                </div>
            </div>

            <div class="school-form-group">
                <h3 class="school-form-section-title">4. Lộ trình thực hiện & Nghiệm thu</h3>
                <div id="milestonesContainer" style="display: flex; flex-direction: column; gap: 1rem;">
                    <div class="milestone-row" style="display: flex; gap: 1rem; align-items: flex-end;">
                        <label class="school-form__field" style="flex: 2; margin: 0;">
                            <span>Tên giai đoạn</span>
                            <input type="text" name="milestoneNames[]" required placeholder="VD: Nghiên cứu lý thuyết">
                        </label>
                        <label class="school-form__field" style="flex: 1; margin: 0;">
                            <span>Deadline</span>
                            <input type="date" name="milestoneDeadlines[]" required>
                        </label>
                        <button type="button" class="btn btn-outline" style="padding: 0.5rem; color: #EF4444; border-color: #EF4444; flex-shrink: 0;" onclick="this.parentElement.remove()" title="Xóa giai đoạn">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                        </button>
                    </div>
                </div>
                <div style="margin-top: 1rem;">
                    <button type="button" class="btn btn-outline btn-sm" onclick="addMilestone()">
                        + Thêm giai đoạn
                    </button>
                </div>
            </div>
            
            <div class="school-form-group">
                <h3 class="school-form-section-title" style="margin-bottom: 0.25rem;">5. Kế hoạch phân bổ kinh phí</h3>
                <p style="font-size: 0.85rem; color: #64748B; margin: 0 0 1.25rem 0;">* Lưu ý: Phân bổ theo các nhóm chi phí lớn. Tổng tỉ lệ các hạng mục phải bằng 100%.</p>
                
                <label class="school-form__field school-form__field--full" style="margin-bottom: 1rem;">
                    <span>Tên nhà trường (Đơn vị nhận tài trợ)</span>
                    <input type="text" value="<?= htmlspecialchars((string) $schoolInfo['name']) ?>" disabled style="background-color: #F1F5F9; color: #64748B; cursor: not-allowed; opacity: 1;">
                </label>
                
                <div id="budgetsContainer" style="display: flex; flex-direction: column; gap: 1rem;">
                    <div class="budget-row" style="display: flex; gap: 1rem; align-items: flex-end;">
                        <label class="school-form__field" style="flex: 1.5; margin: 0;">
                            <span>Nhóm hạng mục chi tiêu</span>
                            <input type="text" name="budgetPurposes[]" required placeholder="VD: Trang thiết bị...">
                        </label>
                        <label class="school-form__field" style="flex: 1.5; margin: 0;">
                            <span>Thành tiền (VNĐ)</span>
                            <input type="text" inputmode="numeric" class="budget-amount-input" name="budgetAmounts[]" required autocomplete="off" placeholder="VD: 5.000.000" oninput="handleCurrencyInput(this, event)">
                        </label>
                        <label class="school-form__field" style="flex: 1; margin: 0;">
                            <span>Tỉ lệ (%)</span>
                            <input type="text" class="budget-pct-display" disabled style="background-color: #F1F5F9; color: #10B981; font-weight: bold; cursor: not-allowed; opacity: 1;" placeholder="0%">
                        </label>
                        <button type="button" class="btn btn-outline" style="padding: 0.5rem; color: #EF4444; border-color: #EF4444; flex-shrink: 0;" onclick="this.parentElement.remove(); calculateBudgets();" title="Xóa hạng mục">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                        </button>
                    </div>
                </div>
                
                <div id="budgetSummaryBar" style="margin-top: 1rem; padding: 0.75rem 1rem; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; text-align: right; font-weight: 600;">
                    <span id="budgetSummaryText" style="color: #64748B;">Tổng phân bổ: 0% / 100% (0 VNĐ)</span>
                </div>
                
                <div style="margin-top: 1rem;">
                    <button type="button" class="btn btn-outline btn-sm" onclick="addBudgetItem()">
                        + Thêm hạng mục
                    </button>
                </div>
            </div>

            <div class="school-form-actions-bar">
                <button class="btn btn-primary school-btn-lg" type="submit">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg>
                    Tạo Dự Án
                </button>
            </div>
        </form>
    </section>

    <!-- Danh Sách Dự án -->
    <section class="school-section-box list-section">
        <div class="school-section-box__header school-section-box__header--bordered">
            <h2 class="school-section-box__title">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                Danh sách Dự án
            </h2>
            <span class="school-badge school-badge--info"><?= count($projects) ?> dự án</span>
        </div>
        
        <?php if ($projects === []): ?>
            <div class="school-empty-state">
                <div class="school-empty-state__icon">📁</div>
                <p>Nhà trường chưa khởi tạo dự án nào.</p>
                <small>Các dự án sau khi tạo sẽ hiển thị tại đây để theo dõi tiến độ.</small>
            </div>
        <?php else: ?>
            <div class="school-project-cards">
                <?php foreach ($projects as $project): ?>
                    <div class="school-project-card">
                        <div class="school-project-card__header">
                            <div class="school-project-title-area">
                                <h3><?= htmlspecialchars((string) $project['title']); ?></h3>
                                <span class="school-project-topic"><?= htmlspecialchars((string) ($project['topic'] ?? $project['category'] ?? 'General')); ?></span>
                            </div>
                            <div class="school-project-status-badge school-project-status-badge--<?= $project['status'] ?>">
                                <?= htmlspecialchars($statusLabels[$project['status']] ?? $project['status']); ?>
                            </div>
                        </div>
                        
                        <div class="school-project-card__stats">
                            <div class="school-project-card__stat">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                                <span><?= (int) ($project['membersCount'] ?? 0); ?> thành viên</span>
                                <?php if (!empty($project['pendingMembersCount']) && (int) $project['pendingMembersCount'] > 0): ?>
                                    <span class="school-badge" style="background:#FEF3C7; color:#B45309; border:1px solid #FCD34D; font-size:0.75rem; font-weight:700; padding:2px 8px; border-radius:12px; margin-left:6px;">
                                        ⏳ <?= (int) $project['pendingMembersCount']; ?> chờ duyệt
                                    </span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($project['fundingGoal']) && $project['fundingGoal'] > 0): ?>
                            <div class="school-project-card__stat school-project-card__stat--funding">
                                <span style="font-weight: 700; font-size: 0.85rem; padding: 0.1rem 0.3rem; background: #D1FAE5; color: #047857; border-radius: 4px; margin-right: 0.25rem;">VNĐ</span>
                                <span>
                                    <strong><?= number_format((float) ($project['raisedAmount'] ?? 0), 0, ',', '.'); ?></strong> / 
                                    <?= number_format((float) ($project['fundingGoal'] ?? 0), 0, ',', '.'); ?>
                                    <small>(<?= (int) ($project['sponsorsCount'] ?? 0); ?> nhà tài trợ)</small>
                                </span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="school-project-card__actions" style="display:flex; flex-direction:column; gap:0.5rem; width:100%;">
                            <button type="button" class="btn btn-outline btn-sm btn-manage-members"
                                data-project-id="<?= htmlspecialchars((string)$project['id'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-project-title="<?= htmlspecialchars((string)$project['title'], ENT_QUOTES, 'UTF-8'); ?>"
                                onclick="openManageMembersModal(this.dataset.projectId, this.dataset.projectTitle)"
                                style="width:100%; display:flex; align-items:center; justify-content:center; gap:6px; font-weight:600; font-size:0.825rem; padding:6px 12px; border-color:#EA580C; color:#EA580C; cursor:pointer;">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                                Thành viên &amp; Đơn đăng ký
                                <?php if (!empty($project['pendingMembersCount']) && (int) $project['pendingMembersCount'] > 0): ?>
                                    <span style="background:#EA580C; color:#fff; border-radius:10px; padding:1px 6px; font-size:0.75rem; font-weight:700;"><?= (int) $project['pendingMembersCount']; ?></span>
                                <?php endif; ?>
                            </button>
                            <form method="post" style="display:flex; align-items:center; gap:0.5rem; width:100%;">
                                <input type="hidden" name="csrfToken" value="<?= htmlspecialchars($session->csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="action" value="status">
                                <input type="hidden" name="projectId" value="<?= htmlspecialchars((string) $project['id']); ?>">
                                <label style="font-size:0.8rem; color:var(--text-secondary); white-space:nowrap;">Trạng thái:</label>
                                <select name="status" class="school-status-select typeui-select typeui-select--compact typeui-select--status" onchange="this.form.submit()">
                                    <?php foreach ($statusLabels as $value => $label): ?>
                                        <option value="<?= $value; ?>" <?= $project['status'] === $value ? 'selected' : ''; ?>>
                                            <?= htmlspecialchars($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<!-- Modal Quản lý Thành viên & Duyệt Đơn (đặt ngoài grid container) -->
<div id="manageMembersModal" class="school-modal-backdrop" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.6); z-index:99999; opacity:1; backdrop-filter:blur(4px); align-items:center; justify-content:center; padding:1rem;">
    <div class="school-modal-card" style="background:#fff; border-radius:16px; width:100%; max-width:760px; max-height:90vh; display:flex; flex-direction:column; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); overflow:hidden;">
        <div class="school-modal-header" style="padding:1.25rem 1.5rem; border-bottom:1px solid #E2E8F0; display:flex; align-items:center; justify-content:space-between;">
            <div>
                <h3 id="modalProjectTitle" style="margin:0; font-size:1.15rem; font-weight:700; color:#0F172A; display:flex; align-items:center; gap:8px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#EA580C" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                    Quản lý thành viên &amp; Phê duyệt đăng ký
                </h3>
                <p id="modalProjectSubtitle" style="margin:4px 0 0; font-size:0.85rem; color:#64748B;">Dự án: <span id="modalProjectName" style="font-weight:600; color:#1E293B;">...</span></p>
            </div>
            <button type="button" onclick="closeManageMembersModal()" style="background:none; border:none; color:#94A3B8; cursor:pointer; padding:6px; border-radius:8px; display:flex; align-items:center; justify-content:center;">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
        </div>

        <div id="modalMembersContent" style="padding:1.5rem; overflow-y:auto; flex:1;">
            <div id="modalLoading" style="text-align:center; padding:2rem; color:#64748B;">
                <div class="school-spinner" style="display:inline-block; width:28px; height:28px; border:3px solid #E2E8F0; border-top-color:#EA580C; border-radius:50%; animation:spin 0.8s linear infinite;"></div>
                <p style="margin-top:0.75rem; font-size:0.9rem;">Đang tải danh sách thành viên...</p>
            </div>
            <div id="modalEmpty" style="display:none; text-align:center; padding:3rem 1rem; color:#64748B;">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#CBD5E1" stroke-width="1.5" style="margin-bottom:0.5rem;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                <p style="font-size:0.95rem; font-weight:600; color:#334155; margin:0;">Chưa có thành viên hoặc đơn đăng ký nào</p>
                <small style="color:#94A3B8;">Khi sinh viên đăng ký tham gia, thông tin sẽ xuất hiện tại đây.</small>
            </div>
            <div id="modalTableWrapper" style="display:none;">
                <table style="width:100%; border-collapse:collapse; font-size:0.875rem;">
                    <thead>
                        <tr style="border-bottom:2px solid #E2E8F0; text-align:left; color:#64748B; font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em;">
                            <th style="padding:0.75rem 0.5rem;">Sinh viên</th>
                            <th style="padding:0.75rem 0.5rem;">Lớp</th>
                            <th style="padding:0.75rem 0.5rem;">Ngày gửi đơn</th>
                            <th style="padding:0.75rem 0.5rem;">Trạng thái</th>
                            <th style="padding:0.75rem 0.5rem; text-align:right;">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody id="modalMembersList">
                    </tbody>
                </table>
            </div>
        </div>

        <div style="padding:1rem 1.5rem; background:#F8FAFC; border-top:1px solid #E2E8F0; display:flex; justify-content:flex-end;">
            <button type="button" class="btn btn-outline" onclick="closeManageMembersModal()">Đóng</button>
        </div>
    </div>
</div>

<?php
$pageBody = ob_get_clean();

$extraStyles = ''; // Component styles extracted to assets/css/school.css

$extraScripts = <<<'HTML'
<script>
function filterAuthors() {
    const input = document.getElementById('authorSearch');
    const filter = input.value.toLowerCase();
    const nodes = document.querySelectorAll('.school-author-item');

    nodes.forEach(node => {
        const name = node.querySelector('.school-author-name').innerText.toLowerCase();
        const className = node.querySelector('.school-author-class').innerText.toLowerCase();
        
        if (name.includes(filter) || className.includes(filter)) {
            node.style.display = 'flex';
        } else {
            node.style.display = 'none';
        }
    });
}

function addMilestone() {
    const container = document.getElementById('milestonesContainer');
    const row = document.createElement('div');
    row.className = 'milestone-row';
    row.style = 'display: flex; gap: 1rem; align-items: flex-end;';
    row.innerHTML = `
        <label class="school-form__field" style="flex: 2; margin: 0;">
            <span>Tên giai đoạn</span>
            <input type="text" name="milestoneNames[]" required placeholder="VD: Bảo vệ nguyên mẫu">
        </label>
        <label class="school-form__field" style="flex: 1; margin: 0;">
            <span>Deadline</span>
            <input type="date" name="milestoneDeadlines[]" required>
        </label>
        <button type="button" class="btn btn-outline" style="padding: 0.5rem; color: #EF4444; border-color: #EF4444; flex-shrink: 0;" onclick="this.parentElement.remove()" title="Xóa giai đoạn">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
        </button>
    `;
    container.appendChild(row);
}

function formatVND(val) {
    if (val === null || val === undefined || val === '') return '';
    const clean = val.toString().replace(/\D/g, '');
    if (!clean) return '';
    return clean.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

function parseVND(val) {
    if (val === null || val === undefined || val === '') return 0;
    const clean = val.toString().replace(/\D/g, '');
    return clean ? parseInt(clean, 10) : 0;
}

function handleCurrencyInput(input, event) {
    if (event && event.isComposing) {
        return;
    }

    const val = input.value;
    const rawDigits = val.replace(/\D/g, '');
    
    if (!rawDigits) {
        if (input.value !== '') {
            input.value = '';
        }
        calculateBudgets();
        return;
    }
    
    const formatted = formatVND(rawDigits);
    
    // Nếu giá trị hiện tại đã đúng định dạng thì tuyệt đối không gán lại input.value
    // Tránh xung đột với bộ gõ tiếng Việt (Unikey/EVKey) làm chèn lặp số (như gõ 2 thành 22)
    if (val === formatted) {
        calculateBudgets();
        return;
    }
    
    // Tính số chữ số nằm trước vị trí con trỏ hiện tại
    const cursorPos = input.selectionStart || 0;
    const textBeforeCursor = val.slice(0, cursorPos);
    const digitsBeforeCursor = textBeforeCursor.replace(/\D/g, '').length;
    
    input.value = formatted;
    
    // Đặt lại con trỏ tương ứng với số chữ số đã gõ
    if (input.setSelectionRange) {
        let newPos = 0;
        let digitCount = 0;
        for (let i = 0; i < formatted.length; i++) {
            if (/\d/.test(formatted[i])) {
                digitCount++;
            }
            if (digitCount === digitsBeforeCursor) {
                newPos = i + 1;
                break;
            }
        }
        if (digitsBeforeCursor === 0) {
            newPos = 0;
        } else if (digitCount < digitsBeforeCursor) {
            newPos = formatted.length;
        }
        input.setSelectionRange(newPos, newPos);
    }
    
    calculateBudgets();
}

function addBudgetItem() {
    const container = document.getElementById('budgetsContainer');
    const row = document.createElement('div');
    row.className = 'budget-row';
    row.style = 'display: flex; gap: 1rem; align-items: flex-end;';
    row.innerHTML = `
        <label class="school-form__field" style="flex: 1.5; margin: 0;">
            <span>Nhóm hạng mục chi tiêu</span>
            <input type="text" name="budgetPurposes[]" required placeholder="VD: Trang thiết bị...">
        </label>
        <label class="school-form__field" style="flex: 1.5; margin: 0;">
            <span>Thành tiền (VNĐ)</span>
            <input type="text" inputmode="numeric" class="budget-amount-input" name="budgetAmounts[]" required autocomplete="off" placeholder="VD: 5.000.000" oninput="handleCurrencyInput(this, event)">
        </label>
        <label class="school-form__field" style="flex: 1; margin: 0;">
            <span>Tỉ lệ (%)</span>
            <input type="text" class="budget-pct-display" disabled style="background-color: #F1F5F9; color: #10B981; font-weight: bold; cursor: not-allowed; opacity: 1;" placeholder="0%">
        </label>
        <button type="button" class="btn btn-outline" style="padding: 0.5rem; color: #EF4444; border-color: #EF4444; flex-shrink: 0;" onclick="this.parentElement.remove(); calculateBudgets();" title="Xóa hạng mục">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
        </button>
    `;
    container.appendChild(row);
    calculateBudgets();
}

function calculateBudgets() {
    const goalInput = document.getElementById('fundingGoalInput');
    const totalGoal = parseVND(goalInput ? goalInput.value : '');
    
    const amtInputs = document.querySelectorAll('.budget-amount-input');
    const pctDisplays = document.querySelectorAll('.budget-pct-display');
    
    let totalAmt = 0;
    
    amtInputs.forEach((input, index) => {
        const amt = parseVND(input.value);
        totalAmt += amt;
        
        if (pctDisplays[index]) {
            let pct = 0;
            if (totalGoal > 0) {
                pct = (amt / totalGoal) * 100;
            }
            pctDisplays[index].value = pct.toFixed(2) + '%';
        }
    });
    
    const summaryText = document.getElementById('budgetSummaryText');
    if (!summaryText) return;
    
    const formattedTotalAmt = formatVND(totalAmt) || '0';
    const formattedTotalGoal = formatVND(totalGoal) || '0';
    
    if (totalAmt === 0 && totalGoal === 0) {
        summaryText.innerHTML = `Tổng phân bổ: 0% / 100% (0 VNĐ)`;
        summaryText.style.color = '#64748B';
    } else if (totalAmt === 0) {
        summaryText.innerHTML = `Tổng phân bổ: 0 / ${formattedTotalGoal} VNĐ (0%)`;
        summaryText.style.color = '#64748B';
    } else if (totalAmt < totalGoal) {
        const diff = formatVND(totalGoal - totalAmt);
        const pct = totalGoal > 0 ? ((totalAmt / totalGoal) * 100).toFixed(1) : '0';
        summaryText.innerHTML = `Tổng phân bổ: ${formattedTotalAmt} / ${formattedTotalGoal} VNĐ (${pct}%) - <span style="color: #F59E0B; font-weight: 700;">Còn thiếu ${diff} VNĐ</span>`;
        summaryText.style.color = '#F59E0B';
    } else if (totalAmt === totalGoal && totalGoal > 0) {
        summaryText.innerHTML = `Tổng phân bổ: ${formattedTotalAmt} / ${formattedTotalGoal} VNĐ (100%) - <span style="color: #10B981; font-weight: 700;">✓ Đã phân bổ đủ ngân sách</span>`;
        summaryText.style.color = '#10B981';
    } else {
        const diff = formatVND(totalAmt - totalGoal);
        const pct = totalGoal > 0 ? ((totalAmt / totalGoal) * 100).toFixed(1) : '100';
        summaryText.innerHTML = `Tổng phân bổ: ${formattedTotalAmt} / ${formattedTotalGoal} VNĐ (${pct}%) - <span style="color: #EF4444; font-weight: 700;">Vượt quá ${diff} VNĐ</span>`;
        summaryText.style.color = '#EF4444';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    // Tự động định dạng lại số tiền nếu form có dữ liệu sẵn
    const goalInput = document.getElementById('fundingGoalInput');
    if (goalInput && goalInput.value) {
        goalInput.value = formatVND(goalInput.value);
    }
    document.querySelectorAll('.budget-amount-input').forEach(input => {
        if (input.value) {
            input.value = formatVND(input.value);
        }
    });

    calculateBudgets();

    // Lắng nghe sự kiện kết thúc composition của bộ gõ (IME)
    document.addEventListener('compositionend', (e) => {
        if (e.target && (e.target.id === 'fundingGoalInput' || e.target.classList.contains('budget-amount-input'))) {
            handleCurrencyInput(e.target, e);
        }
    });

    // Tự động định dạng lại khi blur khỏi ô nhập
    document.addEventListener('blur', (e) => {
        if (e.target && (e.target.id === 'fundingGoalInput' || e.target.classList.contains('budget-amount-input'))) {
            handleCurrencyInput(e.target, e);
        }
    }, true);

    // Kiểm tra tính hợp lệ trước khi submit form
    const form = document.querySelector('.project-form');
    if (form) {
        form.addEventListener('submit', (e) => {
            const currentGoal = parseVND(goalInput ? goalInput.value : '');
            const amtInputs = document.querySelectorAll('.budget-amount-input');
            let currentTotalAmt = 0;
            let hasBudgetItems = false;
            
            amtInputs.forEach(input => {
                const amt = parseVND(input.value);
                if (amt > 0) hasBudgetItems = true;
                currentTotalAmt += amt;
            });
            
            if (currentGoal > 0 && hasBudgetItems && currentTotalAmt !== currentGoal) {
                e.preventDefault();
                alert(`Tổng phân bổ ngân sách (${formatVND(currentTotalAmt)} VNĐ) phải bằng đúng Mục tiêu tài trợ (${formatVND(currentGoal)} VNĐ). Vui lòng kiểm tra lại!`);
                return false;
            }
        });
    }
});

let currentModalProjectId = null;

function openManageMembersModal(projectId, projectTitle) {
    if (!projectId) return;
    currentModalProjectId = projectId;
    const nameEl = document.getElementById('modalProjectName');
    if (nameEl) nameEl.textContent = projectTitle || '...';
    const modal = document.getElementById('manageMembersModal');
    if (modal) {
        modal.style.display = 'flex';
        modal.style.opacity = '1';
        modal.style.visibility = 'visible';
    }
    loadProjectMembers(projectId);
}

function closeManageMembersModal() {
    const modal = document.getElementById('manageMembersModal');
    if (modal) {
        modal.style.display = 'none';
        modal.style.opacity = '0';
    }
    currentModalProjectId = null;
}

async function loadProjectMembers(projectId) {
    const loading = document.getElementById('modalLoading');
    const empty = document.getElementById('modalEmpty');
    const wrapper = document.getElementById('modalTableWrapper');
    const tbody = document.getElementById('modalMembersList');

    if (!loading || !empty || !wrapper || !tbody) return;

    loading.style.display = 'block';
    empty.style.display = 'none';
    wrapper.style.display = 'none';
    tbody.innerHTML = '';

    try {
        const response = await fetch(`projects.php?ajax=members&projectId=${encodeURIComponent(projectId)}`);
        const data = await response.json();
        loading.style.display = 'none';

        if (!data.success || !data.members || data.members.length === 0) {
            empty.style.display = 'block';
            return;
        }

        tbody.innerHTML = '';
        wrapper.style.display = 'block';
        data.members.forEach(member => {
            const tr = document.createElement('tr');
            tr.style = 'border-bottom:1px solid #F1F5F9;';

            let statusBadge = '';
            if (member.status === 'pending') {
                statusBadge = '<span class="status-badge-pending" style="background:#FEF3C7; color:#92400E; border:1px solid #FCD34D; font-size:0.75rem; font-weight:700; padding:2px 8px; border-radius:12px; display:inline-flex; align-items:center; gap:3px;">⏳ Chờ duyệt</span>';
            } else if (member.status === 'active') {
                statusBadge = '<span class="status-badge-active" style="background:#D1FAE5; color:#065F46; border:1px solid #A7F3D0; font-size:0.75rem; font-weight:700; padding:2px 8px; border-radius:12px; display:inline-flex; align-items:center; gap:3px;">✓ Đang tham gia</span>';
            } else if (member.status === 'rejected') {
                statusBadge = '<span class="status-badge-rejected" style="background:#FEE2E2; color:#991B1B; border:1px solid #FECACA; font-size:0.75rem; font-weight:700; padding:2px 8px; border-radius:12px; display:inline-flex; align-items:center; gap:3px;">✕ Đã từ chối</span>';
            } else {
                statusBadge = `<span style="color:#64748B; font-size:0.75rem;">${escapeHtml(member.status)}</span>`;
            }

            const dateStr = member.createdAt ? new Date(member.createdAt).toLocaleDateString('vi-VN') : '—';

            let actionButtons = '';
            if (member.status === 'pending') {
                actionButtons = `
                    <div style="display:flex; justify-content:flex-end; gap:6px;">
                        <button type="button" class="btn btn-sm btn-approve-member" style="background:#10B981; color:#fff; border:none; padding:4px 10px; font-size:0.775rem; font-weight:600; border-radius:6px; cursor:pointer;" onclick="submitMemberStatus('${projectId}', '${member.studentId}', 'approve_member')">
                            ✓ Chấp thuận
                        </button>
                        <button type="button" class="btn btn-sm btn-reject-member" style="background:#EF4444; color:#fff; border:none; padding:4px 10px; font-size:0.775rem; font-weight:600; border-radius:6px; cursor:pointer;" onclick="submitMemberStatus('${projectId}', '${member.studentId}', 'reject_member')">
                            ✕ Từ chối
                        </button>
                    </div>
                `;
            } else if (member.status === 'active') {
                actionButtons = `
                    <div style="display:flex; justify-content:flex-end; gap:6px;">
                        <button type="button" class="btn btn-sm" style="background:none; border:1px solid #EF4444; color:#EF4444; padding:3px 8px; font-size:0.75rem; border-radius:6px; cursor:pointer;" onclick="submitMemberStatus('${projectId}', '${member.studentId}', 'reject_member')">
                            Gỡ khỏi dự án
                        </button>
                    </div>
                `;
            } else {
                actionButtons = `
                    <div style="display:flex; justify-content:flex-end;">
                        <button type="button" class="btn btn-sm" style="background:none; border:1px solid #10B981; color:#10B981; padding:3px 8px; font-size:0.75rem; border-radius:6px; cursor:pointer;" onclick="submitMemberStatus('${projectId}', '${member.studentId}', 'approve_member')">
                            Duyệt lại
                        </button>
                    </div>
                `;
            }

            tr.innerHTML = `
                <td style="padding:0.75rem 0.5rem;">
                    <div style="font-weight:600; color:#1E293B;">${escapeHtml(member.studentName || 'Sinh viên')}</div>
                    <div style="font-size:0.75rem; color:#64748B;">${escapeHtml(member.studentEmail || '')}</div>
                </td>
                <td style="padding:0.75rem 0.5rem; color:#475569;">${escapeHtml(member.className || '—')}</td>
                <td style="padding:0.75rem 0.5rem; color:#64748B;">${dateStr}</td>
                <td style="padding:0.75rem 0.5rem;">${statusBadge}</td>
                <td style="padding:0.75rem 0.5rem; text-align:right;">${actionButtons}</td>
            `;
            tbody.appendChild(tr);
        });
    } catch (err) {
        loading.style.display = 'none';
        empty.style.display = 'block';
        empty.innerHTML = `<p style="color:#EF4444;">Lỗi khi tải thành viên: ${err.message}</p>`;
    }
}

async function submitMemberStatus(projectId, studentId, action) {
    const csrfToken = document.querySelector('input[name="csrfToken"]')?.value || '';
    const formData = new FormData();
    formData.append('csrfToken', csrfToken);
    formData.append('action', action);
    formData.append('projectId', projectId);
    formData.append('studentId', studentId);
    formData.append('ajax', '1');

    try {
        const res = await fetch('projects.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            loadProjectMembers(projectId);
        } else {
            alert('Lỗi: ' + (data.message || 'Không thể cập nhật'));
        }
    } catch (err) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrfToken" value="${csrfToken}">
            <input type="hidden" name="action" value="${action}">
            <input type="hidden" name="projectId" value="${projectId}">
            <input type="hidden" name="studentId" value="${studentId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}
</script>
HTML;

require __DIR__ . '/includes/layout.php';
