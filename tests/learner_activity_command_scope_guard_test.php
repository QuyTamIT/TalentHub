<?php

declare(strict_types=1);

$source = file_get_contents(dirname(__DIR__) . '/app/learner/data/Database/DatabaseActivityCommandRepository.php');
if (!is_string($source)) {
    fwrite(STDERR, "Missing activity command repository.\n");
    exit(1);
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
};

$assert(
    str_contains($source, "if (\$this->isSqlite() && \$this->hasLegacySqliteSchoolScopeFallback())"),
    'Only a legacy SQLite fixture may bypass a missing school-scope schema.'
);
$assert(
    str_contains($source, "return !\$this->hasTable('classes')")
        && str_contains($source, "!\$this->hasColumn('student_profiles', 'classId')")
        && str_contains($source, "!\$this->hasColumn('activities', 'schoolId')"),
    'SQLite compatibility requires the legacy fixture to lack the whole school ownership shape.'
);
$assert(
    str_contains($source, "ACTIVITY_SCHOOL_SCOPE_UNAVAILABLE"),
    'A non-SQLite missing school-scope schema fails closed with a stable error code.'
);
$assert(
    !str_contains($source, "if (!\$this->hasSchoolScopeSchema()) {\n            return;"),
    'A missing school-scope schema must never silently bypass registration authorization.'
);
$assert(
    str_contains($source, "assertStudentCanJoinActivity(\$studentId")
        && str_contains($source, "\$this->assertRegistrationWindow"),
    'School authorization occurs after activity lock/read and before window, capacity, audit, or insert.'
);

require dirname(__DIR__) . '/bin/bootstrap.php';
require dirname(__DIR__) . '/app/learner/data/bootstrap.php';

final class Phase51MysqlModePdo extends PDO
{
    public function __construct(private PDO $inner)
    {
    }

    public function getAttribute(int $attribute): mixed
    {
        return $attribute === PDO::ATTR_DRIVER_NAME ? 'mysql' : $this->inner->getAttribute($attribute);
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $normalized = strtolower($query);
        if (str_contains($normalized, 'information_schema.columns')) {
            return $this->inner->prepare('SELECT 0 WHERE :table IS NULL AND :column IS NULL', $options);
        }
        if (str_contains($normalized, 'information_schema.tables')) {
            return $this->inner->prepare('SELECT 0 WHERE :table IS NULL', $options);
        }

        return $this->inner->prepare(str_replace(' FOR UPDATE', '', $query), $options);
    }

    public function beginTransaction(): bool
    {
        return $this->inner->beginTransaction();
    }

    public function inTransaction(): bool
    {
        return $this->inner->inTransaction();
    }

    public function rollBack(): bool
    {
        return $this->inner->rollBack();
    }
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec(<<<'SQL'
CREATE TABLE classes (id TEXT PRIMARY KEY, schoolId TEXT NOT NULL);
CREATE TABLE student_profiles (id TEXT PRIMARY KEY, userId TEXT NOT NULL, classId TEXT NOT NULL);
CREATE TABLE activities (id TEXT PRIMARY KEY, title TEXT NOT NULL, startAt TEXT NOT NULL, endAt TEXT NOT NULL, capacity INTEGER NOT NULL, status TEXT NOT NULL);
CREATE TABLE activity_registration_policies (activityId TEXT PRIMARY KEY, registrationOpensAt TEXT, registrationClosesAt TEXT, cancellationClosesAt TEXT, approvalMode TEXT);
CREATE TABLE activity_registrations (id TEXT PRIMARY KEY, activityId TEXT NOT NULL, studentId TEXT NOT NULL, status TEXT NOT NULL, registeredAt TEXT NOT NULL, updatedAt TEXT NOT NULL, cancelledAt TEXT NULL, cancellationReason TEXT NULL);
CREATE TABLE audit_logs (id TEXT PRIMARY KEY, userId TEXT, action TEXT, entityType TEXT, entityId TEXT, requestId TEXT, ipAddress TEXT, metadata TEXT, createdAt TEXT);
CREATE TABLE notifications (id TEXT PRIMARY KEY, userId TEXT NOT NULL, eventKey TEXT, notificationType TEXT NOT NULL, title TEXT NOT NULL, message TEXT NOT NULL, deepLink TEXT, readAt TEXT, createdAt TEXT);
CREATE TABLE learner_notification_preferences (studentId TEXT NOT NULL, notificationType TEXT NOT NULL, inAppEnabled INTEGER NOT NULL, emailEnabled INTEGER NOT NULL, updatedAt TEXT NOT NULL, PRIMARY KEY(studentId, notificationType));
SQL
);
$ids = [
    'school' => '10000000-0000-4000-8000-000000000001',
    'class' => '20000000-0000-4000-8000-000000000001',
    'student' => '30000000-0000-4000-8000-000000000001',
    'user' => '40000000-0000-4000-8000-000000000001',
    'activity' => '50000000-0000-4000-8000-000000000001',
];
$pdo->prepare('INSERT INTO classes (id, schoolId) VALUES (?, ?)')->execute([$ids['class'], $ids['school']]);
$pdo->prepare('INSERT INTO student_profiles (id, userId, classId) VALUES (?, ?, ?)')->execute([$ids['student'], $ids['user'], $ids['class']]);
$pdo->prepare('INSERT INTO activities (id, title, startAt, endAt, capacity, status) VALUES (?, ?, ?, ?, ?, ?)')->execute([$ids['activity'], 'Scoped guard fixture', '2099-01-02 09:00:00', '2099-01-02 11:00:00', 0, 'published']);
$pdo->prepare('INSERT INTO activity_registration_policies VALUES (?, ?, ?, ?, ?)')->execute([$ids['activity'], '2099-01-01 00:00:00', '2099-01-01 11:00:00', '2099-01-01 11:00:00', 'automatic']);

$repository = new \TalentHub\Learner\Data\Database\DatabaseActivityCommandRepository($pdo);
try {
    $repository->register(
        $ids['student'],
        $ids['user'],
        'phase51-missing-scope',
        $ids['activity'],
        new DateTimeImmutable('2099-01-01 12:00:00', new DateTimeZone('UTC')),
    );
    $assert(false, 'A partial SQLite ownership schema must fail closed.');
} catch (\TalentHub\Http\ApiException $exception) {
    $assert($exception->status === 503, 'Missing scope schema returns 503 before registration window and capacity checks.');
    $assert($exception->errorCode === 'ACTIVITY_SCHOOL_SCOPE_UNAVAILABLE', 'Missing scope schema returns the stable error code.');
}
foreach (['activity_registrations', 'audit_logs', 'notifications'] as $table) {
    $assert((int) $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn() === 0, "Missing scope schema writes no {$table} rows.");
}

$mysqlModeRepository = new \TalentHub\Learner\Data\Database\DatabaseActivityCommandRepository(new Phase51MysqlModePdo($pdo));
try {
    $mysqlModeRepository->register(
        $ids['student'],
        $ids['user'],
        'phase51-mysql-mode-missing-scope',
        $ids['activity'],
        new DateTimeImmutable('2099-01-01 12:00:00', new DateTimeZone('UTC')),
    );
    $assert(false, 'A non-SQLite database mode must never use the compatibility fallback.');
} catch (\TalentHub\Http\ApiException $exception) {
    $assert($exception->status === 503, 'Non-SQLite missing scope schema returns 503 before every write.');
    $assert($exception->errorCode === 'ACTIVITY_SCHOOL_SCOPE_UNAVAILABLE', 'Non-SQLite mode keeps the stable unavailable code.');
}
foreach (['activity_registrations', 'audit_logs', 'notifications'] as $table) {
    $assert((int) $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn() === 0, "Non-SQLite missing scope writes no {$table} rows.");
}

echo "learner_activity_command_scope_guard_test: OK\n";
