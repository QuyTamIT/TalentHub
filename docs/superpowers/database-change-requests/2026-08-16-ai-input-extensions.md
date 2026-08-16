# Database Change Request: Versioned assessment, evidence, and AI-consent extensions

**Requested migration path:** `Database/migrations/learner/003_create_ai_input_extensions.php`
**Version:** `003_create_ai_input_extensions`
**Scope owner:** Learner module
**Status:** exact-DDL approval granted; disposable proof and the separately authorized shared-database execution completed on 2026-08-16

## Approval Gate

The exact SQL in this request was approved for migration-source creation and SQLite/disposable verification. After a fresh compatibility preflight, backup/restore proof, live count/hash baseline, and disposable MySQL trigger proof, the user explicitly authorized non-destructive, non-conflicting execution. Migration `003` was then executed once on `talenthub_local`; the evidence is recorded below. That authorization does not authorize later schema/data changes, `ALTER`, `DROP`, deletion, truncation, backfill, a recommendation-store migration, or a model-provider change.

This request is additive and forward-only. It creates six learner-owned canonical tables and twelve learner-owned integrity triggers, writes no seed data, alters no existing table, and changes no existing row. It contains no data-mutating `INSERT`, `UPDATE`, `DELETE`, `ALTER`, `DROP`, `TRUNCATE`, rename, conversion, or backfill; the trigger headers only reject invalid future writes. It must not be treated as approval for a later recommendation, seed, shared-module, runtime-write, or model-provider change.

## Purpose and boundary

Task 3 created the canonical assessment, skill, and student identity parents. This extension makes assessment definitions immutable by version, stores answer-level evidence against a chosen version, keeps skill evidence separately auditable, and records AI consent as an ordered event stream. Existing role-owned facts stay authoritative: AI only reads consent-filtered facts and never verifies skills, awards experience hours, publishes an evaluation, or changes a shared source row.

No existing table is altered. The extension must only be considered after `002_create_ai_input_foundation` is already applied, because its canonical tables are the FK parents below. Every FK uses `ON DELETE RESTRICT ON UPDATE CASCADE`; a parent cannot be deleted while extension facts exist, while a canonical identifier update remains referentially consistent.

## Source-of-truth and ownership matrix

| Table/fields/constraints | Owner and source of truth | Learner/AI authority |
|---|---|---|
| Task 3 parents: `student_profiles`, `talent_tests`, `test_questions`, `test_attempts`, `student_skills` | Existing shared identity contract plus Task 3 canonical learner inputs | FK/read only here; never altered or written by this migration |
| `learner_assessment_versions` | Learner-owned published assessment-definition version | Learner persistence selects a published version; AI reads version, scoring version, and schema hash only |
| `learner_assessment_question_versions` | Learner-owned immutable question placement/dimension map | Learner persistence reads selected version; AI reads only normalized derived results |
| `learner_assessment_attempt_metadata` and `learner_assessment_answers` | Learner-owned attempt/answer record | Learner persistence writes under ownership/status controls; AI reads consented summarized facts only |
| `learner_skill_evidence` | Learner-owned link to an existing student skill plus its evidence reference | Verification status is supplied by the owning workflow; AI reads only and never verifies or reclassifies |
| `learner_ai_consent_events` | Learner-owned ordered consent event stream | Learner appends a grant/revoke event; AI resolves only the latest event per scope and never mutates history |
| All unique keys, FK actions, and CHECK constraints | This reviewed DDL contract | Enforces cardinality, lifecycle values, deterministic consent ordering, and JSON validity |

## Existing-query compatibility

Existing Teacher, School, Enterprise, shared `src/**`, and API queries remain untouched. They neither select from nor require columns on these six new tables. Task 3 parent tables remain unchanged: no column, key, status, row, or query shape is modified. Adding child FKs with `RESTRICT/CASCADE` is backward compatible because all new tables start empty and no existing parent row is changed.

Before any shared execution, inspect `information_schema` and fail closed if any of the six target names already exists or any required Task 3/shared parent is missing or incompatible. The reviewed statements deliberately use plain `CREATE TABLE`: after the absent-target preflight, a concurrent or unreviewed target creation fails loudly instead of accepting an unchecked shape. The learner-forward registry remains the only idempotency guard and prevents a second approved invocation from reaching these statements.

## Exact proposed DDL

All timestamps are UTC application-session values. The MySQL statement sequence below uses LF line endings, no BOM, and no fence markers. Its SHA-256 is recorded after the SQL fence.

```sql
CREATE TABLE learner_assessment_versions (
  id CHAR(36) NOT NULL, testId CHAR(36) NOT NULL, version VARCHAR(100) NOT NULL, scoringVersion VARCHAR(100) NOT NULL, schemaHash CHAR(64) NOT NULL, status VARCHAR(50) NOT NULL DEFAULT 'draft', publishedAt DATETIME(6) NULL, createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id), UNIQUE KEY uq_learner_assessment_versions_test_version (testId, version), KEY idx_learner_assessment_versions_test_status (testId, status),
  CONSTRAINT fk_learner_assessment_versions_test FOREIGN KEY (testId) REFERENCES talent_tests(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_learner_assessment_versions_status CHECK (status IN ('draft','published','retired')),
  CONSTRAINT chk_learner_assessment_versions_published_at CHECK ((status = 'draft' AND publishedAt IS NULL) OR (status IN ('published','retired') AND publishedAt IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE learner_assessment_question_versions (
  id CHAR(36) NOT NULL, versionId CHAR(36) NOT NULL, questionId CHAR(36) NOT NULL, position INT UNSIGNED NOT NULL, dimensionCode VARCHAR(100) NOT NULL, required TINYINT UNSIGNED NOT NULL DEFAULT 1, createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id), UNIQUE KEY uq_learner_assessment_question_versions_version_question (versionId, questionId), UNIQUE KEY uq_learner_assessment_question_versions_version_position (versionId, position), KEY idx_learner_assessment_question_versions_question (questionId),
  CONSTRAINT fk_learner_assessment_question_versions_version FOREIGN KEY (versionId) REFERENCES learner_assessment_versions(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_learner_assessment_question_versions_question FOREIGN KEY (questionId) REFERENCES test_questions(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_learner_assessment_question_versions_position CHECK (position >= 1),
  CONSTRAINT chk_learner_assessment_question_versions_required CHECK (required IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE learner_assessment_attempt_metadata (
  id CHAR(36) NOT NULL, attemptId CHAR(36) NOT NULL, versionId CHAR(36) NOT NULL, status VARCHAR(50) NOT NULL DEFAULT 'in_progress', expiresAt DATETIME(6) NULL, submittedAt DATETIME(6) NULL, inputHash CHAR(64) NULL, createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id), UNIQUE KEY uq_learner_assessment_attempt_metadata_attempt (attemptId), KEY idx_learner_assessment_attempt_metadata_version_status (versionId, status),
  CONSTRAINT fk_learner_assessment_attempt_metadata_attempt FOREIGN KEY (attemptId) REFERENCES test_attempts(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_learner_assessment_attempt_metadata_version FOREIGN KEY (versionId) REFERENCES learner_assessment_versions(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_learner_assessment_attempt_metadata_status CHECK (status IN ('in_progress','submitted','expired')),
  CONSTRAINT chk_learner_assessment_attempt_metadata_submission CHECK ((status = 'submitted' AND submittedAt IS NOT NULL AND inputHash IS NOT NULL) OR (status <> 'submitted' AND submittedAt IS NULL AND inputHash IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE learner_assessment_answers (
  id CHAR(36) NOT NULL, attemptId CHAR(36) NOT NULL, questionId CHAR(36) NOT NULL, answerJson LONGTEXT NOT NULL, answeredAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id), UNIQUE KEY uq_learner_assessment_answers_attempt_question (attemptId, questionId), KEY idx_learner_assessment_answers_question (questionId),
  CONSTRAINT fk_learner_assessment_answers_attempt FOREIGN KEY (attemptId) REFERENCES learner_assessment_attempt_metadata(attemptId) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_learner_assessment_answers_question FOREIGN KEY (questionId) REFERENCES test_questions(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_learner_assessment_answers_json CHECK (JSON_VALID(answerJson))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE learner_skill_evidence (
  id CHAR(36) NOT NULL, studentSkillId CHAR(36) NOT NULL, evidenceType VARCHAR(50) NOT NULL, evidenceRef VARCHAR(191) NOT NULL, verificationStatus VARCHAR(50) NOT NULL DEFAULT 'pending', observedAt DATETIME(6) NOT NULL, createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id), KEY idx_learner_skill_evidence_student_skill_observed (studentSkillId, observedAt), KEY idx_learner_skill_evidence_evidence_ref (evidenceRef),
  CONSTRAINT fk_learner_skill_evidence_student_skill FOREIGN KEY (studentSkillId) REFERENCES student_skills(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_learner_skill_evidence_verification CHECK (verificationStatus IN ('self_declared','pending','verified','rejected'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE learner_ai_consent_events (
  id CHAR(36) NOT NULL, studentId CHAR(36) NOT NULL, scope VARCHAR(50) NOT NULL, action VARCHAR(50) NOT NULL, policyVersion VARCHAR(100) NOT NULL, occurredAt DATETIME(6) NOT NULL, requestId CHAR(36) NOT NULL,
  PRIMARY KEY (id), UNIQUE KEY uq_learner_ai_consent_events_student_scope_occurred_request (studentId, scope, occurredAt, requestId), KEY idx_learner_ai_consent_events_student_scope_occurred (studentId, scope, occurredAt),
  CONSTRAINT fk_learner_ai_consent_events_student FOREIGN KEY (studentId) REFERENCES student_profiles(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_learner_ai_consent_events_scope CHECK (scope IN ('assessment','skills','activity','evaluation')),
  CONSTRAINT chk_learner_ai_consent_events_action CHECK (action IN ('granted','revoked'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TRIGGER trg_learner_assessment_versions_immutable_update
BEFORE UPDATE ON learner_assessment_versions
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'assessment version is immutable';
END;

CREATE TRIGGER trg_learner_assessment_versions_immutable_delete
BEFORE DELETE ON learner_assessment_versions
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'assessment version is immutable';
END;

CREATE TRIGGER trg_learner_assessment_question_versions_test_match_insert
BEFORE INSERT ON learner_assessment_question_versions
FOR EACH ROW
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM learner_assessment_versions AS versions
    INNER JOIN test_questions AS questions ON questions.id = NEW.questionId
    WHERE versions.id = NEW.versionId AND versions.testId = questions.testId
  ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'assessment question version test mismatch';
  END IF;
END;

CREATE TRIGGER trg_learner_assessment_question_versions_test_match_update
BEFORE UPDATE ON learner_assessment_question_versions
FOR EACH ROW
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM learner_assessment_versions AS versions
    INNER JOIN test_questions AS questions ON questions.id = NEW.questionId
    WHERE versions.id = NEW.versionId AND versions.testId = questions.testId
  ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'assessment question version test mismatch';
  END IF;
END;

CREATE TRIGGER trg_learner_assessment_question_versions_immutable_update
BEFORE UPDATE ON learner_assessment_question_versions
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'assessment question version is immutable';
END;

CREATE TRIGGER trg_learner_assessment_question_versions_immutable_delete
BEFORE DELETE ON learner_assessment_question_versions
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'assessment question version is immutable';
END;

CREATE TRIGGER trg_learner_assessment_attempt_metadata_test_match_insert
BEFORE INSERT ON learner_assessment_attempt_metadata
FOR EACH ROW
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM test_attempts AS attempts
    INNER JOIN learner_assessment_versions AS versions ON versions.id = NEW.versionId
    WHERE attempts.id = NEW.attemptId AND attempts.testId = versions.testId
  ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'assessment attempt version test mismatch';
  END IF;
END;

CREATE TRIGGER trg_learner_assessment_attempt_metadata_test_match_update
BEFORE UPDATE ON learner_assessment_attempt_metadata
FOR EACH ROW
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM test_attempts AS attempts
    INNER JOIN learner_assessment_versions AS versions ON versions.id = NEW.versionId
    WHERE attempts.id = NEW.attemptId AND attempts.testId = versions.testId
  ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'assessment attempt version test mismatch';
  END IF;
END;

CREATE TRIGGER trg_learner_assessment_answers_version_match_insert
BEFORE INSERT ON learner_assessment_answers
FOR EACH ROW
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM learner_assessment_attempt_metadata AS metadata
    INNER JOIN learner_assessment_question_versions AS question_versions ON question_versions.versionId = metadata.versionId
    WHERE metadata.attemptId = NEW.attemptId AND question_versions.questionId = NEW.questionId
  ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'assessment answer question version mismatch';
  END IF;
END;

CREATE TRIGGER trg_learner_assessment_answers_version_match_update
BEFORE UPDATE ON learner_assessment_answers
FOR EACH ROW
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM learner_assessment_attempt_metadata AS metadata
    INNER JOIN learner_assessment_question_versions AS question_versions ON question_versions.versionId = metadata.versionId
    WHERE metadata.attemptId = NEW.attemptId AND question_versions.questionId = NEW.questionId
  ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'assessment answer question version mismatch';
  END IF;
END;

CREATE TRIGGER trg_learner_ai_consent_events_append_only_update
BEFORE UPDATE ON learner_ai_consent_events
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'append-only consent event';
END;

CREATE TRIGGER trg_learner_ai_consent_events_append_only_delete
BEFORE DELETE ON learner_ai_consent_events
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'append-only consent event';
END;
```

```text
SHA-256: `2f64222d2ffa82ab77e5c1a697682723a22b26ea9eaf1a3b4770c5bc92e6e09f`
```

## Canonical FK contract

| Child table | Child column | Parent table/column | Delete/update action | Reason |
|---|---|---|---|---|
| `learner_assessment_versions` | `testId` | Task 3 `talent_tests.id` | `RESTRICT` / `CASCADE` | Version belongs to one canonical test |
| `learner_assessment_question_versions` | `versionId` | `learner_assessment_versions.id` | `RESTRICT` / `CASCADE` | Question placement belongs to one version |
| `learner_assessment_question_versions` | `questionId` | Task 3 `test_questions.id` | `RESTRICT` / `CASCADE` | Version retains canonical question identity |
| `learner_assessment_attempt_metadata` | `attemptId` | Task 3 `test_attempts.id` | `RESTRICT` / `CASCADE` | One version binding per attempt |
| `learner_assessment_attempt_metadata` | `versionId` | `learner_assessment_versions.id` | `RESTRICT` / `CASCADE` | Attempt uses one immutable assessment version |
| `learner_assessment_answers` | `attemptId` | `learner_assessment_attempt_metadata.attemptId` | `RESTRICT` / `CASCADE` | Answers require an owned version binding |
| `learner_assessment_answers` | `questionId` | Task 3 `test_questions.id` | `RESTRICT` / `CASCADE` | Answers retain canonical question identity |
| `learner_skill_evidence` | `studentSkillId` | Task 3 `student_skills.id` | `RESTRICT` / `CASCADE` | Evidence cannot outlive the skill claim |
| `learner_ai_consent_events` | `studentId` | shared `student_profiles.id` | `RESTRICT` / `CASCADE` | Consent belongs to one learner |

## Versioning and append-only guarantees

`learner_assessment_versions` uniquely identifies `(testId, version)`; database triggers reject every update/delete, so a published historical version cannot be rewritten or removed. `learner_assessment_question_versions` uniquely identifies both `(versionId, questionId)` and `(versionId, position)`, so the selected question set and order cannot be ambiguous. Its insert/update validation triggers require the version's `testId` to equal the selected `test_questions.testId`, and its immutable triggers reject every later update/delete. `learner_assessment_attempt_metadata.attemptId` is unique; each answer is unique by `(attemptId, questionId)` and is JSON-valid. Learner-owned insert/update triggers reject an attempt whose `test_attempts.testId` differs from its selected version's `testId`, and reject an answer whose question is absent from that selected version.

`learner_ai_consent_events` deliberately has no `updatedAt`, `revokedAt`, current-state flag, or replacement key. Its immutable ordering key is `(studentId, scope, occurredAt, requestId)`; readers must resolve the latest event by `occurredAt DESC, requestId DESC`. Database-level learner-owned triggers reject every update and delete, so future learner write code can append an event only. This migration itself contains no data mutation; database privileges and future repository tests must preserve the append-only contract.

## Mandatory preflight, backup, and execution evidence

Before a disposable or shared run, assert all six target tables are absent, migration `002_create_ai_input_foundation` is recorded in `learner_forward_migrations` with the fixed checked-in source checksum `f1c7d125c475fddad946448b9a320ae6207ea5903eaa2d652fb456d505a929bc`, and all Task 3 FK parents have the reviewed primary-key/type, InnoDB, `utf8mb4`, and `utf8mb4_unicode_ci` contracts. The forward runner deliberately preflights every pending approved version before it applies any DDL, so `migrateApproved(['002_create_ai_input_foundation', '003_create_ai_input_extensions'])` fails closed: 003 correctly sees no registered 002 yet. Invoke 003 only after a separate successful, registered 002 invocation. Require `@@session.time_zone = '+00:00'` before timestamp defaults are evaluated. If any target exists, the registry entry is absent/drifted, or a parent differs, stop: do not use `ALTER`, copy, or data repair.

Before shared execution, take a restorable, access-controlled backup/provider snapshot and record its UTC completion time, checksum/identifier, successful restore validation, fresh existing-parent counts, and normalized schema hashes. Run the migration on a dedicated disposable schema twice first; retain proof that the first run applies only `003_create_ai_input_extensions`, the second returns `[]`, exactly one registry record exists, all six tables/FKs/CHECKs/triggers exist, invalid cross-test/cross-version writes and consent update/delete are rejected, and no Task 3/shared parent row count changes.

Immediately before and after a separately approved shared run, compare counts and normalized schema fingerprints for `student_profiles`, `talent_tests`, `test_questions`, `test_attempts`, and `student_skills`; require equality. Confirm all six new tables start with zero rows, FK integrity passes, the registry checksum matches source, and existing role read-only smoke checks pass. Do not record credentials, raw personal data, answers, or consent events in this request.

## Execution record (completed)

The approved MySQL statement sequence retained SHA-256 `2f64222d2ffa82ab77e5c1a697682723a22b26ea9eaf1a3b4770c5bc92e6e09f`. The migration source file checksum recorded in the registry is `6b2c5674e4da5d98bc7540881f90ce5fab421d2cf52e41b7899f51a87d563c38`.

### Backup, restore, and disposable proof

Before 003, a new logical backup was created at `D:\TalentHub\.tmp\learner-ai-backups\shared-pre-003-20260816T072000Z.sql`: 92,973 bytes, SHA-256 `c819fec82761368c057e123c57a5e3bd426c9961e9c76e1a510cf650b64b5183`. It used `--single-transaction --routines --events --triggers --no-tablespaces` under the local administrator and restored successfully into a newly created disposable schema. No shared object or row was changed while provisioning/restoring disposable schemas.

The application principal's first disposable execution stopped at MySQL error 1419 because binary logging was enabled and that principal lacked the privilege required to create triggers. It created no registry record; its partially created disposable schema was deliberately preserved rather than deleted. A fresh disposable restore was then used. The migration ran under the local administrator only for DDL/triggers with an explicitly session-scoped `+00:00` time zone; its first run returned `['003_create_ai_input_extensions']` and its immediate second run returned `[]`.

The application principal then performed a disposable transaction that inserted only synthetic trigger-probe rows and rolled it back. It proved each of the following was rejected: cross-test question-version map, cross-test attempt/version binding, answer outside selected version, assessment-version update, question-version delete, consent update, and consent delete. No probe row persisted.

### Fresh shared preflight and execution

At `2026-08-16T07:28:35Z`, a read-only transaction against `talenthub_local` confirmed MySQL `8.4.3`, `@@session.time_zone = '+00:00'`, the registered 002 checksum `f1c7d125c475fddad946448b9a320ae6207ea5903eaa2d652fb456d505a929bc`, and that all six 003 targets were absent. The migration preflight passed. Parent count/hash evidence at that instant was:

| Parent | Count | Normalized schema SHA-256 |
|---|---:|---|
| `student_profiles` | 12 | `0c85d3ba8fef1ba0fccb7d34191e5f1b0b0fcff17e272b359b0138e5ea6267bf` |
| `talent_tests` | 0 | `5c006a08323100675e8cc5bae8e61273b3d1be5989d7f19a43b114d97200e189` |
| `test_questions` | 0 | `d74474493f68906aae6f274ccc7f8b02eca58a6a7d18729aa4b11fd2d6ea9597` |
| `test_attempts` | 0 | `b751ac7823cce44a3027534df883d4adcf093e011554b1c137b895e574650ae7` |
| `student_skills` | 0 | `c1e51e7bc1014af4e75a855b4fc662218999e0ce97be90c9f226937726f63fdc` |

Because the application principal cannot create triggers under the server's binary-log setting, the local database administrator executed the reviewed runner only for migration DDL with a session-scoped `+00:00` time zone. The first shared call returned `['003_create_ai_input_extensions']`; the immediate second call returned `[]`. The regular application principal subsequently read all six new tables successfully.

Post-execution read-only verification confirmed the five parent counts and normalized schema hashes above were identical, all six new tables contained 0 rows, all six targets existed, all nine FK rules were `ON DELETE RESTRICT ON UPDATE CASCADE`, `@@foreign_key_checks=1`, and all 12 learner trigger definitions existed. The registry checksum exactly matched the migration source file. Empty new-child tables mean no newly introduced child-row FK violations exist; trigger rejection semantics were proven on the disposable schema with a rollback-only application-principal transaction. Existing Teacher/School/Enterprise PHP syntax smoke checks pass and no protected-role, `src`, or `api` path is in the committed diff.

No existing table was altered and no existing row was written, deleted, truncated, or replaced. Future trigger-owning migrations require a dedicated migration principal with the relevant MySQL binary-log privilege, or a separately approved server configuration change; the application principal must not be granted broader administrative privileges merely to run normal learner features.

## Operational rollback

There is no destructive down migration. If a later runtime issue appears, disable learner write/read feature flags or route configuration and preserve all tables and rows. Any restore, remediation, schema alteration, data rewrite, deletion, truncation, or future extension requires a separately approved incident/change procedure.
