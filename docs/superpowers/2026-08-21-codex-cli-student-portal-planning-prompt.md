# Codex CLI Planning Brief — Complete Student Portal and Four-Role Integration

## Mission

Act as the Principal Software Architect, Database Migration Lead, and Product Integration Reviewer for TalentHub.

Inspect the current repository and produce a detailed, evidence-backed implementation plan for completing the Student Portal while preserving and enabling real interactions with the other three roles:

1. Teacher
2. School
3. Enterprise

This session is **plan-only**. Stop after presenting the complete plan.

## Authorization boundary

You may:

- Read source code, repository instructions, Git history, schemas, migrations, tests, and documentation.
- Run read-only Git, filesystem, schema-inspection, and database-status commands.
- Run lint or non-destructive tests when their safety can be established first.
- Ask a question only when a material product decision cannot be resolved from repository evidence.

You may not:

- Modify or create files.
- Run migrations or seeds.
- Execute `INSERT`, `UPDATE`, `DELETE`, `TRUNCATE`, `DROP`, or any other database mutation.
- Commit, push, merge, rebase, checkout, reset, or change branches.
- Modify `.env`, `.claude/`, or `.qwen/`.
- Print secrets or raw credentials.
- Call 9Router, Gemini, or another external model/provider.
- Implement any part of the plan.

## Repository context

- Workspace: `D:\TalentHub`
- Expected branch: `feature/student`
- Baseline commit to verify: `bb167d97d2043f2ce71b78c1f62f9c9ae6d584d4`
- Do not push or merge into `develop`.
- Preserve pre-existing untracked `.claude/` and `.qwen/` content.
- Stack: PHP 8.3, PDO, MySQL/MariaDB, PHP sessions, JSON APIs, and plain HTML/CSS/JavaScript.
- Treat roadmap statuses and unchecked boxes as historical evidence, not current truth. Verify every conclusion against the code, schema, Git history, and tests at `HEAD`.

## Required evidence review

Before designing the plan, inspect at minimum:

- Repository instructions such as `AGENTS.md` or `CLAUDE.md`, if present.
- `TalentHub_Student_Portal_Work.docx`, if it exists or is available.
- `docs/superpowers/plans/2026-08-14-student-portal-completion-roadmap.md`
- `docs/superpowers/readiness/student-production-foundation.md`
- `docs/superpowers/readiness/learner-ai-release-checklist.md`
- `docs/superpowers/readiness/learner-ai-evaluation-gate.md`
- `docs/superpowers/database-change-requests/2026-08-20-complete-ai-demo-dataset.md`
- All files under `Database/migrations/**`.
- Current schema definitions and database blueprints.
- `app/learner/**`
- `assets/js/learner*.js`
- `src/Modules/Student/**`
- `app/teacher/**` and `src/Modules/Teacher/**`
- `app/school/**` and `src/Modules/School/**`
- `app/enterprise/**` and the corresponding Business/Enterprise modules under `src/**`.
- Shared authentication, sessions, RBAC, routing, database connection, migrations, audit, and error-handling infrastructure.
- Relevant learner, teacher, school, enterprise, QR, assessment, AI, and migration tests.

If the Word document is unavailable, state that limitation explicitly. Do not invent requirements from it. Use the repository roadmap and current implementation as the evidence baseline.

## Current-state hypotheses to verify

Do not assume these claims are correct. Confirm, reject, or qualify each one with file-level evidence.

### Implemented or partially implemented

- Authenticated student identity from session and database.
- Basic student-profile read/update APIs.
- CSRF, RBAC, permission, and learner-ownership foundations.
- Real assessment catalog, start/resume, autosave, submit, server-side scoring, versioning, and retake policy.
- AI consent events, recommendation persistence, and recommendation feedback.
- Rule recommendations are learner-visible.
- A 9Router-backed model has run in Shadow mode for two demo learners.
- AI validation, evidence, rate limits, audit, Rule fallback, and learner isolation have tests.
- Teacher QR sessions support active, expired, and revoked states.

### Missing or incomplete

1. Database-backed Dashboard and Talent Passport aggregates.
2. Profile bio, headline, location, avatar, and full field ownership.
3. Certificate CRUD and skill-evidence management.
4. General privacy consent and controlled profile sharing with selected fields, expiry, and revocation.
5. CV/Talent Passport export and identity QR if required by the source specification.
6. Real activity registration and cancellation APIs.
7. Capacity, waitlist, approval, schedule conflict, and cancellation deadlines.
8. Learner camera scanning, manual-token fallback, and transactional QR check-in.
9. Check-in history and confirmed experience hours.
10. Assessment history, aggregate results, post-submit notifications, and appeals if required.
11. Complete partner and opportunity data flows.
12. Application submit/withdraw APIs, immutable consent snapshot, and status timeline.
13. Notification Center, unread count, mark-read/read-all, and preferences.
14. Badge rule engine, idempotent award, levels, and statistics from confirmed data.
15. Consistent loading, empty, error, retry, offline, responsive, and accessibility states.
16. Rate limiting for check-in and applications.
17. Cross-role end-to-end tests and authorization matrices.
18. Student Portal production release gate.
19. AI model-visible release gate.

## Four-role integration requirements

Do not plan the Student Portal as an isolated module. Trace and plan the real workflows below against the existing three-role implementations.

### Activity lifecycle

- Teacher creates, edits, and publishes an activity.
- Student discovers it, registers, is waitlisted or pending, cancels, or is approved.
- Teacher views and manages registrations only where permitted.
- School reads or manages activity information only within its current organizational scope.
- Capacity, registration windows, schedules, and statuses remain consistent for all consumers.
- Notifications originate from committed domain transitions.

### QR check-in and experience

- Teacher creates and revokes QR sessions.
- Student scans with a camera or enters a token manually.
- The backend verifies registration, activity, QR state, expiry, scan limits, and ownership.
- A registration can be checked in only once.
- Check-in, registration transition, and experience-log creation share one transaction.
- Teacher sees check-in results, Student sees history and confirmed hours, and School analytics reads only authorized aggregate data.

### Assessment and evaluation

- Student completes and submits an assessment.
- The server validates, scores, versions, and persists the result.
- Teacher evaluations are visible to Student only after an allowed published state.
- School access is restricted by permission and organizational boundary.
- Determine whether automated assessment, teacher evaluation, and appeal/review require separate state machines.
- Teacher and School must not overwrite deterministic assessment results outside an explicit contract.

### Talent Passport and privacy

- Identify student-declared fields.
- Identify Teacher-, School-, Enterprise-, or system-verified facts.
- Identify immutable or read-only fields.
- Sharing requires consent, a field allow-list, expiry, and revocation.
- Enterprise sees only an authorized shared profile or immutable application snapshot.
- Do not authorize using a client-supplied learner ID or a hard-coded public URL.

### Opportunity and application lifecycle

- Enterprise creates and manages opportunities.
- Student sees eligible opportunities and submits or withdraws an application.
- Submission creates an immutable Talent Passport snapshot under active consent.
- Enterprise sees only applications for its own opportunities.
- Enterprise transitions application status only through an allowed state machine.
- Student sees a real status timeline.
- School sees application data only if an explicit business rule and permission allow it.
- Every transition has appropriate audit and notification behavior.

### Notifications, badges, and statistics

- Notifications originate from real domain events or state transitions.
- Badges are awarded only from confirmed data, never UI clicks.
- Student statistics are scoped to the current student.
- Teacher and School analytics use their own authorized aggregates rather than personal Student endpoints.
- Prevent two roles from independently writing conflicting versions of the same status.

## Database conflict-prevention requirements

Database safety is the highest-priority planning constraint.

### Inventory first

Before proposing a migration, build an inventory of:

- Actual tables and columns.
- Primary keys, foreign keys, unique keys, indexes, and checks.
- ID representation and naming conventions.
- Current status vocabularies.
- Existing migrations and their ownership.
- Every role/module that reads or writes each relevant table.
- Differences among the runtime schema, migration definitions, roadmap, and code assumptions.
- Shared tables consumed by more than one role.

### Required Database Ownership Matrix

For every relevant entity/table, report:

| Field | Required content |
|---|---|
| Table/entity | Actual canonical name |
| Authoritative owner | Module that defines the contract |
| Allowed writers | Roles/services allowed to mutate it |
| Allowed readers | Authorized consumers |
| State-machine owner | Service controlling transitions |
| Existing consumers | Current files/modules |
| Proposed change | Exact schema or contract change |
| Compatibility risk | Cross-role failure modes |
| Migration owner | Shared or learner-owned migration area |

### Migration principles

- Keep one authoritative data source for each entity.
- Do not create learner-only duplicate tables for facts already owned by a canonical shared entity unless repository evidence proves isolation is necessary.
- Never edit an applied migration.
- New migrations must be forward-only, idempotent, and backward-compatible.
- Do not rename or drop a column used by another role in the same release.
- List every reader and writer affected by a shared-schema change.
- Use additive nullable/default changes and staged backfills when compatibility requires them.
- Backfills require preflight, explicit ownership scope, invariants, and postflight verification.
- Never bulk-delete or rewrite unrelated existing rows.
- A unique index requires duplicate preflight.
- A foreign key requires orphan preflight.
- A status change requires a compatibility mapping for all consumers.
- Multi-table mutations require transactions and rollback behavior.
- Race-sensitive flows require lock strategy, unique constraints, idempotency semantics, and concurrency tests.
- Store only QR token hashes. Never persist or log raw tokens.
- Do not persist secrets, unnecessary personal data, or raw AI provider payloads.
- Each migration phase requires a verified backup, disposable-schema rehearsal, apply-twice validation, post-migration verification, and rollback or forward-recovery procedure.
- Any required Teacher, School, or Enterprise code change must be a distinct work item with old/new contracts and regression tests protecting current behavior.
- Prefer small migrations with a compatibility window over a single cross-role cutover.

## Security and privacy requirements

The plan must cover:

- Authentication and exact role checks.
- A permission/RBAC matrix for all four roles.
- Student, Teacher, School, Enterprise, and organization ownership boundaries.
- CSRF for every mutation.
- Prepared statements.
- Idempotency for create, submit, check-in, application, and recommendation generation.
- Rate limits for login, check-in, applications, and AI.
- Audit actor, action, entity, request ID, and safe metadata.
- Consent grant/revoke and immutable consent snapshots.
- Field-level Talent Passport privacy.
- Token hashing, expiry, and revocation.
- Safe logging and redaction.
- Negative tests for cross-student, cross-school, and cross-enterprise access.
- Server-resolved identity. Client-supplied `student_id`, `teacher_id`, `school_id`, or `enterprise_id` must never be the authorization source.

## AI requirements

Separate AI readiness into two explicit levels.

### Current safe AI level

- Rule Engine remains learner-visible.
- The model remains Shadow-only.
- Keep `TALENTHUB_AI_VISIBLE_PERCENT=0`.
- Do not schedule a visible rollout during core Student Portal phases.
- AI inputs must be verified database facts covered by active consent.
- Provider failure must return a validated Rule result.
- AI must not make automated admissions, hiring, or consequential evaluation decisions.

### Future model-visible pilot gate

Plan a separate gate requiring:

- Representative Shadow-evaluation sample, not only two heroes.
- 100% response-schema validity.
- 100% evidence coverage.
- Zero unsupported claims.
- Zero unsafe output.
- Consent revocation blocking every model call.
- Provider timeout, failure, retry, and load tests.
- Successful Rule fallback in all simulated provider failures.
- Approved p50/p95 latency thresholds.
- Approved cost-per-run thresholds.
- Independent security, privacy, and bias review.
- Monitoring, alerting, and rollback.
- Explicit Product Owner approval of a nonzero pilot percentage.

Never equate two successful Shadow runs with production readiness.

## Preferred plan decomposition

Validate this order against actual dependencies and change it when repository evidence supports a safer sequence:

1. Phase 0 — Repository, database, and cross-role contract audit.
2. Phase 1 — Shared contracts, status vocabularies, RBAC, and migration safety foundation.
3. Phase 2 — Dashboard, Talent Passport, and profile field ownership.
4. Phase 3 — Privacy consent, certificates, skill evidence, and controlled sharing.
5. Phase 4 — Activity registration, cancellation, approval, and waitlist.
6. Phase 5 — Learner QR check-in and confirmed experience.
7. Phase 6 — Assessment history, evaluation integration, and appeals if required.
8. Phase 7 — Ecosystem, opportunities, and application lifecycle.
9. Phase 8 — Notification Center and preferences.
10. Phase 9 — Badge engine, levels, and personal statistics.
11. Phase 10 — Cross-role UI/API integration, accessibility, error states, and security hardening.
12. Phase 11 — Full four-role E2E, migration rehearsal, and Student Portal release gate.
13. Phase 12 — AI evaluation at scale and model-visible pilot gate.

Explain the dependency graph and critical path.

## Required content for every phase

For every phase include:

- Goal.
- Evidence-backed current status.
- Prerequisites and entry gate.
- Exact files expected to be created or modified.
- Tables, columns, indexes, and migrations involved.
- Endpoint, HTTP method, request, response, and error contracts.
- Domain service and repository responsibilities.
- Transaction boundary and state transitions.
- Teacher, School, and Enterprise interactions.
- Authorization and privacy rules.
- Idempotency, concurrency, and rollback behavior.
- Frontend changes.
- Test-first execution sequence.
- Unit, integration, MySQL, UI, and E2E tests.
- Negative authorization tests.
- Migration preflight and postflight.
- Acceptance criteria and exit gate.
- Proposed atomic commit boundary.
- Risks, blockers, and Product Owner decisions.

## Required implementation precision

Do not use vague work items such as “add an API,” “update the database,” “write tests,” or “ensure security.”

For each item identify, where evidence permits:

- Endpoint and HTTP method.
- Existing or proposed permission.
- Responsible service and repository.
- Tables, columns, constraints, and indexes.
- Transaction start and end.
- Rows requiring `FOR UPDATE`.
- Unique constraints preventing duplicates.
- Valid state transitions.
- Expected `401`, `403`, `404`, `409`, and `422` behavior.
- Ownership and rollback tests.
- Existing consumers in Teacher, School, and Enterprise that may be affected.

The plan must be detailed enough that another engineering agent can implement a phase without making new architectural decisions.

## Mandatory output structure

Write the plan in Vietnamese. Preserve exact file, class, table, column, endpoint, permission, and status names in English.

Return these sections:

1. Executive conclusion.
2. Repository evidence and inspection limitations.
3. Current-state capability matrix: `Complete`, `Partial`, `Missing`, or `Blocked`.
4. Four-role responsibility matrix.
5. Cross-role workflow and state-transition maps.
6. Database inventory and ownership matrix.
7. Schema-gap and compatibility analysis.
8. API, permission, and consumer matrix.
9. Phased implementation plan.
10. Migration safety plan.
11. Test strategy and authorization matrix.
12. AI Shadow-to-pilot release plan.
13. Dependency graph and critical path.
14. Risk register.
15. Product decisions requiring confirmation.
16. Definition of Done for each phase.
17. Final Definition of Done for the complete Student Portal.
18. Recommended implementation order and reasoning.
19. Specifications, plans, DCRs, and readiness documents to create in a later implementation session.

Finish with:

- A database-conflict review checklist.
- A Teacher/School/Enterprise regression checklist.
- A traceability matrix: requirement → phase → API/service → table → test → acceptance criterion.

## Conclusion rules

- Distinguish demo schema/data from a real user-facing write API.
- Distinguish a visible UI control from a completed backend workflow.
- Distinguish functional Shadow AI from model-visible production readiness.
- Do not mark a phase complete based only on mock data, fixtures, seeds, UI-only state, or `localStorage`.
- Support conclusions with repository paths and line-level evidence when practical.
- Record every unverified assumption as an assumption or blocker.
- If the database is unavailable, complete the static audit and mark runtime verification `BLOCKED`; do not change configuration to bypass the gate.
- Preserve the current data and behavior of all four roles.
- Stop after returning the plan. Do not implement it.
