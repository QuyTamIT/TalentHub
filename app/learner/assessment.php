<?php
/** TalentHub Learner - Holland assessment runner */
require __DIR__ . '/includes/student-data.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/assessment-data.php';

$assessmentId = $_GET['id'] ?? 'holland';
$definition = learner_assessment_definition($assessmentId);
$questions = learner_assessment_questions($assessmentId);
$pageTitle = 'Bài test Holland';
$currentRoute = '/app/learner/discover.php';
$bootData = $definition ? [
    'student' => ['id' => 'student-demo-001', 'name' => $student['name']],
    'definition' => $definition,
    'questions' => $questions,
    'result_url' => 'assessment-result.php?id=' . rawurlencode($assessmentId),
] : null;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Thực hiện bài test Holland RIASEC để khám phá nhóm sở thích nghề nghiệp.">
    <title><?= learner_escape($definition['short_name'] ?? 'Không tìm thấy bài test'); ?> | TalentHub</title>
    <link rel="stylesheet" href="../../assets/css/home.css">
    <link rel="stylesheet" href="../../assets/css/learner.css">
</head>
<body class="learner-app learner-page-assessment">
    <div class="learner-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        <div class="learner-main">
            <?php include __DIR__ . '/includes/header.php'; ?>
            <main class="learner-content" id="main-content">
                <nav class="learner-breadcrumbs" aria-label="Đường dẫn">
                    <a href="discover.php">Khám phá năng khiếu</a><span aria-hidden="true">/</span><span><?= learner_escape($definition['short_name'] ?? 'Bài test'); ?></span>
                </nav>

                <?php if (!$definition): ?>
                    <section class="learner-card learner-not-found">
                        <?= learner_icon('info', 34); ?><h1>Không tìm thấy bài test</h1>
                        <p>Bài đánh giá chưa được công bố hoặc liên kết không hợp lệ.</p>
                        <a class="learner-btn learner-btn--primary" href="discover.php">Quay lại khám phá</a>
                    </section>
                <?php else: ?>
                    <div class="learner-assessment-shell" data-assessment-runner>
                        <section class="learner-card learner-assessment-state" data-assessment-loading>
                            <span class="learner-assessment-spinner" aria-hidden="true"></span>
                            <h1>Đang chuẩn bị bài test...</h1>
                            <p>TalentHub đang đọc bộ câu hỏi và bản nháp trên trình duyệt này.</p>
                        </section>

                        <section class="learner-card learner-assessment-state learner-assessment-state--error" data-assessment-error hidden>
                            <?= learner_icon('info', 32); ?><h1>Không thể mở bài test</h1>
                            <p data-assessment-error-message>Dữ liệu bài test chưa hợp lệ. Vui lòng thử lại.</p>
                            <button class="learner-btn learner-btn--primary" type="button" data-assessment-retry>Thử lại</button>
                        </section>

                        <section class="learner-card learner-assessment-intro" data-assessment-intro hidden>
                            <div class="learner-assessment-intro__visual">
                                <span><?= learner_icon('compass', 38); ?></span>
                                <div class="learner-assessment-riasec" aria-label="Sáu nhóm Holland">
                                    <?php foreach (str_split('RIASEC') as $letter): ?><b><?= $letter; ?></b><?php endforeach; ?>
                                </div>
                            </div>
                            <div class="learner-assessment-intro__content">
                                <span class="learner-eyebrow">Bài đánh giá định hướng</span>
                                <h1><?= learner_escape($definition['name']); ?></h1>
                                <p><?= learner_escape($definition['description']); ?></p>
                                <div class="learner-assessment-intro__facts">
                                    <span><?= learner_icon('file-text', 18); ?><strong><?= learner_escape($definition['question_count']); ?> câu</strong></span>
                                    <span><?= learner_icon('clock', 18); ?><strong><?= learner_escape($definition['duration_minutes']); ?> phút</strong></span>
                                    <span><?= learner_icon('check', 18); ?><strong>Tự lưu bản nháp</strong></span>
                                </div>
                                <div class="learner-data-note"><?= learner_icon('info', 17); ?><p><?= learner_escape($definition['disclaimer']); ?></p></div>
                                <div class="learner-assessment-intro__actions">
                                    <button class="learner-btn learner-btn--primary" type="button" data-assessment-start>Bắt đầu bài test <?= learner_icon('arrow-right', 17); ?></button>
                                    <a class="learner-btn learner-btn--secondary" href="assessment-result.php?id=holland">Xem lịch sử kết quả</a>
                                </div>
                                <button class="learner-text-button" type="button" data-assessment-resume hidden>Tiếp tục bản nháp hiện tại</button>
                            </div>
                        </section>

                        <section class="learner-assessment-runner" data-assessment-active hidden>
                            <header class="learner-card learner-assessment-runner__header">
                                <div>
                                    <span class="learner-eyebrow">Holland RIASEC · Phiên bản <?= learner_escape($definition['version']); ?></span>
                                    <h1 id="assessment-question-heading" tabindex="-1">Câu <span data-assessment-position>1</span>/<?= count($questions); ?></h1>
                                </div>
                                <div class="learner-assessment-timer" role="timer" aria-live="off">
                                    <?= learner_icon('clock', 18); ?><span>Còn lại</span><strong data-assessment-timer>12:00</strong>
                                </div>
                                <div class="learner-progress learner-assessment-overall-progress" role="progressbar" aria-label="Tiến độ bài test" aria-valuemin="0" aria-valuemax="<?= count($questions); ?>" aria-valuenow="0" data-assessment-progress>
                                    <span style="--learner-progress: 0%;"></span>
                                </div>
                                <p class="learner-assessment-save-status" role="status" aria-live="polite" data-assessment-save-status>Đã sẵn sàng.</p>
                            </header>

                            <div class="learner-assessment-runner__layout">
                                <section class="learner-card learner-question-card">
                                    <p class="learner-question-card__hint">Mức độ phát biểu dưới đây giống với bạn</p>
                                    <h2 data-assessment-question></h2>
                                    <fieldset class="learner-likert-options" data-assessment-options>
                                        <legend class="learner-visually-hidden">Chọn một mức độ phù hợp nhất</legend>
                                    </fieldset>
                                    <p class="learner-form-error" role="alert" hidden data-assessment-question-error>Hãy chọn một phương án trước khi tiếp tục.</p>
                                    <div class="learner-question-card__actions">
                                        <button class="learner-btn learner-btn--secondary" type="button" data-assessment-previous><?= learner_icon('arrow-left', 17); ?> Câu trước</button>
                                        <button class="learner-btn learner-btn--primary" type="button" data-assessment-next>Câu tiếp <?= learner_icon('arrow-right', 17); ?></button>
                                        <button class="learner-btn learner-btn--primary" type="button" data-open-modal="learner-assessment-submit-modal" data-assessment-open-submit hidden>Kiểm tra &amp; nộp bài</button>
                                    </div>
                                </section>

                                <aside class="learner-card learner-question-navigator" aria-labelledby="question-navigator-title">
                                    <div class="learner-section-heading"><h2 id="question-navigator-title">Danh sách câu hỏi</h2><span><b data-assessment-answered-count>0</b>/<?= count($questions); ?> đã trả lời</span></div>
                                    <div class="learner-question-navigator__grid" data-assessment-navigator>
                                        <?php foreach ($questions as $index => $question): ?>
                                            <button type="button" data-question-index="<?= $index; ?>" aria-label="Đi tới câu <?= $index + 1; ?>"><?= $index + 1; ?></button>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="learner-question-navigator__legend"><span><i class="is-current"></i> Đang xem</span><span><i class="is-answered"></i> Đã trả lời</span></div>
                                </aside>
                            </div>
                        </section>

                        <section class="learner-card learner-assessment-state learner-assessment-state--expired" data-assessment-expired hidden>
                            <?= learner_icon('clock', 34); ?><h1>Phiên làm bài đã hết thời gian</h1>
                            <p>Bản trả lời đã được giữ trên trình duyệt này nhưng không thể nộp. Bạn có thể bắt đầu một phiên mới.</p>
                            <button class="learner-btn learner-btn--primary" type="button" data-assessment-restart>Bắt đầu lại</button>
                        </section>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <?php if ($definition): ?>
        <div class="learner-modal" id="learner-assessment-submit-modal" hidden data-assessment-submit-modal>
            <button class="learner-modal__backdrop" type="button" data-close-modal aria-label="Đóng xác nhận nộp bài"></button>
            <section class="learner-modal__dialog learner-modal__dialog--compact" role="dialog" aria-modal="true" aria-labelledby="assessment-submit-title">
                <div class="learner-modal__header"><div><span class="learner-modal__eyebrow">Xác nhận hoàn thành</span><h2 id="assessment-submit-title">Nộp bài Holland?</h2></div><button class="learner-icon-button" type="button" data-close-modal aria-label="Đóng"><?= learner_icon('x', 21); ?></button></div>
                <p class="learner-modal__copy" data-assessment-submit-copy>Hãy kiểm tra lại câu trả lời trước khi nộp.</p>
                <div class="learner-assessment-submit-summary"><span>Đã trả lời<strong data-submit-answered>0/<?= count($questions); ?></strong></span><span>Chưa trả lời<strong data-submit-unanswered><?= count($questions); ?></strong></span></div>
                <p class="learner-form-error" role="alert" hidden data-assessment-submit-error>Bạn cần trả lời tất cả câu hỏi trước khi nộp.</p>
                <div class="learner-modal__actions"><button class="learner-btn learner-btn--secondary" type="button" data-close-modal>Kiểm tra lại</button><button class="learner-btn learner-btn--primary" type="button" data-assessment-submit>Xác nhận nộp bài</button></div>
            </section>
        </div>
        <script id="learner-assessment-boot" type="application/json"><?= json_encode($bootData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP); ?></script>
    <?php endif; ?>
    <script src="../../assets/js/learner.js"></script>
    <script src="../../assets/js/learner-assessment.js"></script>
</body>
</html>
