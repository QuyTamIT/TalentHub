<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$include = $root . '/app/learner/includes/ecosystem-data.php';
$source = (string) file_get_contents($include);

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$start = strpos($source, 'function learner_ecosystem_school_activities');
$end = $start === false ? false : strpos($source, "if (!function_exists('learner_ecosystem_applications'))", $start);
$assert($start !== false && $end !== false, 'school activity ecosystem function exists');
$function = $start !== false && $end !== false ? substr($source, $start, $end - $start) : '';
$assert(str_contains($function, 'discoverForStudent('), 'ecosystem school activities use student-scoped discovery');
$assert(str_contains($function, 'learner_current_student_id()'), 'ecosystem scope derives from the authenticated learner');
$assert(!str_contains($function, '->all('), 'ecosystem activity route never calls repository all()');
$assert(!str_contains($function, 'findById('), 'ecosystem activity route never calls repository findById()');
$assert(!str_contains($source, 'learner_activity_repository()->all('), 'production ecosystem include never calls learner activity all()');
$assert(!str_contains($source, '$factory->activity()->all('), 'production ecosystem include never calls factory activity all()');
$assert(str_contains($function, '$activitySchoolId === $schoolId'), 'optional school id can only narrow the already-scoped result');

if ($failures !== []) {
    fwrite(STDERR, "learner_ecosystem_activity_scope_test: RED\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "learner_ecosystem_activity_scope_test: OK\n";
