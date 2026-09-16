<?php
/** Shared CV sections; the caller selects placement without duplicating the Student CV. */
?>
                        <!-- Hồ sơ 4 bài đánh giá năng khiếu & hành vi -->
                        <?php if ($section === 'assessments' && !empty($cv['assessments'])): ?>
                        <section class="cv-side-section">
                            <h2 class="cv-side-title">Định hướng &amp; Năng khiếu</h2>
                            <div class="cv-assessment-grid">
                                <?php foreach ($cv['assessments'] as $a): ?>
                                    <div class="cv-assessment-item">
                                        <span class="cv-assessment-tag"><?= $escapeCv($a['label']); ?></span>
                                        <span class="cv-assessment-code"><?= $escapeCv($a['code']); ?></span>
                                        <?php if ($isApplicationSnapshot && !empty($a['summary'])): ?><p class="cv-meta-sm"><?= $escapeCv($a['summary']); ?></p><?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </section>
                        <?php endif; ?>


                        <!-- Chứng chỉ & Huy hiệu số -->
                        <?php if ($section === 'badges' && !empty($cv['badges'])): ?>
                        <section class="cv-side-section">
                            <h2 class="cv-side-title">Huy hiệu &amp; Chứng thực số</h2>
                            <ul class="cv-badges-list">
                                <?php foreach ($cv['badges'] as $badge): ?>
                                    <li>
                                        <div class="cv-badge-name">★ <?= $escapeCv($badge['name']); ?></div>
                                        <?php if (!empty($badge['description'])): ?><div class="cv-meta-sm"><?= $escapeCv($badge['description']); ?></div><?php endif; ?>
                                        <?php if ($isApplicationSnapshot && !empty($badge['earned_at'])): ?><div class="cv-meta-sm">Ngày ghi nhận: <?= $escapeCv($badge['earned_at']); ?></div><?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </section>
                        <?php endif; ?>


                        <!-- Phong trào & Rèn luyện -->
                        <?php if ($section === 'activities' && (!empty($cv['activities']) || !empty($cv['activity_summary']['total_activities']) || !empty($cv['activity_summary']['total_hours']))): ?>
                        <section class="cv-side-section">
                            <h2 class="cv-side-title">Phong trào &amp; Rèn luyện</h2>
                            <div class="cv-activity-stats">
                                <?php if (!$isApplicationSnapshot || $cv['activity_summary']['total_activities'] !== null): ?>
                                    <strong><?= $escapeCv((string)$cv['activity_summary']['total_activities']); ?> hoạt động xác nhận</strong>
                                <?php endif; ?>
                                <?php if (!empty($cv['activity_summary']['total_hours']) && (float)$cv['activity_summary']['total_hours'] > 0): ?>
                                    <span class="cv-meta">(<?= $escapeCv((string)$cv['activity_summary']['total_hours']); ?>h)</span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($cv['activities'])): ?>
                                <ul class="cv-activity-list">
                                    <?php foreach ($cv['activities'] as $activity): ?>
                                        <li>• <?= $escapeCv($activity['title']); ?><?php if ($isApplicationSnapshot && !empty($activity['date'])): ?> <span class="cv-meta">· <?= $escapeCv($activity['date']); ?></span><?php endif; ?>
                                            <?php if ($isApplicationSnapshot): ?>
                                                <div class="cv-meta cv-activity-details">
                                                    <?php if ($activity['hours'] !== null): ?><span><?= $escapeCv((string)$activity['hours']); ?> giờ</span><?php endif; ?>
                                                    <?php if (!empty($activity['status'])): ?><span><?= $activity['hours'] !== null ? ' · ' : ''; ?><?= $escapeCv(match ($activity['status']) {
                                                        'confirmed'=>'Đã xác nhận', 'pending'=>'Chờ xác nhận',
                                                        'rejected'=>'Không được xác nhận', default=>$activity['status'],
                                                    }); ?></span><?php endif; ?>
                                                    <?php if (!empty($activity['start_at'])): ?><div>Bắt đầu: <?= $escapeCv(substr($activity['start_at'], 0, 10)); ?></div><?php endif; ?>
                                                    <?php if (!empty($activity['end_at'])): ?><div>Kết thúc: <?= $escapeCv(substr($activity['end_at'], 0, 10)); ?></div><?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </section>
                        <?php endif; ?>
                    <?php if ($section === 'passport' && !empty($cv['passport_code'])): ?>
                    <div class="cv-passport-seal">
                        <div class="cv-seal-inner">
                            <div class="cv-seal-header">
                                <span class="cv-seal-emblem">🛡️</span>
                                <span class="cv-seal-title">TALENT PASSPORT</span>
                            </div>
                            <?php if ($cvVerificationUrl !== ''): ?>
                            <div class="cv-seal-qr-wrap">
                                <div class="cv-seal-qr" id="cv-seal-qr" data-qr-url="<?= $escapeCv($cvVerificationUrl); ?>" role="img" aria-label="Mã QR xác thực CV FTalentHub">
                                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&amp;data=<?= urlencode($cvVerificationUrl); ?>" alt="QR xác thực CV" class="cv-seal-qr-img" width="58" height="58">
                                </div>
                            </div>
                            <?php endif; ?>
                            <div class="cv-seal-id">Mã số: <?= $escapeCv($cv['passport_code']); ?></div>
                            <?php if (!empty($cv['is_verified'])): ?><div class="cv-seal-status">✓ CÓ NĂNG LỰC ĐƯỢC XÁC THỰC</div><?php endif; ?>
                            <div class="cv-seal-org">Hệ sinh thái Giáo dục FTalentHub</div>
                        </div>
                    </div>
                    <?php endif; ?>
