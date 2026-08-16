<?php

declare(strict_types=1);

use TalentHub\Database\Connection;
use TalentHub\Learner\Data\Database\SchemaInspector;

require dirname(__DIR__) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';

function ai_mysql_metadata_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

$schema = getenv('LEARNER_MYSQL_TEST_SCHEMA');
if (!is_string($schema) || preg_match('/\Atalenthub_ai_backup_verify_[a-z0-9_]+\z/', $schema) !== 1) {
    fwrite(STDERR, "learner_ai_mysql_metadata_test requires LEARNER_MYSQL_TEST_SCHEMA=talenthub_ai_backup_verify_<disposable-suffix>; refusing configured database fallback\n");
    exit(2);
}

$databaseConfig = require dirname(__DIR__) . '/config/database.php';
$databaseConfig['database'] = $schema;
$pdo = (new Connection($databaseConfig))->connect();
ai_mysql_metadata_assert((string) $pdo->query('SELECT DATABASE()')->fetchColumn() === $schema, 'connects only to explicit disposable schema');

$inspector = new SchemaInspector($pdo, $schema);
ai_mysql_metadata_assert($inspector->columnType('student_profiles', 'id') === 'CHAR(36)', 'student_profiles.id is CHAR(36)');
ai_mysql_metadata_assert($inspector->hasPrimaryKey('student_profiles', 'id'), 'student_profiles.id is the primary key');
ai_mysql_metadata_assert(
    $inspector->hasMySqlTableOptions('student_profiles', 'InnoDB', 'utf8mb4', 'utf8mb4_unicode_ci'),
    'student_profiles uses InnoDB utf8mb4 utf8mb4_unicode_ci',
);

echo "learner_ai_mysql_metadata_test: OK\n";
