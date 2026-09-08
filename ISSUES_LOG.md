# Student subsystem issues log

**Branch:** `feature/student-clean`  
**Scope:** learner/student subsystem plus the smallest shared schema/API changes required for data flow.  
**Status:** recorded for implementation; no code change is implied by this log.

## Priority order

| Priority | Issue | Severity | Current impact | Detailed plan |
|---:|---|---|---|---|
| 1 | #01 Separate competency rubric assessment from workshop/QR attendance | Critical / architecture | Rubric data is coupled to activity registration and teacher grading does not consistently persist rubric evidence. | [View plan](plans/plan-issue-01.md) |
| 2 | #03 Add lifecycle and participation filters in Ecosystem | High / progress UI | Learner project list is limited to `in_progress`; opportunity view has no unified learner status filters. | [View plan](plans/plan-issue-03.md) |
| 2 | #06 Add internship application tracker | High / recruitment UX | Applications API exists, but learner UI does not show application states. | [View plan](plans/plan-issue-06.md) |
| 2 | #07 Add internship experience to profile and project submission flow | High / portfolio workflow | Profile has no internship section; project detail has no submission/acceptance workflow. | [View plan](plans/plan-issue-07.md) |
| 3 | #08 Make assessment retake a soft 90-day confirmation | High / assessment UX | Repository hard-blocks early retakes and UI only exposes a locked state. | [View plan](plans/plan-issue-08.md) |
| 3 | #04 Guarantee one-page A4 talent passport PDF | High / export | Print CSS sets A4 but does not guarantee one page or include internship evidence. | [View plan](plans/plan-issue-04.md) |
| 4 | #02 Sync project skills into AI matching and refresh stale AI results | High / existing AI | Project skill tags are absent from deterministic scoring; generic freshness is hard-coded true and some mutations emit no outbox. | [View plan](plans/plan-issue-02.md) |
| 5 | #05 Add AI activity matching | Medium / new AI feature | Activities page has static filtering only; no activity matching capability exists. | [View plan](plans/plan-issue-05.md) |

## Issue definitions

### #01 — Separate competency rubric assessment from workshop/QR attendance

`DatabaseActivityExperienceSource` treats attendance as experience evidence, but legacy assessment paths still assume an activity context. Teacher grading writes `talentScore` without guaranteeing rubric rows. Introduce an assessment context (`classId`, `subjectId` or `projectId`) independent of `activityId`, preserve published rubric evidence, and migrate existing data safely. A teacher must assess a class/project participant without QR attendance; learner evaluation and passport must show the latest published criteria, total, grade and feedback.

Detailed steps: [plans/plan-issue-01.md](plans/plan-issue-01.md)

### #03 — Ecosystem lifecycle and participation filters

`DatabaseProjectRepository::BASE_SELECT` filters `p.status = 'in_progress'`, so completed projects disappear. Opportunity cards do not carry learner application state. Add canonical filters, membership/application fields, URL persistence and combined filtering.

Detailed steps: [plans/plan-issue-03.md](plans/plan-issue-03.md)

### #06 — Internship application tracker

`applications.php` and `DatabaseApplicationRepository` support listing and withdrawing applications, but no learner page renders history. Add a learner page/tab, status timeline, withdrawal confirmation and notification refresh.

Detailed steps: [plans/plan-issue-06.md](plans/plan-issue-06.md)

### #07 — Internship profile section and project submission

`profile.php` and `DatabaseTalentPassportRepository` do not expose internship history; `project.php` supports membership but no submission record or mentor acceptance. Add a normalized submission record, learner authorization, mentor transitions and profile rendering.

Detailed steps: [plans/plan-issue-07.md](plans/plan-issue-07.md)

### #08 — Flexible assessment retake with a 90-day warning

`DatabaseAssessmentWriteRepository` throws before 90 days and the result UI renders `retake_locked`. Change this to two-step confirmation, keep newest result current, and preserve prior attempts in an auditable history API.

Detailed steps: [plans/plan-issue-08.md](plans/plan-issue-08.md)

### #04 — One-page A4 talent passport PDF

`talent-passport.php` relies on `window.print()` and print CSS; there is no measured one-page acceptance test and internship data is absent. Add a print data budget, explicit A4 constraints, internship section and visual PDF verification.

Detailed steps: [plans/plan-issue-04.md](plans/plan-issue-04.md)

### #02 — Project skill sync and AI freshness

`AiSourceRegistry` can include project rows, but the project query does not provide skill tags. `LearnerApiContext` passes a freshness callback that always returns true; project completion and some internship updates do not publish outbox events. Fix evidence mapping, current-snapshot comparison, mutation coverage and stale-state UI.

Detailed steps: [plans/plan-issue-02.md](plans/plan-issue-02.md)

### #05 — AI activity matching

`activities.php` and `DatabaseActivityRepository` provide catalog/list data, while opportunity/job matching provides reusable snapshot, scorer, prompt, persistence and UI patterns. No activity matching capability exists. Implement this last after the preceding evidence and freshness contracts are stable.

Detailed steps: [plans/plan-issue-05.md](plans/plan-issue-05.md)

## Delivery rule

Implement in priority order. Verify each plan independently before changing shared learner data contracts. Run learner static/JS tests after every plan and record unavailable runtimes such as a missing PHP binary.
