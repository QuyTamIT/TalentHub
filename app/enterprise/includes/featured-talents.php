<?php
/**
 * Enterprise Dashboard - Featured Talents Component
 * Clean, production-grade candidate cards with clear data hierarchy.
 */
?>
<section class="ent-section-box">
    <div class="ent-section-box__header">
        <div>
            <h3 class="ent-section-box__title">Nhân tài nổi bật trong tuần</h3>
            <p class="ent-section-box__subtitle">Đề xuất dựa trên tiêu chí tuyển dụng và kỹ năng yêu cầu của doanh nghiệp</p>
        </div>
        <a href="<?= app_href('/app/enterprise/talents.php'); ?>" class="ent-section-box__link" data-route="/app/enterprise/talents.php">
            Xem tất cả &rarr;
        </a>
    </div>

    <div class="ent-talents-list">
        <?php if (empty($featuredTalents)): ?>
            <div class="ent-empty-state" style="text-align: center; padding: 2.5rem 1rem; background: var(--surface); border: 1px dashed var(--border); border-radius: var(--radius-md);">
                <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="var(--text-muted)" stroke-width="1.5" style="margin-bottom: 0.5rem;">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
                <h4 style="font-size: 0.9375rem; font-weight: 600; color: var(--text-primary); margin-bottom: 0.25rem;">Chưa có nhân tài phù hợp trong tuần</h4>
                <p style="font-size: 0.8125rem; color: var(--text-secondary); margin-bottom: 1rem;">Hệ thống sẽ tự động đề xuất sinh viên khi có hồ sơ mới phù hợp với nhu cầu tuyển dụng.</p>
                <a href="<?= app_href('/app/enterprise/talents.php'); ?>" class="btn btn-secondary btn-sm" data-route="/app/enterprise/talents.php">Khám phá kho nhân tài &rarr;</a>
            </div>
        <?php else: ?>
            <?php foreach ($featuredTalents as $talent): 
                $nameParts = explode(' ', $talent['name']);
                $initials = count($nameParts) >= 2 
                    ? mb_substr($nameParts[count($nameParts) - 2], 0, 1, 'UTF-8') . mb_substr($nameParts[count($nameParts) - 1], 0, 1, 'UTF-8')
                    : mb_substr($nameParts[0], 0, 1, 'UTF-8');
                $score = $talent['talent_score'] ?? $talent['match_score'];
                $detailUrl = app_href('/app/enterprise/talents/detail.php?id=' . urlencode((string)$talent['id']));
            ?>
                <article class="ent-talent-card">
                    <div class="ent-talent-card__left">
                        <div class="ent-talent-card__avatar">
                            <?= htmlspecialchars($initials); ?>
                        </div>
                        <div class="ent-talent-card__details">
                            <div class="ent-talent-card__name-row">
                                <a href="<?= htmlspecialchars($detailUrl); ?>" class="ent-talent-card__name">
                                    <?= htmlspecialchars($talent['name']); ?>
                                </a>
                                <span class="ent-talent-card__score-badge" title="Độ tương thích năng lực">
                                    <?= htmlspecialchars((string)$score); ?>% phù hợp
                                </span>
                            </div>
                            
                            <div class="ent-talent-card__meta-line">
                                <span class="ent-talent-card__school">
                                    <?= htmlspecialchars($talent['school']); ?> &bull; <?= htmlspecialchars($talent['major']); ?>
                                </span>
                                <span class="ent-talent-card__meta-divider">&bull;</span>
                                <span class="ent-talent-card__exp">
                                    <?= htmlspecialchars($talent['experience_hours']); ?>
                                </span>
                            </div>

                            <div class="ent-talent-card__skills">
                                <?php 
                                // Display top 3 key skills for clean scanning
                                $topSkills = array_slice($talent['skills'], 0, 3);
                                foreach ($topSkills as $skill): 
                                ?>
                                    <span class="skill-tag"><?= htmlspecialchars($skill); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="ent-talent-card__actions">
                        <a href="<?= htmlspecialchars($detailUrl); ?>" class="btn btn-secondary btn-sm" data-route="<?= htmlspecialchars($detailUrl); ?>">
                            Xem hồ sơ
                        </a>
                        <button class="btn btn-primary btn-sm ent-talent-btn" data-talent-id="<?= htmlspecialchars((string)$talent['id']); ?>" data-action="contact">
                            Liên hệ
                        </button>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>
