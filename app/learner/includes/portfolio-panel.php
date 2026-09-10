<?php
// Reusable own-learner report panel. API always resolves ownership from the session.
$portfolioContext = isset($portfolioProjectId) ? (string)$portfolioProjectId : '';
$isProfileShowcase = ($portfolioContext === '');
?>
<link rel="stylesheet" href="<?= learner_escape(app_href('/assets/css/learner-portfolio.css')); ?>">
<section class="portfolio-panel learner-card" aria-label="Báo cáo dự án và thực tập">
    <div class="learner-section-heading learner-section-heading--icon" style="margin-bottom: 0.75rem;">
        <span class="learner-section-heading__icon"><?= learner_icon('briefcase', 22); ?></span>
        <div style="display: flex; align-items: center; justify-content: space-between; width: 100%; flex-wrap: wrap; gap: 0.5rem;">
            <h2 style="margin: 0;"><?= $isProfileShowcase ? 'Quá trình thực tập đã hoàn thành' : 'Nộp báo cáo dự án'; ?></h2>
            <?php if ($isProfileShowcase): ?>
                <a href="ecosystem.php?tab=enterprises&amp;filter=completed" class="learner-btn learner-btn--outline" style="font-size: 0.8rem; padding: 4px 10px; display: inline-flex; align-items: center; gap: 4px; color: #EA580C; border-color: #FDBA74; text-decoration: none;">
                    <?= learner_icon('sparkles', 14); ?> Xem doanh nghiệp &amp; cơ hội đã hoàn thành <?= learner_icon('arrow-right', 14); ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
    <p style="color: #64748B; font-size: 0.875rem; margin-top: 0; margin-bottom: 1.25rem;">
        <?= $isProfileShowcase
            ? 'Danh sách các vị trí thực tập doanh nghiệp đã hoàn thành và được giảng viên hướng dẫn xác nhận kết quả.'
            : 'Báo cáo tự khai chỉ trở thành minh chứng sau khi giảng viên được phân công xác nhận. Hoạt động ngoại khóa không được quy đổi thành kỹ năng.'; ?>
    </p>
    <div data-portfolio data-role="student" data-context="<?= learner_escape($portfolioContext); ?>" <?= $isProfileShowcase ? 'data-filter="completed"' : ''; ?> data-endpoint="<?= learner_escape(app_href('/app/learner/api/v1/portfolio.php')); ?>"></div>
</section>
<script src="<?= learner_escape(app_href('/assets/js/learner-portfolio.js')); ?>" defer></script>
