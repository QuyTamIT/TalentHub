# Issue 05 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or superpowers:executing-plans.

**Goal:** Add AI-ranked activity suggestions using the established opportunity matching contracts.

**Architecture:** Read published/ongoing activities into candidates, score deterministic skill/context fit, optionally generate grounded explanations, persist by learner snapshot, and expose GET/POST API plus an activities widget.

**Tech Stack:** PHP/PDO, existing AI snapshot/scorer/prompt/guard/persistence patterns, learner JS/CSS.

## Implementation

- [ ] Define `ActivityCandidate` and `ActivityMatch` fields and write scorer/API contract tests before implementation.
- [ ] Create `DatabaseActivityCandidateSource` using the same published/status/capacity rules as `DatabaseActivityRepository`; exclude closed/full activities.
- [ ] Implement `ActivityMatchService` with snapshot hash, top-three deterministic ranking, grounded `why_fit`, `fit_reasons`, `skills_to_develop`, and persisted run state.
- [ ] Add `app/learner/api/v1/activity-matches.php` GET latest and POST refresh with authorization, rate limit and insufficient-data response.
- [ ] Add widget markup to `app/learner/activities.php` and `assets/js/learner-activity-matches.js` with loading, stale, error, empty and completed states.
- [ ] Verify ranking, stale refresh, no invented activity facts, registration link, access control and existing activity filters. This plan is intentionally last.
