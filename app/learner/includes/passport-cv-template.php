<?php
/** @var array $cv Fresh, bounded PassportCvViewModel output. No database or sharing side effects here. */
$escapeCv = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$cvVerificationUrl = $verificationUrl ?? ((function_exists('app_href') ? app_href('/app/learner/shared-profile.php') : '/app/learner/shared-profile.php') . '?code=' . urlencode($cv['passport_code'] ?? 'PASSPORT-TEST-002'));
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= $escapeCv($cv['name']); ?> - CV Talent Passport</title>
    <link rel="stylesheet" href="../../assets/css/learner-passport-cv.css?v=<?= @filemtime(dirname(__DIR__, 3) . '/assets/css/learner-passport-cv.css') ?: time(); ?>">
</head>
<body data-cv-preview>
    <nav class="cv-toolbar" aria-label="Xuất CV">
        <a href="talent-passport.php">← Talent Passport</a>
        <p>CV 2 cột chọn lọc 1 trang A4. Khi lưu PDF: chọn A4, tỷ lệ 100%, bật đồ họa nền, tắt đầu/chân trang của trình duyệt.</p>
        <button type="button" data-cv-export>Lấy dữ liệu mới &amp; xuất PDF</button>
    </nav>
    <p class="cv-error" data-cv-error role="alert" hidden></p>
    <main class="cv-sheet" aria-label="CV một trang A4">
        <div class="cv-content" data-cv-content>
            <div class="cv-grid">
                <!-- CỘT TRÁI (SIDEBAR ~33%) -->
                <aside class="cv-sidebar">
                    <div class="cv-side-top">
                        <div class="cv-profile-block">
                            <div class="cv-avatar">
                                <?php if (!empty($cv['avatar_url'])): ?>
                                    <img src="<?= $escapeCv($cv['avatar_url']); ?>" alt="<?= $escapeCv($cv['name']); ?>">
                                <?php else: ?>
                                    <span><?= $escapeCv($cv['initials'] ?: 'SV'); ?></span>
                                <?php endif; ?>
                            </div>
                            <h1 class="cv-name<?= mb_strlen($cv['name']) > 50 ? ' cv-name-sm' : ''; ?>"><?= $escapeCv($cv['name'] ?: 'Chưa cập nhật họ tên'); ?></h1>
                            <div class="cv-badge-verified">✓ Xác thực TalentHub 360°</div>
                            <?php if (!empty($cv['headline'])): ?>
                                <p class="cv-headline"><?= $escapeCv($cv['headline']); ?></p>
                            <?php elseif (!empty($cv['class'])): ?>
                                <p class="cv-headline"><?= $escapeCv($cv['class']); ?></p>
                            <?php endif; ?>
                        </div>

                        <!-- Thông tin liên hệ -->
                        <section class="cv-side-section">
                            <h2 class="cv-side-title">Liên hệ</h2>
                            <ul class="cv-contact-list">
                                <?php if (!empty($cv['email'])): ?>
                                    <li>
                                        <span class="cv-icon" aria-hidden="true">
                                            <svg class="cv-icon-svg" viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                                        </span>
                                        <span class="cv-contact-val"><?= $escapeCv($cv['email']); ?></span>
                                    </li>
                                <?php endif; ?>
                                <?php if (!empty($cv['phone'])): ?>
                                    <li>
                                        <span class="cv-icon" aria-hidden="true">
                                            <svg class="cv-icon-svg" viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                                        </span>
                                        <span class="cv-contact-val"><?= $escapeCv($cv['phone']); ?></span>
                                    </li>
                                <?php endif; ?>
                                <?php if (!empty($cv['location'])): ?>
                                    <li>
                                        <span class="cv-icon" aria-hidden="true">
                                            <svg class="cv-icon-svg" viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                                        </span>
                                        <span class="cv-contact-val"><?= $escapeCv($cv['location']); ?></span>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </section>

                        <!-- Học vấn -->
                        <?php if (!empty($cv['school']) || !empty($cv['class'])): ?>
                        <section class="cv-side-section">
                            <h2 class="cv-side-title">Học vấn</h2>
                            <div class="cv-edu-item">
                                <?php if (!empty($cv['school'])): ?><div class="cv-strong"><?= $escapeCv($cv['school']); ?></div><?php endif; ?>
                                <?php if (!empty($cv['class'])): ?><div class="cv-meta"><?= $escapeCv($cv['class']); ?></div><?php endif; ?>
                            </div>
                        </section>
                        <?php endif; ?>

                        <!-- Hồ sơ 4 bài đánh giá năng khiếu & hành vi -->
                        <?php if (!empty($cv['assessments'])): ?>
                        <section class="cv-side-section">
                            <h2 class="cv-side-title">Định hướng &amp; Năng khiếu</h2>
                            <div class="cv-assessment-grid">
                                <?php foreach ($cv['assessments'] as $a): ?>
                                    <div class="cv-assessment-item">
                                        <span class="cv-assessment-tag"><?= $escapeCv($a['label']); ?></span>
                                        <span class="cv-assessment-code"><?= $escapeCv($a['code']); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </section>
                        <?php endif; ?>

                        <!-- Chứng chỉ & Huy hiệu số -->
                        <?php if (!empty($cv['badges'])): ?>
                        <section class="cv-side-section">
                            <h2 class="cv-side-title">Huy hiệu &amp; Chứng thực số</h2>
                            <ul class="cv-badges-list">
                                <?php foreach ($cv['badges'] as $badge): ?>
                                    <li>
                                        <div class="cv-badge-name">★ <?= $escapeCv($badge['name']); ?></div>
                                        <?php if (!empty($badge['description'])): ?><div class="cv-meta-sm"><?= $escapeCv($badge['description']); ?></div><?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </section>
                        <?php endif; ?>

                        <!-- Phong trào & Rèn luyện -->
                        <?php if (!empty($cv['activities']) || !empty($cv['activity_summary']['total_activities'])): ?>
                        <section class="cv-side-section">
                            <h2 class="cv-side-title">Phong trào &amp; Rèn luyện</h2>
                            <div class="cv-activity-stats">
                                <strong><?= $escapeCv((string)$cv['activity_summary']['total_activities']); ?> hoạt động xác nhận</strong>
                                <?php if (!empty($cv['activity_summary']['total_hours']) && (float)$cv['activity_summary']['total_hours'] > 0): ?>
                                    <span class="cv-meta">(<?= $escapeCv((string)$cv['activity_summary']['total_hours']); ?>h)</span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($cv['activities'])): ?>
                                <ul class="cv-activity-list">
                                    <?php foreach ($cv['activities'] as $activity): ?>
                                        <li>• <?= $escapeCv($activity['title']); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </section>
                        <?php endif; ?>
                    </div>

                    <!-- Khung bảo chứng Talent Passport ở chân sidebar -->
                    <div class="cv-passport-seal">
                        <div class="cv-seal-inner">
                            <div class="cv-seal-header">
                                <span class="cv-seal-emblem">🛡️</span>
                                <span class="cv-seal-title">TALENT PASSPORT 360°</span>
                            </div>
                            <div class="cv-seal-qr-wrap">
                                <div class="cv-seal-qr" id="cv-seal-qr" data-qr-url="<?= $escapeCv($cvVerificationUrl); ?>" role="img" aria-label="Mã QR xác thực CV Talent Passport">
                                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&amp;data=<?= urlencode($cvVerificationUrl); ?>" alt="QR xác thực CV" class="cv-seal-qr-img" width="58" height="58">
                                </div>
                            </div>
                            <div class="cv-seal-id">Mã số: <?= !empty($cv['passport_code']) ? $escapeCv($cv['passport_code']) : 'PASSPORT-TEST-002'; ?></div>
                            <div class="cv-seal-status">✓ ĐÃ THẨM ĐỊNH NĂNG LỰC SỐ</div>
                            <div class="cv-seal-org">Hệ sinh thái Giáo dục TalentHub</div>
                        </div>
                    </div>
                </aside>

                <!-- CỘT PHẢI (MAIN CONTENT ~67%) -->
                <main class="cv-main">
                    <div class="cv-main-top">
                        <!-- Tóm tắt mục tiêu & năng lực -->
                        <section class="cv-main-section">
                            <h2 class="cv-section-title">Mục tiêu &amp; Tóm tắt năng lực</h2>
                            <div class="cv-summary-card">
                                <p><?= $escapeCv($cv['strengths_summary']); ?></p>
                            </div>
                        </section>

                        <!-- Kỹ năng cốt lõi đã xác thực -->
                        <?php if (!empty($cv['skills'])): ?>
                        <section class="cv-main-section">
                            <h2 class="cv-section-title">Bảng năng lực cốt lõi (Giảng viên xác thực)</h2>
                            <div class="cv-skills-grid">
                                <?php foreach ($cv['skills'] as $skill): ?>
                                    <div class="cv-skill-row">
                                        <div class="cv-skill-header">
                                            <span class="cv-skill-name"><?= $escapeCv($skill['name']); ?></span>
                                            <?php if ($skill['score'] !== null): ?>
                                                <span class="cv-skill-score"><?= $escapeCv((string)$skill['score']); ?>%</span>
                                            <?php else: ?>
                                                <span class="cv-skill-score cv-skill-score-verified">Xác thực</span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($skill['score'] !== null): ?>
                                            <div class="cv-skill-bar-bg">
                                                <div class="cv-skill-bar-fill" style="width: <?= max(5, min(100, (int)$skill['score'])); ?>%"></div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </section>
                        <?php endif; ?>

                        <!-- Đề án đổi mới sáng tạo tiêu biểu -->
                        <?php if (!empty($cv['projects'])): ?>
                        <section class="cv-main-section">
                            <h2 class="cv-section-title">Đề án đổi mới sáng tạo &amp; Tham gia</h2>
                            <?php foreach ($cv['projects'] as $project): ?>
                                <article class="cv-project-card">
                                    <div class="cv-card-header">
                                        <h3 class="cv-project-title"><?= $escapeCv($project['title']); ?></h3>
                                        <span class="cv-badge-status"><?= $escapeCv($project['status_label']); ?></span>
                                    </div>
                                    <div class="cv-project-meta">
                                        <?php if (!empty($project['category'])): ?><span class="cv-project-cat"><?= $escapeCv($project['category']); ?></span><?php endif; ?>
                                        <?php if (!empty($project['role'])): ?><span class="cv-role-label"> · Vai trò: <?= $escapeCv($project['role']); ?></span><?php endif; ?>
                                        <?php if (!empty($project['mentor'])): ?><span class="cv-mentor-label"> · Hướng dẫn: <?= $escapeCv($project['mentor']); ?></span><?php endif; ?>
                                    </div>
                                    <?php if (!empty($project['contribution'])): ?>
                                        <div class="cv-bullet-point"><?= $escapeCv($project['contribution']); ?></div>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        </section>
                        <?php endif; ?>

                        <!-- Thực tập nếu có -->
                        <?php if (!empty($cv['internships'])): ?>
                        <section class="cv-main-section">
                            <h2 class="cv-section-title">Kinh nghiệm thực tập</h2>
                            <?php foreach ($cv['internships'] as $internship): ?>
                                <article class="cv-internship-card">
                                    <div class="cv-card-header">
                                        <h3 class="cv-project-title"><?= $escapeCv($internship['title']); ?></h3>
                                        <span class="cv-badge-status"><?= $escapeCv($internship['status_label']); ?></span>
                                    </div>
                                    <div class="cv-role-label"><?= $escapeCv($internship['enterprise']); ?></div>
                                    <?php if (!empty($internship['details'])): ?>
                                        <div class="cv-meta"><?= $escapeCv($internship['details']); ?></div>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        </section>
                        <?php endif; ?>

                        <!-- Nhận xét chứng thực từ Giảng viên -->
                        <?php if (!empty($cv['evaluations'])): ?>
                        <section class="cv-main-section">
                            <h2 class="cv-section-title">Nhận xét chứng thực của Giảng viên hướng dẫn</h2>
                            <?php foreach ($cv['evaluations'] as $evaluation): ?>
                                <blockquote class="cv-quote-card">
                                    <p class="cv-quote-text">“<?= $escapeCv($evaluation['comment']); ?>”</p>
                                    <footer class="cv-quote-footer">
                                        — <?= $escapeCv(implode(' · ', array_filter([$evaluation['teacher'], $evaluation['date']]))); ?> · <em>Giảng viên hướng dẫn</em>
                                    </footer>
                                </blockquote>
                            <?php endforeach; ?>
                        </section>
                        <?php endif; ?>
                    </div>

                    <!-- Footer nhỏ gọn -->
                    <footer class="cv-footer">
                        <span>Dữ liệu số xác thực từ Hệ sinh thái TalentHub · Thời gian xuất: <?= $escapeCv($cv['generated_at']); ?>.</span>
                        <?php if ($cv['omitted']): ?><span> (Bản tóm lược chuẩn A4; còn <?= $escapeCv((string)$cv['omitted']); ?> mục trên hồ sơ gốc).</span><?php endif; ?>
                    </footer>
                </main>
            </div>
        </div>
    </main>
    <script src="../../assets/vendor/qrcodejs/qrcode.min.js"></script>
    <script src="../../assets/js/learner-passport-cv.js"></script>
</body>
</html>
