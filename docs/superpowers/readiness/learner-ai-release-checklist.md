# Learner AI model-visible release checklist

## Current decision

**NOT READY — visible model percentage is fixed at `0`.** Rule baseline remains the only learner-visible engine.

## Required evidence before any value above zero

- [ ] Shadow-evaluation gate records 100% schema validity and evidence coverage, with 0% unsupported claims and unsafe output.
- [ ] Product owner records approved p50/p95 latency and cost-per-run thresholds in the evaluation gate.
- [ ] Provider failure simulation proves a completed rule result is returned for every tested failure.
- [ ] Consent revoke test proves both shadow and visible model calls are disabled.
- [ ] Disposable two-learner isolation verification passes.
- [ ] Security/bias review is approved with a sample size large enough for each reported group.
- [ ] Explicit model-visible approval records a deterministic pilot percentage above zero.

The feature flags are `TALENTHUB_AI_ENABLED`, `TALENTHUB_AI_SHADOW`, `TALENTHUB_AI_SHADOW_GATE_APPROVED`, `TALENTHUB_AI_VISIBLE_PERCENT`, and `TALENTHUB_AI_PROVIDER`. Turning any flag off requires no database change. Never use an environment or database edit to bypass this checklist.

## Verification recorded 2026-08-17

The following evidence was run only against the verified disposable Laragon MySQL schema
`talenthub_ai_backup_verify_004_20260816`; `talenthub_local` was not queried or changed.

- [x] Insert-only pilot seed verification passed twice (`learner_ai_pilot_seed_test.php`): 61 deterministic synthetic rows, no update/delete path, and no change to rows outside the documented reserved UUID prefix.
- [x] Disposable two-learner isolation verification passed (`learner_ai_end_to_end_mysql_test.php`): source data, recommendation runs, evidence, reads, and feedback stay scoped to the authenticated learner.
- [x] Provider-failure simulation passed (`learner_ai_end_to_end_mysql_test.php`): the fake unavailable provider returns an evidence-backed rule fallback; no network provider call or real API credential was used.
- [x] Metadata compatibility verification passed (`learner_ai_mysql_metadata_test.php`) on MySQL 8.4 through Laragon.
- [x] Protected-path and syntax verification passed: no changed or untracked files under Teacher, School, Enterprise, `src`, or `api`; PHP lint passed for their 57 files.

The existing foundation MySQL test was intentionally not run: it contains cleanup `DELETE` statements and therefore conflicts with the no-delete data-safety boundary. The available Teacher/School dynamic smoke scripts are either mutating or require separate demo accounts not present in this disposable fixture; none was used to create those accounts. This is not an authorization to remove or create shared-role data. The unmet governance, consent-revocation, shadow-quality, dynamic cross-role, and explicit product-approval gates above keep the current decision **NOT READY** and the visible model percentage at `0`.

## Assessment platform verification 2026-08-18

- [x] Runtime connection through `config/database.php` reaches Laragon MySQL Community Server `8.4.3` (`driver=mysql`, `connection=OK`). The read-only connection gate reported zero pending migrations and no drift.
- [x] Focused assessment and RBAC suites pass: permissions, recommendation API, four deterministic scorer contracts, catalog/lifecycle persistence, assessment API, render contract, and readiness checks.
- [x] Assessment UI and shared API client suites pass: 13 UI controller tests and 12 API client tests with zero failures. Recommendation UI regression passes 6/6.
- [x] The non-MySQL learner AI regression suite passes all 18 PHP tests and the recommendation UI suite.
- [x] Changed PHP files pass syntax lint and the repository diff passes `git diff --check`.
- [x] The four-assessment catalog/runner/result flow is API-driven; browser scoring and assessment `localStorage` state remain disabled, and untrusted response strings use safe DOM text rendering.
- [x] No shared migration or question-bank seed was executed during this verification.
- [x] The model-visible percentage remains fixed at `0`; no Gemini, 9Router, or other provider call was made.
- [ ] Publishing the 12 age-banded assessment catalogs still requires the separate reviewed catalog plan and Database Change Request.
