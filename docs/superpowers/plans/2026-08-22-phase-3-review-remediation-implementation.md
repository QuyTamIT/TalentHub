# Phase 3 Review Remediation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Repair the applied Phase 3 implementation so its schema, readiness, authorization, consent, certificate mutations, public rendering, migration evidence, and regressions satisfy the approved Phase 3 contract.

**Architecture:** Keep applied migrations `20260821000100` and `20260821000200` byte-identical. Add one forward-only reconciliation migration, then harden the existing Student services/endpoints with dedicated permissions and transaction-safe mutations. Rehearse the migration on an explicitly named disposable clone before backing up and applying it to `talenthub_local`.

**Tech Stack:** PHP 8.3.30, PDO, MySQL 8.4.3, SQLite test fixtures, vanilla JavaScript, existing TalentHub migration/readiness/RBAC frameworks.

## Global Constraints

- Work in `D:\TalentHub` on `feature/student`; preserve all pre-existing work.
- PHP executable: `D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe`.
- MySQL executable: `D:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe`.
- Follow `docs/superpowers/specs/2026-08-22-phase-3-review-remediation-design.md`.
- Do not edit applied migrations `20260821000100` or `20260821000200`.
- Do not edit learner migrations `Database/migrations/learner/001`–`004`.
- Do not create badge tables or begin Phase 4.
- Do not edit `.env`, `.claude/`, or `.qwen/`.
- Keep `TALENTHUB_AI_VISIBLE_PERCENT=0`.
- Do not commit, push, merge, reset, clean, stash, or discard existing changes.
- Write a failing regression test before every production behavior change.
- Never run fixture DML against `talenthub_local`; only the approved canonical repair migration may mutate it after backup/rehearsal.

---

### Task 1: Lock readiness and repair-migration contracts

**Files:**
- Modify: `tests/learner_phase_requirements_test.php`
- Modify: `tests/learner_phase_2_optional_capabilities_test.php`
- Create: `tests/phase_3_reconciliation_migration_test.php`
- Modify: `app/learner/data/Readiness/PhaseRequirements.php`

**Interfaces:**
- Consumes: `PhaseRequirements::forPhase(3)`, `TalentPassportOptionalSchema::status(...)`.
- Produces: canonical consent readiness and the exact forward migration contract.

- [ ] **Step 1: Add failing readiness assertions**

Assert Phase 3 requires the canonical existing consent fields:

```php
$phase3 = (new PhaseRequirements())->forPhase(3);
$assert($phase3['columns']['privacy_consents'] === [
    'id', 'studentId', 'scope', 'isGranted', 'policyVersion',
    'grantedAt', 'revokedAt', 'createdAt',
], 'Phase 3 uses the canonical privacy consent schema');
```

- [ ] **Step 2: Add the failing reconciliation migration contract**

Require validation-only precursors `20260821000204_validate_phase_3_canonical_contracts.php`, `20260821000205_preflight_phase_3_reconciliation.php`, and `20260821000206_validate_phase_3_exact_metadata.php`. They validate canonical column/default/precision metadata, ordered indexes, FK actions, exact CHECK grouping, exact `EXTRA`/`ON UPDATE` behavior, and consent owner/scope without DDL. On a fresh database all three sort before the repair. On an already-repaired database, `00206` remains safe because its `up()` performs no DDL or application DML. Keep the non-reversible `20260821000210_reconcile_phase_3_contracts.php` for guarded additions to `projects.category`, `project_members.contribution`, `student_profile_shares.consentId`, the consent FK/index, project date widening, and no table drop/truncate/delete.

- [ ] **Step 3: Run RED**

```powershell
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests\learner_phase_requirements_test.php
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests\phase_3_reconciliation_migration_test.php
```

Expected: the readiness assertion fails on `isConsented/consentedAt`; the migration contract fails because the repair migration is absent.

- [ ] **Step 4: Correct only the Phase 3 consent column map**

Replace the Phase 3 consent columns with the asserted canonical list. Do not weaken project capability requirements.

- [ ] **Step 5: Run the readiness test GREEN**

Expected: `learner_phase_requirements_test: OK`; the migration test remains RED until Task 2.

### Task 2: Implement and rehearse the forward-only schema repair

**Files:**
- Create: `Database/migrations/20260821000210_reconcile_phase_3_contracts.php`
- Test: `tests/phase_3_reconciliation_migration_test.php`
- Modify: `docs/superpowers/database-change-requests/2026-08-22-phase-3-passport-evidence-sharing.md`

**Interfaces:**
- Consumes: live schema produced by migrations `000100` and `000200`.
- Produces: compatible projects and linked share consent without rewriting existing evidence.

- [ ] **Step 1: Implement strict preflight helpers**

Preflight must assert the five Phase 3 tables plus `privacy_consents`, and inspect `information_schema.columns`, `statistics`, `referential_constraints`, and `check_constraints`. It must reject unexpected types/status constraints or orphan share/student/project/member rows before DDL.

- [ ] **Step 2: Implement additive repair DDL**

Use guarded information-schema checks and execute the equivalent of:

```sql
ALTER TABLE projects ADD COLUMN category VARCHAR(100) NULL AFTER title;
UPDATE projects SET category = 'general' WHERE category IS NULL OR TRIM(category) = '';
ALTER TABLE projects MODIFY category VARCHAR(100) NOT NULL;
ALTER TABLE projects MODIFY startAt DATETIME(6) NULL, MODIFY endAt DATETIME(6) NULL;
ALTER TABLE project_members ADD COLUMN contribution TEXT NULL AFTER role;
ALTER TABLE student_profile_shares ADD COLUMN consentId CHAR(36) NULL AFTER studentId;
ALTER TABLE student_profile_shares ADD KEY idx_student_profile_shares_consent (consentId);
ALTER TABLE student_profile_shares ADD CONSTRAINT fk_student_profile_shares_consent
  FOREIGN KEY (consentId) REFERENCES privacy_consents(id)
  ON DELETE SET NULL ON UPDATE CASCADE;
```

The migration is non-reversible. Its `down()` is empty with a forward-recovery comment.

- [ ] **Step 3: Run contract GREEN and migration validation**

Expected: reconciliation contract passes and `bin/migrate.php validate` reports one pending migration without drift.

- [ ] **Step 4: Create and verify a disposable restored clone**

Use a unique schema `talenthub_phase3_fix_<timestamp>`. Validate the resolved name against `\Atalenthub_phase3_fix_[0-9]{14}\z` before any drop. Restore the latest verified pre-Phase-3 backup or create a fresh dump of the current 17-migration primary for repair-only rehearsal. Record dump length and SHA-256.

- [ ] **Step 5: Capture clone pre-state, apply twice, and verify**

Capture stable row counts/hashes for all existing tables, apply the repair migration once, run migrate again expecting no changes, validate final schema, and prove old-table rows outside the intentionally altered three tables are unchanged. Confirm project capability becomes `available`.

- [ ] **Step 6: Clean only the verified disposable clone**

Drop only the exact validated temporary schema and record cleanup. Do not apply to `talenthub_local` yet.

### Task 3: Enforce dedicated sharing permissions and linked consent

**Files:**
- Modify: `app/learner/api/LearnerApiContext.php`
- Modify: `app/learner/api/v1/profile-shares.php`
- Modify: `app/learner/data/Service/ProfileSharingService.php`
- Modify: `tests/learner_profile_privacy_api_test.php`
- Create: `tests/learner_profile_sharing_endpoint_contract_test.php`

**Interfaces:**
- Produces: `studentIdForPermissions(list<string> $permissions): string` and atomic share/consent lifecycle.

- [ ] **Step 1: Write failing permission and consent tests**

Require all endpoint branches to use `student_profile.share_own`; POST/DELETE must also require `privacy_consent.manage_own` and CSRF. In SQLite service tests, add `consentId` and assert create writes it, cross-owner revoke is `404`, owner revoke updates both the share and linked consent, and an injected consent/share failure rolls back both rows.

- [ ] **Step 2: Run RED**

Expected: endpoint contract finds `student_profile.update_own`; service leaves `consentId` null and does not revoke consent atomically.

- [ ] **Step 3: Add multi-permission Student resolution**

Implement:

```php
public function studentIdForPermissions(array $permissions): string
{
    $user = $this->session->requireUser();
    if (($user['role'] ?? null) !== 'student') {
        throw new ApiException(403, 'PERMISSION_DENIED', 'Endpoint chỉ dành cho học viên.');
    }
    foreach (array_values(array_unique($permissions)) as $permission) {
        $this->permissions->require((string) $user['id'], $permission);
    }
    return $this->resolveStudentId((string) $user['id']);
}
```

Keep `studentId(string $permission)` as a compatibility wrapper.

- [ ] **Step 4: Make create/revoke one transaction each**

Creation inserts consent first, then the share with `consentId`, and commits. Revoke starts a transaction, selects the owned share `FOR UPDATE` on MySQL, updates the linked consent to revoked-state columns when present, sets share `revokedAt`, checks affected rows, and commits. Roll back on any throwable.

- [ ] **Step 5: Wire endpoint permissions and run GREEN**

GET uses `['student_profile.share_own']`; POST/DELETE use `['student_profile.share_own', 'privacy_consent.manage_own']`, with CSRF on mutations. Run the service and endpoint contract tests; expected zero failures.

### Task 4: Make certificate mutations transaction- and race-safe

**Files:**
- Modify: `app/learner/data/Database/DatabaseCertificateCommandRepository.php`
- Modify: `app/learner/data/Service/CertificateCommandService.php`
- Modify: `tests/learner_certificate_api_test.php`

**Interfaces:**
- Produces: transactional create/update/delete and merged-date validation.

- [ ] **Step 1: Add failing behavior tests**

Add tests for empty update input, expiry-only update before persisted issue date, issue-only update after persisted expiry, unsafe URL schemes, user-info URLs, and final mutation predicates containing `verificationStatus = 'unverified'`. Add a transaction rollback test using a SQLite trigger that aborts the final select/update path.

- [ ] **Step 2: Run RED**

Expected: current code accepts unsafe schemes/empty patch, date errors escape through the DB, and repository source lacks the atomic predicate/transactions.

- [ ] **Step 3: Validate merged update state**

Load the owned certificate for validation, reject missing/foreign IDs as `404`, reject non-unverified rows, merge submitted values with persisted dates, then validate `expiryDate >= issueDate`. Reject an empty update with `422 VALIDATION_FAILED`.

- [ ] **Step 4: Restrict URLs**

After `FILTER_VALIDATE_URL`, parse the scheme, host, user, and pass. Accept only lowercase-equivalent `https`, a non-empty host, and no URL credentials.

- [ ] **Step 5: Add repository transaction boundaries**

Create wraps INSERT plus persisted-row read. Update/delete begin a transaction, select the owned row with `FOR UPDATE` on MySQL, require unverified, use a final `WHERE studentId=:studentId AND id=:id AND verificationStatus='unverified'`, require exactly one affected row, and commit. A stale zero-row change returns `409 STALE_CERTIFICATE_STATE`.

- [ ] **Step 6: Run GREEN**

Run certificate tests and PHP lint. Expected: all certificate cases pass and no syntax errors.

### Task 5: Harden profile/public rendering and exercise the real view

**Files:**
- Modify: `src/Modules/Student/Service/StudentProfileService.php`
- Modify: `app/learner/shared-profile.php`
- Modify: `tests/student_profile_ownership_api_test.php`
- Modify: `tests/learner_shared_profile_render_test.php`

**Interfaces:**
- Produces: safe avatar URL policy and no-referrer public output.

- [ ] **Step 1: Add failing URL and render tests**

Reject `javascript:`, `data:`, protocol-relative, and credential-bearing avatar URLs; accept HTTPS and application-relative `/...` paths. Render the real shared-profile template in a subprocess/test harness with a hostile stored credential URL and assert no unsafe scheme reaches an `href`, dynamic text is escaped, `Referrer-Policy: no-referrer` exists, and no raw token appears outside the request URL.

- [ ] **Step 2: Run RED**

Expected: avatar validation accepts unsafe text and the template lacks Referrer-Policy.

- [ ] **Step 3: Implement URL policy and headers**

Add a focused profile URL validator. Add `header('Referrer-Policy: no-referrer')` and a restrictive content-security policy compatible with local assets. Render certificate links only after an HTTPS safety check, even though persistence also validates them.

- [ ] **Step 4: Run GREEN**

Run profile ownership, privacy, and actual render tests. Expected: explicit `OK` markers and no unsafe URL output.

### Task 6: Make scope/readiness evidence truthful and lock cross-role boundaries

**Files:**
- Modify: `tests/learner_readiness_test.php`
- Modify: `tests/student_portal_cross_role_contract_test.php`
- Modify: `docs/superpowers/readiness/2026-08-22-phase-3-profile-evidence-sharing-review-report.md`

**Interfaces:**
- Produces: separate schema/readiness evidence and a negative Enterprise bypass guarantee.

- [ ] **Step 1: Add failing report/contract assertions**

Require reports not to claim Enterprise database consent-read, require explicit deferral to the later Talent Explorer phase, and prove no current Enterprise endpoint accepts an arbitrary Student ID or reads real `privacy_consents` as a Phase 3 bypass. Require readiness output to use canonical consent columns and report project capability available after migration.

- [ ] **Step 2: Run RED**

Expected: the existing report claims all gates green without the required deferral/evidence.

- [ ] **Step 3: Correct reporting without weakening scope protection**

Keep protected paths protected. Classify accumulated pre-existing scope findings separately in the final report; do not convert them into schema failures or silently allow `.claude/.qwen`. Record schema readiness as an independently verified gate.

- [ ] **Step 4: Run cross-role GREEN**

Run Student, Teacher, School, Enterprise, permission, activity, assessment, QR, and recommendation contract tests. Expected: no permission vocabulary or route regression.

### Task 7: Backup, apply the repair migration, and verify primary invariants

**Files:**
- Modify: `docs/superpowers/database-change-requests/2026-08-22-phase-3-passport-evidence-sharing.md`
- Modify: `docs/superpowers/readiness/2026-08-22-phase-3-rehearsal-report.md`
- Modify: `docs/superpowers/readiness/2026-08-22-phase-3-profile-evidence-sharing-review-report.md`

**Interfaces:**
- Produces: primary schema at 18 applied migrations, zero pending, no drift, with auditable recovery evidence.

- [ ] **Step 1: Re-run preflight and capture old-table invariants**

Record migration state, table list, row counts, stable table hashes, permission grants, optional capabilities, and `TALENTHUB_AI_VISIBLE_PERCENT` without exposing secrets.

- [ ] **Step 2: Create and restore-test a fresh primary backup**

Dump `talenthub_local` to `C:\Users\CHI NGUYEN\AppData\Local\Temp\TalentHubBackups\...`, record exact byte length and SHA-256, restore it into a validated disposable schema, compare counts/hashes, then remove only that schema.

- [ ] **Step 3: Apply only the pending canonical repair migration**

Run `bin/migrate.php migrate --step=1` or the runner's supported single-step command after confirming exactly one pending version `20260821000210`. Abort if any other version is pending.

- [ ] **Step 4: Verify primary state**

Expected: 18 applied, 0 pending, validation OK; five Phase 3 tables retain their data; old-table counts/hashes are unchanged except intentional metadata/schema changes; projects capability is `available`; permissions remain role-scoped.

- [ ] **Step 5: Replace unsafe rollback text with forward recovery**

Document stop-write, clone-restore, diagnostic validation, and forward corrective migration. Do not instruct an automatic destructive restore over the primary schema.

### Task 8: Run the full completion gate

**Files:**
- Modify: `docs/superpowers/readiness/2026-08-22-phase-3-profile-evidence-sharing-review-report.md`

**Interfaces:**
- Produces: final `PASS` or `FAIL` for Phase 3.

- [ ] **Step 1: Run focused PHP tests**

Run all Phase 2/3 migration, readiness, Talent Passport, profile, certificate, privacy, sharing, render, API, and cross-role tests.

- [ ] **Step 2: Run the existing regression suites**

Run assessment, recommendation, activity, QR, permission, AI/shadow, scorer, and all eight reported Node suites.

- [ ] **Step 3: Run static verification**

Lint every changed PHP file, run `git diff --check`, confirm protected learner migration hashes, scan tracked files/history for secrets, and verify `.env`, `.claude/`, `.qwen/` were not edited by this remediation.

- [ ] **Step 4: Run final runtime verification**

Run connection check, migration status/validate, Phase 3 schema readiness, optional capability status, permission grants, new-table counts, and disposable-schema cleanup query.

- [ ] **Step 5: Write the final review report**

Include files changed, RED/GREEN evidence, exact test counts, backup path/size/SHA-256, migration state, database invariants, authorization/CSRF/ownership evidence, four-role boundary result, AI visibility, remaining scope warnings, and an evidence-based PASS/FAIL. Do not commit or start Phase 4.
