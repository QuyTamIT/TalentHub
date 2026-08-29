# AI strict-runtime evidence — local acceptance

Date: 2026-08-29
Environment: local development database `talenthub` only
Decision: `LOCAL_ACCEPTANCE_EVIDENCE_READY`; `RELEASE_NOT_APPROVED`

This record is evidence from the local development environment. It does not authorize staging, production, a rollout increase, deployment, push, or a provider call for any learner outside the two consented demo heroes.

## Scope and backup

- Backup created before Task 9 writes: `.tmp/db-backups/task9-local-20260829T034952Z/talenthub.sql`
- SHA-256: `7d3095a736f6a880e3bb6f4d1b2a09711ed6585e96a08f9c9db6e71600bd6b90`
- The backup is local-only and is not staged for Git.
- No staging or production database was read or changed.

## Runtime and schema evidence

- `APP_ENV=local`; strict mode was enabled.
- Provider: `gemini`; model: `gemini-3.7-flash`.
- `bin/migrate.php validate`: PASS.
- `bin/learner-migrate.php validate`: PASS.
- Applied learner AI migrations: `002`–`014`.
- Applied bridge/data migrations through `20260827001200`.
- Provider health after the successful runs: `learner:gemini`, state `closed`, failure count `0`.

## Real consented demo evidence

`bin/verify-demo-ai.php` completed with no violation. Both heroes had sufficient real local data: 5 skills, 4 assessments, 2 attended activities, 6 published evaluations, and 21 opportunities.

| Hero | Snapshot SHA-256 | Model run | Completed UTC | Items |
| --- | --- | --- | --- | --- |
| high | `ddf44b1aa288403223c36c84dcdac4f144b9dbad2190e2304b5a93caf973a6da` | `f02685cc-47a0-4d14-9dd2-2fc88bbed4d3` | 2026-08-29 04:21:22 | 4 |
| college | `d7f3978dea7d67d6ab5005a7a8e3ea2dd099a1a4e1044191fe39292caf643319` | `7991c1f3-096b-40c2-a439-e2c682583513` | 2026-08-29 04:22:46 | 4 |

Both persisted results use provider `gemini`, model `gemini-3.7-flash`, and prompt version `learner-recommendation-1.0.0`. No prompt, API key, raw model response, email, or learner identifier is included in this document.

## Strict failure and recovery contract

- Provider/model failures return `provider_unavailable` with no rule-origin items.
- The failed run is marked with a safe error code and a recommendation refresh job is enqueued.
- A valid last-known-good model result may be shown only as `stale_model`; it is never rewritten or labelled as a rule result.
- Metrics for strict provider failure use `provider_error=provider_unavailable` and `fallback=false`.
- Gemini structured output is constrained to the supported recommendation item and action allow-lists. The output token budget is `8192` to avoid truncated JSON (`MAX_TOKENS`).

## Scoped recovery evidence

- The refresh-worker command accepts an opaque `--student-id` scope. In this mode it claims only that learner's refresh job and does not materialize or consume the global outbox.
- A MySQL no-op lease renewal in the same timestamp second is accepted only after ownership is rechecked. A genuinely lost lease still fails closed.
- A completed retry clears its previous safe error metadata. The regression suite covers both behaviours.
- Current local queue summary: 1 completed demo refresh job and 16 pending jobs outside the hero scope. Those 16 jobs were not processed in this rehearsal.

## Automated verification

At 2026-08-29 04:59 UTC, all 26 focused PHP tests and all 3 UI contract tests below passed after the strict-runtime fixes:

- `learner_ai_strict_mode_test.php`
- `learner_ai_no_silent_fallback_test.php`
- `learner_ai_database_schema_test.php`
- `learner_ai_database_sync_test.php`
- `learner_ai_end_to_end_test.php`
- `learner_ai_group_matching_test.php`
- `learner_ai_group_matching_api_test.php`
- `learner_ai_queue_worker_test.php`
- `enterprise_ai_matches_api_test.php`
- `enterprise_ai_gemini_matcher_test.php`
- `learner_ai_provider_test.php`
- `learner_ai_9router_shadow_integration_test.php`
- `learner_ai_group_matching_ui_test.ps1`
- `school_ai_insight_ui_test.ps1`
- `enterprise_ai_matches_ui_test.ps1`

## Required operational monitoring

Alert immediately when any of these is true:

- provider error rate rises above the approved error budget, or circuit state is `open`;
- pending refresh queue depth is non-zero beyond the freshness SLA, or a dead-letter job exists;
- a visible response has `engine_type=rule` while strict mode is enabled;
- a model result is stale past the approved freshness SLA;
- evidence validation, consent validation, or protected-trait rejection fails.

The initial 2026-08-29 local worker rehearsal materialized pre-existing jobs for other learners. The worker now supports a verified learner scope and did not process those jobs further. A separate queue-reconciliation plan is still required before queue cleanliness can be release evidence.

## Rollback procedure

1. Disable new model-generation requests via the environment configuration; do not alter historical model or rule records.
2. Return the canonical unavailable state (`provider_unavailable`, `pending`, or `stale_model`) according to the existing state machine.
3. Preserve a valid last-known-good model result only with its explicit `stale_model` label.
4. Do not substitute a rule result, change `engine_type`, or relabel a rule result as AI.
5. Investigate provider health/queue evidence, restore the verified configuration, then rerun strict verification before re-enabling requests.

## Release blockers

- Manual browser acceptance and screenshots for learner, school, and enterprise roles are missing. The local browser check had no authenticated role session and cannot replace this gate.
- The local global refresh queue contains 16 pending jobs outside the two demo heroes; it has not been reconciled.
- Scope audit reports only the three user-owned, untracked mockup images as out of scope. All modified AI source, verifier, worker, and policy paths are explicitly reviewed by the scope policy. The user-owned files remain untouched and un-staged.
- Staging backup/migration rehearsal and all production gates remain unperformed.

Therefore this document does **not** grant release approval. Only after every blocker is resolved with staging evidence may the decision be changed to `READY_FOR_STAGING_REVIEW`; production still requires a separate approval.
