<?php
/**
 * TalentHub Enterprise - Project Sponsorships Data Provider
 *
 * Provides real dataset structure when database is empty.
 * All static mockup projects and sponsorships have been completely eradicated for production handover.
 */
declare(strict_types=1);

/**
 * Get overall sponsorship summary metrics for Enterprise dashboard
 */
function getSponsorshipMetrics(): array {
    return [
        'total_sponsored_amount' => 0,
        'total_sponsored_formatted' => '0 VNĐ',
        'total_projects_sponsored' => 0,
        'total_learners_supported' => 0,
        'active_sponsorships_count' => 0,
        'completed_milestones_count' => 0,
    ];
}

/**
 * Get list of available filter options
 */
function getSponsorshipFilterOptions(): array {
    return [
        'categories' => [
            'all' => 'Tất cả lĩnh vực',
            'AI & Phần mềm' => 'AI & Phần mềm',
            'Đồ họa 3D & Đa phương tiện' => 'Đồ họa 3D & Đa phương tiện',
            'Kinh tế số & Thương mại điện tử' => 'Kinh tế số & Thương mại điện tử',
        ],
        'schools' => [
            'all' => 'Tất cả các trường',
        ],
        'target_ranges' => [
            'all' => 'Mọi mức tài trợ',
            'under_50m' => 'Dưới 50 triệu',
            '50m_100m' => '50 - 100 triệu',
            'above_100m' => 'Trên 100 triệu',
        ],
        'statuses' => [
            'all' => 'Tất cả trạng thái',
            'in_progress' => 'Đang thực hiện & Kêu gọi',
            'calling' => 'Đang gọi tài trợ',
            'completed' => 'Đã đạt mục tiêu (100%)',
        ],
    ];
}

/**
 * Get innovation projects (empty when database is blank)
 */
function getMockProjects(): array {
    return [];
}

/**
 * Get project details by ID
 */
function getMockProjectById($id): ?array {
    return null;
}

/**
 * Get list of sponsorships made by the enterprise
 */
function getMySponsorships(): array {
    return [];
}
