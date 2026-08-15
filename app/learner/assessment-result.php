<?php
/** TalentHub Learner - Holland result and history */
require __DIR__ . '/includes/student-data.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/assessment-data.php';

$assessmentId = $_GET['id'] ?? 'holland';
$definition = learner_assessment_definition($assessmentId);
$history = learner_assessment_history(learner_current_student_id(), $assessmentId);
$dimensionContent = learner_assessment_dimension_content();
$latestMock = $history[0] ?? null;
$primaryDimension = (string) ($latestMock['result']['primary_dimension'] ?? '');
$primaryContent = $dimensionContent[$primaryDimension] ?? ['name' => '', 'summary' => '', 'suggestions' => []];
$pageTitle = 'Kết quả Holland';
$currentRoute = '/app/learner/discover.php';
$bootData = $definition ? [
    'student_id' => learner_current_student_id(),
    'assessment_id' => $definition['id'],
    'mock_history' => $history,
    'dimensions' => $dimensionContent,
] : null;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Kết quả và lịch sử bài test Holland RIASEC.">
    <title>Kết quả Holland | TalentHub</title>
    <link rel="stylesheet" href="../../assets/css/home.css"><link rel="stylesheet" href="../../assets/css/learner.css">
</head>
<body class="learner-app learner-page-assessment-result">
    <div class="learner-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        <div class="learner-main">
            <?php include __DIR__ . '/includes/header.php'; ?>
            <main class="learner-content" id="main-content" data-assessment-result-page>
                <nav class="learner-breadcrumbs" aria-label="Đường dẫn"><a href="discover.php">Khám phá năng khiếu</a><span>/</span><a href="assessment.php?id=holland">Holland</a><span>/</span><span>Kết quả</span></nav>
                <?php if (!$definition): ?>
                    <section class="learner-card learner-not-found"><h1>Không tìm thấy bài test</h1><p>Liên kết bài đánh giá không hợp lệ.</p><a class="learner-btn learner-btn--primary" href="discover.php">Quay lại khám phá</a></section>
                <?php else: ?>
                    <section class="learner-card learner-not-found" data-assessment-result-empty<?= $latestMock ? ' hidden' : ''; ?>><h1>Chưa có kết quả</h1><p>Hãy hoàn thành bài test để xem phân tích.</p><a class="learner-btn learner-btn--primary" href="assessment.php?id=holland">Làm bài test</a></section>
                    <div data-assessment-result-content<?= $latestMock ? '' : ' hidden'; ?>>
                    <section class="learner-card learner-result-hero" data-assessment-current-result>
                        <div class="learner-result-hero__code" aria-label="Mã Holland" data-result-code><?= learner_escape($latestMock['result']['code'] ?? ''); ?></div>
                        <div><span class="learner-eyebrow">Kết quả Holland gần nhất</span><h1>Nhóm nổi bật của bạn: <span data-result-primary-name><?= learner_escape($primaryContent['name']); ?></span></h1><p data-result-primary-summary><?= learner_escape($primaryContent['summary']); ?></p><span class="learner-demo-pill" data-result-source><?= $latestMock ? 'Lịch sử mẫu dùng chung' : ''; ?></span></div>
                        <div class="learner-result-hero__actions"><a class="learner-btn learner-btn--primary" href="assessment.php?id=holland">Làm lại bài test</a><a class="learner-btn learner-btn--outline" href="discover.php">Về trang khám phá</a></div>
                    </section>

                    <div class="learner-result-layout">
                        <section class="learner-card learner-result-scores" aria-labelledby="result-scores-title">
                            <div class="learner-section-heading"><div><h2 id="result-scores-title">Điểm sáu nhóm RIASEC</h2><p>Thang điểm chuẩn hóa 0–100</p></div></div>
                            <div data-result-score-list>
                                <?php foreach ($dimensionContent as $dimension => $content): $score = $latestMock['result']['scores'][$dimension] ?? 0; ?>
                                    <div class="learner-result-score" data-result-dimension="<?= $dimension; ?>"><span class="learner-result-score__letter"><?= $dimension; ?></span><div><strong><?= learner_escape($content['name']); ?></strong><div class="learner-progress"><span style="--learner-progress: <?= $score; ?>%;"></span></div></div><b><?= $score; ?></b></div>
                                <?php endforeach; ?>
                            </div>
                        </section>
                        <aside class="learner-card learner-result-guidance"><h2>Gợi ý khám phá tiếp</h2><p>Ưu tiên trải nghiệm trước khi đưa ra quyết định ngành học hoặc nghề nghiệp.</p><ul data-result-suggestions><?php foreach ($primaryContent['suggestions'] as $suggestion): ?><li><?= learner_icon('arrow-right', 15); ?> <?= learner_escape($suggestion); ?></li><?php endforeach; ?></ul><a class="learner-btn learner-btn--outline learner-btn--block" href="ecosystem.php?tab=opportunities">Khám phá cơ hội phù hợp</a></aside>
                    </div>

                    <section class="learner-card learner-result-explanation"><h2>Hiểu mã Holland của bạn</h2><div class="learner-result-dimension-grid" data-result-dimension-cards><?php foreach ($dimensionContent as $dimension => $content): ?><article><span><?= $dimension; ?></span><div><h3><?= learner_escape($content['name']); ?></h3><p><?= learner_escape($content['summary']); ?></p></div></article><?php endforeach; ?></div><div class="learner-data-note"><?= learner_icon('info', 17); ?><p><?= learner_escape($definition['disclaimer']); ?></p></div></section>

                    <section class="learner-card learner-assessment-history" data-assessment-history aria-labelledby="assessment-history-title">
                        <div class="learner-section-heading"><div><h2 id="assessment-history-title">Lịch sử thực hiện</h2><p>Lịch sử mẫu có trên mọi máy; kết quả mới chỉ lưu trên trình duyệt hiện tại.</p></div></div>
                        <div class="learner-assessment-history__list" data-assessment-history-list>
                            <?php foreach ($history as $attempt): ?><article data-history-attempt-id="<?= learner_escape($attempt['id']); ?>"><span class="learner-result-mini-code"><?= learner_escape($attempt['result']['code']); ?></span><div><strong><?= learner_escape($dimensionContent[$attempt['result']['primary_dimension']]['name']); ?></strong><span><?= learner_escape((new DateTimeImmutable($attempt['submitted_at']))->format('d/m/Y · H:i')); ?> · Phiên bản <?= learner_escape($attempt['assessment_version']); ?></span></div><span class="learner-verified-pill"><?= learner_icon('check', 14); ?> Đã hoàn thành</span></article><?php endforeach; ?>
                        </div>
                    </section>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
    <?php if ($bootData): ?><script id="learner-assessment-result-boot" type="application/json"><?= json_encode($bootData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP); ?></script><?php endif; ?>
    <script src="../../assets/js/learner-api.js"></script><script src="../../assets/js/learner.js"></script><script src="../../assets/js/learner-assessment.js"></script>
</body>
</html>
