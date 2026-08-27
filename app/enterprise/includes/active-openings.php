<?php
/**
 * Enterprise Dashboard - Active Job Openings Component ("Tin tuyển dụng đang hoạt động")
 * 
 * Displays currently active internship job posts with applicant counters, deadlines,
 * and direct actions for managing applications.
 */

$openingsList = !empty($activePosts) ? $activePosts : [];
?>
<section class="ent-active-openings-box" aria-label="Tin tuyển dụng đang hoạt động">
    <!-- Header of the Active Openings Section -->
    <div class="ent-active-openings-box__header">
        <div class="ent-active-openings-box__titles">
            <h2 class="ent-active-openings-box__title">Tin tuyển dụng đang hoạt động</h2>
            <p class="ent-active-openings-box__subtitle">Theo dõi tiến độ tiếp nhận hồ sơ các vị trí thực tập đang mở</p>
        </div>
        <a href="<?= app_href('/app/enterprise/internships/'); ?>" class="ent-active-openings-box__view-all" data-route="/app/enterprise/internships/">
            <span>Xem tất cả tin</span>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <line x1="5" y1="12" x2="19" y2="12"></line>
                <polyline points="12 5 19 12 12 19"></polyline>
            </svg>
        </a>
    </div>

    <!-- Active Openings List -->
    <div class="ent-openings-list">
        <?php if (empty($openingsList)): ?>
            <div class="ent-empty-state">
                <div class="ent-empty-state__icon">
                    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#94A3B8" stroke-width="1.5">
                        <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                        <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                    </svg>
                </div>
                <h3 class="ent-empty-state__title">Chưa có tin tuyển dụng nào đang mở</h3>
                <p class="ent-empty-state__desc">Đăng tin tuyển dụng mới để tiếp cận hàng nghìn sinh viên tiềm năng từ các trường đại học hàng đầu.</p>
                <a href="<?= app_href('/app/enterprise/internships/create.php'); ?>" class="btn btn-primary" data-route="/app/enterprise/internships/">
                    + Đăng tin tuyển dụng ngay
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($openingsList as $post): 
                $postId = (string) ($post['id'] ?? '');
                $postTitle = (string) ($post['title'] ?? 'Vị trí tuyển dụng');
                $postLocation = (string) ($post['location'] ?? 'Hà Nội / Toàn quốc');
                $postWorkType = (string) ($post['work_type'] ?? 'Full-time');
                $postDeadline = (string) ($post['deadline'] ?? 'Đang nhận hồ sơ');
                $applicantCount = (int) ($post['applicant_count'] ?? 0);
                $applicantsUrl = app_href('/app/enterprise/internships/applicants.php' . (!empty($postId) ? '?post_id=' . urlencode($postId) : ''));
            ?>
                <article class="ent-opening-card">
                    <!-- Left: Icon & Info -->
                    <div class="ent-opening-card__left">
                        <div class="ent-opening-card__icon" aria-hidden="true">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                                <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                            </svg>
                        </div>
                        <div class="ent-opening-card__details">
                            <div class="ent-opening-card__title-row">
                                <a href="<?= htmlspecialchars($applicantsUrl); ?>" class="ent-opening-card__title" data-route="/app/enterprise/internships/">
                                    <?= htmlspecialchars($postTitle); ?>
                                </a>
                                <span class="ent-opening-badge ent-opening-badge--active">Đang nhận hồ sơ</span>
                            </div>
                            
                            <div class="ent-opening-card__meta">
                                <span class="ent-opening-card__meta-item">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                        <circle cx="12" cy="10" r="3"></circle>
                                    </svg>
                                    <span><?= htmlspecialchars($postLocation); ?></span>
                                </span>

                                <span class="ent-opening-card__divider" aria-hidden="true">•</span>

                                <span class="ent-opening-card__meta-item">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <polyline points="12 6 12 12 16 14"></polyline>
                                    </svg>
                                    <span>Hạn nộp: <?= htmlspecialchars($postDeadline); ?></span>
                                </span>

                                <span class="ent-opening-card__divider" aria-hidden="true">•</span>

                                <span class="ent-opening-card__meta-item ent-opening-card__meta-item--applicants">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="9" cy="7" r="4"></circle>
                                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                    </svg>
                                    <strong><?= htmlspecialchars((string)$applicantCount); ?> hồ sơ đã nhận</strong>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Action Button -->
                    <div class="ent-opening-card__actions">
                        <a href="<?= htmlspecialchars($applicantsUrl); ?>" class="ent-btn-review" data-route="/app/enterprise/internships/">
                            <span>Duyệt hồ sơ</span>
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                <polyline points="12 5 19 12 12 19"></polyline>
                            </svg>
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>
