<?php
declare(strict_types=1);

// Test verification for Compact Accepted Application Card

$mockAcceptedApp = [
    'id' => 'app-1',
    'opportunity_id' => 'post-1',
    'opportunity_type' => 'internship',
    'partner_name' => 'FPT Software',
    'partner_initials' => 'FS',
    'title' => 'Frontend Developer (ReactJS / Vue.js)',
    'status' => 'accepted',
    'status_label' => 'Đã nhận',
    'submitted_at_formatted' => '16:13 · 10/09/2026',
    'updated_at_formatted' => '16:46 · 12/09/2026',
    'message' => 'test',
    'can_withdraw' => false,
    'pipeline' => [
        ['id' => 'apply', 'label' => 'Đã nộp hồ sơ', 'desc' => 'Hồ sơ đã chuyển tới doanh nghiệp', 'state' => 'complete', 'date' => '16:13 · 10/09/2026'],
        ['id' => 'review', 'label' => 'Tiếp nhận & Xem xét', 'desc' => 'Doanh nghiệp đã tiếp nhận', 'state' => 'complete', 'date' => null],
        ['id' => 'interview', 'label' => 'Phỏng vấn', 'desc' => 'Trao đổi chuyên môn & lịch thực tập', 'state' => 'complete', 'date' => null],
        ['id' => 'decision', 'label' => 'Kết quả tiếp nhận', 'desc' => 'Chúc mừng! Bạn đã trúng tuyển thực tập', 'state' => 'complete', 'date' => '16:46 · 12/09/2026'],
    ],
];

$mockSubmittedApp = [
    'id' => 'app-2',
    'opportunity_id' => 'post-2',
    'opportunity_type' => 'internship',
    'partner_name' => 'Viettel Cyber Security',
    'partner_initials' => 'VCS',
    'title' => 'Security Intern',
    'status' => 'submitted',
    'status_label' => 'Đã nộp',
    'submitted_at_formatted' => '09:00 · 14/09/2026',
    'updated_at_formatted' => '09:00 · 14/09/2026',
    'message' => 'Mong muon thuc tap tai VCS',
    'can_withdraw' => true,
    'pipeline' => [
        ['id' => 'apply', 'label' => 'Đã nộp hồ sơ', 'desc' => 'Hồ sơ đã chuyển tới doanh nghiệp', 'state' => 'complete', 'date' => '09:00 · 14/09/2026'],
        ['id' => 'review', 'label' => 'Tiếp nhận & Xem xét', 'desc' => 'Doanh nghiệp đang xem xét', 'state' => 'current', 'date' => null],
        ['id' => 'interview', 'label' => 'Phỏng vấn', 'desc' => 'Chờ xếp lịch', 'state' => 'upcoming', 'date' => null],
        ['id' => 'decision', 'label' => 'Kết quả', 'desc' => 'Chờ kết quả', 'state' => 'upcoming', 'date' => null],
    ],
];

function learner_escape($str): string {
    return htmlspecialchars((string) $str, ENT_QUOTES, 'UTF-8');
}

function learner_icon(string $name, int $size = 20): string {
    return "<svg data-icon=\"{$name}\" width=\"{$size}\" height=\"{$size}\"></svg>";
}

// Function to render application card extracted from ecosystem.php logic
function renderApplicationCard(array $app): string {
    ob_start();

    $statusNote = '';
    $appStatus = (string) ($app['status'] ?? 'submitted');
    if (in_array($appStatus, ['submitted', 'applied'], true)) {
        $statusNote = 'Hồ sơ đã được chuyển tới bộ phận tuyển dụng doanh nghiệp. Doanh nghiệp thường xem xét và phản hồi trong 3 - 5 ngày làm việc.';
    } elseif ($appStatus === 'reviewing') {
        $statusNote = 'Doanh nghiệp đang xem xét năng lực và dự án mẫu trong hồ sơ của bạn.';
    } elseif ($appStatus === 'interview') {
        $statusNote = 'Chúc mừng! Bạn đã được chọn vào vòng phỏng vấn. Hãy kiểm tra thông báo và email để nắm lịch chi tiết.';
    } elseif (in_array($appStatus, ['accepted', 'hired'], true)) {
        $statusNote = 'Chúc mừng bạn đã trúng tuyển thực tập! Nhà tuyển dụng đã duyệt tiếp nhận và sẽ sớm liên hệ hướng dẫn nhận việc.';
    } elseif ($appStatus === 'declined') {
        $statusNote = 'Rất tiếc hồ sơ chưa phù hợp trong đợt này. Bạn có thể trau dồi thêm và ứng tuyển vị trí khác.';
    } elseif ($appStatus === 'withdrawn') {
        $statusNote = 'Bạn đã chủ động rút hồ sơ khỏi vị trí tuyển dụng này.';
    }

    $isAccepted = in_array($appStatus, ['accepted', 'hired'], true);
    $acceptedDate = '';
    if ($isAccepted) {
        if (!empty($app['pipeline'])) {
            $pipelineSteps = $app['pipeline'];
            $lastStep = end($pipelineSteps);
            $acceptedDate = $lastStep['date'] ?? '';
        }
        if (empty($acceptedDate)) {
            $acceptedDate = $app['updated_at_formatted'] ?? '';
        }
    }
    ?>
    <article class="learner-application-card" data-app-card data-app-id="<?= learner_escape($app['id']); ?>">
        <div class="learner-application-card__header">
            <div class="learner-application-card__identity">
                <div class="learner-application-card__avatar" aria-hidden="true">
                    <?= learner_escape($app['partner_initials'] ?? 'DN'); ?>
                </div>
                <div class="learner-application-card__details">
                    <div class="learner-application-card__meta-top">
                        <span class="learner-status-badge is-<?= learner_escape($app['status']); ?>" data-app-status-badge>
                            <span class="dot" aria-hidden="true"></span>
                            <?= learner_escape($app['status_label']); ?>
                        </span>
                        <span class="learner-work-type-badge">Thực tập</span>
                    </div>
                    <h3 class="learner-application-card__title">
                        <a href="opportunity.php?type=<?= learner_escape($app['opportunity_type']); ?>&amp;id=<?= learner_escape($app['opportunity_id']); ?>">
                            <?= learner_escape($app['title']); ?>
                        </a>
                    </h3>
                    <div class="learner-application-card__submeta">
                        <span class="learner-application-card__partner-name">
                            <?= learner_icon('building', 14); ?>
                            <?= learner_escape($app['partner_name']); ?>
                        </span>
                        <span>·</span>
                        <span class="learner-application-card__date">
                            Nộp lúc <?= learner_escape($app['submitted_at_formatted'] ?? $app['submitted_at'] ?? 'Chưa xác định'); ?>
                        </span>
                    </div>
                </div>
            </div>
            <div class="learner-application-card__actions">
                <a class="learner-btn learner-btn--view-app" href="opportunity.php?type=<?= learner_escape($app['opportunity_type']); ?>&amp;id=<?= learner_escape($app['opportunity_id']); ?>">
                    Chi tiết vị trí <?= learner_icon('arrow-right', 14); ?>
                </a>
                <?php if (!empty($app['can_withdraw'])): ?>
                    <button class="learner-btn--danger-outline" type="button" data-withdraw-btn data-withdraw-id="<?= learner_escape($app['id']); ?>">
                        Rút hồ sơ
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($isAccepted): ?>
            <div class="learner-app-accepted-banner" role="status">
                <div class="learner-app-accepted-banner__icon" aria-hidden="true">
                    <?= learner_icon('check', 20); ?>
                </div>
                <div class="learner-app-accepted-banner__body">
                    <div class="learner-app-accepted-banner__header">
                        <h4 class="learner-app-accepted-banner__title">Trúng tuyển &amp; Được tiếp nhận</h4>
                        <?php if (!empty($acceptedDate)): ?>
                            <span class="learner-app-accepted-banner__time">Hoàn tất lúc <?= learner_escape($acceptedDate); ?></span>
                        <?php endif; ?>
                    </div>
                    <p class="learner-app-accepted-banner__message">
                        Chúc mừng bạn đã trúng tuyển thực tập! Doanh nghiệp đã duyệt tiếp nhận hồ sơ và sẽ sớm liên hệ hướng dẫn nhận việc qua email hoặc số điện thoại.
                    </p>
                    <?php if (!empty($app['message'])): ?>
                        <div class="learner-app-accepted-banner__user-note">
                            <?= learner_icon('mail', 13); ?>
                            <span>Lời nhắn gửi kèm của bạn: <em>“<?= learner_escape($app['message']); ?>”</em></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="learner-app-stepper" aria-label="Tiến trình xét duyệt">
                <div class="learner-app-stepper__track">
                    <?php
                    $steps = !empty($app['pipeline']) ? $app['pipeline'] : [];
                    foreach ($steps as $idx => $step):
                        $sState = $step['state'] ?? 'upcoming';
                    ?>
                        <div class="learner-app-step is-<?= learner_escape($sState); ?>" data-step-id="<?= learner_escape($step['id']); ?>">
                            <div class="learner-app-step__node" aria-hidden="true">
                                <?= $idx + 1; ?>
                            </div>
                            <div class="learner-app-step__content">
                                <span class="learner-app-step__title"><?= learner_escape($step['label']); ?></span>
                                <span class="learner-app-step__desc"><?= learner_escape($step['desc']); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if (!empty($app['message'])): ?>
                <div class="learner-app-message-preview">
                    <span class="learner-app-message-label"><?= learner_icon('mail', 14); ?> Lời nhắn gửi kèm của bạn:</span>
                    <p class="learner-app-message-text">“<?= learner_escape($app['message']); ?>”</p>
                </div>
            <?php endif; ?>

            <?php if (!empty($statusNote)): ?>
                <div class="learner-app-status-note">
                    <?= learner_icon('info', 16); ?>
                    <div><?= learner_escape($statusNote); ?></div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </article>
    <?php
    return ob_get_clean();
}

// TEST 1: Accepted app renders compact banner
$acceptedHtml = renderApplicationCard($mockAcceptedApp);
assert(str_contains($acceptedHtml, 'learner-app-accepted-banner'), 'FAIL: Accepted app must render learner-app-accepted-banner');
assert(str_contains($acceptedHtml, 'Trúng tuyển &amp; Được tiếp nhận'), 'FAIL: Accepted app must render title');
assert(str_contains($acceptedHtml, 'Hoàn tất lúc 16:46 · 12/09/2026'), 'FAIL: Accepted app must render completion date');
assert(str_contains($acceptedHtml, 'Lời nhắn gửi kèm của bạn: <em>“test”</em>'), 'FAIL: Accepted app must render attached user message');
assert(!str_contains($acceptedHtml, 'learner-app-stepper'), 'FAIL: Accepted app must NOT render bulky stepper');
assert(!str_contains($acceptedHtml, 'learner-app-status-note'), 'FAIL: Accepted app must NOT render redundant status note');
echo "PASS: Test 1 - Accepted application renders compact banner without stepper.\n";

// TEST 2: Submitted app renders stepper
$submittedHtml = renderApplicationCard($mockSubmittedApp);
assert(!str_contains($submittedHtml, 'learner-app-accepted-banner'), 'FAIL: Submitted app must NOT render accepted banner');
assert(str_contains($submittedHtml, 'learner-app-stepper'), 'FAIL: Submitted app must render stepper');
assert(str_contains($submittedHtml, 'learner-app-status-note'), 'FAIL: Submitted app must render status note');
echo "PASS: Test 2 - Submitted application retains stepper and status note.\n";

echo "ALL TESTS PASSED SUCCESSFULLY!\n";
