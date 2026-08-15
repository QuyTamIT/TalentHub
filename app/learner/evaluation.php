<?php
/** TalentHub Learner - Competency evaluation */
require __DIR__ . '/includes/student-data.php';
require_once __DIR__ . '/includes/icons.php';

$pageTitle = 'Đánh giá năng lực';
$currentRoute = '/app/learner/evaluation.php';
$currentTerm = $evaluationTerms[$defaultEvaluationTerm];
$currentEvaluation = $currentTerm['evaluation'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Theo dõi điểm đánh giá năng lực và nhận xét từ giáo viên, huấn luyện viên trên TalentHub.">
    <title>Đánh giá năng lực | TalentHub</title>
    <link rel="stylesheet" href="../../assets/css/home.css">
    <link rel="stylesheet" href="../../assets/css/learner.css">
</head>
<body class="learner-app learner-page-evaluation">
    <div class="learner-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <div class="learner-main">
            <?php include __DIR__ . '/includes/header.php'; ?>

            <main class="learner-content" id="main-content">
                <div class="learner-evaluation-heading">
                    <div class="learner-page-heading">
                        <h1>Đánh giá năng lực</h1>
                        <p>Theo dõi điểm số và nhận xét từ giáo viên, huấn luyện viên.</p>
                    </div>

                    <div class="learner-evaluation-heading__actions">
                        <label class="learner-term-select" for="learner-evaluation-term">
                            <?= learner_icon('calendar', 18); ?>
                            <span class="learner-visually-hidden">Chọn học kỳ</span>
                            <select id="learner-evaluation-term" name="term">
                                <?php foreach ($evaluationTerms as $termId => $term): ?>
                                    <option value="<?= learner_escape($termId); ?>" <?= $termId === $defaultEvaluationTerm ? 'selected' : ''; ?>>
                                        <?= learner_escape($term['label']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <span class="learner-publication-status" data-evaluation-status data-state="published" role="status" aria-live="polite" aria-atomic="true">
                            <span aria-hidden="true"></span><?= learner_escape($currentTerm['status']); ?>
                        </span>
                    </div>
                </div>

                <div class="learner-evaluation-grid">
                    <section class="learner-card learner-evaluation-criteria" data-evaluation-content aria-labelledby="learner-criteria-title">
                        <h2 id="learner-criteria-title">Bảng tiêu chí</h2>
                        <div class="learner-evaluation-criteria__list" data-evaluation-criteria>
                            <?php foreach ($currentEvaluation['criteria'] as $criterion): ?>
                                <?php $percentage = (float) $criterion['score'] / (float) $criterion['max'] * 100; ?>
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
                            <blockquote data-evaluation-comment><?= learner_escape($currentEvaluation['comment']); ?></blockquote>
                            <p>— <span data-evaluation-reviewer><?= learner_escape($currentEvaluation['reviewer']); ?></span></p>
                        </article>
                    </section>

                    <aside class="learner-card learner-evaluation-score" data-evaluation-summary aria-labelledby="learner-total-title">
                        <p id="learner-total-title">Tổng điểm</p>
                        <strong data-evaluation-total><?= learner_escape($currentEvaluation['total']); ?></strong>
                        <span>/ 100</span>
                        <div class="learner-evaluation-classification">
                            <?= learner_icon('star', 21); ?>
                            <strong data-evaluation-classification><?= learner_escape($currentEvaluation['classification']); ?></strong>
                        </div>
                        <div class="learner-evaluation-ranking">
                            <?= learner_icon('chart', 22); ?>
                            <span data-evaluation-ranking><?= learner_escape($currentEvaluation['ranking']); ?></span>
                        </div>
                    </aside>

                    <section class="learner-card learner-empty-state learner-evaluation-empty" data-evaluation-empty role="status" aria-live="polite" hidden>
                        <span class="learner-empty-state__icon"><?= learner_icon('clipboard', 30); ?></span>
                        <h2>Học kỳ này chưa có đánh giá</h2>
                        <p>Kết quả sẽ xuất hiện sau khi giáo viên hoặc huấn luyện viên công bố.</p>
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
