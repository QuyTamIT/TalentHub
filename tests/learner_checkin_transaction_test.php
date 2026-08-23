<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';
require dirname(__DIR__) . '/app/learner/data/bootstrap.php';

use TalentHub\Learner\Data\Database\DatabaseCheckinRepository;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$database = static function (): PDO {
    $pdo = new PDO('sqlite::memory:', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec(<<<'SQL'
CREATE TABLE student_profiles (id TEXT PRIMARY KEY);
CREATE TABLE activities (id TEXT PRIMARY KEY, title TEXT, category TEXT, startAt TEXT, endAt TEXT, status TEXT);
CREATE TABLE activity_registrations (id TEXT PRIMARY KEY, activityId TEXT, studentId TEXT, status TEXT, updatedAt TEXT);
CREATE TABLE activity_qr_sessions (id TEXT PRIMARY KEY, activityId TEXT, tokenHash TEXT UNIQUE, status TEXT, expiresAt TEXT, maxScans INTEGER, usedScans INTEGER, revokedAt TEXT, updatedAt TEXT);
CREATE TABLE activity_experience_policies (activityId TEXT PRIMARY KEY, confirmedHours NUMERIC NOT NULL);
CREATE TABLE checkins (id TEXT PRIMARY KEY, registrationId TEXT UNIQUE, qrSessionId TEXT, status TEXT, checkedInAt TEXT, confirmedAt TEXT, createdAt TEXT);
CREATE TABLE experience_logs (id TEXT PRIMARY KEY, studentId TEXT, activityId TEXT, checkinId TEXT UNIQUE, hours NUMERIC, status TEXT, auditReason TEXT, confirmedAt TEXT, createdAt TEXT);
CREATE TABLE audit_logs (id TEXT PRIMARY KEY, userId TEXT, action TEXT, entityType TEXT, entityId TEXT, requestId TEXT, ipAddress TEXT, metadata TEXT, createdAt TEXT);

INSERT INTO student_profiles VALUES ('11111111-1111-4111-8111-111111111111');
INSERT INTO activities VALUES ('22222222-2222-4222-8222-222222222222','Failure injection','phase5','2026-08-22 08:00:00','2026-08-22 10:00:00','ongoing');
INSERT INTO activity_registrations VALUES ('33333333-3333-4333-8333-333333333333','22222222-2222-4222-8222-222222222222','11111111-1111-4111-8111-111111111111','approved','2026-08-22 07:00:00');
INSERT INTO activity_qr_sessions VALUES ('44444444-4444-4444-8444-444444444444','22222222-2222-4222-8222-222222222222','TOKEN_HASH','active','2099-08-22 12:00:00',10,0,NULL,'2026-08-22 07:00:00');
INSERT INTO activity_experience_policies VALUES ('22222222-2222-4222-8222-222222222222',2.50);
SQL
    );
    $statement = $pdo->prepare('UPDATE activity_qr_sessions SET tokenHash=?');
    $statement->execute([hash('sha256', 'opaque-failure-token')]);
    return $pdo;
};

$failpoints = [
    'after_checkin_insert' => "CREATE TRIGGER phase5_failure AFTER INSERT ON checkins BEGIN SELECT RAISE(ABORT,'after_checkin_insert'); END",
    'after_scan_increment' => "CREATE TRIGGER phase5_failure AFTER UPDATE OF usedScans ON activity_qr_sessions WHEN NEW.usedScans > OLD.usedScans BEGIN SELECT RAISE(ABORT,'after_scan_increment'); END",
    'after_registration_transition' => "CREATE TRIGGER phase5_failure AFTER UPDATE OF status ON activity_registrations WHEN NEW.status='attended' BEGIN SELECT RAISE(ABORT,'after_registration_transition'); END",
    'after_experience_insert' => "CREATE TRIGGER phase5_failure AFTER INSERT ON experience_logs BEGIN SELECT RAISE(ABORT,'after_experience_insert'); END",
    'before_audit_insert' => "CREATE TRIGGER phase5_failure BEFORE INSERT ON audit_logs BEGIN SELECT RAISE(ABORT,'before_audit_insert'); END",
    'after_audit_insert' => "CREATE TRIGGER phase5_failure AFTER INSERT ON audit_logs BEGIN SELECT RAISE(ABORT,'after_audit_insert'); END",
];

foreach ($failpoints as $name => $triggerSql) {
    $pdo = $database();
    $pdo->exec($triggerSql);
    $failed = false;
    try {
        (new DatabaseCheckinRepository($pdo))->createConfirmed(
            '11111111-1111-4111-8111-111111111111',
            '55555555-5555-4555-8555-555555555555',
            '01JPHASE5ROLLBACKTEST0001',
            hash('sha256', 'opaque-failure-token'),
        );
    } catch (Throwable) {
        $failed = true;
    }
    $assert($failed, "{$name} injects a transaction failure.");
    $assert(!$pdo->inTransaction(), "{$name} closes the failed transaction.");
    $assert((int) $pdo->query('SELECT COUNT(*) FROM checkins')->fetchColumn() === 0, "{$name} leaves no orphan check-in.");
    $assert((int) $pdo->query('SELECT COUNT(*) FROM experience_logs')->fetchColumn() === 0, "{$name} leaves no orphan experience.");
    $assert((int) $pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn() === 0, "{$name} leaves no partial audit.");
    $assert((string) $pdo->query("SELECT status FROM activity_registrations WHERE id='33333333-3333-4333-8333-333333333333'")->fetchColumn() === 'approved', "{$name} restores registration state.");
    $assert((int) $pdo->query("SELECT usedScans FROM activity_qr_sessions WHERE id='44444444-4444-4444-8444-444444444444'")->fetchColumn() === 0, "{$name} restores the scan counter.");
}

echo "learner_checkin_transaction_test: OK\n";
