<?php
/**
 * TalentHub - School Dashboard (Phân tích năng lực / Analytics)
 *
 * TODO for future Backend module: replace static arrays with DB fetch functions.
 * Page-specific $currentRoute is set so sidebar can highlight the active item.
 */

require_once __DIR__ . '/includes/school-data.php';

$currentRoute = '/app/school/analytics.php';
$pageTitle    = 'Phân tích năng lực';
$pageSubtitle = 'So sánh điểm năng lực theo lĩnh vực và khối';

// Unique values for filter dropdowns
$talentFields = array_keys($talentByFieldGrade);
$grades       = [10, 11, 12];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="TalentHub School Analytics - Phân tích điểm năng lực theo lĩnh vực và khối.">
    <title>Phân tích năng lực - <?= htmlspecialchars($schoolInfo['name']); ?> | TalentHub</title>

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

                    <!-- Filter Bar -->
                    <div class="sch-filter-bar" role="region" aria-label="Bộ lọc phân tích">
                        <div class="sch-filter-bar__group">
                            <label for="filter-grade" class="sch-filter-bar__label">Khối:</label>
                            <select id="filter-grade" class="sch-filter-bar__select" data-filter="grade">
                                <option value="all">Tất cả khối</option>
                                <?php foreach ($grades as $g): ?>
                                    <option value="<?= $g; ?>">Khối <?= $g; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="sch-filter-bar__group">
                            <label for="filter-field" class="sch-filter-bar__label">Lĩnh vực:</label>
                            <select id="filter-field" class="sch-filter-bar__select" data-filter="field">
                                <option value="all">Tất cả lĩnh vực</option>
                                <?php foreach ($talentFields as $f): ?>
                                    <option value="<?= htmlspecialchars($f); ?>"><?= htmlspecialchars($f); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="sch-filter-bar__group">
                            <label for="filter-class" class="sch-filter-bar__label">Lớp:</label>
                            <select id="filter-class" class="sch-filter-bar__select" data-filter="class">
                                <option value="all">Tất cả lớp</option>
                                <option value="12A1">12A1 - Chuyên Tin</option>
                                <option value="11A2">11A2 - Chuyên Lý</option>
                                <option value="12B3">12B3 - Chuyên Hoá</option>
                            </select>
                        </div>
                    </div>

                    <!-- Grouped Bar Chart -->
                    <section class="sch-section-box" style="margin-bottom: 1.75rem;">
                        <div class="sch-section-box__header">
                            <div>
                                <h3 class="sch-section-box__title">Điểm năng lực theo lĩnh vực × khối</h3>
                                <p class="sch-section-box__subtitle">So sánh điểm trung bình (thang 100) giữa 3 khối lớp qua từng lĩnh vực năng khiếu</p>
                            </div>
                        </div>

                        <?php
                        // SVG chart geometry
                        $chartW   = 760;
                        $chartH   = 340;
                        $paddingL = 50;
                        $paddingR = 20;
                        $paddingT = 30;
                        $paddingB = 50;
                        $plotW    = $chartW - $paddingL - $paddingR;
                        $plotH    = $chartH - $paddingT - $paddingB;
                        $yMax     = 100;
                        $yMin     = 60;
                        $yTicks   = [60, 70, 80, 90, 100];

                        $nFields   = count($talentFields);
                        $groupGap  = 28;
                        $groupW    = ($plotW - $groupGap * ($nFields + 1)) / $nFields;
                        $barW      = $groupW / 3;
                        $gradeColors = ['10' => '#F59E0B', '11' => '#2563EB', '12' => '#16A34A'];
                        $gradeLabels = ['10' => 'Khối 10', '11' => 'Khối 11', '12' => 'Khối 12'];
                        ?>

                        <svg class="sch-chart-grouped" viewBox="0 0 <?= $chartW; ?> <?= $chartH; ?>"
                             role="img" aria-labelledby="groupedTitle groupedDesc"
                             preserveAspectRatio="xMidYMid meet" style="width:100%; height:auto;">
                            <title id="groupedTitle">Biểu đồ điểm năng lực theo lĩnh vực và khối</title>
                            <desc id="groupedDesc">Biểu đồ cột nhóm thể hiện điểm năng lực trung bình của 5 lĩnh vực (Kỹ thuật, Học thuật, Nghệ thuật, Kinh doanh, Thể thao) chia theo 3 khối (10, 11, 12).</desc>

                            <!-- Y axis grid + labels -->
                            <?php foreach ($yTicks as $tick):
                                $y = $paddingT + $plotH - (($tick - $yMin) / ($yMax - $yMin)) * $plotH;
                            ?>
                                <line x1="<?= $paddingL; ?>" x2="<?= $chartW - $paddingR; ?>"
                                      y1="<?= $y; ?>" y2="<?= $y; ?>"
                                      stroke="#E2E8F0" stroke-width="1" stroke-dasharray="3,3"/>
                                <text x="<?= $paddingL - 8; ?>" y="<?= $y + 4; ?>"
                                      text-anchor="end" font-size="11" fill="#64748B"><?= $tick; ?></text>
                            <?php endforeach; ?>

                            <!-- Bars -->
                            <?php foreach ($talentFields as $i => $field):
                                $groupX = $paddingL + $groupGap + $i * ($groupW + $groupGap);
                                foreach (['10', '11', '12'] as $j => $grade):
                                    $score = $talentByFieldGrade[$field][$grade];
                                    $barH  = (($score - $yMin) / ($yMax - $yMin)) * $plotH;
                                    $barX  = $groupX + $j * $barW;
                                    $barY  = $paddingT + $plotH - $barH;
                                ?>
                                    <rect x="<?= $barX; ?>" y="<?= $barY; ?>"
                                          width="<?= $barW - 4; ?>" height="<?= $barH; ?>"
                                          fill="<?= $gradeColors[$grade]; ?>"
                                          rx="3" ry="3"
                                          opacity="0.92">
                                        <title><?= htmlspecialchars($field); ?> - <?= $gradeLabels[$grade]; ?>: <?= $score; ?> điểm</title>
                                    </rect>
                                    <text x="<?= $barX + ($barW - 4) / 2; ?>" y="<?= $barY - 6; ?>"
                                          text-anchor="middle" font-size="10.5" fill="#0F172A" font-weight="700"><?= $score; ?></text>
                                <?php endforeach; ?>
                                <text x="<?= $groupX + $groupW / 2; ?>" y="<?= $chartH - $paddingB + 18; ?>"
                                      text-anchor="middle" font-size="12" fill="#0F172A" font-weight="600"><?= htmlspecialchars($field); ?></text>
                            <?php endforeach; ?>

                            <!-- Y axis title -->
                            <text x="<?= $paddingL; ?>" y="<?= $paddingT - 10; ?>"
                                  font-size="11" fill="#64748B" font-weight="600">Điểm TB (0-100)</text>
                        </svg>

                        <div class="sch-chart-legend">
                            <div class="sch-chart-legend__item">
                                <span class="sch-chart-legend__chip sch-chart-legend__chip--10"></span>
                                <span>Khối 10</span>
                            </div>
                            <div class="sch-chart-legend__item">
                                <span class="sch-chart-legend__chip sch-chart-legend__chip--11"></span>
                                <span>Khối 11</span>
                            </div>
                            <div class="sch-chart-legend__item">
                                <span class="sch-chart-legend__chip sch-chart-legend__chip--12"></span>
                                <span>Khối 12</span>
                            </div>
                        </div>
                    </section>

                    <!-- Top Students Table -->
                    <section class="sch-section-box">
                        <div class="sch-section-box__header">
                            <div>
                                <h3 class="sch-section-box__title">Top 20 học sinh xuất sắc</h3>
                                <p class="sch-section-box__subtitle">Học sinh có điểm năng lực tổng hợp cao nhất toàn trường</p>
                            </div>
                        </div>

                        <table class="sch-data-table">
                            <thead>
                                <tr>
                                    <th style="width: 60px;">#</th>
                                    <th>Họ và tên</th>
                                    <th>Lớp</th>
                                    <th>Lĩnh vực chính</th>
                                    <th style="text-align: right;">Giờ hoạt động</th>
                                    <th style="text-align: right;">Điểm năng lực</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topStudents as $i => $student): ?>
                                    <tr>
                                        <td style="color: var(--text-muted); font-weight: 600;"><?= $i + 1; ?></td>
                                        <td>
                                            <div class="sch-data-table__name-cell">
                                                <div class="sch-data-table__avatar">
                                                    <?= mb_substr(explode(' ', $student['name'])[count(explode(' ', $student['name'])) - 1], 0, 1, 'UTF-8'); ?>
                                                </div>
                                                <div class="sch-data-table__name-info">
                                                    <h5><?= htmlspecialchars($student['name']); ?></h5>
                                                    <p><?= htmlspecialchars($student['major']); ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td><span class="sch-data-table__chip"><?= htmlspecialchars($student['class']); ?></span></td>
                                        <td><?= htmlspecialchars($student['primary_field']); ?></td>
                                        <td style="text-align: right; color: var(--text-secondary);"><?= $student['hours']; ?>h</td>
                                        <td style="text-align: right;">
                                            <span class="sch-data-table__score"><?= $student['talent_score']; ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </section>

                </div>
            </main>
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

    <script src="../../assets/js/school.js"></script>
</body>
</html>