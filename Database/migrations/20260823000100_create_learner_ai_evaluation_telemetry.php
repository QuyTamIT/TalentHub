<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    public function description(): string
    {
        return 'Create append-only learner AI evaluation telemetry';
    }

    public function isReversible(): bool
    {
        return false;
    }

    public function preflight(MigrationContext $context): void
    {
        $database = (string) $context->pdo()->query('SELECT DATABASE()')?->fetchColumn();
        if ($database === '') {
            throw new RuntimeException('Phase 12 evaluation migration requires a selected database.');
        }
        $timeZone = $context->pdo()->query('SELECT @@session.time_zone')?->fetchColumn();
        if ($timeZone !== '+00:00') {
            throw new RuntimeException('Phase 12 evaluation migration requires MySQL session time zone +00:00.');
        }
        foreach (['student_profiles', 'learner_recommendation_input_snapshots', 'learner_recommendation_runs'] as $table) {
            $context->assertTableExists($table);
        }
        if ($context->tableExists('learner_ai_evaluation_runs')) {
            $this->assertInstalledContract($context);
        }
    }

    public function up(MigrationContext $context): void
    {
        if (!$this->indexExists($context, 'learner_recommendation_runs', 'uq_learner_recommendation_runs_id_student')) {
            $context->execute('ALTER TABLE learner_recommendation_runs ADD UNIQUE KEY uq_learner_recommendation_runs_id_student (id, studentId)');
        }

        if (!$context->tableExists('learner_ai_evaluation_runs')) {
            $context->execute(<<<'SQL'
CREATE TABLE learner_ai_evaluation_runs (
  id CHAR(36) NOT NULL,
  studentId CHAR(36) NOT NULL,
  subjectRef CHAR(64) NOT NULL,
  subjectRefVersion VARCHAR(50) NOT NULL,
  attemptKey CHAR(64) NOT NULL,
  ruleRunId CHAR(36) NOT NULL,
  modelRunId CHAR(36) NULL,
  snapshotId CHAR(36) NOT NULL,
  educationBand VARCHAR(32) NOT NULL,
  cohortTagsJson LONGTEXT NOT NULL,
  provider VARCHAR(100) NOT NULL,
  modelVersion VARCHAR(100) NOT NULL,
  promptVersion VARCHAR(100) NOT NULL,
  ruleVersion VARCHAR(100) NOT NULL,
  evaluatorVersion VARCHAR(100) NOT NULL,
  evaluationRevision INT UNSIGNED NOT NULL DEFAULT 1,
  supersedesEvaluationId CHAR(36) NULL,
  inputSnapshotHash CHAR(64) NOT NULL,
  consentPolicyVersion VARCHAR(100) NOT NULL,
  consentDecisionHash CHAR(64) NOT NULL,
  consentEvaluatedAt DATETIME(6) NOT NULL,
  schemaValid TINYINT UNSIGNED NOT NULL,
  evidenceCoverage DECIMAL(7,6) NOT NULL,
  evidenceMatched INT UNSIGNED NOT NULL,
  evidenceRequired INT UNSIGNED NOT NULL,
  unsupportedClaimCount INT UNSIGNED NOT NULL,
  unsafeOutputCount INT UNSIGNED NOT NULL,
  resultType VARCHAR(50) NOT NULL,
  fallbackReason VARCHAR(100) NULL,
  providerErrorCategory VARCHAR(100) NULL,
  latencyMs DECIMAL(12,3) NULL,
  inputTokens INT UNSIGNED NULL,
  outputTokens INT UNSIGNED NULL,
  estimatedCost DECIMAL(18,8) NULL,
  costCurrency CHAR(3) NULL,
  status VARCHAR(50) NOT NULL,
  retentionClass VARCHAR(50) NOT NULL DEFAULT 'evaluation_standard',
  evaluatedAt DATETIME(6) NOT NULL,
  createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id),
  UNIQUE KEY uq_learner_ai_evaluation_id_student (id, studentId),
  UNIQUE KEY uq_learner_ai_evaluation_attempt_revision (attemptKey, evaluatorVersion, evaluationRevision),
  UNIQUE KEY uq_learner_ai_evaluation_model_revision (modelRunId, evaluatorVersion, evaluationRevision),
  UNIQUE KEY uq_learner_ai_evaluation_superseded_once (supersedesEvaluationId),
  KEY idx_learner_ai_evaluation_student_time (studentId, evaluatedAt),
  KEY idx_learner_ai_evaluation_band_status_time (educationBand, status, evaluatedAt),
  KEY idx_learner_ai_evaluation_provider_model_time (provider, modelVersion, evaluatedAt),
  KEY idx_learner_ai_evaluation_gate_time (schemaValid, unsupportedClaimCount, unsafeOutputCount, evaluatedAt),
  KEY idx_learner_ai_evaluation_retention_time (retentionClass, createdAt),
  CONSTRAINT fk_learner_ai_evaluation_student FOREIGN KEY (studentId) REFERENCES student_profiles(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_learner_ai_evaluation_rule_owner FOREIGN KEY (ruleRunId, studentId) REFERENCES learner_recommendation_runs(id, studentId) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_learner_ai_evaluation_model_owner FOREIGN KEY (modelRunId, studentId) REFERENCES learner_recommendation_runs(id, studentId) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_learner_ai_evaluation_snapshot_owner FOREIGN KEY (snapshotId, studentId) REFERENCES learner_recommendation_input_snapshots(id, studentId) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_learner_ai_evaluation_supersedes_owner FOREIGN KEY (supersedesEvaluationId, studentId) REFERENCES learner_ai_evaluation_runs(id, studentId) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT chk_learner_ai_evaluation_subject_ref CHECK (subjectRef REGEXP '^[0-9a-f]{64}$'),
  CONSTRAINT chk_learner_ai_evaluation_subject_ref_version CHECK (subjectRefVersion REGEXP '^[A-Za-z0-9._-]{1,50}$'),
  CONSTRAINT chk_learner_ai_evaluation_attempt_key CHECK (attemptKey REGEXP '^[0-9a-f]{64}$'),
  CONSTRAINT chk_learner_ai_evaluation_band CHECK (educationBand IN ('high','college')),
  CONSTRAINT chk_learner_ai_evaluation_cohort_json CHECK (JSON_VALID(cohortTagsJson)),
  CONSTRAINT chk_learner_ai_evaluation_hashes CHECK (inputSnapshotHash REGEXP '^[0-9a-f]{64}$' AND consentDecisionHash REGEXP '^[0-9a-f]{64}$'),
  CONSTRAINT chk_learner_ai_evaluation_schema_valid CHECK (schemaValid IN (0,1)),
  CONSTRAINT chk_learner_ai_evaluation_revision CHECK ((evaluationRevision = 1 AND supersedesEvaluationId IS NULL) OR (evaluationRevision > 1 AND supersedesEvaluationId IS NOT NULL)),
  CONSTRAINT chk_learner_ai_evaluation_coverage CHECK (evidenceCoverage >= 0 AND evidenceCoverage <= 1 AND evidenceMatched <= evidenceRequired),
  CONSTRAINT chk_learner_ai_evaluation_result_type CHECK (resultType IN ('model','rule_fallback','blocked_before_call')),
  CONSTRAINT chk_learner_ai_evaluation_model_run_presence CHECK ((resultType = 'blocked_before_call' AND modelRunId IS NULL) OR (resultType IN ('model','rule_fallback') AND modelRunId IS NOT NULL)),
  CONSTRAINT chk_learner_ai_evaluation_latency CHECK (latencyMs IS NULL OR latencyMs >= 0),
  CONSTRAINT chk_learner_ai_evaluation_cost CHECK ((estimatedCost IS NULL AND costCurrency IS NULL) OR (estimatedCost IS NOT NULL AND estimatedCost >= 0 AND costCurrency REGEXP '^[A-Z]{3}$')),
  CONSTRAINT chk_learner_ai_evaluation_status CHECK (status IN ('completed','gate_failed','blocked','fallback')),
  CONSTRAINT chk_learner_ai_evaluation_result_status CHECK ((resultType = 'model' AND status = 'completed' AND fallbackReason IS NULL AND providerErrorCategory IS NULL) OR (resultType = 'rule_fallback' AND status IN ('fallback','gate_failed') AND fallbackReason IS NOT NULL) OR (resultType = 'blocked_before_call' AND status = 'blocked' AND fallbackReason IS NOT NULL AND latencyMs IS NULL AND inputTokens IS NULL AND outputTokens IS NULL AND estimatedCost IS NULL AND costCurrency IS NULL)),
  CONSTRAINT chk_learner_ai_evaluation_fallback_reason CHECK (fallbackReason IS NULL OR fallbackReason IN ('consent_missing','consent_revoked','consent_changed','model_disabled','rate_limited','provider_disabled','provider_unavailable','provider_rejected','malformed_response','invalid_request','invalid_model_response','unsafe_output','unsupported_claim','schema_invalid','version_mismatch','reconciliation_required')),
  CONSTRAINT chk_learner_ai_evaluation_provider_error CHECK (providerErrorCategory IS NULL OR providerErrorCategory IN ('rate_limited','provider_disabled','provider_unavailable','provider_rejected','malformed_response','invalid_request','unknown_outcome')),
  CONSTRAINT chk_learner_ai_evaluation_time_order CHECK (consentEvaluatedAt <= evaluatedAt AND evaluatedAt <= createdAt),
  CONSTRAINT chk_learner_ai_evaluation_retention CHECK (retentionClass IN ('evaluation_standard','incident_hold','legal_hold'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        }

        $this->createTriggers($context);
        $this->assertInstalledContract($context);
    }

    public function down(MigrationContext $context): void
    {
        // Forward-only additive schema: evaluation evidence must be preserved.
    }

    private function createTriggers(MigrationContext $context): void
    {
        if (!$this->triggerExists($context, 'trg_learner_ai_evaluation_validate_insert')) {
            $context->execute(<<<'SQL'
CREATE TRIGGER trg_learner_ai_evaluation_validate_insert
BEFORE INSERT ON learner_ai_evaluation_runs FOR EACH ROW
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM learner_recommendation_runs r
    INNER JOIN learner_recommendation_input_snapshots s ON s.id = r.snapshotId AND s.studentId = r.studentId
    WHERE r.id = NEW.ruleRunId AND r.studentId = NEW.studentId AND r.snapshotId = NEW.snapshotId
      AND r.engineType = 'rule' AND r.status = 'completed' AND r.ruleVersion = NEW.ruleVersion
      AND s.contentHash = NEW.inputSnapshotHash
  ) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'evaluation rule/snapshot ownership or version mismatch'; END IF;
  IF NEW.modelRunId IS NOT NULL AND NOT EXISTS (
    SELECT 1 FROM learner_recommendation_runs m
    WHERE m.id = NEW.modelRunId AND m.studentId = NEW.studentId AND m.snapshotId = NEW.snapshotId
      AND m.engineType = 'model' AND m.provider = NEW.provider AND m.modelVersion = NEW.modelVersion
      AND m.promptVersion = NEW.promptVersion AND m.status IN ('completed','failed','fallback')
  ) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'evaluation model ownership or version mismatch'; END IF;
  IF NEW.evidenceCoverage <> IF(NEW.evidenceRequired = 0, 0, ROUND(NEW.evidenceMatched / NEW.evidenceRequired, 6))
  THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'evaluation evidence coverage mismatch'; END IF;
  IF NEW.supersedesEvaluationId IS NOT NULL AND NOT EXISTS (
    SELECT 1 FROM learner_ai_evaluation_runs p
    WHERE p.id = NEW.supersedesEvaluationId AND p.studentId = NEW.studentId
      AND p.attemptKey = NEW.attemptKey AND p.evaluationRevision + 1 = NEW.evaluationRevision
  ) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'evaluation supersession mismatch'; END IF;
END
SQL);
        }
        if (!$this->triggerExists($context, 'trg_learner_ai_evaluation_block_update')) {
            $context->execute(<<<'SQL'
CREATE TRIGGER trg_learner_ai_evaluation_block_update
BEFORE UPDATE ON learner_ai_evaluation_runs FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'learner AI evaluation facts are append-only';
END
SQL);
        }
        if (!$this->triggerExists($context, 'trg_learner_ai_evaluation_block_delete')) {
            $context->execute(<<<'SQL'
CREATE TRIGGER trg_learner_ai_evaluation_block_delete
BEFORE DELETE ON learner_ai_evaluation_runs FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'learner AI evaluation facts are append-only';
END
SQL);
        }
    }

    private function assertInstalledContract(MigrationContext $context): void
    {
        $context->assertTableExists('learner_ai_evaluation_runs');
        foreach (['uq_learner_recommendation_runs_id_student'] as $index) {
            if (!$this->indexExists($context, 'learner_recommendation_runs', $index)) {
                throw new RuntimeException("Missing required owner index {$index}.");
            }
        }
        foreach (['uq_learner_ai_evaluation_attempt_revision', 'uq_learner_ai_evaluation_model_revision', 'uq_learner_ai_evaluation_superseded_once'] as $index) {
            if (!$this->indexExists($context, 'learner_ai_evaluation_runs', $index)) {
                throw new RuntimeException("Missing evaluation index {$index}.");
            }
        }
        foreach (['trg_learner_ai_evaluation_validate_insert', 'trg_learner_ai_evaluation_block_update', 'trg_learner_ai_evaluation_block_delete'] as $trigger) {
            if (!$this->triggerExists($context, $trigger)) {
                throw new RuntimeException("Missing evaluation trigger {$trigger}.");
            }
        }
    }

    private function indexExists(MigrationContext $context, string $table, string $index): bool
    {
        $statement = $context->pdo()->prepare('SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = :table AND index_name = :index');
        $statement->execute(['table' => $table, 'index' => $index]);
        return (int) $statement->fetchColumn() > 0;
    }

    private function triggerExists(MigrationContext $context, string $trigger): bool
    {
        $statement = $context->pdo()->prepare('SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema = DATABASE() AND trigger_name = :trigger');
        $statement->execute(['trigger' => $trigger]);
        return (int) $statement->fetchColumn() > 0;
    }
};
