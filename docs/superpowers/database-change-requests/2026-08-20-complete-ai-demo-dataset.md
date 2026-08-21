# Database Change Request: Complete Learner AI Demo Dataset

**Status:** EXECUTED — APPROVED LOCAL DEMO DATA

## Approval and scope

- Approval: user-approved design and implementation plan in `docs/superpowers/specs/2026-08-20-complete-ai-demo-dataset-design.md` and `docs/superpowers/plans/2026-08-20-complete-ai-demo-dataset.md`.
- Target: `talenthub_local` only.
- Environment: `local`.
- Execution completed at: `2026-08-21T10:40:31.4696205Z`.
- Branch: `feature/student`; no push or merge to `develop`.
- Schema change: none. This execution populated deterministic demo rows and recommendation history only.
- Reserved ownership: `21000000-` for THPT AI-dependent rows and `22000000-` for synthetic Đại học FPT rows.

## Backup evidence

- Backup: `C:\Users\CHI NGUYEN\AppData\Local\Temp\TalentHubBackups\talenthub_local_pre_complete_ai_demo_20260821_172328.sql`
- Size: 405,115 bytes.
- Validated at: `2026-08-21T10:23:28.9554950Z`.
- The backup is outside the repository and was validated before the first demo-data mutation.

## Redacted before/after counts

| Table or role | Before | After |
|---|---:|---:|
| schools | 2 | 3 |
| users / school | 2 | 3 |
| users / teacher | 7 | 11 |
| users / student | 12 | 20 |
| users / enterprise | 1 | 1 |
| teacher_profiles | 7 | 11 |
| student_profiles | 12 | 20 |
| student_skills | 0 | 77 |
| test_attempts | 0 | 42 |
| test_results | 0 | 42 |
| activities | 8 | 26 |
| activity_registrations | 0 | 40 |
| activity_qr_sessions | 0 | 8 |
| checkins | 0 | 20 |
| experience_logs | 0 | 20 |
| assessments | 0 | 20 |
| learner_ai_consent_events | 0 | 76 |
| learner_recommendation_runs | 0 | 4 |

The verifier-owned dataset reports exactly 2 organizations, 31 demo users, 10 demo teacher profiles, 19 learners, 18 activities, 40 registrations, 20 check-ins, 20 experiences, 20 published evaluations, and 76 consent events. Global counts are higher where pre-existing local fixtures are preserved.

## Seed and catalog evidence

- First `php bin/seed.php --demo-ai`: exit `0`.
- First `php bin/verify-demo-ai.php`: exit `0`, `ok=true`.
- Second seed: exit `0`.
- Second verifier: exit `0` with identical counts and hero states.
- Disposable acceptance additionally compared SHA-256 snapshots of every owned row after seed one and seed two: zero content drift.
- High-school mapping: 11 learners, 24 submitted attempts, 24 results, 4 canonical `*_high` catalog codes.
- College mapping: 8 learners, 18 submitted attempts, 18 results, 4 canonical `*_college` catalog codes.
- Canonical catalog rows remained byte-identical; no catalog tables were seeded or updated by the complete demo seeder.

## Role and QR isolation

- Exact hashes for roles, permissions, role-permissions, enterprises, enterprise members, canonical assessment catalogs, and protected test accounts were unchanged in the disposable acceptance gate.
- Rows outside `21000000-` and `22000000-` remained unchanged, except approved shared catalog reuse and existing demo-school metadata allowed by the plan.
- Both THPT and FPT expose one active, two expired, and one revoked hashed QR session through `TeacherQrSessionService::pageData()`.
- No raw QR token is stored; verifier QR hash and raw-column checks pass.

## Live 9Router evidence

- Local endpoint availability: true on port `20128`.
- Provider: `9router_gemini`; model diagnostic: `ag_gemini-3.7-flash-high`; timeout: 30 seconds.
- THPT hero: `quality_state=ready`, `visible_engine=rule`, `visible_item_count=3`, `shadow_engine=model`, `shadow_valid=true`, no violation codes.
- FPT hero: `quality_state=ready`, `visible_engine=rule`, `visible_item_count=3`, `shadow_engine=model`, `shadow_valid=true`, no violation codes.
- Persisted separation: each hero has exactly one completed Rule run and one completed Model shadow run.
- Persisted items: 6 learner-visible Rule items and 7 Model shadow items across the two heroes; every persisted item has evidence.
- A repeated live runner invocation reused the completed runs and returned the same redacted success state.
- Final read-only verifier: both heroes report `engine_type=rule`; shadow model runs are excluded from `latestForStudent()`.

## Security and exclusions

- Configured key tracked-file match count: `0`.
- Configured key Git-history match count: `0`.
- `.env` remains ignored and was not staged; no key value, provider payload, answer, comment, header, or raw QR token was printed or committed.
- QR Phase 2 learner camera scanning is explicitly out of scope and is not claimed as implemented. This delivery provides teacher QR session management plus coherent historical check-in evidence.

## Verification evidence

All commands below completed with exit `0`:

- `php bin/migrate.php validate`.
- PHP syntax checks for all 111 PHP files under `Database/seeds/Demo`, `bin`, and `tests`.
- `php tests/complete_ai_demo_dataset_contract_test.php`.
- `php tests/complete_ai_demo_seed_mysql_test.php` on a disposable guarded schema; its `finally` cleanup confirmed the schema was dropped.
- `php tests/complete_ai_demo_runner_test.php` on a disposable guarded schema; its `finally` cleanup confirmed the schema was dropped.
- `php tests/learner_ai_9router_shadow_integration_test.php`.
- `php tests/learner_ai_provider_test.php`, including empty-model-response fallback coverage.
- `php tests/learner_assessment_scorer_contract_test.php`.
- `php tests/learner_catalog_scorer_integration_test.php` (644 assertions).
- `php tests/qr_session_migration_contract_test.php`.
- `php tests/learner_recommendation_service_test.php`.
- `php tests/learner_recommendation_repository_test.php`.
- Seven learner Node UI/client tests listed in Task 7.
- `php bin/test-school-suite.php` against a separate disposable test database.
- `php bin/verify-demo-ai.php` after live persistence: `model_runs=2`, both heroes `ready`, both learner-visible engines `rule`.
- Repeated `php bin/run-demo-ai.php`: both heroes returned `status=ok`, visible Rule output, valid Model shadow output, and no violation codes.
- `git diff --check` and the final staged-diff check.

## Recovery plan

If recovery is required, do not delete owned rows ad hoc. Restore the validated SQL backup into a separate verification schema first, compare it with `talenthub_local`, and perform any coordinated local replacement only after explicit approval. Future corrections should remain forward-only and preserve unrelated role data.
