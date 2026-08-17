# Student Data Foundation Review Fixes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make learner database mode render-safe, route-compatible, visibility-safe, and contract-compatible with mock mode.

**Architecture:** Add five domain compatibility read-models between repository records and learner pages. Route resolution happens at this boundary, database repositories keep authoritative UUIDs, and activity visibility is also enforced in database SQL. Mock rows are mapped to snake_case before UUID and enum normalization.

**Tech Stack:** PHP 8.3, PDO prepared statements, in-memory SQLite integration tests mirroring `Database/Talenthub_DB.sql`, existing plain PHP/Node tests.

## Global Constraints

- Work only on `feature/student`.
- Modify only `app/learner`, learner tests, and related documentation.
- Do not modify schema, `Database/Talenthub_DB.sql`, other role code, or other role mock data.
- Production repositories remain SELECT-only and receive PDO by dependency injection.
- Do not commit, push, or merge.

---

### Task 1: Domain read-model contracts and mock snake_case parity

**Files:**
- Create: `app/learner/data/ReadModel/StudentReadModel.php`
- Create: `app/learner/data/ReadModel/AssessmentReadModel.php`
- Create: `app/learner/data/ReadModel/ActivityReadModel.php`
- Create: `app/learner/data/ReadModel/EcosystemReadModel.php`
- Create: `app/learner/data/ReadModel/ApplicationReadModel.php`
- Modify: `app/learner/data/Support/MockRecordNormalizer.php`
- Modify: `app/learner/data/Mock/*.php`
- Modify: `app/learner/data/bootstrap.php`
- Test: `tests/learner_data_foundation_test.php`

- [x] Add failing assertions that camelCase mock input is returned as snake_case and each domain read-model supplies every field directly indexed by learner pages.
- [x] Run `php tests/learner_data_foundation_test.php` and confirm missing classes/keys fail.
- [x] Implement `KeyMapper::toSnake()` as the first mock normalization step and five focused read-model classes with documented safe defaults.
- [x] Route existing `learner_*` compatibility functions through the domain read-models and rerun the test to green.

### Task 2: Legacy route and UUID resolution

**Files:**
- Modify: `app/learner/data/ReadModel/AssessmentReadModel.php`
- Modify: `app/learner/data/ReadModel/ActivityReadModel.php`
- Modify: `app/learner/data/ReadModel/EcosystemReadModel.php`
- Modify: `app/learner/data/Database/DatabaseAssessmentRepository.php`
- Modify: `app/learner/data/Database/DatabaseActivityRepository.php`
- Modify: `app/learner/data/Database/DatabaseEcosystemRepository.php`
- Modify: learner pages only where generated links must use repository `id`.
- Test: `tests/learner_database_render_test.php`

- [x] Add failing tests for `holland`, `iot-lab`, numeric opportunity IDs, and UUID URLs in database mode.
- [x] Confirm the current implementation throws `LearnerDataMappingException` for legacy route values.
- [x] Resolve known legacy aliases by inspecting repository records; generate database links with repository UUIDs; invalid legacy aliases return not-found instead of UUID exceptions.
- [x] Rerun route and render tests to green.

### Task 3: Student-visible activity states

**Files:**
- Modify: `app/learner/data/Database/DatabaseActivityRepository.php`
- Test: `tests/learner_database_render_test.php`

- [x] Seed published, draft, and cancelled rows in the in-memory database and assert draft/cancelled are absent from list and detail.
- [x] Confirm RED with current unfiltered SQL.
- [x] Add prepared status parameters and the same allow-list to list/detail SQL.
- [x] Rerun focused tests to green.

### Task 4: Database-mode compatibility and page rendering

**Files:**
- Create: `tests/learner_database_render_test.php`
- Modify: learner read-models and includes only when a failing render identifies a missing safe default.
- Modify: `docs/superpowers/specs/2026-08-14-student-data-foundation-design.md`

- [x] Build an injected SQLite PDO fixture containing only columns from `Database/Talenthub_DB.sql`.
- [x] Render learner assessment, activity, ecosystem, partner, opportunity, and application compatibility paths under database config; convert warnings/notices to exceptions.
- [x] Assert UUID links are emitted, legacy routes do not throw, missing schema presentation fields receive safe defaults, and hidden activities never render.
- [x] Add only the minimal read-model defaults needed for green and document every schema-backed limitation.

### Task 5: Full verification

- [x] Lint all learner PHP and learner PHP tests.
- [x] Run every `tests/learner_*_test.php` and `tests/learner_*_test.js`.
- [x] Run `git diff --check`.
- [x] Audit database production files for DDL/DML/direct `PDO::query()`.
- [x] Audit `git status` and forbidden paths; keep branch uncommitted.
