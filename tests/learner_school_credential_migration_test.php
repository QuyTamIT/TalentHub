<?php

declare(strict_types=1);

$path = dirname(__DIR__) . '/Database/migrations/20260826000100_create_school_credential_catalog.php';
$source = is_file($path) ? (string) file_get_contents($path) : '';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
};

foreach (['schoolId', 'recommendationProfile', 'recommendationEnabled', 'school_certificate_catalog', 'student_school_certificates'] as $required) {
    $assert(str_contains($source, $required), "Missing migration contract: {$required}");
}

$upper = strtoupper($source);
foreach (['DROP TABLE', 'TRUNCATE TABLE', 'DROP COLUMN'] as $forbidden) {
    $assert(!str_contains($upper, $forbidden), "Destructive SQL is forbidden: {$forbidden}");
}

echo "learner_school_credential_migration_test: OK\n";
