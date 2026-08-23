<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Readiness;

use RuntimeException;

final class GitScopeGuard
{
    public const REVIEWABLE_PROTECTED_PATHS = [
        '.claude/settings.local.json',
        '.qwen/settings.json',
    ];

    private const ALLOWED_PREFIXES = [
        'app/learner/',
        'assets/js/learner',
        'assets/css/learner.css',
        'tests/learner_',
        'tests/student_',
        'tests/phase_3_',
        'tests/phase_4_',
        'tests/phase9_',
        'docs/superpowers/',
        'Database/migrations/',
        'Database/seeds/learner/',
    ];
    private const ALLOWED_EXACT_PATHS = [
        'bin/bootstrap.php',
        'src/Rbac/Service/PermissionService.php',
        'tests/permission_service_driver_compatibility_test.php',
        'tests/qr_session_migration_contract_test.php',
        'Database/seeds/System/RolePermissionSeeder.php',
        'src/Bootstrap/StudentAppContext.php',
        'src/Modules/Student/Repository/StudentRepository.php',
        'src/Modules/Student/Service/StudentProfileService.php',
        'app/enterprise/includes/talents-data.php',
        'app/teacher/activities/index.php',
        'src/Bootstrap/Application.php',
        'src/Modules/Teacher/Repository/TeacherActivityRepository.php',
        'src/Modules/Teacher/Service/TeacherActivityService.php',
        'tests/activity_registration_lifecycle_migration_test.php',
        'tests/teacher_activity_registration_page_contract_test.php',
        'tests/teacher_activity_registration_route_contract_test.php',
        'tests/teacher_activity_registration_transition_test.php',
        'bin/run-badge-awards.php',
        'src/Modules/School/Service/SchoolDashboardService.php',
        'src/Modules/Teacher/Repository/TeacherGradingRepository.php',
    ];
    private const PROTECTED_PREFIXES = ['app/teacher/', 'app/school/', 'app/enterprise/', 'src/', 'api/'];
    private const ALWAYS_DENIED_EXACT_PATHS = ['.env'];
    private const ALWAYS_DENIED_PREFIXES = ['Database/migrations/learner/'];

    /** @param array<string,string> $reviewedProtectedHashes */
    public function __construct(private readonly array $reviewedProtectedHashes = [])
    {
    }

    /** @param list<string> $paths
     * @return array{allowed:bool,forbidden_paths:list<string>}
     */
    public function inspectPaths(array $paths): array
    {
        $forbidden = [];
        foreach (array_unique(array_map([$this, 'normalize'], $paths)) as $path) {
            $alwaysDenied = in_array($path, self::ALWAYS_DENIED_EXACT_PATHS, true)
                || $this->startsWith($path, self::ALWAYS_DENIED_PREFIXES);
            if ($path === '' || $alwaysDenied || (!in_array($path, self::ALLOWED_EXACT_PATHS, true) && ($this->startsWith($path, self::PROTECTED_PREFIXES) || !$this->startsWith($path, self::ALLOWED_PREFIXES)))) {
                $forbidden[] = $path;
            }
        }
        sort($forbidden, SORT_STRING);

        return ['allowed' => $forbidden === [], 'forbidden_paths' => $forbidden];
    }

    /** @return array{allowed:bool,forbidden_paths:list<string>,reviewed_paths:list<string>} */
    public function inspectWorkspace(string $repositoryRoot): array
    {
        if (!is_dir($repositoryRoot)) {
            return ['allowed' => false, 'forbidden_paths' => ['workspace inspection unavailable'], 'reviewed_paths' => []];
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
                return ['allowed' => false, 'forbidden_paths' => ['workspace inspection unavailable'], 'reviewed_paths' => []];
            }
            foreach ($output as $path) {
                $paths[] = $path;
            }
        }

        $reviewed = [];
        $effectivePaths = [];
        foreach (array_unique(array_map([$this, 'normalize'], $paths)) as $path) {
            $expectedHash = $this->reviewedProtectedHashes[$path] ?? null;
            $fullPath = $repositoryRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
            if (in_array($path, self::REVIEWABLE_PROTECTED_PATHS, true)
                && is_string($expectedHash)
                && preg_match('/\A[0-9a-f]{64}\z/i', $expectedHash) === 1
                && is_file($fullPath)) {
                $actualHash = hash_file('sha256', $fullPath);
                if (is_string($actualHash) && hash_equals(strtolower($expectedHash), strtolower($actualHash))) {
                    $reviewed[] = $path;
                    continue;
                }
            }
            $effectivePaths[] = $path;
        }
        sort($reviewed, SORT_STRING);
        $result = $this->inspectPaths($effectivePaths);
        $result['reviewed_paths'] = $reviewed;
        return $result;
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
