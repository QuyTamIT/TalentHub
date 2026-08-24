<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Migrations;

use PDO;
use RuntimeException;
use TalentHub\Learner\Data\Database\SchemaInspector;
use TalentHub\Learner\Data\Readiness\AiScopePolicy;

final class LearnerForwardMigrationRunner
{
    private const REGISTRY = 'learner_forward_migrations';
    private const LOCK_NAME = 'talenthub:learner_forward_migrations';

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $directory,
        private readonly SchemaInspector $schemaInspector,
        private readonly AiScopePolicy $scopePolicy = new AiScopePolicy(),
    ) {
    }

    /** @return array<string,array{name:string,checksum:string,description:string,applied:bool}> */
    public function status(): array
    {
        $definitions = $this->definitions();
        $applied = $this->applied();
        foreach ($applied as $version => $checksum) {
            if (!isset($definitions[$version]) || !hash_equals($checksum, LearnerMigrationChecksum::canonical($definitions[$version]->path))) {
                throw new RuntimeException('Applied learner migration drift');
            }
        }

        $status = [];
        foreach ($definitions as $version => $definition) {
            $status[$version] = [
                'name' => $definition->name,
                'checksum' => LearnerMigrationChecksum::canonical($definition->path),
                'description' => $definition->migration->description(),
                'applied' => isset($applied[$version]),
            ];
        }
        return $status;
    }

    /** @param list<string> $approvedVersions
     * @return list<string>
     */
    public function migrateApproved(array $approvedVersions): array
    {
        $definitions = $this->definitions();
        $driver = strtolower((string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
        $locked = false;
        $startedTransaction = false;
        if ($driver === 'mysql') {
            $lock = $this->pdo->prepare('SELECT GET_LOCK(:name, 30)');
            $lock->execute(['name' => self::LOCK_NAME]);
            if ((int) $lock->fetchColumn() !== 1) {
                throw new RuntimeException('Could not acquire learner migration lock');
            }
            $locked = true;
        }

        try {
            $startedTransaction = $driver !== 'mysql' && !$this->pdo->inTransaction();
            if ($startedTransaction) {
                $this->pdo->beginTransaction();
            }

            $applied = $this->applied();
            foreach ($applied as $version => $checksum) {
                if (!isset($definitions[$version]) || !hash_equals($checksum, LearnerMigrationChecksum::canonical($definitions[$version]->path))) {
                    throw new RuntimeException('Applied learner migration drift');
                }
            }

            $approved = array_fill_keys($approvedVersions, true);
            $pending = array_filter($definitions, static fn (ForwardMigrationDefinition $definition): bool => isset($approved[$definition->version]) && !isset($applied[$definition->version]));
            if ($pending === []) {
                if ($startedTransaction) {
                    $this->pdo->commit();
                }
                return [];
            }

            foreach ($pending as $definition) {
                if ($definition->migration instanceof LearnerMigrationPreflight) {
                    $definition->migration->assertBeforeApply($this->schemaInspector);
                }
            }
            $this->ensureRegistry();
            $run = [];
            foreach ($pending as $definition) {
                $this->apply($definition, $driver);
                $run[] = $definition->version;
            }
            if ($startedTransaction) {
                $this->pdo->commit();
            }
            return $run;
        } catch (\Throwable $exception) {
            if ($startedTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        } finally {
            if ($locked) {
                $release = $this->pdo->prepare('SELECT RELEASE_LOCK(:name)');
                $release->execute(['name' => self::LOCK_NAME]);
            }
        }
    }

    /** @return array<string,ForwardMigrationDefinition> */
    private function definitions(): array
    {
        if (!is_dir($this->directory)) {
            throw new RuntimeException('Learner migration directory does not exist');
        }
        $files = glob(rtrim($this->directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.php') ?: [];
        sort($files, SORT_STRING);
        $definitions = [];
        foreach ($files as $path) {
            $filename = basename($path);
            if (preg_match('/^(\d{3}_[a-z][a-z0-9]*(?:_[a-z0-9]+)*)\.php$/', $filename, $match) !== 1) {
                throw new RuntimeException('Invalid learner migration filename: ' . $filename);
            }
            $definition = require $path;
            $definitionPath = $definition instanceof ForwardMigrationDefinition ? realpath($definition->path) : false;
            $migrationPath = realpath($path);
            if (!$definition instanceof ForwardMigrationDefinition || $definition->version !== $match[1] || $definitionPath === false || $migrationPath === false || $definitionPath !== $migrationPath) {
                throw new RuntimeException('Invalid learner migration definition: ' . $filename);
            }
            if ($definition->migration->version() !== $definition->version) {
                throw new RuntimeException('Learner migration version mismatch: ' . $filename);
            }
            if (!LearnerMigrationChecksum::matchesDeclared($path, $definition->checksum)) {
                throw new RuntimeException('Invalid learner migration checksum: ' . $filename);
            }
            $definitions[$definition->version] = $definition;
        }
        return $definitions;
    }

    /** @return array<string,string> */
    private function applied(): array
    {
        if (!$this->schemaInspector->hasTable(self::REGISTRY)) {
            return [];
        }
        $rows = $this->pdo->query('SELECT version, checksum FROM ' . self::REGISTRY)->fetchAll(PDO::FETCH_ASSOC);
        return array_column($rows, 'checksum', 'version');
    }

    private function ensureRegistry(): void
    {
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS ' . self::REGISTRY . ' (version VARCHAR(191) PRIMARY KEY, name VARCHAR(255) NOT NULL, checksum CHAR(64) NOT NULL, description TEXT NOT NULL, appliedAt VARCHAR(40) NOT NULL)');
    }

    private function apply(ForwardMigrationDefinition $definition, string $driver): void
    {
        foreach ($definition->migration->statements($driver) as $statement) {
            if (!is_string($statement)) {
                throw new RuntimeException('Learner migration statements must be strings');
            }
            $forbidden = $this->scopePolicy->inspectMigrationText($statement);
            if ($forbidden !== []) {
                throw new RuntimeException('Rejected destructive learner migration statement: ' . $forbidden[0]);
            }
            $this->pdo->exec($statement);
        }
        foreach ($definition->migration->expectedSchema() as $table => $expected) {
            if (!$this->schemaInspector->hasTable($table)) {
                throw new RuntimeException('Learner migration schema validation failed: missing table ' . $table);
            }
            foreach ($expected['columns'] ?? [] as $column) {
                if (!$this->schemaInspector->hasColumn($table, $column)) {
                    throw new RuntimeException('Learner migration schema validation failed: missing column ' . $table . '.' . $column);
                }
            }
            foreach ($expected['indexes'] ?? [] as $index) {
                if (!$this->schemaInspector->hasIndex($table, $index)) {
                    throw new RuntimeException('Learner migration schema validation failed: missing index ' . $table . '.' . $index);
                }
            }
        }
        $record = $this->pdo->prepare('INSERT INTO ' . self::REGISTRY . ' (version, name, checksum, description, appliedAt) VALUES (:version, :name, :checksum, :description, :appliedAt)');
        $record->execute([
            'version' => $definition->version,
            'name' => $definition->name,
            'checksum' => LearnerMigrationChecksum::canonical($definition->path),
            'description' => $definition->migration->description(),
            'appliedAt' => gmdate('c'),
        ]);
    }
}
