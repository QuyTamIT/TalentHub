<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
};

$badgeEndpoint = file_get_contents($root . '/app/learner/api/v1/badges.php') ?: '';
$statisticsEndpoint = file_get_contents($root . '/app/learner/api/v1/statistics.php') ?: '';
$permissionSeeder = file_get_contents($root . '/Database/seeds/System/RolePermissionSeeder.php') ?: '';
$schoolService = file_get_contents($root . '/src/Modules/School/Service/SchoolDashboardService.php') ?: '';
$passportRepository = file_get_contents($root . '/app/learner/data/Database/DatabaseTalentPassportRepository.php') ?: '';

$assert(str_contains($badgeEndpoint, "studentId('badge.read_own')"), 'badge endpoint derives the owner from Student API context');
$assert(str_contains($statisticsEndpoint, "studentId('student_dashboard.read_own')"), 'statistics endpoint derives the owner from Student API context');
$assert(!str_contains($badgeEndpoint, "queryParam('studentId')"), 'badge endpoint rejects arbitrary learner selection by design');
$assert(!str_contains($statisticsEndpoint, "queryParam('studentId')"), 'statistics endpoint rejects arbitrary learner selection by design');
$assert(str_contains($permissionSeeder, "'student' => [") && str_contains($permissionSeeder, "'badge.read_own'"), 'badge.read_own remains a Student permission');

$assert(str_contains($schoolService, 'JOIN classes c ON c.id = sp.classId'), 'School badge export joins the canonical class boundary');
$assert(str_contains($schoolService, 'WHERE c.schoolId = :schoolId'), 'School badge export is restricted to the authenticated school');
$assert(str_contains($schoolService, 'LEFT JOIN badges b ON b.id = sb.badgeId'), 'School reads canonical Phase 9 badge metadata');
$assert(!str_contains($schoolService, 'sourceEvent'), 'School no longer depends on the legacy badge sourceEvent column');

$assert(str_contains($passportRepository, 'WHERE sb.studentId = :student_id'), 'Talent Passport badge rows are owner-scoped');
$assert(!str_contains($passportRepository, 'SELECT b.*'), 'Talent Passport lists explicit badge columns');

$enterpriseFiles = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root . '/app/enterprise', FilesystemIterator::SKIP_DOTS)
);
foreach ($enterpriseFiles as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
        continue;
    }
    $source = file_get_contents($file->getPathname()) ?: '';
    $assert(!str_contains($source, 'BadgeAwardService'), 'Enterprise has no badge award writer');
    $assert(!str_contains($source, 'INSERT INTO student_badges'), 'Enterprise cannot persist learner badge awards');
}

$assert((string) (getenv('TALENTHUB_AI_VISIBLE_PERCENT') ?: '0') === '0', 'AI outward visibility remains zero during Phase 9');

echo "phase9_cross_role_contract_test: OK\n";
