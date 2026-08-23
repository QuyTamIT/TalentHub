# Phase 8 Disposable Rehearsal Report

- Date: 2026-08-23
- Workspace: `D:\TalentHub`
- Branch / baseline HEAD: `feature/student` / `bb167d97d2043f2ce71b78c1f62f9c9ae6d584d4`
- Decision: PASS

## Pinned Input

- Dump: `C:\Users\CHI NGUYEN\AppData\Local\Temp\TalentHubBackups\talenthub_local_pre_phase8_20260823_083516.sql`
- Size: 812,710 bytes
- SHA-256: `599d170f5559344b672f3baf10444e2639daa5604121537aae593565384e05e7`
- The orchestrator refuses an absent dump, an absent/malformed SHA, the primary schema, and schemas outside the Phase 8 disposable allow-list.

## Final Run

- Disposable schema: `talenthub_phase8_rehearsal_20260823021809`
- Migration validation: PASS
- `20260821000600_create_notifications_and_preferences`: applied
- `20260821000610_validate_phase8_notification_contracts`: applied
- Second migration run: no changes
- Integrity suite: 74 assertions
- Forward-validation static contract: 12 assertions
- Learner notification API/service contract: PASS
- Runtime endpoint authorization, CSRF, ownership, strict JSON and pagination: 27 assertions
- Domain producers and transaction rollback: PASS
- MySQL duplicate-event concurrency, FK failure, and rollback injection: 18 assertions
- Four-role contract: PASS
- Deliberately removed unread index: rejected by `00610`; restored exact contract: accepted

## Preservation and Cleanup

- Baseline table row counts and hashes were preserved except the explicitly approved Phase 8 schema/RBAC/registry delta.
- The permission delta is exactly one permission with four canonical role mappings during initial `00600` application.
- The disposable user's database grant was revoked.
- The disposable schema was dropped and no rehearsal database/grant remained.

## Primary Apply Gate

The rehearsal passed before the final primary action. A new pre-apply backup was then created at `C:\Users\CHI NGUYEN\AppData\Local\Temp\TalentHubBackups\talenthub_local_pre_phase8_00610_20260823_091851.sql` (816,185 bytes, SHA-256 `da6a559853a043235a7222365fac6480499c9958d66178677b297e94124978a7`). Only validation-only migration `00610` was applied afterward.
