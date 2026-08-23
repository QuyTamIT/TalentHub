<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Readiness;

use TalentHub\Learner\Data\Database\SchemaInspector;

final class ReadinessChecker
{
    public function __construct(private readonly PhaseRequirements $requirements, private readonly GitScopeGuard $scopeGuard)
    {
    }

    public function check(int $phase, string $repositoryRoot, callable $pdoFactory): ReadinessResult
    {
        $definition = $this->requirements->forPhase($phase);
        $result = new ReadinessResult($phase);
        $scope = $this->scopeGuard->inspectWorkspace($repositoryRoot);
        if ($scope['allowed']) {
            $result->addPass('scope', 'No changes in protected role paths.');
        } else {
            foreach ($scope['forbidden_paths'] as $path) {
                $result->addFailure('scope', "Protected role path changed: {$path}");
            }
        }
        foreach ($scope['reviewed_paths'] ?? [] as $path) {
            $result->addPass('scope.reviewed', "Reviewed protected path is unchanged: {$path}");
        }

        if (!$definition['requires_database']) {
            $result->addPass('phase', 'Phase 0 does not require a live database.');
            return $result;
        }

        try {
            $pdo = $pdoFactory();
            if (!$pdo instanceof \PDO) {
                throw new \RuntimeException('PDO factory did not return PDO.');
            }
        } catch (\Throwable) {
            $result->addFailure('database.connection', 'Shared database connection is unavailable.', true);
            return $result;
        }

        $result->addPass('database.connection', 'Shared database connection is available.');
        try {
            $inspector = new SchemaInspector($pdo, (string) $pdo->query('SELECT DATABASE()')->fetchColumn());
            foreach ($definition['tables'] as $table) {
                if (!$inspector->hasTable($table)) {
                    $result->addFailure('schema.table', "Missing table: {$table}");
                }
            }
            foreach ($definition['columns'] as $table => $columns) {
                foreach ($columns as $column) {
                    if (!$inspector->hasColumn($table, $column)) {
                        $result->addFailure('schema.column', "Missing column: {$table}.{$column}");
                    }
                }
            }
            foreach ($definition['indexes'] as $table => $indexes) {
                foreach ($indexes as $index) {
                    if (!$inspector->hasIndex($table, $index)) {
                        $result->addFailure('schema.index', "Missing index: {$table}.{$index}");
                    }
                }
            }
            foreach ($definition['foreign_keys'] ?? [] as $table => $foreignKeys) {
                foreach ($foreignKeys as $foreignKey) {
                    if (!$inspector->hasForeignKey($table, $foreignKey['table'], $foreignKey['from'], $foreignKey['to'])) {
                        $result->addFailure(
                            'schema.foreign_key',
                            "Missing foreign key: {$table}.{$foreignKey['from']} -> {$foreignKey['table']}.{$foreignKey['to']}",
                        );
                    }
                }
            }
            foreach (array_keys($definition['optional_table_groups'] ?? []) as $group) {
                $status = TalentPassportOptionalSchema::status($inspector, $group);
                $message = match ($status) {
                    'available' => "Optional capability {$group} is available.",
                    'absent' => "Optional capability {$group} is unavailable (cleanly absent).",
                    'partial' => "Optional capability {$group} is unavailable (partially present).",
                    default => "Optional capability {$group} is unavailable (incompatible schema).",
                };
                $result->addPass('schema.optional', $message);
            }
        } catch (\Throwable) {
            $result->addFailure('database.schema', 'Shared database schema inspection is unavailable.', true);
        }

        return $result;
    }
}
