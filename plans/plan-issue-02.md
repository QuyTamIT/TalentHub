# Issue 02 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or superpowers:executing-plans.

**Goal:** Make project skills usable by AI matching and make persisted AI results detect current learner data changes.

**Architecture:** Extend the project evidence query with verified skill tags and membership state, build one canonical snapshot hash, and use existing outbox/worker services for refresh. Keep deterministic matching and model prose separate.

**Tech Stack:** PHP, PDO, existing `AiSourceRegistry`, `LearnerApiContext`, matching services, outbox worker, learner JS.

## Scope and files

- Modify `app/learner/data/Database/DatabaseTalentPassportRepository.php`, `app/learner/ai/Sources/AiSourceRegistry.php`, `app/learner/ai/Matching/LearnerOpportunityProfile.php`, `app/learner/ai/Service/RecommendationService.php`, `app/learner/api/LearnerApiContext.php`, project/internship mutation repositories, and AI learner assets.
- Create only the required migration for project skill association or verified evidence if the existing schema lacks it.

## Implementation

## Current implementation record — complete

- [x] Added `project_skill_tags` schema and school project create/update validation for active canonical skill codes.
- [x] Applied `019_create_project_skill_tags` on MySQL (`talenthub`). Registry status **APPLIED**. Table columns: `id`, `projectId`, `skillId`, `verifiedAt`, `createdAt`.
- [x] Loaded verified project skills into Talent Passport aggregate as `skill_tags` and `skill_codes` for AI sources.
- [x] UNION mentor-verified `learner_portfolio_skills` (project submissions + internship reports, `status='verified'`, active skills only) into `DatabaseTalentPassportRepository::skills()`. Revoked reports drop out immediately.
- [x] Registered `portfolio_skill` and `internship` AI sources in `AiSourceRegistry`; `LearnerOpportunityProfile::confirmedExperienceTags()` reads internships and portfolio skills without inventing mastery scores.
- [x] Recommendation GET compares current snapshot hash with the persisted run hash and returns `stale_model` when they differ. POST generate remains allowed so the learner can refresh.
- [x] Transactional outbox on portfolio verify/revoke (`portfolio.verified` / `portfolio.revoked`) and on school project create/update (inside the mutation transaction).
- [x] UI already exposes `stale-model` plus refresh on recommendation/roadmap/job/opportunity screens; `tests/learner_ai_stale_state_ui_contract_test.js`: 1 passed, 0 failed.
- [x] Focused PHP test `tests/learner_project_skill_sync_test.php`: verify → skills() + hash change + outbox; revoke → skill removed + hash restored + outbox; two consecutive `updateProject` writes two distinct outbox versions; removed membership drops skill/project and changes snapshot hash. Related PHP/JS regressions PASS.

- [x] Project skill evidence is stored and exposed as canonical `skill_tags`/`skill_codes`.
- [x] Select project skill tags, contribution, status and membership status; normalize them in `AiSourceRegistry` and exclude removed memberships.
- [x] GET latest compares current-snapshot versus persisted-run hash; generate() still runs so refresh is not blocked.
- [x] Publish refresh events on project create/update, portfolio verify/revoke, and the existing internship update workflow; preserve transactional outbox semantics.
- [x] Recommendation/roadmap/job UI already shows stale state and offers refresh; it does not silently replace a learner-visible result while a job is pending.
- [x] Verify completed-project / internship skill evidence, changed hash, outbox event and stale UI with focused PHP tests and the existing JS contract.

## Residual defects closed — 2026-09-09

- [x] exclude removed memberships: `portfolioVerifiedSkills` INNER JOIN `project_members` (`pm.projectId = r.projectId AND pm.studentId = r.studentId AND pm.status = 'active'`); shared and aggregate project reads (previously lines 167 and 653) require `pm.status = 'active'`. Removed members lose verified project skills, drop out of the project list, and change the `AiSourceRegistry` snapshot hash immediately.
- [x] publish refresh events on project mutation: `SchoolProjectRepository` create/update uses `TransactionalAiOutboxPublisher::version()` instead of hardcoded `1`. If `publish()` returns false the mutation transaction throws and rolls back instead of swallowing the outbox write.
