<?php
/**
 * Teacher Dashboard - Welcome Banner Component
 */
$todayLabel = $todayLabel ?? date('d/m/Y');
$rawTeacherName = $_SESSION['user']['fullName'] ?? ($_SESSION['user']['full_name'] ?? ($_SESSION['user_name'] ?? ''));
$teacherName = $rawTeacherName !== ''
    ? $rawTeacherName
    : ($teacherInfo['full_name'] ?? 'Thầy/Cô');

if (($teacherName === '' || $teacherName === 'Thầy/Cô' || $teacherName === 'Giáo viên') && !empty($_SESSION['user']['email'])) {
    $parts = explode('@', (string)$_SESSION['user']['email']);
    $teacherName = ucwords(str_replace(['.', '_', '-'], ' ', $parts[0] ?? 'Thầy/Cô'));
}
?>
<section class="teacher-welcome">
    <div class="teacher-welcome__content">
        <div>
            <span class="teacher-welcome__tag">Tổng quan Giáo viên</span>
            <h2 class="teacher-welcome__title">Xin chào, <?= htmlspecialchars($teacherName); ?></h2>
            <p class="teacher-welcome__description">
                Theo dõi học viên, sân chơi đang phụ trách, bài cần chấm và điểm danh QR trong một màn hình tổng quan gọn gàng.
            </p>
        </div>
        <div class="teacher-welcome__meta">
            <span class="teacher-chip teacher-chip--primary"><?= htmlspecialchars($teacherInfo['role_label'] ?? 'Giáo viên / Hướng dẫn viên'); ?></span>
            <?php if (!empty($teacherInfo['school_name'])): ?>
                <span class="teacher-chip"><?= htmlspecialchars($teacherInfo['school_name']); ?></span>
            <?php endif; ?>
            <?php if (!empty($teacherInfo['managed_class_name'])): ?>
                <span class="teacher-chip" style="background: #FAF5FF; color: #8B4DE8; font-weight: 700; border: 1px solid rgba(139, 77, 232, 0.25);">Lớp phụ trách: <?= htmlspecialchars($teacherInfo['managed_class_name']); ?></span>
            <?php else: ?>
                <span class="teacher-chip" style="background: #F1F5F9; color: #64748B; font-weight: 600;">Chưa phân công lớp</span>
            <?php endif; ?>
            <span class="teacher-chip"><?= htmlspecialchars($todayLabel); ?></span>
        </div>
    </div>
    <div class="teacher-welcome__actions">
        <a href="/app/teacher/activities/index.php?action=create" class="btn btn-primary btn-sm">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            Tạo sân chơi mới
        </a>
        <a href="/app/teacher/grading.php" class="btn btn-secondary btn-sm">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"></path>
                <rect x="9" y="3" width="6" height="4" rx="2"></rect>
                <polyline points="9 14 11 16 15 12"></polyline>
            </svg>
            Vào chấm điểm
        </a>
    </div>
</section>
