# Issue 02 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or superpowers:executing-plans.

**Goal:** Make project skills usable by AI matching and make persisted AI results detect current learner data changes.

**Architecture:** Extend the project evidence query with verified skill tags and membership state, build one canonical snapshot hash, and use existing outbox/worker services for refresh. Keep deterministic matching and model prose separate.

**Tech Stack:** PHP, PDO, existing `AiSourceRegistry`, `LearnerApiContext`, matching services, outbox worker, learner JS.

## Scope and files

- Modify `app/learner/data/Database/DatabaseTalentPassportRepository.php`, `app/learner/ai/Sources/AiSourceRegistry.php`, `app/learner/ai/Matching/LearnerOpportunityProfile.php`, `app/learner/ai/Service/RecommendationService.php`, `app/learner/api/LearnerApiContext.php`, project/internship mutation repositories, and AI learner assets.
- Create only the required migration for project skill association or verified evidence if the existing schema lacks it.

## Implementation

- [ ] Add a failing fixture containing a completed project with canonical skill tags and assert those tags enter `confirmedExperienceTags` only for active/completed membership.
- [ ] Select project skill tags, contribution, status and membership status; normalize them in `AiSourceRegistry` and exclude removed memberships.
- [ ] Replace the hard-coded freshness callback in `LearnerApiContext.php:329` with current-snapshot versus persisted-run hash comparison; make `RecommendationService::latest()` expose stale metadata.
- [ ] Publish refresh events on project completion and every active internship content mutation; preserve transactional outbox semantics and document worker requirement.
- [ ] Update recommendation/roadmap/job UI to show stale state and offer refresh; do not silently replace a learner-visible result while a job is pending.
- [ ] Verify completed-project skill score, changed assessment/project hash, outbox job, worker completion and stale UI with focused tests and existing JS contracts.
