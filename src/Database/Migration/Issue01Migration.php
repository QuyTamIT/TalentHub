<?php
declare(strict_types=1);

namespace TalentHub\Database\Migration;

use PDO;
use RuntimeException;
use TalentHub\Learner\Data\Database\SchemaInspector;
use TalentHub\Learner\Data\Migrations\LearnerForwardMigrationRunner;
use TalentHub\Learner\Data\Migrations\LearnerMigrationChecksum;

/** Bounded handoff: preserves both migration files and records the superseded SQL only after validation. */
final class Issue01Migration
{
    public const VERSION = '20260909000100';
    public const LEGACY = '018_decouple_competency_assessments';

    public function __construct(private readonly PDO $pdo, private readonly string $root) {}

    public function run(bool $apply): string
    {
        $locks = [];
        try {
            foreach (['talenthub:schema_migrations', 'talenthub:learner_forward_migrations'] as $name) {
                $s = $this->pdo->prepare('SELECT GET_LOCK(?,30)');
                $s->execute([$name]);
                if ((int)$s->fetchColumn() !== 1) throw new RuntimeException('Unable to acquire migration lock.');
                $locks[] = $name;
            }
            $context = new MigrationContext($this->pdo);
            $runner = new MigrationRunner($this->pdo, $this->root . '/Database/migrations');
            $definitions = $runner->definitions();
            $target = null;
            foreach ($definitions as $definition) if ($definition->version === self::VERSION) $target = $definition;
            if ($target === null) throw new RuntimeException('Issue01 migration is missing.');
            $repository = new MigrationRepository($this->pdo);
            $applied = $context->tableExists('schema_migrations') ? $repository->applied() : [];
            $runner->validateReadOnly();
            $learner = new LearnerForwardMigrationRunner($this->pdo, $this->root . '/Database/migrations/learner', new SchemaInspector($this->pdo, (string)$this->pdo->query('SELECT DATABASE()')->fetchColumn()));
            $status = $learner->status();
            $legacyApplied = $status[self::LEGACY]['applied'];
            $mainApplied = isset($applied[self::VERSION]);
            if (!$mainApplied) {
                if ($legacyApplied) throw new RuntimeException('Legacy 018 is already applied without the main migration; explicit schema repair is required.');
                $target->migration->preflight($context);
            } else {
                $this->validateTarget();
            }
            $index = $this->pdo->query("SELECT COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='assessments' AND INDEX_NAME='idx_assessments_student_context' ORDER BY SEQ_IN_INDEX")->fetchAll(PDO::FETCH_COLUMN);
            if ($index !== [] && $index !== ['studentId','classId','projectId','status']) throw new RuntimeException('Unexpected learner context index.');
            if (!$apply) return $mainApplied && $legacyApplied ? 'already reconciled' : 'preflight passed; only Issue01 will be applied';
            if (!$mainApplied) {
                $target->migration->up($context);
                $this->validateTarget();
                $repository->bootstrap();
                $repository->record($target, $repository->nextBatch(), 0);
            }
            if ($index === []) $this->pdo->exec('ALTER TABLE assessments ADD INDEX idx_assessments_student_context (studentId,classId,projectId,status)');
            if (!$legacyApplied) {
                $this->pdo->exec('CREATE TABLE IF NOT EXISTS learner_forward_migrations (version VARCHAR(191) PRIMARY KEY, name VARCHAR(255) NOT NULL, checksum CHAR(64) NOT NULL, description TEXT NOT NULL, appliedAt VARCHAR(40) NOT NULL)');
                $definition = require $this->root . '/Database/migrations/learner/' . self::LEGACY . '.php';
                $s = $this->pdo->prepare('INSERT INTO learner_forward_migrations(version,name,checksum,description,appliedAt) VALUES(?,?,?,?,?)');
                $s->execute([self::LEGACY, $definition->name, LearnerMigrationChecksum::canonical($definition->path), 'Superseded by main migration ' . self::VERSION . '; original learner SQL was not executed', gmdate('c')]);
            }
            return 'Issue01 reconciled; other pending migrations were not applied';
        } finally {
            foreach (array_reverse($locks) as $name) {
                $s = $this->pdo->prepare('SELECT RELEASE_LOCK(?)');
                $s->execute([$name]);
            }
        }
    }

    private function validateTarget(): void
    {
        $context = new MigrationContext($this->pdo);
        $context->assertTableExists('teacher_class_assignments');
        $columns = $this->pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='assessments' AND IS_NULLABLE='YES' AND COLUMN_TYPE='char(36)'")->fetchAll(PDO::FETCH_COLUMN);
        if (array_diff(['activityId','classId','projectId'], $columns)) throw new RuntimeException('Incomplete Issue01 context columns.');
        $constraints = $this->pdo->query("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='assessments'")->fetchAll(PDO::FETCH_COLUMN);
        if (array_diff(['fk_assessments_activity_registration','fk_assessments_student','fk_assessments_class','fk_assessments_project','uq_assessments_teacher_student_class','uq_assessments_teacher_student_project','chk_assessments_draft_timestamp'], $constraints)) throw new RuntimeException('Incomplete Issue01 constraints.');
        $triggers = $this->pdo->query("SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND EVENT_OBJECT_TABLE='assessments' AND ACTION_TIMING='BEFORE'")->fetchAll(PDO::FETCH_COLUMN);
        if (array_diff(['trg_assessments_one_context_insert','trg_assessments_one_context_update'], $triggers)) throw new RuntimeException('Incomplete Issue01 context triggers.');
    }
}
