<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Readiness;

final class AiScopePolicy
{
    private const ALLOWED_PREFIXES = ['app/learner/', 'assets/js/learner', 'tests/learner_', 'docs/superpowers/'];
    private const PROTECTED_PREFIXES = ['app/teacher/', 'app/school/', 'app/enterprise/', 'src/', 'api/'];
    private const APPROVAL_PREFIXES = ['Database/migrations/learner/', 'Database/seeds/learner/'];
    private const FORBIDDEN_SQL = ['DELETE', 'DROP', 'TRUNCATE', 'RENAME'];

    /**
     * @param list<string> $paths
     * @param list<string> $approvedDatabasePaths
     * @return array{allowed: bool, forbidden_paths: list<string>, approval_required_paths: list<string>}
     */
    public function inspectPaths(array $paths, array $approvedDatabasePaths = []): array
    {
        $approved = array_fill_keys(array_map([$this, 'normalize'], $approvedDatabasePaths), true);
        $forbidden = [];
        $approvalRequired = [];

        foreach (array_unique(array_map([$this, 'normalize'], $paths)) as $path) {
            if ($this->startsWithAny($path, self::PROTECTED_PREFIXES)) {
                $forbidden[] = $path;
                continue;
            }

            if ($this->startsWithAny($path, self::APPROVAL_PREFIXES)) {
                if (!isset($approved[$path])) {
                    $approvalRequired[] = $path;
                }
                continue;
            }

            if (!$this->startsWithAny($path, self::ALLOWED_PREFIXES)) {
                $forbidden[] = $path;
            }
        }

        sort($forbidden);
        sort($approvalRequired);

        return [
            'allowed' => $forbidden === [] && $approvalRequired === [],
            'forbidden_paths' => $forbidden,
            'approval_required_paths' => $approvalRequired,
        ];
    }

    /** @return list<string> */
    public function inspectMigrationText(string $sql): array
    {
        $withoutComments = preg_replace(['~/\*.*?\*/~s', '~--[^\r\n]*~'], ' ', $sql) ?? $sql;
        $matched = [];

        foreach (self::FORBIDDEN_SQL as $keyword) {
            if (preg_match('/\\b' . $keyword . '\\b/i', $withoutComments) === 1) {
                $matched[] = $keyword;
            }
        }

        return $matched;
    }

    private function normalize(string $path): string
    {
        return ltrim(str_replace('\\', '/', trim($path)), './');
    }

    /** @param list<string> $prefixes */
    private function startsWithAny(string $path, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
