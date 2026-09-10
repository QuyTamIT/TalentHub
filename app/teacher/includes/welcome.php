<?php
/**
 * Teacher Dashboard - Welcome Banner Component
 */
$todayLabel = $todayLabel ?? date('d/m/Y');
$rawTeacherName = $_SESSION['user']['fullName'] ?? ($_SESSION['user']['full_name'] ?? ($_SESSION['user_name'] ?? ''));
$teacherName = $rawTeacherName !== '' && $rawTeacherName !== 'Test Teacher'
    ? $rawTeacherName
    : ($teacherInfo['full_name'] ?? 'Thầy/Cô');

if (($teacherName === 'Test Teacher' || $teacherName === 'Thầy/Cô' || $teacherName === 'Giáo viên') && !empty($_SESSION['user']['email']) && !str_contains((string)$_SESSION['user']['email'], 'test')) {
    $parts = explode('@', (string)$_SESSION['user']['email']);
    $teacherName = ucwords(str_replace(['.', '_', '-'], ' ', $parts[0] ?? 'Thầy/Cô'));
}
if ($teacherName === 'minh triet') {
    $teacherName = 'Minh Triết';
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
</section>
