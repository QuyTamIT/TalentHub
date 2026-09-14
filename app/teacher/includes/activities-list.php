<?php
/**
 * Teacher Dashboard - Activities In Charge Component
 * Displays real activities managed/organized by the currently logged-in teacher.
 */
$managedActivities = $managedActivities ?? ($dashboardData['managedActivities'] ?? []);
?>
<section class="teacher-section-box">
    <div class="teacher-section-box__header">
        <div>
            <h3 class="teacher-section-box__title">Sân chơi đang phụ trách</h3>
            <p class="teacher-section-box__subtitle">Danh sách các hoạt động, sân chơi do Thầy/Cô trực tiếp quản lý.</p>
        </div>
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <span class="teacher-section-box__count"><?= htmlspecialchars((string) count($managedActivities)); ?> hoạt động</span>
            <a href="/app/teacher/activities/index.php" class="teacher-section-link">Xem tất cả &rarr;</a>
        </div>
    </div>

    <?php if (empty($managedActivities)): ?>
        <div class="teacher-empty-state">
            <div class="teacher-empty-state__icon">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"></path>
                    <path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"></path>
                    <path d="M4 22h16"></path>
                    <path d="M18 2H6v7a6 6 0 0 0 12 0V2z"></path>
                </svg>
            </div>
            <h4 class="teacher-empty-state__title">Chưa có hoạt động nào phụ trách</h4>
            <p class="teacher-empty-state__desc">Thầy/Cô chưa có hoạt động hoặc sân chơi nào được tạo hoặc phân công.</p>
            <div class="teacher-empty-state__action">
                <a href="/app/teacher/activities/index.php?action=create" class="btn btn-primary btn-sm">
                    + Tạo hoạt động mới
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="teacher-activities-card-list">
            <?php foreach ($managedActivities as $act): ?>
                <article class="teacher-activity-card">
                    <div class="teacher-activity-card__main">
                        <div class="teacher-activity-card__header">
                            <span class="teacher-chip" style="font-size: 0.6875rem; padding: 2px 8px; font-weight: 600; background: #FFF0EB; color: #F83F70; border: 1px solid #FFDACB;">
                                <?= htmlspecialchars($act['category_label'] ?: 'Hoạt động'); ?>
                            </span>
                            <span class="teacher-status-pill teacher-status-pill--<?= htmlspecialchars($act['status_type']); ?>">
                                <?= htmlspecialchars($act['status_label']); ?>
                            </span>
                        </div>
                        <h4 class="teacher-activity-card__title">
                            <a href="<?= htmlspecialchars($act['detail_url']); ?>">
                                <?= htmlspecialchars($act['title']); ?>
                            </a>
                        </h4>
                        <div class="teacher-activity-card__meta-row">
                            <span class="teacher-activity-card__meta-item">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <polyline points="12 6 12 12 16 14"></polyline>
                                </svg>
                                <?= htmlspecialchars($act['time_display']); ?>
                            </span>
                            <span class="teacher-activity-card__meta-item">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="9" cy="7" r="4"></circle>
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                </svg>
                                <?= htmlspecialchars($act['students_label']); ?>
                            </span>
                        </div>
                    </div>
                    <div class="teacher-activity-card__actions">
                        <a href="<?= htmlspecialchars($act['detail_url']); ?>" class="btn btn-secondary btn-sm">
                            Xem chi tiết
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
