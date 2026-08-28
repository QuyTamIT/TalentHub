<?php
/** @var list<array<string,mixed>> $credentialItems */
$credentialItems = is_array($credentialItems ?? null) ? $credentialItems : [];
$credentialCompact = (bool) ($credentialCompact ?? false);
?>
<?php if ($credentialItems === []): ?>
    <div class="learner-school-credential-empty">
        <?= learner_icon('award', 24); ?>
        <p>Nhà trường chưa công bố huy hiệu hoặc chứng chỉ phù hợp ở thời điểm này.</p>
    </div>
<?php else: ?>
    <div class="learner-school-credential-grid<?= $credentialCompact ? ' learner-school-credential-grid--compact' : ''; ?>">
        <?php foreach ($credentialItems as $credential): ?>
            <?php
            $status = (string) ($credential['status'] ?? 'locked');
            $tone = match ($status) {
                'achieved', 'issued' => 'success',
                'eligible' => 'primary',
                'recommended' => 'ai',
                default => 'locked',
            };
            $kind = (string) ($credential['kind'] ?? 'badge');
            $icon = (string) ($credential['icon_key'] ?? 'award');
            if ($icon === 'certificate') {
                $icon = 'graduation-cap';
            }
            $progress = max(0, min(100, (int) ($credential['progress_percent'] ?? 0)));
            $dateValue = $credential['issued_at'] ?? $credential['awarded_at'] ?? null;
            $dateLabel = '';
            if (is_string($dateValue) && trim($dateValue) !== '') {
                $timestamp = strtotime($dateValue);
                $dateLabel = $timestamp === false ? '' : date('d/m/Y', $timestamp);
            }
            ?>
            <article class="learner-school-credential learner-school-credential--<?= learner_escape($tone); ?>">
                <div class="learner-school-credential__top">
                    <span class="learner-school-credential__icon" aria-hidden="true"><?= learner_icon($icon, 28); ?></span>
                    <span class="learner-school-credential__kind"><?= $kind === 'certificate' ? 'Chứng chỉ trường' : 'Huy hiệu trường'; ?></span>
                    <span class="learner-school-credential__status"><?= learner_escape($credential['status_label'] ?? 'Chưa mở khóa'); ?></span>
                </div>
                <h3><?= learner_escape($credential['name'] ?? ''); ?></h3>
                <p class="learner-school-credential__description"><?= learner_escape($credential['description'] ?? ''); ?></p>
                <p class="learner-school-credential__issuer"><?= learner_icon('building', 15); ?> <?= learner_escape($credential['issuer_name'] ?? 'Nhà trường'); ?></p>
                <?php if ($status === 'recommended' && $progress < 100 && empty($credential['awarded_at']) && empty($credential['issued_at'])): ?>
                    <p class="learner-school-credential__reason"><?= learner_icon('sparkles', 15); ?> <?= learner_escape($credential['reason'] ?? 'Phù hợp với hồ sơ năng lực của bạn.'); ?></p>
                <?php elseif ($dateLabel !== '' && ($status === 'achieved' || $status === 'issued' || $progress >= 100)): ?>
                    <p class="learner-school-credential__reason"><?= learner_icon('check', 15); ?> Được ghi nhận ngày <?= learner_escape($dateLabel); ?></p>
                <?php endif; ?>
                <div class="learner-school-credential__progress">
                    <div class="learner-progress" role="progressbar" aria-label="Tiến độ <?= learner_escape($credential['name'] ?? ''); ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $progress; ?>" style="position: relative; width: 100%; height: 8px; background: #E2E8F0; border-radius: 9999px; overflow: hidden;">
                        <span class="learner-progress--<?= $tone; ?>" style="--learner-progress: <?= $progress; ?>%; width: <?= $progress; ?>%; background-color: <?= match($tone) { 'success' => '#10B981', 'primary' => '#F97316', 'ai' => '#6366F1', default => '#94A3B8' }; ?>; display: block; height: 100%; border-radius: inherit; transition: width 0.55s ease;"></span>
                    </div>
                    <strong style="color: #0F172A;"><?= $status === 'recommended' && $progress === 0 ? learner_escape($credential['match_score'] ?? $progress) . '% phù hợp' : $progress . '%'; ?></strong>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
