# Phase 3 Review Remediation Design

## Decision

Complete Phase 3 with a forward-only repair. The two migrations already recorded in `schema_migrations` remain byte-identical. Schema gaps are repaired by a new additive migration, while application defects are corrected behind the existing Student endpoints and permission vocabulary.

No badge tables, Phase 4 lifecycle work, AI visibility change, commit, push, merge, reset, clean, or edits to `.env`, `.claude/`, `.qwen/`, or learner migrations `001`–`004` are in scope.

## Alternatives considered

1. **Forward-only repair — selected.** Add a new migration after `20260821000200`, preserve migration history, repair the project contract, and strengthen Phase 3 behavior and tests.
2. **Relax the Phase 2 contract to accept the current project schema — rejected.** This would make readiness green by weakening an already approved contract and would leave `category` and `contribution` unavailable.
3. **Edit, roll back, or rebuild the two applied migrations — rejected.** Editing them causes checksum drift; rollback or rebuild risks existing data and violates the forward-recovery rule.

## Scope and architecture

### 1. Schema reconciliation

Create `Database/migrations/20260821000210_reconcile_phase_3_contracts.php`.

The migration:

- Requires the five Phase 3 tables and their parent tables.
- Rejects an unexpected or semantically incompatible existing schema before DDL.
- Adds `projects.category VARCHAR(100)` and backfills existing rows with the stable value `general` before making it `NOT NULL`.
- Adds nullable `project_members.contribution TEXT`.
- Widens `projects.startAt` and `projects.endAt` from `DATE` to `DATETIME(6)` without discarding existing dates.
- Adds a nullable `student_profile_shares.consentId` foreign key to `privacy_consents(id)` so future share revocation can update the consent record associated with that share. Existing legacy shares may remain null and continue to rely on the share's own revoke/expiry state.
- Is non-reversible and has no destructive `down()`.
- Does not create badge tables or modify Teacher, School, Enterprise, activity, assessment, or AI-owned tables.

Because the primary database already contains the two original migrations, the repair must be rehearsed on a disposable restored clone, applied twice through the canonical migration runner, and only then applied to `talenthub_local` after a fresh backup with SHA-256 and old-table count/hash evidence.

### 2. Readiness and optional capability contract

Phase 3 readiness uses the canonical existing `privacy_consents` columns: `isGranted`, `policyVersion`, `grantedAt`, `revokedAt`, and `createdAt`. The project optional capability keeps the approved `category` and `contribution` requirements and must report `available` after the repair migration.

Scope diagnostics must distinguish accumulated reviewed Phase 0–3 changes from forbidden changes. They may not silently ignore `.env`, `.claude/`, `.qwen/`, protected learner migrations, or unrelated role code. The final report must list any remaining scope warning separately from schema readiness.

### 3. Profile sharing authorization and consent

`POST`, `GET`, and revoke operations on `profile-shares.php` require `student_profile.share_own`; mutations additionally require `privacy_consent.manage_own` and CSRF. A helper in `LearnerApiContext` may enforce multiple permissions for the same authenticated Student without accepting a client-supplied user or student ID.

Share creation stores the raw token only in the one-time response and stores its SHA-256 hash in the database. It inserts the `profile_share` consent and share row in one transaction and writes the resulting consent ID into the share row. Revoke locks the owned share, updates its linked consent record when present, sets `revokedAt`, and commits atomically. Expired or revoked shares never resolve.

Email and phone remain excluded unless the Student explicitly selects them in the UI. The public response contains only the stored allow-list. It sends `Cache-Control: no-store` and `Referrer-Policy: no-referrer`.

Enterprise receives no direct arbitrary Student query in Phase 3. The existing Enterprise mock screen is not treated as a real consent consumer. A negative cross-role test must prove there is no Enterprise route that bypasses a share token or `talent.read_consented`; real Talent Explorer database integration remains owned by its later roadmap phase and must be reported honestly rather than claimed complete here.

### 4. Certificate command safety

Certificate create, update, and delete use explicit repository transaction boundaries.

- Update/delete lock the owned row (`FOR UPDATE` on MySQL), require `verificationStatus = 'unverified'` both in the locked check and the final mutation predicate, and treat a stale zero-row mutation as a conflict.
- Cross-student access remains a non-enumerating `404`.
- Partial date updates validate the merged persisted values and return `422`, not a database-derived `503`.
- Empty PATCH input returns `422`.
- `credentialUrl` accepts only HTTPS URLs. `javascript:`, `data:`, credentials-in-URL, and other schemes are rejected before persistence.

Profile `avatarUrl` is restricted to HTTPS or an application-relative path so public rendering cannot load an active non-web scheme.

### 5. Tests

Every production change follows RED–GREEN–REFACTOR.

Focused tests must cover:

- Phase 3 readiness against the canonical consent columns.
- A disposable MySQL migration from the 17-migration state to the repaired schema, including second-run idempotency and `projects=available`.
- Endpoint permission checks for `student_profile.share_own` and `privacy_consent.manage_own`, CSRF, and cross-student revoke.
- Share/consent atomic rollback and linked consent revocation.
- Certificate transaction boundaries, stale verification protection, partial date validation, empty PATCH, and HTTPS-only credentials.
- Actual rendering of the public shared page with hostile text/URL fixtures; the test may not stop at service resolution.
- Student, Teacher, School, Enterprise, assessment, activity, recommendation, AI-shadow, PHP lint, Node UI, migration validation, and `git diff --check` regressions.

Tests must not mutate `talenthub_local` except for the approved repair migration. Behavioral fixtures use SQLite or an explicitly named disposable MySQL schema. Temporary schemas are resolved and checked before cleanup.

## Recovery and reporting

The DCR and Phase 3 report are corrected to include:

- Exact backup path, byte length, and SHA-256.
- Pre/post row counts and stable hashes for every pre-existing table.
- Disposable restore and repair migration evidence.
- Exact schema, permission, readiness, and optional-capability results.
- An honest statement that Enterprise Talent Explorer remains a later-phase integration rather than a completed Phase 3 database consumer.
- Forward-recovery instructions; no automatic restore over the primary database.

## Acceptance criteria

Phase 3 passes only when:

1. `bin/migrate.php status` reports the repair migration applied and zero pending, and `validate` reports no drift.
2. Phase 3 schema readiness has no missing-column/index failure.
3. `TalentPassportOptionalSchema::status(..., 'projects')` is `available` on live MySQL.
4. Sharing enforces both dedicated permissions, CSRF, ownership, expiry, revoke, consent linkage, and raw-token non-persistence.
5. Certificate mutations are transaction-safe and cannot race a verification transition.
6. Public links cannot persist or render an unsafe URL scheme.
7. Focused tests and all listed regressions pass with fresh output.
8. Backup/rehearsal/report evidence is internally consistent.
9. `TALENTHUB_AI_VISIBLE_PERCENT` remains `0`, and no Phase 4, badge, or unrelated role mutation is introduced.
