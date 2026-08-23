<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';

use TalentHub\Database\Connection;

$pdo = (new Connection(require dirname(__DIR__) . '/config/database.php'))->connect();
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$columns = $pdo->query(<<<'SQL'
    SELECT column_name,data_type,column_type,is_nullable,column_default,datetime_precision,
           character_maximum_length,numeric_precision,numeric_scale,column_key,extra
    FROM information_schema.columns
    WHERE table_schema=DATABASE() AND table_name='activity_experience_policies'
    ORDER BY ordinal_position
SQL)?->fetchAll(PDO::FETCH_ASSOC);
$assert(count($columns) === 4, 'Phase 5 policy table has exactly four columns.');
$byName = [];
foreach ($columns as $column) {
    $column = array_change_key_case($column, CASE_LOWER);
    $byName[(string) $column['column_name']] = $column;
}
$assert(($byName['activityId']['column_type'] ?? null) === 'char(36)' && ($byName['activityId']['column_key'] ?? null) === 'PRI', 'activityId exact PK metadata.');
$assert(($byName['confirmedHours']['column_type'] ?? null) === 'decimal(7,2)' && ($byName['confirmedHours']['is_nullable'] ?? null) === 'NO', 'confirmedHours exact decimal metadata.');
foreach (['createdAt', 'updatedAt'] as $timestamp) {
    $assert(($byName[$timestamp]['data_type'] ?? null) === 'datetime' && (int) ($byName[$timestamp]['datetime_precision'] ?? -1) === 6, "{$timestamp} exact DATETIME(6).");
    $assert(strtoupper((string) ($byName[$timestamp]['column_default'] ?? '')) === 'CURRENT_TIMESTAMP(6)', "{$timestamp} exact default.");
}
$assert(stripos((string) $byName['createdAt']['extra'], 'on update') === false, 'createdAt has no ON UPDATE behavior.');
$assert(stripos((string) $byName['updatedAt']['extra'], 'on update CURRENT_TIMESTAMP(6)') !== false, 'updatedAt has exact ON UPDATE behavior.');

$foreignKey = $pdo->query(<<<'SQL'
    SELECT rc.constraint_name,rc.update_rule,rc.delete_rule,kcu.referenced_table_name,kcu.referenced_column_name
    FROM information_schema.referential_constraints rc
    INNER JOIN information_schema.key_column_usage kcu
      ON kcu.constraint_schema=rc.constraint_schema AND kcu.constraint_name=rc.constraint_name AND kcu.table_name=rc.table_name
    WHERE rc.constraint_schema=DATABASE() AND rc.table_name='activity_experience_policies' AND kcu.column_name='activityId'
SQL)?->fetch(PDO::FETCH_ASSOC);
$foreignKey = is_array($foreignKey) ? array_change_key_case($foreignKey, CASE_LOWER) : [];
$assert($foreignKey === [
    'constraint_name' => 'fk_activity_experience_policies_activity',
    'update_rule' => 'CASCADE',
    'delete_rule' => 'CASCADE',
    'referenced_table_name' => 'activities',
    'referenced_column_name' => 'id',
], 'Phase 5 foreign key metadata is exact.');

$check = (string) $pdo->query(<<<'SQL'
    SELECT cc.check_clause
    FROM information_schema.table_constraints tc
    INNER JOIN information_schema.check_constraints cc
      ON cc.constraint_schema=tc.constraint_schema AND cc.constraint_name=tc.constraint_name
    WHERE tc.constraint_schema=DATABASE() AND tc.table_name='activity_experience_policies'
      AND tc.constraint_name='chk_activity_experience_policies_hours' AND tc.constraint_type='CHECK'
SQL)?->fetchColumn();
$normalized = strtolower(preg_replace('/[\s`()]+/', '', $check) ?? '');
$assert($normalized === 'confirmedhours>=0andconfirmedhours<=24', 'Phase 5 hours CHECK expression is exact.');

foreach ([
    ['checkins', 'uq_checkins_registration', 'registrationId'],
    ['experience_logs', 'uq_experience_logs_checkin', 'checkinId'],
] as [$table, $index, $column]) {
    $statement = $pdo->prepare(<<<'SQL'
        SELECT non_unique,GROUP_CONCAT(column_name ORDER BY seq_in_index SEPARATOR ',') columns_list
        FROM information_schema.statistics
        WHERE table_schema=DATABASE() AND table_name=:table AND index_name=:index_name
        GROUP BY non_unique
    SQL);
    $statement->execute(['table' => $table, 'index_name' => $index]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    $row = is_array($row) ? array_change_key_case($row, CASE_LOWER) : [];
    $assert((int) ($row['non_unique'] ?? 1) === 0 && ($row['columns_list'] ?? null) === $column, "{$index} replay barrier is exact.");
}

echo "phase5_applied_schema_contract_test: OK\n";
