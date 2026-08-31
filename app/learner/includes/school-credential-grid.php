<?php
/** @var list<array<string,mixed>> $credentialItems */
$credentialItems = is_array($credentialItems ?? null) ? $credentialItems : [];
$credentialCompact = (bool) ($credentialCompact ?? false);
$credentialKinds = array_values(array_unique(array_map(
    static fn (array $item): string => (string) ($item['kind'] ?? 'badge'),
    $credentialItems
)));
$credentialGridKindClass = count($credentialKinds) === 1
    ? ($credentialKinds[0] === 'certificate' ? ' learner-school-credential-grid--certificates' : ' learner-school-credential-grid--badges')
    : ' learner-school-credential-grid--mixed';
?>
<?php if ($credentialItems === []): ?>
    <div class="learner-school-credential-empty">
        <?= learner_icon('award', 24); ?>
        <p>Nhà trường chưa công bố huy hiệu hoặc chứng chỉ phù hợp ở thời điểm này.</p>
    </div>
<?php else: ?>
    <div class="learner-school-credential-grid<?= $credentialGridKindClass; ?><?= $credentialCompact ? ' learner-school-credential-grid--compact' : ''; ?>">
        <?php foreach ($credentialItems as $credential): ?>
            <?php
            $status = (string) ($credential['status'] ?? 'locked');
            $kind = (string) ($credential['kind'] ?? 'badge');
            $visualState = match (true) {
                in_array($status, ['achieved', 'issued'], true) => 'success',
                in_array($status, ['eligible', 'recommended'], true) => 'progress',
                default => 'locked',
            };
            $visualStateClass = match ($visualState) {
                'success' => 'learner-credential-card--success',
                'progress' => 'learner-credential-card--progress',
                default => 'learner-credential-card--locked',
            };
            $icon = (string) ($credential['icon_key'] ?? 'award');
            if ($icon === 'certificate') {
                $icon = 'graduation-cap';
            }
            $progress = max(0, min(100, (int) ($credential['progress_percent'] ?? 0)));
            $current = $credential['current'] ?? 0;
            $target = $credential['target'] ?? 0;
            $hasCriteria = is_numeric($target) && (float) $target > 0;
            $level = max(1, (int) ($credential['level'] ?? 1));
            $issuerName = trim((string) ($credential['issuer_name'] ?? ''));
            if ($issuerName === '') {
                $issuerName = 'Nhà trường';
            }
            $credentialStateLabel = match ($visualState) {
                'success' => 'Đã đạt',
                'progress' => 'Đang tiến hành',
                default => 'Chưa mở khóa',
            };
            ?>
            <article
                class="learner-credential-card <?= $kind === 'certificate' ? 'learner-credential-card--certificate' : 'learner-credential-card--badge'; ?> <?= $visualStateClass; ?>"
                data-credential-kind="<?= learner_escape($kind); ?>"
                data-credential-status="<?= learner_escape($status); ?>"
            >
                <?php if ($kind === 'certificate'): ?>
                    <div class="learner-credential-card__diploma-frame">
                        <div class="learner-credential-card__diploma-crest" aria-hidden="true">
                            <span class="learner-credential-card__diploma-laurel learner-credential-card__diploma-laurel--left"></span>
                            <span class="learner-credential-card__diploma-shield"><?= learner_icon('graduation-cap', 37); ?></span>
                            <span class="learner-credential-card__diploma-laurel learner-credential-card__diploma-laurel--right"></span>
                        </div>
                        <div class="learner-credential-card__diploma-state">
                            <span aria-hidden="true"></span>
                            <strong><?= learner_escape($credentialStateLabel); ?></strong>
                            <span aria-hidden="true"></span>
                        </div>
                        <h3><?= learner_escape($credential['name'] ?? ''); ?></h3>
                        <div class="learner-credential-card__diploma-rule" aria-hidden="true"><span></span></div>
                        <p class="learner-credential-card__diploma-issuer"><?= learner_escape($issuerName); ?></p>

                        <div class="learner-credential-card__diploma-summary">
                            <?php if ($visualState === 'success'): ?>
                                <div class="learner-credential-card__certificate-seal">
                                    <span aria-hidden="true"><?= learner_icon('shield-check', 18); ?></span>
                                    <strong>Đã xác minh</strong>
                                </div>
                            <?php else: ?>
                                <span>Cấp <?= $level; ?></span>
                                <i aria-hidden="true">•</i>
                                <strong><?= $visualState === 'progress' ? $progress . '% hoàn thành' : ($hasCriteria ? learner_escape($current) . '/' . learner_escape($target) . ' tiêu chí' : $progress . '% tiến độ'); ?></strong>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="learner-credential-card__progress-ring" style="--credential-progress: <?= $progress; ?>%;" role="progressbar" aria-label="Tiến độ huy hiệu <?= learner_escape($credential['name'] ?? ''); ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $progress; ?>">
                        <div class="learner-credential-card__medal" aria-hidden="true">
                            <span class="learner-credential-card__medal-ribbons"></span>
                            <span class="learner-credential-card__medal-face"><?= learner_icon($visualState === 'locked' ? 'lock' : $icon, 30); ?></span>
                        </div>
                    </div>
                    <span class="learner-credential-card__badge-state"><?= learner_escape($credentialStateLabel); ?></span>
                    <h3><?= learner_escape($credential['name'] ?? ''); ?></h3>
                    <div class="learner-credential-card__badge-summary">
                        <?php if ($visualState === 'success'): ?>
                            <span class="learner-credential-card__certificate-seal"><?= learner_icon('shield-check', 17); ?> <strong>Đã xác minh</strong></span>
                        <?php else: ?>
                            <span class="learner-credential-card__level">Cấp <?= $level; ?></span>
                            <i aria-hidden="true">•</i>
                            <span class="learner-credential-card__criteria"><strong><?= $visualState === 'progress' ? $progress . '%' : ($hasCriteria ? learner_escape($current) . '/' . learner_escape($target) : $progress . '%'); ?></strong> <?= $visualState === 'progress' ? 'hoàn thành' : 'tiêu chí'; ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
