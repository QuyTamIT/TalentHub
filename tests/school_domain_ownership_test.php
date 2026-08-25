<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bin/bootstrap.php';

use TalentHub\Http\ApiException;
use TalentHub\Modules\School\Repository\SchoolPartnershipRepository;
use TalentHub\Modules\School\Repository\SchoolProjectRepository;
use TalentHub\Modules\School\Service\SchoolAuthorization;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$expectForbidden = static function (callable $operation, string $message) use ($assert): void {
    try {
        $operation();
    } catch (ApiException $exception) {
        $assert($exception->status === 403, $message . ' (wrong status)');
        return;
    }
    throw new RuntimeException($message . ' (no exception)');
};

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec('CREATE TABLE school_members (id TEXT PRIMARY KEY, schoolId TEXT NOT NULL, userId TEXT NOT NULL, memberRole TEXT NOT NULL)');
$pdo->exec('CREATE TABLE teacher_profiles (id TEXT PRIMARY KEY, userId TEXT NOT NULL, schoolId TEXT NOT NULL, isSchoolAdmin INTEGER NOT NULL DEFAULT 0)');
$pdo->exec("INSERT INTO school_members VALUES ('member-a', 'school-a', 'admin-a', 'admin')");
$pdo->exec("INSERT INTO school_members VALUES ('member-b', 'school-b', 'member-b', 'member')");
$pdo->exec("INSERT INTO teacher_profiles VALUES ('teacher-a', 'teacher-user-a', 'school-a', 0)");

$projects = new SchoolProjectRepository($pdo);
$partnerships = new SchoolPartnershipRepository($pdo);
$authorization = new SchoolAuthorization($pdo);

$assert($projects->schoolIdForUser('admin-a') === 'school-a', 'Project ownership must resolve the actor school membership.');
$assert($partnerships->schoolIdForUser('admin-a') === 'school-a', 'Partnership ownership must resolve the actor school membership.');
$expectForbidden(fn () => $projects->schoolIdForUser('unknown-user'), 'Project ownership must not fall back to the first school.');
$expectForbidden(fn () => $partnerships->schoolIdForUser('unknown-user'), 'Partnership ownership must not fall back to the first school.');

$authorization->requireWriteAccess('admin-a', 'school-a');
$expectForbidden(fn () => $authorization->requireWriteAccess('admin-a', 'school-b'), 'A school admin must not write another school.');
$expectForbidden(fn () => $authorization->requireWriteAccess('member-b', 'school-b'), 'A non-admin school member must not write school data.');

$projectSource = file_get_contents(dirname(__DIR__) . '/src/Modules/School/Repository/SchoolProjectRepository.php');
$partnershipSource = file_get_contents(dirname(__DIR__) . '/src/Modules/School/Repository/SchoolPartnershipRepository.php');
$schoolSource = file_get_contents(dirname(__DIR__) . '/src/Modules/School/Repository/SchoolRepository.php');
$assert($projectSource !== false && !str_contains($projectSource, 'SELECT id FROM schools LIMIT 1'), 'Project repository must not use a global school fallback.');
$assert($partnershipSource !== false && !str_contains($partnershipSource, "SELECT id FROM schools WHERE status = 'active'"), 'Partnership repository must not use a global school fallback.');
$assert(str_contains($partnershipSource, 'sm.schoolId = :schoolId'), 'School notification recipients must be scoped to the partnership school.');
$assert($schoolSource !== false && !str_contains($schoolSource, 'ORDER BY s.name LIMIT 1'), 'School dashboard must not assign the first active school to a legacy user.');

echo "school_domain_ownership_test: OK\n";
