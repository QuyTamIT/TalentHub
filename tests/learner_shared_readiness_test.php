<?php
declare(strict_types=1);

use TalentHub\Learner\Data\Readiness\PhaseRequirements;

require dirname(__DIR__) . '/bin/bootstrap.php';
require dirname(__DIR__) . '/app/learner/data/bootstrap.php';

function shared_readiness_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

$phase = (new PhaseRequirements())->forPhase(1);

shared_readiness_assert($phase['requires_database'] === true, 'phase 1 requires shared database');
shared_readiness_assert($phase['config_keys'] === [], 'readiness must not define a second TALENTHUB_* database vocabulary');

foreach (['roles', 'permissions', 'users', 'role_permissions', 'schools', 'classes', 'student_profiles'] as $table) {
    shared_readiness_assert(in_array($table, $phase['tables'], true), "phase 1 requires {$table}");
}

shared_readiness_assert($phase['columns']['users'] === ['id', 'roleId', 'email', 'passwordHash', 'fullName', 'status'], 'users uses canonical roleId schema');
shared_readiness_assert($phase['columns']['student_profiles'] === ['id', 'userId', 'classId', 'dateOfBirth', 'phone', 'studyStatus'], 'student profile contract matches shared migration');
shared_readiness_assert(in_array('uq_users_email', $phase['indexes']['users'], true), 'users email uniqueness is required');
shared_readiness_assert(in_array('uq_student_profiles_user', $phase['indexes']['student_profiles'], true), 'one profile per user is required');

echo "learner_shared_readiness_test: OK\n";
