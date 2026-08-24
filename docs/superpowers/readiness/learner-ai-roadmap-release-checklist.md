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
