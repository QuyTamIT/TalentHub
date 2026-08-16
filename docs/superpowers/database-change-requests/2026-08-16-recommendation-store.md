# DCR: Learner recommendation store (004)

**Status:** exact-DDL approval granted; SQLite-only schema proof required before any shared-database execution.

## Approval gate

APPROVAL REQUIRED: do not execute migration 004 against a shared database. The user has approved creation of this additive, learner-owned DDL only. A later execution request must capture the shared-schema baseline, backup/recovery proof, preflight result, unchanged existing-row counts, foreign-key check, and a second-run no-op result.

## Purpose and ownership

This request adds a learner-owned, append-only recommendation record and immutable snapshot-evidence provenance. It does not alter or repurpose any canonical fact table owned by Teacher, School, or Enterprise. Task 9 may write only these seven tables in one transaction; it must always scope reads and writes by `studentId`.

| Table | Owner | Source of truth | Data minimization / retention role |
| --- | --- | --- | --- |
| `learner_recommendation_input_snapshots` | Learner AI | Consent-filtered `RecommendationInput` from Task 7 | Stores only normalized, redacted payload JSON, scope/quality metadata, source freshness, and hash; no names, contact details, tokens, CV URLs, or raw provider payloads. |
| `learner_recommendation_runs` | Learner AI | Learner recommendation orchestration | Records idempotency, status, rule/provider/model/prompt versions, safe error/fallback metadata. |
| `learner_recommendation_snapshot_evidence` | Learner AI | Normalized Task 7 evidence reference | Preserves the exact consent-filtered snapshot source type/id/time and safe value once; item evidence must link to this row. |
| `learner_recommendation_items` | Learner AI | Validated recommendation result | Stores user-facing recommendation output and a safe action descriptor. |
| `learner_recommendation_evidence` | Learner AI | Recommendation-to-snapshot evidence link | Keeps a minimal explanation while its `snapshotEvidenceId` proves the source belongs to the run's immutable snapshot. |
| `learner_recommendation_feedback` | Learner AI | Learner feedback event | Append-only feedback; `safeComment` is bounded and must be sanitized by the writer. |
| `learner_recommendation_audit_events` | Learner AI | Recommendation lifecycle event | Append-only minimal audit trail; version/engine metadata is JSON, never a prompt body or raw model response. |

Existing shared tables remain canonical parents only:

| Parent | Consumed by | Relationship | Action |
| --- | --- | --- | --- |
| `student_profiles(id)` | snapshot, run, feedback, audit | learner ownership boundary | `ON DELETE RESTRICT ON UPDATE CASCADE` |
| `learner_recommendation_input_snapshots(id)` | run | immutable input provenance | `ON DELETE RESTRICT ON UPDATE CASCADE` |
| `learner_recommendation_snapshot_evidence(id)` | item evidence | normalized immutable snapshot provenance | `ON DELETE RESTRICT ON UPDATE CASCADE` |
| `learner_recommendation_runs(id)` | item, audit | result and lifecycle provenance | `ON DELETE RESTRICT ON UPDATE CASCADE` |
| `learner_recommendation_items(id)` | evidence, feedback | evidence and feedback provenance | `ON DELETE RESTRICT ON UPDATE CASCADE` |

## Compatibility and forward-only contract

- This migration creates seven new tables and fourteen learner-owned triggers only. There is no `ALTER`, `DELETE` DML, `DROP`, `TRUNCATE`, rename, backfill, seed, or change to an existing table.
- All migration target tables are checked for absence during preflight. The migration then uses plain `CREATE TABLE`/`CREATE INDEX`/`CREATE TRIGGER`, never `IF NOT EXISTS`; an already-created or racing target therefore fails closed instead of accepting an unknown shape.
- The learner forward-migration registry remains the idempotency mechanism. Migration `004_create_recommendation_store` is recorded only after schema validation, so a second approved call is a no-op.
- Preflight requires registry checksum `f1c7d125c475fddad946448b9a320ae6207ea5903eaa2d652fb456d505a929bc` for `002_create_ai_input_foundation` and `6b2c5674e4da5d98bc7540881f90ce5fab421d2cf52e41b7899f51a87d563c38` for `003_create_ai_input_extensions`, a `CHAR(36)` primary key on `student_profiles.id`, and MySQL UTC session/table conventions. Migration 004 must be invoked only after separately completed and registered 002 then 003; a combined `[002,003,004]` call is deliberately rejected before registry creation.
- Operational rollback disables the new learner recommendation read/write path or feature flag. It never removes the tables, triggers, or recorded data.

## Value and integrity rules

- Snapshots are deduplicated by `(studentId, contentHash)`, are immutable, and have a unique `(id, studentId)` pair. Runs reference `(snapshotId, studentId)` as one composite foreign key, so a learner cannot bind a run to another learner's snapshot.
- Snapshot evidence is normalized and immutable through its immutable parent snapshot. Item evidence has a required `snapshotEvidenceId`; database triggers prove that the linked source type/id and snapshot match the item's run snapshot.
- Runs are idempotent by `(studentId, idempotencyKey)`. `rule` runs require only `ruleVersion`; `model` runs require `provider`, `modelVersion`, and `promptVersion`. Final statuses require `completedAt`.
- Item type, confidence, priority, lifecycle, evidence source type, feedback verdict, audit actor, and lifecycle status all use explicit checks. JSON storage uses `JSON_VALID`/`json_valid` checks.
- Feedback and audit rows are database-enforced append-only. Their learner-owned insert/update triggers prove `studentId` matches the item/run owner, while append-only triggers reject every update and delete; a correction is a new event.

## Capacity and index cost

Conservative pilot estimate: one snapshot/run with up to 24 normalized snapshot-evidence rows, 12 items, 24 item-evidence links, one feedback event, and four audit events is approximately 30–50 KiB before index overhead. At 10,000 completed runs this is roughly 0.30–0.50 GiB of table data; ownership/idempotency/evidence indexes can add approximately 30–60%. Monitor payload and metadata JSON sizes, snapshot/item evidence fan-out, and the time-ordered ownership indexes before enabling model traffic. No index is added to shared-role tables.

## Exact MySQL DDL fingerprint

The SHA-256 of the complete SQL fence below is `3742e97d20df66e1797867d06a877935d765e976dca8400bb3d050d96eb931c4` and must equal both `004_create_recommendation_store.php` MySQL statements joined with two newlines and the schema test constant.

The approved migration source checksum is `48d7eaf7122cae13d5dbcb1dbaa2e157c34f2f4cea8f0c430914f193be48f0be`; `learner_forward_migrations` will record this value only after successful post-DDL schema validation.

```sql
CREATE TABLE learner_recommendation_input_snapshots (
  id CHAR(36) NOT NULL, studentId CHAR(36) NOT NULL, schemaVersion VARCHAR(100) NOT NULL, contentHash CHAR(64) NOT NULL, consentScopesJson LONGTEXT NOT NULL, qualityFlagsJson LONGTEXT NOT NULL, payloadJson LONGTEXT NOT NULL, sourceUpdatedAt LONGTEXT NOT NULL, createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id), UNIQUE KEY uq_learner_recommendation_input_snapshots_student_hash (studentId, contentHash), UNIQUE KEY uq_learner_recommendation_input_snapshots_id_student (id, studentId), KEY idx_learner_recommendation_input_snapshots_student_created (studentId, createdAt),
  CONSTRAINT fk_learner_recommendation_input_snapshots_student FOREIGN KEY (studentId) REFERENCES student_profiles(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_learner_recommendation_input_snapshots_consent_json CHECK (JSON_VALID(consentScopesJson)),
  CONSTRAINT chk_learner_recommendation_input_snapshots_quality_json CHECK (JSON_VALID(qualityFlagsJson)),
  CONSTRAINT chk_learner_recommendation_input_snapshots_payload_json CHECK (JSON_VALID(payloadJson)),
  CONSTRAINT chk_learner_recommendation_input_snapshots_source_updated_json CHECK (JSON_VALID(sourceUpdatedAt))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE learner_recommendation_runs (
  id CHAR(36) NOT NULL, studentId CHAR(36) NOT NULL, snapshotId CHAR(36) NOT NULL, idempotencyKey VARCHAR(100) NOT NULL, engineType VARCHAR(50) NOT NULL, status VARCHAR(50) NOT NULL DEFAULT 'pending', ruleVersion VARCHAR(100) NULL, provider VARCHAR(100) NULL, modelVersion VARCHAR(100) NULL, promptVersion VARCHAR(100) NULL, fallbackReason VARCHAR(100) NULL, safeErrorCode VARCHAR(100) NULL, startedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), completedAt DATETIME(6) NULL, createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id), UNIQUE KEY uq_learner_recommendation_runs_student_idempotency (studentId, idempotencyKey), KEY idx_learner_recommendation_runs_student_created (studentId, createdAt), KEY idx_learner_recommendation_runs_snapshot_student (snapshotId, studentId),
  CONSTRAINT fk_learner_recommendation_runs_student FOREIGN KEY (studentId) REFERENCES student_profiles(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_learner_recommendation_runs_snapshot_owner FOREIGN KEY (snapshotId, studentId) REFERENCES learner_recommendation_input_snapshots(id, studentId) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_learner_recommendation_runs_engine CHECK (engineType IN ('rule','model')),
  CONSTRAINT chk_learner_recommendation_runs_status CHECK (status IN ('pending','completed','failed','fallback')),
  CONSTRAINT chk_learner_recommendation_runs_engine_versions CHECK ((engineType = 'rule' AND ruleVersion IS NOT NULL AND provider IS NULL AND modelVersion IS NULL AND promptVersion IS NULL) OR (engineType = 'model' AND ruleVersion IS NULL AND provider IS NOT NULL AND modelVersion IS NOT NULL AND promptVersion IS NOT NULL)),
  CONSTRAINT chk_learner_recommendation_runs_completion CHECK ((status = 'pending' AND completedAt IS NULL) OR (status IN ('completed','failed','fallback') AND completedAt IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE learner_recommendation_snapshot_evidence (
  id CHAR(36) NOT NULL, snapshotId CHAR(36) NOT NULL, sourceType VARCHAR(50) NOT NULL, sourceId CHAR(36) NOT NULL, observedAt DATETIME(6) NULL, safeValueJson LONGTEXT NOT NULL, createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id), UNIQUE KEY uq_learner_recommendation_snapshot_evidence_snapshot_source (snapshotId, sourceType, sourceId), KEY idx_learner_recommendation_snapshot_evidence_source (sourceType, sourceId),
  CONSTRAINT fk_learner_recommendation_snapshot_evidence_snapshot FOREIGN KEY (snapshotId) REFERENCES learner_recommendation_input_snapshots(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_learner_recommendation_snapshot_evidence_source_type CHECK (sourceType IN ('skill','assessment','activity_experience','evaluation','opportunity')),
  CONSTRAINT chk_learner_recommendation_snapshot_evidence_safe_value_json CHECK (JSON_VALID(safeValueJson))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE learner_recommendation_items (
  id CHAR(36) NOT NULL, runId CHAR(36) NOT NULL, itemType VARCHAR(50) NOT NULL, title VARCHAR(255) NOT NULL, summary VARCHAR(1000) NOT NULL, priority TINYINT UNSIGNED NOT NULL, confidenceBand VARCHAR(50) NOT NULL, actionJson LONGTEXT NOT NULL, lifecycleStatus VARCHAR(50) NOT NULL DEFAULT 'active', createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id), KEY idx_learner_recommendation_items_run_lifecycle_priority (runId, lifecycleStatus, priority),
  CONSTRAINT fk_learner_recommendation_items_run FOREIGN KEY (runId) REFERENCES learner_recommendation_runs(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_learner_recommendation_items_type CHECK (itemType IN ('strength','improvement','development','activity','roadmap')),
  CONSTRAINT chk_learner_recommendation_items_priority CHECK (priority BETWEEN 1 AND 100),
  CONSTRAINT chk_learner_recommendation_items_confidence CHECK (confidenceBand IN ('low','medium','high')),
  CONSTRAINT chk_learner_recommendation_items_action_json CHECK (JSON_VALID(actionJson)),
  CONSTRAINT chk_learner_recommendation_items_lifecycle CHECK (lifecycleStatus IN ('active','superseded'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE learner_recommendation_evidence (
  id CHAR(36) NOT NULL, itemId CHAR(36) NOT NULL, snapshotEvidenceId CHAR(36) NOT NULL, sourceType VARCHAR(50) NOT NULL, sourceId CHAR(36) NOT NULL, observedAt DATETIME(6) NULL, contributionLabel VARCHAR(100) NOT NULL, safeValueJson LONGTEXT NOT NULL, createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id), UNIQUE KEY uq_learner_recommendation_evidence_item_source (itemId, sourceType, sourceId), KEY idx_learner_recommendation_evidence_source (sourceType, sourceId), KEY idx_learner_recommendation_evidence_snapshot_evidence (snapshotEvidenceId),
  CONSTRAINT fk_learner_recommendation_evidence_item FOREIGN KEY (itemId) REFERENCES learner_recommendation_items(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_learner_recommendation_evidence_snapshot_evidence FOREIGN KEY (snapshotEvidenceId) REFERENCES learner_recommendation_snapshot_evidence(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_learner_recommendation_evidence_source_type CHECK (sourceType IN ('skill','assessment','activity_experience','evaluation','opportunity')),
  CONSTRAINT chk_learner_recommendation_evidence_safe_value_json CHECK (JSON_VALID(safeValueJson))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE learner_recommendation_feedback (
  id CHAR(36) NOT NULL, studentId CHAR(36) NOT NULL, itemId CHAR(36) NOT NULL, verdict VARCHAR(50) NOT NULL, reasonCode VARCHAR(100) NOT NULL, safeComment VARCHAR(500) NULL, createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id), KEY idx_learner_recommendation_feedback_student_created (studentId, createdAt), KEY idx_learner_recommendation_feedback_item (itemId),
  CONSTRAINT fk_learner_recommendation_feedback_student FOREIGN KEY (studentId) REFERENCES student_profiles(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_learner_recommendation_feedback_item FOREIGN KEY (itemId) REFERENCES learner_recommendation_items(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_learner_recommendation_feedback_verdict CHECK (verdict IN ('helpful','not_helpful','not_relevant','unsafe')),
  CONSTRAINT chk_learner_recommendation_feedback_safe_comment CHECK (safeComment IS NULL OR CHAR_LENGTH(safeComment) <= 500)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE learner_recommendation_audit_events (
  id CHAR(36) NOT NULL, runId CHAR(36) NOT NULL, studentId CHAR(36) NOT NULL, requestId CHAR(36) NOT NULL, actorType VARCHAR(50) NOT NULL, action VARCHAR(100) NOT NULL, engineMetadataJson LONGTEXT NOT NULL, status VARCHAR(50) NOT NULL, createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id), KEY idx_learner_recommendation_audit_events_student_created (studentId, createdAt), KEY idx_learner_recommendation_audit_events_run_created (runId, createdAt),
  CONSTRAINT fk_learner_recommendation_audit_events_run FOREIGN KEY (runId) REFERENCES learner_recommendation_runs(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_learner_recommendation_audit_events_student FOREIGN KEY (studentId) REFERENCES student_profiles(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_learner_recommendation_audit_events_actor CHECK (actorType IN ('learner','system','service')),
  CONSTRAINT chk_learner_recommendation_audit_events_metadata_json CHECK (JSON_VALID(engineMetadataJson)),
  CONSTRAINT chk_learner_recommendation_audit_events_status CHECK (status IN ('pending','completed','failed','fallback'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TRIGGER trg_learner_recommendation_input_snapshots_immutable_update
BEFORE UPDATE ON learner_recommendation_input_snapshots
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'recommendation input snapshot is immutable';
END;

CREATE TRIGGER trg_learner_recommendation_input_snapshots_immutable_delete
BEFORE DELETE ON learner_recommendation_input_snapshots
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'recommendation input snapshot is immutable';
END;

CREATE TRIGGER trg_learner_recommendation_snapshot_evidence_immutable_update
BEFORE UPDATE ON learner_recommendation_snapshot_evidence
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'recommendation snapshot evidence is immutable';
END;

CREATE TRIGGER trg_learner_recommendation_snapshot_evidence_immutable_delete
BEFORE DELETE ON learner_recommendation_snapshot_evidence
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'recommendation snapshot evidence is immutable';
END;

CREATE TRIGGER trg_learner_recommendation_evidence_snapshot_match_insert
BEFORE INSERT ON learner_recommendation_evidence
FOR EACH ROW
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM learner_recommendation_items AS items
    INNER JOIN learner_recommendation_runs AS runs ON runs.id = items.runId
    INNER JOIN learner_recommendation_snapshot_evidence AS snapshot_evidence ON snapshot_evidence.id = NEW.snapshotEvidenceId
    WHERE items.id = NEW.itemId
      AND runs.snapshotId = snapshot_evidence.snapshotId
      AND NEW.sourceType = snapshot_evidence.sourceType
      AND NEW.sourceId = snapshot_evidence.sourceId
  ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'recommendation evidence snapshot mismatch';
  END IF;
END;

CREATE TRIGGER trg_learner_recommendation_evidence_snapshot_match_update
BEFORE UPDATE ON learner_recommendation_evidence
FOR EACH ROW
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM learner_recommendation_items AS items
    INNER JOIN learner_recommendation_runs AS runs ON runs.id = items.runId
    INNER JOIN learner_recommendation_snapshot_evidence AS snapshot_evidence ON snapshot_evidence.id = NEW.snapshotEvidenceId
    WHERE items.id = NEW.itemId
      AND runs.snapshotId = snapshot_evidence.snapshotId
      AND NEW.sourceType = snapshot_evidence.sourceType
      AND NEW.sourceId = snapshot_evidence.sourceId
  ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'recommendation evidence snapshot mismatch';
  END IF;
END;

CREATE TRIGGER trg_learner_recommendation_feedback_owner_match_insert
BEFORE INSERT ON learner_recommendation_feedback
FOR EACH ROW
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM learner_recommendation_items AS items
    INNER JOIN learner_recommendation_runs AS runs ON runs.id = items.runId
    WHERE items.id = NEW.itemId AND runs.studentId = NEW.studentId
  ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'recommendation feedback learner ownership mismatch';
  END IF;
END;

CREATE TRIGGER trg_learner_recommendation_feedback_owner_match_update
BEFORE UPDATE ON learner_recommendation_feedback
FOR EACH ROW
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM learner_recommendation_items AS items
    INNER JOIN learner_recommendation_runs AS runs ON runs.id = items.runId
    WHERE items.id = NEW.itemId AND runs.studentId = NEW.studentId
  ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'recommendation feedback learner ownership mismatch';
  END IF;
END;

CREATE TRIGGER trg_learner_recommendation_feedback_append_only_update
BEFORE UPDATE ON learner_recommendation_feedback
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'append-only recommendation feedback';
END;

CREATE TRIGGER trg_learner_recommendation_feedback_append_only_delete
BEFORE DELETE ON learner_recommendation_feedback
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'append-only recommendation feedback';
END;

CREATE TRIGGER trg_learner_recommendation_audit_events_owner_match_insert
BEFORE INSERT ON learner_recommendation_audit_events
FOR EACH ROW
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM learner_recommendation_runs AS runs
    WHERE runs.id = NEW.runId AND runs.studentId = NEW.studentId
  ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'recommendation audit learner ownership mismatch';
  END IF;
END;

CREATE TRIGGER trg_learner_recommendation_audit_events_owner_match_update
BEFORE UPDATE ON learner_recommendation_audit_events
FOR EACH ROW
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM learner_recommendation_runs AS runs
    WHERE runs.id = NEW.runId AND runs.studentId = NEW.studentId
  ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'recommendation audit learner ownership mismatch';
  END IF;
END;

CREATE TRIGGER trg_learner_recommendation_audit_events_append_only_update
BEFORE UPDATE ON learner_recommendation_audit_events
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'append-only recommendation audit event';
END;

CREATE TRIGGER trg_learner_recommendation_audit_events_append_only_delete
BEFORE DELETE ON learner_recommendation_audit_events
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'append-only recommendation audit event';
END;
```

## SQLite fixture parity

`004_create_recommendation_store.php` uses the following exact SQLite statements for the disposable schema test. `TEXT` is used for JSON/timestamps, `json_valid(...)` replaces MySQL `JSON_VALID(...)`, and `RAISE(ABORT, ...)` replaces MySQL `SIGNAL`. The named columns, values, keys, FK actions, and append-only behavior are identical.

```sql
CREATE TABLE learner_recommendation_input_snapshots (id CHAR(36) NOT NULL PRIMARY KEY, studentId CHAR(36) NOT NULL, schemaVersion VARCHAR(100) NOT NULL, contentHash CHAR(64) NOT NULL, consentScopesJson TEXT NOT NULL, qualityFlagsJson TEXT NOT NULL, payloadJson TEXT NOT NULL, sourceUpdatedAt TEXT NOT NULL, createdAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (studentId) REFERENCES student_profiles(id) ON DELETE RESTRICT ON UPDATE CASCADE, CHECK (json_valid(consentScopesJson)), CHECK (json_valid(qualityFlagsJson)), CHECK (json_valid(payloadJson)), CHECK (json_valid(sourceUpdatedAt)))

CREATE TABLE learner_recommendation_runs (id CHAR(36) NOT NULL PRIMARY KEY, studentId CHAR(36) NOT NULL, snapshotId CHAR(36) NOT NULL, idempotencyKey VARCHAR(100) NOT NULL, engineType VARCHAR(50) NOT NULL, status VARCHAR(50) NOT NULL DEFAULT 'pending', ruleVersion VARCHAR(100) NULL, provider VARCHAR(100) NULL, modelVersion VARCHAR(100) NULL, promptVersion VARCHAR(100) NULL, fallbackReason VARCHAR(100) NULL, safeErrorCode VARCHAR(100) NULL, startedAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, completedAt TEXT NULL, createdAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (studentId) REFERENCES student_profiles(id) ON DELETE RESTRICT ON UPDATE CASCADE, FOREIGN KEY (snapshotId, studentId) REFERENCES learner_recommendation_input_snapshots(id, studentId) ON DELETE RESTRICT ON UPDATE CASCADE, CHECK (engineType IN ('rule','model')), CHECK (status IN ('pending','completed','failed','fallback')), CHECK ((engineType = 'rule' AND ruleVersion IS NOT NULL AND provider IS NULL AND modelVersion IS NULL AND promptVersion IS NULL) OR (engineType = 'model' AND ruleVersion IS NULL AND provider IS NOT NULL AND modelVersion IS NOT NULL AND promptVersion IS NOT NULL)), CHECK ((status = 'pending' AND completedAt IS NULL) OR (status IN ('completed','failed','fallback') AND completedAt IS NOT NULL)))

CREATE TABLE learner_recommendation_snapshot_evidence (id CHAR(36) NOT NULL PRIMARY KEY, snapshotId CHAR(36) NOT NULL, sourceType VARCHAR(50) NOT NULL, sourceId CHAR(36) NOT NULL, observedAt TEXT NULL, safeValueJson TEXT NOT NULL, createdAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (snapshotId) REFERENCES learner_recommendation_input_snapshots(id) ON DELETE RESTRICT ON UPDATE CASCADE, CHECK (sourceType IN ('skill','assessment','activity_experience','evaluation','opportunity')), CHECK (json_valid(safeValueJson)))

CREATE TABLE learner_recommendation_items (id CHAR(36) NOT NULL PRIMARY KEY, runId CHAR(36) NOT NULL, itemType VARCHAR(50) NOT NULL, title VARCHAR(255) NOT NULL, summary VARCHAR(1000) NOT NULL, priority INTEGER NOT NULL, confidenceBand VARCHAR(50) NOT NULL, actionJson TEXT NOT NULL, lifecycleStatus VARCHAR(50) NOT NULL DEFAULT 'active', createdAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (runId) REFERENCES learner_recommendation_runs(id) ON DELETE RESTRICT ON UPDATE CASCADE, CHECK (itemType IN ('strength','improvement','development','activity','roadmap')), CHECK (priority BETWEEN 1 AND 100), CHECK (confidenceBand IN ('low','medium','high')), CHECK (json_valid(actionJson)), CHECK (lifecycleStatus IN ('active','superseded')))

CREATE TABLE learner_recommendation_evidence (id CHAR(36) NOT NULL PRIMARY KEY, itemId CHAR(36) NOT NULL, snapshotEvidenceId CHAR(36) NOT NULL, sourceType VARCHAR(50) NOT NULL, sourceId CHAR(36) NOT NULL, observedAt TEXT NULL, contributionLabel VARCHAR(100) NOT NULL, safeValueJson TEXT NOT NULL, createdAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (itemId) REFERENCES learner_recommendation_items(id) ON DELETE RESTRICT ON UPDATE CASCADE, FOREIGN KEY (snapshotEvidenceId) REFERENCES learner_recommendation_snapshot_evidence(id) ON DELETE RESTRICT ON UPDATE CASCADE, CHECK (sourceType IN ('skill','assessment','activity_experience','evaluation','opportunity')), CHECK (json_valid(safeValueJson)))

CREATE TABLE learner_recommendation_feedback (id CHAR(36) NOT NULL PRIMARY KEY, studentId CHAR(36) NOT NULL, itemId CHAR(36) NOT NULL, verdict VARCHAR(50) NOT NULL, reasonCode VARCHAR(100) NOT NULL, safeComment VARCHAR(500) NULL, createdAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (studentId) REFERENCES student_profiles(id) ON DELETE RESTRICT ON UPDATE CASCADE, FOREIGN KEY (itemId) REFERENCES learner_recommendation_items(id) ON DELETE RESTRICT ON UPDATE CASCADE, CHECK (verdict IN ('helpful','not_helpful','not_relevant','unsafe')), CHECK (safeComment IS NULL OR length(safeComment) <= 500))

CREATE TABLE learner_recommendation_audit_events (id CHAR(36) NOT NULL PRIMARY KEY, runId CHAR(36) NOT NULL, studentId CHAR(36) NOT NULL, requestId CHAR(36) NOT NULL, actorType VARCHAR(50) NOT NULL, action VARCHAR(100) NOT NULL, engineMetadataJson TEXT NOT NULL, status VARCHAR(50) NOT NULL, createdAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (runId) REFERENCES learner_recommendation_runs(id) ON DELETE RESTRICT ON UPDATE CASCADE, FOREIGN KEY (studentId) REFERENCES student_profiles(id) ON DELETE RESTRICT ON UPDATE CASCADE, CHECK (actorType IN ('learner','system','service')), CHECK (json_valid(engineMetadataJson)), CHECK (status IN ('pending','completed','failed','fallback')))

CREATE TRIGGER trg_learner_recommendation_input_snapshots_immutable_update BEFORE UPDATE ON learner_recommendation_input_snapshots FOR EACH ROW BEGIN SELECT RAISE(ABORT, 'recommendation input snapshot is immutable'); END

CREATE TRIGGER trg_learner_recommendation_input_snapshots_immutable_delete BEFORE DELETE ON learner_recommendation_input_snapshots FOR EACH ROW BEGIN SELECT RAISE(ABORT, 'recommendation input snapshot is immutable'); END

CREATE TRIGGER trg_learner_recommendation_snapshot_evidence_immutable_update BEFORE UPDATE ON learner_recommendation_snapshot_evidence FOR EACH ROW BEGIN SELECT RAISE(ABORT, 'recommendation snapshot evidence is immutable'); END

CREATE TRIGGER trg_learner_recommendation_snapshot_evidence_immutable_delete BEFORE DELETE ON learner_recommendation_snapshot_evidence FOR EACH ROW BEGIN SELECT RAISE(ABORT, 'recommendation snapshot evidence is immutable'); END

CREATE TRIGGER trg_learner_recommendation_evidence_snapshot_match_insert BEFORE INSERT ON learner_recommendation_evidence FOR EACH ROW WHEN NOT EXISTS (SELECT 1 FROM learner_recommendation_items AS items INNER JOIN learner_recommendation_runs AS runs ON runs.id = items.runId INNER JOIN learner_recommendation_snapshot_evidence AS snapshot_evidence ON snapshot_evidence.id = NEW.snapshotEvidenceId WHERE items.id = NEW.itemId AND runs.snapshotId = snapshot_evidence.snapshotId AND NEW.sourceType = snapshot_evidence.sourceType AND NEW.sourceId = snapshot_evidence.sourceId) BEGIN SELECT RAISE(ABORT, 'recommendation evidence snapshot mismatch'); END

CREATE TRIGGER trg_learner_recommendation_evidence_snapshot_match_update BEFORE UPDATE ON learner_recommendation_evidence FOR EACH ROW WHEN NOT EXISTS (SELECT 1 FROM learner_recommendation_items AS items INNER JOIN learner_recommendation_runs AS runs ON runs.id = items.runId INNER JOIN learner_recommendation_snapshot_evidence AS snapshot_evidence ON snapshot_evidence.id = NEW.snapshotEvidenceId WHERE items.id = NEW.itemId AND runs.snapshotId = snapshot_evidence.snapshotId AND NEW.sourceType = snapshot_evidence.sourceType AND NEW.sourceId = snapshot_evidence.sourceId) BEGIN SELECT RAISE(ABORT, 'recommendation evidence snapshot mismatch'); END

CREATE TRIGGER trg_learner_recommendation_feedback_owner_match_insert BEFORE INSERT ON learner_recommendation_feedback FOR EACH ROW WHEN NOT EXISTS (SELECT 1 FROM learner_recommendation_items AS items INNER JOIN learner_recommendation_runs AS runs ON runs.id = items.runId WHERE items.id = NEW.itemId AND runs.studentId = NEW.studentId) BEGIN SELECT RAISE(ABORT, 'recommendation feedback learner ownership mismatch'); END

CREATE TRIGGER trg_learner_recommendation_feedback_append_only_update BEFORE UPDATE ON learner_recommendation_feedback FOR EACH ROW BEGIN SELECT RAISE(ABORT, 'append-only recommendation feedback'); END

CREATE TRIGGER trg_learner_recommendation_feedback_owner_match_update BEFORE UPDATE ON learner_recommendation_feedback FOR EACH ROW WHEN NOT EXISTS (SELECT 1 FROM learner_recommendation_items AS items INNER JOIN learner_recommendation_runs AS runs ON runs.id = items.runId WHERE items.id = NEW.itemId AND runs.studentId = NEW.studentId) BEGIN SELECT RAISE(ABORT, 'recommendation feedback learner ownership mismatch'); END

CREATE TRIGGER trg_learner_recommendation_feedback_append_only_delete BEFORE DELETE ON learner_recommendation_feedback FOR EACH ROW BEGIN SELECT RAISE(ABORT, 'append-only recommendation feedback'); END

CREATE TRIGGER trg_learner_recommendation_audit_events_owner_match_insert BEFORE INSERT ON learner_recommendation_audit_events FOR EACH ROW WHEN NOT EXISTS (SELECT 1 FROM learner_recommendation_runs AS runs WHERE runs.id = NEW.runId AND runs.studentId = NEW.studentId) BEGIN SELECT RAISE(ABORT, 'recommendation audit learner ownership mismatch'); END

CREATE TRIGGER trg_learner_recommendation_audit_events_append_only_update BEFORE UPDATE ON learner_recommendation_audit_events FOR EACH ROW BEGIN SELECT RAISE(ABORT, 'append-only recommendation audit event'); END

CREATE TRIGGER trg_learner_recommendation_audit_events_owner_match_update BEFORE UPDATE ON learner_recommendation_audit_events FOR EACH ROW WHEN NOT EXISTS (SELECT 1 FROM learner_recommendation_runs AS runs WHERE runs.id = NEW.runId AND runs.studentId = NEW.studentId) BEGIN SELECT RAISE(ABORT, 'recommendation audit learner ownership mismatch'); END

CREATE TRIGGER trg_learner_recommendation_audit_events_append_only_delete BEFORE DELETE ON learner_recommendation_audit_events FOR EACH ROW BEGIN SELECT RAISE(ABORT, 'append-only recommendation audit event'); END

CREATE UNIQUE INDEX uq_learner_recommendation_input_snapshots_student_hash ON learner_recommendation_input_snapshots (studentId, contentHash)

CREATE UNIQUE INDEX uq_learner_recommendation_input_snapshots_id_student ON learner_recommendation_input_snapshots (id, studentId)

CREATE INDEX idx_learner_recommendation_input_snapshots_student_created ON learner_recommendation_input_snapshots (studentId, createdAt)

CREATE UNIQUE INDEX uq_learner_recommendation_runs_student_idempotency ON learner_recommendation_runs (studentId, idempotencyKey)

CREATE INDEX idx_learner_recommendation_runs_student_created ON learner_recommendation_runs (studentId, createdAt)

CREATE INDEX idx_learner_recommendation_runs_snapshot_student ON learner_recommendation_runs (snapshotId, studentId)

CREATE UNIQUE INDEX uq_learner_recommendation_snapshot_evidence_snapshot_source ON learner_recommendation_snapshot_evidence (snapshotId, sourceType, sourceId)

CREATE INDEX idx_learner_recommendation_snapshot_evidence_source ON learner_recommendation_snapshot_evidence (sourceType, sourceId)

CREATE INDEX idx_learner_recommendation_items_run_lifecycle_priority ON learner_recommendation_items (runId, lifecycleStatus, priority)

CREATE UNIQUE INDEX uq_learner_recommendation_evidence_item_source ON learner_recommendation_evidence (itemId, sourceType, sourceId)

CREATE INDEX idx_learner_recommendation_evidence_source ON learner_recommendation_evidence (sourceType, sourceId)

CREATE INDEX idx_learner_recommendation_evidence_snapshot_evidence ON learner_recommendation_evidence (snapshotEvidenceId)

CREATE INDEX idx_learner_recommendation_feedback_student_created ON learner_recommendation_feedback (studentId, createdAt)

CREATE INDEX idx_learner_recommendation_feedback_item ON learner_recommendation_feedback (itemId)

CREATE INDEX idx_learner_recommendation_audit_events_student_created ON learner_recommendation_audit_events (studentId, createdAt)

CREATE INDEX idx_learner_recommendation_audit_events_run_created ON learner_recommendation_audit_events (runId, createdAt)
```

## Execution checklist (not yet authorized)

1. Verify a recoverable shared-database backup and record baseline row counts/checksums for all existing tables.
2. Run only `004_create_recommendation_store` after registry 002 and 003 match the checked-in source checksums.
3. Confirm all seven tables, indexes, foreign keys, checks, and fourteen triggers; run a foreign-key check and learner read-only smoke test.
4. Confirm no count or checksum changed for existing canonical tables and the second approved call returns `[]`.
5. Record the exact timestamp, operator, preflight, and repeat-run output here before enabling Task 9 writes.
