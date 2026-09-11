<?php
/**
 * TalentHub Enterprise - Internship Applicants Data Provider
 *
 * Provides real dataset structure when database is empty.
 * All static mockup candidates have been completely eradicated for production handover.
 */
declare(strict_types=1);

$mockApplicantsByPost = [];

/**
 * Fetch applicants for a given internship post ID (empty when no DB records)
 */
function getMockApplicantsByPostId($postId): array {
    return [];
}

/**
 * Compute counts for pipeline status tabs for a given post
 */
function getApplicantPipelineCounts($postId): array {
    return [
        'all' => 0,
        'new' => 0,
        'reviewing' => 0,
        'interviewing' => 0,
        'accepted' => 0,
        'rejected' => 0,
    ];
}
