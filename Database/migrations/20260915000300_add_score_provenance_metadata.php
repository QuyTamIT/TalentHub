<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    public function description(): string
    {
        return 'Add score provenance metadata to assessments, learner_evaluations and student_skills';
    }

    public function isReversible(): bool
    {
        return false;
    }

    public function preflight(MigrationContext $context): void
    {
        $driver = strtolower($context->pdo()->getAttribute(PDO::ATTR_DRIVER_NAME));
        if ($driver === 'sqlite') {
            $stmt = $context->pdo()->query("SELECT name FROM sqlite_master WHERE type='table'");
            $tables = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
            foreach (['assessments', 'learner_evaluations', 'student_skills'] as $table) {
                if (!in_array($table, $tables, true)) {
                    throw new RuntimeException("Table {$table} does not exist.");
                }
            }
            return;
        }

        foreach (['assessments', 'learner_evaluations', 'student_skills'] as $table) {
            $context->assertTableExists($table);
        }
    }

    public function up(MigrationContext $context): void
    {
        $pdo = $context->pdo();
        $driver = strtolower($pdo->getAttribute(PDO::ATTR_DRIVER_NAME));

        // 1. assessments table
        if (!$this->columnExists($pdo, 'assessments', 'scoreMethod')) {
            $context->execute("ALTER TABLE assessments ADD COLUMN scoreMethod VARCHAR(32) NULL");
        }
        if (!$this->columnExists($pdo, 'assessments', 'formulaVersion')) {
            $context->execute("ALTER TABLE assessments ADD COLUMN formulaVersion VARCHAR(64) NULL");
        }
        if (!$this->columnExists($pdo, 'assessments', 'calculationJson')) {
            $type = $driver === 'sqlite' ? 'TEXT' : 'LONGTEXT';
            $context->execute("ALTER TABLE assessments ADD COLUMN calculationJson {$type} NULL");
        }

        // 2. learner_evaluations table
        if (!$this->columnExists($pdo, 'learner_evaluations', 'scoreMethod')) {
            $context->execute("ALTER TABLE learner_evaluations ADD COLUMN scoreMethod VARCHAR(32) NULL");
        }
        if (!$this->columnExists($pdo, 'learner_evaluations', 'formulaVersion')) {
            $context->execute("ALTER TABLE learner_evaluations ADD COLUMN formulaVersion VARCHAR(64) NULL");
        }
        if (!$this->columnExists($pdo, 'learner_evaluations', 'calculationJson')) {
            $type = $driver === 'sqlite' ? 'TEXT' : 'LONGTEXT';
            $context->execute("ALTER TABLE learner_evaluations ADD COLUMN calculationJson {$type} NULL");
        }
        if (!$this->columnExists($pdo, 'learner_evaluations', 'supersededAt')) {
            $dtType = $driver === 'sqlite' ? 'TEXT' : 'DATETIME(6)';
            $context->execute("ALTER TABLE learner_evaluations ADD COLUMN supersededAt {$dtType} NULL");
        }
        if (!$this->columnExists($pdo, 'learner_evaluations', 'revokedAt')) {
            $dtType = $driver === 'sqlite' ? 'TEXT' : 'DATETIME(6)';
            $context->execute("ALTER TABLE learner_evaluations ADD COLUMN revokedAt {$dtType} NULL");
        }

        // 3. student_skills table
        if ($driver !== 'sqlite') {
            $context->execute("ALTER TABLE student_skills MODIFY COLUMN levelScore DECIMAL(5,2) NULL");
        } else {
            $this->makeSqliteScoreNullable($pdo);
        }
        if (!$this->columnExists($pdo, 'student_skills', 'scoreState')) {
            $context->execute("ALTER TABLE student_skills ADD COLUMN scoreState VARCHAR(32) NULL");
        }
        if (!$this->columnExists($pdo, 'student_skills', 'sourceEvaluationId')) {
            $context->execute("ALTER TABLE student_skills ADD COLUMN sourceEvaluationId CHAR(36) NULL");
        }
        if (!$this->columnExists($pdo, 'student_skills', 'sourceEvidenceId')) {
            $context->execute("ALTER TABLE student_skills ADD COLUMN sourceEvidenceId CHAR(36) NULL");
        }
        if (!$this->columnExists($pdo, 'student_skills', 'formulaVersion')) {
            $context->execute("ALTER TABLE student_skills ADD COLUMN formulaVersion VARCHAR(64) NULL");
        }
    }

    public function down(MigrationContext $context): void
    {
        throw new RuntimeException('Score provenance is retained for audit. Roll back application code without dropping snapshots; use a forward repair migration.');
    }

    private function makeSqliteScoreNullable(PDO $pdo): void
    {
        $columns = $pdo->query('PRAGMA table_info(student_skills)')->fetchAll(PDO::FETCH_ASSOC);
        $needsRebuild = false;
        foreach ($columns as $column) {
            if ($column['name'] === 'levelScore' && (int) $column['notnull'] === 1) $needsRebuild = true;
        }
        if (!$needsRebuild) return;
        if ($pdo->inTransaction()) throw new RuntimeException('SQLite nullable migration requires its own transaction.');
        $ddl = (string) $pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='student_skills'")->fetchColumn();
        $ddl = preg_replace('/(\blevelScore\b[^,]*?)\bNOT\s+NULL\b/i', '$1', $ddl, 1, $changed);
        if ($changed !== 1) throw new RuntimeException('Cannot safely locate legacy levelScore NOT NULL definition.');
        $ddl = preg_replace('/^CREATE\s+TABLE\s+(?:"student_skills"|`student_skills`|\[student_skills\]|student_skills)/i', 'CREATE TABLE score_provenance_skills_tmp', $ddl, 1, $renamed);
        if ($renamed !== 1) throw new RuntimeException('Cannot safely rebuild legacy student_skills definition.');
        $dependentSql = $pdo->query("SELECT sql FROM sqlite_master WHERE tbl_name='student_skills' AND type IN ('index','trigger') AND sql IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
        $foreignKeys = (int) $pdo->query('PRAGMA foreign_keys')->fetchColumn();
        $pdo->exec('PRAGMA foreign_keys=OFF');
        try {
            $pdo->beginTransaction();
            $pdo->exec($ddl);
            $names = implode(', ', array_map(static fn (array $c): string => '"' . str_replace('"', '""', $c['name']) . '"', $columns));
            $pdo->exec("INSERT INTO score_provenance_skills_tmp ({$names}) SELECT {$names} FROM student_skills");
            $pdo->exec('DROP TABLE student_skills');
            $pdo->exec('ALTER TABLE score_provenance_skills_tmp RENAME TO student_skills');
            foreach ($dependentSql as $sql) $pdo->exec($sql);
            if ($pdo->query('PRAGMA foreign_key_check')->fetch() !== false) throw new RuntimeException('Foreign key violation during score provenance migration.');
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        } finally {
            $pdo->exec('PRAGMA foreign_keys=' . $foreignKeys);
        }
    }

    private function columnExists(PDO $pdo, string $table, string $column): bool
    {
        $driver = strtolower($pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
        if ($driver === 'sqlite') {
            $stmt = $pdo->query("PRAGMA table_info({$table})");
            if ($stmt === false) {
                return false;
            }
            $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($cols as $col) {
                if (strcasecmp((string)($col['name'] ?? ''), $column) === 0) {
                    return true;
                }
            }
            return false;
        }

        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :col"
        );
        $stmt->execute(['table' => $table, 'col' => $column]);
        return (int) $stmt->fetchColumn() > 0;
    }
};