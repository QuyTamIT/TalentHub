<?php
/**
 * Teacher Dashboard - Welcome Banner Component
 */
$todayLabel = $todayLabel ?? date('d/m/Y');
$dbStatus = $dbStatus ?? ($dashboardData['dbStatus'] ?? ['connected' => true, 'label' => 'Đã kết nối', 'message' => '']);
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
            <span class="teacher-chip"><?= htmlspecialchars($teacherInfo['school_name'] ?? 'Cao đẳng Quốc tế BTEC FPT'); ?></span>
            <span class="teacher-chip" style="background: #EFF6FF; color: #1D4ED8; font-weight: 700; border: 1px solid #BFDBFE;">Lớp phụ trách: BTEC-AI-2026A</span>
            <span class="teacher-chip"><?= htmlspecialchars($todayLabel); ?></span>
            <span class="teacher-chip <?= !empty($dbStatus['connected']) ? 'teacher-chip--success' : 'teacher-chip--muted'; ?>">
                <?= htmlspecialchars($dbStatus['label'] ?? 'Đã kết nối'); ?>
            </span>
        </div>
    </div>
    <?php if (!empty($dbStatus['message'])): ?>
        <p class="teacher-welcome__note"><?= htmlspecialchars($dbStatus['message']); ?></p>
    <?php endif; ?>
</section>
