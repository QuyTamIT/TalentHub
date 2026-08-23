# Database Change Request — Phase 5 Learner Check-in Experience Policy

Date: 2026-08-22
Status: APPROVED AND APPLIED — PHASE 5 ONLY

## Requested change

Apply only `Database/migrations/20260821000400_create_activity_experience_policies.php` to `talenthub_local`.

The migration adds one empty table, `activity_experience_policies`, with an activity PK/FK, confirmed-hours value constrained to 0–24, and UTC microsecond timestamps. It does not alter, backfill, delete, or seed existing domain data. It is forward-only because future check-in history depends on the policy anchor.

## Compatibility and safety

- Canonical QR/check-in/experience tables and constraints are reused, not duplicated.
- Existing migration files through `20260821000300` and learner migrations `001`–`004` remain unchanged.
- `uq_checkins_registration` and `uq_experience_logs_checkin` are mandatory preflight replay barriers.
- A semantically equivalent table under another name fails closed. An unregistered pre-existing canonical table is checked for its four canonical columns, primary key, engine/collation, foreign key, and hours constraint before acceptance.
- The migration requires the MySQL session time zone to be `+00:00`.
- No Phase 6+ table or permission is introduced.

## Authorized execution gate

The user authorized primary apply only after: full tests/lint, fresh backup with SHA-256, disposable restore, first apply and second no-op apply, MySQL behavioral/concurrency tests, and unchanged-data hashes. If any gate fails, primary remains pending.

Rollback is restore-from-backup because the migration is intentionally irreversible. No automatic table drop is authorized.

## Execution record

- Fresh primary backup: `C:\Users\CHI NGUYEN\AppData\Local\Temp\TalentHubBackups\talenthub_local_pre_phase5_20260822_173500.sql`
- Size: `802774` bytes
- SHA-256: `561E6DE4107CC76313A302D711D038AE6F7338C41E0AD9BC269D5CDC088B2020`
- Disposable rehearsal and concurrency gates: PASS.
- Primary apply: migration `20260821000400` only; PASS.
- Post-apply: 52 tables, 23 applied, 0 pending, validation and exact metadata preflight PASS.
- The applied migration checksum is frozen. A separate read-only post-apply contract verifies timestamp defaults/update behavior and both replay-barrier indexes. Any future strengthening of the exceptional recovery branch requires a separately authorized successor migration or external validator.
- No seed or primary mutation test was run after apply.
