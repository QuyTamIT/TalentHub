<?php
/** TalentHub Learner - Competency evaluation */
require __DIR__ . '/includes/student-data.php';
require_once __DIR__ . '/includes/icons.php';

$pageTitle = 'Đánh giá năng lực';
$currentRoute = '/app/learner/evaluation.php';
$evaluationSourceState = 'ready';

if ($isDatabaseMode) {
    try {
        $studentIdForEval = learner_current_student_id();
        $publishedEvaluations = learner_repository_factory()->assessment()->publishedEvaluationsForStudent($studentIdForEval);

        $evaluationTerms = [];
        $defaultEvaluationTerm = '';

        foreach ($publishedEvaluations as $eval) {
            $evaluationId = trim((string) ($eval['id'] ?? ''));
            if ($evaluationId === '') {
                continue;
            }

            $publishedAt = trim((string) ($eval['published_at'] ?? ''));
            $publishedDate = $publishedAt;
            if ($publishedAt !== '') {
                $parsedPublishedAt = date_create_immutable($publishedAt);
                $publishedDate = $parsedPublishedAt === false ? $publishedAt : $parsedPublishedAt->format('d/m/Y');
            }
            $activityTitle = trim((string) ($eval['activity_title'] ?? ''));
            $evaluationLabel = $activityTitle !== '' ? $activityTitle : 'Đánh giá';
            if ($publishedDate !== '') {
                $evaluationLabel .= ' · ' . $publishedDate;
            }

            $criteria = [];
            $toneMap = ['primary', 'secondary', 'secondary', 'primary', 'success', 'warning'];
            foreach ($eval['scores'] ?? [] as $idx => $score) {
                $criteria[] = [
                    'name' => (string) ($score['criteria_name'] ?? 'Tiêu chí'),
                    'score' => (float) ($score['score'] ?? 0),
                    'max' => (float) ($score['max_score'] ?? 100),
                    'tone' => $toneMap[$idx % count($toneMap)],
                ];
            }

            $overallScoreVal = is_numeric($eval['overall_score'] ?? null) ? (float) $eval['overall_score'] : null;
            $classification = \TalentHub\Support\GradeClassifier::getClassification($overallScoreVal);
            $ranking = \TalentHub\Support\GradeClassifier::getRankingPercentile($overallScoreVal);
            $tone = \TalentHub\Support\GradeClassifier::getBadgeTone($overallScoreVal);

            $evaluationTerms[$evaluationId] = [
                'label' => $evaluationLabel,
                'status' => 'Đã công bố',
                'evaluation' => [
                    'criteria' => $criteria,
                    'total' => $overallScoreVal !== null ? (string) $overallScoreVal : 'Chưa có dữ liệu',
                    'classification' => $classification,
                    'ranking' => $ranking,
                    'tone' => $tone,
                    'comment' => (string) ($eval['comment'] ?? 'Chưa có nhận xét'),
                    'reviewer' => (string) ($eval['reviewer_name'] ?? 'Giáo viên'),
                ],
            ];

            if ($defaultEvaluationTerm === '') {
                $defaultEvaluationTerm = $evaluationId;
            }
        }

        $evaluationSourceState = $evaluationTerms === [] ? 'empty' : 'ready';
    } catch (\Throwable) {
        $evaluationSourceState = 'source-error';
        $evaluationTerms = [];
        $defaultEvaluationTerm = '';
    }
} elseif ($evaluationTerms === []) {
    $evaluationSourceState = 'empty';
}

$currentTerm = $evaluationTerms[$defaultEvaluationTerm]
    ?? ['label' => 'Chưa có dữ liệu', 'status' => 'Chưa có dữ liệu', 'evaluation' => null];
$currentEvaluation = $currentTerm['evaluation'] ?? null;
$hasEvaluation = $evaluationSourceState === 'ready' && is_array($currentEvaluation);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Theo dõi điểm đánh giá năng lực và nhận xét từ giáo viên, huấn luyện viên trên TalentHub.">
    <title>Đánh giá năng lực | TalentHub</title>
    <link rel="stylesheet" href="../../assets/css/home.css">
    <link rel="stylesheet" href="../../assets/css/global.css">
    <link rel="stylesheet" href="../../assets/css/brand-component.css">
    <link rel="stylesheet" href="../../assets/css/polish.css">
    <link rel="stylesheet" href="../../assets/css/learner.css">
    <link rel="stylesheet" href="../../assets/css/typeui-selects.css">
</head>
<body class="learner-app learner-page-evaluation">
    <div class="learner-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <div class="learner-main">
            <?php include __DIR__ . '/includes/header.php'; ?>

            <main class="learner-content" id="main-content">
                <?php
                $learnerPageBanner = [
                    'id' => 'learner-evaluation-page-title',
                    'eyebrow' => 'Theo dõi tiến bộ',
                    'title' => 'Đánh giá năng lực',
                    'description' => 'Xem điểm số và nhận xét từ giáo viên, huấn luyện viên.',
                    'icon' => 'clipboard',
                ];
                include __DIR__ . '/includes/page-banner.php';
                ?>
                <div class="learner-evaluation-heading learner-evaluation-heading--actions">
                    <div class="learner-evaluation-heading__actions">
                        <label class="learner-term-select typeui-select-shell" for="learner-evaluation-term">
                            <?= learner_icon('calendar', 18); ?>
                            <span class="learner-visually-hidden">Chọn học kỳ</span>
                            <select id="learner-evaluation-term" name="term" class="typeui-select typeui-select--bare">
                                <?php if ($evaluationTerms === []): ?>
                                    <option value="">Chưa có dữ liệu</option>
                                <?php endif; ?>
                                <?php foreach ($evaluationTerms as $termId => $term): ?>
                                    <option value="<?= learner_escape($termId); ?>" <?= $termId === $defaultEvaluationTerm ? 'selected' : ''; ?>>
                                        <?= learner_escape($term['label']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <span class="learner-publication-status" data-evaluation-status data-state="<?= $hasEvaluation ? 'published' : 'empty'; ?>" role="status" aria-live="polite" aria-atomic="true">
                            <span aria-hidden="true"></span><?= learner_escape($currentTerm['status']); ?>
                        </span>
                    </div>
                </div>

                <div class="learner-evaluation-grid">
                    <section class="learner-card learner-evaluation-criteria" data-evaluation-content aria-labelledby="learner-criteria-title" <?= $hasEvaluation ? '' : 'hidden'; ?>>
                        <h2 id="learner-criteria-title">Bảng tiêu chí</h2>
                        <div class="learner-evaluation-criteria__list" data-evaluation-criteria>
                            <?php foreach (($currentEvaluation['criteria'] ?? []) as $criterion): ?>
                                <?php
                                $maximum = (float) $criterion['max'];
                                $percentage = $maximum > 0
                                    ? max(0.0, min(100.0, (float) $criterion['score'] / $maximum * 100))
                                    : 0.0;
                                ?>
                                <article class="learner-evaluation-criterion" data-evaluation-criterion="">
                                    <div class="learner-evaluation-criterion__heading">
                                        <span><?= learner_escape($criterion['name']); ?></span>
                                        <strong><?= learner_escape($criterion['score']); ?>/<?= learner_escape($criterion['max']); ?></strong>
                                    </div>
                                    <div
                                        class="learner-progress"
                                        role="progressbar"
                                        aria-label="<?= learner_escape($criterion['name']); ?>"
                                        aria-valuemin="0"
                                        aria-valuemax="<?= learner_escape($criterion['max']); ?>"
                                        aria-valuenow="<?= learner_escape($criterion['score']); ?>"
                                    >
                                        <span class="learner-progress--<?= learner_escape($criterion['tone']); ?>" style="--learner-progress: <?= learner_escape($percentage); ?>%;"></span>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>

                        <article class="learner-evaluation-comment">
                            <div class="learner-evaluation-comment__title">
                                <?= learner_icon('message-circle', 20); ?>
                                <strong>Nhận xét gần nhất từ HLV</strong>
                            </div>
                            <blockquote data-evaluation-comment><?= learner_escape($currentEvaluation['comment'] ?? 'Chưa có dữ liệu'); ?></blockquote>
                            <p>— <span data-evaluation-reviewer><?= learner_escape($currentEvaluation['reviewer'] ?? 'Chưa có dữ liệu'); ?></span></p>
                        </article>
                    </section>

                    <aside class="learner-card learner-evaluation-score" data-evaluation-summary aria-labelledby="learner-total-title" <?= $hasEvaluation ? '' : 'hidden'; ?>>
                        <p id="learner-total-title">Tổng điểm</p>
                        <strong data-evaluation-total><?= learner_escape($currentEvaluation['total'] ?? 'Chưa có dữ liệu'); ?></strong>
                        <span>/ 100</span>
                        <div class="learner-evaluation-classification learner-evaluation-classification--<?= learner_escape($currentEvaluation['tone'] ?? 'good'); ?>">
                            <?= learner_icon('star', 21); ?>
                            <strong data-evaluation-classification><?= learner_escape($currentEvaluation['classification'] ?? 'Chưa có dữ liệu'); ?></strong>
                        </div>
                        <div class="learner-evaluation-ranking">
                            <?= learner_icon('chart', 22); ?>
                            <span data-evaluation-ranking><?= learner_escape($currentEvaluation['ranking'] ?? 'Chưa có dữ liệu'); ?></span>
                        </div>
                    </aside>

                    <section class="learner-card learner-empty-state learner-evaluation-empty" data-evaluation-empty role="status" aria-live="polite" <?= $evaluationSourceState === 'empty' ? '' : 'hidden'; ?>>
                        <span class="learner-empty-state__icon"><?= learner_icon('clipboard', 30); ?></span>
                        <h2>Chưa có đánh giá được công bố</h2>
                        <p>Kết quả sẽ xuất hiện sau khi giáo viên hoặc huấn luyện viên công bố.</p>
                    </section>

                    <section class="learner-card learner-empty-state learner-evaluation-error" data-evaluation-error role="alert" <?= $evaluationSourceState === 'source-error' ? '' : 'hidden'; ?>>
                        <span class="learner-empty-state__icon"><?= learner_icon('alert-triangle', 30); ?></span>
                        <h2>Không thể tải dữ liệu đánh giá</h2>
                        <p>Hệ thống gặp lỗi khi truy xuất đánh giá. Vui lòng thử lại sau.</p>
                    </section>
                </div>
            </main>
        </div>
    </div>

    <script type="application/json" id="learner-evaluation-data"><?=
        json_encode(
            $evaluationTerms,
            JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
    ?></script>
    <script src="../../assets/js/learner-api.js"></script>
    <script src="../../assets/js/learner.js"></script>
</body>
</html>
