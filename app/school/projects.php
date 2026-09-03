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

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $session->assertCsrf(isset($_POST['csrfToken']) ? (string) $_POST['csrfToken'] : null);
    try {
        $action = (string) ($_POST['action'] ?? 'create');
        if ($action === 'create') {
            $service->createProject($userId, [
                'title'           => $_POST['title'] ?? '',
                'category'        => $_POST['category'] ?? 'general',
                'topic'           => $_POST['topic'] ?? '',
                'mentorTeacherId' => $_POST['mentorTeacherId'] ?? null,
                'authorIds'       => $_POST['authorIds'] ?? [],
                'description'     => $_POST['description'] ?? '',
                'fundingGoal'     => $_POST['fundingGoal'] ?? null,
                'startAt'         => $_POST['startAt'] ?? null,
                'endAt'           => $_POST['endAt'] ?? null,
                'status'          => $_POST['status'] ?? 'draft',
            ], RequestId::generate());
            $flash = 'Đã tạo dự án mới thành công.';
        } elseif ($action === 'status') {
            $service->updateProject($userId, (string) ($_POST['projectId'] ?? ''), ['status' => $_POST['status'] ?? 'draft']);
            $flash = 'Đã cập nhật trạng thái dự án.';
        }
    } catch (ApiException $exception) {
        $error = $exception->getMessage();
    } catch (Throwable $exception) {
        $error = 'Không thể cập nhật dự án: ' . $exception->getMessage();
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

<div class="projects-layout">
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
            
            <div class="form-group-section">
                <h3 class="form-section-title">1. Thông tin chung</h3>
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

            <div class="form-group-section">
                <h3 class="form-section-title">2. Nhân sự tham gia</h3>
                <div class="school-form__grid">
                    <label class="school-form__field">
                        <span>Giảng viên hướng dẫn</span>
                        <select name="mentorTeacherId" class="select-enhanced typeui-select">
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
                        <div class="author-select-box">
                            <div class="author-search">
                                <input type="text" id="authorSearch" placeholder="Tìm kiếm sinh viên..." onkeyup="filterAuthors()">
                            </div>
                            <div class="author-list" id="authorList">
                                <?php foreach ($students as $student): ?>
                                    <label class="author-item">
                                        <input type="checkbox" name="authorIds[]" value="<?= htmlspecialchars((string) $student['id']); ?>">
                                        <div class="author-info">
                                            <span class="author-name"><?= htmlspecialchars((string) $student['fullName']); ?></span>
                                            <span class="author-class"><?= htmlspecialchars((string) ($student['className'] ?? '')); ?></span>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </label>
                </div>
            </div>

            <div class="form-group-section">
                <h3 class="form-section-title">3. Triển khai & Kêu gọi Tài trợ</h3>
                <div class="school-form__grid">
                    <label class="school-form__field">
                        <span>Mục tiêu tài trợ (VND)</span>
                        <div class="input-with-icon">
                            <span class="input-icon">₫</span>
                            <input name="fundingGoal" id="fundingGoalInput" type="number" min="1" step="1000" placeholder="Ví dụ: 50000000" oninput="calculateBudgets()">
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

            <div class="form-group-section">
                <h3 class="form-section-title">4. Lộ trình thực hiện & Nghiệm thu</h3>
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
            
            <div class="form-group-section">
                <h3 class="form-section-title" style="margin-bottom: 0.25rem;">5. Kế hoạch phân bổ kinh phí</h3>
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
                            <input type="number" class="budget-amount-input" name="budgetAmounts[]" required min="1" placeholder="VD: 20000000" oninput="calculateBudgets()">
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

            <div class="form-actions-bar">
                <button class="btn btn-primary btn-lg" type="submit">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg>
                    Tạo Dự Án
                </button>
            </div>
        </form>
    </section>

    <!-- Danh Sách Dự án -->
    <section class="school-section-box list-section">
        <div class="school-section-box__header" style="border-bottom: 1px solid #E2E8F0; padding-bottom: 1rem; margin-bottom: 1.5rem;">
            <h2 class="school-section-box__title">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                Danh sách Dự án
            </h2>
            <span class="badge badge-primary"><?= count($projects) ?> dự án</span>
        </div>
        
        <?php if ($projects === []): ?>
            <div class="empty-state">
                <div class="empty-icon">📁</div>
                <p>Nhà trường chưa khởi tạo dự án nào.</p>
                <small>Các dự án sau khi tạo sẽ hiển thị tại đây để theo dõi tiến độ.</small>
            </div>
        <?php else: ?>
            <div class="project-cards-container">
                <?php foreach ($projects as $project): ?>
                    <div class="project-card">
                        <div class="project-card__header">
                            <div class="project-title-area">
                                <h3><?= htmlspecialchars((string) $project['title']); ?></h3>
                                <span class="project-topic"><?= htmlspecialchars((string) ($project['topic'] ?? $project['category'] ?? 'General')); ?></span>
                            </div>
                            <div class="project-status-badge status-<?= $project['status'] ?>">
                                <?= htmlspecialchars($statusLabels[$project['status']] ?? $project['status']); ?>
                            </div>
                        </div>
                        
                        <div class="project-card__stats">
                            <div class="stat-item">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                                <span><?= (int) ($project['membersCount'] ?? 0); ?> thành viên</span>
                            </div>
                            <?php if (!empty($project['fundingGoal']) && $project['fundingGoal'] > 0): ?>
                            <div class="stat-item funding-stat">
                                <span style="font-weight: 700; font-size: 0.85rem; padding: 0.1rem 0.3rem; background: #D1FAE5; color: #047857; border-radius: 4px; margin-right: 0.25rem;">VNĐ</span>
                                <span>
                                    <strong><?= number_format((float) ($project['raisedAmount'] ?? 0), 0, ',', '.'); ?></strong> / 
                                    <?= number_format((float) ($project['fundingGoal'] ?? 0), 0, ',', '.'); ?>
                                    <small>(<?= (int) ($project['sponsorsCount'] ?? 0); ?> nhà tài trợ)</small>
                                </span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="project-card__actions">
                            <form method="post" style="display:flex; align-items:center; gap:0.5rem; width:100%;">
                                <input type="hidden" name="csrfToken" value="<?= htmlspecialchars($session->csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="action" value="status">
                                <input type="hidden" name="projectId" value="<?= htmlspecialchars((string) $project['id']); ?>">
                                <label style="font-size:0.8rem; color:var(--text-secondary); white-space:nowrap;">Cập nhật:</label>
                                <select name="status" class="status-select typeui-select typeui-select--compact typeui-select--status" onchange="this.form.submit()">
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

<?php
$pageBody = ob_get_clean();

$extraStyles = <<<'HTML'
<style>
.projects-layout {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.5rem;
}
@media (min-width: 1024px) {
    .projects-layout {
        grid-template-columns: 1fr 1fr;
        align-items: start;
    }
}

/* Form Styles enhancements */
.form-group-section {
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}
.form-section-title {
    font-size: 1.05rem;
    color: #1E293B;
    margin: 0 0 1rem 0;
    font-weight: 700;
    border-bottom: 2px solid #E2E8F0;
    padding-bottom: 0.5rem;
    display: inline-block;
}
.select-enhanced {
    width: 100%;
    padding: 0.6rem 0.75rem;
    border-radius: 8px;
    border: 1px solid #CBD5E1;
    background-color: #fff;
    font-size: 0.95rem;
    color: #334155;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}
.select-enhanced:focus {
    border-color: #2563EB;
    outline: none;
    box-shadow: 0 0 0 3px rgba(37,99,235,0.15);
}
.field-hint {
    display: block;
    font-size: 0.8rem;
    color: #64748B;
    margin-top: 0.4rem;
}
.input-with-icon {
    position: relative;
    display: flex;
    align-items: center;
}
.input-with-icon .input-icon {
    position: absolute;
    left: 1rem;
    color: #64748B;
    font-weight: bold;
}
.input-with-icon input {
    padding-left: 2.2rem;
    width: 100%;
}
.form-actions-bar {
    display: flex;
    justify-content: flex-end;
    margin-top: 1.5rem;
}
.btn-lg {
    padding: 0.75rem 1.5rem;
    font-size: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    border-radius: 8px;
}

/* Author Box Selector */
.author-select-box {
    border: 1px solid #CBD5E1;
    border-radius: 8px;
    background: #fff;
    overflow: hidden;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}
.author-search {
    padding: 0.75rem;
    border-bottom: 1px solid #E2E8F0;
    background: #F8FAFC;
}
.author-search input {
    width: 100%;
    padding: 0.5rem 0.75rem;
    border: 1px solid #CBD5E1;
    border-radius: 6px;
    font-size: 0.85rem;
    background: #fff;
    transition: all 0.2s;
}
.author-search input:focus {
    border-color: #2563EB;
    outline: none;
    box-shadow: 0 0 0 3px rgba(37,99,235,0.15);
}
.author-list {
    max-height: 220px;
    overflow-y: auto;
    padding: 0;
}
.author-item {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    cursor: pointer;
    transition: background 0.15s;
    margin: 0 !important;
    border-bottom: 1px solid #E2E8F0;
    width: auto !important;
}
.author-item:last-child {
    border-bottom: none;
}
.author-item:hover {
    background: #F8FAFC;
}
.author-item input[type="checkbox"] {
    margin: 0 !important;
    cursor: pointer;
    width: auto !important;
    flex-shrink: 0;
    transform: scale(1.1);
}
.author-info {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: 0.75rem;
    margin: 0 !important;
    width: auto !important;
    flex: 0 1 auto;
}
.author-name {
    font-weight: 600;
    color: #1E293B;
    font-size: 0.9rem;
    margin: 0 !important;
}
.author-class {
    font-size: 0.75rem;
    color: #64748B;
    background: #F1F5F9;
    padding: 0.2rem 0.5rem;
    border-radius: 4px;
    margin: 0 !important;
}

/* Project Cards */
.project-cards-container {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}
.project-card {
    background: #fff;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    padding: 1.25rem;
    transition: transform 0.2s, box-shadow 0.2s;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}
.project-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05);
    border-color: #CBD5E1;
}
.project-card__header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}
.project-title-area h3 {
    margin: 0 0 0.25rem 0;
    font-size: 1.15rem;
    color: #0F172A;
    font-weight: 700;
}
.project-topic {
    font-size: 0.85rem;
    color: #3B82F6;
    font-weight: 500;
    background: #EFF6FF;
    padding: 0.2rem 0.5rem;
    border-radius: 4px;
    display: inline-block;
}
.project-status-badge {
    font-size: 0.75rem;
    font-weight: 600;
    padding: 0.25rem 0.75rem;
    border-radius: 99px;
    text-transform: uppercase;
}
.status-draft { background: #F1F5F9; color: #475569; }
.status-in_progress { background: #DBEAFE; color: #1D4ED8; }
.status-completed { background: #D1FAE5; color: #047857; }
.status-archived { background: #FEE2E2; color: #B91C1C; }

.project-card__stats {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-bottom: 1rem;
    padding: 0.75rem;
    background: #F8FAFC;
    border-radius: 8px;
}
.stat-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
    color: #475569;
}
.funding-stat {
    color: #047857;
}
.funding-stat strong {
    font-size: 1rem;
}
.project-card__actions {
    border-top: 1px solid #E2E8F0;
    padding-top: 1rem;
}
.status-select {
    flex: 1;
    padding: 0.4rem;
    border: 1px solid #CBD5E1;
    border-radius: 6px;
    background: #F8FAFC;
    font-size: 0.85rem;
}
.badge-primary {
    background: #DBEAFE;
    color: #1D4ED8;
    padding: 0.25rem 0.75rem;
    border-radius: 99px;
    font-size: 0.85rem;
    font-weight: 600;
}
.empty-state {
    text-align: center;
    padding: 3rem 1rem;
    color: #64748B;
}
.empty-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}
</style>
HTML;

$extraScripts = <<<'HTML'
<script>
function filterAuthors() {
    const input = document.getElementById('authorSearch');
    const filter = input.value.toLowerCase();
    const nodes = document.querySelectorAll('.author-item');
    
    nodes.forEach(node => {
        const name = node.querySelector('.author-name').innerText.toLowerCase();
        const className = node.querySelector('.author-class').innerText.toLowerCase();
        
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
            <input type="number" class="budget-amount-input" name="budgetAmounts[]" required min="1" placeholder="VD: 20000000" oninput="calculateBudgets()">
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
    const totalGoal = parseFloat(goalInput.value) || 0;
    
    const amtInputs = document.querySelectorAll('.budget-amount-input');
    const pctDisplays = document.querySelectorAll('.budget-pct-display');
    
    let totalAmt = 0;
    
    amtInputs.forEach((input, index) => {
        const amt = parseFloat(input.value) || 0;
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
    
    const formattedTotalAmt = new Intl.NumberFormat('vi-VN').format(totalAmt);
    const formattedTotalGoal = new Intl.NumberFormat('vi-VN').format(totalGoal);
    
    if (totalAmt === 0) {
        summaryText.innerHTML = `Tổng phân bổ: 0 / ${formattedTotalGoal} VNĐ`;
        summaryText.style.color = '#64748B';
    } else if (totalAmt < totalGoal) {
        const diff = new Intl.NumberFormat('vi-VN').format(totalGoal - totalAmt);
        summaryText.innerHTML = `Tổng phân bổ: ${formattedTotalAmt} / ${formattedTotalGoal} VNĐ - <span style="color: #F59E0B;">Còn thiếu ${diff} VNĐ</span>`;
        summaryText.style.color = '#F59E0B';
    } else if (totalAmt === totalGoal) {
        summaryText.innerHTML = `Tổng phân bổ: ${formattedTotalAmt} / ${formattedTotalGoal} VNĐ - <span style="color: #10B981;">Đã phân bổ đủ ngân sách</span>`;
        summaryText.style.color = '#10B981';
    } else {
        const diff = new Intl.NumberFormat('vi-VN').format(totalAmt - totalGoal);
        summaryText.innerHTML = `Tổng phân bổ: ${formattedTotalAmt} / ${formattedTotalGoal} VNĐ - <span style="color: #EF4444;">Vượt quá ${diff} VNĐ</span>`;
        summaryText.style.color = '#EF4444';
    }
}

// Chạy tính toán lần đầu nếu có dữ liệu sẵn
document.addEventListener('DOMContentLoaded', calculateBudgets);
</script>
HTML;

require __DIR__ . '/includes/layout.php';
