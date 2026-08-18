<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Readiness;

use RuntimeException;

final class GitScopeGuard
{
    private const ALLOWED_PREFIXES = [
        'app/learner/',
        'assets/js/learner',
        'assets/css/learner.css',
        'tests/learner_',
        'docs/superpowers/',
        'Database/migrations/learner/',
        'Database/seeds/learner/',
    ];
    private const ALLOWED_EXACT_PATHS = [
        'src/Rbac/Service/PermissionService.php',
        'tests/permission_service_driver_compatibility_test.php',
    ];
    private const PROTECTED_PREFIXES = ['app/teacher/', 'app/school/', 'app/enterprise/', 'src/', 'api/'];

    /** @param list<string> $paths
     * @return array{allowed:bool,forbidden_paths:list<string>}
     */
    public function inspectPaths(array $paths): array
    {
        $forbidden = [];
        foreach (array_unique(array_map([$this, 'normalize'], $paths)) as $path) {
            if ($path === '' || (!in_array($path, self::ALLOWED_EXACT_PATHS, true) && ($this->startsWith($path, self::PROTECTED_PREFIXES) || !$this->startsWith($path, self::ALLOWED_PREFIXES)))) {
                $forbidden[] = $path;
            }
        }
        sort($forbidden, SORT_STRING);

        return ['allowed' => $forbidden === [], 'forbidden_paths' => $forbidden];
    }

    /** @return array{allowed:bool,forbidden_paths:list<string>} */
    public function inspectWorkspace(string $repositoryRoot): array
    {
        if (!is_dir($repositoryRoot)) {
            return ['allowed' => false, 'forbidden_paths' => ['workspace inspection unavailable']];
        }

        $paths = [];
        foreach ([
            'git -C ' . escapeshellarg($repositoryRoot) . ' diff --name-only --diff-filter=ACMRTUXB',
            'git -C ' . escapeshellarg($repositoryRoot) . ' diff --cached --name-only --diff-filter=ACMRTUXB',
            'git -C ' . escapeshellarg($repositoryRoot) . ' ls-files --others --exclude-standard',
        ] as $command) {
            $output = [];
            $exitCode = 0;
            exec($command . ' 2>NUL', $output, $exitCode);
            if ($exitCode !== 0) {
                return ['allowed' => false, 'forbidden_paths' => ['workspace inspection unavailable']];
            }
            foreach ($output as $path) {
                $paths[] = $path;
            }
        }

        return $this->inspectPaths($paths);
    }

    private function normalize(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $parts = explode('/', $path);
        $normalized = [];
        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                if ($normalized === []) {
                    return '../' . implode('/', $normalized);
                }
                array_pop($normalized);
                continue;
            }
            $normalized[] = $part;
        }
        return implode('/', $normalized);
    }

    /** @param list<string> $prefixes */
    private function startsWith(string $path, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }
        return false;
    }
}
