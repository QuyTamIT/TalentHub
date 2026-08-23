<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';
require dirname(__DIR__) . '/app/learner/data/bootstrap.php';

use TalentHub\Database\Connection;
use TalentHub\Learner\Data\Database\DatabaseBadgeRepository;
use TalentHub\Learner\Data\Database\DatabaseNotificationRepository;
use TalentHub\Learner\Data\Database\DatabaseStatisticsRepository;
use TalentHub\Learner\Data\Service\BadgeAwardService;
use TalentHub\Learner\Data\Service\BadgeRuleEngine;
use TalentHub\Learner\Data\Service\NotificationService;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
};

// Check if this process is a child worker
if (isset($argv[1]) && $argv[1] === '--worker') {
    try {
        $dbName = $argv[2] ?? '';
        $studentId = $argv[3] ?? '';
        if (!preg_match('/^talenthub_phase9_(?:rehearsal|test)_\d{14}$/', $dbName) || $dbName === 'talenthub_local') {
            fwrite(STDERR, "Worker refused invalid database name: {$dbName}\n");
            exit(1);
        }

        $config = require dirname(__DIR__) . '/config/database.php';
        $config['database'] = $dbName;
        $pdo = (new Connection($config))->connect();

        $badgeRepo = new DatabaseBadgeRepository($pdo);
        $statsRepo = new DatabaseStatisticsRepository($pdo);
        $notifRepo = new DatabaseNotificationRepository($pdo);
        $notifService = new NotificationService($notifRepo);
        $ruleEngine = new BadgeRuleEngine();
        $awardService = new BadgeAwardService($badgeRepo, $statsRepo, $ruleEngine, $notifService);

        $awarded = $awardService->evaluateAndAward($studentId);
        echo json_encode(['count' => count($awarded)]) . "\n";
        exit(0);
    } catch (Throwable $e) {
        fwrite(STDERR, "Worker exception: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
        exit(1);
    }
}

// Master process
$rawConfig = require dirname(__DIR__) . '/config/database.php';
$rootPdo = new PDO(
    sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $rawConfig['host'], $rawConfig['port']),
    'root',
    '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
);

$timestamp = gmdate('YmdHis');
$disposableDb = "talenthub_phase9_test_{$timestamp}";

$assert(
    preg_match('/^talenthub_phase9_(?:rehearsal|test)_\d{14}$/', $disposableDb) === 1,
    'Disposable database name must match exact regex.'
);
$assert($disposableDb !== 'talenthub_local', 'Disposable database cannot equal talenthub_local.');

try {
    $rootPdo->exec("CREATE DATABASE `{$disposableDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    $rootPdo->exec("GRANT ALL PRIVILEGES ON `{$disposableDb}`.* TO '{$rawConfig['username']}'@'127.0.0.1';");
    $rootPdo->exec("GRANT ALL PRIVILEGES ON `{$disposableDb}`.* TO '{$rawConfig['username']}'@'localhost';");
    $rootPdo->exec("FLUSH PRIVILEGES;");

    $childConfig = $rawConfig;
    $childConfig['database'] = $disposableDb;
    $pdo = (new Connection($childConfig))->connect();

    // Create tables in disposable DB
    $pdo->exec(<<<'SQL'
        CREATE TABLE users (
            id CHAR(36) PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            fullName VARCHAR(255) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE student_profiles (
            id CHAR(36) PRIMARY KEY,
            userId CHAR(36) NOT NULL,
            CONSTRAINT fk_sp_user FOREIGN KEY (userId) REFERENCES users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE badges (
            id CHAR(36) PRIMARY KEY,
            code VARCHAR(64) NOT NULL UNIQUE,
            name VARCHAR(255) NOT NULL,
            category VARCHAR(64) NOT NULL,
            description TEXT NOT NULL,
            iconUrl VARCHAR(500) NULL,
            level INT NOT NULL DEFAULT 1,
            status VARCHAR(32) NOT NULL DEFAULT 'active',
            createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
            updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE badge_rule_definitions (
            id CHAR(36) PRIMARY KEY,
            badgeId CHAR(36) NOT NULL,
            ruleType VARCHAR(64) NOT NULL DEFAULT 'threshold',
            thresholdCriteria JSON NOT NULL,
            version INT NOT NULL DEFAULT 1,
            isActive TINYINT(1) NOT NULL DEFAULT 1,
            createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
            updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
            UNIQUE KEY uq_badge_rules_badge_version (badgeId, version),
            KEY idx_badge_rules_active (isActive, badgeId, version),
            CONSTRAINT fk_brd_badge FOREIGN KEY (badgeId) REFERENCES badges(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE student_badges (
            id CHAR(36) PRIMARY KEY,
            studentId CHAR(36) NOT NULL,
            badgeId CHAR(36) NOT NULL,
            ruleDefinitionId CHAR(36) NOT NULL,
            awardedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
            awardedBy VARCHAR(64) NOT NULL DEFAULT 'system',
            awardContext JSON NOT NULL,
            UNIQUE KEY uq_student_badges_award (studentId, badgeId),
            CONSTRAINT fk_sb_student FOREIGN KEY (studentId) REFERENCES student_profiles(id),
            CONSTRAINT fk_sb_badge FOREIGN KEY (badgeId) REFERENCES badges(id),
            CONSTRAINT fk_sb_rule FOREIGN KEY (ruleDefinitionId) REFERENCES badge_rule_definitions(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE notifications (
            id CHAR(36) PRIMARY KEY,
            userId CHAR(36) NOT NULL,
            eventKey VARCHAR(191) NOT NULL,
            notificationType VARCHAR(64) NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            deepLink VARCHAR(500) NULL,
            readAt DATETIME(6) NULL,
            createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
            UNIQUE KEY uq_notifications_user_event (userId, eventKey)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE learner_notification_preferences (
            id CHAR(36) PRIMARY KEY,
            studentId CHAR(36) NOT NULL,
            notificationType VARCHAR(64) NOT NULL,
            inAppEnabled TINYINT(1) NOT NULL DEFAULT 1,
            emailEnabled TINYINT(1) NOT NULL DEFAULT 0,
            updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
            UNIQUE KEY uq_pref (studentId, notificationType)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE experience_logs (
            id CHAR(36) PRIMARY KEY,
            studentId CHAR(36) NOT NULL,
            activityId CHAR(36) NOT NULL,
            checkinId CHAR(36) NOT NULL,
            hours DECIMAL(7,2) NOT NULL,
            status VARCHAR(32) NOT NULL,
            confirmedAt DATETIME(6) NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE activities (
            id CHAR(36) PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            category VARCHAR(64) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE activity_registrations (
            id CHAR(36) PRIMARY KEY,
            activityId CHAR(36) NOT NULL,
            studentId CHAR(36) NOT NULL,
            status VARCHAR(32) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE checkins (
            id CHAR(36) PRIMARY KEY,
            registrationId CHAR(36) NOT NULL,
            status VARCHAR(32) NOT NULL,
            confirmedAt DATETIME(6) NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE talent_tests (
            id CHAR(36) PRIMARY KEY,
            type VARCHAR(64) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE test_attempts (
            id CHAR(36) PRIMARY KEY,
            testId CHAR(36) NOT NULL,
            studentId CHAR(36) NOT NULL,
            status VARCHAR(32) NOT NULL,
            submittedAt DATETIME(6) NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE test_results (
            id CHAR(36) PRIMARY KEY,
            attemptId CHAR(36) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE assessments (
            id CHAR(36) PRIMARY KEY,
            studentId CHAR(36) NOT NULL,
            status VARCHAR(32) NOT NULL,
            publishedAt DATETIME(6) NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    SQL);

    $userId = '33333333-3333-4333-8333-333333333333';
    $studentId = '44444444-4444-4444-8444-444444444444';
    $badgeId = 'a1000000-0000-4000-8000-000000000001';
    $ruleId = 'b1000000-0000-4000-8000-000000000001';

    $pdo->exec("INSERT INTO users (id, email, fullName) VALUES ('{$userId}', 'concurrent@example.com', 'Concurrent Student');");
    $pdo->exec("INSERT INTO student_profiles (id, userId) VALUES ('{$studentId}', '{$userId}');");
    $pdo->exec("INSERT INTO badges (id, code, name, category, description, level, status) VALUES ('{$badgeId}', 'first_experience', 'Khởi đầu trải nghiệm', 'experience', '1h exp', 1, 'active');");
    $pdo->exec("INSERT INTO badge_rule_definitions (id, badgeId, ruleType, thresholdCriteria, version, isActive) VALUES ('{$ruleId}', '{$badgeId}', 'threshold', '{\"fact\":\"confirmed_experience_hours\",\"operator\":\"gte\",\"value\":1}', 1, 1);");

    // Add 5 hours confirmed experience -> eligible for first_experience
    $pdo->exec("INSERT INTO experience_logs (id, studentId, activityId, checkinId, hours, status, confirmedAt) VALUES ('e1000000-0000-4000-8000-000000000001', '{$studentId}', 'a1000000-0000-4000-8000-000000000001', 'c1000000-0000-4000-8000-000000000001', 5.0, 'confirmed', '2026-08-15 10:00:00.000000');");

    // Launch 8 concurrent worker processes
    $phpExe = 'D:\\laragon\\bin\\php\\php-8.3.30-Win32-vs16-x64\\php.exe';
    if (!is_file($phpExe)) {
        $phpExe = PHP_BINARY;
    }
    $script = __FILE__;
    $handles = [];
    $pipes = [];

    for ($i = 0; $i < 8; $i++) {
        $cmd = "\"{$phpExe}\" \"{$script}\" --worker \"{$disposableDb}\" \"{$studentId}\"";
        $proc = proc_open($cmd, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes[$i]);
        $handles[$i] = $proc;
    }

    $totalAwardCounts = 0;
    for ($i = 0; $i < 8; $i++) {
        $out = stream_get_contents($pipes[$i][1]);
        $err = stream_get_contents($pipes[$i][2]);
        fclose($pipes[$i][0]);
        fclose($pipes[$i][1]);
        fclose($pipes[$i][2]);
        $code = proc_close($handles[$i]);
        $assert($code === 0, "Worker {$i} exited cleanly. Error: {$err} Output: {$out}");

        $data = json_decode(trim($out), true);
        if (isset($data['count'])) {
            $totalAwardCounts += (int) $data['count'];
        }
    }

    // Exactly one worker should have successfully inserted and reported the award
    $assert($totalAwardCounts === 1, "Exactly 1 worker reported an award insert across 8 concurrent executions (got {$totalAwardCounts}).");

    // Verify exactly 1 student_badges row in MySQL
    $stmt = $pdo->query("SELECT COUNT(*) FROM student_badges WHERE studentId = '{$studentId}' AND badgeId = '{$badgeId}'");
    $awardRows = (int) $stmt->fetchColumn();
    $assert($awardRows === 1, "Exactly 1 student_badges row exists in MySQL (got {$awardRows}).");

    // Verify exactly 1 notification row in MySQL
    $stmt = $pdo->query("SELECT COUNT(*) FROM notifications WHERE userId = '{$userId}' AND notificationType = 'badge_awarded'");
    $notifRows = (int) $stmt->fetchColumn();
    $assert($notifRows === 1, "Exactly 1 notification row exists in MySQL (got {$notifRows}).");

    echo "learner_badge_award_mysql_concurrency_test: OK\n";
} finally {
    try {
        $rootPdo->exec("REVOKE ALL PRIVILEGES ON `{$disposableDb}`.* FROM '{$rawConfig['username']}'@'127.0.0.1';");
        $rootPdo->exec("REVOKE ALL PRIVILEGES ON `{$disposableDb}`.* FROM '{$rawConfig['username']}'@'localhost';");
        $rootPdo->exec("DROP DATABASE IF EXISTS `{$disposableDb}`;");
    } catch (Throwable) {
    }
}
