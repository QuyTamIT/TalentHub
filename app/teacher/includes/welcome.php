<?php
/**
 * Teacher Dashboard - Welcome Banner Component
 */
?>
<section class="teacher-welcome">
    <div class="teacher-welcome__content">
        <div>
            <span class="teacher-welcome__tag">Tổng quan Giáo viên</span>
            <h2 class="teacher-welcome__title">Xin chào, <?= htmlspecialchars($teacherInfo['full_name']); ?></h2>
            <p class="teacher-welcome__description">
                Theo dõi học viên, sân chơi đang phụ trách, bài cần chấm và điểm danh QR trong một màn hình tổng quan gọn gàng.
            </p>
        </div>
        <div class="teacher-welcome__meta">
            <span class="teacher-chip teacher-chip--primary"><?= htmlspecialchars($teacherInfo['role_label']); ?></span>
            <span class="teacher-chip"><?= htmlspecialchars($teacherInfo['school_name']); ?></span>
            <span class="teacher-chip"><?= htmlspecialchars($todayLabel); ?></span>
            <span class="teacher-chip <?= !empty($dbStatus['connected']) ? 'teacher-chip--success' : 'teacher-chip--muted'; ?>">
                <?= htmlspecialchars($dbStatus['label']); ?>
            </span>
        </div>
    </div>
    <p class="teacher-welcome__note"><?= htmlspecialchars($dbStatus['message']); ?></p>
</section>
