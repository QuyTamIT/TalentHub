<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';
require dirname(__DIR__) . '/app/learner/data/Enums/Statuses.php';

use TalentHub\Database\Migration\Migration;
use TalentHub\Database\Migration\MigrationContext;
use TalentHub\Database\Connection;
use TalentHub\Learner\Data\Enums\ActivityRegistrationStatus;
use TalentHub\Learner\Data\Enums\StudentPortalStatusContract;

$path = dirname(__DIR__) . '/Database/migrations/20260825000200_add_activity_registration_no_show.php';
/** @var list<string> $failures */
$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$assert(ActivityRegistrationStatus::tryFrom('no_show')?->value === 'no_show', 'ActivityRegistrationStatus has canonical NoShow = no_show.');
$assert(
    StudentPortalStatusContract::canonicalActivityRegistrationStatuses() === ['pending', 'approved', 'rejected', 'cancelled', 'attended', 'waitlisted', 'no_show'],
    'StudentPortalStatusContract appends exactly no_show after the existing six canonical registration statuses.'
);
$assert(
    StudentPortalStatusContract::activityRegistrationAliases() === ['registered' => 'approved', 'checked_in' => 'attended', 'completed' => 'attended'],
    'No-show migration preserves all existing registration aliases.'
);

$assert(is_file($path), 'Task 4 no_show migration exists.');
if (is_file($path)) {
    $source = file_get_contents($path);
    $assert(is_string($source), 'Task 4 no_show migration source is readable.');
    if (is_string($source)) {
        foreach ([
            'extends AbstractMigration', 'activity_registrations', 'SELECT @@session.time_zone', "'+00:00'", 'attendanceResolvedAt DATETIME(6) NULL',
            'attendanceResolutionReason VARCHAR(120) NULL', 'chk_activity_registrations_status', 'chk_activity_registrations_cancellation',
            'uq_activity_registrations_activity_student', 'fk_activity_registrations_activity', 'fk_activity_registrations_student',
            "'pending','approved','rejected','cancelled','attended','waitlisted','no_show'", 'DROP CHECK chk_activity_registrations_status',
            'ADD CONSTRAINT chk_activity_registrations_status', 'unsupported status values', 'isReversible',
        ] as $fragment) {
            $assert(str_contains($source, $fragment), "Migration contains required contract fragment: {$fragment}.");
        }
        $assert(preg_match('/ALTER\s+TABLE\s+activity_registrations\s+DROP\s+CHECK\s+chk_activity_registrations_status\s*,\s*ADD\s+CONSTRAINT\s+chk_activity_registrations_status/is', $source) === 1, 'Status CHECK replacement is one atomic ALTER TABLE statement.');
        $assert(str_contains($source, 'INFORMATION_SCHEMA escapes quoted CHECK literals'), 'No-show verifier normalizes MySQL escaped CHECK literals.');
        $assert(!preg_match('/UPDATE\s+activity_registrations|INSERT\s+INTO\s+activity_registrations|DELETE\s+FROM\s+activity_registrations/i', $source), 'No-show migration does not mutate existing registration rows.');
        $assert(!preg_match('/DROP\s+TABLE|TRUNCATE\s+TABLE/i', $source), 'No-show migration is forward-only and non-destructive.');
    }
    $migration = require $path;
    $assert($migration instanceof Migration, 'Task 4 migration implements the shared Migration contract.');
    $assert($migration instanceof Migration && !$migration->isReversible(), 'Task 4 migration is forward-only.');
    if ($migration instanceof Migration) {
        $normalizer = new ReflectionMethod($migration, 'normalizeCheck');
        $normalizer->setAccessible(true);
        $assert($normalizer->invoke($migration, "status IN(\\'pending\\',\\'no_show\\')") === "statusin'pending','no_show'", 'No-show verifier removes MySQL INFORMATION_SCHEMA backslash escaping from CHECK literals.');
    }
    $integrationSchema = getenv('TALENTHUB_PHASE4_MIGRATION_TEST_SCHEMA');
    if ($integrationSchema === false || $integrationSchema === '') {
        $integrationSchema = getenv('TALENTHUB_PHASE2_MIGRATION_TEST_SCHEMA');
    }
    if ($integrationSchema !== false && $integrationSchema !== '') {
        $expectedSchemas = ['talenthub_activity_phase2_disposable', 'talenthub_activity_phase4_disposable'];
        if (!in_array((string) $integrationSchema, $expectedSchemas, true)) {
            throw new RuntimeException('No-show migration integration test refuses every schema except the approved Phase 2/Phase 4 disposable schemas.');
        }
        $expectedSchema = (string) $integrationSchema;
        if (!hash_equals($expectedSchema, (string) getenv('DB_DATABASE'))) {
            throw new RuntimeException('Phase 2 migration integration test requires inherited DB_DATABASE=talenthub_activity_phase2_disposable.');
        }
        $pdo = (new Connection(require dirname(__DIR__) . '/config/database.php'))->connect();
        $actualSchema = (string) $pdo->query('SELECT DATABASE()')?->fetchColumn();
        if (!hash_equals($expectedSchema, $actualSchema)) {
            throw new RuntimeException('Phase 2 migration integration test connected to a non-disposable schema.');
        }
        $before = $pdo->query('SELECT status,COUNT(*) count FROM activity_registrations GROUP BY status')?->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
        $context = new MigrationContext($pdo);
        $migration->preflight($context);
        $migration->up($context);
        $migration->preflight($context);
        $migration->up($context);
        $after = $pdo->query('SELECT status,COUNT(*) count FROM activity_registrations GROUP BY status')?->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
        $assert($after === $before, 'No-show migration does not mutate existing registration status rows.');
        $columns = $pdo->query(<<<'SQL'
            SELECT column_name,data_type,is_nullable,character_maximum_length,datetime_precision,column_default
            FROM information_schema.columns
            WHERE table_schema=DATABASE() AND table_name='activity_registrations'
              AND column_name IN ('attendanceResolvedAt','attendanceResolutionReason')
        SQL
        )?->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $metadata = [];
        foreach ($columns as $column) {
            $column = array_change_key_case($column, CASE_LOWER);
            $metadata[(string) $column['column_name']] = $column;
        }
        $assert(($metadata['attendanceResolvedAt']['data_type'] ?? null) === 'datetime' && ($metadata['attendanceResolvedAt']['is_nullable'] ?? null) === 'YES' && (int) ($metadata['attendanceResolvedAt']['datetime_precision'] ?? -1) === 6, 'Disposable MySQL confirms nullable attendanceResolvedAt DATETIME(6).');
        $assert(($metadata['attendanceResolutionReason']['data_type'] ?? null) === 'varchar' && ($metadata['attendanceResolutionReason']['is_nullable'] ?? null) === 'YES' && (int) ($metadata['attendanceResolutionReason']['character_maximum_length'] ?? -1) === 120, 'Disposable MySQL confirms nullable attendanceResolutionReason VARCHAR(120).');
        $statusCheck = (string) $pdo->query(<<<'SQL'
            SELECT cc.check_clause FROM information_schema.table_constraints tc
            INNER JOIN information_schema.check_constraints cc ON cc.constraint_schema=tc.constraint_schema AND cc.constraint_name=tc.constraint_name
            WHERE tc.table_schema=DATABASE() AND tc.table_name='activity_registrations' AND tc.constraint_name='chk_activity_registrations_status'
        SQL
        )?->fetchColumn();
        $assert(str_contains(str_replace("\\'", "'", strtolower($statusCheck)), 'no_show'), 'Disposable MySQL confirms named status CHECK accepts no_show.');
        foreach (['chk_activity_registrations_cancellation', 'uq_activity_registrations_activity_student', 'fk_activity_registrations_activity', 'fk_activity_registrations_student'] as $name) {
            $statement = $pdo->prepare('SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema=DATABASE() AND table_name=\'activity_registrations\' AND constraint_name=:name');
            $statement->execute(['name' => $name]);
            $assert((int) $statement->fetchColumn() === 1, "Disposable MySQL retains {$name} metadata.");
        }
        $uniqueIndex = $pdo->query(<<<'SQL'
            SELECT non_unique,GROUP_CONCAT(column_name ORDER BY seq_in_index SEPARATOR ',') columns_list
            FROM information_schema.statistics
            WHERE table_schema=DATABASE() AND table_name='activity_registrations' AND index_name='uq_activity_registrations_activity_student'
            GROUP BY non_unique
        SQL
        )?->fetch(PDO::FETCH_ASSOC);
        if (is_array($uniqueIndex)) {
            $uniqueIndex = array_change_key_case($uniqueIndex, CASE_LOWER);
        }
        $assert(is_array($uniqueIndex) && (int) $uniqueIndex['non_unique'] === 0 && (string) $uniqueIndex['columns_list'] === 'activityId,studentId', 'Disposable MySQL retains the canonical registration identity index.');
        $foreignKeys = $pdo->query(<<<'SQL'
            SELECT rc.constraint_name,kcu.column_name,kcu.referenced_table_name,kcu.referenced_column_name,rc.delete_rule,rc.update_rule
            FROM information_schema.referential_constraints rc INNER JOIN information_schema.key_column_usage kcu
              ON kcu.constraint_schema=rc.constraint_schema AND kcu.constraint_name=rc.constraint_name AND kcu.table_name=rc.table_name
            WHERE rc.constraint_schema=DATABASE() AND rc.table_name='activity_registrations'
              AND rc.constraint_name IN ('fk_activity_registrations_activity','fk_activity_registrations_student')
        SQL
        )?->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $foreignKeyMap = [];
        foreach ($foreignKeys as $foreignKey) {
            $foreignKey = array_change_key_case($foreignKey, CASE_LOWER);
            $foreignKeyMap[(string) $foreignKey['constraint_name']] = $foreignKey;
        }
        $assert(($foreignKeyMap['fk_activity_registrations_activity']['column_name'] ?? null) === 'activityId' && ($foreignKeyMap['fk_activity_registrations_activity']['referenced_table_name'] ?? null) === 'activities' && ($foreignKeyMap['fk_activity_registrations_activity']['delete_rule'] ?? null) === 'NO ACTION' && ($foreignKeyMap['fk_activity_registrations_activity']['update_rule'] ?? null) === 'CASCADE', 'Disposable MySQL retains activity registration activity FK metadata.');
        $assert(($foreignKeyMap['fk_activity_registrations_student']['column_name'] ?? null) === 'studentId' && ($foreignKeyMap['fk_activity_registrations_student']['referenced_table_name'] ?? null) === 'student_profiles' && ($foreignKeyMap['fk_activity_registrations_student']['delete_rule'] ?? null) === 'NO ACTION' && ($foreignKeyMap['fk_activity_registrations_student']['update_rule'] ?? null) === 'CASCADE', 'Disposable MySQL retains activity registration student FK metadata.');
    }
}

if ($failures !== []) {
    fwrite(STDERR, "learner_activity_no_show_migration_test: RED\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "learner_activity_no_show_migration_test: OK\n";
