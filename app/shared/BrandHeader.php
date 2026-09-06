<?php
/**
 * Shared FTalentHub brand mark and wordmark.
 *
 * Keeping this renderer server-side avoids small SVG/path/class differences
 * between learner, teacher, school, enterprise, admin, and public surfaces.
 */
if (!function_exists('renderBrandHeader')) {
    function renderBrandHeader(
        string $href,
        string $subtitle,
        string $ariaLabel,
        string $className = 'learner-brand'
    ): void {
        $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        ?>
        <a href="<?= $escape($href); ?>" class="<?= $escape($className); ?>" aria-label="<?= $escape($ariaLabel); ?>">
            <span class="learner-brand__mark" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                    <path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-3-5.6 3 1.1-6.2L3 9.6l6.2-.9L12 3Z"></path>
                </svg>
            </span>
            <span class="learner-brand__text">
                <span class="learner-brand__name">FTalent<span>Hub</span></span>
                <span class="learner-brand__subtitle"><?= $escape($subtitle); ?></span>
            </span>
        </a>
        <?php
    }
}
