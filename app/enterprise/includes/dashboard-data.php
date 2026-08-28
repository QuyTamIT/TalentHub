<?php
/**
 * TalentHub Enterprise - Dashboard Data Provider
 *
 * Resolves the enterprise context and database records accurately based on
 * the active user session (from $_SESSION['user'], $_SESSION['user_id'], or $_SESSION['email']).
 *
 * Strict rule:
 * - Query enterprise precisely matching the logged-in user ID or email.
 * - NEVER use hardcoded IDs or arbitrary LIMIT 1 fallbacks.
 */
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/bin/bootstrap.php';
require_once dirname(__DIR__, 3) . '/src/Bootstrap/EnterpriseAppContext.php';

use TalentHub\Bootstrap\EnterpriseAppContext;

$context = (new EnterpriseAppContext())->boot();
$user       = $context['user'];
$enterprise = $context['enterprise'];
$dashboard  = $context['dashboard'];
$workflowService = $context['workflows'];
$internshipService = $context['internships'];
$talentService = $context['talents'];
$partnershipService = $context['partnerships'];
$csrfToken  = $context['csrfToken'];
$pdo        = $context['pdo'] ?? null;

if (!function_exists('getInitials')) {
    function getInitials(string $name): string {
        if (stripos($name, 'Vinamilk') !== false || stripos($name, 'Sữa Việt Nam') !== false || stripos($name, 'VNM') !== false) {
            return 'VNM';
        }
        if (stripos($name, 'FPT') !== false || stripos($name, 'Phần mềm FPT') !== false) {
            return 'FS';
        }
        if (stripos($name, 'MB') !== false || stripos($name, 'Quân đội') !== false) {
            return 'MB';
        }
        $words = preg_split('/\s+/', trim($name));
        if (empty($words) || $words[0] === '') return 'DN';
        if (count($words) === 1) return mb_strtoupper(mb_substr($words[0], 0, 2));
        return mb_strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[count($words) - 1], 0, 1));
    }
}

$companyInitials = getInitials((string)($enterprise['name'] ?? 'Doanh nghiệp'));
$isVerified = ($enterprise['verificationStatus'] ?? 'pending') === 'verified';
$accountType = $isVerified ? 'Doanh nghiệp Đã xác thực' : 'Tài khoản Doanh nghiệp';

$summary = [
    'total_posts' => 0,
    'active_posts' => 0,
    'closed_posts' => 0,
    'total_applicants' => 0,
    'submitted_count' => 0,
    'reviewing_count' => 0,
    'qualified_candidates' => 0,
    'qualified_percentage' => '0%',
    'interviewing' => 0,
    'passed_candidates' => 0,
    'declined_candidates' => 0,
    'pass_rate' => 0.0,
    'pass_rate_formatted' => '0%',
    'sponsored_projects_count' => 0,
    'total_sponsored_amount' => '0.00',
    'total_sponsored_formatted' => '0 VNĐ',
    'matched_talents_count' => 0,
];

try {
    if (isset($user['id']) && $workflowService !== null) {
        $analyticsData = $workflowService->analytics((string) $user['id']);
        if (!empty($analyticsData['summary'])) {
            $summary = array_merge($summary, $analyticsData['summary']);
        }
    }
} catch (\Throwable $e) {
    error_log('Enterprise dashboard-data analytics fetch failed: ' . $e->getMessage());
}

$enterpriseInfo = [
    'id'                => $enterprise['id'] ?? '',
    'company_name'      => $enterprise['name'] ?? 'Doanh nghiệp',
    'name'              => $enterprise['name'] ?? 'Doanh nghiệp',
    'account_type'      => $accountType,
    'logo_initials'     => $companyInitials,
    'logo_url'          => $enterprise['logoUrl'] ?? null,
    'new_matches_count' => $summary['qualified_candidates'] ?? 0,
    'total_talents'     => $summary['matched_talents_count'] ?? 0,
];
