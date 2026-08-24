<?php
if (!isset($learnerPageBanner) || !is_array($learnerPageBanner)) {
    return;
}

$learnerBannerTitle = learner_escape((string) ($learnerPageBanner['title'] ?? 'TalentHub'));
$learnerBannerId = learner_escape((string) ($learnerPageBanner['id'] ?? 'learner-page-banner-title'));
?>
<section class="learner-page-banner" aria-labelledby="<?= $learnerBannerId; ?>">
    <span class="learner-page-banner__icon" aria-hidden="true">
        <?= learner_icon((string) ($learnerPageBanner['icon'] ?? 'sparkles'), 26); ?>
    </span>
    <div>
        <span class="learner-page-banner__eyebrow"><?= learner_escape((string) ($learnerPageBanner['eyebrow'] ?? 'TalentHub')); ?></span>
        <h1 id="<?= $learnerBannerId; ?>"><?= $learnerBannerTitle; ?></h1>
        <p><?= learner_escape((string) ($learnerPageBanner['description'] ?? '')); ?></p>
    </div>
</section>
