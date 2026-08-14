<?php
/**
 * TalentHub - School Dashboard (Lớp & Khối / Classes)
 *
 * TODO for future Backend module: replace static arrays with DB fetch functions.
 */

require_once __DIR__ . '/includes/school-data.php';

$currentRoute = '/app/school/classes.php';
$pageTitle    = 'Lớp & Khối';
$pageSubtitle = 'Quản lý danh sách lớp theo từng khối';

$gradeMeta = [
    12 => ['label' => 'Khối 12', 'icon' => '12'],
    11 => ['label' => 'Khối 11', 'icon' => '11'],
    10 => ['label' => 'Khối 10', 'icon' => '10']
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="TalentHub School Classes - Quản lý lớp theo khối và xem chi tiết top học sinh.">
    <title>Lớp & Khối - <?= htmlspecialchars($schoolInfo['name']); ?> | TalentHub</title>

    <link rel="stylesheet" href="../../assets/css/home.css">
    <link rel="stylesheet" href="../../assets/css/school.css">
</head>
<body class="school-dashboard">

    <div class="sch-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <div class="sch-main-wrapper">
            <?php include __DIR__ . '/includes/header.php'; ?>

            <main class="sch-body">
                <div class="container-fluid">

                    <?php foreach ($classesByGrade as $grade => $classes):
                        $totalStudents = array_sum(array_column($classes, 'students'));
                        $avgScore      = round(array_sum(array_column($classes, 'avg_score')) / count($classes), 1);
                        $totalHours    = array_sum(array_column($classes, 'hours'));
                        // Default open the first grade
                        $isOpen = ($grade === 12) ? 'is-open' : '';
                    ?>
                        <section class="sch-grade-section <?= $isOpen; ?>" data-grade="<?= $grade; ?>">
                            <header class="sch-grade-section__header" data-toggle-grade aria-expanded="<?= $isOpen ? 'true' : 'false'; ?>">
                                <div class="sch-grade-section__title">
                                    <h3><?= $gradeMeta[$grade]['label']; ?></h3>
                                    <span class="sch-grade-section__count"><?= count($classes); ?> lớp</span>
                                </div>
                                <div class="sch-grade-section__meta">
                                    <span><strong style="color: var(--text-primary);"><?= $totalStudents; ?></strong> học sinh</span>
                                    <span>•</span>
                                    <span>Điểm TB: <strong style="color: var(--text-primary);"><?= $avgScore; ?></strong></span>
                                    <span>•</span>
                                    <span><strong style="color: var(--text-primary);"><?= number_format($totalHours); ?></strong>h hoạt động</span>
                                </div>
                                <button class="sch-grade-section__toggle" aria-label="Mở rộng/thu gọn khối <?= $grade; ?>">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                        <polyline points="6 9 12 15 18 9"></polyline>
                                    </svg>
                                </button>
                            </header>

                            <div class="sch-grade-section__body">
                                <?php foreach ($classes as $cls):
                                    $studentsLabel = $cls['students']; ?>
                                    <div class="sch-class-card"
                                         data-class-name="<?= htmlspecialchars($cls['name']); ?>"
                                         data-major="<?= htmlspecialchars($cls['major']); ?>"
                                         data-students="<?= $cls['students']; ?>"
                                         data-score="<?= $cls['avg_score']; ?>"
                                         data-hours="<?= $cls['hours']; ?>">
                                        <div class="sch-class-card__left">
                                            <div class="sch-class-card__icon"><?= htmlspecialchars($cls['name']); ?></div>
                                            <div>
                                                <div class="sch-class-card__name">Lớp <?= htmlspecialchars($cls['name']); ?></div>
                                                <div class="sch-class-card__major"><?= htmlspecialchars($cls['major']); ?></div>
                                            </div>
                                        </div>
                                        <div class="sch-class-card__metrics">
                                            <div class="sch-class-card__metric">
                                                <div class="sch-class-card__metric-label">Sĩ số</div>
                                                <div class="sch-class-card__metric-value"><?= $cls['students']; ?></div>
                                            </div>
                                            <div class="sch-class-card__metric">
                                                <div class="sch-class-card__metric-label">Điểm TB</div>
                                                <div class="sch-class-card__metric-value"><?= $cls['avg_score']; ?></div>
                                            </div>
                                            <div class="sch-class-card__metric">
                                                <div class="sch-class-card__metric-label">Giờ HĐ</div>
                                                <div class="sch-class-card__metric-value"><?= number_format($cls['hours']); ?>h</div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>

                </div>
            </main>
        </div>
    </div>

    <!-- Modal: Top students of a class -->
    <div class="sch-modal" id="sch-class-modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle" aria-hidden="true">
        <div class="sch-modal__panel">
            <header class="sch-modal__header">
                <div>
                    <h3 class="sch-modal__title" id="modalTitle">Top 10 học sinh lớp <span id="modal-class-name">--</span></h3>
                    <p style="font-size: 0.8125rem; color: var(--text-secondary); margin-top: 0.25rem;">
                        Chuyên ngành: <span id="modal-class-major">--</span>
                    </p>
                </div>
                <button class="sch-modal__close" id="sch-modal-close" aria-label="Đóng cửa sổ">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </header>
            <div class="sch-modal__body" id="sch-modal-body">
                <!-- Populated by JS -->
            </div>
        </div>
    </div>

    <div class="sch-toast" id="sch-toast" aria-live="polite" aria-atomic="true">
        <div class="sch-toast__content">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
            <span class="sch-toast__message">Chức năng đang được phát triển!</span>
        </div>
    </div>

    <!-- Embed class top students data for JS -->
    <script>
        window.SCHOOL_TOP_STUDENTS = <?= json_encode($classTopStudents, JSON_UNESCAPED_UNICODE); ?>;
    </script>
    <script src="../../assets/js/school.js"></script>
</body>
</html>