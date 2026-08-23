# Phase 2 Talent Passport Design

## 1. Decision

Implement Phase 2 as a database-backed Talent Passport aggregate without creating the future `certificates`, `projects`, `project_members`, `badges`, or `student_badges` tables.

Phase 2 reads canonical facts that already exist. Future-domain facts are optional capabilities and return an explicit empty list when their owning migrations have not been applied. Certificate and project writes remain owned by Phase 3. Badge definitions and awards remain owned by Phase 9.

This design supersedes the current readiness behavior that incorrectly blocks Phase 2 when future-domain tables are absent. It does not change the reserved migration IDs or ownership recorded in the four-role completion plan.

## 2. Scope

### In scope

- Correct Phase 2 readiness requirements.
- Add a single Talent Passport repository contract and database implementation.
- Add a normalized Talent Passport read model.
- Wire the repository through the existing learner repository factory and bootstrap.
- Replace database-mode Dashboard and Profile demo aggregates with canonical database facts.
- Preserve deterministic mock fixtures only for explicit test/demo source mode.
- Provide safe empty states for certificate, project, and badge sections.
- Preserve four-role ownership and AI safety boundaries.
- Add focused, integration, rendering, security, and regression tests.

### Out of scope

- Creating or modifying database tables.
- Applying migrations, seeds, or production DML.
- Certificate or project commands.
- Consent, profile sharing, or Enterprise Talent Passport access.
- Badge rules, badge awarding, or badge progress calculation.
- Changing Teacher, School, or Enterprise write flows.
- Enabling learner-visible AI model output.

## 3. Architecture

The Phase 2 data flow is:

```text
Authenticated Student context
  -> TalentPassportRepository::aggregateForStudent(studentId)
  -> DatabaseTalentPassportRepository
  -> TalentPassportReadModel
  -> app/learner/includes/student-data.php
  -> Learner Dashboard / Profile / approved AI fact source
```

### Components

1. `TalentPassportRepository`
   - Exposes `aggregateForStudent(string $studentId): array`.
   - Does not expose school-wide or arbitrary-user queries.

2. `DatabaseTalentPassportRepository`
   - Uses prepared statements.
   - Applies `studentId` scope to every learner-owned query.
   - Reads required canonical facts.
   - Checks optional-table capabilities before querying future domains.
   - Does not catch database connection or required-query failures as mock data.

3. `TalentPassportReadModel`
   - Normalizes repository records into one stable aggregate.
   - Provides explicit empty lists and null timestamps.
   - Does not generate facts, timestamps, verification states, or KPI values.

4. Existing `RepositoryFactory` and learner bootstrap
   - Construct the database repository in database mode.
   - Construct deterministic mock behavior only when the existing source policy explicitly allows mock mode.

5. `student-data.php`, Dashboard, and Profile
   - Consume the normalized aggregate.
   - Do not query database tables directly.
   - Do not merge browser-local or hard-coded facts into database results.

## 4. Aggregate Contract

The normalized aggregate has this semantic shape:

```php
[
    'student' => [],
    'skills' => [],
    'experience' => [
        'confirmed_hours' => 0.0,
        'confirmed_entries' => [],
    ],
    'assessment_results' => [],
    'teacher_evaluations' => [],
    'activity_summary' => [],
    'certificates' => [],
    'projects' => [],
    'badges' => [],
    'source_timestamps' => [],
    'capabilities' => [],
]
```

The implementation may add named fields required by the current UI, but it must preserve these domain boundaries and must not collapse automated assessment results into Teacher evaluations.

### Canonical facts

- Student identity and education context: `student_profiles`, `users`, `classes`, and `schools`.
- Skills: `student_skills` joined to `skills`, preserving source and verification facts when present.
- Experience: only `experience_logs.status = 'confirmed'`.
- Activity summary: canonical registrations, check-ins, and confirmed experience without inferring attendance from unrelated rows.
- Automated assessments: only submitted attempts with persisted results.
- Teacher evaluations: only `assessments.status = 'published'`, including published criteria scores when available.
- Source timestamps: persisted timestamps only; unavailable timestamps are `null`.

### Optional future facts

- `certificates`
- `projects` through `project_members`
- `badges` through `student_badges`

When an optional table group is absent, its aggregate field is `[]`. Database mode must never fill the field from demo arrays.

## 5. Readiness and Capability Rules

Phase 2 readiness must distinguish required current facts from optional future facts.

### Required

Phase 2 requires these current canonical table groups:

- Identity and education: `users`, `student_profiles`, `classes`, `schools`.
- Skills: `student_skills`, `skills`.
- Activity and experience: `activities`, `activity_registrations`, `checkins`, `experience_logs`.
- Automated assessment results: `talent_tests`, `test_attempts`, `test_results`.
- Teacher evaluations: `assessments`, `assessment_scores`, `assessment_criteria`.

Readiness validates the primary/foreign-key columns, status columns, ownership columns, and unique indexes actually used by the final prepared queries. The implementation plan must name those columns and indexes before production code is written; it may not weaken this table list to make readiness pass.

If a required table or required canonical column is absent, readiness fails with the exact missing object.

### Optional

Certificate, project, and badge table groups are not Phase 2 entry requirements.

- Entire group absent: capability is unavailable and the aggregate returns `[]`.
- Group present with the compatible schema expected by its owning phase: capability may be read.
- Group partially present or incompatible: capability is unavailable, diagnostics record only schema metadata, and the aggregate returns `[]` without issuing an incompatible domain query.

Phase 2 does not create, alter, or repair an optional table.

## 6. Authorization and Four-Role Boundaries

- The Student ID comes from the authenticated learner context, not from a query-string selector.
- A Student can read only their own aggregate.
- Teacher remains the owner of evaluation draft/publish lifecycle. Only published evaluations become learner-visible.
- School services continue reading canonical shared tables and are not routed through the learner read model.
- Enterprise receives no Talent Passport access in Phase 2. Enterprise access requires Phase 3 consent and sharing.
- Learner endpoints do not mutate Teacher-, School-, or Enterprise-owned states.
- Cross-role regression tests must prove that the new repository and wiring do not alter existing role routes or permissions.

## 7. AI and Privacy Boundaries

- The aggregate may feed only existing approved AI fact-selection policy.
- Unverified or unavailable certificate, project, and badge facts are not substituted with mock facts.
- Teacher comments and assessment answers are not included in diagnostics.
- Diagnostics may contain only source mode, capability availability, missing schema object names, and query time.
- Diagnostics must not contain names, email addresses, phone numbers, assessment answers, Teacher comments, tokens, or other PII.
- `TALENTHUB_AI_VISIBLE_PERCENT` remains unchanged at `0`.

## 8. UI Behavior

Database mode:

- Dashboard and Profile render canonical facts from the aggregate.
- A numeric KPI is `0` only when zero is a truthful aggregate of available facts.
- An unavailable or not-yet-modeled fact displays “Chưa có dữ liệu” rather than a fabricated number.
- Certificate, project, and badge sections render explicit empty states.
- Automated assessments and Teacher evaluations use separate source labels.
- No certificate, project, or badge mutation controls are added.
- No local storage or fake-success path is used.

Explicit test/demo mock mode may retain deterministic fixtures under the existing environment and source restrictions.

## 9. Failure Handling

- Database connection failure uses the existing safe 503 boundary.
- Missing authenticated Student produces the existing safe authorization/not-found behavior; it never loads the demo Student.
- Failure of a required domain query fails the aggregate instead of rendering a mixture of real and fabricated data.
- Missing optional tables are normal empty-state conditions.
- Malformed optional historical records may be omitted from that optional collection with non-PII diagnostics.
- Malformed required identity or ownership data fails safely because student isolation cannot be weakened.

## 10. Testing Strategy

Implementation follows RED-GREEN-REFACTOR.

### Focused contract tests

- Repository contract and stable aggregate keys.
- Student A cannot read Student B facts.
- Required-table absence blocks readiness with a precise reason.
- Optional-table absence does not block Phase 2 and returns `[]`.
- Optional incompatible schema is not queried and returns `[]` with safe capability diagnostics.
- Only confirmed experience contributes hours.
- Only submitted attempts with results appear.
- Only published Teacher evaluations appear.
- Null and empty timestamps are not replaced with the current time.

### Render and source tests

- Database Dashboard and Profile contain canonical fixture facts.
- Database pages do not contain prior demo KPI, certificate, project, or badge values.
- Empty sections render accessible empty-state content.
- Database render integration must reach an explicit final `OK` marker; an early exit is a failure.
- Mock fixtures remain available only in explicitly allowed test/demo mode.

### Regression and security tests

- Student, Teacher, School, and Enterprise contracts remain green.
- Existing assessment, activity, QR, recommendation, and API-client tests remain green.
- AI source tests prove no fabricated optional facts and no PII diagnostics.
- Prepared-query and ownership tests cover hostile or foreign IDs.
- PHP lint, migration validation, readiness, render integration, and `git diff --check` pass.

## 11. Database and Delivery Invariants

- No migration, seed, or DML is run for Phase 2.
- `talenthub_local` row counts and migration state remain unchanged.
- Learner migrations `001` through `004` remain byte-identical.
- Reserved shared migration IDs `20260821000100` through `20260821000700` remain unclaimed by Phase 2.
- Existing `.env`, `.claude/`, and `.qwen/` content is not changed.
- No commit, push, or merge is performed without separate user authorization.

## 12. Acceptance Criteria

Phase 2 is complete only when all of the following are proven with fresh evidence:

1. Phase 2 readiness passes while the five future tables are absent.
2. Dashboard and Profile database mode use persisted canonical facts.
3. Certificate, project, and badge collections are exactly `[]` when unavailable.
4. Database mode contains no static or browser-local fact fallback.
5. Student isolation and four-role ownership regressions pass.
6. The primary database is not mutated.
7. All focused and regression tests pass with zero failures.
8. Database render integration reaches its explicit `OK` marker.
9. The review report lists files, tests, database state, invariants, remaining risks, and a PASS/FAIL decision.

Phase 3 may start only after the Phase 2 report is reviewed and approved.
