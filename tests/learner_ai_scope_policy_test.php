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
ai_assert(!$policy->inspectPaths(['src/Bootstrap/Application.php'])['allowed'], 'shared source path needs approval');
$migrationPath = 'Database/migrations/learner/002_create_ai_input_foundation.php';
ai_assert($policy->inspectPaths([$migrationPath])['approval_required_paths'] === [$migrationPath], 'database path requires approval');
ai_assert($policy->inspectPaths([$migrationPath], [$migrationPath])['allowed'], 'exact approved database path is allowed');
ai_assert($policy->inspectMigrationText('CREATE TABLE learner_x(id CHAR(36))') === [], 'additive DDL is accepted');
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
