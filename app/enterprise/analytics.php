<?php
/**
 * TalentHub Enterprise - Recruitment Analytics Module (Phân tích tuyển dụng)
 * 
 * Comprehensive Recruitment Performance & Candidate Quality Analytics Dashboard.
 */

// Load Mock Data Provider
require_once __DIR__ . '/includes/analytics-data.php';

$pageTitle = 'Phân tích tuyển dụng';
$currentRoute = '/app/enterprise/analytics.php';

$sidebarNav = [
    [
        'title' => 'Tổng quan',
        'route' => '/app/enterprise/index.php',
        'icon' => 'grid',
        'active' => false
    ],
    [
        'title' => 'Tìm nhân tài',
        'route' => '/app/enterprise/talents.php',
        'icon' => 'search',
        'active' => false
    ],
    [
        'title' => 'Tuyển thực tập',
        'route' => '/app/enterprise/internships/',
        'icon' => 'briefcase',
        'active' => false
    ],
    [
        'title' => 'Tài trợ dự án',
        'route' => '/app/enterprise/sponsorships/',
        'icon' => 'award',
        'active' => false
    ],
    [
        'title' => 'Phân tích tuyển dụng',
        'route' => '/app/enterprise/analytics.php',
        'icon' => 'bar-chart-2',
        'active' => true
    ]
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="TalentHub Enterprise Analytics - Phân tích hiệu quả tuyển dụng và chất lượng ứng viên dành cho Doanh nghiệp.">
    <title><?= htmlspecialchars($pageTitle); ?> - FPT Software | TalentHub Enterprise</title>
    
    <!-- CSS Assets -->
    <link rel="stylesheet" href="../../assets/css/home.css">
    <link rel="stylesheet" href="../../assets/css/enterprise.css">
    <link rel="stylesheet" href="../../assets/css/enterprise-analytics.css">
</head>
<body class="enterprise-dashboard enterprise-analytics-page">

    <!-- Layout Wrapper -->
    <div class="ent-layout">
        
        <!-- Sidebar Navigation Partial -->
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <!-- Main Content Wrapper -->
        <div class="ent-main-wrapper">
            
            <!-- Top Header Partial -->
            <?php include __DIR__ . '/includes/header.php'; ?>

            <!-- Page Body Content -->
            <main class="ent-body">
                <div class="container-fluid">
                    
                    <!-- 1. Page Title Header Row -->
                    <div class="ent-page-header" style="margin-bottom: 1.5rem;">
                        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
                            <div>
                                <div style="font-size: 0.8125rem; font-weight: 700; color: var(--primary); text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 0.25rem;">
                                    Doanh nghiệp • Phân tích & Báo cáo
                                </div>
                                <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--text-primary); margin-bottom: 0.35rem; letter-spacing: -0.02em;">
                                    <?= htmlspecialchars($pageTitle); ?>
                                </h1>
                                <p style="font-size: 0.9375rem; color: var(--text-secondary);">
                                    Đánh giá hiệu quả tuyển dụng, đo lường tỷ lệ chuyển đổi phỏng vấn và chất lượng ứng viên từ các vị trí thực tập.
                                </p>
                            </div>
                            
                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                <button class="btn btn-outline btn-sm" onclick="alert('Đang xuất báo cáo Phân tích Tuyển dụng 2026 (PDF/Excel)...');">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 0.35rem;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                                    Xuất báo cáo
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- 2. KPI Overview Strip (4 Cards) -->
                    <div class="ana-kpis-grid">
                        
                        <!-- KPI 1: Tổng ứng viên -->
                        <div class="ana-kpi-card">
                            <div class="ana-kpi-header">
                                <span class="ana-kpi-title">Tổng ứng viên</span>
                                <div class="ana-kpi-icon blue">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                                </div>
                            </div>
                            <div class="ana-kpi-value" id="kpi-total-applicants">
                                <?= number_format($analyticsSummary['total_applicants'], 0, ',', '.'); ?>
                            </div>
                            <div class="ana-kpi-meta">
                                <span class="ana-badge-change <?= $analyticsSummary['total_applicants_type']; ?>">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="18 15 12 9 6 15"></polyline></svg>
                                    <?= $analyticsSummary['total_applicants_change']; ?>
                                </span>
                                <span class="ana-kpi-subtext">so với kỳ trước</span>
                            </div>
                        </div>

                        <!-- KPI 2: Ứng viên phù hợp -->
                        <div class="ana-kpi-card">
                            <div class="ana-kpi-header">
                                <span class="ana-kpi-title">Ứng viên phù hợp</span>
                                <div class="ana-kpi-icon orange">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                </div>
                            </div>
                            <div class="ana-kpi-value" id="kpi-qualified-candidates">
                                <?= number_format($analyticsSummary['qualified_candidates'], 0, ',', '.'); ?>
                            </div>
                            <div class="ana-kpi-meta">
                                <span class="ana-badge-change positive">
                                    Match >= 70%
                                </span>
                                <span class="ana-kpi-subtext"><?= $analyticsSummary['qualified_percentage']; ?> tổng số ứng tuyển</span>
                            </div>
                        </div>

                        <!-- KPI 3: Đang phỏng vấn -->
                        <div class="ana-kpi-card">
                            <div class="ana-kpi-header">
                                <span class="ana-kpi-title">Đang phỏng vấn</span>
                                <div class="ana-kpi-icon purple">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                                </div>
                            </div>
                            <div class="ana-kpi-value" id="kpi-interviewing">
                                <?= number_format($analyticsSummary['interviewing'], 0, ',', '.'); ?>
                            </div>
                            <div class="ana-kpi-meta">
                                <span class="ana-badge-change neutral">
                                    <?= $analyticsSummary['interviewing_change']; ?>
                                </span>
                            </div>
                        </div>

                        <!-- KPI 4: Tỷ lệ phỏng vấn đạt -->
                        <div class="ana-kpi-card">
                            <div class="ana-kpi-header">
                                <span class="ana-kpi-title">Tỷ lệ phỏng vấn đạt</span>
                                <div class="ana-kpi-icon green">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                                </div>
                            </div>
                            <div class="ana-kpi-value" id="kpi-pass-rate">
                                <?= $analyticsSummary['pass_rate']; ?>
                            </div>
                            <div class="ana-kpi-meta">
                                <span class="ana-badge-change positive">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="18 15 12 9 6 15"></polyline></svg>
                                    <?= $analyticsSummary['pass_rate_change']; ?>
                                </span>
                                <span class="ana-kpi-subtext">so với quý trước</span>
                            </div>
                        </div>

                    </div>

                    <!-- 3. Analytics Filters Bar -->
                    <div class="ana-filter-card">
                        <div class="ana-filter-header">
                            <div class="ana-filter-title-wrap">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
                                <h3 class="ana-filter-title">Bộ lọc phân tích chuyên sâu</h3>
                                <span class="ana-filter-active-tag">Dữ liệu thời gian thực</span>
                            </div>
                        </div>

                        <div class="ana-filter-grid">
                            <!-- Filter 1: Time Range -->
                            <div class="ana-filter-group">
                                <label class="ana-filter-label" for="ana-filter-time">Khoảng thời gian</label>
                                <select class="ana-select" id="ana-filter-time">
                                    <?php foreach ($filterOptions['time_ranges'] as $val => $label): ?>
                                        <option value="<?= $val; ?>"><?= htmlspecialchars($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Filter 2: Recruitment Post -->
                            <div class="ana-filter-group">
                                <label class="ana-filter-label" for="ana-filter-post">Tin tuyển dụng</label>
                                <select class="ana-select" id="ana-filter-post">
                                    <?php foreach ($filterOptions['posts'] as $val => $label): ?>
                                        <option value="<?= $val; ?>"><?= htmlspecialchars($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Filter 3: Application Status -->
                            <div class="ana-filter-group">
                                <label class="ana-filter-label" for="ana-filter-status">Trạng thái hồ sơ</label>
                                <select class="ana-select" id="ana-filter-status">
                                    <?php foreach ($filterOptions['statuses'] as $val => $label): ?>
                                        <option value="<?= $val; ?>"><?= htmlspecialchars($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Filter Reset Button -->
                            <div>
                                <button type="button" class="ana-btn-reset" id="ana-btn-reset">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 4v6h-6"></path><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path></svg>
                                    Đặt lại bộ lọc
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- 4 & 5. 2-Column Analytics Grid (Funnel + Trend) -->
                    <div class="ana-grid-2col">
                        
                        <!-- 4. Recruitment Funnel Section -->
                        <div class="ana-section-card">
                            <div class="ana-section-header">
                                <div>
                                    <h3 class="ana-section-title">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
                                        Phễu chuyển đổi tuyển dụng
                                    </h3>
                                    <span class="ana-section-subtitle">Tỷ lệ giữ chân và chuyển đổi qua từng giai đoạn tuyển dụng</span>
                                </div>
                            </div>

                            <div class="ana-funnel-wrap">
                                <?php foreach ($funnelStages as $index => $stage): ?>
                                    <div class="ana-funnel-stage">
                                        <div class="ana-funnel-stage-header">
                                            <div class="ana-funnel-stage-title-wrap">
                                                <div class="ana-funnel-stage-icon" style="background-color: <?= $stage['color']; ?>1A; color: <?= $stage['color']; ?>;">
                                                    <span style="font-size: 0.8125rem; font-weight: 800;"><?= $index + 1; ?></span>
                                                </div>
                                                <div>
                                                    <div class="ana-funnel-stage-name"><?= htmlspecialchars($stage['name']); ?></div>
                                                    <div class="ana-funnel-stage-sub"><?= htmlspecialchars($stage['sub']); ?></div>
                                                </div>
                                            </div>
                                            <div class="ana-funnel-stage-metrics">
                                                <div class="ana-funnel-count" id="funnel-<?= $stage['id']; ?>-count">
                                                    <?= number_format($stage['count'], 0, ',', '.'); ?>
                                                </div>
                                                <div class="ana-funnel-conv">
                                                    <?= $stage['conversion_from_prev']; ?> chuyển đổi
                                                </div>
                                            </div>
                                        </div>

                                        <div class="ana-funnel-bar-track">
                                            <div class="ana-funnel-bar-fill" id="funnel-<?= $stage['id']; ?>-bar" style="width: <?= $stage['percentage']; ?>%; background-color: <?= $stage['color']; ?>;"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- 5. Application Trend Section -->
                        <div class="ana-section-card">
                            <div class="ana-section-header">
                                <div>
                                    <h3 class="ana-section-title">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
                                        Xu hướng ứng tuyển theo thời gian
                                    </h3>
                                    <span class="ana-section-subtitle">So sánh tổng lượt ứng tuyển và số ứng viên đạt tiêu chuẩn sơ tuyển</span>
                                </div>
                            </div>

                            <div class="ana-chart-legend-row">
                                <div class="ana-legend-item">
                                    <span class="ana-legend-dot" style="background-color: #3B82F6;"></span>
                                    <span>Tổng số hồ sơ nộp</span>
                                </div>
                                <div class="ana-legend-item">
                                    <span class="ana-legend-dot" style="background-color: #F97316;"></span>
                                    <span>Hồ sơ đạt Match >= 70%</span>
                                </div>
                            </div>

                            <div class="ana-bars-container" id="trend-bars-container">
                                <?php 
                                $maxVal = max($applicationTrend['total_applicants']);
                                foreach ($applicationTrend['labels'] as $i => $label): 
                                    $totVal = $applicationTrend['total_applicants'][$i];
                                    $qualVal = $applicationTrend['qualified_applicants'][$i];
                                    $totHeight = round(($totVal / $maxVal) * 160);
                                    $qualHeight = round(($qualVal / $maxVal) * 160);
                                ?>
                                    <div class="ana-bar-col">
                                        <div class="ana-bar-group">
                                            <div class="ana-bar total" style="height: <?= $totHeight; ?>px;">
                                                <span class="ana-bar-val"><?= $totVal; ?></span>
                                            </div>
                                            <div class="ana-bar qualified" style="height: <?= $qualHeight; ?>px;">
                                                <span class="ana-bar-val"><?= $qualVal; ?></span>
                                            </div>
                                        </div>
                                        <span class="ana-bar-label"><?= $label; ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                    </div>

                    <!-- 6. Match Score Analysis Section -->
                    <div class="ana-section-card" style="margin-bottom: 1.75rem;">
                        <div class="ana-section-header">
                            <div>
                                <h3 class="ana-section-title">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="M12 8v4l3 3"></path></svg>
                                    Phân tích chất lượng ứng viên theo Match Score
                                </h3>
                                <span class="ana-section-subtitle">Phân bố ứng viên theo các phân khúc phù hợp năng lực trên TalentHub</span>
                            </div>
                        </div>

                        <!-- Match Score Top Summary Box -->
                        <div class="ana-match-summary-box">
                            <div class="ana-match-score-pill">
                                <div class="ana-score-number" id="ana-avg-score-badge"><?= $matchDistribution['avg_score']; ?></div>
                                <div class="ana-score-label">
                                    Điểm Match Score Trung bình<br>
                                    <span style="font-size: 0.75rem; font-weight: 500; color: var(--text-secondary);">Dựa trên <?= number_format($matchDistribution['total_evaluated'], 0, ',', '.'); ?> hồ sơ đã đánh giá</span>
                                </div>
                            </div>
                            <div style="font-size: 0.8125rem; font-weight: 600; color: var(--text-secondary);">
                                🌟 Top 29.5% ứng viên đạt thứ hạng Xuất sắc (Match Score > 90 điểm)
                            </div>
                        </div>

                        <!-- Match Tiers Grid (4 cards) -->
                        <div class="ana-match-tiers-grid">
                            <?php foreach ($matchDistribution['tiers'] as $tier): ?>
                                <div class="ana-tier-card" style="border-top: 3px solid <?= $tier['color']; ?>;">
                                    <div class="ana-tier-range" style="color: <?= $tier['color']; ?>;"><?= $tier['range']; ?></div>
                                    <div class="ana-tier-label"><?= htmlspecialchars($tier['label']); ?></div>
                                    <div class="ana-tier-count"><?= number_format($tier['count'], 0, ',', '.'); ?></div>
                                    <div class="ana-tier-percent"><?= $tier['percentage']; ?>% ứng viên phỏng vấn</div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Skill Dimension Breakdown Bars -->
                        <div style="background: var(--background); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 1.25rem;">
                            <h4 style="font-size: 0.875rem; font-weight: 800; color: var(--text-primary); margin-bottom: 1rem; text-transform: uppercase;">
                                📊 Phân tích chất lượng ứng viên theo 4 chiều đánh giá:
                            </h4>
                            <div class="ana-dimensions-list">
                                <?php foreach ($matchDistribution['skill_dimensions'] as $dim): ?>
                                    <div class="ana-dimension-item">
                                        <div class="ana-dimension-info">
                                            <span><?= htmlspecialchars($dim['name']); ?></span>
                                            <span style="color: var(--primary); font-weight: 800;"><?= $dim['score']; ?> / 100 điểm</span>
                                        </div>
                                        <div class="ana-funnel-bar-track" style="height: 6px;">
                                            <div class="ana-funnel-bar-fill" style="width: <?= $dim['percentage']; ?>%; background: var(--primary);"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- 7. Job Performance Table Section -->
                    <div class="ana-table-card">
                        <div class="ana-table-header-row">
                            <div>
                                <h3 class="ana-section-title" style="margin-bottom: 0.2rem;">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
                                    Bảng so sánh hiệu quả tuyển dụng theo vị trí
                                </h3>
                                <span class="ana-section-subtitle">Đo lường số lượng ứng viên, tỷ lệ đạt và điểm phù hợp trung bình theo tin tuyển dụng</span>
                            </div>

                            <!-- Table Search Input -->
                            <div class="ana-search-input-wrap">
                                <svg class="ana-search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                                <input type="text" class="ana-search-input" id="ana-table-search" placeholder="Tìm theo tên vị trí, bộ phận...">
                            </div>
                        </div>

                        <div class="ana-table-wrapper">
                            <table class="ana-table">
                                <thead>
                                    <tr>
                                        <th>Vị trí tuyển dụng</th>
                                        <th>Bộ phận</th>
                                        <th>Tổng ứng tuyển</th>
                                        <th>Đạt sơ tuyển (>=70%)</th>
                                        <th>Đang PV</th>
                                        <th>Trúng tuyển</th>
                                        <th>Avg Match Score</th>
                                    </tr>
                                </thead>
                                <tbody id="job-performance-tbody">
                                    <?php foreach ($jobPerformanceData as $job): ?>
                                        <tr>
                                            <td>
                                                <div style="font-weight: 700; color: var(--text-primary); margin-bottom: 0.15rem;"><?= htmlspecialchars($job['position']); ?></div>
                                                <div style="font-size: 0.75rem; color: var(--text-muted);">Mã tin: <?= htmlspecialchars($job['code']); ?></div>
                                            </td>
                                            <td>
                                                <span class="ana-dept-badge <?= $job['department_badge']; ?>"><?= htmlspecialchars($job['department']); ?></span>
                                            </td>
                                            <td>
                                                <strong style="color: var(--text-primary);"><?= number_format($job['applicants'], 0, ',', '.'); ?></strong>
                                            </td>
                                            <td>
                                                <span style="color: #16A34A; font-weight: 700;"><?= number_format($job['qualified'], 0, ',', '.'); ?></span>
                                                <span style="font-size: 0.75rem; color: var(--text-muted);"> (<?= round(($job['qualified'] / $job['applicants']) * 100); ?>%)</span>
                                            </td>
                                            <td><?= $job['interviewed']; ?></td>
                                            <td>
                                                <strong style="color: var(--primary);"><?= $job['passed']; ?></strong>
                                            </td>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                    <span style="font-weight: 800; color: var(--text-primary);"><?= $job['avg_match']; ?></span>
                                                    <div style="flex: 1; height: 6px; background: rgba(226, 232, 240, 0.7); border-radius: 3px; max-width: 60px; overflow: hidden;">
                                                        <div style="width: <?= $job['avg_match']; ?>%; height: 100%; background: var(--primary);"></div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- 8. Recruitment Insights Section -->
                    <div style="margin-bottom: 2rem;">
                        <div style="margin-bottom: 1.25rem;">
                            <h3 class="ana-section-title" style="margin-bottom: 0.2rem;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                                Recruitment Insights - Phân tích & Khuyến nghị Tuyển dụng
                            </h3>
                            <span class="ana-section-subtitle">Nhận định tổng hợp dữ liệu giúp tối ưu hóa chiến dịch tuyển dụng thực tập sinh</span>
                        </div>

                        <div class="ana-insights-grid">
                            <?php foreach ($recruitmentInsights as $insight): ?>
                                <div class="ana-insight-card <?= $insight['type']; ?>">
                                    <div>
                                        <span class="ana-insight-badge"><?= htmlspecialchars($insight['badge']); ?></span>
                                        <h4 class="ana-insight-title"><?= htmlspecialchars($insight['title']); ?></h4>
                                        <p class="ana-insight-desc"><?= htmlspecialchars($insight['description']); ?></p>
                                    </div>
                                    <div class="ana-insight-footer">
                                        <span style="color: var(--text-muted);"><?= htmlspecialchars($insight['metric_label']); ?>:</span>
                                        <strong style="color: var(--text-primary); font-weight: 800;"><?= htmlspecialchars($insight['metric_val']); ?></strong>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                </div>
            </main>
        </div>
    </div>

    <!-- Inject JS Mock Data Window Object -->
    <script>
        window.JOB_PERFORMANCE_DATA = <?= json_encode($jobPerformanceData); ?>;
    </script>

    <!-- JS Assets -->
    <script src="../../assets/js/enterprise.js"></script>
    <script src="../../assets/js/enterprise-analytics.js"></script>
</body>
</html>
