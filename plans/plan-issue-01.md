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

- [ ] Inspect actual assessment schema and all writes; write a failing test that creates a class participant with no `activity_registrations` row and expects an assessment plus rubric scores.
- [ ] Add the context columns and a reversible data migration; retain legacy `activityId` only for historical rows and remove the hard foreign-key dependency after backfill validation.
- [ ] Change teacher repository methods to accept `studentId`, context, criteria scores, total, grade, feedback and `published`; wrap assessment plus scores in one transaction.
- [ ] Update teacher UI to select class/project and publish; reject a student outside the selected context with a server-side authorization check.
- [ ] Change learner repository queries to select published assessments by learner/context and map criteria, total, grade and feedback; leave activity history queries unchanged.
- [ ] Render the latest published rubric in `evaluation.php` and passport, with an explicit empty state when no assessment exists.
- [ ] Run the focused tests, migration on a disposable database, and learner/teacher regression tests; acceptance requires no QR registration for grading and exact score/feedback parity on learner pages.
