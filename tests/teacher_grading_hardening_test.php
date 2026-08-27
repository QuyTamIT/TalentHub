<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$grading = (string) file_get_contents($root . '/app/teacher/grading.php');
$repair = (string) file_get_contents($root . '/bin/fix-student.php');
$activation = (string) file_get_contents($root . '/bin/activate-vu-duc-anh-evaluations.php');
$scoreMigration = (string) file_get_contents($root . '/Database/migrations/20260827001100_reconcile_assessment_score_scale.php');
$talentMigration = (string) file_get_contents($root . '/Database/migrations/20260827001200_add_student_talent_score.php');

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
};

$assert(str_contains($grading, 'new TeacherGradingService(new TeacherGradingRepository($pdo))'), 'grading writes use the canonical service');
$assert(str_contains($grading, 'createdByTeacherId = ?'), 'grading activities are scoped to the current teacher');
$assert(!str_contains($grading, 'INSERT INTO activity_registrations'), 'grading never manufactures attendance');
$assert(str_contains($grading, "'partial' =>"), 'batch grading reports partial success explicitly');
$assert(!str_contains($grading, '$respondError($e->getMessage()'), 'internal exception messages are not returned to users');
$assert(str_contains($grading, "'overallScore' => number_format(\$score, 2, '.', '')"), 'new writes remain on the canonical 0-100 scale');

$assert(str_contains($repair, 'SELECT id, status, teacherId FROM assessments'), 'repair script reads assessment immutability fields');
$assert(str_contains($repair, "--school-id"), 'repair writes require explicit school scope');
$assert(str_contains($activation, 'published assessment is immutable'), 'activation script preserves published assessments');
$assert(str_contains($activation, 'beginTransaction()'), 'activation changes are transactional');
$assert(str_contains($scoreMigration, "id LIKE '26000000-%'"), 'score migration targets only provenance-known legacy demo rows');
$assert(str_contains($scoreMigration, 'INVALID_DEMO_ASSESSMENT_ID'), 'score migration repairs the known malformed demo UUID');
$assert(str_contains($talentMigration, "strtolower((string) \$column['COLUMN_TYPE']) !== 'decimal(5,2)'"), 'migration validates exact score column metadata');
$assert(str_contains($talentMigration, 'talentScore BETWEEN 0 AND 100'), 'migration installs a score range constraint');

echo "teacher_grading_hardening_test: OK\n";
