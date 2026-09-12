<?php
declare(strict_types=1);

require_once __DIR__ . '/../bin/bootstrap.php';

$pdo = (new \TalentHub\Database\Connection(require __DIR__ . '/../config/database.php'))->connect();
$isSqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';

if (!$isSqlite) {
    $stmt = $pdo->query("SELECT CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_NAME = 'chk_project_members_status' AND CONSTRAINT_SCHEMA = DATABASE()");
    $clause = (string) $stmt->fetchColumn();
    if (!str_contains($clause, 'pending') || !str_contains($clause, 'rejected')) {
        fwrite(STDERR, "Assertion failed: CHECK constraint does not contain pending and rejected (got: {$clause})\n");
        exit(1);
    }
}
echo "Task 1 schema test: PASS\n";
