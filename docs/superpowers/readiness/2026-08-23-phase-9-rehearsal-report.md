# Phase 9 Disposable Rehearsal Report

- Date: 2026-08-23
- Result: `PASS`
- Workspace: `D:\TalentHub`
- Branch/HEAD: `feature/student` / `8875310dbb919f04a5769c7c65f60b98bd16e399`

## Pinned input

- Dump: `C:\Users\CHI NGUYEN\AppData\Local\Temp\TalentHubBackups\talenthub_local_pre_phase9_20260823_170658.sql`
- Size: 816347 bytes
- SHA-256: `0af69b372367284cc671243612ec5bba6a03e39a6b9ef2b32e65b613ddda4789`

## Evidence

- Disposable schema: `talenthub_phase9_rehearsal_20260823101642`
- Hashed baseline: 57 pre-existing business tables (registry excluded).
- Partial target-table preflight: rejected without registry mutation.
- Migration apply: only `20260821000700`; second run returned no changes.
- Migration preservation: all pre-existing table counts and SHA-256 row snapshots unchanged.
- Catalog before backfill: 5 canonical badges, 5 active v1 rules, 0 learner awards.
- Catalog conflict injection: rejected; transaction rolled back.
- Dry-run: 54 eligible awards.
- Apply: 54 awards and 54 matching notifications.
- Replay: 0 new awards.
- Eight-worker same-award concurrency: PASS.
- Saved-fact justification, award context, owner reads, and primary isolation: PASS.
- Cleanup: disposable schema absent and matching `mysql.db` grant count 0.

No checksum registry row was rewritten and `talenthub_local` was not mutated by rehearsal.
