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
            <span class="teacher-welcome__tag">Xin chào</span>
            <h1 class="teacher-welcome__title"><?= htmlspecialchars($teacherName); ?></h1>
            <p class="teacher-welcome__description">Có <?= number_format((int) ($metrics['upcoming_activities'] ?? 0)); ?> sân chơi sắp diễn ra và <?= number_format((int) ($metrics['pending_assessments'] ?? 0)); ?> học viên cần đánh giá.</p>
            <div class="teacher-welcome-actions">
                <a class="btn teacher-welcome-create" href="<?= app_href('/app/teacher/activities/index.php?action=create'); ?>">＋ Tạo sân chơi mới</a>
                <a class="btn teacher-welcome-grade" href="<?= app_href('/app/teacher/assessments/index.php'); ?>">Vào chấm điểm</a>
            </div>
        </div>
    </div>
</section>
