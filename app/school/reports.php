<?php
/**
 * TalentHub - School Dashboard (Báo cáo / Reports)
 *
 * TODO for future Backend module: replace static arrays with DB fetch functions.
 */

require_once __DIR__ . '/includes/school-data.php';

$currentRoute = '/app/school/reports.php';
$pageTitle    = 'Báo cáo';
$pageSubtitle = 'Danh sách báo cáo định kỳ và theo nhu cầu';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="TalentHub School Reports - Danh sách báo cáo năng lực, hoạt động và huy hiệu.">
    <title>Báo cáo - <?= htmlspecialchars($schoolInfo['name']); ?> | TalentHub</title>

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

                    <!-- Reports Section -->
                    <section class="sch-section-box">
                        <div class="sch-section-box__header">
                            <div>
                                <h3 class="sch-section-box__title">Danh sách báo cáo</h3>
                                <p class="sch-section-box__subtitle">Tải xuống các báo cáo năng lực, hoạt động và huy hiệu của trường</p>
                            </div>
                            <button class="btn btn-primary btn-sm" data-create-report>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                    <line x1="12" y1="5" x2="12" y2="19"></line>
                                    <line x1="5" y1="12" x2="19" y2="12"></line>
                                </svg>
                                Tạo báo cáo mới
                            </button>
                        </div>

                        <!-- Filter chips: category + academic year -->
                        <div class="sch-report-filters" role="region" aria-label="Bộ lọc báo cáo">
                            <span class="sch-filter-bar__label">Loại:</span>
                            <button class="sch-report-chip is-active" data-category="all">Tất cả</button>
                            <?php foreach ($reportCategories as $cat): ?>
                                <button class="sch-report-chip" data-category="<?= htmlspecialchars($cat); ?>"><?= htmlspecialchars($cat); ?></button>
                            <?php endforeach; ?>

                            <span class="sch-filter-bar__label" style="margin-left: 1rem;">Năm học:</span>
                            <button class="sch-report-chip is-active" data-year="all">Tất cả</button>
                            <?php foreach ($academicYears as $year): ?>
                                <button class="sch-report-chip" data-year="<?= htmlspecialchars($year); ?>"><?= htmlspecialchars($year); ?></button>
                            <?php endforeach; ?>
                        </div>

                        <table class="sch-data-table">
                            <thead>
                                <tr>
                                    <th style="width: 50px;">#</th>
                                    <th>Tên báo cáo</th>
                                    <th>Loại</th>
                                    <th>Ngày tạo</th>
                                    <th>Định dạng</th>
                                    <th>Dung lượng</th>
                                    <th style="text-align: right;">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reports as $i => $rpt): ?>
                                    <tr data-category="<?= htmlspecialchars($rpt['category']); ?>"
                                        data-year="<?= htmlspecialchars($rpt['academic_year']); ?>">
                                        <td style="color: var(--text-muted); font-weight: 600;"><?= $i + 1; ?></td>
                                        <td>
                                            <div class="sch-data-table__name-info">
                                                <h5><?= htmlspecialchars($rpt['title']); ?></h5>
                                                <p>Năm học <?= htmlspecialchars($rpt['academic_year']); ?></p>
                                            </div>
                                        </td>
                                        <td><span class="sch-data-table__chip"><?= htmlspecialchars($rpt['category']); ?></span></td>
                                        <td style="color: var(--text-secondary);"><?= htmlspecialchars($rpt['date']); ?></td>
                                        <td>
                                            <span class="sch-report-format sch-report-format--<?= strtolower($rpt['format']); ?>">
                                                <?= htmlspecialchars($rpt['format']); ?>
                                            </span>
                                        </td>
                                        <td style="color: var(--text-secondary);"><?= htmlspecialchars($rpt['size']); ?></td>
                                        <td>
                                            <div class="sch-report-actions">
                                                <button class="btn btn-secondary btn-sm" data-preview-report="<?= $rpt['id']; ?>" title="Xem trước">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                        <circle cx="12" cy="12" r="3"></circle>
                                                    </svg>
                                                </button>
                                                <button class="btn btn-primary btn-sm" data-download-report="<?= $rpt['id']; ?>" title="Tải xuống">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                                        <polyline points="7 10 12 15 17 10"></polyline>
                                                        <line x1="12" y1="15" x2="12" y2="3"></line>
                                                    </svg>
                                                    Tải xuống
                                                </button>
                                            </div>
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