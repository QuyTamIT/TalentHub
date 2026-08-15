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
        <a href="/app/enterprise/talents.php" class="ent-section-box__link" data-route="/app/enterprise/talents.php">
            Xem tất cả &rarr;
        </a>
    </div>

    <div class="ent-talents-list">
        <?php foreach ($featuredTalents as $talent): 
            $nameParts = explode(' ', $talent['name']);
            $initials = count($nameParts) >= 2 
                ? mb_substr($nameParts[count($nameParts) - 2], 0, 1, 'UTF-8') . mb_substr($nameParts[count($nameParts) - 1], 0, 1, 'UTF-8')
                : mb_substr($nameParts[0], 0, 1, 'UTF-8');
            $score = $talent['talent_score'] ?? $talent['match_score'];
        ?>
            <article class="ent-talent-card">
                <div class="ent-talent-card__left">
                    <div class="ent-talent-card__avatar">
                        <?= htmlspecialchars($initials); ?>
                    </div>
                    <div class="ent-talent-card__details">
                        <div class="ent-talent-card__name-row">
                            <a href="/app/enterprise/talents/detail.php?id=<?= htmlspecialchars($talent['id']); ?>" class="ent-talent-card__name">
                                <?= htmlspecialchars($talent['name']); ?>
                            </a>
                            <span class="ent-talent-card__score-badge" title="Độ tương thích năng lực">
                                <?= htmlspecialchars($score); ?>% phù hợp
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
                    <a href="/app/enterprise/talents/detail.php?id=<?= htmlspecialchars($talent['id']); ?>" class="btn btn-secondary btn-sm" data-route="/app/enterprise/talents/detail.php?id=<?= htmlspecialchars($talent['id']); ?>">
                        Xem hồ sơ
                    </a>
                    <button class="btn btn-primary btn-sm ent-talent-btn" data-talent-id="<?= htmlspecialchars($talent['id']); ?>" data-action="contact">
                        Liên hệ
                    </button>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
