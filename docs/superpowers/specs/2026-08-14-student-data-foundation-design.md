# Student Data Foundation Design

## Scope and constraints

Student Data Foundation is limited to the learner module on `feature/student`. It may modify files under `app/learner`, learner-owned assets, learner tests, and this documentation. It may read `Database/Talenthub_DB.sql` and other roles' adapters to understand compatibility, but it must not modify `Database/`, `app/enterprise`, `app/school`, teacher code, or mock data owned by another role.

`Database/Talenthub_DB.sql` is the only schema reference. The implementation must never issue `CREATE`, `ALTER`, `DROP`, migrations, seeds, `INSERT`, `UPDATE`, or `DELETE`. Database repositories are read-only, receive `PDO` through dependency injection, and use prepared statements. They do not contain connection settings. Missing tables or columns are reported by the database driver; the learner layer wraps the error with repository and operation context without attempting to repair the database.

Mock remains the default data source. Database is selected only through explicit learner configuration and a supplied `PDO`. Selecting database without `PDO` raises a clear configuration exception and never falls back to mock.

No commit, push, or merge is part of this work.

## Architecture

The foundation uses five domain contracts:

- `StudentRepository`: finds a learner profile by `student_id`.
- `AssessmentRepository`: reads assessment/test definitions, questions, learner attempt history, and teacher evaluations of the learner.
- `ActivityRepository`: reads the activity catalog, activity details, and learner registrations.
- `EcosystemRepository`: reads school/enterprise partners and opportunities.
- `ApplicationRepository`: reads a learner's internship applications.

Each contract has a mock implementation and a database implementation. A learner-only `RepositoryFactory` selects the implementation from configuration. Compatibility functions such as `learner_activity_catalog()` and `learner_ecosystem_opportunities()` remain the presentation boundary so existing learner pages do not depend on `PDO` or implementation classes.

Five domain read-model adapters sit between those compatibility functions and the repository contracts: `StudentReadModel`, `AssessmentReadModel`, `ActivityReadModel`, `EcosystemReadModel`, and `ApplicationReadModel`. They preserve the learner UI array shape without adding presentation-only columns to the shared schema. Missing presentation values receive conservative defaults and a `data_notes` entry that names the defaulted field. The defaults are display-only and are never persisted.

Repository output uses snake_case. Database rows are selected with the camelCase column names that exist in the schema and passed through a key mapper. Shared relationship keys are `student_id`, `school_id`, `enterprise_id`, and `activity_id`.

## Configuration and error behavior

Learner configuration accepts a source value of `mock` or `database` and an optional `PDO`. Default configuration is `mock` with no PDO. The factory rejects unsupported source names. It also rejects `database` without PDO by throwing `LearnerDataConfigurationException`.

Database query failures are rethrown as `LearnerDataQueryException`. The message identifies the repository operation and preserves the original exception as the cause. No database exception activates mock fallback.

## UUID policy

Database UUID values are treated as authoritative identifiers and normalized to lower-case RFC 4122 text. Invalid database identifiers cause a mapping exception rather than being replaced.

Existing learner mock records use slugs and integers. Mock repositories deterministically derive compatibility UUIDs from the entity type and legacy value. A normalized mock record retains `legacy_id` and declares `id_origin: mock_compat`. These UUIDs exist only inside the mock contract; they are never treated as real database IDs and no write path exists that could persist them.

Compatibility adapters accept legacy route identifiers and canonical UUIDs, then preserve the page-facing shape required by the existing learner UI.

In database mode, `holland` is resolved by matching the database assessment name/type, and activity slugs such as `iot-lab` are resolved against the schema-backed activity title. Repository UUIDs remain the canonical IDs returned to pages and are used in newly generated links. Numeric legacy opportunity routes are accepted at the compatibility boundary: if no matching mock legacy value exists in database mode, the adapter returns not-found without passing the number to strict UUID validation. Database ecosystem lists generate opportunity URLs with the UUID returned by the repository.

The `holland` string remains a URL-resolution alias only. Holland runner, result, and discover boot payloads use the resolved assessment UUID, and browser localStorage scopes drafts/results by that UUID. This allows an attempt submitted from `assessment.php?id=holland` to be found by `assessment-result.php?id=holland` without persisting the legacy alias.

## Status contracts

PHP backed enums define learner-internal status contracts for student study state, assessment attempts, activities, activity registrations, opportunities, and applications. Every enum contains `unknown`. External values that are not in the learner contract normalize to `unknown` because the team-wide status lists are not yet approved. These enums do not alter or constrain other modules.

## Database table coverage

The database implementations only read these existing tables and fields:

- Student: `student_profiles` (`id`, `userId`, `classId`, `dateOfBirth`, `phone`, `studyStatus`), joined to `users`, `classes`, and `schools` through their existing keys.
- Assessment: `talent_tests`, `test_questions`, `test_attempts`, `test_results`, `assessments`, `assessment_scores`, and `assessment_criteria`.
- Activity: `activities` and `activity_registrations`.
- Ecosystem: `schools`, `enterprises`, and `internship_posts`.
- Application: `internship_applications`, joined to `internship_posts` and `enterprises` for learner-facing context.

All identifiers and filter values are bound parameters. Table and column names are fixed constants in repository code and come directly from the reference schema.

`DatabaseActivityRepository` applies a student-visible allow-list (`published`, `active`, `closed`, and `completed`) in prepared list and detail queries. `draft`, `cancelled`, and unapproved/unknown values are excluded at SQL level, so a hidden row cannot be reached through a detail UUID.

`DatabaseEcosystemRepository` applies the same visibility rule to collection, partner-detail, opportunity-detail, and partner-opportunity reads. Schools must be `active`; enterprises must be `active` and have `verificationStatus` of `verified` or `approved`; internship posts must be `active`. Opportunity SQL also checks the joined enterprise, preventing an active post from an inactive or unapproved enterprise from appearing.

Every activity read model exposes `can_register`. It is true only for `published` or `active` activities when the current time is inside the registration window. When the shared schema has no registration window, the existing conservative compatibility rule uses `startAt` as `registration_closes_at`; invalid or missing closing timestamps are non-registerable. PHP renders a disabled action when false, and JavaScript rechecks status/window before writing a local registration.

## Known schema gaps

The current schema cannot fully reproduce all learner mock presentation fields:

- Student lacks location, verification, streak, experience-hour summary, initials, and several profile/dashboard metrics.
- Talent tests lack version, publication state, duration, retake policy, disclaimer, question order, per-question RIASEC dimension, required flag, and stored answers. Numeric or `{value, label}` question options are normalized to `{value, label}`, but the learner layer never invents a missing dimension. Therefore a schema-backed Holland set without 24 explicitly dimensioned, otherwise valid questions is shown as `Bài test chưa sẵn sàng` and cannot create an attempt result. `test_attempts` has no explicit status.
- Activities lack description, summary, location, format, registration windows, cancellation window, approval mode, skills, requirements, benefits, cost, organizer contact, and presentation tone.
- Schools lack address/contact/description/program/facility/event data. Internship posts lack description, work type, duration, education level, slots, applicant count, skills, benefits, and requirements. School opportunities have no schema table.
- Internship applications lack submitted/updated timestamps, withdrawal rules, and timeline events.

Database repositories return only available or safely derived values. The mock source remains the complete default experience until the shared schema and real data cover these gaps.

Current display defaults include empty lists for skills/requirements/benefits/timelines, zero counters, non-actionable placeholder dates where the existing template requires a parseable date, neutral labels, and `capacity = 1` only when necessary to prevent division by zero in the unchanged activity progress UI. These defaults indicate missing schema coverage through `data_notes`; they are not claims about real learner or partner data.

## Data flow

1. A learner page loads its existing learner data include.
2. The include asks the learner repository provider for a contract.
3. The provider uses the factory and learner configuration to select mock or database.
4. The repository normalizes keys, UUIDs, and statuses.
5. A compatibility adapter builds the unchanged page-facing array shape.

No page constructs PDO, contains SQL, or chooses a repository implementation.

## Testing

Tests follow red-green-refactor and cover:

- deterministic mock UUIDs, UUID origin metadata, snake_case mapping, and unknown statuses;
- preservation of semantic JSON keys such as uppercase RIASEC dimensions while field names become snake_case;
- factory default selection, explicit database selection, unsupported sources, and missing-PDO errors;
- contract behavior for all five mock repositories;
- database repository SELECT behavior using injected PDO test doubles or an in-memory compatible driver, including prepared parameters and query-error propagation;
- static checks that learner database repositories contain no write or DDL statements;
- existing learner data, render, and JavaScript regression tests.
- database-mode compatibility and render coverage for activity catalog/detail, Holland runner/result, ecosystem hub, partner detail, and opportunity detail, with warnings/notices promoted to test failures;
- legacy route resolution, UUID link generation, missing-field defaults, and exclusion of draft/cancelled activities from both list and detail.
- direct JavaScript contract coverage for database-shaped Holland options/readiness/submission/history and activity registration eligibility, rather than relying only on HTML assertions;
- exclusion of in-progress assessment attempts from completed history, canonical assessment UUID localStorage matching, inactive/unapproved partners, hidden opportunities, and closed/completed/expired registration attempts.

Verification includes PHP syntax checks, all learner PHP tests, all learner Node tests, branch/status inspection, and a final diff review confirming that forbidden paths were not modified.
