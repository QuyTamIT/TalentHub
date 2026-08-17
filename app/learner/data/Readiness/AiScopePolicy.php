<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Readiness;

final class AiScopePolicy
{
    private const ALLOWED_PREFIXES = ['app/learner/', 'assets/js/learner', 'tests/learner_', 'docs/superpowers/'];
    private const ALLOWED_EXACT_PATHS = ['assets/css/learner.css'];
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

            if (!in_array($path, self::ALLOWED_EXACT_PATHS, true)
                && !$this->startsWithAny($path, self::ALLOWED_PREFIXES)) {
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
        $withoutForeignKeyActions = preg_replace(
            '/\\bON\\s+DELETE\\s+(?:RESTRICT|CASCADE|SET\\s+NULL|NO\\s+ACTION)\\b/i',
            ' ',
            $withoutComments
        ) ?? $withoutComments;
        $withoutAppendOnlyTriggerHeaders = preg_replace(
            '/\\bCREATE\\s+TRIGGER\\s+[A-Za-z_][A-Za-z0-9_]*\\s+BEFORE\\s+DELETE\\s+ON\\s+[A-Za-z_][A-Za-z0-9_]*/i',
            ' ',
            $withoutForeignKeyActions
        ) ?? $withoutForeignKeyActions;
        $matched = [];

        foreach (self::FORBIDDEN_SQL as $keyword) {
            $inspectable = $keyword === 'DELETE' ? $withoutAppendOnlyTriggerHeaders : $withoutComments;
            if (preg_match('/\\b' . $keyword . '\\b/i', $inspectable) === 1) {
                $matched[] = $keyword;
            }
        }

        return $matched;
    }

    private function normalize(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        if ($path === '') {
            return '';
        }

        // Keep absolute paths and leading traversal visibly non-relative. They
        // must never be reduced into an allowlisted relative path.
        $absolute = str_starts_with($path, '/') || preg_match('/^[A-Za-z]:\//', $path) === 1;
        $parts = explode('/', $path);
        $canonical = [];
        $leadingTraversal = false;
        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                if ($canonical !== [] && end($canonical) !== '..') {
                    array_pop($canonical);
                } else {
                    $leadingTraversal = true;
                }
                continue;
            }
            $canonical[] = $part;
        }

        $normalized = implode('/', $canonical);
        if ($absolute) {
            return '/' . $normalized;
        }
        return $leadingTraversal ? '../' . $normalized : $normalized;
    }

    /** @param list<string> $prefixes */
    private function startsWithAny(string $path, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if ($prefix === 'assets/js/learner') {
                // Learner assets may be the learner directory or a learner-
                // named file (e.g. learner.js or learner-assessment.js), but
                // not sibling lookalikes.
                if ($path === $prefix
                    || str_starts_with($path, $prefix . '/')
                    || str_starts_with($path, $prefix . '.')
                    || str_starts_with($path, $prefix . '-')) {
                    return true;
                }
                continue;
            }
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
