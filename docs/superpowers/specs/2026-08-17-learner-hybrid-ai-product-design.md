# Learner Hybrid AI Product Design

**Status:** Approved conversational design; pending written-spec review
**Date:** 2026-08-17
**Scope owner:** Learner module
**Delivery strategy:** Incremental end-to-end vertical slices
**Initial model provider:** Gemini through 9Router, shadow mode only
**Production model provider:** Undecided; replaceable through the existing provider contract

## 1. Purpose

Build the learner AI journey defined by the FTalentHub product presentation:

1. A learner creates and maintains a profile.
2. The learner completes Holland, MBTI-inspired, DISC, and Multiple Intelligence assessments.
3. The system analyzes interests, preferences, strengths, improvement areas, and development potential.
4. The system recommends existing clubs, project groups, and mentor groups.
5. The system recommends eligible activities and development actions.
6. The learner participates and accumulates verified experience.
7. Teacher or mentor evaluations and learner feedback update the evidence base.
8. The system tracks progress over time and contributes verified facts to the Talent Passport.

The primary product is an explainable recommendation and development-roadmap system, not a general-purpose chatbot. Deterministic scoring and rules remain authoritative. A language model may improve explanations and presentation only within validated evidence and action boundaries.

## 2. Product Decisions

- Use a safe hybrid architecture.
- Rule/scoring output is learner-visible and authoritative at launch.
- Gemini through 9Router initially runs in shadow mode.
- Keep model-visible rollout at `0%` until quality and safety gates pass and explicit approval is recorded.
- Keep the provider replaceable because the production API has not been selected.
- Deliver all four assessments in the MVP.
- Use internally authored educational-orientation questions, not copied proprietary instruments and not clinical or psychological diagnosis.
- Support three learner bands: grades 6–9, grades 10–12, and college/university.
- Present four independent assessments with autosave and cross-session resume.
- Permit a retake after 90 days; preserve all historical attempts and results.
- Build a rolling three-month roadmap.
- Show advice directly to the learner with evidence, an advisory disclaimer, and no teacher pre-approval gate.
- Seed versioned question banks into the database; defer question-bank administration UI.
- Recommend real TalentHub opportunities plus safe general learning actions.
- Launch in Vietnamese while keeping content and API contracts localization-ready.
- Preserve the existing learner design system; use the presentation as a functional and content reference rather than a pixel-perfect template.
- Require scoped consent and allow revocation.
- Show per-assessment results and a provisional combined profile after at least two completed assessments.
- Configure provider endpoint, model, allowed hosts, and key through environment variables only.

## 3. Goals

1. Persist versioned, resumable assessment attempts and immutable submitted results.
2. Produce deterministic, reproducible scores for four educational-orientation frameworks.
3. Combine assessment preferences with verified skills, activities, projects, experience, and published evaluations without conflating preference with demonstrated ability.
4. Recommend only real, eligible TalentHub groups and activities.
5. Generate an explainable rolling three-month development roadmap.
6. Preserve evidence and historical recommendation versions.
7. Collect learner feedback and use new verified facts to regenerate later roadmaps.
8. Evaluate Gemini safely in shadow mode before any learner-visible model output.

## 4. Non-goals

- Clinical, psychiatric, psychological, or diagnostic assessment.
- Claiming that the internal MBTI-inspired or DISC content is an official licensed instrument.
- Automatic grading, admissions, hiring, disciplinary, or eligibility decisions.
- Letting a model calculate assessment scores or change source-of-truth records.
- Letting a model invent groups, activities, projects, skills, achievements, or evidence identifiers.
- Automatically forming learner groups or enrolling a learner.
- Writing provider credentials, raw secrets, or unnecessary personal data to the database or logs.
- Building a question-bank administration UI in the MVP.
- Selecting or integrating the final production provider in this design.

## 5. Architecture

### 5.1 Assessment subsystem

The assessment subsystem owns question selection, version resolution, attempt lifecycle, autosave, submission, scoring, and result retrieval. Each scorer is isolated behind a common contract and is versioned independently.

The client never calculates or submits authoritative scores. The server loads the published assessment version for the authenticated learner's education band, validates every answer, computes scores, persists the immutable result, and returns a presentation-safe read model.

### 5.2 Competency profile subsystem

The profile builder combines normalized assessment dimensions with learner-owned or published evidence from:

- student profile and education band;
- student skills and verification state;
- projects and project membership;
- confirmed activity participation and experience;
- published teacher or mentor evaluations;
- certificates and achievements where a canonical source exists.

It outputs interests, evidence-backed strengths, learning/work preferences, improvement areas, potential domains, suitable group roles, coverage, confidence, contradictions, and source references.

### 5.3 Recommendation subsystem

The existing snapshot, data-quality, rule-engine, evidence, persistence, feedback, audit, and API foundations remain the core. New rule definitions cover combined assessment profiles, opportunity matching, group suggestions, and rolling roadmap steps.

### 5.4 Model subsystem

The existing provider-independent model contract remains the integration boundary. Gemini through 9Router receives a minimized snapshot, validated rule output, and opaque evidence references. It may improve learner-facing explanations and sequence roadmap content, but it cannot introduce unsupported facts or modify deterministic values.

The production provider can later replace 9Router by changing configuration or a provider adapter without changing the assessment scorers, rule engine, persistence schema, or learner response contract.

## 6. End-to-End Data Flow

1. Authenticate the learner and resolve `studentId` server-side.
2. Resolve the learner's education band from canonical profile/class data when the grade is unambiguous. If it is not, require an explicit band confirmation before starting and bind that choice to the attempt's assessment version.
3. Show four assessment cards and the latest attempt state.
4. Start or resume one published, band-appropriate assessment version.
5. Autosave validated answers to the current attempt.
6. On submit, require all mandatory answers and enforce idempotency.
7. Run the deterministic, versioned scorer and persist one immutable result.
8. Build or refresh the combined competency profile after at least two completed assessment types.
9. Apply scoped consent and create a minimized immutable recommendation snapshot.
10. Retrieve only eligible groups, projects, mentors, and activities from canonical data.
11. Run the deterministic rule engine and build a rolling three-month roadmap.
12. Persist the run, snapshot, items, evidence, roadmap actions, audit data, and feedback capability.
13. When shadow mode is enabled, send the minimized rule-bounded request to Gemini through 9Router.
14. Validate model schema, evidence coverage, safety, and unsupported claims.
15. Keep the validated rule result learner-visible while model visibility is `0%`.
16. Append later feedback and regenerate when material source facts change.

## 7. Database Design

### 7.1 Reuse existing canonical assessment tables

- `talent_tests`: seed 12 definitions, representing four frameworks across three education bands.
- `test_questions`: seed internally authored, band-specific questions and options.
- `learner_assessment_versions`: record question-bank and scoring versions.
- `learner_assessment_question_versions`: freeze question order, dimension mapping, and required status per version.
- `test_attempts`: own the learner's attempt lifecycle.
- `learner_assessment_attempt_metadata`: bind an attempt to a published version and resumable lifecycle metadata.
- `learner_assessment_answers`: autosave one validated answer per attempt/question.
- `test_results`: store one immutable scored result per submitted attempt.

Example test codes are `holland_middle`, `holland_high`, and `holland_college`. Equivalent codes apply to MBTI-inspired, DISC, and Multiple Intelligence tests. All bands use the same normalized dimension codes so trends remain comparable.

Education-band resolution uses `classes.gradeLevel` only when it clearly maps to grades 6–9 or 10–12. A missing, overloaded, or institution-specific value cannot be guessed from school name or date of birth. In that case, the assessment start flow requires the authenticated learner to confirm grades 6–9, grades 10–12, or college/university. The server validates the choice, selects the matching published version, and permanently records that version through `learner_assessment_attempt_metadata`. No separate duplicate learner-profile table is introduced.

### 7.2 Seed policy

- Use deterministic UUIDs and insert-only behavior.
- Never overwrite a published question or scoring version.
- Publish a new version and retire the prior version when content changes.
- Keep every submitted attempt linked to the exact version completed.
- Do not implement an administration UI in the MVP.

### 7.3 Retake and resume policy

- An in-progress attempt can be resumed across authenticated sessions and devices.
- Submitted answers and results are immutable.
- A new attempt for the same assessment type is allowed 90 days after the latest submitted attempt.
- Old attempts and results remain visible in history.
- The latest submitted result per assessment type is used for the current combined profile.

### 7.4 Profile, consent, and recommendation data

- `student_skills` remains the skill source with explicit source and verification state.
- Assessment preference never marks a skill as verified.
- `privacy_consents` and `learner_ai_consent_events` record grants and revocations by scope.
- Existing `learner_recommendation_*` tables store runs, snapshots, items, evidence, feedback, and audit events.
- Recommendation history is append-only; a regenerated roadmap creates a new run/version.

### 7.5 Group and opportunity sources

- Project-group suggestions read `projects` and `project_members`.
- In the MVP, clubs and mentor groups are represented by active, published `activities` with the corresponding category and responsible teacher/mentor.
- Recommendations use only records the learner is allowed to see and join.
- Every group/activity recommendation stores a real `sourceType` and `sourceId`.
- No matching record means an explicit empty state; the model cannot fabricate one.
- Recommendation never creates membership or registration. The learner follows the normal join/register flow.

### 7.6 Migration safety

- Prefer existing tables and additive, forward-only migrations.
- Do not use `DROP`, `DELETE`, `TRUNCATE`, destructive rename, or history-rewriting backfill.
- Run every migration twice on an isolated database and require the second run to be a no-op.
- Compare source table counts and integrity evidence before and after migration.
- Do not apply a shared database migration without its required approval and safety evidence.

## 8. Assessment Design and Scoring

### 8.1 Holland educational orientation

- 30 internally authored questions.
- Six RIASEC dimensions: Realistic, Investigative, Artistic, Social, Enterprising, Conventional.
- Return raw and normalized scores and a top-three orientation code.

### 8.2 MBTI-inspired educational preferences

- 32 internally authored questions.
- Four continuous preference axes: E–I, S–N, T–F, J–P.
- Return axis percentages and an optional four-letter summary for orientation only.
- Always display that this is not an official licensed or diagnostic MBTI assessment.

### 8.3 DISC educational behavior

- 28 internally authored questions.
- Four dimensions: Dominance, Influence, Steadiness, Conscientiousness.
- Use results for communication, learning environment, group-role, and development suggestions.

### 8.4 Multiple Intelligence orientation

- 32 internally authored questions.
- Eight dimensions: linguistic, logical-mathematical, spatial, bodily-kinesthetic, musical, interpersonal, intrapersonal, and naturalistic.

### 8.5 Shared scoring rules

- Use a five-point response scale.
- Randomize presentation order while preserving stable question identifiers.
- Include reviewed reverse-scored items for consistency checks.
- Convert raw dimension values to normalized 0–100 scores.
- Store raw scores, normalized scores, scoring version, completion, and consistency indicators.
- Never collapse interests, personality preferences, and demonstrated skills into one universal ability score.

## 9. Combined Competency Profile

- Holland and Multiple Intelligence primarily inform interests, domains, and preferred activity types.
- MBTI-inspired and DISC results inform learning/work preferences, communication, and group roles.
- Verified skills, completed projects, confirmed experience, and published evaluations are evidence of demonstrated capability.
- High interest does not imply high skill.
- Contradictory sources produce an explicit exploration opportunity rather than a forced conclusion.
- A provisional profile is available after two assessment types; coverage increases after all four.
- Confidence depends on completeness, consistency, freshness, and corroborating evidence, not merely the number of completed tests.

The profile response exposes:

- prominent interests;
- evidence-backed strengths;
- learning and work preferences;
- improvement areas;
- potential domains;
- suitable group roles;
- confidence and coverage;
- contradiction flags;
- safe evidence summaries.

## 10. Recommendation and Matching

### 10.1 Eligibility filters

Before ranking, exclude candidates that are hidden, inactive, ended, outside the learner's permitted school/audience, unavailable, full, missing prerequisites, or in serious schedule conflict.

### 10.2 Versioned deterministic ranking

The initial ranking uses:

- 40% interest/domain fit from Holland and Multiple Intelligence;
- 25% development-need fit;
- 15% demonstrated skill and published evaluation evidence;
- 10% feasibility such as schedule, location, and capacity;
- 10% exploration value.

Weights are configuration owned by a versioned rule set and covered by golden tests. Every displayed match includes a reason and source reference.

## 11. Rolling Three-Month Roadmap

The roadmap always covers the next three months:

- Month 1 — foundation: learn or practice a core skill.
- Month 2 — application: join an eligible group, project, mentor activity, or experience.
- Month 3 — evidence and feedback: complete an artifact, obtain feedback, or demonstrate progress.

Each month contains two to four actions. Every action includes:

- objective;
- action type;
- optional canonical opportunity reference;
- recommendation reason;
- time window;
- completion criterion;
- progress state;
- safe evidence references.

A material new assessment result, verified skill, confirmed check-in, published evaluation, completed action, or learner feedback can trigger a new roadmap version. Historical roadmaps remain unchanged.

## 12. Gemini Through 9Router

### 12.1 Allowed model behavior

- Summarize the validated competency profile in age-appropriate Vietnamese.
- Explain evidence-backed strengths and improvement areas.
- Improve roadmap wording and ordering within the supplied validated actions.
- Produce only the approved structured response schema.

### 12.2 Forbidden model behavior

- Recalculate or modify assessment dimensions.
- Invent groups, activities, source identifiers, skills, achievements, or experience.
- Diagnose personality, mental health, or career certainty.
- Add unsupported claims.
- Directly mutate source data or Talent Passport facts.

### 12.3 Provider configuration

Reuse the existing environment contract:

- `TALENTHUB_AI_ENABLED`
- `TALENTHUB_AI_PROVIDER`
- `TALENTHUB_AI_MODEL`
- `TALENTHUB_AI_API_URL`
- `TALENTHUB_AI_API_KEY`
- `TALENTHUB_AI_ALLOWED_HOSTS`
- timeout, retry, rate-limit, shadow, gate, and visible-percentage flags already defined by `RecommendationConfig`.

The key is never committed, logged, returned to the browser, or stored in recommendation records. The API URL must use HTTPS and an explicitly allowed hostname.

## 13. Learner UI

### 13.1 Assessment catalog

Show four cards with status, completion percentage, estimated time, latest result, next retake date, and a start/resume action. When the education band cannot be resolved unambiguously, show a one-time confirmation before creating the attempt; resuming an attempt always reuses its bound version.

### 13.2 Assessment runner

Show educational-purpose disclosure, scoped consent where needed, progress, one manageable question group at a time, autosave state, backward navigation, missing-answer review, and an idempotent submit action.

### 13.3 Result view

Show dimension charts, prominent tendencies, strengths, learning preferences, age-appropriate explanation, advisory disclaimer, result history, and next eligible retake date.

### 13.4 AI recommendation view

Extend the existing learner AI page with:

- combined profile summary;
- strengths, improvement areas, and potential domains;
- existing clubs, project groups, mentor groups, and activities;
- a three-month timeline;
- evidence detail through a “Why this recommendation?” action;
- useful/not suitable feedback and safe reason codes;
- generated time, engine label, disclaimer, and coverage state;
- loading, consent-required, insufficient-data, source-error, and empty-match states.

The learner dashboard shows a concise recommendation summary and links to the full page. Talent Passport receives verified facts and actual progress, never unsupported model inference.

## 14. API Design

Keep learner-owned endpoints and stable provider-independent responses for:

- assessment catalog/status;
- start/resume attempt;
- save answer;
- submit attempt;
- assessment result/history;
- combined competency profile;
- consent grant/revoke;
- latest/generate recommendation;
- recommendation evidence;
- feedback;
- roadmap action status.

All mutations require authentication, server-resolved ownership, CSRF protection, validation, and appropriate idempotency. Client-supplied learner identity is ignored or rejected.

## 15. Error and Fallback Behavior

| Condition | Behavior |
|---|---|
| Autosave network failure | Retain unsynced UI state, retry safely, and show sync status |
| Duplicate submit/generate | Return the existing idempotent result |
| Missing required answer | Reject submission and identify missing positions |
| Fewer than two completed tests or insufficient evidence | Return `insufficient_data` with safe completion actions |
| Missing or revoked consent | Return `consent_required`; do not call the provider |
| Source database unavailable | Return `source_unavailable`; never substitute mock data |
| Snapshot changes during generation | Stop the stale run and permit regeneration |
| No eligible group/activity | Return an explicit empty state |
| Provider timeout/rate limit/unavailable | Return the validated rule result and record a safe fallback reason |
| Invalid or unsafe model response | Reject the full model response and return the rule result |

Errors include safe request IDs. Logs exclude credentials, unnecessary personal identifiers, full answer payloads, and raw sensitive provider payloads.

## 16. Testing Strategy

### 16.1 Foundation gate

- Fix the current `PermissionService` SQLite compatibility regression.
- Re-run the full non-MySQL learner AI suite before feature work.
- Run isolated MySQL 8.4 verification where the test safety contract allows it.

### 16.2 Assessment tests

- Golden scoring fixtures for every scorer and education band.
- Boundary, tie, reverse-item, missing-answer, inconsistent-answer, and version-history tests.
- Attempt ownership, autosave, resume, expiry, idempotent submit, immutable result, and 90-day retake tests.

### 16.3 Data and migration tests

- Apply migrations twice and require no-op on the second run.
- Prove no existing row is deleted or rewritten.
- Prove two learners cannot cross-read or cross-write.
- Prove old attempts always resolve their original assessment/scoring versions.

### 16.4 Recommendation tests

- Golden combined-profile fixtures.
- Eligibility and ranking tests for groups and activities.
- Deterministic roadmap tests with evidence and completion criteria.
- Exclusion tests for hidden, expired, full, ineligible, or conflicting candidates.
- Feedback and version-regeneration tests.

### 16.5 UI and accessibility tests

- Catalog, runner, autosave, resume, result, combined profile, roadmap, evidence, consent, feedback, and error-state tests.
- Keyboard navigation, focus management, live-region, contrast, and mobile-layout checks.

### 16.6 Provider and shadow tests

- Fake-provider success, timeout, rate-limit, unavailable, malformed JSON, invalid evidence, unsafe content, and fallback tests.
- 9Router Gemini tests use synthetic or approved isolated data only.
- Consent revocation must prove that no model call occurs.

## 17. Model Quality Gates

Before any model-visible percentage above zero:

- 100% response-schema validity;
- 100% evidence coverage;
- 0 unsupported group, activity, skill, achievement, or experience claims;
- 0 unsafe, diagnostic, or age-inappropriate output;
- rule fallback succeeds for every simulated provider failure;
- consent revocation blocks every model call;
- learner isolation passes;
- latency and cost are recorded against approved thresholds;
- security, privacy, and bias review is approved;
- a product owner explicitly approves the pilot percentage.

## 18. Delivery Slices

### Slice 0 — Stabilize the foundation

Fix the RBAC/SQLite regression and return the learner AI baseline tests to green.

### Slice 1 — Four assessments

Seed versioned band-specific question banks; implement autosave, resume, deterministic scoring, result history, and retake policy.

### Slice 2 — Combined competency profile

Combine assessment results with canonical learner evidence and expose coverage, confidence, and contradictions.

### Slice 3 — Rule recommendations and roadmap

Implement real group/activity matching, evidence explanations, and rolling three-month roadmaps.

### Slice 4 — Gemini through 9Router shadow

Configure the provider adapter, minimized request, strict validation, evaluation metrics, and rule fallback. Keep model visibility at zero.

### Slice 5 — Controlled pilot

After all gates pass, run a monitored internal pilot. Production provider selection and any visible-model approval are separate decisions.

## 19. Acceptance Criteria

The MVP is accepted when:

1. An authenticated learner in each education band can independently complete, resume, submit, and view all four assessments.
2. Every result is deterministic, versioned, immutable, and reproducible.
3. A provisional combined profile appears after two assessment types and a fuller profile appears after all four.
4. The system distinguishes interests/preferences from demonstrated skills.
5. Every displayed group or activity exists, is eligible, and has a canonical source reference.
6. Every roadmap action has a reason, evidence, time window, and completion criterion.
7. Feedback and new verified facts create later recommendation versions without rewriting history.
8. Consent and learner isolation are enforced server-side.
9. Provider failure never prevents a validated rule result from being shown.
10. Gemini remains shadow-only until the documented model quality gates and explicit approval pass.
