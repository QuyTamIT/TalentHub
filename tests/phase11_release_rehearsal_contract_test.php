<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$e2e = $root . '/tests/student_portal_four_role_e2e_mysql_test.php';
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException("Assertion failed: {$message}");
    }
};

$assert(is_file($e2e), 'Phase 11 E2E test exists');
$source = (string) file_get_contents($e2e);
$assert(str_contains($source, 'TALENTHUB_DISPOSABLE_TEST_DB'), 'explicit disposable gate');
$assert(str_contains($source, 'talenthub_phase11_rehearsal_'), 'allow-listed prefix');
$assert(str_contains($source, "!== 'talenthub_local'"), 'primary destructive guard');
$assert(str_contains($source, 'mysqldump'), 'physical backup');
$assert(str_contains($source, "hash_file('sha256'"), 'backup digest verification');
$assert(str_contains($source, 'finally'), 'cleanup is unconditional');
$assert(str_contains($source, 'DROP DATABASE IF EXISTS'), 'disposable cleanup');
$assert(str_contains($source, 'REVOKE ALL PRIVILEGES'), 'disposable grants are revoked');
$assert(str_contains($source, 'primary_before_after_equal'), 'primary invariants are reported');

echo "phase11_release_rehearsal_contract_test: OK\n";

