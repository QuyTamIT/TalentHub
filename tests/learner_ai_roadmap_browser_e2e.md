# Learner AI Roadmap browser E2E

Status: **BLOCKED BY LIVE MODEL + PRIMARY MIGRATION APPROVAL**

Use one disposable learner created specifically for this acceptance run. Record its UUID outside screenshots, never in committed evidence. Do not run against production.

## Preconditions

- Task 12 live contract passes with `analysis_origin=model` and one provider call.
- Task 13 exact migration has a fresh backup and explicit approval for the named non-production target.
- Pilot configuration has a non-empty approval reference, `TALENTHUB_AI_SHADOW_GATE_APPROVED=true`, an approved non-zero percentage, and `TALENTHUB_AI_PILOT_PAUSED=false`.
- The disposable learner is deterministically inside the pilot cohort.
- Browser network panel and screenshots do not expose session cookies, CSRF tokens, prompts, raw responses, or API keys.

## Success workflow

1. Create a new student account and accept onboarding.
2. Complete Holland, MBTI, DISC and Multiple Intelligence in order.
3. After the fourth submission, verify the AI summary modal appears exactly once and reports loading without blocking assessment persistence.
4. Verify the summary is labelled **Tóm tắt từ AI**, not fallback, then open `ai-recommendations.php`.
5. Confirm the Roadmap-first page shows model summary, one primary direction, two alternatives, three cited insights and exactly three phases: 0–30, 31–60 and 61–90 days.
6. Confirm Khám phá năng khiếu details and raw assessment scores are not repeated.
7. Complete one checklist task. Refresh and verify its state and total progress remain saved.
8. Log out, log in again and verify the same task remains completed.
9. Open any activity link and verify it targets only `/app/learner/activity-detail.php?id=<UUID>`. Close/fill that activity in test data and verify the Roadmap no longer renders a registration link.
10. Submit helpful/not-helpful feedback and verify only an allowlisted reason code is stored.
11. Change one approved source record, refresh the analysis once and verify version 2 is created. Select version 1 and version 2 and verify the changed-sections notice.
12. In technical details, confirm provider, model and prompt version are non-empty and no API URL, input hash, raw response, token or request payload is shown.
13. Query the test database: the active Roadmap run must use `engineType=model`; its provider/model/prompt fields and response hash are non-empty; `roadmap.runId` matches that run; evidence references belong to its snapshot.

## Failure workflows

- Simulate provider timeout. The last completed Roadmap remains visible and refresh reports a safe failure/fallback without replacing it.
- Return malformed provider JSON. No content is labelled “Từ AI”; the prior Roadmap remains active.
- Set `TALENTHUB_AI_PILOT_PAUSED=true` or visibility to `0`. Verify no provider request occurs and a rule-only Roadmap is explicitly labelled as such.
- Attempt cross-owner version, task and feedback requests. They must be rejected without disclosing the other learner UUID.

## Cleanup

Use the approved test-account cleanup service/transaction. Do not issue an ad-hoc recursive delete. Because Roadmap foreign keys are intentionally restrictive and immutable, cleanup must follow the reviewed child-to-parent test-data procedure or discard the entire disposable schema. Verify zero rows remain for the disposable learner in roadmap, phase, task, task-event, run, snapshot, evidence, consent and assessment test data.

## Product Owner sign-off

```text
Environment/target:
Pilot approval reference:
Executed at UTC:
Success workflow result:
Timeout result:
Malformed-response result:
Progress after re-login:
Version 2 evidence:
Disposable cleanup evidence:
Product Owner:
Decision:
```
