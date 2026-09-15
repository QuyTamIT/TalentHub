<section class="teacher-section-box teacher-managed-panel" aria-labelledby="managed-heading">
    <div class="teacher-section-box__header">
        <h2 class="teacher-section-box__title" id="managed-heading">Sân chơi đang phụ trách</h2>
        <a class="teacher-slide-link" href="<?= app_href('/app/teacher/activities/index.php'); ?>">Xem tất cả →</a>
    </div>
    <?php if ($managedActivities === []): ?>
        <div class="teacher-empty-state">
            <h3 class="teacher-empty-state__title">Chưa có sân chơi</h3>
            <p class="teacher-empty-state__desc">Tạo sân chơi đầu tiên để tổ chức hoạt động và theo dõi học viên.</p>
            <a class="btn btn-primary" href="<?= app_href('/app/teacher/activities/index.php?action=create'); ?>">Tạo sân chơi mới</a>
        </div>
    <?php else: ?>
        <div class="teacher-managed-list">
            <?php foreach ($managedActivities as $activity): ?>
                <a class="teacher-managed-row" href="<?= htmlspecialchars(app_href('/app/teacher/activities/index.php?action=view&id=' . urlencode((string) $activity['id']))); ?>">
                    <span class="teacher-playground-icon" aria-hidden="true">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"></path><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"></path><path d="M4 22h16"></path><path d="M18 2H6v7a6 6 0 0 0 12 0V2z"></path></svg>
                    </span>
                    <span class="teacher-managed-identity">
                        <strong><?= htmlspecialchars($activity['title']); ?></strong>
                        <small><?= htmlspecialchars($activity['start_label']); ?> · <?= (int) $activity['registered_count']; ?>/<?= (int) $activity['capacity']; ?> học viên</small>
                    </span>
                    <span class="teacher-status-pill teacher-status-pill--<?= htmlspecialchars($activity['status_class']); ?>"><?= htmlspecialchars($activity['status_label']); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
