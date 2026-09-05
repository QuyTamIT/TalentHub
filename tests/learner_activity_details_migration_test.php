<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';

use TalentHub\Database\Migration\Migration;
use TalentHub\Database\Migration\MigrationContext;
use TalentHub\Database\Connection;

$path = dirname(__DIR__) . '/Database/migrations/20260825000100_create_activity_details.php';
/** @var list<string> $failures */
$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$assert(is_file($path), 'Task 3 activity_details migration exists.');
if (is_file($path)) {
    $source = file_get_contents($path);
    $assert(is_string($source), 'Task 3 activity_details migration source is readable.');
    if (is_string($source)) {
        foreach ([
            'extends AbstractMigration', 'CREATE TABLE activity_details', 'ENGINE=InnoDB', 'DEFAULT CHARSET=utf8mb4', 'utf8mb4_unicode_ci',
            'PRIMARY KEY (activityId)', 'idx_activity_details_scope_category', 'idx_activity_details_responsible_teacher',
            'fk_activity_details_activity', 'fk_activity_details_teacher', 'ON DELETE CASCADE ON UPDATE CASCADE',
            'ON DELETE SET NULL ON UPDATE CASCADE', 'chk_activity_details_scope', 'chk_activity_details_delivery', 'chk_activity_details_fee',
            "audienceScope IN ('school_only')", "deliveryMode IN ('in_person', 'online', 'hybrid')", 'feeAmount >= 0',
            'SELECT @@session.time_zone', "'+00:00'", 'teacher_profiles', 'semanticEquivalentExists', 'assertExistingDetailsContract',
        ] as $fragment) {
            $assert(str_contains($source, $fragment), "Migration contains required contract fragment: {$fragment}.");
        }
        foreach (['experienceHighlights', 'skillTags', 'eligibilityRules', 'benefitItems'] as $jsonColumn) {
            $assert(str_contains($source, "{$jsonColumn} JSON NOT NULL"), "{$jsonColumn} is a native required JSON column.");
        }
        $assert(str_contains($source, "['description', 'text', false, 65535"), 'Existing-table verifier expects MySQL TEXT character_maximum_length 65535.');
        $assert(str_contains($source, 'datetime_precision, extra'), 'Existing-table verifier reads column EXTRA metadata.');
        $assert(str_contains($source, "'default_generated on update current_timestamp(6)'"), 'Existing-table verifier requires updatedAt ON UPDATE CURRENT_TIMESTAMP(6).');
        $assert(str_contains($source, "\$type === 'json'"), 'Existing-table verifier validates native JSON by data type without assuming version-dependent length metadata.');
        $assert(str_contains($source, 'INFORMATION_SCHEMA escapes quoted CHECK literals'), 'Existing-table verifier normalizes MySQL escaped CHECK literals.');
        foreach ([
            'activityId CHAR(36) NOT NULL', 'responsibleTeacherId CHAR(36) NULL', "audienceScope VARCHAR(24) NOT NULL DEFAULT 'school_only'",
            'displayCategory VARCHAR(120) NOT NULL', 'filterCategory VARCHAR(120) NOT NULL', 'summary VARCHAR(500) NOT NULL',
            'description TEXT NOT NULL', 'locationName VARCHAR(255) NOT NULL', 'locationAddress VARCHAR(500) NULL',
            "deliveryMode VARCHAR(24) NOT NULL DEFAULT 'in_person'", 'onlineMeetingUrl VARCHAR(500) NULL',
            'organizerName VARCHAR(255) NOT NULL', 'organizerContact VARCHAR(255) NULL', 'organizerEmail VARCHAR(255) NULL',
            'organizerPhone VARCHAR(30) NULL', 'coverImageUrl VARCHAR(500) NULL', 'coverImageAlt VARCHAR(255) NULL', 'feeAmount DECIMAL(12,2) NOT NULL DEFAULT 0.00',
            "currency CHAR(3) NOT NULL DEFAULT 'VND'", 'targetAudience VARCHAR(255) NOT NULL', 'certificateLabel VARCHAR(255) NULL',
            'createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)', 'updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)',
        ] as $column) {
            $assert(str_contains($source, $column), "Migration declares exact DDL column: {$column}.");
        }
        $assert(str_contains($source, 'exactly 27 canonical columns'), 'Existing-table verifier requires exactly 27 canonical columns.');
        $assert(!str_contains($source, 'exactly 26 canonical columns'), 'Existing 26-column activity_details tables fail closed.');
        $assert(!str_contains($source, 'JSON_VALID('), 'Native JSON columns do not receive invented JSON_VALID checks.');
        $assert(!preg_match('/DROP\s+TABLE|TRUNCATE\s+TABLE|DELETE\s+FROM/i', $source), 'Forward-only migration contains no destructive or data-mutating statement.');
    }
    $migration = require $path;
    $assert($migration instanceof Migration, 'Task 3 migration implements the shared Migration contract.');
    $assert($migration instanceof Migration && !$migration->isReversible(), 'Task 3 migration is forward-only.');
    if ($migration instanceof Migration) {
        $normalizer = new ReflectionMethod($migration, 'normalizeCheck');
        $normalizer->setAccessible(true);
        $assert($normalizer->invoke($migration, "audienceScope IN(\\'school_only\\')") === "audiencescope='school_only'", 'Existing-table verifier removes MySQL INFORMATION_SCHEMA backslash escaping from CHECK literals.');
        $assert(
            $normalizer->invoke($migration, "audienceScope IN ('school_only')")
                === $normalizer->invoke($migration, "(`audienceScope` = _utf8mb4\\'school_only\\')"),
            'Existing-table verifier treats singleton IN and MySQL canonical equality as the same CHECK.'
        );
        $assert(
            $normalizer->invoke($migration, "deliveryMode IN ('in_person', 'online', 'hybrid')")
                === "deliverymodein'in_person','online','hybrid'",
            'Existing-table verifier preserves multi-value delivery IN semantics.'
        );
    }
    $integrationSchema = getenv('TALENTHUB_PHASE4_MIGRATION_TEST_SCHEMA');
    if ($integrationSchema === false || $integrationSchema === '') {
        $integrationSchema = getenv('TALENTHUB_PHASE2_MIGRATION_TEST_SCHEMA');
    }
    if ($integrationSchema !== false && $integrationSchema !== '') {
        $expectedSchemas = ['talenthub_activity_phase2_disposable', 'talenthub_activity_phase4_disposable'];
        if (!in_array((string) $integrationSchema, $expectedSchemas, true)) {
            throw new RuntimeException('Activity details migration integration test refuses every schema except the approved Phase 2/Phase 4 disposable schemas.');
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
        $context = new MigrationContext($pdo);
        $migration->preflight($context);
        $migration->up($context);
        $migration->preflight($context);
        $migration->up($context);

        $columns = $pdo->query(<<<'SQL'
            SELECT column_name, data_type, character_maximum_length, datetime_precision, extra
            FROM information_schema.columns
            WHERE table_schema=DATABASE() AND table_name='activity_details'
        SQL
        )?->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $metadata = [];
        foreach ($columns as $column) {
            $column = array_change_key_case($column, CASE_LOWER);
            $metadata[(string) $column['column_name']] = $column;
        }
        $assert(count($metadata) === 27, 'Disposable MySQL confirms activity_details has exactly 27 columns.');
        $columnNames = array_keys($metadata);
        $coverUrlPosition = array_search('coverImageUrl', $columnNames, true);
        $coverAltPosition = array_search('coverImageAlt', $columnNames, true);
        $assert(
            is_int($coverUrlPosition) && is_int($coverAltPosition) && $coverAltPosition === $coverUrlPosition + 1,
            'Disposable MySQL confirms nullable coverImageAlt is immediately after coverImageUrl.',
        );
        $coverAltMetadata = $metadata['coverImageAlt'] ?? [];
        $assert(
            ($coverAltMetadata['data_type'] ?? null) === 'varchar'
                && (int) ($coverAltMetadata['character_maximum_length'] ?? 0) === 255,
            'Disposable MySQL confirms coverImageAlt is VARCHAR(255).',
        );
        $assert(($metadata['description']['data_type'] ?? null) === 'text' && (int) ($metadata['description']['character_maximum_length'] ?? 0) === 65535, 'Disposable MySQL confirms description is TEXT with exact length metadata 65535.');
        foreach (['experienceHighlights', 'skillTags', 'eligibilityRules', 'benefitItems'] as $jsonColumn) {
            $assert(($metadata[$jsonColumn]['data_type'] ?? null) === 'json', "Disposable MySQL confirms {$jsonColumn} remains native JSON.");
        }
        $assert((int) ($metadata['updatedAt']['datetime_precision'] ?? -1) === 6 && str_contains(strtolower((string) ($metadata['updatedAt']['extra'] ?? '')), 'on update current_timestamp(6)'), 'Disposable MySQL confirms updatedAt DATETIME(6) ON UPDATE metadata.');
        $table = $pdo->query("SELECT engine,table_collation FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='activity_details'")?->fetch(PDO::FETCH_ASSOC);
        if (is_array($table)) {
            $table = array_change_key_case($table, CASE_LOWER);
        }
        $assert(is_array($table) && strtoupper((string) $table['engine']) === 'INNODB' && (string) $table['table_collation'] === 'utf8mb4_unicode_ci', 'Disposable MySQL confirms exact activity_details table engine and collation.');
        $names = $pdo->query("SELECT constraint_name FROM information_schema.table_constraints WHERE table_schema=DATABASE() AND table_name='activity_details'")?->fetchAll(PDO::FETCH_COLUMN) ?: [];
        foreach (['PRIMARY', 'fk_activity_details_activity', 'fk_activity_details_teacher', 'chk_activity_details_scope', 'chk_activity_details_delivery', 'chk_activity_details_fee'] as $name) {
            $assert(in_array($name, $names, true), "Disposable MySQL confirms activity_details constraint {$name}.");
        }
        $indexes = $pdo->query(<<<'SQL'
            SELECT index_name,non_unique,GROUP_CONCAT(column_name ORDER BY seq_in_index SEPARATOR ',') columns_list
            FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='activity_details'
            GROUP BY index_name,non_unique
        SQL
        )?->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $indexMap = [];
        foreach ($indexes as $index) {
            $index = array_change_key_case($index, CASE_LOWER);
            $indexMap[(string) $index['index_name']] = [(int) $index['non_unique'], (string) $index['columns_list']];
        }
        $assert(($indexMap['PRIMARY'] ?? null) === [0, 'activityId'], 'Disposable MySQL confirms one-to-one activity_details primary key.');
        $assert(($indexMap['idx_activity_details_scope_category'] ?? null) === [1, 'audienceScope,filterCategory'], 'Disposable MySQL confirms scope/category index columns.');
        $assert(($indexMap['idx_activity_details_responsible_teacher'] ?? null) === [1, 'responsibleTeacherId'], 'Disposable MySQL confirms responsible-teacher index column.');
        $foreignKeys = $pdo->query(<<<'SQL'
            SELECT rc.constraint_name,kcu.column_name,kcu.referenced_table_name,kcu.referenced_column_name,rc.delete_rule,rc.update_rule
            FROM information_schema.referential_constraints rc INNER JOIN information_schema.key_column_usage kcu
              ON kcu.constraint_schema=rc.constraint_schema AND kcu.constraint_name=rc.constraint_name AND kcu.table_name=rc.table_name
            WHERE rc.constraint_schema=DATABASE() AND rc.table_name='activity_details'
        SQL
        )?->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $foreignKeyMap = [];
        foreach ($foreignKeys as $foreignKey) {
            $foreignKey = array_change_key_case($foreignKey, CASE_LOWER);
            $foreignKeyMap[(string) $foreignKey['constraint_name']] = $foreignKey;
        }
        $assert(($foreignKeyMap['fk_activity_details_activity']['column_name'] ?? null) === 'activityId' && ($foreignKeyMap['fk_activity_details_activity']['referenced_table_name'] ?? null) === 'activities' && ($foreignKeyMap['fk_activity_details_activity']['delete_rule'] ?? null) === 'CASCADE' && ($foreignKeyMap['fk_activity_details_activity']['update_rule'] ?? null) === 'CASCADE', 'Disposable MySQL confirms activity details activity FK rules.');
        $assert(($foreignKeyMap['fk_activity_details_teacher']['column_name'] ?? null) === 'responsibleTeacherId' && ($foreignKeyMap['fk_activity_details_teacher']['referenced_table_name'] ?? null) === 'teacher_profiles' && ($foreignKeyMap['fk_activity_details_teacher']['delete_rule'] ?? null) === 'SET NULL' && ($foreignKeyMap['fk_activity_details_teacher']['update_rule'] ?? null) === 'CASCADE', 'Disposable MySQL confirms nullable teacher FK rules.');

        $pdo->exec('ALTER TABLE activity_details DROP COLUMN coverImageAlt');
        try {
            $migration->preflight($context);
            $assert(false, 'Existing legacy 26-column activity_details table must fail closed.');
        } catch (RuntimeException $exception) {
            $assert(str_contains($exception->getMessage(), 'exactly 27 canonical columns'), 'Existing legacy 26-column activity_details fails closed with the exact contract error.');
        }
        $pdo->exec('DROP TABLE activity_details');
        $migration->preflight($context);
        $migration->up($context);
        $restoredColumns = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='activity_details'")->fetchColumn();
        $assert($restoredColumns === 27, 'Disposable MySQL restores the canonical 27-column table after the isolated 26-column rejection rehearsal.');
    }
}

if ($failures !== []) {
    fwrite(STDERR, "learner_activity_details_migration_test: RED\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "learner_activity_details_migration_test: OK\n";
