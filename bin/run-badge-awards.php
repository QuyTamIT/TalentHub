<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require dirname(__DIR__) . '/app/learner/data/bootstrap.php';

use TalentHub\Config\Environment;
use TalentHub\Database\Connection;
use TalentHub\Database\ProtectedDatabasePolicy;
use TalentHub\Learner\Data\Database\DatabaseBadgeRepository;
use TalentHub\Learner\Data\Database\DatabaseNotificationRepository;
use TalentHub\Learner\Data\Database\DatabaseStatisticsRepository;
use TalentHub\Learner\Data\Service\BadgeAwardService;
use TalentHub\Learner\Data\Service\BadgeRuleEngine;
use TalentHub\Learner\Data\Service\NotificationService;
use TalentHub\Learner\Data\Support\Uuid;

$options = [];

try {
    $rawArguments = array_slice($argv ?? [], 1);
    $options = in_array('--json', $rawArguments, true) ? ['json' => true] : [];
    for ($index = 0, $count = count($rawArguments); $index < $count; $index++) {
        $argument = (string) $rawArguments[$index];
        if (in_array($argument, ['--dry-run', '--apply', '--all', '--json'], true)) {
            continue;
        }
        if (str_starts_with($argument, '--student-id=')) {
            if (trim(substr($argument, strlen('--student-id='))) === '') {
                throw new InvalidArgumentException('--student-id requires a UUID value.');
            }
            continue;
        }
        if ($argument === '--student-id') {
            if (!isset($rawArguments[$index + 1]) || str_starts_with((string) $rawArguments[$index + 1], '--')) {
                throw new InvalidArgumentException('--student-id requires a UUID value.');
            }
            $index++;
            continue;
        }
        throw new InvalidArgumentException("Unknown option: {$argument}");
    }

    $options = getopt('', ['dry-run', 'apply', 'student-id:', 'all', 'json']);

    if (isset($options['dry-run'], $options['apply'])) {
        throw new InvalidArgumentException('Choose exactly one mode: --dry-run or --apply.');
    }
    if (isset($options['student-id'], $options['all'])) {
        throw new InvalidArgumentException('Choose exactly one scope: --student-id or --all.');
    }

    $isApply = isset($options['apply']);
    $isDryRun = isset($options['dry-run']) || !$isApply;
    $isJson = isset($options['json']);
    $studentIdFilter = $options['student-id'] ?? null;
    $allStudents = isset($options['all']);

    if (!$studentIdFilter && !$allStudents) {
        $allStudents = true;
    }

    if ($studentIdFilter !== null) {
        $studentIdFilter = trim((string) $studentIdFilter);
        if (!Uuid::isValid($studentIdFilter)) {
            throw new InvalidArgumentException("Invalid student-id UUID: {$studentIdFilter}");
        }
    }

    $dbConfig = require dirname(__DIR__) . '/config/database.php';
    $connection = new Connection($dbConfig);
    $pdo = $connection->connect();

    $databaseName = (string) ($dbConfig['database'] ?? '');

    if ($isApply && ProtectedDatabasePolicy::isProtected($databaseName)) {
        $approved = getenv('TALENTHUB_PHASE9_PRIMARY_APPLY_APPROVED') ?: ($_ENV['TALENTHUB_PHASE9_PRIMARY_APPLY_APPROVED'] ?? '0');
        if (!ProtectedDatabasePolicy::allowsExplicitPrimaryWrite($databaseName, $approved === '1')) {
            throw new RuntimeException(
                'Direct apply is allowed only on talenthub with '
                . 'TALENTHUB_PHASE9_PRIMARY_APPLY_APPROVED=1; talenthub_local is a read-only backup.',
            );
        }
    }

    $statsRepo = new DatabaseStatisticsRepository($pdo);
    $badgeRepo = new DatabaseBadgeRepository($pdo);
    $notifRepo = new DatabaseNotificationRepository($pdo);
    $notifService = new NotificationService($notifRepo);
    $ruleEngine = new BadgeRuleEngine();
    $awardService = new BadgeAwardService($badgeRepo, $statsRepo, $ruleEngine, $notifService);

    // Get target student IDs
    $targetStudentIds = [];
    if ($studentIdFilter !== null) {
        $stmt = $pdo->prepare('SELECT id FROM student_profiles WHERE id = ? LIMIT 1');
        $stmt->execute([$studentIdFilter]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new RuntimeException("Student profile not found: {$studentIdFilter}");
        }
        $targetStudentIds[] = (string) $row['id'];
    } else {
        $stmt = $pdo->query('SELECT id FROM student_profiles ORDER BY createdAt ASC, id ASC');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $targetStudentIds[] = (string) $row['id'];
        }
    }

    $summary = [
        'mode' => $isApply ? 'apply' : 'dry-run',
        'database' => $databaseName,
        'students_scanned' => count($targetStudentIds),
        'total_eligible_awards' => 0,
        'total_persisted_awards' => 0,
        'student_results' => [],
    ];

    $activeRules = $badgeRepo->activeRules();

    foreach ($targetStudentIds as $sid) {
        if ($isDryRun) {
            $facts = $statsRepo->lifetimeFacts($sid);
            $eligible = [];
            foreach ($activeRules as $item) {
                $badge = $item['badge'];
                $rule = $item['rule'];

                if ($badgeRepo->isAwarded($sid, $badge['id'])) {
                    continue;
                }

                $eval = $ruleEngine->evaluate($rule['thresholdCriteria'], $facts);
                if ($eval['eligible']) {
                    $eligible[] = [
                        'badge_code' => $badge['code'],
                        'badge_name' => $badge['name'],
                        'fact' => $eval['fact'],
                        'current' => $eval['current'],
                        'target' => $eval['target'],
                    ];
                }
            }

            $count = count($eligible);
            $summary['total_eligible_awards'] += $count;
            if ($count > 0) {
                $summary['student_results'][$sid] = [
                    'eligible_count' => $count,
                    'badges' => $eligible,
                ];
            }
        } else {
            $awarded = $awardService->evaluateAndAward($sid, 'system');
            $count = count($awarded);
            $summary['total_persisted_awards'] += $count;
            if ($count > 0) {
                $summary['student_results'][$sid] = [
                    'awarded_count' => $count,
                    'badges' => array_map(static fn(array $a): array => [
                        'badge_code' => $a['badge']['code'],
                        'badge_name' => $a['badge']['name'],
                        'fact' => $a['context']['fact'],
                    ], $awarded),
                ];
            }
        }
    }

    $summary['totalAwards'] = $isDryRun ? $summary['total_eligible_awards'] : $summary['total_persisted_awards'];

    if ($isJson) {
        echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    } else {
        echo "=== TalentHub Phase 9 Badge Award Backfill ===" . PHP_EOL;
        echo "Mode: " . strtoupper($summary['mode']) . PHP_EOL;
        echo "Database: " . $summary['database'] . PHP_EOL;
        echo "Students scanned: " . $summary['students_scanned'] . PHP_EOL;
        if ($isDryRun) {
            echo "Total eligible awards: " . $summary['total_eligible_awards'] . PHP_EOL;
        } else {
            echo "Total persisted awards: " . $summary['total_persisted_awards'] . PHP_EOL;
        }
        echo "Students with awards: " . count($summary['student_results']) . PHP_EOL;
    }

    exit(0);
} catch (Throwable $e) {
    if (isset($options['json'])) {
        echo json_encode(['error' => $e->getMessage()], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    } else {
        fwrite(STDERR, "[FAIL] " . $e->getMessage() . PHP_EOL);
    }
    exit(1);
}
