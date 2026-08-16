<?php

declare(strict_types=1);

use TalentHub\Learner\Data\Readiness\AiScopePolicy;

$repositoryRoot = dirname(__DIR__, 3);
require_once dirname(__DIR__) . '/data/Readiness/AiScopePolicy.php';

/** @return list<string> */
function ai_scope_audit_git_paths(string $repositoryRoot, array $arguments): array
{
    $paths = [];
    foreach ($arguments as $argument) {
        $output = [];
        $exitCode = 0;
        exec('git -C ' . escapeshellarg($repositoryRoot) . ' ' . $argument, $output, $exitCode);
        if ($exitCode === 0) {
            array_push($paths, ...$output);
        }
    }

    return array_values(array_filter(
        array_unique($paths),
        static fn (string $path): bool => !str_starts_with(str_replace('\\', '/', $path), '.superpowers/sdd/')
    ));
}

/** @return list<string> */
function ai_scope_audit_forbidden_sql(AiScopePolicy $policy, string $repositoryRoot): array
{
    $forbidden = [];
    foreach (['Database/migrations/learner', 'Database/seeds/learner'] as $directory) {
        foreach (glob($repositoryRoot . '/' . $directory . '/*.{php,sql}', GLOB_BRACE) ?: [] as $file) {
            array_push($forbidden, ...$policy->inspectMigrationText((string) file_get_contents($file)));
        }
    }

    $forbidden = array_values(array_unique($forbidden));
    sort($forbidden);
    return $forbidden;
}

$options = getopt('', ['format:']);
$format = strtolower((string) ($options['format'] ?? 'text'));
if (!in_array($format, ['text', 'json'], true)) {
    fwrite(STDERR, "Format must be text or json.\n");
    exit(2);
}

$policy = new AiScopePolicy();
$scope = $policy->inspectPaths(ai_scope_audit_git_paths($repositoryRoot, [
    'diff --name-only --cached',
    'diff --name-only',
    'ls-files --others --exclude-standard',
]));
$forbiddenSql = ai_scope_audit_forbidden_sql($policy, $repositoryRoot);
$allowed = $scope['allowed'] && $forbiddenSql === [];
$payload = [
    'allowed' => $allowed,
    'forbidden_paths' => $scope['forbidden_paths'],
    'approval_required_paths' => $scope['approval_required_paths'],
    'forbidden_sql' => $forbiddenSql,
];

if ($format === 'json') {
    fwrite(STDOUT, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL);
} else {
    fwrite(STDOUT, ($allowed ? 'ALLOWED' : 'POLICY VIOLATION') . PHP_EOL);
    foreach ($payload['forbidden_paths'] as $path) {
        fwrite(STDOUT, "FORBIDDEN PATH: {$path}\n");
    }
    foreach ($payload['approval_required_paths'] as $path) {
        fwrite(STDOUT, "APPROVAL REQUIRED: {$path}\n");
    }
    foreach ($forbiddenSql as $keyword) {
        fwrite(STDOUT, "FORBIDDEN SQL: {$keyword}\n");
    }
}

exit($allowed ? 0 : 2);
