# Learner AI Roadmap release checklist

Status: **MODEL_VISIBLE_BLOCKED**
Default visibility: `0%`, pilot paused.

This checklist records only safe metrics and hashes. Never paste a learner prompt, raw model response, API key, student identifier, or provider authorization header here.

## Automated contract and safety gate

- [x] Roadmap contract and exactly three 30-day phases are validated.
- [x] Every insight, phase, and task requires snapshot evidence.
- [x] Activity identifiers are allow-listed at generation and revalidated against current availability before a link is rendered.
- [x] Vietnamese learner copy is validated.
- [x] Deterministic safety cases cover diagnosis, protected traits, guaranteed outcomes, fabricated activities, unsupported links, prompt injection, English-only output, uncited claims, and discovery-result duplication.
- [x] Model and rule-fallback origins are labelled separately.
- [x] Progress, feedback, and roadmap versions are owner-scoped and persisted.
- [x] Real recommendation CTA clicks are emitted separately from feedback through a same-origin CSRF-protected endpoint; telemetry failure never blocks link navigation.
- [x] Phase 2 registry reads certificates, projects, badges, progress, confirmed check-ins, teacher feedback, and roadmap feedback from the canonical learner aggregate.
- [x] Phase 2 source availability is explicit: unsupported canonical sources (currently achievements and mentor evaluations) are surfaced with machine-readable reasons and are not silently treated as present.
- [x] Phase 2 evidence persistence accepts the canonical source types through migration `20260827000100_extend_learner_ai_evidence_source_types`.
- [x] Model input includes consent/source completeness flags so unavailable or revoked data is visible to the provider contract.
- [ ] Roadmap model execution is fail-closed behind shadow-gate approval, pilot reference, cohort percentage and an immediate pause switch; `0%` cannot call the model. This remains a Phase 1 implementation gate.

Commands:

```powershell
$php = 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe'
& $php tests\learner_ai_roadmap_safety_evaluation_test.php
$env:APP_ENV = 'test'
& $php tests\learner_ai_roadmap_live_contract_test.php
& $php bin\report-learner-ai-evaluation.php --format=json
```

## Live model provenance gate

- [ ] A separate one-call approval was granted for `learner_ai_roadmap_live_contract_test.php`.
- [ ] The test ran with `APP_ENV` other than `production` and an in-memory disposable schema.
- [ ] `provider_call_count = 1`.
- [ ] `analysis_origin = model`; the stored run has non-empty provider, model, prompt version and response hash.
- [ ] Every evidence reference resolves to the stored snapshot.
- [ ] Roadmap safety metrics pass and no fallback label is present on the success path.
- [ ] A reviewer checked coherence, education-band appropriateness, actionability and non-repetition of Khám phá năng khiếu.

Safe live evidence record (leave blank until the approved run):

```text
Executed at UTC:
Approval reference:
Provider/model:
Prompt version:
Response hash prefix:
Contract validity:
Vietnamese-language rate:
Evidence coverage:
Activity-grounding rate:
Reviewer/sign-off:
```

## Database and rollout gates

- [ ] Fresh backup path/hash recorded outside the repository.
- [x] Exact migration passed twice on an explicitly disposable local schema; it was removed in `finally`.
- [x] Parent metadata and a representative legacy read operated normally against the additive schema.
- [ ] Primary migration apply has a new exact-target approval. (The primary `talenthub` database has not been changed by this checklist.)
- [ ] Product Owner accepted the disposable-account browser E2E and cleanup evidence.
- [ ] Pilot cohort, approval reference, thresholds and zero-percent rollback switch are documented.

Do not enable learner-visible model output until every unchecked gate above is signed off.

## Phase 0 customer contract and release gates

The roadmap shares the Phase 0 **target** response contract with the other AI capabilities: `profile_analysis`, `talent_passport`, `recommendation`, `roadmap`, `adaptive_loop`, `school_insight`, and `enterprise_matching`. The canonical availability field is `state`, not the persistence field `status`. A target response includes `contract_version`, `analysis_origin`, `evidence`, `generated_at`, `model_version`, `rule_version`, `state`, and `freshness_status`. The allowed states are `pending`, `stale_model`, `ai_unavailable`, `ready_model`, and `ready_rule`; freshness is `pending`, `stale`, `unavailable`, or `fresh`. `ready_model`/`stale_model` require model origin; `ready_rule` requires rule origin.

The current roadmap runtime still exposes legacy values: `analysis_origin=rule_fallback`, `state=fallback_rule`, and no `freshness_status` on the ready response. Phase 1 must normalize these values as `analysis_origin=rule`, `state=ready_rule`, and an explicit freshness state. The Phase 0 contract test probes and freezes this gap so it cannot be mistaken for completed runtime support.

| Current runtime value | Phase 0 canonical value |
|---|---|
| `analysis_origin=rule_fallback` | `analysis_origin=rule` |
| `state=fallback_rule` | `state=ready_rule` |
| missing `freshness_status` | `freshness_status=fresh` or `stale` according to persistence state |

- [ ] Input completeness is evidenced for every consented assessment, profile, skill, activity, opportunity, certificate, project, achievement, badge, progress, check-in, mentor/teacher evaluation, and roadmap-feedback source; missing consent or stale input is explicit.
- [ ] Every insight, phase, task, recommendation, and trend has resolvable evidence with source timestamp, snapshot hash/version, analysis origin, and generated/model version metadata.
- [ ] Consent and privacy tests cover grant and revoke per source; no unnecessary PII, protected trait, prompt content, secret, or provider authorization header is sent or rendered.
- [ ] Provider failure tests cover quota/429, timeout, 5xx, malformed output, invalid credential, and prolonged outage. The learner sees a clear `pending`, `stale_model`, `ai_unavailable`, or transparent `ready_rule` state.
- [ ] A failed refresh preserves the last-known-good roadmap, marks `stale_model`, records `stale_since`, error category, and next retry, and does not silently downgrade a valid model to a rule result.
- [ ] Check-in, activity completion, profile/skill edits, badge/progress changes, mentor/teacher evaluation, certificate/project updates, and roadmap feedback enqueue one debounced, version-ordered refresh within the published SLA.
- [ ] Rollback evidence identifies the prior model/version, operator, trigger, command, verification result, and safe learner-visible state.

### Staged rollout decision record

| Stage | Required before advancing |
|---|---|
| `0% shadow` | Contract/safety tests pass, output is non-visible, telemetry contains only hashes/metrics, and pause switch is verified. |
| `pilot` | Product Owner approval reference, representative sample review, consent/privacy review, provider error budget, stale SLA, and rollback drill are recorded. |
| `10%` / `25%` / `50%` | Previous-stage metrics remain within error budget; freshness, evidence coverage, provider health, feedback/adaptive refresh, and incident monitoring are reviewed. |
| `100%` | `enabled=true`, `shadow_gate_approved=true`, `visible_percent=100`, `pilot_paused=false`, valid approval reference, unified policy, queue/last-known-good monitoring, and rollback readiness are all evidenced. |

Keep this checklist at `MODEL_VISIBLE_BLOCKED` whenever any gate is unchecked. Never claim “100% AI displayed” from configuration alone.

## Phase 8 operations evidence

Owners: AI Platform on-call for queue/provider/circuit, Security & Privacy for consent incidents, Database Operations for migration rollback, and Product Owner for staged approvals. `ai-observability-v1` structured events are sanitized to hashes/metrics only and retained for 30 days. Alert at queue depth 100, oldest queue age 300 seconds, stale ratio 5%, provider error rate 10%, p95 latency 2,000ms, quota zero, or circuit `open`; each alert pauses the pilot. Privacy review must explicitly confirm no learner identifier, prompt, raw response, credential, or authorization header is retained.

Recommendation CTA telemetry evidence: the browser sends only `itemId`, optional `catalogId`, and an allow-listed `actionType` using `credentials=same-origin`, `keepalive=true`, and `X-CSRF-Token`. The endpoint revalidates learner ownership and catalog evidence before emitting only the aggregate click flag/action category. Invalid CSRF, invalid input, cross-owner IDs, and infrastructure failures record no click; the client never awaits the request or prevents the CTA's default navigation.

Staged rollout evidence is recorded in order: `0% shadow`, approved `pilot`, `10%`, `25%`, `50%`, then `100%`. Every transition requires error-budget, freshness-SLA, validator pass-rate, privacy, provider-health, and rollback-drill evidence. The 100% stage is blocked until all previous stages are recorded and the unified `AiAvailabilityPolicy` plus last-known-good/queue monitoring are verified.
