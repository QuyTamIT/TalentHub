<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';

use TalentHub\Config\Environment;
use TalentHub\Database\Connection;
use TalentHub\Database\Migration\MigrationRunner;
use TalentHub\Http\ApiException;
use TalentHub\Modules\School\Service\SchoolAuthorization;
use TalentHub\Rbac\Service\PermissionService;

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException("Assertion failed: {$message}");
    }
};

if (Environment::appEnvironment() !== 'test' || getenv('TALENTHUB_DISPOSABLE_TEST_DB') !== '1') {
    fwrite(STDERR, "Phase 11 requires APP_ENV=test and TALENTHUB_DISPOSABLE_TEST_DB=1\n");
    exit(2);
}

/** @return array{code:int,stdout:string,stderr:string} */
$run = static function (array $command, ?string $stdinFile = null, ?string $stdoutFile = null): array {
    $descriptors = [
        0 => $stdinFile === null ? ['pipe', 'r'] : ['file', $stdinFile, 'r'],
        1 => $stdoutFile === null ? ['pipe', 'w'] : ['file', $stdoutFile, 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($command, $descriptors, $pipes, dirname(__DIR__));
    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start Phase 11 child process.');
    }
    if ($stdinFile === null) {
        fclose($pipes[0]);
    }
    $stdout = '';
    if ($stdoutFile === null) {
        $stdout = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);
    }
    $stderr = (string) stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    return ['code' => proc_close($process), 'stdout' => $stdout, 'stderr' => $stderr];
};

function phase11Id(string $seed): string
{
    $hex = substr(hash('sha256', $seed), 0, 32);
    $hex[12] = '4';
    $hex[16] = '8';

    return sprintf(
        '%s-%s-%s-%s-%s',
        substr($hex, 0, 8),
        substr($hex, 8, 4),
        substr($hex, 12, 4),
        substr($hex, 16, 4),
        substr($hex, 20, 12),
    );
}

/**
 * @return array{
 *   students:list<array{user_id:string,student_id:string,class_id:string,school_id:string}>,
 *   teachers:list<array{user_id:string,teacher_id:string,school_id:string}>,
 *   schools:list<array{user_id:string,school_id:string,class_id:string}>,
 *   enterprises:list<array{user_id:string,enterprise_id:string}>
 * }
 */
function phase11CreateActors(PDO $pdo, string $runId): array
{
    $roles = [];
    foreach ($pdo->query("SELECT id, code FROM roles WHERE code IN ('student','teacher','school','enterprise')")->fetchAll() as $role) {
        $roles[(string) $role['code']] = (string) $role['id'];
    }
    foreach (['student', 'teacher', 'school', 'enterprise'] as $roleCode) {
        if (!isset($roles[$roleCode])) {
            throw new RuntimeException("Missing canonical role {$roleCode}.");
        }
    }

    $now = gmdate('Y-m-d H:i:s.u');
    $passwordHash = password_hash('Phase11-disabled-' . $runId, PASSWORD_BCRYPT);
    if (!is_string($passwordHash)) {
        throw new RuntimeException('Unable to create disposable password hash.');
    }
    $insertSchool = $pdo->prepare('INSERT INTO schools (id,name,status,email,level,studentCount,teacherCount,academicYear,createdAt,updatedAt) VALUES (?,?,\'active\',?,\'THPT\',0,0,\'2026-2027\',?,?)');
    $insertEnterprise = $pdo->prepare('INSERT INTO enterprises (id,name,status,industry,email,verificationStatus,createdAt,updatedAt) VALUES (?,?,\'active\',\'Technology\',?,\'verified\',?,?)');
    $insertClass = $pdo->prepare('INSERT INTO classes (id,schoolId,name,gradeLevel,academicYear,status,createdAt,updatedAt) VALUES (?,?,?,12,\'2026-2027\',\'active\',?,?)');
    $insertUser = $pdo->prepare('INSERT INTO users (id,roleId,email,passwordHash,fullName,status,createdAt,updatedAt) VALUES (?,?,?,?,?,\'active\',?,?)');
    $insertStudent = $pdo->prepare('INSERT INTO student_profiles (id,userId,classId,dateOfBirth,phone,studyStatus,createdAt,updatedAt) VALUES (?,?,?,\'2008-01-01\',?,\'active\',?,?)');
    $insertTeacher = $pdo->prepare('INSERT INTO teacher_profiles (id,userId,schoolId,isSchoolAdmin,phone,specialization,bio,createdAt,updatedAt) VALUES (?,?,?,0,?,\'Phase 11\',\'Disposable release actor\',?,?)');
    $insertSchoolMember = $pdo->prepare('INSERT INTO school_members (id,schoolId,userId,memberRole,createdAt,updatedAt) VALUES (?,?,?,?,?,?)');
    $insertEnterpriseMember = $pdo->prepare('INSERT INTO enterprise_members (id,enterpriseId,userId,memberRole,createdAt,updatedAt) VALUES (?,?,?,\'admin\',?,?)');

    $actors = ['students' => [], 'teachers' => [], 'schools' => [], 'enterprises' => []];
    for ($index = 1; $index <= 2; $index++) {
        $suffix = (string) $index;
        $schoolId = phase11Id("{$runId}:school:{$suffix}");
        $classId = phase11Id("{$runId}:class:{$suffix}");
        $enterpriseId = phase11Id("{$runId}:enterprise:{$suffix}");
        $insertSchool->execute([$schoolId, "Phase 11 School {$suffix}", "phase11+school-org-{$runId}-{$suffix}@example.invalid", $now, $now]);
        $insertClass->execute([$classId, $schoolId, "Phase 11 Class {$suffix}", $now, $now]);
        $insertEnterprise->execute([$enterpriseId, "Phase 11 Enterprise {$suffix}", "phase11+enterprise-org-{$runId}-{$suffix}@example.invalid", $now, $now]);

        $studentUserId = phase11Id("{$runId}:student-user:{$suffix}");
        $studentId = phase11Id("{$runId}:student:{$suffix}");
        $insertUser->execute([$studentUserId, $roles['student'], "phase11+student-{$runId}-{$suffix}@example.invalid", $passwordHash, "Phase 11 Student {$suffix}", $now, $now]);
        $insertStudent->execute([$studentId, $studentUserId, $classId, "+84000001{$suffix}", $now, $now]);
        $actors['students'][] = ['user_id' => $studentUserId, 'student_id' => $studentId, 'class_id' => $classId, 'school_id' => $schoolId];

        $teacherUserId = phase11Id("{$runId}:teacher-user:{$suffix}");
        $teacherId = phase11Id("{$runId}:teacher:{$suffix}");
        $insertUser->execute([$teacherUserId, $roles['teacher'], "phase11+teacher-{$runId}-{$suffix}@example.invalid", $passwordHash, "Phase 11 Teacher {$suffix}", $now, $now]);
        $insertTeacher->execute([$teacherId, $teacherUserId, $schoolId, "+84000002{$suffix}", $now, $now]);
        $insertSchoolMember->execute([phase11Id("{$runId}:teacher-member:{$suffix}"), $schoolId, $teacherUserId, 'member', $now, $now]);
        $actors['teachers'][] = ['user_id' => $teacherUserId, 'teacher_id' => $teacherId, 'school_id' => $schoolId];

        $schoolUserId = phase11Id("{$runId}:school-user:{$suffix}");
        $insertUser->execute([$schoolUserId, $roles['school'], "phase11+school-{$runId}-{$suffix}@example.invalid", $passwordHash, "Phase 11 School Admin {$suffix}", $now, $now]);
        $insertSchoolMember->execute([phase11Id("{$runId}:school-member:{$suffix}"), $schoolId, $schoolUserId, 'admin', $now, $now]);
        $actors['schools'][] = ['user_id' => $schoolUserId, 'school_id' => $schoolId, 'class_id' => $classId];

        $enterpriseUserId = phase11Id("{$runId}:enterprise-user:{$suffix}");
        $insertUser->execute([$enterpriseUserId, $roles['enterprise'], "phase11+enterprise-{$runId}-{$suffix}@example.invalid", $passwordHash, "Phase 11 Enterprise Admin {$suffix}", $now, $now]);
        $insertEnterpriseMember->execute([phase11Id("{$runId}:enterprise-member:{$suffix}"), $enterpriseId, $enterpriseUserId, $now, $now]);
        $actors['enterprises'][] = ['user_id' => $enterpriseUserId, 'enterprise_id' => $enterpriseId];
    }

    return $actors;
}

/** @param callable(bool,string):void $assert */
function phase11VerifyAuthorization(PDO $pdo, array $actors, callable $assert): array
{
    $permissions = new PermissionService($pdo);
    $positive = [
        [$actors['students'][0]['user_id'], 'student_profile.read_own'],
        [$actors['students'][1]['user_id'], 'checkin.create_own'],
        [$actors['teachers'][0]['user_id'], 'activity.create_managed'],
        [$actors['teachers'][1]['user_id'], 'assessment.update_managed'],
        [$actors['schools'][0]['user_id'], 'school_dashboard.read_own'],
        [$actors['schools'][1]['user_id'], 'student_profile.read_own_school'],
        [$actors['enterprises'][0]['user_id'], 'internship_post.create_own_business'],
        [$actors['enterprises'][1]['user_id'], 'internship_application.review_own_business'],
    ];
    foreach ($positive as [$userId, $permission]) {
        $permissions->require($userId, $permission);
        $assert(true, "positive permission {$permission}");
    }

    $denied = [
        [$actors['students'][0]['user_id'], 'activity.create_managed'],
        [$actors['students'][1]['user_id'], 'school_dashboard.read_own'],
        [$actors['teachers'][0]['user_id'], 'internship_post.create_own_business'],
        [$actors['teachers'][1]['user_id'], 'student_profile.update_own'],
        [$actors['schools'][0]['user_id'], 'checkin.create_own'],
        [$actors['schools'][1]['user_id'], 'internship_application.review_own_business'],
        [$actors['enterprises'][0]['user_id'], 'assessment.update_managed'],
        [$actors['enterprises'][1]['user_id'], 'school_dashboard.read_own'],
    ];
    foreach ($denied as [$userId, $permission]) {
        $caught = false;
        try {
            $permissions->require($userId, $permission);
        } catch (ApiException $error) {
            $caught = $error->status === 403 && $error->errorCode === 'PERMISSION_DENIED';
        }
        $assert($caught, "forbidden permission {$permission}");
    }

    $schoolAuthorization = new SchoolAuthorization($pdo);
    $schoolAuthorization->requireWriteAccess($actors['schools'][0]['user_id'], $actors['schools'][0]['school_id']);
    $crossSchoolDenied = false;
    try {
        $schoolAuthorization->requireWriteAccess($actors['schools'][0]['user_id'], $actors['schools'][1]['school_id']);
    } catch (ApiException $error) {
        $crossSchoolDenied = $error->status === 403 && $error->errorCode === 'FORBIDDEN';
    }
    $assert($crossSchoolDenied, 'school admin cannot write another school');

    return ['positive' => count($positive) + 1, 'denied' => count($denied) + 1];
}

$config = require dirname(__DIR__) . '/config/database.php';
$sourceDatabase = (string) ($config['database'] ?? '');
$assert($sourceDatabase === 'talenthub_local', 'source must be talenthub_local');
$timestamp = gmdate('YmdHis');
$targetDatabase = 'talenthub_phase11_rehearsal_' . $timestamp;
$assert(preg_match('/\Atalenthub_phase11_rehearsal_\d{14}\z/', $targetDatabase) === 1, 'safe target name');
$assert($targetDatabase !== 'talenthub_local', 'target must not be primary');

$phpBin = (string) (getenv('TALENTHUB_PHP_EXE') ?: 'D:\\laragon\\bin\\php\\php-8.3.30-Win32-vs16-x64\\php.exe');
$mysqlBin = (string) (getenv('TALENTHUB_MYSQL_EXE') ?: 'D:\\laragon\\bin\\mysql\\mysql-8.4.3-winx64\\bin\\mysql.exe');
$mysqldumpBin = (string) (getenv('TALENTHUB_MYSQLDUMP_EXE') ?: dirname($mysqlBin) . '\\mysqldump.exe');
$assert(is_file($phpBin), 'pinned PHP executable exists');
$assert(is_file($mysqlBin), 'pinned MySQL executable exists');
$assert(is_file($mysqldumpBin), 'pinned mysqldump executable exists');

$rootPdo = new PDO(
    sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $config['host'], $config['port']),
    'root',
    '',
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ],
);
$primaryBefore = [
    'tables' => (int) $rootPdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'talenthub_local' AND table_type = 'BASE TABLE'")->fetchColumn(),
    'migrations' => (int) $rootPdo->query('SELECT COUNT(*) FROM talenthub_local.schema_migrations')->fetchColumn(),
];
$assert($primaryBefore === ['tables' => 61, 'migrations' => 29], 'pinned Phase 11 primary baseline matches');

$backupDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'TalentHubBackups';
if (!is_dir($backupDirectory) && !mkdir($backupDirectory, 0700, true) && !is_dir($backupDirectory)) {
    throw new RuntimeException('Unable to create the Phase 11 backup directory.');
}
$backupPath = $backupDirectory . DIRECTORY_SEPARATOR . "talenthub_local_pre_phase11_{$timestamp}.sql";
$dump = $run([
    $mysqldumpBin,
    '--host=' . $config['host'],
    '--port=' . (string) $config['port'],
    '--user=root',
    '--single-transaction',
    '--routines',
    '--events',
    '--triggers',
    '--hex-blob',
    '--default-character-set=utf8mb4',
    '--set-gtid-purged=OFF',
    $sourceDatabase,
], null, $backupPath);
$assert($dump['code'] === 0, 'mysqldump completed: ' . $dump['stderr']);
$assert(is_file($backupPath) && filesize($backupPath) > 0, 'backup is non-empty');
$backupSha256 = (string) hash_file('sha256', $backupPath);
$assert(preg_match('/\A[a-f0-9]{64}\z/', $backupSha256) === 1, 'backup SHA-256 is valid');
$assert(hash_equals($backupSha256, (string) hash_file('sha256', $backupPath)), 'backup SHA-256 re-verifies');

$failure = null;
$evidence = [];
try {
    $rootPdo->exec("CREATE DATABASE `{$targetDatabase}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    foreach (['127.0.0.1', 'localhost'] as $host) {
        $rootPdo->exec("GRANT ALL PRIVILEGES ON `{$targetDatabase}`.* TO '{$config['username']}'@'{$host}'");
    }

    $restore = $run([
        $mysqlBin,
        '--host=' . $config['host'],
        '--port=' . (string) $config['port'],
        '--user=root',
        '--database=' . $targetDatabase,
    ], $backupPath);
    $assert($restore['code'] === 0, 'backup restore completed: ' . $restore['stderr']);

    $targetConfig = $config;
    $targetConfig['database'] = $targetDatabase;
    $targetPdo = (new Connection($targetConfig))->connect();
    $restored = [
        'tables' => (int) $targetPdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'")->fetchColumn(),
        'migrations' => (int) $targetPdo->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn(),
    ];
    $assert($restored === $primaryBefore, 'restored table and migration counts match primary');

    $runner = new MigrationRunner($targetPdo, dirname(__DIR__) . '/Database/migrations');
    $runner->validate();
    $firstReplay = $runner->migrate();
    $secondReplay = $runner->migrate();
    $assert($firstReplay === [], 'first migration replay is a no-op');
    $assert($secondReplay === [], 'second migration replay is a no-op');
    $runner->validate();

    $actors = phase11CreateActors($targetPdo, $timestamp);
    $assert(count($actors['students']) === 2, 'two disposable students exist');
    $assert(count($actors['teachers']) === 2, 'two disposable teachers exist');
    $assert(count($actors['schools']) === 2, 'two disposable schools exist');
    $assert(count($actors['enterprises']) === 2, 'two disposable enterprises exist');
    $actorUserIds = array_column([
        ...$actors['students'],
        ...$actors['teachers'],
        ...$actors['schools'],
        ...$actors['enterprises'],
    ], 'user_id');
    $assert(count(array_unique($actorUserIds)) === 8, 'all Phase 11 actor users are distinct');
    $authorization = phase11VerifyAuthorization($targetPdo, $actors, $assert);

    $primaryAfter = [
        'tables' => (int) $rootPdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'talenthub_local' AND table_type = 'BASE TABLE'")->fetchColumn(),
        'migrations' => (int) $rootPdo->query('SELECT COUNT(*) FROM talenthub_local.schema_migrations')->fetchColumn(),
    ];
    $assert($primaryAfter === $primaryBefore, 'disposable restore/replay did not mutate primary');

    $evidence = [
        'result' => 'PASS',
        'database' => $targetDatabase,
        'mysql_version' => (string) $targetPdo->query('SELECT VERSION()')->fetchColumn(),
        'backup' => ['path' => $backupPath, 'sha256' => $backupSha256, 'size' => filesize($backupPath)],
        'restored' => $restored,
        'migration_replay' => ['first' => $firstReplay, 'second' => $secondReplay, 'drift' => false],
        'actors' => ['student' => 2, 'teacher' => 2, 'school' => 2, 'enterprise' => 2],
        'authorization' => $authorization,
        'primary_before_after_equal' => true,
        'assertions' => $assertions,
    ];
} catch (Throwable $error) {
    $failure = $error;
} finally {
    if (preg_match('/\Atalenthub_phase11_rehearsal_\d{14}\z/', $targetDatabase) !== 1 || $targetDatabase === 'talenthub_local') {
        throw new RuntimeException('Refusing unsafe Phase 11 cleanup.', previous: $failure);
    }
    foreach (['127.0.0.1', 'localhost'] as $host) {
        try {
            $rootPdo->exec("REVOKE ALL PRIVILEGES ON `{$targetDatabase}`.* FROM '{$config['username']}'@'{$host}'");
        } catch (Throwable) {
        }
    }
    try {
        $rootPdo->exec("DROP DATABASE IF EXISTS `{$targetDatabase}`");
    } catch (Throwable $cleanupError) {
        $failure ??= $cleanupError;
    }
}

$schemaCheck = $rootPdo->prepare('SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = :schema');
$schemaCheck->execute(['schema' => $targetDatabase]);
$grantCheck = $rootPdo->prepare("SELECT COUNT(*) FROM mysql.db WHERE Db = :schema AND User = :user AND Host IN ('127.0.0.1', 'localhost')");
$grantCheck->execute(['schema' => $targetDatabase, 'user' => $config['username']]);
$assert((int) $schemaCheck->fetchColumn() === 0, 'disposable schema cleanup verified');
$assert((int) $grantCheck->fetchColumn() === 0, 'disposable grants cleanup verified');
if ($failure !== null) {
    throw $failure;
}

echo json_encode($evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
echo "student_portal_four_role_e2e_mysql_test: OK; cleanup verified\n";
