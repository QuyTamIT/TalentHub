<?php
/**
 * TalentHub Enterprise - Recruitment Analytics Data Provider
 *
 * Provides clean fallback structure when database is empty.
 * All static mockup recruitment data has been completely eradicated for production handover.
 */
declare(strict_types=1);

$enterpriseInfo = [
    'company_name' => '',
    'logo_initials' => 'DN',
    'account_type' => 'Tài khoản Doanh nghiệp',
];

$analyticsSummary = [
    'total_applicants' => 0,
    'total_applicants_change' => '0%',
    'total_applicants_type' => 'neutral',
    'qualified_candidates' => 0,
    'qualified_percentage' => '0%',
    'qualified_change' => '0%',
    'qualified_change_type' => 'neutral',
    'interviewing' => 0,
    'interviewing_change' => '0',
    'interviewing_change_type' => 'neutral',
    'pass_rate' => '0%',
    'pass_rate_change' => '0%',
    'pass_rate_change_type' => 'neutral',
];

$filterOptions = [
    'time_ranges' => [
        '30_days' => '30 ngày qua',
        '6_months' => '6 tháng gần đây',
    ],
    'posts' => [
        'all' => 'Tất cả vị trí',
    ],
    'statuses' => [
        'all' => 'Tất cả trạng thái',
    ],
];

$funnelStages = [
    [
        'id' => 'applied',
        'name' => 'Ứng tuyển',
        'sub' => 'Hồ sơ nhận vào hệ thống',
        'count' => 0,
        'percentage' => 0,
        'conversion_from_prev' => '0%',
        'icon' => 'file-text',
        'color' => '#3B82F6',
    ],
    [
        'id' => 'qualified',
        'name' => 'Sàng lọc hồ sơ',
        'sub' => 'Đạt Match Score >= 70%',
        'count' => 0,
        'percentage' => 0,
        'conversion_from_prev' => '0%',
        'icon' => 'user-check',
        'color' => '#F97316',
    ],
    [
        'id' => 'interviewed',
        'name' => 'Phỏng vấn',
        'sub' => 'Vòng phỏng vấn chuyên môn',
        'count' => 0,
        'percentage' => 0,
        'conversion_from_prev' => '0%',
        'icon' => 'users',
        'color' => '#8B5CF6',
    ],
    [
        'id' => 'passed',
        'name' => 'Đạt / Nhận thực tập',
        'sub' => 'Chính thức nhận vào thực tập',
        'count' => 0,
        'percentage' => 0,
        'conversion_from_prev' => '0%',
        'icon' => 'award',
        'color' => '#16A34A',
    ],
];

$applicationTrend = [
    'labels' => [],
    'current_month_index' => 0,
    'total_applicants' => [],
    'qualified_applicants' => [],
];

$matchDistribution = [
    'avg_score' => 0,
    'total_evaluated' => 0,
    'tiers' => [],
    'skill_dimensions' => [],
];

$jobPerformanceData = [];
$recruitmentInsights = [];
