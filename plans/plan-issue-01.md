# Issue 01 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or superpowers:executing-plans to implement task-by-task.

**Goal:** Decouple competency rubric assessment from workshop attendance while keeping learner evidence synchronized.

**Architecture:** Add an explicit assessment context and retain activity attendance as a separate evidence source. Teacher writes rubric rows and publication state; learner reads only the latest published assessment.

**Tech Stack:** PHP, PDO repositories, SQL migrations, learner/teacher PHP views, JavaScript contract tests.

## Scope and files

- Create migration under `Database/migrations/learner/` for nullable `classId`, `subjectId`, `projectId`, publication metadata and removal/replacement of the activity-registration foreign key.
- Modify `src/Modules/Teacher/Repository/TeacherGradingRepository.php`, `app/teacher/grading.php`, `app/teacher/assessments/index.php`, `app/learner/data/Database/DatabaseTalentPassportRepository.php`, `app/learner/evaluation.php`, `app/learner/talent-passport.php`.
- Add focused PHP/static tests under `tests/` for class/project assessment without registration and published learner reads.

## Implementation

## Current implementation record

- Completed: migration `Database/migrations/learner/018_decouple_competency_assessments.php`; nullable legacy activity context, `classId`, `projectId`, context index and MySQL context check.
- Completed: `TeacherGradingService`/`TeacherGradingRepository` context parameters, class membership authorization, mentor project authorization, and QR-independent save path.
- Completed: learner assessment mapping and `evaluation.php` context labels; `talent-passport.php` displays the current evaluation context.
- Verified: `git diff --check`; PHP contract test is present but cannot run because `php` is unavailable. Migration has not been run against a configured live database.

### Handoff to Teacher Portal

The learner/database portion is ready for integration. Teacher Portal must still:

1. Add class and project selectors to `app/teacher/assessments/index.php`.
2. Add repository queries for teacher-owned classes and mentor projects, including eligible student lists.
3. Pass `classId`/`projectId` through save, draft, publish, redirect and audit flows.
4. Add teacher tests for no-registration class grading, mentor project grading, cross-school denial and immutable published assessments.

- [x] Inspect actual assessment schema and add a migration contract test for a class/project assessment without `activity_registrations`.
- [x] Add context columns and a reversible compatibility migration; retain legacy `activityId` for historical rows.
- [x] Change service/repository methods to accept context, criteria scores, total, feedback and publication state in one transaction.
- [x] Add server-side class membership and mentor project authorization checks.
- [x] Change learner repository queries to select published assessments by learner/context while leaving activity history separate.
- [x] Render context-aware published assessment labels in `evaluation.php` and `talent-passport.php`.
- [ ] Run migration on a configured database and execute PHP/teacher regression tests; acceptance requires no QR registration for class/project grading and exact score/feedback parity on learner pages.

Chi tiết bàn giao kỹ thuật Giảng viên và liên thông Học viên: [handoff-issue-01-teacher.md](handoff-issue-01-teacher.md).
