# Learner AI Rule and Shadow Hardening Design

Date: 2026-08-20
Status: Approved for implementation planning

## Goal

Make the deterministic Holland career-group recommendation path stable and suitable for offline/shadow AI evaluation without implementing a real learner activity-registration API or changing the primary database.

## Scope

The work covers four bounded areas:

1. Normalize Holland assessment handling for `holland`, `holland_middle`, `holland_high`, and `holland_college`.
2. Make the four career-group mappings deterministic and explicit:
   - `technical`: R/I;
   - `business`: E;
   - `arts`: A;
   - `sports_academic`: S/C.
3. Preserve the production quality gate: the recommendation service requires a current assessment, at least two skills, one confirmed activity experience, and one published evaluation. Missing data returns `insufficient_data`; missing consent returns `consent_required`.
4. Keep model output shadow-only: `TALENTHUB_AI_VISIBLE_PERCENT=0`, mock HTTP transport in tests, safe Rule Engine fallback for provider failures, and no external provider calls.

The learner activity UI remains mock/localStorage based. Tests may verify database opportunity persistence and exclusion on a disposable database, but must not claim that a real learner registration API exists.

## Non-goals

- No Gemini/9Router API key or live network call.
- No model-visible rollout.
- No migration or schema change.
- No seed or write to `talenthub_local`.
- No implementation of learner activity-registration controllers/services.
- No changes to unrelated teacher, school, enterprise, or shared-role workflows.

## Architecture and Data Flow

Assessment source data is normalized into recommendation facts. Holland dimension scores are validated and classified by `CareerGroupClassifier`. `RuleRecommendationEngine` consumes the normalized assessment and opportunity facts, applies the deterministic RuleSet, and emits validated recommendation items with evidence references. `RecommendationService` remains the boundary that enforces consent, data quality, persistence, and learner ownership. When model integration is configured for shadow evaluation, the model engine receives the same minimized snapshot through a mock transport; `RecommendationRolloutSelector` keeps all outward responses on the Rule Engine while visible percent is zero.

Opportunity category mapping is explicit and case-insensitive. Unknown categories are ignored for career-group recommendations rather than guessed. Closed, inactive, cancelled, completed, or archived activities are excluded from open opportunities.

## Error and Safety Behavior

- Invalid test code, missing/duplicate Holland dimensions, non-numeric scores, and out-of-range scores produce no career-group classification.
- Empty or non-matching rule facts produce a validated Rule result with `insufficient_data` and no speculative items.
- Unconsented source data produces `consent_required`.
- Provider timeout, connection failure, HTTP 4xx/5xx, rate limiting, malformed JSON, invalid evidence references, or quality-gate rejection fall back to the deterministic Rule Engine with a safe internal reason.
- `TALENTHUB_AI_VISIBLE_PERCENT=0` prevents provider output from reaching learner API/UI responses.
- All integration writes are restricted to an approved disposable MySQL 8.4.3 database and cleaned after the test.

## Verification Plan

The implementation must be test-first:

1. Add or extend focused classifier tests for banded Holland codes, all four groups, tie-breaks, invalid inputs, and unknown categories.
2. Add or extend Rule Engine tests for normalized opportunity mapping, closed-activity exclusion, missing-data behavior, consent behavior, and deterministic ordering.
3. Run the full disposable MySQL E2E flow: catalog discovery, assessment persistence, scoring, four group classifications, matching recommendations, opportunity exclusion after a fixture registration, and cross-learner isolation.
4. Run shadow provider tests with mock HTTP only, including success envelopes, timeout, HTTP errors, malformed responses, rate limiting, consent, and visible-percent-zero invariants.
5. Run existing assessment, career, recommendation, and Node UI regression suites.
6. Verify `talenthub_local` with full introspection before and after; expected state remains 13 migrations applied, zero pending, zero drift, and unchanged assessment/attempt counts.

## Acceptance Criteria

- All four career groups classify deterministically from banded Holland results.
- Rule recommendations contain only open activities matching the classified group and valid evidence.
- Production quality gate behavior is unchanged and regression-tested.
- Shadow provider failures never replace or leak the Rule Engine output.
- No external provider call occurs during tests.
- `talenthub_local` is unchanged.
- The final report explicitly distinguishes disposable persistence verification from a real learner registration API.
