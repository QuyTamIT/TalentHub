<?php

declare(strict_types=1);

use TalentHub\Learner\Data\Readiness\AiScopePolicy;

require_once dirname(__DIR__) . '/app/learner/data/Readiness/AiScopePolicy.php';

function ai_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

$policy = new AiScopePolicy();
ai_assert($policy->inspectPaths(['app/learner/ai/bootstrap.php'])['allowed'], 'learner AI path is allowed');
ai_assert(!$policy->inspectPaths(['app/teacher/index.php'])['allowed'], 'teacher path is forbidden');
ai_assert(
    !$policy->inspectPaths(['app/learner/../../app/teacher/index.php'])['allowed'],
    'canonicalized traversal into teacher path is forbidden'
);
ai_assert(!$policy->inspectPaths(['../app/learner/ai/bootstrap.php'])['allowed'], 'leading traversal is forbidden');
ai_assert(!$policy->inspectPaths(['src/Bootstrap/Application.php'])['allowed'], 'shared source path needs approval');
ai_assert($policy->inspectPaths(['assets/js/learner/app.js'])['allowed'], 'learner asset directory is allowed');
ai_assert($policy->inspectPaths(['assets/js/learner.js'])['allowed'], 'learner asset file is allowed');
ai_assert($policy->inspectPaths(['assets/js/learner-assessment.js'])['allowed'], 'learner hyphenated asset file is allowed');
ai_assert(!$policy->inspectPaths(['assets/js/learner_evil.js'])['allowed'], 'learner asset sibling is forbidden');
ai_assert(!$policy->inspectPaths(['assets/js/learners/'])['allowed'], 'learner asset sibling directory is forbidden');
$migrationPath = 'Database/migrations/learner/002_create_ai_input_foundation.php';
ai_assert($policy->inspectPaths([$migrationPath])['approval_required_paths'] === [$migrationPath], 'database path requires approval');
ai_assert($policy->inspectPaths([$migrationPath], [$migrationPath])['allowed'], 'exact approved database path is allowed');
ai_assert($policy->inspectMigrationText('CREATE TABLE learner_x(id CHAR(36))') === [], 'additive DDL is accepted');
ai_assert($policy->inspectMigrationText('FOREIGN KEY (studentId) REFERENCES student_profiles(id) ON DELETE RESTRICT ON UPDATE CASCADE') === [], 'restrictive foreign-key action is accepted');
ai_assert($policy->inspectMigrationText('CREATE TRIGGER learner_consent_no_delete BEFORE DELETE ON learner_ai_consent_events FOR EACH ROW SIGNAL SQLSTATE \'45000\'') === [], 'append-only trigger delete header is accepted');
ai_assert($policy->inspectMigrationText('CREATE TRIGGER learner_consent_no_delete BEFORE DELETE ON learner_ai_consent_events FOR EACH ROW DELETE FROM users') === ['DELETE'], 'delete DML inside a trigger remains rejected');
ai_assert($policy->inspectMigrationText('DROP TABLE learner_x') === ['DROP'], 'DROP is rejected');
ai_assert($policy->inspectMigrationText('DELETE FROM users') === ['DELETE'], 'DELETE is rejected');
ai_assert($policy->inspectMigrationText('-- DROP TABLE learner_x') === [], 'line comments are ignored');
ai_assert($policy->inspectMigrationText('/* DELETE FROM users */') === [], 'block comments are ignored');
ai_assert(
    $policy->inspectMigrationText('truncate learner_x; rename table learner_x to learner_y') === ['TRUNCATE', 'RENAME'],
    'remaining destructive tokens are case-insensitive'
);
ai_assert($policy->inspectMigrationText('CREATE TABLE dropdown_values(id INT)') === [], 'keywords use whole-word matching');

echo "learner_ai_scope_policy_test: OK\n";
