# Learner AI model-visible release checklist

## Current decision

**LOCAL_ACCEPTANCE_EVIDENCE_READY — RELEASE_NOT_APPROVED.**

On 2026-08-29, the local development database `talenthub` produced two real Gemini model recommendations for the consented demo heroes under enforced strict mode. This is local evidence only. It is **not** staging evidence, production approval, a deployment authorization, or permission to increase a production rollout percentage.

## Strict-runtime local acceptance — 2026-08-29

- [x] Local backup completed before AI writes: `.tmp/db-backups/task9-local-20260829T034952Z/talenthub.sql`, SHA-256 `7d3095a736f6a880e3bb6f4d1b2a09711ed6585e96a08f9c9db6e71600bd6b90`.
- [x] `bin/migrate.php validate` and `bin/learner-migrate.php validate` pass; learner AI migrations `002` through `014` and bridge migration `20260827001200` are applied locally.
- [x] Strict local runtime returns `ready_model` only after a validated provider result. Provider failure returns `provider_unavailable`, persists the safe failure, and queues recovery; it never exposes rule items under an AI label.
- [x] Two consented demo heroes have fresh `gemini-3.7-flash` results with prompt version `learner-recommendation-1.0.0`; `bin/verify-demo-ai.php` reports no violation.
- [x] Fresh focused suite: 26 PHP tests and 3 UI contract tests pass, including strict-mode, no-silent-fallback, provider schema, queue-worker, snapshot/database, group, school, enterprise, scope-policy, and end-to-end contracts.
- [ ] Manual browser acceptance for learner, school, and enterprise roles, with screenshots, has not yet been performed. The local browser check has no authenticated role session, so it is not acceptance evidence.
- [ ] Queue is not clean: 16 pre-existing local refresh jobs remain pending outside the two demo heroes. A scoped worker now processes only an explicit learner and does not consume the global outbox, but queue reconciliation remains a release gate.
- [ ] Staging backup, staging migration rehearsal, staging three-role acceptance, production approval, deploy, and push remain outside this local evidence.

The authoritative local run details, sample hashes, known gaps, monitoring rules, and rollback procedure are in [2026-08-28-ai-strict-runtime-evidence.md](2026-08-28-ai-strict-runtime-evidence.md).

## Historical runtime reconciliation — 2026-08-23

Phase 11 is `APPROVED_PHASE_11`, but that approval covers the production-ready four-role core, not learner-visible model output. The server-side runtime is currently configured for provider `9router_gemini` and model `ag/gemini-3.7-flash-high`; AI and Shadow execution are enabled, the Shadow release gate is not approved, and `TALENTHUB_AI_VISIBLE_PERCENT=0`. Configuration proves availability only—it does not authorize provider calls, evaluation samples, or model visibility. The Rule Engine remains learner-visible and authoritative.

Phase 12 is governed by:

- `docs/superpowers/specs/2026-08-23-phase-12-ai-shadow-evaluation-pilot-design.md`
- `docs/superpowers/specs/2026-08-23-phase-12-ai-shadow-evaluation-protocol.md`
- `docs/superpowers/database-change-requests/2026-08-23-phase-12-ai-evaluation-telemetry.md` (storage-only primary apply authorized and completed; no provider or pilot authorization)

## Required evidence before any value above zero

- [ ] Shadow-evaluation gate records 100% schema validity and evidence coverage, with 0% unsupported claims and unsafe output.
- [ ] Product owner records approved p50/p95 latency and cost-per-run thresholds in the evaluation gate.
- [ ] Provider failure simulation proves `provider_unavailable`, `pending`, or explicitly labelled `stale_model` is returned for every tested strict-mode failure; no rule result may be presented as AI output.
- [ ] Consent revoke test proves both shadow and visible model calls are disabled.
- [ ] Disposable two-learner isolation verification passes.
- [ ] Security/bias review is approved with a sample size large enough for each reported group.
- [ ] Explicit model-visible approval records a deterministic pilot percentage above zero.

The feature flags are `TALENTHUB_AI_ENABLED`, `TALENTHUB_AI_SHADOW`, `TALENTHUB_AI_SHADOW_GATE_APPROVED`, `TALENTHUB_AI_VISIBLE_PERCENT`, and `TALENTHUB_AI_PROVIDER`. Strict mode is enforced in staging/production; only local/test may opt out with `TALENTHUB_AI_STRICT_MODE_OVERRIDE=false` for a test or mock fixture. Never use an environment or database edit to bypass this checklist.

## Verification recorded 2026-08-17

The following evidence was run only against the verified disposable Laragon MySQL schema
`talenthub_ai_backup_verify_004_20260816`; `talenthub_local` was not queried or changed.

- [x] Insert-only pilot seed verification passed twice (`learner_ai_pilot_seed_test.php`): 61 deterministic synthetic rows, no update/delete path, and no change to rows outside the documented reserved UUID prefix.
- [x] Disposable two-learner isolation verification passed (`learner_ai_end_to_end_mysql_test.php`): source data, recommendation runs, evidence, reads, and feedback stay scoped to the authenticated learner.
- [x] Provider-failure simulation passed (`learner_ai_end_to_end_mysql_test.php`): the fake unavailable provider returns an evidence-backed rule fallback; no network provider call or real API credential was used.
- [x] Metadata compatibility verification passed (`learner_ai_mysql_metadata_test.php`) on MySQL 8.4 through Laragon.
- [x] Protected-path and syntax verification passed: no changed or untracked files under Teacher, School, Enterprise, `src`, or `api`; PHP lint passed for their 57 files.

The existing foundation MySQL test was intentionally not run: it contains cleanup `DELETE` statements and therefore conflicts with the no-delete data-safety boundary. The available Teacher/School dynamic smoke scripts are either mutating or require separate demo accounts not present in this disposable fixture; none was used to create those accounts. This is not an authorization to remove or create shared-role data. The unmet governance, consent-revocation, shadow-quality, dynamic cross-role, and explicit product-approval gates above keep the current decision **NOT READY** and the visible model percentage at `0`.

## Assessment platform verification 2026-08-18 (Historical baseline)

- [x] Runtime connection through `config/database.php` reaches Laragon MySQL Community Server `8.4.3` (`driver=mysql`, `connection=OK`). The read-only connection gate reported zero pending migrations and no drift.
- [x] Focused assessment and RBAC suites pass: permissions, recommendation API, four deterministic scorer contracts, catalog/lifecycle persistence, assessment API, render contract, and readiness checks.
- [x] Assessment UI and shared API client suites pass: 13 UI controller tests and 12 API client tests with zero failures. Recommendation UI regression passes 6/6.
- [x] The non-MySQL learner AI regression suite passes all 18 PHP tests and the recommendation UI suite.
- [x] Changed PHP files pass syntax lint and the repository diff passes `git diff --check`.
- [x] The four-assessment catalog/runner/result flow is API-driven; browser scoring and assessment `localStorage` state remain disabled, and untrusted response strings use safe DOM text rendering.
- [x] No shared migration or question-bank seed was executed during this historical verification.
- [x] The model-visible percentage remains fixed at `0`; no Gemini, 9Router, or other provider call was made.
- [x] Publishing the 12 age-banded assessment catalogs: completed on 2026-08-20 per Section below.

## Assessment Catalog Primary Seed Verification (Tasks 13–15, 2026-08-20)

Assessment canonical schema (`20260818000100_create_learner_assessment_schema`) and all 12 age-banded catalogs (366 questions) have been safely migrated and seeded into the primary runtime database `talenthub_local` following full review gate and DCR authorization.

- **Execution timestamp:** `2026-08-20T13:15:00Z` (UTC) / 2026-08-20 20:15:00+07:00
- **Database:** `talenthub_local` on Laragon MySQL Community Server `8.4.3` (`127.0.0.1:3306`), `utf8mb4_unicode_ci`, session timezone `+00:00`.
- **Pre-Task 13 Database Backup:** Captured and preserved at `C:\Users\CHINGU~1\AppData\Local\Temp\talenthub_local_pre_task13_20260820_131414.sql`.
- **DCR Reference:** `docs/superpowers/dcr/2026-08-18-learner-assessment-catalog-seed-dcr.md` (v1.1.0, approved for Option A 30/32/28/32 by Product Owner NCnguyenn and Codex Lead Reviewer).
- **Master Plan Reference:** `docs/superpowers/plans/2026-08-17-learner-assessment-catalog-content.md` (Tasks 13–15).
- **Prerequisite Migrations:** 13 applied, 0 pending, 0 drift (`bin/migrate.php validate` clean).
  - `20260818000100_create_learner_assessment_schema` applied successfully via `MigrationRunner`.
- **Seeder Execution (Task 13):**
  - First run: `inserted=12, no_op=0, failed=0, total=12` (all 12 catalogs committed).
  - Second run (Idempotency): `inserted=0, no_op=12, failed=0, total=12` (`reason=idempotent_match`). No row update, delete, or duplicate.
- **Verified Primary Database Row Counts (`talenthub_local`):**
  - `talent_tests`: **12** rows (all `status = 'published'`)
  - `test_questions`: **366** rows (all `status = 'published'`, 366 unique canonical UUIDs, 366 unique stable codes)
  - `learner_assessment_versions`: **12** rows (all `version = '1.0.0'`, `status = 'published'`, `publishedAt` populated)
  - `learner_assessment_question_versions`: **366** rows (contiguous positions 1..N per version)
  - `test_attempts`: **0** rows (clean, unpolluted)
  - `learner_assessment_attempt_metadata`: **0** rows
  - `learner_assessment_answers`: **0** rows
  - `test_results`: **0** rows
- **Canonical Schema Hash Verification:**
  - All 12 `learner_assessment_versions.schemaHash` values in `talenthub_local` match the canonical SHA-256 hash computed from disk files:
    1. `holland_middle` (30 questions): `8425c2f8f9d4ffe4850831b372a1e3e6ecc8da58b2342f602f6cbcf09719528c`
    2. `holland_high` (30 questions): `6ae9fe0b9404cdf70c6b3d785b384247ddf6861ad338f960663538aa4bb2f998`
    3. `holland_college` (30 questions): `1897bcc607459fa92c0cbe7ce44d701ee3d0ed251a19e99aa0ba7a42f4ef7389`
    4. `mbti_middle` (32 questions): `35a05b672397725097da7937d38d515c12b9074fd2a57db3167c0e6c5fbe7f12`
    5. `mbti_high` (32 questions): `2f53e999afa732f0cae590bb69b515c81d8eac079ec1d689115712d1fb98bce6`
    6. `mbti_college` (32 questions): `6d4948b80b48c98caf5a182cbb16df76af5ba1e1458cfdbf50cd31a902c16d82`
    7. `disc_middle` (28 questions): `d3e366bc18e73ffcad54b5f307798626e3f95019b2179a633055fa814d9242c5`
    8. `disc_high` (28 questions): `0d0fce9d20d1dac9939ff4e66f8191c6faddb8947b74ffa81efc0bec1d935292`
    9. `disc_college` (28 questions): `b39d01aa0b69a6db33a4f9799417215057d63b0dee8549e3400496632955eb67`
    10. `multiple_intelligence_middle` (32 questions): `07a8af65d16454386b2f7b9232550f9ea7697f07bd527b2f56f38f61564f9149`
    11. `multiple_intelligence_high` (32 questions): `52591b809ef0dd03ceef261d7a0b2a0a64689d0a818f5a66c055107762925591`
    12. `multiple_intelligence_college` (32 questions): `16a672944020511848e1b486bd27bdb5712ee162cf84838493f414cf0b61f210`
- **Validation and Test Suites Passed:**
  - `tests/learner_assessment_catalog_seed_test.php` (4,231 assertions passed on live `talenthub_local`).
  - `tests/learner_catalog_content_validator.php` (115 assertions passed).
  - `tests/learner_catalog_cross_consistency_test.php` (8,855 assertions passed).
  - `tests/learner_catalog_scorer_integration_test.php` (644 assertions passed).
  - `tests/learner_catalog_seeder_contract_test.php` (7 assertions passed).
  - `tests/learner_assessment_published_immutability_test.php` (13 assertions passed).
  - 4 Framework Scorers & Scorer Contract: Holland, MBTI, DISC, Multiple Intelligence (all passed).
  - Assessment Catalog & Service integration tests: `learner_assessment_catalog_test.php`, `learner_assessment_api_test.php` (all passed).

## Remaining AI Rollout Blockers (Model-Visible Gate)

The assessment catalog seeding establishes the immutable deterministic Rule/Scoring engine baseline. However, AI model-visible features remain **STRICTLY BLOCKED** (`TALENTHUB_AI_VISIBLE_PERCENT=0`):

1. **Model-Visible Percentage:** Fixed at `0`. Deterministic Rule Engine is authoritative.
2. **Provider execution authorization:** 9Router is configured server-side, but no Phase 12 consented sample, primary evaluation write, or learner-visible provider execution is authorized merely by configuration.
3. **Required Sign-Offs & Evidence Before Model-Visible Rollout:**
   - [ ] Formal model-visible pilot percentage sign-off by Product Owner.
   - [ ] Shadow-evaluation gate report (100% schema validity, 0% unsupported/hallucinated claims).
   - [ ] Provider failure & timeout simulation evidence across peak loads proving reliable rule fallback.
   - [ ] Cost-per-run and p50/p95 latency threshold approval.
   - [ ] Independent AI security, privacy, and bias sign-off.
