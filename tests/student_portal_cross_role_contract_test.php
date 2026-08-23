<?php

declare(strict_types=1);

use TalentHub\Database\Seeds\System\RolePermissionSeeder;
use TalentHub\Learner\Data\Enums\StudentPortalStatusContract;

require dirname(__DIR__) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/Database/seeds/System/RolePermissionSeeder.php';
require dirname(__DIR__) . '/app/learner/data/bootstrap.php';

function contract_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}
");
        exit(1);
    }
}

$root = dirname(__DIR__);
$seeder = new RolePermissionSeeder();
$permissionsByRole = $seeder->expectedPermissionsByRole();
$allPermissions = array_values(array_unique(array_merge(...array_values($permissionsByRole))));

$studentPermissions = $permissionsByRole['student'] ?? [];
$teacherPermissions = $permissionsByRole['teacher'] ?? [];

contract_assert(in_array('activity_registration.create_own', $studentPermissions, true), 'reuse registration permission');
contract_assert(!in_array('student_activity.register_own', $allPermissions, true), 'reject duplicate permission vocabulary');
contract_assert(in_array('certificate.manage_own', $studentPermissions, true), 'student certificate mutation has exact permission');
contract_assert(in_array('activity_registration.update_managed', $teacherPermissions, true), 'teacher registration transition has exact managed permission');
contract_assert(in_array('notification.manage_preferences_own', $allPermissions, true), 'notification preferences have exact own permission');

$applicationSource = file_get_contents($root . '/src/Bootstrap/Application.php') ?: '';
$learnerApiFiles = glob($root . '/app/learner/api/v1/*.php') ?: [];
contract_assert(str_contains($applicationSource, "'/api/v1/auth/csrf'"), 'shared auth CSRF route is canonical');
contract_assert(str_contains($applicationSource, "'/api/v1/students/me'"), 'shared student profile route is reused');
contract_assert(!is_file($root . '/app/learner/api/v1/session.php'), 'learner must not create duplicate session endpoint');
foreach ($learnerApiFiles as $file) {
    $relative = str_replace('\\', '/', substr($file, strlen($root) + 1));
    contract_assert(str_starts_with($relative, 'app/learner/api/v1/'), 'learner endpoints stay in approved API base');
}

$registrationAliases = StudentPortalStatusContract::activityRegistrationAliases();
$activityAliases = StudentPortalStatusContract::activityAliases();
contract_assert($registrationAliases['registered'] === 'approved', 'UI registered maps to DB approved');
contract_assert($registrationAliases['checked_in'] === 'attended', 'UI checked_in derives from attended/checkin data');
contract_assert(!in_array('registered', StudentPortalStatusContract::canonicalActivityRegistrationStatuses(), true), 'registered is not canonical DB status');
contract_assert($activityAliases['active'] === ['published', 'ongoing'], 'UI active maps to published or ongoing');
contract_assert(!in_array('active', StudentPortalStatusContract::canonicalActivityStatuses(), true), 'active is not canonical DB activity status');
contract_assert(StudentPortalStatusContract::aiVisiblePercent() === '0', 'AI visible percent remains zero by contract default');

contract_assert(\TalentHub\Learner\Data\Enums\ActivityStatus::normalize('ongoing') === \TalentHub\Learner\Data\Enums\ActivityStatus::Ongoing, 'ongoing normalizes to ongoing');
contract_assert(\TalentHub\Learner\Data\Enums\ActivityStatus::normalize('published') === \TalentHub\Learner\Data\Enums\ActivityStatus::Published, 'published normalizes to published');
contract_assert(\TalentHub\Learner\Data\Enums\ActivityStatus::normalize('completed') === \TalentHub\Learner\Data\Enums\ActivityStatus::Completed, 'completed normalizes to completed');
contract_assert(\TalentHub\Learner\Data\Enums\ActivityStatus::normalize('archived') === \TalentHub\Learner\Data\Enums\ActivityStatus::Archived, 'archived normalizes to archived');
contract_assert(\TalentHub\Learner\Data\Enums\ActivityStatus::normalize('cancelled') === \TalentHub\Learner\Data\Enums\ActivityStatus::Unknown, 'cancelled is not a canonical activity status in DB schema');
contract_assert(\TalentHub\Learner\Data\Enums\ActivityStatus::normalize('active') === \TalentHub\Learner\Data\Enums\ActivityStatus::Unknown, 'active is an alias and normalizes to unknown');
contract_assert(\TalentHub\Learner\Data\Enums\ActivityStatus::normalize('closed') === \TalentHub\Learner\Data\Enums\ActivityStatus::Unknown, 'closed is an alias and normalizes to unknown');
contract_assert(\TalentHub\Learner\Data\Enums\ActivityStatus::normalize('unknown_value') === \TalentHub\Learner\Data\Enums\ActivityStatus::Unknown, 'unknown activity input remains unknown');

contract_assert(\TalentHub\Learner\Data\Enums\ActivityRegistrationStatus::normalize('approved') === \TalentHub\Learner\Data\Enums\ActivityRegistrationStatus::Approved, 'approved normalizes to approved');
contract_assert(\TalentHub\Learner\Data\Enums\ActivityRegistrationStatus::normalize('attended') === \TalentHub\Learner\Data\Enums\ActivityRegistrationStatus::Attended, 'attended normalizes to attended');
contract_assert(\TalentHub\Learner\Data\Enums\ActivityRegistrationStatus::normalize('pending') === \TalentHub\Learner\Data\Enums\ActivityRegistrationStatus::Pending, 'pending normalizes to pending');
contract_assert(\TalentHub\Learner\Data\Enums\ActivityRegistrationStatus::normalize('rejected') === \TalentHub\Learner\Data\Enums\ActivityRegistrationStatus::Rejected, 'rejected normalizes to rejected');
contract_assert(\TalentHub\Learner\Data\Enums\ActivityRegistrationStatus::normalize('cancelled') === \TalentHub\Learner\Data\Enums\ActivityRegistrationStatus::Cancelled, 'cancelled normalizes to cancelled');
contract_assert(\TalentHub\Learner\Data\Enums\ActivityRegistrationStatus::normalize('registered') === \TalentHub\Learner\Data\Enums\ActivityRegistrationStatus::Unknown, 'registered is an alias and normalizes to unknown');
contract_assert(\TalentHub\Learner\Data\Enums\ActivityRegistrationStatus::normalize('checked_in') === \TalentHub\Learner\Data\Enums\ActivityRegistrationStatus::Unknown, 'checked_in is an alias and normalizes to unknown');
contract_assert(\TalentHub\Learner\Data\Enums\ActivityRegistrationStatus::normalize('completed') === \TalentHub\Learner\Data\Enums\ActivityRegistrationStatus::Unknown, 'completed registration is an alias and normalizes to unknown');
contract_assert(\TalentHub\Learner\Data\Enums\ActivityRegistrationStatus::normalize('waitlisted') === \TalentHub\Learner\Data\Enums\ActivityRegistrationStatus::Waitlisted, 'waitlisted is canonical in Phase 4');
contract_assert(in_array('waitlisted', StudentPortalStatusContract::canonicalActivityRegistrationStatuses(), true), 'waitlisted belongs to the canonical Phase 4 lifecycle');
contract_assert(\TalentHub\Learner\Data\Enums\ActivityRegistrationStatus::normalize('unknown_value') === \TalentHub\Learner\Data\Enums\ActivityRegistrationStatus::Unknown, 'unknown registration input remains unknown');

$phase3MigrationIds = ['20260821000100', '20260821000200', '20260821000204', '20260821000205', '20260821000206', '20260821000210'];
foreach ($phase3MigrationIds as $id) {
    contract_assert(count(glob($root . '/Database/migrations/' . $id . '_*.php')) === 1, "Phase 3 migration {$id} exists");
}

$phase4MigrationIds = ['20260821000300'];
foreach ($phase4MigrationIds as $id) {
    contract_assert(count(glob($root . '/Database/migrations/' . $id . '_*.php')) === 1, "Phase 4 migration {$id} exists");
}

$phase5MigrationIds = ['20260821000400'];
foreach ($phase5MigrationIds as $id) {
    contract_assert(count(glob($root . '/Database/migrations/' . $id . '_*.php')) === 1, "Phase 5 migration {$id} exists");
}

$phase7MigrationIds = ['20260821000500', '20260821000510', '20260821000520'];
foreach ($phase7MigrationIds as $id) {
    contract_assert(count(glob($root . '/Database/migrations/' . $id . '_*.php')) === 1, "Phase 7 migration {$id} exists");
}

$phase8MigrationIds = ['20260821000600', '20260821000610'];
foreach ($phase8MigrationIds as $id) {
    contract_assert(count(glob($root . '/Database/migrations/' . $id . '_*.php')) === 1, "Phase 8 migration {$id} exists");
}

$futureMigrationIds = ['20260821000700'];
foreach ($futureMigrationIds as $id) {
    contract_assert(glob($root . '/Database/migrations/' . $id . '_*.php') === [], "future migration {$id} remains unclaimed before Phase 9");
}
$plannedMigrationIds = array_merge($phase3MigrationIds, $phase4MigrationIds, $phase5MigrationIds, $phase7MigrationIds, $phase8MigrationIds, $futureMigrationIds);

$planContent = file_get_contents($root . '/docs/superpowers/plans/2026-08-21-student-portal-four-role-completion-revised.md');
contract_assert(is_string($planContent), 'Revised plan file must exist and be readable');
preg_match_all('/(20260821\d{6})_([a-z0-9_]+)\.php/', $planContent, $matches, PREG_SET_ORDER);
contract_assert(!empty($matches), 'Plan must contain planned migration references');

$idToFilenames = [];
foreach ($matches as $match) {
    $id = $match[1];
    $filename = $match[0];
    $idToFilenames[$id][$filename] = true;
}

contract_assert(count($idToFilenames) === count($plannedMigrationIds), 'Plan must define exactly the canonical shared migrations including forward repairs and future reserved versions');
foreach ($idToFilenames as $id => $files) {
    contract_assert(in_array((string) $id, $plannedMigrationIds, true), "migration ID {$id} must belong to reserved list");
    contract_assert(count($files) === 1, "migration ID {$id} cannot be assigned to multiple semantic purposes: " . implode(', ', array_keys($files)));
}

foreach (['001_migration_registry.sql','002_create_ai_input_foundation.php','003_create_ai_input_extensions.php','004_create_recommendation_store.php'] as $migration) {
    contract_assert(is_file($root . '/Database/migrations/learner/' . $migration), "learner migration {$migration} remains present");
}

contract_assert(in_array('student_profile.read_own_school', $permissionsByRole['school'] ?? [], true), 'school reads students through own-school boundary');
contract_assert(in_array('internship_application.read_own_business', $permissionsByRole['enterprise'] ?? [], true), 'enterprise reads applications through own-business boundary');
contract_assert(in_array('talent.read_consented', $permissionsByRole['enterprise'] ?? [], true), 'enterprise talent access remains consented');

$shareEndpoint = file_get_contents($root . '/app/learner/api/v1/profile-shares.php') ?: '';
contract_assert(!str_contains($shareEndpoint, "query('studentId')"), 'profile sharing never accepts an arbitrary Student selector');
contract_assert(!str_contains($shareEndpoint, "json()['studentId']"), 'profile sharing derives Student ownership from session');
contract_assert(str_contains($shareEndpoint, 'student_profile.share_own'), 'profile sharing uses dedicated Student permission');
contract_assert(str_contains($shareEndpoint, 'privacy_consent.manage_own'), 'sharing mutations use dedicated consent permission');

$enterprisePhpFiles = [];
$enterpriseIterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root . '/app/enterprise', FilesystemIterator::SKIP_DOTS),
);
foreach ($enterpriseIterator as $enterpriseFile) {
    if ($enterpriseFile->isFile() && strtolower($enterpriseFile->getExtension()) === 'php') {
        $enterprisePhpFiles[] = $enterpriseFile->getPathname();
    }
}
contract_assert($enterprisePhpFiles !== [], 'Enterprise contract scan covers real pages and includes');
foreach ($enterprisePhpFiles as $enterprisePhpFile) {
    $enterpriseApiSource = file_get_contents($enterprisePhpFile) ?: '';
    $enterpriseExecutableSource = '';
    foreach (token_get_all($enterpriseApiSource) as $token) {
        if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }
        $enterpriseExecutableSource .= is_array($token) ? $token[1] : $token;
    }
    contract_assert(
        !str_contains($enterpriseApiSource, 'student_profile_shares') || str_contains($enterpriseApiSource, 'talent.read_consented'),
        'Enterprise cannot read profile shares without talent.read_consented'
    );
    contract_assert(
        preg_match('/\b(?:FROM|JOIN)\s+`?(?:student_profiles|student_profile_shares|privacy_consents)\b/i', $enterpriseExecutableSource) !== 1,
        'Phase 3 Enterprise executable code cannot query Student/profile-share/consent tables before the Phase 7 consent consumer exists',
    );
}
$enterpriseTalentData = file_get_contents($root . '/app/enterprise/includes/talents-data.php') ?: '';
contract_assert(!str_contains($enterpriseTalentData, 'loadStudentProfileFromDb'), 'Phase 3 Enterprise talent fixtures never fall through to arbitrary Student DB lookup');
contract_assert(!str_contains($enterpriseTalentData, 'FROM student_profiles'), 'Phase 3 Enterprise talent include does not query canonical Student profiles');
contract_assert(!str_contains($enterpriseTalentData, 'sp.dateOfBirth'), 'Phase 3 Enterprise mock boundary cannot select Student date of birth');
contract_assert(!str_contains($enterpriseTalentData, 'sp.phone'), 'Phase 3 Enterprise mock boundary cannot select Student phone');

$phase3Report = file_get_contents($root . '/docs/superpowers/readiness/2026-08-22-phase-3-profile-evidence-sharing-review-report.md') ?: '';
contract_assert(
    str_contains($phase3Report, 'Enterprise Talent Explorer database integration is deferred to Phase 7'),
    'Phase 3 report states the Enterprise consent-read boundary honestly'
);

echo "student_portal_cross_role_contract_test: OK\n";
