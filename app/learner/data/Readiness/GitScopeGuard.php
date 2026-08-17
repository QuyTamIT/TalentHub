<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Readiness;

use RuntimeException;

final class GitScopeGuard
{
    /** @var list<string> */
    private const FORBIDDEN_PREFIXES = ['app/teacher/', 'app/school/', 'app/enterprise/'];

    public function inspectWorkspace(string $repositoryRoot): array
    {
        $command = 'git -C ' . escapeshellarg($repositoryRoot) . ' status --porcelain=v1 -z';
        $output = shell_exec($command);
        if ($output === null) {
            throw new RuntimeException('Unable to inspect Git changes for learner scope.');
        }

        $paths = [];
        foreach (explode("\0", $output) as $entry) {
            if ($entry === '') {
                continue;
            }
            if (preg_match('/\A[ MADRCU?!]{2} /', $entry) === 1) {
                $paths[] = substr($entry, 3);
                continue;
            }

            // In -z rename/copy records the original path follows as a second NUL item.
            $paths[] = $entry;
        }

        return $this->inspectPaths($paths);
    }

    /** @param list<string> $paths */
    public function inspectPaths(array $paths): array
    {
        $forbidden = [];
        foreach ($paths as $path) {
            $normalized = str_replace('\\', '/', ltrim($path, '/'));
            foreach (self::FORBIDDEN_PREFIXES as $prefix) {
                if (str_starts_with($normalized, $prefix)) {
                    $forbidden[] = $normalized;
                    break;
                }
            }
        }

        sort($forbidden, SORT_STRING);
        return ['allowed' => $forbidden === [], 'forbidden_paths' => array_values(array_unique($forbidden))];
    }
}
