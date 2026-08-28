<?php
/**
 * Enterprise Dashboard - Featured Talents Component ("Nhân tài nổi bật trong tuần")
 *
 * Clean Flexbox Architecture:
 * - Dynamic list of top real students queried from database.
 * - If no students/scores found, renders a compact, elegant Empty State with talent search CTA.
 */

$talentsList = !empty($featuredTalents) ? $featuredTalents : [];
?>
<section class="ent-featured-talents-box" aria-label="Nhân tài nổi bật trong tuần">
    <!-- Header: Title on the left, Link on the right -->
    <div class="ent-featured-talents-box__header">
        <h2 class="ent-featured-talents-box__title">Nhân tài nổi bật trong tuần</h2>
        <a href="<?= app_href('/app/enterprise/talents.php'); ?>" class="ent-featured-talents-box__view-all" data-route="/app/enterprise/talents.php">
            <span>Xem tất cả →</span>
        </a>
    </div>

    <!-- Candidate List / Empty State View -->
    <div class="ent-talents-list">
        <?php if (empty($talentsList)): ?>
            <div class="ent-empty-state ent-empty-state--compact">
                <div class="ent-empty-state__icon" aria-hidden="true">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#94A3B8" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                </div>
                <h3 class="ent-empty-state__title">Chưa có đề xuất nhân tài trong tuần</h3>
                <p class="ent-empty-state__desc">Hệ thống sẽ tự động tổng hợp các hồ sơ sinh viên, học sinh tiềm năng có điểm số và kỹ năng nổi bật khi có dữ liệu mới.</p>
                <a href="<?= app_href('/app/enterprise/talents.php'); ?>" class="ent-btn-search-talents" data-route="/app/enterprise/talents.php">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <span>Tìm kiếm hồ sơ ứng viên</span>
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($talentsList as $talent):
                $talentId = (string) ($talent['id'] ?? '');
                $talentName = (string) ($talent['name'] ?? 'Ứng viên tiềm năng');
                $talentScore = (int) ($talent['talent_score'] ?? $talent['match_score'] ?? 95);
                $talentMeta = (string) ($talent['meta_description'] ?? 'Lớp 12 • THPT • AI, Python');
                $avatarLetter = (string) ($talent['avatar_letter'] ?? 'UV');
                $avatarBg = (string) ($talent['avatar_bg'] ?? '#F97316');
                $detailUrl = app_href('/app/enterprise/talents/detail.php' . (!empty($talentId) ? '?id=' . urlencode($talentId) : ''));
            ?>
                <article class="ent-talent-row">
                    <!-- Left: Avatar & Info -->
                    <div class="ent-talent-row__left">
                        <div class="ent-talent-row__avatar" style="background-color: <?= htmlspecialchars($avatarBg); ?>;" aria-hidden="true">
                            <?= htmlspecialchars($avatarLetter); ?>
                        </div>

                        <div class="ent-talent-row__info">
                            <div class="ent-talent-row__name-row">
                                <a href="<?= htmlspecialchars($detailUrl); ?>" class="ent-talent-row__name" data-route="/app/enterprise/talents/detail.php">
                                    <?= htmlspecialchars($talentName); ?>
                                </a>
                                <span class="ent-talent-row__score-badge">
                                    ★ <?= htmlspecialchars((string)$talentScore); ?> điểm
                                </span>
                            </div>

                            <p class="ent-talent-row__meta-text">
                                <?= htmlspecialchars($talentMeta); ?>
                            </p>
                        </div>
                    </div>

                    <!-- Right: Action Button -->
                    <div class="ent-talent-row__actions">
                        <a
                            href="<?= htmlspecialchars($detailUrl); ?>"
                            class="ent-btn-contact"
                            aria-label="Xem hồ sơ và liên hệ với ứng viên <?= htmlspecialchars($talentName); ?>"
                            data-route="/app/enterprise/talents/detail.php"
                        >
                            Xem hồ sơ
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>
