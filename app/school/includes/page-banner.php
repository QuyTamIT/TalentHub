<?php
/**
 * School Dashboard - Compact Page Banner Component.
 *
 * Expects $pageTitle and optionally $pageDescription and $pageActions.
 */
$pageDescription = $pageDescription ?? 'Khu vực Nhà trường';
?>
<section class="school-welcome school-welcome--compact">
    <div class="school-welcome__body">
        <div class="school-welcome__content">
            <span class="school-welcome__tag">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>
                    <path d="M6 12v5c3 3 9 3 12 0v-5"></path>
                </svg>
                Khu vực Nhà trường
            </span>
            <h2 class="school-welcome__title"><?= htmlspecialchars($pageTitle ?? 'Tổng quan'); ?></h2>
            <p class="school-welcome__description"><?= htmlspecialchars($pageDescription); ?></p>
            <?php if (!empty($pageActions)): ?>
                <div class="school-welcome__actions">
                    <?= $pageActions; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
