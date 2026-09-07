<?php

declare(strict_types=1);

$source = (string) file_get_contents(dirname(__DIR__) . '/app/learner/api/v1/assessment-submit.php');
$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$assert(str_contains($source, 'dispatchAiRefresh($studentId)'), 'Fourth-assessment completion must dispatch the learner refresh queue.');
$assert(str_contains($source, "'job_keys'"), 'Assessment response must return server-confirmed refresh job keys.');
$assert(!str_contains($source, "'delivery'=>'transactional_outbox'"), 'Assessment response must not claim a transactional outbox dispatch that did not occur.');
$identity = strpos($source, 'studentIdentityForPermissions');
$mutation = strpos($source, 'mutation(');
$submit = strpos($source, 'assessmentService()->submit');
$dispatch = strpos($source, 'dispatchAiRefresh');
$assert($identity !== false && $mutation !== false && $submit !== false && $dispatch !== false
    && $identity < $mutation && $mutation < $submit && $submit < $dispatch,
    'Refresh dispatch must remain behind authenticated ownership, CSRF mutation, and successful assessment submission.');

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}
echo "learner_ai_assessment_refresh_contract_test: OK\n";
