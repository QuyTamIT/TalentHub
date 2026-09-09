# Issue07 backend contract

User approved minimal teacher review for project submissions and internship reports. Do not edit ISSUES_LOG.md. Work in existing feature/student-clean at D:/TalentHub; preserve unrelated dirty files, no commits. Parent implements HTTP/UI/CV. This task owns migration, domain repository and its tests only.

## Deliverables

Create migration `Database/migrations/learner/021_create_learner_portfolio_reports.php` using ForwardMigrationDefinition/LearnerForwardMigration and SQLite/MySQL statements; additive tables only, no alter/drop old tables. Do not apply to real database (parent handles). Create autoloadable `src/Modules/Student/Repository/PortfolioRepository.php` and focused `tests/learner_portfolio_repository_test.php`. Use TDD. PHP executable D:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe. Tests use in-memory SQLite and migration statements, no real learner writes.

## Fixed interfaces

`PortfolioRepository(PDO $pdo)` methods:
- `listForStudent(string $studentId): array` returns `{projects: array, internships: array}`. Include eligible parent projects (active membership) and accepted applications with no report yet, plus own report history even after leaving. Each item camelCase: `kind` project/internship, `contextId` projectId/applicationId, `title`, `organization` school/company, `mentorName`, `report` nullable row described below. No other student data.
- `listForTeacher(string $teacherUserId): array` returns flat items using same keys plus `studentName`, `studentId`, only current assigned mentor of same school, only submitted/changes_requested/verified/revoked reports (including reviewed ones for revoke). Drafts stay private to learner. No admin bypass.
- `save(string $studentId, string $kind, string $contextId, int $expectedVersion, array $input): array`: create/edit draft with keys `notes`, `repositoryUrl`, `demoUrl`, `startDate`, `endDate`, `hours`, `stage` (active/completed, internship only), `submit` boolean, `newRevision` boolean. expectedVersion=0 creates; normal save only draft/changes_requested; newRevision=true allows verified/revoked to new draft and preserves history; submitted immutable until teacher requests changes. Returned report row.
- `review(string $teacherUserId, string $kind, string $reportId, int $expectedVersion, string $decision, string $feedback, array $skillIds=[]): array`: decision verified/changes_requested from submitted; revoked from verified only. Require feedback for rejection/revocation. Teacher must equal projects.mentorTeacherId or internship_mentor_assignments.mentorTeacherId, and match student's/current parent school. Current teacher DB role active required; membership active when verifying project; accepted application when verifying internship. Skill IDs active catalog, max 10 unique, stored only for verified. Same school permission checks also when saving.
- `verifiedForStudent(string $studentId): array` returns `{projects: array, internships: array, skills: array}` from current verified rows ONLY. Current student/parent match must hold. projects rows include `contextId,title,notes,reviewedAt,feedback,reviewerName`; internships include `contextId,title,organization,startDate,endDate,hours,stage,reviewedAt,feedback,reviewerName`; skills `{name,skillId,kind,reportId,reviewedAt,reviewerName}`. Read-only, no cache. Revoked/new draft/changes requested never treated as verified. Deduplicate in CV parent.

Report shape: id,studentId,contextId,version (optimistic lock increment every mutation),revision (learner new submission revision count),status draft/submitted/changes_requested/verified/revoked,notes,repositoryUrl,demoUrl,startDate,endDate,hours,stage,submittedAt,reviewedAt,reviewedByUserId,feedback,updatedAt,history array newest last (each event includes version,status,actorUserId,createdAt,notes/feedback or full snapshot).

## Persistence and behavior

Separate `project_submissions` and `learner_internship_reports` with unique(studentId,contextId). Additional append-only `learner_portfolio_history` stores each mutation snapshot+kind+reportId+version+actorUserId+createdAt; `learner_portfolio_skills` stores kind+reportId+skillId (foreign keys where practical). No overwrite accepted evidence without archived snapshot. No deletes of reports/history. Underlying skills can be replaced for a newly verified revision, history contains prior skill IDs. Atomic transactions and compare-and-swap on version; unique creation conflicts map to ApiException 409. Use ApiException 403/404 ownership,422 input/transition,409 version. SQL identifiers from kind allowlist only.

Validate URLs http/https only, host required, no credentials/control chars; optional links, require notes for submission. Max notes 4000,feedback 2000,URLs 1000; reject over limits not silent truncation. Internship submission requires valid calendar startDate not future; completed requires endDate>=startDate and <=today, hours>0 <=10000; active stage permits blank endDate, no claims completion. Missing mentor blocks submit with clear 422; draft allowed. Missing schema fails clearly, never DDL on requests.

Notifications: reuse existing learner NotificationService and DatabaseNotificationRepository, but DO NOT edit those files (parent adds allowlist values `portfolio_submitted`,`portfolio_reviewed` and links `/app/teacher/portfolio-reviews.php`,`/app/learner/profile.php`). Notification writes in same transaction, do not silently report success after notification failure. Can accept optional injected notifier callable as constructor second argument for SQLite tests (not disabling in real code). Parent can wire callable if needed; agree exact contract before finishing.

Do not change AI/shared outbox code. Parent handles integration with verified sources separately.

## Tests

Test create/draft/submit/changes_requested/resubmit/verified/revoked/newRevision; expectedVersion mismatch; different student and teacher; wrong school; removed membership; unassigned internship; invalid URL/date/hour; no skill from draft; valid skill appears only verified, disappears revoked; no overwrite on invalid/notification failure; history preserved; read ownership. Report test commands and RED/GREEN evidence in `plans/issue-07-backend-report.md` with files created/modified and concerns. Return concise status. Do not edit other files.
