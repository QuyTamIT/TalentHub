# Learner AI shadow-evaluation gate

## Phase 12 final gate — 2026-08-23

The Phase 12 planning baseline required a fail-closed outcome unless every release gate was evidenced.

Phase 12 technical controls and additive telemetry storage are implemented, but the configured provider was not called and no representative consented sample was executed. Learner-visible output remains the Rule Engine and `TALENTHUB_AI_VISIBLE_PERCENT=0`. The final decision is `MODEL_VISIBLE_BLOCKED` because approved latency/cost thresholds, a representative sample, independent review, and an exact nonzero Product Owner pilot decision are absent.

The primary migration `20260823000100_create_learner_ai_evaluation_telemetry` is applied with 0 evaluation rows. Its append-only table does not authorize provider execution or model visibility.

## Required outcomes before visible model output

| Metric | Required value |
|---|---:|
| Schema validity | 100% |
| Evidence coverage | 100% |
| Unsupported-claim rate | 0% |
| Unsafe-output rate | 0% |
| Simulated provider failures using rule fallback | 100% |

Latency p50/p95 and cost-per-run are recorded by the evaluator, but have no release threshold until the product owner approves exact values in this document. Small demographic or cohort samples must report `insufficient_sample`, not a bias score.

Shadow runs may persist a validated `engineType=model` run for audit and comparison only after separate sample authorization. The visible learner response remains the completed rule result. `eligible_for_visible_rollout` stays `false` until this gate, cost/latency values, independent reviews, and the separate model-visible rollout approval are all recorded.

## Phase 0 customer target contract gate

The **target contract** is frozen for these seven capabilities: `profile_analysis`, `talent_passport`, `recommendation`, `roadmap`, `adaptive_loop`, `school_insight`, and `enterprise_matching`. Phase 0 defines and tests the target; it does not claim the current runtime already emits every field. Every capability response must converge on the same top-level provenance and availability fields:

- `analysis_origin`: `model`, `rule`, or `null` while work is not served;
- `contract_version`: the non-empty customer response contract version;
- `evidence`: an array of source references (never raw prompts, secrets, or unnecessary PII);
- `generated_at`: an ISO-8601 timestamp for a served result, otherwise `null`;
- `model_version`: non-empty only for model output;
- `rule_version`: non-empty only for rule output;
- `state`: one of `pending`, `stale_model`, `ai_unavailable`, `ready_model`, or `ready_rule`;
- `freshness_status`: one of `pending`, `stale`, `unavailable`, or `fresh`.

For `ready_model`, `ready_rule`, and `stale_model`, evidence and generation metadata are mandatory. `stale_model` must identify the last-known-good result and its `stale_since` timestamp. `pending` and `ai_unavailable` must not claim a generated origin, model version, or rule version. Silent fallback states are invalid.

`ready_model` and `stale_model` require `analysis_origin=model`; `ready_rule` requires `analysis_origin=rule`. A model state cannot expose a rule version, and a rule state cannot expose a model version.

Current roadmap runtime compatibility is explicitly recorded and is not considered target-contract compliance:

| Current runtime | Canonical target |
|---|---|
| `analysis_origin=rule_fallback` | `rule_fallback` → `rule` |
| `state=fallback_rule` | `fallback_rule` → `ready_rule` |
| no `freshness_status` on ready output | derive and expose `fresh` or `stale` from persisted freshness state |

Phase 1 owns the runtime normalization and unified availability policy. Until that work passes integration tests, the release status remains `MODEL_VISIBLE_BLOCKED`.

## Required customer-safety and operations criteria

Before a capability can move beyond shadow evaluation, the reviewer must record evidence for every item below:

| Gate | Required evidence |
|---|---|
| Input completeness | Snapshot includes all consented assessment, profile, skill, activity, opportunity, certificate, project, achievement, badge, progress, check-in, evaluation, and feedback sources applicable to the capability; missing or stale sources are explicit. |
| Evidence/provenance | Every insight/action resolves to an evidence reference with source timestamp, snapshot/version reference, analysis origin, generated timestamp, and model/rule version. |
| Consent and privacy | Consent is checked per source scope; revoke removes that source from subsequent prompts; school output is aggregate-only above the cohort threshold; enterprise matching excludes protected traits and hidden fields. |
| Provider error/quota/outage | 429, quota exhaustion, timeout, 5xx, malformed response, and invalid credential scenarios are tested; no secret, prompt, or provider authorization header appears in logs or responses. |
| Stale model / last-known-good | A failed refresh preserves the last valid model, marks it `stale_model`, records the failure and next retry, and never silently replaces it with a rule result. |
| Adaptive refresh | Activity, opportunity, certificate, badge, project, evaluation, check-in, progress, and feedback changes produce a versioned refresh request within the published SLA; duplicate events are idempotent/debounced. |
| Rollback | A bad model or migration can be reverted to the prior last-known-good version; rollback owner, trigger, command, and verification evidence are recorded. |
| Staged rollout | `0% shadow` → approved pilot → `10%` → `25%` → `50%` → `100%`; each step records sample review, error budget, freshness SLA, privacy review, provider health, and an immediate pause/rollback switch. |

No capability is eligible for 100% visibility until all criteria are signed off, `pilot_paused=false`, `visible_percent=100`, and a valid approval reference is recorded. A useful state (`ready_model`, `stale_model`, `pending`, `ai_unavailable`, or transparent `ready_rule`) must remain available to every learner during provider failure.

## Phase 8 observability and staged-release evidence

Owner: AI Platform on-call (telemetry/queue/provider), Security & Privacy (consent and privacy review), Database Operations (migration/rollback), and Product Owner (stage approval). The structured metric schema is `ai-observability-v1`; it records queue depth/age, freshness and stale ratio, provider latency/error/quota, circuit state, fallback rate, recommendation click/feedback, and token cost without learner IDs, prompts, responses, credentials, or authorization headers. Retention is 30 days for bounded operational events and aggregate metrics; privacy review must confirm this before every pilot stage.

Alert thresholds: queue depth >100 or oldest age >300s for 10 minutes; stale ratio >5% for 15 minutes; provider error rate >10% for 10 minutes; provider p95 latency >2,000ms; quota remaining = 0; or circuit state `open`. Any alert pauses the pilot and preserves a last-known-good or transparent pending/unavailable response.

The staged gate is `0% shadow -> approved pilot -> 10% -> 25% -> 50% -> 100%`. Advancement requires the prior stage's error budget, freshness SLA, validator/privacy checks, provider health, and rollback drill. The 100% gate additionally requires `enabled=true`, `shadow_gate_approved=true`, `pilot_paused=false`, a valid approval reference, queue/last-known-good monitoring, and a successful rollback rehearsal. The current decision remains `MODEL_VISIBLE_BLOCKED`; no configuration change is implied by this evidence checklist.
