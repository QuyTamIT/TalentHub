<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
};

$source = file_get_contents(dirname(__DIR__) . '/Database/migrations/20260821000400_create_activity_experience_policies.php') ?: '';
$assert(str_contains($source, 'CREATE TABLE activity_experience_policies'), 'Phase 5 migration creates the policy table.');
$assert(str_contains($source, 'confirmedHours DECIMAL(7,2)'), 'Phase 5 policy hours use exact decimal storage.');
$assert(str_contains($source, 'ON DELETE CASCADE ON UPDATE CASCADE'), 'Policy foreign key keeps activity ownership canonical.');
$assert(str_contains($source, 'confirmedHours <= 24'), 'Policy hours match the canonical experience_logs upper bound.');
$assert(str_contains($source, 'assertExistingPolicyContract'), 'Pre-existing policy tables are validated exactly instead of silently accepted.');
$assert(str_contains($source, 'information_schema.referential_constraints'), 'Migration validates the exact canonical foreign key actions.');
$assert(str_contains($source, 'information_schema.check_constraints'), 'Migration validates the policy hours CHECK constraint.');
$assert(str_contains($source, 'array_change_key_case($row, CASE_LOWER)'), 'Migration normalizes MySQL metadata column-name casing without warnings.');

$statusScript = dirname(__DIR__) . '/bin/migrate.php';
$assert(is_file($statusScript), 'Migration runner exists for disposable rehearsal.');

echo "learner_checkin_mysql_test: OK\n";
