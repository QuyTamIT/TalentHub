# Phase 8 — Observability and staged rollout review fixes

Status: APPROVED FOR PHASE 8 IMPLEMENTATION / LIVE 100% ROLLOUT BLOCKED

Implemented and verified:

- `StagedRolloutGate` now fails closed for 100% unless the value is the strict integer `100`, approval reference matches the safe change-id format, and unified policy, last-known-good, queue monitoring, enabled, shadow approval, staged history and all existing gates are true.
- `AiMetricsCollector` keeps queue/freshness/quota/circuit gauges statefully across events, counts queue lifecycle events, bounds telemetry and retains only safe categories. Providers and refresh worker use an optional/shared collector without exposing secrets or learner identifiers.
- Stale and fallback rates use only typed observations as denominators, so click/queue events cannot dilute outage alerts. The availability policy and recommendation selector require explicit rollout evidence before any 100% model visibility; lower stages remain unchanged.
- Canonical staged percentages (10/25/50/100) now require the staged gate and evidence; non-canonical percentages retain prior behavior. Shared telemetry has a safe structured `error_log` sink, queue depth/age hooks, model fallback events, recommendation feedback events, and real CTA click events.
- Recommendation activity/catalog/opportunity CTA links now emit a same-origin, CSRF-protected `keepalive` request. The request is not awaited and browser navigation is never cancelled, so an expired CSRF token, session expiry, network error, or telemetry outage cannot block the learner's destination.
- The click endpoint accepts only `itemId`, optional `catalogId`, and an allow-listed action; validates learner ownership and catalog evidence; and emits only `recommendation_click=true` plus the safe action category. Feedback/evidence expansion remains separate and is not counted as a click.
- Added `bin/rotate-ai-key.ps1`, which delegates by logical secret name to the deployment secret manager and never accepts or prints key material.
- Added `bin/learner-migrate.php` using `LearnerForwardMigrationRunner`; status/validate/apply are executable and rollback fails closed because learner migrations are forward-only. Runbook commands now use this path.
- Added executable review-fix and disposable SQLite migration rehearsal tests.

Focused verification (PHP 8.3):

- `learner_ai_phase8_review_fixes_test.php` — OK
- `learner_ai_phase8_observability_test.php` — OK
- `learner_ai_phase8_readiness_contract_test.php` — OK
- `learner_ai_phase8_migration_rehearsal_test.php` — OK
- `learner_forward_migration_test.php` — OK
- `learner_ai_recommendation_click_test.php` — OK
- `learner_ai_recommendation_ui_test.js` — 14/14 pass
- Changed PHP files lint clean; `git diff --check` clean (line-ending warnings only).

Environment-gated items: live Gemini test-key/network integration and production migration/rollback require deployment credentials and approval; no key was added and 100% visibility remains disabled.

Independent final review: `APPROVED_FOR_PHASE8_IMPLEMENTATION`. Live 100% remains blocked until a real Gemini/network integration run, production-like MySQL migration and worker rehearsal, telemetry-backend retention/alert verification, successful staged measurements, and valid Product Owner approval are attached. The former recommendation-card click-emitter follow-up is now implemented; feedback is not misclassified as a click.
