<?php
/**
 * Smoke test for the Phase 1 CRUD features of the School dashboard.
 * Runs entirely from CLI - no Apache needed.
 *
 *   php bin/smoke-school-crud.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/bin/bootstrap.php';
require $root . '/Database/seeds/Demo/SchoolDemoSeeder.php';

use TalentHub\Database\Connection;
use TalentHub\Database\Seeds\Demo\SchoolDemoSeeder;
use TalentHub\Modules\School\Repository\SchoolRepository;
use TalentHub\Modules\School\Service\SchoolDashboardService;
use TalentHub\Support\Uuid;

$config = require $root . '/config/database.php';
$pdo    = (new Connection($config))->connect();
$repo   = new SchoolRepository($pdo);
$service = new SchoolDashboardService($repo, $pdo);

$seeder = new SchoolDemoSeeder();
$stmt = $pdo->prepare('SELECT id, email FROM users WHERE email = :email');
$stmt->execute(['email' => $seeder->demoAdminEmail()]);
$admin = $stmt->fetch();

if (!$admin) {
    fwrite(STDERR, '[FAIL] Demo admin not found. Run bin/seed.php --demo first.' . PHP_EOL);
    exit(1);
}

$schoolId = (new \TalentHub\Bootstrap\SchoolAppContext());
// bootstrap cần session; ở CLI, ta dùng trực tiếp admin id
$adminId = (string) $admin['id'];

echo '[INFO] Demo admin: ' . $admin['email'] . PHP_EOL;
echo '[INFO] School id resolved via service: ';
$school = $service->getByUser($adminId);
$schoolId = $school['id'];
echo $schoolId . PHP_EOL;

// --- 1. CRUD Lớp ---
$createdClass = $service->createClass($adminId, [
    'name'         => 'TEST-CLASS',
    'gradeLevel'   => 11,
    'academicYear' => '2025 - 2026',
    'status'       => 'active',
]);
echo '[OK] Created class: ' . $createdClass['id'] . PHP_EOL;

$updated = $service->updateClass($adminId, $createdClass['id'], [
    'name' => 'TEST-CLASS-EDIT',
]);
echo '[OK] Updated class name: ' . $updated['name'] . PHP_EOL;

$archived = $service->archiveClass($adminId, $createdClass['id']);
echo '[OK] Archived class status: ' . $archived['status'] . PHP_EOL;

// --- 2. CRUD Học sinh ---
$tempEmail = 'crud-test-' . bin2hex(random_bytes(3)) . '@talenthub.vn';
$roleStmt = $pdo->prepare('SELECT id FROM roles WHERE code = :code');
$roleStmt->execute(['code' => 'student']);
$studentRoleId = $roleStmt->fetchColumn();
$tempUserId = Uuid::v4();
$pdo->prepare(
    'INSERT INTO users (id, roleId, email, passwordHash, fullName, status)
     VALUES (:id, :roleId, :email, :hash, :fullName, \'active\')'
)->execute([
    'id'       => $tempUserId,
    'roleId'   => $studentRoleId,
    'email'    => $tempEmail,
    'hash'     => password_hash('Temp@1234', PASSWORD_DEFAULT),
    'fullName' => 'Học sinh test',
]);

$firstClass = $repo->listClasses($schoolId)[0];
$studentId = $repo->createStudentProfile($schoolId, [
    'userId'     => $tempUserId,
    'classId'    => $firstClass['id'],
    'dateOfBirth'=> '2008-05-05',
    'phone'      => '0900000999',
]);
echo '[OK] Created student profile: ' . $studentId . PHP_EOL;

$secondClass = $repo->listClasses($schoolId)[1] ?? $firstClass;
$updatedStudent = $service->updateStudent($adminId, $studentId, [
    'classId'    => $secondClass['id'],
    'studyStatus'=> 'active',
]);
echo '[OK] Transferred student to class: ' . $updatedStudent['classId'] . PHP_EOL;

// --- 3. CRUD Giáo viên ---
$inviteEmail = 'crud-tch-' . bin2hex(random_bytes(3)) . '@talenthub.vn';
$invited = $service->inviteTeacher($adminId, [
    'email'         => $inviteEmail,
    'fullName'      => 'Giáo viên Test',
    'isSchoolAdmin' => false,
]);
echo '[OK] Invited teacher profileId: ' . $invited['profileId']
    . ' (invitation expires: ' . $invited['expiresAt'] . ')' . PHP_EOL;

$service->setTeacherAdmin($adminId, $invited['profileId'], true);
echo '[OK] Granted school admin role' . PHP_EOL;
$service->setTeacherActive($adminId, $invited['profileId'], false);
echo '[OK] Deactivated teacher' . PHP_EOL;
$service->setTeacherActive($adminId, $invited['profileId'], true);
echo '[OK] Reactivated teacher' . PHP_EOL;

// --- 4. Reports ---
$report = $service->generateReport($adminId, SchoolDashboardService::REPORT_TYPE_STUDENTS);
echo '[OK] Generated student roster CSV: ' . $report['fileUrl'] . PHP_EOL;

$report2 = $service->generateReport($adminId, SchoolDashboardService::REPORT_TYPE_CLASS);
echo '[OK] Generated class overview CSV: ' . $report2['fileUrl'] . PHP_EOL;

// --- 5. Audit logs ---
$auditCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM audit_logs WHERE entityType IN ('class','student_profile','teacher_profile','report','school')"
)->fetchColumn();
echo '[INFO] Audit logs total: ' . $auditCount . PHP_EOL;
if ($auditCount === 0) {
    fwrite(STDERR, '[FAIL] No audit logs were written.' . PHP_EOL);
    exit(1);
}

echo PHP_EOL . '[OK] All Phase 1 CRUD smoke tests passed.' . PHP_EOL;
