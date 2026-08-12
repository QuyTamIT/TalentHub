<?php
/**
 * Enterprise Dashboard - Welcome Banner Component
 */
?>
<section class="ent-welcome">
    <div class="ent-welcome__content">
        <span class="ent-welcome__tag">Dashboard Doanh nghiệp</span>
        <h2 class="ent-welcome__title">Xin chào, <?= htmlspecialchars($enterpriseInfo['company_name']); ?></h2>
        <p class="ent-welcome__description">
            Hệ thống vừa tự động phân tích và kết nối <strong style="color: var(--text-primary); font-weight: 700;"><?= htmlspecialchars($enterpriseInfo['new_matches_count']); ?> hồ sơ năng lực mới</strong> phù hợp với nhu cầu tuyển dụng của doanh nghiệp trong tuần này.
        </p>
        <div class="ent-welcome__actions">
            <a href="/app/enterprise/talents.php" class="btn btn-primary" data-route="/app/enterprise/talents.php">
                Xem nhân tài
                <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M5 12h14M12 5l7 7-7 7"/>
                </svg>
            </a>
            <a href="/app/enterprise/internships" class="btn btn-secondary" data-route="/app/enterprise/internships">
                Đăng tin tuyển dụng
                <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
            </a>
        </div>
    </div>
</section>
