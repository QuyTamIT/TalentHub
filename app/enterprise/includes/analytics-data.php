<?php
/**
 * TalentHub Enterprise - Recruitment Analytics Mock Data Provider
 * 
 * Provides mock data for recruitment performance metrics, funnel conversion,
 * applicant trend timeline, match score distribution, and position performance table.
 */

$enterpriseInfo = [
    'company_name' => 'FPT Software',
    'logo_initials' => 'FPT',
    'account_type' => 'Gói Premium'
];

$analyticsSummary = [
    'total_applicants' => 1482,
    'total_applicants_change' => '+18.5%',
    'total_applicants_type' => 'positive',
    'qualified_candidates' => 964,
    'qualified_percentage' => '65.0%',
    'qualified_change' => '+6.2%',
    'qualified_change_type' => 'positive',
    'interviewing' => 142,
    'interviewing_change' => '+12 ứng viên tuần này',
    'interviewing_change_type' => 'neutral',
    'pass_rate' => '74.2%',
    'pass_rate_change' => '+5.4%',
    'pass_rate_change_type' => 'positive'
];

$filterOptions = [
    'time_ranges' => [
        '30_days' => '30 ngày qua (Mới nhất)',
        'q3_2026' => 'Quý 3/2026',
        '6_months' => '6 tháng gần đây',
        'y2026' => 'Cả năm 2026'
    ],
    'posts' => [
        'all' => 'Tất cả vạch vị trí tuyển dụng (5 tin)',
        'ai_ml' => 'Thực tập sinh AI & Machine Learning',
        'frontend' => 'Thực tập sinh Frontend React/Next.js',
        'backend' => 'Thực tập sinh Backend Node.js/Python',
        'ui_ux' => 'Thực tập sinh UI/UX Product Design',
        'iot' => 'Thực tập sinh IoT & Embedded Systems'
    ],
    'statuses' => [
        'all' => 'Tất cả trạng thái hồ sơ',
        'applied' => 'Mới ứng tuyển (Screening)',
        'qualified' => 'Hồ sơ phù hợp (Match >= 70%)',
        'interviewing' => 'Đang phỏng vấn',
        'passed' => 'Đã nhận việc (Offer Sent)',
        'rejected' => 'Không phù hợp'
    ]
];

$funnelStages = [
    [
        'id' => 'applied',
        'name' => 'Ứng tuyển',
        'sub' => 'Hồ sơ nhận vào hệ thống',
        'count' => 1482,
        'percentage' => 100,
        'conversion_from_prev' => '100%',
        'icon' => 'file-text',
        'color' => '#3B82F6'
    ],
    [
        'id' => 'qualified',
        'name' => 'Sàng lọc hồ sơ',
        'sub' => 'Đạt Match Score >= 70%',
        'count' => 964,
        'percentage' => 65.0,
        'conversion_from_prev' => '65.0%',
        'icon' => 'user-check',
        'color' => '#F97316'
    ],
    [
        'id' => 'interviewed',
        'name' => 'Phỏng vấn',
        'sub' => 'Vòng phỏng vấn chuyên môn',
        'count' => 318,
        'percentage' => 21.5,
        'conversion_from_prev' => '33.0%',
        'icon' => 'users',
        'color' => '#8B5CF6'
    ],
    [
        'id' => 'passed',
        'name' => 'Đạt / Tuyển dụng',
        'sub' => 'Chính thức nhận vào thực tập',
        'count' => 236,
        'percentage' => 15.9,
        'conversion_from_prev' => '74.2%',
        'icon' => 'award',
        'color' => '#16A34A'
    ]
];

$applicationTrend = [
    'labels' => ['Thg 3', 'Thg 4', 'Thg 5', 'Thg 6', 'Thg 7', 'Thg 8 (Hiện tại)'],
    'total_applicants' => [140, 195, 250, 310, 340, 247],
    'qualified_applicants' => [88, 126, 168, 205, 222, 155]
];

$matchDistribution = [
    'avg_score' => 84.6,
    'total_evaluated' => 964,
    'tiers' => [
        [
            'range' => '90 - 100%',
            'label' => 'Xuất sắc (Top Talent)',
            'count' => 284,
            'percentage' => 29.5,
            'color' => '#16A34A',
            'bg_light' => 'rgba(22, 163, 74, 0.12)'
        ],
        [
            'range' => '80 - 89%',
            'label' => 'Rất tốt (High Fit)',
            'count' => 412,
            'percentage' => 42.7,
            'color' => '#2563EB',
            'bg_light' => 'rgba(37, 99, 235, 0.12)'
        ],
        [
            'range' => '70 - 79%',
            'label' => 'Đạt yêu cầu (Qualified)',
            'count' => 268,
            'percentage' => 27.8,
            'color' => '#F97316',
            'bg_light' => 'rgba(249, 115, 22, 0.12)'
        ],
        [
            'range' => '< 70%',
            'label' => 'Chưa đạt (Low Fit)',
            'count' => 518,
            'percentage' => 35.0,
            'color' => '#94A3B8',
            'bg_light' => 'rgba(148, 163, 184, 0.12)'
        ]
    ],
    'skill_dimensions' => [
        ['name' => 'Kỹ năng chuyên môn & Framework', 'score' => 88.2, 'percentage' => 88],
        ['name' => 'Kinh nghiệm dự án thực tế & GitHub', 'score' => 84.5, 'percentage' => 85],
        ['name' => 'Học vấn & Chứng chỉ học thuật', 'score' => 82.0, 'percentage' => 82],
        ['name' => 'Năng lực tiếng Anh & Soft-skills', 'score' => 79.8, 'percentage' => 80]
    ]
];

$jobPerformanceData = [
    [
        'id' => 'post_ai_ml',
        'code' => 'AI-2026-01',
        'position' => 'Thực tập sinh AI & Machine Learning',
        'department' => 'AI Center',
        'department_badge' => 'tech',
        'post_key' => 'ai_ml',
        'applicants' => 380,
        'qualified' => 298,
        'interviewed' => 95,
        'passed' => 78,
        'conversion_rate' => 20.5,
        'avg_match' => 87.4,
        'status' => 'Đang tuyển',
        'status_type' => 'active'
    ],
    [
        'id' => 'post_frontend',
        'code' => 'FE-2026-02',
        'position' => 'Thực tập sinh Frontend React/Next.js',
        'department' => 'Software Div 1',
        'department_badge' => 'web',
        'post_key' => 'frontend',
        'applicants' => 340,
        'qualified' => 228,
        'interviewed' => 76,
        'passed' => 58,
        'conversion_rate' => 17.1,
        'avg_match' => 84.2,
        'status' => 'Đang tuyển',
        'status_type' => 'active'
    ],
    [
        'id' => 'post_backend',
        'code' => 'BE-2026-03',
        'position' => 'Thực tập sinh Backend Node.js/Python',
        'department' => 'Cloud Service',
        'department_badge' => 'cloud',
        'post_key' => 'backend',
        'applicants' => 290,
        'qualified' => 195,
        'interviewed' => 64,
        'passed' => 48,
        'conversion_rate' => 16.5,
        'avg_match' => 83.1,
        'status' => 'Đang tuyển',
        'status_type' => 'active'
    ],
    [
        'id' => 'post_ui_ux',
        'code' => 'DES-2026-04',
        'position' => 'Thực tập sinh UI/UX Product Design',
        'department' => 'Product Studio',
        'department_badge' => 'design',
        'post_key' => 'ui_ux',
        'applicants' => 220,
        'qualified' => 154,
        'interviewed' => 48,
        'passed' => 34,
        'conversion_rate' => 15.5,
        'avg_match' => 85.8,
        'status' => 'Tạm dừng',
        'status_type' => 'paused'
    ],
    [
        'id' => 'post_iot',
        'code' => 'IOT-2026-05',
        'position' => 'Thực tập sinh IoT & Embedded Systems',
        'department' => 'Smart Hardware',
        'department_badge' => 'hardware',
        'post_key' => 'iot',
        'applicants' => 252,
        'qualified' => 114,
        'interviewed' => 35,
        'passed' => 18,
        'conversion_rate' => 7.1,
        'avg_match' => 76.5,
        'status' => 'Đang tuyển',
        'status_type' => 'active'
    ]
];

$recruitmentInsights = [
    [
        'type' => 'success',
        'badge' => 'HIỆU QUẢ CAO NHẤT',
        'title' => 'Dự án AI & Machine Learning thu hút nguồn nhân lực chất lượng cao nhất',
        'description' => 'Vị trí Thực tập sinh AI đạt tỷ lệ hồ sơ phù hợp 78.4% (298/380) và điểm Match Score trung bình 87.4/100, cao nhất toàn doanh nghiệp. Thời gian chốt hợp đồng phỏng vấn đạt trung bình 12 ngày.',
        'metric_label' => 'Match Score TB',
        'metric_val' => '87.4 điểm'
    ],
    [
        'type' => 'warning',
        'badge' => 'CẦN TỐI ƯU SÀNG LỌC',
        'title' => 'Cảnh báo: Vị trí IoT có tỷ lệ đạt hồ sơ thấp (45.2%)',
        'description' => 'Mặc dù nhận được 252 lượt ứng tuyển, nhưng chỉ có 114 ứng viên đạt ngưỡng Match Score >= 70%. Phần lớn hồ sơ bị điểm thấp ở kỹ năng vi điều khiển (STM32/ESP32). Khuyến nghị điều chỉnh mô tả công việc (JD) để làm rõ yêu cầu phần cứng.',
        'metric_label' => 'Chuyển đổi phỏng vấn',
        'metric_val' => '7.1%'
    ],
    [
        'type' => 'info',
        'badge' => 'XU HƯỚNG KỸ NĂNG',
        'title' => '88% ứng viên xuất sắc (Match > 85 điểm) đều sở hữu dự án thực tế',
        'description' => 'Phân tích dữ liệu hồ sơ cho thấy ứng viên có liên kết GitHub active và công bố ít nhất 2 dự án thực tế trên TalentHub có tỷ lệ đỗ phỏng vấn cao gấp 2.4 lần so với ứng viên chỉ có điểm trung bình học tập.',
        'metric_label' => 'Ứng viên Top Talent',
        'metric_val' => '284 ứng viên'
    ]
];
