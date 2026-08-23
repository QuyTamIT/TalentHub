# Database Change Request (DCR) — Phase 9 Badges, Levels, and Personal Statistics

- Date: 2026-08-23
- Author: Antigravity / Anti
- Workspace: `D:\TalentHub`
- Target Branch: `feature/student`
- Baseline Commit: `8875310dbb919f04a5769c7c65f60b98bd16e399`
- Target Database: `talenthub_local` (MySQL 8.4.3)
- Status: `APPROVED_AND_APPLIED`

## Codex approval and execution evidence

- Approved/applied: 2026-08-23 after exact disposable rehearsal passed.
- Recovery backup: `C:\Users\CHI NGUYEN\AppData\Local\Temp\TalentHubBackups\talenthub_local_pre_phase9_20260823_170658.sql`
- Backup size/SHA-256: `816347` bytes / `0af69b372367284cc671243612ec5bba6a03e39a6b9ef2b32e65b613ddda4789`
- Primary result: 61 base tables, 29 applied migrations, 0 pending.
- Catalog/result: 5 badges, 5 active v1 rules, 54 justified learner awards, 54 idempotency-keyed notifications.
- Replay result: 0 new awards; 54 distinct `(studentId,badgeId)` pairs; 0 orphan awards.

---

## 1. Change Summary

Phase 9 introduces deterministic, fact-based badges, versioned level calculation, and personal statistics for students.

### Planned Schema Additions

1. `badges`: System badge catalog definitions.
2. `badge_rule_definitions`: Versioned threshold criteria for system badges.
3. `student_badges`: Materialized learner award records with unique `(studentId, badgeId)`.

### System Catalog and Rules (5 Badges / 5 Rules v1)

- `first_experience`: confirmed experience hours >= 1
- `experience_10h`: confirmed experience hours >= 10
- `active_participant`: attended activity count >= 3
- `assessment_explorer`: submitted distinct assessment types count >= 2
- `teacher_recognition`: published teacher evaluations count >= 1

### Learner Awards

- **Zero** learner award rows will be inserted by the migration.
- Existing confirmed facts will be backfilled via the operator CLI (`bin/run-badge-awards.php`) only after Codex approval and primary migration apply.

---

## 2. Exact DDL Specifications

### Table 1: `badges`
```sql
CREATE TABLE badges (
    id CHAR(36) NOT NULL,
    code VARCHAR(64) NOT NULL,
    name VARCHAR(255) NOT NULL,
    category VARCHAR(64) NOT NULL,
    description TEXT NOT NULL,
    iconUrl VARCHAR(500) NULL,
    level INT NOT NULL DEFAULT 1,
    status VARCHAR(32) NOT NULL DEFAULT 'active',
    createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_badges_code (code),
    CONSTRAINT chk_badges_status CHECK (status IN ('active', 'inactive', 'deprecated')),
    CONSTRAINT chk_badges_level CHECK (level >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table 2: `badge_rule_definitions`
```sql
CREATE TABLE badge_rule_definitions (
    id CHAR(36) NOT NULL,
    badgeId CHAR(36) NOT NULL,
    ruleType VARCHAR(64) NOT NULL DEFAULT 'threshold',
    thresholdCriteria JSON NOT NULL,
    version INT NOT NULL DEFAULT 1,
    isActive TINYINT(1) NOT NULL DEFAULT 1,
    createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_badge_rules_badge_version (badgeId, version),
    KEY idx_badge_rules_active (isActive, badgeId, version),
    CONSTRAINT fk_badge_rule_definitions_badge
        FOREIGN KEY (badgeId) REFERENCES badges (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_badge_rules_type CHECK (ruleType IN ('threshold')),
    CONSTRAINT chk_badge_rules_version CHECK (version >= 1),
    CONSTRAINT chk_badge_rules_is_active CHECK (isActive IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table 3: `student_badges`
```sql
CREATE TABLE student_badges (
    id CHAR(36) NOT NULL,
    studentId CHAR(36) NOT NULL,
    badgeId CHAR(36) NOT NULL,
    ruleDefinitionId CHAR(36) NOT NULL,
    awardedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    awardedBy VARCHAR(64) NOT NULL DEFAULT 'system',
    awardContext JSON NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_student_badges_award (studentId, badgeId),
    KEY idx_student_badges_badge (badgeId),
    KEY idx_student_badges_rule (ruleDefinitionId),
    KEY idx_student_badges_student_awarded (studentId, awardedAt),
    CONSTRAINT fk_student_badges_student
        FOREIGN KEY (studentId) REFERENCES student_profiles (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_student_badges_badge
        FOREIGN KEY (badgeId) REFERENCES badges (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_student_badges_rule
        FOREIGN KEY (ruleDefinitionId) REFERENCES badge_rule_definitions (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_student_badges_awarded_by CHECK (awardedBy IN ('system', 'teacher', 'school_admin'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 3. Preflight and Safety Gates

1. **MySQL Version**: MySQL 8.0+ required (actual: 8.4.3).
2. **Session Timezone**: UTC (`+00:00`) required.
3. **Parent Tables**: `student_profiles` must exist and be accessible.
4. **Collision Preflight**: If any of `badges`, `badge_rule_definitions`, or `student_badges` already exists, exact column types, collations, indexes, foreign keys, and check constraints must match 100%, otherwise migration aborts with a descriptive exception.
5. **No Data Loss**: `down()` is non-reversible (fail-closed) to protect learner award records.

---

## 4. Disposable Rehearsal Protocol

Before applying to primary database:
1. Take fresh dump of `talenthub_local`.
2. Compute and pin SHA-256 hash.
3. Create disposable schema `talenthub_phase9_rehearsal_YYYYMMDDHHMMSS`.
4. Restore dump into disposable schema.
5. Run migration `20260821000700_create_badges_and_award_rules.php`.
6. Verify idempotency by running migration again (`no changes`).
7. Run concurrency, rollback, and backfill tests on disposable schema.
8. Clean up disposable schema and grants.

---

## 5. Post-Approval Primary Apply & Backfill Commands (For Codex)

```powershell
# 1. Backup primary database
$backupFile = "C:\Users\CHI NGUYEN\AppData\Local\Temp\TalentHubBackups\talenthub_local_pre_phase9_" + (Get-Date -Format "yyyyMMdd_HHmmss") + ".sql"
& "D:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysqldump.exe" -u talenthub_app talenthub_local --single-transaction --routines --triggers --events > $backupFile

# 2. Apply Phase 9 migration
& "D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" bin\migrate.php migrate --step=1

# 3. Validate status
& "D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" bin\migrate.php status

# 4. Dry-run operator backfill
& "D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" bin\run-badge-awards.php --dry-run

# 5. Execute operator backfill (after approval)
$env:TALENTHUB_PHASE9_PRIMARY_APPLY_APPROVED = "1"
& "D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" bin\run-badge-awards.php --apply --all
$env:TALENTHUB_PHASE9_PRIMARY_APPLY_APPROVED = "0"
```
