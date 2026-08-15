<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';

function student_context_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

$path = dirname(__DIR__) . '/src/Bootstrap/StudentAppContext.php';
student_context_assert(is_file($path), 'StudentAppContext exists');
$source = file_get_contents($path);
student_context_assert(is_string($source), 'StudentAppContext is readable');
student_context_assert(str_contains($source, "['role'] ?? null) !== 'student'"), 'context rejects wrong role');
student_context_assert(str_contains($source, "student_profile.read_own"), 'context checks Student permission');
student_context_assert(str_contains($source, "AuthPortalRouter::destination"), 'context reuses shared portal routing');
student_context_assert(!str_contains($source, 'student-demo-001'), 'production context has no demo identity fallback');
student_context_assert(!str_contains($source, 'TALENTHUB_DB_'), 'context has no second DB configuration');

echo "learner_student_app_context_test: OK\n";
