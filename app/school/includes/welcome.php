<?php
/**
 * School Dashboard - Welcome Banner Component
 */
?>
<section class="sch-welcome">
    <div class="sch-welcome__content">
        <span class="sch-welcome__tag">Dashboard Nhà trường</span>
        <h2 class="sch-welcome__title">Xin chào, <?= htmlspecialchars($schoolInfo['name']); ?></h2>
        <p class="sch-welcome__description">
            Hệ thống vừa cập nhật chỉ số năng lực theo thời gian thực của
            <strong style="color: var(--text-primary); font-weight: 700;"><?= number_format($schoolInfo['student_total']); ?> học sinh</strong>
            thuộc <?= $schoolInfo['class_total']; ?> lớp trong năm học <?= htmlspecialchars($schoolInfo['academic_year']); ?>.
        </p>
        <div class="sch-welcome__actions">
            <a href="reports.php" class="btn btn-primary" data-route="reports.php">
                Xem báo cáo
                <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M5 12h14M12 5l7 7-7 7"/>
                </svg>
            </a>
            <a href="classes.php" class="btn btn-secondary" data-route="classes.php">
                Quản lý lớp
                <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                </svg>
            </a>
        </div>
    </div>
</section>